<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('prestataire', '../');

$prestaId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.*, pp.categorie_id, pp.disponible, pp.tarif_horaire, pp.note_moyenne, pp.nb_avis, c.nom AS categorie_nom
                        FROM utilisateurs u JOIN prestataire_profils pp ON pp.user_id=u.id JOIN categories c ON c.id=pp.categorie_id
                        WHERE u.id=?");
$stmt->execute([$prestaId]);
$presta = $stmt->fetch();

/* ── Actions ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['toggle_dispo'])) {
        $pdo->prepare('UPDATE prestataire_profils SET disponible = 1 - disponible WHERE user_id=?')->execute([$prestaId]);
        header('Location: dashboard_prestataire.php'); exit;
    }
    if (isset($_POST['candidater'])) {
        $annonceId = (int)$_POST['annonce_id'];
        $chk = $pdo->prepare('SELECT id FROM candidatures WHERE annonce_id=? AND prestataire_id=?');
        $chk->execute([$annonceId, $prestaId]);
        if (!$chk->fetch()) {
            $pdo->prepare("INSERT INTO candidatures (annonce_id, prestataire_id, message, tarif_propose) VALUES (?,?,?,?)")
                ->execute([$annonceId, $prestaId, 'Je suis disponible et intéressé(e) par cette mission.', $presta['tarif_horaire']]);
            $pdo->prepare("UPDATE annonces SET statut='en_discussion' WHERE id=? AND statut='ouverte'")->execute([$annonceId]);
            $stmtA = $pdo->prepare('SELECT client_id, titre FROM annonces WHERE id=?'); $stmtA->execute([$annonceId]); $ann = $stmtA->fetch();
            if ($ann) { creerNotification($pdo, $ann['client_id'], 'Nouvelle candidature 🔧', "{$presta['prenom']} a candidaté pour \"{$ann['titre']}\".", 'annonce', '../espace_client/publier_annonce.php'); }
        }
        header('Location: dashboard_prestataire.php'); exit;
    }
    if (isset($_POST['refuser_annonce'])) {
        $annonceId = (int)$_POST['annonce_id'];
        $chk = $pdo->prepare('SELECT id FROM candidatures WHERE annonce_id=? AND prestataire_id=?');
        $chk->execute([$annonceId, $prestaId]);
        if (!$chk->fetch()) {
            $pdo->prepare("INSERT INTO candidatures (annonce_id, prestataire_id, message, statut) VALUES (?,?,?,'refusee')")
                ->execute([$annonceId, $prestaId, 'Non intéressé(e).']);
        }
        header('Location: dashboard_prestataire.php'); exit;
    }
}

/* ── Stats ── */
$moisActuel = date('Y-m');
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE prestataire_id=? AND DATE_FORMAT(date_reservation,'%Y-%m')=?");
$stmt->execute([$prestaId, $moisActuel]); $missionsMois = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE prestataire_id=? AND statut='en_cours'");
$stmt->execute([$prestaId]); $enCours = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE prestataire_id=? AND statut='terminee' AND DATE_FORMAT(date_reservation,'%Y-%m')=?");
$stmt->execute([$prestaId, $moisActuel]); $termineesMois = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM reservations WHERE prestataire_id=? AND statut='terminee' AND DATE_FORMAT(date_reservation,'%Y-%m')=?");
$stmt->execute([$prestaId, $moisActuel]); $revenusMois = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM candidatures WHERE prestataire_id=?'); $stmt->execute([$prestaId]); $totalCand=(int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM candidatures WHERE prestataire_id=? AND statut='acceptee'"); $stmt->execute([$prestaId]); $candAcceptees=(int)$stmt->fetchColumn();
$tauxAcceptation = $totalCand > 0 ? round($candAcceptees / $totalCand * 100) : 0;

$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE prestataire_id=? AND statut='annulee'"); $stmt->execute([$prestaId]); $nbAnnulees=(int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE prestataire_id=? AND statut='terminee'"); $stmt->execute([$prestaId]); $nbTermineesTotal=(int)$stmt->fetchColumn();
$tauxCompletion = ($nbTermineesTotal+$nbAnnulees) > 0 ? round($nbTermineesTotal/($nbTermineesTotal+$nbAnnulees)*100) : 100;

/* ── Demandes (annonces ouvertes de sa catégorie, pas encore répondues) ── */
$demandes = $pdo->prepare("
    SELECT a.*, cu.prenom, cu.nom
    FROM annonces a JOIN utilisateurs cu ON cu.id=a.client_id
    WHERE a.categorie_id=? AND a.statut IN ('ouverte','en_discussion')
      AND a.id NOT IN (SELECT annonce_id FROM candidatures WHERE prestataire_id=?)
    ORDER BY a.date_creation DESC LIMIT 5
");
$demandes->execute([$presta['categorie_id'], $prestaId]);
$demandes = $demandes->fetchAll();

/* ── Planning du jour ── */
$planningColors = [['#2563EB','#EFF6FF'],['#E85D26','#FFF0EB'],['#059669','#ECFDF5'],['#7C3AED','#F5F3FF']];
$planning = $pdo->prepare("SELECT r.*, cu.prenom, cu.nom FROM reservations r JOIN utilisateurs cu ON cu.id=r.client_id WHERE r.prestataire_id=? AND DATE(r.date_reservation)=CURDATE() ORDER BY r.date_reservation ASC");
$planning->execute([$prestaId]);
$planning = $planning->fetchAll();
$planningStatutLabel = ['en_attente'=>'À venir','confirmee'=>'À venir','en_cours'=>'En cours','terminee'=>'Terminé','annulee'=>'Annulé'];

/* ── Revenus 6 derniers mois ── */
$moisLabels=[]; $moisValeurs=[];
for ($i=5;$i>=0;$i--) {
    $m = date('Y-m', strtotime("-$i months"));
    $moisLabels[] = date('M', strtotime("-$i months"));
    $s = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM reservations WHERE prestataire_id=? AND statut='terminee' AND DATE_FORMAT(date_reservation,'%Y-%m')=?");
    $s->execute([$prestaId, $m]);
    $moisValeurs[] = (float)$s->fetchColumn();
}
$maxMois = max(1, max($moisValeurs));
$revenus6Mois = array_sum($moisValeurs);

/* ── Missions (filtrable) ── */
$filtre = $_GET['filtre'] ?? 'tous';
$whereM = 'r.prestataire_id=?'; $paramsM=[$prestaId];
if ($filtre !== 'tous') { $whereM .= ' AND r.statut=?'; $paramsM[]=$filtre; }
$missions = $pdo->prepare("
    SELECT r.*, cu.prenom AS client_prenom, cu.nom AS client_nom, c.nom AS service_nom,
           (SELECT note FROM avis WHERE reservation_id=r.id) AS note
    FROM reservations r JOIN utilisateurs cu ON cu.id=r.client_id JOIN categories c ON c.id=r.categorie_id
    WHERE $whereM ORDER BY r.date_reservation DESC LIMIT 15
");
$missions->execute($paramsM);
$missions = $missions->fetchAll();

/* ── Avis ── */
$avisRecents = $pdo->prepare("SELECT a.*, cu.prenom, cu.nom FROM avis a JOIN utilisateurs cu ON cu.id=a.client_id WHERE a.prestataire_id=? ORDER BY a.date_creation DESC LIMIT 5");
$avisRecents->execute([$prestaId]);
$avisRecents = $avisRecents->fetchAll();

$repartition = $pdo->prepare('SELECT note, COUNT(*) AS nb FROM avis WHERE prestataire_id=? GROUP BY note');
$repartition->execute([$prestaId]);
$repartitionNotes = array_fill(1,5,0);
foreach ($repartition->fetchAll() as $r) { $repartitionNotes[(int)$r['note']] = (int)$r['nb']; }

$statutLabels = ['en_attente'=>['En attente','badge-orange'],'confirmee'=>['Confirmée','badge-blue'],'en_cours'=>['En cours','badge-blue'],'terminee'=>['Terminée','badge-green'],'annulee'=>['Annulée','badge-gray']];

$joursF = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
$moisF  = [1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$dateAujourdhui = $joursF[(int)date('w')] . ' ' . (int)date('j') . ' ' . $moisF[(int)date('n')];

$page_title  = 'ServicesPlus – Espace prestataire';
$page_active = 'dashboard';
$racine      = '../';
$extra_css   = <<<CSS
.container{max-width:1180px;margin:0 auto;padding:28px 20px}
.dash-layout{display:grid;grid-template-columns:240px 1fr;gap:22px;align-items:start}
.sidebar{background:white;border-radius:20px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);overflow:hidden;position:sticky;top:86px}
.sidebar-user{padding:24px;background:linear-gradient(135deg,#1A3560,#2563EB);text-align:center}
.sidebar-avatar{width:72px;height:72px;border-radius:22px;background:rgba(255,255,255,0.15);display:flex;align-items:center;justify-content:center;font-size:36px;margin:0 auto 12px;border:3px solid rgba(255,255,255,0.3)}
.sidebar-name{color:white;font-weight:800;font-size:16px;margin-bottom:3px}
.sidebar-role{color:rgba(255,255,255,0.7);font-size:12px;margin-bottom:10px}
.dispo-toggle{display:flex;align-items:center;justify-content:center;gap:8px;background:rgba(255,255,255,0.12);border-radius:12px;padding:8px 14px;cursor:pointer;border:none;width:100%}
.dispo-toggle:hover{background:rgba(255,255,255,0.2)}
.toggle-dot{width:10px;height:10px;border-radius:50%;background:#4ADE80}
.toggle-dot.off{background:#94A3B8}
.toggle-label{color:white;font-size:12px;font-weight:700}
.sidebar-menu{padding:12px}
.menu-item{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:12px;cursor:pointer;font-size:13px;font-weight:600;color:var(--slate);text-decoration:none;margin-bottom:3px}
.menu-item:hover,.menu-item.active{background:var(--primary-light);color:var(--primary)}
.menu-item .icon{font-size:17px;width:22px;text-align:center}
.menu-sep{height:1px;background:var(--border);margin:10px 12px}
.menu-item.danger{color:#DC2626} .menu-item.danger:hover{background:#FEF2F2}
.card{background:white;border-radius:18px;padding:22px;border:1.5px solid var(--border);box-shadow:0 2px 6px rgba(15,23,42,0.04)}
.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;flex-wrap:wrap;gap:10px}
.card-title{font-weight:800;font-size:16px}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:20px}
.stat-card{background:white;border-radius:16px;padding:20px;border:1.5px solid var(--border);box-shadow:0 2px 6px rgba(15,23,42,0.04);position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px}
.stat-card.blue::before{background:#2563EB} .stat-card.gold::before{background:#F59E0B}
.stat-card.green::before{background:#059669} .stat-card.purple::before{background:#7C3AED}
.stat-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px}
.stat-icon{font-size:26px}
.stat-value{font-size:26px;font-weight:900;margin-bottom:3px}
.stat-label{font-size:12px;color:var(--slate)}
.stat-sub{font-size:11px;color:#94A3B8;margin-top:2px}

.demande-card{border:1.5px solid var(--border);border-radius:14px;padding:16px;margin-bottom:12px}
.demande-card:hover{border-color:var(--primary);box-shadow:0 4px 16px rgba(232,93,38,0.08)}
.demande-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px}
.demande-client{font-weight:800;font-size:15px}
.demande-urgence{font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:#FEF2F2;color:#DC2626}
.demande-planifie{font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;background:#ECFDF5;color:#059669}
.demande-info{display:flex;gap:16px;font-size:13px;color:var(--slate);margin-bottom:12px;flex-wrap:wrap}
.demande-info span{display:flex;align-items:center;gap:4px}
.demande-desc{font-size:13px;color:#475569;background:#F8FAFC;border-radius:10px;padding:10px 14px;margin-bottom:12px;font-style:italic}
.demande-actions{display:flex;gap:8px}
.btn-accept{flex:1;padding:10px 0;background:linear-gradient(135deg,#059669,#34D399);color:white;border:none;border-radius:10px;font-weight:700;cursor:pointer;font-size:13px}
.btn-reject{padding:10px 16px;background:#FEF2F2;color:#DC2626;border:1.5px solid #FECACA;border-radius:10px;font-weight:700;cursor:pointer;font-size:13px}

.planning-day{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #F8FAFC}
.planning-day:last-child{border-bottom:none}
.planning-time{font-size:12px;font-weight:700;color:#94A3B8;width:42px;flex-shrink:0;padding-top:2px}
.planning-bar{width:3px;border-radius:3px;flex-shrink:0}
.planning-info{flex:1}
.planning-title{font-weight:700;font-size:14px;margin-bottom:2px}
.planning-client{font-size:12px;color:var(--slate)}
.planning-badge{font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;margin-left:auto;align-self:flex-start;flex-shrink:0}

table{width:100%;border-collapse:collapse;font-size:13px}
th{text-align:left;padding:10px 12px;color:#94A3B8;font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:0.5px;border-bottom:2px solid #F1F5F9}
td{padding:13px 12px;border-bottom:1px solid #F8FAFC;vertical-align:middle}
tr:last-child td{border-bottom:none}
.badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-blue{color:#2563EB;background:#EFF6FF} .badge-green{color:#059669;background:#ECFDF5}
.badge-orange{color:#D97706;background:#FFFBEB} .badge-gray{color:#64748B;background:#F1F5F9}

.chart-wrap{display:flex;align-items:flex-end;gap:8px;height:110px}
.bar-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px}
.bar-fill{width:100%;border-radius:6px 6px 0 0}
.bar-lbl{font-size:10px;color:#94A3B8;font-weight:600}
.bar-val{font-size:10px;color:var(--slate);font-weight:700}

.avis-mini{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid #F8FAFC}
.avis-mini:last-child{border-bottom:none}
.avis-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:15px;flex-shrink:0}
.avis-name{font-weight:700;font-size:13px}
.avis-date{font-size:11px;color:#94A3B8}
.avis-text{font-size:13px;color:#475569;font-style:italic;margin-top:4px}

.perf-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #F8FAFC}
.perf-row:last-child{border-bottom:none}
.perf-label{font-size:13px;font-weight:600}
.perf-bar-wrap{flex:1;margin:0 16px;height:8px;background:#F1F5F9;border-radius:20px;overflow:hidden}
.perf-bar{height:100%;border-radius:20px;background:linear-gradient(135deg,#E85D26,#F59E0B)}
.perf-val{font-size:13px;font-weight:800;color:var(--primary);width:40px;text-align:right}

.two-col{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:20px}
.three-col{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:20px}
.res-tabs{display:flex;gap:6px;flex-wrap:wrap}
.res-tab{padding:7px 16px;border-radius:20px;border:1.5px solid var(--border);background:white;font-size:12px;font-weight:700;color:var(--slate);text-decoration:none}
.res-tab.active{background:var(--primary);color:white;border-color:var(--primary)}
.empty-mini{text-align:center;padding:24px;color:var(--slate);font-size:13px}
@media(max-width:1100px){ .stats-grid{grid-template-columns:repeat(2,1fr)} .three-col{grid-template-columns:1fr} }
@media(max-width:900px){ .dash-layout{grid-template-columns:1fr} .two-col{grid-template-columns:1fr} table{display:block;overflow-x:auto;white-space:nowrap} }
CSS;

require '../includes/header.php';
?>

<div class="container">
  <div class="dash-layout">
    <aside class="sidebar">
      <div class="sidebar-user">
        <div class="sidebar-avatar">🧑‍🔧</div>
        <div class="sidebar-name"><?= e($presta['prenom'].' '.$presta['nom']) ?></div>
        <div class="sidebar-role"><?= e($presta['categorie_nom']) ?> • <?= e($presta['ville'] ?? 'Sénégal') ?></div>
        <form method="post"><button type="submit" name="toggle_dispo" value="1" class="dispo-toggle">
          <div class="toggle-dot <?= $presta['disponible']?'':'off' ?>"></div>
          <span class="toggle-label"><?= $presta['disponible']?'Disponible':'Indisponible' ?></span>
        </button></form>
      </div>
      <div class="sidebar-menu">
        <a class="menu-item active" href="dashboard_prestataire.php"><span class="icon">📊</span> Vue d'ensemble</a>
        <a class="menu-item" href="#sec-demandes"><span class="icon">📩</span> Demandes <?php if($demandes): ?><span style="margin-left:auto;background:var(--primary);color:white;font-size:10px;padding:2px 7px;border-radius:20px;"><?= count($demandes) ?></span><?php endif; ?></a>
        <a class="menu-item" href="#sec-planning"><span class="icon">🗓️</span> Planning</a>
        <a class="menu-item" href="#sec-missions"><span class="icon">📋</span> Missions</a>
        <a class="menu-item" href="#sec-avis"><span class="icon">⭐</span> Mes avis</a>
        <a class="menu-item" href="messagerie.php"><span class="icon">💬</span> Messagerie</a>
        <div class="menu-sep"></div>
        <a class="menu-item" href="../profil_prestataire.php?id=<?= $prestaId ?>"><span class="icon">👤</span> Mon profil public</a>
        <a class="menu-item" href="mon_profil.php"><span class="icon">⚙️</span> Paramètres</a>
        <a class="menu-item" href="notifications.php"><span class="icon">🔔</span> Notifications</a>
        <div class="menu-sep"></div>
        <a class="menu-item danger" href="../deconnexion.php"><span class="icon">🚪</span> Déconnexion</a>
      </div>
    </aside>

    <main>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:22px;flex-wrap:wrap;gap:10px;">
        <div>
          <h1 style="font-family:'Playfair Display',serif;font-size:24px;font-weight:900;margin-bottom:4px;">🔧 Espace prestataire</h1>
          <p style="color:var(--slate);font-size:14px;">Bienvenue <?= e($presta['prenom']) ?> — voici votre tableau de bord du jour</p>
        </div>
        <a href="../profil_prestataire.php?id=<?= $prestaId ?>" style="padding:9px 18px;background:transparent;color:var(--slate);border:1.5px solid var(--border);border-radius:11px;font-weight:600;font-size:13px;text-decoration:none;">👁️ Voir mon profil</a>
      </div>

      <div class="stats-grid">
        <div class="stat-card blue">
          <div class="stat-top"><span class="stat-icon">📅</span></div>
          <div class="stat-value"><?= $missionsMois ?></div>
          <div class="stat-label">Missions ce mois</div>
          <div class="stat-sub"><?= $enCours ?> en cours • <?= $termineesMois ?> terminées</div>
        </div>
        <div class="stat-card gold">
          <div class="stat-top"><span class="stat-icon">⭐</span></div>
          <div class="stat-value"><?= $presta['note_moyenne'] ?: '—' ?></div>
          <div class="stat-label">Note globale</div>
          <div class="stat-sub">Sur <?= $presta['nb_avis'] ?> avis clients</div>
        </div>
        <div class="stat-card green">
          <div class="stat-top"><span class="stat-icon">💰</span></div>
          <div class="stat-value"><?= number_format($revenusMois,0,',',' ') ?></div>
          <div class="stat-label">Revenus ce mois (FCFA)</div>
        </div>
        <div class="stat-card purple">
          <div class="stat-top"><span class="stat-icon">✅</span></div>
          <div class="stat-value"><?= $tauxAcceptation ?>%</div>
          <div class="stat-label">Taux d'acceptation</div>
          <div class="stat-sub"><?= $totalCand ?> candidature<?= $totalCand>1?'s':'' ?> envoyée<?= $totalCand>1?'s':'' ?></div>
        </div>
      </div>

      <div class="two-col">
        <div class="card" id="sec-demandes">
          <div class="card-header">
            <span class="card-title">📩 Demandes disponibles</span>
            <?php if ($demandes): ?><span style="background:#FEF2F2;color:#DC2626;font-size:11px;font-weight:700;padding:4px 10px;border-radius:20px;"><?= count($demandes) ?> nouvelle<?= count($demandes)>1?'s':'' ?></span><?php endif; ?>
          </div>
          <?php if (empty($demandes)): ?>
            <div class="empty-mini">Aucune nouvelle demande dans votre catégorie pour le moment.</div>
          <?php else: foreach ($demandes as $d): ?>
            <div class="demande-card">
              <div class="demande-header">
                <div>
                  <div class="demande-client"><?= e($d['prenom'].' '.$d['nom']) ?></div>
                  <div style="font-size:12px;color:var(--slate);margin-top:2px;"><?= e($d['adresse']) ?></div>
                </div>
                <?php if ($d['urgence']): ?><span class="demande-urgence">🚨 Urgent</span><?php else: ?><span class="demande-planifie">Planifié</span><?php endif; ?>
              </div>
              <div class="demande-info">
                <span>📅 <?= date('d/m/Y', strtotime($d['date_souhaitee'])) ?></span>
                <span>📍 <?= e($d['ville']) ?></span>
                <?php if ($d['budget']): ?><span style="font-weight:800;color:var(--primary);">💰 <?= number_format($d['budget'],0,',',' ') ?> FCFA</span><?php endif; ?>
              </div>
              <div class="demande-desc">"<?= e($d['titre']) ?> — <?= e($d['description']) ?>"</div>
              <div class="demande-actions">
                <form method="post" style="flex:1;"><input type="hidden" name="annonce_id" value="<?= $d['id'] ?>"><button type="submit" name="candidater" value="1" class="btn-accept" style="width:100%;">✓ Postuler</button></form>
                <form method="post"><input type="hidden" name="annonce_id" value="<?= $d['id'] ?>"><button type="submit" name="refuser_annonce" value="1" class="btn-reject" onclick="return confirm('Ignorer cette demande ?')">✕</button></form>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <div class="card" id="sec-planning">
          <div class="card-header"><span class="card-title">🗓️ Planning d'aujourd'hui</span><span style="font-size:12px;color:var(--slate);"><?= $dateAujourdhui ?></span></div>
          <?php if (empty($planning)): ?>
            <div class="empty-mini">Aucune intervention prévue aujourd'hui.</div>
          <?php else: foreach ($planning as $i => $p): [$color,$bg] = $planningColors[$i % 4]; $lbl = $planningStatutLabel[$p['statut']] ?? 'À venir'; ?>
            <div class="planning-day">
              <div class="planning-time"><?= date('H:i', strtotime($p['date_reservation'])) ?></div>
              <div class="planning-bar" style="background:<?= $color ?>;"></div>
              <div class="planning-info">
                <div class="planning-title"><?= e($p['description'] ?: 'Intervention') ?></div>
                <div class="planning-client">👤 <?= e($p['prenom'].' '.$p['nom']) ?></div>
              </div>
              <span class="planning-badge" style="background:<?= $bg ?>;color:<?= $color ?>;"><?= $lbl ?></span>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="three-col">
        <div class="card" style="grid-column:1/3;">
          <div class="card-header"><span class="card-title">📈 Revenus mensuels (FCFA)</span></div>
          <div class="chart-wrap">
            <?php foreach ($moisValeurs as $i=>$v): ?>
              <div class="bar-col"><div class="bar-val"><?= number_format($v,0,',',' ') ?></div><div class="bar-fill" style="height:<?= max(4,($v/$maxMois)*96) ?>px;background:<?= $i===count($moisValeurs)-1 ? 'linear-gradient(180deg,#E85D26,#F59E0B)' : '#FFE8DF' ?>;"></div><div class="bar-lbl"><?= $moisLabels[$i] ?></div></div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;justify-content:space-between;margin-top:14px;padding-top:14px;border-top:1px solid #F1F5F9;">
            <div style="text-align:center;"><div style="font-size:20px;font-weight:900;color:var(--primary);"><?= number_format($revenusMois,0,',',' ') ?></div><div style="font-size:11px;color:var(--slate);">Ce mois</div></div>
            <div style="text-align:center;"><div style="font-size:20px;font-weight:900;color:#059669;"><?= number_format($revenus6Mois,0,',',' ') ?></div><div style="font-size:11px;color:var(--slate);">6 derniers mois</div></div>
            <div style="text-align:center;"><div style="font-size:20px;font-weight:900;color:#2563EB;"><?= number_format($revenus6Mois/6,0,',',' ') ?></div><div style="font-size:11px;color:var(--slate);">Moyenne/mois</div></div>
          </div>
        </div>

        <div class="card">
          <div class="card-title" style="margin-bottom:16px;">🏆 Performance</div>
          <div class="perf-row"><span class="perf-label">Satisfaction client</span><div class="perf-bar-wrap"><div class="perf-bar" style="width:<?= $presta['note_moyenne']/5*100 ?>%"></div></div><span class="perf-val"><?= round($presta['note_moyenne']/5*100) ?>%</span></div>
          <div class="perf-row"><span class="perf-label">Taux d'acceptation</span><div class="perf-bar-wrap"><div class="perf-bar" style="width:<?= $tauxAcceptation ?>%"></div></div><span class="perf-val"><?= $tauxAcceptation ?>%</span></div>
          <div class="perf-row"><span class="perf-label">Taux de complétion</span><div class="perf-bar-wrap"><div class="perf-bar" style="width:<?= $tauxCompletion ?>%"></div></div><span class="perf-val"><?= $tauxCompletion ?>%</span></div>
        </div>
      </div>

      <div class="card" style="margin-bottom:20px;" id="sec-missions">
        <div class="card-header">
          <span class="card-title">📋 Historique des missions</span>
          <div class="res-tabs">
            <a class="res-tab <?= $filtre==='tous'?'active':'' ?>" href="?filtre=tous">Toutes</a>
            <a class="res-tab <?= $filtre==='terminee'?'active':'' ?>" href="?filtre=terminee">Terminées</a>
            <a class="res-tab <?= $filtre==='en_cours'?'active':'' ?>" href="?filtre=en_cours">En cours</a>
            <a class="res-tab <?= $filtre==='annulee'?'active':'' ?>" href="?filtre=annulee">Annulées</a>
          </div>
        </div>
        <?php if (empty($missions)): ?>
          <div class="empty-mini">Aucune mission pour ce filtre.</div>
        <?php else: ?>
        <table>
          <thead><tr><th>Client</th><th>Service</th><th>Date</th><th>Durée</th><th>Montant</th><th>Note</th><th>Statut</th></tr></thead>
          <tbody>
          <?php foreach ($missions as $m): [$lbl,$cls] = $statutLabels[$m['statut']] ?? ['—','badge-gray']; ?>
            <tr>
              <td style="font-weight:700;"><?= e($m['client_prenom'].' '.$m['client_nom']) ?></td>
              <td style="color:var(--slate);"><?= e($m['service_nom']) ?></td>
              <td style="color:var(--slate);"><?= formaterDate($m['date_reservation']) ?></td>
              <td style="color:var(--slate);"><?= $m['duree_heures'] ?>h</td>
              <td style="font-weight:800;color:#059669;"><?= number_format($m['montant'],0,',',' ') ?> F</td>
              <td><?= $m['note'] ? '<span style="color:#F59E0B;">'.str_repeat('★',$m['note']).'</span>' : '<span style="color:#CBD5E1;font-size:12px;">Non noté</span>' ?></td>
              <td><span class="badge <?= $cls ?>"><?= $lbl ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>

      <div class="two-col" id="sec-avis">
        <div class="card">
          <div class="card-header"><span class="card-title">⭐ Derniers avis reçus</span></div>
          <?php if (empty($avisRecents)): ?>
            <div class="empty-mini">Pas encore d'avis reçu.</div>
          <?php else: foreach ($avisRecents as $a): ?>
            <div class="avis-mini">
              <div class="avis-av"><?= strtoupper(substr($a['prenom'],0,1)) ?></div>
              <div style="flex:1;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                  <span class="avis-name"><?= e($a['prenom']) ?></span>
                  <span class="avis-date"><?= ilYA($a['date_creation']) ?></span>
                </div>
                <div style="color:#F59E0B;font-size:13px;"><?= str_repeat('★',$a['note']).str_repeat('☆',5-$a['note']) ?></div>
                <p class="avis-text">"<?= e($a['commentaire']) ?>"</p>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>

        <div class="card">
          <div class="card-title" style="margin-bottom:16px;">📊 Répartition des notes</div>
          <div style="text-align:center;margin-bottom:18px;">
            <div style="font-size:52px;font-weight:900;line-height:1;"><?= $presta['note_moyenne'] ?: '—' ?></div>
            <div style="color:#F59E0B;font-size:24px;"><?= str_repeat('★', (int)round($presta['note_moyenne'])) ?></div>
            <div style="font-size:13px;color:var(--slate);margin-top:4px;">Basé sur <?= $presta['nb_avis'] ?> avis</div>
          </div>
          <?php for ($n=5;$n>=1;$n--): $pct = $presta['nb_avis']>0 ? round($repartitionNotes[$n]/$presta['nb_avis']*100) : 0; ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
              <span style="font-size:12px;color:var(--slate);width:12px;"><?= $n ?></span>
              <div style="flex:1;height:8px;background:#F1F5F9;border-radius:20px;overflow:hidden;"><div style="height:100%;border-radius:20px;background:linear-gradient(135deg,#E85D26,#F59E0B);width:<?= $pct ?>%;"></div></div>
              <span style="font-size:12px;color:var(--slate);width:30px;text-align:right;"><?= $pct ?>%</span>
            </div>
          <?php endfor; ?>
          <div style="margin-top:18px;padding:14px;background:#FFF0EB;border-radius:12px;">
            <div style="font-weight:700;font-size:14px;margin-bottom:4px;">💡 Conseil ServicesPlus</div>
            <div style="font-size:12px;color:#7C3C00;line-height:1.6;">Les prestataires disponibles et qui répondent vite aux demandes reçoivent davantage de réservations.</div>
          </div>
        </div>
      </div>
    </main>
  </div>
</div>

<?php require '../includes/footer.php'; ?>