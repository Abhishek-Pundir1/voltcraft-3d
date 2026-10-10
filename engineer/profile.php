<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('engineer');
$user = current_user();
$engineer_id = get_engineer_id($user['id']);

$pdo = get_db_connection();

// Fetch engineer data
$eng_stmt = $pdo->prepare("SELECT * FROM engineers WHERE id = ?");
$eng_stmt->execute([$engineer_id]);
$engineer = $eng_stmt->fetch();

$skills_stmt = $pdo->prepare("SELECT * FROM engineer_skills WHERE engineer_id = ? ORDER BY id ASC");
$skills_stmt->execute([$engineer_id]);
$skills = $skills_stmt->fetchAll();

$all_services = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$current_services = $pdo->prepare("SELECT service_id, custom_rate FROM engineer_services WHERE engineer_id = ?");
$current_services->execute([$engineer_id]);
$assigned_services = $current_services->fetchAll(PDO::FETCH_KEY_PAIR);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = clean_input($_POST['full_name'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    $title = clean_input($_POST['title'] ?? '');
    $bio = clean_input($_POST['bio'] ?? '');
    $location = clean_input($_POST['location'] ?? '');
    $qualification = clean_input($_POST['qualification'] ?? '');
    $hourly_rate = floatval($_POST['hourly_rate'] ?? 500);
    $upi_id = clean_input($_POST['upi_id'] ?? '');

    // Handle QR code image upload if present
    $qr_path = $engineer['upi_qr_image'];
    if (!empty($_FILES['qr_code_file']['name'])) {
        $allowed = ['jpg', 'jpeg', 'png', 'svg', 'webp'];
        $fname = $_FILES['qr_code_file']['name'];
        $ext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $qr_target = 'qr_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $qr_dir = __DIR__ . '/../assets/uploads/';
            if (!is_dir($qr_dir)) mkdir($qr_dir, 0777, true);
            if (move_uploaded_file($_FILES['qr_code_file']['tmp_name'], $qr_dir . $qr_target)) {
                $qr_path = 'assets/uploads/' . $qr_target;
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?")
            ->execute([$full_name, $phone, $user['id']]);

        $pdo->prepare("UPDATE engineers SET title = ?, bio = ?, location = ?, qualification = ?, hourly_rate = ?, upi_id = ?, upi_qr_image = ? WHERE id = ?")
            ->execute([$title, $bio, $location, $qualification, $hourly_rate, $upi_id, $qr_path, $engineer_id]);

        // Update offered services
        $selected_services = $_POST['services'] ?? [];
        $pdo->prepare("DELETE FROM engineer_services WHERE engineer_id = ?")->execute([$engineer_id]);
        $ins_es = $pdo->prepare("INSERT INTO engineer_services (engineer_id, service_id, custom_rate) VALUES (?, ?, ?)");
        foreach ($selected_services as $s_id) {
            $custom_rate = floatval($_POST['rate_' . $s_id] ?? 0);
            $ins_es->execute([$engineer_id, $s_id, $custom_rate ?: null]);
        }

        // Add new skill if entered
        $new_skill_name = clean_input($_POST['new_skill_name'] ?? '');
        $new_skill_prof = intval($_POST['new_skill_prof'] ?? 90);
        if (!empty($new_skill_name)) {
            $pdo->prepare("INSERT INTO engineer_skills (engineer_id, skill_name, proficiency) VALUES (?, ?, ?)")
                ->execute([$engineer_id, $new_skill_name, min(100, max(50, $new_skill_prof))]);
        }

        $pdo->commit();
        $success = 'Engineer profile, services, and UPI credentials updated!';

        // Refresh
        $eng_stmt->execute([$engineer_id]);
        $engineer = $eng_stmt->fetch();
        $skills_stmt->execute([$engineer_id]);
        $skills = $skills_stmt->fetchAll();
        $current_services->execute([$engineer_id]);
        $assigned_services = $current_services->fetchAll(PDO::FETCH_KEY_PAIR);
        $user = current_user();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'Failed to update profile: ' . $e->getMessage();
    }
}

// Skill deletion
if (isset($_GET['del_skill'])) {
    $del_id = intval($_GET['del_skill']);
    $pdo->prepare("DELETE FROM engineer_skills WHERE id = ? AND engineer_id = ?")->execute([$del_id, $engineer_id]);
    header("Location: " . BASE_URL . "engineer/profile.php");
    exit;
}

$page_title = 'Edit Engineer Profile & UPI Gateway — ' . APP_NAME;
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
  <?php require_once __DIR__ . '/../includes/engineer-sidebar.php'; ?>

  <main class="dashboard-main">
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn"><i data-lucide="menu"></i></button>
        <h2>Engineer Profile &amp; Escrow Credentials</h2>
      </div>
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>engineer-detail.php?id=<?= $engineer['id'] ?>" target="_blank" class="btn btn-outline btn-sm">
          <i data-lucide="external-link"></i> View Public Profile
        </a>
      </div>
    </header>

    <div class="dashboard-content" style="max-width:960px;">
      <?php if ($success): ?>
        <div class="alert alert-success"><i data-lucide="check-circle"></i> <?= e($success) ?></div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><i data-lucide="alert-triangle"></i> <?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="" enctype="multipart/form-data">
        <!-- 1. Basic Credentials -->
        <div class="glass-card" style="margin-bottom:2rem;padding:2.25rem;">
          <h3 style="font-size:1.25rem;margin-bottom:1.5rem;color:var(--primary);display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="user"></i> Personal &amp; Professional Details
          </h3>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Full Name *</label>
              <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address (Read-Only)</label>
              <input type="email" class="form-control" value="<?= e($user['email']) ?>" readonly style="opacity:0.7;">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Contact Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= e($user['phone']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Hourly Diagnostic Rate (₹) *</label>
              <input type="number" step="50" name="hourly_rate" class="form-control" value="<?= e($engineer['hourly_rate']) ?>" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Specialization Title *</label>
              <input type="text" name="title" class="form-control" value="<?= e($engineer['title']) ?>" placeholder="e.g. Senior Micro-Soldering & Embedded Specialist" required>
            </div>
            <div class="form-group">
              <label class="form-label">Base Location / City *</label>
              <input type="text" name="location" class="form-control" value="<?= e($engineer['location']) ?>" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Academic &amp; Certification Qualifications</label>
            <input type="text" name="qualification" class="form-control" value="<?= e($engineer['qualification']) ?>" placeholder="e.g. B.Tech Electronics & Comm, IPC-A-610 Master Certified">
          </div>

          <div class="form-group">
            <label class="form-label">Professional Bio / Diagnostic Scope</label>
            <textarea name="bio" class="form-control form-textarea" placeholder="Detail your laboratory experience, specialized test equipment, and circuit diagnostics approach..."><?= e($engineer['bio']) ?></textarea>
          </div>
        </div>

        <!-- 2. UPI Escrow Credentials & QR Code -->
        <div class="glass-card" style="margin-bottom:2rem;padding:2.25rem;">
          <h3 style="font-size:1.25rem;margin-bottom:1rem;color:var(--primary);display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="qr-code"></i> UPI ID &amp; QR Code (20% Advance Escrow Destination)
          </h3>
          <p style="font-size:0.85rem;color:var(--text-dim);margin-bottom:1.5rem;">
            Clients will scan this QR code or remit payments to this UPI address when paying their 20% advance booking deposit.
          </p>

          <div style="display:grid;grid-template-columns:1fr 220px;gap:2rem;align-items:start;">
            <div>
              <div class="form-group">
                <label class="form-label">Verified UPI ID *</label>
                <input type="text" name="upi_id" class="form-control mono" value="<?= e($engineer['upi_id']) ?>" placeholder="yourname@okhdfcbank" required>
                <div class="form-hint">Ensure this ID is active with your banking application.</div>
              </div>

              <div class="form-group">
                <label class="form-label">Upload Custom UPI QR Code Image (Optional)</label>
                <input type="file" name="qr_code_file" class="form-control" accept="image/*">
                <div class="form-hint">Accepted: PNG, JPG, SVG, WEBP</div>
              </div>
            </div>

            <div style="text-align:center;">
              <div style="font-size:0.75rem;color:var(--text-dim);margin-bottom:0.5rem;">CURRENT QR CODE</div>
              <div class="qr-container" style="max-width:180px;padding:0.75rem;">
                <img src="<?= get_upi_qr_url($engineer['upi_qr_image']) ?>" alt="QR" style="width:140px;height:140px;">
                <div style="font-size:0.72rem;font-weight:700;color:#0f172a;word-break:break-all;"><?= e($engineer['upi_id']) ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- 3. Technical Skills Manager -->
        <div class="glass-card" style="margin-bottom:2rem;padding:2.25rem;">
          <h3 style="font-size:1.25rem;margin-bottom:1.25rem;color:var(--primary);display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="cpu"></i> Technical Skills &amp; Proficiency
          </h3>

          <div style="display:flex;flex-wrap:wrap;gap:0.6rem;margin-bottom:1.5rem;">
            <?php foreach ($skills as $sk): ?>
              <div style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.4rem 0.85rem;background:rgba(255,255,255,0.04);border:1px solid var(--border-glass);border-radius:var(--radius-sm);font-size:0.85rem;">
                <span><strong><?= e($sk['skill_name']) ?></strong> (<?= $sk['proficiency'] ?>%)</span>
                <a href="<?= BASE_URL ?>engineer/profile.php?del_skill=<?= $sk['id'] ?>" onclick="return confirm('Delete this skill?')" style="color:var(--danger);font-weight:bold;">&times;</a>
              </div>
            <?php endforeach; ?>
          </div>

          <div style="background:rgba(255,255,255,0.02);padding:1rem;border-radius:var(--radius-sm);border:1px dashed var(--border-glass);">
            <div style="font-size:0.85rem;font-weight:600;margin-bottom:0.75rem;">+ Add New Technical Skill</div>
            <div class="form-row" style="grid-template-columns:2fr 1fr;">
              <input type="text" name="new_skill_name" class="form-control" placeholder="Skill name (e.g. Oscilloscope Waveform Analysis)">
              <input type="number" name="new_skill_prof" class="form-control" min="50" max="100" placeholder="Proficiency % (90)">
            </div>
          </div>
        </div>

        <!-- 4. Services Offered Selection -->
        <div class="glass-card" style="margin-bottom:2rem;padding:2.25rem;">
          <h3 style="font-size:1.25rem;margin-bottom:1rem;color:var(--primary);display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="layers"></i> Services Offered &amp; Custom Rates
          </h3>
          <p style="font-size:0.85rem;color:var(--text-dim);margin-bottom:1.25rem;">
            Select which service types you handle. You can optionally set a custom diagnostic estimate.
          </p>

          <div style="display:flex;flex-direction:column;gap:0.75rem;">
            <?php foreach ($all_services as $as): ?>
              <?php $is_checked = isset($assigned_services[$as['id']]); ?>
              <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem 1rem;background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:var(--radius-sm);">
                <label style="display:flex;align-items:center;gap:0.75rem;cursor:pointer;flex:1;">
                  <input type="checkbox" name="services[]" value="<?= $as['id'] ?>" <?= $is_checked ? 'checked' : '' ?> style="width:18px;height:18px;">
                  <div>
                    <div style="font-weight:600;font-size:0.92rem;"><?= e($as['name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-dim);"><?= e($as['category']) ?> &bull; Default Base: <?= format_currency($as['base_price']) ?></div>
                  </div>
                </label>

                <div style="display:flex;align-items:center;gap:0.5rem;">
                  <span style="font-size:0.75rem;color:var(--text-dim);">Custom Rate (₹):</span>
                  <input type="number" name="rate_<?= $as['id'] ?>" class="form-control" style="width:120px;padding:0.4rem 0.6rem;" value="<?= e($assigned_services[$as['id']] ?? '') ?>" placeholder="<?= $as['base_price'] ?>">
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-bottom:3rem;">
          <i data-lucide="save"></i> Save All Profile Changes
        </button>
      </form>
    </div>
  </main>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script>
  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
