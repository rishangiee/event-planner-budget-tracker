<?php
require_once 'config/config.php';
requireUser();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

$userEmail = $_SESSION['user_email'] ?? 'user@cavendia.com';

// Ensure bookings table can store user budget when the user books an event.
$hasBookingBudgetColumn = false;
try {
    $column = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'budget'")->fetch(PDO::FETCH_ASSOC);
    if ($column) {
        $hasBookingBudgetColumn = true;
    } else {
        $pdo->exec("ALTER TABLE bookings ADD COLUMN budget DECIMAL(12,2) NOT NULL DEFAULT 0");
        $hasBookingBudgetColumn = true;
    }
} catch (PDOException $e) {
    // Ignore schema changes when not possible; booking UI will still fall back to event budget.
}

// ---- Booking action (when coming from dashboard.php) ----
$bookEventId = $_GET['event'] ?? '';
$passedBudget = isset($_GET['budget']) ? (float)$_GET['budget'] : null;
// NOTE: Booking should always start as PENDING.
// Ignore any incoming status query param (it caused bookings to be created as confirmed).
$guestCount = 1;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $bookEventId) {

    $budgetToUse = $passedBudget !== null ? $passedBudget : null;

    // Fetch event (some deployments may not have budget column on bookings table; we only use it for display)
    $stmt = $pdo->prepare("SELECT id, title, date, location, budget FROM events WHERE id = ? AND status != 'cancelled' LIMIT 1");
    $stmt->execute([$bookEventId]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);


    if ($event) {
        $check = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ? AND event_id = ? AND status != 'cancelled' LIMIT 1");
        $check->execute([$userId, $bookEventId]);

        if (!$check->fetch()) {
            $statusToSet = 'pending';
            $budgetToUse = $passedBudget !== null ? $passedBudget : (float)($event['budget'] ?? 0);

            if ($hasBookingBudgetColumn) {
                $ins = $pdo->prepare(
                    "INSERT INTO bookings (user_id, event_id, event_title, event_date, event_location, guest_count, status, budget)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $ins->execute([
                    $userId,
                    $bookEventId,
                    $event['title'],
                    $event['date'],
                    $event['location'] ?? '',
                    $guestCount,
                    $statusToSet,
                    $budgetToUse,
                ]);
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO bookings (user_id, event_id, event_title, event_date, event_location, guest_count, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $ins->execute([
                    $userId,
                    $bookEventId,
                    $event['title'],
                    $event['date'],
                    $event['location'] ?? '',
                    $guestCount,
                    $statusToSet
                ]);

                if ($budgetToUse !== null) {
                    try {
                        $upd = $pdo->prepare("UPDATE bookings SET budget = ? WHERE user_id = ? AND event_id = ? AND status != 'cancelled'");
                        $upd->execute([$budgetToUse, $userId, $bookEventId]);
                    } catch (PDOException $e) {
                        // Ignore if the column doesn't exist; UI will fall back to event budget.
                    }
                }
            }

            try {
                $pdo->prepare("UPDATE events SET attendees = attendees + ? WHERE id = ?")->execute([$guestCount, $bookEventId]);
            } catch (PDOException $e) {
                // Ignore if the events table does not have an attendees column.
            }

            $_SESSION['flash_message'] = 'Booking confirmed!';
        } else {
            $_SESSION['flash_message'] = 'You already booked this event.';
        }
    } else {
        $_SESSION['flash_message'] = 'Event not found or cancelled.';
    }

    header('Location: booking.php');
    exit;
}

// Fetch current user's real profile data first
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();

// Use real DB values for contact info
$userPhone = $currentUser['contact_number'] ?? '';
$userAddress = $currentUser['address'] ?? 'N/A';
$userFullName = trim(($currentUser['firstname'] ?? '') . ' ' . ($currentUser['lastname'] ?? '')) ?: $userName;
// Keep email aligned with DB to avoid session fallback issues
$userEmail = $currentUser['email'] ?? ($userEmail ?? '');


// Fetch user's bookings from DB (pending/confirmed should show here)
// NOTE: This UI is based on `bookings.status` not `events.status`.
$q = trim($_GET['q'] ?? '');

$sql = "SELECT 
        b.id, b.event_id, b.event_title, b.event_date, b.event_location,
        b.guest_count, b.status, b.booking_date";

if ($hasBookingBudgetColumn) {
    $sql .= ", b.budget AS booking_budget";
}

$sql .= ",
        e.title AS db_event_title,
        e.date AS db_event_date,
        e.location AS db_event_location,
        e.max_attendees AS db_max_attendees,
        e.description AS db_event_description,
        e.budget AS db_event_budget,
        (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE event_id = e.id) AS event_spent
     FROM bookings b
     LEFT JOIN events e ON e.id = b.event_id
     WHERE b.user_id = ?";

$params = [$userId];

if ($q !== '') {
    // Match the search against booking + joined event fields
    $like = '%' . $q . '%';
    $sql .= " AND (
        b.id = ?
        OR b.event_title LIKE ?
        OR b.event_location LIKE ?
        OR b.event_date LIKE ?
        OR b.status LIKE ?
        OR e.title LIKE ?
        OR e.location LIKE ?
        OR e.date LIKE ?
    )";

    // If it's not numeric, b.id = ? will never match (that's fine)
    $params[] = is_numeric($q) ? (int)$q : -1;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY b.booking_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rawBookings = $stmt->fetchAll();

$bookings = [];
foreach ($rawBookings as $b) {
    $status = $b['status'] ?? 'pending';

    // Normalize to UI set (pending/approved/rejected)
    $uiStatus = match($status) {
        'confirmed', 'approved' => 'approved',
        'pending' => 'pending',
        'cancelled' => 'rejected',
        default => (in_array($status, ['confirmed', 'approved'], true) ? 'approved' : 'pending'),
    };

    $bookings[] = [
        'id' => $b['id'],
        'event_id' => (int)$b['event_id'],
        'event_title' => $b['event_title'] ?: ($b['db_event_title'] ?? ''),
        'event_date' => $b['event_date'] ?: ($b['db_event_date'] ?? ''),
        'status' => $uiStatus,
        'guest_count' => (int)($b['guest_count'] ?? 0),
        'max_guests' => (int)($b['db_max_attendees'] ?? 200),
        'customer_name' => $userFullName,
        'email' => $userEmail,
        'phone' => $userPhone,
        'address' => $userAddress,
        'description' => $b['event_description'] ?? ($b['db_event_description'] ?? 'No description provided'),
        'package' => ($uiStatus === 'approved') ? 'Approved Package' : 'Pending Package',
        'budget' => (float)($b['booking_budget'] ?? $b['budget'] ?? $b['db_event_budget'] ?? 0),
        'total_spent' => (float)($b['event_spent'] ?? 0),
        'remaining_budget' => max(0, (float)($b['booking_budget'] ?? $b['budget'] ?? $b['db_event_budget'] ?? 0) - (float)($b['event_spent'] ?? 0)),
        'budget_utilization' => ((float)($b['booking_budget'] ?? $b['budget'] ?? $b['db_event_budget'] ?? 0) > 0) ? min(100, ((float)($b['event_spent'] ?? 0) / (float)($b['booking_budget'] ?? $b['budget'] ?? $b['db_event_budget'] ?? 0)) * 100) : 0,
        'created_at' => $b['booking_date'] ? date('Y-m-d', strtotime($b['booking_date'])) : date('Y-m-d'),
    ];
}


$totalBookings = count($bookings);
$pendingCount = count(array_filter($bookings, fn($b) => $b['status'] === 'pending'));
$approvedCount = count(array_filter($bookings, fn($b) => $b['status'] === 'approved'));
$rejectedCount = count(array_filter($bookings, fn($b) => $b['status'] === 'rejected'));

$statusCards = [
    ['label' => 'Total Bookings', 'count' => $totalBookings, 'color' => '#3b82f6', 'bg' => '#eff6ff', 'icon' => 'fa-calendar-check'],
    ['label' => 'Pending', 'count' => $pendingCount, 'color' => '#eab308', 'bg' => '#fefce8', 'icon' => 'fa-clock'],
    ['label' => 'Approved', 'count' => $approvedCount, 'color' => '#16a34a', 'bg' => '#f0fdf4', 'icon' => 'fa-check-circle'],
    ['label' => 'Rejected', 'count' => $rejectedCount, 'color' => '#dc2626', 'bg' => '#fef2f2', 'icon' => 'fa-times-circle'],
];

function getStatusBadgeStyle($status) {
    return match($status) {
        'approved' => ['bg' => '#dcfce7', 'color' => '#15803d', 'text' => 'Approved'],
        'rejected' => ['bg' => '#fee2e2', 'color' => '#991b1b', 'text' => 'Rejected'],
        default => ['bg' => '#fef9c3', 'color' => '#854d0e', 'text' => 'Pending'],
    };
}

function getStatusBanner($status) {
    return match($status) {
        'approved' => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'color' => '#166534', 'icon' => 'fa-info-circle', 'text' => 'Your booking has been approved. You can now view your invoice and manage your event details.'],
'rejected' => ['bg' => '#fef2f2', 'border' => '#fecaca', 'color' => '#991b1b', 'icon' => 'fa-info-circle', 'text' => 'Your booking has been rejected. Please contact support for more information.'],
        default => ['bg' => '#fefce8', 'border' => '#fde047', 'color' => '#854d0e', 'icon' => 'fa-info-circle', 'text' => 'Your booking is under review. We\'ll notify you once it\'s processed.'],
    };
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - CAVENDIA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f0f2f5; }

        /* Minimal dashboard-like nav styles */
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
        .nav-item:hover { background: #f2f3f5; color: #1877f2; }
        .nav-item.active { background: #e4f0ff; color: #1877f2; }
        .nav-item i { font-size: 20px; }

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
            text-decoration: none;
            background: transparent;
        }
        .filter-option:hover { background: #f0f2f5; }

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
        .divider { border-top: 1px solid #e8ebe3; margin: 1.5rem 0; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f4f7f2; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #c5d8bf, #b8c4a9); border-radius: 10px; }

        /* Status Cards */
        .status-card {
            background: #faf9f6;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 12px rgba(53,63,45,0.06);
            transition: all 0.2s ease;
            border: 1px solid #f0f0ec;
        }
        .status-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(53,63,45,0.1);
        }
        .status-card.empty-card {
            opacity: 0.55;
            filter: grayscale(0.4);
        }
        .status-icon-wrap {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }
        .status-count {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 6px;
        }
        .status-label {
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #62744f;
        }

        /* Booking Card */
        .booking-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #f0f0ec;
            box-shadow: 0 2px 10px rgba(53,63,45,0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .booking-card:hover {
            box-shadow: 0 6px 24px rgba(53,63,45,0.1);
        }
        .booking-header {
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .status-banner {
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border-left: 4px solid transparent;
        }
        .status-pill {
            padding: 5px 14px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .booking-body {
            padding: 1.25rem 1.5rem;
        }
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .info-col h4 {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #62744f;
            margin-bottom: 0.75rem;
        }
        .info-row {
            margin-bottom: 0.75rem;
        }
        .info-row .label {
            font-size: 0.78rem;
            font-weight: 600;
            color: #8b9a7a;
            display: block;
            margin-bottom: 2px;
        }
        .info-row .value {
            font-size: 0.9rem;
            color: #353f2d;
            font-weight: 500;
            word-break: break-word;
        }
        .info-row .value.email-val {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 100%;
            display: block;
        }
        .action-menu {
            position: relative;
        }
        .action-btn {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f4f7f2;
            color: #4d5b3f;
            cursor: pointer;
            border: none;
            transition: all 0.15s;
        }
        .action-btn:hover { background: #e8ebe3; }
        .action-dropdown {
            position: absolute;
            right: 0;
            top: 110%;
            background: #ffffff;
            border-radius: 14px;
            box-shadow: 0 8px 30px rgba(53,63,45,0.14);
            border: 1px solid #e8ebe3;
            min-width: 180px;
            display: none;
            z-index: 100;
            overflow: hidden;
        }
        .action-dropdown.active { display: block; }
        .action-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            font-size: 0.85rem;
            color: #353f2d;
            cursor: pointer;
            transition: background 0.15s;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
        }
        .action-item:hover { background: #f4f7f2; }
        .action-item.danger { color: #991b1b; }
        .action-item.danger:hover { background: #fef2f2; }
        .action-item.disabled {
            opacity: 0.45;
            cursor: not-allowed;
        }

        /* Modal */
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
            width: 100%; max-width: 420px; max-height: 90vh; overflow-y: auto;
            margin: 1rem;
            padding: 1.5rem;
        }
        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .btn-solid {
            padding: 12px 22px; border-radius: 12px; font-weight: 600;
            font-size: 14px; cursor: pointer; border: none;
            transition: all 0.2s ease;
        }
        .btn-sage { background: #1a472a; color: #fff; }
        .btn-sage:hover { background: #2d6a4f; }
        .btn-outline {
            padding: 12px 22px; border-radius: 12px; font-weight: 600;
            border: 2px solid #d4dbc9; background: #fff; color: #4d5b3f;
            transition: all 0.2s ease; cursor: pointer;
        }
        .btn-outline:hover { background: #f4f7f2; }
        .guest-input {
            width: 100%; padding: 14px 16px;
            border: 2px solid #e3ebe0; border-radius: 14px;
            font-size: 16px; background: #ffffff;
            transition: all 0.3s ease; color: #293926;
            text-align: center;
        }
        .guest-input:focus {
            outline: none; border-color: #7aa370;
            box-shadow: 0 0 0 4px rgba(122,163,112,0.15);
        }

        @media (max-width: 640px) {
            .info-grid { grid-template-columns: 1fr; }
        }
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
                    placeholder="Search bookings..."
                    class="flex-1 px-3 py-2 rounded-lg border border-gray-200 bg-white text-sm text-gray-900 outline-none focus:ring-2 focus:ring-blue-200"
                >
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-semibold bg-blue-500 hover:bg-blue-600 text-white transition-all flex items-center gap-1">
                    <i class="fas fa-search"></i>
                </button>
            </form>

            <!-- Centered Navigation -->
            <div class="flex items-center gap-6 flex-1 justify-center px-4">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-home"></i>
                    <span>Home</span>
                </a>
                <a href="calendar.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendar</span>
                </a>
                <a href="booking.php" class="nav-item active">
                    <i class="fas fa-bookmark"></i>
                    <span>Bookings</span>
                </a>
            </div>

            <div class="flex items-center gap-2 flex-shrink-0">
                <div class="relative">
                    <div class="flex items-center gap-2">
                        <span class="hidden sm:inline text-sm font-semibold text-gray-900">Welcome back, <?php echo htmlspecialchars($userName); ?>!</span>
                        <div class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold cursor-pointer hover:bg-blue-700" role="button" tabindex="0" aria-label="User menu">
                            <?php echo strtoupper(substr($userName, 0, 2)); ?>
                        </div>
                    </div>
                    <div class="filter-dropdown" id="userMenu">
                        <a href="profile.php" class="filter-option" style="text-decoration:none;"><i class="fas fa-user mr-3"></i>Profile</a>
                        <a href="index.php" class="filter-option" style="text-decoration:none;"><i class="fas fa-sign-out-alt mr-3"></i>Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main class="pt-24 pb-8 px-6 max-w-6xl mx-auto">
        <!-- Page Header (subtle, matches dashboard spacing) -->
        <div class="mb-4" style="margin-top: 40px;">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="fas fa-bookmark text-blue-600"></i>
                        <span>My Bookings</span>
                    </h2>
                    <p class="text-sm mt-1 text-gray-600">Manage and track your event bookings</p>
                </div>
                <div class="hidden sm:block text-right">
                    <span class="text-xs font-semibold px-3 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-100">
                        <?php echo $totalBookings; ?> total
                    </span>
                </div>
            </div>
        </div>

        <!-- Status Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
            <?php foreach ($statusCards as $sc):
                $isEmpty = $sc['count'] === 0;
            ?>
            <div class="status-card <?php echo $isEmpty ? 'empty-card' : ''; ?>">
                <div class="status-icon-wrap" style="background:<?php echo $sc['bg']; ?>;">
                    <i class="fas <?php echo $sc['icon']; ?> text-lg" style="color:<?php echo $sc['color']; ?>"></i>
                </div>
                <div class="status-count" style="color:<?php echo $sc['color']; ?>"><?php echo $sc['count']; ?></div>
                <div class="status-label"><?php echo $sc['label']; ?></div>
            </div>
            <?php endforeach; ?>
        </div>


    <!-- Bookings List (Facebook-style cards) -->
            <div class="space-y-4">

                <?php if (empty($bookings)): ?>
                    <div class="text-center py-16">
                        <div class="w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-6" style="background:#f4f7f2;">
                            <i class="fas fa-calendar-plus text-3xl" style="color:#b8c4a9;"></i>
                        </div>
                        <h3 class="text-xl font-bold mb-2" style="color:#1a472a;">No Bookings Yet</h3>
                        <p class="text-sm" style="color:#62744f;">Create an event to see your bookings here.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings as $bk):
                        $badge = getStatusBadgeStyle($bk['status']);
                        $banner = getStatusBanner($bk['status']);
                    ?>
                    <div class="booking-card" id="booking-<?php echo $bk['id']; ?>">
                        <!-- Header -->
                        <div class="booking-header">
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0" style="background:#f4f7f2;">
                                    <i class="fas fa-calendar-day text-lg" style="color:#1a472a;"></i>
                                </div>
                                <div class="min-w-0">
                                    <div>
                                        <h3 class="font-bold text-base truncate" style="color:#353f2d;"><?php echo htmlspecialchars($bk['event_title']); ?></h3>
                                        <p class="text-xs mt-0.5" style="color:#62744f;">Booked on <?php echo $bk['created_at']; ?></p>
                                    </div>

                                </div>
                            </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <span class="status-pill" style="background:<?php echo $badge['bg']; ?>; color:<?php echo $badge['color']; ?>; display:inline-flex; align-items:center; gap:8px; padding: 8px 14px; border-radius:9999px;">
                                        <?php if ($bk['status'] === 'pending'): ?>
                                            <i class="fas fa-clock" style="font-size:12px;" aria-hidden="true"></i>
                                        <?php endif; ?>
                                        <?php echo $badge['text']; ?>
                                    </span>
                                </div>

                        </div>

                        <!-- Status Banner -->
                        <div class="status-banner" style="background:<?php echo $banner['bg']; ?>; border-left-color:<?php echo $banner['border']; ?>; color:<?php echo $banner['color']; ?>; border-radius:9999px; padding: 0.75rem 1rem; margin: 0 1.5rem;">
                            <i class="fas <?php echo $banner['icon']; ?>" style="opacity:0.9;"></i>
                            <span class="text-sm font-medium"><?php echo $banner['text']; ?></span>
                        </div>


                        <!-- Body / Data Grid -->
                        <div class="booking-body">
                            <div class="info-grid">
                                <div class="info-col">
                                    <h4>Contact Information</h4>
                                    <div class="info-row">
                                        <span class="label">Name</span>
                                        <span class="value"><?php echo htmlspecialchars($bk['customer_name']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Email</span>
                                        <span class="value email-val" title="<?php echo htmlspecialchars($bk['email']); ?>"><?php echo htmlspecialchars($bk['email']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Phone</span>
                                        <span class="value"><?php echo htmlspecialchars($bk['phone']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Address</span>
                                        <span class="value"><?php echo htmlspecialchars($bk['address']); ?></span>
                                    </div>
                                </div>
                                <div class="info-col">
                                    <h4>Booking Details</h4>
                                    <div class="info-row">
                                        <span class="label"><strong>Number of Guests</strong></span>
                                        <span class="value"><?php echo (int)$bk['guest_count']; ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label"><strong>Name</strong></span>
                                        <span class="value"><?php echo htmlspecialchars('b' . $bk['id']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Package</span>
                                        <span class="value"><?php echo htmlspecialchars($bk['package']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Budget</span>
                                        <span class="value" style="color:#1a472a; font-weight:700;">₱<?php echo number_format($bk['budget']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-3xl bg-emerald-50 p-4">
                                    <p class="text-sm font-semibold text-emerald-900">Total Budget</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-900">₱<?php echo number_format($bk['budget']); ?></p>
                                </div>
                                <div class="rounded-3xl bg-sky-50 p-4">
                                    <p class="text-sm font-semibold text-slate-900">Total Spent</p>
                                    <p class="mt-3 text-2xl font-semibold text-slate-900">₱<?php echo number_format($bk['total_spent']); ?></p>
                                </div>
                                <div class="rounded-3xl bg-white p-4 border border-slate-200">
                                    <p class="text-sm font-semibold text-slate-900">Remaining</p>
                                    <p class="mt-3 text-2xl font-semibold text-emerald-700">₱<?php echo number_format($bk['remaining_budget']); ?></p>
                                </div>
                            </div>
                            <div class="mt-4 rounded-3xl bg-slate-100 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-medium text-slate-700">Budget Usage</span>
                                    <span class="text-sm font-semibold text-slate-900"><?php echo number_format($bk['budget_utilization'], 1); ?>%</span>
                                </div>
                                <div class="mt-3 h-3 rounded-full bg-slate-200 overflow-hidden">
                                    <div class="h-full rounded-full bg-emerald-600" style="width: <?php echo number_format($bk['budget_utilization'], 1); ?>%;"></div>
                                </div>
                            </div>
                            <div class="mt-4 flex flex-wrap gap-3">
                                <button type="button" class="btn-outline btn-sage open-add-expense" data-event-title="<?php echo htmlspecialchars($bk['event_title'], ENT_QUOTES); ?>" data-event-id="<?php echo (int)$bk['event_id']; ?>" data-booking-id="<?php echo htmlspecialchars($bk['id'], ENT_QUOTES); ?>" data-rem-budget="<?php echo htmlspecialchars($bk['remaining_budget'], ENT_QUOTES); ?>">Add Expense</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Cancel Booking Modal -->
    <div id="cancelModal" class="modal-overlay">
        <div class="modal-box">
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#fef2f2;">
                    <i class="fas fa-exclamation-triangle text-2xl" style="color:#dc2626;"></i>
                </div>
                <h3 class="text-xl font-bold" style="color:#1a472a;">Cancel Booking</h3>
                <p class="text-sm mt-2" style="color:#62744f;">Are you sure you want to cancel <strong id="cancelBookingTitle"></strong>? This action cannot be undone.</p>
            </div>
            <div class="flex gap-3">
                <button onclick="closeModal('cancelModal')" class="btn-outline flex-1">Keep Booking</button>
                <button onclick="confirmCancel()" class="btn-solid btn-sage flex-1" style="background:#dc2626;">Yes, Cancel</button>
            </div>
        </div>
    </div>





    <!-- Expenses viewing removed (Add-only flow) -->

    <div id="addExpenseModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 560px;">
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#eff6ff;">
                    <i class="fas fa-receipt text-2xl" style="color:#3b82f6;"></i>
                </div>
                <h3 class="text-xl font-bold" style="color:#1a472a;">Add Event Expense</h3>
                <p class="text-sm mt-2" style="color:#62744f;">Record a new expense for <strong id="addExpenseEventTitle"></strong></p>
            </div>
            <form id="addExpenseForm" class="space-y-4">
                <input type="hidden" id="addExpenseEventId" name="event_id" value="">
                <input type="hidden" id="addExpenseBookingId" name="booking_id" value="">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Expense Item</label>
                        <input id="addExpenseDescription" type="text" placeholder="e.g., DJ service" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Cost (₱)</label>
                        <input id="addExpenseAmount" type="number" min="0" step="0.01" value="0" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400">
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Category</label>
                        <select id="addExpenseCategory" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400">
                            <option value="Transportation">Transportation</option>
                            <option value="Venue">Venue</option>
                            <option value="Food">Food</option>
                            <option value="Decor">Decor</option>
                            <option value="Entertainment">Entertainment</option>
                            <option value="Miscellaneous">Miscellaneous</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Description (optional)</label>
                        <input id="addExpenseOptionalDesc" type="text" placeholder="Optional note" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400">
                    </div>
                </div>
                <div id="addExpenseError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 hidden"></div>
                <div class="mb-2 text-sm text-gray-600">Remaining budget: <strong id="addExpenseRemaining">₱0.00</strong></div>
                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('addExpenseModal')" class="btn-outline flex-1">Cancel</button>
                    <button type="button" onclick="submitAddExpense()" class="btn-solid btn-sage flex-1">Add Expense</button>
                </div>
            </form>
        </div>
    </div>

    <div id="expensesModal" class="modal-overlay">
        <div class="modal-box" style="max-width:560px;">
            <div class="text-center mb-4">
                <h3 class="text-xl font-bold" style="color:#1a472a;">Event Expenses</h3>
                <p class="text-sm mt-1 text-gray-600">Expenses for <strong id="expensesModalEventTitle"></strong></p>
            </div>
            <div class="p-4 bg-white rounded-xl border mb-4" style="border-color:#e8ebe3; max-height:400px; overflow:auto;">
                <div id="expensesModalList" class="space-y-3"></div>
            </div>
            <div class="flex gap-3">
                <button type="button" class="btn-outline flex-1" onclick="closeModal('expensesModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
        let currentBookingId = null;
        let currentMaxGuests = 200;

        // User menu toggle (same behavior as dashboard.php)
        (function () {
            const root = document.querySelector('#userMenu')?.parentElement;
            const menu = document.getElementById('userMenu');
            if (!root || !menu) return;

            // Toggle when clicking the avatar/email icon
            root.addEventListener('click', function (e) {
                // prevent immediate outside-click close
                e.stopPropagation();
                menu.classList.toggle('active');
            });

            // Toggle when pressing Enter/Space on the avatar
            const avatar = root.querySelector('[role="button"], [tabindex]');
            if (avatar) {
                avatar.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        menu.classList.toggle('active');
                    }
                });
            }

            // Close on outside click
            document.addEventListener('click', function () {
                menu.classList.remove('active');
            });
        })();


        // Ensure action-menu UI helpers exist (booking.php originally had them in another version)
        function toggleActionMenu(id) {
            const dd = document.getElementById('action-dropdown-' + id);
            if (!dd) return;
            const isActive = dd.classList.contains('active');
            document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('active'));
            if (!isActive) dd.classList.add('active');
        }

        function openGuestModal(id, currentGuests, maxGuests) {
            // Keep original modal flow if present in your page
            // If modal isn't used in the current UI, just ignore.
            if (!document.getElementById('guestModal')) return;
            currentBookingId = id;
            currentMaxGuests = maxGuests;
            const input = document.getElementById('guestInput');
            const maxDisp = document.getElementById('guestMaxDisplay');
            if (input) {
                input.value = currentGuests;
                input.max = maxGuests;
            }
            if (maxDisp) maxDisp.textContent = maxGuests;
            document.getElementById('guestModal').classList.add('active');
        }

        function confirmGuestUpdate() {
            if (!document.getElementById('guestModal')) return;
            closeModal('guestModal');
        }

        // Cancel Modal
        function openCancelModal(id, title) {
            currentBookingId = id;
            const el = document.getElementById('cancelBookingTitle');
            if (el) el.textContent = title;
            document.getElementById('cancelModal').classList.add('active');
        }


        function confirmCancel() {
            if (currentBookingId) {
                const card = document.getElementById('booking-' + currentBookingId);
                if (card) card.style.display = 'none';
            }
            closeModal('cancelModal');
        }



        // Expenses viewing removed; only add-expense flow remains for users.

        function openAddExpenseModal(title, eventId, bookingId) {
            document.getElementById('addExpenseEventTitle').textContent = title;
            document.getElementById('addExpenseEventId').value = eventId;
            document.getElementById('addExpenseBookingId').value = bookingId || '';
            document.getElementById('addExpenseDescription').value = '';
            document.getElementById('addExpenseOptionalDesc').value = '';
            document.getElementById('addExpenseAmount').value = '0.00';
            document.getElementById('addExpenseCategory').value = 'Transportation';
            document.getElementById('addExpenseError').classList.add('hidden');
            // show remaining budget if available via button data attribute
            const btn = document.querySelector('.open-add-expense[data-booking-id="' + (bookingId || '') + '"]');
            let rem = 0;
            if (btn && btn.dataset.remBudget) rem = Number(btn.dataset.remBudget) || 0;
            document.getElementById('addExpenseRemaining').textContent = '₱' + rem.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
            document.getElementById('addExpenseModal').classList.add('active');
        }

        async function submitAddExpense() {
            const addBtn = document.querySelector('#addExpenseModal button[onclick="submitAddExpense()"]');
            if (addBtn) addBtn.disabled = true;

            const eventId = document.getElementById('addExpenseEventId').value;
            const bookingId = document.getElementById('addExpenseBookingId').value;
            const description = document.getElementById('addExpenseDescription').value.trim() || document.getElementById('addExpenseOptionalDesc').value.trim();
            const amount = parseFloat(document.getElementById('addExpenseAmount').value);
            const category = document.getElementById('addExpenseCategory').value;
            const expenseDate = new Date().toISOString().slice(0,10);
            const errorBox = document.getElementById('addExpenseError');

            if ((!eventId && !bookingId) || !category || !expenseDate) {
                errorBox.textContent = 'Please fill required expense fields (event or booking and category required).';
                errorBox.classList.remove('hidden');
                if (addBtn) addBtn.disabled = false;
                return;
            }
            // check remaining budget (if button had remBudget)
            const bookingRem = Number(document.getElementById('addExpenseRemaining').textContent.replace(/[^0-9.-]+/g,'')) || null;
            if (bookingRem !== null && amount > bookingRem) {
                errorBox.textContent = 'Insufficient remaining budget: ₱' + bookingRem.toFixed(2);
                errorBox.classList.remove('hidden');
                if (addBtn) addBtn.disabled = false;
                return;
            }
            if (Number.isNaN(amount) || amount <= 0) {
                errorBox.textContent = 'Expense amount must be greater than zero.';
                errorBox.classList.remove('hidden');
                if (addBtn) addBtn.disabled = false;
                return;
            }

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                            action: 'add_expense',
                            event_id: eventId || null,
                            booking_id: bookingId || null,
                            description,
                            category,
                            amount,
                            expense_date: expenseDate
                        })
                });
                    let data;
                    try {
                        data = await response.json();
                    } catch (e) {
                        const text = await response.text();
                        throw new Error('Invalid response: ' + text);
                    }
                    if (!data.success) {
                        throw new Error(data.error || JSON.stringify(data) || 'Unable to save expense');
                    }
                    // show expenses modal for this event
                    const evtTitle = document.getElementById('addExpenseEventTitle').textContent;
                    const evtId = eventId || null;
                    closeModal('addExpenseModal');
                    fetchAndShowExpenses(evtId, bookingId, evtTitle);
            } catch (err) {
                errorBox.textContent = err.message;
                errorBox.classList.remove('hidden');
                if (addBtn) addBtn.disabled = false;
            }
        }

            async function fetchAndShowExpenses(eventId, bookingId, title) {
                const modal = document.getElementById('expensesModal');
                const list = document.getElementById('expensesModalList');
                document.getElementById('expensesModalEventTitle').textContent = title || '';
                list.innerHTML = '<p class="text-sm" style="color:#62744f;">Loading...</p>';
                modal.classList.add('active');
                try {
                    const url = new URL('api.php', window.location.href);
                    url.searchParams.set('type', 'expenses');
                    url.searchParams.set('event_id', eventId);
                    const res = await fetch(url.toString());
                    const data = await res.json();
                    if (!data.success) throw new Error(data.error || 'Unable to load expenses');
                    const expenses = data.expenses || [];
                    if (!expenses.length) {
                        list.innerHTML = '<div class="empty-title">No expenses yet</div>';
                        return;
                    }
                    list.innerHTML = expenses.map(exp => {
                        const canDelete = exp.created_by && (exp.created_by == <?php echo intval($userId); ?>);
                        return `<div class="rounded-2xl p-3" style="background:#f4f7f2; border:1px solid #e8ebe3; display:flex; justify-content:space-between; align-items:center; gap:12px;">
                            <div>
                                <div style="font-weight:700;color:#1a472a;">${escapeHtml(exp.category || 'Expense')}</div>
                                <div class="text-sm" style="color:#62744f;">${escapeHtml(exp.description || '')}</div>
                                <div class="text-xs" style="color:#8e9b8c;">${exp.expense_date || ''}</div>
                            </div>
                            <div style="text-align:right">
                                <div style="font-weight:800;color:#1a472a;">₱${Number(exp.amount).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})}</div>
                                ${canDelete ? `<button class="btn-outline" onclick="deleteExpense('${exp.id}','${eventId}')">Delete</button>` : ''}
                            </div>
                        </div>`;
                    }).join('');
                } catch (err) {
                    list.innerHTML = '<div class="empty-title" style="color:#b91c1c;">' + escapeHtml(err.message || 'Unable to load expenses') + '</div>';
                }
            }

        // Modal utils
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) overlay.classList.remove('active');
            });
        });

        document.querySelectorAll('.open-add-expense').forEach(button => {
            button.addEventListener('click', function () {
                openAddExpenseModal(this.dataset.eventTitle, this.dataset.eventId, this.dataset.bookingId);
            });
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });
    </script>
    <script>
        async function deleteExpense(expenseId, eventId) {
            if (!expenseId) return;
            if (!confirm('Delete this expense?')) return;
            try {
                const res = await fetch('api.php', {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify({action:'delete_expense', id: expenseId})
                });
                const data = await res.json();
                if (!data.success) throw new Error(data.error || 'Unable to delete expense');
                // refresh expenses list
                fetchAndShowExpenses(eventId, null, document.getElementById('expensesModalEventTitle').textContent);
            } catch (err) {
                alert(err.message || 'Unable to delete expense');
            }
        }
    </script>
<?php require_once 'chat_widget_snippet.php'; ?>
</body>
</html>
