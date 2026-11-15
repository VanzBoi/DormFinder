<?php
declare(strict_types=1);
/*
 * DormFinder end-to-end test suite.
 *
 * Builds a throwaway copy of the database from database/dormfinder.sql, serves the app with
 * PHP's built-in server on a random port, and drives it over HTTP the way a browser would.
 * Your working database (dormfinder_db) is never touched.
 *
 *   C:\xampp\php\php.exe tests\run.php
 *
 * Connection settings come from the same place as the app's (environment, then config/local.php).
 * Environment (all optional): DB_HOST, DB_PORT, DB_USER, DB_PASSWORD,
 * TEST_DB_NAME (default dormfinder_test; must end in _test), KEEP_TEST_DB=1 to keep it afterwards.
 * Logs from the last run are written to tests/.runtime/.
 */

namespace DormFinder\Tests;

use CURLFile;
use DomainException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

const DEMO_PASSWORD = 'DemoPass2026!';

final class Response
{
    public function __construct(public string $label, public int $status, public array $headers, public string $body) {}
    public function header(string $name): string { return $this->headers[strtolower($name)][0] ?? ''; }
    public function location(): string { return $this->header('location'); }
    public function has(string $text): bool { return str_contains($this->body, $text); }
}

final class Browser
{
    /** @var Response[] every response from every browser, scanned for PHP diagnostics at the end */
    public static array $history = [];
    private array $cookies = [];

    public function __construct(private string $base) {}

    public function get(string $path): Response { return $this->request('GET', $path); }

    /** Loads $formPage to pick up its CSRF token, then posts $fields to $action (default: the same page). */
    public function submit(string $formPage, array $fields, ?string $action = null, bool $multipart = false): Response
    {
        return $this->post($action ?? $formPage, ['csrf' => self::csrf($this->get($formPage))] + $fields, $multipart);
    }

    public function post(string $path, array $fields, bool $multipart = false): Response
    {
        return $this->request('POST', $path, $multipart ? self::flatten($fields) : http_build_query($fields));
    }

    public function login(string $email, string $password = DEMO_PASSWORD): Response
    {
        return $this->submit('/auth/login.php', ['email' => $email, 'password' => $password]);
    }

    public function request(string $method, string $path, array|string|null $body = null): Response
    {
        $ch = curl_init($this->base . $path);
        $headers = $this->cookies ? ['Cookie: ' . implode('; ', array_map(fn($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies))] : [];
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $raw = curl_exec($ch);
        if ($raw === false) throw new RuntimeException("$method $path failed: " . curl_error($ch));
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $parsed = [];
        foreach (explode("\r\n", substr($raw, 0, $size)) as $line) {
            if (!str_contains($line, ':')) continue;
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $parsed[strtolower($name)][] = $value;
        }
        foreach ($parsed['set-cookie'] ?? [] as $cookie) {
            [$name, $value] = array_pad(explode('=', explode(';', $cookie, 2)[0], 2), 2, '');
            if ($value === '' || $value === 'deleted' || preg_match('/max-age=0\b/i', $cookie)) unset($this->cookies[$name]);
            else $this->cookies[$name] = $value;
        }
        return self::$history[] = new Response("$method $path", curl_getinfo($ch, CURLINFO_RESPONSE_CODE), $parsed, substr($raw, $size));
    }

    public static function csrf(Response $page): string
    {
        if (!preg_match('/name="csrf" value="([a-f0-9]{64})"/', $page->body, $m)) throw new RuntimeException("No CSRF token on $page->label (HTTP $page->status).");
        return $m[1];
    }

    /** Turns nested arrays into the bracketed field names multipart/form-data needs. */
    private static function flatten(array $fields, string $prefix = ''): array
    {
        $flat = [];
        foreach ($fields as $key => $value) {
            $name = $prefix === '' ? (string)$key : "{$prefix}[$key]";
            if (is_array($value)) $flat += self::flatten($value, $name); else $flat[$name] = $value;
        }
        return $flat;
    }
}

final class Suite
{
    public int $passed = 0;
    public array $failures = [];
    private string $section = '';

    public function section(string $name): void { $this->section = $name; echo "\n$name\n"; }

    public function check(bool $ok, string $label, string $detail = ''): bool
    {
        if ($ok) { $this->passed++; echo "  PASS  $label\n"; return true; }
        $this->failures[] = "$this->section > $label" . ($detail !== '' ? " ($detail)" : '');
        echo "  FAIL  $label" . ($detail !== '' ? " -- $detail" : '') . "\n";
        return false;
    }

    public function status(Response $r, int $expected, string $label): bool
    {
        return $this->check($r->status === $expected, $label, "$r->label returned HTTP $r->status, expected $expected");
    }

    public function redirect(Response $r, string $to, string $label): bool
    {
        $ok = in_array($r->status, [302, 303], true) && str_starts_with($r->location(), $to);
        return $this->check($ok, $label, "$r->label returned HTTP $r->status to '" . $r->location() . "', expected a redirect to $to");
    }

    public function throws(callable $fn, string $class, string $label): bool
    {
        try { $fn(); } catch (Throwable $e) { return $this->check($e instanceof $class, $label, 'threw ' . $e::class . ': ' . $e->getMessage()); }
        return $this->check(false, $label, "no $class was thrown");
    }
}

function val(string $sql, array $params = []): mixed { $s = \db()->prepare($sql); $s->execute($params); return $s->fetchColumn(); }
function row(string $sql, array $params = []): array { $s = \db()->prepare($sql); $s->execute($params); return $s->fetch() ?: []; }
function col(string $sql, array $params = []): array { $s = \db()->prepare($sql); $s->execute($params); return $s->fetchAll(PDO::FETCH_COLUMN); }

function server_connection(): PDO
{
    // Same settings as the app (environment, then config/local.php, then XAMPP defaults), minus the database name.
    $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', \db_setting('DB_HOST', '127.0.0.1'), \db_setting('DB_PORT', '3306'));
    return new PDO($dsn, \db_setting('DB_USER', 'root'), \db_setting('DB_PASSWORD', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

function install_database(string $app, string $name): void
{
    $pdo = server_connection();
    $pdo->exec("DROP DATABASE IF EXISTS `$name`");
    $sql = preg_replace('/^--.*$/m', '', str_replace('dormfinder_db', $name, file_get_contents("$app/database/dormfinder.sql")));
    foreach (preg_split('/;\s*$/m', $sql) as $statement) if (trim($statement) !== '') $pdo->exec($statement);
}

/** @return array{0: resource, 1: string} */
function start_server(string $app, string $runtime, string $database): array
{
    $probe = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int)substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
    fclose($probe);
    $command = [PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=' . E_ALL, '-d', 'log_errors=1',
        '-d', "error_log=$runtime/php-errors.log", '-d', "session.save_path=$runtime/sessions", '-d', "upload_tmp_dir=$runtime/uploads",
        // Small limits so the oversized-upload test needs only a small file. Output buffering matches
        // XAMPP's php.ini, which lets headers still be sent after PHP's own oversized-POST warning.
        '-d', 'post_max_size=1M', '-d', 'upload_max_filesize=1M', '-d', 'output_buffering=4096',
        '-S', "127.0.0.1:$port", '-t', $app, "$app/router.php"];
    $env = getenv(); $env['DB_NAME'] = $database;
    $log = ['file', "$runtime/server.log", 'a'];
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => $log, 2 => $log], $pipes, $app, $env);
    if (!is_resource($process)) throw new RuntimeException('Could not start the PHP development server.');
    for ($i = 0; $i < 100; $i++) {
        if ($socket = @fsockopen('127.0.0.1', $port, $errno, $error, 0.2)) { fclose($socket); return [$process, "http://127.0.0.1:$port"]; }
        usleep(100_000);
    }
    proc_terminate($process);
    throw new RuntimeException("The PHP development server did not start. See $runtime/server.log.");
}

function listing_fields(int $id): array
{
    $keys = ['name', 'location', 'exact_address', 'university_id', 'room_type_id', 'monthly_rent', 'capacity', 'available_slots',
        'description', 'house_rules', 'curfew', 'security_info', 'contact_number', 'map_url'];
    $fields = array_map('strval', array_intersect_key(row('SELECT * FROM dormitories WHERE id=?', [$id]), array_flip($keys)));
    $fields['is_active'] = '1';
    $fields['amenities'] = array_map('strval', col('SELECT amenity_id FROM dormitory_amenities WHERE dormitory_id=?', [$id]));
    return $fields;
}

// ---------------------------------------------------------------------------------------------

$app = dirname(__DIR__);
$runtime = __DIR__ . '/.runtime';
$database = getenv('TEST_DB_NAME') ?: 'dormfinder_test';
if (!preg_match('/^[A-Za-z0-9_]+_test$/D', $database)) { fwrite(STDERR, "Refusing to use '$database': the test database name must end in _test.\n"); exit(2); }
foreach (['curl', 'pdo_mysql', 'fileinfo'] as $extension) if (!extension_loaded($extension)) { fwrite(STDERR, "The $extension extension is required. Run this with XAMPP's PHP: C:\\xampp\\php\\php.exe tests\\run.php\n"); exit(2); }

@mkdir("$runtime/sessions", 0777, true);
@mkdir("$runtime/uploads", 0777, true);
foreach (['php-errors.log', 'server.log'] as $file) file_put_contents("$runtime/$file", '');
date_default_timezone_set('Asia/Manila');
putenv("DB_NAME=$database");
require "$app/config/database.php";
require "$app/includes/functions.php";
require "$app/includes/auth.php";
require "$app/includes/unlock-service.php";

$t = new Suite();
$uploadDir = "$app/uploads/dormitories";
$uploadsBefore = scandir($uploadDir);
$server = null;

try {
    echo "Installing test database '$database'...\n";
    install_database($app, $database);
    [$server, $base] = start_server($app, $runtime, $database);
    echo "Serving the app at $base\n";

    // --- Pure helpers ----------------------------------------------------------------------
    $t->section('Money and decimal helpers');
    foreach (['49' => '49.00', '1.5' => '1.50', '3500.25' => '3500.25', '99999999.99' => '99999999.99', '0' => null, '0.00' => null,
              '-1' => null, '01' => null, '1.555' => null, '100000000' => null, '1e3' => null, ' 5' => null] as $in => $out) {
        $t->check(\decimal((string)$in) === $out, "decimal('$in') is " . var_export($out, true));
    }
    foreach (['3500.00' => '₱3,500', '1234567.50' => '₱1,234,567.50', '49.00' => '₱49'] as $in => $out) {
        $t->check(\money($in) === $out, "money('$in') is $out", 'got ' . \money($in));
    }

    // --- Public pages ----------------------------------------------------------------------
    $guest = new Browser($base);
    $t->section('Public pages');
    $home = $guest->get('/');
    $t->status($home, 200, 'Home page loads');
    $t->check($home->has('name="min_rent"') && $home->has('name="max_rent"') && $home->has('name="university"') && $home->has('name="location"') && $home->has('action="/browse.php"'), 'Home search panel offers university, location and min/max budget and submits to browse');
    $t->check($home->has('Your Next Home,') && !preg_match('~<(?:script|style)\b[^>]*>[^<]|\sstyle=~i', $home->body), 'Home page has the new hero and no inline scripts or styles (CSP-safe)');
    $t->check(str_contains($home->header('content-security-policy'), "default-src 'self'"), 'Content-Security-Policy header is sent');
    $t->check($home->header('x-frame-options') === 'SAMEORIGIN', 'X-Frame-Options header is sent');
    $cookie = implode("\n", $home->headers['set-cookie'] ?? []);
    $t->check(stripos($cookie, 'HttpOnly') !== false && stripos($cookie, 'SameSite=Lax') !== false, 'Session cookie is HttpOnly and SameSite=Lax');

    $t->check($guest->get('/browse.php')->has('<strong>8 places</strong>'), 'Browse shows only the 8 approved, active listings');
    $t->check($guest->get('/browse.php?max_rent=3000')->has('<strong>2 places</strong>'), 'Budget filter narrows results');
    $t->check($guest->get('/browse.php?q=Juniper')->has('<strong>1 place</strong>'), 'Name search finds a single listing (and says "place")');
    $t->check($guest->get('/browse.php?min_rent=0.00')->has('<strong>8 places</strong>'), 'A minimum rent of 0.00 is accepted');
    $t->check($guest->get('/browse.php?min_rent=abc')->has('Enter a valid minimum rent.'), 'An invalid minimum rent is reported');
    $t->check($guest->get('/browse.php?min_rent=6000&max_rent=3000')->has('Minimum rent cannot exceed maximum rent.'), 'Inverted rent range is reported');
    $t->check($guest->get('/browse.php?q=' . urlencode("' OR 1=1 -- "))->has('<strong>0 places</strong>'), 'SQL-like search text is treated as plain text');
    $t->status($guest->get('/compare.php?ids=1,2,8'), 200, 'Compare page loads');
    $t->check(!$guest->get('/compare.php?ids=1,2,8')->has('Campus Corner Cebu'), 'Compare ignores listings that are not public');

    $t->section('Protected listing details');
    $locked = $guest->get('/dorm-details.php?id=2');
    $t->status($locked, 200, 'Public listing details load for guests');
    $t->check(!$locked->has('24 Sample Lane') && !$locked->has('owner@dormfinder.test'), 'Exact address and owner email stay hidden before unlocking');
    $t->check($locked->has('Get the full picture.'), 'Locked listing shows the unlock prompt');
    foreach ([8 => 'pending', 9 => 'inactive', 11 => 'rejected'] as $id => $why) $t->status($guest->get("/dorm-details.php?id=$id"), 404, "A $why listing is not public");
    $publicImage = (int)val('SELECT id FROM dormitory_images WHERE dormitory_id=2 AND is_public=1');
    $privateImage = (int)val('SELECT id FROM dormitory_images WHERE dormitory_id=2 AND is_public=0');
    $image = $guest->get("/image.php?id=$publicImage");
    $t->check($image->status === 200 && $image->header('content-type') === 'image/jpeg', 'The free preview photo is served as a JPEG');
    $t->status($guest->get("/image.php?id=$privateImage"), 404, 'Extra photos are withheld before unlocking');

    $t->section('Private files are not web-accessible');
    foreach (['/config/database.php', '/database/dormfinder.sql', '/includes/functions.php', '/tests/run.php', '/uploads/dormitories/.gitkeep', '/README.md', '/.gitignore'] as $path) {
        $t->status($guest->get($path), 403, "$path is forbidden");
    }

    // --- Accounts --------------------------------------------------------------------------
    $t->section('Sign-in and registration');
    $t->check($guest->login('student@dormfinder.test', 'wrong-password-1')->has('The email or password is incorrect'), 'A wrong password is rejected');
    $t->status($guest->post('/auth/login.php', ['email' => 'student@dormfinder.test', 'password' => DEMO_PASSWORD]), 419, 'A login without a CSRF token is rejected');

    $attacker = new Browser($base);
    $token = Browser::csrf($attacker->get('/auth/login.php'));
    for ($i = 0; $i < 10; $i++) $attacker->post('/auth/login.php', ['csrf' => $token, 'email' => 'admin@dormfinder.test', 'password' => "guess-$i-xxxx"]);
    $t->check($attacker->post('/auth/login.php', ['csrf' => $token, 'email' => 'admin@dormfinder.test', 'password' => DEMO_PASSWORD])->has('Too many attempts'), 'Login is throttled after 10 failed attempts');

    $newStudent = new Browser($base);
    $reg = $newStudent->submit('/auth/register.php', ['name' => '<script>x()</script> Tester', 'email' => 'Tess@Example.test', 'role' => 'student',
        'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1']);
    $t->redirect($reg, '/student/dashboard.php', 'A new student can register');
    $newId = (int)val("SELECT id FROM users WHERE email='tess@example.test'");
    $t->check($newId > 0, 'Registered email is stored in lowercase');
    $dash = $newStudent->get('/student/dashboard.php');
    $t->check($dash->has('&lt;script&gt;x()&lt;/script&gt;') && !$dash->has('<script>x()'), 'User-supplied names are HTML-escaped');
    $t->check((int)val("SELECT COUNT(*) FROM activity_logs WHERE action='auth.register' AND user_id=?", [$newId]) === 1, 'Registration is logged against the new user');
    $t->redirect($newStudent->get('/auth/register.php'), '/student/dashboard.php', 'Signed-in users skip the register form');
    $dup = (new Browser($base))->submit('/auth/register.php', ['name' => 'Dup', 'email' => 'STUDENT@dormfinder.test', 'role' => 'student',
        'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1']);
    $t->check($dup->has('That email is already registered'), 'Duplicate emails are rejected regardless of case');
    $t->check((new Browser($base))->submit('/auth/register.php', ['name' => 'Sneaky', 'email' => 'sneaky@example.test', 'role' => 'admin',
        'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1'])->has('Choose a student or owner account.'), 'Nobody can register as an administrator');

    $student = new Browser($base);
    $signin = $student->login('student@dormfinder.test');
    $t->redirect($signin, '/student/dashboard.php', 'The demo student can sign in with the documented password');
    $t->check((bool)preg_match('~path=/(?:;|$)~mi', implode("\n", $signin->headers['set-cookie'] ?? [])), 'The new session cookie is scoped to the site root', implode(' | ', $signin->headers['set-cookie'] ?? []));
    $t->check(!preg_match('~href="\\\\~', $student->get('/student/dashboard.php')->body), 'Dashboard links contain no backslashes');
    $t->check((int)val("SELECT COUNT(*) FROM activity_logs WHERE action='auth.login' AND user_id=3") === 1, 'Sign-in is logged against the student, not as System');

    $t->section('Role boundaries');
    $t->redirect($guest->get('/student/dashboard.php'), '/auth/login.php', 'Guests are sent to sign in');
    foreach (['/admin/dashboard.php', '/admin/users.php', '/owner/listings.php', '/owner/add-listing.php'] as $path) $t->status($student->get($path), 403, "A student cannot open $path");

    // --- Simulated unlock ------------------------------------------------------------------
    $t->section('Simulated unlock');
    $seeded = $student->get('/dorm-details.php?id=1');
    $t->check($seeded->has('12 Sample Lane') && $seeded->has('owner@dormfinder.test'), 'A previously unlocked listing shows full details');
    $t->status($student->post('/unlock.php?id=2', ['payment_method' => 'gcash']), 419, 'An unlock without a CSRF token is rejected');
    $t->check($student->submit('/unlock.php?id=2', ['payment_method' => 'paypal'])->has('Choose a simulated payment method.'), 'Unknown payment methods are rejected');
    $paid = $student->submit('/unlock.php?id=2', ['payment_method' => 'gcash']);
    $t->redirect($paid, '/unlock-confirmation.php?reference=SIM-', 'A successful simulation redirects to its confirmation');
    $t->check($student->get($paid->location())->has('The details are yours.'), 'The confirmation page reports success');
    $t->check($student->get('/dorm-details.php?id=2')->has('24 Sample Lane'), 'Full details are visible after unlocking');
    $t->status($student->get("/image.php?id=$privateImage"), 200, 'Extra photos are served after unlocking');
    $t->redirect($student->get('/unlock.php?id=2'), '/dorm-details.php?id=2', 'Revisiting the unlock page skips straight to the details');
    $student->submit('/dorm-details.php?id=2', ['payment_method' => 'gcash'], '/unlock.php?id=2');
    $t->check((int)val("SELECT COUNT(*) FROM unlock_transactions WHERE user_id=3 AND dormitory_id=2 AND status='success'") === 1, 'Re-submitting the form does not charge twice');

    $existing = (string)val("SELECT reference FROM unlock_transactions WHERE user_id=3 AND dormitory_id=2 AND status='success'");
    $t->check(\simulate_unlock(3, 2, 'maya')['reference'] === $existing, 'The unlock service returns the existing success instead of creating another');
    $t->throws(fn() => \db()->exec("INSERT INTO unlock_transactions (reference,user_id,dormitory_id,payment_method,amount,status) VALUES ('DUP-1',3,2,'gcash',49,'success')"),
        PDOException::class, 'The database itself refuses a second successful unlock');
    $t->throws(fn() => \simulate_unlock(3, 8, 'gcash'), DomainException::class, 'The service refuses non-public listings');
    $t->throws(fn() => \simulate_unlock(2, 1, 'gcash'), DomainException::class, 'The service refuses non-student accounts');

    // Inject a database failure at the grant step, after the transaction row is inserted.
    // Both writes must roll back. This trigger exists only in the isolated test database.
    \db()->exec("CREATE TRIGGER test_deny_access BEFORE INSERT ON unlocked_listings FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Injected grant failure'");
    try {
        $t->throws(fn() => \simulate_unlock($newId, 7, 'bank'), PDOException::class, 'A database failure during access grant is reported');
        $t->check((int)val('SELECT COUNT(*) FROM unlock_transactions WHERE user_id=? AND dormitory_id=7', [$newId]) === 0, 'A grant failure rolls back the transaction record');
        $t->check((int)val('SELECT COUNT(*) FROM unlocked_listings WHERE user_id=? AND dormitory_id=7', [$newId]) === 0, 'A grant failure leaves no access permission');
    } finally {
        \db()->exec('DROP TRIGGER test_deny_access');
    }

    $failed = $student->submit('/unlock.php?id=3', ['payment_method' => 'maya', 'simulate_failure' => '1']);
    $t->check($student->get($failed->location())->has('That simulation did not complete.'), 'A failed simulation is reported as failed');
    $t->check(!$student->get('/dorm-details.php?id=3')->has('36 Sample Lane'), 'A failed simulation grants no access');
    $t->check((int)val('SELECT COUNT(*) FROM unlocked_listings WHERE user_id=3 AND dormitory_id=3') === 0, 'A failed simulation stores no unlock');
    $t->status($student->get('/unlock.php?id=8'), 404, 'Non-public listings cannot be unlocked');
    $t->status($newStudent->get('/unlock-confirmation.php?reference=DEMO-SUCCESS-0001'), 404, "Students cannot view another student's receipt");

    $t->section('Saved places');
    $student->submit('/browse.php', ['id' => '4'], '/student/favorites.php');
    $t->check((int)val('SELECT COUNT(*) FROM favorites WHERE user_id=3 AND dormitory_id=4') === 1, 'A student can save a listing');
    $student->submit('/browse.php', ['id' => '4'], '/student/favorites.php');
    $t->check((int)val('SELECT COUNT(*) FROM favorites WHERE user_id=3 AND dormitory_id=4') === 0, 'Saving again removes it');
    $t->status($student->submit('/browse.php', ['id' => '8'], '/student/favorites.php'), 404, 'Non-public listings cannot be saved');

    // --- Owner -----------------------------------------------------------------------------
    $owner = new Browser($base);
    $t->section('Owner listings');
    $t->redirect($owner->login('owner@dormfinder.test'), '/owner/dashboard.php', 'The demo owner can sign in');
    $t->status($owner->get('/owner/edit-listing.php?id=3'), 404, "An owner cannot open another owner's listing");
    $t->status($owner->post('/owner/edit-listing.php?id=3', ['csrf' => Browser::csrf($owner->get('/owner/listings.php'))] + listing_fields(3)), 404, "An owner cannot save another owner's listing");
    $t->status($owner->get('/unlock.php?id=2'), 403, 'Owners cannot buy unlocks');
    $fields = listing_fields(1);
    $t->check($owner->submit('/owner/edit-listing.php?id=1', ['available_slots' => '9'] + $fields)->has('Available slots cannot exceed capacity.'), 'Slots above capacity are rejected');
    $t->check($owner->submit('/owner/edit-listing.php?id=1', ['map_url' => 'javascript:alert(1)'] + $fields)->has('Map link must be a valid HTTPS URL'), 'Non-HTTPS map links are rejected');
    $t->redirect($owner->submit('/owner/edit-listing.php?id=1', ['available_slots' => '1'] + $fields), '/owner/listings.php', 'An owner can edit their listing');
    $t->check(row('SELECT status,available_slots FROM dormitories WHERE id=1') === ['status' => 'pending', 'available_slots' => 1], 'Edits are saved and sent back for review');
    $t->status($guest->get('/dorm-details.php?id=1'), 404, 'A listing under review leaves public search');
    $t->check($owner->get('/dorm-details.php?id=1')->has('Management preview'), 'The owner can still preview it');

    $newListing = ['name' => 'Test Harbor House', 'location' => 'Colon', 'exact_address' => 'TEST ONLY: 1 Test Street', 'university_id' => '1', 'room_type_id' => '2',
        'monthly_rent' => '4100.50', 'capacity' => '4', 'available_slots' => '2', 'description' => 'Created by the automated test suite.', 'house_rules' => 'Be kind.',
        'curfew' => '10 PM', 'security_info' => 'Test gate.', 'contact_number' => '0900-000-0099', 'map_url' => 'https://example.com/map', 'is_active' => '1', 'amenities' => ['1', '3']];
    file_put_contents("$runtime/fake.jpg", 'This is plain text pretending to be a photo.');
    $fake = $owner->submit('/owner/add-listing.php', $newListing + ['images' => [new CURLFile("$runtime/fake.jpg", 'image/jpeg', 'room.jpg')]], null, true);
    $t->check($fake->has('Images must be genuine JPEG, PNG, or WebP files'), 'A text file disguised as a JPEG is rejected');
    file_put_contents("$runtime/big.jpg", str_repeat('x', 1_400_000));
    $big = $owner->submit('/owner/add-listing.php', $newListing + ['images' => [new CURLFile("$runtime/big.jpg", 'image/jpeg', 'big.jpg')]], null, true);
    $t->check($big->status === 413 && $big->has('That upload was too large'), 'An upload over post_max_size gets a clear message, not "form expired"', "HTTP $big->status");
    $added = $owner->submit('/owner/add-listing.php', $newListing + ['images' => [new CURLFile("$app/assets/images/room-1.jpg", 'image/jpeg', 'room.jpg')]], null, true);
    $t->redirect($added, '/owner/listings.php', 'An owner can add a listing with a photo');
    $created = row("SELECT id,status,owner_id,monthly_rent FROM dormitories WHERE name='Test Harbor House'");
    $t->check(($created['status'] ?? '') === 'pending' && (int)($created['owner_id'] ?? 0) === 2 && ($created['monthly_rent'] ?? '') === '4100.50', 'New listings start pending, owned by the submitter');
    $upload = row('SELECT id,file_path,is_public,(image_data IS NOT NULL) in_db,LENGTH(image_data) bytes FROM dormitory_images WHERE dormitory_id=?', [$created['id'] ?? 0]);
    if (\db_setting('DORMFINDER_STORAGE_DRIVER', 'file') === 'database') {
        $t->check((int)($upload['is_public'] ?? 0) === 1 && (int)($upload['in_db'] ?? 0) === 1 && (int)($upload['bytes'] ?? 0) === filesize("$app/assets/images/room-1.jpg") && !array_diff(scandir($uploadDir), $uploadsBefore), 'The photo is stored in the database and nothing is written to disk');
        $t->check(hash('sha256', $owner->get('/image.php?id=' . ($upload['id'] ?? 0))->body) === hash_file('sha256', "$app/assets/images/room-1.jpg"), 'The stored photo is served back byte for byte');
    } else {
        $t->check((int)($upload['is_public'] ?? 0) === 1 && preg_match('~^uploads/dormitories/[a-f0-9]{40}\.jpg$~', $upload['file_path'] ?? '') && is_file("$app/" . ($upload['file_path'] ?? '-')), 'The photo is stored under a random name');
    }
    $t->status($guest->get('/image.php?id=' . ($upload['id'] ?? 0)), 404, "A pending listing's photo is not public");
    $t->status($owner->get('/image.php?id=' . ($upload['id'] ?? 0)), 200, 'The owner can see their pending photo');

    // --- Admin -----------------------------------------------------------------------------
    $admin = new Browser($base);
    $t->section('Admin moderation');
    $t->redirect($admin->login('admin@dormfinder.test'), '/admin/dashboard.php', 'The demo admin can sign in');
    $t->check($admin->get('/admin/dashboard.php')->has('Pending approval'), 'Admin dashboard loads');
    $moderate = fn(int $id, string $action, array $extra = []) => $admin->submit('/admin/dormitories.php', ['id' => (string)$id, 'action' => $action] + $extra);
    $t->redirect($moderate(1, 'approve'), '/admin/dormitories.php', 'Admin can approve an edited listing');
    $t->status($guest->get('/dorm-details.php?id=1'), 200, 'Approved listings are public again');
    $moderate((int)$created['id'], 'approve');
    $t->status($guest->get('/image.php?id=' . ($upload['id'] ?? 0)), 200, "Once approved, the new listing's cover photo is public");
    $t->check($moderate(10, 'reject')->has('Provide a rejection reason'), 'Rejecting requires a reason');
    $moderate(10, 'reject', ['reason' => 'Please add a clearer photo of the bathroom.']);
    $t->check(row('SELECT status,rejection_reason FROM dormitories WHERE id=10') === ['status' => 'rejected', 'rejection_reason' => 'Please add a clearer photo of the bathroom.'], 'Rejection and its reason are saved');
    $t->check($owner->get('/owner/listings.php')->has('Please add a clearer photo of the bathroom.'), 'The owner sees the rejection reason');
    $moderate(2, 'deactivate');
    $t->status($guest->get('/dorm-details.php?id=2'), 404, 'Deactivated listings leave public view');
    $moderate(2, 'activate');
    $t->status($guest->get('/dorm-details.php?id=2'), 200, 'Reactivated listings return');
    $t->check($moderate(12, 'archive')->has('Confirm archiving this listing.'), 'Archiving requires confirmation');
    $moderate(12, 'archive', ['confirm' => '1']);
    $t->check(val('SELECT deleted_at FROM dormitories WHERE id=12') !== null, 'Archiving soft-deletes the listing');
    $t->status($guest->get('/dorm-details.php?id=12'), 404, 'Archived listings are gone from public view');
    $t->status($admin->get('/admin/edit-listing.php?id=12'), 404, 'Archived listings can no longer be edited');

    $t->section('Admin reference data');
    $t->check($admin->get('/admin/universities.php')->has('University of Cebu'), 'Universities page loads');
    $t->check($admin->get('/admin/room-types.php')->has('Bedspace'), 'Room types page loads');
    $t->redirect($admin->submit('/admin/universities.php', ['action' => 'save', 'name' => 'Test University']), '/admin/universities.php', 'Admin can add a university');
    $t->check($admin->submit('/admin/universities.php', ['action' => 'save', 'name' => 'test university'])->has('That name already exists'), 'Names are unique regardless of case');
    $t->check($admin->submit('/admin/universities.php', ['action' => 'delete', 'id' => '1', 'confirm' => '1'])->has('used by a listing'), 'A university in use cannot be deleted');
    $testUni = (int)val("SELECT id FROM universities WHERE name='Test University'");
    $admin->submit('/admin/universities.php', ['action' => 'delete', 'id' => (string)$testUni, 'confirm' => '1']);
    $t->check(!val('SELECT COUNT(*) FROM universities WHERE id=?', [$testUni]), 'An unused university can be deleted');

    $t->section('Dashboards render for every role');
    $ownerHome = $owner->get('/owner/dashboard.php');
    $t->check($ownerHome->status === 200 && $ownerHome->has('class="bar-row"') && $ownerHome->has('data-count-to='), 'Owner dashboard shows count-up stats and a status chart', "HTTP $ownerHome->status");
    $adminHome = $admin->get('/admin/dashboard.php');
    $t->check($adminHome->status === 200 && substr_count($adminHome->body, 'class="panel chart-card"') === 3, 'Admin dashboard shows three charts built from database values', "HTTP $adminHome->status");
    $t->check($admin->get('/admin/dashboard.php')->has('aria-current="page"') && $adminHome->has('data-sidebar-collapse') && $adminHome->has('profile-dropdown'), 'Dashboard shell has an active nav item, collapsible sidebar and profile menu');
    $studentHome = (new Browser($base)); $studentHome->login('student@dormfinder.test'); $sh = $studentHome->get('/student/dashboard.php');
    $t->check($sh->status === 200 && $sh->has('Welcome back, Alex.') && $sh->has('Account information'), 'Student dashboard shows the welcome and account panels', "HTTP $sh->status");
    foreach (['/student/favorites.php', '/student/unlocked.php', '/student/profile.php'] as $path) $t->status($studentHome->get($path), 200, "Student page $path renders");
    foreach (['/owner/listings.php', '/owner/profile.php', '/owner/add-listing.php'] as $path) $t->status($owner->get($path), 200, "Owner page $path renders");
    foreach (['/admin/dormitories.php', '/admin/users.php', '/admin/transactions.php', '/admin/activity.php', '/admin/settings.php', '/admin/room-types.php', '/admin/add-listing.php'] as $path) $t->status($admin->get($path), 200, "Admin page $path renders");

    $t->section('Browse filters and details page');
    $filtered = $guest->get('/browse.php?university=1&max_rent=4000&sort=price_asc');
    $t->check(substr_count($filtered->body, 'class="filter-chip"') >= 4 && $filtered->has('Remove filter: Up to ₱4,000') && $filtered->has('Clear all'), 'Active filters appear as removable chips');
    $t->check($filtered->has('data-filter-open') && $filtered->has('id="filters"'), 'The filter panel doubles as the mobile drawer');
    $t->check($guest->get('/browse.php?q=zzzz-nothing')->has('No matching places just yet.'), 'An empty result shows the friendly empty state');
    $lockedPage = $guest->get('/dorm-details.php?id=2');
    $t->check($lockedPage->has('breadcrumb-nav') && $lockedPage->has('data-lightbox') && $lockedPage->has('locked-preview') && !$lockedPage->has('Sample Lane') && !$lockedPage->has('0900-000-0000'), 'Locked page has breadcrumbs, gallery and a blurred preview, with no protected data in the source');
    $t->check($guest->get('/unlock.php?id=2')->status === 302, 'Guests are sent to sign in before unlocking');

    $t->section('Admin settings, transactions and users');
    $t->check($admin->submit('/admin/settings.php', ['unlock_fee' => '-3'])->has('Enter a positive fee'), 'A negative fee is rejected');
    $admin->submit('/admin/settings.php', ['unlock_fee' => '59']);
    $t->check(val("SELECT setting_value FROM settings WHERE setting_key='unlock_fee'") === '59.00', 'The unlock fee can be changed');
    $newStudent->submit('/unlock.php?id=4', ['payment_method' => 'bank']);
    $t->check(val('SELECT amount FROM unlock_transactions WHERE user_id=? AND dormitory_id=4', [$newId]) === '59.00', 'New unlocks use the new fee');
    $t->check(val("SELECT amount FROM unlock_transactions WHERE reference='DEMO-SUCCESS-0001'") === '49.00', 'Past transactions keep their original amount');
    $failedOnly = $admin->get('/admin/transactions.php?status=failed');
    $t->check($failedOnly->has('DEMO-FAILED-0002') && !$failedOnly->has('DEMO-SUCCESS-0001'), 'Transactions can be filtered by status');
    $t->check($admin->get('/admin/activity.php?q=auth.login')->has('Alex Reyes (Demo Student)'), 'Activity log names the user who signed in');
    $t->status($admin->submit('/admin/users.php', ['id' => '1', 'status' => 'inactive']), 403, 'Administrator accounts cannot be deactivated');
    $admin->submit('/admin/users.php', ['id' => '3', 'status' => 'inactive']);
    $t->redirect($student->get('/student/dashboard.php'), '/auth/login.php', 'A deactivated student loses access on their next request');
    $t->check((new Browser($base))->login('student@dormfinder.test')->has('the account is inactive'), 'A deactivated student cannot sign back in');

    $t->section('Sign-out');
    $t->status($admin->get('/auth/logout.php'), 405, 'Sign-out requires POST');
    $t->redirect($admin->submit('/admin/dashboard.php', [], '/auth/logout.php'), '/auth/login.php', 'Admin can sign out');
    $t->redirect($admin->get('/admin/dashboard.php'), '/auth/login.php', 'The old session no longer works');

    // --- Whole-run checks ------------------------------------------------------------------
    if (\db_setting('SESSION_DRIVER', 'file') === 'database') {
        $t->section('Database sessions');
        $t->check((int)val('SELECT COUNT(*) FROM sessions') > 0 && !glob("$runtime/sessions/sess_*"), 'Sessions are stored in the database, not on disk');
        $t->check((int)val("SELECT COUNT(*) FROM sessions WHERE data LIKE '%user_id%'") >= 1, 'Signed-in sessions carry the user id');
    }
    $t->section('Clean output');
    // PHP's own oversized-POST warning is expected for the 413 test; anything else is a defect.
    $diagnostic = '~(?:<b>)?(?:Warning|Notice|Deprecated|Fatal error|Parse error)(?:</b>)?:\s|We couldn.t complete that request~';
    $noisy = array_filter(Browser::$history, fn(Response $r) => preg_match($diagnostic, preg_replace('/^.*POST Content-Length.*$/m', '', $r->body)));
    $t->check(!$noisy, 'No page printed a PHP warning or error (' . count(Browser::$history) . ' responses checked)', implode(', ', array_map(fn($r) => $r->label, $noisy)));
    $logged = array_filter(file("$runtime/php-errors.log", FILE_IGNORE_NEW_LINES) ?: [], fn($line) => trim($line) !== '' && !str_contains($line, 'POST Content-Length of'));
    $t->check(!$logged, 'The PHP error log is empty', $logged ? reset($logged) : '');
} catch (Throwable $error) {
    $t->failures[] = 'The suite stopped early: ' . $error->getMessage() . ' at ' . basename($error->getFile()) . ':' . $error->getLine();
    echo "\nSTOPPED: " . $error->getMessage() . "\n";
} finally {
    if ($server) proc_terminate($server);
    foreach (array_diff(scandir($uploadDir), $uploadsBefore) as $file) if (preg_match('/^[a-f0-9]{40}\.(?:jpg|png|webp)$/D', $file)) unlink("$uploadDir/$file");
    foreach (['fake.jpg', 'big.jpg'] as $file) @unlink("$runtime/$file");
    foreach (glob("$runtime/sessions/sess_*") ?: [] as $file) @unlink($file);
    if (!getenv('KEEP_TEST_DB')) { try { server_connection()->exec("DROP DATABASE IF EXISTS `$database`"); } catch (Throwable) {} }
}

echo "\n" . str_repeat('-', 60) . "\n";
echo $t->passed . ' passed, ' . count($t->failures) . " failed\n";
foreach ($t->failures as $failure) echo "  - $failure\n";
exit($t->failures ? 1 : 0);
