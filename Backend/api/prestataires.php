<?php
require_once __DIR__ . '/../includes/helpers.php';
$method = $_SERVER['REQUEST_METHOD'];
$db     = getDB();

if ($method === 'GET') {
  if (isset($_GET['id'])) {
    $stmt = $db->prepare("
      SELECT p.*,u.nom,u.prenom,u.email,u.telephone,u.avatar,u.created_at AS membre_depuis,
             c.nom AS categorie_nom,c.icone AS categorie_icone,c.id AS categorie_id
      FROM prestataires p
      JOIN users u ON p.user_id=u.id
      JOIN categories c ON p.categorie_id=c.id
      WHERE p.id=? AND p.valide=1 AND u.statut='actif'");
    $stmt->execute([(int)$_GET['id']]);
    $p = $stmt->fetch();
    if (!$p) respond(false,'Prestataire introuvable.',[],404);
    $av = $db->prepare("SELECT a.note,a.commentaire,a.created_at,CONCAT(u.prenom,' ',LEFT(u.nom,1),'.') AS client_nom FROM avis a JOIN reservations r ON a.reservation_id=r.id JOIN users u ON a.client_id=u.id WHERE a.prestataire_id=? ORDER BY a.created_at DESC LIMIT 10");
    $av->execute([$p['id']]);
    $p['avis_liste'] = $av->fetchAll();
    respond(true,'OK',['prestataire'=>$p]);
  }
  $where=[]; $params=[];
  $where[]="p.valide=1"; $where[]="u.statut='actif'";
  if (!empty($_GET['categorie'])) { $where[]="c.nom LIKE ?"; $params[]='%'.$_GET['categorie'].'%'; }
  if (!empty($_GET['ville']))     { $where[]="(p.ville LIKE ? OR u.ville LIKE ?)"; $params[]='%'.$_GET['ville'].'%'; $params[]='%'.$_GET['ville'].'%'; }
  if (!empty($_GET['prix_max']))  { $where[]="p.tarif_horaire<=?"; $params[]=(float)$_GET['prix_max']; }
  $ord = match($_GET['tri']??'') { 'prix_asc'=>'p.tarif_horaire ASC','prix_desc'=>'p.tarif_horaire DESC','missions'=>'p.nb_missions DESC',default=>'p.note_moyenne DESC' };
  $ws  = implode(' AND ',$where);
  $st  = $db->prepare("SELECT p.id,p.tarif_horaire,p.note_moyenne,p.nb_missions,p.nb_avis,p.experience,p.ville,p.paiements_acceptes,u.nom,u.prenom,u.avatar,c.nom AS service,c.icone,c.id AS categorie_id FROM prestataires p JOIN users u ON p.user_id=u.id JOIN categories c ON p.categorie_id=c.id WHERE $ws ORDER BY $ord LIMIT 50");
  $st->execute($params);
  respond(true,'OK',['prestataires'=>$st->fetchAll(),'total'=>$st->rowCount()]);
}

if ($method==='PUT' || ($method==='POST' && ($_GET['action']??'')==='update')) {
  $user=$db->prepare("SELECT id FROM prestataires WHERE user_id=? LIMIT 1");
  $u=requireRole('prestataire'); $b=getBody();
  $user->execute([$u['id']]); $p=$user->fetch();
  if (!$p) respond(false,'Profil introuvable.',[],404);
  $db->prepare("UPDATE prestataires SET bio=?,tarif_horaire=?,experience=?,ville=?,paiements_acceptes=? WHERE id=?")
     ->execute([clean($b['bio']??''),(float)($b['tarif_horaire']??0),clean($b['experience']??''),clean($b['ville']??''),clean($b['paiements_acceptes']??''),$p['id']]);
  respond(true,'Profil mis à jour.');
}
respond(false,'Méthode non autorisée.',[],405);
