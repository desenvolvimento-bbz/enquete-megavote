<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Sorteio GLOBAL (distribui vagas entre todos os apartamentos, independentemente do bloco)
 * Padrão de planilha: Apartamento / Bloco / Vaga / Tipo de Vaga
 */

require_once __DIR__ . '/config.php';
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

// Apenas POST + CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: painel.php'); exit;
}
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
  $_SESSION['error'] = 'Token de segurança inválido. Tente novamente.';
  header('Location: painel.php'); exit;
}
if (empty($_SESSION['dados_planilha']) || !is_array($_SESSION['dados_planilha'])) {
  $_SESSION['error'] = 'Nenhum dado de planilha encontrado. Importe a planilha primeiro.';
  header('Location: painel.php'); exit;
}

/**
 * Embaralhamento Fisher–Yates usando mt_rand (respeita mt_srand).
 * (shuffle() não usa o gerador MT no PHP 7.x)
 */
function shuffle_mt(array &$arr): void {
  $n = count($arr);
  for ($i = $n - 1; $i > 0; $i--) {
    $j = mt_rand(0, $i);
    if ($j !== $i) {
      $tmp     = $arr[$i];
      $arr[$i] = $arr[$j];
      $arr[$j] = $tmp;
    }
  }
}

try {
  $dados = $_SESSION['dados_planilha'];

  // -----------------------------
  // 1) Extrai pools GLOBAIS
  //    - $aptos: lista de apartamentos com seu bloco (para exibição/auditoria)
  //    - $vagas: lista de vagas disponíveis (sem amarrar a bloco)
  // -----------------------------
  $aptos = []; // [['Apartamento'=>'101','Bloco'=>'A'], ...] (únicos por Apto+Bloco)
  $seenApto = []; // chave "Bloco|Apartamento" p/ evitar duplicatas

  $vagas = []; // [['Vaga'=>'1','Tipo de Vaga'=>'Livre'], ...]

  foreach ($dados as $row) {
    $bl   = trim((string)($row['Bloco'] ?? ''));
    $apt  = trim((string)($row['Apartamento'] ?? ''));
    $vaga = trim((string)($row['Vaga'] ?? ''));
    $tipo = trim((string)($row['Tipo de Vaga'] ?? ''));

    // Coleta apartamento (se informado)
    if ($apt !== '') {
      $key = $bl . '|' . $apt;
      if (!isset($seenApto[$key])) {
        $aptos[] = ['Apartamento' => $apt, 'Bloco' => $bl];
        $seenApto[$key] = true;
      }
    }

    // Coleta vaga (se informada) - GLOBAL, sem restrição por bloco
    if ($vaga !== '') {
      $vagas[] = ['Vaga' => $vaga, 'Tipo de Vaga' => $tipo];
    }
  }

  // Se não houver nada útil, aborta
  if (empty($aptos) || empty($vagas)) {
    $_SESSION['error'] = 'Não há apartamentos ou vagas suficientes para realizar o sorteio.';
    header('Location: painel.php'); exit;
  }

  // -----------------------------
  // 2) Semeia PRNG e embaralha GLOBALMENTE
  // -----------------------------
  $seed = time();
  mt_srand($seed);
  logAction('Seed do sorteio', "Seed: {$seed}");

  shuffle_mt($aptos);
  shuffle_mt($vagas);

  // -----------------------------
  // 3) Pareamento simples na ordem embaralhada
  // -----------------------------
  $n = min(count($aptos), count($vagas));
  $resultado     = [];
  $remanescentes = [];

  for ($i = 0; $i < $n; $i++) {
    $resultado[] = [
      'Apartamento' => $aptos[$i]['Apartamento'],
      'Bloco'       => $aptos[$i]['Bloco'],
      'Vaga'        => $vagas[$i]['Vaga'],
      'Tipo Vaga'   => isset($vagas[$i]['Tipo de Vaga']) ? $vagas[$i]['Tipo de Vaga'] : '',
      'Origem'      => 'Sorteado',
    ];
  }

  // Apartamentos que sobraram sem vaga
  if (count($aptos) > $n) {
    for ($i = $n; $i < count($aptos); $i++) {
      $remanescentes[] = $aptos[$i]['Bloco'] . '-' . $aptos[$i]['Apartamento'];
    }
  }

  // -----------------------------
  // 4) Ordenação apenas para EXIBIÇÃO (não altera o sorteio)
  // -----------------------------
  usort($resultado, function ($a, $b) {
    $c = strcmp((string)$a['Bloco'], (string)$b['Bloco']);
    if ($c !== 0) return $c;
    $na = (int)$a['Apartamento']; $nb = (int)$b['Apartamento'];
    if ($na > 0 && $nb > 0) return $na <=> $nb;
    return strcmp((string)$a['Apartamento'], (string)$b['Apartamento']);
  });

  // -----------------------------
  // 5) Persistência e mensagens
  // -----------------------------
  $_SESSION['resultado_sorteio'] = $resultado;
  $_SESSION['remanescentes']     = $remanescentes;
  $_SESSION['sorteio_realizado'] = true;
  $_SESSION['sorteio_timestamp'] = time();
  $_SESSION['sorteio_seed']      = $seed;
  $_SESSION['sorteio_config']    = []; // sem flags agora

  $total = count($resultado);
  $_SESSION['success'] = 'Sorteio realizado com sucesso! ' . $total . ' vaga(s) atribuída(s), ' . count($remanescentes) . ' apartamento(s) sem vaga.';

} catch (Throwable $e) {
  logAction('Erro no sorteio', $e->getMessage());
  $_SESSION['error'] = 'Erro ao realizar sorteio: ' . $e->getMessage();
}

header('Location: painel.php'); exit;
