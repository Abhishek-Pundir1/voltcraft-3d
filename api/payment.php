<?php
// ============================================================================
// VoltCraft 3D - API: 20% Advance Payment & Transaction Processing
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $pdo = get_db_connection();

    // 1. Process 20% Advance Payment
    if ($action === 'pay_advance') {
        if (!is_logged_in() || current_role() !== 'customer') {
            json_response('error', ['message' => 'Please login as a customer to proceed with payment.'], 403);
        }

        $customer_id = get_customer_id($_SESSION['user_id']);
        $request_id = intval($input['request_id'] ?? 0);
        $payment_method = trim($input['payment_method'] ?? 'UPI');
        $transaction_ref = trim($input['transaction_ref'] ?? '');
        $notes = trim($input['notes'] ?? '20% Advance payment');

        if (!$request_id) {
            json_response('error', ['message' => 'Missing service request ID.'], 400);
        }

        // Fetch request details
        $req_stmt = $pdo->prepare("SELECT * FROM service_requests WHERE id = ? AND customer_id = ?");
        $req_stmt->execute([$request_id, $customer_id]);
        $req = $req_stmt->fetch();

        if (!$req) {
            json_response('error', ['message' => 'Service request not found.'], 404);
        }

        if (!in_array($req['status'], ['ACCEPTED', 'PAYMENT_PENDING'])) {
            json_response('error', ['message' => 'This request is not in a payable state (Current status: ' . $req['status'] . ').'], 400);
        }

        // Calculate 20% advance & 80% remaining amounts
        $total_amount = floatval($req['estimated_amount']);
        $advance_amount = round($total_amount * ADVANCE_PERCENTAGE, 2);
        $remaining_amount = round($total_amount - $advance_amount, 2);

        // Gateway transaction verification simulation (Ready for Razorpay/Stripe webhook)
        if (empty($transaction_ref)) {
            $transaction_ref = 'TXN-VOLT-' . strtoupper(bin2hex(random_bytes(4)));
        }

        // Mock external gateway signature check (e.g. Razorpay payment_id)
        $gateway_payment_id = 'pay_volt_' . bin2hex(random_bytes(6));
        $gateway_order_id = 'order_volt_' . bin2hex(random_bytes(4));

        $pdo->beginTransaction();

        // Check if booking already created or create new
        $b_check = $pdo->prepare("SELECT id FROM bookings WHERE request_id = ?");
        $b_check->execute([$request_id]);
        $existing_booking_id = $b_check->fetchColumn();

        if ($existing_booking_id) {
            $booking_id = $existing_booking_id;
            $update_b = $pdo->prepare("UPDATE bookings SET 
                total_amount = ?, advance_amount = ?, remaining_amount = ?, 
                payment_status = 'ADVANCE_PAID', booking_status = 'CONFIRMED' 
                WHERE id = ?");
            $update_b->execute([$total_amount, $advance_amount, $remaining_amount, $booking_id]);
        } else {
            $booking_code = generate_code('BK');
            $scheduled_at = $req['preferred_date'] . ' ' . date('H:i:s', strtotime($req['preferred_time']));

            $insert_b = $pdo->prepare("INSERT INTO bookings 
                (booking_code, request_id, customer_id, engineer_id, service_id, total_amount, advance_amount, remaining_amount, payment_status, booking_status, scheduled_at, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ADVANCE_PAID', 'CONFIRMED', ?, ?)");
            $insert_b->execute([
                $booking_code, $request_id, $customer_id, $req['engineer_id'], $req['service_id'],
                $total_amount, $advance_amount, $remaining_amount, $scheduled_at, $notes
            ]);
            $booking_id = $pdo->lastInsertId();
            // Link diagnosis to booking if exists
            try {
                $pdo->prepare("UPDATE appliance_diagnoses SET booking_id = ? WHERE service_request_id = ?")->execute([$booking_id, $request_id]);
                $pdo->prepare("UPDATE bookings SET diagnosis_id = (SELECT id FROM appliance_diagnoses WHERE service_request_id = ? LIMIT 1) WHERE id = ?")->execute([$request_id, $booking_id]);
            } catch (Exception $e) {
                // Silently continue if column or table not yet linked
            }
        }

        // Record verified payment in payments table
        $insert_pay = $pdo->prepare("INSERT INTO payments 
            (transaction_ref, booking_id, customer_id, engineer_id, amount, payment_type, payment_method, payment_status, gateway_payment_id, gateway_order_id, notes) 
            VALUES (?, ?, ?, ?, ?, 'ADVANCE_20', ?, 'SUCCESS', ?, ?, ?)");
        $insert_pay->execute([
            $transaction_ref, $booking_id, $customer_id, $req['engineer_id'],
            $advance_amount, $payment_method, $gateway_payment_id, $gateway_order_id, $notes
        ]);
        $payment_id = $pdo->lastInsertId();

        // Update service request status to CONFIRMED
        $pdo->prepare("UPDATE service_requests SET status = 'CONFIRMED' WHERE id = ?")->execute([$request_id]);

        // Engineer Notification
        $eng_stmt = $pdo->prepare("SELECT user_id FROM engineers WHERE id = ?");
        $eng_stmt->execute([$req['engineer_id']]);
        $eng_user_id = $eng_stmt->fetchColumn();

        if ($eng_user_id) {
            create_notification(
                $eng_user_id,
                'payment_received',
                '20% Advance Paid — Booking Confirmed!',
                "Customer paid " . format_currency($advance_amount) . " advance. Booking confirmed for " . $req['preferred_date'] . ".",
                "engineer/bookings.php"
            );
        }

        // Customer Notification
        create_notification(
            $_SESSION['user_id'],
            'booking_confirmed',
            'Advance Payment Verified — Appointment Locked!',
            "Your 20% advance (" . format_currency($advance_amount) . ") was verified. Booking confirmed.",
            "customer/booking-details.php?id=" . $booking_id
        );

        $pdo->commit();

        json_response('success', [
            'message' => '20% Advance payment successfully verified! Your booking is confirmed.',
            'booking_id' => $booking_id,
            'payment_id' => $payment_id,
            'transaction_ref' => $transaction_ref,
            'amount_paid' => $advance_amount,
            'remaining_balance' => $remaining_amount,
            'redirect' => BASE_URL . 'customer/booking-details.php?id=' . $booking_id
        ]);
    }

    // 2. Process Remaining 80% Payment (upon completion)
    elseif ($action === 'pay_remaining') {
        if (!is_logged_in()) {
            json_response('error', ['message' => 'Please sign in.'], 403);
        }

        $booking_id = intval($input['booking_id'] ?? 0);
        $payment_method = trim($input['payment_method'] ?? 'UPI');
        $transaction_ref = trim($input['transaction_ref'] ?? ('TXN-VOLT-REM-' . strtoupper(bin2hex(random_bytes(3)))));

        $b_stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ?");
        $b_stmt->execute([$booking_id]);
        $booking = $b_stmt->fetch();

        if (!$booking) {
            json_response('error', ['message' => 'Booking not found.'], 404);
        }

        $pdo->beginTransaction();

        $insert_pay = $pdo->prepare("INSERT INTO payments 
            (transaction_ref, booking_id, customer_id, engineer_id, amount, payment_type, payment_method, payment_status, notes) 
            VALUES (?, ?, ?, ?, ?, 'REMAINING_80', ?, 'SUCCESS', 'Settlement of 80% remaining service balance')");
        $insert_pay->execute([
            $transaction_ref, $booking_id, $booking['customer_id'], $booking['engineer_id'],
            $booking['remaining_amount'], $payment_method
        ]);

        $pdo->prepare("UPDATE bookings SET payment_status = 'FULLY_PAID' WHERE id = ?")->execute([$booking_id]);

        $pdo->commit();

        json_response('success', [
            'message' => 'Remaining balance of ' . format_currency($booking['remaining_amount']) . ' recorded successfully!',
            'transaction_ref' => $transaction_ref
        ]);
    }

    else {
        json_response('error', ['message' => 'Invalid action.'], 400);
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Payment API Error: " . $e->getMessage());
    json_response('error', ['message' => 'An error occurred processing the payment: ' . $e->getMessage()], 500);
}
