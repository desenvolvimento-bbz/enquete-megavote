<?php
// --- Guarda de sessão (admin) ---
require_once __DIR__ . '/config.php';
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

// --- CSRF (fallback caso não exista em config.php) ---
if (!function_exists('generateCSRFToken')) {
  function generateCSRFToken(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
  }
}
$csrfToken = generateCSRFToken();

// --- Estado atual (sessão) ---
$dadosPlanilha = $_SESSION['dados_planilha']    ?? [];
$resultado     = $_SESSION['resultado_sorteio'] ?? [];
$remanescentes = $_SESSION['remanescentes']     ?? [];

// --- Métricas no novo padrão ---
$totalApartamentos = 0;
$totalVagasDisp    = 0;

foreach ($dadosPlanilha as $r) {
  $apt  = trim((string)($r['Apartamento']   ?? ''));
  $vaga = trim((string)($r['Vaga']          ?? ''));
  if ($apt !== '')  $totalApartamentos++;
  if ($vaga !== '') $totalVagasDisp++;
}

$totalSorteadas    = count($resultado);
$totalSemVaga      = count($remanescentes);

// --- Mensagens flash ---
$success = $_SESSION['success'] ?? '';
$error   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// --- Layout/header padrão do app ---
$prefix = '../';
$title  = 'Sorteio de Vagas';
include __DIR__ . '/../layout/header.php';
?>

<?php if ($error): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="mb-0">🎲 Sorteio de Vagas</h2>
    <div class="text-muted">Sorteie vagas por bloco sem misturar blocos entre si.</div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="../hub/index.php" class="btn btn-outline-secondary">
      <i class="bi bi-grid-3x3-gap me-1"></i> Hub
    </a>
    <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
      <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
    </button>
    <a href="../auth/logout.php" class="btn btn-outline-secondary"
       onclick="return confirm('Sair do sistema?')">
      Sair
    </a>
  </div>
</div>

<!-- Cards de métricas (novo padrão) -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <div class="display-6 mb-1"><?= (int)$totalApartamentos ?></div>
        <div class="text-muted small">Apartamentos</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <div class="display-6 mb-1"><?= (int)$totalVagasDisp ?></div>
        <div class="text-muted small">Vagas disponíveis</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <div class="display-6 mb-1 text-success"><?= (int)$totalSorteadas ?></div>
        <div class="text-muted small">Vagas sorteadas</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <div class="display-6 mb-1 text-warning"><?= (int)$totalSemVaga ?></div>
        <div class="text-muted small">Sem vaga</div>
      </div>
    </div>
  </div>
</div>

<!-- Ações principais -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2">
      <a href="download_modelo.php" class="btn btn-outline-primary">
        📥 Baixar Modelo (.xlsx)
      </a>

      <a href="limpar.php" class="btn btn-outline-danger"
         onclick="return confirm('⚠️ Limpar o sorteio? Esta ação não poderá ser desfeita.');">
        <i class="bi bi-trash me-1"></i> Limpar sorteio
      </a>

      <?php if (!empty($_SESSION['sorteio_realizado'])): ?>
        <a href="exportar_xls.php" class="btn btn-outline-success">
          <i class="bi bi-file-earmark-excel me-1"></i> Gerar Excel
        </a>
        <a href="exportar_pdf.php" class="btn btn-outline-danger">
          <i class="bi bi-filetype-pdf me-1"></i> Gerar PDF
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Importar planilha -->
<div class="card shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">
    📤 Importar planilha (.xlsx)
  </div>
  <div class="card-body">
    <form action="upload.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

      <div class="mb-2">
        <input type="file" name="planilha" class="form-control" accept=".xlsx" required>
        <div class="form-text">
          Colunas obrigatórias: <strong>Apartamento, Bloco, Vaga, Tipo de Vaga</strong>.
          (Aceitamos <em>Subsolo</em> como alias de <em>Vaga</em>.)
        </div>
      </div>

      <button class="btn btn-primary" type="submit">
        <i class="bi bi-upload me-1"></i> Importar
      </button>
    </form>
  </div>
</div>

<!-- Executar sorteio (sem painel de configurações) -->
<?php if (!empty($dadosPlanilha)): ?>
<div class="card shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">
    🎲 Executar sorteio
  </div>
  <div class="card-body">
    <form action="sorteio.php" method="POST" onsubmit="return confirm('Confirmar a realização do sorteio?');">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
      <button class="btn btn-success" type="submit">
        <i class="bi bi-shuffle me-1"></i> Realizar sorteio
      </button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($_SESSION['sorteio_realizado'])): ?>
  <div class="alert alert-success">✅ Sorteio realizado com sucesso em <?= date('d/m/Y H:i:s') ?>.</div>
<?php endif; ?>

<!-- Tabelas -->
<div class="row g-3 mb-4">
  <!-- Dados importados -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span>📋 Dados da planilha</span>
        <span class="badge text-bg-success"><?= count($dadosPlanilha) ?> linha(s)</span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Apartamento</th>
                <th>Bloco</th>
                <th>Vaga</th>
                <th>Tipo de Vaga</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($dadosPlanilha)): ?>
                <tr><td colspan="4" class="text-center text-muted">Nenhum dado importado</td></tr>
              <?php else: ?>
                <?php foreach ($dadosPlanilha as $linha): ?>
                  <tr>
                    <td><?= htmlspecialchars($linha['Apartamento']   ?? '') ?></td>
                    <td><?= htmlspecialchars($linha['Bloco']         ?? '') ?></td>
                    <td><?= htmlspecialchars($linha['Vaga']          ?? '') ?></td>
                    <td><?= htmlspecialchars($linha['Tipo de Vaga']  ?? $linha['Tipo Vaga'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Resultado do sorteio -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span>🎯 Resultado do sorteio</span>
        <?php if ($totalSorteadas > 0): ?>
          <span class="badge text-bg-success"><?= (int)$totalSorteadas ?> vaga(s)</span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Apartamento</th>
                <th>Bloco</th>
                <th>Vaga</th>
                <th>Tipo de Vaga</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($resultado)): ?>
                <tr><td colspan="4" class="text-center text-muted">Sem resultados</td></tr>
              <?php else: ?>
                <?php foreach ($resultado as $item): ?>
                  <tr>
                    <td><?= htmlspecialchars($item['Apartamento'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['Bloco']       ?? '') ?></td>
                    <td><?= htmlspecialchars($item['Vaga']        ?? '') ?></td>
                    <td><?= htmlspecialchars($item['Tipo Vaga']   ?? $item['Tipo de Vaga'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if (!empty($remanescentes)): ?>
      <div class="card-footer">
        <div class="alert alert-warning mb-0">
          <strong>Apartamentos sem vaga no respectivo bloco:</strong>
          <div class="mt-1"><?= htmlspecialchars(implode(', ', $remanescentes)) ?></div>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
