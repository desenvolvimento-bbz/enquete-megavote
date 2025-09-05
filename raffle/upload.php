<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Upload de planilhas padronizado/com guarda (compatível PHP 8.1+: null-safe)
 */

require_once __DIR__ . '/config.php';                 // ini_set/cookies ANTES da sessão
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';// abre sessão
enforceSessionGuard('admin', $loginPath);

// Só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: painel.php');
    exit;
}

// Verifica token CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error'] = 'Token de segurança inválido. Tente novamente.';
    header('Location: painel.php');
    exit;
}

// Verifica se um arquivo foi enviado
if (!isset($_FILES['planilha']) || $_FILES['planilha']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE   => 'Arquivo muito grande (limite do servidor)',
        UPLOAD_ERR_FORM_SIZE  => 'Arquivo muito grande (limite do formulário)',
        UPLOAD_ERR_PARTIAL    => 'Upload incompleto',
        UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo selecionado',
        UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário não encontrado',
        UPLOAD_ERR_CANT_WRITE => 'Erro ao escrever arquivo',
        UPLOAD_ERR_EXTENSION  => 'Upload bloqueado por extensão'
    ];
    $error   = $_FILES['planilha']['error'] ?? UPLOAD_ERR_NO_FILE;
    $message = $errorMessages[$error] ?? 'Erro desconhecido no upload';

    logAction('Erro no upload', $message);
    $_SESSION['error'] = "Erro no upload: {$message}";
    header('Location: painel.php');
    exit;
}

// Validações de segurança
$arquivo           = $_FILES['planilha'];
$nomeOriginal      = $arquivo['name'] ?? '';
$tamanho           = (int)($arquivo['size'] ?? 0);
$tipoMime          = $arquivo['type'] ?? '';
$arquivoTemporario = $arquivo['tmp_name'] ?? '';

// Extensão
$extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
if (!in_array($extensao, ALLOWED_EXTENSIONS, true)) {
    logAction('Upload rejeitado', "Extensão inválida: {$extensao}");
    $_SESSION['error'] = 'Apenas arquivos .xlsx são permitidos.';
    header('Location: painel.php');
    exit;
}

// Tamanho
if ($tamanho > MAX_FILE_SIZE) {
    $maxSizeMB = (int)(MAX_FILE_SIZE / (1024 * 1024));
    logAction('Upload rejeitado', "Arquivo muito grande: {$tamanho} bytes");
    $_SESSION['error'] = "Arquivo muito grande. Máximo permitido: {$maxSizeMB}MB";
    header('Location: painel.php');
    exit;
}

// MIME permitido (alguns navegadores usam application/octet-stream para .xlsx – se precisar, acrescente)
$tiposPermitidos = [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel',
];
if (!in_array($tipoMime, $tiposPermitidos, true)) {
    // allowlist “quente”: se quiser aceitar octet-stream, descomente a linha abaixo
    // if ($tipoMime !== 'application/octet-stream') { ... }
    logAction('Upload rejeitado', "Tipo MIME inválido: {$tipoMime}");
    $_SESSION['error'] = 'Tipo de arquivo não permitido.';
    header('Location: painel.php');
    exit;
}

// Gera nome único
$nomeArquivo = 'planilha_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.xlsx';
$caminhoFinal = UPLOADS_PATH . '/' . $nomeArquivo;

// Move o arquivo
if (!move_uploaded_file($arquivoTemporario, $caminhoFinal)) {
    logAction('Erro no upload', 'Falha ao mover arquivo');
    $_SESSION['error'] = 'Erro ao salvar arquivo no servidor.';
    header('Location: painel.php');
    exit;
}

// Carrega PhpSpreadsheet
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    logAction('Erro no sistema', 'PhpSpreadsheet não encontrado');
    $_SESSION['error'] = 'Biblioteca PhpSpreadsheet não encontrada. Contate o administrador.';
    @unlink($caminhoFinal);
    header('Location: painel.php');
    exit;
}

require __DIR__ . '/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $spreadsheet = IOFactory::load($caminhoFinal);
    $dados = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

    if (empty($dados) || count($dados) < 2) {
        throw new Exception('Planilha vazia ou sem dados válidos');
    }

    // -------- Cabeçalho (linha 1) null-safe --------
    $primeiraLinha = $dados[1] ?? [];
    // Converte qualquer null para string vazia e aplica trim em string
    $header = array_map(function($v){
        if ($v === null) return '';
        return trim((string)$v);
    }, $primeiraLinha);

    unset($dados[1]); // remove linha do header

    // Remove linhas totalmente vazias
    $dados = array_filter($dados, function($linha){
        // $linha é um array tipo ['A'=>..., 'B'=>..., ...]
        foreach ($linha as $v) {
            if ($v !== null && trim((string)$v) !== '') return true;
        }
        return false;
    });

    // Colunas obrigatórias (nomes exatos do modelo)
    $colunasObrigatorias = ['Bloco', 'Apartamento', 'Subsolo', 'Tipo Vaga', 'Apartamento Fixado'];

    // Verifica presença das colunas obrigatórias
    $faltando = array_values(array_diff($colunasObrigatorias, $header));
    if (!empty($faltando)) {
        throw new Exception('Colunas obrigatórias não encontradas: ' . implode(', ', $faltando));
    }

    // Índices do cabeçalho para acelerar o combine (evita avisos se tamanhos divergirem)
    // Mapeia: nomeColuna => posição (0..n)
    $headerPos = [];
    foreach ($header as $idx => $nomeCol) {
        if ($nomeCol !== '') $headerPos[$nomeCol] = $idx;
    }

    // -------- Converte linhas A/B/C... para chaves nomeadas, null-safe --------
    $dadosConvertidos = [];
    foreach ($dados as $linha) {
        // $linha vem indexado por A,B,C...; usamos os valores na ordem do header
        $vals = array_values($linha);

        // Constrói array nomeado com base nas posições do header
        $vaga = [];
        foreach ($headerPos as $nomeCol => $pos) {
            $valor = $vals[$pos] ?? null;
            $vaga[$nomeCol] = $valor === null ? '' : (string)$valor; // null-safe
        }

        // Campos essenciais
        $apto   = trim($vaga['Apartamento'] ?? '');
        $tipo   = trim($vaga['Tipo Vaga'] ?? '');
        if ($apto === '' || $tipo === '') {
            continue;
        }

        // Sanitiza tudo
        foreach ($vaga as $k => $v) {
            $vaga[$k] = sanitizeInput($v ?? '');
        }

        $dadosConvertidos[] = $vaga;
    }

    if (empty($dadosConvertidos)) {
        throw new Exception('Nenhum dado válido encontrado na planilha');
    }

    // Salva na sessão e limpa estado anterior de sorteio
    $_SESSION['dados_planilha']   = $dadosConvertidos;
    unset($_SESSION['resultado_sorteio'], $_SESSION['remanescentes'], $_SESSION['sorteio_realizado']);

    logAction('Planilha importada', "Arquivo: {$nomeOriginal}, registros: " . count($dadosConvertidos));
    $_SESSION['success'] = "Planilha importada com sucesso! " . count($dadosConvertidos) . " registros carregados.";

} catch (Throwable $e) {
    logAction('Erro na importação', $e->getMessage());
    if (file_exists($caminhoFinal)) { @unlink($caminhoFinal); }
    $_SESSION['error'] = 'Erro ao processar planilha: ' . $e->getMessage();
}

header('Location: painel.php');
exit;
