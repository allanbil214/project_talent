<?php
// classes/Mail.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../includes/functions.php'; // Add this at the top

class Mail {
    private $mailer;
    private $db; // Add database property
    
    public function __construct($db = null) { // Accept database connection
        $this->mailer = new PHPMailer(true);
        $this->db = $db; // Store database connection
        $this->configure();
    }
    
    /**
     * Configure PHPMailer
     */
    private function configure() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = SMTP_HOST;
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = SMTP_USER;
            $this->mailer->Password = SMTP_PASS;
            $this->mailer->SMTPSecure = SMTP_SECURE;
            $this->mailer->Port = SMTP_PORT;
            
            // Default sender
            $this->mailer->setFrom(SITE_EMAIL, SITE_NAME);
            $this->mailer->isHTML(true);
            
        } catch (Exception $e) {
            error_log("Mail configuration error: " . $e->getMessage());
        }
    }
    
    /**
     * Send email
     */
    public function send($to, $subject, $body, $alt_body = '') {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->AltBody = $alt_body ?: strip_tags($body);
            
            $result = $this->mailer->send();
            
            // Log successful email
            if ($result && $this->db) {
                logActivity($this->db, 'email_sent', 
                    "Email sent to: {$to}, Subject: {$subject}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $error = $this->mailer->ErrorInfo;
            error_log("Mail sending error: " . $error);
            
            // Log email failure
            if ($this->db) {
                logActivity($this->db, 'email_failed', 
                    "Failed to send email to: {$to}, Subject: {$subject}, Error: {$error}");
            }
            
            return false;
        }
    }
    
    /**
     * Send welcome email
     */
    public function sendWelcomeEmail($to, $name, $role) {
        $subject = "Welcome to " . SITE_NAME;
        
        $role_text = $role === ROLE_TALENT ? 'Talent' : 'Employer';
        
        $body = "
            <h2>Welcome to " . SITE_NAME . "!</h2>
            <p>Hi {$name},</p>
            <p>Thank you for registering as a {$role_text}.</p>
            <p>You can now access your dashboard and start exploring opportunities.</p>
            <p><a href='" . SITE_URL . "/public/login.php'>Login to your account</a></p>
            <br>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        ";
        
        $result = $this->send($to, $subject, $body);
        
        if ($result && $this->db) {
            logActivity($this->db, 'welcome_email_sent', 
                "Welcome email sent to {$role_text}: {$to}, Name: {$name}");
        }
        
        return $result;
    }
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($to, $token) {
        $subject = "Password Reset Request";
        
        $reset_link = SITE_URL . "/public/reset-password.php?token=" . $token;
        
        $body = "
            <h2>Password Reset Request</h2>
            <p>You have requested to reset your password.</p>
            <p>Click the link below to reset your password:</p>
            <p><a href='{$reset_link}'>Reset Password</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you did not request this, please ignore this email.</p>
            <br>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        ";
        
        $result = $this->send($to, $subject, $body);
        
        if ($result && $this->db) {
            logActivity($this->db, 'password_reset_email_sent', 
                "Password reset email sent to: {$to}, Token: {$token}");
        }
        
        return $result;
    }
    
    /**
     * Send application received email to talent
     */
    public function sendApplicationReceivedEmail($to, $talent_name, $job_title) {
        $subject = "Application Received - " . $job_title;
        
        $body = "
            <h2>Application Received</h2>
            <p>Hi {$talent_name},</p>
            <p>Your application for <strong>{$job_title}</strong> has been received.</p>
            <p>The employer will review your application and contact you if interested.</p>
            <p><a href='" . SITE_URL . "/public/talent/applications.php'>View your applications</a></p>
            <br>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        ";
        
        $result = $this->send($to, $subject, $body);
        
        if ($result && $this->db) {
            logActivity($this->db, 'application_received_email_sent', 
                "Application received email sent to talent: {$to}, Name: {$talent_name}, Job: {$job_title}");
        }
        
        return $result;
    }
    
    /**
     * Send new application email to employer
     */
    public function sendNewApplicationEmail($to, $company_name, $talent_name, $job_title) {
        $subject = "New Application - " . $job_title;
        
        $body = "
            <h2>New Application Received</h2>
            <p>Hi {$company_name},</p>
            <p><strong>{$talent_name}</strong> has applied for <strong>{$job_title}</strong>.</p>
            <p><a href='" . SITE_URL . "/public/employer/job-detail.php'>Review application</a></p>
            <br>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        ";
        
        $result = $this->send($to, $subject, $body);
        
        if ($result && $this->db) {
            logActivity($this->db, 'new_application_notification_sent', 
                "New application notification sent to employer: {$to}, Company: {$company_name}, " .
                "Talent: {$talent_name}, Job: {$job_title}");
        }
        
        return $result;
    }
    
    /**
     * Send application status update email
     */
    public function sendApplicationStatusEmail($to, $talent_name, $job_title, $status) {
        $subject = "Application Update - " . $job_title;
        
        $status_messages = [
            APP_STATUS_REVIEWED => "Your application has been reviewed",
            APP_STATUS_SHORTLISTED => "Congratulations! You have been shortlisted",
            APP_STATUS_ACCEPTED => "Congratulations! Your application has been accepted",
            APP_STATUS_REJECTED => "Unfortunately, your application was not successful"
        ];
        
        $message = $status_messages[$status] ?? "Your application status has been updated";
        $status_display = strtoupper($status);
        
        $body = "
            <h2>Application Status Update</h2>
            <p>Hi {$talent_name},</p>
            <p>{$message} for <strong>{$job_title}</strong>.</p>
            <p><a href='" . SITE_URL . "/public/talent/applications.php'>View details</a></p>
            <br>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        ";
        
        $result = $this->send($to, $subject, $body);
        
        if ($result && $this->db) {
            logActivity($this->db, 'application_status_email_sent', 
                "Application status email sent to talent: {$to}, Name: {$talent_name}, " .
                "Job: {$job_title}, Status: {$status_display}");
        }
        
        return $result;
    }
    
    /**
     * Send contract created email
     */
    public function sendContractCreatedEmail($to, $name, $job_title, $start_date) {
        $subject = "New Contract - " . $job_title;
        
        $body = "
            <h2>New Contract Created</h2>
            <p>Hi {$name},</p>
            <p>A new contract has been created for <strong>{$job_title}</strong>.</p>
            <p>Start Date: {$start_date}</p>
            <p><a href='" . SITE_URL . "/public/talent/contracts.php'>View contract details</a></p>
            <br>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        ";
        
        $result = $this->send($to, $subject, $body);
        
        if ($result && $this->db) {
            logActivity($this->db, 'contract_created_email_sent', 
                "Contract created email sent to: {$to}, Name: {$name}, Job: {$job_title}, Start Date: {$start_date}");
        }
        
        return $result;
    }
    
    /**
     * Send payment received email
     */
    public function sendPaymentReceivedEmail($to, $name, $amount, $currency = 'IDR') {
        $subject = "Payment Received";
        
        $formatted_amount = formatCurrency($amount, $currency);
        
        $body = "
            <h2>Payment Received</h2>
            <p>Hi {$name},</p>
            <p>You have received a payment of <strong>{$formatted_amount}</strong>.</p>
            <p><a href='" . SITE_URL . "/public/talent/earnings.php'>View payment details</a></p>
            <br>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        ";
        
        $result = $this->send($to, $subject, $body);
        
        if ($result && $this->db) {
            logActivity($this->db, 'payment_received_email_sent', 
                "Payment received email sent to: {$to}, Name: {$name}, Amount: {$formatted_amount}");
        }
        
        return $result;
    }
    
    /**
     * Send new message notification
     */
    public function sendNewMessageEmail($to, $recipient_name, $sender_name) {
        $subject = "New Message from " . $sender_name;
        
        $body = "
            <h2>New Message</h2>
            <p>Hi {$recipient_name},</p>
            <p>You have received a new message from <strong>{$sender_name}</strong>.</p>
            <p><a href='" . SITE_URL . "/public/talent/messages.php'>Read message</a></p>
            <br>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        ";
        
        $result = $this->send($to, $subject, $body);
        
        if ($result && $this->db) {
            logActivity($this->db, 'new_message_email_sent', 
                "New message notification sent to: {$to}, Recipient: {$recipient_name}, Sender: {$sender_name}");
        }
        
        return $result;
    }
    
    /**
     * Send job approved email to employer
     */
    public function sendJobApprovedEmail($to, $company_name, $job_title) {
        $subject = "Job Posting Approved - " . $job_title;
        
        $body = "
            <h2>Job Posting Approved</h2>
            <p>Hi {$company_name},</p>
            <p>Your job posting <strong>{$job_title}</strong> has been approved and is now live.</p>
            <p>Talented professionals can now apply for this position.</p>
            <p><a href='" . SITE_URL . "/public/employer/jobs.php'>View your job postings</a></p>
            <br>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        ";
        
        $result = $this->send($to, $subject, $body);
        
        if ($result && $this->db) {
            logActivity($this->db, 'job_approved_email_sent', 
                "Job approved email sent to employer: {$to}, Company: {$company_name}, Job: {$job_title}");
        }
        
        return $result;
    }
    
    /**
     * Send review received email
     */
    public function sendReviewReceivedEmail($to, $name, $rating, $reviewer_name) {
        $subject = "New Review Received";
        
        $stars = str_repeat('⭐', $rating);
        
        $body = "
            <h2>New Review Received</h2>
            <p>Hi {$name},</p>
            <p><strong>{$reviewer_name}</strong> has left you a review: {$stars}</p>
            <p><a href='" . SITE_URL . "/public/talent/profile.php'>View your profile</a></p>
            <br>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        ";
        
        $result = $this->send($to, $subject, $body);
        
        if ($result && $this->db) {
            logActivity($this->db, 'review_received_email_sent', 
                "Review received email sent to: {$to}, Name: {$name}, " .
                "Reviewer: {$reviewer_name}, Rating: {$rating} stars");
        }
        
        return $result;
    }
    
    /**
     * Send bulk email (for admin use)
     */
    public function sendBulkEmail($recipients, $subject, $body, $alt_body = '') {
        $success_count = 0;
        $fail_count = 0;
        $failed_emails = [];
        
        foreach ($recipients as $recipient) {
            $to = is_array($recipient) ? $recipient['email'] : $recipient;
            
            if ($this->send($to, $subject, $body, $alt_body)) {
                $success_count++;
            } else {
                $fail_count++;
                $failed_emails[] = $to;
            }
        }
        
        // Log bulk email results
        if ($this->db) {
            logActivity($this->db, 'bulk_email_sent', 
                "Bulk email sent. Subject: {$subject}, " .
                "Success: {$success_count}, Failed: {$fail_count}" .
                (!empty($failed_emails) ? ", Failed emails: " . implode(', ', $failed_emails) : ""));
        }
        
        return [
            'success' => $success_count,
            'fail' => $fail_count,
            'failed_emails' => $failed_emails
        ];
    }
    
    /**
     * Test email configuration
     */
    public function testConfiguration($to) {
        $subject = "Test Email from " . SITE_NAME;
        $body = "<h2>Test Email</h2><p>Your email configuration is working correctly!</p>";
        
        $result = $this->send($to, $subject, $body);
        
        if ($result && $this->db) {
            logActivity($this->db, 'email_test', 
                "Test email sent to: {$to} - Configuration test successful");
        }
        
        return $result;
    }
}