<?php
require __DIR__ . '/config.php';
require_login();
require_admin();

$pageTitle = 'User Management';

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_id'])) {
    require_csrf();
    $uid = (int)$_POST['delete_user_id'];
    if ($uid !== (int)$_SESSION['user_id']) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();
        audit_log($conn, 'DELETE_USER', 'User #' . $uid . ' deleted.');
        $_SESSION['success'] = 'User deleted successfully!';
    } else {
        $_SESSION['success'] = 'You cannot delete your own account.';
    }
    redirect('users.php');
}

$users = $conn->query("SELECT * FROM users ORDER BY id ASC");
$editUser = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $eid = (int)$_GET['edit'];
    $stmt->bind_param('i', $eid);
    $stmt->execute();
    $editUser = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

require __DIR__ . '/includes/header.php';
?>
    <div class="page-header">
        <div class="page-icon"><i data-lucide="users"></i></div>
        <div>
            <h2>User Management</h2>
            <p class="page-subtitle">Manage system user accounts and roles</p>
        </div>
    </div>

    <div class="assets-toolbar mb-3">
        <div class="toolbar-left">
            <div class="text-muted"><?= (int)$users->num_rows ?> user(s)</div>
        </div>
        <div class="toolbar-right">
            <button type="button" class="btn btn-primary toolbar-btn d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#userModal">
                <i data-lucide="user-plus"></i> Add User
            </button>
        </div>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="assets-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Display Name</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($u = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= (int)$u['id'] ?></td>
                            <td><strong><?= h($u['username']) ?></strong></td>
                            <td><?= h($u['display_name']) ?></td>
                            <td>
                                <span class="badge bg-<?= $u['role'] === 'admin' ? 'primary' : 'secondary' ?>">
                                    <?= h($u['role']) ?>
                                </span>
                            </td>
                            <td><?= h(date('M j, Y', strtotime($u['created_at']))) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="users.php?edit=<?= (int)$u['id'] ?>" class="action-btn edit">
                                        <i data-lucide="pencil"></i><span>Edit</span>
                                    </a>
                                    <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                                        <button type="button" class="action-btn delete"
                                                onclick="openUserDelete(<?= (int)$u['id'] ?>, '<?= h(addslashes($u['username'])) ?>')">
                                            <i data-lucide="trash-2"></i><span>Delete</span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add / Edit User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" action="user_save.php">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i data-lucide="<?= $editUser ? 'pencil' : 'user-plus' ?>"></i>
                            <?= $editUser ? 'Edit User' : 'Add User' ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php if ($editUser): ?>
                            <input type="hidden" name="id" value="<?= (int)$editUser['id'] ?>">
                        <?php endif; ?>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required
                                   value="<?= h($editUser['username'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Display Name</label>
                            <input type="text" class="form-control" name="display_name"
                                   value="<?= h($editUser['display_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password <?= $editUser ? '(leave blank to keep current)' : '' ?></label>
                            <input type="password" class="form-control" name="password"
                                   <?= $editUser ? '' : 'required' ?>>
                            <div class="form-text">At least 8 characters, containing letters and numbers.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="staff" <?= ($editUser['role'] ?? '') === 'staff' ? 'selected' : '' ?>>Staff</option>
                                <option value="admin" <?= ($editUser['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i data-lucide="save"></i> Save User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="userDeleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i data-lucide="triangle-alert"></i> Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Delete user "<strong id="delUserName"></strong>"?</p>
                    <p class="text-muted small">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="delete_user_id" id="delUserId" value="">
                        <button type="submit" class="btn btn-danger">
                            <i data-lucide="trash-2"></i> Delete User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function openUserDelete(id, name) {
        document.getElementById('delUserId').value = id;
        document.getElementById('delUserName').textContent = name;
        new bootstrap.Modal(document.getElementById('userDeleteModal')).show();
    }
    <?php if ($editUser): ?>
    new bootstrap.Modal(document.getElementById('userModal')).show();
    <?php endif; ?>
    </script>

<?php require __DIR__ . '/includes/footer.php'; ?>
