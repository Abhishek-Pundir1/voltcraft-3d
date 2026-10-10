<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/otp-functions.php';
require_once __DIR__ . '/../includes/diagnosis-engine.php';

require_role('customer');
$user = current_user();
$customer_id = get_customer_id($user['id']);

$pdo = get_db_connection();
$booking_id = intval($_GET['id'] ?? 0);

if (!$booking_id) {
    header("Location: " . BASE_URL . "customer/dashboard.php");
    exit;
}

// Fetch booking details
$stmt = $pdo->prepare("SELECT b.*, sr.request_code, sr.title as problem_title, sr.description as problem_description,
                              sr.address as service_address, sr.city as service_city, sr.attachment,
                              s.name as service_name, s.estimated_time,
                              e.id as engineer_id, e.hourly_rate, e.upi_id,
                              eng_u.full_name as engineer_name, eng_u.email as engineer_email, eng_u.phone as engineer_phone, eng_u.avatar as engineer_avatar
                       FROM bookings b 
                       JOIN service_requests sr ON b.request_id = sr.id 
                       JOIN services s ON b.service_id = s.id 
                       JOIN engineers e ON b.engineer_id = e.id 
                       JOIN users eng_u ON e.user_id = eng_u.id 
                       WHERE b.id = ? AND b.customer_id = ?");
$stmt->execute([$booking_id, $customer_id]);
$booking = $stmt->fetch();

if (!$booking) {
    echo "Booking not found or unauthorized.";
    exit;
}

// Check for active confidential OTP for this customer
$active_otp = get_active_otp_for_customer($booking_id, $user['id']);

// Check for linked Smart Electronics Diagnosis
$diag_stmt = $pdo->prepare("SELECT * FROM appliance_diagnoses WHERE booking_id = ? OR service_request_id = ? LIMIT 1");
$diag_stmt->execute([$booking_id, $booking['request_id']]);
$diag = $diag_stmt->fetch();
$diag_causes = $diag ? (json_decode($diag['possible_causes'] ?? '[]', true) ?: []) : [];

// Fetch payments for this booking
$pay_stmt = $pdo->prepare("SELECT * FROM payments WHERE booking_id = ? ORDER BY paid_at DESC");
$pay_stmt->execute([$booking_id]);
$payments = $pay_stmt->fetchAll();

// Handle Review Submission
$review_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_review') {
    $rating = intval($_POST['rating'] ?? 5);
    $comment = clean_input($_POST['comment'] ?? '');

    if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
        // Check if already reviewed
        $rev_chk = $pdo->prepare("SELECT id FROM reviews WHERE booking_id = ?");
        $rev_chk->execute([$booking_id]);
        if (!$rev_chk->fetch()) {
            $ins_rev = $pdo->prepare("INSERT INTO reviews (booking_id, customer_id, engineer_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
            $ins_rev->execute([$booking_id, $customer_id, $booking['engineer_id'], $rating, $comment]);

            // Update engineer average rating
            $calc = $pdo->prepare("SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM reviews WHERE engineer_id = ?");
            $calc->execute([$booking['engineer_id']]);
            $stats = $calc->fetch();
            $pdo->prepare("UPDATE engineers SET rating = ?, total_reviews = ? WHERE id = ?")
                ->execute([round($stats['avg_r'], 2), $stats['cnt'], $booking['engineer_id']]);

            $review_success = 'Thank you! Your verified review has been published.';
        }
    }
}

// Check existing review
$existing_review_stmt = $pdo->prepare("SELECT * FROM reviews WHERE booking_id = ?");
$existing_review_stmt->execute([$booking_id]);
$existing_review = $existing_review_stmt->fetch();

$page_title = 'Booking ' . e($booking['booking_code']) . ' — ' . APP_NAME;
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
  <?php require_once __DIR__ . '/../includes/customer-sidebar.php'; ?>

  <main class="dashboard-main">
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn"><i data-lucide="menu"></i></button>
        <h2>Booking Dossier <?= e($booking['booking_code']) ?></h2>
      </div>
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>customer/requests.php" class="btn btn-outline btn-sm">
          <i data-lucide="arrow-left"></i> My Requests
        </a>
      </div>
    </header>

    <div class="dashboard-content" style="max-width:1050px;">
      <?php if ($review_success): ?>
        <div class="alert alert-success">
          <i data-lucide="check-circle"></i> <?= e($review_success) ?>
        </div>
      <?php endif; ?>

      <!-- Header Overview Card -->
      <div class="glass-card" style="margin-bottom:2rem;border-color:var(--border-glow);">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1.5rem;">
          <div>
            <div style="display:flex;align-items:center;gap:0.75rem;">
              <span class="badge badge-primary mono"><?= e($booking['booking_code']) ?></span>
              <span class="badge badge-secondary"><?= e($booking['service_name']) ?></span>
              <?= render_status_badge($booking['booking_status']) ?>
            </div>
            <h2 style="font-size:1.6rem;margin-top:0.6rem;"><?= e($booking['problem_title']) ?></h2>
            <p style="font-size:0.88rem;color:var(--text-dim);margin-top:0.25rem;">
              Created on <?= format_date($booking['created_at']) ?> &bull; Scheduled for <?= format_date($booking['scheduled_at']) ?>
            </p>
          </div>

          <div style="text-align:right;">
            <div style="font-size:0.8rem;color:var(--text-dim);text-transform:uppercase;">Total Service Value</div>
            <div style="font-size:2rem;font-weight:800;font-family:var(--font-mono);color:var(--primary);">
              <?= format_currency($booking['total_amount']) ?>
            </div>
            <div style="margin-top:0.25rem;">
              <?= render_status_badge($booking['payment_status']) ?>
            </div>
          </div>
        </div>

        <!-- 5-Stage Status Tracker -->
        <div class="status-tracker">
          <div class="tracker-step completed">
            <div class="tracker-node"><i data-lucide="check" style="width:16px;height:16px;"></i></div>
            <span class="tracker-label">Request Accepted</span>
          </div>

          <div class="tracker-step completed">
            <div class="tracker-node"><i data-lucide="check" style="width:16px;height:16px;"></i></div>
            <span class="tracker-label">20% Advance Escrow (<?= format_currency($booking['advance_amount']) ?>)</span>
          </div>

          <div class="tracker-step <?= in_array($booking['booking_status'], ['CONFIRMED', 'IN_PROGRESS', 'COMPLETED']) ? 'completed' : 'active' ?>">
            <div class="tracker-node">3</div>
            <span class="tracker-label">Booking Confirmed</span>
          </div>

          <div class="tracker-step <?= in_array($booking['booking_status'], ['IN_PROGRESS', 'COMPLETED']) ? ($booking['booking_status'] === 'COMPLETED' ? 'completed' : 'active') : '' ?>">
            <div class="tracker-node">4</div>
            <span class="tracker-label">Diagnostics &amp; Repair</span>
          </div>

          <div class="tracker-step <?= $booking['booking_status'] === 'COMPLETED' ? 'completed' : '' ?>">
            <div class="tracker-node">5</div>
            <span class="tracker-label">Completed &amp; Settled</span>
          </div>
        </div>
      </div>

      <!-- Two Column Layout: Details and Financials -->
      <div style="display:grid;grid-template-columns:1.2fr 1fr;gap:2rem;align-items:start;">
        <!-- Left: Hardware Scope & Engineer Info -->
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
          <div class="glass-card">
            <h3 style="font-size:1.15rem;margin-bottom:1rem;">Problem Description &amp; Fault Scope</h3>
            <p style="font-size:0.92rem;line-height:1.65;color:var(--text-muted);white-space:pre-line;">
              <?= e($booking['problem_description']) ?>
            </p>

            <div style="margin-top:1.5rem;padding-top:1.25rem;border-top:1px solid var(--border-glass);">
              <h4 style="font-size:0.9rem;color:var(--text-dim);text-transform:uppercase;margin-bottom:0.5rem;">Service Location</h4>
              <p style="font-size:0.92rem;color:var(--text-main);">
                <i data-lucide="map-pin" style="width:16px;height:16px;color:var(--primary);vertical-align:middle;"></i>
                <?= e($booking['service_address']) ?>, <?= e($booking['service_city']) ?>
              </p>
            </div>

            <?php if (!empty($booking['notes'])): ?>
              <div style="margin-top:1.25rem;padding:1rem;background:rgba(255,255,255,0.03);border-radius:var(--radius-sm);border-left:3px solid var(--primary);">
                <div style="font-size:0.8rem;color:var(--primary);font-weight:700;">ENGINEER SERVICE LOG:</div>
                <p style="font-size:0.88rem;color:var(--text-main);margin-top:0.25rem;white-space:pre-line;"><?= e($booking['notes']) ?></p>
              </div>
            <?php endif; ?>
          </div>

          <?php if ($active_otp): ?>
            <!-- Confidential Customer Completion OTP -->
            <div class="glass-card" style="border-color:var(--primary);background:rgba(0,240,255,0.06);box-shadow:0 0 25px rgba(0,240,255,0.15);">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;">
                <span class="badge badge-primary"><i data-lucide="key" style="width:13px;height:13px;"></i> VERIFICATION OTP CODE</span>
                <span style="font-size:0.75rem;color:var(--warning);">Expires: <?= format_date($active_otp['expires_at'], 'h:i A') ?></span>
              </div>
              <div style="text-align:center;padding:0.75rem 0;">
                <div style="font-size:0.78rem;color:var(--text-dim);text-transform:uppercase;letter-spacing:1px;">Your 6-Digit Completion Code</div>
                <div class="mono" style="font-size:2.4rem;font-weight:900;letter-spacing:8px;color:var(--primary);margin:0.4rem 0;">
                  <?= e($active_otp['code']) ?>
                </div>
                <p style="font-size:0.82rem;color:var(--text-muted);max-width:440px;margin:0 auto;line-height:1.45;">
                  ⚠️ <strong>IMPORTANT:</strong> Only share this code with engineer <strong><?= e($booking['engineer_name']) ?></strong> after you have inspected and confirmed that your repair was completed satisfactorily.
                </p>
              </div>
            </div>
          <?php endif; ?>

          <?php if ($diag): ?>
            <!-- Smart Electronics Diagnosis Dossier -->
            <div class="glass-card" style="border-color:var(--border-glow);">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem;flex-wrap:wrap;gap:0.5rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                  <span class="badge badge-primary mono"><i data-lucide="cpu" style="width:13px;height:13px;"></i> SMART DIAGNOSIS</span>
                  <span class="badge badge-secondary"><?= e($diag['appliance_type']) ?></span>
                  <span class="badge badge-accent"><?= e($diag['brand']) ?> <?= e($diag['model'] ?: '') ?></span>
                </div>
              </div>

              <h4 style="font-size:1.05rem;margin-bottom:0.5rem;color:var(--primary);">
                Category: <?= e($diag['problem_category']) ?>
              </h4>

              <?php if (!empty($diag['symptoms'])): ?>
                <div style="margin-bottom:0.75rem;">
                  <span style="font-size:0.78rem;color:var(--text-dim);text-transform:uppercase;font-weight:700;">Reported Symptoms:</span>
                  <div style="font-size:0.88rem;color:var(--text-main);margin-top:0.2rem;"><?= e($diag['symptoms']) ?></div>
                </div>
              <?php endif; ?>

              <?php if (!empty($diag_causes)): ?>
                <div style="margin-bottom:0.75rem;">
                  <span style="font-size:0.78rem;color:var(--text-dim);text-transform:uppercase;font-weight:700;">Probable Component Causes:</span>
                  <div style="display:flex;flex-direction:column;gap:0.35rem;margin-top:0.35rem;">
                    <?php foreach ($diag_causes as $c): ?>
                      <div style="padding:0.4rem 0.6rem;background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:4px;display:flex;justify-content:space-between;align-items:center;font-size:0.82rem;">
                        <span><?= e($c['cause']) ?></span>
                        <strong class="mono" style="color:var(--primary);"><?= e($c['probability']) ?></strong>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endif; ?>

              <?php if (!empty($diag['media_attachment'])): ?>
                <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--border-glass);">
                  <a href="<?= BASE_URL . ltrim($diag['media_attachment'], '/') ?>" target="_blank" class="btn btn-outline btn-sm">
                    <i data-lucide="image"></i> View Uploaded Diagnostic Media
                  </a>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <!-- Assigned Engineer Card -->
          <div class="glass-card">
            <h3 style="font-size:1.15rem;margin-bottom:1.25rem;">Assigned Electronics Engineer</h3>
            <div style="display:flex;align-items:center;gap:1rem;">
              <img src="<?= get_avatar_url($booking['engineer_avatar'], $booking['engineer_name']) ?>" alt="Engineer" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);">
              <div>
                <h4 style="font-size:1.1rem;"><?= e($booking['engineer_name']) ?></h4>
                <div style="font-size:0.85rem;color:var(--primary);margin-top:0.15rem;">Verified Hardware Specialist</div>
                <div style="font-size:0.82rem;color:var(--text-dim);margin-top:0.35rem;">
                  <i data-lucide="phone" style="width:12px;height:12px;vertical-align:middle;"></i> <?= e($booking['engineer_phone'] ?: '+91 98000 12345') ?> &bull; 
                  <i data-lucide="mail" style="width:12px;height:12px;vertical-align:middle;"></i> <?= e($booking['engineer_email']) ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Advance / Settlement Ledger & Review -->
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
          <div class="glass-card">
            <h3 style="font-size:1.15rem;margin-bottom:1rem;">Financial Escrow Breakdown</h3>

            <div class="payment-split-box" style="grid-template-columns:1fr;gap:0.75rem;padding:1rem;">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <span class="split-label">Total Agreed Amount</span>
                <span class="split-value" style="font-size:1.2rem;"><?= format_currency($booking['total_amount']) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;color:var(--success);">
                <span class="split-label" style="color:inherit;">20% Advance (Deposited)</span>
                <span class="split-value highlight" style="font-size:1.2rem;color:var(--success);"><?= format_currency($booking['advance_amount']) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;align-items:center;color:var(--warning);">
                <span class="split-label" style="color:inherit;">80% Balance Pending</span>
                <span class="split-value" style="font-size:1.2rem;color:var(--warning);"><?= format_currency($booking['remaining_amount']) ?></span>
              </div>
            </div>

            <!-- Settlement Trigger if completed -->
            <?php if (in_array($booking['booking_status'], ['COMPLETED', 'COMPLETED_AND_VERIFIED']) && $booking['payment_status'] !== 'FULLY_PAID'): ?>
              <div style="margin-top:1.5rem;padding:1.25rem;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-sm);">
                <h4 style="color:var(--success);margin-bottom:0.4rem;font-size:1rem;">Service Completed &amp; Verified!</h4>
                <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:1rem;">
                  Your hardware diagnostics have passed and OTP verification succeeded. Settle the remaining 80% balance (<?= format_currency($booking['remaining_amount']) ?>) to conclude this contract.
                </p>
                <button type="button" onclick="settleBalance(<?= $booking['id'] ?>)" id="settleBalanceBtn" class="btn btn-success btn-block btn-sm">
                  <i data-lucide="credit-card"></i> Pay Remaining 80% Balance
                </button>
              </div>
            <?php endif; ?>

            <!-- Transactions Log -->
            <h4 style="font-size:0.95rem;margin:1.5rem 0 0.75rem;">Verified Transactions</h4>
            <div style="display:flex;flex-direction:column;gap:0.6rem;">
              <?php foreach ($payments as $p): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem;background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:var(--radius-sm);font-size:0.85rem;">
                  <div>
                    <div class="mono" style="font-weight:700;color:var(--text-main);"><?= e($p['transaction_ref']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($p['payment_type']) ?> &bull; <?= format_date($p['paid_at'], 'd M Y, h:i A') ?></div>
                  </div>
                  <div style="text-align:right;">
                    <div class="mono" style="font-weight:800;color:var(--success);"><?= format_currency($p['amount']) ?></div>
                    <span class="badge badge-success" style="font-size:0.65rem;">VERIFIED</span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Review Box if Completed -->
          <?php if (in_array($booking['booking_status'], ['COMPLETED', 'COMPLETED_AND_VERIFIED'])): ?>
            <div class="glass-card">
              <h3 style="font-size:1.15rem;margin-bottom:1rem;">Rate Engineer &amp; Leave Review</h3>
              <?php if ($existing_review): ?>
                <div style="padding:1rem;background:rgba(255,255,255,0.02);border-radius:var(--radius-sm);">
                  <div style="color:var(--warning);font-size:1.1rem;margin-bottom:0.4rem;">
                    <?php for ($i = 0; $i < $existing_review['rating']; $i++): ?>★<?php endfor; ?>
                  </div>
                  <p style="font-size:0.88rem;color:var(--text-muted);font-style:italic;">
                    &ldquo;<?= e($existing_review['comment']) ?>&rdquo;
                  </p>
                  <div style="font-size:0.75rem;color:var(--text-dim);margin-top:0.4rem;">Submitted on <?= format_date($existing_review['created_at']) ?></div>
                </div>
              <?php else: ?>
                <form method="POST" action="">
                  <input type="hidden" name="action" value="submit_review">
                  <div class="form-group">
                    <label class="form-label">Rating</label>
                    <select name="rating" class="form-select">
                      <option value="5">★★★★★ - Outstanding (5/5)</option>
                      <option value="4">★★★★☆ - Very Good (4/5)</option>
                      <option value="3">★★★☆☆ - Satisfactory (3/5)</option>
                      <option value="2">★★☆☆☆ - Below Expectations (2/5)</option>
                      <option value="1">★☆☆☆☆ - Unsatisfactory (1/5)</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label class="form-label">Review / Feedback</label>
                    <textarea name="comment" class="form-control" style="min-height:80px;" placeholder="Describe technical precision, communication, and repair quality..." required></textarea>
                  </div>
                  <button type="submit" class="btn btn-primary btn-block btn-sm">
                    <i data-lucide="star"></i> Publish Review
                  </button>
                </form>
              <?php endif; ?>
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
  async function settleBalance(bookingId) {
    if (!confirm('Proceed to settle the remaining 80% balance?')) return;
    const btn = document.getElementById('settleBalanceBtn');
    btn.disabled = true;

    const res = await ApiClient.request('api/payment.php?action=pay_remaining', {
      method: 'POST',
      body: JSON.stringify({ booking_id: bookingId, payment_method: 'UPI' })
    });

    if (res.status === 'success') {
      window.showToast(res.data.message, 'success');
      setTimeout(() => location.reload(), 1000);
    } else {
      btn.disabled = false;
      alert(res.data.message || 'Settlement failed.');
    }
  }

  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
