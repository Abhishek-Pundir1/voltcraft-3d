<?php
// ============================================================================
// VoltCraft 3D - Automated Test Suite: Feature 1 - Smart Electronics Diagnosis
// ============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/diagnosis-engine.php';

echo "========================================================\n";
echo " RUNNING TEST SUITE: SMART ELECTRONICS DIAGNOSIS ENGINE\n";
echo "========================================================\n\n";

$pdo = get_db_connection();
$tests_passed = 0;
$tests_failed = 0;

function assert_test($description, $condition) {
    global $tests_passed, $tests_failed;
    if ($condition) {
        echo " [PASS] $description\n";
        $tests_passed++;
    } else {
        echo " [FAIL] $description\n";
        $tests_failed++;
    }
}

// ----------------------------------------------------------------------------
// TEST GROUP 1: Database Schema Verification
// ----------------------------------------------------------------------------
echo "\n--- 1. Database Schema Verification ---\n";
ensure_diagnosis_schema();

// Check appliance_diagnoses table
$tbl_stmt = $pdo->query("SHOW TABLES LIKE 'appliance_diagnoses'");
assert_test("appliance_diagnoses table exists", $tbl_stmt->rowCount() > 0);

// Check columns in appliance_diagnoses
$cols_stmt = $pdo->query("SHOW COLUMNS FROM appliance_diagnoses");
$cols = $cols_stmt->fetchAll(PDO::FETCH_COLUMN);
$expected_cols = [
    'id', 'customer_id', 'customer_name', 'customer_phone', 'appliance_type',
    'brand', 'model', 'problem_category', 'symptoms', 'problem_description',
    'troubleshooting_answers', 'possible_causes', 'safety_recommendations',
    'media_attachment', 'service_request_id', 'booking_id', 'created_at'
];
foreach ($expected_cols as $c) {
    assert_test("Column '$c' exists in appliance_diagnoses", in_array($c, $cols));
}

// Check diagnosis_id in service_requests
$sr_cols = $pdo->query("SHOW COLUMNS FROM service_requests")->fetchAll(PDO::FETCH_COLUMN);
assert_test("Column 'diagnosis_id' exists in service_requests", in_array('diagnosis_id', $sr_cols));

// Check diagnosis_id in bookings
$bk_cols = $pdo->query("SHOW COLUMNS FROM bookings")->fetchAll(PDO::FETCH_COLUMN);
assert_test("Column 'diagnosis_id' exists in bookings", in_array('diagnosis_id', $bk_cols));

// ----------------------------------------------------------------------------
// TEST GROUP 2: Knowledge Base Verification (All 7 Appliances)
// ----------------------------------------------------------------------------
echo "\n--- 2. Knowledge Base Verification ---\n";
$kb = get_diagnosis_knowledge_base();

$required_appliances = [
    'Television',
    'Air Conditioner',
    'Refrigerator',
    'Washing Machine',
    'Microwave',
    'Inverter and UPS',
    'Other electronic devices'
];

foreach ($required_appliances as $app) {
    assert_test("Appliance '$app' exists in Knowledge Base", isset($kb[$app]));
    if (isset($kb[$app])) {
        assert_test("'$app' has configured categories", count($kb[$app]['categories']) > 0);
        assert_test("'$app' has popular brands list", count($kb[$app]['popular_brands']) > 0);
    }
}

// Check specific TV No Power questions as required in specification
$tv_np_questions = $kb['Television']['categories']['No Power / Won\'t Turn On']['questions'] ?? [];
$tv_q_ids = array_column($tv_np_questions, 'id');
assert_test("TV contains 'led_state' question (power indicator light)", in_array('led_state', $tv_q_ids));
assert_test("TV contains 'sound_present' question (hear any sound)", in_array('sound_present', $tv_q_ids));
assert_test("TV contains 'tested_outlet' question (tried another outlet)", in_array('tested_outlet', $tv_q_ids));
assert_test("TV contains 'sudden_stop' question (suddenly stop working)", in_array('sudden_stop', $tv_q_ids));

// ----------------------------------------------------------------------------
// TEST GROUP 3: Rule-Based Diagnosis Engine Logic (Multiple Appliances & Answers)
// ----------------------------------------------------------------------------
echo "\n--- 3. Rule-Based Diagnosis Engine Logic ---\n";

// Case 3A: TV Blinking LED (SMPS fault)
$res_tv1 = run_rule_based_diagnosis('Television', 'No Power / Won\'t Turn On', [
    'led_state' => 'led_blinking',
    'sound_present' => 'no_sound',
    'tested_outlet' => 'tested_outlet_yes'
]);
assert_test("TV Blinking LED identifies SMPS / Protection circuit fault", 
    !empty($res_tv1['probable_causes']) && strpos($res_tv1['probable_causes'][0]['cause'], 'SMPS') !== false);
assert_test("TV includes safe cold power drain DIY tip", 
    !empty($res_tv1['safe_troubleshooting']) && strpos(json_encode($res_tv1['safe_troubleshooting']), 'Cold Power Drain') !== false);

// Case 3B: TV Flashlight Test Positive (Backlight Inverter Fault)
$res_tv2 = run_rule_based_diagnosis('Television', 'Black Screen / No Display (Sound Works)', [
    'flashlight_test' => 'flashlight_positive'
]);
assert_test("TV Flashlight positive identifies LED Backlight Strip failure (95%)", 
    !empty($res_tv2['probable_causes']) && strpos($res_tv2['probable_causes'][0]['cause'], 'LED Backlight Strip') !== false && $res_tv2['probable_causes'][0]['probability'] === '95%');

// Case 3C: AC Ice on Evaporator Coil (Refrigerant Leak)
$res_ac1 = run_rule_based_diagnosis('Air Conditioner', 'Not Cooling / Blowing Warm Air', [
    'ice_formation' => 'ice_present',
    'filter_clean' => 'filters_clean'
]);
assert_test("AC Ice on coil detects Refrigerant (Freon) Leak (90%)", 
    !empty($res_ac1['probable_causes']) && strpos($res_ac1['probable_causes'][0]['cause'], 'Refrigerant') !== false);

// Case 3D: Refrigerator Heavy Frost (Defrost Heating Element Fault)
$res_fridge1 = run_rule_based_diagnosis('Refrigerator', 'Freezer Cold but Fridge Warm', [
    'frost_buildup' => 'freezer_heavy_frost',
    'airflow_vent' => 'no_lower_airflow'
]);
assert_test("Fridge heavy frost detects Defrost Heating Element / Bi-metal Thermostat (90%)", 
    !empty($res_fridge1['probable_causes']) && strpos($res_fridge1['probable_causes'][0]['cause'], 'Defrost Heating Element') !== false);

// Case 3E: Microwave Sparks with Burnt Waveguide Cover
$res_micro1 = run_rule_based_diagnosis('Microwave', 'Sparks or Arcing Inside Cavity', [
    'waveguide_cover' => 'mica_burned'
]);
assert_test("Microwave sparks with scorched mica identifies Waveguide Cover Plate (95%)", 
    !empty($res_micro1['probable_causes']) && strpos($res_micro1['probable_causes'][0]['cause'], 'Mica Waveguide Cover') !== false);

// Case 3F: Inverter Continuous Overload with Zero Load
$res_inv1 = run_rule_based_diagnosis('Inverter and UPS', 'Continuous Overload Alarm Beep', [
    'load_disconnected' => 'overload_zero_load'
]);
assert_test("Inverter overload at zero load identifies Shorted MOSFET H-Bridge (90%)", 
    !empty($res_inv1['probable_causes']) && strpos($res_inv1['probable_causes'][0]['cause'], 'MOSFET') !== false);

// ----------------------------------------------------------------------------
// TEST GROUP 4: Strict Safety Directives & Disclaimer Verification
// ----------------------------------------------------------------------------
echo "\n--- 4. Strict Safety Directives & Disclaimer Verification ---\n";
assert_test("Safety warnings forbid casing disassembly and opening electrical appliances", 
    strpos(json_encode($res_tv1['safety_warnings']), 'NO circumstances should you open electrical casings') !== false);
assert_test("Preliminary advisory clarifies certified engineer must confirm physical fault", 
    strpos(json_encode($res_tv1['safety_warnings']), 'preliminary based on reported behavioral symptoms') !== false);

// Check microwave high-voltage capacitor warning specifically
assert_test("Microwave diagnostics contains high-voltage lethal charge alert", 
    strpos(json_encode($res_micro1['safety_warnings']), 'high-voltage capacitor') !== false);

// ----------------------------------------------------------------------------
// TEST GROUP 5: Saving Diagnosis Record & Linking to Customer Request
// ----------------------------------------------------------------------------
echo "\n--- 5. Database Insertion & Request Linking ---\n";

// Get sample customer and engineer
$cust = $pdo->query("SELECT c.id, u.full_name, u.phone FROM customers c JOIN users u ON c.user_id = u.id LIMIT 1")->fetch();
$eng = $pdo->query("SELECT e.id, e.user_id, u.full_name FROM engineers e JOIN users u ON e.user_id = u.id LIMIT 1")->fetch();
$srv = $pdo->query("SELECT id, name, base_price FROM services LIMIT 1")->fetch();

assert_test("Customer and Engineer profiles exist in database", !empty($cust) && !empty($eng));

// Create a simulated diagnosis record
$diag_id = save_appliance_diagnosis([
    'customer_id' => $cust['id'],
    'customer_name' => $cust['full_name'],
    'customer_phone' => $cust['phone'],
    'appliance_type' => 'Television',
    'brand' => 'Sony',
    'model' => 'Bravia 55X90J',
    'problem_category' => 'No Power / Won\'t Turn On',
    'symptoms' => ['Completely dead', 'No standby LED', 'LED blinks continuously'],
    'problem_description' => 'TV refused to start after thunder shower yesterday evening.',
    'troubleshooting_answers' => [
        'led_state' => 'led_blinking',
        'sound_present' => 'no_sound',
        'tested_outlet' => 'tested_outlet_yes',
        'sudden_stop' => 'surge_event_yes'
    ],
    'possible_causes' => $res_tv1['probable_causes'],
    'safety_recommendations' => array_merge($res_tv1['safe_troubleshooting'], $res_tv1['safety_warnings']),
    'media_attachment' => 'assets/uploads/test_tv_sample.jpg'
]);

assert_test("Diagnosis record successfully inserted (ID: $diag_id)", $diag_id > 0);

// Verify inserted record can be read back
$saved_diag = get_diagnosis_dossier($diag_id);
assert_test("Diagnosis dossier retrieved accurately", 
    $saved_diag && $saved_diag['brand'] === 'Sony' && $saved_diag['appliance_type'] === 'Television');

// Create a linked Service Request
$req_code = 'REQ-TEST-' . rand(1000, 9999);
$ins_sr = $pdo->prepare("INSERT INTO service_requests 
    (request_code, customer_id, engineer_id, service_id, title, description, address, city, preferred_date, preferred_time, attachment, estimated_amount, status, diagnosis_id) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)");
$ins_sr->execute([
    $req_code, $cust['id'], $eng['id'], $srv['id'],
    'Smart Diagnosis: Television (Sony Bravia 55X90J) — No Power',
    "【SMART DIAGNOSIS REPORT】\n• Probable SMPS Failure",
    '123 Circuit Way', 'Tech City', date('Y-m-d', strtotime('+1 day')), '10:00 AM - 01:00 PM',
    'assets/uploads/test_tv_sample.jpg', 2200.00, $diag_id
]);
$req_id = $pdo->lastInsertId();
assert_test("Service request created with linked diagnosis_id (Req ID: $req_id)", $req_id > 0);

// Update appliance_diagnoses with service_request_id
$pdo->prepare("UPDATE appliance_diagnoses SET service_request_id = ? WHERE id = ?")->execute([$req_id, $diag_id]);

// Retrieve dossier via request_id
$dossier_by_req = get_diagnosis_dossier(null, $req_id);
assert_test("Dossier retrievable via service_request_id ($req_id)", 
    $dossier_by_req && (int)$dossier_by_req['id'] === (int)$diag_id);

// ----------------------------------------------------------------------------
// TEST GROUP 6: Booking Conversion & Booking ID Linkage
// ----------------------------------------------------------------------------
echo "\n--- 6. Booking Conversion & Booking ID Linkage ---\n";

$bk_code = 'BK-TEST-' . rand(1000, 9999);
$ins_bk = $pdo->prepare("INSERT INTO bookings 
    (booking_code, request_id, customer_id, engineer_id, service_id, diagnosis_id, total_amount, advance_amount, remaining_amount, payment_status, booking_status, scheduled_at, notes) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ADVANCE_PAID', 'CONFIRMED', ?, ?)");
$ins_bk->execute([
    $bk_code, $req_id, $cust['id'], $eng['id'], $srv['id'], $diag_id,
    2200.00, 440.00, 1760.00, date('Y-m-d H:i:s'), 'Automated Smart Diagnosis Booking Test'
]);
$bk_id = $pdo->lastInsertId();
assert_test("Booking record created with diagnosis_id (Booking ID: $bk_id)", $bk_id > 0);

// Link booking_id in appliance_diagnoses
$pdo->prepare("UPDATE appliance_diagnoses SET booking_id = ? WHERE id = ?")->execute([$bk_id, $diag_id]);

// Retrieve dossier via booking_id
$dossier_by_bk = get_diagnosis_dossier(null, null, $bk_id);
assert_test("Dossier retrievable via booking_id ($bk_id)", 
    $dossier_by_bk && (int)$dossier_by_bk['id'] === (int)$diag_id);

// Clean up test records
$pdo->prepare("DELETE FROM bookings WHERE id = ?")->execute([$bk_id]);
$pdo->prepare("DELETE FROM service_requests WHERE id = ?")->execute([$req_id]);
$pdo->prepare("DELETE FROM appliance_diagnoses WHERE id = ?")->execute([$diag_id]);
echo "\n Cleaned up test mock records.\n";

// ----------------------------------------------------------------------------
// SUMMARY REPORT
// ----------------------------------------------------------------------------
echo "\n========================================================\n";
echo " TEST SUMMARY\n";
echo " Passed: $tests_passed\n";
echo " Failed: $tests_failed\n";
echo " Status: " . ($tests_failed === 0 ? "ALL TESTS PASSED SUCCESSFULLY! ✓" : "FAILURES DETECTED!") . "\n";
echo "========================================================\n";
exit($tests_failed === 0 ? 0 : 1);
