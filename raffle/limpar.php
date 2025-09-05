<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Limpeza de dados do sorteio modernizada
 */

session_start();
require_once 'config.php';

// Sistema sem autenticação - acesso direto

// Coleta informações antes da limpeza para log
$dadosAntes = [
    'dados_planilha' => isset($_SESSION['dados_planilha']) ? count($_SESSION['dados_planilha']) : 0,
    'resultado_sorteio' => isset($_SESSION['resultado_sorteio']) ? count($_SESSION['resultado_sorteio']) : 0,
    'remanescentes' => isset($_SESSION['remanescentes']) ? count($_SESSION['remanescentes']) : 0,
    'sorteio_realizado' => isset($_SESSION['sorteio_realizado']) ? 'Sim' : 'Não'
];

// Remove variáveis da sessão relacionadas ao sorteio
$variaveisRemovidas = [];

if (isset($_SESSION['dados_planilha'])) {
    unset($_SESSION['dados_planilha']);
    $variaveisRemovidas[] = 'dados_planilha';
}

if (isset($_SESSION['resultado_sorteio'])) {
    unset($_SESSION['resultado_sorteio']);
    $variaveisRemovidas[] = 'resultado_sorteio';
}

if (isset($_SESSION['remanescentes'])) {
    unset($_SESSION['remanescentes']);
    $variaveisRemovidas[] = 'remanescentes';
}

if (isset($_SESSION['sorteio_realizado'])) {
    unset($_SESSION['sorteio_realizado']);
    $variaveisRemovidas[] = 'sorteio_realizado';
}

if (isset($_SESSION['sorteio_timestamp'])) {
    unset($_SESSION['sorteio_timestamp']);
    $variaveisRemovidas[] = 'sorteio_timestamp';
}

if (isset($_SESSION['sorteio_seed'])) {
    unset($_SESSION['sorteio_seed']);
    $variaveisRemovidas[] = 'sorteio_seed';
}

if (isset($_SESSION['sorteio_config'])) {
    unset($_SESSION['sorteio_config']);
    $variaveisRemovidas[] = 'sorteio_config';
}

// Remove mensagens de erro/sucesso antigas
unset($_SESSION['error']);
unset($_SESSION['success']);

// Log da ação
$detalhes = [
    'Dados antes:' => json_encode($dadosAntes),
    'Variáveis removidas:' => implode(', ', $variaveisRemovidas)
];

logAction('Sorteio limpo', implode(' | ', array_map(fn($k, $v) => "{$k} {$v}", array_keys($detalhes), $detalhes)));

// Opcional: Limpar arquivos de upload antigos (manter apenas os últimos 10)
try {
    $uploadsPath = UPLOADS_PATH;
    if (is_dir($uploadsPath)) {
        $arquivos = glob($uploadsPath . '/planilha_*.xlsx');
        
        if (count($arquivos) > 10) {
            // Ordena por data de modificação (mais antigos primeiro)
            usort($arquivos, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            // Remove arquivos mais antigos, mantendo apenas os 10 mais recentes
            $arquivosParaRemover = array_slice($arquivos, 0, count($arquivos) - 10);
            $removidos = 0;
            
            foreach ($arquivosParaRemover as $arquivo) {
                if (unlink($arquivo)) {
                    $removidos++;
                }
            }
            
            if ($removidos > 0) {
                logAction('Limpeza de arquivos', "Removidos {$removidos} arquivos antigos");
            }
        }
    }
} catch (Exception $e) {
    // Log do erro mas não interrompe o processo
    logAction('Erro na limpeza de arquivos', $e->getMessage());
}

// Mensagem de sucesso
$_SESSION['success'] = 'Sorteio limpo com sucesso! Todos os dados foram removidos.';

// Redireciona para o painel
header('Location: painel.php');
exit;
?>

