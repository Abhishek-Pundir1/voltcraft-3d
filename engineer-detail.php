<?php
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$eng_id = intval($_GET['id'] ?? 0);

if (!$eng_id) {
    header("Location: " . BASE_URL . "engineers.php");
    exit;
}

// Fetch Engineer & User Info
$stmt = $pdo->prepare("SELECT e.*, u.full_name, u.email, u.phone, u.avatar 
                       FROM engineers e 
                       JOIN users u ON e.user_id = u.id 
                       WHERE e.id = ? AND u.status = 'active'");
$stmt->execute([$eng_id]);
$engineer = $stmt->fetch();

if (!$engineer) {
    echo '<div class="container section" style="text-align:center;"><h2>Engineer Not Found</h2><p>The requested profile does not exist.</p><a href="' . BASE_URL . 'engineers.php" class="btn btn-primary" style="margin-top:1rem;">Back to Directory</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Fetch Skills
$skills = $pdo->prepare("SELECT * FROM engineer_skills WHERE engineer_id = ? ORDER BY proficiency DESC");
$skills->execute([$eng_id]);
$skills_list = $skills->fetchAll();

// Fetch Services
$services = $pdo->prepare("SELECT s.*, es.custom_rate 
                           FROM engineer_services es 
                           JOIN services s ON es.service_id = s.id 
                           WHERE es.engineer_id = ? AND s.is_active = 1");
$services->execute([$eng_id]);
$services_list = $services->fetchAll();

// Fetch Reviews
$reviews = $pdo->prepare("SELECT r.*, u.full_name as customer_name, u.avatar as customer_avatar, s.name as service_name 
                          FROM reviews r 
                          JOIN customers c ON r.customer_id = c.id 
                          JOIN users u ON c.user_id = u.id 
                          JOIN bookings b ON r.booking_id = b.id 
                          JOIN services s ON b.service_id = s.id 
                          WHERE r.engineer_id = ? 
                          ORDER BY r.created_at DESC");
$reviews->execute([$eng_id]);
$reviews_list = $reviews->fetchAll();

$page_title = e($engineer['full_name']) . ' — Electronics Engineering Specialist';
?>

<div class="section" style="padding-top:3.5rem;">
  <div class="container">
    <!-- Breadcrumb -->
    <div style="font-size:0.85rem;color:var(--text-dim);margin-bottom:1.5rem;">
      <a href="<?= BASE_URL ?>index.php">Home</a> &rsaquo; 
      <a href="<?= BASE_URL ?>engineers.php">Engineers</a> &rsaquo; 
      <span style="color:var(--text-main);"><?= e($engineer['full_name']) ?></span>
    </div>

    <div style="display:grid;grid-template-columns:320px 1fr;gap:2.5rem;align-items:start;">
      <!-- Left Column: Identity & Contact Card -->
      <div style="display:flex;flex-direction:column;gap:1.5rem;">
        <div class="glass-card" style="text-align:center;">
          <img src="<?= get_avatar_url($engineer['avatar'], $engineer['full_name']) ?>" alt="<?= e($engineer['full_name']) ?>" style="width:120px;height:120px;border-radius:50%;object-fit:cover;border:3px solid var(--primary);box-shadow:0 0 25px var(--primary-glow);margin:0 auto 1.25rem;">

          <h3 style="font-size:1.4rem;display:flex;align-items:center;justify-content:center;gap:0.4rem;">
            <?= e($engineer['full_name']) ?>
            <i data-lucide="check-circle" style="width:18px;height:18px;color:var(--primary);fill:rgba(0,240,255,0.2);"></i>
          </h3>
          <p style="color:var(--primary);font-size:0.9rem;font-weight:600;margin-top:0.25rem;"><?= e($engineer['title']) ?></p>

          <div style="display:flex;justify-content:center;gap:0.5rem;margin:1rem 0;">
            <span class="badge badge-warning">★ <?= number_format($engineer['rating'], 2) ?> (<?= $engineer['total_reviews'] ?>)</span>
            <span class="badge badge-<?= $engineer['availability_status'] === 'available' ? 'success' : 'muted' ?>">
              <span class="badge-dot"></span> <?= ucfirst($engineer['availability_status']) ?>
            </span>
          </div>

          <div style="border-top:1px solid var(--border-glass);border-bottom:1px solid var(--border-glass);padding:1rem 0;margin:1.25rem 0;">
            <div style="font-size:0.8rem;color:var(--text-dim);text-transform:uppercase;">Diagnostic Rate</div>
            <div style="font-size:1.75rem;font-weight:800;color:var(--text-main);font-family:var(--font-mono);">
              <?= format_currency($engineer['hourly_rate']) ?><span style="font-size:0.9rem;color:var(--text-dim);font-weight:400;"> / hr</span>
            </div>
          </div>

          <div style="text-align:left;display:flex;flex-direction:column;gap:0.8rem;font-size:0.88rem;color:var(--text-muted);">
            <div>
              <i data-lucide="map-pin" style="width:16px;height:16px;color:var(--primary);vertical-align:middle;"></i>
              <strong>Location:</strong> <?= e($engineer['location']) ?>
            </div>
            <div>
              <i data-lucide="award" style="width:16px;height:16px;color:var(--primary);vertical-align:middle;"></i>
              <strong>Experience:</strong> <?= $engineer['experience_years'] ?>+ Years Active
            </div>
            <div>
              <i data-lucide="book-open" style="width:16px;height:16px;color:var(--primary);vertical-align:middle;"></i>
              <strong>Qualifications:</strong> <?= e($engineer['qualification'] ?: 'IPC-A-610 Certified') ?>
            </div>
          </div>

          <div style="margin-top:1.75rem;">
            <a href="<?= BASE_URL ?>customer/new-request.php?engineer_id=<?= $engineer['id'] ?>" class="btn btn-primary btn-block">
              <i data-lucide="calendar-plus"></i> Request Service
            </a>
          </div>
        </div>

        <!-- UPI Payment & QR Code Card -->
        <div class="glass-card">
          <div style="display:flex;align-items:center;gap:0.6rem;margin-bottom:1rem;">
            <i data-lucide="qr-code" style="color:var(--primary);width:20px;height:20px;"></i>
            <h4 style="font-size:1.05rem;">Verified UPI Gateway</h4>
          </div>
          <p style="font-size:0.82rem;color:var(--text-dim);margin-bottom:1rem;">
            Direct 20% advance booking is routed to this verified professional UPI ID via secure escrow.
          </p>

          <div class="qr-container">
            <img src="<?= get_upi_qr_url($engineer['upi_qr_image']) ?>" alt="UPI QR Code">
            <div class="qr-caption"><?= e($engineer['upi_id'] ?: 'rajesh.volt@okhdfcbank') ?></div>
          </div>
        </div>
      </div>

      <!-- Right Column: Details, Skills, Services, Reviews -->
      <div style="display:flex;flex-direction:column;gap:2rem;">
        <!-- Bio & Overview -->
        <div class="glass-card">
          <h3 style="font-size:1.25rem;margin-bottom:1rem;">About &amp; Engineering Philosophy</h3>
          <p style="font-size:0.95rem;line-height:1.7;color:var(--text-muted);">
            <?= nl2br(e($engineer['bio'])) ?>
          </p>
        </div>

        <!-- Technical Skills Proficiency Progress -->
        <div class="glass-card">
          <h3 style="font-size:1.25rem;margin-bottom:1.25rem;">Technical Skills &amp; Diagnostic Tooling</h3>
          <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:1.25rem;">
            <?php foreach ($skills_list as $sk): ?>
              <div>
                <div style="display:flex;justify-content:space-between;font-size:0.85rem;font-weight:600;margin-bottom:0.4rem;">
                  <span><?= e($sk['skill_name']) ?></span>
                  <span style="color:var(--primary);font-family:var(--font-mono);"><?= $sk['proficiency'] ?>%</span>
                </div>
                <div style="width:100%;height:6px;background:rgba(255,255,255,0.06);border-radius:10px;overflow:hidden;">
                  <div style="width:<?= $sk['proficiency'] ?>%;height:100%;background:linear-gradient(90deg, var(--primary) 0%, var(--secondary) 100%);border-radius:10px;"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Services Offered & Pricing -->
        <div class="glass-card">
          <h3 style="font-size:1.25rem;margin-bottom:1.25rem;">Services Offered by This Engineer</h3>
          <div style="display:flex;flex-direction:column;gap:1rem;">
            <?php foreach ($services_list as $srv): ?>
              <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem;background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:var(--radius-sm);flex-wrap:wrap;gap:1rem;">
                <div style="display:flex;align-items:center;gap:1rem;">
                  <div style="width:40px;height:40px;border-radius:var(--radius-sm);background:rgba(0,240,255,0.1);display:flex;align-items:center;justify-content:center;color:var(--primary);">
                    <i data-lucide="<?= e($srv['icon']) ?>" style="width:20px;height:20px;"></i>
                  </div>
                  <div>
                    <h4 style="font-size:1.05rem;"><?= e($srv['name']) ?></h4>
                    <span style="font-size:0.8rem;color:var(--text-dim);"><?= e($srv['category']) ?> &bull; Est: <?= e($srv['estimated_time']) ?></span>
                  </div>
                </div>

                <div style="display:flex;align-items:center;gap:1.5rem;">
                  <div style="text-align:right;">
                    <div style="font-size:0.75rem;color:var(--text-dim);">Service Estimate</div>
                    <div style="font-weight:800;font-size:1.2rem;font-family:var(--font-mono);color:var(--primary);">
                      <?= format_currency($srv['custom_rate'] ?: $srv['base_price']) ?>
                    </div>
                  </div>
                  <a href="<?= BASE_URL ?>customer/new-request.php?engineer_id=<?= $engineer['id'] ?>&service_id=<?= $srv['id'] ?>" class="btn btn-outline btn-sm">
                    Select Service
                  </a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Client Reviews & Feedback -->
        <div class="glass-card">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
            <h3 style="font-size:1.25rem;">Client Reviews (<?= count($reviews_list) ?>)</h3>
            <span class="badge badge-warning">★ <?= number_format($engineer['rating'], 2) ?> Average</span>
          </div>

          <?php if (empty($reviews_list)): ?>
            <p style="color:var(--text-dim);font-size:0.9rem;">No reviews submitted yet for this engineer.</p>
          <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:1.25rem;">
              <?php foreach ($reviews_list as $rev): ?>
                <div style="padding-bottom:1.25rem;border-bottom:1px solid var(--border-glass);">
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;">
                    <div style="display:flex;align-items:center;gap:0.75rem;">
                      <img src="<?= get_avatar_url($rev['customer_avatar'], $rev['customer_name']) ?>" alt="Customer" style="width:36px;height:36px;border-radius:50%;">
                      <div>
                        <div style="font-weight:700;font-size:0.9rem;"><?= e($rev['customer_name']) ?></div>
                        <div style="font-size:0.75rem;color:var(--text-dim);"><?= format_date($rev['created_at'], 'd M Y') ?> &bull; <?= e($rev['service_name']) ?></div>
                      </div>
                    </div>
                    <div style="color:var(--warning);font-size:0.85rem;">
                      <?php for ($i = 0; $i < $rev['rating']; $i++): ?>★<?php endfor; ?>
                    </div>
                  </div>
                  <p style="font-size:0.9rem;line-height:1.6;color:var(--text-muted);">
                    &ldquo;<?= e($rev['comment']) ?>&rdquo;
                  </p>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
