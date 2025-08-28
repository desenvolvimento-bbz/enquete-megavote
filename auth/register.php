<?php
// -------- Cookies de sessão com flags seguras (defina ANTES de session_start) --------
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'domain'   => '',
  'secure'   => $secure,
  'httponly' => true,
  'samesite' => 'Lax',
]);

session_start();
require_once('../config/db.php');

// Se já estiver logado, manda para o painel correspondente
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
  if ($_SESSION['role'] === 'master') { header('Location: ../master/painel_master.php'); exit; }
  if ($_SESSION['role'] === 'admin')  { header('Location: ../admin/painel_admin.php'); exit; }
  if ($_SESSION['role'] === 'basic')  { header('Location: ../vote/painel_basic.php'); exit; }
}

// CSRF para o formulário de cadastro
if (empty($_SESSION['csrf_register'])) {
  $_SESSION['csrf_register'] = bin2hex(random_bytes(32));
}

$errors   = [];
$username = trim($_POST['username'] ?? '');
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$confirm  = $_POST['confirm']  ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Checagem CSRF
  if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_register'], $_POST['csrf'])) {
    $errors[] = 'Falha de validação. Atualize a página e tente novamente.';
  } else {
    // --- Validações ---
    if ($username === '' || !preg_match('/^[A-Za-z0-9._-]{3,30}$/', $username)) {
      $errors[] = 'Usuário inválido. Use 3–30 caracteres (letras, números, ponto, hífen ou sublinhado).';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errors[] = 'E-mail inválido.';
    }

    if (strlen($password) < 8) {
      $errors[] = 'A senha deve ter ao menos 8 caracteres.';
    }

    if ($password !== $confirm) {
      $errors[] = 'A confirmação de senha não confere.';
    }

    // Duplicidade
    if (!$errors) {
      try {
        $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $email]);
        if ($row = $stmt->fetch()) {
          if (strcasecmp($row['username'], $username) === 0) { $errors[] = 'Este usuário já está em uso.'; }
          if (strcasecmp($row['email'], $email) === 0)       { $errors[] = 'Este e-mail já está em uso.'; }
        }
      } catch (PDOException $e) {
        $errors[] = 'Erro ao validar usuário. Tente novamente.';
      }
    }

    // Criação
    if (!$errors) {
      try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins  = $pdo->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)');
        $ins->execute([$username, $email, $hash, 'basic']);

        // Redireciona para o login (se quiser exibir mensagem, pode usar login.php?reg=1)
        header('Location: login.php?reg=1');
        exit;
      } catch (PDOException $e) {
        $errors[] = 'Erro ao criar sua conta. Tente novamente.';
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="auto">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Criar conta · MegaVote</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{
      --mv-primary:#60a33d;   /* verde principal */
      --mv-dark:#166434;      /* verde escuro */
      --mv-gray:#53554A;      /* cinza */
      --mv-soft:#f3f6f4;      /* fundo suave */
    }
    html,body{height:100%}
    body{
      background: linear-gradient(180deg, #e9f6e6 0%, #ffffff 40%);
      min-height:100vh;
      display:flex; flex-direction:column;
    }
    main{flex:1; display:flex; align-items:center}
    .brand-bar{
      background: var(--mv-primary);
      color:#fff;
    }
    .brand-bar .brand a{
      color:#fff; text-decoration:none; font-weight:700; letter-spacing:.2px;
    }
    .login-card{
      max-width: 880px;
      border:1px solid #e5e8eb;
      box-shadow: 0 10px 20px rgba(0,0,0,.06);
      border-radius: 16px;
      overflow:hidden;
      background:#fff;
    }
    .login-card .left{
      background: #f7fbf6;
      border-right: 1px solid #edf0ee;
    }
    .mv-btn{
      background: var(--mv-primary);
      border-color: var(--mv-primary);
    }
    .mv-btn:hover{ background: var(--mv-dark); border-color: var(--mv-dark); }
    .form-control:focus{
      border-color: var(--mv-primary);
      box-shadow: 0 0 0 .25rem rgba(96,163,61,.15);
    }
    .muted{ color:#7b7f74; }
    footer small{ color:#7a7d76; }
  </style>
</head>
<body>

<!-- Barra superior minimalista (igual login) -->
<div class="brand-bar py-2">
  <div class="container d-flex align-items-center justify-content-between">
    <div class="brand">
      <a href="../index.php">Megavote Enquetes</a>
    </div>
  </div>
</div>

<main>
  <div class="container py-5">
    <div class="mx-auto login-card row g-0">
      <!-- Lado “branding” -->
      <div class="col-md-5 left p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-person-plus-fill" style="font-size:1.4rem;color:var(--mv-primary)"></i>
            <h5 class="mb-0" style="color:var(--mv-gray)">Crie sua conta</h5>
          </div>
          <p class="mb-4 muted">Cadastre-se para participar das enquetes e acompanhar as votações do seu condomínio.</p>
          <ul class="list-unstyled small muted mb-0">
            <li class="mb-2"><i class="bi bi-lock-fill me-2"></i>Segurança e privacidade</li>
            <li class="mb-2"><i class="bi bi-people-fill me-2"></i>Fácil participação</li>
            <li class="mb-2"><i class="bi bi-graph-up-arrow me-2"></i>Votação simples</li>
          </ul>
        </div>
            <div class="small text-muted">Precisa de ajuda? <a href="#" class="text-decoration-none" style="color:var(--mv-dark)">Fale conosco</a></div>
      </div>

      <!-- Lado do formulário -->
      <div class="col-md-7 p-4 p-md-5">
        <h3 class="fw-bold mb-1" style="color:var(--mv-gray)">Cadastro*</h3>
        <div class="mb-4 muted">Preencha os campos abaixo para criar sua conta no MegaVote</div>

        <?php if (!empty($errors)): ?>
          <div class="alert alert-danger py-2">
            <ul class="mb-0">
              <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_register']) ?>">

          <div class="mb-3">
            <label for="username" class="form-label">Usuário</label>
            <input type="text" class="form-control" id="username" name="username"
                   value="<?= htmlspecialchars($username) ?>" required
                   placeholder="ex.: joao.silva">
          </div>

          <div class="mb-3">
            <label for="email" class="form-label">E-mail</label>
            <input type="email" class="form-control" id="email" name="email"
                   value="<?= htmlspecialchars($email) ?>" required
                   placeholder="voce@exemplo.com">
          </div>

          <div class="row g-3">
            <div class="col-sm-6">
              <label for="password" class="form-label">Senha</label>
              <div class="input-group">
                <input type="password" class="form-control" id="password" name="password"
                       minlength="8" required placeholder="Mín. 8 caracteres">
                <button type="button" class="btn btn-outline-secondary" id="togglePwd" aria-label="Mostrar/ocultar senha">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="col-sm-6">
              <label for="confirm" class="form-label">Confirmar senha</label>
              <div class="input-group">
                <input type="password" class="form-control" id="confirm" name="confirm"
                       minlength="8" required placeholder="Repita a senha">
                <button type="button" class="btn btn-outline-secondary" id="toggleConfirm" aria-label="Mostrar/ocultar confirmação">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
                <div class="col-12">
                <label for="role" class="form-label">Perfil</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="admin" <?= (($_POST['role'] ?? '') === 'admin' ? 'selected' : '') ?>>Admin</option>
                    <option value="basic" <?= (($_POST['role'] ?? '') === 'basic' ? 'selected' : '') ?>>Básico</option>
                </select>
                <div class="form-text">Opção temporária para fins de teste. *</div>
                </div>
          </div>
          <div class="d-grid mt-3">
            <button type="submit" class="btn mv-btn btn-lg" style="color:var(--mv-soft)">Criar conta</button>
          </div>
        </form>

        <div class="mt-3">
          <a href="login.php" class="text-decoration-none" style="color:var(--mv-dark)">Já tem conta? Entrar</a>
        </div>
      </div>
    </div>
    <a class="text-decoration-none" style="color:var(--mv-dark)">Página temporária para fins de teste.*</a><br>
  </div>
</main>

<footer class="border-top bg-white">
  <div class="container py-3 text-center">
    <small>Powered by <strong>Megavote</strong></small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Mostrar/ocultar senha e confirmação
  const togglePwd = document.getElementById('togglePwd');
  const pwd       = document.getElementById('password');
  togglePwd?.addEventListener('click', ()=>{
    const isPwd = pwd.type === 'password';
    pwd.type = isPwd ? 'text' : 'password';
    togglePwd.innerHTML = isPwd ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
  });

  const toggleConfirm = document.getElementById('toggleConfirm');
  const conf          = document.getElementById('confirm');
  toggleConfirm?.addEventListener('click', ()=>{
    const isPwd = conf.type === 'password';
    conf.type = isPwd ? 'text' : 'password';
    toggleConfirm.innerHTML = isPwd ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
  });
</script>
</body>
</html>