<?php
// classes/Application.php

class Application {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Create new application
     */
    public function create($data) {
        // Check if already applied
        if ($this->hasApplied($data['job_id'], $data['talent_id'])) {
            throw new Exception('You have already applied to this job');
        }
        
        $sql = "INSERT INTO applications (
                    job_id, talent_id, cover_letter, proposed_rate, 
                    status, agency_recommended, applied_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [
            $data['job_id'],
            $data['talent_id'],
            $data['cover_letter'] ?? null,
            $data['proposed_rate'] ?? null,
            $data['status'] ?? APP_STATUS_PENDING,
            $data['agency_recommended'] ?? false
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Get application by ID
     */
    public function getById($id) {
        $sql = "SELECT a.*, 
                j.title as job_title, j.job_type, j.location_type,
                t.full_name as talent_name, t.profile_photo_url, t.hourly_rate, t.rating_average,
                e.company_name, e.company_logo_url
                FROM applications a
                INNER JOIN jobs j ON a.job_id = j.id
                INNER JOIN talents t ON a.talent_id = t.id
                INNER JOIN employers e ON j.employer_id = e.id
                WHERE a.id = ?";
        
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Get applications by talent
     */
    public function getByTalent($talent_id, $page = 1, $per_page = ITEMS_PER_PAGE) {
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM applications WHERE talent_id = ?";
        $total = $this->db->fetchColumn($count_sql, [$talent_id]);
        
        // Get data
        $sql = "SELECT a.*, 
                j.title as job_title, j.job_type, j.location_type, j.status as job_status,
                e.company_name, e.company_logo_url
                FROM applications a
                INNER JOIN jobs j ON a.job_id = j.id
                INNER JOIN employers e ON j.employer_id = e.id
                WHERE a.talent_id = ?
                ORDER BY a.applied_at DESC
                LIMIT ? OFFSET ?";
        
        $applications = $this->db->fetchAll($sql, [$talent_id, $per_page, $offset]);
        
        return [
            'data' => $applications,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }
    
    /**
     * Get applications by job
     */
    public function getByJob($job_id, $page = 1, $per_page = ITEMS_PER_PAGE) {
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM applications WHERE job_id = ?";
        $total = $this->db->fetchColumn($count_sql, [$job_id]);
        
        // Get data
        $sql = "SELECT a.*, 
                t.full_name as talent_name, t.profile_photo_url, t.hourly_rate, 
                t.years_experience, t.rating_average, t.city, t.country
                FROM applications a
                INNER JOIN talents t ON a.talent_id = t.id
                WHERE a.job_id = ?
                ORDER BY a.agency_recommended DESC, a.applied_at DESC
                LIMIT ? OFFSET ?";
        
        $applications = $this->db->fetchAll($sql, [$job_id, $per_page, $offset]);
        
        // Get skills for each talent
        require_once __DIR__ . '/Talent.php';
        $talent_model = new Talent($this->db);
        
        foreach ($applications as &$app) {
            $app['skills'] = $talent_model->getSkills($app['talent_id']);
        }
        
        return [
            'data' => $applications,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }
    
    /**
     * Update application status
     */
    public function updateStatus($id, $status) {
        $sql = "UPDATE applications 
                SET status = ?, reviewed_at = NOW() 
                WHERE id = ?";
        
        return $this->db->update($sql, [$status, $id]);
    }
    
    /**
     * Update application
     */
    public function update($id, $data) {
        $allowed_fields = ['cover_letter', 'proposed_rate', 'status', 'agency_recommended'];
        
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
        
        $params[] = $id;
        
        $sql = "UPDATE applications SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        return $this->db->update($sql, $params);
    }
    
    /**
     * Delete application
     */
    public function delete($id) {
        $sql = "DELETE FROM applications WHERE id = ?";
        return $this->db->delete($sql, [$id]);
    }
    
    /**
     * Check if talent has already applied to job
     */
    public function hasApplied($job_id, $talent_id) {
        $sql = "SELECT COUNT(*) FROM applications WHERE job_id = ? AND talent_id = ?";
        return $this->db->fetchColumn($sql, [$job_id, $talent_id]) > 0;
    }
    
    /**
     * Get application by job and talent
     */
    public function getByJobAndTalent($job_id, $talent_id) {
        $sql = "SELECT a.*, 
                j.title as job_title, j.job_type, j.location_type,
                e.company_name, e.company_logo_url
                FROM applications a
                INNER JOIN jobs j ON a.job_id = j.id
                INNER JOIN employers e ON j.employer_id = e.id
                WHERE a.job_id = ? AND a.talent_id = ?";
        
        return $this->db->fetchOne($sql, [$job_id, $talent_id]);
    }
    
    /**
     * Get all applications with filters
     */
    public function getAll($page = 1, $per_page = ITEMS_PER_PAGE, $filters = []) {
        $offset = ($page - 1) * $per_page;
        
        $where_clauses = [];
        $params = [];
        
        // Status filter
        if (!empty($filters['status'])) {
            $where_clauses[] = "a.status = ?";
            $params[] = $filters['status'];
        }
        
        // Job filter
        if (!empty($filters['job_id'])) {
            $where_clauses[] = "a.job_id = ?";
            $params[] = $filters['job_id'];
        }
        
        // Talent filter
        if (!empty($filters['talent_id'])) {
            $where_clauses[] = "a.talent_id = ?";
            $params[] = $filters['talent_id'];
        }
        
        // Agency recommended
        if (isset($filters['agency_recommended'])) {
            $where_clauses[] = "a.agency_recommended = ?";
            $params[] = $filters['agency_recommended'] ? 1 : 0;
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM applications a {$where_sql}";
        $total = $this->db->fetchColumn($count_sql, $params);
        
        // Get data
        $sql = "SELECT a.*, 
                j.title as job_title, j.job_type,
                t.full_name as talent_name, t.profile_photo_url,
                e.company_name
                FROM applications a
                INNER JOIN jobs j ON a.job_id = j.id
                INNER JOIN talents t ON a.talent_id = t.id
                INNER JOIN employers e ON j.employer_id = e.id
                {$where_sql}
                ORDER BY a.applied_at DESC
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $applications = $this->db->fetchAll($sql, $params);
        
        return [
            'data' => $applications,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }
    
    /**
     * Get application statistics
     */
    public function getStats() {
        $stats = [];
        
        $stats['total'] = $this->db->count('applications');
        $stats['pending'] = $this->db->count('applications', 'status = ?', [APP_STATUS_PENDING]);
        $stats['reviewed'] = $this->db->count('applications', 'status = ?', [APP_STATUS_REVIEWED]);
        $stats['shortlisted'] = $this->db->count('applications', 'status = ?', [APP_STATUS_SHORTLISTED]);
        $stats['accepted'] = $this->db->count('applications', 'status = ?', [APP_STATUS_ACCEPTED]);
        $stats['rejected'] = $this->db->count('applications', 'status = ?', [APP_STATUS_REJECTED]);
        
        return $stats;
    }
    
    /**
     * Mark as agency recommended
     */
    public function markAsRecommended($id, $recommended = true) {
        $sql = "UPDATE applications SET agency_recommended = ? WHERE id = ?";
        return $this->db->update($sql, [$recommended ? 1 : 0, $id]);
    }
    
    /**
     * Get recent applications
     */
    public function getRecent($limit = 10) {
        $sql = "SELECT a.*, 
                j.title as job_title,
                t.full_name as talent_name,
                e.company_name
                FROM applications a
                INNER JOIN jobs j ON a.job_id = j.id
                INNER JOIN talents t ON a.talent_id = t.id
                INNER JOIN employers e ON j.employer_id = e.id
                ORDER BY a.applied_at DESC
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [$limit]);
    }
    
    /**
     * Get pending applications count for talent
     */
    public function getPendingCountForTalent($talent_id) {
        $sql = "SELECT COUNT(*) FROM applications 
                WHERE talent_id = ? AND status = ?";
        return $this->db->fetchColumn($sql, [$talent_id, APP_STATUS_PENDING]);
    }
    
    /**
     * Withdraw application
     */
    public function withdraw($id) {
        // Only allow withdrawal of pending/reviewed applications
        $app = $this->getById($id);
        
        if (!$app) {
            throw new Exception('Application not found');
        }
        
        if (!in_array($app['status'], [APP_STATUS_PENDING, APP_STATUS_REVIEWED])) {
            throw new Exception('Cannot withdraw this application');
        }
        
        return $this->delete($id);
    }
}