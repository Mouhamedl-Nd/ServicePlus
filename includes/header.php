<?php
/**
 * Header commun à toutes les pages.
 * Variables à définir AVANT d'inclure ce fichier :
 *   $page_title   (string)            → titre de l'onglet, ex: 'ServicesPlus – Accueil'
 *   $page_active  (string)            → identifiant du lien nav actif, ex: 'accueil'
 *   $racine       (string, optionnel) → '../' si le fichier est dans un sous-dossier, '' sinon
 *   $extra_css    (string, optionnel) → CSS spécifique à la page (contenu du <style> d'origine)
 */

$racine      = $racine ?? '';
$page_title  = $page_title ?? 'ServicesPlus';
$page_active = $page_active ?? '';
$extra_css   = $extra_css ?? '';

$utilisateur = utilisateurConnecte();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($page_title) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --primary: #E85D26; --primary-light: #FFF0EB; --gold: #F59E0B;
  --dark: #0F172A; --slate: #64748B; --border: #E9EEF4; --bg: #F6F8FB;
  --white: #ffffff; --green: #059669; --blue: #2563EB;
}
body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--dark); }

/* NAVBAR (identique aux 13 maquettes) */
nav { position: sticky; top: 0; z-index: 999; background: rgba(255,255,255,0.97); backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border); padding: 0 40px; height: 66px; display: flex; align-items: center; gap: 8px;
  box-shadow: 0 1px 6px rgba(0,0,0,0.06); }
.logo { display:flex; align-items:center; gap:10px; text-decoration:none; margin-right:24px; }
.logo-icon { width:38px; height:38px; border-radius:11px; background:linear-gradient(135deg,#E85D26,#F59E0B);
  display:flex; align-items:center; justify-content:center; font-size:19px; box-shadow:0 3px 10px rgba(232,93,38,0.3); }
.logo-text { font-family:'Playfair Display',serif; font-size:20px; font-weight:900; }
.logo-text span:first-child { color:var(--primary); }
.logo-text span:last-child  { color:var(--dark); }
.nav-links { display:flex; gap:4px; flex:1; }
.nav-link { padding:7px 14px; border-radius:9px; border:none; cursor:pointer; font-size:13px; font-weight:600;
  font-family:'Inter',sans-serif; background:transparent; color:var(--slate); transition:all 0.15s;
  text-decoration:none; display:inline-flex; align-items:center; }
.nav-link:hover, .nav-link.active { background:var(--primary-light); color:var(--primary); }
.nav-actions { display:flex; align-items:center; gap:10px; padding-left:16px; border-left:1px solid var(--border); }
.btn-login { padding:8px 18px; background:transparent; color:var(--slate); border:1.5px solid var(--border);
  border-radius:10px; font-weight:600; cursor:pointer; font-size:13px; transition:all 0.15s; text-decoration:none; }
.btn-login:hover { border-color:var(--primary); color:var(--primary); }
.btn-register { padding:8px 18px; background:linear-gradient(135deg,#E85D26,#F59E0B); color:white; border:none;
  border-radius:10px; font-weight:700; cursor:pointer; font-size:13px; transition:opacity 0.15s; text-decoration:none; }
.btn-register:hover { opacity:0.88; }
.nav-user { display:flex; align-items:center; gap:10px; font-size:13px; font-weight:700; color:var(--dark); text-decoration:none; }
.nav-user-avatar { width:32px; height:32px; border-radius:50%; background:linear-gradient(135deg,#E85D26,#F59E0B);
  display:flex; align-items:center; justify-content:center; color:white; font-weight:800; font-size:13px; }

/* FOOTER (identique aux 13 maquettes) */
footer { background:var(--dark); color:#94A3B8; margin-top:40px; }
.footer-inner { max-width:1200px; margin:0 auto; padding:48px 40px 20px; }
.footer-grid { display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:32px; margin-bottom:28px; }
.footer-logo { display:flex; align-items:center; gap:10px; margin-bottom:14px; }
.footer-logo-icon { width:34px; height:34px; border-radius:10px; background:linear-gradient(135deg,#E85D26,#F59E0B);
  display:flex; align-items:center; justify-content:center; font-size:16px; }
.footer-logo-text { font-family:'Playfair Display',serif; font-size:18px; font-weight:900; color:white; }
.footer-logo-text span { color:var(--primary); }
.footer-desc { font-size:13px; line-height:1.7; max-width:320px; margin-bottom:10px; }
.footer-credit { font-size:11px; color:#64748B; }
.footer-col h4 { color:white; font-size:13px; margin-bottom:14px; }
.footer-col ul { list-style:none; }
.footer-col li { font-size:13px; margin-bottom:10px; cursor:pointer; }
.footer-col li:hover { color:white; }
.footer-bottom { display:flex; justify-content:space-between; padding-top:20px; border-top:1px solid rgba(255,255,255,0.08); font-size:12px; }
.footer-bottom b { color:var(--primary); }

<?= $extra_css ?>
</style>
</head>
<body>
<nav>
  <a href="<?= $racine ?>index.php" class="logo" id="logoLink" onclick="return logoClick(event)">
    <div class="logo-icon">🏠</div>
    <div class="logo-text"><span>Services</span><span>Plus</span></div>
  </a>
  <div class="nav-links">
    <a href="<?= $racine ?>index.php" class="nav-link <?= $page_active==='accueil'?'active':'' ?>">Accueil</a>
    <a href="<?= $racine ?>recherche.php" class="nav-link <?= $page_active==='recherche'?'active':'' ?>">Recherche</a>
<?php if (!$utilisateur): ?>
    <a href="<?= $racine ?>faq.php" class="nav-link <?= $page_active==='faq'?'active':'' ?>">FAQ</a>
    <a href="<?= $racine ?>contact.php" class="nav-link <?= $page_active==='contact'?'active':'' ?>">Contact</a>
<?php elseif ($utilisateur['role'] === 'client'): ?>
    <a href="<?= $racine ?>espace_client/dashboard_client.php" class="nav-link <?= $page_active==='dashboard'?'active':'' ?>">Dashboard</a>
    <a href="<?= $racine ?>espace_client/publier_annonce.php" class="nav-link <?= $page_active==='publier'?'active':'' ?>">Publier une annonce</a>
    <a href="<?= $racine ?>espace_client/notifications.php" class="nav-link <?= $page_active==='notifications'?'active':'' ?>">Notifications</a>
<?php elseif ($utilisateur['role'] === 'prestataire'): ?>
    <a href="<?= $racine ?>espace_prestataire/dashboard_prestataire.php" class="nav-link <?= $page_active==='dashboard'?'active':'' ?>">Dashboard</a>
    <a href="<?= $racine ?>espace_prestataire/notifications.php" class="nav-link <?= $page_active==='notifications'?'active':'' ?>">Notifications</a>
<?php elseif ($utilisateur['role'] === 'admin'): ?>
    <a href="<?= $racine ?>espace_admin/admin.php" class="nav-link <?= $page_active==='dashboard'?'active':'' ?>">Dashboard</a>
    <a href="<?= $racine ?>espace_admin/gestion_prestataires.php" class="nav-link <?= $page_active==='prestataires'?'active':'' ?>">Prestataires</a>
    <a href="<?= $racine ?>espace_admin/gestion_clients.php" class="nav-link <?= $page_active==='clients'?'active':'' ?>">Clients</a>
    <a href="<?= $racine ?>espace_admin/gestion_annonces.php" class="nav-link <?= $page_active==='annonces'?'active':'' ?>">Annonces</a>
<?php endif; ?>
  </div>
  <div class="nav-actions">
<?php if (!$utilisateur): ?>
    <a href="<?= $racine ?>connexion.php" class="btn-login">Connexion</a>
    <a href="<?= $racine ?>inscription.php" class="btn-register">S'inscrire</a>
<?php else: ?>
    <a href="<?= $racine ?><?= $utilisateur['role']==='admin' ? '' : ($utilisateur['role']==='client' ? 'espace_client/' : 'espace_prestataire/') ?>mon_profil.php" class="nav-user">
      <span class="nav-user-avatar"><?= strtoupper(substr($utilisateur['prenom'],0,1)) ?></span>
      <?= e($utilisateur['prenom']) ?>
    </a>
    <a href="<?= $racine ?>deconnexion.php" class="btn-login">Déconnexion</a>
<?php endif; ?>
  </div>
</nav>

<script>
  // Triple-clic sur le logo -> accès discret à la connexion admin
  let logoClicks = 0, logoTimer = null;
  function logoClick(e) {
    e.preventDefault();
    logoClicks++;
    if (logoTimer) clearTimeout(logoTimer);
    if (logoClicks >= 3) {
      logoClicks = 0;
      window.location.href = '<?= $racine ?>espace_admin/admin_connexion.php';
      return false;
    }
    logoTimer = setTimeout(() => {
      logoClicks = 0;
      window.location.href = e.currentTarget.href;
    }, 500);
    return false;
  }
</script>