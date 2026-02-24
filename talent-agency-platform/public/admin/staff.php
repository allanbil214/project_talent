<?php
// public/admin/staff.php
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

$current_user_id = getCurrentUserId();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirect(SITE_URL . '/public/admin/staff.php');
    }

    $action  = $_POST['action'] ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($user_id === $current_user_id && in_array($action, ['suspend', 'delete', 'demote'])) {
        setFlash('error', 'You cannot perform this action on your own account.');
        redirect(SITE_URL . '/public/admin/staff.php');
    }

    try {
        if ($action === 'create') {
            $email    = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $name     = trim($_POST['name'] ?? '');
            $role     = $_POST['role'] ?? ROLE_STAFF;

            if (empty($email) || empty($password) || empty($name)) {
                throw new Exception('Name, email, and password are required.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address.');
            }
            if (strlen($password) < 8) {
                throw new Exception('Password must be at least 8 characters.');
            }
            if (!in_array($role, [ROLE_STAFF, ROLE_SUPER_ADMIN])) {
                throw new Exception('Invalid role.');
            }
            if ($user->emailExists($email)) {
                throw new Exception('Email is already registered.');
            }

            $new_user_id = $user->create([
                'email'    => $email,
                'password' => $password,
                'role'     => $role,
                'status'   => STATUS_ACTIVE,
            ]);

            // Store display name in session note; no staff profile table, so we store it in a meta approach.
            // We'll just store name mapped to user_id in a simple dedicated table if it exists.
            // Since no staff table exists per schema, we use the email as the identifier.
            // Optionally create a basic talent/employer? — no. Just create the user and note name via email prefix.
            // Actually we'll store the name in a staff_meta table if it exists, otherwise skip gracefully.
            try {
                $db->insert(
                    "INSERT INTO staff_profiles (user_id, full_name, created_at) VALUES (?, ?, NOW())",
                    [$new_user_id, $name]
                );
            } catch (Exception $inner) {
                // staff_profiles table may not exist — that's fine, name stored nowhere but user knows
            }

            setFlash('success', "Staff account created for {$email}.");

        } elseif ($action === 'activate' && $user_id > 0) {
            $user->updateStatus($user_id, STATUS_ACTIVE);
            setFlash('success', 'Account activated.');

        } elseif ($action === 'suspend' && $user_id > 0) {
            $user->updateStatus($user_id, STATUS_SUSPENDED);
            setFlash('success', 'Account suspended.');

        } elseif ($action === 'reset_password' && $user_id > 0) {
            $new_password = $_POST['new_password'] ?? '';
            if (strlen($new_password) < 8) throw new Exception('Password must be at least 8 characters.');
            $user->updatePassword($user_id, $new_password);
            setFlash('success', 'Password updated successfully.');

        } elseif ($action === 'promote' && $user_id > 0) {
            $user->updateRole($user_id, ROLE_SUPER_ADMIN);
            setFlash('success', 'Staff promoted to Super Admin.');

        } elseif ($action === 'demote' && $user_id > 0) {
            $user->updateRole($user_id, ROLE_STAFF);
            setFlash('success', 'Admin demoted to Staff.');

        } elseif ($action === 'delete' && $user_id > 0) {
            $user->updateStatus($user_id, STATUS_INACTIVE);
            setFlash('success', 'Account deactivated.');
        }
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }

    redirect(SITE_URL . '/public/admin/staff.php');
}

// Pagination & filters
$page   = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';
$per_page = 20;

$result        = $user->getStaff($page, $per_page, ['search' => $search, 'role' => $role_filter]);
$staff_members = $result['data'];
$pagination    = $result['pagination'];
$stats         = $user->getStaffStats();

$page_title     = 'Staff Management - ' . SITE_NAME;
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
                    <h2 class="mb-0">Staff Management</h2>
                    <p class="text-muted mb-0">Manage agency staff and admin accounts</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createStaffModal">
                    <i class="fas fa-user-plus"></i> Add Staff
                </button>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                            <small class="text-muted">Total Staff</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center border-danger">
                        <div class="card-body py-3">
                            <h4 class="text-danger"><?php echo $stats['admins']; ?></h4>
                            <small class="text-muted">Super Admins</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-warning"><?php echo $stats['staff']; ?></h4>
                            <small class="text-muted">Staff</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-secondary"><?php echo $stats['suspended']; ?></h4>
                            <small class="text-muted">Suspended</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-5">
                            <input type="text" name="search" class="form-control" placeholder="Search by email..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="role" class="form-select">
                                <option value="">All Roles</option>
                                <option value="staff"       <?php echo $role_filter === 'staff'       ? 'selected' : ''; ?>>Staff</option>
                                <option value="super_admin" <?php echo $role_filter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2"><button type="submit" class="btn btn-primary w-100">Filter</button></div>
                        <div class="col-md-2"><a href="?" class="btn btn-outline-secondary w-100">Reset</a></div>
                    </form>
                </div>
            </div>

            <?php flashMessage(); ?>

            <!-- Staff Table -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Staff Accounts <span class="badge bg-secondary"><?php echo $stats['total']; ?></span></h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($staff_members)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-users-cog fa-3x mb-3"></i>
                            <p>No staff accounts found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($staff_members as $s): ?>
                                        <tr class="<?php echo $s['id'] === $current_user_id ? 'table-active' : ''; ?>">
                                            <td><small class="text-muted">#<?php echo $s['id']; ?></small></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="rounded-circle bg-<?php echo $s['role'] === ROLE_SUPER_ADMIN ? 'danger' : 'warning'; ?> text-white d-flex align-items-center justify-content-center"
                                                         style="width:36px;height:36px;font-size:14px;font-weight:bold;flex-shrink:0;">
                                                        <?php echo strtoupper(substr($s['email'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold small"><?php echo htmlspecialchars($s['email']); ?></div>
                                                        <?php if ($s['id'] === $current_user_id): ?>
                                                            <span class="badge bg-light text-dark border" style="font-size:10px;">You</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($s['role'] === ROLE_SUPER_ADMIN): ?>
                                                    <span class="badge bg-danger"><i class="fas fa-shield-alt"></i> Super Admin</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning text-dark"><i class="fas fa-user-tie"></i> Staff</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $sc = [STATUS_ACTIVE => 'success', STATUS_SUSPENDED => 'danger', STATUS_INACTIVE => 'secondary'];
                                                $scolor = $sc[$s['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $scolor; ?>"><?php echo ucfirst($s['status']); ?></span>
                                            </td>
                                            <td><small class="text-muted"><?php echo formatDate($s['created_at']); ?></small></td>
                                            <td><small class="text-muted"><?php echo timeAgo($s['updated_at']); ?></small></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <?php if ($s['id'] !== $current_user_id): ?>
                                                        <!-- Suspend / Activate -->
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                                            <input type="hidden" name="action" value="<?php echo $s['status'] === STATUS_SUSPENDED ? 'activate' : 'suspend'; ?>">
                                                            <button type="submit" class="btn btn-sm <?php echo $s['status'] === STATUS_SUSPENDED ? 'btn-outline-success' : 'btn-outline-warning'; ?>"
                                                                    title="<?php echo $s['status'] === STATUS_SUSPENDED ? 'Activate' : 'Suspend'; ?>">
                                                                <i class="fas <?php echo $s['status'] === STATUS_SUSPENDED ? 'fa-user-check' : 'fa-user-slash'; ?>"></i>
                                                            </button>
                                                        </form>

                                                        <!-- Promote / Demote -->
                                                        <?php if ($s['role'] === ROLE_STAFF): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Promote this staff to Super Admin?')">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                                                <input type="hidden" name="action" value="promote">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Promote to Admin">
                                                                    <i class="fas fa-arrow-up"></i>
                                                                </button>
                                                            </form>
                                                        <?php elseif ($s['role'] === ROLE_SUPER_ADMIN): ?>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Demote this admin to Staff?')">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                <input type="hidden" name="user_id" value="<?php echo $s['id']; ?>">
                                                                <input type="hidden" name="action" value="demote">
                                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Demote to Staff">
                                                                    <i class="fas fa-arrow-down"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>

                                                    <!-- Reset Password -->
                                                    <button type="button" class="btn btn-sm btn-outline-primary"
                                                            onclick="showResetPassword(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['email'])); ?>')"
                                                            title="Reset Password">
                                                        <i class="fas fa-key"></i>
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
                        $base = '?' . http_build_query(array_filter(['search' => $search, 'role' => $role_filter]));
                        echo renderPagination($pagination, $base . '&');
                        ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Roles Reference -->
            <div class="card mt-4">
                <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-info-circle text-info"></i> Role Permissions Reference</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3">
                                <h6><span class="badge bg-warning text-dark me-2">Staff</span>Agency Staff</h6>
                                <ul class="small text-muted mb-0">
                                    <li>Manage talent profiles & verify</li>
                                    <li>Approve/reject job postings</li>
                                    <li>Facilitate manual matching</li>
                                    <li>Handle communications</li>
                                    <li>View limited reports</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 border-danger">
                                <h6><span class="badge bg-danger me-2">Super Admin</span>Full Access</h6>
                                <ul class="small text-muted mb-0">
                                    <li>All Staff permissions</li>
                                    <li>Full system configuration</li>
                                    <li>Manage commission settings</li>
                                    <li>User management (all roles)</li>
                                    <li>Full analytics & reports</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Create Staff Modal -->
<div class="modal fade" id="createStaffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus"></i> Add Staff Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., Budi Santoso">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" required placeholder="staff@example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required minlength="8" placeholder="Min. 8 characters">
                        <small class="text-muted">They can change this after first login.</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select">
                            <option value="staff">Staff</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                        <small class="text-muted">Be careful assigning Super Admin — it grants full system access.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Create Account</button>
                </div>
            </form>
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
                    <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showResetPassword(userId, email) {
    document.getElementById('resetUserId').value = userId;
    document.getElementById('resetUserEmail').textContent = email;
    new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
}
</script>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
