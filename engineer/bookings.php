<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('engineer');
$user = current_user();
$engineer_id = get_engineer_id($user['id']);

$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT b.*, sr.request_code, sr.title as problem_title, sr.description as problem_description, 
                              sr.address as service_address, sr.city as service_city, 
                              s.name as service_name, cust_u.full_name as customer_name, 
                              cust_u.phone as customer_phone, cust_u.email as customer_email,
                              (SELECT id FROM appliance_diagnoses WHERE service_request_id = b.request_id OR booking_id = b.id LIMIT 1) as diag_id 
                       FROM bookings b 
                       JOIN service_requests sr ON b.request_id = sr.id 
                       JOIN services s ON b.service_id = s.id 
                       JOIN customers c ON b.customer_id = c.id 
                       JOIN users cust_u ON c.user_id = cust_u.id 
                       WHERE b.engineer_id = ? 
                       ORDER BY b.scheduled_at DESC");
$stmt->execute([$engineer_id]);
$bookings = $stmt->fetchAll();

$page_title = 'My Bookings & Operations — ' . APP_NAME;
$active_nav = 'bookings';
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
        <h2>Bookings &amp; Service Execution</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.2rem;">All Platform Bookings (<?= count($bookings) ?>)</h3>
        </div>

        <?php if (empty($bookings)): ?>
          <div style="text-align:center;padding:3rem 1rem;">
            <p style="color:var(--text-dim);">No active or historical bookings assigned to you.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Booking Code</th>
                  <th>Customer &amp; Location</th>
                  <th>Device / Fault</th>
                  <th>Appointment</th>
                  <th>Advance (20%)</th>
                  <th>Balance (80%)</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($bookings as $b): ?>
                  <tr>
                    <td><strong class="mono" style="color:var(--primary);"><?= e($b['booking_code']) ?></strong></td>
                    <td>
                      <div><strong><?= e($b['customer_name']) ?></strong></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);">
                        <i data-lucide="phone" style="width:12px;height:12px;vertical-align:middle;"></i> <?= e($b['customer_phone']) ?><br>
                        <i data-lucide="map-pin" style="width:12px;height:12px;vertical-align:middle;"></i> <?= e($b['service_city']) ?>
                      </div>
                    </td>
                    <td>
                      <div style="font-weight:600;"><?= e($b['problem_title']) ?></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($b['service_name']) ?></div>
                    </td>
                    <td><?= format_date($b['scheduled_at']) ?></td>
                    <td class="mono" style="color:var(--success);font-weight:700;"><?= format_currency($b['advance_amount']) ?></td>
                    <td class="mono"><?= format_currency($b['remaining_amount']) ?></td>
                    <td>
                      <?= render_status_badge($b['booking_status']) ?>
                      <div style="margin-top:0.25rem;">
                        <?= render_status_badge($b['payment_status']) ?>
                      </div>
                    </td>
                    <td>
                      <div style="display:flex;flex-direction:column;gap:0.4rem;">
                        <?php if (in_array($b['booking_status'], ['CONFIRMED', 'ACCEPTED_BY_ENGINEER'])): ?>
                          <button type="button" onclick="updateStatus(<?= $b['id'] ?>, 'REPAIR_IN_PROGRESS')" class="btn btn-outline btn-sm" style="color:var(--primary);border-color:var(--primary);">
                            <i data-lucide="play" style="width:13px;height:13px;"></i> Start Diagnostics
                          </button>
                        <?php elseif (in_array($b['booking_status'], ['IN_PROGRESS', 'REPAIR_IN_PROGRESS'])): ?>
                          <button type="button" onclick="openCompleteModal(<?= $b['id'] ?>)" class="btn btn-success btn-sm">
                            <i data-lucide="check-circle" style="width:13px;height:13px;"></i> Complete Repair &amp; Request OTP
                          </button>
                        <?php elseif ($b['booking_status'] === 'REPAIR_COMPLETED_AWAITING_ADMIN'): ?>
                          <span class="badge badge-warning" style="font-size:0.72rem;padding:4px 6px;">
                            <i data-lucide="clock" style="width:11px;height:11px;vertical-align:middle;"></i> Awaiting Admin OTP
                          </span>
                        <?php elseif (in_array($b['booking_status'], ['OTP_VERIFICATION_PENDING', 'OTP_SENT_TO_CUSTOMER', 'OTP_GENERATION_AUTHORIZED'])): ?>
                          <button type="button" onclick="openVerifyOtpModal(<?= $b['id'] ?>, '<?= e($b['booking_code']) ?>')" class="btn btn-primary btn-sm">
                            <i data-lucide="key" style="width:13px;height:13px;"></i> Enter Customer OTP
                          </button>
                        <?php elseif ($b['booking_status'] === 'COMPLETED_AND_VERIFIED'): ?>
                          <span class="badge badge-success" style="font-size:0.72rem;padding:4px 6px;">
                            <i data-lucide="shield-check" style="width:11px;height:11px;vertical-align:middle;"></i> Completed &amp; Verified
                          </span>
                        <?php elseif (in_array($b['booking_status'], ['VERIFICATION_FAILED', 'OTP_EXPIRED'])): ?>
                          <span class="badge badge-danger" style="font-size:0.72rem;padding:4px 6px;">
                            <i data-lucide="alert-triangle" style="width:11px;height:11px;vertical-align:middle;"></i> <?= str_replace('_', ' ', $b['booking_status']) ?>
                          </span>
                        <?php endif; ?>

                        <?php if (!empty($b['diag_id'])): ?>
                          <button type="button" onclick="openDiagnosisDossier(<?= $b['diag_id'] ?>)" class="btn btn-outline btn-sm" style="color:var(--primary);border-color:var(--primary);">
                            <i data-lucide="cpu" style="width:13px;height:13px;"></i> Diagnosis Report
                          </button>
                        <?php endif; ?>

                        <button type="button" onclick="openNotesModal(<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>)" class="btn btn-outline btn-sm">
                          Notes / Log
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

<!-- Modal: Complete Booking & Request Admin OTP -->
<div class="modal-backdrop" id="completeModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 style="font-size:1.15rem;display:flex;align-items:center;gap:0.5rem;">
        <i data-lucide="check-circle" style="color:var(--success);"></i> Complete Repair &amp; Request Admin OTP
      </h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="complete_b_id">
      <div class="form-group">
        <label class="form-label">Diagnostic Findings &amp; Replaced Components:</label>
        <textarea id="complete_notes" class="form-control" style="min-height:100px;" placeholder="e.g. Replaced 2 shorted MOSFETs, calibrated voltage regulator, passed 1-hour load test." required></textarea>
      </div>
      <div style="padding:0.75rem;background:rgba(0,240,255,0.05);border:1px solid rgba(0,240,255,0.2);border-radius:6px;font-size:0.8rem;color:var(--text-main);">
        <i data-lucide="shield" style="width:14px;height:14px;color:var(--primary);vertical-align:middle;"></i>
        Submitting will update status to <strong>Repair Completed - Awaiting Admin Authorization</strong> and notify the Super Admin to authorize customer verification OTP generation.
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="button" onclick="submitCompletion()" class="btn btn-success btn-sm">Submit Completion to Admin</button>
    </div>
  </div>
</div>

<!-- Modal: Enter Customer OTP (Engineer) -->
<div class="modal-backdrop" id="verifyOtpModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 style="font-size:1.15rem;display:flex;align-items:center;gap:0.5rem;">
        <i data-lucide="key" style="color:var(--primary);"></i> Enter Customer Verification OTP
      </h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="verify_otp_b_id">
      <p style="font-size:0.88rem;color:var(--text-muted);margin-bottom:1rem;">
        Enter the 6-digit verification code provided by the customer for booking <strong id="verify_otp_b_code" class="mono" style="color:var(--primary);"></strong>:
      </p>

      <div class="form-group" style="text-align:center;">
        <input type="text" id="entered_otp_code" class="form-control mono" maxlength="6" style="font-size:2rem;letter-spacing:8px;text-align:center;font-weight:800;color:var(--primary);max-width:240px;margin:0 auto;" placeholder="••••••" autofocus required>
        <small style="color:var(--text-dim);font-size:0.75rem;display:block;margin-top:0.4rem;">
          Obtain this code from the customer after they test and inspect the repaired hardware.
        </small>
      </div>

      <div id="otp_error_box" style="display:none;margin-top:0.75rem;padding:0.75rem;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:6px;font-size:0.82rem;color:var(--danger);"></div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="button" onclick="submitOtpVerification()" id="verifyOtpBtn" class="btn btn-primary btn-sm">
        Verify &amp; Finalize Job
      </button>
    </div>
  </div>
</div>

<!-- Modal: Smart Diagnosis Dossier Viewer -->
<div class="modal-backdrop" id="diagnosisModal">
  <div class="modal-dialog" style="max-width:680px;">
    <div class="modal-header">
      <h3 style="font-size:1.15rem;display:flex;align-items:center;gap:0.5rem;">
        <i data-lucide="cpu" style="color:var(--primary);"></i> Smart Electronics Diagnosis Report
      </h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body" id="diagnosisModalBody" style="max-height:480px;overflow-y:auto;">
      <div style="text-align:center;padding:2rem;"><i data-lucide="loader" class="spin"></i> Loading diagnosis dossier...</div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-primary btn-sm" data-modal-close>Close</button>
    </div>
  </div>
</div>

<!-- Modal: View / Edit Notes -->
<div class="modal-backdrop" id="notesModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 style="font-size:1.15rem;">Booking Service Notes</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body" id="notesBody">
      <!-- Populated via JS -->
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-primary btn-sm" data-modal-close>Close</button>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script src="<?= BASE_URL ?>assets/js/api-client.js"></script>
<script>
  async function updateStatus(bId, status, notes = '') {
    const res = await ApiClient.updateBookingStatus(bId, status, notes);
    if (res.status === 'success') {
      window.showToast(res.data.message, 'success');
      setTimeout(() => location.reload(), 900);
    } else {
      alert(res.data.message || 'Status update failed.');
    }
  }

  function openCompleteModal(bId) {
    document.getElementById('complete_b_id').value = bId;
    document.getElementById('completeModal').classList.add('show');
  }

  function submitCompletion() {
    const bId = document.getElementById('complete_b_id').value;
    const notes = document.getElementById('complete_notes').value.trim();
    if (!notes) {
      alert('Please enter your diagnostic findings and replaced parts.');
      return;
    }
    updateStatus(bId, 'REPAIR_COMPLETED_AWAITING_ADMIN', notes);
  }

  function openVerifyOtpModal(bId, code) {
    document.getElementById('verify_otp_b_id').value = bId;
    document.getElementById('verify_otp_b_code').textContent = code;
    document.getElementById('entered_otp_code').value = '';
    document.getElementById('otp_error_box').style.display = 'none';
    document.getElementById('verifyOtpModal').classList.add('show');
  }

  async function submitOtpVerification() {
    const bId = document.getElementById('verify_otp_b_id').value;
    const otp = document.getElementById('entered_otp_code').value.trim();
    const errBox = document.getElementById('otp_error_box');
    const btn = document.getElementById('verifyOtpBtn');

    if (!otp || otp.length !== 6) {
      errBox.textContent = 'Please enter a valid 6-digit numeric OTP.';
      errBox.style.display = 'block';
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Verifying...';
    errBox.style.display = 'none';

    const res = await ApiClient.verifyOtp(bId, otp);
    if (res.status === 'success') {
      window.showToast(res.data.message || 'OTP verified! Job completed and verified.', 'success');
      setTimeout(() => location.reload(), 900);
    } else {
      btn.disabled = false;
      btn.textContent = 'Verify & Finalize Job';
      errBox.textContent = res.data.message || 'OTP verification failed.';
      errBox.style.display = 'block';
    }
  }

  async function openDiagnosisDossier(diagId) {
    const body = document.getElementById('diagnosisModalBody');
    body.innerHTML = '<div style="text-align:center;padding:2rem;"><i data-lucide="loader" class="spin"></i> Loading diagnosis dossier...</div>';
    if (typeof lucide !== 'undefined') lucide.createIcons();
    document.getElementById('diagnosisModal').classList.add('show');

    try {
      const res = await fetch(`<?= BASE_URL ?>api/diagnosis.php?action=get_dossier&id=${diagId}`);
      const data = await res.json();

      if (data.status === 'success') {
        const d = data.data.dossier;
        let causesHtml = '';
        (d.possible_causes || []).forEach(c => {
          causesHtml += `<div style="padding:0.4rem 0.6rem;background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:4px;display:flex;justify-content:space-between;font-size:0.85rem;margin-bottom:0.35rem;">
            <span>${c.cause}</span><strong class="mono" style="color:var(--primary);">${c.probability}</strong>
          </div>`;
        });

        let answersHtml = '';
        for (const [qid, ans] of Object.entries(d.troubleshooting_answers || {})) {
          answersHtml += `<div style="font-size:0.82rem;padding:0.35rem 0.5rem;background:rgba(0,240,255,0.03);border-radius:4px;margin-bottom:0.25rem;">
            <strong style="color:var(--primary);">${qid}:</strong> ${ans.replace(/_/g, ' ')}
          </div>`;
        }

        body.innerHTML = `
          <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem;">
            <span class="badge badge-primary mono">DIAG #${d.id}</span>
            <span class="badge badge-secondary">${d.appliance_type}</span>
            <span class="badge badge-accent">${d.brand} ${d.model || ''}</span>
          </div>

          <div style="margin-bottom:1rem;">
            <div style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;">Primary Failure Category</div>
            <div style="font-weight:700;font-size:1rem;color:var(--primary);">${d.problem_category}</div>
          </div>

          ${d.symptoms ? `<div style="margin-bottom:1rem;">
            <div style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;">Observed Symptoms</div>
            <div style="font-size:0.88rem;color:var(--text-main);">${d.symptoms}</div>
          </div>` : ''}

          <div style="margin-bottom:1rem;">
            <div style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;">Customer Description</div>
            <div style="font-size:0.88rem;color:var(--text-muted);">${d.problem_description || 'None provided.'}</div>
          </div>

          ${causesHtml ? `<div style="margin-bottom:1rem;">
            <div style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;margin-bottom:0.35rem;">Probable Component Causes</div>
            ${causesHtml}
          </div>` : ''}

          ${answersHtml ? `<div style="margin-bottom:1rem;">
            <div style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;margin-bottom:0.35rem;">Troubleshooting Answers</div>
            ${answersHtml}
          </div>` : ''}

          ${d.media_attachment ? `<div style="margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border-glass);">
            <a href="<?= BASE_URL ?>${d.media_attachment}" target="_blank" class="btn btn-outline btn-sm">
              <i data-lucide="image"></i> View Customer Uploaded Media
            </a>
          </div>` : ''}
        `;
      } else {
        body.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-dim);">Diagnostic dossier not found.</div>';
      }
    } catch (e) {
      body.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--danger);">Failed to load diagnosis details.</div>';
    }
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  function openNotesModal(b) {
    const body = document.getElementById('notesBody');
    body.innerHTML = `
      <div style="font-size:0.9rem;">
        <div style="margin-bottom:1rem;">
          <div style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;">Hardware Issue</div>
          <div style="font-weight:700;">${b.problem_title}</div>
          <div style="color:var(--text-muted);font-size:0.85rem;margin-top:0.25rem;">${b.problem_description}</div>
        </div>
        <div style="margin-bottom:1rem;">
          <div style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;">Address</div>
          <div>${b.service_address}, ${b.service_city}</div>
        </div>
        <div>
          <div style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;">Technical Log</div>
          <pre style="background:rgba(255,255,255,0.03);padding:0.75rem;border-radius:6px;font-family:monospace;white-space:pre-wrap;font-size:0.85rem;">${b.notes || 'No log entries recorded yet.'}</pre>
        </div>
      </div>
    `;
    document.getElementById('notesModal').classList.add('show');
  }

  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
