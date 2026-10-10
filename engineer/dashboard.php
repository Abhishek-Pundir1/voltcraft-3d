<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('engineer');
$user = current_user();
$engineer_id = get_engineer_id($user['id']);

$pdo = get_db_connection();

// Fetch Engineer Profile
$eng_stmt = $pdo->prepare("SELECT * FROM engineers WHERE id = ?");
$eng_stmt->execute([$engineer_id]);
$engineer = $eng_stmt->fetch();

// Metrics:
// New/Pending requests
$pending_stmt = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE engineer_id = ? AND status = 'PENDING'");
$pending_stmt->execute([$engineer_id]);
$pending_count = $pending_stmt->fetchColumn();

// Active Bookings (CONFIRMED / IN_PROGRESS)
$active_stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE engineer_id = ? AND booking_status IN ('CONFIRMED', 'IN_PROGRESS')");
$active_stmt->execute([$engineer_id]);
$active_count = $active_stmt->fetchColumn();

// Completed Bookings
$completed_stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE engineer_id = ? AND booking_status = 'COMPLETED'");
$completed_stmt->execute([$engineer_id]);
$completed_count = $completed_stmt->fetchColumn();

// Total Earnings (sum of all verified payments to this engineer)
$earn_stmt = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) FROM payments WHERE engineer_id = ? AND payment_status = 'SUCCESS'");
$earn_stmt->execute([$engineer_id]);
$total_earnings = $earn_stmt->fetchColumn();

// Upcoming Bookings
$upcoming_stmt = $pdo->prepare("SELECT b.*, sr.title as problem_title, s.name as service_name, cust_u.full_name as customer_name, cust_u.phone as customer_phone 
                                FROM bookings b 
                                JOIN service_requests sr ON b.request_id = sr.id 
                                JOIN services s ON b.service_id = s.id 
                                JOIN customers c ON b.customer_id = c.id 
                                JOIN users cust_u ON c.user_id = cust_u.id 
                                WHERE b.engineer_id = ? AND b.booking_status IN ('CONFIRMED', 'IN_PROGRESS') 
                                ORDER BY b.scheduled_at ASC LIMIT 4");
$upcoming_stmt->execute([$engineer_id]);
$upcoming_bookings = $upcoming_stmt->fetchAll();

// Incoming New Requests
$new_req_stmt = $pdo->prepare("SELECT sr.*, s.name as service_name, cust_u.full_name as customer_name, cust_u.phone as customer_phone 
                               FROM service_requests sr 
                               JOIN services s ON sr.service_id = s.id 
                               JOIN customers c ON sr.customer_id = c.id 
                               JOIN users cust_u ON c.user_id = cust_u.id 
                               WHERE sr.engineer_id = ? AND sr.status = 'PENDING' 
                               ORDER BY sr.created_at DESC LIMIT 5");
$new_req_stmt->execute([$engineer_id]);
$incoming_requests = $new_req_stmt->fetchAll();

$page_title = 'Engineer Console — ' . APP_NAME;
$active_nav = 'dashboard';
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
        <h2>Engineer Diagnostic Console</h2>
      </div>

      <div class="topbar-actions">
        <!-- Live Availability Quick Toggle -->
        <button type="button" id="availToggleBtn" onclick="toggleEngineerAvailability()" class="btn btn-sm btn-<?= $engineer['availability_status'] === 'available' ? 'success' : 'warning' ?>">
          <span class="badge-dot"></span>
          <span id="availLabel"><?= $engineer['availability_status'] === 'available' ? 'Status: Available' : 'Status: Busy' ?></span>
        </button>

        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><i data-lucide="info"></i> <?= e($flash['message']) ?></div>
      <?php endif; ?>

      <!-- Stat Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-info">
            <p>New Requests</p>
            <h3><?= $pending_count ?></h3>
          </div>
          <div class="stat-icon <?= $pending_count > 0 ? 'amber' : '' ?>">
            <i data-lucide="inbox"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Active Bookings</p>
            <h3><?= $active_count ?></h3>
          </div>
          <div class="stat-icon purple">
            <i data-lucide="wrench"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Completed Services</p>
            <h3><?= $completed_count ?></h3>
          </div>
          <div class="stat-icon green">
            <i data-lucide="check-circle-2"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Total Revenue Earned</p>
            <h3 style="font-size:1.55rem;"><?= format_currency($total_earnings) ?></h3>
          </div>
          <div class="stat-icon">
            <i data-lucide="dollar-sign"></i>
          </div>
        </div>
      </div>

      <!-- Action Required: Pending Incoming Requests -->
      <?php if (!empty($incoming_requests)): ?>
        <div class="glass-card" style="margin-bottom:2rem;border-color:var(--warning);">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
            <div>
              <span class="badge badge-warning">ACTION REQUIRED</span>
              <h3 style="font-size:1.25rem;margin-top:0.35rem;">New Incoming Service Requests</h3>
            </div>
            <a href="<?= BASE_URL ?>engineer/requests.php" class="btn btn-outline btn-sm">View All Requests</a>
          </div>

          <div style="display:flex;flex-direction:column;gap:1rem;">
            <?php foreach ($incoming_requests as $req): ?>
              <div style="display:flex;align-items:center;justify-content:space-between;padding:1.2rem;background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:var(--radius-sm);flex-wrap:wrap;gap:1rem;">
                <div>
                  <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:0.3rem;">
                    <span class="mono" style="font-weight:700;color:var(--primary);"><?= e($req['request_code']) ?></span>
                    <span class="badge badge-secondary"><?= e($req['service_name']) ?></span>
                  </div>
                  <h4 style="font-size:1.05rem;"><?= e($req['title']) ?></h4>
                  <div style="font-size:0.82rem;color:var(--text-dim);margin-top:0.25rem;">
                    Client: <strong><?= e($req['customer_name']) ?></strong> &bull; Location: <?= e($req['address']) ?>, <?= e($req['city']) ?> &bull; Schedule: <?= format_date($req['preferred_date'], 'd M Y') ?> (<?= e($req['preferred_time']) ?>)
                  </div>
                </div>

                <div style="display:flex;align-items:center;gap:1.25rem;">
                  <div style="text-align:right;">
                    <div style="font-size:0.75rem;color:var(--text-dim);">Estimated Value</div>
                    <div class="mono" style="font-weight:800;font-size:1.15rem;color:var(--text-main);"><?= format_currency($req['estimated_amount']) ?></div>
                    <div style="font-size:0.72rem;color:var(--warning);">20% Adv: <?= format_currency($req['estimated_amount'] * ADVANCE_PERCENTAGE) ?></div>
                  </div>

                  <div style="display:flex;gap:0.5rem;">
                    <button type="button" onclick="respondRequest(<?= $req['id'] ?>, 'ACCEPT')" class="btn btn-primary btn-sm">
                      <i data-lucide="check" style="width:14px;height:14px;"></i> Accept
                    </button>
                    <button type="button" onclick="openRejectModal(<?= $req['id'] ?>)" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:rgba(239,68,68,0.3);">
                      <i data-lucide="x" style="width:14px;height:14px;"></i> Reject
                    </button>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Upcoming Active Bookings -->
      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.2rem;">Upcoming Scheduled Bookings</h3>
          <a href="<?= BASE_URL ?>engineer/bookings.php" class="btn btn-outline btn-sm">All Bookings</a>
        </div>

        <?php if (empty($upcoming_bookings)): ?>
          <div style="text-align:center;padding:3rem 1rem;">
            <p style="color:var(--text-dim);">No upcoming bookings scheduled at this time.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Booking ID</th>
                  <th>Customer</th>
                  <th>Hardware / Issue</th>
                  <th>Scheduled Time</th>
                  <th>Advance Cleared</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($upcoming_bookings as $b): ?>
                  <tr>
                    <td><strong class="mono" style="color:var(--primary);"><?= e($b['booking_code']) ?></strong></td>
                    <td>
                      <div><strong><?= e($b['customer_name']) ?></strong></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($b['customer_phone']) ?></div>
                    </td>
                    <td>
                      <div style="font-weight:600;"><?= e($b['problem_title']) ?></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($b['service_name']) ?></div>
                    </td>
                    <td><?= format_date($b['scheduled_at']) ?></td>
                    <td class="mono" style="color:var(--success);font-weight:700;"><?= format_currency($b['advance_amount']) ?></td>
                    <td><?= render_status_badge($b['booking_status']) ?></td>
                    <td>
                      <a href="<?= BASE_URL ?>engineer/bookings.php" class="btn btn-outline btn-sm">
                        Manage Progress
                      </a>
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

<!-- Reject Reason Modal -->
<div class="modal-backdrop" id="rejectModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 style="font-size:1.15rem;">Decline Service Request</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="reject_req_id">
      <div class="form-group">
        <label class="form-label">Reason for declining (shared with client):</label>
        <textarea id="reject_reason" class="form-control" style="min-height:100px;" placeholder="e.g. Fully booked during requested hours / Cannot source specific proprietary IC..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="button" onclick="submitRejection()" class="btn btn-danger btn-sm">Confirm Decline</button>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script src="<?= BASE_URL ?>assets/js/api-client.js"></script>
<script>
  async function toggleEngineerAvailability() {
    const res = await ApiClient.request('api/engineer.php?action=toggle_availability', { method: 'POST' });
    if (res.status === 'success') {
      const btn = document.getElementById('availToggleBtn');
      const lbl = document.getElementById('availLabel');
      if (res.data.status === 'available') {
        btn.className = 'btn btn-sm btn-success';
        lbl.innerText = 'Status: Available';
      } else {
        btn.className = 'btn btn-sm btn-warning';
        lbl.innerText = 'Status: Busy';
      }
      window.showToast(res.data.message, 'success');
    }
  }

  async function respondRequest(reqId, action, reason = '') {
    const res = await ApiClient.respondToRequest(reqId, action, reason);
    if (res.status === 'success') {
      window.showToast(res.data.message, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      alert(res.data.message || 'Action failed');
    }
  }

  function openRejectModal(reqId) {
    document.getElementById('reject_req_id').value = reqId;
    document.getElementById('rejectModal').classList.add('show');
  }

  function submitRejection() {
    const reqId = document.getElementById('reject_req_id').value;
    const reason = document.getElementById('reject_reason').value.trim();
    respondRequest(reqId, 'REJECT', reason);
  }

  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
