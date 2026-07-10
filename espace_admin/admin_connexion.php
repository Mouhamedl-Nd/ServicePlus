<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';

if (!empty($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: admin.php');
    exit;
}

$erreur = '';
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM utilisateurs WHERE email = ? AND role = 'admin'");
    $stmt->execute([$email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['mot_de_passe'])) {
        $erreur = 'Identifiants incorrects.';
    } elseif ($admin['statut'] === 'suspendu') {
        $erreur = 'Ce compte administrateur a été suspendu.';
    } else {
        $_SESSION['user_id'] = $admin['id'];
        $_SESSION['nom']     = $admin['nom'];
        $_SESSION['prenom']  = $admin['prenom'];
        $_SESSION['role']    = 'admin';
        $_SESSION['photo']   = $admin['photo'];
        header('Location: admin.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Administration – ServicesPlus</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Inter',sans-serif;background:radial-gradient(circle at 30% 20%,#1A3560,#0F172A 60%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.1);backdrop-filter:blur(16px);border-radius:22px;padding:40px;width:100%;max-width:400px;box-shadow:0 20px 60px rgba(0,0,0,0.4)}
.icon{width:60px;height:60px;border-radius:16px;background:linear-gradient(135deg,#E85D26,#F59E0B);display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 18px;box-shadow:0 8px 24px rgba(232,93,38,0.35)}
h1{font-family:'Playfair Display',serif;color:white;font-size:22px;font-weight:900;text-align:center;margin-bottom:4px}
p.sub{color:rgba(255,255,255,0.5);font-size:12.5px;text-align:center;margin-bottom:26px}
.fg{margin-bottom:16px}
.fl{display:block;font-size:11px;font-weight:700;color:rgba(255,255,255,0.55);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}
.fi{width:100%;padding:12px 14px;border-radius:11px;border:1.5px solid rgba(255,255,255,0.15);background:rgba(255,255,255,0.06);color:white;font-size:14px;font-family:'Inter',sans-serif;outline:none}
.fi:focus{border-color:#E85D26}
.fi::placeholder{color:rgba(255,255,255,0.3)}
.iw{position:relative}
.iw .fi{padding-right:44px}
.toggle-pass{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:rgba(255,255,255,0.5)}
.btn{width:100%;padding:14px 0;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:12px;font-weight:800;font-size:14px;cursor:pointer;margin-top:6px;box-shadow:0 8px 24px rgba(232,93,38,0.3)}
.err{background:rgba(220,38,38,0.15);border:1px solid rgba(220,38,38,0.3);color:#FCA5A5;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
.foot-link{text-align:center;margin-top:20px;font-size:12px;color:rgba(255,255,255,0.35)}
.foot-link a{color:rgba(255,255,255,0.55);text-decoration:none;font-weight:700}
.foot-link a:hover{color:white}
</style>
</head>
<body>
<div class="card">
  <div class="icon">🛡️</div>
  <h1>Administration</h1>
  <p class="sub">Accès réservé — ServicesPlus</p>
  <?php if ($erreur): ?><div class="err">⚠️ <?= e($erreur) ?></div><?php endif; ?>
  <form method="post">
    <div class="fg"><label class="fl">Email administrateur</label><input type="email" name="email" class="fi" placeholder="admin@servicesplus.sn" value="<?= e($email) ?>" required></div>
    <div class="fg"><label class="fl">Mot de passe</label>
      <div class="iw">
        <input type="password" name="password" id="passInput" class="fi" placeholder="••••••••" required>
        <button type="button" class="toggle-pass" onclick="togglePass('passInput', this)">👁️</button>
      </div>
    </div>
    <button type="submit" class="btn">Accéder au tableau de bord →</button>
  </form>
  <div class="foot-link">Nouvel administrateur ? <a href="admin_inscription.php">Créer un accès →</a></div>
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