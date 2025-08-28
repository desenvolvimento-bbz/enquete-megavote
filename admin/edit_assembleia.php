<?php
// --- Guarda de sessão (admin) ---
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

// --- DB ---
require_once __DIR__ . '/../config/db.php';

// --- Parâmetros ---
$assembleia_id = isset($_GET['assembleia_id']) ? (int)$_GET['assembleia_id'] : 0;
if ($assembleia_id <= 0) {
  die('Enquete não informada.');
}

// --- Verifica se pertence ao admin ---
$stmt = $pdo->prepare('SELECT * FROM assembleias WHERE id = ? AND criada_por = ?');
$stmt->execute([$assembleia_id, $_SESSION['user_id']]);
$assembleia = $stmt->fetch();
if (!$assembleia) {
  die('Permissão negada ou enquete inexistente.');
}

$erro = '';
// Fallback das helpers de CSRF (caso ainda não estejam no header.php)
if (!function_exists('generateCsrf')) {
  function generateCsrf(): string {
    if (empty($_SESSION['_csrf'])) {
      $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
  }
}
if (!function_exists('verifyCsrf')) {
  function verifyCsrf(?string $token): bool {
    return isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], (string)$token);
  }
}

// --- POST: processa edição (SEM restrição por votos) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!verifyCsrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    die('Falha de verificação CSRF.');
  }

  $titulo = trim($_POST['titulo'] ?? '');
  $data   = $_POST['data_assembleia'] ?? '';
  $status = $_POST['status'] ?? '';

  // Normaliza status
  $statusMap = ['em_andamento', 'encerrada'];
  if (!in_array($status, $statusMap, true)) {
    $status = $assembleia['status']; // fallback no valor atual
  }

  // Validações simples
  if ($titulo === '' || $data === '') {
    $erro = 'Preencha todos os campos corretamente.';
  } else {
    // Atualiza
    $up = $pdo->prepare('UPDATE assembleias SET titulo = ?, data_assembleia = ?, status = ? WHERE id = ?');
    $up->execute([$titulo, $data, $status, $assembleia_id]);
    header('Location: painel_admin.php');
    exit;
  }
}

// --- Layout ---
$prefix = '../';
$title  = 'Editar Assembleia';
include __DIR__ . '/../layout/header.php';

$csrf = generateCsrf();
?>

<div class="mb-3">
  <a href="painel_admin.php" class="text-decoration-none">← Voltar</a>
</div>

<h2 class="mb-3">Editar Enquete</h2>

<?php if ($erro): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<form method="post" class="card card-body shadow-sm" style="max-width:680px;">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

  <div class="mb-3">
    <label class="form-label">Nome do condomínio</label>
    <input type="text" name="titulo" class="form-control" required
           value="<?= htmlspecialchars($assembleia['titulo']) ?>">
  </div>

  <div class="mb-3">
    <label class="form-label">Data da assembleia</label>
    <input type="date" name="data_assembleia" class="form-control" required
           value="<?= htmlspecialchars($assembleia['data_assembleia']) ?>">
  </div>

  <div class="mb-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select" required>
      <option value="em_andamento" <?= $assembleia['status'] === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
      <option value="encerrada"     <?= $assembleia['status'] === 'encerrada' ? 'selected' : '' ?>>Encerrada</option>
    </select>
  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary">Salvar alterações</button>
    <a href="painel_admin.php" class="btn btn-outline-secondary">Cancelar</a>
  </div>
</form>

<?php include __DIR__ . '/../layout/footer.php'; ?>
