<?php
require __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/vendor/tcpdf/tcpdf_barcodes_2d.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM it_asset_inventory WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$asset) {
    $_SESSION['error'] = 'Asset not found.';
    redirect('assets.php');
}

// QR content: absolute URL to the public asset view page (anyone can scan & view)

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
  $qrCode = $base . $basePath . '/qr_view.php?id=' . $asset['id'];

$barcode = new TCPDF2DBarcode($qrCode, 'QRCODE,H');
$pngData = $barcode->getBarcodePngData(4, 4, [0, 0, 0]);

if ($pngData === false) {
    $_SESSION['error'] = 'QR generation failed: the PHP GD extension is not enabled on this server.';
    redirect('assets.php');
}

if (isset($_GET['embed']) && $_GET['embed'] === '1') {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><style>';
    echo 'body{margin:0;font-family:system-ui, sans-serif;color:#0d1a2a;background:#f8fbff;}';
    echo '.qr-frame{display:flex;align-items:center;justify-content:center;height:100vh;padding:20px;box-sizing:border-box;}';
    echo '.qr-card{background:#fff;border:1px solid #dee2e6;border-radius:.75rem;padding:24px;text-align:center;max-width:420px;width:100%;box-shadow:0 8px 24px rgba(0,0,0,.08);}';
    echo '.qr-img{width:100%;max-width:320px;height:auto;margin:0 auto;display:block;}';
    echo '.qr-code{margin-top:18px;font-size:1.3rem;font-weight:700;color:#0c2a5d;}';
    echo '.qr-model{margin-top:6px;font-size:1rem;color:#525f7f;font-weight:600;}';
    echo '.qr-note{margin-top:14px;font-size:.92rem;color:#5a6b82;}';
    echo '</style></head><body><div class="qr-frame"><div class="qr-card">';
    echo '<img class="qr-img" src="data:image/png;base64,' . base64_encode($pngData) . '" alt="QR code">';
    echo '<div class="qr-code">' . h($asset['property_code']) . '</div>';
    echo '<div class="qr-model">' . h($asset['items']) . '</div>';
    echo '<p class="qr-note">Scan this label to open the asset record.</p>';
    echo '</div></div></body></html>';
    exit;
}

$pageTitle = 'QR Label - ' . ($asset['property_code'] ?? 'Asset');
require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i data-lucide="qr-code"></i></div>
        <div>
            <h2>QR Label</h2>
            <p class="page-subtitle"><?= h($asset['property_code']) ?> &middot; <?= h($asset['items']) ?></p>
        </div>
    </div>

    <div class="assets-toolbar mb-3">
        <div class="toolbar-left">
            <button type="button" class="btn btn-primary toolbar-btn d-flex align-items-center gap-2" onclick="window.print()">
                <i data-lucide="printer"></i> Print Label
            </button>
            <a href="assets.php" class="btn btn-outline-secondary toolbar-btn d-flex align-items-center gap-2">
                <i data-lucide="arrow-left"></i> Back to Assets
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-5">
            <div class="panel">
                <div class="panel-body text-center">
                    <div class="qr-label">
                        <img class="qr-img" src="data:image/png;base64,<?= base64_encode($pngData) ?>" alt="QR code">
                        <div class="qr-code"><?= h($asset['property_code']) ?></div>
                        <div class="qr-model"><?= h($asset['items']) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="panel qr-info-panel">
                <div class="panel-title"><i data-lucide="info" class="icon-sm me-1"></i> Scan target</div>
                <div class="panel-body">
                    <p class="text-muted small mb-2">
                        Scanning this QR code with a phone camera opens the asset record directly
                        (<code><?= h($qrCode) ?></code>).
                    </p>
                    <table class="table table-sm table-striped align-middle mb-0">
                        <tbody>
                            <tr><th style="width:40%;">Property Code</th><td><?= h($asset['property_code']) ?></td></tr>
                            <tr><th>Model</th><td><?= h($asset['items']) ?></td></tr>
                            <tr><th>Location</th><td><?= h($asset['location']) ?></td></tr>
                            <tr><th>Issued To</th><td><?= h($asset['issued_to']) ?></td></tr>
                            <tr><th>Status</th><td><?= h($asset['status'] ?? 'Active') ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
    @media print {
        .app-navbar, .sidebar, .page-header, .btn, .qr-info-panel { display: none !important; }
        .main-content { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        .panel { box-shadow: none !important; border: 0 !important; }
    }
    .qr-label { padding: 14px; }
    .qr-img { width: 220px; height: 220px; image-rendering: pixelated; }
    .qr-code { font-size: 1.35rem; font-weight: 800; color: #123a73; margin-top: 8px; letter-spacing: .5px; }
    .qr-model { font-size: .9rem; color: #555; font-weight: 600; }
    </style>

<?php require __DIR__ . '/includes/footer.php'; ?>
