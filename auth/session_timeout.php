<?php
// auth/session_timeout.php
// Uso típico em páginas protegidas:
//   $loginPath = '../auth/login.php';
//   require_once('../auth/session_timeout.php');
//   enforceSessionGuard('admin', $loginPath); // ou 'basic' | ['admin','master']

// =========================
// Configurações (padrões)
// =========================
$IDLE_LIMIT    = $IDLE_LIMIT    ?? 15 * 60;   // 15 min inatividade
$ABS_LIMIT     = $ABS_LIMIT     ?? 0;         // 0 = desativado (ex.: 3*60*60 p/ 3h)
$ROTATE_EVERY  = $ROTATE_EVERY  ?? 5 * 60;    // rotacionar ID de sessão a cada 5 min
$loginPath     = $loginPath     ?? 'auth/login.php';

// =========================
// Sessão + flags do cookie
// =========================
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

if (session_status() !== PHP_SESSION_ACTIVE) {
  // Define flags do cookie ANTES de abrir a sessão
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_start();
} else {
  // Refereza as flags do cookie da sessão já aberta
  setcookie(session_name(), session_id(), [
    'expires'  => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

// =========================
// Utilitário p/ encerrar
// =========================
function _endSessionAndRedirect($loginPath, $reason) {
  if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $p = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
  }
  header("Location: {$loginPath}?timeout=1&reason={$reason}");
  exit;
}

// =========================
// Lógica de timeout/hardening
// =========================
$now = time();

if (!isset($_SESSION['created_at'])) {
  // Inicialização na 1ª passagem
  $_SESSION['created_at']      = $now;
  $_SESSION['last_activity']   = $now;
  $_SESSION['sid_last_rotated']= $now;

  // Define fingerprint inicial
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $ip_prefix = implode('.', array_slice(explode('.', $ip), 0, 2)); // ex.: 177.23
  $_SESSION['fingerprint'] = hash('sha256', $ua.'|'.$ip_prefix);

} else {
  // Timeout absoluto
  if ($ABS_LIMIT > 0 && ($now - (int)$_SESSION['created_at']) > $ABS_LIMIT) {
    _endSessionAndRedirect($loginPath, 'abs');
  }

  // Timeout por inatividade
  if ($IDLE_LIMIT > 0 && isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity']) > $IDLE_LIMIT) {
    _endSessionAndRedirect($loginPath, 'idle');
  }

  // Verificação de fingerprint (mitiga hijack em outra origem)
  $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
  $ip = $_SERVER['REMOTE_ADDR'] ?? '';
  $ip_prefix = implode('.', array_slice(explode('.', $ip), 0, 2));
  $currentFp = hash('sha256', $ua.'|'.$ip_prefix);

  if (!empty($_SESSION['fingerprint']) && !hash_equals($_SESSION['fingerprint'], $currentFp)) {
    _endSessionAndRedirect($loginPath, 'fingerprint');
  }

  // Rotação periódica do ID de sessão
  $lastRot = (int)($_SESSION['sid_last_rotated'] ?? $now);
  if ($ROTATE_EVERY > 0 && ($now - $lastRot) >= $ROTATE_EVERY) {
    session_regenerate_id(true);
    $_SESSION['sid_last_rotated'] = $now;
  }
}

// Atualiza relógio de atividade
$_SESSION['last_activity'] = $now;

// =========================
// Helper de guarda por papel
// =========================
function enforceSessionGuard($requiredRole = null, $loginPath = 'auth/login.php') {
  if (!isset($_SESSION['user_id'])) {
    _endSessionAndRedirect($loginPath, 'idle'); // sem sessão válida, trata como expirada
  }

  if ($requiredRole) {
    $role = $_SESSION['role'] ?? null;
    if (is_array($requiredRole)) {
      if (!in_array($role, $requiredRole, true)) {
        _endSessionAndRedirect($loginPath, 'role');
      }
    } else {
      if ($role !== $requiredRole) {
        _endSessionAndRedirect($loginPath, 'role');
      }
    }
  }
}
