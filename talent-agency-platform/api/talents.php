<?php
// api/talents.php

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Talent.php';
require_once __DIR__ . '/../classes/Upload.php';
require_once __DIR__ . '/../classes/Validator.php';
require_once __DIR__ . '/../includes/functions.php';

// Get database connection (only once)
$db_connection = require __DIR__ . '/../config/database.php';
$db = new Database($db_connection);
$talent_model = new Talent($db);
$upload = new Upload();

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Check authentication
if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
}

try {
    switch ($action) {
        case 'get':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            $talent_id = $_GET['id'] ?? null;
            if (!$talent_id) {
                jsonResponse(['success' => false, 'message' => 'Talent ID required'], 400);
            }
            
            $talent = $talent_model->getById($talent_id);
            if (!$talent) {
                jsonResponse(['success' => false, 'message' => 'Talent not found'], 404);
            }
            
            $talent['skills'] = $talent_model->getSkills($talent_id);
            
            jsonResponse(['success' => true, 'data' => $talent]);
            break;
            
        case 'update':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (getCurrentUserRole() !== ROLE_TALENT) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $talent = $talent_model->getByUserId(getCurrentUserId());
            if (!$talent) {
                jsonResponse(['success' => false, 'message' => 'Talent profile not found'], 404);
            }
            
            $data = $_POST;
            
            // Validate
            $validator = new Validator($data);
            $valid = $validator->validate([
                'full_name' => 'required|max:255',
                'phone' => 'max:50',
                'city' => 'max:100',
                'country' => 'max:100',
                'hourly_rate' => 'numeric',
                'years_experience' => 'integer',
                'portfolio_url' => 'url'
            ]);
            
            if (!$valid) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->getErrors()
                ], 422);
            }
            
            // Handle work preferences
            if (isset($data['preferred_work_type']) && is_array($data['preferred_work_type'])) {
                $data['preferred_work_type'] = implode(',', $data['preferred_work_type']);
            }
            
            $updated = $talent_model->update($talent['id'], $data);
            
            if ($updated) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Profile updated successfully'
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update profile'
                ], 500);
            }
            break;
            
        case 'upload_photo':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (getCurrentUserRole() !== ROLE_TALENT) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $talent = $talent_model->getByUserId(getCurrentUserId());
            if (!$talent) {
                jsonResponse(['success' => false, 'message' => 'Talent profile not found'], 404);
            }
            
            if (!isset($_FILES['profile_photo'])) {
                jsonResponse(['success' => false, 'message' => 'No file uploaded'], 400);
            }
            
            // Log the file info for debugging
            error_log("Profile photo upload attempt: " . print_r($_FILES['profile_photo'], true));
            
            try {
                $photo_path = $upload->uploadProfilePhoto($_FILES['profile_photo']);
                
                // Delete old photo if exists
                if ($talent['profile_photo_url']) {
                    $old_photo_path = __DIR__ . '/../' . $talent['profile_photo_url'];
                    if (file_exists($old_photo_path)) {
                        unlink($old_photo_path);
                    }
                }
                
                $talent_model->updateProfilePhoto($talent['id'], $photo_path);
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Photo uploaded successfully',
                    'photo_url' => SITE_URL . '/' . $photo_path
                ]);
            } catch (Exception $e) {
                error_log("Photo upload error: " . $e->getMessage());
                jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            }
            break;
            
        case 'upload_resume':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (getCurrentUserRole() !== ROLE_TALENT) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $talent = $talent_model->getByUserId(getCurrentUserId());
            if (!$talent) {
                jsonResponse(['success' => false, 'message' => 'Talent profile not found'], 404);
            }
            
            if (!isset($_FILES['resume'])) {
                jsonResponse(['success' => false, 'message' => 'No file uploaded'], 400);
            }
            
            error_log("Resume upload attempt: " . print_r($_FILES['resume'], true));
            
            try {
                $resume_path = $upload->uploadResume($_FILES['resume']);
                
                // Delete old resume if exists
                if ($talent['resume_url']) {
                    $old_resume_path = __DIR__ . '/../' . $talent['resume_url'];
                    if (file_exists($old_resume_path)) {
                        unlink($old_resume_path);
                    }
                }
                
                $talent_model->updateResume($talent['id'], $resume_path);
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Resume uploaded successfully',
                    'resume_url' => SITE_URL . '/' . $resume_path
                ]);
            } catch (Exception $e) {
                error_log("Resume upload error: " . $e->getMessage());
                jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 400);
            }
            break;
            
        case 'add_skill':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (getCurrentUserRole() !== ROLE_TALENT) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $talent = $talent_model->getByUserId(getCurrentUserId());
            if (!$talent) {
                jsonResponse(['success' => false, 'message' => 'Talent profile not found'], 404);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['skill_id'])) {
                jsonResponse(['success' => false, 'message' => 'Skill ID required'], 400);
            }
            
            $proficiency = $data['proficiency_level'] ?? PROFICIENCY_INTERMEDIATE;
            
            $talent_model->addSkill($talent['id'], $data['skill_id'], $proficiency);
            
            jsonResponse([
                'success' => true,
                'message' => 'Skill added successfully'
            ]);
            break;
            
        case 'update_skill':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (getCurrentUserRole() !== ROLE_TALENT) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $talent = $talent_model->getByUserId(getCurrentUserId());
            if (!$talent) {
                jsonResponse(['success' => false, 'message' => 'Talent profile not found'], 404);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['skill_id']) || empty($data['proficiency_level'])) {
                jsonResponse(['success' => false, 'message' => 'Skill ID and proficiency level required'], 400);
            }
            
            $talent_model->updateSkillProficiency(
                $talent['id'], 
                $data['skill_id'], 
                $data['proficiency_level']
            );
            
            jsonResponse([
                'success' => true,
                'message' => 'Skill updated successfully'
            ]);
            break;
            
        case 'remove_skill':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (getCurrentUserRole() !== ROLE_TALENT) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $talent = $talent_model->getByUserId(getCurrentUserId());
            if (!$talent) {
                jsonResponse(['success' => false, 'message' => 'Talent profile not found'], 404);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['skill_id'])) {
                jsonResponse(['success' => false, 'message' => 'Skill ID required'], 400);
            }
            
            $talent_model->removeSkill($talent['id'], $data['skill_id']);
            
            jsonResponse([
                'success' => true,
                'message' => 'Skill removed successfully'
            ]);
            break;
            
        case 'update_availability':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (getCurrentUserRole() !== ROLE_TALENT) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $talent = $talent_model->getByUserId(getCurrentUserId());
            if (!$talent) {
                jsonResponse(['success' => false, 'message' => 'Talent profile not found'], 404);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['status'])) {
                jsonResponse(['success' => false, 'message' => 'Status required'], 400);
            }
            
            $talent_model->updateAvailability($talent['id'], $data['status']);
            
            jsonResponse([
                'success' => true,
                'message' => 'Availability updated successfully'
            ]);
            break;
            
        case 'get_stats':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            if (getCurrentUserRole() !== ROLE_TALENT) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $talent = $talent_model->getByUserId(getCurrentUserId());
            if (!$talent) {
                jsonResponse(['success' => false, 'message' => 'Talent profile not found'], 404);
            }
            
            $stats = $talent_model->getStats($talent['id']);
            
            jsonResponse([
                'success' => true,
                'data' => $stats
            ]);
            break;
            
        case 'search':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            $filters = [
                'keyword' => $_GET['keyword'] ?? '',
                'location' => $_GET['location'] ?? '',
                'availability' => $_GET['availability'] ?? '',
                'min_experience' => $_GET['min_experience'] ?? '',
                'max_experience' => $_GET['max_experience'] ?? '',
                'min_rate' => $_GET['min_rate'] ?? '',
                'max_rate' => $_GET['max_rate'] ?? '',
                'verified_only' => isset($_GET['verified_only']) && $_GET['verified_only'] == '1'
            ];
            
            $talents = $talent_model->search($filters);
            
            jsonResponse([
                'success' => true,
                'data' => $talents
            ]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    error_log("API Error in talents.php: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    jsonResponse([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}