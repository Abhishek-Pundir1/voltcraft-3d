<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('engineer');
$user = current_user();
$engineer_id = get_engineer_id($user['id']);

$pdo = get_db_connection();

$stmt = $pdo->prepare("SELECT sr.*, s.name as service_name, cust_u.full_name as customer_name, 
                              cust_u.email as customer_email, cust_u.phone as customer_phone 
                       FROM service_requests sr 
                       JOIN services s ON sr.service_id = s.id 
                       JOIN customers c ON sr.customer_id = c.id 
                       JOIN users cust_u ON c.user_id = cust_u.id 
                       WHERE sr.engineer_id = ? 
                       ORDER BY sr.created_at DESC");
$stmt->execute([$engineer_id]);
$requests = $stmt->fetchAll();

$page_title = 'Incoming Service Requests — ' . APP_NAME;
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
  <?php require_once __DIR__ . '/../includes/engineer-sidebar.php'; ?>

  <main class="dashboard-main">
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn"><i data-lucide="menu"></i></button>
        <h2>Diagnostic Service Requests</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.2rem;">All Received Diagnostic Requests (<?= count($requests) ?>)</h3>
        </div>

        <?php if (empty($requests)): ?>
          <div style="text-align:center;padding:3rem 1rem;">
            <p style="color:var(--text-dim);">No requests received yet.</p>
          </div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Request ID</th>
                  <th>Client</th>
                  <th>Fault Scope</th>
                  <th>Service</th>
                  <th>Scheduled For</th>
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
                      <div><strong><?= e($r['customer_name']) ?></strong></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($r['customer_phone']) ?> &bull; <?= e($r['city']) ?></div>
                    </td>
                    <td>
                      <div style="font-weight:600;"><?= e($r['title']) ?></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                        <?= e($r['description']) ?>
                      </div>
                    </td>
                    <td><?= e($r['service_name']) ?></td>
                    <td>
                      <div><?= format_date($r['preferred_date'], 'd M Y') ?></div>
                      <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($r['preferred_time']) ?></div>
                    </td>
                    <td class="mono"><?= format_currency($r['estimated_amount']) ?></td>
                    <td><?= render_status_badge($r['status']) ?></td>
                    <td>
                      <?php if ($r['status'] === 'PENDING'): ?>
                        <div style="display:flex;flex-direction:column;gap:0.35rem;">
                          <div style="display:flex;gap:0.3rem;">
                            <button type="button" onclick="respondRequest(<?= $r['id'] ?>, 'ACCEPT')" class="btn btn-primary btn-sm">
                              Accept
                            </button>
                            <button type="button" onclick="openRejectModal(<?= $r['id'] ?>)" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:rgba(239,68,68,0.3);">
                              Decline
                            </button>
                          </div>
                          <a href="<?= BASE_URL ?>engineer/request-details.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.25rem 0.5rem;">
                            <i data-lucide="eye" style="width:12px;height:12px;"></i> View Details
                          </a>
                        </div>
                      <?php elseif ($r['status'] === 'ACCEPTED'): ?>
                        <div style="display:flex;flex-direction:column;gap:0.35rem;">
                          <span style="font-size:0.8rem;color:var(--warning);">Awaiting 20% advance</span>
                          <a href="<?= BASE_URL ?>engineer/request-details.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.25rem 0.5rem;">
                            <i data-lucide="eye" style="width:12px;height:12px;"></i> View Details
                          </a>
                        </div>
                      <?php else: ?>
                        <a href="<?= BASE_URL ?>engineer/request-details.php?id=<?= $r['id'] ?>" class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.25rem 0.5rem;">
                          <i data-lucide="eye" style="width:12px;height:12px;"></i> View Details
                        </a>
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

<!-- Reject Modal -->
<div class="modal-backdrop" id="rejectModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 style="font-size:1.15rem;">Decline Service Request</h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="reject_req_id">
      <div class="form-group">
        <label class="form-label">Decline Reason:</label>
        <textarea id="reject_reason" class="form-control" placeholder="Explain scheduling conflict or component unavailability..."></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="button" onclick="submitRejection()" class="btn btn-danger btn-sm">Submit Decline</button>
    </div>
  </div>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script src="<?= BASE_URL ?>assets/js/api-client.js"></script>
<script>
  async function respondRequest(reqId, action, reason = '') {
    const res = await ApiClient.respondToRequest(reqId, action, reason);
    if (res.status === 'success') {
      window.showToast(res.data.message, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      alert(res.data.message || 'Action failed.');
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
