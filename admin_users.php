<?php
require_once 'config/config.php';
require_once 'auth.php';

guardAdmin();

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = "WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($roleFilter) {
    $where .= " AND role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter) {
    $where .= " AND active = ?";
    $params[] = $statusFilter === 'active' ? 1 : 0;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$limitOffset = " LIMIT $perPage OFFSET $offset";
$sql = "SELECT u.*, 
    (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id AND b.status != 'cancelled') as booking_count,
    (SELECT COALESCE(SUM(ex.amount), 0)
     FROM bookings b
     INNER JOIN events e2 ON e2.id = b.event_id
     LEFT JOIN expenses ex ON ex.event_id = e2.id
     WHERE b.user_id = u.id
       AND b.status != 'cancelled'
       AND e2.status != 'cancelled') as total_budget_spent
    FROM users u $where ORDER BY u.created_at DESC" . $limitOffset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users $where");
$countStmt->execute($params);
$totalUsers = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($totalUsers / $perPage);

try {
    $activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE active = 1")->fetchColumn();
    $inactiveUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE active = 0")->fetchColumn();
} catch (Exception $e) {
    $activeUsers = 0;
    $inactiveUsers = 0;
}

try {
    $roleStatsStmt = $pdo->prepare(" 
        SELECT 
            ROUND(AVG(COALESCE(total_budget_spent, 0)), 2) as avg_budget
        FROM (
            SELECT u.id, COALESCE(SUM(ex.amount), 0) as total_budget_spent
            FROM users u
            LEFT JOIN bookings b ON b.user_id = u.id AND b.status != 'cancelled'
            LEFT JOIN events e ON e.id = b.event_id AND e.status != 'cancelled'
            LEFT JOIN expenses ex ON ex.event_id = e.id
            GROUP BY u.id
        ) t
    ");
    $roleStatsStmt->execute();
    $totalAvgBudget = (float)$roleStatsStmt->fetchColumn();
} catch (Exception $e) {
    $totalAvgBudget = 0;
}

$userName = $_SESSION['user_name'] ?? 'Admin';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cavendia — User Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --sage:#A3B18A;
            --sage-dark:#8A9A6D;
            --cream:#F1F2EE;
            --forest:#1B4332;
            --white:#FFFFFF;
            --text:#2B3A33;
            --muted:#6B7C6D;
            --border:#D8DDD3;
            --shadow: 0 8px 32px rgba(27,67,50,0.12);
            --shadow-hover: 0 20px 40px rgba(27,67,50,0.2);
        }
        *{box-sizing:border-box;margin:0;padding:0;}
        body{
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, var(--cream) 0%, #e8ebe3 100%);
            color: var(--text);
            min-height:100vh;
            line-height: 1.6;
        }

        /* ── Sidebar ── */
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

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(27, 67, 50, 0.7);
            backdrop-filter: blur(8px);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Ensure modal overlays never block clicks when hidden */
        .modal-overlay { pointer-events: none; }
        .modal-overlay.active { pointer-events: auto; }


        /* Create User Modal */
        .create-user-modal {
            background: var(--white);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(27, 67, 50, 0.25);
            max-width: 520px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9) translateY(-20px);
            transition: all 0.3s ease;
            border: 1px solid rgba(163, 177, 138, 0.2);
        }

        .modal-overlay.active .create-user-modal {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            padding: 2rem 2.5rem 1.5rem;
            border-bottom: 1px solid var(--border);
            position: relative;
        }

        .modal-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--forest);
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0;
        }

        .modal-close {
            position: absolute;
            top: 1.75rem;
            right: 2rem;
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(216, 221, 211, 0.5);
            border: none;
            color: var(--muted);
            font-size: 1.1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            transform: scale(1.05);
        }

        .modal-body {
            padding: 2rem 2.5rem 2.5rem;
        }

        .form-group {
            margin-bottom: 1.75rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--forest);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid var(--border);
            border-radius: 16px;
            font-size: 16px;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.9);
            transition: all 0.2s ease;
            font-family: inherit;
        }

        /* ENHANCED VALIDATION UI WITH RED "!" IN CORNER */
        .field {
            position: relative;
        }

        .field.has-error .form-input {
            border-color: #d32f2f !important;
            background: var(--white) !important;
            box-shadow: 0 0 0 4px rgba(211, 47, 47, 0.08) !important;
            padding-right: 50px; /* Space for error icon */
        }

        .field .error-icon {
            display: none;
            position: absolute;
            right: 14px;
            top: 10px;
            transform: none;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: #d32f2f;
            color: #fff !important;
            font-weight: 900 !important;
            font-size: 13px !important;
            align-items: center;
            justify-content: center;
            z-index: 2;
            box-shadow: 0 2px 8px rgba(211, 47, 47, 0.3);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 2px 8px rgba(211, 47, 47, 0.3); }
            50% { box-shadow: 0 2px 12px rgba(211, 47, 47, 0.5); }
            100% { box-shadow: 0 2px 8px rgba(211, 47, 47, 0.3); }
        }

        .field.has-error .error-icon {
            display: flex !important;
        }

        .field .error-msg {
            display: none;
            margin-top: 8px;
            font-size: 13px;
            color: #d32f2f;
            font-weight: 700;
            padding-left: 4px;
        }

        .field.has-error .error-msg {
            display: block !important;
        }

        /* Ensure password toggle doesn't conflict */
        .field .action-btn {
            z-index: 3 !important;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--sage);
            box-shadow: 0 0 0 4px rgba(163, 177, 138, 0.1);
            background: var(--white);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .help {
            font-size: 13px;
            color: var(--muted);
            margin-top: 4px;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .btn {
            padding: 0.875rem 2rem;
            border-radius: 16px;
            font-weight: 700;
            font-size: 15px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            height: 52px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--sage), var(--sage-dark));
            color: var(--white);
            box-shadow: 0 8px 25px rgba(163, 177, 138, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 35px rgba(163, 177, 138, 0.4);
        }

        .btn-secondary {
            background: rgba(216, 221, 211, 0.6);
            color: var(--forest);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            background: rgba(163, 177, 138, 0.15);
            border-color: var(--sage);
            transform: translateY(-1px);
        }

        .main-content { 
            margin-left: 280px; 
            transition: margin-left 0.3s ease, filter 0.3s ease; 
            padding: 6rem 2rem 2rem; 
            min-height: calc(100vh - 6rem); 
            width: calc(100% - 280px); 
            overflow-x: hidden; 
            z-index: 10;
        }

        .page-head{
            display:flex;align-items:flex-start;justify-content:space-between;gap:18px;flex-wrap:wrap;
            margin-bottom:20px;
        }
        .page-head h1{font-size:34px;line-height:1.1;color:var(--forest);font-weight:900;}
        .page-head p{color:var(--muted);font-weight:500;margin-top:8px;}

        .card{
            background: var(--white);
            border:1px solid var(--border);
            border-radius:22px;
            box-shadow: var(--shadow);
            transition: filter 0.3s ease;
        }

        .controls{
            padding:16px;
            display:flex;gap:12px;align-items:center;flex-wrap:wrap;
            border-radius:20px;
            background: linear-gradient(180deg, rgba(241,242,238,0.9) 0%, rgba(241,242,238,0.6) 100%);
            transition: filter 0.3s ease;
        }
        .pill{
            border-radius:999px;
            border:1px solid var(--border);
            background: rgba(255,255,255,0.9);
            height:44px;
            display:flex;align-items:center;gap:10px;
            padding:0 14px;
            transition: filter 0.3s ease;
        }
        .pill input{
            border:none;outline:none;background:transparent;
            width:320px;max-width:70vw;
            font-size:14px;font-weight:600;color:var(--forest);
        }
        .pill i{color: var(--muted);}
        .pill select{
            border:none;outline:none;background:transparent;
            font-size:14px;font-weight:700;color:var(--forest);
        }

        /* Create User Button Styling */
        .create-user-btn {
            padding: 0.75rem 1.25rem;
            border-radius: 1.5rem;
            background: linear-gradient(135deg, var(--sage), var(--sage-dark));
            color: var(--white);
            border: none;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(163,177,138,0.3);
            transition: all 0.3s ease;
            height: 44px;
            white-space: nowrap;
        }

        .create-user-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(163,177,138,0.4);
        }

        .table-wrap{padding:18px; margin-top:18px;}
        .table-head{
            display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;
            padding:6px 6px 14px;
        }
        .table-head .title{
            display:flex;align-items:center;gap:10px;
            font-weight:900;color:var(--forest);font-size:18px;
        }
        .badge-count{
            background: rgba(163,177,138,0.16);
            border:1px solid rgba(163,177,138,0.35);
            color: var(--sage-dark);
            padding:6px 12px;border-radius:999px;
            font-weight:800;font-size:13px;
        }

        table{width:100%;border-collapse:separate;border-spacing:0;}
        thead th{
            position:sticky;top:0;
            background: rgba(163,177,138,0.15);
            color: var(--sage-dark);
            padding:14px 14px;
            font-size:12px;
            letter-spacing:.06em;
            text-transform:uppercase;
            z-index:1;
            border-top-left-radius:16px;
            border-top-right-radius:16px;
        }
        tbody td{
            padding:14px 14px;
            border-bottom:1px solid rgba(216,221,211,0.9);
            vertical-align:middle;
            font-size:14px;
        }
        tbody tr:hover td{background: rgba(241,242,238,0.55);}

        .name-cell{display:flex;align-items:center;gap:12px;}
        .avatar{
            width:38px;height:38px;border-radius:14px;
            background: linear-gradient(135deg, var(--sage) 0%, var(--sage-dark) 100%);
            display:flex;
            align-items:center;
            justify-content:center;
            color: var(--white);
            font-weight:800;
            font-size:0.85rem;
        }

        .user-actions{display:flex;gap:10px;flex-wrap:wrap;}
        .action-btn{border:none;cursor:pointer;padding:8px 12px;border-radius:12px;font-weight:800;font-size:13px;transition:all .2s ease;display:inline-flex;align-items:center;gap:8px;}
        .action-btn.danger{background: rgba(239,68,68,0.12); color:#b91c1c; border:1px solid rgba(239,68,68,0.35);} 
        .action-btn.secondary{background: rgba(216,221,211,0.6); color: var(--forest); border:2px solid var(--border);} 
        .action-btn.primary{background: linear-gradient(135deg, var(--sage), var(--sage-dark)); color:var(--white);} 

        .status-pill{padding:6px 12px;border-radius:999px;font-size:12px;font-weight:900;border:1px solid var(--border);display:inline-flex;align-items:center;gap:8px;}
        .status-pill.active{background: rgba(163,177,138,0.16); border-color: rgba(163,177,138,0.35); color: var(--sage-dark);} 
        .status-pill.inactive{background: rgba(239,68,68,0.08); border-color: rgba(239,68,68,0.25); color:#b91c1c;} 

        .pagination{margin-top:16px;display:flex;gap:10px;align-items:center;justify-content:center;flex-wrap:wrap;}
        .page-link{padding:10px 14px;border-radius:14px;border:1px solid var(--border);background: rgba(255,255,255,0.8);text-decoration:none;color:var(--forest);font-weight:800;font-size:13px;}
        .page-link.active{background: rgba(163,177,138,0.18); border-color: rgba(163,177,138,0.45);} 

        .hidden{display:none !important;}

        /* Responsive */
        @media (max-width: 1024px) {
            .mobile-toggle{display:flex;}
            .sidebar{transform: translateX(-100%);} 
            .sidebar.open{transform: translateX(0);} 
            .main-content{margin-left:0; width:100%;}
        }

        @media (max-width: 640px) {
            .form-row{grid-template-columns:1fr;}
            .pill input{width: 200px;}
        }
    </style>
</head>
<body>
    <button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>

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
            <a href="admin_users.php" class="sidebar-item active">
                <i class="fas fa-users"></i><span>Manage Users</span>
            </a>
<a href="admin_bookings.php" class="sidebar-item">
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
        <div class="page-head">
            <div>
                <h1>User Management</h1>
                <p>Search, create, update and manage user accounts.</p>
            </div>
            <button class="create-user-btn" id="openCreateUser">
                <i class="fas fa-user-plus"></i> Create User
            </button>
        </div>

        <section class="card">
            <div class="controls">
                <div class="pill">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search name or email" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>

                <div class="pill">
                    <i class="fas fa-user-tag"></i>
                    <select id="roleSelect">
                        <option value="" <?= empty($_GET['role']) ? 'selected' : '' ?>>All roles</option>
                        <option value="user" <?= (($_GET['role'] ?? '')==='user') ? 'selected' : '' ?>>User</option>
                        <option value="admin" <?= (($_GET['role'] ?? '')==='admin') ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>

                <div class="pill">
                    <i class="fas fa-circle"></i>
                    <select id="statusSelect">
                        <option value="" <?= empty($_GET['status']) ? 'selected' : '' ?>>All status</option>
                        <option value="active" <?= (($_GET['status'] ?? '')==='active') ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= (($_GET['status'] ?? '')==='inactive') ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>

                <button class="action-btn primary" style="height:44px" id="applyFilters"><i class="fas fa-filter"></i> Apply</button>
            </div>

            <div class="table-wrap">
                <div class="table-head">
                    <div class="title">Users <span class="badge-count"><?= (int)($totalUsers ?? 0) ?></span></div>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <span class="badge-count" title="Active users">Active: <?= (int)($activeUsers ?? 0) ?></span>
                        <span class="badge-count" title="Inactive users">Inactive: <?= (int)($inactiveUsers ?? 0) ?></span>
                    </div>
                </div>

                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Bookings</th>
                                <th>Total Spent</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <div class="name-cell">
                                            <div class="avatar"><?= strtoupper(substr($u['name'] ?? '', 0, 2)) ?></div>
                                            <div>
                                                <div style="font-weight:900;color:var(--forest);"><?= htmlspecialchars($u['name'] ?? '') ?></div>
                                                <div style="color:var(--muted);font-size:12px;font-weight:700;"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-weight:900;"><?= htmlspecialchars($u['role'] ?? 'user') ?></td>
                                    <td>
                                        <?php $isActive = (int)($u['active'] ?? 1) === 1; ?>
                                        <span class="status-pill <?= $isActive ? 'active' : 'inactive' ?>">
                                            <span style="width:10px;height:10px;border-radius:50%;background:<?= $isActive ? 'var(--sage-dark)' : '#ef4444' ?>;"></span>
                                            <?= $isActive ? 'Active' : 'Inactive' ?>
                                        </span>
                                    </td>
                                    <td style="font-weight:900;"><?= (int)($u['booking_count'] ?? 0) ?></td>
                                    <td style="font-weight:900;">₱<?= number_format((float)($u['total_budget_spent'] ?? 0), 2) ?></td>
                                    <td style="text-align:center;">
                                        <div class="user-actions" style="justify-content:center;">
                                            <button type="button" class="action-btn secondary" data-action="edit" data-id="<?= (int)$u['id'] ?>"><i class="fas fa-edit"></i> Edit</button>
                                            <button type="button" class="action-btn danger" data-action="delete" data-id="<?= (int)$u['id'] ?>" onclick="return confirm('Delete this user?')"><i class="fas fa-trash"></i> Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php for ($p=1; $p <= $totalPages; $p++): ?>
                            <?php
                                $qs = http_build_query([
                                    'search' => $_GET['search'] ?? '',
                                    'role' => $_GET['role'] ?? '',
                                    'status' => $_GET['status'] ?? '',
                                    'page' => $p
                                ]);
                            ?>
                            <a class="page-link <?= $p === $page ? 'active' : '' ?>" href="admin_users.php?<?= $qs ?>"><?= $p ?></a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <!-- Create User Modal -->
    <div class="modal-overlay" id="createUserModal">
        <div class="create-user-modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-user-plus"></i> Create User</h3>
                <button class="modal-close" id="closeCreateUser" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <form id="createUserForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First name</label>
                            <input class="form-input" name="firstName" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last name</label>
                            <input class="form-input" name="lastName" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Middle name</label>
                            <input class="form-input" name="middleName">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Suffix</label>
                            <input class="form-input" name="suffix">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email (Gmail only)</label>
                        <input class="form-input" type="email" name="email" required>
                        <div class="help">API requires @gmail.com</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact number (11 digits)</label>
                        <input class="form-input" name="contact" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input class="form-input" name="address" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select class="form-input" name="role">
                                <option value="user">user</option>
                                <option value="admin">admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input class="form-input" type="password" name="password" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current admin password (required)</label>
                        <input class="form-input" type="password" name="currentpassword" required>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" id="cancelCreate">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                    </div>
                </form>
                <div id="createUserMsg" style="margin-top:12px;font-weight:900;color:var(--muted);"></div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editUserModal">
        <div class="create-user-modal">
            <div class="modal-header">
                <h3 class="modal-title"><i class="fas fa-edit"></i> Edit User</h3>
                <button class="modal-close" id="closeEditUser" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    <input type="hidden" name="id" id="editUserId">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">First name</label>
                            <input class="form-input" name="firstName" id="editFirstName" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last name</label>
                            <input class="form-input" name="lastName" id="editLastName" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Middle name</label>
                            <input class="form-input" name="middleName" id="editMiddleName">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Suffix</label>
                            <input class="form-input" name="suffix" id="editSuffix">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input class="form-input" type="email" name="email" id="editEmail" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact number</label>
                        <input class="form-input" name="contact" id="editContact" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <input class="form-input" name="address" id="editAddress" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select class="form-input" name="role" id="editRole">
                                <option value="user">user</option>
                                <option value="admin">admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New password (optional)</label>
                            <input class="form-input" type="password" name="password" id="editPassword">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Current admin password (required)</label>
                        <input class="form-input" type="password" name="currentpassword" id="editCurrentPassword" required>
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" id="cancelEdit">Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                    </div>
                </form>

                <hr style="border:none;border-top:1px solid var(--border);margin:20px 0;">
                <!-- User Budget & Expenses removed per request -->
                <div id="editUserMsg" style="margin-top:12px;font-weight:900;color:var(--muted);"></div>
            </div>
        </div>
    </div>


    <script>
        const sidebar = document.getElementById('sidebar');
        const mobileToggle = document.getElementById('mobileToggle');
        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
            });
        }

        // Filters
        document.getElementById('applyFilters').addEventListener('click', () => {
            const search = document.getElementById('searchInput').value.trim();
            const role = document.getElementById('roleSelect').value;
            const status = document.getElementById('statusSelect').value;
            const qs = new URLSearchParams();
            if (search) qs.set('search', search);
            if (role) qs.set('role', role);
            if (status) qs.set('status', status);
            window.location.href = 'admin_users.php' + (qs.toString() ? ('?' + qs.toString()) : '');
        });

        // Modal helpers
        const createModal = document.getElementById('createUserModal');
        const editModal = document.getElementById('editUserModal');

        const openCreateUserBtn = document.getElementById('openCreateUser');
        openCreateUserBtn?.addEventListener('click', () => {
            createModal.classList.add('active');
        });

        document.getElementById('closeCreateUser')?.addEventListener('click', () => createModal.classList.remove('active'));
        document.getElementById('cancelCreate')?.addEventListener('click', () => createModal.classList.remove('active'));

        document.getElementById('closeEditUser')?.addEventListener('click', () => editModal.classList.remove('active'));
        document.getElementById('cancelEdit')?.addEventListener('click', () => editModal.classList.remove('active'));

        // Create user
        document.getElementById('createUserForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('createUserMsg');
            msg.textContent = 'Creating...';

            const form = e.target;
            const payload = {
                action: 'create_user',
                firstName: form.firstName.value,
                lastName: form.lastName.value,
                middleName: form.middleName.value,
                suffix: form.suffix.value,
                email: form.email.value,
                contact: form.contact.value,
                address: form.address.value,
                role: form.role.value,
                password: form.password.value,
                currentpassword: form.currentpassword.value
            };

            const res = await fetch('api.php?type=events', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                msg.style.color = 'var(--forest)';
                msg.textContent = data.message || 'User created';
                setTimeout(() => window.location.reload(), 800);
            } else {
                msg.style.color = '#b91c1c';
                msg.textContent = data.error || 'Create failed';
            }
        });

        async function loadUserForEdit(id) {
            const res = await fetch('api.php?type=events', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_user', id: id})
            });
            const data = await res.json();
            if (!data.success) throw new Error(data.error || 'Failed to load user');
            return data.user;
        }

        // Budget and expense management UI/logic removed per request.


        // Edit button click
        document.querySelectorAll('button[data-action="edit"]').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.getAttribute('data-id');
                const msg = document.getElementById('editUserMsg');
                msg.textContent = 'Loading...';
                try {
                    const u = await loadUserForEdit(id);
                    document.getElementById('editUserId').value = u.id;
                    document.getElementById('editFirstName').value = u.firstname || '';
                    document.getElementById('editLastName').value = u.lastname || '';
                    document.getElementById('editMiddleName').value = u.middlename || '';
                    document.getElementById('editSuffix').value = u.suffix || '';
                    document.getElementById('editEmail').value = u.email || '';
                    document.getElementById('editContact').value = u.contact_number || '';
                    document.getElementById('editAddress').value = u.address || '';
                    document.getElementById('editRole').value = u.role || 'user';
                    document.getElementById('editCurrentPassword').value = '';
                    document.getElementById('editPassword').value = '';

                    msg.textContent = '';
                    editModal.classList.add('active');
                } catch (err) {
                    msg.style.color = '#b91c1c';
                    msg.textContent = err.message;
                }
            });

        });

        document.getElementById('editUserForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const msg = document.getElementById('editUserMsg');
            msg.textContent = 'Updating...';

            const form = e.target;
            const payload = {
                action: 'update_user',
                id: form.elements['id'].value,
                firstName: form.firstName.value,
                lastName: form.lastName.value,
                middleName: form.middleName.value,
                suffix: form.suffix.value,
                email: form.email.value,
                contact: form.contact.value,
                address: form.address.value,
                role: form.role.value,
                password: form.password.value,
                currentpassword: form.currentpassword.value
            };

            const res = await fetch('api.php?type=events', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                msg.style.color = 'var(--forest)';
                msg.textContent = data.message || 'User updated';
                setTimeout(() => window.location.reload(), 800);
            } else {
                msg.style.color = '#b91c1c';
                msg.textContent = data.error || 'Update failed';
            }
        });

        // Delete click uses inline confirm; implement actual request if user wants AJAX.
        // For now, keep UX simple: reload after delete request.
        document.querySelectorAll('button[data-action="delete"]').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                // if confirm returns false, don't run
                // (inline handler already confirmed)
                e.preventDefault();
                const id = btn.getAttribute('data-id');
                if (!confirm('Delete this user?')) return;

                const res = await fetch('api.php?type=events', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action:'delete_user', id: id})
                });
                const data = await res.json();
                if (data.success) location.reload();
                else alert(data.error || 'Delete failed');
            });
        });
    </script>
</body>
</html>

