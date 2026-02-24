<?php
// classes/Employer.php

require_once __DIR__ . '/../includes/functions.php'; // Add this at the top

class Employer {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Create employer profile
     */
    public function create($user_id, $data) {
        $sql = "INSERT INTO employers (
                    user_id, company_name, industry, company_size,
                    website, description, address, phone,
                    created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $params = [
            $user_id,
            $data['company_name'] ?? '',
            $data['industry'] ?? null,
            $data['company_size'] ?? null,
            $data['website'] ?? null,
            $data['description'] ?? null,
            $data['address'] ?? null,
            $data['phone'] ?? null,
        ];

        $employer_id = $this->db->insert($sql, $params);
        $company_name = $data['company_name'] ?? 'N/A';
        $industry = $data['industry'] ?? 'N/A';
        
        // Log employer profile creation
        logActivity($this->db, 'employer_profile_created', 
            "Employer profile created for User #{$user_id}. " .
            "Company: {$company_name}, Industry: {$industry}");

        return $employer_id;
    }

    /**
     * Update employer profile
     */
    public function update($id, $data) {
        // Get current employer data for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'employer_update_failed', 
                "Attempted to update non-existent employer #{$id}");
            throw new Exception('Employer not found');
        }

        $allowed_fields = [
            'company_name', 'company_logo_url', 'industry', 'company_size',
            'website', 'description', 'address', 'phone'
        ];

        $set_clauses = [];
        $params = [];
        $changes = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $set_clauses[] = "{$field} = ?";
                $params[] = $value;
                
                // Track changes
                if (isset($current[$field]) && $current[$field] != $value) {
                    $old_value = $current[$field];
                    // Truncate long values for logging
                    if (strlen($old_value) > 100) $old_value = substr($old_value, 0, 100) . '...';
                    if (strlen($value) > 100) $value_display = substr($value, 0, 100) . '...';
                    else $value_display = $value;
                    
                    $changes[] = "{$field}: '{$old_value}' → '{$value_display}'";
                }
            }
        }

        if (empty($set_clauses)) {
            return false;
        }

        $set_clauses[] = "updated_at = NOW()";
        $params[] = $id;

        $sql = "UPDATE employers SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $result = $this->db->update($sql, $params);
        
        // Log updates if something changed
        if ($result && !empty($changes)) {
            logActivity($this->db, 'employer_profile_updated', 
                "Employer #{$id} ('{$current['company_name']}') updated. Changes: " . implode(', ', $changes));
        }

        return $result;
    }

    /**
     * Update company logo
     */
    public function updateLogo($id, $logo_url) {
        // Get current employer data for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'employer_logo_update_failed', 
                "Attempted to update logo for non-existent employer #{$id}");
            throw new Exception('Employer not found');
        }

        $old_logo = $current['company_logo_url'];
        
        $sql = "UPDATE employers SET company_logo_url = ?, updated_at = NOW() WHERE id = ?";
        $result = $this->db->update($sql, [$logo_url, $id]);
        
        // Log logo update
        if ($result) {
            logActivity($this->db, 'employer_logo_updated', 
                "Employer #{$id} ('{$current['company_name']}') updated logo. " .
                "Old: '{$old_logo}', New: '{$logo_url}'");
        }

        return $result;
    }

    /**
     * Set verification status
     */
    public function setVerified($id, $verified = true) {
        // Get current employer data for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'employer_verification_failed', 
                "Attempted to verify non-existent employer #{$id}");
            throw new Exception('Employer not found');
        }

        $old_status = $current['verified'] ? 'verified' : 'unverified';
        $new_status = $verified ? 'verified' : 'unverified';
        
        $sql = "UPDATE employers SET verified = ?, updated_at = NOW() WHERE id = ?";
        $result = $this->db->update($sql, [$verified ? 1 : 0, $id]);
        
        // Log verification change
        if ($result) {
            logActivity($this->db, 'employer_verification_changed', 
                "Employer #{$id} ('{$current['company_name']}') verification status changed from '{$old_status}' to '{$new_status}'");
        }

        return $result;
    }

    /**
     * Update rating average
     */
    public function updateRatingAverage($employer_id) {
        $sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
                FROM reviews
                WHERE reviewee_id = (SELECT user_id FROM employers WHERE id = ?)";

        $result = $this->db->fetchOne($sql, [$employer_id]);

        if ($result && $result['total_reviews'] > 0) {
            $old_rating = $this->db->fetchColumn("SELECT rating_average FROM employers WHERE id = ?", [$employer_id]);
            
            $update_sql = "UPDATE employers SET rating_average = ?, updated_at = NOW() WHERE id = ?";
            $update_result = $this->db->update($update_sql, [$result['avg_rating'], $employer_id]);
            
            // Log rating update if significant change
            if ($update_result && abs($old_rating - $result['avg_rating']) > 0.1) {
                $employer = $this->getById($employer_id);
                logActivity($this->db, 'employer_rating_updated', 
                    "Employer #{$employer_id} ('{$employer['company_name']}') rating updated from {$old_rating} to {$result['avg_rating']} " .
                    "based on {$result['total_reviews']} reviews");
            }
            
            return $update_result;
        }

        return false;
    }

    /**
     * Get employer by ID
     */
    public function getById($id) {
        $sql = "SELECT e.*, u.email, u.status, u.created_at as user_created_at
                FROM employers e
                INNER JOIN users u ON e.user_id = u.id
                WHERE e.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Get employer by user ID
     */
    public function getByUserId($user_id) {
        $sql = "SELECT e.*, u.email, u.status
                FROM employers e
                INNER JOIN users u ON e.user_id = u.id
                WHERE e.user_id = ?";
        return $this->db->fetchOne($sql, [$user_id]);
    }

    /**
     * Get all employers with pagination and filters
     */
    public function getAll($page = 1, $per_page = 20, $filters = []) {
        $offset = ($page - 1) * $per_page;

        $where_clauses = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where_clauses[] = "(e.company_name LIKE ? OR e.industry LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }

        if (!empty($filters['industry'])) {
            $where_clauses[] = "e.industry = ?";
            $params[] = $filters['industry'];
        }

        if (isset($filters['verified'])) {
            $where_clauses[] = "e.verified = ?";
            $params[] = $filters['verified'] ? 1 : 0;
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $count_sql = "SELECT COUNT(*) FROM employers e {$where_sql}";
        $total = $this->db->fetchColumn($count_sql, $params);

        $sql = "SELECT e.*, u.email, u.status
                FROM employers e
                INNER JOIN users u ON e.user_id = u.id
                {$where_sql}
                ORDER BY e.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $per_page;
        $params[] = $offset;

        $employers = $this->db->fetchAll($sql, $params);

        return [
            'data' => $employers,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }

    /**
     * Get employer statistics
     */
    public function getStats($employer_id) {
        $stats = [];

        $sql = "SELECT COUNT(*) FROM jobs WHERE employer_id = ?";
        $stats['total_jobs'] = $this->db->fetchColumn($sql, [$employer_id]);

        $sql = "SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND status = 'active'";
        $stats['active_jobs'] = $this->db->fetchColumn($sql, [$employer_id]);

        $sql = "SELECT COUNT(*) FROM jobs WHERE employer_id = ? AND status = 'pending_approval'";
        $stats['pending_jobs'] = $this->db->fetchColumn($sql, [$employer_id]);

        $sql = "SELECT COUNT(*) FROM applications a
                INNER JOIN jobs j ON a.job_id = j.id
                WHERE j.employer_id = ?";
        $stats['total_applications'] = $this->db->fetchColumn($sql, [$employer_id]);

        $sql = "SELECT COUNT(*) FROM applications a
                INNER JOIN jobs j ON a.job_id = j.id
                WHERE j.employer_id = ? AND a.status = 'pending'";
        $stats['pending_applications'] = $this->db->fetchColumn($sql, [$employer_id]);

        $sql = "SELECT COUNT(*) FROM contracts WHERE employer_id = ? AND status = 'active'";
        $stats['active_contracts'] = $this->db->fetchColumn($sql, [$employer_id]);

        $sql = "SELECT COALESCE(SUM(amount), 0) FROM payments p
                INNER JOIN contracts c ON p.contract_id = c.id
                WHERE c.employer_id = ? AND p.status = 'completed'";
        $stats['total_spent'] = $this->db->fetchColumn($sql, [$employer_id]);

        return $stats;
    }

    /**
     * Get recent activity for employer dashboard
     */
    public function getRecentApplications($employer_id, $limit = 10) {
        $sql = "SELECT a.*, j.title as job_title, t.full_name as talent_name,
                       t.profile_photo_url, t.city
                FROM applications a
                INNER JOIN jobs j ON a.job_id = j.id
                INNER JOIN talents t ON a.talent_id = t.id
                WHERE j.employer_id = ?
                ORDER BY a.applied_at DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, [$employer_id, $limit]);
    }

    /**
     * Get active contracts for employer
     */
    public function getActiveContracts($employer_id, $limit = 5) {
        $sql = "SELECT c.*, j.title as job_title, t.full_name as talent_name,
                       t.profile_photo_url
                FROM contracts c
                INNER JOIN jobs j ON c.job_id = j.id
                INNER JOIN talents t ON c.talent_id = t.id
                WHERE c.employer_id = ? AND c.status = 'active'
                ORDER BY c.created_at DESC
                LIMIT ?";

        return $this->db->fetchAll($sql, [$employer_id, $limit]);
    }

    /**
     * Get admin stats
     */
    public function getAdminStats() {
        $stats = [
            'total'     => $this->db->fetchColumn("SELECT COUNT(*) FROM employers"),
            'verified'  => $this->db->fetchColumn("SELECT COUNT(*) FROM employers WHERE verified = 1"),
            'suspended' => $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'employer' AND status = 'suspended'"),
            'jobs'      => $this->db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'active'"),
        ];
        
        // Log when admin views stats (optional - might be too noisy)
        // logActivity($this->db, 'admin_stats_viewed', 'Employer statistics viewed');

        return $stats;
    }

    /**
     * Get industries list
     */
    public function getIndustries() {
        return $this->db->fetchAll("SELECT DISTINCT industry FROM employers WHERE industry IS NOT NULL AND industry != '' ORDER BY industry ASC");
    }

    // Optional: Add method for admin to suspend employer account
    public function suspend($id, $reason = null) {
        $employer = $this->getById($id);
        if (!$employer) {
            throw new Exception('Employer not found');
        }

        // Update user status to suspended
        $sql = "UPDATE users SET status = 'suspended' WHERE id = ?";
        $result = $this->db->update($sql, [$employer['user_id']]);
        
        if ($result) {
            logActivity($this->db, 'employer_suspended', 
                "Employer #{$id} ('{$employer['company_name']}') suspended. " .
                "Reason: " . ($reason ?? 'Not specified'));
        }
        
        return $result;
    }

    // Optional: Add method for admin to activate employer account
    public function activate($id) {
        $employer = $this->getById($id);
        if (!$employer) {
            throw new Exception('Employer not found');
        }

        // Update user status to active
        $sql = "UPDATE users SET status = 'active' WHERE id = ?";
        $result = $this->db->update($sql, [$employer['user_id']]);
        
        if ($result) {
            logActivity($this->db, 'employer_activated', 
                "Employer #{$id} ('{$employer['company_name']}') activated.");
        }
        
        return $result;
    }
}