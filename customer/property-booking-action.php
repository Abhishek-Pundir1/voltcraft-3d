<?php
// ============================================================================
// VoltCraft 3D — Customer Property Actions Handler
// Handles C2C Bookings, Status Transitions, Cancellations, Availability & Reports
// ============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/property-functions.php';

require_login();
$user = current_user();
$customer_id = get_customer_id($user['id']);
$pdo = get_db_connection();

$action = clean_input($_POST['action'] ?? $_GET['action'] ?? '');

// Verify CSRF Token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($action, ['toggle_save'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Security validation failed. Please try again.');
        header("Location: " . BASE_URL . "properties.php");
        exit;
    }
}

switch ($action) {

    // ========================================================================
    // 1. Submit C2C Booking / Visit Request (Requirement 5)
    // ========================================================================
    case 'submit_booking_request':
        if (!$customer_id) {
            set_flash('warning', 'Only registered customer accounts can submit booking requests.');
            header("Location: " . BASE_URL . "login.php");
            exit;
        }

        $property_id = intval($_POST['property_id'] ?? 0);
        $request_type = clean_input($_POST['request_type'] ?? 'visit');
        $visit_date = clean_input($_POST['scheduled_visit_date'] ?? '');
        $visit_time = clean_input($_POST['scheduled_visit_time'] ?? '');
        $message = clean_input($_POST['message'] ?? '');

        // Fetch property
        $stmt = $pdo->prepare("SELECT p.*, c.user_id as owner_user_id FROM customer_properties p JOIN customers c ON p.customer_id = c.id WHERE p.id = ?");
        $stmt->execute([$property_id]);
        $prop = $stmt->fetch();

        if (!$prop) {
            set_flash('danger', 'Property listing not found.');
            header("Location: " . BASE_URL . "properties.php");
            exit;
        }

        if ((int)$prop['customer_id'] === (int)$customer_id) {
            set_flash('warning', 'You cannot submit a booking request for your own listed property.');
            header("Location: " . BASE_URL . "property-detail.php?id=" . $property_id);
            exit;
        }

        // Generate Booking Code: PBK-2026-XXXX
        $booking_code = 'PBK-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // Insert booking with status 'Request Submitted' (Requirement 5: Not automatically confirmed)
        $ins = $pdo->prepare("INSERT INTO property_bookings 
            (booking_code, property_id, requester_customer_id, owner_customer_id, request_type, scheduled_visit_date, 
             scheduled_visit_time, message, offered_price, advance_amount, security_deposit, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Request Submitted')");

        $ins->execute([
            $booking_code,
            $property_id,
            $customer_id,
            $prop['customer_id'],
            $request_type,
            $visit_date,
            $visit_time,
            $message,
            $prop['price'],
            $prop['advance_amount'],
            $prop['security_deposit']
        ]);

        // Record inquiry log
        log_property_inquiry($property_id, $user['id'], 'WEB_INQUIRY', "Submitted booking request {$booking_code}");

        // Notify Property Owner (Requirement 5)
        create_notification(
            $prop['owner_user_id'],
            'new_property_request',
            'New Property Request: ' . $prop['property_code'],
            "Customer {$user['full_name']} requested a {$request_type} for {$prop['title']} on {$visit_date}.",
            'customer/properties.php?tab=requests_received'
        );

        set_flash('success', "Your request ({$booking_code}) has been submitted to the property owner! Track progress in your Customer Property Hub.");
        header("Location: " . BASE_URL . "customer/properties.php?tab=my_requests");
        exit;

    // ========================================================================
    // 2. Owner Updates Booking Status (Accept / Reject / Visit Scheduled / Reserved)
    // ========================================================================
    case 'update_booking_status':
        $booking_id = intval($_POST['booking_id'] ?? 0);
        $new_status = clean_input($_POST['new_status'] ?? '');
        $rejection_reason = clean_input($_POST['rejection_reason'] ?? '');

        $allowed_statuses = [
            'Request Submitted',
            'Pending Owner Approval',
            'Accepted',
            'Rejected',
            'Visit Scheduled',
            'Reserved',
            'Rented or Sold',
            'Cancelled'
        ];

        if (!in_array($new_status, $allowed_statuses, true)) {
            set_flash('danger', 'Invalid booking status transition.');
            header("Location: " . BASE_URL . "customer/properties.php?tab=requests_received");
            exit;
        }

        // Verify that current user is the owner
        $chk = $pdo->prepare("SELECT pb.*, p.title as property_title, p.property_code, req_c.user_id as requester_user_id 
                              FROM property_bookings pb
                              JOIN customer_properties p ON pb.property_id = p.id
                              JOIN customers req_c ON pb.requester_customer_id = req_c.id
                              WHERE pb.id = ? AND pb.owner_customer_id = ?");
        $chk->execute([$booking_id, $customer_id]);
        $bk = $chk->fetch();

        if (!$bk) {
            set_flash('danger', 'Unauthorized or booking request not found.');
            header("Location: " . BASE_URL . "customer/properties.php?tab=requests_received");
            exit;
        }

        // Update status
        $upd = $pdo->prepare("UPDATE property_bookings SET status = ?, rejection_reason = ? WHERE id = ?");
        $upd->execute([$new_status, $rejection_reason, $booking_id]);

        // If reserved or rented, update property availability status
        if ($new_status === 'Reserved') {
            $pdo->prepare("UPDATE customer_properties SET availability_status = 'RESERVED' WHERE id = ?")->execute([$bk['property_id']]);
        } elseif ($new_status === 'Rented or Sold') {
            $pdo->prepare("UPDATE customer_properties SET availability_status = 'RENTED_OR_SOLD' WHERE id = ?")->execute([$bk['property_id']]);
        } elseif ($new_status === 'Visit Scheduled') {
            $pdo->prepare("UPDATE customer_properties SET availability_status = 'VISIT_SCHEDULED' WHERE id = ?")->execute([$bk['property_id']]);
        }

        // Notify interested customer
        create_notification(
            $bk['requester_user_id'],
            'property_status_update',
            "Property Request Update: {$bk['property_code']}",
            "The owner updated your booking request status to: '{$new_status}'.",
            'customer/properties.php?tab=my_requests'
        );

        set_flash('success', "Booking request #{$bk['booking_code']} updated to '{$new_status}'.");
        header("Location: " . BASE_URL . "customer/properties.php?tab=requests_received");
        exit;

    // ========================================================================
    // 3. Customer Cancels Their Own Booking Request
    // ========================================================================
    case 'cancel_my_booking':
        $booking_id = intval($_POST['booking_id'] ?? 0);

        $chk = $pdo->prepare("SELECT pb.*, p.property_code, owner_c.user_id as owner_user_id 
                              FROM property_bookings pb
                              JOIN customer_properties p ON pb.property_id = p.id
                              JOIN customers owner_c ON pb.owner_customer_id = owner_c.id
                              WHERE pb.id = ? AND pb.requester_customer_id = ?");
        $chk->execute([$booking_id, $customer_id]);
        $bk = $chk->fetch();

        if (!$bk) {
            set_flash('danger', 'Unauthorized or booking request not found.');
            header("Location: " . BASE_URL . "customer/properties.php?tab=my_requests");
            exit;
        }

        $pdo->prepare("UPDATE property_bookings SET status = 'Cancelled' WHERE id = ?")->execute([$booking_id]);

        // Notify owner
        create_notification(
            $bk['owner_user_id'],
            'property_request_cancelled',
            "Booking Request Cancelled ({$bk['property_code']})",
            "Customer {$user['full_name']} has cancelled their booking request ({$bk['booking_code']}).",
            'customer/properties.php?tab=requests_received'
        );

        set_flash('info', "Booking request #{$bk['booking_code']} has been cancelled.");
        header("Location: " . BASE_URL . "customer/properties.php?tab=my_requests");
        exit;

    // ========================================================================
    // 4. Update Property Availability (Available, Visit Scheduled, Reserved, Rented/Sold, Inactive)
    // ========================================================================
    case 'update_availability':
        $property_id = intval($_POST['property_id'] ?? 0);
        $new_avail = clean_input($_POST['availability_status'] ?? 'AVAILABLE');

        $allowed_avails = ['AVAILABLE', 'VISIT_SCHEDULED', 'RESERVED', 'RENTED_OR_SOLD', 'INACTIVE'];
        if (!in_array($new_avail, $allowed_avails, true)) {
            $new_avail = 'AVAILABLE';
        }

        $stmt = $pdo->prepare("UPDATE customer_properties SET availability_status = ? WHERE id = ? AND customer_id = ?");
        $stmt->execute([$new_avail, $property_id, $customer_id]);

        set_flash('success', 'Property availability status updated.');
        header("Location: " . BASE_URL . "customer/properties.php?tab=my_properties");
        exit;

    // ========================================================================
    // 5. Delete Property Listing (Authorized Owner Only)
    // ========================================================================
    case 'delete_property':
        $property_id = intval($_POST['property_id'] ?? 0);

        $del = $pdo->prepare("DELETE FROM customer_properties WHERE id = ? AND customer_id = ?");
        $del->execute([$property_id, $customer_id]);

        set_flash('info', 'Your property listing has been deleted.');
        header("Location: " . BASE_URL . "customer/properties.php?tab=my_properties");
        exit;

    // ========================================================================
    // 6. Report Suspicious Property (Requirement 7)
    // ========================================================================
    case 'report_property':
        $property_id = intval($_POST['property_id'] ?? 0);
        $reason = clean_input($_POST['reason'] ?? 'Misleading price or fake listing');
        $details = clean_input($_POST['details'] ?? '');

        if ($property_id > 0 && !empty($details)) {
            $ins = $pdo->prepare("INSERT INTO property_reports (property_id, user_id, reason, details) VALUES (?, ?, ?, ?)");
            $ins->execute([$property_id, $user['id'], $reason, $details]);

            set_flash('success', 'Thank you. Your report has been submitted to platform administrators for investigation.');
        }

        header("Location: " . BASE_URL . "property-detail.php?id=" . $property_id);
        exit;

    default:
        header("Location: " . BASE_URL . "customer/properties.php");
        exit;
}
