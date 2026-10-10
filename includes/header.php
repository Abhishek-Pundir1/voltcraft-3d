<?php
// ============================================================================
// VoltCraft 3D - Global Public Header & Navigation
// ============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

$page_title = $page_title ?? (APP_NAME . ' — Next-Gen 3D Electronics Engineering');
$page_desc = $page_desc ?? 'Book certified hardware, robotics, PCB, and electronics engineers with 3D diagnostics and secure advance escrow.';
$current_page = basename($_SERVER['PHP_SELF']);
$logged_user = current_user();
$unread_notifs = $logged_user ? get_unread_notification_count($logged_user['id']) : 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <meta name="description" content="<?= e($page_desc) ?>">
  <meta name="keywords" content="electronic repair, hardware engineer, PCB soldering, industrial automation, solar inverter repair, 3D booking">
  
  <!-- Favicon / Branding -->
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚡</text></svg>">

  <!-- Core & 3D Stylesheets -->
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/three-hero.css">
  
  <!-- Feather / Lucide Web Icons via unpkg -->
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

  <!-- Navigation Bar -->
  <nav class="navbar" id="mainNavbar">
    <div class="container nav-container">
      <a href="<?= BASE_URL ?>index.php" class="navbar-brand">
        <div class="brand-icon">
          <i data-lucide="zap" style="width:20px;height:20px;stroke-width:2.5;"></i>
        </div>
        <span>Volt<span class="gradient-text">Craft</span> <span style="font-size:0.75rem;padding:2px 6px;border-radius:4px;background:rgba(0,240,255,0.15);color:var(--primary);vertical-align:middle;">3D</span></span>
      </a>

      <!-- Desktop & Mobile Navigation Links -->
      <ul class="nav-links">
        <li><a href="<?= BASE_URL ?>index.php" class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>">Home</a></li>
        <li><a href="<?= BASE_URL ?>properties.php" class="nav-link <?= $current_page === 'properties.php' ? 'active' : '' ?>">Properties</a></li>
        <li><a href="<?= BASE_URL ?>diagnosis.php" class="nav-link <?= $current_page === 'diagnosis.php' ? 'active' : '' ?>">Smart Diagnosis</a></li>
        <li><a href="<?= BASE_URL ?>services.php" class="nav-link <?= $current_page === 'services.php' ? 'active' : '' ?>">Services</a></li>
        <li><a href="<?= BASE_URL ?>engineers.php" class="nav-link <?= $current_page === 'engineers.php' ? 'active' : '' ?>">Engineers</a></li>
        <li><a href="<?= BASE_URL ?>how-it-works.php" class="nav-link <?= $current_page === 'how-it-works.php' ? 'active' : '' ?>">How It Works</a></li>
        <li><a href="<?= BASE_URL ?>about.php" class="nav-link <?= $current_page === 'about.php' ? 'active' : '' ?>">About</a></li>
        <li><a href="<?= BASE_URL ?>faq.php" class="nav-link <?= $current_page === 'faq.php' ? 'active' : '' ?>">FAQ</a></li>
        <li><a href="<?= BASE_URL ?>contact.php" class="nav-link <?= $current_page === 'contact.php' ? 'active' : '' ?>">Contact</a></li>
      </ul>

      <!-- Navbar Right Actions -->
      <div class="nav-actions">
        <!-- Dark / Light Theme Toggle -->
        <button type="button" class="theme-toggle-btn" title="Toggle Light/Dark Theme" aria-label="Toggle Theme">
          <i data-lucide="sun" class="sun-icon" style="width:18px;height:18px;"></i>
        </button>

        <?php if ($logged_user): ?>
          <!-- Logged In User Dropdown -->
          <div class="user-menu-wrapper">
            <button type="button" class="user-profile-btn">
              <img src="<?= get_avatar_url($logged_user['avatar'], $logged_user['full_name']) ?>" alt="Avatar" class="user-avatar-sm">
              <span style="font-size:0.88rem;font-weight:600;max-width:110px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= e(explode(' ', $logged_user['full_name'])[0]) ?>
              </span>
              <?php if ($unread_notifs > 0): ?>
                <span style="background:var(--danger);color:#fff;font-size:0.7rem;padding:2px 6px;border-radius:10px;font-weight:bold;"><?= $unread_notifs ?></span>
              <?php endif; ?>
              <i data-lucide="chevron-down" style="width:14px;height:14px;color:var(--text-dim);"></i>
            </button>

            <div class="user-dropdown">
              <div style="padding:0.6rem 0.9rem;border-bottom:1px solid var(--border-glass);">
                <div style="font-size:0.88rem;font-weight:700;color:var(--text-main);"><?= e($logged_user['full_name']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($logged_user['email']) ?></div>
                <div style="margin-top:0.35rem;">
                  <span class="badge badge-<?= $logged_user['role'] === 'admin' ? 'danger' : ($logged_user['role'] === 'engineer' ? 'secondary' : 'primary') ?>">
                    <?= strtoupper(e($logged_user['role'])) ?>
                  </span>
                </div>
              </div>

              <?php if ($logged_user['role'] === 'customer'): ?>
                <a href="<?= BASE_URL ?>customer/dashboard.php" class="dropdown-item">
                  <i data-lucide="layout-dashboard" style="width:16px;height:16px;"></i> Customer Portal
                </a>
                <a href="<?= BASE_URL ?>customer/properties.php" class="dropdown-item">
                  <i data-lucide="home" style="width:16px;height:16px;"></i> Customer Properties (C2C)
                </a>
                <a href="<?= BASE_URL ?>customer/requests.php" class="dropdown-item">
                  <i data-lucide="clock" style="width:16px;height:16px;"></i> My Service Requests
                </a>
                <a href="<?= BASE_URL ?>customer/payments.php" class="dropdown-item">
                  <i data-lucide="credit-card" style="width:16px;height:16px;"></i> Payment History
                </a>
                <a href="<?= BASE_URL ?>customer/profile.php" class="dropdown-item">
                  <i data-lucide="user" style="width:16px;height:16px;"></i> Profile Settings
                </a>
              <?php elseif ($logged_user['role'] === 'engineer'): ?>
                <a href="<?= BASE_URL ?>engineer/dashboard.php" class="dropdown-item">
                  <i data-lucide="layout-dashboard" style="width:16px;height:16px;"></i> Engineer Console
                </a>
                <a href="<?= BASE_URL ?>engineer/requests.php" class="dropdown-item">
                  <i data-lucide="inbox" style="width:16px;height:16px;"></i> Incoming Requests
                </a>
                <a href="<?= BASE_URL ?>engineer/earnings.php" class="dropdown-item">
                  <i data-lucide="dollar-sign" style="width:16px;height:16px;"></i> Earnings & UPI
                </a>
                <a href="<?= BASE_URL ?>engineer/profile.php" class="dropdown-item">
                  <i data-lucide="user-check" style="width:16px;height:16px;"></i> Edit Engineer Profile
                </a>
              <?php elseif ($logged_user['role'] === 'admin'): ?>
                <a href="<?= BASE_URL ?>admin/dashboard.php" class="dropdown-item">
                  <i data-lucide="shield" style="width:16px;height:16px;"></i> Admin Control Center
                </a>
                <a href="<?= BASE_URL ?>admin/properties.php" class="dropdown-item">
                  <i data-lucide="home" style="width:16px;height:16px;"></i> Manage Properties
                </a>
                <a href="<?= BASE_URL ?>admin/engineers.php" class="dropdown-item">
                  <i data-lucide="users" style="width:16px;height:16px;"></i> Manage Engineers
                </a>
                <a href="<?= BASE_URL ?>admin/bookings.php" class="dropdown-item">
                  <i data-lucide="check-square" style="width:16px;height:16px;"></i> Platform Bookings
                </a>
              <?php endif; ?>

              <div class="dropdown-divider"></div>
              <a href="<?= BASE_URL ?>logout.php" class="dropdown-item" style="color:var(--danger);">
                <i data-lucide="log-out" style="width:16px;height:16px;"></i> Logout
              </a>
            </div>
          </div>
        <?php else: ?>
          <!-- Guest Actions -->
          <a href="<?= BASE_URL ?>login.php" class="btn btn-outline btn-sm">Sign In</a>
          <a href="<?= BASE_URL ?>register.php" class="btn btn-primary btn-sm">Register</a>
        <?php endif; ?>

        <!-- Mobile Nav Toggle -->
        <button type="button" class="mobile-menu-btn" aria-label="Toggle Menu">
          <i data-lucide="menu"></i>
        </button>
      </div>
    </div>
  </nav>

  <!-- Flash Messages Banner -->
  <?php $flash = get_flash(); if ($flash): ?>
    <div class="container" style="margin-top:1.5rem;">
      <div class="alert alert-<?= e($flash['type']) ?>">
        <div style="display:flex;align-items:center;gap:0.75rem;">
          <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'danger' ? 'alert-triangle' : 'info') ?>" style="width:20px;height:20px;"></i>
          <span><?= e($flash['message']) ?></span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;font-size:1.2rem;">&times;</button>
      </div>
    </div>
  <?php endif; ?>
