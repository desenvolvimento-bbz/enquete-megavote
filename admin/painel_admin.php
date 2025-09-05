<?php
// --- Guarda de sessão (admin) ---
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

// --- DB ---
require_once __DIR__ . '/../config/db.php';

// CSRF helpers (fallback local)
if (!function_exists('generateCsrf')) {
  function generateCsrf(): string {
    if (empty($_SESSION['_csrf'])) { $_SESSION['_csrf'] = bin2hex(random_bytes(32)); }
    return $_SESSION['_csrf'];
  }
}
$csrf = generateCsrf();

// Buscar assembleias criadas por este admin
$stmt = $pdo->prepare("SELECT * FROM assembleias WHERE criada_por = ? ORDER BY created_at ASC");
$stmt->execute([$_SESSION['user_id']]);
$assembleias = $stmt->fetchAll();

// Descobre o "web root" do app para gerar caminho absoluto /poll-app/...
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';            // ex: /poll-app/admin/painel_admin.php
$webBase    = rtrim(str_replace('\\','/', dirname($scriptPath)), '/'); // /poll-app/admin
$webRoot    = rtrim(dirname($webBase), '/');            // /poll-app

// Layout
$prefix = '../';
$title  = 'Painel Admin';
include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0">Minhas Enquetes</h2>
  <div class="d-flex gap-2">
    <a href="create_assembleia.php" class="btn btn-primary">
      <i class="bi bi-plus-circle me-1"></i> Nova sala
    </a>
        <a href="../hub/index.php" class="btn btn-outline-secondary">
      <i class="bi bi-grid-3x3-gap me-1"></i> Hub
    </a>
    <a href="../auth/logout.php" class="btn btn-outline-secondary"
       onclick="return confirm('Sair do sistema?')">
      Sair
    </a>
  </div>
</div>

<?php if (empty($assembleias)): ?>
  <div class="alert alert-info">Você ainda não criou nenhuma sala.</div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($assembleias as $a): ?>
      <?php
        // conta PARTICIPANTES ativos (não anulados)
        $c = $pdo->prepare("SELECT COUNT(*) FROM participants WHERE assembleia_id = ? AND is_annulled = 0");
        $c->execute([$a['id']]);
        $qtd_participantes = (int)$c->fetchColumn();

        $statusBadge = $a['status'] === 'encerrada' ? 'text-bg-secondary' : 'text-bg-success';
        $statusLabel = $a['status'] === 'encerrada' ? 'Encerrada' : 'Em andamento';

        // Link público absoluto no site (sem host): /poll-app/public/join.php?token=...
        $token           = $a['invite_token'] ?? '';
        $enabled         = (int)($a['public_enabled'] ?? 0) === 1;
        $publicPathAbs   = $webRoot . '/public/join.php?token=' . urlencode($token);
      ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="card h-100 shadow-sm">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start">
              <h5 class="card-title mb-1"><?= htmlspecialchars($a['titulo']) ?></h5>
              <span class="badge <?= $statusBadge ?>"><?= $statusLabel ?></span>
            </div>
            <div class="small text-muted mb-2">ID #<?= (int)$a['id'] ?></div>

            <ul class="list-unstyled mb-3">
              <li class="mb-1">
                <i class="bi bi-calendar2-event me-2"></i>
                <?= $a['data_assembleia'] ? date('d/m/Y', strtotime($a['data_assembleia'])) : '-' ?>
              </li>
              <li class="mb-1">
                <i class="bi bi-people me-2"></i>
                <?= $qtd_participantes ?> participante(s)
              </li>
            </ul>

            <div class="d-flex flex-wrap gap-2 mt-auto">
              <a href="manage_itens.php?assembleia_id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-primary">
                📂 Pautas
              </a>
              <a href="inscritos_assembleia.php?assembleia_id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-success">
                👥 Participantes
              </a>
              <a href="edit_assembleia.php?assembleia_id=<?= (int)$a['id'] ?>" class="btn btn-sm btn-outline-secondary">
                ✏️ Editar
              </a>
              <a href="delete_assembleia.php?assembleia_id=<?= (int)$a['id'] ?>"
                 class="btn btn-sm btn-outline-danger"
                 onclick="return confirm('Deseja realmente excluir esta sala? Esta ação é irreversível.');">
                🗑️ Excluir
              </a>
            </div>

            <form method="post" action="update_status.php" class="mt-3">
              <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
              <input type="hidden" name="assembleia_id" value="<?= (int)$a['id'] ?>">
              <div class="input-group input-group-sm">
                <label class="input-group-text">Status</label>
                <select name="status" class="form-select" onchange="this.form.submit()">
                  <option value="em_andamento" <?= $a['status'] === 'em_andamento' ? 'selected' : '' ?>>Em andamento</option>
                  <option value="encerrada"     <?= $a['status'] === 'encerrada' ? 'selected' : '' ?>>Encerrada</option>
                </select>
              </div>
            </form>

            <div class="mt-2 border-top pt-2">
              <div class="small text-muted mb-1">Link público:</div>

              <?php if ($enabled && !empty($token)): ?>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                  <!-- mostra caminho absoluto no site -->
                  <code class="bg-light px-2 py-1 rounded text-truncate" style="max-width:280px;">
                    <?= htmlspecialchars($publicPathAbs) ?>
                  </code>

                  <!-- botão copiar: junta origin + caminho absoluto -->
                  <button type="button"
                          class="btn btn-sm btn-outline-secondary copy-link-btn"
                          data-abs-path="<?= htmlspecialchars($publicPathAbs) ?>">
                    Copiar link
                  </button>

                  <!-- abrir em nova aba -->
                  <a class="btn btn-sm btn-outline-success" target="_blank" href="<?= htmlspecialchars($publicPathAbs) ?>">
                    Abrir
                  </a>

                  <form method="post" action="toggle_public_link.php" class="d-inline">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="assembleia_id" value="<?= (int)$a['id'] ?>">
                    <input type="hidden" name="enable" value="0">
                    <button type="submit" class="btn btn-sm btn-outline-warning"
                            onclick="return confirm('Deseja desativar o link público desta sala?');">
                      Desativar link
                    </button>
                  </form>

                  <form method="post" action="regenerate_invite.php" class="d-inline">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="assembleia_id" value="<?= (int)$a['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Gerar um novo link invalida o antigo. Continuar?');">
                      Regenerar link
                    </button>
                  </form>
                </div>
              <?php else: ?>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                  <span class="badge text-bg-secondary">Link desativado</span>
                  <form method="post" action="toggle_public_link.php" class="d-inline">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="assembleia_id" value="<?= (int)$a['id'] ?>">
                    <input type="hidden" name="enable" value="1">
                    <button type="submit" class="btn btn-sm btn-outline-success"
                            onclick="return confirm('Ativar link público para esta sala?');">
                      Ativar link
                    </button>
                  </form>
                </div>
              <?php endif; ?>
            </div>

          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
document.querySelectorAll('.copy-link-btn').forEach(btn=>{
  btn.addEventListener('click', ()=>{
    const absPath = btn.getAttribute('data-abs-path') || ''; // ex: /poll-app/public/join.php?token=...
    const origin  = window.location.origin.replace(/\/+$/,''); // ex: http://localhost
    const full    = origin + absPath;                          // http://localhost/poll-app/public/join.php?token=...
    navigator.clipboard.writeText(full).then(()=>{
      btn.textContent = 'Copiado!';
      setTimeout(()=> btn.textContent = 'Copiar link', 1500);
    });
  });
});
</script>

<?php include __DIR__ . '/../layout/footer.php'; ?>
