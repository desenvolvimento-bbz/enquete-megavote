<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
require_once('../config/db.php');

$poll_id = $_GET['poll_id'] ?? null;
if (!$poll_id) { die("Enquete não informada."); }

// Verifica propriedade
$stmt = $pdo->prepare("
    SELECT p.*, i.descricao AS item_descricao, a.titulo AS assembleia_titulo, a.id AS assembleia_id
    FROM polls p
    JOIN itens i ON p.item_id = i.id
    JOIN assembleias a ON i.assembleia_id = a.id
    WHERE p.id = ? AND a.criada_por = ?
");
$stmt->execute([(int)$poll_id, $_SESSION['user_id']]);
$poll = $stmt->fetch();
if (!$poll) { die("Permissão negada ou enquete inexistente."); }

// Carrega opções com contagem (apenas votos não anulados)
$opt = $pdo->prepare("
    SELECT o.id, o.option_text, COUNT(v.id) AS votos
    FROM options o
    LEFT JOIN votes v ON v.option_id = o.id AND v.is_annulled = 0
    WHERE o.poll_id = ?
    GROUP BY o.id, o.option_text
    ORDER BY o.id
");
$opt->execute([$poll_id]);
$options = $opt->fetchAll();

$labels = array_column($options, 'option_text');
$data   = array_map('intval', array_column($options, 'votos'));
$total  = array_sum($data);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Resultado da Enquete</title>
  <style>
    .poll-card{border:1px solid #ccc;padding:12px;margin-bottom:14px;border-radius:6px}
    .badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;margin-left:6px}
    .ok{background:#e8f5e9;color:#2e7d32}
    .off{background:#ffebee;color:#c62828}
    /* Necessário para o Chart.js funcionar sem "crescer infinito" */
    .chart-wrap { position: relative; }
    .chart-wrap > canvas { display:block; width:100% !important; height:100% !important; }
  </style>
</head>
<body>
  <h2>Resultado: <?= htmlspecialchars($poll['question']) ?></h2>
  <p><strong>Assembleia:</strong> <?= htmlspecialchars($poll['assembleia_titulo']) ?> |
     <strong>Item:</strong> <?= htmlspecialchars($poll['item_descricao']) ?></p>

  <p><em>Visibilidade pública:</em> <?= $poll['show_results'] ? 'Liberado' : 'Oculto' ?></p>
  <a href="manage_poll.php?item_id=<?= $poll['item_id'] ?>">← Voltar</a>
  &nbsp;|&nbsp;
  <button onclick="exportPDF()">📄 Exportar PDF</button>
  <br><br>

  <div id="results">
    <?php if ($total == 0): ?>
      <p>Ainda não há votos válidos.</p>
    <?php else: ?>
      <div class="poll-card">
        <strong>Total de votos válidos: <?= $total ?></strong>
        <div class="chart-wrap" style="height: <?= max(140, count($labels)*28) ?>px;">
          <canvas id="pollChart"></canvas>
        </div>
        <br>
      </div>
    <?php endif; ?>
  </div>

  <!-- Libs -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>

  <script>
    // Paleta (Primary, Secondary, Gray)
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

    // Dados do PHP
    const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE) ?>;
    const data   = <?= json_encode($data) ?>;

    // Cria o gráfico somente se houver dados
    if (labels.length) {
      new Chart(document.getElementById('pollChart'), {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            data,
            backgroundColor: palette(labels.length),
            borderColor: palette(labels.length),
            borderWidth: 1,
            barThickness: 22,
            maxBarThickness: 28
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          resizeDelay: 200,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: { label: (ctx) => `${ctx.parsed.x} voto(s)` }
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
    }

    async function exportPDF(){
      const { jsPDF } = window.jspdf;
      const pdf = new jsPDF('p','pt','a4');

      const results = document.getElementById('results');
      const canvas = await html2canvas(results, {scale:2, useCORS:true});
      const imgData = canvas.toDataURL('image/png');
      const pageWidth = pdf.internal.pageSize.getWidth();
      const pageHeight = pdf.internal.pageSize.getHeight();

      const imgWidth = pageWidth - 40;
      const imgHeight = canvas.height * (imgWidth / canvas.width);

      let position = 20;
      pdf.addImage(imgData, 'PNG', 20, position, imgWidth, imgHeight, undefined, 'FAST');

      // quebra automática se ultrapassar
      let heightLeft = imgHeight - (pageHeight - 40);
      while (heightLeft > 0){
        pdf.addPage();
        position = 20 - (imgHeight - heightLeft);
        pdf.addImage(imgData, 'PNG', 20, position, imgWidth, imgHeight, undefined, 'FAST');
        heightLeft -= (pageHeight - 40);
      }

      pdf.save('resultado-enquete-<?= (int)$poll_id ?>.pdf');
    }

  </script>

  <table border="1" cellpadding="6"
    <tr>
      <th>Opção</th>
      <th>Votos</th>
      <th>%</th>
    </tr>
    <?php foreach ($options as $o):
          $pct = $total > 0 ? round(($o['votos'] * 100) / $total, 2) : 0; ?>
      <tr>
        <td><?= htmlspecialchars($o['option_text']) ?></td>
        <td><?= (int)$o['votos'] ?></td>
        <td><?= $pct ?>%</td>
      </tr>
    <?php endforeach; ?>
  </table>
</body>
</html>
