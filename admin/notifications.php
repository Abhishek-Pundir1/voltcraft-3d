<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$user = current_user();
$pdo = get_db_connection();

if (isset($_GET['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user['id']]);
    set_flash('success', 'All notifications marked as read.');
    header("Location: " . BASE_URL . "admin/notifications.php");
    exit;
}

if (isset($_GET['read_id'])) {
    $read_id = intval($_GET['read_id']);
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$read_id, $user['id']]);
    header("Location: " . BASE_URL . "admin/notifications.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$page_title = 'Admin Notifications & Alerts — ' . APP_NAME;
$active_nav = 'notifications';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<div class="dashboard-wrapper">
  <?php require_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

  <main class="dashboard-main">
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn"><i data-lucide="menu"></i></button>
        <h2>Administrator Notification Center</h2>
      </div>
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>admin/notifications.php?mark_all_read=1" class="btn btn-outline btn-sm">
          <i data-lucide="check-check"></i> Mark All Read
        </a>
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content" style="max-width:900px;">
      <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><i data-lucide="info"></i> <?= e($flash['message']) ?></div>
      <?php endif; ?>

      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.15rem;display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="bell" style="color:var(--primary);"></i>
            System &amp; Verification Alerts (<?= count($notifications) ?>)
          </h3>
          <span style="font-size:0.85rem;color:var(--text-dim);">
            <?= get_unread_notification_count($user['id']) ?> unread
          </span>
        </div>

        <?php if (empty($notifications)): ?>
          <div style="text-align:center;padding:4rem 1rem;">
            <div style="font-size:3rem;margin-bottom:1rem;">🔕</div>
            <h4>No Notifications Yet</h4>
            <p style="color:var(--text-dim);font-size:0.9rem;margin-top:0.35rem;">
              When engineers complete repairs or OTP verification events occur, you will receive real-time alerts here.
            </p>
          </div>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:0.75rem;">
            <?php foreach ($notifications as $n): ?>
              <?php
                $is_otp = strpos($n['type'], 'otp') !== false || strpos($n['type'], 'repair') !== false || strpos($n['type'], 'job') !== false;
                $bg = $n['is_read'] ? 'rgba(255,255,255,0.02)' : 'rgba(0,240,255,0.06)';
                $border = $n['is_read'] ? 'var(--border-glass)' : 'rgba(0,240,255,0.25)';
              ?>
              <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:1.1rem;background:<?= $bg ?>;border:1px solid <?= $border ?>;border-radius:var(--radius-sm);gap:1rem;">
                <div style="display:flex;align-items:flex-start;gap:0.85rem;">
                  <div style="width:36px;height:36px;border-radius:50%;background:<?= $n['is_read'] ? 'rgba(255,255,255,0.05)' : 'rgba(0,240,255,0.15)' ?>;display:flex;align-items:center;justify-content:center;color:var(--primary);flex-shrink:0;">
                    <i data-lucide="<?= $is_otp ? 'shield-alert' : 'bell' ?>" style="width:18px;height:18px;"></i>
                  </div>
                  <div>
                    <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
                      <h4 style="font-size:0.95rem;font-weight:700;color:var(--text-main);"><?= e($n['title']) ?></h4>
                      <?php if (!$n['is_read']): ?>
                        <span class="badge badge-primary" style="font-size:0.65rem;">NEW</span>
                      <?php endif; ?>
                    </div>
                    <p style="font-size:0.88rem;color:var(--text-muted);margin-top:0.3rem;line-height:1.5;">
                      <?= e($n['message']) ?>
                    </p>
                    <div style="font-size:0.75rem;color:var(--text-dim);margin-top:0.4rem;">
                      <i data-lucide="clock" style="width:12px;height:12px;vertical-align:middle;"></i> <?= time_ago($n['created_at']) ?> &bull; <?= format_date($n['created_at']) ?>
                    </div>
                  </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:0.4rem;align-items:flex-end;flex-shrink:0;">
                  <?php if ($n['link']): ?>
                    <a href="<?= BASE_URL . ltrim($n['link'], '/') ?>" class="btn btn-primary btn-sm">
                      View Action
                    </a>
                  <?php endif; ?>
                  <?php if (!$n['is_read']): ?>
                    <a href="<?= BASE_URL ?>admin/notifications.php?read_id=<?= $n['id'] ?>" class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.2rem 0.5rem;">
                      Mark Read
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </main>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script>
  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
