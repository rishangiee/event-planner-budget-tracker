<?php
require_once 'config/config.php';
requireUser();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Fetch events created by admin/anyone (public events that are not cancelled)
// NOTE: Current schema in this project uses events.status. Admin pages use admin_event.php for management.
// We show events with status != 'cancelled'.
$stmt = $pdo->prepare("
    SELECT e.*,
           COALESCE(SUM(ex.amount), 0) as total_spent
    FROM events e
    LEFT JOIN expenses ex ON e.id = ex.event_id
    WHERE e.status != 'cancelled'
    GROUP BY e.id
    ORDER BY e.date ASC
");
$stmt->execute();
$events = $stmt->fetchAll();

// Optional search
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $term = strtolower($search);
    $events = array_values(array_filter($events, function($e) use ($term) {
        return (
            str_contains(strtolower($e['title'] ?? ''), $term) ||
            str_contains(strtolower($e['description'] ?? ''), $term) ||
            str_contains(strtolower($e['location'] ?? ''), $term) ||
            str_contains(strtolower($e['date'] ?? ''), $term)
        );
    }));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Events (Admin) - CAVENDIA</title>
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
            border-radius: 20px;
            box-shadow: 0 2px 12px rgba(53,63,45,0.06);
            transition: all 0.2s ease;
        }
        .card:hover { box-shadow: 0 4px 20px rgba(53,63,45,0.1); }
        .divider { border-top: 1px solid #e8ebe3; margin: 1.5rem 0; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #c5d8bf; border-radius: 10px; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

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
    <aside class="fixed left-0 top-16 bottom-0 w-64 overflow-y-auto shadow-lg" style="background: linear-gradient(180deg, #d8f3dc 0%, #c8e6c8 100%);">
        <div class="p-6 pt-8">
            <div class="mb-8 px-2">
                <span class="text-xs font-bold uppercase tracking-wider block mb-2" style="color:#1a472a;">Event planner User Portal</span>
            </div>
            <nav class="space-y-2">
                <a href="dashboard.php" class="sidebar-item">
                    <i class="fas fa-th-large"></i><span>Dashboard</span>
                </a>
                <a href="all_admin_events.php" class="sidebar-item active">
                    <i class="fas fa-users"></i><span>All Events</span>
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
            </nav>
        </div>
    </aside>

    <main class="flex-1 p-8 min-h-screen" style="margin-left:16rem; background:#f6f7f4;">

        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold" style="color:#353f2d;">All Events from Admin</h1>
            <p class="text-sm mt-2" style="color:#62744f;">Choose an event to view details and book it</p>
        </div>

        <div class="card p-6 mb-6">
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                    <h3 class="text-lg font-bold" style="color:#353f2d;">Search</h3>
                    <p class="text-sm" style="color:#62744f;">Filter events by title, date, location</p>
                </div>
            </div>
            <div class="divider"></div>
            <form method="GET" class="flex gap-3 flex-col sm:flex-row">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search events..." class="flex-1 px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm">
                <button type="submit" class="px-5 py-2.5 bg-slate-700 text-white rounded-xl hover:bg-slate-800 text-sm font-medium flex items-center gap-2 shadow-sm">
                    <i class="fas fa-search text-xs"></i> Search
                </button>
                <?php if ($search !== ''): ?>
                    <a href="all_admin_events.php" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 text-sm font-medium shadow-sm flex items-center gap-2">
                        <i class="fas fa-xmark text-xs"></i> Clear
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="card p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold" style="color:#353f2d;">Events List</h3>
                    <p class="text-sm" style="color:#62744f;">Total: <?php echo count($events); ?></p>
                </div>
            </div>
            <div class="divider"></div>

            <?php if (!empty($events)): ?>
                <?php foreach ($events as $event):
                    $spent = (float)($event['total_spent'] ?? 0);
                    $budget = (float)($event['budget'] ?? 0);
                    $remaining = $budget - $spent;
                    $util = $budget > 0 ? min(100, ($spent / $budget) * 100) : 0;
                ?>
                    <a href="event.php?id=<?php echo htmlspecialchars($event['id']); ?>" class="block p-4 rounded-xl mb-3 transition-all hover:shadow-md" style="background:#faf9f6; border:1px solid #e8ebe3;">
                        <div class="flex items-start justify-between mb-3 gap-4">
                            <div class="min-w-0">
                                <h4 class="text-base font-bold" style="color:#353f2d;"><?php echo htmlspecialchars($event['title']); ?></h4>
                                <p class="text-xs mt-0.5" style="color:#62744f;"><?php echo htmlspecialchars($event['description'] ?? 'No description'); ?></p>
                                <div class="flex flex-wrap gap-6 text-sm mt-3">
                                    <div><span class="text-xs" style="color:#62744f;">Date</span> <span class="font-semibold ml-1" style="color:#353f2d;"><?php echo htmlspecialchars($event['date']); ?></span></div>
                                    <div><span class="text-xs" style="color:#62744f;">Location</span> <span class="font-semibold ml-1" style="color:#353f2d;"><?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></span></div>
                                    <div><span class="text-xs" style="color:#62744f;">Budget</span> <span class="font-semibold ml-1" style="color:#353f2d;">Php <?php echo number_format($budget); ?></span></div>
                                </div>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold" style="background:#dcfce7; color:#15803d;"><?php echo htmlspecialchars($event['status'] ?? 'planned'); ?></span>
                        </div>

                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-xs font-semibold" style="color:#4d5b3f;">Budget Used</span>
                                <span class="text-xs font-bold" style="color:#16a34a;"><?php echo round($util); ?>%</span>
                            </div>
                            <div class="w-full rounded-full h-2" style="background:#e8ebe3;">
                                <div class="progress-fill rounded-full h-2" style="width:<?php echo $util; ?>%;"></div>
                            </div>
                            <div class="text-xs mt-2" style="color:#62744f;">
                                Remaining budget: Php <?php echo number_format(max(0, $remaining), 2); ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-10">
                    <i class="fas fa-calendar-plus text-4xl mb-4" style="color:#c5d8bf;"></i>
                    <p class="text-base font-semibold mb-1" style="color:#62744f;">No events found</p>
                    <p class="text-sm" style="color:#8b9a7a;">Try a different search.</p>
                </div>
            <?php endif; ?>
        </div>

    </main>
</div>

</body>
</html>

