<?php
session_start();
require_once '../database/db_connect.php';

/* =====================
   AUTH CHECK - ADMIN ONLY
===================== */
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

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
   BUILD QUERY FOR CERTIFICATES (SIMPLIFIED VERSION)
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

// Apply search filter
if (!empty($search)) {
    $search_term = "%" . $conn->quote($search) . "%";
    $search_term = str_replace("'", "", $search_term); // Remove quotes for LIKE
    $query .= " AND (c.studentName LIKE '$search_term' 
                     OR c.certificateUUID LIKE '$search_term' 
                     OR u.email LIKE '$search_term' 
                     OR c.courseTitle LIKE '$search_term' 
                     OR CONCAT(inst.firstName, ' ', inst.lastName) LIKE '$search_term')";
}

// Apply course filter
if (!empty($course_filter) && is_numeric($course_filter)) {
    $query .= " AND c.courseID = " . intval($course_filter);
}

// Apply date filters
if (!empty($date_from)) {
    $query .= " AND DATE(c.issuedAt) >= '" . $conn->quote($date_from) . "'";
}

if (!empty($date_to)) {
    $query .= " AND DATE(c.issuedAt) <= '" . $conn->quote($date_to) . "'";
}

// Add ordering and pagination
$query .= " ORDER BY c.issuedAt DESC LIMIT " . intval($limit) . " OFFSET " . intval($offset);

/* =====================
   EXECUTE QUERY
===================== */
try {
    $stmt = $conn->query($query);
    $certificates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

/* =====================
   GET TOTAL COUNT FOR PAGINATION
===================== */
$count_query = "SELECT COUNT(*) as total FROM certificates c
                JOIN courses cr ON c.courseID = cr.courseID
                JOIN users u ON c.userID = u.userID
                WHERE 1=1";

if (!empty($search)) {
    $search_term = "%" . $conn->quote($search) . "%";
    $search_term = str_replace("'", "", $search_term);
    $count_query .= " AND (c.studentName LIKE '$search_term' 
                           OR c.certificateUUID LIKE '$search_term' 
                           OR c.courseTitle LIKE '$search_term')";
}

if (!empty($course_filter) && is_numeric($course_filter)) {
    $count_query .= " AND c.courseID = " . intval($course_filter);
}

if (!empty($date_from)) {
    $count_query .= " AND DATE(c.issuedAt) >= '" . $conn->quote($date_from) . "'";
}

if (!empty($date_to)) {
    $count_query .= " AND DATE(c.issuedAt) <= '" . $conn->quote($date_to) . "'";
}

try {
    $stmt = $conn->query($count_query);
    $total_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_filtered = $total_result['total'];
    $total_pages = ceil($total_filtered / $limit);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Management - LearnNexus Admin</title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --light-bg: #f8f9fa;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            padding: 20px 0;
            position: fixed;
            width: 250px;
        }
        
        .sidebar a {
            color: rgba(255, 255, 255, 0.85);
            padding: 12px 25px;
            text-decoration: none;
            display: block;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .sidebar a:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
            border-left: 3px solid white;
        }
        
        .sidebar a.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            border-left: 3px solid white;
            font-weight: 500;
        }
        
        .sidebar-brand {
            padding: 20px 25px;
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-section {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px 25px 5px;
            margin-top: 20px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }
        
        .navbar-top {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px 20px;
            margin: -20px -20px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s;
            border: 1px solid #e9ecef;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card i {
            font-size: 40px;
            margin-bottom: 15px;
        }
        
        .stat-card h3 {
            font-size: 2rem;
            margin-bottom: 5px;
        }
        
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
        
        .pagination .page-link {
            color: var(--primary-color);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .table-hover tbody tr:hover {
            background-color: rgba(102, 126, 234, 0.05);
        }
        
        .badge-certificate {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: relative;
                width: 100%;
                min-height: auto;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar d-none d-lg-block">
        <div class="sidebar-brand">
            <i class="bi bi-award-fill me-2"></i>
            LEARNEXUS
        </div>
        
        <div class="sidebar-section">Admin Menu</div>
        <a href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
        <a href="users.php"><i class="bi bi-people me-2"></i> Users</a>
        <a href="courses.php"><i class="bi bi-book me-2"></i> Courses</a>
        <a href="enrollments.php"><i class="bi bi-clipboard-check me-2"></i> Enrollments</a>
        <a href="payments.php"><i class="bi bi-credit-card me-2"></i> Payments</a>
        <a href="certificates.php" class="active"><i class="bi bi-award me-2"></i> Certificates</a>
        <a href="vouchers.php"><i class="bi bi-ticket-perforated me-2"></i> Vouchers</a>
        <a href="otp_logs.php"><i class="bi bi-shield-check me-2"></i> OTP Logs</a>
        <a href="sms_feedback.php"><i class="bi bi-chat-left-text me-2"></i> SMS Feedback</a>
        <a href="announcements.php"><i class="bi bi-megaphone me-2"></i> Announcements</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="navbar-top">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-0"><i class="bi bi-award me-2"></i> Certificate Management</h4>
                    <small class="text-muted">Manage and view all issued certificates</small>
                </div>
                <div class="d-flex align-items-center">
                    <span class="me-3">Welcome, <strong><?= htmlspecialchars($_SESSION['first_name'] ?? 'Admin') ?></strong></span>
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person me-2"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="#"><i class="bi bi-gear me-2"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card text-center">
                    <i class="bi bi-award-fill text-primary"></i>
                    <h3><?= number_format($total_certificates) ?></h3>
                    <p class="text-muted mb-0">Total Certificates</p>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card text-center">
                    <i class="bi bi-calendar-month-fill text-success"></i>
                    <h3><?= number_format($this_month) ?></h3>
                    <p class="text-muted mb-0">This Month</p>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card text-center">
                    <i class="bi bi-calendar-day-fill text-warning"></i>
                    <h3><?= number_format($today) ?></h3>
                    <p class="text-muted mb-0">Today</p>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="stat-card text-center">
                    <i class="bi bi-book-fill text-danger"></i>
                    <h3><?= number_format($courses_count) ?></h3>
                    <p class="text-muted mb-0">Active Courses</p>
                </div>
            </div>
        </div>

        <!-- Search & Filter Card -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="bi bi-funnel me-2"></i> Filter Certificates</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Student, Course, Instructor, UUID..." 
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['courseID'] ?>" 
                                    <?= $course_filter == $course['courseID'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" 
                               value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" 
                               value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
                
                <?php if (!empty($search) || !empty($course_filter) || !empty($date_from) || !empty($date_to)): ?>
                    <div class="mt-3">
                        <a href="certificates.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Clear Filters
                        </a>
                        <span class="ms-2 text-muted">
                            Showing <?= count($certificates) ?> of <?= number_format($total_filtered) ?> certificates
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Certificates Table -->
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="mb-0">Issued Certificates</h5>
                    <small class="text-muted"><?= number_format($total_filtered) ?> total certificates found</small>
                </div>
                <div>
                    <button class="btn btn-sm btn-outline-primary" onclick="exportToExcel()">
                        <i class="bi bi-file-earmark-excel me-1"></i> Export
                    </button>
                    <button class="btn btn-sm btn-success" onclick="refreshPage()">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($certificates)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-award display-1 text-muted mb-3"></i>
                        <h4>No Certificates Found</h4>
                        <p class="text-muted mb-4">No certificates have been issued yet or match your search criteria.</p>
                        <a href="certificates.php" class="btn btn-primary">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Clear Filters
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="certificatesTable">
                            <thead>
                                <tr>
                                    <th width="50">ID</th>
                                    <th>Student</th>
                                    <th>Course</th>
                                    <th>Instructor</th>
                                    <th>Issued Date</th>
                                    <th>UUID</th>
                                    <th width="120">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($certificates as $cert): ?>
                                    <?php 
                                    $issueDate = date('M d, Y', strtotime($cert['issuedAt']));
                                    $issueTime = date('h:i A', strtotime($cert['issuedAt']));
                                    $instructorName = $cert['instructor_first'] . ' ' . $cert['instructor_last'];
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge badge-certificate">#<?= $cert['certificateID'] ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($cert['studentName']) ?></div>
                                            <small class="text-muted d-block"><?= htmlspecialchars($cert['student_email']) ?></small>
                                            <?php if ($cert['student_phone']): ?>
                                                <small class="text-muted"><?= htmlspecialchars($cert['student_phone']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($cert['courseTitle']) ?></div>
                                            <small class="text-muted">Course ID: <?= $cert['courseID'] ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($instructorName) ?></td>
                                        <td>
                                            <div><?= $issueDate ?></div>
                                            <small class="text-muted"><?= $issueTime ?></small>
                                        </td>
                                        <td>
                                            <code class="certificate-id" title="<?= htmlspecialchars($cert['certificateUUID']) ?>">
                                                <?= substr($cert['certificateUUID'], 0, 8) ?>...
                                            </code>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button onclick="copyUUID('<?= htmlspecialchars($cert['certificateUUID']) ?>')" 
                                                        class="btn btn-sm btn-outline-secondary"
                                                        title="Copy UUID"
                                                        data-bs-toggle="tooltip">
                                                    <i class="bi bi-clipboard"></i>
                                                </button>
                                                <a href="view_certificate.php?id=<?= $cert['certificateID'] ?>" 
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
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" 
                                       href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&course=<?= $course_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                                        <i class="bi bi-chevron-left"></i>
                                    </a>
                                </li>
                                
                                <?php 
                                // Show page numbers
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                        <a class="page-link" 
                                           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&course=<?= $course_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" 
                                       href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&course=<?= $course_filter ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>">
                                        <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                        
                        <div class="text-center text-muted mt-2">
                            Page <?= $page ?> of <?= $total_pages ?> | 
                            Showing <?= count($certificates) ?> certificates per page
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Quick Stats Footer -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 border-end">
                                <small class="text-muted d-block">Total Students</small>
                                <?php
                                $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND status = 'active'");
                                $student_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                ?>
                                <h5 class="mb-0"><?= number_format($student_count) ?></h5>
                            </div>
                            <div class="col-md-3 border-end">
                                <small class="text-muted d-block">Total Instructors</small>
                                <?php
                                $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'instructor' AND status = 'active'");
                                $instructor_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                ?>
                                <h5 class="mb-0"><?= number_format($instructor_count) ?></h5>
                            </div>
                            <div class="col-md-3 border-end">
                                <small class="text-muted d-block">Published Courses</small>
                                <?php
                                $stmt = $conn->query("SELECT COUNT(*) as count FROM courses WHERE status = 'published'");
                                $published_courses = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                ?>
                                <h5 class="mb-0"><?= number_format($published_courses) ?></h5>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted d-block">Total Enrollments</small>
                                <?php
                                $stmt = $conn->query("SELECT COUNT(*) as count FROM enrollments WHERE status = 'active'");
                                $enrollments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                                ?>
                                <h5 class="mb-0"><?= number_format($enrollments) ?></h5>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#certificatesTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                searching: false,
                info: false,
                paging: false,
                ordering: true,
                order: [[4, 'desc']],
                language: {
                    emptyTable: "No certificates found"
                }
            });
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
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
</body>
</html>