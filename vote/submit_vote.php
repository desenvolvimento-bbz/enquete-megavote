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

$user_id = $_SESSION['user_id'];
$poll_id = $_POST['poll_id']  ?? null;
$item_id = $_GET['item_id']   ?? null;

// Detecta voto único ou múltiplo
$option_ids = $_POST['option_ids'] ?? null; // checkbox
$option_id  = $_POST['option_id']  ?? null; // radio

if (!$poll_id || !$item_id || (!$option_id && !$option_ids)) {
    die("Requisição inválida.");
}

// Se a sessão veio de link público, garanta que o item pertence à assembleia escopada
if (!empty($_SESSION['guest_scope_assembleia_id'])) {
    $chk = $pdo->prepare("SELECT assembleia_id FROM itens WHERE id = ? LIMIT 1");
    $chk->execute([$item_id]);
    $row = $chk->fetch();
    if (!$row || (int)$row['assembleia_id'] !== (int)$_SESSION['guest_scope_assembleia_id']) {
        header('Location: view_itens.php?assembleia_id='.(int)$_SESSION['guest_scope_assembleia_id']);
        exit;
    }
}

// Verifica que o usuário é participante desta assembleia
// (descobrimos a assembleia partindo do poll -> item -> assembleia)
$stmt = $pdo->prepare("
    SELECT a.id AS assembleia_id, p.is_annulled
    FROM polls pl
    JOIN itens it ON pl.item_id = it.id
    JOIN assembleias a ON it.assembleia_id = a.id
    JOIN participants p ON p.assembleia_id = a.id AND p.user_id = ?
    WHERE pl.id = ?
");
$stmt->execute([$user_id, $poll_id]);
$inscricao = $stmt->fetch();
if (!$inscricao) {
    die("Você não tem acesso a esta assembleia.");
}

// Verifica se o usuário já votou (voto válido) nesta enquete
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM votes v
    JOIN options o ON v.option_id = o.id
    WHERE v.user_id = ? AND o.poll_id = ? AND v.is_annulled = 0
");
$stmt->execute([$user_id, $poll_id]);
if ($stmt->fetchColumn() > 0) {
    die("Você já votou nesta enquete.");
}

// Verifica quantas opções são permitidas
$stmt = $pdo->prepare("SELECT max_choices, show_results FROM polls WHERE id = ?");
$stmt->execute([$poll_id]);
$cfg = $stmt->fetch();
$max_choices = (int)($cfg['max_choices'] ?? 1);

// Se a enquete já liberou resultado, não aceita voto
if ((int)$cfg['show_results'] === 1) {
    die("Esta enquete já está com resultado liberado. Votação encerrada.");
}

// Determina as opções enviadas
$options_to_vote = [];
if ($option_ids && is_array($option_ids)) {
    $options_to_vote = array_filter(array_map('intval', $option_ids));
    if (count($options_to_vote) > $max_choices) {
        die("Você selecionou mais opções do que o permitido.");
    }
} elseif ($option_id) {
    $options_to_vote = [intval($option_id)];
} else {
    die("Nenhuma opção selecionada.");
}

// Verifica se as opções pertencem à enquete
$placeholders = implode(',', array_fill(0, count($options_to_vote), '?'));
$stmt = $pdo->prepare("SELECT id FROM options WHERE poll_id = ? AND id IN ($placeholders)");
$stmt->execute(array_merge([$poll_id], $options_to_vote));
$valid_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
if (count($valid_ids) !== count($options_to_vote)) {
    die("Opção inválida detectada.");
}

// Inserir votos
$is_annulled = !empty($inscricao['is_annulled']) ? 1 : 0;
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

$insert_stmt = $pdo->prepare("
    INSERT INTO votes (user_id, option_id, ip_address, is_annulled, voted_at)
    VALUES (?, ?, ?, ?, NOW())
");

foreach ($options_to_vote as $opt_id) {
    $insert_stmt->execute([$user_id, $opt_id, $ip, $is_annulled]);
}

header("Location: view_polls.php?item_id=$item_id");
exit;
