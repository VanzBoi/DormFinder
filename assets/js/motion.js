// DormFinder motion: header state, scroll reveal, count-up. Pure IntersectionObserver, no scroll listeners.
(() => {
  'use strict';
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  let reduce = preference.matches;
  preference.addEventListener('change', (event) => { reduce = event.matches; });
  const hasIO = 'IntersectionObserver' in window;

  // Header turns into a compact glass bar once the page leaves the top.
  const header = document.querySelector('[data-site-header]');
  const sentinel = document.querySelector('[data-scroll-sentinel]');
  if (header && sentinel && hasIO) {
    new IntersectionObserver(([entry]) => header.classList.toggle('is-scrolled', !entry.isIntersecting)).observe(sentinel);
  }

  // Count-up for dashboard numbers. The real value stays in the markup; JS animates 0 -> value when visible.
  const format = new Intl.NumberFormat('en-PH');
  const countUp = (el) => {
    const target = Number(el.dataset.countTo);
    if (!Number.isFinite(target) || reduce) return;
    const start = performance.now(), duration = 700;
    const tick = (now) => {
      const t = reduce ? 1 : Math.min(1, (now - start) / duration);
      el.textContent = format.format(Math.round(target * (1 - Math.pow(1 - t, 3))));
      if (t < 1) requestAnimationFrame(tick); else el.textContent = format.format(target);
    };
    el.textContent = '0';
    requestAnimationFrame(tick);
  };

  const reveal = [...document.querySelectorAll('[data-reveal]')];
  const counters = [...document.querySelectorAll('[data-count-to]')];
  if (!hasIO || reduce) {
    reveal.forEach((el) => el.classList.add('is-visible'));
    return;
  }

  // Content is visible by default. Only animate when it approaches the viewport;
  // a missing observer or a failed module can never leave an empty section.
  const revealObserver = new IntersectionObserver((entries) => {
    entries.filter((e) => e.isIntersecting).forEach((entry, i) => {
      entry.target.style.setProperty('--reveal-delay', `${Math.min(i, 3) * 60}ms`);
      entry.target.classList.add('is-visible');
      revealObserver.unobserve(entry.target);
    });
  }, { threshold: 0, rootMargin: '0px 0px 40px 0px' });
  reveal.forEach((el) => revealObserver.observe(el));

  const countObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      countUp(entry.target);
      countObserver.unobserve(entry.target);
    });
  }, { threshold: 0.4 });
  counters.forEach((el) => countObserver.observe(el));
  document.documentElement.classList.add('motion-ready');
})();
