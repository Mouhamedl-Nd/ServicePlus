<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('admin', '../');

$catMeta = [
    'compte'=>'Compte & Inscription','recherche'=>'Recherche & Prestataires','reservation'=>'Réservation',
    'paiement'=>'Paiement','prestataire'=>'Espace prestataire','securite'=>'Sécurité',
    'technique'=>'Problèmes techniques','autre'=>'Autres questions',
];

$erreurs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['supprimer'])) {
        $pdo->prepare('DELETE FROM faq WHERE id=?')->execute([(int)$_POST['supprimer']]);
        header('Location: gestion_faq.php'); exit;
    }
    if (isset($_POST['enregistrer'])) {
        $categorie = $_POST['categorie'] ?? 'autre';
        $question  = trim($_POST['question'] ?? '');
        $reponse   = trim($_POST['reponse'] ?? '');
        $tags      = trim($_POST['tags'] ?? '');
        $populaire = isset($_POST['populaire']) ? 1 : 0;
        $id        = (int)($_POST['id'] ?? 0);

        if (!$question || !$reponse) { $erreurs[] = 'La question et la réponse sont obligatoires.'; }

        if (!$erreurs) {
            if ($id > 0) {
                $pdo->prepare('UPDATE faq SET categorie=?,question=?,reponse=?,tags=?,populaire=? WHERE id=?')
                    ->execute([$categorie,$question,$reponse,$tags,$populaire,$id]);
            } else {
                $pdo->prepare('INSERT INTO faq (categorie,question,reponse,tags,populaire) VALUES (?,?,?,?,?)')
                    ->execute([$categorie,$question,$reponse,$tags,$populaire]);
            }
            header('Location: gestion_faq.php'); exit;
        } else {
            // En cas d'erreur, on réaffiche le formulaire avec ce que l'admin vient de saisir
            $edition = ['id'=>$id,'categorie'=>$categorie,'question'=>$question,'reponse'=>$reponse,'tags'=>$tags,'populaire'=>$populaire];
        }
    }
}

if (!isset($edition)) {
    $editId = (int)($_GET['edit'] ?? 0);
    $edition = null;
    if ($editId) {
        $stmt = $pdo->prepare('SELECT * FROM faq WHERE id=?');
        $stmt->execute([$editId]);
        $edition = $stmt->fetch();
    }
}

$questions = $pdo->query('SELECT * FROM faq ORDER BY categorie, id')->fetchAll();

$page_title = 'Gestion FAQ';
$page_active = 'faq';
$extra_css = <<<CSS
.layout{display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start}
.faq-row{background:white;border-radius:14px;border:1.5px solid var(--border);padding:16px 18px;margin-bottom:10px}
.faq-row-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
.faq-cat{font-size:10.5px;font-weight:800;padding:3px 10px;border-radius:20px;background:#EFF6FF;color:#2563EB;text-transform:uppercase;margin-bottom:6px;display:inline-block}
.faq-q{font-weight:700;font-size:14px;margin-bottom:4px}
.faq-a-preview{font-size:12.5px;color:var(--slate);line-height:1.5;max-height:40px;overflow:hidden}
.faq-meta{font-size:11.5px;color:#94A3B8;margin-top:6px}
.faq-pop{color:#F59E0B}
.faq-actions{display:flex;gap:6px;flex-shrink:0}
.btn-mini{padding:6px 11px;border-radius:8px;border:none;font-size:11px;font-weight:800;cursor:pointer;font-family:'Inter',sans-serif;text-decoration:none;display:inline-block}
.btn-mini.blue{background:#EFF6FF;color:#2563EB} .btn-mini.blue:hover{background:#2563EB;color:white}
.btn-mini.red{background:#FEF2F2;color:#DC2626} .btn-mini.red:hover{background:#DC2626;color:white}
.panel{background:white;border-radius:18px;padding:22px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);position:sticky;top:86px}
.panel-title{font-weight:800;font-size:15px;margin-bottom:16px}
.fg{margin-bottom:14px}
.fl{display:block;font-size:11px;font-weight:700;color:var(--slate);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.fi{width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid var(--border);font-size:13.5px;font-family:'Inter',sans-serif}
textarea.fi{resize:vertical;min-height:100px}
.check-row{display:flex;align-items:center;gap:8px;margin-bottom:14px}
.btn-p{width:100%;padding:12px 0;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:11px;font-weight:800;font-size:14px;cursor:pointer}
.btn-cancel{width:100%;padding:10px 0;background:#F8FAFC;color:var(--slate);border:1.5px solid var(--border);border-radius:11px;font-weight:700;font-size:13px;text-align:center;text-decoration:none;display:block;margin-top:8px}
.alert-err{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;padding:10px 14px;border-radius:10px;font-size:12.5px;margin-bottom:14px}
@media(max-width:960px){ .layout{grid-template-columns:1fr} .panel{position:static} }
CSS;

require 'admin_header.php';
?>

<div class="layout">
  <div>
    <?php if (empty($questions)): ?>
      <div class="empty-mini" style="text-align:center;padding:40px;background:white;border-radius:16px;border:1.5px solid var(--border);color:var(--slate);">Aucune question dans la FAQ.</div>
    <?php else: foreach ($questions as $f): ?>
      <div class="faq-row">
        <div class="faq-row-top">
          <div style="flex:1;">
            <span class="faq-cat"><?= e($catMeta[$f['categorie']] ?? $f['categorie']) ?></span>
            <?php if ($f['populaire']): ?><span class="faq-pop">🔥 Populaire</span><?php endif; ?>
            <div class="faq-q"><?= e($f['question']) ?></div>
            <div class="faq-a-preview"><?= e(mb_substr($f['reponse'],0,140)) ?><?= mb_strlen($f['reponse'])>140?'…':'' ?></div>
            <div class="faq-meta">👍 <?= (int)$f['votes_utile'] ?> · 👎 <?= (int)$f['votes_inutile'] ?></div>
          </div>
          <div class="faq-actions">
            <a class="btn-mini blue" href="gestion_faq.php?edit=<?= $f['id'] ?>#form">✏️ Modifier</a>
            <form method="post" onsubmit="return confirm('Supprimer cette question ?')"><input type="hidden" name="supprimer" value="<?= $f['id'] ?>"><button type="submit" class="btn-mini red">🗑️</button></form>
          </div>
        </div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="panel" id="form">
    <div class="panel-title"><?= $edition ? '✏️ Modifier la question' : '➕ Ajouter une question' ?></div>
    <?php if ($erreurs): ?><div class="alert-err">❌ <?= e(implode(' ',$erreurs)) ?></div><?php endif; ?>
    <form method="post" action="gestion_faq.php">
      <input type="hidden" name="id" value="<?= $edition['id'] ?? 0 ?>">
      <div class="fg">
        <label class="fl">Catégorie</label>
        <select name="categorie" class="fi">
          <?php foreach ($catMeta as $id => $lbl): ?>
            <option value="<?= $id ?>" <?= (($edition['categorie'] ?? 'autre')===$id)?'selected':'' ?>><?= e($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="fg"><label class="fl">Question</label><input type="text" name="question" class="fi" value="<?= e($edition['question'] ?? '') ?>" required></div>
      <div class="fg"><label class="fl">Réponse</label><textarea name="reponse" class="fi" required><?= e($edition['reponse'] ?? '') ?></textarea></div>
      <div class="fg"><label class="fl">Tags (séparés par des virgules)</label><input type="text" name="tags" class="fi" value="<?= e($edition['tags'] ?? '') ?>" placeholder="Ex: Inscription,Compte"></div>
      <div class="check-row"><input type="checkbox" name="populaire" id="pop" <?= !empty($edition['populaire'])?'checked':'' ?>><label for="pop" style="font-size:13px;">Marquer comme question populaire</label></div>
      <button type="submit" name="enregistrer" value="1" class="btn-p"><?= $edition ? 'Enregistrer les modifications' : 'Ajouter la question' ?></button>
      <?php if ($edition): ?><a href="gestion_faq.php" class="btn-cancel">Annuler</a><?php endif; ?>
    </form>
  </div>
</div>

<?php require 'admin_footer.php'; ?>