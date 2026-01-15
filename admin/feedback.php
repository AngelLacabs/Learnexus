<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$page_title = "SMS Feedback - Admin Panel";

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Check if direction column exists
$hasDirectionColumn = false;
try {
    $checkStmt = $conn->query("SHOW COLUMNS FROM sms_feedback LIKE 'direction'");
    $hasDirectionColumn = $checkStmt->rowCount() > 0;
} catch (PDOException $e) {
    // Column doesn't exist, continue without it
}

// Build query - Only show inbound (received) messages if direction column exists
$whereConditions = [];
$params = [];

if ($hasDirectionColumn) {
    $whereConditions[] = "(direction = 'inbound' OR direction IS NULL)";
}

if ($statusFilter !== 'all') {
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
}

if ($search) {
    $searchTerm = "%$search%";
    $whereConditions[] = "(from_number LIKE ? OR message LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Fetch only received feedback with user info (if phone matches)
try {
    $stmt = $conn->prepare("
        SELECT 
            f.*,
            u.userID,
            u.firstName,
            u.lastName,
            u.email
        FROM sms_feedback f
        LEFT JOIN users u ON f.from_number = u.phone
        $whereClause
        ORDER BY f.createdAt DESC
    ");
    $stmt->execute($params);
    $feedbackList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    if ($hasDirectionColumn) {
        $statsStmt = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
            FROM sms_feedback
            WHERE direction = 'inbound' OR direction IS NULL
        ");
    } else {
        $statsStmt = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
                SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived
            FROM sms_feedback
        ");
    }
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Feedback Error: " . $e->getMessage());
    $feedbackList = [];
    $stats = ['total' => 0, 'unread' => 0, 'read_count' => 0, 'archived' => 0];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content pb-3 pb-lg-4 ps-3 ps-lg-4 pe-3 pe-lg-4 pt-5">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="card border-0 rounded-4 shadow-sm mb-5">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">SMS Feedback</h1>
                        <p class="text-muted mb-0">View and manage feedback messages received from users via SMS</p>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" class="d-flex">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                            <div class="input-group">
                                <input type="text" 
                                       class="form-control" 
                                       placeholder="Search by phone or message..." 
                                       name="search"
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-4 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Total Feedback</h6>
                                <h3 class="mb-0"><?php echo $stats['total']; ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-chat-dots text-primary" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Unread</h6>
                                <h3 class="mb-0 text-warning">
                                    <?php echo $stats['unread']; ?>
                                </h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="bi bi-envelope text-warning" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-4 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Read</h6>
                                <h3 class="mb-0 text-info">
                                    <?php echo $stats['read_count']; ?>
                                </h3>
                            </div>
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="bi bi-envelope-open text-info" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-body p-0">
                <ul class="nav nav-tabs border-0 px-3 pt-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $statusFilter === 'all' ? 'active' : ''; ?>" 
                           href="?status=all<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            All (<?php echo $stats['total']; ?>)
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $statusFilter === 'unread' ? 'active' : ''; ?>" 
                           href="?status=unread<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            Unread (<?php echo $stats['unread']; ?>)
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $statusFilter === 'read' ? 'active' : ''; ?>" 
                           href="?status=read<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            Read (<?php echo $stats['read_count']; ?>)
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?php echo $statusFilter === 'archived' ? 'active' : ''; ?>" 
                           href="?status=archived<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            Archived (<?php echo $stats['archived']; ?>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Feedback List -->
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-white border-0 px-4 py-3">
                <h5 class="mb-0">Feedback Messages</h5>
            </div>
            <div class="card-body px-4">
                <?php if (empty($feedbackList)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 48px; color: #ccc;"></i>
                        <h4 class="mt-3">No Feedback Found</h4>
                        <p class="text-muted">
                            <?php echo $search || $statusFilter !== 'all' ? 'No feedback matches your criteria.' : 'No feedback messages have been received yet.'; ?>
                        </p>
                        <?php if ($search || $statusFilter !== 'all'): ?>
                            <a href="feedback.php" class="btn btn-primary">
                                <i class="bi bi-arrow-left"></i> View All Feedback
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($feedbackList as $feedback): ?>
                            <div class="list-group-item px-0 py-3 border-bottom <?php echo $feedback['status'] === 'unread' ? 'bg-light' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="me-3">
                                                <i class="bi bi-telephone-fill text-primary"></i>
                                                <strong><?php echo htmlspecialchars($feedback['from_number']); ?></strong>
                                            </div>
                                            <?php if ($feedback['userID']): ?>
                                                <span class="badge bg-info me-2">
                                                    <i class="bi bi-person"></i> 
                                                    <?php echo htmlspecialchars($feedback['firstName'] . ' ' . $feedback['lastName']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if ($feedback['sim_slot']): ?>
                                                <span class="badge bg-secondary me-2">
                                                    SIM: <?php echo htmlspecialchars($feedback['sim_slot']); ?>
                                                </span>
                                            <?php endif; ?>
                                            <span class="badge bg-<?php 
                                                echo $feedback['status'] === 'unread' ? 'warning' : 
                                                    ($feedback['status'] === 'read' ? 'info' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($feedback['status']); ?>
                                            </span>
                                        </div>
                                        <p class="mb-2 text-dark" style="white-space: pre-wrap;"><?php echo htmlspecialchars($feedback['message']); ?></p>
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i> 
                                            <?php 
                                                $createdAt = strtotime($feedback['createdAt']);
                                                $timeAgo = time() - $createdAt;
                                                
                                                if ($timeAgo < 60) {
                                                    echo 'Just now';
                                                } elseif ($timeAgo < 3600) {
                                                    echo floor($timeAgo / 60) . ' minutes ago';
                                                } elseif ($timeAgo < 86400) {
                                                    echo floor($timeAgo / 3600) . ' hours ago';
                                                } else {
                                                    echo date('M d, Y h:i A', $createdAt);
                                                }
                                            ?>
                                        </small>
                                    </div>
                                    <div class="btn-group btn-group-sm ms-3">
                                        <?php if ($feedback['status'] === 'unread'): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-primary" 
                                                    onclick="updateFeedbackStatus(<?php echo $feedback['feedbackID']; ?>, 'read')"
                                                    title="Mark as read">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($feedback['status'] !== 'archived'): ?>
                                            <button type="button" 
                                                    class="btn btn-outline-secondary" 
                                                    onclick="updateFeedbackStatus(<?php echo $feedback['feedbackID']; ?>, 'archived')"
                                                    title="Archive">
                                                <i class="bi bi-archive"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    class="btn btn-outline-info" 
                                                    onclick="updateFeedbackStatus(<?php echo $feedback['feedbackID']; ?>, 'read')"
                                                    title="Restore">
                                                <i class="bi bi-arrow-counterclockwise"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            Showing <?php echo count($feedbackList); ?> feedback message<?php echo count($feedbackList) !== 1 ? 's' : ''; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateFeedbackStatus(feedbackID, status) {
    if (!confirm(`Are you sure you want to mark this feedback as ${status}?`)) {
        return;
    }
    
    fetch('feedback_action.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=update_status&feedback_id=${feedbackID}&status=${status}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: data.message,
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                // Reload page to reflect changes
                window.location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to update feedback status'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while updating feedback status'
        });
    });
}
</script>

<?php include 'includes/footer.php'; ?>
