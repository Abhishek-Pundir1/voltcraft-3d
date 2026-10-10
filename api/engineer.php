<?php
// ============================================================================
// VoltCraft 3D - API: Engineers Directory, Search, Filter & Profile
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'filter';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    $pdo = get_db_connection();

    // 1. Dynamic Search & Multi-criteria Filtering
    if ($action === 'filter') {
        $q = trim($_GET['q'] ?? '');
        $service_id = intval($_GET['service_id'] ?? 0);
        $skill = trim($_GET['skill'] ?? '');
        $location = trim($_GET['location'] ?? '');
        $min_rating = floatval($_GET['min_rating'] ?? 0);
        $availability = trim($_GET['availability'] ?? '');
        $sort = trim($_GET['sort'] ?? 'rating_desc');

        $sql = "SELECT e.*, u.full_name, u.email, u.phone, u.avatar,
                       GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') AS services_offered,
                       GROUP_CONCAT(DISTINCT es.skill_name SEPARATOR ', ') AS skills_list
                FROM engineers e
                JOIN users u ON e.user_id = u.id
                LEFT JOIN engineer_services esv ON e.id = esv.engineer_id
                LEFT JOIN services s ON esv.service_id = s.id
                LEFT JOIN engineer_skills es ON e.id = es.engineer_id
                WHERE u.status = 'active'";0

        $params = [];

        if (!empty($q)) {
            $sql .= " AND (u.full_name LIKE ? OR e.title LIKE ? OR e.bio LIKE ? OR e.location LIKE ?)";
            $term = "%$q%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            
        }
        if ($service_id > 0) {
            $sql .= " AND e.id IN (SELECT engineer_id FROM engineer_services WHERE service_id = ?)";
            $params[] = $service_id;
        }

        if (!empty($skill)) {
            $sql .= " AND e.id IN (SELECT engineer_id FROM engineer_skills WHERE skill_name LIKE ?)";
            $params[] = "%$skill%";
        }

        if (!empty($location)) {
            $sql .= " AND e.location LIKE ?";
            $params[] = "%$location%";
        }

        if ($min_rating > 0) {
            $sql .= " AND e.rating >= ?";
            $params[] = $min_rating;
        }

        if (!empty($availability)) {
            $sql .= " AND e.availability_status = ?";
            $params[] = $availability;
        }

        $sql .= " GROUP BY e.id";

        // Sorting
        switch ($sort) {
            case 'price_asc':
                $sql .= " ORDER BY e.hourly_rate ASC";
                break;
            case 'price_desc':
                $sql .= " ORDER BY e.hourly_rate DESC";
                break;
            case 'experience_desc':
                $sql .= " ORDER BY e.experience_years DESC";
                break;
            case 'reviews_desc':
                $sql .= " ORDER BY e.total_reviews DESC";
                break;
            case 'rating_desc':
            default:
                $sql .= " ORDER BY e.rating DESC, e.total_reviews DESC";
                break;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $engineers = $stmt->fetchAll();

        // Format result items
        $results = array_map(function($eng) {
            return [
                'id' => (int)$eng['id'],
                'user_id' => (int)$eng['user_id'],
                'name' => $eng['full_name'],
                'title' => $eng['title'],
                'bio' => $eng['bio'],
                'location' => $eng['location'],
                'experience' => (int)$eng['experience_years'],
                'qualification' => $eng['qualification'],
                'rating' => (float)$eng['rating'],
                'total_reviews' => (int)$eng['total_reviews'],
                'hourly_rate' => (float)$eng['hourly_rate'],
                'hourly_rate_formatted' => format_currency($eng['hourly_rate']),
                'availability' => $eng['availability_status'],
                'is_verified' => (bool)$eng['is_verified'],
                'upi_id' => $eng['upi_id'],
                'avatar_url' => get_avatar_url($eng['avatar'], $eng['full_name']),
                'services' => $eng['services_offered'] ? explode(', ', $eng['services_offered']) : [],
                'skills' => $eng['skills_list'] ? explode(', ', $eng['skills_list']) : [],
                'profile_url' => BASE_URL . 'engineer-detail.php?id=' . $eng['id']
            ];
        }, $engineers);

        json_response('success', [
            'count' => count($results),
            'engineers' => $results
        ]);
    }

    // 2. Toggle Availability
    elseif ($action === 'toggle_availability') {
        if (!is_logged_in() || current_role() !== 'engineer') {
            json_response('error', ['message' => 'Unauthorized.'], 403);
        }

        $eng_id = get_engineer_id($_SESSION['user_id']);
        if (!$eng_id) {
            json_response('error', ['message' => 'Engineer profile not found.'], 404);
        }

        $status = trim($input['status'] ?? '');
        if (!in_array($status, ['available', 'busy', 'offline'])) {
            // Auto toggle between available and busy
            $stmt = $pdo->prepare("SELECT availability_status FROM engineers WHERE id = ?");
            $stmt->execute([$eng_id]);
            $current = $stmt->fetchColumn();
            $status = ($current === 'available') ? 'busy' : 'available';
        }

        $stmt = $pdo->prepare("UPDATE engineers SET availability_status = ? WHERE id = ?");
        $stmt->execute([$status, $eng_id]);

        json_response('success', [
            'message' => 'Availability updated to ' . ucfirst($status),
            'status' => $status
        ]);
    }

    else {
        json_response('error', ['message' => 'Unknown action.'], 400);
    }

} catch (Exception $e) {
    error_log("Engineer API Error: " . $e->getMessage());
    json_response('error', ['message' => 'An error occurred fetching engineers.'], 500);
}
