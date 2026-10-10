<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$user = current_user();
$pdo = get_db_connection();

// Platform KPIs
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_engineers = $pdo->query("SELECT COUNT(*) FROM engineers")->fetchColumn();
$total_customers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$total_revenue = $pdo->query("SELECT IFNULL(SUM(amount), 0) FROM payments WHERE payment_status = 'SUCCESS'")->fetchColumn();
$open_complaints = $pdo->query("SELECT COUNT(*) FROM complaints WHERE status = 'OPEN'")->fetchColumn();

// Status breakdown
$status_counts = $pdo->query("SELECT booking_status, COUNT(*) as cnt FROM bookings GROUP BY booking_status")->fetchAll(PDO::FETCH_KEY_PAIR);

// Top Services
$top_services = $pdo->query("SELECT s.name, COUNT(b.id) as cnt, SUM(b.total_amount) as rev 
                             FROM services s 
                             LEFT JOIN bookings b ON s.id = b.service_id 
                             GROUP BY s.id ORDER BY cnt DESC LIMIT 5")->fetchAll();

// Recent Bookings
$recent_bookings = $pdo->query("SELECT b.*, sr.title as problem_title, s.name as service_name, 
                                       cust_u.full_name as customer_name, eng_u.full_name as engineer_name 
                                FROM bookings b 
                                JOIN service_requests sr ON b.request_id = sr.id 
                                JOIN services s ON b.service_id = s.id 
                                JOIN customers c ON b.customer_id = c.id 
                                JOIN users cust_u ON c.user_id = cust_u.id 
                                JOIN engineers e ON b.engineer_id = e.id 
                                JOIN users eng_u ON e.user_id = eng_u.id 
                                ORDER BY b.created_at DESC LIMIT 5")->fetchAll();

$page_title = 'Super Administrator Dashboard — ' . APP_NAME;
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
  <!-- Chart.js for High-Precision Analytics -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<div class="dashboard-wrapper">
  <?php require_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

  <main class="dashboard-main">
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn"><i data-lucide="menu"></i></button>
        <h2>Platform Operations &amp; Intelligence</h2>
      </div>
      <div class="topbar-actions">
        <span class="badge badge-danger"><i data-lucide="shield"></i> Root Admin Mode</span>
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <!-- KPI Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-info">
            <p>Total Registered Users</p>
            <h3><?= $total_users ?></h3>
          </div>
          <div class="stat-icon">
            <i data-lucide="users"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Verified Engineers</p>
            <h3><?= $total_engineers ?></h3>
          </div>
          <div class="stat-icon purple">
            <i data-lucide="hard-hat"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Platform Bookings</p>
            <h3><?= $total_bookings ?></h3>
          </div>
          <div class="stat-icon green">
            <i data-lucide="check-square"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Gross Escrow Cleared</p>
            <h3 style="font-size:1.55rem;"><?= format_currency($total_revenue) ?></h3>
          </div>
          <div class="stat-icon amber">
            <i data-lucide="dollar-sign"></i>
          </div>
        </div>
      </div>

      <!-- Analytics Charts Row -->
      <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:2rem;margin-bottom:2rem;">
        <!-- Service Volume Chart -->
        <div class="glass-card">
          <h3 style="font-size:1.15rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="bar-chart-2" style="color:var(--primary);"></i> Top Electronic Service Categories
          </h3>
          <div style="height:260px;">
            <canvas id="servicesChart"></canvas>
          </div>
        </div>

        <!-- Booking Status Doughnut -->
        <div class="glass-card">
          <h3 style="font-size:1.15rem;margin-bottom:1.25rem;display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="pie-chart" style="color:var(--secondary);"></i> Booking Status Distribution
          </h3>
          <div style="height:260px;position:relative;">
            <canvas id="statusChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Recent Platform Bookings -->
      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.2rem;">Recent Platform Bookings</h3>
          <a href="<?= BASE_URL ?>admin/bookings.php" class="btn btn-outline btn-sm">Manage All Bookings</a>
        </div>

        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Booking Code</th>
                <th>Client</th>
                <th>Engineer</th>
                <th>Service Scope</th>
                <th>Scheduled At</th>
                <th>Total Value</th>
                <th>Advance (20%)</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_bookings as $rb): ?>
                <tr>
                  <td><strong class="mono" style="color:var(--primary);"><?= e($rb['booking_code']) ?></strong></td>
                  <td><?= e($rb['customer_name']) ?></td>
                  <td><?= e($rb['engineer_name']) ?></td>
                  <td><?= e($rb['service_name']) ?></td>
                  <td><?= format_date($rb['scheduled_at']) ?></td>
                  <td class="mono"><?= format_currency($rb['total_amount']) ?></td>
                  <td class="mono" style="color:var(--success);"><?= format_currency($rb['advance_amount']) ?></td>
                  <td><?= render_status_badge($rb['booking_status']) ?></td>
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
  // Render Chart.js Analytics
  document.addEventListener('DOMContentLoaded', () => {
    // 1. Services Chart
    const srvCtx = document.getElementById('servicesChart').getContext('2d');
    new Chart(srvCtx, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($top_services, 'name')) ?>,
        datasets: [{
          label: 'Bookings',
          data: <?= json_encode(array_map('intval', array_column($top_services, 'cnt'))) ?>,
          backgroundColor: 'rgba(0, 240, 255, 0.65)',
          borderColor: '#00f0ff',
          borderWidth: 1.5,
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { ticks: { color: '#94a3b8', font: { size: 10 } }, grid: { display: false } },
          y: { ticks: { color: '#94a3b8', stepSize: 1 }, grid: { color: 'rgba(255,255,255,0.05)' } }
        }
      }
    });

    // 2. Status Doughnut Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: ['CONFIRMED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'],
        datasets: [{
          data: [
            <?= intval($status_counts['CONFIRMED'] ?? 0) ?>,
            <?= intval($status_counts['IN_PROGRESS'] ?? 0) ?>,
            <?= intval($status_counts['COMPLETED'] ?? 0) ?>,
            <?= intval($status_counts['CANCELLED'] ?? 0) ?>
          ],
          backgroundColor: ['#00f0ff', '#8b5cf6', '#10b981', '#64748b'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { color: '#94a3b8', boxWidth: 12 } }
        }
      }
    });
  });

  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
