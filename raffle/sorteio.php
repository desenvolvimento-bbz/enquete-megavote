<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Sorteio por BLOCO (padrão: Apartamento / Bloco / Vaga / Tipo de Vaga)
 */
require_once __DIR__ . '/config.php';
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

// Apenas POST + CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: painel.php'); exit; }
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
  $_SESSION['error'] = 'Token de segurança inválido. Tente novamente.';
  header('Location: painel.php'); exit;
}
if (empty($_SESSION['dados_planilha']) || !is_array($_SESSION['dados_planilha'])) {
  $_SESSION['error'] = 'Nenhum dado de planilha encontrado. Importe a planilha primeiro.';
  header('Location: painel.php'); exit;
}

try {
  $dados = $_SESSION['dados_planilha'];

  // Estruturas por bloco
  $aptosPorBloco = []; // 'A' => ['101', '102', ...]
  $vagasPorBloco = []; // 'A' => [ ['Vaga'=>'S1','Tipo de Vaga'=>'Livre'], ... ]
  foreach ($dados as $row) {
    $bl   = trim((string)($row['Bloco'] ?? ''));
    $apt  = trim((string)($row['Apartamento'] ?? ''));
    $vaga = trim((string)($row['Vaga'] ?? ''));
    $tipo = trim((string)($row['Tipo de Vaga'] ?? ''));

    if ($bl === '') continue;

    if ($apt !== '')  { $aptosPorBloco[$bl][] = $apt; }
    if ($vaga !== '') { $vagasPorBloco[$bl][] = ['Vaga'=>$vaga, 'Tipo de Vaga'=>$tipo]; }
  }
  foreach ($aptosPorBloco as $bl=>$L)   $aptosPorBloco[$bl] = array_values(array_unique($L));
  // não precisa únicos de vaga; pode repetir se existirem linhas duplicadas (mas aceitamos únicos também)
  foreach ($vagasPorBloco as $bl=>$L)   $vagasPorBloco[$bl] = array_values($L);

  $resultado     = [];
  $remanescentes = [];

  // Sorteia bloco a bloco
  $seed = time();
  mt_srand($seed);
  logAction('Seed do sorteio', "Seed: {$seed}");

  foreach ($aptosPorBloco as $bl => $aptos) {
    $vagas = $vagasPorBloco[$bl] ?? [];

    shuffle($aptos);
    shuffle($vagas);

    $n = min(count($aptos), count($vagas));
    for ($i=0; $i<$n; $i++) {
      $resultado[] = [
        'Apartamento' => $aptos[$i],
        'Bloco'       => $bl,
        'Vaga'        => $vagas[$i]['Vaga'],
        'Tipo Vaga'   => $vagas[$i]['Tipo de Vaga'] ?? '',
        'Origem'      => 'Sorteado',
      ];
    }
    if (count($aptos) > $n) {
      $sobras = array_slice($aptos, $n);
      foreach ($sobras as $ap) $remanescentes[] = "{$bl}-{$ap}";
    }
  }

  // Ordena por Bloco + Apartamento (numérico quando possível)
  usort($resultado, function($a,$b){
    $c = strcmp((string)$a['Bloco'], (string)$b['Bloco']);
    if ($c !== 0) return $c;
    $na = (int)$a['Apartamento']; $nb = (int)$b['Apartamento'];
    if ($na>0 && $nb>0) return $na <=> $nb;
    return strcmp((string)$a['Apartamento'], (string)$b['Apartamento']);
  });

  $_SESSION['resultado_sorteio'] = $resultado;
  $_SESSION['remanescentes']     = $remanescentes;
  $_SESSION['sorteio_realizado'] = true;
  $_SESSION['sorteio_timestamp'] = time();
  $_SESSION['sorteio_seed']      = $seed;
  $_SESSION['sorteio_config']    = []; // sem flags agora

  $total = count($resultado);
  $_SESSION['success'] = "Sorteio realizado com sucesso! {$total} vaga(s) atribuída(s), ".count($remanescentes)." apartamento(s) sem vaga no bloco.";

} catch (Throwable $e) {
  logAction('Erro no sorteio', $e->getMessage());
  $_SESSION['error'] = 'Erro ao realizar sorteio: '.$e->getMessage();
}

header('Location: painel.php'); exit;
