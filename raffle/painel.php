<?php
require_once __DIR__ . '/config.php';
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

require_once __DIR__ . '/config.php'; // define DATA_PATH e generateCSRFToken()

// CSRF fallback (caso não exista em config.php)
if (!function_exists('generateCSRFToken')) {
  function generateCSRFToken(): string {
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['_csrf'];
  }
}
$csrfToken = generateCSRFToken();

// dados de vagas fixas (em JSON sob DATA_PATH)
$fixosPath = DATA_PATH . '/fixos.json';
$listaFixos = file_exists($fixosPath) ? json_decode(file_get_contents($fixosPath), true) : [];
if (!is_array($listaFixos)) $listaFixos = [];

// estatísticas
$totalDados         = isset($_SESSION['dados_planilha'])     ? count($_SESSION['dados_planilha']) : 0;
$totalResultados    = isset($_SESSION['resultado_sorteio'])  ? count($_SESSION['resultado_sorteio']) : 0;
$totalRemanescentes = isset($_SESSION['remanescentes'])       ? count($_SESSION['remanescentes']) : 0;
$totalFixos         = count($listaFixos);

// header padrão do app
$prefix = '../';
$title  = 'Sorteio de Vagas';
include __DIR__ . '/../layout/header.php';
?>

<?php if (!empty($_SESSION['error'])): ?>
  <div class="megavote-alert megavote-alert-danger megavote-mb-3">
    <?= htmlspecialchars($_SESSION['error']) ?>
  </div>
  <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (!empty($_SESSION['success'])): ?>
  <div class="megavote-alert megavote-alert-success megavote-mb-3">
    <?= htmlspecialchars($_SESSION['success']) ?>
  </div>
  <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h2 class="mb-0">🎲 Sorteio de Vagas</h2>
    <div class="text-muted">Gerencie importação, configurações e resultado do sorteio.</div>
  </div>
  <div class="d-flex gap-2">
    <a href="../hub/index.php" class="btn btn-outline-secondary">
      <i class="bi bi-grid-3x3-gap me-1"></i> Hub
    </a>
    <a href="../auth/logout.php" class="btn btn-outline-secondary"
       onclick="return confirm('Sair do sistema?')">
      Sair
    </a>
  </div>
</div>

<!-- Cards de métricas -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <div class="display-6 mb-1"><?= $totalDados ?></div>
        <div class="text-muted small">Vagas importadas</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <div class="display-6 mb-1 text-success"><?= $totalResultados ?></div>
        <div class="text-muted small">Vagas sorteadas</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <div class="display-6 mb-1 text-info"><?= $totalFixos ?></div>
        <div class="text-muted small">Vagas fixas</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card shadow-sm h-100">
      <div class="card-body text-center">
        <div class="display-6 mb-1 text-warning"><?= $totalRemanescentes ?></div>
        <div class="text-muted small">Sem vaga</div>
      </div>
    </div>
  </div>
</div>

<!-- Ações principais -->
<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap gap-2">
      <a href="modelo.xlsx" class="btn btn-outline-primary">
        <i class="bi bi-download me-1"></i> Baixar modelo
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
        <div class="form-text">Colunas obrigatórias: Bloco, Apartamento, Subsolo, Tipo Vaga, Apartamento Fixado</div>
      </div>

      <button class="btn btn-primary" type="submit">
        <i class="bi bi-upload me-1"></i> Importar
      </button>
    </form>
  </div>
</div>

<!-- Configurações do sorteio -->
<?php if ($totalDados > 0): ?>
<div class="card shadow-sm mb-3">
  <div class="card-header bg-white fw-semibold">
    ⚙️ Configurações do sorteio
  </div>
  <div class="card-body">
    <form action="sorteio.php" method="POST" onsubmit="return confirm('Realizar o sorteio com as opções selecionadas?');">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

      <div class="row g-3">
        <div class="col-12 col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="ignorar_pne" name="ignorar_pne" value="1">
            <label class="form-check-label" for="ignorar_pne">Ignorar vagas PNE</label>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="ignorar_idosos" name="ignorar_idosos" value="1">
            <label class="form-check-label" for="ignorar_idosos">Ignorar vagas Idosos</label>
          </div>
        </div>
        <div class="col-12 col-md-4">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="usar_casadas" name="usar_casadas" value="1" checked>
            <label class="form-check-label" for="usar_casadas">Considerar vagas casadas</label>
          </div>
        </div>
      </div>

      <div class="mt-3">
        <button class="btn btn-success" type="submit">
          <i class="bi bi-shuffle me-1"></i> Realizar sorteio
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!empty($_SESSION['sorteio_realizado'])): ?>
  <div class="alert alert-success">✅ Sorteio realizado com sucesso em <?= date('d/m/Y H:i:s') ?>.</div>
<?php endif; ?>

<!-- Tabelas -->
<div class="row g-3 mb-3">
  <!-- Dados importados -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span>📋 Dados da planilha</span>
        <?php if ($totalDados > 0): ?>
          <span class="badge text-bg-success"><?= $totalDados ?> itens</span>
        <?php endif; ?>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm table-striped align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Bloco</th>
                <th>Apartamento</th>
                <th>Subsolo</th>
                <th>Tipo Vaga</th>
                <th>Apto Fixado</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($totalDados > 0): ?>
                <?php foreach ($_SESSION['dados_planilha'] as $linha): ?>
                  <tr>
                    <td><?= htmlspecialchars($linha['Bloco'] ?? '') ?></td>
                    <td><?= htmlspecialchars($linha['Apartamento'] ?? '') ?></td>
                    <td><?= htmlspecialchars($linha['Subsolo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($linha['Tipo Vaga'] ?? '') ?></td>
                    <td><?= htmlspecialchars($linha['Apartamento Fixado'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="5" class="text-center text-muted">Nenhum dado importado</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Resultado -->
  <div class="col-12 col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span>🎯 Resultado do sorteio</span>
        <?php if ($totalResultados > 0): ?>
          <span class="badge text-bg-success"><?= $totalResultados ?> vagas</span>
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
              <?php if ($totalResultados > 0): ?>
                <?php foreach ($_SESSION['resultado_sorteio'] as $item): ?>
                  <tr>
                    <td><?= htmlspecialchars($item['Apartamento'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['Bloco'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['Vaga'] ?? '') ?></td>
                    <td><?= htmlspecialchars($item['Tipo Vaga'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="text-center text-muted">Sem resultados</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <?php if (!empty($_SESSION['remanescentes'])): ?>
      <div class="card-footer">
        <div class="alert alert-warning mb-0">
          <strong>Apartamentos sem vaga:</strong>
          <?= htmlspecialchars(implode(', ', $_SESSION['remanescentes'])) ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Vagas fixas -->
<div class="card shadow-sm mb-4">
  <div class="card-header bg-white fw-semibold">🔒 Vagas fixas</div>
  <div class="card-body">
    <div class="row g-4">
      <div class="col-12 col-lg-5">
        <h6 class="mb-3">➕ Adicionar</h6>
        <form action="fixar_vaga.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
          <div class="mb-2">
            <label class="form-label">Bloco *</label>
            <input name="bloco" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Subsolo *</label>
            <input name="subsolo" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Apartamento *</label>
            <input name="apartamento" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Tipo de Vaga</label>
            <input name="tipo_vaga" class="form-control" placeholder="Livre, PNE, Casada">
          </div>
          <button class="btn btn-outline-primary" type="submit">
            <i class="bi bi-pin-angle me-1"></i> Fixar vaga
          </button>
        </form>
      </div>
      <div class="col-12 col-lg-7">
        <h6 class="mb-3">📋 Cadastradas</h6>
        <div class="table-responsive" style="max-height:320px;">
          <table class="table table-sm table-striped align-middle">
            <thead class="table-light">
              <tr>
                <th>#</th>
                <th>Bloco</th>
                <th>Subsolo</th>
                <th>Tipo</th>
                <th>Apartamento</th>
                <th>Ações</th>
              </tr>
            </thead>
            <tbody>
              <?php if (count($listaFixos) === 0): ?>
                <tr><td colspan="6" class="text-center text-muted">Nenhuma vaga fixada</td></tr>
              <?php else: ?>
                <?php foreach ($listaFixos as $idx => $f): ?>
                  <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($f['Bloco'] ?? '') ?></td>
                    <td><?= htmlspecialchars($f['Subsolo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($f['Tipo Vaga'] ?? '') ?></td>
                    <td><?= htmlspecialchars($f['Apartamento'] ?? '') ?></td>
                    <td>
                      <a href="fixar_vaga.php?remover=<?= $idx ?>"
                         class="btn btn-sm btn-outline-danger"
                         onclick="return confirm('Remover esta vaga fixa?');">
                        Remover
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../layout/footer.php'; ?>
