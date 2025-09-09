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

require_once(__DIR__ . '/../config/db.php');

// ---------------------------------------------------------------------
// Helpers simples de CSRF (mantivemos como no projeto)
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_login'];

// ---------------------------------------------------------------------
// LOG DE ACESSO
// Tabela: access_logs (DDL ao final deste arquivo)
// Ação exemplos: admin_login_success, admin_login_fail, admin_login_redirect
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
        $stmt->execute([
            $userId, $role, $action, $ip, $ua, $page, $metaJson
        ]);
    } catch (Throwable $e) {
        // Silencioso: logging não pode derrubar a app
    }
}

// ---------------------------------------------------------------------
// Já logado como ADMIN? Vai pro Hub.
if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin','master'], true)) {
    log_access($pdo, 'admin_login_redirect', ['reason' => 'already_logged']);
    header('Location: ../hub/index.php');
    exit;
}

// ---------------------------------------------------------------------
// POST (login admin)
$loginError = "";
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!isset($_POST['csrf']) || !hash_equals($csrfToken, $_POST['csrf'])) {
        $loginError = "Falha de validação. Atualize a página e tente novamente.";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $user = false;
        }

        $okRole = $user && in_array(($user['role'] ?? ''), ['admin','master'], true);

        if ($user && $okRole && password_verify($password, $user['password'])) {
            // Reforça cookie atual e evita fixation
            setcookie(session_name(), session_id(), [
                'expires'  => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_regenerate_id(true);

            // Metadados de sessão (timeout/fingerprint)
            $_SESSION['created_at']    = time();
            $_SESSION['last_activity'] = time();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ip_octets = implode('.', array_slice(explode('.', $ip), 0, 2));
            $_SESSION['fingerprint'] = hash('sha256', $ua.'|'.$ip_octets);

            // Dados do usuário
            $_SESSION['user_id']  = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            log_access($pdo, 'admin_login_success', ['username' => $username]);

            // Sempre leva pro HUB
            header("Location: ../hub/index.php");
            exit;
        } else {
            $loginError = "Usuário ou senha inválidos (ou perfil sem permissão).";
            log_access($pdo, 'admin_login_fail', ['username' => $username]);
            // usleep(300000); // atraso opcional anti brute-force
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="auto">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login · MegaVote</title>
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
    .brand-bar{ background: var(--mv-primary); color:#fff; }
    .brand-bar a{ color:#fff; text-decoration:none; font-weight:700; letter-spacing:.2px; }
    .login-card{
      max-width: 880px;
      border:1px solid #e5e8eb;
      box-shadow: 0 10px 20px rgba(0,0,0,.06);
      border-radius: 16px; overflow:hidden; background:#fff;
    }
    .left{ background:#f7fbf6; border-right:1px solid #edf0ee; }
    .mv-btn{ background: var(--mv-primary); border-color: var(--mv-primary); }
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

<!-- Barra superior -->
<div class="brand-bar py-2">
  <div class="container d-flex align-items-center justify-content-between">
    <div class="brand">
      <a href="../index.php">Serviços Megavote</a>
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
            <i class="bi bi-shield-check" style="font-size:1.4rem;color:var(--mv-primary)"></i>
            <h5 class="mb-0" style="color:var(--mv-gray)">Acesso de Administrador</h5>
          </div>
          <p class="mb-4 muted">Entre para gerenciar salas, pautas, enquetes e acompanhar resultados.</p>
          <ul class="list-unstyled small muted mb-0">
            <li class="mb-2"><i class="bi bi-lock-fill me-2"></i>Sessões protegidas</li>
            <li class="mb-2"><i class="bi bi-graph-up-arrow me-2"></i>Resultados claros</li>
            <li class="mb-2"><i class="bi bi-people-fill me-2"></i>Hub com módulos</li>
          </ul>
        </div>
        <div class="small text-muted">
          É participante? Acesse pelo link público recebido.
        </div>
      </div>

      <!-- Lado do formulário -->
      <div class="col-md-7 p-4 p-md-5">
        <h3 class="fw-bold mb-1" style="color:var(--mv-gray)">Entrar</h3>
        <div class="mb-4 muted">Use seu usuário e senha de administrador</div>

        <?php if (!empty($loginError)): ?>
          <div class="alert alert-danger py-2"><?= htmlspecialchars($loginError) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken) ?>">
          <div class="mb-3">
            <label for="username" class="form-label">Usuário</label>
            <input type="text" class="form-control" id="username" name="username" required autofocus>
          </div>

          <div class="mb-2">
            <label for="password" class="form-label">Senha</label>
            <div class="input-group">
              <input type="password" class="form-control" id="password" name="password" required>
              <button type="button" class="btn btn-outline-secondary" id="togglePwd" aria-label="Mostrar/ocultar senha">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <div class="d-grid mt-3">
            <button type="submit" class="btn mv-btn btn-lg" style="color:var(--mv-soft)">Entrar</button>
          </div>
        </form>

        <!-- Acesso via Link público (participante) -->
        <div class="mt-4">
          <label class="form-label">Tenho um link público</label>
          <div class="input-group">
            <input type="text" id="publicLink" class="form-control" placeholder="Cole aqui o link recebido (join.php?token=...)">
            <button class="btn btn-outline-primary" id="goPublic">Ir</button>
          </div>
          <div class="form-text">Se você é morador/participante, use o link enviado pelo síndico.</div>
        </div>

      </div>
    </div>
  </div>
</main>

<footer class="border-top bg-white">
  <div class="container py-3 text-center">
    <small>Powered by <strong>Megavote</strong></small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Mostrar/ocultar senha
  const btn = document.getElementById('togglePwd');
  const pwd = document.getElementById('password');
  if (btn && pwd){
    btn.addEventListener('click', ()=>{
      const isPwd = pwd.type === 'password';
      pwd.type = isPwd ? 'text' : 'password';
      btn.innerHTML = isPwd ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
    });
  }

  // Abrir link público
  document.getElementById('goPublic')?.addEventListener('click', ()=>{
    const v = (document.getElementById('publicLink').value || '').trim();
    if (!v) return;
    try {
      // Se usuário colou só o token, monta URL
      if (!/^https?:\/\//i.test(v) && /^[A-Za-z0-9_-]{8,}$/.test(v)) {
        window.location.href = '../public/join.php?token=' + encodeURIComponent(v);
        return;
      }
      // Se é URL completa, vai direto
      const u = new URL(v, window.location.origin);
      window.location.href = u.href;
    } catch(e) {
      alert('Link inválido.');
    }
  });
</script>
</body>
</html>
