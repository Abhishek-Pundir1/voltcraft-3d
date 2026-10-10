<?php
// ============================================================================
// VoltCraft 3D — Customer Property Listing Form (Add / Edit)
// ============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/property-functions.php';

require_role('customer');
$user = current_user();
$customer_id = get_customer_id($user['id']);

$pdo = get_db_connection();
$edit_id = intval($_GET['id'] ?? 0);
$property = null;

if ($edit_id > 0) {
    // Ensure the customer owns the listing (Requirement 7)
    $stmt = $pdo->prepare("SELECT * FROM customer_properties WHERE id = ? AND customer_id = ?");
    $stmt->execute([$edit_id, $customer_id]);
    $property = $stmt->fetch();

    if (!$property) {
        set_flash('danger', 'Unauthorized or property not found.');
        header("Location: " . BASE_URL . "customer/properties.php");
        exit;
    }
}

// Handle Form Submission (Add or Update)
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_property') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Session security token expired. Please refresh the page.';
    }

    $title = clean_input($_POST['title'] ?? '');
    $property_type = clean_input($_POST['property_type'] ?? 'flat');
    $listing_type = clean_input($_POST['listing_type'] ?? 'rent');
    $price = floatval($_POST['price'] ?? 0);
    $security_deposit = floatval($_POST['security_deposit'] ?? 0);
    $advance_amount = floatval($_POST['advance_amount'] ?? 0);
    $address = clean_input($_POST['address'] ?? '');
    $city = clean_input($_POST['city'] ?? '');
    $state = clean_input($_POST['state'] ?? '');
    $postal_code = clean_input($_POST['postal_code'] ?? '');
    $bedrooms = intval($_POST['bedrooms'] ?? 1);
    $bathrooms = intval($_POST['bathrooms'] ?? 1);
    $area_sqft = intval($_POST['area_sqft'] ?? 0);
    $description = clean_input($_POST['description'] ?? '');
    $whatsapp_number = clean_input($_POST['whatsapp_number'] ?? '');
    $phone_number = clean_input($_POST['phone_number'] ?? '');
    $allow_direct_calls = isset($_POST['allow_direct_calls']) ? 1 : 0;
    $cancellation_policy = clean_input($_POST['cancellation_policy'] ?? '');
    $primary_image = clean_input($_POST['primary_image'] ?? '');
    $extra_images_raw = clean_input($_POST['extra_images'] ?? '');

    // Facilities array
    $selected_facilities = $_POST['facilities'] ?? [];
    if (!is_array($selected_facilities)) $selected_facilities = [];
    $facilities_json = json_encode(array_values(array_unique(array_filter($selected_facilities))));

    // Images handling
    $image_list = [];
    if (!empty($primary_image)) {
        $image_list[] = $primary_image;
    } else {
        $primary_image = 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80';
        $image_list[] = $primary_image;
    }
    if (!empty($extra_images_raw)) {
        $lines = explode("\n", $extra_images_raw);
        foreach ($lines as $line) {
            $url = trim($line);
            if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $image_list[] = $url;
            }
        }
    }
    $images_json = json_encode(array_values(array_unique($image_list)));

    // Validation
    if (empty($title)) $errors[] = 'Property title/name is required.';
    if ($price <= 0) $errors[] = 'Please specify a valid monthly rent or sale price.';
    if (empty($address) || empty($city)) $errors[] = 'Address and City are required.';
    if (empty($whatsapp_number)) $errors[] = 'Authorized WhatsApp number is required.';
    if (empty(sanitize_whatsapp_number($whatsapp_number))) {
        $errors[] = 'Please provide a valid 10-digit WhatsApp number (with optional country code).';
    }
    if (empty($description)) $errors[] = 'Property description is required.';

    if (empty($errors)) {
        if ($property) {
            // Update Existing Listing
            $update_stmt = $pdo->prepare("UPDATE customer_properties SET 
                title = ?, property_type = ?, listing_type = ?, price = ?, security_deposit = ?, advance_amount = ?,
                address = ?, city = ?, state = ?, postal_code = ?, bedrooms = ?, bathrooms = ?, area_sqft = ?,
                facilities = ?, images = ?, primary_image = ?, description = ?, whatsapp_number = ?, 
                allow_direct_calls = ?, phone_number = ?, cancellation_policy = ?
                WHERE id = ? AND customer_id = ?");

            $update_stmt->execute([
                $title, $property_type, $listing_type, $price, $security_deposit, $advance_amount,
                $address, $city, $state, $postal_code, $bedrooms, $bathrooms, $area_sqft,
                $facilities_json, $images_json, $primary_image, $description, $whatsapp_number,
                $allow_direct_calls, $phone_number, $cancellation_policy, $property['id'], $customer_id
            ]);

            set_flash('success', "Property '{$property['property_code']}' has been updated successfully.");
            header("Location: " . BASE_URL . "customer/properties.php");
            exit;

        } else {
            // Insert New Listing (Requirement 1 & 7: Unique Property ID, defaults to PENDING verification)
            $property_code = generate_property_code();
            $verification_status = 'PENDING'; // Kept pending until verification process is complete

            $insert_stmt = $pdo->prepare("INSERT INTO customer_properties 
                (property_code, customer_id, title, property_type, listing_type, price, security_deposit, advance_amount,
                 address, city, state, postal_code, bedrooms, bathrooms, area_sqft, facilities, images, primary_image,
                 description, whatsapp_number, allow_direct_calls, phone_number, cancellation_policy, verification_status, availability_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'AVAILABLE')");

            $insert_stmt->execute([
                $property_code, $customer_id, $title, $property_type, $listing_type, $price, $security_deposit, $advance_amount,
                $address, $city, $state, $postal_code, $bedrooms, $bathrooms, $area_sqft, $facilities_json, $images_json, $primary_image,
                $description, $whatsapp_number, $allow_direct_calls, $phone_number, $cancellation_policy, $verification_status
            ]);

            // Notify customer in-app
            create_notification(
                $user['id'],
                'property_submitted',
                'Property Listing Submitted (' . $property_code . ')',
                'Your property listing has been submitted and is currently pending verification. Our admin team will verify it shortly.',
                'customer/properties.php'
            );

            set_flash('success', "Property listing {$property_code} submitted successfully! It is currently pending verification before public display.");
            header("Location: " . BASE_URL . "customer/properties.php");
            exit;
        }
    }
}

// Pre-fill existing values
$val = function($key, $default = '') use ($property) {
    if (isset($_POST[$key])) return e($_POST[$key]);
    if ($property && isset($property[$key])) return e($property[$key]);
    return e($default);
};

$saved_facilities = [];
if ($property && !empty($property['facilities'])) {
    $saved_facilities = json_decode($property['facilities'], true) ?: [];
}

$page_title = ($property ? 'Edit Property ' . $property['property_code'] : 'List Your Property') . ' — ' . APP_NAME;
$active_nav = 'properties';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($page_title) ?></title>
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/dashboard.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/property.css">
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>

<div class="dashboard-wrapper">
  <!-- Customer Sidebar -->
  <?php require_once __DIR__ . '/../includes/customer-sidebar.php'; ?>

  <main class="dashboard-main">
    <header class="dashboard-topbar">
      <div class="topbar-title">
        <button type="button" class="sidebar-toggle-btn"><i data-lucide="menu"></i></button>
        <h2><?= $property ? 'Edit Property Listing (' . e($property['property_code']) . ')' : 'List a New Customer Property' ?></h2>
      </div>
      <div class="topbar-actions">
        <a href="<?= BASE_URL ?>customer/properties.php" class="btn btn-outline btn-sm">
          <i data-lucide="arrow-left"></i> Back to Hub
        </a>
      </div>
    </header>

    <div class="dashboard-content">

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom:1.5rem;">
          <ul style="margin:0; padding-left:1.25rem;">
            <?php foreach ($errors as $err): ?>
              <li><?= e($err) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <!-- Verification Notice Banner -->
      <div class="safety-alert-box" style="margin-top:0; margin-bottom:2rem;">
        <div class="safety-alert-icon">
          <i data-lucide="shield-check"></i>
        </div>
        <div style="font-size:0.88rem; color:var(--text-muted); line-height:1.5;">
          <strong style="color:var(--text-main);">Customer-to-Customer Listing Terms:</strong>
          Newly submitted listings receive a unique Property ID and remain pending until administrator review to maintain quality and prevent duplicate or misleading posts. You can update or remove your listing at any time.
        </div>
      </div>

      <div class="dashboard-card">
        <div class="card-header">
          <h3 class="card-title"><?= $property ? 'Update Property Details' : 'Property Listing Form' ?></h3>
          <p class="card-subtitle">Fill in complete details regarding your flat, apartment, or house.</p>
        </div>

        <div class="card-body">
          <form action="<?= BASE_URL ?>customer/property-form.php<?= $property ? '?id=' . $property['id'] : '' ?>" method="POST">
            <input type="hidden" name="action" value="save_property">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- Section 1: Basic Information -->
            <div style="font-weight:700; font-size:1.1rem; color:var(--primary); margin-bottom:1.25rem; display:flex; align-items:center; gap:0.5rem;">
              <i data-lucide="info" style="width:18px; height:18px;"></i> 1. Basic Information &amp; Type
            </div>

            <div class="form-group" style="margin-bottom:1.25rem;">
              <label class="form-label">Property Title / Name *</label>
              <input type="text" name="title" value="<?= $val('title') ?>" placeholder="e.g. Spacious 2BHK Flat with Lake View in Indiranagar" class="form-control" required>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
              <div class="form-group">
                <label class="form-label">Property Type *</label>
                <select name="property_type" class="form-control" required>
                  <option value="flat" <?= $val('property_type') === 'flat' ? 'selected' : '' ?>>Flat</option>
                  <option value="apartment" <?= $val('property_type') === 'apartment' ? 'selected' : '' ?>>Apartment</option>
                  <option value="studio" <?= $val('property_type') === 'studio' ? 'selected' : '' ?>>Studio Apartment</option>
                  <option value="independent_house" <?= $val('property_type') === 'independent_house' ? 'selected' : '' ?>>Independent House</option>
                  <option value="villa" <?= $val('property_type') === 'villa' ? 'selected' : '' ?>>Villa</option>
                  <option value="commercial" <?= $val('property_type') === 'commercial' ? 'selected' : '' ?>>Commercial / Office</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Listing Purpose *</label>
                <select name="listing_type" class="form-control" required>
                  <option value="rent" <?= $val('listing_type') === 'rent' ? 'selected' : '' ?>>For Rent</option>
                  <option value="sale" <?= $val('listing_type') === 'sale' ? 'selected' : '' ?>>For Sale</option>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Bedrooms (BHK) *</label>
                <select name="bedrooms" class="form-control" required>
                  <?php for ($i = 1; $i <= 6; $i++): ?>
                    <option value="<?= $i ?>" <?= (int)$val('bedrooms', 2) === $i ? 'selected' : '' ?>><?= $i ?> BHK</option>
                  <?php endfor; ?>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Bathrooms *</label>
                <select name="bathrooms" class="form-control" required>
                  <?php for ($i = 1; $i <= 6; $i++): ?>
                    <option value="<?= $i ?>" <?= (int)$val('bathrooms', 2) === $i ? 'selected' : '' ?>><?= $i ?> Bathrooms</option>
                  <?php endfor; ?>
                </select>
              </div>

              <div class="form-group">
                <label class="form-label">Super Built-up Area (Sq.Ft)</label>
                <input type="number" name="area_sqft" value="<?= $val('area_sqft') ?>" placeholder="e.g. 1200" class="form-control">
              </div>
            </div>

            <!-- Section 2: Pricing & Deposit -->
            <div style="font-weight:700; font-size:1.1rem; color:var(--primary); margin:2rem 0 1.25rem; display:flex; align-items:center; gap:0.5rem; border-top:1px solid var(--border-glass); padding-top:1.5rem;">
              <i data-lucide="dollar-sign" style="width:18px; height:18px;"></i> 2. Pricing, Deposits &amp; Financials
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
              <div class="form-group">
                <label class="form-label">Monthly Rent or Sale Price (₹) *</label>
                <input type="number" step="0.01" name="price" value="<?= $val('price') ?>" placeholder="e.g. 35000" class="form-control" required>
              </div>

              <div class="form-group">
                <label class="form-label">Security Deposit (₹)</label>
                <input type="number" step="0.01" name="security_deposit" value="<?= $val('security_deposit', '0') ?>" placeholder="e.g. 100000" class="form-control">
              </div>

              <div class="form-group">
                <label class="form-label">Advance Booking Token (₹)</label>
                <input type="number" step="0.01" name="advance_amount" value="<?= $val('advance_amount', '0') ?>" placeholder="e.g. 10000" class="form-control">
              </div>
            </div>

            <!-- Section 3: Location -->
            <div style="font-weight:700; font-size:1.1rem; color:var(--primary); margin:2rem 0 1.25rem; display:flex; align-items:center; gap:0.5rem; border-top:1px solid var(--border-glass); padding-top:1.5rem;">
              <i data-lucide="map-pin" style="width:18px; height:18px;"></i> 3. Property Location
            </div>

            <div class="form-group" style="margin-bottom:1.25rem;">
              <label class="form-label">Full Address / Society / Street *</label>
              <input type="text" name="address" value="<?= $val('address') ?>" placeholder="e.g. Flat 402, Skyline Residency, 100 Feet Rd" class="form-control" required>
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1.25rem; margin-bottom:1.5rem;">
              <div class="form-group">
                <label class="form-label">City *</label>
                <input type="text" name="city" value="<?= $val('city', $user['city'] ?? 'Bangalore') ?>" placeholder="e.g. Bangalore" class="form-control" required>
              </div>
              <div class="form-group">
                <label class="form-label">State *</label>
                <input type="text" name="state" value="<?= $val('state', 'Karnataka') ?>" placeholder="e.g. Karnataka" class="form-control" required>
              </div>
              <div class="form-group">
                <label class="form-label">Postal / Pincode</label>
                <input type="text" name="postal_code" value="<?= $val('postal_code') ?>" placeholder="e.g. 560038" class="form-control">
              </div>
            </div>

            <!-- Section 4: Facilities -->
            <div style="font-weight:700; font-size:1.1rem; color:var(--primary); margin:2rem 0 1.25rem; display:flex; align-items:center; gap:0.5rem; border-top:1px solid var(--border-glass); padding-top:1.5rem;">
              <i data-lucide="check-square" style="width:18px; height:18px;"></i> 4. Facilities &amp; Amenities
            </div>

            <?php
            $available_facilities = [
                'High-Speed Fiber WiFi',
                'Air Conditioning',
                'Covered Car Parking',
                '100% Power Backup',
                'Fully Furnished',
                'Semi Furnished',
                'Modular Kitchen',
                '24/7 Security CCTV',
                'Elevator Access',
                'Balcony Garden',
                'Clubhouse Gym',
                'Swimming Pool',
                'Water Purifier',
                'Geyser / Hot Water',
                'Pet Friendly'
            ];
            ?>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap:0.75rem; margin-bottom:1.5rem;">
              <?php foreach ($available_facilities as $fac): 
                $checked = in_array($fac, $saved_facilities);
              ?>
                <label style="display:flex; align-items:center; gap:0.6rem; padding:0.65rem 0.9rem; background:rgba(255,255,255,0.02); border:1px solid var(--border-glass); border-radius:var(--radius-sm); cursor:pointer; font-size:0.85rem;">
                  <input type="checkbox" name="facilities[]" value="<?= e($fac) ?>" <?= $checked ? 'checked' : '' ?>>
                  <span><?= e($fac) ?></span>
                </label>
              <?php endforeach; ?>
            </div>

            <!-- Section 5: Media & Description -->
            <div style="font-weight:700; font-size:1.1rem; color:var(--primary); margin:2rem 0 1.25rem; display:flex; align-items:center; gap:0.5rem; border-top:1px solid var(--border-glass); padding-top:1.5rem;">
              <i data-lucide="image" style="width:18px; height:18px;"></i> 5. Images &amp; Description
            </div>

            <div class="form-group" style="margin-bottom:1.25rem;">
              <label class="form-label">Primary Cover Image URL</label>
              <input type="url" name="primary_image" value="<?= $val('primary_image') ?>" placeholder="https://example.com/property-cover.jpg (Leave empty to use high-quality default)" class="form-control">
              <small style="color:var(--text-dim); font-size:0.78rem;">Provide a direct image URL (Unsplash, Imgur, or cloud storage).</small>
            </div>

            <div class="form-group" style="margin-bottom:1.25rem;">
              <label class="form-label">Additional Photos (One URL per line)</label>
              <textarea name="extra_images" rows="2" class="form-control" placeholder="https://example.com/photo1.jpg&#10;https://example.com/photo2.jpg"></textarea>
            </div>

            <div class="form-group" style="margin-bottom:1.5rem;">
              <label class="form-label">Detailed Property Description *</label>
              <textarea name="description" rows="4" class="form-control" placeholder="Describe the layout, natural lighting, ventilation, society amenities, nearby landmarks (metro, grocery, schools), and tenant preferences..." required><?= $val('description') ?></textarea>
            </div>

            <!-- Section 6: Contact & Direct Interaction Authorization -->
            <div style="font-weight:700; font-size:1.1rem; color:var(--primary); margin:2rem 0 1.25rem; display:flex; align-items:center; gap:0.5rem; border-top:1px solid var(--border-glass); padding-top:1.5rem;">
              <i data-lucide="phone" style="width:18px; height:18px;"></i> 6. Authorized WhatsApp &amp; Direct Call Settings
            </div>

            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap:1.25rem; margin-bottom:1.25rem;">
              <div class="form-group">
                <label class="form-label">Authorized WhatsApp Number *</label>
                <input type="text" name="whatsapp_number" value="<?= $val('whatsapp_number', $user['phone'] ?? '') ?>" placeholder="e.g. +91 98860 11223 or 9886011223" class="form-control" required>
                <small style="color:var(--text-dim); font-size:0.78rem;">Customers will receive a direct WhatsApp link to chat with you.</small>
              </div>

              <div class="form-group">
                <label class="form-label">Calling Phone Number</label>
                <input type="text" name="phone_number" value="<?= $val('phone_number', $user['phone'] ?? '') ?>" placeholder="e.g. +91 98860 11223" class="form-control">
              </div>
            </div>

            <!-- Privacy Consent Toggle (Requirement 4 & 7) -->
            <div class="form-group" style="margin-bottom:1.5rem; background:rgba(255,255,255,0.02); padding:1rem; border-radius:var(--radius-md); border:1px solid var(--border-glass);">
              <label style="display:flex; align-items:center; gap:0.75rem; cursor:pointer;">
                <input type="checkbox" name="allow_direct_calls" value="1" <?= $val('allow_direct_calls', 1) ? 'checked' : '' ?> style="width:18px; height:18px;">
                <div>
                  <strong style="color:var(--text-main); font-size:0.92rem;">Direct Phone Call Consent:</strong>
                  <div style="color:var(--text-muted); font-size:0.8rem; margin-top:2px;">
                    Allow registered customers to view your phone number and trigger the "Call Owner" button. If unchecked, your phone number remains strictly private and users can only contact you via WhatsApp or platform booking requests.
                  </div>
                </div>
              </label>
            </div>

            <div class="form-group" style="margin-bottom:1.5rem;">
              <label class="form-label">Cancellation &amp; Move-in Policy</label>
              <textarea name="cancellation_policy" rows="2" class="form-control" placeholder="e.g. 100% advance deposit refund if visit or booking is cancelled before final agreement execution. 1 month notice period for move-out."><?= $val('cancellation_policy') ?></textarea>
            </div>

            <!-- Owner Verification Declaration Checkbox (Requirement 7) -->
            <div class="form-group" style="margin-bottom:2rem; background:rgba(0,240,255,0.04); border:1px solid rgba(0,240,255,0.25); border-radius:var(--radius-md); padding:1rem;">
              <label style="display:flex; align-items:flex-start; gap:0.75rem; cursor:pointer;">
                <input type="checkbox" name="declaration" required checked style="margin-top:3px; width:18px; height:18px;">
                <div style="font-size:0.85rem; color:var(--text-main); line-height:1.5;">
                  <strong>Customer Declaration:</strong> I confirm that I am the authorized owner or authorized representative of this property. All information, photographs, and pricing provided are accurate and genuine.
                </div>
              </label>
            </div>

            <!-- Submit Button -->
            <div style="display:flex; justify-content:flex-end; gap:1rem;">
              <a href="<?= BASE_URL ?>customer/properties.php" class="btn btn-outline btn-lg">Cancel</a>
              <button type="submit" class="btn btn-primary btn-lg">
                <i data-lucide="check-circle"></i> <?= $property ? 'Update Property Listing' : 'Submit Property for Verification' ?>
              </button>
            </div>

          </form>
        </div>
      </div>

    </div>
  </main>
</div>

</body>
</html>
