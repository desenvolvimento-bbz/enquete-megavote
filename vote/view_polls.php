<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'basic') {
    header("Location: ../auth/login.php");
    exit;
}
require_once('../config/db.php');

// Timeout + fingerprint
$loginPath = '../auth/login.php';
require_once(__DIR__ . '/../auth/session_timeout.php');

$user_id = $_SESSION['user_id'];
$item_id = $_GET['item_id'] ?? null;

if (!$item_id) {
  $prefix = '../'; $title = 'Enquetes';
  include __DIR__ . '/../layout/header.php';
  echo '<div class="alert alert-danger">Pauta não informada.</div>';
  echo '<a href="painel_basic.php" class="btn btn-outline-secondary">← Voltar</a>';
  include __DIR__ . '/../layout/footer.php';
  exit;
}

// Se a sessão veio de link público, garanta que o item pertence à assembleia escopada
if (!empty($_SESSION['guest_scope_assembleia_id'])) {
    $chk = $pdo->prepare("
        SELECT i.assembleia_id
        FROM itens i
        WHERE i.id = ?
        LIMIT 1
    ");
    $chk->execute([$item_id]);
    $row = $chk->fetch();
    if (!$row || (int)$row['assembleia_id'] !== (int)$_SESSION['guest_scope_assembleia_id']) {
        header('Location: view_itens.php?assembleia_id='.(int)$_SESSION['guest_scope_assembleia_id']);
        exit;
    }
}

// Confirma que o usuário é participante da assembleia do item (participants) e carrega o contexto
$stmt = $pdo->prepare("
  SELECT i.id, i.numero, i.descricao,
         a.id AS assembleia_id, a.titulo AS assembleia_titulo, a.data_assembleia, a.status,
         p.is_annulled AS part_anulada
  FROM itens i
  JOIN assembleias a  ON a.id = i.assembleia_id
  JOIN participants p ON p.assembleia_id = a.id AND p.user_id = ?
  WHERE i.id = ?
");
$stmt->execute([$user_id, $item_id]);
$ctx = $stmt->fetch();

if (!$ctx) {
  $prefix = '../'; $title = 'Enquetes';
  include __DIR__ . '/../layout/header.php';
  echo '<div class="alert alert-danger">Você não tem acesso a esta assembleia ou a pauta é inválida.</div>';
  echo '<a href="painel_basic.php" class="btn btn-outline-secondary">← Voltar</a>';
  include __DIR__ . '/../layout/footer.php';
  exit;
}

$assembleia_id   = (int)$ctx['assembleia_id'];
$item_numero     = (int)$ctx['numero'];
$item_descricao  = $ctx['descricao'];
$asm_titulo      = $ctx['assembleia_titulo'];
$asm_data        = $ctx['data_assembleia'];
$asm_status      = $ctx['status'];
$insc_anulada    = ((int)$ctx['part_anulada'] === 1); // mantive o nome usado na view

// Carrega enquetes: ativas OU com resultado liberado
$stmt = $pdo->prepare("
  SELECT id, question, max_choices, is_active, show_results, ordem
  FROM polls
  WHERE item_id = ? AND (is_active = 1 OR show_results = 1)
  ORDER BY ordem
");
$stmt->execute([$item_id]);
$polls = $stmt->fetchAll();

// Mapeia votos do usuário neste item
$userVotes = []; // [poll_id] => [ option_id, ... ]
if (!empty($polls)) {
  $stmt = $pdo->prepare("
    SELECT o.poll_id, v.option_id, v.is_annulled
    FROM votes v
    JOIN options o ON v.option_id = o.id
    WHERE v.user_id = ? AND o.poll_id IN (SELECT id FROM polls WHERE item_id = ?)
  ");
  $stmt->execute([$user_id, $item_id]);
  foreach ($stmt->fetchAll() as $row) {
    $pid = (int)$row['poll_id'];
    $userVotes[$pid] = $userVotes[$pid] ?? [];
    $userVotes[$pid][] = (int)$row['option_id'];
  }
}

// Pré-carrega dados de gráfico para polls liberadas
$charts = []; // [{poll_id, labels[], data[]}]
foreach ($polls as $p) {
  if ((int)$p['show_results'] === 1) {
    $opt = $pdo->prepare("
      SELECT o.id, o.option_text, COUNT(v.id) AS votos
      FROM options o
      LEFT JOIN votes v
        ON v.option_id = o.id
       AND v.is_annulled = 0
      WHERE o.poll_id = ?
      GROUP BY o.id, o.option_text
      ORDER BY o.id
    ");
    $opt->execute([$p['id']]);
    $rows   = $opt->fetchAll();
    $labels = array_map(fn($r)=>$r['option_text'], $rows);
    $data   = array_map(fn($r)=>(int)$r['votos'],   $rows);
    $charts[] = [
      'poll_id' => (int)$p['id'],
      'labels'  => $labels,
      'data'    => $data
    ];
  }
}

// Layout
$prefix = '../';
$title  = 'Enquetes — ' . htmlspecialchars($item_descricao);
include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h2 class="mb-1">Enquetes — <?= htmlspecialchars($item_descricao) ?></h2>
    <div class="text-muted">
      Sala: <strong><?= htmlspecialchars($asm_titulo) ?></strong>
      &nbsp;•&nbsp; Pauta: <strong><?= $item_numero ?></strong>
      <span class="ms-2">Data: <?= $asm_data ? date('d/m/Y', strtotime($asm_data)) : '-' ?></span>
      <span class="ms-2 badge <?= $asm_status==='em_andamento' ? 'text-bg-success' : 'text-bg-secondary' ?>">
        <?= $asm_status==='em_andamento' ? 'Em andamento' : 'Encerrada' ?>
      </span>
      <?php if ($insc_anulada): ?>
        <span class="ms-2 badge text-bg-warning">Sua participação está anulada (novos votos ficam anulados)</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="view_itens.php?assembleia_id=<?= $assembleia_id ?>" class="btn btn-outline-secondary">← Voltar</a>
    <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
      <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
    </button>
  </div>
</div>

<?php if (empty($polls)): ?>
  <div class="alert alert-info">Não há enquetes disponíveis neste item.</div>
<?php else: ?>
  <div class="vstack gap-3">
    <?php foreach ($polls as $p): ?>
      <?php
        // opções desta enquete
        $optStmt = $pdo->prepare("SELECT id, option_text FROM options WHERE poll_id = ? ORDER BY id");
        $optStmt->execute([$p['id']]);
        $options    = $optStmt->fetchAll();
        $pid        = (int)$p['id'];
        $voted      = isset($userVotes[$pid]) && count($userVotes[$pid]) > 0;
        $isMulti    = ((int)($p['max_choices'] ?? 1)) > 1;
        $maxChoices = max(1, (int)($p['max_choices'] ?? 1));
      ?>
      <div class="card shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <div>
              <div class="small text-muted">Enquete #<?= (int)$p['ordem'] ?></div>
              <h5 class="card-title mb-1"><?= htmlspecialchars($p['question']) ?></h5>
              <div class="small text-muted">
                <?php if ((int)$p['is_active'] === 1): ?>
                  <span class="badge text-bg-success ms-1">Votação ativa</span>
                <?php else: ?>
                  <span class="badge text-bg-secondary ms-1">Votação encerrada</span>
                <?php endif; ?>
                <?php if ($isMulti): ?>
                  <span class="badge text-bg-info ms-1">Múltipla escolha (máx. <?= $maxChoices ?>)</span>
                <?php else: ?>
                  <span class="badge text-bg-info ms-1">Escolha única</span>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <hr class="my-3">

          <?php if ((int)$p['show_results'] === 1): ?>
            <!-- Resultado público: mostra gráfico; nunca mostra formulário -->
            <div class="chart-wrap" style="position:relative; height: <?= max(140, count($options)*28) ?>px;">
              <canvas id="chart-<?= $pid ?>"></canvas>
            </div>
            <?php if ($voted): ?>
              <p class="mt-3 mb-0">
                Você votou em:
                <strong>
                  <?php
                    $chosen = array_filter($options, fn($o)=> in_array($o['id'], $userVotes[$pid]));
                    echo implode(', ', array_map(fn($o)=>htmlspecialchars($o['option_text']), $chosen));
                  ?>
                </strong>
              </p>
            <?php endif; ?>

          <?php else: ?>
            <?php if ((int)$p['is_active'] === 1 && !$voted): ?>
              <!-- Formulário de votação -->
              <form method="post" action="submit_vote.php?item_id=<?= (int)$item_id ?>" class="vote-form" data-max="<?= $maxChoices ?>">
                <input type="hidden" name="poll_id" value="<?= $pid ?>">
                <?php foreach ($options as $opt): ?>
                  <div class="form-check mb-1">
                    <input
                      class="form-check-input opt-ck"
                      type="<?= $isMulti ? 'checkbox' : 'radio' ?>"
                      name="<?= $isMulti ? 'option_ids[]' : 'option_id' ?>"
                      value="<?= (int)$opt['id'] ?>"
                      id="opt-<?= (int)$opt['id'] ?>"
                      <?= $isMulti ? '' : 'required' ?>
                    >
                    <label class="form-check-label" for="opt-<?= (int)$opt['id'] ?>">
                      <?= htmlspecialchars($opt['option_text']) ?>
                    </label>
                  </div>
                <?php endforeach; ?>
                <div class="mt-3">
                  <button type="submit" class="btn btn-primary">Enviar voto</button>
                </div>
              </form>
            <?php else: ?>
              <!-- Já votou ou votação encerrada -->
              <?php if ($voted): ?>
                <p class="mb-0">
                  Você votou em:
                  <strong>
                    <?php
                      $chosen = array_filter($options, fn($o)=> in_array($o['id'], $userVotes[$pid]));
                      echo implode(', ', array_map(fn($o)=>htmlspecialchars($o['option_text']), $chosen));
                    ?>
                  </strong>
                </p>
              <?php else: ?>
                <p class="text-muted mb-0">Votação encerrada.</p>
              <?php endif; ?>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>

<!-- Scripts específicos da página -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  // Limite de checkboxes por formulário (múltipla escolha)
  document.querySelectorAll('.vote-form').forEach(form=>{
    const max    = parseInt(form.dataset.max || "1");
    const checks = form.querySelectorAll('.opt-ck[type="checkbox"]');
    if (checks.length && max > 1){
      checks.forEach(cb=>{
        cb.addEventListener('change', ()=>{
          const n = [...checks].filter(x=>x.checked).length;
          if (n > max){
            cb.checked = false;
            alert(`Você pode escolher no máximo ${max} opção(ões).`);
          }
        });
      });
    }
  });

  // Gráficos para polls liberadas
  const charts = <?php echo json_encode($charts, JSON_UNESCAPED_UNICODE); ?>;

  // Paleta padrão do app
  const PALETTE = ['#60A33D', '#53554A'];
  const palette = (n)=> Array.from({length:n}, (_,i)=> PALETTE[i % PALETTE.length]);

  // Plugin: valor ao fim da barra
  const ValueAtEndPlugin = {
    id: 'valueAtEnd',
    afterDatasetsDraw(chart) {
      const ctx = chart.ctx;
      ctx.save();
      ctx.fillStyle = '#333';
      ctx.font = '12px sans-serif';
      ctx.textBaseline = 'middle';
      const meta = chart.getDatasetMeta(0);
      const data = chart.data.datasets[0].data || [];
      meta.data.forEach((bar, i) => {
        const val = data[i];
        if (val == null) return;
        const pos = bar.tooltipPosition();
        ctx.fillText(val, pos.x + 6, pos.y);
      });
      ctx.restore();
    }
  };

  charts.forEach(c=>{
    const el = document.getElementById('chart-'+c.poll_id);
    if (!el) return;
    new Chart(el, {
      type: 'bar',
      data: {
        labels: c.labels,
        datasets: [{
          data: c.data,
          backgroundColor: palette(c.labels.length),
          borderColor: palette(c.labels.length),
          borderWidth: 1
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (ctx)=> `${ctx.parsed.x} voto(s)` } }
        },
        scales: {
          x: { beginAtZero: true, ticks: { precision: 0 } },
          y: { ticks: { autoSkip: false } }
        },
        layout: { padding: { right: 28 } }
      },
      plugins: [ValueAtEndPlugin]
    });
  });
</script>
