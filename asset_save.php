<?php
require __DIR__ . '/config.php';
require __DIR__ . '/includes/filters.php';
require_login();
require_admin();
require_csrf();

$editId = (int)($_POST['id'] ?? 0);

$items           = trim((string)($_POST['items'] ?? ''));
$category        = strtoupper(substr(trim((string)($_POST['category'] ?? '')), 0, 3));
$location        = trim((string)($_POST['location'] ?? ''));
$propertyCode    = trim((string)($_POST['property_code'] ?? ''));
$lifeSpan        = trim((string)($_POST['life_span'] ?? ''));
$acquisitionDate = trim((string)($_POST['acquisition_date'] ?? ''));
if (!empty($_POST['acq_na'])) {
    $acquisitionDate = 'N/A';
}
$disposalMethod  = trim((string)($_POST['disposal_method'] ?? ''));
$remarks         = trim((string)($_POST['remarks'] ?? ''));
$issuedTo        = trim((string)($_POST['issued_to'] ?? ''));
$status          = in_array(($_POST['status'] ?? 'Active'), asset_statuses(), true) ? (string)$_POST['status'] : 'Active';

$cats = asset_categories();

if ($items === '') {
    $_SESSION['error'] = 'Model field is required.';
    redirect('assets.php');
}

// Format the model as "LABEL (model)" when a category is selected,
// unless it is already formatted or equals the bare category label.
if ($category !== '' && isset($cats[$category])) {
    $label = strtoupper($cats[$category]['label']);
    if (strcasecmp($items, $label) !== 0 &&
        !preg_match('/^' . preg_quote($label, '/') . '\s*\(.+\)$/i', $items)) {
        $items = $label . ' (' . $items . ')';
    }
}

// Auto-generate the property code when adding and none was provided
if ($propertyCode === '') {
    if ($category !== '' && isset($cats[$category])) {
        $propertyCode = next_property_code($conn, $category);
    } else {
        $_SESSION['error'] = 'Please select a category or provide a property code.';
        redirect('assets.php');
    }
}

// Auto-fill lifespan from category when empty
if ($lifeSpan === '' && $category !== '' && isset($cats[$category])) {
    $lifeSpan = $cats[$category]['lifespan'];
}

$changedBy = (string)($_SESSION['username'] ?? '');

if ($editId > 0) {
    // Load the previous record so we can log what changed (audit + history)
    $stmt = $conn->prepare("SELECT * FROM it_asset_inventory WHERE id = ?");
    $stmt->bind_param('i', $editId);
    $stmt->execute();
    $prev = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $changeLog = [];
    $fieldMap = [
        'items'           => 'Model',
        'location'        => 'Location',
        'property_code'   => 'Property Code',
        'life_span'       => 'Life Span',
        'acquisition_date'=> 'Acquisition Date',
        'disposal_method' => 'Disposal Method',
        'remarks'         => 'Remarks',
        'issued_to'       => 'Issued To',
        'status'          => 'Status',
    ];
    foreach ($fieldMap as $col => $label) {
        $old = (string)($prev[$col] ?? '');
        $new = (string)$$col;
        if ($old !== $new) {
            $changeLog[] = $label . ': ' . ($old === '' ? '(empty)' : $old) . ' -> ' . ($new === '' ? '(empty)' : $new);
            $hs = $conn->prepare("INSERT INTO asset_history (asset_id, field_name, old_value, new_value, changed_by) VALUES (?,?,?,?,?)");
            $hs->bind_param('issss', $editId, $label, $old, $new, $changedBy);
            $hs->execute();
            $hs->close();
        }
    }

    $stmt = $conn->prepare(
        "UPDATE it_asset_inventory
         SET items = ?, location = ?, property_code = ?, life_span = ?,
             acquisition_date = ?, disposal_method = ?, remarks = ?, issued_to = ?, status = ?
         WHERE id = ?"
    );
    $stmt->bind_param('sssssssssi',
        $items, $location, $propertyCode, $lifeSpan,
        $acquisitionDate, $disposalMethod, $remarks, $issuedTo, $status, $editId
    );
    $stmt->execute();
    $stmt->close();

    audit_log($conn, 'UPDATE_ASSET', 'Asset #' . $editId . ' (' . $propertyCode . ') updated' .
        (count($changeLog) ? ': ' . implode(' | ', $changeLog) : '.'));
    $_SESSION['success'] = 'Asset updated successfully!';
    redirect('assets.php');
} else {
    $stmt = $conn->prepare(
        "INSERT INTO it_asset_inventory
            (items, location, property_code, life_span, acquisition_date, disposal_method, remarks, issued_to, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('sssssssss',
        $items, $location, $propertyCode, $lifeSpan,
        $acquisitionDate, $disposalMethod, $remarks, $issuedTo, $status
    );
    $stmt->execute();
    $newId = (int)$stmt->insert_id;
    $stmt->close();

    // Record the initial assignment when one was specified
    if ($issuedTo !== '') {
        $hs = $conn->prepare("INSERT INTO asset_history (asset_id, field_name, old_value, new_value, changed_by) VALUES (?, 'Issued To', '', ?, ?)");
        $hs->bind_param('iss', $newId, $issuedTo, $changedBy);
        $hs->execute();
        $hs->close();
    }

    audit_log($conn, 'ADD_ASSET', 'Asset #' . $newId . ' (' . $propertyCode . ') added.');
    $_SESSION['success'] = 'Asset added successfully!';
    redirect('assets.php');
}
