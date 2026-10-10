<?php
// ============================================================================
// VoltCraft 3D - API: Service Requests & Bookings Workflow
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/otp-functions.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $pdo = get_db_connection();

    // 1. Submit Service Request (Customer)
    if ($action === 'create_request') {
        if (!is_logged_in() || current_role() !== 'customer') {
            json_response('error', ['message' => 'Please login as a customer to submit a request.'], 403);
        }

        $customer_id = get_customer_id($_SESSION['user_id']);
        if (!$customer_id) {
            json_response('error', ['message' => 'Customer profile not found.'], 404);
        }

        $engineer_id = intval($input['engineer_id'] ?? 0);
        $service_id = intval($input['service_id'] ?? 0);
        $title = trim($input['title'] ?? '');
        $description = trim($input['description'] ?? '');
        $address = trim($input['address'] ?? '');
        $city = trim($input['city'] ?? '');
        $preferred_date = trim($input['preferred_date'] ?? '');
        $preferred_time = trim($input['preferred_time'] ?? '10:00 AM - 01:00 PM');
        $estimated_amount = floatval($input['estimated_amount'] ?? 0);

        if (!$engineer_id || !$service_id || empty($title) || empty($description) || empty($address) || empty($preferred_date)) {
            json_response('error', ['message' => 'Please fill in all required fields.'], 400);
        }

        // Prevent duplicate pending requests for same customer, engineer, and date
        $dup_check = $pdo->prepare("SELECT id FROM service_requests WHERE customer_id = ? AND engineer_id = ? AND preferred_date = ? AND status IN ('PENDING', 'ACCEPTED', 'CONFIRMED')");
        $dup_check->execute([$customer_id, $engineer_id, $preferred_date]);
        if ($dup_check->fetch()) {
            json_response('error', ['message' => 'You already have an active service request with this engineer for this date.'], 409);
        }

        // Calculate default amount if missing
        if ($estimated_amount <= 0) {
            $srv_stmt = $pdo->prepare("SELECT base_price FROM services WHERE id = ?");
            $srv_stmt->execute([$service_id]);
            $estimated_amount = floatval($srv_stmt->fetchColumn() ?: 2000.00);
        }

        $request_code = generate_code('REQ');

        $stmt = $pdo->prepare("INSERT INTO service_requests 
            (request_code, customer_id, engineer_id, service_id, title, description, address, city, preferred_date, preferred_time, estimated_amount, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')");
        $stmt->execute([
            $request_code, $customer_id, $engineer_id, $service_id, $title,
            $description, $address, $city, $preferred_date, $preferred_time, $estimated_amount
        ]);
        $new_request_id = $pdo->lastInsertId();

        // Get engineer user_id to notify them
        $eng_user = $pdo->prepare("SELECT user_id FROM engineers WHERE id = ?");
        $eng_user->execute([$engineer_id]);
        $eng_user_id = $eng_user->fetchColumn();

        if ($eng_user_id) {
            create_notification(
                $eng_user_id,
                'new_request',
                'New Service Request Received',
                "Customer submitted a new request ({$request_code}): {$title}",
                "engineer/requests.php"
            );
        }

        json_response('success', [
            'message' => 'Service request submitted successfully! The engineer will review it shortly.',
            'request_id' => $new_request_id,
            'request_code' => $request_code,
            'redirect' => BASE_URL . 'customer/requests.php'
        ]);
    }

    // 2. Engineer Responds to Request (Accept / Reject)
    elseif ($action === 'respond_request') {
        if (!is_logged_in() || current_role() !== 'engineer') {
            json_response('error', ['message' => 'Unauthorized action.'], 403);
        }

        $engineer_id = get_engineer_id($_SESSION['user_id']);
        $request_id = intval($input['request_id'] ?? 0);
        $decision = trim(strtoupper($input['action'] ?? ''));
        $reason = trim($input['reason'] ?? '');

        if (!$request_id || !in_array($decision, ['ACCEPT', 'REJECT'])) {
            json_response('error', ['message' => 'Invalid request response parameters.'], 400);
        }

        $req_stmt = $pdo->prepare("SELECT sr.*, c.user_id AS customer_user_id FROM service_requests sr JOIN customers c ON sr.customer_id = c.id WHERE sr.id = ? AND sr.engineer_id = ?");
        $req_stmt->execute([$request_id, $engineer_id]);
        $req = $req_stmt->fetch();

        if (!$req) {
            json_response('error', ['message' => 'Service request not found or not assigned to you.'], 404);
        }

        if ($decision === 'ACCEPT') {
            $new_status = 'ACCEPTED';
            $update_stmt = $pdo->prepare("UPDATE service_requests SET status = 'ACCEPTED' WHERE id = ?");
            $update_stmt->execute([$request_id]);

            // Notify customer that request was accepted and advance is required
            $advance = $req['estimated_amount'] * ADVANCE_PERCENTAGE;
            create_notification(
                $req['customer_user_id'],
                'request_accepted',
                'Request Accepted — 20% Advance Required',
                "Your service request {$req['request_code']} was accepted! Pay the 20% advance (" . format_currency($advance) . ") to confirm your appointment.",
                "customer/payment.php?request_id=" . $request_id
            );

            json_response('success', [
                'message' => 'Request accepted! The customer has been prompted to deposit the 20% advance.',
                'status' => 'ACCEPTED'
            ]);
        } else {
            $update_stmt = $pdo->prepare("UPDATE service_requests SET status = 'REJECTED', rejection_reason = ? WHERE id = ?");
            $update_stmt->execute([$reason ?: 'Engineer is unavailable during the requested time slot.', $request_id]);

            create_notification(
                $req['customer_user_id'],
                'request_rejected',
                'Service Request Declined',
                "Your request {$req['request_code']} could not be accepted. Reason: " . ($reason ?: 'Scheduling conflict'),
                "customer/requests.php"
            );

            json_response('success', [
                'message' => 'Request has been rejected.',
                'status' => 'REJECTED'
            ]);
        }
    }

    // 3. Update Booking Status (IN_PROGRESS, COMPLETED, CANCELLED)
    elseif ($action === 'update_status') {
        if (!is_logged_in() || !in_array(current_role(), ['engineer', 'admin'])) {
            json_response('error', ['message' => 'Unauthorized action.'], 403);
        }

        $booking_id = intval($input['booking_id'] ?? 0);
        $new_status = trim(strtoupper($input['status'] ?? ''));
        $notes = trim($input['notes'] ?? '');

        $allowed_statuses = [
            'CONFIRMED', 'IN_PROGRESS', 'REPAIR_IN_PROGRESS',
            'REPAIR_COMPLETED_AWAITING_ADMIN', 'COMPLETED', 'COMPLETED_AND_VERIFIED', 'CANCELLED'
        ];

        if (!$booking_id || !in_array($new_status, $allowed_statuses)) {
            json_response('error', ['message' => 'Invalid booking status update.'], 400);
        }

        $b_stmt = $pdo->prepare("SELECT b.*, sr.title, sr.request_code, c.user_id AS customer_user_id, eng_u.full_name as engineer_name 
                                 FROM bookings b 
                                 JOIN service_requests sr ON b.request_id = sr.id 
                                 JOIN customers c ON b.customer_id = c.id 
                                 JOIN engineers e ON b.engineer_id = e.id 
                                 JOIN users eng_u ON e.user_id = eng_u.id 
                                 WHERE b.id = ?");
        $b_stmt->execute([$booking_id]);
        $booking = $b_stmt->fetch();

        if (!$booking) {
            json_response('error', ['message' => 'Booking not found.'], 404);
        }

        // If engineer, verify ownership and prevent direct unverified completion
        if (current_role() === 'engineer') {
            $eng_id = get_engineer_id($_SESSION['user_id']);
            if ($booking['engineer_id'] != $eng_id) {
                json_response('error', ['message' => 'Access denied.'], 403);
            }

            // Direct completion without OTP is prohibited for engineers
            if (in_array($new_status, ['COMPLETED', 'COMPLETED_AND_VERIFIED'])) {
                json_response('error', [
                    'message' => 'Final completion requires customer OTP verification authorized by Admin.'
                ], 400);
            }
        }

        $completed_sql = in_array($new_status, ['COMPLETED', 'COMPLETED_AND_VERIFIED']) ? ", completed_at = NOW()" : "";
        $update = $pdo->prepare("UPDATE bookings SET booking_status = ? $completed_sql, notes = CONCAT(IFNULL(notes,''), '\n', ?) WHERE id = ?");
        $update->execute([$new_status, $notes, $booking_id]);

        // Keep service request status synchronized
        $pdo->prepare("UPDATE service_requests SET status = ? WHERE id = ?")->execute([$new_status, $booking['request_id']]);

        // When engineer marks repair completed awaiting admin
        if ($new_status === 'REPAIR_COMPLETED_AWAITING_ADMIN') {
            log_otp_audit(
                $booking_id, null, $_SESSION['user_id'], current_role(),
                'REPAIR_COMPLETED',
                "Engineer {$booking['engineer_name']} marked repair completed. Findings: " . ($notes ?: 'No additional notes.')
            );

            // Notify all admins immediately
            notify_all_admins(
                'repair_completed',
                '🛠️ Repair Completed: ' . $booking['booking_code'],
                "Engineer {$booking['engineer_name']} finished repair on {$booking['title']}. Admin OTP authorization requested.",
                'admin/otp-verification.php?booking_id=' . $booking_id
            );

            // Notify customer
            create_notification(
                $booking['customer_user_id'],
                'repair_completed_pending_admin',
                'Repair Completed — Admin Authorization Pending',
                "Your engineer {$booking['engineer_name']} has completed repair work for {$booking['booking_code']}. You will receive a verification OTP as soon as Admin authorizes completion.",
                'customer/booking-details.php?id=' . $booking_id
            );
        } else {
            // Customer notification for other statuses
            $status_msg = [
                'IN_PROGRESS'        => 'The engineer has arrived and begun the service diagnostics.',
                'REPAIR_IN_PROGRESS' => 'The engineer has started active hardware repair & diagnostic procedures.',
                'COMPLETED'          => 'Service has been completed! Please inspect the repair and leave a rating.',
                'CANCELLED'          => 'The booking was cancelled.'
            ];

            create_notification(
                $booking['customer_user_id'],
                'booking_status_change',
                "Booking Status: " . str_replace('_', ' ', $new_status),
                $status_msg[$new_status] ?? "Status is now {$new_status}",
                "customer/booking-details.php?id=" . $booking_id
            );
        }

        json_response('success', [
            'message' => ($new_status === 'REPAIR_COMPLETED_AWAITING_ADMIN')
                ? 'Repair marked as completed! Admin has been notified to authorize customer OTP generation.'
                : "Booking status updated to {$new_status}.",
            'status' => $new_status
        ]);
    }

    else {
        json_response('error', ['message' => 'Invalid action.'], 400);
    }

} catch (Exception $e) {
    error_log("Booking API Error: " . $e->getMessage());
    json_response('error', ['message' => 'An error occurred processing the booking action.'], 500);
}
