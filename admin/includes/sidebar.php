<div class="sidebar offcanvas-lg offcanvas-start position-fixed top-0 start-0 h-100" style="width: var(--sidebar-width);" id="sidebar">
    <div class="offcanvas-header d-lg-none border-bottom">
        <h5 class="offcanvas-title sidebar-brand">LEARNEXUS ADMIN</h5>
    </div>

    <div class="offcanvas-body p-0 d-flex flex-column h-100">
        <div class="sidebar-brand px-4 py-4 mb-4 d-none d-lg-block">LEARNEXUS ADMIN</div>
        
        <nav class="flex-grow-1 px-3">
            <!-- Dashboard -->
            <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium 
                      <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
               href="dashboard.php">
                <i class="bi bi-speedometer2 fs-5"></i>
                <span>Dashboard</span>
            </a>

            <!-- Users -->
            <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium 
                      <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" 
               href="users.php">
                <i class="bi bi-people fs-5"></i>
                <span>Users</span>
            </a>

            <!-- Courses -->
            <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium 
                      <?php echo basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : ''; ?>" 
               href="courses.php">
                <i class="bi bi-book fs-5"></i>
                <span>Courses</span>
            </a>

            <!-- Enrollments -->
            <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium 
                      <?php echo basename($_SERVER['PHP_SELF']) == 'enrollments.php' ? 'active' : ''; ?>" 
               href="enrollments.php">
                <i class="bi bi-journal-check fs-5"></i>
                <span>Enrollments</span>
            </a>

            <!-- Payments -->
            <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium 
                      <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>" 
               href="payments.php">
                <i class="bi bi-credit-card fs-5"></i>
                <span>Payments</span>
            </a>

            <!-- Certificates -->
            <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium 
                      <?php echo basename($_SERVER['PHP_SELF']) == 'certificates.php' ? 'active' : ''; ?>" 
               href="certificates.php">
                <i class="bi bi-award fs-5"></i>
                <span>Certificates</span>
            </a>

            <!-- Vouchers -->
            <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium 
                      <?php echo basename($_SERVER['PHP_SELF']) == 'vouchers.php' ? 'active' : ''; ?>" 
               href="vouchers.php">
                <i class="bi bi-ticket-perforated fs-5"></i>
                <span>Vouchers</span>
            </a>
        </nav>
        
        <div class="p-3 mt-auto">
            <button class="btn btn-outline-danger w-100 rounded-pill fw-semibold" onclick="window.location.href='logout.php'">
                <i class="bi bi-box-arrow-left me-2"></i>Logout
            </button>
        </div>
    </div>
</div>

<style>
:root {
    --sidebar-width: 260px;
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

/* Main Content Margin */
@media (min-width: 992px) {
    .main-content {
        margin-left: var(--sidebar-width);
    }
}

@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.show {
        transform: translateX(0);
    }
}
</style>