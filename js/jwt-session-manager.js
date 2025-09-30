/**
 * JWT Session Manager for Frontend
 * Handles localStorage, automatic token verification, and session management
 */

class JWTSessionManager {
    constructor(config = {}) {
        this.config = {
            apiBaseUrl: config.apiBaseUrl || '/api',
            tokenKey: config.tokenKey || 'jwt_token',
            userKey: config.userKey || 'user_data',
            autoVerifyInterval: config.autoVerifyInterval || 300000, // 5 minutes
            onTokenExpired: config.onTokenExpired || this.defaultTokenExpiredHandler,
            onLoginSuccess: config.onLoginSuccess || null,
            onLogoutSuccess: config.onLogoutSuccess || null,
            ...config
        };
        
        this.verifyInterval = null;
        this.isInitialized = false;
        
        // Initialize on page load
        this.init();
    }
    
    /**
     * Initialize the session manager
     */
    init() {
        if (this.isInitialized) return;
        
        console.log('JWT Session Manager initialized');
        
        // Start automatic token verification if user is logged in
        if (this.isLoggedIn()) {
            this.startAutoVerification();
        }
        
        this.isInitialized = true;
    }
    
    /**
     * Login user
     */
    async login(username, password) {
        try {
            const response = await fetch(`${this.config.apiBaseUrl}/login.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: username,
                    password: password
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Store token and user data in localStorage
                this.setToken(data.data.token);
                this.setUserData(data.data.user);
                
                // Start automatic verification
                this.startAutoVerification();
                
                // Call success callback
                if (this.config.onLoginSuccess) {
                    this.config.onLoginSuccess(data.data);
                }
                
                console.log('Login successful:', data.message);
                return {
                    success: true,
                    data: data.data,
                    message: data.message
                };
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Login failed:', error.message);
            return {
                success: false,
                message: error.message
            };
        }
    }
    
    /**
     * Logout user
     */
    async logout() {
        try {
            const token = this.getToken();
            
            if (token) {
                // Call logout API
                const response = await fetch(`${this.config.apiBaseUrl}/logout.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    }
                });
                
                const data = await response.json();
                console.log('Logout API response:', data.message);
            }
            
            // Clear local storage regardless of API response
            this.clearSession();
            
            // Call success callback
            if (this.config.onLogoutSuccess) {
                this.config.onLogoutSuccess();
            }
            
            console.log('Logout successful');
            return { success: true, message: 'Logout successful' };
            
        } catch (error) {
            // Clear session even if API call fails
            this.clearSession();
            console.error('Logout error:', error.message);
            return { success: true, message: 'Logout completed (with errors)' };
        }
    }
    
    /**
     * Verify token with server
     */
    async verifyToken() {
        try {
            const token = this.getToken();
            
            if (!token) {
                throw new Error('No token found');
            }
            
            const response = await fetch(`${this.config.apiBaseUrl}/verify_token.php`, {
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update user data if provided
                if (data.data.user_data) {
                    this.setUserData(data.data.user_data);
                }
                
                console.log('Token verified successfully');
                return { success: true, data: data.data };
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Token verification failed:', error.message);
            this.handleTokenExpired();
            return { success: false, message: error.message };
        }
    }
    
    /**
     * Check if user is logged in
     */
    isLoggedIn() {
        return this.getToken() !== null && this.getUserData() !== null;
    }
    
    /**
     * Get stored JWT token
     */
    getToken() {
        return localStorage.getItem(this.config.tokenKey);
    }
    
    /**
     * Store JWT token
     */
    setToken(token) {
        localStorage.setItem(this.config.tokenKey, token);
    }
    
    /**
     * Get stored user data
     */
    getUserData() {
        const userData = localStorage.getItem(this.config.userKey);
        return userData ? JSON.parse(userData) : null;
    }
    
    /**
     * Store user data
     */
    setUserData(userData) {
        localStorage.setItem(this.config.userKey, JSON.stringify(userData));
    }
    
    /**
     * Clear all session data
     */
    clearSession() {
        localStorage.removeItem(this.config.tokenKey);
        localStorage.removeItem(this.config.userKey);
        this.stopAutoVerification();
    }
    
    /**
     * Get authorization header
     */
    getAuthHeader() {
        const token = this.getToken();
        return token ? { 'Authorization': `Bearer ${token}` } : {};
    }
    
    /**
     * Make authenticated API request
     */
    async apiRequest(url, options = {}) {
        const defaultOptions = {
            headers: {
                'Content-Type': 'application/json',
                ...this.getAuthHeader(),
                ...(options.headers || {})
            }
        };
        
        const finalOptions = { ...defaultOptions, ...options };
        
        try {
            const response = await fetch(url, finalOptions);
            const data = await response.json();
            
            // If token is invalid, handle it
            if (!response.ok && data.error_code === 'INVALID_TOKEN') {
                this.handleTokenExpired();
                throw new Error('Session expired');
            }
            
            return { success: response.ok, data: data, response: response };
        } catch (error) {
            console.error('API request failed:', error.message);
            throw error;
        }
    }
    
    /**
     * Start automatic token verification
     */
    startAutoVerification() {
        if (this.verifyInterval) {
            clearInterval(this.verifyInterval);
        }
        
        this.verifyInterval = setInterval(() => {
            this.verifyToken();
        }, this.config.autoVerifyInterval);
        
        console.log('Auto-verification started');
    }
    
    /**
     * Stop automatic token verification
     */
    stopAutoVerification() {
        if (this.verifyInterval) {
            clearInterval(this.verifyInterval);
            this.verifyInterval = null;
            console.log('Auto-verification stopped');
        }
    }
    
    /**
     * Handle token expiration
     */
    handleTokenExpired() {
        console.log('Token expired or invalid');
        this.clearSession();
        this.config.onTokenExpired();
    }
    
    /**
     * Default token expired handler
     */
    defaultTokenExpiredHandler() {
        console.log('Session expired. Please login again.');
        // Redirect to login page or show login modal
        if (window.location.pathname !== '/login.html' && window.location.pathname !== '/login') {
            window.location.href = '/login.html';
        }
    }
    
    /**
     * Get user info for display
     */
    getUserInfo() {
        const userData = this.getUserData();
        return userData ? {
            username: userData.username,
            email: userData.email,
            firstName: userData.first_name,
            lastName: userData.last_name,
            fullName: `${userData.first_name} ${userData.last_name}`.trim(),
            role: userData.role
        } : null;
    }
    
    /**
     * Check if user has specific role
     */
    hasRole(role) {
        const userData = this.getUserData();
        return userData && userData.role === role;
    }
    
    /**
     * Destroy session manager
     */
    destroy() {
        this.stopAutoVerification();
        this.isInitialized = false;
    }
}

// Global instance
window.JWTSessionManager = JWTSessionManager;

// Auto-initialize default instance
window.sessionManager = new JWTSessionManager({
    apiBaseUrl: './api', // Updated to work from root directory
    onTokenExpired: () => {
        // Custom token expired handler
        alert('Your session has expired. Please login again.');
        window.location.href = '/login.html';
    }
});

// Utility functions for backward compatibility
window.login = (username, password) => window.sessionManager.login(username, password);
window.logout = () => window.sessionManager.logout();
window.isLoggedIn = () => window.sessionManager.isLoggedIn();
window.getUserData = () => window.sessionManager.getUserData();
window.apiRequest = (url, options) => window.sessionManager.apiRequest(url, options);

console.log('JWT Session Manager loaded and ready!');