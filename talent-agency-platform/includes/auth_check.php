<?php
// includes/auth_check.php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Store the intended URL to redirect after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    if (isAjax()) {
        jsonResponse([
            'success' => false,
            'message' => 'Authentication required',
            'redirect' => SITE_URL . '/public/login.php'
        ], 401);
    } else {
        redirect(SITE_URL . '/public/login.php');
    }
}

/**
 * Require specific role(s)
 */
function requireRole($allowed_roles) {
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    if (!hasRole($allowed_roles)) {
        if (isAjax()) {
            jsonResponse([
                'success' => false,
                'message' => 'Access denied. Insufficient permissions.'
            ], 403);
        } else {
            setFlash('error', 'Access denied. Insufficient permissions.');
            redirect(SITE_URL . '/public/unauthorized.php');
        }
    }
}

/**
 * Require admin or staff
 */
function requireAdmin() {
    requireRole([ROLE_SUPER_ADMIN, ROLE_STAFF]);
}

/**
 * Check if session is expired
 */
function checkSessionExpiry() {
    if (isset($_SESSION['last_activity'])) {
        $inactive = time() - $_SESSION['last_activity'];
        
        if ($inactive > SESSION_LIFETIME) {
            session_unset();
            session_destroy();
            
            if (isAjax()) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Session expired. Please login again.',
                    'redirect' => SITE_URL . '/public/login.php'
                ], 401);
            } else {
                setFlash('error', 'Session expired. Please login again.');
                redirect(SITE_URL . '/public/login.php');
            }
        }
    }
    
    $_SESSION['last_activity'] = time();
}

checkSessionExpiry();