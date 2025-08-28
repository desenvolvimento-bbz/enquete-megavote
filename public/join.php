<?php
session_start();
require_once('../config/db.php');

$token = $_GET['token'] ?? '';
if (!$token) { http_response_code(400); die('Link inválido.'); }

// Busca a sala pelo token
$stm = $pdo->prepare("
  SELECT id, titulo, public_enabled, status, data_assembleia
  FROM assembleias
  WHERE invite_token = ?
  LIMIT 1
");
$stm->execute([$token]);
$asm = $stm->fetch();

if (!$asm || !$asm['public_enabled']) { http_response_code(403); die('Link desativado.'); }
if ($asm['status'] !== 'em_andamento') { http_response_code(403); die('Esta sala não está em andamento.'); }

// Se já há sessão de participante ESCOPADA para ESTA assembleia, vá direto aos itens
if (
  isset($_SESSION['role'], $_SESSION['guest_scope_assembleia_id']) &&
  $_SESSION['role'] === 'basic' &&
  (int)$_SESSION['guest_scope_assembleia_id'] === (int)$asm['id']
) {
  header('Location: ../vote/view_itens.php?assembleia_id=' . (int)$asm['id']);
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Acesso à enquete · MegaVote</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root{
      --mv-primary:#60a33d;   /* verde principal */
      --mv-dark:#166434;      /* verde escuro */
      --mv-gray:#53554A;      /* cinza */
      --mv-soft:#f3f6f4;      /* fundo suave */
    }
    html,body{height:100%}
    body{
      background: linear-gradient(180deg, #e9f6e6 0%, #ffffff 40%);
      min-height:100vh; display:flex; flex-direction:column;
    }
    main{flex:1; display:flex; align-items:center}
    .brand-bar{ background: var(--mv-primary); color:#fff; }
    .brand-bar a{ color:#fff; text-decoration:none; font-weight:700; letter-spacing:.2px; }
    .join-card{
      max-width: 980px;
      border:1px solid #e5e8eb;
      box-shadow: 0 10px 20px rgba(0,0,0,.06);
      border-radius: 16px; overflow:hidden; background:#fff;
    }
    .join-left{ background:#f7fbf6; border-right:1px solid #edf0ee; }
    .mv-btn{ background: var(--mv-primary); border-color: var(--mv-primary); }
    .mv-btn:hover{ background: var(--mv-dark); border-color: var(--mv-dark); }
    .form-control:focus{ border-color: var(--mv-primary); box-shadow:0 0 0 .25rem rgba(96,163,61,.15); }
    .muted{ color:#7b7f74; }
    footer small{ color:#7a7d76; }
  </style>
</head>
<body>

<!-- barra de marca -->
<div class="brand-bar py-2">
  <div class="container d-flex align-items-center justify-content-between">
    <div class="brand"><a href="../index.php">Megavote Enquetes</a></div>
  </div>
</div>

<main>
  <div class="container py-5">
    <div class="mx-auto join-card row g-0">
      <!-- lado informativo -->
      <div class="col-md-5 join-left p-4 d-flex flex-column justify-content-between">
        <div>
          <div class="d-flex align-items-center gap-2 mb-2">
            <i class="bi bi-people-fill" style="font-size:1.4rem;color:var(--mv-primary)"></i>
            <h5 class="mb-0" style="color:var(--mv-gray)">Participar da enquete</h5>
          </div>
          <p class="mb-3 muted">Sala: <strong><?= htmlspecialchars($asm['titulo']) ?></strong></p>
          <p class="mb-4 muted">Data: <?= date('d/m/Y', strtotime($asm['data_assembleia'])) ?></p>
          <ul class="list-unstyled small muted mb-0">
            <li class="mb-2"><i class="bi bi-check2-circle me-2"></i>Identificação rápida</li>
            <li class="mb-2"><i class="bi bi-lock-fill me-2"></i>Seu acesso vale só para esta sala</li>
            <li class="mb-2"><i class="bi bi-clipboard2-check me-2"></i>Vote nas pautas liberadas</li>
          </ul>
        </div>
        <div class="small text-muted">Dúvidas? <a href="#" class="text-decoration-none" style="color:var(--mv-dark)">Fale conosco</a></div>
      </div>

      <!-- lado do formulário -->
      <div class="col-md-7 p-4 p-md-5">
        <h3 class="fw-bold mb-1" style="color:var(--mv-gray)">Identifique-se</h3>
        <div class="mb-4 muted">Preencha os dados para participar desta sala.</div>

        <form method="post" action="join_save.php" novalidate>
          <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

          <div class="row g-3">
            <div class="col-12">
              <label class="form-label">Nome completo do proprietário</label>
              <input name="full_name" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">E-mail</label>
              <input type="email" name="email" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Condomínio</label>
              <input name="condo_name" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Bloco</label>
              <input name="bloco" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Unidade</label>
              <input name="unidade" class="form-control" required>
            </div>
          </div>

          <div class="d-grid mt-4">
            <button class="btn mv-btn btn-lg" style="color:var(--mv-soft)">Entrar</button>
          </div>
        </form>

      </div>
    </div>
  </div>
</main>

<footer class="border-top bg-white">
  <div class="container py-3 text-center">
    <small>Powered by <strong>Megavote</strong></small>
  </div>
</footer>

</body>
</html>
