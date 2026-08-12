<?php
require __DIR__ . '/config.php';
require_login();
require __DIR__ . '/includes/filters.php';

// Document control values come from the Settings page (fall back to config.php constants)
$doc_org            = settings_get($conn, 'doc_org', DOC_ORG);
$doc_title          = settings_get($conn, 'doc_title', DOC_TITLE);
$doc_id             = settings_get($conn, 'doc_id', DOC_ID);
$doc_revision       = settings_get($conn, 'doc_revision', DOC_REVISION);
$doc_date_approved  = settings_get($conn, 'doc_date_approved', DOC_DATE_APPROVED);
$doc_classification = settings_get($conn, 'doc_classification', DOC_CLASSIFICATION);
$doc_department     = settings_get($conn, 'doc_department', DOC_DEPARTMENT);
$doc_branch         = settings_get($conn, 'doc_branch', DOC_BRANCH);

// All rows matching the current filters (no pagination) — printed document = current view
$result = fetch_filtered_assets($conn, 'id ASC');
$totalRecords = $result->num_rows;

// ---- Build document fragments (injected into JS) ----
$headerHtml = '
    <div class="doc-top">
        <div class="doc-left">
            <img class="doc-logo" src="' . LOGO_FILE . '" alt="COMPASS Logo">
            <div class="doc-org">' . $doc_org . '</div>
        </div>
        <div class="doc-title">' . $doc_title . '</div>
        <div class="doc-control">
            <span class="doc-control-row">Doc ID: ' . $doc_id . '</span>
            <span class="doc-control-row">Revision No.: ' . $doc_revision . '</span>
            <span class="doc-control-row">Date Approved: ' . $doc_date_approved . '</span>
            <span class="doc-control-row doc-class">' . $doc_classification . '</span>
        </div>
    </div>
    <div class="doc-meta">
        <span>Department: ' . $doc_department . '</span>
        <span>Branch: ' . $doc_branch . '</span>
    </div>
    <div class="doc-note">Note: Sections that are left blank denotes "Not Applicable" for the specified item.</div>
    <div class="doc-sep"></div>';

$theadHtml = '<thead>
        <tr>
            <th style="width:15.7%">Model</th>
            <th style="width:13.8%">Location</th>
            <th style="width:14.2%">Property Code</th>
            <th style="width:8.6%">Life Span</th>
            <th style="width:10%">Acquisition Date</th>
            <th style="width:11%">Disposal Method</th>
            <th style="width:16.7%">Remarks</th>
            <th style="width:10%">ISSUED TO</th>
        </tr>
    </thead>';

$signatureHtml = '
    <div class="doc-signature">
        <div class="sig-col">
            <div class="sig-label">Prepared by:</div>
            <div class="sig-stack">
                <div class="sig-line"></div>
            </div>
            <div class="sig-sub">Name and Signature</div>
        </div>
        <div class="sig-col">
            <div class="sig-label">Approved by:</div>
            <div class="sig-stack">
                <img class="sig-img" src="SIGNEW.png" alt="Approved by">
                <div class="sig-name">RYAN ALDRICH LAVILLA</div>
                <div class="sig-line"></div>
            </div>
            <div class="sig-sub">Department Head / Manager</div>
        </div>
    </div>';

$rowsHtml = '';
while ($row = $result->fetch_assoc()) {
    $rowsHtml .= '<tr class="datarow">
        <td class="al-left">' . e($row['items']) . '</td>
        <td class="al-left">' . e($row['location']) . '</td>
        <td class="al-center pc">' . e($row['property_code']) . '</td>
        <td class="al-center">' . e($row['life_span']) . '</td>
        <td class="al-center">' . e($row['acquisition_date']) . '</td>
        <td class="al-center">' . e($row['disposal_method']) . '</td>
        <td class="al-left remarks">' . e($row['remarks']) . '</td>
        <td class="al-left">' . e($row['issued_to']) . '</td>
    </tr>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Preview - <?= h($doc_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* ============ DOCUMENT STYLES (screen + print consistent) ============ */
        body { background: #eef1f6; font-family: Arial, Helvetica, sans-serif; margin: 0; }
        .inv-doc { padding: 18px 0; }

        .inv-sheet {
            background: #fff;
            width: 297mm;
            height: 210mm;
            margin: 0 auto 20px;
            padding: 14mm 13mm 17mm;
            box-shadow: 0 2px 12px rgba(0,0,0,.2);
            position: relative;
            box-sizing: border-box;
            overflow: hidden;
            font-family: Arial, Helvetica, sans-serif;
        }
        /* A single row that is taller than one page gets its own sheet; let it
           overflow visibly instead of clipping the bottom of the cell. */
        .inv-sheet.oversized { overflow: visible; }
        .inv-sheet.oversized .doc-table-wrap { overflow: visible; }
        .inv-sheet { isolation: isolate; }
        .doc-watermark {
            position: absolute; right: 6mm; bottom: 10mm;
            width: 100mm; height: auto;
            opacity: 0.45; z-index: -1;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ---- header ---- */
        .doc-top { display: flex; align-items: flex-start; justify-content: space-between; }
        .doc-left { display: flex; flex-direction: column; align-items: flex-start; width: 50mm; }
        .doc-logo { width: 26mm; height: auto; object-fit: contain; }
        .doc-org { font-size: 9pt; font-weight: 700; color: #1a1a1a; letter-spacing: .4px; margin-top: 1mm; }
        .doc-title {
            font-size: 16pt; font-weight: 700; color: #123A73;
            letter-spacing: .6px; text-align: center; flex: 1;
            margin: 0 4mm; white-space: nowrap;
        }
        .doc-control {
            font-size: 8pt; color: #333333; width: 77mm;
            text-align: right; line-height: 1.5; vertical-align: top;
        }
        .doc-control-row { display: block; }
        .doc-class { font-weight: 700; }

        /* ---- meta ---- */
        .doc-meta {
            display: flex; justify-content: space-between; align-items: center;
            font-size: 10pt; font-weight: 700; margin-top: 3.5mm; color: #333333;
        }
        .doc-note { font-size: 8pt; color: #666666; margin-top: 1mm; font-style: italic; }
        .doc-sep { border-bottom: 1.2pt solid #123A73; margin: 2mm 0 3.5mm; }

        /* ---- table ---- */
        .doc-table-wrap { overflow: hidden; }
        .doc-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .doc-table thead { display: table-header-group; }
        .doc-table th {
            background: #123A73; color: #fff; font-size: 8pt; font-weight: 700;
            padding: 1.8mm 1.2mm; border: 0.4pt solid #ffffff;
            text-align: center; vertical-align: middle;
            word-wrap: break-word;
        }
        .doc-table td {
            font-size: 7.5pt; padding: 1.4mm 1.2mm; border: 0.4pt solid #C7CDD4;
            vertical-align: top; word-wrap: break-word; overflow-wrap: break-word;
            color: #1a1a1a; line-height: 1.35;
        }
        .doc-table .al-left { text-align: left; }
        .doc-table .al-center { text-align: center; }
        .doc-table tbody tr:nth-child(odd) { background: #F4F7F9; }
        .doc-table tbody tr:nth-child(even) { background: #ffffff; }
        .doc-table .pc { font-size: 7.5pt; }
        .doc-table .remarks { font-size: 7.5pt; }
        .no-data { text-align: center; padding: 8mm; color: #555; font-size: 10pt; }

        /* ---- signature ---- */
        .doc-signature {
            display: flex; justify-content: space-between;
            margin-top: 3mm; page-break-inside: avoid;
        }
        .sig-col { width: 45%; }
        .sig-label { font-size: 9pt; font-weight: 700; color: #123A73; }
        /* Both columns use an identical stack so the signature line sits on the
           same baseline in "Prepared by" and "Approved by". */
        .sig-stack { position: relative; height: 20mm; }
        .sig-line {
            position: absolute; left: 0; right: 0; bottom: 0; height: 0;
            border-bottom: 0.8pt solid #000;
        }
        .sig-name {
            position: absolute; left: 0; right: 0; bottom: 1mm;
            text-align: center; font-size: 9pt; font-weight: 700; color: #1a1a1a;
        }
        .sig-stack .sig-img {
            position: absolute; left: 50%; transform: translateX(-50%);
            top: 0; height: 17mm; width: auto;
        }
        .sig-sub { font-size: 8pt; font-style: italic; text-align: center; color: #666666; margin-top: 1mm; }

        /* ---- footer / page number ---- */
        .doc-footer {
            position: absolute; left: 13mm; right: 13mm; bottom: 6mm;
            display: flex; justify-content: flex-end; align-items: center;
            font-size: 8pt; color: #555555;
        }
        .doc-footer .page-no { font-weight: 700; }

        /* ---- toolbar (screen only) ---- */
        .print-toolbar {
            background: #fff; border-bottom: 1px solid #dfe5ee; padding: 12px 18px;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            position: sticky; top: 0; z-index: 20;
        }
        .print-toolbar .btn i { width: 17px; height: 17px; }

        /* ---- print rules: each sheet = exactly one A4 page ---- */
        @page { size: A4 landscape; margin: 0; }
        @media print {
            body { background: #fff; }
            /* Keep backgrounds (dark-blue header + zebra rows) even when
               "Background graphics" is off in the print dialog. */
            .doc-table th,
            .doc-table tbody tr:nth-child(odd) {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .print-toolbar { display: none !important; }
            .inv-doc { padding: 0; }
            .inv-sheet {
                margin: 0; box-shadow: none; width: 297mm; height: 210mm;
                page-break-after: always; break-after: page;
            }
            .inv-sheet:last-child { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>

<div class="print-toolbar">
    <div>
        <strong><?= h($doc_title) ?></strong>
        <span class="text-muted small ms-2" id="countLabel"></span>
    </div>
</div>

<div class="inv-doc" id="invDoc">
    <div id="sheets"></div>
</div>

<!-- Source rows used by the paginator (kept in DOM, detached into sheets) -->
<div id="rowSource" style="display:none;"><table><tbody><?= $rowsHtml ?></tbody></table></div>

<script>
(function () {
    var rows = Array.prototype.slice.call(
        document.querySelectorAll('#rowSource .datarow')
    );

    var headerHtml = <?= json_encode($headerHtml) ?>;
    var theadHtml  = <?= json_encode($theadHtml) ?>;
    var sigHtml    = <?= json_encode($signatureHtml) ?>;
    var sheetsEl   = document.getElementById('sheets');

    var SHEET_H_MM   = 210;
    var PAD_T_MM     = 14;
    var PAD_B_MM     = 17;
    var FOOT_MM      = 10;  // always keep this strip clear at the sheet bottom (footer)
    var SAFE_MM      = 3;   // safety buffer so the bottom row never clips against the footer
    var MM2PX        = 3.7795275591;

    function makeSheet() {
        var s = document.createElement('div');
        s.className = 'inv-sheet';
        s.innerHTML =
            '<img class="doc-watermark" src="4.png" alt="">' +
            '<div class="doc-fixed">' + headerHtml + '</div>' +
            '<div class="doc-table-wrap"><table class="doc-table">' + theadHtml + '<tbody></tbody></table></div>' +
            sigHtml;
        var f = document.createElement('div');
        f.className = 'doc-footer';
        s.appendChild(f);
        sheetsEl.appendChild(s);
        return s;
    }

    function partsOf(s) {
        return {
            wrap:  s.querySelector('.doc-table-wrap'),
            tbody: s.querySelector('.doc-table tbody'),
            fixed: s.querySelector('.doc-fixed')
        };
    }

    function headerHeightMm(parts) {
        return parts.fixed.offsetHeight / MM2PX;
    }

    // Measure the real height (mm) of the signature block using an off-screen
    // sheet, so the space reserved on every page is exact rather than a guess.
    function measureSignatureMm() {
        var probe = document.createElement('div');
        probe.className = 'inv-sheet';
        probe.style.position = 'absolute';
        probe.style.visibility = 'hidden';
        probe.style.left = '-10000px';
        probe.innerHTML = sigHtml;
        document.body.appendChild(probe);
        var sig = probe.querySelector('.doc-signature');
        var h = sig ? (sig.offsetHeight / MM2PX) : 0;
        probe.parentNode.removeChild(probe);
        // Always reserve at least this much so the signature block is never
        // clipped or hidden behind the footer.
        return Math.max(h, 26);
    }

    // Size the table wrap so it exactly fills the remaining sheet height after
    // the fixed header and the signature + footer reserve on every page.
    function sizeSheet(s, sigMm) {
        var parts = partsOf(s);
        var extras = FOOT_MM + (sigMm || 0) + SAFE_MM;
        var availHmm = SHEET_H_MM - PAD_T_MM - PAD_B_MM - headerHeightMm(parts) - extras;
        parts.wrap.style.height = Math.max(10, availHmm * MM2PX) + 'px';
    }

    function finalize(s, pageNum, totalPages) {
        var footer = s.querySelector('.doc-footer');
        if (!footer) {
            footer = document.createElement('div');
            footer.className = 'doc-footer';
            s.appendChild(footer);
        }
        footer.innerHTML = '<span class="page-no">Page ' + pageNum + ' of ' + totalPages + '</span>';
    }

    // Paginate `list` into sheets. sigMm reserves exact signature space on
    // every page so nothing is clipped or overlaps the footer/header.
    function paginate(list, withNumbers, totalPages, sigMm) {
        var sheet = makeSheet();
        sizeSheet(sheet, sigMm);
        var parts = partsOf(sheet);
        var pageIndex = 1;

        function newSheet() {
            sheet = makeSheet();
            sizeSheet(sheet, sigMm);
            parts = partsOf(sheet);
        }

        for (var i = 0; i < list.length; i++) {
            var row = list[i];
            var isLast = (i === list.length - 1);

            parts.tbody.appendChild(row);

            if (parts.wrap.scrollHeight > parts.wrap.clientHeight + 2) {
                parts.tbody.removeChild(row);
                if (parts.tbody.children.length === 0) {
                    // A single row taller than a full page: keep it on its own
                    // sheet (let it overflow visibly instead of clipping) so it
                    // is never cut off. If it isn't the last row, continue the
                    // remaining rows on a fresh sheet.
                    parts.tbody.appendChild(row);
                    sheet.classList.add('oversized');
                    if (!isLast) {
                        finalize(sheet, pageIndex, totalPages);
                        pageIndex++;
                        newSheet();
                    }
                } else {
                    finalize(sheet, pageIndex, totalPages);
                    pageIndex++;
                    newSheet();
                    parts.tbody.appendChild(row);
                }
            }
        }

        // Finalize the last sheet so the final page always gets its footer.
        finalize(sheet, pageIndex, totalPages);
        return pageIndex;
    }

    window.addEventListener('load', function () {
        // Measure the real signature block height once (used to reserve the
        // exact space on every page).
        var sigMm = measureSignatureMm();

        if (rows.length === 0) {
            var empty = makeSheet();
            partsOf(empty).wrap.innerHTML = '<div class="no-data">No inventory records match the current filters.</div>';
            finalize(empty, 1, 1);
            setCount(0, 1);
            lucide.createIcons();
            maybeAutoPrint();
            return;
        }

        // Pass 1 (clones) to learn the total page count
        var totalPages = paginate(rows.map(function (r) { return r.cloneNode(true); }), false, 0, sigMm);

        // Pass 2 (real rows) with page-number footers
        sheetsEl.innerHTML = '';
        paginate(rows, true, totalPages, sigMm);

        setCount(rows.length, totalPages);
        lucide.createIcons();
        maybeAutoPrint();
    });

    function setCount(n, pages) {
        var el = document.getElementById('countLabel');
        if (!el) return;
        el.textContent = n + ' record' + (n === 1 ? '' : 's') + ' \u00b7 ' + pages + ' page' + (pages === 1 ? '' : 's');
    }

    var AUTOPRINT = <?= !empty($_GET['autoprint']) ? 'true' : 'false' ?>;

    function maybeAutoPrint() {
        if (AUTOPRINT) {
            setTimeout(function () { window.print(); }, 250);
        }
    }
})();
</script>
<script src="https://unpkg.com/lucide@latest"></script>
</body>
</html>
