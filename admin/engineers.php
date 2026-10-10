<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$pdo = get_db_connection();

// Toggle verification status
if (isset($_GET['toggle_verify'])) {
    $e_id = intval($_GET['toggle_verify']);
    $pdo->prepare("UPDATE engineers SET is_verified = IF(is_verified=1, 0, 1) WHERE id = ?")->execute([$e_id]);
    set_flash('success', 'Engineer verification status updated.');
    header("Location: " . BASE_URL . "admin/engineers.php");
    exit;
}

// Toggle active/suspended
if (isset($_GET['toggle_status'])) {
    $u_id = intval($_GET['toggle_status']);
    $pdo->prepare("UPDATE users SET status = IF(status='active', 'suspended', 'active') WHERE id = ? AND role = 'engineer'")->execute([$u_id]);
    set_flash('info', 'Engineer account access status updated.');
    header("Location: " . BASE_URL . "admin/engineers.php");
    exit;
}

$stmt = $pdo->query("SELECT e.*, u.id as user_id, u.full_name, u.email, u.phone, u.status as user_status,
                            COUNT(b.id) as total_jobs, IFNULL(SUM(p.amount), 0) as total_cleared 
                     FROM engineers e 
                     JOIN users u ON e.user_id = u.id 
                     LEFT JOIN bookings b ON e.id = b.engineer_id 
                     LEFT JOIN payments p ON e.id = p.engineer_id AND p.payment_status = 'SUCCESS' 
                     GROUP BY e.id 
                     ORDER BY e.created_at DESC");
$engineers = $stmt->fetchAll();

$page_title = 'Manage Engineers — ' . APP_NAME;
$active_nav = 'engineers';
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
        <h2>Engineer Verification &amp; Account Control</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><i data-lucide="info"></i> <?= e($flash['message']) ?></div>
      <?php endif; ?>

      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.2rem;">Registered Electronics Engineers (<?= count($engineers) ?>)</h3>
        </div>

        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Engineer Name</th>
                <th>Specialization &amp; City</th>
                <th>Rate / Hr</th>
                <th>Rating</th>
                <th>Jobs Done</th>
                <th>Verified Badge</th>
                <th>Account Status</th>
                <th>Admin Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($engineers as $e): ?>
                <tr>
                  <td>
                    <div><strong><?= e($e['full_name']) ?></strong></div>
                    <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($e['email']) ?> &bull; <?= e($e['phone']) ?></div>
                  </td>
                  <td>
                    <div style="font-weight:600;"><?= e($e['title']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($e['location']) ?></div>
                  </td>
                  <td class="mono"><?= format_currency($e['hourly_rate']) ?></td>
                  <td><span class="badge badge-warning">★ <?= number_format($e['rating'], 2) ?></span></td>
                  <td><strong><?= $e['total_jobs'] ?></strong></td>
                  <td>
                    <span class="badge badge-<?= $e['is_verified'] ? 'success' : 'muted' ?>">
                      <?= $e['is_verified'] ? 'IPC Verified' : 'Unverified' ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge badge-<?= $e['user_status'] === 'active' ? 'primary' : 'danger' ?>">
                      <?= ucfirst($e['user_status']) ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex;gap:0.4rem;">
                      <a href="<?= BASE_URL ?>admin/engineers.php?toggle_verify=<?= $e['id'] ?>" class="btn btn-outline btn-sm" title="Toggle Verification">
                        <?= $e['is_verified'] ? 'Revoke' : 'Approve' ?>
                      </a>
                      <a href="<?= BASE_URL ?>admin/engineers.php?toggle_status=<?= $e['user_id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);" title="Toggle Suspend">
                        <?= $e['user_status'] === 'active' ? 'Suspend' : 'Activate' ?>
                      </a>
                      <a href="<?= BASE_URL ?>engineer-detail.php?id=<?= $e['id'] ?>" target="_blank" class="btn btn-outline btn-sm">
                        Profile
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
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
