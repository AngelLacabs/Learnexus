<?php
session_start();
require_once '../database/db_connect.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['id'])) {
    header('Location: certificates.php');
    exit();
}

$certificateID = (int)$_GET['id'];

try {
    // Get certificate details
    $stmt = $conn->prepare("
        SELECT 
            cert.*,
            u.email as studentEmail,
            u.phone as studentPhone,
            u.studentNumber,
            u.avatar,
            c.courseID,
            c.title as courseTitle,
            c.description as courseDescription,
            c.price as coursePrice,
            c.category,
            c.teacherID,
            ct.firstName as teacherFirstName,
            ct.lastName as teacherLastName,
            ct.email as teacherEmail,
            e.enrollmentID,
            e.progressPercentage,
            e.status as enrollmentStatus,
            e.enrolledAt,
            e.completedAt
        FROM certificates cert
        JOIN users u ON cert.userID = u.userID
        JOIN courses c ON cert.courseID = c.courseID
        JOIN users ct ON c.teacherID = ct.userID
        LEFT JOIN enrollments e ON cert.enrollmentID = e.enrollmentID
        WHERE cert.certificateID = ?
    ");
    $stmt->execute([$certificateID]);
    $certificate = $stmt->fetch();
    
    if (!$certificate) {
        $_SESSION['error'] = 'Certificate not found';
        header('Location: certificates.php');
        exit();
    }
    
    // Get download history
    $stmt = $conn->prepare("
        SELECT cd.*, u.firstName, u.lastName
        FROM certificate_downloads cd
        LEFT JOIN users u ON cd.userID = u.userID
        WHERE cd.certificateID = ?
        ORDER BY cd.downloadedAt DESC
    ");
    $stmt->execute([$certificateID]);
    $downloadHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get quiz results for this course and student
    $stmt = $conn->prepare("
        SELECT qr.*, q.title as quizTitle
        FROM quiz_results qr
        JOIN quizzes q ON qr.quizID = q.quizID
        WHERE qr.userID = ? AND q.courseID = ?
        ORDER BY qr.submittedAt DESC
    ");
    $stmt->execute([$certificate['userID'], $certificate['courseID']]);
    $quizResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get average quiz score
    $avgQuizScore = 0;
    if (!empty($quizResults)) {
        $totalScore = 0;
        foreach ($quizResults as $quiz) {
            $totalScore += (float)$quiz['percentage'];
        }
        $avgQuizScore = round($totalScore / count($quizResults), 1);
    }
    
} catch (PDOException $e) {
    error_log("Certificate View Error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading certificate details';
    header('Location: certificates.php');
    exit();
}

$page_title = "Certificate Details - " . $certificate['studentName'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <a href="certificates.php" class="btn btn-outline-secondary me-3">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <div>
                    <h1 class="h3 mb-0">Certificate Details</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="certificates.php">Certificates</a></li>
                            <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($certificate['studentName']); ?></li>
                        </ol>
                    </nav>
                </div>
            </div>
            <div class="btn-group">
                <a href="../student/certificate.php?course=<?php echo $certificate['courseID']; ?>&preview=1&admin=1" 
                   target="_blank" 
                   class="btn btn-primary">
                    <i class="bi bi-eye me-2"></i>View Certificate
                </a>
                <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="visually-hidden">Toggle Dropdown</span>
                </button>
                <ul class="dropdown-menu">
                    <li>
                        <a href="../student/certificate.php?course=<?php echo $certificate['courseID']; ?>&download=1&admin=1" 
                           target="_blank" 
                           class="dropdown-item">
                            <i class="bi bi-download me-2"></i>Download PDF
                        </a>
                    </li>
                    <li>
                        <a href="#" class="dropdown-item" onclick="window.print()">
                            <i class="bi bi-printer me-2"></i>Print Certificate
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a href="#" class="dropdown-item text-info" data-bs-toggle="modal" data-bs-target="#shareModal">
                            <i class="bi bi-share me-2"></i>Share Certificate
                        </a>
                    </li>
                    <li>
                        <a href="#" class="dropdown-item" onclick="copyToClipboard('<?php echo $certificate['certificateUUID']; ?>')">
                            <i class="bi bi-clipboard me-2"></i>Copy UUID
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a href="certificate_actions.php?action=delete&id=<?php echo $certificateID; ?>" 
                           class="dropdown-item text-danger"
                           data-confirm-delete="Are you sure you want to delete this certificate? This action cannot be undone.">
                            <i class="bi bi-trash me-2"></i>Delete Certificate
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Certificate Overview -->
        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Certificate Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Certificate Details</h6>
                                <div class="mb-3">
                                    <small class="text-muted d-block">Certificate ID</small>
                                    <p class="mb-0">
                                        <strong><?php echo $certificate['certificateID']; ?></strong>
                                        <br>
                                        <small class="text-primary"><?php echo $certificate['certificateUUID']; ?></small>
                                    </p>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Issue Date</small>
                                        <p class="mb-0"><?php echo date('F d, Y', strtotime($certificate['issuedAt'])); ?></p>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($certificate['issuedAt'])); ?></small>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Downloads</small>
                                        <h3 class="text-info mb-0"><?php echo $certificate['downloadCount']; ?></h3>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Course Information</h6>
                                <h5 class="mb-2"><?php echo htmlspecialchars($certificate['courseTitle']); ?></h5>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars(substr($certificate['courseDescription'], 0, 150)) . (strlen($certificate['courseDescription']) > 150 ? '...' : ''); ?></p>
                                
                                <div class="mb-2">
                                    <small class="text-muted d-block">Instructor</small>
                                    <p class="mb-0"><?php echo htmlspecialchars($certificate['teacherFirstName'] . ' ' . $certificate['teacherLastName']); ?></p>
                                    <small class="text-muted"><?php echo htmlspecialchars($certificate['teacherEmail']); ?></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Student Information -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Student Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <?php if (!empty($certificate['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($certificate['avatar']); ?>" 
                                     class="rounded-circle me-3" 
                                     width="80" 
                                     height="80"
                                     alt="Avatar">
                            <?php else: ?>
                                <div class="avatar-lg bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                    <?php echo strtoupper(substr($certificate['studentName'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h4 class="mb-0"><?php echo htmlspecialchars($certificate['studentName']); ?></h4>
                                <p class="text-muted mb-1"><?php echo htmlspecialchars($certificate['studentEmail']); ?></p>
                                <?php if (!empty($certificate['studentNumber'])): ?>
                                    <small class="text-muted">Student #: <?php echo htmlspecialchars($certificate['studentNumber']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">Phone Number</small>
                                    <p class="mb-0"><?php echo !empty($certificate['studentPhone']) ? htmlspecialchars($certificate['studentPhone']) : 'N/A'; ?></p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <small class="text-muted d-block">User ID</small>
                                    <p class="mb-0"><?php echo $certificate['userID']; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($certificate['enrollmentID']): ?>
                            <hr class="my-4">
                            <h6>Enrollment Status</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Status</small>
                                    <span class="badge bg-<?php 
                                        echo $certificate['enrollmentStatus'] === 'completed' ? 'success' : 
                                            ($certificate['enrollmentStatus'] === 'active' ? 'primary' : 
                                            ($certificate['enrollmentStatus'] === 'dropped' ? 'danger' : 'warning')); 
                                    ?>">
                                        <?php echo ucfirst($certificate['enrollmentStatus']); ?>
                                    </span>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Progress</small>
                                    <p class="mb-0"><?php echo number_format($certificate['progressPercentage'], 1); ?>%</p>
                                </div>
                                <div class="col-md-4">
                                    <small class="text-muted d-block">Avg Quiz Score</small>
                                    <p class="mb-0"><?php echo $avgQuizScore; ?>%</p>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">Enrolled</small>
                                    <p class="mb-0"><?php echo date('F d, Y', strtotime($certificate['enrolledAt'])); ?></p>
                                </div>
                                <?php if ($certificate['completedAt']): ?>
                                    <div class="col-md-6">
                                        <small class="text-muted d-block">Completed</small>
                                        <p class="mb-0"><?php echo date('F d, Y', strtotime($certificate['completedAt'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="../student/certificate.php?course=<?php echo $certificate['courseID']; ?>&preview=1&admin=1" 
                               target="_blank" 
                               class="btn btn-primary">
                                <i class="bi bi-eye me-2"></i>View Certificate
                            </a>
                            
                            <a href="../student/certificate.php?course=<?php echo $certificate['courseID']; ?>&download=1&admin=1" 
                               target="_blank" 
                               class="btn btn-success">
                                <i class="bi bi-download me-2"></i>Download PDF
                            </a>
                            
                            <a href="user_view.php?id=<?php echo $certificate['userID']; ?>" class="btn btn-outline-primary">
                                <i class="bi bi-person me-2"></i>View Student Profile
                            </a>
                            
                            <a href="course_view.php?id=<?php echo $certificate['courseID']; ?>" class="btn btn-outline-success">
                                <i class="bi bi-book me-2"></i>View Course Details
                            </a>
                            
                            <?php if ($certificate['enrollmentID']): ?>
                                <a href="enrollment_view.php?id=<?php echo $certificate['enrollmentID']; ?>" class="btn btn-outline-info">
                                    <i class="bi bi-journal-check me-2"></i>View Enrollment
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Download History -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Download History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($downloadHistory)): ?>
                            <p class="text-muted text-center mb-0">No downloads recorded yet</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($downloadHistory as $download): ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">
                                                    <?php if ($download['firstName']): ?>
                                                        <?php echo htmlspecialchars($download['firstName'] . ' ' . $download['lastName']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">System</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo date('M d, Y', strtotime($download['downloadedAt'])); ?>
                                                    <br>
                                                    <?php echo date('h:i A', strtotime($download['downloadedAt'])); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-muted d-block">IP: <?php echo htmlspecialchars($download['ipAddress']); ?></small>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($download['userAgent'], 0, 30)); ?>...</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($downloadHistory) > 5): ?>
                                <button class="btn btn-sm btn-outline-info w-100 mt-3" type="button" data-bs-toggle="collapse" data-bs-target="#allDownloads">
                                    Show All Downloads
                                </button>
                                <div class="collapse" id="allDownloads">
                                    <!-- Additional downloads would show here -->
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Certificate Verification -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0">Certificate Verification</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted d-block">Verification URL</small>
                            <div class="input-group">
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . '/verify.php?id=' . $certificate['certificateUUID']); ?>"
                                       id="verificationUrl" readonly>
                                <button class="btn btn-outline-secondary" type="button" onclick="copyVerificationUrl()">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted d-block">QR Code</small>
                            <div class="text-center">
                                <div id="qrcode" class="mb-2"></div>
                                <small class="text-muted">Scan to verify certificate</small>
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="publicVerification" checked>
                            <label class="form-check-label" for="publicVerification">
                                Allow public verification
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quiz Performance -->
        <?php if (!empty($quizResults)): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Quiz Performance</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Quiz</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th>Attempt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($quizResults as $quiz): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($quiz['quizTitle']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo (float)$quiz['percentage'] >= 70 ? 'success' : 'danger'; ?>">
                                            <?php echo number_format($quiz['percentage'], 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ((float)$quiz['percentage'] >= 70): ?>
                                            <span class="badge bg-success">Passed</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Failed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($quiz['submittedAt'])); ?>
                                        <br><small class="text-muted"><?php echo date('h:i A', strtotime($quiz['submittedAt'])); ?></small>
                                    </td>
                                    <td>#<?php echo $quiz['resultID']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Share Modal -->
<div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shareModalLabel">Share Certificate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Share Link</label>
                    <div class="input-group">
                        <input type="text" class="form-control" 
                               value="<?php echo htmlspecialchars('https://' . $_SERVER['HTTP_HOST'] . '/certificate/' . $certificate['certificateUUID']); ?>"
                               id="shareLink" readonly>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyShareLink()">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Share via Email</label>
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Enter email address" id="shareEmail">
                        <button class="btn btn-primary" type="button" onclick="shareViaEmail()">
                            <i class="bi bi-send"></i>
                        </button>
                    </div>
                </div>
                
                <div class="social-share">
                    <label class="form-label">Share on Social Media</label>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="shareOnFacebook()">
                            <i class="bi bi-facebook"></i> Facebook
                        </button>
                        <button class="btn btn-outline-info btn-sm" onclick="shareOnTwitter()">
                            <i class="bi bi-twitter"></i> Twitter
                        </button>
                        <button class="btn btn-outline-success btn-sm" onclick="shareOnLinkedIn()">
                            <i class="bi bi-linkedin"></i> LinkedIn
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- QR Code Library -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.0/build/qrcode.min.js"></script>

<script>
// Copy UUID to clipboard
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        const toast = new bootstrap.Toast(document.getElementById('copyToast'));
        document.getElementById('copyText').textContent = 'Copied UUID to clipboard';
        toast.show();
    });
}

// Copy verification URL
function copyVerificationUrl() {
    const url = document.getElementById('verificationUrl');
    url.select();
    navigator.clipboard.writeText(url.value).then(() => {
        const toast = new bootstrap.Toast(document.getElementById('copyToast'));
        document.getElementById('copyText').textContent = 'Copied verification URL';
        toast.show();
    });
}

// Copy share link
function copyShareLink() {
    const link = document.getElementById('shareLink');
    link.select();
    navigator.clipboard.writeText(link.value).then(() => {
        const toast = new bootstrap.Toast(document.getElementById('copyToast'));
        document.getElementById('copyText').textContent = 'Copied share link';
        toast.show();
    });
}

// Share via email
function shareViaEmail() {
    const email = document.getElementById('shareEmail').value;
    const subject = 'Certificate of Completion - ' + '<?php echo addslashes($certificate["courseTitle"]); ?>';
    const body = 'Dear recipient,\n\n' +
                 'Please find attached the certificate of completion for:\n' +
                 'Student: <?php echo addslashes($certificate["studentName"]); ?>\n' +
                 'Course: <?php echo addslashes($certificate["courseTitle"]); ?>\n\n' +
                 'You can view the certificate here: ' + document.getElementById('shareLink').value + '\n\n' +
                 'Best regards,\n' +
                 'Learnexus Admin';
    
    window.location.href = 'mailto:' + email + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
}

// Social media sharing
function shareOnFacebook() {
    const url = encodeURIComponent(document.getElementById('shareLink').value);
    window.open('https://www.facebook.com/sharer/sharer.php?u=' + url, '_blank');
}

function shareOnTwitter() {
    const url = encodeURIComponent(document.getElementById('shareLink').value);
    const text = encodeURIComponent('Check out this certificate: <?php echo addslashes($certificate["studentName"]); ?> completed <?php echo addslashes($certificate["courseTitle"]); ?>');
    window.open('https://twitter.com/intent/tweet?url=' + url + '&text=' + text, '_blank');
}

function shareOnLinkedIn() {
    const url = encodeURIComponent(document.getElementById('shareLink').value);
    window.open('https://www.linkedin.com/sharing/share-offsite/?url=' + url, '_blank');
}

// Generate QR Code
document.addEventListener('DOMContentLoaded', function() {
    const verificationUrl = document.getElementById('verificationUrl').value;
    QRCode.toCanvas(document.getElementById('qrcode'), verificationUrl, {
        width: 128,
        height: 128,
        margin: 1,
        color: {
            dark: '#000000',
            light: '#FFFFFF'
        }
    }, function(error) {
        if (error) console.error(error);
    });
});
</script>

<!-- Copy Success Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="copyToast" class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body">
                <i class="bi bi-check-circle me-2"></i>
                <span id="copyText"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>