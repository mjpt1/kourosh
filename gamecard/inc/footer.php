</main>
<footer class="foot">
  <div class="wrap foot-in">
    <div>
      <a class="brand" href="./"><img src="assets/logo.svg" alt="" width="40" height="40" loading="lazy"><span><?= e(SITE_NAME) ?></span></a>
      <p><?= e(SITE_TAGLINE) ?>. همه محصولات دیجیتال هستند و کد بلافاصله بعد از پرداخت در پنل کاربری شما نمایش داده می‌شود.</p>
      <?php if (setting('phone')): ?><p>📞 <a href="tel:<?= e(setting('phone')) ?>" class="en"><?= e(setting('phone')) ?></a></p><?php endif; ?>
    </div>
    <nav class="foot-links" aria-label="دسته‌ها">
      <b>دسته‌بندی‌ها</b>
      <?php foreach ($cats as $c): ?><a href="<?= curl_cat($c['slug']) ?>">خرید <?= e($c['name']) ?></a><?php endforeach; ?>
    </nav>
    <nav class="foot-links" aria-label="دسترسی سریع">
      <b>دسترسی سریع</b>
      <a href="account">پنل کاربری</a>
      <a href="track.php">پیگیری سفارش</a>
      <a href="<?= e(SUPPORT_TELEGRAM) ?>" target="_blank" rel="noopener">پشتیبانی تلگرام</a>
      <a href="./#faq">سوالات متداول</a>
    </nav>
    <?php if (setting('enamad')): ?><div class="trust"><?= setting('enamad') ?></div><?php endif; ?>
  </div>
  <div class="wrap copyr">© <?= fa(date('Y')) ?> <?= e(SITE_NAME) ?> — تمامی حقوق محفوظ است.</div>
</footer>
<nav class="tabbar" aria-label="منوی موبایل">
  <a href="./"><svg viewBox="0 0 24 24"><path d="M3 11 12 4l9 7v9h-6v-6H9v6H3z"/></svg>خانه</a>
  <a href="./#shop"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/></svg>فروشگاه</a>
  <a href="cart.php"><svg viewBox="0 0 24 24"><path d="M3 4h2l2.4 11h11l2-8H6.5"/><circle cx="9" cy="19.5" r="1.5"/><circle cx="17" cy="19.5" r="1.5"/></svg>سبد<b class="tb-count <?= cart_count() ? '' : 'hide' ?>"><?= fa(cart_count()) ?></b></a>
  <a href="<?= user() ? 'account' : 'login' ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/></svg>حساب من</a>
</nav>
<div id="toast" role="status" aria-live="polite"></div>
<script>window.CSRF = <?= json_encode(csrf()) ?>;</script>
<script src="assets/app.js?v=<?= filemtime(__DIR__ . '/../assets/app.js') ?>" defer></script>
</body>
</html>
