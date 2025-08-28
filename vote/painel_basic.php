<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'basic') {
    header("Location: ../auth/login.php");
    exit;
}

if (!empty($_SESSION['guest_scope_assembleia_id'])) {
    $aid = (int)$_SESSION['guest_scope_assembleia_id'];
    header("Location: view_itens.php?assembleia_id={$aid}");
    exit;
}

require_once('../config/db.php');

$user_id = (int)$_SESSION['user_id'];

// Inscrição do usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inscrever_id'])) {
    $assembleia_id = (int)$_POST['inscrever_id'];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // já inscrito?
    $check = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE assembleia_id = ? AND user_id = ?");
    $check->execute([$assembleia_id, $user_id]);
    if ($check->fetchColumn() == 0) {
        $stmt = $pdo->prepare("
            INSERT INTO inscritos (assembleia_id, user_id, ip_address, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$assembleia_id, $user_id, $ip]);

        // contador (defensivo; existe coluna 'inscritos' na tabela assembleias no seu schema)
        $pdo->prepare("UPDATE assembleias SET inscritos = inscritos + 1 WHERE id = ?")->execute([$assembleia_id]);
    }

    header("Location: painel_basic.php?ok=inscrito");
    exit;
}

// Assembleias em andamento + status do usuário (null=não inscrito, 0=inscrito ativo, 1=inscrito anulado)
$stmt = $pdo->prepare("
    SELECT a.*,
           (
               SELECT i.is_annulled
               FROM inscritos i
               WHERE i.assembleia_id = a.id AND i.user_id = ?
               LIMIT 1
           ) AS inscrito_status
    FROM assembleias a
    WHERE a.status = 'em_andamento'
    ORDER BY a.data_assembleia DESC, a.created_at ASC
");
$stmt->execute([$user_id]);
$assembleias = $stmt->fetchAll();

// Layout
$prefix = '../';
$title  = 'Minhas Assembleias';
include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <h2 class="mb-0">Bem-vindo(a), <?= htmlspecialchars($_SESSION['username']) ?></h2>
  <a href="../auth/logout.php" class="btn btn-outline-secondary">Sair</a>
</div>

<?php if (isset($_GET['ok']) && $_GET['ok'] === 'inscrito'): ?>
  <div class="alert alert-success">Participação confirmada com sucesso!</div>
<?php endif; ?>

<h5 class="text-muted mb-3">Enquetes em andamento</h5>

<?php if (empty($assembleias)): ?>
  <div class="alert alert-info">Nenhuma enquete disponível no momento.</div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($assembleias as $a): 
      // status da inscrição para este usuário
      $status = $a['inscrito_status']; // null | 0 | 1
      $dataFmt = $a['data_assembleia'] ? date('d/m/Y', strtotime($a['data_assembleia'])) : '-';
    ?>
      <div class="col-12 col-md-6 col-lg-4">
        <div class="card shadow-sm h-100">
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start">
              <h5 class="card-title mb-1"><?= htmlspecialchars($a['titulo']) ?></h5>
              <span class="badge text-bg-success">Em andamento</span>
            </div>
            <div class="text-muted mb-3">Data: <?= $dataFmt ?></div>

            <?php if ($status === null): ?>
              <div class="mt-auto">
                <form method="POST" class="d-inline">
                  <input type="hidden" name="inscrever_id" value="<?= (int)$a['id'] ?>">
                  <button type="submit"
                          class="btn btn-primary w-100"
                          onclick="return confirm('Deseja participar dessa enquete?');">
                    📌 Participar
                  </button>
                </form>
                <div class="small text-muted mt-2">
                  * É necessário registrar sua participação para votar.
                </div>
              </div>

            <?php elseif ((int)$status === 1): ?>
              <div class="mb-2">
                <span class="badge text-bg-warning">Inscrição anulada</span>
              </div>
              <div class="small text-muted mb-3">
                Você ainda pode visualizar as pautas.
              </div>
              <a href="view_itens.php?assembleia_id=<?= (int)$a['id'] ?>" class="btn btn-outline-secondary w-100">
                📂 Acessar pautas
              </a>

            <?php else: // status === 0 (inscrito ativo) ?>
              <div class="mb-2">
                <span class="badge text-bg-success">Registrado</span>
              </div>
              <a href="view_itens.php?assembleia_id=<?= (int)$a['id'] ?>" class="btn btn-outline-success w-100">
                📂 Acessar pautas
              </a>
            <?php endif; ?>

          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
