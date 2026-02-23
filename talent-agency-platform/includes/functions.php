<?php
// includes/functions.php

/**
 * Sanitize input data
 */
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Format date
 */
function formatDate($date, $format = 'd M Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

/**
 * Format datetime
 */
function formatDateTime($datetime, $format = 'd M Y H:i') {
    if (empty($datetime)) return '';
    return date($format, strtotime($datetime));
}

/**
 * Time ago
 */
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return floor($diff / 31536000) . ' years ago';
}

/**
 * Format currency
 */
function formatCurrency($amount, $currency = DEFAULT_CURRENCY) {
    if ($currency === 'IDR') {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    } elseif ($currency === 'USD') {
        return '$' . number_format($amount, 2, '.', ',');
    }
    return $currency . ' ' . number_format($amount, 2);
}

function formatSalaryRange($min, $max, $type = 'monthly') {
    $suffix = '/' . $type;
    if ($min && $max) {
        return formatCurrency($min) . ' - ' . formatCurrency($max) . $suffix;
    } elseif ($min) {
        return 'From ' . formatCurrency($min) . $suffix;
    } elseif ($max) {
        return 'Up to ' . formatCurrency($max) . $suffix;
    }
    return 'Negotiable';
}

/**
 * Generate random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = generateToken();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Flash message
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get current user ID
 */
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user role
 */
function getCurrentUserRole() {
    return $_SESSION['role'] ?? null;
}

/**
 * Check if user has role
 */
function hasRole($roles) {
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    return in_array(getCurrentUserRole(), $roles);
}

/**
 * Get avatar URL
 */
function getAvatarUrl($photo_url) {
    if (empty($photo_url)) {
        return SITE_URL . '/public/assets/images/placeholder-avatar.png';
    }
    if (strpos($photo_url, 'http') === 0) {
        return $photo_url;
    }
    // Strip any accidental leading slash then prepend SITE_URL
    return SITE_URL . '/' . ltrim($photo_url, '/');
}

/**
 * Get company logo URL
 */
function getCompanyLogoUrl($logo_url) {
    if (empty($logo_url)) {
        return SITE_URL . '/public/assets/images/placeholder-company.png';
    }
    if (strpos($logo_url, 'http') === 0) {
        return $logo_url;
    }
    return SITE_URL . '/' . ltrim($logo_url, '/');
}

/**
 * Truncate text
 */
function truncate($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Slugify text
 */
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

/**
 * Get file extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Get file size in readable format
 */
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}

/**
 * Check if request is AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Get pagination
 */
function getPagination($total, $current_page, $per_page = ITEMS_PER_PAGE) {
    $total_pages = ceil($total / $per_page);
    
    return [
        'total' => $total,
        'current_page' => $current_page,
        'per_page' => $per_page,
        'total_pages' => $total_pages,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages,
        'previous_page' => $current_page - 1,
        'next_page' => $current_page + 1
    ];
}

/**
 * Render pagination HTML
 */
function renderPagination($pagination, $base_url) {
    if ($pagination['total_pages'] <= 1) return '';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination">';
    
    // Previous button
    if ($pagination['has_previous']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $pagination['previous_page'] . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        if ($i == $pagination['current_page']) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($pagination['has_next']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . '?page=' . $pagination['next_page'] . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

/**
 * Get status badge class
 */
function getStatusBadgeClass($status) {
    $classes = [
        STATUS_ACTIVE => 'badge-success',
        STATUS_INACTIVE => 'badge-secondary',
        STATUS_SUSPENDED => 'badge-danger',
        JOB_STATUS_ACTIVE => 'badge-success',
        JOB_STATUS_PENDING => 'badge-warning',
        JOB_STATUS_FILLED => 'badge-info',
        JOB_STATUS_CLOSED => 'badge-secondary',
        APP_STATUS_PENDING => 'badge-warning',
        APP_STATUS_REVIEWED => 'badge-info',
        APP_STATUS_SHORTLISTED => 'badge-primary',
        APP_STATUS_ACCEPTED => 'badge-success',
        APP_STATUS_REJECTED => 'badge-danger',
        CONTRACT_STATUS_ACTIVE => 'badge-success',
        CONTRACT_STATUS_COMPLETED => 'badge-info',
        CONTRACT_STATUS_TERMINATED => 'badge-danger',
        PAYMENT_STATUS_PENDING => 'badge-warning',
        PAYMENT_STATUS_COMPLETED => 'badge-success',
        PAYMENT_STATUS_FAILED => 'badge-danger',
    ];
    
    return $classes[$status] ?? 'badge-secondary';
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone
 */
function isValidPhone($phone) {
    return preg_match('/^[0-9\-\+\(\) ]{10,20}$/', $phone);
}

/**
 * Get request method
 */
function getRequestMethod() {
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Get POST data as JSON
 */
function getJsonInput() {
    return json_decode(file_get_contents('php://input'), true);
}

function getApplicationStatusBadge($status) {
    $map = [
        'pending'     => 'bg-warning text-dark',
        'reviewed'    => 'bg-info text-white',
        'shortlisted' => 'bg-primary text-white',
        'accepted'    => 'bg-success text-white',
        'rejected'    => 'bg-danger text-white',
    ];
    $class = $map[$status] ?? 'bg-secondary text-white';
    return '<span class="badge ' . $class . '">' . ucfirst($status) . '</span>';
}

function getJobStatusBadge($status) {
    $map = [
        'draft'            => 'bg-secondary text-white',
        'pending_approval' => 'bg-warning text-dark',
        'active'           => 'bg-success text-white',
        'filled'           => 'bg-primary text-white',
        'closed'           => 'bg-danger text-white',
    ];
    $class = $map[$status] ?? 'bg-secondary text-white';
    return '<span class="badge ' . $class . '">' . ucfirst(str_replace('_', ' ', $status)) . '</span>';
}

/**
 * Display and clear flash message (renders Bootstrap alert HTML)
 */
function flashMessage() {
    $flash = getFlash();

    // Also support the legacy $_SESSION['flash_success/error/info'] keys
    if (!$flash) {
        if (!empty($_SESSION['flash_success'])) {
            $flash = ['type' => 'success', 'message' => $_SESSION['flash_success']];
            unset($_SESSION['flash_success']);
        } elseif (!empty($_SESSION['flash_error'])) {
            $flash = ['type' => 'danger', 'message' => $_SESSION['flash_error']];
            unset($_SESSION['flash_error']);
        } elseif (!empty($_SESSION['flash_info'])) {
            $flash = ['type' => 'info', 'message' => $_SESSION['flash_info']];
            unset($_SESSION['flash_info']);
        } elseif (!empty($_SESSION['flash_warning'])) {
            $flash = ['type' => 'warning', 'message' => $_SESSION['flash_warning']];
            unset($_SESSION['flash_warning']);
        }
    }

    if (!$flash) return;

    $type    = htmlspecialchars($flash['type']);
    $message = htmlspecialchars($flash['message']);

    echo <<<HTML
<div class="alert alert-{$type} alert-dismissible fade show" role="alert">
    {$message}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
HTML;
}