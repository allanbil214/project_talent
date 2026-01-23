<?php
// classes/User.php

class User {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Create new user
     */
    public function create($data) {
        $sql = "INSERT INTO users (email, password_hash, role, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $data['email'],
            password_hash($data['password'], PASSWORD_DEFAULT),
            $data['role'],
            $data['status'] ?? STATUS_ACTIVE
        ];
        
        return $this->db->insert($sql, $params);
    }
    
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
     * Update user
     */
    public function update($id, $data) {
        $allowed_fields = ['email', 'password_hash', 'role', 'status'];
        $set_clauses = [];
        $params = [];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $set_clauses[] = "{$field} = ?";
                $params[] = $value;
            }
        }
        
        if (empty($set_clauses)) {
            return false;
        }
        
        $set_clauses[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE users SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        return $this->db->update($sql, $params);
    }
    
    /**
     * Update password
     */
    public function updatePassword($id, $new_password) {
        $sql = "UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?";
        $params = [
            password_hash($new_password, PASSWORD_DEFAULT),
            $id
        ];
        return $this->db->update($sql, $params);
    }
    
    /**
     * Update email
     */
    public function updateEmail($id, $email) {
        if ($this->emailExists($email, $id)) {
            throw new Exception('Email already in use');
        }
        
        $sql = "UPDATE users SET email = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$email, $id]);
    }
    
    /**
     * Update status
     */
    public function updateStatus($id, $status) {
        $sql = "UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$status, $id]);
    }
    
    /**
     * Delete user (soft delete by setting status to inactive)
     */
    public function delete($id) {
        return $this->updateStatus($id, STATUS_INACTIVE);
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
        
        return [
            'data' => $users,
            'pagination' => getPagination($total, $page, $per_page)
        ];
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
        
        return $stats;
    }
    
    /**
     * Store password reset token
     */
    public function createPasswordResetToken($email) {
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $sql = "INSERT INTO password_resets (email, token, expires_at, created_at) 
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()";
        
        $this->db->query($sql, [$email, $token, $expires_at, $token, $expires_at]);
        
        return $token;
    }
    
    /**
     * Verify password reset token
     */
    public function verifyPasswordResetToken($email, $token) {
        $sql = "SELECT * FROM password_resets 
                WHERE email = ? AND token = ? AND expires_at > NOW()";
        
        return $this->db->fetchOne($sql, [$email, $token]);
    }
    
    /**
     * Delete password reset token
     */
    public function deletePasswordResetToken($email) {
        $sql = "DELETE FROM password_resets WHERE email = ?";
        return $this->db->delete($sql, [$email]);
    }
}