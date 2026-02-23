<?php
// public/employer/job-detail.php
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
    redirect(SITE_URL . '/public/employer/profile.php');
}

$job_id = (int)($_GET['id'] ?? 0);
if (!$job_id) {
    redirect(SITE_URL . '/public/employer/jobs.php');
}

$job = $job_model->getById($job_id);

if (!$job || $job['employer_id'] != $employer['id']) {
    redirect(SITE_URL . '/public/employer/jobs.php');
}

$applications = $job_model->getApplications($job_id);

// Group applications by status
$apps_by_status = [];
foreach ($applications as $app) {
    $apps_by_status[$app['status']][] = $app;
}

$page_title = htmlspecialchars($job['title']) . ' - ' . SITE_NAME;
$body_class = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <!-- Header -->
            <div class="page-header d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-3">
                    <a href="<?php echo SITE_URL; ?>/public/employer/jobs.php"
                       class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div>
                        <h2 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h2>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <?php echo getJobStatusBadge($job['status']); ?>
                            <span class="text-muted small">
                                <i class="fas fa-clock me-1"></i>Posted <?php echo timeAgo($job['created_at']); ?>
                            </span>
                            <span class="text-muted small">
                                <i class="fas fa-users me-1"></i><?php echo count($applications); ?> applicants
                            </span>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <?php if (!in_array($job['status'], [JOB_STATUS_FILLED, JOB_STATUS_CLOSED])): ?>
                        <a href="<?php echo SITE_URL; ?>/public/employer/edit-job.php?id=<?php echo $job_id; ?>"
                           class="btn btn-outline-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <?php if ($job['status'] === JOB_STATUS_ACTIVE): ?>
                            <button type="button" class="btn btn-outline-success mark-filled-btn"
                                    data-job-id="<?php echo $job_id; ?>">
                                <i class="fas fa-check-circle"></i> Mark Filled
                            </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-danger close-job-btn"
                                data-job-id="<?php echo $job_id; ?>"
                                data-job-title="<?php echo htmlspecialchars($job['title']); ?>">
                            <i class="fas fa-times"></i> Close
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="row">
                <!-- Job Info -->
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Job Details</h5>

                            <dl class="row mb-0">
                                <dt class="col-5 text-muted small">Type</dt>
                                <dd class="col-7 small text-capitalize"><?php echo $job['job_type']; ?></dd>

                                <dt class="col-5 text-muted small">Location</dt>
                                <dd class="col-7 small text-capitalize">
                                    <?php echo $job['location_type']; ?>
                                    <?php if ($job['location_address']): ?>
                                        <div class="text-muted"><?php echo htmlspecialchars($job['location_address']); ?></div>
                                    <?php endif; ?>
                                </dd>

                                <dt class="col-5 text-muted small">Salary</dt>
                                <dd class="col-7 small">
                                    <?php if ($job['salary_min'] || $job['salary_max']): ?>
                                        <?php echo formatSalaryRange($job['salary_min'], $job['salary_max'], $job['salary_type']); ?>
                                    <?php else: ?>
                                        Negotiable
                                    <?php endif; ?>
                                </dd>

                                <dt class="col-5 text-muted small">Experience</dt>
                                <dd class="col-7 small">
                                    <?php echo $job['experience_required'] > 0
                                        ? $job['experience_required'] . '+ years'
                                        : 'Any level'; ?>
                                </dd>

                                <dt class="col-5 text-muted small">Deadline</dt>
                                <dd class="col-7 small">
                                    <?php if ($job['deadline']): ?>
                                        <?php echo formatDate($job['deadline']); ?>
                                        <?php if (strtotime($job['deadline']) < time()): ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        Open
                                    <?php endif; ?>
                                </dd>
                            </dl>
                        </div>
                    </div>

                    <!-- Skills -->
                    <?php if (!empty($job['skills'])): ?>
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Required Skills</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($job['skills'] as $skill): ?>
                                    <span class="badge <?php echo $skill['required'] ? 'bg-primary' : 'bg-light text-dark border'; ?>">
                                        <?php echo htmlspecialchars($skill['name']); ?>
                                        <?php if (!$skill['required']): ?><small class="ms-1 opacity-75">(nice)</small><?php endif; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Description -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Description</h5>
                            <div class="text-muted small" style="white-space:pre-wrap;line-height:1.6">
                                <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Applications -->
                <div class="col-lg-8 mb-4" id="applications">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-users text-primary"></i>
                                    Applications (<?php echo count($applications); ?>)
                                </h5>
                                <!-- Filter tabs -->
                                <div class="btn-group btn-group-sm" id="appFilterTabs">
                                    <button class="btn btn-outline-secondary active" data-filter="all">All</button>
                                    <button class="btn btn-outline-warning" data-filter="pending">
                                        Pending
                                        <?php if (!empty($apps_by_status['pending'])): ?>
                                            <span class="badge bg-warning text-dark"><?php echo count($apps_by_status['pending']); ?></span>
                                        <?php endif; ?>
                                    </button>
                                    <button class="btn btn-outline-info" data-filter="shortlisted">Shortlisted</button>
                                    <button class="btn btn-outline-success" data-filter="accepted">Accepted</button>
                                    <button class="btn btn-outline-danger" data-filter="rejected">Rejected</button>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/public/employer/applications.php?job_id=<?php echo $job_id; ?>"
                                class="btn btn-sm btn-primary">
                                    <i class="fas fa-list-ul me-1"></i> Full Review
                                </a>
                            </div>
                        </div>

                        <div class="card-body p-0">
                            <?php if (!empty($applications)): ?>
                                <div id="applicationsContainer">
                                    <?php foreach ($applications as $app): ?>
                                        <div class="application-row border-bottom p-3" data-status="<?php echo $app['status']; ?>">
                                            <div class="d-flex gap-3">
                                                <img src="<?php echo getAvatarUrl($app['profile_photo_url']); ?>"
                                                     class="avatar-md rounded-circle flex-shrink-0" alt="">

                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-0 fw-semibold">
                                                                <?php echo htmlspecialchars($app['full_name']); ?>
                                                            </h6>
                                                            <div class="text-muted small">
                                                                <?php echo htmlspecialchars($app['city'] ?? ''); ?>
                                                                <?php if ($app['years_experience']): ?>
                                                                    &bull; <?php echo $app['years_experience']; ?> yrs exp
                                                                <?php endif; ?>
                                                                <?php if ($app['rating_average']): ?>
                                                                    &bull; <i class="fas fa-star text-warning"></i>
                                                                    <?php echo number_format($app['rating_average'], 1); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <?php echo getApplicationStatusBadge($app['status']); ?>
                                                            <?php if ($app['agency_recommended']): ?>
                                                                <span class="badge bg-purple" title="Agency Recommended">
                                                                    <i class="fas fa-star"></i> Agency Pick
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <?php if ($app['proposed_rate']): ?>
                                                        <div class="mt-1 small">
                                                            <span class="text-muted">Proposed rate:</span>
                                                            <strong><?php echo formatCurrency($app['proposed_rate']); ?></strong>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($app['cover_letter']): ?>
                                                        <div class="mt-2 small text-muted cover-letter-preview">
                                                            <?php echo nl2br(htmlspecialchars(substr($app['cover_letter'], 0, 200))); ?>
                                                            <?php if (strlen($app['cover_letter']) > 200): ?>
                                                                <a href="#" class="read-more-link" data-full="<?php echo htmlspecialchars($app['cover_letter']); ?>">
                                                                    ... read more
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="mt-2 d-flex gap-2 flex-wrap">
                                                        <span class="text-muted small">
                                                            Applied <?php echo timeAgo($app['applied_at']); ?>
                                                        </span>

                                                        <?php if ($app['status'] === APPLICATION_STATUS_PENDING || $app['status'] === APPLICATION_STATUS_REVIEWED): ?>
                                                            <button type="button"
                                                                    class="btn btn-sm btn-outline-info update-app-btn"
                                                                    data-app-id="<?php echo $app['id']; ?>"
                                                                    data-status="shortlisted">
                                                                <i class="fas fa-star"></i> Shortlist
                                                            </button>
                                                            <button type="button"
                                                                    class="btn btn-sm btn-outline-success update-app-btn"
                                                                    data-app-id="<?php echo $app['id']; ?>"
                                                                    data-status="accepted">
                                                                <i class="fas fa-check"></i> Accept
                                                            </button>
                                                            <button type="button"
                                                                    class="btn btn-sm btn-outline-danger update-app-btn"
                                                                    data-app-id="<?php echo $app['id']; ?>"
                                                                    data-status="rejected">
                                                                <i class="fas fa-times"></i> Reject
                                                            </button>
                                                        <?php elseif ($app['status'] === APPLICATION_STATUS_SHORTLISTED): ?>
                                                            <button type="button"
                                                                    class="btn btn-sm btn-success update-app-btn"
                                                                    data-app-id="<?php echo $app['id']; ?>"
                                                                    data-status="accepted">
                                                                <i class="fas fa-check"></i> Accept
                                                            </button>
                                                            <button type="button"
                                                                    class="btn btn-sm btn-outline-danger update-app-btn"
                                                                    data-app-id="<?php echo $app['id']; ?>"
                                                                    data-status="rejected">
                                                                <i class="fas fa-times"></i> Reject
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state py-5 text-center">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No applications yet.</p>
                                    <?php if ($job['status'] === JOB_STATUS_ACTIVE): ?>
                                        <p class="text-muted small">Your job is live. Talents will apply soon!</p>
                                    <?php elseif ($job['status'] === JOB_STATUS_PENDING): ?>
                                        <p class="text-muted small">Your job is pending approval before talents can apply.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Cover Letter Modal -->
<div class="modal fade" id="coverLetterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cover Letter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="coverLetterContent" style="white-space:pre-wrap"></p>
            </div>
        </div>
    </div>
</div>

<?php
$additional_js = ['employer.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
