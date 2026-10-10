<?php
// ============================================================================
// VoltCraft 3D - API: Admin-Controlled OTP Verification System
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

    // 1. Admin Authorizes OTP Generation
    if ($action === 'authorize_otp') {
        if (!is_logged_in() || current_role() !== 'admin') {
            json_response('error', ['message' => 'Unauthorized: Only an Administrator can authorize OTP generation.'], 403);
        }

        $booking_id = intval($input['booking_id'] ?? 0);
        $expiry_mins = intval($input['expiry_minutes'] ?? 15);
        if ($expiry_mins <= 0 || $expiry_mins > 60) $expiry_mins = 15;

        if (!$booking_id) {
            json_response('error', ['message' => 'Booking ID is required.'], 400);
        }

        $result = admin_authorize_and_generate_otp($booking_id, $_SESSION['user_id'], $expiry_mins);
        if ($result['status'] === 'success') {
            json_response('success', $result);
        } else {
            json_response('error', ['message' => $result['message']], 400);
        }
    }

    // 2. Admin Rejects OTP Authorization
    elseif ($action === 'reject_otp') {
        if (!is_logged_in() || current_role() !== 'admin') {
            json_response('error', ['message' => 'Unauthorized: Only an Administrator can reject OTP generation.'], 403);
        }

        $booking_id = intval($input['booking_id'] ?? 0);
        $reason = trim($input['reason'] ?? '');

        if (!$booking_id) {
            json_response('error', ['message' => 'Booking ID is required.'], 400);
        }

        $result = admin_reject_otp_authorization($booking_id, $_SESSION['user_id'], $reason);
        if ($result['status'] === 'success') {
            json_response('success', $result);
        } else {
            json_response('error', ['message' => $result['message']], 400);
        }
    }

    // 3. Admin Regenerates / Extends OTP
    elseif ($action === 'regenerate_otp') {
        if (!is_logged_in() || current_role() !== 'admin') {
            json_response('error', ['message' => 'Unauthorized: Only an Administrator can regenerate OTP.'], 403);
        }

        $booking_id = intval($input['booking_id'] ?? 0);
        $expiry_mins = intval($input['expiry_minutes'] ?? 15);

        if (!$booking_id) {
            json_response('error', ['message' => 'Booking ID is required.'], 400);
        }

        // Allow regenerating if expired or pending
        $stmt = $pdo->prepare("SELECT booking_status FROM bookings WHERE id = ?");
        $stmt->execute([$booking_id]);
        $curr_status = $stmt->fetchColumn();

        if (in_array($curr_status, ['OTP_EXPIRED', 'VERIFICATION_FAILED', 'OTP_VERIFICATION_PENDING', 'REPAIR_COMPLETED_AWAITING_ADMIN'])) {
            // Reset booking status to allow re-generation
            $pdo->prepare("UPDATE bookings SET booking_status = 'REPAIR_COMPLETED_AWAITING_ADMIN' WHERE id = ?")->execute([$booking_id]);
            $result = admin_authorize_and_generate_otp($booking_id, $_SESSION['user_id'], $expiry_mins);
            if ($result['status'] === 'success') {
                log_otp_audit($booking_id, $result['verification_id'], $_SESSION['user_id'], 'admin', 'OTP_REGENERATED', 'Admin regenerated a fresh OTP.');
                json_response('success', $result);
            } else {
                json_response('error', ['message' => $result['message']], 400);
            }
        } else {
            json_response('error', ['message' => 'Cannot regenerate OTP for a booking with status: ' . $curr_status], 400);
        }
    }

    // 4. Engineer Enters & Verifies Customer OTP
    elseif ($action === 'verify_otp') {
        if (!is_logged_in() || current_role() !== 'engineer') {
            json_response('error', ['message' => 'Unauthorized: Only the assigned Engineer can verify the customer OTP.'], 403);
        }

        $engineer_id = get_engineer_id($_SESSION['user_id']);
        if (!$engineer_id) {
            json_response('error', ['message' => 'Engineer profile not found.'], 404);
        }

        $booking_id = intval($input['booking_id'] ?? 0);
        $otp = trim($input['otp'] ?? '');

        if (!$booking_id || empty($otp)) {
            json_response('error', ['message' => 'Booking ID and 6-digit OTP code are required.'], 400);
        }

        $result = verify_engineer_otp($booking_id, $otp, $engineer_id, $_SESSION['user_id']);
        if ($result['status'] === 'success') {
            json_response('success', $result);
        } else {
            json_response('error', ['message' => $result['message']], 400);
        }
    }

    // 5. Fetch Audit Trail (Admin Only)
    elseif ($action === 'get_audit_logs') {
        if (!is_logged_in() || current_role() !== 'admin') {
            json_response('error', ['message' => 'Unauthorized.'], 403);
        }

        $booking_id = intval($_GET['booking_id'] ?? $input['booking_id'] ?? 0);
        if (!$booking_id) {
            json_response('error', ['message' => 'Booking ID required.'], 400);
        }

        $logs = get_booking_otp_audit_logs($booking_id);
        json_response('success', ['logs' => $logs]);
    }

    // 6. Admin Summary Stats
    elseif ($action === 'get_stats') {
        if (!is_logged_in() || current_role() !== 'admin') {
            json_response('error', ['message' => 'Unauthorized.'], 403);
        }

        $awaiting_count = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'REPAIR_COMPLETED_AWAITING_ADMIN'")->fetchColumn();
        $pending_verify_count = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status IN ('OTP_VERIFICATION_PENDING', 'OTP_SENT_TO_CUSTOMER', 'OTP_GENERATION_AUTHORIZED')")->fetchColumn();
        $verified_count = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status = 'COMPLETED_AND_VERIFIED'")->fetchColumn();
        $rejected_expired_count = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_status IN ('OTP_GENERATION_REJECTED', 'OTP_EXPIRED', 'VERIFICATION_FAILED')")->fetchColumn();

        json_response('success', [
            'awaiting_admin' => $awaiting_count,
            'pending_verification' => $pending_verify_count,
            'completed_verified' => $verified_count,
            'rejected_expired' => $rejected_expired_count
        ]);
    }

    else {
        json_response('error', ['message' => 'Invalid OTP action.'], 400);
    }

} catch (Exception $e) {
    error_log("OTP API Error: " . $e->getMessage());
    json_response('error', ['message' => 'An unexpected server error occurred: ' . $e->getMessage()], 500);
}
