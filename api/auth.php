<?php
// ============================================================================
// VoltCraft 3D - API: Authentication & Session Management
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $pdo = get_db_connection();

    // 1. User Login
    if ($action === 'login') {
        $email = trim($input['email'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($email) || empty($password)) {
            json_response('error', ['message' => 'Email and password are required.'], 400);
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            json_response('error', ['message' => 'Invalid email or password.'], 401);
        }

        if ($user['status'] !== 'active') {
            json_response('error', ['message' => 'Your account is ' . $user['status'] . '. Please contact support.'], 403);
        }

        // Establish session
        login_user($user);

        // Determine destination dashboard
        $redirect = 'customer/dashboard.php';
        if ($user['role'] === 'engineer') {
            $redirect = 'engineer/dashboard.php';
        } elseif ($user['role'] === 'admin') {
            $redirect = 'admin/dashboard.php';
        }

        json_response('success', [
            'message' => 'Login successful. Redirecting...',
            'role' => $user['role'],
            'redirect' => BASE_URL . $redirect
        ]);
    }

    // 2. User Registration
    elseif ($action === 'register') {
        $full_name = trim($input['full_name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $password = trim($input['password'] ?? '');
        $role = trim($input['role'] ?? 'customer');

        if (empty($full_name) || empty($email) || empty($password)) {
            json_response('error', ['message' => 'Full name, email, and password are required.'], 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response('error', ['message' => 'Please enter a valid email address.'], 400);
        }

        if (strlen($password) < 6) {
            json_response('error', ['message' => 'Password must be at least 6 characters long.'], 400);
        }

        if (!in_array($role, ['customer', 'engineer'])) {
            $role = 'customer';
        }

        // Check if email already registered
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            json_response('error', ['message' => 'An account with that email already exists.'], 409);
        }

        $pdo->beginTransaction();

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $insert_user = $pdo->prepare("INSERT INTO users (full_name, email, password, phone, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $insert_user->execute([$full_name, $email, $hash, $phone, $role]);
        $new_user_id = $pdo->lastInsertId();

        if ($role === 'engineer') {
            $title = trim($input['title'] ?? 'Certified Electronics Technician');
            $location = trim($input['location'] ?? 'Bangalore, India');
            $hourly_rate = floatval($input['hourly_rate'] ?? 600.00);
            $upi_id = trim($input['upi_id'] ?? 'engineer@okhdfcbank');

            $insert_eng = $pdo->prepare("INSERT INTO engineers (user_id, title, location, hourly_rate, upi_id, is_verified) VALUES (?, ?, ?, ?, ?, 1)");
            $insert_eng->execute([$new_user_id, $title, $location, $hourly_rate, $upi_id]);
            $redirect = 'engineer/dashboard.php';
        } else {
            $address = trim($input['address'] ?? '');
            $city = trim($input['city'] ?? 'Bangalore');

            $insert_cust = $pdo->prepare("INSERT INTO customers (user_id, address, city) VALUES (?, ?, ?)");
            $insert_cust->execute([$new_user_id, $address, $city]);
            $redirect = 'customer/dashboard.php';
        }

        $pdo->commit();

        // Auto login
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$new_user_id]);
        $user = $stmt->fetch();
        login_user($user);

        // Welcome Notification
        create_notification($new_user_id, 'welcome', 'Welcome to VoltCraft 3D!', 'Your account has been created. Explore verified engineers and 3D diagnostics.', $redirect);

        json_response('success', [
            'message' => 'Registration successful! Welcome aboard.',
            'redirect' => BASE_URL . $redirect
        ]);
    }

    // 3. Me
    elseif ($action === 'me') {
        $user = current_user();
        if (!$user) {
            json_response('error', ['authenticated' => false], 401);
        }
        json_response('success', [
            'authenticated' => true,
            'user' => [
                'id' => $user['id'],
                'full_name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'avatar' => get_avatar_url($user['avatar'], $user['full_name'])
            ]
        ]);
    }

    else {
        json_response('error', ['message' => 'Invalid action.'], 400);
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Auth API Error: " . $e->getMessage());
    json_response('error', ['message' => 'A server error occurred. Please try again.'], 500);
}
