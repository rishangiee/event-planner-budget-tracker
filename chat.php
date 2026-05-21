<?php
require_once 'config/config.php';
requireUser();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Handle send message
$sendError = '';
$sendSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_message') {
    $message = trim($_POST['message'] ?? '');
    
    if (empty($message)) {
        $sendError = 'Please enter a message';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (user_id, message, sender_type) VALUES (?, ?, 'user')");
            $stmt->execute([$userId, $message]);
            $sendSuccess = true;
        } catch (PDOException $e) {
            $sendError = 'Failed to send message';
        }
    }
}

// Get chat messages
try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               CASE WHEN m.sender_type = 'admin' THEN 'Admin' ELSE u.name END as sender_name
        FROM messages m 
        LEFT JOIN users u ON m.user_id = u.id 
        WHERE m.user_id = ? OR m.sender_type = 'admin'
        ORDER BY m.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$userId]);
    $messages = $stmt->fetchAll();
} catch (PDOException $e) {
    $messages = [];
}

// Reverse to show oldest first
$messages = array_reverse($messages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Admin - CAVENDIA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f6f7f4; }
        .sidebar-item {
            transition: all 0.2s ease;
            border-radius: 0 12px 12px 0;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            margin-bottom: 4px;
            font-size: 0.9rem;
        }
        .sidebar-item:hover { background-color: rgba(255,255,255,0.25); }
        .sidebar-item.active {
            background-color: rgba(26,71,42,0.85);
            border-left: 3px solid #1a472a;
            color: #ffffff;
            font-weight: 600;
        }
        .sidebar-item i { width: 20px; text-align: center; }
        
        .chat-container {
            height: calc(100vh - 200px);
            min-height: 400px;
        }
        .message-list {
            height: calc(100% - 70px);
            overflow-y: auto;
        }
        .message-bubble {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 18px;
            margin-bottom: 12px;
            position: relative;
        }
        .message-bubble.user {
            background: linear-gradient(135deg, #1a472a, #2d6a4f);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }
        .message-bubble.admin {
            background: white;
            color: #353f2d;
            border: 1px solid #e8ebe3;
            border-bottom-left-radius: 4px;
        }
        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 4px;
        }
        .chat-input-wrapper {
            position: relative;
        }
        .chat-input {
            width: 100%;
            padding: 14px 50px 14px 16px;
            border: 2px solid #e3ebe0;
            border-radius: 24px;
            font-size: 0.95rem;
            outline: none;
            transition: all 0.3s ease;
        }
        .chat-input:focus {
            border-color: #7aa370;
            box-shadow: 0 0 0 4px rgba(122,163,112,0.15);
        }
        .send-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1a472a;
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .send-btn:hover {
            background: #2d6a4f;
            transform: translateY(-50%) scale(1.05);
        }
        .empty-chat {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #8b9a7a;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Top Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 shadow-sm" style="background: linear-gradient(135deg, #3d5a40, #4a6b50);">
        <div class="flex items-center justify-between px-6 py-4">
            <a href="dashboard.php" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background: linear-gradient(135deg, #1a472a, #2d6a4f);">
                    <i class="fas fa-calendar-alt text-white text-lg"></i>
                </div>
                <span class="text-xl font-bold tracking-wide text-white">CAVENDIA</span>
            </a>
            <a href="index.php" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-xl font-semibold text-sm transition-colors flex items-center gap-2 shadow-sm">
                <i class="fas fa-sign-out-alt text-xs"></i> Logout
            </a>
        </div>
    </nav>

    <div class="flex pt-16 min-h-screen">
        <!-- Sidebar -->
        <aside class="fixed left-0 top-16 bottom-0 w-64 overflow-y-auto shadow-lg" style="background: linear-gradient(180deg, #d8f3dc 0%, #c8e6c8 100%);">
            <div class="p-6 pt-8">
                <div class="mb-8 px-2">
                    <span class="text-xs font-bold uppercase tracking-wider block mb-2" style="color:#1a472a;">Event planner User Portal</span>
                </div>
                <nav class="space-y-2">
                    <a href="dashboard.php" class="sidebar-item">
                        <i class="fas fa-th-large"></i><span>Dashboard</span>
                    </a>

                    <a href="calendar.php" class="sidebar-item">
                        <i class="fas fa-calendar"></i><span>Calendar</span>
                    </a>
                    <a href="booking.php" class="sidebar-item">
                        <i class="fas fa-bookmark"></i><span>My Bookings</span>
                    </a>
                    <a href="profile.php" class="sidebar-item">
                        <i class="fas fa-user"></i><span>Profile</span>
                    </a>
                    <a href="chat.php" class="sidebar-item active">
                        <i class="fas fa-comments"></i><span>Chat</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8 min-h-screen" style="margin-left:16rem; background:#f6f7f4;">
            
            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold" style="color:#1a472a;">Chat with Admin</h2>
                        <p class="text-sm mt-1" style="color:#62744f;">Send messages and get support</p>
                    </div>
                </div>
            </div>

            <!-- Chat Card -->
            <div class="card rounded-2xl overflow-hidden" style="background:white;">
                <!-- Chat Header -->
                <div class="p-4 border-b" style="border-color:#e8ebe3;">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background:#dcfce7;">
                            <i class="fas fa-headset" style="color:#16a34a;"></i>
                        </div>
                        <div>
                            <h3 class="font-bold" style="color:#1a472a;">CAVENDIA Support</h3>
                            <p class="text-xs" style="color:#16a34a;">● Online</p>
                        </div>
                    </div>
                </div>
                
                <!-- Message List -->
            <div class="chat-container p-4" style="background:#faf9f6;">
                    <div class="message-list" id="messageList">
                        <?php if (empty($messages)): ?>
                        <div class="empty-chat">
                            <i class="fas fa-comments text-4xl mb-4" style="color:#c5d8bf;"></i>
                            <p class="font-semibold mb-1">No messages yet</p>
                            <p class="text-sm">Start a conversation with our support team</p>
                        <?php else: ?>
                            <?php foreach ($messages as $msg): ?>
                            <div class="message-bubble <?php echo $msg['sender_type'] === 'user' ? 'user' : 'admin'; ?>">
                                <div><?php echo htmlspecialchars($msg['message']); ?></div>
                                
                                <div class="message-time">
                                    <?php echo $msg['sender_name']; ?> • <?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?>
                                </div>

                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Message Input -->
                <form method="POST" class="p-4 border-t" style="border-color:#e8ebe3;">
                    <input type="hidden" name="action" value="send_message">
                    <div class="chat-input-wrapper">
                        <input type="text" name="message" class="chat-input" placeholder="Type your message..." autocomplete="off" required>
                        <button type="submit" class="send-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
            </div>

        </main>
    </div>

    <script>
    // Auto-scroll to bottom
        const msgList = document.getElementById('messageList');
        if (msgList) {
            msgList.scrollTop = msgList.scrollHeight;
        }

        // Keep track of last rendered message to simulate real-time updates (polling)
        let lastRenderedSignature = null;
        function getLastSignature() {
            const bubbles = document.querySelectorAll('#messageList .message-bubble');
            if (!bubbles.length) return null;
            const last = bubbles[bubbles.length - 1];
            // Signature from text content (safe enough for polling)
            return last.innerText.trim().slice(-300);
        }
        lastRenderedSignature = getLastSignature();

        async function fetchNewMessages() {
            const res = await fetch('api.php?type=chat', { method: 'GET' });
            if (!res.ok) return;
            const data = await res.json();
            if (!data.success || !Array.isArray(data.messages)) return;

            // data.messages comes with latest first; convert to oldest first
            const messages = data.messages.slice().reverse();
            const container = document.getElementById('messageList');
            if (!container) return;

            // Compare using first/last message for minimal DOM churn
            const newFirst = messages[0] ? (messages[0].sender_type + '|' + (messages[0].message || '').slice(0, 80) + '|' + messages[0].created_at) : null;
            const currentFirstEl = container.querySelector('.message-bubble');
            const currentFirstSig = currentFirstEl ? currentFirstEl.innerText.trim().slice(0, 120) : null;

            // If there is any difference, rerender fully (simple + reliable)
            const shouldRerender = currentFirstSig !== null ? (newFirst && !currentFirstSig.includes((messages[0].message || '').slice(0, 20))) : true;
            if (!shouldRerender) return;

            container.innerHTML = '';

            if (!messages.length) {
                const empty = document.createElement('div');
                empty.className = 'empty-chat';
                empty.innerHTML = '<i class="fas fa-comments text-4xl mb-4" style="color:#c5d8bf;"></i><p class="font-semibold mb-1">No messages yet</p><p class="text-sm">Start a conversation with our support team</p>';
                container.appendChild(empty);
                return;
            }

            messages.forEach(msg => {
                const bubbleType = (msg.sender_type === 'user') ? 'user' : 'admin';
                const bubble = document.createElement('div');
                bubble.className = 'message-bubble ' + bubbleType;

                const messageText = msg.message ? String(msg.message) : '';
                const senderName = msg.user_name ? String(msg.user_name) : (bubbleType === 'user' ? 'User' : 'Admin');
                const createdAt = msg.created_at ? String(msg.created_at) : '';

                bubble.innerHTML = `
                    <div>${messageText.replace(/</g,'<').replace(/>/g,'>')}</div>
                    <div class="message-time">${senderName} &bull; ${createdAt}</div>
                `;
                container.appendChild(bubble);
            });

            if (msgList) {
                msgList.scrollTop = msgList.scrollHeight;
            }
            lastRenderedSignature = getLastSignature();
        }

        // Poll every 2 seconds for new messages
        setInterval(() => {
            // avoid hammering while user is typing (best-effort)
            const active = document.activeElement;
            if (active && active.name === 'message') return;
            fetchNewMessages().catch(() => {});
        }, 2000);

        // Show success message
        <?php if ($sendSuccess): ?>
        alert('Message sent successfully!');
        location.reload();
        <?php endif; ?>
        
        // Show error
        <?php if ($sendError): ?>
        alert('<?php echo addslashes($sendError); ?>');
        <?php endif; ?>
    </script>
</body>
</html>
