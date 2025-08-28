<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'basic') {
    header("Location: ../auth/login.php");
    exit;
}
require_once('../config/db.php');

// Timeout + fingerprint
$loginPath = '../auth/login.php';
require_once(__DIR__ . '/../auth/session_timeout.php');

$user_id       = (int)$_SESSION['user_id'];
$assembleia_id = isset($_GET['assembleia_id']) ? (int)$_GET['assembleia_id'] : 0;

if ($assembleia_id <= 0) {
  $prefix = '../'; $title = 'Pautas';
  include __DIR__ . '/../layout/header.php';
  echo '<div class="alert alert-danger">Sala não informada.</div>';
  echo '<a href="painel_basic.php" class="btn btn-outline-secondary">← Voltar</a>';
  include __DIR__ . '/../layout/footer.php';
  exit;
}

// Se veio de link público, garanta que está acessando a sala correta
if (!empty($_SESSION['guest_scope_assembleia_id'])) {
  if ((int)$_SESSION['guest_scope_assembleia_id'] !== $assembleia_id) {
    header('Location: view_itens.php?assembleia_id='.(int)$_SESSION['guest_scope_assembleia_id']);
    exit;
  }
}

// Busca info da assembleia + participação (participants)
$stmt = $pdo->prepare("
  SELECT a.titulo, a.data_assembleia, a.status,
         p.id AS participant_id, p.is_annulled, p.full_name, p.condo_name, p.bloco, p.unidade
  FROM assembleias a
  LEFT JOIN participants p
         ON p.assembleia_id = a.id AND p.user_id = ?
  WHERE a.id = ?
  LIMIT 1
");
$stmt->execute([$user_id, $assembleia_id]);
$asm = $stmt->fetch();

if (!$asm) {
  $prefix = '../'; $title = 'Pautas';
  include __DIR__ . '/../layout/header.php';
  echo '<div class="alert alert-danger">Sala não encontrada.</div>';
  echo '<a href="painel_basic.php" class="btn btn-outline-secondary">← Voltar</a>';
  include __DIR__ . '/../layout/footer.php';
  exit;
}

// Se não há participação registrada, barra o acesso
if (empty($asm['participant_id'])) {
  $prefix = '../'; $title = 'Pautas';
  include __DIR__ . '/../layout/header.php';
  echo '<div class="alert alert-warning">Você ainda não está registrado nesta sala. Acesse pelo link público enviado pelo administrador.</div>';
  echo '<a href="painel_basic.php" class="btn btn-outline-secondary">← Voltar</a>';
  include __DIR__ . '/../layout/footer.php';
  exit;
}

$asm_titulo   = $asm['titulo'];
$asm_data     = $asm['data_assembleia'];
$asm_status   = $asm['status'];
$part_anulada = ((int)$asm['is_annulled'] === 1);

// Busca pautas (itens) + quantidade de enquetes disponíveis (ativas ou liberadas)
$stmt = $pdo->prepare("
  SELECT i.id, i.numero, i.descricao,
         (
           SELECT COUNT(*)
           FROM polls p
           WHERE p.item_id = i.id AND (p.is_active = 1 OR p.show_results = 1)
         ) AS qtd_enquetes
  FROM itens i
  WHERE i.assembleia_id = ?
  ORDER BY i.numero ASC
");
$stmt->execute([$assembleia_id]);
$itens = $stmt->fetchAll();

// Layout
$prefix = '../';
$title  = 'Pautas — ' . htmlspecialchars($asm_titulo);
include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h2 class="mb-1">
      Pautas — <?= htmlspecialchars($asm_titulo) ?>
      <small class="text-muted fw-normal">(<?= $asm_data ? date('d/m/Y', strtotime($asm_data)) : '-' ?>)</small>
    </h2>
    <div class="small text-muted">
      Status:
      <span class="badge <?= $asm_status==='em_andamento' ? 'text-bg-success' : 'text-bg-secondary' ?>">
        <?= $asm_status==='em_andamento' ? 'Em andamento' : 'Encerrada' ?>
      </span>
      <?php if ($part_anulada): ?>
        <span class="badge text-bg-warning ms-2">Sua participação está anulada (novos votos ficam anulados)</span>
      <?php endif; ?>
      <?php if (!empty($asm['full_name'])): ?>
        <span class="ms-2">• Você: <strong><?= htmlspecialchars($asm['full_name']) ?></strong></span>
        <?php if (!empty($asm['condo_name']) || !empty($asm['bloco']) || !empty($asm['unidade'])): ?>
          <span class="ms-1">
            (<?= htmlspecialchars(trim($asm['condo_name'].' Bl. '.$asm['bloco'].' Un. '.$asm['unidade'])) ?>)
          </span>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="painel_basic.php" class="btn btn-outline-secondary">← Voltar</a>
    <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
      <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
    </button>
  </div>
</div>

<?php if (empty($itens)): ?>
  <div class="alert alert-info">Esta sala ainda não possui pautas disponíveis.</div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($itens as $item): ?>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card shadow-sm h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="small text-muted">Pauta #<?= (int)$item['numero'] ?></div>
                <h5 class="card-title mb-1"><?= htmlspecialchars($item['descricao']) ?></h5>
              </div>
              <span class="badge <?= ((int)$item['qtd_enquetes'] > 0) ? 'text-bg-success' : 'text-bg-secondary' ?>">
                <?= (int)$item['qtd_enquetes'] ?> votação(ões)
              </span>
            </div>

            <div class="mt-auto pt-3">
              <?php if ((int)$item['qtd_enquetes'] > 0): ?>
                <a class="btn btn-primary w-100"
                   href="view_polls.php?item_id=<?= (int)$item['id'] ?>">
                  Ver enquetes
                </a>
              <?php else: ?>
                <button class="btn btn-secondary w-100" disabled>Nenhuma votação disponível</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
