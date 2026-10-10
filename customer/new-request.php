<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');
$user = current_user();
$customer_id = get_customer_id($user['id']);

$pdo = get_db_connection();

// Pre-fill parameters if came from engineer/services page
$pre_engineer_id = intval($_GET['engineer_id'] ?? 0);
$pre_service_id = intval($_GET['service_id'] ?? 0);

// Fetch Engineers & Services
$engineers = $pdo->query("SELECT e.id, e.hourly_rate, u.full_name, e.title, e.location 
                          FROM engineers e 
                          JOIN users u ON e.user_id = u.id 
                          WHERE u.status = 'active' AND e.is_verified = 1 
                          ORDER BY u.full_name ASC")->fetchAll();

$services = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Fetch Customer default address
$cust_data = $pdo->prepare("SELECT address, city FROM customers WHERE id = ?");
$cust_data->execute([$customer_id]);
$cust_profile = $cust_data->fetch();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $engineer_id = intval($_POST['engineer_id'] ?? 0);
    $service_id = intval($_POST['service_id'] ?? 0);
    $title = clean_input($_POST['title'] ?? '');
    $description = clean_input($_POST['description'] ?? '');
    $address = clean_input($_POST['address'] ?? '');
    $city = clean_input($_POST['city'] ?? '');
    $preferred_date = clean_input($_POST['preferred_date'] ?? '');
    $preferred_time = clean_input($_POST['preferred_time'] ?? '');
    $estimated_amount = floatval($_POST['estimated_amount'] ?? 0);

    if (!$engineer_id || !$service_id || empty($title) || empty($description) || empty($address) || empty($preferred_date)) {
        $error = 'Please fill out all required fields.';
    } else {
        // Handle file upload if present
        $attachment_path = null;
        if (!empty($_FILES['attachment']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
            $filename = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $target_name = 'req_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $upload_dir = __DIR__ . '/../assets/uploads/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $upload_dir . $target_name)) {
                    $attachment_path = 'assets/uploads/' . $target_name;
                }
            }
        }

        try {
            $request_code = generate_code('REQ');
            $ins = $pdo->prepare("INSERT INTO service_requests 
                (request_code, customer_id, engineer_id, service_id, title, description, address, city, preferred_date, preferred_time, attachment, estimated_amount, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')");
            $ins->execute([
                $request_code, $customer_id, $engineer_id, $service_id, $title,
                $description, $address, $city, $preferred_date, $preferred_time,
                $attachment_path, $estimated_amount
            ]);

            // Engineer user notification
            $eng_u = $pdo->prepare("SELECT user_id FROM engineers WHERE id = ?");
            $eng_u->execute([$engineer_id]);
            $eng_uid = $eng_u->fetchColumn();
            if ($eng_uid) {
                create_notification($eng_uid, 'new_request', 'New Hardware Diagnostic Request', "Customer submitted {$request_code}: {$title}", 'engineer/requests.php');
            }

            set_flash('success', "Service request {$request_code} submitted successfully! The engineer will review it shortly.");
            header("Location: " . BASE_URL . "customer/requests.php");
            exit;

        } catch (PDOException $e) {
            $error = 'Failed to submit request: ' . $e->getMessage();
        }
    }
}

$page_title = 'Submit Service Request — ' . APP_NAME;
$active_nav = 'new_request';
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
        <h2>Submit Diagnostic Service Request</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content" style="max-width:960px;">
      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i data-lucide="alert-triangle"></i> <?= e($error) ?>
        </div>
      <?php endif; ?>

      <div class="glass-card" style="padding:2.5rem;">
        <form method="POST" action="" enctype="multipart/form-data">
          <!-- Section 1: Engineer & Service Selection -->
          <h3 style="font-size:1.2rem;margin-bottom:1.25rem;color:var(--primary);display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="wrench"></i> 1. Select Professional &amp; Engineering Domain
          </h3>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Assigned Electronics Engineer *</label>
              <select name="engineer_id" id="engineer_select" class="form-select" required>
                <option value="">-- Choose an Engineer --</option>
                <?php foreach ($engineers as $eng): ?>
                  <option value="<?= $eng['id'] ?>" <?= $pre_engineer_id == $eng['id'] ? 'selected' : '' ?>>
                    <?= e($eng['full_name']) ?> &mdash; <?= e($eng['title']) ?> (<?= format_currency($eng['hourly_rate']) ?>/hr)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label class="form-label">Service Domain / Category *</label>
              <select name="service_id" id="service_select" class="form-select" required>
                <option value="">-- Choose Service Type --</option>
                <?php foreach ($services as $srv): ?>
                  <option value="<?= $srv['id'] ?>" data-price="<?= $srv['base_price'] ?>" <?= $pre_service_id == $srv['id'] ? 'selected' : '' ?>>
                    <?= e($srv['name']) ?> (Base: <?= format_currency($srv['base_price']) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <!-- Section 2: Fault Scope & Details -->
          <h3 style="font-size:1.2rem;margin:1.5rem 0 1.25rem;color:var(--primary);display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="alert-circle"></i> 2. Problem Description &amp; Hardware Symptoms
          </h3>

          <div class="form-group">
            <label class="form-label">Device Name / Fault Title *</label>
            <input type="text" name="title" class="form-control" placeholder="e.g. Inverter Board Error code F002 / Short circuit on 12V rail" required>
          </div>

          <div class="form-group">
            <label class="form-label">Detailed Fault Symptoms &amp; History *</label>
            <textarea name="description" class="form-control form-textarea" placeholder="Describe symptoms, liquid damage history, unusual noises, burned smell, or error codes..." required></textarea>
          </div>

          <div class="form-group">
            <label class="form-label">Schematic / Damaged Board Photo (Optional)</label>
            <input type="file" name="attachment" class="form-control" accept="image/*,.pdf">
            <div class="form-hint">Accepted formats: JPG, PNG, WEBP, PDF (Max 5MB)</div>
          </div>

          <!-- Section 3: Appointment & Address -->
          <h3 style="font-size:1.2rem;margin:1.5rem 0 1.25rem;color:var(--primary);display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="calendar"></i> 3. Schedule &amp; Physical Location
          </h3>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Preferred Date *</label>
              <input type="date" name="preferred_date" class="form-control" min="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="form-group">
              <label class="form-label">Preferred Time Slot *</label>
              <select name="preferred_time" class="form-select" required>
                <option value="10:00 AM - 01:00 PM">Morning (10:00 AM - 01:00 PM)</option>
                <option value="02:00 PM - 05:00 PM">Afternoon (02:00 PM - 05:00 PM)</option>
                <option value="05:30 PM - 08:30 PM">Evening (05:30 PM - 08:30 PM)</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Location Address *</label>
              <input type="text" name="address" class="form-control" value="<?= e($cust_profile['address'] ?? '') ?>" placeholder="Street address, building, landmark" required>
            </div>

            <div class="form-group">
              <label class="form-label">City *</label>
              <input type="text" name="city" class="form-control" value="<?= e($cust_profile['city'] ?? 'Bangalore') ?>" required>
            </div>
          </div>

          <!-- Hidden Input for Estimated Amount (updated dynamically by main.js) -->
          <input type="hidden" name="estimated_amount" id="estimated_amount" value="2500.00">

          <!-- Section 4: 20% Advance Escrow Live Breakdown -->
          <h3 style="font-size:1.2rem;margin:1.5rem 0 1.25rem;color:var(--primary);display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="shield-check"></i> 4. 20% Advance Escrow Preview
          </h3>

          <div class="payment-split-box">
            <div class="split-item">
              <div class="split-label">Total Estimated Fee</div>
              <div class="split-value" id="total_preview_val">₹ 2,500.00</div>
            </div>
            <div class="split-item">
              <div class="split-label">20% Advance (Paid on Acceptance)</div>
              <div class="split-value highlight" id="advance_preview_val">₹ 500.00</div>
            </div>
            <div class="split-item">
              <div class="split-label">80% Balance (Paid Post-Repair)</div>
              <div class="split-value" id="remaining_preview_val">₹ 2,000.00</div>
            </div>
          </div>

          <div style="margin-top:2rem;">
            <button type="submit" class="btn btn-primary btn-lg btn-block">
              <i data-lucide="send"></i> Submit Service Request to Engineer
            </button>
          </div>
        </form>
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
