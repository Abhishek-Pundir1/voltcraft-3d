<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$pdo = get_db_connection();

// Resolve complaint
if (isset($_POST['action']) && $_POST['action'] === 'resolve_complaint') {
    $c_id = intval($_POST['complaint_id']);
    $res = clean_input($_POST['resolution']);
    $pdo->prepare("UPDATE complaints SET status = 'RESOLVED', resolution = ? WHERE id = ?")
        ->execute([$res, $c_id]);
    set_flash('success', "Complaint #{$c_id} has been marked as RESOLVED.");
    header("Location: " . BASE_URL . "admin/complaints.php");
    exit;
}

$stmt = $pdo->query("SELECT c.*, u.full_name as user_name, u.email as user_email, b.booking_code 
                     FROM complaints c 
                     JOIN users u ON c.user_id = u.id 
                     LEFT JOIN bookings b ON c.booking_id = b.id 
                     ORDER BY c.created_at DESC");
$complaints = $stmt->fetchAll();

$page_title = 'Complaints & Support Oversight — ' . APP_NAME;
$active_nav = 'complaints';
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
        <h2>Complaints &amp; Support Resolution</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><i data-lucide="info"></i> <?= e($flash['message']) ?></div>
      <?php endif; ?>

      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.2rem;">All Customer Tickets (<?= count($complaints) ?>)</h3>
        </div>

        <?php if (empty($complaints)): ?>
          <div style="text-align:center;padding:3rem 1rem;">
            <p style="color:var(--text-dim);">No complaints or tickets filed.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Ticket ID</th>
                  <th>Client</th>
                  <th>Subject &amp; Issue</th>
                  <th>Booking Ref</th>
                  <th>Filed Date</th>
                  <th>Status</th>
                  <th>Resolution</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($complaints as $c): ?>
                  <tr>
                    <td><strong>#<?= $c['id'] ?></strong></td>
                    <td>
                      <div><strong><?= e($c['user_name']) ?></strong></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($c['user_email']) ?></div>
                    </td>
                    <td>
                      <div style="font-weight:600;"><?= e($c['subject']) ?></div>
                      <div style="font-size:0.8rem;color:var(--text-muted);"><?= e($c['description']) ?></div>
                    </td>
                    <td class="mono"><?= e($c['booking_code'] ?: 'N/A') ?></td>
                    <td><?= format_date($c['created_at']) ?></td>
                    <td>
                      <span class="badge badge-<?= $c['status'] === 'RESOLVED' ? 'success' : 'warning' ?>">
                        <?= e($c['status']) ?>
                      </span>
                    </td>
                    <td>
                      <?php if ($c['status'] === 'RESOLVED'): ?>
                        <div style="font-size:0.8rem;color:var(--success);"><?= e($c['resolution']) ?></div>
                      <?php else: ?>
                        <form method="POST" action="" style="display:flex;gap:0.4rem;">
                          <input type="hidden" name="action" value="resolve_complaint">
                          <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                          <input type="text" name="resolution" class="form-control" style="padding:0.3rem 0.5rem;font-size:0.8rem;" placeholder="Resolution note..." required>
                          <button type="submit" class="btn btn-success btn-sm">Resolve</button>
                        </form>
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
