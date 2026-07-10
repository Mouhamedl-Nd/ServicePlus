<?php
require 'config/connexion.php';
require 'config/session.php';
require 'includes/fonctions.php';

/* ── Statistiques globales ── */
$nbPrestataires = $pdo->query(
    "SELECT COUNT(*) FROM prestataire_profils WHERE statut_validation = 'valide'"
)->fetchColumn();

$nbClients = $pdo->query(
    "SELECT COUNT(*) FROM utilisateurs WHERE role = 'client'"
)->fetchColumn();

$noteGlobale = $pdo->query(
    "SELECT AVG(note_moyenne) FROM prestataire_profils WHERE statut_validation = 'valide' AND nb_avis > 0"
)->fetchColumn();
$noteGlobale = $noteGlobale ? round($noteGlobale, 1) : 5.0;

/* ── Catégories avec nombre de prestataires validés ── */
$categories = $pdo->query("
    SELECT c.id, c.nom, c.icone, COUNT(pp.user_id) AS nb
    FROM categories c
    LEFT JOIN prestataire_profils pp ON pp.categorie_id = c.id AND pp.statut_validation = 'valide'
    GROUP BY c.id
    ORDER BY c.id
")->fetchAll();

/* ── Top prestataires (les mieux notés) ── */
$topPrestataires = $pdo->query("
    SELECT u.id, u.nom, u.prenom, u.ville, pp.tarif_horaire, pp.note_moyenne, pp.nb_avis, c.nom AS categorie_nom
    FROM prestataire_profils pp
    JOIN utilisateurs u ON u.id = pp.user_id
    JOIN categories c   ON c.id = pp.categorie_id
    WHERE pp.statut_validation = 'valide'
    ORDER BY pp.note_moyenne DESC, pp.nb_avis DESC
    LIMIT 3
")->fetchAll();

/* ── Derniers avis clients ── */
$derniersAvis = $pdo->query("
    SELECT a.note, a.commentaire, a.date_creation, cu.prenom AS client_prenom, cu.nom AS client_nom
    FROM avis a
    JOIN utilisateurs cu ON cu.id = a.client_id
    ORDER BY a.date_creation DESC
    LIMIT 4
")->fetchAll();

function etoiles(float $note): string
{
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span style="color:' . ($i <= round($note) ? '#F59E0B' : '#E2E8F0') . '">★</span>';
    }
    return $html;
}

$page_title  = 'ServicesPlus – Accueil';
$page_active = 'accueil';
$racine      = '';
$extra_css   = <<<CSS
.container { max-width:1180px; margin:0 auto; padding:32px 20px; }
.hero { border-radius:28px; background:linear-gradient(130deg,#1A3560 0%,#E85D26 100%); padding:72px 60px; margin-bottom:44px; position:relative; overflow:hidden; }
.hero::before { content:''; position:absolute; inset:0; background-image: radial-gradient(circle, rgba(255,255,255,0.07) 1px, transparent 1px); background-size: 26px 26px; }
.hero-inner { position:relative; display:grid; grid-template-columns:1fr 400px; gap:60px; align-items:center; }
.hero-badge { display:inline-flex; align-items:center; gap:8px; background:rgba(255,255,255,0.13); border-radius:20px; padding:6px 18px; margin-bottom:22px; }
.hero-badge-dot { width:8px; height:8px; border-radius:50%; background:#4ADE80; }
.hero-badge span { color:rgba(255,255,255,0.9); font-size:11px; font-weight:700; letter-spacing:0.9px; text-transform:uppercase; }
.hero h1 { font-family:'Playfair Display',serif; color:white; font-size:48px; font-weight:900; margin-bottom:18px; line-height:1.1; letter-spacing:-1px; }
.hero h1 em { color:#FCD34D; font-style:normal; }
.hero p { color:rgba(255,255,255,0.78); font-size:16px; margin-bottom:32px; line-height:1.8; }
.search-bar { display:flex; background:white; border-radius:16px; overflow:hidden; box-shadow:0 10px 36px rgba(0,0,0,0.22); max-width:560px; margin-bottom:34px; }
.search-bar input { flex:1; padding:17px 22px; border:none; outline:none; font-size:14px; font-family:'Inter',sans-serif; color:var(--dark); }
.search-bar .sep { width:1px; background:var(--border); margin:10px 0; }
.search-bar .city-input { width:140px; padding:17px 16px; border:none; outline:none; font-size:14px; font-family:'Inter',sans-serif; }
.search-bar button { padding:17px 26px; background:var(--primary); color:white; border:none; cursor:pointer; font-size:14px; font-weight:800; }
.hero-stats { display:flex; gap:36px; }
.hero-stat-value { color:white; font-weight:900; font-size:24px; }
.hero-stat-label { color:rgba(255,255,255,0.6); font-size:12px; margin-top:2px; }
.hero-card { background:rgba(255,255,255,0.12); border-radius:22px; padding:26px; backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,0.2); }
.hero-card-title { color:rgba(255,255,255,0.7); font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.6px; margin-bottom:16px; }
.presta-mini { background:white; border-radius:14px; padding:14px; margin-bottom:10px; display:flex; align-items:center; gap:12px; }
.presta-mini-avatar { width:46px; height:46px; border-radius:13px; display:flex; align-items:center; justify-content:center; font-size:22px; background:#FFE8DF; }
.presta-mini-name { font-weight:700; font-size:14px; }
.presta-mini-sub { color:var(--slate); font-size:12px; }
.presta-mini-stars { color:#F59E0B; font-size:12px; }
.presta-mini-right { margin-left:auto; text-align:right; }
.presta-mini-prix { font-weight:800; color:var(--primary); font-size:13px; }
.presta-mini-dispo { font-size:10px; color:#4ADE80; font-weight:700; }
.btn-hero-cta { width:100%; padding:13px 0; margin-top:6px; background:linear-gradient(135deg,#E85D26,#F59E0B); color:white; border:none; border-radius:13px; font-weight:800; cursor:pointer; font-size:14px; text-decoration:none; display:block; text-align:center; }
.section { margin-bottom:44px; }
.section-header { display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:20px; }
.section-title { font-family:'Playfair Display',serif; font-size:24px; font-weight:900; margin-bottom:4px; }
.section-sub { color:var(--slate); font-size:13px; }
.btn-voir-tout { padding:9px 18px; background:transparent; border:1.5px solid var(--border); border-radius:10px; color:var(--slate); font-weight:600; cursor:pointer; font-size:13px; text-decoration:none; }
.btn-voir-tout:hover { border-color:var(--primary); color:var(--primary); }
.services-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:12px; }
.service-card { background:white; border-radius:18px; padding:20px 12px; text-align:center; cursor:pointer; border:1.5px solid var(--border); transition:all 0.2s; text-decoration:none; color:inherit; display:block; }
.service-card:hover { transform:translateY(-4px); box-shadow:0 8px 24px rgba(232,93,38,0.12); border-color:var(--primary); }
.service-icon-wrap { width:50px; height:50px; border-radius:15px; display:flex; align-items:center; justify-content:center; font-size:22px; margin:0 auto 10px; background:#FFF0EB; }
.service-name { font-weight:700; font-size:12px; margin-bottom:3px; }
.service-count { font-size:11px; font-weight:600; color:var(--primary); }
.presta-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
.presta-card { background:white; border-radius:20px; padding:22px; border:1.5px solid var(--border); box-shadow:0 2px 8px rgba(15,23,42,0.05); position:relative; overflow:hidden; text-decoration:none; color:inherit; display:block; }
.presta-color-bar { position:absolute; top:0; left:0; right:0; height:4px; background:var(--primary); }
.presta-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px; margin-top:6px; }
.presta-avatar { width:56px; height:56px; border-radius:18px; display:flex; align-items:center; justify-content:center; font-size:28px; background:#FFE8DF; }
.presta-dispo { font-size:11px; font-weight:700; padding:5px 12px; border-radius:20px; color:#059669; background:#ECFDF5; }
.presta-name { font-weight:800; font-size:15px; margin-bottom:2px; }
.presta-service { color:var(--slate); font-size:12px; margin-bottom:10px; }
.presta-stars { display:flex; align-items:center; gap:6px; margin-bottom:14px; }
.stars-num { font-weight:700; font-size:14px; }
.stars-avis { font-size:11px; color:#94A3B8; }
.presta-footer { display:flex; justify-content:space-between; align-items:center; padding-top:14px; border-top:1px solid #F1F5F9; }
.presta-prix { font-weight:900; color:var(--primary); font-size:16px; }
.btn-reserver { padding:8px 16px; border:none; border-radius:10px; font-weight:700; cursor:pointer; font-size:12px; background:linear-gradient(135deg,#E85D26,#F59E0B); color:white; }
.how-card { background:white; border-radius:24px; padding:44px; border:1.5px solid var(--border); box-shadow:0 2px 8px rgba(15,23,42,0.05); margin-bottom:44px; }
.how-card h2 { font-family:'Playfair Display',serif; font-size:26px; font-weight:900; text-align:center; margin-bottom:36px; }
.steps-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:28px; position:relative; }
.step { text-align:center; position:relative; }
.step-icon-wrap { position:relative; display:inline-block; margin-bottom:16px; }
.step-icon-bg { width:60px; height:60px; border-radius:20px; background:linear-gradient(135deg,#FFF0EB,#FEF3C7); display:flex; align-items:center; justify-content:center; font-size:26px; margin:0 auto; }
.step-num { position:absolute; top:-8px; right:-8px; width:24px; height:24px; border-radius:50%; background:linear-gradient(135deg,#E85D26,#F59E0B); color:white; font-size:11px; font-weight:900; display:flex; align-items:center; justify-content:center; }
.step h3 { font-weight:800; font-size:15px; margin-bottom:8px; }
.step p { color:var(--slate); font-size:13px; line-height:1.65; }
.avis-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:14px; }
.avis-card { background:white; border-radius:18px; padding:20px; border:1.5px solid var(--border); box-shadow:0 2px 8px rgba(15,23,42,0.05); }
.avis-header { display:flex; gap:10px; margin-bottom:12px; }
.avis-avatar { width:42px; height:42px; border-radius:50%; background:linear-gradient(135deg,#E85D26,#F59E0B); color:white; display:flex; align-items:center; justify-content:center; font-weight:900; font-size:16px; flex-shrink:0; }
.avis-name { font-weight:700; font-size:13px; }
.avis-meta { font-size:11px; color:#94A3B8; margin-top:2px; }
.avis-text { color:#475569; font-size:13px; line-height:1.65; font-style:italic; }
.cta-banner { border-radius:24px; background:linear-gradient(130deg,#1A3560,#E85D26); padding:44px 52px; display:flex; justify-content:space-between; align-items:center; margin-bottom:44px; }
.cta-banner h2 { font-family:'Playfair Display',serif; color:white; font-size:24px; font-weight:900; margin-bottom:8px; }
.cta-banner p { color:rgba(255,255,255,0.75); font-size:14px; line-height:1.7; }
.cta-btns { display:flex; gap:12px; flex-shrink:0; }
.btn-cta-white { padding:13px 26px; background:white; color:var(--primary); border:none; border-radius:12px; font-weight:800; cursor:pointer; font-size:14px; text-decoration:none; }
.btn-cta-ghost { padding:13px 26px; background:transparent; color:white; border:2px solid rgba(255,255,255,0.4); border-radius:12px; font-weight:700; cursor:pointer; font-size:14px; }
.empty-state { grid-column:1/-1; text-align:center; padding:30px; color:var(--slate); font-size:13px; background:white; border-radius:16px; border:1.5px dashed var(--border); }
CSS;

require 'includes/header.php';
?>

<div class="container">

  <!-- ═══ HERO ═══ -->
  <div class="hero">
    <div class="hero-inner">
      <div>
        <div class="hero-badge">
          <div class="hero-badge-dot"></div>
          <span>Plateforme N°1 au Sénégal</span>
        </div>
        <h1>Le bon prestataire,<br/><em>au bon moment.</em></h1>
        <p>Trouvez des professionnels qualifiés pour tous vos besoins à domicile.<br/>Réservez en ligne, payez en toute sécurité.</p>

        <form class="search-bar" action="recherche.php" method="get">
          <input type="text" name="q" placeholder="Quel service cherchez-vous ?"/>
          <div class="sep"></div>
          <input type="text" name="ville" class="city-input" placeholder="Ville..."/>
          <button type="submit">🔍 Chercher</button>
        </form>

        <div class="hero-stats">
          <div><div class="hero-stat-value"><?= number_format($nbPrestataires, 0, ',', ' ') ?>+</div><div class="hero-stat-label">Prestataires</div></div>
          <div><div class="hero-stat-value"><?= number_format($nbClients, 0, ',', ' ') ?>+</div><div class="hero-stat-label">Clients</div></div>
          <div><div class="hero-stat-value"><?= e((string)$noteGlobale) ?> ★</div><div class="hero-stat-label">Note moyenne</div></div>
        </div>
      </div>

      <div class="hero-card">
        <div class="hero-card-title">Prestataires disponibles près de vous</div>
        <?php if (empty($topPrestataires)): ?>
          <p style="color:rgba(255,255,255,0.7);font-size:13px;">Aucun prestataire validé pour le moment.</p>
        <?php else: foreach (array_slice($topPrestataires, 0, 2) as $p): ?>
          <div class="presta-mini">
            <div class="presta-mini-avatar">🧑‍🔧</div>
            <div>
              <div class="presta-mini-name"><?= e($p['prenom'] . ' ' . $p['nom']) ?></div>
              <div class="presta-mini-sub"><?= e($p['categorie_nom']) ?> • <?= e($p['ville'] ?? 'Dakar') ?></div>
              <div class="presta-mini-stars"><?= etoiles($p['note_moyenne']) ?> <span style="color:#64748B;font-size:11px;"><?= e((string)$p['note_moyenne']) ?></span></div>
            </div>
            <div class="presta-mini-right">
              <div class="presta-mini-prix"><?= number_format($p['tarif_horaire'], 0, ',', ' ') ?> FCFA/h</div>
              <div class="presta-mini-dispo">● Disponible</div>
            </div>
          </div>
        <?php endforeach; endif; ?>
        <a class="btn-hero-cta" href="recherche.php">Voir tous les prestataires →</a>
      </div>
    </div>
  </div>

  <!-- ═══ SERVICES ═══ -->
  <div class="section">
    <div class="section-header">
      <div>
        <div class="section-title">Nos services 🛠️</div>
        <div class="section-sub"><?= count($categories) ?> catégories, des centaines de professionnels</div>
      </div>
      <a class="btn-voir-tout" href="recherche.php">Tout voir →</a>
    </div>
    <div class="services-grid">
      <?php foreach ($categories as $cat): ?>
        <a class="service-card" href="recherche.php?categorie[]=<?= (int)$cat['id'] ?>">
          <div class="service-icon-wrap"><?= $cat['icone'] ?></div>
          <div class="service-name"><?= e($cat['nom']) ?></div>
          <div class="service-count"><?= (int)$cat['nb'] ?> presta.</div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ═══ TOP PRESTATAIRES ═══ -->
  <div class="section">
    <div class="section-header">
      <div>
        <div class="section-title">Top prestataires ⭐</div>
        <div class="section-sub">Les mieux notés de la plateforme</div>
      </div>
      <a class="btn-voir-tout" href="recherche.php">Voir plus →</a>
    </div>
    <div class="presta-grid">
      <?php if (empty($topPrestataires)): ?>
        <div class="empty-state">Aucun prestataire validé pour le moment. Revenez bientôt !</div>
      <?php else: foreach ($topPrestataires as $p): ?>
        <a class="presta-card" href="profil_prestataire.php?id=<?= (int)$p['id'] ?>">
          <div class="presta-color-bar"></div>
          <div class="presta-header">
            <div class="presta-avatar">🧑‍🔧</div>
            <span class="presta-dispo">● Disponible</span>
          </div>
          <div class="presta-name"><?= e($p['prenom'] . ' ' . $p['nom']) ?></div>
          <div class="presta-service"><?= e($p['categorie_nom']) ?> • <?= (int)$p['nb_avis'] ?> avis</div>
          <div class="presta-stars">
            <span class="stars"><?= etoiles($p['note_moyenne']) ?></span>
            <span class="stars-num"><?= e((string)$p['note_moyenne']) ?></span>
            <span class="stars-avis">(<?= (int)$p['nb_avis'] ?> avis)</span>
          </div>
          <div class="presta-footer">
            <span class="presta-prix"><?= number_format($p['tarif_horaire'], 0, ',', ' ') ?> FCFA/h</span>
            <span class="btn-reserver">Réserver</span>
          </div>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- ═══ COMMENT ÇA MARCHE ═══ -->
  <div class="how-card">
    <h2>Comment ça marche ? 🤔</h2>
    <div class="steps-grid">
      <div class="step"><div class="step-icon-wrap"><div class="step-icon-bg">🔍</div><div class="step-num">1</div></div><h3>Cherchez</h3><p>Sélectionnez votre service et votre ville pour trouver les prestataires disponibles.</p></div>
      <div class="step"><div class="step-icon-wrap"><div class="step-icon-bg">👤</div><div class="step-num">2</div></div><h3>Comparez</h3><p>Consultez les profils, notes et tarifs pour faire le meilleur choix.</p></div>
      <div class="step"><div class="step-icon-wrap"><div class="step-icon-bg">📅</div><div class="step-num">3</div></div><h3>Réservez</h3><p>Choisissez la date et confirmez votre réservation en ligne en quelques clics.</p></div>
      <div class="step"><div class="step-icon-wrap"><div class="step-icon-bg">⭐</div><div class="step-num">4</div></div><h3>Évaluez</h3><p>Après l'intervention, notez votre prestataire pour aider la communauté.</p></div>
    </div>
  </div>

  <!-- ═══ AVIS ═══ -->
  <div class="section">
    <div class="section-title" style="margin-bottom:20px;">Ce que disent nos clients 💬</div>
    <div class="avis-grid">
      <?php if (empty($derniersAvis)): ?>
        <div class="empty-state">Pas encore d'avis publiés. Soyez le premier à réserver !</div>
      <?php else: foreach ($derniersAvis as $a): ?>
        <div class="avis-card">
          <div class="avis-header">
            <div class="avis-avatar"><?= strtoupper(substr($a['client_prenom'], 0, 1)) ?></div>
            <div>
              <div class="avis-name"><?= e($a['client_prenom']) ?></div>
              <div class="avis-meta"><?= etoiles($a['note']) ?> — <?= ilYA($a['date_creation']) ?></div>
            </div>
          </div>
          <p class="avis-text">"<?= e($a['commentaire']) ?>"</p>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- ═══ CTA ═══ -->
  <div class="cta-banner">
    <div>
      <h2>Vous êtes prestataire ? 🔧</h2>
      <p>Rejoignez ServicesPlus et développez votre activité.<br/>Plus de <?= number_format($nbPrestataires, 0, ',', ' ') ?> prestataires nous font déjà confiance.</p>
    </div>
    <div class="cta-btns">
      <a class="btn-cta-white" href="inscription.php">Créer mon profil gratuitement</a>
      <a class="btn-cta-ghost" href="faq.php">En savoir plus</a>
    </div>
  </div>

</div>

<?php require 'includes/footer.php'; ?>