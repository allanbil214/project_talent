<?php
// public/employer/edit-job.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_EMPLOYER);

$db_connection = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Employer.php';
require_once __DIR__ . '/../../classes/Job.php';
require_once __DIR__ . '/../../classes/Skill.php';

$database = new Database($db_connection);
$employer_model = new Employer($database);
$job_model = new Job($database);
$skill_model = new Skill($database);

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

// Cannot edit filled/closed jobs
if (in_array($job['status'], [JOB_STATUS_FILLED, JOB_STATUS_CLOSED])) {
    redirect(SITE_URL . '/public/employer/job-detail.php?id=' . $job_id);
}

$all_skills = $skill_model->getAll();
$skills_by_category = [];
foreach ($all_skills as $skill) {
    $cat = $skill['category'] ?? 'Other';
    $skills_by_category[$cat][] = $skill;
}

$job_skill_ids = array_column($job['skills'], 'id');
$job_skills_for_js = array_map(function($s) {
    return ['id' => $s['id'], 'name' => $s['name'], 'required' => (int)$s['required']];
}, $job['skills']);

$page_title = 'Edit Job - ' . SITE_NAME;
$body_class = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="page-header d-flex align-items-center gap-3">
                <a href="<?php echo SITE_URL; ?>/public/employer/job-detail.php?id=<?php echo $job_id; ?>"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="mb-0"><i class="fas fa-edit"></i> Edit Job</h2>
                    <p class="text-muted mb-0">Editing: <?php echo htmlspecialchars($job['title']); ?></p>
                </div>
            </div>

            <?php if ($job['status'] === JOB_STATUS_ACTIVE): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Saving changes will re-submit this job for admin approval and temporarily remove it from listings.
                </div>
            <?php endif; ?>

            <form id="editJobForm" data-job-id="<?php echo $job_id; ?>">

                <div class="row">
                    <div class="col-lg-8 mb-4">

                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Job Details</h5>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Job Title *</label>
                                    <input type="text" class="form-control" name="title" id="title"
                                           value="<?php echo htmlspecialchars($job['title']); ?>" required>
                                    <div class="invalid-feedback" id="title-error"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Job Description *</label>
                                    <textarea class="form-control" name="description" id="description"
                                              rows="10" required><?php echo htmlspecialchars($job['description']); ?></textarea>
                                    <div class="invalid-feedback" id="description-error"></div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Job Type *</label>
                                        <select class="form-select" name="job_type" required>
                                            <?php foreach (['full-time','part-time','contract','freelance'] as $type): ?>
                                                <option value="<?php echo $type; ?>"
                                                    <?php echo $job['job_type'] === $type ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($type); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Location Type *</label>
                                        <select class="form-select" name="location_type" id="location_type" required>
                                            <?php foreach (['remote','onsite','hybrid'] as $type): ?>
                                                <option value="<?php echo $type; ?>"
                                                    <?php echo $job['location_type'] === $type ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($type); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="mb-3" id="location_address_wrapper"
                                     <?php echo $job['location_type'] === 'remote' ? 'style="display:none"' : ''; ?>>
                                    <label class="form-label fw-semibold">Office Address</label>
                                    <input type="text" class="form-control" name="location_address"
                                           value="<?php echo htmlspecialchars($job['location_address'] ?? ''); ?>">
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Experience Required (years)</label>
                                        <input type="number" class="form-control" name="experience_required"
                                               min="0" max="20" value="<?php echo $job['experience_required'] ?? 0; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Application Deadline</label>
                                        <input type="date" class="form-control" name="deadline"
                                               value="<?php echo $job['deadline'] ?? ''; ?>"
                                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Salary -->
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Compensation</h5>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">Salary Type</label>
                                        <select class="form-select" name="salary_type">
                                            <?php foreach (['monthly','hourly','project'] as $st): ?>
                                                <option value="<?php echo $st; ?>"
                                                    <?php echo ($job['salary_type'] ?? 'monthly') === $st ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($st); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">Min Salary (IDR)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" class="form-control" name="salary_min"
                                                   value="<?php echo $job['salary_min'] ?? ''; ?>" min="0" step="100000">
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">Max Salary (IDR)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" class="form-control" name="salary_max"
                                                   value="<?php echo $job['salary_max'] ?? ''; ?>" min="0" step="100000">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Skills -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-1">Required Skills</h5>
                                <p class="text-muted small mb-3">Click to add. Green = required, yellow = nice-to-have.</p>

                                <div id="selectedSkillsPreview" class="mb-3 d-flex flex-wrap gap-2 min-height-40">
                                    <?php if (empty($job['skills'])): ?>
                                        <span class="text-muted small" id="noSkillsHint">No skills selected yet</span>
                                    <?php endif; ?>
                                </div>

                                <input type="text" class="form-control mb-3" id="skillSearchInput" placeholder="Search skills...">

                                <div id="skillsContainer" style="max-height:300px;overflow-y:auto">
                                    <?php foreach ($skills_by_category as $category => $skills): ?>
                                        <div class="skill-category mb-3">
                                            <h6 class="text-muted small fw-bold mb-2">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($category); ?>
                                            </h6>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($skills as $skill): ?>
                                                    <button type="button"
                                                            class="btn btn-sm skill-option-btn <?php echo in_array($skill['id'], $job_skill_ids) ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                                                            data-skill-id="<?php echo $skill['id']; ?>"
                                                            data-skill-name="<?php echo htmlspecialchars($skill['name']); ?>">
                                                        <?php echo htmlspecialchars($skill['name']); ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <input type="hidden" name="skills_json" id="skills_json"
                                       value="<?php echo htmlspecialchars(json_encode($job_skills_for_js)); ?>">
                            </div>
                        </div>

                    </div>

                    <!-- Right -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm sticky-top" style="top:80px">
                            <div class="card-body">
                                <h5 class="card-title">Current Status</h5>
                                <p><?php echo getJobStatusBadge($job['status']); ?></p>

                                <div class="alert alert-info small">
                                    <i class="fas fa-info-circle"></i>
                                    Editing will re-submit this job for approval.
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary" id="updateJobBtn">
                                        <i class="fas fa-save"></i> Save & Re-submit
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </form>

        </div>
    </div>
</div>

<script>
    // Pre-populate selected skills on page load
    window.preloadedSkills = <?php echo json_encode($job_skills_for_js); ?>;
</script>

<?php
$additional_js = ['employer.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
