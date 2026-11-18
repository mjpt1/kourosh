<?php
require __DIR__ . '/config.php';
$where = "p.active=1"; $args = [];
if (!empty($_GET['cat'])) { $where .= " AND c.slug=?"; $args[] = $_GET['cat']; }
if (!empty($_GET['q']))   { $where .= " AND (p.title LIKE ? OR p.subtitle LIKE ?)"; $args[] = '%'.$_GET['q'].'%'; $args[] = '%'.$_GET['q'].'%'; }
$s = db()->prepare("SELECT p.*, c.name cat_name, c.c1, c.c2 FROM products p JOIN categories c ON c.id=p.cat_id WHERE $where ORDER BY p.hot DESC, p.id");
$s->execute($args); $products = $s->fetchAll();
$hot = db()->query("SELECT p.*, c.c1, c.c2 FROM products p JOIN categories c ON c.id=p.cat_id WHERE p.active=1 AND p.hot=1 ORDER BY p.id LIMIT 8")->fetchAll();
$deal = db()->query("SELECT p.*, c.c1, c.c2 FROM products p JOIN categories c ON c.id=p.cat_id WHERE p.active=1 AND p.old_price>p.price ORDER BY (p.old_price-p.price)*1.0/p.old_price DESC LIMIT 1")->fetch();
$filtered = !empty($_GET['cat']) || !empty($_GET['q']);
$root = base_url() . '/';
$catRow = null;
if (!empty($_GET['cat'])) { $s = db()->prepare("SELECT * FROM categories WHERE slug=?"); $s->execute([$_GET['cat']]); $catRow = $s->fetch(); }
if (!empty($_GET['cat']) && !$catRow) http_response_code(404);
$faqs = [
    ['کد چطور به دستم می‌رسه؟', 'بلافاصله بعد از پرداخت، کد در پنل کاربری شما نمایش داده می‌شه و هر زمان بخواید می‌تونید دوباره ببینیدش.'],
    ['ریجن گیفت کارت مهمه؟', 'بله. کارت‌های «گلوبال» روی همه اکانت‌ها کار می‌کنن، اما بقیه فقط روی اکانت همون ریجن.'],
    ['اگه کد کار نکرد چی؟', 'به پشتیبانی پیام بدید؛ بعد از بررسی، کد جایگزین یا وجه برگشت داده می‌شه.'],
    ['پرداخت امنه؟', 'پرداخت از طریق درگاه رسمی بانکی انجام می‌شه و هیچ اطلاعات کارتی روی سایت ذخیره نمی‌شه.'],
];
if ($catRow) {
    $title = 'خرید گیفت کارت ' . $catRow['name'] . ' با تحویل آنی';
    $desc = $catRow['seo_text'] ?: 'خرید انواع گیفت کارت و شارژ ' . $catRow['name'] . ' با بهترین قیمت، پرداخت ریالی و تحویل آنی کد.';
    $canonical = curl_cat($catRow['slug']);
    $schema = [['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => SITE_NAME, 'item' => $root],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $catRow['name']]]]];
} elseif (!empty($_GET['q'])) {
    $title = 'جستجو: ' . $_GET['q']; $noindex = true;
} else {
    $canonical = '';
    $schema = [
        ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => SITE_NAME, 'url' => $root, 'inLanguage' => 'fa-IR',
         'potentialAction' => ['@type' => 'SearchAction', 'target' => $root . '?q={search_term_string}', 'query-input' => 'required name=search_term_string']],
        ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs)],
        ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => array_map(fn($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => $root . purl($p), 'name' => $p['title']], $hot, array_keys($hot))],
    ];
}
include __DIR__ . '/inc/header.php';
?>

<?php if (!$filtered): ?>
<section class="hero">
  <div class="synth" aria-hidden="true"><div class="sun"></div><div class="floor"></div></div>
  <div class="wrap hero-in">
    <div class="hero-txt">
      <span class="pill"><i></i> ONLINE • تحویل زیر ۱ دقیقه</span>
      <div class="glitch en" data-text="LEVEL UP">LEVEL UP</div>
      <h1>شارژ کن، <span class="grad">برو تو بازی.</span></h1>
      <p>گیفت کارت استیم، پلی‌استیشن، ایکس‌باکس و یوسی و جم بازی‌های موبایل. پرداخت می‌کنی و همون لحظه کدت رو می‌گیری.</p>
      <div class="hero-cta">
        <a href="#shop" class="btn primary"><span class="en">▶</span> شروع خرید</a>
        <a href="#hot" class="btn">پیشنهادهای داغ</a>
      </div>
      <div class="stats">
        <div><b class="en" data-count="12000">0</b><span>سفارش موفق</span></div>
        <div><b class="en" data-count="98" data-suffix="%">0</b><span>رضایت</span></div>
        <div><b class="en">24/7</b><span>پشتیبانی</span></div>
      </div>
    </div>
    <div class="hero-art" aria-hidden="true">
      <div class="hud-ring"></div><div class="hud-ring r2"></div>
      <?php foreach (array_slice($hot, 0, 3) as $i => $h): ?>
        <img class="float f<?= $i ?>" src="<?= img($h) ?>" alt="">
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="ticker" aria-hidden="true"><div class="ticker-in">
  <?php for ($k = 0; $k < 2; $k++) foreach ($hot as $h): ?><span>⚡ <?= e($h['title']) ?> <b><?= fa(number_format($h['price'])) ?></b></span><?php endforeach; ?>
</div></div>

<section class="wrap feats">
  <div><span>⚡</span><b>تحویل آنی</b><small>کد بلافاصله بعد از پرداخت</small></div>
  <div><span>🛡️</span><b>ضمانت اصالت</b><small>کد سالم یا بازگشت وجه</small></div>
  <div><span>💳</span><b>پرداخت ریالی</b><small>با همه کارت‌های شتاب</small></div>
  <div><span>🎧</span><b>پشتیبانی واقعی</b><small>همیشه کنارتیم</small></div>
</section>

<?php if ($deal): ?>
<section class="wrap">
  <div class="deal" style="--a:<?= e($deal['c1']) ?>;--b:<?= e($deal['c2']) ?>">
    <div class="deal-txt">
      <span class="tag en">FLASH DEAL</span>
      <h2><?= e($deal['title']) ?></h2>
      <div class="deal-price"><s><?= fa(number_format($deal['old_price'])) ?></s><b><?= money($deal['price']) ?></b><em><?= fa(round(100 - $deal['price'] * 100 / $deal['old_price'])) ?>٪−</em></div>
      <div class="timer en" data-midnight><span>00</span>:<span>00</span>:<span>00</span></div>
      <div class="hero-cta"><button class="btn primary" data-add="<?= $deal['id'] ?>">افزودن به سبد</button><a class="btn" href="<?= purl($deal) ?>">جزئیات</a></div>
    </div>
    <a href="<?= purl($deal) ?>" class="deal-img"><img src="<?= img($deal) ?>" alt="<?= e($deal['title']) ?>"></a>
  </div>
</section>
<?php endif; ?>

<section class="wrap" id="hot">
  <div class="sec-head"><h2><small class="en">TOP SELLERS</small>پرفروش‌ها</h2></div>
  <div class="rail"><?php foreach ($hot as $p) card($p); ?></div>
</section>

<section class="wrap">
  <div class="sec-head"><h2><small class="en">SELECT PLATFORM</small>پلتفرمت رو انتخاب کن</h2></div>
  <div class="cats">
    <?php foreach ($cats as $c): ?>
      <a href="<?= curl_cat($c['slug']) ?>" class="cat tilt" style="--a:<?= e($c['c1']) ?>;--b:<?= e($c['c2']) ?>">
        <img src="art/c<?= $c['id'] ?>.svg" alt="" loading="lazy"><b><?= e($c['name']) ?></b>
      </a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="wrap" id="shop">
  <div class="sec-head">
    <?php if ($catRow): ?>
      <h1 class="h2"><small class="en">STORE</small>خرید گیفت کارت <?= e($catRow['name']) ?></h1>
    <?php else: ?>
      <h2><small class="en">STORE</small><?= !empty($_GET['q']) ? 'نتایج «' . e($_GET['q']) . '»' : 'همه محصولات' ?></h2>
    <?php endif; ?>
    <?php if ($filtered): ?><a href="./#shop" class="link">نمایش همه ←</a><?php endif; ?>
  </div>
  <?php if ($catRow && $catRow['seo_text']): ?><p class="muted cat-intro"><?= nl2br(e($catRow['seo_text'])) ?></p><?php endif; ?>
  <?php if (!$products): ?>
    <div class="empty">😕 چیزی پیدا نشد. <a href="./">بازگشت</a></div>
  <?php else: ?>
    <div class="grid"><?php foreach ($products as $p) card($p); ?></div>
  <?php endif; ?>
</section>

<?php if (!$filtered): ?>
<section class="wrap how">
  <div class="sec-head"><h2><small class="en">HOW IT WORKS</small>سه مرحله تا بازی</h2></div>
  <div class="steps">
    <div><i class="en">01</i><b>انتخاب کن</b><small>گیفت کارت یا شارژ مورد نظرت</small></div>
    <div><i class="en">02</i><b>پرداخت کن</b><small>درگاه امن بانکی</small></div>
    <div><i class="en">03</i><b>کد رو بگیر</b><small>نمایش آنی + قابل پیگیری</small></div>
  </div>
</section>

<section class="wrap" id="faq">
  <div class="sec-head"><h2><small class="en">FAQ</small>سوالات متداول</h2></div>
  <div class="faq">
    <?php foreach ($faqs as $f): ?><details><summary><?= e($f[0]) ?></summary><p><?= e($f[1]) ?></p></details><?php endforeach; ?>
  </div>
</section>

<section class="wrap seo-text">
  <h2>خرید گیفت کارت استیم، پلی‌استیشن و ایکس‌باکس با تحویل آنی</h2>
  <p><?= e(SITE_NAME) ?> فروشگاه تخصصی محصولات دیجیتال بازی است. اینجا می‌تونید <a href="<?= curl_cat('steam') ?>">گیفت کارت استیم</a> برای شارژ کیف پول Steam، <a href="<?= curl_cat('playstation') ?>">گیفت کارت پلی‌استیشن</a> و اشتراک PS Plus، <a href="<?= curl_cat('xbox') ?>">گیم پس ایکس‌باکس</a> و <a href="<?= curl_cat('mobile') ?>">شارژ بازی‌های موبایل</a> مثل یوسی پابجی، جم فری‌فایر و سی‌پی کالاف دیوتی رو با پرداخت ریالی بخرید.</p>
  <p>همه کدها اورجینال هستند و بلافاصله بعد از پرداخت در پنل کاربری شما نمایش داده می‌شن. قبل از خرید به ریجن اکانتتون دقت کنید؛ کارت‌های گلوبال روی همه اکانت‌ها قابل استفاده‌اند.</p>
</section>
<?php endif; ?>

<?php include __DIR__ . '/inc/footer.php'; ?>
