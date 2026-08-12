<?php
require __DIR__ . '/config.php';
// NOTE: require_login() is intentionally NOT called here so that QR-scanned
// asset lookups work for anonymous visitors. Logged-in users are sent to the
// full asset_details.php; anonymous visitors land on the public qr_view.php.

// Lookup by numeric id (QR scan target)
if (!empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id FROM it_asset_inventory WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$found) {
        $_SESSION['error'] = 'No asset found for that QR code.';
        // Anonymous visitors can't reach assets.php — send them to the public view
        if (empty($_SESSION['user_id'])) {
            redirect('qr_view.php?id=' . $id);
        }
        redirect('assets.php');
    }

    if (!empty($_SESSION['user_id'])) {
        // Logged-in users see the full admin details page
        redirect('asset_details.php?id=' . $id);
    }
    // Anonymous visitors see the public QR view
    redirect('qr_view.php?id=' . $id);
}

// Lookup by exact property code (scan / search box on assets.php)
if (!empty($_GET['code'])) {
    $code = trim((string)$_GET['code']);
    $stmt = $conn->prepare("SELECT id FROM it_asset_inventory WHERE property_code = ?");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($found) {
        $targetId = (int)$found['id'];
        // If already logged in, go to full details; otherwise go to public view
        if (!empty($_SESSION['user_id'])) {
            redirect('asset_details.php?id=' . $targetId);
        }
        redirect('qr_view.php?id=' . $targetId);
    }
    $_SESSION['error'] = 'No asset matches code "' . $code . '".';
    redirect('assets.php?' . http_build_query(['search' => $code]));
}

redirect('assets.php');
