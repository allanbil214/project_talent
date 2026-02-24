<?php
// public/admin/matches.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Application.php';
require_once __DIR__ . '/../../classes/Talent.php';
require_once __DIR__ . '/../../classes/Job.php';

requireAdmin();

$pdo         = require __DIR__ . '/../../config/database.php';
$db          = new Database($pdo);
$application = new Application($db);
$talentClass = new Talent($db);
$jobClass    = new Job($db);

// Handle manual match (agency creates application on behalf of talent)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { setFlash('error','Invalid request.'); redirect(SITE_URL.'/public/admin/matches.php'); }
    $action    = $_POST['action'] ?? '';
    $talent_id = (int)($_POST['talent_id'] ?? 0);
    $job_id    = (int)($_POST['job_id'] ?? 0);
    try {
        if ($action === 'match' && $talent_id && $job_id) {
            if ($application->hasApplied($job_id, $talent_id)) throw new Exception('This talent has already applied to this job.');
            $application->create([
                'job_id'              => $job_id,
                'talent_id'           => $talent_id,
                'cover_letter'        => $_POST['cover_letter'] ?? 'Matched by agency staff.',
                'proposed_rate'       => (float)($_POST['proposed_rate'] ?? 0) ?: null,
                'status'              => APP_STATUS_SHORTLISTED,
                'agency_recommended'  => true,
            ]);
            setFlash('success', 'Talent matched and shortlisted successfully!');
        }
    } catch (Exception $e) { setFlash('error', $e->getMessage()); }
    redirect(SITE_URL . '/public/admin/matches.php');
}

// Load active jobs and available talents for the matching form
$active_jobs = $jobClass->getActiveForMatching();

// Selected job for talent suggestions
$selected_job_id = (int)($_GET['job_id'] ?? 0);
$selected_job    = null;
$suggested_talents = [];
$already_applied   = [];

if ($selected_job_id) {
    $selected_job = $jobClass->getById($selected_job_id);

    $skill_ids = !empty($selected_job['skills'])
        ? array_column($selected_job['skills'], 'id')
        : [];

    $suggested_talents = $talentClass->getSuggestedForJob(
        $selected_job_id,
        $skill_ids
    );

    foreach ($suggested_talents as &$st) {
        $st['skills'] = $talentClass->getSkills($st['id']);
    }
    
    // Who's already applied
    $already_applied = $application->getByJobWithTalent($selected_job_id);
}

// Recent manual matches
$recent_matches = $application->getRecentRecommended(20);

$page_title = 'Manual Matching - ' . SITE_NAME;
$body_class = 'dashboard-page admin-dashboard';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div><h2 class="mb-0">Manual Matching</h2><p class="text-muted mb-0">Match and recommend talents to specific jobs</p></div>
            </div>

            <?php flashMessage(); ?>

            <div class="row g-4">
                <!-- Step 1: Select Job -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><span class="badge bg-primary me-2">1</span> Select a Job</h5>
                        </div>
                        <div class="card-body p-0" style="max-height:600px;overflow-y:auto">
                            <?php if (empty($active_jobs)): ?>
                                <div class="text-center py-4 text-muted"><i class="fas fa-briefcase fa-2x mb-2"></i><p>No active jobs.</p></div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($active_jobs as $j): ?>
                                        <a href="?job_id=<?php echo $j['id']; ?>"
                                           class="list-group-item list-group-item-action <?php echo $selected_job_id===$j['id']?'active':''; ?>">
                                            <div class="fw-semibold small"><?php echo htmlspecialchars($j['title']); ?></div>
                                            <small class="<?php echo $selected_job_id===$j['id']?'text-white-50':'text-muted'; ?>">
                                                <?php echo htmlspecialchars($j['company_name']); ?> &middot; <?php echo ucfirst($j['job_type']); ?> &middot; <?php echo ucfirst($j['location_type']); ?>
                                            </small>
                                            <div class="mt-1">
                                                <span class="badge <?php echo $selected_job_id===$j['id']?'bg-white text-primary':'bg-light text-dark'; ?>"><?php echo $j['app_count']; ?> applications</span>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Talent Suggestions -->
                <div class="col-lg-8">
                    <?php if (!$selected_job): ?>
                        <div class="card">
                            <div class="card-body text-center py-5 text-muted">
                                <i class="fas fa-hand-point-left fa-3x mb-3"></i>
                                <p>Select a job from the left to see talent suggestions.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Job Summary -->
                        <div class="card mb-3">
                            <div class="card-body">
                                <h5><?php echo htmlspecialchars($selected_job['title']); ?></h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($selected_job['company_name']); ?> &middot; <?php echo ucfirst($selected_job['job_type']); ?> &middot; <?php echo ucfirst($selected_job['location_type']); ?></p>
                                <?php if (!empty($selected_job['skills'])): ?>
                                    <div>
                                        <?php foreach ($selected_job['skills'] as $sk): ?>
                                            <span class="badge bg-primary me-1"><?php echo htmlspecialchars($sk['name']); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Tabs -->
                        <ul class="nav nav-tabs mb-3" role="tablist">
                            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#suggested">Suggested (<?php echo count($suggested_talents); ?>)</button></li>
                            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#applied">Already Applied (<?php echo count($already_applied); ?>)</button></li>
                        </ul>

                        <div class="tab-content">
                            <!-- Suggested Talents Tab -->
                            <div class="tab-pane fade show active" id="suggested">
                                <?php if (empty($suggested_talents)): ?>
                                    <div class="card"><div class="card-body text-center py-4 text-muted"><p>All available talents have already applied.</p></div></div>
                                <?php else: ?>
                                    <?php foreach ($suggested_talents as $t): ?>
                                        <div class="card mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="d-flex gap-3">
                                                        <img src="<?php echo getAvatarUrl($t['profile_photo_url']); ?>" width="50" height="50" class="rounded-circle" style="object-fit:cover;">
                                                        <div>
                                                            <div class="fw-semibold">
                                                                <?php echo htmlspecialchars($t['full_name']); ?>
                                                                <?php if ($t['verified']): ?><i class="fas fa-check-circle text-primary" title="Verified"></i><?php endif; ?>
                                                                <?php if ($t['matching_skills'] > 0): ?>
                                                                    <span class="badge bg-success ms-1"><?php echo $t['matching_skills']; ?> skill match</span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <small class="text-muted">
                                                                <?php echo $t['city'] ? htmlspecialchars($t['city']).' &middot; ' : ''; ?>
                                                                <?php echo (int)$t['years_experience']; ?> yrs exp
                                                                <?php if ($t['hourly_rate']): ?> &middot; <?php echo formatCurrency($t['hourly_rate'],$t['currency']); ?>/hr<?php endif; ?>
                                                                <?php if ($t['rating_average'] > 0): ?> &middot; <i class="fas fa-star text-warning"></i> <?php echo number_format($t['rating_average'],1); ?><?php endif; ?>
                                                            </small>
                                                            <div class="mt-1">
                                                                <?php foreach (array_slice($t['skills'],0,5) as $sk): ?>
                                                                    <?php
                                                                    $is_match = false;
                                                                    if (!empty($selected_job['skills'])) {
                                                                        foreach ($selected_job['skills'] as $jsk) {
                                                                            if ($jsk['name'] === $sk['name']) { $is_match = true; break; }
                                                                        }
                                                                    }
                                                                    ?>
                                                                    <span class="badge <?php echo $is_match ? 'bg-success' : 'bg-light text-dark border'; ?> me-1"><?php echo htmlspecialchars($sk['name']); ?></span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-<?php echo $t['availability_status']==='available'?'success':($t['availability_status']==='busy'?'warning':'secondary'); ?> mb-2 d-block">
                                                            <?php echo ucfirst($t['availability_status']); ?>
                                                        </span>
                                                        <button class="btn btn-primary btn-sm" onclick="openMatchModal(<?php echo $t['id']; ?>,'<?php echo htmlspecialchars(addslashes($t['full_name'])); ?>',<?php echo (float)($t['hourly_rate']??0); ?>)">
                                                            <i class="fas fa-handshake"></i> Match
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Already Applied Tab -->
                            <div class="tab-pane fade" id="applied">
                                <?php if (empty($already_applied)): ?>
                                    <div class="card"><div class="card-body text-center py-4 text-muted"><p>No applications yet.</p></div></div>
                                <?php else: ?>
                                    <div class="card"><div class="card-body p-0">
                                        <table class="table align-middle mb-0">
                                            <thead class="table-light"><tr><th>Talent</th><th>Rate</th><th>Status</th><th>Applied</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($already_applied as $aa): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center gap-2">
                                                                <img src="<?php echo getAvatarUrl($aa['profile_photo_url']); ?>" width="32" height="32" class="rounded-circle" style="object-fit:cover;">
                                                                <small><?php echo htmlspecialchars($aa['full_name']); ?></small>
                                                            </div>
                                                        </td>
                                                        <td><small><?php echo $aa['proposed_rate'] ? formatCurrency($aa['proposed_rate']) : '—'; ?></small></td>
                                                        <td><?php echo getApplicationStatusBadge($aa['status']); ?></td>
                                                        <td><small class="text-muted"><?php echo timeAgo($aa['applied_at']); ?></small></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Matches -->
            <?php if (!empty($recent_matches)): ?>
            <div class="card mt-4">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="fas fa-history text-primary"></i> Recent Agency Matches</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Talent</th><th>Job</th><th>Company</th><th>Rate</th><th>Status</th><th>Matched</th></tr></thead>
                            <tbody>
                                <?php foreach ($recent_matches as $rm): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <img src="<?php echo getAvatarUrl($rm['profile_photo_url']); ?>" width="32" height="32" class="rounded-circle" style="object-fit:cover;">
                                                <small><?php echo htmlspecialchars($rm['talent_name']); ?></small>
                                            </div>
                                        </td>
                                        <td><small><?php echo htmlspecialchars($rm['job_title']); ?></small></td>
                                        <td><small><?php echo htmlspecialchars($rm['company_name']); ?></small></td>
                                        <td><small><?php echo $rm['proposed_rate'] ? formatCurrency($rm['proposed_rate']) : '—'; ?></small></td>
                                        <td><?php echo getApplicationStatusBadge($rm['status']); ?></td>
                                        <td><small class="text-muted"><?php echo timeAgo($rm['applied_at']); ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Match Modal -->
<div class="modal fade" id="matchModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
            <input type="hidden" name="action" value="match">
            <input type="hidden" name="job_id" value="<?php echo $selected_job_id; ?>">
            <input type="hidden" name="talent_id" id="matchTalentId">
            <div class="modal-header">
                <h5 class="modal-title">Match Talent to Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    Matching <strong id="matchTalentName"></strong> to <strong><?php echo $selected_job ? htmlspecialchars($selected_job['title']) : ''; ?></strong>
                    <?php if ($selected_job): ?>at <strong><?php echo htmlspecialchars($selected_job['company_name']); ?></strong><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Proposed Rate</label>
                    <div class="input-group"><span class="input-group-text">Rp</span><input type="number" name="proposed_rate" id="matchRate" class="form-control" min="0" step="1000"></div>
                    <small class="text-muted">Optional. Leave blank to use talent's default rate.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Cover Letter / Match Notes</label>
                    <textarea name="cover_letter" class="form-control" rows="4" placeholder="Write a brief note about why this talent is a good match...">Matched by <?php echo SITE_NAME; ?> agency staff based on skill alignment and availability.</textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-handshake"></i> Confirm Match</button>
            </div>
        </form>
    </div></div>
</div>

<script>
function openMatchModal(talentId, talentName, defaultRate) {
    document.getElementById('matchTalentId').value   = talentId;
    document.getElementById('matchTalentName').textContent = talentName;
    document.getElementById('matchRate').value        = defaultRate || '';
    new bootstrap.Modal(document.getElementById('matchModal')).show();
}
</script>
<?php $additional_js=['dashboard.js']; require_once __DIR__.'/../../includes/footer.php'; ?>
