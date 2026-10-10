<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$pdo = get_db_connection();

$stmt = $pdo->query("SELECT * FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();

$page_title = 'All Platform Users — ' . APP_NAME;
$active_nav = 'users';
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
        <h2>All Platform Users (<?= count($users) ?>)</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <div class="glass-card">
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email Address</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created At</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $u): ?>
                <tr>
                  <td><strong>#<?= $u['id'] ?></strong></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                      <img src="<?= get_avatar_url($u['avatar'], $u['full_name']) ?>" alt="Avatar" style="width:34px;height:34px;border-radius:50%;">
                      <strong><?= e($u['full_name']) ?></strong>
                    </div>
                  </td>
                  <td><?= e($u['email']) ?></td>
                  <td><?= e($u['phone'] ?: 'N/A') ?></td>
                  <td>
                    <span class="badge badge-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'engineer' ? 'secondary' : 'primary') ?>">
                      <?= strtoupper(e($u['role'])) ?>
                    </span>
                  </td>
                  <td>
                    <span class="badge badge-<?= $u['status'] === 'active' ? 'success' : 'danger' ?>">
                      <?= ucfirst(e($u['status'])) ?>
                    </span>
                  </td>
                  <td><?= format_date($u['created_at']) ?></td>
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
