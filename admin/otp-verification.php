<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/otp-functions.php';

require_role('admin');
$user = current_user();
$pdo = get_db_connection();

$tab = clean_input($_GET['tab'] ?? 'all');
$focus_booking_id = intval($_GET['booking_id'] ?? 0);

// Base query for OTP bookings
$sql = "SELECT b.*, sr.request_code, sr.title as problem_title, sr.description as problem_description,
               sr.address as service_address, sr.city as service_city,
               s.name as service_name, s.estimated_time,
               c.id as customer_id, cust_u.full_name as customer_name, cust_u.phone as customer_phone, cust_u.email as customer_email,
               e.id as engineer_id, eng_u.full_name as engineer_name, eng_u.phone as engineer_phone, eng_u.email as engineer_email,
               ov.id as otp_verification_id, ov.status as otp_status, ov.plain_otp_masked, ov.attempts as otp_attempts, ov.max_attempts as otp_max_attempts,
               ov.delivery_channel, ov.delivery_recipient, ov.delivery_status, ov.expires_at as otp_expires_at, ov.verified_at as otp_verified_at,
               admin_u.full_name as authorized_admin_name
        FROM bookings b
        JOIN service_requests sr ON b.request_id = sr.id
        JOIN services s ON b.service_id = s.id
        JOIN customers c ON b.customer_id = c.id
        JOIN users cust_u ON c.user_id = cust_u.id
        JOIN engineers e ON b.engineer_id = e.id
        JOIN users eng_u ON e.user_id = eng_u.id
        LEFT JOIN otp_verifications ov ON (ov.booking_id = b.id AND ov.id = (SELECT MAX(id) FROM otp_verifications WHERE booking_id = b.id))
        LEFT JOIN users admin_u ON ov.authorized_admin_id = admin_u.id";

// Tab filter logic
$where = [];
$params = [];

if ($focus_booking_id > 0) {
    $where[] = "b.id = ?";
    $params[] = $focus_booking_id;
} else {
    if ($tab === 'awaiting') {
        $where[] = "b.booking_status = 'REPAIR_COMPLETED_AWAITING_ADMIN'";
    } elseif ($tab === 'pending_verify') {
        $where[] = "b.booking_status IN ('OTP_VERIFICATION_PENDING', 'OTP_SENT_TO_CUSTOMER', 'OTP_GENERATION_AUTHORIZED')";
    } elseif ($tab === 'verified') {
        $where[] = "b.booking_status = 'COMPLETED_AND_VERIFIED'";
    } elseif ($tab === 'rejected_expired') {
        $where[] = "b.booking_status IN ('OTP_GENERATION_REJECTED', 'OTP_EXPIRED', 'VERIFICATION_FAILED')";
    } else {
        // 'all': Show all bookings that reached at least repair stage or have OTP records
        $where[] = "(b.booking_status IN ('REPAIR_COMPLETED_AWAITING_ADMIN', 'OTP_GENERATION_AUTHORIZED', 'OTP_SENT_TO_CUSTOMER', 'OTP_VERIFICATION_PENDING', 'COMPLETED_AND_VERIFIED', 'OTP_GENERATION_REJECTED', 'OTP_EXPIRED', 'VERIFICATION_FAILED') OR ov.id IS NOT NULL)";
    }
}

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY b.updated_at DESC, b.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Counts for quick tab badges
$cnt_awaiting = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'REPAIR_COMPLETED_AWAITING_ADMIN'")->fetchColumn();
$cnt_pending_verify = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status IN ('OTP_VERIFICATION_PENDING', 'OTP_SENT_TO_CUSTOMER', 'OTP_GENERATION_AUTHORIZED')")->fetchColumn();
$cnt_verified = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'COMPLETED_AND_VERIFIED'")->fetchColumn();
$cnt_rejected_expired = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status IN ('OTP_GENERATION_REJECTED', 'OTP_EXPIRED', 'VERIFICATION_FAILED')")->fetchColumn();
$cnt_all = $cnt_awaiting + $cnt_pending_verify + $cnt_verified + $cnt_rejected_expired;

$page_title = 'OTP Verification & Job Completion — ' . APP_NAME;
$active_nav = 'otp_verification';
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
        <h2>OTP Verification &amp; Job Completion</h2>
      </div>
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>admin/notifications.php" class="btn btn-outline btn-sm">
          <i data-lucide="bell"></i> Notifications
        </a>
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <?php if ($focus_booking_id > 0): ?>
        <div class="alert alert-info" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <div>
            <i data-lucide="filter"></i> Filtered by Booking ID: <strong>#<?= $focus_booking_id ?></strong>
          </div>
          <a href="<?= BASE_URL ?>admin/otp-verification.php" class="btn btn-sm btn-outline">Clear Filter</a>
        </div>
      <?php endif; ?>

      <!-- Quick Operational Metrics Grid -->
      <div class="stats-grid" style="margin-bottom:2rem;">
        <div class="stat-card" style="border-color:<?= $cnt_awaiting > 0 ? 'var(--warning)' : 'var(--border-glass)' ?>;">
          <div class="stat-info">
            <p>Awaiting Admin Authorization</p>
            <h3 style="color:<?= $cnt_awaiting > 0 ? 'var(--warning)' : 'inherit' ?>;"><?= $cnt_awaiting ?></h3>
          </div>
          <div class="stat-icon amber">
            <i data-lucide="clock"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Verification Pending</p>
            <h3><?= $cnt_pending_verify ?></h3>
          </div>
          <div class="stat-icon purple">
            <i data-lucide="key-round"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Completed &amp; Verified</p>
            <h3><?= $cnt_verified ?></h3>
          </div>
          <div class="stat-icon green">
            <i data-lucide="shield-check"></i>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-info">
            <p>Rejected / Expired / Failed</p>
            <h3><?= $cnt_rejected_expired ?></h3>
          </div>
          <div class="stat-icon">
            <i data-lucide="alert-triangle"></i>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs -->
      <div class="glass-card" style="margin-bottom:1.5rem;padding:0.75rem 1.25rem;">
        <div style="display:flex;align-items:center;gap:0.5rem;flex-wrap:wrap;">
          <a href="<?= BASE_URL ?>admin/otp-verification.php?tab=all" class="btn btn-sm <?= $tab === 'all' ? 'btn-primary' : 'btn-outline' ?>">
            All Records (<?= $cnt_all ?>)
          </a>
          <a href="<?= BASE_URL ?>admin/otp-verification.php?tab=awaiting" class="btn btn-sm <?= $tab === 'awaiting' ? 'btn-warning' : 'btn-outline' ?>">
            <i data-lucide="shield-alert" style="width:14px;height:14px;"></i> Awaiting Authorization (<?= $cnt_awaiting ?>)
          </a>
          <a href="<?= BASE_URL ?>admin/otp-verification.php?tab=pending_verify" class="btn btn-sm <?= $tab === 'pending_verify' ? 'btn-primary' : 'btn-outline' ?>">
            <i data-lucide="key" style="width:14px;height:14px;"></i> OTP Pending (<?= $cnt_pending_verify ?>)
          </a>
          <a href="<?= BASE_URL ?>admin/otp-verification.php?tab=verified" class="btn btn-sm <?= $tab === 'verified' ? 'btn-success' : 'btn-outline' ?>">
            <i data-lucide="check-circle" style="width:14px;height:14px;"></i> Successfully Verified (<?= $cnt_verified ?>)
          </a>
          <a href="<?= BASE_URL ?>admin/otp-verification.php?tab=rejected_expired" class="btn btn-sm <?= $tab === 'rejected_expired' ? 'btn-danger' : 'btn-outline' ?>">
            <i data-lucide="x-circle" style="width:14px;height:14px;"></i> Rejected / Expired (<?= $cnt_rejected_expired ?>)
          </a>
        </div>
      </div>

      <!-- Main Ledger Table -->
      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;flex-wrap:wrap;gap:1rem;">
          <h3 style="font-size:1.15rem;display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="shield-check" style="color:var(--primary);"></i>
            Repair Completion &amp; OTP Authorization Ledger
          </h3>
          <span style="font-size:0.85rem;color:var(--text-dim);">Showing <?= count($bookings) ?> record(s)</span>
        </div>

        <?php if (empty($bookings)): ?>
          <div style="text-align:center;padding:4rem 1rem;">
            <div style="font-size:3rem;margin-bottom:1rem;">🛡️</div>
            <h4>No Records in this Section</h4>
            <p style="color:var(--text-dim);font-size:0.9rem;margin-top:0.35rem;">
              When engineers complete repairs and request admin authorization, they will appear here.
            </p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Booking Code</th>
                  <th>Customer Details</th>
                  <th>Engineer Details</th>
                  <th>Repair Findings &amp; Scope</th>
                  <th>Timestamps</th>
                  <th>OTP Status</th>
                  <th>Admin Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($bookings as $b): ?>
                  <tr style="<?= ($focus_booking_id == $b['id']) ? 'background:rgba(0,240,255,0.06);' : '' ?>">
                    <td>
                      <strong class="mono" style="color:var(--primary);"><?= e($b['booking_code']) ?></strong>
                      <div style="font-size:0.75rem;color:var(--text-dim);margin-top:0.25rem;">
                        Req: <?= e($b['request_code']) ?>
                      </div>
                      <div style="margin-top:0.35rem;">
                        <?= render_status_badge($b['booking_status']) ?>
                      </div>
                    </td>

                    <td>
                      <div><strong><?= e($b['customer_name']) ?></strong></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);margin-top:0.2rem;">
                        <i data-lucide="phone" style="width:11px;height:11px;vertical-align:middle;"></i> <?= e($b['customer_phone'] ?: 'N/A') ?><br>
                        <i data-lucide="mail" style="width:11px;height:11px;vertical-align:middle;"></i> <?= e($b['customer_email']) ?><br>
                        <i data-lucide="map-pin" style="width:11px;height:11px;vertical-align:middle;"></i> <?= e($b['service_city']) ?>
                      </div>
                    </td>

                    <td>
                      <div><strong><?= e($b['engineer_name']) ?></strong></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);margin-top:0.2rem;">
                        <i data-lucide="phone" style="width:11px;height:11px;vertical-align:middle;"></i> <?= e($b['engineer_phone'] ?: 'N/A') ?><br>
                        <i data-lucide="mail" style="width:11px;height:11px;vertical-align:middle;"></i> <?= e($b['engineer_email']) ?>
                      </div>
                    </td>

                    <td>
                      <div style="font-weight:600;"><?= e($b['problem_title']) ?></div>
                      <div style="font-size:0.75rem;color:var(--primary);margin-top:0.15rem;"><?= e($b['service_name']) ?></div>
                      <?php if (!empty($b['notes'])): ?>
                        <div style="margin-top:0.4rem;padding:0.4rem 0.6rem;background:rgba(255,255,255,0.02);border-radius:4px;font-size:0.75rem;color:var(--text-muted);max-width:260px;white-space:pre-wrap;max-height:90px;overflow-y:auto;">
                          <?= e($b['notes']) ?>
                        </div>
                      <?php endif; ?>
                    </td>

                    <td style="font-size:0.78rem;">
                      <div><span style="color:var(--text-dim);">Scheduled:</span> <?= format_date($b['scheduled_at'], 'd M Y') ?></div>
                      <?php if ($b['otp_expires_at']): ?>
                        <div style="margin-top:0.25rem;">
                          <span style="color:var(--text-dim);">OTP Expires:</span> <?= format_date($b['otp_expires_at'], 'd M, h:i A') ?>
                        </div>
                      <?php endif; ?>
                      <?php if ($b['completed_at']): ?>
                        <div style="margin-top:0.25rem;color:var(--success);">
                          <span style="color:var(--text-dim);">Verified:</span> <?= format_date($b['completed_at'], 'd M, h:i A') ?>
                        </div>
                      <?php endif; ?>
                    </td>

                    <td>
                      <?php if ($b['plain_otp_masked']): ?>
                        <div class="mono" style="font-size:0.8rem;background:rgba(0,240,255,0.1);padding:2px 6px;border-radius:4px;display:inline-block;color:var(--primary);">
                          Code: <?= e($b['plain_otp_masked']) ?>
                        </div>
                        <div style="font-size:0.72rem;color:var(--text-dim);margin-top:0.25rem;">
                          Attempts: <?= $b['otp_attempts'] ?> / <?= $b['otp_max_attempts'] ?>
                        </div>
                        <?php if ($b['authorized_admin_name']): ?>
                          <div style="font-size:0.72rem;color:var(--text-dim);">
                            Auth by: <?= e($b['authorized_admin_name']) ?>
                          </div>
                        <?php endif; ?>
                      <?php else: ?>
                        <span style="font-size:0.78rem;color:var(--text-dim);">No OTP Generated</span>
                      <?php endif; ?>
                    </td>

                    <td>
                      <div style="display:flex;flex-direction:column;gap:0.35rem;min-width:140px;">
                        <?php if ($b['booking_status'] === 'REPAIR_COMPLETED_AWAITING_ADMIN'): ?>
                          <button type="button" onclick="openAuthorizeModal(<?= $b['id'] ?>, '<?= e($b['booking_code']) ?>', '<?= e(addslashes($b['customer_name'])) ?>')" class="btn btn-primary btn-sm">
                            <i data-lucide="key" style="width:13px;height:13px;"></i> Authorize &amp; Generate OTP
                          </button>
                          <button type="button" onclick="openRejectModal(<?= $b['id'] ?>, '<?= e($b['booking_code']) ?>')" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:rgba(239,68,68,0.3);">
                            <i data-lucide="x" style="width:13px;height:13px;"></i> Reject Request
                          </button>
                        <?php elseif (in_array($b['booking_status'], ['OTP_EXPIRED', 'VERIFICATION_FAILED'])): ?>
                          <button type="button" onclick="regenerateOtp(<?= $b['id'] ?>)" class="btn btn-warning btn-sm">
                            <i data-lucide="refresh-cw" style="width:13px;height:13px;"></i> Regenerate OTP
                          </button>
                        <?php elseif (in_array($b['booking_status'], ['OTP_VERIFICATION_PENDING', 'OTP_SENT_TO_CUSTOMER', 'OTP_GENERATION_AUTHORIZED'])): ?>
                          <button type="button" onclick="regenerateOtp(<?= $b['id'] ?>)" class="btn btn-outline btn-sm">
                            <i data-lucide="refresh-cw" style="width:13px;height:13px;"></i> Resend / Extend OTP
                          </button>
                        <?php endif; ?>

                        <button type="button" onclick="viewAuditLogs(<?= $b['id'] ?>, '<?= e($b['booking_code']) ?>')" class="btn btn-outline btn-sm">
                          <i data-lucide="file-text" style="width:13px;height:13px;"></i> Audit Trail
                        </button>
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
  </main>
</div>

<!-- Modal: Authorize & Generate OTP -->
<div class="modal-backdrop" id="authorizeModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 style="font-size:1.15rem;display:flex;align-items:center;gap:0.5rem;">
        <i data-lucide="key" style="color:var(--primary);"></i> Authorize &amp; Generate Customer OTP
      </h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="auth_booking_id">
      <p style="font-size:0.9rem;color:var(--text-muted);margin-bottom:1rem;">
        You are authorizing repair completion for booking <strong id="auth_booking_code" style="color:var(--primary);"></strong> with customer <strong id="auth_customer_name"></strong>.
      </p>

      <div class="form-group">
        <label class="form-label">OTP Validity Window:</label>
        <select id="auth_expiry" class="form-select">
          <option value="15" selected>15 Minutes (Standard)</option>
          <option value="30">30 Minutes</option>
          <option value="45">45 Minutes</option>
          <option value="60">60 Minutes</option>
        </select>
      </div>

      <div style="padding:0.75rem;background:rgba(0,240,255,0.05);border:1px solid rgba(0,240,255,0.2);border-radius:6px;font-size:0.8rem;color:var(--text-main);">
        <i data-lucide="info" style="width:14px;height:14px;color:var(--primary);vertical-align:middle;"></i>
        A cryptographic 6-digit OTP will be generated and dispatched exclusively to the customer's portal and registered contacts. The engineer will not be able to view this OTP.
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="button" onclick="submitAuthorization()" id="authSubmitBtn" class="btn btn-primary btn-sm">
        Authorize &amp; Dispatch OTP
      </button>
    </div>
  </div>
</div>

<!-- Modal: Reject Completion Request -->
<div class="modal-backdrop" id="rejectModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 style="font-size:1.15rem;display:flex;align-items:center;gap:0.5rem;color:var(--danger);">
        <i data-lucide="x-circle"></i> Reject Completion Request
      </h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="reject_booking_id">
      <p style="font-size:0.88rem;color:var(--text-muted);margin-bottom:1rem;">
        Rejecting this completion request will pause the verification workflow and notify both the engineer and customer.
      </p>
      <div class="form-group">
        <label class="form-label">Reason for Rejection / Corrective Action Required:</label>
        <textarea id="reject_reason" class="form-control" style="min-height:90px;" placeholder="e.g. Diagnostic photos missing, component load test report required, or warranty seal inspection needed..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="button" onclick="submitRejection()" id="rejectSubmitBtn" class="btn btn-danger btn-sm">
        Confirm Rejection
      </button>
    </div>
  </div>
</div>

<!-- Modal: Audit Trail Viewer -->
<div class="modal-backdrop" id="auditModal">
  <div class="modal-dialog" style="max-width:680px;">
    <div class="modal-header">
      <h3 style="font-size:1.15rem;display:flex;align-items:center;gap:0.5rem;">
        <i data-lucide="file-text" style="color:var(--primary);"></i>
        Verification Audit Trail — <span id="auditBookingCode" class="mono" style="color:var(--primary);"></span>
      </h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body" id="auditBody" style="max-height:450px;overflow-y:auto;">
      <div style="text-align:center;padding:2rem;">
        <i data-lucide="loader" class="spin"></i> Loading audit logs...
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-primary btn-sm" data-modal-close>Close</button>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script src="<?= BASE_URL ?>assets/js/api-client.js"></script>
<script>
  function openAuthorizeModal(bookingId, bookingCode, customerName) {
    document.getElementById('auth_booking_id').value = bookingId;
    document.getElementById('auth_booking_code').textContent = bookingCode;
    document.getElementById('auth_customer_name').textContent = customerName;
    document.getElementById('authorizeModal').classList.add('show');
  }

  async function submitAuthorization() {
    const bookingId = document.getElementById('auth_booking_id').value;
    const expiry = parseInt(document.getElementById('auth_expiry').value) || 15;
    const btn = document.getElementById('authSubmitBtn');

    btn.disabled = true;
    btn.textContent = 'Generating & Dispatching...';

    const res = await ApiClient.authorizeOtp(bookingId, expiry);
    if (res.status === 'success') {
      window.showToast(res.data.message || 'OTP authorized and dispatched!', 'success');
      setTimeout(() => location.reload(), 900);
    } else {
      btn.disabled = false;
      btn.textContent = 'Authorize & Dispatch OTP';
      alert(res.data.message || 'Authorization failed.');
    }
  }

  function openRejectModal(bookingId, bookingCode) {
    document.getElementById('reject_booking_id').value = bookingId;
    document.getElementById('rejectModal').classList.add('show');
  }

  async function submitRejection() {
    const bookingId = document.getElementById('reject_booking_id').value;
    const reason = document.getElementById('reject_reason').value.trim();
    const btn = document.getElementById('rejectSubmitBtn');

    btn.disabled = true;
    btn.textContent = 'Rejecting...';

    const res = await ApiClient.rejectOtp(bookingId, reason);
    if (res.status === 'success') {
      window.showToast('Completion request rejected.', 'info');
      setTimeout(() => location.reload(), 900);
    } else {
      btn.disabled = false;
      btn.textContent = 'Confirm Rejection';
      alert(res.data.message || 'Rejection failed.');
    }
  }

  async function regenerateOtp(bookingId) {
    if (!confirm('Regenerate and dispatch a fresh OTP for this booking?')) return;
    const res = await ApiClient.regenerateOtp(bookingId, 15);
    if (res.status === 'success') {
      window.showToast(res.data.message || 'Fresh OTP dispatched to customer!', 'success');
      setTimeout(() => location.reload(), 900);
    } else {
      alert(res.data.message || 'Regeneration failed.');
    }
  }

  async function viewAuditLogs(bookingId, bookingCode) {
    document.getElementById('auditBookingCode').textContent = bookingCode;
    const body = document.getElementById('auditBody');
    body.innerHTML = '<div style="text-align:center;padding:2rem;"><i data-lucide="loader" class="spin"></i> Loading audit logs...</div>';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    document.getElementById('auditModal').classList.add('show');

    const res = await ApiClient.getOtpAuditLogs(bookingId);
    if (res.status === 'success' && res.data.logs && res.data.logs.length > 0) {
      let html = '<div style="display:flex;flex-direction:column;gap:0.75rem;">';
      res.data.logs.forEach(log => {
        let badgeColor = 'primary';
        if (log.action.includes('REJECT') || log.action.includes('FAILED')) badgeColor = 'danger';
        else if (log.action.includes('VERIFIED')) badgeColor = 'success';
        else if (log.action.includes('EXPIRED') || log.action.includes('COMPLETED')) badgeColor = 'warning';

        html += `
          <div style="padding:0.85rem;background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:6px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.35rem;">
              <span class="badge badge-${badgeColor}" style="font-size:0.72rem;">${log.action}</span>
              <span style="font-size:0.75rem;color:var(--text-dim);">${log.created_at}</span>
            </div>
            <div style="font-size:0.85rem;color:var(--text-main);">${log.details || ''}</div>
            <div style="font-size:0.75rem;color:var(--text-dim);margin-top:0.3rem;">
              Actor: <strong>${log.actor_name || log.actor_role}</strong> (${log.actor_role}) &bull; IP: ${log.ip_address || '127.0.0.1'}
            </div>
          </div>
        `;
      });
      html += '</div>';
      body.innerHTML = html;
    } else {
      body.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-dim);">No audit logs recorded for this booking yet.</div>';
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
