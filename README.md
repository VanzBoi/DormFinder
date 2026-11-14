# DormFinder

DormFinder helps students find dormitories near their campus in Cebu City. It is an **academic prototype**. Every listing, account and contact detail is fictional, and every payment is **simulated**: no money is collected and no payment service is contacted.

Students can browse and compare listings for free. They then pay a simulated one-time fee per listing to unlock its exact address, owner contact, house rules and remaining photos. Owners submit listings, and an administrator reviews them before they go public.

## Requirements

- [XAMPP](https://www.apachefriends.org/) with PHP 8.1 or newer and MySQL/MariaDB (tested with PHP 8.2.12 and MariaDB 10.4)
- PHP extensions `pdo_mysql` and `fileinfo` (both enabled in XAMPP by default), plus `curl` to run the tests

The application uses native PHP, PDO prepared statements, MySQL/MariaDB, HTML5, CSS3, Bootstrap 5, Bootstrap Icons and vanilla JavaScript. There is no Node.js build step, framework server, paid service, or external payment dependency. Bootstrap, icons and sample photos are included locally, so the application works offline after installation. Optional map links open an external service.

## Problem and audience

Students near University of Cebu Main and nearby downtown Cebu schools often piece together information from social posts, referrals, and neighborhood visits. Prices, availability and property details can be incomplete or stale. DormFinder brings those details into one searchable prototype for three audiences: students finding a place, owners maintaining their listings, and administrators reviewing the platform's data.

This implementation follows the supplied master prompt. Separate HackPitch and CubeTech source documents were not provided; no additional requirements from those documents have been assumed.

## Setup

1. Open this project in VS Code using **File → Open Folder**. The source workspace is `C:\Users\govan\OneDrive\Desktop\DormFinder`.
2. Open **Terminal → New Terminal** (PowerShell) and run `powershell -ExecutionPolicy Bypass -File scripts/install-xampp.ps1`. This creates `C:\xampp\htdocs\DORMFINDER` and copies the complete application, excluding temporary test files. It refuses to overwrite a nonempty destination. Alternatively, create that folder in File Explorer and copy the project's files into it; do not copy `tests/.runtime`.
3. Open `C:\xampp\htdocs\DORMFINDER` in VS Code for subsequent edits to the Apache-served copy. The desktop source and copied installation are separate folders.
4. Start **Apache** and **MySQL** in the XAMPP Control Panel.
5. Open phpMyAdmin at <http://localhost/phpmyadmin>. At the server level, select **Import → Choose File**, select `C:\xampp\htdocs\DORMFINDER\database\dormfinder.sql`, choose **utf-8**, keep **SQL** as the format, and click **Import**. The file creates `dormfinder_db`, all tables, and sample data. If the database already exists and has tables, do not import again: use the existing installation or choose a separate database after making a backup.
6. Expand `dormfinder_db` in phpMyAdmin and browse `dormitories`: a fresh installation has 12 listings, including pending, inactive, rejected, and fully occupied examples. The public browse page shows eight approved, active listings.
7. Visit <http://localhost/DORMFINDER/>. Open **Find a dorm**, then log in as each role using the demo credentials below.

If your MySQL `root` account has a password, or you use a different database name, copy `config/local.example.php` to `config/local.php` and edit the values. `config/local.php` is git-ignored. Environment variables with the same names (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`) take precedence over that file.

**Without Apache:** from the project folder, run `C:\xampp\php\php.exe -S 127.0.0.1:8000 router.php` and open <http://127.0.0.1:8000/>. `router.php` blocks the private folders the same way the `.htaccess` files do under Apache.

In PowerShell, prefix the executable with `&` when quoting its path:

```powershell
& 'C:\xampp\php\php.exe' -S 127.0.0.1:8000 -t . router.php
```

Stop that development server with **Ctrl+C**. Keep MySQL running. Do not use `php -S` without `router.php`, because PHP's development server does not read Apache `.htaccess` rules.

## PHP and database configuration

`config/database.php` opens one shared PDO connection using `utf8mb4`, native prepared statements, exception handling, and Philippine database session time. All three roles use this connection. Defaults are host `127.0.0.1`, port `3306`, database `dormfinder_db`, user `root`, and an empty password, matching a typical local XAMPP installation. Use `config/local.php` to override credentials; never put database credentials in JavaScript.

Use **XAMPP → Apache → Config → PHP (php.ini)** to inspect PHP settings. Enable `extension=pdo_mysql` and `extension=fileinfo`. Recommended local upload settings are:

```ini
upload_max_filesize=5M
post_max_size=48M
max_file_uploads=8
memory_limit=128M
```

Ensure `upload_tmp_dir` and `session.save_path` point to writable folders (normally `C:\xampp\tmp`), and that Apache can write `uploads/dormitories`. Restart Apache after editing php.ini. The application independently enforces 5 MB per image, eight images per listing and a 24-megapixel image limit.

PHP 8.1+ is required because the source uses `never` return types. Use XAMPP's PHP explicitly when another `php` executable on PATH lacks the required extensions.

## URLs to test

| Purpose | URL |
|---|---|
| Home | http://localhost/DORMFINDER/ |
| Search and filters | http://localhost/DORMFINDER/browse.php |
| Locked sample | http://localhost/DORMFINDER/dorm-details.php?id=2 |
| Compare samples | http://localhost/DORMFINDER/compare.php?ids=1,2,3 |
| Login / registration | http://localhost/DORMFINDER/auth/login.php / http://localhost/DORMFINDER/auth/register.php |
| Student dashboard | http://localhost/DORMFINDER/student/dashboard.php |
| Owner listings | http://localhost/DORMFINDER/owner/listings.php |
| Admin overview | http://localhost/DORMFINDER/admin/dashboard.php |
| Admin review queue | http://localhost/DORMFINDER/admin/dormitories.php?status=pending |
| Unlock price setting | http://localhost/DORMFINDER/admin/settings.php |

Dashboard URLs require the matching role. Logout is a POST action using the **Log out** button, not a direct GET link.

## Demo accounts

All four accounts use the password **`DemoPass2026!`**.

| Role          | Email                     | What to try                                                          |
|---------------|---------------------------|----------------------------------------------------------------------|
| Administrator | `admin@dormfinder.test`   | Approve the two pending listings, change the unlock fee, view reports |
| Owner         | `owner@dormfinder.test`   | Edit a listing, add one with photos, read a rejection reason         |
| Owner         | `owner2@dormfinder.test`  | Same, with a different set of listings                               |
| Student       | `student@dormfinder.test` | Unlock a listing, save favorites, compare up to three places         |

The sample student has already unlocked *Casa Study Cebu*, and has one failed simulated payment for *The Juniper Rooms*.

## Features

**Everyone**
- Search by name or neighborhood, and filter by university, room type, rent range and availability
- Compare up to three listings side by side (the shortlist is kept in the browser)
- See each listing's description, price, capacity, availability, first photo and three amenities

**Students**
- Simulated one-time unlock per listing, by GCash, Maya or bank transfer. You can also choose to simulate a failed payment.
- After unlocking: exact address, map link, owner contact, house rules, curfew, security notes, all photos and all amenities
- Saved places, unlocked places, recently viewed listings and transaction history

**Owners**
- Add and edit listings with up to eight photos (JPEG, PNG or WebP, 5 MB each)
- Every new or edited listing goes back to *pending* and leaves public search until an administrator approves it

**Administrators**
- Approve, reject (with a reason the owner can see), activate, deactivate or archive listings
- Manage universities and room types. Entries still used by a listing can't be deleted.
- Activate or deactivate student and owner accounts
- Set the unlock fee. Past transactions keep the amount they were charged.
- Transaction log, activity log, and availability by university

## Security measures

- Every query uses a prepared statement; all output is HTML-escaped.
- Every POST form carries a CSRF token.
- Passwords are stored with `password_hash()`. The session ID changes at login, and the session cookie is `HttpOnly` and `SameSite=Lax`.
- Sign-in is throttled to 10 failed attempts per five minutes.
- Locked details are left out of the page for users who haven't unlocked the listing, rather than hidden with CSS.
- Owner-uploaded photos are served only through `image.php`, which checks who may see each one. The upload folder can't be opened directly. Illustrative seed photos are also public assets used by the home and login pages.
- Uploads are checked for real image content, not just the file extension, and are stored under random names.
- The database allows at most one successful unlock per student per listing, so a double-submitted form can't charge twice.
- Pages send a strict Content-Security-Policy and related security headers. The `config`, `includes`, `database`, `uploads` and `tests` folders are blocked from the web.

## Running the tests

```
C:\xampp\php\php.exe tests\run.php
```

MySQL must be running. The suite creates a temporary database named `dormfinder_test`, loads `database/dormfinder.sql` into it, and starts PHP's built-in server on a free port. It then uses the site as a guest, a student, an owner and an administrator (138 checks), and deletes the temporary database and any uploaded test photos when it's done. Your `dormfinder_db` database is not touched. Logs from the last run are kept in `tests/.runtime/`. The deliberately oversized upload check produces one expected PHP startup warning; other diagnostics fail the suite.

To keep the test database for inspection, set `KEEP_TEST_DB=1`. To use a different name, set `TEST_DB_NAME`. The name must end in `_test`.

The suite resets its selected test database. Use a fresh test-only name if `dormfinder_test` contains anything you need. It must never point at a working application database.

See [the acceptance checklist](tests/test-checklist.md) for reproducible manual steps and results. Browser checks and screenshots use the optional Python Playwright developer utility and an installed Chrome browser; Python is not required to run DormFinder:

```powershell
python -m pip install --target tests/.runtime/python playwright
python tests/browser-checks.py http://127.0.0.1:8000
```

Browser checks need the documented seed accounts, record login/recent-view activity, and save screenshots to `tests/screenshots`. They do not submit listing edits or simulated payments. Browser results are recorded in `tests/browser-results.json`.

## Database relationships

See [database/relationships.md](database/relationships.md) for table responsibilities and a relationship diagram. `dormfinder.sql` is the complete schema, seed data and relationship definition; no migration framework is needed.

## Five-day learning sequence

| Day | Review and demonstrate |
|---|---|
| 1 | Import SQL, inspect relationships, configure PDO, render shared layout and navigation |
| 2 | Browse and combine filters; inspect listing details and availability; resize the page |
| 3 | Register/login; demonstrate successful and failed simulated unlocks; inspect atomic database writes |
| 4 | Submit an owner listing, approve it as admin, change reference data and the unlock fee |
| 5 | Run automated checks, complete the manual checklist, review screenshots and present the walkthrough |

The implementation for these stages is included. Follow [tests/demo-walkthrough.md](tests/demo-walkthrough.md) for a presentation script.

## Common setup problems

| Symptom | What to check |
|---|---|
| `could not find driver` | Use XAMPP PHP, enable `pdo_mysql`, restart Apache, and run `C:\xampp\php\php.exe -m`. |
| Database connection refused | Start MySQL and confirm the configured host and port. |
| Access denied for database user | Set the correct password in `config/local.php`; phpMyAdmin and the app must use compatible credentials. |
| Unknown database or missing table | Import the entire SQL file into a new database; do not import only the seed statements. |
| Table already exists during import | The database is already initialized. Do not import repeatedly or drop a database containing work you need. |
| PHP source is displayed or downloaded | Open the page through Apache's localhost URL, not `file://` or a static Live Server extension. |
| Apache will not start | Check the XAMPP log for a port conflict. If using port 8080, use `http://localhost:8080/DORMFINDER/`. |
| Login does not persist | Allow local cookies and make `session.save_path` writable. The restricted coding environment can require a writable workspace session directory. |
| Photos fail to upload | Check `fileinfo`, writable temporary/upload folders, PHP size limits and the actual image format. SVG/GIF/executable uploads are intentionally rejected. |
| Owner listing disappears after editing | Edits return it to pending review. Approve it in the admin dashboard to restore public visibility. |
| Localhost shows old changes | Confirm which folder Apache serves. Editing the desktop source does not update an earlier copied htdocs installation. |
| Accented names appear as question marks | Import SQL as UTF-8 in phpMyAdmin. Avoid piping Unicode SQL through Windows PowerShell's default ASCII output encoding. |

If running a sandboxed preview, create `tests/.runtime/preview-sessions` and `tests/.runtime/preview-uploads`, then pass `-d session.save_path=ABSOLUTE_PATH_TO_SESSIONS -d upload_tmp_dir=ABSOLUTE_PATH_TO_UPLOAD_TEMP` to PHP before `-S`, replacing those values with the absolute paths to the new folders. Relative session paths break on nested routes because PHP changes its working directory. These private test folders are blocked by `router.php`.

## Resetting the sample data

`database/dormfinder.sql` never drops existing data, so importing it twice fails. To start over, run `DROP DATABASE dormfinder_db;` in phpMyAdmin and import the file again. This also deletes any listings, accounts and transactions you created.

## Project structure

```
index.php, browse.php, dorm-details.php, compare.php   public pages
unlock.php, unlock-confirmation.php                    simulated unlock
image.php                                              access-checked photo delivery
auth/        sign in, register, sign out
student/     student dashboard, saved and unlocked places, profile
owner/       owner dashboard, listings, profile
admin/       moderation, users, reference data, transactions, activity, settings
includes/    bootstrap, helpers, shared templates and forms (not web-accessible)
config/      database connection (not web-accessible)
database/    schema and sample data (not web-accessible)
uploads/     owner photos, served only through image.php
tests/       end-to-end test suite
```

## Known limitations

- Payments are simulated. A real payment provider would need webhooks, refunds and receipts.
- There is no email verification and no password reset.
- Login throttling is tied to the browser session, so clearing cookies resets it. A production system would throttle by account and IP address.
- Unlocking reveals details but does not reserve a room.
- Owner details such as security information are not verified.
- Admin transaction management is searchable, filtered, immutable history plus fee configuration. There is no manual conversion of a failed transaction to success and no refund workflow.
- Archiving is used instead of destructive listing deletion. No restore interface is included. Removed images remain private on disk for recovery and need a future maintenance policy.
- All edits, including availability-only edits, return owner listings to pending review. There is no parallel draft that keeps an older approved version public.
- Accounts may be activated/deactivated; administrators cannot change other users' passwords or roles through the dashboard.
- Comparison uses browser storage and JavaScript; it does not synchronize between devices. Core search and forms work without JavaScript.
- Map links are optional, owner-entered HTTPS links. No paid geocoding, embedded map service, or distance calculation is included.
- Password recovery, email verification, bookings, messaging, real property verification, and production monitoring are future work.

## Assets and AI assistance

Bootstrap 5.3.3 and Bootstrap Icons 1.11.3 are bundled under their MIT licenses. See [assets/credits.md](assets/credits.md) for local files and illustrative photo sources. Photos do not depict verified listed businesses.

This academic prototype was created with AI assistance for code, database design, UI, tests and documentation. The student should review the implementation, understand the permission and transaction flows, and disclose AI use according to the school's academic requirements. It is not a production-ready rental or payment service.
