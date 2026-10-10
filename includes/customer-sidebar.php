<?php
// Customer Dashboard Sidebar & Topbar Component
$active_nav = $active_nav ?? 'dashboard';
$user = current_user();
$unread_count = get_unread_notification_count($user['id']);
?>
<aside class="dashboard-sidebar" id="customerSidebar">
  <div class="sidebar-header">
    <a href="<?= BASE_URL ?>index.php" class="navbar-brand" style="font-size:1.25rem;">
      <div class="brand-icon" style="width:30px;height:30px;">
        <i data-lucide="zap" style="width:16px;height:16px;"></i>
      </div>
      <span>Volt<span class="gradient-text">Craft</span></span>
    </a>
    <span class="sidebar-role-badge badge badge-primary">Customer</span>
  </div>

  <div class="sidebar-user">
    <img src="<?= get_avatar_url($user['avatar'], $user['full_name']) ?>" alt="Avatar" class="sidebar-avatar">
    <div class="sidebar-user-info">
      <h4><?= e($user['full_name']) ?></h4>
      <p><?= e($user['email']) ?></p>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main Menu</div>
    <a href="<?= BASE_URL ?>customer/dashboard.php" class="sidebar-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>">
      <i data-lucide="layout-dashboard"></i> <span>Dashboard</span>
    </a>
    <a href="<?= BASE_URL ?>customer/new-request.php" class="sidebar-link <?= $active_nav === 'new_request' ? 'active' : '' ?>">
      <i data-lucide="plus-circle"></i> <span>Book Engineer</span>
    </a>
    <a href="<?= BASE_URL ?>diagnosis.php" class="sidebar-link <?= $active_nav === 'diagnosis' ? 'active' : '' ?>">
      <i data-lucide="cpu"></i> <span>Smart Diagnosis</span>
    </a>
    <a href="<?= BASE_URL ?>customer/requests.php" class="sidebar-link <?= $active_nav === 'requests' ? 'active' : '' ?>">
      <i data-lucide="clock"></i> <span>My Requests</span>
    </a>
    <a href="<?= BASE_URL ?>customer/engineers.php" class="sidebar-link <?= $active_nav === 'engineers' ? 'active' : '' ?>">
      <i data-lucide="users"></i> <span>Browse Engineers</span>
    </a>
    <a href="<?= BASE_URL ?>customer/properties.php" class="sidebar-link <?= $active_nav === 'properties' ? 'active' : '' ?>">
      <i data-lucide="home"></i> <span>Properties (C2C)</span>
    </a>

    <div class="nav-section-label" style="margin-top:1rem;">Billing & Account</div>
    <a href="<?= BASE_URL ?>customer/payments.php" class="sidebar-link <?= $active_nav === 'payments' ? 'active' : '' ?>">
      <i data-lucide="credit-card"></i> <span>Payments & Receipts</span>
    </a>
    <a href="<?= BASE_URL ?>customer/notifications.php" class="sidebar-link <?= $active_nav === 'notifications' ? 'active' : '' ?>">
      <i data-lucide="bell"></i> <span>Notifications</span>
      <?php if ($unread_count > 0): ?>
        <span class="sidebar-badge"><?= $unread_count ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>customer/profile.php" class="sidebar-link <?= $active_nav === 'profile' ? 'active' : '' ?>">
      <i data-lucide="settings"></i> <span>Profile & Address</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>logout.php" class="sidebar-link" style="color:var(--danger);">
      <i data-lucide="log-out"></i> <span>Sign Out</span>
    </a>
  </div>
</aside>
