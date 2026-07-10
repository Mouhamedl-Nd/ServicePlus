<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('client', '../');

$clientId = $_SESSION['user_id'];
$prestaIdUrl = (int)($_GET['id'] ?? 0);

$prestataires = $pdo->query("
    SELECT u.id, u.nom, u.prenom, u.ville, pp.tarif_horaire, pp.note_moyenne, pp.nb_avis, pp.categorie_id, c.nom AS categorie_nom, c.icone
    FROM prestataire_profils pp
    JOIN utilisateurs u ON u.id = pp.user_id
    JOIN categories c ON c.id = pp.categorie_id
    WHERE pp.statut_validation = 'valide'
    ORDER BY pp.note_moyenne DESC
")->fetchAll();

$erreurs = [];
$succes  = false;
$referenceGeneree = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer'])) {
    $prestataireId = (int)$_POST['prestataire_id'];
    $date = $_POST['date'] ?? '';
    $heure = $_POST['heure'] ?? '';
    $duree = max(1, (int)($_POST['duree'] ?? 1));
    $adresse = trim($_POST['adresse'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $urgence = isset($_POST['urgence']) ? 1 : 0;
    $methodePaiement = in_array($_POST['methode_paiement'] ?? '', ['carte','orange_money','wave','especes']) ? $_POST['methode_paiement'] : 'especes';

    $stmtP = $pdo->prepare("SELECT u.id, u.ville, pp.tarif_horaire, pp.categorie_id FROM prestataire_profils pp JOIN utilisateurs u ON u.id=pp.user_id WHERE u.id=? AND pp.statut_validation='valide'");
    $stmtP->execute([$prestataireId]);
    $presta = $stmtP->fetch();

    if (!$presta) { $erreurs[] = 'Prestataire introuvable.'; }
    if (!$date || !$heure) { $erreurs[] = 'Veuillez choisir une date et un créneau.'; }
    if (!$adresse) { $erreurs[] = 'Veuillez indiquer une adresse d\'intervention.'; }

    if (!$erreurs) {
        $montant = round($presta['tarif_horaire'] * $duree * 1.05, 0);
        $reference = genererReference();
        $pdo->prepare("INSERT INTO reservations (client_id, prestataire_id, categorie_id, description, adresse, ville, date_reservation, duree_heures, montant, methode_paiement, statut, reference) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$clientId, $prestataireId, $presta['categorie_id'], $description, $adresse, $presta['ville'] ?? 'Dakar', "$date $heure:00", $duree, $montant, $methodePaiement, 'confirmee', $reference]);

        creerNotification($pdo, $prestataireId, 'Nouvelle réservation 📅', "Vous avez une nouvelle réservation le $date à $heure.", 'reservation', '../espace_prestataire/dashboard_prestataire.php');

        $succes = true;
        $referenceGeneree = $reference;
        $confirmData = ['presta'=>$presta, 'date'=>$date, 'heure'=>$heure, 'duree'=>$duree, 'adresse'=>$adresse, 'montant'=>$montant, 'methode'=>$methodePaiement];

        // On recharge les infos du prestataire choisi pour l'affichage de confirmation
        $stmtInfo = $pdo->prepare("SELECT u.prenom, u.nom, c.nom AS categorie_nom FROM utilisateurs u JOIN prestataire_profils pp ON pp.user_id=u.id JOIN categories c ON c.id=pp.categorie_id WHERE u.id=?");
        $stmtInfo->execute([$prestataireId]);
        $prestaInfo = $stmtInfo->fetch();
    }
}

$page_title  = 'ServicesPlus – Réservation';
$racine      = '../';
$extra_css   = <<<CSS
.container{max-width:1000px;margin:0 auto;padding:32px 20px}
.page-title{font-family:'Playfair Display',serif;font-size:26px;font-weight:900;margin-bottom:6px}
.page-sub{color:var(--slate);font-size:14px;margin-bottom:28px}
.steps-bar{background:white;border-radius:16px;padding:20px 28px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);display:flex;align-items:center;margin-bottom:32px}
.step-item{display:flex;flex-direction:column;align-items:center;gap:6px;flex:1}
.step-circle{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:14px}
.step-circle.done{background:#059669;color:white}
.step-circle.active{background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;box-shadow:0 4px 12px rgba(232,93,38,0.3)}
.step-circle.pending{background:#F1F5F9;color:#94A3B8}
.step-label{font-size:11px;font-weight:700;white-space:nowrap}
.step-label.done{color:#059669} .step-label.active{color:var(--primary)} .step-label.pending{color:#94A3B8}
.step-line{flex:1;height:2px;margin:0 8px;margin-bottom:18px}
.step-line.done{background:linear-gradient(to right,#E85D26,#F59E0B)} .step-line.pending{background:#E9EEF4}
.content-layout{display:grid;grid-template-columns:1fr 310px;gap:22px;align-items:start}
.card{background:white;border-radius:20px;padding:28px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05)}
.card-title{font-weight:800;font-size:18px;margin-bottom:22px}
.presta-choice{display:flex;gap:14px;align-items:center;padding:16px;border-radius:14px;border:2px solid var(--border);cursor:pointer;transition:all .2s;margin-bottom:10px}
.presta-choice:hover,.presta-choice.selected{border-color:var(--primary);background:var(--primary-light)}
.p-avatar{width:54px;height:54px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0;background:#FFE8DF}
.p-name{font-weight:800;font-size:15px;margin-bottom:2px}
.p-service{color:var(--slate);font-size:12px;margin-bottom:5px}
.p-stars{color:#F59E0B;font-size:12px}
.p-right{margin-left:auto;text-align:right}
.p-prix{font-weight:900;color:var(--primary);font-size:15px}
.radio-c{width:20px;height:20px;border-radius:50%;border:2px solid var(--border);flex-shrink:0;display:flex;align-items:center;justify-content:center}
.presta-choice.selected .radio-c{border-color:var(--primary);background:var(--primary)}
.radio-dot{width:8px;height:8px;border-radius:50%;background:white;opacity:0}
.presta-choice.selected .radio-dot{opacity:1}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-group{display:flex;flex-direction:column;gap:7px}
.form-group.full{grid-column:1/-1}
.form-label{font-size:11px;font-weight:700;color:var(--slate);text-transform:uppercase;letter-spacing:.5px}
.form-input{padding:12px 16px;border-radius:11px;border:1.5px solid var(--border);font-size:14px;font-family:'Inter',sans-serif;color:var(--dark);outline:none;width:100%}
textarea.form-input{resize:vertical;min-height:90px}
.creneaux-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:4px}
.creneau-btn{padding:10px 0;border-radius:10px;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:13px;font-weight:700;color:var(--slate);text-align:center}
.creneau-btn:hover{border-color:var(--primary);color:var(--primary);background:var(--primary-light)}
.creneau-btn.active{border-color:var(--primary);background:var(--primary);color:white}
.pay-opt{display:flex;align-items:center;gap:14px;padding:14px 18px;border-radius:14px;border:1.5px solid var(--border);cursor:pointer;transition:all .2s;margin-bottom:10px}
.pay-opt:hover,.pay-opt.selected{border-color:var(--primary);background:var(--primary-light)}
.pay-icon{font-size:24px}
.pay-name{font-weight:700;font-size:14px}
.pay-desc{font-size:12px;color:var(--slate)}
.pay-radio{margin-left:auto;width:20px;height:20px;border-radius:50%;border:2px solid var(--border);display:flex;align-items:center;justify-content:center}
.pay-opt.selected .pay-radio{border-color:var(--primary);background:var(--primary)}
.pay-radio-dot{width:8px;height:8px;border-radius:50%;background:white;opacity:0}
.pay-opt.selected .pay-radio-dot{opacity:1}
.sec-badge{display:flex;align-items:center;gap:8px;background:#ECFDF5;border-radius:10px;padding:10px 14px;font-size:12px;color:#059669;font-weight:600;margin-top:14px}
.confirm-wrap{text-align:center;padding:20px 0}
.confirm-icon{width:80px;height:80px;border-radius:24px;background:#ECFDF5;display:flex;align-items:center;justify-content:center;font-size:40px;margin:0 auto 22px}
.confirm-title{font-family:'Playfair Display',serif;font-size:28px;font-weight:900;color:#059669;margin-bottom:10px}
.confirm-sub{color:var(--slate);font-size:15px;line-height:1.7;margin-bottom:28px}
.confirm-box{background:#F8FAFC;border-radius:16px;padding:20px;text-align:left;margin-bottom:24px}
.confirm-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid #E9EEF4;font-size:14px}
.confirm-row:last-child{border-bottom:none;font-size:16px;font-weight:900}
.confirm-row span:first-child{color:var(--slate)}
.confirm-actions{display:flex;gap:12px;justify-content:center}
.recap-card{background:white;border-radius:20px;padding:24px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);position:sticky;top:86px}
.recap-title{font-weight:800;font-size:16px;margin-bottom:18px;padding-bottom:14px;border-bottom:1px solid #F1F5F9}
.recap-presta{display:flex;gap:12px;align-items:center;background:#F8FAFC;border-radius:12px;padding:14px;margin-bottom:18px}
.recap-row{display:flex;justify-content:space-between;font-size:13px;padding:8px 0;border-bottom:1px solid #F8FAFC}
.recap-row span:first-child{color:var(--slate)} .recap-row span:last-child{font-weight:600}
.recap-total{display:flex;justify-content:space-between;font-size:17px;font-weight:900;padding-top:14px;border-top:2px solid #F1F5F9;margin-top:8px}
.btn-nav{display:flex;gap:12px;margin-top:22px}
.btn-back{padding:13px 28px;background:#F8FAFC;color:var(--slate);border:1.5px solid var(--border);border-radius:13px;font-weight:700;font-size:14px;cursor:pointer}
.btn-next{padding:13px 28px;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:13px;font-weight:800;font-size:14px;cursor:pointer}
.btn-prim{padding:13px 28px;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:13px;font-weight:800;font-size:14px;cursor:pointer;text-decoration:none;display:inline-block}
.btn-sec{padding:13px 28px;background:#F8FAFC;color:var(--slate);border:1.5px solid var(--border);border-radius:13px;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none;display:inline-block}
.alert-err{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;padding:12px 16px;border-radius:12px;font-size:13px;margin-bottom:16px}
@media(max-width:900px){ .content-layout{grid-template-columns:1fr} .form-grid{grid-template-columns:1fr} .creneaux-grid{grid-template-columns:repeat(2,1fr)} }
CSS;

require '../includes/header.php';
?>

<div class="container">
  <div class="page-title">📅 Nouvelle réservation</div>
  <div class="page-sub">Réservez un prestataire en quelques étapes simples</div>

  <?php if ($succes): ?>
    <div class="card">
      <div class="confirm-wrap">
        <div class="confirm-icon">✅</div>
        <div class="confirm-title">Réservation confirmée !</div>
        <div class="confirm-sub">Votre réservation a été envoyée à <strong><?= e($prestaInfo['prenom'].' '.$prestaInfo['nom']) ?></strong>. Il/elle a été notifié(e).</div>
        <div class="confirm-box">
          <div class="confirm-row"><span>N° Réservation</span><span style="font-weight:800;color:var(--primary);"><?= e($referenceGeneree) ?></span></div>
          <div class="confirm-row"><span>Prestataire</span><span><?= e($prestaInfo['prenom'].' '.$prestaInfo['nom']) ?></span></div>
          <div class="confirm-row"><span>Service</span><span><?= e($prestaInfo['categorie_nom']) ?></span></div>
          <div class="confirm-row"><span>Date & Heure</span><span><?= formaterDate($confirmData['date'].' '.$confirmData['heure'].':00') ?></span></div>
          <div class="confirm-row"><span>Adresse</span><span><?= e($confirmData['adresse']) ?></span></div>
          <div class="confirm-row"><span>Montant</span><span style="color:var(--primary);"><?= number_format($confirmData['montant'],0,',',' ') ?> FCFA</span></div>
        </div>
        <div class="confirm-actions">
          <a class="btn-prim" href="dashboard_client.php">Voir mes réservations →</a>
          <a class="btn-sec" href="reservation.php">Nouvelle réservation</a>
        </div>
      </div>
    </div>
  <?php else: ?>

  <div class="steps-bar" id="stepsBar"></div>

  <?php if ($erreurs): ?><div class="alert-err">❌ <?= e(implode(' ', $erreurs)) ?></div><?php endif; ?>

  <form method="post" id="formRes">
    <input type="hidden" name="prestataire_id" id="prestataireIdInput" value="<?= $prestaIdUrl ?: ($prestataires[0]['id'] ?? 0) ?>">
    <div class="content-layout">
      <div>
        <div id="step1" class="card">
          <div class="card-title">👤 Choisissez votre prestataire</div>
          <?php if (empty($prestataires)): ?>
            <p style="color:var(--slate);">Aucun prestataire validé disponible pour le moment. <a href="../recherche.php" style="color:var(--primary);">Voir la recherche</a></p>
          <?php else: foreach ($prestataires as $p): ?>
            <div class="presta-choice" data-id="<?= $p['id'] ?>" data-nom="<?= e($p['prenom'].' '.$p['nom']) ?>" data-service="<?= e($p['categorie_nom']) ?>" data-prix="<?= $p['tarif_horaire'] ?>" data-note="<?= $p['note_moyenne'] ?>" data-avis="<?= $p['nb_avis'] ?>" onclick="selectPresta(this)">
              <div class="p-avatar"><?= $p['icone'] ?></div>
              <div>
                <div class="p-name"><?= e($p['prenom'].' '.$p['nom']) ?></div>
                <div class="p-service"><?= e($p['categorie_nom']) ?> • <?= e($p['ville'] ?? 'Dakar') ?></div>
                <div class="p-stars">★ <?= e((string)$p['note_moyenne']) ?> (<?= (int)$p['nb_avis'] ?> avis)</div>
              </div>
              <div class="p-right">
                <div class="p-prix"><?= number_format($p['tarif_horaire'],0,',',' ') ?> F/h</div>
              </div>
              <div class="radio-c"><div class="radio-dot"></div></div>
            </div>
          <?php endforeach; endif; ?>
          <div class="btn-nav"><button type="button" class="btn-next" style="flex:1;" onclick="goStep(2)">Continuer →</button></div>
        </div>

        <div id="step2" class="card" style="display:none;">
          <div class="card-title">📅 Date, heure et détails</div>
          <div class="form-grid">
            <div class="form-group"><label class="form-label">Date d'intervention</label><input type="date" class="form-input" name="date" id="dateInput" onchange="updateRecap()" required></div>
            <div class="form-group">
              <label class="form-label">Durée estimée</label>
              <select class="form-input" name="duree" id="dureeInput" onchange="updateRecap()">
                <?php for ($h=1;$h<=6;$h++): ?><option value="<?= $h ?>" <?= $h==2?'selected':'' ?>><?= $h ?> heure<?= $h>1?'s':'' ?></option><?php endfor; ?>
              </select>
            </div>
            <div class="form-group full">
              <label class="form-label">Choisir un créneau</label>
              <input type="hidden" name="heure" id="heureInput" value="14:00">
              <div class="creneaux-grid" id="creneauxGrid">
                <?php foreach (['08:00','09:00','11:00','13:00','14:00','15:00','16:00','18:00'] as $h): ?>
                  <div class="creneau-btn <?= $h==='14:00'?'active':'' ?>" onclick="selectCreneau(this,'<?= $h ?>')"><?= $h ?></div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="form-group full"><label class="form-label">Adresse d'intervention</label><input type="text" class="form-input" name="adresse" placeholder="Ex : 12 Rue Félix Faure, Dakar" required></div>
            <div class="form-group full"><label class="form-label">Description du problème</label><textarea class="form-input" name="description" placeholder="Décrivez votre besoin en quelques mots..."></textarea></div>
            <div class="form-group full" style="flex-direction:row;align-items:center;gap:10px;">
              <input type="checkbox" name="urgence" id="urgenceCheck" style="width:17px;height:17px;accent-color:var(--primary);">
              <label for="urgenceCheck" style="font-size:13px;color:var(--slate);">Intervention urgente (dès que possible)</label>
            </div>
          </div>
          <div class="btn-nav">
            <button type="button" class="btn-back" onclick="goStep(1)">← Retour</button>
            <button type="button" class="btn-next" onclick="verifierEtape2()">Continuer vers le paiement →</button>
          </div>
        </div>

        <div id="step3" class="card" style="display:none;">
          <div class="card-title">💳 Mode de paiement</div>
          <div id="payOptions">
            <?php foreach ([['carte','💳','Carte bancaire','Visa, Mastercard'],['orange_money','📱','Orange Money','Paiement mobile'],['wave','🌊','Wave','Paiement rapide'],['especes','💵','Espèces','En main propre après intervention']] as $i => $pay): ?>
              <div class="pay-opt <?= $i===0?'selected':'' ?>" data-val="<?= $pay[0] ?>" onclick="selectPay(this)">
                <span class="pay-icon"><?= $pay[1] ?></span>
                <div><div class="pay-name"><?= $pay[2] ?></div><div class="pay-desc"><?= $pay[3] ?></div></div>
                <div class="pay-radio"><div class="pay-radio-dot"></div></div>
              </div>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="methode_paiement" id="methodePaiementInput" value="carte">
          <div class="sec-badge">🔒 Réservation sécurisée — le paiement en espèces se règle directement au prestataire</div>
          <div class="btn-nav">
            <button type="button" class="btn-back" onclick="goStep(2)">← Retour</button>
            <button type="submit" name="confirmer" value="1" class="btn-next">🔒 Confirmer la réservation →</button>
          </div>
        </div>
      </div>

      <div class="recap-card">
        <div class="recap-title">📋 Récapitulatif</div>
        <div class="recap-presta">
          <span id="recapIcon" style="font-size:28px;">🧑‍🔧</span>
          <div><div id="recapName" style="font-weight:700;font-size:14px;">—</div><div id="recapNote" style="color:#F59E0B;font-size:12px;">—</div></div>
        </div>
        <div class="recap-row"><span>Service</span><span id="rService">—</span></div>
        <div class="recap-row"><span>Durée</span><span id="rDuree">2 heures</span></div>
        <div class="recap-row"><span>Tarif horaire</span><span id="rTarif">—</span></div>
        <div class="recap-row"><span>Sous-total</span><span id="rSousTotal">—</span></div>
        <div class="recap-row"><span>Frais service (5%)</span><span id="rFrais">—</span></div>
        <div class="recap-total"><span>Total</span><span id="rTotal">—</span></div>
      </div>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
  let currentStep = 1;
  const LABELS = ["Prestataire","Date & Heure","Paiement"];
  let selPrix = 0, selDuree = 2;

  function renderSteps() {
    document.getElementById('stepsBar').innerHTML = LABELS.map((lbl,i) => {
      const n = i+1;
      const state = n < currentStep ? 'done' : (n === currentStep ? 'active' : 'pending');
      const line = i < LABELS.length-1 ? `<div class="step-line ${n < currentStep ? 'done':'pending'}"></div>` : '';
      return `<div class="step-item"><div class="step-circle ${state}">${n < currentStep ? '✓' : n}</div><div class="step-label ${state}">${lbl}</div></div>${line}`;
    }).join('');
  }
  function goStep(n) {
    currentStep = n;
    ['step1','step2','step3'].forEach((id,i) => document.getElementById(id).style.display = (i+1===n) ? 'block' : 'none');
    renderSteps();
    window.scrollTo({top:0,behavior:'smooth'});
  }
  function selectPresta(el) {
    document.querySelectorAll('.presta-choice').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('prestataireIdInput').value = el.dataset.id;
    selPrix = parseFloat(el.dataset.prix);
    document.getElementById('recapName').textContent = el.dataset.nom;
    document.getElementById('recapNote').textContent = '★ ' + el.dataset.note + ' (' + el.dataset.avis + ' avis)';
    document.getElementById('rService').textContent = el.dataset.service;
    document.getElementById('rTarif').textContent = selPrix.toLocaleString() + ' FCFA/h';
    updateRecap();
  }
  function selectCreneau(el, h) {
    document.querySelectorAll('.creneau-btn').forEach(b => b.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('heureInput').value = h;
  }
  function selectPay(el) {
    document.querySelectorAll('.pay-opt').forEach(p => p.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('methodePaiementInput').value = el.dataset.val;
  }
  function updateRecap() {
    selDuree = parseInt(document.getElementById('dureeInput')?.value || 2);
    document.getElementById('rDuree').textContent = selDuree + ' heure' + (selDuree>1?'s':'');
    const sousTotal = selPrix * selDuree;
    const frais = Math.round(sousTotal * 0.05);
    document.getElementById('rSousTotal').textContent = sousTotal.toLocaleString() + ' FCFA';
    document.getElementById('rFrais').textContent = frais.toLocaleString() + ' FCFA';
    document.getElementById('rTotal').textContent = (sousTotal+frais).toLocaleString() + ' FCFA';
  }
  function verifierEtape2() {
    if (!document.getElementById('dateInput').value) { alert('Veuillez choisir une date.'); return; }
    if (!document.querySelector('[name="adresse"]').value.trim()) { alert('Veuillez indiquer une adresse.'); return; }
    goStep(3);
  }

  window.onload = () => {
    const today = new Date().toISOString().split('T')[0];
    const dateInput = document.getElementById('dateInput');
    if (dateInput) { dateInput.min = today; dateInput.value = today; }
    const preselect = document.querySelector('.presta-choice[data-id="<?= (int)($prestaIdUrl ?: 0) ?>"]') || document.querySelector('.presta-choice');
    if (preselect) selectPresta(preselect);
    renderSteps();
  };
</script>

<?php require '../includes/footer.php'; ?>