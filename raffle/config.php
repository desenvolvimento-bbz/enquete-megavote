<?php
/**
 * MEGAVOTE - RAFFLE (Sorteio de Vagas)
 * Configuração base do módulo Raffle
 *
 * IMPORTANTE:
 * - Inclua ESTE arquivo ANTES do guard de sessão:
 *     require_once __DIR__ . '/config.php';
 *     $loginPath = '../auth/login.php';
 *     require_once __DIR__ . '/../auth/session_timeout.php';
 *     enforceSessionGuard('admin', $loginPath);
 *
 * - NÃO chame session_start() aqui. O guard faz isso de forma padronizada.
 */

/* -----------------------
 * Sessão / Cookies (apenas se ainda não ativa)
 * --------------------- */
if (session_status() !== PHP_SESSION_ACTIVE) {
  // Segurança de cookie de sessão
  @ini_set('session.use_only_cookies', '1');
  @ini_set('session.use_strict_mode',  '1');
  @ini_set('session.cookie_httponly',  '1');
  // em produção com HTTPS, troque para 1
  @ini_set('session.cookie_secure',    '0');

  // Se quiser padronizar os cookies aqui:
  $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

/* -----------------------
 * Erros (habilite somente em dev)
 * --------------------- */
error_reporting(E_ALL);
ini_set('display_errors', '1');

/* -----------------------
 * App / Paths
 * --------------------- */
define('APP_NAME',    'MegaVote - Sistema de Sorteio');
define('APP_VERSION', '2.0.0');
define('APP_URL',     'http://localhost'); // Ajuste em prod, se necessário

define('ROOT_PATH',    __DIR__);
define('DATA_PATH',    ROOT_PATH . '/data');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('ASSETS_PATH',  ROOT_PATH . '/assets');

date_default_timezone_set('America/Sao_Paulo');

/* -----------------------
 * Upload
 * --------------------- */
define('MAX_FILE_SIZE',       10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['xlsx']);

/* -----------------------
 * Helpers
 * --------------------- */

function createRequiredDirectories(): void {
  foreach ([DATA_PATH, UPLOADS_PATH] as $dir) {
    if (!is_dir($dir)) {
      @mkdir($dir, 0755, true);
    }
  }
}

/** Verifica se vendor/autoload existe (PhpSpreadsheet/Dompdf etc.) */
function checkDependencies(): array {
  $missing = [];
  if (!file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    $missing[] = 'Composer autoload (vendor/autoload.php)';
  }
  return $missing;
}

function sanitizeInput(?string $input): string {
  return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

function isValidEmail(string $email): bool {
  return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function generateCSRFToken(): string {
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}

function verifyCSRFToken(?string $token): bool {
  return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log de ações:
 * - Sempre escreve em arquivo (raffle/data/actions.log)
 * - Se $pdo existir e a tabela access_logs estiver disponível, tenta gravar também no banco (silenciosamente).
 */
function logAction(string $action, string $details = ''): void {
  // Log em arquivo
  $logFile   = DATA_PATH . '/actions.log';
  $timestamp = date('Y-m-d H:i:s');
  $userLabel = $_SESSION['username'] ?? ($_SESSION['usuario'] ?? 'Anônimo');
  $ip        = $_SERVER['REMOTE_ADDR']  ?? 'Desconhecido';
  $ua        = $_SERVER['HTTP_USER_AGENT'] ?? '';

  $line = "[{$timestamp}] {$userLabel} ({$ip}) :: {$action}";
  if ($details !== '') { $line .= " - {$details}"; }
  $line .= PHP_EOL;

  @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

  // Tentativa opcional de logar no banco
  try {
    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
      $stmt = $GLOBALS['pdo']->prepare("
        INSERT INTO access_logs (user_id, role, action, ip_address, user_agent, page, meta)
        VALUES (?, ?, ?, ?, ?, ?, ?)
      ");
      $userId = $_SESSION['user_id'] ?? null;
      $role   = $_SESSION['role']    ?? null;
      $page   = $_SERVER['REQUEST_URI'] ?? null;
      $meta   = json_encode(['details' => $details], JSON_UNESCAPED_UNICODE);
      $stmt->execute([$userId, $role, $action, $ip, $ua, $page, $meta]);
    }
  } catch (Throwable $e) {
    // Silencioso: não quebra o fluxo do app se não houver DB/tabela
  }
}

/* -----------------------
 * Inicialização
 * --------------------- */
createRequiredDirectories();

if (php_sapi_name() !== 'cli') {
  $missing = checkDependencies();
  if (!empty($missing)) {
    error_log('Dependências faltando: ' . implode(', ', $missing));
    // Em produção você pode exibir uma página amigável
  }
}
