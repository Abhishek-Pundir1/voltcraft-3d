<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$pdo = get_db_connection();

// Toggle customer active/suspended
if (isset($_GET['toggle_status'])) {
    $u_id = intval($_GET['toggle_status']);
    $pdo->prepare("UPDATE users SET status = IF(status='active', 'suspended', 'active') WHERE id = ? AND role = 'customer'")->execute([$u_id]);
    set_flash('info', 'Customer account status updated.');
    header("Location: " . BASE_URL . "admin/customers.php");
    exit;
}

$stmt = $pdo->query("SELECT c.*, u.id as user_id, u.full_name, u.email, u.phone, u.status as user_status, u.created_at as registered_at,
                            COUNT(b.id) as total_bookings, IFNULL(SUM(p.amount), 0) as total_spent 
                     FROM customers c 
                     JOIN users u ON c.user_id = u.id 
                     LEFT JOIN bookings b ON c.id = b.customer_id 
                     LEFT JOIN payments p ON c.id = p.customer_id AND p.payment_status = 'SUCCESS' 
                     GROUP BY c.id 
                     ORDER BY u.created_at DESC");
$customers = $stmt->fetchAll();

$page_title = 'Manage Customers — ' . APP_NAME;
$active_nav = 'customers';
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
        <h2>Customer Accounts Directory</h2>
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
          <h3 style="font-size:1.2rem;">Registered Customers (<?= count($customers) ?>)</h3>
        </div>

        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Customer Name</th>
                <th>Contact Phone</th>
                <th>Service Address</th>
                <th>City</th>
                <th>Bookings Count</th>
                <th>Total Spent</th>
                <th>Account Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($customers as $c): ?>
                <tr>
                  <td>
                    <div><strong><?= e($c['full_name']) ?></strong></div>
                    <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($c['email']) ?></div>
                  </td>
                  <td><?= e($c['phone'] ?: 'N/A') ?></td>
                  <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?= e($c['address'] ?: 'Not provided') ?>
                  </td>
                  <td><?= e($c['city'] ?: 'N/A') ?></td>
                  <td><strong><?= $c['total_bookings'] ?></strong></td>
                  <td class="mono" style="font-weight:700;color:var(--primary);"><?= format_currency($c['total_spent']) ?></td>
                  <td>
                    <span class="badge badge-<?= $c['user_status'] === 'active' ? 'primary' : 'danger' ?>">
                      <?= ucfirst($c['user_status']) ?>
                    </span>
                  </td>
                  <td>
                    <a href="<?= BASE_URL ?>admin/customers.php?toggle_status=<?= $c['user_id'] ?>" class="btn btn-outline btn-sm" style="color:var(--danger);">
                      <?= $c['user_status'] === 'active' ? 'Suspend' : 'Activate' ?>
                    </a>
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
