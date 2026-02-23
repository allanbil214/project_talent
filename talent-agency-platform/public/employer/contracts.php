<?php
// public/employer/contracts.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_EMPLOYER);

$db_connection = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Employer.php';
require_once __DIR__ . '/../../classes/Contract.php';

$database       = new Database($db_connection);
$employer_model = new Employer($database);
$contract_model = new Contract($database);

$user_id  = getCurrentUserId();
$employer = $employer_model->getByUserId($user_id);

if (!$employer) {
    redirect(SITE_URL . '/public/employer/profile.php');
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = 'Invalid request.';
        redirect(SITE_URL . '/public/employer/contracts.php');
    }

    $action      = $_POST['action'] ?? '';
    $contract_id = (int)($_POST['contract_id'] ?? 0);

    try {
        if ($action === 'update_status') {
            $contract_model->updateStatus($contract_id, $_POST['status'], $employer['id'], ROLE_EMPLOYER);
            $_SESSION['flash_success'] = 'Contract status updated.';
        } elseif ($action === 'update_contract') {
            $contract_model->update($contract_id, $employer['id'], [
                'end_date'     => $_POST['end_date'] ?: null,
                'total_amount' => !empty($_POST['total_amount']) ? (float)$_POST['total_amount'] : null,
            ]);
            $_SESSION['flash_success'] = 'Contract updated.';
        }
    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }

    redirect(SITE_URL . '/public/employer/contracts.php');
}

$status_filter = $_GET['status'] ?? '';
$page          = max(1, (int)($_GET['page'] ?? 1));

$result    = $contract_model->getByEmployer($employer['id'], $page, ITEMS_PER_PAGE, ['status' => $status_filter]);
$contracts = $result['data'];
$pagination = $result['pagination'];
$stats     = $contract_model->getStats('employer', $employer['id']);

$status_labels = [
    CONTRACT_STATUS_ACTIVE     => ['label' => 'Active',     'class' => 'success'],
    CONTRACT_STATUS_COMPLETED  => ['label' => 'Completed',  'class' => 'info'],
    CONTRACT_STATUS_TERMINATED => ['label' => 'Terminated', 'class' => 'danger'],
];

$page_title    = 'Contracts - ' . SITE_NAME;
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
                    <h2><i class="fas fa-file-contract"></i> Contracts</h2>
                    <p class="text-muted mb-0">Manage your talent contracts.</p>
                </div>
            </div>

            <?php flashMessage(); ?>

            <!-- Summary Stats -->
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
                            <div class="text-muted small">Total Value</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body py-2">
                    <div class="d-flex gap-1">
                        <a href="?status=" class="btn btn-sm <?php echo $status_filter === '' ? 'btn-dark' : 'btn-outline-secondary'; ?>">
                            All (<?php echo $stats['total'] ?? 0; ?>)
                        </a>
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
                    <p class="text-muted">Contracts are created when you hire an applicant from a job posting.</p>
                    <a href="<?php echo SITE_URL; ?>/public/employer/jobs.php" class="btn btn-primary">View Jobs</a>
                </div>
            </div>
            <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($contracts as $c): ?>
                <?php $meta = $status_labels[$c['status']] ?? ['label' => ucfirst($c['status']), 'class' => 'secondary']; ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <?php if ($c['profile_photo_url']): ?>
                                <img src="<?php echo getAvatarUrl($c['profile_photo_url']); ?>"
                                     class="rounded-circle" style="width:50px;height:50px;object-fit:cover;flex-shrink:0;" alt="">
                            <?php else: ?>
                                <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center text-white fw-bold"
                                     style="width:50px;height:50px;font-size:1.2rem;flex-shrink:0;">
                                    <?php echo strtoupper(substr($c['talent_name'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>

                            <div class="flex-grow-1">
                                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($c['talent_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($c['job_title']); ?></small>
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
                                                <i class="fas fa-receipt me-1"></i>
                                                Total: <?php echo $c['currency']; ?> <?php echo number_format($c['total_amount']); ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if ($c['agency_commission_amount']): ?>
                                            <span class="badge bg-warning text-dark">
                                                Commission: <?php echo $c['currency']; ?> <?php echo number_format($c['agency_commission_amount']); ?>
                                                (<?php echo $c['agency_commission_percentage']; ?>%)
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $meta['class']; ?> px-2 py-1"><?php echo $meta['label']; ?></span>
                                        <div class="text-muted small mt-1"><?php echo timeAgo($c['created_at']); ?></div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <?php if ($c['status'] === CONTRACT_STATUS_ACTIVE): ?>
                                <div class="d-flex flex-wrap gap-2 mt-3">
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Mark this contract as completed?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="contract_id" value="<?php echo $c['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo CONTRACT_STATUS_COMPLETED; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-check-circle me-1"></i>Mark Completed
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline"
                                          onsubmit="return confirm('Terminate this contract?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="contract_id" value="<?php echo $c['id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo CONTRACT_STATUS_TERMINATED; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-ban me-1"></i>Terminate
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="modal" data-bs-target="#editModal"
                                            data-id="<?php echo $c['id']; ?>"
                                            data-end="<?php echo htmlspecialchars($c['end_date'] ?? ''); ?>"
                                            data-total="<?php echo htmlspecialchars($c['total_amount'] ?? ''); ?>">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </button>
                                </div>
                                <?php endif; ?>

                                <?php if ($c['contract_document_url']): ?>
                                <div class="mt-2">
                                    <a href="<?php echo SITE_URL; ?>/public/serve-file.php?file=<?php echo urlencode($c['contract_document_url']); ?>"
                                       target="_blank" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-file-pdf me-1"></i>Contract Document
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

<!-- Edit Contract Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="update_contract">
                <input type="hidden" name="contract_id" id="edit_contract_id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Contract</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" id="edit_end_date" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Total Amount (<?php echo DEFAULT_CURRENCY; ?>)</label>
                        <input type="number" name="total_amount" id="edit_total_amount" class="form-control" min="0" step="0.01">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('edit_contract_id').value = btn.dataset.id;
    document.getElementById('edit_end_date').value = btn.dataset.end;
    document.getElementById('edit_total_amount').value = btn.dataset.total;
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
