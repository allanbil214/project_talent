<?php
// public/admin/contracts.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Contract.php';

requireAdmin();

$pdo      = require __DIR__ . '/../../config/database.php';
$db       = new Database($pdo);
$contract = new Contract($db);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirect(SITE_URL . '/public/admin/contracts.php');
    }

    $action      = $_POST['action'] ?? '';
    $contract_id = (int)($_POST['contract_id'] ?? 0);

    try {
        if (in_array($action, ['completed', 'terminated', 'active'])) {
            $contract->updateStatus($contract_id, $action);
            setFlash('success', 'Contract status updated to ' . $action . '.');
        }
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
    redirect(SITE_URL . '/public/admin/contracts.php');
}

// Filters & pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$filters = ['status' => $_GET['status'] ?? ''];

$result     = $contract->getAll($page, 20, $filters);
$contracts  = $result['data'];
$pagination = $result['pagination'];

// Stats
$stats = $contract->getStats('all');

$page_title     = 'Manage Contracts - ' . SITE_NAME;
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
                    <h2 class="mb-0">Manage Contracts</h2>
                    <p class="text-muted mb-0">Track all placements and contracts</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-2">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                            <small class="text-muted">Total</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-success"><?php echo $stats['active']; ?></h4>
                            <small class="text-muted">Active</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-info"><?php echo $stats['completed']; ?></h4>
                            <small class="text-muted">Completed</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-danger"><?php echo $stats['terminated']; ?></h4>
                            <small class="text-muted">Terminated</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h5 class="text-dark mb-0"><?php echo formatCurrency($stats['total_value']); ?></h5>
                            <small class="text-muted">Total Value</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card text-center border-success">
                        <div class="card-body py-3">
                            <h5 class="text-success mb-0"><?php echo formatCurrency($stats['total_commission']); ?></h5>
                            <small class="text-muted">Commission</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="active"     <?php echo $filters['status'] === 'active'     ? 'selected' : ''; ?>>Active</option>
                                <option value="completed"  <?php echo $filters['status'] === 'completed'  ? 'selected' : ''; ?>>Completed</option>
                                <option value="terminated" <?php echo $filters['status'] === 'terminated' ? 'selected' : ''; ?>>Terminated</option>
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

            <!-- Contracts Table -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Contracts <span class="badge bg-secondary"><?php echo $pagination['total']; ?></span></h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($contracts)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-file-contract fa-3x mb-3"></i>
                            <p>No contracts found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Job</th>
                                        <th>Talent</th>
                                        <th>Employer</th>
                                        <th>Rate</th>
                                        <th>Total</th>
                                        <th>Commission</th>
                                        <th>Dates</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contracts as $c): ?>
                                        <tr>
                                            <td><small class="text-muted">#<?php echo $c['id']; ?></small></td>
                                            <td>
                                                <div class="fw-semibold small"><?php echo htmlspecialchars($c['job_title']); ?></div>
                                                <small class="text-muted"><?php echo ucfirst($c['job_type']); ?></small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?php echo getAvatarUrl($c['profile_photo_url']); ?>"
                                                         width="30" height="30" class="rounded-circle" style="object-fit:cover;">
                                                    <small><?php echo htmlspecialchars($c['talent_name']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if ($c['company_logo_url']): ?>
                                                        <img src="<?php echo getCompanyLogoUrl($c['company_logo_url']); ?>"
                                                             width="25" height="25" class="rounded" style="object-fit:cover;">
                                                    <?php endif; ?>
                                                    <small><?php echo htmlspecialchars($c['company_name']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?php echo formatCurrency($c['rate']); ?>/<?php echo htmlspecialchars($c['rate_type']); ?></small>
                                            </td>
                                            <td>
                                                <small class="fw-semibold"><?php echo $c['total_amount'] ? formatCurrency($c['total_amount']) : '—'; ?></small>
                                            </td>
                                            <td>
                                                <small class="text-success fw-semibold">
                                                    <?php echo $c['agency_commission_amount'] ? formatCurrency($c['agency_commission_amount']) : '—'; ?>
                                                </small>
                                                <?php if ($c['agency_commission_percentage']): ?>
                                                    <br><small class="text-muted">(<?php echo $c['agency_commission_percentage']; ?>%)</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small>Start: <?php echo formatDate($c['start_date']); ?></small><br>
                                                <small class="text-muted">End: <?php echo $c['end_date'] ? formatDate($c['end_date']) : 'Ongoing'; ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $contract_colors = ['active' => 'success', 'completed' => 'info', 'terminated' => 'danger'];
                                                $cc = $contract_colors[$c['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $cc; ?>"><?php echo ucfirst($c['status']); ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <?php if ($c['status'] === 'active'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="contract_id" value="<?php echo $c['id']; ?>">
                                                            <input type="hidden" name="action" value="completed">
                                                            <button type="submit" class="btn btn-sm btn-outline-info" title="Mark Completed">
                                                                <i class="fas fa-check-double"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline"
                                                              onsubmit="return confirm('Terminate this contract?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="contract_id" value="<?php echo $c['id']; ?>">
                                                            <input type="hidden" name="action" value="terminated">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Terminate">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                            onclick="viewContract(<?php echo $c['id']; ?>)" title="View Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
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
                        $base = '?' . http_build_query(array_filter(['status' => $filters['status']]));
                        echo renderPagination($pagination, $base . '&');
                        ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Contract Detail Modal -->
<div class="modal fade" id="contractDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Contract Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="contractDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
function viewContract(contractId) {
    const modal = new bootstrap.Modal(document.getElementById('contractDetailModal'));
    const body  = document.getElementById('contractDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    modal.show();

    fetch(`${SITE_URL}/api/contracts.php?action=get&id=${contractId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.contract) {
            const c = data.contract;
            body.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Job</h6>
                        <p>${c.job_title} (${c.job_type})</p>
                        <h6>Talent</h6>
                        <p>${c.talent_name}<br><small class="text-muted">${c.talent_email}</small></p>
                        <h6>Employer</h6>
                        <p>${c.company_name}<br><small class="text-muted">${c.employer_email}</small></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Financial</h6>
                        <table class="table table-sm table-bordered">
                            <tr><td>Rate</td><td>${c.rate} / ${c.rate_type}</td></tr>
                            <tr><td>Total Amount</td><td>${c.total_amount ?? '—'}</td></tr>
                            <tr><td>Commission %</td><td>${c.agency_commission_percentage}%</td></tr>
                            <tr><td>Commission Amount</td><td><strong class="text-success">${c.agency_commission_amount ?? '—'}</strong></td></tr>
                        </table>
                        <h6>Dates</h6>
                        <p>Start: ${c.start_date}<br>End: ${c.end_date ?? 'Ongoing'}</p>
                        <h6>Status</h6>
                        <span class="badge bg-${c.status === 'active' ? 'success' : c.status === 'completed' ? 'info' : 'danger'}">${c.status}</span>
                    </div>
                </div>
                ${c.contract_document_url ? '<a href="' + c.contract_document_url + '" target="_blank" class="btn btn-outline-primary btn-sm mt-2"><i class="fas fa-file-pdf"></i> View Document</a>' : ''}
            `;
        } else {
            body.innerHTML = '<p class="text-danger">Could not load contract details.</p>';
        }
    })
    .catch(() => { body.innerHTML = '<p class="text-danger">Error loading data.</p>'; });
}
</script>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
