<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$pdo = get_db_connection();

$total_volume = $pdo->query("SELECT IFNULL(SUM(amount), 0) FROM payments WHERE payment_status = 'SUCCESS'")->fetchColumn();
$total_advances = $pdo->query("SELECT IFNULL(SUM(amount), 0) FROM payments WHERE payment_type = 'ADVANCE_20' AND payment_status = 'SUCCESS'")->fetchColumn();
$total_balances = $pdo->query("SELECT IFNULL(SUM(amount), 0) FROM payments WHERE payment_type = 'REMAINING_80' AND payment_status = 'SUCCESS'")->fetchColumn();

$stmt = $pdo->query("SELECT p.*, b.booking_code, s.name as service_name, 
                            cust_u.full_name as customer_name, eng_u.full_name as engineer_name 
                     FROM payments p 
                     JOIN bookings b ON p.booking_id = b.id 
                     JOIN services s ON b.service_id = s.id 
                     JOIN customers c ON p.customer_id = c.id 
                     JOIN users cust_u ON c.user_id = cust_u.id 
                     JOIN engineers e ON p.engineer_id = e.id 
                     JOIN users eng_u ON e.user_id = eng_u.id 
                     ORDER BY p.paid_at DESC");
$payments = $stmt->fetchAll();

$page_title = 'Financial & Escrow Audits — ' . APP_NAME;
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
  <?php require_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

  <main class="dashboard-main">
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn"><i data-lucide="menu"></i></button>
        <h2>Financial Audits &amp; Escrow Ledger</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-info">
            <p>Total Processed Volume</p>
            <h3 style="color:var(--primary);"><?= format_currency($total_volume) ?></h3>
          </div>
          <div class="stat-icon">
            <i data-lucide="dollar-sign"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>20% Advance Escrows</p>
            <h3><?= format_currency($total_advances) ?></h3>
          </div>
          <div class="stat-icon green">
            <i data-lucide="shield-check"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>80% Final Settlements</p>
            <h3><?= format_currency($total_balances) ?></h3>
          </div>
          <div class="stat-icon purple">
            <i data-lucide="check-circle-2"></i>
          </div>
        </div>
      </div>

      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.2rem;">Complete Payments Ledger (<?= count($payments) ?>)</h3>
        </div>

        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Transaction Ref</th>
                <th>Booking</th>
                <th>Customer</th>
                <th>Engineer</th>
                <th>Type</th>
                <th>Method</th>
                <th>Amount</th>
                <th>Paid At</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($payments as $p): ?>
                <tr>
                  <td><strong class="mono" style="color:var(--primary);"><?= e($p['transaction_ref']) ?></strong></td>
                  <td class="mono"><?= e($p['booking_code']) ?></td>
                  <td><?= e($p['customer_name']) ?></td>
                  <td><?= e($p['engineer_name']) ?></td>
                  <td>
                    <span class="badge badge-<?= $p['payment_type'] === 'ADVANCE_20' ? 'primary' : 'success' ?>">
                      <?= $p['payment_type'] === 'ADVANCE_20' ? '20% ADVANCE' : '80% BALANCE' ?>
                    </span>
                  </td>
                  <td><?= e($p['payment_method']) ?></td>
                  <td class="mono" style="font-weight:700;color:var(--success);"><?= format_currency($p['amount']) ?></td>
                  <td><?= format_date($p['paid_at']) ?></td>
                  <td><?= render_status_badge($p['payment_status']) ?></td>
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
