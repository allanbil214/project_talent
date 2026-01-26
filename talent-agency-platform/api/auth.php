<?php
// api/auth.php

require_once __DIR__ . '/../config/session.php';

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../classes/Validator.php';
require_once __DIR__ . '/../classes/Mail.php';

$database = new Database($db);
$auth = new Auth($database);
$mail = new Mail();

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'login':
            if (getRequestMethod() !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $data = getJsonInput() ?: $_POST;
            
            // Validate
            $validator = new Validator($data);
            if (!$validator->validate([
                'email' => 'required|email',
                'password' => 'required'
            ])) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->getErrors()
                ], 400);
            }
            
            // Login
            $user = $auth->login($data['email'], $data['password']);
            
            jsonResponse([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ],
                'redirect' => $auth->getRedirectUrl($user['role'])
            ]);
            break;
            
        case 'register':
            if (getRequestMethod() !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $data = getJsonInput() ?: $_POST;
            
            // Validate
            $validator = new Validator($data);
            $rules = [
                'email' => 'required|email',
                'password' => 'required|min:8',
                'role' => 'required|in:' . ROLE_TALENT . ',' . ROLE_EMPLOYER
            ];
            
            if ($data['role'] === ROLE_TALENT) {
                $rules['full_name'] = 'required|max:255';
            } elseif ($data['role'] === ROLE_EMPLOYER) {
                $rules['company_name'] = 'required|max:255';
            }
            
            if (!$validator->validate($rules)) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->getErrors()
                ], 400);
            }
            
            // Register
            $user = $auth->register($data);
            
            // Send welcome email
            $name = $data['role'] === ROLE_TALENT ? $data['full_name'] : $data['company_name'];
            $mail->sendWelcomeEmail($data['email'], $name, $data['role']);
            
            // Auto-login
            $auth->login($data['email'], $data['password']);
            
            jsonResponse([
                'success' => true,
                'message' => 'Registration successful',
                'redirect' => $auth->getRedirectUrl($user['role'])
            ]);
            break;
            
        case 'logout':
            $auth->logout();
            
            jsonResponse([
                'success' => true,
                'message' => 'Logged out successfully',
                'redirect' => SITE_URL . '/public/login.php'
            ]);
            break;
            
        case 'forgot-password':
            if (getRequestMethod() !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $data = getJsonInput() ?: $_POST;
            
            // Validate
            $validator = new Validator($data);
            if (!$validator->validate(['email' => 'required|email'])) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->getErrors()
                ], 400);
            }
            
            // Request password reset
            $reset = $auth->requestPasswordReset($data['email']);
            
            // Send reset email
            $mail->sendPasswordResetEmail($reset['email'], $reset['token']);
            
            jsonResponse([
                'success' => true,
                'message' => 'Password reset link has been sent to your email'
            ]);
            break;
            
        case 'reset-password':
            if (getRequestMethod() !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $data = getJsonInput() ?: $_POST;
            
            // Validate
            $validator = new Validator($data);
            if (!$validator->validate([
                'email' => 'required|email',
                'token' => 'required',
                'password' => 'required|min:8',
                'password_confirmation' => 'required'
            ])) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->getErrors()
                ], 400);
            }
            
            // Check password confirmation
            if ($data['password'] !== $data['password_confirmation']) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Passwords do not match'
                ], 400);
            }
            
            // Reset password
            $auth->resetPassword($data['email'], $data['token'], $data['password']);
            
            jsonResponse([
                'success' => true,
                'message' => 'Password reset successful. You can now login with your new password.',
                'redirect' => SITE_URL . '/public/login.php'
            ]);
            break;
            
        case 'change-password':
            require_once __DIR__ . '/../includes/auth_check.php';
            
            if (getRequestMethod() !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            $data = getJsonInput() ?: $_POST;
            
            // Validate
            $validator = new Validator($data);
            if (!$validator->validate([
                'current_password' => 'required',
                'new_password' => 'required|min:8',
                'new_password_confirmation' => 'required'
            ])) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->getErrors()
                ], 400);
            }
            
            // Check password confirmation
            if ($data['new_password'] !== $data['new_password_confirmation']) {
                jsonResponse([
                    'success' => false,
                    'message' => 'Passwords do not match'
                ], 400);
            }
            
            // Change password
            $auth->changePassword(
                getCurrentUserId(), 
                $data['current_password'], 
                $data['new_password']
            );
            
            jsonResponse([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
            break;
            
        case 'check-session':
            jsonResponse([
                'success' => true,
                'logged_in' => $auth->isLoggedIn(),
                'user' => $auth->isLoggedIn() ? [
                    'id' => getCurrentUserId(),
                    'email' => $_SESSION['email'] ?? null,
                    'role' => getCurrentUserRole(),
                    'name' => $_SESSION['name'] ?? null
                ] : null
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'message' => $e->getMessage()
    ], 400);
}