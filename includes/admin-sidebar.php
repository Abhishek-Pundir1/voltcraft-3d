<?php
// Admin Console Sidebar Component
$active_nav = $active_nav ?? 'dashboard';
$user = current_user();
?>
<aside class="dashboard-sidebar" id="adminSidebar">
  <div class="sidebar-header">
    <a href="<?= BASE_URL ?>index.php" class="navbar-brand" style="font-size:1.25rem;">
      <div class="brand-icon" style="width:30px;height:30px;background:linear-gradient(135deg, #ef4444 0%, #f59e0b 100%);">
        <i data-lucide="shield" style="width:16px;height:16px;"></i>
      </div>
      <span>Admin<span style="color:#ef4444;">Ops</span></span>
    </a>
    <span class="sidebar-role-badge badge badge-danger">Root</span>
  </div>

  <div class="sidebar-user">
    <img src="<?= get_avatar_url($user['avatar'], $user['full_name']) ?>" alt="Avatar" class="sidebar-avatar" style="border-color:#ef4444;">
    <div class="sidebar-user-info">
      <h4><?= e($user['full_name']) ?></h4>
      <p style="color:#ef4444;">Super Administrator</p>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Platform Core</div>
    <a href="<?= BASE_URL ?>admin/dashboard.php" class="sidebar-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>">
      <i data-lucide="layout-dashboard"></i> <span>Overview</span>
    </a>
    <a href="<?= BASE_URL ?>admin/engineers.php" class="sidebar-link <?= $active_nav === 'engineers' ? 'active' : '' ?>">
      <i data-lucide="hard-hat"></i> <span>Manage Engineers</span>
    </a>
    <a href="<?= BASE_URL ?>admin/customers.php" class="sidebar-link <?= $active_nav === 'customers' ? 'active' : '' ?>">
      <i data-lucide="users"></i> <span>Manage Customers</span>
    </a>
    <a href="<?= BASE_URL ?>admin/services.php" class="sidebar-link <?= $active_nav === 'services' ? 'active' : '' ?>">
      <i data-lucide="cpu"></i> <span>Service Catalog</span>
    </a>
    <a href="<?= BASE_URL ?>admin/bookings.php" class="sidebar-link <?= $active_nav === 'bookings' ? 'active' : '' ?>">
      <i data-lucide="calendar"></i> <span>All Bookings</span>
    </a>
    <?php
      $sb_pdo = get_db_connection();
      $sb_awaiting_otp = (int)$sb_pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'REPAIR_COMPLETED_AWAITING_ADMIN'")->fetchColumn();
      $sb_unread_admin_notifs = get_unread_notification_count($user['id']);
    ?>
    <a href="<?= BASE_URL ?>admin/otp-verification.php" class="sidebar-link <?= $active_nav === 'otp_verification' ? 'active' : '' ?>">
      <i data-lucide="key-round"></i> <span>OTP Verification</span>
      <?php if ($sb_awaiting_otp > 0): ?>
        <span class="badge badge-warning" style="margin-left:auto;font-size:0.68rem;padding:2px 6px;"><?= $sb_awaiting_otp ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>admin/notifications.php" class="sidebar-link <?= $active_nav === 'notifications' ? 'active' : '' ?>">
      <i data-lucide="bell"></i> <span>Notifications</span>
      <?php if ($sb_unread_admin_notifs > 0): ?>
        <span class="badge badge-danger" style="margin-left:auto;font-size:0.68rem;padding:2px 6px;"><?= $sb_unread_admin_notifs ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>admin/properties.php" class="sidebar-link <?= $active_nav === 'properties' ? 'active' : '' ?>">
      <i data-lucide="home"></i> <span>Manage Properties</span>
    </a>

    <div class="nav-section-label" style="margin-top:1rem;">Audit & Intelligence</div>
    <a href="<?= BASE_URL ?>admin/payments.php" class="sidebar-link <?= $active_nav === 'payments' ? 'active' : '' ?>">
      <i data-lucide="dollar-sign"></i> <span>Payment Audits</span>
    </a>
    <a href="<?= BASE_URL ?>admin/complaints.php" class="sidebar-link <?= $active_nav === 'complaints' ? 'active' : '' ?>">
      <i data-lucide="message-square"></i> <span>Complaints & Tickets</span>
    </a>
    <a href="<?= BASE_URL ?>admin/reports.php" class="sidebar-link <?= $active_nav === 'reports' ? 'active' : '' ?>">
      <i data-lucide="bar-chart-2"></i> <span>Reports & Analytics</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>logout.php" class="sidebar-link" style="color:var(--danger);">
      <i data-lucide="log-out"></i> <span>Sign Out</span>
    </a>
  </div>
</aside>
