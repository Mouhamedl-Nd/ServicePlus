<?php
require 'config/connexion.php';
require 'config/session.php';
require 'includes/fonctions.php';

$id = (int)($_GET['id'] ?? 0);
$utilisateur = utilisateurConnecte();

/* ── Ajout/retrait des favoris (client connecté uniquement) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_favori']) && $utilisateur && $utilisateur['role'] === 'client') {
    $stmtF = $pdo->prepare('SELECT id FROM favoris WHERE client_id=? AND prestataire_id=?');
    $stmtF->execute([$utilisateur['id'], $id]);
    if ($stmtF->fetch()) {
        $pdo->prepare('DELETE FROM favoris WHERE client_id=? AND prestataire_id=?')->execute([$utilisateur['id'], $id]);
    } else {
        $pdo->prepare('INSERT INTO favoris (client_id, prestataire_id) VALUES (?,?)')->execute([$utilisateur['id'], $id]);
    }
    header('Location: profil_prestataire.php?id=' . $id);
    exit;
}

$estFavori = false;
if ($utilisateur && $utilisateur['role'] === 'client') {
    $stmtF = $pdo->prepare('SELECT id FROM favoris WHERE client_id=? AND prestataire_id=?');
    $stmtF->execute([$utilisateur['id'], $id]);
    $estFavori = (bool)$stmtF->fetch();
}

$stmt = $pdo->prepare("
    SELECT u.id, u.nom, u.prenom, u.ville, u.telephone, u.date_creation, u.photo,
           pp.tarif_horaire, pp.note_moyenne, pp.nb_avis, pp.experience, pp.bio,
           c.nom AS categorie_nom, c.icone
    FROM prestataire_profils pp
    JOIN utilisateurs u ON u.id = pp.user_id
    JOIN categories c ON c.id = pp.categorie_id
    WHERE u.id = ? AND pp.statut_validation = 'valide'
");
$stmt->execute([$id]);
$presta = $stmt->fetch();

$page_title  = $presta ? 'ServicesPlus – ' . $presta['prenom'] . ' ' . $presta['nom'] : 'ServicesPlus – Profil introuvable';
$extra_css   = <<<CSS
.breadcrumb { display:flex; align-items:center; gap:8px; font-size:13px; color:var(--slate); margin:22px auto 0; max-width:1100px; padding:0 20px; }
.breadcrumb a { color:var(--slate); text-decoration:none; }
.breadcrumb a:hover { color:var(--primary); }
.breadcrumb span { color:#CBD5E1; }
.container { max-width:1100px; margin:0 auto; padding:20px; }
.layout { display:grid; grid-template-columns:1fr 300px; gap:22px; align-items:start; }
.profil-header { background:white; border-radius:22px; border:1.5px solid var(--border); box-shadow:0 2px 8px rgba(15,23,42,0.05); overflow:hidden; margin-bottom:20px; }
.profil-cover { height:130px; position:relative; background:linear-gradient(135deg,#2563EB,#1A3560); }
.profil-cover::before { content:''; position:absolute; inset:0; background-image:radial-gradient(circle,rgba(255,255,255,0.08) 1px,transparent 1px); background-size:20px 20px; }
.profil-cover-actions { position:absolute; top:14px; right:14px; display:flex; gap:8px; }
.cover-btn { padding:7px 14px; border-radius:10px; border:none; cursor:pointer; font-size:12px; font-weight:700; font-family:'Inter',sans-serif; background:rgba(255,255,255,0.2); color:white; backdrop-filter:blur(8px); text-decoration:none; }
.cover-btn:hover { background:rgba(255,255,255,0.35); }
.cover-btn.primary { background:white; color:var(--primary); }
.profil-body { padding:24px 28px 26px; }
.profil-avatar { width:88px; height:88px; border-radius:24px; border:4px solid white; display:flex; align-items:center; justify-content:center; font-size:42px; flex-shrink:0; box-shadow:0 4px 16px rgba(0,0,0,0.12); background:#DBEAFE; }
.profil-identity { display:flex; align-items:center; gap:20px; margin-bottom:20px; flex-wrap:wrap; }
.profil-identity-info { flex:1; padding-bottom:4px; min-width:200px; }
.profil-name { font-family:'Playfair Display',serif; font-size:24px; font-weight:900; margin-bottom:4px; }
.profil-subtitle { color:var(--slate); font-size:14px; margin-bottom:8px; }
.profil-badges { display:flex; gap:7px; flex-wrap:wrap; }
.badge { font-size:11px; font-weight:700; padding:4px 12px; border-radius:20px; }
.badge-green { color:#059669; background:#ECFDF5; }
.badge-blue { color:#2563EB; background:#EFF6FF; }
.profil-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; padding-top:20px; border-top:1px solid #F1F5F9; }
.stat-box { text-align:center; padding:14px 8px; background:#FAFBFC; border-radius:14px; }
.stat-icon { font-size:22px; margin-bottom:6px; }
.stat-value { font-weight:900; font-size:20px; margin-bottom:2px; }
.stat-label { font-size:11px; color:#94A3B8; }
.tabs { display:flex; gap:0; background:white; border-radius:14px; padding:6px; border:1.5px solid var(--border); box-shadow:0 2px 6px rgba(15,23,42,0.04); margin-bottom:18px; flex-wrap:wrap; }
.tab { flex:1; min-width:100px; padding:10px 0; border-radius:10px; border:none; cursor:pointer; font-size:13px; font-weight:700; font-family:'Inter',sans-serif; background:transparent; color:var(--slate); }
.tab.active { background:linear-gradient(135deg,#E85D26,#F59E0B); color:white; }
.tab-content { display:none; }
.tab-content.active { display:block; }
.card { background:white; border-radius:18px; padding:24px; border:1.5px solid var(--border); box-shadow:0 2px 6px rgba(15,23,42,0.04); margin-bottom:16px; }
.card-title { font-weight:800; font-size:16px; margin-bottom:16px; }
.about-text { color:#475569; line-height:1.8; font-size:14px; margin-bottom:20px; white-space:pre-line; }
.zone-box { background:#F8FAFC; border-radius:12px; padding:14px 18px; font-size:13px; color:var(--slate); margin-top:4px; }
.rating-summary { display:flex; gap:24px; align-items:center; padding:20px; background:#FAFBFC; border-radius:14px; margin-bottom:20px; flex-wrap:wrap; }
.rating-big { text-align:center; }
.rating-num { font-size:52px; font-weight:900; color:var(--dark); line-height:1; }
.rating-stars { color:#F59E0B; font-size:22px; margin:4px 0; }
.rating-total { font-size:12px; color:#94A3B8; }
.rating-bars { flex:1; min-width:200px; }
.rating-bar-row { display:flex; align-items:center; gap:10px; margin-bottom:7px; }
.rating-bar-label { font-size:12px; color:var(--slate); width:12px; }
.rating-bar-track { flex:1; height:7px; background:#E9EEF4; border-radius:20px; overflow:hidden; }
.rating-bar-fill { height:100%; border-radius:20px; background:linear-gradient(135deg,#E85D26,#F59E0B); }
.rating-bar-pct { font-size:12px; color:var(--slate); width:32px; text-align:right; }
.avis-item { padding:18px 0; border-bottom:1px solid #F8FAFC; }
.avis-item:last-child { border-bottom:none; }
.avis-header { display:flex; gap:12px; margin-bottom:10px; align-items:center; }
.avis-avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,#E85D26,#F59E0B); color:white; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:16px; flex-shrink:0; }
.avis-name { font-weight:700; font-size:14px; }
.avis-date { font-size:11px; color:#94A3B8; }
.avis-text { color:#475569; font-size:13px; line-height:1.7; font-style:italic; }
.sidebar { display:flex; flex-direction:column; gap:16px; position:sticky; top:86px; }
.sidebar-card { background:white; border-radius:18px; padding:22px; border:1.5px solid var(--border); box-shadow:0 2px 6px rgba(15,23,42,0.04); }
.sidebar-card-title { font-weight:800; font-size:15px; margin-bottom:16px; }
.prix-big { font-weight:900; color:var(--primary); font-size:28px; margin-bottom:4px; }
.prix-sub { color:var(--slate); font-size:12px; margin-bottom:18px; }
.btn-full { width:100%; padding:14px 0; border:none; border-radius:13px; font-weight:800; font-size:15px; cursor:pointer; margin-bottom:10px; font-family:'Inter',sans-serif; text-decoration:none; display:block; text-align:center; box-sizing:border-box; }
.btn-full.primary { background:linear-gradient(135deg,#E85D26,#F59E0B); color:white; }
.btn-full.secondary { background:#F8FAFC; color:var(--slate); border:1.5px solid var(--border); }
.info-row { display:flex; gap:12px; margin-bottom:13px; align-items:flex-start; }
.info-icon { font-size:18px; flex-shrink:0; margin-top:1px; }
.info-label { font-size:11px; color:#94A3B8; margin-bottom:2px; }
.info-value { font-size:13px; font-weight:600; }
.presta-similaire { display:flex; gap:12px; align-items:center; padding:12px 0; border-bottom:1px solid #F8FAFC; text-decoration:none; color:inherit; }
.presta-similaire:last-child { border-bottom:none; }
.sim-avatar { width:42px; height:42px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:20px; background:#EFF6FF; }
.sim-name { font-weight:700; font-size:13px; }
.sim-service { font-size:11px; color:var(--slate); }
.sim-note { font-size:11px; color:#F59E0B; margin-left:auto; font-weight:700; }
.empty { text-align:center; padding:60px 20px; background:white; border-radius:18px; border:1.5px solid var(--border); }
@media(max-width:800px){
  .layout{grid-template-columns:1fr}
  .profil-stats{grid-template-columns:repeat(2,1fr)}
}
CSS;

require 'includes/header.php';
?>

<div class="breadcrumb">
  <a href="index.php">Accueil</a><span>›</span>
  <a href="recherche.php">Recherche</a><span>›</span>
  <span style="color:var(--dark);font-weight:600;"><?= $presta ? e($presta['prenom'].' '.$presta['nom']) : 'Profil' ?></span>
</div>

<div class="container">
<?php if (!$presta): ?>
  <div class="empty">
    <div style="font-size:48px;margin-bottom:16px;">🔍</div>
    <h3 style="font-weight:800;font-size:18px;margin-bottom:8px;">Ce prestataire n'existe pas ou n'est pas encore validé</h3>
    <p style="color:var(--slate);font-size:14px;margin-bottom:20px;">Il a peut-être été retiré de la plateforme ou son dossier est en cours de validation.</p>
    <a href="recherche.php" style="color:var(--primary);font-weight:700;text-decoration:none;">← Retour à la recherche</a>
  </div>
<?php else:
  $stmtMissions = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE prestataire_id = ? AND statut = 'terminee'");
  $stmtMissions->execute([$presta['id']]);
  $missions = (int)$stmtMissions->fetchColumn();

  $stmtAvis = $pdo->prepare("
      SELECT a.note, a.commentaire, a.date_creation, cu.prenom, cu.nom
      FROM avis a JOIN utilisateurs cu ON cu.id = a.client_id
      WHERE a.prestataire_id = ? ORDER BY a.date_creation DESC LIMIT 10
  ");
  $stmtAvis->execute([$presta['id']]);
  $avisListe = $stmtAvis->fetchAll();

  $distribution = $pdo->prepare("SELECT note, COUNT(*) AS nb FROM avis WHERE prestataire_id = ? GROUP BY note");
  $distribution->execute([$presta['id']]);
  $repartition = array_fill(1, 5, 0);
  foreach ($distribution->fetchAll() as $row) { $repartition[(int)$row['note']] = (int)$row['nb']; }

  $similaires = $pdo->prepare("
      SELECT u.id, u.nom, u.prenom, pp.note_moyenne
      FROM prestataire_profils pp JOIN utilisateurs u ON u.id = pp.user_id
      WHERE pp.categorie_id = (SELECT categorie_id FROM prestataire_profils WHERE user_id = ?)
        AND pp.statut_validation = 'valide' AND u.id != ?
      ORDER BY pp.note_moyenne DESC LIMIT 3
  ");
  $similaires->execute([$presta['id'], $presta['id']]);
  $similairesListe = $similaires->fetchAll();

  $anneesPlateforme = max(1, (int)date('Y') - (int)date('Y', strtotime($presta['date_creation'])));
  ?>

  <div class="profil-header">
    <div class="profil-cover">
      <div class="profil-cover-actions">
        <?php if ($utilisateur && $utilisateur['role'] === 'client'): ?>
          <form method="post" style="display:inline;"><input type="hidden" name="toggle_favori" value="1">
            <button type="submit" class="cover-btn" style="border:none;cursor:pointer;"><?= $estFavori ? '❤️ Favori' : '🤍 Favori' ?></button>
          </form>
        <?php else: ?>
          <button class="cover-btn" onclick="alert('Connectez-vous en tant que client pour ajouter aux favoris.')">🤍 Favori</button>
        <?php endif; ?>
        <button class="cover-btn" onclick="navigator.clipboard.writeText(location.href);alert('Lien copié !')">📤 Partager</button>
        <a class="cover-btn primary" href="espace_client/reservation.php?id=<?= $presta['id'] ?>">📅 Réserver</a>
      </div>
    </div>
    <div class="profil-body">
      <div class="profil-identity">
        <div class="profil-avatar"><?= $presta['photo'] ? '<img src="'.e($presta['photo']).'" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:20px;">' : $presta['icone'] ?></div>
        <div class="profil-identity-info">
          <div class="profil-name"><?= e($presta['prenom'].' '.$presta['nom']) ?></div>
          <div class="profil-subtitle"><?= e($presta['categorie_nom']) ?> professionnel • <?= e($presta['ville'] ?? 'Dakar') ?>, Sénégal</div>
          <div class="profil-badges">
            <span class="badge badge-green">● Disponible</span>
            <span class="badge badge-green">✓ Identité vérifiée</span>
            <span class="badge badge-blue">✓ Validé par l'admin</span>
          </div>
        </div>
      </div>
      <div class="profil-stats">
        <div class="stat-box"><div class="stat-icon">⭐</div><div class="stat-value" style="color:#F59E0B;"><?= $presta['nb_avis'] > 0 ? e((string)$presta['note_moyenne']) : 'Nouveau' ?></div><div class="stat-label">Note globale</div></div>
        <div class="stat-box"><div class="stat-icon">💬</div><div class="stat-value"><?= (int)$presta['nb_avis'] ?></div><div class="stat-label">Avis clients</div></div>
        <div class="stat-box"><div class="stat-icon">🏆</div><div class="stat-value"><?= $missions ?></div><div class="stat-label">Missions</div></div>
        <div class="stat-box"><div class="stat-icon">📅</div><div class="stat-value"><?= $anneesPlateforme ?> an<?= $anneesPlateforme>1?'s':'' ?></div><div class="stat-label">Sur la plateforme</div></div>
      </div>
    </div>
  </div>

  <div class="layout">
    <div>
      <div class="tabs">
        <button class="tab active" onclick="showTab('apropos',this)">📄 À propos</button>
        <button class="tab" onclick="showTab('avis',this)">⭐ Avis (<?= (int)$presta['nb_avis'] ?>)</button>
      </div>

      <div id="tab-apropos" class="tab-content active">
        <div class="card">
          <div class="card-title">👋 À propos de <?= e($presta['prenom']) ?></div>
          <p class="about-text"><?= $presta['bio'] ? nl2br(e($presta['bio'])) : 'Ce prestataire n\'a pas encore ajouté de description.' ?></p>
          <div class="card-title" style="margin-top:20px;">🔧 Expérience</div>
          <div class="zone-box"><?= e($presta['experience']) ?> d'expérience dans le secteur <?= e($presta['categorie_nom']) ?>.</div>
        </div>
      </div>

      <div id="tab-avis" class="tab-content">
        <div class="card">
          <div class="rating-summary">
            <div class="rating-big">
              <div class="rating-num"><?= $presta['nb_avis'] > 0 ? e((string)$presta['note_moyenne']) : '—' ?></div>
              <div class="rating-stars"><?= $presta['nb_avis'] > 0 ? (str_repeat('★', (int)round($presta['note_moyenne'])) . str_repeat('☆', 5 - (int)round($presta['note_moyenne']))) : '☆☆☆☆☆' ?></div>
              <div class="rating-total"><?= (int)$presta['nb_avis'] ?> avis</div>
            </div>
            <div class="rating-bars">
              <?php for ($n = 5; $n >= 1; $n--): $pct = $presta['nb_avis'] > 0 ? round($repartition[$n] / $presta['nb_avis'] * 100) : 0; ?>
                <div class="rating-bar-row"><span class="rating-bar-label"><?= $n ?></span><div class="rating-bar-track"><div class="rating-bar-fill" style="width:<?= $pct ?>%"></div></div><span class="rating-bar-pct"><?= $pct ?>%</span></div>
              <?php endfor; ?>
            </div>
          </div>

          <?php if (empty($avisListe)): ?>
            <p style="color:var(--slate);font-size:14px;text-align:center;padding:20px 0;">Pas encore d'avis pour ce prestataire.</p>
          <?php else: foreach ($avisListe as $a): ?>
            <div class="avis-item">
              <div class="avis-header">
                <div class="avis-avatar"><?= strtoupper(substr($a['prenom'],0,1)) ?></div>
                <div style="flex:1;">
                  <div class="avis-name"><?= e($a['prenom'].' '.substr($a['nom'],0,1).'.') ?></div>
                  <div class="avis-date"><?= str_repeat('★',$a['note']) ?> — <?= ilYA($a['date_creation']) ?></div>
                </div>
              </div>
              <p class="avis-text">"<?= e($a['commentaire']) ?>"</p>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <aside class="sidebar">
      <div class="sidebar-card">
        <div class="sidebar-card-title">💰 Tarifs</div>
        <div class="prix-big"><?= number_format($presta['tarif_horaire'],0,',',' ') ?> FCFA<span style="font-size:16px;font-weight:600;color:var(--slate);">/heure</span></div>
        <div class="prix-sub">Paiement après intervention</div>
        <a class="btn-full primary" href="espace_client/reservation.php?id=<?= $presta['id'] ?>">📅 Réserver maintenant</a>
        <a class="btn-full secondary" href="contact.php">💬 Contacter le support</a>
      </div>

      <div class="sidebar-card">
        <div class="sidebar-card-title">ℹ️ Informations</div>
        <div class="info-row"><span class="info-icon">📍</span><div><div class="info-label">Localisation</div><div class="info-value"><?= e($presta['ville'] ?? 'Dakar') ?></div></div></div>
        <div class="info-row"><span class="info-icon">🗓️</span><div><div class="info-label">Membre depuis</div><div class="info-value"><?= date('F Y', strtotime($presta['date_creation'])) ?></div></div></div>
        <div class="info-row" style="margin-bottom:0;"><span class="info-icon">💳</span><div><div class="info-label">Paiements acceptés</div><div class="info-value">Orange Money, Wave, Espèces</div></div></div>
      </div>

      <?php if ($similairesListe): ?>
      <div class="sidebar-card">
        <div class="sidebar-card-title">👥 Prestataires similaires</div>
        <?php foreach ($similairesListe as $s): ?>
          <a class="presta-similaire" href="profil_prestataire.php?id=<?= $s['id'] ?>">
            <div class="sim-avatar">🧑‍🔧</div>
            <div><div class="sim-name"><?= e($s['prenom'].' '.$s['nom']) ?></div><div class="sim-service"><?= e($presta['categorie_nom']) ?></div></div>
            <span class="sim-note">★ <?= e((string)$s['note_moyenne']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </aside>
  </div>
<?php endif; ?>
</div>

<script>
  function showTab(id, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    btn.classList.add('active');
  }
</script>

<?php require 'includes/footer.php'; ?>