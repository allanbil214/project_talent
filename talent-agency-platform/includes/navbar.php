<?php
// includes/navbar.php
require_once __DIR__ . '/../includes/functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = isLoggedIn();
$user_role = getCurrentUserRole();
?>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold text-primary" href="<?php echo SITE_URL; ?>/public/index.php">
            <i class="fas fa-briefcase"></i> <?php echo SITE_NAME; ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <?php if (!$is_logged_in): ?>
                    <!-- Public Navigation -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>/public/index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'how-it-works.php' ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>/public/how-it-works.php">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'pricing.php' ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>/public/pricing.php">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'about.php' ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>/public/about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $current_page == 'contact.php' ? 'active' : ''; ?>" 
                           href="<?php echo SITE_URL; ?>/public/contact.php">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/public/login.php">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-primary btn-sm ms-2" href="<?php echo SITE_URL; ?>/public/register.php">
                            Get Started
                        </a>
                    </li>
                <?php else: ?>
                    <!-- Logged-in Navigation -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> 
                            <?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <?php if ($user_role === ROLE_TALENT): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/talent/dashboard.php">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/talent/profile.php">
                                        <i class="fas fa-user"></i> My Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/talent/jobs.php">
                                        <i class="fas fa-briefcase"></i> Browse Jobs
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/talent/applications.php">
                                        <i class="fas fa-file-alt"></i> My Applications
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/talent/contracts.php">
                                        <i class="fas fa-file-contract"></i> Contracts
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/talent/messages.php">
                                        <i class="fas fa-envelope"></i> Messages
                                    </a>
                                </li>
                            <?php elseif ($user_role === ROLE_EMPLOYER): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/employer/dashboard.php">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/employer/profile.php">
                                        <i class="fas fa-building"></i> Company Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/employer/jobs.php">
                                        <i class="fas fa-briefcase"></i> My Jobs
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/employer/post-job.php">
                                        <i class="fas fa-plus-circle"></i> Post New Job
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/employer/talents.php">
                                        <i class="fas fa-users"></i> Browse Talents
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/employer/messages.php">
                                        <i class="fas fa-envelope"></i> Messages
                                    </a>
                                </li>
                            <?php elseif ($user_role === ROLE_SUPER_ADMIN || $user_role === ROLE_STAFF): ?>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/admin/dashboard.php">
                                        <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/admin/talents.php">
                                        <i class="fas fa-users"></i> Manage Talents
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/admin/employers.php">
                                        <i class="fas fa-building"></i> Manage Employers
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/admin/jobs.php">
                                        <i class="fas fa-briefcase"></i> Manage Jobs
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="<?php echo SITE_URL; ?>/public/logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- Notifications -->
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="#" id="notificationBell">
                            <i class="fas fa-bell"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" 
                                  id="notification-badge" style="display: none;">
                                0
                            </span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>