<?php
// register_process.php
session_start();
require_once 'database/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $middleInitial = trim($_POST['middleInitial']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];
    
    // Role-specific ID
    if ($role == 'student') {
        $studentNumber = trim($_POST['studentNumber']);
        $teacherNumber = null;
    } else {
        $teacherNumber = trim($_POST['teacherNumber']);
        $studentNumber = null;
    }

    // Store all form data in session to repopulate form if error occurs
    $_SESSION['register_data'] = [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'middleInitial' => $middleInitial,
        'email' => $email,
        'phone' => $phone,
        'studentNumber' => $studentNumber,
        'teacherNumber' => $teacherNumber
    ];

    // Validation
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

    // Phone validation (must be 11 digits)
    if (!empty($phone) && !preg_match('/^\d{11}$/', $phone)) {
        $errors[] = 'Phone number must be exactly 11 digits';
    }

    // Email validation
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    // If there are validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['error'] = implode('<br>', $errors);
        header('Location: register.php?role=' . urlencode($role));
        exit();
    }

    try {
        // Check if email exists
        $stmt = $conn->prepare("SELECT userID FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['error'] = 'Email already registered';
            header('Location: register.php?role=' . urlencode($role));
            exit();
        }

        // Check if student/teacher number already exists
        if ($role == 'student') {
            $stmt = $conn->prepare("SELECT userID FROM users WHERE studentNumber = ?");
            $stmt->execute([$studentNumber]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['error'] = 'Student Number already registered';
                header('Location: register.php?role=' . urlencode($role));
                exit();
            }
        } else {
            $stmt = $conn->prepare("SELECT userID FROM users WHERE teacherNumber = ?");
            $stmt->execute([$teacherNumber]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['error'] = 'Teacher Number already registered';
                header('Location: register.php?role=' . urlencode($role));
                exit();
            }
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Insert user with appropriate number
        if ($role == 'student') {
            $stmt = $conn->prepare("INSERT INTO users (email, passwordHash, firstName, lastName, middleInitial, phone, role, studentNumber) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $passwordHash, $firstName, $lastName, $middleInitial, $phone, $role, $studentNumber]);
        } else {
            $stmt = $conn->prepare("INSERT INTO users (email, passwordHash, firstName, lastName, middleInitial, phone, role, teacherNumber) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$email, $passwordHash, $firstName, $lastName, $middleInitial, $phone, $role, $teacherNumber]);
        }

        // Get the inserted user ID
        $userID = $conn->lastInsertId();
        
        // Clear registration data from session
        unset($_SESSION['register_data']);
        unset($_SESSION['error']);
        
        // Auto login after registration
        $_SESSION['user_id'] = $userID;
        $_SESSION['email'] = $email;
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
        $_SESSION['role'] = $role;
        $_SESSION['phone'] = $phone;
        
        // Store number in session based on role
        if ($role == 'student') {
            $_SESSION['student_number'] = $studentNumber;
        } else {
            $_SESSION['teacher_number'] = $teacherNumber;
        }

        // Redirect based on role
        switch ($role) {
            case 'student':
                header('Location: student/dashboard.php');
                exit();
            case 'instructor':
                header('Location: teacher/dashboard.php');
                exit();
            default:
                header('Location: index.php');
                exit();
        }

    } catch(PDOException $e) {
        $_SESSION['error'] = 'Registration failed. Please try again.';
        header('Location: register.php?role=' . urlencode($role));
        exit();
    }
} else {
    header('Location: register.php');
    exit();
}
?>