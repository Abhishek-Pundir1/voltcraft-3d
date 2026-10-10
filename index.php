<?php
$page_title = 'VoltCraft 3D — Next-Gen Electronics Engineering & Service Booking Platform';
$page_desc = 'Interactive 3D booking platform connecting clients with IPC-certified electronics, robotics, PCB, and automation engineers with 20% advance escrow protection.';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();

// Featured Services
$services = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY id ASC LIMIT 6")->fetchAll();

// Featured Verified Engineers
$engineers = $pdo->query("SELECT e.*, u.full_name, u.avatar,
                          GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS services_offered
                          FROM engineers e
                          JOIN users u ON e.user_id = u.id
                          LEFT JOIN engineer_services es ON e.id = es.engineer_id
                          LEFT JOIN services s ON es.service_id = s.id
                          WHERE e.is_verified = 1 AND u.status = 'active'
                          GROUP BY e.id ORDER BY e.rating DESC LIMIT 3")->fetchAll();

// Recent 5-Star Reviews
$reviews = $pdo->query("SELECT r.*, u.full_name, u.avatar, eng_u.full_name AS engineer_name, s.name AS service_name
                        FROM reviews r
                        JOIN customers c ON r.customer_id = c.id
                        JOIN users u ON c.user_id = u.id
                        JOIN engineers e ON r.engineer_id = e.id
                        JOIN users eng_u ON e.user_id = eng_u.id
                        JOIN bookings b ON r.booking_id = b.id
                        JOIN services s ON b.service_id = s.id
                        ORDER BY r.rating DESC, r.created_at DESC LIMIT 3")->fetchAll();

// Customer-to-Customer Properties
require_once __DIR__ . '/includes/property-functions.php';
$featured_properties = $pdo->query("SELECT p.*, u.full_name as owner_name, u.avatar as owner_avatar 
                                     FROM customer_properties p 
                                     JOIN customers c ON p.customer_id = c.id 
                                     JOIN users u ON c.user_id = u.id 
                                     WHERE p.verification_status = 'APPROVED' 
                                     ORDER BY p.views_count DESC LIMIT 3")->fetchAll();
?>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/property.css">

<!-- 1. Interactive 3D Hero Section -->
<section class="hero-3d-section">
  <!-- Three.js Canvas Container -->
  <div class="hero-canvas-container" id="hero-3d-canvas-container"></div>

  <!-- Floating HUD Badges -->
  <div class="hero-hud-card hud-top">
    <div class="hud-icon-box">
      <i data-lucide="shield-check"></i>
    </div>
    <div class="hud-info">
      <h5>20% Advance Escrow</h5>
      <p>Funds locked until verified diagnosis</p>
    </div>
  </div>

  <div class="hero-hud-card hud-bottom">
    <div class="hud-icon-box" style="background:rgba(16,185,129,0.12);color:var(--success);">
      <i data-lucide="cpu"></i>
    </div>
    <div class="hud-info">
      <h5>IPC-A-610 Master Certified</h5>
      <p>Hardware diagnostics &amp; micro-soldering</p>
    </div>
  </div>

  <div class="hero-3d-hint">
    <i data-lucide="move" style="width:14px;height:14px;"></i> Drag to rotate 3D Electronic Core &bull; Scroll to explore
  </div>

  <!-- Main Hero Content -->
  <div class="container hero-content-wrapper">
    <div class="hero-text-content">
      <div class="hero-badge-live">
        <span class="hero-live-indicator"></span>
        <span>VERIFIED HARDWARE PROFESSIONALS ON DEMAND</span>
      </div>

      <h1 class="hero-title">
        Next-Gen Electronics <br>
        <span class="gradient-text">Engineering &amp; Repair</span>
      </h1>

      <p class="hero-lead">
        From multi-layer PCB micro-soldering and industrial PLC debugging to solar inverter diagnostics and drone robotics. Connect with certified engineers backed by our strict <strong>20% Advance Escrow Protocol</strong>.
      </p>

      <div class="hero-cta-group">
        <a href="<?= BASE_URL ?>engineers.php" class="btn btn-primary btn-lg">
          <i data-lucide="search"></i> Find Engineers
        </a>
        <a href="<?= BASE_URL ?>how-it-works.php" class="btn btn-outline btn-lg">
          <i data-lucide="play-circle"></i> How It Works
        </a>
      </div>

      <!-- Stats Strip -->
      <div class="hero-stats-strip">
        <div class="hero-stat-item">
          <span class="hero-stat-val" data-counter="4800" data-suffix="+">4,800+</span>
          <span class="hero-stat-lbl">Circuits Repaired</span>
        </div>
        <div class="hero-stat-item">
          <span class="hero-stat-val" data-counter="99" data-suffix=".6%">99.6%</span>
          <span class="hero-stat-lbl">Satisfaction Rate</span>
        </div>
        <div class="hero-stat-item">
          <span class="hero-stat-val" data-counter="100" data-suffix="%">100%</span>
          <span class="hero-stat-lbl">Verified Credentials</span>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- 2. Services Catalog Overview -->
<section class="section" style="background:linear-gradient(180deg, transparent 0%, rgba(12,18,34,0.4) 100%);">
  <div class="container">
    <div class="section-title">
      <span class="badge badge-primary">ENGINEERING DOMAINS</span>
      <h2>Specialized Electronics Solutions</h2>
      <p>Precision diagnostics and component-level repairs executed by verified subject-matter experts.</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:2rem;">
      <?php foreach ($services as $srv): ?>
        <div class="glass-card glass-card-tilt">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:1.25rem;">
            <div style="width:52px;height:52px;border-radius:var(--radius-sm);background:rgba(0,240,255,0.1);border:1px solid rgba(0,240,255,0.25);display:flex;align-items:center;justify-content:center;color:var(--primary);">
              <i data-lucide="<?= e($srv['icon']) ?>" style="width:26px;height:26px;"></i>
            </div>
            <span class="badge badge-secondary"><?= e($srv['category']) ?></span>
          </div>

          <h3 style="font-size:1.25rem;margin-bottom:0.75rem;"><?= e($srv['name']) ?></h3>
          <p style="font-size:0.92rem;margin-bottom:1.5rem;line-height:1.6;"><?= e($srv['description']) ?></p>

          <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border-glass);padding-top:1.25rem;">
            <div>
              <div style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;font-weight:600;">Base Estimate</div>
              <div style="font-size:1.35rem;font-weight:800;color:var(--text-main);font-family:var(--font-mono);">
                <?= format_currency($srv['base_price']) ?>
              </div>
            </div>
            <a href="<?= BASE_URL ?>customer/new-request.php?service_id=<?= $srv['id'] ?>" class="btn btn-outline btn-sm">
              Book Service <i data-lucide="arrow-right" style="width:14px;height:14px;"></i>
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="text-align:center;margin-top:3rem;">
      <a href="<?= BASE_URL ?>services.php" class="btn btn-outline">
        View All 8 Engineering Services <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
      </a>
    </div>
  </div>
</section>

<!-- 3. Featured Verified Engineers -->
<section class="section">
  <div class="container">
    <div class="section-title">
      <span class="badge badge-success">ELITE TALENT</span>
      <h2>Featured Electronics Engineers</h2>
      <p>Browse top-rated specialists equipped with laboratory-grade diagnostic tooling.</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(330px, 1fr));gap:2rem;">
      <?php foreach ($engineers as $eng): ?>
        <div class="glass-card glass-card-tilt" style="display:flex;flex-direction:column;justify-content:space-between;">
          <div>
            <div style="display:flex;align-items:center;gap:1.2rem;margin-bottom:1.25rem;">
              <img src="<?= get_avatar_url($eng['avatar'], $eng['full_name']) ?>" alt="<?= e($eng['full_name']) ?>" style="width:68px;height:68px;border-radius:50%;object-fit:cover;border:2.5px solid var(--primary);box-shadow:0 0 15px var(--primary-glow);">
              <div>
                <div style="display:flex;align-items:center;gap:0.4rem;">
                  <h4 style="font-size:1.15rem;"><?= e($eng['full_name']) ?></h4>
                  <i data-lucide="check-circle" style="width:16px;height:16px;color:var(--primary);fill:rgba(0,240,255,0.2);"></i>
                </div>
                <div style="font-size:0.82rem;color:var(--primary);font-weight:600;margin-top:0.2rem;"><?= e($eng['title']) ?></div>
                <div style="font-size:0.8rem;color:var(--text-dim);margin-top:0.2rem;">
                  <i data-lucide="map-pin" style="width:12px;height:12px;vertical-align:middle;"></i> <?= e($eng['location']) ?>
                </div>
              </div>
            </div>

            <p style="font-size:0.88rem;line-height:1.55;margin-bottom:1.25rem;color:var(--text-muted);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
              <?= e($eng['bio']) ?>
            </p>

            <div style="display:flex;flex-wrap:wrap;gap:0.4rem;margin-bottom:1.5rem;">
              <span class="badge badge-warning" style="font-size:0.75rem;">★ <?= number_format($eng['rating'], 2) ?> (<?= $eng['total_reviews'] ?>)</span>
              <span class="badge badge-info" style="font-size:0.75rem;"><?= $eng['experience_years'] ?>+ Years Exp</span>
              <span class="badge badge-<?= $eng['availability_status'] === 'available' ? 'success' : 'muted' ?>" style="font-size:0.75rem;">
                <span class="badge-dot"></span> <?= ucfirst($eng['availability_status']) ?>
              </span>
            </div>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border-glass);padding-top:1.2rem;">
            <div>
              <span style="font-size:0.75rem;color:var(--text-dim);">Hourly Rate</span>
              <div style="font-size:1.25rem;font-weight:800;font-family:var(--font-mono);color:var(--text-main);">
                <?= format_currency($eng['hourly_rate']) ?>/hr
              </div>
            </div>
            <a href="<?= BASE_URL ?>engineer-detail.php?id=<?= $eng['id'] ?>" class="btn btn-primary btn-sm">
              View Profile &amp; Book
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="text-align:center;margin-top:3rem;">
      <a href="<?= BASE_URL ?>engineers.php" class="btn btn-outline">
        Browse All Engineers &amp; Filter By Skill <i data-lucide="arrow-right" style="width:16px;height:16px;"></i>
      </a>
    </div>
  </div>
</section>

<!-- 3.5 Customer-to-Customer Property Exchange Section -->
<section class="section" style="background: radial-gradient(circle at 50% 30%, rgba(0, 240, 255, 0.05) 0%, transparent 70%); border-top:1px solid var(--border-glass);">
  <div class="container">
    <div class="section-title">
      <span class="badge badge-primary">ZERO BROKERAGE &bull; DIRECT INTERACTION</span>
      <h2>Customer-to-Customer Property Exchange</h2>
      <p>Discover verified rental flats, apartments, and homes listed directly by registered customers with instant WhatsApp chat and scheduled property visits.</p>
    </div>

    <div class="property-grid" style="margin-top:0;">
      <?php foreach ($featured_properties as $prop): 
        $facilities = json_decode($prop['facilities'] ?? '[]', true) ?: [];
        $wa_url = get_property_whatsapp_url(
            $prop['whatsapp_number'],
            $prop['property_code'],
            $prop['title'],
            $prop['city'] . ', ' . $prop['state']
        );
      ?>
        <div class="property-card">
          <div class="property-card-media">
            <img src="<?= e($prop['primary_image']) ?>" alt="<?= e($prop['title']) ?>" loading="lazy">
            <div class="property-media-overlay-top">
              <span class="prop-id-badge"><i data-lucide="tag" style="width:11px; height:11px;"></i> <?= e($prop['property_code']) ?></span>
              <span class="prop-purpose-badge prop-purpose-<?= e($prop['listing_type']) ?>">For <?= ucfirst(e($prop['listing_type'])) ?></span>
            </div>
            <div class="property-media-overlay-bottom">
              <?= render_property_availability_badge($prop['availability_status']) ?>
            </div>
          </div>

          <div class="property-card-body">
            <div class="property-location">
              <i data-lucide="map-pin" style="width:14px; height:14px; color:var(--primary);"></i>
              <span><?= e($prop['city']) ?>, <?= e($prop['state']) ?></span>
            </div>

            <h4 class="property-title">
              <a href="<?= BASE_URL ?>property-detail.php?id=<?= $prop['id'] ?>"><?= e($prop['title']) ?></a>
            </h4>

            <div class="property-specs-strip">
              <div class="property-spec-item"><i data-lucide="bed"></i> <span><?= $prop['bedrooms'] ?> BHK</span></div>
              <div class="property-spec-item"><i data-lucide="bath"></i> <span><?= $prop['bathrooms'] ?> Bath</span></div>
              <?php if (!empty($prop['area_sqft'])): ?>
                <div class="property-spec-item"><i data-lucide="maximize"></i> <span><?= number_format($prop['area_sqft']) ?> sq.ft</span></div>
              <?php endif; ?>
            </div>

            <div class="property-price-box">
              <div>
                <span class="property-price-val"><?= format_currency($prop['price']) ?></span>
                <span class="property-price-period"><?= $prop['listing_type'] === 'rent' ? '/ mo' : '' ?></span>
              </div>
              <div class="property-deposit-val">Dep: <?= format_currency($prop['security_deposit']) ?></div>
            </div>

            <div class="property-owner-strip">
              <div class="property-owner-info">
                <img src="<?= get_avatar_url($prop['owner_avatar'], $prop['owner_name']) ?>" alt="Owner" class="property-owner-avatar">
                <div>
                  <div class="property-owner-name"><?= e($prop['owner_name']) ?></div>
                  <div class="property-owner-badge"><i data-lucide="check-circle" style="width:11px; height:11px;"></i> Verified Customer</div>
                </div>
              </div>
            </div>

            <div class="property-actions-row">
              <?php if ($wa_url): ?>
                <a href="<?= e($wa_url) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp btn-sm">
                  <i data-lucide="message-circle" style="width:15px; height:15px;"></i> WhatsApp
                </a>
              <?php endif; ?>
              <a href="<?= BASE_URL ?>property-detail.php?id=<?= $prop['id'] ?>" class="btn btn-primary btn-sm">
                View &amp; Book <i data-lucide="arrow-right" style="width:14px; height:14px;"></i>
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="text-align:center; margin-top:3rem; display:flex; justify-content:center; gap:1rem; flex-wrap:wrap;">
      <a href="<?= BASE_URL ?>properties.php" class="btn btn-primary btn-lg">
        <i data-lucide="compass"></i> Explore All Properties
      </a>
      <a href="<?= BASE_URL ?>customer/property-form.php" class="btn btn-outline btn-lg">
        <i data-lucide="plus-circle"></i> List Your Property
      </a>
    </div>
  </div>
</section>

<!-- 4. Interactive 20% Advance Workflow Diagram -->
<section class="section" style="background:rgba(12, 18, 34, 0.6);border-top:1px solid var(--border-glass);border-bottom:1px solid var(--border-glass);">
  <div class="container">
    <div class="section-title">
      <span class="badge badge-secondary">TRANSPARENT WORKFLOW</span>
      <h2>How VoltCraft Service Booking Operates</h2>
      <p>A structured 5-stage protocol engineered for client security and technical precision.</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1.5rem;position:relative;">
      <!-- Step 1 -->
      <div class="glass-card" style="text-align:center;">
        <div style="width:48px;height:48px;border-radius:50%;background:rgba(0,240,255,0.1);border:1px solid var(--primary);color:var(--primary);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-weight:800;font-size:1.2rem;">1</div>
        <h4 style="margin-bottom:0.6rem;">Select &amp; Submit</h4>
        <p style="font-size:0.85rem;">Choose an engineer, specify your hardware issue, and attach photos or fault logs.</p>
      </div>

      <!-- Step 2 -->
      <div class="glass-card" style="text-align:center;">
        <div style="width:48px;height:48px;border-radius:50%;background:rgba(139,92,246,0.1);border:1px solid var(--secondary);color:var(--secondary);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-weight:800;font-size:1.2rem;">2</div>
        <h4 style="margin-bottom:0.6rem;">Engineer Review</h4>
        <p style="font-size:0.85rem;">Engineer verifies technical feasibility, tooling requirements, and accepts the request.</p>
      </div>

      <!-- Step 3 -->
      <div class="glass-card" style="text-align:center;border-color:var(--border-glow);">
        <div style="width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);color:#060913;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-weight:800;font-size:1.2rem;">3</div>
        <h4 style="margin-bottom:0.6rem;">20% Advance Lock</h4>
        <p style="font-size:0.85rem;">Deposit the 20% advance via UPI / Card. Booking locks into the engineer schedule.</p>
      </div>

      <!-- Step 4 -->
      <div class="glass-card" style="text-align:center;">
        <div style="width:48px;height:48px;border-radius:50%;background:rgba(16,185,129,0.1);border:1px solid var(--success);color:var(--success);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-weight:800;font-size:1.2rem;">4</div>
        <h4 style="margin-bottom:0.6rem;">Service Execution</h4>
        <p style="font-size:0.85rem;">Engineer provides on-site or lab repair with live status tracking on your dashboard.</p>
      </div>

      <!-- Step 5 -->
      <div class="glass-card" style="text-align:center;">
        <div style="width:48px;height:48px;border-radius:50%;background:rgba(245,158,11,0.1);border:1px solid var(--warning);color:var(--warning);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;font-weight:800;font-size:1.2rem;">5</div>
        <h4 style="margin-bottom:0.6rem;">Verify &amp; Balance</h4>
        <p style="font-size:0.85rem;">Inspect test waveforms, settle the remaining 80% balance, and submit your review.</p>
      </div>
    </div>
  </div>
</section>

<!-- 5. Customer Testimonials -->
<section class="section">
  <div class="container">
    <div class="section-title">
      <span class="badge badge-warning">CLIENT EXPERIENCES</span>
      <h2>Real Feedback from Real Repairs</h2>
      <p>Verified reviews from engineering labs, businesses, and electronics owners.</p>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(320px, 1fr));gap:2rem;">
      <?php foreach ($reviews as $rev): ?>
        <div class="glass-card">
          <div style="display:flex;align-items:center;gap:0.3rem;margin-bottom:1rem;color:var(--warning);">
            <?php for ($i = 0; $i < $rev['rating']; $i++): ?>★<?php endfor; ?>
          </div>
          <p style="font-style:italic;font-size:0.95rem;line-height:1.65;margin-bottom:1.5rem;color:var(--text-main);">
            &ldquo;<?= e($rev['comment']) ?>&rdquo;
          </p>
          <div style="display:flex;align-items:center;gap:0.9rem;border-top:1px solid var(--border-glass);padding-top:1rem;">
            <img src="<?= get_avatar_url($rev['avatar'], $rev['full_name']) ?>" alt="<?= e($rev['full_name']) ?>" style="width:42px;height:42px;border-radius:50%;">
            <div>
              <div style="font-weight:700;font-size:0.92rem;"><?= e($rev['full_name']) ?></div>
              <div style="font-size:0.78rem;color:var(--text-dim);">
                Engineer: <strong style="color:var(--primary);"><?= e($rev['engineer_name']) ?></strong> &bull; <?= e($rev['service_name']) ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- 6. Call To Action Banner -->
<section class="section" style="padding-bottom:6rem;">
  <div class="container">
    <div class="glass-card" style="background:linear-gradient(135deg, rgba(16,24,43,0.9) 0%, rgba(20,35,65,0.85) 100%);border-color:var(--border-glow);padding:3.5rem 2.5rem;text-align:center;">
      <h2 style="font-size:clamp(1.9rem, 3.8vw, 2.8rem);margin-bottom:1rem;">
        Are You a Certified Electronics Engineer?
      </h2>
      <p style="max-width:600px;margin:0 auto 2rem;font-size:1.05rem;">
        Join our elite network of hardware professionals. Receive direct diagnostic requests, earn competitive rates, and receive fast 20% advance deposits directly to your UPI ID.
      </p>
      <div style="display:flex;justify-content:center;gap:1.25rem;flex-wrap:wrap;">
        <a href="<?= BASE_URL ?>register.php?role=engineer" class="btn btn-primary btn-lg">
          <i data-lucide="award"></i> Apply as an Engineer
        </a>
        <a href="<?= BASE_URL ?>contact.php" class="btn btn-outline btn-lg">
          Contact Engineering Operations
        </a>
      </div>
    </div>
  </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
