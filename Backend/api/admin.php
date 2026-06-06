<?php
require_once __DIR__ . '/../includes/helpers.php';
$action=$_GET['action']??''; $db=getDB(); requireRole('admin');
switch($action) {
  case 'stats':
    respond(true,'OK',['stats'=>[
      'total_users'           =>$db->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn(),
      'total_prestataires'    =>$db->query("SELECT COUNT(*) FROM prestataires WHERE valide=1")->fetchColumn(),
      'total_reservations'    =>$db->query("SELECT COUNT(*) FROM reservations")->fetchColumn(),
      'reservations_actives'  =>$db->query("SELECT COUNT(*) FROM reservations WHERE statut IN('attente','confirme','en-cours')")->fetchColumn(),
      'revenus_total'         =>$db->query("SELECT COALESCE(SUM(montant),0) FROM paiements WHERE statut='complete'")->fetchColumn(),
      'revenus_plateforme'    =>$db->query("SELECT COALESCE(SUM(frais_plateforme),0) FROM reservations WHERE statut='termine'")->fetchColumn(),
      'en_attente_validation' =>$db->query("SELECT COUNT(*) FROM prestataires WHERE valide=0")->fetchColumn(),
      'note_moyenne_globale'  =>$db->query("SELECT ROUND(AVG(note),1) FROM avis")->fetchColumn(),
    ]]);
  case 'users':
    $st=$db->query("SELECT u.id,u.nom,u.prenom,u.email,u.telephone,u.ville,u.role,u.statut,u.created_at,COUNT(r.id) AS nb_reservations FROM users u LEFT JOIN reservations r ON r.client_id=u.id WHERE u.role!='admin' GROUP BY u.id ORDER BY u.created_at DESC");
    respond(true,'OK',['users'=>$st->fetchAll()]);
  case 'prestataires':
    $v=isset($_GET['valide'])?(int)$_GET['valide']:-1; $w=$v>=0?"WHERE p.valide=$v":'';
    $st=$db->query("SELECT p.id,p.tarif_horaire,p.note_moyenne,p.nb_missions,p.experience,p.ville,p.valide,p.documents_count,p.created_at,u.nom,u.prenom,u.email,u.statut AS user_statut,c.nom AS service FROM prestataires p JOIN users u ON p.user_id=u.id JOIN categories c ON p.categorie_id=c.id $w ORDER BY p.created_at DESC");
    respond(true,'OK',['prestataires'=>$st->fetchAll()]);
  case 'valider':
    if ($_SERVER['REQUEST_METHOD']!=='POST') respond(false,'POST requis.',[],405);
    $b=getBody(); $pid=(int)($b['prestataire_id']??0);
    if (!$pid) respond(false,'ID requis.',[],400);
    $db->prepare("UPDATE prestataires SET valide=1 WHERE id=?")->execute([$pid]);
    $db->prepare("UPDATE users SET statut='actif' WHERE id=(SELECT user_id FROM prestataires WHERE id=?)")->execute([$pid]);
    $uid=$db->prepare("SELECT user_id FROM prestataires WHERE id=?"); $uid->execute([$pid]); $u=$uid->fetchColumn();
    $db->prepare("INSERT INTO notifications (user_id,titre,message,type) VALUES(?,'Dossier validé ✅','Votre profil prestataire a été validé !','système')")->execute([$u]);
    respond(true,'Prestataire validé.');
  case 'suspendre':
    if ($_SERVER['REQUEST_METHOD']!=='POST') respond(false,'POST requis.',[],405);
    $b=getBody(); $uid=(int)($b['user_id']??0);
    if (!$uid) respond(false,'ID requis.',[],400);
    $db->prepare("UPDATE users SET statut='suspendu' WHERE id=? AND role!='admin'")->execute([$uid]);
    respond(true,'Utilisateur suspendu.');
  case 'reactiver':
    if ($_SERVER['REQUEST_METHOD']!=='POST') respond(false,'POST requis.',[],405);
    $b=getBody(); $uid=(int)($b['user_id']??0);
    if (!$uid) respond(false,'ID requis.',[],400);
    $db->prepare("UPDATE users SET statut='actif' WHERE id=?")->execute([$uid]);
    respond(true,'Utilisateur réactivé.');
  default: respond(false,'Action inconnue.',[],404);
}
