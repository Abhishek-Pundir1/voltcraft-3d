<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('engineer');
$user = current_user();
$engineer_id = get_engineer_id($user['id']);

$pdo = get_db_connection();

// Earnings metrics
$total_earned_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) FROM payments WHERE engineer_id = ? AND payment_status = 'SUCCESS'");
$total_earned_stmt->execute([$engineer_id]);
$total_earned = $total_earned_stmt->fetchColumn();

$advances_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) FROM payments WHERE engineer_id = ? AND payment_type = 'ADVANCE_20' AND payment_status = 'SUCCESS'");
$advances_stmt->execute([$engineer_id]);
$total_advances = $advances_stmt->fetchColumn();

$balances_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) FROM payments WHERE engineer_id = ? AND payment_type = 'REMAINING_80' AND payment_status = 'SUCCESS'");
$balances_stmt->execute([$engineer_id]);
$total_balances = $balances_stmt->fetchColumn();

$pending_balance_stmt = $pdo->prepare("SELECT IFNULL(SUM(remaining_amount), 0) FROM bookings WHERE engineer_id = ? AND payment_status = 'ADVANCE_PAID'");
$pending_balance_stmt->execute([$engineer_id]);
$pending_balances = $pending_balance_stmt->fetchColumn();

// Payment Transactions Breakdown
$payments_stmt = $pdo->prepare("SELECT p.*, b.booking_code, s.name as service_name, cust_u.full_name as customer_name 
                                FROM payments p 
                                JOIN bookings b ON p.booking_id = b.id 
                                JOIN services s ON b.service_id = s.id 
                                JOIN customers c ON p.customer_id = c.id 
                                JOIN users cust_u ON c.user_id = cust_u.id 
                                WHERE p.engineer_id = ? 
                                ORDER BY p.paid_at DESC");
$payments_stmt->execute([$engineer_id]);
$earnings_log = $payments_stmt->fetchAll();

$page_title = 'Earnings & Payouts — ' . APP_NAME;
$active_nav = 'earnings';
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
        <h2>Earnings &amp; UPI Escrow Settlements</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <!-- KPI Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-info">
            <p>Total Revenue Cleared</p>
            <h3 style="color:var(--primary);"><?= format_currency($total_earned) ?></h3>
          </div>
          <div class="stat-icon">
            <i data-lucide="wallet"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>20% Advances Received</p>
            <h3><?= format_currency($total_advances) ?></h3>
          </div>
          <div class="stat-icon green">
            <i data-lucide="check-circle"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>80% Balances Cleared</p>
            <h3><?= format_currency($total_balances) ?></h3>
          </div>
          <div class="stat-icon purple">
            <i data-lucide="layers"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Pending Escrow Balance</p>
            <h3 style="color:var(--warning);"><?= format_currency($pending_balances) ?></h3>
          </div>
          <div class="stat-icon amber">
            <i data-lucide="clock"></i>
          </div>
        </div>
      </div>

      <!-- Transactions Log -->
      <div class="glass-card">
        <h3 style="font-size:1.2rem;margin-bottom:1.5rem;">Verified Payment Transactions</h3>

        <?php if (empty($earnings_log)): ?>
          <div style="text-align:center;padding:3rem 1rem;">
            <p style="color:var(--text-dim);">No transactions logged yet.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Transaction Reference</th>
                  <th>Booking</th>
                  <th>Customer</th>
                  <th>Service</th>
                  <th>Tranche Type</th>
                  <th>Method</th>
                  <th>Net Cleared</th>
                  <th>Date &amp; Time</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($earnings_log as $e): ?>
                  <tr>
                    <td><strong class="mono" style="color:var(--primary);"><?= e($e['transaction_ref']) ?></strong></td>
                    <td class="mono"><?= e($e['booking_code']) ?></td>
                    <td><?= e($e['customer_name']) ?></td>
                    <td><?= e($e['service_name']) ?></td>
                    <td>
                      <span class="badge badge-<?= $e['payment_type'] === 'ADVANCE_20' ? 'primary' : 'success' ?>">
                        <?= $e['payment_type'] === 'ADVANCE_20' ? '20% ADVANCE' : '80% BALANCE' ?>
                      </span>
                    </td>
                    <td><?= e($e['payment_method']) ?></td>
                    <td class="mono" style="font-weight:800;color:var(--success);"><?= format_currency($e['amount']) ?></td>
                    <td><?= format_date($e['paid_at']) ?></td>
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
