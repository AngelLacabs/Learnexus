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

// Get all certificates for this user
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
        body {
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .sidebar {
            width: 260px;
            height: 100vh;
            background: white;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 24px 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .brand {
            font-size: 22px;
            font-weight: 700;
            color: #1e88e5;
            text-decoration: none;
        }

        .sidebar-menu {
            flex: 1;
            padding: 20px 0;
            overflow-y: auto;
        }

        .menu-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #666;
            text-decoration: none;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .menu-item:hover {
            background: #f8f9fa;
            color: #1e88e5;
        }

        .menu-item.active {
            background: #e3f2fd;
            color: #1e88e5;
            border-left-color: #1e88e5;
            font-weight: 600;
        }

        .menu-item i {
            font-size: 20px;
            width: 24px;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
        }

        .top-bar {
            background: white;
            padding: 20px 40px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .search-box {
            position: relative;
            width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 40px 10px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
        }

        .notification-badge {
            position: absolute;
            top: -6px;
            right: -6px;
            background: #f44336;
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 10px;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #1e88e5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .content-area {
            padding: 40px;
        }

        .page-header {
            margin-bottom: 32px;
        }

        .page-header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .certificates-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
        }

        .certificate-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }

        .certificate-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }

        .certificate-preview {
            height: 220px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            padding: 32px;
            color: white;
        }

        .certificate-preview-content {
            text-align: center;
            width: 100%;
        }

        .certificate-preview h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .certificate-badge {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }

        .certificate-badge i {
            font-size: 32px;
        }

        .certificate-body {
            padding: 20px;
        }

        .certificate-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }

        .certificate-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 16px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #666;
        }

        .meta-item i {
            color: #999;
        }

        .certificate-actions {
            display: flex;
            gap: 8px;
        }

        .btn-view {
            flex: 1;
            padding: 10px;
            background: #1e88e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-view:hover {
            background: #1976d2;
        }

        .btn-download {
            padding: 10px 16px;
            background: transparent;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            color: #666;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-download:hover {
            background: #f8f9fa;
            border-color: #ccc;
        }

        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: white;
            border-radius: 12px;
        }

        .empty-state i {
            font-size: 64px;
            color: #ddd;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #666;
            margin-bottom: 12px;
        }

        .empty-state p {
            color: #999;
            margin-bottom: 24px;
        }

        .btn-browse {
            padding: 12px 32px;
            background: #1e88e5;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }

        .btn-browse:hover {
            background: #1976d2;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="dashboard.php" class="brand">LEARNEXUS</a>
        </div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="menu-item">
                <i class="bi bi-grid"></i>
                <span>Dashboard</span>
            </a>
            <a href="browse_courses.php" class="menu-item">
                <i class="bi bi-book"></i>
                <span>Course Catalog</span>
            </a>
            <a href="my_courses.php" class="menu-item">
                <i class="bi bi-collection"></i>
                <span>My Courses</span>
            </a>
            <a href="certificates.php" class="menu-item active">
                <i class="bi bi-award"></i>
                <span>Certificates</span>
            </a>
            <a href="vouchers.php" class="menu-item">
                <i class="bi bi-ticket-perforated"></i>
                <span>Vouchers</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <a href="../logout.php" class="menu-item">
                <i class="bi bi-box-arrow-left"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="search-box">
                <input type="text" placeholder="Search for courses, assignments...">
                <i class="bi bi-search"></i>
            </div>
            
            <div class="user-section">
                <div class="user-info">
                    <span style="font-weight: 600; color: #333;">
                        <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                    </span>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <div class="page-header">
                <h1><i class="bi bi-award"></i> My Certificates</h1>
                <p class="text-muted">View and download your earned certificates</p>
            </div>

            <?php if (count($certificates) > 0): ?>
                <div class="certificates-grid">
                    <?php foreach ($certificates as $cert): 
                        $gradients = [
                            'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                            'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                            'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                            'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
                            'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                            'linear-gradient(135deg, #30cfd0 0%, #330867 100%)',
                        ];
                        $gradient = $gradients[$cert['certificateID'] % count($gradients)];
                    ?>
                        <div class="certificate-card" onclick="window.location.href='view_certificate.php?id=<?php echo $cert['certificateUUID']; ?>'">
                            <div class="certificate-preview" style="background: <?php echo $gradient; ?>">
                                <div class="certificate-preview-content">
                                    <div class="certificate-badge">
                                        <i class="bi bi-award-fill"></i>
                                    </div>
                                    <h3>Certificate of Completion</h3>
                                    <p style="font-size: 14px; opacity: 0.9; margin: 0;">
                                        <?php echo htmlspecialchars($cert['courseTitle']); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="certificate-body">
                                <div class="certificate-title"><?php echo htmlspecialchars($cert['courseTitle']); ?></div>
                                <div class="certificate-meta">
                                    <div class="meta-item">
                                        <i class="bi bi-person"></i>
                                        <span>Instructor: <?php echo htmlspecialchars($cert['instructorName']); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="bi bi-calendar-check"></i>
                                        <span>Issued: <?php echo date('F d, Y', strtotime($cert['issuedAt'])); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i class="bi bi-hash"></i>
                                        <span>ID: <?php echo htmlspecialchars(substr($cert['certificateUUID'], 0, 8)); ?></span>
                                    </div>
                                </div>
                                <div class="certificate-actions">
                                    <button class="btn-view" onclick="event.stopPropagation(); window.location.href='view_certificate.php?id=<?php echo $cert['certificateUUID']; ?>'">
                                        <i class="bi bi-eye"></i> View Certificate
                                    </button>
                                    <button class="btn-download" onclick="event.stopPropagation(); downloadCertificate('<?php echo $cert['certificateUUID']; ?>')">
                                        <i class="bi bi-download"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-award"></i>
                    <h3>No Certificates Yet</h3>
                    <p>Complete courses to earn certificates and showcase your achievements</p>
                    <a href="browse_courses.php" class="btn-browse">Browse Courses</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadCertificate(uuid) {
            // Show loading
            Swal.fire({
                title: 'Preparing Download',
                text: 'Generating your certificate...',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Simulate download preparation
            setTimeout(() => {
                window.open(`download_certificate.php?id=${uuid}`, '_blank');
                Swal.close();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Download Started',
                    text: 'Your certificate is being downloaded',
                    timer: 2000,
                    showConfirmButton: false
                });
            }, 1000);
        }
    </script>
</body>
</html>