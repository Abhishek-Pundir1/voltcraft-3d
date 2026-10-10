<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');
$user = current_user();
$customer_id = get_customer_id($user['id']);

$pdo = get_db_connection();
$request_id = intval($_GET['request_id'] ?? 0);

if (!$request_id) {
    header("Location: " . BASE_URL . "customer/requests.php");
    exit;
}

// Fetch Request & Engineer details
$stmt = $pdo->prepare("SELECT sr.*, s.name as service_name, s.estimated_time, 
                              e.id as engineer_id, e.hourly_rate, e.upi_id, e.upi_qr_image, 
                              eng_u.full_name as engineer_name, eng_u.email as engineer_email, eng_u.phone as engineer_phone, eng_u.avatar as engineer_avatar 
                       FROM service_requests sr 
                       JOIN services s ON sr.service_id = s.id 
                       JOIN engineers e ON sr.engineer_id = e.id 
                       JOIN users eng_u ON e.user_id = eng_u.id 
                       WHERE sr.id = ? AND sr.customer_id = ?");
$stmt->execute([$request_id, $customer_id]);
$req = $stmt->fetch();

if (!$req) {
    set_flash('danger', 'Service request not found or unauthorized.');
    header("Location: " . BASE_URL . "customer/requests.php");
    exit;
}

// Calculate financial figures
$total_amount = floatval($req['estimated_amount']);
$advance_amount = round($total_amount * ADVANCE_PERCENTAGE, 2);
$remaining_amount = round($total_amount - $advance_amount, 2);

$page_title = 'Deposit 20% Advance Escrow — ' . APP_NAME;
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
        <h2>20% Advance Booking Escrow</h2>
      </div>
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>customer/requests.php" class="btn btn-outline btn-sm">
          <i data-lucide="arrow-left"></i> Back to Requests
        </a>
      </div>
    </header>

    <div class="dashboard-content" style="max-width:1000px;">
      <!-- Alert banner -->
      <div class="alert alert-info" style="margin-bottom:2rem;">
        <i data-lucide="shield-check" style="width:20px;height:20px;"></i>
        <span>
          Engineer <strong><?= e($req['engineer_name']) ?></strong> has reviewed and accepted your request. Depositing the 20% advance secures your appointment and dispatches technical tooling.
        </span>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1.25fr;gap:2rem;align-items:start;">
        <!-- Left Column: Scope & 20% Breakdown Card -->
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
          <div class="glass-card">
            <h3 style="font-size:1.2rem;margin-bottom:1rem;color:var(--text-main);">Order Summary</h3>
            <div style="display:flex;flex-direction:column;gap:0.75rem;font-size:0.9rem;">
              <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border-glass);padding-bottom:0.5rem;">
                <span style="color:var(--text-dim);">Request Code</span>
                <strong class="mono" style="color:var(--primary);"><?= e($req['request_code']) ?></strong>
              </div>
              <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border-glass);padding-bottom:0.5rem;">
                <span style="color:var(--text-dim);">Service</span>
                <span><?= e($req['service_name']) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border-glass);padding-bottom:0.5rem;">
                <span style="color:var(--text-dim);">Engineer</span>
                <span><?= e($req['engineer_name']) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border-glass);padding-bottom:0.5rem;">
                <span style="color:var(--text-dim);">Scheduled Date</span>
                <span><?= format_date($req['preferred_date'], 'd M Y') ?> (<?= e($req['preferred_time']) ?>)</span>
              </div>
            </div>

            <!-- Visual 20% Advance Breakdown -->
            <div style="background:rgba(6,9,19,0.7);border:1px solid var(--border-glass);border-radius:var(--radius-sm);padding:1.25rem;margin-top:1.5rem;">
              <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;">
                <span style="color:var(--text-muted);">Total Service Amount</span>
                <span class="mono" style="font-weight:700;"><?= format_currency($total_amount) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;color:var(--primary);font-weight:700;">
                <span>20% Advance Required Now</span>
                <span class="mono" style="font-size:1.15rem;"><?= format_currency($advance_amount) ?></span>
              </div>
              <div style="display:flex;justify-content:space-between;padding-top:0.5rem;border-top:1px dashed var(--border-glass);color:var(--text-dim);font-size:0.85rem;">
                <span>80% Remaining (Post-Repair)</span>
                <span class="mono"><?= format_currency($remaining_amount) ?></span>
              </div>
            </div>
          </div>

          <!-- Engineer UPI QR Code -->
          <div class="glass-card" style="text-align:center;">
            <h4 style="font-size:1rem;margin-bottom:0.5rem;">Engineer Direct UPI QR Code</h4>
            <p style="font-size:0.8rem;color:var(--text-dim);margin-bottom:1rem;">
              Scan using any UPI App (GPay, PhonePe, Paytm, BHIM)
            </p>

            <div class="qr-container">
              <img src="<?= get_upi_qr_url($req['upi_qr_image']) ?>" alt="UPI QR Code">
              <div class="qr-caption"><?= e($req['upi_id'] ?: 'rajesh.volt@okhdfcbank') ?></div>
            </div>
          </div>
        </div>

        <!-- Right Column: Interactive Payment Submission Interface -->
        <div class="glass-card" style="padding:2.25rem;">
          <h3 style="font-size:1.35rem;margin-bottom:0.5rem;">Complete Advance Payment</h3>
          <p style="font-size:0.88rem;color:var(--text-dim);margin-bottom:1.75rem;">
            Choose your preferred gateway mode. All transactions are securely verified before appointment confirmation.
          </p>

          <!-- Method Selector Tabs -->
          <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:0.5rem;margin-bottom:1.5rem;">
            <button type="button" onclick="selectMethod('UPI')" id="methodBtnUPI" class="btn btn-sm btn-primary">
              <i data-lucide="qr-code"></i> UPI Transfer
            </button>
            <button type="button" onclick="selectMethod('GATEWAY_DEMO')" id="methodBtnDemo" class="btn btn-sm btn-outline">
              <i data-lucide="zap"></i> Instant Gateway
            </button>
            <button type="button" onclick="selectMethod('CARD')" id="methodBtnCard" class="btn btn-sm btn-outline">
              <i data-lucide="credit-card"></i> Card / Netbanking
            </button>
          </div>

          <form id="paymentForm" onsubmit="event.preventDefault(); processAdvancePayment();">
            <input type="hidden" id="pay_request_id" value="<?= $req['id'] ?>">
            <input type="hidden" id="pay_method" value="UPI">

            <!-- Amount Display Callout -->
            <div style="background:rgba(0,240,255,0.08);border:1px solid rgba(0,240,255,0.25);border-radius:var(--radius-sm);padding:1.25rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;">
              <div>
                <div style="font-size:0.75rem;color:var(--primary);text-transform:uppercase;font-weight:700;">Advance Amount to Lock</div>
                <div style="font-size:1.85rem;font-weight:800;font-family:var(--font-mono);color:var(--text-main);">
                  <?= format_currency($advance_amount) ?>
                </div>
              </div>
              <span class="badge badge-success">Escrow Protected</span>
            </div>

            <!-- UPI Details Panel -->
            <div id="upiPanel">
              <div class="form-group">
                <label class="form-label">Beneficiary UPI ID</label>
                <div style="display:flex;gap:0.5rem;">
                  <input type="text" class="form-control mono" value="<?= e($req['upi_id'] ?: 'rajesh.volt@okhdfcbank') ?>" readonly>
                  <button type="button" class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText('<?= e($req['upi_id'] ?: 'rajesh.volt@okhdfcbank') ?>'); showToast('UPI ID copied to clipboard!', 'success');">
                    Copy
                  </button>
                </div>
              </div>

              <div class="form-group">
                <label class="form-label">UPI Reference / UTR Number (12 Digits) *</label>
                <div style="display:flex;gap:0.5rem;">
                  <input type="text" id="transaction_ref" class="form-control mono" placeholder="e.g. 984710294821" required>
                  <button type="button" class="btn btn-outline btn-sm" onclick="generateDemoRef()" title="Generate Test UTR">
                    Test UTR
                  </button>
                </div>
                <div class="form-hint">Enter the 12-digit transaction ID or UTR generated by your banking app.</div>
              </div>
            </div>

            <!-- Demo Instant Gateway Simulation Panel -->
            <div id="demoPanel" style="display:none;background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:var(--radius-sm);padding:1.25rem;margin-bottom:1.5rem;">
              <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.75rem;">
                <div style="width:36px;height:36px;border-radius:var(--radius-sm);background:rgba(0,240,255,0.15);color:var(--primary);display:flex;align-items:center;justify-content:center;">
                  <i data-lucide="shield"></i>
                </div>
                <div>
                  <h4 style="font-size:0.95rem;">Simulated Razorpay / Stripe Gateway</h4>
                  <div style="font-size:0.75rem;color:var(--text-dim);">Ready for Production API Secret Key injection</div>
                </div>
              </div>
              <p style="font-size:0.85rem;color:var(--text-muted);line-height:1.5;">
                In development mode, clicking below verifies token generation, executes cryptographic transaction logging, updates MySQL records, and issues booking confirmation.
              </p>
            </div>

            <div class="form-group">
              <label class="form-label">Payment Notes (Optional)</label>
              <input type="text" id="pay_notes" class="form-control" placeholder="e.g. 20% advance for MacBook logic board repair">
            </div>

            <button type="submit" id="paySubmitBtn" class="btn btn-primary btn-lg btn-block" style="margin-top:1.5rem;">
              <i data-lucide="check-circle"></i> Verify &amp; Confirm Booking (<?= format_currency($advance_amount) ?>)
            </button>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script src="<?= BASE_URL ?>assets/js/api-client.js"></script>
<script>
  let activeMethod = 'UPI';

  function selectMethod(method) {
    activeMethod = method;
    document.getElementById('pay_method').value = method;
    
    document.getElementById('methodBtnUPI').className = (method === 'UPI') ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
    document.getElementById('methodBtnDemo').className = (method === 'GATEWAY_DEMO') ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';
    document.getElementById('methodBtnCard').className = (method === 'CARD') ? 'btn btn-sm btn-primary' : 'btn btn-sm btn-outline';

    const upiPanel = document.getElementById('upiPanel');
    const demoPanel = document.getElementById('demoPanel');

    if (method === 'UPI' || method === 'CARD') {
      upiPanel.style.display = 'block';
      demoPanel.style.display = 'none';
      if (!document.getElementById('transaction_ref').value) {
        generateDemoRef();
      }
    } else {
      upiPanel.style.display = 'none';
      demoPanel.style.display = 'block';
      document.getElementById('transaction_ref').value = 'GATEWAY-DEMO-' + Math.floor(10000000 + Math.random() * 90000000);
    }
  }

  function generateDemoRef() {
    const ref = 'UTR' + Math.floor(100000000000 + Math.random() * 900000000000);
    document.getElementById('transaction_ref').value = ref;
    window.showToast('Generated simulated UTR: ' + ref, 'info');
  }

  async function processAdvancePayment() {
    const btn = document.getElementById('paySubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader" style="animation:spin 1s linear infinite;"></i> Verifying Transaction Escrow...';
    if (typeof lucide !== 'undefined') lucide.createIcons();

    const payload = {
      request_id: document.getElementById('pay_request_id').value,
      payment_method: document.getElementById('pay_method').value,
      transaction_ref: document.getElementById('transaction_ref').value.trim(),
      notes: document.getElementById('pay_notes').value.trim()
    };

    const res = await ApiClient.processPayment(payload);

    if (res.status === 'success') {
      window.showToast('Advance payment verified successfully! Redirecting...', 'success');
      setTimeout(() => {
        window.location.href = res.data.redirect;
      }, 1000);
    } else {
      btn.disabled = false;
      btn.innerHTML = '<i data-lucide="check-circle"></i> Retry Payment';
      if (typeof lucide !== 'undefined') lucide.createIcons();
      alert(res.data.message || 'Payment verification failed.');
    }
  }

  // Pre-generate a test UTR for instant testing
  document.addEventListener('DOMContentLoaded', () => {
    generateDemoRef();
  });
</script>
</body>
</html>
