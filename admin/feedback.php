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
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Validate status filter - Add 'archived' to the allowed statuses
$allowedStatuses = ['all', 'unread', 'read', 'archived', 'sent', 'failed'];
if (!in_array($statusFilter, $allowedStatuses)) {
    $statusFilter = 'all';
}

// Debug: Check what's in the database
error_log("=== FEEDBACK DEBUG START ===");
error_log("Status filter from URL: '$statusFilter'");

// First, let's check what statuses exist in the database
try {
    $debugStmt = $conn->query("SELECT DISTINCT status, COUNT(*) as count FROM sms_feedback GROUP BY status");
    $debugResults = $debugStmt->fetchAll(PDO::FETCH_ASSOC);
    error_log("Statuses in database:");
    foreach ($debugResults as $row) {
        error_log("  Status: '{$row['status']}' = {$row['count']} messages");
    }
} catch (Exception $e) {
    error_log("Debug query failed: " . $e->getMessage());
}

// Build query
$whereConditions = [];
$params = [];

// Apply status filter if not 'all'
if ($statusFilter !== 'all') {
    // Remove BINARY - use regular comparison for ENUM fields
    $whereConditions[] = "status = ?";
    $params[] = $statusFilter;
    error_log("Adding status filter: '$statusFilter'");
}

// Apply search filter
if ($search) {
    $searchTerm = "%$search%";
    $whereConditions[] = "(from_number LIKE ? OR message LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    error_log("Adding search filter: '$search'");
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
error_log("Final WHERE clause: '$whereClause'");
error_log("Number of parameters: " . count($params));

// Fetch feedback messages
try {
    $query = "
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
    ";
    
    error_log("Query: $query");
    
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        // Execute with parameters
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    
    $feedbackList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("Found " . count($feedbackList) . " messages for filter: '$statusFilter'");
    
    // Log sample of results
    if (!empty($feedbackList)) {
        error_log("Sample messages:");
        $sampleMessages = array_slice($feedbackList, 0, 3);
        foreach ($sampleMessages as $index => $msg) {
            error_log("  [$index] ID: {$msg['feedbackID']}, Status: '{$msg['status']}', Phone: {$msg['from_number']}");
        }
    } else {
        error_log("No messages found. Let's test the query directly:");
        
        // Try a direct query to see what's wrong
        if ($statusFilter !== 'all') {
            $testQuery = "SELECT COUNT(*) as count FROM sms_feedback WHERE status = ?";
            $testStmt = $conn->prepare($testQuery);
            $testStmt->execute([$statusFilter]);
            $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
            error_log("Direct count query for status '$statusFilter': " . $testResult['count'] . " messages");
        }
    }
    
} catch (PDOException $e) {
    error_log("Feedback Query Error: " . $e->getMessage());
    error_log("Query: " . $query);
    error_log("Params: " . json_encode($params));
    $feedbackList = [];
}

// Get statistics - count ALL messages in database
// Include 'archived' in the statistics query
try {
    $statsStmt = $conn->query("
        SELECT 
            COUNT(*) as total,
            COALESCE(SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END), 0) as unread,
            COALESCE(SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END), 0) as read_count,
            COALESCE(SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END), 0) as archived,
            COALESCE(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END), 0) as sent,
            COALESCE(SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END), 0) as failed
        FROM sms_feedback
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    error_log("Statistics - Total: {$stats['total']}, Unread: {$stats['unread']}, Read: {$stats['read_count']}, Archived: {$stats['archived']}");
} catch (PDOException $e) {
    error_log("Statistics Query Error: " . $e->getMessage());
    $stats = null;
}

error_log("=== FEEDBACK DEBUG END ===");

// Ensure stats always has all required keys with integer values
if (!isset($stats) || !is_array($stats)) {
    $stats = ['total' => 0, 'unread' => 0, 'read_count' => 0, 'archived' => 0, 'sent' => 0, 'failed' => 0];
} else {
    $stats = array_merge([
        'total' => 0,
        'unread' => 0,
        'read_count' => 0,
        'archived' => 0,
        'sent' => 0,
        'failed' => 0
    ], $stats);
}

// Convert all values to integers
$stats = array_map(function($value) {
    if ($value === null || $value === false) return 0;
    return (int)$value;
}, $stats);

// Ensure feedbackList is always an array
if (!isset($feedbackList) || !is_array($feedbackList)) {
    $feedbackList = [];
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
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-xl-3 col-md-6">
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
            
            <div class="col-xl-3 col-md-6">
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
            
            <div class="col-xl-3 col-md-6">
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
            
            <div class="col-xl-3 col-md-6">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-1">Archived</h6>
                                <h3 class="mb-0 text-secondary">
                                    <?php echo $stats['archived']; ?>
                                </h3>
                            </div>
                            <div class="bg-secondary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-archive text-secondary" style="font-size: 24px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Delete Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-0 rounded-4 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <form method="GET" class="d-flex flex-grow-1" style="min-width: 300px;">
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                                <div class="input-group flex-grow-1">
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
                            <?php if (!empty($feedbackList)): ?>
                                <button type="button" 
                                        class="btn btn-danger" 
                                        onclick="deleteSelectedFeedback()"
                                        id="deleteBtn"
                                        style="display: none;">
                                    <i class="bi bi-trash"></i> Delete Selected (<span id="selectedCount">0</span>)
                                </button>
                            <?php endif; ?>
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
                            <?php 
                            if ($search) {
                                echo 'No feedback matches your search criteria.';
                            } elseif ($statusFilter !== 'all') {
                                echo "No feedback messages with status: <strong>" . htmlspecialchars($statusFilter) . "</strong>";
                                // Get the correct stats key for the current filter
                                $statsKey = strtolower($statusFilter);
                                if ($statsKey === 'read') {
                                    $statsKey = 'read_count';
                                }
                                if (isset($stats[$statsKey]) && $stats[$statsKey] > 0) {
                                    echo "<br><small class='text-danger'>Statistics show there should be " . $stats[$statsKey] . " messages</small>";
                                }
                            } else {
                                echo 'No feedback messages have been received yet.';
                            }
                            ?>
                        </p>
                        <?php if ($search || $statusFilter !== 'all'): ?>
                            <a href="feedback.php" class="btn btn-primary mt-2">
                                <i class="bi bi-arrow-left"></i> View All Feedback
                            </a>
                            <?php if ($statusFilter === 'unread' && $stats['unread'] > 0): ?>
                                <br>
                                <small class="text-warning mt-2 d-block">
                                    <i class="bi bi-exclamation-triangle"></i> 
                                    Database shows <?php echo $stats['unread']; ?> unread messages, but query returned none.
                                </small>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($feedbackList as $feedback): ?>
                            <div class="list-group-item px-0 py-3 border-bottom <?php echo strtolower($feedback['status']) === 'unread' ? 'bg-light' : ''; ?>">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="form-check me-3 mt-1">
                                        <input class="form-check-input feedback-checkbox" 
                                               type="checkbox" 
                                               value="<?php echo $feedback['feedbackID']; ?>"
                                               onchange="updateDeleteButton()">
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="me-3">
                                                <?php if (isset($feedback['direction']) && $feedback['direction'] === 'outbound'): ?>
                                                    <i class="bi bi-arrow-up-circle-fill text-success"></i>
                                                    <strong>To: <?php echo htmlspecialchars($feedback['from_number']); ?></strong>
                                                <?php else: ?>
                                                    <i class="bi bi-telephone-fill text-primary"></i>
                                                    <strong>From: <?php echo htmlspecialchars($feedback['from_number']); ?></strong>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (isset($feedback['direction']) && $feedback['direction'] === 'outbound'): ?>
                                                <span class="badge bg-success me-2">
                                                    <i class="bi bi-send"></i> Outbound
                                                </span>
                                            <?php elseif (isset($feedback['direction']) && $feedback['direction'] === 'inbound'): ?>
                                                <span class="badge bg-primary me-2">
                                                    <i class="bi bi-inbox"></i> Inbound
                                                </span>
                                            <?php endif; ?>
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
                                                $status = strtolower($feedback['status']);
                                                if ($status === 'unread') echo 'warning';
                                                elseif ($status === 'read') echo 'info';
                                                elseif ($status === 'archived') echo 'secondary';
                                                elseif ($status === 'sent') echo 'success';
                                                elseif ($status === 'failed') echo 'danger';
                                                else echo 'secondary';
                                            ?>">
                                                <?php echo htmlspecialchars($feedback['status']); ?>
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
                                            <?php if ($feedback['readAt'] && strtolower($feedback['status']) === 'read'): ?>
                                                <br><i class="bi bi-eye"></i> Read: <?php echo date('M d, Y h:i A', strtotime($feedback['readAt'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="d-flex gap-2 ms-3 me-3" style="flex-shrink: 0;">
                                        <?php 
                                        $status = strtolower($feedback['status']);
                                        $canUpdateStatus = in_array($status, ['unread', 'read', 'archived']);
                                        ?>
                                        <?php if ($canUpdateStatus && $status === 'unread'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm" 
                                                    onclick="updateFeedbackStatus(<?php echo $feedback['feedbackID']; ?>, 'read')"
                                                    title="Mark as read"
                                                    style="background: linear-gradient(135deg,rgb(251, 187, 235) 0%,rgb(160, 67, 180) 100%); border: none; color: white; padding: 0.25rem 0.75rem; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                Mark as Read
                                            </button>
                                        <?php elseif ($canUpdateStatus && $status === 'read'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm" 
                                                    onclick="updateFeedbackStatus(<?php echo $feedback['feedbackID']; ?>, 'archived')"
                                                    title="Mark as archived"
                                                    style="background: linear-gradient(135deg, #6a11cb 0%, #2575fc 100%); border: none; color: white; padding: 0.25rem 0.75rem; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                Archive
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm" 
                                                    onclick="updateFeedbackStatus(<?php echo $feedback['feedbackID']; ?>, 'unread')"
                                                    title="Mark as unread"
                                                    style="background: linear-gradient(135deg, #fadb61 0%, #f093fb 100%); border: none; color: white; padding: 0.25rem 0.75rem; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                Mark as Unread
                                            </button>
                                        <?php elseif ($canUpdateStatus && $status === 'archived'): ?>
                                            <button type="button" 
                                                    class="btn btn-sm" 
                                                    onclick="updateFeedbackStatus(<?php echo $feedback['feedbackID']; ?>, 'read')"
                                                    title="Mark as read"
                                                    style="background: linear-gradient(135deg,rgb(251, 187, 235) 0%,rgb(160, 67, 180) 100%); border: none; color: white; padding: 0.25rem 0.75rem; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                Mark as Read
                                            </button>
                                            <button type="button" 
                                                    class="btn btn-sm" 
                                                    onclick="updateFeedbackStatus(<?php echo $feedback['feedbackID']; ?>, 'unread')"
                                                    title="Mark as unread"
                                                    style="background: linear-gradient(135deg, #fadb61 0%, #f093fb 100%); border: none; color: white; padding: 0.25rem 0.75rem; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                                Mark as Unread
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" 
                                                class="btn btn-sm" 
                                                onclick="deleteSingleFeedback(<?php echo $feedback['feedbackID']; ?>)"
                                                title="Delete this feedback"
                                                style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); border: none; color: white; padding: 0.25rem 0.75rem; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            Showing <?php echo count($feedbackList); ?> feedback message<?php echo count($feedbackList) !== 1 ? 's' : ''; ?>
                            <?php if ($statusFilter !== 'all'): ?>
                                with status: <strong><?php echo htmlspecialchars($statusFilter); ?></strong>
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function updateDeleteButton() {
    const checkboxes = document.querySelectorAll('.feedback-checkbox:checked');
    const deleteBtn = document.getElementById('deleteBtn');
    const selectedCount = document.getElementById('selectedCount');
    if (deleteBtn) {
        deleteBtn.style.display = checkboxes.length > 0 ? 'inline-block' : 'none';
    }
    if (selectedCount) {
        selectedCount.textContent = checkboxes.length;
    }
}

function deleteSingleFeedback(feedbackID) {
    Swal.fire({
        icon: 'warning',
        title: 'Delete Feedback?',
        text: 'Are you sure you want to delete this feedback message? This action cannot be undone.',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('feedback_action.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete&feedback_id=${feedbackID}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: data.message || 'Feedback deleted successfully',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Reload page to reflect changes, preserving current filters
                        const urlParams = new URLSearchParams(window.location.search);
                        const status = urlParams.get('status') || 'all';
                        const search = urlParams.get('search') || '';
                        
                        let reloadUrl = 'feedback.php';
                        const params = [];
                        if (status !== 'all') params.push('status=' + encodeURIComponent(status));
                        if (search) params.push('search=' + encodeURIComponent(search));
                        if (params.length > 0) reloadUrl += '?' + params.join('&');
                        
                        window.location.href = reloadUrl;
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'Failed to delete feedback'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while deleting feedback'
                });
            });
        }
    });
}

function deleteSelectedFeedback() {
    const checkboxes = document.querySelectorAll('.feedback-checkbox:checked');
    const selectedIds = Array.from(checkboxes).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'No Selection',
            text: 'Please select at least one feedback to delete'
        });
        return;
    }
    
    Swal.fire({
        icon: 'warning',
        title: 'Delete Feedback?',
        text: `Are you sure you want to delete ${selectedIds.length} feedback message(s)? This action cannot be undone.`,
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Delete each selected feedback
            const deletePromises = selectedIds.map(id => {
                return fetch('feedback_action.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&feedback_id=${id}`
                }).then(response => response.json());
            });
            
            Promise.all(deletePromises)
                .then(results => {
                    const successCount = results.filter(r => r.success).length;
                    const failCount = results.length - successCount;
                    
                    if (failCount === 0) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: `${successCount} feedback message(s) deleted successfully`,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            // Reload page to reflect changes, preserving current filters
                            const urlParams = new URLSearchParams(window.location.search);
                            const status = urlParams.get('status') || 'all';
                            const search = urlParams.get('search') || '';
                            
                            let reloadUrl = 'feedback.php';
                            const params = [];
                            if (status !== 'all') params.push('status=' + encodeURIComponent(status));
                            if (search) params.push('search=' + encodeURIComponent(search));
                            if (params.length > 0) reloadUrl += '?' + params.join('&');
                            
                            window.location.href = reloadUrl;
                        });
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Partial Success',
                            text: `${successCount} deleted, ${failCount} failed`
                        }).then(() => {
                            window.location.reload();
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while deleting feedback'
                    });
                });
        }
    });
}

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
                // Reload page to reflect changes, preserving current filters
                const urlParams = new URLSearchParams(window.location.search);
                const status = urlParams.get('status') || 'all';
                const search = urlParams.get('search') || '';
                
                let reloadUrl = 'feedback.php';
                const params = [];
                if (status !== 'all') params.push('status=' + encodeURIComponent(status));
                if (search) params.push('search=' + encodeURIComponent(search));
                if (params.length > 0) reloadUrl += '?' + params.join('&');
                
                window.location.href = reloadUrl;
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