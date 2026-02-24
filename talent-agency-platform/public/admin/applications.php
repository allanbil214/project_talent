<?php
// public/admin/applications.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Application.php';
require_once __DIR__ . '/../../classes/Contract.php';

requireAdmin();

$pdo         = require __DIR__ . '/../../config/database.php';
$db          = new Database($pdo);
$application = new Application($db);
$contract    = new Contract($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlash('error','Invalid request.'); redirect(SITE_URL.'/public/admin/applications.php'); }
    $action         = $_POST['action'] ?? '';
    $application_id = (int)($_POST['application_id'] ?? 0);
    try {
        if (in_array($action, [APP_STATUS_REVIEWED, APP_STATUS_SHORTLISTED, APP_STATUS_REJECTED, APP_STATUS_ACCEPTED])) {
            $application->updateStatus($application_id, $action);
            setFlash('success', 'Status updated to '.$action.'.');
        } elseif ($action === 'recommend') {
            $application->toggleRecommendation($application_id);
            setFlash('success', 'Recommendation toggled.');
        } elseif ($action === 'create_contract') {
            $app = $application->getById($application_id);
            if (!$app) throw new Exception('Application not found.');
            if ($app['status'] !== APP_STATUS_ACCEPTED) throw new Exception('Application must be accepted first.');
            $contract->create([
                'job_id'         => $app['job_id'],
                'talent_id'      => $app['talent_id'],
                'employer_id'    => $app['employer_id'],
                'application_id' => $application_id,
                'start_date'     => $_POST['start_date'] ?? date('Y-m-d'),
                'end_date'       => $_POST['end_date'] ?: null,
                'rate'           => (float)($_POST['rate'] ?? $app['proposed_rate'] ?? 0),
                'rate_type'      => $_POST['rate_type'] ?? 'monthly',
                'total_amount'   => (float)($_POST['total_amount'] ?? 0) ?: null,
            ]);
            setFlash('success', 'Contract created!');
        }
    } catch (Exception $e) { setFlash('error', $e->getMessage()); }
    redirect(SITE_URL . '/public/admin/applications.php');
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'status'             => $_GET['status'] ?? '',
    'agency_recommended' => (isset($_GET['recommended']) && $_GET['recommended']==='1') ? true : null,
];
$result       = $application->getAll($page, 20, $filters);
$applications = $result['data'];
$pagination   = $result['pagination'];
$stats        = $application->getStats();

$page_title = 'Applications - ' . SITE_NAME;
$body_class = 'dashboard-page admin-dashboard';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div><h2 class="mb-0">Applications</h2><p class="text-muted mb-0">Manage the full application pipeline</p></div>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <?php
                $stat_items = [
                    ['Total','total','primary',''],['Pending','pending','warning','pending'],
                    ['Reviewed','reviewed','info','reviewed'],['Shortlisted','shortlisted','primary','shortlisted'],
                    ['Accepted','accepted','success','accepted'],['Rejected','rejected','danger','rejected'],
                ];
                foreach ($stat_items as $s): ?>
                    <div class="col-4 col-lg-2">
                        <a href="?status=<?php echo $s[3]; ?>" class="text-decoration-none">
                            <div class="card text-center <?php echo $filters['status']===$s[3]&&$s[3]!==''?'border-primary':''; ?>">
                                <div class="card-body py-3">
                                    <h4 class="text-<?php echo $s[2]; ?> mb-0"><?php echo $stats[$s[1]]; ?></h4>
                                    <small class="text-muted"><?php echo $s[0]; ?></small>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Filters -->
            <div class="card mb-4"><div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="pending"     <?php echo $filters['status']==='pending'    ?'selected':''; ?>>Pending</option>
                            <option value="reviewed"    <?php echo $filters['status']==='reviewed'   ?'selected':''; ?>>Reviewed</option>
                            <option value="shortlisted" <?php echo $filters['status']==='shortlisted'?'selected':''; ?>>Shortlisted</option>
                            <option value="accepted"    <?php echo $filters['status']==='accepted'   ?'selected':''; ?>>Accepted</option>
                            <option value="rejected"    <?php echo $filters['status']==='rejected'   ?'selected':''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select name="recommended" class="form-select">
                            <option value="">All Applications</option>
                            <option value="1" <?php echo (isset($_GET['recommended'])&&$_GET['recommended']==='1')?'selected':''; ?>>Agency Recommended</option>
                        </select>
                    </div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                    <div class="col-md-2"><a href="?" class="btn btn-outline-secondary w-100">Reset</a></div>
                </form>
            </div></div>

            <?php flashMessage(); ?>

            <div class="card">
                <div class="card-header bg-white"><h5 class="mb-0">Applications <span class="badge bg-secondary"><?php echo $pagination['total']; ?></span></h5></div>
                <div class="card-body p-0">
                    <?php if (empty($applications)): ?>
                        <div class="text-center py-5 text-muted"><i class="fas fa-file-alt fa-3x mb-3"></i><p>No applications found.</p></div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th>#</th><th>Talent</th><th>Job</th><th>Company</th><th>Rate</th><th>Rec.</th><th>Applied</th><th>Status</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($applications as $a): ?>
                                        <tr>
                                            <td><small class="text-muted">#<?php echo $a['id']; ?></small></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?php echo getAvatarUrl($a['profile_photo_url']); ?>" width="35" height="35" class="rounded-circle" style="object-fit:cover;">
                                                    <small class="fw-semibold"><?php echo htmlspecialchars($a['talent_name']); ?></small>
                                                </div>
                                            </td>
                                            <td><small class="fw-semibold"><?php echo htmlspecialchars($a['job_title']); ?></small><br><small class="text-muted"><?php echo ucfirst($a['job_type']); ?></small></td>
                                            <td><small><?php echo htmlspecialchars($a['company_name']); ?></small></td>
                                            <td><small><?php echo $a['proposed_rate'] ? formatCurrency($a['proposed_rate']) : '—'; ?></small></td>
                                            <td><?php echo $a['agency_recommended'] ? '<i class="fas fa-star text-warning"></i>' : '<span class="text-muted">—</span>'; ?></td>
                                            <td><small class="text-muted"><?php echo timeAgo($a['applied_at']); ?></small></td>
                                            <td><?php echo getApplicationStatusBadge($a['status']); ?></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <div class="dropdown">
                                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"><i class="fas fa-edit"></i></button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <?php
                                                            $statuses = [APP_STATUS_REVIEWED=>'Mark Reviewed',APP_STATUS_SHORTLISTED=>'Shortlist',APP_STATUS_ACCEPTED=>'Accept',APP_STATUS_REJECTED=>'Reject'];
                                                            foreach ($statuses as $s => $label):
                                                                if ($a['status'] === $s) continue;
                                                            ?>
                                                                <li>
                                                                    <form method="POST" class="d-inline">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                        <input type="hidden" name="application_id" value="<?php echo $a['id']; ?>">
                                                                        <input type="hidden" name="action" value="<?php echo $s; ?>">
                                                                        <button type="submit" class="dropdown-item"><?php echo $label; ?></button>
                                                                    </form>
                                                                </li>
                                                            <?php endforeach; ?>
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                    <input type="hidden" name="application_id" value="<?php echo $a['id']; ?>">
                                                                    <input type="hidden" name="action" value="recommend">
                                                                    <button type="submit" class="dropdown-item"><?php echo $a['agency_recommended'] ? 'Remove Recommendation' : '⭐ Recommend'; ?></button>
                                                                </form>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewApplication(<?php echo $a['id']; ?>)" title="View"><i class="fas fa-eye"></i></button>
                                                    <?php if ($a['status'] === APP_STATUS_ACCEPTED): ?>
                                                        <button class="btn btn-sm btn-success" onclick="openContractModal(<?php echo $a['id']; ?>,<?php echo (float)($a['proposed_rate']??0); ?>)" title="Create Contract"><i class="fas fa-file-contract"></i></button>
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
                        <?php $base='?'.http_build_query(array_filter(['status'=>$filters['status']])); echo renderPagination($pagination,$base.'&'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Application Detail Modal -->
<div class="modal fade" id="applicationDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Application Detail</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="applicationDetailBody"><div class="text-center py-4"><div class="spinner-border text-primary"></div></div></div>
    </div></div>
</div>

<!-- Create Contract Modal -->
<div class="modal fade" id="createContractModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="create_contract">
            <input type="hidden" name="application_id" id="contractApplicationId">
            <div class="modal-header"><h5 class="modal-title">Create Contract</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Start Date <span class="text-danger">*</span></label><input type="date" name="start_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="col-md-6"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control"><small class="text-muted">Leave blank for ongoing</small></div>
                    <div class="col-md-6">
                        <label class="form-label">Rate <span class="text-danger">*</span></label>
                        <div class="input-group"><span class="input-group-text">Rp</span><input type="number" name="rate" id="contractRate" class="form-control" required min="1" step="1000"></div>
                    </div>
                    <div class="col-md-6"><label class="form-label">Rate Type</label><select name="rate_type" class="form-select"><option value="monthly">Monthly</option><option value="hourly">Hourly</option><option value="project">Project</option></select></div>
                    <div class="col-12"><label class="form-label">Total Amount</label><div class="input-group"><span class="input-group-text">Rp</span><input type="number" name="total_amount" class="form-control" min="0" step="1000"></div><small class="text-muted">Optional. Used for commission calculation.</small></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-success">Create Contract</button></div>
        </form>
    </div></div>
</div>

<script>
function viewApplication(appId) {
    const modal = new bootstrap.Modal(document.getElementById('applicationDetailModal'));
    const body  = document.getElementById('applicationDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    modal.show();
    fetch(`${SITE_URL}/api/applications.php?action=get&id=${appId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.application) {
            const a = data.application;
            body.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Talent</h6><p class="mb-1"><strong>${a.talent_name}</strong></p><p class="mb-3 text-muted small">${a.talent_email??''}</p>
                        <h6>Job</h6><p class="mb-1"><strong>${a.job_title}</strong></p><p class="mb-0 text-muted small">${a.company_name} &middot; ${a.job_type} &middot; ${a.location_type}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Details</h6>
                        <table class="table table-sm table-bordered">
                            <tr><td>Status</td><td>${a.status}</td></tr>
                            <tr><td>Proposed Rate</td><td>${a.proposed_rate ? 'Rp '+Number(a.proposed_rate).toLocaleString() : '—'}</td></tr>
                            <tr><td>Applied</td><td>${a.applied_at}</td></tr>
                            <tr><td>Agency Rec.</td><td>${a.agency_recommended ? '⭐ Yes' : 'No'}</td></tr>
                        </table>
                        ${a.resume_url ? `<a href="${a.resume_url}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-file-pdf"></i> Resume</a>` : ''}
                    </div>
                </div>
                ${a.cover_letter ? `<hr><h6>Cover Letter</h6><div class="border rounded p-3 bg-light" style="max-height:200px;overflow-y:auto">${a.cover_letter}</div>` : ''}`;
        } else { body.innerHTML = '<p class="text-danger">Could not load details.</p>'; }
    })
    .catch(() => { body.innerHTML = '<p class="text-danger">Error loading data.</p>'; });
}
function openContractModal(appId, rate) {
    document.getElementById('contractApplicationId').value = appId;
    document.getElementById('contractRate').value = rate || '';
    new bootstrap.Modal(document.getElementById('createContractModal')).show();
}
</script>
<?php $additional_js = ['dashboard.js']; require_once __DIR__ . '/../../includes/footer.php'; ?>
