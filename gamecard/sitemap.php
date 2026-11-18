<?php
require __DIR__ . '/config.php';
header('Content-Type: application/xml; charset=utf-8');
$root = base_url() . '/';
$today = date('Y-m-d');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
  <url><loc><?= e($root) ?></loc><lastmod><?= $today ?></lastmod><changefreq>daily</changefreq><priority>1.0</priority></url>
<?php foreach (db()->query("SELECT slug FROM categories ORDER BY sort") as $c): ?>
  <url><loc><?= e($root . curl_cat($c['slug'])) ?></loc><lastmod><?= $today ?></lastmod><changefreq>daily</changefreq><priority>0.9</priority></url>
<?php endforeach; ?>
<?php foreach (db()->query("SELECT id, title FROM products WHERE active=1") as $p): ?>
  <url><loc><?= e($root . purl($p)) ?></loc><lastmod><?= $today ?></lastmod><changefreq>weekly</changefreq><priority>0.8</priority>
    <image:image><image:loc><?= e($root . img($p)) ?></image:loc><image:title><?= e($p['title']) ?></image:title></image:image></url>
<?php endforeach; ?>
</urlset>
