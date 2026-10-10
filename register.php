<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    header("Location: " . BASE_URL . "index.php");
    exit;
}

$role_param = clean_input($_GET['role'] ?? 'customer');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = clean_input($_POST['full_name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    $password = clean_input($_POST['password'] ?? '');
    $confirm_password = clean_input($_POST['confirm_password'] ?? '');
    $role = clean_input($_POST['role'] ?? 'customer');

    if (!in_array($role, ['customer', 'engineer'])) {
        $role = 'customer';
    }

    if (empty($full_name) || empty($email) || empty($password)) {
        $error = 'Please complete all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $pdo = get_db_connection();
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = 'An account with that email is already registered. Please log in.';
        } else {
            try {
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins_u = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
                $ins_u->execute([$full_name, $email, $hash, $phone, $role]);
                $user_id = $pdo->lastInsertId();

                if ($role === 'engineer') {
                    $title = clean_input($_POST['title'] ?? 'Electronics Engineering Specialist');
                    $location = clean_input($_POST['location'] ?? 'Bangalore, India');
                    $hourly_rate = floatval($_POST['hourly_rate'] ?? 600.00);
                    $upi_id = clean_input($_POST['upi_id'] ?? 'engineer@okhdfcbank');

                    $ins_e = $pdo->prepare("INSERT INTO engineers (user_id, title, location, hourly_rate, upi_id, is_verified) VALUES (?, ?, ?, ?, ?, 1)");
                    $ins_e->execute([$user_id, $title, $location, $hourly_rate, $upi_id]);
                    $redirect = 'engineer/dashboard.php';
                } else {
                    $address = clean_input($_POST['address'] ?? '');
                    $city = clean_input($_POST['city'] ?? 'Bangalore');

                    $ins_c = $pdo->prepare("INSERT INTO customers (user_id, address, city) VALUES (?, ?, ?)");
                    $ins_c->execute([$user_id, $address, $city]);
                    $redirect = 'customer/dashboard.php';
                }

                $pdo->commit();

                // Log in new user
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch();
                login_user($user);

                set_flash('success', 'Registration complete! Welcome to VoltCraft 3D.');
                header("Location: " . BASE_URL . $redirect);
                exit;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to create account: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Create an Account — VoltCraft 3D';
require_once __DIR__ . '/includes/header.php';
?>

<div class="section" style="padding-top:3.5rem;padding-bottom:5rem;">
  <div class="container" style="max-width:620px;">
    <div class="glass-card" style="padding:2.5rem;">
      <div style="text-align:center;margin-bottom:2rem;">
        <h2 style="font-size:1.8rem;margin-bottom:0.4rem;">Join VoltCraft 3D</h2>
        <p style="font-size:0.88rem;color:var(--text-dim);">Register as a customer to book repairs or as an engineer to offer services</p>
      </div>

      <!-- Role Selector Tabs -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:2rem;background:rgba(255,255,255,0.03);padding:0.35rem;border-radius:var(--radius-sm);border:1px solid var(--border-glass);">
        <button type="button" id="tabCustomerBtn" onclick="switchRole('customer')" class="btn btn-sm <?= $role_param !== 'engineer' ? 'btn-primary' : 'btn-outline' ?>" style="border:none;">
          <i data-lucide="user" style="width:16px;height:16px;"></i> I Need Repairs (Customer)
        </button>
        <button type="button" id="tabEngineerBtn" onclick="switchRole('engineer')" class="btn btn-sm <?= $role_param === 'engineer' ? 'btn-primary' : 'btn-outline' ?>" style="border:none;">
          <i data-lucide="wrench" style="width:16px;height:16px;"></i> I am an Engineer
        </button>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i data-lucide="alert-triangle" style="width:18px;height:18px;"></i> <?= e($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="role" id="selectedRoleInput" value="<?= e($role_param === 'engineer' ? 'engineer' : 'customer') ?>">

        <!-- Common Fields -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. Vikram Sharma" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" placeholder="name@domain.com" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Phone Number *</label>
            <input type="text" name="phone" class="form-control" placeholder="+91 98765 43210" required>
          </div>
          <div class="form-group">
            <label class="form-label">City / Region *</label>
            <input type="text" name="city" class="form-control" placeholder="e.g. Bangalore" required>
          </div>
        </div>

        <!-- Role Specific: Customer -->
        <div id="customerFields" style="<?= $role_param === 'engineer' ? 'display:none;' : '' ?>">
          <div class="form-group">
            <label class="form-label">Default Service / Delivery Address</label>
            <input type="text" name="address" class="form-control" placeholder="Flat, Street, Landmark">
          </div>
        </div>

        <!-- Role Specific: Engineer -->
        <div id="engineerFields" style="<?= $role_param !== 'engineer' ? 'display:none;' : '' ?>">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Specialization Title *</label>
              <input type="text" name="title" class="form-control" placeholder="e.g. Senior Micro-Soldering Specialist">
            </div>
            <div class="form-group">
              <label class="form-label">Hourly Diagnostic Rate (₹) *</label>
              <input type="number" name="hourly_rate" class="form-control" placeholder="750" value="650">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Operating Location *</label>
              <input type="text" name="location" class="form-control" placeholder="e.g. Indiranagar, Bangalore">
            </div>
            <div class="form-group">
              <label class="form-label">UPI ID for 20% Advance Escrow *</label>
              <input type="text" name="upi_id" class="form-control" placeholder="name@okhdfcbank">
            </div>
          </div>
        </div>

        <!-- Passwords -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password * (Min 6 chars)</label>
            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password *</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="••••••••" required>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="margin-top:1rem;padding:0.85rem;">
          <i data-lucide="user-plus"></i> Register &amp; Access Dashboard
        </button>
      </form>

      <div style="text-align:center;margin-top:1.75rem;font-size:0.9rem;color:var(--text-dim);">
        Already have an account? 
        <a href="<?= BASE_URL ?>login.php" style="color:var(--primary);font-weight:600;">Sign In</a>
      </div>
    </div>
  </div>
</div>

<script>
  function switchRole(role) {
    document.getElementById('selectedRoleInput').value = role;
    const custBtn = document.getElementById('tabCustomerBtn');
    const engBtn = document.getElementById('tabEngineerBtn');
    const custFields = document.getElementById('customerFields');
    const engFields = document.getElementById('engineerFields');

    if (role === 'engineer') {
      engBtn.className = 'btn btn-sm btn-primary';
      custBtn.className = 'btn btn-sm btn-outline';
      engFields.style.display = 'block';
      custFields.style.display = 'none';
    } else {
      custBtn.className = 'btn btn-sm btn-primary';
      engBtn.className = 'btn btn-sm btn-outline';
      custFields.style.display = 'block';
      engFields.style.display = 'none';
    }
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
