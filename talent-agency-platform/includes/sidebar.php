<?php
// includes/sidebar.php
require_once __DIR__ . '/../includes/functions.php';

$user_role = getCurrentUserRole();
$current_page = basename($_SERVER['PHP_SELF']);

// Define menu items based on role
$menu_items = [];

if ($user_role === ROLE_TALENT) {
    $menu_items = [
        [
            'icon' => 'fa-tachometer-alt',
            'label' => 'Dashboard',
            'url' => '/public/talent/dashboard.php',
            'page' => 'dashboard.php'
        ],
        [
            'icon' => 'fa-user',
            'label' => 'My Profile',
            'url' => '/public/talent/profile.php',
            'page' => 'profile.php'
        ],
        [
            'icon' => 'fa-award',
            'label' => 'Skills',
            'url' => '/public/talent/skills.php',
            'page' => 'skills.php'
        ],
        [
            'icon' => 'fa-briefcase',
            'label' => 'Browse Jobs',
            'url' => '/public/talent/jobs.php',
            'page' => 'jobs.php'
        ],
        [
            'icon' => 'fa-file-alt',
            'label' => 'Applications',
            'url' => '/public/talent/applications.php',
            'page' => 'applications.php'
        ],
        [
            'icon' => 'fa-file-contract',
            'label' => 'Contracts',
            'url' => '/public/talent/contracts.php',
            'page' => 'contracts.php'
        ],
        [
            'icon' => 'fa-money-bill-wave',
            'label' => 'Earnings',
            'url' => '/public/talent/earnings.php',
            'page' => 'earnings.php'
        ],
        [
            'icon' => 'fa-envelope',
            'label' => 'Messages',
            'url' => '/public/talent/messages.php',
            'page' => 'messages.php'
        ],
        [
            'icon' => 'fa-bell',
            'label' => 'Notifications',
            'url' => '/public/talent/notifications.php',
            'page' => 'notifications.php'
        ],
        [
            'icon' => 'fa-cog',
            'label' => 'Settings',
            'url' => '/public/talent/settings.php',
            'page' => 'settings.php'
        ]
    ];
} elseif ($user_role === ROLE_EMPLOYER) {
    $menu_items = [
        [
            'icon' => 'fa-tachometer-alt',
            'label' => 'Dashboard',
            'url' => '/public/employer/dashboard.php',
            'page' => 'dashboard.php'
        ],
        [
            'icon' => 'fa-building',
            'label' => 'Company Profile',
            'url' => '/public/employer/profile.php',
            'page' => 'profile.php'
        ],
        [
            'icon' => 'fa-briefcase',
            'label' => 'My Jobs',
            'url' => '/public/employer/jobs.php',
            'page' => 'jobs.php'
        ],
        [
            'icon' => 'fa-plus-circle',
            'label' => 'Post New Job',
            'url' => '/public/employer/post-job.php',
            'page' => 'post-job.php'
        ],
        [
            'icon' => 'fa-users',
            'label' => 'Browse Talents',
            'url' => '/public/employer/talents.php',
            'page' => 'talents.php'
        ],
        [
            'icon' => 'fa-file-contract',
            'label' => 'Contracts',
            'url' => '/public/employer/contracts.php',
            'page' => 'contracts.php'
        ],
        [
            'icon' => 'fa-credit-card',
            'label' => 'Payments',
            'url' => '/public/employer/payments.php',
            'page' => 'payments.php'
        ],
        [
            'icon' => 'fa-envelope',
            'label' => 'Messages',
            'url' => '/public/employer/messages.php',
            'page' => 'messages.php'
        ],
        [
            'icon' => 'fa-bell',
            'label' => 'Notifications',
            'url' => '/public/employer/notifications.php',
            'page' => 'notifications.php'
        ],
        [
            'icon' => 'fa-cog',
            'label' => 'Settings',
            'url' => '/public/employer/settings.php',
            'page' => 'settings.php'
        ]
    ];
} elseif ($user_role === ROLE_SUPER_ADMIN || $user_role === ROLE_STAFF) {
    $menu_items = [
        [
            'icon' => 'fa-tachometer-alt',
            'label' => 'Dashboard',
            'url' => '/public/admin/dashboard.php',
            'page' => 'dashboard.php'
        ],
        [
            'icon' => 'fa-users',
            'label' => 'Talents',
            'url' => '/public/admin/talents.php',
            'page' => 'talents.php'
        ],
        [
            'icon' => 'fa-building',
            'label' => 'Employers',
            'url' => '/public/admin/employers.php',
            'page' => 'employers.php'
        ],
        [
            'icon' => 'fa-briefcase',
            'label' => 'Jobs',
            'url' => '/public/admin/jobs.php',
            'page' => 'jobs.php'
        ],
        [
            'icon' => 'fa-file-alt',
            'label' => 'Applications',
            'url' => '/public/admin/applications.php',
            'page' => 'applications.php'
        ],
        [
            'icon' => 'fa-file-contract',
            'label' => 'Contracts',
            'url' => '/public/admin/contracts.php',
            'page' => 'contracts.php'
        ],
        [
            'icon' => 'fa-credit-card',
            'label' => 'Payments',
            'url' => '/public/admin/payments.php',
            'page' => 'payments.php'
        ],
        [
            'icon' => 'fa-handshake',
            'label' => 'Manual Matching',
            'url' => '/public/admin/matches.php',
            'page' => 'matches.php'
        ],
        [
            'icon' => 'fa-envelope',
            'label' => 'Messages',
            'url' => '/public/admin/messages.php',
            'page' => 'messages.php'
        ],
        [
            'icon' => 'fa-chart-bar',
            'label' => 'Reports',
            'url' => '/public/admin/reports.php',
            'page' => 'reports.php'
        ],
        [
            'icon' => 'fa-user-shield',
            'label' => 'Users',
            'url' => '/public/admin/users.php',
            'page' => 'users.php'
        ],
        [
            'icon' => 'fa-user-tie',
            'label' => 'Staff',
            'url' => '/public/admin/staff.php',
            'page' => 'staff.php'
        ],
        [
            'icon' => 'fa-tags',
            'label' => 'Skills',
            'url' => '/public/admin/skills.php',
            'page' => 'skills.php'
        ],
        [
            'icon' => 'fa-cog',
            'label' => 'Settings',
            'url' => '/public/admin/settings.php',
            'page' => 'settings.php'
        ],
        [
            'icon' => 'fa-history',
            'label' => 'Logs',
            'url' => '/public/admin/logs.php',
            'page' => 'logs.php'
        ]
    ];
}
?>

<div class="sidebar bg-dark text-white" id="sidebar">
    <div class="sidebar-header p-3 border-bottom border-secondary">
        <h5 class="mb-0">
            <i class="fas fa-briefcase"></i> 
            <?php echo SITE_NAME; ?>
        </h5>
        <small class="text-muted">
            <?php 
            if ($user_role === ROLE_TALENT) echo 'Talent Portal';
            elseif ($user_role === ROLE_EMPLOYER) echo 'Employer Portal';
            else echo 'Admin Panel';
            ?>
        </small>
    </div>
    
    <div class="sidebar-user p-3 border-bottom border-secondary">
        <div class="d-flex align-items-center">
            <div class="avatar me-3">
                <i class="fas fa-user-circle fa-3x text-primary"></i>
            </div>
            <div class="user-info">
                <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></h6>
                <small class="text-muted"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></small>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <?php foreach ($menu_items as $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $current_page === $item['page'] ? 'active' : ''; ?>" 
                       href="<?php echo SITE_URL . $item['url']; ?>">
                        <i class="fas <?php echo $item['icon']; ?> me-2"></i>
                        <?php echo $item['label']; ?>
                    </a>
                </li>
            <?php endforeach; ?>
            
            <li class="nav-item mt-3">
                <a class="nav-link text-danger" href="<?php echo SITE_URL; ?>/public/logout.php">
                    <i class="fas fa-sign-out-alt me-2"></i>
                    Logout
                </a>
            </li>
        </ul>
    </nav>
</div>

<!-- Sidebar Toggle Button (Mobile) -->
<button class="btn btn-primary sidebar-toggle d-lg-none" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>