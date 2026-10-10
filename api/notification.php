<?php
// ============================================================================
// VoltCraft 3D - API: User Notifications
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in()) {
    json_response('error', ['message' => 'Unauthorized.'], 401);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$user_id = $_SESSION['user_id'];

try {
    $pdo = get_db_connection();

    if ($action === 'list') {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
        $stmt->execute([$user_id]);
        $notifs = $stmt->fetchAll();

        json_response('success', [
            'count' => count($notifs),
            'unread' => get_unread_notification_count($user_id),
            'notifications' => $notifs
        ]);
    }

    elseif ($action === 'mark_read') {
        $notif_id = intval($input['id'] ?? 0);
        if ($notif_id > 0) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notif_id, $user_id]);
        } else {
            // Mark all read
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }

        json_response('success', ['message' => 'Notification(s) marked as read.']);
    }

    else {
        json_response('error', ['message' => 'Invalid action.'], 400);
    }

} catch (Exception $e) {
    error_log("Notifications API Error: " . $e->getMessage());
    json_response('error', ['message' => 'Error handling notifications.'], 500);
}
