<?php
// classes/Job.php

class Job {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Create new job
     */
    public function create($data) {
        $sql = "INSERT INTO jobs (
                    employer_id, title, description, job_type, location_type, 
                    location_address, salary_min, salary_max, salary_type, currency,
                    experience_required, status, deadline, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $data['employer_id'],
            $data['title'],
            $data['description'],
            $data['job_type'],
            $data['location_type'],
            $data['location_address'] ?? null,
            $data['salary_min'] ?? null,
            $data['salary_max'] ?? null,
            $data['salary_type'] ?? SALARY_TYPE_MONTHLY,
            $data['currency'] ?? DEFAULT_CURRENCY,
            $data['experience_required'] ?? 0,
            $data['status'] ?? JOB_STATUS_PENDING,
            $data['deadline'] ?? null
        ];
        
        return $this->db->insert($sql, $params);
    }
    
    /**
     * Get job by ID
     */
    public function getById($id) {
        $sql = "SELECT j.*, e.company_name, e.company_logo_url, e.industry 
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                WHERE j.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Get active jobs with pagination
     */
    public function getActive($page = 1, $per_page = JOBS_PER_PAGE) {
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM jobs WHERE status = ?";
        $total = $this->db->fetchColumn($count_sql, [JOB_STATUS_ACTIVE]);
        
        // Get data
        $sql = "SELECT j.*, e.company_name, e.company_logo_url 
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                WHERE j.status = ? 
                ORDER BY j.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $jobs = $this->db->fetchAll($sql, [JOB_STATUS_ACTIVE, $per_page, $offset]);
        
        return [
            'data' => $jobs,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }
    
    /**
     * Get all jobs with pagination and filters
     */
    public function getAll($page = 1, $per_page = JOBS_PER_PAGE, $filters = []) {
        $offset = ($page - 1) * $per_page;
        
        $where_clauses = [];
        $params = [];
        
        // Status filter
        if (!empty($filters['status'])) {
            $where_clauses[] = "j.status = ?";
            $params[] = $filters['status'];
        }
        
        // Employer filter
        if (!empty($filters['employer_id'])) {
            $where_clauses[] = "j.employer_id = ?";
            $params[] = $filters['employer_id'];
        }
        
        // Job type filter
        if (!empty($filters['job_type'])) {
            $where_clauses[] = "j.job_type = ?";
            $params[] = $filters['job_type'];
        }
        
        // Location type filter
        if (!empty($filters['location_type'])) {
            $where_clauses[] = "j.location_type = ?";
            $params[] = $filters['location_type'];
        }
        
        // Search filter
        if (!empty($filters['search'])) {
            $where_clauses[] = "(j.title LIKE ? OR j.description LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM jobs j {$where_sql}";
        $total = $this->db->fetchColumn($count_sql, $params);
        
        // Get data
        $sql = "SELECT j.*, e.company_name, e.company_logo_url 
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                {$where_sql} 
                ORDER BY j.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $jobs = $this->db->fetchAll($sql, $params);
        
        return [
            'data' => $jobs,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }
    
    /**
     * Update job
     */
    public function update($id, $data) {
        $allowed_fields = [
            'title', 'description', 'job_type', 'location_type', 
            'location_address', 'salary_min', 'salary_max', 'salary_type',
            'currency', 'experience_required', 'status', 'deadline'
        ];
        
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
        
        $sql = "UPDATE jobs SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        return $this->db->update($sql, $params);
    }
    
    /**
     * Update job status
     */
    public function updateStatus($id, $status) {
        $sql = "UPDATE jobs SET status = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$status, $id]);
    }
    
    /**
     * Mark job as filled
     */
    public function markAsFilled($id) {
        $sql = "UPDATE jobs SET status = ?, filled_at = NOW(), updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [JOB_STATUS_FILLED, $id]);
    }
    
    /**
     * Delete job
     */
    public function delete($id) {
        $sql = "DELETE FROM jobs WHERE id = ?";
        return $this->db->delete($sql, [$id]);
    }
    
    /**
     * Get job skills
     */
    public function getSkills($job_id) {
        $sql = "SELECT s.id, s.name, s.category, js.required 
                FROM job_skills js 
                INNER JOIN skills s ON js.skill_id = s.id 
                WHERE js.job_id = ? 
                ORDER BY js.required DESC, s.name ASC";
        
        return $this->db->fetchAll($sql, [$job_id]);
    }
    
    /**
     * Add skill requirement to job
     */
    public function addSkill($job_id, $skill_id, $required = true) {
        $sql = "INSERT INTO job_skills (job_id, skill_id, required) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE required = ?";
        
        return $this->db->query($sql, [$job_id, $skill_id, $required ? 1 : 0, $required ? 1 : 0]);
    }
    
    /**
     * Remove skill from job
     */
    public function removeSkill($job_id, $skill_id) {
        $sql = "DELETE FROM job_skills WHERE job_id = ? AND skill_id = ?";
        return $this->db->delete($sql, [$job_id, $skill_id]);
    }
    
    /**
     * Search jobs
     */
    public function search($filters) {
        $where_clauses = ["j.status = 'active'"];
        $params = [];
        
        // Keyword search
        if (!empty($filters['keyword'])) {
            $where_clauses[] = "(j.title LIKE ? OR j.description LIKE ?)";
            $keyword = '%' . $filters['keyword'] . '%';
            $params[] = $keyword;
            $params[] = $keyword;
        }
        
        // Job type filter
        if (!empty($filters['job_type'])) {
            $where_clauses[] = "j.job_type = ?";
            $params[] = $filters['job_type'];
        }
        
        // Location
        if (!empty($filters['location'])) {
            $where_clauses[] = "(j.location_type = 'remote' OR j.location_address LIKE ?)";
            $params[] = '%' . $filters['location'] . '%';
        }
        
        // Salary range
        if (!empty($filters['min_salary'])) {
            $where_clauses[] = "j.salary_max >= ?";
            $params[] = $filters['min_salary'];
        }
        
        // Skills filter
        if (!empty($filters['skills']) && is_array($filters['skills'])) {
            $placeholders = implode(',', array_fill(0, count($filters['skills']), '?'));
            $where_clauses[] = "j.id IN (
                SELECT job_id FROM job_skills 
                WHERE skill_id IN ($placeholders)
            )";
            $params = array_merge($params, $filters['skills']);
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        
        $sql = "SELECT j.*, e.company_name, e.company_logo_url 
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                {$where_sql} 
                ORDER BY j.created_at DESC 
                LIMIT 50";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get jobs by employer
     */
    public function getByEmployer($employer_id, $page = 1, $per_page = JOBS_PER_PAGE) {
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM jobs WHERE employer_id = ?";
        $total = $this->db->fetchColumn($count_sql, [$employer_id]);
        
        // Get data
        $sql = "SELECT j.*, e.company_name, e.company_logo_url,
                (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                WHERE j.employer_id = ? 
                ORDER BY j.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $jobs = $this->db->fetchAll($sql, [$employer_id, $per_page, $offset]);
        
        return [
            'data' => $jobs,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }
    
    /**
     * Get job statistics
     */
    public function getStats($job_id) {
        $stats = [];
        
        // Total applications
        $sql = "SELECT COUNT(*) FROM applications WHERE job_id = ?";
        $stats['total_applications'] = $this->db->fetchColumn($sql, [$job_id]);
        
        // Pending applications
        $sql = "SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = ?";
        $stats['pending_applications'] = $this->db->fetchColumn($sql, [$job_id, APP_STATUS_PENDING]);
        
        // Shortlisted
        $sql = "SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = ?";
        $stats['shortlisted'] = $this->db->fetchColumn($sql, [$job_id, APP_STATUS_SHORTLISTED]);
        
        return $stats;
    }
    
    /**
     * Get recent jobs
     */
    public function getRecent($limit = 10) {
        $sql = "SELECT j.*, e.company_name, e.company_logo_url 
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                WHERE j.status = ? 
                ORDER BY j.created_at DESC 
                LIMIT ?";
        
        return $this->db->fetchAll($sql, [JOB_STATUS_ACTIVE, $limit]);
    }
}