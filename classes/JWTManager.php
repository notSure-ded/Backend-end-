<?php
/**
 * JWT Manager Class
 * Complete JWT Session Management System
 * Features: Never-expire tokens, localStorage integration, session tracking
 */

// Include JWT config with proper path handling
if (file_exists(__DIR__ . '/../jwt_config.php')) {
    require_once __DIR__ . '/../jwt_config.php';
} else {
    require_once 'jwt_config.php';
}

class JWTManager {
    private $pdo;
    private $secretKey;
    private $algorithm;
    
    public function __construct() {
        $this->secretKey = JWT_SECRET_KEY;
        $this->algorithm = JWT_ALGORITHM;
        
        try {
            $this->pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Generate JWT Token
     */
    public function generateToken($userId, $userData = []) {
        $header = json_encode(['typ' => 'JWT', 'alg' => $this->algorithm]);
        
        $sessionId = $this->generateSessionId();
        
        $payload = [
            'iss' => JWT_ISSUER,
            'aud' => JWT_AUDIENCE,
            'iat' => time(),
            'user_id' => $userId,
            'session_id' => $sessionId,
            'data' => $userData
        ];
        
        // For never-expire tokens, we don't set 'exp'
        if (!JWT_NEVER_EXPIRE && JWT_SESSION_TIMEOUT > 0) {
            $payload['exp'] = time() + JWT_SESSION_TIMEOUT;
        }
        
        $payloadJson = json_encode($payload);
        
        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payloadJson);
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $this->secretKey, true);
        $base64Signature = $this->base64UrlEncode($signature);
        
        $jwt = $base64Header . "." . $base64Payload . "." . $base64Signature;
        
        // Store session in database
        $this->storeSession($userId, $sessionId, $jwt);
        
        return $jwt;
    }
    
    /**
     * Verify JWT Token
     */
    public function verifyToken($token) {
        try {
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                return false;
            }
            
            $header = $this->base64UrlDecode($tokenParts[0]);
            $payload = $this->base64UrlDecode($tokenParts[1]);
            $signature = $this->base64UrlDecode($tokenParts[2]);
            
            // Verify signature
            $expectedSignature = hash_hmac('sha256', $tokenParts[0] . "." . $tokenParts[1], $this->secretKey, true);
            
            if (!hash_equals($signature, $expectedSignature)) {
                return false;
            }
            
            $payloadData = json_decode($payload, true);
            
            // Check if token has expired (only if JWT_NEVER_EXPIRE is false)
            if (!JWT_NEVER_EXPIRE && isset($payloadData['exp']) && $payloadData['exp'] < time()) {
                return false;
            }
            
            // Check if session is still active in database
            if (!$this->isSessionActive($payloadData['user_id'], $payloadData['session_id'])) {
                return false;
            }
            
            // Update last activity
            $this->updateSessionActivity($payloadData['session_id']);
            
            return $payloadData;
        } catch (Exception $e) {
            error_log("JWT verification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Refresh JWT Token
     */
    public function refreshToken($oldToken) {
        $payloadData = $this->verifyToken($oldToken);
        if (!$payloadData) {
            return false;
        }
        
        // Generate new token with same user data
        $newToken = $this->generateToken($payloadData['user_id'], $payloadData['data']);
        
        // Invalidate old session
        $this->invalidateSession($payloadData['session_id']);
        
        return $newToken;
    }
    
    /**
     * Login user and create session
     */
    public function loginUser($userId, $userData = []) {
        // Invalidate all existing sessions for this user (force single session)
        $this->invalidateAllUserSessions($userId);
        
        // Generate new token
        $token = $this->generateToken($userId, $userData);
        
        return [
            'success' => true,
            'token' => $token,
            'user_id' => $userId,
            'expires_never' => JWT_NEVER_EXPIRE,
            'message' => 'Login successful'
        ];
    }
    
    /**
     * Logout user
     */
    public function logoutUser($token) {
        $payloadData = $this->verifyToken($token);
        if ($payloadData) {
            $this->invalidateSession($payloadData['session_id']);
            return ['success' => true, 'message' => 'Logout successful'];
        }
        return ['success' => false, 'message' => 'Invalid token'];
    }
    
    /**
     * Logout all sessions for a user
     */
    public function logoutAllSessions($userId) {
        $this->invalidateAllUserSessions($userId);
        return ['success' => true, 'message' => 'All sessions logged out'];
    }
    
    /**
     * Get active sessions for a user
     */
    public function getUserSessions($userId) {
        $stmt = $this->pdo->prepare("
            SELECT session_token, created_at, last_activity, ip_address, user_agent 
            FROM jwt_sessions 
            WHERE user_id = ? AND is_active = 1 
            ORDER BY last_activity DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if user is logged in
     */
    public function isUserLoggedIn($token) {
        return $this->verifyToken($token) !== false;
    }
    
    // Private helper methods
    
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private function base64UrlDecode($data) {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }
    
    private function generateSessionId() {
        return bin2hex(random_bytes(32));
    }
    
    private function storeSession($userId, $sessionId, $token) {
        $stmt = $this->pdo->prepare("
            INSERT INTO jwt_sessions (user_id, session_token, jwt_token, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $stmt->execute([$userId, $sessionId, $token, $ipAddress, $userAgent]);
    }
    
    private function isSessionActive($userId, $sessionId) {
        $stmt = $this->pdo->prepare("
            SELECT id FROM jwt_sessions 
            WHERE user_id = ? AND session_token = ? AND is_active = 1
        ");
        $stmt->execute([$userId, $sessionId]);
        return $stmt->rowCount() > 0;
    }
    
    private function updateSessionActivity($sessionId) {
        $stmt = $this->pdo->prepare("
            UPDATE jwt_sessions 
            SET last_activity = CURRENT_TIMESTAMP 
            WHERE session_token = ? AND is_active = 1
        ");
        $stmt->execute([$sessionId]);
    }
    
    private function invalidateSession($sessionId) {
        $stmt = $this->pdo->prepare("
            UPDATE jwt_sessions 
            SET is_active = 0 
            WHERE session_token = ?
        ");
        $stmt->execute([$sessionId]);
    }
    
    private function invalidateAllUserSessions($userId) {
        $stmt = $this->pdo->prepare("
            UPDATE jwt_sessions 
            SET is_active = 0 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }
}
?>