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

// Stats
$totalEvents = count($events);
$upcomingEvents = count(array_filter($events, fn($e) => ($e['date'] ?? '') >= date('Y-m-d')));
$totalBudget = array_sum(array_column($events, 'budget'));
$totalSpent = array_sum(array_column($events, 'total_spent'));
$totalAttendees = array_sum(array_column($events, 'attendees'));
$remainingBudget = $totalBudget - $totalSpent;
$budgetUtilization = $totalBudget > 0 ? min(100, ($totalSpent / $totalBudget) * 100) : 0;

// Search handling
$search = trim($_GET['search'] ?? '');
if ($search) {
    $events = array_filter($events, function($event) use ($search) {
        $term = strtolower($search);
        return strpos(strtolower($event['title']), $term) !== false ||
               strpos(strtolower($event['user_name'] ?? ''), $term) !== false ||
               strpos($event['date'], $term) !== false;
    });
}

// Update stats for filtered results
$totalEvents = count($events);
$upcomingEvents = count(array_filter($events, fn($e) => ($e['date'] ?? '') >= date('Y-m-d')));

// All users
$users = $pdo->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();

// Calendar data
$today = date('Y-m-d');
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

if ($currentMonth < 1) { $currentMonth = 12; $currentYear--; }
if ($currentMonth > 12) { $currentMonth = 1; $currentYear++; }

$monthName = date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfWeek = (int)date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear));

function getEventColor($status, $date, $today) {
    if ($status === 'completed' || $date < $today) return '#E8E8E8';
    if ($status === 'ongoing' || $date === $today) return '#DFF2F2';
    return '#CFE1D1';
}

$calendarEvents = [];
if (!empty($events)) {
    foreach ($events as $evt) {
        $calendarEvents[] = [
            'id' => $evt['id'],
            'title' => $evt['title'],
            'date' => $evt['date'],
            'customer' => $evt['customer_name'] ?? ($evt['user_name'] ?? 'N/A'),
            'attendees' => (int)($evt['attendees'] ?? 0),
            'max_attendees' => (int)($evt['max_attendees'] ?? 200),
            'description' => $evt['description'] ?? '',
            'status' => $evt['status'] ?? 'planned',
            'color' => getEventColor($evt['status'] ?? 'planned', $evt['date'], $today)
        ];
    }
}

// Upcoming events for month
$upcomingMonthEvents = array_filter($calendarEvents, function($e) use ($currentMonth, $currentYear, $today) {
    $d = date('Y-m-d', strtotime($e['date']));
    return (int)date('n', strtotime($d)) === $currentMonth &&
           (int)date('Y', strtotime($d)) === $currentYear &&
           $d >= $today;
});
usort($upcomingMonthEvents, fn($a, $b) => strcmp($a['date'], $b['date']));

// Prev/Next
$prevMonth = $currentMonth - 1; $prevYear = $currentYear;
$nextMonth = $currentMonth + 1; $nextYear = $currentYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
$prevLink = "?month=$prevMonth&year=$prevYear";
$nextLink = "?month=$nextMonth&year=$nextYear";
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
        .progress-fill {
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
            background: #16a34a;
            height: 100%;
            border-radius: 9999px;
        }
        .divider { border-top: 1px solid #e8ebe3; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: #c5d8bf; border-radius: 10px; }

        /* Calendar Styles - copied from user dashboard */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: 10px;
            grid-auto-rows: minmax(120px, auto);
        }
        .calendar-day-header {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #62744f;
            padding: 10px 0 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .calendar-day {
            min-height: 120px;
            border-radius: 16px;
            background: #ffffff;
            border: 1px solid #e8ebe3;
            padding: 10px;
            position: relative;
            transition: all 0.25s ease;
            display: flex;
            flex-direction: column;
        }
        .calendar-day:hover { background: #f4f7f2; }
        .calendar-day.today {
            border: 2px solid #1a472a;
            background: #eff7ea;
        }
        .calendar-day.today .day-number {
            font-weight: 800;
            color: #1a472a;
            background: #d8f3dc;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .calendar-day.empty {
            background: transparent;
            border: none;
        }
        .day-number {
            font-size: 0.9rem;
            font-weight: 700;
            color: #353f2d;
            margin-bottom: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f4f7f2;
        }
        .day-events {
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-top: 4px;
            overflow: hidden;
        }
        .event-pill {
            font-size: 0.75rem;
            padding: 6px 9px;
            border-radius: 9999px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            border: 1px solid rgba(0,0,0,0.06);
            font-weight: 600;
            color: #2d4934;
            background: rgba(207, 225, 209, 0.45);
        }
        .event-pill:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(53,63,45,0.12);
        }
        .event-pill.hidden-filter { display: none !important; }
        .search-input-wrapper {
            position: relative;
            flex: 1;
        }
        .search-input-wrapper .search-input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
            pointer-events: none;
        }
        .search-input-wrapper input {
            padding-left: 42px;
        }

        .event-popover {
            position: absolute;
            z-index: 200;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 12px 40px rgba(53,63,45,0.18);
            padding: 16px;
            min-width: 220px;
            display: none;
            border: 1px solid #e8ebe3;
        }
        .event-popover.active { display: block; }
        .event-popover::before {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 24px;
            width: 16px;
            height: 16px;
            background: #ffffff;
            transform: rotate(45deg);
            border-right: 1px solid #e8ebe3;
            border-bottom: 1px solid #e8ebe3;
        }
        .popover-title {
            font-weight: 700;
            font-size: 0.95rem;
            color: #1a472a;
            margin-bottom: 8px;
        }
        .popover-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.8rem;
            color: #62744f;
            margin-bottom: 4px;
        }
        .popover-row i { width: 14px; text-align: center; color: #7aa370; }

        .filter-dropdown {
            position: absolute;
            right: 0;
            top: 110%;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(53,63,45,0.12);
            border: 1px solid #e8ebe3;
            padding: 12px;
            min-width: 180px;
            display: none;
            z-index: 100;
        }
        .filter-dropdown.active { display: block; }
        .filter-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 0.85rem;
            color: #353f2d;
            transition: background 0.15s;
        }
        .filter-option:hover { background: #f4f7f2; }
        .filter-option input { accent-color: #1a472a; }

        .upcoming-list {
            max-height: 380px;
            overflow-y: auto;
            padding-right: 4px;
        }
        .upcoming-card {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #ffffff;
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(53,63,45,0.05);
            border: 1px solid #f0f0ec;
            transition: all 0.2s ease;
        }
        .upcoming-card:hover {
            box-shadow: 0 4px 14px rgba(53,63,45,0.1);
            transform: translateY(-1px);
        }
        .upcoming-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #d8f3dc;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .upcoming-icon i { color: #1a472a; font-size: 0.9rem; }
    </style>
</head>
<body class="min-h-screen flex flex-col">

    <!-- Top Banner (Gradient like user dashboard) -->
    <nav class="fixed top-0 left-0 right-0 z-50 shadow-sm" style="background: linear-gradient(135deg, #3d5a40, #4a6b50);">
        <div class="flex items-center justify-between px-6 py-4">
            <a href="admin_dashboard.php" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center shadow-lg" style="background: linear-gradient(135deg, #1a472a, #2d6a4f);">
                    <i class="fas fa-shield-alt text-white text-lg"></i>
                </div>
                <span class="text-xl font-bold tracking-wide text-white">CAVENDIA ADMIN</span>
            </a>
            <div class="flex items-center gap-5">
                <span class="text-sm hidden sm:inline font-medium text-white/90">Welcome, <?php echo htmlspecialchars($userName); ?>!</span>
                <a href="index.php" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-xl font-semibold text-sm transition-colors flex items-center gap-2 shadow-sm">
                    <i class="fas fa-sign-out-alt text-xs"></i> Logout
                </a>

            </div>
        </div>
    </nav>

    <div class="flex pt-16 min-h-screen">
        <!-- Sidebar (Gradient like user) -->
        <aside class="fixed left-0 top-16 bottom-0 w-64 overflow-y-auto shadow-lg" style="background: linear-gradient(180deg, #d8f3dc 0%, #c8e6c8 100%);">
            <div class="p-6 pt-8">
                <div class="mb-8 px-2">
                    <span class="text-xs font-bold uppercase tracking-wider block mb-2" style="color:#1a472a;">Admin Portal</span>
                </div>
                <nav class="space-y-2">
                    <a href="admin_dashboard.php" class="sidebar-item active">
                        <i class="fas fa-th-large"></i><span>Dashboard</span>
                    </a>
<a href="#events-section" class="sidebar-item events-link" onclick="scrollToEvents()">
                        <i class="fas fa-calendar-alt"></i><span>Events</span>
                    </a>
                    <a href="#" class="sidebar-item">
                        <i class="fas fa-users"></i><span>Users</span>
                    </a>
                    <a href="#" class="sidebar-item">
                        <i class="fas fa-chart-bar"></i><span>Analytics</span>
                    </a>
                    <a href="#" class="sidebar-item">
                        <i class="fas fa-cog"></i><span>Settings</span>
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8 min-h-screen" style="margin-left:16rem; background:#f6f7f4;">

            <!-- Welcome -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold" style="color:#353f2d;">Welcome, <?php echo htmlspecialchars($userName); ?>!</h1>
            </div>

            <!-- Stats Row (like user, with icons) -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">
                <div class="card p-6">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#dcfce7;">
                            <i class="fas fa-calendar text-lg" style="color:#16a34a;"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold" style="color:#353f2d;"><?php echo number_format($totalEvents); ?></div>
                            <div class="text-xs font-medium" style="color:#62744f;">Total Events</div>
                        </div>
                    </div>
                </div>
                <div class="card p-6">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#dbeafe;">
                            <i class="fas fa-users text-lg" style="color:#3b82f6;"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold" style="color:#353f2d;"><?php echo number_format(count($users)); ?></div>
                            <div class="text-xs font-medium" style="color:#62744f;">Total Users</div>
                        </div>
                    </div>
                </div>
                <div class="card p-6">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#f3e8ff;">
                            <i class="fas fa-dollar-sign text-lg" style="color:#9333ea;"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold" style="color:#353f2d;">$<?php echo number_format($totalBudget); ?></div>
                            <div class="text-xs font-medium" style="color:#62744f;">Total Budget</div>
                        </div>
                    </div>
                </div>
                <div class="card p-6">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#ffedd5;">
                            <i class="fas fa-calendar-check text-lg" style="color:#f97316;"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold" style="color:#353f2d;"><?php echo number_format($totalAttendees); ?></div>
                            <div class="text-xs font-medium" style="color:#62744f;">Total Attendees</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Budget Overview (like user) -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-bold mb-4" style="color:#353f2d;">Budget Overview</h3>
                <div class="divider mb-4"></div>
                <div class="grid grid-cols-3 gap-6 mb-5">
                    <div>
                        <div class="text-xs font-medium mb-1" style="color:#62744f;">Total Budget</div>
                        <div class="text-xl font-bold" style="color:#353f2d;">$<?php echo number_format($totalBudget); ?></div>
                    </div>
                    <div>
                        <div class="text-xs font-medium mb-1" style="color:#62744f;">Total Spent</div>
                        <div class="text-xl font-bold text-red-600">$<?php echo number_format($totalSpent); ?></div>
                    </div>
                    <div>
                        <div class="text-xs font-medium mb-1" style="color:#62744f;">Remaining</div>
                        <div class="text-xl font-bold" style="color:#1a472a;">$<?php echo number_format($remainingBudget); ?></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-semibold" style="color:#4d5b3f;">Budget Utilization</span>
                        <span class="text-sm font-bold" style="color:#1a472a;"><?php echo round($budgetUtilization); ?>%</span>
                    </div>
                    <div class="w-full rounded-full h-3" style="background:#e8ebe3;">
                        <div class="progress-fill" style="width:<?php echo $budgetUtilization; ?>%;"></div>
                    </div>
                </div>
            </div>

            <!-- Events Interface: Calendar + Upcoming + Management -->
            <div id="events-section">

            <!-- Calendar Section (like user, all events) -->
            <div id="calendar-section" class="card p-6 mb-6">

                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold" style="color:#353f2d;">Event Calendar (All Events)</h3>
                    <div class="flex items-center gap-3">
                        <a href="<?php echo $prevLink; ?>" class="w-9 h-9 rounded-xl flex items-center justify-center transition-all hover:shadow-md" style="background:#f4f7f2; color:#4d5b3f;">
                            <i class="fas fa-chevron-left text-sm"></i>
                        </a>
                        <span class="text-base font-bold min-w-[130px] text-center" style="color:#1a472a;"><?php echo $monthName; ?></span>
                        <a href="<?php echo $nextLink; ?>" class="w-9 h-9 rounded-xl flex items-center justify-center transition-all hover:shadow-md" style="background:#f4f7f2; color:#4d5b3f;">
                            <i class="fas fa-chevron-right text-sm"></i>
                        </a>
                        <div class="relative">
                            <button onclick="toggleFilter()" class="w-9 h-9 rounded-xl flex items-center justify-center transition-all hover:shadow-md" style="background:#f4f7f2; color:#4d5b3f;" title="Filter">
                                <i class="fas fa-filter text-sm"></i>
                            </button>
                            <div id="filterDropdown" class="filter-dropdown">
                                <label class="filter-option">
                                    <input type="checkbox" checked onchange="applyFilter()" data-filter="upcoming">
                                    <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#CFE1D1;"></span>
                                    Upcoming
                                </label>
                                <label class="filter-option">
                                    <input type="checkbox" checked onchange="applyFilter()" data-filter="ongoing">
                                    <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#DFF2F2;"></span>
                                    Ongoing
                                </label>
                                <label class="filter-option">
                                    <input type="checkbox" checked onchange="applyFilter()" data-filter="completed">
                                    <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#E8E8E8;"></span>
                                    Completed
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-4 mb-5">
                    <div class="flex items-center gap-2">
                        <span style="display:inline-block;width:14px;height:14px;border-radius:4px;background:#CFE1D1;"></span>
                        <span class="text-xs font-medium" style="color:#62744f;">Upcoming</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span style="display:inline-block;width:14px;height:14px;border-radius:4px;background:#DFF2F2;"></span>
                        <span class="text-xs font-medium" style="color:#62744f;">Ongoing</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span style="display:inline-block;width:14px;height:14px;border-radius:4px;background:#E8E8E8;"></span>
                        <span class="text-xs font-medium" style="color:#62744f;">Completed</span>
                    </div>
                </div>
                <div class="calendar-grid">
                    <?php
                    $dayHeaders = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                    foreach ($dayHeaders as $dh): ?>
                        <div class="calendar-day-header"><?php echo $dh; ?></div>
                    <?php endforeach; ?>
                    <?php for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>
                    <?php for ($day = 1; $day <= $daysInMonth; $day++):
                        $dayStr = str_pad($day, 2, '0', STR_PAD_LEFT);
                        $monthStr = str_pad($currentMonth, 2, '0', STR_PAD_LEFT);
                        $fullDate = "$currentYear-$monthStr-$dayStr";
                        $isToday = ($fullDate === $today);
                        $dayEvents = array_filter($calendarEvents, fn($e) => $e['date'] === $fullDate);
                    ?>
                        <div class="calendar-day <?php echo $isToday ? 'today' : ''; ?>" data-date="<?php echo $fullDate; ?>">
                            <div class="day-number"><?php echo $day; ?></div>
                            <div class="day-events">
                                <?php foreach ($dayEvents as $de): ?>
                                    <div class="event-pill"
                                         style="background:<?php echo $de['color']; ?>;"
                                         data-status="<?php echo $de['status'] === 'completed' || $de['date'] < $today ? 'completed' : ($de['status'] === 'ongoing' || $de['date'] === $today ? 'ongoing' : 'upcoming'); ?>"
                                         data-title="<?php echo htmlspecialchars($de['title']); ?>"
                                         data-customer="<?php echo htmlspecialchars($de['customer']); ?>"
                                         data-attendees="<?php echo $de['attendees']; ?>"
                                         data-max="<?php echo $de['max_attendees']; ?>">
                                        <?php echo htmlspecialchars(substr($de['title'], 0, 20)); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div id="eventPopover" class="event-popover">
                    <div class="popover-title" id="popoverTitle"></div>
                    <div class="popover-row"><i class="fas fa-user"></i><span id="popoverCustomer"></span></div>
                    <div class="popover-row"><i class="fas fa-users"></i><span id="popoverAttendees"></span></div>
                </div>
            </div>

            <!-- Upcoming Events This Month (like user) -->
            <div class="card p-6 mb-6">

                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-bold" style="color:#353f2d;">Upcoming Events This Month</h3>
                    <span class="text-xs font-semibold px-3 py-1 rounded-full" style="background:#f4f7f2; color:#62744f;">
                        <?php echo count($upcomingMonthEvents); ?> events
                    </span>
                </div>
                <div class="divider mb-4"></div>
                <div class="upcoming-list">
                    <?php if (!empty($upcomingMonthEvents)): ?>
                        <?php foreach ($upcomingMonthEvents as $ue): ?>
                            <div class="upcoming-card">
                                <div class="flex items-center gap-4">
                                    <div class="upcoming-icon">
                                        <i class="fas fa-calendar-day"></i>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-sm" style="color:#353f2d;"><?php echo htmlspecialchars($ue['title']); ?></h4>
                                        <p class="text-xs mt-0.5" style="color:#62744f;"><?php echo htmlspecialchars($ue['description'] ?: 'No description'); ?></p>
                                    </div>
                                </div>
                                <div class="text-right shrink-0 ml-4">
                                    <div class="text-xs font-semibold" style="color:#1a472a;"><?php echo $ue['date']; ?></div>
                                    <div class="text-xs mt-1" style="color:#62744f;">
                                        <?php echo $ue['attendees']; ?>/<?php echo $ue['max_attendees']; ?> attendees
                                    </div>
                                    <div class="w-24 h-1.5 rounded-full mt-1.5 ml-auto" style="background:#e8ebe3;">
                                        <div class="h-1.5 rounded-full" style="width:<?php echo min(100, ($ue['max_attendees']>0 ? ($ue['attendees']/$ue['max_attendees'])*100 : 0)); ?>%; background:#7aa370;"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-calendar-check text-3xl mb-3" style="color:#c5d8bf;"></i>
                            <p class="text-sm font-medium" style="color:#62744f;">No upcoming events this month</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

<!-- Event Management Section -->
            <div class="card p-6 mb-6">

                <div class="flex items-center justify-between mb-4 gap-4 flex-wrap">
                    <div>
                        <h3 class="text-lg font-bold" style="color:#353f2d;">Event Management</h3>
                        <p class="text-sm" style="color:#62744f;"><?php echo $totalEvents; ?> events<?php if ($search): ?> • <span class="font-semibold text-blue-600">"<?php echo htmlspecialchars($search); ?>"</span><?php endif; ?></p>
                    </div>
                    <button onclick="openEventModal()" class="px-5 py-2.5 bg-green-600 text-white rounded-xl font-semibold text-sm hover:bg-green-700 transition-colors flex items-center gap-2 shadow-sm">
                        <i class="fas fa-plus text-xs"></i> Create Event
                    </button>
                </div>
                <div class="divider mb-4"></div>
                
                <!-- Search Bar -->
                <div class="mb-4">
                    <form method="GET" class="flex flex-col sm:flex-row gap-2 items-stretch">
                        <div class="search-input-wrapper">
                            <span class="search-input-icon"><i class="fas fa-search"></i></span>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search events by title, date, or user..." class="w-full px-4 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-green-500 focus:border-transparent text-sm">
                        </div>
                        <button type="submit" class="px-5 py-2.5 bg-slate-700 text-white rounded-xl hover:bg-slate-800 text-sm font-medium flex items-center justify-center gap-2">
                            <i class="fas fa-search text-xs"></i> Search
                        </button>
                        <?php if ($search): ?>
                            <a href="admin_dashboard.php" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-xl hover:bg-gray-300 text-sm font-medium flex items-center justify-center">
                                Clear
                            </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Events Table -->
                <?php if (!empty($events)): ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr style="border-bottom:2px solid #e8ebe3;">
                                    <th class="text-left py-3 px-3 font-semibold" style="color:#4d5b3f;">Event</th>
                                    <th class="text-left py-3 px-3 font-semibold" style="color:#4d5b3f;">Date</th>
                                    <th class="text-left py-3 px-3 font-semibold" style="color:#4d5b3f;">Location</th>
                                    <th class="text-left py-3 px-3 font-semibold" style="color:#4d5b3f;">Budget</th>
                                    <th class="text-left py-3 px-3 font-semibold" style="color:#4d5b3f;">Status</th>
                                    <th class="text-right py-3 px-3 font-semibold" style="color:#4d5b3f;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $event): ?>
                                <tr style="border-bottom:1px solid #f4f7f2;" class="hover:bg-green-50 transition-colors">
                                    <td class="py-3 px-3">
                                        <div class="font-semibold" style="color:#353f2d;"><?php echo htmlspecialchars($event['title']); ?></div>
                                        <div class="text-xs" style="color:#62744f;"><?php echo htmlspecialchars(substr($event['description'] ?? '', 0, 50)); ?><?php echo strlen($event['description'] ?? '') > 50 ? '...' : ''; ?></div>
                                    </td>
                                    <td class="py-3 px-3" style="color:#62744f;"><?php echo !empty($event['date']) ? date('M j, Y', strtotime($event['date'])) : 'N/A'; ?></td>
                                    <td class="py-3 px-3" style="color:#62744f;"><?php echo htmlspecialchars($event['location'] ?? 'N/A'); ?></td>
                                    <td class="py-3 px-3 font-medium" style="color:#353f2d;">$<?php echo number_format($event['budget'] ?? 0); ?></td>
                                    <td class="py-3 px-3">
                                        <?php 
                                        $status = $event['status'] ?? 'planned';
                                        $statusColors = ['planned' => '#dcfce7|#15803d', 'ongoing' => '#dbeafe|#1d4ed8', 'completed' => '#e8e8e8|#6b7280', 'cancelled' => '#fee2e2|#dc2626'];
                                        $colors = explode('|', $statusColors[$status] ?? 'dcfce7|#15803d');
                                        ?>
                                        <span class="px-2.5 py-1 rounded-full text-xs font-semibold" style="background:<?php echo $colors[0]; ?>; color:<?php echo $colors[1]; ?>;">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="admin_event.php?id=<?php echo htmlspecialchars($event['id']); ?>" class="w-8 h-8 rounded-lg flex items-center justify-center bg-green-100 text-green-600 hover:bg-green-200 transition-colors" title="View">
                                                <i class="fas fa-eye text-xs"></i>
                                            </a>
                                            <button onclick="editEvent('<?php echo htmlspecialchars($event['id']); ?>', '<?php echo htmlspecialchars($event['title']); ?>', '<?php echo $event['date'] ?? ''; ?>', '<?php echo htmlspecialchars($event['location'] ?? ''); ?>', '<?php echo htmlspecialchars($event['description'] ?? ''); ?>', '<?php echo $event['budget'] ?? 0; ?>', '<?php echo $event['max_attendees'] ?? 200; ?>', '<?php echo $status; ?>')" class="w-8 h-8 rounded-lg flex items-center justify-center bg-blue-100 text-blue-600 hover:bg-blue-200 transition-colors" title="Edit">
                                                <i class="fas fa-edit text-xs"></i>
                                            </button>
                                            <button onclick="deleteEvent('<?php echo htmlspecialchars($event['id']); ?>', '<?php echo htmlspecialchars($event['title']); ?>')" class="w-8 h-8 rounded-lg flex items-center justify-center bg-red-100 text-red-600 hover:bg-red-200 transition-colors" title="Delete">
                                                <i class="fas fa-trash text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-10">
                        <i class="fas fa-calendar-plus text-4xl mb-4" style="color:#c5d8bf;"></i>
                        <p class="text-base font-semibold mb-1" style="color:#62744f;">No events yet</p>
                        <p class="text-sm" style="color:#8b9a7a;">Click "Create Event" to add your first event.</p>
                    </div>
                <?php endif; ?>
            </div>
            </div>
            </div>

            <!-- Users Table (admin-specific) -->
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

    <!-- JS (copied from user dashboard) -->
    <script>
        function toggleFilter() {
            document.getElementById('filterDropdown').classList.toggle('active');
        }
        function applyFilter() {
            const checks = document.querySelectorAll('#filterDropdown input[type="checkbox"]');
            const active = {};
            checks.forEach(c => active[c.dataset.filter] = c.checked);
            document.querySelectorAll('.event-pill').forEach(pill => {
                const s = pill.dataset.status;
                if (active[s]) pill.classList.remove('hidden-filter');
                else pill.classList.add('hidden-filter');
            });
        }
        document.addEventListener('click', (e) => {
            const dd = document.getElementById('filterDropdown');
            const btn = e.target.closest('button[onclick="toggleFilter()"]');
            if (!btn && dd.classList.contains('active')) dd.classList.remove('active');
        });

        const popover = document.getElementById('eventPopover');
        const pTitle = document.getElementById('popoverTitle');
        const pCustomer = document.getElementById('popoverCustomer');
        const pAttendees = document.getElementById('popoverAttendees');

        document.querySelectorAll('.event-pill').forEach(pill => {
            pill.addEventListener('click', (e) => {
                e.stopPropagation();
                pTitle.textContent = pill.dataset.title;
                pCustomer.textContent = pill.dataset.customer;
                pAttendees.textContent = pill.dataset.attendees + '/' + pill.dataset.max + ' attendees';
                const rect = pill.getBoundingClientRect();
                const container = document.getElementById('calendar-section').getBoundingClientRect();
                popover.style.left = (rect.left - container.left + 10) + 'px';
                popover.style.top = (rect.top - container.top - popover.offsetHeight - 10) + 'px';
                popover.classList.add('active');
            });
        });
document.addEventListener('click', () => popover.classList.remove('active'));

        // Smooth scroll to Events section
        function scrollToEvents() {
            document.querySelectorAll('.sidebar-item').forEach(item => item.classList.remove('active'));
            event.target.closest('.sidebar-item').classList.add('active');
            const eventsSection = document.getElementById('events-section');
            if (eventsSection) {
                eventsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        // Update active nav on scroll (optional)
        window.addEventListener('scroll', () => {
            const scrollPos = window.scrollY + 200;
            const eventsSection = document.getElementById('events-section');
            if (eventsSection && eventsSection.offsetTop <= scrollPos) {
                document.querySelectorAll('.sidebar-item').forEach(item => item.classList.remove('active'));
                document.querySelector('.events-link')?.classList.add('active');
            }
        });
    </script>

    <!-- Event Modal -->
    <div id="eventModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.6); backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:24px; max-width:520px; width:90%; max-height:90vh; overflow-y:auto; box-shadow:0 25px 50px rgba(0,0,0,0.3);">
            <div style="padding:28px;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                    <h2 id="modalTitle" style="font-size:1.5rem; font-weight:700; color:#353f2d; margin:0;">Create Event</h2>
                    <button onclick="closeEventModal()" style="background:transparent; border:none; font-size:1.5rem; cursor:pointer; color:#62744f;">&times;</button>
                </div>
                
                <form id="eventForm" method="POST" action="api.php?type=events" onsubmit="return handleEventSubmit(event)">
                    <input type="hidden" name="id" id="eventId" value="">
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:#4d5b3f; margin-bottom:6px;">Event Title *</label>
                        <input type="text" name="title" id="eventTitle" required placeholder="Enter event title" style="width:100%; padding:12px 16px; border:1.5px solid #e8ebe3; border-radius:12px; font-size:0.95rem;">
                    </div>
                    
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#4d5b3f; margin-bottom:6px;">Date *</label>
                            <input type="date" name="date" id="eventDate" required style="width:100%; padding:12px 16px; border:1.5px solid #e8ebe3; border-radius:12px; font-size:0.95rem;">
                        </div>
                        <div>
                            <label style="display:block; font-size:0.85rem; font-weight:600; color:#4d5b3f; margin-bottom:6px;">Max Attendees</label>
                            <input type="number" name="max_attendees" id="eventMaxAttendees" value="200" min="1" style="width:100%; padding:12px 16px; border:1.5px solid #e8ebe3; border-radius:12px; font-size:0.95rem;">
                        </div>
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:#4d5b3f; margin-bottom:6px;">Location</label>
                        <input type="text" name="location" id="eventLocation" placeholder="Enter location" style="width:100%; padding:12px 16px; border:1.5px solid #e8ebe3; border-radius:12px; font-size:0.95rem;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:#4d5b3f; margin-bottom:6px;">Budget ($)</label>
                        <input type="number" name="budget" id="eventBudget" value="0" min="0" step="0.01" style="width:100%; padding:12px 16px; border:1.5px solid #e8ebe3; border-radius:12px; font-size:0.95rem;">
                    </div>
                    
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:#4d5b3f; margin-bottom:6px;">Status</label>
                        <select name="status" id="eventStatus" style="width:100%; padding:12px 16px; border:1.5px solid #e8ebe3; border-radius:12px; font-size:0.95rem;">
                            <option value="planned">Planned</option>
                            <option value="ongoing">Ongoing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:0.85rem; font-weight:600; color:#4d5b3f; margin-bottom:6px;">Description</label>
                        <textarea name="description" id="eventDescription" rows="3" placeholder="Enter event description" style="width:100%; padding:12px 16px; border:1.5px solid #e8ebe3; border-radius:12px; font-size:0.95rem; resize:vertical;"></textarea>
                    </div>
                    
                    <div style="display:flex; gap:12px;">
                        <button type="button" onclick="closeEventModal()" style="flex:1; padding:14px 20px; background:transparent; border:1.5px solid #e8ebe3; color:#62744f; border-radius:16px; font-size:0.9rem; font-weight:600; cursor:pointer;">Cancel</button>
                        <button type="submit" style="flex:1; padding:14px 20px; background:#16a34a; border:none; color:white; border-radius:16px; font-size:0.9rem; font-weight:600; cursor:pointer;">Save Event</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Modal Functions
        function openEventModal() {
            document.getElementById('eventId').value = '';
            document.getElementById('eventTitle').value = '';
            document.getElementById('eventDate').value = '';
            document.getElementById('eventLocation').value = '';
            document.getElementById('eventDescription').value = '';
            document.getElementById('eventBudget').value = '0';
            document.getElementById('eventMaxAttendees').value = '200';
            document.getElementById('eventStatus').value = 'planned';
            document.getElementById('modalTitle').textContent = 'Create Event';
            document.getElementById('eventModal').style.display = 'flex';
        }

        function closeEventModal() {
            document.getElementById('eventModal').style.display = 'none';
        }

        function editEvent(id, title, date, location, description, budget, maxAttendees, status) {
            document.getElementById('eventId').value = id;
            document.getElementById('eventTitle').value = title;
            document.getElementById('eventDate').value = date;
            document.getElementById('eventLocation').value = location;
            document.getElementById('eventDescription').value = description;
            document.getElementById('eventBudget').value = budget;
            document.getElementById('eventMaxAttendees').value = maxAttendees;
            document.getElementById('eventStatus').value = status;
            document.getElementById('modalTitle').textContent = 'Edit Event';
            document.getElementById('eventModal').style.display = 'flex';
        }

        function deleteEvent(id, title) {
            if (confirm('Are you sure you want to delete "' + title + '"? This action cannot be undone.')) {
                fetch('api.php?type=events', {
                    method: 'DELETE',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Event deleted successfully!');
                        location.reload();
                    } else {
                        alert(data.error || 'Failed to delete event.');
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('An error occurred.');
                });
            }
        }

        function handleEventSubmit(e) {
            e.preventDefault();
            const form = document.getElementById('eventForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            const isEdit = data.id !== '';
            const method = isEdit ? 'PUT' : 'POST';
            
            fetch('api.php?type=events', {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    alert(isEdit ? 'Event updated successfully!' : 'Event created successfully!');
                    closeEventModal();
                    location.reload();
                } else {
                    alert(result.error || 'Failed to save event.');
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred.');
            });
            
            return false;
        }

        // Close modal on outside click
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) closeEventModal();
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEventModal();
        });
    </script>
</body>
</html>