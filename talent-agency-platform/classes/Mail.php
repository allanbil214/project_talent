<?php
// classes/Mail.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mail {
    private $mailer;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
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
            
            return $this->mailer->send();
            
        } catch (Exception $e) {
            error_log("Mail sending error: " . $this->mailer->ErrorInfo);
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
        
        return $this->send($to, $subject, $body);
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
        
        return $this->send($to, $subject, $body);
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
        
        return $this->send($to, $subject, $body);
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
        
        return $this->send($to, $subject, $body);
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
        
        $body = "
            <h2>Application Status Update</h2>
            <p>Hi {$talent_name},</p>
            <p>{$message} for <strong>{$job_title}</strong>.</p>
            <p><a href='" . SITE_URL . "/public/talent/applications.php'>View details</a></p>
            <br>
            <p>Best regards,<br>" . SITE_NAME . " Team</p>
        ";
        
        return $this->send($to, $subject, $body);
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
        
        return $this->send($to, $subject, $body);
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
        
        return $this->send($to, $subject, $body);
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
        
        return $this->send($to, $subject, $body);
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
        
        return $this->send($to, $subject, $body);
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
        
        return $this->send($to, $subject, $body);
    }
}