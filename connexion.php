<?php
require 'config/connexion.php';
require 'config/session.php';
require 'includes/fonctions.php';

// Si déjà connecté, on renvoie directement vers son espace
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . urlEspace($_SESSION['role']));
    exit;
}

$erreur       = '';
$emailSaisi   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_connexion'])) {
    $emailSaisi = trim($_POST['email'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (!$emailSaisi || !str_contains($emailSaisi, '@')) {
        $erreur = 'Veuillez entrer une adresse email valide.';
    } elseif (strlen($password) < 6) {
        $erreur = 'Le mot de passe doit contenir au moins 6 caractères.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE email = ?');
        $stmt->execute([$emailSaisi]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['mot_de_passe'])) {
            $erreur = 'Email ou mot de passe incorrect.';
        } elseif ($user['statut'] === 'suspendu') {
            $erreur = 'Votre compte a été suspendu. Contactez l\'administrateur.';
        } else {
            $statutValidation = null;

            if ($user['role'] === 'prestataire') {
                $stmt2 = $pdo->prepare('SELECT statut_validation FROM prestataire_profils WHERE user_id = ?');
                $stmt2->execute([$user['id']]);
                $statutValidation = $stmt2->fetchColumn();

                if ($statutValidation === 'en_attente') {
                    $erreur = 'Votre compte prestataire est en attente de validation par l\'administrateur.';
                } elseif ($statutValidation === 'refuse') {
                    $erreur = 'Votre inscription prestataire a été refusée. Contactez l\'administrateur.';
                }
            }

            if (!$erreur) {
                $_SESSION['user_id']           = $user['id'];
                $_SESSION['nom']               = $user['nom'];
                $_SESSION['prenom']             = $user['prenom'];
                $_SESSION['role']               = $user['role'];
                $_SESSION['photo']              = $user['photo'];
                $_SESSION['statut_validation']  = $statutValidation;

                header('Location: ' . urlEspace($user['role']));
                exit;
            }
        }
    }
}

/* ── Stats dynamiques du panneau gauche (mêmes données que l'accueil) ── */
$nbPrestataires = $pdo->query("SELECT COUNT(*) FROM prestataire_profils WHERE statut_validation = 'valide'")->fetchColumn();
$nbClients      = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE role = 'client'")->fetchColumn();
$nbMissions     = $pdo->query("SELECT COUNT(*) FROM reservations WHERE statut = 'terminee'")->fetchColumn();
$noteGlobale    = $pdo->query("SELECT AVG(note_moyenne) FROM prestataire_profils WHERE statut_validation='valide' AND nb_avis > 0")->fetchColumn();
$noteGlobale    = $noteGlobale ? round($noteGlobale, 1) : 5.0;

/* ── Un témoignage réel (dernier avis 5 étoiles), sinon message par défaut ── */
$temoignage = $pdo->query("
    SELECT a.commentaire, cu.prenom, cu.ville
    FROM avis a JOIN utilisateurs cu ON cu.id = a.client_id
    WHERE a.note = 5 ORDER BY a.date_creation DESC LIMIT 1
")->fetch();

$page_title = 'ServicesPlus – Connexion';
$extra_css  = <<<CSS
body{min-height:100vh;display:flex;flex-direction:column}
.nav-r{margin-left:auto}
.main{flex:1;display:grid;grid-template-columns:1fr 1fr;min-height:calc(100vh - 66px)}
.left-panel{background:linear-gradient(145deg,#1A3560 0%,#2563EB 50%,#E85D26 100%);position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:60px 48px}
.left-panel::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.07) 1px,transparent 1px);background-size:28px 28px}
.left-content{position:relative;text-align:center;color:white}
.left-logo{font-size:64px;margin-bottom:24px;display:block}
.left-title{font-family:'Playfair Display',serif;font-size:36px;font-weight:900;margin-bottom:14px;line-height:1.2}
.left-title em{color:#FCD34D;font-style:normal}
.left-sub{color:rgba(255,255,255,0.75);font-size:15px;line-height:1.8;margin-bottom:36px}
.left-stats{display:grid;grid-template-columns:1fr 1fr;gap:12px;width:100%;max-width:340px}
.stat-box{background:rgba(255,255,255,0.12);border-radius:16px;padding:16px;text-align:center;backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.2)}
.stat-val{font-size:22px;font-weight:900;margin-bottom:3px}
.stat-lbl{font-size:11px;color:rgba(255,255,255,0.7);font-weight:600}
.testimonial{margin-top:32px;background:rgba(255,255,255,0.1);border-radius:16px;padding:20px 24px;max-width:380px;backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.15)}
.test-text{font-size:14px;color:rgba(255,255,255,0.9);font-style:italic;line-height:1.7;margin-bottom:12px}
.test-author{display:flex;align-items:center;gap:10px}
.test-av{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#E85D26,#F59E0B);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:15px}
.test-name{font-size:13px;font-weight:700;color:white}
.test-role{font-size:11px;color:rgba(255,255,255,0.6)}
.right-panel{display:flex;align-items:center;justify-content:center;padding:60px 40px;background:white}
.form-wrap{width:100%;max-width:420px}
.form-header{text-align:center;margin-bottom:32px}
.form-icon{width:64px;height:64px;border-radius:20px;background:linear-gradient(135deg,#E85D26,#F59E0B);display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 16px;box-shadow:0 8px 24px rgba(232,93,38,0.3)}
.form-title{font-family:'Playfair Display',serif;font-size:28px;font-weight:900;margin-bottom:6px}
.form-sub{color:var(--slate);font-size:14px}
.form-group{margin-bottom:18px}
.form-label{display:block;font-size:11px;font-weight:700;color:var(--slate);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.input-wrap{position:relative}
.form-input{width:100%;padding:13px 16px 13px 44px;border-radius:12px;border:1.5px solid var(--border);font-size:14px;font-family:'Inter',sans-serif;color:var(--dark);outline:none;transition:all .15s;background:white}
.form-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(232,93,38,.1)}
.form-input.error{border-color:#DC2626;box-shadow:0 0 0 3px rgba(220,38,38,.1)}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:17px;pointer-events:none}
.input-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:16px;background:none;border:none;color:var(--slate)}
.form-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:22px}
.checkbox-wrap{display:flex;align-items:center;gap:8px;cursor:pointer}
.checkbox-wrap input{width:17px;height:17px;accent-color:var(--primary);cursor:pointer}
.checkbox-wrap span{font-size:13px;color:var(--slate);font-weight:500}
.forgot-link{font-size:13px;color:var(--primary);font-weight:700;text-decoration:none;cursor:pointer;background:none;border:none}
.forgot-link:hover{text-decoration:underline}
.btn-login{width:100%;padding:15px 0;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:13px;font-weight:800;font-size:15px;cursor:pointer;font-family:'Inter',sans-serif;transition:all .2s;box-shadow:0 4px 16px rgba(232,93,38,0.3);margin-bottom:16px}
.btn-login:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(232,93,38,0.4)}
.separator{position:relative;margin:20px 0}
.separator::before{content:'';position:absolute;top:50%;left:0;right:0;height:1px;background:var(--border)}
.sep-text{position:relative;background:white;padding:0 14px;font-size:12px;color:#94A3B8;display:block;text-align:center;width:fit-content;margin:0 auto}
.social-btns{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:24px}
.social-btn{padding:12px 0;border-radius:12px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:13px;font-weight:700;font-family:'Inter',sans-serif;color:var(--dark);display:flex;align-items:center;justify-content:center;gap:8px;transition:all .15s}
.social-btn:hover{border-color:var(--primary);background:var(--pl)}
.register-link{text-align:center;font-size:14px;color:var(--slate)}
.register-link a{color:var(--primary);font-weight:700;text-decoration:none}
.alert{padding:13px 16px;border-radius:12px;font-size:13px;font-weight:600;margin-bottom:18px;display:none;align-items:center;gap:10px}
.alert.show{display:flex}
.alert.success{background:#ECFDF5;color:#059669;border:1px solid #A7F3D0}
.alert.error{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA}
.alert.info{background:#EFF6FF;color:#2563EB;border:1px solid #BFDBFE}
.toast{position:fixed;bottom:28px;left:50%;transform:translateX(-50%) translateY(20px);background:#0F172A;color:white;padding:12px 24px;border-radius:14px;font-size:14px;font-weight:600;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,.2);transition:all .3s;opacity:0;pointer-events:none}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
@media(max-width:768px){
  .main{grid-template-columns:1fr}
  .left-panel{display:none}
  .right-panel{padding:40px 24px}
}
CSS;

require 'includes/header.php';
?>

<div class="main">
  <div class="left-panel">
    <div class="left-content">
      <span class="left-logo">🏠</span>
      <h1 class="left-title">Bienvenue sur<br/><em>ServicesPlus</em></h1>
      <p class="left-sub">La plateforme N°1 au Sénégal pour trouver<br/>des professionnels qualifiés près de chez vous.</p>

      <div class="left-stats">
        <div class="stat-box"><div class="stat-val"><?= number_format($nbPrestataires,0,',',' ') ?>+</div><div class="stat-lbl">Prestataires</div></div>
        <div class="stat-box"><div class="stat-val"><?= number_format($nbClients,0,',',' ') ?>+</div><div class="stat-lbl">Clients satisfaits</div></div>
        <div class="stat-box"><div class="stat-val"><?= e((string)$noteGlobale) ?> ★</div><div class="stat-lbl">Note moyenne</div></div>
        <div class="stat-box"><div class="stat-val"><?= number_format($nbMissions,0,',',' ') ?>+</div><div class="stat-lbl">Missions réalisées</div></div>
      </div>

      <?php if ($temoignage): ?>
        <div class="testimonial">
          <p class="test-text">"<?= e($temoignage['commentaire']) ?>"</p>
          <div class="test-author">
            <div class="test-av"><?= strtoupper(substr($temoignage['prenom'],0,1)) ?></div>
            <div>
              <div class="test-name"><?= e($temoignage['prenom']) ?></div>
              <div class="test-role">Client — <?= e($temoignage['ville'] ?? 'Sénégal') ?></div>
            </div>
          </div>
        </div>
      <?php else: ?>
        <div class="testimonial">
          <p class="test-text">"ServicesPlus m'a permis de trouver un plombier en moins de 30 minutes. Service impeccable !"</p>
          <div class="test-author">
            <div class="test-av">S</div>
            <div><div class="test-name">Un client ServicesPlus</div><div class="test-role">Dakar</div></div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="right-panel">
    <div class="form-wrap">
      <div class="form-header">
        <div class="form-icon">🔐</div>
        <h2 class="form-title">Se connecter</h2>
        <p class="form-sub">Accédez à votre espace personnel</p>
      </div>

      <div class="alert info show" id="alertInfo">
        ℹ️ <span>Le même formulaire sert aux clients, prestataires et admin — votre espace est reconnu automatiquement.</span>
      </div>
      <?php if ($erreur): ?>
        <div class="alert error show">❌ <span><?= e($erreur) ?></span></div>
      <?php endif; ?>

      <form method="post" action="connexion.php" id="mainForm">
        <input type="hidden" name="action_connexion" value="1">
        <div class="form-group">
          <label class="form-label">Adresse email</label>
          <div class="input-wrap">
            <span class="input-icon">📧</span>
            <input type="email" name="email" class="form-input" placeholder="votre@email.com" value="<?= e($emailSaisi) ?>" required/>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Mot de passe</label>
          <div class="input-wrap">
            <span class="input-icon">🔒</span>
            <input type="password" name="password" class="form-input" id="passInput" placeholder="••••••••" required/>
            <button type="button" class="input-toggle" onclick="togglePass()" id="passToggle">👁️</button>
          </div>
        </div>

        <div class="form-row">
          <label class="checkbox-wrap">
            <input type="checkbox" name="remember" checked/>
            <span>Se souvenir de moi</span>
          </label>
          <button type="button" class="forgot-link" onclick="T('📧 Contactez l\'administrateur pour réinitialiser votre mot de passe.')">Mot de passe oublié ?</button>
        </div>

        <button type="submit" class="btn-login">Se connecter →</button>

        <div class="separator"><span class="sep-text">ou continuer avec</span></div>
        <div class="social-btns">
          <button type="button" class="social-btn" onclick="T('⚠️ Connexion Orange Money — bientôt disponible.')">📱 Orange Money</button>
          <button type="button" class="social-btn" onclick="T('⚠️ Connexion Wave — bientôt disponible.')">🌊 Wave</button>
        </div>

        <div class="register-link">
          Pas encore de compte ? <a href="inscription.php">S'inscrire gratuitement →</a>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  function togglePass() {
    const input = document.getElementById('passInput');
    const btn   = document.getElementById('passToggle');
    const visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    btn.textContent = visible ? '👁️' : '🙈';
  }
  function T(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
  }
</script>

<?php require 'includes/footer.php'; ?>