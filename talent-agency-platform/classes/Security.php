<?php
// classes/Security.php

class Security {
    
    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public static function verifyCsrfToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Get CSRF token input field
     */
    public static function csrfField() {
        $token = self::generateCsrfToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Sanitize input
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Clean string for database (additional layer)
     */
    public static function clean($string) {
        $string = trim($string);
        $string = stripslashes($string);
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate and sanitize email
     */
    public static function sanitizeEmail($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
    
    /**
     * Validate email
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate URL
     */
    public static function isValidUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Hash password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Encrypt data
     */
    public static function encrypt($data, $key = null) {
        $key = $key ?? $_ENV['ENCRYPTION_KEY'] ?? 'default-key-change-in-production';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
        return base64_encode($encrypted . '::' . $iv);
    }
    
    /**
     * Decrypt data
     */
    public static function decrypt($data, $key = null) {
        $key = $key ?? $_ENV['ENCRYPTION_KEY'] ?? 'default-key-change-in-production';
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, 'aes-256-cbc', $key, 0, $iv);
    }
    
    /**
     * Check for XSS attempts
     */
    public static function hasXssAttempt($input) {
        $dangerous_patterns = [
            '/<script\b[^>]*>(.*?)<\/script>/is',
            '/<iframe\b[^>]*>(.*?)<\/iframe>/is',
            '/javascript:/i',
            '/on\w+\s*=/i',
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for SQL injection attempts
     */
    public static function hasSqlInjectionAttempt($input) {
        $dangerous_patterns = [
            '/(\bUNION\b.*\bSELECT\b)/i',
            '/(\bDROP\b.*\bTABLE\b)/i',
            '/(\bINSERT\b.*\bINTO\b)/i',
            '/(\bDELETE\b.*\bFROM\b)/i',
            '/(\bUPDATE\b.*\bSET\b)/i',
            '/(--|\#|\/\*)/i',
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $input)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($key, $max_attempts = 5, $time_window = 300) {
        $cache_key = 'rate_limit_' . $key;
        
        if (!isset($_SESSION[$cache_key])) {
            $_SESSION[$cache_key] = [
                'count' => 1,
                'reset_time' => time() + $time_window
            ];
            return true;
        }
        
        $rate_data = $_SESSION[$cache_key];
        
        // Reset if time window has passed
        if (time() > $rate_data['reset_time']) {
            $_SESSION[$cache_key] = [
                'count' => 1,
                'reset_time' => time() + $time_window
            ];
            return true;
        }
        
        // Increment counter
        $_SESSION[$cache_key]['count']++;
        
        // Check if limit exceeded
        return $_SESSION[$cache_key]['count'] <= $max_attempts;
    }
    
    /**
     * Get rate limit remaining attempts
     */
    public static function getRateLimitRemaining($key, $max_attempts = 5) {
        $cache_key = 'rate_limit_' . $key;
        
        if (!isset($_SESSION[$cache_key])) {
            return $max_attempts;
        }
        
        $remaining = $max_attempts - $_SESSION[$cache_key]['count'];
        return max(0, $remaining);
    }
    
    /**
     * Log security event
     */
    public static function logSecurityEvent($event_type, $details = []) {
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event_type' => $event_type,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'details' => $details
        ];
        
        $log_file = __DIR__ . '/../logs/security.log';
        $log_dir = dirname($log_file);
        
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
        
        error_log(json_encode($log_entry) . PHP_EOL, 3, $log_file);
    }
    
    /**
     * Prevent session fixation
     */
    public static function regenerateSession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
    
    /**
     * Check if IP is whitelisted
     */
    public static function isIpWhitelisted($ip = null) {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? '';
        
        $whitelist = [
            '127.0.0.1',
            '::1',
            // Add your IPs here
        ];
        
        return in_array($ip, $whitelist);
    }
    
    /**
     * Check if IP is blacklisted
     */
    public static function isIpBlacklisted($ip = null) {
        $ip = $ip ?? $_SERVER['REMOTE_ADDR'] ?? '';
        
        $blacklist = [
            // Add banned IPs here
        ];
        
        return in_array($ip, $blacklist);
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowed_types, $max_size) {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            throw new Exception('File size exceeds maximum allowed');
        }
        
        // Check file extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowed_types)) {
            throw new Exception('File type not allowed');
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (!isset($allowed_mimes[$extension]) || $mime_type !== $allowed_mimes[$extension]) {
            throw new Exception('File MIME type does not match extension');
        }
        
        return true;
    }
    
    /**
     * Get client IP address
     */
    public static function getClientIp() {
        $ip_keys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Prevent clickjacking
     */
    public static function preventClickjacking() {
        header('X-Frame-Options: SAMEORIGIN');
    }
    
    /**
     * Set security headers
     */
    public static function setSecurityHeaders() {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header_remove('X-Powered-By');
    }
}