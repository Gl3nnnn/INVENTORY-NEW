<?php
require __DIR__ . '/config.php';
require_login();

$pageTitle = 'Dashboard';

$totalAssets = (int)$conn->query("SELECT COUNT(*) AS c FROM it_asset_inventory")->fetch_assoc()['c'];

// Status distribution
$statusCounts = ['Active' => 0, 'In Repair' => 0, 'Disposed' => 0];
$statusRes = $conn->query("SELECT status, COUNT(*) AS c FROM it_asset_inventory GROUP BY status");
while ($row = $statusRes->fetch_assoc()) {
    $statusCounts[$row['status']] = (int)$row['c'];
}

// Category distribution (parsed from the property code prefix)
$cats = asset_categories();
$catCounts = array_fill_keys(array_keys($cats), 0);
$res = $conn->query("SELECT property_code FROM it_asset_inventory");
while ($row = $res->fetch_assoc()) {
    $p = category_prefix_from_code((string)$row['property_code']);
    if ($p !== '' && isset($catCounts[$p])) {
        $catCounts[$p]++;
    }
}
arsort($catCounts);

// Top locations
$topLocations = $conn->query(
    "SELECT location, COUNT(*) AS c FROM it_asset_inventory
     WHERE location IS NOT NULL AND location <> '' AND location <> 'N/A'
     GROUP BY location ORDER BY c DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

// Recently added
$recent = $conn->query("SELECT * FROM it_asset_inventory ORDER BY id DESC LIMIT 6");

// Distinct item types (first token of items) — quick overview
$itemTypes = $conn->query(
    "SELECT items, COUNT(*) AS c FROM it_asset_inventory
     GROUP BY items ORDER BY c DESC LIMIT 8"
);

// Expected-life analysis (acquisition_date + life_span)
function lifespan_max_years(string $s): int
{
    $s = trim($s);
    if (preg_match('/(\d+)\s*(?:to|-)\s*(\d+)\s*years?/i', $s, $m)) {
        return (int)$m[2];
    }
    if (preg_match('/(\d+)\s*years?/i', $s, $m)) {
        return (int)$m[1];
    }
    return 0;
}

$expiring = [];
$expired  = [];
$lifeRes = $conn->query(
    "SELECT id, items, property_code, location, acquisition_date, life_span, status
     FROM it_asset_inventory WHERE status <> 'Disposed'"
);
while ($row = $lifeRes->fetch_assoc()) {
    $acq = trim((string)$row['acquisition_date']);
    if ($acq === '' || strcasecmp($acq, 'N/A') === 0) {
        continue;
    }
    $dt = DateTime::createFromFormat('n/j/Y', $acq);
    if (!$dt) {
        continue;
    }
    $years = lifespan_max_years((string)$row['life_span']);
    if ($years <= 0) {
        continue;
    }
    $end = clone $dt;
    $end->modify("+{$years} years");
    $today = new DateTime();
    $entry = [
        'id'   => (int)$row['id'],
        'code' => (string)$row['property_code'],
        'items'=> (string)$row['items'],
        'loc'  => (string)$row['location'],
        'end'  => $end,
    ];
    if ($end < $today) {
        $entry['days'] = (int)$today->diff($end)->format('%a') * -1;
        $expired[] = $entry;
    } else {
        $days = (int)$today->diff($end)->format('%a');
        if ($days <= 365) {
            $entry['days'] = $days;
            $expiring[] = $entry;
        }
    }
}
usort($expiring, fn($a, $b) => $a['end'] <=> $b['end']);
usort($expired, fn($a, $b) => $a['end'] <=> $b['end']);
$expiring = array_slice($expiring, 0, 10);
$expired  = array_slice($expired, 0, 10);

require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i data-lucide="layout-dashboard"></i></div>
        <div>
            <h2>Dashboard</h2>
            <p class="page-subtitle">Overview of the I.T. infrastructure inventory</p>
        </div>
    </div>

    <div class="dashboard-actions row g-3 mb-4">
        <div class="col-sm-6 col-md-3">
            <a href="assets.php" class="dashboard-action-card">
                <div>
                    <h3>Assets</h3>
                    <p>Browse inventory, search assets, and update records.</p>
                </div>
                <i data-lucide="hard-drive"></i>
            </a>
        </div>
        <div class="col-sm-6 col-md-3">
            <a href="maintenance.php" class="dashboard-action-card">
                <div>
                    <h3>Maintenance</h3>
                    <p>Track repairs, service history, and upcoming jobs.</p>
                </div>
                <i data-lucide="wrench"></i>
            </a>
        </div>
        <div class="col-sm-6 col-md-3">
            <a href="users.php" class="dashboard-action-card">
                <div>
                    <h3>Users</h3>
                    <p>Manage access, add staff, and update roles.</p>
                </div>
                <i data-lucide="users"></i>
            </a>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#0d2a5e;"><i data-lucide="package"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($totalAssets) ?></div>
                    <div class="stat-label">Total Assets</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#1d8a50;"><i data-lucide="circle-check"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($statusCounts['Active']) ?></div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#e0a12e;"><i data-lucide="wrench"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($statusCounts['In Repair']) ?></div>
                    <div class="stat-label">In Repair</div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#d0583a;"><i data-lucide="archive"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($statusCounts['Disposed']) ?></div>
                    <div class="stat-label">Disposed</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-title"><i data-lucide="pie-chart" class="icon-sm me-1"></i> Assets by Category</div>
                <div class="panel-body">
                    <div class="chart-wrap">
                        <canvas id="catChart" height="260"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-title"><i data-lucide="map-pin" class="icon-sm me-1"></i> Top Locations</div>
                <div class="panel-body">
                    <div class="chart-wrap">
                        <canvas id="locChart" height="260"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Status doughnut -->
        <div class="col-lg-4">
            <div class="panel h-100">
                <div class="panel-title"><i data-lucide="activity" class="icon-sm me-1"></i> Assets by Status</div>
                <div class="panel-body">
                    <div class="chart-wrap">
                        <canvas id="statusChart" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expected life -->
        <div class="col-lg-8">
            <div class="panel h-100">
                <div class="panel-title">
                    <i data-lucide="timer" class="icon-sm me-1"></i> Expected Life
                    <span class="text-muted small fw-normal ms-2">based on acquisition date &amp; life span</span>
                </div>
                <div class="panel-body">
                    <?php if (!$expiring && !$expired): ?>
                        <p class="text-muted mb-0">No assets with a computable end-of-life date.</p>
                    <?php else: ?>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 class="text-danger small fw-bold text-uppercase mb-2">
                                    <i data-lucide="alert-triangle" class="icon-sm"></i>
                                    Past expected life (<?= count($expired) ?>)
                                </h6>
                                <?php if ($expired): ?>
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead><tr><th>Code</th><th>Model</th><th>Overdue</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($expired as $e): ?>
                                                <tr>
                                                    <td><a href="asset_details.php?id=<?= $e['id'] ?>" class="text-danger small"><?= h($e['code']) ?></a></td>
                                                    <td class="small text-wrap" style="max-width:160px;"><?= h($e['items']) ?></td>
                                                    <td><span class="badge bg-danger"><?= number_format(abs($e['days'])) ?>d</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="text-muted small mb-0">None past expected life.</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-warning small fw-bold text-uppercase mb-2">
                                    <i data-lucide="hourglass" class="icon-sm"></i>
                                    Expiring within 12 months (<?= count($expiring) ?>)
                                </h6>
                                <?php if ($expiring): ?>
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead><tr><th>Code</th><th>Model</th><th>Remaining</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($expiring as $e): ?>
                                                <tr>
                                                    <td><a href="asset_details.php?id=<?= $e['id'] ?>" class="small"><?= h($e['code']) ?></a></td>
                                                    <td class="small text-wrap" style="max-width:160px;"><?= h($e['items']) ?></td>
                                                    <td><span class="badge bg-warning text-dark"><?= number_format($e['days']) ?>d</span></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="text-muted small mb-0">Nothing expiring soon.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Common item descriptions -->
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-title"><i data-lucide="tags" class="icon-sm me-1"></i> Most Common Models</div>
                <div class="panel-body">
                    <?php $types = $itemTypes->fetch_all(MYSQLI_ASSOC); if ($types): ?>
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead><tr><th>Item Description</th><th class="text-end">Count</th></tr></thead>
                            <tbody>
                                <?php foreach ($types as $t): ?>
                                    <tr>
                                        <td><?= h($t['items']) ?></td>
                                        <td class="text-end fw-bold"><?= number_format((int)$t['c']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p class="text-muted mb-0">No data.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent additions -->
        <div class="col-lg-6">
            <div class="panel h-100">
                <div class="panel-title"><i data-lucide="clock" class="icon-sm me-1"></i> Recently Added</div>
                <div class="panel-body">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Property Code</th><th>Model</th><th>Location</th></tr></thead>
                        <tbody>
                            <?php while ($r = $recent->fetch_assoc()): ?>
                                <tr>
                                    <td><a href="asset_details.php?id=<?= (int)$r['id'] ?>"><span class="badge bg-light text-primary border"><?= h($r['property_code']) ?></span></a></td>
                                    <td><?= h($r['items']) ?></td>
                                    <td><?= h($r['location']) ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var brand  = '#0d2a5e';
        var brandL = '#1f4fa8';
        var accent = '#4361ee';
        var green  = '#1d8a50';
        var amber  = '#e0a12e';
        var red    = '#d0583a';
        var palette = ['#0d2a5e','#1f4fa8','#4361ee','#1d8a50','#e0a12e','#d0583a','#5a6e99','#8ab4f8','#6a9b7f','#c98d4b'];

        // Category pie
        var catData = <?= json_encode(array_values($catCounts)) ?>;
        var catLabels = <?= json_encode(array_map('category_label', array_keys($catCounts))) ?>;
        var catCanvas = document.getElementById('catChart');
        if (catCanvas) {
            new Chart(catCanvas, {
                type: 'pie',
                data: {
                    labels: catLabels,
                    datasets: [{
                        data: catData,
                        backgroundColor: palette,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right' } }
                }
            });
        }

        // Location bar
        var locLabels = <?= json_encode(array_column($topLocations, 'location')) ?>;
        var locData = <?= json_encode(array_map(fn($l) => (int)$l['c'], $topLocations)) ?>;
        var locCanvas = document.getElementById('locChart');
        if (locCanvas) {
            new Chart(locCanvas, {
                type: 'bar',
                data: {
                    labels: locLabels,
                    datasets: [{
                        label: 'Assets',
                        data: locData,
                        backgroundColor: brandL,
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: { legend: { display: false } },
                    scales: { x: { grid: { color: '#eef1f6' } }, y: { grid: { display: false } } }
                }
            });
        }

        // Status doughnut
        var statusCanvas = document.getElementById('statusChart');
        if (statusCanvas) {
            new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_keys($statusCounts)) ?>,
                    datasets: [{
                        data: <?= json_encode(array_values($statusCounts)) ?>,
                        backgroundColor: [green, amber, red],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    });
    </script>

<?php require __DIR__ . '/includes/footer.php'; ?>
