<?php
// classes/Auth.php

require_once __DIR__ . '/../config/constants.php';

class Auth {
    private $db;
    private $user;
    
    public function __construct(Database $db) {
        $this->db = $db;
        $this->user = new User($db);
    }
    
    /**
     * Login user
     */
    public function login($email, $password) {
        // Get user by email
        $user = $this->user->getByEmail($email);
        
        if (!$user) {
            throw new Exception('Invalid email or password');
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'])) {
            throw new Exception('Invalid email or password');
        }
        
        // Check if user is active
        if ($user['status'] !== STATUS_ACTIVE) {
            throw new Exception('Your account has been suspended. Please contact support.');
        }
        
        // Create session
        $this->createSession($user);
        
        return $user;
    }
    
    /**
     * Register new user
     */
    public function register($data) {
        // Validate required fields
        if (empty($data['email']) || empty($data['password']) || empty($data['role'])) {
            throw new Exception('Email, password, and role are required');
        }
        
        // Check if email already exists
        if ($this->user->emailExists($data['email'])) {
            throw new Exception('Email already registered');
        }
        
        // Validate role
        $allowed_roles = [ROLE_TALENT, ROLE_EMPLOYER];
        if (!in_array($data['role'], $allowed_roles)) {
            throw new Exception('Invalid role');
        }
        
        try {
            $this->db->beginTransaction();
            
            // Create user
            $user_id = $this->user->create([
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
                'status' => STATUS_ACTIVE
            ]);
            
            // Create role-specific profile
            if ($data['role'] === ROLE_TALENT) {
                $this->createTalentProfile($user_id, $data);
            } elseif ($data['role'] === ROLE_EMPLOYER) {
                $this->createEmployerProfile($user_id, $data);
            }
            
            $this->db->commit();
            
            // Get complete user data
            $user = $this->user->getById($user_id);
            
            return $user;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    /**
     * Create talent profile
     */
    private function createTalentProfile($user_id, $data) {
        $sql = "INSERT INTO talents (user_id, full_name, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())";
        
        $full_name = $data['full_name'] ?? 'User';
        
        return $this->db->insert($sql, [$user_id, $full_name]);
    }
    
    /**
     * Create employer profile
     */
    private function createEmployerProfile($user_id, $data) {
        $sql = "INSERT INTO employers (user_id, company_name, created_at, updated_at) 
                VALUES (?, ?, NOW(), NOW())";
        
        $company_name = $data['company_name'] ?? 'Company';
        
        return $this->db->insert($sql, [$user_id, $company_name]);
    }
    
    /**
     * Create session
     */
    private function createSession($user) {
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        
        // Get profile name
        $profile_name = $this->getProfileName($user['id'], $user['role']);
        if ($profile_name) {
            $_SESSION['name'] = $profile_name;
        }
    }
    
    /**
     * Get profile name based on role
     */
    private function getProfileName($user_id, $role) {
        if ($role === ROLE_TALENT) {
            $sql = "SELECT full_name FROM talents WHERE user_id = ?";
            $profile = $this->db->fetchOne($sql, [$user_id]);
            return $profile['full_name'] ?? null;
        } elseif ($role === ROLE_EMPLOYER) {
            $sql = "SELECT company_name FROM employers WHERE user_id = ?";
            $profile = $this->db->fetchOne($sql, [$user_id]);
            return $profile['company_name'] ?? null;
        }
        return null;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Destroy session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Get current user ID
     */
    public function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        $user_id = $this->getCurrentUserId();
        if (!$user_id) {
            return null;
        }
        return $this->user->getUserWithProfile($user_id);
    }
    
    /**
     * Request password reset
     */
    public function requestPasswordReset($email) {
        // Check if user exists
        $user = $this->user->getByEmail($email);
        if (!$user) {
            throw new Exception('Email not found');
        }
        
        // Generate reset token
        $token = $this->user->createPasswordResetToken($email);
        
        return [
            'email' => $email,
            'token' => $token
        ];
    }
    
    /**
     * Reset password
     */
    public function resetPassword($email, $token, $new_password) {
        // Verify token
        $reset_request = $this->user->verifyPasswordResetToken($email, $token);
        if (!$reset_request) {
            throw new Exception('Invalid or expired reset token');
        }
        
        // Get user
        $user = $this->user->getByEmail($email);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Update password
        $this->user->updatePassword($user['id'], $new_password);
        
        // Delete reset token
        $this->user->deletePasswordResetToken($email);
        
        return true;
    }
    
    /**
     * Change password (for logged-in users)
     */
    public function changePassword($user_id, $current_password, $new_password) {
        // Get user
        $user = $this->user->getById($user_id);
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Verify current password
        if (!password_verify($current_password, $user['password_hash'])) {
            throw new Exception('Current password is incorrect');
        }
        
        // Update password
        return $this->user->updatePassword($user_id, $new_password);
    }
    
    /**
     * Check if user has permission
     */
    public function hasRole($required_roles) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        if (!is_array($required_roles)) {
            $required_roles = [$required_roles];
        }
        
        return in_array($_SESSION['role'], $required_roles);
    }
    
    /**
     * Get redirect URL based on role
     */
    public function getRedirectUrl($role) {
        $urls = [
            ROLE_SUPER_ADMIN => '/public/admin/dashboard.php',
            ROLE_STAFF => '/public/admin/dashboard.php',
            ROLE_TALENT => '/public/talent/dashboard.php',
            ROLE_EMPLOYER => '/public/employer/dashboard.php'
        ];
        
        return SITE_URL . ($urls[$role] ?? '/public/index.php');
    }
}