<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');
$user = current_user();
$customer_id = get_customer_id($user['id']);

$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT p.*, b.booking_code, s.name as service_name, eng_u.full_name as engineer_name 
                       FROM payments p 
                       JOIN bookings b ON p.booking_id = b.id 
                       JOIN services s ON b.service_id = s.id 
                       JOIN engineers e ON p.engineer_id = e.id 
                       JOIN users eng_u ON e.user_id = eng_u.id 
                       WHERE p.customer_id = ? 
                       ORDER BY p.paid_at DESC");
$stmt->execute([$customer_id]);
$payments = $stmt->fetchAll();

$page_title = 'Payment History & Escrow Statements — ' . APP_NAME;
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
  <?php require_once __DIR__ . '/../includes/customer-sidebar.php'; ?>

  <main class="dashboard-main">
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn"><i data-lucide="menu"></i></button>
        <h2>Payment Records &amp; Escrow Receipts</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.2rem;">All Payment Transactions</h3>
          <span class="badge badge-success">Escrow Protected</span>
        </div>

        <?php if (empty($payments)): ?>
          <div style="text-align:center;padding:3rem 1rem;">
            <p style="color:var(--text-dim);">No payment transactions recorded yet.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Transaction Ref</th>
                  <th>Booking Code</th>
                  <th>Service &amp; Engineer</th>
                  <th>Type</th>
                  <th>Method</th>
                  <th>Amount</th>
                  <th>Paid At</th>
                  <th>Status</th>
                  <th>Receipt</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($payments as $p): ?>
                  <tr>
                    <td><strong class="mono" style="color:var(--primary);"><?= e($p['transaction_ref']) ?></strong></td>
                    <td>
                      <a href="<?= BASE_URL ?>customer/booking-details.php?id=<?= $p['booking_id'] ?>" style="color:var(--text-main);text-decoration:underline;">
                        <?= e($p['booking_code']) ?>
                      </a>
                    </td>
                    <td>
                      <div style="font-weight:600;"><?= e($p['service_name']) ?></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($p['engineer_name']) ?></div>
                    </td>
                    <td>
                      <span class="badge badge-<?= $p['payment_type'] === 'ADVANCE_20' ? 'primary' : 'success' ?>" style="font-size:0.7rem;">
                        <?= $p['payment_type'] === 'ADVANCE_20' ? '20% ADVANCE' : '80% BALANCE' ?>
                      </span>
                    </td>
                    <td><?= e($p['payment_method']) ?></td>
                    <td class="mono" style="font-weight:800;"><?= format_currency($p['amount']) ?></td>
                    <td><?= format_date($p['paid_at'], 'd M Y, h:i A') ?></td>
                    <td><?= render_status_badge($p['payment_status']) ?></td>
                    <td>
                      <button type="button" class="btn btn-outline btn-sm" onclick="showReceipt(<?= htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') ?>)">
                        <i data-lucide="receipt" style="width:14px;height:14px;"></i> View
                      </button>
                    </td>
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

<!-- Receipt Modal -->
<div class="modal-backdrop" id="receiptModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 style="font-size:1.15rem;display:flex;align-items:center;gap:0.5rem;">
        <i data-lucide="check-circle" style="color:var(--success);"></i> VoltCraft Official Receipt
      </h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body" id="receiptModalBody">
      <!-- Populated via JS -->
    </div>
    <div class="modal-footer">
      <button type="button" onclick="window.print()" class="btn btn-outline btn-sm">
        <i data-lucide="printer"></i> Print Statement
      </button>
      <button type="button" class="btn btn-primary btn-sm" data-modal-close>Close</button>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script>
  function showReceipt(p) {
    const body = document.getElementById('receiptModalBody');
    body.innerHTML = `
      <div style="background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:var(--radius-sm);padding:1.5rem;">
        <div style="display:flex;justify-content:space-between;margin-bottom:1.5rem;border-bottom:1px solid var(--border-glass);padding-bottom:1rem;">
          <div>
            <div style="font-weight:800;font-size:1.1rem;color:var(--primary);">VoltCraft 3D Escrow</div>
            <div style="font-size:0.75rem;color:var(--text-dim);">Digital Hardware Escrow Receipt</div>
          </div>
          <div style="text-align:right;">
            <div class="mono" style="font-size:0.85rem;color:var(--text-main);">${p.transaction_ref}</div>
            <div style="font-size:0.75rem;color:var(--text-dim);">${p.paid_at}</div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;font-size:0.88rem;">
          <div>
            <div style="color:var(--text-dim);font-size:0.75rem;">BOOKING CODE</div>
            <div style="font-weight:700;">${p.booking_code}</div>
          </div>
          <div>
            <div style="color:var(--text-dim);font-size:0.75rem;">ASSIGNED ENGINEER</div>
            <div style="font-weight:700;">${p.engineer_name}</div>
          </div>
          <div>
            <div style="color:var(--text-dim);font-size:0.75rem;">SERVICE SCOPE</div>
            <div>${p.service_name}</div>
          </div>
          <div>
            <div style="color:var(--text-dim);font-size:0.75rem;">PAYMENT TYPE</div>
            <div>${p.payment_type} (${p.payment_method})</div>
          </div>
        </div>

        <div style="background:rgba(0,240,255,0.06);border-radius:var(--radius-sm);padding:1rem;display:flex;justify-content:space-between;align-items:center;">
          <span style="font-weight:700;">Amount Cleared</span>
          <span style="font-size:1.4rem;font-weight:800;color:var(--primary);font-family:monospace;">₹ ${parseFloat(p.amount).toFixed(2)}</span>
        </div>
      </div>
    `;
    document.getElementById('receiptModal').classList.add('show');
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
