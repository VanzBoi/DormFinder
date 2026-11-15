"""Optional visual/interaction checks. Requires Python Playwright and installed Chrome.
Run: python tests/browser-checks.py http://127.0.0.1:8088
Uses seeded demo accounts; logs sign-ins/recent views but never changes listings.
"""
from pathlib import Path
import json
import sys
import re

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / 'tests/.runtime/python'))
from playwright.sync_api import sync_playwright

BASE = sys.argv[1] if len(sys.argv) > 1 else 'http://127.0.0.1:8088'
OUT = ROOT / 'tests/screenshots'
OUT.mkdir(exist_ok=True)
results = []
errors = []

def check(ok, message):
    results.append({'result': 'Pass' if ok else 'Fail', 'check': message})
    print(('PASS ' if ok else 'FAIL ') + message)

with sync_playwright() as p:
    browser = p.chromium.launch(channel='chrome', headless=True)
    for width in [1280, 768, 375]:
        context = browser.new_context(viewport={'width': width, 'height': 900}, reduced_motion='reduce')
        page = context.new_page()
        page.on('pageerror', lambda err: errors.append(str(err)))
        for route in ['/', '/browse.php', '/dorm-details.php?id=2', '/auth/login.php', '/auth/register.php', '/compare.php?ids=1,2,3']:
            response = page.goto(BASE + route, wait_until='networkidle')
            check(response.status == 200, f'{width}px {route}: loads')
            check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), f'{width}px {route}: no page overflow')
            if route == '/':
                page.locator('img').evaluate_all('(images) => images.forEach(i => i.loading = "eager")')
                page.locator('img').evaluate_all('async (images) => { await Promise.all(images.map(i => i.decode().catch(() => {}))); }')
                check(page.locator('img').evaluate_all('(images) => images.every(i => i.complete && i.naturalWidth > 0)'), f'{width}px home: all images load')
                page.screenshot(path=str(OUT / f'home-{width}.png'), full_page=True)
                if width <= 900:
                    page.get_by_role('button', name='Toggle navigation').click()
                    page.wait_for_timeout(500)  # the menu slides and fades in (about 300 ms)
                    check(page.locator('#site-nav').is_visible(), f'{width}px: mobile navigation opens')
                    page.get_by_role('button', name='Toggle navigation').click()
                    page.wait_for_timeout(500)
                    check(not page.locator('#site-nav').is_visible(), f'{width}px: mobile navigation closes')
            if route == '/browse.php':
                page.screenshot(path=str(OUT / f'browse-{width}.png'), full_page=True)
        page.goto(BASE + '/browse.php?q=Study&university=1&room_type=2&location=Sanciangko&min_rent=3000&max_rent=4000&sort=price_asc')
        check(page.locator('.dorm-card').count() == 1 and 'Casa Study Cebu' in page.locator('.dorm-card').inner_text(), f'{width}px: combined search and filters')
        page.goto(BASE + '/dorm-details.php?id=5')
        check(page.locator('.details-head .avail-badge').inner_text() == 'Fully occupied', f'{width}px: fully occupied status')
        page.goto(BASE + '/browse.php?available=1')
        check('Mango & Maple Dorm' not in page.locator('main').inner_text(), f'{width}px: occupied listing excluded by available-only filter')
        for sort, reverse in [('price_asc', False), ('price_desc', True)]:
            page.goto(BASE + '/browse.php?sort=' + sort)
            prices = [int(re.sub(r'[^0-9]', '', t)) for t in page.locator('.card-price strong').all_text_contents()]
            check(prices == sorted(prices, reverse=reverse), f'{width}px: {sort} order')
        page.goto(BASE + '/browse.php')
        page.evaluate("localStorage.removeItem('dormfinder-compare')")
        page.reload()
        page.locator('.compare-button').nth(0).click()
        page.locator('.compare-button').nth(1).click()
        check(page.locator('#compare-count').text_content() == '(2)', f'{width}px: comparison selection')
        if width <= 900:
            page.get_by_role('button', name='Toggle navigation').click()
            page.wait_for_timeout(500)
        page.locator('#site-nav a[href*="compare.php"]').click()
        check(page.locator('.comparison-table thead th').count() == 3, f'{width}px: comparison table displays two places')
        page.get_by_role('button', name='Clear comparison').click()
        check(page.locator('.empty-state').is_visible(), f'{width}px: clear comparison')
        for role, routes in {
            'student': ['dashboard.php', 'favorites.php', 'unlocked.php', 'profile.php'],
            'owner': ['dashboard.php', 'listings.php', 'add-listing.php', 'edit-listing.php?id=1', 'profile.php'],
            'admin': ['dashboard.php', 'dormitories.php', 'users.php', 'universities.php', 'room-types.php', 'transactions.php', 'activity.php', 'settings.php', 'add-listing.php'],
        }.items():
            page.goto(BASE + '/auth/login.php')
            page.get_by_label('Email address', exact=True).fill(f'{role}@dormfinder.test')
            page.get_by_label('Password', exact=True).fill('DemoPass2026!')
            page.get_by_role('button', name='Log in', exact=True).click()
            page.wait_for_url(f'**/{role}/dashboard.php')
            check(f'/{role}/dashboard.php' in page.url, f'{width}px: {role} browser login')
            for route in routes:
                response = page.goto(BASE + f'/{role}/' + route, wait_until='networkidle')
                check(response.status == 200 and f'/{role}/' in page.url, f'{width}px /{role}/{route}: loads')
                check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), f'{width}px /{role}/{route}: no page overflow')
                if route == 'dashboard.php':
                    page.screenshot(path=str(OUT / f'{role}-{width}.png'), full_page=True)
            if role == 'student':
                page.goto(BASE + '/unlock.php?id=2', wait_until='networkidle')
                check(page.evaluate('document.documentElement.scrollWidth <= innerWidth'), f'{width}px unlock form: no page overflow')
            # Logout via the form, even when the mobile navigation is collapsed.
            page.locator('form[action$="logout.php"]').first.evaluate('(form) => form.requestSubmit()')
            page.wait_for_url('**/auth/login.php')
        context.close()
    browser.close()
check(not errors, 'No JavaScript errors during browser checks')
(ROOT / 'tests/browser-results.json').write_text(json.dumps({'checks': results, 'javascript_errors': errors}, indent=2), encoding='utf-8')
print(f"{sum(r['result'] == 'Pass' for r in results)} passed, {sum(r['result'] == 'Fail' for r in results)} failed")
sys.exit(any(r['result'] == 'Fail' for r in results))
