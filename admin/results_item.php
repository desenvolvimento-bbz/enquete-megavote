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
    $prefix = '../'; $title = 'Resultados do Item';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Item não informado.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// valida item pertence ao admin + pega data e descrição
$stmt = $pdo->prepare("
    SELECT i.*, 
           a.titulo AS assembleia_nome, 
           a.id     AS assembleia_id,
           a.data_assembleia
    FROM itens i
    JOIN assembleias a ON i.assembleia_id = a.id
    WHERE i.id = ? AND a.criada_por = ?
");
$stmt->execute([$item_id, $_SESSION['user_id']]);
$item = $stmt->fetch();
if (!$item) {
    $prefix = '../'; $title = 'Resultados do Item';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Permissão negada ou item inexistente.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// origem para o botão Voltar (manage_poll ou manage_itens)
$from = $_GET['from'] ?? '';
$backHref = ($from === 'manage_poll')
    ? "manage_poll.php?item_id=" . (int)$item['id']
    : "manage_itens.php?assembleia_id=" . (int)$item['assembleia_id'];

// busca enquetes e contagens
$stmt = $pdo->prepare("SELECT id, question, max_choices, show_results, ordem FROM polls WHERE item_id = ? ORDER BY ordem");
$stmt->execute([$item_id]);
$polls = $stmt->fetchAll();

$charts = []; // para Chart.js
foreach ($polls as $p) {
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
    $rows = $opt->fetchAll();

    $labels = array_map(fn($r) => $r['option_text'], $rows);
    $data   = array_map(fn($r) => (int)$r['votos'], $rows);
    $total  = array_sum($data);

    $charts[] = [
        'poll_id'      => (int)$p['id'],
        'ordem'        => (int)$p['ordem'],
        'question'     => $p['question'],
        'max_choices'  => (int)$p['max_choices'],
        'show_results' => (int)$p['show_results'],
        'labels'       => $labels,
        'data'         => $data,
        'total'        => $total,
    ];
}

// Layout
$prefix = '../';
$title  = 'Resultados — ' . htmlspecialchars($item['descricao']);
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
    <button class="btn btn-outline-success" onclick="exportPDF()">📄 Exportar PDF</button>
  </div>
</div>

<hr class="mt-3 mb-4">

<div id="results">
<?php if (empty($charts)): ?>
    <div class="alert alert-info">Não há enquetes para este item.</div>
<?php else: ?>
    <?php foreach ($charts as $c): ?>
      <div class="card mb-3 shadow-sm">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-semibold mb-1"><?= (int)$c['ordem'] ?>. <?= htmlspecialchars($c['question']) ?></div>
              <?php if ($c['show_results']): ?>
                <span class="badge text-bg-success">Liberado ao público</span>
              <?php else: ?>
                <span class="badge text-bg-secondary">Somente admin</span>
              <?php endif; ?>
            </div>
            <div class="text-muted small">Votos válidos: <strong><?= (int)$c['total'] ?></strong></div>
          </div>

          <div class="mt-3" style="height: <?= max(140, count($c['labels']) * 28) ?>px;">
            <canvas id="chart-<?= (int)$c['poll_id'] ?>"></canvas>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<!-- libs -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

<script>
  // Paleta (Primary / Gray)
  const PALETTE = ['#60A33D', '#53554A'];
  function palette(n){
    const out = [];
    for (let i=0;i<n;i++) out.push(PALETTE[i % PALETTE.length]);
    return out;
  }

  // Plugin: escreve o valor no fim da barra
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

  const charts = <?php echo json_encode($charts, JSON_UNESCAPED_UNICODE); ?>;

  charts.forEach(cfg => {
    const el = document.getElementById('chart-'+cfg.poll_id);
    if (!el) return;

    new Chart(el, {
      type: 'bar',
      data: {
        labels: cfg.labels,
        datasets: [{
          data: cfg.data,
          backgroundColor: palette(cfg.labels.length),
          borderColor: palette(cfg.labels.length),
          borderWidth: 1,
          barThickness: 22,
          maxBarThickness: 28
        }]
      },
      options: {
        indexAxis: 'y',          // barras na horizontal
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.parsed.x} voto(s)`
            }
          }
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

  async function exportPDF(){
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('p','pt','a4');

    const results = document.getElementById('results');
    const canvas = await html2canvas(results, {scale: 2, useCORS: true});
    const imgData = canvas.toDataURL('image/png');
    const pageWidth  = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();

    const imgWidth  = pageWidth - 40;
    const imgHeight = canvas.height * (imgWidth / canvas.width);

    pdf.addImage(imgData, 'PNG', 20, 20, imgWidth, imgHeight, undefined, 'FAST');

    let heightLeft = imgHeight - pageHeight + 40;
    while (heightLeft > 0){
      pdf.addPage();
      const position = 20 - (imgHeight - heightLeft);
      pdf.addImage(imgData, 'PNG', 20, position, imgWidth, imgHeight, undefined, 'FAST');
      heightLeft -= pageHeight;
    }

    pdf.save('resultados-item-<?= (int)$item_id ?>.pdf');
  }
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
