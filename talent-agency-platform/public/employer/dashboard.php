<?php
// public/employer/dashboard.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_EMPLOYER);

$db_connection = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Employer.php';
require_once __DIR__ . '/../../classes/Job.php';

$database = new Database($db_connection);
$employer_model = new Employer($database);
$job_model = new Job($database);

$user_id = getCurrentUserId();
$employer = $employer_model->getByUserId($user_id);

if (!$employer) {
    $employer_id = $employer_model->create($user_id, ['company_name' => $_SESSION['name'] ?? 'My Company']);
    $employer = $employer_model->getById($employer_id);
}

$stats = $employer_model->getStats($employer['id']);
$recent_applications = $employer_model->getRecentApplications($employer['id'], 8);
$active_contracts = $employer_model->getActiveContracts($employer['id'], 5);
$recent_jobs_result = $job_model->getByEmployer($employer['id'], 1, 5);
$recent_jobs = $recent_jobs_result['data'];

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
            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-tachometer-alt"></i> Dashboard</h2>
                    <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($employer['company_name']); ?></p>
                </div>
                <a href="<?php echo SITE_URL; ?>/public/employer/post-job.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Post a Job
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted small mb-1">Active Jobs</p>
                                    <h3 class="mb-0 fw-bold"><?php echo $stats['active_jobs']; ?></h3>
                                </div>
                                <div class="stat-icon bg-primary-soft">
                                    <i class="fas fa-briefcase text-primary"></i>
                                </div>
                            </div>
                            <?php if ($stats['pending_jobs'] > 0): ?>
                                <small class="text-warning">
                                    <i class="fas fa-clock"></i> <?php echo $stats['pending_jobs']; ?> pending approval
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted small mb-1">Total Applications</p>
                                    <h3 class="mb-0 fw-bold"><?php echo $stats['total_applications']; ?></h3>
                                </div>
                                <div class="stat-icon bg-success-soft">
                                    <i class="fas fa-file-alt text-success"></i>
                                </div>
                            </div>
                            <?php if ($stats['pending_applications'] > 0): ?>
                                <small class="text-danger">
                                    <i class="fas fa-exclamation-circle"></i> <?php echo $stats['pending_applications']; ?> need review
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted small mb-1">Active Contracts</p>
                                    <h3 class="mb-0 fw-bold"><?php echo $stats['active_contracts']; ?></h3>
                                </div>
                                <div class="stat-icon bg-info-soft">
                                    <i class="fas fa-handshake text-info"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="card stat-card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <p class="text-muted small mb-1">Total Spent</p>
                                    <h3 class="mb-0 fw-bold"><?php echo formatCurrency($stats['total_spent']); ?></h3>
                                </div>
                                <div class="stat-icon bg-warning-soft">
                                    <i class="fas fa-money-bill-wave text-warning"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Completion Warning -->
            <?php if (!$employer['verified']): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Complete your company profile</strong> to build trust with talents.
                <a href="<?php echo SITE_URL; ?>/public/employer/profile.php" class="alert-link">Update profile →</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Recent Applications -->
                <div class="col-lg-7 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-users text-primary"></i> Recent Applications</h5>
                            <a href="<?php echo SITE_URL; ?>/public/employer/jobs.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($recent_applications)): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Talent</th>
                                                <th>Job</th>
                                                <th>Status</th>
                                                <th>Applied</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_applications as $app): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <img src="<?php echo getAvatarUrl($app['profile_photo_url']); ?>"
                                                                 class="avatar-sm rounded-circle" alt="">
                                                            <div>
                                                                <div class="fw-semibold small"><?php echo htmlspecialchars($app['talent_name']); ?></div>
                                                                <div class="text-muted" style="font-size:0.75rem"><?php echo htmlspecialchars($app['city'] ?? ''); ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="small"><?php echo htmlspecialchars($app['job_title']); ?></td>
                                                    <td><?php echo getApplicationStatusBadge($app['status']); ?></td>
                                                    <td class="text-muted small"><?php echo timeAgo($app['applied_at']); ?></td>
                                                    <td>
                                                        <a href="<?php echo SITE_URL; ?>/public/employer/job-detail.php?id=<?php echo $app['job_id']; ?>"
                                                           class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="empty-state py-5">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No applications yet.</p>
                                    <a href="<?php echo SITE_URL; ?>/public/employer/post-job.php" class="btn btn-primary btn-sm">
                                        Post your first job
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right column -->
                <div class="col-lg-5 mb-4">
                    <!-- My Jobs -->
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fas fa-briefcase text-success"></i> My Jobs</h5>
                            <a href="<?php echo SITE_URL; ?>/public/employer/jobs.php" class="btn btn-sm btn-outline-success">All Jobs</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (!empty($recent_jobs)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recent_jobs as $job): ?>
                                        <li class="list-group-item px-3 py-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <a href="<?php echo SITE_URL; ?>/public/employer/job-detail.php?id=<?php echo $job['id']; ?>"
                                                       class="fw-semibold text-dark text-decoration-none">
                                                        <?php echo htmlspecialchars($job['title']); ?>
                                                    </a>
                                                    <div class="text-muted small mt-1">
                                                        <span class="badge bg-light text-dark border"><?php echo $job['job_type']; ?></span>
                                                        <span class="ms-1"><?php echo $job['application_count']; ?> applicants</span>
                                                    </div>
                                                </div>
                                                <?php echo getJobStatusBadge($job['status']); ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="empty-state py-4">
                                    <p class="text-muted small mb-2">No jobs posted yet.</p>
                                    <a href="<?php echo SITE_URL; ?>/public/employer/post-job.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus"></i> Post a Job
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Active Contracts -->
                    <?php if (!empty($active_contracts)): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-handshake text-info"></i> Active Contracts</h5>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php foreach ($active_contracts as $contract): ?>
                                    <li class="list-group-item px-3 py-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?php echo getAvatarUrl($contract['profile_photo_url']); ?>"
                                                 class="avatar-sm rounded-circle" alt="">
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold small"><?php echo htmlspecialchars($contract['talent_name']); ?></div>
                                                <div class="text-muted" style="font-size:0.75rem">
                                                    <?php echo htmlspecialchars($contract['job_title']); ?> &bull;
                                                    <?php echo formatCurrency($contract['rate']); ?>/<?php echo $contract['rate_type']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
$additional_js = ['employer.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
