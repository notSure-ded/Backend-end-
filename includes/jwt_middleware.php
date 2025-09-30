<?php
/**
 * JWT Middleware for protecting API routes
 * Include this file in any API endpoint that requires authentication
 */

// Include JWT Manager with proper path
if (file_exists(__DIR__ . '/../classes/JWTManager.php')) {
    require_once __DIR__ . '/../classes/JWTManager.php';
} else {
    require_once '../classes/JWTManager.php';
}

class JWTMiddleware {
    private $jwtManager;
    
    public function __construct() {
        $this->jwtManager = new JWTManager();
    }
    
    /**
     * Authenticate request and return user data
     */
    public function authenticate() {
        try {
            // Get token from Authorization header
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            
            if (empty($authHeader)) {
                $this->sendUnauthorized('Authorization header is required');
                return false;
            }
            
            // Extract token from "Bearer <token>" format
            if (strpos($authHeader, 'Bearer ') !== 0) {
                $this->sendUnauthorized('Invalid authorization header format');
                return false;
            }
            
            $token = substr($authHeader, 7);
            
            if (empty($token)) {
                $this->sendUnauthorized('Token is required');
                return false;
            }
            
            // Verify token
            $payload = $this->jwtManager->verifyToken($token);
            
            if (!$payload) {
                $this->sendUnauthorized('Invalid or expired token');
                return false;
            }
            
            // Return user data
            return [
                'user_id' => $payload['user_id'],
                'user_data' => $payload['data'],
                'session_id' => $payload['session_id'],
                'token_issued_at' => $payload['iat']
            ];
            
        } catch (Exception $e) {
            $this->sendUnauthorized('Authentication failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Quick authentication check (returns true/false)
     */
    public function isAuthenticated() {
        return $this->authenticate() !== false;
    }
    
    /**
     * Require authentication (stops execution if not authenticated)
     */
    public function requireAuth() {
        $auth = $this->authenticate();
        if (!$auth) {
            exit(); // Stop execution if not authenticated
        }
        return $auth;
    }
    
    /**
     * Check if user has specific role
     */
    public function requireRole($requiredRole) {
        $auth = $this->requireAuth();
        
        if (!isset($auth['user_data']['role']) || $auth['user_data']['role'] !== $requiredRole) {
            $this->sendForbidden('Insufficient permissions');
            exit();
        }
        
        return $auth;
    }
    
    /**
     * Check if user has any of the specified roles
     */
    public function requireAnyRole($roles) {
        $auth = $this->requireAuth();
        
        if (!isset($auth['user_data']['role']) || !in_array($auth['user_data']['role'], $roles)) {
            $this->sendForbidden('Insufficient permissions');
            exit();
        }
        
        return $auth;
    }
    
    /**
     * Send unauthorized response
     */
    private function sendUnauthorized($message) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'UNAUTHORIZED'
        ]);
    }
    
    /**
     * Send forbidden response
     */
    private function sendForbidden($message) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'error_code' => 'FORBIDDEN'
        ]);
    }
}

// Global function for easy access
function requireAuth() {
    $middleware = new JWTMiddleware();
    return $middleware->requireAuth();
}

function requireRole($role) {
    $middleware = new JWTMiddleware();
    return $middleware->requireRole($role);
}

function requireAnyRole($roles) {
    $middleware = new JWTMiddleware();
    return $middleware->requireAnyRole($roles);
}

function isAuthenticated() {
    $middleware = new JWTMiddleware();
    return $middleware->isAuthenticated();
}
?>