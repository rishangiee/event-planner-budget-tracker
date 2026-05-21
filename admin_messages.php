<?php
/**
 * Admin Messages / Reply Center
 * - Centralized reply center for all user concerns/inquiries/bookings/messages
 */
// Load DB + auth from the same project root as this file
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/auth.php';

// Fallbacks: in case the wrong auth/config was used previously
if (!function_exists('guardAdmin') && file_exists(__DIR__ . '/Event_planner/auth.php')) {
    require_once __DIR__ . '/Event_planner/auth.php';
}

if (!isset($pdo) && file_exists(__DIR__ . '/config/config.php')) {
    // config/config.php already required above; this is just defensive
}


guardAdmin();

$userName = $_SESSION['user_name'] ?? 'Admin';
$flash = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

// Conversation / Threading for Admin (needed for POST reply too)
$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Build user list from all messages, so the Users card shows every user who ever chatted.
$users = [];
try {
    $stmt = $pdo->query(
        "SELECT DISTINCT 
                COALESCE(m.sender_id, m.user_id) AS user_id,
                u.name AS user_name,
                u.email AS user_email
         FROM messages m
         LEFT JOIN users u ON u.id = COALESCE(m.sender_id, m.user_id)
         WHERE (m.sender_type = 'user' AND COALESCE(m.sender_id, m.user_id) IS NOT NULL)
            OR (m.sender_type = 'admin' AND COALESCE(m.sender_id, m.user_id) IS NOT NULL)
         ORDER BY u.name ASC"
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

// If no user selected, default to the first user in the list.
if ($selectedUserId <= 0 && !empty($users)) {
    $selectedUserId = (int)($users[0]['user_id'] ?? 0);
}

// Handle admin reply send
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';
    if (function_exists('validateCsrfToken') && !validateCsrfToken($csrfToken)) {
        $_SESSION['flash_message'] = 'Security token mismatch. Please try again.';
        header('Location: admin_messages.php');
        exit;
    }

    $message = trim($_POST['message'] ?? '');

    if ($message === '' || strlen($message) > 1000) {
        $_SESSION['flash_message'] = 'Please enter a valid message (max 1000 chars).';
        header('Location: admin_messages.php');
        exit;
    }

    $senderType = 'admin';

    // Reply should belong to the currently selected user conversation.
    // We use sender_id = user_id of the conversation partner.
    $replyToUserId = isset($_POST['reply_to_user_id']) ? (int)$_POST['reply_to_user_id'] : (int)$selectedUserId;
    if ($replyToUserId <= 0) {
        $_SESSION['flash_message'] = 'Select a user conversation before replying.';
        header('Location: admin_messages.php');
        exit;
    }

    $senderId = $replyToUserId;

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO messages (user_id, message, sender_type, sender_id, created_at)
             VALUES (?, ?, ?, ?, NOW())"
        );
        // For admin replies, keep user_id NULL and rely on sender_id for threading.
        $stmt->execute([
            null,
            $message,
            $senderType,
            $senderId,
        ]);


        $_SESSION['flash_message'] = 'Reply sent successfully.';
        header('Location: admin_messages.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = 'Failed to send reply: ' . $e->getMessage();
        header('Location: admin_messages.php');
        exit;
    }
}

// ---- Conversation / Threading for Admin ----
// We keep messages table as-is, but we group by (admin <-> user) using:
// - user messages: sender_type='user' and sender_id=<user_id>
// - admin messages: sender_type='admin' and sender_id=<user_id they replied to>
// This prevents a single global chat.

$selectedUserId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// Build user list from all messages, so the Users card shows every user who ever chatted.
$users = [];
try {
    $stmt = $pdo->query(
        "SELECT DISTINCT 
                COALESCE(m.sender_id, m.user_id) AS user_id,
                u.name AS user_name,
                u.email AS user_email
         FROM messages m
         LEFT JOIN users u ON u.id = COALESCE(m.sender_id, m.user_id)
         WHERE (m.sender_type = 'user' AND COALESCE(m.sender_id, m.user_id) IS NOT NULL)
            OR (m.sender_type = 'admin' AND COALESCE(m.sender_id, m.user_id) IS NOT NULL)
         ORDER BY u.name ASC"
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
}

// If no user selected, default to the first user in the list.
if ($selectedUserId <= 0 && !empty($users)) {
    $selectedUserId = (int)($users[0]['user_id'] ?? 0);
}

// Load messages for the selected conversation only.
$messages = [];
if ($selectedUserId > 0) {
    try {
        $stmt = $pdo->prepare(
            "SELECT m.*, 
                    u.name as user_name,
                    u.email as user_email,
                    COALESCE(m.sender_id, m.user_id) as convo_user_id
             FROM messages m
             LEFT JOIN users u ON u.id = COALESCE(m.sender_id, m.user_id)
             WHERE (m.sender_type = 'user' AND COALESCE(m.sender_id, m.user_id) = ?)
                OR (m.sender_type = 'admin' AND COALESCE(m.sender_id, m.user_id) = ?)
             ORDER BY m.created_at ASC
             LIMIT 500"
        );
        $stmt->execute([$selectedUserId, $selectedUserId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $messages = [];
    }
}


// CSRF
$csrfToken = function_exists('generateCsrfToken') ? generateCsrfToken() : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Messages - CAVENDIA</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root{
            --sage:#A3B18A;
            --sage-dark:#8A9A6D;
            --sage-light:#D4D9CB;
            --cream:#F1F2EE;
            --forest:#1B4332;
            --forest-light:#2C5F41;
            --white:#FFFFFF;
            --text-muted:#6B7C6D;
            --text-dark:#1B4332;
            --border:#D8DDD3;
            --shadow:0 8px 32px rgba(27,67,50,0.12);
            --shadow-hover:0 20px 40px rgba(27,67,50,0.2);
            --blue:#1D4ED8;
            --blue-soft:rgba(29,78,216,0.15);
            --success:#10B981;
            --danger:#EF4444;
        }

        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:linear-gradient(135deg,var(--cream) 0%, #e8ebe3 100%);color:var(--forest);line-height:1.6;}

        /* Top app chrome */
        .sidebar{position:fixed;top:0;left:0;width:280px;height:100vh;background:var(--sage);z-index:100;transition:transform .3s ease;padding-top:5rem;overflow-y:auto;}
        .sidebar-header{padding:2rem 2rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.1);margin-bottom:1.5rem;}
        .sidebar-title{display:flex;align-items:center;gap:.75rem;margin-bottom:.25rem;}
        .sidebar-icon{width:2.5rem;height:2.5rem;background:rgba(255,255,255,0.2);border-radius:.75rem;display:flex;align-items:center;justify-content:center;}
        .sidebar-title h2{font-size:1.5rem;font-weight:700;color:var(--white);} 
        .sidebar-subtitle{font-size:.875rem;color:rgba(255,255,255,0.8);font-weight:400;}
        .sidebar-nav{padding:0 2rem 2rem;}
        .sidebar-item{display:flex;align-items:center;gap:1rem;padding:.875rem 1rem;color:rgba(255,255,255,0.8);text-decoration:none;border-radius:1rem;margin-bottom:.5rem;font-weight:500;transition:all .2s ease;font-size:.95rem;}
        .sidebar-item:hover{background:rgba(255,255,255,0.15);color:var(--white);transform:translateX(.25rem);} 
        .sidebar-item.active{background:rgba(255,255,255,0.25);color:var(--white);border-left:4px solid var(--white);font-weight:600;}
        .sidebar-item i{width:1.25rem;font-size:1.1rem;}

        .mobile-toggle{position:fixed;top:1.5rem;left:1.5rem;z-index:200;background:var(--sage);border:none;padding:1rem;border-radius:1rem;color:var(--white);font-size:1.25rem;cursor:pointer;box-shadow:var(--shadow);transition:all .3s ease;display:none;}
        .mobile-toggle:hover{background:var(--forest);transform:scale(1.05);} 
        .overlay{position:fixed;inset:0;background:rgba(27,67,50,0.45);backdrop-filter:none;z-index:150;opacity:0;visibility:hidden;transition:all .3s ease;}
        .overlay.active{opacity:1;visibility:visible;}

        .main-content{margin-left:280px;transition:margin-left .3s ease;padding:3rem 2rem 2rem;min-height:100vh;}

        .page-header{margin-bottom:1.25rem;text-align:left;}
        .page-header h1{font-size:2.35rem;font-weight:900;color:var(--forest);letter-spacing:0.01em;}
        .page-header p{margin-top:.45rem;font-size:1.0rem;color:var(--text-muted);font-weight:500;}

        /* Main two-column */
        .messages-layout{display:grid;grid-template-columns:340px 1fr;gap:1.5rem;align-items:start;}
        @media (max-width: 1024px){
            .messages-layout{grid-template-columns:1fr;}
        }

        .panel{background:var(--white);border:1px solid var(--border);box-shadow:var(--shadow);border-radius:18px;overflow:hidden;}
        .panel-head{padding:18px 18px 14px;border-bottom:1px solid var(--border);background:linear-gradient(135deg, rgba(163,177,138,0.12), rgba(241,242,238,0));}
        .panel-head-title{font-weight:700;color:var(--forest);font-size:1.1rem;}

        /* Users card */
        .users-card{padding:0;}
        .users-items{padding:12px;display:flex;flex-direction:column;gap:10px;max-height:70vh;overflow-y:auto;}
        
        .user-item{position:relative;background:#fff;border-radius:12px;border:1px solid var(--border);padding:12px;display:flex;gap:10px;align-items:center;cursor:pointer;transition:all .2s ease;}
        .user-item:hover{background:var(--cream);border-color:var(--sage-dark);}
        
        .user-item .avatar{width:40px;height:40px;border-radius:50%;background:var(--sage-light);color:var(--forest);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;}
        .user-item .meta{min-width:0;flex:1;overflow:hidden;}
        .user-item .name{font-weight:600;color:var(--forest);font-size:.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .user-item .email{font-size:.8rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        
        .user-item.active{background:linear-gradient(135deg, var(--sage-light), var(--cream));border-color:var(--sage-dark);box-shadow:0 2px 8px rgba(163,177,138,0.3);}
        .user-item.active::before{content:'';position:absolute;left:0;top:50%;transform:translateY(-50%);width:3px;height:70%;background:var(--forest);border-radius:0 3px 3px 0;}
        
        .users-card-title{padding:18px 18px 14px;border-bottom:1px solid var(--border);}
        .users-title-row{font-weight:700;letter-spacing:0.02em;color:var(--forest);font-size:1.1rem;}

        /* CHAT PANEL - FIXED HEIGHT FOR SCROLLING */
        .chat-panel {
            display: flex;
            flex-direction: column;
            height: 600px; /* Fixed height for scrollable area */
        }
        
        /* Scrollable messages area */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            background-color: #F8FAF9;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .chat-messages::-webkit-scrollbar {
            width: 6px;
        }
        
        .chat-messages::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .chat-messages::-webkit-scrollbar-thumb {
            background: var(--sage-light);
            border-radius: 3px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: var(--sage-dark);
        }

        .message-row {
            display: flex;
            flex-direction: column;
            max-width: 75%;
            animation: fadeIn 0.25s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-row.user-msg {
            align-self: flex-end;
            align-items: flex-end;
        }

        .message-row.admin-msg {
            align-self: flex-start;
            align-items: flex-start;
        }

        .chat-bubble {
            padding: 12px 16px;
            font-size: 0.925rem;
            line-height: 1.5;
            position: relative;
            word-wrap: break-word;
            border-radius: 16px;
        }

        /* User message style - sage light */
        .message-row.user-msg .chat-bubble {
            background: var(--sage-light);
            color: var(--forest);
            border-bottom-right-radius: 4px;
        }

        /* Admin message style - white with border */
        .message-row.admin-msg .chat-bubble {
            background: var(--white);
            color: var(--forest);
            border: 1px solid var(--border);
            border-bottom-left-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .message-meta {
            font-size: 0.7rem;
            color: var(--text-muted);
            margin-top: 4px;
            padding: 0 4px;
            display: flex;
            gap: 6px;
            align-items: center;
        }

        .message-row.user-msg .message-meta {
            justify-content: flex-end;
        }

        /* Reply panel */
        .reply-bottom {
            padding: 16px 18px 18px;
            border-top: 1px solid var(--border);
            background: var(--white);
            border-bottom-right-radius: 18px;
            border-bottom-left-radius: 18px;
        }
        
        .reply-label {
            font-weight: 600;
            color: var(--forest);
            font-size: .95rem;
            margin-bottom: 10px;
        }
        
        .reply-textarea {
            width: 100%;
            min-height: 80px;
            resize: none;
            border-radius: 12px;
            border: 2px solid var(--border);
            background: var(--cream);
            padding: 12px 14px;
            font-size: .95rem;
            font-family: inherit;
            color: var(--forest);
            outline: none;
            transition: all .2s ease;
            line-height: 1.5;
        }
        
        .reply-textarea::placeholder {
            color: rgba(107,124,109,0.7);
        }
        
        .reply-textarea:focus {
            border-color: var(--sage-dark);
            box-shadow: 0 0 0 3px rgba(163,177,138,0.15);
            background: var(--white);
        }

.reply-input-row {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }

        .btn-send-reply {
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--forest);
            color: var(--white);
            border: none;
            width: 44px;
            height: 44px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all .2s ease;
            flex-shrink: 0;
            margin-top: 36px; /* vertically align near textarea center */
        }

        .btn-send-reply:hover {
            background: var(--forest-light);
            transform: translateY(-1px);
        }

        .btn-send-reply:active {
            transform: translateY(0);
        }

        .reply-disclaimer {
            margin-top: 10px;
            color: var(--text-muted);
            font-size: .8rem;
        }

        /* Flash message */
        .top-small-flash {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 60;
            background: var(--white);
            border: 2px solid var(--success);
            padding: 12px 20px;
            border-radius: 12px;
            color: var(--forest);
            font-size: 14px;
            font-weight: 500;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown .3s ease;
        }
        
        @keyframes slideDown { 
            from { opacity: 0; transform:translate(-50%, -20px); } 
            to { opacity: 1; transform:translate(-50%, 0); } 
        }
        
        .top-small-flash i {
            color: var(--success);
        }

        @media (max-width: 768px){
            .mobile-toggle{display:block;}
            .sidebar{transform:translateX(-100%);} 
            .sidebar.open{transform:translateX(0);} 
            .main-content{margin-left:0;}
            .messages-layout{grid-template-columns:1fr;}
            .chat-panel { height: 500px; }
        }
    </style>
</head>
<body>

<button class="mobile-toggle" id="mobileToggle">
    <i class="fas fa-bars"></i>
</button>
<div class="overlay" id="overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-title">
            <div class="sidebar-icon"><i class="fas fa-crown"></i></div>
            <h2>Admin Portal</h2>
        </div>
        <p class="sidebar-subtitle">Welcome, <?= htmlspecialchars($userName) ?></p>
    </div>
        <nav class="sidebar-nav">
        <a href="admin_dashboard.php" class="sidebar-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
                <a href="admin_event.php" class="sidebar-item"><i class="fas fa-calendar-alt"></i><span>Manage Events</span></a>
        <a href="admin_users.php" class="sidebar-item"><i class="fas fa-users"></i><span>Manage Users</span></a>
        <a href="admin_bookings.php" class="sidebar-item"><i class="fas fa-clipboard-list"></i><span>Bookings</span></a>
        <a href="admin_messages.php" class="sidebar-item active"><i class="fas fa-comments"></i><span>Messages</span></a>
        <a href="admin_logout.php" class="sidebar-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>

<script>
// Prevent hidden overlays from capturing clicks on this page
(function(){
  const overlay = document.getElementById('chatWidgetOverlay');
  if (overlay) overlay.style.pointerEvents = 'none';
})();
</script>
</aside>

<main class="main-content" id="mainContent">

    <?php if ($flash): ?>
        <div class="top-small-flash">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($flash) ?></span>
        </div>
    <?php endif; ?>

    <div class="page-header">
        <h1>Admin Messages</h1>
        <p>Centralized reply center for all user inquiries</p>
    </div>

    <div class="messages-layout">
        <!-- Left: Users List -->
        <section class="panel users-card">
            <div class="users-card-title">
                <div class="users-title-row">Users</div>
            </div>

            <div class="users-items">
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $u):
                        $uid = (int)($u['user_id'] ?? 0);
                        if ($uid <= 0) continue;
                        $active = ($selectedUserId === $uid);
                        $displayName = $u['user_name'] ?: ($u['user_email'] ?? 'User');
                        $initial = strtoupper(substr(trim($u['user_name'] ?: ($u['user_email'] ?? 'U')), 0, 1));
                    ?>
                        <a class="user-item <?= $active ? 'active' : '' ?>" href="admin_messages.php?user_id=<?= $uid ?>" style="text-decoration:none;">
                            <div class="avatar"><?= htmlspecialchars($initial) ?></div>
                            <div class="meta">
                                <div class="name"><?= htmlspecialchars($u['user_name'] ?? '') ?></div>
                                <div class="email"><?= htmlspecialchars($u['user_email'] ?? '') ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding:24px;color:var(--text-muted);text-align:center;">
                        <i class="fas fa-user-friends" style="font-size:1.5rem;opacity:0.6;"></i>
                        <div style="margin-top:10px;font-weight:700;">No conversations yet</div>
                        <div style="font-size:12px;margin-top:4px;">When users message support, they will appear here.</div>
                    </div>
                <?php endif; ?>
            </div>

        </section>

        <!-- Right: Chat Panel (Scrollable) -->
        <section class="panel chat-panel">
            <div class="panel-head">
                    <div class="panel-head-title">
                        <i class="fas fa-user-circle" style="margin-right:8px;color:var(--sage-dark);"></i>
                    <?php
                        // Show the selected conversation partner name consistently.
                        // Header is rendered before the reply-form variables are computed,
                        // so we derive it directly from $users/$selectedUserId.
                        $chatTitle = 'User';
                        foreach (($users ?? []) as $uu) {
                            if ((int)($uu['user_id'] ?? 0) === (int)($selectedUserId ?? 0)) {
                                $chatTitle = (string)($uu['user_name'] ?? ($uu['user_email'] ?? 'User'));
                                break;
                            }
                        }
                        if ($chatTitle === 'User' && !empty($users)) {
                            $chatTitle = (string)($users[0]['user_name'] ?? ($users[0]['user_email'] ?? 'User'));
                        }
                    ?>
                    Chat <?= htmlspecialchars($chatTitle) ?>

                </div>
            </div>

            <!-- Scrollable Messages Area -->
            <div class="chat-messages" id="messageList">
                <!-- User Message 1 -->
                <div class="message-row user-msg">
                    <div class="chat-bubble">
                        Hi, I wanted to inquire about the upcoming charity gala event.
                    </div>
                    <div class="message-meta">
                        <span></span> • <span>Today, 10:30 AM</span>
                    </div>
                </div>

                <!-- Admin Reply 1 -->
                <div class="message-row admin-msg">
                    <div class="chat-bubble">
                        Hello ! Thank you for reaching out. The gala is scheduled for next month. Would you like more details?
                    </div>
                    <div class="message-meta">
                        <span>Admin</span> • <span>Today, 10:35 AM</span>
                    </div>
                </div>

                <!-- User Message 2 -->
                <div class="message-row user-msg">
                    <div class="chat-bubble">
                        Yes please! What is the dress code?
                    </div>
                    <div class="message-meta">
                        <span></span> • <span>Today, 10:38 AM</span>
                    </div>
                </div>

                <!-- Admin Reply 2 -->
                <div class="message-row admin-msg">
                    <div class="chat-bubble">
                        The dress code is formal black tie. We also have a themed costume contest if you're interested!
                    </div>
                    <div class="message-meta">
                        <span>Admin</span> • <span>Today, 10:42 AM</span>
                    </div>
                </div>

                <!-- User Message 3 -->
                <div class="message-row user-msg">
                    <div class="chat-bubble">
                        That sounds great! How can I purchase tickets?
                    </div>
                    <div class="message-meta">
                        <span></span> • <span>Today, 10:45 AM</span>
                    </div>
                </div>

                <!-- Database Messages Loop -->
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $m): 
                        $isAdmin = ($m['sender_type'] ?? '') === 'admin';
                        $senderName = $isAdmin ? 'Admin' : ($m['user_email'] ?? '');
                        $time = $m['created_at'] ? date('M j, g:i A', strtotime($m['created_at'])) : '';
                    ?>
                        <div class="message-row <?= $isAdmin ? 'user-msg' : 'admin-msg' ?>">
                            <div class="chat-bubble"><?= htmlspecialchars($m['message'] ?? '') ?></div>
                            <div class="message-meta">
                                <span><?= htmlspecialchars($senderName) ?></span> • <span><?= htmlspecialchars($time) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Empty State -->
                <?php if (empty($messages)): ?>
                    <div style="text-align:center;padding:40px 20px;color:var(--text-muted);">
                        <i class="fas fa-comments" style="font-size:2rem;margin-bottom:10px;opacity:0.5;"></i>
                        <p>No messages yet. Start the conversation!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Reply Form -->
            <div class="reply-bottom">
                <?php
                    $replyToEmail = '';
                    $replyToName = '';
                    foreach (($users ?? []) as $uu) {
                        if ((int)($uu['user_id'] ?? 0) === (int)$selectedUserId) {
                            $replyToEmail = (string)($uu['user_email'] ?? '');
                            $replyToName = (string)($uu['user_name'] ?? '');
                            break;
                        }
                    }
                    $replyToLabel = $replyToName ?: ($replyToEmail ?: 'User');
                ?>
                <div class="reply-label">Reply to <?= htmlspecialchars($replyToLabel) ?></div>
                
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="reply_to_user_id" value="<?= (int)$selectedUserId ?>">


                    <div class="reply-input-row">
                        <textarea 
                            class="reply-textarea" 
                            name="message" 
                            maxlength="1000" 
                            placeholder="Type your reply here..."
                            required
                        ></textarea>

                        <button type="submit" class="btn-send-reply" aria-label="Send reply" title="Send reply">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </form>
                
                <p class="reply-disclaimer">
                    <i class="fas fa-info-circle" style="margin-right:4px;"></i>
                    Replies are recorded and visible to the user.
                </p>
            </div>
        </section>
    </div>

</main>

<script>
    // Mobile Toggle
    const mobileToggle = document.getElementById('mobileToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    mobileToggle.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    });

    overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    });

    // Auto-scroll to bottom of chat on page load
    const messageList = document.getElementById('messageList');
    if (messageList) {
        messageList.scrollTop = messageList.scrollHeight;
    }

    // Auto-hide flash messages
    setTimeout(() => {
        const flash = document.querySelector('.top-small-flash');
        if (flash) {
            flash.style.opacity = '0';
            flash.style.transition = 'opacity 0.3s ease';
            setTimeout(() => flash.remove(), 300);
        }
    }, 3500);
</script>

</body>
</html>