<?php
/**
 * Connexion à la base de données ServicesPlus (PDO / MySQL)
 * A inclure en tout premier dans chaque fichier qui a besoin de la BD.
 */

$db_host = 'localhost';
$db_name = 'servicesplus';
$db_user = 'root';       // à adapter selon l'environnement (WAMP/XAMPP/hébergeur)
$db_pass = '';           // à adapter selon l'environnement

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données : ' . $e->getMessage());
}