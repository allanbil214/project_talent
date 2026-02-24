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
            logActivity($this->db, 'application_duplicate', 
                "Talent #{$data['talent_id']} attempted to re-apply to job #{$data['job_id']}");
            throw new Exception('You have already applied to this job');
        }

        // Check job is still active
        $job = $this->db->fetchOne("SELECT id, title FROM jobs WHERE id = ? AND status = 'active'", [$data['job_id']]);
        if (!$job) {
            logActivity($this->db, 'application_failed', 
                "Talent #{$data['talent_id']} attempted to apply to inactive/non-existent job #{$data['job_id']}");
            throw new Exception('This job is no longer available.');
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

        $application_id = $this->db->insert($sql, $params);
		
		// Log successful application
        logActivity($this->db, 'application_created', 
            "Talent #{$data['talent_id']} applied to job #{$data['job_id']}: '{$job['title']}'. Application ID: {$application_id}");

        return $application_id;
    }

    /**
     * Get application by ID
     */
    public function getById($id) {
        $sql = "SELECT a.*,
                j.title as job_title, j.job_type, j.location_type,
                j.salary_min, j.salary_max, j.salary_type, j.currency,
                j.status as job_status, j.employer_id,
                t.full_name as talent_name, t.profile_photo_url, t.hourly_rate,
                t.rating_average, t.resume_url, t.city,
                e.company_name, e.company_logo_url,
                u_t.email as talent_email
                FROM applications a
                INNER JOIN jobs j ON a.job_id = j.id
                INNER JOIN talents t ON a.talent_id = t.id
                INNER JOIN employers e ON j.employer_id = e.id
                INNER JOIN users u_t ON t.user_id = u_t.id
                WHERE a.id = ?";

        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Get applications by talent (with optional status filter)
     */
    public function getByTalent($talent_id, $page = 1, $per_page = ITEMS_PER_PAGE, $filters = []) {
        $offset = ($page - 1) * $per_page;

        $where_clauses = ['a.talent_id = ?'];
        $params = [$talent_id];

        if (!empty($filters['status'])) {
            $where_clauses[] = 'a.status = ?';
            $params[] = $filters['status'];
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM applications a INNER JOIN jobs j ON a.job_id = j.id $where_sql",
            $params
        );

        $sql = "SELECT a.*,
                j.title as job_title, j.job_type, j.location_type, j.status as job_status,
                j.salary_min, j.salary_max, j.salary_type, j.currency,
                e.company_name, e.company_logo_url
                FROM applications a
                INNER JOIN jobs j ON a.job_id = j.id
                INNER JOIN employers e ON j.employer_id = e.id
                $where_sql
                ORDER BY a.applied_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $per_page;
        $params[] = $offset;

        return [
            'data' => $this->db->fetchAll($sql, $params),
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }

    /**
     * Get applications by job (with optional status filter + skill matching)
     */
    public function getByJob($job_id, $page = 1, $per_page = ITEMS_PER_PAGE, $filters = []) {
        $offset = ($page - 1) * $per_page;

        $where_clauses = ['a.job_id = ?'];
        $params = [$job_id];

        if (!empty($filters['status'])) {
            $where_clauses[] = 'a.status = ?';
            $params[] = $filters['status'];
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM applications a $where_sql",
            $params
        );

        $sql = "SELECT a.*,
                t.full_name as talent_name, t.profile_photo_url, t.hourly_rate,
                t.years_experience, t.rating_average, t.city, t.country, t.resume_url,
                t.availability_status,
                u.email as talent_email
                FROM applications a
                INNER JOIN talents t ON a.talent_id = t.id
                INNER JOIN users u ON t.user_id = u.id
                $where_sql
                ORDER BY a.agency_recommended DESC, a.applied_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $per_page;
        $params[] = $offset;

        $applications = $this->db->fetchAll($sql, $params);

        // Attach skills with job-match highlighting
        foreach ($applications as &$app) {
            $app['skills'] = $this->getTalentSkillsForJob($app['talent_id'], $job_id);
        }

        return [
            'data' => $applications,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }

    /**
     * Get talent skills, flagging which ones match the job requirements
     */
    private function getTalentSkillsForJob($talent_id, $job_id) {
        $sql = "SELECT s.name, ts.proficiency_level,
                       IF(js.skill_id IS NOT NULL, 1, 0) AS is_required_by_job
                FROM talent_skills ts
                JOIN skills s ON ts.skill_id = s.id
                LEFT JOIN job_skills js ON js.skill_id = ts.skill_id AND js.job_id = ?
                WHERE ts.talent_id = ?
                ORDER BY is_required_by_job DESC, s.name ASC";
        return $this->db->fetchAll($sql, [$job_id, $talent_id]);
    }

    /**
     * Update application status
     */
    public function updateStatus($id, $status) {
        // Get current application info for logging
        $application = $this->getById($id);
        if (!$application) {
            throw new Exception('Application not found');
        }

        $old_status = $application['status'];
        
        $sql = "UPDATE applications
                SET status = ?, reviewed_at = NOW()
                WHERE id = ?";

        $result = $this->db->update($sql, [$status, $id]);
        
        // Log status change
        logActivity($this->db, 'application_status_changed', 
            "Application #{$id} status changed from '{$old_status}' to '{$status}'. " .
            "Job: '{$application['job_title']}', Talent: '{$application['talent_name']}'");

        return $result;
    }

    /**
     * Update application fields
     */
    public function update($id, $data) {
        // Get current application info for logging
        $application = $this->getById($id);
        if (!$application) {
            throw new Exception('Application not found');
        }

        $allowed_fields = ['cover_letter', 'proposed_rate', 'status', 'agency_recommended'];

        $set_clauses = [];
        $params = [];
        $changes = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $set_clauses[] = "{$field} = ?";
                $params[] = $value;
                
                // Track what changed
                if (isset($application[$field]) && $application[$field] != $value) {
                    $old_value = $application[$field];
                    $changes[] = "{$field}: '{$old_value}' → '{$value}'";
                }
            }
        }

        if (empty($set_clauses)) {
            return false;
        }

        $params[] = $id;

        $sql = "UPDATE applications SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $result = $this->db->update($sql, $params);
        
        // Log updates if something changed
        if ($result && !empty($changes)) {
            logActivity($this->db, 'application_updated', 
                "Application #{$id} updated. Changes: " . implode(', ', $changes) . ". " .
                "Job: '{$application['job_title']}', Talent: '{$application['talent_name']}'");
        }

        return $result;
    }

    /**
     * Delete application
     */
    public function delete($id) {
        // Get application info for logging
        $application = $this->getById($id);
        if (!$application) {
            throw new Exception('Application not found');
        }

        $sql = "DELETE FROM applications WHERE id = ?";
        $result = $this->db->delete($sql, [$id]);
        
        // Log deletion
        logActivity($this->db, 'application_deleted', 
            "Application #{$id} deleted. Job: '{$application['job_title']}', " .
            "Talent: '{$application['talent_name']}', Status: '{$application['status']}'");

        return $result;
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
     * Get all applications with filters (admin use)
     */
    public function getAll($page = 1, $per_page = ITEMS_PER_PAGE, $filters = []) {
        $offset = ($page - 1) * $per_page;

        $where_clauses = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where_clauses[] = "a.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['job_id'])) {
            $where_clauses[] = "a.job_id = ?";
            $params[] = $filters['job_id'];
        }

        if (!empty($filters['talent_id'])) {
            $where_clauses[] = "a.talent_id = ?";
            $params[] = $filters['talent_id'];
        }

        if (isset($filters['agency_recommended'])) {
            $where_clauses[] = "a.agency_recommended = ?";
            $params[] = $filters['agency_recommended'] ? 1 : 0;
        }

        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM applications a $where_sql",
            $params
        );

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

        return [
            'data' => $this->db->fetchAll($sql, $params),
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }

    /**
     * Get application statistics (global — admin use)
     */
    public function getStats() {
        $stats = [];

        $stats['total']       = $this->db->count('applications');
        $stats['pending']     = $this->db->count('applications', 'status = ?', [APP_STATUS_PENDING]);
        $stats['reviewed']    = $this->db->count('applications', 'status = ?', [APP_STATUS_REVIEWED]);
        $stats['shortlisted'] = $this->db->count('applications', 'status = ?', [APP_STATUS_SHORTLISTED]);
        $stats['accepted']    = $this->db->count('applications', 'status = ?', [APP_STATUS_ACCEPTED]);
        $stats['rejected']    = $this->db->count('applications', 'status = ?', [APP_STATUS_REJECTED]);

        return $stats;
    }

    /**
     * Get per-status counts for a specific job (used in employer applicant tabs)
     */
    public function getStatusCounts($job_id) {
        $sql = "SELECT status, COUNT(*) AS count FROM applications WHERE job_id = ? GROUP BY status";
        $rows = $this->db->fetchAll($sql, [$job_id]);

        $counts = [
            APP_STATUS_PENDING     => 0,
            APP_STATUS_REVIEWED    => 0,
            APP_STATUS_SHORTLISTED => 0,
            APP_STATUS_REJECTED    => 0,
            APP_STATUS_ACCEPTED    => 0,
            'total'                => 0
        ];

        foreach ($rows as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total']       += (int)$row['count'];
        }

        return $counts;
    }

    /**
     * Get per-status counts for a talent (used in talent dashboard/applications page)
     */
    public function getTalentStats($talent_id) {
        $sql = "SELECT status, COUNT(*) AS count FROM applications WHERE talent_id = ? GROUP BY status";
        $rows = $this->db->fetchAll($sql, [$talent_id]);

        $counts = ['total' => 0];
        foreach ($rows as $row) {
            $counts[$row['status']] = (int)$row['count'];
            $counts['total']       += (int)$row['count'];
        }

        return $counts;
    }

    /**
     * Mark as agency recommended
     */
    public function markAsRecommended($id, $recommended = true) {
        // Get application info for logging
        $application = $this->getById($id);
        if (!$application) {
            throw new Exception('Application not found');
        }

        $recommended_val = $recommended ? 1 : 0;
        $sql = "UPDATE applications SET agency_recommended = ? WHERE id = ?";
        $result = $this->db->update($sql, [$recommended_val, $id]);
        
        // Log recommendation change
        $action = $recommended ? 'application_recommended' : 'application_unrecommended';
        $status_text = $recommended ? 'marked as recommended' : 'removed from recommendations';
        
        logActivity($this->db, $action, 
            "Application #{$id} {$status_text} by agency. " .
            "Job: '{$application['job_title']}', Talent: '{$application['talent_name']}'");

        return $result;
    }

    /**
     * Toggle agency recommendation
     */
    public function toggleRecommendation($id) {
        // Get current state
        $application = $this->getById($id);
        if (!$application) {
            throw new Exception('Application not found');
        }

        $was_recommended = $application['agency_recommended'];
        
        $sql = "UPDATE applications SET agency_recommended = NOT agency_recommended WHERE id = ?";
        $result = $this->db->update($sql, [$id]);
        
        // Log toggle
        $new_state = !$was_recommended;
        $action = $new_state ? 'application_recommended' : 'application_unrecommended';
        $status_text = $new_state ? 'marked as recommended' : 'removed from recommendations';
        
        logActivity($this->db, $action, 
            "Application #{$id} {$status_text} by agency (toggled). " .
            "Job: '{$application['job_title']}', Talent: '{$application['talent_name']}'");

        return $result;
    }

    /**
     * Get recent applications (admin dashboard)
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
     * Get pending application count for a talent
     */
    public function getPendingCountForTalent($talent_id) {
        $sql = "SELECT COUNT(*) FROM applications WHERE talent_id = ? AND status = ?";
        return $this->db->fetchColumn($sql, [$talent_id, APP_STATUS_PENDING]);
    }

    /**
     * Withdraw application — verifies talent ownership, only allows pending/reviewed
     */
    public function withdraw($id, $talent_id = null) {
        $app = $this->getById($id);

        if (!$app) {
            logActivity($this->db, 'application_withdraw_failed', 
                "Attempted to withdraw non-existent application #{$id}");
            throw new Exception('Application not found');
        }

        // If talent_id provided, verify ownership
        if ($talent_id !== null && $app['talent_id'] != $talent_id) {
            logActivity($this->db, 'application_withdraw_unauthorized', 
                "Talent #{$talent_id} attempted to withdraw application #{$id} belonging to Talent #{$app['talent_id']}");
            throw new Exception('Access denied.');
        }

        if (!in_array($app['status'], [APP_STATUS_PENDING, APP_STATUS_REVIEWED])) {
            logActivity($this->db, 'application_withdraw_blocked', 
                "Talent #{$talent_id} attempted to withdraw application #{$id} with status '{$app['status']}'");
            throw new Exception('Cannot withdraw this application');
        }

        $result = $this->delete($id);
        
        // Log withdrawal (delete already logs, but adding specific withdraw log)
        logActivity($this->db, 'application_withdrawn', 
            "Application #{$id} withdrawn by " . ($talent_id ? "Talent #{$talent_id}" : "system") . ". " .
            "Job: '{$app['job_title']}', Status: '{$app['status']}'");

        return $result;
    }
    
    public function getByJobWithTalent($job_id) {
        return $this->db->fetchAll(
            "SELECT a.*, t.full_name, t.profile_photo_url
             FROM applications a JOIN talents t ON a.talent_id=t.id
             WHERE a.job_id=? ORDER BY a.applied_at DESC",
            [$job_id]
        );
    }

    public function getRecentRecommended($limit = 20) {
        return $this->db->fetchAll(
            "SELECT a.id, a.status, a.applied_at, a.proposed_rate,
                    t.full_name AS talent_name, t.profile_photo_url,
                    j.title AS job_title, e.company_name
             FROM applications a
             JOIN talents t ON a.talent_id=t.id
             JOIN jobs j ON a.job_id=j.id
             JOIN employers e ON j.employer_id=e.id
             WHERE a.agency_recommended=1
             ORDER BY a.applied_at DESC LIMIT ?",
            [$limit]
        );
    }

    public function getRecentByTalent($talent_id, $limit = 10) {
        return $this->db->fetchAll(
            "SELECT a.*, j.title AS job_title, j.job_type, e.company_name
             FROM applications a
             JOIN jobs j ON j.id = a.job_id
             JOIN employers e ON e.id = j.employer_id
             WHERE a.talent_id = ?
             ORDER BY a.applied_at DESC LIMIT ?",
            [$talent_id, $limit]
        );
    }
}
