<?php
// public/serve-file.php
// Secure file serving with permission checks

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Security.php';

$database = new Database($db);

// Get parameters
$type = $_GET['type'] ?? '';
$file = $_GET['file'] ?? '';

if (empty($type) || empty($file)) {
    http_response_code(400);
    die('Invalid request');
}

// Prevent directory traversal
if (strpos($file, '..') !== false || strpos($file, '/') === 0) {
    http_response_code(400);
    die('Invalid file path');
}

// Define allowed file types and directories
$allowed_types = [
    'profile' => 'uploads/profiles',
    'resume' => 'uploads/resumes',
    'portfolio' => 'uploads/portfolios',
    'logo' => 'uploads/company-logos',
    'document' => 'uploads/documents'
];

if (!isset($allowed_types[$type])) {
    http_response_code(400);
    die('Invalid file type');
}

// Build file path
$file_path = __DIR__ . '/../' . $allowed_types[$type] . '/' . basename($file);

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    die('File not found');
}

// Permission checks based on file type
$current_user_id = getCurrentUserId();
$current_role = getCurrentUserRole();

switch ($type) {
    case 'resume':
        // Only the talent who owns it, employers who received application, or admin can view
        if (!canViewResume($database, $file, $current_user_id, $current_role)) {
            http_response_code(403);
            die('Access denied');
        }
        break;
        
    case 'document':
        // Only users involved in the contract or admin can view
        if (!canViewDocument($database, $file, $current_user_id, $current_role)) {
            http_response_code(403);
            die('Access denied');
        }
        break;
        
    case 'profile':
    case 'logo':
    case 'portfolio':
        // These are publicly viewable
        break;
}

// Set appropriate headers
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_path);
finfo_close($finfo);

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));

// For documents, force download
if ($type === 'resume' || $type === 'document') {
    header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
} else {
    header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
}

// Prevent caching of sensitive documents
if ($type === 'resume' || $type === 'document') {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
} else {
    header('Cache-Control: public, max-age=31536000');
}

// Security headers
Security::setSecurityHeaders();

// Output file
readfile($file_path);

/**
 * Check if user can view resume
 */
function canViewResume($database, $file, $user_id, $role) {
    // Admin can view all
    if ($role === ROLE_SUPER_ADMIN || $role === ROLE_STAFF) {
        return true;
    }
    
    // Get talent who owns this resume
    $sql = "SELECT user_id FROM talents WHERE resume_url = ?";
    $talent = $database->fetchOne($sql, ['uploads/resumes/' . basename($file)]);
    
    if (!$talent) {
        return false;
    }
    
    // Talent can view their own resume
    if ($talent['user_id'] == $user_id) {
        return true;
    }
    
    // Employer can view if talent applied to their job
    if ($role === ROLE_EMPLOYER) {
        $sql = "SELECT COUNT(*) FROM applications a
                INNER JOIN jobs j ON a.job_id = j.id
                INNER JOIN employers e ON j.employer_id = e.id
                INNER JOIN talents t ON a.talent_id = t.id
                WHERE e.user_id = ? AND t.user_id = ?";
        
        return $database->fetchColumn($sql, [$user_id, $talent['user_id']]) > 0;
    }
    
    return false;
}

/**
 * Check if user can view document
 */
function canViewDocument($database, $file, $user_id, $role) {
    // Admin can view all
    if ($role === ROLE_SUPER_ADMIN || $role === ROLE_STAFF) {
        return true;
    }
    
    // Get contract associated with this document
    $sql = "SELECT c.*, t.user_id as talent_user_id, e.user_id as employer_user_id
            FROM contracts c
            INNER JOIN talents t ON c.talent_id = t.id
            INNER JOIN employers e ON c.employer_id = e.id
            WHERE c.contract_document_url = ?";
    
    $contract = $database->fetchOne($sql, ['uploads/documents/' . basename($file)]);
    
    if (!$contract) {
        return false;
    }
    
    // User must be either the talent or employer in this contract
    return $contract['talent_user_id'] == $user_id || $contract['employer_user_id'] == $user_id;
}