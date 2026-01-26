<?php
// public/talent/profile.php
require_once __DIR__ . '/../../config/session.php';

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_TALENT);

$db = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Talent.php';

$database = new Database($db);
$talent_model = new Talent($database);

$user_id = getCurrentUserId();
$talent = $talent_model->getByUserId($user_id);

if (!$talent) {
    // Create talent profile if doesn't exist
    $talent_id = $talent_model->create($user_id, ['full_name' => $_SESSION['name'] ?? 'User']);
    $talent = $talent_model->getById($talent_id);
}

$page_title = 'My Profile - ' . SITE_NAME;
$body_class = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Page Header -->
            <div class="page-header">
                <h2><i class="fas fa-user"></i> My Profile</h2>
                <p class="text-muted mb-0">Manage your professional profile</p>
            </div>
            
            <div class="row">
                <!-- Profile Photo -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-body text-center">
                            <h5 class="card-title mb-3">Profile Photo</h5>
                            <div class="mb-3">
                                <img src="<?php echo getAvatarUrl($talent['profile_photo_url']); ?>" 
                                     alt="Profile Photo" 
                                     class="avatar-lg rounded-circle mb-3" 
                                     id="profilePhotoPreview">
                            </div>
                            <form id="uploadPhotoForm" enctype="multipart/form-data">
                                <input type="file" class="form-control mb-2" id="profilePhoto" 
                                       name="profile_photo" accept="image/*">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="fas fa-upload"></i> Upload Photo
                                </button>
                            </form>
                            
                            <?php if ($talent['verified']): ?>
                                <div class="mt-3">
                                    <span class="badge badge-success">
                                        <i class="fas fa-check-circle"></i> Verified Profile
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Resume Upload -->
                    <div class="card mt-3">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Resume/CV</h5>
                            <?php if ($talent['resume_url']): ?>
                                <p class="mb-2">
                                    <i class="fas fa-file-pdf text-danger"></i>
                                    <a href="<?php echo SITE_URL . '/' . $talent['resume_url']; ?>" 
                                       target="_blank">View Resume</a>
                                </p>
                            <?php endif; ?>
                            <form id="uploadResumeForm" enctype="multipart/form-data">
                                <input type="file" class="form-control mb-2" id="resume" 
                                       name="resume" accept=".pdf,.doc,.docx">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-upload"></i> Upload Resume
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Profile Form -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-4">Personal Information</h5>
                            <form id="profileForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="full_name" class="form-label">Full Name *</label>
                                        <input type="text" class="form-control" id="full_name" 
                                               name="full_name" value="<?php echo htmlspecialchars($talent['full_name']); ?>" required>
                                        <div class="invalid-feedback" id="full_name-error"></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" 
                                               name="phone" value="<?php echo htmlspecialchars($talent['phone'] ?? ''); ?>">
                                        <div class="invalid-feedback" id="phone-error"></div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" 
                                               name="city" value="<?php echo htmlspecialchars($talent['city'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="country" class="form-label">Country</label>
                                        <input type="text" class="form-control" id="country" 
                                               name="country" value="<?php echo htmlspecialchars($talent['country'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" 
                                               name="date_of_birth" value="<?php echo $talent['date_of_birth'] ?? ''; ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="years_experience" class="form-label">Years of Experience</label>
                                        <input type="number" class="form-control" id="years_experience" 
                                               name="years_experience" min="0" max="50" 
                                               value="<?php echo $talent['years_experience'] ?? 0; ?>">
                                    </div>
                                    
                                    <div class="col-12 mb-3">
                                        <label for="bio" class="form-label">Bio/About Me</label>
                                        <textarea class="form-control" id="bio" name="bio" 
                                                  rows="4" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($talent['bio'] ?? ''); ?></textarea>
                                        <small class="text-muted">Describe your experience, skills, and what you're looking for</small>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="hourly_rate" class="form-label">Hourly Rate</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Rp</span>
                                            <input type="number" class="form-control" id="hourly_rate" 
                                                   name="hourly_rate" min="0" step="1000" 
                                                   value="<?php echo $talent['hourly_rate'] ?? ''; ?>">
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="availability_status" class="form-label">Availability Status</label>
                                        <select class="form-select" id="availability_status" name="availability_status">
                                            <option value="<?php echo AVAILABILITY_AVAILABLE; ?>" 
                                                <?php echo $talent['availability_status'] == AVAILABILITY_AVAILABLE ? 'selected' : ''; ?>>
                                                Available
                                            </option>
                                            <option value="<?php echo AVAILABILITY_BUSY; ?>" 
                                                <?php echo $talent['availability_status'] == AVAILABILITY_BUSY ? 'selected' : ''; ?>>
                                                Busy
                                            </option>
                                            <option value="<?php echo AVAILABILITY_UNAVAILABLE; ?>" 
                                                <?php echo $talent['availability_status'] == AVAILABILITY_UNAVAILABLE ? 'selected' : ''; ?>>
                                                Unavailable
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="portfolio_url" class="form-label">Portfolio URL</label>
                                        <input type="url" class="form-control" id="portfolio_url" 
                                               name="portfolio_url" placeholder="https://" 
                                               value="<?php echo htmlspecialchars($talent['portfolio_url'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Preferred Work Type</label>
                                        <div>
                                            <?php 
                                            $preferences = $talent_model->getWorkPreferences($talent['id']);
                                            ?>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="preferred_work_type[]" value="full-time" 
                                                       id="work_full_time" 
                                                       <?php echo in_array('full-time', $preferences) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="work_full_time">Full-time</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="preferred_work_type[]" value="part-time" 
                                                       id="work_part_time" 
                                                       <?php echo in_array('part-time', $preferences) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="work_part_time">Part-time</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="preferred_work_type[]" value="freelance" 
                                                       id="work_freelance" 
                                                       <?php echo in_array('freelance', $preferences) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="work_freelance">Freelance</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="preferred_work_type[]" value="project" 
                                                       id="work_project" 
                                                       <?php echo in_array('project', $preferences) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="work_project">Project</label>
                                            </div>
                                        </div>
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
$additional_js = ['profile.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>