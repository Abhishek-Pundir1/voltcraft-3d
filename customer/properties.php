<?php
// ============================================================================
// VoltCraft 3D — Customer Property Command Center
// Customer-to-Customer Property Management, Inquiries & Booking Oversight
// ============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/property-functions.php';

require_role('customer');
$user = current_user();
$customer_id = get_customer_id($user['id']);

$pdo = get_db_connection();

// Current Active Tab
$current_tab = clean_input($_GET['tab'] ?? 'my_properties');

// 1. My Listed Properties
$my_properties_stmt = $pdo->prepare("SELECT p.*, 
                                     (SELECT COUNT(*) FROM property_bookings WHERE property_id = p.id) as total_requests,
                                     (SELECT COUNT(*) FROM property_inquiries WHERE property_id = p.id) as total_inquiries
                                     FROM customer_properties p 
                                     WHERE p.customer_id = ? 
                                     ORDER BY p.created_at DESC");
$my_properties_stmt->execute([$customer_id]);
$my_properties = $my_properties_stmt->fetchAll();

// 2. Booking Requests Received (As Property Owner)
$received_requests_stmt = $pdo->prepare("SELECT pb.*, p.title as property_title, p.property_code, p.city as property_city,
                                                u.full_name as requester_name, u.email as requester_email, u.phone as requester_phone, u.avatar as requester_avatar
                                         FROM property_bookings pb
                                         JOIN customer_properties p ON pb.property_id = p.id
                                         JOIN customers c ON pb.requester_customer_id = c.id
                                         JOIN users u ON c.user_id = u.id
                                         WHERE pb.owner_customer_id = ?
                                         ORDER BY pb.created_at DESC");
$received_requests_stmt->execute([$customer_id]);
$received_requests = $received_requests_stmt->fetchAll();

// 3. My Booking Requests (As Property Seeker)
$my_booking_requests_stmt = $pdo->prepare("SELECT pb.*, p.title as property_title, p.property_code, p.city as property_city, 
                                                  p.price as property_price, p.listing_type, p.whatsapp_number, p.phone_number, p.allow_direct_calls,
                                                  owner_u.full_name as owner_name, owner_u.avatar as owner_avatar, owner_u.phone as owner_user_phone
                                           FROM property_bookings pb
                                           JOIN customer_properties p ON pb.property_id = p.id
                                           JOIN customers owner_c ON pb.owner_customer_id = owner_c.id
                                           JOIN users owner_u ON owner_c.user_id = owner_u.id
                                           WHERE pb.requester_customer_id = ?
                                           ORDER BY pb.created_at DESC");
$my_booking_requests_stmt->execute([$customer_id]);
$my_requests = $my_booking_requests_stmt->fetchAll();

// 4. Saved Properties (Bookmarks)
$saved_properties_stmt = $pdo->prepare("SELECT p.*, ps.created_at as saved_at,
                                               u.full_name as owner_name, u.avatar as owner_avatar
                                        FROM property_saved ps
                                        JOIN customer_properties p ON ps.property_id = p.id
                                        JOIN customers c ON p.customer_id = c.id
                                        JOIN users u ON c.user_id = u.id
                                        WHERE ps.customer_id = ?
                                        ORDER BY ps.created_at DESC");
$saved_properties_stmt->execute([$customer_id]);
$saved_properties = $saved_properties_stmt->fetchAll();

// 5. Inquiries & Contact History
$inquiries_stmt = $pdo->prepare("SELECT pi.*, p.property_code, p.title as property_title 
                                 FROM property_inquiries pi
                                 JOIN customer_properties p ON pi.property_id = p.id
                                 WHERE p.customer_id = ? OR pi.sender_user_id = ?
                                 ORDER BY pi.created_at DESC LIMIT 30");
$inquiries_stmt->execute([$customer_id, $user['id']]);
$inquiries = $inquiries_stmt->fetchAll();

// KPI Metrics for Customer Properties
$count_my_listings = count($my_properties);
$count_received_requests = count($received_requests);
$count_my_bookings = count($my_requests);
$count_saved = count($saved_properties);

$page_title = 'Customer Property Hub — ' . APP_NAME;
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
  <!-- Customer Sidebar -->
  <?php require_once __DIR__ . '/../includes/customer-sidebar.php'; ?>

  <!-- Main Dashboard View -->
  <main class="dashboard-main">
    
    <!-- Topbar -->
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn" aria-label="Toggle Navigation">
          <i data-lucide="menu"></i>
        </button>
        <h2>Customer Property Hub (C2C)</h2>
      </div>

      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>properties.php" class="btn btn-outline btn-sm">
          <i data-lucide="compass"></i> Browse Properties
        </a>
        <a href="<?= BASE_URL ?>customer/property-form.php" class="btn btn-primary btn-sm">
          <i data-lucide="plus-circle"></i> List New Property
        </a>
      </div>
    </header>

    <div class="dashboard-content">
      
      <!-- Flash Notifications -->
      <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" style="margin-bottom:1.5rem;">
          <div style="display:flex; align-items:center; gap:0.6rem;">
            <i data-lucide="info"></i>
            <span><?= e($flash['message']) ?></span>
          </div>
          <button onclick="this.parentElement.remove()" style="background:none; border:none; color:inherit; cursor:pointer;">&times;</button>
        </div>
      <?php endif; ?>

      <!-- Quick KPI Counters Strip -->
      <div class="dashboard-kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 2rem;">
        
        <div class="kpi-card" onclick="switchTab('my_properties')" style="cursor:pointer;">
          <div class="kpi-icon" style="background:rgba(0, 240, 255, 0.12); color:var(--primary);">
            <i data-lucide="building"></i>
          </div>
          <div class="kpi-details">
            <span class="kpi-label">My Listings</span>
            <h3 class="kpi-value"><?= $count_my_listings ?></h3>
          </div>
        </div>

        <div class="kpi-card" onclick="switchTab('requests_received')" style="cursor:pointer;">
          <div class="kpi-icon" style="background:rgba(245, 158, 11, 0.12); color:var(--warning);">
            <i data-lucide="inbox"></i>
          </div>
          <div class="kpi-details">
            <span class="kpi-label">Requests Received</span>
            <h3 class="kpi-value"><?= $count_received_requests ?></h3>
          </div>
        </div>

        <div class="kpi-card" onclick="switchTab('my_requests')" style="cursor:pointer;">
          <div class="kpi-icon" style="background:rgba(139, 92, 246, 0.12); color:var(--secondary);">
            <i data-lucide="send"></i>
          </div>
          <div class="kpi-details">
            <span class="kpi-label">My Booking Requests</span>
            <h3 class="kpi-value"><?= $count_my_bookings ?></h3>
          </div>
        </div>

        <div class="kpi-card" onclick="switchTab('saved_properties')" style="cursor:pointer;">
          <div class="kpi-icon" style="background:rgba(239, 68, 68, 0.12); color:var(--danger);">
            <i data-lucide="heart"></i>
          </div>
          <div class="kpi-details">
            <span class="kpi-label">Saved Favorites</span>
            <h3 class="kpi-value"><?= $count_saved ?></h3>
          </div>
        </div>

      </div>

      <!-- Module Tabs (Requirement 6) -->
      <div class="prop-tabs">
        <button type="button" class="prop-tab-btn <?= $current_tab === 'my_properties' ? 'active' : '' ?>" onclick="switchTab('my_properties')">
          <i data-lucide="home"></i> My Properties (<?= $count_my_listings ?>)
        </button>
        <button type="button" class="prop-tab-btn <?= $current_tab === 'requests_received' ? 'active' : '' ?>" onclick="switchTab('requests_received')">
          <i data-lucide="inbox"></i> Requests Received (<?= $count_received_requests ?>)
        </button>
        <button type="button" class="prop-tab-btn <?= $current_tab === 'my_requests' ? 'active' : '' ?>" onclick="switchTab('my_requests')">
          <i data-lucide="calendar"></i> My Bookings &amp; Visits (<?= $count_my_bookings ?>)
        </button>
        <button type="button" class="prop-tab-btn <?= $current_tab === 'saved_properties' ? 'active' : '' ?>" onclick="switchTab('saved_properties')">
          <i data-lucide="bookmark"></i> Saved Properties (<?= $count_saved ?>)
        </button>
        <button type="button" class="prop-tab-btn <?= $current_tab === 'inquiries' ? 'active' : '' ?>" onclick="switchTab('inquiries')">
          <i data-lucide="message-square"></i> Inquiries &amp; Visits Log
        </button>
      </div>

      <!-- TAB 1: MY PROPERTIES (Listings, Status, Edit, Delete, Availability) -->
      <div id="tab_my_properties" class="tab-pane" style="display:<?= $current_tab === 'my_properties' ? 'block' : 'none' ?>;">
        <div class="dashboard-card">
          <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
            <div>
              <h3 class="card-title">My Listed Properties</h3>
              <p class="card-subtitle">Manage flats, apartments, and houses you have published for rent or sale.</p>
            </div>
            <a href="<?= BASE_URL ?>customer/property-form.php" class="btn btn-primary btn-sm">
              <i data-lucide="plus-circle"></i> Add New Listing
            </a>
          </div>

          <div class="card-body">
            <?php if (empty($my_properties)): ?>
              <div style="text-align:center; padding:3.5rem 1rem;">
                <i data-lucide="home" style="width:48px; height:48px; color:var(--text-dim); margin-bottom:1rem;"></i>
                <h4>No Property Listings Yet</h4>
                <p style="color:var(--text-muted); max-width:450px; margin:0 auto 1.5rem;">
                  You haven't listed any properties yet. List your flat, apartment, or house to connect directly with interested tenants and buyers with zero brokerage.
                </p>
                <a href="<?= BASE_URL ?>customer/property-form.php" class="btn btn-primary">
                  <i data-lucide="plus-circle"></i> List Your First Property
                </a>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Property</th>
                      <th>Type / Purpose</th>
                      <th>Price &amp; Deposit</th>
                      <th>Verification</th>
                      <th>Availability</th>
                      <th>Inquiries / Requests</th>
                      <th style="text-align:right;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($my_properties as $p): ?>
                      <tr>
                        <td>
                          <div style="display:flex; align-items:center; gap:0.75rem;">
                            <img src="<?= e($p['primary_image'] ?: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=150&q=80') ?>" alt="Thumb" style="width:48px; height:48px; border-radius:8px; object-fit:cover;">
                            <div>
                              <div style="font-weight:700; font-size:0.92rem; color:var(--text-main);">
                                <a href="<?= BASE_URL ?>property-detail.php?id=<?= $p['id'] ?>" style="color:inherit; text-decoration:none;">
                                  <?= e($p['title']) ?>
                                </a>
                              </div>
                              <div style="font-size:0.78rem; color:var(--text-dim); display:flex; align-items:center; gap:0.35rem;">
                                <span class="prop-id-badge" style="padding:1px 5px; font-size:0.7rem;"><?= e($p['property_code']) ?></span>
                                &bull; <?= e($p['city']) ?>
                              </div>
                            </div>
                          </div>
                        </td>

                        <td>
                          <span style="font-size:0.85rem; font-weight:600; text-transform:capitalize;">
                            <?= e(str_replace('_', ' ', $p['property_type'])) ?>
                          </span>
                          <div>
                            <span class="prop-purpose-badge prop-purpose-<?= e($p['listing_type']) ?>" style="font-size:0.7rem;">
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
                          <!-- Availability Management Dropdown Form (Requirement 6) -->
                          <form action="<?= BASE_URL ?>customer/property-booking-action.php" method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="update_availability">
                            <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <select name="availability_status" onchange="this.form.submit()" class="form-control" style="padding:0.25rem 0.6rem; font-size:0.78rem; width:auto; border-radius:6px;">
                              <option value="AVAILABLE" <?= $p['availability_status'] === 'AVAILABLE' ? 'selected' : '' ?>>Available Now</option>
                              <option value="VISIT_SCHEDULED" <?= $p['availability_status'] === 'VISIT_SCHEDULED' ? 'selected' : '' ?>>Visit Scheduled</option>
                              <option value="RESERVED" <?= $p['availability_status'] === 'RESERVED' ? 'selected' : '' ?>>Reserved</option>
                              <option value="RENTED_OR_SOLD" <?= $p['availability_status'] === 'RENTED_OR_SOLD' ? 'selected' : '' ?>>Rented / Sold</option>
                              <option value="INACTIVE" <?= $p['availability_status'] === 'INACTIVE' ? 'selected' : '' ?>>Off Market</option>
                            </select>
                          </form>
                        </td>

                        <td>
                          <span class="badge badge-primary"><?= (int)$p['total_requests'] ?> Requests</span>
                          <span class="badge badge-secondary"><?= (int)$p['total_inquiries'] ?> Inquiries</span>
                        </td>

                        <td style="text-align:right;">
                          <div style="display:flex; justify-content:flex-end; gap:0.5rem;">
                            <a href="<?= BASE_URL ?>property-detail.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" title="View Dossier">
                              <i data-lucide="eye" style="width:14px; height:14px;"></i>
                            </a>
                            <a href="<?= BASE_URL ?>customer/property-form.php?id=<?= $p['id'] ?>" class="btn btn-outline btn-sm" title="Edit Listing">
                              <i data-lucide="edit" style="width:14px; height:14px;"></i>
                            </a>
                            <form action="<?= BASE_URL ?>customer/property-booking-action.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this property listing?');">
                              <input type="hidden" name="action" value="delete_property">
                              <input type="hidden" name="property_id" value="<?= $p['id'] ?>">
                              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                              <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);" title="Delete Listing">
                                <i data-lucide="trash-2" style="width:14px; height:14px;"></i>
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
      </div>

      <!-- TAB 2: BOOKING REQUESTS RECEIVED (As Owner) -->
      <div id="tab_requests_received" class="tab-pane" style="display:<?= $current_tab === 'requests_received' ? 'block' : 'none' ?>;">
        <div class="dashboard-card">
          <div class="card-header">
            <h3 class="card-title">Booking Requests Received from Other Customers</h3>
            <p class="card-subtitle">Review, accept, schedule visits, or respond to interested tenants and buyers.</p>
          </div>

          <div class="card-body">
            <?php if (empty($received_requests)): ?>
              <div style="text-align:center; padding:3.5rem 1rem;">
                <i data-lucide="inbox" style="width:48px; height:48px; color:var(--text-dim); margin-bottom:1rem;"></i>
                <h4>No Incoming Requests Yet</h4>
                <p style="color:var(--text-muted);">When other customers submit booking requests or ask to schedule a property visit, they will appear here.</p>
              </div>
            <?php else: ?>
              <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <?php foreach ($received_requests as $req): 
                  // Prepare WhatsApp chat link to customer
                  $cust_wa = get_property_whatsapp_url(
                      $req['requester_phone'],
                      $req['property_code'],
                      $req['property_title'],
                      $req['property_city'],
                      'Regarding your booking request ' . $req['booking_code']
                  );
                ?>
                  <div class="glass-card" style="padding:1.5rem; border:1px solid var(--border-glass);">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
                      <div>
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.35rem;">
                          <span class="prop-id-badge"><?= e($req['booking_code']) ?></span>
                          <span style="font-weight:700; color:var(--text-main);"><?= e($req['property_title']) ?></span>
                          <span style="font-size:0.75rem; color:var(--text-dim);">(<?= e($req['property_code']) ?>)</span>
                        </div>
                        <div style="font-size:0.84rem; color:var(--text-muted); display:flex; align-items:center; gap:0.5rem;">
                          <span>Request Type: <strong><?= ucfirst(e(str_replace('_', ' ', $req['request_type']))) ?></strong></span>
                          &bull;
                          <span>Submitted: <?= time_ago($req['created_at']) ?></span>
                        </div>
                      </div>

                      <div>
                        <?= render_property_booking_status_badge($req['status']) ?>
                      </div>
                    </div>

                    <!-- Requester Details & Scheduled Visit Info -->
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:1rem; background:rgba(255,255,255,0.02); padding:1rem; border-radius:var(--radius-md); margin-bottom:1.25rem;">
                      <div>
                        <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Interested Customer</div>
                        <div style="display:flex; align-items:center; gap:0.6rem; margin-top:0.35rem;">
                          <img src="<?= get_avatar_url($req['requester_avatar'], $req['requester_name']) ?>" alt="Avatar" style="width:32px; height:32px; border-radius:50%;">
                          <div>
                            <div style="font-weight:700; font-size:0.9rem;"><?= e($req['requester_name']) ?></div>
                            <div style="font-size:0.78rem; color:var(--text-dim);"><?= e($req['requester_email']) ?> &bull; <?= e($req['requester_phone']) ?></div>
                          </div>
                        </div>
                      </div>

                      <div>
                        <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Requested Visit Date &amp; Slot</div>
                        <div style="font-weight:700; font-size:0.92rem; color:var(--primary); margin-top:0.35rem;">
                          <i data-lucide="calendar" style="width:14px; height:14px; vertical-align:middle;"></i> <?= format_date($req['scheduled_visit_date'], 'D, d M Y') ?>
                        </div>
                        <div style="font-size:0.8rem; color:var(--text-muted);"><?= e($req['scheduled_visit_time']) ?></div>
                      </div>
                    </div>

                    <!-- Customer Message -->
                    <div style="font-size:0.88rem; line-height:1.6; color:var(--text-muted); background:rgba(0,0,0,0.2); padding:0.85rem 1rem; border-radius:var(--radius-sm); margin-bottom:1.25rem;">
                      <strong style="color:var(--text-main);">Customer Message:</strong> <?= e($req['message']) ?>
                    </div>

                    <?php if (!empty($req['rejection_reason'])): ?>
                      <div class="alert alert-danger" style="margin-bottom:1rem; font-size:0.85rem;">
                        <strong>Rejection Reason:</strong> <?= e($req['rejection_reason']) ?>
                      </div>
                    <?php endif; ?>

                    <!-- Action Controls for Owner (Requirement 5) -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
                      <div style="display:flex; gap:0.6rem;">
                        <?php if ($cust_wa): ?>
                          <a href="<?= e($cust_wa) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm">
                            <i data-lucide="message-circle"></i> Chat with Customer
                          </a>
                        <?php endif; ?>
                        <a href="tel:<?= preg_replace('/[^0-9+]/', '', $req['requester_phone']) ?>" class="btn btn-outline btn-sm">
                          <i data-lucide="phone"></i> Call Customer
                        </a>
                      </div>

                      <div style="display:flex; gap:0.5rem;">
                        <?php if (in_array($req['status'], ['Request Submitted', 'Pending Owner Approval'])): ?>
                          <!-- Accept Form -->
                          <form action="<?= BASE_URL ?>customer/property-booking-action.php" method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="update_booking_status">
                            <input type="hidden" name="booking_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="new_status" value="Accepted">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                              <i data-lucide="check"></i> Accept Request
                            </button>
                          </form>

                          <!-- Schedule Visit Form -->
                          <form action="<?= BASE_URL ?>customer/property-booking-action.php" method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="update_booking_status">
                            <input type="hidden" name="booking_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="new_status" value="Visit Scheduled">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-outline btn-sm">
                              <i data-lucide="calendar"></i> Confirm Visit
                            </button>
                          </form>

                          <!-- Reject Form with Reason Prompt -->
                          <form action="<?= BASE_URL ?>customer/property-booking-action.php" method="POST" style="margin:0;" onsubmit="var r = prompt('Reason for rejecting request:'); if(!r) return false; this.rejection_reason.value = r;">
                            <input type="hidden" name="action" value="update_booking_status">
                            <input type="hidden" name="booking_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="new_status" value="Rejected">
                            <input type="hidden" name="rejection_reason" value="">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);">
                              <i data-lucide="x"></i> Reject
                            </button>
                          </form>

                        <?php elseif ($req['status'] === 'Accepted' || $req['status'] === 'Visit Scheduled'): ?>
                          <form action="<?= BASE_URL ?>customer/property-booking-action.php" method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="update_booking_status">
                            <input type="hidden" name="booking_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="new_status" value="Reserved">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-primary btn-sm">
                              <i data-lucide="lock"></i> Mark Reserved
                            </button>
                          </form>
                          <form action="<?= BASE_URL ?>customer/property-booking-action.php" method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="update_booking_status">
                            <input type="hidden" name="booking_id" value="<?= $req['id'] ?>">
                            <input type="hidden" name="new_status" value="Rented or Sold">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--success);">
                              <i data-lucide="check-check"></i> Mark Rented / Sold
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </div>

                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- TAB 3: MY BOOKING REQUESTS (As Seeker) -->
      <div id="tab_my_requests" class="tab-pane" style="display:<?= $current_tab === 'my_requests' ? 'block' : 'none' ?>;">
        <div class="dashboard-card">
          <div class="card-header">
            <h3 class="card-title">My Property Booking Requests &amp; Scheduled Visits</h3>
            <p class="card-subtitle">Track the status of property visits and rental requests you submitted to owners.</p>
          </div>

          <div class="card-body">
            <?php if (empty($my_requests)): ?>
              <div style="text-align:center; padding:3.5rem 1rem;">
                <i data-lucide="calendar" style="width:48px; height:48px; color:var(--text-dim); margin-bottom:1rem;"></i>
                <h4>No Booking Requests Submitted</h4>
                <p style="color:var(--text-muted); max-width:450px; margin:0 auto 1.5rem;">
                  Browse customer-listed properties and submit a visit request or booking inquiry directly.
                </p>
                <a href="<?= BASE_URL ?>properties.php" class="btn btn-primary">Browse Properties</a>
              </div>
            <?php else: ?>
              <div style="display:flex; flex-direction:column; gap:1.25rem;">
                <?php foreach ($my_requests as $mr): 
                  $owner_wa = get_property_whatsapp_url(
                      $mr['whatsapp_number'],
                      $mr['property_code'],
                      $mr['property_title'],
                      $mr['property_city'],
                      'Regarding my booking request ' . $mr['booking_code']
                  );
                ?>
                  <div class="glass-card" style="padding:1.5rem;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; margin-bottom:1rem;">
                      <div>
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.25rem;">
                          <span class="prop-id-badge"><?= e($mr['booking_code']) ?></span>
                          <h4 style="margin:0; font-size:1.05rem;">
                            <a href="<?= BASE_URL ?>property-detail.php?id=<?= $mr['property_id'] ?>" style="color:inherit; text-decoration:none;">
                              <?= e($mr['property_title']) ?>
                            </a>
                          </h4>
                        </div>
                        <div style="font-size:0.82rem; color:var(--text-dim);">
                          Property ID: <?= e($mr['property_code']) ?> &bull; <?= e($mr['property_city']) ?>
                        </div>
                      </div>

                      <div>
                        <?= render_property_booking_status_badge($mr['status']) ?>
                      </div>
                    </div>

                    <!-- Visit Schedule Details & Owner Contact -->
                    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1rem; background:rgba(255,255,255,0.02); padding:1rem; border-radius:var(--radius-md); margin-bottom:1rem;">
                      <div>
                        <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Scheduled Visit Slot</div>
                        <div style="font-weight:700; font-size:0.95rem; color:var(--primary); margin-top:0.25rem;">
                          <?= format_date($mr['scheduled_visit_date'], 'D, d M Y') ?>
                        </div>
                        <div style="font-size:0.8rem; color:var(--text-muted);"><?= e($mr['scheduled_visit_time']) ?></div>
                      </div>

                      <div>
                        <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Property Owner</div>
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.25rem;">
                          <img src="<?= get_avatar_url($mr['owner_avatar'], $mr['owner_name']) ?>" alt="Avatar" style="width:28px; height:28px; border-radius:50%;">
                          <div style="font-weight:700; font-size:0.88rem;"><?= e($mr['owner_name']) ?></div>
                        </div>
                      </div>

                      <div>
                        <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Rent / Price</div>
                        <div style="font-weight:700; font-size:0.95rem; color:var(--primary); margin-top:0.25rem;">
                          <?= format_currency($mr['property_price']) ?> <?= $mr['listing_type'] === 'rent' ? '/mo' : '' ?>
                        </div>
                      </div>
                    </div>

                    <!-- Inquiry Note -->
                    <div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">
                      <strong>My Note:</strong> <?= e($mr['message']) ?>
                    </div>

                    <?php if (!empty($mr['rejection_reason'])): ?>
                      <div class="alert alert-danger" style="margin-bottom:1rem; font-size:0.85rem;">
                        <strong>Owner Response:</strong> <?= e($mr['rejection_reason']) ?>
                      </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
                      <div style="display:flex; gap:0.6rem;">
                        <?php if ($owner_wa): ?>
                          <a href="<?= e($owner_wa) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm">
                            <i data-lucide="message-circle"></i> Chat with Owner
                          </a>
                        <?php endif; ?>
                        <?php if ($mr['allow_direct_calls']): ?>
                          <a href="tel:<?= preg_replace('/[^0-9+]/', '', $mr['phone_number'] ?: $mr['owner_user_phone']) ?>" class="btn btn-outline btn-sm">
                            <i data-lucide="phone"></i> Call Owner
                          </a>
                        <?php endif; ?>
                      </div>

                      <div>
                        <?php if (!in_array($mr['status'], ['Cancelled', 'Rejected', 'Rented or Sold'])): ?>
                          <form action="<?= BASE_URL ?>customer/property-booking-action.php" method="POST" style="margin:0;" onsubmit="return confirm('Cancel this booking request?');">
                            <input type="hidden" name="action" value="cancel_my_booking">
                            <input type="hidden" name="booking_id" value="<?= $mr['id'] ?>">
                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);">
                              <i data-lucide="slash"></i> Cancel Request
                            </button>
                          </form>
                        <?php endif; ?>
                      </div>
                    </div>

                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- TAB 4: SAVED PROPERTIES (Favorites) -->
      <div id="tab_saved_properties" class="tab-pane" style="display:<?= $current_tab === 'saved_properties' ? 'block' : 'none' ?>;">
        <div class="dashboard-card">
          <div class="card-header">
            <h3 class="card-title">Saved Properties &amp; Wishlist</h3>
            <p class="card-subtitle">Listings you bookmarked for quick review or future visits.</p>
          </div>

          <div class="card-body">
            <?php if (empty($saved_properties)): ?>
              <div style="text-align:center; padding:3.5rem 1rem;">
                <i data-lucide="heart" style="width:48px; height:48px; color:var(--text-dim); margin-bottom:1rem;"></i>
                <h4>No Saved Properties</h4>
                <p style="color:var(--text-muted); max-width:450px; margin:0 auto 1.5rem;">
                  Click the heart icon on any property in the discovery catalog to bookmark it here.
                </p>
                <a href="<?= BASE_URL ?>properties.php" class="btn btn-primary">Browse Properties</a>
              </div>
            <?php else: ?>
              <div class="property-grid" style="margin-top:0;">
                <?php foreach ($saved_properties as $prop): 
                  $wa_url = get_property_whatsapp_url($prop['whatsapp_number'], $prop['property_code'], $prop['title'], $prop['city']);
                ?>
                  <div class="property-card">
                    <div class="property-card-media" style="height:180px;">
                      <img src="<?= e($prop['primary_image'] ?: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=600&q=80') ?>" alt="Thumb">
                      <div class="property-media-overlay-top">
                        <span class="prop-id-badge"><?= e($prop['property_code']) ?></span>
                        <button type="button" class="property-save-btn active" onclick="removeSaved(<?= $prop['id'] ?>, this)" title="Remove from Saved">
                          <i data-lucide="heart" style="width:16px; height:16px; fill:currentColor;"></i>
                        </button>
                      </div>
                    </div>

                    <div class="property-card-body">
                      <div class="property-location">
                        <i data-lucide="map-pin" style="width:13px; height:13px; color:var(--primary);"></i>
                        <span><?= e($prop['city']) ?>, <?= e($prop['state']) ?></span>
                      </div>
                      <h4 class="property-title">
                        <a href="<?= BASE_URL ?>property-detail.php?id=<?= $prop['id'] ?>"><?= e($prop['title']) ?></a>
                      </h4>
                      <div class="property-price-box">
                        <span class="property-price-val"><?= format_currency($prop['price']) ?></span>
                        <span class="property-price-period"><?= $prop['listing_type'] === 'rent' ? '/mo' : '' ?></span>
                      </div>

                      <div class="property-actions-row">
                        <?php if ($wa_url): ?>
                          <a href="<?= e($wa_url) ?>" target="_blank" class="btn btn-whatsapp btn-sm">
                            <i data-lucide="message-circle"></i> WhatsApp
                          </a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>property-detail.php?id=<?= $prop['id'] ?>" class="btn btn-primary btn-sm">
                          View Dossier
                        </a>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- TAB 5: INQUIRIES & VISIT LOG -->
      <div id="tab_inquiries" class="tab-pane" style="display:<?= $current_tab === 'inquiries' ? 'block' : 'none' ?>;">
        <div class="dashboard-card">
          <div class="card-header">
            <h3 class="card-title">Property Inquiries &amp; Contact History</h3>
            <p class="card-subtitle">Audited log of WhatsApp conversations, phone calls, and visit interactions initiated.</p>
          </div>

          <div class="card-body">
            <?php if (empty($inquiries)): ?>
              <div style="text-align:center; padding:3rem 1rem;">
                <i data-lucide="message-square" style="width:40px; height:40px; color:var(--text-dim); margin-bottom:1rem;"></i>
                <h4>No Contact History Recorded</h4>
                <p style="color:var(--text-muted);">Whenever a customer clicks to chat over WhatsApp or initiates a call for your property, the activity is tracked here.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th>Property</th>
                      <th>Interaction Channel</th>
                      <th>Activity Summary</th>
                      <th>Timestamp</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($inquiries as $inq): ?>
                      <tr>
                        <td>
                          <span class="prop-id-badge" style="font-size:0.75rem;"><?= e($inq['property_code']) ?></span>
                          <strong style="margin-left:0.4rem; font-size:0.88rem;"><?= e($inq['property_title']) ?></strong>
                        </td>
                        <td>
                          <?php if ($inq['channel'] === 'WHATSAPP'): ?>
                            <span class="badge" style="background:rgba(37,211,102,0.15); color:#25D366; border:1px solid rgba(37,211,102,0.3);">
                              <i data-lucide="message-circle" style="width:12px; height:12px; vertical-align:middle;"></i> WhatsApp Chat
                            </span>
                          <?php else: ?>
                            <span class="badge badge-primary">
                              <i data-lucide="phone" style="width:12px; height:12px; vertical-align:middle;"></i> Phone Call
                            </span>
                          <?php endif; ?>
                        </td>
                        <td style="font-size:0.84rem; color:var(--text-muted);">
                          <?= e($inq['message_summary'] ?: 'Customer initiated direct conversation with owner.') ?>
                        </td>
                        <td style="font-size:0.82rem; color:var(--text-dim);">
                          <?= time_ago($inq['created_at']) ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

    </div>
  </main>
</div>

<script>
// Switch Tab Function
function switchTab(tabId) {
  // Update Buttons
  document.querySelectorAll('.prop-tab-btn').forEach(btn => btn.classList.remove('active'));
  document.querySelectorAll('.tab-pane').forEach(pane => pane.style.display = 'none');

  const activePane = document.getElementById('tab_' + tabId);
  if (activePane) {
    activePane.style.display = 'block';
  }

  // Set active class on clicked button
  event && event.currentTarget && event.currentTarget.classList.add('active');

  // Update URL history state without reloading
  history.replaceState(null, '', '<?= BASE_URL ?>customer/properties.php?tab=' + tabId);
}

// Remove saved property
function removeSaved(propId, btn) {
  fetch('<?= BASE_URL ?>api/property.php?action=toggle_save&property_id=' + propId, {
    method: 'POST'
  })
  .then(res => res.json())
  .then(data => {
    btn.closest('.property-card').remove();
  });
}
</script>

</body>
</html>
