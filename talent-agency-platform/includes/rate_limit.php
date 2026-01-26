<?php
// includes/rate_limit.php

require_once __DIR__ . '/../classes/Security.php';

/**
 * Apply rate limiting to API endpoints
 * 
 * Usage:
 * require_once __DIR__ . '/../includes/rate_limit.php';
 * applyRateLimit('api_endpoint', 60, 60); // 60 requests per 60 seconds
 */

function applyRateLimit($key_prefix = 'global', $max_requests = 60, $time_window = 60) {
    $ip = Security::getClientIp();
    $user_id = $_SESSION['user_id'] ?? 'guest';
    
    $rate_key = $key_prefix . '_' . $user_id . '_' . $ip;
    
    if (!Security::checkRateLimit($rate_key, $max_requests, $time_window)) {
        $remaining = Security::getRateLimitRemaining($rate_key, $max_requests);
        
        Security::logSecurityEvent('rate_limit_exceeded', [
            'key' => $key_prefix,
            'ip' => $ip,
            'user_id' => $user_id,
            'max_requests' => $max_requests,
            'time_window' => $time_window
        ]);
        
        header('X-RateLimit-Limit: ' . $max_requests);
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: ' . ($_SESSION['rate_limit_' . $rate_key]['reset_time'] ?? time()));
        
        if (isAjax()) {
            jsonResponse([
                'success' => false,
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $time_window
            ], 429);
        } else {
            http_response_code(429);
            die('Too many requests. Please try again later.');
        }
    }
    
    // Set rate limit headers
    $remaining = Security::getRateLimitRemaining($rate_key, $max_requests);
    header('X-RateLimit-Limit: ' . $max_requests);
    header('X-RateLimit-Remaining: ' . max(0, $remaining - 1));
}

/**
 * Apply strict rate limiting for authentication endpoints
 */
function applyAuthRateLimit() {
    applyRateLimit('auth', 5, 300); // 5 attempts per 5 minutes
}

/**
 * Apply rate limiting for API endpoints
 */
function applyApiRateLimit() {
    applyRateLimit('api', 100, 60); // 100 requests per minute
}

/**
 * Apply rate limiting for file uploads
 */
function applyUploadRateLimit() {
    applyRateLimit('upload', 10, 300); // 10 uploads per 5 minutes
}