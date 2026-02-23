<?php
// public/talent/applications.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_TALENT);

$db_connection = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Talent.php';
require_once __DIR__ . '/../../classes/Application.php';

$database     = new Database($db_connection);
$talent_model = new Talent($database);
$app_model    = new Application($database);

$user_id = getCurrentUserId();
$talent  = $talent_model->getByUserId($user_id);

if (!$talent) {
    redirect(SITE_URL . '/public/talent/profile.php');
}

// Handle withdraw
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'withdraw') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid request.';
    } else {
        try {
            $app_model->withdraw((int)$_POST['app_id'], $talent['id']);
            $_SESSION['flash_success'] = 'Application withdrawn.';
        } catch (Exception $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
    }
    redirect(SITE_URL . '/public/talent/applications.php');
}

$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

$result = $app_model->getByTalent($talent['id'], $page, ITEMS_PER_PAGE, ['status' => $status_filter]);
$applications = $result['data'];
$pagination   = $result['pagination'];
$stats        = $app_model->getTalentStats($talent['id']);

$status_labels = [
    APP_STATUS_PENDING     => ['label' => 'Pending',     'class' => 'warning'],
    APP_STATUS_REVIEWED    => ['label' => 'Reviewed',    'class' => 'info'],
    APP_STATUS_SHORTLISTED => ['label' => 'Shortlisted', 'class' => 'primary'],
    APP_STATUS_REJECTED    => ['label' => 'Rejected',    'class' => 'danger'],
    APP_STATUS_ACCEPTED    => ['label' => 'Accepted',    'class' => 'success'],
];

$page_title    = 'My Applications - ' . SITE_NAME;
$body_class    = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fas fa-file-alt"></i> My Applications</h2>
                    <p class="text-muted mb-0">Track the status of all your job applications.</p>
                </div>
            </div>

            <?php flashMessage(); ?>

            <!-- Stats Row -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-3">
                            <div class="fs-4 fw-bold"><?php echo $stats['total'] ?? 0; ?></div>
                            <div class="text-muted small">Total</div>
                        </div>
                    </div>
                </div>
                <?php foreach ($status_labels as $key => $meta): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-3">
                            <div class="fs-4 fw-bold text-<?php echo $meta['class']; ?>">
                                <?php echo $stats[$key] ?? 0; ?>
                            </div>
                            <div class="text-muted small"><?php echo $meta['label']; ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Filter Tabs -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body py-2">
                    <div class="d-flex flex-wrap gap-1">
                        <a href="?status=" class="btn btn-sm <?php echo $status_filter === '' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                            All (<?php echo $stats['total'] ?? 0; ?>)
                        </a>
                        <?php foreach ($status_labels as $key => $meta): ?>
                        <a href="?status=<?php echo $key; ?>"
                           class="btn btn-sm <?php echo $status_filter === $key ? 'btn-' . $meta['class'] : 'btn-outline-secondary'; ?>">
                            <?php echo $meta['label']; ?> (<?php echo $stats[$key] ?? 0; ?>)
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Applications List -->
            <?php if (empty($applications)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No applications found</h5>
                    <p class="text-muted">
                        <?php echo $status_filter ? "No $status_filter applications yet." : "You haven't applied to any jobs yet."; ?>
                    </p>
                    <a href="<?php echo SITE_URL; ?>/public/talent/dashboard.php" class="btn btn-primary">
                        Browse Jobs
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($applications as $app): ?>
                <?php $meta = $status_labels[$app['status']] ?? ['label' => ucfirst($app['status']), 'class' => 'secondary']; ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <!-- Company Logo -->
                            <?php if ($app['company_logo_url']): ?>
                                <img src="<?php echo getCompanyLogoUrl($app['company_logo_url']); ?>"
                                     alt="Logo" class="rounded" style="width:50px;height:50px;object-fit:cover;flex-shrink:0;">
                            <?php else: ?>
                                <div class="rounded bg-light d-flex align-items-center justify-content-center"
                                     style="width:50px;height:50px;font-size:1.3rem;flex-shrink:0;">
                                    <i class="fas fa-building text-secondary"></i>
                                </div>
                            <?php endif; ?>

                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($app['job_title']); ?></h6>
                                        <p class="text-muted small mb-1"><?php echo htmlspecialchars($app['company_name']); ?></p>
                                        <div class="d-flex flex-wrap gap-1 mb-2">
                                            <span class="badge bg-light text-dark"><?php echo ucfirst($app['job_type']); ?></span>
                                            <span class="badge bg-light text-dark"><?php echo ucfirst($app['location_type']); ?></span>
                                            <?php if ($app['salary_min'] || $app['salary_max']): ?>
                                            <span class="badge bg-light text-dark">
                                                <?php echo $app['currency']; ?> 
                                                <?php echo $app['salary_min'] ? number_format($app['salary_min']) : ''; ?>
                                                <?php echo ($app['salary_min'] && $app['salary_max']) ? ' - ' : ''; ?>
                                                <?php echo $app['salary_max'] ? number_format($app['salary_max']) : ''; ?>
                                                / <?php echo $app['salary_type']; ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $meta['class']; ?> fs-6 px-3 py-2">
                                            <?php echo $meta['label']; ?>
                                        </span>
                                        <?php if ($app['status'] === APP_STATUS_ACCEPTED): ?>
                                        <div class="mt-1">
                                            <a href="<?php echo SITE_URL; ?>/public/talent/contracts.php" 
                                               class="btn btn-sm btn-outline-success">
                                                <i class="fas fa-file-contract me-1"></i>View Contract
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Applied <?php echo timeAgo($app['applied_at']); ?>
                                        <?php if ($app['reviewed_at']): ?>
                                         &bull; Reviewed <?php echo timeAgo($app['reviewed_at']); ?>
                                        <?php endif; ?>
                                        <?php if ($app['proposed_rate']): ?>
                                         &bull; Proposed: <?php echo $app['currency']; ?> <?php echo number_format($app['proposed_rate']); ?>
                                        <?php endif; ?>
                                    </small>

                                    <?php if ($app['status'] === APP_STATUS_PENDING): ?>
                                    <form method="POST" onsubmit="return confirm('Withdraw this application?');" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="action" value="withdraw">
                                        <input type="hidden" name="app_id" value="<?php echo $app['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-times me-1"></i>Withdraw
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?status=<?php echo urlencode($status_filter); ?>&page=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
            <?php endif; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
