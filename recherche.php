<?php
require 'config/connexion.php';
require 'config/session.php';
require 'includes/fonctions.php';

/* ── Lecture des filtres (GET) ── */
$q            = trim($_GET['q'] ?? '');
$ville        = trim($_GET['ville'] ?? '');
$categorieBrut = $_GET['categorie'] ?? [];
if (!is_array($categorieBrut)) { $categorieBrut = [$categorieBrut]; }
$categorieSel = array_map('intval', $categorieBrut);
$noteMin      = (float)($_GET['note'] ?? 0);
$prixMax      = (int)($_GET['prix_max'] ?? 0);
$tri          = $_GET['tri'] ?? 'note';
$vue          = ($_GET['vue'] ?? 'liste') === 'grille' ? 'grille' : 'liste';
$page         = max(1, (int)($_GET['page'] ?? 1));
$parPage      = 9;

/* ── Catégories (pour la sidebar + tags rapides) avec compte réel ── */
$categories = $pdo->query("
    SELECT c.id, c.nom, c.icone, COUNT(pp.user_id) AS nb
    FROM categories c
    LEFT JOIN prestataire_profils pp ON pp.categorie_id = c.id AND pp.statut_validation = 'valide'
    GROUP BY c.id ORDER BY c.id
")->fetchAll();

/* ── Prix max réel dans la BD (pour le curseur) ── */
$prixMaxBD = (int)$pdo->query("SELECT COALESCE(MAX(tarif_horaire), 5000) FROM prestataire_profils WHERE statut_validation='valide'")->fetchColumn();
if ($prixMax <= 0) { $prixMax = $prixMaxBD; }

/* ── Construction de la requête ── */
$where  = ["pp.statut_validation = 'valide'"];
$params = [];

if ($q !== '') {
    $where[] = '(u.nom LIKE ? OR u.prenom LIKE ? OR c.nom LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($ville !== '') {
    $where[] = 'u.ville = ?';
    $params[] = $ville;
}
if ($categorieSel) {
    $where[] = 'pp.categorie_id IN (' . implode(',', array_fill(0, count($categorieSel), '?')) . ')';
    foreach ($categorieSel as $cid) { $params[] = $cid; }
}
if ($noteMin > 0) {
    $where[] = 'pp.note_moyenne >= ?';
    $params[] = $noteMin;
}
$where[] = 'pp.tarif_horaire <= ?';
$params[] = $prixMax;

$whereSql = implode(' AND ', $where);

$ordreSql = match ($tri) {
    'prix_asc'  => 'pp.tarif_horaire ASC',
    'prix_desc' => 'pp.tarif_horaire DESC',
    'missions'  => 'missions DESC',
    default     => 'pp.note_moyenne DESC, pp.nb_avis DESC',
};

/* ── Total pour la pagination ── */
$sqlCount = "SELECT COUNT(*) FROM prestataire_profils pp
             JOIN utilisateurs u ON u.id = pp.user_id
             JOIN categories c ON c.id = pp.categorie_id
             WHERE $whereSql";
$stmt = $pdo->prepare($sqlCount);
$stmt->execute($params);
$total     = (int)$stmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $parPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $parPage;

/* ── Résultats ── */
$sql = "SELECT u.id, u.nom, u.prenom, u.ville, pp.tarif_horaire, pp.note_moyenne, pp.nb_avis, pp.experience,
               c.nom AS categorie_nom, c.icone,
               (SELECT COUNT(*) FROM reservations r WHERE r.prestataire_id = u.id AND r.statut = 'terminee') AS missions
        FROM prestataire_profils pp
        JOIN utilisateurs u ON u.id = pp.user_id
        JOIN categories c ON c.id = pp.categorie_id
        WHERE $whereSql
        ORDER BY $ordreSql
        LIMIT $parPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$prestataires = $stmt->fetchAll();

function etoiles(float $note): string
{
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span style="color:' . ($i <= round($note) ? '#F59E0B' : '#E2E8F0') . '">★</span>';
    }
    return $html;
}

/** Reconstruit l'URL avec les filtres actuels + des remplacements ponctuels */
function urlAvec(array $remplacements = []): string
{
    $params = array_merge($_GET, $remplacements);
    foreach ($params as $k => $v) { if ($v === '' || $v === null) unset($params[$k]); }
    return 'recherche.php?' . http_build_query($params);
}

$page_title  = 'ServicesPlus – Recherche';
$page_active = 'recherche';
$extra_css   = <<<CSS
.container { max-width:1180px; margin:0 auto; padding:28px 20px; }
.search-hero { background: linear-gradient(130deg,#1A3560,#2563EB); padding: 32px 40px; border-radius: 22px; margin-bottom: 28px; position: relative; overflow: hidden; }
.search-hero::before { content:''; position:absolute; inset:0; background-image:radial-gradient(circle,rgba(255,255,255,0.06) 1px,transparent 1px); background-size:22px 22px; }
.search-hero-inner { position:relative; }
.search-hero h2 { color:white; font-family:'Playfair Display',serif; font-size:22px; font-weight:900; margin-bottom:16px; }
.search-bar { display:flex; background:white; border-radius:14px; overflow:hidden; box-shadow:0 6px 24px rgba(0,0,0,0.2); max-width:780px; }
.search-bar input { flex:1; padding:15px 20px; border:none; outline:none; font-size:14px; font-family:'Inter',sans-serif; color:var(--dark); }
.search-bar select { width:160px; padding:15px 14px; border:none; border-left:1px solid var(--border); outline:none; font-size:14px; font-family:'Inter',sans-serif; color:var(--dark); background:white; cursor:pointer; }
.search-bar button { padding:15px 28px; background:var(--primary); color:white; border:none; cursor:pointer; font-size:14px; font-weight:800; }
.quick-tags { display:flex; gap:8px; margin-top:14px; flex-wrap:wrap; }
.qtag { padding:6px 14px; border-radius:20px; border:none; cursor:pointer; font-size:12px; font-weight:600; font-family:'Inter',sans-serif; background:rgba(255,255,255,0.15); color:white; text-decoration:none; display:inline-block; }
.qtag:hover, .qtag.active { background:white; color:var(--primary); }
.layout { display:grid; grid-template-columns:260px 1fr; gap:22px; }
.sidebar { background:white; border-radius:18px; padding:22px; border:1.5px solid var(--border); box-shadow:0 2px 8px rgba(15,23,42,0.05); height:fit-content; position:sticky; top:86px; }
.sidebar-title { font-weight:800; font-size:16px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; }
.reset-btn { font-size:12px; color:var(--primary); cursor:pointer; font-weight:600; border:none; background:none; text-decoration:none; }
.filter-section { margin-bottom:22px; padding-bottom:22px; border-bottom:1px solid #F1F5F9; }
.filter-section:last-child { margin-bottom:0; padding-bottom:0; border-bottom:none; }
.filter-label { font-size:11px; font-weight:700; color:var(--slate); text-transform:uppercase; letter-spacing:0.6px; margin-bottom:12px; display:block; }
.filter-item { display:flex; align-items:center; gap:9px; margin-bottom:9px; cursor:pointer; }
.filter-item input[type="checkbox"], .filter-item input[type="radio"] { accent-color:var(--primary); width:15px; height:15px; cursor:pointer; }
.filter-item label { font-size:13px; cursor:pointer; flex:1; }
.filter-item .count { font-size:11px; color:#94A3B8; font-weight:600; }
.price-range { display:flex; flex-direction:column; gap:8px; }
.price-range input[type="range"] { width:100%; accent-color:var(--primary); }
.price-labels { display:flex; justify-content:space-between; font-size:12px; color:var(--slate); }
.results-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.results-count { font-weight:700; font-size:15px; }
.results-count span { color:var(--primary); }
.results-controls { display:flex; gap:8px; align-items:center; }
.sort-select { padding:8px 14px; border-radius:9px; border:1.5px solid var(--border); font-size:13px; font-family:'Inter',sans-serif; outline:none; background:white; cursor:pointer; }
.vue-btn { width:36px; height:36px; border-radius:9px; border:1.5px solid var(--border); background:white; cursor:pointer; font-size:16px; display:flex; align-items:center; justify-content:center; text-decoration:none; color:var(--dark); }
.vue-btn:hover, .vue-btn.active { border-color:var(--primary); background:var(--primary-light); }
.presta-list { display:flex; flex-direction:column; gap:14px; }
.presta-card-list { background:white; border-radius:18px; padding:20px; border:1.5px solid var(--border); box-shadow:0 2px 6px rgba(15,23,42,0.04); display:flex; gap:18px; align-items:center; text-decoration:none; color:inherit; }
.presta-card-list:hover { border-color:var(--primary); box-shadow:0 6px 24px rgba(232,93,38,0.1); }
.presta-avatar-lg { width:68px; height:68px; border-radius:20px; display:flex; align-items:center; justify-content:center; font-size:32px; flex-shrink:0; background:#FFF0EB; }
.presta-info { flex:1; }
.presta-top-row { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:4px; }
.presta-name { font-weight:800; font-size:16px; }
.presta-dispo { font-size:11px; font-weight:700; padding:4px 12px; border-radius:20px; color:#059669; background:#ECFDF5; }
.presta-meta { color:var(--slate); font-size:13px; margin-bottom:8px; }
.presta-tags { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:8px; }
.presta-tag { padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; background:#F1F5F9; color:var(--slate); }
.presta-stars { display:flex; align-items:center; gap:6px; }
.stars-num { font-weight:700; font-size:14px; }
.stars-avis { font-size:12px; color:#94A3B8; }
.presta-right { text-align:right; flex-shrink:0; min-width:140px; }
.presta-prix { font-weight:900; color:var(--primary); font-size:18px; margin-bottom:6px; }
.presta-dist { font-size:12px; color:#94A3B8; margin-bottom:10px; }
.presta-actions { display:flex; gap:8px; justify-content:flex-end; }
.btn-profil { padding:8px 14px; background:#F8FAFC; color:var(--slate); border:1.5px solid var(--border); border-radius:10px; font-weight:700; cursor:pointer; font-size:12px; text-decoration:none; }
.btn-profil:hover { border-color:var(--primary); color:var(--primary); }
.btn-reserver { padding:8px 16px; border:none; border-radius:10px; font-weight:700; cursor:pointer; font-size:12px; background:linear-gradient(135deg,#E85D26,#F59E0B); color:white; text-decoration:none; display:inline-block; }
.presta-grid-view { display:grid; grid-template-columns:repeat(3,1fr); gap:14px; }
.presta-card-grid { background:white; border-radius:18px; padding:20px; border:1.5px solid var(--border); position:relative; overflow:hidden; text-decoration:none; color:inherit; display:block; }
.presta-card-grid:hover { border-color:var(--primary); box-shadow:0 10px 28px rgba(232,93,38,0.1); }
.color-bar { position:absolute; top:0; left:0; right:0; height:4px; background:var(--primary); }
.grid-avatar { width:52px; height:52px; border-radius:16px; display:flex; align-items:center; justify-content:center; font-size:26px; margin-bottom:12px; margin-top:6px; background:#FFF0EB; }
.grid-name { font-weight:800; font-size:14px; margin-bottom:2px; }
.grid-service { color:var(--slate); font-size:12px; margin-bottom:10px; }
.grid-footer { display:flex; justify-content:space-between; align-items:center; padding-top:12px; border-top:1px solid #F1F5F9; margin-top:10px; }
.grid-prix { font-weight:900; color:var(--primary); font-size:14px; }
.pagination { display:flex; justify-content:center; gap:6px; margin-top:28px; }
.page-btn { width:38px; height:38px; border-radius:10px; border:1.5px solid var(--border); background:white; cursor:pointer; font-size:13px; font-weight:600; color:var(--slate); text-decoration:none; display:flex; align-items:center; justify-content:center; }
.page-btn:hover { border-color:var(--primary); color:var(--primary); }
.page-btn.active { background:linear-gradient(135deg,#E85D26,#F59E0B); color:white; border-color:transparent; }
.no-result { text-align:center; padding:60px 20px; background:white; border-radius:18px; border:1.5px solid var(--border); }
.no-result .icon { font-size:48px; margin-bottom:16px; }
.no-result h3 { font-weight:800; font-size:18px; margin-bottom:8px; }
.no-result p { color:var(--slate); font-size:14px; }
@media(max-width:900px){
  .layout { grid-template-columns:1fr; }
  .sidebar { position:static; }
  .presta-grid-view { grid-template-columns:repeat(2,1fr); }
}
@media(max-width:600px){
  .search-bar { flex-direction:column; }
  .search-bar select { width:100%; border-left:none; border-top:1px solid var(--border); }
  .presta-grid-view { grid-template-columns:1fr; }
  .presta-card-list { flex-direction:column; align-items:stretch; }
  .presta-right { text-align:left; min-width:0; }
  .presta-actions { justify-content:flex-start; }
}
CSS;

require 'includes/header.php';
?>

<script>
  // Les filtres de la sidebar (catégorie, note, prix) doivent fonctionner seuls,
  // sans être bloqués par un ancien texte resté dans la barre de recherche.
  function filtrerSansTexte() {
    const q = document.querySelector('#formFiltres [name="q"]');
    if (q) q.value = '';
    document.getElementById('formFiltres').submit();
  }
</script>

<div class="container">

  <form method="get" action="recherche.php" id="formFiltres">
  <div class="search-hero">
    <div class="search-hero-inner">
      <h2>🔍 Trouvez votre prestataire idéal</h2>
      <div class="search-bar">
        <input type="text" name="q" placeholder="Ex : plombier, ménage, électricien..." value="<?= e($q) ?>"/>
        <select name="ville">
          <option value="">Toutes les villes</option>
          <?php foreach (['Dakar','Thiès','Saint-Louis','Ziguinchor','Kaolack','Rufisque'] as $v): ?>
            <option <?= $ville === $v ? 'selected' : '' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
        <button type="submit">🔍 Rechercher</button>
      </div>
      <div class="quick-tags">
        <a class="qtag <?= empty($categorieSel) ? 'active' : '' ?>" href="recherche.php">Tous</a>
        <?php foreach (array_slice($categories, 0, 5) as $cat): ?>
          <a class="qtag <?= (in_array($cat['id'], $categorieSel) && count($categorieSel)===1) ? 'active' : '' ?>" href="<?= urlAvec(['categorie' => [$cat['id']], 'page' => null, 'q' => null]) ?>"><?= $cat['icone'] ?> <?= e($cat['nom']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="layout">
    <aside class="sidebar">
      <div class="sidebar-title">🎛️ Filtres <a class="reset-btn" href="recherche.php">Réinitialiser</a></div>

      <div class="filter-section">
        <span class="filter-label">Catégorie</span>
        <?php foreach ($categories as $cat): ?>
          <div class="filter-item">
            <input type="checkbox" name="categorie[]" id="cat<?= $cat['id'] ?>" value="<?= $cat['id'] ?>" <?= in_array($cat['id'], $categorieSel) ? 'checked' : '' ?> onchange="filtrerSansTexte()"/>
            <label for="cat<?= $cat['id'] ?>"><?= $cat['icone'] ?> <?= e($cat['nom']) ?></label>
            <span class="count"><?= (int)$cat['nb'] ?></span>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="filter-section">
        <span class="filter-label">Note minimum</span>
        <?php foreach ([['val'=>4.5,'lbl'=>'⭐⭐⭐⭐⭐ 4.5+'], ['val'=>4.0,'lbl'=>'⭐⭐⭐⭐ 4.0+'], ['val'=>3.5,'lbl'=>'⭐⭐⭐ 3.5+']] as $opt): ?>
          <div class="filter-item">
            <input type="radio" name="note" id="n<?= (int)($opt['val']*10) ?>" value="<?= $opt['val'] ?>" <?= $noteMin == $opt['val'] ? 'checked' : '' ?> onchange="filtrerSansTexte()"/>
            <label for="n<?= (int)($opt['val']*10) ?>"><?= $opt['lbl'] ?></label>
          </div>
        <?php endforeach; ?>
        <div class="filter-item">
          <input type="radio" name="note" id="nall" value="" <?= $noteMin == 0 ? 'checked' : '' ?> onchange="filtrerSansTexte()"/>
          <label for="nall">Toutes les notes</label>
        </div>
      </div>

      <div class="filter-section">
        <span class="filter-label">Prix max (FCFA/h)</span>
        <div class="price-range">
          <input type="range" name="prix_max" min="500" max="<?= max($prixMaxBD,500) ?>" step="100" value="<?= $prixMax ?>" oninput="document.getElementById('prixLabel').textContent=this.value+' FCFA'" onchange="filtrerSansTexte()"/>
          <div class="price-labels">
            <span>500 FCFA</span>
            <span id="prixLabel" style="color:var(--primary);font-weight:700;"><?= $prixMax ?> FCFA</span>
          </div>
        </div>
      </div>

      <input type="hidden" name="tri" value="<?= e($tri) ?>">
      <input type="hidden" name="vue" value="<?= e($vue) ?>">
    </aside>

    <main>
      <div class="results-header">
        <div class="results-count"><span><?= $total ?></span> prestataire<?= $total>1?'s':'' ?> trouvé<?= $total>1?'s':'' ?></div>
        <div class="results-controls">
          <select class="sort-select" name="tri_select" onchange="document.querySelector('[name=tri]').value=this.value; document.getElementById('formFiltres').submit()">
            <option value="note" <?= $tri==='note'?'selected':'' ?>>Trier par : Note</option>
            <option value="prix_asc" <?= $tri==='prix_asc'?'selected':'' ?>>Prix croissant</option>
            <option value="prix_desc" <?= $tri==='prix_desc'?'selected':'' ?>>Prix décroissant</option>
            <option value="missions" <?= $tri==='missions'?'selected':'' ?>>Expérience (missions)</option>
          </select>
          <a class="vue-btn <?= $vue==='liste'?'active':'' ?>" href="<?= urlAvec(['vue'=>'liste']) ?>" title="Vue liste">☰</a>
          <a class="vue-btn <?= $vue==='grille'?'active':'' ?>" href="<?= urlAvec(['vue'=>'grille']) ?>" title="Vue grille">⊞</a>
        </div>
      </div>

      <?php if (empty($prestataires)): ?>
        <div class="no-result">
          <div class="icon">🔍</div>
          <h3>Aucun prestataire trouvé</h3>
          <p>Essayez de modifier vos filtres ou d'élargir votre recherche.</p>
        </div>
      <?php elseif ($vue === 'liste'): ?>
        <div class="presta-list">
          <?php foreach ($prestataires as $p): ?>
            <a class="presta-card-list" href="profil_prestataire.php?id=<?= (int)$p['id'] ?>">
              <div class="presta-avatar-lg"><?= $p['icone'] ?></div>
              <div class="presta-info">
                <div class="presta-top-row">
                  <div class="presta-name"><?= e($p['prenom'].' '.$p['nom']) ?></div>
                  <span class="presta-dispo">● Disponible</span>
                </div>
                <div class="presta-meta"><?= e($p['categorie_nom']) ?> • <?= (int)$p['missions'] ?> missions • 📍 <?= e($p['ville'] ?? 'Dakar') ?></div>
                <div class="presta-tags"><span class="presta-tag"><?= e($p['experience']) ?></span></div>
                <div class="presta-stars">
                  <span class="stars"><?= etoiles($p['note_moyenne']) ?></span>
                  <span class="stars-num"><?= e((string)$p['note_moyenne']) ?></span>
                  <span class="stars-avis">(<?= (int)$p['nb_avis'] ?> avis)</span>
                </div>
              </div>
              <div class="presta-right">
                <div class="presta-prix"><?= number_format($p['tarif_horaire'],0,',',' ') ?> FCFA/h</div>
                <div class="presta-actions">
                  <span class="btn-profil">Voir profil</span>
                  <span class="btn-reserver">Réserver →</span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="presta-grid-view">
          <?php foreach ($prestataires as $p): ?>
            <a class="presta-card-grid" href="profil_prestataire.php?id=<?= (int)$p['id'] ?>">
              <div class="color-bar"></div>
              <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;">
                <div class="grid-avatar"><?= $p['icone'] ?></div>
                <span class="presta-dispo">● Dispo</span>
              </div>
              <div class="grid-name"><?= e($p['prenom'].' '.$p['nom']) ?></div>
              <div class="grid-service"><?= e($p['categorie_nom']) ?> • <?= (int)$p['missions'] ?> missions</div>
              <div class="presta-stars" style="margin-bottom:10px;">
                <span class="stars"><?= etoiles($p['note_moyenne']) ?></span>
                <span class="stars-num" style="font-size:13px;"><?= e((string)$p['note_moyenne']) ?></span>
                <span class="stars-avis">(<?= (int)$p['nb_avis'] ?>)</span>
              </div>
              <div class="grid-footer">
                <span class="grid-prix"><?= number_format($p['tarif_horaire'],0,',',' ') ?> FCFA/h</span>
                <span class="btn-reserver">Réserver</span>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($totalPages > 1): ?>
        <div class="pagination">
          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a class="page-btn <?= $i===$page?'active':'' ?>" href="<?= urlAvec(['page'=>$i]) ?>"><?= $i ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
  </form>
</div>

<?php require 'includes/footer.php'; ?>