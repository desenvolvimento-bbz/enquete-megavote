<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once('../config/db.php');

// Timeout + fingerprint
$loginPath = '../auth/login.php';
require_once(__DIR__ . '/../auth/session_timeout.php');

// Parâmetros (aceita GET para abrir a tela de confirmação e POST para executar)
$item_id       = $_GET['item_id'] ?? $_POST['item_id'] ?? null;
$assembleia_id = $_GET['assembleia_id'] ?? $_POST['assembleia_id'] ?? null;

if (!$item_id || !$assembleia_id) {
    $prefix = '../'; $title = 'Excluir item';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Parâmetros inválidos.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Verifica se o item pertence a uma assembleia criada por este admin
$stmt = $pdo->prepare("
    SELECT i.*, a.titulo AS assembleia_titulo, a.data_assembleia
    FROM itens i
    JOIN assembleias a ON i.assembleia_id = a.id
    WHERE i.id = ? AND a.criada_por = ?
");
$stmt->execute([$item_id, $_SESSION['user_id']]);
$item = $stmt->fetch();

if (!$item) {
    $prefix = '../'; $title = 'Excluir item';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Item não encontrado ou permissão negada.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Conta dependências (para exibir no aviso)
$polls_count = (int)$pdo->prepare("SELECT COUNT(*) FROM polls WHERE item_id = ?")
                        ->execute([$item_id]) ? $pdo->query("SELECT ROW_COUNT()") : 0; // dummy
$polls_count_stmt = $pdo->prepare("SELECT COUNT(*) FROM polls WHERE item_id = ?");
$polls_count_stmt->execute([$item_id]);
$polls_count = (int)$polls_count_stmt->fetchColumn();

$options_count_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM options o
    JOIN polls p ON p.id = o.poll_id
    WHERE p.item_id = ?
");
$options_count_stmt->execute([$item_id]);
$options_count = (int)$options_count_stmt->fetchColumn();

$votes_count_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM votes v
    JOIN options o ON o.id = v.option_id
    JOIN polls p ON p.id = o.poll_id
    WHERE p.item_id = ?
");
$votes_count_stmt->execute([$item_id]);
$votes_count = (int)$votes_count_stmt->fetchColumn();

// CSRF para o POST de confirmação
if (empty($_SESSION['csrf_delete_item'])) {
    $_SESSION['csrf_delete_item'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_delete_item'];

// Se veio POST confirmando, valida e executa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'true') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_delete_item'], $_POST['csrf'])) {
        $prefix = '../'; $title = 'Excluir item';
        include __DIR__ . '/../layout/header.php';
        echo '<div class="alert alert-danger">Falha de validação. Recarregue a página e tente novamente.</div>';
        echo '<a href="manage_itens.php?assembleia_id=' . (int)$assembleia_id . '" class="btn btn-outline-secondary">← Voltar</a>';
        include __DIR__ . '/../layout/footer.php';
        exit;
    }

    // Exclusão segura: votos → opções → enquetes → item
    $pdo->beginTransaction();
    try {
        // 1) Votos
        $delVotes = $pdo->prepare("
            DELETE v
            FROM votes v
            JOIN options o ON o.id = v.option_id
            JOIN polls p   ON p.id = o.poll_id
            WHERE p.item_id = ?
        ");
        $delVotes->execute([$item_id]);

        // 2) Opções
        $delOpts = $pdo->prepare("
            DELETE o
            FROM options o
            JOIN polls p ON p.id = o.poll_id
            WHERE p.item_id = ?
        ");
        $delOpts->execute([$item_id]);

        // 3) Enquetes
        $delPolls = $pdo->prepare("DELETE FROM polls WHERE item_id = ?");
        $delPolls->execute([$item_id]);

        // 4) Item
        $delItem = $pdo->prepare("DELETE FROM itens WHERE id = ?");
        $delItem->execute([$item_id]);

        $pdo->commit();

        header("Location: manage_itens.php?assembleia_id=" . (int)$assembleia_id);
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $prefix = '../'; $title = 'Excluir item';
        include __DIR__ . '/../layout/header.php';
        echo '<div class="alert alert-danger">Erro ao excluir: ' . htmlspecialchars($e->getMessage()) . '</div>';
        echo '<a href="manage_itens.php?assembleia_id=' . (int)$assembleia_id . '" class="btn btn-outline-secondary">← Voltar</a>';
        include __DIR__ . '/../layout/footer.php';
        exit;
    }
}

// Sem POST (ou sem confirmação): se NÃO houver votos, pode excluir direto OU mostrar tela?
// Requisito: só pedir confirmação extra quando houver votos.
// -> Se não houver votos, apagamos direto, mantendo confirmação básica do browser na lista, se houver.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $votes_count === 0) {
    // Exclusão direta
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE o FROM options o JOIN polls p ON p.id = o.poll_id WHERE p.item_id = ?")->execute([$item_id]);
        $pdo->prepare("DELETE FROM polls WHERE item_id = ?")->execute([$item_id]);
        $pdo->prepare("DELETE FROM itens WHERE id = ?")->execute([$item_id]);
        $pdo->commit();

        header("Location: manage_itens.php?assembleia_id=" . (int)$assembleia_id);
        exit;
    } catch (Throwable $e) {
        $pdo->rollBack();
        $prefix = '../'; $title = 'Excluir item';
        include __DIR__ . '/../layout/header.php';
        echo '<div class="alert alert-danger">Erro ao excluir: ' . htmlspecialchars($e->getMessage()) . '</div>';
        echo '<a href="manage_itens.php?assembleia_id=' . (int)$assembleia_id . '" class="btn btn-outline-secondary">← Voltar</a>';
        include __DIR__ . '/../layout/footer.php';
        exit;
    }
}

// Se chegou até aqui, há votos → mostrar tela de confirmação reforçada
$prefix = '../';
$title  = 'Excluir item';
include __DIR__ . '/../layout/header.php';
?>

<h2 class="mb-3">Excluir pauta</h2>

<div class="alert alert-warning">
  <div class="d-flex align-items-start">
    <div class="me-2 fs-4">⚠️</div>
    <div>
      <strong>Atenção:</strong> Esta pauta possui dados vinculados.<br>
      <small class="text-muted">A exclusão é irreversível e removerá permanentemente as enquetes, opções e votos desta pauta.</small>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <h5 class="card-title mb-3"><?= htmlspecialchars($item['numero']) ?> — <?= htmlspecialchars($item['descricao']) ?></h5>
    <ul class="list-unstyled mb-0">
      <li><strong>Condomínio:</strong> <?= htmlspecialchars($item['assembleia_titulo']) ?></li>
      <li><strong>Data:</strong> <?= date('d/m/Y', strtotime($item['data_assembleia'])) ?></li>
      <li><strong>Enquetes:</strong> <?= $polls_count ?></li>
      <li><strong>Opções:</strong> <?= $options_count ?></li>
      <li><strong>Votos registrados:</strong> <?= $votes_count ?></li>
    </ul>
  </div>
</div>

<form method="post" class="d-inline">
  <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
  <input type="hidden" name="item_id" value="<?= (int)$item_id ?>">
  <input type="hidden" name="assembleia_id" value="<?= (int)$assembleia_id ?>">
  <input type="hidden" name="confirm_delete" value="true">
  <button type="submit" class="btn btn-danger">
    ✅ Sim, excluir definitivamente
  </button>
</form>
<a href="manage_itens.php?assembleia_id=<?= (int)$assembleia_id ?>" class="btn btn-outline-secondary ms-2">❌ Cancelar</a>

<?php include __DIR__ . '/../layout/footer.php'; ?>
