<?php
// public/admin/settings.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Setting.php';

requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$db  = new Database($pdo);
$setting = new Setting($db);

$setting->ensureTable();

// Handle POST save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirect(SITE_URL . '/public/admin/settings.php');
    }

    $group = $_POST['group'] ?? 'general';

    try {
        $settings_input = $_POST['settings'] ?? [];
        $updated = 0;
        foreach ($settings_input as $key => $value) {
            $key   = preg_replace('/[^a-z0-9_]/', '', $key); // sanitize key
            $value = trim($value);

            // Special validation
            if ($key === 'commission_percentage') {
                $value = (float)$value;
                if ($value < 0 || $value > 100) throw new Exception('Commission must be between 0 and 100.');
                $value = number_format($value, 2, '.', '');
            }
            if ($key === 'min_password_length') {
                $value = max(6, (int)$value);
            }
            if ($key === 'session_lifetime_hours') {
                $value = max(1, (int)$value);
            }

            $setting->set($key, $value);
            $updated++;
        }
        setFlash('success', "Settings saved ({$updated} values updated).");
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }

    redirect(SITE_URL . '/public/admin/settings.php?tab=' . $group);
}

// Load all settings
$settings = $setting->getAll();

// Get system info
$sys_info = $setting->getSystemInfo();

$active_tab = $_GET['tab'] ?? 'general';

$page_title = 'Settings - ' . SITE_NAME;
$body_class = 'dashboard-page admin-dashboard';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';

// Helper to get setting value with fallback
function getSetting($settings, $group, $key, $fallback = '') {
    return $settings[$group][$key]['setting_value'] ?? $fallback;
}
function getSettingLabel($settings, $group, $key, $fallback = '') {
    return $settings[$group][$key]['label'] ?? $fallback;
}
function getSettingDesc($settings, $group, $key, $fallback = '') {
    return $settings[$group][$key]['description'] ?? $fallback;
}
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">System Settings</h2>
                    <p class="text-muted mb-0">Configure platform behaviour, commission, and notifications</p>
                </div>
            </div>

            <?php flashMessage(); ?>

            <div class="row g-4">
                <!-- Tab Nav -->
                <div class="col-lg-3">
                    <div class="card">
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush rounded">
                                <?php
                                $tabs = [
                                    'general'  => ['fa-cog',          'General'],
                                    'financial' => ['fa-percentage',   'Financial'],
                                    'email'     => ['fa-envelope',     'Email'],
                                    'uploads'   => ['fa-upload',       'Uploads'],
                                    'security'  => ['fa-shield-alt',   'Security'],
                                    'system'    => ['fa-server',       'System Info'],
                                ];
                                foreach ($tabs as $tab_key => [$icon, $label]):
                                ?>
                                    <a href="?tab=<?php echo $tab_key; ?>"
                                       class="list-group-item list-group-item-action d-flex align-items-center gap-2 <?php echo $active_tab === $tab_key ? 'active' : ''; ?>">
                                        <i class="fas <?php echo $icon; ?> fa-fw"></i>
                                        <?php echo $label; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Panel -->
                <div class="col-lg-9">

                    <?php if ($active_tab === 'general'): ?>
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-cog text-primary"></i> General Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="group" value="general">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Site Name</label>
                                    <input type="text" name="settings[site_name]" class="form-control"
                                           value="<?php echo htmlspecialchars(getSetting($settings, 'general', 'site_name', SITE_NAME)); ?>"
                                           placeholder="<?php echo SITE_NAME; ?>">
                                    <small class="text-muted">Displayed in the browser tab and email subjects.</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Support Email</label>
                                    <input type="email" name="settings[site_email]" class="form-control"
                                           value="<?php echo htmlspecialchars(getSetting($settings, 'general', 'site_email', SITE_EMAIL)); ?>">
                                    <small class="text-muted">Contact email shown to users on the platform.</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Jobs Per Page</label>
                                    <input type="number" name="settings[jobs_per_page]" class="form-control" style="max-width:120px;"
                                           value="<?php echo (int)getSetting($settings, 'general', 'jobs_per_page', JOBS_PER_PAGE); ?>" min="5" max="100">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Talents Per Page</label>
                                    <input type="number" name="settings[talents_per_page]" class="form-control" style="max-width:120px;"
                                           value="<?php echo (int)getSetting($settings, 'general', 'talents_per_page', TALENTS_PER_PAGE); ?>" min="5" max="100">
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Allow New Registrations</label>
                                    <select name="settings[allow_registration]" class="form-select" style="max-width:200px;">
                                        <option value="1" <?php echo getSetting($settings, 'general', 'allow_registration', '1') === '1' ? 'selected' : ''; ?>>Yes — Open to public</option>
                                        <option value="0" <?php echo getSetting($settings, 'general', 'allow_registration', '1') === '0' ? 'selected' : ''; ?>>No — Invite only</option>
                                    </select>
                                    <small class="text-muted d-block mt-1">When disabled, the registration page will show a closed message.</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Require Job Approval</label>
                                    <select name="settings[require_job_approval]" class="form-select" style="max-width:200px;">
                                        <option value="1" <?php echo getSetting($settings, 'general', 'require_job_approval', '1') === '1' ? 'selected' : ''; ?>>Yes — Admin must approve</option>
                                        <option value="0" <?php echo getSetting($settings, 'general', 'require_job_approval', '1') === '0' ? 'selected' : ''; ?>>No — Auto-publish</option>
                                    </select>
                                    <small class="text-muted d-block mt-1">Recommended: keep enabled to control job quality.</small>
                                </div>

                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save General Settings</button>
                            </form>
                        </div>
                    </div>

                    <?php elseif ($active_tab === 'financial'): ?>
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-percentage text-success"></i> Financial Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning mb-4">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Important:</strong> Changing the commission rate only affects <em>new contracts</em>. Existing contracts keep their original commission.
                            </div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="group" value="financial">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Default Agency Commission (%)</label>
                                    <div class="input-group" style="max-width: 200px;">
                                        <input type="number" name="settings[commission_percentage]" class="form-control"
                                               value="<?php echo getSetting($settings, 'financial', 'commission_percentage', DEFAULT_COMMISSION_PERCENTAGE); ?>"
                                               min="0" max="100" step="0.5">
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Current default: <strong><?php echo getSetting($settings, 'financial', 'commission_percentage', DEFAULT_COMMISSION_PERCENTAGE); ?>%</strong>. This is applied when a new contract is created.</small>
                                </div>

                                <!-- Commission preview -->
                                <div class="card bg-light mb-4">
                                    <div class="card-body">
                                        <h6 class="mb-3">Commission Preview Calculator</h6>
                                        <div class="row g-3 align-items-center">
                                            <div class="col-md-4">
                                                <label class="form-label small">Contract Amount</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Rp</span>
                                                    <input type="number" id="previewAmount" class="form-control" value="10000000" oninput="calcPreview()">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">Commission</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text text-success">Rp</span>
                                                    <input type="text" id="previewCommission" class="form-control text-success fw-bold" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">Talent Receives</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text text-primary">Rp</span>
                                                    <input type="text" id="previewTalent" class="form-control text-primary fw-bold" readonly>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Financial Settings</button>
                            </form>
                        </div>
                    </div>

                    <?php elseif ($active_tab === 'email'): ?>
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-envelope text-info"></i> Email / SMTP Settings</h5>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info mb-4">
                                <i class="fas fa-info-circle"></i>
                                These settings override the <code>.env</code> file at runtime. For production, prefer setting values in the environment file.
                            </div>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="group" value="email">

                                <div class="row g-3 mb-3">
                                    <div class="col-md-8">
                                        <label class="form-label fw-semibold">SMTP Host</label>
                                        <input type="text" name="settings[smtp_host]" class="form-control"
                                               value="<?php echo htmlspecialchars(getSetting($settings, 'email', 'smtp_host', SMTP_HOST)); ?>"
                                               placeholder="smtp.gmail.com">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">SMTP Port</label>
                                        <input type="number" name="settings[smtp_port]" class="form-control"
                                               value="<?php echo (int)getSetting($settings, 'email', 'smtp_port', SMTP_PORT); ?>"
                                               placeholder="587">
                                        <small class="text-muted">587 (TLS) or 465 (SSL)</small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">SMTP Username</label>
                                    <input type="email" name="settings[smtp_user]" class="form-control"
                                           value="<?php echo htmlspecialchars(getSetting($settings, 'email', 'smtp_user', '')); ?>"
                                           placeholder="your@gmail.com">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-semibold">SMTP Password</label>
                                    <input type="password" name="settings[smtp_pass]" class="form-control" placeholder="Leave blank to keep current">
                                    <small class="text-muted">Password is not displayed for security. Enter a new one only if changing it.</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Admin Notification Email</label>
                                    <input type="email" name="settings[notification_email]" class="form-control"
                                           value="<?php echo htmlspecialchars(getSetting($settings, 'email', 'notification_email', SITE_EMAIL)); ?>">
                                    <small class="text-muted">Where system alerts and notifications are sent.</small>
                                </div>

                                <button type="submit" class="btn btn-info text-white"><i class="fas fa-save"></i> Save Email Settings</button>
                            </form>
                        </div>
                    </div>

                    <?php elseif ($active_tab === 'uploads'): ?>
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-upload text-warning"></i> Upload Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="group" value="uploads">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Max File Size (MB)</label>
                                    <div class="input-group" style="max-width: 180px;">
                                        <input type="number" name="settings[max_file_size_mb]" class="form-control"
                                               value="<?php echo (int)getSetting($settings, 'uploads', 'max_file_size_mb', 10); ?>"
                                               min="1" max="50">
                                        <span class="input-group-text">MB</span>
                                    </div>
                                    <small class="text-muted">Applies to resumes, portfolios, and documents. Must also be &le; <code>upload_max_filesize</code> in php.ini.</small>
                                </div>

                                <!-- Upload directory status -->
                                <div class="card bg-light mb-4">
                                    <div class="card-header bg-light"><h6 class="mb-0">Upload Directory Status</h6></div>
                                    <div class="card-body p-0">
                                        <table class="table table-sm mb-0">
                                            <thead class="table-light"><tr><th>Directory</th><th>Exists</th><th>Writable</th></tr></thead>
                                            <tbody>
                                                <?php
                                                $upload_dirs = [
                                                    'uploads/profiles'      => 'Profile Photos',
                                                    'uploads/resumes'       => 'Resumes / CVs',
                                                    'uploads/portfolios'    => 'Portfolios',
                                                    'uploads/company-logos' => 'Company Logos',
                                                    'uploads/documents'     => 'Documents',
                                                ];
                                                $base = __DIR__ . '/../../';
                                                foreach ($upload_dirs as $dir => $label):
                                                    $full = $base . $dir;
                                                    $exists   = is_dir($full);
                                                    $writable = $exists && is_writable($full);
                                                ?>
                                                    <tr>
                                                        <td><small><code><?php echo $dir; ?></code> — <?php echo $label; ?></small></td>
                                                        <td><span class="badge bg-<?php echo $exists ? 'success' : 'danger'; ?>"><?php echo $exists ? 'Yes' : 'No'; ?></span></td>
                                                        <td><span class="badge bg-<?php echo $writable ? 'success' : ($exists ? 'warning' : 'secondary'); ?>"><?php echo $writable ? 'Yes' : ($exists ? 'No' : '—'); ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> Save Upload Settings</button>
                            </form>
                        </div>
                    </div>

                    <?php elseif ($active_tab === 'security'): ?>
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-shield-alt text-danger"></i> Security Settings</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                <input type="hidden" name="group" value="security">

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Minimum Password Length</label>
                                    <div class="input-group" style="max-width: 140px;">
                                        <input type="number" name="settings[min_password_length]" class="form-control"
                                               value="<?php echo (int)getSetting($settings, 'security', 'min_password_length', 8); ?>"
                                               min="6" max="32">
                                        <span class="input-group-text">chars</span>
                                    </div>
                                    <small class="text-muted">Recommended minimum: 8 characters.</small>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-semibold">Session Lifetime</label>
                                    <div class="input-group" style="max-width: 160px;">
                                        <input type="number" name="settings[session_lifetime_hours]" class="form-control"
                                               value="<?php echo (int)getSetting($settings, 'security', 'session_lifetime_hours', 24); ?>"
                                               min="1" max="168">
                                        <span class="input-group-text">hours</span>
                                    </div>
                                    <small class="text-muted">Users are logged out after this period of inactivity. 24h recommended.</small>
                                </div>

                                <button type="submit" class="btn btn-danger"><i class="fas fa-save"></i> Save Security Settings</button>
                            </form>
                        </div>
                    </div>

                    <?php elseif ($active_tab === 'system'): ?>
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0"><i class="fas fa-server text-secondary"></i> System Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <h6 class="text-muted text-uppercase small fw-bold mb-3">Server</h6>
                                    <table class="table table-sm table-borderless">
                                        <tr><td class="text-muted">PHP Version</td><td><strong><?php echo $sys_info['php_version']; ?></strong> <?php echo version_compare($sys_info['php_version'], '8.0', '>=') ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-warning">Upgrade recommended</span>'; ?></td></tr>
                                        <tr><td class="text-muted">Database</td><td><strong>MySQL <?php echo htmlspecialchars($sys_info['db_version']); ?></strong></td></tr>
                                        <tr><td class="text-muted">Web Server</td><td><?php echo htmlspecialchars($sys_info['server']); ?></td></tr>
                                        <tr><td class="text-muted">DB Size</td><td><?php echo $sys_info['db_size']; ?> MB</td></tr>
                                        <tr><td class="text-muted">Timezone</td><td><?php echo date_default_timezone_get(); ?></td></tr>
                                        <tr><td class="text-muted">Current Time</td><td><?php echo date('d M Y H:i:s'); ?></td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="text-muted text-uppercase small fw-bold mb-3">Platform Data</h6>
                                    <table class="table table-sm table-borderless">
                                        <tr><td class="text-muted">Total Users</td><td><strong><?php echo number_format($sys_info['total_users']); ?></strong></td></tr>
                                        <tr><td class="text-muted">Total Jobs</td><td><strong><?php echo number_format($sys_info['total_jobs']); ?></strong></td></tr>
                                        <tr><td class="text-muted">PHP Memory Limit</td><td><?php echo ini_get('memory_limit'); ?></td></tr>
                                        <tr><td class="text-muted">Max Upload Size</td><td><?php echo ini_get('upload_max_filesize'); ?></td></tr>
                                        <tr><td class="text-muted">Post Max Size</td><td><?php echo ini_get('post_max_size'); ?></td></tr>
                                    </table>
                                    <h6 class="text-muted text-uppercase small fw-bold mb-3 mt-3">PHP Extensions</h6>
                                    <?php
                                    $ext_checks = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo', 'gd'];
                                    foreach ($ext_checks as $ext):
                                        $loaded = extension_loaded($ext);
                                    ?>
                                        <span class="badge bg-<?php echo $loaded ? 'success' : 'danger'; ?> me-1 mb-1"><?php echo $ext; ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
function calcPreview() {
    const commInput = document.querySelector('input[name="settings[commission_percentage]"]');
    const pct   = parseFloat(commInput ? commInput.value : <?php echo getSetting($settings, 'financial', 'commission_percentage', DEFAULT_COMMISSION_PERCENTAGE); ?>) || 0;
    const amount = parseFloat(document.getElementById('previewAmount')?.value) || 0;
    const commission = Math.round(amount * pct / 100);
    const talent     = amount - commission;
    const fmt = n => n.toLocaleString('id-ID');
    const commEl = document.getElementById('previewCommission');
    const talentEl = document.getElementById('previewTalent');
    if (commEl)   commEl.value   = fmt(commission);
    if (talentEl) talentEl.value = fmt(talent);
}
document.addEventListener('DOMContentLoaded', function () {
    calcPreview();
    const commInput = document.querySelector('input[name="settings[commission_percentage]"]');
    if (commInput) commInput.addEventListener('input', calcPreview);
});
</script>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
