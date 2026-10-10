<?php
$page_title = 'Frequently Asked Questions — VoltCraft 3D';
$page_desc = 'Answers to common questions about electronic service booking, 20% advance escrow, refunds, and engineer qualifications.';
require_once __DIR__ . '/includes/header.php';
?>

<div class="section" style="padding-top:3.5rem;">
  <div class="container" style="max-width:850px;">
    <div class="section-title">
      <span class="badge badge-primary">HELP CENTER</span>
      <h2>Frequently Asked Questions</h2>
      <p>Everything you need to know about our electronics engineering booking process and escrow policy.</p>
    </div>

    <!-- FAQ Accordion Items -->
    <div style="display:flex;flex-direction:column;gap:1rem;">
      <details class="glass-card" open style="cursor:pointer;padding:1.5rem;">
        <summary style="font-weight:700;font-size:1.1rem;color:var(--text-main);list-style:none;display:flex;justify-content:space-between;align-items:center;">
          <span>Why is a 20% advance payment required?</span>
          <i data-lucide="chevron-down" style="width:18px;height:18px;color:var(--primary);"></i>
        </summary>
        <p style="margin-top:1rem;font-size:0.92rem;line-height:1.7;color:var(--text-muted);">
          Specialized electronics repairs require the engineer to reserve bench time, procure custom microscopic SMD parts (such as PMICs, MOSFET arrays, and diodes), and bring calibrated oscilloscopes and thermal imagers. The 20% advance secures your appointment slot and prevents unannounced cancellations.
        </p>
      </details>

      <details class="glass-card" style="cursor:pointer;padding:1.5rem;">
        <summary style="font-weight:700;font-size:1.1rem;color:var(--text-main);list-style:none;display:flex;justify-content:space-between;align-items:center;">
          <span>How does the 20% advance calculation work?</span>
          <i data-lucide="chevron-down" style="width:18px;height:18px;color:var(--primary);"></i>
        </summary>
        <p style="margin-top:1rem;font-size:0.92rem;line-height:1.7;color:var(--text-muted);">
          When an engineer accepts your request with an estimated service total (for instance, ₹5,000), exactly 20% (₹1,000) is collected upfront via UPI or online gateway. The remaining 80% (₹4,000) is paid only after you inspect and verify the repaired equipment.
        </p>
      </details>

      <details class="glass-card" style="cursor:pointer;padding:1.5rem;">
        <summary style="font-weight:700;font-size:1.1rem;color:var(--text-main);list-style:none;display:flex;justify-content:space-between;align-items:center;">
          <span>What happens if an engineer rejects my service request?</span>
          <i data-lucide="chevron-down" style="width:18px;height:18px;color:var(--primary);"></i>
        </summary>
        <p style="margin-top:1rem;font-size:0.92rem;line-height:1.7;color:var(--text-muted);">
          If an engineer is fully booked or cannot source proprietary components, they will reject the request with an explanatory note. No payment is charged, and you can instantly reassign the request to another qualified engineer with one click.
        </p>
      </details>

      <details class="glass-card" style="cursor:pointer;padding:1.5rem;">
        <summary style="font-weight:700;font-size:1.1rem;color:var(--text-main);list-style:none;display:flex;justify-content:space-between;align-items:center;">
          <span>Are engineers verified by VoltCraft?</span>
          <i data-lucide="chevron-down" style="width:18px;height:18px;color:var(--primary);"></i>
        </summary>
        <p style="margin-top:1rem;font-size:0.92rem;line-height:1.7;color:var(--text-muted);">
          Yes. Every engineer undergoes a 4-step verification audit: government ID validation, technical degree and diploma verification, background check, and a practical bench diagnostic test (IPC-A-610 workmanship standard).
        </p>
      </details>

      <details class="glass-card" style="cursor:pointer;padding:1.5rem;">
        <summary style="font-weight:700;font-size:1.1rem;color:var(--text-main);list-style:none;display:flex;justify-content:space-between;align-items:center;">
          <span>What warranty is provided on completed repairs?</span>
          <i data-lucide="chevron-down" style="width:18px;height:18px;color:var(--primary);"></i>
        </summary>
        <p style="margin-top:1rem;font-size:0.92rem;line-height:1.7;color:var(--text-muted);">
          All completed motherboard and component-level repairs come with a standard 90-day hardware guarantee covering the replaced silicon components and solder traces.
        </p>
      </details>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
