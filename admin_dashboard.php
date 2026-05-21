<?php
require_once 'config/config.php';
require_once 'auth.php';

guardAdmin();

$userName = $_SESSION['user_name'] ?? 'Admin';

// Enhanced stats queries
$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
$totalUsers = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_events,
        COUNT(CASE WHEN date >= CURDATE() THEN 1 END) as upcoming_events,
        COALESCE((SELECT SUM(budget) FROM events), 0) as total_budget,
        SUM(attendees) as total_attendees,
        COUNT(DISTINCT CASE WHEN b.status != 'cancelled' THEN b.id END) as total_bookings,
        SUM((SELECT SUM(amount) FROM expenses WHERE event_id = e.id)) as total_spent
    FROM events e 
    LEFT JOIN bookings b ON e.id = b.event_id
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['total_users'] = $totalUsers;
$stats['revenue'] = $stats['total_budget']; // Treat budget as revenue for Figma
$stats['budget_utilization'] = $stats['total_budget'] > 0 ? round(($stats['total_spent'] / $stats['total_budget']) * 100, 1) : 0;
$stats['remaining_budget'] = max(0, ($stats['total_budget'] ?? 0) - ($stats['total_spent'] ?? 0));

// Chart data: Monthly budget/spent (last 6 months)
$chartData = [];
$labels = [];
for ($i = 5; $i >= 0; $i--) {
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(budget), 0) as monthly_budget,
            COALESCE(SUM((SELECT SUM(amount) FROM expenses WHERE event_id = e.id)), 0) as monthly_spent
        FROM events e 
        WHERE date BETWEEN ? AND ?
    ");
    $stmt->execute([$monthStart, $monthEnd]);
    $monthStats = $stmt->fetch();
    
    $label = date('M Y', strtotime($monthStart));
    $labels[] = $label;
    $chartData['budget'][] = (float)$monthStats['monthly_budget'];
    $chartData['spent'][] = (float)$monthStats['monthly_spent'];
}

$today = date('Y-m-d');
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($currentMonth < 1) { $currentMonth = 12; $currentYear--; }
if ($currentMonth > 12) { $currentMonth = 1; $currentYear++; }
$monthName = date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfWeek = (int)date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear));

function getDashboardEventColor($status, $date, $today) {
    if ($status === 'completed' || $date < $today) return '#E8E8E8';
    if ($status === 'ongoing' || $date === $today) return '#DFF2F2';
    return '#CFE1D1';
}

$calendarEvents = [];
$eventsStmt = $pdo->query("SELECT e.*, u.name AS user_name FROM events e LEFT JOIN users u ON e.user_id = u.id ORDER BY e.date ASC");
$allEvents = $eventsStmt->fetchAll();
foreach ($allEvents as $evt) {
    $eventDate = $evt['date'] ?? '';
    if (!$eventDate) continue;
    $calendarEvents[] = [
        'id' => $evt['id'],
        'title' => $evt['title'] ?? 'Untitled Event',
        'date' => $eventDate,
        'status' => $evt['status'] ?? 'planned',
        'user_name' => $evt['user_name'] ?? 'Admin',
        'location' => $evt['location'] ?? ''
    ];
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

// Recent events & bookings
$stmt = $pdo->query("
    SELECT e.*, u.name as user_name, 
           (SELECT SUM(amount) FROM expenses WHERE event_id = e.id) as total_spent
    FROM events e LEFT JOIN users u ON e.user_id = u.id 
    ORDER BY e.date DESC LIMIT 5
");
$recentEvents = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT b.*, e.title as event_title, u.name as user_name
    FROM bookings b 
    LEFT JOIN events e ON b.event_id = e.id
    LEFT JOIN users u ON b.user_id = u.id 
    WHERE b.status != 'cancelled'
    ORDER BY b.booking_date DESC LIMIT 5
");
$recentBookings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — CAVENDIA</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Cleaner/professional baseline */
        html { scroll-behavior: smooth; }
        a { color: inherit; }
    
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }


        :root {
            --sage: #A3B18A;
            --sage-dark: #8A9A6D;
            --cream: #F1F2EE;
            --forest: #1B4332;
            --white: #FFFFFF;
            --text-muted: #6B7C6D;
            --border: #D8DDD3;
            --shadow: 0 8px 32px rgba(27,67,50,0.12);
            --shadow-hover: 0 20px 40px rgba(27,67,50,0.2);
        }

        body {
            background: linear-gradient(135deg, var(--cream) 0%, #e8ebe3 100%);
            color: var(--forest);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* ── Sidebar ── (Aligned with admin_event.php) */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 280px;
            height: 100vh;
            background: var(--sage);
            z-index: 100;
            transition: transform 0.3s ease;
            padding-top: 5rem;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 2rem 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 1.5rem;
        }

        .sidebar-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.25rem;
        }

        .sidebar-icon {
            width: 2.5rem;
            height: 2.5rem;
            background: rgba(255,255,255,0.2);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-title h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--white);
        }

        .sidebar-subtitle {
            font-size: 0.875rem;
            color: rgba(255,255,255,0.8);
            font-weight: 400;
        }

        .sidebar-nav {
            padding: 0 2rem 2rem;
        }

        .sidebar-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.875rem 1rem;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 1rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .sidebar-item:hover {
            background: rgba(255,255,255,0.15);
            color: var(--white);
            transform: translateX(0.25rem);
        }

        .sidebar-item.active {
            background: rgba(255,255,255,0.25);
            color: var(--white);
            border-left: 4px solid var(--white);
            font-weight: 600;
        }

        .sidebar-item i {
            width: 1.25rem;
            font-size: 1.1rem;
        }

        /* ── Mobile Toggle ── */
        .mobile-toggle {
            position: fixed;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 200;
            background: var(--sage);
            border: none;
            padding: 1rem;
            border-radius: 1rem;
            color: var(--white);
            font-size: 1.25rem;
            cursor: pointer;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .mobile-toggle:hover {
            background: var(--forest);
            transform: scale(1.05);
        }

        .overlay {
            position: fixed;
            inset: 0;
            background: rgba(27,67,50,0.5);
            backdrop-filter: blur(4px);
            z-index: 150;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* ── Main Content ── */
        .main-content {
            margin-right: 0;
            transition: margin-right 0.3s ease;
            padding: 6rem 2rem 2rem;
            min-height: 100vh;
        }

        .main-desktop {
            margin-left: 280px;
        }

        /* ── Header ── */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 3rem;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .header-title {
            font-size: 3rem;
            font-weight: 800;
            background: linear-gradient(135deg, var(--forest), var(--sage-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.1;
            margin-bottom: 0.25rem;
        }

        .header-subtitle {
            font-size: 1.125rem;
            color: var(--text-muted);
            font-weight: 400;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        /* Professional polish */
        .card, .chart-card, .recent-section, .stat-card {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .search-field::placeholder {
            color: rgba(107,124,109,0.75);
        }

        .search-input {
            position: relative;
            flex: 1;
            max-width: 20rem;
        }

        .search-field {
            width: 100%;
            padding: 1rem 1rem 1rem 2.75rem;
            border: 2px solid var(--border);
            border-radius: 1.5rem;
            background: var(--white);
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .search-field:focus {
            outline: none;
            border-color: var(--sage);
            box-shadow: 0 0 0 4px rgba(163,177,138,0.15);
            background: var(--cream);
        }

        .search-icon {
            position: absolute;
            left: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .profile-btn {
            padding: 0.875rem;
            background: var(--white);
            border-radius: 1.5rem;
            border: 2px solid var(--border);
            color: var(--forest);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
        }

        .profile-btn:hover {
            border-color: var(--sage);
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .notification-badge {
            position: absolute;
            top: -0.25rem;
            right: -0.25rem;
            width: 1.25rem;
            height: 1.25rem;
            background: #ef4444;
            color: var(--white);
            border-radius: 50%;
            font-size: 0.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-avatar {
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(135deg, var(--sage), var(--sage-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-weight: 700;
            font-size: 0.875rem;
            margin-right: 0.5rem;
        }

        /* ── Stats Grid ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            margin-bottom: 3rem;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.25rem;
            }
        }

        /* Matcha/Bookings-like stat card sizing */
        .stat-card {
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(216,221,211,0.9);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 18px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform .2s ease, box-shadow .2s ease;
            position: relative;
            overflow: hidden;
            min-height: 94px;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--sage), var(--sage-dark));
        }

        .stat-card:hover {
            box-shadow: var(--shadow-hover);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display:flex;
            align-items:center;
            justify-content:center;
            background: rgba(163,177,138,0.18);
            color: var(--sage-dark);
            font-size: 1rem;
        }

        .stat-trend {
            font-size: 1.5rem;
            color: var(--sage);
        }

        .stat-number {
            font-size: 34px;
            font-weight: 1000;
            color: var(--forest);
            margin-top: 6px;
            line-height: 1;
        }

        .stat-label {
            font-size: 14px;
            font-weight: 800;
            color: var(--sage-dark);
            text-transform: uppercase;
            letter-spacing: 0.06em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* ── Charts Section ── */
        .charts-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .chart-card {
            background: var(--white);
            border-radius: 2rem;
            padding: 2rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }

        .chart-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--forest);
            margin-bottom: 1.5rem;
        }

        .chart-container {
            height: 20rem;
            position: relative;
        }

        .budget-progress {
            margin-bottom: 1.5rem;
        }

        .progress-labels {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .progress-bar {
            width: 100%;
            height: 1rem;
            background: var(--cream);
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0,0,0,0.05);
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--sage), var(--sage-dark));
            border-radius: 1rem;
            transition: width 1s ease;
            box-shadow: 0 0 20px rgba(163,177,138,0.3);
        }

        /* ── Calendar Styles ── */
        .calendar-section {
            background: var(--white);
            border-radius: 2rem;
            padding: 2rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0,1fr));
            gap: 12px;
            margin-top: 1rem;
        }

        .calendar-day-header {
            font-size: .8rem;
            font-weight: 700;
            color: var(--sage-dark);
            text-transform: uppercase;
            letter-spacing: .08em;
            text-align: center;
        }

        .calendar-day {
            min-height: 120px;
            padding: 10px;
            border-radius: 1.25rem;
            background: var(--cream);
            border: 1px solid var(--border);
            position: relative;
            transition: all .25s ease;
        }

        .calendar-day:hover {
            background: #f4f7f2;
        }

        .calendar-day.today {
            border-color: var(--sage);
            background: #eff7ea;
        }

        .calendar-day.empty {
            background: transparent;
            border: none;
        }

        .calendar-day .day-number {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--forest);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #f1f2ee;
        }

        .calendar-event-pill {
            display: block;
            padding: 0.45rem 0.75rem;
            border-radius: 9999px;
            font-size: .75rem;
            font-weight: 700;
            color: #1f4333;
            margin-top: 0.65rem;
            background: rgba(163,177,138,0.15);
            border: 1px solid rgba(163,177,138,0.25);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .calendar-event-pill strong {
            font-weight: 800;
        }

        .calendar-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .calendar-nav a {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .75rem 1rem;
            border-radius: 1rem;
            border: 1px solid var(--border);
            background: var(--white);
            text-decoration: none;
            color: var(--forest);
            font-weight: 700;
        }

        .calendar-nav a:hover {
            background: var(--cream);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .calendar-title {
            margin: 0;
        }

        .calendar-subtitle {
            color: var(--text-muted);
            margin: 0.5rem 0 0;
            max-width: 620px;
        }

        .event-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: 1rem;
            background: var(--cream);
        }

        .upcoming-empty {
            padding: 1.75rem;
            border-radius: 1.5rem;
            border: 1px dashed var(--border);
            background: #fafaf7;
            color: var(--text-muted);
            text-align: center;
        }

        .upcoming-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        /* ── Recent Sections ── */
        .recent-section {
            background: var(--white);
            border-radius: 2rem;
            padding: 2rem;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .recent-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--forest);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .recent-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.25rem;
        }

        .recent-card {
            background: var(--cream);
            border-radius: 1.5rem;
            padding: 1.5rem;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .recent-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--sage), var(--sage-dark));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .recent-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-hover);
        }

        .recent-card:hover::before {
            opacity: 1;
        }

        .recent-avatar {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, var(--sage), var(--sage-dark));
            color: var(--white);
            font-weight: 700;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            flex-shrink: 0;
        }

        .recent-title-text {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--forest);
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .recent-place {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-bottom: 0.125rem;
        }

        .recent-meta {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .recent-stats {
            display: flex;
            gap: 1rem;
            margin-top: 0.75rem;
            font-size: 0.875rem;
        }

        .stat-badge {
            padding: 0.375rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            border: 1px solid var(--border);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-desktop {
                margin-left: 0 !important;
            }
            .charts-section {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 4rem 1rem 2rem;
            }
            .header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .header-title {
                font-size: 2.25rem;
            }
            .recent-grid {
                grid-template-columns: 1fr;
            }
            .calendar-nav {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Toggle -->
    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Overlay -->
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">
                <div class="sidebar-icon">
                    <i class="fas fa-crown"></i>
                </div>
                <h2>Admin Portal</h2>
            </div>
            <p class="sidebar-subtitle">Welcome, <?= htmlspecialchars($userName) ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="sidebar-item active">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin_event.php" class="sidebar-item">
                <i class="fas fa-calendar-alt"></i>
                <span>Manage Events</span>
            </a>
            <a href="admin_users.php" class="sidebar-item">
                <i class="fas fa-users"></i>
                <span>Manage User</span>
            </a>

            <a href="admin_bookings.php" class="sidebar-item">
                <i class="fas fa-clipboard-list"></i>
                <span>Bookings</span>
            </a>

            <a href="admin_messages.php" class="sidebar-item">
                <i class="fas fa-comments"></i>
                <span>Messages</span>
            </a>

<script>
// Fix: prevent the dashboard chat widget from blocking sidebar clicks
// Some browsers can keep the widget overlay capturing clicks.
(function(){
  const overlay = document.getElementById('chatWidgetOverlay');
  if(!overlay) return;
  overlay.style.pointerEvents = 'none';
})();
</script>




            <a href="admin_logout.php" class="sidebar-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
<main class="main-content main-desktop" id="mainContent">

        <!-- Header -->
        <header class="header">
            <div>
                <h1 class="header-title">Dashboard</h1>
                <p class="header-subtitle">Manage events, users & analytics</p>
            </div>
            <div class="header-actions">
                <div class="search-input">
                    <i class="fas fa-search search-icon"></i>
                    <input id="dashboardSearch" type="text" class="search-field" placeholder="Search events, users, bookings..." autocomplete="off">
                </div>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: rgba(220, 252, 231, 1); color: #16a34a;">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-number"><?= $stats['total_events'] ?></div>
                <div class="stat-label">Total Events</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: rgba(253, 186, 116, 0.25); color: #f97316;">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-number"><?= $stats['total_users'] ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: rgba(196, 181, 253, 0.35); color: #7c3aed;">
                        <i class="fas fa-sack-dollar"></i>
                    </div>
                </div>
                <div class="stat-number">₱<?= number_format($stats['revenue']) ?></div>
                <div class="stat-label">Revenue</div>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon" style="background: rgba(147,197,253,0.35); color: #2563eb;">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
                <div class="stat-number"><?= $stats['total_bookings'] ?></div>
                <div class="stat-label">Bookings</div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="charts-section">
            <div class="chart-card">
                <h3 class="chart-title">Revenue Trends</h3>
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">Budget Overview</h3>
                <div class="budget-progress">
                    <div class="progress-labels">
                        <span>Spent</span>
                        <span><?= $stats['budget_utilization'] ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $stats['budget_utilization'] ?>%"></div>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="budgetChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Event Calendar (MOVED HERE - BEFORE RECENT EVENTS) -->
        <div class="calendar-section">
            <div class="calendar-header">
                <div>
                    <h3 class="calendar-title" style="font-size:1.5rem;font-weight:800;color:var(--forest);margin:0;">Event Calendar</h3>
                    <p class="calendar-subtitle">A quick calendar view of all scheduled events and upcoming activity for the selected month.</p>
                </div>
                <div class="calendar-nav">
                    <a href="<?= htmlspecialchars($prevLink) ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                    <span style="font-weight:700;color:var(--forest);font-size:1rem;"><?= htmlspecialchars($monthName) ?></span>
                    <a href="<?= htmlspecialchars($nextLink) ?>">Next <i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
            <div class="calendar-grid">
                <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dayHeader): ?>
                    <div class="calendar-day-header"><?= $dayHeader ?></div>
                <?php endforeach; ?>
                <?php for ($space = 0; $space < $firstDayOfWeek; $space++): ?>
                    <div class="calendar-day empty"></div>
                <?php endfor; ?>
                <?php for ($day = 1; $day <= $daysInMonth; $day++):
                    $fullDate = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
                    $isToday = $fullDate === $today;
                    $dayEvents = array_filter($calendarEvents, fn($e) => $e['date'] === $fullDate);
                ?>
                    <div class="calendar-day <?= $isToday ? 'today' : '' ?>">
                        <span class="day-number"><?= $day ?></span>
                        <?php foreach ($dayEvents as $evt): ?>
                            <span class="calendar-event-pill" title="<?= htmlspecialchars($evt['title']) ?>"><?= htmlspecialchars(substr($evt['title'], 0, 24)) ?><?= strlen($evt['title']) > 24 ? '…' : '' ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="upcoming-header">
                <h4 style="margin:0;font-size:1rem;font-weight:700;color:var(--forest);">Upcoming events this month</h4>
                <span style="color:var(--text-muted);font-size:.95rem;">Showing <?= htmlspecialchars($monthName) ?></span>
            </div>
            <?php if (empty($upcomingMonthEvents)): ?>
                <div class="upcoming-empty">No upcoming events for this month.</div>
            <?php else: ?>
                <div style="display:grid;gap:12px; margin-top:1rem;">
                    <?php foreach ($upcomingMonthEvents as $evt): ?>
                        <div class="event-list-item">
                            <div>
                                <div style="font-weight:700;color:var(--forest);"><?= htmlspecialchars($evt['title']) ?></div>
                                <div style="color:var(--text-muted);font-size:.9rem;"><?= date('M j, Y', strtotime($evt['date'])) ?> · <?= htmlspecialchars($evt['location'] ?: 'No location') ?></div>
                            </div>
                            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.35rem;">
                                <span style="font-size:.82rem;color:var(--forest);font-weight:700;"><?= htmlspecialchars(ucfirst($evt['status'])) ?></span>
                                <span style="font-size:.82rem;color:var(--text-muted);">By <?= htmlspecialchars($evt['user_name']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="recent-section">
            <h3 class="recent-title">
                <i class="fas fa-clock" style="color: var(--sage);"></i>
                Recent Events
            </h3>
            <div class="recent-grid">
                <?php foreach (array_slice($recentEvents, 0, 6) as $event): ?>
                <div class="recent-card" style="display: flex; align-items: flex-start; gap: 1rem;">
                    <div class="recent-avatar"><?= strtoupper(substr($event['title'], 0, 2)) ?></div>
                    <div style="flex: 1; min-width: 0;">
                        <div class="recent-title-text"><?= htmlspecialchars(substr($event['title'], 0, 50)) ?><?= strlen($event['title']) > 50 ? '...' : '' ?></div>
                        <div class="recent-place"><?= htmlspecialchars($event['location'] ?? 'Location TBA') ?></div>
                        <div class="recent-meta"><?= htmlspecialchars($event['user_name'] ?? 'Direct') ?></div>
                        <div class="recent-stats">
                            <span class="stat-badge"><?= date('M j', strtotime($event['date'])) ?></span>
                            <span class="stat-badge">₱<?= number_format($event['budget']) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="recent-section">
            <h3 class="recent-title">
                <i class="fas fa-clipboard-list" style="color: var(--sage);"></i>
                Recent Bookings
            </h3>
            <div class="recent-grid">
                <?php foreach (array_slice($recentBookings, 0, 6) as $booking): ?>
                <div class="recent-card" style="display: flex; align-items: flex-start; gap: 1rem;">
                    <div class="recent-avatar"><?= strtoupper(substr($booking['event_title'], 0, 2)) ?></div>
                    <div style="flex: 1; min-width: 0;">
                        <div class="recent-title-text"><?= htmlspecialchars($booking['event_title']) ?></div>
                        <div class="recent-meta"><?= htmlspecialchars($booking['user_name']) ?></div>
                        <div class="recent-stats">
                            <span class="stat-badge"><?= $booking['guest_count'] ?> guests</span>
                            <span class="stat-badge"><?= date('M j', strtotime($booking['booking_date'])) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Messenger-style Chat Widget (Dashboard Popup) -->
    <button id="chatWidgetBtn" type="button" class="fixed" style="right: 24px; bottom: 24px; z-index: 9999; width: 56px; height: 56px; border-radius: 9999px; background: linear-gradient(135deg, #1a472a, #2d6a4f); display:flex; align-items:center; justify-content:center; box-shadow: 0 16px 48px rgba(27,67,50,0.25); cursor:pointer; border:none;">
        <i class="fas fa-comments" style="color: white; font-size: 20px;"></i>
    </button>

<div id="chatWidgetOverlay" class="fixed inset-0" style="z-index: 10000; display:none; background: rgba(15,23,42,0.35); backdrop-filter: blur(8px); pointer-events:none;"></div>

    <div id="chatWidgetModal" class="fixed" style="z-index: 10001; display:none; right: 24px; bottom: 92px; width: 360px; max-width: calc(100vw - 48px); height: 520px; background:#fff; border-radius: 22px; box-shadow: 0 25px 50px rgba(0,0,0,0.25); overflow:hidden; border: 1px solid rgba(163,177,138,0.25);">
        <div style="padding: 14px 16px; background: linear-gradient(135deg, #3d5a40, #4a6b50); color:white; display:flex; align-items:center; justify-content:space-between;">
            <div style="display:flex; align-items:center; gap:10px;">
                <div style="width:36px; height:36px; border-radius: 14px; background: rgba(220,252,231,0.25); display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-headset" style="color:#dcfce7;"></i>
                </div>
                <div>
                    <div style="font-weight:900; line-height:1.1;">CAVENDIA Support</div>
                    <div style="font-size:12px; opacity:0.9;">Online</div>
                </div>
            </div>
            <button id="chatWidgetClose" type="button" style="background: rgba(255,255,255,0.15); border:none; color:white; width:36px; height:36px; border-radius: 14px; cursor:pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div id="chatWidgetMessageList" style="height: calc(100% - 140px); overflow-y:auto; padding: 12px 12px; background:#faf9f6;">
            <div id="chatWidgetEmpty" style="height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; color:#8b9a7a; gap:8px;">
                <i class="fas fa-comments text-4xl" style="color:#c5d8bf;"></i>
                <div style="font-weight:700;">No messages yet</div>
                <div style="font-size:12px; text-align:center;">Start a conversation with our support team</div>
            </div>
            <div id="chatWidgetBubbles"></div>
        </div>

        <form id="chatWidgetForm" style="padding: 10px 12px; border-top: 1px solid #e8ebe3; background: white;" autocomplete="off">
            <div style="position:relative;">
                <input id="chatWidgetInput" type="text" name="message" placeholder="Type your message..." required style="width:100%; padding: 12px 44px 12px 14px; border: 2px solid #e3ebe0; border-radius: 24px; outline:none; font-size: 14px;" />
                <button type="submit" style="position:absolute; right: 8px; top:50%; transform: translateY(-50%); width: 34px; height:34px; border-radius: 50%; background:#1a472a; color:white; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>
    </div>


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
                    const safeMsg = escapeHtml(msg.message);
                    const senderName = msg.user_name ? String(msg.user_name) : (senderType === 'user' ? 'User' : 'Admin');
                    const createdAt = msg.created_at ? String(msg.created_at) : '';

                    return `
                        <div class="message-bubble ${senderType}" style="max-width:75%; padding:10px 14px; border-radius:18px; margin-bottom:10px; ${senderType === 'user' ? 'margin-left:auto; background: linear-gradient(135deg, #1a472a, #2d6a4f); color:#fff;' : 'background:#fff; border:1px solid #e8ebe3; color:#353f2d;'}">
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
                bubbles.forEach(html => {
                    const wrapper = document.createElement('div');
                    wrapper.innerHTML = html;
                    messageList.appendChild(wrapper.firstElementChild);
                });

                const container = document.getElementById('chatWidgetMessageList');
                if (container) container.scrollTop = container.scrollHeight;
            }

            async function fetchMessages() {
                const res = await fetch('api.php?type=chat', { method: 'GET' });
                if (!res.ok) return;
                const data = await res.json();
                if (!data.success || !Array.isArray(data.messages)) return;

                const messages = data.messages.slice().reverse();
                const sig = messages.length ? (messages[messages.length - 1].sender_type + '|' + messages[messages.length - 1].message + '|' + messages[messages.length - 1].created_at) : null;
                if (sig && sig === lastMsgSig) return;
                lastMsgSig = sig;

                renderMessages(messages);
            }

            function startPolling() {
                if (pollingTimer) return;
                fetchMessages().catch(() => {});
                pollingTimer = setInterval(() => fetchMessages().catch(() => {}), 2000);
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

                await fetch('api.php?type=chat', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: msg })
                }).catch(() => {});

                input.value = '';
                fetchMessages().catch(() => {});
            });

            // start closed
            closeWidget();
        })();

        // Mobile sidebar

        const mobileToggle = document.getElementById('mobileToggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('overlay');
        const mainContent = document.getElementById('mainContent');

        // Live search (client-side filtering)
        const dashboardSearch = document.getElementById('dashboardSearch');
        const recentEventCards = Array.from(document.querySelectorAll('.recent-section:nth-of-type(2) .recent-card'));
        const recentBookingCards = Array.from(document.querySelectorAll('.recent-section:last-of-type .recent-card'));

        function normalizeText(s) {
            return (s || '').toString().toLowerCase().trim();
        }

        function applyDashboardSearch(term) {
            const q = normalizeText(term);
            const isEmpty = q.length === 0;

            const filterCards = (cards) => {
                cards.forEach(card => {
                    const text = normalizeText(card.innerText);
                    card.style.display = (!isEmpty && text.indexOf(q) === -1) ? 'none' : '';
                });
            };

            filterCards(recentEventCards);
            filterCards(recentBookingCards);
        }

        if (dashboardSearch) {
            let t = null;
            dashboardSearch.addEventListener('input', (e) => {
                const v = e.target.value;
                window.clearTimeout(t);
                t = window.setTimeout(() => applyDashboardSearch(v), 120);
            });
        }

        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            overlay.classList.toggle('active');
            document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
        });

        overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        });

        // Charts
        document.addEventListener('DOMContentLoaded', () => {
            const revenueCtx = document.getElementById('revenueChart')?.getContext('2d');
            if (revenueCtx) {
                new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: <?= json_encode($labels) ?>,
                        datasets: [{
                            label: 'Budget',
                            data: <?= json_encode($chartData['budget']) ?>,
                            borderColor: 'var(--sage)',
                            backgroundColor: 'rgba(163,177,138,0.2)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 3
                        }, {
                            label: 'Spent',
                            data: <?= json_encode($chartData['spent']) ?>,
                            borderColor: '#ef4444',
                            backgroundColor: 'rgba(239,68,68,0.1)',
                            tension: 0.4,
                            fill: true,
                            borderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'top', labels: { color: 'var(--forest)' } } },
                        scales: {
                            y: { beginAtZero: true, grid: { color: 'rgba(216,221,211,0.3)' }, ticks: { color: 'var(--text-muted)' } },
                            x: { grid: { color: 'rgba(216,221,211,0.3)' }, ticks: { color: 'var(--text-muted)' } }
                        }
                    }
                });
            }

            const budgetCtx = document.getElementById('budgetChart')?.getContext('2d');
            if (budgetCtx) {
                new Chart(budgetCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Spent', 'Remaining'],
                        datasets: [{
                            data: [<?= $stats['total_spent'] ?? 0 ?>, <?= $stats['remaining_budget'] ?? 0 ?>],
                            backgroundColor: ['#ef4444', '#1B4332'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom', labels: { color: 'var(--forest)' } } }
                    }
                });
            }
        });
    </script>
</body>
</html>


