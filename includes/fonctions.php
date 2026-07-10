<?php
/**
 * Fonctions utilitaires communes à tout le projet ServicesPlus.
 */

/** Echappe une chaîne pour un affichage HTML sûr (anti XSS). */
function e(?string $texte): string
{
    return htmlspecialchars($texte ?? '', ENT_QUOTES, 'UTF-8');
}

/** Génère une référence unique de réservation, ex : SP-2026-4F8C1A */
function genererReference(): string
{
    return 'SP-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

/** Affiche une date au format français lisible (ex : 12 juillet 2026 à 14h30). */
function formaterDate(string $dateSql): string
{
    $mois = [1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',
              7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre'];
    $ts = strtotime($dateSql);
    return date('j', $ts) . ' ' . $mois[(int)date('n', $ts)] . ' ' . date('Y', $ts)
         . ' à ' . date('H\hi', $ts);
}

/** Temps écoulé depuis une date (pour les notifications), ex : "il y a 3h". */
function ilYA(string $dateSql): string
{
    $diff = time() - strtotime($dateSql);
    if ($diff < 60)     return 'à l\'instant';
    if ($diff < 3600)   return 'il y a ' . floor($diff / 60) . ' min';
    if ($diff < 86400)  return 'il y a ' . floor($diff / 3600) . 'h';
    return 'il y a ' . floor($diff / 86400) . ' j';
}

/**
 * Upload sécurisé d'un fichier (photo de profil, pièce d'identité...).
 * Retourne le chemin relatif stocké en BD, ou null en cas d'échec.
 */
function uploaderFichier(array $fichier, string $sousDossier = 'divers'): ?string
{
    if (empty($fichier['name']) || $fichier['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    $extensionsAutorisees = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    $extension = strtolower(pathinfo($fichier['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $extensionsAutorisees, true)) {
        return null;
    }

    $dossier = __DIR__ . '/../uploads/' . $sousDossier . '/';
    if (!is_dir($dossier)) {
        mkdir($dossier, 0755, true);
    }

    $nomFichier = uniqid('spf_') . '.' . $extension;
    if (move_uploaded_file($fichier['tmp_name'], $dossier . $nomFichier)) {
        return 'uploads/' . $sousDossier . '/' . $nomFichier;
    }
    return null;
}

/** Insère une notification pour un utilisateur donné. */
function creerNotification(PDO $pdo, int $userId, string $titre, string $message, string $type = 'info', ?string $lien = null): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (user_id, titre, message, type, lien) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $titre, $message, $type, $lien]);
}

/** Recalcule la note moyenne d'un prestataire après un nouvel avis. */
function recalculerNoteMoyenne(PDO $pdo, int $prestataireId): void
{
    $stmt = $pdo->prepare(
        'SELECT AVG(note) AS moyenne, COUNT(*) AS total FROM avis WHERE prestataire_id = ?'
    );
    $stmt->execute([$prestataireId]);
    $res = $stmt->fetch();

    $pdo->prepare(
        'UPDATE prestataire_profils SET note_moyenne = ?, nb_avis = ? WHERE user_id = ?'
    )->execute([round($res['moyenne'] ?? 0, 2), $res['total'] ?? 0, $prestataireId]);
}