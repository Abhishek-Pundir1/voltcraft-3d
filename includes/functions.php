<?php
// ============================================================================
// VoltCraft 3D - Global Helper & Utility Functions
// ============================================================================

require_once __DIR__ . '/../config/database.php';

/**
 * Escape HTML output to prevent XSS
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize text input
 */
function clean_input($data) {
    if (is_array($data)) {
        return array_map('clean_input', $data);
    }
    $data = trim($data ?? '');
    return stripslashes($data);
}

/**
 * JSON Response Helper for REST APIs
 */
function json_response($status = 'success', $data = [], $http_code = 200) {
    http_response_code($http_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'status' => $status,
        'timestamp' => time(),
        'data' => $data
    ]);
    exit;
}

/**
 * Format currency amount with symbol
 */
function format_currency($amount) {
    return CURRENCY_SYMBOL . ' ' . number_format((float)$amount, 2);
}

/**
 * Format date nicely
 */
function format_date($datetime, $format = 'd M Y, h:i A') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

/**
 * Human friendly relative time
 */
function time_ago($datetime) {
    if (!$datetime) return 'Just now';
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' mins ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hrs ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('d M Y', $time);
}

/**
 * Generate CSRF Token
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash messaging system
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Insert in-app notification
 */
function create_notification($user_id, $type, $title, $message, $link = null) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $type, $title, $message, $link]);
    } catch (PDOException $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Count unread notifications
 */
function get_unread_notification_count($user_id) {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * HTML Badge for Booking / Request Statuses
 */
function render_status_badge($status) {
    $status = strtoupper((string)$status);
    $badges = [
        // Requests & Repair Workflow
        'PENDING'                         => ['class' => 'badge-warning', 'label' => 'Pending Review', 'icon' => 'clock'],
        'REQUESTED'                       => ['class' => 'badge-warning', 'label' => 'Requested', 'icon' => 'clock'],
        'ACCEPTED'                        => ['class' => 'badge-info', 'label' => 'Accepted (Pay Advance)', 'icon' => 'check-circle'],
        'ACCEPTED_BY_ENGINEER'            => ['class' => 'badge-info', 'label' => 'Accepted by Engineer', 'icon' => 'check-circle'],
        'PAYMENT_PENDING'                 => ['class' => 'badge-warning', 'label' => 'Payment Pending', 'icon' => 'credit-card'],
        'CONFIRMED'                       => ['class' => 'badge-primary', 'label' => 'Booking Confirmed', 'icon' => 'shield-check'],
        'IN_PROGRESS'                     => ['class' => 'badge-accent', 'label' => 'In Progress', 'icon' => 'tool'],
        'REPAIR_IN_PROGRESS'              => ['class' => 'badge-accent', 'label' => 'Repair In Progress', 'icon' => 'tool'],
        'REPAIR_COMPLETED_AWAITING_ADMIN' => ['class' => 'badge-warning', 'label' => 'Repair Completed - Awaiting Admin Authorization', 'icon' => 'shield-alert'],
        'OTP_GENERATION_AUTHORIZED'       => ['class' => 'badge-info', 'label' => 'OTP Authorized', 'icon' => 'key'],
        'OTP_SENT_TO_CUSTOMER'            => ['class' => 'badge-info', 'label' => 'OTP Sent to Customer', 'icon' => 'send'],
        'OTP_VERIFICATION_PENDING'        => ['class' => 'badge-warning', 'label' => 'OTP Verification Pending', 'icon' => 'key-round'],
        'COMPLETED_AND_VERIFIED'          => ['class' => 'badge-success', 'label' => 'Completed & Verified', 'icon' => 'shield-check'],
        'COMPLETED'                       => ['class' => 'badge-success', 'label' => 'Completed', 'icon' => 'check-double'],
        // Rejections & Failures
        'REJECTED'                        => ['class' => 'badge-danger', 'label' => 'Request Rejected', 'icon' => 'x-circle'],
        'REQUEST_REJECTED'                => ['class' => 'badge-danger', 'label' => 'Request Rejected', 'icon' => 'x-circle'],
        'OTP_GENERATION_REJECTED'         => ['class' => 'badge-danger', 'label' => 'OTP Generation Rejected', 'icon' => 'x-octagon'],
        'OTP_EXPIRED'                     => ['class' => 'badge-muted', 'label' => 'OTP Expired', 'icon' => 'clock'],
        'VERIFICATION_FAILED'             => ['class' => 'badge-danger', 'label' => 'Verification Failed', 'icon' => 'alert-triangle'],
        'CANCELLED'                       => ['class' => 'badge-muted', 'label' => 'Cancelled', 'icon' => 'slash'],
        // Payments
        'ADVANCE_PAID'                    => ['class' => 'badge-success', 'label' => '20% Advance Paid', 'icon' => 'check'],
        'FULLY_PAID'                      => ['class' => 'badge-success', 'label' => 'Fully Paid', 'icon' => 'check-all'],
        'SUCCESS'                         => ['class' => 'badge-success', 'label' => 'Success', 'icon' => 'check'],
        'FAILED'                          => ['class' => 'badge-danger', 'label' => 'Failed', 'icon' => 'alert-triangle'],
        // Availability
        'AVAILABLE'                       => ['class' => 'badge-success', 'label' => 'Available Now', 'icon' => 'radio'],
        'BUSY'                            => ['class' => 'badge-warning', 'label' => 'Currently Busy', 'icon' => 'pause-circle'],
        'OFFLINE'                         => ['class' => 'badge-muted', 'label' => 'Offline', 'icon' => 'moon']
    ];

    $cfg = $badges[$status] ?? ['class' => 'badge-secondary', 'label' => str_replace('_', ' ', $status), 'icon' => 'info'];
    return '<span class="status-badge ' . e($cfg['class']) . '"><span class="badge-dot"></span>' . e($cfg['label']) . '</span>';
}

/**
 * Generate Unique Identifiers
 */
function generate_code($prefix = 'REQ') {
    return $prefix . '-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Robust Avatar URL helper with SVG data-URI fallback
 */
function get_avatar_url($avatar_path, $name = 'User') {
    if (!empty($avatar_path) && file_exists(__DIR__ . '/../' . ltrim($avatar_path, '/'))) {
        return BASE_URL . ltrim($avatar_path, '/');
    }
    // High-tech stylized SVG initials badge
    $initials = strtoupper(substr(trim($name ?: 'VC'), 0, 2));
    $hash = md5($name);
    $hue1 = hexdec(substr($hash, 0, 2)) % 360;
    $hue2 = ($hue1 + 45) % 360;
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">
      <defs>
        <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stop-color="hsl(' . $hue1 . ', 85%, 55%)"/>
          <stop offset="100%" stop-color="hsl(' . $hue2 . ', 85%, 45%)"/>
        </linearGradient>
      </defs>
      <circle cx="50" cy="50" r="50" fill="url(#g)"/>
      <circle cx="50" cy="50" r="46" fill="none" stroke="rgba(255,255,255,0.25)" stroke-width="2"/>
      <text x="50" y="58" font-family="system-ui, sans-serif" font-weight="bold" font-size="34" fill="#ffffff" text-anchor="middle" dominant-baseline="middle">' . e($initials) . '</text>
    </svg>';
    return 'data:image/svg+xml;utf8,' . rawurlencode($svg);
}

/**
 * Robust UPI QR Code helper
 */
function get_upi_qr_url($qr_path, $upi_id = '') {
    if (!empty($qr_path) && file_exists(__DIR__ . '/../' . ltrim($qr_path, '/'))) {
        return BASE_URL . ltrim($qr_path, '/');
    }
    // Return standard QR code SVG
    return BASE_URL . 'assets/images/qr/qr1.svg';
}

