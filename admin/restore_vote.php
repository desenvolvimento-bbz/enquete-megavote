<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}
require_once('../config/db.php');

$vote_id = $_POST['vote_id'] ?? null;
$poll_id = $_POST['poll_id'] ?? null;
$option_id = $_POST['option_id'] ?? null;

if (!$vote_id || !$poll_id || !$option_id) {
    echo "❌ Dados inválidos.";
    exit;
}

$check = $pdo->prepare("
    SELECT p.id
    FROM votes v
    JOIN options o ON v.option_id = o.id
    JOIN polls p ON o.poll_id = p.id
    WHERE v.id = ? AND p.id = ? AND p.created_by = ?
");
$check->execute([$vote_id, $poll_id, $_SESSION['user_id']]);
if (!$check->fetch()) {
    echo "❌ Permissão negada.";
    exit;
}

$stmt = $pdo->prepare("
    UPDATE votes 
    SET is_annulled = 0, annulled_by = NULL, annulled_at = NULL 
    WHERE id = ?
");
$stmt->execute([$vote_id]);

header("Location: votes_poll.php?option_id=$option_id&poll_id=$poll_id");
exit;
