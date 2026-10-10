<?php
$page_title = 'Find Verified Electronics Engineers — VoltCraft 3D';
$page_desc = 'Search and filter certified electronics, robotics, and PCB hardware engineers by skill, rating, location, and hourly rate.';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$services = $pdo->query("SELECT id, name FROM services WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Pre-selected filter parameters
$selected_service_id = intval($_GET['service_id'] ?? 0);
$search_query = trim($_GET['q'] ?? '');
?>

<div class="section" style="padding-top:3.5rem;">
  <div class="container">
    <div class="section-title" style="margin-bottom:2.5rem;">
      <span class="badge badge-success">VERIFIED TALENT POOL</span>
      <h2>Find &amp; Hire Electronics Engineers</h2>
      <p>Search by hardware expertise, diagnostic specializations, location, or verified ratings.</p>
    </div>

    <!-- Filter Control Panel -->
    <div class="glass-card" style="margin-bottom:2.5rem;padding:1.5rem 1.75rem;">
      <form id="engineerFilterForm" onsubmit="event.preventDefault(); applyFilters();">
        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));gap:1rem;margin-bottom:1rem;">
          <!-- Keyword Search -->
          <div>
            <label class="form-label" style="font-size:0.8rem;">Search Keywords</label>
            <input type="text" id="filter_q" class="form-control" placeholder="Engineer name, title, bio..." value="<?= e($search_query) ?>">
          </div>

          <!-- Service Category -->
          <div>
            <label class="form-label" style="font-size:0.8rem;">Service Domain</label>
            <select id="filter_service" class="form-select">
              <option value="">All Services</option>
              <?php foreach ($services as $srv): ?>
                <option value="<?= $srv['id'] ?>" <?= $selected_service_id == $srv['id'] ? 'selected' : '' ?>>
                  <?= e($srv['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Location -->
          <div>
            <label class="form-label" style="font-size:0.8rem;">Location / City</label>
            <input type="text" id="filter_location" class="form-control" placeholder="e.g. Bangalore, Mumbai, Delhi">
          </div>

          <!-- Specific Skill -->
          <div>
            <label class="form-label" style="font-size:0.8rem;">Specific Skill</label>
            <input type="text" id="filter_skill" class="form-control" placeholder="e.g. BGA, PLC, BMS, ESC">
          </div>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:1rem;align-items:flex-end;">
          <!-- Minimum Rating -->
          <div>
            <label class="form-label" style="font-size:0.8rem;">Minimum Rating</label>
            <select id="filter_rating" class="form-select">
              <option value="0">Any Rating</option>
              <option value="4.5">4.5+ Stars</option>
              <option value="4.8">4.8+ Stars</option>
              <option value="4.9">4.9+ Stars</option>
            </select>
          </div>

          <!-- Availability -->
          <div>
            <label class="form-label" style="font-size:0.8rem;">Availability</label>
            <select id="filter_availability" class="form-select">
              <option value="">Any Availability</option>
              <option value="available">Available Now</option>
              <option value="busy">Busy</option>
            </select>
          </div>

          <!-- Sort Order -->
          <div>
            <label class="form-label" style="font-size:0.8rem;">Sort By</label>
            <select id="filter_sort" class="form-select">
              <option value="rating_desc">Highest Rated</option>
              <option value="experience_desc">Most Experienced</option>
              <option value="price_asc">Price: Low to High</option>
              <option value="price_desc">Price: High to Low</option>
              <option value="reviews_desc">Most Reviewed</option>
            </select>
          </div>

          <!-- Action Buttons -->
          <div style="display:flex;gap:0.5rem;">
            <button type="button" onclick="applyFilters()" class="btn btn-primary" style="flex:1;">
              <i data-lucide="filter" style="width:16px;height:16px;"></i> Filter
            </button>
            <button type="button" onclick="resetFilters()" class="btn btn-outline" style="padding:0.75rem;">
              <i data-lucide="rotate-ccw" style="width:16px;height:16px;"></i>
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Live Results Status Counter -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
      <div id="resultsCountText" style="font-size:0.92rem;color:var(--text-dim);font-weight:600;">
        Showing verified electronics engineers...
      </div>
      <div id="filterLoadingIndicator" style="display:none;font-size:0.85rem;color:var(--primary);">
        <i data-lucide="loader" style="animation:spin 1s linear infinite;width:14px;height:14px;vertical-align:middle;"></i> Searching...
      </div>
    </div>

    <!-- Engineers Cards Container -->
    <div id="engineersContainer" style="display:grid;grid-template-columns:repeat(auto-fit, minmax(360px, 1fr));gap:2rem;">
      <!-- Populated via AJAX and initialized on page load -->
    </div>
  </div>
</div>

<script>
  async function fetchEngineers(params = {}) {
    const container = document.getElementById('engineersContainer');
    const loading = document.getElementById('filterLoadingIndicator');
    const countText = document.getElementById('resultsCountText');

    if (loading) loading.style.display = 'inline-block';

    const res = await ApiClient.filterEngineers(params);

    if (loading) loading.style.display = 'none';

    if (res.status === 'success') {
      const engineers = res.data.engineers;
      countText.innerText = `Found ${engineers.length} verified electronics engineer${engineers.length === 1 ? '' : 's'}`;

      if (engineers.length === 0) {
        container.innerHTML = `
          <div class="glass-card" style="grid-column:1/-1;text-align:center;padding:4rem 2rem;">
            <div style="font-size:3rem;margin-bottom:1rem;">🔍</div>
            <h3>No Engineers Found</h3>
            <p style="margin-top:0.5rem;">Try relaxing your search terms, removing filters, or choosing a different domain.</p>
            <button onclick="resetFilters()" class="btn btn-outline" style="margin-top:1.5rem;">Reset All Filters</button>
          </div>
        `;
        return;
      }

      container.innerHTML = engineers.map(eng => `
        <div class="glass-card glass-card-tilt" style="display:flex;flex-direction:column;justify-content:space-between;">
          <div>
            <!-- Header -->
            <div style="display:flex;align-items:flex-start;gap:1.25rem;margin-bottom:1.25rem;">
              <img src="${eng.avatar_url}" alt="${eng.name}" style="width:72px;height:72px;border-radius:50%;object-fit:cover;border:2px solid var(--primary);box-shadow:0 0 15px var(--primary-glow);">
              <div style="flex:1;">
                <div style="display:flex;align-items:center;gap:0.4rem;">
                  <h4 style="font-size:1.2rem;">${eng.name}</h4>
                  <span title="IPC Certified / Identity Verified" style="color:var(--primary);">
                    <i data-lucide="check-circle" style="width:16px;height:16px;"></i>
                  </span>
                </div>
                <div style="font-size:0.85rem;color:var(--primary);font-weight:600;margin-top:0.2rem;">${eng.title}</div>
                <div style="font-size:0.8rem;color:var(--text-dim);margin-top:0.25rem;">
                  <i data-lucide="map-pin" style="width:12px;height:12px;vertical-align:middle;"></i> ${eng.location}
                </div>
              </div>
            </div>

            <!-- Bio -->
            <p style="font-size:0.88rem;line-height:1.55;color:var(--text-muted);margin-bottom:1.25rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
              ${eng.bio || 'Experienced electronics and circuit design specialist.'}
            </p>

            <!-- Badges -->
            <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:1.25rem;">
              <span class="badge badge-warning">★ ${eng.rating.toFixed(2)} (${eng.total_reviews} reviews)</span>
              <span class="badge badge-info">${eng.experience}+ Yrs Exp</span>
              <span class="badge badge-${eng.availability === 'available' ? 'success' : 'muted'}">
                <span class="badge-dot"></span> ${eng.availability === 'available' ? 'Available' : 'Busy'}
              </span>
            </div>

            <!-- Skills Pills -->
            <div style="margin-bottom:1.25rem;">
              <div style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;margin-bottom:0.4rem;font-weight:600;">Key Skills</div>
              <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
                ${eng.skills.slice(0, 3).map(sk => `<span style="font-size:0.75rem;padding:2px 8px;border-radius:4px;background:rgba(255,255,255,0.06);color:var(--text-muted);">${sk}</span>`).join('')}
              </div>
            </div>
          </div>

          <!-- Bottom Footer -->
          <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border-glass);padding-top:1.25rem;">
            <div>
              <span style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;">Hourly Rate</span>
              <div style="font-size:1.35rem;font-weight:800;font-family:var(--font-mono);color:var(--text-main);">
                ${eng.hourly_rate_formatted}/hr
              </div>
            </div>

            <div style="display:flex;gap:0.6rem;">
              <a href="${eng.profile_url}" class="btn btn-outline btn-sm">Profile</a>
              <a href="<?= BASE_URL ?>customer/new-request.php?engineer_id=${eng.id}" class="btn btn-primary btn-sm">Book</a>
            </div>
          </div>
        </div>
      `).join('');

      if (typeof lucide !== 'undefined') lucide.createIcons();
    }
  }

  function applyFilters() {
    const params = {
      q: document.getElementById('filter_q').value.trim(),
      service_id: document.getElementById('filter_service').value,
      location: document.getElementById('filter_location').value.trim(),
      skill: document.getElementById('filter_skill').value.trim(),
      min_rating: document.getElementById('filter_rating').value,
      availability: document.getElementById('filter_availability').value,
      sort: document.getElementById('filter_sort').value
    };
    fetchEngineers(params);
  }

  function resetFilters() {
    document.getElementById('engineerFilterForm').reset();
    applyFilters();
  }

  // Initial load
  document.addEventListener('DOMContentLoaded', () => {
    applyFilters();
  });
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
