<?php
// classes/Report.php

class Report {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function getOverview($date_from, $date_to) {
        return [
            'total_users'         => $this->db->fetchColumn("SELECT COUNT(*) FROM users"),
            'new_users'           => $this->db->fetchColumn("SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN ? AND ?", [$date_from, $date_to]),
            'total_talents'       => $this->db->fetchColumn("SELECT COUNT(*) FROM talents"),
            'total_employers'     => $this->db->fetchColumn("SELECT COUNT(*) FROM employers"),
            'active_jobs'         => $this->db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE status='active'"),
            'jobs_posted'         => $this->db->fetchColumn("SELECT COUNT(*) FROM jobs WHERE DATE(created_at) BETWEEN ? AND ?", [$date_from, $date_to]),
            'total_applications'  => $this->db->fetchColumn("SELECT COUNT(*) FROM applications"),
            'applications_period' => $this->db->fetchColumn("SELECT COUNT(*) FROM applications WHERE DATE(applied_at) BETWEEN ? AND ?", [$date_from, $date_to]),
            'active_contracts'    => $this->db->fetchColumn("SELECT COUNT(*) FROM contracts WHERE status='active'"),
            'completed_contracts' => $this->db->fetchColumn("SELECT COUNT(*) FROM contracts WHERE status='completed'"),
            'revenue_period'      => $this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND DATE(paid_at) BETWEEN ? AND ?", [$date_from, $date_to]),
            'commission_period'   => $this->db->fetchColumn("SELECT COALESCE(SUM(agency_commission),0) FROM payments WHERE status='completed' AND DATE(paid_at) BETWEEN ? AND ?", [$date_from, $date_to]),
            'total_revenue'       => $this->db->fetchColumn("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'"),
            'total_commission'    => $this->db->fetchColumn("SELECT COALESCE(SUM(agency_commission),0) FROM payments WHERE status='completed'"),
        ];
    }

    public function getMonthlyRevenue($months = 12) {
        return $this->db->fetchAll(
            "SELECT DATE_FORMAT(paid_at,'%Y-%m') AS month, SUM(amount) AS revenue, SUM(agency_commission) AS commission, COUNT(*) AS transactions
             FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
             GROUP BY DATE_FORMAT(paid_at,'%Y-%m') ORDER BY month ASC",
            [$months]
        );
    }

    public function getTopTalents($limit = 10) {
        return $this->db->fetchAll(
            "SELECT t.full_name, t.profile_photo_url, t.rating_average, t.total_jobs_completed,
                    COUNT(c.id) AS contract_count, COALESCE(SUM(c.total_amount),0) AS total_value
             FROM talents t LEFT JOIN contracts c ON c.talent_id=t.id AND c.status='completed'
             GROUP BY t.id ORDER BY contract_count DESC, total_value DESC LIMIT ?",
            [$limit]
        );
    }

    public function getTopEmployers($limit = 10) {
        return $this->db->fetchAll(
            "SELECT e.company_name, e.company_logo_url, e.industry, COUNT(DISTINCT j.id) AS jobs_posted,
                    COUNT(DISTINCT c.id) AS placements, COALESCE(SUM(p.amount),0) AS total_spent
             FROM employers e LEFT JOIN jobs j ON j.employer_id=e.id
             LEFT JOIN contracts c ON c.employer_id=e.id AND c.status='completed'
             LEFT JOIN payments p ON p.contract_id=c.id AND p.status='completed'
             GROUP BY e.id ORDER BY placements DESC, total_spent DESC LIMIT ?",
            [$limit]
        );
    }

    public function getJobTypeBreakdown() {
        return $this->db->fetchAll(
            "SELECT job_type, COUNT(*) AS total,
                    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status='filled' THEN 1 ELSE 0 END) AS filled
             FROM jobs GROUP BY job_type ORDER BY total DESC"
        );
    }

    public function getApplicationFunnel() {
        return $this->db->fetchAll(
            "SELECT status, COUNT(*) AS count FROM applications
             GROUP BY status ORDER BY FIELD(status,'pending','reviewed','shortlisted','accepted','rejected')"
        );
    }

    public function getSkillDemand($limit = 15) {
        return $this->db->fetchAll(
            "SELECT s.name, s.category, COUNT(DISTINCT js.job_id) AS job_demand, COUNT(DISTINCT ts.talent_id) AS talent_supply
             FROM skills s LEFT JOIN job_skills js ON js.skill_id=s.id LEFT JOIN talent_skills ts ON ts.skill_id=s.id
             GROUP BY s.id ORDER BY job_demand DESC LIMIT ?",
            [$limit]
        );
    }   
}