<?php
session_start();

// Guarda de sessão (admin) + timeout/fingerprint
$loginPath = '../auth/login.php';
require_once(__DIR__ . '/../auth/session_timeout.php');
enforceSessionGuard('admin', $loginPath);

require_once('../config/db.php');

$poll_id = isset($_GET['poll_id']) ? (int)$_GET['poll_id'] : 0;
if ($poll_id <= 0) {
    $prefix = '../'; $title = 'Votos';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Enquete não informada.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Dados da enquete + checagem de propriedade
$stmt = $pdo->prepare("
    SELECT p.*,
           i.numero AS item_numero,
           i.descricao AS item_descricao,
           a.titulo  AS assembleia_titulo,
           a.id      AS assembleia_id
    FROM polls p
    JOIN itens i        ON p.item_id = i.id
    JOIN assembleias a  ON i.assembleia_id = a.id
    WHERE p.id = ? AND a.criada_por = ?
");
$stmt->execute([$poll_id, $_SESSION['user_id']]);
$poll = $stmt->fetch();
if (!$poll) {
    $prefix = '../'; $title = 'Votos';
    include __DIR__ . '/../layout/header.php';
    echo '<div class="alert alert-danger">Permissão negada ou enquete inexistente.</div>';
    echo '<a href="painel_admin.php" class="btn btn-outline-secondary">← Voltar</a>';
    include __DIR__ . '/../layout/footer.php';
    exit;
}

// Ações em votos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_vote'])) {
        $stmt = $pdo->prepare("DELETE FROM votes WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_vote']]);
    }
    if (isset($_POST['annul_vote'])) {
        $stmt = $pdo->prepare("UPDATE votes SET is_annulled = 1, annulled_by = ?, annulled_at = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['username'], (int)$_POST['annul_vote']]);
    }
    if (isset($_POST['restore_vote'])) {
        $stmt = $pdo->prepare("UPDATE votes SET is_annulled = 0, annulled_by = NULL, annulled_at = NULL WHERE id = ?");
        $stmt->execute([(int)$_POST['restore_vote']]);
    }

    header("Location: votes_poll.php?poll_id=" . $poll_id);
    exit;
}

// Votos + dados do participante (participants)
$stmt = $pdo->prepare("
    SELECT v.*,
           o.option_text,
           p.full_name, p.email, p.condo_name, p.bloco, p.unidade,
           p.is_annulled AS part_anulada,
           p.annulled_by AS part_annulled_by,
           p.annulled_at AS part_annulled_at
    FROM votes v
    JOIN options o ON v.option_id = o.id
    LEFT JOIN participants p
           ON p.user_id = v.user_id
          AND p.assembleia_id = ?
    WHERE o.poll_id = ?
    ORDER BY v.voted_at DESC
");
$stmt->execute([(int)$poll['assembleia_id'], $poll_id]);
$votos = $stmt->fetchAll();

// Layout
$prefix = '../';
$title  = 'Votos — ' . htmlspecialchars($poll['question']);
include __DIR__ . '/../layout/header.php';
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h2 class="mb-1">Votos — <?= htmlspecialchars($poll['question']) ?></h2>
    <div class="text-muted">
      Sala: <strong><?= htmlspecialchars($poll['assembleia_titulo']) ?></strong>
      <span class="ms-2">Pauta: <strong><?= (int)$poll['item_numero'] ?></strong></span>
      <?php if (!empty($poll['item_descricao'])): ?>
        <span class="ms-2">“<?= htmlspecialchars($poll['item_descricao']) ?>”</span>
      <?php endif; ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a href="manage_poll.php?item_id=<?= (int)$poll['item_id'] ?>" class="btn btn-outline-secondary">← Voltar</a>
    <button type="button" class="btn btn-outline-primary" onclick="location.reload()">
      <i class="bi bi-arrow-clockwise me-1"></i> Atualizar
    </button>
  </div>
</div>

<?php if (empty($votos)): ?>
  <div class="alert alert-info">Nenhum voto registrado ainda.</div>
<?php else: ?>
  <div class="card shadow-sm">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Participante</th>
              <th>E-mail</th>
              <th>Condomínio</th>
              <th>Bloco</th>
              <th>Unidade</th>
              <th>Opção</th>
              <th>IP</th>
              <th>Data/Hora</th>
              <th style="width:220px;">Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($votos as $v): ?>
              <tr>
                <td><?= htmlspecialchars($v['full_name'] ?: '-') ?></td>
                <td><?= htmlspecialchars($v['email'] ?: '-') ?></td>
                <td><?= htmlspecialchars($v['condo_name'] ?: '-') ?></td>
                <td><?= htmlspecialchars($v['bloco'] ?: '-') ?></td>
                <td><?= htmlspecialchars($v['unidade'] ?: '-') ?></td>
                <td><?= htmlspecialchars($v['option_text']) ?></td>
                <td><?= htmlspecialchars($v['ip_address']) ?></td>
                <td>
                  <?= $v['voted_at'] ? date('d/m/Y H:i', strtotime($v['voted_at'])) : '-' ?>
                  <?php if ((int)$v['is_annulled'] === 1): ?>
                    <br><span class="text-danger small">
                      ❌ Voto anulado
                      <?php if (!empty($v['annulled_at']) && !empty($v['annulled_by'])): ?>
                        (<?= date('d/m/Y H:i', strtotime($v['annulled_at'])) ?> por <?= htmlspecialchars($v['annulled_by']) ?>)
                      <?php endif; ?>
                    </span>
                  <?php elseif ((int)$v['part_anulada'] === 1): ?>
                    <br><span class="text-warning small">
                      ⚠ Participação anulada
                      <?php if (!empty($v['part_annulled_at']) && !empty($v['part_annulled_by'])): ?>
                        (<?= date('d/m/Y H:i', strtotime($v['part_annulled_at'])) ?> por <?= htmlspecialchars($v['part_annulled_by']) ?>)
                      <?php endif; ?>
                    </span>
                  <?php endif; ?>
                </td>
                <td class="text-nowrap">
                  <form method="post" class="d-inline">
                    <input type="hidden" name="<?= ((int)$v['is_annulled'] === 1) ? 'restore_vote' : 'annul_vote' ?>" value="<?= (int)$v['id'] ?>">
                    <button type="submit"
                            class="btn btn-sm <?= ((int)$v['is_annulled'] === 1) ? 'btn-outline-success' : 'btn-outline-warning' ?>"
                            onclick="return confirm('Confirmar esta ação no voto?')">
                      <?= ((int)$v['is_annulled'] === 1) ? 'Desanular' : 'Anular voto' ?>
                    </button>
                  </form>

                  <form method="post" class="d-inline">
                    <input type="hidden" name="delete_vote" value="<?= (int)$v['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Excluir este voto?')">
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
