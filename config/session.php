<?php
/**
 * Gestion de session + protection des espaces par rôle.
 * A inclure juste après config/connexion.php dans chaque fichier protégé.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie que l'utilisateur est connecté. Sinon redirige vers connexion.php.
 * $racine = chemin relatif vers la racine du projet depuis le fichier appelant
 *           (ex: '../' si on est dans espace_client/, '' si on est à la racine)
 */
function verifierConnexion(string $racine = ''): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . $racine . 'connexion.php');
        exit;
    }
}

/**
 * Vérifie que l'utilisateur connecté a bien le rôle attendu.
 * Sinon le renvoie vers son propre espace (ou la connexion s'il n'est pas connecté).
 */
function verifierRole(string $roleAttendu, string $racine = ''): void
{
    verifierConnexion($racine);

    if ($_SESSION['role'] !== $roleAttendu) {
        header('Location: ' . $racine . urlEspace($_SESSION['role']));
        exit;
    }

    // Un prestataire non validé par l'admin n'accède pas encore à son espace
    if ($roleAttendu === 'prestataire' && ($_SESSION['statut_validation'] ?? '') !== 'valide') {
        header('Location: ' . $racine . 'connexion.php?erreur=en_attente_validation');
        exit;
    }
}

/**
 * Retourne l'URL de l'espace correspondant à un rôle (utilisé pour les redirections).
 */
function urlEspace(string $role): string
{
    return match ($role) {
        'admin'        => 'espace_admin/admin.php',
        'prestataire'  => 'espace_prestataire/dashboard_prestataire.php',
        'client'       => 'espace_client/dashboard_client.php',
        default        => 'connexion.php',
    };
}

/**
 * Utilisateur actuellement connecté (id + infos essentielles), ou null.
 */
function utilisateurConnecte(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'     => $_SESSION['user_id'],
        'nom'    => $_SESSION['nom'],
        'prenom' => $_SESSION['prenom'],
        'role'   => $_SESSION['role'],
        'photo'  => $_SESSION['photo'] ?? null,
    ];
}