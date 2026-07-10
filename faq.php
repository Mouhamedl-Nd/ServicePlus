<?php
require 'config/connexion.php';
require 'config/session.php';
require 'includes/fonctions.php';

if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['faq_votes'])) {
    $_SESSION['faq_votes'] = [];
}

/* ── Vote (utile / pas utile) ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vote_faq'])) {
    $faqId = (int)$_POST['vote_faq'];
    $type  = $_POST['type'] === 'yes' ? 'yes' : 'no';

    if (!in_array($faqId, $_SESSION['faq_votes'], true)) {
        $colonne = $type === 'yes' ? 'votes_utile' : 'votes_inutile';
        $pdo->prepare("UPDATE faq SET $colonne = $colonne + 1 WHERE id = ?")->execute([$faqId]);
        $_SESSION['faq_votes'][] = $faqId;
    }
    header('Location: faq.php' . (isset($_GET['cat']) ? '?cat=' . urlencode($_GET['cat']) : '') . '#faq-' . $faqId);
    exit;
}

$catMeta = [
    'tous'         => ['ic' => '🔔', 'lbl' => 'Toutes les questions'],
    'compte'       => ['ic' => '👤', 'lbl' => 'Compte & Inscription'],
    'recherche'    => ['ic' => '🔍', 'lbl' => 'Recherche & Prestataires'],
    'reservation'  => ['ic' => '📅', 'lbl' => 'Réservation'],
    'paiement'     => ['ic' => '💳', 'lbl' => 'Paiement'],
    'prestataire'  => ['ic' => '🔧', 'lbl' => 'Espace prestataire'],
    'securite'     => ['ic' => '🛡️', 'lbl' => 'Sécurité'],
    'technique'    => ['ic' => '⚙️', 'lbl' => 'Problèmes techniques'],
    'autre'        => ['ic' => '💬', 'lbl' => 'Autres questions'],
];

$catActive = $_GET['cat'] ?? 'tous';
if (!isset($catMeta[$catActive])) { $catActive = 'tous'; }
$q   = trim($_GET['q'] ?? '');
$tri = $_GET['tri'] ?? 'default';

/* ── Compteurs par catégorie ── */
$comptes = $pdo->query('SELECT categorie, COUNT(*) AS nb FROM faq GROUP BY categorie')
               ->fetchAll(PDO::FETCH_KEY_PAIR);
$totalFaq = array_sum($comptes);

/* ── Questions populaires (affichées seulement en vue "Toutes" sans recherche) ── */
$populaires = $pdo->query('SELECT * FROM faq WHERE populaire = 1 ORDER BY votes_utile DESC LIMIT 6')->fetchAll();

/* ── Liste principale ── */
$where = [];
$params = [];
if ($q !== '') {
    $where[] = '(question LIKE ? OR reponse LIKE ? OR tags LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
} elseif ($catActive !== 'tous') {
    $where[] = 'categorie = ?';
    $params[] = $catActive;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$ordreSql = match ($tri) {
    'popular' => 'populaire DESC, votes_utile DESC',
    'recent'  => 'id DESC',
    default   => 'id ASC',
};

$stmt = $pdo->prepare("SELECT * FROM faq $whereSql ORDER BY $ordreSql");
$stmt->execute($params);
$questions = $stmt->fetchAll();

$page_title  = 'ServicesPlus – FAQ';
$page_active = 'faq';
$extra_css   = <<<CSS
.hero{background:linear-gradient(130deg,#1A3560,#2563EB,#E85D26);padding:64px 40px;text-align:center;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.06) 1px,transparent 1px);background-size:26px 26px}
.hero-inner{position:relative;max-width:700px;margin:0 auto}
.hero-badge{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,0.13);border-radius:20px;padding:6px 18px;margin-bottom:20px}
.hero-badge span{color:rgba(255,255,255,0.9);font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase}
.hero h1{font-family:'Playfair Display',serif;color:white;font-size:42px;font-weight:900;margin-bottom:14px;line-height:1.15}
.hero h1 em{color:#FCD34D;font-style:normal}
.hero p{color:rgba(255,255,255,0.78);font-size:15px;margin-bottom:32px;line-height:1.75}
.search-bar{display:flex;background:white;border-radius:14px;overflow:hidden;box-shadow:0 8px 28px rgba(0,0,0,0.2);max-width:560px;margin:0 auto}
.search-bar input{flex:1;padding:16px 20px;border:none;outline:none;font-size:14px;font-family:'Inter',sans-serif;color:var(--dark)}
.search-bar button{padding:16px 26px;background:var(--primary);color:white;border:none;cursor:pointer;font-size:14px;font-weight:800}
.stats-bar{background:white;border-bottom:1px solid var(--border);padding:20px 40px}
.stats-inner{max-width:1100px;margin:0 auto;display:flex;justify-content:center;gap:48px;flex-wrap:wrap}
.stat-item{text-align:center}
.stat-val{font-size:22px;font-weight:900;color:var(--primary);margin-bottom:3px}
.stat-lbl{font-size:12px;color:var(--slate)}
.container{max-width:1100px;margin:0 auto;padding:40px 20px}
.layout{display:grid;grid-template-columns:260px 1fr;gap:28px;align-items:start}
.sidebar{background:white;border-radius:18px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);overflow:hidden;position:sticky;top:86px}
.sb-title{padding:18px 20px;font-weight:800;font-size:15px;border-bottom:1px solid var(--border)}
.cat-item{display:flex;align-items:center;gap:10px;padding:13px 20px;cursor:pointer;font-size:13px;font-weight:600;color:var(--slate);text-decoration:none;border-bottom:1px solid #F8FAFC}
.cat-item:last-child{border-bottom:none}
.cat-item:hover,.cat-item.active{background:var(--pl);color:var(--primary)}
.cat-item .ic{font-size:18px;width:24px;text-align:center}
.cat-item .cnt{margin-left:auto;background:#F1F5F9;color:var(--slate);font-size:11px;font-weight:700;padding:2px 8px;border-radius:20px}
.cat-item.active .cnt{background:var(--primary);color:white}
.faq-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:10px}
.faq-count{font-size:14px;color:var(--slate)}
.faq-sort{padding:8px 14px;border-radius:9px;border:1.5px solid var(--border);font-size:13px;font-family:'Inter',sans-serif;outline:none;background:white;cursor:pointer}
.faq-item{background:white;border-radius:16px;border:1.5px solid var(--border);box-shadow:0 2px 6px rgba(15,23,42,0.04);margin-bottom:10px;overflow:hidden;transition:all .2s;scroll-margin-top:100px}
.faq-item.open{border-color:var(--primary);box-shadow:0 4px 16px rgba(232,93,38,0.1)}
.faq-q{display:flex;justify-content:space-between;align-items:center;padding:18px 22px;cursor:pointer;gap:14px}
.faq-q-text{font-weight:700;font-size:15px;line-height:1.4;flex:1}
.faq-icon{font-size:20px;color:var(--primary);flex-shrink:0;font-weight:900;transition:transform .25s;width:24px;text-align:center}
.faq-item.open .faq-icon{transform:rotate(45deg)}
.faq-a{display:none;padding:0 22px 20px}
.faq-item.open .faq-a{display:block}
.faq-a-text{color:#475569;font-size:14px;line-height:1.8;margin-bottom:12px}
.faq-a-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.faq-tag{font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;background:#F1F5F9;color:var(--slate)}
.faq-helpful{display:flex;align-items:center;gap:8px;margin-left:auto;font-size:12px;color:var(--slate)}
.faq-helpful button{padding:5px 12px;border-radius:8px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:12px;font-weight:700;font-family:'Inter',sans-serif}
.faq-helpful button:hover{border-color:var(--primary);color:var(--primary)}
.faq-helpful button.voted{border-color:var(--green);background:#ECFDF5;color:var(--green)}
.popular-section{margin-bottom:28px}
.pop-title{font-weight:800;font-size:16px;margin-bottom:14px;display:flex;align-items:center;gap:8px}
.pop-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.pop-card{background:white;border-radius:14px;border:1.5px solid var(--border);padding:14px 16px;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:10px;text-decoration:none;color:inherit}
.pop-card:hover{border-color:var(--primary);background:var(--pl)}
.pop-ic{font-size:20px}
.pop-text{font-size:13px;font-weight:600;line-height:1.4;flex:1}
.pop-arrow{font-size:14px;color:#CBD5E1}
.cta-contact{background:linear-gradient(130deg,#1A3560,#E85D26);border-radius:20px;padding:36px;text-align:center;margin-top:28px}
.cta-contact h3{font-family:'Playfair Display',serif;color:white;font-size:22px;font-weight:900;margin-bottom:10px}
.cta-contact p{color:rgba(255,255,255,0.78);font-size:14px;margin-bottom:22px;line-height:1.7}
.cta-btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn-white{padding:12px 24px;background:white;color:var(--primary);border:none;border-radius:12px;font-weight:800;cursor:pointer;font-size:14px;text-decoration:none;display:inline-block}
.btn-ghost{padding:12px 24px;background:transparent;color:white;border:2px solid rgba(255,255,255,0.4);border-radius:12px;font-weight:700;cursor:pointer;font-size:14px}
.guide-section{margin-bottom:28px}
.guide-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.guide-card{background:white;border-radius:14px;border:1.5px solid var(--border);padding:18px;text-align:center;cursor:pointer;transition:all .2s;text-decoration:none;color:inherit;display:block}
.guide-card:hover{border-color:var(--primary);transform:translateY(-3px);box-shadow:0 8px 24px rgba(232,93,38,0.1)}
.guide-ic{font-size:32px;margin-bottom:10px}
.guide-title{font-weight:800;font-size:14px;margin-bottom:4px}
.guide-sub{font-size:12px;color:var(--slate);line-height:1.5}
.empty{text-align:center;padding:48px 20px;background:white;border-radius:18px;border:1.5px solid var(--border)}
.empty-ic{font-size:52px;margin-bottom:14px}
.empty-title{font-weight:800;font-size:18px;margin-bottom:8px}
.empty-sub{color:var(--slate);font-size:14px}
@media(max-width:800px){
  .layout{grid-template-columns:1fr}
  .sidebar{position:static}
  .pop-grid{grid-template-columns:1fr}
  .guide-grid{grid-template-columns:1fr}
  .stats-inner{gap:24px}
}
CSS;

require 'includes/header.php';
?>

<div class="hero">
  <div class="hero-inner">
    <div class="hero-badge"><span>❓ Centre d'aide ServicesPlus</span></div>
    <h1>Trouvez rapidement<br/><em>vos réponses</em></h1>
    <p><?= $totalFaq ?>+ questions répondues par notre équipe.<br/>Trouvez l'aide dont vous avez besoin en quelques secondes.</p>
    <form class="search-bar" method="get" action="faq.php">
      <input type="text" name="q" placeholder="Recherchez une question... Ex : comment réserver ?" value="<?= e($q) ?>"/>
      <button type="submit">🔍 Rechercher</button>
    </form>
  </div>
</div>

<div class="stats-bar">
  <div class="stats-inner">
    <div class="stat-item"><div class="stat-val"><?= $totalFaq ?>+</div><div class="stat-lbl">Questions répondues</div></div>
    <div class="stat-item"><div class="stat-val"><?= count($catMeta) - 1 ?></div><div class="stat-lbl">Catégories</div></div>
    <div class="stat-item"><div class="stat-val">98%</div><div class="stat-lbl">Taux de satisfaction</div></div>
    <div class="stat-item"><div class="stat-val">&lt; 2h</div><div class="stat-lbl">Temps de réponse support</div></div>
  </div>
</div>

<div class="container">
  <div class="layout">

    <aside class="sidebar">
      <div class="sb-title">📚 Catégories</div>
      <?php foreach ($catMeta as $id => $meta): ?>
        <a class="cat-item <?= $catActive === $id ? 'active' : '' ?>" href="faq.php?cat=<?= $id ?>">
          <span class="ic"><?= $meta['ic'] ?></span>
          <span><?= $meta['lbl'] ?></span>
          <span class="cnt"><?= $id === 'tous' ? $totalFaq : ($comptes[$id] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
    </aside>

    <main>
      <div class="guide-section">
        <div class="pop-title">🚀 Guides rapides</div>
        <div class="guide-grid">
          <a class="guide-card" href="recherche.php"><div class="guide-ic">📅</div><div class="guide-title">Comment réserver ?</div><div class="guide-sub">Trouvez un prestataire et réservez en ligne</div></a>
          <a class="guide-card" href="inscription.php"><div class="guide-ic">🔧</div><div class="guide-title">Devenir prestataire</div><div class="guide-sub">Créez votre compte professionnel</div></a>
          <a class="guide-card" href="contact.php"><div class="guide-ic">💬</div><div class="guide-title">Contacter le support</div><div class="guide-sub">Notre équipe vous répond rapidement</div></a>
        </div>
      </div>

      <?php if ($catActive === 'tous' && $q === '' && $populaires): ?>
        <div class="popular-section">
          <div class="pop-title">🔥 Questions populaires</div>
          <div class="pop-grid">
            <?php foreach ($populaires as $f): ?>
              <a class="pop-card" href="#faq-<?= $f['id'] ?>"><span class="pop-ic">❓</span><span class="pop-text"><?= e($f['question']) ?></span><span class="pop-arrow">›</span></a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <div class="faq-header">
        <span class="faq-count"><?= count($questions) ?> question<?= count($questions)>1?'s':'' ?> trouvée<?= count($questions)>1?'s':'' ?></span>
        <form method="get" action="faq.php">
          <?php if ($catActive !== 'tous'): ?><input type="hidden" name="cat" value="<?= e($catActive) ?>"><?php endif; ?>
          <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
          <select class="faq-sort" name="tri" onchange="this.form.submit()">
            <option value="default" <?= $tri==='default'?'selected':'' ?>>Trier par défaut</option>
            <option value="popular" <?= $tri==='popular'?'selected':'' ?>>Plus populaires</option>
            <option value="recent" <?= $tri==='recent'?'selected':'' ?>>Plus récentes</option>
          </select>
        </form>
      </div>

      <?php if (empty($questions)): ?>
        <div class="empty">
          <div class="empty-ic">🔍</div>
          <div class="empty-title">Aucune question trouvée</div>
          <div class="empty-sub">Essayez d'autres mots-clés ou <a href="contact.php" style="color:var(--primary);font-weight:700;">contactez notre support</a>.</div>
        </div>
      <?php else: ?>
        <?php foreach ($questions as $f): $dejaVote = in_array($f['id'], $_SESSION['faq_votes'] ?? []); ?>
          <div class="faq-item" id="faq-<?= $f['id'] ?>">
            <div class="faq-q" onclick="this.parentElement.classList.toggle('open')">
              <span class="faq-q-text"><?= e($f['question']) ?></span>
              <span class="faq-icon">+</span>
            </div>
            <div class="faq-a">
              <p class="faq-a-text"><?= nl2br(e($f['reponse'])) ?></p>
              <div class="faq-a-actions">
                <?php foreach (array_filter(explode(',', $f['tags'] ?? '')) as $tag): ?>
                  <span class="faq-tag"><?= e(trim($tag)) ?></span>
                <?php endforeach; ?>
                <form method="post" action="faq.php<?= $catActive!=='tous' ? '?cat='.e($catActive) : '' ?>" class="faq-helpful">
                  Utile ?
                  <input type="hidden" name="vote_faq" value="<?= $f['id'] ?>">
                  <button type="submit" name="type" value="yes" class="<?= $dejaVote ? 'voted' : '' ?>" <?= $dejaVote?'disabled':'' ?>>👍 Oui (<?= (int)$f['votes_utile'] ?>)</button>
                  <button type="submit" name="type" value="no" <?= $dejaVote?'disabled':'' ?>>👎 Non (<?= (int)$f['votes_inutile'] ?>)</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="cta-contact">
        <h3>Vous n'avez pas trouvé votre réponse ? 🤔</h3>
        <p>Notre équipe support est disponible pour vous aider.<br/>Nous répondons rapidement à toutes vos demandes.</p>
        <div class="cta-btns">
          <a class="btn-white" href="contact.php">📧 Nous contacter</a>
        </div>
      </div>

    </main>
  </div>
</div>

<script>
  // Ouvre automatiquement la question ciblée par l'ancre #faq-ID (ex: depuis "Questions populaires")
  window.addEventListener('DOMContentLoaded', () => {
    if (location.hash) {
      const el = document.querySelector(location.hash);
      if (el) { el.classList.add('open'); el.scrollIntoView({behavior:'smooth', block:'center'}); }
    }
  });
</script>

<?php require 'includes/footer.php'; ?>