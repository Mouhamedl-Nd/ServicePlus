<?php
require_once __DIR__ . '/../includes/helpers.php';
$method=$_SERVER['REQUEST_METHOD']; $db=getDB();
if ($method==='GET') {
  $pid=(int)($_GET['id']??0);
  if (!$pid) respond(false,'ID requis.',[],400);
  $st=$db->prepare("SELECT a.note,a.commentaire,a.reponse_presta,a.created_at,CONCAT(u.prenom,' ',LEFT(u.nom,1),'.') AS client_nom FROM avis a JOIN reservations r ON a.reservation_id=r.id JOIN users u ON a.client_id=u.id WHERE a.prestataire_id=? ORDER BY a.created_at DESC");
  $st->execute([$pid]);
  respond(true,'OK',['avis'=>$st->fetchAll()]);
}
if ($method==='POST') {
  $user=requireRole('client'); $b=getBody();
  $rid=(int)($b['reservation_id']??0); $note=(int)($b['note']??0); $cmt=clean($b['commentaire']??'');
  if (!$rid||$note<1||$note>5) respond(false,'Données invalides.',[],400);
  $res=$db->prepare("SELECT * FROM reservations WHERE id=? AND client_id=? AND statut='termine' LIMIT 1"); $res->execute([$rid,$user['id']]); $r=$res->fetch();
  if (!$r) respond(false,'Réservation introuvable ou non terminée.',[],404);
  $chk=$db->prepare("SELECT id FROM avis WHERE reservation_id=? LIMIT 1"); $chk->execute([$rid]);
  if ($chk->fetch()) respond(false,'Avis déjà publié.',[],409);
  $db->prepare("INSERT INTO avis (reservation_id,client_id,prestataire_id,note,commentaire) VALUES(?,?,?,?,?)")->execute([$rid,$user['id'],$r['prestataire_id'],$note,$cmt]);
  $db->prepare("UPDATE prestataires SET note_moyenne=(SELECT ROUND(AVG(note),2) FROM avis WHERE prestataire_id=?),nb_avis=(SELECT COUNT(*) FROM avis WHERE prestataire_id=?) WHERE id=?")->execute([$r['prestataire_id'],$r['prestataire_id'],$r['prestataire_id']]);
  $puid=$db->prepare("SELECT user_id FROM prestataires WHERE id=?"); $puid->execute([$r['prestataire_id']]); $uid=$puid->fetchColumn();
  $db->prepare("INSERT INTO notifications (user_id,titre,message,type) VALUES(?,'Nouvel avis ⭐',?,'avis')")->execute([$uid,"{$user['prenom']} vous a laissé un avis $note/5."]);
  respond(true,'Avis publié.',[], 201);
}
respond(false,'Méthode non autorisée.',[],405);
