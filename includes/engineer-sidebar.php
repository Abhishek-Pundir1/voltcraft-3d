<?php
// Engineer Console Sidebar Component
$active_nav = $active_nav ?? 'dashboard';
$user = current_user();
$unread_count = get_unread_notification_count($user['id']);

// Fetch engineer status
$pdo = get_db_connection();
$eng_stmt = $pdo->prepare("SELECT availability_status, is_verified, rating FROM engineers WHERE user_id = ?");
$eng_stmt->execute([$user['id']]);
$engineer_data = $eng_stmt->fetch() ?: ['availability_status' => 'available', 'is_verified' => 1, 'rating' => 5.0];
?>
<aside class="dashboard-sidebar" id="engineerSidebar">
  <div class="sidebar-header">
    <a href="<?= BASE_URL ?>index.php" class="navbar-brand" style="font-size:1.25rem;">
      <div class="brand-icon" style="width:30px;height:30px;">
        <i data-lucide="zap" style="width:16px;height:16px;"></i>
      </div>
      <span>Volt<span class="gradient-text">Craft</span></span>
    </a>
    <span class="sidebar-role-badge badge badge-secondary">Engineer</span>
  </div>

  <div class="sidebar-user">
    <img src="<?= get_avatar_url($user['avatar'], $user['full_name']) ?>" alt="Avatar" class="sidebar-avatar">
    <div class="sidebar-user-info">
      <h4><?= e($user['full_name']) ?></h4>
      <div style="display:flex;align-items:center;gap:0.4rem;margin-top:0.2rem;">
        <span class="badge badge-<?= $engineer_data['availability_status'] === 'available' ? 'success' : 'warning' ?>" style="font-size:0.65rem;padding:1px 6px;">
          <?= ucfirst(e($engineer_data['availability_status'])) ?>
        </span>
        <span style="font-size:0.75rem;color:var(--warning);font-weight:700;">★ <?= number_format($engineer_data['rating'], 1) ?></span>
      </div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Operations</div>
    <a href="<?= BASE_URL ?>engineer/dashboard.php" class="sidebar-link <?= $active_nav === 'dashboard' ? 'active' : '' ?>">
      <i data-lucide="activity"></i> <span>Console</span>
    </a>
    <a href="<?= BASE_URL ?>engineer/requests.php" class="sidebar-link <?= $active_nav === 'requests' ? 'active' : '' ?>">
      <i data-lucide="inbox"></i> <span>Incoming Requests</span>
    </a>
    <a href="<?= BASE_URL ?>engineer/bookings.php" class="sidebar-link <?= $active_nav === 'bookings' ? 'active' : '' ?>">
      <i data-lucide="wrench"></i> <span>Active Bookings</span>
    </a>

    <div class="nav-section-label" style="margin-top:1rem;">Financials & Profile</div>
    <a href="<?= BASE_URL ?>engineer/earnings.php" class="sidebar-link <?= $active_nav === 'earnings' ? 'active' : '' ?>">
      <i data-lucide="trending-up"></i> <span>Earnings & Payouts</span>
    </a>
    <a href="<?= BASE_URL ?>engineer/payments.php" class="sidebar-link <?= $active_nav === 'payments' ? 'active' : '' ?>">
      <i data-lucide="shield-check"></i> <span>Advance Payments Log</span>
    </a>
    <a href="<?= BASE_URL ?>engineer/notifications.php" class="sidebar-link <?= $active_nav === 'notifications' ? 'active' : '' ?>">
      <i data-lucide="bell"></i> <span>Notifications</span>
      <?php if ($unread_count > 0): ?>
        <span class="sidebar-badge"><?= $unread_count ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>engineer/profile.php" class="sidebar-link <?= $active_nav === 'profile' ? 'active' : '' ?>">
      <i data-lucide="user-check"></i> <span>Profile & UPI QR</span>
    </a>
  </nav>

  <div class="sidebar-footer">
    <a href="<?= BASE_URL ?>logout.php" class="sidebar-link" style="color:var(--danger);">
      <i data-lucide="log-out"></i> <span>Sign Out</span>
    </a>
  </div>
</aside>
