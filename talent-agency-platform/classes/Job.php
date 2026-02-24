<?php
// classes/Job.php

require_once __DIR__ . '/../includes/functions.php'; // Add this at the top

class Job {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    public function create($data) {
        // Support both array with employer_id and separate employer_id parameter
        $employer_id = is_array($data) && isset($data['employer_id']) ? $data['employer_id'] : $data;
        $job_data = is_array($data) && isset($data['employer_id']) ? $data : func_get_arg(1);
        
        if (!is_array($job_data)) {
            $job_data = $data;
        }
        
        // Get employer info for logging
        $employer = $this->db->fetchOne("SELECT company_name FROM employers WHERE id = ?", [$employer_id]);
        
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
        
        // Log job creation
        $status_text = $job_data['status'] ?? JOB_STATUS_PENDING;
        logActivity($this->db, 'job_created', 
            "Job #{$job_id} created by Employer #{$employer_id} ('{$employer['company_name']}'). " .
            "Title: '{$job_data['title']}', Type: {$job_data['job_type']}, Status: {$status_text}");
        
        return $job_id;
    }

    public function update($id, $data) {
        // Get current job info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'job_update_failed', "Attempted to update non-existent job #{$id}");
            throw new Exception('Job not found');
        }
        
        $allowed_fields = [
            'title', 'description', 'job_type', 'location_type', 
            'location_address', 'salary_min', 'salary_max', 'salary_type',
            'currency', 'experience_required', 'deadline'
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
        
        // Reset to pending approval on edit
        $set_clauses[] = "status = ?";
        $params[] = JOB_STATUS_PENDING;
        $changes[] = "status: '{$current['status']}' → '" . JOB_STATUS_PENDING . "' (reset for approval)";
        
        $set_clauses[] = "updated_at = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE jobs SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $result = $this->db->update($sql, $params);
        
        // Sync skills if provided
        if (isset($data['skills']) && is_array($data['skills'])) {
            $this->syncSkills($id, $data['skills']);
            $changes[] = "skills: updated";
        }
        
        // Log updates
        if ($result && !empty($changes)) {
            logActivity($this->db, 'job_updated', 
                "Job #{$id} ('{$current['title']}') updated by Employer #{$current['employer_id']}. " .
                "Changes: " . implode(', ', $changes));
        }
        
        return $result;
    }
    
    /**
     * Update job status
     */
    public function updateStatus($id, $status) {
        // Get current job info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'job_status_update_failed', "Attempted to update status of non-existent job #{$id}");
            throw new Exception('Job not found');
        }
        
        $old_status = $current['status'];
        
        $extra = '';
        $params = [$status];
        
        if ($status === JOB_STATUS_FILLED) {
            $extra = ', filled_at = NOW()';
        }
        
        $params[] = $id;
        $sql = "UPDATE jobs SET status = ?, updated_at = NOW(){$extra} WHERE id = ?";
        $result = $this->db->update($sql, $params);
        
        // Log status change
        if ($result) {
            $action = 'job_status_changed';
            if ($status === JOB_STATUS_ACTIVE && $old_status === JOB_STATUS_PENDING) {
                $action = 'job_approved';
            } elseif ($status === JOB_STATUS_REJECTED) {
                $action = 'job_rejected';
            } elseif ($status === JOB_STATUS_FILLED) {
                $action = 'job_filled';
            } elseif ($status === JOB_STATUS_CLOSED) {
                $action = 'job_closed';
            }
            
            logActivity($this->db, $action, 
                "Job #{$id} ('{$current['title']}') status changed from '{$old_status}' to '{$status}'. " .
                "Employer: '{$current['company_name']}'");
        }
        
        return $result;
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
        // Get current job info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'job_delete_failed', "Attempted to delete non-existent job #{$id}");
            throw new Exception('Job not found');
        }
        
        $sql = "UPDATE jobs SET status = 'deleted', updated_at = NOW() WHERE id = ?";
        $result = $this->db->update($sql, [$id]);
        
        // Log deletion
        if ($result) {
            logActivity($this->db, 'job_deleted', 
                "Job #{$id} ('{$current['title']}') deleted. " .
                "Employer: '{$current['company_name']}', Previous status: '{$current['status']}'");
        }
        
        return $result;
    }
    
    /**
     * Hard delete job (use with caution)
     */
    public function hardDelete($id) {
        // Get current job info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'job_hard_delete_failed', "Attempted to hard delete non-existent job #{$id}");
            throw new Exception('Job not found');
        }
        
        $sql = "DELETE FROM jobs WHERE id = ?";
        $result = $this->db->delete($sql, [$id]);
        
        // Log hard deletion
        if ($result) {
            logActivity($this->db, 'job_hard_deleted', 
                "Job #{$id} ('{$current['title']}') permanently deleted from database. " .
                "Employer: '{$current['company_name']}', Status: '{$current['status']}'");
        }
        
        return $result;
    }
    
    /**
     * Add skill requirement to job
     */
    public function addSkill($job_id, $skill_id, $required = true) {
        // Get job and skill info for logging
        $job = $this->getById($job_id);
        $skill = $this->db->fetchOne("SELECT name FROM skills WHERE id = ?", [$skill_id]);
        
        if (!$job || !$skill) {
            logActivity($this->db, 'job_skill_add_failed', 
                "Failed to add skill #{$skill_id} to job #{$job_id} - job or skill not found");
            return false;
        }
        
        $sql = "INSERT IGNORE INTO job_skills (job_id, skill_id, required) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE required = ?";
        
        $result = $this->db->query($sql, [$job_id, $skill_id, $required ? 1 : 0, $required ? 1 : 0]);
        
        // Log skill addition
        logActivity($this->db, 'job_skill_added', 
            "Skill '{$skill['name']}' added to job #{$job_id} ('{$job['title']}'). " .
            "Required: " . ($required ? 'Yes' : 'No'));
        
        return $result;
    }
    
    /**
     * Remove skill from job
     */
    public function removeSkill($job_id, $skill_id) {
        // Get job and skill info for logging
        $job = $this->getById($job_id);
        $skill = $this->db->fetchOne("SELECT name FROM skills WHERE id = ?", [$skill_id]);
        
        if (!$job || !$skill) {
            logActivity($this->db, 'job_skill_remove_failed', 
                "Failed to remove skill #{$skill_id} from job #{$job_id} - job or skill not found");
            return false;
        }
        
        $sql = "DELETE FROM job_skills WHERE job_id = ? AND skill_id = ?";
        $result = $this->db->delete($sql, [$job_id, $skill_id]);
        
        // Log skill removal
        if ($result) {
            logActivity($this->db, 'job_skill_removed', 
                "Skill '{$skill['name']}' removed from job #{$job_id} ('{$job['title']}')");
        }
        
        return $result;
    }
    
    /**
     * Sync job skills (replace all)
     */
    public function syncSkills($job_id, $skills) {
        // Get job info for logging
        $job = $this->getById($job_id);
        if (!$job) {
            logActivity($this->db, 'job_skill_sync_failed', "Attempted to sync skills for non-existent job #{$job_id}");
            return false;
        }
        
        // Get current skills for comparison
        $current_skills = $this->getSkills($job_id);
        $current_ids = array_column($current_skills, 'id');
        $new_ids = [];
        
        foreach ($skills as $skill) {
            $skill_id = is_array($skill) ? $skill['id'] : $skill;
            $new_ids[] = $skill_id;
        }
        
        $added = array_diff($new_ids, $current_ids);
        $removed = array_diff($current_ids, $new_ids);
        
        // Delete existing
        $this->db->delete("DELETE FROM job_skills WHERE job_id = ?", [$job_id]);
        
        // Insert new
        foreach ($skills as $skill) {
            $skill_id = is_array($skill) ? $skill['id'] : $skill;
            $required = is_array($skill) ? ($skill['required'] ?? 1) : 1;
            
            $sql = "INSERT IGNORE INTO job_skills (job_id, skill_id, required) VALUES (?, ?, ?)";
            $this->db->query($sql, [$job_id, $skill_id, $required]);
        }
        
        // Log skill sync if there were changes
        if (!empty($added) || !empty($removed)) {
            $added_names = [];
            $removed_names = [];
            
            if (!empty($added)) {
                $added_skills = $this->db->fetchAll("SELECT id, name FROM skills WHERE id IN (" . implode(',', $added) . ")");
                $added_names = array_column($added_skills, 'name');
            }
            
            if (!empty($removed)) {
                $removed_skills = $this->db->fetchAll("SELECT id, name FROM skills WHERE id IN (" . implode(',', $removed) . ")");
                $removed_names = array_column($removed_skills, 'name');
            }
            
            logActivity($this->db, 'job_skills_synced', 
                "Skills synced for job #{$job_id} ('{$job['title']}'). " .
                (!empty($added_names) ? "Added: " . implode(', ', $added_names) . ". " : "") .
                (!empty($removed_names) ? "Removed: " . implode(', ', $removed_names) . "." : ""));
        }
    }
    
    // Read operations - no logging needed
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
    
    public function getActive($page = 1, $per_page = JOBS_PER_PAGE) {
        $offset = ($page - 1) * $per_page;
        
        $count_sql = "SELECT COUNT(*) FROM jobs WHERE status = ?";
        $total = $this->db->fetchColumn($count_sql, [JOB_STATUS_ACTIVE]);
        
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
    
    public function getAll($page = 1, $per_page = JOBS_PER_PAGE, $filters = []) {
        $offset = ($page - 1) * $per_page;
        
        $where_clauses = [];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where_clauses[] = "j.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['employer_id'])) {
            $where_clauses[] = "j.employer_id = ?";
            $params[] = $filters['employer_id'];
        }
        
        if (!empty($filters['job_type'])) {
            $where_clauses[] = "j.job_type = ?";
            $params[] = $filters['job_type'];
        }
        
        if (!empty($filters['location_type'])) {
            $where_clauses[] = "j.location_type = ?";
            $params[] = $filters['location_type'];
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(j.title LIKE ? OR j.description LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        $count_sql = "SELECT COUNT(*) FROM jobs j {$where_sql}";
        $total = $this->db->fetchColumn($count_sql, $params);
        
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
    
    public function getSkills($job_id) {
        $sql = "SELECT s.id, s.name, s.category, js.required 
                FROM job_skills js 
                INNER JOIN skills s ON js.skill_id = s.id 
                WHERE js.job_id = ? 
                ORDER BY js.required DESC, s.name ASC";
        
        return $this->db->fetchAll($sql, [$job_id]);
    }
    
    public function search($filters = [], $page = 1, $per_page = JOBS_PER_PAGE) {
        $offset = ($page - 1) * $per_page;
        
        $where_clauses = ["j.status = 'active'"];
        $params = [];
        
        if (!empty($filters['keyword'])) {
            $where_clauses[] = "(j.title LIKE ? OR j.description LIKE ?)";
            $keyword = '%' . $filters['keyword'] . '%';
            $params[] = $keyword;
            $params[] = $keyword;
        }
        
        if (!empty($filters['job_type'])) {
            $where_clauses[] = "j.job_type = ?";
            $params[] = $filters['job_type'];
        }
        
        if (!empty($filters['location_type'])) {
            $where_clauses[] = "j.location_type = ?";
            $params[] = $filters['location_type'];
        }
        
        if (!empty($filters['location'])) {
            $where_clauses[] = "(j.location_type = 'remote' OR j.location_address LIKE ?)";
            $params[] = '%' . $filters['location'] . '%';
        }
        
        if (!empty($filters['min_salary'])) {
            $where_clauses[] = "j.salary_max >= ?";
            $params[] = $filters['min_salary'];
        }
        
        if (!empty($filters['max_salary'])) {
            $where_clauses[] = "j.salary_min <= ?";
            $params[] = $filters['max_salary'];
        }
        
        if (!empty($filters['experience_max'])) {
            $where_clauses[] = "j.experience_required <= ?";
            $params[] = $filters['experience_max'];
        }
        
        if (!empty($filters['skills']) && is_array($filters['skills'])) {
            $placeholders = implode(',', array_fill(0, count($filters['skills']), '?'));
            $where_clauses[] = "j.id IN (
                SELECT job_id FROM job_skills 
                WHERE skill_id IN ($placeholders)
            )";
            $params = array_merge($params, $filters['skills']);
        }
        
        if (!empty($filters['deadline_after'])) {
            $where_clauses[] = "(j.deadline IS NULL OR j.deadline >= ?)";
            $params[] = $filters['deadline_after'];
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        
        $count_sql = "SELECT COUNT(*) FROM jobs j {$where_sql}";
        $total = $this->db->fetchColumn($count_sql, $params);
        
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
        
        $count_sql = "SELECT COUNT(*) FROM jobs j {$where_sql}";
        $total = $this->db->fetchColumn($count_sql, $params);
        
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
    
    public function getStats($job_id) {
        $stats = [];
        
        $stats['total_applications'] = $this->db->fetchColumn("SELECT COUNT(*) FROM applications WHERE job_id = ?", [$job_id]);
        $stats['pending_applications'] = $this->db->fetchColumn("SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = ?", [$job_id, APP_STATUS_PENDING]);
        $stats['shortlisted'] = $this->db->fetchColumn("SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = ?", [$job_id, APP_STATUS_SHORTLISTED]);
        $stats['interviewed'] = $this->db->fetchColumn("SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = ?", [$job_id, APP_STATUS_INTERVIEWED]);
        $stats['hired'] = $this->db->fetchColumn("SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = ?", [$job_id, APP_STATUS_HIRED]);
        $stats['rejected'] = $this->db->fetchColumn("SELECT COUNT(*) FROM applications WHERE job_id = ? AND status = ?", [$job_id, APP_STATUS_REJECTED]);
        
        return $stats;
    }
    
    public function getApplicationCount($job_id) {
        $sql = "SELECT COUNT(*) FROM applications WHERE job_id = ?";
        return $this->db->fetchColumn($sql, [$job_id]);
    }
    
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
    
    public function getFeatured($limit = 6) {
        return $this->getRecent($limit);
    }
    
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
    
    public function hasApplied($job_id, $talent_id) {
        $sql = "SELECT COUNT(*) FROM applications WHERE job_id = ? AND talent_id = ?";
        return $this->db->fetchColumn($sql, [$job_id, $talent_id]) > 0;
    }
    
    public function belongsToEmployer($job_id, $employer_id) {
        $sql = "SELECT COUNT(*) FROM jobs WHERE id = ? AND employer_id = ?";
        return $this->db->fetchColumn($sql, [$job_id, $employer_id]) > 0;
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
    
    public static function getJobTypes() {
        return ['full-time', 'part-time', 'contract', 'freelance', 'internship'];
    }
    
    public static function getLocationTypes() {
        return ['onsite', 'remote', 'hybrid'];
    }
    
    public static function getSalaryTypes() {
        return ['hourly', 'daily', 'weekly', 'monthly', 'yearly', 'project'];
    }
    
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
}