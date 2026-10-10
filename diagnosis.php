<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/diagnosis-engine.php';

$page_title = 'Smart Electronics Diagnosis — ' . APP_NAME;
$page_desc = 'Interactive rule-based diagnostics for TVs, ACs, Refrigerators, Washing Machines, Microwaves, and Inverters. Get preliminary causes, safe troubleshooting, and book certified repair specialists.';
$current_page = 'diagnosis.php';

$pdo = get_db_connection();
$kb = get_diagnosis_knowledge_base();

// Fetch verified engineers for booking option
$engineers = $pdo->query("SELECT e.id, e.hourly_rate, e.rating, e.location, u.full_name, e.title, u.avatar 
                          FROM engineers e 
                          JOIN users u ON e.user_id = u.id 
                          WHERE u.status = 'active' AND e.is_verified = 1 
                          ORDER BY e.rating DESC, u.full_name ASC")->fetchAll();

// Pre-fill user details if logged in
$logged_user = current_user();
$default_address = '';
$default_city = '';
if ($logged_user && $logged_user['role'] === 'customer') {
    $cust_id = get_customer_id($logged_user['id']);
    $c_stmt = $pdo->prepare("SELECT address, city FROM customers WHERE id = ?");
    $c_stmt->execute([$cust_id]);
    $c_data = $c_stmt->fetch();
    if ($c_data) {
        $default_address = $c_data['address'] ?? '';
        $default_city = $c_data['city'] ?? '';
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="background: radial-gradient(circle at 50% 10%, rgba(0,240,255,0.08) 0%, transparent 60%); padding: 3rem 0 5rem;">
  <div class="container" style="max-width: 1100px;">
    
    <!-- Hero Header -->
    <div style="text-align:center;margin-bottom:3rem;">
      <div style="display:inline-flex;align-items:center;gap:0.5rem;padding:0.4rem 1rem;background:rgba(0,240,255,0.1);border:1px solid rgba(0,240,255,0.25);border-radius:30px;color:var(--primary);font-size:0.85rem;font-weight:700;margin-bottom:1rem;">
        <i data-lucide="cpu" style="width:16px;height:16px;"></i> RULE-BASED HARDWARE DIAGNOSTICS
      </div>
      <h1 style="font-size:2.4rem;font-weight:800;letter-spacing:-0.5px;margin-bottom:0.75rem;">
        Smart Electronics <span class="gradient-text">Diagnosis</span>
      </h1>
      <p style="color:var(--text-muted);font-size:1.05rem;max-width:680px;margin:0 auto;line-height:1.6;">
        Troubleshoot electronic appliances with device-specific symptom analysis. Receive probable fault causes, safe preliminary DIY recommendations, and dispatch a certified specialist.
      </p>
    </div>

    <!-- Stepper Navigation -->
    <div class="glass-card" style="margin-bottom:2.5rem;padding:1rem 1.5rem;">
      <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div class="step-indicator active" id="stepIndicator1">
          <span class="step-num">1</span>
          <span class="step-title">Select Appliance</span>
        </div>
        <div style="color:var(--border-glass);">&rarr;</div>
        <div class="step-indicator" id="stepIndicator2">
          <span class="step-num">2</span>
          <span class="step-title">Symptoms &amp; Media</span>
        </div>
        <div style="color:var(--border-glass);">&rarr;</div>
        <div class="step-indicator" id="stepIndicator3">
          <span class="step-num">3</span>
          <span class="step-title">Troubleshooting Q&amp;A</span>
        </div>
        <div style="color:var(--border-glass);">&rarr;</div>
        <div class="step-indicator" id="stepIndicator4">
          <span class="step-num">4</span>
          <span class="step-title">Analysis &amp; Booking</span>
        </div>
      </div>
    </div>

    <!-- Multi-Step Form Wrapper -->
    <form id="diagnosisForm" onsubmit="return false;" enctype="multipart/form-data">

      <!-- STEP 1: APPLIANCE SELECTION -->
      <section id="step1" class="diag-step-section active">
        <div class="glass-card">
          <h3 style="font-size:1.3rem;margin-bottom:0.5rem;display:flex;align-items:center;gap:0.5rem;">
            <i data-lucide="layers" style="color:var(--primary);"></i> Step 1: Select Your Electronic Appliance
          </h3>
          <p style="color:var(--text-dim);font-size:0.9rem;margin-bottom:2rem;">
            Choose the equipment category experiencing malfunction to load targeted diagnostic logic:
          </p>

          <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:1.25rem;">
            <?php foreach ($kb as $app_name => $app_data): ?>
              <div class="appliance-choice-card" onclick="selectAppliance('<?= e($app_name) ?>')">
                <div class="choice-icon">
                  <i data-lucide="<?= e($app_data['icon']) ?>"></i>
                </div>
                <h4 style="font-size:1.05rem;font-weight:700;margin-bottom:0.35rem;"><?= e($app_name) ?></h4>
                <p style="font-size:0.8rem;color:var(--text-dim);line-height:1.4;">
                  <?= count($app_data['categories']) ?> specialized problem workflows
                </p>
                <div class="select-badge"><i data-lucide="check"></i></div>
              </div>
            <?php endforeach; ?>
          </div>

          <input type="hidden" id="selected_appliance" name="appliance" value="">
        </div>
      </section>

      <!-- STEP 2: DETAILS, SYMPTOMS & MEDIA -->
      <section id="step2" class="diag-step-section" style="display:none;">
        <div class="glass-card">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
            <div>
              <span class="badge badge-primary" id="selected_appliance_pill">Appliance</span>
              <h3 style="font-size:1.3rem;margin-top:0.4rem;display:flex;align-items:center;gap:0.5rem;">
                <i data-lucide="clipboard-list" style="color:var(--primary);"></i> Step 2: Device Details &amp; Observed Symptoms
              </h3>
            </div>
            <button type="button" onclick="goToStep(1)" class="btn btn-outline btn-sm">
              <i data-lucide="arrow-left"></i> Change Appliance
            </button>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
            <div class="form-group">
              <label class="form-label">Brand Name *</label>
              <select id="brand_select" class="form-select" onchange="handleBrandChange(this.value)">
                <option value="">-- Select Brand --</option>
              </select>
              <input type="text" id="custom_brand_input" class="form-control" style="display:none;margin-top:0.5rem;" placeholder="Enter custom brand name...">
            </div>

            <div class="form-group">
              <label class="form-label">Model Number (Optional, if known)</label>
              <input type="text" id="model_input" class="form-control" placeholder="e.g. 55X90J, AR18CY3, GL-T302...">
              <small style="color:var(--text-dim);font-size:0.75rem;">Located on the back sticker or warranty card.</small>
            </div>
          </div>

          <div class="form-group" style="margin-bottom:1.5rem;">
            <label class="form-label">Primary Problem Category *</label>
            <select id="category_select" class="form-select" onchange="loadCategorySymptoms(this.value)">
              <option value="">-- Select Primary Failure Category --</option>
            </select>
          </div>

          <!-- Dynamic Symptoms Checkboxes -->
          <div class="form-group" id="symptoms_wrapper" style="display:none;margin-bottom:1.5rem;">
            <label class="form-label">Observed Symptoms (Check all that apply):</label>
            <div id="symptoms_checkboxes" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(240px, 1fr));gap:0.75rem;padding:1rem;background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:var(--radius-sm);">
              <!-- Injected via JS -->
            </div>
          </div>

          <div class="form-group" style="margin-bottom:1.5rem;">
            <label class="form-label">Detailed Problem Description *</label>
            <textarea id="description_input" class="form-control" style="min-height:95px;" placeholder="Describe what happens when you operate the device, any strange smells, sounds, or visual anomalies..."></textarea>
          </div>

          <!-- Photos or Videos Upload -->
          <div class="form-group" style="margin-bottom:2rem;">
            <label class="form-label">Upload Fault Photo or Video (Optional):</label>
            <div class="upload-dropzone" onclick="document.getElementById('media_file').click()">
              <input type="file" id="media_file" name="media" accept="image/*,video/*,.pdf" style="display:none;" onchange="handleFilePreview(this)">
              <i data-lucide="upload-cloud" style="width:36px;height:36px;color:var(--primary);margin-bottom:0.5rem;"></i>
              <div style="font-weight:600;font-size:0.95rem;" id="uploadPromptText">Click or Drag &amp; Drop Photo / Short Video</div>
              <p style="font-size:0.78rem;color:var(--text-dim);margin-top:0.25rem;">Supported formats: JPG, PNG, WEBP, MP4, MOV (Max 15MB)</p>
            </div>
          </div>

          <div style="display:flex;justify-content:space-between;align-items:center;">
            <button type="button" onclick="goToStep(1)" class="btn btn-outline">Back</button>
            <button type="button" onclick="validateStep2AndProceed()" class="btn btn-primary">
              Continue to Troubleshooting Questions &rarr;
            </button>
          </div>
        </div>
      </section>

      <!-- STEP 3: TROUBLESHOOTING QUESTIONS -->
      <section id="step3" class="diag-step-section" style="display:none;">
        <div class="glass-card">
          <div style="margin-bottom:1.5rem;">
            <span class="badge badge-primary">Dynamic Diagnostic Matrix</span>
            <h3 style="font-size:1.3rem;margin-top:0.4rem;display:flex;align-items:center;gap:0.5rem;">
              <i data-lucide="help-circle" style="color:var(--primary);"></i> Step 3: Appliance-Specific Diagnostic Questions
            </h3>
            <p style="color:var(--text-dim);font-size:0.88rem;margin-top:0.25rem;">
              Answer these focused troubleshooting questions to isolate component failure probabilities:
            </p>
          </div>

          <div id="questions_container" style="display:flex;flex-direction:column;gap:1.5rem;margin-bottom:2rem;">
            <!-- Injected via JS -->
          </div>

          <div style="display:flex;justify-content:space-between;align-items:center;">
            <button type="button" onclick="goToStep(2)" class="btn btn-outline">Back</button>
            <button type="button" onclick="runDiagnosisEngine()" id="runEngineBtn" class="btn btn-primary">
              <i data-lucide="sparkles"></i> Generate Smart Diagnosis Report &rarr;
            </button>
          </div>
        </div>
      </section>

      <!-- STEP 4: DIAGNOSIS REPORT & BOOKING -->
      <section id="step4" class="diag-step-section" style="display:none;">
        <!-- Injected via JS with Full Analysis -->
        <div id="report_display_area"></div>
      </section>

    </form>

  </div>
</div>

<!-- Modal: Direct Dispatch / Booking with Diagnosis -->
<div class="modal-backdrop" id="bookingModal">
  <div class="modal-dialog" style="max-width:640px;">
    <div class="modal-header">
      <h3 style="font-size:1.15rem;display:flex;align-items:center;gap:0.5rem;">
        <i data-lucide="calendar" style="color:var(--primary);"></i> Book Engineer with Diagnosis Dossier
      </h3>
      <button class="modal-close" data-modal-close>&times;</button>
    </div>
    <div class="modal-body">
      <p style="font-size:0.88rem;color:var(--text-muted);margin-bottom:1.25rem;">
        Your complete diagnosis report, answered questions, and photos will be linked directly to your service booking.
      </p>

      <div class="form-group">
        <label class="form-label">Select Certified Electronics Specialist *</label>
        <select id="modal_engineer_id" class="form-select" required>
          <?php foreach ($engineers as $eng): ?>
            <option value="<?= $eng['id'] ?>" data-rate="<?= $eng['hourly_rate'] ?>">
              <?= e($eng['full_name']) ?> — <?= e($eng['title']) ?> (<?= format_currency($eng['hourly_rate']) ?>/hr, <?= $eng['rating'] ?>★) &bull; <?= e($eng['location']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;">
        <div class="form-group">
          <label class="form-label">Preferred Date *</label>
          <input type="date" id="modal_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" min="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Preferred Time Slot *</label>
          <select id="modal_time" class="form-select">
            <option value="09:00 AM - 12:00 PM">09:00 AM - 12:00 PM (Morning)</option>
            <option value="12:00 PM - 03:00 PM">12:00 PM - 03:00 PM (Afternoon)</option>
            <option value="03:00 PM - 06:00 PM">03:00 PM - 06:00 PM (Evening)</option>
          </select>
        </div>
      </div>

      <div class="form-group" style="margin-bottom:1rem;">
        <label class="form-label">Service Street Address *</label>
        <input type="text" id="modal_address" class="form-control" value="<?= e($default_address) ?>" placeholder="Flat/House No, Building, Street..." required>
      </div>

      <div class="form-group">
        <label class="form-label">City *</label>
        <input type="text" id="modal_city" class="form-control" value="<?= e($default_city ?: 'Bangalore') ?>" placeholder="City..." required>
      </div>

      <div style="padding:0.75rem;background:rgba(0,240,255,0.06);border:1px solid rgba(0,240,255,0.2);border-radius:6px;font-size:0.8rem;margin-top:1rem;">
        <i data-lucide="shield-check" style="width:14px;height:14px;color:var(--primary);vertical-align:middle;"></i>
        Standard 20% advance escrow applies once the engineer accepts your request. Full diagnosis findings are automatically attached.
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
      <button type="button" onclick="submitBookingWithDiagnosis()" id="modalSubmitBtn" class="btn btn-primary btn-sm">
        Confirm &amp; Dispatch Request
      </button>
    </div>
  </div>
</div>

<style>
.step-indicator {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  color: var(--text-dim);
  font-size: 0.88rem;
  font-weight: 600;
  transition: all 0.2s ease;
}
.step-indicator.active {
  color: var(--primary);
}
.step-num {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: rgba(255,255,255,0.05);
  border: 1px solid var(--border-glass);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
}
.step-indicator.active .step-num {
  background: var(--primary);
  color: #000;
  border-color: var(--primary);
}

.appliance-choice-card {
  padding: 1.5rem;
  background: rgba(255,255,255,0.02);
  border: 1px solid var(--border-glass);
  border-radius: var(--radius-sm);
  cursor: pointer;
  position: relative;
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
  text-align: left;
}
.appliance-choice-card:hover {
  transform: translateY(-4px);
  border-color: var(--primary);
  box-shadow: 0 10px 25px -5px rgba(0,240,255,0.15);
  background: rgba(0,240,255,0.04);
}
.appliance-choice-card.selected {
  border-color: var(--primary);
  background: rgba(0,240,255,0.08);
  box-shadow: 0 0 20px rgba(0,240,255,0.2);
}
.choice-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: rgba(0,240,255,0.1);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
}
.select-badge {
  position: absolute;
  top: 1rem;
  right: 1rem;
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--primary);
  color: #000;
  display: none;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
}
.appliance-choice-card.selected .select-badge {
  display: flex;
}

.upload-dropzone {
  border: 2px dashed var(--border-glass);
  border-radius: var(--radius-sm);
  padding: 2rem 1.5rem;
  text-align: center;
  cursor: pointer;
  background: rgba(255,255,255,0.01);
  transition: all 0.2s ease;
}
.upload-dropzone:hover {
  border-color: var(--primary);
  background: rgba(0,240,255,0.03);
}

.question-card {
  padding: 1.25rem;
  background: rgba(255,255,255,0.02);
  border: 1px solid var(--border-glass);
  border-radius: var(--radius-sm);
}
.question-options-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 0.75rem;
  margin-top: 0.85rem;
}
.option-label-btn {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.75rem 1rem;
  background: rgba(255,255,255,0.03);
  border: 1px solid var(--border-glass);
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.85rem;
  color: var(--text-main);
  transition: all 0.2s ease;
}
.option-label-btn:hover {
  border-color: var(--primary);
  background: rgba(0,240,255,0.05);
}
.option-label-btn input[type="radio"] {
  accent-color: var(--primary);
}
.option-label-btn:has(input[type="radio"]:checked) {
  border-color: var(--primary);
  background: rgba(0,240,255,0.1);
  color: #fff;
}
</style>

<script>
const KnowledgeBase = <?= json_encode($kb) ?>;
let currentStep = 1;
let currentAppliance = '';
let currentCategory = '';
let generatedDiagnosisId = null;

function selectAppliance(name) {
  currentAppliance = name;
  document.getElementById('selected_appliance').value = name;
  document.getElementById('selected_appliance_pill').textContent = name;

  // Highlight card
  document.querySelectorAll('.appliance-choice-card').forEach(card => card.classList.remove('selected'));
  event.currentTarget.classList.add('selected');

  // Populate Brands
  const brandSelect = document.getElementById('brand_select');
  brandSelect.innerHTML = '<option value="">-- Select Brand --</option>';
  if (KnowledgeBase[name] && KnowledgeBase[name].popular_brands) {
    KnowledgeBase[name].popular_brands.forEach(b => {
      const opt = document.createElement('option');
      opt.value = b;
      opt.textContent = b;
      brandSelect.appendChild(opt);
    });
  }

  // Populate Categories
  const catSelect = document.getElementById('category_select');
  catSelect.innerHTML = '<option value="">-- Select Primary Failure Category --</option>';
  if (KnowledgeBase[name] && KnowledgeBase[name].categories) {
    Object.keys(KnowledgeBase[name].categories).forEach(c => {
      const opt = document.createElement('option');
      opt.value = c;
      opt.textContent = c;
      catSelect.appendChild(opt);
    });
  }

  goToStep(2);
}

function handleBrandChange(val) {
  const customInput = document.getElementById('custom_brand_input');
  if (val === 'Other') {
    customInput.style.display = 'block';
    customInput.required = true;
  } else {
    customInput.style.display = 'none';
    customInput.required = false;
  }
}

function loadCategorySymptoms(cat) {
  currentCategory = cat;
  const wrapper = document.getElementById('symptoms_wrapper');
  const container = document.getElementById('symptoms_checkboxes');
  container.innerHTML = '';

  if (currentAppliance && cat && KnowledgeBase[currentAppliance]?.categories[cat]) {
    const symptoms = KnowledgeBase[currentAppliance].categories[cat].symptoms || [];
    if (symptoms.length > 0) {
      symptoms.forEach(sym => {
        const lbl = document.createElement('label');
        lbl.style.display = 'flex';
        lbl.style.alignItems = 'center';
        lbl.style.gap = '0.5rem';
        lbl.style.fontSize = '0.85rem';
        lbl.style.cursor = 'pointer';
        lbl.innerHTML = `<input type="checkbox" name="symptoms[]" value="${sym}" style="accent-color:var(--primary);"> <span>${sym}</span>`;
        container.appendChild(lbl);
      });
      wrapper.style.display = 'block';
    } else {
      wrapper.style.display = 'none';
    }
  } else {
    wrapper.style.display = 'none';
  }
}

function handleFilePreview(input) {
  const prompt = document.getElementById('uploadPromptText');
  if (input.files && input.files[0]) {
    prompt.innerHTML = `<i data-lucide="check-circle" style="color:var(--success);vertical-align:middle;"></i> Selected: <strong>${input.files[0].name}</strong> (${(input.files[0].size/1024/1024).toFixed(2)} MB)`;
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }
}

function goToStep(step) {
  currentStep = step;
  document.querySelectorAll('.diag-step-section').forEach(sec => sec.style.display = 'none');
  const activeSec = document.getElementById('step' + step);
  if (activeSec) activeSec.style.display = 'block';

  // Update indicators
  for (let i = 1; i <= 4; i++) {
    const ind = document.getElementById('stepIndicator' + i);
    if (ind) {
      if (i <= step) ind.classList.add('active');
      else ind.classList.remove('active');
    }
  }
  window.scrollTo({ top: 120, behavior: 'smooth' });
  if (typeof lucide !== 'undefined') lucide.createIcons();
}

function validateStep2AndProceed() {
  let brand = document.getElementById('brand_select').value;
  if (brand === 'Other') {
    brand = document.getElementById('custom_brand_input').value.trim();
  }
  const category = document.getElementById('category_select').value;
  const description = document.getElementById('description_input').value.trim();

  if (!brand) {
    alert('Please select or specify a brand.');
    return;
  }
  if (!category) {
    alert('Please select a problem category.');
    return;
  }
  if (!description) {
    alert('Please provide a brief description of the issue.');
    return;
  }

  // Load Questions into Step 3
  loadQuestions(currentAppliance, category);
  goToStep(3);
}

function loadQuestions(appliance, category) {
  const container = document.getElementById('questions_container');
  container.innerHTML = '';

  const catData = KnowledgeBase[appliance]?.categories[category];
  const questions = catData?.questions || [];

  if (questions.length === 0) {
    container.innerHTML = `
      <div style="text-align:center;padding:2rem;color:var(--text-dim);">
        <i data-lucide="info" style="width:28px;height:28px;margin-bottom:0.5rem;color:var(--primary);"></i>
        <p>No extra troubleshooting questions needed for this issue. You can proceed directly to generate the diagnosis.</p>
      </div>
    `;
  } else {
    questions.forEach((q, idx) => {
      let optHtml = '';
      for (const [label, val] of Object.entries(q.options)) {
        optHtml += `
          <label class="option-label-btn">
            <input type="radio" name="q_${q.id}" value="${val}" required>
            <span>${label}</span>
          </label>
        `;
      }

      container.innerHTML += `
        <div class="question-card">
          <div style="display:flex;align-items:center;gap:0.5rem;">
            <span class="badge badge-secondary" style="font-size:0.75rem;">Q${idx + 1}</span>
            <div style="font-weight:700;font-size:0.95rem;">${q.label}</div>
          </div>
          <div class="question-options-grid">
            ${optHtml}
          </div>
        </div>
      `;
    });
  }
  if (typeof lucide !== 'undefined') lucide.createIcons();
}

async function runDiagnosisEngine() {
  const btn = document.getElementById('runEngineBtn');
  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader" class="spin"></i> Analyzing Hardware Rules...';
  if (typeof lucide !== 'undefined') lucide.createIcons();

  let brand = document.getElementById('brand_select').value;
  if (brand === 'Other') {
    brand = document.getElementById('custom_brand_input').value.trim();
  }
  const model = document.getElementById('model_input').value.trim();
  const category = document.getElementById('category_select').value;
  const description = document.getElementById('description_input').value.trim();

  // Collect checked symptoms
  const symptoms = [];
  document.querySelectorAll('input[name="symptoms[]"]:checked').forEach(cb => symptoms.push(cb.value));

  // Collect answers
  const answers = {};
  const catData = KnowledgeBase[currentAppliance]?.categories[category];
  const questions = catData?.questions || [];
  questions.forEach(q => {
    const selected = document.querySelector(`input[name="q_${q.id}"]:checked`);
    if (selected) answers[q.id] = selected.value;
  });

  // Prepare FormData for file & JSON
  const formData = new FormData();
  formData.append('appliance', currentAppliance);
  formData.append('brand', brand);
  formData.append('model', model);
  formData.append('category', category);
  formData.append('description', description);
  formData.append('symptoms', JSON.stringify(symptoms));
  formData.append('answers', JSON.stringify(answers));

  const fileInput = document.getElementById('media_file');
  if (fileInput.files && fileInput.files[0]) {
    formData.append('media', fileInput.files[0]);
  }

  try {
    const response = await fetch('api/diagnosis.php?action=diagnose', {
      method: 'POST',
      body: formData
    });
    const res = await response.json();

    if (res.status === 'success') {
      generatedDiagnosisId = res.data.diagnosis_id;
      renderDiagnosisReport(res.data);
      goToStep(4);
    } else {
      alert(res.data.message || 'Diagnosis failed.');
    }
  } catch (err) {
    console.error(err);
    alert('Communication error while running diagnosis.');
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="sparkles"></i> Generate Smart Diagnosis Report &rarr;';
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }
}

function renderDiagnosisReport(data) {
  const r = data.report;
  const area = document.getElementById('report_display_area');

  let causesHtml = '';
  if (r.probable_causes && r.probable_causes.length > 0) {
    r.probable_causes.forEach(c => {
      const probNum = parseInt(c.probability) || 50;
      let probColor = 'var(--primary)';
      if (probNum >= 80) probColor = 'var(--danger)';
      else if (probNum >= 60) probColor = 'var(--warning)';

      causesHtml += `
        <div style="padding:1rem;background:rgba(255,255,255,0.02);border:1px solid var(--border-glass);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
          <div>
            <div style="font-weight:700;font-size:0.95rem;color:var(--text-main);">${c.cause}</div>
            <div style="font-size:0.75rem;color:var(--text-dim);margin-top:0.2rem;">Physical electronic component level finding</div>
          </div>
          <div style="text-align:right;">
            <span class="mono" style="font-size:1.15rem;font-weight:800;color:${probColor};">${c.probability}</span>
            <div style="font-size:0.7rem;color:var(--text-dim);text-transform:uppercase;">Likelihood</div>
          </div>
        </div>
      `;
    });
  }

  let diyHtml = '';
  if (r.safe_troubleshooting && r.safe_troubleshooting.length > 0) {
    r.safe_troubleshooting.forEach(tip => {
      diyHtml += `
        <li style="margin-bottom:0.6rem;font-size:0.9rem;color:var(--text-main);line-height:1.5;">
          <i data-lucide="check" style="width:14px;height:14px;color:var(--success);vertical-align:middle;margin-right:0.35rem;"></i>
          ${tip}
        </li>
      `;
    });
  }

  let warningsHtml = '';
  if (r.safety_warnings && r.safety_warnings.length > 0) {
    r.safety_warnings.forEach(w => {
      warningsHtml += `
        <div style="padding:0.75rem 1rem;background:rgba(239,68,68,0.1);border-left:3px solid var(--danger);border-radius:4px;font-size:0.85rem;color:#fca5a5;line-height:1.5;margin-bottom:0.5rem;">
          ${w}
        </div>
      `;
    });
  }

  area.innerHTML = `
    <div class="glass-card" style="margin-bottom:2rem;border-color:var(--primary);">
      <!-- Report Header -->
      <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem;margin-bottom:1.5rem;padding-bottom:1.25rem;border-bottom:1px solid var(--border-glass);">
        <div>
          <div style="display:flex;align-items:center;gap:0.5rem;">
            <span class="badge badge-primary mono">DIAG-REPORT #${data.diagnosis_id}</span>
            <span class="badge badge-secondary">${data.appliance}</span>
            <span class="badge badge-accent">${data.brand} ${data.model || ''}</span>
          </div>
          <h2 style="font-size:1.6rem;font-weight:800;margin-top:0.6rem;">${r.summary_headline || (data.category + ' Failure Analysis')}</h2>
          <p style="font-size:0.85rem;color:var(--text-dim);margin-top:0.25rem;">
            Rule-Based Expert Diagnostic Dossier &bull; Generated on ${new Date().toLocaleDateString('en-GB', {day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'})}
          </p>
        </div>

        <div style="text-align:right;">
          <div style="font-size:0.75rem;color:var(--text-dim);text-transform:uppercase;">Recommended Specialist</div>
          <div style="font-size:1rem;font-weight:700;color:var(--primary);">${r.recommended_specialist}</div>
          <div style="font-size:0.8rem;color:var(--text-dim);margin-top:0.2rem;">Est. Time: ${r.estimated_labor_hours}</div>
        </div>
      </div>

      <!-- Probable Causes -->
      <div style="margin-bottom:2rem;">
        <h4 style="font-size:1.1rem;margin-bottom:0.75rem;display:flex;align-items:center;gap:0.5rem;">
          <i data-lucide="target" style="color:var(--primary);"></i> Algorithmic Probable Component Faults:
        </h4>
        <div style="display:flex;flex-direction:column;gap:0.6rem;">
          ${causesHtml}
        </div>
      </div>

      <!-- Safe Preliminary DIY Checks -->
      <div style="margin-bottom:2rem;padding:1.25rem;background:rgba(16,185,129,0.04);border:1px solid rgba(16,185,129,0.2);border-radius:var(--radius-sm);">
        <h4 style="font-size:1.05rem;color:var(--success);margin-bottom:0.75rem;display:flex;align-items:center;gap:0.5rem;">
          <i data-lucide="shield-check"></i> Safe Preliminary DIY Checks (Non-Invasive):
        </h4>
        <ul style="list-style:none;padding:0;margin:0;">
          ${diyHtml}
        </ul>
      </div>

      <!-- Safety Warnings & Mandatory Disclaimer -->
      <div style="margin-bottom:2rem;">
        <h4 style="font-size:1.05rem;color:var(--danger);margin-bottom:0.75rem;display:flex;align-items:center;gap:0.5rem;">
          <i data-lucide="alert-triangle"></i> Mandatory Safety Directives &amp; Advisory:
        </h4>
        ${warningsHtml}
      </div>

      <!-- Action Button: Book Verified Engineer -->
      <div style="padding:1.5rem;background:rgba(0,240,255,0.06);border:1px solid rgba(0,240,255,0.25);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;">
        <div>
          <h4 style="font-size:1.15rem;margin-bottom:0.25rem;">Ready to Resolve this Fault?</h4>
          <p style="font-size:0.88rem;color:var(--text-muted);">
            Book a certified electronics engineer. Your complete diagnosis report and photos will be linked directly to your appointment.
          </p>
        </div>
        <div style="display:flex;gap:0.75rem;">
          <button type="button" onclick="goToStep(1)" class="btn btn-outline">Start New Diagnosis</button>
          <button type="button" onclick="openBookingModal()" class="btn btn-primary">
            <i data-lucide="wrench"></i> Book Engineer with Diagnosis &rarr;
          </button>
        </div>
      </div>
    </div>
  `;
  if (typeof lucide !== 'undefined') lucide.createIcons();
}

function openBookingModal() {
  document.getElementById('bookingModal').classList.add('show');
}

async function submitBookingWithDiagnosis() {
  if (!generatedDiagnosisId) {
    alert('Please complete the diagnosis first.');
    return;
  }

  const engId = document.getElementById('modal_engineer_id').value;
  const date = document.getElementById('modal_date').value;
  const time = document.getElementById('modal_time').value;
  const address = document.getElementById('modal_address').value.trim();
  const city = document.getElementById('modal_city').value.trim();
  const btn = document.getElementById('modalSubmitBtn');

  if (!address || !city) {
    alert('Please enter your service address and city.');
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Dispatching Request...';

  try {
    const res = await fetch('api/diagnosis.php?action=submit_request_with_diagnosis', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        diagnosis_id: generatedDiagnosisId,
        engineer_id: engId,
        address: address,
        city: city,
        preferred_date: date,
        preferred_time: time
      })
    });
    const data = await res.json();

    if (data.status === 'success') {
      window.showToast?.(data.data.message || 'Service request submitted successfully!', 'success');
      alert(data.data.message);
      window.location.href = data.data.redirect || 'customer/requests.php';
    } else {
      btn.disabled = false;
      btn.textContent = 'Confirm & Dispatch Request';
      alert(data.data.message || 'Submission failed.');
    }
  } catch (err) {
    console.error(err);
    btn.disabled = false;
    btn.textContent = 'Confirm & Dispatch Request';
    alert('Failed to connect to the server.');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  if (typeof lucide !== 'undefined') lucide.createIcons();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
