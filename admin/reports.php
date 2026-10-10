<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$pdo = get_db_connection();

// Top engineers ranking
$eng_ranking = $pdo->query("SELECT e.id, u.full_name, e.title, e.location, e.rating, e.total_reviews,
                                   COUNT(b.id) as completed_jobs,
                                   IFNULL(SUM(p.amount), 0) as gross_revenue 
                            FROM engineers e 
                            JOIN users u ON e.user_id = u.id 
                            LEFT JOIN bookings b ON e.id = b.engineer_id AND b.booking_status = 'COMPLETED' 
                            LEFT JOIN payments p ON e.id = p.engineer_id AND p.payment_status = 'SUCCESS' 
                            GROUP BY e.id 
                            ORDER BY gross_revenue DESC, e.rating DESC")->fetchAll();

// Monthly Booking Totals
$monthly = $pdo->query("SELECT DATE_FORMAT(created_at, '%M %Y') as month_name, 
                               COUNT(*) as total_bookings, 
                               SUM(total_amount) as total_val, 
                               SUM(advance_amount) as total_adv 
                        FROM bookings 
                        GROUP BY DATE_FORMAT(created_at, '%Y-%m') 
                        ORDER BY created_at DESC LIMIT 6")->fetchAll();

$page_title = 'Platform Reports & Analytics — ' . APP_NAME;
$active_nav = 'reports';
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
        <h2>Platform Performance Reports &amp; Analytics</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" onclick="window.print()" class="btn btn-outline btn-sm">
          <i data-lucide="printer"></i> Print Report
        </button>
      </div>
    </header>

    <div class="dashboard-content">
      <!-- Monthly Volume Summary -->
      <div class="glass-card" style="margin-bottom:2rem;">
        <h3 style="font-size:1.2rem;margin-bottom:1.25rem;">Financial Volume By Month</h3>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Period</th>
                <th>Total Bookings</th>
                <th>Total Service Volume</th>
                <th>20% Advances Cleared</th>
                <th>Est. Remaining Escrow</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($monthly as $m): ?>
                <tr>
                  <td><strong><?= e($m['month_name']) ?></strong></td>
                  <td><?= $m['total_bookings'] ?></td>
                  <td class="mono" style="font-weight:700;color:var(--primary);"><?= format_currency($m['total_val']) ?></td>
                  <td class="mono" style="color:var(--success);"><?= format_currency($m['total_adv']) ?></td>
                  <td class="mono"><?= format_currency($m['total_val'] - $m['total_adv']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Engineer Performance Leaderboard -->
      <div class="glass-card">
        <h3 style="font-size:1.2rem;margin-bottom:1.25rem;">Engineer Revenue &amp; Performance Ranking</h3>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Engineer</th>
                <th>Specialization &amp; Hub</th>
                <th>Rating</th>
                <th>Completed Repairs</th>
                <th>Gross Revenue Generated</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($eng_ranking as $idx => $er): ?>
                <tr>
                  <td><strong style="color:var(--primary);">#<?= $idx + 1 ?></strong></td>
                  <td><strong><?= e($er['full_name']) ?></strong></td>
                  <td>
                    <div><?= e($er['title']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($er['location']) ?></div>
                  </td>
                  <td><span class="badge badge-warning">★ <?= number_format($er['rating'], 2) ?> (<?= $er['total_reviews'] ?>)</span></td>
                  <td><strong><?= $er['completed_jobs'] ?></strong></td>
                  <td class="mono" style="font-weight:800;color:var(--success);"><?= format_currency($er['gross_revenue']) ?></td>
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
