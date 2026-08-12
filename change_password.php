<?php
require __DIR__ . '/config.php';
require_login();

$pageTitle = 'Change Password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $uid     = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || !password_verify($current, $row['password_hash'])) {
        $_SESSION['error'] = 'Current password is incorrect.';
        redirect('change_password.php');
    }
    if ($new !== $confirm) {
        $_SESSION['error'] = 'New password and confirmation do not match.';
        redirect('change_password.php');
    }
    if ($new === $current) {
        $_SESSION['error'] = 'New password must be different from the current password.';
        redirect('change_password.php');
    }
    if (($pwErr = password_policy_error($new)) !== '') {
        $_SESSION['error'] = $pwErr;
        redirect('change_password.php');
    }

    $hash = password_hash($new, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->bind_param('si', $hash, $uid);
    $stmt->execute();
    $stmt->close();

    audit_log($conn, 'CHANGE_PASSWORD', 'User changed their own password.');
    $_SESSION['success'] = 'Password changed successfully!';
    redirect('index.php');
}

require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i data-lucide="key-round"></i></div>
        <div>
            <h2>Change Password</h2>
            <p class="page-subtitle">Update the password for your own account</p>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="panel">
                <div class="panel-title"><i data-lucide="shield-check" class="icon-sm me-1"></i> Account Password</div>
                <div class="panel-body">
                    <form method="post" action="change_password.php">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="current_password">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="new_password">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required autocomplete="new-password">
                            <div class="form-text">At least 8 characters, containing letters and numbers.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="confirm_password">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary d-flex align-items-center gap-2">
                            <i data-lucide="save"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="panel">
                <div class="panel-title"><i data-lucide="info" class="icon-sm me-1"></i> How it works</div>
                <div class="panel-body">
                    <p class="text-muted small mb-2">
                        Your new password must match the account password policy:
                        <strong>at least 8 characters</strong>, containing both
                        <strong>letters and numbers</strong>.
                    </p>
                    <p class="text-muted small mb-2">
                        The current password is verified before any change is applied, and
                        the new password must differ from the current one.
                    </p>
                    <p class="text-muted small mb-0">
                        Every password change is recorded in the <strong>audit log</strong>
                        for security tracking.
                    </p>
                </div>
            </div>
        </div>
    </div>

<?php require __DIR__ . '/includes/footer.php'; ?>
