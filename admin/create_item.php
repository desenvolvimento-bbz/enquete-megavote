<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once('../config/db.php');

// Endurecimento de sessão (timeout + fingerprint)
$loginPath = '../auth/login.php';
require_once(__DIR__ . '/../auth/session_timeout.php');

$assembleia_id = $_GET['assembleia_id'] ?? null;
if (!$assembleia_id) {
    $prefix = '../';
    $title  = 'Criar item';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">ID da Enquete ausente.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Verifica se a assembleia pertence ao admin logado
$check = $pdo->prepare("SELECT * FROM assembleias WHERE id = ? AND criada_por = ?");
$check->execute([$assembleia_id, $_SESSION['user_id']]);
$assembleia = $check->fetch();

if (!$assembleia) {
    $prefix = '../';
    $title  = 'Criar item';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Permissão negada.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Próximo número sugerido
$maxStmt = $pdo->prepare("SELECT COALESCE(MAX(numero),0) FROM itens WHERE assembleia_id = ?");
$maxStmt->execute([$assembleia_id]);
$nextNumber = (int)$maxStmt->fetchColumn() + 1;

// CSRF
if (empty($_SESSION['csrf_item'])) {
    $_SESSION['csrf_item'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_item'];

$erro = "";
$numero = $nextNumber;
$descricao = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_item'], $_POST['csrf'])) {
        $erro = "Falha de validação. Recarregue a página e tente novamente.";
    } else {
        $numero    = max(1, (int)($_POST['numero'] ?? 0));
        $descricao = trim($_POST['descricao'] ?? '');

        if ($descricao === "") {
            $erro = "Descrição é obrigatória.";
        } else {
            // Verificar duplicidade do número no mesmo assembleia_id
            $verifica = $pdo->prepare("SELECT COUNT(*) FROM itens WHERE assembleia_id = ? AND numero = ?");
            $verifica->execute([$assembleia_id, $numero]);
            $existe = (int)$verifica->fetchColumn();

            if ($existe > 0) {
                $erro = "Já existe uma pauta de número {$numero} nesta sala.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO itens (assembleia_id, numero, descricao) VALUES (?, ?, ?)");
                $stmt->execute([$assembleia_id, $numero, $descricao]);
                header("Location: manage_itens.php?assembleia_id={$assembleia_id}");
                exit;
            }
        }
    }
}

// Layout
$prefix = '../';
$title  = 'Criar item';
include __DIR__ . '/../layout/header.php';
?>

<h2 class="mb-3">
  Nova pauta — <?= htmlspecialchars($assembleia['titulo']) ?>
  <small class="text-muted fw-normal">(<?= date('d/m/Y', strtotime($assembleia['data_assembleia'])) ?>)</small>
</h2>

<div class="d-flex gap-2 mb-3">
  <a class="btn btn-outline-secondary" href="manage_itens.php?assembleia_id=<?= (int)$assembleia_id ?>">← Voltar</a>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <?php if (!empty($erro)): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="POST" class="row g-3">
      <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">

      <div class="col-12 col-md-3">
        <label for="numero" class="form-label">Número da pauta</label>
        <input type="number" class="form-control" id="numero" name="numero" min="1"
               value="<?= htmlspecialchars((string)$numero) ?>" required>
        <div class="form-text">Sugerido: <?= (int)$nextNumber ?></div>
      </div>

      <div class="col-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="4" required><?= htmlspecialchars($descricao) ?></textarea>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i> Criar pauta
        </button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
