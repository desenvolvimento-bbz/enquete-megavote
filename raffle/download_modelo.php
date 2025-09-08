<?php
require_once __DIR__ . '/config.php';

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) { http_response_code(500); echo 'PhpSpreadsheet ausente.'; exit; }
require $autoload;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$ss = new Spreadsheet();
$sh = $ss->getActiveSheet();
$sh->setTitle('Modelo');

// Cabeçalhos (padrão novo)
$headers = ['Apartamento','Bloco','Vaga','Tipo de Vaga'];
$col = 'A';
foreach ($headers as $h) { $sh->setCellValue($col.'1', $h); $col++; }

// Exemplos:
//  - Linha 2: apartamento do Bloco A
//  - Linhas 3-4: vagas disponíveis no Bloco A
$sh->fromArray([
  ['101', 'A', '1', 'Livre'],
  ['102', 'A', '2', 'Livre'],
  ['201',    'B', '3', 'Livre'],
    ['202',    'B', '4', 'Livre'],
], null, 'A2');

$filename = 'modelo_megavote.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($ss);
$writer->save('php://output');
exit;
