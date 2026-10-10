<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');
$user = current_user();
$customer_id = get_customer_id($user['id']);
$pdo = get_db_connection();

// Fetch customer address details
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = clean_input($_POST['full_name'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    $address = clean_input($_POST['address'] ?? '');
    $city = clean_input($_POST['city'] ?? '');
    $state = clean_input($_POST['state'] ?? '');
    $postal_code = clean_input($_POST['postal_code'] ?? '');

    // Password change optional
    $new_pass = clean_input($_POST['new_password'] ?? '');

    if (empty($full_name)) {
        $error = 'Full name cannot be blank.';
    } else {
        try {
            $pdo->beginTransaction();

            $upd_user = $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?");
            $upd_user->execute([$full_name, $phone, $user['id']]);

            $upd_cust = $pdo->prepare("UPDATE customers SET address = ?, city = ?, state = ?, postal_code = ? WHERE id = ?");
            $upd_cust->execute([$address, $city, $state, $postal_code, $customer_id]);

            if (!empty($new_pass) && strlen($new_pass) >= 6) {
                $hash = password_hash($new_pass, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $user['id']]);
            }

            $pdo->commit();
            $success = 'Profile and service address updated successfully!';
            
            // Refresh
            $user = current_user();
            $stmt->execute([$customer_id]);
            $customer = $stmt->fetch();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'Failed to update profile: ' . $e->getMessage();
        }
    }
}

$page_title = 'Profile & Address Settings — ' . APP_NAME;
$active_nav = 'profile';
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
        <h2>Customer Profile &amp; Service Address</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content" style="max-width:850px;">
      <?php if ($success): ?>
        <div class="alert alert-success"><i data-lucide="check-circle"></i> <?= e($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><i data-lucide="alert-triangle"></i> <?= e($error) ?></div>
      <?php endif; ?>

      <div class="glass-card" style="padding:2.5rem;">
        <div style="display:flex;align-items:center;gap:1.5rem;margin-bottom:2rem;padding-bottom:1.5rem;border-bottom:1px solid var(--border-glass);">
          <img src="<?= get_avatar_url($user['avatar'], $user['full_name']) ?>" alt="Avatar" style="width:80px;height:80px;border-radius:50%;border:2px solid var(--primary);">
          <div>
            <h3 style="font-size:1.4rem;"><?= e($user['full_name']) ?></h3>
            <div style="font-size:0.85rem;color:var(--text-dim);"><?= e($user['email']) ?> &bull; Role: Customer</div>
            <div style="margin-top:0.35rem;">
              <span class="badge badge-primary">Active Customer Account</span>
            </div>
          </div>
        </div>

        <form method="POST" action="">
          <h4 style="margin-bottom:1.25rem;color:var(--primary);">Contact Information</h4>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Full Name *</label>
              <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address (Read-only)</label>
              <input type="email" class="form-control" value="<?= e($user['email']) ?>" readonly style="opacity:0.7;">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Contact Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>">
            </div>
          </div>

          <h4 style="margin:2rem 0 1.25rem;color:var(--primary);">Default On-Site Diagnostic Address</h4>
          <div class="form-group">
            <label class="form-label">Street Address &amp; Apartment / Landmark</label>
            <input type="text" name="address" class="form-control" value="<?= e($customer['address'] ?? '') ?>" placeholder="e.g. 402, Skyline Residency, 100 Feet Rd">
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">City</label>
              <input type="text" name="city" class="form-control" value="<?= e($customer['city'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">State</label>
              <input type="text" name="state" class="form-control" value="<?= e($customer['state'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Postal Code</label>
              <input type="text" name="postal_code" class="form-control" value="<?= e($customer['postal_code'] ?? '') ?>">
            </div>
          </div>

          <h4 style="margin:2rem 0 1.25rem;color:var(--primary);">Security / Change Password (Optional)</h4>
          <div class="form-group">
            <label class="form-label">New Password (leave blank to keep current)</label>
            <input type="password" name="new_password" class="form-control" placeholder="••••••••">
          </div>

          <button type="submit" class="btn btn-primary btn-block" style="margin-top:1.5rem;">
            <i data-lucide="save"></i> Save Profile &amp; Address Changes
          </button>
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
