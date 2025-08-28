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

$item_id = $_GET['item_id'] ?? null;
if (!$item_id) {
    $prefix = '../'; $title = 'Gerenciar Enquetes';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Item não informado.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Verifica se o item pertence ao admin (traz também a data da assembleia)
$stmt = $pdo->prepare("
    SELECT i.*, 
           a.titulo AS assembleia_nome,
           a.data_assembleia
    FROM itens i
    JOIN assembleias a ON i.assembleia_id = a.id
    WHERE i.id = ? AND a.criada_por = ?
");
$stmt->execute([$item_id, $_SESSION['user_id']]);
$item = $stmt->fetch();

if (!$item) {
    $prefix = '../'; $title = 'Gerenciar Enquetes';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Permissão negada ou item inexistente.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Botão Voltar "inteligente": se veio de results_item, volta pra lá; senão, volta para manage_itens
$from = $_GET['from'] ?? '';
$backHref = ($from === 'results')
  ? "results_item.php?item_id=" . (int)$item_id . "&from=manage_poll"
  : "manage_itens.php?assembleia_id=" . (int)$item['assembleia_id'];

// Ações: ativar/desativar, mover ordem, liberar/ocultar resultados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Alternar ativo/inativo
    if (isset($_POST['toggle_poll_id'])) {
        $poll_id = (int)$_POST['toggle_poll_id'];
        $pdo->prepare("UPDATE polls SET is_active = NOT is_active WHERE id = ?")->execute([$poll_id]);
        header("Location: manage_poll.php?item_id={$item_id}" . ($from ? "&from={$from}" : ""));
        exit;
    }

    // Mover ordem
    if (isset($_POST['move'], $_POST['poll_id'])) {
        $poll_id   = (int)$_POST['poll_id'];
        $direction = $_POST['move'] === 'up' ? -1 : 1;

        $q = $pdo->prepare("SELECT id, ordem FROM polls WHERE item_id = ? ORDER BY ordem");
        $q->execute([$item_id]);
        $polls = $q->fetchAll();

        $idx = array_search($poll_id, array_column($polls, 'id'));
        if ($idx !== false && isset($polls[$idx + $direction])) {
            $current = $polls[$idx];
            $swap    = $polls[$idx + $direction];

            $pdo->prepare("UPDATE polls SET ordem = ? WHERE id = ?")->execute([$swap['ordem'], $current['id']]);
            $pdo->prepare("UPDATE polls SET ordem = ? WHERE id = ?")->execute([$current['ordem'], $swap['id']]);
        }
        header("Location: manage_poll.php?item_id={$item_id}" . ($from ? "&from={$from}" : ""));
        exit;
    }

    // Liberar / Ocultar resultado público
    if (isset($_POST['toggle_result_id'])) {
        $poll_id = (int)$_POST['toggle_result_id'];
        $pdo->prepare("UPDATE polls SET show_results = NOT show_results WHERE id = ?")->execute([$poll_id]);
        header("Location: manage_poll.php?item_id={$item_id}" . ($from ? "&from={$from}" : ""));
        exit;
    }
}

// Recupera enquetes
$stmt = $pdo->prepare("SELECT * FROM polls WHERE item_id = ? ORDER BY ordem");
$stmt->execute([$item_id]);
$polls = $stmt->fetchAll();

// Layout (título com a DESCRIÇÃO DA PAUTA)
$prefix = '../';
$title  = 'Enquetes — ' . htmlspecialchars($item['descricao']);
include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-2">
  <div>
    <h2 class="mb-1"><?= htmlspecialchars($item['descricao']) ?></h2>
    <div class="text-muted">
      <?= htmlspecialchars($item['assembleia_nome']) ?> — 
      <?= date('d/m/Y', strtotime($item['data_assembleia'])) ?> · 
      Pauta nº <?= (int)$item['numero'] ?>
    </div>
  </div>

  <div class="d-flex gap-2">
    <a href="<?= htmlspecialchars($backHref) ?>" class="btn btn-outline-secondary">← Voltar</a>
    <a href="results_item.php?item_id=<?= (int)$item_id ?>&from=manage_poll" class="btn btn-outline-success">
      📊 Resultados
    </a>
    <a href="create_poll.php?item_id=<?= (int)$item_id ?>" class="btn btn-primary">
      <i class="bi bi-plus-circle me-1"></i> Nova Enquete
    </a>
  </div>
</div>

<hr class="mt-3 mb-4">

<?php if (empty($polls)): ?>
  <div class="alert alert-info">Nenhuma enquete criada ainda.</div>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body p-0">
      <table class="table table-hover table-sm align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:70px;">Seq.</th>
            <th>Enquete</th>
            <th style="width:220px;">Status</th>
            <th style="width:300px;">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($polls as $poll): ?>
          <tr>
            <td class="text-nowrap">
              <span class="badge text-bg-secondary me-2"><?= (int)$poll['ordem'] ?></span> <!-- Número da ordem -->
              <!-- mover -->
              <form method="post" class="d-inline">
                <input type="hidden" name="poll_id" value="<?= (int)$poll['id'] ?>">
                <button type="submit" name="move" value="up"
                        class="btn btn-sm btn-outline-secondary" title="Mover para cima">
                  ↑
                </button>
                <button type="submit" name="move" value="down"
                        class="btn btn-sm btn-outline-secondary" title="Mover para baixo">
                  ↓
                </button>
              </form>
            </td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($poll['question']) ?></div>
              <!-- mini tabela de opções + votos (válidos) -->
              <?php
                $o = $pdo->prepare("
                  SELECT o.option_text, COUNT(v.id) AS votos
                  FROM options o
                  LEFT JOIN votes v
                         ON v.option_id = o.id
                        AND v.is_annulled = 0
                  WHERE o.poll_id = ?
                  GROUP BY o.id, o.option_text
                  ORDER BY o.id
                ");
                $o->execute([$poll['id']]);
                $opts = $o->fetchAll();
              ?>
              <?php if (!empty($opts)): ?>
                <div class="small text-muted mt-1">
                  <?php foreach ($opts as $row): ?>
                    <div>• <?= htmlspecialchars($row['option_text']) ?> —
                      <a class="text-decoration-none" href="votes_poll.php?poll_id=<?= (int)$poll['id'] ?>">
                        <?= (int)$row['votos'] ?> voto(s)
                      </a>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <div class="mb-2">
                <span class="badge <?= $poll['is_active'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                  <?= $poll['is_active'] ? 'Votação ativada' : 'Votação desativada' ?> <!-- Status votação -->
                </span>
              </div>
              <div>
                <span class="badge <?= $poll['show_results'] ? 'text-bg-success' : 'text-bg-secondary' ?>">
                  <?= $poll['show_results'] ? 'Resultado público' : 'Somente admin' ?>
                </span>
              </div>
            </td>
            <td class="text-nowrap">
              <form method="post" class="d-inline">
                <input type="hidden" name="toggle_poll_id" value="<?= (int)$poll['id'] ?>">
                <button type="submit" class="btn btn-sm <?= $poll['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                  <?= $poll['is_active'] ? 'Ocultar votação' : 'Liberar votação' ?>
                </button>
              </form>

              <form method="post" class="d-inline">
                <input type="hidden" name="toggle_result_id" value="<?= (int)$poll['id'] ?>">
                <button type="submit" class="btn btn-sm <?= $poll['show_results'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                  <?= $poll['show_results'] ? 'Ocultar resultado' : 'Liberar resultado' ?>
                </button>
              </form>

              <a class="btn btn-sm btn-outline-primary" href="edit_poll.php?poll_id=<?= (int)$poll['id'] ?>">✏️ Editar</a>

              <form method="get" action="delete_poll.php" class="d-inline">
                <input type="hidden" name="poll_id" value="<?= (int)$poll['id'] ?>">
                <input type="hidden" name="item_id" value="<?= (int)$item_id ?>">
                <button type="submit"
                        class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Excluir esta enquete?');">
                  🗑️ Excluir
                </button>
              </form> 
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
