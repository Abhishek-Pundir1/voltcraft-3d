<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in() && current_role() === 'admin') {
    header("Location: " . BASE_URL . "admin/dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email'] ?? '');
    $password = clean_input($_POST['password'] ?? '');

    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND role = 'admin' LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        login_user($user);
        header("Location: " . BASE_URL . "admin/dashboard.php");
        exit;
    } else {
        $error = 'Invalid administrator credentials.';
    }
}

$page_title = 'Admin Console Access — ' . APP_NAME;
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:#05070f;">

<div class="container" style="max-width:440px;padding:1.5rem;">
  <div class="glass-card" style="border-color:rgba(239,68,68,0.3);box-shadow:0 0 35px rgba(239,68,68,0.2);padding:2.5rem 2rem;">
    <div style="text-align:center;margin-bottom:2rem;">
      <div style="width:52px;height:52px;border-radius:var(--radius-sm);background:linear-gradient(135deg, #ef4444 0%, #dc2626 100%);color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;box-shadow:0 0 20px rgba(239,68,68,0.4);">
        <i data-lucide="shield" style="width:28px;height:28px;"></i>
      </div>
      <h2 style="font-size:1.6rem;color:#f87171;">Admin Ops Console</h2>
      <p style="font-size:0.85rem;color:var(--text-dim);">Authorized System Administrators Only</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger" style="font-size:0.88rem;">
        <i data-lucide="alert-triangle"></i> <?= e($error) ?>
      </div>
    <?php endif; ?>

    <div style="background:rgba(255,255,255,0.03);padding:0.75rem;border-radius:var(--radius-sm);margin-bottom:1.5rem;text-align:center;">
      <button type="button" onclick="fillAdmin()" class="btn btn-outline btn-sm" style="font-size:0.75rem;width:100%;border-color:rgba(239,68,68,0.4);color:#f87171;">
        ⚡ Fill Demo Admin Credentials
      </button>
    </div>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label">Administrator Email</label>
        <input type="email" id="admin_email" name="email" class="form-control" placeholder="admin@voltcraft.com" required>
      </div>

      <div class="form-group">
        <label class="form-label">Security Key / Password</label>
        <input type="password" id="admin_password" name="password" class="form-control" placeholder="••••••••" required>
      </div>

      <button type="submit" class="btn btn-danger btn-block" style="margin-top:1.5rem;padding:0.85rem;">
        <i data-lucide="lock"></i> Authenticate Administrator
      </button>
    </form>

    <div style="text-align:center;margin-top:1.5rem;">
      <a href="<?= BASE_URL ?>index.php" style="font-size:0.85rem;color:var(--text-dim);">&larr; Return to Public Portal</a>
    </div>
  </div>
</div>

<script>
  function fillAdmin() {
    document.getElementById('admin_email').value = 'admin@voltcraft.com';
    document.getElementById('admin_password').value = 'admin123';
  }
  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
