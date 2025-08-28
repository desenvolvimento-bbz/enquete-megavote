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

// ---------------------------------------------------------------------
// Mensagens de timeout (idle | ttl/abs | fingerprint)
$timeoutMsg = "";
if (isset($_GET['timeout'])) {
    $reason = $_GET['reason'] ?? '';
    if ($reason === 'idle') {
        $timeoutMsg = "Sua sessão expirou por inatividade. Faça login novamente.";
    } elseif ($reason === 'ttl' || $reason === 'abs') {
        $timeoutMsg = "Sua sessão expirou pelo tempo máximo de uso. Faça login novamente.";
    } elseif ($reason === 'fingerprint') {
        $timeoutMsg = "Sua sessão foi invalidada por segurança. Faça login novamente.";
    } else {
        $timeoutMsg = "Sua sessão expirou. Faça login novamente.";
    }
}

// ---------------------------------------------------------------------
// Se vier ?token= na URL, encaminha direto ao join (qualquer user)
if (!empty($_GET['token'])) {
    $tok = preg_replace('~[^a-zA-Z0-9_\-]~', '', $_GET['token']);
    header('Location: ../public/join.php?token=' . urlencode($tok));
    exit;
}

// ---------------------------------------------------------------------
// Helpers CSRF (para o formulário de admin)
if (empty($_SESSION['csrf_login'])) {
    $_SESSION['csrf_login'] = bin2hex(random_bytes(32));
}
function csrf_ok($token) {
    return isset($_SESSION['csrf_login']) && is_string($token) && hash_equals($_SESSION['csrf_login'], $token);
}

// ---------------------------------------------------------------------
// Processa envio do BLOCO "participante" (colar link/token)
$participantError = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'participant') {
    $raw = trim($_POST['room_link'] ?? '');

    // Tenta extrair token=... de um link colado
    $tok = '';
    if (preg_match('~[?&]token=([a-zA-Z0-9_\-]+)~', $raw, $m)) {
        $tok = $m[1];
    } elseif (preg_match('~^[a-zA-Z0-9_\-]{10,}$~', $raw)) {
        // Aceita token “cru”
        $tok = $raw;
    }

    if ($tok !== '') {
        header('Location: ../public/join.php?token=' . urlencode($tok));
        exit;
    } else {
        $participantError = "Informe um link válido (join.php?token=...) ou apenas o token.";
    }
}

// ---------------------------------------------------------------------
// Processa envio do BLOCO "admin" (usuário/senha)
$loginError = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['mode'] ?? '') === 'admin') {

    if (!csrf_ok($_POST['csrf'] ?? '')) {
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

        if ($user && password_verify($password, $user['password'])) {
            // Reforça cookie e evita fixation
            setcookie(session_name(), session_id(), [
                'expires'  => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_regenerate_id(true);

            // metadata + fingerprint
            $_SESSION['created_at']    = time();
            $_SESSION['last_activity'] = time();
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ip_octets = implode('.', array_slice(explode('.', $ip), 0, 2));
            $_SESSION['fingerprint'] = hash('sha256', $ua.'|'.$ip_octets);

            // dados do usuário
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // Redireciona por perfil
            if ($user['role'] === 'master') {
                header("Location: ../master/painel_master.php");
            } elseif ($user['role'] === 'admin') {
                header("Location: ../admin/painel_admin.php");
            } else {
                header("Location: ../vote/painel_basic.php");
            }
            exit;

        } else {
            $loginError = "Usuário ou senha inválidos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="auto">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Entrar · MegaVote</title>
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
      min-height:100vh; display:flex; flex-direction:column;
    }
    main{flex:1; display:flex; align-items:center}
    .brand-bar{ background: var(--mv-primary); color:#fff; }
    .brand-bar a{ color:#fff; text-decoration:none; font-weight:700; letter-spacing:.2px; }
    .login-card{
      max-width: 980px;
      border:1px solid #e5e8eb;
      box-shadow: 0 10px 20px rgba(0,0,0,.06);
      border-radius: 16px; overflow:hidden; background:#fff;
    }
    .left{ background:#f7fbf6; border-right:1px solid #edf0ee; }
    .mv-btn{ background: var(--mv-primary); border-color: var(--mv-primary); }
    .mv-btn:hover{ background: var(--mv-dark); border-color: var(--mv-dark); }
    .form-control:focus{ border-color: var(--mv-primary); box-shadow:0 0 0 .25rem rgba(96,163,61,.15); }
    .muted{ color:#7b7f74; }
    footer small{ color:#7a7d76; }
    .tab-btn{ cursor:pointer; }
    .tab-btn.active{ background: var(--mv-primary); color:#fff; border-color: var(--mv-primary); }
    .hidden{ display:none !important; }
  </style>
</head>
<body>

<!-- Barra superior minimalista -->
<div class="brand-bar py-2">
  <div class="container d-flex align-items-center justify-content-between">
    <div class="brand"><a href="../index.php">Megavote Enquetes</a></div>
  </div>
</div>

<main>
  <div class="container py-5">
    <div class="mx-auto login-card row g-0">
      <!-- Lado informativo -->
      <div class="col-md-5 left p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-shield-check" style="font-size:1.4rem;color:var(--mv-primary)"></i>
            <h5 class="mb-0" style="color:var(--mv-gray)">Acesso</h5>
          </div>
          <p class="mb-4 muted">Cole o link da sala para participar, ou acesse como administrador.</p>
          <ul class="list-unstyled small muted mb-0">
            <li class="mb-2"><i class="bi bi-link-45deg me-2"></i>Convites por link com token</li>
            <li class="mb-2"><i class="bi bi-lock-fill me-2"></i>Sessões protegidas</li>
            <li class="mb-2"><i class="bi bi-people-fill me-2"></i>Votação simples</li>
          </ul>
        </div>
        <div class="small text-muted">Dúvidas? <a href="#" class="text-decoration-none" style="color:var(--mv-dark)">Fale conosco</a></div>
      </div>

      <!-- Lado dos formulários -->
      <div class="col-md-7 p-4 p-md-5">
        <?php if (!empty($timeoutMsg)): ?>
          <div class="alert alert-warning py-2 mb-3"><?= htmlspecialchars($timeoutMsg) ?></div>
        <?php endif; ?>

        <div class="d-flex gap-2 mb-3">
          <button class="btn btn-outline-secondary tab-btn active" id="tab-part">Participante</button>
          <button class="btn btn-outline-secondary tab-btn" id="tab-admin">Administrador</button>
        </div>

        <!-- Participante -->
        <div id="pane-part">
          <h3 class="fw-bold mb-1" style="color:var(--mv-gray)">Acesso do participante</h3>
          <div class="mb-3 muted">Cole o <strong>link da sala</strong> da sua enquete para entrar.</div>

          <?php if (!empty($participantError)): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($participantError) ?></div>
          <?php endif; ?>

          <form method="post" id="participantForm" novalidate>
            <input type="hidden" name="mode" value="participant">
            <div class="mb-3">
              <label for="room_link" class="form-label">Link da sala:</label>
              <input type="text" class="form-control" id="room_link" name="room_link"
                     placeholder="https://..."
                     required>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn mv-btn btn-lg" style="color:var(--mv-soft)">Entrar</button>
            </div>
          </form>
        </div>

        <!-- Administrador -->
        <div id="pane-admin" class="hidden">
          <h3 class="fw-bold mb-1" style="color:var(--mv-gray)">Acesso do administrador</h3>
          <div class="mb-3 muted">Use seu usuário e senha para gerenciar as salas.</div>

          <?php if (!empty($loginError)): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($loginError) ?></div>
          <?php endif; ?>

          <form method="POST" novalidate>
            <input type="hidden" name="mode" value="admin">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_login']) ?>">
            <div class="mb-3">
              <label for="username" class="form-label">Usuário</label>
              <input type="text" class="form-control" id="username" name="username" autocomplete="username" required>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Senha</label>
              <div class="input-group">
                <input type="password" class="form-control" id="password" name="password" autocomplete="current-password" required>
                <button type="button" class="btn btn-outline-secondary" id="togglePwd" aria-label="Mostrar/ocultar senha">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
            </div>
            <div class="d-grid">
              <button type="submit" class="btn mv-btn btn-lg" style="color:var(--mv-soft)">Entrar</button>
            </div>
          </form>
          <div class="mt-3">
          <a href="register.php" class="text-decoration-none" style="color:var(--mv-dark)">Não tem conta? Cadastre-se</a>
        </div>
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
  // Alternância entre Participante/Admin
  const tabPart  = document.getElementById('tab-part');
  const tabAdmin = document.getElementById('tab-admin');
  const panePart = document.getElementById('pane-part');
  const paneAdmin= document.getElementById('pane-admin');

  function setTab(which){
    if (which==='admin'){
      tabAdmin.classList.add('active');
      tabPart.classList.remove('active');
      paneAdmin.classList.remove('hidden');
      panePart.classList.add('hidden');
    } else {
      tabPart.classList.add('active');
      tabAdmin.classList.remove('active');
      panePart.classList.remove('hidden');
      paneAdmin.classList.add('hidden');
    }
  }
  tabPart.addEventListener('click', ()=> setTab('part'));
  tabAdmin.addEventListener('click', ()=> setTab('admin'));

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

  // Extra: se o campo de participante já vier preenchido com um link contendo ?token=, ao enviar vamos parsear
  const formPart = document.getElementById('participantForm');
  formPart?.addEventListener('submit', (e)=>{
    const raw = document.getElementById('room_link').value.trim();
    if (!raw) return; // deixa o POST tratar erro
    // tenta extrair token no cliente e redireciona direto
    let token = '';
    const m = raw.match(/[?&]token=([a-zA-Z0-9_\-]+)/);
    if (m) token = m[1];
    else if (/^[a-zA-Z0-9_\-]{10,}$/.test(raw)) token = raw;

    if (token){
      e.preventDefault();
      window.location.href = '../public/join.php?token=' + encodeURIComponent(token);
    }
  });
</script>
</body>
</html>
