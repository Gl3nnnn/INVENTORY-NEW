<?php
require __DIR__ . '/config.php';
require_login();
require_admin();
require_once __DIR__ . '/includes/xlsx.php';

$pageTitle = 'Import Assets';

$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $mode = ($_POST['mode'] ?? 'skip') === 'update' ? 'update' : 'skip';

    if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Please choose a CSV or XLSX file to upload.';
        redirect('import.php');
    }

    $upload = $_FILES['file'];
    $ext = strtolower(pathinfo((string)$upload['name'], PATHINFO_EXTENSION));

    if ($ext === 'xlsx') {
        $rows = xlsx_read_rows($upload['tmp_name']);
    } else {
        $rows = parse_csv_file($upload['tmp_name']);
    }

    if (count($rows) < 2) {
        $_SESSION['error'] = 'The file does not contain any data rows.';
        redirect('import.php');
    }

    // Detect header row (first row starts with "ID")
    $headerRow = false;
    $colMap = null;
    if (strcasecmp(trim((string)($rows[0][0] ?? '')), 'ID') === 0) {
        $headerRow = true;
        $colMap = [];
        $labels = array_map(fn($h) => strtolower(preg_replace('/[^a-z0-9]/', '', (string)$h)), $rows[0]);
        foreach ($labels as $i => $label) {
            $colMap[$label] = $i;
        }
        array_shift($rows);
    }

    $col = function (array $row, string $name, int $positional) use ($colMap) {
        if ($colMap !== null) {
            $idx = $colMap[$name] ?? null;
            return $idx !== null ? trim((string)($row[$idx] ?? '')) : '';
        }
        return trim((string)($row[$positional] ?? ''));
    };

    $cats = asset_categories();
    $statuses = asset_statuses();
    $changedBy = (string)($_SESSION['username'] ?? '');
    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $i => $row) {
        $line = $i + ($headerRow ? 2 : 1);
        if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
            continue; // blank row
        }

        $model  = $col($row, 'model', 0);
        $code   = $col($row, 'propertycode', 2);
        $cat    = $col($row, 'category', 0);
        $status = $col($row, 'status', 6);

        if ($model === '') {
            $errors[] = "Line $line: Model is required. Row skipped.";
            continue;
        }

        // Resolve category: explicit column, else from property-code prefix
        $prefix = '';
        foreach ($cats as $p => $info) {
            if (strcasecmp($cat, $info['label']) === 0 || strcasecmp($cat, $p) === 0) {
                $prefix = $p;
                break;
            }
        }
        if ($prefix === '') {
            $prefix = category_prefix_from_code($code);
        }

        // Auto-generate the property code when missing
        if ($code === '') {
            if ($prefix === '' || !isset($cats[$prefix])) {
                $errors[] = "Line $line: Property Code is empty and category could not be determined. Row skipped.";
                continue;
            }
            $code = next_property_code($conn, $prefix);
        }

        $location  = $col($row, 'location', 1);
        $lifeSpan  = $col($row, 'lifespan', 3);
        if ($lifeSpan === '' && $prefix !== '' && isset($cats[$prefix])) {
            $lifeSpan = $cats[$prefix]['lifespan'];
        }
        $acqDate   = $col($row, 'acquisitiondate', 4);
        if ($acqDate === '' || strcasecmp($acqDate, 'N/A') === 0) {
            $acqDate = 'N/A';
        }
        $disposal  = $col($row, 'disposalmethod', 5);
        $remarks   = $col($row, 'remarks', 7);
        $issuedTo  = $col($row, 'issuedto', 8);
        if ($status === '' || !in_array($status, $statuses, true)) {
            $status = 'Active';
        }

        // Duplicate check
        $stmt = $conn->prepare("SELECT id, items FROM it_asset_inventory WHERE property_code = ?");
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            if ($mode === 'skip') {
                $skipped++;
                continue;
            }
            $stmt = $conn->prepare(
                "UPDATE it_asset_inventory
                 SET items = ?, location = ?, life_span = ?, acquisition_date = ?,
                     disposal_method = ?, remarks = ?, issued_to = ?, status = ?
                 WHERE id = ?"
            );
            $stmt->bind_param('ssssssssi',
                $model, $location, $lifeSpan, $acqDate,
                $disposal, $remarks, $issuedTo, $status, (int)$existing['id']);
            $stmt->execute();
            $stmt->close();
            $updated++;
            continue;
        }

        $stmt = $conn->prepare(
            "INSERT INTO it_asset_inventory
                (items, location, property_code, life_span, acquisition_date, disposal_method, remarks, issued_to, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('sssssssss',
            $model, $location, $code, $lifeSpan,
            $acqDate, $disposal, $remarks, $issuedTo, $status);
        $stmt->execute();
        $newId = (int)$stmt->insert_id;
        $stmt->close();

        if ($issuedTo !== '') {
            $hs = $conn->prepare("INSERT INTO asset_history (asset_id, field_name, old_value, new_value, changed_by) VALUES (?, 'Issued To', '', ?, ?)");
            $hs->bind_param('iss', $newId, $issuedTo, $changedBy);
            $hs->execute();
            $hs->close();
        }
        $inserted++;
    }

    audit_log($conn, 'IMPORT', "Bulk import finished: $inserted inserted, $updated updated, $skipped skipped, " . count($errors) . " errors.");
    $summary = [
        'inserted' => $inserted,
        'updated'  => $updated,
        'skipped'  => $skipped,
        'errors'   => $errors,
    ];
}

require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i data-lucide="upload"></i></div>
        <div>
            <h2>Import Assets</h2>
            <p class="page-subtitle">Bulk-add or update assets from a CSV or Excel file</p>
        </div>
    </div>

    <?php if ($summary): ?>
    <div class="panel mb-4">
        <div class="panel-title"><i data-lucide="clipboard-check" class="icon-sm me-1"></i> Import Summary</div>
        <div class="panel-body">
            <div class="row g-3 text-center">
                <div class="col-4">
                    <div class="stat-value text-success"><?= $summary['inserted'] ?></div>
                    <div class="stat-label">Inserted</div>
                </div>
                <div class="col-4">
                    <div class="stat-value text-primary"><?= $summary['updated'] ?></div>
                    <div class="stat-label">Updated</div>
                </div>
                <div class="col-4">
                    <div class="stat-value text-secondary"><?= $summary['skipped'] ?></div>
                    <div class="stat-label">Skipped (duplicates)</div>
                </div>
            </div>
            <?php if ($summary['errors']): ?>
                <hr>
                <p class="fw-bold small text-danger mb-2"><?= count($summary['errors']) ?> row(s) failed:</p>
                <ul class="small text-muted mb-0" style="max-height: 200px; overflow: auto;">
                    <?php foreach (array_slice($summary['errors'], 0, 100) as $err): ?>
                        <li><?= h($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-7">
            <div class="panel">
                <div class="panel-title"><i data-lucide="file-up" class="icon-sm me-1"></i> Upload file</div>
                <div class="panel-body">
                    <form method="post" enctype="multipart/form-data" action="import.php">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="file">CSV or Excel (.xlsx) file</label>
                            <input type="file" class="form-control" id="file" name="file" accept=".csv,.xlsx" required>
                            <div class="form-text">Max 5 MB. The first row may be a header (ID, Model, Location, ...) as exported by this system.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">When a property code already exists</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" id="modeSkip" value="skip" checked>
                                <label class="form-check-label" for="modeSkip">Skip the row (recommended)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" id="modeUpdate" value="update">
                                <label class="form-check-label" for="modeUpdate">Update the existing asset</label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
                            <i data-lucide="upload"></i> Import Now
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="panel">
                <div class="panel-title"><i data-lucide="info" class="icon-sm me-1"></i> File format</div>
                <div class="panel-body">
                    <p class="text-muted small mb-2">
                        Recognized columns (header names are matched loosely):
                    </p>
                    <ul class="small text-muted mb-3">
                        <li><strong>ID</strong> - internal ID (ignored)</li>
                        <li><strong>Model</strong> - required</li>
                        <li><strong>Property Code</strong> - blank = auto-generated</li>
                        <li><strong>Category</strong> - label or prefix (e.g. Laptop / LTP)</li>
                        <li><strong>Location, Life Span, Acquisition Date, Disposal Method, Status, Remarks, Issued To</strong></li>
                    </ul>
                    <a href="export.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
                        <i data-lucide="file-text" class="icon-sm"></i> Download CSV template
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
function parse_csv_file(string $path): array
{
    $rows = [];
    if (($handle = fopen($path, 'r')) === false) {
        return $rows;
    }
    while (($data = fgetcsv($handle)) !== false) {
        $rows[] = $data;
    }
    fclose($handle);
    return $rows;
}
