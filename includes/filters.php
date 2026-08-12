<?php
/**
 * Shared filter logic — used by assets.php, export.php, print.php and print_pdf.php
 * so the printed / exported / listed data all respect the same filters.
 *
 * Uses prepared statements (via run_query) to keep SQL injection-safe.
 */

function build_asset_filters(mysqli $conn): array
{
    $clauses = [];
    $params  = [];

    // Free-text search across item name, property code, location, issued to, remarks
    if (!empty($_GET['search'])) {
        $search = trim((string)$_GET['search']);
        $like   = '%' . $search . '%';
        $clauses[] = "(items LIKE ? OR property_code LIKE ? OR location LIKE ?
                        OR issued_to LIKE ? OR remarks LIKE ?)";
        for ($i = 0; $i < 5; $i++) {
            $params[] = $like;
        }
    }

    // Category filter — matches the property-code prefix (IL-XXX-...)
    if (!empty($_GET['category'])) {
        $cat = strtoupper(substr(trim((string)$_GET['category']), 0, 3));
        $cat = preg_replace('/[^A-Z]/', '', $cat);
        if ($cat !== '') {
            $clauses[] = "property_code LIKE ?";
            $params[] = "IL-$cat-%";
        }
    }

    // Location filter
    if (!empty($_GET['location'])) {
        $clauses[] = "location = ?";
        $params[] = (string)$_GET['location'];
    }

    // Issued To filter
    if (!empty($_GET['issued_to'])) {
        $clauses[] = "issued_to = ?";
        $params[] = (string)$_GET['issued_to'];
    }

    // Status filter (management field)
    if (!empty($_GET['status'])) {
        $status = (string)$_GET['status'];
        if (in_array($status, asset_statuses(), true)) {
            $clauses[] = "status = ?";
            $params[] = $status;
        }
    }

    $whereSQL = '';
    if (count($clauses) > 0) {
        $whereSQL = 'WHERE ' . implode(' AND ', $clauses);
    }

    return ['clauses' => $clauses, 'where' => $whereSQL, 'params' => $params];
}

/**
 * Fetch all records matching the current filters (no pagination) — used by
 * print / PDF / CSV export.
 */
function fetch_filtered_assets(mysqli $conn, string $order = 'id ASC'): mysqli_result
{
    $f = build_asset_filters($conn);
    $sql = "SELECT * FROM it_asset_inventory {$f['where']} ORDER BY $order";
    return run_query($conn, $sql, $f['params']);
}

/** Count of records matching the current filters. */
function count_filtered_assets(mysqli $conn): int
{
    $f = build_asset_filters($conn);
    $res = run_query($conn, "SELECT COUNT(*) AS c FROM it_asset_inventory {$f['where']}", $f['params']);
    $row = $res ? $res->fetch_assoc() : [];
    return (int)($row['c'] ?? 0);
}

function distinct_locations(mysqli $conn): mysqli_result
{
    return $conn->query(
        "SELECT DISTINCT location FROM it_asset_inventory
         WHERE location IS NOT NULL AND location <> '' AND location <> 'N/A'
         ORDER BY location ASC"
    );
}

function distinct_issued_to(mysqli $conn): mysqli_result
{
    return $conn->query(
        "SELECT DISTINCT issued_to FROM it_asset_inventory
         WHERE issued_to IS NOT NULL AND issued_to <> ''
         ORDER BY issued_to ASC"
    );
}

function asset_statuses(): array
{
    return ['Active', 'In Repair', 'Disposed'];
}

/** Display helper: keep the value as stored, blank when empty (matches source data). */
function display_value(?string $value): string
{
    $value = (string)$value;
    return trim($value);
}
