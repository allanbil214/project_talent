<?php
// api/applications.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

// Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db_connection = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Application.php';

$database  = new Database($db_connection);
$app_model = new Application($database);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'status_counts':
                    requireRole_api([ROLE_EMPLOYER, ROLE_STAFF, ROLE_SUPER_ADMIN]);
                    $job_id = (int)($_GET['job_id'] ?? 0);
                    if (!$job_id) throw new Exception('job_id required');
                    echo json_encode(['success' => true, 'data' => $app_model->getStatusCounts($job_id)]);
                    break;

                case 'talent_stats':
                    requireRole_api([ROLE_TALENT]);
                    // Get talent_id from session user
                    $talent = $database->fetchOne(
                        "SELECT id FROM talents WHERE user_id = ?",
                        [$_SESSION['user_id']]
                    );
                    if (!$talent) throw new Exception('Talent profile not found');
                    echo json_encode(['success' => true, 'data' => $app_model->getTalentStats($talent['id'])]);
                    break;

                default:
                    throw new Exception('Unknown action');
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            // CSRF check for non-JSON
            if (!empty($_POST) && !verifyCsrfToken($input['csrf_token'] ?? '')) {
                throw new Exception('Invalid CSRF token');
            }

            switch ($action) {
                case 'apply':
                    requireRole_api([ROLE_TALENT]);
                    $talent = $database->fetchOne(
                        "SELECT id FROM talents WHERE user_id = ?",
                        [$_SESSION['user_id']]
                    );
                    if (!$talent) throw new Exception('Talent profile not found');

                    $job_id = (int)($input['job_id'] ?? 0);
                    if (!$job_id) throw new Exception('job_id required');

                    $id = $app_model->create($talent['id'], $job_id, [
                        'cover_letter'  => $input['cover_letter'] ?? null,
                        'proposed_rate' => !empty($input['proposed_rate']) ? (float)$input['proposed_rate'] : null,
                    ]);

                    echo json_encode(['success' => true, 'message' => 'Application submitted', 'application_id' => $id]);
                    break;

                case 'update_status':
                    requireRole_api([ROLE_EMPLOYER, ROLE_STAFF, ROLE_SUPER_ADMIN]);

                    $app_id    = (int)($input['app_id'] ?? 0);
                    $status    = $input['status'] ?? '';
                    $employer_id = null;

                    // Employers can only update their own job's applications
                    if ($_SESSION['role'] === ROLE_EMPLOYER) {
                        $employer = $database->fetchOne(
                            "SELECT id FROM employers WHERE user_id = ?",
                            [$_SESSION['user_id']]
                        );
                        if (!$employer) throw new Exception('Employer profile not found');
                        $employer_id = $employer['id'];
                    }

                    $app_model->updateStatus($app_id, $status, $employer_id);
                    echo json_encode(['success' => true, 'message' => 'Status updated']);
                    break;

                case 'toggle_recommendation':
                    requireRole_api([ROLE_STAFF, ROLE_SUPER_ADMIN]);
                    $app_id = (int)($input['app_id'] ?? 0);
                    if (!$app_id) throw new Exception('app_id required');
                    $app_model->toggleRecommendation($app_id);
                    echo json_encode(['success' => true, 'message' => 'Recommendation toggled']);
                    break;

                case 'withdraw':
                    requireRole_api([ROLE_TALENT]);
                    $talent = $database->fetchOne(
                        "SELECT id FROM talents WHERE user_id = ?",
                        [$_SESSION['user_id']]
                    );
                    if (!$talent) throw new Exception('Talent profile not found');

                    $app_id = (int)($input['app_id'] ?? 0);
                    if (!$app_id) throw new Exception('app_id required');

                    $app_model->withdraw($app_id, $talent['id']);
                    echo json_encode(['success' => true, 'message' => 'Application withdrawn']);
                    break;

                default:
                    throw new Exception('Unknown action');
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Inline role check for API
 */
function requireRole_api($roles) {
    if (!in_array($_SESSION['role'] ?? '', $roles)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}
