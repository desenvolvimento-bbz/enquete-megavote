<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Upload de planilhas (padrão: Apartamento / Bloco / Vaga / Tipo de Vaga)
 */
require_once __DIR__ . '/config.php';
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: painel.php'); exit; }

// CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
  $_SESSION['error'] = 'Token de segurança inválido. Tente novamente.';
  header('Location: painel.php'); exit;
}

// Upload ok?
if (!isset($_FILES['planilha']) || $_FILES['planilha']['error'] !== UPLOAD_ERR_OK) {
  $map = [
    UPLOAD_ERR_INI_SIZE=>'Arquivo muito grande (servidor)',UPLOAD_ERR_FORM_SIZE=>'Arquivo muito grande (formulário)',
    UPLOAD_ERR_PARTIAL=>'Upload incompleto',UPLOAD_ERR_NO_FILE=>'Nenhum arquivo',UPLOAD_ERR_NO_TMP_DIR=>'Sem /tmp',
    UPLOAD_ERR_CANT_WRITE=>'Falha ao escrever',UPLOAD_ERR_EXTENSION=>'Bloqueado por extensão'
  ];
  $err = $_FILES['planilha']['error'] ?? UPLOAD_ERR_NO_FILE;
  $_SESSION['error'] = 'Erro no upload: '.($map[$err] ?? 'desconhecido');
  header('Location: painel.php'); exit;
}

// Validação básica
$nomeOriginal = (string)($_FILES['planilha']['name'] ?? '');
$ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
if (!in_array($ext, ALLOWED_EXTENSIONS, true)) {
  $_SESSION['error'] = 'Apenas arquivos .xlsx são permitidos.';
  header('Location: painel.php'); exit;
}
if (($_FILES['planilha']['size'] ?? 0) > MAX_FILE_SIZE) {
  $_SESSION['error'] = 'Arquivo muito grande.';
  header('Location: painel.php'); exit;
}

// Move para uploads/
$dest = UPLOADS_PATH.'/planilha_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.xlsx';
if (!move_uploaded_file($_FILES['planilha']['tmp_name'], $dest)) {
  $_SESSION['error'] = 'Erro ao salvar arquivo no servidor.';
  header('Location: painel.php'); exit;
}

// PhpSpreadsheet
$autoload = __DIR__.'/vendor/autoload.php';
if (!file_exists($autoload)) { @unlink($dest); $_SESSION['error']='Biblioteca PhpSpreadsheet não encontrada.'; header('Location: painel.php'); exit; }
require $autoload;
use PhpOffice\PhpSpreadsheet\IOFactory;

try {
  $ss = IOFactory::load($dest);
  $rows = $ss->getActiveSheet()->toArray(null, true, true, true);
  if (empty($rows) || count($rows) < 2) throw new Exception('Planilha vazia ou sem dados válidos');

  // Header (linha 1) por letra + aliases
  $h1 = $rows[1] ?? [];
  $norm = function($s){ $s=(string)$s; $s=trim(mb_strtolower($s,'UTF-8')); $s=preg_replace('/\s+/', ' ', $s); return $s; };
  $aliases = [
    'apartamento'   => 'Apartamento',
    'bloco'         => 'Bloco',
    'vaga'          => 'Vaga',       // canônico
    'subsolo'       => 'Vaga',       // alias aceito
    'tipo vaga'     => 'Tipo de Vaga',
    'tipo de vaga'  => 'Tipo de Vaga',
  ];
  $headerByLetter = [];
  foreach ($h1 as $letter => $val) {
    $canon = $aliases[$norm($val)] ?? null;
    if ($canon) $headerByLetter[$letter] = $canon;
  }
  unset($rows[1]);

  // Obrigações: exatamente essas 4
  $required = ['Apartamento','Bloco','Vaga','Tipo de Vaga'];
  $present  = array_values(array_unique(array_values($headerByLetter)));
  $missing  = array_values(array_diff($required, $present));
  if (!empty($missing)) throw new Exception('Colunas obrigatórias não encontradas: '.implode(', ', $missing));

  // Remove linhas totalmente vazias
  $rows = array_filter($rows, function($r){
    foreach ($r as $v) if ($v !== null && trim((string)$v) !== '') return true;
    return false;
  });

  // Converte linhas → chaves canônicas
  $out = [];
  foreach ($rows as $r) {
    $rec = [];
    foreach ($headerByLetter as $L => $canon) {
      $val = $r[$L] ?? '';
      $rec[$canon] = $val === null ? '' : (string)$val;
    }
    // Sanitiza e filtra vazias de fato
    $apto = trim((string)$rec['Apartamento']);
    $bl   = trim((string)$rec['Bloco']);
    $vg   = trim((string)$rec['Vaga']);
    $tv   = trim((string)$rec['Tipo de Vaga']);
    if ($apto==='' && $vg==='') continue; // sem apto e sem vaga = ignora
    $rec['Apartamento'] = sanitizeInput($apto);
    $rec['Bloco']       = sanitizeInput($bl);
    $rec['Vaga']        = sanitizeInput($vg);
    $rec['Tipo de Vaga']= sanitizeInput($tv);
    $out[] = $rec;
  }
  if (empty($out)) throw new Exception('Nenhum dado válido encontrado na planilha');

  $_SESSION['dados_planilha'] = $out;
  unset($_SESSION['resultado_sorteio'], $_SESSION['remanescentes'], $_SESSION['sorteio_realizado']);

  logAction('Planilha importada', "Arquivo: {$nomeOriginal}, registros: ".count($out));
  $_SESSION['success'] = 'Planilha importada com sucesso! '.count($out).' registro(s).';

} catch (Throwable $e) {
  @unlink($dest);
  logAction('Erro na importação', $e->getMessage());
  $_SESSION['error'] = 'Erro ao processar planilha: '.$e->getMessage();
}

header('Location: painel.php'); exit;
