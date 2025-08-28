<?php
session_start();
require_once('../config/db.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  die('Método não permitido.');
}

$token      = trim($_POST['token'] ?? '');
$full_name  = trim($_POST['full_name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$condo_name = trim($_POST['condo_name'] ?? '');
$bloco      = trim($_POST['bloco'] ?? '');
$unidade    = trim($_POST['unidade'] ?? '');

if ($token === '' || $full_name === '' || $email === '' || $condo_name === '' || $bloco === '' || $unidade === '') {
  http_response_code(400);
  die('Campos obrigatórios ausentes.');
}

// 1) Resolve assembleia pelo token
$asm = $pdo->prepare("
  SELECT id, public_enabled, status
  FROM assembleias
  WHERE invite_token = ?
  LIMIT 1
");
$asm->execute([$token]);
$assembleia = $asm->fetch();

if (!$assembleia || !$assembleia['public_enabled'] || $assembleia['status'] !== 'em_andamento') {
  http_response_code(403);
  die('Link inválido ou sala indisponível.');
}

$assembleia_id = (int)$assembleia['id'];

// 2) Resolve/cria usuário em `users` por e-mail (role basic)
$usr = $pdo->prepare("SELECT id, username, role FROM users WHERE email = ? LIMIT 1");
$usr->execute([$email]);
$user = $usr->fetch();

if (!$user) {
  // cria conta básica “silenciosa”
  $username = $email; // pode ser $full_name se quiser
  $password = bin2hex(random_bytes(16));
  $hash     = password_hash($password, PASSWORD_DEFAULT);
  $ins = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, 'basic', NOW())");
  $ins->execute([$username, $email, $hash]);
  $user_id = (int)$pdo->lastInsertId();
  $user = ['id' => $user_id, 'username' => $username, 'role' => 'basic'];
} else {
  $user_id = (int)$user['id'];
}

// 3) Upsert no `participants` (assembleia_id + user_id)
$sel = $pdo->prepare("SELECT id, is_annulled FROM participants WHERE assembleia_id = ? AND user_id = ? LIMIT 1");
$sel->execute([$assembleia_id, $user_id]);
$part = $sel->fetch();

$ip  = $_SERVER['REMOTE_ADDR'] ?? '';
$ua  = $_SERVER['HTTP_USER_AGENT'] ?? '';

if ($part) {
  // Atualiza dados e “desanula” se preciso
  $upd = $pdo->prepare("
    UPDATE participants
       SET full_name = ?, email = ?, condo_name = ?, bloco = ?, unidade = ?,
           ip_address = ?, user_agent = ?,
           is_annulled = 0, annulled_by = NULL, annulled_at = NULL
     WHERE id = ?
  ");
  $ok = $upd->execute([$full_name, $email, $condo_name, $bloco, $unidade, $ip, $ua, $part['id']]);
} else {
  $insP = $pdo->prepare("
    INSERT INTO participants
      (assembleia_id, user_id, full_name, email, condo_name, bloco, unidade, ip_address, user_agent, is_annulled, created_at)
    VALUES
      (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
  ");
  $ok = $insP->execute([$assembleia_id, $user_id, $full_name, $email, $condo_name, $bloco, $unidade, $ip, $ua]);
}

if (!$ok) {
  http_response_code(500);
  die('Falha ao registrar acesso. Tente novamente.');
}

// 4) Sobe sessão escopada e vai para os itens
session_regenerate_id(true);
$_SESSION['user_id']  = $user_id;
$_SESSION['username'] = $user['username'] ?? $email;
$_SESSION['role']     = 'basic';
$_SESSION['guest_scope_assembleia_id'] = $assembleia_id;

header('Location: ../vote/view_itens.php?assembleia_id=' . $assembleia_id);
exit;
