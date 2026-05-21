<?php
require_once 'config/config.php';
requireUser();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Get user's events
$stmt = $pdo->prepare("
    SELECT e.*, COALESCE(SUM(ex.amount), 0) as total_spent
    FROM events e LEFT JOIN expenses ex ON e.id = ex.event_id
    WHERE e.user_id = ? GROUP BY e.id ORDER BY e.date ASC
");
$stmt->execute([$userId]);
$events = $stmt->fetchAll();

// Stats
$totalEvents = count($events);
$upcomingEvents = count(array_filter($events, fn($e) => $e['date'] >= date('Y-m-d')));
$totalBudget = array_sum(array_column($events, 'budget'));
$totalSpent = array_sum(array_column($events, 'total_spent'));
$totalAttendees = array_sum(array_column($events, 'attendees'));
$remainingBudget = $totalBudget - $totalSpent;
$budgetUtilization = $totalBudget > 0 ? min(100, ($totalSpent / $totalBudget) * 100) : 0;

// Use real values (zeros if no events)
$demoTotalEvents = $totalEvents;
$demoUpcoming = $upcomingEvents;
$demoBudget = $totalBudget;
$demoAttendees = $totalAttendees;
$demoEventBudget = $totalBudget;
$demoEventSpent = $totalSpent;
$demoEventRemaining = $remainingBudget;
$demoEventUtilization = $totalBudget > 0 ? round($budgetUtilization) : 0;

// ===================== CALENDAR DATA =====================
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
    return '#CFE1D1'; // upcoming / planned
}

$calendarEvents = [];
if (!empty($events)) {
    foreach ($events as $evt) {
        $calendarEvents[] = [
            'id' => $evt['id'],
            'title' => $evt['title'],
            'date' => $evt['date'],
            'customer' => $evt['customer_name'] ?? 'N/A',
            'attendees' => (int)($evt['attendees'] ?? 0),
            'max_attendees' => (int)($evt['max_attendees'] ?? 200),
            'description' => $evt['description'] ?? '',
            'status' => $evt['status'] ?? 'planned',
            'color' => getEventColor($evt['status'] ?? 'planned', $evt['date'], $today)
        ];
    }
}

// Upcoming events for selected month (today onwards)
$upcomingMonthEvents = array_filter($calendarEvents, function($e) use ($currentMonth, $currentYear, $today) {
    $d = date('Y-m-d', strtotime($e['date']));
    return (int)date('n', strtotime($d)) === $currentMonth &&
           (int)date('Y', strtotime($d)) === $currentYear &&
           $d >= $today;
});
usort($upcomingMonthEvents, fn($a, $b) => strcmp($a['date'], $b['date']));

// Prev / Next links
$prevMonth = $currentMonth - 1; $prevYear = $currentYear;
$nextMonth = $currentMonth + 1; $nextYear = $currentYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }
$prevLink = "?month=$prevMonth&year=$prevYear";
$nextLink = "?month=$nextMonth&year=$nextYear";
// =========================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CAVENDIA</title>
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

        /* ===== Calendar Styles ===== */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        .calendar-day-header {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #62744f;
            padding: 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .calendar-day {
            min-height: 100px;
            border-radius: 12px;
            background: #faf9f6;
            border: 1px solid #e8ebe3;
            padding: 6px;
            position: relative;
            transition: all 0.2s ease;
        }
        .calendar-day:hover { background: #f4f7f2; }
        .calendar-day.today {
            border: 2px solid #1a472a;
            background: #f4f7f2;
        }
        .calendar-day.today .day-number {
            font-weight: 800;
            color: #1a472a;
            background: #d8f3dc;
            width: 28px;
            height: 28px;
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
            font-size: 0.85rem;
            font-weight: 500;
            color: #353f2d;
            margin-bottom: 4px;
            display: inline-block;
        }
        .event-pill {
            font-size: 0.7rem;
            padding: 3px 8px;
            border-radius: 9999px;
            margin-bottom: 3px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            border: 1px solid rgba(0,0,0,0.04);
            font-weight: 500;
            color: #353f2d;
        }
        .event-pill:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 6px rgba(53,63,45,0.12);
        }
        .event-pill.hidden-filter { display: none !important; }

        /* Popover */
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

        /* Filter dropdown */
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

        /* Upcoming Events List */
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

    <!-- Top Banner -->
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
                    <a href="dashboard.php" class="sidebar-item active">
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
                </nav>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="flex-1 p-8 min-h-screen" style="margin-left:16rem; background:#f6f7f4;">

            <!-- Welcome Message -->
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold" style="color:#353f2d;">Welcome, <?php echo htmlspecialchars($userName); ?>!</h1>
            </div>

            <!-- Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-5 mb-6">
                <!-- Total Events -->
                <div class="card p-6">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#dcfce7;">
                            <i class="fas fa-calendar text-lg" style="color:#16a34a;"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold" style="color:#353f2d;"><?php echo number_format($demoTotalEvents); ?></div>
                            <div class="text-xs font-medium" style="color:#62744f;">Total Events</div>
                        </div>
                    </div>
                </div>
                <!-- Upcoming Events -->
                <div class="card p-6">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#dbeafe;">
                            <i class="fas fa-chart-line text-lg" style="color:#3b82f6;"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold" style="color:#353f2d;"><?php echo number_format($demoUpcoming); ?></div>
                            <div class="text-xs font-medium" style="color:#62744f;">Upcoming Events</div>
                        </div>
                    </div>
                </div>
                <!-- Total Budget -->
                <div class="card p-6">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#f3e8ff;">
                            <i class="fas fa-dollar-sign text-lg" style="color:#9333ea;"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold" style="color:#353f2d;">Php <?php echo number_format($demoBudget); ?></div>
                            <div class="text-xs font-medium" style="color:#62744f;">Total Budget</div>
                        </div>
                    </div>
                </div>
                <!-- Total Attendees -->
                <div class="card p-6">
                    <div class="flex items-center gap-4 mb-3">
                        <div class="w-12 h-12 rounded-xl flex items-center justify-center" style="background:#ffedd5;">
                            <i class="fas fa-users text-lg" style="color:#f97316;"></i>
                        </div>
                        <div>
                            <div class="text-2xl font-bold" style="color:#353f2d;"><?php echo number_format($demoAttendees); ?></div>
                            <div class="text-xs font-medium" style="color:#62744f;">Total Attendees</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Budget Overview -->
            <div class="card p-6 mb-6">
                <h3 class="text-lg font-bold mb-4" style="color:#353f2d;">Budget Overview</h3>
                <div class="divider mb-4"></div>
                <div class="grid grid-cols-3 gap-6 mb-5">
                    <div>
                        <div class="text-xs font-medium mb-1" style="color:#62744f;">Total Budget</div>
                        <div class="text-xl font-bold" style="color:#353f2d;">Php <?php echo number_format($demoEventBudget); ?></div>
                    </div>
                    <div>
                        <div class="text-xs font-medium mb-1" style="color:#62744f;">Total Spent</div>
                        <div class="text-xl font-bold text-red-600">Php <?php echo number_format($demoEventSpent); ?></div>
                    </div>
                    <div>
                        <div class="text-xs font-medium mb-1" style="color:#62744f;">Remaining</div>
                        <div class="text-xl font-bold" style="color:#1a472a;">Php <?php echo number_format($demoEventRemaining); ?></div>
                    </div>
                </div>
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-semibold" style="color:#4d5b3f;">Budget Utilization</span>
                        <span class="text-sm font-bold" style="color:#1a472a;"><?php echo $demoEventUtilization; ?>%</span>
                    </div>
                    <div class="w-full rounded-full h-3" style="background:#e8ebe3;">
                        <div class="progress-fill" style="width:<?php echo $demoEventUtilization; ?>%;"></div>
                    </div>
                </div>
            </div>

            <!-- ================== CALENDAR SECTION ================== -->
            <div id="calendar-section" class="card p-6 mb-6">
                <!-- Header with nav + filter -->
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold" style="color:#353f2d;">Event Calendar</h3>
                    <div class="flex items-center gap-3">
                        <a href="<?php echo $prevLink; ?>" class="w-9 h-9 rounded-xl flex items-center justify-center transition-all hover:shadow-md" style="background:#f4f7f2; color:#4d5b3f;">
                            <i class="fas fa-chevron-left text-sm"></i>
                        </a>
                        <span class="text-base font-bold min-w-[130px] text-center" style="color:#1a472a;"><?php echo $monthName; ?></span>
                        <a href="<?php echo $nextLink; ?>" class="w-9 h-9 rounded-xl flex items-center justify-center transition-all hover:shadow-md" style="background:#f4f7f2; color:#4d5b3f;">
                            <i class="fas fa-chevron-right text-sm"></i>
                        </a>
                        <div class="relative">
                            <button onclick="toggleFilter()" class="w-9 h-9 rounded-xl flex items-center justify-center transition-all hover:shadow-md" style="background:#f4f7f2; color:#4d5b3f;" title="Filter by status">
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

                <!-- Legend -->
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

                <!-- Calendar Grid -->
                <div class="calendar-grid">
                    <?php
                    $dayHeaders = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
                    foreach ($dayHeaders as $dh): ?>
                        <div class="calendar-day-header"><?php echo $dh; ?></div>
                    <?php endforeach; ?>

                    <?php
                    // Empty cells before month starts
                    for ($i = 0; $i < $firstDayOfWeek; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor;

                    // Days
                    for ($day = 1; $day <= $daysInMonth; $day++):
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
                                        <?php echo htmlspecialchars($de['title']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Popover -->
                <div id="eventPopover" class="event-popover">
                    <div class="popover-title" id="popoverTitle"></div>
                    <div class="popover-row"><i class="fas fa-user"></i><span id="popoverCustomer"></span></div>
                    <div class="popover-row"><i class="fas fa-users"></i><span id="popoverAttendees"></span></div>
                </div>
            </div>

            <!-- Upcoming Events This Month -->
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

            <!-- Your Events -->
            <div class="card p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold" style="color:#353f2d;">Your Events</h3>
                        <p class="text-sm" style="color:#62744f;">Click an event to view details</p>
                    </div>
                </div>
                <div class="divider mb-4"></div>

                <?php if (!empty($events)): ?>
                    <?php foreach ($events as $event):
                        $spent = $event['total_spent'] ?? 0;
                        $budget = $event['budget'] ?? 0;
                        $attendees = $event['attendees'] ?? 0;
                        $evtUtil = $budget > 0 ? min(100, ($spent / $budget) * 100) : 0;
                    ?>
                    <div class="block p-4 rounded-xl mb-3 transition-all hover:shadow-md" style="background:#faf9f6; border:1px solid #e8ebe3;">

                        <div class="flex items-start justify-between mb-3">
                            <div>
                                <h4 class="text-base font-bold" style="color:#353f2d;"><?php echo htmlspecialchars($event['title']); ?></h4>
                                <p class="text-xs mt-0.5" style="color:#62744f;"><?php echo htmlspecialchars($event['description'] ?? 'No description'); ?></p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-xs font-semibold" style="background:#dcfce7; color:#15803d;">upcoming</span>
                        </div>
                        <div class="flex flex-wrap gap-6 text-sm mb-3">
                            <div><span class="text-xs" style="color:#62744f;">Date</span> <span class="font-semibold ml-1" style="color:#353f2d;"><?php echo $event['date']; ?></span></div>
                            <div><span class="text-xs" style="color:#62744f;">Budget</span> <span class="font-semibold ml-1" style="color:#353f2d;">Php <?php echo number_format($budget); ?></span></div>
                            <div><span class="text-xs" style="color:#62744f;">Spent</span> <span class="font-semibold ml-1 text-red-600">Php <?php echo number_format($spent, 2); ?></span></div>
                            <div><span class="text-xs" style="color:#62744f;">Attendees</span> <span class="font-semibold ml-1" style="color:#353f2d;"><?php echo number_format($attendees); ?></span></div>
                        </div>
                        <div>
                            <div class="flex justify-between mb-1">
                                <span class="text-xs font-semibold" style="color:#4d5b3f;">Budget Used</span>
                                <span class="text-xs font-bold" style="color:#16a34a;"><?php echo round($evtUtil); ?>%</span>
                            </div>
                            <div class="w-full rounded-full h-2" style="background:#e8ebe3;">
                                <div class="progress-fill rounded-full h-2" style="width:<?php echo $evtUtil; ?>%;"></div>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-10">
                        <i class="fas fa-calendar-plus text-4xl mb-4" style="color:#c5d8bf;"></i>
                        <p class="text-base font-semibold mb-1" style="color:#62744f;">No events yet</p>
                        <p class="text-sm" style="color:#8b9a7a;">Create your first event to get started!</p>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>

    <script>
        // ===== Calendar Filter =====
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
        // Close filter on outside click
        document.addEventListener('click', (e) => {
            const dd = document.getElementById('filterDropdown');
            const btn = e.target.closest('button[onclick="toggleFilter()"]');
            if (!btn && dd.classList.contains('active')) dd.classList.remove('active');
        });

        // ===== Event Popover =====
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
    </script>
</body>
</html>