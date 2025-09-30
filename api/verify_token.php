<?php
/**
 * Verify Token API Endpoint
 * JWT Session Management - Token Verification
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
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

// Allow both GET and POST requests
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
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
    
    // Verify token
    $payload = $jwtManager->verifyToken($token);
    
    if ($payload) {
        echo json_encode([
            'success' => true,
            'message' => 'Token is valid',
            'data' => [
                'user_id' => $payload['user_id'],
                'user_data' => $payload['data'],
                'issued_at' => date('Y-m-d H:i:s', $payload['iat']),
                'session_id' => $payload['session_id'],
                'expires_never' => JWT_NEVER_EXPIRE,
                'verified_at' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        throw new Exception('Invalid or expired token');
    }
    
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'INVALID_TOKEN'
    ]);
}
?>