<?php
// public/employer/dashboard.php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_EMPLOYER);

$page_title = 'Dashboard - ' . SITE_NAME;
$body_class = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">Dashboard</h2>
                    <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</p>
                </div>
                <div>
                    <a href="<?php echo SITE_URL; ?>/public/employer/post-job.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Post New Job
                    </a>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Active Jobs</h6>
                                    <h3 class="mb-0" id="activeJobs">0</h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-briefcase fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">New Applications</h6>
                                    <h3 class="mb-0" id="newApplications">0</h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-file-alt fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Active Hires</h6>
                                    <h3 class="mb-0" id="activeHires">0</h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-users fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Total Spent</h6>
                                    <h3 class="mb-0" id="totalSpent">Rp 0</h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Recent Applications -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-alt text-primary"></i> Recent Applications
                                </h5>
                                <a href="<?php echo SITE_URL; ?>/public/employer/jobs.php" class="btn btn-sm btn-outline-primary">
                                    View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="recentApplications">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-bolt text-primary"></i> Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="<?php echo SITE_URL; ?>/public/employer/post-job.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Post New Job
                                </a>
                                <a href="<?php echo SITE_URL; ?>/public/employer/talents.php" class="btn btn-outline-primary">
                                    <i class="fas fa-search"></i> Browse Talents
                                </a>
                                <a href="<?php echo SITE_URL; ?>/public/employer/jobs.php" class="btn btn-outline-primary">
                                    <i class="fas fa-briefcase"></i> Manage Jobs
                                </a>
                                <a href="<?php echo SITE_URL; ?>/public/employer/contracts.php" class="btn btn-outline-primary">
                                    <i class="fas fa-file-contract"></i> View Contracts
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line text-primary"></i> Quick Stats
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted">Jobs Filled This Month</small>
                                <h4 class="mb-0" id="jobsFilledMonth">0</h4>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">Average Time to Fill</small>
                                <h4 class="mb-0" id="avgTimeToFill">0 days</h4>
                            </div>
                            <div>
                                <small class="text-muted">Application Response Rate</small>
                                <h4 class="mb-0" id="responseRate">0%</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Jobs -->
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-briefcase text-primary"></i> Active Job Postings
                                </h5>
                                <a href="<?php echo SITE_URL; ?>/public/employer/jobs.php" class="btn btn-sm btn-outline-primary">
                                    View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="activeJobsList">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>