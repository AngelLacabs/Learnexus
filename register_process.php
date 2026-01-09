<?php
session_start();
require_once 'database/db_connect.php';
require_once 'helpers/otp_helper.php';
require_once 'config/email_config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: register.php');
    exit();
}

$firstName = trim($_POST['firstName'] ?? '');
$lastName = trim($_POST['lastName'] ?? '');
$middleInitial = trim($_POST['middleInitial'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$role = $_POST['role'] ?? '';

if ($role == 'student') {
    $studentNumber = trim($_POST['studentNumber'] ?? '');
    $teacherNumber = null;
} else if ($role == 'instructor') {
    $teacherNumber = trim($_POST['teacherNumber'] ?? '');
    $studentNumber = null;
} else {
    $_SESSION['error'] = 'Invalid role selected.';
    header('Location: register.php');
    exit();
}

$_SESSION['pending_registration'] = [
    'firstName' => $firstName,
    'lastName' => $lastName,
    'middleInitial' => $middleInitial,
    'email' => $email,
    'phone' => $phone,
    'password' => $password,
    'role' => $role,
    'studentNumber' => $studentNumber,
    'teacherNumber' => $teacherNumber
];

$errors = [];

if (empty($firstName)) $errors[] = 'First Name is required';
if (empty($lastName)) $errors[] = 'Last Name is required';
if (empty($email)) $errors[] = 'Email Address is required';
if (empty($password)) $errors[] = 'Password is required';
if (empty($phone)) $errors[] = 'Phone Number is required';

if ($role == 'student' && empty($studentNumber)) {
    $errors[] = 'Student Number is required';
} elseif ($role == 'instructor' && empty($teacherNumber)) {
    $errors[] = 'Teacher Number is required';
}

if ($password !== $confirm_password) {
    $errors[] = 'Passwords do not match';
}

if (!empty($phone) && !preg_match('/^\d{11}$/', $phone)) {
    $errors[] = 'Phone number must be exactly 11 digits';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format';
}

if (!empty($password) && strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters';
}

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header('Location: register.php?role=' . urlencode($role));
    exit();
}

try {
    $stmt = $conn->prepare("SELECT userID FROM users WHERE email = ?");
    $stmt->execute([$email]);

    if ($stmt->rowCount() > 0) {
        $_SESSION['error'] = 'Email already registered. Please use a different email or login.';
        header('Location: register.php?role=' . urlencode($role));
        exit();
    }

    if ($role == 'student') {
        $stmt = $conn->prepare("SELECT userID FROM users WHERE studentNumber = ?");
        $stmt->execute([$studentNumber]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = 'Student Number already registered.';
            header('Location: register.php?role=' . urlencode($role));
            exit();
        }
    } else {
        $stmt = $conn->prepare("SELECT userID FROM users WHERE teacherNumber = ?");
        $stmt->execute([$teacherNumber]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = 'Teacher Number already registered.';
            header('Location: register.php?role=' . urlencode($role));
            exit();
        }
    }

    $otpHelper = new OTPHelper($conn);
    $otpCode = $otpHelper->createEmailOTP($email);

    if ($otpCode) {
        $toName = $firstName . ' ' . $lastName;
        $emailSent = sendEmailOTP($email, $toName, $otpCode);

        if ($emailSent) {
            $_SESSION['otp_email'] = $email;
            $_SESSION['success'] = 'OTP has been sent to your email! Please check your inbox.';
            header('Location: verify_email.php');
            exit();
        } else {
            $_SESSION['error'] = 'Failed to send OTP email. Please try again.';
            header('Location: register.php?role=' . urlencode($role));
            exit();
        }
    } else {
        $_SESSION['error'] = 'Failed to generate OTP. Please try again.';
        header('Location: register.php?role=' . urlencode($role));
        exit();
    }
} catch (PDOException $e) {
    error_log("Registration Error: " . $e->getMessage());
    $_SESSION['error'] = 'Registration failed due to a system error. Please try again later.';
    header('Location: register.php?role=' . urlencode($role));
    exit();
}
