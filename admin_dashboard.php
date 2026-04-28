<?php
require_once 'config/config.php';
requireAdmin();

$userName = $_SESSION['user_name'] ?? 'Admin';

// Get all events for admin view
$stmt = $pdo->prepare("
    SELECT e.*, u.name as user_name,
           COALESCE(SUM(ex.amount), 0) as total_spent
    FROM events e 
    LEFT JOIN users u ON e.user_id = u.id
    LEFT JOIN expenses ex ON e.id = ex.event_id 
    GROUP BY e.id 
    ORDER BY e.date ASC
");
$stmt->execute();
$events = $stmt->fetchAll();

// Total stats
$totalEvents = count($events);
$totalBudget = array_sum(array_column($events, 'budget'));
$totalSpent = array_sum(array_column($events, 'total_spent'));
$totalAttendees = array_sum(array_column($events, 'attendees'));
$remainingBudget = $totalBudget - $totalSpent;
$budgetUtilization = $totalBudget > 0 ? min(100, ($totalSpent / $totalBudget) * 100) : 0;

// Get all users
$users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CAVENDIA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f6f7f4; }
        .sidebar-item { transition: all 0.2s ease; border-radius: 0 12px 12px 0; }
        .sidebar-item:hover { background-color: rgba(255,255,255,0.5); }
        .sidebar-item.active {
            background-color: rgba(255,255,255,0.85);
            border-left: 3px solid #4d5b3f;
            color: #353f2d;
            font-weight: 600;
        }
        .card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 2px 12px rgba(53,63,45,0.06);
            transition: all 0.2s ease;
        }
        .card:hover { box-shadow: 0 4px 20px rgba(53,63,45,0.1); }
        .progress-fill { transition: width 0.8s cubic-bezier(0.4, 0, 0.2, 1); }
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(53,63,45,0.35); backdrop-filter: blur(3px);
            z-index: 100; align-items: center; justify-content: center;
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #faf9f6; border-radius: 24px;
            box-shadow: 0 20px 60px rgba(53,63,45,0.15);
            animation: modalSlide 0.25s ease; width: 100%; max-width: 480px;
            margin: 20px; max-height: 90vh; overflow-y: auto;
        }
        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(20px) scale(0.97); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .modal-close {
            width: 32px; height: 32px; border-radius: 10px;
            background: #fef2f2; color: #991b1b; border: none;
            display: flex; align-items: center; justify-content: center;
            cursor: pointer; transition: all 0.15s;
        }
        .modal-close:hover { background: #fee2e2; }
        .form-input {
            width: 100%; padding: 14px 16px;
            border: 1.5px solid #e3ebe0; border-radius: 14px;
            font-size: 0.95rem; background: #ffffff;
            transition: all 0.2s ease; color: #293926;
        }
        .form-input:focus { outline: none; border-color: #7aa370; box-shadow: 0 0 0 4px rgba(122,163,112,0.1); }
        .form-input::placeholder { color: #b8c4a9; }
        .btn-solid {
            padding: 12px 24px; border-radius: 14px; font-weight: 600;
            font-size: 0.9rem; cursor: pointer; border: none; transition: all 0.2s ease;
        }
        .btn-solid:hover { transform: translateY(-1px); }
        .btn-forest { background: #1a472a; color: #fff; }
        .btn-forest:hover { background: #2d6a4f; }
        .btn-purple { background: #8b5cf6; color: #fff; }
        .btn-purple:hover { background: #7c3aed; }
        .btn-outline {
            padding: 12px 24px; border-radius: 14px; font-weight: 600;
            font-size: 0.9rem; cursor: pointer; border: 1.5px solid #d4dbc9;
            background: #fff; color: #4d5b3f; transition: all 0.2s ease;
        }
        .btn-outline:hover { background: #f4f7f2; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #c5d8bf; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Top Nav -->
    <nav class="fixed top-0 left-0 right-0 bg-white z-50 shadow-sm border-b" style="border-color:#e8ebe3;">
        <div class="flex items-center justify-between px-6 py-3.5">
            <a href="admin_dashboard.php" class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:#1a472a;">
                    <i class="fas fa-leaf text-white text-sm"></i>
                </div>
                <span class="text-xl font-bold tracking-wide" style="color:#353f2d;">CAVENDIA</span>
            </a>
            <div class="flex items-center gap-5">
                <span class="text-sm hidden sm:inline font-medium" style="color:#62744f;">Welcome, <?php echo htmlspecialchars($userName); ?>!</span>
                <a href="logout.php" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-xl font-semibold text-sm transition-colors flex items-center gap-2 shadow-sm">
                    <i class="fas fa-sign-out-alt text-xs"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="flex pt-14 min-h-screen">
        <!-- Sidebar -->
        <aside class="fixed left-0 top-14 bottom-0 overflow-y-auto shadow-sm" style="width:16rem; background:#d8f3dc;">
            <div class="p-5 pt-6">
                <div class="mb-5 px-3">
                    <span class="text-xs font-bold uppercase tracking-wider" style="color:#62744f;">Admin Portal</span>
                </div>
                <nav class="space-y-1">
                    <a href="admin_dashboard.php" class="sidebar-item active flex items-center gap-3 px-3 py-2.5 text-sm" style="color:#353f2d;">
                        <i class="fas fa-th-large w-4 text-center" style="color:#4d5b3f;"></i><span>Dashboard</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 text-sm" style="color:#353f2d;">
                        <i class="fas fa-calendar-alt w-4 text-center" style="color:#62744f;"></i><span>Events</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 text-sm" style="color:#353f2d;">
                        <i class="fas fa-users w-4 text-center" style="color:#62744f;"></i><span>Users</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 text-sm" style="color:#353f2d;">
                        <i class="fas fa-chart-bar w-4 text-center" style="color:#62744f;"></i><span>Analytics</span>
                    </a>
                    <a href="#" class="sidebar-item flex items-center gap-3 px-3 py-2.5 text-sm" style="color:#353f2d;">
                        <i class="fas fa-cog w-4 text-center" style="color:#62744f;"></i><span>Settings</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-6 min-h-screen" style="margin-left:16rem; background:#f6f7f4;">

            <div class="flex items-center justify-between mb-5">
                <div>
                    <h1 class="text-2xl font-bold" style="color:#353f2d;">Welcome, <?php echo htmlspecialchars($userName); ?>!</h1>
                    <p class="text-sm mt-0.5" style="color:#62744f;">Admin overview of all events and users.</p>
                </div>
            </div>

            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold" style="color:#353f2d;">Event & Budget Tracker</h2>
                    <p class="text-sm" style="color:#62744f;">Manage all events, expenses, and attendees</p>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="card p-5">
                    <div class="text-xs font-medium mb-1" style="color:#62744f;">Total Events</div>
                    <div class="text-2xl font-bold" style="color:#353f2d;"><?php echo $totalEvents; ?></div>
                </div>
                <div class="card p-5">
                    <div class="text-xs font-medium mb-1" style="color:#62744f;">Total Users</div>
                    <div class="text-2xl font-bold" style="color:#353f2d;"><?php echo count($users); ?></div>
                </div>
                <div class="card p-5">
                    <div class="text-xs font-medium mb-1" style="color:#62744f;">Total Budget</div>
                    <div class="text-2xl font-bold" style="color:#353f2d;">$<?php echo number_format($totalBudget); ?></div>
                </div>
                <div class="card p-5">
                    <div class="text-xs font-medium mb-1" style="color:#62744f;">Total Attendees</div>
                    <div class="text-2xl font-bold" style="color:#353f2d;"><?php echo number_format($totalAttendees); ?></div>
                </div>
            </div>

            <!-- Budget Overview -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-bold mb-4" style="color:#353f2d;">Budget Overview</h3>
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="p-4 rounded-2xl" style="background:#faf9f6; border:1px solid #e8ebe3;">
                        <div class="text-xs mb-1" style="color:#62744f;">Total Budget</div>
                        <div class="text-xl font-bold" style="color:#353f2d;">$<?php echo number_format($totalBudget, 2); ?></div>
                    </div>
                    <div class="p-4 rounded-2xl" style="background:#faf9f6; border:1px solid #e8ebe3;">
                        <div class="text-xs mb-1" style="color:#62744f;">Total Spent</div>
                        <div class="text-xl font-bold text-red-600">$<?php echo number_format($totalSpent, 2); ?></div>
                    </div>
                    <div class="p-4 rounded-2xl" style="background:#faf9f6; border:1px solid #e8ebe3;">
                        <div class="text-xs mb-1" style="color:#62744f;">Remaining</div>
                        <div class="text-xl font-bold" style="color:#1a472a;">$<?php echo number_format($remainingBudget, 2); ?></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between mb-2">
                        <span class="text-sm font-semibold" style="color:#4d5b3f;">Budget Utilization</span>
                        <span class="text-sm font-bold" style="color:#1a472a;"><?php echo round($budgetUtilization); ?>%</span>
                    </div>
                    <div class="w-full rounded-full h-2.5" style="background:#e8ebe3;">
                        <div class="progress-fill rounded-full h-2.5" style="width:<?php echo $budgetUtilization; ?>%; background:#16a34a;"></div>
                    </div>
                </div>
            </div>

            <!-- All Events -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-bold mb-4" style="color:#353f2d;">All Events</h3>
                <?php if (empty($events)): ?>
                <div class="text-center py-12">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#f4f7f2;">
                        <i class="fas fa-calendar text-2xl" style="color:#b8c4a9;"></i>
                    </div>
                    <h4 class="text-base font-bold mb-1" style="color:#4d5b3f;">No events yet</h4>
                    <p class="text-sm" style="color:#62744f;">Events created by users will appear here.</p>
                </div>
                <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($events as $event):
                        $spent = $event['total_spent'] ?? 0;
                        $budget = $event['budget'] ?? 0;
                        $remaining = $budget - $spent;
                        $utilization = $budget > 0 ? min(100, ($spent / $budget) * 100) : 0;
                        $attendees = $event['attendees'] ?? 0;
                    ?>
                    <div class="p-5 rounded-2xl" style="background:#faf9f6; border:1px solid #e8ebe3;">
                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h4 class="text-base font-bold" style="color:#353f2d;"><?php echo htmlspecialchars($event['title']); ?></h4>
                                <p class="text-xs mt-0.5" style="color:#62744f;">by <?php echo htmlspecialchars($event['user_name'] ?? 'Unknown'); ?></p>
                            </div>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" style="background:#dcfce7; color:#15803d;">Upcoming</span>
                        </div>
                        <div class="grid grid-cols-4 gap-3 mb-3">
                            <div class="p-2 rounded-xl text-center" style="background:#fff; border:1px solid #e8ebe3;">
                                <div class="text-xs" style="color:#62744f;">Budget</div>
                                <div class="text-sm font-bold" style="color:#353f2d;">$<?php echo number_format($budget); ?></div>
                            </div>
                            <div class="p-2 rounded-xl text-center" style="background:#fff; border:1px solid #e8ebe3;">
                                <div class="text-xs" style="color:#62744f;">Spent</div>
                                <div class="text-sm font-bold text-red-600">$<?php echo number_format($spent, 2); ?></div>
                            </div>
                            <div class="p-2 rounded-xl text-center" style="background:#fff; border:1px solid #e8ebe3;">
                                <div class="text-xs" style="color:#62744f;">Remaining</div>
                                <div class="text-sm font-bold" style="color:#1a472a;">$<?php echo number_format($remaining, 2); ?></div>
                            </div>
                            <div class="p-2 rounded-xl text-center" style="background:#fff; border:1px solid #e8ebe3;">
                                <div class="text-xs" style="color:#62744f;">Attendees</div>
                                <div class="text-sm font-bold" style="color:#353f2d;"><?php echo number_format($attendees); ?></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-xs font-semibold" style="color:#4d5b3f;">Budget Used</span>
                                <span class="text-xs font-bold" style="color:#16a34a;"><?php echo round($utilization); ?>%</span>
                            </div>
                            <div class="w-full rounded-full h-2" style="background:#e8ebe3;">
                                <div class="progress-fill rounded-full h-2" style="width:<?php echo $utilization; ?>%; background:#16a34a;"></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Users Table -->
            <div class="card p-6">
                <h3 class="text-lg font-bold mb-4" style="color:#353f2d;">All Users</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr style="border-bottom:2px solid #e8ebe3;">
                                <th class="text-left py-3 px-2 font-semibold" style="color:#4d5b3f;">Name</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color:#4d5b3f;">Email</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color:#4d5b3f;">Role</th>
                                <th class="text-left py-3 px-2 font-semibold" style="color:#4d5b3f;">Joined</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr style="border-bottom:1px solid #f4f7f2;">
                                <td class="py-3 px-2 font-medium" style="color:#353f2d;"><?php echo htmlspecialchars($u['name']); ?></td>
                                <td class="py-3 px-2" style="color:#62744f;"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td class="py-3 px-2">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-semibold <?php echo $u['role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700'; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td class="py-3 px-2" style="color:#62744f;"><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

</body>
</html>