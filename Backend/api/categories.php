<?php
require_once __DIR__ . '/../includes/helpers.php';
if ($_SERVER['REQUEST_METHOD']!=='GET') respond(false,'GET uniquement.',[],405);
$db=getDB();
$st=$db->query("SELECT id,nom,icone,description FROM categories ORDER BY nom ASC");
respond(true,'OK',['categories'=>$st->fetchAll()]);
