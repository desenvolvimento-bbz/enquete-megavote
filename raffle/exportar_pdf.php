<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Exportação de relatório em PDF (padronizado com guard de sessão + logs)
 */

require_once __DIR__ . '/config.php';

// Guarda de sessão (apenas ADMIN)
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

// Verifica se há sorteio realizado
if (empty($_SESSION['resultado_sorteio']) || !is_array($_SESSION['resultado_sorteio'])) {
  $_SESSION['error'] = 'Nenhum sorteio realizado para exportar.';
  header('Location: painel.php');
  exit;
}

// Autoloader do Dompdf (via Composer)
$vendor = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendor)) {
  logAction('Erro PDF', 'Vendor/autoload não encontrado (Dompdf ausente)');
  $_SESSION['error'] = 'Biblioteca Dompdf não encontrada. Contate o administrador.';
  header('Location: painel.php');
  exit;
}
require $vendor;

use Dompdf\Dompdf;
use Dompdf\Options;

try {
  $resultado         = $_SESSION['resultado_sorteio'];
  $remanescentes     = $_SESSION['remanescentes']     ?? [];
  $sorteioTimestamp  = $_SESSION['sorteio_timestamp'] ?? time();
  $sorteioConfig     = $_SESSION['sorteio_config']    ?? [];
  $sorteioSeed       = $_SESSION['sorteio_seed']      ?? 'N/A';

  $dataHora   = date('d/m/Y H:i:s', $sorteioTimestamp);
  $usuario    = $_SESSION['username'] ?? 'Admin';

  // Estatísticas
  $totalVagas         = count($resultado);
  $totalRemanescentes = count($remanescentes);
  $totalFixos         = count(array_filter($resultado, fn($r) => in_array($r['Origem'] ?? '', ['Fixo JSON','Fixo Planilha'], true)));
  $totalSorteados     = $totalVagas - $totalFixos;

  // Configurações aplicadas
  $configTexto = [];
  if (!empty($sorteioConfig['ignorar_pne']))    $configTexto[] = 'Vagas PNE ignoradas';
  if (!empty($sorteioConfig['ignorar_idosos'])) $configTexto[] = 'Vagas Idosos ignoradas';
  if (!empty($sorteioConfig['usar_casadas']))   $configTexto[] = 'Vagas Casadas consideradas';
  $configStr = $configTexto ? implode(', ', $configTexto) : 'Configuração padrão';

  // HTML do relatório
  $html = '<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8">
  <style>
    @page { margin: 2cm; }
    body{ font-family:"DejaVu Sans", Arial, sans-serif; font-size:11px; line-height:1.4; color:#374151; margin:0; padding:0; }
    .header{ text-align:center; margin-bottom:30px; padding-bottom:20px; border-bottom:3px solid #60a33d; }
    .logo{ background:#60a33d; color:#fff; width:60px; height:60px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; font-size:24px; font-weight:bold; margin-bottom:10px; }
    h1{ color:#60a33d; font-size:24px; margin:10px 0 5px; font-weight:bold; }
    h2{ color:#166434; font-size:16px; margin:20px 0 10px; font-weight:bold; border-bottom:1px solid #e5e7eb; padding-bottom:5px; }
    .subtitle{ color:#6b7280; font-size:12px; margin:0; }

    .grid{ display:table; width:100%; margin-bottom:25px; border-collapse:collapse; }
    .row{ display:table-row; }
    .cell{ display:table-cell; padding:8px 12px; border:1px solid #e5e7eb; background:#f9fafb; }
    .label{ font-weight:bold; color:#374151; width:30%; }
    .value{ color:#6b7280; }

    .stats .cell{ text-align:center; padding:15px; background:#f3f4f6; }
    .stats-number{ font-size:20px; font-weight:bold; color:#60a33d; display:block; }
    .stats-label{ font-size:10px; color:#6b7280; text-transform:uppercase; letter-spacing:.5px; }

    table{ width:100%; border-collapse:collapse; margin-bottom:20px; font-size:10px; }
    th{ background:linear-gradient(135deg, #60a33d 0%, #166434 100%); color:#fff; padding:10px 8px; text-align:left; font-weight:bold; font-size:9px; text-transform:uppercase; letter-spacing:.5px; }
    td{ border:1px solid #e5e7eb; padding:8px; }
    tbody tr:nth-child(even){ background:#f9fafb; }
    tbody tr:hover{ background:#f3f4f6; }

    .origem-fixo{ background:#fef3c7 !important; color:#92400e; font-weight:bold; }
    .origem-sorteado{ background:#dcfce7 !important; color:#166434; }

    .remanescentes{ background:#fee2e2; border:1px solid #fecaca; padding:15px; border-radius:5px; margin-top:20px; }
    .remanescentes h3{ color:#dc2626; margin:0 0 10px; font-size:14px; }

    .footer{ margin-top:30px; padding-top:20px; border-top:1px solid #e5e7eb; font-size:9px; color:#6b7280; text-align:center; }
    .audit{ background:#f0f9ff; border:1px solid #bae6fd; padding:10px; border-radius:5px; margin-bottom:20px; font-size:9px; }
  </style>
</head>
<body>

  <div class="header">
    <div class="logo">MV</div>
    <h1>Relatório de Sorteio de Vagas</h1>
    <p class="subtitle">Sistema Eletrônico MegaVote</p>
  </div>

  <div class="grid">
    <div class="row">
      <div class="cell label">Data/Hora do Sorteio:</div>
      <div class="cell value">'.htmlspecialchars($dataHora).'</div>
    </div>
    <div class="row">
      <div class="cell label">Operador:</div>
      <div class="cell value">'.htmlspecialchars($usuario).'</div>
    </div>
    <div class="row">
      <div class="cell label">Configurações:</div>
      <div class="cell value">'.htmlspecialchars($configStr).'</div>
    </div>
  </div>

  <div class="grid stats">
    <div class="row">
      <div class="cell">
        <span class="stats-number">'.$totalVagas.'</span>
        <span class="stats-label">Total de Vagas</span>
      </div>
      <div class="cell">
        <span class="stats-number">'.$totalSorteados.'</span>
        <span class="stats-label">Vagas Sorteadas</span>
      </div>
      <div class="cell">
        <span class="stats-number">'.$totalFixos.'</span>
        <span class="stats-label">Vagas Fixas</span>
      </div>
      <div class="cell">
        <span class="stats-number">'.$totalRemanescentes.'</span>
        <span class="stats-label">Sem Vaga</span>
      </div>
    </div>
  </div>

  <div class="audit">
    <strong>Informações de Auditoria:</strong>
    Seed do sorteio: '.htmlspecialchars((string)$sorteioSeed).' |
    Algoritmo: Mersenne Twister |
    Timestamp: '.$sorteioTimestamp.'
  </div>

  <h2>🎯 Resultado do Sorteio</h2>
  <table>
    <thead>
      <tr>
        <th style="width:15%;">Apartamento</th>
        <th style="width:10%;">Bloco</th>
        <th style="width:15%;">Vaga</th>
        <th style="width:25%;">Tipo de Vaga</th>
        <th style="width:15%;">Origem</th>
      </tr>
    </thead>
    <tbody>';

  foreach ($resultado as $item) {
    $origem = $item['Origem'] ?? 'Sorteado';
    $classe = in_array($origem, ['Fixo JSON','Fixo Planilha'], true) ? 'origem-fixo' : 'origem-sorteado';

    $html .= '<tr class="'.$classe.'">
      <td>'.htmlspecialchars((string)$item['Apartamento']).'</td>
      <td>'.htmlspecialchars((string)$item['Bloco']).'</td>
      <td>'.htmlspecialchars((string)$item['Vaga']).'</td>
      <td>'.htmlspecialchars((string)$item['Tipo Vaga']).'</td>
      <td>'.htmlspecialchars((string)$origem).'</td>
    </tr>';
  }

  $html .= '</tbody></table>';

  if (!empty($remanescentes)) {
    $html .= '<div class="remanescentes">
      <h3>⚠️ Apartamentos sem vaga alocada</h3>
      <p>'.htmlspecialchars(implode(', ', $remanescentes)).'</p>
    </div>';
  }

  $html .= '
  <div class="footer">
    <p>Relatório gerado automaticamente pelo Sistema MegaVote em '.date('d/m/Y H:i:s').'</p>
    <p>Para auditoria, consulte os logs do sistema pelo seed: '.htmlspecialchars((string)$sorteioSeed).'</p>
  </div>
</body>
</html>';

  // Dompdf
  $options = new Options();
  $options->set('defaultFont', 'DejaVu Sans');
  $options->set('isHtml5ParserEnabled', true);
  $options->set('isRemoteEnabled', false);

  $dompdf = new Dompdf($options);
  $dompdf->loadHtml($html);
  $dompdf->setPaper('A4', 'portrait');
  $dompdf->render();

  $nomeArquivo = 'relatorio_sorteio_' . date('Ymd_His', $sorteioTimestamp) . '.pdf';
  logAction('Relatório PDF gerado', "Arquivo: {$nomeArquivo}, {$totalVagas} vagas");

  // Download
  $dompdf->stream($nomeArquivo, ['Attachment' => true]);
  exit;

} catch (Throwable $e) {
  logAction('Erro na geração de PDF', $e->getMessage());
  $_SESSION['error'] = 'Erro ao gerar relatório PDF: ' . $e->getMessage();
  header('Location: painel.php');
  exit;
}