<?php
// public/admin/talent-detail.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Talent.php';
require_once __DIR__ . '/../../classes/Contract.php';
require_once __DIR__ . '/../../classes/Review.php';
require_once __DIR__ . '/../../classes/Application.php';

requireAdmin();

$pdo    = require __DIR__ . '/../../config/database.php';
$db     = new Database($pdo);
$talent = new Talent($db);
$contract = new Contract($db);
$review = new Review($db);
$application = new Application($db);

$talent_id = (int)($_GET['id'] ?? 0);
if (!$talent_id) {
    setFlash('error', 'Invalid talent ID.');
    redirect(SITE_URL . '/public/admin/talents.php');
}

$t = $talent->getById($talent_id);
if (!$t) {
    setFlash('error', 'Talent not found.');
    redirect(SITE_URL . '/public/admin/talents.php');
}

$skills = $talent->getSkills($talent_id);

$applications = $application->getRecentByTalent($talent_id);
$contracts    = $contract->getRecentByTalent($talent_id);
$reviews      = $review->getRecentByReviewee($t['user_id']);

$page_title     = htmlspecialchars($t['full_name']) . ' — Admin View - ' . SITE_NAME;
$body_class     = 'dashboard-page admin-dashboard';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <!-- Back button -->
            <div class="mb-3">
                <a href="<?php echo SITE_URL; ?>/public/admin/talents.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back to Talents
                </a>
            </div>

            <?php flashMessage(); ?>

            <div class="row g-4">

                <!-- Left: Profile Card -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body text-center py-4">
                            <img src="<?php echo getAvatarUrl($t['profile_photo_url']); ?>"
                                 width="100" height="100" class="rounded-circle mb-3" style="object-fit:cover;">
                            <h5 class="mb-1">
                                <?php echo htmlspecialchars($t['full_name']); ?>
                                <?php if ($t['verified']): ?>
                                    <i class="fas fa-check-circle text-primary" title="Verified"></i>
                                <?php endif; ?>
                            </h5>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($t['email']); ?></p>

                            <?php
                            $avail_colors = ['available' => 'success', 'busy' => 'warning', 'unavailable' => 'secondary'];
                            $ac = $avail_colors[$t['availability_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $ac; ?> mb-3"><?php echo ucfirst($t['availability_status']); ?></span>

                            <?php if ($t['rating_average'] > 0): ?>
                                <div class="mb-2">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star <?php echo $i <= round($t['rating_average']) ? 'text-warning' : 'text-muted'; ?>"></i>
                                    <?php endfor; ?>
                                    <span class="small text-muted ms-1"><?php echo number_format($t['rating_average'], 1); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-light p-0">
                            <table class="table table-sm table-borderless mb-0 small">
                                <tr><td class="text-muted ps-3">User ID</td><td>#<?php echo $t['user_id']; ?></td></tr>
                                <tr><td class="text-muted ps-3">Talent ID</td><td>#<?php echo $t['id']; ?></td></tr>
                                <tr><td class="text-muted ps-3">Phone</td><td><?php echo htmlspecialchars($t['phone'] ?? '—'); ?></td></tr>
                                <tr><td class="text-muted ps-3">Location</td><td><?php echo htmlspecialchars(implode(', ', array_filter([$t['city'], $t['country']]))); ?></td></tr>
                                <tr><td class="text-muted ps-3">Experience</td><td><?php echo (int)$t['years_experience']; ?> years</td></tr>
                                <tr><td class="text-muted ps-3">Rate</td><td><?php echo $t['hourly_rate'] ? formatCurrency($t['hourly_rate'], $t['currency']) . '/hr' : '—'; ?></td></tr>
                                <tr><td class="text-muted ps-3">Jobs Done</td><td><?php echo (int)$t['total_jobs_completed']; ?></td></tr>
                                <tr><td class="text-muted ps-3">Account</td><td>
                                    <?php
                                    $sc = [STATUS_ACTIVE => 'success', STATUS_SUSPENDED => 'danger', STATUS_INACTIVE => 'secondary'];
                                    $scolor = $sc[$t['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $scolor; ?>"><?php echo ucfirst($t['status']); ?></span>
                                </td></tr>
                                <tr><td class="text-muted ps-3">Joined</td><td><?php echo formatDate($t['created_at']); ?></td></tr>
                            </table>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="card mt-3">
                        <div class="card-header bg-white"><h6 class="mb-0">Quick Actions</h6></div>
                        <div class="card-body d-flex flex-column gap-2">
                            <a href="<?php echo SITE_URL; ?>/public/admin/talents.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-list"></i> Back to Talent List
                            </a>
                            <?php if ($t['resume_url']): ?>
                                <a href="<?php echo SITE_URL . '/' . ltrim($t['resume_url'], '/'); ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-file-pdf"></i> View Resume
                                </a>
                            <?php endif; ?>
                            <?php if ($t['portfolio_url']): ?>
                                <a href="<?php echo htmlspecialchars($t['portfolio_url']); ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-briefcase"></i> View Portfolio
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo SITE_URL; ?>/public/admin/matches.php" class="btn btn-outline-success btn-sm">
                                <i class="fas fa-handshake"></i> Match to a Job
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Right: Details -->
                <div class="col-lg-8">

                    <!-- Bio -->
                    <?php if ($t['bio']): ?>
                    <div class="card mb-4">
                        <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-user text-primary"></i> Bio</h6></div>
                        <div class="card-body">
                            <p class="mb-0 text-muted"><?php echo nl2br(htmlspecialchars($t['bio'])); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Skills -->
                    <div class="card mb-4">
                        <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-tags text-primary"></i> Skills</h6></div>
                        <div class="card-body">
                            <?php if (empty($skills)): ?>
                                <p class="text-muted small mb-0">No skills added yet.</p>
                            <?php else: ?>
                                <?php
                                $proficiency_colors = ['beginner' => 'secondary', 'intermediate' => 'info', 'advanced' => 'primary', 'expert' => 'success'];
                                foreach ($skills as $sk):
                                    $pc = $proficiency_colors[$sk['proficiency_level']] ?? 'secondary';
                                ?>
                                    <span class="badge bg-<?php echo $pc; ?> me-1 mb-1" title="<?php echo ucfirst($sk['proficiency_level']); ?>">
                                        <?php echo htmlspecialchars($sk['name']); ?>
                                    </span>
                                <?php endforeach; ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <span class="badge bg-secondary me-1">Beginner</span>
                                        <span class="badge bg-info me-1">Intermediate</span>
                                        <span class="badge bg-primary me-1">Advanced</span>
                                        <span class="badge bg-success me-1">Expert</span>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Applications -->
                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-file-alt text-primary"></i> Recent Applications</h6>
                            <a href="<?php echo SITE_URL; ?>/public/admin/applications.php" class="btn btn-outline-primary btn-sm">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($applications)): ?>
                                <div class="text-center py-3 text-muted small">No applications yet.</div>
                            <?php else: ?>
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr><th>Job</th><th>Company</th><th>Rate</th><th>Status</th><th>Applied</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($applications as $a): ?>
                                            <tr>
                                                <td><small class="fw-semibold"><?php echo htmlspecialchars($a['job_title']); ?></small><br><small class="text-muted"><?php echo ucfirst($a['job_type']); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($a['company_name']); ?></small></td>
                                                <td><small><?php echo $a['proposed_rate'] ? formatCurrency($a['proposed_rate']) : '—'; ?></small></td>
                                                <td><?php echo getApplicationStatusBadge($a['status']); ?></td>
                                                <td><small class="text-muted"><?php echo timeAgo($a['applied_at']); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Contracts -->
                    <div class="card mb-4">
                        <div class="card-header bg-white d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-file-contract text-primary"></i> Contracts</h6>
                            <a href="<?php echo SITE_URL; ?>/public/admin/contracts.php" class="btn btn-outline-primary btn-sm">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($contracts)): ?>
                                <div class="text-center py-3 text-muted small">No contracts yet.</div>
                            <?php else: ?>
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr><th>Job</th><th>Company</th><th>Rate</th><th>Status</th><th>Start</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contracts as $c):
                                            $cc = ['active' => 'success', 'completed' => 'info', 'terminated' => 'danger'];
                                            $ccolor = $cc[$c['status']] ?? 'secondary';
                                        ?>
                                            <tr>
                                                <td><small class="fw-semibold"><?php echo htmlspecialchars($c['job_title']); ?></small></td>
                                                <td><small><?php echo htmlspecialchars($c['company_name']); ?></small></td>
                                                <td><small><?php echo formatCurrency($c['rate']); ?>/<?php echo $c['rate_type']; ?></small></td>
                                                <td><span class="badge bg-<?php echo $ccolor; ?>"><?php echo ucfirst($c['status']); ?></span></td>
                                                <td><small class="text-muted"><?php echo formatDate($c['start_date']); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Reviews -->
                    <?php if (!empty($reviews)): ?>
                    <div class="card">
                        <div class="card-header bg-white"><h6 class="mb-0"><i class="fas fa-star text-warning"></i> Reviews Received</h6></div>
                        <div class="card-body">
                            <?php foreach ($reviews as $r): ?>
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted"><?php echo htmlspecialchars($r['reviewer_email']); ?></small>
                                        <small class="text-muted"><?php echo formatDate($r['created_at']); ?></small>
                                    </div>
                                    <div class="mb-1">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $r['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <?php if ($r['comment']): ?>
                                        <p class="small mb-0"><?php echo htmlspecialchars($r['comment']); ?></p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
