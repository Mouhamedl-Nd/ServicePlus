<?php
require 'config/connexion.php';
require 'config/session.php';
require 'includes/fonctions.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . urlEspace($_SESSION['role']));
    exit;
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();

$erreurs     = [];
$success     = false;
$typeCompte  = $_POST['type_compte'] ?? 'client';
$vd          = []; // valeurs déjà saisies, pour réafficher le formulaire si erreur
$stepErreur  = 2;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soumettre'])) {
    $vd = $_POST;

    $prenom     = trim($_POST['prenom'] ?? '');
    $nom        = trim($_POST['nom'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $telephone  = trim($_POST['telephone'] ?? '');
    $ville      = trim($_POST['ville'] ?? '');
    $password   = $_POST['password'] ?? '';
    $password2  = $_POST['password2'] ?? '';
    $cgu        = isset($_POST['cgu']);

    if (!$prenom || !$nom || !$email || !$telephone || !$ville) {
        $erreurs[] = 'Veuillez remplir tous les champs obligatoires.';
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
    if (!$cgu) {
        $erreurs[] = 'Vous devez accepter les conditions d\'utilisation.';
    }

    if (!$erreurs) {
        $stmt = $pdo->prepare('SELECT id, role FROM utilisateurs WHERE email = ?');
        $stmt->execute([$email]);
        $existant = $stmt->fetch();
        if ($existant) {
            $erreurs[] = "Un compte existe déjà avec l'adresse $email (rôle : {$existant['role']}). Si ce n'est pas vous, contactez le support ; sinon connectez-vous directement.";
        }
    }

    $categorieId = 0;
    $tarif = 0;
    $experience = '';
    $zone = '';
    $descriptionPresta = '';

    if ($typeCompte === 'prestataire') {
        $stepErreur = 3;
        $categorieId = (int)($_POST['categorie_id'] ?? 0);
        $tarif       = (float)($_POST['tarif'] ?? 0);
        $experience  = trim($_POST['experience'] ?? '3 à 5 ans');
        $zone        = trim($_POST['zone'] ?? '');
        $descriptionPresta = trim($_POST['description'] ?? '');

        if (!$categorieId) { $erreurs[] = 'Veuillez sélectionner votre service principal.'; }
        if ($tarif <= 0)   { $erreurs[] = 'Veuillez indiquer un tarif horaire valide.'; }
        if (!$zone)        { $erreurs[] = 'Veuillez indiquer votre zone d\'intervention.'; }
    } else {
        $stepErreur = 2;
    }

    if (!$erreurs) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare('INSERT INTO utilisateurs (role, nom, prenom, email, mot_de_passe, telephone, ville) VALUES (?,?,?,?,?,?,?)')
                ->execute([$typeCompte, $nom, $prenom, $email, $hash, $telephone, $ville]);
            $userId = (int)$pdo->lastInsertId();

            if (!empty($_FILES['photo']['name'])) {
                $photoPath = uploaderFichier($_FILES['photo'], 'profils');
                if ($photoPath) {
                    $pdo->prepare('UPDATE utilisateurs SET photo = ? WHERE id = ?')->execute([$photoPath, $userId]);
                }
            }

            if ($typeCompte === 'prestataire') {
                $piecePath = null;
                if (!empty($_FILES['piece_identite']['name'])) {
                    $piecePath = uploaderFichier($_FILES['piece_identite'], 'identite');
                }
                $bio = $descriptionPresta . ($zone ? "\nZone d'intervention : " . $zone : '');

                $pdo->prepare('INSERT INTO prestataire_profils (user_id, categorie_id, bio, tarif_horaire, experience, piece_identite) VALUES (?,?,?,?,?,?)')
                    ->execute([$userId, $categorieId, $bio, $tarif, $experience, $piecePath]);

                // Notifier tous les admins qu'un prestataire attend une validation
                $admins = $pdo->query("SELECT id FROM utilisateurs WHERE role = 'admin'")->fetchAll();
                foreach ($admins as $admin) {
                    creerNotification(
                        $pdo, $admin['id'],
                        'Nouveau prestataire à valider',
                        "$prenom $nom vient de s'inscrire comme prestataire et attend votre validation.",
                        'prestataire',
                        'gestion_prestataires.php'
                    );
                }
            }

            $pdo->commit();
            $success = true;
            $prenomAffiche = $prenom; $nomAffiche = $nom; $emailAffiche = $email; $villeAffiche = $ville;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000') {
                $erreurs[] = 'Un compte existe déjà avec cette adresse email (doublon détecté au moment de l\'enregistrement).';
            } else {
                $erreurs[] = 'Une erreur est survenue lors de la création du compte. Veuillez réessayer.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            $erreurs[] = 'Une erreur est survenue lors de la création du compte. Veuillez réessayer.';
        }
    }
}

$page_title = 'ServicesPlus – Inscription';
$extra_css  = <<<CSS
body{min-height:100vh;display:flex;flex-direction:column}
.main{flex:1;display:grid;grid-template-columns:1fr 1fr;min-height:calc(100vh - 66px)}
.left-panel{background:linear-gradient(145deg,#1A3560 0%,#2563EB 50%,#E85D26 100%);position:relative;overflow:hidden;display:flex;flex-direction:column;justify-content:center;align-items:center;padding:60px 48px}
.left-panel::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.07) 1px,transparent 1px);background-size:28px 28px}
.left-content{position:relative;text-align:center;color:white}
.left-logo{font-size:60px;margin-bottom:20px;display:block}
.left-title{font-family:'Playfair Display',serif;font-size:32px;font-weight:900;margin-bottom:12px;line-height:1.2}
.left-title em{color:#FCD34D;font-style:normal}
.left-sub{color:rgba(255,255,255,0.75);font-size:14px;line-height:1.8;margin-bottom:32px}
.avantages{display:flex;flex-direction:column;gap:14px;max-width:360px;width:100%;margin-bottom:32px}
.av-item{display:flex;align-items:center;gap:14px;background:rgba(255,255,255,0.12);border-radius:14px;padding:14px 18px;backdrop-filter:blur(8px);border:1px solid rgba(255,255,255,0.15);text-align:left}
.av-icon{font-size:24px;flex-shrink:0}
.av-title{color:white;font-weight:700;font-size:14px;margin-bottom:2px}
.av-sub{color:rgba(255,255,255,0.65);font-size:12px}
.right-panel{display:flex;align-items:center;justify-content:center;padding:40px;background:white;overflow-y:auto}
.form-wrap{width:100%;max-width:480px}
.form-header{text-align:center;margin-bottom:28px}
.form-icon{width:60px;height:60px;border-radius:18px;background:linear-gradient(135deg,#E85D26,#F59E0B);display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 14px;box-shadow:0 8px 24px rgba(232,93,38,0.3)}
.form-title{font-family:'Playfair Display',serif;font-size:26px;font-weight:900;margin-bottom:6px}
.form-sub{color:var(--slate);font-size:14px}
.steps{display:flex;align-items:center;margin-bottom:28px}
.step-item{display:flex;flex-direction:column;align-items:center;gap:5px;flex:1}
.step-circle{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;transition:all .3s}
.step-circle.done{background:#059669;color:white}
.step-circle.active{background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;box-shadow:0 4px 12px rgba(232,93,38,0.3)}
.step-circle.pending{background:#F1F5F9;color:#94A3B8}
.step-label{font-size:10px;font-weight:700;white-space:nowrap}
.step-label.done{color:#059669}.step-label.active{color:var(--primary)}.step-label.pending{color:#94A3B8}
.step-line{flex:1;height:2px;margin:0 6px;margin-bottom:18px}
.step-line.done{background:linear-gradient(to right,#E85D26,#F59E0B)}
.step-line.pending{background:#E9EEF4}
.type-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px}
.type-card{padding:20px;border-radius:16px;border:2px solid var(--border);cursor:pointer;text-align:center;transition:all .2s}
.type-card:hover{border-color:var(--primary);background:var(--pl)}
.type-card.selected{border-color:var(--primary);background:var(--pl)}
.type-icon{font-size:36px;margin-bottom:10px}
.type-title{font-weight:800;font-size:15px;margin-bottom:4px}
.type-desc{font-size:12px;color:var(--slate);line-height:1.5}
.type-check{width:22px;height:22px;border-radius:50%;border:2px solid var(--border);margin:10px auto 0;display:flex;align-items:center;justify-content:center;transition:all .2s}
.type-card.selected .type-check{background:var(--primary);border-color:var(--primary);color:white;font-size:12px;font-weight:700}
.form-section{display:none}
.form-section.active{display:block}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{margin-bottom:16px}
.form-group.full{grid-column:1/-1}
.form-label{display:block;font-size:11px;font-weight:700;color:var(--slate);text-transform:uppercase;letter-spacing:.5px;margin-bottom:7px}
.input-wrap{position:relative}
.form-input{width:100%;padding:12px 16px 12px 44px;border-radius:11px;border:1.5px solid var(--border);font-size:14px;font-family:'Inter',sans-serif;color:var(--dark);outline:none;transition:all .15s}
.form-input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(232,93,38,.1)}
.form-input.no-icon{padding-left:16px}
.input-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:16px;pointer-events:none}
.input-toggle{position:absolute;right:14px;top:50%;transform:translateY(-50%);cursor:pointer;font-size:15px;background:none;border:none;color:var(--slate)}
.pass-strength{margin-top:8px}
.strength-bar{height:4px;border-radius:20px;background:#E9EEF4;overflow:hidden;margin-bottom:4px}
.strength-fill{height:100%;border-radius:20px;transition:all .3s;width:0}
.strength-text{font-size:11px;font-weight:600}
.services-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:4px}
.svc-btn{padding:8px 6px;border-radius:10px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:11px;font-weight:600;font-family:'Inter',sans-serif;color:var(--slate);text-align:center;transition:all .15s}
.svc-btn:hover{border-color:var(--primary);color:var(--primary)}
.svc-btn.selected{border-color:var(--primary);background:var(--pl);color:var(--primary)}
.upload-zone{border:2px dashed var(--border);border-radius:14px;padding:18px 10px;text-align:center;cursor:pointer;transition:all .2s;position:relative}
.upload-zone:hover{border-color:var(--primary);background:var(--pl)}
.upload-zone input[type=file]{position:absolute;inset:0;opacity:0;cursor:pointer}
.upload-icon{font-size:30px;margin-bottom:6px}
.upload-title{font-weight:700;font-size:13px;margin-bottom:3px}
.upload-sub{font-size:11px;color:var(--slate)}
.cgu-wrap{display:flex;align-items:flex-start;gap:10px;margin-bottom:20px;cursor:pointer}
.cgu-wrap input{width:17px;height:17px;accent-color:var(--primary);cursor:pointer;margin-top:2px;flex-shrink:0}
.cgu-wrap span{font-size:13px;color:var(--slate);line-height:1.6}
.cgu-wrap a{color:var(--primary);font-weight:700;text-decoration:none}
.btn-nav{display:flex;gap:12px;margin-top:20px}
.btn-back-form{flex:1;padding:13px 0;background:#F8FAFC;color:var(--slate);border:1.5px solid var(--border);border-radius:13px;font-weight:700;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif}
.btn-next{flex:2;padding:13px 0;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:13px;font-weight:800;font-size:14px;cursor:pointer;font-family:'Inter',sans-serif;box-shadow:0 4px 16px rgba(232,93,38,0.25)}
.confirm-wrap{text-align:center;padding:20px 0}
.confirm-icon{width:80px;height:80px;border-radius:24px;background:#ECFDF5;display:flex;align-items:center;justify-content:center;font-size:40px;margin:0 auto 20px}
.confirm-title{font-family:'Playfair Display',serif;font-size:26px;font-weight:900;color:#059669;margin-bottom:10px}
.confirm-sub{color:var(--slate);font-size:14px;line-height:1.75;margin-bottom:24px}
.confirm-box{background:#F8FAFC;border-radius:14px;padding:18px;text-align:left;margin-bottom:24px}
.confirm-row{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #E9EEF4;font-size:14px}
.confirm-row:last-child{border-bottom:none}
.confirm-row span:first-child{color:var(--slate)}
.confirm-row span:last-child{font-weight:700}
.login-link{text-align:center;font-size:14px;color:var(--slate);margin-top:18px}
.login-link a{color:var(--primary);font-weight:700;text-decoration:none}
.alert-err{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;padding:12px 16px;border-radius:12px;font-size:13px;margin-bottom:16px}
@media(max-width:768px){
  .main{grid-template-columns:1fr}
  .left-panel{display:none}
  .form-grid{grid-template-columns:1fr}
  .services-grid{grid-template-columns:repeat(2,1fr)}
}
CSS;

require 'includes/header.php';
?>

<div class="main">
  <div class="left-panel">
    <div class="left-content">
      <span class="left-logo">🚀</span>
      <h1 class="left-title">Rejoignez<br/><em>ServicesPlus</em></h1>
      <p class="left-sub">Créez votre compte gratuit en moins de 2 minutes<br/>et accédez à des milliers de professionnels.</p>
      <div class="avantages">
        <div class="av-item"><span class="av-icon">🔍</span><div><div class="av-title">Trouvez rapidement</div><div class="av-sub">Des prestataires vérifiés près de chez vous</div></div></div>
        <div class="av-item"><span class="av-icon">🔒</span><div><div class="av-title">Paiement sécurisé</div><div class="av-sub">Transactions protégées via Orange Money, Wave ou espèces</div></div></div>
        <div class="av-item"><span class="av-icon">⭐</span><div><div class="av-title">Prestataires vérifiés</div><div class="av-sub">Identité contrôlée et validée par notre équipe</div></div></div>
        <div class="av-item"><span class="av-icon">🛡️</span><div><div class="av-title">Garantie satisfaction</div><div class="av-sub">Évaluations transparentes après chaque intervention</div></div></div>
      </div>
    </div>
  </div>

  <div class="right-panel">
    <div class="form-wrap">

    <?php if ($success): ?>
      <!-- ══ CONFIRMATION (vraies données) ══ -->
      <div class="confirm-wrap">
        <div class="confirm-icon">🎉</div>
        <div class="confirm-title">Compte créé avec succès !</div>
        <div class="confirm-sub">Bienvenue sur <strong>ServicesPlus</strong>, <?= e($prenomAffiche) ?> !</div>
        <div class="confirm-box">
          <div class="confirm-row"><span>Nom complet</span><span><?= e($prenomAffiche . ' ' . $nomAffiche) ?></span></div>
          <div class="confirm-row"><span>Email</span><span><?= e($emailAffiche) ?></span></div>
          <div class="confirm-row"><span>Ville</span><span><?= e($villeAffiche) ?></span></div>
          <div class="confirm-row"><span>Type de compte</span><span><?= $typeCompte === 'prestataire' ? '🔧 Prestataire' : '👤 Client' ?></span></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
          <a href="connexion.php" style="padding:13px 0;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:12px;font-weight:800;font-size:14px;text-decoration:none;">Se connecter →</a>
          <a href="index.php" style="padding:13px 0;background:#F8FAFC;color:var(--slate);border:1.5px solid var(--border);border-radius:12px;font-weight:700;font-size:14px;text-decoration:none;">Accueil</a>
        </div>
        <?php if ($typeCompte === 'prestataire'): ?>
          <div style="background:#FFF0EB;border-radius:12px;padding:14px 16px;font-size:13px;color:#7C3C00;text-align:left;">
            🔧 <strong>Prestataire :</strong> Votre dossier est en cours de validation par notre équipe. Vous pourrez vous connecter dès que l'admin aura validé votre compte.
          </div>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <div class="form-header">
        <div class="form-icon">✨</div>
        <h2 class="form-title">Créer un compte</h2>
        <p class="form-sub">C'est gratuit et rapide !</p>
      </div>

      <div class="steps" id="stepsBar"></div>

      <?php if ($erreurs): ?>
        <div class="alert-err">❌ <?= e(implode(' ', $erreurs)) ?></div>
      <?php endif; ?>

      <form method="post" action="inscription.php" enctype="multipart/form-data" id="formInscription">
        <input type="hidden" name="type_compte" id="typeCompteInput" value="<?= e($typeCompte) ?>">

        <!-- ÉTAPE 1 : TYPE DE COMPTE -->
        <div class="form-section" id="sec1">
          <div style="font-weight:700;font-size:15px;margin-bottom:16px;">Quel type de compte souhaitez-vous créer ?</div>
          <div class="type-grid">
            <div class="type-card" onclick="selectType('client',this)" id="typeClient">
              <div class="type-icon">👤</div><div class="type-title">Client</div>
              <div class="type-desc">Je cherche des prestataires pour mes besoins à domicile</div>
              <div class="type-check">✓</div>
            </div>
            <div class="type-card" onclick="selectType('prestataire',this)" id="typePresta">
              <div class="type-icon">🔧</div><div class="type-title">Prestataire</div>
              <div class="type-desc">Je propose mes services professionnels à des clients</div>
              <div class="type-check"></div>
            </div>
          </div>
          <div id="typeInfo" style="background:var(--pl);border-radius:12px;padding:14px;font-size:13px;color:#7C3C00;margin-bottom:8px;"></div>
          <div class="btn-nav" style="margin-top:16px;">
            <button type="button" class="btn-next" style="flex:1;" onclick="goStep(2)">Continuer →</button>
          </div>
          <div class="login-link">Déjà un compte ? <a href="connexion.php">Se connecter</a></div>
        </div>

        <!-- ÉTAPE 2 : INFOS PERSONNELLES -->
        <div class="form-section" id="sec2">
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Prénom *</label>
              <div class="input-wrap"><span class="input-icon">👤</span>
                <input type="text" class="form-input" name="prenom" placeholder="Mohammed" value="<?= e($vd['prenom'] ?? '') ?>" required></div>
            </div>
            <div class="form-group">
              <label class="form-label">Nom *</label>
              <div class="input-wrap"><span class="input-icon">👤</span>
                <input type="text" class="form-input" name="nom" placeholder="Dia" value="<?= e($vd['nom'] ?? '') ?>" required></div>
            </div>
            <div class="form-group full">
              <label class="form-label">Adresse email *</label>
              <div class="input-wrap"><span class="input-icon">📧</span>
                <input type="email" class="form-input" name="email" placeholder="votre@email.com" value="<?= e($vd['email'] ?? '') ?>" required></div>
            </div>
            <div class="form-group full">
              <label class="form-label">Numéro de téléphone *</label>
              <div class="input-wrap"><span class="input-icon">📞</span>
                <input type="tel" class="form-input" name="telephone" placeholder="+221 77 000 00 00" value="<?= e($vd['telephone'] ?? '') ?>" required></div>
            </div>
            <div class="form-group full">
              <label class="form-label">Ville *</label>
              <div class="input-wrap"><span class="input-icon">📍</span>
                <select class="form-input" name="ville" required>
                  <option value="">Sélectionnez votre ville</option>
                  <?php foreach (['Dakar','Thiès','Saint-Louis','Ziguinchor','Kaolack','Rufisque'] as $v): ?>
                    <option <?= (($vd['ville'] ?? '') === $v) ? 'selected' : '' ?>><?= $v ?></option>
                  <?php endforeach; ?>
                </select></div>
            </div>
            <div class="form-group full">
              <label class="form-label">Photo de profil (optionnel)</label>
              <div class="upload-zone"><input type="file" name="photo" accept="image/*">
                <div class="upload-icon">📸</div><div class="upload-title">Photo de profil</div><div class="upload-sub">JPG, PNG ou WEBP</div>
              </div>
            </div>
            <div class="form-group full">
              <label class="form-label">Mot de passe *</label>
              <div class="input-wrap"><span class="input-icon">🔒</span>
                <input type="password" class="form-input" id="password" name="password" placeholder="Minimum 8 caractères" oninput="checkStrength(this.value)" required>
                <button type="button" class="input-toggle" onclick="togglePass('password',this)">👁️</button></div>
              <div class="pass-strength"><div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div><span class="strength-text" id="strengthText"></span></div>
            </div>
            <div class="form-group full">
              <label class="form-label">Confirmer le mot de passe *</label>
              <div class="input-wrap"><span class="input-icon">🔒</span>
                <input type="password" class="form-input" id="password2" name="password2" placeholder="Retapez votre mot de passe" required>
                <button type="button" class="input-toggle" onclick="togglePass('password2',this)">👁️</button></div>
            </div>
          </div>
          <div class="btn-nav">
            <button type="button" class="btn-back-form" onclick="goStep(1)">← Retour</button>
            <button type="button" class="btn-next" id="btnContinueStep2" onclick="continuerDepuisEtape2()">Continuer →</button>
          </div>
        </div>

        <!-- ÉTAPE 3 : INFOS PRESTATAIRE -->
        <div class="form-section" id="sec3">
          <div style="font-weight:800;font-size:15px;margin-bottom:16px;">🔧 Informations professionnelles</div>
          <div class="form-group">
            <label class="form-label">Sélectionnez votre service principal *</label>
            <input type="hidden" name="categorie_id" id="categorieIdInput" value="<?= e($vd['categorie_id'] ?? '') ?>">
            <div class="services-grid" id="servicesGrid">
              <?php foreach ($categories as $cat): ?>
                <div class="svc-btn <?= (($vd['categorie_id'] ?? '') == $cat['id']) ? 'selected' : '' ?>" onclick="selectService(<?= $cat['id'] ?>,this)">
                  <div style="font-size:20px;margin-bottom:4px;"><?= $cat['icone'] ?></div><div><?= e($cat['nom']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-grid" style="margin-top:4px;">
            <div class="form-group">
              <label class="form-label">Tarif horaire (FCFA) *</label>
              <div class="input-wrap"><span class="input-icon">💰</span>
                <input type="number" class="form-input" name="tarif" placeholder="Ex: 1500" value="<?= e($vd['tarif'] ?? '') ?>"></div>
            </div>
            <div class="form-group">
              <label class="form-label">Années d'expérience *</label>
              <div class="input-wrap"><span class="input-icon">📅</span>
                <select class="form-input" name="experience">
                  <?php foreach (['Moins de 1 an','1 à 3 ans','3 à 5 ans','5 à 10 ans','Plus de 10 ans'] as $ex): ?>
                    <option <?= (($vd['experience'] ?? '3 à 5 ans') === $ex) ? 'selected' : '' ?>><?= $ex ?></option>
                  <?php endforeach; ?>
                </select></div>
            </div>
            <div class="form-group full">
              <label class="form-label">Zone d'intervention *</label>
              <div class="input-wrap"><span class="input-icon">📍</span>
                <input type="text" class="form-input" name="zone" placeholder="Ex: Dakar, Pikine, Guédiawaye (rayon 25 km)" value="<?= e($vd['zone'] ?? '') ?>"></div>
            </div>
            <div class="form-group full">
              <label class="form-label">Description de vos services</label>
              <textarea class="form-input no-icon" name="description" rows="3" style="padding:12px 16px;resize:none;" placeholder="Décrivez vos compétences et spécialités..."><?= e($vd['description'] ?? '') ?></textarea>
            </div>
          </div>

          <div style="font-weight:800;font-size:14px;margin:16px 0 12px;">📄 Documents</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
            <div class="upload-zone"><input type="file" name="piece_identite" accept="image/*,.pdf">
              <div class="upload-icon">🪪</div><div class="upload-title">Pièce d'identité *</div><div class="upload-sub">CNI ou Passeport</div></div>
            <div class="upload-zone" style="opacity:.55;cursor:not-allowed;" title="Fonctionnalité à venir">
              <div class="upload-icon">🎓</div><div class="upload-title">Diplôme/Certificat</div><div class="upload-sub">Bientôt disponible</div></div>
          </div>

          <div style="background:#FFF0EB;border-radius:12px;padding:12px 16px;font-size:12px;color:#7C3C00;margin-bottom:8px;">
            ℹ️ Votre dossier sera examiné par notre équipe. Vous ne pourrez vous connecter qu'après validation par l'administrateur.
          </div>

          <label class="cgu-wrap">
            <input type="checkbox" name="cgu" <?= isset($vd['cgu']) ? 'checked' : '' ?>>
            <span>J'accepte les <a href="#">Conditions d'utilisation</a> et la <a href="#">Politique de confidentialité</a> de ServicesPlus</span>
          </label>

          <div class="btn-nav">
            <button type="button" class="btn-back-form" onclick="goStep(2)">← Retour</button>
            <button type="submit" name="soumettre" value="1" class="btn-next">🚀 Créer mon compte →</button>
          </div>
        </div>

        <!-- CGU pour le parcours client (pas d'étape 3) -->
        <div id="cguClientWrap" style="display:none;">
          <label class="cgu-wrap">
            <input type="checkbox" name="cgu_client">
            <span>J'accepte les <a href="#">Conditions d'utilisation</a> et la <a href="#">Politique de confidentialité</a> de ServicesPlus</span>
          </label>
          <button type="submit" name="soumettre" value="1" class="btn-next" style="width:100%;">🚀 Créer mon compte →</button>
        </div>
      </form>
    <?php endif; ?>

    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  const STEPS = {
    client:      [{n:1,lbl:"Type"},{n:2,lbl:"Infos"},{n:4,lbl:"Confirmation"}],
    prestataire: [{n:1,lbl:"Type"},{n:2,lbl:"Infos"},{n:3,lbl:"Services"},{n:4,lbl:"Confirmation"}],
  };
  let typeActif   = document.getElementById('typeCompteInput')?.value || 'client';
  let currentStep = <?= ($erreurs ? $stepErreur : 1) ?>;

  window.onload = () => {
    selectType(typeActif, document.getElementById(typeActif === 'prestataire' ? 'typePresta' : 'typeClient'));
    goStep(currentStep);
  };

  function renderSteps() {
    const steps = STEPS[typeActif];
    document.getElementById('stepsBar').innerHTML = steps.map((s, i) => {
      const state = s.n < currentStep ? 'done' : (s.n === currentStep ? 'active' : 'pending');
      const line = i < steps.length - 1 ? `<div class="step-line ${s.n < currentStep ? 'done' : 'pending'}"></div>` : '';
      return `<div class="step-item"><div class="step-circle ${state}">${s.n < currentStep ? '✓' : s.n}</div><div class="step-label ${state}">${s.lbl}</div></div>${line}`;
    }).join('');
  }

  function goStep(n) {
    currentStep = n;
    document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
    document.getElementById('sec' + n)?.classList.add('active');
    document.getElementById('cguClientWrap').style.display = (typeActif === 'client' && n === 2) ? 'none' : 'none';
    renderSteps();
    window.scrollTo({top: 0, behavior: 'smooth'});
  }

  function continuerDepuisEtape2() {
    const requis = ['prenom','nom','email','telephone','ville','password','password2'];
    for (const name of requis) {
      const el = document.querySelector(`[name="${name}"]`);
      if (!el.value.trim()) { T('⚠️ Veuillez remplir tous les champs obligatoires.'); el.focus(); return; }
    }
    const pass = document.getElementById('password').value;
    const pass2 = document.getElementById('password2').value;
    if (pass.length < 8) { T('⚠️ Mot de passe trop court (8 caractères min).'); return; }
    if (pass !== pass2)  { T('⚠️ Les mots de passe ne correspondent pas.'); return; }

    if (typeActif === 'prestataire') {
      goStep(3);
    } else {
      // Compte client : on demande juste l'acceptation des CGU puis on soumet le vrai formulaire
      const form = document.getElementById('formInscription');
      const cguOk = confirm("J'accepte les conditions d'utilisation et la politique de confidentialité de ServicesPlus.\n\nCliquez sur OK pour créer votre compte.");
      if (!cguOk) return;
      const hidden = document.createElement('input');
      hidden.type = 'hidden'; hidden.name = 'cgu'; hidden.value = '1';
      form.appendChild(hidden);
      const submitBtn = document.createElement('input');
      submitBtn.type = 'hidden'; submitBtn.name = 'soumettre'; submitBtn.value = '1';
      form.appendChild(submitBtn);
      form.submit();
    }
  }

  function selectType(type, el) {
    typeActif = type;
    document.getElementById('typeCompteInput').value = type;
    document.querySelectorAll('.type-card').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    const info = {
      client: '👤 <strong>Compte Client :</strong> Trouvez et réservez des prestataires qualifiés en quelques clics.',
      prestataire: '🔧 <strong>Compte Prestataire :</strong> Publiez votre profil et répondez aux demandes des clients près de chez vous.',
    };
    document.getElementById('typeInfo').innerHTML = info[type];
    renderSteps();
  }

  function selectService(id, el) {
    document.querySelectorAll('.svc-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('categorieIdInput').value = id;
  }

  function togglePass(id, btn) {
    const input = document.getElementById(id);
    const visible = input.type === 'text';
    input.type = visible ? 'password' : 'text';
    btn.textContent = visible ? '👁️' : '🙈';
  }

  function checkStrength(val) {
    const fill = document.getElementById('strengthFill');
    const text = document.getElementById('strengthText');
    let score = 0;
    if (val.length >= 8) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = [{pct:0,color:'#E9EEF4',txt:''},{pct:25,color:'#DC2626',txt:'Faible'},{pct:50,color:'#D97706',txt:'Moyen'},{pct:75,color:'#2563EB',txt:'Bon'},{pct:100,color:'#059669',txt:'Excellent'}];
    const lvl = levels[score] || levels[0];
    fill.style.width = lvl.pct + '%'; fill.style.background = lvl.color;
    text.textContent = lvl.txt; text.style.color = lvl.color;
  }

  function T(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
  }
</script>

<?php require 'includes/footer.php'; ?>