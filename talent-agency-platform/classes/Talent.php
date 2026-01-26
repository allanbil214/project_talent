<?php
// classes/Talent.php

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
        
        return $this->db->insert($sql, $params);
    }
    
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
     * Update talent profile
     */
    public function update($id, $data) {
        $allowed_fields = [
            'full_name', 'phone', 'city', 'country', 'bio', 
            'profile_photo_url', 'availability_status', 'hourly_rate', 
            'currency', 'preferred_work_type', 'years_experience', 
            'portfolio_url', 'resume_url', 'date_of_birth'
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
        
        $sql = "UPDATE talents SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        return $this->db->update($sql, $params);
    }
    
    /**
     * Update profile photo
     */
    public function updateProfilePhoto($id, $photo_url) {
        $sql = "UPDATE talents SET profile_photo_url = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$photo_url, $id]);
    }
    
    /**
     * Update resume
     */
    public function updateResume($id, $resume_url) {
        $sql = "UPDATE talents SET resume_url = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$resume_url, $id]);
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
     * Add skill to talent
     */
    public function addSkill($talent_id, $skill_id, $proficiency_level = PROFICIENCY_INTERMEDIATE) {
        $sql = "INSERT INTO talent_skills (talent_id, skill_id, proficiency_level) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE proficiency_level = ?";
        
        return $this->db->query($sql, [$talent_id, $skill_id, $proficiency_level, $proficiency_level]);
    }
    
    /**
     * Update skill proficiency
     */
    public function updateSkillProficiency($talent_id, $skill_id, $proficiency_level) {
        $sql = "UPDATE talent_skills 
                SET proficiency_level = ? 
                WHERE talent_id = ? AND skill_id = ?";
        
        return $this->db->update($sql, [$proficiency_level, $talent_id, $skill_id]);
    }
    
    /**
     * Remove skill from talent
     */
    public function removeSkill($talent_id, $skill_id) {
        $sql = "DELETE FROM talent_skills WHERE talent_id = ? AND skill_id = ?";
        return $this->db->delete($sql, [$talent_id, $skill_id]);
    }
    
    /**
     * Set talent verification status
     */
    public function setVerified($id, $verified = true) {
        $sql = "UPDATE talents SET verified = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$verified ? 1 : 0, $id]);
    }
    
    /**
     * Update availability status
     */
    public function updateAvailability($id, $status) {
        $sql = "UPDATE talents SET availability_status = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$status, $id]);
    }
    
    /**
     * Get talent statistics
     */
    public function getStats($talent_id) {
        $stats = [];
        
        // Total applications
        $sql = "SELECT COUNT(*) FROM applications WHERE talent_id = ?";
        $stats['total_applications'] = $this->db->fetchColumn($sql, [$talent_id]);
        
        // Active contracts
        $sql = "SELECT COUNT(*) FROM contracts WHERE talent_id = ? AND status = ?";
        $stats['active_contracts'] = $this->db->fetchColumn($sql, [$talent_id, CONTRACT_STATUS_ACTIVE]);
        
        // Completed jobs
        $sql = "SELECT COUNT(*) FROM contracts WHERE talent_id = ? AND status = ?";
        $stats['completed_jobs'] = $this->db->fetchColumn($sql, [$talent_id, CONTRACT_STATUS_COMPLETED]);
        
        // Total earnings
        $sql = "SELECT COALESCE(SUM(amount - agency_commission), 0) 
                FROM payments 
                WHERE payee_id = (SELECT user_id FROM talents WHERE id = ?) 
                AND status = ?";
        $stats['total_earnings'] = $this->db->fetchColumn($sql, [$talent_id, PAYMENT_STATUS_COMPLETED]);
        
        // Average rating
        $talent = $this->getById($talent_id);
        $stats['average_rating'] = $talent['rating_average'] ?? 0;
        
        return $stats;
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
            $update_sql = "UPDATE talents 
                          SET rating_average = ?, updated_at = NOW() 
                          WHERE id = ?";
            
            return $this->db->update($update_sql, [$result['avg_rating'], $talent_id]);
        }
        
        return false;
    }
    
    /**
     * Increment completed jobs count
     */
    public function incrementCompletedJobs($talent_id) {
        $sql = "UPDATE talents 
                SET total_jobs_completed = total_jobs_completed + 1, updated_at = NOW() 
                WHERE id = ?";
        
        return $this->db->update($sql, [$talent_id]);
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
        
        // Get skills for each talent
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
     * Update work preferences
     */
    public function updateWorkPreferences($talent_id, $preferences) {
        if (is_array($preferences)) {
            $preferences = implode(',', $preferences);
        }
        
        $sql = "UPDATE talents SET preferred_work_type = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$preferences, $talent_id]);
    }
}