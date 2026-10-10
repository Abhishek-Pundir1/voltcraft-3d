<?php
// ============================================================================
// VoltCraft 3D - Database Configuration & Global Constants
// ============================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Credentials (env vars for cloud deploy, fallback for local XAMPP)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'voltcraft_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_CHARSET', 'utf8mb4');

// Application Details
define('APP_NAME', 'VoltCraft 3D');
define('APP_TAGLINE', 'Next-Gen 3D Electronics Engineering & Service Platform');
define('CURRENCY_SYMBOL', '₹');
define('ADVANCE_PERCENTAGE', 0.20); // 20% Advance Booking Fee

// Dynamic Base URL Detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Determine the script directory relative to document root
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$appDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
$relativePath = str_replace($docRoot, '', $appDir);
$relativePath = '/' . trim($relativePath, '/');
if ($relativePath === '/') {
    $relativePath = '';
}

define('BASE_PATH', $relativePath);
define('BASE_URL', rtrim($protocol . $host . $relativePath, '/') . '/');

/**
 * Get PDO Database Connection
 * @return PDO
 */
function get_db_connection() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Avoid leaking sensitive credentials on failure
            error_log("Database connection error: " . $e->getMessage());
            die('<div style="font-family:sans-serif;padding:30px;background:#0b0f19;color:#fff;border-radius:12px;margin:50px auto;max-width:600px;border:1px solid #ff4444;">
                <h2 style="color:#ff5555;margin-top:0;">⚡ Database Connection Error</h2>
                <p>Could not connect to the VoltCraft MySQL database (<code>voltcraft_db</code>).</p>
                <p>Please ensure XAMPP MySQL is running and that <code>database.sql</code> has been imported.</p>
                <p style="font-size:12px;color:#888;">Error details: ' . htmlspecialchars($e->getMessage()) . '</p>
            </div>');
        }
    }
    return $pdo;
}
