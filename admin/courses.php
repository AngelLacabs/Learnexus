<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$page_title = "Course Management - Learnexus";

// Pagination
$limit = 15;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$teacher = isset($_GET['teacher']) ? (int)$_GET['teacher'] : 0;

// Build query
$whereClauses = [];
$params = [];
$joinSQL = "LEFT JOIN users u ON c.teacherID = u.userID";

if (!empty($search)) {
    $whereClauses[] = "(c.title LIKE ? OR c.description LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($category)) {
    $whereClauses[] = "c.category = ?";
    $params[] = $category;
}

if (!empty($status)) {
    $whereClauses[] = "c.status = ?";
    $params[] = $status;
}

if ($teacher > 0) {
    $whereClauses[] = "c.teacherID = ?";
    $params[] = $teacher;
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get total count for pagination
$countSQL = "SELECT COUNT(*) FROM courses c $joinSQL $whereSQL";
$countStmt = $conn->prepare($countSQL);

if (!empty($params)) {
    $countStmt->execute($params);
} else {
    $countStmt->execute();
}
$totalCourses = $countStmt->fetchColumn();
$totalPages = ceil($totalCourses / $limit);

// Get courses with pagination
$sql = "SELECT 
            c.*, 
            u.firstName as teacherFirstName,
            u.lastName as teacherLastName,
            u.email as teacherEmail,
            u.avatar as teacherAvatar,
            COUNT(e.enrollmentID) as enrolledCount
        FROM courses c 
        LEFT JOIN users u ON c.teacherID = u.userID
        LEFT JOIN enrollments e ON c.courseID = e.courseID 
        $whereSQL 
        GROUP BY c.courseID 
        ORDER BY c.createdAt DESC";
$sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->execute($params);
} else {
    $stmt->execute();
}

$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsStmt = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(status = 'published') as published,
        SUM(status = 'draft') as draft,
        SUM(status = 'archived') as archived,
        COUNT(DISTINCT teacherID) as teachers
    FROM courses
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get all categories for filter
$categoryStmt = $conn->query("SELECT DISTINCT category FROM courses WHERE category IS NOT NULL AND category != ''");
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

// Get all teachers for filter
$teacherStmt = $conn->query("
    SELECT u.userID, u.firstName, u.lastName 
    FROM users u 
    WHERE u.role = 'instructor' 
    ORDER BY u.firstName, u.lastName
");
$teachers = $teacherStmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content pb-3 pb-lg-4 ps-3 ps-lg-4 pe-3 pe-lg-4 pt-3">
    <div class="container-fluid">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Box 1: Page Header -->
        <div class="card border-0 rounded-4 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">Course Management</h1>
                        <p class="text-muted mb-0">Manage all courses in the system</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Box 2: Statistics Cards - Separate Boxes -->
        <div class="row g-4 mb-5">
            <!-- Total Courses -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Total Courses</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($stats['total']); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-book-fill" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Published -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Published</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($stats['published']); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-check-circle-fill" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Draft -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Draft</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($stats['draft']); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-pencil-fill" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Teachers -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Active Teachers</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($stats['teachers']); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-person-badge-fill" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Box 3: Filters Card -->
        <div class="card border-0 rounded-4 shadow-sm mb-5">
            <div class="card-body p-4">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               placeholder="Course title or description"
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                    <?php echo $category === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="published" <?php echo $status === 'published' ? 'selected' : ''; ?>>Published</option>
                            <option value="draft" <?php echo $status === 'draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="archived" <?php echo $status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Teacher</label>
                        <select class="form-select" name="teacher">
                            <option value="0">All Teachers</option>
                            <?php foreach ($teachers as $teacherData): ?>
                                <option value="<?php echo $teacherData['userID']; ?>" 
                                    <?php echo $teacher == $teacherData['userID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacherData['firstName'] . ' ' . $teacherData['lastName']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn w-100 text-white border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="bi bi-funnel me-2"></i>Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Box 4: Courses Table -->
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-white border-0 py-3 px-4">
                <h5 class="mb-0">Courses List</h5>
            </div>
            <div class="card-body px-4">
                <?php if (empty($courses)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-book fs-1 text-muted"></i>
                        <h5 class="mt-3">No courses found</h5>
                        <p class="text-muted">Try adjusting your search or filter criteria</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Teacher</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Students</th>
                                    <th>Price</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses as $course): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($course['title']); ?></h6>
                                                <small class="text-muted">ID: <?php echo $course['courseID']; ?></small>
                                                <?php if (!empty($course['description'])): ?>
                                                    <p class="mb-0 mt-1 small text-truncate" style="max-width: 250px;">
                                                        <?php echo htmlspecialchars(substr($course['description'], 0, 100)); ?>
                                                        <?php echo strlen($course['description']) > 100 ? '...' : ''; ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="overflow: hidden;">
                                                    <?php if (!empty($course['teacherAvatar']) && file_exists($course['teacherAvatar'])): ?>
                                                        <img src="<?php echo htmlspecialchars($course['teacherAvatar']); ?>" alt="Avatar" 
                                                             class="w-100 h-100 rounded-circle object-fit-cover">
                                                    <?php else: ?>
                                                        <?php echo strtoupper(substr($course['teacherFirstName'], 0, 1)); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($course['teacherFirstName'] . ' ' . $course['teacherLastName']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($course['teacherEmail']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?php echo !empty($course['category']) ? htmlspecialchars($course['category']) : 'Uncategorized'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $course['status'] === 'published' ? 'success' : 
                                                    ($course['status'] === 'draft' ? 'warning' : 'secondary'); 
                                            ?>">
                                                <?php echo ucfirst($course['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <h5 class="mb-0"><?php echo $course['enrolledCount']; ?></h5>
                                            <small class="text-muted">enrolled</small>
                                        </td>
                                        <td>
                                            <h5 class="mb-0 text-success">₱<?php echo number_format($course['price'], 2); ?></h5>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($course['createdAt'])); ?></small>
                                            <br><small class="text-muted"><?php echo date('h:i A', strtotime($course['createdAt'])); ?></small>
                                        </td>
                                        <td>
                                            <a href="course_view.php?id=<?php echo $course['courseID']; ?>" 
                                               class="btn btn-outline-primary btn-sm" 
                                               data-bs-toggle="tooltip" 
                                               title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reject Course Modal -->
<div class="modal fade" id="rejectCourseModal" tabindex="-1" aria-labelledby="rejectCourseModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectCourseModalLabel">Reject/Archive Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectCourseForm" method="POST" action="course_actions.php">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" id="rejectCourseId" name="id" value="">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <input type="text" class="form-control" id="rejectCourseTitle" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Action *</label>
                        <select class="form-select" name="status" required>
                            <option value="draft">Send back to Draft (for revisions)</option>
                            <option value="archived">Archive Course (hide from students)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason/Feedback *</label>
                        <textarea class="form-control" name="rejection_reason" rows="4" required 
                                  placeholder="Provide specific feedback to the teacher about what needs to be improved..."></textarea>
                        <small class="text-muted">This feedback will be sent to the course creator</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Submit Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle reject course button clicks
    const rejectButtons = document.querySelectorAll('.reject-course-btn');
    const rejectModal = new bootstrap.Modal(document.getElementById('rejectCourseModal'));
    const rejectCourseId = document.getElementById('rejectCourseId');
    const rejectCourseTitle = document.getElementById('rejectCourseTitle');
    const rejectCourseForm = document.getElementById('rejectCourseForm');
    
    rejectButtons.forEach(button => {
        button.addEventListener('click', function() {
            const courseId = this.getAttribute('data-course-id');
            const courseTitle = this.getAttribute('data-course-title');
            
            rejectCourseId.value = courseId;
            rejectCourseTitle.value = courseTitle;
            rejectModal.show();
        });
    });
    
    // Handle reject form submission
    rejectCourseForm.addEventListener('submit', function() {
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
        submitBtn.disabled = true;
    });
});
</script>

<?php include 'includes/footer.php'; ?>