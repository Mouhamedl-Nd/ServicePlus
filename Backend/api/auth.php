<?php
require_once __DIR__ . '/../includes/helpers.php';
$action = $_GET['action'] ?? '';

switch ($action) {
  case 'login':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false,'Méthode invalide.',[],405);
    $b = getBody();
    $email = clean($b['email'] ?? '');
    $pass  = $b['password'] ?? '';
    if (!$email || !$pass) respond(false,'Email et mot de passe requis.',[],400);
    $db   = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? AND statut != 'suspendu' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($pass, $user['password'])) respond(false,'Email ou mot de passe incorrect.',[],401);
    unset($user['password'], $user['remember_token']);
    $_SESSION['user'] = $user;
    $redirect = match($user['role']) {
      'admin'       => '07_admin.html',
      'prestataire' => '06_dashboard_prestataire.html',
      default       => '05_dashboard_client.html',
    };
    respond(true,'Connexion réussie.',['user'=>$user,'redirect'=>$redirect]);

  case 'register':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false,'Méthode invalide.',[],405);
    $b      = getBody();
    $nom    = clean($b['nom']    ?? '');
    $prenom = clean($b['prenom'] ?? '');
    $email  = clean($b['email']  ?? '');
    $pass   = $b['password']     ?? '';
    $role   = in_array($b['role']??'',['client','prestataire']) ? $b['role'] : 'client';
    $ville  = clean($b['ville']  ?? '');
    $tel    = clean($b['telephone'] ?? '');
    if (!$nom||!$prenom||!$email||!$pass) respond(false,'Champs obligatoires manquants.',[],400);
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) respond(false,'Email invalide.',[],400);
    if (strlen($pass)<6) respond(false,'Mot de passe trop court.',[],400);
    $db = getDB();
    $chk = $db->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $chk->execute([$email]);
    if ($chk->fetch()) respond(false,'Email déjà utilisé.',[],409);
    $statut = $role==='prestataire' ? 'attente' : 'actif';
    $hash   = password_hash($pass, PASSWORD_BCRYPT);
    $db->prepare("INSERT INTO users (nom,prenom,email,password,telephone,ville,role,statut) VALUES(?,?,?,?,?,?,?,?)")
       ->execute([$nom,$prenom,$email,$hash,$tel?:null,$ville?:null,$role,$statut]);
    $uid = (int)$db->lastInsertId();
    if ($role==='prestataire') {
      $catId = (int)($b['categorie_id']??1);
      $tarif = (float)($b['tarif_horaire']??0);
      $bio   = clean($b['bio']??'');
      $exp   = clean($b['experience']??'1 à 3 ans');
      $db->prepare("INSERT INTO prestataires (user_id,categorie_id,bio,tarif_horaire,experience,ville) VALUES(?,?,?,?,?,?)")
         ->execute([$uid,$catId,$bio,$tarif,$exp,$ville?:null]);
      respond(true,'Inscription réussie. Dossier en attente de validation.',['user_id'=>$uid,'role'=>$role],201);
    }
    $db->prepare("INSERT INTO notifications (user_id,titre,message,type) VALUES(?,'Bienvenue ! 🎉','Votre compte a été créé avec succès.','système')")
       ->execute([$uid]);
    respond(true,'Inscription réussie ! Vous pouvez vous connecter.',['user_id'=>$uid,'role'=>$role],201);

  case 'logout':
    session_destroy();
    respond(true,'Déconnexion réussie.');

  case 'me':
    respond(true,'OK',['user'=>requireAuth()]);

  default:
    respond(false,'Action inconnue.',[],404);
}
