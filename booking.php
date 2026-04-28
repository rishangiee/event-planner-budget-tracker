<?php
require_once 'config/config.php';
requireUser();

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userEmail = $_SESSION['user_email'] ?? 'user@cavendia.com';

// Fetch current user's real profile data first
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$currentUser = $stmt->fetch();

// Use real DB values for contact info
$userPhone = $currentUser['contact_number'] ?? '';
$userAddress = $currentUser['address'] ?? 'N/A';
$userFullName = trim(($currentUser['firstname'] ?? '') . ' ' . ($currentUser['lastname'] ?? '')) ?: $userName;

// Fetch real events from DB
$stmt = $pdo->prepare("SELECT * FROM events WHERE user_id = ? ORDER BY date ASC");
$stmt->execute([$userId]);
$dbEvents = $stmt->fetchAll();

// Map event statuses to booking statuses
function mapEventStatus($eventStatus) {
    return match($eventStatus) {
        'planned' => 'pending',
        'ongoing' => 'approved',
        'completed' => 'approved',
        'cancelled' => 'rejected',
        default => 'pending',
    };
}

$bookings = [];
if (!empty($dbEvents)) {
    foreach ($dbEvents as $evt) {
        $bookings[] = [
            'id' => $evt['id'],
            'event_title' => $evt['title'],
            'event_date' => $evt['date'],
            'status' => mapEventStatus($evt['status'] ?? 'planned'),
            'guest_count' => (int)($evt['attendees'] ?? 0),
            'max_guests' => (int)($evt['max_attendees'] ?? 200),
            'customer_name' => ($evt['customer_name'] ?? '') ?: $userFullName,
            'email' => $userEmail,
            'phone' => ($evt['customer_contact'] ?? '') ?: $userPhone,
            'address' => ($evt['customer_address'] ?? '') ?: $userAddress,
            'description' => $evt['description'] ?: 'No description provided',
            'package' => ucfirst($evt['status'] ?? 'Standard') . ' Package',
            'budget' => (float)($evt['budget'] ?? 0),
            'created_at' => $evt['created_at'] ? date('Y-m-d', strtotime($evt['created_at'])) : date('Y-m-d'),
        ];
    }
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
        'approved' => ['bg' => '#f0fdf4', 'border' => '#bbf7d0', 'color' => '#166534', 'icon' => 'fa-check-circle', 'text' => 'Your booking has been approved! You can now view your invoice and manage your event details.'],
        'rejected' => ['bg' => '#fef2f2', 'border' => '#fecaca', 'color' => '#991b1b', 'icon' => 'fa-times-circle', 'text' => 'We regret to inform you that this booking has been declined. Please contact support for more information.'],
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
<body class="min-h-screen flex flex-col">

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
                    <a href="event.php" class="sidebar-item">
                        <i class="fas fa-calendar-check"></i><span>Event</span>
                    </a>
                    <a href="calendar.php" class="sidebar-item">
                        <i class="fas fa-calendar-days"></i><span>Calendar</span>
                    </a>
                    <a href="booking.php" class="sidebar-item active">
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

            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold" style="color:#1a472a;">My Bookings</h2>
                        <p class="text-sm mt-1" style="color:#62744f;">Manage and track your event bookings</p>
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

            <!-- Bookings List -->
            <div class="space-y-6">
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
                                    <h3 class="font-bold text-base truncate" style="color:#353f2d;"><?php echo htmlspecialchars($bk['event_title']); ?></h3>
                                    <p class="text-xs mt-0.5" style="color:#62744f;">Booked on <?php echo $bk['created_at']; ?> &bull; Event: <?php echo $bk['event_date']; ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="status-pill" style="background:<?php echo $badge['bg']; ?>; color:<?php echo $badge['color']; ?>">
                                    <?php echo $badge['text']; ?>
                                </span>
                                <div class="action-menu">
                                    <button class="action-btn" onclick="toggleActionMenu('<?php echo $bk['id']; ?>')">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div id="action-dropdown-<?php echo $bk['id']; ?>" class="action-dropdown">
                                        <button class="action-item danger" onclick="openCancelModal('<?php echo $bk['id']; ?>', '<?php echo htmlspecialchars($bk['event_title'], ENT_QUOTES); ?>')">
                                            <i class="fas fa-ban"></i> Cancel Booking
                                        </button>
                                        <button class="action-item" onclick="openGuestModal('<?php echo $bk['id']; ?>', <?php echo $bk['guest_count']; ?>, <?php echo $bk['max_guests']; ?>)">
                                            <i class="fas fa-users"></i> Update Guest Count
                                        </button>
                                        <button class="action-item <?php echo $bk['status'] !== 'approved' ? 'disabled' : ''; ?>" <?php echo $bk['status'] !== 'approved' ? 'disabled' : ''; ?> onclick="<?php echo $bk['status'] === 'approved' ? 'viewInvoice(\''.$bk['id'].'\')' : ''; ?>">
                                            <i class="fas fa-file-invoice"></i> View Invoice
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status Banner -->
                        <div class="status-banner" style="background:<?php echo $banner['bg']; ?>; border-left-color:<?php echo $banner['border']; ?>; color:<?php echo $banner['color']; ?>;">
                            <i class="fas <?php echo $banner['icon']; ?>"></i>
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
                                        <span class="label">Description</span>
                                        <span class="value"><?php echo htmlspecialchars($bk['description']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Package</span>
                                        <span class="value"><?php echo htmlspecialchars($bk['package']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Guests</span>
                                        <span class="value"><?php echo $bk['guest_count']; ?> / <?php echo $bk['max_guests']; ?> max</span>
                                    </div>
                                    <div class="info-row">
                                        <span class="label">Budget</span>
                                        <span class="value" style="color:#1a472a; font-weight:700;">₱<?php echo number_format($bk['budget']); ?></span>
                                    </div>
                                </div>
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

    <!-- Update Guest Count Modal -->
    <div id="guestModal" class="modal-overlay">
        <div class="modal-box">
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#f0fdf4;">
                    <i class="fas fa-users text-2xl" style="color:#16a34a;"></i>
                </div>
                <h3 class="text-xl font-bold" style="color:#1a472a;">Update Guest Count</h3>
                <p class="text-sm mt-2" style="color:#62744f;">Enter the new number of guests for this booking.</p>
            </div>
            <div class="mb-6">
                <input type="number" id="guestInput" class="guest-input" min="1" max="1000">
                <p class="text-xs text-center mt-2" style="color:#62744f;">Max allowed: <span id="guestMaxDisplay"></span> guests</p>
            </div>
            <div class="flex gap-3">
                <button onclick="closeModal('guestModal')" class="btn-outline flex-1">Cancel</button>
                <button onclick="confirmGuestUpdate()" class="btn-solid btn-sage flex-1">Update</button>
            </div>
        </div>
    </div>

    <!-- Invoice Modal -->
    <div id="invoiceModal" class="modal-overlay">
        <div class="modal-box" style="max-width: 480px;">
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#eff6ff;">
                    <i class="fas fa-file-invoice text-2xl" style="color:#3b82f6;"></i>
                </div>
                <h3 class="text-xl font-bold" style="color:#1a472a;">Invoice</h3>
                <p class="text-sm mt-2" style="color:#62744f;">Booking Invoice #<span id="invoiceId"></span></p>
            </div>
            <div class="bg-white rounded-xl p-4 mb-6 border" style="border-color:#e8ebe3;">
                <div class="flex justify-between mb-2">
                    <span class="text-sm" style="color:#62744f;">Event</span>
                    <span class="text-sm font-semibold" style="color:#353f2d;" id="invoiceEvent"></span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="text-sm" style="color:#62744f;">Date</span>
                    <span class="text-sm font-semibold" style="color:#353f2d;" id="invoiceDate"></span>
                </div>
                <div class="flex justify-between mb-2">
                    <span class="text-sm" style="color:#62744f;">Guests</span>
                    <span class="text-sm font-semibold" style="color:#353f2d;" id="invoiceGuests"></span>
                </div>
                <div class="divider my-3"></div>
                <div class="flex justify-between">
                    <span class="text-base font-bold" style="color:#1a472a;">Total</span>
                    <span class="text-base font-bold" style="color:#1a472a;" id="invoiceTotal"></span>
                </div>
            </div>
            <div class="flex gap-3">
                <button onclick="closeModal('invoiceModal')" class="btn-outline flex-1">Close</button>
                <button class="btn-solid btn-sage flex-1"><i class="fas fa-download mr-2"></i>Download</button>
            </div>
        </div>
    </div>

    <script>
        let currentBookingId = null;
        let currentMaxGuests = 200;

        // Action menu toggle
        function toggleActionMenu(id) {
            const dd = document.getElementById('action-dropdown-' + id);
            const isActive = dd.classList.contains('active');
            document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('active'));
            if (!isActive) dd.classList.add('active');
        }

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.action-menu')) {
                document.querySelectorAll('.action-dropdown').forEach(d => d.classList.remove('active'));
            }
        });

        // Cancel Modal
        function openCancelModal(id, title) {
            currentBookingId = id;
            document.getElementById('cancelBookingTitle').textContent = title;
            document.getElementById('cancelModal').classList.add('active');
        }

        function confirmCancel() {
            if (currentBookingId) {
                const card = document.getElementById('booking-' + currentBookingId);
                if (card) card.style.display = 'none';
            }
            closeModal('cancelModal');
        }

        // Guest Modal
        function openGuestModal(id, currentGuests, maxGuests) {
            currentBookingId = id;
            currentMaxGuests = maxGuests;
            document.getElementById('guestInput').value = currentGuests;
            document.getElementById('guestInput').max = maxGuests;
            document.getElementById('guestMaxDisplay').textContent = maxGuests;
            document.getElementById('guestModal').classList.add('active');
        }

        function confirmGuestUpdate() {
            const val = parseInt(document.getElementById('guestInput').value);
            if (val < 1 || val > currentMaxGuests) {
                alert('Please enter a value between 1 and ' + currentMaxGuests);
                return;
            }
            closeModal('guestModal');
        }

        // Invoice Modal
        function viewInvoice(id) {
            const bk = <?php echo json_encode($bookings); ?>.find(b => b.id == id);
            if (bk) {
                document.getElementById('invoiceId').textContent = bk.id;
                document.getElementById('invoiceEvent').textContent = bk.event_title;
                document.getElementById('invoiceDate').textContent = bk.event_date;
                document.getElementById('invoiceGuests').textContent = bk.guest_count;
                document.getElementById('invoiceTotal').textContent = '₱' + bk.budget.toLocaleString();
                document.getElementById('invoiceModal').classList.add('active');
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
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });
    </script>
</body>
</html>