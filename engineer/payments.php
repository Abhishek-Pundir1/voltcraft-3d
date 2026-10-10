<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('engineer');
$user = current_user();
$engineer_id = get_engineer_id($user['id']);

$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT p.*, b.booking_code, s.name as service_name, cust_u.full_name as customer_name, cust_u.email as customer_email 
                       FROM payments p 
                       JOIN bookings b ON p.booking_id = b.id 
                       JOIN services s ON b.service_id = s.id 
                       JOIN customers c ON p.customer_id = c.id 
                       JOIN users cust_u ON c.user_id = cust_u.id 
                       WHERE p.engineer_id = ? 
                       ORDER BY p.paid_at DESC");
$stmt->execute([$engineer_id]);
$payments = $stmt->fetchAll();

$page_title = 'Advance Payments Audit Log — ' . APP_NAME;
$active_nav = 'payments';
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
        <h2>Advance Payments Audit Log</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.2rem;">Client Payment Deposits (<?= count($payments) ?>)</h3>
          <span class="badge badge-success">Direct UPI Verified</span>
        </div>

        <?php if (empty($payments)): ?>
          <div style="text-align:center;padding:3rem 1rem;">
            <p style="color:var(--text-dim);">No customer payments recorded yet.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Transaction Ref</th>
                  <th>Booking</th>
                  <th>Customer</th>
                  <th>Service</th>
                  <th>Type</th>
                  <th>Method</th>
                  <th>Amount Cleared</th>
                  <th>Paid At</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $p): ?>
                  <tr>
                    <td><strong class="mono" style="color:var(--primary);"><?= e($p['transaction_ref']) ?></strong></td>
                    <td class="mono"><?= e($p['booking_code']) ?></td>
                    <td>
                      <div><strong><?= e($p['customer_name']) ?></strong></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($p['customer_email']) ?></div>
                    </td>
                    <td><?= e($p['service_name']) ?></td>
                    <td>
                      <span class="badge badge-<?= $p['payment_type'] === 'ADVANCE_20' ? 'primary' : 'success' ?>">
                        <?= $p['payment_type'] === 'ADVANCE_20' ? '20% ADVANCE' : '80% BALANCE' ?>
                      </span>
                    </td>
                    <td><?= e($p['payment_method']) ?></td>
                    <td class="mono" style="font-weight:800;color:var(--success);"><?= format_currency($p['amount']) ?></td>
                    <td><?= format_date($p['paid_at']) ?></td>
                    <td><?= render_status_badge($p['payment_status']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
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
