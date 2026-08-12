<?php
require __DIR__ . '/config.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $conn->prepare("SELECT * FROM it_asset_inventory WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$asset) {
    $_SESSION['error'] = 'Asset not found.';
    redirect('assets.php');
}

$history = $conn->prepare("SELECT * FROM asset_history WHERE asset_id = ? ORDER BY created_at DESC, id DESC");
$history->bind_param('i', $id);
$history->execute();
$historyRes = $history->get_result();

$maint = $conn->prepare("SELECT * FROM maintenance_log WHERE asset_id = ? ORDER BY maintenance_date DESC, id DESC");
$maint->bind_param('i', $id);
$maint->execute();
$maintRes = $maint->get_result();

$pageTitle = $asset['property_code'] ?? 'Asset Details';
require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i data-lucide="eye"></i></div>
        <div>
            <h2><?= h($asset['property_code']) ?></h2>
            <p class="page-subtitle"><?= h($asset['items']) ?></p>
        </div>
    </div>

    <div class="d-flex gap-2 flex-wrap mb-3">
        <?php if (is_admin()): ?>
            <a href="assets.php?edit=<?= (int)$asset['id'] ?>" class="btn btn-primary d-flex align-items-center gap-2">
                <i data-lucide="pencil"></i> Edit Asset
            </a>
            <a href="maintenance.php?asset_id=<?= (int)$asset['id'] ?>" class="btn btn-outline-primary d-flex align-items-center gap-2">
                <i data-lucide="wrench"></i> Add Maintenance
            </a>
        <?php endif; ?>
        <a href="qr.php?id=<?= (int)$asset['id'] ?>" class="btn btn-outline-secondary d-flex align-items-center gap-2" target="_blank">
            <i data-lucide="qrcode"></i> QR Label
        </a>
        <a href="assets.php" class="btn btn-outline-secondary d-flex align-items-center gap-2">
            <i data-lucide="arrow-left"></i> Back
        </a>
    </div>

    <div class="row g-4">
        <!-- Asset details -->
        <div class="col-lg-7">
            <div class="panel">
                <div class="panel-title"><i data-lucide="hard-drive" class="icon-sm me-1"></i> Asset Details</div>
                <div class="panel-body">
                    <?php
                    $st = $asset['status'] ?? 'Active';
                    $sc = $st === 'Active' ? 'success' : ($st === 'In Repair' ? 'warning text-dark' : 'secondary');
                    $rows = [
                        'Property Code'   => $asset['property_code'],
                        'Model'           => $asset['items'],
                        'Location'        => $asset['location'],
                        'Issued To'       => $asset['issued_to'],
                        'Life Span'       => $asset['life_span'],
                        'Acquisition Date'=> $asset['acquisition_date'],
                        'Disposal Method' => $asset['disposal_method'],
                        'Remarks'         => $asset['remarks'],
                    ];
                    ?>
                    <table class="table table-striped align-middle mb-0">
                        <tbody>
                            <tr>
                                <th style="width:40%;">Status</th>
                                <td><span class="badge bg-<?= $sc ?>"><?= h($st) ?></span></td>
                            </tr>
                            <?php foreach ($rows as $label => $value): ?>
                                <tr>
                                    <th><?= h($label) ?></th>
                                    <td><?= h($value) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Assignment history -->
        <div class="col-lg-5">
            <div class="panel">
                <div class="panel-title"><i data-lucide="history" class="icon-sm me-1"></i> Assignment History</div>
                <div class="panel-body">
                    <?php if ($historyRes->num_rows === 0): ?>
                        <p class="text-muted mb-0">No recorded changes yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr><th>Field</th><th>Change</th><th>By</th><th>When</th></tr>
                                </thead>
                                <tbody>
                                    <?php while ($h = $historyRes->fetch_assoc()): ?>
                                        <tr>
                                            <td><span class="badge bg-light text-primary border"><?= h($h['field_name']) ?></span></td>
                                            <td class="small text-wrap" style="max-width: 220px;">
                                                <?php
                                                $old = $h['old_value'] === '' ? '(empty)' : $h['old_value'];
                                                $new = $h['new_value'] === '' ? '(empty)' : $h['new_value'];
                                                ?>
                                                <span class="text-danger text-decoration-line-through"><?= h($old) ?></span>
                                                &rarr;
                                                <span class="text-success"><?= h($new) ?></span>
                                            </td>
                                            <td class="small"><?= h($h['changed_by']) ?></td>
                                            <td class="small"><?= h(date('M j, Y g:i A', strtotime($h['created_at']))) ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Maintenance -->
            <div class="panel mt-4">
                <div class="panel-title"><i data-lucide="wrench" class="icon-sm me-1"></i> Maintenance Records</div>
                <div class="panel-body">
                    <?php if ($maintRes->num_rows === 0): ?>
                        <p class="text-muted mb-0">No maintenance records.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr><th>Date</th><th>Description</th><th>Cost</th></tr>
                                </thead>
                                <tbody>
                                    <?php while ($m = $maintRes->fetch_assoc()): ?>
                                        <tr>
                                            <td class="small"><?= h(date('M j, Y', strtotime($m['maintenance_date']))) ?></td>
                                            <td class="small text-wrap">
                                                <?= h($m['description']) ?>
                                                <?php if ($m['performed_by'] !== ''): ?>
                                                    <div class="text-muted">by <?= h($m['performed_by']) ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small"><?= $m['cost'] > 0 ? '&#8369;' . number_format((float)$m['cost'], 2) : '' ?></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
