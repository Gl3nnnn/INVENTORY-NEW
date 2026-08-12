<?php
require __DIR__ . '/config.php';
require_login();
require_admin();

$pageTitle = 'Document Settings';

$fields = [
    'doc_id'            => ['label' => 'Document ID',                 'default' => DOC_ID],
    'doc_revision'      => ['label' => 'Revision No.',                'default' => DOC_REVISION],
    'doc_date_approved' => ['label' => 'Date Approved',               'default' => DOC_DATE_APPROVED],
    'doc_classification'=> ['label' => 'Classification',              'default' => DOC_CLASSIFICATION],
    'doc_branch'        => ['label' => 'Branch',                      'default' => DOC_BRANCH],
    'doc_department'    => ['label' => 'Department',                  'default' => DOC_DEPARTMENT],
    'doc_title'         => ['label' => 'Document Title',              'default' => DOC_TITLE],
    'doc_org'           => ['label' => 'Organization / Header Line',  'default' => DOC_ORG],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    foreach ($fields as $key => $info) {
        $value = trim((string)($_POST[$key] ?? ''));
        settings_set($conn, $key, $value);
    }
    audit_log($conn, 'UPDATE_SETTINGS', 'Document control settings updated.');
    $_SESSION['success'] = 'Settings saved successfully!';
    redirect('settings.php');
}

$values = [];
foreach ($fields as $key => $info) {
    $values[$key] = settings_get($conn, $key, $info['default']);
}

require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i data-lucide="settings"></i></div>
        <div>
            <h2>Document Settings</h2>
            <p class="page-subtitle">Edit the values shown on printed inventory documents and PDF exports</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="panel">
                <div class="panel-title"><i data-lucide="file-text" class="icon-sm me-1"></i> Document Control Header</div>
                <div class="panel-body">
                    <form method="post" action="settings.php">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="doc_id">Document ID</label>
                                <input type="text" class="form-control" id="doc_id" name="doc_id" value="<?= h($values['doc_id']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="doc_revision">Revision No.</label>
                                <input type="text" class="form-control" id="doc_revision" name="doc_revision" value="<?= h($values['doc_revision']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="doc_date_approved">Date Approved</label>
                                <input type="text" class="form-control" id="doc_date_approved" name="doc_date_approved" value="<?= h($values['doc_date_approved']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="doc_classification">Classification</label>
                                <input type="text" class="form-control" id="doc_classification" name="doc_classification" value="<?= h($values['doc_classification']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="doc_branch">Branch</label>
                                <input type="text" class="form-control" id="doc_branch" name="doc_branch" value="<?= h($values['doc_branch']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="doc_department">Department</label>
                                <input type="text" class="form-control" id="doc_department" name="doc_department" value="<?= h($values['doc_department']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="doc_title">Document Title</label>
                                <input type="text" class="form-control" id="doc_title" name="doc_title" value="<?= h($values['doc_title']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="doc_org">Organization / Header Line</label>
                                <input type="text" class="form-control" id="doc_org" name="doc_org" value="<?= h($values['doc_org']) ?>">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-4">
                            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
                                <i data-lucide="save"></i> Save Settings
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel">
                <div class="panel-title"><i data-lucide="info" class="icon-sm me-1"></i> How it works</div>
                <div class="panel-body">
                    <p class="text-muted small mb-2">
                        These values are used in the header of the <strong>print preview</strong> and
                        <strong>PDF export</strong> inventory documents.
                    </p>
                    <p class="text-muted small mb-0">
                        Values saved here override the defaults in <code>config.php</code>. Leave a field
                        blank to hide that line on the printed document.
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
