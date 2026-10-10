<?php
// ============================================================================
// VoltCraft 3D - OTP Verification & Job Completion Core Functions
// ============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Ensure database schema has OTP tables and columns
 * Automatically runs once and idempotently
 */
function ensure_otp_schema() {
    static $initialized = false;
    if ($initialized) return;

    $pdo = get_db_connection();

    try {
        // 1. Create otp_verifications table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `otp_verifications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `booking_id` INT NOT NULL,
            `customer_id` INT NOT NULL,
            `engineer_id` INT NOT NULL,
            `authorized_admin_id` INT NOT NULL,
            `otp_hash` VARCHAR(255) NOT NULL,
            `plain_otp_enc` VARCHAR(255) DEFAULT NULL,
            `status` VARCHAR(50) NOT NULL DEFAULT 'PENDING',
            `rejection_reason` TEXT DEFAULT NULL,
            `attempts` INT NOT NULL DEFAULT 0,
            `max_attempts` INT NOT NULL DEFAULT 5,
            `delivery_channel` VARCHAR(50) NOT NULL DEFAULT 'IN_APP_SECURE',
            `delivery_recipient` VARCHAR(150) NOT NULL,
            `delivery_status` VARCHAR(50) NOT NULL DEFAULT 'DELIVERED',
            `plain_otp_masked` VARCHAR(20) DEFAULT NULL,
            `expires_at` DATETIME NOT NULL,
            `verified_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX `idx_otp_bk` (`booking_id`),
            INDEX `idx_otp_cust` (`customer_id`),
            INDEX `idx_otp_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 2. Create otp_audit_logs table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS `otp_audit_logs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `booking_id` INT NOT NULL,
            `otp_verification_id` INT DEFAULT NULL,
            `actor_user_id` INT DEFAULT NULL,
            `actor_role` VARCHAR(50) NOT NULL DEFAULT 'system',
            `action` VARCHAR(60) NOT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `details` TEXT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_audit_bk` (`booking_id`),
            INDEX `idx_audit_action` (`action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // 3. Ensure booking_status and status columns in bookings and service_requests can store longer strings
        try {
            $pdo->exec("ALTER TABLE `bookings` MODIFY COLUMN `booking_status` VARCHAR(60) NOT NULL DEFAULT 'CONFIRMED'");
        } catch (PDOException $e) {
            // Already altered or compatible
        }

        try {
            $pdo->exec("ALTER TABLE `service_requests` MODIFY COLUMN `status` VARCHAR(60) NOT NULL DEFAULT 'PENDING'");
        } catch (PDOException $e) {
            // Already altered or compatible
        }

        $initialized = true;
    } catch (PDOException $e) {
        error_log("Schema initialization error: " . $e->getMessage());
    }
}

// Ensure schema is verified on load
ensure_otp_schema();

/**
 * Log an event to otp_audit_logs
 */
function log_otp_audit($booking_id, $verification_id, $actor_user_id, $actor_role, $action, $details = '') {
    try {
        $pdo = get_db_connection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO otp_audit_logs 
            (booking_id, otp_verification_id, actor_user_id, actor_role, action, ip_address, details) 
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$booking_id, $verification_id, $actor_user_id, $actor_role, $action, $ip, $details]);
    } catch (PDOException $e) {
        error_log("Failed to log OTP audit: " . $e->getMessage());
    }
}

/**
 * Dispatch notification to all platform administrators
 */
function notify_all_admins($type, $title, $message, $link = null) {
    try {
        $pdo = get_db_connection();
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($admins as $admin_id) {
            create_notification($admin_id, $type, $title, $message, $link);
        }
    } catch (PDOException $e) {
        error_log("Failed to notify admins: " . $e->getMessage());
    }
}

/**
 * Simple reversible helper for customer-only token display
 * Customer needs to view their own OTP code, but we never store plaintext directly
 */
function encrypt_otp_for_customer($otp, $booking_id) {
    $key = hash('sha256', 'VOLTCRAFT_OTP_KEY_' . $booking_id, true);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($otp, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

function decrypt_otp_for_customer($encrypted_str, $booking_id) {
    try {
        $data = base64_decode($encrypted_str);
        if (!$data || strlen($data) < 17) return false;
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $key = hash('sha256', 'VOLTCRAFT_OTP_KEY_' . $booking_id, true);
        return openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Authorize & Generate OTP (Admin Only)
 * 
 * @param int $booking_id
 * @param int $admin_user_id
 * @param int $expiry_minutes (default 15)
 * @return array Result
 */
function admin_authorize_and_generate_otp($booking_id, $admin_user_id, $expiry_minutes = 15) {
    $pdo = get_db_connection();

    // 1. Fetch booking with customer and engineer info
    $stmt = $pdo->prepare("SELECT b.*, sr.request_code, sr.title as problem_title,
                                  c.user_id as customer_user_id, cust_u.full_name as customer_name,
                                  cust_u.phone as customer_phone, cust_u.email as customer_email,
                                  e.user_id as engineer_user_id, eng_u.full_name as engineer_name
                           FROM bookings b
                           JOIN service_requests sr ON b.request_id = sr.id
                           JOIN customers c ON b.customer_id = c.id
                           JOIN users cust_u ON c.user_id = cust_u.id
                           JOIN engineers e ON b.engineer_id = e.id
                           JOIN users eng_u ON e.user_id = eng_u.id
                           WHERE b.id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        return ['status' => 'error', 'message' => 'Booking not found.'];
    }

    // Only bookings awaiting admin authorization can have OTP generated
    $valid_statuses = ['REPAIR_COMPLETED_AWAITING_ADMIN', 'OTP_EXPIRED', 'OTP_VERIFICATION_PENDING'];
    if (!in_array($booking['booking_status'], $valid_statuses)) {
        return [
            'status' => 'error', 
            'message' => 'Cannot authorize OTP for this booking. Current status is: ' . $booking['booking_status']
        ];
    }

    // 2. Invalidate any existing active OTP for this booking
    $pdo->prepare("UPDATE otp_verifications SET status = 'EXPIRED' WHERE booking_id = ? AND status = 'PENDING'")
        ->execute([$booking_id]);

    // 3. Generate secure 6-digit random OTP
    $raw_otp = (string)random_int(100000, 999999);
    $otp_hash = password_hash($raw_otp, PASSWORD_BCRYPT);
    $masked_otp = '••' . substr($raw_otp, -2);
    $encrypted_for_cust = encrypt_otp_for_customer($raw_otp, $booking_id);

    $expires_at = date('Y-m-d H:i:s', time() + ($expiry_minutes * 60));
    $recipient = trim(($booking['customer_phone'] ?: '') . ' | ' . $booking['customer_email']);

    // 4. Save to otp_verifications
    $ins = $pdo->prepare("INSERT INTO otp_verifications 
        (booking_id, customer_id, engineer_id, authorized_admin_id, otp_hash, plain_otp_enc, status, 
         attempts, max_attempts, delivery_channel, delivery_recipient, delivery_status, plain_otp_masked, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, 'PENDING', 0, 5, 'IN_APP_SECURE_SMS', ?, 'DELIVERED', ?, ?)");
    $ins->execute([
        $booking_id, $booking['customer_id'], $booking['engineer_id'], $admin_user_id,
        $otp_hash, $encrypted_for_cust, $recipient, $masked_otp, $expires_at
    ]);
    $verification_id = $pdo->lastInsertId();

    // 5. Update booking status flow:
    // OTP_GENERATION_AUTHORIZED -> OTP_SENT_TO_CUSTOMER -> OTP_VERIFICATION_PENDING
    $pdo->prepare("UPDATE bookings SET booking_status = 'OTP_VERIFICATION_PENDING' WHERE id = ?")
        ->execute([$booking_id]);
    $pdo->prepare("UPDATE service_requests SET status = 'OTP_VERIFICATION_PENDING' WHERE id = ?")
        ->execute([$booking['request_id']]);

    // 6. Audit logs for state transitions
    log_otp_audit($booking_id, $verification_id, $admin_user_id, 'admin', 'OTP_AUTHORIZED', "Admin authorized OTP generation for booking #{$booking['booking_code']}");
    log_otp_audit($booking_id, $verification_id, $admin_user_id, 'admin', 'OTP_GENERATED', "Secure 6-digit OTP generated (Masked: {$masked_otp}), valid until {$expires_at}");
    log_otp_audit($booking_id, $verification_id, $admin_user_id, 'system', 'OTP_SENT_TO_CUSTOMER', "OTP dispatched to customer via Secure In-App & SMS channel ({$recipient})");

    // 7. Deliver notification directly to customer
    $cust_notif_msg = "🔐 Your Repair Completion OTP is [ {$raw_otp} ]. Share this 6-digit code with engineer {$booking['engineer_name']} ONLY AFTER inspecting your electronics repair. Valid for {$expiry_minutes} minutes.";
    create_notification(
        $booking['customer_user_id'],
        'otp_received',
        '🔐 Repair Completion OTP Code',
        $cust_notif_msg,
        'customer/booking-details.php?id=' . $booking_id
    );

    // 8. Notify engineer that admin authorized OTP and customer has received it
    create_notification(
        $booking['engineer_user_id'],
        'otp_pending',
        'Admin Authorized OTP Generation',
        "Admin authorized repair completion for booking {$booking['booking_code']}. Please obtain the 6-digit OTP from the customer and verify it in your bookings console.",
        'engineer/bookings.php'
    );

    return [
        'status' => 'success',
        'message' => 'OTP generated and securely dispatched to the customer.',
        'verification_id' => $verification_id,
        'masked_otp' => $masked_otp,
        'expires_at' => $expires_at,
        'booking_status' => 'OTP_VERIFICATION_PENDING'
    ];
}

/**
 * Reject OTP Authorization Request (Admin Only)
 */
function admin_reject_otp_authorization($booking_id, $admin_user_id, $reason = '') {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("SELECT b.*, sr.request_code, c.user_id as customer_user_id, e.user_id as engineer_user_id 
                           FROM bookings b 
                           JOIN service_requests sr ON b.request_id = sr.id 
                           JOIN customers c ON b.customer_id = c.id 
                           JOIN engineers e ON b.engineer_id = e.id 
                           WHERE b.id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        return ['status' => 'error', 'message' => 'Booking not found.'];
    }

    $reason_text = clean_input($reason) ?: 'Admin requires additional diagnostic proof or component verification.';

    // Invalidate pending OTPs
    $pdo->prepare("UPDATE otp_verifications SET status = 'REJECTED', rejection_reason = ? WHERE booking_id = ? AND status = 'PENDING'")
        ->execute([$reason_text, $booking_id]);

    // Update booking status
    $pdo->prepare("UPDATE bookings SET booking_status = 'OTP_GENERATION_REJECTED', notes = CONCAT(IFNULL(notes,''), '\n[Admin Rejected OTP]: ', ?) WHERE id = ?")
        ->execute([$reason_text, $booking_id]);
    $pdo->prepare("UPDATE service_requests SET status = 'OTP_GENERATION_REJECTED' WHERE id = ?")
        ->execute([$booking['request_id']]);

    log_otp_audit($booking_id, null, $admin_user_id, 'admin', 'OTP_GENERATION_REJECTED', "Admin rejected OTP request. Reason: {$reason_text}");

    // Notify engineer
    create_notification(
        $booking['engineer_user_id'],
        'otp_rejected',
        'OTP Authorization Declined by Admin',
        "Admin declined completion for booking {$booking['booking_code']}. Reason: {$reason_text}",
        'engineer/bookings.php'
    );

    // Notify customer
    create_notification(
        $booking['customer_user_id'],
        'otp_rejected',
        'Repair Completion Under Admin Review',
        "Repair completion authorization for booking {$booking['booking_code']} was paused by Admin: {$reason_text}",
        'customer/booking-details.php?id=' . $booking_id
    );

    return [
        'status' => 'success',
        'message' => 'OTP generation request rejected successfully.',
        'booking_status' => 'OTP_GENERATION_REJECTED'
    ];
}

/**
 * Verify Customer's OTP (Engineer Only)
 * 
 * @param int $booking_id
 * @param string $entered_otp
 * @param int $engineer_id Profile ID
 * @param int $engineer_user_id User account ID
 * @return array
 */
function verify_engineer_otp($booking_id, $entered_otp, $engineer_id, $engineer_user_id) {
    $pdo = get_db_connection();

    $entered_otp = trim((string)$entered_otp);
    if (strlen($entered_otp) !== 6 || !ctype_digit($entered_otp)) {
        return ['status' => 'error', 'message' => 'Please enter a valid 6-digit numeric OTP.'];
    }

    // 1. Fetch booking and verify engineer ownership
    $stmt = $pdo->prepare("SELECT b.*, sr.request_code, c.user_id as customer_user_id, cust_u.full_name as customer_name,
                                  e.user_id as engineer_user_id, eng_u.full_name as engineer_name
                           FROM bookings b
                           JOIN service_requests sr ON b.request_id = sr.id
                           JOIN customers c ON b.customer_id = c.id
                           JOIN users cust_u ON c.user_id = cust_u.id
                           JOIN engineers e ON b.engineer_id = e.id
                           JOIN users eng_u ON e.user_id = eng_u.id
                           WHERE b.id = ?");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        return ['status' => 'error', 'message' => 'Booking not found.'];
    }

    // Strict access control: Engineer can only verify their own assigned booking
    if ((int)$booking['engineer_id'] !== (int)$engineer_id) {
        log_otp_audit($booking_id, null, $engineer_user_id, 'engineer', 'UNAUTHORIZED_ATTEMPT', 'Engineer tried to verify another engineer booking');
        return ['status' => 'error', 'message' => 'Access denied: You are not assigned to this booking.'];
    }

    // Verify booking is in an OTP pending state
    $allowed_statuses = ['OTP_VERIFICATION_PENDING', 'OTP_SENT_TO_CUSTOMER', 'OTP_GENERATION_AUTHORIZED'];
    if (!in_array($booking['booking_status'], $allowed_statuses)) {
        if ($booking['booking_status'] === 'COMPLETED_AND_VERIFIED' || $booking['booking_status'] === 'COMPLETED') {
            return ['status' => 'error', 'message' => 'This booking has already been verified and completed.'];
        }
        return ['status' => 'error', 'message' => 'Booking is not awaiting OTP verification. Status is: ' . $booking['booking_status']];
    }

    // 2. Fetch active OTP record
    $otp_stmt = $pdo->prepare("SELECT * FROM otp_verifications WHERE booking_id = ? AND status = 'PENDING' ORDER BY id DESC LIMIT 1");
    $otp_stmt->execute([$booking_id]);
    $otp_record = $otp_stmt->fetch();

    if (!$otp_record) {
        return ['status' => 'error', 'message' => 'No active OTP verification found for this booking. Please request Admin to authorize one.'];
    }

    $now = date('Y-m-d H:i:s');

    // 3. Expiration check
    if ($now > $otp_record['expires_at']) {
        $pdo->prepare("UPDATE otp_verifications SET status = 'EXPIRED' WHERE id = ?")->execute([$otp_record['id']]);
        $pdo->prepare("UPDATE bookings SET booking_status = 'OTP_EXPIRED' WHERE id = ?")->execute([$booking_id]);
        $pdo->prepare("UPDATE service_requests SET status = 'OTP_EXPIRED' WHERE id = ?")->execute([$booking['request_id']]);

        log_otp_audit($booking_id, $otp_record['id'], $engineer_user_id, 'engineer', 'OTP_EXPIRED', 'Verification failed: OTP has expired.');

        // Notify Admin of expiration
        notify_all_admins(
            'otp_expired',
            '⚠️ OTP Expired for Booking ' . $booking['booking_code'],
            "OTP for booking {$booking['booking_code']} expired before verification. Engineer {$booking['engineer_name']} or Customer may request regeneration.",
            'admin/otp-verification.php?booking_id=' . $booking_id
        );

        return ['status' => 'error', 'message' => 'The OTP has expired. Please ask Admin to regenerate a new OTP.'];
    }

    // 4. Rate-limit & Attempt check
    if ($otp_record['attempts'] >= $otp_record['max_attempts']) {
        $pdo->prepare("UPDATE otp_verifications SET status = 'FAILED' WHERE id = ?")->execute([$otp_record['id']]);
        $pdo->prepare("UPDATE bookings SET booking_status = 'VERIFICATION_FAILED' WHERE id = ?")->execute([$booking_id]);
        $pdo->prepare("UPDATE service_requests SET status = 'VERIFICATION_FAILED' WHERE id = ?")->execute([$booking['request_id']]);

        log_otp_audit($booking_id, $otp_record['id'], $engineer_user_id, 'engineer', 'VERIFICATION_FAILED', "Max attempt limit ({$otp_record['max_attempts']}) exceeded.");

        // Notify Admin of repeated failures
        notify_all_admins(
            'otp_failed',
            '🚨 OTP Verification Failed (Exceeded Attempts)',
            "Booking {$booking['booking_code']} reached the maximum attempt limit ({$otp_record['max_attempts']}). Status set to VERIFICATION_FAILED.",
            'admin/otp-verification.php?booking_id=' . $booking_id
        );

        return [
            'status' => 'error', 
            'message' => 'Maximum attempt limit exceeded. OTP verification failed. Please contact Admin.'
        ];
    }

    // 5. Secure hash verification
    $is_valid = password_verify($entered_otp, $otp_record['otp_hash']);

    if (!$is_valid) {
        $new_attempts = $otp_record['attempts'] + 1;
        $remaining = $otp_record['max_attempts'] - $new_attempts;

        if ($remaining <= 0) {
            $pdo->prepare("UPDATE otp_verifications SET attempts = ?, status = 'FAILED' WHERE id = ?")->execute([$new_attempts, $otp_record['id']]);
            $pdo->prepare("UPDATE bookings SET booking_status = 'VERIFICATION_FAILED' WHERE id = ?")->execute([$booking_id]);
            $pdo->prepare("UPDATE service_requests SET status = 'VERIFICATION_FAILED' WHERE id = ?")->execute([$booking['request_id']]);

            log_otp_audit($booking_id, $otp_record['id'], $engineer_user_id, 'engineer', 'VERIFICATION_FAILED', "Final attempt incorrect. Max attempts exceeded.");

            notify_all_admins(
                'otp_failed',
                '🚨 OTP Verification Failed for Booking ' . $booking['booking_code'],
                "Engineer entered an incorrect OTP multiple times. Booking {$booking['booking_code']} marked as VERIFICATION_FAILED.",
                'admin/otp-verification.php?booking_id=' . $booking_id
            );

            return [
                'status' => 'error',
                'message' => 'Incorrect OTP code. Maximum attempts reached. Booking marked as VERIFICATION_FAILED.'
            ];
        } else {
            $pdo->prepare("UPDATE otp_verifications SET attempts = ? WHERE id = ?")->execute([$new_attempts, $otp_record['id']]);

            log_otp_audit($booking_id, $otp_record['id'], $engineer_user_id, 'engineer', 'OTP_VERIFY_ATTEMPT_FAILED', "Incorrect OTP entered (Attempt {$new_attempts}/{$otp_record['max_attempts']}).");

            return [
                'status' => 'error',
                'message' => "Incorrect OTP code. Please re-check with the customer. ({$remaining} attempt(s) remaining)."
            ];
        }
    }

    // 6. SUCCESSFUL VERIFICATION!
    $pdo->beginTransaction();
    try {
        // Mark OTP verification as VERIFIED
        $pdo->prepare("UPDATE otp_verifications SET status = 'VERIFIED', verified_at = NOW(), plain_otp_enc = NULL WHERE id = ?")
            ->execute([$otp_record['id']]);

        // Update booking status to COMPLETED_AND_VERIFIED & set completed_at
        $pdo->prepare("UPDATE bookings SET booking_status = 'COMPLETED_AND_VERIFIED', completed_at = NOW() WHERE id = ?")
            ->execute([$booking_id]);

        // Keep service_requests synchronized
        $pdo->prepare("UPDATE service_requests SET status = 'COMPLETED_AND_VERIFIED' WHERE id = ?")
            ->execute([$booking['request_id']]);

        // Log audit event
        log_otp_audit(
            $booking_id,
            $otp_record['id'],
            $engineer_user_id,
            'engineer',
            'OTP_VERIFIED',
            "OTP successfully verified by Engineer {$booking['engineer_name']}. Authorized Admin ID: {$otp_record['authorized_admin_id']}."
        );

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Verification transaction error: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Internal database error during verification.'];
    }

    // 7. Dispatch Notifications
    // Customer Notification
    create_notification(
        $booking['customer_user_id'],
        'repair_verified',
        '✅ Repair Completed & Verified!',
        "Your electronics repair for booking {$booking['booking_code']} has been successfully verified! You may now settle any remaining balance and submit your engineer review.",
        'customer/booking-details.php?id=' . $booking_id
    );

    // Immediate Admin Notification
    notify_all_admins(
        'job_verified',
        '🎉 Repair Verified & Completed: ' . $booking['booking_code'],
        "Engineer {$booking['engineer_name']} successfully verified the OTP with customer {$booking['customer_name']}. Booking {$booking['booking_code']} marked as COMPLETED_AND_VERIFIED.",
        'admin/otp-verification.php?booking_id=' . $booking_id
    );

    return [
        'status' => 'success',
        'message' => 'OTP verified successfully! Job marked as Completed and Verified.',
        'booking_status' => 'COMPLETED_AND_VERIFIED',
        'booking_id' => $booking_id,
        'booking_code' => $booking['booking_code']
    ];
}

/**
 * Fetch active OTP code safely for the customer only
 */
function get_active_otp_for_customer($booking_id, $customer_user_id) {
    $pdo = get_db_connection();

    $stmt = $pdo->prepare("SELECT ov.*, b.customer_id, c.user_id 
                           FROM otp_verifications ov
                           JOIN bookings b ON ov.booking_id = b.id
                           JOIN customers c ON b.customer_id = c.id
                           WHERE ov.booking_id = ? AND ov.status = 'PENDING' AND c.user_id = ?
                           ORDER BY ov.id DESC LIMIT 1");
    $stmt->execute([$booking_id, $customer_user_id]);
    $rec = $stmt->fetch();

    if (!$rec) return null;

    $now = date('Y-m-d H:i:s');
    if ($now > $rec['expires_at']) {
        return null;
    }

    $raw_code = decrypt_otp_for_customer($rec['plain_otp_enc'], $booking_id);
    if (!$raw_code) return null;

    return [
        'code' => $raw_code,
        'expires_at' => $rec['expires_at'],
        'attempts' => $rec['attempts'],
        'max_attempts' => $rec['max_attempts'],
        'created_at' => $rec['created_at']
    ];
}

/**
 * Fetch all audit logs for a booking
 */
function get_booking_otp_audit_logs($booking_id) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT a.*, u.full_name as actor_name, u.role as actor_role_name 
                           FROM otp_audit_logs a 
                           LEFT JOIN users u ON a.actor_user_id = u.id 
                           WHERE a.booking_id = ? 
                           ORDER BY a.created_at ASC");
    $stmt->execute([$booking_id]);
    return $stmt->fetchAll();
}
