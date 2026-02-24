<?php
// public/admin/jobs.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Job.php';

requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$db  = new Database($pdo);
$job = new Job($db);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirect(SITE_URL . '/public/admin/jobs.php');
    }

    $action = $_POST['action'] ?? '';
    $job_id = (int)($_POST['job_id'] ?? 0);

    if ($job_id > 0) {
        try {
            if ($action === 'approve') {
                $job->updateStatus($job_id, JOB_STATUS_ACTIVE);
                // Notify employer via email (optional - requires Mail class)
                setFlash('success', 'Job approved and is now live.');
            } elseif ($action === 'reject') {
                $job->updateStatus($job_id, JOB_STATUS_CLOSED);
                setFlash('success', 'Job rejected.');
            } elseif ($action === 'close') {
                $job->updateStatus($job_id, JOB_STATUS_CLOSED);
                setFlash('success', 'Job closed.');
            } elseif ($action === 'delete') {
                $job->delete($job_id);
                setFlash('success', 'Job deleted.');
            }
        } catch (Exception $e) {
            setFlash('error', $e->getMessage());
        }
    }
    redirect(SITE_URL . '/public/admin/jobs.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// Filters
$page    = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'status'    => $_GET['status'] ?? '',
    'search'    => $_GET['search'] ?? '',
    'job_type'  => $_GET['job_type'] ?? '',
];

$result = $job->getAll($page, 20, $filters);
$jobs   = $result['data'];
$pagination = $result['pagination'];
$stats = $job->getAdminStats();

$page_title  = 'Manage Jobs - ' . SITE_NAME;
$body_class  = 'dashboard-page admin-dashboard';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">Manage Jobs</h2>
                    <p class="text-muted mb-0">Review, approve, and manage all job postings</p>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <?php
                $stat_items = [
                    ['label' => 'Total',   'key' => 'total',   'color' => 'primary',  'icon' => 'fa-briefcase'],
                    ['label' => 'Pending', 'key' => 'pending', 'color' => 'warning',  'icon' => 'fa-clock'],
                    ['label' => 'Active',  'key' => 'active',  'color' => 'success',  'icon' => 'fa-check-circle'],
                    ['label' => 'Filled',  'key' => 'filled',  'color' => 'info',     'icon' => 'fa-handshake'],
                    ['label' => 'Closed',  'key' => 'closed',  'color' => 'secondary','icon' => 'fa-times-circle'],
                    ['label' => 'Deleted', 'key' => 'deleted', 'color' => 'danger',   'icon' => 'fa-trash'],
                ];
                foreach ($stat_items as $s): ?>
                    <div class="col-6 col-lg-auto flex-lg-fill">
                    <a href="?status=<?php echo $s['key'] === 'total' ? '' : ($s['key'] === 'pending' ? 'pending_approval' : $s['key']); ?>" class="text-decoration-none">
                            <div class="card text-center border-<?php echo $s['color']; ?> border-top border-top-3">
                                <div class="card-body py-3">
                                    <div class="text-<?php echo $s['color']; ?> mb-1"><i class="fas <?php echo $s['icon']; ?> fa-lg"></i></div>
                                    <h4 class="mb-0"><?php echo $stats[$s['key']]; ?></h4>
                                    <small class="text-muted"><?php echo $s['label']; ?></small>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search title or description..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="pending_approval" <?php echo $filters['status'] === 'pending_approval' ? 'selected' : ''; ?>>Pending Approval</option>
                                <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="filled" <?php echo $filters['status'] === 'filled' ? 'selected' : ''; ?>>Filled</option>
                                <option value="closed" <?php echo $filters['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                <option value="draft" <?php echo $filters['status'] === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                <option value="deleted" <?php echo $filters['status'] === 'deleted' ? 'selected' : ''; ?>>Deleted</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="job_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="full-time" <?php echo $filters['job_type'] === 'full-time' ? 'selected' : ''; ?>>Full-time</option>
                                <option value="part-time" <?php echo $filters['job_type'] === 'part-time' ? 'selected' : ''; ?>>Part-time</option>
                                <option value="contract" <?php echo $filters['job_type'] === 'contract' ? 'selected' : ''; ?>>Contract</option>
                                <option value="freelance" <?php echo $filters['job_type'] === 'freelance' ? 'selected' : ''; ?>>Freelance</option>
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

            <!-- Jobs Table -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Jobs <span class="badge bg-secondary"><?php echo $pagination['total']; ?></span></h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($jobs)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-briefcase fa-3x mb-3"></i>
                            <p>No jobs found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Job</th>
                                        <th>Company</th>
                                        <th>Type</th>
                                        <th>Salary</th>
                                        <th>Apps</th>
                                        <th>Status</th>
                                        <th>Posted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jobs as $j): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars($j['title']); ?></div>
                                                <small class="text-muted">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo ucfirst($j['location_type']); ?>
                                                    <?php if ($j['location_address']): ?> &middot; <?php echo htmlspecialchars($j['location_address']); ?><?php endif; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <?php if ($j['company_logo_url']): ?>
                                                        <img src="<?php echo getCompanyLogoUrl($j['company_logo_url']); ?>" width="30" height="30" class="rounded" style="object-fit:cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary rounded d-flex align-items-center justify-content-center text-white" style="width:30px;height:30px;font-size:12px;">
                                                            <?php echo strtoupper(substr($j['company_name'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span><?php echo htmlspecialchars($j['company_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><span class="badge bg-light text-dark"><?php echo ucfirst($j['job_type']); ?></span></td>
                                            <td>
                                                <small><?php echo formatSalaryRange($j['salary_min'], $j['salary_max'], $j['salary_type']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info text-white"><?php echo (int)$j['application_count']; ?></span>
                                            </td>
                                            <td><?php echo getJobStatusBadge($j['status']); ?></td>
                                            <td><small class="text-muted"><?php echo formatDate($j['created_at']); ?></small></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <?php if ($j['status'] === JOB_STATUS_PENDING): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="job_id" value="<?php echo $j['id']; ?>">
                                                            <input type="hidden" name="action" value="approve">
                                                            <button type="submit" class="btn btn-success btn-sm" title="Approve">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="job_id" value="<?php echo $j['id']; ?>">
                                                            <input type="hidden" name="action" value="reject">
                                                            <button type="submit" class="btn btn-danger btn-sm" title="Reject">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($j['status'] === JOB_STATUS_ACTIVE): ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="job_id" value="<?php echo $j['id']; ?>">
                                                            <input type="hidden" name="action" value="close">
                                                            <button type="submit" class="btn btn-warning btn-sm" title="Close Job">
                                                                <i class="fas fa-ban"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-primary btn-sm"
                                                            onclick="viewJobDetail(<?php echo $j['id']; ?>)"
                                                            title="View Detail">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this job? This cannot be undone.')">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="job_id" value="<?php echo $j['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
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
                        $base = '?' . http_build_query(array_filter(['status' => $filters['status'], 'search' => $filters['search'], 'job_type' => $filters['job_type']]));
                        echo renderPagination($pagination, $base . '&'); 
                        ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Job Detail Modal -->
<div class="modal fade" id="jobDetailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Job Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="jobDetailBody">
                <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
function viewJobDetail(jobId) {
    const modal = new bootstrap.Modal(document.getElementById('jobDetailModal'));
    const body = document.getElementById('jobDetailBody');
    body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
    modal.show();

    fetch(`${SITE_URL}/api/jobs.php?action=get&id=${jobId}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && data.job) {
            const j = data.job;
            body.innerHTML = `
                <h4>${j.title}</h4>
                <p class="text-muted">${j.company_name} &middot; ${j.location_type} ${j.location_address ? '&middot; ' + j.location_address : ''}</p>
                <div class="row mb-3">
                    <div class="col-sm-6"><strong>Type:</strong> ${j.job_type}</div>
                    <div class="col-sm-6"><strong>Status:</strong> ${j.status}</div>
                    <div class="col-sm-6"><strong>Experience:</strong> ${j.experience_required ?? 0} years</div>
                    <div class="col-sm-6"><strong>Deadline:</strong> ${j.deadline ?? 'Open'}</div>
                </div>
                <h6>Description</h6>
                <div class="border rounded p-3 bg-light" style="max-height:200px;overflow-y:auto">${j.description}</div>
                ${j.skills && j.skills.length ? '<h6 class="mt-3">Required Skills</h6><div>' + j.skills.map(s => `<span class="badge bg-primary me-1">${s.name}</span>`).join('') + '</div>' : ''}
            `;
        } else {
            body.innerHTML = '<p class="text-danger">Could not load job details.</p>';
        }
    })
    .catch(() => { body.innerHTML = '<p class="text-danger">Error loading data.</p>'; });
}
</script>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
