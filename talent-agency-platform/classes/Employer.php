<?php
// classes/Employer.php

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

        return $this->db->insert($sql, $params);
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
     * Update employer profile
     */
    public function update($id, $data) {
        $allowed_fields = [
            'company_name', 'company_logo_url', 'industry', 'company_size',
            'website', 'description', 'address', 'phone'
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

        $sql = "UPDATE employers SET " . implode(', ', $set_clauses) . " WHERE id = ?";
        return $this->db->update($sql, $params);
    }

    /**
     * Update company logo
     */
    public function updateLogo($id, $logo_url) {
        $sql = "UPDATE employers SET company_logo_url = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$logo_url, $id]);
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
     * Set verification status
     */
    public function setVerified($id, $verified = true) {
        $sql = "UPDATE employers SET verified = ?, updated_at = NOW() WHERE id = ?";
        return $this->db->update($sql, [$verified ? 1 : 0, $id]);
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
            $update_sql = "UPDATE employers SET rating_average = ?, updated_at = NOW() WHERE id = ?";
            return $this->db->update($update_sql, [$result['avg_rating'], $employer_id]);
        }

        return false;
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
}
