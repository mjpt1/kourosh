<?php
require __DIR__ . '/config.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_ok() || !cart()) { header('Location: cart.php'); exit; }

$fail = function ($m) { $_SESSION['err'] = $m; header('Location: cart.php'); exit; };
if (ZARINPAL_MERCHANT === '' && empty($_SESSION['admin'])) $fail('درگاه پرداخت هنوز فعال نشده است. لطفاً بعداً تلاش کنید یا به پشتیبانی پیام دهید.');

// هر خریدار یک حساب دارد: یا وارد شده، یا همین‌جا حسابش ساخته می‌شود
if ($u = user()) {
    $name = $u['name']; $phone = $u['phone']; $email = $u['email']; $uid = $u['id'];
} else {
    $phone = norm_phone($_POST['phone'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if (!$name || !$phone) $fail('نام و شماره موبایل معتبر وارد کنید.');
    if (mb_strlen($_POST['pass'] ?? '') < 6) $fail('رمز عبور حداقل ۶ کاراکتر باشد.');
    $s = db()->prepare("SELECT id FROM users WHERE phone=?"); $s->execute([$phone]);
    if ($s->fetch()) $fail('این شماره قبلاً حساب دارد. لطفاً اول وارد شوید.');
    db()->prepare("INSERT INTO users(name, phone, email, pass, created) VALUES(?,?,?,?,?)")
        ->execute([$name, $phone, $email, password_hash($_POST['pass'], PASSWORD_DEFAULT), date('Y-m-d H:i')]);
    $uid = db()->lastInsertId();
    login_user($uid);
}

$cart = cart(); $items = []; $total = 0;
$ids = implode(',', array_map('intval', array_keys($cart)));
foreach (db()->query("SELECT id, title, price FROM products WHERE id IN ($ids) AND active=1") as $p) {
    $q = (int)$cart[$p['id']];
    $items[] = ['id' => $p['id'], 'title' => $p['title'], 'price' => $p['price'], 'qty' => $q];
    $total += $p['price'] * $q;
}
if (!$items) { header('Location: cart.php'); exit; }

$track = strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
db()->prepare("INSERT INTO orders(track,user_id,name,phone,email,note,total,items,created) VALUES(?,?,?,?,?,?,?,?,?)")
   ->execute([$track, $uid, $name, $phone, $email, trim($_POST['note'] ?? ''), $total, json_encode($items, JSON_UNESCAPED_UNICODE), date('Y-m-d H:i')]);
$_SESSION['my_orders'][] = $track;

if (ZARINPAL_MERCHANT === '') {           // حالت آزمایشی
    header('Location: verify.php?demo=1&t=' . $track); exit;
}

$res = zarinpal('request', [
    'merchant_id' => ZARINPAL_MERCHANT,
    'amount' => $total * 10,              // ریال
    'callback_url' => base_url() . '/verify.php?t=' . $track,
    'description' => 'سفارش ' . $track,
    'metadata' => ['mobile' => $phone],
]);
if (($res['data']['code'] ?? 0) == 100) {
    db()->prepare("UPDATE orders SET authority=? WHERE track=?")->execute([$res['data']['authority'], $track]);
    header('Location: https://payment.zarinpal.com/pg/StartPay/' . $res['data']['authority']); exit;
}
$_SESSION['err'] = 'اتصال به درگاه ناموفق بود. دوباره تلاش کنید.';
header('Location: cart.php');

