// ================================================================
//  ServicesPlus — api.js
//  Fichier à inclure dans toutes les pages HTML
//  <script src="api.js"></script>
// ================================================================

// ── URL de base de l'API backend ─────────────────────────────
// LOCAL  : http://localhost/ServicesPlus/backend/api
// RENDER : https://ton-app.onrender.com/api  (à changer après déploiement)
const API_BASE = 'http://localhost/ServicePlus/Backend/api';
// ── Appel API générique ───────────────────────────────────────
async function apiFetch(endpoint, method = 'GET', body = null) {
  const options = {
    method,
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include', // pour les cookies de session PHP
  };
  if (body) options.body = JSON.stringify(body);
  try {
    const res  = await fetch(`${API_BASE}/${endpoint}`, options);
    const data = await res.json();
    return data;
  } catch (err) {
    console.error('Erreur réseau :', err);
    return { success: false, message: 'Erreur réseau. Vérifiez que XAMPP est démarré.' };
  } 
}

// ── Gestion session utilisateur ───────────────────────────────
const Auth = {
  setUser(user) {
    localStorage.setItem('sp_user', JSON.stringify(user));
  },
  getUser() {
    try { return JSON.parse(localStorage.getItem('sp_user')); }
    catch { return null; }
  },
  isLoggedIn() { return !!this.getUser(); },
  async logout() {
    await apiFetch('auth.php?action=logout', 'POST');
    localStorage.removeItem('sp_user');
    window.location.href = '10_connexion.html';
  },
  requireLogin(redirectUrl = '10_connexion.html') {
    const user = this.getUser();
    if (!user) { window.location.href = redirectUrl; return null; }
    return user;
  },
  requireRole(role) {
    const user = this.requireLogin();
    if (!user) return null;
    if (user.role !== role) { window.location.href = '01_accueil.html'; return null; }
    return user;
  }
};

// ── Mise à jour navbar selon connexion ────────────────────────
function updateNavbar() {
  const user = Auth.getUser();
  const navActions = document.querySelector('.nav-actions');
  if (!navActions || !user) return;

  const dest = user.role === 'admin' ? '07_admin.html'
             : user.role === 'prestataire' ? '06_dashboard_prestataire.html'
             : '07_dashboard_client.html';

  navActions.innerHTML = `
    <a href="${dest}" style="padding:8px 14px;background:var(--primary-light,#FFF0EB);color:var(--primary,#E85D26);border-radius:10px;font-weight:700;font-size:13px;text-decoration:none;">
      👤 ${user.prenom}
    </a>
    <button onclick="Auth.logout()" style="padding:8px 18px;background:transparent;color:#64748B;border:1.5px solid #E9EEF4;border-radius:10px;font-weight:600;cursor:pointer;font-size:13px;">
      Déconnexion
    </button>
  `;
}

document.addEventListener('DOMContentLoaded', updateNavbar);
