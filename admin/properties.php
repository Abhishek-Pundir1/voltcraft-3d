<?php
// ============================================================================
// VoltCraft 3D — Super Admin Property Management Console
// Verification Approval, Reported Listing Moderation & C2C Oversight
// ============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/property-functions.php';

require_role('admin');
$user = current_user();
$pdo = get_db_connection();

$active_tab = clean_input($_GET['tab'] ?? 'all_properties');

// Handle Admin Actions: Approve, Reject, Delete Listing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation token expired.');
        header("Location: " . BASE_URL . "admin/properties.php");
        exit;
    }

    $act = $_POST['action'];

    if ($act === 'approve_property') {
        $p_id = intval($_POST['property_id']);
        $pdo->prepare("UPDATE customer_properties SET verification_status = 'APPROVED' WHERE id = ?")->execute([$p_id]);
        
        // Notify owner
        $owner_uid = $pdo->query("SELECT c.user_id FROM customer_properties p JOIN customers c ON p.customer_id = c.id WHERE p.id = {$p_id}")->fetchColumn();
        if ($owner_uid) {
            create_notification($owner_uid, 'property_approved', 'Property Listing Approved!', 'Your property listing has been approved and is now publicly live on the website.', 'customer/properties.php');
        }

        set_flash('success', "Property #{$p_id} approved and published.");
        header("Location: " . BASE_URL . "admin/properties.php?tab=" . $active_tab);
        exit;

    } elseif ($act === 'reject_property') {
        $p_id = intval($_POST['property_id']);
        $reason = clean_input($_POST['reason'] ?? 'Listing did not meet platform verification guidelines.');
        $pdo->prepare("UPDATE customer_properties SET verification_status = 'REJECTED', verification_notes = ? WHERE id = ?")->execute([$reason, $p_id]);

        $owner_uid = $pdo->query("SELECT c.user_id FROM customer_properties p JOIN customers c ON p.customer_id = c.id WHERE p.id = {$p_id}")->fetchColumn();
        if ($owner_uid) {
            create_notification($owner_uid, 'property_rejected', 'Property Listing Needs Revision', "Your property listing was rejected: {$reason}", 'customer/properties.php');
        }

        set_flash('info', "Property #{$p_id} rejected.");
        header("Location: " . BASE_URL . "admin/properties.php?tab=" . $active_tab);
        exit;

    } elseif ($act === 'delete_property') {
        $p_id = intval($_POST['property_id']);
        $pdo->prepare("DELETE FROM customer_properties WHERE id = ?")->execute([$p_id]);
        set_flash('warning', "Property #{$p_id} removed permanently.");
        header("Location: " . BASE_URL . "admin/properties.php?tab=" . $active_tab);
        exit;

    } elseif ($act === 'resolve_report') {
        $report_id = intval($_POST['report_id']);
        $admin_note = clean_input($_POST['admin_notes'] ?? 'Reviewed and resolved by admin.');
        $pdo->prepare("UPDATE property_reports SET status = 'ACTIONED', admin_notes = ? WHERE id = ?")->execute([$admin_note, $report_id]);
        set_flash('success', "Safety report #{$report_id} marked as resolved.");
        header("Location: " . BASE_URL . "admin/properties.php?tab=reports");
        exit;

    } elseif ($act === 'dismiss_report') {
        $report_id = intval($_POST['report_id']);
        $pdo->prepare("UPDATE property_reports SET status = 'DISMISSED' WHERE id = ?")->execute([$report_id]);
        set_flash('info', "Safety report #{$report_id} dismissed.");
        header("Location: " . BASE_URL . "admin/properties.php?tab=reports");
        exit;
    }
}

// 1. Fetch All Properties
$properties = $pdo->query("SELECT p.*, u.full_name as owner_name, u.email as owner_email, u.phone as owner_phone,
                                  (SELECT COUNT(*) FROM property_bookings WHERE property_id = p.id) as total_bookings,
                                  (SELECT COUNT(*) FROM property_reports WHERE property_id = p.id AND status = 'PENDING') as pending_reports
                           FROM customer_properties p
                           JOIN customers c ON p.customer_id = c.id
                           JOIN users u ON c.user_id = u.id
                           ORDER BY p.created_at DESC")->fetchAll();

// 2. Fetch Reports
$reports = $pdo->query("SELECT r.*, p.property_code, p.title as property_title, u.full_name as reporter_name, u.email as reporter_email 
                        FROM property_reports r 
                        JOIN customer_properties p ON r.property_id = p.id 
                        JOIN users u ON r.user_id = u.id 
                        ORDER BY r.created_at DESC")->fetchAll();

// 3. Fetch All Bookings
$bookings = $pdo->query("SELECT pb.*, p.property_code, p.title as property_title,
                                req_u.full_name as requester_name, req_u.phone as requester_phone,
                                owner_u.full_name as owner_name, owner_u.phone as owner_phone
                         FROM property_bookings pb
                         JOIN customer_properties p ON pb.property_id = p.id
                         JOIN customers req_c ON pb.requester_customer_id = req_c.id
                         JOIN users req_u ON req_c.user_id = req_u.id
                         JOIN customers owner_c ON pb.owner_customer_id = owner_c.id
                         JOIN users owner_u ON owner_c.user_id = owner_u.id
                         ORDER BY pb.created_at DESC LIMIT 50")->fetchAll();

// KPI Counters
$total_prop_count = count($properties);
$pending_prop_count = 0;
$approved_prop_count = 0;
foreach ($properties as $p) {
    if ($p['verification_status'] === 'PENDING') $pending_prop_count++;
    if ($p['verification_status'] === 'APPROVED') $approved_prop_count++;
}
$pending_reports_count = 0;
foreach ($reports as $r) {
    if ($r['status'] === 'PENDING') $pending_reports_count++;
}

$page_title = 'Customer Properties Oversight — AdminOps';
$active_nav = 'properties';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/property.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<div class="dashboard-wrapper">
  <!-- Admin Sidebar -->
  <?php require_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

  <main class="dashboard-main">
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn"><i data-lucide="menu"></i></button>
        <h2>Customer Property (C2C) Management</h2>
      </div>
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>properties.php" target="_blank" class="btn btn-outline btn-sm">
          <i data-lucide="external-link"></i> Live Catalog
        </a>
      </div>
    </header>

    <div class="dashboard-content">

      <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" style="margin-bottom:1.5rem;">
          <i data-lucide="info"></i> <?= e($flash['message']) ?>
        </div>
      <?php endif; ?>

      <!-- KPIs -->
      <div class="dashboard-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 2rem;">
        
        <div class="kpi-card" onclick="location.href='?tab=all_properties'" style="cursor:pointer;">
          <div class="kpi-icon" style="background:rgba(0, 240, 255, 0.12); color:var(--primary);">
            <i data-lucide="home"></i>
          </div>
          <div class="kpi-details">
            <span class="kpi-label">Total Listings</span>
            <h3 class="kpi-value"><?= $total_prop_count ?></h3>
          </div>
        </div>

        <div class="kpi-card" onclick="location.href='?tab=pending_verification'" style="cursor:pointer;">
          <div class="kpi-icon" style="background:rgba(245, 158, 11, 0.12); color:var(--warning);">
            <i data-lucide="clock"></i>
          </div>
          <div class="kpi-details">
            <span class="kpi-label">Pending Verification</span>
            <h3 class="kpi-value"><?= $pending_prop_count ?></h3>
          </div>
        </div>

        <div class="kpi-card" onclick="location.href='?tab=reports'" style="cursor:pointer;">
          <div class="kpi-icon" style="background:rgba(239, 68, 68, 0.12); color:var(--danger);">
            <i data-lucide="alert-triangle"></i>
          </div>
          <div class="kpi-details">
            <span class="kpi-label">Reported Listings</span>
            <h3 class="kpi-value"><?= $pending_reports_count ?></h3>
          </div>
        </div>

        <div class="kpi-card" onclick="location.href='?tab=all_bookings'" style="cursor:pointer;">
          <div class="kpi-icon" style="background:rgba(139, 92, 246, 0.12); color:var(--secondary);">
            <i data-lucide="calendar"></i>
          </div>
          <div class="kpi-details">
            <span class="kpi-label">C2C Bookings</span>
            <h3 class="kpi-value"><?= count($bookings) ?></h3>
          </div>
        </div>

      </div>

      <!-- Navigation Tabs -->
      <div class="prop-tabs">
        <a href="?tab=all_properties" class="prop-tab-btn <?= $active_tab === 'all_properties' ? 'active' : '' ?>">
          <i data-lucide="grid"></i> All Listings (<?= $total_prop_count ?>)
        </a>
        <a href="?tab=pending_verification" class="prop-tab-btn <?= $active_tab === 'pending_verification' ? 'active' : '' ?>">
          <i data-lucide="shield-alert"></i> Pending Verification (<?= $pending_prop_count ?>)
        </a>
        <a href="?tab=reports" class="prop-tab-btn <?= $active_tab === 'reports' ? 'active' : '' ?>">
          <i data-lucide="flag"></i> Safety Reports (<?= count($reports) ?>)
        </a>
        <a href="?tab=all_bookings" class="prop-tab-btn <?= $active_tab === 'all_bookings' ? 'active' : '' ?>">
          <i data-lucide="users"></i> C2C Bookings Audit (<?= count($bookings) ?>)
        </a>
      </div>

      <!-- TAB 1: ALL PROPERTIES / PENDING VERIFICATION -->
      <?php if ($active_tab === 'all_properties' || $active_tab === 'pending_verification'): ?>
        <div class="dashboard-card">
          <div class="card-header">
            <h3 class="card-title"><?= $active_tab === 'pending_verification' ? 'Pending Verification Queue' : 'Customer Property Directory' ?></h3>
            <p class="card-subtitle">Review listings submitted by customers. Approve verified properties or reject non-compliant items.</p>
          </div>

          <div class="card-body">
            <?php
              $display_list = $properties;
              if ($active_tab === 'pending_verification') {
                  $display_list = array_filter($properties, fn($p) => $p['verification_status'] === 'PENDING');
              }
            ?>

            <?php if (empty($display_list)): ?>
              <div style="text-align:center; padding:3rem 1rem;">
                <i data-lucide="check-circle" style="width:48px; height:48px; color:var(--success); margin-bottom:1rem;"></i>
                <h4>No Listings Pending Action</h4>
                <p style="color:var(--text-muted);">All customer property submissions have been processed.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Property &amp; ID</th>
                      <th>Owner Customer</th>
                      <th>Type &amp; Purpose</th>
                      <th>Price / Deposit</th>
                      <th>Verification</th>
                      <th>Availability</th>
                      <th style="text-align:right;">Admin Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($display_list as $p): ?>
                      <tr>
                        <td>
                          <div style="display:flex; align-items:center; gap:0.75rem;">
                            <img src="<?= e($p['primary_image'] ?: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=150&q=80') ?>" alt="Thumb" style="width:45px; height:45px; border-radius:6px; object-fit:cover;">
                            <div>
                              <strong style="color:var(--text-main); font-size:0.92rem;"><?= e($p['title']) ?></strong>
                              <div style="font-size:0.75rem; color:var(--text-dim); display:flex; align-items:center; gap:0.35rem;">
                                <span class="prop-id-badge" style="padding:1px 5px; font-size:0.7rem;"><?= e($p['property_code']) ?></span>
                                &bull; <?= e($p['city']) ?>, <?= e($p['state']) ?>
                              </div>
                            </div>
                          </div>
                        </td>

                        <td>
                          <div style="font-weight:600; font-size:0.88rem;"><?= e($p['owner_name']) ?></div>
                          <div style="font-size:0.75rem; color:var(--text-dim);"><?= e($p['owner_email']) ?> &bull; <?= e($p['owner_phone']) ?></div>
                        </td>

                        <td>
                          <span style="font-size:0.82rem; text-transform:capitalize;"><?= e(str_replace('_', ' ', $p['property_type'])) ?></span>
                          <div>
                            <span class="prop-purpose-badge prop-purpose-<?= e($p['listing_type']) ?>" style="font-size:0.68rem;">
                              For <?= ucfirst(e($p['listing_type'])) ?>
                            </span>
                          </div>
                        </td>

                        <td>
                          <div style="font-weight:700; color:var(--primary);">
                            <?= format_currency($p['price']) ?> <?= $p['listing_type'] === 'rent' ? '/mo' : '' ?>
                          </div>
                          <div style="font-size:0.75rem; color:var(--text-dim);">
                            Dep: <?= format_currency($p['security_deposit']) ?>
                          </div>
                        </td>

                        <td>
                          <?= render_property_verification_badge($p['verification_status']) ?>
                        </td>

                        <td>
                          <?= render_property_availability_badge($p['availability_status']) ?>
                        </td>

                        <td style="text-align:right;">
                          <div style="display:flex; justify-content:flex-end; align-items:center; gap:0.4rem;">
                            
                            <!-- View Dossier -->
                            <a href="<?= BASE_URL ?>property-detail.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-outline btn-sm" title="View Public Listing">
                              <i data-lucide="external-link" style="width:13px; height:13px;"></i>
                            </a>

                            <!-- Approve Button -->
                            <?php if ($p['verification_status'] !== 'APPROVED'): ?>
                              <form action="<?= BASE_URL ?>admin/properties.php" method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="approve_property">
                                <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-primary btn-sm" title="Approve &amp; Verify Listing">
                                  <i data-lucide="check" style="width:13px; height:13px;"></i> Approve
                                </button>
                              </form>
                            <?php endif; ?>

                            <!-- Reject Button -->
                            <?php if ($p['verification_status'] !== 'REJECTED'): ?>
                              <form action="<?= BASE_URL ?>admin/properties.php" method="POST" style="margin:0;" onsubmit="var r = prompt('Reason for rejection:'); if(!r) return false; this.reason.value = r;">
                                <input type="hidden" name="action" value="reject_property">
                                <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="reason" value="">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-outline btn-sm" style="color:var(--warning);" title="Reject Listing">
                                  <i data-lucide="x" style="width:13px; height:13px;"></i>
                                </button>
                              </form>
                            <?php endif; ?>

                            <!-- Delete / Take Down -->
                            <form action="<?= BASE_URL ?>admin/properties.php" method="POST" style="margin:0;" onsubmit="return confirm('Permanently remove this property listing from the database?');">
                              <input type="hidden" name="action" value="delete_property">
                              <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
                              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                              <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);" title="Remove Listing">
                                <i data-lucide="trash-2" style="width:13px; height:13px;"></i>
                              </button>
                            </form>

                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- TAB 2: SAFETY REPORTS (Requirement 7 & 9) -->
      <?php if ($active_tab === 'reports'): ?>
        <div class="dashboard-card">
          <div class="card-header">
            <h3 class="card-title">Flagged Properties &amp; Safety Complaints</h3>
            <p class="card-subtitle">Moderation queue for user-reported misleading prices, fake listings, or suspicious interactions.</p>
          </div>

          <div class="card-body">
            <?php if (empty($reports)): ?>
              <div style="text-align:center; padding:3rem 1rem;">
                <i data-lucide="shield-check" style="width:48px; height:48px; color:var(--success); margin-bottom:1rem;"></i>
                <h4>Zero Active Safety Complaints</h4>
                <p style="color:var(--text-muted);">No customer property has been flagged as suspicious.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Flagged Property</th>
                      <th>Reporter</th>
                      <th>Reason Flagged</th>
                      <th>Details / Evidence</th>
                      <th>Status</th>
                      <th style="text-align:right;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($reports as $rep): ?>
                      <tr>
                        <td>
                          <span class="prop-id-badge"><?= e($rep['property_code']) ?></span>
                          <strong style="display:block; margin-top:3px;"><?= e($rep['property_title']) ?></strong>
                        </td>
                        <td>
                          <div style="font-weight:600;"><?= e($rep['reporter_name']) ?></div>
                          <div style="font-size:0.75rem; color:var(--text-dim);"><?= e($rep['reporter_email']) ?></div>
                        </td>
                        <td style="color:var(--danger); font-weight:600; font-size:0.85rem;">
                          <?= e($rep['reason']) ?>
                        </td>
                        <td style="font-size:0.85rem; color:var(--text-muted); max-width:280px;">
                          <?= e($rep['details']) ?>
                        </td>
                        <td>
                          <span class="badge badge-<?= $rep['status'] === 'PENDING' ? 'warning' : ($rep['status'] === 'ACTIONED' ? 'danger' : 'secondary') ?>">
                            <?= e($rep['status']) ?>
                          </span>
                        </td>
                        <td style="text-align:right;">
                          <?php if ($rep['status'] === 'PENDING'): ?>
                            <div style="display:flex; justify-content:flex-end; gap:0.4rem;">
                              <form action="<?= BASE_URL ?>admin/properties.php" method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="resolve_report">
                                <input type="hidden" name="report_id" value="<?= $rep['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-primary btn-sm" title="Action / Resolve">
                                  Action
                                </button>
                              </form>
                              <form action="<?= BASE_URL ?>admin/properties.php" method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="dismiss_report">
                                <input type="hidden" name="report_id" value="<?= $rep['id'] ?>">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <button type="submit" class="btn btn-outline btn-sm" title="Dismiss Report">
                                  Dismiss
                                </button>
                              </form>
                            </div>
                          <?php else: ?>
                            <span style="font-size:0.8rem; color:var(--text-dim);">Processed</span>
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
      <?php endif; ?>

      <!-- TAB 3: ALL C2C BOOKINGS AUDIT -->
      <?php if ($active_tab === 'all_bookings'): ?>
        <div class="dashboard-card">
          <div class="card-header">
            <h3 class="card-title">All Customer-to-Customer Bookings Ledger</h3>
            <p class="card-subtitle">Full transparency on requests, scheduled property visits, and confirmed reservations.</p>
          </div>

          <div class="card-body">
            <div class="table-responsive">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Booking Code</th>
                    <th>Property</th>
                    <th>Requester (Customer)</th>
                    <th>Property Owner</th>
                    <th>Scheduled Visit</th>
                    <th>Status</th>
                    <th>Submitted</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($bookings as $bk): ?>
                    <tr>
                      <td>
                        <span class="prop-id-badge"><?= e($bk['booking_code']) ?></span>
                      </td>
                      <td>
                        <strong><?= e($bk['property_title']) ?></strong>
                        <div style="font-size:0.75rem; color:var(--text-dim);"><?= e($bk['property_code']) ?></div>
                      </td>
                      <td>
                        <div style="font-weight:600;"><?= e($bk['requester_name']) ?></div>
                        <div style="font-size:0.75rem; color:var(--text-dim);"><?= e($bk['requester_phone']) ?></div>
                      </td>
                      <td>
                        <div style="font-weight:600;"><?= e($bk['owner_name']) ?></div>
                        <div style="font-size:0.75rem; color:var(--text-dim);"><?= e($bk['owner_phone']) ?></div>
                      </td>
                      <td>
                        <div style="font-weight:700; color:var(--primary); font-size:0.85rem;">
                          <?= format_date($bk['scheduled_visit_date'], 'd M Y') ?>
                        </div>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?= e($bk['scheduled_visit_time']) ?></div>
                      </td>
                      <td>
                        <?= render_property_booking_status_badge($bk['status']) ?>
                      </td>
                      <td style="font-size:0.78rem; color:var(--text-dim);">
                        <?= time_ago($bk['created_at']) ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>

    </div>
  </main>
</div>

</body>
</html>
