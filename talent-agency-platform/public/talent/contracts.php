<?php
// public/talent/contracts.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_TALENT);

$db_connection = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Talent.php';
require_once __DIR__ . '/../../classes/Contract.php';

$database     = new Database($db_connection);
$talent_model = new Talent($database);
$contract_model = new Contract($database);

$user_id = getCurrentUserId();
$talent  = $talent_model->getByUserId($user_id);

if (!$talent) {
    redirect(SITE_URL . '/public/talent/profile.php');
}

$status_filter = $_GET['status'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));

$result    = $contract_model->getByTalent($talent['id'], $page, ITEMS_PER_PAGE, ['status' => $status_filter]);
$contracts = $result['data'];
$pagination = $result['pagination'];
$stats     = $contract_model->getStats('talent', $talent['id']);

$status_labels = [
    CONTRACT_STATUS_ACTIVE     => ['label' => 'Active',     'class' => 'success'],
    CONTRACT_STATUS_COMPLETED  => ['label' => 'Completed',  'class' => 'info'],
    CONTRACT_STATUS_TERMINATED => ['label' => 'Terminated', 'class' => 'danger'],
];

$page_title    = 'My Contracts - ' . SITE_NAME;
$body_class    = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="page-header mb-4">
                <h2><i class="fas fa-file-contract"></i> My Contracts</h2>
                <p class="text-muted mb-0">Your employment contracts with employers.</p>
            </div>

            <?php flashMessage(); ?>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-3">
                            <div class="fs-4 fw-bold"><?php echo $stats['total'] ?? 0; ?></div>
                            <div class="text-muted small">Total</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-3">
                            <div class="fs-4 fw-bold text-success"><?php echo $stats['active'] ?? 0; ?></div>
                            <div class="text-muted small">Active</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-3">
                            <div class="fs-4 fw-bold text-info"><?php echo $stats['completed'] ?? 0; ?></div>
                            <div class="text-muted small">Completed</div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-3">
                            <div class="fs-4 fw-bold">
                                <?php echo DEFAULT_CURRENCY; ?> <?php echo $stats['total_value'] ? number_format($stats['total_value']) : '0'; ?>
                            </div>
                            <div class="text-muted small">Total Earned</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body py-2">
                    <div class="d-flex gap-1">
                        <a href="?status=" class="btn btn-sm <?php echo $status_filter === '' ? 'btn-dark' : 'btn-outline-secondary'; ?>">All</a>
                        <?php foreach ($status_labels as $key => $meta): ?>
                        <a href="?status=<?php echo $key; ?>"
                           class="btn btn-sm <?php echo $status_filter === $key ? 'btn-' . $meta['class'] : 'btn-outline-secondary'; ?>">
                            <?php echo $meta['label']; ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Contracts -->
            <?php if (empty($contracts)): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-contract fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No contracts yet</h5>
                    <p class="text-muted">Once an employer hires you, your contracts will appear here.</p>
                    <a href="<?php echo SITE_URL; ?>/public/talent/applications.php" class="btn btn-outline-primary">
                        View Applications
                    </a>
                </div>
            </div>
            <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($contracts as $c): ?>
                <?php $meta = $status_labels[$c['status']] ?? ['label' => ucfirst($c['status']), 'class' => 'secondary']; ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <?php if ($c['company_logo_url']): ?>
                                <img src="<?php echo getCompanyLogoUrl($c['company_logo_url']); ?>"
                                     class="rounded" style="width:50px;height:50px;object-fit:cover;flex-shrink:0;" alt="">
                            <?php else: ?>
                                <div class="rounded bg-light d-flex align-items-center justify-content-center"
                                     style="width:50px;height:50px;font-size:1.3rem;flex-shrink:0;">
                                    <i class="fas fa-building text-secondary"></i>
                                </div>
                            <?php endif; ?>

                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($c['company_name']); ?></h6>
                                        <small class="text-muted d-block"><?php echo htmlspecialchars($c['job_title']); ?></small>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-money-bill me-1"></i>
                                                <?php echo htmlspecialchars($c['currency']); ?> <?php echo number_format($c['rate']); ?> / <?php echo $c['rate_type']; ?>
                                            </span>
                                            <span class="badge bg-light text-dark">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?php echo date('d M Y', strtotime($c['start_date'])); ?>
                                                <?php echo $c['end_date'] ? ' &rarr; ' . date('d M Y', strtotime($c['end_date'])) : ' (ongoing)'; ?>
                                            </span>
                                            <?php if ($c['total_amount']): ?>
                                            <span class="badge bg-light text-dark">
                                                Total: <?php echo $c['currency']; ?> <?php echo number_format($c['total_amount']); ?>
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $meta['class']; ?> px-2 py-1"><?php echo $meta['label']; ?></span>
                                        <div class="text-muted small mt-1"><?php echo timeAgo($c['created_at']); ?></div>
                                    </div>
                                </div>

                                <?php if ($c['contract_document_url']): ?>
                                <div class="mt-2">
                                    <a href="<?php echo SITE_URL; ?>/public/serve-file.php?file=<?php echo urlencode($c['contract_document_url']); ?>"
                                       target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-file-pdf me-1"></i>View Contract Document
                                    </a>
                                </div>
                                <?php endif; ?>
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
                        <a class="page-link" href="?status=<?php echo urlencode($status_filter); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
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
