<?php
// ============================================================================
// VoltCraft 3D - API: Analytics & Reporting Stats
// ============================================================================

header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!is_logged_in() || current_role() !== 'admin') {
    json_response('error', ['message' => 'Admin authorization required.'], 403);
}

try {
    $pdo = get_db_connection();

    // Key metrics
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_engineers = $pdo->query("SELECT COUNT(*) FROM engineers")->fetchColumn();
    $total_customers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $total_bookings = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $total_revenue = $pdo->query("SELECT IFNULL(SUM(amount), 0) FROM payments WHERE payment_status = 'SUCCESS'")->fetchColumn();
    $pending_requests = $pdo->query("SELECT COUNT(*) FROM service_requests WHERE status = 'PENDING'")->fetchColumn();

    // Booking Status Distribution
    $status_stmt = $pdo->query("SELECT booking_status, COUNT(*) as count FROM bookings GROUP BY booking_status");
    $statuses = $status_stmt->fetchAll();

    // Services Popularity
    $serv_stmt = $pdo->query("SELECT s.name, COUNT(b.id) as booking_count 
                              FROM services s 
                              LEFT JOIN bookings b ON s.id = b.service_id 
                              GROUP BY s.id ORDER BY booking_count DESC LIMIT 5");
    $top_services = $serv_stmt->fetchAll();

    json_response('success', [
        'kpis' => [
            'total_users' => (int)$total_users,
            'total_engineers' => (int)$total_engineers,
            'total_customers' => (int)$total_customers,
            'total_bookings' => (int)$total_bookings,
            'total_revenue' => (float)$total_revenue,
            'total_revenue_formatted' => format_currency($total_revenue),
            'pending_requests' => (int)$pending_requests
        ],
        'status_distribution' => $statuses,
        'top_services' => $top_services
    ]);

} catch (Exception $e) {
    error_log("Stats API Error: " . $e->getMessage());
    json_response('error', ['message' => 'Error retrieving system statistics.'], 500);
}
