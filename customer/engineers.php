<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('customer');
$page_title = 'Browse & Hire Engineers — ' . APP_NAME;
$active_nav = 'engineers';

$pdo = get_db_connection();
$services = $pdo->query("SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
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
        <h2>Browse Verified Engineers</h2>
      </div>
      <div class="topbar-actions">
        <button type="button" class="theme-toggle-btn"><i data-lucide="sun"></i></button>
      </div>
    </header>

    <div class="dashboard-content">
      <!-- Filter Bar -->
      <div class="glass-card" style="margin-bottom:2rem;padding:1.5rem;">
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;margin-bottom:1rem;">
          <input type="text" id="cust_q" class="form-control" placeholder="Search engineer name, specialty..." oninput="filterPortalEngineers()">
          <select id="cust_srv" class="form-select" onchange="filterPortalEngineers()">
            <option value="">All Services</option>
            <?php foreach ($services as $srv): ?>
              <option value="<?= $srv['id'] ?>"><?= e($srv['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <select id="cust_avail" class="form-select" onchange="filterPortalEngineers()">
            <option value="">Any Availability</option>
            <option value="available">Available Now</option>
            <option value="busy">Busy</option>
          </select>
          <select id="cust_sort" class="form-select" onchange="filterPortalEngineers()">
            <option value="rating_desc">Highest Rated</option>
            <option value="price_asc">Lowest Rate</option>
            <option value="experience_desc">Most Experienced</option>
          </select>
        </div>
      </div>

      <!-- Engineers Container -->
      <div id="portalEngContainer" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:1.5rem;">
        <!-- Populated via JS -->
      </div>
    </div>
  </main>
</div>

<script src="<?= BASE_URL ?>assets/js/main.js"></script>
<script src="<?= BASE_URL ?>assets/js/api-client.js"></script>
<script>
  async function filterPortalEngineers() {
    const params = {
      q: document.getElementById('cust_q').value.trim(),
      service_id: document.getElementById('cust_srv').value,
      availability: document.getElementById('cust_avail').value,
      sort: document.getElementById('cust_sort').value
    };

    const res = await ApiClient.filterEngineers(params);
    const container = document.getElementById('portalEngContainer');

    if (res.status === 'success') {
      const list = res.data.engineers;
      if (list.length === 0) {
        container.innerHTML = '<div class="glass-card" style="grid-column:1/-1;text-align:center;padding:3rem;">No matching engineers found.</div>';
        return;
      }

      container.innerHTML = list.map(eng => `
        <div class="glass-card glass-card-tilt" style="display:flex;flex-direction:column;justify-content:space-between;">
          <div>
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1rem;">
              <img src="${eng.avatar_url}" alt="Engineer" style="width:60px;height:60px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);">
              <div>
                <h4 style="font-size:1.1rem;">${eng.name}</h4>
                <div style="font-size:0.8rem;color:var(--primary);font-weight:600;">${eng.title}</div>
                <div style="font-size:0.75rem;color:var(--text-dim);">${eng.location}</div>
              </div>
            </div>

            <div style="display:flex;gap:0.4rem;margin-bottom:1rem;">
              <span class="badge badge-warning" style="font-size:0.72rem;">★ ${eng.rating.toFixed(2)} (${eng.total_reviews})</span>
              <span class="badge badge-${eng.availability === 'available' ? 'success' : 'muted'}" style="font-size:0.72rem;">
                ${eng.availability === 'available' ? 'Available' : 'Busy'}
              </span>
            </div>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border-glass);padding-top:1rem;">
            <div>
              <span style="font-size:0.75rem;color:var(--text-dim);">Hourly Rate</span>
              <div style="font-size:1.2rem;font-weight:800;color:var(--text-main);font-family:var(--font-mono);">${eng.hourly_rate_formatted}/hr</div>
            </div>
            <a href="<?= BASE_URL ?>customer/new-request.php?engineer_id=${eng.id}" class="btn btn-primary btn-sm">
              <i data-lucide="calendar-plus" style="width:14px;height:14px;"></i> Book
            </a>
          </div>
        </div>
      `).join('');

      if (typeof lucide !== 'undefined') lucide.createIcons();
    }
  }

  document.addEventListener('DOMContentLoaded', filterPortalEngineers);
</script>
</body>
</html>
