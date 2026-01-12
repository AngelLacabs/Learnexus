<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: enrollments.php');
    exit();
}

$enrollmentID = (int)$_GET['id'];

// Fetch enrollment details
try {
    $stmt = $conn->prepare("
        SELECT e.*, u.firstName, u.lastName, c.title as courseTitle 
        FROM enrollments e 
        JOIN users u ON e.userID = u.userID 
        JOIN courses c ON e.courseID = c.courseID 
        WHERE e.enrollmentID = ?
    ");
    $stmt->execute([$enrollmentID]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment) {
        $_SESSION['error'] = 'Enrollment not found';
        header('Location: enrollments.php');
        exit();
    }
} catch (PDOException $e) {
    error_log("Enrollment Edit Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading enrollment details';
    header('Location: enrollments.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $progressPercentage = (float)$_POST['progressPercentage'];
    $status = $_POST['status'];
    
    // If marking as completed and not already completed
    if ($status === 'completed' && $enrollment['status'] !== 'completed') {
        $completedAt = date('Y-m-d H:i:s');
    } else {
        $completedAt = $enrollment['completedAt'];
    }
    
    try {
        $stmt = $conn->prepare("
            UPDATE enrollments SET 
                progressPercentage = ?,
                status = ?,
                completedAt = ?
            WHERE enrollmentID = ?
        ");
        
        $stmt->execute([
            $progressPercentage,
            $status,
            $completedAt,
            $enrollmentID
        ]);
        
        $_SESSION['success'] = 'Enrollment updated successfully!';
        header("Location: enrollment_view.php?id=$enrollmentID");
        exit();
        
    } catch (PDOException $e) {
        error_log("Enrollment Update Error: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating enrollment: ' . $e->getMessage();
    }
}

$page_title = "Edit Enrollment - " . $enrollment['firstName'] . ' ' . $enrollment['lastName'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="enrollment_view.php?id=<?php echo $enrollmentID; ?>" class="btn btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h3 mb-0">Edit Enrollment</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="enrollments.php">Enrollments</a></li>
                            <li class="breadcrumb-item"><a href="enrollment_view.php?id=<?php echo $enrollmentID; ?>"><?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Edit</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Update Enrollment Details</h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['error']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <!-- Enrollment Summary -->
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Student:</h6>
                                        <p class="mb-0">
                                            <strong><?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?></strong>
                                        </p>
                                        <small class="text-muted">ID: <?php echo $enrollment['userID']; ?></small>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Course:</h6>
                                        <p class="mb-0">
                                            <strong><?php echo htmlspecialchars($enrollment['courseTitle']); ?></strong>
                                        </p>
                                        <small class="text-muted">ID: <?php echo $enrollment['courseID']; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Progress Percentage *</label>
                                    <div class="input-group">
                                        <input type="number" 
                                               class="form-control" 
                                               name="progressPercentage" 
                                               min="0" 
                                               max="100" 
                                               step="0.1"
                                               value="<?php echo number_format($enrollment['progressPercentage'], 1); ?>" 
                                               required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                    <small class="text-muted">Current progress: 0-100%</small>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status *</label>
                                    <select class="form-select" name="status" required>
                                        <option value="active" <?php echo $enrollment['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="completed" <?php echo $enrollment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="dropped" <?php echo $enrollment['status'] === 'dropped' ? 'selected' : ''; ?>>Dropped</option>
                                        <option value="pending" <?php echo $enrollment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    </select>
                                    <?php if ($enrollment['completedAt']): ?>
                                        <small class="text-muted">
                                            Completed on: <?php echo date('F d, Y', strtotime($enrollment['completedAt'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Add any notes about this enrollment..."><?php echo htmlspecialchars($enrollment['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Enrollment Dates -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Enrollment Date</label>
                                    <input type="text" 
                                           class="form-control" 
                                           value="<?php echo date('F d, Y h:i A', strtotime($enrollment['enrolledAt'])); ?>" 
                                           readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Updated</label>
                                    <input type="text" 
                                           class="form-control" 
                                           value="<?php echo date('F d, Y h:i A', strtotime($enrollment['enrolledAt'])); ?>" 
                                           readonly>
                                </div>
                            </div>
                            
                            <!-- Warning for completed status -->
                            <div class="alert alert-warning mb-4" id="completedWarning" style="display: none;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> Marking as completed will set the completion date to now. This action cannot be undone.
                            </div>
                            
                            <!-- Warning for dropped status -->
                            <div class="alert alert-danger mb-4" id="droppedWarning" style="display: none;">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Warning:</strong> Dropping an enrollment will prevent the student from accessing course content. Consider setting to "pending" instead.
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="enrollment_view.php?id=<?php echo $enrollmentID; ?>" class="btn btn-secondary">
                                    Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save me-2"></i>Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.querySelector('select[name="status"]');
    const completedWarning = document.getElementById('completedWarning');
    const droppedWarning = document.getElementById('droppedWarning');
    
    function updateWarnings() {
        const status = statusSelect.value;
        
        // Hide all warnings first
        completedWarning.style.display = 'none';
        droppedWarning.style.display = 'none';
        
        // Show appropriate warning
        if (status === 'completed') {
            completedWarning.style.display = 'block';
        } else if (status === 'dropped') {
            droppedWarning.style.display = 'block';
        }
    }
    
    // Initial check
    updateWarnings();
    
    // Update on change
    statusSelect.addEventListener('change', updateWarnings);
});
</script>

<?php include 'includes/footer.php'; ?>