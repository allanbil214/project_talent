<?php
// public/admin/talents.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Talent.php';
require_once __DIR__ . '/../../classes/User.php';

requireAdmin();

$pdo    = require __DIR__ . '/../../config/database.php';
$db     = new Database($pdo);
$talent = new Talent($db);
$user   = new User($db);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirect(SITE_URL . '/public/admin/talents.php');
    }

    $action     = $_POST['action'] ?? '';
    $talent_id  = (int)($_POST['talent_id'] ?? 0);
    $user_id    = (int)($_POST['user_id'] ?? 0);

    try {
        if ($action === 'verify') {
            $talent->setVerified($talent_id, true);
            setFlash('success', 'Talent verified.');
        } elseif ($action === 'unverify') {
            $talent->setVerified($talent_id, false);
            setFlash('success', 'Talent unverified.');
        } elseif ($action === 'suspend') {
            $user->updateStatus($user_id, STATUS_SUSPENDED);
            setFlash('success', 'Talent account suspended.');
        } elseif ($action === 'activate') {
            $user->updateStatus($user_id, STATUS_ACTIVE);
            setFlash('success', 'Talent account activated.');
        }
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }

    redirect(SITE_URL . '/public/admin/talents.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
}

// Filters & pagination
$page    = max(1, (int)($_GET['page'] ?? 1));
$filters = [
    'search'              => $_GET['search'] ?? '',
    'availability_status' => $_GET['availability'] ?? '',
    'verified'            => isset($_GET['verified']) ? (int)$_GET['verified'] : null,
];

$result     = $talent->getAll($page, 20, $filters);
$talents    = $result['data'];
$pagination = $result['pagination'];

// Stats
$stats = $talent->getAdminStats();

$page_title     = 'Manage Talents - ' . SITE_NAME;
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
                    <h2 class="mb-0">Manage Talents</h2>
                    <p class="text-muted mb-0">Verify, manage, and monitor talent accounts</p>
                </div>
            </div>

            <!-- Stat Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                            <small class="text-muted">Total Talents</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-success"><?php echo $stats['verified']; ?></h4>
                            <small class="text-muted">Verified</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-info"><?php echo $stats['available']; ?></h4>
                            <small class="text-muted">Available Now</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-danger"><?php echo $stats['suspended']; ?></h4>
                            <small class="text-muted">Suspended</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <input type="text" name="search" class="form-control" placeholder="Search name, bio, city..." value="<?php echo htmlspecialchars($filters['search']); ?>">
                        </div>
                        <div class="col-md-2">
                            <select name="availability" class="form-select">
                                <option value="">All Availability</option>
                                <option value="available"   <?php echo $filters['availability_status'] === 'available'   ? 'selected' : ''; ?>>Available</option>
                                <option value="busy"        <?php echo $filters['availability_status'] === 'busy'        ? 'selected' : ''; ?>>Busy</option>
                                <option value="unavailable" <?php echo $filters['availability_status'] === 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="verified" class="form-select">
                                <option value="">All</option>
                                <option value="1" <?php echo $filters['verified'] === 1 ? 'selected' : ''; ?>>Verified</option>
                                <option value="0" <?php echo $filters['verified'] === 0 ? 'selected' : ''; ?>>Unverified</option>
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

            <!-- Talents Table -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">Talents <span class="badge bg-secondary"><?php echo $pagination['total']; ?></span></h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($talents)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <p>No talents found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Talent</th>
                                        <th>Contact</th>
                                        <th>Skills</th>
                                        <th>Rate</th>
                                        <th>Rating</th>
                                        <th>Availability</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($talents as $t): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <img src="<?php echo getAvatarUrl($t['profile_photo_url']); ?>"
                                                         width="40" height="40" class="rounded-circle" style="object-fit:cover;">
                                                    <div>
                                                        <div class="fw-semibold">
                                                            <?php echo htmlspecialchars($t['full_name']); ?>
                                                            <?php if ($t['verified']): ?>
                                                                <i class="fas fa-check-circle text-primary" title="Verified"></i>
                                                            <?php endif; ?>
                                                        </div>
                                                        <small class="text-muted">
                                                            <?php echo $t['city'] ? htmlspecialchars($t['city']) : '—'; ?>
                                                            &middot; <?php echo (int)$t['years_experience']; ?> yrs exp
                                                        </small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($t['email']); ?></small><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($t['phone'] ?? '—'); ?></small>
                                            </td>
                                            <td>
                                                <?php foreach (array_slice($t['skills'] ?? [], 0, 3) as $skill): ?>
                                                    <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($skill['name']); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($t['skills'] ?? []) > 3): ?>
                                                    <span class="badge bg-light text-muted">+<?php echo count($t['skills']) - 3; ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo $t['hourly_rate'] ? formatCurrency($t['hourly_rate'], $t['currency']) . '/hr' : '—'; ?></small>
                                            </td>
                                            <td>
                                                <?php if ($t['rating_average'] > 0): ?>
                                                    <i class="fas fa-star text-warning"></i>
                                                    <?php echo number_format($t['rating_average'], 1); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $avail_colors = ['available' => 'success', 'busy' => 'warning', 'unavailable' => 'secondary'];
                                                $color = $avail_colors[$t['availability_status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($t['availability_status']); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($t['status'] === STATUS_SUSPENDED): ?>
                                                    <span class="badge bg-danger">Suspended</span>
                                                <?php elseif ($t['status'] === STATUS_ACTIVE): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($t['status']); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <!-- Verify / Unverify -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="talent_id" value="<?php echo $t['id']; ?>">
                                                        <input type="hidden" name="action" value="<?php echo $t['verified'] ? 'unverify' : 'verify'; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $t['verified'] ? 'btn-outline-secondary' : 'btn-outline-primary'; ?>"
                                                                title="<?php echo $t['verified'] ? 'Remove Verification' : 'Verify Talent'; ?>">
                                                            <i class="fas <?php echo $t['verified'] ? 'fa-times-circle' : 'fa-check-circle'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <!-- Suspend / Activate -->
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                        <input type="hidden" name="talent_id" value="<?php echo $t['id']; ?>">
                                                        <input type="hidden" name="user_id" value="<?php echo $t['user_id']; ?>">
                                                        <input type="hidden" name="action" value="<?php echo $t['status'] === STATUS_SUSPENDED ? 'activate' : 'suspend'; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $t['status'] === STATUS_SUSPENDED ? 'btn-outline-success' : 'btn-outline-danger'; ?>"
                                                                title="<?php echo $t['status'] === STATUS_SUSPENDED ? 'Activate' : 'Suspend'; ?>">
                                                            <i class="fas <?php echo $t['status'] === STATUS_SUSPENDED ? 'fa-user-check' : 'fa-user-slash'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <!-- View Profile -->
                                                    <a href="<?php echo SITE_URL; ?>/public/admin/talent-detail.php?id=<?php echo $t['id']; ?>""
                                                       class="btn btn-sm btn-outline-info" target="_blank" title="View Profile">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
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
                        $base = '?' . http_build_query(array_filter(['search' => $filters['search'], 'availability' => $filters['availability_status']], fn($v) => $v !== '' && $v !== null));
                        echo renderPagination($pagination, $base . '&');
                        ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
