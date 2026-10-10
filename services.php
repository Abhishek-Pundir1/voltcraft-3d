<?php
$page_title = 'Electronics Engineering & Diagnostic Services — VoltCraft 3D';
$page_desc = 'Comprehensive hardware engineering services: PCB micro-soldering, industrial PLC repair, solar inverters, and drone electronics.';
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$services = $pdo->query("SELECT s.*, COUNT(es.engineer_id) as engineer_count 
                         FROM services s 
                         LEFT JOIN engineer_services es ON s.id = es.service_id 
                         WHERE s.is_active = 1 
                         GROUP BY s.id 
                         ORDER BY s.id ASC")->fetchAll();
?>

<div class="section" style="padding-top:3.5rem;">
  <div class="container">
    <div class="section-title">
      <span class="badge badge-primary">CATALOG OF CAPABILITIES</span>
      <h2>Specialized Electronics Services</h2>
      <p>Precision component-level troubleshooting, schematic analysis, and hardware restoration across eight core technical domains.</p>
    </div>

    <!-- Services Grid -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(360px, 1fr));gap:2.25rem;">
      <?php foreach ($services as $srv): ?>
        <div class="glass-card glass-card-tilt" style="display:flex;flex-direction:column;justify-content:space-between;">
          <div>
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
              <div style="width:58px;height:58px;border-radius:var(--radius-sm);background:rgba(0,240,255,0.12);border:1px solid rgba(0,240,255,0.3);display:flex;align-items:center;justify-content:center;color:var(--primary);">
                <i data-lucide="<?= e($srv['icon']) ?>" style="width:30px;height:30px;"></i>
              </div>
              <div style="text-align:right;">
                <span class="badge badge-secondary"><?= e($srv['category']) ?></span>
                <div style="font-size:0.75rem;color:var(--text-dim);margin-top:0.3rem;">
                  <?= $srv['engineer_count'] ?> Certified Engineers
                </div>
              </div>
            </div>

            <h3 style="font-size:1.4rem;margin-bottom:0.85rem;"><?= e($srv['name']) ?></h3>
            <p style="font-size:0.92rem;line-height:1.65;color:var(--text-muted);margin-bottom:1.5rem;">
              <?= e($srv['description']) ?>
            </p>

            <div style="display:flex;align-items:center;gap:1.5rem;margin-bottom:1.5rem;padding:0.75rem 1rem;background:rgba(255,255,255,0.03);border-radius:var(--radius-sm);">
              <div>
                <span style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;">Estimated Duration</span>
                <div style="font-weight:700;font-size:0.95rem;color:var(--text-main);">
                  <i data-lucide="clock" style="width:14px;height:14px;vertical-align:middle;color:var(--primary);"></i> <?= e($srv['estimated_time']) ?>
                </div>
              </div>
              <div style="border-left:1px solid var(--border-glass);padding-left:1.5rem;">
                <span style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;">Advance Escrow</span>
                <div style="font-weight:700;font-size:0.95rem;color:var(--warning);">
                  20% (<?= format_currency($srv['base_price'] * ADVANCE_PERCENTAGE) ?>)
                </div>
              </div>
            </div>
          </div>

          <div style="display:flex;align-items:center;justify-content:space-between;border-top:1px solid var(--border-glass);padding-top:1.25rem;">
            <div>
              <span style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;font-weight:600;">Base Service Fee</span>
              <div style="font-size:1.5rem;font-weight:800;font-family:var(--font-mono);color:var(--primary);">
                <?= format_currency($srv['base_price']) ?>
              </div>
            </div>

            <div style="display:flex;gap:0.75rem;">
              <a href="<?= BASE_URL ?>engineers.php?service_id=<?= $srv['id'] ?>" class="btn btn-outline btn-sm" title="Find engineers offering this service">
                <i data-lucide="users" style="width:14px;height:14px;"></i> Engineers
              </a>
              <a href="<?= BASE_URL ?>customer/new-request.php?service_id=<?= $srv['id'] ?>" class="btn btn-primary btn-sm">
                Book Now
              </a>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
