<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';

// Code secret requis pour pouvoir créer un compte admin.
// ⚠️ Change cette valeur avant la mise en production, et ne la partage qu'avec les personnes autorisées.
define('ADMIN_SECRET_CODE', 'SP-ADMIN-2026-KHALILTECH');

$erreurs = [];
$succes  = false;
$vd = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vd = $_POST;
    $nom      = trim($_POST['nom'] ?? '');
    $prenom   = trim($_POST['prenom'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telephone= trim($_POST['telephone'] ?? '');
    $ville    = trim($_POST['ville'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2= $_POST['password2'] ?? '';
    $code     = trim($_POST['code'] ?? '');

    if ($code !== ADMIN_SECRET_CODE) {
        $erreurs[] = 'Code secret invalide. Vous n\'êtes pas autorisé à créer un compte administrateur.';
    }
    if (!$nom || !$prenom || !$email || !$telephone || !$ville) {
        $erreurs[] = 'Veuillez remplir tous les champs.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreurs[] = 'Adresse email invalide.';
    }
    if (strlen($password) < 8) {
        $erreurs[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $password2) {
        $erreurs[] = 'Les mots de passe ne correspondent pas.';
    }

    if (!$erreurs) {
        $stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $erreurs[] = 'Un compte existe déjà avec cette adresse email.';
        }
    }

    if (!$erreurs) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO utilisateurs (role, nom, prenom, email, mot_de_passe, telephone, ville, statut) VALUES ('admin', ?, ?, ?, ?, ?, ?, 'actif')")
            ->execute([$nom, $prenom, $email, $hash, $telephone, $ville]);
        $succes = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Administration – Création d'accès</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:radial-gradient(circle at 30% 20%,#1A3560,#0F172A 60%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);backdrop-filter:blur(16px);border-radius:22px;padding:40px;width:100%;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,0.4)}
.icon{width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,#E85D26,#F59E0B);display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 18px;box-shadow:0 8px 24px rgba(232,93,38,0.35)}
h1{font-family:'Playfair Display',serif;color:white;font-size:22px;font-weight:900;text-align:center;margin-bottom:4px}
p.sub{color:rgba(255,255,255,0.5);font-size:12.5px;text-align:center;margin-bottom:26px}
.fg{margin-bottom:14px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.fl{display:block;font-size:11px;font-weight:700;color:rgba(255,255,255,0.55);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}
.fi{width:100%;padding:12px 14px;border-radius:11px;border:1.5px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.06);color:white;font-size:14px;font-family:'Inter',sans-serif;outline:none}
.fi:focus{border-color:#E85D26}
.fi::placeholder{color:rgba(255,255,255,0.3)}
.iw{position:relative}
.iw .fi{padding-right:44px}
.toggle-pass{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:rgba(255,255,255,0.5)}
.btn{width:100%;padding:14px 0;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:12px;font-weight:800;font-size:14px;cursor:pointer;margin-top:6px;box-shadow:0 8px 24px rgba(232,93,38,0.3)}
.err{background:rgba(220,38,38,0.15);border:1px solid rgba(220,38,38,0.3);color:#FCA5A5;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
.ok{background:rgba(5,150,105,0.15);border:1px solid rgba(5,150,105,0.3);color:#6EE7B7;padding:16px;border-radius:12px;font-size:13.5px;margin-bottom:16px;text-align:center;line-height:1.6}
.foot-link{text-align:center;margin-top:20px;font-size:12px;color:rgba(255,255,255,0.35)}
.foot-link a{color:rgba(255,255,255,0.55);text-decoration:none;font-weight:700}
.foot-link a:hover{color:white}
.code-hint{font-size:11px;color:rgba(255,255,255,0.3);margin-top:5px}
</style>
</head>
<body>
<div class="card">
  <div class="icon">🛡️</div>
  <h1>Nouvel accès admin</h1>
  <p class="sub">Réservé aux personnes autorisées par ServicesPlus</p>

  <?php if ($succes): ?>
    <div class="ok">✅ Compte administrateur créé avec succès pour <?= e($vd['prenom']) ?> !<br/><a href="admin_connexion.php" style="color:#6EE7B7;font-weight:800;">Se connecter maintenant →</a></div>
  <?php else: ?>
    <?php if ($erreurs): ?><div class="err">⚠️ <?= e(implode(' ', $erreurs)) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-grid">
        <div class="fg"><label class="fl">Prénom</label><input type="text" name="prenom" class="fi" value="<?= e($vd['prenom'] ?? '') ?>" required></div>
        <div class="fg"><label class="fl">Nom</label><input type="text" name="nom" class="fi" value="<?= e($vd['nom'] ?? '') ?>" required></div>
      </div>
      <div class="fg"><label class="fl">Email</label><input type="email" name="email" class="fi" placeholder="admin@servicesplus.sn" value="<?= e($vd['email'] ?? '') ?>" required></div>
      <div class="form-grid">
        <div class="fg"><label class="fl">Téléphone</label><input type="tel" name="telephone" class="fi" placeholder="+221 77 000 00 00" value="<?= e($vd['telephone'] ?? '') ?>" required></div>
        <div class="fg"><label class="fl">Ville</label><input type="text" name="ville" class="fi" placeholder="Dakar" value="<?= e($vd['ville'] ?? '') ?>" required></div>
      </div>
      <div class="form-grid">
        <div class="fg"><label class="fl">Mot de passe</label>
          <div class="iw">
            <input type="password" name="password" id="pass1" class="fi" placeholder="8 caractères min." required>
            <button type="button" class="toggle-pass" onclick="togglePass('pass1', this)">👁️</button>
          </div>
        </div>
        <div class="fg"><label class="fl">Confirmer</label>
          <div class="iw">
            <input type="password" name="password2" id="pass2" class="fi" required>
            <button type="button" class="toggle-pass" onclick="togglePass('pass2', this)">👁️</button>
          </div>
        </div>
      </div>
      <div class="fg">
        <label class="fl">Code secret administrateur</label>
        <input type="password" name="code" class="fi" placeholder="Fourni par le responsable du projet" required>
        <div class="code-hint">Ce code protège la création de comptes admin. Demandez-le au responsable ServicesPlus.</div>
      </div>
      <button type="submit" class="btn">Créer le compte administrateur →</button>
    </form>
  <?php endif; ?>

  <div class="foot-link">Déjà un accès ? <a href="admin_connexion.php">Se connecter →</a></div>
</div>
<script>
  function togglePass(id, btn) {
    const input = document.getElementById(id);
    const visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    btn.textContent = visible ? '👁️' : '🙈';
  }
</script>
</body>
</html>