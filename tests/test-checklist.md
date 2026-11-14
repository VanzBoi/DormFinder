# Acceptance checklist

Record **Pass**, **Fail**, or **Not Tested** for each manual run. The evidence column distinguishes automated verification from a human walkthrough. Run destructive or data-changing scenarios against a test/demo database, not valuable working data.

Verified on 2026-11-14 using XAMPP PHP 8.2.12, MariaDB 10.4.32, and Chrome on Windows. The backend suite completed **138 checks with 0 failures**, including 155 HTTP responses. It creates its own test database and cleans it up. An intentionally oversized POST generates one expected PHP startup warning; no unexpected PHP diagnostics were found.

| # | Manual procedure and expected result | Current result | Evidence |
|---|---|---|---|
| 1 | Browse: search Study, university UC Main, Sanciangko, Shared room, rent 3000–4000. Only Casa Study Cebu matches; filters remain selected. | Pass | Browser test combines all filters. |
| 2 | Reset filters, select ascending price, then descending price. Card prices appear in the requested order. | Pass | Browser reads and compares every card price in both orders. |
| 3 | Try details IDs 8 (pending), 9 (inactive), and 11 (rejected). Each is 404; public browse contains eight approved active seed listings. | Pass | Backend public visibility tests. |
| 4 | View Mango & Maple Dorm, ID 5. It shows Fully Occupied and zero available slots. Available-only filtering excludes it. | Not Tested | Manual check pending; covered by rendering logic, not claimed as a completed test yet. |
| 5 | As guest, view ID 2 and inspect HTML/image requests. Exact address, contact and additional photo stay inaccessible. | Pass | Backend HTML assertions and private-image 404 checks. |
| 6 | Log in as student, unlock ID 2 with a simulated method. A successful receipt and full details become available. | Pass | Backend HTTP flow verifies receipt, details and image access. |
| 7 | Repeat the unlock POST and revisit the unlock page. Only one successful record exists. | Pass | Backend HTTP, service and database unique-constraint checks. |
| 8 | Simulate failure for an unpurchased listing. No access is granted. A database error while inserting a grant must also roll back the earlier transaction insert. | Pass | Failed-payment flow plus injected database trigger failure. |
| 9 | Open admin and owner pages as guest/student. Guests go to login; wrong roles get 403. | Pass | Backend role-boundary checks. |
| 10 | First owner attempts to view and POST edits to listing 3 belonging to owner 2. Both requests are rejected. | Pass | Backend ownership checks for GET and POST. |
| 11 | Owner adds a valid listing with a photo. It saves as pending, attributed to the owner; guests cannot see it or its photo. | Pass | Backend upload, ownership, review-state and image checks. |
| 12 | Admin approves the new listing. Public page and cover photo become available. | Pass | Backend moderation flow. |
| 13 | Change slots as owner, reload owner table, approve as admin and reload public details. Shared data reflects the saved values and review state. | Pass | Backend uses separate requests and database assertions across roles. |
| 14 | Change unlock fee, buy another listing, check earlier amounts; attempt deleting a referenced university and duplicating a successful unlock. | Pass | Backend historical-value and relational-constraint checks. |
| 15 | At 375 px and 1280 px, review all public and dashboard pages; open mobile navigation; check controls and page overflow. | Not Tested | Browser run in progress; final result will be recorded after completion. |
| 16 | Submit invalid slots, map scheme, email, role, password and negative fee; omit CSRF. All are rejected on the server. | Pass | Backend validation and CSRF checks. |
| 17 | Upload a real JPEG, text renamed as JPEG, and an oversized POST. Valid photo succeeds; invalid ones are rejected. | Pass | Backend multipart upload tests and 413 response. |
| 18 | Log in and log out; try the old session; deactivate a student and reuse their session. Access is denied after logout/deactivation. | Pass | Backend session and account-status checks. |

Additional checks: stored names are escaped, map URLs require HTTPS, duplicate names ignore capitalization, public cover images and protected images follow different access rules, archiving retains history, comparison is capped at three items, favorites toggle, and static assets are bundled locally.

Not yet independently verified: Firefox/Safari, a physical mobile device, real-world property accuracy, screen-reader navigation, deployment outside local XAMPP, and real payments (intentionally not implemented). Manual results can be updated using the same three result labels without conflating automated evidence with human review.
