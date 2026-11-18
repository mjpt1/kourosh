<?php
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
$b = base_path();
echo "User-agent: *\n";
foreach (['/admin/', '/cart.php', '/checkout.php', '/verify.php', '/cart_api.php', '/account', '/login', '/track.php', '/data/', '/*?q='] as $d) echo "Disallow: $b$d\n";
echo "Allow: $b/\n\nSitemap: " . base_url() . "/sitemap.xml\n";
