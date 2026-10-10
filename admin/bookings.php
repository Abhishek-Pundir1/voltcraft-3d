<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$pdo = get_db_connection();

// Status override
if (isset($_POST['action']) && $_POST['action'] === 'override_status') {
    $b_id = intval($_POST['booking_id']);
    $new_status = clean_input($_POST['status']);
    $pdo->prepare("UPDATE bookings SET booking_status = ? WHERE id = ?")->execute([$new_status, $b_id]);
    set_flash('success', "Booking #{$b_id} status updated to {$new_status}");
    header("Location: " . BASE_URL . "admin/bookings.php");
    exit;
}

$status_filter = clean_input($_GET['status'] ?? '');
$sql = "SELECT b.*, sr.request_code, sr.title as problem_title, s.name as service_name, 
               cust_u.full_name as customer_name, eng_u.full_name as engineer_name 
        FROM bookings b 
        JOIN service_requests sr ON b.request_id = sr.id 
        JOIN services s ON b.service_id = s.id 
        JOIN customers c ON b.customer_id = c.id 
        JOIN users cust_u ON c.user_id = cust_u.id 
        JOIN engineers e ON b.engineer_id = e.id 
        JOIN users eng_u ON e.user_id = eng_u.id";

if (!empty($status_filter)) {
    $sql .= " WHERE b.booking_status = ?";
    $stmt = $pdo->prepare($sql . " ORDER BY b.created_at DESC");
    $stmt->execute([$status_filter]);
} else {
    $stmt = $pdo->query($sql . " ORDER BY b.created_at DESC");
}
$bookings = $stmt->fetchAll();

$page_title = 'Manage All Bookings — ' . APP_NAME;
$active_nav = 'bookings';
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
        <h2>Platform Bookings Oversight</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><i data-lucide="info"></i> <?= e($flash['message']) ?></div>
      <?php endif; ?>

      <!-- Filter Controls -->
      <div class="glass-card" style="margin-bottom:1.5rem;padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div style="display:flex;align-items:center;gap:0.75rem;">
          <span style="font-size:0.85rem;color:var(--text-dim);">Filter Status:</span>
          <a href="<?= BASE_URL ?>admin/bookings.php" class="btn btn-sm <?= empty($status_filter) ? 'btn-primary' : 'btn-outline' ?>">All</a>
          <a href="<?= BASE_URL ?>admin/bookings.php?status=CONFIRMED" class="btn btn-sm <?= $status_filter === 'CONFIRMED' ? 'btn-primary' : 'btn-outline' ?>">Confirmed</a>
          <a href="<?= BASE_URL ?>admin/bookings.php?status=IN_PROGRESS" class="btn btn-sm <?= $status_filter === 'IN_PROGRESS' ? 'btn-primary' : 'btn-outline' ?>">In Progress</a>
          <a href="<?= BASE_URL ?>admin/bookings.php?status=COMPLETED" class="btn btn-sm <?= $status_filter === 'COMPLETED' ? 'btn-primary' : 'btn-outline' ?>">Completed</a>
          <a href="<?= BASE_URL ?>admin/bookings.php?status=CANCELLED" class="btn btn-sm <?= $status_filter === 'CANCELLED' ? 'btn-primary' : 'btn-outline' ?>">Cancelled</a>
        </div>
      </div>

      <div class="glass-card">
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Booking Code</th>
                <th>Client</th>
                <th>Engineer</th>
                <th>Service Scope</th>
                <th>Scheduled Date</th>
                <th>Total Value</th>
                <th>Advance (20%)</th>
                <th>Status</th>
                <th>Admin Override</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $b): ?>
                <tr>
                  <td><strong class="mono" style="color:var(--primary);"><?= e($b['booking_code']) ?></strong></td>
                  <td><?= e($b['customer_name']) ?></td>
                  <td><?= e($b['engineer_name']) ?></td>
                  <td><?= e($b['service_name']) ?></td>
                  <td><?= format_date($b['scheduled_at']) ?></td>
                  <td class="mono" style="font-weight:700;"><?= format_currency($b['total_amount']) ?></td>
                  <td class="mono" style="color:var(--success);"><?= format_currency($b['advance_amount']) ?></td>
                  <td><?= render_status_badge($b['booking_status']) ?></td>
                  <td>
                    <form method="POST" action="" style="display:inline-flex;gap:0.35rem;">
                      <input type="hidden" name="action" value="override_status">
                      <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                      <select name="status" class="form-select" style="padding:0.25rem 0.5rem;font-size:0.75rem;width:auto;">
                        <option value="CONFIRMED" <?= $b['booking_status'] === 'CONFIRMED' ? 'selected' : '' ?>>CONFIRMED</option>
                        <option value="IN_PROGRESS" <?= $b['booking_status'] === 'IN_PROGRESS' ? 'selected' : '' ?>>IN_PROGRESS</option>
                        <option value="COMPLETED" <?= $b['booking_status'] === 'COMPLETED' ? 'selected' : '' ?>>COMPLETED</option>
                        <option value="CANCELLED" <?= $b['booking_status'] === 'CANCELLED' ? 'selected' : '' ?>>CANCELLED</option>
                      </select>
                      <button type="submit" class="btn btn-outline btn-sm" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Set</button>
                    </form>
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
