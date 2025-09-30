<?php
/**
 * JWT Configuration File
 * Complete JWT Session Management System
 */

// JWT Secret Key - Change this to a strong, unique secret
define('JWT_SECRET_KEY', 'SpaceECE_JWT_Secret_Key_2025_Change_This_In_Production_' . hash('sha256', 'space-ece-backend'));

// JWT Algorithm
define('JWT_ALGORITHM', 'HS256');

// JWT Issuer
define('JWT_ISSUER', 'space-ece-backend');

// JWT Audience
define('JWT_AUDIENCE', 'space-ece-users');

// Session settings - Never expire until user logs out or logs in again
define('JWT_NEVER_EXPIRE', true);
define('JWT_SESSION_TIMEOUT', 0); // 0 means never expire

// Token refresh settings
define('JWT_AUTO_REFRESH', true);
define('JWT_REFRESH_THRESHOLD', 86400); // 24 hours for auto refresh

// Session table for tracking active sessions
define('JWT_SESSIONS_TABLE', 'jwt_sessions');

// Database connection - Use simple config for testing
if (file_exists('jwt_simple_config.php')) {
    require_once 'jwt_simple_config.php';
} else {
    require_once 'config.php';
}

// Create sessions table if it doesn't exist
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $createTable = "
        CREATE TABLE IF NOT EXISTS jwt_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(500) NOT NULL UNIQUE,
            jwt_token TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            ip_address VARCHAR(45),
            user_agent TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            INDEX idx_user_id (user_id),
            INDEX idx_session_token (session_token),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";
    
    $pdo->exec($createTable);
} catch (Exception $e) {
    error_log("JWT Sessions table creation failed: " . $e->getMessage());
}
?>