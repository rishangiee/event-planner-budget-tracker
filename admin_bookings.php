<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/auth.php';

// Build API endpoint URL relative to THIS admin page (prevents localhost path issues)
$apiUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/api.php';

guardAdmin();

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$eventFilter = $_GET['event'] ?? '';

$where = 'WHERE 1=1';
$params = [];
if ($search) {
    $where .= ' AND (b.id LIKE ? OR u.name LIKE ? OR e.title LIKE ?)';
    $params = ["%$search%", "%$search%", "%$search%"];
}
if ($statusFilter) {
    $where .= ' AND b.status = ?';
    $params[] = $statusFilter;
}
if ($eventFilter) {
    $where .= ' AND b.event_id = ?';
    $params[] = $eventFilter;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    LEFT JOIN events e ON b.event_id = e.id $where");
$totalStmt->execute($params);
$totalBookings = $totalStmt->fetchColumn();

$limitInt = (int)$perPage;
$offsetInt = (int)$offset;
$stmt = $pdo->prepare("SELECT b.*, u.name as user_name, u.email, e.title as event_title, e.date as event_date, e.budget AS event_budget, b.budget AS booking_budget, COALESCE((SELECT SUM(amount) FROM expenses WHERE event_id = e.id), 0) AS event_spent 
    FROM bookings b 
    LEFT JOIN users u ON b.user_id = u.id 
    LEFT JOIN events e ON b.event_id = e.id 
    $where ORDER BY b.booking_date DESC LIMIT $limitInt OFFSET $offsetInt");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Stats
$stats = [
    'pending' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn(),
    'confirmed' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'")->fetchColumn(),
    'cancelled' => $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn(),
    'total_revenue' => $pdo->query("SELECT SUM(guest_count * 500) FROM bookings WHERE status != 'cancelled'")->fetchColumn() ?? 0
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings Management - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body style="font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f6f7f4 0%, #e8ebe3 100%);">
    
    <!-- All sidebar and header styles remain the same -->
    <style>
        :root{
            --sage:#A3B18A;
            --sage-dark:#8A9A6D;
            --cream:#F1F2EE;
            --forest:#1B4332;
            --white:#FFFFFF;
            --text-muted:#6B7C6D;
            --border:#D8DDD3;
            --shadow: 0 8px 32px rgba(27,67,50,0.12);
            --shadow-hover: 0 20px 40px rgba(27,67,50,0.2);
        }

        *{box-sizing:border-box;margin:0;padding:0;}
        body{background: linear-gradient(135deg, #f6f7f4 0%, #e8ebe3 100%);}

        /* Sidebar */
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

        /* Mobile Toggle */
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
            display: none;
        }

        .mobile-toggle:hover {
            background: var(--forest);
            transform: scale(1.05);
        }

        /* Overlay */
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

        /* Main content */
        .main-content {
            margin-left: 280px;
            transition: margin-left 0.3s ease;
            padding: 6rem 2rem 2rem;
            min-height: 100vh;
        }

        @media (max-width: 768px) {
            .mobile-toggle { display: block; }
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; }
        }
    </style>

    <button class="mobile-toggle" id="mobileToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="overlay" id="overlay"></div>

    <?php $userName = $_SESSION['user_name'] ?? 'Admin'; ?>

    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-title">
                <div class="sidebar-icon"><i class="fas fa-crown"></i></div>
                <h2>Admin Portal</h2>
            </div>
            <p class="sidebar-subtitle">Welcome, <?= htmlspecialchars($userName) ?></p>
        </div>
        <nav class="sidebar-nav">
            <a href="admin_dashboard.php" class="sidebar-item">
                <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
            </a>
            <a href="admin_event.php" class="sidebar-item">
                <i class="fas fa-calendar-alt"></i><span>Manage Events</span>
            </a>
            <a href="admin_users.php" class="sidebar-item">
                <i class="fas fa-users"></i><span>Manage Users</span>
            </a>
<a href="admin_bookings.php" class="sidebar-item active">
                <i class="fas fa-clipboard-list"></i><span>Bookings</span>
            </a>
            <a href="chat.php" class="sidebar-item">
                <i class="fas fa-comments"></i><span>Messages</span>
            </a>
            <a href="admin_logout.php" class="sidebar-item">
                <i class="fas fa-sign-out-alt"></i><span>Logout</span>
            </a>
        </nav>
    </aside>

    <main class="main-content" id="mainContent">
        <div class="page-container">

            <header class="mb-8">
                <h1 class="page-title">Bookings Management</h1>
            </header>

            <!-- Stats + Search aligned to the table left edge -->
            <section class="top-controls">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-left">
                            <div class="stat-label">Total Bookings</div>
                            <div class="stat-number stat-total"><?= (int)$totalBookings ?></div>
                        </div>
                        <div class="stat-icon stat-icon-total">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-left">
                            <div class="stat-label">Confirmed</div>
                            <div class="stat-number stat-confirmed"><?= (int)$stats['confirmed'] ?></div>
                        </div>
                        <div class="stat-icon stat-icon-confirmed">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-left">
                            <div class="stat-label">Pending</div>
                            <div class="stat-number stat-pending"><?= (int)$stats['pending'] ?></div>
                        </div>
                        <div class="stat-icon stat-icon-pending">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-left">
                            <div class="stat-label">Rejected</div>
                            <div class="stat-number stat-rejected"><?= (int)$stats['cancelled'] ?></div>
                        </div>
                        <div class="stat-icon stat-icon-rejected">
                            <i class="fas fa-times-circle"></i>
                        </div>
                    </div>
                </div>

                <!-- Search bar (modern, minimal) -->
                <div class="search-row">
                    <div class="search-pill">
                        <i class="fas fa-search search-icon"></i>
                        <input id="bookingSearch" value="<?= htmlspecialchars($search) ?>" placeholder="Search bookings..." />
                    </div>

                    <select id="statusFilter" class="mini-select" aria-label="Filter by status">
                        <option value="">All Status</option>
                        <option value="pending" <?= $statusFilter=='pending'?'selected':'' ?>>Pending</option>
                        <option value="confirmed" <?= $statusFilter=='confirmed'?'selected':'' ?>>Confirmed</option>
                        <option value="cancelled" <?= $statusFilter=='cancelled'?'selected':'' ?>>Cancelled</option>
                    </select>

                    <button type="button" onclick="applyBookingFilters()" class="btn-primary">Search</button>
                </div>
            </section>

            <!-- Bookings Table -->
            <div class="table-shell">
                <div class="table-header">
                    <h3 class="table-title">Bookings</h3>
                    <span class="table-count"><?= number_format($totalBookings) ?></span>
                </div>

                <div class="table-wrap">
                    <?php if (empty($bookings)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox" aria-hidden="true"></i>
                            <div class="empty-title">No bookings found</div>
                        </div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>USER</th>
                                    <th>EVENT</th>
                                    <th>BOOKED ON</th>
                                    <th>STATUS</th>
                                    <th>BUDGET</th>
                                    <th>ACTIONS</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <div class="cell-user">
                                            <div class="cell-user-email"><?= htmlspecialchars($booking['email'] ?? '') ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cell-event"> <?= htmlspecialchars($booking['event_title'] ?? '') ?> </div>
                                        <div class="cell-sub"><?= date('M j, Y', strtotime($booking['event_date'])) ?></div>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($booking['booking_date'])) ?></td>
                                    <td>
                                        <?php
                                            $badgeMap = [
                                                'confirmed' => ['cls' => 'badge-success', 'label' => 'Confirmed', 'icon' => 'fa-check-circle'],
                                                'pending' => ['cls' => 'badge-pending', 'label' => 'Pending', 'icon' => 'fa-clock'],
                                                'cancelled' => ['cls' => 'badge-cancel', 'label' => 'Cancelled', 'icon' => 'fa-times-circle'],
                                            ];
                                            $b = $badgeMap[$booking['status']] ?? ['cls'=>'badge-cancel','label'=>ucfirst($booking['status']),'icon'=>'fa-circle'];
                                        ?>
                                        <span class="status-badge <?= $b['cls'] ?>">
                                            <i class="fas <?= $b['icon'] ?>"></i>
                                            <?= $b['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="budget-cell">
                                            <?php
                                                $budgetValue = $booking['booking_budget'] ?: $booking['event_budget'];
                                                $budgetSpent = $booking['event_spent'];
                                                $remainingBudget = max(0, $budgetValue - $budgetSpent);
                                            ?>
                                            <div class="cell-event-budget">₱<?= number_format($budgetValue, 2) ?></div>
                                            <div class="cell-event-sub">Spent: ₱<?= number_format($budgetSpent, 2) ?></div>
                                            <div class="cell-event-sub">Rem: ₱<?= number_format($remainingBudget, 2) ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="action-confirm" type="button" onclick="updateStatus('<?= $booking['id'] ?>', 'confirmed')">
                                                <i class="fas fa-check"></i> Confirm
                                            </button>
                                            <button class="action-cancel" type="button" onclick="updateStatus('<?= $booking['id'] ?>', 'cancelled')">
                                                <i class="fas fa-times"></i> Cancel
                                            </button>
                                            <button class="action-add" type="button" onclick="openExpenseModal('<?= htmlspecialchars(addslashes($booking['event_title'])) ?>', '<?= htmlspecialchars($booking['event_id']) ?>')">
                                                <i class="fas fa-receipt"></i> Add Expense
                                            </button>
                                        </div>

                                        <div class="expenses-block" id="expensesBlock_<?= (int)$booking['event_id'] ?>">
                                            <div class="expenses-toolbar">
                                                <button class="btn-outline btn-small" type="button" onclick="toggleExpenses('<?= (int)$booking['event_id'] ?>')">
                                                    <i class="fas fa-list"></i> <span id="expensesToggleText_<?= (int)$booking['event_id'] ?>">View expenses</span>
                                                </button>
                                            </div>
                                            <div class="expenses-list" id="expensesList_<?= (int)$booking['event_id'] ?>" style="display:none;"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Pagination stub -->
                <div class="pagination" aria-hidden="true"></div>
            </div>
        </div>
    </main>

    <!-- All table and component styles remain the same -->
    <style>
        /* Matcha (sage/cream) minimal UI */
        .page-container{
            max-width: 1250px;
            margin: 0;
        }

        .page-title{
            font-size: 42px;
            font-weight: 900;
            letter-spacing: -0.02em;
            color: var(--forest);
        }

        .top-controls{
            display: flex;
            flex-direction: column;
            gap: 18px;
            margin-bottom: 18px;
        }

        .stats-grid{
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
        }

        @media (max-width: 1024px){
            .stats-grid{grid-template-columns: repeat(2, minmax(0, 1fr));}
        }

        @media (max-width: 640px){
            .stats-grid{grid-template-columns: 1fr;}
        }

        .stat-card{
            background: rgba(255,255,255,0.9);
            border: 1px solid rgba(216,221,211,0.9);
            border-radius: 18px;
            box-shadow: var(--shadow);
            padding: 18px 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .stat-card:hover{transform: translateY(-3px); box-shadow: var(--shadow-hover);}        

        .stat-label{
            font-size: 14px;
            font-weight: 800;
            color: var(--sage-dark);
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .stat-number{
            font-size: 34px;
            font-weight: 1000;
            color: var(--forest);
            margin-top: 6px;
        }

        .stat-icon{
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display:flex;
            align-items:center;
            justify-content:center;
            background: rgba(163,177,138,0.18);
            color: var(--sage-dark);
        }

        .stat-total{color: #1B4332;}
        .stat-icon-total{color: #1B4332;}

        .stat-confirmed{color: #0f766e;}
        .stat-icon-confirmed{color: #0f766e;}

        .stat-pending{color: #d97706;}
        .stat-icon-pending{color: #d97706;}

        .stat-rejected{color: #dc2626;}
        .stat-icon-rejected{color: #dc2626;}

        .search-row{
            display:flex;
            align-items:center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-pill{
            display:flex;
            align-items:center;
            gap: 12px;
            flex: 1;
            min-width: 280px;
            background: rgba(255,255,255,0.95);
            border: 1px solid rgba(216,221,211,0.95);
            border-radius: 999px;
            padding: 12px 16px;
            box-shadow: var(--shadow);
        }

        .search-pill input{
            border:none;
            outline:none;
            width: 100%;
            font-size: 15px;
            font-weight: 700;
            background: transparent;
        }

        .search-icon{color: var(--sage-dark);}

        .mini-select{
            background: rgba(255,255,255,0.95);
            border: 1px solid rgba(216,221,211,0.95);
            border-radius: 999px;
            padding: 12px 16px;
            font-weight: 800;
            color: var(--forest);
            box-shadow: var(--shadow);
        }

        .btn-primary{
            background: linear-gradient(135deg, var(--sage), var(--sage-dark));
            color: var(--white);
            border:none;
            border-radius: 999px;
            padding: 12px 18px;
            font-weight: 900;
            box-shadow: 0 8px 25px rgba(163,177,138,0.25);
            cursor:pointer;
            transition: transform .2s ease, box-shadow .2s ease;
        }
        .btn-primary:hover{transform: translateY(-2px); box-shadow: 0 12px 35px rgba(163,177,138,0.35);}        

        .table-shell{
            background: var(--cream);
            border-radius: 18px;
            padding: 16px;
        }

        .table-header{
            display:flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 10px 16px;
        }

        .table-title{
            font-size: 18px;
            font-weight: 1000;
            color: var(--forest);
        }
        .table-count{
            font-size: 13px;
            font-weight: 900;
            color: var(--sage-dark);
            border:1px solid rgba(216,221,211,0.9);
            background: rgba(255,255,255,0.85);
            padding: 6px 10px;
            border-radius: 999px;
        }

        .table-wrap{
            background: rgba(255,255,255,0.92);
            border: 1px solid rgba(216,221,211,0.9);
            border-radius: 16px;
            overflow:hidden;
            box-shadow: var(--shadow);
        }

        table{width:100%; border-collapse: collapse;}
        thead th{
            background: rgba(163,177,138,0.15);
            color: var(--sage-dark);
            font-weight: 1000;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 14px 14px;
            text-align:left;
            border-bottom: 1px solid rgba(216,221,211,0.9);
        }

        tbody td{
            padding: 14px 14px;
            border-bottom: 1px solid rgba(216,221,211,0.7);
            color: var(--forest);
            font-weight: 700;
            vertical-align: middle;
            font-size: 14px;
        }
        tbody tr:hover td{background: rgba(241,242,238,0.75);}        

        .actions{
            display:flex;
            gap: 8px;
            flex-wrap: nowrap;
            align-items: center;
            justify-content: flex-start;
            white-space: nowrap;
        }

        .expenses-block{margin-top:10px;}
        .expenses-toolbar{display:flex; justify-content:flex-start; margin-bottom:8px;}
        .btn-small{padding:8px 12px; font-size:13px;}
        .expenses-list{
            border:1px solid rgba(216,221,211,0.9);
            background: rgba(241,242,238,0.5);
            border-radius: 14px;
            padding: 10px;
        }
        .expense-row{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:10px;
            padding: 8px 0;
            border-bottom: 1px dashed rgba(216,221,211,0.8);
        }
        .expense-row:last-child{border-bottom:none;}
        .expense-meta{display:flex; flex-direction:column; gap:2px;}
        .expense-desc{font-weight:900; color: var(--forest);}
        .expense-sub{font-size:12px; color:#6b7c6d; font-weight:700;}
        .expense-amount{font-weight:1000; color:#1f4e2e; white-space:nowrap;}
        .action-delete{
            border:none;
            border-radius: 12px;
            padding: 7px 10px;
            font-weight: 1000;
            cursor:pointer;
            transition: transform .15s ease, filter .15s ease;
            background: #b91c1c;
            color: var(--white);
        }
        .action-delete:hover{transform: translateY(-1px); filter: brightness(1.05);}
        .action-confirm{
            border:none;
            border-radius: 12px;
            padding: 8px 12px;
            font-weight: 1000;
            cursor:pointer;
            transition: transform .15s ease, filter .15s ease;
            background: #0f766e;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .action-confirm:hover{transform: translateY(-1px); filter: brightness(1.1);}
        
        .action-cancel{
            border:none;
            border-radius: 12px;
            padding: 8px 12px;
            font-weight: 1000;
            cursor:pointer;
            transition: transform .15s ease, filter .15s ease;
            background: #dc2626;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .action-cancel:hover{transform: translateY(-1px); filter: brightness(1.1);}
        .action-add{
            border:none;
            border-radius: 12px;
            padding: 8px 12px;
            font-weight: 1000;
            cursor:pointer;
            transition: transform .15s ease, filter .15s ease;
            background: #16a34a;
            color: var(--white);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .action-add:hover{transform: translateY(-1px); filter: brightness(1.05);}

        .btn-outline {
            border: 1px solid rgba(148, 163, 184, 0.4);
            background: #ffffff;
            color: var(--forest);
            transition: background .2s ease, transform .2s ease;
        }
        .btn-outline:hover {
            background: #f5f7f3;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(27, 67, 50, 0.26);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: #ffffff;
            border-radius: 1.75rem;
            box-shadow: 0 30px 80px rgba(27, 67, 50, 0.15);
            padding: 2rem;
            width: 100%;
            max-width: 560px;
        }

        .budget-cell{
            display:flex;
            flex-direction:column;
            gap:4px;
            color: var(--forest);
        }
        .cell-event-budget{
            font-weight: 900;
            color: #1f4e2e;
        }
        .cell-event-sub{
            font-size: 12px;
            color: #6b7c6d;
        }

        .status-badge{
            display:inline-flex;
            align-items:center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            font-weight: 1000;
            border: 1px solid rgba(216,221,211,0.9);
            background: rgba(255,255,255,0.9);
        }
        .badge-success{color: #0f766e;}
        .badge-pending{color: #d97706;}
        .badge-cancel{color: #b91c1c;}

        .empty-state{
            padding: 48px 16px;
            display:flex;
            flex-direction: column;
            align-items:center;
            justify-content:center;
            gap: 10px;
            color: var(--text-muted);
        }
        .empty-title{
            font-weight: 1000;
            color: var(--forest);
            font-size: 18px;
        }

        /* Mobile table: wrap cells */
        @media (max-width: 720px){
            thead{display:none;}
            table{display:block;}
            tbody tr{display:block; border-bottom:1px solid rgba(216,221,211,0.7);} 
            tbody td{display:block; width:100%; padding: 10px 14px;}
        }
    </style>

    <div id="expenseModal" class="modal-overlay">
        <div class="modal-box" style="max-width:520px; width:100%;">
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background:#dcfce7;">
                    <i class="fas fa-receipt text-2xl" style="color:#15803d;"></i>
                </div>
                <h3 class="text-xl font-bold" style="color:#1f4336;">Add Event Expense</h3>
                <p class="text-sm mt-2" style="color:#516957;">Record a new expense for <strong id="expenseEventTitle"></strong></p>
            </div>
            <form id="expenseForm" class="space-y-4">
                <input type="hidden" id="expenseEventId" name="event_id" value="">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Expense Item</label>
                        <input id="expenseDescription" type="text" placeholder="e.g., DJ service" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Cost (₱)</label>
                        <input id="expenseAmount" type="number" min="0" step="0.01" value="0" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400">
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Category</label>
                        <select id="expenseCategory" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400">
                            <option value="Transportation">Transportation</option>
                            <option value="Venue">Venue</option>
                            <option value="Food">Food</option>
                            <option value="Decor">Decor</option>
                            <option value="Entertainment">Entertainment</option>
                            <option value="Miscellaneous">Miscellaneous</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Description</label>
                        <input id="expenseDescription" type="text" placeholder="e.g., DJ service" class="w-full rounded-2xl border border-slate-300 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400">
                    </div>
                </div>
                <div id="expenseError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 hidden"></div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeExpenseModal()" class="btn-outline flex-1">Cancel</button>
                    <button type="button" onclick="submitExpense()" class="btn-primary flex-1">Add Expense</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filter application
        function applyBookingFilters() {
            const params = new URLSearchParams(window.location.search);
            params.set('search', document.getElementById('bookingSearch').value);
            params.set('status', document.getElementById('statusFilter').value);

            // eventFilter is not present on this page (remove it to avoid polluting query)
            params.delete('event');

            window.location.search = params.toString();
        }

        // Direct status update (no confirmation modal)
        function updateStatus(bookingId, status) {
            fetch('<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'update_booking_status', id: bookingId, status})
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.error || 'Update failed');
                }
            }).catch(error => {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            });
        }

        function openExpenseModal(title, eventId) {
            document.getElementById('expenseEventTitle').textContent = title;
            document.getElementById('expenseEventId').value = eventId;
            document.getElementById('expenseDescription').value = '';
            document.getElementById('expenseAmount').value = '0.00';
            document.getElementById('expenseCategory').value = 'Transportation';
            document.getElementById('expenseError').classList.add('hidden');

            document.getElementById('expenseModal').classList.add('active');
        }

        function closeExpenseModal() {
            document.getElementById('expenseModal').classList.remove('active');
        }

        async function submitExpense() {
            // Prevent accidental double-submit
            const addBtn = document.querySelector('#expenseModal button[onclick="submitExpense()"]');
            if (addBtn) {
                addBtn.disabled = true;
                // lock click + show progress state if present
                // addBtn.textContent = addBtn.textContent;
            }

            const eventId = document.getElementById('expenseEventId').value;

            const description = document.getElementById('expenseDescription').value.trim();
            const amount = parseFloat(document.getElementById('expenseAmount').value);
            const category = document.getElementById('expenseCategory').value;
            const errorBox = document.getElementById('expenseError');

            if (!eventId || !description || !category) {

                errorBox.textContent = 'Please fill in expense description, cost and category.';

                errorBox.classList.remove('hidden');
                return;
            }
            if (Number.isNaN(amount) || amount <= 0) {
                errorBox.textContent = 'Expense amount must be greater than zero.';
                errorBox.classList.remove('hidden');
                return;
            }

            try {
                const response = await fetch('<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'add_expense',
                        amount,
                        category,
                        description,
                        // keep compatibility with API that expects event_id
                        event_id: document.getElementById('expenseEventId').value
                    })
                });

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Unable to save expense');
                }

                closeExpenseModal();
                location.reload();
            } catch (err) {
                errorBox.textContent = err.message;
                errorBox.classList.remove('hidden');
            }
        }

        async function toggleExpenses(eventId) {
            const block = document.getElementById('expensesBlock_' + eventId);
            const listEl = document.getElementById('expensesList_' + eventId);
            const toggleTextEl = document.getElementById('expensesToggleText_' + eventId);

            if (!block || !listEl || !toggleTextEl) return;

            const isHidden = listEl.style.display === 'none' || listEl.style.display === '';
            if (isHidden) {
                toggleTextEl.textContent = 'Loading...';
                listEl.style.display = 'block';
                await fetchExpensesForEvent(eventId);
                toggleTextEl.textContent = 'Hide expenses';
            } else {
                listEl.style.display = 'none';
                toggleTextEl.textContent = 'View expenses';
            }
        }

        async function fetchExpensesForEvent(eventId) {
            const listEl = document.getElementById('expensesList_' + eventId);
            if (!listEl) return;

            try {
                const response = await fetch('<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Cache-Control':'no-store'},
                    body: JSON.stringify({
                        action: 'list_expenses',
                        event_id: Number(eventId)
                    })
                });



                const data = await response.json();
                console.log('list_expenses response for event', eventId, data);
                if (!data.success) {
                    throw new Error(data.error || 'Unable to load expenses');
                }

                const expenses = data.expenses || [];

                if (expenses.length === 0) {
                    listEl.innerHTML = '<div class="empty-title" style="font-size:14px; font-weight:900; color:#6b7c6d;">No expenses yet</div>';
                    return;
                }

                listEl.innerHTML = expenses.map(exp => {
                    const amount = Number(exp.amount || 0);
                    const desc = exp.description ? exp.description : exp.category;
                    const date = exp.expense_date ? exp.expense_date : '';
                    return `
                        <div class="expense-row">
                            <div class="expense-meta">
                                <div class="expense-desc">${escapeHtml(desc)}</div>
                                <div class="expense-sub">${escapeHtml(exp.category || '')}${date ? ' • ' + escapeHtml(date) : ''}</div>
                            </div>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <div class="expense-amount">₱${amount.toFixed(2)}</div>
                                <button type="button" class="action-delete" aria-label="Delete expense" title="Delete expense" onclick="deleteExpense('${exp.id}', '${eventId}')">
                                    <i class="fas fa-trash"></i>
                                </button>

                                <span style="display:none" data-expense-id="${exp.id}"></span>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch (err) {
                listEl.innerHTML = '<div class="empty-title" style="font-size:14px; font-weight:900; color:#b91c1c;">' + escapeHtml(err.message || 'Unable to load expenses') + '</div>';
            }
        }

        function escapeHtml(str) {
            return String(str ?? '').replace(/[&<>"']/g, function (m) {
                const map = {'&':'&amp;','<':'<','>':'>','"':'"',"'":'&#039;'};
                return map[m];
            });
        }

        async function deleteExpense(expenseId, eventId) {
            if (!expenseId) return;
            if (!confirm('Delete this expense?')) return;

            try {
                const response = await fetch('<?= htmlspecialchars($apiUrl, ENT_QUOTES, 'UTF-8') ?>', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'delete_expense',
                        id: expenseId
                    })
                });

                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.error || 'Unable to delete expense');
                }

                await fetchExpensesForEvent(eventId);
            } catch (err) {
                alert(err.message || 'Unable to delete expense');
            }
        }

        // Mobile
        document.getElementById('mobileToggle').addEventListener('click', () => {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('active');
        });

        document.getElementById('overlay').addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('overlay').classList.remove('active');
        });
    </script>
</body>
</html>

