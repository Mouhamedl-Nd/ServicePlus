<?php
require 'config/connexion.php';
require 'config/session.php';
require 'includes/fonctions.php';

$erreurs  = [];
$succes   = false;
$reference = '';
$vd = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['envoyer'])) {
    $vd = $_POST;
    $prenom  = trim($_POST['prenom'] ?? '');
    $nom     = trim($_POST['nom'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $tel     = trim($_POST['telephone'] ?? '');
    $sujet   = trim($_POST['sujet'] ?? '');
    $priorite = in_array($_POST['priorite'] ?? '', ['urgent','normal','faible']) ? $_POST['priorite'] : 'normal';
    $resnum  = trim($_POST['resnum'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $rgpd    = isset($_POST['rgpd']);

    if (!$prenom || !$nom)                         { $erreurs[] = 'Veuillez renseigner votre nom complet.'; }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $erreurs[] = 'Veuillez entrer une adresse email valide.'; }
    if (!$sujet)                                    { $erreurs[] = 'Veuillez sélectionner un sujet.'; }
    if (strlen($message) < 20)                      { $erreurs[] = 'Votre message doit contenir au moins 20 caractères.'; }
    if (!$rgpd)                                      { $erreurs[] = 'Veuillez accepter la politique de confidentialité.'; }

    if (!$erreurs) {
        $priorLabel = ['urgent' => '🔴 Urgent', 'normal' => '🟡 Normal', 'faible' => '🟢 Faible'][$priorite];
        $messageComplet = "Priorité : $priorLabel"
            . ($tel ? " | Téléphone : $tel" : '')
            . ($resnum ? " | Réservation : $resnum" : '')
            . "\n\n" . $message;

        $stmt = $pdo->prepare('INSERT INTO messages_contact (nom, email, sujet, message) VALUES (?,?,?,?)');
        $stmt->execute(["$prenom $nom", $email, $sujet, $messageComplet]);

        $reference = 'TICKET-' . date('Y') . '-' . str_pad((string)$pdo->lastInsertId(), 4, '0', STR_PAD_LEFT);
        $succes = true;
    }
}

/* ── 3 questions FAQ populaires réelles, pour la mini-FAQ de la sidebar ── */
$faqMini = $pdo->query("SELECT id, question FROM faq WHERE populaire = 1 ORDER BY votes_utile DESC LIMIT 3")->fetchAll();

$page_title  = 'ServicesPlus – Contact';
$page_active = 'contact';
$extra_css   = <<<CSS
.hero{background:linear-gradient(130deg,#1A3560 0%,#2563EB 50%,#E85D26 100%);padding:64px 40px;text-align:center;position:relative;overflow:hidden}
.hero::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,0.06) 1px,transparent 1px);background-size:26px 26px}
.hero-inner{position:relative;max-width:620px;margin:0 auto}
.hero-badge{display:inline-flex;align-items:center;gap:7px;background:rgba(255,255,255,0.13);border-radius:20px;padding:6px 18px;margin-bottom:20px}
.hero-badge span{color:rgba(255,255,255,0.9);font-size:11px;font-weight:700;letter-spacing:.8px;text-transform:uppercase}
.hero h1{font-family:'Playfair Display',serif;color:white;font-size:42px;font-weight:900;margin-bottom:14px;line-height:1.15}
.hero h1 em{color:#FCD34D;font-style:normal}
.hero p{color:rgba(255,255,255,0.78);font-size:15px;line-height:1.75;margin-bottom:28px}
.hero-btns{display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
.btn-white{padding:12px 24px;background:white;color:var(--primary);border:none;border-radius:12px;font-weight:800;cursor:pointer;font-size:14px;text-decoration:none;display:inline-block}
.btn-ghost{padding:12px 24px;background:transparent;color:white;border:2px solid rgba(255,255,255,0.4);border-radius:12px;font-weight:700;cursor:pointer;font-size:14px}
.channels{background:white;border-bottom:1px solid var(--border);padding:0 40px}
.channels-inner{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:repeat(4,1fr);gap:0}
.channel{padding:24px 20px;text-align:center;border-right:1px solid var(--border);cursor:pointer;position:relative}
.channel:last-child{border-right:none}
.channel:hover{background:var(--pl)}
.ch-icon{font-size:32px;margin-bottom:10px}
.ch-title{font-weight:800;font-size:15px;margin-bottom:4px}
.ch-sub{font-size:12px;color:var(--slate);margin-bottom:8px;line-height:1.5}
.ch-badge{display:inline-block;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px}
.ch-badge.green{color:var(--green);background:#ECFDF5}
.ch-badge.blue{color:var(--blue);background:#EFF6FF}
.ch-badge.orange{color:#D97706;background:#FFFBEB}
.ch-badge.gray{color:var(--slate);background:#F1F5F9}
.container{max-width:1100px;margin:0 auto;padding:40px 20px}
.layout{display:grid;grid-template-columns:1fr 380px;gap:28px;align-items:start}
.card{background:white;border-radius:18px;padding:28px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);margin-bottom:18px}
.card-title{font-weight:800;font-size:18px;margin-bottom:6px}
.card-sub{color:var(--slate);font-size:14px;margin-bottom:24px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.fg{display:flex;flex-direction:column;gap:7px}
.fg.full{grid-column:1/-1}
.fl{font-size:11px;font-weight:700;color:var(--slate);text-transform:uppercase;letter-spacing:.5px}
.iw{position:relative}
.fi{width:100%;padding:12px 16px 12px 44px;border-radius:11px;border:1.5px solid var(--border);font-size:14px;font-family:'Inter',sans-serif;color:var(--dark);outline:none}
.fi:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(232,93,38,.1)}
.fi.no-ic{padding-left:16px}
.fi-ic{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:16px;pointer-events:none}
textarea.fi{padding:12px 16px;resize:vertical;min-height:120px}
.fi-select{width:100%;padding:12px 16px;border-radius:11px;border:1.5px solid var(--border);font-size:14px;font-family:'Inter',sans-serif;color:var(--dark);outline:none;background:white;cursor:pointer}
.priority-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:4px}
.prio-lbl{padding:10px;border-radius:10px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:12px;font-weight:700;text-align:center;display:block}
.prio-lbl input{display:none}
.prio-lbl:has(input:checked){border-color:var(--primary);background:var(--pl);color:var(--primary)}
.attach-zone{border:2px dashed var(--border);border-radius:14px;padding:22px;text-align:center;color:var(--slate);font-size:13px}
.btn-submit{width:100%;padding:15px 0;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:13px;font-weight:800;font-size:15px;cursor:pointer;font-family:'Inter',sans-serif;box-shadow:0 4px 16px rgba(232,93,38,0.3);margin-top:8px}
.success-wrap{text-align:center;padding:28px 0}
.success-icon{width:80px;height:80px;border-radius:24px;background:#ECFDF5;display:flex;align-items:center;justify-content:center;font-size:40px;margin:0 auto 18px}
.success-title{font-family:'Playfair Display',serif;font-size:24px;font-weight:900;color:#059669;margin-bottom:8px}
.success-sub{color:var(--slate);font-size:14px;line-height:1.75;margin-bottom:24px}
.success-ref{background:#F8FAFC;border-radius:12px;padding:14px 18px;font-size:13px;font-weight:700;color:var(--primary);margin-bottom:20px}
.alert-err{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;padding:12px 16px;border-radius:12px;font-size:13px;margin-bottom:16px}
.info-card{background:white;border-radius:18px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);overflow:hidden;margin-bottom:16px}
.ic-header{padding:18px 22px;background:linear-gradient(135deg,#1A3560,#2563EB);color:white}
.ic-header-title{font-weight:800;font-size:16px;margin-bottom:4px}
.ic-header-sub{font-size:12px;color:rgba(255,255,255,0.75)}
.ic-body{padding:18px 22px}
.contact-row{display:flex;gap:14px;align-items:flex-start;margin-bottom:16px}
.contact-row:last-child{margin-bottom:0}
.cr-icon{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
.cr-label{font-size:11px;color:#94A3B8;margin-bottom:2px}
.cr-value{font-size:14px;font-weight:700}
.cr-sub{font-size:12px;color:var(--slate);margin-top:1px}
.horaire-row{display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #F8FAFC;font-size:13px}
.horaire-row:last-child{border-bottom:none}
.horaire-jour{font-weight:600}
.horaire-heure{font-weight:700}
.horaire-heure.ouvert{color:var(--green)}
.horaire-heure.ferme{color:#94A3B8}
.faq-mini{padding:12px 0;border-bottom:1px solid #F8FAFC;text-decoration:none;color:inherit;display:block}
.faq-mini:last-child{border-bottom:none}
.faq-mini:hover .faq-mini-q{color:var(--primary)}
.faq-mini-q{font-size:13px;font-weight:600;color:var(--dark);margin-bottom:3px}
@media(max-width:900px){
  .layout{grid-template-columns:1fr}
  .channels-inner{grid-template-columns:repeat(2,1fr)}
  .channel{border-bottom:1px solid var(--border)}
  .form-grid{grid-template-columns:1fr}
}
CSS;

require 'includes/header.php';
?>

<div class="hero">
  <div class="hero-inner">
    <div class="hero-badge"><span>💬 Support ServicesPlus</span></div>
    <h1>Nous sommes là<br/><em>pour vous aider</em></h1>
    <p>Notre équipe support vous répond dans les meilleurs délais.<br/>Choisissez le canal qui vous convient le mieux.</p>
    <div class="hero-btns">
      <a class="btn-white" href="#formSection">✉️ Envoyer un message</a>
    </div>
  </div>
</div>

<div class="channels">
  <div class="channels-inner">
    <div class="channel"><div class="ch-icon">✉️</div><div class="ch-title">Formulaire</div><div class="ch-sub">Décrivez votre demande en détail</div><span class="ch-badge blue">Rapide</span></div>
    <div class="channel"><div class="ch-icon">📞</div><div class="ch-title">Téléphone</div><div class="ch-sub">+221 33 000 00 00</div><span class="ch-badge orange">Lun-Sam 8h-20h</span></div>
    <div class="channel"><div class="ch-icon">📱</div><div class="ch-title">WhatsApp</div><div class="ch-sub">+221 77 000 00 00</div><span class="ch-badge green">7j/7</span></div>
    <div class="channel"><a href="faq.php" style="text-decoration:none;color:inherit;"><div class="ch-icon">❓</div><div class="ch-title">FAQ</div><div class="ch-sub">Questions déjà répondues</div><span class="ch-badge gray">Self-service</span></a></div>
  </div>
</div>

<div class="container">
  <div class="layout">

    <div id="formSection">
      <div class="card">
        <?php if ($succes): ?>
          <div class="success-wrap">
            <div class="success-icon">✅</div>
            <div class="success-title">Message envoyé !</div>
            <div class="success-sub">Merci pour votre message, <strong><?= e($vd['prenom']) ?></strong> !<br/>Notre équipe vous répondra à l'adresse <strong><?= e($vd['email']) ?></strong></div>
            <div class="success-ref">📋 Référence : <?= e($reference) ?></div>
            <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
              <a href="contact.php" style="padding:12px 24px;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:12px;font-weight:800;font-size:14px;text-decoration:none;">Envoyer un autre message</a>
              <a href="index.php" style="padding:12px 24px;background:#F8FAFC;color:var(--slate);border:1.5px solid var(--border);border-radius:12px;font-weight:700;font-size:14px;text-decoration:none;">Retour à l'accueil</a>
            </div>
          </div>
        <?php else: ?>
          <div class="card-title">✉️ Envoyer un message</div>
          <div class="card-sub">Remplissez le formulaire et notre équipe vous répond rapidement.</div>

          <?php if ($erreurs): ?><div class="alert-err">❌ <?= e(implode(' ', $erreurs)) ?></div><?php endif; ?>

          <form method="post" action="contact.php#formSection">
            <div class="form-grid">
              <div class="fg"><label class="fl">Prénom *</label><div class="iw"><span class="fi-ic">👤</span><input type="text" name="prenom" class="fi" placeholder="Mohammed" value="<?= e($vd['prenom'] ?? '') ?>"></div></div>
              <div class="fg"><label class="fl">Nom *</label><div class="iw"><span class="fi-ic">👤</span><input type="text" name="nom" class="fi" placeholder="Dia" value="<?= e($vd['nom'] ?? '') ?>"></div></div>
              <div class="fg"><label class="fl">Email *</label><div class="iw"><span class="fi-ic">📧</span><input type="email" name="email" class="fi" placeholder="votre@email.com" value="<?= e($vd['email'] ?? '') ?>"></div></div>
              <div class="fg"><label class="fl">Téléphone</label><div class="iw"><span class="fi-ic">📞</span><input type="tel" name="telephone" class="fi" placeholder="+221 77 000 00 00" value="<?= e($vd['telephone'] ?? '') ?>"></div></div>

              <div class="fg full">
                <label class="fl">Sujet *</label>
                <select name="sujet" class="fi-select">
                  <option value="">Sélectionnez un sujet</option>
                  <?php
                  $groupes = [
                      'Réservations' => ['Problème avec une réservation','Annulation de réservation','Modification de réservation'],
                      'Paiements'    => ['Problème de paiement','Demande de remboursement','Facture manquante'],
                      'Compte'       => ['Problème de connexion','Modification du profil','Suppression de compte'],
                      'Prestataires' => ['Signaler un prestataire','Devenir prestataire','Litige avec un prestataire'],
                      'Autre'        => ["Suggestion d'amélioration",'Partenariat commercial','Autre question'],
                  ];
                  foreach ($groupes as $label => $options): ?>
                    <optgroup label="<?= e($label) ?>">
                      <?php foreach ($options as $opt): ?>
                        <option <?= (($vd['sujet'] ?? '') === $opt) ? 'selected' : '' ?>><?= e($opt) ?></option>
                      <?php endforeach; ?>
                    </optgroup>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="fg full">
                <label class="fl">Priorité</label>
                <div class="priority-grid">
                  <label class="prio-lbl"><input type="radio" name="priorite" value="urgent" <?= ($vd['priorite'] ?? '')==='urgent'?'checked':'' ?>>🔴 Urgent</label>
                  <label class="prio-lbl"><input type="radio" name="priorite" value="normal" <?= ($vd['priorite'] ?? 'normal')==='normal'?'checked':'' ?>>🟡 Normal</label>
                  <label class="prio-lbl"><input type="radio" name="priorite" value="faible" <?= ($vd['priorite'] ?? '')==='faible'?'checked':'' ?>>🟢 Faible</label>
                </div>
              </div>

              <div class="fg full"><label class="fl">Numéro de réservation (si applicable)</label><div class="iw"><span class="fi-ic">🔢</span><input type="text" name="resnum" class="fi" placeholder="Ex: SP-2026-4F8C1A" value="<?= e($vd['resnum'] ?? '') ?>"></div></div>

              <div class="fg full">
                <label class="fl">Votre message *</label>
                <textarea name="message" class="fi no-ic" rows="5" placeholder="Décrivez votre problème ou question en détail (20 caractères min.)..."><?= e($vd['message'] ?? '') ?></textarea>
              </div>

              <div class="fg full">
                <div class="attach-zone">📎 Pièces jointes — bientôt disponible</div>
              </div>

              <div class="fg full">
                <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;">
                  <input type="checkbox" name="rgpd" style="width:17px;height:17px;accent-color:var(--primary);margin-top:2px;flex-shrink:0;cursor:pointer;" <?= isset($vd['rgpd'])?'checked':'' ?>>
                  <span style="font-size:13px;color:var(--slate);line-height:1.6;">J'accepte que ServicesPlus traite mes données personnelles pour répondre à ma demande, conformément à la <a href="#" style="color:var(--primary);font-weight:700;">Politique de confidentialité</a>. *</span>
                </label>
              </div>
            </div>

            <button type="submit" name="envoyer" value="1" class="btn-submit">📨 Envoyer le message</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <div>
      <div class="info-card">
        <div class="ic-header"><div class="ic-header-title">📞 Nos coordonnées</div><div class="ic-header-sub">Plusieurs façons de nous joindre</div></div>
        <div class="ic-body">
          <div class="contact-row"><div class="cr-icon" style="background:#FFF0EB;">📧</div><div><div class="cr-label">Email</div><div class="cr-value">contact@servicesplus.sn</div></div></div>
          <div class="contact-row"><div class="cr-icon" style="background:#ECFDF5;">📞</div><div><div class="cr-label">Téléphone</div><div class="cr-value">+221 33 000 00 00</div><div class="cr-sub">Lun–Sam de 8h à 20h</div></div></div>
          <div class="contact-row"><div class="cr-icon" style="background:#EFF6FF;">📱</div><div><div class="cr-label">WhatsApp</div><div class="cr-value">+221 77 000 00 00</div></div></div>
          <div class="contact-row"><div class="cr-icon" style="background:#F5F3FF;">📍</div><div><div class="cr-label">Adresse</div><div class="cr-value">Plateau, Dakar, Sénégal</div></div></div>
        </div>
      </div>

      <div class="info-card">
        <div class="ic-header" style="background:linear-gradient(135deg,#059669,#34D399);"><div class="ic-header-title">🕐 Horaires du support</div></div>
        <div class="ic-body">
          <?php foreach (['Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi','Dimanche'] as $i => $j):
            $ouvert = $i < 6; ?>
            <div class="horaire-row"><span class="horaire-jour"><?= $j ?></span><span class="horaire-heure <?= $ouvert?'ouvert':'ferme' ?>"><?= $ouvert?'8h - 20h':'Fermé' ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($faqMini): ?>
      <div class="info-card">
        <div class="ic-header" style="background:linear-gradient(135deg,#7C3AED,#A78BFA);"><div class="ic-header-title">❓ Questions fréquentes</div></div>
        <div class="ic-body">
          <?php foreach ($faqMini as $f): ?>
            <a class="faq-mini" href="faq.php#faq-<?= $f['id'] ?>"><div class="faq-mini-q">❓ <?= e($f['question']) ?></div></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require 'includes/footer.php'; ?>