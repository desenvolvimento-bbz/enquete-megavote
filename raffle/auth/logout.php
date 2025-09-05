<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Logout do sistema
 */

session_start();
require_once '../config.php';

// Log da ação se usuário estiver logado
if (isset($_SESSION['usuario'])) {
    logAction('Logout realizado', "Usuário: {$_SESSION['usuario']}");
}

// Limpa todas as variáveis de sessão
$_SESSION = array();

// Destrói o cookie de sessão se existir
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destrói a sessão
session_destroy();

// Redireciona para a página de login
header('Location: ../index.php?logout=1');
exit;
?>

