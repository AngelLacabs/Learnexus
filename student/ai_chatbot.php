<?php
session_start();
require_once '../database/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];
$student_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Get user avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$userID]);
$userAvatar = $stmt->fetchColumn();

// ============================================
// BACKEND: Handle POST requests to Ollama
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prompt'])) {
    // Sanitize input
    $user_question = trim(filter_input(INPUT_POST, 'prompt', FILTER_SANITIZE_STRING));

    if (empty($user_question)) {
        echo "ERROR: Please enter a valid question.";
        exit;
    }

    // Check if the question is about courses
    $course_keywords = ['course', 'price', 'cost', 'available', 'cheap', 'expensive', 'category', 'data administration', 'riph', 'web development', 'sad', 'programming', 'design', 'history', 'enrollment', 'lesson', 'instructor'];

    $is_course_related = false;
    $question_lower = strtolower($user_question);

    foreach ($course_keywords as $keyword) {
        if (strpos($question_lower, $keyword) !== false) {
            $is_course_related = true;
            break;
        }
    }

    // If not course-related, respond with guidance and do NOT query the database
    if (!$is_course_related) {
        echo "Sorry, I can only answer questions related to the available courses in the system.\n\n";
        echo "You can ask me about:\n";
        echo "• Available courses\n";
        echo "• Course prices\n";
        echo "• Course categories\n";
        echo "• Specific course details\n";
        echo "• Enrollment and lessons\n";
        echo "• Instructors";
        exit;
    }

    // For course-related questions, fetch course data from database
    $courses = getCoursesFromDatabase($conn);

    if (empty($courses)) {
        echo "Sorry, there's no available course right now.";
        exit;
    }

    // FIRST: Try to answer directly from database
    $direct_answer = getDirectAnswerFromDatabase($user_question, $courses);
    if ($direct_answer !== false) {
        echo $direct_answer;
        exit;
    }

    // If direct answer not found, use Ollama
    $course_data_text = formatCoursesForAI($courses);
    $prompt = "Here is the course information:\n\n{$course_data_text}\n\nQuestion: {$user_question}\n\nPlease answer based on the information above. Be helpful and concise.";

    // Prepare request to Ollama API
    $payload = json_encode([
        'model' => 'gemma3:270m',
        'prompt' => $prompt,
        'stream' => false
    ]);

    $ch = curl_init('http://127.0.0.1:11434/api/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        echo generateFallbackResponse($user_question, $courses);
        exit;
    }

    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        echo generateFallbackResponse($user_question, $courses);
        exit;
    }

    $result = json_decode($response, true);
    $reply = $result['response'] ?? 'No response from AI';

    // Clean up the response
    $reply = trim($reply);
    $reply = htmlspecialchars($reply);

    echo $reply;
    exit;
}

// Function to get courses from database
function getCoursesFromDatabase($conn)
{
    try {
        $stmt = $conn->prepare("
            SELECT c.*, 
                   CONCAT(u.firstName, ' ', u.lastName) as instructorName,
                   (SELECT COUNT(*) FROM enrollments WHERE courseID = c.courseID) as enrollmentCount,
                   (SELECT COUNT(*) FROM lessons WHERE courseID = c.courseID) as lessonCount
            FROM courses c
            JOIN users u ON c.teacherID = u.userID
            WHERE c.status = 'published'
            ORDER BY c.category, c.title
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return [];
    }
}

// Function to format courses for AI prompt
function formatCoursesForAI($courses)
{
    $text = "";
    foreach ($courses as $course) {
        $text .= "Course: {$course['title']}\n";
        $text .= "Category: {$course['category']}\n";
        $text .= "Price: ₱" . number_format($course['price'], 2) . "\n";
        $text .= "Instructor: {$course['instructorName']}\n";
        $text .= "Enrollments: {$course['enrollmentCount']}\n";
        $text .= "Lessons: {$course['lessonCount']}\n";
        $text .= "Description: {$course['description']}\n";
        $text .= "Status: {$course['status']}\n\n";
    }
    return $text;
}

// Function to get direct answer from database
function getDirectAnswerFromDatabase($question, $courses)
{
    $question_lower = strtolower($question);

    // Available courses
    if (strpos($question_lower, 'available') !== false || strpos($question_lower, 'what courses') !== false || strpos($question_lower, 'list') !== false) {
        $response = "📚 **AVAILABLE COURSES**\n\n";
        $categories = [];

        foreach ($courses as $course) {
            $category = $course['category'] ?? 'General';
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $course;
        }

        foreach ($categories as $category => $categoryCourses) {
            $response .= "*{$category}*:\n";
            foreach ($categoryCourses as $course) {
                $response .= "• {$course['title']} - ₱" . number_format($course['price'], 2) . " ({$course['enrollmentCount']} enrolled, {$course['lessonCount']} lessons)\n";
            }
            $response .= "\n";
        }

        $response .= "*Total:* " . count($courses) . " courses available.";
        return $response;
    }

    // Prices
    if (strpos($question_lower, 'price') !== false || strpos($question_lower, 'cost') !== false || strpos($question_lower, 'how much') !== false) {
        $response = "💰 **COURSE PRICES**\n\n";
        foreach ($courses as $course) {
            $response .= "• {$course['title']}: ₱" . number_format($course['price'], 2) . "\n";
        }

        // Calculate cheapest and most expensive
        $prices = array_column($courses, 'price');
        $min_price = min($prices);
        $max_price = max($prices);

        $cheapest = array_filter($courses, fn($c) => $c['price'] == $min_price);
        $expensive = array_filter($courses, fn($c) => $c['price'] == $max_price);

        $response .= "\n**Price Summary:**\n";
        $response .= "• Cheapest: " . implode(', ', array_column($cheapest, 'title')) . " (₱" . number_format($min_price, 2) . ")\n";
        $response .= "• Most Expensive: " . implode(', ', array_column($expensive, 'title')) . " (₱" . number_format($max_price, 2) . ")";

        return $response;
    }

    // Cheapest courses
    if (strpos($question_lower, 'cheap') !== false) {
        $prices = array_column($courses, 'price');
        $min_price = min($prices);
        $cheapest_courses = array_filter($courses, fn($c) => $c['price'] == $min_price);

        $response = "💸 **CHEAPEST COURSES**\n\n";
        foreach ($cheapest_courses as $course) {
            $response .= "• *{$course['title']}* - ₱" . number_format($min_price, 2) . "\n";
            $response .= "  Category: {$course['category']}\n";
            $response .= "  Instructor: {$course['instructorName']}\n";
            $response .= "  Lessons: {$course['lessonCount']}\n\n";
        }
        return $response;
    }

    // Most expensive courses
    if (strpos($question_lower, 'expensive') !== false || strpos($question_lower, 'most expensive') !== false) {
        $prices = array_column($courses, 'price');
        $max_price = max($prices);
        $expensive_courses = array_filter($courses, fn($c) => $c['price'] == $max_price);

        $response = "💎 **MOST EXPENSIVE COURSES**\n\n";
        foreach ($expensive_courses as $course) {
            $response .= "• *{$course['title']}* - ₱" . number_format($max_price, 2) . "\n";
            $response .= "  Category: {$course['category']}\n";
            $response .= "  Instructor: {$course['instructorName']}\n";
            $response .= "  Lessons: {$course['lessonCount']}\n\n";
        }
        return $response;
    }

    // Specific course
    foreach ($courses as $course) {
        $course_name_lower = strtolower($course['title']);
        if (strpos($question_lower, $course_name_lower) !== false) {
            $response = "📖 **" . strtoupper($course['title']) . "**\n\n";
            $response .= "• *Category:* {$course['category']}\n";
            $response .= "• *Price:* ₱" . number_format($course['price'], 2) . "\n";
            $response .= "• *Instructor:* {$course['instructorName']}\n";
            $response .= "• *Enrollments:* {$course['enrollmentCount']}\n";
            $response .= "• *Lessons:* {$course['lessonCount']}\n";
            $response .= "• *Description:* {$course['description']}\n";
            $response .= "• *Status:* {$course['status']}\n";
            return $response;
        }
    }

    // Category questions
    if (strpos($question_lower, 'category') !== false) {
        $categories = [];
        foreach ($courses as $course) {
            $category = $course['category'] ?? 'General';
            if (!isset($categories[$category])) {
                $categories[$category] = [];
            }
            $categories[$category][] = $course;
        }

        $response = "🏷️ **COURSES BY CATEGORY**\n\n";
        foreach ($categories as $category => $categoryCourses) {
            $response .= "*{$category}* (" . count($categoryCourses) . " courses):\n";
            foreach ($categoryCourses as $course) {
                $response .= "• {$course['title']} - ₱" . number_format($course['price'], 2) . "\n";
            }
            $response .= "\n";
        }
        return $response;
    }

    // Instructor questions
    if (strpos($question_lower, 'instructor') !== false || strpos($question_lower, 'teacher') !== false) {
        $instructors = [];
        foreach ($courses as $course) {
            $instructor = $course['instructorName'];
            if (!isset($instructors[$instructor])) {
                $instructors[$instructor] = [];
            }
            $instructors[$instructor][] = $course['title'];
        }

        $response = "👨‍🏫 **COURSES BY INSTRUCTOR**\n\n";
        foreach ($instructors as $instructor => $courseList) {
            $response .= "*{$instructor}* (" . count($courseList) . " courses):\n";
            $response .= "• " . implode(', ', $courseList) . "\n\n";
        }
        return $response;
    }

    return false; // No direct answer found
}

// Fallback response generator
function generateFallbackResponse($question, $courses)
{
    $question_lower = strtolower($question);

    // Try direct answer first
    $direct_answer = getDirectAnswerFromDatabase($question, $courses);
    if ($direct_answer !== false) {
        return $direct_answer . "\n\n*(Note: Ollama is currently unavailable)*";
    }

    // Generic fallback
    return "I can help with course information from the database. Try asking:\n\n" .
        "• What courses are available?\n" .
        "• How much do courses cost?\n" .
        "• Tell me about [course name]\n" .
        "• What are the cheapest/most expensive courses?\n" .
        "• Courses by category or instructor\n\n" .
        "(Ollama is currently unavailable)";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Course Assistant - Learnexus</title>
    <link rel="icon" type="image/png" href="../images/Learnexus.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --sidebar-width: 260px;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .sidebar {
            background: linear-gradient(180deg, #e8f0fe 0%, #f0f4ff 50%, #f8f9fa 100%);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.08);
        }

        .sidebar-brand {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #1a73e8 0%, #4285f4 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

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
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
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

        /* AI Chat specific styles */
        .chat-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            flex: 1;
            min-height: 70vh;
            height: calc(100vh - 180px);
            /* Adjust based on header height */
        }

        .chat-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
            flex-shrink: 0;
        }

        .chat-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .chat-header p {
            font-size: 14px;
            opacity: 0.9;
        }

        #chat {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background: #f8f9fa;
            min-height: 200px;
        }

        .message {
            margin-bottom: 20px;
            padding: 18px 22px;
            border-radius: 18px;
            max-width: 85%;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease-in;
            line-height: 1.5;
            white-space: pre-wrap;
            font-size: 15px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .user-message {
            background: #667eea;
            color: white;
            margin-left: auto;
            text-align: right;
            border-bottom-right-radius: 5px;
        }

        .bot-message {
            background: white;
            color: #333;
            border: 1px solid #e0e0e0;
            border-bottom-left-radius: 5px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef5350;
        }

        .message-label {
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 8px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-area {
            padding: 20px;
            background: white;
            border-top: 2px solid #f0f0f0;
            display: flex !important;
            gap: 12px;
            flex-shrink: 0;
            visibility: visible !important;
            opacity: 1 !important;
        }

        #input {
            flex: 1;
            padding: 16px 22px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.3s;
        }

        #input:focus {
            border-color: #667eea;
        }

        .send-btn {
            padding: 16px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
            flex-shrink: 0;
        }

        .send-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        .send-btn:active {
            transform: translateY(0);
        }

        .send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .loading {
            display: inline-block;
            width: 10px;
            height: 10px;
            margin: 0 3px;
            border-radius: 50%;
            background: #667eea;
            animation: pulse 1.5s ease-in-out infinite;
        }

        .loading:nth-child(2) {
            animation-delay: 0.2s;
        }

        .loading:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 0.3;
                transform: scale(0.8);
            }

            50% {
                opacity: 1;
                transform: scale(1.2);
            }
        }

        .info-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #e1bee7 100%);
            border-left: 4px solid #667eea;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 25px;
            font-size: 14px;
            color: #333;
            flex-shrink: 0;
        }

        .info-box strong {
            color: #667eea;
        }

        .suggestions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding: 15px 25px;
            background: #f8f9fa;
            border-bottom: 1px solid #e0e0e0;
            flex-shrink: 0;
        }

        .suggestion-chip {
            background: #f0f0f0;
            padding: 10px 18px;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #e0e0e0;
            white-space: nowrap;
            font-weight: 500;
        }

        .suggestion-chip:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.2);
        }

        .data-info {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 10px 20px;
            margin: 15px 25px;
            font-size: 13px;
            color: #155724;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }

        .data-info i {
            color: #28a745;
        }

        /* Header */
        .header-card {
            background: white;
            border-radius: 16px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
        }

        .header-card .card-body {
            padding: 15px;
        }

        /* Scrollbar styling */
        #chat::-webkit-scrollbar {
            width: 8px;
        }

        #chat::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        #chat::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        #chat::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }

        /* Typing indicator */
        .typing-indicator {
            display: none;
            padding: 18px 22px;
            margin-bottom: 20px;
            background: white;
            border: 1px solid #e0e0e0;
            border-bottom-left-radius: 18px;
            border-radius: 18px;
            max-width: 85%;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .typing-indicator .message-label {
            margin-bottom: 10px;
        }

        .typing-dots {
            display: flex;
            gap: 4px;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #667eea;
            animation: typing 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) {
            animation-delay: -0.32s;
        }

        .typing-dot:nth-child(2) {
            animation-delay: -0.16s;
        }

        @keyframes typing {

            0%,
            80%,
            100% {
                transform: scale(0.8);
                opacity: 0.5;
            }

            40% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Ensure main content takes full height */
        .main-content {
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .container-fluid {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
            /* Important for flex children to scroll properly */
        }

        .row:last-child {
            flex: 1;
            min-height: 0;
            /* Important for flex children to scroll properly */
        }

        /* Make sure input area is always at bottom */
        .input-area {
            position: relative;
            z-index: 10;
            background: white;
        }
    </style>
</head>

<body>
    <!-- Hamburger Button -->
    <div class="position-fixed top-0 start-0 p-3 d-lg-none" style="z-index: 1100;">
        <button class="hamburger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar"
            id="hamburgerBtn">
            <div class="hamburger-icon d-flex flex-column align-items-center justify-content-center">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </button>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar offcanvas-lg offcanvas-start position-fixed top-0 start-0 h-100"
        style="width: var(--sidebar-width);" id="sidebar">
        <div class="offcanvas-header d-lg-none border-bottom">
            <h5 class="offcanvas-title sidebar-brand">LEARNEXUS</h5>
        </div>

        <div class="offcanvas-body p-0 d-flex flex-column h-100">
            <div class="sidebar-brand px-4 py-4 mb-4 d-none d-lg-block">LEARNEXUS</div>

            <nav class="flex-grow-1 px-3">
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="dashboard.php">
                    <i class="bi bi-grid fs-5"></i><span>Dashboard</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="course_catalog.php">
                    <i class="bi bi-book fs-5"></i><span>Course Catalog</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="my_courses.php">
                    <i class="bi bi-journal-bookmark fs-5"></i><span>My Courses</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="certificates.php">
                    <i class="bi bi-award fs-5"></i><span>Certificates</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="vouchers.php">
                    <i class="bi bi-ticket-perforated fs-5"></i><span>Vouchers</span>
                </a>
                <a class="nav-link d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="settings.php">
                    <i class="bi bi-gear fs-5"></i><span>Settings</span>
                </a>
                <a class="nav-link active d-flex align-items-center gap-3 px-3 py-3 mb-2 text-dark fw-medium"
                    href="ai_chatbot.php">
                    <i class="bi bi-robot fs-5"></i><span>AI Tutor</span>
                </a>
            </nav>

            <div class="p-3 mt-auto">
                <button class="btn btn-outline-danger w-100 rounded-pill fw-semibold"
                    onclick="window.location.href='../logout.php'">
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
                    <div class="card border-0 rounded-4 shadow-sm header-card">
                        <div class="card-body p-3 d-flex justify-content-between align-items-center gap-3">
                            <div style="flex: 1;">
                                <h1 class="h4 fw-bold mb-0 text-primary">
                                    <i class="bi bi-robot me-2"></i>AI Course Assistant
                                </h1>
                                <p class="text-muted small mb-0 mt-1">Ask about courses, prices, and availability</p>
                            </div>

                            <div class="d-flex align-items-center gap-3" onclick="window.location.href='settings.php'"
                                role="button" style="flex-shrink: 0;">
                                <span class="fw-semibold d-none d-sm-inline text-nowrap">
                                    <?php echo htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']); ?>
                                </span>
                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                    style="width: 45px; height: 45px; min-width: 45px; background: linear-gradient(135deg, #667eea, #764ba2);">
                                    <?php if (!empty($userAvatar) && file_exists($userAvatar)): ?>
                                        <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar"
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

            <!-- Chat Container -->
            <div class="row flex-grow-1">
                <div class="col-12 d-flex flex-column" style="min-height: 0;">
                    <div class="chat-container">
                        <div class="chat-header">
                            <h1><i class="fas fa-brain"></i> AI Course Assistant</h1>
                            <p>Powered by Database Knowledge & Ollama AI</p>
                        </div>

                        <div class="data-info">
                            <i class="fas fa-database"></i>
                            <span>Knowledge source: <strong>Database</strong> - Real-time course information</span>
                        </div>

                        <div class="info-box">
                            <strong><i class="fas fa-info-circle"></i> I can answer these questions
                                accurately:</strong><br>
                            - What courses are available?<br>
                            - Course prices and cost comparisons<br>
                            - Cheapest and most expensive courses<br>
                            - Specific course details (Data Administration, RIPH, etc.)<br>
                            - Courses by category or instructor<br>
                            - Enrollment and lesson counts
                        </div>

                        <div class="suggestions">
                            <div class="suggestion-chip" onclick="askQuestion('What courses are available?')">
                                What courses are available?
                            </div>
                            <div class="suggestion-chip" onclick="askQuestion('How much do courses cost?')">
                                Course prices
                            </div>
                            <div class="suggestion-chip" onclick="askQuestion('What are the cheapest courses?')">
                                Cheapest courses
                            </div>
                            <div class="suggestion-chip" onclick="askQuestion('What are the most expensive courses?')">
                                Most expensive
                            </div>
                            <div class="suggestion-chip" onclick="askQuestion('Courses by category')">
                                By category
                            </div>
                        </div>

                        <div id="chat"></div>

                        <!-- INPUT AREA - ALWAYS VISIBLE -->
                        <div class="input-area">
                            <input id="input" type="text" placeholder="Type your question about courses here..."
                                onkeypress="if(event.key==='Enter') sendMessage()" autocomplete="off">
                            <button id="sendBtn" class="send-btn" onclick="sendMessage()">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>

        const hamburgerBtn = document.getElementById('hamburgerBtn');
        const sidebar = document.getElementById('sidebar');

        if (hamburgerBtn && sidebar) {
            sidebar.addEventListener('show.bs.offcanvas', () => hamburgerBtn.classList.add('active'));
            sidebar.addEventListener('hide.bs.offcanvas', () => hamburgerBtn.classList.remove('active'));
        }

        const navLinks = document.querySelectorAll('.sidebar .nav-link');
        const currentPage = window.location.pathname.split('/').pop();

        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                navLinks.forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            }
            link.addEventListener('click', () => {
                if (window.innerWidth <= 992) {
                    const offcanvas = bootstrap.Offcanvas.getInstance(sidebar);
                    if (offcanvas) offcanvas.hide();
                }
            });
        });

        // Chat functionality
        let isProcessing = false;

        function askQuestion(question) {
            document.getElementById('input').value = question;
            sendMessage();
        }

        function sendMessage() {
            if (isProcessing) return;

            const chat = document.getElementById("chat");
            const input = document.getElementById("input");
            const sendBtn = document.getElementById("sendBtn");
            const text = input.value.trim();

            if (!text) return;

            isProcessing = true;
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            // Display user message
            const userMsg = document.createElement('div');
            userMsg.className = 'message user-message';
            userMsg.innerHTML = '<div class="message-label">You</div>' + escapeHtml(text);
            chat.appendChild(userMsg);

            input.value = "";
            chat.scrollTop = chat.scrollHeight;

            // Show typing indicator
            const typingIndicator = document.createElement('div');
            typingIndicator.className = 'typing-indicator';
            typingIndicator.id = 'typing-indicator';
            typingIndicator.innerHTML = `
                <div class="message-label">AI Assistant</div>
                <div class="typing-dots">
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                    <div class="typing-dot"></div>
                </div>
            `;
            chat.appendChild(typingIndicator);
            chat.scrollTop = chat.scrollHeight;

            // Send to backend
            fetch("ai_chatbot.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "prompt=" + encodeURIComponent(text)
            })
                .then(res => res.text())
                .then(reply => {
                    // Remove typing indicator
                    const indicator = document.getElementById('typing-indicator');
                    if (indicator) indicator.remove();

                    const reply_id = "reply_" + Date.now();
                    const botMsg = document.createElement('div');
                    botMsg.id = reply_id;
                    botMsg.className = 'message bot-message';

                    if (reply.includes('ERROR:') || reply.includes('⚠️')) {
                        botMsg.className = 'message error-message';
                        botMsg.innerHTML = '<div class="message-label"><i class="fas fa-exclamation-triangle"></i> Error</div>' + formatText(reply);
                    } else {
                        botMsg.innerHTML = '<div class="message-label">AI Assistant</div>' + formatText(reply);
                    }

                    chat.appendChild(botMsg);
                    chat.scrollTop = chat.scrollHeight;
                    isProcessing = false;
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                    input.focus();
                })
                .catch(error => {
                    // Remove typing indicator
                    const indicator = document.getElementById('typing-indicator');
                    if (indicator) indicator.remove();

                    const reply_id = "reply_" + Date.now();
                    const errorMsg = document.createElement('div');
                    errorMsg.id = reply_id;
                    errorMsg.className = 'message error-message';
                    errorMsg.innerHTML =
                        '<div class="message-label"><i class="fas fa-exclamation-triangle"></i> Network Error</div>Could not connect to server. Please try again.';
                    chat.appendChild(errorMsg);
                    chat.scrollTop = chat.scrollHeight;
                    isProcessing = false;
                    sendBtn.disabled = false;
                    sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function formatText(text) {
            // Convert markdown-like formatting to HTML
            text = escapeHtml(text);

            // Bold text with **
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // Line breaks
            text = text.replace(/\n/g, '<br>');

            return text;
        }

        // Welcome message and focus input on page load
        window.onload = function () {
            const chat = document.getElementById("chat");
            const welcomeMsg = document.createElement('div');
            welcomeMsg.className = 'message bot-message';
            welcomeMsg.innerHTML = '<div class="message-label">AI Assistant</div>' +
                '<i class="fas fa-hand-wave" style="color: #667eea;"></i> Hello <strong>' +
                escapeHtml("<?php echo htmlspecialchars($_SESSION['first_name']); ?>") +
                '</strong>!<br><br>' +
                'I\'m your AI Course Assistant. I answer questions based on real-time information from the <strong>database</strong>.<br><br>' +
                '<strong>You can ask me about:</strong><br>' +
                '• Available courses<br>' +
                '• Course prices<br>' +
                '• Cheapest and most expensive courses<br>' +
                '• Courses by category or instructor<br>' +
                '• Specific course details<br>' +
                '• Enrollment and lesson counts';
            chat.appendChild(welcomeMsg);
            chat.scrollTop = chat.scrollHeight;

            // Focus on input field immediately
            document.getElementById('input').focus();
        };

        // Also focus on input when clicking anywhere in the chat area
        document.getElementById('chat').addEventListener('click', function () {
            document.getElementById('input').focus();
        });
    </script>
</body>

</html>