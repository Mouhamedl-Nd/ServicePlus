<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('admin', '../');

$adminId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['lire'])) {
        $pdo->prepare('UPDATE notifications SET lu=1 WHERE id=? AND user_id=?')->execute([(int)$_POST['lire'], $adminId]);
    } elseif (isset($_POST['lire_tout'])) {
        $pdo->prepare('UPDATE notifications SET lu=1 WHERE user_id=?')->execute([$adminId]);
    } elseif (isset($_POST['supprimer'])) {
        $pdo->prepare('DELETE FROM notifications WHERE id=? AND user_id=?')->execute([(int)$_POST['supprimer'], $adminId]);
    } elseif (isset($_POST['supprimer_tout'])) {
        $pdo->prepare('DELETE FROM notifications WHERE user_id=?')->execute([$adminId]);
    }
    header('Location: notifications.php');
    exit;
}

$typeIcones = [
    'prestataire'=>['🔧','#FFF0EB'], 'valide'=>['✅','#ECFDF5'], 'refuse'=>['❌','#FEF2F2'],
    'suspendu'=>['🚫','#FEF2F2'], 'actif'=>['✅','#ECFDF5'], 'annulation'=>['❌','#FEF2F2'],
    'info'=>['🔔','#EFF6FF'],
];

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=?'); $stmt->execute([$adminId]); $total = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND lu=0'); $stmt->execute([$adminId]); $nonLues = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY date_creation DESC');
$stmt->execute([$adminId]);
$notifs = $stmt->fetchAll();

$page_title = 'Notifications';
$page_active = 'notifications';
$extra_css = <<<CSS
.summary-bar{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;margin-bottom:20px;max-width:420px}
.sum-card{background:white;border-radius:14px;padding:16px;border:1.5px solid var(--border);text-align:center}
.sum-val{font-size:24px;font-weight:900;margin-bottom:3px}
.sum-lbl{font-size:11.5px;color:var(--slate)}
.header-actions{display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap}
.btn-o{padding:9px 16px;background:white;color:var(--slate);border:1.5px solid var(--border);border-radius:10px;font-weight:700;cursor:pointer;font-size:12.5px}
.btn-o:hover{border-color:var(--primary);color:var(--primary)}
.notif-card{background:white;border-radius:16px;border:1.5px solid var(--border);margin-bottom:10px;overflow:hidden;position:relative}
.notif-card.unread{border-left:4px solid var(--primary)}
.notif-card.unread .notif-inner{background:linear-gradient(to right,#FFF0EB,white)}
.notif-inner{display:flex;gap:14px;padding:16px 20px;align-items:flex-start}
.notif-icon-wrap{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.notif-content{flex:1}
.notif-top{display:flex;justify-content:space-between;gap:10px}
.notif-title{font-weight:700;font-size:14px}
.notif-time{font-size:11px;color:#94A3B8;white-space:nowrap}
.notif-msg{font-size:13px;color:var(--slate);line-height:1.6;margin:4px 0 8px}
.notif-actions{display:flex;gap:8px}
.btn-mini{padding:5px 11px;border-radius:8px;border:none;font-size:11px;font-weight:800;cursor:pointer;font-family:'Inter',sans-serif}
.btn-mini.gray{background:#F1F5F9;color:var(--slate)} .btn-mini.gray:hover{background:var(--slate);color:white}
.btn-mini.red{background:#FEF2F2;color:#DC2626} .btn-mini.red:hover{background:#DC2626;color:white}
.empty-mini{text-align:center;padding:50px 20px;background:white;border-radius:16px;border:1.5px solid var(--border);color:var(--slate)}
CSS;

require 'admin_header.php';
?>

<div class="summary-bar">
  <div class="sum-card"><div class="sum-val"><?= $total ?></div><div class="sum-lbl">Total</div></div>
  <div class="sum-card"><div class="sum-val" style="color:var(--primary);"><?= $nonLues ?></div><div class="sum-lbl">Non lues</div></div>
</div>

<div class="header-actions">
  <form method="post"><button type="submit" name="lire_tout" value="1" class="btn-o">✓ Tout marquer comme lu</button></form>
  <form method="post" onsubmit="return confirm('Supprimer toutes les notifications ?')"><button type="submit" name="supprimer_tout" value="1" class="btn-o">🗑️ Tout supprimer</button></form>
</div>

<?php if (empty($notifs)): ?>
  <div class="empty-mini">🔔 Aucune notification pour le moment.</div>
<?php else: foreach ($notifs as $n): [$icone,$bg] = $typeIcones[$n['type']] ?? $typeIcones['info']; ?>
  <div class="notif-card <?= !$n['lu']?'unread':'' ?>">
    <div class="notif-inner">
      <div class="notif-icon-wrap" style="background:<?= $bg ?>;"><?= $icone ?></div>
      <div class="notif-content">
        <div class="notif-top">
          <div class="notif-title"><?= e($n['titre']) ?></div>
          <span class="notif-time"><?= ilYA($n['date_creation']) ?></span>
        </div>
        <div class="notif-msg"><?= e($n['message']) ?></div>
        <div class="notif-actions">
          <?php if (!$n['lu']): ?>
            <form method="post"><input type="hidden" name="lire" value="<?= $n['id'] ?>"><button type="submit" class="btn-mini gray">✓ Marquer lu</button></form>
          <?php endif; ?>
          <?php if ($n['lien']): ?><a class="btn-mini gray" href="<?= e($n['lien']) ?>" style="text-decoration:none;">→ Voir</a><?php endif; ?>
          <form method="post"><input type="hidden" name="supprimer" value="<?= $n['id'] ?>"><button type="submit" class="btn-mini red">🗑️</button></form>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; endif; ?>

<?php require 'admin_footer.php'; ?>