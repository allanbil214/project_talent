<?php
// public/login.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $role = getCurrentUserRole();
    if ($role === ROLE_TALENT) {
        redirect(SITE_URL . '/public/talent/dashboard.php');
    } elseif ($role === ROLE_EMPLOYER) {
        redirect(SITE_URL . '/public/employer/dashboard.php');
    } elseif ($role === ROLE_SUPER_ADMIN || $role === ROLE_STAFF) {
        redirect(SITE_URL . '/public/admin/dashboard.php');
    }
}

$page_title = 'Login - ' . SITE_NAME;
$body_class = 'login-page';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="login-container">
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary">
                                <i class="fas fa-briefcase"></i> <?php echo SITE_NAME; ?>
                            </h2>
                            <p class="text-muted">Login to your account</p>
                        </div>
                        
                        <form id="loginForm">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           placeholder="Enter your email" required>
                                </div>
                                <div class="invalid-feedback" id="email-error"></div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" 
                                           placeholder="Enter your password" required>
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback" id="password-error"></div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-lg" id="loginBtn">
                                    <i class="fas fa-sign-in-alt"></i> Login
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <a href="<?php echo SITE_URL; ?>/public/forgot-password.php" class="text-decoration-none">
                                    Forgot password?
                                </a>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account?</p>
                            <a href="<?php echo SITE_URL; ?>/public/register.php" class="btn btn-outline-primary mt-2">
                                Create Account
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
