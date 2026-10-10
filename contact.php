<?php
$page_title = 'Contact Support & Enterprise Operations — VoltCraft 3D';
$page_desc = 'Contact our engineering dispatch and technical assistance team for electronics diagnostics, laboratory repairs, or platform support.';
require_once __DIR__ . '/includes/header.php';

$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = clean_input($_POST['name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    $subject = clean_input($_POST['subject'] ?? '');
    $message = clean_input($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_msg = 'Please fill in all required contact fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = 'Please enter a valid email address.';
    } else {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $phone, $subject, $message]);
            $success_msg = 'Thank you! Your technical message has been submitted. Our engineering dispatch will get back to you within 2 hours.';
        } catch (PDOException $e) {
            $error_msg = 'Unable to send message at this time. Please try again.';
        }
    }
}
?>

<div class="section" style="padding-top:3.5rem;">
  <div class="container">
    <div class="section-title">
      <span class="badge badge-primary">24/7 TECHNICAL DESK</span>
      <h2>Contact Technical Operations</h2>
      <p>Have an inquiry about an active diagnostic ticket, laboratory repair, or enterprise hardware service contract? We are here to assist.</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1.3fr;gap:3rem;align-items:start;max-width:1050px;margin:0 auto;">
      <!-- Contact Info Cards -->
      <div style="display:flex;flex-direction:column;gap:1.5rem;">
        <div class="glass-card">
          <div style="width:44px;height:44px;border-radius:var(--radius-sm);background:rgba(0,240,255,0.1);color:var(--primary);display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">
            <i data-lucide="map-pin"></i>
          </div>
          <h4 style="margin-bottom:0.4rem;">Central Diagnostic Lab</h4>
          <p style="font-size:0.88rem;color:var(--text-muted);line-height:1.6;">
            VoltCraft Engineering Center, Block 4, Indiranagar Tech Corridor, Bangalore, Karnataka 560038
          </p>
        </div>

        <div class="glass-card">
          <div style="width:44px;height:44px;border-radius:var(--radius-sm);background:rgba(139,92,246,0.1);color:var(--secondary);display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">
            <i data-lucide="phone-call"></i>
          </div>
          <h4 style="margin-bottom:0.4rem;">Emergency Dispatch Helpline</h4>
          <p style="font-size:0.88rem;color:var(--text-muted);line-height:1.6;">
            Toll-Free: <strong>1800-VOLT-3D</strong> (1800-8658-33)<br>
            Direct Operations: +91 (80) 4920-8000
          </p>
        </div>

        <div class="glass-card">
          <div style="width:44px;height:44px;border-radius:var(--radius-sm);background:rgba(16,185,129,0.1);color:var(--success);display:flex;align-items:center;justify-content:center;margin-bottom:1rem;">
            <i data-lucide="mail"></i>
          </div>
          <h4 style="margin-bottom:0.4rem;">Email Contacts</h4>
          <p style="font-size:0.88rem;color:var(--text-muted);line-height:1.6;">
            General Support: <span style="color:var(--primary);">support@voltcraft.io</span><br>
            Enterprise Lab AMC: <span style="color:var(--primary);">enterprise@voltcraft.io</span>
          </p>
        </div>
      </div>

      <!-- Contact Form -->
      <div class="glass-card" style="padding:2.5rem;">
        <h3 style="font-size:1.4rem;margin-bottom:0.75rem;">Send a Message</h3>
        <p style="font-size:0.88rem;color:var(--text-dim);margin-bottom:1.75rem;">
          Fill out the details below and an on-call electronics engineer will review your inquiry.
        </p>

        <?php if ($success_msg): ?>
          <div class="alert alert-success">
            <i data-lucide="check-circle" style="width:20px;height:20px;"></i> <?= e($success_msg) ?>
          </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
          <div class="alert alert-danger">
            <i data-lucide="alert-triangle" style="width:20px;height:20px;"></i> <?= e($error_msg) ?>
          </div>
        <?php endif; ?>

        <form method="POST" action="">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Your Full Name *</label>
              <input type="text" name="name" class="form-control" placeholder="e.g. Rahul Sharma" required>
            </div>
            <div class="form-group">
              <label class="form-label">Email Address *</label>
              <input type="email" name="email" class="form-control" placeholder="e.g. rahul@example.com" required>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Contact Phone</label>
              <input type="text" name="phone" class="form-control" placeholder="+91 98765 43210">
            </div>
            <div class="form-group">
              <label class="form-label">Subject *</label>
              <input type="text" name="subject" class="form-control" placeholder="e.g. Inverter Board Repair Query" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Detailed Message *</label>
            <textarea name="message" class="form-control form-textarea" placeholder="Describe your technical inquiry, hardware model, or fault symptoms..." required></textarea>
          </div>

          <button type="submit" class="btn btn-primary btn-block">
            <i data-lucide="send"></i> Submit Technical Inquiry
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
