<?php
// public/admin/logs.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';

requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$db  = new Database($pdo);

// Handle POST: clear logs (super admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirect(SITE_URL . '/public/admin/logs.php');
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'clear_old' && getCurrentUserRole() === ROLE_SUPER_ADMIN) {
        $days    = max(7, (int)($_POST['days'] ?? 30));
        $deleted = $db->fetchColumn(
            "SELECT COUNT(*) FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        $db->query(
            "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)",
            [$days]
        );
        setFlash('success', "Deleted {$deleted} log entries older than {$days} days.");
    }
    redirect(SITE_URL . '/public/admin/logs.php');
}

// Filters
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 50;
$offset        = ($page - 1) * $per_page;
$filter_action = trim($_GET['action_type'] ?? '');
$filter_user   = (int)($_GET['user_id'] ?? 0);
$filter_date   = $_GET['date'] ?? '';

$where_parts = ['1=1'];
$params      = [];

if ($filter_action) {
    $where_parts[] = "l.action = ?";
    $params[]      = $filter_action;
}
if ($filter_user) {
    $where_parts[] = "l.user_id = ?";
    $params[]      = $filter_user;
}
if ($filter_date) {
    $where_parts[] = "DATE(l.created_at) = ?";
    $params[]      = $filter_date;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_parts);

$total = $db->fetchColumn(
    "SELECT COUNT(*) FROM activity_logs l $where_sql",
    $params
);

// JOIN users to get email + role for display (not stored in logs table)
$logs = $db->fetchAll(
    "SELECT l.id, l.user_id, l.action, l.description, l.ip_address, l.user_agent, l.created_at,
            u.email AS user_email, u.role AS user_role
     FROM activity_logs l
     LEFT JOIN users u ON u.id = l.user_id
     $where_sql
     ORDER BY l.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$per_page, $offset])
);

$pagination = getPagination($total, $page, $per_page);

// Stats
$stats = [
    'total'  => $db->fetchColumn("SELECT COUNT(*) FROM activity_logs"),
    'today'  => $db->fetchColumn("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()"),
    'week'   => $db->fetchColumn("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"),
    'errors' => $db->fetchColumn("SELECT COUNT(*) FROM activity_logs WHERE action LIKE 'error%' OR action LIKE '%fail%'"),
];

// Distinct action types for filter dropdown
$action_types = $db->fetchAll(
    "SELECT DISTINCT action FROM activity_logs ORDER BY action ASC"
);

// Top actions this week
$action_summary = $db->fetchAll(
    "SELECT action, COUNT(*) AS count
     FROM activity_logs
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     GROUP BY action
     ORDER BY count DESC
     LIMIT 10"
);

function logActionColor($action) {
    $action = strtolower($action);
    if (str_contains($action, 'delete') || str_contains($action, 'fail') || str_contains($action, 'error'))       return 'danger';
    if (str_contains($action, 'suspend') || str_contains($action, 'reject') || str_contains($action, 'terminat')) return 'warning';
    if (str_contains($action, 'create') || str_contains($action, 'register') || str_contains($action, 'approve')) return 'success';
    if (str_contains($action, 'login') || str_contains($action, 'update') || str_contains($action, 'edit'))       return 'info';
    return 'secondary';
}

$page_title     = 'Activity Logs - ' . SITE_NAME;
$body_class     = 'dashboard-page admin-dashboard';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="mb-0">Activity Logs</h2>
                    <p class="text-muted mb-0">Audit trail of all platform actions</p>
                </div>
                <?php if (getCurrentUserRole() === ROLE_SUPER_ADMIN): ?>
                    <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                        <i class="fas fa-trash"></i> Clear Old Logs
                    </button>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-primary"><?php echo number_format($stats['total']); ?></h4>
                            <small class="text-muted">Total Entries</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-success"><?php echo number_format($stats['today']); ?></h4>
                            <small class="text-muted">Today</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-info"><?php echo number_format($stats['week']); ?></h4>
                            <small class="text-muted">This Week</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center border-danger">
                        <div class="card-body py-3">
                            <h4 class="text-danger"><?php echo number_format($stats['errors']); ?></h4>
                            <small class="text-muted">Errors / Failures</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">

                <!-- Filters -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row g-2 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small">User ID</label>
                                    <input type="number" name="user_id" class="form-control form-control-sm"
                                           placeholder="e.g. 42"
                                           value="<?php echo $filter_user ?: ''; ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Action</label>
                                    <select name="action_type" class="form-select form-select-sm">
                                        <option value="">All Actions</option>
                                        <?php foreach ($action_types as $at): ?>
                                            <option value="<?php echo htmlspecialchars($at['action']); ?>"
                                                <?php echo $filter_action === $at['action'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($at['action']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small">Date</label>
                                    <input type="date" name="date" class="form-control form-control-sm"
                                           value="<?php echo htmlspecialchars($filter_date); ?>">
                                </div>
                                <div class="col-md-1">
                                    <button type="submit" class="btn btn-primary btn-sm w-100">Go</button>
                                </div>
                                <div class="col-md-2">
                                    <a href="?" class="btn btn-outline-secondary btn-sm w-100">Reset</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Top Actions Summary -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-white">
                            <h6 class="mb-0"><i class="fas fa-chart-bar text-primary"></i> Top Actions (7 days)</h6>
                        </div>
                        <div class="card-body p-0" style="max-height:220px;overflow-y:auto;">
                            <?php if (empty($action_summary)): ?>
                                <div class="text-center py-3 text-muted small">No activity in the last 7 days.</div>
                            <?php else: ?>
                                <?php $max_count = max(array_column($action_summary, 'count')); ?>
                                <?php foreach ($action_summary as $as): ?>
                                    <div class="px-3 py-2 border-bottom">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span class="badge bg-<?php echo logActionColor($as['action']); ?>">
                                                <?php echo htmlspecialchars($as['action']); ?>
                                            </span>
                                            <span class="text-muted"><?php echo $as['count']; ?></span>
                                        </div>
                                        <div class="progress" style="height:4px;">
                                            <div class="progress-bar bg-<?php echo logActionColor($as['action']); ?>"
                                                 style="width:<?php echo round(($as['count'] / $max_count) * 100); ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <?php flashMessage(); ?>

            <!-- Logs Table -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Log Entries <span class="badge bg-secondary"><?php echo number_format($total); ?></span></h5>
                    <small class="text-muted">Showing <?php echo count($logs); ?> of <?php echo number_format($total); ?></small>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-history fa-3x mb-3"></i>
                            <p>No log entries found.</p>
                            <?php if ($stats['total'] == 0): ?>
                                <small>Activity logs will appear here once users start performing actions.</small>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size:13px;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width:150px;">Time</th>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Description</th>
                                        <th>IP Address</th>
                                        <th>User Agent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <div class="small"><?php echo date('d M Y', strtotime($log['created_at'])); ?></div>
                                                <div class="text-muted" style="font-size:11px;"><?php echo date('H:i:s', strtotime($log['created_at'])); ?></div>
                                            </td>
                                            <td>
                                                <?php if ($log['user_email']): ?>
                                                    <div class="small fw-semibold"><?php echo htmlspecialchars($log['user_email']); ?></div>
                                                    <?php if ($log['user_role']): ?>
                                                        <span class="badge bg-light text-dark border" style="font-size:10px;">
                                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['user_role']))); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php elseif ($log['user_id']): ?>
                                                    <span class="text-muted small">User #<?php echo $log['user_id']; ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted small">System</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo logActionColor($log['action']); ?>">
                                                    <?php echo htmlspecialchars($log['action']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($log['description']): ?>
                                                    <small class="text-muted"
                                                           title="<?php echo htmlspecialchars($log['description']); ?>">
                                                        <?php echo htmlspecialchars(truncate($log['description'], 80)); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small class="text-muted font-monospace">
                                                    <?php echo htmlspecialchars($log['ip_address'] ?? '—'); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($log['user_agent']): ?>
                                                    <small class="text-muted" style="font-size:11px;"
                                                           title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                                        <?php echo htmlspecialchars(truncate($log['user_agent'], 40)); ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
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
                        $base = '?' . http_build_query(array_filter([
                            'user_id'     => $filter_user ?: null,
                            'action_type' => $filter_action,
                            'date'        => $filter_date,
                        ]));
                        echo renderPagination($pagination, $base . '&');
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Clear Logs Modal (super admin only) -->
<?php if (getCurrentUserRole() === ROLE_SUPER_ADMIN): ?>
<div class="modal fade" id="clearLogsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="clear_old">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-trash"></i> Clear Old Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <strong>Warning:</strong> This permanently deletes log entries and cannot be undone.
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Delete logs older than</label>
                        <div class="input-group" style="max-width:160px;">
                            <input type="number" name="days" class="form-control" value="30" min="7" max="365">
                            <span class="input-group-text">days</span>
                        </div>
                        <small class="text-muted">Minimum: 7 days. Total logs: <strong><?php echo number_format($stats['total']); ?></strong></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Delete old logs permanently?')">
                        <i class="fas fa-trash"></i> Delete Logs
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
