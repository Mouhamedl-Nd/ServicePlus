<?php
require_once __DIR__ . '/../includes/helpers.php';
$method=$_SERVER['REQUEST_METHOD']; $action=$_GET['action']??''; $db=getDB();
if ($method==='GET') {
  $user=requireAuth();
  $st=$db->prepare("SELECT id,titre,message,type,lu,created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 30"); $st->execute([$user['id']]);
  $notifs=$st->fetchAll(); $nonLues=count(array_filter($notifs,fn($n)=>!$n['lu']));
  respond(true,'OK',['notifications'=>$notifs,'non_lues'=>$nonLues]);
}
if ($method==='PUT'||($method==='POST'&&$action==='lire')) {
  $user=requireAuth(); $b=getBody(); $nid=isset($b['id'])?(int)$b['id']:null;
  if ($nid) $db->prepare("UPDATE notifications SET lu=1 WHERE id=? AND user_id=?")->execute([$nid,$user['id']]);
  else $db->prepare("UPDATE notifications SET lu=1 WHERE user_id=?")->execute([$user['id']]);
  respond(true,'Marqué comme lu.');
}
respond(false,'Méthode non autorisée.',[],405);
