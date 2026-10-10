<?php
// ============================================================================
// VoltCraft 3D — Customer-to-Customer Property Details Dossier
// ============================================================================

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/property-functions.php';

$pdo = get_db_connection();
$property_id = intval($_GET['id'] ?? 0);

if (!$property_id) {
    header("Location: " . BASE_URL . "properties.php");
    exit;
}

// Increment views count
$pdo->prepare("UPDATE customer_properties SET views_count = views_count + 1 WHERE id = ?")->execute([$property_id]);

// Fetch Property & Owner Information
$property = get_property_by_id($property_id);

if (!$property) {
    $page_title = 'Property Not Found — ' . APP_NAME;
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="container section" style="text-align:center; padding:5rem 1rem;">
            <h2>Property Not Found</h2>
            <p style="color:var(--text-muted); margin:1rem 0 2rem;">The requested property listing does not exist or has been removed.</p>
            <a href="' . BASE_URL . 'properties.php" class="btn btn-primary">Browse All Properties</a>
          </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$page_title = e($property['title']) . ' (' . e($property['property_code']) . ') — ' . APP_NAME;
$page_desc = 'Direct customer listing in ' . e($property['city']) . '. Chat on WhatsApp, call owner directly, or schedule a property visit with zero brokerage.';

require_once __DIR__ . '/includes/header.php';

$logged_user = current_user();
$current_customer_id = ($logged_user && $logged_user['role'] === 'customer') ? get_customer_id($logged_user['id']) : null;
$is_owner = ($current_customer_id && (int)$current_customer_id === (int)$property['customer_id']);
$is_saved = $current_customer_id ? is_property_saved($current_customer_id, $property['id']) : false;

// Decode images & facilities
$images = json_decode($property['images'] ?? '[]', true) ?: [];
if (empty($images) && !empty($property['primary_image'])) {
    $images = [$property['primary_image']];
}
if (empty($images)) {
    $images = ['https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1200&q=80'];
}
$facilities = json_decode($property['facilities'] ?? '[]', true) ?: [];

// Formatted WhatsApp interaction URL
$wa_url = get_property_whatsapp_url(
    $property['whatsapp_number'],
    $property['property_code'],
    $property['title'],
    $property['city'] . ', ' . $property['state']
);

// Call link considering privacy consent
$call_link = get_property_call_link($property['phone_number'] ?: $property['owner_user_phone'], $property['allow_direct_calls']);
?>

<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/property.css">

<!-- Breadcrumbs & Actions Header -->
<div style="background:var(--bg-surface); border-bottom:1px solid var(--border-glass); padding:1rem 0;">
  <div class="container" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
    <div style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem; color:var(--text-dim);">
      <a href="<?= BASE_URL ?>index.php" style="color:var(--text-dim); text-decoration:none;">Home</a>
      <span>/</span>
      <a href="<?= BASE_URL ?>properties.php" style="color:var(--text-dim); text-decoration:none;">Properties</a>
      <span>/</span>
      <span style="color:var(--primary); font-family:var(--font-mono); font-weight:700;"><?= e($property['property_code']) ?></span>
    </div>

    <div style="display:flex; align-items:center; gap:0.75rem;">
      <button type="button" class="btn btn-outline btn-sm <?= $is_saved ? 'active' : '' ?>" onclick="toggleSaveProperty(<?= $property['id'] ?>, this)">
        <i data-lucide="heart" style="width:14px; height:14px; <?= $is_saved ? 'fill:currentColor;' : '' ?>"></i>
        <span><?= $is_saved ? 'Saved' : 'Save Property' ?></span>
      </button>

      <button type="button" class="btn btn-outline btn-sm" onclick="openReportModal()" style="color:var(--text-dim);">
        <i data-lucide="flag" style="width:14px; height:14px;"></i> Report Listing
      </button>

      <?php if ($is_owner): ?>
        <a href="<?= BASE_URL ?>customer/property-form.php?id=<?= $property['id'] ?>" class="btn btn-primary btn-sm">
          <i data-lucide="edit-3" style="width:14px; height:14px;"></i> Edit My Listing
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Main Details Dossier -->
<section class="section" style="padding-top:2.5rem;">
  <div class="container">

    <!-- Top Headline Section -->
    <div class="property-detail-header">
      <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap; margin-bottom:0.75rem;">
        <span class="prop-id-badge">
          <i data-lucide="tag" style="width:12px; height:12px;"></i> <?= e($property['property_code']) ?>
        </span>
        <span class="prop-purpose-badge prop-purpose-<?= e($property['listing_type']) ?>">
          For <?= ucfirst(e($property['listing_type'])) ?>
        </span>
        <?= render_property_verification_badge($property['verification_status']) ?>
        <?= render_property_availability_badge($property['availability_status']) ?>
        <span style="font-size:0.8rem; color:var(--text-dim); margin-left:auto;">
          <i data-lucide="eye" style="width:14px; height:14px; vertical-align:middle;"></i> <?= number_format($property['views_count']) ?> views
        </span>
      </div>

      <h1 style="font-size:clamp(1.75rem, 3.2vw, 2.4rem); font-weight:800; line-height:1.3; margin-bottom:0.6rem;">
        <?= e($property['title']) ?>
      </h1>

      <div style="display:flex; align-items:center; gap:0.5rem; color:var(--text-muted); font-size:1rem;">
        <i data-lucide="map-pin" style="color:var(--primary); width:18px; height:18px;"></i>
        <span><?= e($property['address']) ?>, <?= e($property['city']) ?>, <?= e($property['state']) ?> - <?= e($property['postal_code']) ?></span>
      </div>
    </div>

    <!-- Gallery Grid -->
    <div class="property-detail-gallery">
      <div class="property-gallery-main">
        <img id="mainGalleryImg" src="<?= e($images[0]) ?>" alt="Main Photo">
      </div>
      <div class="property-gallery-thumbs">
        <?php foreach (array_slice($images, 1, 2) as $img): ?>
          <div class="property-gallery-thumb" onclick="swapGalleryImage('<?= e($img) ?>')">
            <img src="<?= e($img) ?>" alt="Thumb">
          </div>
        <?php endforeach; ?>
        <?php if (count($images) === 1): ?>
          <div class="property-gallery-thumb" style="display:flex; align-items:center; justify-content:center; background:rgba(255,255,255,0.02); color:var(--text-dim);">
            <div style="text-align:center;">
              <i data-lucide="camera" style="width:28px; height:28px; margin-bottom:0.4rem;"></i>
              <div style="font-size:0.8rem;">Direct Owner Photos</div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Main Content & Sticky Booking Sidebar -->
    <div class="property-detail-layout">
      
      <!-- Left Column: Specs, Description, Facilities, Terms -->
      <div>
        
        <!-- Key Specifications Grid -->
        <div class="property-specs-grid">
          <div class="spec-box">
            <span class="spec-box-lbl">Bedrooms</span>
            <span class="spec-box-val"><i data-lucide="bed"></i> <?= $property['bedrooms'] ?> BHK</span>
          </div>
          <div class="spec-box">
            <span class="spec-box-lbl">Bathrooms</span>
            <span class="spec-box-val"><i data-lucide="bath"></i> <?= $property['bathrooms'] ?> Baths</span>
          </div>
          <?php if (!empty($property['area_sqft'])): ?>
            <div class="spec-box">
              <span class="spec-box-lbl">Super Built-up</span>
              <span class="spec-box-val"><i data-lucide="maximize"></i> <?= number_format($property['area_sqft']) ?> sq.ft</span>
            </div>
          <?php endif; ?>
          <div class="spec-box">
            <span class="spec-box-lbl">Property Type</span>
            <span class="spec-box-val"><i data-lucide="building"></i> <?= ucfirst(e(str_replace('_', ' ', $property['property_type']))) ?></span>
          </div>
          <div class="spec-box">
            <span class="spec-box-lbl">Listed Date</span>
            <span class="spec-box-val"><i data-lucide="calendar"></i> <?= date('d M Y', strtotime($property['created_at'])) ?></span>
          </div>
        </div>

        <!-- Description Section -->
        <div class="glass-card" style="padding:1.75rem; margin-bottom:2rem;">
          <h3 style="font-size:1.25rem; font-weight:700; margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
            <i data-lucide="align-left" style="color:var(--primary); width:20px; height:20px;"></i> Property Description
          </h3>
          <div style="font-size:0.95rem; line-height:1.75; color:var(--text-muted); white-space:pre-line;">
            <?= e($property['description']) ?>
          </div>
        </div>

        <!-- Facilities & Amenities Section -->
        <?php if (!empty($facilities)): ?>
          <div class="glass-card" style="padding:1.75rem; margin-bottom:2rem;">
            <h3 style="font-size:1.25rem; font-weight:700; margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
              <i data-lucide="check-circle-2" style="color:var(--primary); width:20px; height:20px;"></i> Facilities &amp; Amenities
            </h3>
            <div class="property-facilities-full-grid">
              <?php foreach ($facilities as $fac): ?>
                <div class="facility-full-item">
                  <i data-lucide="check" style="color:var(--success);"></i>
                  <span><?= e($fac) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Advance Deposit & Cancellation Policy -->
        <div class="glass-card" style="padding:1.75rem; margin-bottom:2rem;">
          <h3 style="font-size:1.25rem; font-weight:700; margin-bottom:1rem; display:flex; align-items:center; gap:0.5rem;">
            <i data-lucide="shield" style="color:var(--primary); width:20px; height:20px;"></i> Booking &amp; Cancellation Policy
          </h3>
          <div style="font-size:0.92rem; line-height:1.65; color:var(--text-muted); margin-bottom:1rem;">
            <?= !empty($property['cancellation_policy']) ? e($property['cancellation_policy']) : 'Standard terms: 100% advance deposit refund if visit or booking is cancelled before the final agreement execution. Security deposit is refundable at the end of the tenancy agreement subject to inspection.' ?>
          </div>
          <div style="background:rgba(255,255,255,0.02); border-left:3px solid var(--primary); padding:0.85rem 1.25rem; border-radius:0 var(--radius-sm) var(--radius-sm) 0; font-size:0.85rem; color:var(--text-main);">
            <strong>Notice:</strong> All payments, lease agreements, and deposit settlements should be conducted through documented bank transfers after physical property verification.
          </div>
        </div>

        <!-- External Interaction Safety Disclosure -->
        <div class="safety-alert-box">
          <div class="safety-alert-icon">
            <i data-lucide="info"></i>
          </div>
          <div style="font-size:0.86rem; line-height:1.55; color:var(--text-muted);">
            <strong style="color:var(--text-main);">Customer Communication Protocol:</strong> Direct conversations and phone calls happen outside the website through authorized WhatsApp or phone. Booking requests, scheduled visits, and reservation records remain permanently available in your dashboard.
          </div>
        </div>

      </div>

      <!-- Right Column: Sticky Booking & Direct Interaction Card -->
      <div>
        <div class="property-sidebar-card">
          
          <!-- Price Display -->
          <div style="margin-bottom:1.5rem; padding-bottom:1.25rem; border-bottom:1px solid var(--border-glass);">
            <div style="font-size:0.8rem; color:var(--text-dim); text-transform:uppercase; font-weight:700; margin-bottom:0.25rem;">
              <?= $property['listing_type'] === 'rent' ? 'Monthly Rental' : 'Sale Price' ?>
            </div>
            <div style="display:flex; align-items:baseline; gap:0.4rem;">
              <span style="font-size:2rem; font-weight:800; color:var(--primary); font-family:var(--font-sans);">
                <?= format_currency($property['price']) ?>
              </span>
              <span style="color:var(--text-dim); font-size:0.9rem;">
                <?= $property['listing_type'] === 'rent' ? '/ month' : 'total' ?>
              </span>
            </div>
            
            <div style="display:flex; justify-content:space-between; margin-top:0.75rem; font-size:0.85rem; color:var(--text-muted);">
              <span>Security Deposit:</span>
              <strong style="color:var(--text-main);"><?= format_currency($property['security_deposit']) ?></strong>
            </div>

            <?php if ($property['advance_amount'] > 0): ?>
              <div style="display:flex; justify-content:space-between; margin-top:0.35rem; font-size:0.85rem; color:var(--text-muted);">
                <span>Advance Token Fee:</span>
                <strong style="color:var(--primary);"><?= format_currency($property['advance_amount']) ?></strong>
              </div>
            <?php endif; ?>
          </div>

          <!-- Owner Profile Summary -->
          <div style="display:flex; align-items:center; gap:0.9rem; padding:1rem; background:rgba(255,255,255,0.03); border:1px solid var(--border-glass); border-radius:var(--radius-md); margin-bottom:1.5rem;">
            <img src="<?= get_avatar_url($property['owner_avatar'], $property['owner_name']) ?>" alt="<?= e($property['owner_name']) ?>" style="width:48px; height:48px; border-radius:50%; border:2px solid var(--primary);">
            <div>
              <div style="font-size:0.75rem; color:var(--text-dim); text-transform:uppercase;">Listed by Customer</div>
              <div style="font-weight:700; font-size:0.98rem; color:var(--text-main);"><?= e($property['owner_name']) ?></div>
              <div style="font-size:0.75rem; color:var(--success); display:flex; align-items:center; gap:3px;">
                <i data-lucide="shield-check" style="width:12px; height:12px;"></i> Verified Account &bull; 0% Brokerage
              </div>
            </div>
          </div>

          <!-- Direct Interaction Action Buttons -->
          <div style="display:flex; flex-direction:column; gap:0.85rem; margin-bottom:1.5rem;">
            
            <!-- WhatsApp Button (Requirement 3) -->
            <?php if ($wa_url): ?>
              <a href="<?= e($wa_url) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-lg" style="width:100%;" onclick="logInquiry(<?= $property['id'] ?>, 'WHATSAPP')">
                <i data-lucide="message-circle" style="width:20px; height:20px;"></i> Chat on WhatsApp
              </a>
            <?php else: ?>
              <button class="btn btn-outline btn-lg" style="width:100%;" disabled>
                <i data-lucide="message-circle"></i> WhatsApp Not Available
              </button>
            <?php endif; ?>

            <!-- Direct Call Owner Button (Requirement 4: Respects Privacy Consent) -->
            <?php if ($call_link): ?>
              <a href="<?= e($call_link) ?>" class="btn btn-call btn-lg" style="width:100%;" onclick="logInquiry(<?= $property['id'] ?>, 'CALL')">
                <i data-lucide="phone-call" style="width:18px; height:18px;"></i> Call Owner Directly
              </a>
            <?php else: ?>
              <div style="text-align:center; padding:0.6rem; background:rgba(255,255,255,0.02); border:1px solid var(--border-glass); border-radius:var(--radius-sm); font-size:0.78rem; color:var(--text-dim);">
                <i data-lucide="lock" style="width:12px; height:12px; vertical-align:middle;"></i> Phone masked by owner privacy setting. Please chat on WhatsApp or book a visit below.
              </div>
            <?php endif; ?>

            <!-- Customer-to-Customer Booking / Schedule Visit Button (Requirement 5) -->
            <?php if ($is_owner): ?>
              <div style="padding:0.75rem; text-align:center; background:rgba(0,240,255,0.08); border-radius:var(--radius-sm); font-size:0.85rem; color:var(--primary);">
                This is your property listing. You can manage incoming requests from your dashboard.
              </div>
            <?php else: ?>
              <button type="button" class="btn btn-primary btn-lg" style="width:100%;" onclick="openBookingModal()">
                <i data-lucide="calendar"></i> Book Now / Schedule Visit
              </button>
            <?php endif; ?>

          </div>

          <!-- Feature Highlights Box -->
          <div style="border-top:1px solid var(--border-glass); padding-top:1.25rem;">
            <div style="font-size:0.75rem; text-transform:uppercase; color:var(--text-dim); font-weight:700; margin-bottom:0.75rem;">Platform Highlights</div>
            <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:0.6rem; font-size:0.84rem; color:var(--text-muted);">
              <li style="display:flex; align-items:center; gap:0.5rem;">
                <i data-lucide="check" style="color:var(--primary); width:15px; height:15px;"></i> Direct interaction with property owner
              </li>
              <li style="display:flex; align-items:center; gap:0.5rem;">
                <i data-lucide="check" style="color:var(--primary); width:15px; height:15px;"></i> Scheduled offline property inspection
              </li>
              <li style="display:flex; align-items:center; gap:0.5rem;">
                <i data-lucide="check" style="color:var(--primary); width:15px; height:15px;"></i> Persistent status progression &amp; tracking
              </li>
              <li style="display:flex; align-items:center; gap:0.5rem;">
                <i data-lucide="check" style="color:var(--primary); width:15px; height:15px;"></i> Zero broker commissions
              </li>
            </ul>
          </div>

        </div>
      </div>

    </div>

  </div>
</section>

<!-- =========================================================================
     MODAL 1: C2C Booking Request / Schedule Visit (Requirement 5)
     ========================================================================= -->
<div class="modal-backdrop" id="bookingModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <div>
        <h3 style="font-size:1.15rem; font-weight:700; margin-bottom:0.2rem;">Book Property / Schedule Visit</h3>
        <p style="font-size:0.78rem; color:var(--text-dim); margin:0;">Property ID: <?= e($property['property_code']) ?> &bull; <?= e($property['title']) ?></p>
      </div>
      <button type="button" class="modal-close-btn" onclick="closeBookingModal()">
        <i data-lucide="x"></i>
      </button>
    </div>

    <div class="modal-body">
      <?php if (!$logged_user): ?>
        <div style="text-align:center; padding:1.5rem 0;">
          <i data-lucide="log-in" style="width:40px; height:40px; color:var(--primary); margin-bottom:1rem;"></i>
          <h4>Sign In Required</h4>
          <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:1.5rem;">Please log in as a registered customer to submit a booking or visit request.</p>
          <a href="<?= BASE_URL ?>login.php?redirect=property-detail.php?id=<?= $property['id'] ?>" class="btn btn-primary">Sign In to Continue</a>
        </div>
      <?php elseif ($logged_user['role'] !== 'customer'): ?>
        <div class="alert alert-warning">
          Only registered customer accounts can submit property booking requests.
        </div>
      <?php else: ?>
        <form action="<?= BASE_URL ?>customer/property-booking-action.php" method="POST">
          <input type="hidden" name="action" value="submit_booking_request">
          <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <!-- Request Type -->
          <div class="form-group" style="margin-bottom:1.2rem;">
            <label class="form-label">Request Type</label>
            <select name="request_type" class="form-control" required>
              <option value="visit">Schedule In-Person Property Visit</option>
              <option value="rent_booking">Direct Rental Booking Request</option>
              <?php if ($property['listing_type'] === 'sale'): ?>
                <option value="purchase_offer">Formal Purchase Offer</option>
              <?php endif; ?>
            </select>
          </div>

          <!-- Date & Time Slot Grid -->
          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1rem; margin-bottom:1.2rem;">
            <div class="form-group">
              <label class="form-label">Preferred Date</label>
              <input type="date" name="scheduled_visit_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Preferred Time Slot</label>
              <select name="scheduled_visit_time" class="form-control" required>
                <option value="Morning (10:00 AM - 12:00 PM)">Morning (10:00 AM - 12:00 PM)</option>
                <option value="Afternoon (02:00 PM - 04:00 PM)">Afternoon (02:00 PM - 04:00 PM)</option>
                <option value="Evening (05:00 PM - 07:00 PM)">Evening (05:00 PM - 07:00 PM)</option>
                <option value="Flexible / As Per Owner">Flexible / Any Time</option>
              </select>
            </div>
          </div>

          <!-- Message & Inquiries -->
          <div class="form-group" style="margin-bottom:1.2rem;">
            <label class="form-label">Message / Questions for the Owner</label>
            <textarea name="message" rows="3" class="form-control" placeholder="Introduce yourself (e.g. profession, family size, move-in date) and ask any questions..." required>Hello, I am interested in your property (<?= e($property['property_code']) ?>). I would like to schedule a visit and discuss terms.</textarea>
          </div>

          <!-- Financial and Safety Disclosure -->
          <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-glass); border-radius:var(--radius-md); padding:1rem; margin-bottom:1.25rem; font-size:0.84rem;">
            <div style="font-weight:700; color:var(--text-main); margin-bottom:0.5rem; display:flex; align-items:center; gap:0.4rem;">
              <i data-lucide="shield-alert" style="color:var(--warning); width:15px; height:15px;"></i> Booking &amp; Payment Conditions:
            </div>
            <ul style="padding-left:1.2rem; margin:0; color:var(--text-muted); line-height:1.5;">
              <li>Submitting this request does not automatically charge or confirm a booking.</li>
              <li>The owner will review your submission and can Accept, Schedule a Visit, or Respond.</li>
              <li>Expected Monthly Rent / Price: <strong><?= format_currency($property['price']) ?></strong></li>
              <li>Security Deposit: <strong><?= format_currency($property['security_deposit']) ?></strong></li>
              <li>Refund Terms: Full advance refund if cancelled before agreement signing.</li>
            </ul>
          </div>

          <button type="submit" class="btn btn-primary btn-lg" style="width:100%;">
            <i data-lucide="send"></i> Submit Request to Owner
          </button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- =========================================================================
     MODAL 2: Report Listing & Safety Mechanism (Requirement 7)
     ========================================================================= -->
<div class="modal-backdrop" id="reportModal">
  <div class="modal-dialog">
    <div class="modal-header">
      <h3 style="font-size:1.15rem; font-weight:700; color:var(--danger); display:flex; align-items:center; gap:0.4rem;">
        <i data-lucide="flag"></i> Report Suspicious Property
      </h3>
      <button type="button" class="modal-close-btn" onclick="closeReportModal()">
        <i data-lucide="x"></i>
      </button>
    </div>

    <div class="modal-body">
      <p style="font-size:0.86rem; color:var(--text-muted); margin-bottom:1.2rem;">
        Help keep our community safe. Our administration reviews every flagged listing promptly.
      </p>

      <form action="<?= BASE_URL ?>customer/property-booking-action.php" method="POST">
        <input type="hidden" name="action" value="report_property">
        <input type="hidden" name="property_id" value="<?= $property['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <div class="form-group" style="margin-bottom:1.2rem;">
          <label class="form-label">Reason for Report</label>
          <select name="reason" class="form-control" required>
            <option value="Misleading price or fake listing">Misleading price or fake listing</option>
            <option value="Property already rented or sold">Property already rented or sold</option>
            <option value="Incorrect address or stolen photos">Incorrect address or stolen photos</option>
            <option value="Suspicious behavior or spam contact">Suspicious behavior or spam contact</option>
            <option value="Unreachable owner / Invalid phone">Unreachable owner / Invalid phone</option>
            <option value="Other platform violation">Other platform violation</option>
          </select>
        </div>

        <div class="form-group" style="margin-bottom:1.5rem;">
          <label class="form-label">Details / Observations</label>
          <textarea name="details" rows="3" class="form-control" placeholder="Provide any specific evidence or notes for our moderators..." required></textarea>
        </div>

        <button type="submit" class="btn btn-primary" style="width:100%; background:var(--danger); border-color:var(--danger);">
          <i data-lucide="shield-alert"></i> Submit Report to Admin
        </button>
      </form>
    </div>
  </div>
</div>

<script>
// Swap Gallery Image
function swapGalleryImage(src) {
  document.getElementById('mainGalleryImg').src = src;
}

// Modal Handlers
function openBookingModal() {
  document.getElementById('bookingModal').classList.add('active');
}
function closeBookingModal() {
  document.getElementById('bookingModal').classList.remove('active');
}

function openReportModal() {
  document.getElementById('reportModal').classList.add('active');
}
function closeReportModal() {
  document.getElementById('reportModal').classList.remove('active');
}

// Close on outside click
window.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-backdrop')) {
    e.target.classList.remove('active');
  }
});

// Toggle Save Property
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
        btnElement.querySelector('span').textContent = 'Saved';
        btnElement.querySelector('i').style.fill = 'currentColor';
      } else {
        btnElement.classList.remove('active');
        btnElement.querySelector('span').textContent = 'Save Property';
        btnElement.querySelector('i').style.fill = 'none';
      }
    } else if (data.status === 'unauthorized') {
      alert('Please sign in to save properties to your favorites.');
      window.location.href = '<?= BASE_URL ?>login.php?redirect=property-detail.php?id=' + propId;
    }
  });
}

function logInquiry(propId, channel) {
  fetch('<?= BASE_URL ?>api/property.php?action=log_inquiry&property_id=' + propId + '&channel=' + channel, {
    method: 'POST'
  }).catch(() => {});
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
