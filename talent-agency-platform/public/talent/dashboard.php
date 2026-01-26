<?php
// public/talent/dashboard.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_TALENT);

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
                    <span class="text-muted"><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="card stat-card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Active Applications</h6>
                                    <h3 class="mb-0" id="activeApplications">0</h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-file-alt fa-3x opacity-50"></i>
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
                                    <h6 class="mb-1 text-white-50">Active Contracts</h6>
                                    <h3 class="mb-0" id="activeContracts">0</h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-file-contract fa-3x opacity-50"></i>
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
                                    <h6 class="mb-1 text-white-50">Total Earnings</h6>
                                    <h3 class="mb-0" id="totalEarnings">Rp 0</h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-money-bill-wave fa-3x opacity-50"></i>
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
                                    <h6 class="mb-1 text-white-50">Profile Views</h6>
                                    <h3 class="mb-0" id="profileViews">0</h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-eye fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row g-4">
                <!-- Recent Jobs -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-briefcase text-primary"></i> Recommended Jobs
                                </h5>
                                <a href="<?php echo SITE_URL; ?>/public/talent/jobs.php" class="btn btn-sm btn-outline-primary">
                                    View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="recentJobs">
                                <div class="text-center py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-clock text-primary"></i> Recent Activity
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="recentActivity">
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
            
            <!-- Application Status -->
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-list text-primary"></i> My Applications
                                </h5>
                                <a href="<?php echo SITE_URL; ?>/public/talent/applications.php" class="btn btn-sm btn-outline-primary">
                                    View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="myApplications">
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