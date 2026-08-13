<?php
require __DIR__ . '/config.php';
require_login();
require __DIR__ . '/includes/filters.php';

$rows = [];

// Explicit selection (?ids=1,2,3) takes precedence; otherwise fall back to the
// same filters used by the assets page / print.php.
$idsParam = isset($_GET['ids']) ? trim((string)$_GET['ids']) : '';
if ($idsParam !== '') {
    $ids = [];
    foreach (preg_split('/[,;]/', $idsParam) as $part) {
        $n = (int)trim($part);
        if ($n > 0) {
            $ids[$n] = $n;
        }
    }
    $ids = array_values($ids);
    if (count($ids) > 1000) {
        $ids = array_slice($ids, 0, 1000);
    }
    if (count($ids) > 0) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $orderBy = implode(',', $ids);
        $sql = "SELECT * FROM it_asset_inventory
                WHERE id IN ($placeholders)
                ORDER BY FIELD(id, $orderBy)";
        $res = run_query($conn, $sql, $ids);
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
} else {
    $res = fetch_filtered_assets($conn, 'id ASC');
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
}

$total = count($rows);
$pages = array_chunk($rows, 28);
$totalPages = count($pages);

function label_card_html(array $row): string
{
    $code = display_value($row['property_code'] ?? '');
    $item = display_value($row['items'] ?? '');
    $loc  = display_value($row['location'] ?? '');

    return '<div class="label-card">'
        . '<img class="label-logo" src="2.png" alt="COMPASS Logo">'
        . '<div class="label-info">'
        . '<div class="label-field"><strong>Property Code:</strong><span class="label-value">' . e($code) . '</span></div>'
        . '<div class="label-field"><strong>Item:</strong><span class="label-value">' . e($item) . '</span></div>'
        . '<div class="label-field"><strong>Location:</strong><span class="label-value">' . e($loc) . '</span></div>'
        . '</div>'
        . '<img class="label-watermark" src="4.png" alt="">'
        . '</div>';
}

$sheetsHtml = '';
foreach ($pages as $pageRows) {
    $cards = '';
    foreach ($pageRows as $row) {
        $cards .= label_card_html($row);
    }
    for ($i = count($pageRows); $i < 28; $i++) {
        $cards .= '<div class="label-card label-blank"></div>';
    }
    $sheetsHtml .= '<div class="labels-sheet">' . $cards . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Property Labels - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body { margin: 0; padding: 0; }
        body { background: #fff; font-family: Arial, Helvetica, sans-serif; }

        /* ---- screen-only toolbar ---- */
        .print-toolbar {
            position: sticky; top: 0; z-index: 20;
            background: #fff; border-bottom: 1px solid #dfe5ee;
            padding: 12px 18px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px;
        }

        /* ---- A4 landscape sheet: fixed 4 x 7 grid (28 labels), fills the page ---- */
        .labels-sheet {
            width: 297mm;
            height: 210mm;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            grid-template-rows: repeat(7, 1fr);
            gap: 0;
            background: #fff;
            box-sizing: border-box;
            margin: 0 auto;
            page-break-after: always;
            break-after: page;
        }
        .labels-sheet:last-child { page-break-after: auto; break-after: auto; }

        /* ---- individual label ---- */
        .label-card {
            position: relative;
            border: 1px solid #777;
            background: #fff;
            box-sizing: border-box;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            padding: 6px 8px;
            break-inside: avoid;
            page-break-inside: avoid;
        }
        .label-blank { display: block; }

        /* ---- logo (actual 2.png), top-left, aspect preserved ---- */
        .label-logo {
            width: 130px;
            height: auto;
            object-fit: contain;
            display: block;
            max-width: 100%;
            margin-right: auto;
        }

        .label-info {
            position: relative;
            z-index: 2;
            margin-top: 5px;
        }

        /* ---- fields: bold name + value line that fills the remaining label width ---- */
        .label-field {
            font-size: 9.5px;
            line-height: 1.4;
            color: #111;
            margin-top: 3px;
            display: grid;
            grid-template-columns: 82px 1fr;
            width: 100%;
            align-items: end;
        }
        .label-field:first-child { margin-top: 0; }
        .label-field strong {
            font-weight: 700;
            white-space: nowrap;
            padding-right: 4px;
        }
        .label-value {
            min-width: 0;
            width: 100%;
            border-bottom: 1px solid #222;
            padding-bottom: 1px;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        /* ---- watermark (actual 4.png): large, bottom-right, behind the text, prints reliably ---- */
        .label-watermark {
            position: absolute;
            right: 2px;
            bottom: 0;
            width: 110px;
            height: 110px;
            object-fit: contain;
            opacity: 0.40;
            z-index: 0;
            pointer-events: none;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .no-data-box {
            max-width: 560px;
            margin: 48px auto;
            background: #fff;
            border: 1px solid #dfe5ee;
            border-radius: 10px;
            padding: 32px;
            text-align: center;
        }

        /* ---- print rules: each sheet is exactly one A4 landscape page ---- */
        @page { size: A4 landscape; margin: 0; }
        @media print {
            body { background: #fff; margin: 0; }
            .print-toolbar { display: none !important; }
            .labels-sheet {
                margin: 0;
                width: 297mm;
                height: 210mm;
            }
        }
    </style>
</head>
<body>

<div class="print-toolbar">
    <div>
        <strong>Property Labels</strong>
        <span class="text-muted small ms-2" id="countLabel"></span>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="assets.php" class="btn btn-outline-secondary btn-sm">Back to Assets</a>
        <button type="button" class="btn btn-primary btn-sm d-flex align-items-center gap-2" onclick="window.print()">
            <i data-lucide="printer"></i> Print Labels
        </button>
    </div>
</div>

<?php if ($total === 0): ?>
    <div class="no-data-box">
        <p class="mb-3">No inventory records were selected and no assets match the current filters.</p>
        <a href="assets.php" class="btn btn-outline-secondary">Back to Assets</a>
    </div>
<?php else: ?>
    <div id="labelSheets"><?= $sheetsHtml ?></div>
<?php endif; ?>

<script src="https://unpkg.com/lucide@latest"></script>
<script>
(function () {
    var countEl = document.getElementById('countLabel');
    if (countEl) {
        countEl.textContent = '<?= $total ?> label' + (<?= $total ?> === 1 ? '' : 's')
            + ' \u00b7 ' + <?= $totalPages ?> + ' page' + (<?= $totalPages ?> === 1 ? '' : 's');
    }
    if (window.lucide) lucide.createIcons();

    var AUTOPRINT = <?= (isset($_GET['autoprint']) && $_GET['autoprint'] === '1') ? 'true' : 'false' ?>;
    if (AUTOPRINT && <?= $total ?> > 0) {
        setTimeout(function () { window.print(); }, 250);
    }
})();
</script>
</body>
</html>
