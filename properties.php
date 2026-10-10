<?php
// ============================================================================
// VoltCraft 3D — Customer-to-Customer Property Discovery Hub
// ============================================================================

$page_title = 'Customer Properties — Direct C2C Rentals & Sales | VoltCraft';
$page_desc = 'Discover flats, apartments, and houses directly from registered customers. Zero brokerage, authorized WhatsApp chat, and scheduled visits.';

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/property-functions.php';

$pdo = get_db_connection();
$logged_user = current_user();
$customer_id = $logged_user && $logged_user['role'] === 'customer' ? get_customer_id($logged_user['id']) : null;

// Filtering & Search Parameters
$search = clean_input($_GET['q'] ?? '');
$filter_city = clean_input($_GET['city'] ?? '');
$filter_type = clean_input($_GET['type'] ?? '');
$filter_purpose = clean_input($_GET['purpose'] ?? '');
$filter_min_price = !empty($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
$filter_max_price = !empty($_GET['max_price']) ? floatval($_GET['max_price']) : 0;
$filter_availability = clean_input($_GET['availability'] ?? '');
$sort = clean_input($_GET['sort'] ?? 'newest');

// Build SQL Query
$sql = "SELECT p.*, c.user_id, u.full_name as owner_name, u.avatar as owner_avatar, u.status as owner_status
        FROM customer_properties p
        JOIN customers c ON p.customer_id = c.id
        JOIN users u ON c.user_id = u.id
        WHERE p.verification_status = 'APPROVED'";
$params = [];

if (!empty($search)) {
    $sql .= " AND (p.title LIKE ? OR p.address LIKE ? OR p.city LIKE ? OR p.property_code LIKE ? OR p.description LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

if (!empty($filter_city)) {
    $sql .= " AND p.city = ?";
    $params[] = $filter_city;
}

if (!empty($filter_type)) {
    $sql .= " AND p.property_type = ?";
    $params[] = $filter_type;
}

if (!empty($filter_purpose)) {
    $sql .= " AND p.listing_type = ?";
    $params[] = $filter_purpose;
}

if ($filter_min_price > 0) {
    $sql .= " AND p.price >= ?";
    $params[] = $filter_min_price;
}

if ($filter_max_price > 0) {
    $sql .= " AND p.price <= ?";
    $params[] = $filter_max_price;
}

if (!empty($filter_availability)) {
    $sql .= " AND p.availability_status = ?";
    $params[] = $filter_availability;
}

// Sorting logic
switch ($sort) {
    case 'price_low':
        $sql .= " ORDER BY p.price ASC";
        break;
    case 'price_high':
        $sql .= " ORDER BY p.price DESC";
        break;
    case 'popular':
        $sql .= " ORDER BY p.views_count DESC";
        break;
    case 'newest':
    default:
        $sql .= " ORDER BY p.created_at DESC";
        break;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$properties = $stmt->fetchAll();

// Get unique cities for filter dropdown
$cities_stmt = $pdo->query("SELECT DISTINCT city FROM customer_properties WHERE verification_status = 'APPROVED' ORDER BY city ASC");
$available_cities = $cities_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/property.css">

<!-- Header Hero Banner -->
<section style="background: radial-gradient(circle at 50% 20%, rgba(0, 240, 255, 0.08) 0%, transparent 70%); padding: 4.5rem 0 2rem;">
  <div class="container">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:1.5rem; margin-bottom: 2.5rem;">
      <div>
        <div style="display:inline-flex; align-items:center; gap:0.5rem; background:rgba(0,240,255,0.1); border:1px solid rgba(0,240,255,0.25); padding:4px 12px; border-radius:var(--radius-full); margin-bottom:1rem; font-size:0.8rem; color:var(--primary); font-weight:600;">
          <i data-lucide="home" style="width:14px; height:14px;"></i> Customer-to-Customer Exchange &bull; Zero Brokerage
        </div>
        <h1 style="font-size:clamp(2rem, 3.5vw, 3rem); font-weight:800; line-height:1.2; margin-bottom:0.75rem;">
          Direct Property <span class="gradient-text">Listings &amp; Interactions</span>
        </h1>
        <p style="color:var(--text-muted); font-size:1.05rem; max-width:650px;">
          Explore flats, apartments, and independent homes listed directly by verified community customers. Connect immediately over authorized WhatsApp, arrange offline visits, or request bookings.
        </p>
      </div>

      <div style="display:flex; gap:1rem; flex-wrap:wrap;">
        <?php if ($logged_user && $logged_user['role'] === 'customer'): ?>
          <a href="<?= BASE_URL ?>customer/property-form.php" class="btn btn-primary btn-lg">
            <i data-lucide="plus-circle"></i> List Your Property
          </a>
          <a href="<?= BASE_URL ?>customer/properties.php" class="btn btn-outline btn-lg">
            <i data-lucide="layout-grid"></i> My Property Hub
          </a>
        <?php else: ?>
          <a href="<?= BASE_URL ?>customer/property-form.php" class="btn btn-primary btn-lg">
            <i data-lucide="plus-circle"></i> List Your Property
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Safety & Interaction Protocol Notice Strip -->
    <div class="safety-alert-box">
      <div class="safety-alert-icon">
        <i data-lucide="shield-check"></i>
      </div>
      <div style="font-size:0.88rem; line-height:1.5; color:var(--text-muted);">
        <strong style="color:var(--text-main);">Safe Direct Interaction &amp; Verification Protocol:</strong> 
        WhatsApp conversations and phone calls happen directly between parties outside the website. Booking requests, scheduled visit slots, and reservation statuses are maintained persistently inside your customer portal. All newly submitted properties undergo verification before publication.
      </div>
    </div>

    <!-- Multi-criteria Filter Bar -->
    <div class="glass-card" style="padding:1.5rem; margin-top:2rem;">
      <form action="<?= BASE_URL ?>properties.php" method="GET" style="display:grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)) auto; gap:1rem; align-items:end;">
        
        <!-- Search Keyword -->
        <div>
          <label style="display:block; font-size:0.75rem; text-transform:uppercase; color:var(--text-dim); margin-bottom:0.4rem; font-weight:700;">Search Keyword / Colony</label>
          <div style="position:relative;">
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="e.g. Indiranagar, 2BHK..." class="form-control" style="padding-left:2.4rem;">
            <i data-lucide="search" style="position:absolute; left:10px; top:50%; transform:translateY(-50%); width:16px; height:16px; color:var(--text-dim);"></i>
          </div>
        </div>

        <!-- City Filter -->
        <div>
          <label style="display:block; font-size:0.75rem; text-transform:uppercase; color:var(--text-dim); margin-bottom:0.4rem; font-weight:700;">City</label>
          <select name="city" class="form-control">
            <option value="">All Locations</option>
            <?php foreach ($available_cities as $city): ?>
              <option value="<?= e($city) ?>" <?= $filter_city === $city ? 'selected' : '' ?>><?= e($city) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Property Type -->
        <div>
          <label style="display:block; font-size:0.75rem; text-transform:uppercase; color:var(--text-dim); margin-bottom:0.4rem; font-weight:700;">Property Type</label>
          <select name="type" class="form-control">
            <option value="">All Types</option>
            <option value="flat" <?= $filter_type === 'flat' ? 'selected' : '' ?>>Flat</option>
            <option value="apartment" <?= $filter_type === 'apartment' ? 'selected' : '' ?>>Apartment</option>
            <option value="studio" <?= $filter_type === 'studio' ? 'selected' : '' ?>>Studio</option>
            <option value="independent_house" <?= $filter_type === 'independent_house' ? 'selected' : '' ?>>Independent House</option>
            <option value="villa" <?= $filter_type === 'villa' ? 'selected' : '' ?>>Villa</option>
            <option value="commercial" <?= $filter_type === 'commercial' ? 'selected' : '' ?>>Commercial / Office</option>
          </select>
        </div>

        <!-- Purpose (Rent / Sale) -->
        <div>
          <label style="display:block; font-size:0.75rem; text-transform:uppercase; color:var(--text-dim); margin-bottom:0.4rem; font-weight:700;">Listing Purpose</label>
          <select name="purpose" class="form-control">
            <option value="">All (Rent &amp; Sale)</option>
            <option value="rent" <?= $filter_purpose === 'rent' ? 'selected' : '' ?>>For Rent</option>
            <option value="sale" <?= $filter_purpose === 'sale' ? 'selected' : '' ?>>For Sale</option>
          </select>
        </div>

        <!-- Max Budget -->
        <div>
          <label style="display:block; font-size:0.75rem; text-transform:uppercase; color:var(--text-dim); margin-bottom:0.4rem; font-weight:700;">Max Budget (₹)</label>
          <input type="number" name="max_price" value="<?= $filter_max_price > 0 ? e($filter_max_price) : '' ?>" placeholder="e.g. 50000" class="form-control">
        </div>

        <!-- Submit & Reset Buttons -->
        <div style="display:flex; gap:0.5rem;">
          <button type="submit" class="btn btn-primary" style="height:44px; padding:0 1.25rem;">
            <i data-lucide="sliders-horizontal" style="width:16px;height:16px;"></i> Filter
          </button>
          <?php if (!empty($search) || !empty($filter_city) || !empty($filter_type) || !empty($filter_purpose) || $filter_max_price > 0): ?>
            <a href="<?= BASE_URL ?>properties.php" class="btn btn-outline" style="height:44px; padding:0 0.9rem;" title="Clear Filters">
              <i data-lucide="rotate-ccw" style="width:16px;height:16px;"></i>
            </a>
          <?php endif; ?>
        </div>

      </form>
    </div>
  </div>
</section>

<!-- Property Listings Grid Section -->
<section class="section" style="padding-top:2rem;">
  <div class="container">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
      <div>
        <h3 style="font-size:1.35rem; font-weight:700;">
          Available Properties <span style="font-size:0.95rem; color:var(--text-dim); font-weight:normal;">(<?= count($properties) ?> listings found)</span>
        </h3>
      </div>

      <div style="display:flex; align-items:center; gap:0.75rem;">
        <span style="font-size:0.82rem; color:var(--text-dim);">Sort By:</span>
        <select onchange="location.href=this.value" class="form-control" style="width:auto; padding:0.4rem 0.8rem; font-size:0.85rem;">
          <?php
            $currentQuery = $_GET;
            $buildSortUrl = function($s) use ($currentQuery) {
                $q = $currentQuery;
                $q['sort'] = $s;
                return BASE_URL . 'properties.php?' . http_build_query($q);
            };
          ?>
          <option value="<?= $buildSortUrl('newest') ?>" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
          <option value="<?= $buildSortUrl('price_low') ?>" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
          <option value="<?= $buildSortUrl('price_high') ?>" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
          <option value="<?= $buildSortUrl('popular') ?>" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Viewed</option>
        </select>
      </div>
    </div>

    <?php if (empty($properties)): ?>
      <div class="glass-card" style="text-align:center; padding:4rem 2rem;">
        <div style="width:64px; height:64px; border-radius:50%; background:rgba(0,240,255,0.08); display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem; color:var(--primary);">
          <i data-lucide="home" style="width:32px; height:32px;"></i>
        </div>
        <h3 style="margin-bottom:0.5rem;">No Properties Match Your Search</h3>
        <p style="color:var(--text-muted); max-width:450px; margin:0 auto 1.5rem;">
          Try expanding your search criteria or resetting filters to view all customer-listed properties.
        </p>
        <a href="<?= BASE_URL ?>properties.php" class="btn btn-primary">
          <i data-lucide="rotate-ccw"></i> Reset Filters
        </a>
      </div>
    <?php else: ?>
      <div class="property-grid">
        <?php foreach ($properties as $prop): 
          $facilities = json_decode($prop['facilities'] ?? '[]', true) ?: [];
          $is_saved = $customer_id ? is_property_saved($customer_id, $prop['id']) : false;
          
          // Generate WhatsApp contact link with prefilled template
          $wa_url = get_property_whatsapp_url(
              $prop['whatsapp_number'],
              $prop['property_code'],
              $prop['title'],
              $prop['city'] . ', ' . $prop['state']
          );
        ?>
          <div class="property-card">
            <!-- Media & Badges -->
            <div class="property-card-media">
              <img src="<?= e($prop['primary_image'] ?: 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=800&q=80') ?>" alt="<?= e($prop['title']) ?>" loading="lazy">
              
              <div class="property-media-overlay-top">
                <span class="prop-id-badge">
                  <i data-lucide="tag" style="width:11px; height:11px;"></i> <?= e($prop['property_code']) ?>
                </span>
                
                <button type="button" class="property-save-btn <?= $is_saved ? 'active' : '' ?>" onclick="toggleSaveProperty(<?= $prop['id'] ?>, this)" title="Save Property">
                  <i data-lucide="heart" style="width:16px; height:16px; <?= $is_saved ? 'fill:currentColor;' : '' ?>"></i>
                </button>
              </div>

              <div class="property-media-overlay-bottom">
                <span class="prop-purpose-badge prop-purpose-<?= e($prop['listing_type']) ?>">
                  For <?= ucfirst(e($prop['listing_type'])) ?>
                </span>
                <div>
                  <?= render_property_availability_badge($prop['availability_status']) ?>
                </div>
              </div>
            </div>

            <!-- Card Body -->
            <div class="property-card-body">
              <div class="property-location">
                <i data-lucide="map-pin" style="width:14px; height:14px; color:var(--primary);"></i>
                <span><?= e($prop['city']) ?>, <?= e($prop['state']) ?></span>
              </div>

              <h4 class="property-title">
                <a href="<?= BASE_URL ?>property-detail.php?id=<?= $prop['id'] ?>">
                  <?= e($prop['title']) ?>
                </a>
              </h4>

              <!-- Specs strip -->
              <div class="property-specs-strip">
                <div class="property-spec-item" title="Bedrooms">
                  <i data-lucide="bed"></i>
                  <span><?= $prop['bedrooms'] ?> BHK</span>
                </div>
                <div class="property-spec-item" title="Bathrooms">
                  <i data-lucide="bath"></i>
                  <span><?= $prop['bathrooms'] ?> Bath</span>
                </div>
                <?php if (!empty($prop['area_sqft'])): ?>
                  <div class="property-spec-item" title="Carpet Area">
                    <i data-lucide="maximize"></i>
                    <span><?= number_format($prop['area_sqft']) ?> sq.ft</span>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Facilities preview -->
              <?php if (!empty($facilities)): ?>
                <div class="property-facility-pills">
                  <?php foreach (array_slice($facilities, 0, 3) as $fac): ?>
                    <span class="facility-pill"><?= e($fac) ?></span>
                  <?php endforeach; ?>
                  <?php if (count($facilities) > 3): ?>
                    <span class="facility-pill">+<?= count($facilities) - 3 ?> more</span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <!-- Price Box -->
              <div class="property-price-box">
                <div>
                  <span class="property-price-val"><?= format_currency($prop['price']) ?></span>
                  <span class="property-price-period"><?= $prop['listing_type'] === 'rent' ? '/ month' : 'total' ?></span>
                </div>
                <?php if ($prop['security_deposit'] > 0): ?>
                  <div class="property-deposit-val">
                    Deposit: <?= format_currency($prop['security_deposit']) ?>
                  </div>
                <?php endif; ?>
              </div>

              <!-- Owner Information Strip -->
              <div class="property-owner-strip">
                <div class="property-owner-info">
                  <img src="<?= get_avatar_url($prop['owner_avatar'], $prop['owner_name']) ?>" alt="<?= e($prop['owner_name']) ?>" class="property-owner-avatar">
                  <div>
                    <div class="property-owner-name"><?= e($prop['owner_name']) ?></div>
                    <div class="property-owner-badge">
                      <i data-lucide="check-circle" style="width:11px; height:11px;"></i> Verified Customer
                    </div>
                  </div>
                </div>
                <span style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">
                  <?= ucfirst(e(str_replace('_', ' ', $prop['property_type']))) ?>
                </span>
              </div>

              <!-- Interaction Buttons Row -->
              <div class="property-actions-row">
                <!-- Direct WhatsApp Interaction -->
                <?php if ($wa_url): ?>
                  <a href="<?= e($wa_url) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm" onclick="logInquiry(<?= $prop['id'] ?>, 'WHATSAPP')" title="Chat on WhatsApp with Owner">
                    <i data-lucide="message-circle" style="width:15px; height:15px;"></i> WhatsApp
                  </a>
                <?php else: ?>
                  <button type="button" class="btn btn-outline btn-sm" disabled title="WhatsApp Not Configured">
                    <i data-lucide="message-circle" style="width:15px; height:15px;"></i> WhatsApp
                  </button>
                <?php endif; ?>

                <!-- View Dossier / Book Button -->
                <a href="<?= BASE_URL ?>property-detail.php?id=<?= $prop['id'] ?>" class="btn btn-primary btn-sm">
                  View &amp; Book <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
                </a>
              </div>

            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</section>

<!-- Callout Banner for Customers -->
<section class="section" style="padding-top:2rem; padding-bottom:5rem;">
  <div class="container">
    <div class="glass-card" style="background:linear-gradient(135deg, rgba(16,24,43,0.92) 0%, rgba(15,30,58,0.85) 100%); border-color:var(--border-glow); padding:3.5rem 2.5rem; text-align:center;">
      <span class="badge badge-primary" style="margin-bottom:1rem;">OWNER-DIRECT ADVANTAGE</span>
      <h2 style="font-size:clamp(1.8rem, 3.2vw, 2.5rem); margin-bottom:1rem;">
        Own a Property or Have a Flat to Rent?
      </h2>
      <p style="max-width:620px; margin:0 auto 2rem; color:var(--text-muted); font-size:1.05rem;">
        Publish your listing directly to thousands of verified hardware professionals, engineers, and platform customers. Receive instant inquiries over WhatsApp, schedule offline visits, and confirm bookings with zero middlemen.
      </p>
      <div style="display:flex; justify-content:center; gap:1.25rem; flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>customer/property-form.php" class="btn btn-primary btn-lg">
          <i data-lucide="plus-circle"></i> List Your Property Now
        </a>
        <a href="<?= BASE_URL ?>how-it-works.php" class="btn btn-outline btn-lg">
          <i data-lucide="help-circle"></i> Learn How It Works
        </a>
      </div>
    </div>
  </div>
</section>

<script>
// Toggle Save / Bookmark Property via AJAX
function toggleSaveProperty(propId, btnElement) {
  fetch('<?= BASE_URL ?>api/property.php?action=toggle_save&property_id=' + propId, {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(res => res.json())
  .then(data => {
    if (data.status === 'success') {
      if (data.data.saved) {
        btnElement.classList.add('active');
        btnElement.querySelector('i').style.fill = 'currentColor';
      } else {
        btnElement.classList.remove('active');
        btnElement.querySelector('i').style.fill = 'none';
      }
    } else if (data.status === 'unauthorized') {
      alert('Please sign in to save properties to your favorites.');
      window.location.href = '<?= BASE_URL ?>login.php?redirect=properties.php';
    }
  })
  .catch(err => console.error('Save failed', err));
}

// Log Inquiry interaction
function logInquiry(propId, channel) {
  fetch('<?= BASE_URL ?>api/property.php?action=log_inquiry&property_id=' + propId + '&channel=' + channel, {
    method: 'POST'
  }).catch(() => {});
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
