<?php
// متغیرهای سئو که هر صفحه می‌تواند قبل از include تنظیم کند:
// $title, $desc, $canonical (مسیر نسبی), $ogImage (مسیر نسبی), $schema (آرایه JSON-LD), $noindex
$cats = db()->query("SELECT * FROM categories ORDER BY sort")->fetchAll();
$root = base_url() . '/';
$pageTitle = !empty($title) ? $title . ' | ' . SITE_NAME : setting('seo_title') . ' | ' . SITE_NAME;
$pageDesc = mb_substr(trim(preg_replace('/\s+/u', ' ', $desc ?? setting('seo_desc'))), 0, 160);
$canon = $root . ($canonical ?? '');
$ogImg = $root . ($ogImage ?? 'assets/og.png');
$schemas = $schema ?? [];
$schemas[] = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => SITE_NAME, 'url' => $root, 'logo' => $root . 'assets/logo.svg', 'sameAs' => array_values(array_filter([SUPPORT_TELEGRAM]))];
$u = user();
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="fa-IR" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<base href="<?= e(base_path()) ?>/">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, follow"><?php else: ?><meta name="robots" content="index, follow, max-image-preview:large"><?php endif; ?>
<link rel="canonical" href="<?= e($canon) ?>">
<meta property="og:type" content="<?= isset($ogType) ? e($ogType) : 'website' ?>">
<meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
<meta property="og:locale" content="fa_IR">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:url" content="<?= e($canon) ?>">
<meta property="og:image" content="<?= e($ogImg) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#05050c">
<link rel="icon" href="assets/logo.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/logo.svg">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vazirmatn@33.0.3/Vazirmatn-font-face.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Lalezar&family=Orbitron:wght@600;800;900&display=swap">
<link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">
<?php foreach ($schemas as $sc): ?><script type="application/ld+json"><?= json_encode($sc, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
<?php endforeach; ?>
</head>
<body>
<div id="boot" aria-hidden="true"><div><img src="assets/logo.svg" alt=""><b>LOADING</b><i></i></div></div>
<canvas id="stars" aria-hidden="true"></canvas>
<header class="top">
  <div class="wrap top-in">
    <a class="brand" href="./"><img src="assets/logo.svg" alt="<?= e(SITE_NAME) ?>" width="40" height="40"><span><?= e(SITE_NAME) ?></span></a>
    <form class="search" action="./" method="get" role="search">
      <input name="q" placeholder="جستجوی بازی یا گیفت کارت..." value="<?= e($_GET['q'] ?? '') ?>" aria-label="جستجو">
      <button aria-label="جستجو"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button>
    </form>
    <nav class="top-links">
      <a href="<?= $u ? 'account' : 'login' ?>" class="ghost user-btn"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg><?= $u ? e(mb_substr($u['name'], 0, 14)) : 'ورود / ثبت‌نام' ?></a>
      <a href="cart.php" class="cart-btn" aria-label="سبد خرید">
        <svg viewBox="0 0 24 24"><path d="M3 4h2l2.4 11h11l2-8H6.5"/><circle cx="9" cy="19.5" r="1.5"/><circle cx="17" cy="19.5" r="1.5"/></svg>
        <b id="cartCount" class="<?= cart_count() ? '' : 'hide' ?>"><?= fa(cart_count()) ?></b>
      </a>
    </nav>
  </div>
  <nav class="wrap chips" aria-label="دسته‌بندی‌ها">
    <?php foreach ($cats as $c): ?>
      <a class="chip <?= (($_GET['cat'] ?? '') === $c['slug']) ? 'on' : '' ?>" href="<?= curl_cat($c['slug']) ?>" style="--a:<?= e($c['c1']) ?>;--b:<?= e($c['c2']) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<main>
