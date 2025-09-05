<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Gerenciamento de vagas fixas modernizado
 */

session_start();
require_once 'config.php';

// Sistema sem autenticação - acesso direto

$arquivoFixos = DATA_PATH . '/fixos.json';

// Garante que o arquivo exista
if (!file_exists($arquivoFixos)) {
    file_put_contents($arquivoFixos, json_encode([], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Carrega fixos atuais
$fixos = json_decode(file_get_contents($arquivoFixos), true);
if (!is_array($fixos)) $fixos = [];

// REMOVER FIXO (GET)
if (isset($_GET['remover'])) {
    $idx = (int) $_GET['remover'];
    
    if (isset($fixos[$idx])) {
        $fixoRemovido = $fixos[$idx];
        unset($fixos[$idx]);
        $fixos = array_values($fixos); // Reindexar array
        
        // Salva arquivo
        if (file_put_contents($arquivoFixos, json_encode($fixos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            logAction('Vaga fixa removida', "Bloco: {$fixoRemovido['Bloco']}, Subsolo: {$fixoRemovido['Subsolo']}, Apartamento: {$fixoRemovido['Apartamento']}");
            $_SESSION['success'] = 'Vaga fixa removida com sucesso!';
        } else {
            logAction('Erro ao remover vaga fixa', 'Falha ao salvar arquivo');
            $_SESSION['error'] = 'Erro ao remover vaga fixa. Tente novamente.';
        }
    } else {
        $_SESSION['error'] = 'Vaga fixa não encontrada.';
    }
    
    header('Location: painel.php');
    exit;
}

// ADICIONAR FIXO (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifica token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Token de segurança inválido. Tente novamente.';
        header('Location: painel.php');
        exit;
    }
    
    // Sanitiza e valida entrada
    $bloco = sanitizeInput($_POST['bloco'] ?? '');
    $subsolo = sanitizeInput($_POST['subsolo'] ?? '');
    $apartamento = sanitizeInput($_POST['apartamento'] ?? '');
    $tipoVaga = sanitizeInput($_POST['tipo_vaga'] ?? ''); // opcional
    
    // Validações
    $erros = [];
    
    if ($bloco === '') {
        $erros[] = 'Bloco é obrigatório';
    }
    
    if ($subsolo === '') {
        $erros[] = 'Subsolo é obrigatório';
    }
    
    if ($apartamento === '') {
        $erros[] = 'Apartamento é obrigatório';
    }
    
    // Validação de formato (opcional - pode ser customizada)
    if ($bloco !== '' && !preg_match('/^[A-Z0-9]+$/i', $bloco)) {
        $erros[] = 'Bloco deve conter apenas letras e números';
    }
    
    if ($apartamento !== '' && !preg_match('/^[0-9A-Z]+$/i', $apartamento)) {
        $erros[] = 'Apartamento deve conter apenas números e letras';
    }
    
    if (!empty($erros)) {
        $_SESSION['error'] = 'Erro na validação: ' . implode(', ', $erros);
        header('Location: painel.php');
        exit;
    }
    
    // Verifica duplicidades exatas (mesmo bloco/subsolo)
    $duplicado = false;
    foreach ($fixos as $f) {
        if (
            strcasecmp($f['Bloco'], $bloco) === 0 &&
            strcasecmp($f['Subsolo'], $subsolo) === 0
        ) {
            $duplicado = true;
            break;
        }
    }
    
    if ($duplicado) {
        logAction('Tentativa de duplicar vaga fixa', "Bloco: {$bloco}, Subsolo: {$subsolo}");
        $_SESSION['error'] = 'Já existe uma vaga fixa para este Bloco/Subsolo. Remova antes de cadastrar novamente.';
        header('Location: painel.php');
        exit;
    }
    
    // Verifica se o apartamento já possui vaga fixa
    $apartamentoDuplicado = false;
    foreach ($fixos as $f) {
        if (strcasecmp($f['Apartamento'], $apartamento) === 0) {
            $apartamentoDuplicado = true;
            break;
        }
    }
    
    if ($apartamentoDuplicado) {
        logAction('Tentativa de duplicar apartamento fixo', "Apartamento: {$apartamento}");
        $_SESSION['error'] = 'Este apartamento já possui uma vaga fixa. Remova antes de cadastrar novamente.';
        header('Location: painel.php');
        exit;
    }
    
    // Adiciona novo fixo
    $novoFixo = [
        'Bloco' => $bloco,
        'Subsolo' => $subsolo,
        'Tipo Vaga' => $tipoVaga,
        'Apartamento' => $apartamento,
        'Data_Criacao' => date('Y-m-d H:i:s'),
        'Usuario' => 'Sistema MegaVote'
    ];
    
    $fixos[] = $novoFixo;
    
    // Salva arquivo
    if (file_put_contents($arquivoFixos, json_encode($fixos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        logAction('Vaga fixa adicionada', "Bloco: {$bloco}, Subsolo: {$subsolo}, Apartamento: {$apartamento}, Tipo: {$tipoVaga}");
        $_SESSION['success'] = 'Vaga fixada com sucesso!';
    } else {
        logAction('Erro ao adicionar vaga fixa', 'Falha ao salvar arquivo');
        $_SESSION['error'] = 'Erro ao salvar vaga fixa. Tente novamente.';
    }
    
    header('Location: painel.php');
    exit;
}

// Se chegou até aqui, redireciona para o painel
header('Location: painel.php');
exit;
?>

