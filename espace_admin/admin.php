<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('admin', '../');

/* ── Actions rapides depuis le dashboard (valider / refuser un prestataire) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_rapide'])) {
    $prestataireId = (int)$_POST['prestataire_id'];
    $nouveauStatut = $_POST['action_rapide'] === 'valider' ? 'valide' : 'refuse';

    $pdo->prepare('UPDATE prestataire_profils SET statut_validation = ?, admin_validateur_id = ?, date_validation = NOW() WHERE user_id = ?')
        ->execute([$nouveauStatut, $_SESSION['user_id'], $prestataireId]);

    $u = $pdo->prepare('SELECT prenom FROM utilisateurs WHERE id = ?');
    $u->execute([$prestataireId]);
    $prenom = $u->fetchColumn();

    creerNotification(
        $pdo, $prestataireId,
        $nouveauStatut === 'valide' ? 'Compte validé ✅' : 'Inscription refusée',
        $nouveauStatut === 'valide'
            ? 'Votre dossier prestataire a été validé. Vous pouvez maintenant vous connecter.'
            : 'Votre dossier prestataire a été refusé. Contactez le support pour plus d\'informations.',
        $nouveauStatut
    );

    header('Location: admin.php');
    exit;
}

/* ── Statistiques globales ── */
$nbClients        = (int)$pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role='client'")->fetchColumn();
$nbPrestaValides  = (int)$pdo->query("SELECT COUNT(*) FROM prestataire_profils WHERE statut_validation='valide'")->fetchColumn();
$nbPrestaAttente  = (int)$pdo->query("SELECT COUNT(*) FROM prestataire_profils WHERE statut_validation='en_attente'")->fetchColumn();
$nbReservations   = (int)$pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
$nbReservTerm     = (int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE statut='terminee'")->fetchColumn();
$revenuTotal      = (float)$pdo->query("SELECT COALESCE(SUM(montant),0) FROM reservations WHERE statut='terminee'")->fetchColumn();
$noteGlobale      = $pdo->query("SELECT AVG(note_moyenne) FROM prestataire_profils WHERE statut_validation='valide' AND nb_avis>0")->fetchColumn();
$noteGlobale      = $noteGlobale ? round($noteGlobale,1) : 0;
$nbMessagesNonTraites = (int)$pdo->query("SELECT COUNT(*) FROM messages_contact WHERE traite=0")->fetchColumn();

/* ── Réservations des 6 derniers mois (pour la courbe) ── */
$moisLabels = []; $moisData = [];
for ($i = 5; $i >= 0; $i--) {
    $mois = date('Y-m', strtotime("-$i months"));
    $moisLabels[] = date('M Y', strtotime("-$i months"));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE DATE_FORMAT(date_creation,'%Y-%m') = ?");
    $stmt->execute([$mois]);
    $moisData[] = (int)$stmt->fetchColumn();
}

/* ── Répartition des prestataires validés par catégorie (pour le donut) ── */
$repartitionCat = $pdo->query("
    SELECT c.nom, COUNT(pp.user_id) AS nb
    FROM categories c
    LEFT JOIN prestataire_profils pp ON pp.categorie_id = c.id AND pp.statut_validation='valide'
    GROUP BY c.id HAVING nb > 0
    ORDER BY nb DESC
")->fetchAll();

/* ── Prestataires en attente de validation (5 derniers) ── */
$enAttente = $pdo->query("
    SELECT u.id, u.nom, u.prenom, u.email, u.date_creation, c.nom AS categorie_nom, pp.tarif_horaire
    FROM prestataire_profils pp
    JOIN utilisateurs u ON u.id = pp.user_id
    JOIN categories c ON c.id = pp.categorie_id
    WHERE pp.statut_validation = 'en_attente'
    ORDER BY u.date_creation DESC LIMIT 5
")->fetchAll();

/* ── Derniers inscrits (tous rôles) ── */
$derniersInscrits = $pdo->query("
    SELECT nom, prenom, email, role, date_creation FROM utilisateurs
    WHERE role != 'admin' ORDER BY date_creation DESC LIMIT 6
")->fetchAll();

/* ── Dernières réservations ── */
$dernieresReservations = $pdo->query("
    SELECT r.reference, r.montant, r.statut, r.date_creation, cu.prenom AS client_prenom, pu.prenom AS presta_prenom, pu.nom AS presta_nom
    FROM reservations r
    JOIN utilisateurs cu ON cu.id = r.client_id
    JOIN utilisateurs pu ON pu.id = r.prestataire_id
    ORDER BY r.date_creation DESC LIMIT 6
")->fetchAll();

$page_title  = 'Tableau de bord';
$page_active = 'dashboard';
$extra_css   = <<<CSS
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:22px}
.stat-card{background:white;border-radius:18px;padding:20px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);position:relative;overflow:hidden;transition:transform .2s}
.stat-card:hover{transform:translateY(-3px);box-shadow:0 10px 28px rgba(15,23,42,0.08)}
.stat-card-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px}
.stat-icon-wrap{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:20px}
.stat-trend{font-size:11px;font-weight:800;padding:3px 9px;border-radius:20px}
.stat-trend.up{color:#059669;background:#ECFDF5}
.stat-trend.warn{color:#D97706;background:#FFFBEB}
.stat-value{font-size:26px;font-weight:900;margin-bottom:2px}
.stat-label{font-size:12.5px;color:var(--slate);font-weight:600}
.charts-grid{display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:22px}
.chart-card{background:white;border-radius:18px;padding:22px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05)}
.chart-card-title{font-weight:800;font-size:15px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center}
.chart-wrap{position:relative;height:260px}
.tables-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.panel{background:white;border-radius:18px;padding:22px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);margin-bottom:16px}
.panel-title{font-weight:800;font-size:15px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center}
.panel-link{font-size:12px;color:var(--primary);font-weight:700;text-decoration:none}
.attente-item{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #F8FAFC}
.attente-item:last-child{border-bottom:none}
.attente-avatar{width:40px;height:40px;border-radius:12px;background:#FFF0EB;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.attente-name{font-weight:700;font-size:13.5px}
.attente-sub{font-size:11.5px;color:var(--slate)}
.attente-actions{display:flex;gap:6px;margin-left:auto}
.btn-mini{padding:6px 12px;border-radius:8px;border:none;font-size:11.5px;font-weight:800;cursor:pointer;font-family:'Inter',sans-serif}
.btn-mini.valider{background:#ECFDF5;color:#059669}
.btn-mini.valider:hover{background:#059669;color:white}
.btn-mini.refuser{background:#FEF2F2;color:#DC2626}
.btn-mini.refuser:hover{background:#DC2626;color:white}
.empty-mini{text-align:center;padding:30px 10px;color:var(--slate);font-size:13px}
.mini-row{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid #F8FAFC;font-size:13px}
.mini-row:last-child{border-bottom:none}
.mini-badge{font-size:10px;font-weight:800;padding:2px 8px;border-radius:20px;text-transform:uppercase}
.mini-badge.client{background:#EFF6FF;color:#2563EB}
.mini-badge.prestataire{background:#FFF0EB;color:var(--primary)}
.statut-pill{font-size:10.5px;font-weight:800;padding:3px 10px;border-radius:20px;text-transform:uppercase}
.statut-pill.confirmee{background:#EFF6FF;color:#2563EB}
.statut-pill.terminee{background:#ECFDF5;color:#059669}
.statut-pill.en_attente{background:#FFFBEB;color:#D97706}
.statut-pill.annulee{background:#F1F5F9;color:#64748B}
.statut-pill.en_cours{background:#FDF4FF;color:#9333EA}
@media(max-width:1200px){ .stats-grid{grid-template-columns:repeat(2,1fr)} .charts-grid{grid-template-columns:1fr} .tables-grid{grid-template-columns:1fr} }
@media(max-width:560px){ .stats-grid{grid-template-columns:1fr} }
CSS;

require 'admin_header.php';
?>

<div class="stats-grid">
  <div class="stat-card anim anim-d1">
    <div class="stat-card-top"><div class="stat-icon-wrap" style="background:#EFF6FF;">👤</div><span class="stat-trend up">Actifs</span></div>
    <div class="stat-value"><?= number_format($nbClients,0,',',' ') ?></div>
    <div class="stat-label">Clients inscrits</div>
  </div>
  <div class="stat-card anim anim-d2">
    <div class="stat-card-top"><div class="stat-icon-wrap" style="background:#FFF0EB;">🔧</div><span class="stat-trend up">Validés</span></div>
    <div class="stat-value"><?= number_format($nbPrestaValides,0,',',' ') ?></div>
    <div class="stat-label">Prestataires actifs</div>
  </div>
  <div class="stat-card anim anim-d3">
    <div class="stat-card-top"><div class="stat-icon-wrap" style="background:#FFFBEB;">⏳</div><?php if($nbPrestaAttente>0): ?><span class="stat-trend warn">À traiter</span><?php endif; ?></div>
    <div class="stat-value"><?= number_format($nbPrestaAttente,0,',',' ') ?></div>
    <div class="stat-label">En attente de validation</div>
  </div>
  <div class="stat-card anim anim-d4">
    <div class="stat-card-top"><div class="stat-icon-wrap" style="background:#ECFDF5;">💰</div><span class="stat-trend up"><?= $nbReservTerm ?> missions</span></div>
    <div class="stat-value"><?= number_format($revenuTotal,0,',',' ') ?> F</div>
    <div class="stat-label">Revenu total généré</div>
  </div>
</div>

<div class="charts-grid">
  <div class="chart-card anim anim-d1">
    <div class="chart-card-title"><span>📈 Réservations (6 derniers mois)</span></div>
    <div class="chart-wrap"><canvas id="chartReservations"></canvas></div>
  </div>
  <div class="chart-card anim anim-d2">
    <div class="chart-card-title"><span>🥧 Prestataires par catégorie</span></div>
    <div class="chart-wrap"><canvas id="chartCategories"></canvas></div>
  </div>
</div>

<div class="tables-grid">
  <div class="panel anim anim-d1">
    <div class="panel-title"><span>⏳ Prestataires en attente</span><a class="panel-link" href="gestion_prestataires.php">Voir tout →</a></div>
    <?php if (empty($enAttente)): ?>
      <div class="empty-mini">✅ Aucun dossier en attente</div>
    <?php else: foreach ($enAttente as $p): ?>
      <div class="attente-item">
        <div class="attente-avatar">🧑‍🔧</div>
        <div>
          <div class="attente-name"><?= e($p['prenom'].' '.$p['nom']) ?></div>
          <div class="attente-sub"><?= e($p['categorie_nom']) ?> • <?= number_format($p['tarif_horaire'],0,',',' ') ?> FCFA/h</div>
        </div>
        <div class="attente-actions">
          <form method="post" style="display:inline;"><input type="hidden" name="prestataire_id" value="<?= $p['id'] ?>"><button type="submit" name="action_rapide" value="valider" class="btn-mini valider">✓ Valider</button></form>
          <form method="post" style="display:inline;"><input type="hidden" name="prestataire_id" value="<?= $p['id'] ?>"><button type="submit" name="action_rapide" value="refuser" class="btn-mini refuser" onclick="return confirm('Refuser ce prestataire ?')">✕ Refuser</button></form>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="panel anim anim-d2">
    <div class="panel-title"><span>🆕 Derniers inscrits</span></div>
    <?php if (empty($derniersInscrits)): ?>
      <div class="empty-mini">Aucune inscription pour le moment</div>
    <?php else: foreach ($derniersInscrits as $u): ?>
      <div class="mini-row">
        <span class="mini-badge <?= $u['role'] ?>"><?= $u['role']==='client'?'Client':'Presta' ?></span>
        <div style="flex:1;"><strong><?= e($u['prenom'].' '.$u['nom']) ?></strong><div style="font-size:11px;color:var(--slate);"><?= e($u['email']) ?></div></div>
        <span style="font-size:11px;color:#94A3B8;"><?= ilYA($u['date_creation']) ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="panel anim anim-d3">
  <div class="panel-title"><span>📋 Dernières réservations</span><a class="panel-link" href="gestion_annonces.php">Voir tout →</a></div>
  <?php if (empty($dernieresReservations)): ?>
    <div class="empty-mini">Aucune réservation pour le moment</div>
  <?php else: foreach ($dernieresReservations as $r): ?>
    <div class="mini-row">
      <div style="flex:1;">
        <strong><?= e($r['client_prenom']) ?></strong> → <?= e($r['presta_prenom'].' '.$r['presta_nom']) ?>
        <div style="font-size:11px;color:var(--slate);"><?= e($r['reference']) ?> • <?= ilYA($r['date_creation']) ?></div>
      </div>
      <span style="font-weight:800;color:var(--primary);"><?= number_format($r['montant'],0,',',' ') ?> F</span>
      <span class="statut-pill <?= $r['statut'] ?>"><?= ucfirst(str_replace('_',' ',$r['statut'])) ?></span>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php if ($nbMessagesNonTraites > 0): ?>
<div class="panel anim anim-d4" style="background:#FFFBEB;border-color:#FDE68A;">
  <span style="font-weight:700;">📬 <?= $nbMessagesNonTraites ?> message<?= $nbMessagesNonTraites>1?'s':'' ?> de contact non traité<?= $nbMessagesNonTraites>1?'s':'' ?></span>
  <a href="gestion_messages.php" class="panel-link" style="margin-left:10px;">Traiter →</a>
</div>
<?php endif; ?>

<script>
new Chart(document.getElementById('chartReservations'), {
  type: 'line',
  data: {
    labels: <?= json_encode($moisLabels) ?>,
    datasets: [{
      label: 'Réservations',
      data: <?= json_encode($moisData) ?>,
      borderColor: '#E85D26',
      backgroundColor: 'rgba(232,93,38,0.1)',
      tension: 0.35,
      fill: true,
      pointBackgroundColor: '#E85D26',
      pointRadius: 4,
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
  }
});

new Chart(document.getElementById('chartCategories'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_column($repartitionCat, 'nom')) ?>,
    datasets: [{
      data: <?= json_encode(array_map('intval', array_column($repartitionCat, 'nb'))) ?>,
      backgroundColor: ['#E85D26','#F59E0B','#2563EB','#059669','#7C3AED','#DC2626','#0891B2','#D97706','#EC4899','#64748B'],
      borderWidth: 2,
      borderColor: '#fff',
    }]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
  }
});
</script>

<?php require 'admin_footer.php'; ?>