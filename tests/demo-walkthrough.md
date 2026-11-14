# Five-minute demonstration

Use the documented demo password `DemoPass2026!`. Open the application at `http://localhost/DORMFINDER/` after XAMPP setup. All properties and payments in this demonstration are fictional.

1. **Discover as a visitor.** Open Find a dorm. Search `Study`, choose University of Cebu Main, Shared room, Sanciangko, minimum 3000 and maximum 4000. Casa Study Cebu is the matching sample. Reset, sort prices both ways, and show Mango & Maple Dorm's Fully Occupied label.
2. **Compare.** Add two or three cards to comparison. Open Compare in the navigation and show the rent, university, type, capacity and slot rows. No premium contact information appears here.
3. **Show the information boundary.** Open The Juniper Rooms as a guest. The address, owner contact and full house rules are not present in the HTML. Login as `student@dormfinder.test` and return to that listing.
4. **Simulate failure and success.** Open Unlock details, choose Maya and check the failed-transaction demonstration box. Show the failed receipt and that the listing stays locked. Retry without the failure option. Show the successful receipt, full details and additional image. Open Unlock again: it sends you to the already unlocked details without creating another successful transaction.
5. **Submit as an owner.** Log out and log in as `owner@dormfinder.test`. Add a listing with all required fields, amenities and a JPEG photo. Show Pending review in the owner table. The public search does not show it yet.
6. **Approve as admin.** Log out and log in as `admin@dormfinder.test`. Find the new listing in the pending queue, review its preview, and approve it. It now appears publicly. Demonstrate rejection with a reason on a different pending sample.
7. **Show consistent shared data.** Change the unlock fee, show transaction filtering, and note that the earlier transaction keeps its original amount. Open availability reports and activity logs.
8. **Finish with permissions and mobile layout.** A student receives 403 on an admin page; the first owner cannot edit listing 3 owned by the second owner. Resize to 375 px and open the mobile menu. Review `tests/test-checklist.md` and the saved screenshots.

Use a fresh imported demo database for repeatable demonstrations. Do not reset a database containing work you need. This script changes the chosen demo database through the normal interface; the automated backend suite instead uses an isolated test database.
