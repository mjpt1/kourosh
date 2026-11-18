(() => {
  const $ = (s, r = document) => r.querySelector(s), $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const fa = n => String(n).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
  const en = s => String(s).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
  const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ستاره‌های متحرک پس‌زمینه
  const cv = $('#stars');
  if (cv && !calm) {
    const c = cv.getContext('2d'); let W, H, st = [];
    const size = () => { W = cv.width = document.documentElement.clientWidth; H = cv.height = innerHeight; st = Array.from({ length: Math.min(120, W / 10) }, () => ({ x: Math.random() * W, y: Math.random() * H, z: Math.random() * 1.5 + .3, t: Math.random() * 6 })); };
    size(); addEventListener('resize', size);
    let my = 0; addEventListener('scroll', () => my = scrollY, { passive: true });
    (function draw() {
      c.clearRect(0, 0, W, H);
      for (const s of st) {
        s.t += .02; const y = (s.y - my * s.z * .15) % H, yy = y < 0 ? y + H : y;
        c.globalAlpha = .35 + Math.sin(s.t) * .3; c.fillStyle = s.z > 1.3 ? '#9fe8ff' : '#fff';
        c.beginPath(); c.arc(s.x, yy, s.z, 0, 7); c.fill();
      }
      requestAnimationFrame(draw);
    })();
  }

  // افکت سه‌بعدی کارت‌ها (فقط دسکتاپ)
  if (matchMedia('(hover:hover)').matches && !calm) $$('.tilt').forEach(el => {
    el.addEventListener('mousemove', e => {
      const r = el.getBoundingClientRect(), x = (e.clientX - r.left) / r.width - .5, y = (e.clientY - r.top) / r.height - .5;
      el.style.transform = `perspective(800px) rotateY(${x * 10}deg) rotateX(${-y * 10}deg) translateY(-4px)`;
    });
    el.addEventListener('mouseleave', () => el.style.transform = '');
  });

  const toast = msg => { const t = $('#toast'); t.textContent = msg; t.classList.add('show'); clearTimeout(t._h); t._h = setTimeout(() => t.classList.remove('show'), 2200); };
  const setCount = (n, nf) => $$('#cartCount,.tb-count').forEach(b => { b.textContent = nf; b.classList.toggle('hide', !n); b.classList.remove('bump'); void b.offsetWidth; b.classList.add('bump'); });
  const api = (id, qty, mode = 'add') => fetch('cart_api.php', { method: 'POST', body: new URLSearchParams({ id, qty, mode, csrf: window.CSRF }) }).then(r => r.json());

  // افزودن به سبد
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-add]');
    if (b) {
      e.preventDefault();
      const qty = b.dataset.qtyFrom ? en($(b.dataset.qtyFrom).value) || 1 : 1;
      const r = await api(b.dataset.add, qty);
      if (r.ok) { setCount(r.count, r.countFa); toast('✅ به سبد اضافه شد'); fly(b); }
    }
    const bn = e.target.closest('[data-buy-now]');
    if (bn) { e.preventDefault(); await api(bn.dataset.buyNow, en($('#qty')?.value || 1)); location.href = 'cart.php'; }
    const q = e.target.closest('[data-q]');
    if (q) { const i = $('#qty'); i.value = fa(Math.max(1, Math.min(20, (+en(i.value) || 1) + +q.dataset.q))); }
    const s = e.target.closest('[data-set]');
    if (s) {
      const line = s.closest('.line'), inp = $('input', line), n = Math.max(0, Math.min(20, +en(inp.value) + +s.dataset.d));
      const r = await api(s.dataset.set, n, 'set');
      if (r.ok) { if (!n) { line.style.opacity = 0; line.style.transform = 'translateX(-40px)'; } setTimeout(() => location.reload(), n ? 0 : 300); }
    }
    const sh = e.target.closest('[data-show]');
    if (sh) { const b = $('[data-code]', sh.parentNode), on = b.textContent === b.dataset.code; b.textContent = on ? '••••••••••••' : b.dataset.code; sh.textContent = on ? 'نمایش' : 'پنهان'; }
    const cp = e.target.closest('[data-copy]');
    if (cp) { navigator.clipboard?.writeText(cp.dataset.copy).then(() => toast('📋 کپی شد')); }
  });

  // انیمیشن پرواز به سبد
  function fly(from) {
    if (calm) return;
    const to = $('.cart-btn:not([style*="none"])'), tg = (to && to.offsetParent) ? to : $('.tabbar a[href="cart.php"]');
    if (!tg) return;
    const a = from.getBoundingClientRect(), z = tg.getBoundingClientRect(), d = document.createElement('i');
    d.style.cssText = `position:fixed;z-index:200;left:${a.left + a.width / 2}px;top:${a.top}px;width:16px;height:16px;border-radius:50%;background:linear-gradient(135deg,#7b5cff,#00e5ff);box-shadow:0 0 18px #ff3df2;transition:all .7s cubic-bezier(.5,-0.4,.6,1);pointer-events:none`;
    document.body.append(d);
    requestAnimationFrame(() => { d.style.left = z.left + z.width / 2 + 'px'; d.style.top = z.top + 'px'; d.style.transform = 'scale(.3)'; d.style.opacity = .3; });
    setTimeout(() => d.remove(), 750);
  }

  // ظاهر شدن تدریجی هنگام اسکرول
  const io = new IntersectionObserver(es => es.forEach(x => { if (x.isIntersecting) { x.target.classList.add('in'); io.unobserve(x.target); } }), { threshold: .1 });
  $$('.grid .card,.cat,.feats>div,.steps>div,.faq details').forEach((el, i) => { el.classList.add('reveal'); el.style.transitionDelay = (i % 6) * 60 + 'ms'; io.observe(el); });
  // تأخیر فقط برای ورود؛ بعد از آن برای افکت tilt پاک شود
  $$('.reveal').forEach(el => el.addEventListener('transitionend', () => { el.style.transitionDelay = ''; }, { once: true }));

  // شمارنده آمار
  $$('[data-count]').forEach(el => {
    const end = +el.dataset.count, t0 = performance.now();
    const step = t => { const p = Math.min(1, (t - t0) / 1600), v = Math.round(end * (1 - Math.pow(1 - p, 3))); el.textContent = v.toLocaleString('en') + (el.dataset.suffix || (end > 1000 ? '+' : '')); if (p < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  });

  // جشن تحویل سفارش
  const cf = $('.confetti');
  if (cf && !calm) {
    const cols = ['#7b5cff', '#ff3df2', '#00e5ff', '#2ee59d', '#ffc44d'];
    for (let i = 0; i < 90; i++) { const p = document.createElement('i'); p.style.cssText = `left:${Math.random() * 100}%;background:${cols[i % 5]};animation-duration:${2 + Math.random() * 2.5}s;animation-delay:${Math.random() * .8}s`; cf.append(p); }
    setTimeout(() => cf.remove(), 5500);
  }

  // صفحه بوت فقط بار اول هر نشست
  const boot = $('#boot');
  if (boot) { let seen = false; try { seen = sessionStorage.getItem('boot'); sessionStorage.setItem('boot', 1); } catch (e) {}
    if (seen || calm) boot.remove(); else { addEventListener('load', () => setTimeout(() => boot.classList.add('off'), 500)); setTimeout(() => boot.classList.add('off'), 2200); } }

  // شمارش معکوس تا نیمه‌شب
  const tm = $('[data-midnight]');
  if (tm) { const sp = $$('span', tm), tick = () => { const n = new Date(), m = new Date(n); m.setHours(24, 0, 0, 0); const d = Math.max(0, (m - n) / 1000 | 0); [d / 3600 | 0, d / 60 % 60 | 0, d % 60].forEach((v, i) => sp[i].textContent = String(v).padStart(2, '0')); }; tick(); setInterval(tick, 1000); }

  // تب فعال در نوار پایین موبایل
  const path = location.pathname.split('/').pop() || 'index.php', hash = location.hash;
  $$('.tabbar a').forEach(a => { const h = a.getAttribute('href').split('#')[0].replace(/^\.\/$/, 'index.php'); if (h === path || (h === 'index.php' && path === '')) a.classList.add('on'); });
})();

// ===== اسلایدر خودکار پرفروش‌ها با دکمه‌های مثلثی =====
(() => {
  const calm = matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('.rail').forEach(rail => {
    if (rail.closest('.carousel')) return;
    const box = document.createElement('div'); box.className = 'carousel';
    rail.before(box); box.append(rail);
    const mk = (cls, label) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'car-btn ' + cls; b.setAttribute('aria-label', label); box.append(b); return b; };
    const prev = mk('car-prev', 'قبلی'), next = mk('car-next', 'بعدی');
    const dir = getComputedStyle(rail).direction === 'rtl' ? -1 : 1;   // در راست‌به‌چپ، جلو رفتن یعنی حرکت به چپ
    const step = () => { const c = rail.children[0]; return c ? c.getBoundingClientRect().width + parseFloat(getComputedStyle(rail).columnGap || 18) : 280; };
    const atEnd = () => Math.abs(rail.scrollLeft) + rail.clientWidth >= rail.scrollWidth - 6;
    const atStart = () => Math.abs(rail.scrollLeft) < 6;
    const go = n => {
      if (n > 0 && atEnd()) rail.scrollTo({ left: 0, behavior: 'smooth' });
      else if (n < 0 && atStart()) rail.scrollTo({ left: dir * rail.scrollWidth, behavior: 'smooth' });
      else rail.scrollBy({ left: dir * n * step(), behavior: 'smooth' });
    };
    next.onclick = () => { go(1); restart(); };
    prev.onclick = () => { go(-1); restart(); };
    let timer = null, paused = false;
    const start = () => { if (calm || timer) return; timer = setInterval(() => { if (!paused && !document.hidden) go(1); }, 3000); };
    const stop = () => { clearInterval(timer); timer = null; };
    const restart = () => { stop(); start(); };
    // توقف موقع نگه داشتن ماوس یا لمس، ادامه بعد از رها کردن
    box.addEventListener('mouseenter', () => paused = true);
    box.addEventListener('mouseleave', () => paused = false);
    rail.addEventListener('touchstart', () => paused = true, { passive: true });
    rail.addEventListener('touchend', () => setTimeout(() => paused = false, 2500), { passive: true });
    // فقط وقتی در صفحه دیده می‌شود حرکت کند
    new IntersectionObserver(es => es[0].isIntersecting ? start() : stop(), { threshold: .2 }).observe(rail);
  });
})();
