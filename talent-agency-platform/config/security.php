<?php
// config/security.php

// Security configuration settings

// Password policy
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_LOWERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);
define('PASSWORD_REQUIRE_SPECIAL', false);

// Session security
define('SESSION_REGENERATE_INTERVAL', 300); // Regenerate session ID every 5 minutes
define('SESSION_ABSOLUTE_TIMEOUT', 28800); // 8 hours absolute timeout
define('SESSION_IDLE_TIMEOUT', 3600); // 1 hour idle timeout

// Rate limiting
define('RATE_LIMIT_LOGIN', 5); // Max login attempts
define('RATE_LIMIT_LOGIN_WINDOW', 900); // In 15 minutes
define('RATE_LIMIT_API', 100); // Max API calls
define('RATE_LIMIT_API_WINDOW', 60); // Per minute
define('RATE_LIMIT_UPLOAD', 10); // Max uploads
define('RATE_LIMIT_UPLOAD_WINDOW', 300); // In 5 minutes

// File upload security
define('UPLOAD_MAX_SIZE_IMAGE', 5 * 1024 * 1024); // 5MB
define('UPLOAD_MAX_SIZE_DOCUMENT', 10 * 1024 * 1024); // 10MB
define('UPLOAD_ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('UPLOAD_ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx']);

// IP restrictions
define('ADMIN_IP_WHITELIST_ENABLED', false); // Set to true in production
define('IP_BLACKLIST_ENABLED', true);

// Whitelisted IPs for admin access (if enabled)
define('ADMIN_IP_WHITELIST', [
    '127.0.0.1',
    '::1',
    // Add your office/home IPs here
]);

// Blacklisted IPs
define('IP_BLACKLIST', [
    // Add banned IPs here
]);

// CORS settings
define('CORS_ENABLED', false);
define('CORS_ALLOWED_ORIGINS', [
    // Add allowed origins here if CORS is enabled
]);

// Content Security Policy
define('CSP_ENABLED', true);
define('CSP_POLICY', "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; img-src 'self' data: https:; font-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net;");

// XSS Protection
define('XSS_PROTECTION_ENABLED', true);

// SQL Injection Protection
define('SQL_INJECTION_PROTECTION_ENABLED', true);

// Encryption
define('ENCRYPTION_KEY', $_ENV['ENCRYPTION_KEY'] ?? 'change-this-in-production-' . bin2hex(random_bytes(16)));
define('ENCRYPTION_CIPHER', 'aes-256-cbc');

// Two-Factor Authentication
define('2FA_ENABLED', false); // Set to true to enable 2FA
define('2FA_REQUIRED_FOR_ADMIN', false);

// Logging
define('SECURITY_LOG_ENABLED', true);
define('SECURITY_LOG_FILE', __DIR__ . '/../logs/security.log');

// Brute force protection
define('BRUTE_FORCE_PROTECTION_ENABLED', true);
define('BRUTE_FORCE_MAX_ATTEMPTS', 5);
define('BRUTE_FORCE_LOCKOUT_TIME', 900); // 15 minutes

// Token expiry times
define('PASSWORD_RESET_TOKEN_EXPIRY', 3600); // 1 hour
define('EMAIL_VERIFICATION_TOKEN_EXPIRY', 86400); // 24 hours

// Maintenance mode
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_ALLOWED_IPS', ['127.0.0.1', '::1']);

// Initialize security headers
if (!headers_sent()) {
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy
    if (CSP_ENABLED) {
        header('Content-Security-Policy: ' . CSP_POLICY);
    }
    
    // Remove server information
    header_remove('X-Powered-By');
    
    // HSTS (uncomment in production with HTTPS)
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

// Validate password strength
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters long';
    }
    
    if (PASSWORD_REQUIRE_UPPERCASE && !preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (PASSWORD_REQUIRE_LOWERCASE && !preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    if (PASSWORD_REQUIRE_NUMBER && !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }
    
    return empty($errors) ? true : $errors;
}