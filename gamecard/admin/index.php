<?php
require dirname(__DIR__) . '/config.php';
$pdo = db();
header('X-Robots-Tag: noindex, nofollow');

function admin_ok($pass) {
    $h = setting('admin_hash');
    return $h ? password_verify($pass, $h) : hash_equals('admin1234', $pass);
}

if (isset($_GET['logout'])) { unset($_SESSION['admin']); header('Location: ./'); exit; }
if (empty($_SESSION['admin'])) {
    $bad = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_SESSION['atries'] = ($_SESSION['atries'] ?? 0) + 1;
        usleep(min(5, $_SESSION['atries']) * 400000);
        if (admin_ok($_POST['pass'] ?? '')) { session_regenerate_id(true); $_SESSION['admin'] = 1; $_SESSION['atries'] = 0; header('Location: ./'); exit; }
        $bad = true;
    }
    page_top('ورود مدیر'); ?>
    <form method="post" class="box login"><h2>🔐 ورود مدیر</h2><?php if ($bad): ?><div class="alert">رمز اشتباه است.</div><?php endif; ?>
    <label>رمز عبور<input type="password" name="pass" autofocus required dir="ltr"></label><button class="btn primary block">ورود</button></form>
    <?php page_end(); exit;
}

$tab = $_GET['tab'] ?? 'dash';
$flash = ''; $ferr = '';
$st = ['pending' => 'در انتظار پرداخت', 'processing' => 'در حال پردازش', 'paid' => 'پرداخت‌شده (نیاز به تحویل)', 'done' => 'تحویل شد', 'failed' => 'ناموفق', 'refund' => 'بازگشت وجه'];
$icons = ['steam' => 'استیم', 'ps' => 'پلی‌استیشن', 'xbox' => 'ایکس‌باکس', 'mobile' => 'موبایل', 'pc' => 'کامپیوتر', 'switch' => 'نینتندو'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) die('CSRF');
    $a = $_POST['a'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    switch ($a) {
    case 'order':
        $pdo->prepare("UPDATE orders SET status=?, delivered=?, admin_note=? WHERE id=?")
            ->execute([$_POST['status'], str_replace(["\r\n", "\r"], PHP_EOL, trim($_POST['delivered'])), trim($_POST['admin_note']), $id]);
        $flash = 'سفارش ذخیره شد و در پنل مشتری نمایش داده می‌شود.'; $tab = 'orders'; break;
    case 'autofill':   // برداشتن کد از انبار برای سفارش منتظر
        $o = $pdo->prepare("SELECT * FROM orders WHERE id=?"); $o->execute([$id]); $o = $o->fetch();
        $have = $o['delivered'] ? explode(PHP_EOL, $o['delivered']) : []; $need = false;
        foreach (json_decode($o['items'], true) as $it) {
            $already = count(array_filter($have, fn($l) => str_starts_with($l, $it['title'] . ': ')));
            for ($i = $already; $i < $it['qty']; $i++) {
                $c = $pdo->prepare("SELECT id, code FROM codes WHERE product_id=? AND order_id IS NULL LIMIT 1"); $c->execute([$it['id']]); $c = $c->fetch();
                if ($c) { $pdo->prepare("UPDATE codes SET order_id=? WHERE id=?")->execute([$id, $c['id']]); $have[] = $it['title'] . ': ' . $c['code']; } else $need = true;
            }
        }
        $pdo->prepare("UPDATE orders SET delivered=?, status=? WHERE id=?")->execute([implode(PHP_EOL, $have), $need ? 'paid' : 'done', $id]);
        $flash = $need ? 'بخشی از کدها در انبار نبود؛ بقیه را دستی وارد کنید.' : 'همه کدها تحویل شد.'; $tab = 'orders'; break;
    case 'product':
        $f = [(int)$_POST['cat_id'], trim($_POST['title']), trim($_POST['subtitle']), (int)$_POST['price'], (int)$_POST['old_price'], trim($_POST['region']), trim($_POST['description']), isset($_POST['hot']) ? 1 : 0, isset($_POST['active']) ? 1 : 0];
        if ($id) { $f[] = $id; $pdo->prepare("UPDATE products SET cat_id=?,title=?,subtitle=?,price=?,old_price=?,region=?,description=?,hot=?,active=? WHERE id=?")->execute($f); }
        else $pdo->prepare("INSERT INTO products(cat_id,title,subtitle,price,old_price,region,description,hot,active) VALUES(?,?,?,?,?,?,?,?,?)")->execute($f);
        $flash = 'محصول ذخیره شد.'; $tab = 'products'; break;
    case 'delprod':
        $pdo->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$id]);
        if (!empty($_POST['hard'])) { $pdo->prepare("DELETE FROM products WHERE id=?")->execute([$id]); $pdo->prepare("DELETE FROM codes WHERE product_id=? AND order_id IS NULL")->execute([$id]); }
        $flash = 'حذف شد.'; $tab = 'products'; break;
    case 'cat':
        $slug = slugify($_POST['slug'] ?: $_POST['name']);
        $f = [$slug, trim($_POST['name']), $_POST['c1'], $_POST['c2'], $_POST['icon'], (int)$_POST['sort'], trim($_POST['seo_text'])];
        try {
            if ($id) { $f[] = $id; $pdo->prepare("UPDATE categories SET slug=?,name=?,c1=?,c2=?,icon=?,sort=?,seo_text=? WHERE id=?")->execute($f); }
            else $pdo->prepare("INSERT INTO categories(slug,name,c1,c2,icon,sort,seo_text) VALUES(?,?,?,?,?,?,?)")->execute($f);
            $flash = 'دسته ذخیره شد.';
        } catch (PDOException $e) { $ferr = 'نامک (slug) تکراری است.'; }
        $tab = 'cats'; break;
    case 'delcat':
        $n = $pdo->prepare("SELECT COUNT(*) FROM products WHERE cat_id=?"); $n->execute([$id]);
        if ($n->fetchColumn()) $ferr = 'این دسته محصول دارد؛ اول محصولاتش را جابه‌جا یا حذف کنید.';
        else { $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$id]); $flash = 'دسته حذف شد.'; }
        $tab = 'cats'; break;
    case 'codes':
        $n = 0; $s = $pdo->prepare("INSERT INTO codes(product_id, code) VALUES(?,?)");
        $pdo->beginTransaction();
        foreach (preg_split('/\R/', $_POST['codes']) as $c) if ($c = trim($c)) { $s->execute([(int)$_POST['product_id'], $c]); $n++; }
        $pdo->commit();
        $flash = fa($n) . ' کد به انبار اضافه شد.'; $tab = 'codes'; break;
    case 'delcode':
        $pdo->prepare("DELETE FROM codes WHERE id=? AND order_id IS NULL")->execute([$id]); $tab = 'codes'; break;
    case 'user':
        if (!empty($_POST['newpass'])) {
            if (mb_strlen($_POST['newpass']) < 6) { $ferr = 'رمز حداقل ۶ کاراکتر.'; $tab = 'users'; break; }
            $pdo->prepare("UPDATE users SET pass=? WHERE id=?")->execute([password_hash($_POST['newpass'], PASSWORD_DEFAULT), $id]);
        }
        $pdo->prepare("UPDATE users SET name=?, email=?, blocked=? WHERE id=?")->execute([trim($_POST['name']), trim($_POST['email']), isset($_POST['blocked']) ? 1 : 0, $id]);
        $flash = 'کاربر ذخیره شد.'; $tab = 'users'; break;
    case 'settings':
        foreach (['site_name', 'site_tagline', 'seo_title', 'seo_desc', 'zarinpal', 'telegram', 'phone', 'enamad'] as $k) set_setting($k, trim($_POST[$k] ?? ''));
        if (!empty($_POST['admin_new'])) {
            if (mb_strlen($_POST['admin_new']) < 8) $ferr = 'رمز مدیر حداقل ۸ کاراکتر باشد.';
            else set_setting('admin_hash', password_hash($_POST['admin_new'], PASSWORD_DEFAULT));
        }
        if (!$ferr) $flash = 'تنظیمات ذخیره شد.';
        $tab = 'settings'; break;
    }
    if (!$ferr) { $_SESSION['flash'] = $flash; header('Location: ?tab=' . $tab . (isset($_GET['edit']) && $a !== 'product' && $a !== 'cat' ? '' : '')); exit; }
}
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

$cats = $pdo->query("SELECT * FROM categories ORDER BY sort")->fetchAll();
$prods = $pdo->query("SELECT p.*, c.name cat_name, (SELECT COUNT(*) FROM codes WHERE product_id=p.id AND order_id IS NULL) stock FROM products p LEFT JOIN categories c ON c.id=p.cat_id ORDER BY p.cat_id, p.id")->fetchAll();
$waiting = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status='paid'")->fetchColumn();

page_top('پنل مدیریت'); ?>
<div class="admin-top">
  <h2>⚙️ پنل مدیریت <?= e(SITE_NAME) ?></h2>
  <div class="row-gap"><a class="btn sm" href="../" target="_blank">مشاهده سایت ↗</a><a class="btn sm" href="?logout=1">خروج</a></div>
</div>
<?php if (!setting('admin_hash')): ?><div class="alert">⚠️ رمز پیش‌فرض مدیر هنوز فعال است. از «تنظیمات» همین الان عوضش کنید.</div><?php endif; ?>
<?php if (!ZARINPAL_MERCHANT): ?><div class="alert">⚠️ مرچنت زرین‌پال وارد نشده؛ مشتری‌ها نمی‌توانند پرداخت کنند (فقط خود شما برای تست). از «تنظیمات» واردش کنید.</div><?php endif; ?>
<nav class="atabs">
  <?php foreach (['dash' => '📊 داشبورد', 'orders' => '🧾 سفارش‌ها' . ($waiting ? " ($waiting)" : ''), 'products' => '🎮 محصولات', 'codes' => '🔑 انبار کد', 'cats' => '🗂️ دسته‌ها', 'users' => '👥 کاربران', 'settings' => '⚙️ تنظیمات و سئو'] as $k => $v): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="?tab=<?= $k ?>"><?= $v ?></a><?php endforeach; ?>
</nav>
<?php if ($flash): ?><div class="alert info"><?= e($flash) ?></div><?php endif; ?>
<?php if ($ferr): ?><div class="alert"><?= e($ferr) ?></div><?php endif; ?>

<?php if ($tab === 'dash'):
  $k = $pdo->query("SELECT COUNT(*) n, COALESCE(SUM(CASE WHEN status IN ('paid','done') THEN total END),0) sales, SUM(status IN ('paid','done')) okn FROM orders")->fetch();
  $today = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('paid','done') AND created LIKE ?"); $today->execute([date('Y-m-d') . '%']);
  $users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
  $days = [];
  for ($i = 13; $i >= 0; $i--) { $d = date('Y-m-d', strtotime("-$i day")); $q = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE status IN ('paid','done') AND created LIKE ?"); $q->execute([$d . '%']); $days[$d] = (int)$q->fetchColumn(); }
  $max = max(1, max($days));
  $low = array_filter($prods, fn($p) => $p['active'] && $p['stock'] < 3);
  $top = $pdo->query("SELECT title, sold FROM products ORDER BY sold DESC LIMIT 5")->fetchAll(); ?>
  <div class="kpis big">
    <div><small>فروش امروز</small><b><?= money($today->fetchColumn()) ?></b></div>
    <div><small>کل فروش</small><b><?= money($k['sales']) ?></b></div>
    <div><small>سفارش موفق</small><b><?= fa((int)$k['okn']) ?></b></div>
    <div><small>کاربران</small><b><?= fa($users) ?></b></div>
    <div class="<?= $waiting ? 'hl' : '' ?>"><small>منتظر تحویل</small><b><?= fa($waiting) ?></b></div>
  </div>
  <div class="box"><h3>فروش ۱۴ روز اخیر</h3>
    <div class="bars"><?php foreach ($days as $d => $v): ?><div title="<?= e($d) ?>: <?= number_format($v) ?>"><i style="height:<?= max(2, round($v / $max * 100)) ?>%"></i><small><?= fa(substr($d, 8)) ?></small></div><?php endforeach; ?></div>
  </div>
  <div class="two">
    <div class="box"><h3>⚠️ موجودی کم (کمتر از ۳ کد)</h3><?php if (!$low): ?><p class="muted">همه محصولات موجودی کافی دارند.</p><?php endif; ?>
      <?php foreach ($low as $p): ?><div class="mini-row"><span><?= e($p['title']) ?></span><b class="warnt"><?= fa($p['stock']) ?></b></div><?php endforeach; ?>
      <a class="btn sm" href="?tab=codes">افزودن کد</a></div>
    <div class="box"><h3>🏆 پرفروش‌ترین‌ها</h3><?php foreach ($top as $t): ?><div class="mini-row"><span><?= e($t['title']) ?></span><b><?= fa($t['sold']) ?></b></div><?php endforeach; ?></div>
  </div>

<?php elseif ($tab === 'orders'):
  $f = $_GET['f'] ?? ''; $q = trim($_GET['q'] ?? '');
  $w = []; $args = [];
  if ($f) { $w[] = 'status=?'; $args[] = $f; }
  if ($q) { $w[] = '(track LIKE ? OR phone LIKE ? OR name LIKE ?)'; array_push($args, "%$q%", '%' . (norm_phone($q) ?: $q) . '%', "%$q%"); }
  $s = $pdo->prepare("SELECT * FROM orders" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY (status='paid') DESC, id DESC LIMIT 300"); $s->execute($args); ?>
  <form class="filters"><input type="hidden" name="tab" value="orders"><input name="q" value="<?= e($q) ?>" placeholder="جستجو: کد پیگیری، موبایل یا نام"><button class="btn sm primary">جستجو</button></form>
  <div class="filters"><a href="?tab=orders" class="chip <?= !$f ? 'on' : '' ?>">همه</a><?php foreach ($st as $k => $v): ?><a href="?tab=orders&f=<?= $k ?>" class="chip <?= $f === $k ? 'on' : '' ?>"><?= $v ?></a><?php endforeach; ?></div>
  <?php foreach ($s as $o): ?>
  <details class="box arow" <?= $o['status'] === 'paid' ? 'open' : '' ?>>
    <summary><b class="mono"><?= e($o['track']) ?></b><span><?= e($o['name']) ?> — <span class="en"><?= e($o['phone']) ?></span></span><span><?= money($o['total']) ?></span><span class="status s-<?= e($o['status']) ?>"><?= $st[$o['status']] ?? e($o['status']) ?></span><small><?= fa(e($o['created'])) ?></small></summary>
    <ul class="items"><?php foreach (json_decode($o['items'], true) as $it): ?><li><span><?= e($it['title']) ?> × <?= fa($it['qty']) ?></span><b><?= money($it['price'] * $it['qty']) ?></b></li><?php endforeach; ?></ul>
    <?php if ($o['note']): ?><p>📝 توضیح مشتری: <b><?= e($o['note']) ?></b></p><?php endif; ?>
    <?php if ($o['email']): ?><p>✉️ <?= e($o['email']) ?></p><?php endif; ?>
    <?php if ($o['ref_id']): ?><p>کد رهگیری بانک: <span class="mono"><?= e($o['ref_id']) ?></span></p><?php endif; ?>
    <?php if (in_array($o['status'], ['paid', 'processing'])): ?>
      <form method="post"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="autofill"><input type="hidden" name="id" value="<?= $o['id'] ?>"><button class="btn sm">⚡ برداشتن خودکار کد از انبار</button></form>
    <?php endif; ?>
    <form method="post" class="oform"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="order"><input type="hidden" name="id" value="<?= $o['id'] ?>">
      <label>کدهای تحویلی — در پنل مشتری نمایش داده می‌شود (هر خط: «نام محصول: کد»)<textarea name="delivered" rows="3" class="mono" dir="ltr"><?= e($o['delivered']) ?></textarea></label>
      <label>پیام برای مشتری (اختیاری)<textarea name="admin_note" rows="2"><?= e($o['admin_note'] ?? '') ?></textarea></label>
      <label>وضعیت<select name="status"><?php foreach ($st as $k => $v): ?><option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
      <button class="btn primary">ذخیره و نمایش به مشتری</button>
    </form>
  </details>
  <?php endforeach; ?>

<?php elseif ($tab === 'products'):
  $edit = null; foreach ($prods as $p) if ($p['id'] == ($_GET['edit'] ?? 0)) $edit = $p; ?>
  <form method="post" class="box pform" id="pf"><h3><?= $edit ? 'ویرایش: ' . e($edit['title']) : '➕ محصول جدید' ?></h3>
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="product"><input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
    <label>دسته<select name="cat_id"><?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= ($edit['cat_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
    <label>عنوان فارسی (مهم برای سئو)<input name="title" required value="<?= e($edit['title'] ?? '') ?>" placeholder="گیفت کارت استیم ۲۰ دلاری"></label>
    <label>عنوان انگلیسی (روی تصویر کارت)<input name="subtitle" dir="ltr" value="<?= e($edit['subtitle'] ?? '') ?>" placeholder="Steam Wallet $20"></label>
    <label>ریجن<input name="region" value="<?= e($edit['region'] ?? 'گلوبال') ?>"></label>
    <label>قیمت (تومان)<input name="price" type="number" min="0" required value="<?= e($edit['price'] ?? '') ?>"></label>
    <label>قیمت قبل از تخفیف (۰ = بدون تخفیف)<input name="old_price" type="number" min="0" value="<?= e($edit['old_price'] ?? 0) ?>"></label>
    <label class="full">توضیحات (برای سئو: کامل و طبیعی بنویسید، حداقل ۲–۳ جمله)<textarea name="description" rows="4"><?= e($edit['description'] ?? '') ?></textarea></label>
    <label class="ck"><input type="checkbox" name="hot" <?= !empty($edit['hot']) ? 'checked' : '' ?>> پرفروش / داغ (در صفحه اول)</label>
    <label class="ck"><input type="checkbox" name="active" <?= ($edit['active'] ?? 1) ? 'checked' : '' ?>> فعال (نمایش در سایت)</label>
    <div class="full row-gap"><button class="btn primary">ذخیره</button><?php if ($edit): ?><a class="btn" href="?tab=products">انصراف</a><a class="btn" href="../<?= purl($edit) ?>" target="_blank">مشاهده ↗</a><?php endif; ?></div>
  </form>
  <div class="table">
  <?php foreach ($prods as $p): ?>
    <div class="trow <?= $p['active'] ? '' : 'off' ?>"><img src="../<?= img($p) ?>" alt="" loading="lazy" width="70" height="44"><b><?= e($p['title']) ?><small class="muted"> — <?= e($p['cat_name']) ?></small></b><span><?= money($p['price']) ?></span><span class="<?= $p['stock'] ? '' : 'warnt' ?>">انبار: <?= fa($p['stock']) ?></span><span>فروش: <?= fa($p['sold']) ?></span>
      <a class="btn sm" href="?tab=products&edit=<?= $p['id'] ?>#pf">ویرایش</a>
      <form method="post" onsubmit="return confirm(this.hard.checked ? 'حذف کامل؟ قابل بازگشت نیست.' : 'محصول غیرفعال شود؟')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="delprod"><input type="hidden" name="id" value="<?= $p['id'] ?>"><label class="ck sm"><input type="checkbox" name="hard"> کامل</label><button class="btn sm danger">حذف</button></form></div>
  <?php endforeach; ?>
  </div>

<?php elseif ($tab === 'codes'): $pid = (int)($_GET['p'] ?? 0); ?>
  <form method="post" class="box"><h3>➕ افزودن کد به انبار</h3>
    <p class="muted">کد موجود بعد از پرداخت خودکار در پنل مشتری نمایش داده می‌شود. اگر موجود نباشد، سفارش «نیاز به تحویل» می‌شود.</p>
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="codes">
    <label>محصول<select name="product_id"><?php foreach ($prods as $p): ?><option value="<?= $p['id'] ?>" <?= $pid == $p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?> (موجودی <?= fa($p['stock']) ?>)</option><?php endforeach; ?></select></label>
    <label>کدها (هر خط یک کد)<textarea name="codes" rows="6" class="mono" dir="ltr" required></textarea></label>
    <button class="btn primary">افزودن</button>
  </form>
  <div class="filters"><a class="chip <?= !$pid ? 'on' : '' ?>" href="?tab=codes">همه</a><?php foreach ($prods as $p) if ($p['stock']): ?><a class="chip <?= $pid == $p['id'] ? 'on' : '' ?>" href="?tab=codes&p=<?= $p['id'] ?>"><?= e($p['title']) ?> (<?= fa($p['stock']) ?>)</a><?php endif; ?></div>
  <div class="table">
  <?php $s = $pdo->prepare("SELECT c.*, p.title FROM codes c JOIN products p ON p.id=c.product_id WHERE c.order_id IS NULL" . ($pid ? " AND c.product_id=?" : "") . " ORDER BY c.id DESC LIMIT 500"); $s->execute($pid ? [$pid] : []);
  foreach ($s as $c): ?>
    <div class="trow"><b><?= e($c['title']) ?></b><span class="mono" dir="ltr"><?= e(mb_substr($c['code'], 0, 4)) ?>••••••</span>
    <form method="post" onsubmit="return confirm('این کد حذف شود؟')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="delcode"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn sm danger">حذف</button></form></div>
  <?php endforeach; ?>
  </div>

<?php elseif ($tab === 'cats'):
  $edit = null; foreach ($cats as $c) if ($c['id'] == ($_GET['edit'] ?? 0)) $edit = $c; ?>
  <form method="post" class="box pform" id="cf"><h3><?= $edit ? 'ویرایش دسته' : '➕ دسته جدید' ?></h3>
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="cat"><input type="hidden" name="id" value="<?= $edit['id'] ?? '' ?>">
    <label>نام<input name="name" required value="<?= e($edit['name'] ?? '') ?>"></label>
    <label>نامک در آدرس (انگلیسی، مثل steam)<input name="slug" dir="ltr" value="<?= e($edit['slug'] ?? '') ?>"></label>
    <label>رنگ اول<input type="color" name="c1" value="<?= e($edit['c1'] ?? '#7b5cff') ?>"></label>
    <label>رنگ دوم<input type="color" name="c2" value="<?= e($edit['c2'] ?? '#00d4ff') ?>"></label>
    <label>آیکون تصویر<select name="icon"><?php foreach ($icons as $k => $v): ?><option value="<?= $k ?>" <?= ($edit['icon'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></label>
    <label>ترتیب نمایش<input type="number" name="sort" value="<?= e($edit['sort'] ?? count($cats) + 1) ?>"></label>
    <label class="full">متن معرفی دسته (برای سئو؛ بالای صفحه دسته نمایش داده می‌شود)<textarea name="seo_text" rows="3"><?= e($edit['seo_text'] ?? '') ?></textarea></label>
    <div class="full row-gap"><button class="btn primary">ذخیره</button><?php if ($edit): ?><a class="btn" href="?tab=cats">انصراف</a><?php endif; ?></div>
  </form>
  <div class="table">
  <?php foreach ($cats as $c): ?>
    <div class="trow"><img src="../art/c<?= $c['id'] ?>.svg" alt="" width="70" height="44" loading="lazy"><b><?= e($c['name']) ?> <small class="muted en">/<?= e($c['slug']) ?></small></b>
      <a class="btn sm" href="?tab=cats&edit=<?= $c['id'] ?>#cf">ویرایش</a>
      <form method="post" onsubmit="return confirm('دسته حذف شود؟')"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="delcat"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="btn sm danger">حذف</button></form></div>
  <?php endforeach; ?>
  </div>

<?php elseif ($tab === 'users'):
  $q = trim($_GET['q'] ?? ''); $uid = (int)($_GET['u'] ?? 0);
  $s = $pdo->prepare("SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id=u.id) n, (SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id=u.id AND status IN ('paid','done')) spent FROM users u" . ($q ? " WHERE name LIKE ? OR phone LIKE ?" : "") . " ORDER BY u.id DESC LIMIT 300");
  $s->execute($q ? ["%$q%", '%' . (norm_phone($q) ?: $q) . '%'] : []); ?>
  <form class="filters"><input type="hidden" name="tab" value="users"><input name="q" value="<?= e($q) ?>" placeholder="جستجو: نام یا موبایل"><button class="btn sm primary">جستجو</button></form>
  <?php foreach ($s as $u): ?>
  <details class="box arow" <?= $uid === (int)$u['id'] ? 'open' : '' ?>>
    <summary><b><?= e($u['name']) ?></b><span class="en"><?= e($u['phone']) ?></span><span><?= fa($u['n']) ?> سفارش</span><span><?= money($u['spent']) ?></span><?php if ($u['blocked']): ?><span class="status s-failed">مسدود</span><?php endif; ?><small><?= fa(e($u['created'])) ?></small></summary>
    <form method="post" class="oform"><input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="user"><input type="hidden" name="id" value="<?= $u['id'] ?>">
      <label>نام<input name="name" value="<?= e($u['name']) ?>"></label>
      <label>ایمیل<input name="email" value="<?= e($u['email']) ?>" dir="ltr"></label>
      <label>رمز جدید (برای بازیابی رمز مشتری؛ خالی = بدون تغییر)<input name="newpass" dir="ltr" autocomplete="off"></label>
      <label class="ck"><input type="checkbox" name="blocked" <?= $u['blocked'] ? 'checked' : '' ?>> مسدود</label>
      <div class="row-gap"><button class="btn primary">ذخیره</button><a class="btn" href="?tab=orders&q=<?= e($u['phone']) ?>">سفارش‌های این کاربر</a></div>
    </form>
  </details>
  <?php endforeach; ?>

<?php else: ?>
  <form method="post" class="box pform"><h3>⚙️ تنظیمات سایت</h3>
    <input type="hidden" name="csrf" value="<?= csrf() ?>"><input type="hidden" name="a" value="settings">
    <label>نام سایت<input name="site_name" value="<?= e(setting('site_name')) ?>"></label>
    <label>شعار کوتاه<input name="site_tagline" value="<?= e(setting('site_tagline')) ?>"></label>
    <label class="full">عنوان سئو صفحه اصلی (حدود ۵۰–۶۰ کاراکتر، کلمه کلیدی اصلی اول)<input name="seo_title" value="<?= e(setting('seo_title')) ?>" data-count="60"></label>
    <label class="full">توضیحات متا صفحه اصلی (حدود ۱۲۰–۱۶۰ کاراکتر)<textarea name="seo_desc" rows="3" data-count="160"><?= e(setting('seo_desc')) ?></textarea></label>
    <label>مرچنت کد زرین‌پال<input name="zarinpal" dir="ltr" value="<?= e(setting('zarinpal')) ?>" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></label>
    <label>لینک پشتیبانی تلگرام<input name="telegram" dir="ltr" value="<?= e(setting('telegram')) ?>"></label>
    <label>تلفن پشتیبانی<input name="phone" dir="ltr" value="<?= e(setting('phone')) ?>"></label>
    <label class="full">کد نماد اعتماد (اینماد) — HTML کامل که از enamad.ir می‌گیرید<textarea name="enamad" rows="3" dir="ltr" class="mono"><?= e(setting('enamad')) ?></textarea></label>
    <h3 class="full">🔐 تغییر رمز مدیر</h3>
    <label class="full">رمز جدید مدیر (حداقل ۸ کاراکتر؛ خالی = بدون تغییر)<input name="admin_new" type="password" dir="ltr" autocomplete="new-password"></label>
    <div class="full"><button class="btn primary">ذخیره تنظیمات</button></div>
  </form>
  <div class="box"><h3>🔎 ابزار سئو</h3>
    <p>نقشه سایت: <a class="link en" href="../sitemap.xml" target="_blank"><?= e(base_url()) ?>/sitemap.xml</a></p>
    <p class="muted">این آدرس را در Google Search Console ← Sitemaps ثبت کنید.</p>
  </div>
<?php endif;
page_end();

function page_top($t) { ?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= e($t) ?></title>
<link rel="icon" href="../assets/logo.svg"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css"><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lalezar&family=Orbitron:wght@600;800;900&display=swap"><link rel="stylesheet" href="../assets/style.css?v=<?= filemtime(dirname(__DIR__) . '/assets/style.css') ?>"></head><body class="admin"><main class="wrap"><?php }
function page_end() { ?></main><script>document.querySelectorAll('[data-count]').forEach(el=>{const s=document.createElement('small');s.className='muted';el.after(s);const u=()=>s.textContent=el.value.length+' / '+el.dataset.count;el.addEventListener('input',u);u();});</script></body></html><?php }
