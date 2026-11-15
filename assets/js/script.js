// DormFinder behaviours. Every feature below is progressive enhancement: pages work without this file.
(() => {
  'use strict';
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const ease = 'cubic-bezier(.22, 1, .36, 1)';
  const store = {
    get(key) { try { return localStorage.getItem(key); } catch (_) { return null; } },
    set(key, value) { try { localStorage.setItem(key, value); } catch (_) {} },
  };

  // Shared keyboard and focus behavior for off-canvas panels. Hidden panels are
  // inert; opening one traps focus, isolates background content, and restores the
  // triggering control on dismissal. Native forms remain inside the panel.
  const mobile = matchMedia('(max-width: 900px)');
  const focusables = (root) => $$('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex="0"]', root)
    .filter((el) => !el.closest('[inert]') && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
  const createDrawer = ({panel, host, className, openers, closers, label}) => {
    if (!panel) return null;
    let opened = false, trigger, muted = [];
    const isolate = () => {
      let branch = panel;
      while (branch.parentElement && branch.parentElement !== document.documentElement) {
        for (const sibling of branch.parentElement.children) {
          if (sibling !== branch && !sibling.matches('.drawer-backdrop, script, style, link') && !sibling.inert) {
            sibling.inert = true; muted.push(sibling);
          }
        }
        branch = branch.parentElement;
      }
    };
    const set = (open, source) => {
      const wasOpen = opened;
      opened = Boolean(open && mobile.matches);
      host.classList.toggle(className, opened);
      openers.forEach((b) => b.setAttribute('aria-expanded', String(opened)));
      panel.inert = mobile.matches && !opened;
      document.body.classList.toggle('overlay-open', opened);
      if (opened && !wasOpen) {
        trigger = source || document.activeElement;
        panel.setAttribute('role', 'dialog'); panel.setAttribute('aria-modal', 'true'); panel.setAttribute('aria-label', label);
        isolate();
        (focusables(panel)[0] || panel).focus({preventScroll: true});
      } else if (!opened) {
        muted.forEach((el) => { el.inert = false; }); muted = [];
        panel.removeAttribute('role'); panel.removeAttribute('aria-modal');
        if (wasOpen && trigger?.isConnected) trigger.focus({preventScroll: true});
      }
    };
    panel.addEventListener('keydown', (e) => {
      if (!opened) return;
      if (e.key === 'Escape') { e.preventDefault(); set(false); }
      if (e.key !== 'Tab') return;
      const controls = focusables(panel), first = controls[0], last = controls.at(-1);
      if (!first) { e.preventDefault(); return; }
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    openers.forEach((b) => b.addEventListener('click', () => set(true, b)));
    closers.forEach((b) => b.addEventListener('click', () => set(false)));
    mobile.addEventListener('change', () => set(false));
    set(false);
    return {close: () => set(false)};
  };

  // ---- Toasts: flash messages slide in, then leave on their own ----
  const dismissToast = (toast) => {
    if (toast.classList.contains('is-leaving')) return;
    toast.classList.add('is-leaving');
    setTimeout(() => toast.remove(), reduce ? 0 : 300);
  };
  const initToast = (toast) => {
    const close = $('.toast-close', toast);
    if (close) { close.hidden = false; close.addEventListener('click', () => dismissToast(toast)); }
    // Messages remain until dismissed, so keyboard and screen-reader users have
    // enough time to read them. They never block form interaction.
  };
  $$('.toast-item').forEach(initToast);
  const toast = (message, type = 'info') => {
    let stack = $('.toast-stack');
    if (!stack) { stack = document.createElement('div'); stack.className = 'toast-stack'; stack.setAttribute('aria-live', 'polite'); document.body.append(stack); }
    const item = document.createElement('div'); item.className = `toast-item toast-${type}`; item.setAttribute('role', 'status');
    const text = document.createElement('span'); text.textContent = message;
    const close = document.createElement('button'); close.type = 'button'; close.className = 'toast-close'; close.setAttribute('aria-label', 'Dismiss message'); close.textContent = '×';
    item.append(text, close); stack.append(item); initToast(item);
  };

  // ---- Mobile navigation ----
  const header = $('[data-site-header]');
  const navToggle = $('.nav-toggle');
  const siteNav = $('#site-nav');
  const setMenu = (open) => {
    if (!header || !navToggle) return;
    header.classList.toggle('menu-open', open);
    navToggle.setAttribute('aria-expanded', String(open));
    if (siteNav) siteNav.inert = mobile.matches && !open;
  };
  navToggle?.addEventListener('click', () => setMenu(navToggle.getAttribute('aria-expanded') !== 'true'));
  $$('#site-nav a').forEach((link) => link.addEventListener('click', () => setMenu(false)));
  mobile.addEventListener('change', () => setMenu(false));
  document.addEventListener('click', (event) => { if (header && !header.contains(event.target)) setMenu(false); });

  // ---- Compare shortlist (kept in the browser) ----
  const key = 'dormfinder-compare';
  let selected = [];
  try { const value = JSON.parse(store.get(key) || '[]'); if (Array.isArray(value)) selected = [...new Set(value.map(Number).filter((n) => Number.isInteger(n) && n > 0))].slice(0, 3); } catch (_) {}
  const comparePage = $('#comparison-page');
  if (comparePage && !new URLSearchParams(location.search).has('ids') && selected.length) location.replace(`${location.pathname}?ids=${selected.join(',')}`);
  if (comparePage?.dataset.selected) selected = comparePage.dataset.selected.split(',').map(Number).filter(Boolean).slice(0, 3);
  $$('.compare-button').forEach((button) => { const label = $('span', button); button.dataset.label = label ? label.textContent : button.textContent.trim(); });
  const syncCompare = () => {
    store.set(key, JSON.stringify(selected));
    $$('.compare-button').forEach((button) => {
      const active = selected.includes(Number(button.dataset.id));
      const label = $('span', button), icon = $('i', button);
      if (label) label.textContent = active ? 'Added to compare' : button.dataset.label;
      if (icon) { icon.classList.toggle('bi-plus-circle', !active); icon.classList.toggle('bi-check-circle-fill', active); }
      button.setAttribute('aria-pressed', String(active));
    });
    const count = $('#compare-count');
    if (count) count.textContent = selected.length ? `(${selected.length})` : '';
    $$('a[href*="compare.php"]').forEach((a) => { a.href = `${a.href.split('?')[0]}${selected.length ? `?ids=${selected.join(',')}` : ''}`; });
  };
  $$('.compare-button').forEach((button) => button.addEventListener('click', () => {
    const id = Number(button.dataset.id);
    if (selected.includes(id)) selected = selected.filter((n) => n !== id);
    else if (selected.length < 3) selected.push(id);
    else { toast('You can compare up to three places. Remove one from your shortlist first.', 'warning'); return; }
    syncCompare();
  }));
  $('#clear-compare')?.addEventListener('click', () => { selected = []; syncCompare(); location.href = location.pathname; });
  syncCompare();

  // ---- Submit feedback: prevent double posts and show a spinner on .btn submit buttons ----
  $$('form[method="post"], form[role="search"], #filter-form').forEach((form) => form.addEventListener('submit', (event) => {
    if (form.getAttribute('aria-busy') === 'true') { event.preventDefault(); return; }
    form.setAttribute('aria-busy', 'true');
    // Keep named submit buttons enabled: their values select the server-side action.
    const button = event.submitter || $('button.btn:not([name])', form);
    if (!button || button.disabled) return;
    if (button.classList.contains('fav-btn') && !reduce) button.animate([{transform:'scale(1)'},{transform:'scale(1.2)'},{transform:'scale(1)'}], {duration: 280});
    if (button.name || !button.classList.contains('btn')) return;
    button.classList.add('is-loading');
    const spinner = document.createElement('span'); spinner.className = 'spinner'; spinner.setAttribute('aria-hidden', 'true');
    button.prepend(spinner);
    setTimeout(() => { button.disabled = true; }, 0);
  }));
  // Coming back with the Back button restores the page from cache: undo the loading state.
  addEventListener('pageshow', (e) => {
    if (!e.persisted) return;
    $$('form[aria-busy]').forEach((form) => form.removeAttribute('aria-busy'));
    $$('.btn.is-loading').forEach((button) => { button.disabled = false; button.classList.remove('is-loading'); $('.spinner', button)?.remove(); });
  });

  // ---- Password visibility toggle (button ships hidden; only shown when this script runs) ----
  $$('[data-toggle-password]').forEach((button) => {
    const input = $(`#${button.dataset.togglePassword}`);
    if (!input) return;
    button.hidden = false;
    button.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      button.setAttribute('aria-pressed', String(show));
      button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      $('i', button).className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  });

  // ---- Filter drawer (mobile) ----
  const layout = $('[data-filter-layout]');
  if (layout) {
    createDrawer({panel: $('.filter-drawer', layout), host: layout, className: 'drawer-open',
      openers: $$('[data-filter-open]'), closers: $$('[data-filter-close]', layout), label: 'Search filters'});
  }

  // ---- Accordions: <details> works alone; this adds a smooth open/close ----
  $$('details.accordion-item').forEach((details) => {
    const summary = $('summary', details), body = $('.accordion-body', details);
    if (!summary || !body || reduce || !body.animate) return;
    let running = null, intendedOpen = details.open;
    summary.addEventListener('click', (e) => {
      e.preventDefault();
      if (running) { running.onfinish = null; running.cancel(); }
      const opening = intendedOpen = !intendedOpen;
      if (opening) details.open = true;
      const height = body.scrollHeight;
      running = body.animate(
        { height: opening ? ['0px', `${height}px`] : [`${height}px`, '0px'], opacity: opening ? [0, 1] : [1, 0] },
        { duration: opening ? 320 : 240, easing: ease },
      );
      running.onfinish = () => { if (!opening) details.open = false; running = null; };
    });
  });

  // ---- Lightbox for listing photos ----
  const photos = $$('[data-lightbox]');
  if (photos.length && 'HTMLDialogElement' in window) {
    let index = 0, dialog, image, counter, origin;
    const build = () => {
      dialog = document.createElement('dialog'); dialog.className = 'lightbox'; dialog.setAttribute('aria-label', 'Photo viewer');
      image = document.createElement('img'); image.alt = '';
      counter = document.createElement('span'); counter.className = 'lightbox-count'; counter.setAttribute('aria-live', 'polite');
      const mk = (cls, label, icon) => { const b = document.createElement('button'); b.type = 'button'; b.className = `lightbox-btn ${cls}`; b.setAttribute('aria-label', label); const i = document.createElement('i'); i.className = `bi ${icon}`; i.setAttribute('aria-hidden', 'true'); b.append(i); return b; };
      const close = mk('lightbox-close', 'Close photo viewer', 'bi-x-lg'), prev = mk('lightbox-prev', 'Previous photo', 'bi-chevron-left'), next = mk('lightbox-next', 'Next photo', 'bi-chevron-right');
      close.addEventListener('click', () => dialog.close());
      dialog.addEventListener('close', () => { document.body.classList.remove('overlay-open'); origin?.focus({preventScroll: true}); });
      prev.addEventListener('click', () => show(index - 1)); next.addEventListener('click', () => show(index + 1));
      dialog.addEventListener('click', (e) => { if (e.target === dialog) dialog.close(); });
      dialog.addEventListener('keydown', (e) => { if (e.key === 'ArrowLeft') show(index - 1); if (e.key === 'ArrowRight') show(index + 1); });
      dialog.append(image, close, prev, next, counter); document.body.append(dialog);
      if (photos.length < 2) { prev.hidden = true; next.hidden = true; }
    };
    const show = (n) => {
      index = (n + photos.length) % photos.length;
      image.src = photos[index].href; image.alt = $('img', photos[index])?.alt || 'Listing photo';
      if (!reduce) image.animate([{opacity:.3,transform:'scale(.985)'},{opacity:1,transform:'none'}], {duration:250,easing:ease});
      counter.textContent = `${index + 1} / ${photos.length}`;
    };
    photos.forEach((link, n) => link.addEventListener('click', (e) => {
      if (e.metaKey || e.ctrlKey || e.shiftKey) return;
      e.preventDefault(); origin = link; if (!dialog) build(); show(n); dialog.showModal(); document.body.classList.add('overlay-open');
    }));
  }

  // ---- Dashboard shell: collapsible sidebar, off-canvas on mobile, profile menu ----
  const collapseKey = 'dormfinder-sidebar';
  if (store.get(collapseKey) === 'collapsed') document.body.classList.add('sidebar-collapsed');
  const collapseButton = $('[data-sidebar-collapse]');
  const syncCollapse = () => {
    const collapsed = document.body.classList.contains('sidebar-collapsed');
    collapseButton?.setAttribute('aria-expanded', String(!collapsed));
    collapseButton?.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    if (collapseButton) collapseButton.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
  };
  syncCollapse();
  $('[data-sidebar-collapse]')?.addEventListener('click', (e) => {
    const collapsed = document.body.classList.toggle('sidebar-collapsed');
    store.set(collapseKey, collapsed ? 'collapsed' : 'open');
    syncCollapse();
  });
  createDrawer({panel: $('#app-sidebar'), host: document.body, className: 'sidebar-open',
    openers: $$('[data-sidebar-open]'), closers: $$('[data-sidebar-backdrop], [data-sidebar-close]'), label: 'Dashboard navigation'});
  const profile = $('.profile-menu');
  if (profile) {
    const button = $('.profile-btn', profile);
    const setProfile = (open) => { profile.classList.toggle('open', open); button.setAttribute('aria-expanded', String(open)); $('.profile-dropdown',profile).inert = !open; };
    setProfile(false);
    button.addEventListener('click', (e) => { e.stopPropagation(); setProfile(!profile.classList.contains('open')); });
    document.addEventListener('click', (e) => { if (!profile.contains(e.target)) setProfile(false); });
    profile.addEventListener('focusout', (e) => { if (!profile.contains(e.relatedTarget)) setProfile(false); });
  }
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (header?.classList.contains('menu-open')) { setMenu(false); navToggle.focus(); }
    if (profile?.classList.contains('open')) { profile.classList.remove('open'); $('.profile-dropdown',profile).inert = true; $('.profile-btn', profile).setAttribute('aria-expanded', 'false'); $('.profile-btn', profile).focus(); }
  });
  // Enable collapsible controls only after their handlers exist. If this file is
  // missing, the ordinary navigation and filter forms stay visible and usable.
  document.documentElement.classList.add('js');
  setMenu(false);
})();
