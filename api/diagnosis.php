<?php
// ============================================================================
// VoltCraft 3D - API: Smart Electronics Diagnosis Engine
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/diagnosis-engine.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $pdo = get_db_connection();
    $kb = get_diagnosis_knowledge_base();

    // 1. Get Questions & Categories for selected Appliance
    if ($action === 'get_questions') {
        $appliance = trim($_GET['appliance'] ?? $input['appliance'] ?? '');
        if (!isset($kb[$appliance])) {
            json_response('error', ['message' => 'Invalid appliance type selected.'], 400);
        }

        json_response('success', [
            'appliance' => $appliance,
            'details' => $kb[$appliance]
        ]);
    }

    // 2. Perform Rule-Based Diagnosis
    elseif ($action === 'diagnose') {
        $appliance = trim($_POST['appliance'] ?? $input['appliance'] ?? '');
        $brand = trim($_POST['brand'] ?? $input['brand'] ?? '');
        $model = trim($_POST['model'] ?? $input['model'] ?? '');
        $category = trim($_POST['category'] ?? $input['category'] ?? '');
        $description = trim($_POST['description'] ?? $input['description'] ?? '');
        $symptoms = $_POST['symptoms'] ?? $input['symptoms'] ?? [];
        $answers = $_POST['answers'] ?? $input['answers'] ?? [];

        if (is_string($symptoms)) {
            $symptoms = json_decode($symptoms, true) ?: [$symptoms];
        }
        if (is_string($answers)) {
            $answers = json_decode($answers, true) ?: [];
        }

        if (empty($appliance) || empty($brand) || empty($category)) {
            json_response('error', ['message' => 'Please select appliance type, brand, and problem category.'], 400);
        }

        // Handle media upload (photos / videos)
        $media_path = null;
        if (!empty($_FILES['media']['name'])) {
            $allowed = ['jpg', 'jpeg', 'png', 'webp', 'mp4', 'mov', 'pdf'];
            $file_name = $_FILES['media']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (in_array($ext, $allowed)) {
                $target_dir = __DIR__ . '/../assets/uploads/';
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

                $saved_name = 'diag_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['media']['tmp_name'], $target_dir . $saved_name)) {
                    $media_path = 'assets/uploads/' . $saved_name;
                }
            }
        }

        // Run Rule-Based Engine
        $diag_result = run_rule_based_diagnosis($appliance, $category, $answers);

        // Identify logged-in customer if available
        $customer_id = null;
        $cust_name = 'Guest Visitor';
        $cust_phone = null;

        if (is_logged_in()) {
            $user = current_user();
            $cust_name = $user['full_name'];
            $cust_phone = $user['phone'];
            if (current_role() === 'customer') {
                $customer_id = get_customer_id($user['id']);
            }
        }

        // Save diagnosis record
        $diagnosis_id = save_appliance_diagnosis([
            'customer_id' => $customer_id,
            'customer_name' => $cust_name,
            'customer_phone' => $cust_phone,
            'appliance_type' => $appliance,
            'brand' => $brand,
            'model' => $model,
            'problem_category' => $category,
            'symptoms' => $symptoms,
            'problem_description' => $description,
            'troubleshooting_answers' => $answers,
            'possible_causes' => $diag_result['probable_causes'],
            'safety_recommendations' => array_merge($diag_result['safe_troubleshooting'], $diag_result['safety_warnings']),
            'media_attachment' => $media_path
        ]);

        json_response('success', [
            'diagnosis_id' => $diagnosis_id,
            'appliance' => $appliance,
            'brand' => $brand,
            'model' => $model,
            'category' => $category,
            'report' => $diag_result,
            'media_path' => $media_path
        ]);
    }

    // 3. Submit Service Request Directly from Diagnosis
    elseif ($action === 'submit_request_with_diagnosis') {
        if (!is_logged_in() || current_role() !== 'customer') {
            json_response('error', ['message' => 'Please sign in to submit a service request.'], 403);
        }

        $customer_id = get_customer_id($_SESSION['user_id']);
        if (!$customer_id) {
            json_response('error', ['message' => 'Customer profile not found.'], 404);
        }

        $diagnosis_id = intval($input['diagnosis_id'] ?? 0);
        $engineer_id = intval($input['engineer_id'] ?? 0);
        $service_id = intval($input['service_id'] ?? 0);
        $address = trim($input['address'] ?? '');
        $city = trim($input['city'] ?? '');
        $preferred_date = trim($input['preferred_date'] ?? date('Y-m-d', strtotime('+1 day')));
        $preferred_time = trim($input['preferred_time'] ?? '10:00 AM - 01:00 PM');
        $estimated_amount = floatval($input['estimated_amount'] ?? 0);

        if (!$diagnosis_id || !$engineer_id || empty($address)) {
            json_response('error', ['message' => 'Please provide engineer, service address, and diagnosis ID.'], 400);
        }

        // Fetch diagnosis details
        $diag_stmt = $pdo->prepare("SELECT * FROM appliance_diagnoses WHERE id = ?");
        $diag_stmt->execute([$diagnosis_id]);
        $diag = $diag_stmt->fetch();

        if (!$diag) {
            json_response('error', ['message' => 'Diagnosis record not found.'], 404);
        }

        // If service_id not provided, match or pick default
        if (!$service_id) {
            $srv_search = $pdo->prepare("SELECT id, base_price FROM services WHERE name LIKE ? OR category LIKE ? LIMIT 1");
            $srv_search->execute(['%' . $diag['appliance_type'] . '%', '%' . $diag['appliance_type'] . '%']);
            $matched_srv = $srv_search->fetch();

            if ($matched_srv) {
                $service_id = (int)$matched_srv['id'];
                if ($estimated_amount <= 0) $estimated_amount = (float)$matched_srv['base_price'];
            } else {
                $service_id = (int)$pdo->query("SELECT id FROM services ORDER BY id ASC LIMIT 1")->fetchColumn();
                if ($estimated_amount <= 0) $estimated_amount = 1500.00;
            }
        }

        $title = "Smart Diagnosis: " . $diag['appliance_type'] . " (" . $diag['brand'] . " " . ($diag['model'] ? $diag['model'] : '') . ") — " . $diag['problem_category'];
        
        // Build rich description incorporating findings
        $description = "【SMART DIAGNOSIS REPORT】\n";
        $description .= "• Appliance: " . $diag['appliance_type'] . " | Brand: " . $diag['brand'] . " " . ($diag['model'] ? " | Model: " . $diag['model'] : "") . "\n";
        $description .= "• Issue Category: " . $diag['problem_category'] . "\n";
        if (!empty($diag['symptoms'])) {
            $description .= "• Observed Symptoms: " . $diag['symptoms'] . "\n";
        }
        $description .= "• Customer Notes: " . ($diag['problem_description'] ?: 'None') . "\n";

        $causes = json_decode($diag['possible_causes'], true) ?: [];
        if (!empty($causes)) {
            $description .= "• Algorithmic Probable Causes:\n";
            foreach ($causes as $c) {
                $description .= "  - " . $c['cause'] . " (" . $c['probability'] . ")\n";
            }
        }

        $request_code = generate_code('REQ');

        // Create Service Request
        $ins = $pdo->prepare("INSERT INTO service_requests 
            (request_code, customer_id, engineer_id, service_id, title, description, address, city, preferred_date, preferred_time, attachment, estimated_amount, status, diagnosis_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING', ?)");
        $ins->execute([
            $request_code, $customer_id, $engineer_id, $service_id, $title,
            $description, $address, $city, $preferred_date, $preferred_time,
            $diag['media_attachment'], $estimated_amount, $diagnosis_id
        ]);
        $new_request_id = $pdo->lastInsertId();

        // Update appliance_diagnoses with customer_id and service_request_id
        $pdo->prepare("UPDATE appliance_diagnoses SET customer_id = ?, service_request_id = ? WHERE id = ?")
            ->execute([$customer_id, $new_request_id, $diagnosis_id]);

        // Notify Engineer
        $eng_u = $pdo->prepare("SELECT user_id FROM engineers WHERE id = ?");
        $eng_u->execute([$engineer_id]);
        $eng_uid = $eng_u->fetchColumn();
        if ($eng_uid) {
            create_notification(
                $eng_uid,
                'new_request',
                '⚡ New Smart Diagnostic Repair Request',
                "Customer submitted request {$request_code} with full diagnostic findings for {$diag['appliance_type']} ({$diag['brand']}).",
                'engineer/requests.php'
            );
        }

        json_response('success', [
            'message' => 'Service request submitted successfully with complete Smart Diagnosis summary!',
            'request_id' => $new_request_id,
            'request_code' => $request_code,
            'redirect' => BASE_URL . 'customer/requests.php'
        ]);
    }

    // 4. Fetch Diagnosis Dossier
    elseif ($action === 'get_dossier') {
        $diag_id = intval($_GET['id'] ?? 0);
        $req_id = intval($_GET['request_id'] ?? 0);
        $bk_id = intval($_GET['booking_id'] ?? 0);

        $dossier = get_diagnosis_dossier($diag_id ?: null, $req_id ?: null, $bk_id ?: null);
        if (!$dossier) {
            json_response('error', ['message' => 'No diagnostic report found.'], 404);
        }

        $dossier['troubleshooting_answers'] = json_decode($dossier['troubleshooting_answers'] ?? '[]', true);
        $dossier['possible_causes'] = json_decode($dossier['possible_causes'] ?? '[]', true);
        $dossier['safety_recommendations'] = json_decode($dossier['safety_recommendations'] ?? '[]', true);

        json_response('success', ['dossier' => $dossier]);
    }

    else {
        json_response('error', ['message' => 'Invalid diagnosis action.'], 400);
    }

} catch (Exception $e) {
    error_log("Diagnosis API Error: " . $e->getMessage());
    json_response('error', ['message' => 'An error occurred processing diagnosis: ' . $e->getMessage()], 500);
}
