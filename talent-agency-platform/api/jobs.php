<?php
// api/jobs.php

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Job.php';
require_once __DIR__ . '/../classes/Employer.php';
require_once __DIR__ . '/../classes/Validator.php';
require_once __DIR__ . '/../includes/functions.php';

$db_connection = require __DIR__ . '/../config/database.php';
$db = new Database($db_connection);
$job_model = new Job($db);
$employer_model = new Employer($db);

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {

        // ----------------------------------------------------------------
        // PUBLIC / TALENT: search active jobs
        // ----------------------------------------------------------------
        case 'search':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            $filters = [
                'keyword'        => $_GET['keyword'] ?? '',
                'job_type'       => $_GET['job_type'] ?? '',
                'location_type'  => $_GET['location_type'] ?? '',
                'location'       => $_GET['location'] ?? '',
                'min_salary'     => $_GET['min_salary'] ?? '',
                'max_salary'     => $_GET['max_salary'] ?? '',
                'experience_max' => $_GET['experience_max'] ?? '',
                'sort'           => $_GET['sort'] ?? '',
                'deadline_after' => date('Y-m-d'),
            ];

            $page = max(1, (int)($_GET['page'] ?? 1));
            $result = $job_model->search($filters, $page);

            jsonResponse(['success' => true, 'data' => $result['data'], 'pagination' => $result['pagination']]);
            break;

        // ----------------------------------------------------------------
        // PUBLIC: get single job
        // ----------------------------------------------------------------
        case 'get':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'Job ID required'], 400);
            }

            $job = $job_model->getById($id);
            if (!$job) {
                jsonResponse(['success' => false, 'message' => 'Job not found'], 404);
            }

            jsonResponse(['success' => true, 'job' => $job]);
            break;

        // ----------------------------------------------------------------
        // EMPLOYER: create job
        // ----------------------------------------------------------------
        case 'create':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (!isLoggedIn() || getCurrentUserRole() !== ROLE_EMPLOYER) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $employer = $employer_model->getByUserId(getCurrentUserId());
            if (!$employer) {
                jsonResponse(['success' => false, 'message' => 'Employer profile not found'], 404);
            }

            $data = json_decode(file_get_contents('php://input'), true);

            $validator = new Validator($data);
            $valid = $validator->validate([
                'title'       => 'required|max:255',
                'description' => 'required',
                'job_type'    => 'required',
                'location_type' => 'required',
            ]);

            if (!$valid) {
                jsonResponse(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()], 422);
            }

            $job_id = $job_model->create($employer['id'], $data);

            jsonResponse([
                'success'  => true,
                'message'  => 'Job posted successfully. Pending admin approval.',
                'job_id'   => $job_id
            ]);
            break;

        // ----------------------------------------------------------------
        // EMPLOYER: update job
        // ----------------------------------------------------------------
        case 'update':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (!isLoggedIn() || getCurrentUserRole() !== ROLE_EMPLOYER) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['id'])) {
                jsonResponse(['success' => false, 'message' => 'Job ID required'], 400);
            }

            $employer = $employer_model->getByUserId(getCurrentUserId());
            if (!$employer || !$job_model->belongsToEmployer($data['id'], $employer['id'])) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $validator = new Validator($data);
            $valid = $validator->validate([
                'title'         => 'required|max:255',
                'description'   => 'required',
                'job_type'      => 'required',
                'location_type' => 'required',
            ]);

            if (!$valid) {
                jsonResponse(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()], 422);
            }

            $updated = $job_model->update($data['id'], $data);

            if ($updated) {
                jsonResponse(['success' => true, 'message' => 'Job updated and re-submitted for approval.']);
            } else {
                jsonResponse(['success' => false, 'message' => 'Failed to update job'], 500);
            }
            break;

        // ----------------------------------------------------------------
        // EMPLOYER: delete (close) job
        // ----------------------------------------------------------------
        case 'delete':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (!isLoggedIn() || getCurrentUserRole() !== ROLE_EMPLOYER) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['id'])) {
                jsonResponse(['success' => false, 'message' => 'Job ID required'], 400);
            }

            $employer = $employer_model->getByUserId(getCurrentUserId());
            if (!$employer || !$job_model->belongsToEmployer($data['id'], $employer['id'])) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $job_model->delete($data['id']);
            jsonResponse(['success' => true, 'message' => 'Job closed successfully.']);
            break;

        // ----------------------------------------------------------------
        // EMPLOYER: get own jobs
        // ----------------------------------------------------------------
        case 'get_mine':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (!isLoggedIn() || getCurrentUserRole() !== ROLE_EMPLOYER) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $employer = $employer_model->getByUserId(getCurrentUserId());
            if (!$employer) {
                jsonResponse(['success' => false, 'message' => 'Employer profile not found'], 404);
            }

            $filters = [
                'status' => $_GET['status'] ?? '',
                'search' => $_GET['search'] ?? '',
            ];

            $page = max(1, (int)($_GET['page'] ?? 1));
            $result = $job_model->getByEmployer($employer['id'], $page, JOBS_PER_PAGE, $filters);

            jsonResponse(['success' => true, 'data' => $result['data'], 'pagination' => $result['pagination']]);
            break;

        // ----------------------------------------------------------------
        // EMPLOYER: get applications for a job
        // ----------------------------------------------------------------
        case 'get_applications':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (!isLoggedIn() || getCurrentUserRole() !== ROLE_EMPLOYER) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $job_id = $_GET['job_id'] ?? null;
            if (!$job_id) {
                jsonResponse(['success' => false, 'message' => 'Job ID required'], 400);
            }

            $employer = $employer_model->getByUserId(getCurrentUserId());
            if (!$employer || !$job_model->belongsToEmployer($job_id, $employer['id'])) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $status = $_GET['status'] ?? null;
            $applications = $job_model->getApplications($job_id, $status);

            jsonResponse(['success' => true, 'data' => $applications]);
            break;

        // ----------------------------------------------------------------
        // ADMIN: approve/reject job
        // ----------------------------------------------------------------
        case 'approve':
        case 'reject':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (!isLoggedIn() || !hasRole([ROLE_SUPER_ADMIN, ROLE_STAFF])) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['id'])) {
                jsonResponse(['success' => false, 'message' => 'Job ID required'], 400);
            }

            $new_status = ($action === 'approve') ? JOB_STATUS_ACTIVE : JOB_STATUS_CLOSED;
            $job_model->updateStatus($data['id'], $new_status);

            jsonResponse(['success' => true, 'message' => 'Job ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully.']);
            break;

        // ----------------------------------------------------------------
        // ADMIN/EMPLOYER: update status manually
        // ----------------------------------------------------------------
        case 'update_status':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (!isLoggedIn()) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['id']) || empty($data['status'])) {
                jsonResponse(['success' => false, 'message' => 'Job ID and status required'], 400);
            }

            // Employers can only close or mark filled their own jobs
            if (getCurrentUserRole() === ROLE_EMPLOYER) {
                $employer = $employer_model->getByUserId(getCurrentUserId());
                if (!$employer || !$job_model->belongsToEmployer($data['id'], $employer['id'])) {
                    jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
                }
                if (!in_array($data['status'], [JOB_STATUS_CLOSED, JOB_STATUS_FILLED])) {
                    jsonResponse(['success' => false, 'message' => 'Invalid status'], 400);
                }
            } elseif (!hasRole([ROLE_SUPER_ADMIN, ROLE_STAFF])) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $job_model->updateStatus($data['id'], $data['status']);
            jsonResponse(['success' => true, 'message' => 'Status updated.']);
            break;

        // ----------------------------------------------------------------
        // EMPLOYER: get employer stats
        // ----------------------------------------------------------------
        case 'get_stats':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (!isLoggedIn() || getCurrentUserRole() !== ROLE_EMPLOYER) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $employer = $employer_model->getByUserId(getCurrentUserId());
            if (!$employer) {
                jsonResponse(['success' => false, 'message' => 'Employer profile not found'], 404);
            }

            $stats = $employer_model->getStats($employer['id']);
            jsonResponse(['success' => true, 'data' => $stats]);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }

} catch (Exception $e) {
    error_log("API Error in jobs.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    jsonResponse(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
}
