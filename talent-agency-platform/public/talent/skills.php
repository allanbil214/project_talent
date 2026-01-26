<?php
// public/talent/skills.php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

requireRole(ROLE_TALENT);

$db = require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Talent.php';
require_once __DIR__ . '/../../classes/Skill.php';

$database = new Database($db);
$talent_model = new Talent($database);
$skill_model = new Skill($database);

$user_id = getCurrentUserId();
$talent = $talent_model->getByUserId($user_id);

if (!$talent) {
    redirect(SITE_URL . '/public/talent/profile.php');
}

$my_skills = $talent_model->getSkills($talent['id']);
$all_skills = $skill_model->getAll();

// Group skills by category
$skills_by_category = [];
foreach ($all_skills as $skill) {
    $category = $skill['category'] ?? 'Other';
    if (!isset($skills_by_category[$category])) {
        $skills_by_category[$category] = [];
    }
    $skills_by_category[$category][] = $skill;
}

$page_title = 'My Skills - ' . SITE_NAME;
$body_class = 'dashboard-page';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="container-fluid p-4">
            <!-- Page Header -->
            <div class="page-header">
                <h2><i class="fas fa-award"></i> My Skills</h2>
                <p class="text-muted mb-0">Manage your professional skills and expertise</p>
            </div>
            
            <div class="row">
                <!-- My Skills -->
                <div class="col-lg-5 mb-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-star text-warning"></i> My Skills (<?php echo count($my_skills); ?>)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($my_skills)): ?>
                                <div id="mySkillsList">
                                    <?php foreach ($my_skills as $skill): ?>
                                        <div class="skill-item mb-3 p-3 border rounded" data-skill-id="<?php echo $skill['id']; ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($skill['name']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($skill['category'] ?? 'General'); ?></small>
                                                </div>
                                                <button class="btn btn-sm btn-danger remove-skill-btn" 
                                                        data-skill-id="<?php echo $skill['id']; ?>">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <div>
                                                <label class="form-label small mb-1">Proficiency Level</label>
                                                <select class="form-select form-select-sm proficiency-select" 
                                                        data-skill-id="<?php echo $skill['id']; ?>">
                                                    <option value="<?php echo PROFICIENCY_BEGINNER; ?>" 
                                                        <?php echo $skill['proficiency_level'] == PROFICIENCY_BEGINNER ? 'selected' : ''; ?>>
                                                        Beginner
                                                    </option>
                                                    <option value="<?php echo PROFICIENCY_INTERMEDIATE; ?>" 
                                                        <?php echo $skill['proficiency_level'] == PROFICIENCY_INTERMEDIATE ? 'selected' : ''; ?>>
                                                        Intermediate
                                                    </option>
                                                    <option value="<?php echo PROFICIENCY_ADVANCED; ?>" 
                                                        <?php echo $skill['proficiency_level'] == PROFICIENCY_ADVANCED ? 'selected' : ''; ?>>
                                                        Advanced
                                                    </option>
                                                    <option value="<?php echo PROFICIENCY_EXPERT; ?>" 
                                                        <?php echo $skill['proficiency_level'] == PROFICIENCY_EXPERT ? 'selected' : ''; ?>>
                                                        Expert
                                                    </option>
                                                </select>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-award"></i>
                                    <p>No skills added yet</p>
                                    <p class="small text-muted">Add skills from the list to improve your profile</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Available Skills -->
                <div class="col-lg-7 mb-4">
                    <div class="card">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fas fa-plus-circle text-primary"></i> Add Skills
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Search Box -->
                            <div class="mb-3">
                                <input type="text" class="form-control" id="skillSearch" 
                                       placeholder="Search skills...">
                            </div>
                            
                            <!-- Skills by Category -->
                            <div id="skillsList">
                                <?php foreach ($skills_by_category as $category => $skills): ?>
                                    <div class="skill-category mb-3">
                                        <h6 class="text-primary mb-2">
                                            <i class="fas fa-folder"></i> <?php echo htmlspecialchars($category); ?>
                                        </h6>
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php foreach ($skills as $skill): ?>
                                                <?php
                                                // Check if talent already has this skill
                                                $has_skill = false;
                                                foreach ($my_skills as $my_skill) {
                                                    if ($my_skill['id'] == $skill['id']) {
                                                        $has_skill = true;
                                                        break;
                                                    }
                                                }
                                                ?>
                                                <button class="btn btn-sm <?php echo $has_skill ? 'btn-success disabled' : 'btn-outline-primary'; ?> add-skill-btn" 
                                                        data-skill-id="<?php echo $skill['id']; ?>"
                                                        data-skill-name="<?php echo htmlspecialchars($skill['name']); ?>"
                                                        <?php echo $has_skill ? 'disabled' : ''; ?>>
                                                    <?php if ($has_skill): ?>
                                                        <i class="fas fa-check"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-plus"></i>
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($skill['name']); ?>
                                                </button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <!-- Add Custom Skill -->
                            <div class="mt-4 pt-3 border-top">
                                <h6 class="mb-3">Don't see your skill? Add a custom one:</h6>
                                <form id="addCustomSkillForm" class="row g-2">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" id="customSkillName" 
                                               name="skill_name" placeholder="Skill name" required>
                                    </div>
                                    <div class="col-md-4">
                                        <input type="text" class="form-control" id="customSkillCategory" 
                                               name="category" placeholder="Category (optional)">
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-plus"></i> Add
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Skill Modal -->
<div class="modal fade" id="addSkillModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Skill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addSkillForm">
                    <input type="hidden" id="selectedSkillId" name="skill_id">
                    <div class="mb-3">
                        <label class="form-label">Skill</label>
                        <input type="text" class="form-control" id="selectedSkillName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Proficiency Level</label>
                        <select class="form-select" name="proficiency_level" required>
                            <option value="<?php echo PROFICIENCY_BEGINNER; ?>">Beginner</option>
                            <option value="<?php echo PROFICIENCY_INTERMEDIATE; ?>" selected>Intermediate</option>
                            <option value="<?php echo PROFICIENCY_ADVANCED; ?>">Advanced</option>
                            <option value="<?php echo PROFICIENCY_EXPERT; ?>">Expert</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAddSkill">Add Skill</button>
            </div>
        </div>
    </div>
</div>

<?php
$additional_js = ['skills.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>