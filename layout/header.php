<?php
// header.php
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($prefix)) $prefix = '';

// Decide destino do brand conforme papel do usuário
$homeHref = $prefix . 'auth/login.php';
if (isset($_SESSION['role'])) {
  if ($_SESSION['role'] === 'admin') {
    $homeHref = $prefix . 'admin/painel_admin.php';
  } elseif ($_SESSION['role'] === 'basic') {
    $homeHref = $prefix . 'vote/painel_basic.php';
  } else {
    // fallback para outros papéis, se surgirem
    $homeHref = $prefix . 'index.php';
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="UTF-8" />
  <title><?= isset($title) ? htmlspecialchars($title) : 'Poll App' ?></title>

  <!-- Bootstrap CSS + Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">

  <!-- App CSS -->
  <link rel="stylesheet" href="<?= $prefix ?>public/css/app.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand fw-semibold" href="<?= $homeHref ?>">Megavote Enquetes</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topnav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div id="topnav" class="collapse navbar-collapse">
      <ul class="navbar-nav me-auto">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= $prefix ?>admin/painel_admin.php"><i class="bi bi-speedometer2 me-1"></i>Painel Admin</a></li>
        <?php endif; ?>
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'basic'): ?>
          <li class="nav-item"><a class="nav-link" href="<?= $prefix ?>vote/painel_basic.php"><i class="bi bi-ui-checks-grid me-1"></i>Enquetes</a></li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav">
        <?php if (isset($_SESSION['username'])): ?>
          <li class="nav-item">
            <span class="navbar-text me-3"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['username']) ?> (<?= $_SESSION['role'] ?>)</span>
          </li>
          <li class="nav-item"><a class="btn btn-sm btn-light text-primary" href="<?= $prefix ?>auth/logout.php">Sair</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="btn btn-sm btn-light text-primary" href="<?= $prefix ?>auth/login.php">Entrar</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main class="py-4">
  <div class="container">
