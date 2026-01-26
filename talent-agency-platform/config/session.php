<?php

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    
    session_name('talent_agency_session');
    session_set_cookie_params(3600 * 24); // 24 hours
    session_start();
}
