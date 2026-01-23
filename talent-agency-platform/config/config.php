<?php
// config/config.php

// Site configuration
define('SITE_NAME', $_ENV['SITE_NAME'] ?? 'Talent Agency Platform');
define('SITE_URL', $_ENV['SITE_URL'] ?? 'http://localhost/talent-agency-platform');
define('SITE_EMAIL', $_ENV['SITE_EMAIL'] ?? 'admin@talentagency.com');

// Pagination
define('ITEMS_PER_PAGE', 20);
define('JOBS_PER_PAGE', 15);
define('TALENTS_PER_PAGE', 12);

// File upload settings
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MAX_IMAGE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);
define('ALLOWED_DOCUMENT_TYPES', ['pdf', 'doc', 'docx']);

// Session configuration
define('SESSION_LIFETIME', 3600 * 24); // 24 hours
define('SESSION_NAME', 'talent_agency_session');

// Email configuration
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_SECURE', $_ENV['SMTP_SECURE'] ?? 'tls');

// Commission settings (default)
define('DEFAULT_COMMISSION_PERCENTAGE', 15.00);

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (turn off in production)
if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);

session_name(SESSION_NAME);
session_set_cookie_params(SESSION_LIFETIME);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}