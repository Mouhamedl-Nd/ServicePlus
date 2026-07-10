<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('prestataire', '../');

$prestaId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['lire'])) { $pdo->prepare('UPDATE notifications SET lu=1 WHERE id=? AND user_id=?')->execute([(int)$_POST['lire'], $prestaId]); }
    elseif (isset($_POST['lire_tout'])) { $pdo->prepare('UPDATE notifications SET lu=1 WHERE user_id=?')->execute([$prestaId]); }
    elseif (isset($_POST['supprimer'])) { $pdo->prepare('DELETE FROM notifications WHERE id=? AND user_id=?')->execute([(int)$_POST['supprimer'], $prestaId]); }
    elseif (isset($_POST['supprimer_tout'])) { $pdo->prepare('DELETE FROM notifications WHERE user_id=?')->execute([$prestaId]); }
    header('Location: notifications.php'); exit;
}

$typeIcones = ['reservation'=>['📅','#EFF6FF'],'annonce'=>['📢','#F5F3FF'],'annulation'=>['❌','#FEF2F2'],'avis'=>['⭐','#FFFBEB'],'valide'=>['✅','#ECFDF5'],'refuse'=>['❌','#FEF2F2'],'info'=>['🔔','#F1F5F9']];

$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=?'); $stmt->execute([$prestaId]); $total=(int)$stmt->fetchColumn();
$stmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id=? AND lu=0'); $stmt->execute([$prestaId]); $nonLues=(int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY date_creation DESC');
$stmt->execute([$prestaId]);
$notifs = $stmt->fetchAll();

$page_title  = 'ServicesPlus – Notifications';
$page_active = 'notifications';
$racine      = '../';
$extra_css   = <<<CSS
.container{max-width:860px;margin:0 auto;padding:32px 20px}
.page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:22px;flex-wrap:wrap;gap:12px}
.page-title{font-family:'Playfair Display',serif;font-size:26px;font-weight:900;margin-bottom:4px}
.page-sub{color:var(--slate);font-size:14px}
.header-actions{display:flex;gap:8px;flex-wrap:wrap}
.btn-o{padding:9px 16px;background:white;color:var(--slate);border:1.5px solid var(--border);border-radius:10px;font-weight:700;cursor:pointer;font-size:12.5px}
.btn-o:hover{border-color:var(--primary);color:var(--primary)}
.summary-bar{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:24px;max-width:340px}
.sum-card{background:white;border-radius:14px;padding:16px;border:1.5px solid var(--border);text-align:center}
.sum-val{font-size:22px;font-weight:900;margin-bottom:3px}
.sum-lbl{font-size:11px;color:var(--slate)}
.notif-card{background:white;border-radius:16px;border:1.5px solid var(--border);margin-bottom:10px;overflow:hidden}
.notif-card.unread{border-left:4px solid var(--primary)}
.notif-card.unread .notif-inner{background:linear-gradient(to right,#FFF0EB,white)}
.notif-inner{display:flex;gap:14px;padding:18px 20px;align-items:flex-start}
.notif-icon-wrap{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.notif-content{flex:1}
.notif-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:5px}
.notif-title{font-weight:700;font-size:14px}
.notif-time{font-size:11px;color:#94A3B8;white-space:nowrap;margin-left:10px}
.notif-msg{font-size:13px;color:var(--slate);line-height:1.6;margin-bottom:10px}
.notif-actions{display:flex;gap:8px;flex-wrap:wrap}
.btn-s{padding:5px 12px;border-radius:8px;border:none;cursor:pointer;font-size:11px;font-weight:700}
.btn-s.gray{background:#F1F5F9;color:#64748B} .btn-s.red{background:#FEF2F2;color:#DC2626}
.empty-state{text-align:center;padding:60px 20px;background:white;border-radius:18px;border:1.5px solid var(--border)}
.empty-icon{font-size:56px;margin-bottom:16px}
.empty-title{font-weight:800;font-size:18px;margin-bottom:8px}
.empty-sub{color:var(--slate);font-size:14px}
CSS;

require '../includes/header.php';
?>

<div class="container">
  <div class="page-header">
    <div><h1 class="page-title">🔔 Notifications</h1><p class="page-sub"><?= $nonLues ?> notification<?= $nonLues>1?'s':'' ?> non lue<?= $nonLues>1?'s':'' ?> sur <?= $total ?></p></div>
    <div class="header-actions">
      <form method="post"><button type="submit" name="lire_tout" value="1" class="btn-o">✓ Tout marquer comme lu</button></form>
      <form method="post" onsubmit="return confirm('Supprimer toutes les notifications ?')"><button type="submit" name="supprimer_tout" value="1" class="btn-o">🗑️ Tout supprimer</button></form>
    </div>
  </div>

  <div class="summary-bar">
    <div class="sum-card"><div class="sum-val"><?= $total ?></div><div class="sum-lbl">Total</div></div>
    <div class="sum-card"><div class="sum-val" style="color:var(--primary);"><?= $nonLues ?></div><div class="sum-lbl">Non lues</div></div>
  </div>

  <?php if (empty($notifs)): ?>
    <div class="empty-state"><div class="empty-icon">🔔</div><div class="empty-title">Aucune notification</div><div class="empty-sub">Vous serez notifié ici pour les nouvelles demandes, réservations et avis.</div></div>
  <?php else: foreach ($notifs as $n): [$icone,$bg] = $typeIcones[$n['type']] ?? $typeIcones['info']; ?>
    <div class="notif-card <?= !$n['lu']?'unread':'' ?>">
      <div class="notif-inner">
        <div class="notif-icon-wrap" style="background:<?= $bg ?>;"><?= $icone ?></div>
        <div class="notif-content">
          <div class="notif-top"><span class="notif-title"><?= e($n['titre']) ?></span><span class="notif-time"><?= ilYA($n['date_creation']) ?></span></div>
          <div class="notif-msg"><?= e($n['message']) ?></div>
          <div class="notif-actions">
            <?php if (!$n['lu']): ?><form method="post"><input type="hidden" name="lire" value="<?= $n['id'] ?>"><button type="submit" class="btn-s gray">✓ Marquer lu</button></form><?php endif; ?>
            <?php if ($n['lien']): ?><a href="<?= e($n['lien']) ?>" class="btn-s gray" style="text-decoration:none;">→ Voir</a><?php endif; ?>
            <form method="post"><input type="hidden" name="supprimer" value="<?= $n['id'] ?>"><button type="submit" class="btn-s red">🗑️</button></form>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php require '../includes/footer.php'; ?>