<?php
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

$prefix = '../';
$title  = 'Hub';
include __DIR__ . '/../layout/header.php';
?>
<div class="container py-4">
  <h2 class="mb-3">Hub do Administrador</h2>
  <div class="row g-3">
    <div class="col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
          <h5>Enquetes</h5>
          <p class="text-muted">Crie salas, pautas e enquetes com link público.</p>
          <div class="mt-auto">
            <a href="<?= $prefix ?>admin/painel_admin.php" class="btn btn-primary">Ir para Enquetes</a>
          </div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card h-100 shadow-sm">
        <div class="card-body d-flex flex-column">
          <h5>Sorteio de Vagas Simples</h5>
          <p class="text-muted">Impor­te planilha e realize sorteios simples com relatórios.</p>
          <div class="mt-auto">
            <a href="<?= $prefix ?>raffle/painel.php" class="btn btn-primary">Ir para Sorteio</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../layout/footer.php'; ?>