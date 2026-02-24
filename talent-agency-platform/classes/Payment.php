<?php
// classes/Payment.php

require_once __DIR__ . '/../includes/functions.php'; // Add this at the top

class Payment {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }

    /**
     * Update payment status
     */
    public function updateStatus($id, $status) {
        // Get current payment info for logging
        $payment = $this->getById($id);
        if (!$payment) {
            logActivity($this->db, 'payment_status_update_failed', 
                "Attempted to update status of non-existent payment #{$id}");
            throw new Exception('Payment not found');
        }
        
        $old_status = $payment['status'];
        
        $extra = $status === 'completed' ? ', paid_at = NOW()' : '';
        $result = $this->db->update(
            "UPDATE payments SET status = ?$extra, updated_at = NOW() WHERE id = ?",
            [$status, $id]
        );
        
        // Log status change
        if ($result) {
            $action = $status === 'completed' ? 'payment_completed' : 
                     ($status === 'refunded' ? 'payment_refunded' : 'payment_status_changed');
            
            logActivity($this->db, $action, 
                "Payment #{$id} status changed from '{$old_status}' to '{$status}'. " .
                "Amount: " . formatCurrency($payment['amount']) . ", " .
                "Job: '{$payment['job_title']}', Talent: '{$payment['talent_name']}'");
        }
        
        return $result;
    }

    /**
     * Create payment
     */
    public function create($contract_id, $amount, $agency_commission, $payment_method, $notes = null) {
        $contract = $this->db->fetchOne("SELECT * FROM contracts WHERE id = ?", [$contract_id]);
        if (!$contract) {
            logActivity($this->db, 'payment_creation_failed', 
                "Attempted to create payment for non-existent contract #{$contract_id}");
            throw new Exception('Contract not found.');
        }
    
        $employer_user = $this->db->fetchOne("SELECT user_id FROM employers WHERE id = ?", [$contract['employer_id']]);
        $talent_user   = $this->db->fetchOne("SELECT user_id FROM talents WHERE id = ?", [$contract['talent_id']]);
        
        // Get job and names for logging
        $job = $this->db->fetchOne("SELECT title FROM jobs WHERE id = ?", [$contract['job_id']]);
        $talent = $this->db->fetchOne("SELECT full_name FROM talents WHERE id = ?", [$contract['talent_id']]);
        $employer = $this->db->fetchOne("SELECT company_name FROM employers WHERE id = ?", [$contract['employer_id']]);
    
        $payment_id = $this->db->insert(
            "INSERT INTO payments (contract_id, payer_id, payee_id, amount, agency_commission, payment_method, status, notes, paid_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, NOW(), NOW(), NOW())",
            [$contract_id, $employer_user['user_id'], $talent_user['user_id'], $amount, $agency_commission, $payment_method, $notes]
        );
        
        // Log payment creation
        logActivity($this->db, 'payment_created', 
            "Payment #{$payment_id} created for Contract #{$contract_id}. " .
            "Job: '{$job['title']}', Talent: '{$talent['full_name']}', Employer: '{$employer['company_name']}', " .
            "Amount: " . formatCurrency($amount) . ", Commission: " . formatCurrency($agency_commission) . ", " .
            "Method: {$payment_method}");
        
        return $payment_id;
    }

    /**
     * Get all payments with pagination and filters
     */
    public function getAll($page = 1, $per_page = 20, $filters = []) {
        $offset = ($page - 1) * $per_page;
        $where_clauses = [];
        $params = [];
    
        if (!empty($filters['status'])) { 
            $where_clauses[] = "p.status = ?"; 
            $params[] = $filters['status']; 
        }
        
        if (!empty($filters['search'])) {
            $where_clauses[] = "(t.full_name LIKE ? OR e.company_name LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            $params[] = $s; 
            $params[] = $s;
        }
        
        $where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM payments p
             JOIN contracts c ON p.contract_id = c.id
             JOIN talents t ON c.talent_id = t.id
             JOIN employers e ON c.employer_id = e.id $where_sql", 
            $params
        );
        
        $data = $this->db->fetchAll(
            "SELECT p.*, j.title AS job_title, t.full_name AS talent_name, e.company_name
             FROM payments p
             JOIN contracts c ON p.contract_id = c.id
             JOIN jobs j ON c.job_id = j.id
             JOIN talents t ON c.talent_id = t.id
             JOIN employers e ON c.employer_id = e.id
             $where_sql ORDER BY p.created_at DESC LIMIT ? OFFSET ?",
            array_merge($params, [$per_page, $offset])
        );
        
        // Log when someone searches payments (admin audit)
        if (!empty($filters['search']) && isset($_SESSION['user_id'])) {
            logActivity($this->db, 'payments_searched', 
                "User #{$_SESSION['user_id']} searched payments with term: '{$filters['search']}'");
        }
    
        return ['data' => $data, 'pagination' => getPagination($total, $page, $per_page)];
    }

    /**
     * Get payment by ID (helper method - needed for logging)
     */
    private function getById($id) {
        $sql = "SELECT p.*, j.title AS job_title, t.full_name AS talent_name, e.company_name
                FROM payments p
                JOIN contracts c ON p.contract_id = c.id
                JOIN jobs j ON c.job_id = j.id
                JOIN talents t ON c.talent_id = t.id
                JOIN employers e ON c.employer_id = e.id
                WHERE p.id = ?";
        return $this->db->fetchOne($sql, [$id]);
    }

    /**
     * Get admin stats
     */
    public function getAdminStats() {
        $stats = [
            'total_paid'       => $this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'"),
            'total_commission' => $this->db->fetchColumn("SELECT COALESCE(SUM(agency_commission),0) FROM payments WHERE status='completed'"),
            'pending_count'    => $this->db->fetchColumn("SELECT COUNT(*) FROM payments WHERE status='pending'"),
            'pending_amount'   => $this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='pending'"),
            'total_count'      => $this->db->fetchColumn("SELECT COUNT(*) FROM payments"),
            'refunded'         => $this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='refunded'"),
        ];
        
        // Log when admin views payment stats (optional - might be too noisy)
        // if (isset($_SESSION['user_id'])) {
        //     logActivity($this->db, 'admin_payment_stats_viewed', 'Payment statistics viewed');
        // }
        
        return $stats;
    }

    /**
     * Get monthly revenue
     */
    public function getMonthlyRevenue($months = 6) {
        $results = $this->db->fetchAll(
            "SELECT DATE_FORMAT(paid_at,'%Y-%m') AS month, SUM(amount) AS revenue, SUM(agency_commission) AS commission
             FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(paid_at,'%Y-%m') ORDER BY month ASC",
            [$months]
        );
        
        // Log when monthly revenue report is generated
        if (isset($_SESSION['user_id'])) {
            logActivity($this->db, 'monthly_revenue_viewed', 
                "Monthly revenue report generated for last {$months} months");
        }
        
        return $results;
    }

    /**
     * Process refund (additional method that might be useful)
     */
    public function refund($id, $reason = null) {
        $payment = $this->getById($id);
        if (!$payment) {
            logActivity($this->db, 'payment_refund_failed', 
                "Attempted to refund non-existent payment #{$id}");
            throw new Exception('Payment not found');
        }
        
        if ($payment['status'] !== 'completed') {
            logActivity($this->db, 'payment_refund_failed', 
                "Attempted to refund payment #{$id} with status '{$payment['status']}' - only completed payments can be refunded");
            throw new Exception('Only completed payments can be refunded');
        }
        
        $result = $this->updateStatus($id, 'refunded');
        
        if ($result) {
            logActivity($this->db, 'payment_refunded_with_reason', 
                "Payment #{$id} refunded. Amount: " . formatCurrency($payment['amount']) . ", " .
                "Reason: " . ($reason ?? 'Not specified'));
        }
        
        return $result;
    }
}