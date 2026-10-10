<?php
// ============================================================================
// VoltCraft 3D — REST API for Property Operations
// ============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/property-functions.php';

header('Content-Type: application/json; charset=UTF-8');

$action = clean_input($_GET['action'] ?? '');
$pdo = get_db_connection();

switch ($action) {

    // Toggle Bookmark / Save Property
    case 'toggle_save':
        if (!is_logged_in()) {
            json_response('unauthorized', ['message' => 'Please sign in to save properties.'], 401);
        }

        $user = current_user();
        $customer_id = get_customer_id($user['id']);
        if (!$customer_id) {
            json_response('error', ['message' => 'Only customer accounts can save properties.'], 403);
        }

        $property_id = intval($_GET['property_id'] ?? $_POST['property_id'] ?? 0);
        if (!$property_id) {
            json_response('error', ['message' => 'Property ID is required.'], 400);
        }

        $chk = $pdo->prepare("SELECT id FROM property_saved WHERE customer_id = ? AND property_id = ?");
        $chk->execute([$customer_id, $property_id]);
        $existing = $chk->fetch();

        if ($existing) {
            $del = $pdo->prepare("DELETE FROM property_saved WHERE id = ?");
            $del->execute([$existing['id']]);
            json_response('success', ['saved' => false, 'message' => 'Removed from saved properties.']);
        } else {
            $ins = $pdo->prepare("INSERT INTO property_saved (customer_id, property_id) VALUES (?, ?)");
            $ins->execute([$customer_id, $property_id]);
            json_response('success', ['saved' => true, 'message' => 'Property saved to favorites.']);
        }
        break;

    // Log Inquiry (WhatsApp / Phone call)
    case 'log_inquiry':
        $property_id = intval($_GET['property_id'] ?? $_POST['property_id'] ?? 0);
        $channel = clean_input($_GET['channel'] ?? $_POST['channel'] ?? 'WHATSAPP');
        $user_id = is_logged_in() ? $_SESSION['user_id'] : null;

        if ($property_id > 0) {
            log_property_inquiry($property_id, $user_id, $channel, "Inquiry initiated via {$channel}");
            json_response('success', ['logged' => true]);
        }
        json_response('error', ['message' => 'Invalid property ID'], 400);
        break;

    // Fetch quick filter results
    case 'list':
        $city = clean_input($_GET['city'] ?? '');
        $type = clean_input($_GET['type'] ?? '');
        $purpose = clean_input($_GET['purpose'] ?? '');

        $sql = "SELECT id, property_code, title, property_type, listing_type, price, city, primary_image, availability_status 
                FROM customer_properties 
                WHERE verification_status = 'APPROVED'";
        $params = [];

        if (!empty($city)) {
            $sql .= " AND city = ?";
            $params[] = $city;
        }
        if (!empty($type)) {
            $sql .= " AND property_type = ?";
            $params[] = $type;
        }
        if (!empty($purpose)) {
            $sql .= " AND listing_type = ?";
            $params[] = $purpose;
        }
        $sql .= " ORDER BY created_at DESC LIMIT 20";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        json_response('success', ['properties' => $results]);
        break;

    default:
        json_response('error', ['message' => 'Unknown action.'], 400);
        break;
}
