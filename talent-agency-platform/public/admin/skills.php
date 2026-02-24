<?php
// public/admin/skills.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Skill.php';

requireAdmin();

$pdo   = require __DIR__ . '/../../config/database.php';
$db    = new Database($pdo);
$skill = new Skill($db);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
        redirect(SITE_URL . '/public/admin/skills.php');
    }

    $action   = $_POST['action'] ?? '';
    $skill_id = (int)($_POST['skill_id'] ?? 0);
    $name     = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');

    try {
        if ($action === 'create') {
            if (empty($name)) throw new Exception('Skill name is required.');
            if ($skill->nameExists($name)) throw new Exception('Skill name already exists.');
            $skill->create(['name' => $name, 'category' => $category ?: null]);
            setFlash('success', "Skill '{$name}' created.");
        } elseif ($action === 'update' && $skill_id > 0) {
            if (empty($name)) throw new Exception('Skill name is required.');
            if ($skill->nameExists($name, $skill_id)) throw new Exception('Skill name already exists.');
            $skill->update($skill_id, ['name' => $name, 'category' => $category ?: null]);
            setFlash('success', 'Skill updated.');
        } elseif ($action === 'delete' && $skill_id > 0) {
            $skill->delete($skill_id);
            setFlash('success', 'Skill deleted.');
        }
    } catch (Exception $e) {
        setFlash('error', $e->getMessage());
    }

    redirect(SITE_URL . '/public/admin/skills.php');
}

// Get all skills with talent count
$skills     = $skill->getWithTalentCount();
$categories = $skill->getCategories();

$page_title     = 'Manage Skills - ' . SITE_NAME;
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
                    <h2 class="mb-0">Manage Skills</h2>
                    <p class="text-muted mb-0">Create and manage the platform skill taxonomy</p>
                </div>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSkillModal">
                    <i class="fas fa-plus"></i> Add Skill
                </button>
            </div>

            <?php flashMessage(); ?>

            <div class="row">
                <!-- Skills by Category -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Skills <span class="badge bg-secondary"><?php echo count($skills); ?></span></h5>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($skills)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-tags fa-3x mb-3"></i>
                                    <p>No skills yet. Add some!</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0" id="skillsTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Skill Name</th>
                                                <th>Category</th>
                                                <th>Talents</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($skills as $s): ?>
                                                <tr>
                                                    <td class="fw-semibold"><?php echo htmlspecialchars($s['name']); ?></td>
                                                    <td>
                                                        <?php if ($s['category']): ?>
                                                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($s['category']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info text-white"><?php echo (int)$s['talent_count']; ?></span>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-1">
                                                            <button class="btn btn-sm btn-outline-primary"
                                                                    onclick="editSkill(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'])); ?>', '<?php echo htmlspecialchars(addslashes($s['category'] ?? '')); ?>')"
                                                                    title="Edit">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <form method="POST" class="d-inline"
                                                                  onsubmit="return confirm('Delete skill \'<?php echo htmlspecialchars(addslashes($s['name'])); ?>\'? This will remove it from all talent profiles.')">
                                                                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                                                                <input type="hidden" name="action" value="delete">
                                                                <input type="hidden" name="skill_id" value="<?php echo $s['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
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
                    </div>
                </div>

                <!-- Categories Summary -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Categories</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $category_counts = [];
                            foreach ($skills as $s) {
                                $cat = $s['category'] ?? 'Uncategorized';
                                $category_counts[$cat] = ($category_counts[$cat] ?? 0) + 1;
                            }
                            arsort($category_counts);
                            ?>
                            <?php if (empty($category_counts)): ?>
                                <p class="text-muted">No categories yet.</p>
                            <?php else: ?>
                                <?php foreach ($category_counts as $cat => $count): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span><?php echo htmlspecialchars($cat); ?></span>
                                        <span class="badge bg-primary"><?php echo $count; ?> skills</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">Top 10 Skills</h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $top = array_slice(array_filter($skills, fn($s) => $s['talent_count'] > 0), 0, 10);
                            usort($top, fn($a, $b) => $b['talent_count'] - $a['talent_count']);
                            foreach ($top as $s):
                            ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="small"><?php echo htmlspecialchars($s['name']); ?></span>
                                    <span class="badge bg-success"><?php echo $s['talent_count']; ?></span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($top)): ?>
                                <p class="text-muted small">No talent-skill associations yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Create Skill Modal -->
<div class="modal fade" id="createSkillModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="create">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Skill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Skill Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g., JavaScript">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" class="form-control" list="categoryList" placeholder="e.g., Programming">
                        <datalist id="categoryList">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <small class="text-muted">Optional. Start typing to reuse existing categories.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Skill</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Skill Modal -->
<div class="modal fade" id="editSkillModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="skill_id" id="editSkillId">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Skill</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Skill Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editSkillName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <input type="text" name="category" id="editSkillCategory" class="form-control" list="categoryList">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editSkill(id, name, category) {
    document.getElementById('editSkillId').value       = id;
    document.getElementById('editSkillName').value     = name;
    document.getElementById('editSkillCategory').value = category;
    new bootstrap.Modal(document.getElementById('editSkillModal')).show();
}

// Simple client-side search
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.createElement('input');
    searchInput.type  = 'text';
    searchInput.className = 'form-control form-control-sm mb-3';
    searchInput.placeholder = 'Filter skills...';
    document.querySelector('#skillsTable').before(searchInput);

    searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#skillsTable tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});
</script>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
