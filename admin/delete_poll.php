<?php
// admin/delete_poll.php
declare(strict_types=1);

session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
  header('Location: ../auth/login.php');
  exit;
}

require_once('../config/db.php');

// Timeout + fingerprint
$loginPath = '../auth/login.php';
require_once(__DIR__ . '/../auth/session_timeout.php');

// CSRF para a confirmação final
if (empty($_SESSION['csrf_delpoll'])) {
  $_SESSION['csrf_delpoll'] = bin2hex(random_bytes(32));
}

$poll_id = isset($_GET['poll_id']) ? (int)$_GET['poll_id'] : (int)($_POST['poll_id'] ?? 0);
if ($poll_id <= 0) {
  $prefix = '../'; $title = 'Excluir Enquete';
  include __DIR__ . '/../layout/header.php';
  echo '<div class="alert alert-danger">Enquete não informada.</div>';
  echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
  include __DIR__ . '/../layout/footer.php';
  exit;
}

// Busca enquete + valida propriedade
$q = $pdo->prepare("
  SELECT p.*, i.numero AS item_numero, i.descricao AS item_descricao, i.assembleia_id,
         a.titulo AS assembleia_titulo
  FROM polls p
  JOIN itens i        ON p.item_id = i.id
  JOIN assembleias a  ON i.assembleia_id = a.id
  WHERE p.id = ? AND a.criada_por = ?
");
$q->execute([$poll_id, $_SESSION['user_id']]);
$poll = $q->fetch();

if (!$poll) {
  $prefix = '../'; $title = 'Excluir Enquete';
  include __DIR__ . '/../layout/header.php';
  echo '<div class="alert alert-danger">Permissão negada ou enquete inexistente.</div>';
  echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
  include __DIR__ . '/../layout/footer.php';
  exit;
}

$item_id        = (int)$poll['item_id'];
$assembleia_id  = (int)$poll['assembleia_id'];

// Contagens (para exibir na confirmação)
$cntOpt = $pdo->prepare("SELECT COUNT(*) FROM options WHERE poll_id = ?");
$cntOpt->execute([$poll_id]);
$options_count = (int)$cntOpt->fetchColumn();

$cntVotes = $pdo->prepare("
  SELECT COUNT(*)
  FROM votes v
  JOIN options o ON v.option_id = o.id
  WHERE o.poll_id = ?
");
$cntVotes->execute([$poll_id]);
$votes_count = (int)$cntVotes->fetchColumn();

// Excluir (POST confirmado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm_delete'] ?? '') === 'true') {
  // Valida CSRF
  if (empty($_POST['csrf']) || !hash_equals($_SESSION['csrf_delpoll'], $_POST['csrf'])) {
    $prefix = '../'; $title = 'Excluir Enquete';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Falha de validação (CSRF). Recarregue a página e tente novamente.</div>';
    echo '<a href="manage_poll.php?item_id='.(int)$item_id.'" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
  }

  // Exclui votos → opções → enquete
  $pdo->prepare("
    DELETE v FROM votes v
    JOIN options o ON v.option_id = o.id
    WHERE o.poll_id = ?
  ")->execute([$poll_id]);

  $pdo->prepare("DELETE FROM options WHERE poll_id = ?")->execute([$poll_id]);
  $pdo->prepare("DELETE FROM polls WHERE id = ?")->execute([$poll_id]);

  // Reorganiza ordem das enquetes remanescentes do item
  $rows = $pdo->prepare("SELECT id FROM polls WHERE item_id = ? ORDER BY ordem ASC");
  $rows->execute([$item_id]);
  $rest = $rows->fetchAll();

  $ordem = 1;
  foreach ($rest as $r) {
    $pdo->prepare("UPDATE polls SET ordem = ? WHERE id = ?")->execute([$ordem++, (int)$r['id']]);
  }

  header('Location: manage_poll.php?item_id='.$item_id.'&ok=deleted');
  exit;
}

// --- TELA DE CONFIRMAÇÃO --- //
$prefix = '../';
$title  = 'Excluir Enquete';
include __DIR__ . '/../layout/header.php';
?>

<h2 class="mb-2" style="color:#53554A">
  Excluir Enquete
</h2>
<div class="text-muted mb-3">
  <div><strong>Sala:</strong> <?= htmlspecialchars($poll['assembleia_titulo']) ?></div>
  <div><strong>Pauta <?= (int)$poll['item_numero'] ?>:</strong> <?= htmlspecialchars($poll['item_descricao']) ?></div>
</div>

<div class="card shadow-sm border-0">
  <div class="card-body">
    <h5 class="fw-semibold mb-3" style="color:#53554A">Pergunta</h5>
    <p class="mb-3"><?= nl2br(htmlspecialchars($poll['question'])) ?></p>

    <div class="alert alert-warning">
      <div class="fw-semibold mb-1">Atenção:</div>
      <ul class="mb-0">
        <li>Esta ação irá <strong>apagar definitivamente</strong> a enquete.</li>
        <li>Também serão apagados <strong><?= $votes_count ?></strong> voto(s) e <strong><?= $options_count ?></strong> opção(ões) vinculadas.</li>
        <li>Não é possível desfazer.</li>
      </ul>
    </div>

    <div class="d-flex flex-wrap gap-2">
      <a href="manage_poll.php?item_id=<?= (int)$item_id ?>" class="btn btn-outline-secondary">
        ← Cancelar
      </a>

      <form method="post" class="d-inline">
        <input type="hidden" name="poll_id" value="<?= (int)$poll_id ?>">
        <input type="hidden" name="confirm_delete" value="true">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_delpoll']) ?>">
        <button type="submit"
                class="btn btn-danger"
                onclick="return confirm('Tem certeza que deseja excluir esta enquete e todos os seus dados?');">
          🗑️ Excluir definitivamente
        </button>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>