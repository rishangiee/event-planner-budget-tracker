<?php
require_once 'config/config.php';
requireUser();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

$message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

// Handle Create New Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_event') {
    $newId = uniqid('evt_');
    $title = trim($_POST['title'] ?? '');
    $date = $_POST['date'] ?? '';
    $budget = (float) ($_POST['budget'] ?? 0);
    $maxAttendees = (int) ($_POST['max_attendees'] ?? 200);
    $description = trim($_POST['description'] ?? '');

    if ($title && $date && $budget > 0) {
        $ins = $pdo->prepare("INSERT INTO events (id, user_id, title, date, budget, attendees, max_attendees, status, description) VALUES (?, ?, ?, ?, ?, 0, ?, 'planned', ?)");
        $ins->execute([$newId, $userId, $title, $date, $budget, $maxAttendees, $description]);
        $_SESSION['flash_message'] = 'Event created successfully!';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $message = 'Please fill all required fields correctly.';
    }
}

// Handle Update Attendees
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_attendees') {
    $eventId = $_POST['event_id'] ?? '';
    $attendees = (int) ($_POST['attendees'] ?? 0);

    if ($eventId) {
        $upd = $pdo->prepare("UPDATE events SET attendees = ? WHERE id = ? AND user_id = ?");
        $upd->execute([$attendees, $eventId, $userId]);
        $_SESSION['flash_message'] = 'Attendees updated successfully!';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . urlencode($eventId));
        exit;
    }
}

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_expense') {
    $expId = uniqid('exp_');
    $eventId = $_POST['event_id'] ?? '';
    $category = trim($_POST['category'] ?? 'General');
    $amount = (float) ($_POST['amount'] ?? 0);
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $desc = trim($_POST['expense_description'] ?? '');

    if ($eventId && $amount > 0) {
        // Check event exists and get current budget info
        $chk = $pdo->prepare("
            SELECT e.*, COALESCE(SUM(ex.amount), 0) as total_spent 
            FROM events e 
            LEFT JOIN expenses ex ON e.id = ex.event_id 
            WHERE e.id = ? AND e.user_id = ?
            GROUP BY e.id
        ");
        $chk->execute([$eventId, $userId]);
        $eventData = $chk->fetch();

        if ($eventData) {
            $currentSpent = (float) ($eventData['total_spent'] ?? 0);
            $eventBudget = (float) ($eventData['budget'] ?? 0);
            $remaining = $eventBudget - $currentSpent;

            if ($amount > $remaining) {
                $message = '⚠️ Insufficient balance! Remaining budget: ₱' . number_format($remaining, 2);
            } else {
                $ins = $pdo->prepare("INSERT INTO expenses (id, event_id, category, amount, expense_date, description) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->execute([$expId, $eventId, $category, $amount, $expenseDate, $desc]);
                $_SESSION['flash_message'] = '✅ Expense added successfully!';
                header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . urlencode($eventId));
                exit;
            }
        } else {
            $message = 'Event not found.';
        }
    } else {
        $message = 'Please enter a valid amount.';
    }
}

// Handle Delete Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_event') {
    $eventId = $_POST['event_id'] ?? '';
    if ($eventId) {
        // Delete expenses first (foreign key constraint)
        $pdo->prepare("DELETE FROM expenses WHERE event_id = ?")->execute([$eventId]);
        // Delete event
        $pdo->prepare("DELETE FROM events WHERE id = ? AND user_id = ?")->execute([$eventId, $userId]);
        $_SESSION['flash_message'] = 'Event deleted successfully!';
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle Edit Event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_event') {
    $eventId = $_POST['event_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $date = $_POST['date'] ?? '';
    $budget = (float) ($_POST['budget'] ?? 0);
    $maxAttendees = (int) ($_POST['max_attendees'] ?? 200);
    $description = trim($_POST['description'] ?? '');

    if ($eventId && $title && $date && $budget > 0) {
        $upd = $pdo->prepare("UPDATE events SET title = ?, date = ?, budget = ?, max_attendees = ?, description = ? WHERE id = ? AND user_id = ?");
        $upd->execute([$title, $date, $budget, $maxAttendees, $description, $eventId, $userId]);
        $_SESSION['flash_message'] = 'Event updated successfully!';
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . urlencode($eventId));
        exit;
    } else {
        $message = 'Please fill all required fields correctly.';
    }
}

// Get user's events
$stmt = $pdo->prepare("
    SELECT e.*, COALESCE(SUM(ex.amount), 0) as total_spent, COUNT(ex.id) as expense_count
    FROM events e LEFT JOIN expenses ex ON e.id = ex.event_id
    WHERE e.user_id = ? GROUP BY e.id ORDER BY e.date ASC
");
$stmt->execute([$userId]);
$events = $stmt->fetchAll();

$hasEvents = !empty($events);
$activeEvent = null;
$expenses = [];
$budget = 0;
$spent = 0;
$remaining = 0;
$budgetUtilization = 0;
$attendees = 0;
$maxAttendees = 200;
$attendeePercent = 0;

$requestedId = $_GET['id'] ?? '';

if ($hasEvents) {
    if ($requestedId) {
        foreach ($events as $ev) {
            if ($ev['id'] === $requestedId) {
                $activeEvent = $ev;
                break;
            }
        }
    }
    if (!$activeEvent) {
        $activeEvent = $events[0];
    }
    
    if ($activeEvent) {
        $expStmt = $pdo->prepare("SELECT * FROM expenses WHERE event_id = ? ORDER BY expense_date DESC, created_at DESC LIMIT 50");
        $expStmt->execute([$activeEvent['id']]);
        $expenses = $expStmt->fetchAll();

        $budget = (float) ($activeEvent['budget'] ?? 0);
        $spent = (float) ($activeEvent['total_spent'] ?? 0); // Use pre-calculated total_spent
        $remaining = $budget - $spent;
        $budgetUtilization = $budget > 0 ? min(100, ($spent / $budget) * 100) : 0;
        $attendees = (int) ($activeEvent['attendees'] ?? 0);
        $maxAttendees = (int) ($activeEvent['max_attendees'] ?? 200);
        $attendeePercent = $maxAttendees > 0 ? min(100, ($attendees / $maxAttendees) * 100) : 0;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Planner - CAVENDIA</title>
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
        .card {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 2px 12px rgba(53,63,45,0.06);
            transition: all 0.2s ease;
        }
        .card:hover { box-shadow: 0 4px 20px rgba(53,63,45,0.1); }
        .progress-fill {
            transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            background: linear-gradient(90deg, #16a34a, #22c55e);
            height: 100%;
            border-radius: 9999px;
            box-shadow: 0 0 10px rgba(22, 163, 74, 0.3);
        }
        .progress-track {
            background: #e8ebe3;
            border-radius: 9999px;
            overflow: hidden;
            height: 12px;
        }
        .divider { border-top: 1px solid #e8ebe3; margin: 1.5rem 0; }
        .status-box {
            background: linear-gradient(135deg, #faf9f6 0%, #f5f7f5 100%);
            border: 1px solid #e8ebe3;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.2s ease;
        }
        .status-box:hover { transform: translateY(-2px); }
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(53,63,45,0.5); backdrop-filter: blur(8px);
            z-index: 1000; align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #faf9f6; border-radius: 20px;
            box-shadow: 0 25px 50px rgba(53,63,45,0.2);
            animation: modalSlide 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto;
            margin: 1rem;
        }
        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-close {
            position: absolute; top: 1rem; right: 1rem;
            width: 36px; height: 36px; border-radius: 12px;
            background: #fef2f2; color: #991b1b; border: none;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.2s ease; font-size: 14px;
        }
        .modal-close:hover { background: #fee2e2; transform: scale(1.05); }
        .form-input, .form-textarea {
            width: 100%; padding: 14px 16px;
            border: 2px solid #e3ebe0; border-radius: 14px;
            font-size: 15px; background: #ffffff;
            transition: all 0.3s ease; color: #293926; font-family: inherit;
        }
        .form-input:focus, .form-textarea:focus {
            outline: none; border-color: #7aa370; 
            box-shadow: 0 0 0 4px rgba(122,163,112,0.15);
            background: #fff;
        }
        .form-input::placeholder { color: #b8c4a9; }
        .btn-solid {
            padding: 14px 24px; border-radius: 14px; font-weight: 600;
            font-size: 15px; cursor: pointer; border: none; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex; align-items: center; gap: 8px;
        }
        .btn-solid:hover { transform: translateY(-2px); }
        .btn-forest { background: linear-gradient(135deg, #1a472a, #2d6a4f); color: #fff; }
        .btn-forest:hover { background: linear-gradient(135deg, #2d6a4f, #3d7b5a); box-shadow: 0 8px 25px rgba(26,71,42,0.3); }
        .btn-sage { background: linear-gradient(135deg, #7aa370, #88b47f); color: #fff; }
        .btn-sage:hover { background: linear-gradient(135deg, #88b47f, #9ac38f); }
        .btn-outline {
            padding: 14px 24px; border-radius: 14px; font-weight: 600;
            border: 2px solid #d4dbc9; background: #fff; color: #4d5b3f;
            transition: all 0.3s ease;
        }
        .btn-outline:hover { background: #f4f7f2; border-color: #7aa370; transform: translateY(-1px); }
        .expense-section { display: none; }
        .expense-section.active { display: block; animation: slideDown 0.3s ease; }
        @keyframes slideDown { from { opacity: 0; max-height: 0; } to { opacity: 1; max-height: 1000px; } }
        .expense-item {
            background: #faf9f6; border: 1px solid #e8ebe3;
            border-radius: 12px; padding: 16px; margin-bottom: 12px;
            transition: all 0.2s ease;
        }
        .expense-item:hover { border-color: #7aa370; transform: translateY(-1px); }
        .alert-success {
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            border: 1px solid #93d7a3; color: #166534;
        }
        .alert-error {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 1px solid #fca5a5; color: #991b1b;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f4f7f2; }
        ::-webkit-scrollbar-thumb { 
            background: linear-gradient(135deg, #c5d8bf, #b8c4a9); 
            border-radius: 10px; 
        }
        ::-webkit-scrollbar-thumb:hover { background: #a8b4a0; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Toast Message -->
    <?php if ($message): ?>
    <?php $isError = str_contains($message, '⚠️') || str_contains($message, 'Insufficient') || str_contains($message, 'Please'); ?>
    <div class="fixed top-20 right-6 z-50 p-4 rounded-2xl shadow-xl <?php echo $isError ? 'alert-error' : 'alert-success'; ?> font-semibold flex items-center gap-3 max-w-sm animate-pulse">
        <i class="fas <?php echo $isError ? 'fa-exclamation-circle' : 'fa-check-circle'; ?> text-lg"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <!-- Top Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 shadow-sm" style="background: linear-gradient(135deg, #3d5a40, #4a6b50);">
        <div class="flex items-center justify-between px-6 py-4">
            <a href="dashboard.php" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background: linear-gradient(135deg, #1a472a, #2d6a4f);">
                    <i class="fas fa-calendar-alt text-white text-lg"></i>
                </div>
                <span class="text-xl font-bold tracking-wide text-white">CAVENDIA</span>
            </a>
            <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-xl font-semibold text-sm transition-all flex items-center gap-2 shadow-lg hover:shadow-xl hover:-translate-y-0.5">
                <i class="fas fa-sign-out-alt"></i> Logout
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
                    <a href="event.php" class="sidebar-item active">
                        <i class="fas fa-calendar-check"></i><span>Event</span>
                    </a>
                    <a href="calendar.php" class="sidebar-item">
                        <i class="fas fa-calendar-days"></i><span>Calendar</span>
                    </a>
                    <a href="booking.php" class="sidebar-item">
                        <i class="fas fa-bookmark"></i><span>My Bookings</span>
                    </a>
                    <a href="profile.php" class="sidebar-item">
                        <i class="fas fa-user-circle"></i><span>Profile</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8 min-h-screen" style="margin-left:16rem; background:#f6f7f4;">

            <?php if (!$hasEvents): ?>
            <!-- EMPTY STATE -->
            <div class="max-w-2xl mx-auto mt-32 text-center">
                <div class="card p-12">
                    <div class="w-24 h-24 rounded-3xl flex items-center justify-center mx-auto mb-8" style="background: linear-gradient(135deg, #f4f7f2, #f0f4f0);">
                        <i class="fas fa-calendar-plus text-4xl" style="color:#b8c4a9;"></i>
                    </div>
                    <h2 class="text-3xl font-bold mb-4" style="color:#353f2d;">No Events Yet</h2>
                    <p class="text-lg mb-8 text-[#62744f] leading-relaxed max-w-md mx-auto">
                        Start planning your first event. Track budgets, manage attendees, and monitor expenses effortlessly.
                    </p>
                    <button onclick="openModal('createModal')" class="btn-solid btn-forest text-lg px-12 py-4 shadow-xl">
                        <i class="fas fa-plus mr-2"></i>Create First Event
                    </button>
                </div>
            </div>
            <?php else: ?>
            <!-- EVENT DETAILS -->
            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold" style="color:#1a472a;">Event &amp; Budget Tracker</h2>
                        <p class="text-sm mt-1" style="color:#62744f;">Manage events, expenses, and attendees</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button onclick="openModal('createModal')" class="btn-solid btn-forest text-sm px-5 py-3">
                            <i class="fas fa-plus mr-2"></i>Create Event
                        </button>
                    </div>
                </div>
            </div>

            <div class="card p-8 mb-8">
                <!-- Header -->
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between lg:gap-6 mb-8">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-start gap-3 mb-4 flex-wrap">
                            <h1 class="text-3xl font-bold" style="color:#1a472a;"><?php echo htmlspecialchars($activeEvent['title']); ?></h1>
                            <span class="px-4 py-2 rounded-full text-sm font-semibold mt-1" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0); color:#15803d;">
                                <i class="fas fa-clock mr-1"></i>Upcoming
                            </span>
                        </div>
                        <?php if ($activeEvent['description']): ?>
                        <p class="text-lg mb-4 leading-relaxed" style="color:#4d5b3f;"><?php echo htmlspecialchars($activeEvent['description']); ?></p>
                        <?php endif; ?>
                        <div class="flex flex-wrap gap-6 text-sm font-medium">
                            <div class="flex items-center gap-2" style="color:#62744f;">
                                <i class="far fa-calendar-alt text-lg"></i>
                                <span><?php echo date('F j, Y', strtotime($activeEvent['date'])); ?></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fas fa-users text-lg" style="color:#62744f;"></i>
                                <span id="attendeeCount"><?php echo number_format($attendees); ?> / <?php echo number_format($maxAttendees); ?> attendees</span>
                                <button onclick="openModal('attendeesModal')" class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all hover:scale-105" style="background:#4d5b3f; color:#ffffff;">
                                    Update
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-4 lg:mt-0 shrink-0">
                        <button onclick="openModal('editModal')" class="w-10 h-10 rounded-xl flex items-center justify-center transition-all hover:scale-105" style="background:#f4f7f2; color:#4d5b3f;" title="Edit Event">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this event and all its data?')">
                            <input type="hidden" name="action" value="delete_event">
                            <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($activeEvent['id']); ?>">
                            <button type="submit" class="w-10 h-10 rounded-xl flex items-center justify-center transition-all hover:scale-105" style="background:#fef2f2; color:#991b1b;" title="Delete Event">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Budget Warning Banner -->
                <?php if ($remaining <= 0): ?>
                <div class="mb-6 p-4 rounded-xl flex items-center gap-3" style="background: linear-gradient(135deg, #fef2f2, #fee2e2); border: 1px solid #fca5a5;">
                    <i class="fas fa-exclamation-triangle text-xl" style="color:#dc2626;"></i>
                    <div>
                        <p class="font-bold" style="color:#991b1b;">Insufficient Balance!</p>
                        <p class="text-sm" style="color:#b91c1c;">You have exceeded your budget. Remaining: ₱<?php echo number_format($remaining, 2); ?></p>
                    </div>
                </div>
                <?php elseif ($budgetUtilization >= 90): ?>
                <div class="mb-6 p-4 rounded-xl flex items-center gap-3" style="background: linear-gradient(135deg, #fffbeb, #fef3c7); border: 1px solid #fcd34d;">
                    <i class="fas fa-exclamation-circle text-xl" style="color:#d97706;"></i>
                    <div>
                        <p class="font-bold" style="color:#92400e;">Budget Almost Depleted!</p>
                        <p class="text-sm" style="color:#b45309;">You have used <?php echo round($budgetUtilization); ?>% of your budget. Remaining: ₱<?php echo number_format($remaining, 2); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Financial Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="status-box">
                        <div class="text-sm font-semibold mb-2 uppercase tracking-wide" style="color:#62744f;">Total Budget</div>
                        <div class="text-3xl font-bold" style="color:#1a472a;">₱<?php echo number_format($budget, 2); ?></div>
                    </div>
                    <div class="status-box">
                        <div class="text-sm font-semibold mb-2 uppercase tracking-wide" style="color:#62744f;">Spent</div>
                        <div class="text-3xl font-bold" style="color:#b91c1c;">₱<?php echo number_format($spent, 2); ?></div>
                    </div>
                    <div class="status-box">
                        <div class="text-sm font-semibold mb-2 uppercase tracking-wide" style="color:#62744f;">Remaining</div>
                        <div class="text-3xl font-bold" style="color:#16a34a;">₱<?php echo number_format($remaining, 2); ?></div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Budget Progress -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-lg font-semibold" style="color:#353f2d;">Budget Utilization</span>
                            <span class="text-lg font-bold" style="color:#16a34a;"><?php echo round($budgetUtilization); ?>%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: <?php echo $budgetUtilization; ?>%"></div>
                        </div>
                    </div>

                    <!-- Attendees Progress -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <span class="text-lg font-semibold" style="color:#353f2d;">Attendee Capacity</span>
                            <span class="text-lg font-bold" style="color:#9333ea;"><?php echo round($attendeePercent); ?>%</span>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: <?php echo $attendeePercent; ?>%; background: linear-gradient(90deg, #9333ea, #a855f7);"></div>
                        </div>
                    </div>
                </div>

                <!-- Expenses Section -->
                <div class="divider"></div>
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold flex items-center gap-3" style="color:#353f2d;">
                        <i class="fas fa-receipt text-2xl" style="color:#62744f;"></i>
                        Expenses (<?php echo count($expenses); ?>)
                    </h3>
                    <div class="flex items-center gap-3">
                        <button onclick="toggleExpenses()" id="expenseToggle" class="px-6 py-3 rounded-xl font-semibold text-sm transition-all bg-[#f4f7f2] hover:bg-[#e8ebe3] text-[#4d5b3f]">
                            <i class="fas fa-chevron-down mr-1"></i>Show Expenses
                        </button>
                        <button onclick="openModal('addExpenseModal')" class="btn-solid btn-sage px-8 py-3">
                            <i class="fas fa-plus mr-2"></i>Add Expense
                        </button>
                    </div>
                </div>

                <div id="expenseSection" class="expense-section">
                    <?php if (empty($expenses)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-receipt text-4xl text-[#b8c4a9] mb-4"></i>
                        <p class="text-lg font-medium mb-2" style="color:#62744f;">No expenses recorded yet</p>
                        <p class="text-sm" style="color:#8b9a7a;">Add your first expense to start tracking spending</p>
                    </div>
                    <?php else: ?>
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        <?php foreach (array_slice($expenses, 0, 10) as $expense): ?>
                        <div class="expense-item">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-1">
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold" style="background:#e8f5e8; color:#15803d;">
                                            <?php echo htmlspecialchars($expense['category'] ?? 'General'); ?>
                                        </span>
                                    </div>
                                    <?php if ($expense['description']): ?>
                                    <p class="text-sm mb-1" style="color:#4d5b3f;"><?php echo htmlspecialchars($expense['description']); ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs" style="color:#8b9a7a;"><?php echo date('M j, Y', strtotime($expense['expense_date'] ?? 'now')); ?></p>
                                </div>
                                <div class="text-right ml-4">
                                    <div class="text-2xl font-bold" style="color:#b91c1c;">₱<?php echo number_format((float)$expense['amount'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (count($expenses) > 10): ?>
                        <div class="text-center py-4 pt-8 border-t border-[#e8ebe3]">
                            <p class="text-sm text-[#62744f]">Showing 10 of <?php echo count($expenses); ?> expenses</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Events List (if multiple) -->
            <?php if (count($events) > 1): ?>
            <div class="card p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-3" style="color:#353f2d;">
                    <i class="fas fa-list text-xl"></i>All Events
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($events as $event): ?>
                    <a href="?id=<?php echo urlencode($event['id']); ?>" class="group">
                        <div class="card p-6 h-full hover:shadow-xl transition-all border-2 border-transparent group-hover:border-[#7aa370] rounded-xl">
                            <h4 class="font-bold mb-2 text-lg" style="color:#1a472a;"><?php echo htmlspecialchars($event['title']); ?></h4>
                            <p class="text-sm mb-3" style="color:#62744f;"><?php echo date('M j, Y', strtotime($event['date'])); ?></p>
                            <div class="flex items-center justify-between">
                                <span class="text-2xl font-bold" style="color:#16a34a;">₱<?php echo number_format((float)$event['budget'], 0); ?></span>
                                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-[#e8f5e8] text-[#15803d]">
                                    <?php echo (float)$event['total_spent'] > 0 ? round(((float)$event['total_spent'] / (float)$event['budget']) * 100) . '%' : 'New'; ?>
                                </span>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

        </main>
    </div>

    <!-- Create Event Modal -->
    <div id="createModal" class="modal-overlay">
        <div class="modal-box p-8 relative">
            <button class="modal-close" onclick="closeModal('createModal')"><i class="fas fa-times"></i></button>
            <h3 class="text-2xl font-bold mb-6" style="color:#1a472a;">Create New Event</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_event">
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Event Title</label>
                        <input type="text" name="title" required placeholder="e.g. Summer Wedding Celebration" class="form-input">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Event Date</label>
                        <input type="date" name="date" required min="<?php echo date('Y-m-d'); ?>" class="form-input">
                    </div>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Budget</label>
                            <input type="number" name="budget" min="1" required placeholder="50000" class="form-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Max Attendees</label>
                            <input type="number" name="max_attendees" min="1" max="500" required placeholder="200" class="form-input">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Description</label>
                        <textarea name="description" rows="4" class="form-textarea" placeholder="Event description..."></textarea>
                    </div>
                </div>
                <div class="flex gap-4 mt-8 pt-6 border-t border-[#e8ebe3]">
                    <button type="button" onclick="closeModal('createModal')" class="btn-outline flex-1">Cancel</button>
                    <button type="submit" class="btn-solid btn-forest flex-1">Create Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Event Modal -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-box p-8 relative">
            <button class="modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
            <h3 class="text-2xl font-bold mb-6" style="color:#1a472a;">Edit Event</h3>
            <form method="POST">
                <input type="hidden" name="action" value="edit_event">
                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($activeEvent['id'] ?? ''); ?>">
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Event Title</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($activeEvent['title'] ?? ''); ?>" required class="form-input">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Event Date</label>
                        <input type="date" name="date" value="<?php echo htmlspecialchars($activeEvent['date'] ?? ''); ?>" required class="form-input">
                    </div>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Budget</label>
                            <input type="number" name="budget" min="1" value="<?php echo htmlspecialchars($activeEvent['budget'] ?? ''); ?>" required class="form-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Max Attendees</label>
                            <input type="number" name="max_attendees" min="1" max="500" value="<?php echo htmlspecialchars($activeEvent['max_attendees'] ?? ''); ?>" required class="form-input">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Description</label>
                        <textarea name="description" rows="4" class="form-textarea"><?php echo htmlspecialchars($activeEvent['description'] ?? ''); ?></textarea>
                    </div>
                </div>
                <div class="flex gap-4 mt-8 pt-6 border-t border-[#e8ebe3]">
                    <button type="button" onclick="closeModal('editModal')" class="btn-outline flex-1">Cancel</button>
                    <button type="submit" class="btn-solid btn-sage flex-1">Update Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Attendees Modal -->
    <div id="attendeesModal" class="modal-overlay">
        <div class="modal-box p-8 relative max-w-md mx-auto">
            <button class="modal-close" onclick="closeModal('attendeesModal')"><i class="fas fa-times"></i></button>
            <h3 class="text-2xl font-bold mb-6" style="color:#1a472a;">Update Attendees</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_attendees">
                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($activeEvent['id'] ?? ''); ?>">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold mb-2" style="color:#4d5b3f;">Current Attendees</label>
                        <input type="number" name="attendees" value="<?php echo $attendees; ?>" min="0" max="<?php echo $maxAttendees; ?>" required class="form-input text-center text-3xl font-bold tracking-wider">
                    </div>
                    <div class="text-center py-4 bg-[#f4f7f2] rounded-xl">
                        <p class="text-sm font-semibold mb-1" style="color:#62744f;">Max Capacity</p>
                        <p class="text-2xl font-bold" style="color:#1a472a;"><?php echo number_format($maxAttendees); ?></p>
                    </div>
                </div>
                <div class="flex gap-4 mt-8 pt-6 border-t border-[#e8ebe3]">
                    <button type="button" onclick="closeModal('attendeesModal')" class="btn-outline flex-1">Cancel</button>
                    <button type="submit" class="btn-solid btn-forest flex-1">Update Count</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Expense Modal -->
    <div id="addExpenseModal" class="modal-overlay">
        <div class="modal-box p-8 relative">
            <button class="modal-close" onclick="closeModal('addExpenseModal')"><i class="fas fa-times"></i></button>
            <h3 class="text-2xl font-bold mb-6" style="color:#1a472a;">Add New Expense</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_expense">
                <input type="hidden" name="event_id" value="<?php echo htmlspecialchars($activeEvent['id'] ?? ''); ?>">
                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Category</label>
                        <input type="text" name="category" placeholder="Venue, Catering, Decorations..." required class="form-input">
                    </div>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Amount</label>
                            <input type="number" name="amount" step="0.01" min="0.01" required placeholder="1250.00" class="form-input">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Date</label>
                            <input type="date" name="expense_date" value="<?php echo date('Y-m-d'); ?>" class="form-input">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold mb-3" style="color:#4d5b3f;">Description</label>
                        <textarea name="expense_description" rows="3" class="form-textarea" placeholder="Additional details about this expense..."></textarea>
                    </div>
                </div>
                <div class="flex gap-4 mt-8 pt-6 border-t border-[#e8ebe3]">
                    <button type="button" onclick="closeModal('addExpenseModal')" class="btn-outline flex-1">Cancel</button>
                    <button type="submit" class="btn-solid btn-sage flex-1">Add Expense</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal Controls
        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }

        // Toggle Expenses
        function toggleExpenses() {
            const section = document.getElementById('expenseSection');
            const toggleBtn = document.getElementById('expenseToggle');
            
            if (section.classList.contains('active')) {
                section.classList.remove('active');
                toggleBtn.innerHTML = '<i class="fas fa-chevron-down mr-1"></i>Show Expenses';
                toggleBtn.classList.remove('bg-[#e8ebe3]');
            } else {
                section.classList.add('active');
                toggleBtn.innerHTML = '<i class="fas fa-chevron-up mr-1"></i>Hide Expenses';
                toggleBtn.classList.add('bg-[#e8ebe3]');
            }
        }

        // Close modals on overlay click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Auto-hide success message
        setTimeout(() => {
            const alert = document.querySelector('.alert-success');
            if (alert) {
                alert.style.transition = 'all 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateX(400px)';
                setTimeout(() => alert.remove(), 500);
            }
        }, 5000);

        // ESC key to close modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(modal => {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                });
            }
        });

        // Refresh page after successful operations to update data
        <?php if ($message && !str_contains($message, '⚠️') && !str_contains($message, 'Insufficient')): ?>
        setTimeout(() => {
            window.location.reload();
        }, 1500);
        <?php endif; ?>
    </script>

</body>
</html>