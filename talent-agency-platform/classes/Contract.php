<?php
// classes/Contract.php

class Contract {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Create a contract (typically after accepting an application)
     */
    public function create($data) {
        // Validate required
        $required = ['job_id', 'talent_id', 'employer_id', 'start_date', 'rate', 'rate_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Prevent duplicate active contract for same job+talent
        $existing = $this->db->fetchOne(
            "SELECT id FROM contracts WHERE job_id = ? AND talent_id = ? AND status = 'active'",
            [$data['job_id'], $data['talent_id']]
        );
        if ($existing) {
            throw new Exception('An active contract already exists for this talent and job.');
        }

        $commission = $data['agency_commission_percentage'] ?? DEFAULT_COMMISSION_PERCENTAGE;
        $commission_amount = null;
        if (!empty($data['total_amount'])) {
            $commission_amount = round($data['total_amount'] * ($commission / 100), 2);
        }

        $sql = "INSERT INTO contracts 
                    (job_id, talent_id, employer_id, application_id, start_date, end_date,
                     rate, rate_type, currency, total_amount, agency_commission_percentage,
                     agency_commission_amount, status, contract_document_url, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW(), NOW())";

        $id = $this->db->insert($sql, [
            $data['job_id'],
            $data['talent_id'],
            $data['employer_id'],
            $data['application_id'] ?? null,
            $data['start_date'],
            $data['end_date'] ?? null,
            $data['rate'],
            $data['rate_type'],
            $data['currency'] ?? DEFAULT_CURRENCY,
            $data['total_amount'] ?? null,
            $commission,
            $commission_amount,
            $data['contract_document_url'] ?? null
        ]);

        // Mark job as filled if contract created
        $this->db->update(
            "UPDATE jobs SET status = 'filled', filled_at = NOW() WHERE id = ?",
            [$data['job_id']]
        );

        // Mark application as accepted if application_id given
        if (!empty($data['application_id'])) {
            $this->db->update(
                "UPDATE applications SET status = 'accepted', reviewed_at = NOW() WHERE id = ?",
                [$data['application_id']]
            );
        }

        return $id;
    }

    /**
     * Get contract by ID with full details
     */
    public function getById($id) {
        $sql = "SELECT c.*,
                       j.title AS job_title, j.job_type, j.location_type,
                       t.full_name AS talent_name, t.profile_photo_url, t.city AS talent_city,
                       u_t.email AS talent_email,
                       e.company_name, e.company_logo_url,
                       u_e.email AS employer_email
                FROM contracts c
                JOIN jobs j ON c.job_id = j.id
                JOIN talents t ON c.talent_id = t.id
                JOIN users u_t ON t.user_id = u_t.id
                JOIN employers e ON c.employer_id = e.id
                JOIN users u_e ON e.user_id = u_e.id
                WHERE c.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Get contracts for a talent
     */
    public function getByTalent($talent_id, $page = 1, $per_page = ITEMS_PER_PAGE, $filters = []) {
        return $this->_getList('c.talent_id = ?', [$talent_id], $page, $per_page, $filters);
    }

    /**
     * Get contracts for an employer
     */
    public function getByEmployer($employer_id, $page = 1, $per_page = ITEMS_PER_PAGE, $filters = []) {
        return $this->_getList('c.employer_id = ?', [$employer_id], $page, $per_page, $filters);
    }

    /**
     * Get all contracts (admin)
     */
    public function getAll($page = 1, $per_page = ITEMS_PER_PAGE, $filters = []) {
        return $this->_getList('1=1', [], $page, $per_page, $filters);
    }

    /**
     * Internal: paginated contract list
     */
    private function _getList($base_where, $base_params, $page, $per_page, $filters) {
        $offset = ($page - 1) * $per_page;
        $where = [$base_where];
        $params = $base_params;

        if (!empty($filters['status'])) {
            $where[] = 'c.status = ?';
            $params[] = $filters['status'];
        }

        $where_sql = 'WHERE ' . implode(' AND ', $where);

        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM contracts c $where_sql",
            $params
        );

        $sql = "SELECT c.*,
                       j.title AS job_title, j.job_type,
                       t.full_name AS talent_name, t.profile_photo_url,
                       e.company_name, e.company_logo_url
                FROM contracts c
                JOIN jobs j ON c.job_id = j.id
                JOIN talents t ON c.talent_id = t.id
                JOIN employers e ON c.employer_id = e.id
                $where_sql
                ORDER BY c.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $per_page;
        $params[] = $offset;

        return [
            'data' => $this->db->fetchAll($sql, $params),
            'pagination' => getPagination($total, $page, $per_page)
        ];
    }

    /**
     * Update contract status
     */
    public function updateStatus($id, $status, $actor_id = null, $actor_role = null) {
        $allowed = [CONTRACT_STATUS_ACTIVE, CONTRACT_STATUS_COMPLETED, CONTRACT_STATUS_TERMINATED];
        if (!in_array($status, $allowed)) {
            throw new Exception('Invalid contract status.');
        }

        $contract = $this->getById($id);
        if (!$contract) {
            throw new Exception('Contract not found.');
        }

        // Access check
        if ($actor_id !== null && $actor_role !== null) {
            if ($actor_role === ROLE_EMPLOYER && $contract['employer_id'] != $actor_id) {
                throw new Exception('Access denied.');
            }
            if ($actor_role === ROLE_TALENT && $contract['talent_id'] != $actor_id) {
                throw new Exception('Access denied.');
            }
        }

        $this->db->update(
            "UPDATE contracts SET status = ?, updated_at = NOW() WHERE id = ?",
            [$status, $id]
        );

        // If completed, increment talent's total_jobs_completed
        if ($status === CONTRACT_STATUS_COMPLETED) {
            $this->db->update(
                "UPDATE talents SET total_jobs_completed = total_jobs_completed + 1 WHERE id = ?",
                [$contract['talent_id']]
            );
        }

        return true;
    }

    /**
     * Update contract details (employer only, active contracts)
     */
    public function update($id, $employer_id, $data) {
        $contract = $this->db->fetchOne(
            "SELECT * FROM contracts WHERE id = ? AND employer_id = ? AND status = 'active'",
            [$id, $employer_id]
        );
        if (!$contract) {
            throw new Exception('Contract not found or cannot be edited.');
        }

        $allowed = ['end_date', 'total_amount', 'contract_document_url'];
        $set = [];
        $params = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $set[] = "$field = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($set)) return false;

        // Recalculate commission if total_amount changed
        if (isset($data['total_amount']) && $data['total_amount'] !== null) {
            $commission_amount = round($data['total_amount'] * ($contract['agency_commission_percentage'] / 100), 2);
            $set[] = 'agency_commission_amount = ?';
            $params[] = $commission_amount;
        }

        $set[] = 'updated_at = NOW()';
        $params[] = $id;

        $sql = "UPDATE contracts SET " . implode(', ', $set) . " WHERE id = ?";
        return $this->db->update($sql, $params);
    }

    /**
     * Get summary stats
     */
    public function getStats($scope = 'all', $scope_id = null) {
        $where  = 'AND 1=1';
        $params = [];

        if ($scope === 'employer' && $scope_id) {
            $where = 'AND employer_id = ?';
            $params = [$scope_id];
        } elseif ($scope === 'talent' && $scope_id) {
            $where = 'AND talent_id = ?';
            $params = [$scope_id];
        }

        $total      = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM contracts WHERE 1 $where", $params);
        $active     = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM contracts WHERE status = 'active' $where", $params);
        $completed  = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM contracts WHERE status = 'completed' $where", $params);
        $terminated = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM contracts WHERE status = 'terminated' $where", $params);
        $total_value      = $this->db->fetchColumn("SELECT COALESCE(SUM(total_amount), 0) FROM contracts WHERE 1 $where", $params);
        $total_commission = $this->db->fetchColumn("SELECT COALESCE(SUM(agency_commission_amount), 0) FROM contracts WHERE 1 $where", $params);

        return [
            'total'            => $total,
            'active'           => $active,
            'completed'        => $completed,
            'terminated'       => $terminated,
            'total_value'      => $total_value,
            'total_commission' => $total_commission,
        ];
    }
}