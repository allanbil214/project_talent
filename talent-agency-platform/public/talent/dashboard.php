<?php
// public/talent/dashboard.php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_TALENT);

$db = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Talent.php';
require_once __DIR__ . '/../../classes/Job.php';
require_once __DIR__ . '/../../classes/Application.php';

$database = new Database($db);
$talent_model = new Talent($database);
$job_model = new Job($database);
$application_model = new Application($database);

$user_id = getCurrentUserId();
$talent = $talent_model->getByUserId($user_id);

if (!$talent) {
    redirect(SITE_URL . '/public/talent/profile.php');
}

$stats = $talent_model->getStats($talent['id']);

// Get recent jobs
$recent_jobs = $job_model->getActive(1, 5);

// Get recent applications
$recent_applications = $application_model->getByTalent($talent['id'], 1, 5);

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
                    <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($talent['full_name']); ?>!</p>
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
                                    <h3 class="mb-0"><?php echo $stats['total_applications']; ?></h3>
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
                                    <h3 class="mb-0"><?php echo $stats['active_contracts']; ?></h3>
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
                                    <h3 class="mb-0"><?php echo formatCurrency($stats['total_earnings']); ?></h3>
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
                                    <h6 class="mb-1 text-white-50">Completed Jobs</h6>
                                    <h3 class="mb-0"><?php echo $stats['completed_jobs']; ?></h3>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-check-circle fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Profile Completion -->
            <?php
            $completion = 0;
            if (!empty($talent['full_name'])) $completion += 20;
            if (!empty($talent['bio'])) $completion += 20;
            if (!empty($talent['profile_photo_url'])) $completion += 15;
            if (!empty($talent['resume_url'])) $completion += 15;
            if (!empty($talent['hourly_rate'])) $completion += 15;
            
            $skills = $talent_model->getSkills($talent['id']);
            if (count($skills) >= 3) $completion += 15;
            
            if ($completion < 100):
            ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">
                                <i class="fas fa-chart-line text-primary"></i> Complete Your Profile
                            </h5>
                            <div class="progress mb-3" style="height: 25px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $completion; ?>%;" 
                                     aria-valuenow="<?php echo $completion; ?>" 
                                     aria-valuemin="0" aria-valuemax="100">
                                    <?php echo $completion; ?>%
                                </div>
                            </div>
                            <p class="text-muted mb-2">A complete profile gets 5x more visibility!</p>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if (empty($talent['bio'])): ?>
                                    <a href="<?php echo SITE_URL; ?>/public/talent/profile.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-plus"></i> Add Bio
                                    </a>
                                <?php endif; ?>
                                <?php if (empty($talent['profile_photo_url'])): ?>
                                    <a href="<?php echo SITE_URL; ?>/public/talent/profile.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-plus"></i> Upload Photo
                                    </a>
                                <?php endif; ?>
                                <?php if (empty($talent['resume_url'])): ?>
                                    <a href="<?php echo SITE_URL; ?>/public/talent/profile.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-plus"></i> Upload Resume
                                    </a>
                                <?php endif; ?>
                                <?php if (count($skills) < 3): ?>
                                    <a href="<?php echo SITE_URL; ?>/public/talent/skills.php" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-plus"></i> Add Skills
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
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
                            <?php if (!empty($recent_jobs['data'])): ?>
                                <?php foreach ($recent_jobs['data'] as $job): ?>
                                    <div class="job-card">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1">
                                                    <a href="<?php echo SITE_URL; ?>/public/talent/job-detail.php?id=<?php echo $job['id']; ?>" 
                                                       class="text-decoration-none text-dark">
                                                        <?php echo htmlspecialchars($job['title']); ?>
                                                    </a>
                                                </h6>
                                                <p class="text-muted mb-2 small">
                                                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($job['company_name']); ?>
                                                </p>
                                            </div>
                                            <small class="text-muted"><?php echo timeAgo($job['created_at']); ?></small>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-info text-white"><?php echo $job['job_type']; ?></span>
                                                <span class="badge bg-secondary"><?php echo $job['location_type']; ?></span>
                                                <?php if ($job['salary_max']): ?>
                                                    <span class="badge bg-success"><?php echo formatCurrency($job['salary_max']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <a href="<?php echo SITE_URL; ?>/public/talent/job-detail.php?id=<?php echo $job['id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-arrow-right"></i>
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-briefcase"></i>
                                    <p>No jobs available at the moment</p>
                                    <p class="small text-muted">Check back later for new opportunities</p>
                                </div>
                            <?php endif; ?>
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
                            <?php if (!empty($recent_applications['data'])): ?>
                                <div class="activity-timeline">
                                    <?php foreach ($recent_applications['data'] as $app): ?>
                                        <div class="activity-item">
                                            <div class="mb-2">
                                                <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>
                                            </div>
                                            <small class="text-muted d-block mb-1">
                                                Applied <?php echo timeAgo($app['applied_at']); ?>
                                            </small>
                                            <span class="badge <?php echo getStatusBadgeClass($app['status']); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-clock"></i>
                                    <p>No recent activity</p>
                                    <a href="<?php echo SITE_URL; ?>/public/talent/jobs.php" class="btn btn-sm btn-primary mt-2">
                                        Browse Jobs
                                    </a>
                                </div>
                            <?php endif; ?>
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
                            <?php if (!empty($recent_applications['data'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Job Title</th>
                                                <th>Company</th>
                                                <th>Applied Date</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_applications['data'] as $app): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?php echo $app['job_type']; ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($app['company_name']); ?></td>
                                                    <td><?php echo formatDate($app['applied_at']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo getStatusBadgeClass($app['status']); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="<?php echo SITE_URL; ?>/public/talent/job-detail.php?id=<?php echo $app['job_id']; ?>" 
                                                           class="btn btn-sm btn-outline-primary">
                                                            View Job
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-file-alt"></i>
                                    <p>No applications yet</p>
                                    <p class="small text-muted">Start applying to jobs to see them here</p>
                                    <a href="<?php echo SITE_URL; ?>/public/talent/jobs.php" class="btn btn-primary mt-2">
                                        <i class="fas fa-search"></i> Browse Jobs
                                    </a>
                                </div>
                            <?php endif; ?>
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