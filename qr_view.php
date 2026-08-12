<?php
require __DIR__ . '/config.php';

/*
 * Public QR view — accessible WITHOUT login.
 * When someone scans a QR label with their phone camera, this page
 * displays the asset details so anyone can see them immediately.
 */

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$code = !empty($_GET['code']) ? trim((string)$_GET['code']) : '';

$asset = null;

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM it_asset_inventory WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();
    $stmt->close();
} elseif ($code !== '') {
    $stmt = $conn->prepare("SELECT * FROM it_asset_inventory WHERE property_code = ?");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $asset = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$asset) {
    http_response_code(404);
    $pageTitle = 'Asset Not Found';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Asset Not Found &mdash; <?= APP_NAME ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="icon" href="<?= LOGO_FILE ?>">
        <style>
            body { background: #f2f4f8; font-family: "Segoe UI", Arial, Helvetica, sans-serif; }
            .not-found { max-width: 480px; margin: 4rem auto; text-align: center; }
            .not-found .display-1 { font-size: 3rem; color: #94a3b8; }
        </style>
    </head>
    <body>
        <div class="not-found">
            <div class="display-1 mb-3">?</div>
            <h2>Asset Not Found</h2>
            <p class="text-muted">No asset was found for the QR code you scanned.</p>
            <a href="login.php" class="btn btn-primary">Sign In to Inventory System</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$pageTitle = ($asset['property_code'] ?? 'Asset') . ' - QR View';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { background: #f2f4f8; }
        .qr-view-header {
            background: #fff;
            border-radius: 12px;
            padding: 1.5rem 1.75rem;
            box-shadow: 0 1px 3px rgba(16, 35, 75, .08);
            margin-bottom: 1.25rem;
        }
        .qr-view-header h2 { margin: 0; font-size: 1.4rem; font-weight: 700; color: #0d2a5e; }
        .qr-view-header .text-muted { font-size: .9rem; }
        .asset-detail-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(16, 35, 75, .08);
            overflow: hidden;
        }
        .asset-detail-card .table thead th {
            background: #0d2a5e;
            color: #fff;
            font-size: .8rem;
            text-transform: uppercase;
            letter-spacing: .4px;
            border: none;
            padding: .6rem .1rem;
        }
        .asset-detail-card .table td {
            padding: .6rem;
            border-bottom: 1px solid #eef1f6;
            font-size: .9rem;
            vertical-align: middle;
        }
        .asset-detail-card .table tbody tr td:first-child {
            font-weight: 600;
            color: #33415c;
            width: 40%;
        }
        .badge-status { font-size: .8rem; padding: .4rem .7rem; }
        .qr-view-footer {
            text-align: center;
            margin-top: 1.25rem;
            padding: 1rem;
        }
        .qr-view-footer .small { color: #6b7590; }
        .logo-sm { width: 36px; height: 36px; object-fit: contain; }
        @media (max-width: 576px) {
            .qr-view-header h2 { font-size: 1.15rem; }
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="qr-view-header d-flex align-items-center gap-3">
        <img src="<?= LOGO_FILE ?>" alt="Logo" class="logo-sm">
        <div class="flex-grow-1">
            <h2><?= h($asset['property_code'] ?? 'Asset') ?></h2>
            <div class="text-muted"><?= h($asset['items'] ?? '') ?></div>
        </div>
    </div>

    <div class="asset-detail-card">
        <table class="table table-sm table-striped align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col">Field</th>
                    <th scope="col">Value</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $st = $asset['status'] ?? 'Active';
                $sc = $st === 'Active' ? 'success' : ($st === 'In Repair' ? 'warning text-dark' : ($st === 'Disposed' ? 'secondary' : 'info'));
                ?>
                <tr>
                    <td>Status</td>
                    <td><span class="badge bg-<?= $sc ?> badge-status"><?= h($st) ?></span></td>
                </tr>
                <tr>
                    <td>Property Code</td>
                    <td><?= h($asset['property_code']) ?></td>
                </tr>
                <tr>
                    <td>Model / Item</td>
                    <td><?= h($asset['items']) ?></td>
                </tr>
                <tr>
                    <td>Location</td>
                    <td><?= h($asset['location']) ?></td>
                </tr>
                <tr>
                    <td>Life Span</td>
                    <td><?= h($asset['life_span']) ?></td>
                </tr>
                <tr>
                    <td>Acquisition Date</td>
                    <td><?= h($asset['acquisition_date']) ?></td>
                </tr>
                <tr>
                    <td>Disposal Method</td>
                    <td><?= h($asset['disposal_method']) ?></td>
                </tr>
                <tr>
                    <td>Issued To</td>
                    <td><?= h($asset['issued_to']) ?></td>
                </tr>
                <tr>
                    <td>Remarks</td>
                    <td><?= h($asset['remarks']) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="qr-view-footer">
        <p class="small mb-0">
            <img src="<?= LOGO_FILE ?>" alt="" class="logo-sm me-1">
            <strong><?= APP_NAME ?></strong> &middot; COMPASS Maritime Training Center
        </p>
        <p class="small mb-0">
            <a href="login.php" class="text-decoration-none">Sign in for full asset management</a>
        </p>
    </div>
</div>

<script>
    lucide.createIcons();
</script>
</body>
</html>