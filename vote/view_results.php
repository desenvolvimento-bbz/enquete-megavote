<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'basic') {
    header("Location: ../auth/login.php");
    exit;
}
require_once('../config/db.php');

$user_id = $_SESSION['user_id'];
$poll_id = $_GET['poll_id'] ?? null;
if (!$poll_id) { die("Enquete não informada."); }

// Carrega enquete + visibilidade + assembleia
$stmt = $pdo->prepare("
  SELECT p.*, i.descricao AS item_descricao, a.titulo AS assembleia_titulo, a.id AS assembleia_id
  FROM polls p
  JOIN itens i ON p.item_id = i.id
  JOIN assembleias a ON i.assembleia_id = a.id
  WHERE p.id = ?
");
$stmt->execute([(int)$poll_id]);
$poll = $stmt->fetch();
if (!$poll) { die("Enquete inexistente."); }

// Precisa estar inscrito na assembleia
$chk = $pdo->prepare("SELECT COUNT(*) FROM inscritos WHERE user_id = ? AND assembleia_id = ?");
$chk->execute([$user_id, $poll['assembleia_id']]);
if ($chk->fetchColumn() == 0) { die("Você não está inscrito nesta assembleia."); }

// Resultado só aparece se show_results = 1
if (!$poll['show_results']) {
    die("O resultado desta enquete não foi liberado.");
}

// Opções + contagem (só votos não anulados)
$opt = $pdo->prepare("
    SELECT o.id, o.option_text, COUNT(v.id) AS votos
    FROM options o
    LEFT JOIN votes v ON v.option_id = o.id AND v.is_annulled = 0
    WHERE o.poll_id = ?
    GROUP BY o.id, o.option_text
    ORDER BY o.id
");
$opt->execute([$poll_id]);
$options = $opt->fetchAll();

$total = array_sum(array_column($options, 'votos'));
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Resultado</title>
</head>
<body>
  <h2>Resultado: <?= htmlspecialchars($poll['question']) ?></h2>
  <p><strong>Assembleia:</strong> <?= htmlspecialchars($poll['assembleia_titulo']) ?> |
     <strong>Item:</strong> <?= htmlspecialchars($poll['item_descricao']) ?></p>
  <a href="view_polls.php?item_id=<?= $poll['item_id'] ?>">← Voltar</a>
  <br><br>

  <?php if ($total == 0): ?>
    <p>Ainda não há votos válidos.</p>
  <?php else: ?>
    <table border="1" cellpadding="6">
      <tr>
        <th>Opção</th>
        <th>Votos</th>
        <th>%</th>
      </tr>
      <?php foreach ($options as $o):
            $pct = $total > 0 ? round(($o['votos'] * 100) / $total, 2) : 0; ?>
        <tr>
          <td><?= htmlspecialchars($o['option_text']) ?></td>
          <td><?= (int)$o['votos'] ?></td>
          <td><?= $pct ?>%</td>
        </tr>
      <?php endforeach; ?>
      <tr>
        <td><strong>Total</strong></td>
        <td colspan="2"><strong><?= $total ?></strong></td>
      </tr>
    </table>
  <?php endif; ?>
</body>
</html>
