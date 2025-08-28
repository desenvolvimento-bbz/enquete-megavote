<?php
// --- Guarda de sessão (admin) ---
$loginPath = '../auth/login.php';
require_once __DIR__ . '/../auth/session_timeout.php';
enforceSessionGuard('admin', $loginPath);

// --- DB ---
require_once __DIR__ . '/../config/db.php';

// --- Params ---
$assembleia_id = isset($_GET['assembleia_id']) ? (int)$_GET['assembleia_id'] : 0;
if ($assembleia_id <= 0) {
  die('ID de assembleia inválido.');
}

// --- Verifica ownership ---
$stmt = $pdo->prepare('SELECT * FROM assembleias WHERE id = ? AND criada_por = ?');
$stmt->execute([$assembleia_id, $_SESSION['user_id']]);
$assembleia = $stmt->fetch();
if (!$assembleia) {
  die('Permissão negada para esta assembleia.');
}

// --- Itens ---
$item_stmt = $pdo->prepare('SELECT * FROM itens WHERE assembleia_id = ? ORDER BY numero ASC');
$item_stmt->execute([$assembleia_id]);
$itens = $item_stmt->fetchAll();

// --- Layout ---
$prefix = '../';
$title  = 'Itens da Assembleia';
include __DIR__ . '/../layout/header.php';

// --- CSRF (usamos token no form de delete) ---
// Fallback caso alguém ainda não tenha atualizado o header com as helpers:
if (!function_exists('generateCsrf')) {
  function generateCsrf(): string {
    if (empty($_SESSION['_csrf'])) {
      $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
  }
}
$csrf = generateCsrf();
?>

<?php
// URL absoluta para /public/join.php?token=...
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST']; // inclui porta se houver
$appBase = rtrim(dirname($_SERVER['SCRIPT_NAME'], 2), '/'); // sobe de /admin/... para a raiz do app
$publicUrl = $scheme . '://' . $host . $appBase . '/public/join.php?token=' . rawurlencode($assembleia['invite_token'] ?? '');
?>

<h2 class="mb-3">
  Pautas — <?= htmlspecialchars($assembleia['titulo']) ?>
  <small class="text-muted fw-normal">(<?= date('d/m/Y', strtotime($assembleia['data_assembleia'])) ?>)</small>
</h2>

<div class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <h5 class="mb-0">Link público para participação</h5>
      <?php if (!empty($assembleia['public_enabled'])): ?>
        <span class="badge text-bg-success">Ativo</span>
      <?php else: ?>
        <span class="badge text-bg-secondary">Desativado</span>
      <?php endif; ?>
    </div>

    <div class="row g-2 align-items-center mt-2">
      <div class="col-lg-9">
        <input id="publicLink" type="text" class="form-control" 
               value="<?= htmlspecialchars($publicUrl) ?>" readonly>
      </div>
      <div class="col-lg-3 d-grid d-sm-block">
        <button type="button" class="btn btn-outline-secondary" id="btnCopyLink">Copiar</button>
        <a class="btn btn-outline-primary ms-sm-2 mt-2 mt-sm-0" target="_blank"
           href="<?= htmlspecialchars($publicUrl) ?>">Abrir</a>
      </div>
    </div>

    <?php if (empty($assembleia['public_enabled'])): ?>
      <div class="small text-muted mt-2">
        Este link está <strong>desativado</strong> para o público. Ative-o em 
        <a href="edit_assembleia.php?assembleia_id=<?= (int)$assembleia['id'] ?>">Editar Assembleia</a>.
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  (function(){
    const btn = document.getElementById('btnCopyLink');
    const inp = document.getElementById('publicLink');
    if (!btn || !inp) return;

    btn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(inp.value);
        const old = btn.textContent;
        btn.textContent = 'Copiado!';
        setTimeout(()=> btn.textContent = old, 1500);
      } catch(e) {
        inp.select();
        document.execCommand('copy');
      }
    });
  })();
</script>

<div class="d-flex gap-2 mb-3">
  <a class="btn btn-outline-secondary" href="painel_admin.php">← Voltar</a>
  <a class="btn btn-primary" href="create_item.php?assembleia_id=<?= $assembleia_id ?>">
    <i class="bi bi-plus-circle me-1"></i> Nova pauta
  </a>
</div>

<?php if (empty($itens)): ?>
  <div class="alert alert-info">Nenhuma pauta criada ainda.</div>
<?php else: ?>
  <div class="card">
    <div class="card-body p-0">
      <table class="table table-hover table-sm align-middle mb-0">
        <thead>
          <tr>
            <th style="width:130px;">Seq.</th>
            <th>Descrição</th>
            <th style="width:420px;">Ações</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($itens as $i): ?>
          <tr>
            <td>
            <span class="badge text-bg-secondary me-2"><?= (int)$i['numero'] ?></span><!-- N Sequencia -->

            <form method="get" action="move_item.php" class="d-inline">
                <input type="hidden" name="item_id" value="<?= $i['id'] ?>">
                <input type="hidden" name="dir" value="up">
                <input type="hidden" name="assembleia_id" value="<?= $assembleia_id ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary ms-2" title="Mover para cima">↑</button>
            </form>

            <form method="get" action="move_item.php" class="d-inline">
                <input type="hidden" name="item_id" value="<?= $i['id'] ?>">
                <input type="hidden" name="dir" value="down">
                <input type="hidden" name="assembleia_id" value="<?= $assembleia_id ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Mover para baixo">↓</button>
            </form>
            </td>
            <td><?= htmlspecialchars($i['descricao']) ?></td>
            <td class="d-flex flex-wrap gap-2">
              <a class="btn btn-sm btn-outline-primary" href="manage_poll.php?item_id=<?= (int)$i['id'] ?>">
                📋 Enquetes
              </a>
              <a class="btn btn-sm btn-outline-success" href="results_item.php?item_id=<?= (int)$i['id'] ?>">
                📊 Resultados
              </a>
              <a class="btn btn-sm btn-outline-secondary" href="edit_item.php?item_id=<?= (int)$i['id'] ?>">
                ✏️ Editar
              </a>

              <!-- Delete via POST + CSRF -->
                <form method="get" action="delete_item.php" class="d-inline">
                    <input type="hidden" name="item_id" value="<?= $i['id'] ?>">
                    <input type="hidden" name="assembleia_id" value="<?= $assembleia_id ?>">
                    <button type="submit"
                            class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Deseja excluir esta pauta? Isso removerá enquetes, opções e votos vinculados.');">
                    🗑️ Excluir
                    </button>
                </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
