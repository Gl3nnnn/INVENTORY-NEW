<?php
require __DIR__ . '/config.php';
require_login();
require_admin();

// ---- Auto property code + lifespan lookup for the add/edit form ----
if (isset($_GET['cat'])) {
    $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)$_GET['cat']), 0, 3));
    $cats   = asset_categories();
    header('Content-Type: application/json');
    if (!isset($cats[$prefix])) {
        echo json_encode(['code' => '', 'lifespan' => '']);
        exit;
    }
    echo json_encode([
        'code'     => next_property_code($conn, $prefix),
        'lifespan' => $cats[$prefix]['lifespan'],
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Missing category.']);
