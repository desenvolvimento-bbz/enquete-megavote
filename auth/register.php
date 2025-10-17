<?php
// auth/register.php
// Registro de usuário com seleção de papel ('admin' | 'basic') validada por whitelist.
// Compatível com PHP 7.2. Mantém CSRF dedicado (csrf_register) e checagens de duplicidade.

// === Bootstrap mínimo de sessão segura (7.2-friendly) ===
if (PHP_SAPI !== 'cli') {
    ini_set('session.cookie_secure', '1');     // requer HTTPS para efetivo
    ini_set('session.cookie_httponly', '1');
    // Em PHP 7.2 não há suporte nativo a SameSite via session_set_cookie_params; configurar no servidor/reverse-proxy.
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// === Dependências de DB (ajuste o caminho conforme seu projeto) ===
// Espera um $pdo (PDO) conectado, com ERRMODE_EXCEPTION e emulacao desativada.
require_once __DIR__ . '/../config/db.php';

// === Funções utilitárias ===
function generate_csrf_token($key) {
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
    return $_SESSION[$key];
}
function verify_csrf_token($key, $token) {
    return isset($_SESSION[$key], $token) && hash_equals($_SESSION[$key], $token);
}
function redirect($path) {
    header('Location: ' . $path);
    exit;
}
function sanitize($v) {
    return trim((string)$v);
}

// === Estado da página ===
$errors = [];
$success = false;

// Pré-gerar token CSRF da tela
$csrf_key = 'csrf_register';
$csrf_token = generate_csrf_token($csrf_key);

// POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1) CSRF
    $posted_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    if (!verify_csrf_token($csrf_key, $posted_token)) {
        $errors[] = 'Falha de verificação CSRF. Recarregue a página e tente novamente.';
    }

    // 2) Inputs
    $username = sanitize(isset($_POST['username']) ? $_POST['username'] : '');
    $email    = sanitize(isset($_POST['email']) ? $_POST['email'] : '');
    $password = isset($_POST['password']) ? (string)$_POST['password'] : '';
    $role_in  = strtolower(sanitize(isset($_POST['role']) ? $_POST['role'] : 'basic'));

    // 3) Whitelist e normalização do papel
    $allowed_roles = ['admin', 'basic'];
    $role = in_array($role_in, $allowed_roles, true) ? $role_in : 'basic';

    // 3.1) Mitigação de criação arbitrária de admin:
    // Use um feature flag/variável de ambiente para bloquear auto-registro de admins em produção.
    // Se ALLOW_ADMIN_SELF_SIGNUP != 'true', força 'basic' independentemente do select.
    $allowAdminSignup = getenv('ALLOW_ADMIN_SELF_SIGNUP') === 'true';
    if (!$allowAdminSignup && $role === 'admin') {
        // Comentário: em produção, considere exigir aprovação manual ou fluxo separado para promover admin.
        $role = 'basic';
    }

    // 4) Validações
    if ($username === '' || !preg_match('/^[a-zA-Z0-9_.-]{3,32}$/', $username)) {
        $errors[] = 'Informe um nome de usuário válido (3-32 chars; letras, números, ".", "_", "-").';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido.';
    }
    if ($password === '' || strlen($password) < 8) {
        $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
    }

    // 5) Duplicidade (username/email)
    if (!$errors) {
        try {
            $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $email]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($exists) {
                if (strcasecmp($exists['username'], $username) === 0) {
                    $errors[] = 'Nome de usuário já está em uso.';
                }
                if (strcasecmp($exists['email'], $email) === 0) {
                    $errors[] = 'E-mail já está cadastrado.';
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Erro ao verificar duplicidade. Tente novamente.';
        }
    }

    // 6) Inserção
    if (!$errors) {
        try {
            // Segurança extra: transação (não é estritamente necessário para uma única inserção,
            // mas padroniza o fluxo com demais operações).
            $pdo->beginTransaction();

            $password_hash = password_hash($password, PASSWORD_DEFAULT); // PHP 7.2 => bcrypt

            $ins = $pdo->prepare('INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)');
            $ins->execute([$username, $email, $password_hash, $role]);

            $pdo->commit();
            $success = true;

            // Limpa token para evitar re-post
            unset($_SESSION[$csrf_key]);

            // Opcional: registre em access_logs se houver tabela e política definida
            // try {
            //     $log = $pdo->prepare('INSERT INTO access_logs (user_id, action, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())');
            //     $log->execute([$pdo->lastInsertId(), 'register', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
            // } catch (Exception $e) { /* silencioso */ }

            // Redireciona para login
            redirect('../auth/login.php?registered=1');
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Erro ao criar a conta. Por favor, tente novamente.';
        }
    }
}

// Regerar token caso tenha sido invalidado após tentativa
$csrf_token = generate_csrf_token($csrf_key);
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Registrar — Poll-App</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons (opcional) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; }
        .card { border-radius: 1rem; }
        .brand { font-weight: 700; letter-spacing: .3px; }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="text-center mb-4">
                <h1 class="brand">Poll-App</h1>
                <p class="text-muted mb-0">Crie sua conta</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger" role="alert">
                    <strong>Não foi possível concluir o cadastro:</strong>
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="mb-3">
                            <label class="form-label" for="username">Usuário</label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= isset($username) ? htmlspecialchars($username, ENT_QUOTES, 'UTF-8') : '' ?>"
                                   required minlength="3" maxlength="32" autocomplete="username">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="email">E-mail</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '' ?>"
                                   required autocomplete="email">
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Senha</label>
                            <input type="password" class="form-control" id="password" name="password"
                                   required minlength="8" autocomplete="new-password">
                            <div class="form-text">Mínimo de 8 caracteres.</div>
                        </div>

                        <!-- Mantém o select já existente no formulário -->
                        <div class="mb-3">
                            <label class="form-label" for="role">Papel</label>
                            <select id="role" name="role" class="form-select">
                                <?php
                                $selectedRole = isset($role_in) ? $role_in : 'basic';
                                ?>
                                <option value="basic" <?= ($selectedRole === 'basic') ? 'selected' : '' ?>>Básico</option>
                                <option value="admin" <?= ($selectedRole === 'admin') ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <div class="form-text">
                                <!-- Dica de segurança: em produção, a criação de Admin pode estar bloqueada por política interna. -->
                                A criação de contas "Admin" pode exigir aprovação conforme configuração do ambiente.
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-person-plus"></i> Criar conta
                            </button>
                        </div>
                    </form>

                    <hr class="my-4">
                    <div class="text-center">
                        <a href="../auth/login.php" class="link-secondary">Já possui conta? Entrar</a>
                    </div>
                </div>
            </div>

            <p class="text-center text-muted small mt-3">
                Ao continuar, você concorda com nossos termos e políticas internas.
            </p>
        </div>
    </div>
</div>

<!-- Bootstrap JS (opcional, para UX melhor com validações visuais etc.) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Validação front-end opcional (não substitui validação server-side)
(function () {
    'use strict';
    var forms = document.querySelectorAll('form');
    Array.prototype.slice.call(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>
</body>
</html>
