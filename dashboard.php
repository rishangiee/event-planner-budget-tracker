<?php
require_once 'config/config.php';
requireUser();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Handle budget updates
if (($_POST['action'] ?? '') === 'set_budget') {
    $eventId = (int)$_POST['event_id'];
    $budget = (float)$_POST['budget'];
    
    $stmt = $pdo->prepare("UPDATE events SET budget = ? WHERE id = ?");
    $stmt->execute([$budget, $eventId]);
    
    header("Location: " . $_SERVER['PHP_SELF'] . "?success=budget_updated");
    exit;
}

// Get ALL ADMIN EVENTS (not just user's events) with optional search
$q = trim($_GET['q'] ?? '');

$sql = "
    SELECT e.*, COALESCE(SUM(ex.amount), 0) as total_spent
    FROM events e
    LEFT JOIN bookings b ON b.event_id = e.id AND b.user_id = :user_id AND b.status != 'cancelled'
    LEFT JOIN expenses ex ON ex.event_id = e.id
    WHERE b.id IS NULL
      AND e.status != 'cancelled'
    GROUP BY e.id
";


$params = [':user_id' => (int)$userId];

if ($q !== '') {
    $sql .= " AND (e.title LIKE :q OR e.location LIKE :q OR e.description LIKE :q) ";
    $params[':q'] = "%{$q}%";
}

$sql .= " ORDER BY e.date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$events = $stmt->fetchAll();


// Stats (based on all admin events)
$totalEvents = count($events);
$today = date('Y-m-d');
$upcomingEvents = count(array_filter($events, fn($e) => $e['date'] >= $today));
$totalBudget = array_sum(array_column($events, 'budget'));
$totalSpent = array_sum(array_column($events, 'total_spent'));
$totalAttendees = array_sum(array_column($events, 'attendees'));
$remainingBudget = $totalBudget - $totalSpent;
$budgetUtilization = $totalBudget > 0 ? min(100, ($totalSpent / $totalBudget) * 100) : 0;

// Use real values
$demoTotalEvents = $totalEvents;
$demoUpcoming = $upcomingEvents;
$demoBudget = $totalBudget;
$demoAttendees = $totalAttendees;
$demoEventBudget = $totalBudget;
$demoEventSpent = $totalSpent;
$demoEventRemaining = $remainingBudget;
$demoEventUtilization = $totalBudget > 0 ? round($budgetUtilization) : 0;

// ===================== CALENDAR DATA =====================
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
                'customer' => $evt['customer_name'] ?? 'N/A',
                'attendees' => (int)($evt['attendees'] ?? 0),
                'max_attendees' => (int)($evt['max_attendees'] ?? 200),
                'description' => $evt['description'] ?? '',
                'status' => $evt['status'] ?? 'planned',
                'color' => getEventColor($evt['status'] ?? 'planned', $evt['date'], $today)
            ];
        }
    }

    $upcomingMonthEvents = array_filter($calendarEvents, function($e) use ($currentMonth, $currentYear, $today) {
        $d = date('Y-m-d', strtotime($e['date']));
        return (int)date('n', strtotime($d)) === $currentMonth &&
               (int)date('Y', strtotime($d)) === $currentYear &&
               $d >= $today;
    });
    usort($upcomingMonthEvents, fn($a, $b) => strcmp($a['date'], $b['date']));

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
    <title>Set Budget - All Admin Events - CAVENDIA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }
        .card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
            border: 1px solid #e4e6ea;
        }
        .card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
        .progress-fill {
            transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
            background: #1877f2;
            height: 100%;
            border-radius: 9999px;
        }
        .divider { border-top: 1px solid #e4e6ea; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-thumb { background: #e4e6ea; border-radius: 10px; }

        /* Facebook-like Top Nav */
        .top-nav {
            background: #ffffff;
            border-bottom: 1px solid #e4e6ea;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            padding: 8px 12px;
            color: #65676b;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s ease;
            font-size: 12px;
            font-weight: 500;
        }
        .nav-item:hover {
            background: #f2f3f5;
            color: #1877f2;
        }
        .nav-item.active {
            background: #e4f0ff;
            color: #1877f2;
        }
        .nav-item i { font-size: 20px; }

        /* Calendar Styles */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }
        .calendar-day-header {
            text-align: center;
            font-size: 0.75rem;
            font-weight: 600;
            color: #65676b;
            padding: 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .calendar-day {
            min-height: 100px;
            border-radius: 8px;
            background: #f8f9fa;
            border: 1px solid #e4e6ea;
            padding: 6px;
            position: relative;
            transition: all 0.2s ease;
        }
        .calendar-day:hover { background: #f0f2f5; }
        .calendar-day.today {
            border: 2px solid #1877f2;
            background: #e4f0ff;
        }
        .day-number {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1c1e21;
            margin-bottom: 4px;
            display: inline-block;
        }
        .event-pill {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 12px;
            margin-bottom: 2px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: all 0.15s ease;
            border: 1px solid rgba(0,0,0,0.1);
            font-weight: 500;
            color: #1c1e21;
        }
        .event-pill:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

               /* Featured/All event cards - match index.php layout */
        .event-card {
            background: #FFFFFF;
            border: 1px solid #D8DDD3;
            border-radius: 24px;
            overflow: hidden;
            transition: all 0.35s ease;
            margin-bottom: 12px;
        }
        .event-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 48px rgba(27,67,50,0.1);
            border-color: #A3B18A;
        }

        .event-img {
            height: 220px;
            position: relative;
            overflow: hidden;
            background: #F1F2EE;
        }
        .event-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.5s ease;
        }
        .event-card:hover .event-img img { transform: scale(1.08); }

        /* NEW: Image overlay for title and description */
        .event-img-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent 0%, rgba(0,0,0,0.85) 25%);
            padding: 24px 28px 28px;
            color: white;
            transform: translateY(100%);
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        }
        .event-card:hover .event-img-overlay {
            transform: translateY(0);
        }
        .event-img-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 8px;
            line-height: 1.3;
            opacity: 0;
            transform: translateY(10px);
            animation: fadeInUp 0.4s ease forwards;
        }
        .event-img-description {
            font-size: 0.9rem;
            line-height: 1.5;
            opacity: 0;
            transform: translateY(10px);
            animation: fadeInUp 0.4s ease 0.1s forwards;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .event-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #FFFFFF;
        }

        .event-content { padding: 28px; }
        .event-content h4 {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 600;
            color: #1B4332;
            margin-bottom: 10px;
            line-height: 1.3;
        }
        .event-content p {
            font-size: 0.88rem;
            font-weight: 300;
            color: #6B7C6D;
            line-height: 1.65;
            margin-bottom: 18px;
        }
        .event-meta {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .events-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 32px;
        }

        @media (max-width: 1024px){
            .events-grid-3{ grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 640px){
            .events-grid-3{ grid-template-columns: 1fr; }
        }
        .event-meta .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.82rem;
            color: #6B7C6D;
        }
        .event-meta .meta-item i {
            color: #A3B18A;
            font-size: 0.9rem;
            width: 18px;
            text-align: center;
        }

        .status-badge { display:none; }

        .status-upcoming { background: #e7f3ff; color: #1877f2; }
        .status-ongoing { background: #fff3cd; color: #856404; }
        .status-completed { background: #d1ecf1; color: #0c5460; }

        /* Filter dropdown */
        .filter-dropdown {
            position: absolute;
            right: 0;
            top: 110%;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            border: 1px solid #e4e6ea;
            padding: 8px 0;
            min-width: 160px;
            display: none;
            z-index: 100;
        }
        .filter-dropdown.active { display: block; }
        .filter-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            cursor: pointer;
            font-size: 14px;
            color: #1c1e21;
            transition: background 0.15s;
        }
        .filter-option:hover { background: #f0f2f5; }
        .filter-option input { accent-color: #1877f2; }

        /* NEW Budget Styles */
        .budget-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px;
            margin-top: 12px;
        }
        .budget-progress {
            height: 10px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            margin: 12px 0;
        }
        .budget-progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.8s ease;
        }
        .budget-progress-fill.under { background: linear-gradient(90deg, #10b981, #34d399); }
        .budget-progress-fill.on-track { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
        .budget-progress-fill.over { background: linear-gradient(90deg, #ef4444, #f87171); }
        .budget-progress-fill.no-budget { background: #d1d5db; }

        .budget-set-btn {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border: none;
            padding: 10px 20px;
            border-radius: 12px;
            color: white;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        .budget-set-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 25px rgba(59,130,246,0.4); }

        /* Budget Modal */
        .budget-modal { position: fixed; inset: 0; z-index: 10000; background: rgba(15,23,42,0.6); backdrop-filter: blur(12px); display: none; align-items: center; justify-content: center; padding: 20px; }
        .budget-modal.active { display: flex; }
        .budget-modal-content { background: white; border-radius: 24px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px rgba(0,0,0,0.25); transform: scale(0.9) translateY(20px); opacity: 0; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1); }
        .budget-modal.active .budget-modal-content { transform: scale(1) translateY(0); opacity: 1; }
    </style>
</head>
<body class="min-h-screen">

    <!-- Facebook-like Top Navigation -->
    <div class="top-nav fixed top-0 left-0 right-0 z-50">
        <!-- Top Bar with Navigation Integrated -->
        <div class="flex items-center justify-between px-4 py-2 bg-white border-b border-gray-200 gap-4">
            <a href="dashboard.php" class="flex items-center gap-2 flex-shrink-0">
                <div class="w-10 h-10 rounded-full flex items-center justify-center bg-gradient-to-r from-blue-600 to-blue-700 shadow-lg">
                    <i class="fas fa-calendar-alt text-white text-lg"></i>
                </div>
                <span class="text-lg font-bold text-gray-900">CAVENDIA</span>
            </a>
            
            <!-- Search Bar -->
            <form method="GET" class="flex gap-2 flex-shrink-0" style="min-width: 280px;">
                <input
                    type="text"
                    name="q"
                    value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>"
                    placeholder="Search events..."
                    class="flex-1 px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm text-gray-900 outline-none focus:ring-2 focus:ring-blue-200"
                >
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 hover:bg-blue-600 text-white transition-all flex items-center gap-1">
                    <i class="fas fa-search"></i>
                </button>
            </form>
            
            <!-- Centered Navigation -->
            <div class="flex items-center gap-6 flex-1 justify-center px-4">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="calendar.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendar</span>
                </a>
                <a href="booking.php" class="nav-item">
                    <i class="fas fa-bookmark"></i>
                    <span>Bookings</span>
                </a>
            </div>
            
        <div class="flex items-center gap-2 flex-shrink-0">
                <div class="relative">
                    <div class="flex items-center gap-2">
                        <span class="hidden sm:inline text-sm font-semibold text-gray-900">Welcome back, <?php echo htmlspecialchars($userName); ?>!</span>
                        <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold cursor-pointer hover:bg-blue-700">
                            <?php echo strtoupper(substr($userName, 0, 2)); ?>
                        </div>
                    </div>
                    <div class="filter-dropdown" id="userMenu">
                        <a href="profile.php" class="filter-option"><i class="fas fa-user mr-3"></i>Profile</a>
                        <a href="index.php" class="filter-option"><i class="fas fa-sign-out-alt mr-3"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require_once 'chat_widget_snippet.php'; ?>




    <!-- Main Content -->
    <main class="pt-24 pb-8 px-6 max-w-6xl mx-auto">
        <!-- Success Message -->
        <?php if (isset($_GET['success']) && $_GET['success'] === 'budget_updated'): ?>
        <div class="fixed top-24 right-6 p-3 bg-green-50 border border-green-200 rounded-lg shadow-lg max-w-sm" style="z-index: 9999;">
            <div class="flex items-center gap-2">
                <i class="fas fa-check-circle text-green-500 text-lg"></i>
                <span class="text-green-800 font-semibold text-sm">Budget Updated Successfully!</span>
            </div>
        </div>
        <?php endif; ?>

        <!-- All Admin Events - Facebook Style -->
        <div class="space-y-4" style="margin-top: 40px;">
            <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                <i class="fas fa-list text-blue-600"></i>
                All Events (<?php echo count($events); ?>)
            </h2>
            
            <?php if (!empty($events)): ?>
                <?php foreach ($events as $event):
                    $spent = $event['total_spent'] ?? 0;
                    $budget = $event['budget'] ?? 0;
                    $attendees = $event['attendees'] ?? 0;
                    $maxAttendees = $event['max_attendees'] ?? 200;
                    $evtUtil = $budget > 0 ? min(100, ($spent / $budget) * 100) : 0;
                    $status = $event['status'] ?? 'planned';

                    // badge color heuristic (similar to index.php)
                    $titleLower = strtolower($event['title'] ?? '');
                    $badgeColor = '#A3B18A';
                    $badgeColors = [
                        'wedding' => '#A3B18A',
                        'corporate' => '#4a6b50',
                        'gala' => '#8B7355',
                        'charity' => '#6B8E6B',
                        'music' => '#e17055',
                        'exhibition' => '#6c5ce7'
                    ];
                    foreach ($badgeColors as $type => $color) {
                        if (strpos($titleLower, $type) !== false) { $badgeColor = $color; break; }
                    }

                    // Budget status
                    $budgetStatus = $budget > 0 ? ($spent > $budget ? 'over' : ($evtUtil > 80 ? 'on-track' : 'under')) : 'no-budget';
                    $budgetStatusText = $budget > 0 ? number_format($evtUtil, 1) . '%' : 'No Budget Set';
                    $budgetText = $budget > 0 ? '₱' . number_format($budget) : 'Set Budget';

                    $eventDate = !empty($event['date']) ? date('F j, Y', strtotime($event['date'])) : 'TBD';
                    $eventTime = !empty($event['time']) ? date('g:i A', strtotime($event['time'])) . ' onwards' : 'Time TBA';
                    $location = htmlspecialchars($event['location'] ?? 'Location TBA');
                    $description = htmlspecialchars($event['description'] ?? 'No description available.');
                    $shortDescription = strlen($description) > 120 ? substr($description, 0, 120) . '...' : $description;
                ?>
                <div class="event-card">
                    <div class="event-img">
                        <img src="photorealistic-wedding-venue-with-intricate-decor-ornaments_23-2151481464.avif" alt="Venue">
                        <span class="event-badge" style="background: <?php echo $badgeColor; ?>;">Event</span>
                        
                        <!-- NEW: Title and Description Overlay -->
                        <div class="event-img-overlay">
                            <h3 class="event-img-title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p class="event-img-description"><?php echo $shortDescription; ?></p>
                        </div>
                    </div>
                    <div class="event-content">

                        <div class="event-meta">
                            <div class="meta-item"><i class="far fa-calendar-alt"></i><span><?php echo $eventDate; ?></span></div>
                            <div class="meta-item"><i class="far fa-clock"></i><span><?php echo $eventTime; ?></span></div>
                            <div class="meta-item"><i class="fas fa-map-marker-alt"></i><span><?php echo $location; ?></span></div>
                            <div class="meta-item"><i class="fas fa-wallet"></i><span><?php echo $budget > 0 ? '₱' . number_format($budget) : 'No budget'; ?></span></div>
                            <div class="meta-item"><i class="fas fa-users"></i><span><?php echo number_format($attendees); ?> / <?php echo number_format($maxAttendees); ?></span></div>
                        </div>

                        <!-- BUDGET TRACKING CARD (initially hidden until first click) -->
                        <div class="budget-card book-budget-card hidden" id="budgetCard-<?php echo (int)$event['id']; ?>">

                            <div class="flex justify-between items-center mb-3">
                                <span class="font-semibold text-lg text-gray-900 flex items-center gap-2">
                                    <i class="fas fa-wallet text-blue-600"></i>
                                    Budget: <?php echo $budgetText; ?>
                                </span>
                                <button type="button" 
                                        onclick="openBudgetModal(<?php echo (int)$event['id']; ?>, '<?php echo addslashes($event['title']); ?>', <?php echo $budget; ?>)"
                                        class="budget-set-btn">
                                    <i class="fas fa-edit mr-1"></i>
                                    <?php echo $budget > 0 ? 'Update' : 'Set'; ?> Budget
                                </button>
                            </div>
                            <div class="budget-progress">
                                <div class="budget-progress-fill <?php echo $budgetStatus; ?>" 
                                     style="width: <?php echo $budget > 0 ? $evtUtil : 0; ?>%"></div>
                            </div>
                            <div class="flex justify-between text-sm mt-2">
                                <span class="text-gray-600"><?php echo $spent > 0 ? 'Spent: ₱' . number_format($spent) : 'No expenses yet'; ?></span>
                                <span class="font-semibold <?php echo $budgetStatus === 'over' ? 'text-red-600' : ($budgetStatus === 'on-track' ? 'text-blue-600' : 'text-green-600'); ?>">
                                    <?php echo $budgetStatusText; ?>
                                </span>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap gap-2">
                            <button type="button" class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 hover:bg-blue-600 text-white transition-all flex items-center gap-1"
onclick="openEventModal(<?php echo (int)$event['id']; ?>, <?php echo htmlspecialchars(json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES); ?>)">
                                <i class="fas fa-eye"></i> View details
                            </button>

                            <form id="bookForm-<?php echo (int)$event['id']; ?>" action="booking.php" method="GET" style="display:inline;">
                                <input type="hidden" name="event" value="<?php echo $event['id']; ?>">
                                <input type="hidden" name="budget" value="<?php echo (float)($event['budget'] ?? 0); ?>">
                                <input type="hidden" name="status" value="pending">
                                <button type="button" 
                                        class="book-btn px-4 py-2 rounded-lg text-sm font-semibold bg-green-500 hover:bg-green-600 text-white transition-all flex items-center gap-1" 
                                        style="border:none;" 
                                        data-event-id="<?php echo (int)$event['id']; ?>"
                                        data-budget="<?php echo (float)($event['budget'] ?? 0); ?>"
                                        data-title="<?php echo addslashes($event['title']); ?>">
                                    <i class="fas fa-calendar-check"></i> Book Now
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            <?php else: ?>
                <div class="card p-12 text-center">
                    <i class="fas fa-calendar-times text-5xl text-gray-300 mb-4"></i>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">No events found</h3>
                    <p class="text-gray-600">No events available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- BUDGET SETTING MODAL -->
    <div id="budgetModal" class="budget-modal" aria-hidden="true">
        <div class="budget-modal-content">
            <div class="p-6 border-b bg-gray-50 rounded-t-3xl">
                <h3 class="text-2xl font-bold text-gray-900 mb-1" id="budgetModalTitle">Set Event Budget</h3>
                <p class="text-gray-600" id="budgetModalSubtitle">Track expenses for this event</p>
            </div>
            <form method="POST" id="budgetForm" class="p-6">
                <input type="hidden" name="action" value="set_budget">
                <input type="hidden" name="event_id" id="budgetEventId">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Event Budget Amount</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-semibold">₱</span>
                            <input type="number" name="budget" id="budgetInput" 
                                   step="0.01" min="0" required
                                   class="w-full pl-12 pr-4 py-4 border border-gray-200 rounded-2xl focus:ring-2 focus:ring-blue-200 focus:border-transparent text-lg font-semibold"
                                   placeholder="0.00">
                        </div>
                    </div>
                    <div class="text-sm text-gray-500 bg-blue-50 border border-blue-100 p-4 rounded-xl">
                        <i class="fas fa-lightbulb text-blue-500 mr-2"></i>
                        This budget helps track all expenses and maintain financial control for the event.
                    </div>
                </div>
                
                <div class="flex gap-3 pt-6 border-t bg-gray-50 px-2 pb-4 rounded-b-3xl -mx-6 mt-2">
                    <button type="button" onclick="closeBudgetModal()" 
                            class="flex-1 py-3 px-6 border border-gray-200 rounded-xl hover:bg-gray-50 font-semibold text-gray-700">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="submit" class="flex-1 py-3 px-6 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-xl font-semibold shadow-lg hover:shadow-xl transition-all flex items-center justify-center">
                        <i class="fas fa-save mr-2"></i>Save Budget
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Full-screen Event Details Modal (opened from dashboard "View details") -->
    <div id="eventDetailsOverlay" class="event-details-overlay" aria-hidden="true">
        <div class="event-details-modal" role="dialog" aria-modal="true" aria-labelledby="eventDetailsModalHeader">
            <!-- Header -->
            <div class="event-details-header">
                <h3 id="eventDetailsModalHeader" class="event-details-header-title">Event Details</h3>
                <button type="button" class="event-details-close" aria-label="Close" onclick="closeEventModal()">
                    <i class="fas fa-times" aria-hidden="true"></i>
                </button>
            </div>

            <!-- Hero -->
            <div class="event-details-hero">
                <img id="eventDetailsHeroImage" src="photorealistic-wedding-venue-with-intricate-decor-ornaments_23-2151481464.avif" alt="Event hero image" />
                <div class="event-details-hero-overlay"></div>
                <div class="event-details-hero-body">
                    <span id="eventDetailsCategory" class="event-details-category"></span>
                    <h2 id="eventDetailsTitle" class="event-details-hero-title">Event Title</h2>
                    <p id="eventDetailsDescription" class="event-details-hero-description"></p>
                </div>

                <!-- NEW: Title + Description OUTSIDE the image (requested) -->
                <div class="event-details-outside">
                    <h3 class="event-details-outside-title">Event Title</h3>
                    <p class="event-details-outside-description" id="eventDetailsDescriptionOutside">Event description</p>
                </div>

                <div id="eventDetailsBudgetBanner" class="event-details-budget-banner hidden" aria-live="polite">
                    <div class="event-details-budget-title">Set Your Budget for This Event</div>
                    <div class="event-details-budget-value">₱<span id="eventDetailsBudgetValue">0</span></div>
                    <div class="event-details-budget-note">This budget will be used to track your expenses for this event.</div>
                </div>
            </div>

            <!-- Info Cards Grid -->
            <div class="event-details-grid" aria-label="Event information">
                <div class="event-details-card">
                    <div class="event-details-card-icon"><i class="far fa-calendar-alt" aria-hidden="true"></i></div>
                    <div class="event-details-card-body">
                        <div class="event-details-card-label">Date</div>
                        <div class="event-details-card-value" id="eventDetailsDate">TBD</div>
                    </div>
                </div>

                <div class="event-details-card">
                    <div class="event-details-card-icon"><i class="far fa-clock" aria-hidden="true"></i></div>
                    <div class="event-details-card-body">
                        <div class="event-details-card-label">Time</div>
                        <div class="event-details-card-value" id="eventDetailsTime">TBA</div>
                    </div>
                </div>

                <div class="event-details-card">
                    <div class="event-details-card-icon"><i class="fas fa-map-marker-alt" aria-hidden="true"></i></div>
                    <div class="event-details-card-body">
                        <div class="event-details-card-label">Location</div>
                        <div class="event-details-card-value" id="eventDetailsLocation">Location TBA</div>
                    </div>
                </div>

                <div class="event-details-card">
                    <div class="event-details-card-icon"><i class="fas fa-users" aria-hidden="true"></i></div>
                    <div class="event-details-card-body">
                        <div class="event-details-card-label">Available Spots</div>
                        <div class="event-details-card-value" id="eventDetailsAvailableSpots">0</div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="event-details-actions">
                <button type="button" class="event-details-btn event-details-btn-close" onclick="closeEventModal()">
                    Close
                </button>


                <form id="eventDetailsBookForm" action="booking.php" method="GET" class="event-details-form-book">
                    <input type="hidden" name="event" id="eventDetailsBookEventId" value="" />
                    <input type="hidden" name="budget" id="eventDetailsBookBudget" value="0" />
                    <button type="button" id="eventDetailsBookBtn" class="event-details-btn event-details-btn-book" data-confirming="0" data-event-title="">
                        <i class="fas fa-calendar-check" aria-hidden="true"></i>
                        <span>Book This Event</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Event Details Modal Styles -->
    <style>
        .event-details-overlay{
            position:fixed; inset:0; z-index:10000;
            background:rgba(15,23,42,0.45);
            backdrop-filter: blur(10px);
            display:none;
            align-items:center;
            justify-content:center;
            padding: 18px;
        }

        /* Outside the hero image (title/description) */
        .event-details-outside{
            padding: 12px 18px 0;
        }
        .event-details-outside-title{
            margin: 0 0 6px;
            font-size: 1.05rem;
            font-weight: 900;
            color:#1F2937;
            letter-spacing: -0.02em;
        }
        .event-details-outside-description{
            margin: 0;
            color:#475569;
            font-weight: 500;
            line-height: 1.5;
            font-size: 0.85rem;
            max-height: 3em;
            overflow:hidden;
        }
        .event-details-overlay.active{ display:flex; }

        .event-details-modal{
            width:100%;
            max-width: 760px;
            max-height: 92vh;
            overflow:auto;
            background: #FCFCFB;
            border-radius: 22px;
            box-shadow: 0 25px 50px rgba(27,67,50,0.18);
            transform: translateY(10px);
            opacity: 0;
            transition: transform 280ms ease, opacity 280ms ease;
            border: 1px solid rgba(163,177,138,0.25);
        }
        .event-details-overlay.active .event-details-modal{
            transform: translateY(0);
            opacity: 1;
        }

        .event-details-header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding: 18px 18px 0;
        }

        /* removed duplicate hero-body styles */


        .event-details-header-title{
            font-size: 1.25rem;
            font-weight: 900;
            color:#1B4332;
            letter-spacing: -0.02em;
            margin: 0;
        }

        .event-details-close{
            width:44px; height:44px;
            border-radius: 14px;
            border: 1px solid rgba(28,43,38,0.08);
            background: rgba(240, 253, 244, 0.6);
            color:#1B4332;
            cursor:pointer;
            display:flex; align-items:center; justify-content:center;
            transition: all 0.15s ease;
        }
        .event-details-close:hover{
            transform: translateY(-1px);
            background: rgba(240, 253, 244, 0.95);
        }

.event-details-hero{
            position:relative;
            margin: 14px 18px 0;
            border-radius: 18px;
            overflow:hidden;
            background: #F1F2EE;
            /* smaller hero image height */
            min-height: 140px;
            height: 140px;
        }
.event-details-hero img{
            width:100%;
            height: 100%;
            object-fit: cover;
            display:block;
        }
        .event-details-hero-overlay{
            position:absolute; inset:0;
            background: linear-gradient(90deg, rgba(252,252,251,0.95) 0%, rgba(252,252,251,0.75) 45%, rgba(252,252,251,0.25) 100%);
        }

        .event-details-hero-body{
            display:flex;
            flex-direction:column;
            gap: 6px;
        }


        .event-details-hero-category,
        #eventDetailsCategory,
        #eventDetailsTitle,
        #eventDetailsDescription{
            pointer-events: auto;
        }

        .event-details-category{
            align-self:flex-start;
            background: rgba(16,185,129,0.10);
            color:#0f766e;
            border: 1px solid rgba(16,185,129,0.18);
            padding: 6px 10px;
            border-radius: 9999px;
            font-weight: 800;
            letter-spacing: 0.01em;
            font-size: 0.8rem;
        }

        .event-details-hero-title{
            margin: 0;
            font-size: 1.2rem;
            font-weight: 900;
            color:#1F2937;
            letter-spacing: -0.02em;
        }

.event-details-hero-description{
            margin: 0;
            color:#475569;
            font-weight: 500;
            line-height: 1.5;
            max-width: 52ch;
            font-size: 0.85rem;
            max-height: 2.7em;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .event-details-grid{
            padding: 18px;
            display:grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        @media (max-width: 520px){
            .event-details-grid{ grid-template-columns: 1fr; }
        }

        .event-details-card{
            background: #F3F4F6;
            border: 1px solid rgba(148,163,184,0.18);
            border-radius: 16px;
            padding: 14px;
            display:flex;
            align-items:flex-start;
            gap: 12px;
        }

        .event-details-card-icon{
            width:40px; height:40px;
            border-radius: 14px;
            background: rgba(163,177,138,0.25);
            color:#1B4332;
            display:flex;
            align-items:center;
            justify-content:center;
            border: 1px solid rgba(163,177,138,0.35);
            flex: 0 0 auto;
        }

        .event-details-card-icon i{ font-size: 18px; }

        .event-details-card-label{
            font-size: 0.85rem;
            font-weight: 800;
            color:#6B7280;
            margin-bottom: 4px;
        }

        .event-details-card-value{
            font-size: 1rem;
            font-weight: 900;
            color:#111827;
            letter-spacing: -0.01em;
        }

        .event-details-actions{
            padding: 0 18px 18px;
            display:grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        @media (max-width: 520px){
            .event-details-actions{ grid-template-columns: 1fr; }
        }

        .event-details-form-book{ margin:0; }

        .event-details-btn{
            width:100%;
            border-radius: 16px;
            padding: 14px 16px;
            font-weight: 900;
            border: 1px solid transparent;
            cursor:pointer;
            display:flex;
            align-items:center;
            justify-content:center;
            gap: 10px;
            transition: all 0.15s ease;
        }

        .event-details-btn-close{
            background: #ECEFF3;
            color:#111827;
            border-color: rgba(148,163,184,0.35);
        }
        .event-details-btn-close:hover{
            background: #E6EAF0;
            transform: translateY(-1px);
        }

        .event-details-btn-book{
            background: #95C3A0;
            color:#0B2B1C;
            border-color: rgba(21,128,61,0.22);
        }
        .event-details-btn-book:hover{
            background: #7FB894;
            transform: translateY(-1px);
        }
        .event-details-budget-banner {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 18px;
            padding: 18px 20px;
            margin: 18px;
            color: #0f766e;
            display: none;
        }
        .event-details-budget-banner.visible {
            display: block;
        }
        .event-details-budget-title {
            font-size: 0.98rem;
            font-weight: 900;
            margin-bottom: 8px;
        }
        .event-details-budget-value {
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 8px;
            color: #115e59;
        }
        .event-details-budget-note {
            font-size: 0.95rem;
            line-height: 1.5;
            color: #134e4a;
        }
    </style>

    <script>
        // Messenger-style dashboard chat widget
        (function () {
            const btn = document.getElementById('chatWidgetBtn');
            const overlay = document.getElementById('chatWidgetOverlay');
            const modal = document.getElementById('chatWidgetModal');
            const closeBtn = document.getElementById('chatWidgetClose');
            const messageList = document.getElementById('chatWidgetBubbles');
            const emptyState = document.getElementById('chatWidgetEmpty');
            const form = document.getElementById('chatWidgetForm');
            const input = document.getElementById('chatWidgetInput');

            if (!btn || !overlay || !modal || !messageList || !emptyState || !form || !input) return;

            let pollingTimer = null;
            let lastMsgSig = null;

            function escapeHtml(str) {
                return String(str ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '<')
                    .replace(/>/g, '>')
                    .replace(/"/g, '"')
                    .replace(/'/g, '&#039;');
            }

            function renderMessages(messages) {
                messageList.innerHTML = '';

                const bubbles = (messages || []).map(msg => {
                    const senderType = msg.sender_type === 'user' ? 'user' : 'admin';
                    const bubbleClass = 'message-bubble ' + senderType;

                    // using a compact bubble style inline (messenger)
                    const safeMsg = escapeHtml(msg.message);
                    const senderName = msg.user_name ? String(msg.user_name) : (senderType === 'user' ? 'User' : 'Admin');
                    const createdAt = msg.created_at ? String(msg.created_at) : '';

                    return `
                        <div class="${bubbleClass}" style="max-width:75%; padding:10px 14px; border-radius:18px; margin-bottom:10px; ${senderType === 'user' ? 'margin-left:auto; background: linear-gradient(135deg, #1a472a, #2d6a4f); color:#fff;' : 'background:#fff; border:1px solid #e8ebe3; color:#353f2d;'}">
                            <div style="word-break:break-word;">${safeMsg}</div>
                            <div style="font-size:12px; opacity:.7; margin-top:4px;">${escapeHtml(senderName)} &bull; ${escapeHtml(createdAt)}</div>
                        </div>
                    `;
                });

                if (!bubbles.length) {
                    emptyState.style.display = 'flex';
                    return;
                }

                emptyState.style.display = 'none';
                bubbles.forEach(b => {
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = b;
                    messageList.appendChild(wrapper.firstElementChild);
                });

                // scroll to bottom
                const container = document.getElementById('chatWidgetMessageList');
                if (container) container.scrollTop = container.scrollHeight;
            }

            async function fetchMessages() {
                const res = await fetch('api.php?type=chat', { method: 'GET' });
                if (!res.ok) return;
                const data = await res.json();
                if (!data.success || !Array.isArray(data.messages)) return;

                const messages = data.messages.slice().reverse(); // oldest first

                const sig = messages.length ? (messages[messages.length - 1].sender_type + '|' + messages[messages.length - 1].message + '|' + messages[messages.length - 1].created_at) : null;
                if (sig && sig === lastMsgSig) return;
                lastMsgSig = sig;

                renderMessages(messages);
            }

            function startPolling() {
                if (pollingTimer) return;
                fetchMessages().catch(() => {});
                pollingTimer = setInterval(() => {
                    fetchMessages().catch(() => {});
                }, 2000);
            }

            function stopPolling() {
                if (pollingTimer) {
                    clearInterval(pollingTimer);
                    pollingTimer = null;
                }
            }

            function openWidget() {
                overlay.style.display = 'block';
                modal.style.display = 'block';
                overlay.classList.add('active');
                startPolling();
            }

            function closeWidget() {
                overlay.style.display = 'none';
                modal.style.display = 'none';
                overlay.classList.remove('active');
                stopPolling();
            }

            // Make sure the button never disappears
            btn.style.display = 'flex';
            btn.style.opacity = '1';

            btn.addEventListener('click', (e) => {

                e.preventDefault();
                openWidget();
            });

            closeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                closeWidget();
            });

            overlay.addEventListener('click', closeWidget);

            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                const msg = input.value.trim();
                if (!msg) return;

                const fd = new FormData();
                fd.append('message', msg);

                // POST to API (json expected by api.php?type=chat)
                await fetch('api.php?type=chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: msg })
                }).catch(() => {});

                input.value = '';
                fetchMessages().catch(() => {});
            });

            // initial hidden
            closeWidget();
        })();

        // User menu toggle
        document.querySelector('.relative').addEventListener('click', function(e) {

            e.stopPropagation();
            const menu = document.getElementById('userMenu');
            menu.classList.toggle('active');
        });

        document.addEventListener('click', function() {
            document.getElementById('userMenu').classList.remove('active');
        });

        // BUDGET MODAL FUNCTIONS
        function openBudgetModal(eventId, eventTitle, currentBudget) {
            document.getElementById('budgetModal').classList.add('active');
            document.getElementById('budgetModalTitle').textContent = `Set Budget: ${eventTitle}`;
            document.getElementById('budgetEventId').value = eventId;
            document.getElementById('budgetInput').value = currentBudget || '';
            document.body.style.overflow = 'hidden';
        }

        function closeBudgetModal() {
            document.getElementById('budgetModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        window.openEventModal = function(eventId, eventObj){
            try{
                const overlay = document.getElementById('eventDetailsOverlay');
                overlay.classList.add('active');
                overlay.setAttribute('aria-hidden','false');

                    const titleRaw = eventObj.title ?? eventObj.event_title ?? '';
                    const title = (titleRaw ?? '').toString().trim() || 'Event Title';

                    const descriptionRaw = eventObj.description ?? eventObj.event_description ?? '';
                    const description = (descriptionRaw ?? '').toString().trim();
                    const safeDescription = description.length ? description : 'No description available.';
                    const category = (eventObj.category ?? '').toString();
                    const location = (eventObj.location ?? 'Location TBA').toString();


                const date = eventObj.date ? new Date(eventObj.date+'T00:00:00') : null;
                const eventDateText = date ? date.toLocaleDateString(undefined, { year:'numeric', month:'short', day:'numeric' }) : 'TBD';

                const eventTimeText = eventObj.time
                    ? (new Date('1970-01-01T'+eventObj.time).toLocaleTimeString(undefined, { hour:'numeric', minute:'2-digit' }) + ' onwards')
                    : 'Time TBA';

                const attendees = Number(eventObj.attendees ?? 0);
                const maxAttendees = Number(eventObj.max_attendees ?? eventObj.max_participants ?? 200);
                const availableSpots = Math.max(0, maxAttendees - attendees);

                document.getElementById('eventDetailsTitle').textContent = title;
                document.getElementById('eventDetailsDescription').textContent = safeDescription;
                // Outside-image title/description
                const outsideTitleEl = document.querySelector('.event-details-outside-title');
                if (outsideTitleEl) outsideTitleEl.textContent = title;
                const outsideDescEl = document.getElementById('eventDetailsDescriptionOutside');
                if (outsideDescEl) outsideDescEl.textContent = safeDescription;
                // If not found (e.g. template not updated), also set the hero title/description as fallback
                document.getElementById('eventDetailsTitle').textContent = title;
                document.getElementById('eventDetailsDescription').textContent = safeDescription;
                document.getElementById('eventDetailsCategory').textContent = category;
                document.getElementById('eventDetailsDate').textContent = eventDateText;
                document.getElementById('eventDetailsTime').textContent = eventTimeText;
                document.getElementById('eventDetailsLocation').textContent = location;
                document.getElementById('eventDetailsAvailableSpots').textContent = availableSpots;

                document.getElementById('eventDetailsBookEventId').value = eventId;
                const modalBudgetInput = document.getElementById('eventDetailsBookBudget');
                const eventDetailsBtn = document.getElementById('eventDetailsBookBtn');
                const eventDetailsBudgetValue = document.getElementById('eventDetailsBudgetValue');
                const eventTitle = title;
                const currentBudget = Number(eventObj.budget ?? 0).toFixed(2);
                if (modalBudgetInput) {
                    modalBudgetInput.value = currentBudget;
                }
                if (eventDetailsBudgetValue) {
                    eventDetailsBudgetValue.textContent = Number(currentBudget).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                }
                hideEventDetailsBudgetBanner();
                if (eventDetailsBtn) {
                    eventDetailsBtn.dataset.eventId = eventId;
                    eventDetailsBtn.dataset.budget = currentBudget;
                    eventDetailsBtn.dataset.eventTitle = eventTitle;
                    eventDetailsBtn.dataset.confirming = '0';
                    setEventDetailsBookButtonState(false);
                }
                document.body.style.overflow = 'hidden';
            }catch(err){
                console.error(err);
            }
        };

        window.closeEventModal = function(){
            const overlay = document.getElementById('eventDetailsOverlay');
            overlay.classList.remove('active');
            overlay.setAttribute('aria-hidden','true');
            document.body.style.overflow = '';
        };

        // 2-step booking UI: Book Now -> show budget card -> Confirm Booking -> submit
        function setBookUiState(eventId, isConfirming) {
            const btn = document.querySelector('.book-btn[data-event-id="' + eventId + '"]');
            if (!btn) return;

            const budgetCard = document.getElementById('budgetCard-' + eventId);
            if (budgetCard) {
                if (isConfirming) budgetCard.classList.remove('hidden');
                else budgetCard.classList.add('hidden');
            }

            if (isConfirming) {
                btn.classList.remove('bg-green-500', 'hover:bg-green-600');
                btn.classList.add('bg-blue-500', 'hover:bg-blue-600');
                const icon = btn.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-calendar-check';
                }
                btn.innerHTML = '<i class="fas fa-calendar-check"></i> Confirm Booking';
            } else {
                btn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                btn.classList.add('bg-green-500', 'hover:bg-green-600');
                btn.innerHTML = '<i class="fas fa-calendar-check"></i> Book Now';
            }
        }

        function attachBookButtonListeners() {
            document.querySelectorAll('.book-btn[data-event-id]').forEach(function(btn){
                btn.removeEventListener('click', handleBookClick);
                btn.addEventListener('click', handleBookClick);
            });
        }

        function setEventDetailsBookButtonState(isConfirming) {
            const btn = document.getElementById('eventDetailsBookBtn');
            if (!btn) return;
            const icon = btn.querySelector('i');
            if (isConfirming) {
                btn.classList.remove('event-details-btn-book');
                btn.classList.add('bg-blue-500', 'hover:bg-blue-600', 'text-white');
                if (icon) icon.className = 'fas fa-check';
                btn.querySelector('span').textContent = 'Confirm Booking';
            } else {
                btn.classList.remove('bg-blue-500', 'hover:bg-blue-600', 'text-white');
                btn.classList.add('event-details-btn-book');
                if (icon) icon.className = 'fas fa-calendar-check';
                btn.querySelector('span').textContent = 'Book This Event';
            }
        }

        function showEventDetailsBudgetBanner(budget) {
            const banner = document.getElementById('eventDetailsBudgetBanner');
            const value = document.getElementById('eventDetailsBudgetValue');
            if (!banner || !value) return;
            value.textContent = Number(budget ?? 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            banner.classList.remove('hidden');
            banner.classList.add('visible');
        }

        function hideEventDetailsBudgetBanner() {
            const banner = document.getElementById('eventDetailsBudgetBanner');
            if (!banner) return;
            banner.classList.add('hidden');
            banner.classList.remove('visible');
        }

        function handleBookClick() {
            const eventId = this.getAttribute('data-event-id');
            const budget = parseFloat(this.getAttribute('data-budget'));
            const eventTitle = this.getAttribute('data-title');
            const isConfirming = this.dataset.confirming === '1';

            if (!isConfirming) {
                if (budget <= 0) {
                    openBudgetModal(eventId, eventTitle, budget);
                    return;
                }
                // Step 1: change label to confirm booking
                this.dataset.confirming = '1';
                setBookUiState(eventId, true);
                return;
            }

            // Step 2: Check if budget is set before submitting
            const updatedBudget = parseFloat(this.getAttribute('data-budget'));
            if (updatedBudget <= 0) {
                alert('Please set a budget for this event before confirming the booking.');
                this.dataset.confirming = '0';
                setBookUiState(eventId, false);
                openBudgetModal(eventId, eventTitle, updatedBudget);
                return;
            }

            const form = document.getElementById('bookForm-' + eventId);
            if (form) form.submit();
        }

        const eventDetailsBookBtn = document.getElementById('eventDetailsBookBtn');
        if (eventDetailsBookBtn) {
            eventDetailsBookBtn.addEventListener('click', function(e){
                const eventId = this.dataset.eventId;
                const budget = parseFloat(document.getElementById('eventDetailsBookBudget').value || '0');
                const eventTitle = this.dataset.eventTitle || 'Event';
                const isConfirming = this.dataset.confirming === '1';

                if (!eventId) return;

                if (!isConfirming) {
                    showEventDetailsBudgetBanner(budget);
                    if (budget <= 0) {
                        openBudgetModal(eventId, eventTitle, budget);
                        return;
                    }
                    this.dataset.confirming = '1';
                    setEventDetailsBookButtonState(true);
                    return;
                }

                if (budget <= 0) {
                    showEventDetailsBudgetBanner(budget);
                    alert('Please set a budget for this event before confirming the booking.');
                    this.dataset.confirming = '0';
                    setEventDetailsBookButtonState(false);
                    openBudgetModal(eventId, eventTitle, budget);
                    return;
                }

                document.getElementById('eventDetailsBookForm').submit();
            });
        }

        attachBookButtonListeners();

        // Auto-dismiss success message after 3 seconds
        const successMsg = document.querySelector('.fixed[style*="z-index: 9999"]');
        if (successMsg) {
            setTimeout(function() {
                successMsg.style.transition = 'opacity 0.3s ease';
                successMsg.style.opacity = '0';
                setTimeout(function() {
                    successMsg.remove();
                }, 300);
            }, 3000);
        }

        // Handle budget form submission
        const budgetForm = document.getElementById('budgetForm');
        if (budgetForm) {
            budgetForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    // Get the event ID from the form
                    const eventId = document.getElementById('budgetEventId').value;
                    const budgetAmount = parseFloat(document.getElementById('budgetInput').value);
                    
                    // Update the button's data-budget attribute
                    const btn = document.querySelector('.book-btn[data-event-id="' + eventId + '"]');
                    if (btn) {
                        btn.setAttribute('data-budget', budgetAmount);
                        btn.dataset.budget = budgetAmount;
                        btn.dataset.confirming = '1';
                        setBookUiState(eventId, true);
                    }
                    const hiddenBudgetInput = document.querySelector('#bookForm-' + eventId + ' input[name="budget"]');
                    if (hiddenBudgetInput) {
                        hiddenBudgetInput.value = budgetAmount;
                    }
                    const modalBudgetInput = document.getElementById('eventDetailsBookBudget');
                    if (modalBudgetInput) {
                        modalBudgetInput.value = budgetAmount;
                    }
                    const eventDetailsBtn = document.getElementById('eventDetailsBookBtn');
                    if (eventDetailsBtn && eventDetailsBtn.dataset.eventId === eventId.toString()) {
                        eventDetailsBtn.dataset.budget = budgetAmount;
                        eventDetailsBtn.dataset.confirming = '1';
                        setEventDetailsBookButtonState(true);
                        showEventDetailsBudgetBanner(budgetAmount);
                    }
                    
                    // Reattach listeners to ensure they work with updated budget
                    attachBookButtonListeners();
                    
                    // Close budget modal
                    closeBudgetModal();
                    
                    // Show success message with auto-dismiss
                    const successDiv = document.createElement('div');
                    successDiv.className = 'fixed top-24 right-6 p-3 bg-green-50 border border-green-200 rounded-lg shadow-lg max-w-sm';
                    successDiv.style.zIndex = '9999';
                    successDiv.innerHTML = '<div class="flex items-center gap-2"><i class="fas fa-check-circle text-green-500 text-lg"></i><span class="text-green-800 font-semibold text-sm">Budget Updated Successfully!</span></div>';
                    document.body.appendChild(successDiv);
                    
                    setTimeout(function() {
                        successDiv.style.transition = 'opacity 0.3s ease';
                        successDiv.style.opacity = '0';
                        setTimeout(function() {
                            successDiv.remove();
                        }, 300);
                    }, 3000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating budget. Please try again.');
                });
            });
        }

        // Close modals on outside click & Escape
        document.getElementById('eventDetailsOverlay').addEventListener('click', function(e){
            if(e.target === this) closeEventModal();
        });
        document.getElementById('budgetModal').addEventListener('click', function(e){
            if(e.target === this) closeBudgetModal();
        });
        document.addEventListener('keydown', function(e){
            if(e.key === 'Escape') {
                closeEventModal();
                closeBudgetModal();
            }
        });

    </script>

</body>
</html>

