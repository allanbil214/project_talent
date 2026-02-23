<?php
// api/contracts.php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db_connection = require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Contract.php';

$database       = new Database($db_connection);
$contract_model = new Contract($database);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function requireRole_api($roles) {
    if (!in_array($_SESSION['role'] ?? '', $roles)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

function resolveActorId($db, $role) {
    if ($role === ROLE_EMPLOYER) {
        $r = $db->fetchOne("SELECT id FROM employers WHERE user_id = ?", [$_SESSION['user_id']]);
        return $r ? $r['id'] : null;
    }
    if ($role === ROLE_TALENT) {
        $r = $db->fetchOne("SELECT id FROM talents WHERE user_id = ?", [$_SESSION['user_id']]);
        return $r ? $r['id'] : null;
    }
    return null;
}

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'stats':
                    $role = $_SESSION['role'];
                    if ($role === ROLE_EMPLOYER) {
                        requireRole_api([ROLE_EMPLOYER]);
                        $id = resolveActorId($database, ROLE_EMPLOYER);
                        if (!$id) throw new Exception('Employer profile not found');
                        echo json_encode(['success' => true, 'data' => $contract_model->getStats('employer', $id)]);
                    } elseif ($role === ROLE_TALENT) {
                        requireRole_api([ROLE_TALENT]);
                        $id = resolveActorId($database, ROLE_TALENT);
                        if (!$id) throw new Exception('Talent profile not found');
                        echo json_encode(['success' => true, 'data' => $contract_model->getStats('talent', $id)]);
                    } else {
                        requireRole_api([ROLE_STAFF, ROLE_SUPER_ADMIN]);
                        echo json_encode(['success' => true, 'data' => $contract_model->getStats()]);
                    }
                    break;

                case 'get':
                    $contract_id = (int)($_GET['id'] ?? 0);
                    if (!$contract_id) throw new Exception('id required');
                    $contract = $contract_model->getById($contract_id);
                    if (!$contract) throw new Exception('Contract not found');

                    // Access check
                    $role = $_SESSION['role'];
                    if ($role === ROLE_EMPLOYER) {
                        $actor_id = resolveActorId($database, ROLE_EMPLOYER);
                        if ($contract['employer_id'] != $actor_id) throw new Exception('Forbidden');
                    } elseif ($role === ROLE_TALENT) {
                        $actor_id = resolveActorId($database, ROLE_TALENT);
                        if ($contract['talent_id'] != $actor_id) throw new Exception('Forbidden');
                    }

                    echo json_encode(['success' => true, 'data' => $contract]);
                    break;

                default:
                    throw new Exception('Unknown action');
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

            switch ($action) {
                case 'create':
                    requireRole_api([ROLE_EMPLOYER, ROLE_STAFF, ROLE_SUPER_ADMIN]);

                    $employer_id = resolveActorId($database, ROLE_EMPLOYER);
                    if (!$employer_id && in_array($_SESSION['role'], [ROLE_STAFF, ROLE_SUPER_ADMIN])) {
                        $employer_id = (int)($input['employer_id'] ?? 0);
                    }
                    if (!$employer_id) throw new Exception('Employer profile not found');

                    $id = $contract_model->create([
                        'job_id'         => (int)($input['job_id'] ?? 0),
                        'talent_id'      => (int)($input['talent_id'] ?? 0),
                        'employer_id'    => $employer_id,
                        'application_id' => !empty($input['application_id']) ? (int)$input['application_id'] : null,
                        'start_date'     => $input['start_date'] ?? '',
                        'end_date'       => $input['end_date'] ?? null,
                        'rate'           => (float)($input['rate'] ?? 0),
                        'rate_type'      => $input['rate_type'] ?? '',
                        'currency'       => $input['currency'] ?? DEFAULT_CURRENCY,
                        'total_amount'   => !empty($input['total_amount']) ? (float)$input['total_amount'] : null,
                        'agency_commission_percentage' => (float)($input['commission'] ?? DEFAULT_COMMISSION_PERCENTAGE),
                        'contract_document_url' => $input['contract_document_url'] ?? null,
                    ]);

                    echo json_encode(['success' => true, 'message' => 'Contract created', 'contract_id' => $id]);
                    break;

                case 'update_status':
                    $contract_id = (int)($input['contract_id'] ?? 0);
                    $status      = $input['status'] ?? '';
                    $role        = $_SESSION['role'];
                    $actor_id    = resolveActorId($database, $role);

                    $contract_model->updateStatus($contract_id, $status, $actor_id, $role);
                    echo json_encode(['success' => true, 'message' => 'Status updated']);
                    break;

                case 'update':
                    requireRole_api([ROLE_EMPLOYER]);
                    $contract_id = (int)($input['contract_id'] ?? 0);
                    $employer_id = resolveActorId($database, ROLE_EMPLOYER);
                    if (!$employer_id) throw new Exception('Employer profile not found');

                    $contract_model->update($contract_id, $employer_id, [
                        'end_date'              => $input['end_date'] ?? null,
                        'total_amount'          => !empty($input['total_amount']) ? (float)$input['total_amount'] : null,
                        'contract_document_url' => $input['contract_document_url'] ?? null,
                    ]);

                    echo json_encode(['success' => true, 'message' => 'Contract updated']);
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
