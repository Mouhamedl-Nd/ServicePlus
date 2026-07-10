<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('prestataire', '../');

$prestaId = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE id=?'); $stmt->execute([$prestaId]); $user = $stmt->fetch();
$stmtPP = $pdo->prepare('SELECT * FROM prestataire_profils WHERE user_id=?'); $stmtPP->execute([$prestaId]); $profil = $stmtPP->fetch();
$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();

$erreurs = []; $succes = false; $erreursPass = []; $succesPass = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maj_profil'])) {
    $nom = trim($_POST['nom'] ?? ''); $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? ''); $telephone = trim($_POST['telephone'] ?? ''); $ville = trim($_POST['ville'] ?? '');
    $categorieId = (int)($_POST['categorie_id'] ?? 0);
    $tarif = (float)($_POST['tarif'] ?? 0);
    $experience = trim($_POST['experience'] ?? '');
    $bio = trim($_POST['bio'] ?? '');

    if (!$nom || !$prenom || !$email || !$categorieId || $tarif<=0) { $erreurs[] = 'Veuillez remplir tous les champs obligatoires.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erreurs[] = 'Email invalide.'; }
    if (!$erreurs) {
        $chk = $pdo->prepare('SELECT id FROM utilisateurs WHERE email=? AND id!=?'); $chk->execute([$email, $prestaId]);
        if ($chk->fetch()) { $erreurs[] = 'Cette adresse email est déjà utilisée.'; }
    }
    if (!$erreurs) {
        $photoPath = $user['photo'];
        if (!empty($_FILES['photo']['name'])) { $up = uploaderFichier($_FILES['photo'],'profils'); if ($up) $photoPath = $up; }
        $pdo->prepare('UPDATE utilisateurs SET nom=?,prenom=?,email=?,telephone=?,ville=?,photo=? WHERE id=?')
            ->execute([$nom,$prenom,$email,$telephone,$ville,$photoPath,$prestaId]);
        $pdo->prepare('UPDATE prestataire_profils SET categorie_id=?, tarif_horaire=?, experience=?, bio=? WHERE user_id=?')
            ->execute([$categorieId,$tarif,$experience,$bio,$prestaId]);
        $_SESSION['nom']=$nom; $_SESSION['prenom']=$prenom; $_SESSION['photo']=$photoPath;
        $succes = true;
        $stmt->execute([$prestaId]); $user = $stmt->fetch();
        $stmtPP->execute([$prestaId]); $profil = $stmtPP->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maj_password'])) {
    $actuel = $_POST['mdp_actuel'] ?? ''; $nouveau = $_POST['mdp_nouveau'] ?? ''; $confirm = $_POST['mdp_confirm'] ?? '';
    if (!password_verify($actuel, $user['mot_de_passe'])) { $erreursPass[] = 'Mot de passe actuel incorrect.'; }
    if (strlen($nouveau) < 8) { $erreursPass[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.'; }
    if ($nouveau !== $confirm) { $erreursPass[] = 'Les mots de passe ne correspondent pas.'; }
    if (!$erreursPass) {
        $pdo->prepare('UPDATE utilisateurs SET mot_de_passe=? WHERE id=?')->execute([password_hash($nouveau, PASSWORD_BCRYPT), $prestaId]);
        $succesPass = true;
    }
}

$page_title  = 'ServicesPlus – Mon profil';
$racine      = '../';
$extra_css   = <<<CSS
.container{max-width:900px;margin:0 auto;padding:32px 20px}
.tabs{display:flex;gap:2px;background:#F8FAFC;border-radius:13px;padding:5px;border:1.5px solid var(--border);margin-bottom:20px}
.tab{flex:1;padding:9px 0;border-radius:10px;border:none;cursor:pointer;font-size:13px;font-weight:700;background:transparent;color:var(--slate)}
.tab.active{background:linear-gradient(135deg,#E85D26,#F59E0B);color:white}
.sec{display:none} .sec.active{display:block}
.card{background:white;border-radius:18px;padding:24px;border:1.5px solid var(--border);box-shadow:0 2px 6px rgba(15,23,42,0.04);margin-bottom:18px}
.card-title{font-weight:800;font-size:16px;margin-bottom:20px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.fg{display:flex;flex-direction:column;gap:7px;margin-bottom:14px}
.fg.full{grid-column:1/-1}
.fl{font-size:11px;font-weight:700;color:var(--slate);text-transform:uppercase;letter-spacing:.5px}
.fi{width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid var(--border);font-size:13.5px;font-family:'Inter',sans-serif}
textarea.fi{resize:vertical;min-height:90px}
.btn-p{padding:12px 24px;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:11px;font-weight:800;font-size:14px;cursor:pointer}
.alert-ok{background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
.alert-err{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
@media(max-width:700px){ .form-grid{grid-template-columns:1fr} }
CSS;

require '../includes/header.php';
?>

<div class="container">
  <div class="tabs">
    <button class="tab active" onclick="showTab('perso',this)">👤 Personnel</button>
    <button class="tab" onclick="showTab('pro',this)">🔧 Professionnel</button>
    <button class="tab" onclick="showTab('securite',this)">🔒 Sécurité</button>
  </div>

  <div class="sec active" id="sec-perso">
    <div class="card">
      <div class="card-title">👤 Informations personnelles</div>
      <?php if ($succes): ?><div class="alert-ok">✅ Profil mis à jour avec succès.</div><?php endif; ?>
      <?php if ($erreurs): ?><div class="alert-err">❌ <?= e(implode(' ', $erreurs)) ?></div><?php endif; ?>
      <form method="post" enctype="multipart/form-data" id="formProfil">
        <div class="form-grid">
          <div class="fg"><label class="fl">Prénom</label><input type="text" name="prenom" class="fi" value="<?= e($user['prenom']) ?>" required></div>
          <div class="fg"><label class="fl">Nom</label><input type="text" name="nom" class="fi" value="<?= e($user['nom']) ?>" required></div>
          <div class="fg full"><label class="fl">Email</label><input type="email" name="email" class="fi" value="<?= e($user['email']) ?>" required></div>
          <div class="fg"><label class="fl">Téléphone</label><input type="tel" name="telephone" class="fi" value="<?= e($user['telephone'] ?? '') ?>"></div>
          <div class="fg"><label class="fl">Ville</label><input type="text" name="ville" class="fi" value="<?= e($user['ville'] ?? '') ?>"></div>
          <div class="fg full"><label class="fl">Photo de profil</label><input type="file" name="photo" class="fi" accept="image/*"></div>
        </div>
        <div id="proFields" style="display:none;"></div>
        <button type="submit" name="maj_profil" value="1" class="btn-p">💾 Enregistrer</button>
      </form>
    </div>
  </div>

  <div class="sec" id="sec-pro">
    <div class="card">
      <div class="card-title">🔧 Informations professionnelles</div>
      <form method="post" id="formPro">
        <div class="form-grid">
          <div class="fg"><label class="fl">Catégorie de service</label>
            <select name="categorie_id" class="fi" form="formProfil" required>
              <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $profil['categorie_id']==$c['id']?'selected':'' ?>><?= $c['icone'] ?> <?= e($c['nom']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="fg"><label class="fl">Tarif horaire (FCFA)</label><input type="number" name="tarif" class="fi" value="<?= $profil['tarif_horaire'] ?>" form="formProfil" required></div>
          <div class="fg full"><label class="fl">Expérience</label>
            <select name="experience" class="fi" form="formProfil">
              <?php foreach (['Moins de 1 an','1 à 3 ans','3 à 5 ans','5 à 10 ans','Plus de 10 ans'] as $ex): ?><option <?= $profil['experience']===$ex?'selected':'' ?>><?= $ex ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="fg full"><label class="fl">Bio / Description</label><textarea name="bio" class="fi" form="formProfil"><?= e($profil['bio']) ?></textarea></div>
        </div>
        <button type="submit" class="btn-p" onclick="document.getElementById('formProfil').requestSubmit(); return false;">💾 Enregistrer</button>
      </form>
    </div>
  </div>

  <div class="sec" id="sec-securite">
    <div class="card">
      <div class="card-title">🔒 Changer le mot de passe</div>
      <?php if ($succesPass): ?><div class="alert-ok">✅ Mot de passe modifié avec succès.</div><?php endif; ?>
      <?php if ($erreursPass): ?><div class="alert-err">❌ <?= e(implode(' ', $erreursPass)) ?></div><?php endif; ?>
      <form method="post">
        <div class="fg"><label class="fl">Mot de passe actuel</label><input type="password" name="mdp_actuel" class="fi" required></div>
        <div class="fg"><label class="fl">Nouveau mot de passe</label><input type="password" name="mdp_nouveau" class="fi" required></div>
        <div class="fg"><label class="fl">Confirmer</label><input type="password" name="mdp_confirm" class="fi" required></div>
        <button type="submit" name="maj_password" value="1" class="btn-p">🔑 Changer le mot de passe</button>
      </form>
    </div>
  </div>
</div>

<script>
function showTab(id, btn) {
  document.querySelectorAll('.sec').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('sec-'+id).classList.add('active');
  btn.classList.add('active');
}
</script>

<?php require '../includes/footer.php'; ?>