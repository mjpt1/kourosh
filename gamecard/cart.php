<?php
require __DIR__ . '/config.php';
$title = 'سبد خرید'; $noindex = true; $canonical = 'cart.php';
$items = []; $total = 0;
if ($cart = cart()) {
    $ids = implode(',', array_map('intval', array_keys($cart)));
    foreach (db()->query("SELECT p.*, c.c1, c.c2 FROM products p JOIN categories c ON c.id=p.cat_id WHERE p.id IN ($ids) AND p.active=1") as $p) {
        $p['qty'] = $cart[$p['id']]; $p['line'] = $p['qty'] * $p['price'];
        $total += $p['line']; $items[] = $p;
    }
}
$err = $_SESSION['err'] ?? ''; unset($_SESSION['err']);
include __DIR__ . '/inc/header.php';
?>
<section class="wrap">
  <div class="sec-head"><h2>🛒 سبد خرید</h2></div>
  <?php if (!$items): ?>
    <div class="empty big"><div class="empty-ico">🎮</div>سبدت خالیه! <a class="btn primary" href="index.php#shop">بریم خرید</a></div>
  <?php else: ?>
  <div class="checkout">
    <div class="lines">
      <?php foreach ($items as $p): ?>
      <div class="line" data-line="<?= $p['id'] ?>">
        <img src="<?= img($p) ?>" alt="">
        <div class="line-info"><a href="<?= purl($p) ?>"><?= e($p['title']) ?></a><span class="price"><?= money($p['price']) ?></span></div>
        <div class="qty sm"><button data-set="<?= $p['id'] ?>" data-d="-1">−</button><input value="<?= fa($p['qty']) ?>" readonly><button data-set="<?= $p['id'] ?>" data-d="1">+</button></div>
        <button class="rm" data-set="<?= $p['id'] ?>" data-d="-99" aria-label="حذف">✕</button>
      </div>
      <?php endforeach; ?>
    </div>
    <form class="box pay" method="post" action="checkout.php">
      <h3>تکمیل خرید</h3>
      <?php if ($err): ?><div class="alert"><?= e($err) ?></div><?php endif; ?>
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <?php if ($u = user()): ?>
        <div class="who"><span>👤 <?= e($u['name']) ?></span><small class="en muted"><?= e($u['phone']) ?></small></div>
        <small class="muted">کدها بعد از پرداخت در <a class="link" href="account">پنل کاربری</a> شما نمایش داده می‌شود.</small>
      <?php else: ?>
        <p class="muted small-note">حساب داری؟ <a class="link" href="login?back=cart.php">وارد شو</a> — وگرنه همین‌جا حسابت ساخته می‌شه و کدها توی پنلت می‌مونه.</p>
        <label>نام<input name="name" required autocomplete="name"></label>
        <label>موبایل<input name="phone" required inputmode="tel" placeholder="09xxxxxxxxx" autocomplete="tel" dir="ltr"></label>
        <label>یک رمز عبور برای پنلت<input name="pass" type="password" required minlength="6" autocomplete="new-password" dir="ltr"></label>
        <label>ایمیل (اختیاری)<input name="email" type="email" autocomplete="email" dir="ltr"></label>
      <?php endif; ?>
      <label>آیدی بازیکن / توضیحات (برای شارژ موبایل)<input name="note"></label>
      <div class="sum"><span>مبلغ قابل پرداخت</span><b><?= money($total) ?></b></div>
      <button class="btn primary block">پرداخت و دریافت کد ⚡</button>
      <small class="muted center">پرداخت امن از طریق درگاه بانکی</small>
    </form>
  </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/inc/footer.php'; ?>
