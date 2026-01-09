<?php
session_start();
require_once 'database/db_connect.php';
require_once 'helpers/OTPHelper.php';
require_once 'config/email_config.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];

    try {
        $stmt = $conn->prepare("SELECT * FROM users WHERE (email = ? OR studentNumber = ? OR teacherNumber = ?) AND status = 'active'");
        $stmt->execute([$identifier, $identifier, $identifier]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['passwordHash'])) {
            if (!$user['emailVerified']) {
                $_SESSION['error'] = 'Please verify your email address before logging in.';

                $otpHelper = new OTPHelper($conn);
                $otpCode = $otpHelper->createEmailOTP($user['email']);

                if ($otpCode) {
                    $toName = $user['firstName'] . ' ' . $user['lastName'];
                    sendEmailOTP($user['email'], $toName, $otpCode);
                }

                $_SESSION['otp_email'] = $user['email'];
                $_SESSION['pending_verification_user'] = $user['userID'];
                header('Location: verify_email.php');
                exit();
            }

            $_SESSION['user_id'] = $user['userID'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['first_name'] = $user['firstName'];
            $_SESSION['last_name'] = $user['lastName'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] == 'student' && $user['studentNumber']) {
                $_SESSION['student_number'] = $user['studentNumber'];
            }
            if ($user['role'] == 'instructor' && $user['teacherNumber']) {
                $_SESSION['teacher_number'] = $user['teacherNumber'];
            }

            switch ($user['role']) {
                case 'student':
                    header('Location: student/dashboard.php');
                    exit();
                case 'instructor':
                    header('Location: teacher/dashboard.php');
                    exit();
                case 'admin':
                    header('Location: admin/dashboard.php');
                    exit();
                default:
                    $_SESSION['error'] = 'Invalid user role';
                    header('Location: index.php');
                    exit();
            }
        } else {
            $_SESSION['error'] = 'Invalid ID or password';
            header('Location: index.php');
            exit();
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Database error. Please try again later.';
        header('Location: index.php');
        exit();
    }
} else {
    header('Location: index.php');
    exit();
}
