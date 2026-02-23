<?php
// public/talent/apply.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_TALENT);

$db_connection = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Talent.php';
require_once __DIR__ . '/../../classes/Job.php';
require_once __DIR__ . '/../../classes/Application.php';

$database    = new Database($db_connection);
$talent_model = new Talent($database);
$job_model    = new Job($database);
$app_model    = new Application($database);

$user_id = getCurrentUserId();
$talent  = $talent_model->getByUserId($user_id);

if (!$talent) {
    redirect(SITE_URL . '/public/talent/profile.php');
}

$job_id = (int)($_GET['job_id'] ?? 0);
if (!$job_id) {
    redirect(SITE_URL . '/public/talent/dashboard.php');
}

$job = $job_model->getById($job_id);
if (!$job || $job['status'] !== JOB_STATUS_ACTIVE) {
    $_SESSION['flash_error'] = 'This job is no longer available.';
    redirect(SITE_URL . '/public/talent/dashboard.php');
}

// Already applied?
if ($app_model->hasApplied($talent['id'], $job_id)) {
    $_SESSION['flash_info'] = 'You have already applied to this job.';
    redirect(SITE_URL . '/public/talent/applications.php');
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $cover_letter  = trim($_POST['cover_letter'] ?? '');
        $proposed_rate = !empty($_POST['proposed_rate']) ? (float)$_POST['proposed_rate'] : null;

        if (empty($cover_letter)) {
            $errors[] = 'Cover letter is required.';
        } elseif (mb_strlen($cover_letter) < 50) {
            $errors[] = 'Cover letter must be at least 50 characters.';
        }

        if (empty($errors)) {
            try {
                $app_model->create($talent['id'], $job_id, [
                    'cover_letter'  => $cover_letter,
                    'proposed_rate' => $proposed_rate
                ]);
                $_SESSION['flash_success'] = 'Application submitted successfully!';
                redirect(SITE_URL . '/public/talent/applications.php');
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }
    }
}

// Get job skills
$job_skills = $job_model->getSkills($job_id);

$page_title    = 'Apply - ' . htmlspecialchars($job['title']) . ' - ' . SITE_NAME;
$body_class    = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4" style="max-width: 860px;">

            <div class="page-header mb-4">
                <a href="javascript:history.back()" class="btn btn-sm btn-outline-secondary mb-2">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
                <h2><i class="fas fa-paper-plane"></i> Apply for Job</h2>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0"><?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <!-- Job Summary Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-3">
                        <?php if ($job['company_logo_url']): ?>
                            <img src="<?php echo htmlspecialchars($job['company_logo_url']); ?>"
                                 alt="Logo" class="rounded" style="width:60px;height:60px;object-fit:cover;">
                        <?php else: ?>
                            <div class="rounded bg-light d-flex align-items-center justify-content-center"
                                 style="width:60px;height:60px;font-size:1.5rem;">
                                <i class="fas fa-building text-secondary"></i>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <h5 class="mb-1"><?php echo htmlspecialchars($job['title']); ?></h5>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($job['company_name']); ?></p>
                            <div class="d-flex flex-wrap gap-2">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-briefcase me-1"></i><?php echo ucfirst($job['job_type']); ?>
                                </span>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo ucfirst($job['location_type']); ?>
                                </span>
                                <?php if ($job['salary_min'] || $job['salary_max']): ?>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-money-bill me-1"></i>
                                    <?php
                                        echo $job['currency'] . ' ';
                                        if ($job['salary_min'] && $job['salary_max'])
                                            echo number_format($job['salary_min']) . ' - ' . number_format($job['salary_max']);
                                        elseif ($job['salary_min'])
                                            echo 'From ' . number_format($job['salary_min']);
                                        else
                                            echo 'Up to ' . number_format($job['salary_max']);
                                        echo ' / ' . $job['salary_type'];
                                    ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($job['deadline']): ?>
                                <span class="badge bg-<?php echo strtotime($job['deadline']) < time() ? 'danger' : 'warning text-dark'; ?>">
                                    <i class="fas fa-clock me-1"></i>Deadline: <?php echo date('d M Y', strtotime($job['deadline'])); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($job_skills)): ?>
                            <div class="mt-2">
                                <?php foreach ($job_skills as $skill): ?>
                                    <span class="badge <?php echo $skill['required'] ? 'bg-primary' : 'bg-secondary'; ?> me-1">
                                        <?php echo htmlspecialchars($skill['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Application Form -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Your Application</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Cover Letter <span class="text-danger">*</span></label>
                            <textarea name="cover_letter" rows="8" class="form-control"
                                      placeholder="Introduce yourself and explain why you're the perfect fit for this role..."
                                      required minlength="50"><?php echo htmlspecialchars($_POST['cover_letter'] ?? ''); ?></textarea>
                            <div class="form-text">Minimum 50 characters. Be specific about your experience and why you're a great match.</div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">
                                Proposed Rate
                                <small class="text-muted fw-normal">
                                    (<?php echo $job['currency']; ?> / <?php echo $job['salary_type']; ?>, optional)
                                </small>
                            </label>
                            <div class="input-group" style="max-width:300px;">
                                <span class="input-group-text"><?php echo htmlspecialchars($job['currency']); ?></span>
                                <input type="number" name="proposed_rate" class="form-control"
                                       min="0" step="0.01"
                                       placeholder="e.g. 5000000"
                                       value="<?php echo htmlspecialchars($_POST['proposed_rate'] ?? ($talent['hourly_rate'] ?? '')); ?>">
                            </div>
                            <div class="form-text">Leave blank to accept the posted rate.</div>
                        </div>

                        <!-- Profile completeness reminder -->
                        <?php if (empty($talent['resume_url'])): ?>
                        <div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>
                                Your profile has no resume attached. 
                                <a href="<?php echo SITE_URL; ?>/public/talent/profile.php">Upload your resume</a> to improve your chances.
                            </span>
                        </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="fas fa-paper-plane me-2"></i>Submit Application
                            </button>
                            <a href="javascript:history.back()" class="btn btn-outline-secondary px-4">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
