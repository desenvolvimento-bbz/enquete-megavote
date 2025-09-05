<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Exportação de relatório em Excel modernizada
 */

session_start();
require_once 'config.php';

// Sistema sem autenticação - acesso direto

// Verifica se há sorteio realizado
if (!isset($_SESSION['resultado_sorteio']) || empty($_SESSION['resultado_sorteio'])) {
    $_SESSION['error'] = 'Nenhum sorteio realizado para exportar.';
    header('Location: painel.php');
    exit;
}

// Carrega a biblioteca PhpSpreadsheet
if (!file_exists('vendor/autoload.php')) {
    $_SESSION['error'] = 'Biblioteca PhpSpreadsheet não encontrada. Contate o administrador.';
    header('Location: painel.php');
    exit;
}

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

try {
    $resultado = $_SESSION['resultado_sorteio'];
    $remanescentes = $_SESSION['remanescentes'] ?? [];
    $sorteioTimestamp = $_SESSION['sorteio_timestamp'] ?? time();
    $sorteioConfig = $_SESSION['sorteio_config'] ?? [];
    $sorteioSeed = $_SESSION['sorteio_seed'] ?? 'N/A';
    
    $dataHora = date('d/m/Y H:i:s', $sorteioTimestamp);
    $usuario = 'Sistema MegaVote';
    
    // Estatísticas
    $totalVagas = count($resultado);
    $totalRemanescentes = count($remanescentes);
    $totalFixos = count(array_filter($resultado, fn($r) => in_array($r['Origem'] ?? '', ['Fixo JSON', 'Fixo Planilha'])));
    $totalSorteados = $totalVagas - $totalFixos;
    
    // Configurações aplicadas
    $configTexto = [];
    if ($sorteioConfig['ignorar_pne'] ?? false) $configTexto[] = 'Vagas PNE ignoradas';
    if ($sorteioConfig['ignorar_idosos'] ?? false) $configTexto[] = 'Vagas Idosos ignoradas';
    if ($sorteioConfig['usar_casadas'] ?? false) $configTexto[] = 'Vagas Casadas consideradas';
    $configStr = empty($configTexto) ? 'Configuração padrão' : implode(', ', $configTexto);
    
    // Cria nova planilha
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Sorteio de Vagas');
    
    // Cores do tema MegaVote
    $corPrimaria = '60A33D';
    $corSecundaria = '166434';
    $corClara = 'DCFCE7';
    $corCinza = 'F3F4F6';
    
    // SEÇÃO 1: Cabeçalho
    $sheet->mergeCells('A1:F1');
    $sheet->setCellValue('A1', 'MEGAVOTE - RELATÓRIO DE SORTEIO DE VAGAS');
    $sheet->getStyle('A1')->applyFromArray([
        'font' => [
            'bold' => true,
            'size' => 16,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $corPrimaria]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ]);
    $sheet->getRowDimension(1)->setRowHeight(30);
    
    // SEÇÃO 2: Informações do Sorteio
    $linha = 3;
    $sheet->setCellValue("A{$linha}", 'INFORMAÇÕES DO SORTEIO');
    $sheet->getStyle("A{$linha}:F{$linha}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 12],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $corCinza]]
    ]);
    $sheet->mergeCells("A{$linha}:F{$linha}");
    
    $linha++;
    $infos = [
        ['Data/Hora do Sorteio:', $dataHora],
        ['Operador:', $usuario],
        ['Configurações:', $configStr],
        ['Seed de Auditoria:', $sorteioSeed],
        ['Total de Vagas:', $totalVagas],
        ['Vagas Sorteadas:', $totalSorteados],
        ['Vagas Fixas:', $totalFixos],
        ['Apartamentos sem Vaga:', $totalRemanescentes]
    ];
    
    foreach ($infos as $info) {
        $sheet->setCellValue("A{$linha}", $info[0]);
        $sheet->setCellValue("B{$linha}", $info[1]);
        $sheet->getStyle("A{$linha}")->getFont()->setBold(true);
        $linha++;
    }
    
    // SEÇÃO 3: Resultado do Sorteio
    $linha += 2;
    $sheet->setCellValue("A{$linha}", 'RESULTADO DO SORTEIO');
    $sheet->getStyle("A{$linha}:F{$linha}")->applyFromArray([
        'font' => ['bold' => true, 'size' => 12],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $corCinza]]
    ]);
    $sheet->mergeCells("A{$linha}:F{$linha}");
    
    // Cabeçalhos da tabela
    $linha++;
    $cabecalhos = ['Apartamento', 'Bloco', 'Vaga', 'Tipo de Vaga', 'Origem'];
    $colunas = ['A', 'B', 'C', 'D', 'E'];
    
    foreach ($cabecalhos as $i => $cabecalho) {
        $sheet->setCellValue($colunas[$i] . $linha, $cabecalho);
    }
    
    $sheet->getStyle("A{$linha}:E{$linha}")->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => $corPrimaria]
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);
    
    // Dados do sorteio
    $linhaInicial = $linha + 1;
    foreach ($resultado as $item) {
        $linha++;
        $origem = $item['Origem'] ?? 'Sorteado';
        
        $sheet->setCellValue("A{$linha}", $item['Apartamento']);
        $sheet->setCellValue("B{$linha}", $item['Bloco']);
        $sheet->setCellValue("C{$linha}", $item['Vaga']);
        $sheet->setCellValue("D{$linha}", $item['Tipo Vaga']);
        $sheet->setCellValue("E{$linha}", $origem);
        
        // Cor de fundo baseada na origem
        $corFundo = in_array($origem, ['Fixo JSON', 'Fixo Planilha']) ? 'FEF3C7' : $corClara;
        
        $sheet->getStyle("A{$linha}:E{$linha}")->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $corFundo]
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'CCCCCC']
                ]
            ]
        ]);
    }
    
    // SEÇÃO 4: Apartamentos Remanescentes
    if (!empty($remanescentes)) {
        $linha += 3;
        $sheet->setCellValue("A{$linha}", 'APARTAMENTOS SEM VAGA ALOCADA');
        $sheet->getStyle("A{$linha}:F{$linha}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEE2E2']]
        ]);
        $sheet->mergeCells("A{$linha}:F{$linha}");
        
        $linha++;
        $remanescentes_str = implode(', ', $remanescentes);
        $sheet->setCellValue("A{$linha}", $remanescentes_str);
        $sheet->mergeCells("A{$linha}:F{$linha}");
        $sheet->getStyle("A{$linha}")->getAlignment()->setWrapText(true);
    }
    
    // SEÇÃO 5: Rodapé
    $linha += 3;
    $sheet->setCellValue("A{$linha}", 'Relatório gerado automaticamente pelo Sistema MegaVote');
    $sheet->setCellValue("A" . ($linha + 1), 'Data de geração: ' . date('d/m/Y H:i:s'));
    $sheet->setCellValue("A" . ($linha + 2), 'Este documento possui validade legal como comprovante oficial do sorteio');
    
    $sheet->getStyle("A{$linha}:A" . ($linha + 2))->applyFromArray([
        'font' => ['italic' => true, 'size' => 9],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
    ]);
    
    foreach (range($linha, $linha + 2) as $l) {
        $sheet->mergeCells("A{$l}:F{$l}");
    }
    
    // Ajusta largura das colunas
    $larguras = ['A' => 15, 'B' => 10, 'C' => 15, 'D' => 25, 'E' => 15, 'F' => 15];
    foreach ($larguras as $coluna => $largura) {
        $sheet->getColumnDimension($coluna)->setWidth($largura);
    }
    
    // Congela a primeira linha de cabeçalhos da tabela
    $sheet->freezePane("A{$linhaInicial}");
    
    // Adiciona filtros automáticos na tabela de resultados
    $ultimaLinha = $linhaInicial + count($resultado) - 1;
    $sheet->setAutoFilter("A" . ($linhaInicial - 1) . ":E{$ultimaLinha}");
    
    // Nome do arquivo
    $nomeArquivo = 'relatorio_sorteio_' . date('Ymd_His', $sorteioTimestamp) . '.xlsx';
    
    // Log da ação
    logAction('Relatório Excel gerado', "Arquivo: {$nomeArquivo}, {$totalVagas} vagas");
    
    // Configura headers para download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header("Content-Disposition: attachment; filename=\"{$nomeArquivo}\"");
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');
    
    // Gera e envia o arquivo
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (Exception $e) {
    // Log do erro
    logAction('Erro na geração de Excel', $e->getMessage());
    $_SESSION['error'] = 'Erro ao gerar relatório Excel: ' . $e->getMessage();
    header('Location: painel.php');
    exit;
}
?>

