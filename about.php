<?php
$page_title = 'About Us — VoltCraft 3D Technologies';
$page_desc = 'Pioneering on-demand precision electronics engineering, component diagnostics, and high-tech hardware service infrastructure.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="section" style="padding-top:3.5rem;">
  <div class="container">
    <div class="section-title">
      <span class="badge badge-primary">OUR MISSION</span>
      <h2>Engineering the Future of Electronics Repair</h2>
      <p>VoltCraft 3D was founded to bridge the gap between high-end electronics engineering laboratories and clients seeking specialized, component-level repairs.</p>
    </div>

    <!-- Vision Card -->
    <div class="glass-card" style="margin-bottom:3.5rem;padding:3rem 2.5rem;display:grid;grid-template-columns:1fr 1fr;gap:3rem;align-items:center;">
      <div>
        <h3 style="font-size:1.8rem;margin-bottom:1.25rem;">Why Board-Level Repair Matters</h3>
        <p style="font-size:0.95rem;line-height:1.7;color:var(--text-muted);margin-bottom:1rem;">
          Modern electronics manufacturers often urge customers to scrap expensive assemblies or pay exorbitant fees for total module swaps. A single burned capacitor or blown MOSFET should never render a ₹1,00,000 piece of equipment obsolete.
        </p>
        <p style="font-size:0.95rem;line-height:1.7;color:var(--text-muted);">
          VoltCraft connects customers with IPC-certified electronics engineers equipped with thermal cameras, high-frequency oscilloscopes, and microscope soldering stations capable of diagnosing and repairing hardware down to the individual 0201 SMD component.
        </p>
      </div>

      <div style="background:rgba(6,9,19,0.7);border:1px solid var(--border-glass);border-radius:var(--radius-md);padding:2rem;">
        <h4 style="margin-bottom:1.25rem;color:var(--primary);display:flex;align-items:center;gap:0.5rem;">
          <i data-lucide="award"></i> Core Platform Benchmarks
        </h4>
        <ul style="list-style:none;display:flex;flex-direction:column;gap:1rem;font-size:0.92rem;">
          <li style="display:flex;gap:0.75rem;">
            <i data-lucide="check" style="color:var(--success);width:18px;height:18px;flex-shrink:0;"></i>
            <span><strong>100% Background-Checked Engineers:</strong> Strict credential vetting including degrees and certifications.</span>
          </li>
          <li style="display:flex;gap:0.75rem;">
            <i data-lucide="check" style="color:var(--success);width:18px;height:18px;flex-shrink:0;"></i>
            <span><strong>20% Advance Escrow:</strong> Transparent advance payment system with zero hidden surcharges.</span>
          </li>
          <li style="display:flex;gap:0.75rem;">
            <i data-lucide="check" style="color:var(--success);width:18px;height:18px;flex-shrink:0;"></i>
            <span><strong>Static-Free Cleanrooms (ESD Safe):</strong> All diagnostic benches adhere to ANSI/ESD S20.20 electrostatic standards.</span>
          </li>
          <li style="display:flex;gap:0.75rem;">
            <i data-lucide="check" style="color:var(--success);width:18px;height:18px;flex-shrink:0;"></i>
            <span><strong>3D Interactive Telemetry:</strong> Real-time hardware fault status representation and tracking.</span>
          </li>
        </ul>
      </div>
    </div>

    <!-- Stats Counter Strip -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:1.5rem;margin-bottom:4rem;">
      <div class="glass-card" style="text-align:center;">
        <div style="font-size:2.4rem;font-weight:800;color:var(--primary);font-family:var(--font-mono);" data-counter="4800" data-suffix="+">4,800+</div>
        <div style="font-size:0.85rem;color:var(--text-dim);font-weight:600;margin-top:0.35rem;text-transform:uppercase;">Circuits Restored</div>
      </div>
      <div class="glass-card" style="text-align:center;">
        <div style="font-size:2.4rem;font-weight:800;color:var(--secondary);font-family:var(--font-mono);" data-counter="98" data-suffix="%">98%</div>
        <div style="font-size:0.85rem;color:var(--text-dim);font-weight:600;margin-top:0.35rem;text-transform:uppercase;">First-Time Fix Rate</div>
      </div>
      <div class="glass-card" style="text-align:center;">
        <div style="font-size:2.4rem;font-weight:800;color:var(--success);font-family:var(--font-mono);" data-counter="25" data-suffix=" Lac+">₹25 Lac+</div>
        <div style="font-size:0.85rem;color:var(--text-dim);font-weight:600;margin-top:0.35rem;text-transform:uppercase;">Client Savings</div>
      </div>
      <div class="glass-card" style="text-align:center;">
        <div style="font-size:2.4rem;font-weight:800;color:var(--warning);font-family:var(--font-mono);" data-counter="50" data-suffix="+">50+</div>
        <div style="font-size:0.85rem;color:var(--text-dim);font-weight:600;margin-top:0.35rem;text-transform:uppercase;">Cities Covered</div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
