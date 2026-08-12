<?php
require __DIR__ . '/config.php';
require_login();
require_admin();
require __DIR__ . '/includes/filters.php';
require_once __DIR__ . '/includes/xlsx.php';

$format = strtolower($_GET['format'] ?? 'csv') === 'xlsx' ? 'xlsx' : 'csv';

$result = fetch_filtered_assets($conn, 'id ASC');

$headers = ['ID', 'Model', 'Location', 'Property Code', 'Category', 'Life Span', 'Acquisition Date', 'Disposal Method', 'Status', 'Remarks', 'Issued To'];

$rows = [];
while ($row = $result->fetch_assoc()) {
    $prefix = category_prefix_from_code((string)$row['property_code']);
    $rows[] = [
        (int)$row['id'],
        (string)$row['items'],
        (string)$row['location'],
        (string)$row['property_code'],
        $prefix !== '' ? category_label($prefix) : '',
        (string)$row['life_span'],
        (string)$row['acquisition_date'],
        (string)$row['disposal_method'],
        (string)($row['status'] ?? 'Active'),
        (string)$row['remarks'],
        (string)$row['issued_to'],
    ];
}

$stamp = date('Y-m-d');

if ($format === 'xlsx') {
    $data = xlsx_build($headers, $rows);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename=it_asset_inventory_' . $stamp . '.xlsx');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo $data;
    exit;
}

// CSV (default)
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=it_asset_inventory_' . $stamp . '.csv');

$out = fopen('php://output', 'w');

// UTF-8 BOM so Excel reads the header correctly
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, $headers);

foreach ($rows as $row) {
    fputcsv($out, $row);
}

fclose($out);
exit;
