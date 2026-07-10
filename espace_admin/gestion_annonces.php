<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('admin', '../');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['annuler_reservation'])) {
    $id = (int)$_POST['annuler_reservation'];
    $stmt = $pdo->prepare('SELECT client_id FROM reservations WHERE id=?');
    $stmt->execute([$id]);
    $clientId = $stmt->fetchColumn();
    $pdo->prepare("UPDATE reservations SET statut='annulee' WHERE id=?")->execute([$id]);
    if ($clientId) { creerNotification($pdo, $clientId, 'Réservation annulée', 'Votre réservation a été annulée par l\'administrateur.', 'annulation'); }
    header('Location: gestion_annonces.php?onglet=reservations');
    exit;
}

$onglet = $_GET['onglet'] ?? 'reservations';
$statutFiltre = $_GET['statut'] ?? 'tous';
$page = max(1,(int)($_GET['page'] ?? 1));
$parPage = 10;

if ($onglet === 'annonces') {
    $compteurs = $pdo->query("SELECT statut, COUNT(*) AS nb FROM annonces GROUP BY statut")->fetchAll(PDO::FETCH_KEY_PAIR);
    $totalTous = array_sum($compteurs);
    $where = []; $params = [];
    if ($statutFiltre !== 'tous') { $where[] = 'a.statut = ?'; $params[] = $statutFiltre; }
    $whereSql = $where ? 'WHERE '.implode(' AND ',$where) : '';
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM annonces a $whereSql"); $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();
    $totalPages = max(1,(int)ceil($total/$parPage)); $page = min($page,$totalPages); $offset=($page-1)*$parPage;
    $sql = "SELECT a.*, u.prenom, u.nom, c.nom AS categorie_nom FROM annonces a
            JOIN utilisateurs u ON u.id=a.client_id JOIN categories c ON c.id=a.categorie_id
            $whereSql ORDER BY a.date_creation DESC LIMIT $parPage OFFSET $offset";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $lignes = $stmt->fetchAll();
} else {
    $compteurs = $pdo->query("SELECT statut, COUNT(*) AS nb FROM reservations GROUP BY statut")->fetchAll(PDO::FETCH_KEY_PAIR);
    $totalTous = array_sum($compteurs);
    $where = []; $params = [];
    if ($statutFiltre !== 'tous') { $where[] = 'r.statut = ?'; $params[] = $statutFiltre; }
    $whereSql = $where ? 'WHERE '.implode(' AND ',$where) : '';
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM reservations r $whereSql"); $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();
    $totalPages = max(1,(int)ceil($total/$parPage)); $page = min($page,$totalPages); $offset=($page-1)*$parPage;
    $sql = "SELECT r.*, cu.prenom AS client_prenom, cu.nom AS client_nom, pu.prenom AS presta_prenom, pu.nom AS presta_nom
            FROM reservations r
            JOIN utilisateurs cu ON cu.id=r.client_id JOIN utilisateurs pu ON pu.id=r.prestataire_id
            $whereSql ORDER BY r.date_creation DESC LIMIT $parPage OFFSET $offset";
    $stmt = $pdo->prepare($sql); $stmt->execute($params);
    $lignes = $stmt->fetchAll();
}

function urlAvecA(array $r=[]): string {
    $p = array_merge($_GET,$r);
    foreach($p as $k=>$v) if ($v===''||$v===null) unset($p[$k]);
    return 'gestion_annonces.php?'.http_build_query($p);
}

$page_title = 'Annonces & réservations';
$page_active = 'annonces';
$extra_css = <<<CSS
.onglets{display:flex;gap:8px;margin-bottom:18px;background:white;padding:6px;border-radius:14px;border:1.5px solid var(--border);width:fit-content}
.onglet{padding:9px 20px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:700;color:var(--slate)}
.onglet.active{background:linear-gradient(135deg,#E85D26,#F59E0B);color:white}
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
.pill.ouverte,.pill.confirmee{background:#EFF6FF;color:#2563EB}
.pill.terminee{background:#ECFDF5;color:#059669}
.pill.en_attente,.pill.en_discussion{background:#FFFBEB;color:#D97706}
.pill.attribuee,.pill.en_cours{background:#FDF4FF;color:#9333EA}
.pill.annulee{background:#F1F5F9;color:#64748B}
.btn-mini{padding:6px 11px;border-radius:8px;border:none;font-size:11px;font-weight:800;cursor:pointer;font-family:'Inter',sans-serif}
.btn-mini.red{background:#FEF2F2;color:#DC2626} .btn-mini.red:hover{background:#DC2626;color:white}
.pagination{display:flex;justify-content:center;gap:6px;margin-top:20px}
.page-btn{width:34px;height:34px;border-radius:9px;border:1.5px solid var(--border);background:white;text-decoration:none;color:var(--slate);display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700}
.page-btn.active{background:var(--primary);color:white;border-color:var(--primary)}
.empty-mini{text-align:center;padding:40px;color:var(--slate);font-size:13.5px}
CSS;

require 'admin_header.php';
?>

<div class="onglets">
  <a class="onglet <?= $onglet==='reservations'?'active':'' ?>" href="gestion_annonces.php?onglet=reservations">📋 Réservations</a>
  <a class="onglet <?= $onglet==='annonces'?'active':'' ?>" href="gestion_annonces.php?onglet=annonces">📢 Annonces</a>
</div>

<div class="filter-tabs">
  <a class="ftab <?= $statutFiltre==='tous'?'active':'' ?>" href="<?= urlAvecA(['statut'=>'tous','page'=>null]) ?>">Tous <span class="cnt"><?= $totalTous ?></span></a>
  <?php foreach ($compteurs as $s => $n): ?>
    <a class="ftab <?= $statutFiltre===$s?'active':'' ?>" href="<?= urlAvecA(['statut'=>$s,'page'=>null]) ?>"><?= ucfirst(str_replace('_',' ',$s)) ?> <span class="cnt"><?= $n ?></span></a>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
<?php if ($onglet === 'annonces'): ?>
<table class="data-table">
  <thead><tr><th>Client</th><th>Titre</th><th>Catégorie</th><th>Budget</th><th>Ville</th><th>Statut</th><th>Publiée</th></tr></thead>
  <tbody>
  <?php if (empty($lignes)): ?>
    <tr><td colspan="7" class="empty-mini">Aucune annonce pour le moment.</td></tr>
  <?php else: foreach ($lignes as $a): ?>
    <tr>
      <td><?= e($a['prenom'].' '.$a['nom']) ?></td>
      <td><?= e($a['titre']) ?></td>
      <td><?= e($a['categorie_nom']) ?></td>
      <td><?= $a['budget'] ? number_format($a['budget'],0,',',' ').' F' : '—' ?></td>
      <td><?= e($a['ville']) ?></td>
      <td><span class="pill <?= $a['statut'] ?>"><?= ucfirst(str_replace('_',' ',$a['statut'])) ?></span></td>
      <td style="font-size:12px;color:var(--slate);"><?= ilYA($a['date_creation']) ?></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<?php else: ?>
<table class="data-table">
  <thead><tr><th>Référence</th><th>Client</th><th>Prestataire</th><th>Montant</th><th>Date intervention</th><th>Statut</th><th>Actions</th></tr></thead>
  <tbody>
  <?php if (empty($lignes)): ?>
    <tr><td colspan="7" class="empty-mini">Aucune réservation pour le moment.</td></tr>
  <?php else: foreach ($lignes as $r): ?>
    <tr>
      <td><strong><?= e($r['reference']) ?></strong></td>
      <td><?= e($r['client_prenom'].' '.$r['client_nom']) ?></td>
      <td><?= e($r['presta_prenom'].' '.$r['presta_nom']) ?></td>
      <td><?= number_format($r['montant'],0,',',' ') ?> F</td>
      <td style="font-size:12.5px;"><?= formaterDate($r['date_reservation']) ?></td>
      <td><span class="pill <?= $r['statut'] ?>"><?= ucfirst(str_replace('_',' ',$r['statut'])) ?></span></td>
      <td>
        <?php if (!in_array($r['statut'], ['terminee','annulee'])): ?>
          <form method="post"><input type="hidden" name="annuler_reservation" value="<?= $r['id'] ?>"><button type="submit" class="btn-mini red" onclick="return confirm('Annuler cette réservation ?')">✕ Annuler</button></form>
        <?php else: ?>—<?php endif; ?>
      </td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody>
</table>
<?php endif; ?>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i=1;$i<=$totalPages;$i++): ?>
    <a class="page-btn <?= $i===$page?'active':'' ?>" href="<?= urlAvecA(['page'=>$i]) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php require 'admin_footer.php'; ?>