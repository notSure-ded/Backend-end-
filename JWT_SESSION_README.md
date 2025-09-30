# JWT Session Management System

A complete JWT-based session management system for PHP backend with JavaScript frontend integration. Features never-expiring tokens, localStorage management, and automatic session verification.

## Features

- ✅ **Never-expiring JWT tokens** (only reset on new login)
- ✅ **localStorage integration** for persistent sessions
- ✅ **Automatic token verification** every 5 minutes
- ✅ **Session tracking** in database
- ✅ **Single session per user** (force logout on new login)
- ✅ **Role-based access control**
- ✅ **Secure token handling**
- ✅ **API middleware protection**
- ✅ **Frontend session manager**

## File Structure

```
├── jwt_config.php              # JWT configuration and database setup
├── classes/
│   └── JWTManager.php          # Main JWT handling class
├── includes/
│   └── jwt_middleware.php      # API protection middleware
├── api/
│   ├── login.php               # Login endpoint
│   ├── logout.php              # Logout endpoint
│   ├── verify_token.php        # Token verification endpoint
│   └── profile.php             # Example protected endpoint
├── js/
│   └── jwt-session-manager.js  # Frontend session manager
└── jwt_demo.html               # Demo/example page
```

## Setup Instructions

### 1. Database Configuration

Ensure your `config.php` file has the correct database credentials:

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
?>
```

### 2. User Table

Make sure you have a `users` table with at least these columns:

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    role VARCHAR(20) DEFAULT 'user',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 3. JWT Sessions Table

The system automatically creates the `jwt_sessions` table when `jwt_config.php` is included.

## Backend Usage

### Basic Authentication

```php
// Include the JWT manager
require_once 'classes/JWTManager.php';

$jwtManager = new JWTManager();

// Login user
$loginResult = $jwtManager->loginUser($userId, $userData);

// Verify token
$payload = $jwtManager->verifyToken($token);

// Logout user
$logoutResult = $jwtManager->logoutUser($token);
```

### API Protection

```php
// Protect an API endpoint
require_once 'includes/jwt_middleware.php';

// Require authentication
$auth = requireAuth();

// Require specific role
$auth = requireRole('admin');

// Require any of multiple roles
$auth = requireAnyRole(['admin', 'moderator']);
```

### Example Protected API

```php
<?php
require_once '../includes/jwt_middleware.php';

// This will automatically send 401 if not authenticated
$auth = requireAuth();

// User is authenticated, proceed with API logic
$userId = $auth['user_id'];
$userData = $auth['user_data'];

echo json_encode([
    'success' => true,
    'data' => 'Protected data for user ' . $userId
]);
?>
```

## Frontend Usage

### Include the Session Manager

```html
<script src="js/jwt-session-manager.js"></script>
```

### Login

```javascript
// Login user
const result = await sessionManager.login('username', 'password');

if (result.success) {
    console.log('Login successful:', result.data);
    // User data is automatically stored in localStorage
} else {
    console.error('Login failed:', result.message);
}
```

### Check Login Status

```javascript
// Check if user is logged in
if (sessionManager.isLoggedIn()) {
    console.log('User is logged in');
    
    // Get user data
    const userData = sessionManager.getUserData();
    const userInfo = sessionManager.getUserInfo();
}
```

### Make Authenticated API Calls

```javascript
// Make authenticated API request
try {
    const result = await sessionManager.apiRequest('/api/profile.php');
    
    if (result.success) {
        console.log('API response:', result.data);
    }
} catch (error) {
    console.error('API error:', error.message);
}
```

### Logout

```javascript
// Logout user
const result = await sessionManager.logout();
console.log('Logout result:', result.message);
```

### Manual Token Verification

```javascript
// Verify token manually
const result = await sessionManager.verifyToken();

if (result.success) {
    console.log('Token is valid');
} else {
    console.log('Token is invalid or expired');
}
```

## API Endpoints

### POST /api/login.php

Login user and get JWT token.

**Request:**
```json
{
    "username": "user@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
        "user": {
            "username": "user@example.com",
            "email": "user@example.com",
            "first_name": "John",
            "last_name": "Doe",
            "role": "user"
        },
        "user_id": 1,
        "expires_never": true,
        "login_time": "2025-09-30 10:30:00"
    }
}
```

### POST /api/logout.php

Logout user and invalidate token.

**Headers:**
```
Authorization: Bearer <jwt_token>
```

**Response:**
```json
{
    "success": true,
    "message": "Logout successful",
    "data": {
        "logout_time": "2025-09-30 11:30:00"
    }
}
```

### GET /api/verify_token.php

Verify JWT token validity.

**Headers:**
```
Authorization: Bearer <jwt_token>
```

**Response:**
```json
{
    "success": true,
    "message": "Token is valid",
    "data": {
        "user_id": 1,
        "user_data": { ... },
        "issued_at": "2025-09-30 10:30:00",
        "session_id": "abc123...",
        "expires_never": true,
        "verified_at": "2025-09-30 11:30:00"
    }
}
```

## Configuration Options

### Backend Configuration (jwt_config.php)

```php
// Never expire tokens
define('JWT_NEVER_EXPIRE', true);

// Secret key (change in production)
define('JWT_SECRET_KEY', 'your-secret-key');

// Auto-refresh settings
define('JWT_AUTO_REFRESH', true);
define('JWT_REFRESH_THRESHOLD', 86400); // 24 hours
```

### Frontend Configuration

```javascript
const sessionManager = new JWTSessionManager({
    apiBaseUrl: '/api',                    // API base URL
    tokenKey: 'jwt_token',                 // localStorage key for token
    userKey: 'user_data',                  // localStorage key for user data
    autoVerifyInterval: 300000,            // Auto-verify every 5 minutes
    onTokenExpired: () => {                // Custom expired handler
        alert('Session expired!');
        window.location.href = '/login.html';
    },
    onLoginSuccess: (data) => {            // Custom login success handler
        console.log('Login successful:', data);
    },
    onLogoutSuccess: () => {               // Custom logout success handler
        console.log('Logout successful');
    }
});
```

## Security Features

1. **Strong Secret Key**: Uses SHA-256 hashed secret key
2. **Session Tracking**: All sessions stored and tracked in database
3. **Single Session**: New login invalidates previous sessions
4. **IP and User Agent Tracking**: Sessions track client information
5. **Automatic Verification**: Frontend automatically verifies tokens
6. **Secure Headers**: Proper CORS and security headers
7. **Input Validation**: All inputs validated and sanitized

## Error Handling

The system provides comprehensive error handling:

- **401 Unauthorized**: Invalid or missing token
- **403 Forbidden**: Insufficient permissions
- **405 Method Not Allowed**: Wrong HTTP method
- **500 Internal Server Error**: Server-side errors

## Demo

Open `jwt_demo.html` in your browser to see a working example of the JWT session management system.

## Customization

### Adding Custom User Data

```php
// When logging in, add custom data to token
$userData = [
    'username' => $user['username'],
    'role' => $user['role'],
    'department' => $user['department'],     // Custom field
    'permissions' => $user['permissions']    // Custom field
];

$loginResult = $jwtManager->loginUser($userId, $userData);
```

### Custom Token Expiration

To enable token expiration, modify `jwt_config.php`:

```php
define('JWT_NEVER_EXPIRE', false);
define('JWT_SESSION_TIMEOUT', 86400); // 24 hours
```

### Role-Based Protection

```php
// Require admin role
$auth = requireRole('admin');

// Require any of multiple roles
$auth = requireAnyRole(['admin', 'moderator', 'editor']);
```

## Troubleshooting

### Common Issues

1. **"Database connection failed"**: Check database credentials in `config.php`
2. **"Authorization header is required"**: Ensure frontend sends proper headers
3. **"Invalid or expired token"**: Check if session was invalidated or token corrupted
4. **CORS errors**: Verify CORS headers in API endpoints

### Debugging

Enable error logging in PHP:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
```

### Token Debugging

Check token payload without verification:

```javascript
// Decode token payload (client-side debugging only)
function debugToken(token) {
    const payload = token.split('.')[1];
    const decoded = atob(payload.replace(/-/g, '+').replace(/_/g, '/'));
    console.log('Token payload:', JSON.parse(decoded));
}
```

## License

This JWT Session Management System is open source and available under the MIT License.