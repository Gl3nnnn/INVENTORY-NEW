<?php
require __DIR__ . '/config.php';
require_login();
require_admin();
require_csrf();

$editId = (int)($_POST['id'] ?? 0);
$username = trim((string)($_POST['username'] ?? ''));
$display  = trim((string)($_POST['display_name'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$role     = ($_POST['role'] ?? 'staff') === 'admin' ? 'admin' : 'staff';

if ($username === '') {
    $_SESSION['error'] = 'Username is required.';
    redirect('users.php');
}

// Password policy: enforced whenever a (new) password is supplied
if ($password !== '' && ($pwErr = password_policy_error($password)) !== '') {
    $_SESSION['error'] = $pwErr;
    redirect('users.php');
}

// Prevent removing the last admin / demoting self
if ($editId > 0 && $editId === (int)$_SESSION['user_id'] && $role !== 'admin') {
    $_SESSION['error'] = 'You cannot change your own role to non-admin.';
    redirect('users.php');
}

// Check for duplicate username (excluding self)
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ?");
$selfId = $editId > 0 ? $editId : 0;
$stmt->bind_param('si', $username, $selfId);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    $stmt->close();
    $_SESSION['error'] = 'That username is already taken.';
    redirect('users.php');
}
$stmt->close();

if ($editId > 0) {
    // Update existing user
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username=?, display_name=?, role=?, password_hash=? WHERE id=?");
        $stmt->bind_param('ssssi', $username, $display, $role, $hash, $editId);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username=?, display_name=?, role=? WHERE id=?");
        $stmt->bind_param('sssi', $username, $display, $role, $editId);
    }
    $stmt->execute();
    $stmt->close();

    // Keep the session in sync if editing self
    if ($editId === (int)$_SESSION['user_id']) {
        $_SESSION['username']     = $username;
        $_SESSION['display_name'] = $display;
        $_SESSION['role']         = $role;
    }

    audit_log($conn, 'UPDATE_USER', 'User #' . $editId . ' (' . $username . ', role: ' . $role . ') updated.');
    $_SESSION['success'] = 'User updated successfully!';
    redirect('users.php');
}

// Create new user
if ($password === '') {
    $_SESSION['error'] = 'Password is required for new users.';
    redirect('users.php');
}
$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (username, display_name, password_hash, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param('ssss', $username, $display, $hash, $role);
$stmt->execute();
$newUserId = (int)$stmt->insert_id;
$stmt->close();

audit_log($conn, 'ADD_USER', 'User #' . $newUserId . ' (' . $username . ', role: ' . $role . ') created.');
$_SESSION['success'] = 'User created successfully!';
redirect('users.php');
