<?php
// public/employer/applications.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_EMPLOYER);

$db_connection = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Employer.php';
require_once __DIR__ . '/../../classes/Job.php';
require_once __DIR__ . '/../../classes/Application.php';
require_once __DIR__ . '/../../classes/Contract.php';

$database       = new Database($db_connection);
$employer_model = new Employer($database);
$job_model      = new Job($database);
$app_model      = new Application($database);
$contract_model = new Contract($database);

$user_id  = getCurrentUserId();
$employer = $employer_model->getByUserId($user_id);

if (!$employer) {
    redirect(SITE_URL . '/public/employer/profile.php');
}

$job_id = (int)($_GET['job_id'] ?? 0);
if (!$job_id) {
    redirect(SITE_URL . '/public/employer/jobs.php');
}

$job = $job_model->getById($job_id);
if (!$job || $job['employer_id'] != $employer['id']) {
    redirect(SITE_URL . '/public/employer/jobs.php');
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid request.';
        redirect("?job_id=$job_id");
    }

    $action = $_POST['action'] ?? '';
    $app_id = (int)($_POST['app_id'] ?? 0);

    try {
        if ($action === 'update_status') {
            $app_model->updateStatus($app_id, $_POST['status'], $employer['id']);
            $_SESSION['flash_success'] = 'Application status updated.';
        } elseif ($action === 'create_contract') {
            // Validate inputs
            $rate = (float)($_POST['rate'] ?? 0);
            $rate_type = $_POST['rate_type'] ?? '';
            $start_date = $_POST['start_date'] ?? '';

            if (!$rate || !$rate_type || !$start_date) {
                throw new Exception('Rate, rate type, and start date are required.');
            }

            $app = $app_model->getById($app_id);
            if (!$app) throw new Exception('Application not found.');

            $total_amount = !empty($_POST['total_amount']) ? (float)$_POST['total_amount'] : null;

            $contract_model->create([
                'job_id'         => $job_id,
                'talent_id'      => $app['talent_id'],
                'employer_id'    => $employer['id'],
                'application_id' => $app_id,
                'start_date'     => $start_date,
                'end_date'       => $_POST['end_date'] ?: null,
                'rate'           => $rate,
                'rate_type'      => $rate_type,
                'currency'       => $job['currency'],
                'total_amount'   => $total_amount,
            ]);

            $_SESSION['flash_success'] = 'Contract created successfully!';
            redirect(SITE_URL . '/public/employer/contracts.php');
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }

    redirect("?job_id=$job_id");
}

$status_filter = $_GET['status'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));
$result        = $app_model->getByJob($job_id, $page, ITEMS_PER_PAGE, ['status' => $status_filter]);
$applications  = $result['data'];
$pagination    = $result['pagination'];
$counts        = $app_model->getStatusCounts($job_id);
$job_skills    = $job_model->getSkills($job_id);

$status_labels = [
    APP_STATUS_PENDING     => ['label' => 'Pending',     'class' => 'warning'],
    APP_STATUS_REVIEWED    => ['label' => 'Reviewed',    'class' => 'info'],
    APP_STATUS_SHORTLISTED => ['label' => 'Shortlisted', 'class' => 'primary'],
    APP_STATUS_REJECTED    => ['label' => 'Rejected',    'class' => 'danger'],
    APP_STATUS_ACCEPTED    => ['label' => 'Accepted',    'class' => 'success'],
];

$required_skill_ids = array_column(array_filter($job_skills, fn($s) => $s['required']), 'skill_id');

$page_title    = 'Applications - ' . htmlspecialchars($job['title']) . ' - ' . SITE_NAME;
$body_class    = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="page-header mb-3">
                <a href="<?php echo SITE_URL; ?>/public/employer/job-detail.php?id=<?php echo $job_id; ?>"
                   class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="fas fa-arrow-left"></i> Back to Job
                </a>
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h2><i class="fas fa-users"></i> Applicants</h2>
                        <p class="text-muted mb-0">
                            <?php echo htmlspecialchars($job['title']); ?> &mdash;
                            <?php echo $counts['total']; ?> total application<?php echo $counts['total'] !== 1 ? 's' : ''; ?>
                        </p>
                    </div>
                </div>
            </div>

            <?php flashMessage(); ?>

            <!-- Status Tabs -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-1">
                        <a href="?job_id=<?php echo $job_id; ?>&status="
                           class="btn btn-sm <?php echo $status_filter === '' ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                            All (<?php echo $counts['total']; ?>)
                        </a>
                        <?php foreach ($status_labels as $key => $meta): ?>
                        <a href="?job_id=<?php echo $job_id; ?>&status=<?php echo $key; ?>"
                           class="btn btn-sm <?php echo $status_filter === $key ? 'btn-' . $meta['class'] : 'btn-outline-secondary'; ?>">
                            <?php echo $meta['label']; ?> (<?php echo $counts[$key]; ?>)
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Required skills legend -->
            <?php if (!empty($job_skills)): ?>
            <div class="mb-3">
                <small class="text-muted">Required skills: </small>
                <?php foreach ($job_skills as $sk): ?>
                    <span class="badge <?php echo $sk['required'] ? 'bg-primary' : 'bg-secondary'; ?> me-1">
                        <?php echo htmlspecialchars($sk['name']); ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Applicant Cards -->
            <?php if (empty($applications)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No applicants yet</h5>
                    <p class="text-muted">Applications will appear here once talents apply.</p>
                </div>
            </div>
            <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($applications as $app): ?>
                <?php $meta = $status_labels[$app['status']] ?? ['label' => ucfirst($app['status']), 'class' => 'secondary']; ?>
                <div class="card border-0 shadow-sm <?php echo $app['agency_recommended'] ? 'border-start border-4 border-warning' : ''; ?>">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <!-- Avatar -->
                            <?php if ($app['profile_photo_url']): ?>
                                <img src="<?php echo getAvatarUrl($app['profile_photo_url']); ?>"
                                     alt="Photo" class="rounded-circle"
                                     style="width:56px;height:56px;object-fit:cover;flex-shrink:0;">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold"
                                     style="width:56px;height:56px;font-size:1.3rem;flex-shrink:0;">
                                    <?php echo strtoupper(substr($app['talent_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>

                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                    <div>
                                        <h6 class="mb-0">
                                            <?php echo htmlspecialchars($app['talent_name']); ?>
                                            <?php if ($app['agency_recommended']): ?>
                                                <span class="badge bg-warning text-dark ms-1">
                                                    <i class="fas fa-star me-1"></i>Recommended
                                                </span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($app['talent_email']); ?>
                                            <?php if ($app['city']): ?> &bull; <?php echo htmlspecialchars($app['city']); ?><?php endif; ?>
                                            <?php if ($app['years_experience']): ?> &bull; <?php echo $app['years_experience']; ?>y exp<?php endif; ?>
                                            <?php if ($app['rating_average']): ?>
                                                &bull; <i class="fas fa-star text-warning"></i> <?php echo number_format($app['rating_average'], 1); ?>
                                            <?php endif; ?>
                                        </small>

                                        <!-- Skills match -->
                                        <?php if (!empty($app['skills'])): ?>
                                        <div class="mt-1">
                                            <?php foreach ($app['skills'] as $sk): ?>
                                                <span class="badge <?php echo $sk['is_required_by_job'] ? 'bg-success' : 'bg-light text-dark'; ?> me-1 mb-1">
                                                    <?php echo htmlspecialchars($sk['name']); ?>
                                                    <small>(<?php echo $sk['proficiency_level']; ?>)</small>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>

                                        <?php if ($app['proposed_rate']): ?>
                                        <small class="text-muted d-block mt-1">
                                            <i class="fas fa-money-bill me-1"></i>
                                            Proposed: <?php echo $job['currency']; ?> <?php echo number_format($app['proposed_rate']); ?> / <?php echo $job['salary_type']; ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-end d-flex flex-column align-items-end gap-1">
                                        <span class="badge bg-<?php echo $meta['class']; ?> px-2 py-1"><?php echo $meta['label']; ?></span>
                                        <small class="text-muted"><?php echo timeAgo($app['applied_at']); ?></small>
                                        <?php if ($app['resume_url']): ?>
                                        <a href="<?php echo SITE_URL; ?>/public/serve-file.php?file=<?php echo urlencode($app['resume_url']); ?>"
                                           target="_blank" class="btn btn-xs btn-outline-secondary btn-sm py-0">
                                            <i class="fas fa-file-pdf me-1"></i>Resume
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Cover Letter (collapsible) -->
                                <?php if ($app['cover_letter']): ?>
                                <div class="mt-2">
                                    <a class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="collapse"
                                       href="#cl-<?php echo $app['id']; ?>">
                                        <i class="fas fa-chevron-down me-1"></i>Cover Letter
                                    </a>
                                    <div class="collapse mt-2" id="cl-<?php echo $app['id']; ?>">
                                        <div class="bg-light rounded p-3">
                                            <p class="mb-0 small" style="white-space:pre-wrap;">
                                                <?php echo htmlspecialchars($app['cover_letter']); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <!-- Actions -->
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <!-- Status Update -->
                                    <?php if ($app['status'] !== APP_STATUS_ACCEPTED): ?>
                                    <form method="POST" class="d-flex gap-1 align-items-center">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                        <select name="status" class="form-select form-select-sm" style="width:auto;">
                                            <?php foreach ($status_labels as $key => $m): ?>
                                                <?php if ($key !== APP_STATUS_ACCEPTED): ?>
                                                <option value="<?php echo $key; ?>" <?php echo $app['status'] === $key ? 'selected' : ''; ?>>
                                                    <?php echo $m['label']; ?>
                                                </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Update</button>
                                    </form>
                                    <?php endif; ?>

                                    <!-- Hire / Create Contract -->
                                    <?php if (in_array($app['status'], [APP_STATUS_SHORTLISTED, APP_STATUS_REVIEWED]) && $job['status'] !== JOB_STATUS_FILLED): ?>
                                    <button class="btn btn-sm btn-success"
                                            data-bs-toggle="modal"
                                            data-bs-target="#contractModal"
                                            data-app-id="<?php echo $app['id']; ?>"
                                            data-talent="<?php echo htmlspecialchars($app['talent_name']); ?>"
                                            data-rate="<?php echo $app['proposed_rate'] ?? $app['hourly_rate'] ?? ''; ?>"
                                            data-rate-type="<?php echo $job['salary_type']; ?>">
                                        <i class="fas fa-file-contract me-1"></i>Hire
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- Create Contract Modal -->
<div class="modal fade" id="contractModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="create_contract">
                <input type="hidden" name="app_id" id="modal_app_id">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-contract me-2"></i>Create Contract</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-3">Hiring: <strong id="modal_talent_name"></strong></p>

                    <div class="row g-3">
                        <div class="col-sm-7">
                            <label class="form-label">Rate (<?php echo htmlspecialchars($job['currency']); ?>) <span class="text-danger">*</span></label>
                            <input type="number" name="rate" id="modal_rate" class="form-control" min="0" step="0.01" required>
                        </div>
                        <div class="col-sm-5">
                            <label class="form-label">Rate Type <span class="text-danger">*</span></label>
                            <select name="rate_type" id="modal_rate_type" class="form-select" required>
                                <option value="hourly">Hourly</option>
                                <option value="monthly">Monthly</option>
                                <option value="project">Project</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" class="form-control" required
                                   min="<?php echo date('Y-m-d'); ?>"
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label">End Date <small class="text-muted">(optional)</small></label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Total Amount <small class="text-muted">(optional — for project contracts)</small></label>
                            <div class="input-group">
                                <span class="input-group-text"><?php echo htmlspecialchars($job['currency']); ?></span>
                                <input type="number" name="total_amount" class="form-control" min="0" step="0.01">
                            </div>
                            <div class="form-text">
                                Agency commission: <?php echo DEFAULT_COMMISSION_PERCENTAGE; ?>% will be calculated automatically.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-1"></i>Create Contract
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('contractModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('modal_app_id').value = btn.dataset.appId;
    document.getElementById('modal_talent_name').textContent = btn.dataset.talent;
    document.getElementById('modal_rate').value = btn.dataset.rate;
    const rt = btn.dataset.rateType;
    const sel = document.getElementById('modal_rate_type');
    for (let opt of sel.options) { opt.selected = opt.value === rt; }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
