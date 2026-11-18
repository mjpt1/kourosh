<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
if (!csrf_ok()) { http_response_code(403); echo json_encode(['ok' => false]); exit; }
$id = (int)($_POST['id'] ?? 0);
$qty = max(0, min(20, (int)($_POST['qty'] ?? 1)));
$mode = $_POST['mode'] ?? 'add';
$s = db()->prepare("SELECT id FROM products WHERE id=? AND active=1"); $s->execute([$id]);
if ($s->fetch()) {
    $cart = cart();
    if ($mode === 'set') $cart[$id] = $qty; else $cart[$id] = min(20, ($cart[$id] ?? 0) + max(1, $qty));
    if ($cart[$id] <= 0) unset($cart[$id]);
    $_SESSION['cart'] = $cart;
}
echo json_encode(['ok' => true, 'count' => cart_count(), 'countFa' => fa(cart_count())]);
