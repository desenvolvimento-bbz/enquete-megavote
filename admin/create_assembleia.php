<?php
// --- Guarda de sessão endurecida (admin) ---
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

// --- DB ---
require_once __DIR__ . '/../config/db.php';

// --- CSRF helpers (fallback) ---
if (!function_exists('generateCsrf')) {
    function generateCsrf(): string {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }
}
if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken(string $token): bool {
        return isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $token);
    }
}
$csrf = generateCsrf();

// --- Gerador de token público (link da sala) ---
function make_token(int $len = 16): string {
    // base64url ~22 chars; baixo risco de colisão e fácil de colocar na URL
    return rtrim(strtr(base64_encode(random_bytes($len)), '+/', '-_'), '=');
}

// --- Lógica de POST ---
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!verifyCsrfToken($_POST['csrf'] ?? '')) {
        http_response_code(403);
        die('Token CSRF inválido. Recarregue a página e tente novamente.');
    }

    $titulo     = trim($_POST['titulo'] ?? '');
    $data       = $_POST['data_assembleia'] ?? '';
    $publicRaw  = $_POST['public_enabled'] ?? '1';
    $public     = ($publicRaw === '1') ? 1 : 0;
    $criada_por = $_SESSION['user_id'];

    // Validação de data Y-m-d
    $okDate = false;
    if ($data) {
        $dt = DateTime::createFromFormat('Y-m-d', $data);
        $okDate = $dt && $dt->format('Y-m-d') === $data;
    }

    if ($titulo === '' || !$okDate) {
        $erro = 'Preencha todos os campos corretamente.';
    } else {
        // tenta inserir com invite_token único
        $maxTries = 5;
        $ok = false;
        for ($i=0; $i<$maxTries; $i++) {
            $token = make_token(16);
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO assembleias (titulo, data_assembleia, criada_por, invite_token, public_enabled)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$titulo, $data, $criada_por, $token, $public]);
                $ok = true;
                break;
            } catch (PDOException $e) {
                // colisão de UNIQUE (invite_token) → tenta outro
                if ($e->getCode() === '23000') { continue; }
                // outros erros: exibe genérico
                $erro = 'Erro ao criar a sala. Tente novamente.';
                break;
            }
        }

        if ($ok) {
            // Você pode exibir o link no painel_admin/manage_itens
            // Ex: https://SEU_HOST/public/join.php?token={invite_token}
            header('Location: painel_admin.php');
            exit;
        }

        if (!$erro) {
            $erro = 'Não foi possível gerar o link público. Tente novamente.';
        }
    }
}

// --- Layout/header ---
$prefix = '../';
$title  = 'Nova Assembleia';
include __DIR__ . '/../layout/header.php';
?>

<div class="container py-4">
  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <a href="painel_admin.php" class="text-decoration-none">← Voltar</a>
      <h2 class="mt-2 mb-3">Nova Sala de Enquete</h2>

      <?php if ($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
      <?php endif; ?>

      <form method="POST" class="row g-3">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

        <div class="col-12">
          <label class="form-label">Nome do condomínio</label>
          <input
            type="text"
            name="titulo"
            class="form-control"
            required
            value="<?= isset($_POST['titulo']) ? htmlspecialchars($_POST['titulo']) : '' ?>">
        </div>

        <div class="col-12 col-sm-6">
          <label class="form-label">Data da enquete</label>
          <input
            type="date"
            name="data_assembleia"
            class="form-control"
            required
            value="<?= isset($_POST['data_assembleia']) ? htmlspecialchars($_POST['data_assembleia']) : '' ?>">
        </div>

        <div class="col-12 col-sm-6">
          <label class="form-label d-block">Link público</label>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="public_enabled" name="public_enabled" value="1"
                   <?= (!isset($_POST['public_enabled']) || $_POST['public_enabled'] === '1') ? 'checked' : '' ?>>
            <label class="form-check-label" for="public_enabled">
              Permitir acesso via link (recomendado)
            </label>
          </div>
          <div class="form-text">
            Você poderá copiar o link no painel. É possível desativar ou regenerar esse link depois.
          </div>
        </div>

        <div class="col-12">
          <button type="submit" class="btn btn-primary">Criar sala</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>