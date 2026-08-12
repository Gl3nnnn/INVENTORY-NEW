<?php
require __DIR__ . '/config.php';
require_login();
require __DIR__ . '/includes/filters.php';

$pageTitle = 'Asset Management';

// ---- Delete (admin only, POST via confirm modal) ----
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['delete_id']) && is_admin()) {
    require_csrf();
    $delId = (int)$_POST['delete_id'];
    $stmt = $conn->prepare("SELECT property_code FROM it_asset_inventory WHERE id = ?");
    $stmt->bind_param('i', $delId);
    $stmt->execute();
    $pcode = ($r = $stmt->get_result()->fetch_assoc()) ? (string)$r['property_code'] : '';
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM it_asset_inventory WHERE id = ?");
    $stmt->bind_param('i', $delId);
    $stmt->execute();
    $stmt->close();

    audit_log($conn, 'DELETE_ASSET', "Asset #$delId ($pcode) deleted.");
    $_SESSION['success'] = 'Asset deleted successfully!';
    redirect('assets.php?' . keep_query(['delete_id']));
}

// ---- Filters + pagination ----
$f = build_asset_filters($conn);
$whereSQL = $f['where'];

$limit  = isset($_GET['limit']) ? max(5, min(100, (int)$_GET['limit'])) : 10;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Sortable columns (whitelisted so no arbitrary SQL can be injected)
$sortWhitelist = ['id', 'items', 'location', 'property_code', 'life_span', 'acquisition_date', 'issued_to', 'status'];
$sort = (isset($_GET['sort']) && in_array((string)$_GET['sort'], $sortWhitelist, true)) ? (string)$_GET['sort'] : 'id';
$dir  = (($_GET['dir'] ?? 'asc') === 'desc') ? 'DESC' : 'ASC';
$order = $sort . ' ' . $dir;

$totalRows = count_filtered_assets($conn);
$totalPages = max(1, (int)ceil($totalRows / $limit));

$result = run_query($conn, "SELECT * FROM it_asset_inventory $whereSQL ORDER BY $order LIMIT $limit OFFSET $offset", $f['params']);

// Sort-link builder for the table header
$sortLink = function (string $col, string $label) use ($sort, $dir) {
    $q = $_GET;
    $q['sort'] = $col;
    $q['dir'] = ($sort === $col && $dir === 'ASC') ? 'desc' : 'asc';
    unset($q['page']);
    $arrow = $sort === $col ? ($dir === 'ASC' ? ' &#9650;' : ' &#9660;') : '';
    return '<a class="sort-link" href="assets.php?' . h(http_build_query($q)) . '">' . h($label) . $arrow . '</a>';
};

$locations = distinct_locations($conn);
$issuedList = distinct_issued_to($conn);
$categories = asset_categories();

// Query string that carries the current filters (used by print / PDF / export)
$filterQs = http_build_query(array_diff_key($_GET, ['page' => 1, 'limit' => 1]));
$filterQs = $filterQs === '' ? '' : '?' . $filterQs;

// Active filter tracking for the toolbar Filters badge + auto-open behaviour.
// Search lives in the toolbar, so typing it must not force the panel open.
$nonSearchFilters = array_intersect_key($_GET, ['category' => 1, 'location' => 1, 'issued_to' => 1, 'status' => 1]);
$hasNonSearchFilter = count($nonSearchFilters) > 0;
$activeFilterCount = count($nonSearchFilters) + (!empty($_GET['search']) ? 1 : 0);

// ---- Edit mode: load the asset to pre-fill the Add/Edit modal ----
$editAsset = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $stmt = $conn->prepare("SELECT * FROM it_asset_inventory WHERE id = ?");
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $editAsset = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i data-lucide="hard-drive"></i></div>
        <div>
            <h2>Asset Management</h2>
            <p class="page-subtitle">View, search, filter and manage all IT assets in your inventory</p>
        </div>
    </div>

    <form method="get" action="assets.php" class="assets-toolbar-form">
        <!-- Toolbar -->
        <div class="assets-toolbar">
            <div class="toolbar-left">
                <div class="search-box">
                    <i data-lucide="search" class="search-box-icon"></i>
                    <input type="text" name="search" id="searchInput" class="form-control"
                           placeholder="Search assets..." value="<?= h($_GET['search'] ?? '') ?>"
                           autocomplete="off">
                </div>
                <button type="button" class="btn btn-outline-secondary toolbar-btn toolbar-filter-btn"
                        data-bs-toggle="collapse" data-bs-target="#filterPanel"
                        aria-expanded="<?= $hasNonSearchFilter ? 'true' : 'false' ?>">
                    <i data-lucide="filter" class="icon-sm"></i>
                    <span>Filters</span>
                    <?php if ($activeFilterCount > 0): ?>
                        <span class="filter-count-badge"><?= $activeFilterCount ?></span>
                    <?php endif; ?>
                </button>
            </div>
            <div class="toolbar-right">
                <button type="button" class="btn btn-outline-secondary toolbar-btn d-flex align-items-center gap-2" id="printPreviewBtn">
                    <i data-lucide="printer"></i> Print
                </button>
                <?php if (is_admin()): ?>
                    <div class="dropdown">
                        <button type="button" class="btn btn-outline-secondary toolbar-btn d-flex align-items-center gap-2 dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            <i data-lucide="file-text"></i> Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item d-flex align-items-center gap-2" href="export.php<?= h($filterQs) ?>">
                                <i data-lucide="file-text" class="icon-sm"></i> Export CSV
                            </a></li>
                            <li><a class="dropdown-item d-flex align-items-center gap-2" href="export.php<?= h($filterQs) ?>&format=xlsx">
                                <i data-lucide="table" class="icon-sm"></i> Export Excel (XLSX)
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item d-flex align-items-center gap-2" href="import.php">
                                <i data-lucide="upload" class="icon-sm"></i> Import CSV / XLSX
                            </a></li>
                        </ul>
                    </div>
                    <button type="button" class="btn btn-primary toolbar-btn d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#assetModal">
                        <i data-lucide="plus"></i> Add Asset
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Expandable filter panel -->
        <div class="collapse <?= $hasNonSearchFilter ? 'show' : '' ?>" id="filterPanel">
            <div class="filter-panel">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Item Category</label>
                        <select name="category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $prefix => $info): ?>
                                <option value="<?= h($prefix) ?>" <?= ($_GET['category'] ?? '') === $prefix ? 'selected' : '' ?>>
                                    <?= h($info['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Location</label>
                        <select name="location" class="form-select">
                            <option value="">All Locations</option>
                            <?php while ($loc = $locations->fetch_assoc()): ?>
                                <option value="<?= h($loc['location']) ?>" <?= ($_GET['location'] ?? '') === $loc['location'] ? 'selected' : '' ?>>
                                    <?= h($loc['location']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Issued To</label>
                        <select name="issued_to" class="form-select">
                            <option value="">All Issued To</option>
                            <?php while ($it = $issuedList->fetch_assoc()): ?>
                                <option value="<?= h($it['issued_to']) ?>" <?= ($_GET['issued_to'] ?? '') === $it['issued_to'] ? 'selected' : '' ?>>
                                    <?= h($it['issued_to']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <?php foreach (asset_statuses() as $st): ?>
                                <option value="<?= h($st) ?>" <?= ($_GET['status'] ?? '') === $st ? 'selected' : '' ?>>
                                    <?= h($st) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-3">
                        <button type="submit" class="filter-btn primary">
                            <i data-lucide="search" class="icon-sm"></i><span>Apply Filters</span>
                        </button>
                        <a href="assets.php" class="filter-btn secondary">
                            <i data-lucide="x" class="icon-sm"></i><span>Clear Filters</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Count row -->
    <div class="asset-count-row">
        <div class="asset-count-left">
            <span class="asset-count-total"><?= $totalRows ?> assets</span>
        </div>
        <div class="asset-count-right">
            <label class="show-entries-info">Show:</label>
            <select class="form-select form-select-sm" onchange="changeLimit(this.value)">
                <?php foreach ([10, 25, 50, 100] as $n): ?>
                    <option value="<?= $n ?>" <?= $limit === $n ? 'selected' : '' ?>><?= $n ?></option>
                <?php endforeach; ?>
            </select>
            <span class="show-entries-info">
                Showing <?= $totalRows ? ($offset + 1) : 0 ?> to <?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?>
            </span>
        </div>
    </div>

    <!-- Assets table -->
    <div class="table-container">
        <div class="table-responsive">
            <table class="assets-table">
                <thead>
                    <tr>
                        <th><?= $sortLink('id', 'ID') ?></th>
                        <th><?= $sortLink('items', 'Model') ?></th>
                        <th><?= $sortLink('location', 'Location') ?></th>
                        <th><?= $sortLink('property_code', 'Property Code') ?></th>
                        <th>Category</th>
                        <th><?= $sortLink('life_span', 'Life Span') ?></th>
                        <th><?= $sortLink('acquisition_date', 'Acquisition Date') ?></th>
                        <th>Disposal Method</th>
                        <th>Remarks</th>
                        <th><?= $sortLink('issued_to', 'Issued To') ?></th>
                        <th><?= $sortLink('status', 'Status') ?></th>
                        <?php if (is_admin()): ?><th>Actions</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()):
                            $prefix = category_prefix_from_code($row['property_code']);
                        ?>
                                                                                <tr>
                                <td><?= (int)$row['id'] ?></td>
                                <td><strong><?= h($row['items']) ?></strong></td>
                                <td><?= h($row['location']) ?></td>
                                <td><span class="badge bg-primary-light"><?= h($row['property_code']) ?></span></td>
                                <td><?= $prefix ? h(category_label($prefix)) : '' ?></td>
                                <td><?= h($row['life_span']) ?></td>
                                <td><?= h($row['acquisition_date']) ?></td>
                                <td><?= h($row['disposal_method']) ?></td>
                                <td class="remarks-cell"><?= h($row['remarks']) ?></td>
                                <td><?= h($row['issued_to']) ?></td>
                                <td>
                                    <?php $st = $row['status'] ?? 'Active';
                                          $sb = $st === 'Active' ? 'status-badge active' : ($st === 'In Repair' ? 'status-badge repair' : ($st === 'Disposed' ? 'status-badge disposed' : 'status-badge other')); ?>
                                    <span class="<?= $sb ?>"><?= h($st) ?></span>
                                </td>
                                <?php if (is_admin()): ?>
                                <td>
                                    <div class="action-buttons">
                                        <a href="asset_details.php?id=<?= (int)$row['id'] ?>" class="action-btn view view-action" title="View / History"
                                           data-id="<?= (int)$row['id'] ?>"
                                           data-items="<?= h($row['items']) ?>"
                                           data-location="<?= h($row['location']) ?>"
                                           data-property-code="<?= h($row['property_code']) ?>"
                                           data-life-span="<?= h($row['life_span']) ?>"
                                           data-acquisition-date="<?= h($row['acquisition_date']) ?>"
                                           data-disposal-method="<?= h($row['disposal_method']) ?>"
                                           data-remarks="<?= h($row['remarks']) ?>"
                                           data-issued-to="<?= h($row['issued_to']) ?>"
                                           data-status="<?= h($row['status'] ?? 'Active') ?>">
                                            <i data-lucide="eye"></i><span>View</span>
                                        </a>
                                        <a href="qr.php?id=<?= (int)$row['id'] ?>" class="action-btn qr qr-action" title="QR Label"
                                           data-id="<?= (int)$row['id'] ?>"
                                           data-items="<?= h($row['items']) ?>"
                                           data-property-code="<?= h($row['property_code']) ?>"
                                           data-location="<?= h($row['location']) ?>"
                                           data-issued-to="<?= h($row['issued_to']) ?>"
                                           data-status="<?= h($row['status'] ?? 'Active') ?>">
                                            <i data-lucide="qrcode"></i><span>QR</span>
                                        </a>
                                        <a href="assets.php?edit=<?= (int)$row['id'] ?>&<?= h(keep_query(['edit', 'page'])) ?>" class="action-btn edit edit-action" title="Edit"
                                           data-id="<?= (int)$row['id'] ?>"
                                           data-items="<?= h($row['items']) ?>"
                                           data-category="<?= h(category_prefix_from_code($row['property_code'])) ?>"
                                           data-location="<?= h($row['location']) ?>"
                                           data-property-code="<?= h($row['property_code']) ?>"
                                           data-life-span="<?= h($row['life_span']) ?>"
                                           data-acquisition-date="<?= h($row['acquisition_date']) ?>"
                                           data-disposal-method="<?= h($row['disposal_method']) ?>"
                                           data-remarks="<?= h($row['remarks']) ?>"
                                           data-issued-to="<?= h($row['issued_to']) ?>"
                                           data-status="<?= h($row['status'] ?? 'Active') ?>">
                                            <i data-lucide="pencil"></i><span>Edit</span>
                                        </a>
                                        <button type="button" class="action-btn delete" title="Delete"
                                                onclick="openDeleteModal(<?= (int)$row['id'] ?>, '<?= h(addslashes($row['items'] ?? '')) ?>')">
                                            <i data-lucide="trash-2"></i><span>Delete</span>
                                        </button>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                                                <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= is_admin() ? 12 : 11 ?>" class="empty-state">
                                <i data-lucide="inbox" class="icon-lg"></i>
                                <p class="mt-2">No assets match your current filters.</p>
                                <a href="assets.php" class="btn btn-outline-secondary btn-sm mt-2">
                                    <i data-lucide="x" class="icon-sm"></i> Clear all filters
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php
            $base = function (int $p) {
                $q = array_merge($_GET, ['page' => $p]);
                return '?' . http_build_query($q);
            };
            ?>
            <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= h($base($page - 1)) ?>">&laquo;</a></li>
            <?php endif; ?>
            <?php
            $start = max(1, $page - 2);
            $end   = min($totalPages, $page + 2);
            if ($start > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= h($base(1)) ?>">1</a></li>
                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
            <?php endif; ?>
            <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= h($base($i)) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?= h($base($totalPages)) ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <li class="page-item"><a class="page-link" href="<?= h($base($page + 1)) ?>">&raquo;</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <!-- Print Preview Modal -->
    <div class="modal fade" id="printModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="printer"></i> Print Preview — Inventory List
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 bg-light">
                    <p class="text-center text-muted py-5 mb-0">Click Print below to print the current inventory list.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="doPrintBtn">
                        <i data-lucide="printer"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Asset Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i data-lucide="eye"></i> Asset Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><strong>Property Code</strong><div id="viewPropertyCode"></div></div>
                        <div class="col-md-6"><strong>Item</strong><div id="viewItems"></div></div>
                        <div class="col-md-6"><strong>Location</strong><div id="viewLocation"></div></div>
                        <div class="col-md-6"><strong>Issued To</strong><div id="viewIssuedTo"></div></div>
                        <div class="col-md-6"><strong>Life Span</strong><div id="viewLifeSpan"></div></div>
                        <div class="col-md-6"><strong>Status</strong><div id="viewStatus"></div></div>
                        <div class="col-md-6"><strong>Acquisition Date</strong><div id="viewAcquisitionDate"></div></div>
                        <div class="col-md-6"><strong>Disposal</strong><div id="viewDisposal"></div></div>
                        <div class="col-12"><strong>Remarks</strong><div id="viewRemarks"></div></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <a href="#" id="viewFullPageLink" class="btn btn-outline-secondary">Open Full Asset Page</a>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- QR Label Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i data-lucide="qrcode"></i> QR Label Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status" id="qrLoadingSpinner"><span class="visually-hidden">Loading...</span></div>
                        <p class="text-muted mt-3 mb-0" id="qrModalStatus">Loading QR label...</p>
                        <div class="qr-iframe-wrapper ratio ratio-1x1 mt-4 d-none" id="qrIframeWrapper"></div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <a href="#" id="qrOpenPageLink" class="btn btn-outline-secondary">Open Full QR Page</a>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (is_admin()): ?>
    <?php
    $editId       = $editAsset['id'] ?? 0;
    $formItems    = $editAsset['items'] ?? '';
    $formLocation = $editAsset['location'] ?? '';
    $formCode     = $editAsset['property_code'] ?? '';
    $formLifeSpan = $editAsset['life_span'] ?? '';
    $formAcqDate  = $editAsset['acquisition_date'] ?? '';
    $formDisposal = $editAsset['disposal_method'] ?? '';
    $formRemarks  = $editAsset['remarks'] ?? '';
    $formIssuedTo = $editAsset['issued_to'] ?? '';
    $formStatus   = $editAsset['status'] ?? 'Active';
    $currentPrefix = category_prefix_from_code($formCode);

    // Strip the "LABEL (...)" wrapper when editing so the field shows the inner model
    $formModel = $formItems;
    if ($currentPrefix !== '' && isset($categories[$currentPrefix])) {
        $catLabel = strtoupper($categories[$currentPrefix]['label']);
        if (preg_match('/^' . preg_quote($catLabel, '/') . '\s*\((.*)\)$/i', $formItems, $m)) {
            $formModel = $m[1];
        }
    }

    $formAcqDateValue = '';
    $formAcqNa        = false;
    if (strtoupper($formAcqDate) === 'N/A' || $formAcqDate === '') {
        $formAcqNa = true;
    } else {
        $dt = DateTime::createFromFormat('n/j/Y', $formAcqDate);
        $formAcqDateValue = $dt ? $dt->format('Y-m-d') : $formAcqDate;
    }
    ?>
    <!-- Add / Edit Asset Modal -->
    <div class="modal fade" id="assetModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <form method="post" action="asset_save.php">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i id="assetModalTitleIcon" data-lucide="<?= $editAsset ? 'pencil' : 'plus' ?>"></i>
                            <span id="assetModalTitleText"><?= $editAsset ? 'Edit Asset' : 'Add New Asset' ?></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="assetIdInput" value="<?= (int)$editId ?>">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="items">Model *</label>
                                <input type="text" class="form-control" id="items" name="items" required
                                       placeholder="e.g. ACER SF314"
                                       value="<?= h($formModel) ?>">
                                <div class="form-text">Stored as <span id="itemsPreview"><?= h($formItems) ?></span></div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="category">Item Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $prefix => $info): ?>
                                        <option value="<?= h($prefix) ?>"
                                            <?= ($_POST['category'] ?? $currentPrefix) === $prefix ? 'selected' : '' ?>>
                                            <?= h($info['label']) ?> (<?= h($prefix) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="location">Location</label>
                                <input type="text" class="form-control" id="location" name="location"
                                       placeholder="e.g. REGISTRATION" value="<?= h($formLocation) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="issued_to">Issued To</label>
                                <input type="text" class="form-control" id="issued_to" name="issued_to"
                                       placeholder="e.g. IT/TD" value="<?= h($formIssuedTo) ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="status">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <?php foreach (asset_statuses() as $opt): ?>
                                        <option value="<?= h($opt) ?>" <?= $formStatus === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="property_code">Property Code</label>
                                <input type="text" class="form-control" id="property_code" name="property_code"
                                       placeholder="Auto-generated" value="<?= h($formCode) ?>">
                                <div class="form-text">Auto-generated as IL-CAT-YYYY-NNN when adding. Editable.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="life_span">Life Span</label>
                                <input type="text" class="form-control" id="life_span" name="life_span"
                                       placeholder="e.g. 3-5 years" value="<?= h($formLifeSpan) ?>">
                                <div class="form-text">Auto-filled from the selected category; editable.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="acquisition_date">Acquisition Date</label>
                                <div class="input-group">
                                    <input type="date" class="form-control" id="acquisition_date" name="acquisition_date"
                                           value="<?= h($formAcqDateValue) ?>" <?= $formAcqNa ? 'disabled' : '' ?>>
                                    <div class="input-group-text">
                                        <input class="form-check-input mt-0" type="checkbox" id="acq_na" name="acq_na" value="1" <?= $formAcqNa ? 'checked' : '' ?>>
                                        <label class="form-check-label ms-1" for="acq_na">N/A</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="disposal_method">Disposal Method</label>
                                <select class="form-select" id="disposal_method" name="disposal_method">
                                    <?php foreach (['N/A', 'Recycle', 'Sell', 'Donation', 'Write-off', 'Trade-in', 'Return to Supplier', 'Other'] as $opt): ?>
                                        <option value="<?= h($opt) ?>" <?= $formDisposal === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label" for="remarks">Remarks</label>
                                <textarea class="form-control" id="remarks" name="remarks" rows="3"
                                          placeholder="e.g. GOOD WORKING CONDITION"><?= h($formRemarks) ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i> <?= $editAsset ? 'Save Changes' : 'Add Asset' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i data-lucide="triangle-alert"></i> Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete "<strong id="delAssetName"></strong>"?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="delete_id" id="delAssetId" value="">
                        <button type="submit" class="btn btn-danger">
                            <i data-lucide="trash-2"></i> Delete Asset
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function changeLimit(limit) {
        const url = new URL(window.location);
        url.searchParams.set('limit', limit);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    // Live search: auto-apply the text filter as the user types (debounced)
    const liveSearch = document.getElementById('searchInput');
    if (liveSearch) {
        let timer = null;
        liveSearch.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                const form = liveSearch.closest('form');
                if (form) form.submit();
            }, 600);
        });
    }

    function openDeleteModal(id, name) {
        document.getElementById('delAssetId').value = id;
        document.getElementById('delAssetName').textContent = name;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }

    document.getElementById('printPreviewBtn').addEventListener('click', function () {
        new bootstrap.Modal(document.getElementById('printModal')).show();
    });

    document.getElementById('doPrintBtn').addEventListener('click', function () {
        // Open the inventory print document (print.php) in a new tab and auto-print it.
        // Resolve print.php relative to the current page's directory (e.g. /invent/print.php).
        const base = window.location.pathname.replace(/[^/]*$/, '');
        const url = new URL(base + 'print.php', window.location.origin);
        url.search = window.location.search;            // carry the current filters
        url.searchParams.set('autoprint', '1');
        window.open(url.toString(), '_blank');
    });

    // N/A checkbox toggles the Acquisition Date field
    const acqInput = document.getElementById('acquisition_date');
    const acqNa = document.getElementById('acq_na');
    const syncAcq = function () { if (acqInput && acqNa) acqInput.disabled = acqNa.checked; };
    if (acqInput && acqNa) {
        acqNa.addEventListener('change', syncAcq);
        syncAcq();
    }

    // Auto-fill property code + life span when a category is picked
    const assetCatSelect = document.getElementById('category');
    const assetCodeInput = document.getElementById('property_code');
    const assetLifeInput = document.getElementById('life_span');
    if (assetCatSelect) {
        const isEdit = <?= $editAsset ? 'true' : 'false' ?>;
        assetCatSelect.addEventListener('change', function () {
            const prefix = this.value;
            if (!prefix) return;
            fetch('api_next_code.php?cat=' + encodeURIComponent(prefix))
                .then(r => r.json())
                .then(data => {
                    if (!isEdit || assetCodeInput.value.trim() === '') {
                        assetCodeInput.value = data.code || '';
                    }
                    if (data.lifespan) assetLifeInput.value = data.lifespan;
                })
                .catch(() => {});
        });
    }

    function openEditModal(source) {
        const modalEl = document.getElementById('assetModal');
        if (!modalEl) return;

        const titleIcon = modalEl.querySelector('#assetModalTitleIcon');
        const titleText = modalEl.querySelector('#assetModalTitleText');
        const submitBtn = modalEl.querySelector('button[type="submit"]');
        const idInput = document.getElementById('assetIdInput');
        const itemsInput = document.getElementById('items');
        const categoryInput = document.getElementById('category');
        const locationInput = document.getElementById('location');
        const codeInput = document.getElementById('property_code');
        const lifeInput = document.getElementById('life_span');
        const acqInput = document.getElementById('acquisition_date');
        const acqNaInput = document.getElementById('acq_na');
        const disposalInput = document.getElementById('disposal_method');
        const remarksInput = document.getElementById('remarks');
        const issuedInput = document.getElementById('issued_to');
        const statusInput = document.getElementById('status');
        const preview = document.getElementById('itemsPreview');

        if (idInput) idInput.value = source.dataset.id || '';
        if (itemsInput) itemsInput.value = source.dataset.items || '';
        if (categoryInput) categoryInput.value = source.dataset.category || '';
        if (locationInput) locationInput.value = source.dataset.location || '';
        if (codeInput) codeInput.value = source.dataset.propertyCode || '';
        if (lifeInput) lifeInput.value = source.dataset.lifeSpan || '';
        if (acqInput) {
            const dateValue = source.dataset.acquisitionDate || '';
            if (dateValue.toUpperCase() === 'N/A' || dateValue === '') {
                if (acqNaInput) acqNaInput.checked = true;
                acqInput.value = '';
            } else {
                if (acqNaInput) acqNaInput.checked = false;
                acqInput.value = dateValue;
            }
        }
        if (disposalInput) disposalInput.value = source.dataset.disposalMethod || 'N/A';
        if (remarksInput) remarksInput.value = source.dataset.remarks || '';
        if (issuedInput) issuedInput.value = source.dataset.issuedTo || '';
        if (statusInput) statusInput.value = source.dataset.status || 'Active';
        if (preview && itemsInput) preview.textContent = itemsInput.value;

        if (titleIcon) {
            titleIcon.setAttribute('data-lucide', 'pencil');
        }
        if (titleText) {
            titleText.textContent = 'Edit Asset';
        }
        if (submitBtn) {
            submitBtn.innerHTML = '<i data-lucide="save"></i> Save Changes';
        }

        syncAcq();
        if (assetCatSelect) assetCatSelect.dispatchEvent(new Event('change'));
        new bootstrap.Modal(modalEl).show();
        if (titleIcon) lucide.replace({ parent: modalEl });
    }

    document.querySelectorAll('.edit-action').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            openEditModal(link);
        });
    });

    document.querySelectorAll('.view-action').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            openViewModal(link);
        });
    });

    document.querySelectorAll('.qr-action').forEach(function (link) {
        link.addEventListener('click', function (event) {
            event.preventDefault();
            openQrModal(link);
        });
    });

    function openViewModal(link) {
        const getText = function (id, value) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = value || '—';
        };
        getText('viewPropertyCode', link.dataset.propertyCode);
        getText('viewItems', link.dataset.items);
        getText('viewLocation', link.dataset.location);
        getText('viewIssuedTo', link.dataset.issuedTo);
        getText('viewLifeSpan', link.dataset.lifeSpan);
        getText('viewStatus', link.dataset.status);
        getText('viewAcquisitionDate', link.dataset.acquisitionDate);
        getText('viewDisposal', link.dataset.disposalMethod);
        getText('viewRemarks', link.dataset.remarks);
        const fullPageLink = document.getElementById('viewFullPageLink');
        if (fullPageLink) {
            fullPageLink.href = 'asset_details.php?id=' + encodeURIComponent(link.dataset.id || '');
            fullPageLink.target = '_blank';
        }
        const modal = new bootstrap.Modal(document.getElementById('viewModal'));
        modal.show();
        if (window.lucide) {
            lucide.replace({ parent: document.getElementById('viewModal') });
        }
    }

    function openQrModal(link) {
        const wrapper = document.getElementById('qrIframeWrapper');
        const spinner = document.getElementById('qrLoadingSpinner');
        const status = document.getElementById('qrModalStatus');
        const openPageLink = document.getElementById('qrOpenPageLink');
        if (openPageLink) {
            openPageLink.href = 'qr.php?id=' + encodeURIComponent(link.dataset.id || '');
            openPageLink.target = '_blank';
        }
        if (wrapper) {
            wrapper.innerHTML = '';
            wrapper.classList.add('d-none');
        }
        if (spinner) {
            spinner.classList.remove('d-none');
        }
        if (status) {
            status.textContent = 'Loading QR label...';
        }

        const iframe = document.createElement('iframe');
        iframe.src = 'qr.php?id=' + encodeURIComponent(link.dataset.id || '') + '&embed=1';
        iframe.className = 'w-100 border-0';
        iframe.style.minHeight = '360px';
        iframe.onload = function () {
            if (spinner) spinner.classList.add('d-none');
            if (status) status.textContent = '';
            if (wrapper) wrapper.classList.remove('d-none');
        };
        if (wrapper) {
            wrapper.appendChild(iframe);
        }
        new bootstrap.Modal(document.getElementById('qrModal')).show();
    }

    const qrModalEl = document.getElementById('qrModal');
    if (qrModalEl) {
        qrModalEl.addEventListener('hidden.bs.modal', function () {
            const wrapper = document.getElementById('qrIframeWrapper');
            if (wrapper) wrapper.innerHTML = '';
            const spinner = document.getElementById('qrLoadingSpinner');
            if (spinner) spinner.classList.remove('d-none');
            const status = document.getElementById('qrModalStatus');
            if (status) status.textContent = 'Loading QR label...';
        });
    }

    // Live preview of the stored "LABEL (model)" format
    const catLabels = <?= json_encode(array_map(fn($c) => $c['label'], $categories)) ?>;
    const modelInput = document.getElementById('items');
    const modelPreview = document.getElementById('itemsPreview');
    if (modelInput && modelPreview) {
        const escRe = function (s) { return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); };
        const updatePreview = function () {
            const prefix = assetCatSelect ? assetCatSelect.value : '';
            const label = (catLabels[prefix] || '').toUpperCase();
            const model = modelInput.value.trim();
            if (!label) { modelPreview.textContent = model; return; }
            const already = new RegExp('^' + escRe(label) + '\\s*\\(.+\\).*$', 'i');
            modelPreview.textContent =
                already.test(model) ? model
                : (model.toLowerCase() === label.toLowerCase() ? label : label + ' (' + model + ')');
        };
        modelInput.addEventListener('input', updatePreview);
        if (assetCatSelect) assetCatSelect.addEventListener('change', updatePreview);
        updatePreview();
    }

    <?php if ($editAsset): ?>
    document.addEventListener('DOMContentLoaded', function () {
        const assetModalEl = document.getElementById('assetModal');
        if (assetModalEl) {
            new bootstrap.Modal(assetModalEl).show();
        }
    });
    <?php endif; ?>
    </script>

<?php require __DIR__ . '/includes/footer.php'; ?>
