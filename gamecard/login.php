<?php
require __DIR__ . '/config.php';
if (user()) { header('Location: account'); exit; }
$mode = ($_GET['m'] ?? $_POST['m'] ?? 'login') === 'register' ? 'register' : 'login';
$err = '';
$back = preg_match('#^[a-z_]+(\.php)?$#', $_GET['back'] ?? '') ? $_GET['back'] : 'account';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['tries'] = ($_SESSION['tries'] ?? 0) + 1;
    if ($_SESSION['tries'] > 15) { usleep(1500000); }
    $phone = norm_phone($_POST['phone'] ?? '');
    $pass = $_POST['pass'] ?? '';
    if (!csrf_ok()) $err = 'نشست منقضی شده، دوباره تلاش کنید.';
    elseif (!$phone) $err = 'شماره موبایل معتبر نیست.';
    elseif ($mode === 'login') {
        $s = db()->prepare("SELECT * FROM users WHERE phone=?"); $s->execute([$phone]); $u = $s->fetch();
        if ($u && $u['blocked']) $err = 'حساب شما مسدود شده است. با پشتیبانی تماس بگیرید.';
        elseif ($u && password_verify($pass, $u['pass'])) { login_user($u['id']); $_SESSION['tries'] = 0; header('Location: ' . $back); exit; }
        else { usleep(300000); $err = 'شماره موبایل یا رمز عبور اشتباه است.'; }
    } else {
        $name = trim($_POST['name'] ?? '');
        if (mb_strlen($name) < 2) $err = 'نام خود را وارد کنید.';
        elseif (mb_strlen($pass) < 6) $err = 'رمز عبور حداقل ۶ کاراکتر باشد.';
        else {
            $s = db()->prepare("SELECT id FROM users WHERE phone=?"); $s->execute([$phone]);
            if ($s->fetch()) $err = 'این شماره قبلاً ثبت‌نام کرده؛ وارد شوید.';
            else {
                db()->prepare("INSERT INTO users(name, phone, email, pass, created) VALUES(?,?,?,?,?)")
                    ->execute([$name, $phone, trim($_POST['email'] ?? ''), password_hash($pass, PASSWORD_DEFAULT), date('Y-m-d H:i')]);
                $uid = db()->lastInsertId();
                // سفارش‌های قبلی همین شماره به حساب جدید وصل شود
                db()->prepare("UPDATE orders SET user_id=? WHERE phone=? AND user_id IS NULL")->execute([$uid, $phone]);
                login_user($uid); header('Location: ' . $back); exit;
            }
        }
    }
}
$title = $mode === 'login' ? 'ورود به حساب' : 'ثبت‌نام'; $canonical = 'login'; $noindex = true;
include __DIR__ . '/inc/header.php';
?>
<section class="wrap narrow auth">
  <div class="auth-tabs">
    <a href="login?back=<?= e($back) ?>" class="<?= $mode === 'login' ? 'on' : '' ?>">ورود</a>
    <a href="login?m=register&back=<?= e($back) ?>" class="<?= $mode === 'register' ? 'on' : '' ?>">ثبت‌نام</a>
  </div>
  <form method="post" class="box">
    <h1 class="h2"><?= $mode === 'login' ? '🎮 خوش برگشتی!' : '🚀 ساخت حساب گیمر' ?></h1>
    <p class="muted"><?= $mode === 'login' ? 'برای دیدن سفارش‌ها و کدهات وارد شو.' : 'همه خریدها و کدهات یه جا، همیشه در دسترس.' ?></p>
    <?php if ($err): ?><div class="alert"><?= e($err) ?></div><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="m" value="<?= $mode ?>">
    <?php if ($mode === 'register'): ?><label>نام و نام خانوادگی<input name="name" required autocomplete="name" value="<?= e($_POST['name'] ?? '') ?>"></label><?php endif; ?>
    <label>شماره موبایل<input name="phone" required inputmode="tel" autocomplete="tel" placeholder="09xxxxxxxxx" dir="ltr" value="<?= e($_POST['phone'] ?? '') ?>"></label>
    <?php if ($mode === 'register'): ?><label>ایمیل (اختیاری)<input name="email" type="email" autocomplete="email" dir="ltr"></label><?php endif; ?>
    <label>رمز عبور<input name="pass" type="password" required minlength="<?= $mode === 'register' ? 6 : 1 ?>" autocomplete="<?= $mode === 'register' ? 'new-password' : 'current-password' ?>" dir="ltr"></label>
    <button class="btn primary block"><?= $mode === 'login' ? 'ورود' : 'ساخت حساب' ?></button>
    <?php if ($mode === 'login'): ?><small class="muted center">رمز را فراموش کرده‌اید؟ به <a class="link" href="<?= e(SUPPORT_TELEGRAM) ?>" target="_blank" rel="noopener">پشتیبانی</a> پیام دهید.</small><?php endif; ?>
  </form>
</section>
<?php include __DIR__ . '/inc/footer.php'; ?>
