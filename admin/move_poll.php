<?php
session_start();
require_once('../config/db.php');

$poll_id = $_GET['poll_id'] ?? null;
$dir = $_GET['dir'] ?? null;
$item_id = $_GET['item_id'] ?? null;

if (!$poll_id || !$dir || !$item_id) {
    die("Parâmetros inválidos.");
}

// Enquete atual
$current = $pdo->prepare("SELECT * FROM polls WHERE id = ?");
$current->execute([$poll_id]);
$atual = $current->fetch();

if (!$atual) {
    die("Enquete não encontrada.");
}

// Vizinho
$op = $dir === 'up' ? '<' : '>';
$order = $dir === 'up' ? 'DESC' : 'ASC';

$vizinho = $pdo->prepare("SELECT * FROM polls WHERE item_id = ? AND ordem $op ? ORDER BY ordem $order LIMIT 1");
$vizinho->execute([$item_id, $atual['ordem']]);
$alvo = $vizinho->fetch();

if ($alvo) {
    // Trocar ordem
    $pdo->prepare("UPDATE polls SET ordem = ? WHERE id = ?")->execute([$alvo['ordem'], $atual['id']]);
    $pdo->prepare("UPDATE polls SET ordem = ? WHERE id = ?")->execute([$atual['ordem'], $alvo['id']]);
}

header("Location: manage_poll.php?item_id=$item_id");
exit;
