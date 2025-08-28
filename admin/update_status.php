<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

require_once('../config/db.php');

$assembleia_id = $_POST['assembleia_id'] ?? null;
$status = $_POST['status'] ?? null;

// Validação simples
if (!in_array($status, ['em_andamento', 'encerrada'])) {
    die("Status inválido.");
}

// Verifica se a assembleia pertence ao admin logado
$stmt = $pdo->prepare("SELECT * FROM assembleias WHERE id = ? AND criada_por = ?");
$stmt->execute([$assembleia_id, $_SESSION['user_id']]);
$assembleia = $stmt->fetch();

if (!$assembleia) {
    die("Permissão negada.");
}

// Atualiza o status
$update = $pdo->prepare("UPDATE assembleias SET status = ? WHERE id = ?");
$update->execute([$status, $assembleia_id]);

header("Location: painel_admin.php");
exit;
