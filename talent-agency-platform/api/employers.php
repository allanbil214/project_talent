<?php
// api/employers.php

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Employer.php';
require_once __DIR__ . '/../classes/Upload.php';
require_once __DIR__ . '/../classes/Validator.php';
require_once __DIR__ . '/../includes/functions.php';

$db_connection = require __DIR__ . '/../config/database.php';
$db = new Database($db_connection);
$employer_model = new Employer($db);
$upload = new Upload();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
}

try {
    switch ($action) {

        case 'get':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'Employer ID required'], 400);
            }

            $employer = $employer_model->getById($id);
            if (!$employer) {
                jsonResponse(['success' => false, 'message' => 'Employer not found'], 404);
            }

            jsonResponse(['success' => true, 'data' => $employer]);
            break;

        case 'update':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (getCurrentUserRole() !== ROLE_EMPLOYER) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $employer = $employer_model->getByUserId(getCurrentUserId());
            if (!$employer) {
                jsonResponse(['success' => false, 'message' => 'Employer profile not found'], 404);
            }

            $data = $_POST;

            $validator = new Validator($data);
            $valid = $validator->validate([
                'company_name' => 'required|max:255',
                'phone'        => 'max:50',
                'website'      => 'url',
            ]);

            if (!$valid) {
                jsonResponse(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()], 422);
            }

            $updated = $employer_model->update($employer['id'], $data);

            if ($updated) {
                jsonResponse(['success' => true, 'message' => 'Company profile updated successfully.']);
            } else {
                jsonResponse(['success' => false, 'message' => 'Failed to update profile'], 500);
            }
            break;

        case 'upload_logo':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (getCurrentUserRole() !== ROLE_EMPLOYER) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $employer = $employer_model->getByUserId(getCurrentUserId());
            if (!$employer) {
                jsonResponse(['success' => false, 'message' => 'Employer profile not found'], 404);
            }

            if (!isset($_FILES['company_logo'])) {
                jsonResponse(['success' => false, 'message' => 'No file uploaded'], 400);
            }

            try {
                $logo_path = $upload->uploadCompanyLogo($_FILES['company_logo']);

                if ($employer['company_logo_url']) {
                    $old = __DIR__ . '/../' . $employer['company_logo_url'];
                    if (file_exists($old)) unlink($old);
                }

                $employer_model->updateLogo($employer['id'], $logo_path);

                jsonResponse([
                    'success'  => true,
                    'message'  => 'Logo uploaded successfully.',
                    'logo_url' => SITE_URL . '/' . $logo_path
                ]);
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
            }
            break;

        case 'get_stats':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (getCurrentUserRole() !== ROLE_EMPLOYER) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $employer = $employer_model->getByUserId(getCurrentUserId());
            if (!$employer) {
                jsonResponse(['success' => false, 'message' => 'Employer profile not found'], 404);
            }

            $stats = $employer_model->getStats($employer['id']);
            jsonResponse(['success' => true, 'data' => $stats]);
            break;

        case 'get_recent_applications':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }

            if (getCurrentUserRole() !== ROLE_EMPLOYER) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $employer = $employer_model->getByUserId(getCurrentUserId());
            if (!$employer) {
                jsonResponse(['success' => false, 'message' => 'Employer profile not found'], 404);
            }

            $limit = (int)($_GET['limit'] ?? 10);
            $applications = $employer_model->getRecentApplications($employer['id'], $limit);
            jsonResponse(['success' => true, 'data' => $applications]);
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }

} catch (Exception $e) {
    error_log("API Error in employers.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    jsonResponse(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
}
