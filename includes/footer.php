<?php
// ============================================================================
// VoltCraft 3D - Global Footer & Script Attachments
// ============================================================================
?>
  <!-- Footer Component -->
  <footer class="footer">
    <div class="container">
      <div class="footer-grid">
        <!-- Brand Column -->
        <div class="footer-brand">
          <a href="<?= BASE_URL ?>index.php" class="navbar-brand" style="margin-bottom:1rem;">
            <div class="brand-icon">
              <i data-lucide="zap" style="width:20px;height:20px;"></i>
            </div>
            <span>Volt<span class="gradient-text">Craft</span> 3D</span>
          </a>
          <p>Next-generation electronic engineering, robotics diagnostics, and PCB hardware servicing platform with 3D visualization and verified engineers.</p>
          <div style="display:flex;gap:0.75rem;margin-top:1.25rem;">
            <span class="badge badge-success"><span class="badge-dot"></span> All Systems Operational</span>
            <span class="badge badge-primary">20% Advance Escrow</span>
          </div>
        </div>

        <!-- Quick Links -->
        <div>
          <h4 class="footer-heading">Platform</h4>
          <ul class="footer-links">
            <li><a href="<?= BASE_URL ?>properties.php">Customer Properties (C2C)</a></li>
            <li><a href="<?= BASE_URL ?>services.php">Browse Services</a></li>
            <li><a href="<?= BASE_URL ?>engineers.php">Find Engineers</a></li>
            <li><a href="<?= BASE_URL ?>how-it-works.php">Workflow & Guarantee</a></li>
            <li><a href="<?= BASE_URL ?>about.php">About VoltCraft</a></li>
            <li><a href="<?= BASE_URL ?>faq.php">Frequently Asked Questions</a></li>
          </ul>
        </div>

        <!-- Service Domains -->
        <div>
          <h4 class="footer-heading">Specialties</h4>
          <ul class="footer-links">
            <li><a href="<?= BASE_URL ?>services.php">Micro-Soldering & PCB</a></li>
            <li><a href="<?= BASE_URL ?>services.php">Industrial Automation & PLC</a></li>
            <li><a href="<?= BASE_URL ?>services.php">Solar Inverters & BMS</a></li>
            <li><a href="<?= BASE_URL ?>services.php">Drone Avionics & ESCs</a></li>
            <li><a href="<?= BASE_URL ?>services.php">Audiophile Systems & Hi-Fi</a></li>
          </ul>
        </div>

        <!-- Support & Security -->
        <div>
          <h4 class="footer-heading">Support & Trust</h4>
          <ul class="footer-links">
            <li><a href="<?= BASE_URL ?>contact.php">Contact Technical Team</a></li>
            <li><a href="<?= BASE_URL ?>login.php">Engineer Portal Sign-in</a></li>
            <li><a href="<?= BASE_URL ?>admin/login.php">Admin Access Console</a></li>
            <li><span style="font-size:0.85rem;color:var(--text-dim);">Toll Free: 1800-VOLT-3D</span></li>
            <li><span style="font-size:0.85rem;color:var(--text-dim);">Email: support@voltcraft.io</span></li>
          </ul>
        </div>
      </div>

      <!-- Footer Bottom Bar -->
      <div class="footer-bottom">
        <div>
          &copy; <?= date('Y') ?> VoltCraft 3D Technologies. Built with Three.js, PHP 8 &amp; MySQL.
        </div>
        <div style="display:flex;gap:1.5rem;">
          <a href="<?= BASE_URL ?>how-it-works.php" style="color:var(--text-dim);">Terms of Service</a>
          <a href="<?= BASE_URL ?>how-it-works.php" style="color:var(--text-dim);">Privacy Policy</a>
          <a href="<?= BASE_URL ?>how-it-works.php" style="color:var(--text-dim);">20% Advance Escrow Policy</a>
        </div>
      </div>
    </div>
  </footer>

  <!-- Scripts -->
  <script src="<?= BASE_URL ?>assets/js/three.min.js"></script>
  <script src="<?= BASE_URL ?>assets/js/three-hero.js"></script>
  <script src="<?= BASE_URL ?>assets/js/tilt.js"></script>
  <script src="<?= BASE_URL ?>assets/js/main.js"></script>
  <script src="<?= BASE_URL ?>assets/js/api-client.js"></script>

  <script>
    // Initialize Lucide SVG icons safely
    if (typeof lucide !== 'undefined') {
      lucide.createIcons();
    }
  </script>
</body>
</html>
