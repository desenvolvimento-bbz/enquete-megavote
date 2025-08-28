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

// Entrada: aceita GET (início) e POST (confirmação)
$assembleia_id = $_GET['assembleia_id'] ?? $_POST['assembleia_id'] ?? null;
$confirm       = $_POST['confirm_delete'] ?? null;

if (!$assembleia_id) {
    $prefix = '../';
    $title  = 'Excluir assembleia';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Enquete não informada.</div>';
    echo '<a class="btn btn-outline-secondary" href="painel_admin.php">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Verifica se pertence ao admin
$stmt = $pdo->prepare("SELECT * FROM assembleias WHERE id = ? AND criada_por = ?");
$stmt->execute([$assembleia_id, $_SESSION['user_id']]);
$assembleia = $stmt->fetch();

if (!$assembleia) {
    $prefix = '../';
    $title  = 'Excluir assembleia';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Permissão negada ou enquete inexistente.</div>';
    echo '<a class="btn btn-outline-secondary" href="painel_admin.php">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Verifica se há votos registrados (para pedir confirmação extra)
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM votes v
    JOIN options o ON v.option_id = o.id
    JOIN polls   p ON o.poll_id   = p.id
    JOIN itens   i ON p.item_id   = i.id
    WHERE i.assembleia_id = ?
");
$stmt->execute([$assembleia_id]);
$tem_votos = $stmt->fetchColumn() > 0;

// Se há votos e ainda não confirmou, mostra tela de confirmação (POST + CSRF)
if ($tem_votos && $confirm !== 'true') {
    if (empty($_SESSION['csrf_delete_asm'])) {
        $_SESSION['csrf_delete_asm'] = bin2hex(random_bytes(32));
    }
    $csrf = $_SESSION['csrf_delete_asm'];

    $prefix = '../';
    $title  = 'Excluir assembleia';
    include __DIR__ . '/../layout/header.php';
    ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title text-danger mb-3">Excluir Enquete com votos</h5>
        <p class="mb-3">
          ⚠️ A sala da enquete <strong><?= htmlspecialchars($assembleia['titulo']) ?></strong> possui votos registrados.
          <br>Deseja realmente excluí-la? <strong>Todas</strong> as enquetes, opções, votos, itens e inscrições serão
          permanentemente apagados.
        </p>
        <form method="post" class="d-inline">
          <input type="hidden" name="assembleia_id" value="<?= (int)$assembleia_id ?>">
          <input type="hidden" name="confirm_delete" value="true">
          <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
          <button type="submit" class="btn btn-danger">✅ Sim, excluir definitivamente</button>
        </form>
        <a href="painel_admin.php" class="btn btn-outline-secondary ms-2">❌ Cancelar</a>
      </div>
    </div>
    <?php
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Se estamos confirmando um caso com votos, valide CSRF
if ($tem_votos && $confirm === 'true') {
    if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf_delete_asm'] ?? '', $_POST['csrf'])) {
        $prefix = '../';
        $title  = 'Excluir assembleia';
        include __DIR__ . '/../layout/header.php';
        echo '<div class="alert alert-danger">Falha de validação. Recarregue a página e tente novamente.</div>';
        echo '<a class="btn btn-outline-secondary" href="painel_admin.php">← Voltar</a>';
        include __DIR__ . '/../layout/footer.php';
        exit;
    }
    unset($_SESSION['csrf_delete_asm']);
}

// Exclusão em transação (votos → opções → enquetes → itens → inscritos → assembleia)
try {
    $pdo->beginTransaction();

    // 1) VOTOS
    $sqlVotes = "
        DELETE FROM votes 
        WHERE option_id IN (
            SELECT id FROM options 
            WHERE poll_id IN (
                SELECT p.id FROM polls p
                JOIN itens i ON p.item_id = i.id
                WHERE i.assembleia_id = ?
            )
        )
    ";
    $pdo->prepare($sqlVotes)->execute([$assembleia_id]);

    // 2) OPÇÕES
    $sqlOptions = "
        DELETE FROM options
        WHERE poll_id IN (
            SELECT p.id FROM polls p
            JOIN itens i ON p.item_id = i.id
            WHERE i.assembleia_id = ?
        )
    ";
    $pdo->prepare($sqlOptions)->execute([$assembleia_id]);

    // 3) ENQUETES
    $sqlPolls = "
        DELETE FROM polls
        WHERE item_id IN (
            SELECT id FROM itens WHERE assembleia_id = ?
        )
    ";
    $pdo->prepare($sqlPolls)->execute([$assembleia_id]);

    // 4) ITENS
    $pdo->prepare("DELETE FROM itens WHERE assembleia_id = ?")->execute([$assembleia_id]);

    // 5) INSCRITOS
    $pdo->prepare("DELETE FROM inscritos WHERE assembleia_id = ?")->execute([$assembleia_id]);

    // 6) ASSEMBLEIA
    $pdo->prepare("DELETE FROM assembleias WHERE id = ?")->execute([$assembleia_id]);

    $pdo->commit();

    header("Location: painel_admin.php");
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    $prefix = '../';
    $title  = 'Excluir assembleia';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Erro ao excluir: ' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '<a class="btn btn-outline-secondary" href="painel_admin.php">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}
