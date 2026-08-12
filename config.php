<?php
if (session_status() === PHP_SESSION_NONE) {
    session_cache_limiter('nocache');
    session_start();
}

// ------------------------------------------------------------------
// Database configuration (XAMPP / MariaDB)
// ------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'invent');

// ------------------------------------------------------------------
// Fixed document control values (do NOT auto-generate / change)
// ------------------------------------------------------------------
define('DOC_ID', 'ITD-03F1');
define('DOC_REVISION', '0');
define('DOC_DATE_APPROVED', '14-Dec-2024');
define('DOC_CLASSIFICATION', 'Internal');
define('DOC_BRANCH', 'ILOILO');
define('DOC_DEPARTMENT', 'All Departments');
define('DOC_TITLE', 'INVENTORY LIST OF I.T. INFRASTRUCTURE');
define('DOC_ORG', 'COMPASS | ILOILO');

define('APP_NAME', 'IT Asset Inventory');
define('LOGO_FILE', '2.png');

// ------------------------------------------------------------------
// Database connection
// ------------------------------------------------------------------
$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

// ------------------------------------------------------------------
// Asset categories derived from the property code prefix (IL-XXX-...)
// ------------------------------------------------------------------
function asset_categories(): array
{
    return [
        'LTP' => ['label' => 'Laptop',           'lifespan' => '3-5 years'],
        'MTR' => ['label' => 'Monitor',          'lifespan' => '3-5 years'],
        'CPU' => ['label' => 'System Unit',      'lifespan' => '5-8 years'],
        'CTV' => ['label' => 'CCTV',             'lifespan' => '4-7 years'],
        'PRT' => ['label' => 'Printer',          'lifespan' => '3-5 years'],
        'UPS' => ['label' => 'UPS',              'lifespan' => '7-10 years'],
        'TAB' => ['label' => 'Tablet',           'lifespan' => '3-5 years'],
        'MSE' => ['label' => 'Mouse',            'lifespan' => '3-5 years'],
        'KYB' => ['label' => 'Keyboard',         'lifespan' => '5-10 years'],
        'RTR' => ['label' => 'Router',           'lifespan' => '3-5 years'],
        'SWT' => ['label' => 'Switch',           'lifespan' => '3-5 years'],
        'SPE' => ['label' => 'Speaker',          'lifespan' => '5-10 years'],
        'CAM' => ['label' => 'Webcam',           'lifespan' => '3-5 years'],
        'HST' => ['label' => 'Headset',          'lifespan' => '2-3 years'],
        'SIG' => ['label' => 'Signal Booster',   'lifespan' => '3-5 years'],
    ];
}

function category_label(string $prefix): string
{
    $cats = asset_categories();
    return $cats[$prefix]['label'] ?? $prefix;
}

function category_lifespan(string $prefix): string
{
    $cats = asset_categories();
    return $cats[$prefix]['lifespan'] ?? '';
}

function category_prefix_from_code(string $propertyCode): string
{
    if (preg_match('/^IL-([A-Z]{3})-/i', $propertyCode, $m)) {
        return strtoupper($m[1]);
    }
    return '';
}

// ------------------------------------------------------------------
// Helpers
// ------------------------------------------------------------------
function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

function is_admin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        redirect('assets.php');
    }
}

// ------------------------------------------------------------------
// Auto-generate the next property code for a category prefix
// Format: IL-<PREFIX>-<CURRENT YEAR>-<max number in that prefix + 1>
// ------------------------------------------------------------------
function next_property_code(mysqli $conn, string $prefix): string
{
    $prefix = strtoupper(substr($prefix, 0, 3));
    $year   = date('Y');
    $like   = "IL-" . $prefix . "-%";
    $stmt   = $conn->prepare(
        "SELECT property_code FROM it_asset_inventory
         WHERE property_code LIKE ?"
    );
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();

    $max = 0;
    while ($row = $res->fetch_assoc()) {
        if (preg_match('/^IL-' . $prefix . '-(?:[0-9]{4}|N\/A)?-?(\d{1,5})$/i', trim($row['property_code']), $m)) {
            $n = (int)$m[1];
            if ($n > $max) {
                $max = $n;
            }
        }
    }
    $stmt->close();

    return 'IL-' . $prefix . '-' . $year . '-' . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
}

// Generate the edit page's form url preserved filters for "add asset"
function keep_query(array $exclude = []): string
{
    $q = $_GET;
    foreach ($exclude as $k) {
        unset($q[$k]);
    }
    return http_build_query($q);
}

// ------------------------------------------------------------------
// CSRF protection
// ------------------------------------------------------------------
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $token !== '' && $stored !== '' && hash_equals($stored, $token);
}

function require_csrf(): void
{
    if (!verify_csrf()) {
        http_response_code(403);
        die('Invalid or missing CSRF token. Please go back and try again.');
    }
}

// ------------------------------------------------------------------
// Audit logging
// ------------------------------------------------------------------
function audit_log(mysqli $conn, string $action, string $details = ''): void
{
    $user = (string)($_SESSION['username'] ?? 'system');
    $stmt = $conn->prepare("INSERT INTO audit_log (username, action, details) VALUES (?, ?, ?)");
    $stmt->bind_param('sss', $user, $action, $details);
    $stmt->execute();
    $stmt->close();
}

// ------------------------------------------------------------------
// Settings store (editable document-control values, etc.)
// ------------------------------------------------------------------
function settings_get(mysqli $conn, string $key, string $default = ''): string
{
    $stmt = $conn->prepare("SELECT value FROM settings WHERE skey = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $r ? (string)$r['value'] : $default;
}

function settings_set(mysqli $conn, string $key, string $value): void
{
    $stmt = $conn->prepare(
        "INSERT INTO settings (skey, value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE value = VALUES(value)"
    );
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}

// ------------------------------------------------------------------
// Password policy
// ------------------------------------------------------------------
function password_policy_error(string $pw): string
{
    if (strlen($pw) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'Password must contain at least one letter and one number.';
    }
    return '';
}

// ------------------------------------------------------------------
// Prepared-statement query runner (params bound as i/s based on type)
// ------------------------------------------------------------------
function run_query(mysqli $conn, string $sql, array $params = []): mysqli_result
{
    if (count($params) === 0) {
        return $conn->query($sql);
    }
    $types = '';
    foreach ($params as $p) {
        $types .= is_int($p) ? 'i' : 's';
    }
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die('Query prepare failed: ' . $conn->error);
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result();
}
