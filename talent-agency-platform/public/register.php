<?php
// public/register.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = getCurrentUserRole();
    if ($role === ROLE_TALENT) {
        redirect(SITE_URL . '/public/talent/dashboard.php');
    } elseif ($role === ROLE_EMPLOYER) {
        redirect(SITE_URL . '/public/employer/dashboard.php');
    }
}

$selected_role = $_GET['role'] ?? 'talent';
$page_title = 'Register - ' . SITE_NAME;
$body_class = 'register-page';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="register-container">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100 py-5">
            <div class="col-md-6">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary">
                                <i class="fas fa-briefcase"></i> <?php echo SITE_NAME; ?>
                            </h2>
                            <p class="text-muted">Create your account</p>
                        </div>
                        
                        <!-- Role Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">I want to register as:</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="role" id="roleTalent" 
                                       value="talent" <?php echo $selected_role === 'talent' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary" for="roleTalent">
                                    <i class="fas fa-user"></i> Talent
                                </label>
                                
                                <input type="radio" class="btn-check" name="role" id="roleEmployer" 
                                       value="employer" <?php echo $selected_role === 'employer' ? 'checked' : ''; ?>>
                                <label class="btn btn-outline-primary" for="roleEmployer">
                                    <i class="fas fa-building"></i> Employer
                                </label>
                            </div>
                        </div>
                        
                        <form id="registerForm">
                            <input type="hidden" name="role" id="selectedRole" value="<?php echo $selected_role; ?>">
                            
                            <!-- Talent Fields -->
                            <div id="talentFields" style="<?php echo $selected_role === 'talent' ? '' : 'display:none;'; ?>">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           placeholder="Enter your full name">
                                    <div class="invalid-feedback" id="full_name-error"></div>
                                </div>
                            </div>
                            
                            <!-- Employer Fields -->
                            <div id="employerFields" style="<?php echo $selected_role === 'employer' ? '' : 'display:none;'; ?>">
                                <div class="mb-3">
                                    <label for="company_name" class="form-label">Company Name *</label>
                                    <input type="text" class="form-control" id="company_name" name="company_name" 
                                           placeholder="Enter your company name">
                                    <div class="invalid-feedback" id="company_name-error"></div>
                                </div>
                            </div>
                            
                            <!-- Common Fields -->
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="Enter your email" required>
                                </div>
                                <div class="invalid-feedback" id="email-error"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Create a password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <small class="text-muted">Minimum 8 characters</small>
                                <div class="invalid-feedback" id="password-error"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password_confirmation" class="form-label">Confirm Password *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password_confirmation" 
                                           name="password_confirmation" placeholder="Confirm your password" required>
                                </div>
                                <div class="invalid-feedback" id="password_confirmation-error"></div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="text-decoration-none">Terms of Service</a> 
                                    and <a href="#" class="text-decoration-none">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg" id="registerBtn">
                                    <i class="fas fa-user-plus"></i> Create Account
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">Already have an account?</p>
                            <a href="<?php echo SITE_URL; ?>/public/login.php" class="btn btn-outline-primary mt-2">
                                Login
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="<?php echo SITE_URL; ?>/public/index.php" class="text-muted">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$additional_js = ['auth.js'];
require_once __DIR__ . '/../includes/footer.php';
?>