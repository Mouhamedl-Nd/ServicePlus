<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('admin', '../');

$adminId = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE id=?');
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

if (!$admin) {
    // Session invalide ou compte supprimé : on force une reconnexion propre
    session_destroy();
    header('Location: admin_connexion.php');
    exit;
}

$erreurs = []; $succes = false; $erreursPass = []; $succesPass = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maj_profil'])) {
    $nom = trim($_POST['nom'] ?? ''); $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? ''); $telephone = trim($_POST['telephone'] ?? ''); $ville = trim($_POST['ville'] ?? '');

    if (!$nom || !$prenom || !$email) { $erreurs[] = 'Veuillez remplir tous les champs obligatoires.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erreurs[] = 'Email invalide.'; }
    if (!$erreurs) {
        $chk = $pdo->prepare('SELECT id FROM utilisateurs WHERE email=? AND id!=?');
        $chk->execute([$email, $adminId]);
        if ($chk->fetch()) { $erreurs[] = 'Cette adresse email est déjà utilisée par un autre compte.'; }
    }
    if (!$erreurs) {
        $photoPath = $admin['photo'];
        if (!empty($_FILES['photo']['name'])) {
            $up = uploaderFichier($_FILES['photo'], 'profils');
            if ($up) { $photoPath = $up; }
        }
        $pdo->prepare('UPDATE utilisateurs SET nom=?,prenom=?,email=?,telephone=?,ville=?,photo=? WHERE id=?')
            ->execute([$nom,$prenom,$email,$telephone,$ville,$photoPath,$adminId]);
        $_SESSION['nom']=$nom; $_SESSION['prenom']=$prenom; $_SESSION['photo']=$photoPath;
        $succes = true;
        $stmt->execute([$adminId]); $admin = $stmt->fetch();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['maj_password'])) {
    $actuel = $_POST['mdp_actuel'] ?? ''; $nouveau = $_POST['mdp_nouveau'] ?? ''; $confirm = $_POST['mdp_confirm'] ?? '';
    if (!password_verify($actuel, $admin['mot_de_passe'])) { $erreursPass[] = 'Mot de passe actuel incorrect.'; }
    if (strlen($nouveau) < 8) { $erreursPass[] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.'; }
    if ($nouveau !== $confirm) { $erreursPass[] = 'Les mots de passe ne correspondent pas.'; }
    if (!$erreursPass) {
        $pdo->prepare('UPDATE utilisateurs SET mot_de_passe=? WHERE id=?')->execute([password_hash($nouveau, PASSWORD_BCRYPT), $adminId]);
        $succesPass = true;
    }
}

$page_title = 'Mon profil';
$page_active = 'profil';
$extra_css = <<<CSS
.layout{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start}
.panel{background:white;border-radius:18px;padding:24px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);margin-bottom:20px}
.panel-title{font-weight:800;font-size:16px;margin-bottom:18px}
.avatar-big{width:80px;height:80px;border-radius:20px;background:linear-gradient(135deg,#E85D26,#F59E0B);display:flex;align-items:center;justify-content:center;font-size:32px;color:white;font-weight:800;margin:0 auto 16px}
.fg{margin-bottom:14px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.fl{display:block;font-size:11px;font-weight:700;color:var(--slate);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px}
.fi{width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid var(--border);font-size:13.5px;font-family:'Inter',sans-serif}
.btn-p{padding:12px 24px;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:11px;font-weight:800;font-size:14px;cursor:pointer}
.alert-ok{background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
.alert-err{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
@media(max-width:900px){ .layout{grid-template-columns:1fr} }
CSS;

require 'admin_header.php';
?>

<div class="layout">
  <div class="panel">
    <div class="panel-title">👤 Informations personnelles</div>
    <?php if ($succes): ?><div class="alert-ok">✅ Profil mis à jour avec succès.</div><?php endif; ?>
    <?php if ($erreurs): ?><div class="alert-err">❌ <?= e(implode(' ', $erreurs)) ?></div><?php endif; ?>
    <div style="text-align:center;">
      <div class="avatar-big"><?= strtoupper(substr($admin['prenom'],0,1)) ?></div>
    </div>
    <form method="post" enctype="multipart/form-data">
      <div class="form-grid">
        <div class="fg"><label class="fl">Prénom</label><input type="text" name="prenom" class="fi" value="<?= e($admin['prenom'] ?? '') ?>" required></div>
        <div class="fg"><label class="fl">Nom</label><input type="text" name="nom" class="fi" value="<?= e($admin['nom'] ?? '') ?>" required></div>
      </div>
      <div class="fg"><label class="fl">Email</label><input type="email" name="email" class="fi" value="<?= e($admin['email'] ?? '') ?>" required></div>
      <div class="form-grid">
        <div class="fg"><label class="fl">Téléphone</label><input type="tel" name="telephone" class="fi" value="<?= e($admin['telephone'] ?? '') ?>"></div>
        <div class="fg"><label class="fl">Ville</label><input type="text" name="ville" class="fi" value="<?= e($admin['ville'] ?? '') ?>"></div>
      </div>
      <div class="fg"><label class="fl">Photo de profil</label><input type="file" name="photo" class="fi" accept="image/*"></div>
      <button type="submit" name="maj_profil" value="1" class="btn-p">💾 Enregistrer</button>
    </form>
  </div>

  <div class="panel">
    <div class="panel-title">🔒 Changer le mot de passe</div>
    <?php if ($succesPass): ?><div class="alert-ok">✅ Mot de passe modifié avec succès.</div><?php endif; ?>
    <?php if ($erreursPass): ?><div class="alert-err">❌ <?= e(implode(' ', $erreursPass)) ?></div><?php endif; ?>
    <form method="post">
      <div class="fg"><label class="fl">Mot de passe actuel</label><input type="password" name="mdp_actuel" class="fi" required></div>
      <div class="fg"><label class="fl">Nouveau mot de passe</label><input type="password" name="mdp_nouveau" class="fi" required></div>
      <div class="fg"><label class="fl">Confirmer le nouveau mot de passe</label><input type="password" name="mdp_confirm" class="fi" required></div>
      <button type="submit" name="maj_password" value="1" class="btn-p">🔑 Changer le mot de passe</button>
    </form>

    <div class="panel-title" style="margin-top:26px;">ℹ️ Informations du compte</div>
    <div style="font-size:13px;color:var(--slate);line-height:2;">
      Rôle : <strong style="color:var(--dark);">Administrateur</strong><br>
      Membre depuis : <strong style="color:var(--dark);"><?= date('d/m/Y', strtotime($admin['date_creation'])) ?></strong>
    </div>
  </div>
</div>

<?php require 'admin_footer.php'; ?>