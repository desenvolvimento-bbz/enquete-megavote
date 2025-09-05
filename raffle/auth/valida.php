<?php
/**
 * MEGAVOTE - SISTEMA DE SORTEIO DE VAGAS
 * Validação de login
 */

session_start();
require_once '../config.php';

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

// Verifica token CSRF
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    $_SESSION['error'] = 'Token de segurança inválido. Tente novamente.';
    header('Location: ../index.php');
    exit;
}

// Sanitiza e valida entrada
$email = sanitizeInput($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

// Validações básicas
if (empty($email) || empty($senha)) {
    $_SESSION['error'] = 'Email e senha são obrigatórios.';
    header('Location: ../index.php');
    exit;
}

if (!isValidEmail($email)) {
    $_SESSION['error'] = 'Email inválido.';
    header('Location: ../index.php');
    exit;
}

// Verifica credenciais
$users = AUTH_USERS;
$loginSuccess = false;
$userData = null;

if (isset($users[$email])) {
    $userData = $users[$email];
    if (password_verify($senha, $userData['password'])) {
        $loginSuccess = true;
    }
}

if ($loginSuccess) {
    // Regenera ID da sessão por segurança
    session_regenerate_id(true);
    
    // Define variáveis de sessão
    $_SESSION['logado'] = true;
    $_SESSION['usuario'] = $userData['name'];
    $_SESSION['email'] = $email;
    $_SESSION['role'] = $userData['role'];
    $_SESSION['login_time'] = time();
    
    // Log da ação
    logAction('Login realizado', "Email: {$email}");
    
    // Remove mensagens de erro
    unset($_SESSION['error']);
    
    // Redireciona para o painel
    header('Location: ../painel.php');
    exit;
} else {
    // Log da tentativa de login falhada
    logAction('Tentativa de login falhada', "Email: {$email}");
    
    // Adiciona um pequeno delay para dificultar ataques de força bruta
    sleep(1);
    
    $_SESSION['error'] = 'Email ou senha incorretos.';
    header('Location: ../index.php');
    exit;
}
?>

