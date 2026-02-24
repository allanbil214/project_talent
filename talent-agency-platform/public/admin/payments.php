<?php
// public/admin/payments.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Payment.php';
require_once __DIR__ . '/../../classes/Contract.php';

requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$db  = new Database($pdo);
$payment = new Payment($db);
$contract = new Contract($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirect(SITE_URL . '/public/admin/payments.php');
    }
    $action     = $_POST['action'] ?? '';
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    try {
        if ($action === 'mark_completed' && $payment_id > 0) {
            $payment->updateStatus($payment_id, 'completed');
            setFlash('success', 'Payment marked as completed.');
        } elseif ($action === 'mark_failed' && $payment_id > 0) {
            $payment->updateStatus($payment_id, 'failed');
            setFlash('success', 'Payment marked as failed.');
        } elseif ($action === 'refund' && $payment_id > 0) {
            $payment->updateStatus($payment_id, 'refunded');
            setFlash('success', 'Payment refunded.');
        } elseif ($action === 'create') {
            $contract_id       = (int)($_POST['contract_id'] ?? 0);
            $amount            = (float)($_POST['amount'] ?? 0);
            $agency_commission = (float)($_POST['agency_commission'] ?? 0);
            $payment_method    = $_POST['payment_method'] ?? 'manual';
            $notes             = trim($_POST['notes'] ?? '');
            $payment->create($contract_id, $amount, $agency_commission, $payment_method, $notes ?: null);;
            setFlash('success', 'Payment recorded.');
        }
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }
    redirect(SITE_URL . '/public/admin/payments.php');
}

$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 20;
$offset        = ($page - 1) * $per_page;
$status_filter = $_GET['status'] ?? '';
$search_filter = $_GET['search'] ?? '';

$where_clauses = [];
$params        = [];
if ($status_filter) { $where_clauses[] = "p.status = ?"; $params[] = $status_filter; }
if ($search_filter) {
    $where_clauses[] = "(t.full_name LIKE ? OR e.company_name LIKE ?)";
    $s = '%' . $search_filter . '%';
    $params[] = $s; $params[] = $s;
}
$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

$result     = $payment->getAll($page, $per_page, ['status' => $status_filter, 'search' => $search_filter]);
$payments   = $result['data'];
$pagination = $result['pagination'];

$stats = $payment->getAdminStats();

$monthly = $payment->getMonthlyRevenue();

$active_contracts = $contract->getActiveForPayment();

$page_title = 'Payments - ' . SITE_NAME;
$body_class = 'dashboard-page admin-dashboard';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div><h2 class="mb-0">Payments</h2><p class="text-muted mb-0">Track revenue, commissions, and payment status</p></div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordPaymentModal"><i class="fas fa-plus"></i> Record Payment</button>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-2"><div class="card text-center"><div class="card-body py-3"><h6 class="text-primary mb-0"><?php echo formatCurrency($stats['total_paid']); ?></h6><small class="text-muted">Total Collected</small></div></div></div>
                <div class="col-6 col-lg-2"><div class="card text-center border-success"><div class="card-body py-3"><h6 class="text-success mb-0"><?php echo formatCurrency($stats['total_commission']); ?></h6><small class="text-muted">Agency Revenue</small></div></div></div>
                <div class="col-6 col-lg-2"><div class="card text-center"><div class="card-body py-3"><h6 class="text-warning mb-0"><?php echo formatCurrency($stats['pending_amount']); ?></h6><small class="text-muted">Pending (<?php echo $stats['pending_count']; ?>)</small></div></div></div>
                <div class="col-6 col-lg-2"><div class="card text-center"><div class="card-body py-3"><h6 class="text-danger mb-0"><?php echo formatCurrency($stats['refunded']); ?></h6><small class="text-muted">Refunded</small></div></div></div>
                <div class="col-6 col-lg-2"><div class="card text-center"><div class="card-body py-3"><h6 class="text-dark mb-0"><?php echo $stats['total_count']; ?></h6><small class="text-muted">Transactions</small></div></div></div>
                <div class="col-6 col-lg-2"><div class="card text-center"><div class="card-body py-3">
                    <?php $avg = $stats['total_paid'] > 0 ? round(($stats['total_commission']/$stats['total_paid'])*100,1) : 0; ?>
                    <h6 class="text-info mb-0"><?php echo $avg; ?>%</h6><small class="text-muted">Avg Commission</small>
                </div></div></div>
            </div>

            <?php if (!empty($monthly)): ?>
            <div class="card mb-4">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="fas fa-chart-bar text-primary"></i> Monthly Revenue (Last 6 Months)</h5></div>
                <div class="card-body"><canvas id="revenueChart" height="60"></canvas></div>
            </div>
            <?php endif; ?>

            <div class="card mb-4"><div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Search talent or company..." value="<?php echo htmlspecialchars($search_filter); ?>"></div>
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="pending"   <?php echo $status_filter==='pending'   ?'selected':''; ?>>Pending</option>
                            <option value="completed" <?php echo $status_filter==='completed' ?'selected':''; ?>>Completed</option>
                            <option value="failed"    <?php echo $status_filter==='failed'    ?'selected':''; ?>>Failed</option>
                            <option value="refunded"  <?php echo $status_filter==='refunded'  ?'selected':''; ?>>Refunded</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                    <div class="col-md-2"><a href="?" class="btn btn-outline-secondary w-100">Reset</a></div>
                </form>
            </div></div>

            <?php flashMessage(); ?>

            <div class="card">
                <div class="card-header bg-white"><h5 class="mb-0">Transactions <span class="badge bg-secondary"><?php echo $total; ?></span></h5></div>
                <div class="card-body p-0">
                    <?php if (empty($payments)): ?>
                        <div class="text-center py-5 text-muted"><i class="fas fa-credit-card fa-3x mb-3"></i><p>No payments found.</p></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>#</th><th>Job</th><th>Company</th><th>Talent</th><th>Amount</th><th>Commission</th><th>Method</th><th>Date</th><th>Status</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $p): ?>
                                        <tr>
                                            <td><small class="text-muted">#<?php echo $p['id']; ?></small></td>
                                            <td><small class="fw-semibold"><?php echo htmlspecialchars($p['job_title']); ?></small><br><small class="text-muted">Contract #<?php echo $p['contract_id']; ?></small></td>
                                            <td><small><?php echo htmlspecialchars($p['company_name']); ?></small></td>
                                            <td><small><?php echo htmlspecialchars($p['talent_name']); ?></small></td>
                                            <td><strong><?php echo formatCurrency($p['amount']); ?></strong></td>
                                            <td><span class="text-success"><?php echo formatCurrency($p['agency_commission'] ?? 0); ?></span></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo ucfirst($p['payment_method'] ?? 'manual'); ?></span></td>
                                            <td><small><?php echo formatDate($p['paid_at'] ?? $p['created_at']); ?></small></td>
                                            <td>
                                                <?php $pcolors=['pending'=>'warning','completed'=>'success','failed'=>'danger','refunded'=>'secondary']; ?>
                                                <span class="badge bg-<?php echo $pcolors[$p['status']]??'secondary'; ?>"><?php echo ucfirst($p['status']); ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <?php if ($p['status']==='pending'): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                                            <input type="hidden" name="action" value="mark_completed">
                                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Mark Completed"><i class="fas fa-check"></i></button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                                            <input type="hidden" name="action" value="mark_failed">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Mark Failed"><i class="fas fa-times"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if ($p['status']==='completed'): ?>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Issue refund?')">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="payment_id" value="<?php echo $p['id']; ?>">
                                                            <input type="hidden" name="action" value="refund">
                                                            <button type="submit" class="btn btn-sm btn-outline-warning" title="Refund"><i class="fas fa-undo"></i></button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <?php if (!empty($p['notes'])): ?>
                                                        <button class="btn btn-sm btn-outline-secondary" title="<?php echo htmlspecialchars($p['notes']); ?>"><i class="fas fa-sticky-note"></i></button>
                                                    <?php endif; ?>
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
                        <?php $base = '?'.http_build_query(array_filter(['status'=>$status_filter,'search'=>$search_filter])); echo renderPagination($pagination, $base.'&'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Record Payment Modal -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="create">
            <div class="modal-header"><h5 class="modal-title">Record Manual Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Contract <span class="text-danger">*</span></label>
                    <select name="contract_id" class="form-select" required id="contractSelect" onchange="prefillCommission(this)">
                        <option value="">Select active contract...</option>
                        <?php foreach ($active_contracts as $ac): ?>
                            <option value="<?php echo $ac['id']; ?>" data-commission="<?php echo $ac['agency_commission_percentage']; ?>">
                                #<?php echo $ac['id']; ?> — <?php echo htmlspecialchars($ac['job_title']); ?> (<?php echo htmlspecialchars($ac['talent_name']); ?> @ <?php echo htmlspecialchars($ac['company_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Amount <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text">Rp</span><input type="number" name="amount" id="paymentAmount" class="form-control" required min="1" step="1000" oninput="calcCommission()"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Agency Commission</label>
                        <div class="input-group"><span class="input-group-text">Rp</span><input type="number" name="agency_commission" id="paymentCommission" class="form-control" step="1000" min="0"></div>
                        <small class="text-muted" id="commissionNote"></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Payment Method</label>
                        <select name="payment_method" class="form-select">
                            <option value="manual">Manual / Cash</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="ewallet">E-Wallet</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional...">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Record Payment</button>
            </div>
        </form>
    </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($monthly)): ?>
new Chart(document.getElementById('revenueChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($monthly,'month')); ?>,
        datasets: [
            { label: 'Revenue',    data: <?php echo json_encode(array_map(fn($m)=>(float)$m['revenue'],    $monthly)); ?>, backgroundColor: 'rgba(13,110,253,0.6)' },
            { label: 'Commission', data: <?php echo json_encode(array_map(fn($m)=>(float)$m['commission'], $monthly)); ?>, backgroundColor: 'rgba(25,135,84,0.6)' }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
});
<?php endif; ?>
function prefillCommission(select) {
    const pct = parseFloat(select.options[select.selectedIndex].dataset.commission) || 0;
    document.getElementById('commissionNote').textContent = pct ? `Auto-calc at ${pct}%` : '';
    calcCommission();
}
function calcCommission() {
    const select = document.getElementById('contractSelect');
    const pct    = parseFloat(select.options[select.selectedIndex].dataset.commission) || 0;
    const amount = parseFloat(document.getElementById('paymentAmount').value) || 0;
    if (pct && amount) document.getElementById('paymentCommission').value = Math.round(amount * pct / 100);
}
</script>
<?php $additional_js = ['dashboard.js']; require_once __DIR__ . '/../../includes/footer.php'; ?>
