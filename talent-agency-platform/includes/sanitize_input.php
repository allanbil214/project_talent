<?php
// includes/sanitize_input.php
// Automatically sanitize all input data

require_once __DIR__ . '/../classes/Security.php';

// Sanitize GET data
if (!empty($_GET)) {
    foreach ($_GET as $key => $value) {
        $_GET[$key] = Security::sanitize($value);
    }
}

// Sanitize POST data
if (!empty($_POST)) {
    foreach ($_POST as $key => $value) {
        // Skip password fields from sanitization
        if (strpos($key, 'password') === false && strpos($key, 'csrf_token') === false) {
            $_POST[$key] = Security::sanitize($value);
        }
    }
}

// Sanitize COOKIE data
if (!empty($_COOKIE)) {
    foreach ($_COOKIE as $key => $value) {
        $_COOKIE[$key] = Security::sanitize($value);
    }
}

// Check for XSS attempts in request
$check_xss = function($data) {
    if (is_array($data)) {
        foreach ($data as $value) {
            if (Security::hasXssAttempt($value)) {
                return true;
            }
        }
    } else {
        return Security::hasXssAttempt($data);
    }
    return false;
};

if ($check_xss($_GET) || $check_xss($_POST)) {
    Security::logSecurityEvent('xss_attempt', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'get' => $_GET,
        'post' => array_map(function($v) {
            return is_string($v) && strlen($v) > 100 ? substr($v, 0, 100) . '...' : $v;
        }, $_POST)
    ]);
    
    http_response_code(400);
    die('Invalid input detected');
}

// Check for SQL injection attempts in request
$check_sql = function($data) {
    if (is_array($data)) {
        foreach ($data as $value) {
            if (Security::hasSqlInjectionAttempt($value)) {
                return true;
            }
        }
    } else {
        return Security::hasSqlInjectionAttempt($data);
    }
    return false;
};

if ($check_sql($_GET) || $check_sql($_POST)) {
    Security::logSecurityEvent('sql_injection_attempt', [
        'method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'get' => $_GET,
        'post' => array_map(function($v) {
            return is_string($v) && strlen($v) > 100 ? substr($v, 0, 100) . '...' : $v;
        }, $_POST)
    ]);
    
    http_response_code(400);
    die('Invalid input detected');
}