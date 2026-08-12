<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_login();

$currentPage = basename($_SERVER['PHP_SELF']);
$assetPages  = ['assets.php'];
$userPages   = ['users.php', 'add_user.php', 'edit_user.php'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? h($pageTitle) . ' - ' . APP_NAME : APP_NAME ?></title>
    <link rel="icon" href="<?= LOGO_FILE ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body>
  <!-- Sea loader overlay (global) -->
  <div id="seaLoader" aria-hidden="true">
    <div class="sea-wrap" role="status" aria-live="polite">
      <div class="sea-text">Processing</div>
      <div class="sea-wave wave-3"></div>
      <div class="sea-wave wave-2"></div>
      <div class="sea-wave wave-1 sea-wave"></div>
      <div class="sea-bubbles">
        <div class="sea-bubble b1" style="left:22%; bottom:12px;"></div>
        <div class="sea-bubble b2" style="left:58%; bottom:18px;"></div>
        <div class="sea-bubble b3" style="left:36%; bottom:8px;"></div>
      </div>
    </div>
  </div>

<nav class="navbar navbar-expand-lg navbar-dark app-navbar">
  <div class="container-fluid d-flex justify-content-between align-items-center">
    <a class="navbar-brand d-flex align-items-center" href="index.php">
      <img src="<?= LOGO_FILE ?>" alt="COMPASS Logo" class="navbar-logo me-2">
      <span class="brand-text"><?= APP_NAME ?></span>
    </a>
    <button class="hamburger-btn d-md-none" id="hamburgerBtn" title="Toggle Sidebar">
      <i data-lucide="menu"></i>
    </button>
    <div class="navbar-actions d-flex align-items-center">
      <span class="welcome-text me-3 d-none d-md-block">
        <i data-lucide="user-circle" class="icon-sm me-1"></i>
        <?= h(($_SESSION['display_name'] ?? '') ?: ($_SESSION['username'] ?? '')) ?>
        <span class="badge role-badge"><?= h($_SESSION['role']) ?></span>
      </span>
      <a href="logout.php" class="logout-btn" title="Logout">
        <i data-lucide="log-out" class="icon-sm me-1"></i>
        <span>Logout</span>
      </a>
    </div>
  </div>
</nav>

<div class="app-body">
  <aside class="sidebar" id="appSidebar" role="navigation" aria-label="Main menu">
    <nav class="nav flex-column">
      <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">
        <i data-lucide="layout-dashboard"></i><span>Dashboard</span>
      </a>
      <a class="nav-link <?= in_array($currentPage, $assetPages) ? 'active' : '' ?>" href="assets.php">
        <i data-lucide="hard-drive"></i><span>Assets</span>
      </a>
      <a class="nav-link <?= $currentPage === 'qr_stickers.php' ? 'active' : '' ?>" href="qr_stickers.php">
        <i class="bi bi-qr-code"></i><span>QR Stickers</span>
      </a>
      <a class="nav-link <?= in_array($currentPage, $userPages) ? 'active' : '' ?>" href="users.php">
        <i data-lucide="users"></i><span>Users</span>
      </a>
      <?php if (is_admin()): ?>
      <div class="sidebar-section">Administration</div>
      <a class="nav-link <?= $currentPage === 'maintenance.php' ? 'active' : '' ?>" href="maintenance.php">
        <i data-lucide="wrench"></i><span>Maintenance</span>
      </a>
      <a class="nav-link <?= $currentPage === 'audit.php' ? 'active' : '' ?>" href="audit.php">
        <i data-lucide="scroll-text"></i><span>Audit Log</span>
      </a>
      <a class="nav-link <?= $currentPage === 'settings.php' ? 'active' : '' ?>" href="settings.php">
        <i data-lucide="settings"></i><span>Settings</span>
      </a>
      <a class="nav-link <?= $currentPage === 'backup.php' ? 'active' : '' ?>" href="backup.php">
        <i data-lucide="database-backup"></i><span>Backup</span>
      </a>
      <?php endif; ?>
      <a class="nav-link <?= $currentPage === 'change_password.php' ? 'active' : '' ?>" href="change_password.php">
        <i data-lucide="key-round"></i><span>Change Password</span>
      </a>
      <div class="sidebar-footer mt-auto">
        <small class="text-muted">COMPASS Maritime Training Center</small>
      </div>
    </nav>
  </aside>
  <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

  <main class="main-content">
