<?php
// classes/Setting.php

class Setting {
    private $db;
    
    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function ensureTable() {
        try {
            $this->db->fetchColumn("SELECT COUNT(*) FROM settings");
        } catch (Exception $e) {
            $this->db->query("CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) NOT NULL UNIQUE,
                setting_value TEXT,
                setting_group VARCHAR(50) DEFAULT 'general',
                label VARCHAR(255),
                description TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )", []);
            $this->seedDefaults();
        }
    }
    
    private function seedDefaults() {
        $defaults = [
            ['commission_percentage',  DEFAULT_COMMISSION_PERCENTAGE, 'financial', 'Default Commission %',     'Agency commission percentage deducted from each payment'],
            ['site_name',              SITE_NAME,                      'general',   'Site Name',                'Name of the platform shown in the UI'],
            ['site_email',             SITE_EMAIL,                     'general',   'Support Email',            'Contact email shown to users'],
            ['jobs_per_page',          JOBS_PER_PAGE,                  'general',   'Jobs Per Page',            'How many jobs to show per page in listings'],
            ['talents_per_page',       TALENTS_PER_PAGE,               'general',   'Talents Per Page',         'How many talents to show per page'],
            ['max_file_size_mb',       10,                             'uploads',   'Max File Size (MB)',        'Maximum allowed file upload size'],
            ['allow_registration',     '1',                            'general',   'Allow Registration',       'Allow new talent/employer sign-ups (1 = yes, 0 = no)'],
            ['require_job_approval',   '1',                            'general',   'Require Job Approval',     'Jobs must be approved by admin before going live'],
            ['min_password_length',    '8',                            'security',  'Min Password Length',      'Minimum number of characters required in passwords'],
            ['session_lifetime_hours', '24',                           'security',  'Session Lifetime (hours)', 'How long before users are automatically logged out'],
            ['smtp_host',              SMTP_HOST,                      'email',     'SMTP Host',                'Outgoing mail server hostname'],
            ['smtp_port',              SMTP_PORT,                      'email',     'SMTP Port',                'Mail server port (587 for TLS, 465 for SSL)'],
            ['smtp_user',              '',                             'email',     'SMTP Username',            'Email account used to send system emails'],
            ['notification_email',     SITE_EMAIL,                     'email',     'Notification Email',       'Where to send admin notifications'],
        ];
        foreach ($defaults as [$key, $val, $grp, $lbl, $desc]) {
            try {
                $this->db->insert(
                    "INSERT IGNORE INTO settings (setting_key, setting_value, setting_group, label, description) VALUES (?, ?, ?, ?, ?)",
                    [$key, $val, $grp, $lbl, $desc]
                );
            } catch (Exception $inner) {}
        }
    }

    public function set($key, $value) {
        return $this->db->query(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()",
            [$key, $value, $value]
        );
    }

    public function getAll() {
        $rows = $this->db->fetchAll("SELECT * FROM settings ORDER BY setting_group, label ASC");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_group']][$row['setting_key']] = $row;
        }
        return $settings;
    }

    public function getSystemInfo() {
        return [
            'php_version' => PHP_VERSION,
            'db_version'  => $this->db->fetchColumn("SELECT VERSION()"),
            'server'      => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'total_users' => $this->db->fetchColumn("SELECT COUNT(*) FROM users"),
            'total_jobs'  => $this->db->fetchColumn("SELECT COUNT(*) FROM jobs"),
            'db_size'     => $this->db->fetchColumn(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
                 FROM information_schema.TABLES WHERE table_schema = DATABASE()"
            ),
        ];
    }
}