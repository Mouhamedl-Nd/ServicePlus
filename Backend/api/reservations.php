<?php
require_once __DIR__ . '/../includes/helpers.php';
$method=$_SERVER['REQUEST_METHOD']; $action=$_GET['action']??''; $db=getDB();

if ($method==='GET') {
  $user=requireAuth(); $where=[]; $params=[];
  if ($user['role']==='client') { $where[]="r.client_id=?"; $params[]=$user['id']; }
  elseif ($user['role']==='prestataire') {
    $sp=$db->prepare("SELECT id FROM prestataires WHERE user_id=? LIMIT 1"); $sp->execute([$user['id']]); $p=$sp->fetch();
    if (!$p) respond(false,'Profil prestataire introuvable.',[],404);
    $where[]="r.prestataire_id=?"; $params[]=$p['id'];
  }
  if (!empty($_GET['statut'])) { $where[]="r.statut=?"; $params[]=$_GET['statut']; }
  $ws=$where?'WHERE '.implode(' AND ',$where):'';
  $st=$db->prepare("SELECT r.*,CONCAT(uc.prenom,' ',uc.nom) AS client_nom,CONCAT(up.prenom,' ',up.nom) AS prestataire_nom,c.nom AS service_nom,c.icone FROM reservations r JOIN users uc ON r.client_id=uc.id JOIN prestataires pr ON r.prestataire_id=pr.id JOIN users up ON pr.user_id=up.id JOIN categories c ON r.categorie_id=c.id $ws ORDER BY r.created_at DESC");
  $st->execute($params);
  respond(true,'OK',['reservations'=>$st->fetchAll()]);
}

if ($method==='POST' && $action!=='statut') {
  $user=requireRole('client'); $b=getBody();
  $pid=(int)($b['prestataire_id']??0); $cid=(int)($b['categorie_id']??0);
  $date=clean($b['date_reservation']??''); $duree=(int)($b['duree_heures']??2); $adresse=clean($b['adresse']??''); $ville=clean($b['ville']??''); $methode=clean($b['methode_paiement']??'especes'); $urgence=$b['urgence']??0?1:0;
  if (!$pid||!$cid||!$date) respond(false,'Données incomplètes.',[],400);
  $sp=$db->prepare("SELECT id,tarif_horaire FROM prestataires WHERE id=? AND valide=1"); $sp->execute([$pid]); $p=$sp->fetch();
  if (!$p) respond(false,'Prestataire introuvable.',[],404);
  $ht=round($p['tarif_horaire']*$duree,2); $frais=round($ht*FRAIS_PLATEFORME,2); $total=round($ht+$frais,2); $ref=genReference();
  $db->prepare("INSERT INTO reservations (reference,client_id,prestataire_id,categorie_id,description,adresse,ville,date_reservation,duree_heures,urgence,montant_ht,frais_plateforme,montant_total,statut) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,'attente')")
     ->execute([$ref,$user['id'],$pid,$cid,clean($b['description']??''),$adresse,$ville,$date,$duree,$urgence,$ht,$frais,$total]);
  $resId=(int)$db->lastInsertId();
  $db->prepare("INSERT INTO paiements (reservation_id,methode,montant) VALUES(?,?,?)")->execute([$resId,$methode,$total]);
  $up=$db->prepare("SELECT user_id FROM prestataires WHERE id=?"); $up->execute([$pid]); $puid=$up->fetchColumn();
  $db->prepare("INSERT INTO notifications (user_id,titre,message,type) VALUES(?,'Nouvelle réservation 📋',?,'reservation')")->execute([$puid,"Nouvelle réservation $ref de {$user['prenom']} {$user['nom']}."]);
  $db->prepare("INSERT INTO notifications (user_id,titre,message,type) VALUES(?,'Réservation envoyée ✅',?,'reservation')")->execute([$user['id'],"Votre réservation $ref a été envoyée."]);
  respond(true,'Réservation créée.',['reservation_id'=>$resId,'reference'=>$ref,'montant_total'=>$total],201);
}

if ($method==='PUT'||($method==='POST'&&$action==='statut')) {
  $user=requireAuth(); $b=getBody(); $rid=(int)($b['reservation_id']??$_GET['id']??0); $statut=clean($b['statut']??'');
  if (!in_array($statut,['attente','confirme','en-cours','termine','annule'])) respond(false,'Statut invalide.',[],400);
  $res=$db->prepare("SELECT * FROM reservations WHERE id=? LIMIT 1"); $res->execute([$rid]); $r=$res->fetch();
  if (!$r) respond(false,'Réservation introuvable.',[],404);
  $ok=false;
  if ($user['role']==='admin') $ok=true;
  elseif ($user['role']==='client'&&$r['client_id']==$user['id']&&$statut==='annule') $ok=true;
  elseif ($user['role']==='prestataire') { $sp=$db->prepare("SELECT id FROM prestataires WHERE user_id=? LIMIT 1"); $sp->execute([$user['id']]); $p=$sp->fetch(); if ($p&&$r['prestataire_id']==$p['id']) $ok=true; }
  if (!$ok) respond(false,'Non autorisé.',[],403);
  $db->prepare("UPDATE reservations SET statut=? WHERE id=?")->execute([$statut,$rid]);
  $db->prepare("INSERT INTO notifications (user_id,titre,message,type) VALUES(?,'Mise à jour réservation',?,'reservation')")->execute([$r['client_id'],"Réservation {$r['reference']} : $statut."]);
  respond(true,'Statut mis à jour.');
}

if ($method==='DELETE') {
  $user=requireRole('client'); $rid=(int)($_GET['id']??0);
  $r=$db->prepare("SELECT * FROM reservations WHERE id=? AND client_id=? AND statut='attente'"); $r->execute([$rid,$user['id']]); $res=$r->fetch();
  if (!$res) respond(false,'Impossible d\'annuler.',[],404);
  $db->prepare("UPDATE reservations SET statut='annule' WHERE id=?")->execute([$rid]);
  respond(true,'Réservation annulée.');
}
respond(false,'Méthode non autorisée.',[],405);
