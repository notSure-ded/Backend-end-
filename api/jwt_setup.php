<?php
/**
 * JWT System Setup and Test Endpoint
 * This endpoint will check and setup the required database tables
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include config
require_once '../config.php';

try {
    // Test database connection
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $setupResults = [];
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = $stmt->rowCount() > 0;
    
    if (!$usersTableExists) {
        // Create users table
        $createUsersTable = "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(50),
                last_name VARCHAR(50),
                role VARCHAR(20) DEFAULT 'user',
                status ENUM('active', 'inactive') DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        $pdo->exec($createUsersTable);
        $setupResults['users_table'] = 'Created successfully';
        
        // Insert a test user
        $testPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $insertTestUser = "
            INSERT INTO users (username, email, password, first_name, last_name, role) 
            VALUES ('admin', 'admin@spacece.com', ?, 'Admin', 'User', 'admin')
        ";
        $stmt = $pdo->prepare($insertTestUser);
        $stmt->execute([$testPassword]);
        $setupResults['test_user'] = 'Created: admin@spacece.com / admin123';
    } else {
        $setupResults['users_table'] = 'Already exists';
    }
    
    // Check if jwt_sessions table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'jwt_sessions'");
    $jwtSessionsTableExists = $stmt->rowCount() > 0;
    
    if ($jwtSessionsTableExists) {
        $setupResults['jwt_sessions_table'] = 'Already exists';
    } else {
        $setupResults['jwt_sessions_table'] = 'Will be created automatically by jwt_config.php';
    }
    
    // Test JWT Manager
    try {
        require_once '../classes/JWTManager.php';
        $jwtManager = new JWTManager();
        $setupResults['jwt_manager'] = 'Loaded successfully';
    } catch (Exception $e) {
        $setupResults['jwt_manager'] = 'Error: ' . $e->getMessage();
    }
    
    // Get database info
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'JWT System Setup Check Complete',
        'data' => [
            'database' => [
                'host' => DB_HOST,
                'name' => $dbInfo['db_name'],
                'connection' => 'successful'
            ],
            'setup_results' => $setupResults,
            'jwt_config' => [
                'never_expire' => JWT_NEVER_EXPIRE,
                'secret_key_set' => !empty(JWT_SECRET_KEY),
                'algorithm' => JWT_ALGORITHM
            ],
            'next_steps' => [
                '1. Test login with: admin@spacece.com / admin123',
                '2. Check jwt_demo.html for frontend demo',
                '3. Use /api/login.php for authentication'
            ]
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Setup failed: ' . $e->getMessage(),
        'debug_info' => [
            'db_host' => defined('DB_HOST') ? DB_HOST : 'Not defined',
            'db_name' => defined('DB_NAME') ? DB_NAME : 'Not defined',
            'db_user' => defined('DB_USER') ? DB_USER : 'Not defined',
            'config_loaded' => file_exists('../config.php')
        ]
    ]);
}
?>