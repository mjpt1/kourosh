<?php
// تصویر اختصاصی هر محصول/دسته به صورت SVG — فقط متن لاتین تا در همه مرورگرها درست نمایش داده شود
require __DIR__ . '/config.php';
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=86400');

$isCat = isset($_GET['cat']);
if ($isCat) {
    $s = db()->prepare("SELECT * FROM categories WHERE id=?"); $s->execute([(int)$_GET['cat']]); $c = $s->fetch();
} else {
    $s = db()->prepare("SELECT p.*, c.c1, c.c2, c.icon, c.slug FROM products p JOIN categories c ON c.id=p.cat_id WHERE p.id=?");
    $s->execute([(int)($_GET['id'] ?? 0)]); $c = $s->fetch();
}
if (!$c) $c = ['c1' => '#7b5cff', 'c2' => '#00d4ff', 'icon' => 'pc', 'id' => 1, 'slug' => 'pc'];
// اگر مرورگر نسخه فعلی را دارد، دوباره نفرست
$etag = '"' . md5(json_encode($c) . filemtime(__FILE__)) . '"';
header('ETag: ' . $etag);
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
$c1 = $c['c1']; $c2 = $c['c2']; $seed = (int)$c['id'];
$brandNames = ['steam' => 'STEAM', 'ps' => 'PLAYSTATION', 'xbox' => 'XBOX', 'mobile' => 'MOBILE', 'pc' => 'PC GAMES', 'switch' => 'NINTENDO'];
$brand = $brandNames[$c['icon']] ?? 'GAME';

// جدا کردن مبلغ/مقدار (مثلاً $5 یا 660 UC) برای نمایش درشت
$big = ''; $small = '';
if (!$isCat) {
    $sub = $c['subtitle'];
    if (preg_match('/\$\s?\d+/', $sub, $m)) { $big = str_replace(' ', '', $m[0]); $small = trim(preg_replace('/\$\s?\d+|\bUS\b/', '', $sub)); }
    elseif (preg_match('/(\d[\d,]*)\s*(.*)$/', $sub, $m)) { $big = $m[1]; $small = trim(preg_replace('/\d[\d,]*.*$/', '', $sub) . ' ' . $m[2]); }
    else { $small = $sub; }
    $small = strtoupper($small);
}
$x = fn($s) => htmlspecialchars($s, ENT_XML1);

$icons = [
 'steam'  => '<circle r="44" fill="none" stroke="#fff" stroke-width="8"/><circle cx="20" cy="-13" r="13" fill="#fff"/><circle cx="-19" cy="17" r="8" fill="#fff"/><path d="M-19 17 L20 -13" stroke="#fff" stroke-width="7" stroke-linecap="round"/>',
 'ps'     => '<path d="M-56 10 q0-34 30-34 h52 q30 0 30 34 l6 26 q4 18-12 18 q-10 0-18-12 l-8-12 h-42 l-8 12 q-8 12-18 12 q-16 0-12-18z" fill="#fff"/><circle cx="24" cy="0" r="6" fill="'.$c1.'"/><circle cx="36" cy="10" r="6" fill="'.$c1.'"/><rect x="-36" y="-4" width="22" height="7" rx="3" fill="'.$c1.'"/><rect x="-29" y="-11" width="7" height="22" rx="3" fill="'.$c1.'"/>',
 'xbox'   => '<circle r="46" fill="#fff"/><path d="M-29 -29 Q0 -6 29 -29 M-33 29 Q0 -8 33 29" stroke="'.$c1.'" stroke-width="10" fill="none" stroke-linecap="round"/>',
 'mobile' => '<rect x="-30" y="-52" width="60" height="104" rx="12" fill="#fff"/><rect x="-22" y="-40" width="44" height="72" rx="4" fill="'.$c1.'"/><path d="M-6 -14 l18 10 l-18 10z" fill="#fff"/><circle cy="42" r="4" fill="'.$c1.'"/>',
 'pc'     => '<rect x="-56" y="-40" width="112" height="70" rx="8" fill="#fff"/><rect x="-46" y="-30" width="92" height="50" rx="3" fill="'.$c1.'"/><path d="M-14 30 h28 l6 18 h-40z" fill="#fff"/><path d="M-18 -16 l14 10 l-14 10 M2 6 h18" stroke="#fff" stroke-width="5" fill="none" stroke-linecap="round"/>',
 'switch' => '<rect x="-52" y="-44" width="40" height="88" rx="18" fill="#fff"/><rect x="12" y="-44" width="40" height="88" rx="18" fill="#fff" opacity=".85"/><circle cx="-32" cy="-18" r="8" fill="'.$c1.'"/><circle cx="32" cy="16" r="8" fill="'.$c1.'"/>',
];
$icon = $icons[$c['icon']] ?? $icons['pc'];

// شبکه شش‌ضلعی و ذرات ثابت بر اساس seed
mt_srand($seed * 7919 + ($isCat ? 3 : 0));
$hex = '';
for ($r = 0; $r < 7; $r++) for ($q = 0; $q < 12; $q++) {
    if (mt_rand(0, 100) > 30) continue;
    $cx = $q * 36 + ($r % 2) * 18; $cy = $r * 31 + 10; $o = mt_rand(6, 22) / 100;
    $hex .= sprintf('<path d="M%1$d %2$dl15 -9l15 9v17l-15 9l-15 -9z" fill="#fff" opacity="%3$.2f"/>', $cx - 15, $cy, $o);
}
$sparks = '';
for ($i = 0; $i < 16; $i++) $sparks .= sprintf('<circle cx="%d" cy="%d" r="%.1f" fill="#fff" opacity="%.2f"/>', mt_rand(0, 400), mt_rand(0, 250), mt_rand(6, 22) / 10, mt_rand(20, 70) / 100);
$g = 'a' . $seed . ($isCat ? 'c' : 'p');
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 250">
<defs>
 <linearGradient id="<?=$g?>" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="<?=$c1?>"/><stop offset="1" stop-color="<?=$c2?>"/></linearGradient>
 <linearGradient id="<?=$g?>d" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#05050c" stop-opacity="0"/><stop offset="1" stop-color="#05050c" stop-opacity=".85"/></linearGradient>
 <radialGradient id="<?=$g?>s" cx=".78" cy=".3" r=".55"><stop offset="0" stop-color="#fff" stop-opacity=".55"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></radialGradient>
 <filter id="<?=$g?>f" x="-50%" y="-50%" width="200%" height="200%"><feGaussianBlur stdDeviation="7" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
 <clipPath id="<?=$g?>k"><path d="M18 0H400V232L382 250H0V18Z"/></clipPath>
</defs>
<g clip-path="url(#<?=$g?>k)">
 <rect width="400" height="250" fill="#0a0a18"/>
 <rect width="400" height="250" fill="url(#<?=$g?>)" opacity=".92"/>
 <?=$hex?>
 <circle cx="312" cy="80" r="150" fill="url(#<?=$g?>s)"/>
 <path d="M0 250 L120 120 L170 170 L250 70 L400 210 V250Z" fill="#000" opacity=".16"/>
 <path d="M0 0 L400 0" stroke="#fff" stroke-opacity=".0"/>
 <?=$sparks?>
 <g stroke="#fff" stroke-opacity=".35" stroke-width="1.5" fill="none"><path d="M0 60 H50 L64 46 H120"/><path d="M400 200 H350 L336 214 H290"/></g>
 <rect width="400" height="250" fill="url(#<?=$g?>d)"/>
 <g transform="translate(<?= $isCat ? '200 112' : '318 92' ?>) scale(<?= $isCat ? 1.15 : .78 ?>)" filter="url(#<?=$g?>f)"><?=$icon?></g>
 <?php if ($isCat): ?>
 <text x="200" y="222" text-anchor="middle" font-family="Arial Black,Impact,Segoe UI,sans-serif" font-weight="900" font-size="22" letter-spacing="6" fill="#fff"><?=$x($brand)?></text>
 <?php else: ?>
 <text x="22" y="40" font-family="Segoe UI,Arial,sans-serif" font-weight="700" font-size="12" letter-spacing="3" fill="#fff" opacity=".85"><?=$x($brand)?> • GIFT CARD</text>
 <?php if ($big): ?><text x="20" y="178" font-family="Arial Black,Impact,Segoe UI,sans-serif" font-weight="900" font-size="<?= strlen($big) > 5 ? 50 : 64 ?>" fill="#fff" filter="url(#<?=$g?>f)"><?=$x($big)?></text><?php endif; ?>
 <text x="22" y="214" font-family="Segoe UI,Arial,sans-serif" font-weight="800" font-size="17" letter-spacing="2" fill="#fff"><?=$x($small)?></text>
 <rect x="22" y="225" width="70" height="4" fill="#fff" opacity=".8"/>
 <?php endif; ?>
</g>
<path d="M18 1.5H398.5V231.5L381.5 248.5H1.5V18.5Z" fill="none" stroke="#fff" stroke-opacity=".35" stroke-width="3"/>
</svg>
