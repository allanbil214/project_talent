<?php
// public/employer/jobs.php
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

$page = max(1, (int)($_GET['page'] ?? 1));
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';

$result = $job_model->getByEmployer($employer['id'], $page, JOBS_PER_PAGE, [
    'status' => $status_filter,
    'search' => $search_filter,
]);

$jobs = $result['data'];
$pagination = $result['pagination'];
$stats = $employer_model->getStats($employer['id']);

$page_title = 'My Jobs - ' . SITE_NAME;
$body_class = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-briefcase"></i> My Jobs</h2>
                    <p class="text-muted mb-0">Manage all your job postings</p>
                </div>
                <a href="<?php echo SITE_URL; ?>/public/employer/post-job.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Post a Job
                </a>
            </div>

            <!-- Quick Stats -->
            <div class="row g-2 mb-4">
                <?php
                $stat_tabs = [
                    '' => ['label' => 'All Jobs', 'count' => $stats['total_jobs'], 'color' => 'secondary'],
                    'active' => ['label' => 'Active', 'count' => $stats['active_jobs'], 'color' => 'success'],
                    'pending_approval' => ['label' => 'Pending', 'count' => $stats['pending_jobs'], 'color' => 'warning'],
                ];
                foreach ($stat_tabs as $val => $tab):
                ?>
                    <div class="col-auto">
                        <a href="?status=<?php echo $val; ?>"
                           class="btn btn-sm <?php echo $status_filter === $val ? 'btn-' . $tab['color'] : 'btn-outline-' . $tab['color']; ?>">
                            <?php echo $tab['label']; ?>
                            <span class="badge bg-white text-dark ms-1"><?php echo $tab['count']; ?></span>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Search & Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body py-3">
                    <form method="GET" class="row g-2 align-items-end">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <div class="col-md-8">
                            <input type="text" class="form-control" name="search"
                                   value="<?php echo htmlspecialchars($search_filter); ?>"
                                   placeholder="Search by job title...">
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                        <?php if ($search_filter): ?>
                            <div class="col-md-2">
                                <a href="?status=<?php echo htmlspecialchars($status_filter); ?>"
                                   class="btn btn-outline-secondary w-100">Clear</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- Jobs Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <?php if (!empty($jobs)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Job Title</th>
                                        <th>Type</th>
                                        <th>Location</th>
                                        <th>Salary</th>
                                        <th>Applications</th>
                                        <th>Status</th>
                                        <th>Deadline</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jobs as $job): ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo SITE_URL; ?>/public/employer/job-detail.php?id=<?php echo $job['id']; ?>"
                                                   class="fw-semibold text-dark text-decoration-none">
                                                    <?php echo htmlspecialchars($job['title']); ?>
                                                </a>
                                                <div class="text-muted small">
                                                    Posted <?php echo timeAgo($job['created_at']); ?>
                                                </div>
                                                <?php if (!empty($job['skills'])): ?>
                                                    <div class="mt-1">
                                                        <?php foreach (array_slice($job['skills'], 0, 3) as $skill): ?>
                                                            <span class="badge bg-light text-dark border small"><?php echo htmlspecialchars($skill['name']); ?></span>
                                                        <?php endforeach; ?>
                                                        <?php if (count($job['skills']) > 3): ?>
                                                            <span class="text-muted small">+<?php echo count($job['skills']) - 3; ?> more</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border">
                                                    <?php echo ucfirst($job['job_type']); ?>
                                                </span>
                                            </td>
                                            <td class="text-capitalize">
                                                <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                                <?php echo $job['location_type']; ?>
                                            </td>
                                            <td class="small">
                                                <?php if ($job['salary_min'] || $job['salary_max']): ?>
                                                    <?php echo formatSalaryRange($job['salary_min'], $job['salary_max'], $job['salary_type']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Negotiable</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="<?php echo SITE_URL; ?>/public/employer/job-detail.php?id=<?php echo $job['id']; ?>#applications"
                                                   class="text-decoration-none">
                                                    <span class="fw-semibold"><?php echo $job['application_count']; ?></span>
                                                    <span class="text-muted small"> applicants</span>
                                                </a>
                                            </td>
                                            <td><?php echo getJobStatusBadge($job['status']); ?></td>
                                            <td class="small text-muted">
                                                <?php echo $job['deadline'] ? formatDate($job['deadline']) : '—'; ?>
                                                <?php if ($job['deadline'] && strtotime($job['deadline']) < time()): ?>
                                                    <span class="badge bg-danger ms-1">Expired</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="<?php echo SITE_URL; ?>/public/employer/job-detail.php?id=<?php echo $job['id']; ?>"
                                                       class="btn btn-outline-secondary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (!in_array($job['status'], [JOB_STATUS_FILLED, JOB_STATUS_CLOSED])): ?>
                                                        <a href="<?php echo SITE_URL; ?>/public/employer/edit-job.php?id=<?php echo $job['id']; ?>"
                                                           class="btn btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button"
                                                                class="btn btn-outline-danger close-job-btn"
                                                                data-job-id="<?php echo $job['id']; ?>"
                                                                data-job-title="<?php echo htmlspecialchars($job['title']); ?>"
                                                                title="Close">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($pagination['total_pages'] > 1): ?>
                            <div class="d-flex justify-content-center py-3">
                                <?php echo renderPagination($pagination, '?status=' . urlencode($status_filter) . '&search=' . urlencode($search_filter) . '&page='); ?>
                            </div>
                        <?php endif; ?>

                    <?php else: ?>
                        <div class="empty-state py-5 text-center">
                            <i class="fas fa-briefcase fa-3x text-muted mb-3"></i>
                            <?php if ($search_filter || $status_filter): ?>
                                <p class="text-muted">No jobs found matching your filters.</p>
                                <a href="?" class="btn btn-outline-primary btn-sm">Clear filters</a>
                            <?php else: ?>
                                <p class="text-muted">You haven't posted any jobs yet.</p>
                                <a href="<?php echo SITE_URL; ?>/public/employer/post-job.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Post Your First Job
                                </a>
                            <?php endif; ?>
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
