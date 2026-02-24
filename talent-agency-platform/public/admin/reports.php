<?php
// public/admin/reports.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Report.php';

requireAdmin();
$pdo = require __DIR__ . '/../../config/database.php';
$db  = new Database($pdo);
$report = new Report($db);

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

$overview = $report->getOverview($date_from, $date_to);
$monthly_revenue = $report->getMonthlyRevenue();
$top_talents = $report->getTopTalents();
$top_employers = $report->getTopEmployers();
$job_type_breakdown = $report->getJobTypeBreakdown();
$app_funnel = $report->getApplicationFunnel();
$skill_demand = $report->getSkillDemand();

$page_title = 'Reports & Analytics - ' . SITE_NAME;
$body_class = 'dashboard-page admin-dashboard';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div><h2 class="mb-0">Reports & Analytics</h2><p class="text-muted mb-0">Platform performance overview</p></div>
                <form method="GET" class="d-flex gap-2 align-items-center">
                    <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo $date_from; ?>">
                    <span class="text-muted">to</span>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo $date_to; ?>">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="?" class="btn btn-outline-secondary btn-sm">Reset</a>
                </form>
            </div>

            <div class="alert alert-light border mb-4">
                <strong>Period:</strong> <?php echo formatDate($date_from); ?> — <?php echo formatDate($date_to); ?> &nbsp;|&nbsp;
                <strong><?php echo $overview['new_users']; ?></strong> new users &nbsp;|&nbsp;
                <strong><?php echo $overview['jobs_posted']; ?></strong> jobs posted &nbsp;|&nbsp;
                <strong><?php echo $overview['applications_period']; ?></strong> applications &nbsp;|&nbsp;
                <strong class="text-success"><?php echo formatCurrency($overview['revenue_period']); ?></strong> revenue &nbsp;|&nbsp;
                <strong class="text-primary"><?php echo formatCurrency($overview['commission_period']); ?></strong> commission
            </div>

            <!-- Overview Cards -->
            <div class="row g-3 mb-4">
                <?php
                $cards = [
                    ['Total Users',$overview['total_users'],'primary','fa-users'],
                    ['Talents',$overview['total_talents'],'info','fa-user-tie'],
                    ['Employers',$overview['total_employers'],'warning','fa-building'],
                    ['Active Jobs',$overview['active_jobs'],'success','fa-briefcase'],
                    ['Active Contracts',$overview['active_contracts'],'primary','fa-file-contract'],
                    ['Placements Done',$overview['completed_contracts'],'success','fa-handshake'],
                    ['All-Time Revenue',formatCurrency($overview['total_revenue']),'dark','fa-dollar-sign'],
                    ['All-Time Commission',formatCurrency($overview['total_commission']),'success','fa-percentage'],
                ];
                foreach ($cards as $c): ?>
                    <div class="col-6 col-lg-3">
                        <div class="card"><div class="card-body py-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="rounded-circle bg-<?php echo $c[2]; ?> bg-opacity-10 p-3"><i class="fas <?php echo $c[3]; ?> text-<?php echo $c[2]; ?>"></i></div>
                                <div><div class="fw-bold"><?php echo $c[1]; ?></div><small class="text-muted"><?php echo $c[0]; ?></small></div>
                            </div>
                        </div></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Revenue Chart -->
            <?php if (!empty($monthly_revenue)): ?>
            <div class="card mb-4">
                <div class="card-header bg-white"><h5 class="mb-0"><i class="fas fa-chart-line text-primary"></i> Monthly Revenue (Last 12 Months)</h5></div>
                <div class="card-body"><canvas id="revenueChart" height="60"></canvas></div>
            </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <!-- Application Funnel -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-white"><h5 class="mb-0"><i class="fas fa-filter text-primary"></i> Application Funnel</h5></div>
                        <div class="card-body">
                            <?php
                            $total_apps = array_sum(array_column($app_funnel,'count'));
                            $funnel_colors = ['pending'=>'warning','reviewed'=>'info','shortlisted'=>'primary','accepted'=>'success','rejected'=>'danger'];
                            foreach ($app_funnel as $f):
                                $pct = $total_apps > 0 ? round(($f['count']/$total_apps)*100,1) : 0;
                                $color = $funnel_colors[$f['status']] ?? 'secondary';
                            ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="small fw-semibold"><?php echo ucfirst($f['status']); ?></span>
                                        <span class="small"><?php echo $f['count']; ?> (<?php echo $pct; ?>%)</span>
                                    </div>
                                    <div class="progress" style="height:8px"><div class="progress-bar bg-<?php echo $color; ?>" style="width:<?php echo $pct; ?>%"></div></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Job Types -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-white"><h5 class="mb-0"><i class="fas fa-chart-pie text-primary"></i> Job Types</h5></div>
                        <div class="card-body"><canvas id="jobTypeChart"></canvas></div>
                    </div>
                </div>

                <!-- Skill Demand -->
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header bg-white"><h5 class="mb-0"><i class="fas fa-tags text-primary"></i> Skill Demand vs Supply</h5></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light"><tr><th>Skill</th><th class="text-center">Jobs</th><th class="text-center">Talents</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($skill_demand as $sk): ?>
                                            <tr>
                                                <td><small><?php echo htmlspecialchars($sk['name']); ?></small><?php if ($sk['category']): ?><br><span class="badge bg-light text-muted" style="font-size:10px"><?php echo htmlspecialchars($sk['category']); ?></span><?php endif; ?></td>
                                                <td class="text-center"><span class="badge bg-primary"><?php echo $sk['job_demand']; ?></span></td>
                                                <td class="text-center"><span class="badge bg-success"><?php echo $sk['talent_supply']; ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Talents & Employers -->
            <div class="row g-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white"><h5 class="mb-0"><i class="fas fa-trophy text-warning"></i> Top Talents by Placements</h5></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th>#</th><th>Talent</th><th class="text-center">Placements</th><th>Value</th><th>Rating</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($top_talents as $i => $t): ?>
                                            <tr>
                                                <td><?php echo $i<3 ? '<span class="badge bg-'.['warning','secondary','dark'][$i].'">'.(($i+1)).'</span>' : '<small class="text-muted">'.($i+1).'</small>'; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?php echo getAvatarUrl($t['profile_photo_url']); ?>" width="32" height="32" class="rounded-circle" style="object-fit:cover;">
                                                        <small><?php echo htmlspecialchars($t['full_name']); ?></small>
                                                    </div>
                                                </td>
                                                <td class="text-center"><span class="badge bg-primary"><?php echo $t['contract_count']; ?></span></td>
                                                <td><small><?php echo formatCurrency($t['total_value']); ?></small></td>
                                                <td><small><?php echo $t['rating_average']>0 ? '<i class="fas fa-star text-warning"></i> '.number_format($t['rating_average'],1) : '—'; ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($top_talents)): ?><tr><td colspan="5" class="text-center text-muted py-4">No data yet</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-white"><h5 class="mb-0"><i class="fas fa-building text-info"></i> Top Employers by Placements</h5></div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light"><tr><th>#</th><th>Company</th><th class="text-center">Jobs</th><th class="text-center">Placed</th><th>Spent</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($top_employers as $i => $e): ?>
                                            <tr>
                                                <td><?php echo $i<3 ? '<span class="badge bg-'.['warning','secondary','dark'][$i].'">'.(($i+1)).'</span>' : '<small class="text-muted">'.($i+1).'</small>'; ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <?php if ($e['company_logo_url']): ?><img src="<?php echo getCompanyLogoUrl($e['company_logo_url']); ?>" width="28" height="28" class="rounded" style="object-fit:cover;"><?php endif; ?>
                                                        <div><small class="fw-semibold"><?php echo htmlspecialchars($e['company_name']); ?></small><?php if ($e['industry']): ?><br><span style="font-size:10px" class="text-muted"><?php echo htmlspecialchars($e['industry']); ?></span><?php endif; ?></div>
                                                    </div>
                                                </td>
                                                <td class="text-center"><span class="badge bg-info text-white"><?php echo $e['jobs_posted']; ?></span></td>
                                                <td class="text-center"><span class="badge bg-success"><?php echo $e['placements']; ?></span></td>
                                                <td><small><?php echo formatCurrency($e['total_spent']); ?></small></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($top_employers)): ?><tr><td colspan="5" class="text-center text-muted py-4">No data yet</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($monthly_revenue)): ?>
new Chart(document.getElementById('revenueChart').getContext('2d'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_column($monthly_revenue,'month')); ?>,
        datasets: [
            { label:'Revenue', data:<?php echo json_encode(array_map(fn($m)=>(float)$m['revenue'],$monthly_revenue)); ?>, borderColor:'rgba(13,110,253,1)', backgroundColor:'rgba(13,110,253,0.1)', fill:true, tension:0.4 },
            { label:'Commission', data:<?php echo json_encode(array_map(fn($m)=>(float)$m['commission'],$monthly_revenue)); ?>, borderColor:'rgba(25,135,84,1)', backgroundColor:'rgba(25,135,84,0.1)', fill:true, tension:0.4 }
        ]
    },
    options: { responsive:true, plugins:{legend:{position:'top'}}, scales:{y:{beginAtZero:true}} }
});
<?php endif; ?>
<?php if (!empty($job_type_breakdown)): ?>
new Chart(document.getElementById('jobTypeChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode(array_map(fn($j)=>ucfirst($j['job_type']),$job_type_breakdown)); ?>,
        datasets: [{ data:<?php echo json_encode(array_column($job_type_breakdown,'total')); ?>, backgroundColor:['#0d6efd','#198754','#ffc107','#dc3545','#6f42c1','#0dcaf0'] }]
    },
    options: { responsive:true, plugins:{legend:{position:'bottom'}} }
});
<?php endif; ?>
</script>
<?php $additional_js=['dashboard.js']; require_once __DIR__.'/../../includes/footer.php'; ?>
