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
    // Load course knowledge base from text file
    $file_path = __DIR__ . '/course_data.txt';
    
    // Check if file exists
    if (!file_exists($file_path)) {
        echo "ERROR: course_data.txt file not found. Please create it with course information.";
        exit;
    }
    
    $data = file_get_contents($file_path);
    
    if (empty($data)) {
        echo "ERROR: course_data.txt is empty. Please add course information.";
        exit;
    }
    
    // Get user question
    $user_question = $_POST['prompt'];
    
    // Check if the question is about courses (simpler check)
    $course_keywords = ['course', 'price', 'cost', 'available', 'cheap', 'expensive', 'category', 'data administration', 'riph', 'web development', 'sad', 'programming', 'design', 'history'];
    
    $is_course_related = false;
    $question_lower = strtolower($user_question);
    
    foreach ($course_keywords as $keyword) {
        if (strpos($question_lower, $keyword) !== false) {
            $is_course_related = true;
            break;
        }
    }
    
    if (!$is_course_related) {
        echo "I can only answer questions about courses. Please ask about:\n\n";
        echo "• Available courses\n";
        echo "• Course prices\n";
        echo "• Course categories\n";
        echo "• Specific courses (Data Administration, RIPH, Web Development, SAD)";
        exit;
    }
    
    // FIRST: Try to answer directly from the text data without Ollama
    $direct_answer = getDirectAnswerFromText($user_question, $data);
    if ($direct_answer !== false) {
        echo $direct_answer;
        exit;
    }
    
    // If direct answer not found, use Ollama
    // Build SIMPLER prompt for Ollama
    $prompt = "Here is the course information:\n\n{$data}\n\nQuestion: {$user_question}\n\nPlease answer based on the information above:";
    
    // Prepare request to Ollama API
    $payload = json_encode([
        'model' => 'gemma3:270m',
        'prompt' => $prompt,
        'stream' => false
    ]);
    
    // Send request to Ollama
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
        
        // Fallback manual responses if Ollama not available
        echo generateFallbackResponse($user_question, $data);
        exit;
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        // Fallback response
        echo generateFallbackResponse($user_question, $data);
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

// Function to get direct answer from text without Ollama
function getDirectAnswerFromText($question, $data) {
    $question_lower = strtolower($question);
    $data_lower = strtolower($data);
    
    // Extract course information from text
    $courses = [];
    $lines = explode("\n", $data);
    
    $current_course = null;
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Check if line starts a new course
        if (preg_match('/^\d+\.\s+(.+)/', $line, $matches)) {
            $current_course = $matches[1];
            $courses[$current_course] = ['name' => $matches[1]];
        } 
        // Check for course details
        elseif ($current_course && preg_match('/^\s*-\s*(Price|Category|Passing Score|Status|Description):\s*(.+)/i', $line, $matches)) {
            $key = strtolower(str_replace(' ', '_', trim($matches[1])));
            $courses[$current_course][$key] = trim($matches[2]);
        }
    }
    
    // Check for specific questions
    if (strpos($question_lower, 'available') !== false || strpos($question_lower, 'what courses') !== false) {
        $response = "📚 **AVAILABLE COURSES**\n\n";
        $count = 1;
        foreach ($courses as $course) {
            $response .= "{$count}. **{$course['name']}**\n";
            if (isset($course['price'])) {
                $response .= "   • Price: {$course['price']}\n";
            }
            if (isset($course['category'])) {
                $response .= "   • Category: {$course['category']}\n";
            }
            if (isset($course['status'])) {
                $response .= "   • Status: {$course['status']}\n";
            }
            $response .= "\n";
            $count++;
        }
        $response .= "**Total:** " . count($courses) . " courses available.";
        return $response;
    }
    
    if (strpos($question_lower, 'price') !== false || strpos($question_lower, 'cost') !== false || strpos($question_lower, 'how much') !== false) {
        $response = "💰 **COURSE PRICES**\n\n";
        foreach ($courses as $course) {
            if (isset($course['price'])) {
                $response .= "• {$course['name']}: {$course['price']}\n";
            }
        }
        
        // Calculate cheapest and most expensive
        $prices = [];
        foreach ($courses as $course) {
            if (isset($course['price'])) {
                // Extract numeric value from price
                if (preg_match('/₱([\d,.]+)/', $course['price'], $matches)) {
                    $price_value = (float) str_replace(',', '', $matches[1]);
                    $prices[$course['name']] = $price_value;
                }
            }
        }
        
        if (!empty($prices)) {
            $response .= "\n**Price Summary:**\n";
            $min_price = min($prices);
            $max_price = max($prices);
            
            $cheapest = array_keys($prices, $min_price);
            $expensive = array_keys($prices, $max_price);
            
            $response .= "• Cheapest: " . implode(', ', $cheapest) . " (₱" . number_format($min_price, 2) . ")\n";
            $response .= "• Most Expensive: " . implode(', ', $expensive) . " (₱" . number_format($max_price, 2) . ")";
        }
        
        return $response;
    }
    
    if (strpos($question_lower, 'cheap') !== false) {
        $prices = [];
        foreach ($courses as $course) {
            if (isset($course['price'])) {
                if (preg_match('/₱([\d,.]+)/', $course['price'], $matches)) {
                    $price_value = (float) str_replace(',', '', $matches[1]);
                    $prices[$course['name']] = $price_value;
                }
            }
        }
        
        if (!empty($prices)) {
            $min_price = min($prices);
            $cheapest_courses = array_keys($prices, $min_price);
            
            $response = "💸 **CHEAPEST COURSES**\n\n";
            foreach ($cheapest_courses as $course_name) {
                $response .= "• **{$course_name}** - ₱" . number_format($min_price, 2) . "\n";
                if (isset($courses[$course_name]['category'])) {
                    $response .= "  Category: {$courses[$course_name]['category']}\n";
                }
                if (isset($courses[$course_name]['description'])) {
                    $response .= "  Description: {$courses[$course_name]['description']}\n";
                }
                $response .= "\n";
            }
            return $response;
        }
    }
    
    if (strpos($question_lower, 'expensive') !== false || strpos($question_lower, 'most expensive') !== false) {
        $prices = [];
        foreach ($courses as $course) {
            if (isset($course['price'])) {
                if (preg_match('/₱([\d,.]+)/', $course['price'], $matches)) {
                    $price_value = (float) str_replace(',', '', $matches[1]);
                    $prices[$course['name']] = $price_value;
                }
            }
        }
        
        if (!empty($prices)) {
            $max_price = max($prices);
            $expensive_courses = array_keys($prices, $max_price);
            
            $response = "💎 **MOST EXPENSIVE COURSES**\n\n";
            foreach ($expensive_courses as $course_name) {
                $response .= "• **{$course_name}** - ₱" . number_format($max_price, 2) . "\n";
                if (isset($courses[$course_name]['category'])) {
                    $response .= "  Category: {$courses[$course_name]['category']}\n";
                }
                if (isset($courses[$course_name]['description'])) {
                    $response .= "  Description: {$courses[$course_name]['description']}\n";
                }
                $response .= "\n";
            }
            return $response;
        }
    }
    
    // Check for specific course questions
    foreach ($courses as $course_name => $course_info) {
        $course_name_lower = strtolower($course_name);
        if (strpos($question_lower, $course_name_lower) !== false) {
            $response = "📖 **" . strtoupper($course_name) . "**\n\n";
            foreach ($course_info as $key => $value) {
                if ($key !== 'name') {
                    $formatted_key = ucwords(str_replace('_', ' ', $key));
                    $response .= "• **{$formatted_key}:** {$value}\n";
                }
            }
            return $response;
        }
    }
    
    // Check for category questions
    if (strpos($question_lower, 'category') !== false || strpos($question_lower, 'programming') !== false || 
        strpos($question_lower, 'design') !== false || strpos($question_lower, 'history') !== false) {
        
        // Extract all categories
        $categories = [];
        foreach ($courses as $course) {
            if (isset($course['category'])) {
                $category = $course['category'];
                if (!isset($categories[$category])) {
                    $categories[$category] = [];
                }
                $categories[$category][] = $course['name'];
            }
        }
        
        $response = "🏷️ **COURSES BY CATEGORY**\n\n";
        foreach ($categories as $category => $course_list) {
            $response .= "**{$category}** (" . count($course_list) . " courses):\n";
            foreach ($course_list as $course_name) {
                $response .= "• {$course_name}";
                if (isset($courses[$course_name]['price'])) {
                    $response .= " - {$courses[$course_name]['price']}";
                }
                $response .= "\n";
            }
            $response .= "\n";
        }
        return $response;
    }
    
    return false; // No direct answer found
}

// Fallback response generator
function generateFallbackResponse($question, $data) {
    $question_lower = strtolower($question);
    
    // Try direct answer first
    $direct_answer = getDirectAnswerFromText($question, $data);
    if ($direct_answer !== false) {
        return $direct_answer . "\n\n*(Note: Ollama is currently unavailable)*";
    }
    
    // Generic fallback
    if (strpos($question_lower, 'available') !== false || strpos($question_lower, 'course') !== false) {
        return "Based on course_data.txt, available courses are:\n\n" .
               "1. Data Administration\n" .
               "2. RIPH (Readings in Philippine History)\n" .
               "3. Web Development\n" .
               "4. SAD (Systems Analysis and Design)\n\n" .
               "Check course_data.txt for details.";
    }
    
    return "I can help with course information from course_data.txt. Try asking:\n\n" .
           "• What courses are available?\n" .
           "• How much do courses cost?\n" .
           "• Tell me about [course name]\n" .
           "• What are the cheapest/most expensive courses?\n\n" .
           "(Ollama is currently unavailable)";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Course Assistant - Student Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .navbar {
            background: white;
            padding: 15px 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .navbar h2 {
            color: #667eea;
            font-size: 20px;
        }
        
        .navbar a {
            color: #667eea;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 20px;
            transition: all 0.3s;
        }
        
        .navbar a:hover {
            background: #667eea;
            color: white;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 1000px;
            margin: 0 auto;
            height: 90vh; /* Updated height for chat container */
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        #chat {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            background: #f8f9fa;
            min-height: 400px; /* Add this to ensure minimum height */
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
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
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
            display: flex;
            gap: 12px;
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
        
        button {
            padding: 16px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        button:active {
            transform: translateY(0);
        }
        
        button:disabled {
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
        
        .loading:nth-child(2) { animation-delay: 0.2s; }
        .loading:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes pulse {
            0%, 100% { opacity: 0.3; transform: scale(0.8); }
            50% { opacity: 1; transform: scale(1.2); }
        }
        
        .info-box {
            background: linear-gradient(135deg, #e3f2fd 0%, #e1bee7 100%);
            border-left: 4px solid #667eea;
            border-radius: 10px;
            padding: 15px;
            margin: 10px 25px;
            font-size: 14px;
            color: #333;
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
        }
        
        .data-info i {
            color: #28a745;
        }
        
        .user-info {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-right: 20px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            font-size: 16px;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Larger chat container */
        .message-content {
            font-size: 15px;
            line-height: 1.6;
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
    </style>
</head>
<body>
    <div class="navbar">
        <h2><i class="fas fa-robot"></i> AI Course Assistant</h2>
        <div>
            <div class="user-info">
                <span style="color: #666; font-weight: 500;"><?php echo htmlspecialchars($student_name); ?></span>
                <div class="user-avatar">
                    <?php if (!empty($userAvatar) && file_exists($userAvatar)): ?>
                        <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar">
                    <?php else: ?>
                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
            </div>
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

    <div class="container" style="height: 90vh;">
        <div class="header">
            <h1><i class="fas fa-brain"></i> AI Course Assistant</h1>
            <p>Powered by Text-Based Knowledge & Ollama</p>
        </div>
        
        <div class="data-info">
            <i class="fas fa-database"></i>
            <span>Knowledge source: <strong>course_data.txt</strong> - All answers are based on this file</span>
        </div>
        
        <div class="info-box">
            <strong><i class="fas fa-info-circle"></i> I can answer these questions accurately:</strong><br>
            • What courses are available?<br>
            • Course prices and cost comparisons<br>
            • Cheapest and most expensive courses<br>
            • Specific course details (Data Administration, RIPH, etc.)
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
        </div>
        
        <div id="chat"></div>
        
        <div class="input-area">
            <input 
                id="input" 
                type="text" 
                placeholder="Ask about courses (pricing, availability, categories)..." 
                onkeypress="if(event.key==='Enter') sendMessage()"
            >
            <button id="sendBtn" onclick="sendMessage()">
                <i class="fas fa-paper-plane"></i> Send
            </button>
        </div>
    </div>

    <script>
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
            
            // Create loading message
            const reply_id = "reply_" + Date.now();
            const loadingMsg = document.createElement('div');
            loadingMsg.id = reply_id;
            loadingMsg.className = 'message bot-message';
            loadingMsg.innerHTML = '<div class="message-label">AI Assistant</div><span class="loading"></span><span class="loading"></span><span class="loading"></span> Processing...';
            chat.appendChild(loadingMsg);
            chat.scrollTop = chat.scrollHeight;
            
            // Send to backend
            fetch("ai_chatbot.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "prompt=" + encodeURIComponent(text)
            })
            .then(res => res.text())
            .then(reply => {
                const msgElement = document.getElementById(reply_id);
                
                if (reply.includes('ERROR:') || reply.includes('⚠️')) {
                    msgElement.className = 'message error-message';
                    msgElement.innerHTML = '<div class="message-label"><i class="fas fa-exclamation-triangle"></i> Error</div>' + formatText(reply);
                } else {
                    msgElement.innerHTML = '<div class="message-label">AI Assistant</div>' + formatText(reply);
                }
                
                chat.scrollTop = chat.scrollHeight;
                isProcessing = false;
                sendBtn.disabled = false;
                sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send';
                input.focus();
            })
            .catch(error => {
                const msgElement = document.getElementById(reply_id);
                msgElement.className = 'message error-message';
                msgElement.innerHTML = 
                    '<div class="message-label"><i class="fas fa-exclamation-triangle"></i> Network Error</div>Could not connect to server.';
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
        
        // Welcome message
        window.onload = function() {
            const chat = document.getElementById("chat");
            const welcomeMsg = document.createElement('div');
            welcomeMsg.className = 'message bot-message';
            welcomeMsg.innerHTML = '<div class="message-label">AI Assistant</div>' +
                '<i class="fas fa-hand-wave" style="color: #667eea;"></i> Hello <strong>' + 
                escapeHtml("<?php echo htmlspecialchars($_SESSION['first_name']); ?>") + 
                '</strong>!<br><br>' +
                'I\'m your AI Course Assistant. I answer questions based on the information in <strong>course_data.txt</strong>.<br><br>' +
                '<strong>You can ask me about:</strong><br>' +
                '• Available courses<br>' +
                '• Course prices<br>' +
                '• Cheapest and most expensive courses<br>' +
                '• Courses by category<br>' +
                '• Specific course details';
            chat.appendChild(welcomeMsg);
            chat.scrollTop = chat.scrollHeight;
        };
    </script>
</body>
</html>