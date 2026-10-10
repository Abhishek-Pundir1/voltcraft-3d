<?php
$page_title = 'How It Works & 20% Advance Escrow Policy — VoltCraft 3D';
$page_desc = 'Learn about our transparent electronic service booking process, 20% advance fee escrow, and engineer dispatch guarantee.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="section" style="padding-top:3.5rem;">
  <div class="container">
    <div class="section-title">
      <span class="badge badge-primary">TRANSPARENT ARCHITECTURE</span>
      <h2>How the VoltCraft 3D Platform Works</h2>
      <p>A dependable, escrow-protected workflow designed to safeguard both customers and hardware engineers from unverified work and payment disputes.</p>
    </div>

    <!-- 5 Step Deep Dive -->
    <div style="display:flex;flex-direction:column;gap:2rem;max-width:900px;margin:0 auto 4rem;">
      <div class="glass-card" style="display:flex;gap:1.5rem;align-items:flex-start;">
        <div style="width:48px;height:48px;border-radius:var(--radius-sm);background:rgba(0,240,255,0.1);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.3rem;flex-shrink:0;">
          01
        </div>
        <div>
          <h3 style="font-size:1.25rem;margin-bottom:0.5rem;">Select Certified Engineer &amp; Submit Problem Scope</h3>
          <p style="font-size:0.92rem;line-height:1.65;color:var(--text-muted);">
            Browse our directory of verified electronics engineers filtered by hardware expertise, location, and customer ratings. Submit a detailed service request describing your device faults, symptoms, preferred date, and photos of damaged components or circuit boards.
          </p>
        </div>
      </div>

      <div class="glass-card" style="display:flex;gap:1.5rem;align-items:flex-start;">
        <div style="width:48px;height:48px;border-radius:var(--radius-sm);background:rgba(139,92,246,0.1);color:var(--secondary);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.3rem;flex-shrink:0;">
          02
        </div>
        <div>
          <h3 style="font-size:1.25rem;margin-bottom:0.5rem;">Engineer Feasibility Check &amp; Acceptance</h3>
          <p style="font-size:0.92rem;line-height:1.65;color:var(--text-muted);">
            The assigned engineer reviews the diagnostic request, checks replacement component availability, and confirms scheduling capacity. If accepted, the booking moves to <code>PAYMENT_PENDING</code> and you are notified instantly.
          </p>
        </div>
      </div>

      <div class="glass-card" style="display:flex;gap:1.5rem;align-items:flex-start;border-color:var(--border-glow);box-shadow:var(--shadow-glow);">
        <div style="width:48px;height:48px;border-radius:var(--radius-sm);background:linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);color:#060913;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.3rem;flex-shrink:0;">
          03
        </div>
        <div>
          <h3 style="font-size:1.25rem;margin-bottom:0.5rem;">Pay 20% Advance Escrow Deposit</h3>
          <p style="font-size:0.92rem;line-height:1.65;color:var(--text-muted);margin-bottom:1rem;">
            To eliminate phantom cancellations and reserve specialized diagnostic instruments, a strict <strong>20% advance payment</strong> is required.
          </p>
          <div class="payment-split-box" style="margin:0.5rem 0;">
            <div class="split-item">
              <div class="split-label">Total Service Estimate</div>
              <div class="split-value">₹ 5,000.00</div>
            </div>
            <div class="split-item">
              <div class="split-label">20% Advance Required</div>
              <div class="split-value highlight">₹ 1,000.00</div>
            </div>
            <div class="split-item">
              <div class="split-label">Remaining Balance (80%)</div>
              <div class="split-value">₹ 4,000.00</div>
            </div>
          </div>
          <p style="font-size:0.82rem;color:var(--text-dim);margin-top:0.75rem;">
            Deposit via engineer UPI ID or online gateway. The moment the advance transaction reference is verified, the booking is instantly locked as <code>CONFIRMED</code>.
          </p>
        </div>
      </div>

      <div class="glass-card" style="display:flex;gap:1.5rem;align-items:flex-start;">
        <div style="width:48px;height:48px;border-radius:var(--radius-sm);background:rgba(16,185,129,0.1);color:var(--success);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.3rem;flex-shrink:0;">
          04
        </div>
        <div>
          <h3 style="font-size:1.25rem;margin-bottom:0.5rem;">Diagnostic &amp; Repair Execution</h3>
          <p style="font-size:0.92rem;line-height:1.65;color:var(--text-muted);">
            The engineer arrives at your scheduled appointment or receives the hardware in the specialized clean lab. Real-time status changes (<code>IN_PROGRESS</code>) are posted directly to your customer dashboard.
          </p>
        </div>
      </div>

      <div class="glass-card" style="display:flex;gap:1.5rem;align-items:flex-start;">
        <div style="width:48px;height:48px;border-radius:var(--radius-sm);background:rgba(245,158,11,0.1);color:var(--warning);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.3rem;flex-shrink:0;">
          05
        </div>
        <div>
          <h3 style="font-size:1.25rem;margin-bottom:0.5rem;">Verification, Balance Settlement &amp; Review</h3>
          <p style="font-size:0.92rem;line-height:1.65;color:var(--text-muted);">
            Once diagnostics and load-testing pass, the engineer marks the repair as <code>COMPLETED</code>. You settle the remaining 80% balance and leave a verified review to help other customers.
          </p>
        </div>
      </div>
    </div>

    <!-- Assurance Highlights -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(280px, 1fr));gap:2rem;">
      <div class="glass-card">
        <div style="color:var(--primary);font-size:2rem;margin-bottom:0.75rem;"><i data-lucide="shield-alert"></i></div>
        <h4 style="margin-bottom:0.5rem;">Zero Risk Guarantee</h4>
        <p style="font-size:0.88rem;color:var(--text-muted);">If an engineer fails to arrive or cannot begin diagnostics due to technical failure, the 20% advance is refunded in full.</p>
      </div>

      <div class="glass-card">
        <div style="color:var(--secondary);font-size:2rem;margin-bottom:0.75rem;"><i data-lucide="cpu"></i></div>
        <h4 style="margin-bottom:0.5rem;">IPC-A-610 Workmanship</h4>
        <p style="font-size:0.88rem;color:var(--text-muted);">All solder joints, trace repairs, and component placements adhere strictly to international electronics manufacturing standards.</p>
      </div>

      <div class="glass-card">
        <div style="color:var(--success);font-size:2rem;margin-bottom:0.75rem;"><i data-lucide="check-check"></i></div>
        <h4 style="margin-bottom:0.5rem;">Audit Trail &amp; Receipts</h4>
        <p style="font-size:0.88rem;color:var(--text-muted);">Every transaction reference is digitally archived with verifiable timestamps and printable tax-compliant receipt statements.</p>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
