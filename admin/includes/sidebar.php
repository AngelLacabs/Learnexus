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
    </ul>
</div>