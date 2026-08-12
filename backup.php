<?php
require __DIR__ . '/config.php';
require_login();
require_admin();

$pageTitle = 'Database Backup';

// ---- Download request ----
if (isset($_GET['download'])) {
    audit_log($conn, 'BACKUP', 'Database backup downloaded.');
    $stamp = date('Ymd_His');
    $filename = 'invent_backup_' . $stamp . '.sql';

    $mysqldump = 'C:\xampp2\mysql\bin\mysqldump.exe';
    $sql = '';

    if (is_file($mysqldump)) {
        $cmd = '"' . $mysqldump . '" -u' . DB_USER . ' --no-tablespaces --add-drop-table ' . DB_NAME . ' 2>&1';
        $out = [];
        $code = 0;
        exec($cmd, $out, $code);
        if ($code === 0) {
            $sql = implode("\n", $out);
        }
    }

    if ($sql === '') {
        $sql = php_dump_database($conn);
    }

    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo $sql;
    exit;
}

require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i data-lucide="database-backup"></i></div>
        <div>
            <h2>Database Backup</h2>
            <p class="page-subtitle">Download a snapshot of the inventory database</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="panel">
                <div class="panel-title"><i data-lucide="archive" class="icon-sm me-1"></i> Backup the database</div>
                <div class="panel-body">
                    <p class="text-muted">
                        This creates a <code>.sql</code> dump of the entire <code><?= h(DB_NAME) ?></code>
                        database (assets, users, audit log, settings, history and maintenance records).
                    </p>
                    <div class="d-flex gap-2">
                        <a href="backup.php?download=1" class="btn btn-primary d-flex align-items-center gap-2">
                            <i data-lucide="download"></i> Download Backup
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel">
                <div class="panel-title"><i data-lucide="info" class="icon-sm me-1"></i> Restoring</div>
                <div class="panel-body">
                    <p class="text-muted small mb-0">
                        To restore, import the downloaded file using phpMyAdmin or the
                        MySQL command line: <code>mysql -uroot invent &lt; backup.sql</code>
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
// ------------------------------------------------------------------
// Fallback: dump the database with plain PHP when mysqldump is missing
// ------------------------------------------------------------------
function php_dump_database(mysqli $conn): string
{
    $out  = "-- IT Asset Inventory database backup\n";
    $out .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $out .= "-- Database: " . DB_NAME . "\n\n";

    $tables = $conn->query("SHOW TABLES");
    while ($t = $tables->fetch_row()) {
        $table = (string)$t[0];
        $out .= "DROP TABLE IF EXISTS `{$table}`;\n";

        $create = $conn->query("SHOW CREATE TABLE `{$table}`")->fetch_assoc();
        $out .= ($create['Create Table'] ?? '') . ";\n\n";

        $rows = $conn->query("SELECT * FROM `{$table}`");
        $cols = [];
        if ($fieldInfo = $rows->fetch_fields()) {
            foreach ($fieldInfo as $f) {
                $cols[] = '`' . $f->name . '`';
            }
        }
        $rows->data_seek(0);

        $colSql = implode(', ', $cols);
        while ($row = $rows->fetch_assoc()) {
            $vals = [];
            foreach ($row as $v) {
                $vals[] = ($v === null) ? 'NULL' : "'" . str_replace(["'", "\\"], ["''", "\\\\"], (string)$v) . "'";
            }
            $out .= "INSERT INTO `{$table}` ({$colSql}) VALUES (" . implode(', ', $vals) . ");\n";
        }
        $out .= "\n";
    }

    return $out;
}
