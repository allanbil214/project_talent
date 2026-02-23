<?php
// public/employer/post-job.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_EMPLOYER);

$db_connection = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Employer.php';
require_once __DIR__ . '/../../classes/Skill.php';

$database = new Database($db_connection);
$employer_model = new Employer($database);
$skill_model = new Skill($database);

$user_id = getCurrentUserId();
$employer = $employer_model->getByUserId($user_id);

if (!$employer) {
    redirect(SITE_URL . '/public/employer/profile.php');
}

$all_skills = $skill_model->getAll();
$skills_by_category = [];
foreach ($all_skills as $skill) {
    $cat = $skill['category'] ?? 'Other';
    $skills_by_category[$cat][] = $skill;
}

$page_title = 'Post a Job - ' . SITE_NAME;
$body_class = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="page-header d-flex align-items-center gap-3">
                <a href="<?php echo SITE_URL; ?>/public/employer/jobs.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div>
                    <h2 class="mb-0"><i class="fas fa-plus-circle"></i> Post a Job</h2>
                    <p class="text-muted mb-0">Fill in the details below. Jobs go live after admin approval.</p>
                </div>
            </div>

            <form id="postJobForm">
                <div class="row">
                    <!-- Left: Main Info -->
                    <div class="col-lg-8 mb-4">

                        <!-- Basic Info -->
                        <div class="card border-0 shadow-sm mb-3">
                            <div class="card-body">
                                <h5 class="card-title mb-4">Job Details</h5>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Job Title *</label>
                                    <input type="text" class="form-control" name="title" id="title"
                                           placeholder="e.g. Senior Frontend Developer" required>
                                    <div class="invalid-feedback" id="title-error"></div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Job Description *</label>
                                    <textarea class="form-control" name="description" id="description"
                                              rows="8" placeholder="Describe the role, responsibilities, requirements, and what makes it great..."
                                              required></textarea>
                                    <div class="invalid-feedback" id="description-error"></div>
                                    <small class="text-muted">Tip: Be specific about day-to-day tasks and must-have skills.</small>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Job Type *</label>
                                        <select class="form-select" name="job_type" id="job_type" required>
                                            <option value="">Select type...</option>
                                            <option value="full-time">Full-time</option>
                                            <option value="part-time">Part-time</option>
                                            <option value="contract">Contract</option>
                                            <option value="freelance">Freelance</option>
                                        </select>
                                        <div class="invalid-feedback" id="job_type-error"></div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-semibold">Location Type *</label>
                                        <select class="form-select" name="location_type" id="location_type" required>
                                            <option value="">Select...</option>
                                            <option value="remote">Remote</option>
                                            <option value="onsite">On-site</option>
                                            <option value="hybrid">Hybrid</option>
                                        </select>
                                        <div class="invalid-feedback" id="location_type-error"></div>
                                    </div>
                                </div>

                                <div class="mb-3" id="location_address_wrapper" style="display:none">
                                    <label class="form-label fw-semibold">Office Address</label>
                                    <input type="text" class="form-control" name="location_address"
                                           placeholder="e.g. Jl. Sudirman No. 1, Jakarta">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Years of Experience Required</label>
                                    <input type="number" class="form-control" name="experience_required"
                                           min="0" max="20" value="0">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Application Deadline</label>
                                    <input type="date" class="form-control" name="deadline"
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
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
                                        <select class="form-select" name="salary_type" id="salary_type">
                                            <option value="monthly">Monthly</option>
                                            <option value="hourly">Hourly</option>
                                            <option value="project">Per Project</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">Min Salary (IDR)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" class="form-control" name="salary_min"
                                                   min="0" step="100000" placeholder="0">
                                        </div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label fw-semibold">Max Salary (IDR)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" class="form-control" name="salary_max"
                                                   min="0" step="100000" placeholder="0">
                                        </div>
                                    </div>
                                </div>
                                <small class="text-muted">Leave blank to show "Negotiable"</small>
                            </div>
                        </div>

                        <!-- Skills -->
                        <div class="card border-0 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title mb-1">Required Skills</h5>
                                <p class="text-muted small mb-3">Click skills to add them. Toggle required/nice-to-have.</p>

                                <!-- Selected Skills Preview -->
                                <div id="selectedSkillsPreview" class="mb-3 d-flex flex-wrap gap-2 min-height-40">
                                    <span class="text-muted small" id="noSkillsHint">No skills selected yet</span>
                                </div>

                                <!-- Skill Search -->
                                <input type="text" class="form-control mb-3" id="skillSearchInput"
                                       placeholder="Search skills...">

                                <!-- Skills by Category -->
                                <div id="skillsContainer" style="max-height:300px;overflow-y:auto">
                                    <?php foreach ($skills_by_category as $category => $skills): ?>
                                        <div class="skill-category mb-3">
                                            <h6 class="text-muted small fw-bold mb-2">
                                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($category); ?>
                                            </h6>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($skills as $skill): ?>
                                                    <button type="button"
                                                            class="btn btn-sm btn-outline-secondary skill-option-btn"
                                                            data-skill-id="<?php echo $skill['id']; ?>"
                                                            data-skill-name="<?php echo htmlspecialchars($skill['name']); ?>">
                                                        <?php echo htmlspecialchars($skill['name']); ?>
                                                    </button>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Hidden input for skills JSON -->
                                <input type="hidden" name="skills_json" id="skills_json" value="[]">
                            </div>
                        </div>

                    </div>

                    <!-- Right: Summary & Submit -->
                    <div class="col-lg-4 mb-4">
                        <div class="card border-0 shadow-sm sticky-top" style="top:80px">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Company</h5>
                                <div class="d-flex align-items-center gap-3 mb-4 pb-3 border-bottom">
                                    <?php if ($employer['company_logo_url']): ?>
                                        <img src="<?php echo SITE_URL . '/' . $employer['company_logo_url']; ?>"
                                             class="rounded" width="48" height="48" style="object-fit:cover" alt="">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                             style="width:48px;height:48px">
                                            <i class="fas fa-building text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($employer['company_name']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($employer['industry'] ?? 'No industry set'); ?></div>
                                    </div>
                                </div>

                                <div class="alert alert-info small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Your job will be reviewed by our team before going live. This usually takes less than 24 hours.
                                </div>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary" id="submitJobBtn">
                                        <i class="fas fa-paper-plane"></i> Submit for Review
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="saveDraftBtn">
                                        <i class="fas fa-save"></i> Save as Draft
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

<?php
$additional_js = ['employer.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
