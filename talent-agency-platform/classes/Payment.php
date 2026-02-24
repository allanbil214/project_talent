<?php
// classes/Payment.php

class Payment {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function updateStatus($id, $status) {
        $extra = $status === 'completed' ? ', paid_at = NOW()' : '';
        return $this->db->update(
            "UPDATE payments SET status = ?$extra, updated_at = NOW() WHERE id = ?",
            [$status, $id]
        );
    }

    public function create($contract_id, $amount, $agency_commission, $payment_method, $notes = null) {
        $contract = $this->db->fetchOne("SELECT * FROM contracts WHERE id = ?", [$contract_id]);
        if (!$contract) throw new Exception('Contract not found.');
    
        $employer_user = $this->db->fetchOne("SELECT user_id FROM employers WHERE id = ?", [$contract['employer_id']]);
        $talent_user   = $this->db->fetchOne("SELECT user_id FROM talents WHERE id = ?", [$contract['talent_id']]);
    
        return $this->db->insert(
            "INSERT INTO payments (contract_id, payer_id, payee_id, amount, agency_commission, payment_method, status, notes, paid_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, NOW(), NOW(), NOW())",
            [$contract_id, $employer_user['user_id'], $talent_user['user_id'], $amount, $agency_commission, $payment_method, $notes]
        );
    }

    public function getAll($page = 1, $per_page = 20, $filters = []) {
        $offset = ($page - 1) * $per_page;
        $where_clauses = [];
        $params = [];
    
        if (!empty($filters['status'])) { $where_clauses[] = "p.status = ?"; $params[] = $filters['status']; }
        if (!empty($filters['search'])) {
            $where_clauses[] = "(t.full_name LIKE ? OR e.company_name LIKE ?)";
            $s = '%' . $filters['search'] . '%';
            $params[] = $s; $params[] = $s;
        }
        $where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
        $total = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM payments p
             JOIN contracts c ON p.contract_id = c.id
             JOIN talents t ON c.talent_id = t.id
             JOIN employers e ON c.employer_id = e.id $where_sql", $params
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
    
        return ['data' => $data, 'pagination' => getPagination($total, $page, $per_page)];
    }

    public function getAdminStats() {
        return [
            'total_paid'       => $this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'"),
            'total_commission' => $this->db->fetchColumn("SELECT COALESCE(SUM(agency_commission),0) FROM payments WHERE status='completed'"),
            'pending_count'    => $this->db->fetchColumn("SELECT COUNT(*) FROM payments WHERE status='pending'"),
            'pending_amount'   => $this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='pending'"),
            'total_count'      => $this->db->fetchColumn("SELECT COUNT(*) FROM payments"),
            'refunded'         => $this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='refunded'"),
        ];
    }

    public function getMonthlyRevenue($months = 6) {
        return $this->db->fetchAll(
            "SELECT DATE_FORMAT(paid_at,'%Y-%m') AS month, SUM(amount) AS revenue, SUM(agency_commission) AS commission
             FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(paid_at,'%Y-%m') ORDER BY month ASC",
            [$months]
        );
    }
}