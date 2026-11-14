(() => {
  'use strict';
  const toggle = document.querySelector('.nav-toggle');
  toggle?.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(open));
    document.querySelector('#site-nav')?.classList.toggle('open', open);
  });
  const key = 'dormfinder-compare';
  let selected = [];
  try { const value = JSON.parse(localStorage.getItem(key) || '[]'); if (Array.isArray(value)) selected = [...new Set(value.map(Number).filter(n => Number.isInteger(n) && n > 0))].slice(0, 3); } catch (_) {}
  const page = document.querySelector('#comparison-page');
  if (page && !new URLSearchParams(location.search).has('ids') && selected.length) {
    location.replace(`${location.pathname}?ids=${selected.join(',')}`);
  }
  if (page?.dataset.selected) selected = page.dataset.selected.split(',').map(Number).filter(Boolean).slice(0, 3);
  const sync = () => {
    try { localStorage.setItem(key, JSON.stringify(selected)); } catch (_) {}
    document.querySelectorAll('.compare-button').forEach(button => {
      const active = selected.includes(Number(button.dataset.id));
      button.textContent = active ? '✓ Added to compare' : '+ Compare';
      button.setAttribute('aria-pressed', String(active));
    });
    const count = document.querySelector('#compare-count');
    if (count) count.textContent = selected.length ? `(${selected.length})` : '';
    document.querySelectorAll('a[href*="compare.php"]').forEach(a => { a.href = `${a.href.split('?')[0]}${selected.length ? `?ids=${selected.join(',')}` : ''}`; });
  };
  document.querySelectorAll('.compare-button').forEach(button => button.addEventListener('click', () => {
    const id = Number(button.dataset.id);
    if (selected.includes(id)) selected = selected.filter(n => n !== id);
    else if (selected.length < 3) selected.push(id);
    else { alert('You can compare up to three places. Remove one from your shortlist first.'); return; }
    sync();
  }));
  document.querySelector('#clear-compare')?.addEventListener('click', () => { selected = []; sync(); location.href = location.pathname; });
  sync();
  document.querySelectorAll('form[method="post"]').forEach(form => form.addEventListener('submit', () => {
    // Keep named submit buttons enabled: their values select the server-side action.
    form.setAttribute('aria-busy', 'true');
    const button = form.querySelector('button:not([name])');
    if (button) { button.textContent = 'Saving…'; button.disabled = true; }
  }));
})();
