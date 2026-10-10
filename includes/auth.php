<?php
// ============================================================================
// VoltCraft 3D - Authentication & Authorization Middleware
// ============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Check if a user is currently logged in
 */
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

/**
 * Get the current user's session role
 */
function current_role() {
    return $_SESSION['role'] ?? null;
}

/**
 * Get complete user record
 */
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

/**
 * Get current customer profile ID
 */
function get_customer_id($user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? 0;
    }
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM customers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: null;
}

/**
 * Get current engineer profile ID
 */
function get_engineer_id($user_id = null) {
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? 0;
    }
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM engineers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn() ?: null;
}

/**
 * Enforce authentication
 */
function require_login($redirect_to = 'login.php') {
    if (!is_logged_in()) {
        set_flash('warning', 'Please sign in to access that page.');
        header("Location: " . BASE_URL . $redirect_to);
        exit;
    }
}

/**
 * Enforce role authorization
 * @param string|array $roles Single role or array of allowed roles
 */
function require_role($roles) {
    require_login();
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    if (!in_array(current_role(), $roles, true)) {
        set_flash('danger', 'Access denied. You do not have permission for that area.');
        // Redirect based on actual role
        if (current_role() === 'admin') {
            header("Location: " . BASE_URL . "admin/dashboard.php");
        } elseif (current_role() === 'engineer') {
            header("Location: " . BASE_URL . "engineer/dashboard.php");
        } else {
            header("Location: " . BASE_URL . "customer/dashboard.php");
        }
        exit;
    }
}

/**
 * Establish authenticated session
 */
function login_user($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['avatar'] = $user['avatar'] ?? 'assets/images/default-avatar.png';
}

/**
 * Destroy authenticated session
 */
function logout_user() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}
