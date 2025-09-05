<?php
// auth/logout.php
// Destroi a sessão e registra log de saída (inclusive quedas por inatividade)

$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
  'lifetime' => 0, 'path' => '/', 'domain' => '',
  'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
]);

session_start();
require_once(__DIR__ . '/../config/db.php');

// logger embutido e tolerante a falhas
function log_access(PDO $pdo, string $action, array $meta = []): void {
  try {
    $userId   = $_SESSION['user_id']  ?? null;
    $role     = $_SESSION['role']     ?? null;
    $ip       = $_SERVER['REMOTE_ADDR']     ?? '';
    $ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page     = $_SERVER['REQUEST_URI']     ?? '';
    $metaJson = json_encode($meta, JSON_UNESCAPED_UNICODE);

    $pdo->exec("CREATE TABLE IF NOT EXISTS access_logs (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      user_id INT NULL,
      role VARCHAR(30) NULL,
      action VARCHAR(64) NOT NULL,
      ip_address VARCHAR(64) NULL,
      user_agent TEXT NULL,
      page VARCHAR(255) NULL,
      meta JSON NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $stmt = $pdo->prepare("
      INSERT INTO access_logs (user_id, role, action, ip_address, user_agent, page, meta)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $role, $action, $ip, $ua, $page, $metaJson]);
  } catch (Throwable $e) { /* noop */ }
}

// motivo opcional (?reason=idle|ttl|fingerprint|manual)
$reason = $_GET['reason'] ?? 'manual';
log_access($pdo, 'admin_logout', ['reason' => $reason]);

// limpa tudo
$_SESSION = [];
if (ini_get("session.use_cookies")) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000,
    $params["path"], $params["domain"], $params["secure"], $params["httponly"]
  );
}
session_destroy();

// volta para a tela de login
header("Location: login.php");
exit;
