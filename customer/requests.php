<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');
$user = current_user();
$customer_id = get_customer_id($user['id']);

$pdo = get_db_connection();

// Handle cancellation
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    $pdo->prepare("UPDATE service_requests SET status = 'CANCELLED' WHERE id = ? AND customer_id = ? AND status = 'PENDING'")
        ->execute([$cancel_id, $customer_id]);
    set_flash('info', 'Service request was cancelled.');
    header("Location: " . BASE_URL . "customer/requests.php");
    exit;
}

// Fetch all requests
$stmt = $pdo->prepare("SELECT sr.*, s.name as service_name, eng_u.full_name as engineer_name, e.hourly_rate, b.id as booking_id 
                       FROM service_requests sr 
                       JOIN services s ON sr.service_id = s.id 
                       JOIN engineers e ON sr.engineer_id = e.id 
                       JOIN users eng_u ON e.user_id = eng_u.id 
                       LEFT JOIN bookings b ON sr.id = b.request_id 
                       WHERE sr.customer_id = ? 
                       ORDER BY sr.created_at DESC");
$stmt->execute([$customer_id]);
$requests = $stmt->fetchAll();

$page_title = 'My Service Requests — ' . APP_NAME;
$active_nav = 'requests';
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
        <h2>My Service Requests</h2>
      </div>
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>customer/new-request.php" class="btn btn-primary btn-sm">
          <i data-lucide="plus-circle"></i> New Request
        </a>
      </div>
    </header>

    <div class="dashboard-content">
      <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>">
          <i data-lucide="info"></i> <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <div class="glass-card">
        <?php if (empty($requests)): ?>
          <div style="text-align:center;padding:4rem 2rem;">
            <div style="font-size:3rem;margin-bottom:1rem;">📋</div>
            <h3>No Service Requests Yet</h3>
            <p style="margin-top:0.5rem;color:var(--text-dim);">Need electronic diagnostics or hardware repairs? Submit a request to a certified specialist.</p>
            <a href="<?= BASE_URL ?>customer/new-request.php" class="btn btn-primary" style="margin-top:1.5rem;">
              <i data-lucide="plus"></i> Create Request
            </a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Request ID</th>
                  <th>Device / Fault</th>
                  <th>Service</th>
                  <th>Engineer</th>
                  <th>Preferred Date</th>
                  <th>Estimate</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($requests as $r): ?>
                  <tr>
                    <td>
                      <strong class="mono" style="color:var(--primary);"><?= e($r['request_code']) ?></strong>
                      <?php if (!empty($r['diagnosis_id'])): ?>
                        <div style="margin-top:0.25rem;">
                          <span class="badge badge-primary" style="font-size:0.65rem;padding:2px 5px;"><i data-lucide="cpu" style="width:10px;height:10px;vertical-align:middle;"></i> Smart Diagnosis</span>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div style="font-weight:600;"><?= e($r['title']) ?></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= e($r['description']) ?>
                      </div>
                    </td>
                    <td><?= e($r['service_name']) ?></td>
                    <td><?= e($r['engineer_name']) ?></td>
                    <td>
                      <div><?= format_date($r['preferred_date'], 'd M Y') ?></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($r['preferred_time']) ?></div>
                    </td>
                    <td class="mono"><?= format_currency($r['estimated_amount']) ?></td>
                    <td>
                      <?= render_status_badge($r['status']) ?>
                      <?php if ($r['status'] === 'REJECTED' && $r['rejection_reason']): ?>
                        <div style="font-size:0.72rem;color:var(--danger);margin-top:0.25rem;">
                          Reason: <?= e($r['rejection_reason']) ?>
                        </div>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($r['status'] === 'ACCEPTED'): ?>
                        <a href="<?= BASE_URL ?>customer/payment.php?request_id=<?= $r['id'] ?>" class="btn btn-primary btn-sm">
                          <i data-lucide="credit-card" style="width:14px;height:14px;"></i> Pay 20% (<?= format_currency($r['estimated_amount'] * ADVANCE_PERCENTAGE) ?>)
                        </a>
                      <?php elseif ($r['booking_id']): ?>
                        <a href="<?= BASE_URL ?>customer/booking-details.php?id=<?= $r['booking_id'] ?>" class="btn btn-outline btn-sm">
                          Booking Details
                        </a>
                      <?php elseif ($r['status'] === 'PENDING'): ?>
                        <a href="<?= BASE_URL ?>customer/requests.php?cancel_id=<?= $r['id'] ?>" onclick="return confirm('Cancel this pending request?')" class="btn btn-danger btn-sm" style="background:rgba(239,68,68,0.15);color:var(--danger);border-color:rgba(239,68,68,0.3);">
                          Cancel
                        </a>
                      <?php else: ?>
                        <span style="font-size:0.8rem;color:var(--text-dim);">&mdash;</span>
                      <?php endif; ?>
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

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script>
  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
