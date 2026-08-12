<?php
require __DIR__ . '/config.php';
require_login();
require __DIR__ . '/includes/filters.php';
require_once __DIR__ . '/vendor/tcpdf/tcpdf_barcodes_2d.php';

$pageTitle = 'QR Stickers';
$result = fetch_filtered_assets($conn, 'id ASC');
$totalRows = $result->num_rows;

require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i class="bi bi-qr-code"></i></div>
        <div>
            <h2>QR Stickers</h2>
            <p class="page-subtitle">Generate a printable A4 sheet of small QR stickers for your assets.</p>
        </div>
    </div>

    <div class="assets-toolbar mb-3">
        <div class="toolbar-left">
            <button type="button" class="btn btn-primary toolbar-btn d-flex align-items-center gap-2" id="printStickersBtn">
                <i data-lucide="printer"></i> Print / Download Stickers
            </button>
            <a href="assets.php" class="btn btn-outline-secondary toolbar-btn d-flex align-items-center gap-2">
                <i data-lucide="hard-drive"></i> Back to Assets
            </a>
        </div>
        <div class="toolbar-right">
            <div class="text-muted">
                <?= $totalRows ?> asset<?= $totalRows === 1 ? '' : 's' ?> included
            </div>
        </div>
    </div>

    <?php if ($totalRows === 0): ?>
        <div class="alert alert-warning">No assets match the current filters.</div>
    <?php else: ?>
        <div class="stickers-grid">
            <?php
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base   = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
            $barcode = new TCPDF2DBarcode('', 'QRCODE,H');
            while ($row = $result->fetch_assoc()):
                $qrCode = $base . $basePath . '/qr_view.php?id=' . (int)$row['id'];
                $barcode->setBarcode($qrCode, 'QRCODE,H');
                $pngData = $barcode->getBarcodePngData(4, 4, [0, 0, 0]);
                if ($pngData === false):
            ?>
                    <div class="alert alert-danger">
                        QR generation failed: the PHP GD extension is not enabled on this server.
                    </div>
                <?php
                    $result->close();
                    require __DIR__ . '/includes/footer.php';
                    exit;
                endif;
            ?>
                <div class="sticker-card">
                    <div class="sticker-qr">
                        <img src="data:image/png;base64,<?= base64_encode($pngData) ?>" alt="QR code">
                    </div>
                    <div class="sticker-text">
                        <div class="sticker-code"><?= h($row['property_code']) ?></div>
                        <div class="sticker-item"><?= h($row['items']) ?></div>
                        <div class="sticker-location"><?= h($row['location']) ?></div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>

    <style>
    .stickers-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        justify-content: center;
    }
    .sticker-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        gap: 6px;
        border: 1px solid #c3c7cf;
        border-radius: 10px;
        padding: 10px;
        background: #fff;
        min-height: 140px;
        box-sizing: border-box;
        page-break-inside: avoid;
        break-inside: avoid-column;
    }
    .sticker-qr {
        width: 90px;
        height: 90px;
        display: grid;
        place-items: center;
        margin: 0 auto;
    }
    .sticker-qr img {
        width: 88px;
        height: 88px;
        object-fit: contain;
    }
    .sticker-text {
        width: 100%;
    }
    .sticker-code {
        font-size: 0.92rem;
        font-weight: 700;
        color: #0d1a2a;
        margin-bottom: 3px;
        white-space: normal;
        overflow: visible;
        text-overflow: unset;
    }
    .sticker-item,
    .sticker-location {
        font-size: 0.8rem;
        color: #333;
        line-height: 1.25;
        white-space: normal;
        overflow: visible;
        text-overflow: unset;
    }
    .sticker-item { font-weight: 600; }
    .sticker-location { margin-top: 2px; }

    @page { size: A4 portrait; margin: 10mm; }
    @media print {
        body { background: #fff !important; color: #000 !important; }
        .app-navbar,
        .sidebar,
        .page-header,
        .btn,
        .filters-card,
        .text-muted,
        .print-toolbar { display: none !important; }
        .main-content { margin: 0; padding: 0; }
        .stickers-grid { grid-template-columns: repeat(4, minmax(150px, 1fr)); gap: 8px; }
        .sticker-card { border-color: #999; padding: 10px; min-height: 150px; }
        .sticker-code { font-size: 0.95rem; }
        .sticker-item,
        .sticker-location { font-size: 0.82rem; }
        .sticker-qr { width: 84px; height: 84px; }
        .sticker-qr img { width: 82px; height: 82px; }
        .sticker-card { page-break-inside: avoid; break-inside: avoid; }
    }
    </style>

    <script>
    document.getElementById('printStickersBtn').addEventListener('click', function () {
        window.print();
    });
    </script>

<?php require __DIR__ . '/includes/footer.php'; ?>
