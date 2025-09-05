<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Lógica de sorteio (padronizada com guard de sessão + CSRF)
 */

require_once __DIR__ . '/config.php';
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

// Permite apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: painel.php');
  exit;
}

// CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
  $_SESSION['error'] = 'Token de segurança inválido. Tente novamente.';
  header('Location: painel.php');
  exit;
}

// Planilha importada?
if (empty($_SESSION['dados_planilha']) || !is_array($_SESSION['dados_planilha'])) {
  $_SESSION['error'] = 'Nenhum dado de planilha encontrado. Importe a planilha primeiro.';
  header('Location: painel.php');
  exit;
}

try {
  $dadosPlanilha = $_SESSION['dados_planilha'];

  // Configurações do formulário
  $ignorarPNE    = isset($_POST['ignorar_pne']);
  $ignorarIdosos = isset($_POST['ignorar_idosos']);
  $usarCasadas   = isset($_POST['usar_casadas']); // reservado p/ evolução

  // Log das configs
  $configs = [];
  if ($ignorarPNE)    $configs[] = 'Ignorar PNE';
  if ($ignorarIdosos) $configs[] = 'Ignorar Idosos';
  if ($usarCasadas)   $configs[] = 'Usar Casadas';
  $configsStr = $configs ? implode(', ', $configs) : 'Padrão';
  logAction('Sorteio iniciado', "Configurações: {$configsStr}");

  // Carrega fixos persistidos
  $fixosPath        = DATA_PATH . '/fixos.json';
  $fixosPersistidos = file_exists($fixosPath) ? json_decode(file_get_contents($fixosPath), true) : [];
  if (!is_array($fixosPersistidos)) $fixosPersistidos = [];

  // Estruturas de trabalho
  $apartamentos     = [];
  $vagasDisponiveis = [];
  $resultado        = [];
  $remanescentes    = [];
  $paresFixados     = []; // ["Bloco|Subsolo" => true]

  // ETAPA 1: aplica fixos do JSON
  foreach ($fixosPersistidos as $fx) {
    $bl = trim($fx['Bloco'] ?? '');
    $ss = trim($fx['Subsolo'] ?? '');
    $ap = trim($fx['Apartamento'] ?? '');
    $tp = trim($fx['Tipo Vaga'] ?? '');

    if ($bl !== '' && $ss !== '' && $ap !== '') {
      $resultado[] = [
        'Apartamento' => $ap,
        'Bloco'       => $bl,
        'Vaga'        => $ss,
        'Tipo Vaga'   => $tp,
        'Origem'      => 'Fixo JSON',
      ];
      $paresFixados["{$bl}|{$ss}"] = true;
    }
  }

  // ETAPA 2: varre planilha – coleta aptos e separa vagas
  foreach ($dadosPlanilha as $linha) {
    $aptoPlan = trim($linha['Apartamento'] ?? '');
    if ($aptoPlan !== '') {
      $apartamentos[] = $aptoPlan;
    }

    $bloco         = trim($linha['Bloco'] ?? '');
    $subsolo       = trim($linha['Subsolo'] ?? '');
    $tipo          = trim($linha['Tipo Vaga'] ?? '');
    $fixoPlanilha  = trim($linha['Apartamento Fixado'] ?? '');

    // Se vaga já fixada (JSON), pula
    if ($bloco !== '' && $subsolo !== '' && isset($paresFixados["{$bloco}|{$subsolo}"])) {
      continue;
    }

    // Fixos da própria planilha
    if ($fixoPlanilha !== '') {
      $resultado[] = [
        'Apartamento' => $fixoPlanilha,
        'Bloco'       => $bloco,
        'Vaga'        => $subsolo,
        'Tipo Vaga'   => $tipo,
        'Origem'      => 'Fixo Planilha',
      ];
      $paresFixados["{$bloco}|{$subsolo}"] = true;
      continue;
    }

    // Filtros (apenas para vagas não fixas)
    $tipoLower = mb_strtolower($tipo, 'UTF-8');
    if ($ignorarPNE && strpos($tipoLower, 'pne') !== false)    continue;
    if ($ignorarIdosos && strpos($tipoLower, 'idoso') !== false) continue;

    // Adiciona ao pool
    if ($bloco !== '' && $subsolo !== '') {
      $vagasDisponiveis[] = [
        'Bloco'     => $bloco,
        'Subsolo'   => $subsolo,
        'Tipo Vaga' => $tipo,
      ];
    }
  }

  // ETAPA 3: aptos únicos e remove os já atendidos por fixos
  $apartamentos      = array_values(array_unique($apartamentos));
  $aptosJaAtendidos  = array_map(fn($r) => $r['Apartamento'], $resultado);
  $aptosParaSortear  = array_values(array_diff($apartamentos, $aptosJaAtendidos));

  // ETAPA 4: sorteio
  $seed = time();
  mt_srand($seed);
  shuffle($vagasDisponiveis);
  shuffle($aptosParaSortear);
  logAction('Seed do sorteio', "Seed: {$seed}");

  $countAptos = count($aptosParaSortear);
  for ($i = 0; $i < $countAptos; $i++) {
    if (isset($vagasDisponiveis[$i])) {
      $resultado[] = [
        'Apartamento' => $aptosParaSortear[$i],
        'Bloco'       => $vagasDisponiveis[$i]['Bloco'],
        'Vaga'        => $vagasDisponiveis[$i]['Subsolo'],
        'Tipo Vaga'   => $vagasDisponiveis[$i]['Tipo Vaga'],
        'Origem'      => 'Sorteado',
      ];
    } else {
      $remanescentes[] = $aptosParaSortear[$i];
    }
  }

  // ETAPA 5: ordena por Bloco e Apartamento
  usort($resultado, function ($a, $b) {
    $cmp = strcmp((string)$a['Bloco'], (string)$b['Bloco']);
    if ($cmp !== 0) return $cmp;

    // tenta ordenar aptos numericamente quando possível
    $aptoA = (int)$a['Apartamento'];
    $aptoB = (int)$b['Apartamento'];
    if ($aptoA > 0 && $aptoB > 0) {
      return $aptoA <=> $aptoB;
    }
    return strcmp((string)$a['Apartamento'], (string)$b['Apartamento']);
  });

  // ETAPA 6: persiste em sessão
  $_SESSION['resultado_sorteio'] = $resultado;
  $_SESSION['remanescentes']     = $remanescentes;
  $_SESSION['sorteio_realizado'] = true;
  $_SESSION['sorteio_timestamp'] = time();
  $_SESSION['sorteio_seed']      = $seed;
  $_SESSION['sorteio_config']    = [
    'ignorar_pne'    => $ignorarPNE,
    'ignorar_idosos' => $ignorarIdosos,
    'usar_casadas'   => $usarCasadas,
  ];

  // Estatísticas + log
  $totalVagas         = count($resultado);
  $totalRemanescentes = count($remanescentes);
  $totalFixos         = count(array_filter($resultado, fn($r) => in_array(($r['Origem'] ?? ''), ['Fixo JSON','Fixo Planilha'], true)));
  $totalSorteados     = $totalVagas - $totalFixos;

  logAction(
    'Sorteio concluído',
    "Total: {$totalVagas}, Sorteadas: {$totalSorteados}, Fixas: {$totalFixos}, Remanescentes: {$totalRemanescentes}"
  );

  $_SESSION['success'] =
    "Sorteio realizado com sucesso! {$totalVagas} vagas distribuídas, ".
    "{$totalRemanescentes} apartamentos sem vaga.";
} catch (Throwable $e) {
  logAction('Erro no sorteio', $e->getMessage());
  $_SESSION['error'] = 'Erro ao realizar sorteio: ' . $e->getMessage();
}

// Volta pro painel
header('Location: painel.php');
exit;