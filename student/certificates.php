<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../database/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

// Get user data including avatar
$userStmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$userStmt->execute([$userID]);
$user = $userStmt->fetch();

// Get search query
$searchQuery = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get certificates with optional search
if (!empty($searchQuery)) {
    $stmt = $conn->prepare("
        SELECT 
            cert.*,
            c.title as courseTitle,
            c.description as courseDescription,
            CONCAT(u.firstName, ' ', u.lastName) as instructorName,
            e.completedAt,
            e.progressPercentage
        FROM certificates cert
        JOIN enrollments e ON cert.enrollmentID = e.enrollmentID
        JOIN courses c ON cert.courseID = c.courseID
        JOIN users u ON c.teacherID = u.userID
        WHERE cert.userID = ? 
        AND (c.title LIKE ? OR c.description LIKE ? OR CONCAT(u.firstName, ' ', u.lastName) LIKE ?)
        ORDER BY cert.issuedAt DESC
    ");
    $searchParam = "%{$searchQuery}%";
    $stmt->execute([$userID, $searchParam, $searchParam, $searchParam]);
} else {
    $stmt = $conn->prepare("
        SELECT 
            cert.*,
            c.title as courseTitle,
            c.description as courseDescription,
            CONCAT(u.firstName, ' ', u.lastName) as instructorName,
            e.completedAt,
            e.progressPercentage
        FROM certificates cert
        JOIN enrollments e ON cert.enrollmentID = e.enrollmentID
        JOIN courses c ON cert.courseID = c.courseID
        JOIN users u ON c.teacherID = u.userID
        WHERE cert.userID = ?
        ORDER BY cert.issuedAt DESC
    ");
    $stmt->execute([$userID]);
}
$certificates = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Certificates - Learnexus</title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --sidebar-width: 260px;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #e8f0fe 0%, #f0f4ff 50%, #f8f9fa 100%);
            box-shadow: 4px 0 20px rgba(0,0,0,0.08);
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Navigation */
        .nav-link {
            border-radius: 12px;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 0;
            background: #1a73e8;
            border-radius: 0 4px 4px 0;
            transition: height 0.25s ease;
        }

        .nav-link:hover::before {
            height: 60%;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .nav-link.active::before {
            display: none;
        }

        /* Hamburger - EXACTLY matching dashboard */
        .hamburger-btn {
            width: 50px;
            height: 50px;
            background: white;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .hamburger-icon span {
            display: block;
            width: 24px;
            height: 3px;
            background: #1a73e8;
            border-radius: 3px;
            transition: all 0.3s ease;
            margin: 5px 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(1) {
            transform: translateY(8px) rotate(45deg);
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(2) {
            opacity: 0;
        }

        .hamburger-btn.active .hamburger-icon span:nth-child(3) {
            transform: translateY(-8px) rotate(-45deg);
        }

        /* Main Content Margin - EXACTLY matching dashboard */
        @media (min-width: 992px) {
            .main-content {
                margin-left: var(--sidebar-width);
            }
        }

        .search-input {
            padding-left: 2.5rem;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            border-radius: 25px !important;
        }

        .search-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            z-index: 10;
        }

        .search-input:focus ~ .search-icon {
            color: #667eea;
        }

        .clear-search {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #999;
            cursor: pointer;
            display: none;
            z-index: 10;
        }

        .clear-search.show {
            display: block;
        }

        .card-hover {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }

        .card-hover:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.15) !important;
        }

        .cert-header {
            height: 200px;
        }

        mark {
            background-color: #fff59d;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <!-- Hamburger Button -->
    <div class="position-fixed top-0 start-0 p-3 d-lg-none" style="z-index: 1100;">
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" id="hamburgerBtn">
            <div class="hamburger-icon d-flex flex-column align-items-center justify-content-center">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar offcanvas-lg offcanvas-start position-fixed top-0 start-0 h-100" style="width: var(--sidebar-width);" id="sidebar">
        <div class="offcanvas-header d-lg-none border-bottom">
            <h5 class="offcanvas-title sidebar-brand">LEARNEXUS</h5>
        </div>

        <div class="offcanvas-body p-0 d-flex flex-column h-100">
            <div class="sidebar-brand px-4 py-4 mb-4 d-none d-lg-block">LEARNEXUS</div>
            
            <nav class="flex-grow-1 px-3">
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="dashboard.php">
                    <i class="bi bi-grid fs-5"></i><span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="course_catalog.php">
                    <i class="bi bi-book fs-5"></i><span>Course Catalog</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="my_courses.php">
                    <i class="bi bi-journal-bookmark fs-5"></i><span>My Courses</span>
                </a>
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="certificates.php">
                    <i class="bi bi-award fs-5"></i><span>Certificates</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="vouchers.php">
                    <i class="bi bi-ticket-perforated fs-5"></i><span>Vouchers</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="settings.php">
                    <i class="bi bi-gear fs-5"></i><span>Settings</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium" href="ai_chatbot.php">
                    <i class="bi bi-robot fs-5"></i><span>AI Tutor</span>
                </a>
            </nav>
            
            <div class="p-3 mt-auto">
                <button class="btn btn-outline-danger w-100 rounded-pill fw-semibold" onclick="window.location.href='../logout.php'">
                    <i class="bi bi-box-arrow-left me-2"></i>Logout
                </button>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content p-3 p-lg-4">
        <div class="container-fluid">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card border-0 rounded-4 shadow-sm">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <!-- NEW SEARCH BAR -->
                            <div class="position-relative" style="flex: 1; max-width: 500px;">
                                <form method="GET" class="position-relative w-100">
                                    <i class="bi bi-search search-icon"></i>
                                    <input type="text" name="search" id="certificateSearch" 
                                           class="form-control search-input ps-5" 
                                           placeholder="Search certificates..." 
                                           value="<?php echo htmlspecialchars($searchQuery); ?>" 
                                           autocomplete="off">
                                    <button type="button" class="clear-search" id="clearSearch">
                                        <i class="bi bi-x-circle-fill"></i>
                                    </button>
                                </form>
                            </div>
                            
                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'" role="button" style="flex-shrink: 0;">
                                <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" 
                                     style="width: 45px; height: 45px; min-width: 45px; background: linear-gradient(135deg, #667eea, #764ba2);">
                                    <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar" 
                                             class="w-100 h-100 rounded-circle object-fit-cover">
                                    <?php else: ?>
                                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Page Title -->
            <div class="row mb-4">
                <div class="col-12">
                    <h1 class="h3 fw-bold"><i class="bi bi-award me-2"></i>My Certificates</h1>
                    <p class="text-muted">View and download your earned certificates</p>
                </div>
            </div>

            <!-- Search Results Info -->
            <?php if (!empty($searchQuery)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-info d-flex justify-content-between align-items-center">
                            <span>
                                <i class="bi bi-search me-2"></i>
                                Found <strong><?php echo count($certificates); ?></strong> certificate(s) for 
                                "<strong><?php echo htmlspecialchars($searchQuery); ?></strong>"
                            </span>
                            <button class="btn btn-sm btn-outline-secondary rounded-pill" onclick="window.location.href='certificates.php'">
                                <i class="bi bi-x"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Certificates Grid -->
            <?php if (count($certificates) > 0): ?>
                <div class="row g-4">
                    <?php 
                    $gradients = [
                        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                        'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
                        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                        'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
                    ];
                    foreach ($certificates as $cert): 
                        $gradient = $gradients[$cert['certificateID'] % count($gradients)];
                        $courseTitle = htmlspecialchars($cert['courseTitle']);
                        $instructorName = htmlspecialchars($cert['instructorName']);
                        if (!empty($searchQuery)) {
                            $courseTitle = preg_replace('/(' . preg_quote($searchQuery, '/') . ')/i', '<mark>$1</mark>', $courseTitle);
                            $instructorName = preg_replace('/(' . preg_quote($searchQuery, '/') . ')/i', '<mark>$1</mark>', $instructorName);
                        }
                    ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card border-0 rounded-4 shadow-sm card-hover h-100" 
                                 onclick="window.location.href='view_certificate.php?id=<?php echo $cert['certificateUUID']; ?>'">
                                <div class="cert-header position-relative d-flex flex-column align-items-center justify-content-center text-white text-center p-4" 
                                     style="background: <?php echo $gradient; ?>">
                                    <div class="rounded-circle bg-white bg-opacity-25 d-flex align-items-center justify-content-center mb-3" 
                                         style="width: 60px; height: 60px;">
                                        <i class="bi bi-award-fill fs-1"></i>
                                    </div>
                                    <h6 class="fw-bold mb-1">Certificate of Completion</h6>
                                    <p class="small mb-0"><?php echo htmlspecialchars($cert['courseTitle']); ?></p>
                                </div>
                                
                                <div class="card-body p-4">
                                    <h5 class="fw-bold mb-3"><?php echo $courseTitle; ?></h5>
                                    
                                    <div class="d-flex flex-column gap-2 small text-muted mb-3">
                                        <div><i class="bi bi-person me-2"></i><?php echo $instructorName; ?></div>
                                        <div><i class="bi bi-calendar-check me-2"></i><?php echo date('F d, Y', strtotime($cert['issuedAt'])); ?></div>
                                        <div><i class="bi bi-hash me-2"></i><?php echo substr($cert['certificateUUID'], 0, 8); ?></div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary rounded-pill flex-grow-1" 
                                                onclick="event.stopPropagation(); window.location.href='view_certificate.php?id=<?php echo $cert['certificateUUID']; ?>'">
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                        <button class="btn btn-outline-secondary rounded-circle" 
                                                onclick="event.stopPropagation(); downloadCertificate('<?php echo $cert['certificateUUID']; ?>')"
                                                style="width: 42px; height: 42px;">
                                            <i class="bi bi-download"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="row">
                    <div class="col-12">
                        <div class="card border-0 rounded-4 shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-award display-1 text-muted mb-3"></i>
                                <?php if (!empty($searchQuery)): ?>
                                    <h3 class="h5 fw-bold mb-3">No Certificates Found</h3>
                                    <p class="text-muted mb-4">No certificates match "<?php echo htmlspecialchars($searchQuery); ?>"</p>
                                    <button class="btn btn-primary rounded-pill px-4" onclick="window.location.href='certificates.php'">
                                        <i class="bi bi-arrow-left me-2"></i>View All
                                    </button>
                                <?php else: ?>
                                    <h3 class="h5 fw-bold mb-3">No Certificates Yet</h3>
                                    <p class="text-muted mb-4">Complete courses to earn certificates</p>
                                    <a href="course_catalog.php" class="btn btn-primary rounded-pill px-4 fw-semibold" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white !important; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">
    <i class="bi bi-search me-2"></i>Browse Courses
</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hamburger animation
        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');

        if (hamburgerBtn && sidebar) {
            sidebar.addEventListener('show.bs.offcanvas', () => hamburgerBtn.classList.add('active'));
            sidebar.addEventListener('hide.bs.offcanvas', () => hamburgerBtn.classList.remove('active'));
        }

        // Active nav state
        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        const currentPage = window.location.pathname.split('/').pop();
        
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            }
            
            // Close sidebar
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
                    if (offcanvas) offcanvas.hide();
                }
            });
        });

        // NEW SEARCH FUNCTIONALITY
        const searchInput = document.getElementById('certificateSearch');
        const clearSearchBtn = document.getElementById('clearSearch');

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            clearSearchBtn.classList.toggle('show', searchTerm.length > 0);
            
            // Auto-submit form when typing (with slight delay)
            if (searchTerm !== "<?php echo $searchQuery; ?>") {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => {
                    if (searchTerm.length > 0 || searchTerm.length === 0) {
                        this.form.submit();
                    }
                }, 500);
            }
        });

        clearSearchBtn.addEventListener('click', function() {
            searchInput.value = '';
            clearSearchBtn.classList.remove('show');
            searchInput.form.submit();
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                searchInput.value = '';
                clearSearchBtn.classList.remove('show');
                searchInput.form.submit();
            }
        });

        function downloadCertificate(uuid) {
            Swal.fire({
                title: 'Preparing Download',
                text: 'Generating your certificate...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            setTimeout(() => {
                window.open(`download_certificate.php?id=${uuid}`, '_blank');
                Swal.close();
                Swal.fire({
                    icon: 'success',
                    title: 'Download Started',
                    timer: 2000,
                    showConfirmButton: false
                });
            }, 1000);
        }
    </script>
</body>
</html>