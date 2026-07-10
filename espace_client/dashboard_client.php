<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('client', '../');

$clientId = $_SESSION['user_id'];
$client = $pdo->prepare('SELECT * FROM utilisateurs WHERE id=?');
$client->execute([$clientId]);
$client = $client->fetch();

/* ── Actions POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['annuler_reservation'])) {
        $resId = (int)$_POST['annuler_reservation'];
        $chk = $pdo->prepare("SELECT prestataire_id FROM reservations WHERE id=? AND client_id=? AND statut IN ('en_attente','confirmee')");
        $chk->execute([$resId, $clientId]);
        $prestaId = $chk->fetchColumn();
        if ($prestaId) {
            $pdo->prepare("UPDATE reservations SET statut='annulee' WHERE id=?")->execute([$resId]);
            creerNotification($pdo, $prestaId, 'Réservation annulée', 'Un client a annulé sa réservation.', 'annulation');
        }
        header('Location: dashboard_client.php'); exit;
    }
    if (isset($_POST['retirer_favori'])) {
        $pdo->prepare('DELETE FROM favoris WHERE client_id=? AND prestataire_id=?')->execute([$clientId, (int)$_POST['retirer_favori']]);
        header('Location: dashboard_client.php'); exit;
    }
    if (isset($_POST['soumettre_avis'])) {
        $resId = (int)$_POST['reservation_id'];
        $note = max(1, min(5, (int)$_POST['note']));
        $commentaire = trim($_POST['commentaire'] ?? '');
        $chk = $pdo->prepare("SELECT prestataire_id FROM reservations WHERE id=? AND client_id=? AND statut='terminee'");
        $chk->execute([$resId, $clientId]);
        $prestaId = $chk->fetchColumn();
        if ($prestaId) {
            $dejaAvis = $pdo->prepare('SELECT id FROM avis WHERE reservation_id=?');
            $dejaAvis->execute([$resId]);
            if (!$dejaAvis->fetch()) {
                $pdo->prepare('INSERT INTO avis (reservation_id, client_id, prestataire_id, note, commentaire) VALUES (?,?,?,?,?)')
                    ->execute([$resId, $clientId, $prestaId, $note, $commentaire]);
                recalculerNoteMoyenne($pdo, $prestaId);
                creerNotification($pdo, $prestaId, 'Nouvel avis reçu ⭐', "Vous avez reçu une note de $note/5.", 'avis');
            }
        }
        header('Location: dashboard_client.php'); exit;
    }
}

/* ── Stats ── */
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE client_id=? AND statut IN ('en_attente','confirmee','en_cours')");
$stmt->execute([$clientId]); $reservationsActives = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(DISTINCT categorie_id) FROM reservations WHERE client_id=?');
$stmt->execute([$clientId]); $servicesUtilises = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT AVG(note) FROM avis WHERE client_id=?');
$stmt->execute([$clientId]); $noteMoyenneDonnee = $stmt->fetchColumn();
$noteMoyenneDonnee = $noteMoyenneDonnee ? round($noteMoyenneDonnee,1) : 0;

$stmt = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM reservations WHERE client_id=? AND statut='terminee'");
$stmt->execute([$clientId]); $totalDepense = (float)$stmt->fetchColumn();

/* ── Réservations (avec filtre) ── */
$filtreRes = $_GET['filtre'] ?? 'tous';
$whereRes = 'r.client_id = ?'; $paramsRes = [$clientId];
if ($filtreRes !== 'tous') { $whereRes .= ' AND r.statut = ?'; $paramsRes[] = $filtreRes; }
$stmt = $pdo->prepare("
    SELECT r.*, u.prenom AS presta_prenom, u.nom AS presta_nom, c.nom AS categorie_nom,
           (SELECT COUNT(*) FROM avis WHERE reservation_id=r.id) AS a_avis
    FROM reservations r
    JOIN utilisateurs u ON u.id = r.prestataire_id
    JOIN categories c ON c.id = r.categorie_id
    WHERE $whereRes ORDER BY r.date_reservation DESC
");
$stmt->execute($paramsRes);
$reservations = $stmt->fetchAll();

$compteursRes = $pdo->prepare("SELECT statut, COUNT(*) AS nb FROM reservations WHERE client_id=? GROUP BY statut");
$compteursRes->execute([$clientId]);
$compteursRes = $compteursRes->fetchAll(PDO::FETCH_KEY_PAIR);
$totalRes = array_sum($compteursRes);

/* ── Favoris ── */
$stmt = $pdo->prepare("
    SELECT u.id, u.nom, u.prenom, pp.tarif_horaire, pp.note_moyenne, c.nom AS categorie_nom, c.icone
    FROM favoris f
    JOIN utilisateurs u ON u.id = f.prestataire_id
    JOIN prestataire_profils pp ON pp.user_id = u.id
    JOIN categories c ON c.id = pp.categorie_id
    WHERE f.client_id = ? ORDER BY f.date_creation DESC
");
$stmt->execute([$clientId]);
$favoris = $stmt->fetchAll();

/* ── Activité récente (notifications) ── */
$stmt = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY date_creation DESC LIMIT 6');
$stmt->execute([$clientId]);
$activites = $stmt->fetchAll();

/* ── Dépenses 6 derniers mois ── */
$moisLabels = []; $moisValeurs = [];
for ($i=5; $i>=0; $i--) {
    $mois = date('Y-m', strtotime("-$i months"));
    $moisLabels[] = date('M', strtotime("-$i months"));
    $s = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM reservations WHERE client_id=? AND statut='terminee' AND DATE_FORMAT(date_reservation,'%Y-%m')=?");
    $s->execute([$clientId, $mois]);
    $moisValeurs[] = (float)$s->fetchColumn();
}
$maxMois = max(1, max($moisValeurs));

$depensesCat = $pdo->prepare("
    SELECT c.nom, c.icone, COALESCE(SUM(r.montant),0) AS total
    FROM reservations r JOIN categories c ON c.id=r.categorie_id
    WHERE r.client_id=? AND r.statut='terminee' GROUP BY c.id ORDER BY total DESC LIMIT 3
");
$depensesCat->execute([$clientId]);
$depensesCat = $depensesCat->fetchAll();

$typeIcones = ['prestataire'=>'🔧','valide'=>'✅','refuse'=>'❌','suspendu'=>'🚫','actif'=>'✅','annulation'=>'❌','avis'=>'⭐','info'=>'🔔'];

$statutLabels = ['en_attente'=>['En attente','badge-orange'],'confirmee'=>['Confirmée','badge-blue'],'en_cours'=>['En cours','badge-gray'],'terminee'=>['Terminée','badge-green'],'annulee'=>['Annulée','badge-red']];

$page_title  = 'ServicesPlus – Dashboard';
$page_active = 'dashboard';
$racine      = '../';
$extra_css   = <<<CSS
.container{max-width:1180px;margin:0 auto;padding:28px 20px}
.dash-layout{display:grid;grid-template-columns:240px 1fr;gap:22px;align-items:start}
.sidebar{background:white;border-radius:20px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);overflow:hidden;position:sticky;top:86px}
.sidebar-user{padding:24px;background:linear-gradient(135deg,#1A3560,#2563EB);text-align:center}
.sidebar-avatar{width:72px;height:72px;border-radius:22px;background:linear-gradient(135deg,#E85D26,#F59E0B);display:flex;align-items:center;justify-content:center;font-size:34px;margin:0 auto 12px;border:3px solid rgba(255,255,255,0.3);color:white;font-weight:800}
.sidebar-name{color:white;font-weight:800;font-size:16px;margin-bottom:3px}
.sidebar-role{color:rgba(255,255,255,0.7);font-size:12px;margin-bottom:10px}
.sidebar-badge{display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,0.15);border-radius:20px;padding:4px 12px;font-size:11px;color:white;font-weight:600}
.sidebar-menu{padding:12px}
.menu-item{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:12px;cursor:pointer;font-size:13px;font-weight:600;color:var(--slate);text-decoration:none;margin-bottom:3px}
.menu-item:hover,.menu-item.active{background:var(--primary-light);color:var(--primary)}
.menu-item .icon{font-size:17px;width:22px;text-align:center}
.menu-sep{height:1px;background:var(--border);margin:10px 12px}
.menu-item.danger{color:#DC2626}
.menu-item.danger:hover{background:#FEF2F2}
.card{background:white;border-radius:18px;padding:22px;border:1.5px solid var(--border);box-shadow:0 2px 6px rgba(15,23,42,0.04);margin-bottom:20px}
.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px}
.card-title{font-weight:800;font-size:16px}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:white;border-radius:16px;padding:20px;border:1.5px solid var(--border);box-shadow:0 2px 6px rgba(15,23,42,0.04);position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.stat-card.blue::before{background:#2563EB} .stat-card.orange::before{background:#E85D26}
.stat-card.gold::before{background:#F59E0B} .stat-card.green::before{background:#059669}
.stat-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
.stat-icon{font-size:26px}
.stat-value{font-size:26px;font-weight:900;margin-bottom:3px}
.stat-label{font-size:12px;color:var(--slate)}
.res-tabs{display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap}
.res-tab{padding:7px 16px;border-radius:20px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:12px;font-weight:700;color:var(--slate);text-decoration:none}
.res-tab.active{background:var(--primary);color:white;border-color:var(--primary)}
table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;padding:10px 12px;color:#94A3B8;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #F1F5F9}
td{padding:14px 12px;border-bottom:1px solid #F8FAFC;vertical-align:middle}
tr:last-child td{border-bottom:none}
.badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-blue{color:#2563EB;background:#EFF6FF} .badge-green{color:#059669;background:#ECFDF5}
.badge-orange{color:#D97706;background:#FFFBEB} .badge-gray{color:#64748B;background:#F1F5F9}
.badge-red{color:#DC2626;background:#FEF2F2}
.btn-sm{padding:5px 12px;border-radius:8px;border:none;cursor:pointer;font-size:11px;font-weight:700;font-family:'Inter',sans-serif}
.btn-sm.orange{background:var(--primary-light);color:var(--primary)}
.btn-sm.gold{background:#FFFBEB;color:#D97706}
.btn-sm.red{background:#FEF2F2;color:#DC2626}
.btn-sm.gray{background:#F1F5F9;color:#64748B}
.favoris-grid{display:flex;flex-direction:column;gap:10px}
.favori-card{background:#F8FAFC;border-radius:14px;padding:14px;display:flex;gap:10px;align-items:center;border:1.5px solid var(--border)}
.fav-avatar{width:46px;height:46px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;background:#FFE8DF}
.fav-name{font-weight:700;font-size:13px;margin-bottom:2px}
.fav-service{font-size:11px;color:var(--slate)}
.fav-stars{color:#F59E0B;font-size:11px}
.fav-actions{margin-left:auto;display:flex;gap:6px}
.activity-list{display:flex;flex-direction:column;gap:0}
.activity-item{display:flex;gap:14px;align-items:flex-start;padding:13px 0;border-bottom:1px solid #F8FAFC}
.activity-item:last-child{border-bottom:none}
.act-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;background:#FFF0EB}
.act-msg{font-size:13px;font-weight:600;margin-bottom:3px}
.act-time{font-size:11px;color:#94A3B8}
.act-dot{width:7px;height:7px;border-radius:50%;background:var(--primary);margin-left:auto;margin-top:6px;flex-shrink:0}
.chart-bars{display:flex;align-items:flex-end;gap:8px;height:100px;margin-top:8px}
.bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
.bar-fill{width:100%;border-radius:6px 6px 0 0;background:#FFE8DF}
.bar-fill.last{background:linear-gradient(180deg,#E85D26,#F59E0B)}
.bar-label{font-size:10px;color:#94A3B8;font-weight:600}
.bar-val{font-size:10px;color:var(--slate);font-weight:700}
.btn-primary{padding:10px 20px;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:11px;font-weight:700;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px}
.empty-mini{text-align:center;padding:30px;color:var(--slate);font-size:13px}
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center}
.modal-overlay.show{display:flex}
.modal{background:white;border-radius:22px;padding:32px;max-width:440px;width:90%}
.modal-title{font-weight:900;font-size:20px;margin-bottom:6px}
.modal-sub{color:var(--slate);font-size:13px;margin-bottom:20px}
.stars-select{display:flex;gap:10px;justify-content:center;margin-bottom:20px}
.star-btn{font-size:34px;cursor:pointer;filter:grayscale(1);opacity:0.4;background:none;border:none}
.star-btn.active{filter:none;opacity:1}
textarea.modal-ta{width:100%;padding:12px 16px;border-radius:11px;border:1.5px solid var(--border);font-size:14px;font-family:'Inter',sans-serif;resize:none;min-height:90px}
@media(max-width:900px){
  .dash-layout{grid-template-columns:1fr}
  .stats-grid{grid-template-columns:repeat(2,1fr)}
  .two-col{grid-template-columns:1fr}
  table{display:block;overflow-x:auto;white-space:nowrap}
}
CSS;

require '../includes/header.php';
?>

<div class="container">
  <div class="dash-layout">

    <aside class="sidebar">
      <div class="sidebar-user">
        <div class="sidebar-avatar"><?= strtoupper(substr($client['prenom'],0,1)) ?></div>
        <div class="sidebar-name"><?= e($client['prenom'].' '.$client['nom']) ?></div>
        <div class="sidebar-role">Client • <?= e($client['ville'] ?? 'Sénégal') ?></div>
        <div class="sidebar-badge">✓ Compte actif</div>
      </div>
      <div class="sidebar-menu">
        <a class="menu-item active" href="dashboard_client.php"><span class="icon">📊</span> Vue d'ensemble</a>
        <a class="menu-item" href="publier_annonce.php"><span class="icon">📢</span> Publier une annonce</a>
        <a class="menu-item" href="../recherche.php"><span class="icon">🔍</span> Trouver un prestataire</a>
        <div class="menu-sep"></div>
        <a class="menu-item" href="mon_profil.php"><span class="icon">⚙️</span> Mon profil</a>
        <a class="menu-item" href="notifications.php"><span class="icon">🔔</span> Notifications</a>
        <div class="menu-sep"></div>
        <a class="menu-item danger" href="../deconnexion.php"><span class="icon">🚪</span> Déconnexion</a>
      </div>
    </aside>

    <main>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
        <div>
          <h1 style="font-family:'Playfair Display',serif;font-size:24px;font-weight:900;margin-bottom:4px;">👋 Bonjour, <?= e($client['prenom']) ?> !</h1>
          <p style="color:var(--slate);font-size:14px;">Voici un aperçu de votre activité sur ServicesPlus</p>
        </div>
        <a class="btn-primary" href="../recherche.php">+ Nouvelle réservation</a>
      </div>

      <div class="stats-grid">
        <div class="stat-card blue"><div class="stat-top"><span class="stat-icon">📅</span></div><div class="stat-value"><?= $reservationsActives ?></div><div class="stat-label">Réservations actives</div></div>
        <div class="stat-card orange"><div class="stat-top"><span class="stat-icon">🛠️</span></div><div class="stat-value"><?= $servicesUtilises ?></div><div class="stat-label">Types de services utilisés</div></div>
        <div class="stat-card gold"><div class="stat-top"><span class="stat-icon">⭐</span></div><div class="stat-value"><?= $noteMoyenneDonnee ?: '—' ?></div><div class="stat-label">Note moyenne donnée</div></div>
        <div class="stat-card green"><div class="stat-top"><span class="stat-icon">💰</span></div><div class="stat-value"><?= number_format($totalDepense,0,',',' ') ?></div><div class="stat-label">FCFA dépensés au total</div></div>
      </div>

      <div class="card" id="sec-reservations">
        <div class="card-header">
          <span class="card-title">📋 Mes réservations</span>
          <a class="btn-primary" href="../recherche.php">+ Nouvelle</a>
        </div>
        <div class="res-tabs">
          <a class="res-tab <?= $filtreRes==='tous'?'active':'' ?>" href="?filtre=tous">Toutes (<?= $totalRes ?>)</a>
          <a class="res-tab <?= $filtreRes==='en_attente'?'active':'' ?>" href="?filtre=en_attente">En attente (<?= $compteursRes['en_attente'] ?? 0 ?>)</a>
          <a class="res-tab <?= $filtreRes==='confirmee'?'active':'' ?>" href="?filtre=confirmee">Confirmées (<?= $compteursRes['confirmee'] ?? 0 ?>)</a>
          <a class="res-tab <?= $filtreRes==='en_cours'?'active':'' ?>" href="?filtre=en_cours">En cours (<?= $compteursRes['en_cours'] ?? 0 ?>)</a>
          <a class="res-tab <?= $filtreRes==='terminee'?'active':'' ?>" href="?filtre=terminee">Terminées (<?= $compteursRes['terminee'] ?? 0 ?>)</a>
        </div>
        <?php if (empty($reservations)): ?>
          <div class="empty-mini">Aucune réservation pour ce filtre. <a href="../recherche.php" style="color:var(--primary);font-weight:700;">Réservez un prestataire →</a></div>
        <?php else: ?>
        <table>
          <thead><tr><th>Réf.</th><th>Service</th><th>Prestataire</th><th>Date</th><th>Montant</th><th>Statut</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($reservations as $r): [$lbl,$cls] = $statutLabels[$r['statut']] ?? ['—','badge-gray']; ?>
            <tr>
              <td><strong><?= e($r['reference']) ?></strong></td>
              <td><?= e($r['categorie_nom']) ?></td>
              <td><?= e($r['presta_prenom'].' '.$r['presta_nom']) ?></td>
              <td><?= formaterDate($r['date_reservation']) ?></td>
              <td style="font-weight:800;color:var(--primary);"><?= number_format($r['montant'],0,',',' ') ?> F</td>
              <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
              <td>
                <div style="display:flex;gap:5px;flex-wrap:wrap;">
                  <?php if ($r['statut']==='terminee' && !$r['a_avis']): ?>
                    <button class="btn-sm gold" onclick="ouvrirAvis(<?= $r['id'] ?>,'<?= e(addslashes($r['presta_prenom'])) ?>')">⭐ Noter</button>
                  <?php elseif ($r['statut']==='terminee'): ?>
                    <span class="badge badge-gray">Déjà noté</span>
                  <?php endif; ?>
                  <?php if (in_array($r['statut'], ['en_attente','confirmee'])): ?>
                    <form method="post" onsubmit="return confirm('Annuler cette réservation ?')"><input type="hidden" name="annuler_reservation" value="<?= $r['id'] ?>"><button type="submit" class="btn-sm red">✕ Annuler</button></form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <div class="two-col">
        <div class="card" id="sec-favoris">
          <div class="card-header"><span class="card-title">❤️ Mes favoris</span><a href="../recherche.php" style="font-size:13px;color:var(--primary);font-weight:700;text-decoration:none;">Voir plus →</a></div>
          <div class="favoris-grid">
            <?php if (empty($favoris)): ?>
              <div class="empty-mini">Aucun favori. Ajoutez-en depuis le profil d'un prestataire.</div>
            <?php else: foreach ($favoris as $f): ?>
              <div class="favori-card">
                <div class="fav-avatar"><?= $f['icone'] ?></div>
                <div>
                  <div class="fav-name"><?= e($f['prenom'].' '.$f['nom']) ?></div>
                  <div class="fav-service"><?= e($f['categorie_nom']) ?></div>
                  <div class="fav-stars">⭐ <?= e((string)$f['note_moyenne']) ?></div>
                </div>
                <div class="fav-actions">
                  <a class="btn-sm orange" href="../profil_prestataire.php?id=<?= $f['id'] ?>">Voir</a>
                  <form method="post"><input type="hidden" name="retirer_favori" value="<?= $f['id'] ?>"><button type="submit" class="btn-sm red">✕</button></form>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <div class="card" id="sec-activite">
          <div class="card-header"><span class="card-title">🕐 Activité récente</span><a href="notifications.php" style="font-size:13px;color:var(--primary);font-weight:700;text-decoration:none;">Tout voir →</a></div>
          <div class="activity-list">
            <?php if (empty($activites)): ?>
              <div class="empty-mini">Aucune activité récente.</div>
            <?php else: foreach ($activites as $a): ?>
              <div class="activity-item">
                <div class="act-icon"><?= $typeIcones[$a['type']] ?? '🔔' ?></div>
                <div><div class="act-msg"><?= e($a['titre']) ?></div><div class="act-time"><?= ilYA($a['date_creation']) ?></div></div>
                <?php if (!$a['lu']): ?><div class="act-dot"></div><?php endif; ?>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
      </div>

      <div class="card" id="sec-depenses">
        <div class="card-header">
          <span class="card-title">💰 Mes dépenses (6 derniers mois)</span>
          <span style="font-size:13px;color:var(--slate);">Total : <strong style="color:var(--primary);"><?= number_format($totalDepense,0,',',' ') ?> FCFA</strong></span>
        </div>
        <div class="chart-bars">
          <?php foreach ($moisValeurs as $i => $v): ?>
            <div class="bar-col">
              <div class="bar-val"><?= number_format($v,0,',',' ') ?></div>
              <div class="bar-fill <?= $i===count($moisValeurs)-1?'last':'' ?>" style="height:<?= max(4,($v/$maxMois)*82) ?>px;"></div>
              <div class="bar-label"><?= $moisLabels[$i] ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($depensesCat): ?>
        <div style="display:grid;grid-template-columns:repeat(<?= count($depensesCat) ?>,1fr);gap:12px;margin-top:18px;">
          <?php foreach ($depensesCat as $d): ?>
            <div style="background:#F8FAFC;border-radius:12px;padding:14px;text-align:center;">
              <div style="font-size:22px;margin-bottom:6px;"><?= $d['icone'] ?></div>
              <div style="font-weight:800;font-size:15px;color:var(--primary);"><?= number_format($d['total'],0,',',' ') ?></div>
              <div style="font-size:11px;color:var(--slate);margin-top:2px;"><?= e($d['nom']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </main>
  </div>
</div>

<!-- Modal avis -->
<div class="modal-overlay" id="modalAvis">
  <div class="modal">
    <div class="modal-title">⭐ Évaluer votre prestation</div>
    <div class="modal-sub">Comment s'est passée votre intervention avec <strong id="modalPrestaName"></strong> ?</div>
    <form method="post" id="formAvis">
      <input type="hidden" name="reservation_id" id="avisResId">
      <input type="hidden" name="note" id="avisNoteInput" value="5">
      <div class="stars-select" id="starsSelect">
        <?php for ($i=1;$i<=5;$i++): ?><button type="button" class="star-btn active" data-n="<?= $i ?>" onclick="setStar(<?= $i ?>)">★</button><?php endfor; ?>
      </div>
      <textarea class="modal-ta" name="commentaire" placeholder="Décrivez votre expérience (optionnel)..."></textarea>
      <div style="display:flex;gap:10px;margin-top:16px;">
        <button type="submit" name="soumettre_avis" value="1" class="btn-primary" style="flex:2;text-align:center;border:none;">Publier l'avis</button>
        <button type="button" class="btn-sm gray" style="flex:1;" onclick="closeModal()">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
  function ouvrirAvis(resId, prenom) {
    document.getElementById('avisResId').value = resId;
    document.getElementById('modalPrestaName').textContent = prenom;
    setStar(5);
    document.getElementById('modalAvis').classList.add('show');
  }
  function closeModal() { document.getElementById('modalAvis').classList.remove('show'); }
  function setStar(n) {
    document.getElementById('avisNoteInput').value = n;
    document.querySelectorAll('.star-btn').forEach(b => b.classList.toggle('active', parseInt(b.dataset.n) <= n));
  }
  document.getElementById('modalAvis').addEventListener('click', function(e){ if (e.target === this) closeModal(); });
</script>

<?php require '../includes/footer.php'; ?>