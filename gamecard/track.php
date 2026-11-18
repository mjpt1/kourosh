<?php
require __DIR__ . '/config.php';
$title = 'پیگیری سفارش'; $noindex = true; $canonical = 'track.php';
$o = null; $msg = '';
$t = strtoupper(trim($_GET['t'] ?? $_POST['t'] ?? ''));
if ($t) {
    $s = db()->prepare("SELECT * FROM orders WHERE track=?"); $s->execute([$t]); $o = $s->fetch();
    // کد فقط برای خریدار همین مرورگر یا کسی که شماره موبایل را بداند نمایش داده می‌شود
    $phone = preg_replace('/\D/', '', strtr($_POST['phone'] ?? '', ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']));
    $mine = $o && (in_array($t, $_SESSION['my_orders'] ?? [], true) || ($phone && ltrim($phone, '0') === ltrim($o['phone'], '0')));
    if ($o && $mine) $_SESSION['my_orders'][] = $t;
    if (!$o || !$mine) { if ($_SERVER['REQUEST_METHOD'] === 'POST') $msg = 'سفارشی با این مشخصات پیدا نشد.'; $o = null; }
}
$st = ['processing' => ['در حال پردازش', 'warn'], 'refund' => ['بازگشت وجه', 'bad'], 'pending' => ['در انتظار پرداخت', 'warn'], 'failed' => ['پرداخت ناموفق', 'bad'], 'paid' => ['پرداخت شد — در حال آماده‌سازی کد', 'warn'], 'done' => ['تحویل شد', 'good']];
include __DIR__ . '/inc/header.php';
?>
<section class="wrap narrow">
<?php if ($o): $items = json_decode($o['items'], true); ?>
  <?php if ($o['status'] === 'done' && isset($_GET['new'])): ?><div class="confetti" aria-hidden="true"></div><?php endif; ?>
  <div class="box order">
    <div class="order-head">
      <div><small class="muted">کد پیگیری</small><h2 class="mono" data-copy="<?= e($o['track']) ?>"><?= e($o['track']) ?> <span class="copy">کپی</span></h2></div>
      <span class="status <?= $st[$o['status']][1] ?>"><?= $st[$o['status']][0] ?></span>
    </div>
    <?php if ($o['delivered']): ?>
      <h3>🎁 کدهای شما</h3>
      <?php foreach (explode(PHP_EOL, $o['delivered']) as $line): $parts = explode(': ', $line, 2); ?>
        <div class="code"><span><?= e($parts[0]) ?></span><b class="mono" data-copy="<?= e($parts[1] ?? $line) ?>"><?= e($parts[1] ?? $line) ?></b><button data-copy="<?= e($parts[1] ?? $line) ?>">کپی</button></div>
      <?php endforeach; ?>
    <?php endif; ?>
    <?php if ($o['status'] === 'paid'): ?><p class="alert info">پرداخت شما ثبت شد. کد تا چند دقیقه دیگر توسط پشتیبانی در همین صفحه قرار می‌گیرد. این صفحه را نگه دارید.</p><?php endif; ?>
    <?php if ($o['status'] === 'failed'): ?><p class="alert">پرداخت انجام نشد. اگر مبلغ کسر شده، تا ۷۲ ساعت به حساب شما برمی‌گردد. <a href="cart.php">تلاش مجدد</a></p><?php endif; ?>
    <ul class="items">
      <?php foreach ($items as $it): ?><li><span><?= e($it['title']) ?> × <?= fa($it['qty']) ?></span><b><?= money($it['price'] * $it['qty']) ?></b></li><?php endforeach; ?>
      <li class="tot"><span>جمع کل</span><b><?= money($o['total']) ?></b></li>
    </ul>
    <small class="muted">ثبت: <?= fa(e($o['created'])) ?></small>
  </div>
<?php else: ?>
  <form class="box track" method="post">
    <h2>📦 پیگیری سفارش</h2>
    <p class="muted">کد پیگیری و شماره موبایلی که موقع خرید وارد کردی رو بنویس.</p>
    <?php if ($msg): ?><div class="alert"><?= e($msg) ?></div><?php endif; ?>
    <label>کد پیگیری<input name="t" required class="mono" value="<?= e($t) ?>"></label>
    <label>شماره موبایل<input name="phone" required inputmode="tel"></label>
    <button class="btn primary block">نمایش سفارش</button>
  </form>
<?php endif; ?>
</section>
<?php include __DIR__ . '/inc/footer.php'; ?>
