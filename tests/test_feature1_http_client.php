<?php
// ============================================================================
// VoltCraft 3D - Automated HTTP API & Web Integration Tests for Feature 1
// ============================================================================

$baseUrl = 'http://127.0.0.1/electronice%20website%203/api/diagnosis.php';
$tests_passed = 0;
$tests_failed = 0;

function run_http_test($title, $condition) {
    global $tests_passed, $tests_failed;
    if ($condition) {
        echo " [PASS] $title\n";
        $tests_passed++;
    } else {
        echo " [FAIL] $title\n";
        $tests_failed++;
    }
}

function http_request($url, $method = 'GET', $data = null, $cookies = '') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($cookies) {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if (is_array($data)) {
            // Check if multipart form data or json
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    curl_close($ch);

    return [
        'code' => $http_code,
        'headers' => $header,
        'body' => $body,
        'json' => json_decode($body, true)
    ];
}

echo "========================================================\n";
echo " RUNNING HTTP ENDPOINT TESTS: SMART ELECTRONICS DIAGNOSIS\n";
echo "========================================================\n\n";

// 1. GET Questions for each appliance
$appliances = [
    'Television', 'Air Conditioner', 'Refrigerator',
    'Washing Machine', 'Microwave', 'Inverter and UPS', 'Other electronic devices'
];

foreach ($appliances as $app) {
    $res = http_request($baseUrl . '?action=get_questions&appliance=' . urlencode($app));
    run_http_test("GET get_questions for '$app' returns 200 OK", $res['code'] === 200 && $res['json']['status'] === 'success');
}

// 2. Incomplete form / invalid inputs
$inv1 = http_request($baseUrl . '?action=diagnose', 'POST', json_encode([
    'brand' => 'Sony'
]));
run_http_test("POST diagnose with missing appliance/category returns HTTP 400", $inv1['code'] === 400);

$inv2 = http_request($baseUrl . '?action=diagnose', 'POST', json_encode([
    'appliance' => 'Television',
    'category' => "No Power / Won't Turn On"
]));
run_http_test("POST diagnose with missing brand returns HTTP 400", $inv2['code'] === 400);

$inv3 = http_request($baseUrl . '?action=get_questions&appliance=' . urlencode('Unknown Appliance 999'));
run_http_test("GET get_questions for invalid appliance returns HTTP 400", $inv3['code'] === 400);

// 3. Complete diagnosis with answers and photo upload simulation
$tmp_img = __DIR__ . '/test_board_sample.png';
if (!file_exists($tmp_img)) {
    // Generate a minimal 1x1 PNG
    file_put_contents($tmp_img, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
}

$cfile = new CURLFile($tmp_img, 'image/png', 'test_board_sample.png');
$post_fields = [
    'appliance' => 'Television',
    'brand' => 'LG',
    'model' => 'OLED55C1',
    'category' => "No Power / Won't Turn On",
    'description' => 'Blown fuse suspected after heavy lightning strike.',
    'symptoms' => json_encode(['Completely dead', 'No standby LED']),
    'answers' => json_encode([
        'led_state' => 'led_off',
        'sound_present' => 'no_sound',
        'tested_outlet' => 'tested_outlet_yes',
        'sudden_stop' => 'surge_event_yes'
    ]),
    'media' => $cfile
];

$diag_res = http_request($baseUrl . '?action=diagnose', 'POST', $post_fields);
run_http_test("POST diagnose with multipart form data & photo returns 200 OK", 
    $diag_res['code'] === 200 && $diag_res['json']['status'] === 'success');

$diag_id = $diag_res['json']['data']['diagnosis_id'] ?? 0;
run_http_test("Diagnosis ID returned (> 0)", $diag_id > 0);
run_http_test("Photo upload saved in assets/uploads/", 
    !empty($diag_res['json']['data']['media_path']) && file_exists(__DIR__ . '/../' . $diag_res['json']['data']['media_path']));

run_http_test("Probable causes returned in response", 
    !empty($diag_res['json']['data']['report']['probable_causes']));
run_http_test("Safe DIY suggestions returned in response", 
    !empty($diag_res['json']['data']['report']['safe_troubleshooting']));
run_http_test("Strict safety warnings present in response", 
    !empty($diag_res['json']['data']['report']['safety_warnings']));

// 4. Dossier retrieval via API
$dossier_res = http_request($baseUrl . '?action=get_dossier&id=' . $diag_id);
run_http_test("GET get_dossier returns valid dossier JSON", 
    $dossier_res['code'] === 200 && $dossier_res['json']['data']['dossier']['brand'] === 'LG');

// 5. Test Customer login and Service Request submission with diagnosis
// Log in as customer
$login_url = 'http://127.0.0.1/electronice%20website%203/api/auth.php?action=login';
$login_ch = curl_init();
curl_setopt($login_ch, CURLOPT_URL, $login_url);
curl_setopt($login_ch, CURLOPT_POST, true);
curl_setopt($login_ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'customer@voltcraft.io',
    'password' => 'Customer@123'
]));
curl_setopt($login_ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($login_ch, CURLOPT_HEADER, true);
curl_setopt($login_ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$l_resp = curl_exec($login_ch);
preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $l_resp, $matches);
$cookie = implode('; ', $matches[1]);
curl_close($login_ch);

run_http_test("Customer login succeeds with active session cookie", !empty($cookie));

// Fetch available engineer
require_once __DIR__ . '/../config/database.php';
$pdo = get_db_connection();
$eng_id = (int)$pdo->query("SELECT id FROM engineers WHERE is_verified = 1 LIMIT 1")->fetchColumn();

// Submit request with diagnosis using the session cookie
$submit_res = http_request($baseUrl . '?action=submit_request_with_diagnosis', 'POST', json_encode([
    'diagnosis_id' => $diag_id,
    'engineer_id' => $eng_id,
    'address' => '42 Quantum Boulevard, Cyber City',
    'city' => 'Bangalore',
    'preferred_date' => date('Y-m-d', strtotime('+2 days')),
    'preferred_time' => '12:00 PM - 03:00 PM'
]), $cookie);

run_http_test("Submit service request with diagnosis returns success", 
    $submit_res['code'] === 200 && $submit_res['json']['status'] === 'success');

$new_req_id = $submit_res['json']['data']['request_id'] ?? 0;
run_http_test("New service request ID generated ($new_req_id)", $new_req_id > 0);

// Verify database state for the created request and diagnosis
$chk = $pdo->prepare("SELECT sr.id, sr.diagnosis_id, ad.service_request_id, sr.attachment 
                      FROM service_requests sr 
                      JOIN appliance_diagnoses ad ON sr.diagnosis_id = ad.id 
                      WHERE sr.id = ?");
$chk->execute([$new_req_id]);
$linked_data = $chk->fetch();

run_http_test("Database confirms service_requests.diagnosis_id == $diag_id", 
    $linked_data && (int)$linked_data['diagnosis_id'] === (int)$diag_id);
run_http_test("Database confirms appliance_diagnoses.service_request_id == $new_req_id", 
    $linked_data && (int)$linked_data['service_request_id'] === (int)$new_req_id);
run_http_test("Service request inherited media attachment from diagnosis", 
    !empty($linked_data['attachment']));

// 6. Test Engineer Request Details page rendering
$eng_details_url = 'http://127.0.0.1/electronice%20website%203/engineer/request-details.php?id=' . $new_req_id;
// Log in as engineer
$eng_user = $pdo->query("SELECT u.email FROM users u JOIN engineers e ON e.user_id = u.id WHERE e.id = $eng_id")->fetch();
$l_eng = curl_init();
curl_setopt($l_eng, CURLOPT_URL, $login_url);
curl_setopt($l_eng, CURLOPT_POST, true);
curl_setopt($l_eng, CURLOPT_POSTFIELDS, json_encode([
    'email' => $eng_user['email'],
    'password' => 'Engineer@123'
]));
curl_setopt($l_eng, CURLOPT_RETURNTRANSFER, true);
curl_setopt($l_eng, CURLOPT_HEADER, true);
curl_setopt($l_eng, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$eng_resp = curl_exec($l_eng);
preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $eng_resp, $eng_matches);
$eng_cookie = implode('; ', $eng_matches[1]);
curl_close($l_eng);

$eng_page = http_request($eng_details_url, 'GET', null, $eng_cookie);
run_http_test("Engineer request-details.php loads 200 OK", $eng_page['code'] === 200);
run_http_test("Engineer page contains 'SMART DIAGNOSIS DOSSIER'", 
    strpos($eng_page['body'], 'SMART DIAGNOSIS DOSSIER') !== false);
run_http_test("Engineer page displays appliance brand 'LG OLED55C1'", 
    strpos($eng_page['body'], 'LG OLED55C1') !== false);

// Clean up test file and database records
if (file_exists($tmp_img)) @unlink($tmp_img);
if (!empty($diag_res['json']['data']['media_path']) && file_exists(__DIR__ . '/../' . $diag_res['json']['data']['media_path'])) {
    @unlink(__DIR__ . '/../' . $diag_res['json']['data']['media_path']);
}
$pdo->prepare("DELETE FROM service_requests WHERE id = ?")->execute([$new_req_id]);
$pdo->prepare("DELETE FROM appliance_diagnoses WHERE id = ?")->execute([$diag_id]);
echo "\n Cleaned up test mock request and uploaded media.\n";

echo "\n========================================================\n";
echo " HTTP INTEGRATION TEST SUMMARY\n";
echo " Passed: $tests_passed\n";
echo " Failed: $tests_failed\n";
echo " Status: " . ($tests_failed === 0 ? "ALL HTTP INTEGRATION TESTS PASSED! ✓" : "FAILURES DETECTED!") . "\n";
echo "========================================================\n";
exit($tests_failed === 0 ? 0 : 1);
