<?php
/**
 * One-time setup: creates the users table and seeds the default admin account.
 * Run via browser: http://localhost/invent/setup.php
 */
require __DIR__ . '/config.php';

$messages = [];

$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(100) DEFAULT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$messages[] = 'users table ready.';

$check = $conn->query("SELECT COUNT(*) AS c FROM users WHERE username = 'admin'");
if ($check && (int)$check->fetch_assoc()['c'] === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password_hash, display_name, role) VALUES (?, ?, ?, 'admin')");
    $name = 'Administrator';
    $user = 'admin';
    $stmt->bind_param('sss', $user, $hash, $name);
    $stmt->execute();
    $messages[] = 'Default admin created (username: admin / password: admin123).';
} else {
    $messages[] = 'Default admin already exists.';
}

// Ensure asset table has AUTO_INCREMENT (existing records untouched)
$conn->query("ALTER TABLE it_asset_inventory MODIFY id INT NOT NULL AUTO_INCREMENT");
$messages[] = 'AUTO_INCREMENT ensured on it_asset_inventory.';

// Add a status column (management field) if it does not exist yet
$hasStatus = $conn->query("SHOW COLUMNS FROM it_asset_inventory LIKE 'status'");
if ($hasStatus && $hasStatus->num_rows === 0) {
    $conn->query("ALTER TABLE it_asset_inventory ADD COLUMN status ENUM('Active','In Repair','Disposed') NOT NULL DEFAULT 'Active' AFTER issued_to");
    $messages[] = 'status column added to it_asset_inventory.';
} else {
    $messages[] = 'status column already present.';
}

// ------------------------------------------------------------------
// Feature tables (audit log, settings, history, maintenance, login throttle)
// ------------------------------------------------------------------
$conn->query("CREATE TABLE IF NOT EXISTS audit_log (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL DEFAULT '',
    action VARCHAR(100) NOT NULL,
    details TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY username (username), KEY created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$messages[] = 'audit_log table ready.';

$conn->query("CREATE TABLE IF NOT EXISTS settings (
    skey VARCHAR(100) NOT NULL PRIMARY KEY,
    value TEXT,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$messages[] = 'settings table ready.';

$conn->query("CREATE TABLE IF NOT EXISTS asset_history (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    changed_by VARCHAR(50) DEFAULT '',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY asset_id (asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$messages[] = 'asset_history table ready.';

$conn->query("CREATE TABLE IF NOT EXISTS maintenance_log (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    maintenance_date DATE DEFAULT NULL,
    description TEXT,
    performed_by VARCHAR(100) DEFAULT '',
    cost DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY asset_id (asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$messages[] = 'maintenance_log table ready.';

$conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY lookup (username, ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
$messages[] = 'login_attempts table ready.';

// Seed default settings (used if the Settings page has not been customised yet)
$defaults = [
    'doc_id'            => DOC_ID,
    'doc_revision'      => DOC_REVISION,
    'doc_date_approved' => DOC_DATE_APPROVED,
    'doc_classification'=> DOC_CLASSIFICATION,
    'doc_branch'        => DOC_BRANCH,
    'doc_department'    => DOC_DEPARTMENT,
    'doc_title'         => DOC_TITLE,
    'doc_org'           => DOC_ORG,
];
foreach ($defaults as $k => $v) {
    $stmt = $conn->prepare("INSERT IGNORE INTO settings (skey, value) VALUES (?, ?)");
    $stmt->bind_param('ss', $k, $v);
    $stmt->execute();
    $stmt->close();
}
$messages[] = 'Default settings seeded.';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container" style="max-width: 640px; margin-top: 80px;">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <strong>Inventory System Setup</strong>
            </div>
            <div class="card-body">
                <ul class="mb-3">
                    <?php foreach ($messages as $msg): ?>
                        <li><?= h($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="login.php" class="btn btn-primary">Go to Login</a>
            </div>
        </div>
        <p class="text-muted mt-3 text-center small">
            For security, delete <code>setup.php</code> after running it.
        </p>
    </div>
</body>
</html>
