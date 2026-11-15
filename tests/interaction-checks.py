"""Interaction checks for the redesigned UI: menus, drawer, lightbox, accordion, count-up, sidebar, reduced motion, no-JS, overflow.
Run against a FRESH database (it unlocks a listing and signs in as all three demo roles):
    python tests/interaction-checks.py http://127.0.0.1:8000
Requires Python Playwright (see README) and installed Chrome. Screenshots go to tests/screenshots/interactions.
"""
import sys
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tests/.runtime/python"))
from playwright.sync_api import sync_playwright
BASE = sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8000"
_out = ROOT / "tests/screenshots/interactions"; _out.mkdir(parents=True, exist_ok=True)
OUT = str(_out)
res = []
def check(ok, label, detail=""):
    res.append(ok); print(("PASS  " if ok else "FAIL  ") + label + (f"  -- {detail}" if detail and not ok else ""))
def login(pg, email):
    pg.goto(BASE + "/auth/login.php"); pg.fill("#email", email); pg.fill("#password", "DemoPass2026!"); pg.click("form button.btn-primary"); pg.wait_for_load_state()

with sync_playwright() as p:
    b = p.chromium.launch(channel="chrome", headless=True)
    errors = []
    def new(width=1280, height=900, **kw):
        ctx = b.new_context(viewport={"width": width, "height": height}, **kw); pg = ctx.new_page()
        pg.on("pageerror", lambda e: errors.append(f"pageerror: {e}"))
        pg.on("console", lambda m: errors.append(f"console.{m.type}: {m.text}") if m.type == "error" else None)
        return ctx, pg

    # --- mobile navigation
    ctx, pg = new(375); pg.goto(BASE + "/"); pg.wait_for_load_state("networkidle")
    nav = pg.locator("#site-nav"); tog = pg.locator(".nav-toggle")
    check(not nav.is_visible(), "Mobile menu is hidden at first")
    tog.click(); pg.wait_for_timeout(500)
    check(tog.get_attribute("aria-expanded") == "true" and nav.is_visible(), "Hamburger opens the slide-down menu and sets aria-expanded")
    pg.screenshot(path=f"{OUT}/mobile-menu-375.png")
    check(pg.locator("#site-nav a:has-text('Explore dorms')").is_visible(), "Menu links are reachable")
    pg.keyboard.press("Escape"); pg.wait_for_timeout(500)
    check(tog.get_attribute("aria-expanded") == "false" and not nav.is_visible(), "Escape closes the menu")
    check(pg.evaluate("document.activeElement === document.querySelector('.nav-toggle')"), "Closing mobile navigation returns focus to its button")
    check(pg.locator('#site-nav').evaluate('(el) => el.inert'), "Closed mobile navigation is absent from the tab order")
    ctx.close()

    # --- One script failing must not hide content or leave dead mobile controls.
    ctx, pg = new(375)
    ctx.route('**/assets/js/motion.js', lambda route: route.fulfill(status=200, content_type='text/javascript', body='/* simulated unavailable motion module */'))
    pg.goto(BASE + '/'); pg.wait_for_load_state('networkidle')
    check(pg.locator('[data-reveal]').evaluate_all('(els) => els.every(e => getComputedStyle(e).opacity === "1")'), 'Missing motion module: every section stays visible')
    pg.locator('.nav-toggle').click(); check(pg.locator('#site-nav').get_attribute('inert') is None, 'Missing motion module: mobile navigation still works')
    ctx.close()
    ctx, pg = new(375)
    ctx.route('**/assets/js/script.js', lambda route: route.fulfill(status=200, content_type='text/javascript', body='/* simulated unavailable interaction module */'))
    pg.goto(BASE + '/browse.php'); pg.wait_for_load_state('networkidle')
    check(pg.locator('#q').is_visible() and pg.locator('#site-nav').is_visible(), 'Missing interaction module: filters and navigation remain usable')
    check(not pg.locator('[data-filter-open]').is_visible(), 'Missing interaction module: no dead filter toggle')
    ctx.close()

    # --- desktop header scroll state, reveal, hover
    ctx, pg = new(1280); pg.goto(BASE + "/"); pg.wait_for_load_state("networkidle")
    hdr = pg.locator("[data-site-header]")
    h0 = hdr.bounding_box()["height"]
    check("is-over-hero" in hdr.get_attribute("class") and "is-scrolled" not in hdr.get_attribute("class"), "Header starts transparent over the hero")
    pg.mouse.wheel(0, 900); pg.wait_for_timeout(700)
    h1 = hdr.bounding_box()["height"]
    check("is-scrolled" in hdr.get_attribute("class") and h1 < h0, "Header becomes a compact glass bar on scroll", f"{h0} -> {h1}")
    check("blur" in pg.evaluate("getComputedStyle(document.querySelector('[data-site-header]')).backdropFilter"), "Scrolled header has backdrop blur")
    pg.evaluate("document.querySelector('.cards-grid').scrollIntoView()"); pg.wait_for_timeout(1500)
    ops = pg.evaluate("[...document.querySelectorAll('.card-slot')].map(e=>getComputedStyle(e).opacity)")
    check(bool(ops) and all(o == "1" for o in ops), "Cards reveal on scroll and finish fully visible", str(ops))
    pg.locator(".dorm-card").first.hover(); pg.wait_for_timeout(700)
    tr = pg.evaluate("getComputedStyle(document.querySelector('.dorm-card')).transform")
    check("matrix" in tr, "Card lifts on hover", tr)
    imgtr = pg.evaluate("getComputedStyle(document.querySelector('.dorm-card .card-image img')).transform")
    check(imgtr.startswith("matrix(1.04"), "Card image zooms to 1.04 on hover", imgtr)
    btn = pg.locator(".compare-button").first; btn.click(); pg.wait_for_timeout(200)
    check("Added" in btn.inner_text() and btn.get_attribute("aria-pressed") == "true", "Compare button toggles state")
    check(pg.locator("#compare-count").inner_text() == "(1)", "Nav compare counter updates")
    ctx.close()

    # --- reduced motion
    ctx, pg = new(1280, reduced_motion="reduce"); pg.goto(BASE + "/"); pg.wait_for_load_state("networkidle"); pg.wait_for_timeout(300)
    ops = pg.evaluate("[...document.querySelectorAll('[data-reveal],.hero-in,.search-in')].map(e=>getComputedStyle(e).opacity)")
    check(bool(ops) and all(o == "1" for o in ops), "prefers-reduced-motion: every animated element is visible immediately", str(set(ops)))
    ctx.close()

    # --- JavaScript disabled
    ctx, pg = new(375, java_script_enabled=False); pg.goto(BASE + "/"); pg.wait_for_load_state()
    vis = pg.locator(".dorm-card").first.is_visible() and pg.locator(".search-panel").is_visible() and pg.locator("h1").is_visible()
    check(vis, "JS disabled: hero, search panel and cards still render")
    check(pg.locator("#site-nav a:has-text('Explore dorms')").is_visible(), "JS disabled: navigation links are still reachable on mobile")
    pg.goto(BASE + "/auth/login.php"); check(not pg.locator(".pw-toggle").is_visible(), "JS disabled: no dead password-toggle button")
    ctx.close()

    # --- login form
    ctx, pg = new(1280); pg.goto(BASE + "/auth/login.php"); pg.wait_for_load_state("networkidle")
    pg.fill("#password", "secret-value"); pg.click(".pw-toggle")
    check(pg.get_attribute("#password", "type") == "text" and pg.get_attribute(".pw-toggle", "aria-pressed") == "true", "Password toggle reveals the password")
    pg.click(".pw-toggle"); check(pg.get_attribute("#password", "type") == "password", "Password toggle hides it again")
    pg.fill("#email", "student@dormfinder.test"); pg.fill("#password", "wrong-password-x"); pg.click("form button.btn-primary"); pg.wait_for_load_state()
    check(pg.locator(".alert-danger").is_visible(), "Wrong password shows an error message")
    ctx.close()

    # --- browse filters
    ctx, pg = new(375); pg.goto(BASE + "/browse.php?university=1"); pg.wait_for_load_state("networkidle")
    dr = pg.locator("#filters")
    check(dr.bounding_box()["x"] < -50, "Mobile filters start off-screen")
    pg.click("[data-filter-open]"); pg.wait_for_timeout(500)
    check(dr.bounding_box()["x"] >= -1, "Filter button slides the drawer in")
    pg.screenshot(path=f"{OUT}/drawer-375.png")
    check(pg.evaluate("document.activeElement.classList.contains('drawer-close')"), "Focus moves into the drawer")
    check(dr.get_attribute('aria-modal') == 'true', 'Filter drawer announces a modal dialog')
    pg.keyboard.press('Shift+Tab')
    check(pg.evaluate("document.querySelector('#filters').contains(document.activeElement)"), 'Shift-Tab stays inside the filter drawer')
    pg.keyboard.press('Tab')
    check(pg.evaluate("document.activeElement.classList.contains('drawer-close')"), 'Tab wraps back to the first drawer control')
    pg.keyboard.press("Escape"); pg.wait_for_timeout(500)
    check(dr.bounding_box()["x"] < -50, "Escape closes the drawer")
    check(pg.locator('#filters').evaluate('(el) => el.inert'), 'Closed filter drawer cannot receive keyboard focus')
    check(pg.evaluate("document.activeElement.hasAttribute('data-filter-open')"), 'Filter drawer restores focus to the Filters button')
    pg.set_viewport_size({'width':1280,'height':900})
    check(not pg.locator('#filters').evaluate('(el) => el.inert'), 'Resizing to desktop restores interactive sidebar filters')
    pg.set_viewport_size({'width':375,'height':900})
    pg.screenshot(path=f"{OUT}/browse-375.png")
    ctx.close()
    ctx, pg = new(1280); pg.goto(BASE + "/browse.php?university=1&max_rent=9000"); pg.wait_for_load_state("networkidle")
    check(pg.locator(".filter-chip").count() >= 3, "Active filters show as chips")
    pg.locator(".filter-chip").first.click(); pg.wait_for_load_state()
    check("university" not in pg.url and "max_rent" in pg.url, "Removing a chip drops only that filter", pg.url)
    pg.click("a:has-text('Clear filters')"); pg.wait_for_load_state(); check(pg.url.rstrip("/").endswith("browse.php"), "Clear filters resets the search")
    pg.goto(BASE + "/browse.php?q=zzzz"); check(pg.locator(".empty-state").is_visible(), "Empty state appears when nothing matches")
    ctx.close()

    # --- details: lightbox + accordion
    ctx, pg = new(1280); login(pg, "student@dormfinder.test"); pg.goto(BASE + "/dorm-details.php?id=1"); pg.wait_for_load_state("networkidle")
    pg.click(".gallery-item >> nth=0"); pg.wait_for_timeout(500)
    check(pg.locator("dialog.lightbox[open]").count() == 1, "Clicking a photo opens the lightbox")
    c0 = pg.locator(".lightbox-count").inner_text(); pg.keyboard.press("ArrowRight"); pg.wait_for_timeout(200); c1 = pg.locator(".lightbox-count").inner_text()
    check(c0 != c1, "Arrow keys move between photos", f"{c0} -> {c1}")
    pg.screenshot(path=f"{OUT}/lightbox-1280.png")
    pg.keyboard.press("Escape"); pg.wait_for_timeout(200); check(pg.locator("dialog.lightbox[open]").count() == 0, "Escape closes the lightbox")
    check(pg.evaluate("document.activeElement.matches('[data-lightbox]')"), 'Lightbox restores focus to the gallery photo')
    det = pg.locator("details.accordion-item:has-text('Curfew')"); check(det.get_attribute("open") is None, "Curfew accordion starts closed")
    det.locator("summary").click(); pg.wait_for_timeout(120)
    mid = det.locator(".accordion-body").bounding_box()["height"]; pg.wait_for_timeout(500)
    end = det.locator(".accordion-body").bounding_box()["height"]
    check(det.get_attribute("open") is not None and end > 0 and mid <= end, "Accordion opens with an animated height", f"height {mid} -> {end}")
    det.locator("summary").click(); pg.wait_for_timeout(600); check(det.get_attribute("open") is None, "Accordion closes again")
    ctx.close()

    # --- unlock page
    ctx, pg = new(1280); login(pg, "student@dormfinder.test"); pg.goto(BASE + "/unlock.php?id=3"); pg.wait_for_load_state("networkidle")
    pg.click(".pay-option:has-text('Maya')"); pg.wait_for_timeout(300)
    check(pg.is_checked("input[value=maya]"), "Clicking a payment card selects it")
    bs = pg.evaluate("getComputedStyle(document.querySelector('input[value=maya] + .pay-card')).borderColor")
    check("37, 99, 235" in bs, "Selected payment card highlights in blue", bs)
    pg.screenshot(path=f"{OUT}/unlock-selected-1280.png")
    pg.click("form button.btn-primary"); pg.wait_for_load_state(); check("unlock-confirmation.php" in pg.url, "Confirming goes to the confirmation page")
    pg.wait_for_timeout(1500)
    off = pg.evaluate("getComputedStyle(document.querySelector('.result-icon path')).strokeDashoffset")
    check(pg.locator(".result-icon.ok").is_visible() and off in ("0px", "0"), "Success checkmark finishes drawing", off)
    pg.screenshot(path=f"{OUT}/confirmation-1280.png")
    ctx.close()

    # --- dashboards
    ctx, pg = new(1280); login(pg, "admin@dormfinder.test"); pg.goto(BASE + "/admin/dashboard.php"); pg.wait_for_load_state("networkidle"); pg.wait_for_timeout(1600)
    vals = pg.evaluate("[...document.querySelectorAll('[data-count-to]')].map(e=>[e.dataset.countTo.toString(), e.textContent.replace(/,/g,'')])")
    check(bool(vals) and all(a == b for a, b in vals), "Count-up numbers end on the exact database values", str(vals))
    w0 = pg.locator(".app-sidebar").bounding_box()["width"]; pg.click("[data-sidebar-collapse]"); pg.wait_for_timeout(600)
    w1 = pg.locator(".app-sidebar").bounding_box()["width"]; check(w1 < w0 and w1 < 100, "Sidebar collapses to an icon rail", f"{w0} -> {w1}")
    pg.screenshot(path=f"{OUT}/admin-collapsed-1280.png"); pg.reload(); pg.wait_for_load_state("networkidle")
    check(pg.locator(".app-sidebar").bounding_box()["width"] < 100, "Collapsed state is remembered after reload")
    check(pg.get_attribute('[data-sidebar-collapse]','aria-expanded') == 'false' and pg.get_attribute('[data-sidebar-collapse]','aria-label') == 'Expand sidebar', 'Remembered collapse state has the correct accessible label')
    pg.click("[data-sidebar-collapse]"); pg.wait_for_timeout(500)
    pg.click(".profile-btn"); pg.wait_for_timeout(300); check(pg.locator(".profile-dropdown").is_visible() and pg.get_attribute(".profile-btn", "aria-expanded") == "true", "Profile dropdown opens")
    pg.screenshot(path=f"{OUT}/profile-menu-1280.png"); pg.keyboard.press("Escape"); pg.wait_for_timeout(300); check(not pg.locator(".profile-dropdown").is_visible(), "Escape closes the profile menu")
    ctx.close()
    ctx, pg = new(375); login(pg, "owner@dormfinder.test"); pg.goto(BASE + "/owner/dashboard.php"); pg.wait_for_load_state("networkidle")
    sb = pg.locator("#app-sidebar"); check(sb.bounding_box()["x"] < -50, "Mobile dashboard sidebar starts hidden")
    check(sb.evaluate('(el) => el.inert'), 'Hidden dashboard navigation cannot receive keyboard focus')
    pg.click("[data-sidebar-open]"); pg.wait_for_timeout(500); check(sb.bounding_box()["x"] >= -1, "Menu button slides the sidebar in")
    check(pg.evaluate("document.activeElement.hasAttribute('data-sidebar-close')"), 'Dashboard drawer focuses its close button')
    check(pg.locator('.app-main').evaluate('(el) => el.inert'), 'Dashboard content is inert while its drawer is open')
    pg.screenshot(path=f"{OUT}/owner-sidebar-375.png")
    pg.keyboard.press('Shift+Tab'); check(pg.evaluate("document.querySelector('#app-sidebar').contains(document.activeElement)"), 'Dashboard drawer traps keyboard focus')
    pg.keyboard.press('Escape'); check(pg.evaluate("document.activeElement.hasAttribute('data-sidebar-open')"), 'Escape restores focus to dashboard menu button')
    check(not pg.locator('.app-main').evaluate('(el) => el.inert'), 'Closing the drawer restores dashboard interaction')
    ctx.close()

    # --- horizontal overflow
    bad = []
    ctx, pg = new(1280); login(pg, "admin@dormfinder.test"); cookies = ctx.cookies(); ctx.close()
    for width in (320, 375, 768, 1280, 1440):
        ctx, pg = new(width); ctx.add_cookies(cookies)
        for path in ["/", "/browse.php", "/dorm-details.php?id=1", "/dorm-details.php?id=2", "/compare.php?ids=1,2,3", "/auth/login.php", "/auth/register.php", "/admin/dashboard.php", "/admin/dormitories.php", "/admin/users.php", "/admin/transactions.php", "/admin/activity.php", "/admin/settings.php", "/admin/add-listing.php", "/admin/universities.php"]:
            pg.goto(BASE + path); pg.wait_for_load_state("domcontentloaded"); pg.wait_for_timeout(120)
            sw = pg.evaluate("document.documentElement.scrollWidth")
            if sw > width + 1: bad.append(f"{path}@{width} scrollWidth={sw}")
        ctx.close()
    check(not bad, "No horizontal scrolling at 320, 375, 768, 1280 and 1440px across 15 pages", "; ".join(bad))
    check(not errors, "No JavaScript errors or CSP violations in the console", "; ".join(errors[:4]))
    b.close()
print(f"\n{sum(res)} passed, {len(res)-sum(res)} failed")
sys.exit(0 if all(res) else 1)
