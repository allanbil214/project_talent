<?php
// public/admin/employers.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Employer.php';
require_once __DIR__ . '/../../classes/User.php';

requireAdmin();

$pdo      = require __DIR__ . '/../../config/database.php';
$db       = new Database($pdo);
$employer = new Employer($db);
$user     = new User($db);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirect(SITE_URL . '/public/admin/employers.php');
    }

    $action      = $_POST['action'] ?? '';
    $employer_id = (int)($_POST['employer_id'] ?? 0);
    $user_id     = (int)($_POST['user_id'] ?? 0);

    try {
        if ($action === 'verify') {
            $employer->setVerified($employer_id, true);
            setFlash('success', 'Employer verified.');
        } elseif ($action === 'unverify') {
            $employer->setVerified($employer_id, false);
            setFlash('success', 'Employer unverified.');
        } elseif ($action === 'suspend') {
            $user->updateStatus($user_id, STATUS_SUSPENDED);
            setFlash('success', 'Employer account suspended.');
        } elseif ($action === 'activate') {
            $user->updateStatus($user_id, STATUS_ACTIVE);
            setFlash('success', 'Employer account activated.');
        }
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }

    redirect(SITE_URL . '/public/admin/employers.php');
}

// Filters & pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'search'   => $_GET['search'] ?? '',
    'industry' => $_GET['industry'] ?? '',
    'verified' => isset($_GET['verified']) ? (int)$_GET['verified'] : null,
];

$result     = $employer->getAll($page, 20, $filters);
$employers  = $result['data'];
$pagination = $result['pagination'];
$stats      = $employer->getAdminStats();
$industries = $employer->getIndustries();

$page_title     = 'Manage Employers - ' . SITE_NAME;
$body_class     = 'dashboard-page admin-dashboard';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">Manage Employers</h2>
                    <p class="text-muted mb-0">Verify, manage, and monitor employer accounts</p>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                            <small class="text-muted">Total Employers</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-success"><?php echo $stats['verified']; ?></h4>
                            <small class="text-muted">Verified</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-info"><?php echo $stats['jobs']; ?></h4>
                            <small class="text-muted">Active Jobs Posted</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-danger"><?php echo $stats['suspended']; ?></h4>
                            <small class="text-muted">Suspended</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search company name, industry..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="industry" class="form-select">
                                <option value="">All Industries</option>
                                <?php foreach ($industries as $ind): ?>
                                    <option value="<?php echo htmlspecialchars($ind['industry']); ?>"
                                        <?php echo $filters['industry'] === $ind['industry'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($ind['industry']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="verified" class="form-select">
                                <option value="">All</option>
                                <option value="1" <?php echo $filters['verified'] === 1 ? 'selected' : ''; ?>>Verified</option>
                                <option value="0" <?php echo $filters['verified'] === 0 ? 'selected' : ''; ?>>Unverified</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                        <div class="col-md-2">
                            <a href="?" class="btn btn-outline-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <?php flashMessage(); ?>

            <!-- Employers Table -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Employers <span class="badge bg-secondary"><?php echo $pagination['total']; ?></span></h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($employers)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-building fa-3x mb-3"></i>
                            <p>No employers found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Company</th>
                                        <th>Contact</th>
                                        <th>Industry</th>
                                        <th>Size</th>
                                        <th>Rating</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($employers as $e): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if ($e['company_logo_url']): ?>
                                                        <img src="<?php echo getCompanyLogoUrl($e['company_logo_url']); ?>"
                                                             width="40" height="40" class="rounded" style="object-fit:cover;">
                                                    <?php else: ?>
                                                        <div class="bg-primary rounded d-flex align-items-center justify-content-center text-white"
                                                             style="width:40px;height:40px;font-size:14px;font-weight:bold;">
                                                            <?php echo strtoupper(substr($e['company_name'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <div class="fw-semibold">
                                                            <?php echo htmlspecialchars($e['company_name']); ?>
                                                            <?php if ($e['verified']): ?>
                                                                <i class="fas fa-check-circle text-primary" title="Verified"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($e['website']): ?>
                                                            <small><a href="<?php echo htmlspecialchars($e['website']); ?>" target="_blank" class="text-muted">
                                                                <?php echo htmlspecialchars(parse_url($e['website'], PHP_URL_HOST) ?? $e['website']); ?>
                                                            </a></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($e['email']); ?></small><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($e['phone'] ?? '—'); ?></small>
                                            </td>
                                            <td><small><?php echo htmlspecialchars($e['industry'] ?? '—'); ?></small></td>
                                            <td><small><?php echo htmlspecialchars($e['company_size'] ?? '—'); ?></small></td>
                                            <td>
                                                <?php if ($e['rating_average'] > 0): ?>
                                                    <i class="fas fa-star text-warning"></i>
                                                    <?php echo number_format($e['rating_average'], 1); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($e['status'] === STATUS_SUSPENDED): ?>
                                                    <span class="badge bg-danger">Suspended</span>
                                                <?php elseif ($e['status'] === STATUS_ACTIVE): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($e['status']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><small class="text-muted"><?php echo formatDate($e['created_at']); ?></small></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <!-- Verify -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="employer_id" value="<?php echo $e['id']; ?>">
                                                        <input type="hidden" name="action" value="<?php echo $e['verified'] ? 'unverify' : 'verify'; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $e['verified'] ? 'btn-outline-secondary' : 'btn-outline-primary'; ?>"
                                                                title="<?php echo $e['verified'] ? 'Remove Verification' : 'Verify'; ?>">
                                                            <i class="fas <?php echo $e['verified'] ? 'fa-times-circle' : 'fa-check-circle'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <!-- Suspend / Activate -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="employer_id" value="<?php echo $e['id']; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $e['user_id']; ?>">
                                                        <input type="hidden" name="action" value="<?php echo $e['status'] === STATUS_SUSPENDED ? 'activate' : 'suspend'; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $e['status'] === STATUS_SUSPENDED ? 'btn-outline-success' : 'btn-outline-danger'; ?>"
                                                                title="<?php echo $e['status'] === STATUS_SUSPENDED ? 'Activate' : 'Suspend'; ?>">
                                                            <i class="fas <?php echo $e['status'] === STATUS_SUSPENDED ? 'fa-user-check' : 'fa-user-slash'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <!-- Jobs -->
                                                    <a href="<?php echo SITE_URL; ?>/public/admin/jobs.php?employer_id=<?php echo $e['id']; ?>"
                                                       class="btn btn-sm btn-outline-info" title="View Jobs">
                                                        <i class="fas fa-briefcase"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="card-footer bg-white">
                        <?php
                        $base = '?' . http_build_query(array_filter(['search' => $filters['search'], 'industry' => $filters['industry']]));
                        echo renderPagination($pagination, $base . '&');
                        ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
