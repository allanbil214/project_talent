<?php
// public/admin/users.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/User.php';

requireAdmin();

$pdo  = require __DIR__ . '/../../config/database.php';
$db   = new Database($pdo);
$user = new User($db);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirect(SITE_URL . '/public/admin/users.php');
    }

    $action  = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);
    $current = getCurrentUserId();

    if ($user_id === $current && in_array($action, ['suspend', 'delete'])) {
        setFlash('error', 'You cannot suspend or delete your own account.');
        redirect(SITE_URL . '/public/admin/users.php');
    }

    try {
        if ($action === 'activate') {
            $user->updateStatus($user_id, STATUS_ACTIVE);
            setFlash('success', 'User activated.');
        } elseif ($action === 'suspend') {
            $user->updateStatus($user_id, STATUS_SUSPENDED);
            setFlash('success', 'User suspended.');
        } elseif ($action === 'delete') {
            // Soft delete — just deactivate
            $user->updateStatus($user_id, STATUS_INACTIVE);
            setFlash('success', 'User deactivated (soft delete).');
        } elseif ($action === 'reset_password' && !empty($_POST['new_password'])) {
            if (strlen($_POST['new_password']) < 8) {
                throw new Exception('Password must be at least 8 characters.');
            }
            $user->updatePassword($user_id, $_POST['new_password']);
            setFlash('success', 'Password updated.');
        }
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }

    redirect(SITE_URL . '/public/admin/users.php');
}

// Filters & pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'role'   => $_GET['role']   ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? '',
];

$result     = $user->getAll($page, 25, $filters);
$users      = $result['data'];
$pagination = $result['pagination'];

// Stats
$stats = $user->getStats();

$page_title     = 'Manage Users - ' . SITE_NAME;
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
                    <h2 class="mb-0">Manage Users</h2>
                    <p class="text-muted mb-0">All user accounts across the platform</p>
                </div>
            </div>

            <!-- Stat Cards -->
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
                            <h4 class="text-info"><?php echo $stats['by_role']['talents']; ?></h4>
                            <small class="text-muted">Talents</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-warning"><?php echo $stats['by_role']['employers']; ?></h4>
                            <small class="text-muted">Employers</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-secondary"><?php echo $stats['by_role']['staff']; ?></h4>
                            <small class="text-muted">Staff</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-dark"><?php echo $stats['by_role']['admins']; ?></h4>
                            <small class="text-muted">Admins</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search by email..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="role" class="form-select">
                                <option value="">All Roles</option>
                                <option value="talent"      <?php echo $filters['role'] === 'talent'      ? 'selected' : ''; ?>>Talent</option>
                                <option value="employer"    <?php echo $filters['role'] === 'employer'    ? 'selected' : ''; ?>>Employer</option>
                                <option value="staff"       <?php echo $filters['role'] === 'staff'       ? 'selected' : ''; ?>>Staff</option>
                                <option value="super_admin" <?php echo $filters['role'] === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="active"    <?php echo $filters['status'] === 'active'    ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive"  <?php echo $filters['status'] === 'inactive'  ? 'selected' : ''; ?>>Inactive</option>
                                <option value="suspended" <?php echo $filters['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
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

            <!-- Users Table -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Users <span class="badge bg-secondary"><?php echo $pagination['total']; ?></span></h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($users)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <p>No users found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $u): ?>
                                        <tr <?php echo $u['id'] === getCurrentUserId() ? 'class="table-active"' : ''; ?>>
                                            <td><small class="text-muted">#<?php echo $u['id']; ?></small></td>
                                            <td>
                                                <?php echo htmlspecialchars($u['email']); ?>
                                                <?php if ($u['id'] === getCurrentUserId()): ?>
                                                    <span class="badge bg-light text-dark border">You</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $role_colors = [
                                                    'super_admin' => 'danger',
                                                    'staff'       => 'warning',
                                                    'employer'    => 'info',
                                                    'talent'      => 'primary',
                                                ];
                                                $rc = $role_colors[$u['role']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $rc; ?>"><?php echo ucfirst(str_replace('_', ' ', $u['role'])); ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $sc = ['active' => 'success', 'suspended' => 'danger', 'inactive' => 'secondary'];
                                                $scolor = $sc[$u['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $scolor; ?>"><?php echo ucfirst($u['status']); ?></span>
                                            </td>
                                            <td><small class="text-muted"><?php echo formatDate($u['created_at']); ?></small></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <?php if ($u['id'] !== getCurrentUserId()): ?>
                                                        <!-- Suspend / Activate -->
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                            <input type="hidden" name="action" value="<?php echo $u['status'] === STATUS_SUSPENDED ? 'activate' : 'suspend'; ?>">
                                                            <button type="submit" class="btn btn-sm <?php echo $u['status'] === STATUS_SUSPENDED ? 'btn-outline-success' : 'btn-outline-danger'; ?>">
                                                                <i class="fas <?php echo $u['status'] === STATUS_SUSPENDED ? 'fa-user-check' : 'fa-user-slash'; ?>"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    <!-- Reset Password -->
                                                    <button type="button" class="btn btn-sm btn-outline-warning"
                                                            onclick="showResetPassword(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['email'])); ?>')"
                                                            title="Reset Password">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <!-- Profile link -->
                                                    <?php if ($u['role'] === 'talent'): ?>
                                                        <a href="<?php echo SITE_URL; ?>/public/admin/talents.php" class="btn btn-sm btn-outline-info" title="Go to Talents">
                                                            <i class="fas fa-user"></i>
                                                        </a>
                                                    <?php elseif ($u['role'] === 'employer'): ?>
                                                        <a href="<?php echo SITE_URL; ?>/public/admin/employers.php" class="btn btn-sm btn-outline-info" title="Go to Employers">
                                                            <i class="fas fa-building"></i>
                                                        </a>
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
                        <?php
                        $base = '?' . http_build_query(array_filter(['role' => $filters['role'], 'status' => $filters['status'], 'search' => $filters['search']]));
                        echo renderPagination($pagination, $base . '&');
                        ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUserId">
                <div class="modal-header">
                    <h5 class="modal-title">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Resetting password for: <strong id="resetUserEmail"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="8" placeholder="Min. 8 characters">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showResetPassword(userId, email) {
    document.getElementById('resetUserId').value  = userId;
    document.getElementById('resetUserEmail').textContent = email;
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}
</script>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
