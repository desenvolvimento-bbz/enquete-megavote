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

$poll_id = $_GET['poll_id'] ?? null;
if (!$poll_id) {
    $prefix = '../'; $title = 'Editar Enquete';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Enquete não informada.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Verifica se pertence ao admin + carrega contexto do item/assembleia
$stmt = $pdo->prepare("
    SELECT p.*,
           i.id          AS item_id,
           i.numero      AS item_numero,
           i.descricao   AS item_descricao,
           a.id          AS assembleia_id,
           a.titulo      AS assembleia_nome,
           a.data_assembleia
    FROM polls p
    JOIN itens i        ON p.item_id = i.id
    JOIN assembleias a  ON i.assembleia_id = a.id
    WHERE p.id = ? AND a.criada_por = ?
");
$stmt->execute([(int)$poll_id, $_SESSION['user_id']]);
$poll = $stmt->fetch();

if (!$poll) {
    $prefix = '../'; $title = 'Editar Enquete';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Permissão negada ou enquete inexistente.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

$item_id     = (int)$poll['item_id'];
$question    = $poll['question'];
$max_choices = (int)($poll['max_choices'] ?? 1);

// Verifica se há votos (não anulados ou mesmo anulados — a política aqui é travar edição se houve votação)
$v_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM votes 
    WHERE option_id IN (SELECT id FROM options WHERE poll_id = ?)
");
$v_stmt->execute([$poll_id]);
$tem_voto = $v_stmt->fetchColumn() > 0;

$erro = "";

// Processa edição apenas se NÃO houver votos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$tem_voto) {
    $question     = trim($_POST['question'] ?? '');
    $option_ids   = $_POST['option_ids'] ?? [];
    $options_in   = $_POST['options'] ?? [];
    $max_choices  = max(1, (int)($_POST['max_choices'] ?? 1));

    // Limpa opções vazias só para validação de quantidade,
    // mas no loop de update/insert vamos pular as vazias individualmente.
    $clean_options = array_values(array_filter(array_map('trim', $options_in), fn($t)=>$t !== ''));

    if (count($clean_options) < 2) {
        $erro = "Insira pelo menos duas opções.";
    } elseif ($max_choices > count($clean_options)) {
        $erro = "O número máximo de escolhas não pode ser maior que o número de opções.";
    } elseif ($question === '') {
        $erro = "Informe a pergunta da enquete.";
    }

    if ($erro === '') {
        // Atualiza pergunta e max_choices
        $pdo->prepare("UPDATE polls SET question = ?, max_choices = ? WHERE id = ?")
            ->execute([$question, $max_choices, $poll_id]);

        // Busca IDs antigos
        $old = $pdo->prepare("SELECT id FROM options WHERE poll_id = ?");
        $old->execute([$poll_id]);
        $old_ids = array_column($old->fetchAll(), 'id');

        // Garante alinhamento seguro (novas opções têm option_ids[] vazio)
        $to_keep = array_values(array_filter($option_ids)); // mantém só os que têm ID
        $to_delete = array_diff($old_ids, $to_keep);
        if (!empty($to_delete)) {
            $in = implode(',', array_fill(0, count($to_delete), '?'));
            $del = $pdo->prepare("DELETE FROM options WHERE id IN ($in)");
            $del->execute(array_values($to_delete));
        }

        // Atualiza existentes / Insere novas
        for ($i = 0; $i < count($options_in); $i++) {
            $opt    = trim($options_in[$i] ?? '');
            $opt_id = $option_ids[$i] ?? null;

            if ($opt_id) {
                // Atualiza apenas se não vazio
                $pdo->prepare("UPDATE options SET option_text = ? WHERE id = ?")
                    ->execute([$opt, $opt_id]);
            } else {
                // Insere somente se houver texto
                if ($opt !== '') {
                    $pdo->prepare("INSERT INTO options (poll_id, option_text) VALUES (?, ?)")
                        ->execute([$poll_id, $opt]);
                }
            }
        }

        header("Location: manage_poll.php?item_id=$item_id");
        exit;
    }
}

// Busca opções para exibir/editar
$opt_stmt = $pdo->prepare("SELECT * FROM options WHERE poll_id = ? ORDER BY id");
$opt_stmt->execute([$poll_id]);
$options = $opt_stmt->fetchAll();

// Layout
$prefix = '../';
$title  = 'Editar Enquete — ' . htmlspecialchars($poll['item_descricao']);
include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-2">
  <div>
    <h2 class="mb-1">Editar Enquete</h2>
    <div class="text-muted">
      <?= htmlspecialchars($poll['assembleia_nome']) ?> —
      <?= date('d/m/Y', strtotime($poll['data_assembleia'])) ?> ·
      Item nº <?= (int)$poll['item_numero'] ?> ·
      <span class="fw-semibold"><?= htmlspecialchars($poll['item_descricao']) ?></span>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="manage_poll.php?item_id=<?= (int)$item_id ?>" class="btn btn-outline-secondary">← Voltar</a>
    <a href="result_poll.php?poll_id=<?= (int)$poll_id ?>" class="btn btn-outline-success">📊 Ver resultado</a>
  </div>
</div>

<hr class="mt-3 mb-4">

<?php if ($tem_voto): ?>
  <div class="alert alert-warning">
    <strong>Atenção:</strong> Esta enquete já possui votos e não pode ser editada.
  </div>

  <div class="card shadow-sm mb-3">
    <div class="card-body">
      <div class="mb-2"><span class="fw-semibold">Pergunta:</span> <?= htmlspecialchars($question) ?></div>
      <div class="mb-3"><span class="fw-semibold">Máximo de escolhas:</span> <?= (int)$max_choices ?></div>

      <div class="fw-semibold mb-2">Opções:</div>
      <?php
        // carrega contagem de votos válidos por opção
        $c = $pdo->prepare("
          SELECT o.option_text, COUNT(v.id) AS votos
          FROM options o
          LEFT JOIN votes v
                 ON v.option_id = o.id
                AND v.is_annulled = 0
          WHERE o.poll_id = ?
          GROUP BY o.id, o.option_text
          ORDER BY o.id
        ");
        $c->execute([$poll_id]);
        $rows = $c->fetchAll();
      ?>
      <?php if (!empty($rows)): ?>
        <ul class="list-group">
          <?php foreach ($rows as $r): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
              <?= htmlspecialchars($r['option_text']) ?>
              <span class="badge text-bg-secondary"><?= (int)$r['votos'] ?> voto(s)</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <div class="text-muted">Sem opções cadastradas.</div>
      <?php endif; ?>
    </div>
  </div>

  <div class="d-flex gap-2">
    <a href="manage_poll.php?item_id=<?= (int)$item_id ?>" class="btn btn-outline-secondary">Voltar</a>
    <a href="votes_poll.php?poll_id=<?= (int)$poll_id ?>" class="btn btn-outline-primary">🔎 Ver votos</a>
  </div>

<?php else: ?>

  <?php if (!empty($erro)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <div class="card shadow-sm">
    <div class="card-body">
      <form method="post" id="pollForm" novalidate>
        <!-- Pergunta -->
        <div class="mb-3">
          <label class="form-label fw-semibold">Pergunta da enquete</label>
          <textarea name="question" class="form-control" rows="3" required><?= htmlspecialchars($question) ?></textarea>
        </div>

        <!-- Máximo de escolhas -->
        <div class="mb-3">
          <label class="form-label fw-semibold">Número máximo de escolhas permitidas</label>
          <input type="number" name="max_choices" class="form-control" min="1" max="99" value="<?= (int)$max_choices ?>" required>
        </div>

        <!-- Opções -->
        <div class="mb-2">
          <label class="form-label fw-semibold">Opções</label>
          <div id="options" class="vstack gap-2">
            <?php foreach ($options as $i => $opt): ?>
              <div class="input-group option-row">
                <span class="input-group-text"><?= $i + 1 ?></span>
                <input type="hidden" name="option_ids[]" value="<?= (int)$opt['id'] ?>">
                <input type="text" name="options[]" class="form-control"
                       placeholder="Opção <?= $i + 1 ?>"
                       value="<?= htmlspecialchars($opt['option_text']) ?>" required>
                <button type="button"
                        class="btn btn-outline-danger remove-btn"
                        onclick="removeOption(this)"
                        <?= $i < 2 ? 'style="display:none;"' : '' ?>>
                  <i class="bi bi-x-lg"></i>
                </button>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="mt-2">
            <button type="button" class="btn btn-outline-primary" onclick="addOption()">
              <i class="bi bi-plus-circle me-1"></i> Nova opção
            </button>
          </div>
        </div>

        <hr class="my-4">

        <div class="d-flex gap-2">
          <a href="manage_poll.php?item_id=<?= (int)$item_id ?>" class="btn btn-outline-secondary">Cancelar</a>
          <button type="submit" class="btn btn-primary">Salvar alterações</button>
        </div>
      </form>
    </div>
  </div>

<?php endif; ?>

<script>
// Adiciona nova opção (para edição)
function addOption() {
  const container = document.getElementById('options');
  const index = container.children.length + 1;

  const div = document.createElement('div');
  div.className = 'input-group option-row';
  div.innerHTML = `
    <span class="input-group-text">${index}</span>
    <input type="hidden" name="option_ids[]" value="">
    <input type="text" name="options[]" class="form-control" placeholder="Opção ${index}" required>
    <button type="button" class="btn btn-outline-danger remove-btn" onclick="removeOption(this)">
      <i class="bi bi-x-lg"></i>
    </button>
  `;
  container.appendChild(div);
  updateRemoveButtons();
}

function removeOption(button) {
  const container = document.getElementById('options');
  if (container.children.length > 2) {
    button.closest('.option-row').remove();
    renumberOptions();
    updateRemoveButtons();
  }
}

function renumberOptions() {
  const rows = document.querySelectorAll('#options .option-row');
  rows.forEach((row, idx) => {
    const label = row.querySelector('.input-group-text');
    const input = row.querySelector('input[name="options[]"]');
    if (label) label.textContent = (idx + 1);
    if (input)  input.placeholder = 'Opção ' + (idx + 1);
  });
}

function updateRemoveButtons() {
  const rows = document.querySelectorAll('#options .option-row');
  rows.forEach((row, index) => {
    const btn = row.querySelector('.remove-btn');
    if (btn) btn.style.display = (rows.length <= 2 || index < 2) ? 'none' : 'inline-block';
  });
}

// Validação no submit (mínimo 2 opções e max_choices coerente)
document.getElementById('pollForm')?.addEventListener('submit', function(e) {
  const texts = [...document.querySelectorAll('input[name="options[]"]')]
                  .map(i => i.value.trim())
                  .filter(Boolean);
  if (texts.length < 2) {
    alert('Insira pelo menos duas opções.');
    e.preventDefault();
    return;
  }
  const maxInput = document.querySelector('input[name="max_choices"]');
  const max = parseInt(maxInput.value || '1', 10);
  if (max < 1) {
    alert('O número máximo de escolhas deve ser ao menos 1.');
    e.preventDefault();
    return;
  }
  if (max > texts.length) {
    alert('O número máximo de escolhas não pode ser maior que a quantidade de opções preenchidas.');
    e.preventDefault();
    return;
  }
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
