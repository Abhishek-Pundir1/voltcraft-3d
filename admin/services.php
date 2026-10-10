<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');
$pdo = get_db_connection();

// Toggle service active state
if (isset($_GET['toggle_active'])) {
    $s_id = intval($_GET['toggle_active']);
    $pdo->prepare("UPDATE services SET is_active = IF(is_active=1, 0, 1) WHERE id = ?")->execute([$s_id]);
    set_flash('info', 'Service active status updated.');
    header("Location: " . BASE_URL . "admin/services.php");
    exit;
}

// Handle Add / Edit Service Form
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = intval($_POST['service_id'] ?? 0);
    $name = clean_input($_POST['name'] ?? '');
    $category = clean_input($_POST['category'] ?? '');
    $icon = clean_input($_POST['icon'] ?? 'cpu');
    $description = clean_input($_POST['description'] ?? '');
    $base_price = floatval($_POST['base_price'] ?? 1000);
    $estimated_time = clean_input($_POST['estimated_time'] ?? '2-4 Hours');

    if (empty($name) || empty($category) || empty($description)) {
        $error = 'Please fill out all required fields.';
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));

        if ($service_id > 0) {
            // Edit
            $pdo->prepare("UPDATE services SET name = ?, slug = ?, category = ?, icon = ?, description = ?, base_price = ?, estimated_time = ? WHERE id = ?")
                ->execute([$name, $slug, $category, $icon, $description, $base_price, $estimated_time, $service_id]);
            $success = 'Service updated successfully!';
        } else {
            // Add new
            $pdo->prepare("INSERT INTO services (name, slug, category, icon, description, base_price, estimated_time, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)")
                ->execute([$name, $slug, $category, $icon, $description, $base_price, $estimated_time]);
            $success = 'New electronic service category added!';
        }
    }
}

$services = $pdo->query("SELECT s.*, COUNT(b.id) as booking_count 
                         FROM services s 
                         LEFT JOIN bookings b ON s.id = b.service_id 
                         GROUP BY s.id 
                         ORDER BY s.id ASC")->fetchAll();

$page_title = 'Manage Services Catalog — ' . APP_NAME;
$active_nav = 'services';
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
  <?php require_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

  <main class="dashboard-main">
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn"><i data-lucide="menu"></i></button>
        <h2>Electronics Services Catalog</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" onclick="openNewServiceModal()" class="btn btn-primary btn-sm">
          <i data-lucide="plus-circle"></i> Add Service Category
        </button>
      </div>
    </header>

    <div class="dashboard-content">
      <?php if ($success): ?><div class="alert alert-success"><i data-lucide="check-circle"></i> <?= e($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><i data-lucide="alert-triangle"></i> <?= e($error) ?></div><?php endif; ?>

      <div class="glass-card">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
          <h3 style="font-size:1.2rem;">All Platform Services (<?= count($services) ?>)</h3>
        </div>

        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Service Name</th>
                <th>Category</th>
                <th>Estimated Time</th>
                <th>Base Fee</th>
                <th>20% Advance</th>
                <th>Bookings Count</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($services as $s): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                      <div style="width:34px;height:34px;border-radius:6px;background:rgba(0,240,255,0.1);color:var(--primary);display:flex;align-items:center;justify-content:center;">
                        <i data-lucide="<?= e($s['icon']) ?>" style="width:16px;height:16px;"></i>
                      </div>
                      <div>
                        <strong><?= e($s['name']) ?></strong>
                        <div style="font-size:0.75rem;color:var(--text-dim);max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($s['description']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge badge-secondary"><?= e($s['category']) ?></span></td>
                  <td><?= e($s['estimated_time']) ?></td>
                  <td class="mono" style="font-weight:700;color:var(--primary);"><?= format_currency($s['base_price']) ?></td>
                  <td class="mono" style="color:var(--warning);"><?= format_currency($s['base_price'] * ADVANCE_PERCENTAGE) ?></td>
                  <td><strong><?= $s['booking_count'] ?></strong></td>
                  <td>
                    <span class="badge badge-<?= $s['is_active'] ? 'success' : 'muted' ?>">
                      <?= $s['is_active'] ? 'Active' : 'Disabled' ?>
                    </span>
                  </td>
                  <td>
                    <div style="display:flex;gap:0.4rem;">
                      <button type="button" onclick="editService(<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>)" class="btn btn-outline btn-sm">
                        Edit
                      </button>
                      <a href="<?= BASE_URL ?>admin/services.php?toggle_active=<?= $s['id'] ?>" class="btn btn-outline btn-sm" style="color:<?= $s['is_active'] ? 'var(--danger)' : 'var(--success)' ?>;">
                        <?= $s['is_active'] ? 'Disable' : 'Enable' ?>
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Modal: Add / Edit Service -->
<div class="modal-backdrop" id="serviceModal">
  <div class="modal-dialog">
    <form method="POST" action="">
      <input type="hidden" name="service_id" id="modal_service_id" value="0">
      <div class="modal-header">
        <h3 id="modalServiceTitle" style="font-size:1.15rem;">Add Electronic Service</h3>
        <button type="button" class="modal-close" data-modal-close>&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Service Title *</label>
          <input type="text" name="name" id="modal_service_name" class="form-control" placeholder="e.g. PCB Micro-Soldering" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Category Domain *</label>
            <input type="text" name="category" id="modal_service_category" class="form-control" placeholder="e.g. Hardware Engineering" required>
          </div>
          <div class="form-group">
            <label class="form-label">Lucide Icon Key</label>
            <input type="text" name="icon" id="modal_service_icon" class="form-control" placeholder="cpu, zap, settings" value="cpu">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Base Diagnostic Price (₹) *</label>
            <input type="number" step="100" name="base_price" id="modal_service_price" class="form-control" placeholder="2500" required>
          </div>
          <div class="form-group">
            <label class="form-label">Estimated Service Time</label>
            <input type="text" name="estimated_time" id="modal_service_time" class="form-control" placeholder="2-4 Hours" value="2-4 Hours">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Service Description *</label>
          <textarea name="description" id="modal_service_desc" class="form-control form-textarea" placeholder="Detail the diagnostic procedures..." required></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
        <button type="submit" class="btn btn-primary btn-sm">Save Service</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script>
  function openNewServiceModal() {
    document.getElementById('modal_service_id').value = '0';
    document.getElementById('modalServiceTitle').innerText = 'Add Electronic Service';
    document.getElementById('modal_service_name').value = '';
    document.getElementById('modal_service_category').value = '';
    document.getElementById('modal_service_price').value = '2500';
    document.getElementById('modal_service_desc').value = '';
    document.getElementById('serviceModal').classList.add('show');
  }

  function editService(s) {
    document.getElementById('modal_service_id').value = s.id;
    document.getElementById('modalServiceTitle').innerText = 'Edit ' + s.name;
    document.getElementById('modal_service_name').value = s.name;
    document.getElementById('modal_service_category').value = s.category;
    document.getElementById('modal_service_icon').value = s.icon;
    document.getElementById('modal_service_price').value = s.base_price;
    document.getElementById('modal_service_time').value = s.estimated_time;
    document.getElementById('modal_service_desc').value = s.description;
    document.getElementById('serviceModal').classList.add('show');
  }

  if (typeof lucide !== 'undefined') lucide.createIcons();
</script>
</body>
</html>
