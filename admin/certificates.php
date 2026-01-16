<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$page_title = "Certificate Management - Learnexus";

/* =====================
   SEARCH & FILTER PARAMETERS
===================== */
$search = $_GET['search'] ?? '';
$course_filter = $_GET['course'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

/* =====================
   GET STATISTICS
===================== */
// Total certificates
$stmt = $conn->query("SELECT COUNT(*) as total FROM certificates");
$total_certificates = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// This month certificates
$stmt = $conn->query("SELECT COUNT(*) as count FROM certificates 
                      WHERE MONTH(issuedAt) = MONTH(CURRENT_DATE()) 
                      AND YEAR(issuedAt) = YEAR(CURRENT_DATE())");
$this_month = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Today's certificates
$stmt = $conn->query("SELECT COUNT(*) as count FROM certificates 
                      WHERE DATE(issuedAt) = CURDATE()");
$today = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Unique courses with certificates
$stmt = $conn->query("SELECT COUNT(DISTINCT courseID) as count FROM certificates");
$courses_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

/* =====================
   GET ALL COURSES FOR FILTER
===================== */
$stmt = $conn->query("SELECT courseID, title FROM courses ORDER BY title");
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =====================
   BUILD QUERY FOR CERTIFICATES
===================== */
$query = "SELECT c.*, 
                 cr.title as course_name,
                 u.email as student_email,
                 u.phone as student_phone,
                 inst.firstName as instructor_first,
                 inst.lastName as instructor_last
          FROM certificates c
          JOIN courses cr ON c.courseID = cr.courseID
          JOIN users u ON c.userID = u.userID
          JOIN users inst ON cr.teacherID = inst.userID
          WHERE 1=1";

// Build where clauses and parameters
$whereClauses = [];
$params = [];

if (!empty($search)) {
    $whereClauses[] = "(c.studentName LIKE ? OR c.certificateUUID LIKE ? OR u.email LIKE ? OR c.courseTitle LIKE ? OR CONCAT(inst.firstName, ' ', inst.lastName) LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($course_filter) && is_numeric($course_filter)) {
    $whereClauses[] = "c.courseID = ?";
    $params[] = intval($course_filter);
}

if (!empty($date_from)) {
    $whereClauses[] = "DATE(c.issuedAt) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $whereClauses[] = "DATE(c.issuedAt) <= ?";
    $params[] = $date_to;
}

$whereSQL = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM certificates c
                JOIN courses cr ON c.courseID = cr.courseID
                JOIN users u ON c.userID = u.userID
                JOIN users inst ON cr.teacherID = inst.userID
                $whereSQL";

try {
    $countStmt = $conn->prepare($count_query);
    if (!empty($params)) {
        $countStmt->execute($params);
    } else {
        $countStmt->execute();
    }
    $total_result = $countStmt->fetch(PDO::FETCH_ASSOC);
    $total_filtered = $total_result['total'];
    $total_pages = ceil($total_filtered / $limit);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get certificates with pagination
$sql = "SELECT c.*, 
               cr.title as course_name,
               u.email as student_email,
               u.phone as student_phone,
               inst.firstName as instructor_first,
               inst.lastName as instructor_last
        FROM certificates c
        JOIN courses cr ON c.courseID = cr.courseID
        JOIN users u ON c.userID = u.userID
        JOIN users inst ON cr.teacherID = inst.userID
        $whereSQL 
        ORDER BY c.issuedAt DESC 
        LIMIT " . intval($limit) . " OFFSET " . intval($offset);

try {
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->execute($params);
    } else {
        $stmt->execute();
    }
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get additional stats - FIXED QUERIES
// Get total active students
$studentStmt = $conn->query("SELECT COUNT(*) as total_students FROM users WHERE role = 'student' AND status = 'active'");
$studentStats = $studentStmt->fetch(PDO::FETCH_ASSOC);
$total_students = $studentStats['total_students'] ?? 0;

// Get total active instructors
$instructorStmt = $conn->query("SELECT COUNT(*) as total_instructors FROM users WHERE role = 'instructor' AND status = 'active'");
$instructorStats = $instructorStmt->fetch(PDO::FETCH_ASSOC);
$total_instructors = $instructorStats['total_instructors'] ?? 0;

// Get total published courses
$courseStmt = $conn->query("SELECT COUNT(*) as published_courses FROM courses WHERE status = 'published'");
$courseStats = $courseStmt->fetch(PDO::FETCH_ASSOC);
$published_courses = $courseStats['published_courses'] ?? 0;

// Get total active enrollments
$enrollStmt = $conn->query("SELECT COUNT(*) as total_enrollments FROM enrollments WHERE status = 'active'");
$enrollStats = $enrollStmt->fetch(PDO::FETCH_ASSOC);
$total_enrollments = $enrollStats['total_enrollments'] ?? 0;

// Check if includes folder exists, otherwise include from root
$header_path = file_exists('includes/header.php') ? 'includes/header.php' : 'header.php';
$sidebar_path = file_exists('includes/sidebar.php') ? 'includes/sidebar.php' : 'sidebar.php';
$footer_path = file_exists('includes/footer.php') ? 'includes/footer.php' : 'footer.php';

include $header_path;
include $sidebar_path;
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
        <div class="card border-0 rounded-4 shadow-sm mb-5">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">Certificate Management</h1>
                        <p class="text-muted mb-0">Manage and view all issued certificates</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Box 2: Statistics Cards - Separate Boxes with gradient styling -->
        <div class="row g-4 mb-5">
            <!-- Total Certificates -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Total Certificates</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($total_certificates); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-award-fill" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- This Month -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">This Month</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($this_month); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-calendar-month-fill" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Today -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Today</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($today); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-calendar-day-fill" style="font-size: 2.5rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Active Courses -->
            <div class="col-lg-3 col-md-6">
                <div class="card border-0 rounded-4 h-100 text-white" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.1);">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-2 text-white-50" style="font-size: 0.875rem; font-weight: 500;">Active Courses</h6>
                                <h2 class="mb-0 text-white fw-bold" style="font-size: 2rem;"><?php echo number_format($courses_count); ?></h2>
                            </div>
                            <div class="ms-3" style="opacity: 0.9;">
                                <i class="bi bi-book-fill" style="font-size: 2.5rem;"></i>
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
                               placeholder="Student, Course, Instructor, UUID..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $courseData): ?>
                                <option value="<?php echo $courseData['courseID']; ?>" 
                                    <?php echo $course_filter == $courseData['courseID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($courseData['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" 
                               class="form-control" 
                               name="date_from" 
                               value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" 
                               class="form-control" 
                               name="date_to" 
                               value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="col-md-1 d-flex align-items-end">
                        <div class="d-flex gap-2 w-100">
                            <button type="submit" class="btn w-100 text-white border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="bi bi-search"></i>
                            </button>
                            <a href="certificates.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($search) || !empty($course_filter) || !empty($date_from) || !empty($date_to)): ?>
                    <div class="mt-3">
                        <a href="certificates.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Clear Filters
                        </a>
                        <span class="ms-2 text-muted">
                            Showing <?php echo count($certificates); ?> of <?php echo number_format($total_filtered); ?> certificates
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Box 4: Certificates Table -->
        <div class="card border-0 rounded-4 shadow-sm">
            <div class="card-header bg-white border-0 py-3 px-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">Issued Certificates</h5>
                        <small class="text-muted"><?php echo number_format($total_filtered); ?> total certificates found</small>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-primary me-2" onclick="exportToExcel()">
                            <i class="bi bi-file-earmark-excel me-1"></i> Export
                        </button>
                        <button class="btn btn-sm btn-success" onclick="refreshPage()">
                            <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body px-4">
                <?php if (empty($certificates)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-award fs-1 text-muted"></i>
                        <h5 class="mt-3">No certificates found</h5>
                        <p class="text-muted">No certificates have been issued yet or match your search criteria.</p>
                        <a href="certificates.php" class="btn btn-primary">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="certificatesTable">
                            <thead>
                                <tr>
                                    <th width="60">ID</th>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Instructor</th>
                                    <th>Issued Date</th>
                                    <th>UUID</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($certificates as $cert): 
                                    $issueDate = date('M d, Y', strtotime($cert['issuedAt']));
                                    $issueTime = date('h:i A', strtotime($cert['issuedAt']));
                                    $instructorName = $cert['instructor_first'] . ' ' . $cert['instructor_last'];
                                ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary">#<?php echo $cert['certificateID']; ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($cert['studentName']); ?></div>
                                            <small class="text-muted d-block"><?php echo htmlspecialchars($cert['student_email']); ?></small>
                                            <?php if ($cert['student_phone']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($cert['student_phone']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($cert['courseTitle']); ?></div>
                                            <small class="text-muted">Course ID: <?php echo $cert['courseID']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($instructorName); ?></td>
                                        <td>
                                            <div class="fw-bold"><?php echo $issueDate; ?></div>
                                            <small class="text-muted"><?php echo $issueTime; ?></small>
                                        </td>
                                        <td>
                                            <code class="certificate-id" title="<?php echo htmlspecialchars($cert['certificateUUID']); ?>">
                                                <?php echo substr($cert['certificateUUID'], 0, 8); ?>...
                                            </code>
                                        </td>
                                        <td>
                                            <div class="action-buttons d-flex gap-2">
                                                <button onclick="copyUUID('<?php echo htmlspecialchars($cert['certificateUUID']); ?>')" 
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Copy UUID"
                                                        data-bs-toggle="tooltip">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                                <a href="view_certificate.php?id=<?php echo $cert['certificateID']; ?>" 
                                                   class="btn btn-sm btn-outline-info"
                                                   title="View Details"
                                                   data-bs-toggle="tooltip">
                                                    <i class="bi bi-info-circle"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
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
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        
                        <div class="text-center text-muted mt-2">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?> | 
                            Showing <?php echo count($certificates); ?> certificates per page
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Stats Footer -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card border-0 rounded-4 shadow-sm">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 border-end">
                                <small class="text-muted d-block">Total Students</small>
                                <h5 class="mb-0"><?php echo number_format($total_students); ?></h5>
                            </div>
                            <div class="col-md-3 border-end">
                                <small class="text-muted d-block">Total Instructors</small>
                                <h5 class="mb-0"><?php echo number_format($total_instructors); ?></h5>
                            </div>
                            <div class="col-md-3 border-end">
                                <small class="text-muted d-block">Published Courses</small>
                                <h5 class="mb-0"><?php echo number_format($published_courses); ?></h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Total Enrollments</small>
                                <h5 class="mb-0"><?php echo number_format($total_enrollments); ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Copy UUID to clipboard
function copyUUID(uuid) {
    navigator.clipboard.writeText(uuid).then(function() {
        Swal.fire({
            icon: 'success',
            title: 'Copied!',
            text: 'Certificate UUID copied to clipboard',
            timer: 1500,
            showConfirmButton: false
        });
    }).catch(function(err) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to copy UUID'
        });
    });
}

// Export to Excel
function exportToExcel() {
    const table = document.getElementById('certificatesTable');
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Certificates");
    
    const timestamp = new Date().toISOString().slice(0,19).replace(/[:]/g, "-");
    const filename = `learnexus_certificates_${timestamp}.xlsx`;
    
    XLSX.writeFile(wb, filename);
    
    Swal.fire({
        icon: 'success',
        title: 'Exported!',
        text: 'Certificate data exported to Excel',
        timer: 2000,
        showConfirmButton: false
    });
}

// Refresh page
function refreshPage() {
    Swal.fire({
        title: 'Refreshing...',
        text: 'Updating certificate data',
        allowOutsideClick: false,
        timer: 1000,
        didOpen: () => {
            Swal.showLoading()
        },
        willClose: () => {
            window.location.reload();
        }
    });
}

// Print certificate list
function printCertificateList() {
    const printContent = document.getElementById('certificatesTable').outerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>LearnNexus - Certificates Report</title>
            <style>
                body { font-family: Arial, sans-serif; }
                table { width: 100%; border-collapse: collapse; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                @media print { @page { size: landscape; } }
            </style>
        </head>
        <body>
            <h2>LearnNexus - Certificate Report</h2>
            <p>Generated on: ${new Date().toLocaleDateString()}</p>
            ${printContent}
        </body>
        </html>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    window.location.reload();
}
</script>

<!-- Load required libraries -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<style>
.certificate-id {
    font-family: 'Courier New', monospace;
    font-size: 12px;
    color: #666;
    background: #f8f9fa;
    padding: 2px 6px;
    border-radius: 3px;
    border: 1px solid #dee2e6;
}

.action-buttons .btn {
    padding: 5px 10px;
    font-size: 0.875rem;
}

.card {
    transition: transform 0.2s ease-in-out;
}

.card:hover {
    transform: translateY(-2px);
}

.table th {
    font-weight: 600;
    background-color: #f8f9fa;
    padding: 12px 16px;
}

.table td {
    vertical-align: middle;
    padding: 12px 16px;
}

.table tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.badge {
    font-size: 0.85em;
    padding: 5px 10px;
}

.table-responsive {
    border-radius: 8px;
    overflow: hidden;
}

.table-hover tbody tr {
    transition: background-color 0.2s ease;
}
</style>

<script>
$(document).ready(function() {
    $('#certificatesTable').DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        searching: false,
        info: false,
        paging: false,
        ordering: true,
        order: [[4, 'desc']], // Order by issued date (column index 4)
        language: {
            emptyTable: "No certificates found"
        },
        columnDefs: [
            { width: "60px", targets: 0 }, // ID column
            { width: "120px", targets: 6 } // Actions column
        ]
    });
});
</script>

<?php include $footer_path; ?>