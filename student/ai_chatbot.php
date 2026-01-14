<?php
session_start();
require_once '../database/db_connect.php';

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: ../index.php');
    exit();
}

$userID = $_SESSION['user_id'];

// ============================================
// BACKEND: Handle POST requests to Ollama
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['prompt'])) {
    // Fetch course data from database - using actual columns from your database
    $stmt = $conn->prepare("
        SELECT courseID, title, description, price, category, status, passingScore, createdAt
        FROM courses 
        WHERE status = 'published'
        ORDER BY title
    ");
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$result || count($result) === 0) {
        echo "No courses available at the moment.";
        exit;
    }
    
    // Build categories list
    $categories = [];
    foreach ($result as $course) {
        if (!in_array($course['category'], $categories)) {
            $categories[] = $course['category'];
        }
    }
    
    // Build enhanced knowledge base from database
    $knowledge_base = "AVAILABLE COURSES INFORMATION:\n\n";
    $knowledge_base .= "CATEGORIES AVAILABLE: " . implode(', ', $categories) . "\n\n";
    
    foreach ($result as $course) {
        $knowledge_base .= "=== COURSE: " . $course['title'] . " ===\n";
        $knowledge_base .= "Category: " . $course['category'] . "\n";
        $knowledge_base .= "Description: " . ($course['description'] ?: "A comprehensive course in " . $course['category']) . "\n";
        $knowledge_base .= "Price: ₱" . number_format($course['price'], 2) . "\n";
        $knowledge_base .= "Passing Score: " . $course['passingScore'] . "%\n";
        $knowledge_base .= "Status: " . ucfirst($course['status']) . "\n\n";
    }
    
    // Get user question
    $user_question = trim($_POST['prompt']);
    
    if (empty($user_question)) {
        echo "Please enter a question.";
        exit;
    }
    
    // Check if question is about courses using comprehensive logic
    $course_keywords = [
        'course', 'courses', 'class', 'classes', 'subject', 'subjects',
        'price', 'cost', 'fee', 'fees', 'how much',
        'available', 'offer', 'offered', 'provide',
        'duration', 'how long', 'time', 'length',
        'category', 'categories', 'type', 'types',
        'description', 'detail', 'info', 'information',
        'passing', 'score', 'requirement', 'required',
        'programming', 'design', 'business', // Add your actual categories from database
        'what', 'which', 'tell me about', 'explain', 'show'
    ];
    
    $is_course_related = false;
    $user_question_lower = strtolower($user_question);
    
    foreach ($course_keywords as $keyword) {
        if (strpos($user_question_lower, $keyword) !== false) {
            $is_course_related = true;
            break;
        }
    }
    
    // Also check for broader patterns
    $course_patterns = [
        '/what.*course/i',
        '/which.*course/i',
        '/tell.*about.*course/i',
        '/do you have.*course/i',
        '/course.*available/i',
        '/how.*cost/i',
        '/how.*price/i',
        '/what.*price/i',
        '/course.*fee/i',
        '/show.*course/i',
        '/list.*course/i',
        '/recommend.*course/i',
        '/best.*course/i',
        '/cheap.*course/i',
        '/free.*course/i'
    ];
    
    if (!$is_course_related) {
        foreach ($course_patterns as $pattern) {
            if (preg_match($pattern, $user_question)) {
                $is_course_related = true;
                break;
            }
        }
    }
    
    // If still not course-related, provide a more helpful message
    if (!$is_course_related) {
        echo "I'm your course assistant! I can help you with information about our available courses, including:\n\n";
        echo "• Course titles and descriptions\n";
        echo "• Pricing information\n";
        echo "• Course categories\n";
        echo "• Passing score requirements\n";
        echo "• Available subjects\n\n";
        echo "Try asking something like:\n";
        echo "- 'What courses are available?'\n";
        echo "- 'How much does the Web Development course cost?'\n";
        echo "- 'Tell me about Programming courses'\n";
        echo "- 'Show me all courses'\n";
        echo "- 'What are the cheapest courses?'\n";
        exit;
    }
    
    // Build enhanced prompt for Ollama
    $prompt = <<<EOT
You are a helpful and friendly course assistant for an online learning platform called LMS Learnexus.
Use ONLY the course information provided below to answer questions.

IMPORTANT GUIDELINES:
1. Be concise, friendly, and helpful
2. Format prices with PHP peso sign (₱)
3. If listing multiple courses, use bullet points or clear formatting
4. If the user asks about something not in the course data, politely say: "I don't have specific information about that in our course database. I can only help with questions about our available courses, their prices, categories, and requirements."
5. When comparing prices or listing courses, organize the information clearly
6. Always mention the course category when relevant

--- COURSE DATA ---
$knowledge_base
--- END COURSE DATA ---

Question: $user_question

Answer (be helpful, use only the provided data, and format clearly):
EOT;
    
    // Prepare request to Ollama API
    $payload = json_encode([
        'model' => 'gemma3:270m',
        'prompt' => $prompt,
        'stream' => false,
        'options' => [
            'temperature' => 0.3,
            'num_predict' => 200
        ]
    ]);
    
    // Debug logging (optional)
    // error_log("User question: " . $user_question);
    // error_log("Knowledge base: " . substr($knowledge_base, 0, 500));
    
    // Send request to Ollama
    $ch = curl_init('http://127.0.0.1:11434/api/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        echo "ERROR: Could not connect to Ollama. Make sure Ollama is running. Error: " . $error;
        exit;
    }
    
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        echo "ERROR: Ollama returned error code " . $http_code;
        exit;
    }
    
    $result = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "ERROR: Could not parse Ollama response.";
        exit;
    }
    
    $reply = $result['response'] ?? '';
    
    if (empty($reply)) {
        echo "ERROR: Ollama returned empty response.";
        exit;
    }
    
    echo htmlspecialchars($reply);
    exit;
}

$student_name = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Get user avatar
$stmt = $conn->prepare("SELECT avatar FROM users WHERE userID = ?");
$stmt->execute([$userID]);
$userAvatar = $stmt->fetchColumn();
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
            max-width: 900px;
            margin: 0 auto;
            height: 600px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 26px;
            margin-bottom: 8px;
        }
        
        .header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        #chat {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8f9fa;
        }
        
        .message {
            margin-bottom: 15px;
            padding: 14px 18px;
            border-radius: 15px;
            max-width: 75%;
            word-wrap: break-word;
            animation: fadeIn 0.3s ease-in;
            line-height: 1.5;
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
        }
        
        .error-message {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef5350;
        }
        
        .message-label {
            font-weight: bold;
            font-size: 11px;
            margin-bottom: 6px;
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
            padding: 14px 20px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 14px;
            outline: none;
            transition: border-color 0.3s;
        }
        
        #input:focus {
            border-color: #667eea;
        }
        
        button {
            padding: 14px 35px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
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
            width: 8px;
            height: 8px;
            margin: 0 2px;
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
            margin: 15px 20px;
            font-size: 13px;
            color: #333;
        }
        
        .info-box strong {
            color: #667eea;
        }
        
        .suggestions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            padding: 0 20px 15px;
        }
        
        .suggestion-chip {
            background: #f0f0f0;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #e0e0e0;
        }
        
        .suggestion-chip:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2><i class="fas fa-robot"></i> AI Course Assistant</h2>
        <div>
            <div class="user-info" style="display: inline-flex; align-items: center; gap: 10px; margin-right: 20px;">
                <span style="color: #666; font-weight: 500;"><?php echo htmlspecialchars($student_name); ?></span>
                <div style="width: 35px; height: 35px; border-radius: 50%; overflow: hidden; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600;">
                    <?php if (!empty($userAvatar) && file_exists($userAvatar)): ?>
                        <img src="<?php echo htmlspecialchars($userAvatar); ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <?php echo strtoupper(substr($_SESSION['first_name'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
            </div>
            <a href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <h1><i class="fas fa-brain"></i> AI Course Assistant</h1>
            <p>Powered by Ollama Gemma 3 - Ask me about our courses!</p>
        </div>
        
        <div class="info-box">
            <strong><i class="fas fa-info-circle"></i> What I can help with:</strong><br>
            • Available courses and categories<br>
            • Course prices and fees<br>
            • Course descriptions and requirements<br>
            • Passing score requirements<br>
            • Comparing course options
        </div>
        
        <div class="suggestions">
            <div class="suggestion-chip" onclick="askQuestion('What courses are available?')">
                What courses are available?
            </div>
            <div class="suggestion-chip" onclick="askQuestion('How much do the courses cost?')">
                How much do courses cost?
            </div>
            <div class="suggestion-chip" onclick="askQuestion('Show me all courses')">
                Show me all courses
            </div>
            <div class="suggestion-chip" onclick="askQuestion('What are the cheapest courses?')">
                Cheapest courses?
            </div>
            <div class="suggestion-chip" onclick="askQuestion('Tell me about Programming courses')">
                Programming courses?
            </div>
            <div class="suggestion-chip" onclick="askQuestion('List courses by category')">
                Courses by category
            </div>
        </div>
        
        <div id="chat"></div>
        
        <div class="input-area">
            <input 
                id="input" 
                type="text" 
                placeholder="Ask about our courses..." 
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
            loadingMsg.innerHTML = '<div class="message-label">AI Assistant</div><span class="loading"></span><span class="loading"></span><span class="loading"></span> Thinking...';
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
                
                if (reply.includes('ERROR:')) {
                    msgElement.className = 'message error-message';
                    msgElement.innerHTML = '<div class="message-label"><i class="fas fa-exclamation-triangle"></i> Error</div>' + escapeHtml(reply);
                } else {
                    // Format the response with better line breaks
                    const formattedReply = escapeHtml(reply).replace(/\n/g, '<br>');
                    msgElement.innerHTML = '<div class="message-label">AI Assistant</div>' + formattedReply;
                }
                
                chat.scrollTop = chat.scrollHeight;
                isProcessing = false;
                sendBtn.disabled = false;
                input.focus();
            })
            .catch(error => {
                const msgElement = document.getElementById(reply_id);
                msgElement.className = 'message error-message';
                msgElement.innerHTML = 
                    '<div class="message-label"><i class="fas fa-exclamation-triangle"></i> Network Error</div>Could not connect to server: ' + error.message;
                chat.scrollTop = chat.scrollHeight;
                isProcessing = false;
                sendBtn.disabled = false;
            });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Welcome message
        window.onload = function() {
            const chat = document.getElementById("chat");
            const welcomeMsg = document.createElement('div');
            welcomeMsg.className = 'message bot-message';
            welcomeMsg.innerHTML = '<div class="message-label">AI Assistant</div>' +
                '<i class="fas fa-hand-wave" style="color: #667eea;"></i> Hello <strong>' + 
                escapeHtml("<?php echo htmlspecialchars($_SESSION['first_name']); ?>") + 
                '</strong>! I\'m your AI course assistant. ' +
                'I can help you find information about our available courses, their prices, categories, and requirements. ' +
                'What would you like to know about our courses?';
            chat.appendChild(welcomeMsg);
        };
    </script>
</body>
</html>