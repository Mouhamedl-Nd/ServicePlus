<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('admin', '../');

/* ── Actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_presta'])) {
    $id = (int)$_POST['user_id'];
    $action = $_POST['action_presta'];

    if (in_array($action, ['valider','refuser'])) {
        $statut = $action === 'valider' ? 'valide' : 'refuse';
        $pdo->prepare('UPDATE prestataire_profils SET statut_validation=?, admin_validateur_id=?, date_validation=NOW() WHERE user_id=?')
            ->execute([$statut, $_SESSION['user_id'], $id]);
        creerNotification($pdo, $id,
            $statut==='valide' ? 'Compte validé ✅' : 'Inscription refusée',
            $statut==='valide' ? 'Votre dossier prestataire a été validé. Vous pouvez maintenant vous connecter.' : 'Votre dossier prestataire a été refusé. Contactez le support.',
            $statut);
    } elseif ($action === 'suspendre') {
        $pdo->prepare("UPDATE utilisateurs SET statut='suspendu' WHERE id=?")->execute([$id]);
        creerNotification($pdo, $id, 'Compte suspendu', 'Votre compte a été suspendu par l\'administrateur.', 'suspendu');
    } elseif ($action === 'reactiver') {
        $pdo->prepare("UPDATE utilisateurs SET statut='actif' WHERE id=?")->execute([$id]);
        creerNotification($pdo, $id, 'Compte réactivé', 'Votre compte a été réactivé.', 'actif');
    }
    header('Location: gestion_prestataires.php' . (isset($_GET['statut']) ? '?statut='.urlencode($_GET['statut']) : ''));
    exit;
}

$statutFiltre = $_GET['statut'] ?? 'tous';
$q     = trim($_GET['q'] ?? '');
$page  = max(1, (int)($_GET['page'] ?? 1));
$parPage = 10;

$compteurs = $pdo->query("SELECT statut_validation, COUNT(*) AS nb FROM prestataire_profils GROUP BY statut_validation")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalTous = array_sum($compteurs);

$where = []; $params = [];
if ($statutFiltre !== 'tous') { $where[] = 'pp.statut_validation = ?'; $params[] = $statutFiltre; }
if ($q !== '') { $where[] = '(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)'; $like="%$q%"; array_push($params,$like,$like,$like); }
$whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM prestataire_profils pp JOIN utilisateurs u ON u.id=pp.user_id $whereSql");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($total/$parPage));
$page = min($page, $totalPages);
$offset = ($page-1)*$parPage;

$sql = "SELECT u.id, u.nom, u.prenom, u.email, u.telephone, u.ville, u.statut AS statut_compte, u.date_creation,
               pp.tarif_horaire, pp.note_moyenne, pp.nb_avis, pp.statut_validation, pp.piece_identite, c.nom AS categorie_nom
        FROM prestataire_profils pp
        JOIN utilisateurs u ON u.id = pp.user_id
        JOIN categories c ON c.id = pp.categorie_id
        $whereSql
        ORDER BY u.date_creation DESC LIMIT $parPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prestataires = $stmt->fetchAll();

function urlAvecP(array $r = []): string {
    $p = array_merge($_GET, $r);
    foreach ($p as $k=>$v) if ($v===''||$v===null) unset($p[$k]);
    return 'gestion_prestataires.php?' . http_build_query($p);
}

$page_title = 'Prestataires';
$page_active = 'prestataires';
$extra_css = <<<CSS
.toolbar{display:flex;gap:12px;margin-bottom:18px;flex-wrap:wrap;align-items:center}
.search-input{flex:1;min-width:220px;padding:11px 16px;border-radius:11px;border:1.5px solid var(--border);font-size:13.5px;font-family:'Inter',sans-serif}
.filter-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
.ftab{padding:8px 16px;border-radius:20px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:12.5px;font-weight:700;color:var(--slate);text-decoration:none;display:inline-flex;gap:6px;align-items:center}
.ftab.active{background:var(--primary);color:white;border-color:var(--primary)}
.ftab .cnt{background:#F1F5F9;color:var(--slate);padding:1px 7px;border-radius:20px;font-size:10.5px}
.ftab.active .cnt{background:rgba(255,255,255,.25);color:white}
.table-wrap{overflow-x:auto;border-radius:16px;border:1.5px solid var(--border);background:white}
.data-table{width:100%;border-collapse:collapse;min-width:760px}
.data-table th{background:#FAFBFC;padding:12px 16px;text-align:left;font-size:11px;font-weight:800;color:var(--slate);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
.data-table td{padding:13px 16px;border-bottom:1px solid #F8FAFC;font-size:13.5px;vertical-align:middle}
.data-table tr:last-child td{border-bottom:none}
.data-table tr:hover{background:#FAFBFC}
.pill{font-size:10.5px;font-weight:800;padding:3px 10px;border-radius:20px;text-transform:uppercase;display:inline-block}
.pill.valide,.pill.actif{background:#ECFDF5;color:#059669}
.pill.en_attente{background:#FFFBEB;color:#D97706}
.pill.refuse,.pill.suspendu{background:#FEF2F2;color:#DC2626}
.btn-mini{padding:6px 11px;border-radius:8px;border:none;font-size:11px;font-weight:800;cursor:pointer;font-family:'Inter',sans-serif;text-decoration:none;display:inline-block}
.btn-mini.green{background:#ECFDF5;color:#059669} .btn-mini.green:hover{background:#059669;color:white}
.btn-mini.red{background:#FEF2F2;color:#DC2626} .btn-mini.red:hover{background:#DC2626;color:white}
.btn-mini.gray{background:#F1F5F9;color:var(--slate)} .btn-mini.gray:hover{background:var(--slate);color:white}
.btn-mini.blue{background:#EFF6FF;color:#2563EB} .btn-mini.blue:hover{background:#2563EB;color:white}
.actions-cell{display:flex;gap:6px;flex-wrap:wrap}
.avatar-sm{width:36px;height:36px;border-radius:10px;background:#FFF0EB;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.name-cell{display:flex;align-items:center;gap:10px}
.pagination{display:flex;justify-content:center;gap:6px;margin-top:20px}
.page-btn{width:34px;height:34px;border-radius:9px;border:1.5px solid var(--border);background:white;text-decoration:none;color:var(--slate);display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700}
.page-btn.active{background:var(--primary);color:white;border-color:var(--primary)}
.empty-mini{text-align:center;padding:40px;color:var(--slate);font-size:13.5px}
CSS;

require 'admin_header.php';
?>

<div class="toolbar">
  <form method="get" style="flex:1;display:flex;gap:12px;flex-wrap:wrap;">
    <input type="hidden" name="statut" value="<?= e($statutFiltre) ?>">
    <input type="text" name="q" class="search-input" placeholder="🔍 Rechercher un nom, email..." value="<?= e($q) ?>">
  </form>
</div>

<div class="filter-tabs">
  <a class="ftab <?= $statutFiltre==='tous'?'active':'' ?>" href="<?= urlAvecP(['statut'=>'tous','page'=>null]) ?>">Tous <span class="cnt"><?= $totalTous ?></span></a>
  <a class="ftab <?= $statutFiltre==='en_attente'?'active':'' ?>" href="<?= urlAvecP(['statut'=>'en_attente','page'=>null]) ?>">⏳ En attente <span class="cnt"><?= $compteurs['en_attente'] ?? 0 ?></span></a>
  <a class="ftab <?= $statutFiltre==='valide'?'active':'' ?>" href="<?= urlAvecP(['statut'=>'valide','page'=>null]) ?>">✓ Validés <span class="cnt"><?= $compteurs['valide'] ?? 0 ?></span></a>
  <a class="ftab <?= $statutFiltre==='refuse'?'active':'' ?>" href="<?= urlAvecP(['statut'=>'refuse','page'=>null]) ?>">✕ Refusés <span class="cnt"><?= $compteurs['refuse'] ?? 0 ?></span></a>
</div>

<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Prestataire</th><th>Catégorie</th><th>Tarif</th><th>Note</th><th>Statut compte</th><th>Validation</th><th>Inscrit</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (empty($prestataires)): ?>
    <tr><td colspan="8" class="empty-mini">Aucun prestataire trouvé.</td></tr>
  <?php else: foreach ($prestataires as $p): ?>
    <tr>
      <td>
        <div class="name-cell">
          <div class="avatar-sm">🧑‍🔧</div>
          <div><strong><?= e($p['prenom'].' '.$p['nom']) ?></strong><div style="font-size:11.5px;color:var(--slate);"><?= e($p['email']) ?></div></div>
        </div>
      </td>
      <td><?= e($p['categorie_nom']) ?></td>
      <td><?= number_format($p['tarif_horaire'],0,',',' ') ?> F/h</td>
      <td><?= $p['nb_avis']>0 ? '⭐ '.e((string)$p['note_moyenne']).' ('.$p['nb_avis'].')' : '—' ?></td>
      <td><span class="pill <?= $p['statut_compte'] ?>"><?= $p['statut_compte']==='actif'?'Actif':'Suspendu' ?></span></td>
      <td><span class="pill <?= $p['statut_validation'] ?>"><?= ['valide'=>'Validé','en_attente'=>'En attente','refuse'=>'Refusé'][$p['statut_validation']] ?></span></td>
      <td style="font-size:12px;color:var(--slate);"><?= ilYA($p['date_creation']) ?></td>
      <td>
        <div class="actions-cell">
          <a class="btn-mini blue" href="../profil_prestataire.php?id=<?= $p['id'] ?>" target="_blank">👁️ Profil</a>
          <?php if ($p['piece_identite']): ?><a class="btn-mini gray" href="../<?= e($p['piece_identite']) ?>" target="_blank">🪪 Pièce</a><?php endif; ?>
          <?php if ($p['statut_validation'] === 'en_attente'): ?>
            <form method="post" style="display:inline;"><input type="hidden" name="user_id" value="<?= $p['id'] ?>"><button type="submit" name="action_presta" value="valider" class="btn-mini green">✓ Valider</button></form>
            <form method="post" style="display:inline;"><input type="hidden" name="user_id" value="<?= $p['id'] ?>"><button type="submit" name="action_presta" value="refuser" class="btn-mini red" onclick="return confirm('Refuser ce prestataire ?')">✕ Refuser</button></form>
          <?php endif; ?>
          <?php if ($p['statut_compte'] === 'actif'): ?>
            <form method="post" style="display:inline;"><input type="hidden" name="user_id" value="<?= $p['id'] ?>"><button type="submit" name="action_presta" value="suspendre" class="btn-mini red" onclick="return confirm('Suspendre ce compte ?')">🚫 Suspendre</button></form>
          <?php else: ?>
            <form method="post" style="display:inline;"><input type="hidden" name="user_id" value="<?= $p['id'] ?>"><button type="submit" name="action_presta" value="reactiver" class="btn-mini green">↺ Réactiver</button></form>
          <?php endif; ?>
        </div>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i=1;$i<=$totalPages;$i++): ?>
    <a class="page-btn <?= $i===$page?'active':'' ?>" href="<?= urlAvecP(['page'=>$i]) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php require 'admin_footer.php'; ?>