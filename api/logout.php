<?php
/**
 * Logout API Endpoint
 * JWT Session Management - Logout
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include JWT Manager with proper path
if (file_exists(__DIR__ . '/../classes/JWTManager.php')) {
    require_once __DIR__ . '/../classes/JWTManager.php';
} else {
    require_once '../classes/JWTManager.php';
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
    // Get JWT token from Authorization header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (empty($authHeader)) {
        throw new Exception('Authorization header is required');
    }
    
    // Extract token from "Bearer <token>" format
    if (strpos($authHeader, 'Bearer ') !== 0) {
        throw new Exception('Invalid authorization header format');
    }
    
    $token = substr($authHeader, 7); // Remove "Bearer " prefix
    
    if (empty($token)) {
        throw new Exception('Token is required');
    }
    
    // Initialize JWT Manager
    $jwtManager = new JWTManager();
    
    // Logout user
    $logoutResult = $jwtManager->logoutUser($token);
    
    if ($logoutResult['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Logout successful',
            'data' => [
                'logout_time' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        throw new Exception($logoutResult['message']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>