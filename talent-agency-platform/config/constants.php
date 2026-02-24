<?php
// config/constants.php

// User roles
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_STAFF', 'staff');
define('ROLE_TALENT', 'talent');
define('ROLE_EMPLOYER', 'employer');

// User statuses
define('STATUS_ACTIVE', 'active');
define('STATUS_INACTIVE', 'inactive');
define('STATUS_SUSPENDED', 'suspended');

// Job types
define('JOB_TYPE_FULL_TIME', 'full-time');
define('JOB_TYPE_PART_TIME', 'part-time');
define('JOB_TYPE_CONTRACT', 'contract');
define('JOB_TYPE_FREELANCE', 'freelance');

// Location types
define('LOCATION_ONSITE', 'onsite');
define('LOCATION_REMOTE', 'remote');
define('LOCATION_HYBRID', 'hybrid');

// Job statuses
define('JOB_STATUS_DRAFT', 'draft');
define('JOB_STATUS_PENDING', 'pending_approval');
define('JOB_STATUS_ACTIVE', 'active');
define('JOB_STATUS_FILLED', 'filled');
define('JOB_STATUS_CLOSED', 'closed');
define('JOB_STATUS_DELETED', 'deleted');

// Application statuses
define('APP_STATUS_PENDING', 'pending');
define('APP_STATUS_REVIEWED', 'reviewed');
define('APP_STATUS_SHORTLISTED', 'shortlisted');
define('APP_STATUS_REJECTED', 'rejected');
define('APP_STATUS_ACCEPTED', 'accepted');

// Contract statuses
define('CONTRACT_STATUS_ACTIVE', 'active');
define('CONTRACT_STATUS_COMPLETED', 'completed');
define('CONTRACT_STATUS_TERMINATED', 'terminated');

// Payment statuses
define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_COMPLETED', 'completed');
define('PAYMENT_STATUS_FAILED', 'failed');
define('PAYMENT_STATUS_REFUNDED', 'refunded');

// Availability statuses
define('AVAILABILITY_AVAILABLE', 'available');
define('AVAILABILITY_BUSY', 'busy');
define('AVAILABILITY_UNAVAILABLE', 'unavailable');

// Proficiency levels
define('PROFICIENCY_BEGINNER', 'beginner');
define('PROFICIENCY_INTERMEDIATE', 'intermediate');
define('PROFICIENCY_ADVANCED', 'advanced');
define('PROFICIENCY_EXPERT', 'expert');

// Salary types
define('SALARY_TYPE_HOURLY', 'hourly');
define('SALARY_TYPE_MONTHLY', 'monthly');
define('SALARY_TYPE_PROJECT', 'project');

// Company sizes
define('COMPANY_SIZE_1_10', '1-10');
define('COMPANY_SIZE_11_50', '11-50');
define('COMPANY_SIZE_51_200', '51-200');
define('COMPANY_SIZE_201_500', '201-500');
define('COMPANY_SIZE_500_PLUS', '500+');

// Notification types
define('NOTIFICATION_JOB_MATCH', 'job_match');
define('NOTIFICATION_APPLICATION_UPDATE', 'application_update');
define('NOTIFICATION_PAYMENT_RECEIVED', 'payment_received');
define('NOTIFICATION_NEW_MESSAGE', 'new_message');
define('NOTIFICATION_CONTRACT_CREATED', 'contract_created');
define('NOTIFICATION_REVIEW_RECEIVED', 'review_received');

// Work types
define('WORK_TYPE_FULL_TIME', 'full-time');
define('WORK_TYPE_PART_TIME', 'part-time');
define('WORK_TYPE_FREELANCE', 'freelance');
define('WORK_TYPE_PROJECT', 'project');

// Currency
define('DEFAULT_CURRENCY', 'IDR');

// Application Status
define('APPLICATION_STATUS_PENDING',     APP_STATUS_PENDING);
define('APPLICATION_STATUS_REVIEWED',    APP_STATUS_REVIEWED);
define('APPLICATION_STATUS_SHORTLISTED', APP_STATUS_SHORTLISTED);
define('APPLICATION_STATUS_REJECTED',    APP_STATUS_REJECTED);
define('APPLICATION_STATUS_ACCEPTED',    APP_STATUS_ACCEPTED);