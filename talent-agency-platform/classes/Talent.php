<?php
// classes/Talent.php

require_once __DIR__ . '/../includes/functions.php'; // Add this at the top

class Talent {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }
    
    /**
     * Create talent profile
     */
    public function create($user_id, $data) {
        $sql = "INSERT INTO talents (
                    user_id, full_name, phone, city, country, bio, 
                    availability_status, hourly_rate, currency, 
                    years_experience, date_of_birth, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $params = [
            $user_id,
            $data['full_name'] ?? '',
            $data['phone'] ?? null,
            $data['city'] ?? null,
            $data['country'] ?? null,
            $data['bio'] ?? null,
            $data['availability_status'] ?? AVAILABILITY_AVAILABLE,
            $data['hourly_rate'] ?? null,
            $data['currency'] ?? DEFAULT_CURRENCY,
            $data['years_experience'] ?? 0,
            $data['date_of_birth'] ?? null
        ];
        
        $talent_id = $this->db->insert($sql, $params);
        
        // Log talent profile creation
        logActivity($this->db, 'talent_profile_created', 
            "Talent profile created for User #{$user_id}. " .
            "Name: '{$data['full_name']}', Location: {$data['city']}, {$data['country']}");
        
        return $talent_id;
    }
    
    /**
     * Update talent profile
     */
    public function update($id, $data) {
        // Get current talent info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'talent_update_failed', 
                "Attempted to update non-existent talent #{$id}");
            throw new Exception('Talent not found');
        }
        
        $allowed_fields = [
            'full_name', 'phone', 'city', 'country', 'bio', 
            'profile_photo_url', 'availability_status', 'hourly_rate', 
            'currency', 'preferred_work_type', 'years_experience', 
            'portfolio_url', 'resume_url', 'date_of_birth'
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
        
        $sql = "UPDATE talents SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        $result = $this->db->update($sql, $params);
        
        // Log updates if something changed
        if ($result && !empty($changes)) {
            logActivity($this->db, 'talent_profile_updated', 
                "Talent #{$id} ('{$current['full_name']}') updated. Changes: " . implode(', ', $changes));
        }
        
        return $result;
    }
    
    /**
     * Update profile photo
     */
    public function updateProfilePhoto($id, $photo_url) {
        // Get current talent info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'talent_photo_update_failed', 
                "Attempted to update photo for non-existent talent #{$id}");
            throw new Exception('Talent not found');
        }
        
        $old_photo = $current['profile_photo_url'];
        
        $sql = "UPDATE talents SET profile_photo_url = ?, updated_at = NOW() WHERE id = ?";
        $result = $this->db->update($sql, [$photo_url, $id]);
        
        // Log photo update
        if ($result) {
            logActivity($this->db, 'talent_photo_updated', 
                "Talent #{$id} ('{$current['full_name']}') updated profile photo. " .
                "Old: '{$old_photo}', New: '{$photo_url}'");
        }
        
        return $result;
    }
    
    /**
     * Update resume
     */
    public function updateResume($id, $resume_url) {
        // Get current talent info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'talent_resume_update_failed', 
                "Attempted to update resume for non-existent talent #{$id}");
            throw new Exception('Talent not found');
        }
        
        $old_resume = $current['resume_url'];
        
        $sql = "UPDATE talents SET resume_url = ?, updated_at = NOW() WHERE id = ?";
        $result = $this->db->update($sql, [$resume_url, $id]);
        
        // Log resume update
        if ($result) {
            logActivity($this->db, 'talent_resume_updated', 
                "Talent #{$id} ('{$current['full_name']}') updated resume. " .
                "Old: '{$old_resume}', New: '{$resume_url}'");
        }
        
        return $result;
    }
    
    /**
     * Add skill to talent
     */
    public function addSkill($talent_id, $skill_id, $proficiency_level = PROFICIENCY_INTERMEDIATE) {
        // Get talent and skill info for logging
        $talent = $this->getById($talent_id);
        $skill = $this->db->fetchOne("SELECT name FROM skills WHERE id = ?", [$skill_id]);
        
        if (!$talent || !$skill) {
            logActivity($this->db, 'talent_skill_add_failed', 
                "Failed to add skill #{$skill_id} to talent #{$talent_id} - talent or skill not found");
            return false;
        }
        
        // Check if skill already exists
        $existing = $this->db->fetchOne(
            "SELECT proficiency_level FROM talent_skills WHERE talent_id = ? AND skill_id = ?",
            [$talent_id, $skill_id]
        );
        
        $sql = "INSERT INTO talent_skills (talent_id, skill_id, proficiency_level) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE proficiency_level = ?";
        
        $result = $this->db->query($sql, [$talent_id, $skill_id, $proficiency_level, $proficiency_level]);
        
        // Log skill addition or update
        if ($existing) {
            logActivity($this->db, 'talent_skill_updated', 
                "Talent #{$talent_id} ('{$talent['full_name']}') updated skill '{$skill['name']}' " .
                "proficiency from '{$existing['proficiency_level']}' to '{$proficiency_level}'");
        } else {
            logActivity($this->db, 'talent_skill_added', 
                "Talent #{$talent_id} ('{$talent['full_name']}') added skill '{$skill['name']}' " .
                "with proficiency '{$proficiency_level}'");
        }
        
        return $result;
    }
    
    /**
     * Update skill proficiency
     */
    public function updateSkillProficiency($talent_id, $skill_id, $proficiency_level) {
        // Get talent and skill info for logging
        $talent = $this->getById($talent_id);
        $skill = $this->db->fetchOne("SELECT name FROM skills WHERE id = ?", [$skill_id]);
        
        if (!$talent || !$skill) {
            logActivity($this->db, 'talent_skill_update_failed', 
                "Failed to update skill proficiency for talent #{$talent_id}, skill #{$skill_id}");
            return false;
        }
        
        // Get current proficiency
        $current = $this->db->fetchOne(
            "SELECT proficiency_level FROM talent_skills WHERE talent_id = ? AND skill_id = ?",
            [$talent_id, $skill_id]
        );
        
        $sql = "UPDATE talent_skills 
                SET proficiency_level = ? 
                WHERE talent_id = ? AND skill_id = ?";
        
        $result = $this->db->update($sql, [$proficiency_level, $talent_id, $skill_id]);
        
        // Log proficiency update
        if ($result && $current) {
            logActivity($this->db, 'talent_skill_proficiency_updated', 
                "Talent #{$talent_id} ('{$talent['full_name']}') updated skill '{$skill['name']}' " .
                "proficiency from '{$current['proficiency_level']}' to '{$proficiency_level}'");
        }
        
        return $result;
    }
    
    /**
     * Remove skill from talent
     */
    public function removeSkill($talent_id, $skill_id) {
        // Get talent and skill info for logging
        $talent = $this->getById($talent_id);
        $skill = $this->db->fetchOne("SELECT name FROM skills WHERE id = ?", [$skill_id]);
        
        if (!$talent || !$skill) {
            logActivity($this->db, 'talent_skill_remove_failed', 
                "Failed to remove skill #{$skill_id} from talent #{$talent_id} - talent or skill not found");
            return false;
        }
        
        $sql = "DELETE FROM talent_skills WHERE talent_id = ? AND skill_id = ?";
        $result = $this->db->delete($sql, [$talent_id, $skill_id]);
        
        // Log skill removal
        if ($result) {
            logActivity($this->db, 'talent_skill_removed', 
                "Talent #{$talent_id} ('{$talent['full_name']}') removed skill '{$skill['name']}'");
        }
        
        return $result;
    }
    
    /**
     * Set talent verification status
     */
    public function setVerified($id, $verified = true) {
        // Get current talent info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'talent_verification_failed', 
                "Attempted to verify non-existent talent #{$id}");
            throw new Exception('Talent not found');
        }
        
        $old_status = $current['verified'] ? 'verified' : 'unverified';
        $new_status = $verified ? 'verified' : 'unverified';
        
        $sql = "UPDATE talents SET verified = ?, updated_at = NOW() WHERE id = ?";
        $result = $this->db->update($sql, [$verified ? 1 : 0, $id]);
        
        // Log verification change
        if ($result) {
            logActivity($this->db, 'talent_verification_changed', 
                "Talent #{$id} ('{$current['full_name']}') verification status changed from '{$old_status}' to '{$new_status}'");
        }
        
        return $result;
    }
    
    /**
     * Update availability status
     */
    public function updateAvailability($id, $status) {
        // Get current talent info for logging
        $current = $this->getById($id);
        if (!$current) {
            logActivity($this->db, 'talent_availability_update_failed', 
                "Attempted to update availability for non-existent talent #{$id}");
            throw new Exception('Talent not found');
        }
        
        $old_status = $current['availability_status'];
        
        $sql = "UPDATE talents SET availability_status = ?, updated_at = NOW() WHERE id = ?";
        $result = $this->db->update($sql, [$status, $id]);
        
        // Log availability change
        if ($result && $old_status != $status) {
            logActivity($this->db, 'talent_availability_changed', 
                "Talent #{$id} ('{$current['full_name']}') availability changed from '{$old_status}' to '{$status}'");
        }
        
        return $result;
    }
    
    /**
     * Update rating average
     */
    public function updateRatingAverage($talent_id) {
        $sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews
                FROM reviews 
                WHERE reviewee_id = (SELECT user_id FROM talents WHERE id = ?)";
        
        $result = $this->db->fetchOne($sql, [$talent_id]);
        
        if ($result && $result['total_reviews'] > 0) {
            $talent = $this->getById($talent_id);
            $old_rating = $talent['rating_average'];
            
            $update_sql = "UPDATE talents 
                          SET rating_average = ?, updated_at = NOW() 
                          WHERE id = ?";
            
            $update_result = $this->db->update($update_sql, [$result['avg_rating'], $talent_id]);
            
            // Log rating update if significant change
            if ($update_result && abs($old_rating - $result['avg_rating']) > 0.1) {
                logActivity($this->db, 'talent_rating_updated', 
                    "Talent #{$talent_id} ('{$talent['full_name']}') rating updated from {$old_rating} to {$result['avg_rating']} " .
                    "based on {$result['total_reviews']} reviews");
            }
            
            return $update_result;
        }
        
        return false;
    }
    
    /**
     * Increment completed jobs count
     */
    public function incrementCompletedJobs($talent_id) {
        $talent = $this->getById($talent_id);
        if (!$talent) {
            logActivity($this->db, 'talent_job_increment_failed', 
                "Attempted to increment completed jobs for non-existent talent #{$talent_id}");
            return false;
        }
        
        $old_count = $talent['total_jobs_completed'];
        
        $sql = "UPDATE talents 
                SET total_jobs_completed = total_jobs_completed + 1, updated_at = NOW() 
                WHERE id = ?";
        
        $result = $this->db->update($sql, [$talent_id]);
        
        // Log job completion increment
        if ($result) {
            logActivity($this->db, 'talent_jobs_completed_incremented', 
                "Talent #{$talent_id} ('{$talent['full_name']}') completed jobs count increased from {$old_count} to " . ($old_count + 1));
        }
        
        return $result;
    }
    
    /**
     * Update work preferences
     */
    public function updateWorkPreferences($talent_id, $preferences) {
        $talent = $this->getById($talent_id);
        if (!$talent) {
            logActivity($this->db, 'talent_preferences_update_failed', 
                "Attempted to update work preferences for non-existent talent #{$talent_id}");
            throw new Exception('Talent not found');
        }
        
        $old_preferences = $talent['preferred_work_type'];
        
        if (is_array($preferences)) {
            $preferences = implode(',', $preferences);
        }
        
        $sql = "UPDATE talents SET preferred_work_type = ?, updated_at = NOW() WHERE id = ?";
        $result = $this->db->update($sql, [$preferences, $talent_id]);
        
        // Log preferences update
        if ($result && $old_preferences != $preferences) {
            logActivity($this->db, 'talent_work_preferences_updated', 
                "Talent #{$talent_id} ('{$talent['full_name']}') work preferences updated. " .
                "Old: '{$old_preferences}', New: '{$preferences}'");
        }
        
        return $result;
    }
    
    // Read operations - no logging needed (keep as is)
    
    /**
     * Get talent by ID
     */
    public function getById($id) {
        $sql = "SELECT t.*, u.email, u.status, u.created_at as user_created_at 
                FROM talents t 
                INNER JOIN users u ON t.user_id = u.id 
                WHERE t.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }
    
    /**
     * Get talent by user ID
     */
    public function getByUserId($user_id) {
        $sql = "SELECT t.*, u.email, u.status 
                FROM talents t 
                INNER JOIN users u ON t.user_id = u.id 
                WHERE t.user_id = ?";
        return $this->db->fetchOne($sql, [$user_id]);
    }
    
    /**
     * Get all talents with pagination and filters
     */
    public function getAll($page = 1, $per_page = TALENTS_PER_PAGE, $filters = []) {
        $offset = ($page - 1) * $per_page;
        
        $where_clauses = [];
        $params = [];
        
        // Search filter
        if (!empty($filters['search'])) {
            $where_clauses[] = "(t.full_name LIKE ? OR t.bio LIKE ? OR t.city LIKE ?)";
            $search = '%' . $filters['search'] . '%';
            $params[] = $search;
            $params[] = $search;
            $params[] = $search;
        }
        
        // Availability filter
        if (!empty($filters['availability_status'])) {
            $where_clauses[] = "t.availability_status = ?";
            $params[] = $filters['availability_status'];
        }
        
        // City filter
        if (!empty($filters['city'])) {
            $where_clauses[] = "t.city = ?";
            $params[] = $filters['city'];
        }
        
        // Country filter
        if (!empty($filters['country'])) {
            $where_clauses[] = "t.country = ?";
            $params[] = $filters['country'];
        }
        
        // Experience filter
        if (!empty($filters['min_experience'])) {
            $where_clauses[] = "t.years_experience >= ?";
            $params[] = $filters['min_experience'];
        }
        
        // Hourly rate filter
        if (!empty($filters['max_rate'])) {
            $where_clauses[] = "t.hourly_rate <= ?";
            $params[] = $filters['max_rate'];
        }
        
        // Skills filter
        if (!empty($filters['skills']) && is_array($filters['skills'])) {
            $placeholders = implode(',', array_fill(0, count($filters['skills']), '?'));
            $where_clauses[] = "t.id IN (
                SELECT talent_id FROM talent_skills 
                WHERE skill_id IN ($placeholders)
                GROUP BY talent_id
                HAVING COUNT(DISTINCT skill_id) = ?
            )";
            $params = array_merge($params, $filters['skills']);
            $params[] = count($filters['skills']);
        }
        
        // Verified filter
        if (isset($filters['verified'])) {
            $where_clauses[] = "t.verified = ?";
            $params[] = $filters['verified'] ? 1 : 0;
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        // Get total count
        $count_sql = "SELECT COUNT(*) FROM talents t {$where_sql}";
        $total = $this->db->fetchColumn($count_sql, $params);
        
        // Get data
        $sql = "SELECT t.*, u.email, u.status 
                FROM talents t 
                INNER JOIN users u ON t.user_id = u.id 
                {$where_sql} 
                ORDER BY t.created_at DESC 
                LIMIT ? OFFSET ?";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $talents = $this->db->fetchAll($sql, $params);
        
        // Get skills for each talent
        foreach ($talents as &$talent) {
            $talent['skills'] = $this->getSkills($talent['id']);
        }
        
        return [
            'data' => $talents,
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }
    
    /**
     * Get talent skills
     */
    public function getSkills($talent_id) {
        $sql = "SELECT s.id, s.name, s.category, ts.proficiency_level 
                FROM talent_skills ts 
                INNER JOIN skills s ON ts.skill_id = s.id 
                WHERE ts.talent_id = ? 
                ORDER BY ts.proficiency_level DESC, s.name ASC";
        
        return $this->db->fetchAll($sql, [$talent_id]);
    }
    
    /**
     * Get talent statistics
     */
    public function getStats($talent_id) {
        $stats = [];
        
        $stats['total_applications'] = $this->db->fetchColumn("SELECT COUNT(*) FROM applications WHERE talent_id = ?", [$talent_id]);
        $stats['active_contracts'] = $this->db->fetchColumn("SELECT COUNT(*) FROM contracts WHERE talent_id = ? AND status = ?", [$talent_id, CONTRACT_STATUS_ACTIVE]);
        $stats['completed_jobs'] = $this->db->fetchColumn("SELECT COUNT(*) FROM contracts WHERE talent_id = ? AND status = ?", [$talent_id, CONTRACT_STATUS_COMPLETED]);
        
        $stats['total_earnings'] = $this->db->fetchColumn(
            "SELECT COALESCE(SUM(amount - agency_commission), 0) 
             FROM payments 
             WHERE payee_id = (SELECT user_id FROM talents WHERE id = ?) 
             AND status = ?", 
            [$talent_id, PAYMENT_STATUS_COMPLETED]
        );
        
        $talent = $this->getById($talent_id);
        $stats['average_rating'] = $talent['rating_average'] ?? 0;
        
        return $stats;
    }
    
    /**
     * Search talents (advanced)
     */
    public function search($filters) {
        $where_clauses = ["u.status = 'active'"];
        $params = [];
        
        // Keyword search
        if (!empty($filters['keyword'])) {
            $where_clauses[] = "(t.full_name LIKE ? OR t.bio LIKE ?)";
            $keyword = '%' . $filters['keyword'] . '%';
            $params[] = $keyword;
            $params[] = $keyword;
        }
        
        // Location
        if (!empty($filters['location'])) {
            $where_clauses[] = "(t.city LIKE ? OR t.country LIKE ?)";
            $location = '%' . $filters['location'] . '%';
            $params[] = $location;
            $params[] = $location;
        }
        
        // Availability
        if (!empty($filters['availability'])) {
            $where_clauses[] = "t.availability_status = ?";
            $params[] = $filters['availability'];
        }
        
        // Experience range
        if (!empty($filters['min_experience'])) {
            $where_clauses[] = "t.years_experience >= ?";
            $params[] = $filters['min_experience'];
        }
        
        if (!empty($filters['max_experience'])) {
            $where_clauses[] = "t.years_experience <= ?";
            $params[] = $filters['max_experience'];
        }
        
        // Rate range
        if (!empty($filters['min_rate'])) {
            $where_clauses[] = "t.hourly_rate >= ?";
            $params[] = $filters['min_rate'];
        }
        
        if (!empty($filters['max_rate'])) {
            $where_clauses[] = "t.hourly_rate <= ?";
            $params[] = $filters['max_rate'];
        }
        
        // Only verified
        if (!empty($filters['verified_only'])) {
            $where_clauses[] = "t.verified = 1";
        }
        
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
        
        $sql = "SELECT t.*, u.email 
                FROM talents t 
                INNER JOIN users u ON t.user_id = u.id 
                {$where_sql} 
                ORDER BY t.verified DESC, t.rating_average DESC, t.total_jobs_completed DESC 
                LIMIT 50";
        
        $talents = $this->db->fetchAll($sql, $params);
        
        foreach ($talents as &$talent) {
            $talent['skills'] = $this->getSkills($talent['id']);
        }
        
        return $talents;
    }
    
    /**
     * Get featured talents
     */
    public function getFeatured($limit = 6) {
        $sql = "SELECT t.*, u.email 
                FROM talents t 
                INNER JOIN users u ON t.user_id = u.id 
                WHERE t.verified = 1 AND u.status = 'active'
                ORDER BY t.rating_average DESC, t.total_jobs_completed DESC 
                LIMIT ?";
        
        $talents = $this->db->fetchAll($sql, [$limit]);
        
        foreach ($talents as &$talent) {
            $talent['skills'] = $this->getSkills($talent['id']);
        }
        
        return $talents;
    }
    
    /**
     * Get talent work preferences
     */
    public function getWorkPreferences($talent_id) {
        $talent = $this->getById($talent_id);
        
        if (!$talent || empty($talent['preferred_work_type'])) {
            return [];
        }
        
        return explode(',', $talent['preferred_work_type']);
    }
    
    /**
     * Get suggested talents for job
     */
    public function getSuggestedForJob($job_id, $skill_ids = []) {
        if (!empty($skill_ids)) {
            $placeholders = implode(',', array_fill(0, count($skill_ids), '?'));
            return $this->db->fetchAll(
                "SELECT t.id, t.full_name, t.profile_photo_url, t.city, t.hourly_rate, t.currency,
                        t.availability_status, t.years_experience, t.rating_average, t.verified,
                        COUNT(DISTINCT ts.skill_id) AS matching_skills, u.status AS user_status
                 FROM talents t
                 JOIN users u ON t.user_id = u.id
                 LEFT JOIN talent_skills ts ON ts.talent_id = t.id AND ts.skill_id IN ($placeholders)
                 WHERE u.status = 'active'
                   AND t.id NOT IN (SELECT talent_id FROM applications WHERE job_id = ?)
                 GROUP BY t.id
                 ORDER BY matching_skills DESC, t.verified DESC, t.rating_average DESC
                 LIMIT 30",
                array_merge($skill_ids, [$job_id])
            );
        }
    
        return $this->db->fetchAll(
            "SELECT t.id, t.full_name, t.profile_photo_url, t.city, t.hourly_rate, t.currency,
                    t.availability_status, t.years_experience, t.rating_average, t.verified,
                    0 AS matching_skills, u.status AS user_status
             FROM talents t JOIN users u ON t.user_id=u.id
             WHERE u.status='active' AND t.id NOT IN (SELECT talent_id FROM applications WHERE job_id=?)
             ORDER BY t.verified DESC, t.rating_average DESC LIMIT 30",
            [$job_id]
        );
    }
    
    /**
     * Get admin stats
     */
    public function getAdminStats() {
        $stats = [
            'total'     => $this->db->fetchColumn("SELECT COUNT(*) FROM talents"),
            'verified'  => $this->db->fetchColumn("SELECT COUNT(*) FROM talents WHERE verified = 1"),
            'available' => $this->db->fetchColumn("SELECT COUNT(*) FROM talents WHERE availability_status = 'available'"),
            'suspended' => $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE role = 'talent' AND status = 'suspended'"),
        ];
        
        // Log when admin views talent stats (optional)
        // if (isset($_SESSION['user_id'])) {
        //     logActivity($this->db, 'admin_talent_stats_viewed', 'Talent statistics viewed');
        // }
        
        return $stats;
    }
    
    /**
     * Suspend talent account (admin action)
     */
    public function suspend($id, $reason = null) {
        $talent = $this->getById($id);
        if (!$talent) {
            throw new Exception('Talent not found');
        }
        
        // Update user status to suspended
        $sql = "UPDATE users SET status = 'suspended' WHERE id = ?";
        $result = $this->db->update($sql, [$talent['user_id']]);
        
        if ($result) {
            logActivity($this->db, 'talent_suspended', 
                "Talent #{$id} ('{$talent['full_name']}') suspended. " .
                "Reason: " . ($reason ?? 'Not specified'));
        }
        
        return $result;
    }
    
    /**
     * Activate talent account (admin action)
     */
    public function activate($id) {
        $talent = $this->getById($id);
        if (!$talent) {
            throw new Exception('Talent not found');
        }
        
        // Update user status to active
        $sql = "UPDATE users SET status = 'active' WHERE id = ?";
        $result = $this->db->update($sql, [$talent['user_id']]);
        
        if ($result) {
            logActivity($this->db, 'talent_activated', 
                "Talent #{$id} ('{$talent['full_name']}') activated.");
        }
        
        return $result;
    }
}