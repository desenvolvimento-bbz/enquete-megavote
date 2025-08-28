<?php
session_start();
require_once('../config/db.php');

// Timeout + fingerprint
$loginPath = '../auth/login.php';
require_once(__DIR__ . '/../auth/session_timeout.php');

$item_id       = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
$dir           = $_GET['dir'] ?? '';
$assembleia_id = isset($_GET['assembleia_id']) ? (int)$_GET['assembleia_id'] : 0;

if (!$item_id || !$assembleia_id || !in_array($dir, ['up','down'], true)) {
    header("Location: manage_itens.php?assembleia_id={$assembleia_id}");
    exit;
}

// Busca o item e garante que pertence ao admin logado
$sql = "
  SELECT i.id, i.numero, i.assembleia_id
  FROM itens i
  JOIN assembleias a ON a.id = i.assembleia_id
  WHERE i.id = ? AND a.criada_por = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$item_id, $_SESSION['user_id']]);
$atual = $stmt->fetch();

if (!$atual || (int)$atual['assembleia_id'] !== $assembleia_id) {
    header("Location: manage_itens.php?assembleia_id={$assembleia_id}");
    exit;
}

// Calcula alvo (vizinhos)
$targetNumero = ($dir === 'up') ? ($atual['numero'] - 1) : ($atual['numero'] + 1);
if ($targetNumero < 1) {
    header("Location: manage_itens.php?assembleia_id={$assembleia_id}");
    exit;
}

// Busca o vizinho com o número alvo
$viz = $pdo->prepare("SELECT id, numero FROM itens WHERE assembleia_id = ? AND numero = ?");
$viz->execute([$assembleia_id, $targetNumero]);
$alvo = $viz->fetch();

if (!$alvo) {
    // Nada a mover (ou já está no topo/fundo)
    header("Location: manage_itens.php?assembleia_id={$assembleia_id}");
    exit;
}

// Troca com número temporário para não violar o índice único
try {
    $pdo->beginTransaction();

    // Número temporário livre dentro da assembleia
    $mx = $pdo->prepare("SELECT COALESCE(MAX(numero), 0) FROM itens WHERE assembleia_id = ?");
    $mx->execute([$assembleia_id]);
    $tempNumero = ((int)$mx->fetchColumn()) + 1;

    // 1) manda o vizinho para o temporário
    $pdo->prepare("UPDATE itens SET numero = ? WHERE id = ?")->execute([$tempNumero, $alvo['id']]);

    // 2) move o atual para o número antigo do vizinho
    $pdo->prepare("UPDATE itens SET numero = ? WHERE id = ?")->execute([$alvo['numero'], $atual['id']]);

    // 3) move o vizinho para o número antigo do atual
    $pdo->prepare("UPDATE itens SET numero = ? WHERE id = ?")->execute([$atual['numero'], $alvo['id']]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    // Se quiser, logue $e->getMessage()
}

header("Location: manage_itens.php?assembleia_id={$assembleia_id}");
exit;
