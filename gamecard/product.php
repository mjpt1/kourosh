<?php
require __DIR__ . '/config.php';
$s = db()->prepare("SELECT p.*, c.name cat_name, c.slug, c.c1, c.c2 FROM products p JOIN categories c ON c.id=p.cat_id WHERE p.id=? AND p.active=1");
$s->execute([(int)($_GET['id'] ?? 0)]); $p = $s->fetch();
if (!$p) { http_response_code(404); $title = 'محصول پیدا نشد'; $noindex = true; include __DIR__ . '/inc/header.php'; echo '<section class="wrap"><div class="box empty big"><div class="empty-ico en">404</div>این محصول پیدا نشد.<a class="btn primary" href="./#shop">رفتن به فروشگاه</a></div></section>'; include __DIR__ . '/inc/footer.php'; exit; }
// اگر آدرس قدیمی/ناقص بود، با 301 به آدرس اصلی برو (سئو)
if (basename($_SERVER['SCRIPT_NAME']) === 'product.php' && !preg_match('#/product/\d+/#', $_SERVER['REQUEST_URI'])) { header('Location: ' . base_url() . '/' . purl($p), true, 301); exit; }
[$rc, $rl] = rarity($p['price']);
$title = 'خرید ' . $p['title'] . ' (' . $p['subtitle'] . ')';
$desc = 'خرید ' . $p['title'] . ' ریجن ' . $p['region'] . ' با تحویل آنی و ضمانت اصالت. ' . $p['description'];
$canonical = purl($p); $ogImage = img($p); $ogType = 'product';
$root = base_url() . '/';
$schema = [
    ['@context' => 'https://schema.org', '@type' => 'Product', 'name' => $p['title'], 'alternateName' => $p['subtitle'],
     'image' => $root . img($p), 'description' => $p['description'], 'sku' => 'GC-' . $p['id'], 'category' => $p['cat_name'],
     'brand' => ['@type' => 'Brand', 'name' => $p['cat_name']],
     'offers' => ['@type' => 'Offer', 'url' => $root . purl($p), 'priceCurrency' => 'IRR', 'price' => $p['price'] * 10,
                  'availability' => 'https://schema.org/InStock', 'itemCondition' => 'https://schema.org/NewCondition',
                  'seller' => ['@type' => 'Organization', 'name' => SITE_NAME]]],
    ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => SITE_NAME, 'item' => $root],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $p['cat_name'], 'item' => $root . curl_cat($p['slug'])],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $p['title']]]],
];
$rel = db()->prepare("SELECT p.*, c.c1, c.c2 FROM products p JOIN categories c ON c.id=p.cat_id WHERE p.cat_id=? AND p.id<>? AND p.active=1 LIMIT 4");
$rel->execute([$p['cat_id'], $p['id']]); $rel = $rel->fetchAll();
include __DIR__ . '/inc/header.php';
?>
<section class="wrap pd" style="--a:<?= e($p['c1']) ?>;--b:<?= e($p['c2']) ?>">
  <div class="pd-img tilt"><img src="<?= img($p) ?>" alt="<?= e($p['title']) ?>" width="400" height="250" fetchpriority="high"><span class="rar big en <?= $rc ?>"><?= $rl ?></span></div>
  <div class="pd-info">
    <nav class="crumb" aria-label="مسیر"><a href="./">خانه</a> / <a href="<?= curl_cat($p['slug']) ?>"><?= e($p['cat_name']) ?></a></nav>
    <h1><?= e($p['title']) ?></h1>
    <p class="muted"><?= e($p['subtitle']) ?></p>
    <div class="tags"><span>🌍 ریجن: <?= e($p['region']) ?></span><span>⚡ تحویل آنی</span><span>🛡️ ضمانت اصالت</span></div>
    <div class="pd-price">
      <?php if ($p['old_price'] > $p['price']): ?><s><?= fa(number_format($p['old_price'])) ?></s><?php endif; ?>
      <b><?= money($p['price']) ?></b>
    </div>
    <div class="buy">
      <div class="qty"><button data-q="-1">−</button><input id="qty" value="1" inputmode="numeric"><button data-q="1">+</button></div>
      <button class="btn primary grow" data-add="<?= $p['id'] ?>" data-qty-from="#qty">افزودن به سبد</button>
    </div>
    <a class="btn block" href="cart.php" data-buy-now="<?= $p['id'] ?>">خرید فوری</a>
    <div class="desc"><h3>توضیحات</h3><p><?= nl2br(e($p['description'])) ?></p></div>
  </div>
</section>
<?php if ($rel): ?>
<section class="wrap">
  <div class="sec-head"><h2>محصولات مشابه</h2></div>
  <div class="rail">
  <?php foreach ($rel as $r): ?>
    <a class="card mini tilt" href="<?= purl($r) ?>"><img src="<?= img($r) ?>" alt="" loading="lazy"><b><?= e($r['title']) ?></b><span class="price"><?= money($r['price']) ?></span></a>
  <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>
<?php include __DIR__ . '/inc/footer.php'; ?>
