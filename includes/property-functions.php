<?php
// ============================================================================
// VoltCraft 3D — Customer-to-Customer Property Module Functions & Schema
// ============================================================================

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Automatically ensure C2C Property tables exist in the database
 */
function init_property_tables() {
    static $initialized = false;
    if ($initialized) return;

    $pdo = get_db_connection();

    // 1. customer_properties table
    $pdo->exec("CREATE TABLE IF NOT EXISTS `customer_properties` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `property_code` VARCHAR(30) NOT NULL UNIQUE,
      `customer_id` INT NOT NULL,
      `title` VARCHAR(255) NOT NULL,
      `property_type` ENUM('flat', 'apartment', 'independent_house', 'studio', 'villa', 'commercial') NOT NULL DEFAULT 'flat',
      `listing_type` ENUM('rent', 'sale') NOT NULL DEFAULT 'rent',
      `price` DECIMAL(12,2) NOT NULL,
      `security_deposit` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
      `advance_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
      `address` TEXT NOT NULL,
      `city` VARCHAR(100) NOT NULL,
      `state` VARCHAR(100) NOT NULL,
      `postal_code` VARCHAR(20) DEFAULT NULL,
      `bedrooms` INT DEFAULT 1,
      `bathrooms` INT DEFAULT 1,
      `area_sqft` INT DEFAULT NULL,
      `facilities` TEXT DEFAULT NULL,
      `images` TEXT DEFAULT NULL,
      `primary_image` VARCHAR(255) DEFAULT NULL,
      `description` TEXT NOT NULL,
      `whatsapp_number` VARCHAR(30) NOT NULL,
      `allow_direct_calls` TINYINT(1) NOT NULL DEFAULT 1,
      `phone_number` VARCHAR(30) DEFAULT NULL,
      `cancellation_policy` TEXT DEFAULT NULL,
      `verification_status` ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
      `verification_notes` TEXT DEFAULT NULL,
      `availability_status` ENUM('AVAILABLE', 'VISIT_SCHEDULED', 'RESERVED', 'RENTED_OR_SOLD', 'INACTIVE') NOT NULL DEFAULT 'AVAILABLE',
      `views_count` INT NOT NULL DEFAULT 0,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_prop_cust` (`customer_id`),
      INDEX `idx_prop_status` (`availability_status`),
      INDEX `idx_prop_verify` (`verification_status`),
      INDEX `idx_prop_city` (`city`),
      INDEX `idx_prop_type` (`property_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 2. property_bookings table (C2C Booking & Visit Requests)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `property_bookings` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `booking_code` VARCHAR(30) NOT NULL UNIQUE,
      `property_id` INT NOT NULL,
      `requester_customer_id` INT NOT NULL,
      `owner_customer_id` INT NOT NULL,
      `request_type` ENUM('visit', 'rent_booking', 'purchase_offer') NOT NULL DEFAULT 'visit',
      `scheduled_visit_date` DATE DEFAULT NULL,
      `scheduled_visit_time` VARCHAR(50) DEFAULT NULL,
      `message` TEXT NOT NULL,
      `offered_price` DECIMAL(12,2) DEFAULT NULL,
      `advance_amount` DECIMAL(12,2) DEFAULT 0.00,
      `security_deposit` DECIMAL(12,2) DEFAULT 0.00,
      `status` ENUM('Request Submitted', 'Pending Owner Approval', 'Accepted', 'Rejected', 'Visit Scheduled', 'Reserved', 'Rented or Sold', 'Cancelled') NOT NULL DEFAULT 'Request Submitted',
      `owner_response` TEXT DEFAULT NULL,
      `rejection_reason` TEXT DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_pbk_prop` (`property_id`),
      INDEX `idx_pbk_req` (`requester_customer_id`),
      INDEX `idx_pbk_owner` (`owner_customer_id`),
      INDEX `idx_pbk_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. property_saved table (Customer Bookmarks)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `property_saved` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `customer_id` INT NOT NULL,
      `property_id` INT NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY `uk_saved_prop` (`customer_id`, `property_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. property_reports table (Safety, misleading price & suspicious reports)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `property_reports` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `property_id` INT NOT NULL,
      `user_id` INT NOT NULL,
      `reason` VARCHAR(150) NOT NULL,
      `details` TEXT NOT NULL,
      `status` ENUM('PENDING', 'REVIEWED', 'DISMISSED', 'ACTIONED') NOT NULL DEFAULT 'PENDING',
      `admin_notes` TEXT DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_prep_prop` (`property_id`),
      INDEX `idx_prep_status` (`status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. property_inquiries table (Interaction history / WhatsApp tracking)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `property_inquiries` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `property_id` INT NOT NULL,
      `sender_user_id` INT DEFAULT NULL,
      `channel` ENUM('WHATSAPP', 'CALL', 'WEB_INQUIRY') NOT NULL DEFAULT 'WHATSAPP',
      `message_summary` TEXT DEFAULT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_pinq_prop` (`property_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Seed realistic sample C2C properties if table is empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM customer_properties")->fetchColumn();
    if ($count === 0) {
        seed_customer_properties($pdo);
    }

    $initialized = true;
}

/**
 * Seed initial realistic customer properties
 */
function seed_customer_properties($pdo) {
    // Check if customer 1 (Rohan Mehta) and customer 2 (Neha Kapoor) exist
    $cust1 = (int)$pdo->query("SELECT id FROM customers LIMIT 1")->fetchColumn();
    $cust2 = (int)$pdo->query("SELECT id FROM customers WHERE id != " . ($cust1 ?: 0) . " LIMIT 1")->fetchColumn();
    if (!$cust1) return;
    if (!$cust2) $cust2 = $cust1;

    $properties = [
        [
            'property_code' => 'PROP-101',
            'customer_id' => $cust1,
            'title' => 'Luxury 2BHK High-Rise Flat with Smart Home Automation',
            'property_type' => 'flat',
            'listing_type' => 'rent',
            'price' => 38000.00,
            'security_deposit' => 100000.00,
            'advance_amount' => 15000.00,
            'address' => 'Tower C, 14th Floor, Skyline Grand, 100 Feet Road, Indiranagar',
            'city' => 'Bangalore',
            'state' => 'Karnataka',
            'postal_code' => '560038',
            'bedrooms' => 2,
            'bathrooms' => 2,
            'area_sqft' => 1250,
            'facilities' => json_encode(['High-Speed Fiber WiFi', 'Air Conditioning', 'Covered Car Parking', '100% Power Backup', 'Fully Furnished', 'Modular Kitchen', 'Balcony Garden', '24/7 Security CCTV', 'Clubhouse Gym', 'Elevator Access']),
            'images' => json_encode([
                'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80',
                'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=1000&q=80',
                'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1000&q=80'
            ]),
            'primary_image' => 'https://images.unsplash.com/photo-1545324418-cc1a3fa10c00?auto=format&fit=crop&w=1000&q=80',
            'description' => 'Beautifully designed 2BHK flat available for rent directly from owner. Fitted with high-end IoT lighting, motorized blinds, modular kitchen with chimney, and premium wooden flooring. Overlooking the lush defence greenery in Indiranagar. Looking for working professionals or families.',
            'whatsapp_number' => '+919886011223',
            'allow_direct_calls' => 1,
            'phone_number' => '+91 98860 11223',
            'cancellation_policy' => 'Full advance deposit refund if visit or booking is cancelled at least 48 hours before scheduled agreement date. 1 month notice period required before move-out.',
            'verification_status' => 'APPROVED',
            'availability_status' => 'AVAILABLE',
            'views_count' => 142
        ],
        [
            'property_code' => 'PROP-102',
            'customer_id' => $cust2,
            'title' => 'Sea-Facing 3BHK Executive Apartment in Bandra West',
            'property_type' => 'apartment',
            'listing_type' => 'rent',
            'price' => 85000.00,
            'security_deposit' => 250000.00,
            'advance_amount' => 25000.00,
            'address' => 'Flat 12B, Sea Crest Towers, Carter Road, Bandra West',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400050',
            'bedrooms' => 3,
            'bathrooms' => 3,
            'area_sqft' => 1650,
            'facilities' => json_encode(['Sea View Balcony', 'High-Speed Fiber WiFi', 'Air Conditioning in All Rooms', '2 Covered Car Parkings', 'Italian Marble Flooring', 'Gated Security 24/7', 'Power Backup', 'Swimming Pool', 'Private Lift']),
            'images' => json_encode([
                'https://images.unsplash.com/photo-1512918728675-ed5a9ecdebfd?auto=format&fit=crop&w=1000&q=80',
                'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1000&q=80',
                'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1000&q=80'
            ]),
            'primary_image' => 'https://images.unsplash.com/photo-1512918728675-ed5a9ecdebfd?auto=format&fit=crop&w=1000&q=80',
            'description' => 'Direct owner listing: Prime 3BHK sea-facing apartment on Carter Road with breathtaking Arabian Sea sunset views. Cross-ventilation, Italian marble, spacious master bedroom with walk-in wardrobe, and quiet gated society. Zero brokerage.',
            'whatsapp_number' => '+919820044556',
            'allow_direct_calls' => 1,
            'phone_number' => '+91 98200 44556',
            'cancellation_policy' => '100% refund of advance fee if reservation is cancelled within 72 hours of request submission. Security deposit returned via direct bank transfer at lease conclusion.',
            'verification_status' => 'APPROVED',
            'availability_status' => 'AVAILABLE',
            'views_count' => 285
        ],
        [
            'property_code' => 'PROP-103',
            'customer_id' => $cust1,
            'title' => 'Minimalist Studio Apartment near Hitec City Tech Hub',
            'property_type' => 'studio',
            'listing_type' => 'rent',
            'price' => 22000.00,
            'security_deposit' => 50000.00,
            'advance_amount' => 5000.00,
            'address' => 'Plot 45, Silicon Enclave, Madhapur',
            'city' => 'Hyderabad',
            'state' => 'Telangana',
            'postal_code' => '500081',
            'bedrooms' => 1,
            'bathrooms' => 1,
            'area_sqft' => 550,
            'facilities' => json_encode(['High-Speed Fiber WiFi', 'Dedicated Work Desk', 'Smart TV', 'Air Conditioning', 'Kitchenette with Microwave', '24/7 Security CCTV', 'Two Wheeler Parking', 'Water Purifier']),
            'images' => json_encode([
                'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1000&q=80',
                'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=1000&q=80'
            ]),
            'primary_image' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=1000&q=80',
            'description' => 'Ideal for remote tech professionals, software developers, and research fellows. Fully furnished plug-and-play studio with ergonomic workstation, 300 Mbps fiber line, kitchenette, and queen-size bed. 5 minutes from Cyber Towers.',
            'whatsapp_number' => '+919886011223',
            'allow_direct_calls' => 1,
            'phone_number' => '+91 98860 11223',
            'cancellation_policy' => 'Flexible cancellation: Full refund before physical key handover. Monthly rolling agreement with 15 days notice.',
            'verification_status' => 'APPROVED',
            'availability_status' => 'AVAILABLE',
            'views_count' => 97
        ],
        [
            'property_code' => 'PROP-104',
            'customer_id' => $cust2,
            'title' => 'Independent 4BHK Duplex Villa with Private Solar Rooftop',
            'property_type' => 'villa',
            'listing_type' => 'sale',
            'price' => 24500000.00,
            'security_deposit' => 500000.00,
            'advance_amount' => 100000.00,
            'address' => 'Villa 18, Green Meadows Estate, Golf Course Extension Rd',
            'city' => 'Delhi NCR',
            'state' => 'Haryana',
            'postal_code' => '122001',
            'bedrooms' => 4,
            'bathrooms' => 5,
            'area_sqft' => 3400,
            'facilities' => json_encode(['Private Garden', '5kW Solar System Installed', '2 Covered Car Garage', 'EV Charging Station', 'Home Theater Room', 'Servant Quarters', 'Gated Community', 'Swimming Pool', '24/7 Security CCTV']),
            'images' => json_encode([
                'https://images.unsplash.com/photo-1613977257363-707ba9348227?auto=format&fit=crop&w=1000&q=80',
                'https://images.unsplash.com/photo-1600585154526-990dced4db0d?auto=format&fit=crop&w=1000&q=80'
            ]),
            'primary_image' => 'https://images.unsplash.com/photo-1613977257363-707ba9348227?auto=format&fit=crop&w=1000&q=80',
            'description' => 'Direct from owner for sale: Brand new 4BHK luxury duplex villa with rooftop solar power station, private lawn, and double-height ceiling living room. Freehold registry title ready with clear legal chain. Serious buyers welcome for weekend site inspection.',
            'whatsapp_number' => '+919820044556',
            'allow_direct_calls' => 1,
            'phone_number' => '+91 98200 44556',
            'cancellation_policy' => 'Booking token is 100% refundable if bank title search or legal title verification reveals encumbrances within 30 days.',
            'verification_status' => 'APPROVED',
            'availability_status' => 'AVAILABLE',
            'views_count' => 312
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO `customer_properties` 
      (`property_code`, `customer_id`, `title`, `property_type`, `listing_type`, `price`, `security_deposit`, `advance_amount`, 
       `address`, `city`, `state`, `postal_code`, `bedrooms`, `bathrooms`, `area_sqft`, `facilities`, `images`, `primary_image`, 
       `description`, `whatsapp_number`, `allow_direct_calls`, `phone_number`, `cancellation_policy`, `verification_status`, 
       `availability_status`, `views_count`) 
      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($properties as $p) {
        $stmt->execute([
            $p['property_code'], $p['customer_id'], $p['title'], $p['property_type'], $p['listing_type'],
            $p['price'], $p['security_deposit'], $p['advance_amount'], $p['address'], $p['city'], $p['state'],
            $p['postal_code'], $p['bedrooms'], $p['bathrooms'], $p['area_sqft'], $p['facilities'], $p['images'],
            $p['primary_image'], $p['description'], $p['whatsapp_number'], $p['allow_direct_calls'], $p['phone_number'],
            $p['cancellation_policy'], $p['verification_status'], $p['availability_status'], $p['views_count']
        ]);
    }
}

// Ensure tables exist on include
init_property_tables();

/**
 * Generate a unique Property ID (e.g. PROP-105, PROP-106)
 */
function generate_property_code() {
    $pdo = get_db_connection();
    $maxId = (int)$pdo->query("SELECT MAX(id) FROM customer_properties")->fetchColumn();
    $next = max(101, $maxId + 101);
    $code = 'PROP-' . $next;
    
    // Ensure uniqueness
    $stmt = $pdo->prepare("SELECT id FROM customer_properties WHERE property_code = ?");
    $stmt->execute([$code]);
    if ($stmt->fetch()) {
        $code = 'PROP-' . ($next + rand(1, 99));
    }
    return $code;
}

/**
 * Validate WhatsApp number and format for wa.me API
 */
function sanitize_whatsapp_number($phone) {
    if (empty($phone)) return '';
    // Strip everything except digits
    $digits = preg_replace('/[^0-9]/', '', $phone);
    // If standard 10 digit Indian number without country code, prepend 91
    if (strlen($digits) === 10) {
        $digits = '91' . $digits;
    }
    // Must have at least 10 digits
    if (strlen($digits) < 10) {
        return '';
    }
    return $digits;
}

/**
 * Generate direct WhatsApp chat URL with pre-filled message template
 * 
 * Template:
 * "Hello, I found your property on the website.
 * Property ID: PROP-102
 * I am interested in your flat.
 * Is it available for rent or booking?
 * Please share the complete details."
 */
function get_property_whatsapp_url($rawPhone, $propertyCode, $propertyName = '', $location = '', $customInquiry = '') {
    $cleanPhone = sanitize_whatsapp_number($rawPhone);
    if (empty($cleanPhone)) {
        return null;
    }

    $msg = "Hello, I found your property on the website.\n" .
           "Property ID: " . $propertyCode . "\n";
    if (!empty($propertyName)) {
        $msg .= "Property: " . $propertyName . "\n";
    }
    if (!empty($location)) {
        $msg .= "Location: " . $location . "\n";
    }
    $msg .= "I am interested in your flat.\n" .
            "Is it available for rent or booking?\n" .
            "Please share the complete details.";

    if (!empty($customInquiry)) {
        $msg .= "\n\nMy Note: " . trim($customInquiry);
    }

    return "https://wa.me/" . $cleanPhone . "?text=" . rawurlencode($msg);
}

/**
 * Format Call Owner link based on owner's privacy consent
 */
function get_property_call_link($phone, $allowDirectCalls) {
    if (!$allowDirectCalls || empty($phone)) {
        return null;
    }
    $digits = preg_replace('/[^0-9+]/', '', $phone);
    return 'tel:' . $digits;
}

/**
 * Fetch a single property with owner details
 */
function get_property_by_id($id) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT p.*, c.user_id, u.full_name as owner_name, u.email as owner_email, 
                                  u.avatar as owner_avatar, u.phone as owner_user_phone, u.status as owner_status,
                                  c.address as owner_address, c.city as owner_city
                           FROM customer_properties p
                           JOIN customers c ON p.customer_id = c.id
                           JOIN users u ON c.user_id = u.id
                           WHERE p.id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/**
 * Fetch property by code (e.g. PROP-102)
 */
function get_property_by_code($code) {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT p.*, c.user_id, u.full_name as owner_name, u.email as owner_email, 
                                  u.avatar as owner_avatar, u.phone as owner_user_phone,
                                  c.address as owner_address, c.city as owner_city
                           FROM customer_properties p
                           JOIN customers c ON p.customer_id = c.id
                           JOIN users u ON c.user_id = u.id
                           WHERE p.property_code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch() ?: null;
}

/**
 * Check if a property is saved by a customer
 */
function is_property_saved($customer_id, $property_id) {
    if (!$customer_id || !$property_id) return false;
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT id FROM property_saved WHERE customer_id = ? AND property_id = ?");
    $stmt->execute([$customer_id, $property_id]);
    return (bool)$stmt->fetch();
}

/**
 * Log customer inquiry / contact interaction (WhatsApp or phone call)
 */
function log_property_inquiry($property_id, $sender_user_id = null, $channel = 'WHATSAPP', $summary = '') {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("INSERT INTO property_inquiries (property_id, sender_user_id, channel, message_summary) VALUES (?, ?, ?, ?)");
        $stmt->execute([$property_id, $sender_user_id, $channel, $summary]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Render HTML badge for property availability status
 */
function render_property_availability_badge($status) {
    $status = strtoupper((string)$status);
    $badges = [
        'AVAILABLE'       => ['class' => 'badge-success', 'label' => 'Available Now', 'icon' => 'check-circle'],
        'VISIT_SCHEDULED' => ['class' => 'badge-info', 'label' => 'Visit Scheduled', 'icon' => 'calendar'],
        'RESERVED'        => ['class' => 'badge-warning', 'label' => 'Reserved', 'icon' => 'lock'],
        'RENTED_OR_SOLD'  => ['class' => 'badge-muted', 'label' => 'Rented / Sold', 'icon' => 'tag'],
        'INACTIVE'        => ['class' => 'badge-secondary', 'label' => 'Off Market', 'icon' => 'eye-off']
    ];
    $cfg = $badges[$status] ?? ['class' => 'badge-secondary', 'label' => $status, 'icon' => 'info'];
    return '<span class="status-badge ' . e($cfg['class']) . '"><span class="badge-dot"></span>' . e($cfg['label']) . '</span>';
}

/**
 * Render HTML badge for verification status
 */
function render_property_verification_badge($status) {
    $status = strtoupper((string)$status);
    $badges = [
        'APPROVED' => ['class' => 'badge-success', 'label' => 'Verified Listing', 'icon' => 'shield-check'],
        'PENDING'  => ['class' => 'badge-warning', 'label' => 'Pending Verification', 'icon' => 'clock'],
        'REJECTED' => ['class' => 'badge-danger', 'label' => 'Verification Rejected', 'icon' => 'x-circle']
    ];
    $cfg = $badges[$status] ?? ['class' => 'badge-secondary', 'label' => $status, 'icon' => 'info'];
    return '<span class="status-badge ' . e($cfg['class']) . '"><span class="badge-dot"></span>' . e($cfg['label']) . '</span>';
}

/**
 * Render HTML badge for Customer-to-Customer Booking Status
 */
function render_property_booking_status_badge($status) {
    $map = [
        'Request Submitted'       => ['class' => 'badge-info', 'icon' => 'send'],
        'Pending Owner Approval'  => ['class' => 'badge-warning', 'icon' => 'clock'],
        'Accepted'                => ['class' => 'badge-success', 'icon' => 'check'],
        'Rejected'                => ['class' => 'badge-danger', 'icon' => 'x'],
        'Visit Scheduled'         => ['class' => 'badge-accent', 'icon' => 'calendar'],
        'Reserved'                => ['class' => 'badge-primary', 'icon' => 'shield-check'],
        'Rented or Sold'          => ['class' => 'badge-muted', 'icon' => 'check-check'],
        'Cancelled'               => ['class' => 'badge-muted', 'icon' => 'slash']
    ];
    $cfg = $map[$status] ?? ['class' => 'badge-secondary', 'icon' => 'info'];
    return '<span class="status-badge ' . e($cfg['class']) . '"><span class="badge-dot"></span>' . e($status) . '</span>';
}
