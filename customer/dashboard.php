<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');
$user = current_user();
$customer_id = get_customer_id($user['id']);

$pdo = get_db_connection();

// Customer KPIs
$total_requests = $pdo->prepare("SELECT COUNT(*) FROM service_requests WHERE customer_id = ?");
$total_requests->execute([$customer_id]);
$total_requests_count = $total_requests->fetchColumn();

$active_bookings = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND booking_status IN ('CONFIRMED', 'IN_PROGRESS')");
$active_bookings->execute([$customer_id]);
$active_bookings_count = $active_bookings->fetchColumn();

$completed_repairs = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND booking_status = 'COMPLETED'");
$completed_repairs->execute([$customer_id]);
$completed_repairs_count = $completed_repairs->fetchColumn();

$total_spent = $pdo->prepare("SELECT IFNULL(SUM(amount), 0) FROM payments WHERE customer_id = ? AND payment_status = 'SUCCESS'");
$total_spent->execute([$customer_id]);
$total_spent_val = $total_spent->fetchColumn();

// Latest Active Booking for live progress tracking
$latest_booking_stmt = $pdo->prepare("SELECT b.*, s.name as service_name, eng_u.full_name as engineer_name, e.hourly_rate 
                                      FROM bookings b 
                                      JOIN services s ON b.service_id = s.id 
                                      JOIN engineers e ON b.engineer_id = e.id 
                                      JOIN users eng_u ON e.user_id = eng_u.id 
                                      WHERE b.customer_id = ? 
                                      ORDER BY b.created_at DESC LIMIT 1");
$latest_booking_stmt->execute([$customer_id]);
$latest_booking = $latest_booking_stmt->fetch();

// Recent Service Requests
$recent_requests = $pdo->prepare("SELECT sr.*, s.name as service_name, eng_u.full_name as engineer_name, b.id as booking_id 
                                  FROM service_requests sr 
                                  JOIN services s ON sr.service_id = s.id 
                                  JOIN engineers e ON sr.engineer_id = e.id 
                                  JOIN users eng_u ON e.user_id = eng_u.id 
                                  LEFT JOIN bookings b ON sr.id = b.request_id 
                                  WHERE sr.customer_id = ? 
                                  ORDER BY sr.created_at DESC LIMIT 5");
$recent_requests->execute([$customer_id]);
$requests = $recent_requests->fetchAll();

$page_title = 'Customer Portal — ' . APP_NAME;
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
  <!-- Customer Sidebar -->
  <?php require_once __DIR__ . '/../includes/customer-sidebar.php'; ?>

  <!-- Main Content Area -->
  <main class="dashboard-main">
    <!-- Topbar -->
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn" aria-label="Toggle Navigation">
          <i data-lucide="menu"></i>
        </button>
        <h2>Customer Command Center</h2>
      </div>

      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn" title="Toggle Theme">
          <i data-lucide="sun"></i>
        </button>
        <a href="<?= BASE_URL ?>customer/new-request.php" class="btn btn-primary btn-sm">
          <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Book Repair
        </a>
      </div>
    </header>

    <div class="dashboard-content">
      <!-- Welcome Flash -->
      <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>">
          <div style="display:flex;align-items:center;gap:0.6rem;">
            <i data-lucide="info"></i>
            <span><?= e($flash['message']) ?></span>
          </div>
          <button onclick="this.parentElement.remove()" style="background:none;border:none;color:inherit;cursor:pointer;">&times;</button>
        </div>
      <?php endif; ?>

      <!-- Stat Cards Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-info">
            <p>Total Requests</p>
            <h3><?= $total_requests_count ?></h3>
          </div>
          <div class="stat-icon">
            <i data-lucide="send"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Active Bookings</p>
            <h3><?= $active_bookings_count ?></h3>
          </div>
          <div class="stat-icon purple">
            <i data-lucide="wrench"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Completed Repairs</p>
            <h3><?= $completed_repairs_count ?></h3>
          </div>
          <div class="stat-icon green">
            <i data-lucide="check-circle-2"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Total Escrow &amp; Paid</p>
            <h3 style="font-size:1.55rem;"><?= format_currency($total_spent_val) ?></h3>
          </div>
          <div class="stat-icon amber">
            <i data-lucide="credit-card"></i>
          </div>
        </div>
      </div>

      <!-- Active Service Telemetry Tracker -->
      <?php if ($latest_booking): ?>
        <div class="glass-card" style="margin-bottom:2rem;border-color:var(--border-glow);">
          <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
            <div>
              <span class="badge badge-primary">ACTIVE TRACKING</span>
              <h3 style="font-size:1.25rem;margin-top:0.4rem;">
                Booking <?= e($latest_booking['booking_code']) ?> &bull; <?= e($latest_booking['service_name']) ?>
              </h3>
              <p style="font-size:0.85rem;color:var(--text-dim);margin-top:0.2rem;">
                Assigned Engineer: <strong style="color:var(--primary);"><?= e($latest_booking['engineer_name']) ?></strong> &bull; Scheduled: <?= format_date($latest_booking['scheduled_at']) ?>
              </p>
            </div>

            <div style="display:flex;gap:0.75rem;align-items:center;">
              <?= render_status_badge($latest_booking['booking_status']) ?>
              <a href="<?= BASE_URL ?>customer/booking-details.php?id=<?= $latest_booking['id'] ?>" class="btn btn-outline btn-sm">
                View Full Dossier <i data-lucide="arrow-right" style="width:14px;height:14px;"></i>
              </a>
            </div>
          </div>

          <!-- Timeline Steps -->
          <div class="status-tracker">
            <div class="tracker-step completed">
              <div class="tracker-node"><i data-lucide="check" style="width:16px;height:16px;"></i></div>
              <span class="tracker-label">Request Accepted</span>
            </div>

            <div class="tracker-step completed">
              <div class="tracker-node"><i data-lucide="check" style="width:16px;height:16px;"></i></div>
              <span class="tracker-label">20% Advance Escrow</span>
            </div>

            <div class="tracker-step <?= in_array($latest_booking['booking_status'], ['IN_PROGRESS', 'COMPLETED']) ? 'completed' : 'active' ?>">
              <div class="tracker-node">3</div>
              <span class="tracker-label">Appointment Confirmed</span>
            </div>

            <div class="tracker-step <?= $latest_booking['booking_status'] === 'COMPLETED' ? 'completed' : ($latest_booking['booking_status'] === 'IN_PROGRESS' ? 'active' : '') ?>">
              <div class="tracker-node">4</div>
              <span class="tracker-label">Bench Diagnostics</span>
            </div>

            <div class="tracker-step <?= $latest_booking['booking_status'] === 'COMPLETED' ? 'completed' : '' ?>">
              <div class="tracker-node">5</div>
              <span class="tracker-label">Completed &amp; Settled</span>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <!-- Recent Service Requests Table -->
      <!-- Smart Electronics Diagnosis Banner -->
      <div class="glass-card" style="margin-bottom:2rem;background:linear-gradient(135deg, rgba(0,240,255,0.08) 0%, rgba(139,92,246,0.08) 100%);border-color:var(--primary);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1.5rem;">
        <div>
          <span class="badge badge-primary"><i data-lucide="cpu" style="width:13px;height:13px;"></i> RULE-BASED ENGINE</span>
          <h3 style="font-size:1.25rem;margin-top:0.4rem;">Troubleshoot Electronic Appliances with Smart Diagnosis</h3>
          <p style="font-size:0.88rem;color:var(--text-muted);margin-top:0.25rem;max-width:560px;">
            Diagnose TV, AC, Refrigerator, Washing Machine, Microwave, and Inverter faults with interactive symptoms analysis and safe preliminary checks.
          </p>
        </div>
        <a href="<?= BASE_URL ?>diagnosis.php" class="btn btn-primary">
          <i data-lucide="sparkles"></i> Launch Smart Diagnosis &rarr;
        </a>
      </div>

      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.2rem;">Recent Service Requests</h3>
          <a href="<?= BASE_URL ?>customer/requests.php" class="btn btn-outline btn-sm">
            View All (<?= $total_requests_count ?>)
          </a>
        </div>

        <?php if (empty($requests)): ?>
          <div style="text-align:center;padding:3rem 1rem;">
            <p style="color:var(--text-dim);margin-bottom:1rem;">You have not submitted any service requests yet.</p>
            <a href="<?= BASE_URL ?>customer/new-request.php" class="btn btn-primary btn-sm">
              <i data-lucide="plus"></i> Create First Request
            </a>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Request ID</th>
                  <th>Service &amp; Problem</th>
                  <th>Engineer</th>
                  <th>Schedule</th>
                  <th>Estimate</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($requests as $r): ?>
                  <tr>
                    <td><strong class="mono" style="color:var(--primary);"><?= e($r['request_code']) ?></strong></td>
                    <td>
                      <div style="font-weight:600;"><?= e($r['title']) ?></div>
                      <div style="font-size:0.78rem;color:var(--text-dim);"><?= e($r['service_name']) ?></div>
                    </td>
                    <td><?= e($r['engineer_name']) ?></td>
                    <td>
                      <div><?= format_date($r['preferred_date'], 'd M Y') ?></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($r['preferred_time']) ?></div>
                    </td>
                    <td class="mono"><?= format_currency($r['estimated_amount']) ?></td>
                    <td><?= render_status_badge($r['status']) ?></td>
                    <td>
                      <?php if ($r['status'] === 'ACCEPTED'): ?>
                        <a href="<?= BASE_URL ?>customer/payment.php?request_id=<?= $r['id'] ?>" class="btn btn-primary btn-sm">
                          <i data-lucide="credit-card" style="width:14px;height:14px;"></i> Pay 20% Advance
                        </a>
                      <?php elseif ($r['booking_id']): ?>
                        <a href="<?= BASE_URL ?>customer/booking-details.php?id=<?= $r['booking_id'] ?>" class="btn btn-outline btn-sm">
                          Details
                        </a>
                      <?php else: ?>
                        <span style="font-size:0.8rem;color:var(--text-dim);">Awaiting review</span>
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
