<?php
// toggle_public_link.php
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

require_once __DIR__ . '/../config/db.php';

// CSRF fallback
if (!function_exists('verifyCsrfToken')) {
  function verifyCsrfToken(string $token): bool {
    return isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
  }
}

$assembleia_id = (int)($_POST['assembleia_id'] ?? 0);
$enable        = (int)($_POST['enable'] ?? 0);
$csrf          = $_POST['csrf'] ?? '';

if (!$assembleia_id || !verifyCsrfToken($csrf)) {
  http_response_code(400);
  die('Requisição inválida.');
}

// Confirma propriedade
$stmt = $pdo->prepare("SELECT id FROM assembleias WHERE id = ? AND criada_por = ?");
$stmt->execute([$assembleia_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
  http_response_code(403);
  die('Permissão negada.');
}

$pdo->prepare("UPDATE assembleias SET public_enabled = ? WHERE id = ?")
    ->execute([$enable ? 1 : 0, $assembleia_id]);

// Volta para onde veio (painel ou itens)
$back = $_SERVER['HTTP_REFERER'] ?? 'painel_admin.php';
header("Location: $back");
exit;
