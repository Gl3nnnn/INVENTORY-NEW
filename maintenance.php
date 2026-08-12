<?php
require __DIR__ . '/config.php';
require_login();
require_admin();

$pageTitle = 'Maintenance Log';

// ---- Mutations ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = (string)($_POST['action'] ?? '');
    $assetId = (int)($_POST['asset_id'] ?? 0);
    $date = trim((string)($_POST['maintenance_date'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $by   = trim((string)($_POST['performed_by'] ?? ''));
    $cost = (float)($_POST['cost'] ?? 0);
    $mid  = (int)($_POST['id'] ?? 0);

    if ($assetId <= 0 || $desc === '') {
        $_SESSION['error'] = 'Asset and description are required.';
        redirect('maintenance.php');
    }

    if ($action === 'delete' && $mid > 0) {
        $stmt = $conn->prepare("DELETE FROM maintenance_log WHERE id = ?");
        $stmt->bind_param('i', $mid);
        $stmt->execute();
        $stmt->close();
        audit_log($conn, 'DELETE_MAINTENANCE', "Maintenance record #$mid deleted.");
        $_SESSION['success'] = 'Maintenance record deleted.';
        redirect('maintenance.php');
    }

    if ($action === 'edit' && $mid > 0) {
        $stmt = $conn->prepare(
            "UPDATE maintenance_log SET asset_id = ?, maintenance_date = ?, description = ?, performed_by = ?, cost = ? WHERE id = ?"
        );
        $stmt->bind_param('issdsi', $assetId, $date, $desc, $by, $cost, $mid);
        $stmt->execute();
        $stmt->close();
        audit_log($conn, 'UPDATE_MAINTENANCE', "Maintenance record #$mid updated.");
        $_SESSION['success'] = 'Maintenance record updated.';
        redirect('maintenance.php');
    }

    // add
    $stmt = $conn->prepare(
        "INSERT INTO maintenance_log (asset_id, maintenance_date, description, performed_by, cost) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('isssd', $assetId, $date, $desc, $by, $cost);
    $stmt->execute();
    $newId = (int)$stmt->insert_id;
    $stmt->close();
    audit_log($conn, 'ADD_MAINTENANCE', "Maintenance record #$newId added for asset #$assetId.");
    $_SESSION['success'] = 'Maintenance record added.';
    redirect('maintenance.php');
}

// ---- Filters ----
$clauses = [];
$params  = [];
if (!empty($_GET['asset_id'])) {
    $assetIdFilter = (int)$_GET['asset_id'];
    $clauses[] = "m.asset_id = ?";
    $params[]  = $assetIdFilter;
}
if (!empty($_GET['q'])) {
    $like = '%' . trim((string)$_GET['q']) . '%';
    $clauses[] = "(a.items LIKE ? OR a.property_code LIKE ? OR m.description LIKE ?)";
    $params[]  = $like;
    $params[]  = $like;
    $params[]  = $like;
}
$whereSQL = '';
if (count($clauses) > 0) {
    $whereSQL = 'WHERE ' . implode(' AND ', $clauses);
}

$limit  = isset($_GET['limit']) ? max(5, min(100, (int)$_GET['limit'])) : 10;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$totalRows = (int)run_query($conn,
    "SELECT COUNT(*) AS c FROM maintenance_log m JOIN it_asset_inventory a ON a.id = m.asset_id $whereSQL",
    $params)->fetch_assoc()['c'];
$totalPages = max(1, (int)ceil($totalRows / $limit));

$rows = run_query($conn,
    "SELECT m.*, a.items AS asset_items, a.property_code AS asset_code
     FROM maintenance_log m JOIN it_asset_inventory a ON a.id = m.asset_id
     $whereSQL ORDER BY m.maintenance_date DESC, m.id DESC LIMIT $limit OFFSET $offset",
    $params);

// Assets for the select
$assets = $conn->query("SELECT id, items, property_code FROM it_asset_inventory ORDER BY property_code ASC");

// Edit mode
$editMaint = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM maintenance_log WHERE id = ?");
    $eid = (int)$_GET['edit'];
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $editMaint = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i data-lucide="wrench"></i></div>
        <div>
            <h2>Maintenance Log</h2>
            <p class="page-subtitle">Repair and upkeep records for all assets</p>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#maintModal">
                <i data-lucide="plus"></i> Add Maintenance
            </button>
        </div>
        <div class="text-muted">Total: <strong><?= $totalRows ?></strong> record<?= $totalRows === 1 ? '' : 's' ?></div>
    </div>

    <!-- Filters -->
    <form method="get" class="filters-card mb-3">
        <div class="filters-content">
            <div class="row gx-3 gy-2 align-items-end">
                <div class="col-lg-5 col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="q" class="form-control" placeholder="Model, property code, description..."
                           value="<?= h($_GET['q'] ?? '') ?>">
                </div>
                <div class="col-lg-4 col-md-6">
                    <label class="form-label">Asset</label>
                    <select name="asset_id" class="form-select">
                        <option value="">All Assets</option>
                        <?php $assets->data_seek(0); while ($a = $assets->fetch_assoc()): ?>
                            <option value="<?= (int)$a['id'] ?>" <?= (isset($_GET['asset_id']) && (int)$_GET['asset_id'] === (int)$a['id']) ? 'selected' : '' ?>>
                                <?= h($a['property_code']) ?> &middot; <?= h($a['items']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-12 d-flex align-items-end gap-2">
                    <button type="submit" class="filter-btn primary">
                        <i data-lucide="search" class="icon-sm"></i><span>Apply</span>
                    </button>
                    <a href="maintenance.php" class="filter-btn secondary">
                        <i data-lucide="x" class="icon-sm"></i><span>Clear</span>
                    </a>
                </div>
            </div>
        </div>
    </form>

    <div class="table-container">
        <div class="table-responsive">
            <table class="assets-table">
                <thead>
                    <tr>
                        <th>Asset</th>
                        <th>Maintenance Date</th>
                        <th>Description</th>
                        <th>Performed By</th>
                        <th>Cost</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalRows === 0): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i data-lucide="inbox" class="icon-lg text-muted"></i>
                                <p class="mt-2 text-muted mb-0">No maintenance records found.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php while ($r = $rows->fetch_assoc()): ?>
                        <tr>
                            <td class="maintenance-asset-cell">
                                <a href="asset_details.php?id=<?= (int)$r['asset_id'] ?>"><strong><?= h($r['asset_code']) ?></strong></a>
                                <span class="text-muted small"><?= h($r['asset_items']) ?></span>
                            </td>
                            <td><?= h(date('M j, Y', strtotime($r['maintenance_date']))) ?></td>
                            <td class="text-wrap" style="max-width: 280px;"><?= h($r['description']) ?></td>
                            <td><?= h($r['performed_by']) ?></td>
                            <td><?= $r['cost'] > 0 ? '&#8369;' . number_format((float)$r['cost'], 2) : '' ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="maintenance.php?edit=<?= (int)$r['id'] ?>" class="action-btn edit">
                                        <i data-lucide="pencil"></i><span>Edit</span>
                                    </a>
                                    <button type="button" class="action-btn delete" onclick="openMaintDelete(<?= (int)$r['id'] ?>)">
                                        <i data-lucide="trash-2"></i><span>Delete</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
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
            <?php if ($page > 1): ?><li class="page-item"><a class="page-link" href="<?= h($base($page - 1)) ?>">&laquo;</a></li><?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= h($base($i)) ?>"><?= $i ?></a></li>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?><li class="page-item"><a class="page-link" href="<?= h($base($page + 1)) ?>">&raquo;</a></li><?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <?php
    $mAssetId   = $editMaint['asset_id'] ?? ($_GET['asset_id'] ?? 0);
    $mDate      = $editMaint['maintenance_date'] ?? date('Y-m-d');
    $mDesc      = $editMaint['description'] ?? '';
    $mBy        = $editMaint['performed_by'] ?? '';
    $mCost      = $editMaint['cost'] ?? 0;
    ?>
    <!-- Add / Edit Maintenance Modal -->
    <div class="modal fade" id="maintModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="maintenance.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $editMaint ? 'edit' : 'add' ?>">
                    <?php if ($editMaint): ?><input type="hidden" name="id" value="<?= (int)$editMaint['id'] ?>"><?php endif; ?>
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i data-lucide="<?= $editMaint ? 'pencil' : 'wrench' ?>"></i>
                            <?= $editMaint ? 'Edit Maintenance Record' : 'Add Maintenance Record' ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="m_asset">Asset</label>
                            <select class="form-select" id="m_asset" name="asset_id" required>
                                <option value="">-- Select Asset --</option>
                                <?php $assets->data_seek(0); while ($a = $assets->fetch_assoc()): ?>
                                    <option value="<?= (int)$a['id'] ?>" <?= (int)$mAssetId === (int)$a['id'] ? 'selected' : '' ?>>
                                        <?= h($a['property_code']) ?> &middot; <?= h($a['items']) ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="m_date">Maintenance Date</label>
                            <input type="date" class="form-control" id="m_date" name="maintenance_date" value="<?= h($mDate) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="m_desc">Description</label>
                            <textarea class="form-control" id="m_desc" name="description" rows="3" required placeholder="e.g. Replaced defective power supply"><?= h($mDesc) ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="m_by">Performed By</label>
                            <input type="text" class="form-control" id="m_by" name="performed_by" placeholder="e.g. Ryan Aldrich Lavilla" value="<?= h($mBy) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="m_cost">Cost (PHP)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="m_cost" name="cost" value="<?= h($mCost) ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i> <?= $editMaint ? 'Save Changes' : 'Add Record' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="maintDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i data-lucide="triangle-alert"></i> Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-0">Delete this maintenance record? This cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="maintDelId" value="">
                        <button type="submit" class="btn btn-danger">
                            <i data-lucide="trash-2"></i> Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openMaintDelete(id) {
        document.getElementById('maintDelId').value = id;
        new bootstrap.Modal(document.getElementById('maintDeleteModal')).show();
    }
    <?php if ($editMaint): ?>
    document.addEventListener('DOMContentLoaded', function () {
        new bootstrap.Modal(document.getElementById('maintModal')).show();
    });
    <?php endif; ?>
    </script>

<?php require __DIR__ . '/includes/footer.php'; ?>
