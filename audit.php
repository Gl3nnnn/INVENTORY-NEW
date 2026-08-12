<?php
require __DIR__ . '/config.php';
require_login();
require_admin();

$pageTitle = 'Audit Log';

// ---- Filters ----
$clauses = [];
$params  = [];

if (!empty($_GET['action'])) {
    $clauses[] = "action = ?";
    $params[]  = (string)$_GET['action'];
}
if (!empty($_GET['username'])) {
    $clauses[] = "username LIKE ?";
    $params[]  = '%' . trim((string)$_GET['username']) . '%';
}
if (!empty($_GET['from'])) {
    $clauses[] = "DATE(created_at) >= ?";
    $params[]  = (string)$_GET['from'];
}
if (!empty($_GET['to'])) {
    $clauses[] = "DATE(created_at) <= ?";
    $params[]  = (string)$_GET['to'];
}

$whereSQL = '';
if (count($clauses) > 0) {
    $whereSQL = 'WHERE ' . implode(' AND ', $clauses);
}

$limit  = isset($_GET['limit']) ? max(10, min(200, (int)$_GET['limit'])) : 50;
$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$res = run_query($conn, "SELECT COUNT(*) AS c FROM audit_log $whereSQL", $params);
$totalRows = (int)$res->fetch_assoc()['c'];
$totalPages = max(1, (int)ceil($totalRows / $limit));

$rows = run_query($conn, "SELECT * FROM audit_log $whereSQL ORDER BY id DESC LIMIT $limit OFFSET $offset", $params);

$actionsRes = $conn->query("SELECT DISTINCT action FROM audit_log ORDER BY action ASC");
$actions = [];
while ($a = $actionsRes->fetch_assoc()) {
    $actions[] = $a['action'];
}

require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i data-lucide="scroll-text"></i></div>
        <div>
            <h2>Audit Log</h2>
            <p class="page-subtitle">Record of system activity and changes</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-card mb-3">
        <div class="filters-header">
            <button class="filter-toggle-btn" type="button" data-bs-toggle="collapse" data-bs-target="#auditFilterSection">
                <i data-lucide="filter" class="icon-sm"></i>
                <span>Filters</span>
            </button>
        </div>
        <div class="collapse <?= count(array_intersect_key($_GET, ['action'=>1,'username'=>1,'from'=>1,'to'=>1])) ? 'show' : '' ?>" id="auditFilterSection">
            <div class="filters-content">
                <form method="get" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Action</label>
                        <select name="action" class="form-select">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $a): ?>
                                <option value="<?= h($a) ?>" <?= ($_GET['action'] ?? '') === $a ? 'selected' : '' ?>><?= h($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. admin" value="<?= h($_GET['username'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from" class="form-control" value="<?= h($_GET['from'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to" class="form-control" value="<?= h($_GET['to'] ?? '') ?>">
                    </div>
                    <div class="col-12 d-flex gap-3">
                        <button type="submit" class="filter-btn primary">
                            <i data-lucide="search" class="icon-sm"></i><span>Apply Filters</span>
                        </button>
                        <a href="audit.php" class="filter-btn secondary">
                            <i data-lucide="x" class="icon-sm"></i><span>Clear Filters</span>
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="assets-toolbar mb-3">
        <div class="toolbar-left">
            <div class="text-muted">Showing <?= $totalRows ? ($offset + 1) : 0 ?> to <?= min($offset + $limit, $totalRows) ?> of <?= $totalRows ?> entries</div>
        </div>
        <div class="toolbar-right">
            <select class="form-select form-select-sm" style="width: 90px;" onchange="changeLimit(this.value)">
            <?php foreach ([25, 50, 100, 200] as $n): ?>
                <option value="<?= $n ?>" <?= $limit === $n ? 'selected' : '' ?>><?= $n ?></option>
            <?php endforeach; ?>
        </select>
        </div>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="assets-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Date / Time</th>
                        <th>Username</th>
                        <th>Action</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($totalRows === 0): ?>
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <i data-lucide="inbox" class="icon-lg text-muted"></i>
                                <p class="mt-2 text-muted mb-0">No audit records found.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php while ($r = $rows->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$r['id'] ?></td>
                            <td><?= h(date('M j, Y g:i A', strtotime($r['created_at']))) ?></td>
                            <td><strong><?= h($r['username']) ?></strong></td>
                            <td><span class="badge bg-light text-primary border"><?= h($r['action']) ?></span></td>
                            <td class="text-wrap" style="max-width: 420px;"><?= h($r['details']) ?></td>
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
            <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= h($base($page - 1)) ?>">&laquo;</a></li>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                    <a class="page-link" href="<?= h($base($i)) ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <li class="page-item"><a class="page-link" href="<?= h($base($page + 1)) ?>">&raquo;</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <script>
    function changeLimit(limit) {
        const url = new URL(window.location);
        url.searchParams.set('limit', limit);
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }
    </script>

<?php require __DIR__ . '/includes/footer.php'; ?>
