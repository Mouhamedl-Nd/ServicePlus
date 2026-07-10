<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('admin', '../');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['traiter'])) {
        $pdo->prepare('UPDATE messages_contact SET traite=1 WHERE id=?')->execute([(int)$_POST['traiter']]);
    } elseif (isset($_POST['rouvrir'])) {
        $pdo->prepare('UPDATE messages_contact SET traite=0 WHERE id=?')->execute([(int)$_POST['rouvrir']]);
    } elseif (isset($_POST['supprimer'])) {
        $pdo->prepare('DELETE FROM messages_contact WHERE id=?')->execute([(int)$_POST['supprimer']]);
    }
    header('Location: gestion_messages.php' . (isset($_GET['statut']) ? '?statut='.urlencode($_GET['statut']) : ''));
    exit;
}

$statutFiltre = $_GET['statut'] ?? 'tous';
$page = max(1,(int)($_GET['page'] ?? 1));
$parPage = 8;

$nbNonTraites = (int)$pdo->query('SELECT COUNT(*) FROM messages_contact WHERE traite=0')->fetchColumn();
$nbTraites    = (int)$pdo->query('SELECT COUNT(*) FROM messages_contact WHERE traite=1')->fetchColumn();
$totalTous = $nbNonTraites + $nbTraites;

$where = []; $params = [];
if ($statutFiltre === 'non_traite') { $where[] = 'traite=0'; }
elseif ($statutFiltre === 'traite') { $where[] = 'traite=1'; }
$whereSql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM messages_contact $whereSql"); $stmtCount->execute($params);
$total = (int)$stmtCount->fetchColumn();
$totalPages = max(1,(int)ceil($total/$parPage)); $page=min($page,$totalPages); $offset=($page-1)*$parPage;

$stmt = $pdo->prepare("SELECT * FROM messages_contact $whereSql ORDER BY date_creation DESC LIMIT $parPage OFFSET $offset");
$stmt->execute($params);
$messages = $stmt->fetchAll();

function urlAvecM(array $r=[]): string {
    $p = array_merge($_GET,$r);
    foreach($p as $k=>$v) if ($v===''||$v===null) unset($p[$k]);
    return 'gestion_messages.php?'.http_build_query($p);
}

$page_title = 'Messages contact';
$page_active = 'messages';
$extra_css = <<<CSS
.filter-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px}
.ftab{padding:8px 16px;border-radius:20px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:12.5px;font-weight:700;color:var(--slate);text-decoration:none;display:inline-flex;gap:6px;align-items:center}
.ftab.active{background:var(--primary);color:white;border-color:var(--primary)}
.ftab .cnt{background:#F1F5F9;color:var(--slate);padding:1px 7px;border-radius:20px;font-size:10.5px}
.ftab.active .cnt{background:rgba(255,255,255,.25);color:white}
.msg-card{background:white;border-radius:16px;border:1.5px solid var(--border);padding:20px;margin-bottom:12px;position:relative}
.msg-card.non-traite{border-left:4px solid var(--primary)}
.msg-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;flex-wrap:wrap;gap:8px}
.msg-from{font-weight:800;font-size:14.5px}
.msg-email{font-size:12px;color:var(--slate)}
.msg-sujet{display:inline-block;background:#EFF6FF;color:#2563EB;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:20px;margin-top:4px}
.msg-time{font-size:11.5px;color:#94A3B8;white-space:nowrap}
.msg-body{font-size:13.5px;color:#475569;line-height:1.7;margin:12px 0;white-space:pre-line;background:#FAFBFC;border-radius:12px;padding:14px}
.msg-actions{display:flex;gap:8px;flex-wrap:wrap}
.btn-mini{padding:6px 12px;border-radius:8px;border:none;font-size:11.5px;font-weight:800;cursor:pointer;font-family:'Inter',sans-serif}
.btn-mini.green{background:#ECFDF5;color:#059669} .btn-mini.green:hover{background:#059669;color:white}
.btn-mini.gray{background:#F1F5F9;color:var(--slate)} .btn-mini.gray:hover{background:var(--slate);color:white}
.btn-mini.red{background:#FEF2F2;color:#DC2626} .btn-mini.red:hover{background:#DC2626;color:white}
.pill{font-size:10.5px;font-weight:800;padding:3px 10px;border-radius:20px;text-transform:uppercase}
.pill.non-traite{background:#FFFBEB;color:#D97706}
.pill.traite{background:#ECFDF5;color:#059669}
.pagination{display:flex;justify-content:center;gap:6px;margin-top:20px}
.page-btn{width:34px;height:34px;border-radius:9px;border:1.5px solid var(--border);background:white;text-decoration:none;color:var(--slate);display:flex;align-items:center;justify-content:center;font-size:12.5px;font-weight:700}
.page-btn.active{background:var(--primary);color:white;border-color:var(--primary)}
.empty-mini{text-align:center;padding:40px;color:var(--slate);font-size:13.5px;background:white;border-radius:16px;border:1.5px solid var(--border)}
CSS;

require 'admin_header.php';
?>

<div class="filter-tabs">
  <a class="ftab <?= $statutFiltre==='tous'?'active':'' ?>" href="<?= urlAvecM(['statut'=>'tous','page'=>null]) ?>">Tous <span class="cnt"><?= $totalTous ?></span></a>
  <a class="ftab <?= $statutFiltre==='non_traite'?'active':'' ?>" href="<?= urlAvecM(['statut'=>'non_traite','page'=>null]) ?>">📬 Non traités <span class="cnt"><?= $nbNonTraites ?></span></a>
  <a class="ftab <?= $statutFiltre==='traite'?'active':'' ?>" href="<?= urlAvecM(['statut'=>'traite','page'=>null]) ?>">✓ Traités <span class="cnt"><?= $nbTraites ?></span></a>
</div>

<?php if (empty($messages)): ?>
  <div class="empty-mini">Aucun message pour le moment.</div>
<?php else: foreach ($messages as $m): ?>
  <div class="msg-card <?= !$m['traite']?'non-traite':'' ?>">
    <div class="msg-top">
      <div>
        <div class="msg-from"><?= e($m['nom']) ?> <span class="pill <?= $m['traite']?'traite':'non-traite' ?>" style="margin-left:6px;"><?= $m['traite']?'Traité':'Non traité' ?></span></div>
        <div class="msg-email"><?= e($m['email']) ?></div>
        <span class="msg-sujet"><?= e($m['sujet']) ?></span>
      </div>
      <span class="msg-time"><?= ilYA($m['date_creation']) ?></span>
    </div>
    <div class="msg-body"><?= nl2br(e($m['message'])) ?></div>
    <div class="msg-actions">
      <a class="btn-mini gray" href="mailto:<?= e($m['email']) ?>">✉️ Répondre par email</a>
      <?php if (!$m['traite']): ?>
        <form method="post"><input type="hidden" name="traiter" value="<?= $m['id'] ?>"><button type="submit" class="btn-mini green">✓ Marquer traité</button></form>
      <?php else: ?>
        <form method="post"><input type="hidden" name="rouvrir" value="<?= $m['id'] ?>"><button type="submit" class="btn-mini gray">↺ Rouvrir</button></form>
      <?php endif; ?>
      <form method="post" onsubmit="return confirm('Supprimer ce message ?')"><input type="hidden" name="supprimer" value="<?= $m['id'] ?>"><button type="submit" class="btn-mini red">🗑️ Supprimer</button></form>
    </div>
  </div>
<?php endforeach; endif; ?>

<?php if ($totalPages > 1): ?>
<div class="pagination">
  <?php for ($i=1;$i<=$totalPages;$i++): ?>
    <a class="page-btn <?= $i===$page?'active':'' ?>" href="<?= urlAvecM(['page'=>$i]) ?>"><?= $i ?></a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<?php require 'admin_footer.php'; ?>