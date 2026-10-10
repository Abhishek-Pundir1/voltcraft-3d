<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('engineer');
$user = current_user();
$pdo = get_db_connection();

if (isset($_GET['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user['id']]);
    header("Location: " . BASE_URL . "engineer/notifications.php");
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$notifications = $stmt->fetchAll();

$page_title = 'Engineer Notifications — ' . APP_NAME;
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
  <?php require_once __DIR__ . '/../includes/engineer-sidebar.php'; ?>

  <main class="dashboard-main">
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn"><i data-lucide="menu"></i></button>
        <h2>Engineer Notifications</h2>
      </div>
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>engineer/notifications.php?mark_all_read=1" class="btn btn-outline btn-sm">
          <i data-lucide="check-check"></i> Mark All Read
        </a>
      </div>
    </header>

    <div class="dashboard-content" style="max-width:850px;">
      <div class="glass-card">
        <?php if (empty($notifications)): ?>
          <div style="text-align:center;padding:3rem 1rem;">
            <p style="color:var(--text-dim);">No notifications received.</p>
          </div>
        <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:0.75rem;">
            <?php foreach ($notifications as $n): ?>
              <div style="display:flex;align-items:flex-start;justify-content:space-between;padding:1.1rem;background:<?= $n['is_read'] ? 'rgba(255,255,255,0.02)' : 'rgba(0,240,255,0.06)' ?>;border:1px solid <?= $n['is_read'] ? 'var(--border-glass)' : 'rgba(0,240,255,0.25)' ?>;border-radius:var(--radius-sm);gap:1rem;">
                <div style="display:flex;gap:1rem;align-items:flex-start;">
                  <div style="width:36px;height:36px;border-radius:50%;background:rgba(0,240,255,0.1);color:var(--primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i data-lucide="bell" style="width:16px;height:16px;"></i>
                  </div>
                  <div>
                    <h4 style="font-size:0.95rem;margin-bottom:0.25rem;color:var(--text-main);"><?= e($n['title']) ?></h4>
                    <p style="font-size:0.85rem;color:var(--text-muted);"><?= e($n['message']) ?></p>
                    <div style="font-size:0.75rem;color:var(--text-dim);margin-top:0.4rem;">
                      <i data-lucide="clock" style="width:12px;height:12px;vertical-align:middle;"></i> <?= time_ago($n['created_at']) ?>
                    </div>
                  </div>
                </div>

                <?php if (!empty($n['link'])): ?>
                  <a href="<?= BASE_URL . ltrim($n['link'], '/') ?>" class="btn btn-outline btn-sm" style="flex-shrink:0;">
                    View &rsaquo;
                  </a>
                <?php endif; ?>
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
