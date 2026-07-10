<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('admin', '../');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_client'])) {
    $id = (int)$_POST['user_id'];
    $action = $_POST['action_client'];
    if ($action === 'suspendre') {
        $pdo->prepare("UPDATE utilisateurs SET statut='suspendu' WHERE id=?")->execute([$id]);
        creerNotification($pdo, $id, 'Compte suspendu', 'Votre compte a été suspendu par l\'administrateur.', 'suspendu');
    } elseif ($action === 'reactiver') {
        $pdo->prepare("UPDATE utilisateurs SET statut='actif' WHERE id=?")->execute([$id]);
        creerNotification($pdo, $id, 'Compte réactivé', 'Votre compte a été réactivé.', 'actif');
    }
    header('Location: gestion_clients.php' . (isset($_GET['statut']) ? '?statut='.urlencode($_GET['statut']) : ''));
    exit;
}

$statutFiltre = $_GET['statut'] ?? 'tous';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$parPage = 10;

$compteurs = $pdo->query("SELECT statut, COUNT(*) AS nb FROM utilisateurs WHERE role='client' GROUP BY statut")->fetchAll(PDO::FETCH_KEY_PAIR);
$totalTous = array_sum($compteurs);

$where = ["u.role='client'"]; $params = [];
if ($statutFiltre !== 'tous') { $where[] = 'u.statut = ?'; $params[] = $statutFiltre; }
if ($q !== '') { $where[] = '(u.nom LIKE ? OR u.prenom LIKE ? OR u.email LIKE ?)'; $like="%$q%"; array_push($params,$like,$like,$like); }
$whereSql = 'WHERE '.implode(' AND ', $where);

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs u $whereSql");
$stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1,(int)ceil($total/$parPage));
$page = min($page, $totalPages);
$offset = ($page-1)*$parPage;

$sql = "SELECT u.id, u.nom, u.prenom, u.email, u.telephone, u.ville, u.statut, u.date_creation,
        (SELECT COUNT(*) FROM reservations r WHERE r.client_id=u.id) AS nb_reservations,
        (SELECT COUNT(*) FROM annonces a WHERE a.client_id=u.id) AS nb_annonces,
        (SELECT COUNT(*) FROM avis av WHERE av.client_id=u.id) AS nb_avis_laisses
        FROM utilisateurs u $whereSql ORDER BY u.date_creation DESC LIMIT $parPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

function urlAvecC(array $r=[]): string {
    $p = array_merge($_GET,$r);
    foreach($p as $k=>$v) if ($v===''||$v===null) unset($p[$k]);
    return 'gestion_clients.php?'.http_build_query($p);
}

$page_title = 'Clients';
$page_active = 'clients';
$extra_css = <<<CSS
.toolbar{display:flex;gap:12px;margin-bottom:18px;flex-wrap:wrap;align-items:center}
.search-input{flex:1;min-width:220px;padding:11px 16px;border-radius:11px;border:1.5px solid var(--border);font-size:13.5px;font-family:'Inter',sans-serif}
.filter-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
.ftab{padding:8px 16px;border-radius:20px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:12.5px;font-weight:700;color:var(--slate);text-decoration:none;display:inline-flex;gap:6px;align-items:center}
.ftab.active{background:var(--primary);color:white;border-color:var(--primary)}
.ftab .cnt{background:#F1F5F9;color:var(--slate);padding:1px 7px;border-radius:20px;font-size:10.5px}
.ftab.active .cnt{background:rgba(255,255,255,.25);color:white}
.table-wrap{overflow-x:auto;border-radius:16px;border:1.5px solid var(--border);background:white}
.data-table{width:100%;border-collapse:collapse;min-width:720px}
.data-table th{background:#FAFBFC;padding:12px 16px;text-align:left;font-size:11px;font-weight:800;color:var(--slate);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border)}
.data-table td{padding:13px 16px;border-bottom:1px solid #F8FAFC;font-size:13.5px;vertical-align:middle}
.data-table tr:last-child td{border-bottom:none}
.data-table tr:hover{background:#FAFBFC}
.pill{font-size:10.5px;font-weight:800;padding:3px 10px;border-radius:20px;text-transform:uppercase;display:inline-block}
.pill.actif{background:#ECFDF5;color:#059669}
.pill.suspendu{background:#FEF2F2;color:#DC2626}
.btn-mini{padding:6px 11px;border-radius:8px;border:none;font-size:11px;font-weight:800;cursor:pointer;font-family:'Inter',sans-serif}
.btn-mini.green{background:#ECFDF5;color:#059669} .btn-mini.green:hover{background:#059669;color:white}
.btn-mini.red{background:#FEF2F2;color:#DC2626} .btn-mini.red:hover{background:#DC2626;color:white}
.avatar-sm{width:36px;height:36px;border-radius:10px;background:#EFF6FF;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0}
.name-cell{display:flex;align-items:center;gap:10px}
.pagination{display:flex;justify-content:center;gap:6px;margin-top:20px}
.page-btn{width:34px;height:34px;border-radius:9px;border:1.5px solid var(--border);background:white;text-decoration:none;color:var(--slate);display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700}
.page-btn.active{background:var(--primary);color:white;border-color:var(--primary)}
.empty-mini{text-align:center;padding:40px;color:var(--slate);font-size:13.5px}
.mini-stat{font-size:11.5px;color:var(--slate)}
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
  <a class="ftab <?= $statutFiltre==='tous'?'active':'' ?>" href="<?= urlAvecC(['statut'=>'tous','page'=>null]) ?>">Tous <span class="cnt"><?= $totalTous ?></span></a>
  <a class="ftab <?= $statutFiltre==='actif'?'active':'' ?>" href="<?= urlAvecC(['statut'=>'actif','page'=>null]) ?>">✓ Actifs <span class="cnt"><?= $compteurs['actif'] ?? 0 ?></span></a>
  <a class="ftab <?= $statutFiltre==='suspendu'?'active':'' ?>" href="<?= urlAvecC(['statut'=>'suspendu','page'=>null]) ?>">🚫 Suspendus <span class="cnt"><?= $compteurs['suspendu'] ?? 0 ?></span></a>
</div>

<div class="table-wrap">
<table class="data-table">
  <thead><tr><th>Client</th><th>Ville</th><th>Activité</th><th>Statut</th><th>Inscrit</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (empty($clients)): ?>
    <tr><td colspan="6" class="empty-mini">Aucun client trouvé.</td></tr>
  <?php else: foreach ($clients as $c): ?>
    <tr>
      <td>
        <div class="name-cell">
          <div class="avatar-sm">👤</div>
          <div><strong><?= e($c['prenom'].' '.$c['nom']) ?></strong><div style="font-size:11.5px;color:var(--slate);"><?= e($c['email']) ?> <?= $c['telephone'] ? '• '.e($c['telephone']) : '' ?></div></div>
        </div>
      </td>
      <td><?= e($c['ville'] ?? '—') ?></td>
      <td class="mini-stat"><?= $c['nb_reservations'] ?> réservation<?= $c['nb_reservations']>1?'s':'' ?> • <?= $c['nb_annonces'] ?> annonce<?= $c['nb_annonces']>1?'s':'' ?> • <?= $c['nb_avis_laisses'] ?> avis laissé<?= $c['nb_avis_laisses']>1?'s':'' ?></td>
      <td><span class="pill <?= $c['statut'] ?>"><?= $c['statut']==='actif'?'Actif':'Suspendu' ?></span></td>
      <td style="font-size:12px;color:var(--slate);"><?= ilYA($c['date_creation']) ?></td>
      <td>
        <?php if ($c['statut']==='actif'): ?>
          <form method="post"><input type="hidden" name="user_id" value="<?= $c['id'] ?>"><button type="submit" name="action_client" value="suspendre" class="btn-mini red" onclick="return confirm('Suspendre ce compte ?')">🚫 Suspendre</button></form>
        <?php else: ?>
          <form method="post"><input type="hidden" name="user_id" value="<?= $c['id'] ?>"><button type="submit" name="action_client" value="reactiver" class="btn-mini green">↺ Réactiver</button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i=1;$i<=$totalPages;$i++): ?>
    <a class="page-btn <?= $i===$page?'active':'' ?>" href="<?= urlAvecC(['page'=>$i]) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php require 'admin_footer.php'; ?>