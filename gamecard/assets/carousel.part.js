
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
