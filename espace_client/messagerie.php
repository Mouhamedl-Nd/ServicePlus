<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('prestataire', '../');

$prestaId = $_SESSION['user_id'];

$annonceId = (int)($_GET['annonce_id'] ?? 0);
$avecId    = (int)($_GET['avec'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer'])) {
    $annonceId = (int)$_POST['annonce_id'];
    $avecId = (int)$_POST['destinataire_id'];
    $contenu = trim($_POST['contenu'] ?? '');
    if ($contenu !== '') {
        $pdo->prepare('INSERT INTO messages (annonce_id, expediteur_id, destinataire_id, contenu) VALUES (?,?,?,?)')
            ->execute([$annonceId, $prestaId, $avecId, $contenu]);
        creerNotification($pdo, $avecId, 'Nouveau message 💬', 'Vous avez reçu un nouveau message concernant une annonce.', 'info', '../espace_client/messagerie.php?annonce_id='.$annonceId.'&avec='.$prestaId);
    }
    header("Location: messagerie.php?annonce_id=$annonceId&avec=$avecId"); exit;
}

$conversations = $pdo->prepare("
    SELECT m.annonce_id, a.titre,
           IF(m.expediteur_id=?, m.destinataire_id, m.expediteur_id) AS avec_id,
           MAX(m.date_envoi) AS dernier_message,
           SUM(CASE WHEN m.destinataire_id=? AND m.lu=0 THEN 1 ELSE 0 END) AS non_lus
    FROM messages m JOIN annonces a ON a.id = m.annonce_id
    WHERE m.expediteur_id=? OR m.destinataire_id=?
    GROUP BY m.annonce_id, avec_id
    ORDER BY dernier_message DESC
");
$conversations->execute([$prestaId,$prestaId,$prestaId,$prestaId]);
$conversations = $conversations->fetchAll();

foreach ($conversations as &$c) {
    $u = $pdo->prepare('SELECT prenom, nom FROM utilisateurs WHERE id=?');
    $u->execute([$c['avec_id']]);
    $c['contact'] = $u->fetch();
}
unset($c);

$messages = [];
$contactActuel = null;
if ($annonceId && $avecId) {
    $pdo->prepare('UPDATE messages SET lu=1 WHERE annonce_id=? AND expediteur_id=? AND destinataire_id=?')->execute([$annonceId, $avecId, $prestaId]);
    $stmt = $pdo->prepare('SELECT * FROM messages WHERE annonce_id=? AND ((expediteur_id=? AND destinataire_id=?) OR (expediteur_id=? AND destinataire_id=?)) ORDER BY date_envoi ASC');
    $stmt->execute([$annonceId, $prestaId, $avecId, $avecId, $prestaId]);
    $messages = $stmt->fetchAll();
    $u = $pdo->prepare('SELECT prenom, nom FROM utilisateurs WHERE id=?'); $u->execute([$avecId]); $contactActuel = $u->fetch();
}

$page_title  = 'ServicesPlus – Messagerie';
$racine      = '../';
$extra_css   = <<<CSS
.container{max-width:1000px;margin:0 auto;padding:28px 20px}
.msg-layout{display:grid;grid-template-columns:280px 1fr;gap:18px;height:70vh;min-height:500px}
.conv-list{background:white;border-radius:16px;border:1.5px solid var(--border);overflow-y:auto}
.conv-item{display:block;padding:14px 16px;border-bottom:1px solid #F8FAFC;text-decoration:none;color:inherit}
.conv-item:hover,.conv-item.active{background:var(--pl)}
.conv-titre{font-weight:700;font-size:13px}
.conv-sub{font-size:11.5px;color:var(--slate);margin-top:2px}
.conv-badge{background:var(--primary);color:white;font-size:10px;font-weight:800;padding:2px 7px;border-radius:20px;float:right}
.chat-panel{background:white;border-radius:16px;border:1.5px solid var(--border);display:flex;flex-direction:column}
.chat-header{padding:16px 20px;border-bottom:1px solid var(--border);font-weight:800}
.chat-body{flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;gap:10px}
.bubble{max-width:70%;padding:10px 14px;border-radius:14px;font-size:13.5px;line-height:1.5}
.bubble.moi{align-self:flex-end;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border-bottom-right-radius:4px}
.bubble.autre{align-self:flex-start;background:#F1F5F9;color:var(--dark);border-bottom-left-radius:4px}
.bubble-time{font-size:10px;opacity:.7;margin-top:4px}
.chat-form{display:flex;gap:10px;padding:14px;border-top:1px solid var(--border)}
.chat-input{flex:1;padding:11px 16px;border-radius:20px;border:1.5px solid var(--border);font-size:13.5px;font-family:'Inter',sans-serif}
.btn-send{padding:11px 20px;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:20px;font-weight:700;cursor:pointer}
.empty-chat{flex:1;display:flex;align-items:center;justify-content:center;color:var(--slate);font-size:13.5px;text-align:center;padding:20px}
@media(max-width:800px){ .msg-layout{grid-template-columns:1fr;height:auto} .conv-list{max-height:200px} }
CSS;

require '../includes/header.php';
?>

<div class="container">
  <div class="msg-layout">
    <div class="conv-list">
      <?php if (empty($conversations)): ?>
        <div style="padding:20px;color:var(--slate);font-size:13px;">Aucune conversation. Postulez à une demande depuis votre dashboard pour démarrer une discussion.</div>
      <?php else: foreach ($conversations as $c): ?>
        <a class="conv-item <?= ($c['annonce_id']==$annonceId && $c['avec_id']==$avecId)?'active':'' ?>" href="messagerie.php?annonce_id=<?= $c['annonce_id'] ?>&avec=<?= $c['avec_id'] ?>">
          <?php if ($c['non_lus']>0): ?><span class="conv-badge"><?= $c['non_lus'] ?></span><?php endif; ?>
          <div class="conv-titre"><?= e($c['contact']['prenom'].' '.$c['contact']['nom']) ?></div>
          <div class="conv-sub"><?= e($c['titre']) ?> • <?= ilYA($c['dernier_message']) ?></div>
        </a>
      <?php endforeach; endif; ?>
    </div>

    <div class="chat-panel">
      <?php if ($contactActuel): ?>
        <div class="chat-header">💬 <?= e($contactActuel['prenom'].' '.$contactActuel['nom']) ?></div>
        <div class="chat-body">
          <?php foreach ($messages as $m): ?>
            <div class="bubble <?= $m['expediteur_id']==$prestaId?'moi':'autre' ?>"><?= e($m['contenu']) ?><div class="bubble-time"><?= ilYA($m['date_envoi']) ?></div></div>
          <?php endforeach; ?>
        </div>
        <form method="post" class="chat-form">
          <input type="hidden" name="annonce_id" value="<?= $annonceId ?>">
          <input type="hidden" name="destinataire_id" value="<?= $avecId ?>">
          <input type="text" name="contenu" class="chat-input" placeholder="Écrivez votre message..." required autocomplete="off">
          <button type="submit" name="envoyer" value="1" class="btn-send">Envoyer</button>
        </form>
      <?php else: ?>
        <div class="empty-chat">Sélectionnez une conversation à gauche, ou démarrez-en une depuis le dashboard après avoir postulé à une demande.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require '../includes/footer.php'; ?>