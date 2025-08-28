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

$item_id = $_GET['item_id'] ?? null;
if (!$item_id) {
    $prefix = '../';
    $title  = 'Editar item';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">ID do item inválido.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Busca o item e verifica permissão (item deve pertencer a assembleia criada por este admin)
$stmt = $pdo->prepare("
    SELECT i.*, a.titulo AS assembleia_titulo, a.data_assembleia
    FROM itens i
    JOIN assembleias a ON i.assembleia_id = a.id
    WHERE i.id = ? AND a.criada_por = ?
");
$stmt->execute([$item_id, $_SESSION['user_id']]);
$item = $stmt->fetch();

if (!$item) {
    $prefix = '../';
    $title  = 'Editar item';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Pauta não encontrada ou sem permissão.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// CSRF
if (empty($_SESSION['csrf_edit_item'])) {
    $_SESSION['csrf_edit_item'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_edit_item'];

$erro = "";
$numero    = (int)$item['numero'];
$descricao = $item['descricao'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valida CSRF
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_edit_item'], $_POST['csrf'])) {
        $erro = "Falha de validação. Recarregue a página e tente novamente.";
    } else {
        $numero    = max(1, (int)($_POST['numero'] ?? 0));
        $descricao = trim($_POST['descricao'] ?? '');

        if ($descricao === '') {
            $erro = "Descrição é obrigatória.";
        } else {
            // Verificar se o novo número já existe em OUTRO item da mesma assembleia
            $check_num = $pdo->prepare("SELECT COUNT(*) FROM itens WHERE assembleia_id = ? AND numero = ? AND id != ?");
            $check_num->execute([$item['assembleia_id'], $numero, $item_id]);
            $existe = (int)$check_num->fetchColumn();

            if ($existe > 0) {
                $erro = "Já existe uma pauta número {$numero} nesta assembleia.";
            } else {
                $update = $pdo->prepare("UPDATE itens SET numero = ?, descricao = ? WHERE id = ?");
                $update->execute([$numero, $descricao, $item_id]);

                // Sucesso → voltar para a listagem de itens
                header("Location: manage_itens.php?assembleia_id=" . (int)$item['assembleia_id']);
                exit;
            }
        }
    }
}

// Layout
$prefix = '../';
$title  = 'Editar item';
include __DIR__ . '/../layout/header.php';
?>

<h2 class="mb-3">
  Editar pauta — <?= htmlspecialchars($item['assembleia_titulo']) ?>
  <small class="text-muted fw-normal">(<?= date('d/m/Y', strtotime($item['data_assembleia'])) ?>)</small>
</h2>

<div class="d-flex gap-2 mb-3">
  <a class="btn btn-outline-secondary" href="manage_itens.php?assembleia_id=<?= (int)$item['assembleia_id'] ?>">← Voltar</a>
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
      </div>

      <div class="col-12">
        <label for="descricao" class="form-label">Descrição</label>
        <textarea class="form-control" id="descricao" name="descricao" rows="4" required><?= htmlspecialchars($descricao) ?></textarea>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-primary">
          <i class="bi bi-save me-1"></i> Salvar alterações
        </button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
