<?php
// api/skills.php

// 1. Start session FIRST
require_once __DIR__ . '/../config/session.php';

// 2. Set headers
header('Content-Type: application/json');

// 3. Load other configs
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Skill.php';
require_once __DIR__ . '/../classes/Validator.php';
require_once __DIR__ . '/../includes/functions.php';

// 4. Get database connection - FIX THIS PART
$db_connection = require __DIR__ . '/../config/database.php';
$db = new Database($db_connection);
$skill_model = new Skill($db);

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($action) {
        case 'get_all':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            $skills = $skill_model->getAll();
            
            jsonResponse([
                'success' => true,
                'data' => $skills
            ]);
            break;
            
        case 'get':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            $id = $_GET['id'] ?? null;
            if (!$id) {
                jsonResponse(['success' => false, 'message' => 'Skill ID required'], 400);
            }
            
            $skill = $skill_model->getById($id);
            if (!$skill) {
                jsonResponse(['success' => false, 'message' => 'Skill not found'], 404);
            }
            
            jsonResponse([
                'success' => true,
                'data' => $skill
            ]);
            break;
            
        case 'get_by_category':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            $category = $_GET['category'] ?? null;
            if (!$category) {
                jsonResponse(['success' => false, 'message' => 'Category required'], 400);
            }
            
            $skills = $skill_model->getByCategory($category);
            
            jsonResponse([
                'success' => true,
                'data' => $skills
            ]);
            break;
            
        case 'get_categories':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            $categories = $skill_model->getCategories();
            
            jsonResponse([
                'success' => true,
                'data' => $categories
            ]);
            break;
            
        case 'search':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            $keyword = $_GET['keyword'] ?? '';
            if (empty($keyword)) {
                jsonResponse(['success' => false, 'message' => 'Search keyword required'], 400);
            }
            
            $skills = $skill_model->search($keyword);
            
            jsonResponse([
                'success' => true,
                'data' => $skills
            ]);
            break;
            
        case 'create':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            // Check authentication
            if (!isLoggedIn()) {
                jsonResponse(['success' => false, 'message' => 'Authentication required'], 401);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate
            $validator = new Validator($data);
            $valid = $validator->validate([
                'name' => 'required|max:100'
            ]);
            
            if (!$valid) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->getErrors()
                ], 422);
            }
            
            // Check if skill name already exists
            if ($skill_model->nameExists($data['name'])) {
                jsonResponse([
                    'success' => false,
                    'message' => 'A skill with this name already exists'
                ], 400);
            }
            
            $skill_id = $skill_model->create($data);
            
            jsonResponse([
                'success' => true,
                'message' => 'Skill created successfully',
                'skill_id' => $skill_id
            ]);
            break;
            
        case 'update':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            // Check authentication and admin role
            if (!isLoggedIn() || !hasRole([ROLE_SUPER_ADMIN, ROLE_STAFF])) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                jsonResponse(['success' => false, 'message' => 'Skill ID required'], 400);
            }
            
            // Validate
            $validator = new Validator($data);
            $valid = $validator->validate([
                'name' => 'required|max:100'
            ]);
            
            if (!$valid) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->getErrors()
                ], 422);
            }
            
            // Check if skill name already exists (excluding current skill)
            if ($skill_model->nameExists($data['name'], $data['id'])) {
                jsonResponse([
                    'success' => false,
                    'message' => 'A skill with this name already exists'
                ], 400);
            }
            
            $updated = $skill_model->update($data['id'], $data);
            
            if ($updated) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Skill updated successfully'
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'message' => 'Failed to update skill'
                ], 500);
            }
            break;
            
        case 'delete':
            if ($method !== 'POST') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            // Check authentication and admin role
            if (!isLoggedIn() || !hasRole([ROLE_SUPER_ADMIN, ROLE_STAFF])) {
                jsonResponse(['success' => false, 'message' => 'Unauthorized'], 403);
            }
            
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                jsonResponse(['success' => false, 'message' => 'Skill ID required'], 400);
            }
            
            $deleted = $skill_model->delete($data['id']);
            
            if ($deleted) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Skill deleted successfully'
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'message' => 'Failed to delete skill'
                ], 500);
            }
            break;
            
        case 'get_popular':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $skills = $skill_model->getPopular($limit);
            
            jsonResponse([
                'success' => true,
                'data' => $skills
            ]);
            break;
            
        case 'get_with_count':
            if ($method !== 'GET') {
                jsonResponse(['success' => false, 'message' => 'Method not allowed'], 405);
            }
            
            $skills = $skill_model->getWithTalentCount();
            
            jsonResponse([
                'success' => true,
                'data' => $skills
            ]);
            break;
            
        default:
            jsonResponse(['success' => false, 'message' => 'Invalid action'], 400);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ], 500);
}