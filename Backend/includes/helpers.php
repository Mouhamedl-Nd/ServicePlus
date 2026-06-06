<?php
// ============================================================
//  ServicesPlus — Helpers communs
// ============================================================
require_once __DIR__ . '/../config/database.php';

// CORS — permet au frontend HTML d'appeler l'API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['samesite' => 'None', 'secure' => false]);
    session_start();
}

function respond(bool $ok, string $msg, array $data = [], int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $msg, 'data' => $data]);
    exit;
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? $_POST ?? [];
}

function requireAuth(): array {
    if (empty($_SESSION['user'])) respond(false, 'Non authentifié.', [], 401);
    return $_SESSION['user'];
}

function requireRole(string $role): array {
    $user = requireAuth();
    if ($user['role'] !== $role) respond(false, 'Accès refusé.', [], 403);
    return $user;
}

function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)));
}

function genReference(): string {
    $db    = getDB();
    $annee = date('Y');
    $stmt  = $db->query("SELECT COUNT(*) FROM reservations WHERE YEAR(created_at) = $annee");
    $count = (int)$stmt->fetchColumn() + 1;
    return 'RES-' . $annee . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}
