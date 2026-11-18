<?php
require __DIR__ . '/config.php';
$s = db()->prepare("SELECT * FROM orders WHERE track=?"); $s->execute([$_GET['t'] ?? '']); $o = $s->fetch();
if (!$o) { header('Location: ./'); exit; }

if ($o['status'] === 'pending') {
    $ok = false; $ref = '';
    if (ZARINPAL_MERCHANT === '' && isset($_GET['demo']) && !empty($_SESSION['admin'])) { $ok = true; $ref = 'DEMO'; }
    elseif (ZARINPAL_MERCHANT !== '' && ($_GET['Status'] ?? '') === 'OK' && ($_GET['Authority'] ?? '') === $o['authority']) {
        $r = zarinpal('verify', ['merchant_id' => ZARINPAL_MERCHANT, 'amount' => $o['total'] * 10, 'authority' => $o['authority']]);
        $code = $r['data']['code'] ?? 0;
        if ($code == 100 || $code == 101) { $ok = true; $ref = (string)$r['data']['ref_id']; }
    }
    if ($ok) {
        db()->prepare("UPDATE orders SET ref_id=? WHERE id=?")->execute([$ref, $o['id']]);
        deliver($o);   // فقط یک بار اجرا می‌شود (قفل داخل deliver)
        $_SESSION['cart'] = [];
    } else {
        db()->prepare("UPDATE orders SET status='failed' WHERE id=? AND status='pending'")->execute([$o['id']]);
    }
}
// خریدار به پنل خودش می‌رود؛ اگر وارد نیست، صفحه پیگیری
if (user() && user()['id'] == $o['user_id']) header('Location: account?o=' . $o['track'] . '&new=1#o-' . $o['track']);
else header('Location: track.php?t=' . $o['track'] . '&new=1');
