<?php
// includes/ip_check.php

require_once __DIR__ . '/../classes/Security.php';

$client_ip = Security::getClientIp();

// Check if IP is blacklisted
if (Security::isIpBlacklisted($client_ip)) {
    Security::logSecurityEvent('blacklisted_ip_access', [
        'ip' => $client_ip,
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    http_response_code(403);
    die('Access denied');
}

// Optional: Restrict admin panel to whitelisted IPs only
if (strpos($_SERVER['REQUEST_URI'], '/admin/') !== false) {
    $require_whitelist = $_ENV['ADMIN_REQUIRE_WHITELIST'] ?? false;
    
    if ($require_whitelist && !Security::isIpWhitelisted($client_ip)) {
        Security::logSecurityEvent('non_whitelisted_admin_access', [
            'ip' => $client_ip,
            'uri' => $_SERVER['REQUEST_URI'] ?? ''
        ]);
        
        http_response_code(403);
        die('Admin access restricted to whitelisted IPs only');
    }
}