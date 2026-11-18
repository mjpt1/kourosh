<?php
// همه تنظیمات (نام سایت، درگاه، رمز مدیر و...) از داخل پنل مدیریت ← «تنظیمات» قابل تغییر است.
// رمز اولیه پنل مدیریت: admin1234 — بلافاصله بعد از اولین ورود عوضش کنید.

ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_start();
date_default_timezone_set('Asia/Tehran');

const DEFAULTS = [
    'site_name' => 'گیم‌پاس',
    'site_tagline' => 'گیفت کارت و آیتم بازی، تحویل آنی',
    'seo_title' => 'خرید گیفت کارت استیم، پلی‌استیشن و ایکس‌باکس | تحویل آنی',
    'seo_desc' => 'خرید آنلاین گیفت کارت استیم، پلی‌استیشن، ایکس‌باکس، گیم پس و شارژ یوسی پابجی و جم فری‌فایر با پرداخت ریالی و تحویل آنی کد. ضمانت اصالت و پشتیبانی ۲۴ ساعته.',
    'zarinpal' => '',
    'telegram' => 'https://t.me/your_id',
    'phone' => '',
    'admin_hash' => '',
    'enamad' => '',
];

function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $pdo = new PDO('sqlite:' . $dir . '/shop.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;
    CREATE TABLE IF NOT EXISTS categories(id INTEGER PRIMARY KEY, slug TEXT UNIQUE, name TEXT, c1 TEXT, c2 TEXT, icon TEXT, sort INTEGER DEFAULT 0);
    CREATE TABLE IF NOT EXISTS products(id INTEGER PRIMARY KEY, cat_id INTEGER, title TEXT, subtitle TEXT, price INTEGER, old_price INTEGER DEFAULT 0, region TEXT, description TEXT, hot INTEGER DEFAULT 0, active INTEGER DEFAULT 1, sold INTEGER DEFAULT 0);
    CREATE TABLE IF NOT EXISTS codes(id INTEGER PRIMARY KEY, product_id INTEGER, code TEXT, order_id INTEGER DEFAULT NULL);
    CREATE TABLE IF NOT EXISTS orders(id INTEGER PRIMARY KEY, track TEXT UNIQUE, name TEXT, phone TEXT, email TEXT, note TEXT, total INTEGER, items TEXT, status TEXT DEFAULT 'pending', authority TEXT, ref_id TEXT, delivered TEXT DEFAULT '', created TEXT);
    CREATE TABLE IF NOT EXISTS users(id INTEGER PRIMARY KEY, name TEXT, phone TEXT UNIQUE, email TEXT, pass TEXT, blocked INTEGER DEFAULT 0, created TEXT);
    CREATE TABLE IF NOT EXISTS settings(k TEXT PRIMARY KEY, v TEXT);
    CREATE INDEX IF NOT EXISTS ix_codes ON codes(product_id, order_id);");
    // ارتقای دیتابیس نسخه قبلی
    $cols = array_column($pdo->query("PRAGMA table_info(orders)")->fetchAll(), 'name');
    if (!in_array('user_id', $cols)) $pdo->exec("ALTER TABLE orders ADD COLUMN user_id INTEGER; ALTER TABLE orders ADD COLUMN admin_note TEXT DEFAULT '';");
    $cols = array_column($pdo->query("PRAGMA table_info(categories)")->fetchAll(), 'name');
    if (!in_array('seo_text', $cols)) $pdo->exec("ALTER TABLE categories ADD COLUMN seo_text TEXT DEFAULT '';");
    if (!$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn()) seed($pdo);
    return $pdo;
}

function setting($k) {
    static $all;
    if ($all === null) { $all = DEFAULTS; foreach (db()->query("SELECT k, v FROM settings") as $r) $all[$r['k']] = $r['v']; }
    return $all[$k] ?? '';
}
function set_setting($k, $v) { db()->prepare("REPLACE INTO settings(k, v) VALUES(?, ?)")->execute([$k, $v]); }

define('SITE_NAME', setting('site_name'));
define('SITE_TAGLINE', setting('site_tagline'));
define('ZARINPAL_MERCHANT', setting('zarinpal'));
define('SUPPORT_TELEGRAM', setting('telegram'));

// ---------- کاربران ----------
function user() {
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) { $s = db()->prepare("SELECT * FROM users WHERE id=? AND blocked=0"); $s->execute([$_SESSION['uid']]); $u = $s->fetch() ?: null; }
    }
    return $u;
}
function login_user($id) { session_regenerate_id(true); $_SESSION['uid'] = $id; }
function norm_phone($p) {
    $p = preg_replace('/\D/', '', strtr((string)$p, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']));
    if (str_starts_with($p, '98')) $p = '0' . substr($p, 2);
    if (strlen($p) === 10) $p = '0' . $p;
    return preg_match('/^09\d{9}$/', $p) ? $p : '';
}

// ---------- آدرس‌های تمیز برای سئو ----------
function base_path() {   // مثلاً /game — همه اسکریپت‌ها در ریشه یا پوشه admin هستند
    $d = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if (substr($d, -6) === '/admin') $d = substr($d, 0, -6);
    return $d;
}
function slugify($s) { $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', mb_strtolower(trim($s))); return trim($s, '-'); }
function purl($p) { return 'product/' . (int)$p['id'] . '/' . rawurlencode(slugify($p['title'])); }
function curl_cat($slug) { return 'category/' . rawurlencode($slug); }

function seed(PDO $pdo) {
    $cats = [
        ['steam','استیم','#1b6dff','#00d4ff','steam',1],
        ['playstation','پلی‌استیشن','#2a3cff','#7b5cff','ps',2],
        ['xbox','ایکس‌باکس','#11c26d','#8cff3f','xbox',3],
        ['mobile','بازی موبایل','#ff3d81','#ffb13d','mobile',4],
        ['pc','بازی‌های PC','#8a2be2','#ff3df2','pc',5],
        ['nintendo','نینتندو','#ff2a2a','#ff8a3d','switch',6],
    ];
    $s = $pdo->prepare("INSERT INTO categories(slug,name,c1,c2,icon,sort) VALUES(?,?,?,?,?,?)");
    foreach ($cats as $c) $s->execute($c);
    $p = [
        [1,'گیفت کارت استیم ۵ دلاری','Steam Wallet $5',780000,850000,'گلوبال','شارژ مستقیم کیف پول استیم. قابل استفاده برای خرید بازی، DLC و آیتم.',1],
        [1,'گیفت کارت استیم ۱۰ دلاری','Steam Wallet $10',1520000,0,'گلوبال','شارژ مستقیم کیف پول استیم. قابل استفاده برای خرید بازی، DLC و آیتم.',1],
        [1,'گیفت کارت استیم ۲۰ دلاری','Steam Wallet $20',2990000,3150000,'گلوبال','شارژ مستقیم کیف پول استیم.',0],
        [1,'گیفت کارت استیم ۵۰ دلاری','Steam Wallet $50',7350000,0,'گلوبال','شارژ مستقیم کیف پول استیم.',0],
        [2,'گیفت کارت پلی‌استیشن ۱۰ دلاری','PSN $10 US',1590000,0,'آمریکا','مخصوص اکانت‌های ریجن آمریکا.',1],
        [2,'گیفت کارت پلی‌استیشن ۲۵ دلاری','PSN $25 US',3890000,0,'آمریکا','مخصوص اکانت‌های ریجن آمریکا.',0],
        [2,'اشتراک پلاس اسنشیال ۳ ماهه','PS Plus Essential',3450000,3700000,'آمریکا','بازی آنلاین + بازی‌های رایگان ماهانه.',1],
        [3,'گیم پس آلتیمیت ۱ ماهه','Game Pass Ultimate',1290000,0,'گلوبال','دسترسی به صدها بازی روی کنسول و PC.',1],
        [3,'گیفت کارت ایکس‌باکس ۱۵ دلاری','Xbox $15',2290000,0,'آمریکا','شارژ اکانت ایکس‌باکس.',0],
        [4,'۶۶۰ یوسی پابجی موبایل','PUBG Mobile 660 UC',590000,650000,'گلوبال','شارژ با آیدی بازیکن، بدون نیاز به رمز.',1],
        [4,'۱۰۰۰ جم فری‌فایر','Free Fire 1000 Diamonds',720000,0,'گلوبال','شارژ با آیدی بازیکن.',0],
        [4,'سی‌پی کالاف دیوتی موبایل ۸۰۰','CODM 800 CP',650000,0,'گلوبال','شارژ با آیدی بازیکن.',0],
        [5,'ولورانت ۱۰۰۰ پوینت','Valorant 1000 VP',890000,0,'اروپا','کد شارژ ولورانت پوینت.',1],
        [5,'فورتنایت ۱۰۰۰ وی‌باکس','Fortnite 1000 V-Bucks',760000,0,'گلوبال','کد شارژ وی‌باکس.',0],
        [6,'گیفت کارت نینتندو ۱۰ دلاری','Nintendo eShop $10',1610000,0,'آمریکا','شارژ اکانت نینتندو.',0],
    ];
    $s = $pdo->prepare("INSERT INTO products(cat_id,title,subtitle,price,old_price,region,description,hot) VALUES(?,?,?,?,?,?,?,?)");
    foreach ($p as $r) $s->execute($r);
}

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function fa($n) { return strtr((string)$n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); }
function money($n) { return fa(number_format((int)$n)) . ' <small>تومان</small>'; }
function csrf() { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_ok() { return isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']); }
function cart() { return $_SESSION['cart'] ?? []; }
function cart_count() { return array_sum(cart()); }
function base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    if (substr($dir, -6) === '/admin') $dir = substr($dir, 0, -6);
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $dir;
}
function img($p) { return 'art/p' . (int)$p['id'] . '.svg'; }
function stock($pid) { $s = db()->prepare("SELECT COUNT(*) FROM codes WHERE product_id=? AND order_id IS NULL"); $s->execute([$pid]); return (int)$s->fetchColumn(); }

function zarinpal($action, $data) {
    $ch = curl_init("https://payment.zarinpal.com/pg/v4/payment/$action.json");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode($data)]);
    $r = curl_exec($ch); curl_close($ch);
    return json_decode((string)$r, true) ?: [];
}

// تخصیص خودکار کد از انبار به سفارش پرداخت‌شده
function deliver($order) {
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    $lock = $pdo->prepare("UPDATE orders SET status='processing' WHERE id=? AND status='pending'");
    $lock->execute([$order['id']]);
    if (!$lock->rowCount()) { $pdo->exec('ROLLBACK'); return; }   // قبلاً پردازش شده
    $out = []; $missing = false;
    foreach (json_decode($order['items'], true) as $it) {
        for ($i = 0; $i < $it['qty']; $i++) {
            $s = $pdo->prepare("SELECT id, code FROM codes WHERE product_id=? AND order_id IS NULL LIMIT 1");
            $s->execute([$it['id']]); $c = $s->fetch();
            if ($c) { $pdo->prepare("UPDATE codes SET order_id=? WHERE id=?")->execute([$order['id'], $c['id']]); $out[] = $it['title'] . ': ' . $c['code']; }
            else $missing = true;
        }
        $pdo->prepare("UPDATE products SET sold=sold+? WHERE id=?")->execute([$it['qty'], $it['id']]);
    }
    $pdo->prepare("UPDATE orders SET status=?, delivered=? WHERE id=?")->execute([$missing ? 'paid' : 'done', implode(PHP_EOL, $out), $order['id']]);
    $pdo->exec('COMMIT');
}

// سطح «کمیابی» کارت مثل آیتم‌های بازی
function rarity($price) {
    if ($price >= 3000000) return ['legendary', 'LEGENDARY'];
    if ($price >= 1200000) return ['epic', 'EPIC'];
    return ['rare', 'RARE'];
}

function card($p) { [$rc, $rl] = rarity($p['price']); ?>
  <article class="card tilt <?= $rc ?>" style="--a:<?= e($p['c1']) ?>;--b:<?= e($p['c2']) ?>">
    <a href="<?= purl($p) ?>" class="card-img">
      <img src="<?= img($p) ?>" alt="<?= e($p['title']) ?>" loading="lazy" decoding="async" width="400" height="250">
      <span class="rar en"><?= $rl ?></span>
      <?php if ($p['old_price'] > $p['price']): ?><span class="badge off"><?= fa(round(100 - $p['price'] * 100 / $p['old_price'])) ?>٪ تخفیف</span><?php elseif ($p['hot']): ?><span class="badge">🔥 داغ</span><?php endif; ?>
    </a>
    <div class="card-body">
      <a href="<?= purl($p) ?>" class="card-title"><?= e($p['title']) ?></a>
      <div class="card-meta"><span>🌍 <?= e($p['region']) ?></span><span>⚡ تحویل آنی</span></div>
      <div class="card-foot">
        <div class="price"><?php if ($p['old_price'] > $p['price']): ?><s><?= fa(number_format($p['old_price'])) ?></s><?php endif; ?><?= money($p['price']) ?></div>
        <button class="add" data-add="<?= $p['id'] ?>" aria-label="افزودن به سبد"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></button>
      </div>
    </div>
  </article>
<?php }
