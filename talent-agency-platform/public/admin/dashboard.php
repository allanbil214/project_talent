<?php
// public/admin/dashboard.php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAdmin();

$page_title = 'Admin Dashboard - ' . SITE_NAME;
$body_class = 'dashboard-page admin-dashboard';
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
                    <h2 class="mb-0">Admin Dashboard</h2>
                    <p class="text-muted mb-0">System overview and analytics</p>
                </div>
                <div>
                    <span class="badge bg-success">System Online</span>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card bg-gradient-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Total Users</h6>
                                    <h3 class="mb-0" id="totalUsers">0</h3>
                                    <small>+0 this month</small>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-users fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card bg-gradient-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Active Jobs</h6>
                                    <h3 class="mb-0" id="totalJobs">0</h3>
                                    <small>+0 this week</small>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-briefcase fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card bg-gradient-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Total Placements</h6>
                                    <h3 class="mb-0" id="totalPlacements">0</h3>
                                    <small>+0 this month</small>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-handshake fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stat-card bg-gradient-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1 text-white-50">Commission Revenue</h6>
                                    <h3 class="mb-0" id="totalRevenue">Rp 0</h3>
                                    <small>This month</small>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-dollar-sign fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-line text-primary"></i> Revenue Trend
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="80"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-chart-pie text-primary"></i> User Distribution
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="userDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tables Row -->
            <div class="row g-4">
                <!-- Pending Approvals -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-clock text-warning"></i> Pending Job Approvals
                                </h5>
                                <a href="<?php echo SITE_URL; ?>/public/admin/jobs.php" class="btn btn-sm btn-outline-primary">
                                    View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="pendingJobs">
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
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-history text-primary"></i> Recent Activity
                                </h5>
                                <a href="<?php echo SITE_URL; ?>/public/admin/logs.php" class="btn btn-sm btn-outline-primary">
                                    View All
                                </a>
                            </div>
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
            
            <!-- Latest Contracts -->
            <div class="row g-4 mt-2">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-file-contract text-primary"></i> Recent Contracts
                                </h5>
                                <a href="<?php echo SITE_URL; ?>/public/admin/contracts.php" class="btn btn-sm btn-outline-primary">
                                    View All
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div id="recentContracts">
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
$additional_js = ['dashboard.js', 'charts.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>