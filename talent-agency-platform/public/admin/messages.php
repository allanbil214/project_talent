<?php
// public/admin/messages.php
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Message.php';

requireAdmin();

$pdo = require __DIR__ . '/../../config/database.php';
$db  = new Database($pdo);
$message = new Message($db);

// Selected conversation
$selected_conv_id = (int)($_GET['conversation_id'] ?? 0);

// Stats
$stats = $message->getAdminStats();

// Search filter
$search = trim($_GET['search'] ?? '');

// Get all conversations with participant info and latest message
$search_sql = '';
$search_params = [];
if ($search) {
    $search_sql = "HAVING participant_names LIKE ?";
    $search_params[] = '%' . $search . '%';
}

$conversations = $message->getConversations($search);

// Load selected conversation messages
$selected_messages = [];
$selected_participants = [];
if ($selected_conv_id) {
    $selected_messages = $message->getByConversation($selected_conv_id);
    $selected_participants = $message->getParticipants($selected_conv_id);
}

$page_title = 'Messages - ' . SITE_NAME;
$body_class = 'dashboard-page admin-dashboard';
$additional_css = ['dashboard.css'];
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="dashboard-wrapper">
    <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid p-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">Messages</h2>
                    <p class="text-muted mb-0">Monitor all platform conversations (read-only)</p>
                </div>
            </div>

            <!-- Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-primary"><?php echo $stats['total_conversations']; ?></h4>
                            <small class="text-muted">Conversations</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-info"><?php echo $stats['total_messages']; ?></h4>
                            <small class="text-muted">Total Messages</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-success"><?php echo $stats['today_messages']; ?></h4>
                            <small class="text-muted">Sent Today</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-lg-3">
                    <div class="card text-center">
                        <div class="card-body py-3">
                            <h4 class="text-warning"><?php echo $stats['unread_messages']; ?></h4>
                            <small class="text-muted">Unread</small>
                        </div>
                    </div>
                </div>
            </div>

            <?php flashMessage(); ?>

            <!-- Messenger Layout -->
            <div class="row g-0" style="height: 640px;">

                <!-- Conversation List -->
                <div class="col-lg-4 border-end" style="overflow-y: auto; height: 100%;">
                    <div class="card rounded-0 border-0 h-100">
                        <div class="card-header bg-white border-bottom px-3 py-2">
                            <form method="GET" class="d-flex gap-2">
                                <?php if ($selected_conv_id): ?>
                                    <input type="hidden" name="conversation_id" value="<?php echo $selected_conv_id; ?>">
                                <?php endif; ?>
                                <input type="text" name="search" class="form-control form-control-sm"
                                       placeholder="Search participants..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </form>
                        </div>
                        <div class="card-body p-0" style="overflow-y: auto;">
                            <?php if (empty($conversations)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="fas fa-comments fa-3x mb-3"></i>
                                    <p>No conversations found.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($conversations as $conv): ?>
                                        <?php $is_active = $selected_conv_id === (int)$conv['id']; ?>
                                        <a href="?conversation_id=<?php echo $conv['id']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                                           class="list-group-item list-group-item-action border-bottom px-3 py-3 <?php echo $is_active ? 'active' : ''; ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1 me-2 overflow-hidden">
                                                    <div class="fw-semibold small text-truncate">
                                                        <?php echo htmlspecialchars($conv['participant_names'] ?? 'Unknown'); ?>
                                                        <?php if ($conv['unread_count'] > 0): ?>
                                                            <span class="badge bg-danger ms-1"><?php echo $conv['unread_count']; ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="text-truncate <?php echo $is_active ? 'text-white-50' : 'text-muted'; ?>" style="font-size:12px;">
                                                        <?php echo htmlspecialchars(truncate($conv['last_message'] ?? '—', 55)); ?>
                                                    </div>
                                                </div>
                                                <div class="text-end flex-shrink-0">
                                                    <small class="<?php echo $is_active ? 'text-white-50' : 'text-muted'; ?>" style="font-size:11px;">
                                                        <?php echo $conv['last_message_at'] ? timeAgo($conv['last_message_at']) : '—'; ?>
                                                    </small>
                                                    <div class="<?php echo $is_active ? 'text-white-50' : 'text-muted'; ?>" style="font-size:11px;">
                                                        <?php echo (int)$conv['message_count']; ?> msg
                                                    </div>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Message View -->
                <div class="col-lg-8" style="height: 100%; display: flex; flex-direction: column;">
                    <?php if (!$selected_conv_id): ?>
                        <div class="d-flex align-items-center justify-content-center h-100 text-muted bg-light">
                            <div class="text-center">
                                <i class="fas fa-hand-point-left fa-3x mb-3"></i>
                                <p>Select a conversation to view messages.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Participants Header -->
                        <div class="bg-white border-bottom px-4 py-3 d-flex align-items-center gap-3">
                            <div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php foreach ($selected_participants as $p): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?php echo getAvatarUrl($p['profile_photo_url']); ?>"
                                                 width="30" height="30" class="rounded-circle" style="object-fit:cover;">
                                            <div>
                                                <span class="small fw-semibold"><?php echo htmlspecialchars($p['display_name']); ?></span>
                                                <span class="badge bg-<?php echo $p['role'] === 'talent' ? 'primary' : ($p['role'] === 'employer' ? 'warning text-dark' : 'secondary'); ?> ms-1" style="font-size:10px;">
                                                    <?php echo ucfirst($p['role']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <?php if ($p !== end($selected_participants)): ?>
                                            <span class="text-muted">↔</span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted">Conversation #<?php echo $selected_conv_id; ?> &middot; <?php echo count($selected_messages); ?> messages</small>
                            </div>
                        </div>

                        <!-- Messages -->
                        <div class="flex-grow-1 p-4 bg-light" style="overflow-y: auto;" id="messageContainer">
                            <?php if (empty($selected_messages)): ?>
                                <div class="text-center text-muted py-5">No messages in this conversation.</div>
                            <?php else: ?>
                                <?php
                                $prev_date = null;
                                foreach ($selected_messages as $msg):
                                    $msg_date = date('Y-m-d', strtotime($msg['sent_at']));
                                    if ($msg_date !== $prev_date):
                                        $prev_date = $msg_date;
                                ?>
                                    <div class="text-center mb-3">
                                        <span class="badge bg-light text-muted border" style="font-size:11px;">
                                            <?php echo formatDate($msg['sent_at']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <div class="d-flex gap-2 mb-3 <?php echo $msg['sender_role'] === 'employer' ? 'flex-row-reverse' : ''; ?>">
                                    <img src="<?php echo getAvatarUrl($msg['profile_photo_url']); ?>"
                                         width="32" height="32" class="rounded-circle flex-shrink-0" style="object-fit:cover; align-self:flex-end;">
                                    <div style="max-width: 70%;">
                                        <div class="small text-muted mb-1 <?php echo $msg['sender_role'] === 'employer' ? 'text-end' : ''; ?>">
                                            <?php echo htmlspecialchars($msg['sender_name']); ?>
                                            <span class="badge bg-<?php echo $msg['sender_role'] === 'talent' ? 'primary' : ($msg['sender_role'] === 'employer' ? 'warning text-dark' : 'secondary'); ?>" style="font-size:9px;">
                                                <?php echo ucfirst($msg['sender_role']); ?>
                                            </span>
                                        </div>
                                        <div class="rounded-3 p-3 <?php echo $msg['sender_role'] === 'employer' ? 'bg-primary text-white' : 'bg-white border'; ?>">
                                            <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                                        </div>
                                        <div class="small text-muted mt-1 <?php echo $msg['sender_role'] === 'employer' ? 'text-end' : ''; ?>">
                                            <?php echo date('H:i', strtotime($msg['sent_at'])); ?>
                                            <?php if ($msg['read_status']): ?>
                                                <i class="fas fa-check-double text-primary" title="Read"></i>
                                            <?php else: ?>
                                                <i class="fas fa-check text-muted" title="Unread"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Read-only notice -->
                        <div class="bg-white border-top px-4 py-3">
                            <div class="alert alert-light border mb-0 py-2 text-muted small text-center">
                                <i class="fas fa-eye"></i> Admin view — read-only. Messages are between platform users.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <!-- end messenger layout -->

        </div>
    </div>
</div>

<script>
// Scroll to bottom of messages on load
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('messageContainer');
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
});
</script>

<?php
$additional_js = ['dashboard.js'];
require_once __DIR__ . '/../../includes/footer.php';
?>
