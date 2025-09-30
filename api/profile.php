<?php
/**
 * Protected API Endpoint Example
 * Demonstrates how to use JWT middleware to protect API routes
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Include JWT middleware with proper path
if (file_exists(__DIR__ . '/../includes/jwt_middleware.php')) {
    require_once __DIR__ . '/../includes/jwt_middleware.php';
} else {
    require_once '../includes/jwt_middleware.php';
}

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Require authentication for this endpoint
    $auth = requireAuth();
    
    // If we get here, user is authenticated
    $userId = $auth['user_id'];
    $userData = $auth['user_data'];
    
    // Example: Get user profile data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Simulate getting additional user data from database
        $response = [
            'success' => true,
            'message' => 'Profile data retrieved successfully',
            'data' => [
                'user_id' => $userId,
                'profile' => $userData,
                'session_info' => [
                    'session_id' => $auth['session_id'],
                    'token_issued_at' => date('Y-m-d H:i:s', $auth['token_issued_at']),
                    'never_expires' => true
                ],
                'additional_data' => [
                    'last_login' => date('Y-m-d H:i:s'),
                    'permissions' => ['read', 'write'],
                    'preferences' => [
                        'theme' => 'dark',
                        'language' => 'en'
                    ]
                ]
            ]
        ];
        
        echo json_encode($response);
    }
    
    // Example: Update user data
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Simulate updating user data
        $response = [
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user_id' => $userId,
                'updated_fields' => $input,
                'updated_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        echo json_encode($response);
    }
    
    else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>