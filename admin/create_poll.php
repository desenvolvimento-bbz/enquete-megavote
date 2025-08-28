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
    $prefix = '../'; $title = 'Nova Enquete';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Item não informado.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Verifica se o item pertence ao admin (traz também info da assembleia)
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
    $prefix = '../'; $title = 'Nova Enquete';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Permissão negada ou item inexistente.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

$erro = "";
$max_choices = (int)($_POST['max_choices'] ?? 1);
if ($max_choices < 1) $max_choices = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = trim($_POST['question'] ?? '');
    $options  = array_map('trim', $_POST['options'] ?? []);
    $options  = array_values(array_filter($options, fn($v) => $v !== '')); // remove vazias
    $max_choices = max(1, (int)($_POST['max_choices'] ?? 1));
    $created_by  = $_SESSION['user_id'];

    if (count($options) < 2) {
        $erro = "Insira pelo menos duas opções.";
    } elseif ($max_choices > count($options)) {
        $erro = "O número máximo de escolhas não pode ser maior que a quantidade de opções.";
    } elseif ($question === '') {
        $erro = "Informe a pergunta da enquete.";
    } else {
        // Cria enquete
        $stmt = $pdo->prepare("
            INSERT INTO polls (question, item_id, created_by, max_choices)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$question, $item_id, $created_by, $max_choices]);
        $poll_id = (int)$pdo->lastInsertId();

        // Define ordem da enquete como último valor
        $order_stmt = $pdo->prepare("SELECT COALESCE(MAX(ordem), 0) FROM polls WHERE item_id = ?");
        $order_stmt->execute([$item_id]);
        $max_ordem = (int)$order_stmt->fetchColumn();
        $pdo->prepare("UPDATE polls SET ordem = ? WHERE id = ?")->execute([$max_ordem + 1, $poll_id]);

        // Inserir opções
        $opt_stmt = $pdo->prepare("INSERT INTO options (poll_id, option_text) VALUES (?, ?)");
        foreach ($options as $opt) {
            $opt_stmt->execute([$poll_id, $opt]);
        }

        header("Location: manage_poll.php?item_id=$item_id");
        exit;
    }
}

// Layout
$prefix = '../';
$title  = 'Nova Enquete — ' . htmlspecialchars($item['descricao']);
include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-start mb-2">
  <div>
    <h2 class="mb-1">Nova Enquete</h2>
    <div class="text-muted">
      <?= htmlspecialchars($item['assembleia_nome']) ?> — 
      <?= date('d/m/Y', strtotime($item['data_assembleia'])) ?> · 
      Item nº <?= (int)$item['numero'] ?> · 
      <span class="fw-semibold"><?= htmlspecialchars($item['descricao']) ?></span>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="manage_poll.php?item_id=<?= (int)$item_id ?>" class="btn btn-outline-secondary">← Voltar</a>
  </div>
</div>

<hr class="mt-3 mb-4">

<?php if (!empty($erro)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" id="pollForm" novalidate>
      <!-- Pergunta -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Pergunta da enquete</label>
        <textarea name="question" class="form-control" rows="3" required><?= htmlspecialchars($_POST['question'] ?? '') ?></textarea>
        <div class="form-text">Ex.: “Aprovar a reforma da fachada?”</div>
      </div>

      <!-- Máximo de escolhas -->
      <div class="mb-3">
        <label class="form-label fw-semibold">Número máximo de escolhas permitidas</label>
        <input type="number" name="max_choices" class="form-control" min="1" max="99" value="<?= (int)$max_choices ?>" required>
        <div class="form-text">Use <strong>1</strong> para voto único. Para múltipla escolha, informe o limite.</div>
      </div>

      <!-- Opções -->
      <div class="mb-2">
        <label class="form-label fw-semibold">Opções</label>
        <div id="options" class="vstack gap-2">
          <?php
            $initial_options = $_POST['options'] ?? ["", ""];
            // Garante ao menos 2 campos
            if (count($initial_options) < 2) $initial_options = array_pad($initial_options, 2, "");
            foreach ($initial_options as $i => $opt):
          ?>
          <div class="input-group option-row">
            <span class="input-group-text"><?= $i + 1 ?></span>
            <input type="text" name="options[]" class="form-control" placeholder="Opção <?= $i + 1 ?>" value="<?= htmlspecialchars($opt) ?>" required>
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
            <i class="bi bi-plus-circle me-1"></i> Adicionar opção
          </button>
        </div>
      </div>

      <hr class="my-4">

      <div class="d-flex gap-2">
        <a href="manage_poll.php?item_id=<?= (int)$item_id ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">Criar Enquete</button>
      </div>
    </form>
  </div>
</div>

<script>
// Adiciona uma nova opção
function addOption() {
  const container = document.getElementById('options');
  const index = container.children.length + 1;

  const div = document.createElement('div');
  div.className = 'input-group option-row';
  div.innerHTML = `
    <span class="input-group-text">${index}</span>
    <input type="text" name="options[]" class="form-control" placeholder="Opção ${index}" required>
    <button type="button" class="btn btn-outline-danger remove-btn" onclick="removeOption(this)">
      <i class="bi bi-x-lg"></i>
    </button>
  `;
  container.appendChild(div);
  updateRemoveButtons();
}

// Remove uma opção (mantém ao menos 2)
function removeOption(button) {
  const container = document.getElementById('options');
  if (container.children.length > 2) {
    button.closest('.option-row').remove();
    renumberOptions();
    updateRemoveButtons();
  }
}

function renumberOptions() {
  const container = document.getElementById('options');
  [...container.querySelectorAll('.option-row')].forEach((row, idx) => {
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

// Validação simples no submit
document.getElementById('pollForm').addEventListener('submit', function(e) {
  const texts = [...document.querySelectorAll('input[name="options[]"]')].map(i => i.value.trim()).filter(Boolean);
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
