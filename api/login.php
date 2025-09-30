<?php
/**
 * Login API Endpoint
 * JWT Session Management - Login
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Include JWT Manager with proper path
if (file_exists(__DIR__ . '/../classes/JWTManager.php')) {
    require_once __DIR__ . '/../classes/JWTManager.php';
} else {
    require_once '../classes/JWTManager.php';
}

// Include config with proper path
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    require_once '../config.php';
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    if (!isset($input['username']) || !isset($input['password'])) {
        throw new Exception('Username and password are required');
    }
    
    $username = trim($input['username']);
    $password = trim($input['password']);
    
    if (empty($username) || empty($password)) {
        throw new Exception('Username and password cannot be empty');
    }
    
    // Database connection
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check user credentials (adjust table and column names as needed)
    $stmt = $pdo->prepare("
        SELECT id, username, email, password, first_name, last_name, role, status 
        FROM users 
        WHERE (username = ? OR email = ?) AND status = 'active'
    ");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Invalid credentials');
    }
    
    // Verify password (assuming password is hashed with password_hash())
    if (!password_verify($password, $user['password'])) {
        throw new Exception('Invalid credentials');
    }
    
    // Initialize JWT Manager
    $jwtManager = new JWTManager();
    
    // Prepare user data for token (remove sensitive data)
    $userData = [
        'username' => $user['username'],
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'role' => $user['role']
    ];
    
    // Login user and get token
    $loginResult = $jwtManager->loginUser($user['id'], $userData);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'token' => $loginResult['token'],
            'user' => $userData,
            'user_id' => $user['id'],
            'expires_never' => true,
            'login_time' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>