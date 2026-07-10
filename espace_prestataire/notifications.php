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
    header('Location: notifications.php' . (isset($_GET['filtre']) ? '?filtre='.urlencode($_GET['filtre']) : '')); exit;
}

/* Icônes + badges par type réellement générés par l'application */
$typeMeta = [
    'reservation' => ['📅','#FFF0EB','Réservation','nb-blue'],
    'annonce'     => ['📢','#F5F3FF','Annonce','nb-purple'],
    'annulation'  => ['❌','#FEF2F2','Annulation','nb-red'],
    'avis'        => ['⭐','#FFFBEB','Avis','nb-orange'],
    'valide'      => ['✅','#ECFDF5','Compte','nb-green'],
    'refuse'      => ['❌','#FEF2F2','Compte','nb-red'],
    'actif'       => ['✅','#ECFDF5','Compte','nb-green'],
    'suspendu'    => ['🚫','#FEF2F2','Compte','nb-red'],
    'info'        => ['🔔','#F1F5F9','Info','nb-gray'],
];

$filtreActif = $_GET['filtre'] ?? 'tous';

$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY date_creation DESC');
$stmt->execute([$prestaId]);
$toutes = $stmt->fetchAll();

$total   = count($toutes);
$nonLues = count(array_filter($toutes, fn($n) => !$n['lu']));
$lues    = $total - $nonLues;
$aujourdhui = count(array_filter($toutes, fn($n) => date('Y-m-d', strtotime($n['date_creation'])) === date('Y-m-d')));

/* Filtres dynamiques : seulement les types réellement présents dans les notifs du prestataire */
$typesPresents = array_unique(array_column($toutes, 'type'));
$compteParType = array_count_values(array_column($toutes, 'type'));

$filtres = [['id'=>'tous','lbl'=>'Toutes','icon'=>'🔔','cnt'=>$total], ['id'=>'nonlues','lbl'=>'Non lues','icon'=>'🔴','cnt'=>$nonLues]];
foreach ($typesPresents as $t) {
    $meta = $typeMeta[$t] ?? ['🔔','#F1F5F9',ucfirst($t),'nb-gray'];
    $filtres[] = ['id'=>$t, 'lbl'=>$meta[2], 'icon'=>$meta[0], 'cnt'=>$compteParType[$t]];
}

/* Application du filtre actif */
if ($filtreActif === 'nonlues') { $notifs = array_values(array_filter($toutes, fn($n)=>!$n['lu'])); }
elseif ($filtreActif !== 'tous') { $notifs = array_values(array_filter($toutes, fn($n)=>$n['type']===$filtreActif)); }
else { $notifs = $toutes; }

/* Regroupement par date : Aujourd'hui / Hier / Cette semaine / Plus ancien */
$groupes = ["Aujourd'hui"=>[], 'Hier'=>[], 'Cette semaine'=>[], 'Plus ancien'=>[]];
foreach ($notifs as $n) {
    $jours = (strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($n['date_creation'])))) / 86400;
    if ($jours < 1) $groupes["Aujourd'hui"][] = $n;
    elseif ($jours < 2) $groupes['Hier'][] = $n;
    elseif ($jours < 7) $groupes['Cette semaine'][] = $n;
    else $groupes['Plus ancien'][] = $n;
}

$page_title  = 'ServicesPlus – Notifications';
$page_active = 'notifications';
$racine      = '../';
$extra_css   = <<<CSS
.container{max-width:860px;margin:0 auto;padding:32px 20px}
.page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;flex-wrap:wrap;gap:12px}
.page-title{font-family:'Playfair Display',serif;font-size:26px;font-weight:900;margin-bottom:4px}
.page-sub{color:var(--slate);font-size:14px}
.header-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.btn-o{padding:8px 16px;background:transparent;color:var(--slate);border:1.5px solid var(--border);border-radius:10px;font-weight:600;cursor:pointer;font-size:13px}
.btn-o:hover{border-color:var(--primary);color:var(--primary)}
.summary-bar{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:24px}
.sum-card{background:white;border-radius:14px;padding:16px;border:1.5px solid var(--border);text-align:center}
.sum-val{font-size:22px;font-weight:900;margin-bottom:3px}
.sum-lbl{font-size:11px;color:var(--slate)}
.filter-bar{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
.ftab{padding:8px 18px;border-radius:20px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:13px;font-weight:700;color:var(--slate);text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.ftab:hover{border-color:var(--primary);color:var(--primary)}
.ftab.active{background:var(--primary);color:white;border-color:var(--primary)}
.ftab .cnt{background:#F1F5F9;color:var(--slate);padding:1px 7px;border-radius:20px;font-size:10px}
.ftab.active .cnt{background:rgba(255,255,255,0.25);color:white}
.date-group{font-size:12px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.7px;margin:20px 0 10px;padding-left:4px}
.notif-card{background:white;border-radius:16px;border:1.5px solid var(--border);margin-bottom:10px;overflow:hidden}
.notif-card.unread{border-left:4px solid var(--primary)}
.notif-card.unread .notif-inner{background:linear-gradient(to right,#FFF0EB,white)}
.notif-inner{display:flex;gap:14px;padding:18px 20px;align-items:flex-start}
.notif-icon-wrap{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
.notif-content{flex:1}
.notif-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:5px}
.notif-title{font-weight:700;font-size:14px}
.notif-time{font-size:11px;color:#94A3B8;white-space:nowrap;margin-left:10px;flex-shrink:0}
.notif-msg{font-size:13px;color:var(--slate);line-height:1.6;margin-bottom:10px}
.notif-badge{font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;margin-bottom:8px;display:inline-block}
.nb-blue{color:#2563EB;background:#EFF6FF} .nb-green{color:#059669;background:#ECFDF5}
.nb-orange{color:#D97706;background:#FFFBEB} .nb-red{color:#DC2626;background:#FEF2F2}
.nb-gray{color:#64748B;background:#F1F5F9} .nb-purple{color:#7C3AED;background:#F5F3FF}
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
    <div>
      <h1 class="page-title">🔔 Notifications</h1>
      <p class="page-sub"><?= $nonLues > 0 ? "$nonLues notification".($nonLues>1?'s':'')." non lue".($nonLues>1?'s':'') : 'Toutes les notifications sont lues' ?></p>
    </div>
    <div class="header-actions">
      <form method="post"><button type="submit" name="lire_tout" value="1" class="btn-o">✓ Tout marquer comme lu</button></form>
      <form method="post" onsubmit="return confirm('Supprimer toutes les notifications ?')"><button type="submit" name="supprimer_tout" value="1" class="btn-o">🗑️ Tout supprimer</button></form>
    </div>
  </div>

  <div class="summary-bar">
    <div class="sum-card"><div class="sum-val" style="color:#0F172A;"><?= $total ?></div><div class="sum-lbl">Total</div></div>
    <div class="sum-card"><div class="sum-val" style="color:var(--primary);"><?= $nonLues ?></div><div class="sum-lbl">Non lues</div></div>
    <div class="sum-card"><div class="sum-val" style="color:#2563EB;"><?= $aujourdhui ?></div><div class="sum-lbl">Aujourd'hui</div></div>
    <div class="sum-card"><div class="sum-val" style="color:#059669;"><?= $lues ?></div><div class="sum-lbl">Lues</div></div>
  </div>

  <div class="filter-bar">
    <?php foreach ($filtres as $f): ?>
      <a class="ftab <?= $filtreActif===$f['id']?'active':'' ?>" href="?filtre=<?= $f['id'] ?>"><?= $f['icon'] ?> <?= $f['lbl'] ?> <span class="cnt"><?= $f['cnt'] ?></span></a>
    <?php endforeach; ?>
  </div>

  <?php if (empty($notifs)): ?>
    <div class="empty-state"><div class="empty-icon">🔔</div><div class="empty-title">Aucune notification</div><div class="empty-sub">Vous serez notifié ici pour les nouvelles demandes, réservations et avis.</div></div>
  <?php else: foreach ($groupes as $label => $liste): if (empty($liste)) continue; ?>
    <div class="date-group"><?= $label ?></div>
    <?php foreach ($liste as $n): $meta = $typeMeta[$n['type']] ?? ['🔔','#F1F5F9','Info','nb-gray']; ?>
      <div class="notif-card <?= !$n['lu']?'unread':'' ?>">
        <div class="notif-inner">
          <div class="notif-icon-wrap" style="background:<?= $meta[1] ?>;"><?= $meta[0] ?></div>
          <div class="notif-content">
            <div class="notif-top"><span class="notif-title"><?= e($n['titre']) ?></span><span class="notif-time"><?= ilYA($n['date_creation']) ?></span></div>
            <span class="notif-badge <?= $meta[3] ?>"><?= $meta[2] ?></span>
            <div class="notif-msg"><?= e($n['message']) ?></div>
            <div class="notif-actions">
              <?php if (!$n['lu']): ?><form method="post"><input type="hidden" name="lire" value="<?= $n['id'] ?>"><button type="submit" class="btn-s gray">✓ Marquer lu</button></form><?php endif; ?>
              <?php if ($n['lien']): ?><a href="<?= e($n['lien']) ?>" class="btn-s gray" style="text-decoration:none;">→ Voir</a><?php endif; ?>
              <form method="post"><input type="hidden" name="supprimer" value="<?= $n['id'] ?>"><button type="submit" class="btn-s red">🗑️</button></form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; endforeach; ?>
  <?php endif; ?>
</div>

<?php require '../includes/footer.php'; ?>