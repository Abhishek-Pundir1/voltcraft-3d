<?php
// ============================================================================
// VoltCraft 3D - API: Customer Profile & Operations
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in() || current_role() !== 'customer') {
    json_response('error', ['message' => 'Customer authorization required.'], 403);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$user_id = $_SESSION['user_id'];
$customer_id = get_customer_id($user_id);

try {
    $pdo = get_db_connection();

    if ($action === 'update_profile') {
        $full_name = trim($input['full_name'] ?? '');
        $phone = trim($input['phone'] ?? '');
        $address = trim($input['address'] ?? '');
        $city = trim($input['city'] ?? '');
        $state = trim($input['state'] ?? '');
        $postal_code = trim($input['postal_code'] ?? '');

        if (empty($full_name)) {
            json_response('error', ['message' => 'Full name cannot be empty.'], 400);
        }

        $pdo->beginTransaction();

        $pdo->prepare("UPDATE users SET full_name = ?, phone = ? WHERE id = ?")
            ->execute([$full_name, $phone, $user_id]);

        $pdo->prepare("UPDATE customers SET address = ?, city = ?, state = ?, postal_code = ? WHERE id = ?")
            ->execute([$address, $city, $state, $postal_code, $customer_id]);

        $pdo->commit();

        $_SESSION['full_name'] = $full_name;

        json_response('success', ['message' => 'Profile updated successfully.']);
    }

    elseif ($action === 'submit_review') {
        $booking_id = intval($input['booking_id'] ?? 0);
        $rating = intval($input['rating'] ?? 5);
        $comment = trim($input['comment'] ?? '');

        if (!$booking_id || empty($comment)) {
            json_response('error', ['message' => 'Booking ID and comment are required.'], 400);
        }

        $b_stmt = $pdo->prepare("SELECT engineer_id, booking_status FROM bookings WHERE id = ? AND customer_id = ?");
        $b_stmt->execute([$booking_id, $customer_id]);
        $booking = $b_stmt->fetch();

        if (!$booking || !in_array($booking['booking_status'], ['COMPLETED', 'COMPLETED_AND_VERIFIED'])) {
            json_response('error', ['message' => 'You can only review completed bookings.'], 400);
        }

        $pdo->prepare("INSERT INTO reviews (booking_id, customer_id, engineer_id, rating, comment) VALUES (?, ?, ?, ?, ?)")
            ->execute([$booking_id, $customer_id, $booking['engineer_id'], min(5, max(1, $rating)), $comment]);

        // Recalculate average rating
        $calc = $pdo->prepare("SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM reviews WHERE engineer_id = ?");
        $calc->execute([$booking['engineer_id']]);
        $stats = $calc->fetch();
        $pdo->prepare("UPDATE engineers SET rating = ?, total_reviews = ? WHERE id = ?")
            ->execute([round($stats['avg_r'], 2), $stats['cnt'], $booking['engineer_id']]);

        json_response('success', ['message' => 'Review submitted successfully!']);
    }

    else {
        json_response('error', ['message' => 'Invalid action.'], 400);
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    error_log("Customer API Error: " . $e->getMessage());
    json_response('error', ['message' => 'Server error: ' . $e->getMessage()], 500);
}
