<?php
/**
 * Layout commun à toutes les pages de l'espace admin (sidebar + topbar).
 * Variables à définir avant d'inclure ce fichier :
 *   $page_title   (string)
 *   $page_active  (string) identifiant du lien sidebar actif : dashboard|prestataires|clients|annonces|messages|faq|notifications|profil
 *   $extra_css    (string, optionnel) CSS spécifique à la page
 */
$admin = utilisateurConnecte();
$page_active = $page_active ?? '';
$extra_css   = $extra_css ?? '';

$nbNotifsAdmin = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND lu = 0');
$nbNotifsAdmin->execute([$admin['id']]);
$nbNotifsAdmin = (int)$nbNotifsAdmin->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title><?= e($page_title ?? 'ServicesPlus – Admin') ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--primary:#E85D26;--pl:#FFF0EB;--gold:#F59E0B;--dark:#0F172A;--slate:#64748B;--border:#E9EEF4;--bg:#F6F8FB;--green:#059669;--blue:#2563EB;--sidebar-w:260px}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--dark)}

/* ═══ SIDEBAR ═══ */
.admin-sidebar{
  position:fixed; top:0; left:0; bottom:0; width:var(--sidebar-w); z-index:1000;
  background:linear-gradient(180deg,#0F172A 0%,#1A3560 100%);
  display:flex; flex-direction:column; padding:22px 16px;
  transition:transform .3s ease; overflow-y:auto;
}
.admin-sidebar::-webkit-scrollbar{width:5px}
.admin-sidebar::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.15);border-radius:10px}
.admin-logo{display:flex;align-items:center;gap:10px;text-decoration:none;padding:6px 10px 22px;border-bottom:1px solid rgba(255,255,255,0.08);margin-bottom:18px}
.admin-logo-icon{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#E85D26,#F59E0B);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.admin-logo-text{font-family:'Playfair Display',serif;font-size:17px;font-weight:900;color:white}
.admin-logo-text span{color:var(--primary)}
.admin-logo-badge{font-size:9px;font-weight:800;color:rgba(255,255,255,0.5);text-transform:uppercase;letter-spacing:1px;margin-top:-2px}

.sidebar-section-label{font-size:10px;font-weight:800;color:rgba(255,255,255,0.35);text-transform:uppercase;letter-spacing:1px;padding:14px 12px 8px}
.sidebar-link{display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:12px;color:rgba(255,255,255,0.65);text-decoration:none;font-size:13.5px;font-weight:600;margin-bottom:3px;transition:all .15s;position:relative}
.sidebar-link:hover{background:rgba(255,255,255,0.06);color:white}
.sidebar-link.active{background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;box-shadow:0 4px 14px rgba(232,93,38,0.35)}
.sidebar-link .ic{font-size:17px;width:20px;text-align:center;flex-shrink:0}
.sidebar-badge{margin-left:auto;background:#DC2626;color:white;font-size:10px;font-weight:800;padding:2px 7px;border-radius:20px}
.sidebar-link.active .sidebar-badge{background:rgba(255,255,255,0.3)}

.sidebar-footer{margin-top:auto;padding-top:16px;border-top:1px solid rgba(255,255,255,0.08)}
.sidebar-admin-card{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,0.05);margin-bottom:8px}
.sidebar-admin-avatar{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#E85D26,#F59E0B);display:flex;align-items:center;justify-content:center;font-weight:800;color:white;font-size:14px;flex-shrink:0}
.sidebar-admin-name{color:white;font-size:12.5px;font-weight:700}
.sidebar-admin-role{color:rgba(255,255,255,0.45);font-size:10.5px}

/* ═══ OVERLAY MOBILE ═══ */
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,0.5);z-index:999}
.sidebar-overlay.show{display:block}

/* ═══ CONTENU PRINCIPAL ═══ */
.admin-main{margin-left:var(--sidebar-w);min-height:100vh;display:flex;flex-direction:column}
.admin-topbar{position:sticky;top:0;z-index:500;background:rgba(255,255,255,0.97);backdrop-filter:blur(12px);border-bottom:1px solid var(--border);height:66px;display:flex;align-items:center;gap:14px;padding:0 28px}
.sidebar-toggle{display:none;width:38px;height:38px;border-radius:10px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:18px;align-items:center;justify-content:center;flex-shrink:0}
.topbar-title{font-family:'Playfair Display',serif;font-size:19px;font-weight:900}
.topbar-right{margin-left:auto;display:flex;align-items:center;gap:14px}
.topbar-bell{position:relative;width:40px;height:40px;border-radius:12px;border:1.5px solid var(--border);background:white;display:flex;align-items:center;justify-content:center;font-size:18px;cursor:pointer;text-decoration:none;color:inherit}
.topbar-bell .dot{position:absolute;top:6px;right:7px;width:8px;height:8px;border-radius:50%;background:#DC2626;border:2px solid white}
.topbar-avatar{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#E85D26,#F59E0B);display:flex;align-items:center;justify-content:center;font-weight:800;color:white}

.admin-content{padding:26px 28px;flex:1}

/* ═══ RESPONSIVE ═══ */
@media(max-width:960px){
  .admin-sidebar{transform:translateX(-100%)}
  .admin-sidebar.open{transform:translateX(0)}
  .admin-main{margin-left:0}
  .sidebar-toggle{display:flex}
}

/* ═══ ANIMATIONS ═══ */
@keyframes fadeInUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.anim{animation:fadeInUp .45s ease both}
.anim-d1{animation-delay:.05s} .anim-d2{animation-delay:.1s} .anim-d3{animation-delay:.15s} .anim-d4{animation-delay:.2s}

<?= $extra_css ?>
</style>
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<aside class="admin-sidebar" id="adminSidebar">
  <a href="admin.php" class="admin-logo">
    <div class="admin-logo-icon">🏠</div>
    <div><div class="admin-logo-text"><span>Services</span>Plus</div><div class="admin-logo-badge">Espace Admin</div></div>
  </a>

  <div class="sidebar-section-label">Général</div>
  <a href="admin.php" class="sidebar-link <?= $page_active==='dashboard'?'active':'' ?>"><span class="ic">📊</span> Tableau de bord</a>

  <div class="sidebar-section-label">Gestion</div>
  <a href="gestion_prestataires.php" class="sidebar-link <?= $page_active==='prestataires'?'active':'' ?>"><span class="ic">🔧</span> Prestataires</a>
  <a href="gestion_clients.php" class="sidebar-link <?= $page_active==='clients'?'active':'' ?>"><span class="ic">👤</span> Clients</a>
  <a href="gestion_annonces.php" class="sidebar-link <?= $page_active==='annonces'?'active':'' ?>"><span class="ic">📋</span> Annonces &amp; réservations</a>
  <a href="gestion_messages.php" class="sidebar-link <?= $page_active==='messages'?'active':'' ?>"><span class="ic">✉️</span> Messages contact</a>
  <a href="gestion_faq.php" class="sidebar-link <?= $page_active==='faq'?'active':'' ?>"><span class="ic">❓</span> FAQ</a>

  <div class="sidebar-section-label">Compte</div>
  <a href="notifications.php" class="sidebar-link <?= $page_active==='notifications'?'active':'' ?>"><span class="ic">🔔</span> Notifications <?php if($nbNotifsAdmin>0): ?><span class="sidebar-badge"><?= $nbNotifsAdmin ?></span><?php endif; ?></a>
  <a href="mon_profil.php" class="sidebar-link <?= $page_active==='profil'?'active':'' ?>"><span class="ic">⚙️</span> Mon profil</a>

  <div class="sidebar-footer">
    <div class="sidebar-admin-card">
      <div class="sidebar-admin-avatar"><?= strtoupper(substr($admin['prenom'],0,1)) ?></div>
      <div><div class="sidebar-admin-name"><?= e($admin['prenom'].' '.$admin['nom']) ?></div><div class="sidebar-admin-role">Administrateur</div></div>
    </div>
    <a href="../deconnexion.php" class="sidebar-link" style="color:#FCA5A5;"><span class="ic">🚪</span> Déconnexion</a>
  </div>
</aside>

<div class="admin-main">
  <div class="admin-topbar">
    <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
    <div class="topbar-title"><?= e($page_title ?? '') ?></div>
    <div class="topbar-right">
      <a href="notifications.php" class="topbar-bell">🔔<?php if($nbNotifsAdmin>0): ?><span class="dot"></span><?php endif; ?></a>
      <a href="mon_profil.php" class="topbar-avatar" style="text-decoration:none;"><?= strtoupper(substr($admin['prenom'],0,1)) ?></a>
    </div>
  </div>
  <div class="admin-content">