<?php
require __DIR__ . '/config.php';
if (isset($_GET['logout'])) { unset($_SESSION['uid']); session_regenerate_id(true); header('Location: ./'); exit; }
$u = user();
if (!$u) { header('Location: login?back=account'); exit; }
$tab = $_GET['tab'] ?? 'orders';
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    if (($_POST['a'] ?? '') === 'profile') {
        $name = trim($_POST['name'] ?? '');
        if (mb_strlen($name) < 2) $err = 'نام معتبر نیست.';
        else { db()->prepare("UPDATE users SET name=?, email=? WHERE id=?")->execute([$name, trim($_POST['email'] ?? ''), $u['id']]); $msg = 'اطلاعات ذخیره شد.'; }
    } elseif (($_POST['a'] ?? '') === 'pass') {
        if (!password_verify($_POST['old'] ?? '', $u['pass'])) $err = 'رمز فعلی اشتباه است.';
        elseif (mb_strlen($_POST['new'] ?? '') < 6) $err = 'رمز جدید حداقل ۶ کاراکتر باشد.';
        else { db()->prepare("UPDATE users SET pass=? WHERE id=?")->execute([password_hash($_POST['new'], PASSWORD_DEFAULT), $u['id']]); $msg = 'رمز عبور تغییر کرد.'; }
    }
    $tab = 'profile';
    $s = db()->prepare("SELECT * FROM users WHERE id=?"); $s->execute([$u['id']]); $u = $s->fetch();
}

$s = db()->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY id DESC"); $s->execute([$u['id']]); $orders = $s->fetchAll();
$open = $_GET['o'] ?? ($orders[0]['track'] ?? '');
$spent = 0; $codes = 0;
foreach ($orders as $o) if (in_array($o['status'], ['paid', 'done'])) { $spent += $o['total']; $codes += $o['delivered'] ? count(explode(PHP_EOL, $o['delivered'])) : 0; }
// سطح گیمر بر اساس خرید
$lv = 1 + intdiv($spent, 2000000); $xp = ($spent % 2000000) / 20000;
$st = ['pending' => ['در انتظار پرداخت', 'warn'], 'failed' => ['پرداخت ناموفق', 'bad'], 'paid' => ['در حال آماده‌سازی کد', 'warn'], 'done' => ['تحویل شد', 'good'], 'refund' => ['بازگشت وجه', 'bad']];
$title = 'پنل کاربری'; $canonical = 'account'; $noindex = true;
include __DIR__ . '/inc/header.php';
?>
<section class="wrap acc">
  <aside class="box acc-side">
    <div class="avatar en"><?= e(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></div>
    <h1 class="h2"><?= e($u['name']) ?></h1>
    <small class="muted en"><?= e($u['phone']) ?></small>
    <div class="lvl"><span class="en">LVL <?= $lv ?></span><div class="xp"><i style="width:<?= max(4, round($xp)) ?>%"></i></div></div>
    <div class="acc-kpi"><div><b><?= fa(count($orders)) ?></b><small>سفارش</small></div><div><b><?= fa($codes) ?></b><small>کد دریافتی</small></div></div>
    <nav class="acc-nav">
      <a href="account" class="<?= $tab === 'orders' ? 'on' : '' ?>">🎁 سفارش‌ها و کدها</a>
      <a href="account?tab=profile" class="<?= $tab === 'profile' ? 'on' : '' ?>">⚙️ اطلاعات حساب</a>
      <a href="<?= e(SUPPORT_TELEGRAM) ?>" target="_blank" rel="noopener">🎧 پشتیبانی</a>
      <a href="account?logout=1">🚪 خروج</a>
    </nav>
  </aside>

  <div class="acc-main">
  <?php if ($tab === 'profile'): ?>
    <?php if ($msg): ?><div class="alert info"><?= e($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert"><?= e($err) ?></div><?php endif; ?>
    <form method="post" class="box"><h2>اطلاعات حساب</h2>
      <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="profile">
      <label>نام<input name="name" value="<?= e($u['name']) ?>" required></label>
      <label>شماره موبایل<input value="<?= e($u['phone']) ?>" disabled dir="ltr"></label>
      <label>ایمیل<input name="email" type="email" value="<?= e($u['email']) ?>" dir="ltr"></label>
      <button class="btn primary">ذخیره</button>
    </form>
    <form method="post" class="box"><h2>تغییر رمز عبور</h2>
      <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="pass">
      <label>رمز فعلی<input name="old" type="password" required autocomplete="current-password" dir="ltr"></label>
      <label>رمز جدید<input name="new" type="password" required minlength="6" autocomplete="new-password" dir="ltr"></label>
      <button class="btn primary">تغییر رمز</button>
    </form>
  <?php elseif (!$orders): ?>
    <div class="box empty big"><div class="empty-ico">🕹️</div>هنوز خریدی نداشتی. اولین لول‌آپ رو بزن!<a class="btn primary" href="./#shop">رفتن به فروشگاه</a></div>
  <?php else: ?>
    <h2 class="acc-h">سفارش‌های من</h2>
    <?php foreach ($orders as $o): $items = json_decode($o['items'], true); $isOpen = $o['track'] === $open; ?>
    <details class="box ord" <?= $isOpen ? 'open' : '' ?> id="o-<?= e($o['track']) ?>">
      <summary>
        <div><b class="en"><?= e($o['track']) ?></b><small class="muted"><?= fa(e($o['created'])) ?></small></div>
        <span class="ord-items"><?= e($items[0]['title'] ?? '') ?><?= count($items) > 1 ? ' + ' . fa(count($items) - 1) . ' مورد' : '' ?></span>
        <b><?= money($o['total']) ?></b>
        <span class="status <?= $st[$o['status']][1] ?? 'warn' ?>"><?= $st[$o['status']][0] ?? e($o['status']) ?></span>
      </summary>
      <?php if ($o['status'] === 'done' && isset($_GET['new']) && $isOpen): ?><div class="confetti" aria-hidden="true"></div><?php endif; ?>
      <?php if ($o['delivered']): ?>
        <h3>🎁 کدهای شما</h3>
        <?php foreach (explode(PHP_EOL, $o['delivered']) as $line): $parts = explode(': ', $line, 2); $code = $parts[1] ?? $line; ?>
          <div class="code"><?php if (isset($parts[1])): ?><span><?= e($parts[0]) ?></span><?php endif; ?><b class="mono reveal-code" data-code="<?= e($code) ?>">••••••••••••</b><button type="button" data-show>نمایش</button><button type="button" data-copy="<?= e($code) ?>">کپی</button></div>
        <?php endforeach; ?>
      <?php endif; ?>
      <?php if ($o['admin_note']): ?><p class="alert info">💬 پیام پشتیبانی: <?= nl2br(e($o['admin_note'])) ?></p><?php endif; ?>
      <?php if ($o['status'] === 'paid'): ?><p class="alert info">پرداخت ثبت شد. کد به‌زودی توسط پشتیبانی همین‌جا قرار می‌گیرد.</p><?php endif; ?>
      <?php if ($o['status'] === 'failed'): ?><p class="alert">پرداخت انجام نشد. اگر مبلغ کسر شده، تا ۷۲ ساعت برمی‌گردد.</p><?php endif; ?>
      <ul class="items">
        <?php foreach ($items as $it): ?><li><span><?= e($it['title']) ?> × <?= fa($it['qty']) ?></span><b><?= money($it['price'] * $it['qty']) ?></b></li><?php endforeach; ?>
        <?php if ($o['note']): ?><li><span>توضیحات شما</span><span><?= e($o['note']) ?></span></li><?php endif; ?>
        <?php if ($o['ref_id']): ?><li><span>کد رهگیری بانک</span><span class="mono"><?= e($o['ref_id']) ?></span></li><?php endif; ?>
      </ul>
    </details>
    <?php endforeach; ?>
  <?php endif; ?>
  </div>
</section>
<?php include __DIR__ . '/inc/footer.php'; ?>
