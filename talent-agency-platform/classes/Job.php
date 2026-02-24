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
        // Support both array with employer_id and separate employer_id parameter
        $employer_id = is_array($data) && isset($data['employer_id']) ? $data['employer_id'] : $data;
        $job_data = is_array($data) && isset($data['employer_id']) ? $data : func_get_arg(1);
        
        if (!is_array($job_data)) {
            $job_data = $data;
        }
        
        $sql = "INSERT INTO jobs (
                    employer_id, title, description, job_type, location_type, 
                    location_address, salary_min, salary_max, salary_type, currency,
                    experience_required, status, deadline, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $employer_id,
            $job_data['title'],
            $job_data['description'],
            $job_data['job_type'],
            $job_data['location_type'],
            $job_data['location_address'] ?? null,
            $job_data['salary_min'] ?? null,
            $job_data['salary_max'] ?? null,
            $job_data['salary_type'] ?? SALARY_TYPE_MONTHLY,
            $job_data['currency'] ?? DEFAULT_CURRENCY,
            $job_data['experience_required'] ?? 0,
            $job_data['status'] ?? JOB_STATUS_PENDING,
            $job_data['deadline'] ?? null
        ];
        
        $job_id = $this->db->insert($sql, $params);
        
        // Attach skills if provided
        if (!empty($job_data['skills']) && is_array($job_data['skills'])) {
            $this->syncSkills($job_id, $job_data['skills']);
        }
        
        return $job_id;
    }
    
    /**
     * Get job by ID (with employer info and skills)
     */
    public function getById($id) {
        $sql = "SELECT j.*, e.company_name, e.company_logo_url, e.industry,
                       e.website, e.verified as employer_verified
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                WHERE j.id = ?";
        
        $job = $this->db->fetchOne($sql, [$id]);
        
        if ($job) {
            $job['skills'] = $this->getSkills($id);
            $job['application_count'] = $this->getApplicationCount($id);
        }
        
        return $job;
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
        $sql = "SELECT j.*, e.company_name, e.company_logo_url,
                       (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                WHERE j.status = ? 
                ORDER BY j.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $jobs = $this->db->fetchAll($sql, [JOB_STATUS_ACTIVE, $per_page, $offset]);
        
        foreach ($jobs as &$job) {
            $job['skills'] = $this->getSkills($job['id']);
        }
        
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
        $sql = "SELECT j.*, e.company_name, e.company_logo_url,
                       (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                {$where_sql} 
                ORDER BY j.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $jobs = $this->db->fetchAll($sql, $params);
        
        foreach ($jobs as &$job) {
            $job['skills'] = $this->getSkills($job['id']);
        }
        
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
            'currency', 'experience_required', 'deadline'
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
        
        // Reset to pending approval on edit
        $set_clauses[] = "status = ?";
        $params[] = JOB_STATUS_PENDING;
        
        $set_clauses[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE jobs SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $result = $this->db->update($sql, $params);
        
        // Sync skills if provided
        if (isset($data['skills']) && is_array($data['skills'])) {
            $this->syncSkills($id, $data['skills']);
        }
        
        return $result;
    }
    
    /**
     * Update job status
     */
    public function updateStatus($id, $status) {
        $extra = '';
        $params = [$status];
        
        if ($status === JOB_STATUS_FILLED) {
            $extra = ', filled_at = NOW()';
        }
        
        $params[] = $id;
        $sql = "UPDATE jobs SET status = ?, updated_at = NOW(){$extra} WHERE id = ?";
        return $this->db->update($sql, $params);
    }
    
    /**
     * Mark job as filled
     */
    public function markAsFilled($id) {
        return $this->updateStatus($id, JOB_STATUS_FILLED);
    }
    
    /**
     * Delete job (soft delete - close it)
     */
    public function delete($id) {
        $sql = "UPDATE jobs SET status = 'deleted', updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$id]);
    }
    
    /**
     * Hard delete job (use with caution)
     */
    public function hardDelete($id) {
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
        $sql = "INSERT IGNORE INTO job_skills (job_id, skill_id, required) 
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
     * Sync job skills (replace all)
     */
    public function syncSkills($job_id, $skills) {
        // Delete existing
        $this->db->delete("DELETE FROM job_skills WHERE job_id = ?", [$job_id]);
        
        // Insert new
        foreach ($skills as $skill) {
            $skill_id = is_array($skill) ? $skill['id'] : $skill;
            $required = is_array($skill) ? ($skill['required'] ?? 1) : 1;
            
            $sql = "INSERT IGNORE INTO job_skills (job_id, skill_id, required) VALUES (?, ?, ?)";
            $this->db->query($sql, [$job_id, $skill_id, $required]);
        }
    }
    
    /**
     * Search jobs (for talents and public)
     */
    public function search($filters = [], $page = 1, $per_page = JOBS_PER_PAGE) {
        $offset = ($page - 1) * $per_page;
        
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
        
        // Location type filter
        if (!empty($filters['location_type'])) {
            $where_clauses[] = "j.location_type = ?";
            $params[] = $filters['location_type'];
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
        
        if (!empty($filters['max_salary'])) {
            $where_clauses[] = "j.salary_min <= ?";
            $params[] = $filters['max_salary'];
        }
        
        // Experience filter
        if (!empty($filters['experience_max'])) {
            $where_clauses[] = "j.experience_required <= ?";
            $params[] = $filters['experience_max'];
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
        
        // Deadline filter
        if (!empty($filters['deadline_after'])) {
            $where_clauses[] = "(j.deadline IS NULL OR j.deadline >= ?)";
            $params[] = $filters['deadline_after'];
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM jobs j {$where_sql}";
        $total = $this->db->fetchColumn($count_sql, $params);
        
        // Sorting
        $order = 'j.created_at DESC';
        if (!empty($filters['sort'])) {
            switch ($filters['sort']) {
                case 'salary_high':  $order = 'j.salary_max DESC'; break;
                case 'salary_low':   $order = 'j.salary_min ASC';  break;
                case 'deadline':     $order = 'j.deadline ASC';    break;
                default:             $order = 'j.created_at DESC';
            }
        }
        
        $sql = "SELECT j.*, e.company_name, e.company_logo_url, e.verified as employer_verified,
                       (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                {$where_sql} 
                ORDER BY {$order} 
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $jobs = $this->db->fetchAll($sql, $params);
        
        foreach ($jobs as &$job) {
            $job['skills'] = $this->getSkills($job['id']);
        }
        
        return [
            'data' => $jobs,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }
    
    /**
     * Get jobs by employer with pagination
     */
    public function getByEmployer($employer_id, $page = 1, $per_page = JOBS_PER_PAGE, $filters = []) {
        $offset = ($page - 1) * $per_page;
        
        $where_clauses = ["j.employer_id = ?"];
        $params = [$employer_id];
        
        if (!empty($filters['status'])) {
            $where_clauses[] = "j.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "j.title LIKE ?";
            $params[] = '%' . $filters['search'] . '%';
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM jobs j {$where_sql}";
        $total = $this->db->fetchColumn($count_sql, $params);
        
        // Get data
        $sql = "SELECT j.*, e.company_name, e.company_logo_url,
                (SELECT COUNT(*) FROM applications WHERE job_id = j.id) as application_count
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                {$where_sql} 
                ORDER BY j.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $jobs = $this->db->fetchAll($sql, $params);
        
        foreach ($jobs as &$job) {
            $job['skills'] = $this->getSkills($job['id']);
        }
        
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
        
        // Interviewed
        $sql = "SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = ?";
        $stats['interviewed'] = $this->db->fetchColumn($sql, [$job_id, APP_STATUS_INTERVIEWED]);
        
        // Hired
        $sql = "SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = ?";
        $stats['hired'] = $this->db->fetchColumn($sql, [$job_id, APP_STATUS_HIRED]);
        
        // Rejected
        $sql = "SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = ?";
        $stats['rejected'] = $this->db->fetchColumn($sql, [$job_id, APP_STATUS_REJECTED]);
        
        return $stats;
    }
    
    /**
     * Get application count for a job
     */
    public function getApplicationCount($job_id) {
        $sql = "SELECT COUNT(*) FROM applications WHERE job_id = ?";
        return $this->db->fetchColumn($sql, [$job_id]);
    }
    
    /**
     * Get applications for a job (employer view)
     */
    public function getApplications($job_id, $status = null) {
        $params = [$job_id];
        $where = "WHERE a.job_id = ?";
        
        if ($status) {
            $where .= " AND a.status = ?";
            $params[] = $status;
        }
        
        $sql = "SELECT a.*, t.full_name, t.profile_photo_url, t.city,
                       t.years_experience, t.hourly_rate, t.rating_average
                FROM applications a
                INNER JOIN talents t ON a.talent_id = t.id
                {$where}
                ORDER BY a.applied_at DESC";
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get recent active jobs
     */
    public function getRecent($limit = 10) {
        $sql = "SELECT j.*, e.company_name, e.company_logo_url 
                FROM jobs j 
                INNER JOIN employers e ON j.employer_id = e.id 
                WHERE j.status = ? 
                ORDER BY j.created_at DESC 
                LIMIT ?";
        
        $jobs = $this->db->fetchAll($sql, [JOB_STATUS_ACTIVE, $limit]);
        
        foreach ($jobs as &$job) {
            $job['skills'] = $this->getSkills($job['id']);
        }
        
        return $jobs;
    }
    
    /**
     * Get featured jobs
     */
    public function getFeatured($limit = 6) {
        return $this->getRecent($limit);
    }
    
    /**
     * Get jobs pending admin approval
     */
    public function getPendingApproval($page = 1, $per_page = 20) {
        $offset = ($page - 1) * $per_page;
        
        $count_sql = "SELECT COUNT(*) FROM jobs WHERE status = 'pending_approval'";
        $total = $this->db->fetchColumn($count_sql, []);
        
        $sql = "SELECT j.*, e.company_name, e.company_logo_url
                FROM jobs j
                INNER JOIN employers e ON j.employer_id = e.id
                WHERE j.status = 'pending_approval'
                ORDER BY j.created_at ASC
                LIMIT ? OFFSET ?";
        
        $jobs = $this->db->fetchAll($sql, [$per_page, $offset]);
        
        return [
            'data' => $jobs,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }
    
    /**
     * Check if a talent has already applied
     */
    public function hasApplied($job_id, $talent_id) {
        $sql = "SELECT COUNT(*) FROM applications WHERE job_id = ? AND talent_id = ?";
        return $this->db->fetchColumn($sql, [$job_id, $talent_id]) > 0;
    }
    
    /**
     * Check if job belongs to employer
     */
    public function belongsToEmployer($job_id, $employer_id) {
        $sql = "SELECT COUNT(*) FROM jobs WHERE id = ? AND employer_id = ?";
        return $this->db->fetchColumn($sql, [$job_id, $employer_id]) > 0;
    }
    
    /**
     * Get available job types
     */
    public static function getJobTypes() {
        return ['full-time', 'part-time', 'contract', 'freelance', 'internship'];
    }
    
    /**
     * Get available location types
     */
    public static function getLocationTypes() {
        return ['onsite', 'remote', 'hybrid'];
    }
    
    /**
     * Get available salary types
     */
    public static function getSalaryTypes() {
        return ['hourly', 'daily', 'weekly', 'monthly', 'yearly', 'project'];
    }
    
    /**
     * Get job statuses
     */
    public static function getStatuses() {
        return [
            JOB_STATUS_PENDING,
            JOB_STATUS_ACTIVE,
            JOB_STATUS_FILLED,
            JOB_STATUS_EXPIRED,
            JOB_STATUS_CLOSED,
            JOB_STATUS_REJECTED,
            JOB_STATUS_DELETED
        ];
    }

    public function getAdminStats() {
        return [
            'total'   => $this->db->fetchColumn("SELECT COUNT(*) FROM jobs"),
            'pending' => $this->db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'pending_approval'"),
            'active'  => $this->db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'active'"),
            'filled'  => $this->db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'filled'"),
            'closed'  => $this->db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'closed'"),
            'deleted' => $this->db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status = 'deleted'")
        ];
    }    

    public function getActiveForMatching() {
        return $this->db->fetchAll(
            "SELECT j.id, j.title, j.job_type, j.location_type, e.company_name,
                    (SELECT COUNT(*) FROM applications WHERE job_id=j.id) AS app_count
             FROM jobs j JOIN employers e ON j.employer_id=e.id
             WHERE j.status='active' ORDER BY j.created_at DESC"
        );
    }
}