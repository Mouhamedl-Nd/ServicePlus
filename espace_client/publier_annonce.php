<?php
require '../config/connexion.php';
require '../config/session.php';
require '../includes/fonctions.php';
verifierRole('client', '../');

$clientId = $_SESSION['user_id'];
$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();

$erreurs = []; $succes = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['publier'])) {
    $categorieId = (int)($_POST['categorie_id'] ?? 0);
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $ville = trim($_POST['ville'] ?? '');
    $budget = $_POST['budget'] !== '' ? (float)$_POST['budget'] : null;
    $dateSouhaitee = $_POST['date_souhaitee'] ?? '';
    $urgence = isset($_POST['urgence']) ? 1 : 0;

    if (!$categorieId || !$titre || !$description || !$adresse || !$ville || !$dateSouhaitee) {
        $erreurs[] = 'Veuillez remplir tous les champs obligatoires.';
    }

    if (!$erreurs) {
        $pdo->prepare('INSERT INTO annonces (client_id, categorie_id, titre, description, adresse, ville, budget, date_souhaitee, urgence) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([$clientId, $categorieId, $titre, $description, $adresse, $ville, $budget, $dateSouhaitee, $urgence]);
        $annonceId = (int)$pdo->lastInsertId();

        // Notifier tous les prestataires validés de cette catégorie
        $stmtP = $pdo->prepare("SELECT user_id FROM prestataire_profils WHERE categorie_id=? AND statut_validation='valide'");
        $stmtP->execute([$categorieId]);
        foreach ($stmtP->fetchAll() as $p) {
            creerNotification($pdo, $p['user_id'], 'Nouvelle annonce disponible 📢', "Une nouvelle demande \"$titre\" correspond à votre catégorie.", 'annonce', '../espace_prestataire/dashboard_prestataire.php');
        }
        $succes = true;
    }
}

/* ── Accepter une candidature ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accepter_candidature'])) {
    $candId = (int)$_POST['accepter_candidature'];
    $stmt = $pdo->prepare("SELECT c.*, a.client_id, a.titre, a.adresse, a.ville, a.categorie_id, a.date_souhaitee
                           FROM candidatures c JOIN annonces a ON a.id=c.annonce_id
                           WHERE c.id=? AND a.client_id=?");
    $stmt->execute([$candId, $clientId]);
    $cand = $stmt->fetch();

    if ($cand) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE candidatures SET statut='acceptee' WHERE id=?")->execute([$candId]);
        $pdo->prepare("UPDATE candidatures SET statut='refusee' WHERE annonce_id=? AND id!=?")->execute([$cand['annonce_id'], $candId]);
        $pdo->prepare("UPDATE annonces SET statut='attribuee', prestataire_choisi_id=? WHERE id=?")->execute([$cand['prestataire_id'], $cand['annonce_id']]);

        $montant = $cand['tarif_propose'] ?: 0;
        $reference = genererReference();
        $pdo->prepare("INSERT INTO reservations (annonce_id, client_id, prestataire_id, categorie_id, description, adresse, ville, date_reservation, duree_heures, montant, methode_paiement, statut, reference) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$cand['annonce_id'], $clientId, $cand['prestataire_id'], $cand['categorie_id'], $cand['titre'], $cand['adresse'], $cand['ville'], $cand['date_souhaitee'].' 09:00:00', 2, $montant, 'especes', 'confirmee', $reference]);

        $pdo->commit();

        creerNotification($pdo, $cand['prestataire_id'], 'Candidature acceptée ✅', "Votre candidature pour \"{$cand['titre']}\" a été acceptée !", 'reservation', '../espace_prestataire/dashboard_prestataire.php');
    }
    header('Location: publier_annonce.php'); exit;
}

/* ── Mes annonces + candidatures ── */
$stmt = $pdo->prepare("
    SELECT a.*, c.nom AS categorie_nom, c.icone,
           (SELECT COUNT(*) FROM candidatures WHERE annonce_id=a.id) AS nb_candidatures
    FROM annonces a JOIN categories c ON c.id=a.categorie_id
    WHERE a.client_id = ? ORDER BY a.date_creation DESC
");
$stmt->execute([$clientId]);
$mesAnnonces = $stmt->fetchAll();

foreach ($mesAnnonces as &$a) {
    $stmtC = $pdo->prepare("
        SELECT c.*, u.prenom, u.nom, pp.tarif_horaire, pp.note_moyenne, pp.nb_avis
        FROM candidatures c
        JOIN utilisateurs u ON u.id=c.prestataire_id
        JOIN prestataire_profils pp ON pp.user_id=u.id
        WHERE c.annonce_id=? ORDER BY c.date_creation DESC
    ");
    $stmtC->execute([$a['id']]);
    $a['candidatures'] = $stmtC->fetchAll();
}
unset($a);

$statutLabels = ['ouverte'=>['Ouverte','badge-blue'],'en_discussion'=>['En discussion','badge-orange'],'attribuee'=>['Attribuée','badge-green'],'terminee'=>['Terminée','badge-gray'],'annulee'=>['Annulée','badge-red']];

$page_title  = 'ServicesPlus – Publier une annonce';
$page_active = 'publier';
$racine      = '../';
$extra_css   = <<<CSS
.container{max-width:900px;margin:0 auto;padding:32px 20px}
.card{background:white;border-radius:18px;padding:26px;border:1.5px solid var(--border);box-shadow:0 2px 8px rgba(15,23,42,0.05);margin-bottom:20px}
.card-title{font-weight:800;font-size:17px;margin-bottom:18px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.fg{display:flex;flex-direction:column;gap:7px;margin-bottom:14px}
.fg.full{grid-column:1/-1}
.fl{font-size:11px;font-weight:700;color:var(--slate);text-transform:uppercase;letter-spacing:.5px}
.fi{width:100%;padding:11px 14px;border-radius:10px;border:1.5px solid var(--border);font-size:13.5px;font-family:'Inter',sans-serif}
textarea.fi{resize:vertical;min-height:90px}
.btn-p{padding:12px 26px;background:linear-gradient(135deg,#E85D26,#F59E0B);color:white;border:none;border-radius:11px;font-weight:800;font-size:14px;cursor:pointer}
.alert-ok{background:#ECFDF5;color:#059669;border:1px solid #A7F3D0;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
.alert-err{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;padding:11px 14px;border-radius:10px;font-size:13px;margin-bottom:16px}
.annonce-item{border:1.5px solid var(--border);border-radius:14px;padding:16px 18px;margin-bottom:12px}
.annonce-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
.annonce-titre{font-weight:800;font-size:14.5px}
.annonce-meta{font-size:12px;color:var(--slate);margin-top:3px}
.badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.badge-blue{color:#2563EB;background:#EFF6FF} .badge-green{color:#059669;background:#ECFDF5}
.badge-orange{color:#D97706;background:#FFFBEB} .badge-gray{color:#64748B;background:#F1F5F9}
.badge-red{color:#DC2626;background:#FEF2F2}
.cand-list{margin-top:12px;border-top:1px solid #F1F5F9;padding-top:12px}
.cand-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #F8FAFC}
.cand-item:last-child{border-bottom:none}
.cand-name{font-weight:700;font-size:13px}
.cand-sub{font-size:11.5px;color:var(--slate)}
.btn-mini{padding:6px 12px;border-radius:8px;border:none;font-size:11px;font-weight:800;cursor:pointer;margin-left:auto}
.btn-mini.green{background:#ECFDF5;color:#059669}
.empty-mini{text-align:center;padding:30px;color:var(--slate);font-size:13px}
@media(max-width:700px){ .form-grid{grid-template-columns:1fr} }
CSS;

require '../includes/header.php';
?>

<div class="container">
  <div class="card">
    <div class="card-title">📢 Publier une nouvelle annonce</div>
    <?php if ($succes): ?><div class="alert-ok">✅ Votre annonce a été publiée ! Les prestataires de cette catégorie ont été notifiés.</div><?php endif; ?>
    <?php if ($erreurs): ?><div class="alert-err">❌ <?= e(implode(' ', $erreurs)) ?></div><?php endif; ?>
    <form method="post">
      <div class="form-grid">
        <div class="fg full"><label class="fl">Titre de la demande *</label><input type="text" name="titre" class="fi" placeholder="Ex : Réparation fuite d'eau urgente" required></div>
        <div class="fg"><label class="fl">Catégorie *</label>
          <select name="categorie_id" class="fi" required>
            <option value="">Sélectionnez</option>
            <?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>"><?= $c['icone'] ?> <?= e($c['nom']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fg"><label class="fl">Budget indicatif (FCFA, optionnel)</label><input type="number" name="budget" class="fi" placeholder="Ex : 15000"></div>
        <div class="fg"><label class="fl">Ville *</label><input type="text" name="ville" class="fi" value="Dakar" required></div>
        <div class="fg"><label class="fl">Date souhaitée *</label><input type="date" name="date_souhaitee" class="fi" min="<?= date('Y-m-d') ?>" required></div>
        <div class="fg full"><label class="fl">Adresse *</label><input type="text" name="adresse" class="fi" placeholder="Adresse de l'intervention" required></div>
        <div class="fg full"><label class="fl">Description *</label><textarea name="description" class="fi" placeholder="Décrivez votre besoin en détail..." required></textarea></div>
        <div class="fg full" style="flex-direction:row;align-items:center;gap:10px;">
          <input type="checkbox" name="urgence" id="urg" style="width:17px;height:17px;accent-color:var(--primary);">
          <label for="urg" style="font-size:13px;color:var(--slate);">Demande urgente</label>
        </div>
      </div>
      <button type="submit" name="publier" value="1" class="btn-p">📢 Publier l'annonce</button>
    </form>
  </div>

  <div class="card">
    <div class="card-title">📋 Mes annonces publiées</div>
    <?php if (empty($mesAnnonces)): ?>
      <div class="empty-mini">Vous n'avez publié aucune annonce pour le moment.</div>
    <?php else: foreach ($mesAnnonces as $a): [$lbl,$cls] = $statutLabels[$a['statut']] ?? ['—','badge-gray']; ?>
      <div class="annonce-item">
        <div class="annonce-top">
          <div>
            <div class="annonce-titre"><?= $a['icone'] ?> <?= e($a['titre']) ?></div>
            <div class="annonce-meta"><?= e($a['categorie_nom']) ?> • <?= e($a['ville']) ?> • Souhaitée le <?= date('d/m/Y', strtotime($a['date_souhaitee'])) ?> • <?= (int)$a['nb_candidatures'] ?> candidature<?= $a['nb_candidatures']>1?'s':'' ?></div>
          </div>
          <span class="badge <?= $cls ?>"><?= $lbl ?></span>
        </div>
        <?php if ($a['candidatures']): ?>
          <div class="cand-list">
            <?php foreach ($a['candidatures'] as $c): ?>
              <div class="cand-item">
                <div>
                  <div class="cand-name"><?= e($c['prenom'].' '.$c['nom']) ?></div>
                  <div class="cand-sub">⭐ <?= e((string)$c['note_moyenne']) ?> (<?= $c['nb_avis'] ?> avis) <?= $c['tarif_propose'] ? '• Propose '.number_format($c['tarif_propose'],0,',',' ').' FCFA' : '' ?><?= $c['message'] ? ' — "'.e($c['message']).'"' : '' ?></div>
                </div>
                <?php if ($a['statut']==='ouverte' || $a['statut']==='en_discussion'): ?>
                  <a class="btn-mini" style="background:#EFF6FF;color:#2563EB;margin-left:auto;text-decoration:none;" href="messagerie.php?annonce_id=<?= $a['id'] ?>&avec=<?= $c['prestataire_id'] ?>">💬 Discuter</a>
                  <?php if ($c['statut']==='en_attente'): ?>
                    <form method="post"><input type="hidden" name="accepter_candidature" value="<?= $c['id'] ?>"><button type="submit" class="btn-mini green" onclick="return confirm('Accepter ce prestataire ? Une réservation sera créée automatiquement.')">✓ Accepter</button></form>
                  <?php endif; ?>
                <?php elseif ($c['statut']==='acceptee'): ?>
                  <span class="badge badge-green">Choisi</span>
                <?php elseif ($c['statut']==='refusee'): ?>
                  <span class="badge badge-gray">Non retenu</span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<?php require '../includes/footer.php'; ?>