<?php
// public/employer/profile.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_EMPLOYER);

$db_connection = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Employer.php';

$database = new Database($db_connection);
$employer_model = new Employer($database);

$user_id = getCurrentUserId();
$employer = $employer_model->getByUserId($user_id);

if (!$employer) {
    $employer_id = $employer_model->create($user_id, ['company_name' => $_SESSION['name'] ?? 'My Company']);
    $employer = $employer_model->getById($employer_id);
}

$page_title = 'Company Profile - ' . SITE_NAME;
$body_class = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="page-header">
                <h2><i class="fas fa-building"></i> Company Profile</h2>
                <p class="text-muted mb-0">Manage your company information</p>
            </div>

            <div class="row">
                <!-- Logo -->
                <div class="col-lg-4 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">Company Logo</h5>
                            <div class="mb-3">
                                <?php if ($employer['company_logo_url']): ?>
                                    <img src="<?php echo SITE_URL . '/' . $employer['company_logo_url']; ?>"
                                         alt="Company Logo"
                                         class="rounded mb-3"
                                         id="logoPreview"
                                         style="width:120px;height:120px;object-fit:cover">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3 mx-auto"
                                         id="logoPlaceholder"
                                         style="width:120px;height:120px">
                                        <i class="fas fa-building fa-3x text-muted"></i>
                                    </div>
                                    <img src="" alt="Company Logo" class="rounded mb-3 d-none"
                                         id="logoPreview" style="width:120px;height:120px;object-fit:cover">
                                <?php endif; ?>
                            </div>
                            <form id="uploadLogoForm" enctype="multipart/form-data">
                                <input type="file" class="form-control mb-2" id="companyLogo"
                                       name="company_logo" accept="image/*">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-upload"></i> Upload Logo
                                </button>
                            </form>

                            <?php if ($employer['verified']): ?>
                                <div class="mt-3">
                                    <span class="badge bg-success">
                                        <i class="fas fa-check-circle"></i> Verified Company
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="mt-3">
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-clock"></i> Pending Verification
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Account Info -->
                    <div class="card border-0 shadow-sm mt-3">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Account Info</h5>
                            <p class="mb-1 small text-muted">Email</p>
                            <p class="fw-semibold"><?php echo htmlspecialchars($employer['email']); ?></p>
                            <p class="mb-1 small text-muted">Member Since</p>
                            <p class="fw-semibold"><?php echo formatDate($employer['user_created_at'] ?? $employer['created_at']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Profile Form -->
                <div class="col-lg-8 mb-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Company Information</h5>
                            <form id="companyProfileForm">

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Company Name *</label>
                                        <input type="text" class="form-control" name="company_name"
                                               value="<?php echo htmlspecialchars($employer['company_name']); ?>" required>
                                        <div class="invalid-feedback" id="company_name-error"></div>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Industry</label>
                                        <input type="text" class="form-control" name="industry"
                                               value="<?php echo htmlspecialchars($employer['industry'] ?? ''); ?>"
                                               placeholder="e.g. Technology, Finance, Healthcare">
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Company Size</label>
                                        <select class="form-select" name="company_size">
                                            <option value="">Select...</option>
                                            <?php foreach (['1-10', '11-50', '51-200', '201-500', '500+'] as $size): ?>
                                                <option value="<?php echo $size; ?>"
                                                    <?php echo ($employer['company_size'] ?? '') === $size ? 'selected' : ''; ?>>
                                                    <?php echo $size; ?> employees
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" class="form-control" name="phone"
                                               value="<?php echo htmlspecialchars($employer['phone'] ?? ''); ?>">
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Website</label>
                                        <input type="url" class="form-control" name="website"
                                               value="<?php echo htmlspecialchars($employer['website'] ?? ''); ?>"
                                               placeholder="https://yourcompany.com">
                                        <div class="invalid-feedback" id="website-error"></div>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Company Description</label>
                                        <textarea class="form-control" name="description" rows="4"
                                                  placeholder="Tell talents about your company, culture, and what makes you great to work with..."><?php echo htmlspecialchars($employer['description'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="col-12 mb-3">
                                        <label class="form-label">Office Address</label>
                                        <textarea class="form-control" name="address" rows="2"
                                                  placeholder="Full office address"><?php echo htmlspecialchars($employer['address'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end">
                                    <button type="submit" class="btn btn-primary" id="saveProfileBtn">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php
$additional_js = ['employer.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
