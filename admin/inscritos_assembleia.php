<?php
session_start();

// Guarda de sessão (admin) + timeout/fingerprint
$loginPath = '../auth/login.php';
require_once(__DIR__ . '/../auth/session_timeout.php');
enforceSessionGuard('admin', $loginPath);

require_once('../config/db.php');

$assembleia_id = isset($_GET['assembleia_id']) ? (int)$_GET['assembleia_id'] : 0;
if ($assembleia_id <= 0) {
    $prefix = '../'; $title = 'Participantes';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Assembleia não informada.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Verifica se a assembleia pertence a este admin
$stmt = $pdo->prepare("SELECT id, titulo, data_assembleia FROM assembleias WHERE id = ? AND criada_por = ?");
$stmt->execute([$assembleia_id, $_SESSION['user_id']]);
$assembleia = $stmt->fetch();
if (!$assembleia) {
    $prefix = '../'; $title = 'Participantes';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Permissão negada ou assembleia inexistente.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Ações (anular, desanular, excluir)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['participant_id'], $_POST['action'])) {
    $participant_id = (int)$_POST['participant_id'];
    $action         = $_POST['action'];

    // Carrega participant para confirmar assembleia
    $ps = $pdo->prepare("SELECT id, user_id FROM participants WHERE id = ? AND assembleia_id = ?");
    $ps->execute([$participant_id, $assembleia_id]);
    $part = $ps->fetch();
    if ($part) {
        $user_id = (int)$part['user_id'];

        if ($action === 'annul') {
            // Anula participação
            $q = $pdo->prepare("UPDATE participants
                                SET is_annulled = 1, annulled_by = ?, annulled_at = NOW()
                                WHERE id = ?");
            $q->execute([$_SESSION['username'], $participant_id]);

            // Anula votos deste usuário nesta assembleia
            $q = $pdo->prepare("
                UPDATE votes v
                JOIN options o ON v.option_id = o.id
                JOIN polls p   ON o.poll_id   = p.id
                JOIN itens it  ON p.item_id   = it.id
                SET v.is_annulled = 1
                WHERE v.user_id = ? AND it.assembleia_id = ?
            ");
            $q->execute([$user_id, $assembleia_id]);

        } elseif ($action === 'restore') {
            // Restaura participação
            $q = $pdo->prepare("UPDATE participants
                                SET is_annulled = 0, annulled_by = NULL, annulled_at = NULL
                                WHERE id = ?");
            $q->execute([$participant_id]);

            // Restaura votos deste usuário nesta assembleia
            $q = $pdo->prepare("
                UPDATE votes v
                JOIN options o ON v.option_id = o.id
                JOIN polls p   ON o.poll_id   = p.id
                JOIN itens it  ON p.item_id   = it.id
                SET v.is_annulled = 0
                WHERE v.user_id = ? AND it.assembleia_id = ?
            ");
            $q->execute([$user_id, $assembleia_id]);

        } elseif ($action === 'delete') {
            // (opcional) decrementa contador, se existir coluna 'inscritos'
            try {
                $pdo->prepare("UPDATE assembleias SET inscritos = GREATEST(inscritos - 1, 0) WHERE id = ?")
                    ->execute([$assembleia_id]);
            } catch (Exception $e) { /* coluna pode não existir; ignore */ }

            // Apaga todos os votos deste usuário nesta assembleia
            $q = $pdo->prepare("
                DELETE v FROM votes v
                JOIN options o ON v.option_id = o.id
                JOIN polls p   ON o.poll_id   = p.id
                JOIN itens it  ON p.item_id   = it.id
                WHERE v.user_id = ? AND it.assembleia_id = ?
            ");
            $q->execute([$user_id, $assembleia_id]);

            // Remove participante
            $pdo->prepare("DELETE FROM participants WHERE id = ?")->execute([$participant_id]);
        }
    }

    header("Location: inscritos_assembleia.php?assembleia_id=" . $assembleia_id);
    exit;
}

// Lista de participantes
$stmt = $pdo->prepare("
    SELECT p.*,
           u.username
    FROM participants p
    LEFT JOIN users u ON u.id = p.user_id
    WHERE p.assembleia_id = ?
    ORDER BY p.created_at DESC
");
$stmt->execute([$assembleia_id]);
$participants = $stmt->fetchAll();

// Layout
$prefix = '../';
$title  = 'Participantes — ' . htmlspecialchars($assembleia['titulo']);
include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h2 class="mb-1">
      Participantes — <?= htmlspecialchars($assembleia['titulo']) ?>
      <small class="text-muted fw-normal">(<?= $assembleia['data_assembleia'] ? date('d/m/Y', strtotime($assembleia['data_assembleia'])) : '-' ?>)</small>
    </h2>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary" href="painel_admin.php">← Voltar</a>
    <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
      <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
    </button>
  </div>
</div>

<?php if (empty($participants)): ?>
  <div class="alert alert-info">Nenhum participante registrado ainda.</div>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Condomínio</th>
              <th>Bloco</th>
              <th>Unidade</th>
              <th>IP</th>
              <th>Inscrito em</th>
              <th>Status</th>
              <th style="width:240px;">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($participants as $p): ?>
              <tr>
                <td><?= htmlspecialchars($p['full_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['condo_name'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['bloco'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['unidade'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['ip_address'] ?? '') ?></td>
                <td><?= $p['created_at'] ? date('d/m/Y H:i', strtotime($p['created_at'])) : '-' ?></td>
                <td>
                  <?php if ((int)$p['is_annulled'] === 1): ?>
                    <span class="badge text-bg-warning">
                      Anulada<?= !empty($p['annulled_by']) ? ' por ' . htmlspecialchars($p['annulled_by']) : '' ?>
                      <?= !empty($p['annulled_at']) ? ' em ' . date('d/m/Y H:i', strtotime($p['annulled_at'])) : '' ?>
                    </span>
                  <?php else: ?>
                    <span class="badge text-bg-success">Ativa</span>
                  <?php endif; ?>
                </td>
                <td class="text-nowrap">
                  <?php if ((int)$p['is_annulled'] === 1): ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="participant_id" value="<?= (int)$p['id'] ?>">
                      <input type="hidden" name="action" value="restore">
                      <button type="submit" class="btn btn-sm btn-outline-success"
                              onclick="return confirm('Desanular esta participação? Isso também restaurará os votos.')">
                        Desanular
                      </button>
                    </form>
                  <?php else: ?>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="participant_id" value="<?= (int)$p['id'] ?>">
                      <input type="hidden" name="action" value="annul">
                      <button type="submit" class="btn btn-sm btn-outline-warning"
                              onclick="return confirm('Anular esta participação? Isso também anulará os votos do participante nesta assembleia.')">
                        Anular
                      </button>
                    </form>
                  <?php endif; ?>

                  <form method="post" class="d-inline">
                    <input type="hidden" name="participant_id" value="<?= (int)$p['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Excluir esta participação? Todos os votos deste participante nesta assembleia serão removidos.')">
                      Excluir
                    </button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../layout/footer.php'; ?>
