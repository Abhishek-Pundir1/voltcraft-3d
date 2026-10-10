<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/diagnosis-engine.php';

require_role('engineer');
$user = current_user();
$engineer_id = get_engineer_id($user['id']);

$pdo = get_db_connection();
$request_id = intval($_GET['id'] ?? 0);

if (!$request_id) {
    header("Location: " . BASE_URL . "engineer/requests.php");
    exit;
}

$stmt = $pdo->prepare("SELECT sr.*, s.name as service_name, s.estimated_time, 
                              c.id as customer_id, cust_u.full_name as customer_name, 
                              cust_u.email as customer_email, cust_u.phone as customer_phone, cust_u.avatar as customer_avatar 
                       FROM service_requests sr 
                       JOIN services s ON sr.service_id = s.id 
                       JOIN customers c ON sr.customer_id = c.id 
                       JOIN users cust_u ON c.user_id = cust_u.id 
                       WHERE sr.id = ? AND sr.engineer_id = ?");
$stmt->execute([$request_id, $engineer_id]);
$req = $stmt->fetch();

if (!$req) {
    echo "Request not found or access denied.";
    exit;
}

// Fetch any linked Smart Diagnosis
$diag_stmt = $pdo->prepare("SELECT * FROM appliance_diagnoses WHERE service_request_id = ? OR id = ? LIMIT 1");
$diag_stmt->execute([$request_id, $req['diagnosis_id'] ?? 0]);
$diag = $diag_stmt->fetch();
$diag_causes = $diag ? (json_decode($diag['possible_causes'] ?? '[]', true) ?: []) : [];
$diag_answers = $diag ? (json_decode($diag['troubleshooting_answers'] ?? '[]', true) ?: []) : [];

$page_title = 'Request Details ' . e($req['request_code']) . ' — ' . APP_NAME;
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
        <h2>Service Request <?= e($req['request_code']) ?></h2>
      </div>
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>engineer/requests.php" class="btn btn-outline btn-sm">
          <i data-lucide="arrow-left"></i> All Requests
        </a>
      </div>
    </header>

    <div class="dashboard-content" style="max-width:900px;">
      <div class="glass-card" style="margin-bottom:2rem;border-color:var(--border-glow);">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
          <div>
            <div style="display:flex;align-items:center;gap:0.75rem;">
              <span class="mono" style="font-weight:700;color:var(--primary);"><?= e($req['request_code']) ?></span>
              <span class="badge badge-secondary"><?= e($req['service_name']) ?></span>
              <?= render_status_badge($req['status']) ?>
            </div>
            <h2 style="font-size:1.5rem;margin-top:0.5rem;"><?= e($req['title']) ?></h2>
            <p style="font-size:0.85rem;color:var(--text-dim);">
              Received on <?= format_date($req['created_at']) ?> &bull; Preferred Date: <?= format_date($req['preferred_date'], 'd M Y') ?> (<?= e($req['preferred_time']) ?>)
            </p>
          </div>

          <div style="text-align:right;">
            <div style="font-size:0.8rem;color:var(--text-dim);text-transform:uppercase;">Estimated Value</div>
            <div style="font-size:1.8rem;font-weight:800;font-family:var(--font-mono);color:var(--text-main);">
              <?= format_currency($req['estimated_amount']) ?>
            </div>
            <div style="font-size:0.75rem;color:var(--warning);">
              20% Advance: <?= format_currency($req['estimated_amount'] * ADVANCE_PERCENTAGE) ?>
            </div>
          </div>
        </div>
      </div>

      <div style="display:grid;grid-template-columns:1.3fr 1fr;gap:2rem;">
        <div>
          <div class="glass-card">
          <h3 style="font-size:1.15rem;margin-bottom:1rem;">Fault Description &amp; Scope</h3>
          <p style="font-size:0.92rem;line-height:1.7;color:var(--text-muted);white-space:pre-line;">
            <?= e($req['description']) ?>
          </p>

          <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--border-glass);">
            <h4 style="font-size:0.85rem;color:var(--text-dim);text-transform:uppercase;margin-bottom:0.4rem;">Service Location</h4>
            <p style="font-size:0.92rem;">
              <i data-lucide="map-pin" style="width:16px;height:16px;color:var(--primary);vertical-align:middle;"></i>
              <?= e($req['address']) ?>, <?= e($req['city']) ?>
            </p>
          </div>

          <?php if (!empty($req['attachment'])): ?>
            <div style="margin-top:1.25rem;">
              <h4 style="font-size:0.85rem;color:var(--text-dim);text-transform:uppercase;margin-bottom:0.4rem;">Attached Board Schematic / Image</h4>
              <a href="<?= BASE_URL . ltrim($req['attachment'], '/') ?>" target="_blank" class="btn btn-outline btn-sm">
                <i data-lucide="paperclip"></i> View Uploaded File
              </a>
            </div>
          <?php endif; ?>
        </div>

        <?php if ($diag): ?>
          <!-- Smart Diagnosis Dossier for Engineer -->
          <div class="glass-card" style="border-color:var(--primary);margin-top:1.5rem;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
              <div style="display:flex;align-items:center;gap:0.5rem;">
                <span class="badge badge-primary mono"><i data-lucide="cpu" style="width:13px;height:13px;"></i> SMART DIAGNOSIS DOSSIER</span>
                <span class="badge badge-secondary"><?= e($diag['appliance_type']) ?></span>
                <span class="badge badge-accent"><?= e($diag['brand']) ?> <?= e($diag['model'] ?: '') ?></span>
              </div>
            </div>

            <h4 style="font-size:1.1rem;margin-bottom:0.75rem;color:var(--primary);">
              Category: <?= e($diag['problem_category']) ?>
            </h4>

            <?php if (!empty($diag['symptoms'])): ?>
              <div style="margin-bottom:1rem;">
                <span style="font-size:0.78rem;color:var(--text-dim);text-transform:uppercase;font-weight:700;">Observed Symptoms:</span>
                <div style="font-size:0.9rem;color:var(--text-main);margin-top:0.25rem;"><?= e($diag['symptoms']) ?></div>
              </div>
            <?php endif; ?>

            <?php if (!empty($diag_causes)): ?>
              <div style="margin-bottom:1rem;">
                <span style="font-size:0.78rem;color:var(--text-dim);text-transform:uppercase;font-weight:700;">Algorithmic Component Probability:</span>
                <div style="display:flex;flex-direction:column;gap:0.4rem;margin-top:0.35rem;">
                  <?php foreach ($diag_causes as $c): ?>
                    <div style="padding:0.5rem 0.75rem;background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:4px;display:flex;justify-content:space-between;align-items:center;font-size:0.85rem;">
                      <span><?= e($c['cause']) ?></span>
                      <strong class="mono" style="color:var(--primary);"><?= e($c['probability']) ?></strong>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($diag_answers)): ?>
              <div style="margin-bottom:1rem;">
                <span style="font-size:0.78rem;color:var(--text-dim);text-transform:uppercase;font-weight:700;">Customer Troubleshooting Answers:</span>
                <div style="display:flex;flex-direction:column;gap:0.35rem;margin-top:0.35rem;">
                  <?php foreach ($diag_answers as $qid => $ans): ?>
                    <div style="font-size:0.82rem;padding:0.4rem 0.6rem;background:rgba(0,240,255,0.03);border-radius:4px;color:var(--text-muted);">
                      <span class="mono" style="color:var(--primary);"><?= e($qid) ?>:</span> <?= e(str_replace('_', ' ', $ans)) ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if (!empty($diag['media_attachment'])): ?>
              <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--border-glass);">
                <a href="<?= BASE_URL . ltrim($diag['media_attachment'], '/') ?>" target="_blank" class="btn btn-outline btn-sm">
                  <i data-lucide="image"></i> View Customer Uploaded Fault Media
                </a>
              </div>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        </div>

        <div style="display:flex;flex-direction:column;gap:1.5rem;">
          <div class="glass-card">
            <h3 style="font-size:1.15rem;margin-bottom:1rem;">Client Information</h3>
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
              <img src="<?= get_avatar_url($req['customer_avatar'], $req['customer_name']) ?>" alt="Avatar" style="width:52px;height:52px;border-radius:50%;">
              <div>
                <h4 style="font-size:1.05rem;"><?= e($req['customer_name']) ?></h4>
                <div style="font-size:0.82rem;color:var(--text-dim);"><?= e($req['customer_phone']) ?></div>
                <div style="font-size:0.82rem;color:var(--text-dim);"><?= e($req['customer_email']) ?></div>
              </div>
            </div>
          </div>

          <?php if ($req['status'] === 'PENDING'): ?>
            <div class="glass-card" style="text-align:center;">
              <h4 style="font-size:1rem;margin-bottom:0.75rem;">Respond to Request</h4>
              <p style="font-size:0.85rem;color:var(--text-dim);margin-bottom:1.25rem;">
                Accepting prompts the client to deposit the 20% advance booking fee.
              </p>
              <div style="display:flex;gap:0.75rem;">
                <button type="button" onclick="respondRequest(<?= $req['id'] ?>, 'ACCEPT')" class="btn btn-primary btn-block">
                  <i data-lucide="check"></i> Accept Request
                </button>
                <button type="button" onclick="respondRequest(<?= $req['id'] ?>, 'REJECT')" class="btn btn-danger btn-block">
                  <i data-lucide="x"></i> Decline
                </button>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
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

  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
