<?php
require_once 'config/config.php';
requireAdmin();

$userId = $_SESSION['user_id'];
$adminName = $_SESSION['user_name'] ?? 'Admin';

$eventId = $_GET['id'] ?? '';
$message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

if (!$eventId) {
    header('Location: admin_dashboard.php');
    exit;
}

// Get event details + aggregated data
$stmt = $pdo->prepare("
    SELECT e.*, u.name as owner_name, u.email as owner_email,
           COALESCE(SUM(ex.amount), 0) as total_spent,
           COUNT(b.id) as booking_count,
           e.attendees, e.max_attendees
    FROM events e 
    LEFT JOIN users u ON e.user_id = u.id
    LEFT JOIN expenses ex ON e.id = ex.event_id
    LEFT JOIN bookings b ON e.id = b.event_id AND b.status != 'cancelled'
    WHERE e.id = ? 
    GROUP BY e.id
");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    $_SESSION['flash_message'] = 'Event not found.';
    header('Location: admin_dashboard.php');
    exit;
}

// Get all expenses
$expensesStmt = $pdo->prepare("SELECT * FROM expenses WHERE event_id = ? ORDER BY expense_date DESC, created_at DESC");
$expensesStmt->execute([$eventId]);
$expenses = $expensesStmt->fetchAll();

// Get bookings
$bookingsStmt = $pdo->prepare("
    SELECT b.*, u.name as user_name, u.email 
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    WHERE b.event_id = ? AND b.status != 'cancelled'
    ORDER BY b.created_at DESC
");
$bookingsStmt->execute([$eventId]);
$bookings = $bookingsStmt->fetchAll();

// Financial calculations
$budget = (float)($event['budget'] ?? 0);
$totalSpent = (float)($event['total_spent'] ?? 0);
$remaining = $budget - $totalSpent;
$budgetUtilization = $budget > 0 ? min(100, ($totalSpent / $budget) * 100) : 0;
$attendees = (int)($event['attendees'] ?? 0);
$maxAttendees = (int)($event['max_attendees'] ?? 200);
$attendeePercent = $maxAttendees > 0 ? min(100, ($attendees / $maxAttendees) * 100) : 0;

// Handle admin actions
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_status') {
        $newStatus = $_POST['status'] ?? $event['status'];
        $pdo->prepare("UPDATE events SET status = ? WHERE id = ?")->execute([$newStatus, $eventId]);
        $_SESSION['flash_message'] = 'Event status updated!';
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . urlencode($eventId));
        exit;
    }
    
    if ($action === 'update_attendees') {
        $newAttendees = (int)($_POST['attendees'] ?? $attendees);
        $pdo->prepare("UPDATE events SET attendees = ? WHERE id = ?")->execute([$newAttendees, $eventId]);
        $_SESSION['flash_message'] = 'Attendees updated!';
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . urlencode($eventId));
        exit;
    }
    
    if ($action === 'assign_user') {
        $newUserId = (int)($_POST['user_id'] ?? $event['user_id']);
        $pdo->prepare("UPDATE events SET user_id = ? WHERE id = ?")->execute([$newUserId, $eventId]);
        $_SESSION['flash_message'] = 'Event reassigned!';
        header("Location: " . $_SERVER['PHP_SELF'] . "?id=" . urlencode($eventId));
        exit;
    }
}

// Get all users for reassignment dropdown
$usersStmt = $pdo->query("SELECT id, name, email FROM users ORDER BY name");
$users = $usersStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['title'] ?? 'Event'); ?> - Admin - CAVENDIA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f6f7f4; }
        .sidebar-item.active { background-color: rgba(26,71,42,0.85); border-left: 3px solid #1a472a; }
        .card { background: #ffffff; border-radius: 20px; box-shadow: 0 2px 12px rgba(53,63,45,0.06); }
        .progress-fill { background: linear-gradient(90deg, #16a34a, #22c55e); height: 100%; border-radius: 9999px; }
        .btn-admin { background: linear-gradient(135deg, #9333ea, #a855f7); color: white; }
        .btn-admin:hover { background: linear-gradient(135deg, #a855f7, #c084fc); }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #c5d8bf, #b8c4a9); border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Top Nav (Admin version) -->
    <nav class="fixed top-0 left-0 right-0 z-50 shadow-sm" style="background: linear-gradient(135deg, #3d5a40, #4a6b50);">
        <div class="flex items-center justify-between px-6 py-4">
            <a href="admin_dashboard.php" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background: linear-gradient(135deg, #1a472a, #2d6a4f);">
                    <i class="fas fa-shield-alt text-white text-lg"></i>
                </div>
                <span class="text-xl font-bold tracking-wide text-white">CAVENDIA ADMIN</span>
            </a>
            <div class="flex items-center gap-4">
                <a href="admin_event.php?id=<?php echo urlencode($eventId); ?>" class="text-white/80 hover:text-white transition-colors">
                    ← Back to Events
                </a>
                <a href="index.php" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-xl font-semibold text-sm transition-all flex items-center gap-2 shadow-sm">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="flex pt-16 min-h-screen">
        <!-- Sidebar -->
        <aside class="fixed left-0 top-16 bottom-0 w-64 overflow-y-auto shadow-lg" style="background: linear-gradient(180deg, #d8f3dc 0%, #c8e6c8 100%);">
            <div class="p-6 pt-8">
                <nav class="space-y-2">
                    <a href="admin_dashboard.php" class="sidebar-item">
                        <i class="fas fa-th-large"></i><span>Dashboard</span>
                    </a>
                    <a href="admin_event.php" class="sidebar-item active">
                        <i class="fas fa-calendar-check"></i><span>Event Details</span>
                    </a>
                </nav>
            </div>
        </aside>

        <main class="flex-1 p-8 min-h-screen" style="margin-left:16rem; background:#f6f7f4;">

            <!-- Header -->
            <div class="mb-8">
                <div class="flex items-start justify-between gap-6 mb-6">
                    <div>
                        <h1 class="text-3xl font-bold" style="color:#1a472a;"><?php echo htmlspecialchars($event['title']); ?></h1>
                        <div class="flex items-center gap-4 mt-2">
                            <span class="px-4 py-2 rounded-full text-sm font-semibold" style="background: linear-gradient(135deg, #dcfce7, #bbf7d0); color:#15803d;">
                                <?php echo ucfirst($event['status'] ?? 'Planned'); ?>
                            </span>
                            <span class="text-sm font-medium" style="color:#62744f;">
                                Owner: <?php echo htmlspecialchars($event['owner_name'] ?? 'Unassigned'); ?>
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <a href="admin_dashboard.php" class="px-6 py-3 rounded-xl font-semibold text-sm transition-all bg-[#f4f7f2] hover:bg-[#e8ebe3] text-[#4d5b3f] flex items-center gap-2">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <!-- Event Overview Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="card p-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold mb-1" style="color:#16a34a;">₱<?php echo number_format($budget, 2); ?></div>
                        <div class="text-sm font-medium mb-1" style="color:#62744f;">Total Budget</div>
                    </div>
                </div>
                <div class="card p-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold mb-1 text-red-600">₱<?php echo number_format($totalSpent, 2); ?></div>
                        <div class="text-sm font-medium mb-1" style="color:#62744f;">Total Spent</div>
                    </div>
                </div>
                <div class="card p-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold mb-1" style="color:#9333ea;"><?php echo $bookingCount ?? 0; ?></div>
                        <div class="text-sm font-medium mb-1" style="color:#62744f;">Bookings</div>
                    </div>
                </div>
                <div class="card p-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold mb-1" style="color:#f97316;"><?php echo number_format($attendees); ?>/<?php echo number_format($maxAttendees); ?></div>
                        <div class="text-sm font-medium" style="color:#62744f;">Attendees/Capacity</div>
                    </div>
                </div>
            </div>

            <!-- Main Metrics Row -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                <!-- Budget Progress -->
                <div class="card p-8">
                    <h3 class="text-xl font-bold mb-6 flex items-center gap-3" style="color:#1a472a;">
                        <i class="fas fa-chart-line text-2xl"></i> Budget Progress
                    </h3>
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span>Utilization</span>
                            <span class="font-bold"><?php echo round($budgetUtilization); ?>%</span>
                        </div>
                        <div class="w-full h-4 bg-gray-200 rounded-full">
                            <div class="progress-fill h-4 rounded-full" style="width: <?php echo $budgetUtilization; ?>%"></div>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>Remaining: <span class="font-bold text-green-600">₱<?php echo number_format($remaining, 2); ?></span></div>
                            <?php if (isset($event['owner_name'])): ?>
                            <div>Owner: <span class="font-medium"><?php echo htmlspecialchars($event['owner_name']); ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card p-8">
                    <h3 class="text-xl font-bold mb-6" style="color:#1a472a;">Quick Actions</h3>
                    <div class="grid grid-cols-1 gap-4">
                        <form method="POST" class="flex items-center gap-3">
                            <input type="hidden" name="action" value="update_status">
                            <select name="status" class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="planned" <?php echo ($event['status'] ?? 'planned') === 'planned' ? 'selected' : ''; ?>>Planned</option>
                                <option value="ongoing" <?php echo ($event['status'] ?? '') === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="completed" <?php echo ($event['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo ($event['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                            <button type="submit" class="px-6 py-3 bg-purple-600 text-white rounded-xl font-semibold hover:bg-purple-700 transition-all flex items-center gap-2">
                                <i class="fas fa-save"></i> Update
                            </button>
                        </form>
                        
                        <?php if ($users): ?>
                        <form method="POST" class="flex items-center gap-3">
                            <input type="hidden" name="action" value="assign_user">
                            <select name="user_id" class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl focus:ring-2 focus:ring-green-500">
                                <option value="">Reassign Owner</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo ($event['user_id'] ?? 0) == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 transition-all flex items-center gap-2">
                                <i class="fas fa-user-plus"></i> Reassign
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Expenses & Bookings Tabs -->
            <div class="card p-8">
                <div class="flex items-center justify-between mb-8">
                    <h3 class="text-xl font-bold" style="color:#1a472a;"><?php echo htmlspecialchars($event['title']); ?> Details</h3>
                </div>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Expenses -->
                    <div>
                        <h4 class="text-lg font-bold mb-4 flex items-center gap-2" style="color:#353f2d;">
                            <i class="fas fa-receipt"></i> Expenses (<?php echo count($expenses); ?>)
                        </h4>
                        <?php if (empty($expenses)): ?>
                        <div class="text-center py-12 border-2 border-dashed border-gray-200 rounded-2xl">
                            <i class="fas fa-receipt text-4xl text-gray-400 mb-4"></i>
                            <p class="text-lg font-medium text-gray-500">No expenses recorded</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-4 max-h-96 overflow-y-auto">
                            <?php foreach ($expenses as $expense): ?>
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                <div>
                                    <div class="font-semibold text-sm"><?php echo htmlspecialchars($expense['category'] ?? 'General'); ?></div>
                                    <?php if ($expense['description']): ?>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($expense['description']); ?></div>
                                    <?php endif; ?>
                                    <div class="text-xs text-gray-400"><?php echo date('M j, Y', strtotime($expense['expense_date'])); ?></div>
                                </div>
                                <div class="font-bold text-lg text-red-600">₱<?php echo number_format((float)$expense['amount'], 2); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Bookings -->
                    <div>
                        <h4 class="text-lg font-bold mb-4 flex items-center gap-2" style="color:#353f2d;">
                            <i class="fas fa-bookmark"></i> Bookings (<?php echo count($bookings); ?>)
                        </h4>
                        <?php if (empty($bookings)): ?>
                        <div class="text-center py-12 border-2 border-dashed border-gray-200 rounded-2xl">
                            <i class="fas fa-bookmark text-4xl text-gray-400 mb-4"></i>
                            <p class="text-lg font-medium text-gray-500">No bookings yet</p>
                        </div>
                        <?php else: ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach ($bookings as $booking): ?>
                            <div class="flex items-center justify-between p-4 bg-blue-50/50 rounded-xl border border-blue-100">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-blue-200 flex items-center justify-center text-sm font-bold text-blue-800">
                                        <?php echo strtoupper(substr($booking['user_name'] ?? 'User', 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-sm"><?php echo htmlspecialchars($booking['user_name'] ?? 'Anonymous'); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($booking['email']); ?></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-sm font-medium"><?php echo $booking['guest_count']; ?> guests</div>
                                    <div class="text-xs text-gray-500"><?php echo date('M j', strtotime($booking['created_at'])); ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <script>
        // Simple JS for potential future interactions
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide flash messages
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(alert => {
                    alert.style.transition = 'all 0.5s ease';
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateX(20px)';
                });
            }, 4000);
        });
    </script>

</body>
</html>

