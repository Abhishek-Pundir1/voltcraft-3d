<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to appropriate portal
if (is_logged_in()) {
    $role = current_role();
    if ($role === 'admin') header("Location: " . BASE_URL . "admin/dashboard.php");
    elseif ($role === 'engineer') header("Location: " . BASE_URL . "engineer/dashboard.php");
    else header("Location: " . BASE_URL . "customer/dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email'] ?? '');
    $password = clean_input($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both your email address and password.';
    } else {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated. Please contact administrator.';
            } else {
                login_user($user);
                set_flash('success', 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!');

                if ($user['role'] === 'admin') header("Location: " . BASE_URL . "admin/dashboard.php");
                elseif ($user['role'] === 'engineer') header("Location: " . BASE_URL . "engineer/dashboard.php");
                else header("Location: " . BASE_URL . "customer/dashboard.php");
                exit;
            }
        } else {
            $error = 'Invalid email address or password. Please check your credentials.';
        }
    }
}

$page_title = 'Sign In — VoltCraft 3D';
require_once __DIR__ . '/includes/header.php';
?>

<div class="section" style="padding-top:4rem;padding-bottom:5rem;">
  <div class="container" style="max-width:480px;">
    <div class="glass-card" style="padding:2.5rem 2rem;">
      <div style="text-align:center;margin-bottom:2rem;">
        <div class="brand-icon" style="width:48px;height:48px;margin:0 auto 1rem;">
          <i data-lucide="zap" style="width:26px;height:26px;"></i>
        </div>
        <h2 style="font-size:1.8rem;margin-bottom:0.4rem;">Account Sign In</h2>
        <p style="font-size:0.88rem;color:var(--text-dim);">Access your customer, engineer, or admin console</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger">
          <i data-lucide="alert-triangle" style="width:18px;height:18px;"></i> <?= e($error) ?>
        </div>
      <?php endif; ?>

      <!-- Quick Demo Credentials Selector -->
      <div style="background:rgba(255,255,255,0.03);border:1px solid var(--border-glass);border-radius:var(--radius-sm);padding:0.85rem;margin-bottom:1.75rem;">
        <div style="font-size:0.75rem;font-weight:700;color:var(--primary);text-transform:uppercase;margin-bottom:0.5rem;letter-spacing:0.04em;">
          ⚡ Quick 1-Click Demo Login
        </div>
        <div style="display:grid;grid-template-columns:repeat(3, 1fr);gap:0.4rem;">
          <button type="button" onclick="fillCredentials('customer@voltcraft.com', 'customer123')" class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.4rem;">
            Customer
          </button>
          <button type="button" onclick="fillCredentials('rajesh@voltcraft.com', 'engineer123')" class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.4rem;">
            Engineer
          </button>
          <button type="button" onclick="fillCredentials('admin@voltcraft.com', 'admin123')" class="btn btn-outline btn-sm" style="font-size:0.75rem;padding:0.35rem 0.4rem;">
            Admin
          </button>
        </div>
      </div>

      <!-- Login Form -->
      <form method="POST" action="">
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" id="login_email" name="email" class="form-control" placeholder="name@domain.com" required>
        </div>

        <div class="form-group">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.45rem;">
            <label class="form-label" style="margin-bottom:0;">Password</label>
            <a href="#" style="font-size:0.8rem;color:var(--primary);">Forgot?</a>
          </div>
          <input type="password" id="login_password" name="password" class="form-control" placeholder="••••••••" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="margin-top:1.5rem;padding:0.85rem;">
          <i data-lucide="log-in"></i> Sign In to Account
        </button>
      </form>

      <div style="text-align:center;margin-top:2rem;font-size:0.9rem;color:var(--text-dim);">
        Don't have an account yet? 
        <a href="<?= BASE_URL ?>register.php" style="color:var(--primary);font-weight:600;">Create Account</a>
      </div>
    </div>
  </div>
</div>

<script>
  function fillCredentials(email, pass) {
    document.getElementById('login_email').value = email;
    document.getElementById('login_password').value = pass;
    window.showToast(`Loaded demo credentials for: ${email}`, 'info');
  }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
