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
        <!-- Page Header - Updated to match other pages -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="bg-white rounded-3 shadow-sm p-3 w-100">
                <div class="d-flex align-items-center">
                    <a href="enrollment_view.php?id=<?php echo $enrollmentID; ?>" class="btn btn-outline-secondary me-3" id="backButton">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div class="flex-grow-1">
                        <h1 class="h3 mb-0">Edit Enrollment</h1>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb mb-0">
                                <li class="breadcrumb-item"><a href="dashboard.php" class="fw-bold text-primary">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="enrollments.php" class="fw-bold text-primary">Enrollments</a></li>
                                <li class="breadcrumb-item"><a href="enrollment_view.php?id=<?php echo $enrollmentID; ?>" class="fw-bold text-primary"><?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?></a></li>
                                <li class="breadcrumb-item active text-dark" aria-current="page">Edit</li>
                            </ol>
                        </nav>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Form -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border-radius: 0.75rem;">
                    <div class="card-header border-0" style="background: transparent;">
                        <h5 class="mb-0 text-white fw-bold d-flex align-items-center">
                            <i class="bi bi-pencil-square me-2"></i>Update Enrollment Details
                        </h5>
                    </div>
                    <div class="card-body" style="background: rgba(255, 255, 255, 0.95); border-radius: 0 0 0.75rem 0.75rem;">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <?php echo $_SESSION['error']; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <!-- Enrollment Summary -->
                        <div class="card mb-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 0.5rem;">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="text-white mb-2">Student Information</h6>
                                        <p class="mb-0 text-white fw-medium">
                                            <?php echo htmlspecialchars($enrollment['firstName'] . ' ' . $enrollment['lastName']); ?>
                                        </p>
                                        <small class="text-white-50">ID: <?php echo $enrollment['userID']; ?></small>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-white mb-2">Course Information</h6>
                                        <p class="mb-0 text-white fw-medium">
                                            <?php echo htmlspecialchars($enrollment['courseTitle']); ?>
                                        </p>
                                        <small class="text-white-50">ID: <?php echo $enrollment['courseID']; ?></small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">Progress Percentage *</label>
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
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">Status *</label>
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
                            
                            <div class="mt-4">
                                <label class="form-label fw-medium">Notes (Optional)</label>
                                <textarea class="form-control" name="notes" rows="3" placeholder="Add any notes about this enrollment..."><?php echo htmlspecialchars($enrollment['notes'] ?? ''); ?></textarea>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Enrollment Dates -->
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">Enrollment Date</label>
                                    <input type="text" 
                                           class="form-control" 
                                           value="<?php echo date('F d, Y h:i A', strtotime($enrollment['enrolledAt'])); ?>" 
                                           readonly
                                           style="background-color: #f8f9fa;">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-medium">Last Updated</label>
                                    <input type="text" 
                                           class="form-control" 
                                           value="<?php echo date('F d, Y h:i A', strtotime($enrollment['enrolledAt'])); ?>" 
                                           readonly
                                           style="background-color: #f8f9fa;">
                                </div>
                            </div>
                            
                            <!-- Warning for completed status -->
                            <div class="alert alert-warning mb-4" id="completedWarning" style="display: none; border-radius: 0.5rem;">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle me-2 fs-4"></i>
                                    <div>
                                        <strong>Warning:</strong> Marking as completed will set the completion date to now. This action cannot be undone.
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Warning for dropped status -->
                            <div class="alert alert-danger mb-4" id="droppedWarning" style="display: none; border-radius: 0.5rem;">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-exclamation-triangle me-2 fs-4"></i>
                                    <div>
                                        <strong>Warning:</strong> Dropping an enrollment will prevent the student from accessing course content. Consider setting to "pending" instead.
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex flex-column flex-sm-row justify-content-between gap-2">
                                <a href="enrollment_view.php?id=<?php echo $enrollmentID; ?>" class="btn btn-secondary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; font-weight: 500;">
                                    <i class="bi bi-x-circle me-2"></i>Cancel
                                </a>
                                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); border: none; color: white; font-weight: 500;">
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

<style>
/* Back button styling to match other pages */
#backButton.btn-outline-secondary {
    border-color: #fbb6ce !important;
    color: #6c757d !important;
    transition: all 0.2s ease !important;
}

#backButton.btn-outline-secondary:active {
    background-color: #fbb6ce !important;
    color: #fff !important;
    border-color: #fbb6ce !important;
}

#backButton.btn-outline-secondary:hover {
    background-color: rgba(251, 182, 206, 0.1) !important;
}

/* Breadcrumb styling */
.breadcrumb {
    background-color: transparent !important;
    padding: 0 !important;
    margin-bottom: 0 !important;
}

.breadcrumb-item a {
    text-decoration: none;
    color: #6c757d;
    transition: color 0.2s ease;
}

.breadcrumb-item a:hover {
    color: #0d6efd;
}

.breadcrumb-item a.fw-bold.text-primary {
    font-weight: 600 !important;
    color: #0d6efd !important;
}

.breadcrumb-item a.fw-bold.text-primary:hover {
    color: #0a58ca !important;
    text-decoration: underline;
}

.breadcrumb-item.active.text-dark {
    color: #212529 !important;
    font-weight: 500;
}

.breadcrumb-item + .breadcrumb-item::before {
    color: #adb5bd;
    content: "›";
    font-size: 1.1em;
}

/* Form styling improvements */
.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
    color: #495057;
}

.form-control,
.form-select {
    border-radius: 0.375rem;
    border: 1px solid #ced4da;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.form-control:focus,
.form-select:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Alert styling */
.alert {
    border-radius: 0.5rem;
    border: none;
}

/* Gradient button styling */
.btn[style*="linear-gradient"] {
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.btn[style*="linear-gradient"]:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
    opacity: 0.9;
}

.btn[style*="linear-gradient"]:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Responsive adjustments */
@media (max-width: 767.98px) {
    .bg-white.rounded-3.shadow-sm.p-3 {
        padding: 1rem !important;
    }
    
    .h3.mb-0 {
        font-size: 1.25rem;
    }
    
    .breadcrumb {
        font-size: 0.85rem;
    }
    
    #backButton.btn-outline-secondary {
        padding: 0.375rem 0.75rem;
        font-size: 0.9rem;
    }
    
    .col-lg-8.mx-auto {
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
}

@media (max-width: 575.98px) {
    .d-flex.flex-column.flex-sm-row {
        flex-direction: column !important;
    }
    
    .d-flex.flex-column.flex-sm-row .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
    
    .row.g-3 {
        --bs-gutter-y: 1rem;
    }
}
</style>

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