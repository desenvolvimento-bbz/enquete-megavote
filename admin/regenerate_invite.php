<?php
// regenerate_invite.php
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

function make_token(int $len = 16): string {
  return rtrim(strtr(base64_encode(random_bytes($len)), '+/', '-_'), '=');
}

$assembleia_id = (int)($_POST['assembleia_id'] ?? 0);
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

// Tenta regenerar token único
$max = 5;
for ($i=0;$i<$max;$i++) {
  $token = make_token(16);
  try {
    $upd = $pdo->prepare("UPDATE assembleias SET invite_token = ? WHERE id = ?");
    $upd->execute([$token, $assembleia_id]);
    break;
  } catch (PDOException $e) {
    if ($e->getCode() === '23000') { continue; } // colisão unique
    break;
  }
}

$back = $_SERVER['HTTP_REFERER'] ?? 'painel_admin.php';
header("Location: $back");
exit;
