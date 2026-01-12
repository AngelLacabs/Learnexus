<div class="sidebar">
    <div class="sidebar-header">
        <h5 class="mb-0">Admin Menu</h5>
    </div>
    
    <ul class="sidebar-menu">
        <!-- Dashboard -->
        <li class="menu-item">
            <a href="dashboard.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- User Management -->
        <li class="menu-item">
            <a href="users.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <i class="bi bi-people"></i>
                <span>Users</span>
            </a>
        </li>

        <!-- Course Management -->
        <li class="menu-item">
            <a href="courses.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : ''; ?>">
                <i class="bi bi-book"></i>
                <span>Courses</span>
            </a>
        </li>

        <!-- Enrollments -->
        <li class="menu-item">
            <a href="enrollments.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'enrollments.php' ? 'active' : ''; ?>">
                <i class="bi bi-journal-check"></i>
                <span>Enrollments</span>
            </a>
        </li>

        <!-- Payments -->
        <li class="menu-item">
            <a href="payments.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>">
                <i class="bi bi-credit-card"></i>
                <span>Payments</span>
            </a>
        </li>

        <!-- Certificates -->
        <li class="menu-item">
            <a href="certificates.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'certificates.php' ? 'active' : ''; ?>">
                <i class="bi bi-award"></i>
                <span>Certificates</span>
            </a>
        </li>

        <!-- Vouchers -->
        <li class="menu-item">
            <a href="vouchers.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'vouchers.php' ? 'active' : ''; ?>">
                <i class="bi bi-ticket-perforated"></i>
                <span>Vouchers</span>
            </a>
        </li>

        <!-- Divider -->
        <li class="menu-divider"></li>

        <!-- OTP Logs -->
        <li class="menu-item">
            <a href="otp-logs.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'otp-logs.php' ? 'active' : ''; ?>">
                <i class="bi bi-shield-lock"></i>
                <span>OTP Logs</span>
            </a>
        </li>

        <!-- SMS Feedback -->
        <li class="menu-item">
            <a href="sms-feedback.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'sms-feedback.php' ? 'active' : ''; ?>">
                <i class="bi bi-chat-dots"></i>
                <span>SMS Feedback</span>
            </a>
        </li>

        <!-- Announcements -->
        <li class="menu-item">
            <a href="announcements.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>">
                <i class="bi bi-megaphone"></i>
                <span>Announcements</span>
            </a>
        </li>

        <!-- Divider -->
        <li class="menu-divider"></li>

        <!-- Reports -->
        <li class="menu-item">
            <a href="reports.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class="bi bi-graph-up"></i>
                <span>Reports</span>
            </a>
        </li>

        <!-- Activity Logs -->
        <li class="menu-item">
            <a href="activity-logs.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'activity-logs.php' ? 'active' : ''; ?>">
                <i class="bi bi-clock-history"></i>
                <span>Activity Logs</span>
            </a>
        </li>

        <!-- System Settings -->
        <li class="menu-item">
            <a href="settings.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class="bi bi-gear"></i>
                <span>Settings</span>
            </a>
        </li>
    </ul>
</div><li class="menu-item">
    <a class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" href="users.php">
        <i class="bi bi-people"></i>
        <span>Users</span>
    </a>
</li>
<!-- Add this in the sidebar menu -->
<li class="menu-item">
    <a href="courses.php" class="menu-link <?php echo basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : ''; ?>">
        <i class="bi bi-book"></i>
        <span>Course Management</span>
    </a>
</li>

<!-- Add this to your sidebar menu in includes/sidebar.php -->
<li class="nav-item">
    <a class="nav-link" href="payments.php">
        <i class="bi bi-cash-stack"></i>
        <span>Payments</span>
    </a>
</li>