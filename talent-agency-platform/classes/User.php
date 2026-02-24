<?php
// classes/User.php

require_once __DIR__ . '/../includes/functions.php'; // Add this at the top

class User {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Create new user
     */
    public function create($data) {
        // Check if email already exists
        if ($this->emailExists($data['email'])) {
            logActivity($this->db, 'user_creation_failed', 
                "User creation failed - email already exists: {$data['email']}");
            throw new Exception('Email already exists');
        }
        
        $sql = "INSERT INTO users (email, password_hash, role, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())";
        
        $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);
        $params = [
            $data['email'],
            $hashed_password,
            $data['role'],
            $data['status'] ?? STATUS_ACTIVE
        ];
        
        $user_id = $this->db->insert($sql, $params);
        
        // Log user creation
        logActivity($this->db, 'user_created', 
            "User #{$user_id} created. Email: {$data['email']}, Role: {$data['role']}, Status: " . ($data['status'] ?? STATUS_ACTIVE));
        
        return $user_id;
    }
    
    /**
     * Update user
     */
    public function update($id, $data) {
        // Get current user info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'user_update_failed', 
                "Attempted to update non-existent user #{$id}");
            throw new Exception('User not found');
        }
        
        $allowed_fields = ['email', 'password_hash', 'role', 'status'];
        $set_clauses = [];
        $params = [];
        $changes = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $set_clauses[] = "{$field} = ?";
                $params[] = $value;
                
                // Track changes (don't log password hash values)
                if ($field === 'password_hash') {
                    if (isset($data['password']) && !empty($data['password'])) {
                        $changes[] = "password: [updated]";
                    }
                } elseif ($current[$field] != $value) {
                    $changes[] = "{$field}: '{$current[$field]}' → '{$value}'";
                }
            }
        }
        
        if (empty($set_clauses)) {
            return false;
        }
        
        $set_clauses[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE users SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $result = $this->db->update($sql, $params);
        
        // Log updates if something changed
        if ($result && !empty($changes)) {
            logActivity($this->db, 'user_updated', 
                "User #{$id} ('{$current['email']}') updated. Changes: " . implode(', ', $changes));
        }
        
        return $result;
    }
    
    /**
     * Update password
     */
    public function updatePassword($id, $new_password) {
        // Get current user info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'password_update_failed', 
                "Attempted to update password for non-existent user #{$id}");
            throw new Exception('User not found');
        }
        
        $sql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
        $params = [
            password_hash($new_password, PASSWORD_DEFAULT),
            $id
        ];
        
        $result = $this->db->update($sql, $params);
        
        // Log password update
        if ($result) {
            logActivity($this->db, 'password_updated', 
                "Password updated for User #{$id} ('{$current['email']}')");
        }
        
        return $result;
    }
    
    /**
     * Update email
     */
    public function updateEmail($id, $email) {
        // Get current user info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'email_update_failed', 
                "Attempted to update email for non-existent user #{$id}");
            throw new Exception('User not found');
        }
        
        if ($this->emailExists($email, $id)) {
            logActivity($this->db, 'email_update_failed', 
                "Email update failed - email already in use: {$email} for user #{$id}");
            throw new Exception('Email already in use');
        }
        
        $old_email = $current['email'];
        
        $sql = "UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?";
        $result = $this->db->update($sql, [$email, $id]);
        
        // Log email update
        if ($result) {
            logActivity($this->db, 'email_updated', 
                "Email updated for User #{$id} from '{$old_email}' to '{$email}'");
        }
        
        return $result;
    }
    
    /**
     * Update status
     */
    public function updateStatus($id, $status) {
        // Get current user info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'status_update_failed', 
                "Attempted to update status for non-existent user #{$id}");
            throw new Exception('User not found');
        }
        
        $old_status = $current['status'];
        
        $sql = "UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?";
        $result = $this->db->update($sql, [$status, $id]);
        
        // Log status change
        if ($result && $old_status != $status) {
            $action = $status === STATUS_ACTIVE ? 'user_activated' : 
                     ($status === STATUS_SUSPENDED ? 'user_suspended' : 'user_status_changed');
            
            logActivity($this->db, $action, 
                "User #{$id} ('{$current['email']}') status changed from '{$old_status}' to '{$status}'");
        }
        
        return $result;
    }
    
    /**
     * Update role
     */
    public function updateRole($id, $role) {
        // Get current user info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'role_update_failed', 
                "Attempted to update role for non-existent user #{$id}");
            throw new Exception('User not found');
        }
        
        $old_role = $current['role'];
        
        $result = $this->db->update(
            "UPDATE users SET role = ?, updated_at = NOW() WHERE id = ?",
            [$role, $id]
        );
        
        // Log role change
        if ($result && $old_role != $role) {
            logActivity($this->db, 'user_role_changed', 
                "User #{$id} ('{$current['email']}') role changed from '{$old_role}' to '{$role}'");
        }
        
        return $result;
    }
    
    /**
     * Delete user (soft delete by setting status to inactive)
     */
    public function delete($id) {
        // Get current user info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'user_delete_failed', 
                "Attempted to delete non-existent user #{$id}");
            throw new Exception('User not found');
        }
        
        $result = $this->updateStatus($id, STATUS_INACTIVE);
        
        // Log user deletion (soft delete)
        if ($result) {
            logActivity($this->db, 'user_deleted', 
                "User #{$id} ('{$current['email']}', Role: {$current['role']}) soft deleted (status set to inactive)");
        }
        
        return $result;
    }
    
    /**
     * Hard delete user (admin only - use with caution)
     */
    public function hardDelete($id) {
        // Get current user info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'user_hard_delete_failed', 
                "Attempted to hard delete non-existent user #{$id}");
            throw new Exception('User not found');
        }
        
        // Delete related records based on role
        if ($current['role'] === ROLE_TALENT) {
            $this->db->delete("DELETE FROM talents WHERE user_id = ?", [$id]);
            $this->db->delete("DELETE FROM talent_skills WHERE talent_id IN (SELECT id FROM talents WHERE user_id = ?)", [$id]);
        } elseif ($current['role'] === ROLE_EMPLOYER) {
            $this->db->delete("DELETE FROM employers WHERE user_id = ?", [$id]);
            // Jobs will be handled by foreign key constraints or need manual handling
        }
        
        // Delete user
        $sql = "DELETE FROM users WHERE id = ?";
        $result = $this->db->delete($sql, [$id]);
        
        // Log hard deletion
        if ($result) {
            logActivity($this->db, 'user_hard_deleted', 
                "User #{$id} ('{$current['email']}', Role: {$current['role']}) permanently deleted from database");
        }
        
        return $result;
    }
    
    /**
     * Store password reset token
     */
    public function createPasswordResetToken($email) {
        $user = $this->getByEmail($email);
        if (!$user) {
            logActivity($this->db, 'password_reset_token_failed', 
                "Password reset token requested for non-existent email: {$email}");
            throw new Exception('Email not found');
        }
        
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $sql = "INSERT INTO password_resets (email, token, expires_at, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()";
        
        $this->db->query($sql, [$email, $token, $expires_at, $token, $expires_at]);
        
        // Log token creation
        logActivity($this->db, 'password_reset_token_created', 
            "Password reset token created for User #{$user['id']} ('{$email}'). Expires: {$expires_at}");
        
        return $token;
    }
    
    /**
     * Verify password reset token
     */
    public function verifyPasswordResetToken($email, $token) {
        $sql = "SELECT * FROM password_resets 
                WHERE email = ? AND token = ? AND expires_at > NOW()";
        
        $result = $this->db->fetchOne($sql, [$email, $token]);
        
        // Log token verification (success/failure)
        if ($result) {
            logActivity($this->db, 'password_reset_token_verified', 
                "Password reset token verified for email: {$email}");
        } else {
            logActivity($this->db, 'password_reset_token_invalid', 
                "Invalid or expired password reset token attempted for email: {$email}");
        }
        
        return $result;
    }
    
    /**
     * Delete password reset token
     */
    public function deletePasswordResetToken($email) {
        $sql = "DELETE FROM password_resets WHERE email = ?";
        $result = $this->db->delete($sql, [$email]);
        
        // Log token deletion
        if ($result) {
            logActivity($this->db, 'password_reset_token_deleted', 
                "Password reset token deleted for email: {$email}");
        }
        
        return $result;
    }
    
    // Read operations - no logging needed (keep as is)
    
    /**
     * Get user by ID
     */
    public function getById($id) {
        $sql = "SELECT * FROM users WHERE id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Get user by email
     */
    public function getByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ?";
        return $this->db->fetchOne($sql, [$email]);
    }
    
    /**
     * Check if email exists
     */
    public function emailExists($email, $exclude_id = null) {
        $sql = "SELECT COUNT(*) FROM users WHERE email = ?";
        $params = [$email];
        
        if ($exclude_id) {
            $sql .= " AND id != ?";
            $params[] = $exclude_id;
        }
        
        return $this->db->fetchColumn($sql, $params) > 0;
    }
    
    /**
     * Get all users with pagination
     */
    public function getAll($page = 1, $per_page = ITEMS_PER_PAGE, $filters = []) {
        $offset = ($page - 1) * $per_page;
        
        $where_clauses = [];
        $params = [];
        
        // Role filter
        if (!empty($filters['role'])) {
            $where_clauses[] = "role = ?";
            $params[] = $filters['role'];
        }
        
        // Status filter
        if (!empty($filters['status'])) {
            $where_clauses[] = "status = ?";
            $params[] = $filters['status'];
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $where_clauses[] = "email LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM users {$where_sql}";
        $total = $this->db->fetchColumn($count_sql, $params);
        
        // Get data
        $sql = "SELECT * FROM users {$where_sql} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $per_page;
        $params[] = $offset;
        
        $users = $this->db->fetchAll($sql, $params);
        
        // Log if someone is searching users (admin audit)
        if (!empty($filters['search']) && isset($_SESSION['user_id'])) {
            logActivity($this->db, 'users_searched', 
                "User #{$_SESSION['user_id']} searched users with term: '{$filters['search']}'");
        }
        
        return [
            'data' => $users,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }
    
    /**
     * Get staff members with pagination
     */
    public function getStaff($page = 1, $per_page = 20, $filters = []) {
        $offset = ($page - 1) * $per_page;
        $where  = ["u.role IN ('staff', 'super_admin')"];
        $params = [];
    
        if (!empty($filters['search'])) { 
            $where[] = "u.email LIKE ?"; 
            $params[] = '%' . $filters['search'] . '%'; 
        }
        if (!empty($filters['role']))   { 
            $where[] = "u.role = ?";     
            $params[] = $filters['role']; 
        }
    
        $where_sql = 'WHERE ' . implode(' AND ', $where);
        $total = $this->db->fetchColumn("SELECT COUNT(*) FROM users u $where_sql", $params);
        $data  = $this->db->fetchAll(
            "SELECT u.id, u.email, u.role, u.status, u.created_at, u.updated_at
             FROM users u $where_sql ORDER BY u.role ASC, u.created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$per_page, $offset])
        );
        
        return ['data' => $data, 'pagination' => getPagination($total, $page, $per_page)];
    }
    
    /**
     * Get user with profile data
     */
    public function getUserWithProfile($user_id) {
        $user = $this->getById($user_id);
        if (!$user) {
            return null;
        }
        
        // Get additional profile data based on role
        if ($user['role'] === ROLE_TALENT) {
            $sql = "SELECT * FROM talents WHERE user_id = ?";
            $profile = $this->db->fetchOne($sql, [$user_id]);
            if ($profile) {
                $user['profile'] = $profile;
            }
        } elseif ($user['role'] === ROLE_EMPLOYER) {
            $sql = "SELECT * FROM employers WHERE user_id = ?";
            $profile = $this->db->fetchOne($sql, [$user_id]);
            if ($profile) {
                $user['profile'] = $profile;
            }
        }
        
        return $user;
    }
    
    /**
     * Get statistics
     */
    public function getStats() {
        $stats = [];
        
        $stats['total'] = $this->db->count('users');
        $stats['active'] = $this->db->count('users', 'status = ?', [STATUS_ACTIVE]);
        $stats['by_role'] = [
            'talents' => $this->db->count('users', 'role = ?', [ROLE_TALENT]),
            'employers' => $this->db->count('users', 'role = ?', [ROLE_EMPLOYER]),
            'staff' => $this->db->count('users', 'role = ?', [ROLE_STAFF]),
            'admins' => $this->db->count('users', 'role = ?', [ROLE_SUPER_ADMIN])
        ];
        
        // Log when stats are viewed (optional)
        // if (isset($_SESSION['user_id'])) {
        //     logActivity($this->db, 'user_stats_viewed', 'User statistics viewed');
        // }
        
        return $stats;
    }
    
    /**
     * Get staff statistics
     */
    public function getStaffStats() {
        $stats = [
            'total'     => $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE role IN ('staff','super_admin')"),
            'admins'    => $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'super_admin'"),
            'staff'     => $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'staff'"),
            'suspended' => $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE role IN ('staff','super_admin') AND status = 'suspended'"),
        ];
        
        return $stats;
    }
    
    /**
     * Get recent user registrations
     */
    public function getRecentRegistrations($limit = 10) {
        $sql = "SELECT * FROM users ORDER BY created_at DESC LIMIT ?";
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Count users by date range
     */
    public function countByDateRange($start_date, $end_date) {
        $sql = "SELECT COUNT(*) FROM users WHERE created_at BETWEEN ? AND ?";
        return $this->db->fetchColumn($sql, [$start_date, $end_date]);
    }
}