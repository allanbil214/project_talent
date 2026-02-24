<?php
// classes/Contract.php

require_once __DIR__ . '/../includes/functions.php'; // Add this at the top

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
                logActivity($this->db, 'contract_creation_failed', 
                    "Contract creation failed - missing required field: {$field}");
                throw new Exception("Missing required field: $field");
            }
        }

        // Prevent duplicate active contract for same job+talent
        $existing = $this->db->fetchOne(
            "SELECT id FROM contracts WHERE job_id = ? AND talent_id = ? AND status = 'active'",
            [$data['job_id'], $data['talent_id']]
        );
        if ($existing) {
            logActivity($this->db, 'contract_creation_failed', 
                "Contract creation failed - active contract already exists for Job #{$data['job_id']} and Talent #{$data['talent_id']}");
            throw new Exception('An active contract already exists for this talent and job.');
        }

        // Get job and talent info for logging
        $job = $this->db->fetchOne("SELECT title FROM jobs WHERE id = ?", [$data['job_id']]);
        $talent = $this->db->fetchOne("SELECT full_name FROM talents WHERE id = ?", [$data['talent_id']]);
        $employer = $this->db->fetchOne("SELECT company_name FROM employers WHERE id = ?", [$data['employer_id']]);

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
            
            // Log application acceptance
            logActivity($this->db, 'application_accepted', 
                "Application #{$data['application_id']} accepted via contract creation. Contract #{$id}");
        }

        // Log contract creation
        logActivity($this->db, 'contract_created', 
            "Contract #{$id} created. Job: '{$job['title']}' (ID: {$data['job_id']}), " .
            "Talent: '{$talent['full_name']}' (ID: {$data['talent_id']}), " .
            "Employer: '{$employer['company_name']}' (ID: {$data['employer_id']}), " .
            "Rate: {$data['rate']}/{$data['rate_type']}, " .
            "Total Amount: " . ($data['total_amount'] ?? 'N/A'));

        return $id;
    }

    /**
     * Update contract status
     */
    public function updateStatus($id, $status, $actor_id = null, $actor_role = null) {
        $allowed = [CONTRACT_STATUS_ACTIVE, CONTRACT_STATUS_COMPLETED, CONTRACT_STATUS_TERMINATED];
        if (!in_array($status, $allowed)) {
            logActivity($this->db, 'contract_status_update_failed', 
                "Contract #{$id} - invalid status attempted: {$status}");
            throw new Exception('Invalid contract status.');
        }

        $contract = $this->getById($id);
        if (!$contract) {
            logActivity($this->db, 'contract_status_update_failed', 
                "Attempted to update status of non-existent contract #{$id}");
            throw new Exception('Contract not found.');
        }

        $old_status = $contract['status'];

        // Access check
        if ($actor_id !== null && $actor_role !== null) {
            if ($actor_role === ROLE_EMPLOYER && $contract['employer_id'] != $actor_id) {
                logActivity($this->db, 'contract_status_update_unauthorized', 
                    "Employer #{$actor_id} attempted to update contract #{$id} belonging to Employer #{$contract['employer_id']}");
                throw new Exception('Access denied.');
            }
            if ($actor_role === ROLE_TALENT && $contract['talent_id'] != $actor_id) {
                logActivity($this->db, 'contract_status_update_unauthorized', 
                    "Talent #{$actor_id} attempted to update contract #{$id} belonging to Talent #{$contract['talent_id']}");
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
            
            logActivity($this->db, 'contract_completed', 
                "Contract #{$id} completed. Talent #{$contract['talent_id']}'s job count incremented.");
        }

        if ($status === CONTRACT_STATUS_TERMINATED) {
            logActivity($this->db, 'contract_terminated', 
                "Contract #{$id} terminated. Job: '{$contract['job_title']}', " .
                "Talent: '{$contract['talent_name']}', Employer: '{$contract['company_name']}'");
        }

        // Log status change
        logActivity($this->db, 'contract_status_changed', 
            "Contract #{$id} status changed from '{$old_status}' to '{$status}' by " .
            ($actor_role ? "{$actor_role} #{$actor_id}" : "system") . ". " .
            "Job: '{$contract['job_title']}', Talent: '{$contract['talent_name']}'");

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
            logActivity($this->db, 'contract_update_failed', 
                "Contract #{$id} update failed - not found, not active, or not owned by Employer #{$employer_id}");
            throw new Exception('Contract not found or cannot be edited.');
        }

        $allowed = ['end_date', 'total_amount', 'contract_document_url'];
        $set = [];
        $params = [];
        $changes = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $set[] = "$field = ?";
                $params[] = $data[$field];
                
                // Track changes
                if ($contract[$field] != $data[$field]) {
                    $old_value = $contract[$field];
                    $changes[] = "{$field}: '{$old_value}' → '{$data[$field]}'";
                }
            }
        }

        if (empty($set)) return false;

        // Recalculate commission if total_amount changed
        if (isset($data['total_amount']) && $data['total_amount'] !== null) {
            $commission_amount = round($data['total_amount'] * ($contract['agency_commission_percentage'] / 100), 2);
            $set[] = 'agency_commission_amount = ?';
            $params[] = $commission_amount;
            $changes[] = "commission_amount: '{$contract['agency_commission_amount']}' → '{$commission_amount}'";
        }

        $set[] = 'updated_at = NOW()';
        $params[] = $id;

        $sql = "UPDATE contracts SET " . implode(', ', $set) . " WHERE id = ?";
        $result = $this->db->update($sql, $params);
        
        // Log updates if something changed
        if ($result && !empty($changes)) {
            logActivity($this->db, 'contract_updated', 
                "Contract #{$id} updated by Employer #{$employer_id}. Changes: " . implode(', ', $changes));
        }

        return $result;
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

    public function getActiveForPayment() {
        return $this->db->fetchAll(
            "SELECT c.id, j.title AS job_title, t.full_name AS talent_name, e.company_name, c.agency_commission_percentage
             FROM contracts c JOIN jobs j ON c.job_id=j.id JOIN talents t ON c.talent_id=t.id JOIN employers e ON c.employer_id=e.id
             WHERE c.status='active' ORDER BY c.created_at DESC"
        );
    }

    public function getRecentByTalent($talent_id, $limit = 5) {
        return $this->db->fetchAll(
            "SELECT c.*, j.title AS job_title, e.company_name
             FROM contracts c
             JOIN jobs j ON j.id = c.job_id
             JOIN employers e ON e.id = c.employer_id
             WHERE c.talent_id = ?
             ORDER BY c.created_at DESC LIMIT ?",
            [$talent_id, $limit]
        );
    }

    // Optional: Add a method to delete/terminate contracts with logging
    public function terminate($id, $reason = null, $actor_id = null, $actor_role = null) {
        $contract = $this->getById($id);
        if (!$contract) {
            throw new Exception('Contract not found');
        }

        // Log termination with reason
        $result = $this->updateStatus($id, CONTRACT_STATUS_TERMINATED, $actor_id, $actor_role);
        
        logActivity($this->db, 'contract_terminated_with_reason', 
            "Contract #{$id} terminated. Reason: " . ($reason ?? 'Not specified') . ". " .
            "Job: '{$contract['job_title']}', Talent: '{$contract['talent_name']}'");
        
        return $result;
    }
}