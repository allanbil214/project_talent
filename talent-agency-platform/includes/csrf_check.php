<?php
// includes/csrf_check.php

require_once __DIR__ . '/../classes/Security.php';

// Only check CSRF for POST, PUT, DELETE requests
$method = $_SERVER['REQUEST_METHOD'];

if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $token = null;
    
    // Check for token in POST data
    if (isset($_POST['csrf_token'])) {
        $token = $_POST['csrf_token'];
    }
    // Check for token in headers (for AJAX requests)
    elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    // Check in JSON body
    else {
        $json_data = json_decode(file_get_contents('php://input'), true);
        if (isset($json_data['csrf_token'])) {
            $token = $json_data['csrf_token'];
        }
    }
    
    // Verify token
    if (!$token || !Security::verifyCsrfToken($token)) {
        Security::logSecurityEvent('csrf_token_invalid', [
            'method' => $method,
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ]);
        
        if (isAjax()) {
            jsonResponse([
                'success' => false,
                'message' => 'CSRF token validation failed'
            ], 403);
        } else {
            http_response_code(403);
            die('CSRF token validation failed. Please try again.');
        }
    }
}