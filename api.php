<?php
/**
 * RESTful API for CAVENDIA Event Planner
 * 
 * Endpoints:
 * - GET /api.php?type=events - Get all events
 * - POST /api.php?type=events - Create event (admin only)
 * - PUT /api.php?type=events - Update event (admin only)
 * - DELETE /api.php?type=events - Delete event (admin only)
 * 
 * - POST /api.php?type=book - Book an event
 * - GET /api.php?type=bookings - Get user bookings
 * - DELETE /api.php?type=bookings - Cancel booking
 * - GET /api.php?type=expenses&event_id=<event_id> - Get event expenses for the logged-in user
 * 
 * - POST /api.php?type=chat - Send message
 * - GET /api.php?type=chat - Get messages
 */

require_once __DIR__ . '/config/config.php';


header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request type and method
$type = $_GET['type'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Support admin_bookings.php compatibility:
// 1) If UI calls /api.php?action=list_expenses, map it.
if ($type === '' && isset($_GET['action']) && $_GET['action'] === 'list_expenses') {
    $type = 'expenses';
}

// 2) If UI calls /api.php?type=list_expenses, map it to the existing GET expenses endpoint.
if ($type === 'list_expenses') {
    $type = 'expenses';
}



// ---- ADMIN bookings status update (must handle regardless of type routing) ----
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $tmp = json_decode($raw, true);

    if (is_array($tmp) && (($tmp['action'] ?? '') === 'update_booking_status')) {
        // Helper: send JSON response
        // (sendResponse is defined later in file, so just output directly)
        if (!isset($_SESSION) || (
            !((isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') ||
              (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'))
        )) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Admin access required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $bookingId = (int)($tmp['id'] ?? 0);
        $newStatus = $tmp['status'] ?? '';

        if ($bookingId <= 0) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Booking id is required'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        if (!in_array($newStatus, ['confirmed', 'cancelled'], true)) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Invalid status'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        require_once __DIR__ . '/config/config.php';
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $bookingId]);

        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Booking not found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $pdo->commit();
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Booking status updated'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

// Allow logged-in users to add an expense via JSON POST { action: 'add_expense', event_id, amount, category, expense_date, description }
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $tmp = json_decode($raw, true);
    if (is_array($tmp) && (($tmp['action'] ?? '') === 'add_expense')) {
        require_once __DIR__ . '/config/config.php';

        $eventId = $tmp['event_id'] ?? '';
        $bookingId = $tmp['booking_id'] ?? '';
        $category = sanitizeInput($tmp['category'] ?? '');
        $amount = (float)($tmp['amount'] ?? 0);
        $expenseDate = $tmp['expense_date'] ?? date('Y-m-d');
        $description = sanitizeInput($tmp['description'] ?? '');

        if (!$eventId && $bookingId) {
            // derive event_id from booking; admins may derive for any booking
            if (isAdmin()) {
                $stmtB = $pdo->prepare("SELECT event_id FROM bookings WHERE id = ? LIMIT 1");
                $stmtB->execute([$bookingId]);
            } else {
                $stmtB = $pdo->prepare("SELECT event_id FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
                $stmtB->execute([$bookingId, $_SESSION['user_id']]);
            }
            $rowB = $stmtB->fetch(PDO::FETCH_ASSOC);
            if ($rowB) {
                $eventId = $rowB['event_id'];
            }
        }

        if (!$eventId || $amount <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid event ID or booking ID and amount required']);
            exit;
        }

        if (!isUserLoggedIn()) {
            http_response_code(403);
            echo json_encode(['error' => 'Login required']);
            exit;
        }

        // Ensure the requester either has a booking for this event or is an admin
        if (!isAdmin()) {
            $bookingCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE event_id = ? AND user_id = ? AND status != 'cancelled'");
            $bookingCheckStmt->execute([$eventId, $_SESSION['user_id']]);
            if ($bookingCheckStmt->fetchColumn() == 0) {
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized event or booking not found']);
                exit;
            }
        }

        // Ensure expenses table has created_by column to track owner
        try {
            $col = $pdo->query("SHOW COLUMNS FROM expenses LIKE 'created_by'")->fetch(PDO::FETCH_ASSOC);
            if (!$col) {
                $pdo->exec("ALTER TABLE expenses ADD COLUMN created_by INT NULL AFTER description");
            }
        } catch (PDOException $e) {
            // ignore
        }

        // Verify booking exists and compute remaining budget (admins can inspect any booking)
        if ($bookingId) {
            if (isAdmin()) {
                $bkStmt = $pdo->prepare("SELECT budget FROM bookings WHERE id = ? LIMIT 1");
                $bkStmt->execute([$bookingId]);
            } else {
                $bkStmt = $pdo->prepare("SELECT budget FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
                $bkStmt->execute([$bookingId, $_SESSION['user_id']]);
            }
            $bk = $bkStmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $bk = null;
        }
        $bookingBudget = $bk ? (float)$bk['budget'] : null;

        $spentStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as s FROM expenses WHERE event_id = ?");
        $spentStmt->execute([$eventId]);
        $eventSpent = (float)$spentStmt->fetchColumn();

        if ($bookingBudget !== null) {
            $remaining = $bookingBudget - $eventSpent;
            if ($amount > $remaining) {
                http_response_code(400);
                echo json_encode(['error' => 'Insufficient budget. Remaining: ' . number_format($remaining,2)]);
                exit;
            }
        }

        $expId = uniqid('exp_');
        $stmt = $pdo->prepare("INSERT INTO expenses (id, event_id, category, amount, expense_date, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$expId, $eventId, $category, $amount, $expenseDate, $description, $_SESSION['user_id']]);

        echo json_encode(['success' => true, 'expense_id' => $expId]);
        exit;
    }
}

// Allow logged-in users (and admins) to list expenses for a given event via JSON POST
// { action: 'list_expenses', event_id } (keeps backward compatibility with older UIs)
// NOTE: This block is the authoritative handler for the admin_bookings.php UI.
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $tmp = json_decode($raw, true);
    if (is_array($tmp) && (($tmp['action'] ?? '') === 'list_expenses')) {
        if (!isUserLoggedIn() && !isAdmin()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Please login'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $eventId = $tmp['event_id'] ?? '';
        if (!$eventId) {
            http_response_code(200);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'expenses' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        // Permissions: admins can list any event; users must have access via bookings.
        if (!isAdmin()) {
            $stmt = $pdo->prepare(
                "SELECT e.id
                 FROM events e
                 LEFT JOIN bookings b ON b.event_id = e.id AND b.user_id = ? AND b.status != 'cancelled'
                 WHERE e.id = ? AND (e.user_id = ? OR b.id IS NOT NULL)"
            );
            $stmt->execute([$_SESSION['user_id'], $eventId, $_SESSION['user_id']]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$event) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Event not found or not accessible'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                exit;
            }
        }

        $stmt = $pdo->prepare(
            "SELECT id, category, amount, expense_date, description,
                    created_at, created_by
             FROM expenses
             WHERE event_id = ?
             ORDER BY expense_date DESC, created_at DESC"
        );
        $stmt->execute([$eventId]);
        $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($expenses as &$expense) {
            $expense['amount'] = (float)($expense['amount'] ?? 0);
            if (!empty($expense['expense_date'])) {
                $expense['expense_date'] = date('F j, Y', strtotime($expense['expense_date']));
            }
            $expense['created_by'] = isset($expense['created_by']) ? (int)$expense['created_by'] : null;
        }

        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'expenses' => $expenses], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}


// Allow logged-in users to delete their own expense via JSON POST { action: 'delete_expense', id }
if ($method === 'POST') {

    $raw = file_get_contents('php://input');
    $tmp = json_decode($raw, true);
    if (is_array($tmp) && (($tmp['action'] ?? '') === 'delete_expense')) {
        if (!isUserLoggedIn()) {
            http_response_code(403);
            echo json_encode(['error' => 'Login required']);
            exit;
        }
        $id = $tmp['id'] ?? '';
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Expense ID required']);
            exit;
        }

        // Ensure created_by exists
        try {
            $col = $pdo->query("SHOW COLUMNS FROM expenses LIKE 'created_by'")->fetch(PDO::FETCH_ASSOC);
            if (!$col) {
                http_response_code(403);
                echo json_encode(['error' => 'Deletion not permitted']);
                exit;
            }
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT created_by FROM expenses WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'Expense not found']);
            exit;
        }

        // Allow deletion if owner or admin
        if ((int)$row['created_by'] !== (int)($_SESSION['user_id'] ?? 0) && !isAdmin()) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authorized to delete this expense']);
            exit;
        }

        $del = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
        $del->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }
}


// If no type is provided, try to infer from admin booking calls
if ($type === '' && $method === 'POST') {
    // admin_bookings.php sends JSON {action:'update_booking_status', ...} to /api.php
    $raw = file_get_contents('php://input');
    $tmp = json_decode($raw, true);
    if (is_array($tmp) && (($tmp['action'] ?? '') === 'update_booking_status')) {
        $type = 'bookings';
        // Put data back so downstream logic can read it
        file_put_contents('php://temp', $raw);
    }
}

// Helper function to send JSON response
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}


// Helper to check if user is admin
function isAdmin() {
    // Some pages use user_role, others may store admin role differently.
    return (
        (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') ||
        (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')
    );
}


// Helper to check if user is logged in
function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Helper to validate and sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Helper to validate date format (YYYY-MM-DD)
function isValidDate($date) {
    return strtotime($date) !== false && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
}

// ============ EVENT API ============
if ($type === 'events') {
    
    // GET - Fetch all events
    if ($method === 'GET') {
        try {
            $stmt = $pdo->query("SELECT id, title, date, location, description, budget, max_attendees, image, status FROM events WHERE status != 'cancelled' ORDER BY date ASC");
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format dates for JSON
            foreach ($events as &$event) {
                $event['date'] = date('F j, Y', strtotime($event['date']));
                $event['formatted_budget'] = '₱' . number_format($event['budget'], 2);
            }
            
            sendResponse(['success' => true, 'events' => $events, 'count' => count($events)]);
        } catch (PDOException $e) {
            sendResponse(['error' => 'Failed to fetch events'], 500);
        }
    }
    
// POST /api.php?action=create_event or update_event - Admin CRUD
if ($method === 'POST') {
        if (!isAdmin()) {
            sendResponse(['error' => 'Admin access required'], 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(['error' => 'Invalid JSON'], 400);
        }
        
$action = $input['action'] ?? '';
        if (!in_array($action, ['create_event', 'update_event', 'delete_event', 'get_event', 'add_expense', 'list_expenses', 'update_expense', 'delete_expense', 'create_user', 'get_user', 'update_user', 'delete_user', 'get_user_events_with_budget_spent', 'update_event_budget', 'update_booking_status'])) {
            sendResponse(['error' => 'Invalid action'], 400);
        }




        
        switch ($action) {
            case 'create_user':
                if (!isAdmin()) {
                    sendResponse(['error' => 'Admin access required'], 403);
                }

                // ------------------------------
                // NOTE: Admin user CRUD actions are implemented below.
                // ------------------------------

                


                // Admin-created payload from admin_users.php:
                // firstName, lastName, middleName, suffix, email, phone(contact), address, role, password, currentpassword
                $firstName = sanitizeInput($input['firstName'] ?? '');
                $lastName = sanitizeInput($input['lastName'] ?? '');
                $middleName = sanitizeInput($input['middleName'] ?? '');
                $suffix = sanitizeInput($input['suffix'] ?? '');
                $email = sanitizeInput($input['email'] ?? '');
                $contact = sanitizeInput($input['contact'] ?? ($input['phone'] ?? ''));
                $address = sanitizeInput($input['address'] ?? '');
                $role = sanitizeInput($input['role'] ?? 'user');
                $password = (string)($input['password'] ?? '');
                $currentPassword = (string)($input['currentpassword'] ?? '');

                // Verify admin password (hardcoded, matches admin_login.php)
                if ($currentPassword !== 'admin_demo2026') {
                    sendResponse(['error' => 'Invalid current admin password'], 403);
                }

                // Register-style validations (mirror register.php behavior)
                if (strpos($email, '@gmail.com') === false) {
                    sendResponse(['error' => 'Email must be a Gmail address (@gmail.com)'], 400);
                }
                if (!preg_match('/^\d{11}$/', $contact)) {
                    sendResponse(['error' => 'Contact number must be exactly 11 digits'], 400);
                }


                // Validation
                if (!$firstName || !$lastName || !$email || !$password) {
                    sendResponse(['error' => 'firstName, lastName, email and password are required'], 400);
                }
                if (!$contact) {
                    sendResponse(['error' => 'Contact number is required'], 400);
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    sendResponse(['error' => 'Valid email required'], 400);
                }
                if (strlen($password) < 6) {
                    sendResponse(['error' => 'Password must be at least 6 characters'], 400);
                }
                if (!in_array($role, ['user', 'admin'], true)) {
                    sendResponse(['error' => 'Invalid role'], 400);
                }

                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    sendResponse(['error' => 'Email already registered'], 400);
                }

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $userId = uniqid('user_');

                $fullName = trim($firstName . ' ' . $lastName);

                // Insert (matches actual DB schema in setup.sql)
                // users table columns: id (auto), email (unique), password, name, lastname, firstname, middlename, suffix,
                // address, contact_number, role, photo, created_at/updated_at
                try {
                    $stmt = $pdo->prepare(
                        "INSERT INTO users 
                        (email, password, name, lastname, firstname, middlename, suffix, address, contact_number, role)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );

                    $stmt->execute([
                        $email,
                        $hashedPassword,
                        $fullName,
                        $lastName,
                        $firstName,
                        $middleName !== '' ? $middleName : null,
                        $suffix !== '' ? $suffix : null,
                        $address,
                        $contact,
                        $role
                    ]);

                    $newId = (int)$pdo->lastInsertId();
                    sendResponse(['success' => true, 'user_id' => $newId, 'message' => 'User created successfully']);
                } catch (PDOException $e) {
                    sendResponse(['error' => 'DB error: ' . $e->getMessage()], 500);
                }

                break;

            // ------------------------------
            // Admin user CRUD actions
            // ------------------------------
case 'get_user_events_with_budget_spent':
                if (!isAdmin()) {
                    sendResponse(['error' => 'Admin access required'], 403);
                }

                $userId = (int)($input['user_id'] ?? 0);
                if ($userId <= 0) {
                    sendResponse(['error' => 'user_id is required'], 400);
                }

                // IMPORTANT: Admin needs the events that this user HAS BOOKED.
                // Current UI (admin_users.php) populates the “Select Event” dropdown for booking-related budget/expenses.
                // So we must query events via bookings.user_id, not events.user_id.
                $stmt = $pdo->prepare("SELECT DISTINCT
                    e.id,
                    b.id as booking_id,
                    e.title,
                    e.date,
                    e.budget as event_budget,
                    COALESCE(b.budget, 0) as booking_budget,
                    COALESCE(SUM(ex.amount), 0) as total_spent
                FROM bookings b
                INNER JOIN events e ON e.id = b.event_id
                LEFT JOIN expenses ex ON ex.event_id = e.id
                WHERE b.user_id = ?
                  AND b.status != 'cancelled'
                  AND e.status != 'cancelled'
                GROUP BY e.id, b.id, e.title, e.date, e.budget, b.budget
                ORDER BY e.date ASC");
                $stmt->execute([$userId]);
                $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($events as &$ev) {
                    $ev['budget'] = (float)$ev['booking_budget'] > 0 ? (float)$ev['booking_budget'] : (float)$ev['event_budget'];
                    $ev['formatted_budget'] = '₱' . number_format($ev['budget'], 2);
                    $ev['formatted_spent'] = '₱' . number_format((float)($ev['total_spent'] ?? 0), 2);
                    $ev['remaining_budget'] = $ev['budget'] - (float)($ev['total_spent'] ?? 0);
                }

                sendResponse(['success' => true, 'events' => $events]);
                break;

            case 'update_event_budget':
                if (!isAdmin()) {
                    sendResponse(['error' => 'Admin access required'], 403);
                }

                $eventId = $input['event_id'] ?? null;
                $bookingId = $input['booking_id'] ?? null;
                $budget = $input['budget'] ?? null;
                if (!$eventId && !$bookingId) {
                    sendResponse(['error' => 'event_id or booking_id is required'], 400);
                }
                if ($budget === null) {
                    sendResponse(['error' => 'budget is required'], 400);
                }

                $budgetVal = (float)$budget;
                if ($budgetVal < 0) {
                    sendResponse(['error' => 'budget must be >= 0'], 400);
                }

                if ($bookingId) {
                    $stmt = $pdo->prepare("UPDATE bookings SET budget = ? WHERE id = ?");
                    $stmt->execute([$budgetVal, $bookingId]);
                    if ($stmt->rowCount() === 0) {
                        sendResponse(['error' => 'Booking not found'], 404);
                    }
                    sendResponse(['success' => true, 'message' => 'User event budget updated']);
                }

                $stmt = $pdo->prepare("UPDATE events SET budget = ? WHERE id = ?");
                $stmt->execute([$budgetVal, $eventId]);
                if ($stmt->rowCount() === 0) {
                    sendResponse(['error' => 'Event not found'], 404);
                }

                sendResponse(['success' => true, 'message' => 'Event budget updated']);
                break;


            // ------------------------------
            // Admin user CRUD actions
            // ------------------------------
            case 'get_user':



                if (!isAdmin()) {
                    sendResponse(['error' => 'Admin access required'], 403);
                }

                $id = (int)($input['id'] ?? 0);
                if ($id <= 0) {
                    sendResponse(['error' => 'User ID required'], 400);
                }

                $stmt = $pdo->prepare("SELECT id, email, name, firstname, lastname, middlename, suffix, address, contact_number, role, photo, created_at, updated_at FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    sendResponse(['error' => 'User not found'], 404);
                }

                sendResponse(['success' => true, 'user' => $user]);
                break;

            case 'update_user':
                if (!isAdmin()) {
                    sendResponse(['error' => 'Admin access required'], 403);
                }

                $id = (int)($input['id'] ?? 0);
                if ($id <= 0) {
                    sendResponse(['error' => 'User ID required'], 400);
                }

                $firstName = sanitizeInput($input['firstName'] ?? '');
                $lastName = sanitizeInput($input['lastName'] ?? '');
                $middleName = sanitizeInput($input['middleName'] ?? '');
                $suffix = sanitizeInput($input['suffix'] ?? '');
                $email = sanitizeInput($input['email'] ?? '');
                $contact = sanitizeInput($input['contact'] ?? ($input['phone'] ?? ''));
                $address = sanitizeInput($input['address'] ?? '');
                $role = sanitizeInput($input['role'] ?? 'user');
                $password = (string)($input['password'] ?? '');
                $currentPassword = (string)($input['currentpassword'] ?? '');

                // Keep validations strict when changing password.
                if ($currentPassword !== 'admin_demo2026') {
                    sendResponse(['error' => 'Invalid current admin password'], 403);
                }

                if (!$firstName || !$lastName || !$email) {
                    sendResponse(['error' => 'firstName, lastName and email are required'], 400);
                }

                if (strpos($email, '@gmail.com') === false) {
                    sendResponse(['error' => 'Email must be a Gmail address (@gmail.com)'], 400);
                }

                if ($contact && !preg_match('/^\d{11}$/', $contact)) {
                    sendResponse(['error' => 'Contact number must be exactly 11 digits'], 400);
                }

                if (!in_array($role, ['user', 'admin'], true)) {
                    sendResponse(['error' => 'Invalid role'], 400);
                }

                // If email changed, ensure uniqueness.
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
                $stmt->execute([$email, $id]);
                if ($stmt->fetch()) {
                    sendResponse(['error' => 'Email already registered'], 400);
                }

                $fullName = trim($firstName . ' ' . $lastName);

                $fields = [];
                $params = [];

                $fields[] = 'name = ?'; $params[] = $fullName;
                $fields[] = 'firstname = ?'; $params[] = $firstName;
                $fields[] = 'lastname = ?'; $params[] = $lastName;
                $fields[] = 'middlename = ?'; $params[] = ($middleName !== '' ? $middleName : null);
                $fields[] = 'suffix = ?'; $params[] = ($suffix !== '' ? $suffix : null);
                $fields[] = 'address = ?'; $params[] = $address;
                $fields[] = 'contact_number = ?'; $params[] = $contact;
                $fields[] = 'email = ?'; $params[] = $email;
                $fields[] = 'role = ?'; $params[] = $role;

                if ($password !== '') {
                    if (strlen($password) < 6) {
                        sendResponse(['error' => 'Password must be at least 6 characters'], 400);
                    }
                    $fields[] = 'password = ?';
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }

                $fields[] = 'updated_at = CURRENT_TIMESTAMP';

                $params[] = $id;
                $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                sendResponse(['success' => true, 'message' => 'User updated successfully']);
                break;

            case 'delete_user':
                if (!isAdmin()) {
                    sendResponse(['error' => 'Admin access required'], 403);
                }

                $id = (int)($input['id'] ?? 0);
                if ($id <= 0) {
                    sendResponse(['error' => 'User ID required'], 400);
                }

                // Do not allow deleting self just in case.
                if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === $id) {
                    sendResponse(['error' => 'You cannot delete your own account'], 400);
                }

                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);

                if ($stmt->rowCount() === 0) {
                    sendResponse(['error' => 'User not found'], 404);
                }

                sendResponse(['success' => true, 'message' => 'User deleted successfully']);
                break;

            case 'get_event':
                $eventId = $input['id'] ?? '';
                if (!$eventId) sendResponse(['error' => 'Event ID required'], 400);

                $stmt = $pdo->prepare("SELECT e.*, u.name as user_name FROM events e LEFT JOIN users u ON e.user_id = u.id WHERE e.id = ?");
                $stmt->execute([$eventId]);
                $event = $stmt->fetch(PDO::FETCH_ASSOC);
                sendResponse($event ? ['success' => true, 'event' => $event] : ['error' => 'Event not found']);
                break;

                
            case 'create_event':
                $title = sanitizeInput($input['title'] ?? '');
                $date = $input['date'] ?? '';
                $location = sanitizeInput($input['location'] ?? '');
                $description = sanitizeInput($input['description'] ?? '');
                $budget = (float)($input['budget'] ?? 0);
                $attendees = (int)($input['attendees'] ?? 0);
                
                if (!$title || !$date || !isValidDate($date)) {
                    sendResponse(['error' => 'Title and date required'], 400);
                }
                
                $stmt = $pdo->prepare("INSERT INTO events (user_id, title, date, location, description, budget, attendees, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'planned')");
                $stmt->execute([$_SESSION['admin_id'] ?? 1, $title, $date, $location, $description, $budget, $attendees]);
                $eventId = $pdo->lastInsertId();
                sendResponse(['success' => true, 'event_id' => $eventId]);
                break;
                
            case 'update_event':
                $id = $input['id'] ?? '';
                if (!$id) sendResponse(['error' => 'Event ID required'], 400);
                
                $set = [];
                $params = [];
                foreach (['title', 'date', 'location', 'description', 'budget', 'attendees', 'status'] as $field) {
                    if (isset($input[$field])) {
                        $set[] = "$field = ?";
                        $params[] = $input[$field];
                    }
                }
                $params[] = $id;
                
                if (empty($set)) sendResponse(['error' => 'No fields to update'], 400);
                
                $stmt = $pdo->prepare("UPDATE events SET " . implode(', ', $set) . " WHERE id = ?");
                $stmt->execute($params);
                sendResponse(['success' => true]);
                break;
                
            case 'delete_event':
                $id = $input['id'] ?? '';
                if (!$id) sendResponse(['error' => 'Event ID required'], 400);
                
                $pdo->prepare("DELETE FROM expenses WHERE event_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM bookings WHERE event_id = ?")->execute([$id]);
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                $stmt->execute([$id]);
                sendResponse(['success' => true]);
                break;

            case 'list_expenses':
                // JSON fallback for older UIs.
                // If event_id is missing, try to derive from booking_id.
                $eventId = $input['event_id'] ?? '';
                $bookingId = $input['booking_id'] ?? '';

                if (!$eventId && $bookingId) {
                    $bookingIdInt = (int)$bookingId;
                    if ($bookingIdInt > 0) {
                        if (isAdmin()) {
                            $stmtB = $pdo->prepare("SELECT event_id FROM bookings WHERE id = ? LIMIT 1");
                            $stmtB->execute([$bookingIdInt]);
                        } else {
                            $stmtB = $pdo->prepare("SELECT event_id FROM bookings WHERE id = ? AND user_id = ? LIMIT 1");
                            $stmtB->execute([$bookingIdInt, $_SESSION['user_id']]);
                        }
                        $rowB = $stmtB->fetch(PDO::FETCH_ASSOC);
                        if ($rowB) $eventId = $rowB['event_id'];
                    }
                }

                // IMPORTANT: don’t hard-fail with “Event ID required”.
                // This causes admin UI to look broken even when a different handler could work.
                if (!$eventId) {
                    sendResponse(['success' => true, 'expenses' => []]);
                }

                $stmt = $pdo->prepare("SELECT * FROM expenses WHERE event_id = ? ORDER BY created_at DESC");
                $stmt->execute([$eventId]);
                $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                sendResponse(['success' => true, 'expenses' => $expenses]);
                break;


                
            case 'add_expense':
                $eventId = $input['event_id'] ?? '';
                $category = sanitizeInput($input['category'] ?? '');
                $amount = (float)($input['amount'] ?? 0);
                $expenseDate = $input['expense_date'] ?? date('Y-m-d');
                $description = sanitizeInput($input['description'] ?? '');
                
                if (!$eventId || $amount <= 0) {
                    sendResponse(['error' => 'Valid event ID and amount required'], 400);
                }

                if (!isAdmin()) {
                    if (!isUserLoggedIn()) {
                        sendResponse(['error' => 'Login required'], 403);
                    }
                    $bookingCheckStmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE event_id = ? AND user_id = ? AND status != 'cancelled'");
                    $bookingCheckStmt->execute([$eventId, $_SESSION['user_id']]);
                    if ($bookingCheckStmt->fetchColumn() == 0) {
                        sendResponse(['error' => 'Unauthorized event or booking not found'], 403);
                    }
                }
                
                $expId = uniqid('exp_');
                // Ensure created_by column exists and set created_by to current user id
                try {
                    $col = $pdo->query("SHOW COLUMNS FROM expenses LIKE 'created_by'")->fetch(PDO::FETCH_ASSOC);
                    if (!$col) {
                        $pdo->exec("ALTER TABLE expenses ADD COLUMN created_by INT NULL AFTER description");
                    }
                } catch (PDOException $e) {
                    // ignore
                }
                $createdBy = $_SESSION['user_id'] ?? null;
                $stmt = $pdo->prepare("INSERT INTO expenses (id, event_id, category, amount, expense_date, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$expId, $eventId, $category, $amount, $expenseDate, $description, $createdBy]);
                sendResponse(['success' => true, 'expense_id' => $expId]);
                break;
                
            case 'update_expense':

                $id = $input['id'] ?? '';
                if (!$id) sendResponse(['error' => 'Expense ID required'], 400);
                
                $set = [];
                $params = [];
                foreach (['category', 'amount', 'expense_date', 'description'] as $field) {
                    if (isset($input[$field])) {
                        $set[] = "$field = ?";
                        if ($field === 'amount') $params[] = (float)$input[$field];
                        else $params[] = $input[$field];
                    }
                }
                $params[] = $id;
                
                if (empty($set)) sendResponse(['error' => 'No fields to update'], 400);
                
                $stmt = $pdo->prepare("UPDATE expenses SET " . implode(', ', $set) . " WHERE id = ?");
                $stmt->execute($params);
                sendResponse(['success' => true]);
                break;

                
case 'delete_expense':
                $id = $input['id'] ?? '';
                if (!$id) sendResponse(['error' => 'Expense ID required'], 400);

                if (!isUserLoggedIn()) sendResponse(['error' => 'Login required'], 403);

                // Ensure created_by exists
                try {
                    $col = $pdo->query("SHOW COLUMNS FROM expenses LIKE 'created_by'")->fetch(PDO::FETCH_ASSOC);
                    if (!$col) {
                        sendResponse(['error' => 'Deletion not permitted'], 403);
                    }
                } catch (PDOException $e) {
                    sendResponse(['error' => 'Server error'], 500);
                }

                $stmt = $pdo->prepare("SELECT created_by FROM expenses WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) sendResponse(['error' => 'Expense not found'], 404);

                if ((int)$row['created_by'] !== (int)($_SESSION['user_id'] ?? 0) && !isAdmin()) {
                    sendResponse(['error' => 'Not authorized to delete this expense'], 403);
                }

                $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ?");
                $stmt->execute([$id]);
                sendResponse(['success' => true]);
                break;

            case 'update_booking_status':
                if (!isAdmin()) {
                    sendResponse(['error' => 'Admin access required'], 403);
                }

                $bookingId = $input['id'] ?? null;
                $newStatus = $input['status'] ?? '';

                $bookingId = (int)$bookingId;
                if ($bookingId <= 0) {
                    sendResponse(['error' => 'Booking id is required'], 400);
                }

                if (!in_array($newStatus, ['confirmed', 'cancelled'], true)) {
                    sendResponse(['error' => 'Invalid status'], 400);
                }

                $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                $stmt->execute([$newStatus, $bookingId]);

                if ($stmt->rowCount() === 0) {
                    sendResponse(['error' => 'Booking not found'], 404);
                }

                sendResponse(['success' => true, 'message' => 'Booking status updated']);
                break;
        }
    }


    
    // PUT - Update event (admin only)
    elseif ($method === 'PUT') {
        if (!isAdmin()) {
            sendResponse(['error' => 'Admin access required'], 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(['error' => 'Invalid JSON'], 400);
        }
        
        $id = $input['id'] ?? '';
        if (!$id) {
            sendResponse(['error' => 'Event ID is required'], 400);
        }
        
        $fields = [];
        $values = [];
        
        if (!empty($input['title'])) {
            $fields[] = 'title = ?';
            $values[] = sanitizeInput($input['title']);
        }
        if (!empty($input['date']) && isValidDate($input['date'])) {
            $fields[] = 'date = ?';
            $values[] = $input['date'];
        }
        if (isset($input['location'])) {
            $fields[] = 'location = ?';
            $values[] = sanitizeInput($input['location']);
        }
        if (isset($input['description'])) {
            $fields[] = 'description = ?';
            $values[] = sanitizeInput($input['description']);
        }
        if (isset($input['budget'])) {
            $fields[] = 'budget = ?';
            $values[] = (float)$input['budget'];
        }
        if (isset($input['max_attendees'])) {
            $fields[] = 'max_attendees = ?';
            $values[] = (int)$input['max_attendees'];
        }
if (isset($input['image'])) {
            $fields[] = 'image = ?';
            $values[] = $input['image'];
        }
        if (isset($input['status'])) {
            $fields[] = 'status = ?';
            $values[] = in_array($input['status'], ['planned', 'ongoing', 'completed', 'cancelled']) ? $input['status'] : 'planned';
        }
        
        if (empty($fields)) {
            sendResponse(['error' => 'No fields to update'], 400);
        }
        
        $values[] = $id;
        $sql = "UPDATE events SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            sendResponse(['success' => true, 'message' => 'Event updated successfully']);
        } catch (PDOException $e) {
            sendResponse(['error' => 'Failed to update event'], 500);
        }
    }
    
    // DELETE - Delete event (admin only)
    elseif ($method === 'DELETE') {
        if (!isAdmin()) {
            sendResponse(['error' => 'Admin access required'], 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(['error' => 'Invalid JSON'], 400);
        }
        
        $id = $input['id'] ?? '';
        if (!$id) {
            sendResponse(['error' => 'Event ID is required'], 400);
        }
        
        try {
            $pdo->beginTransaction();
            
            // Delete related expenses first (if table exists)
            $pdo->prepare("DELETE FROM expenses WHERE event_id = ?")->execute([$id]);
            
            // Delete bookings for this event
            $pdo->prepare("DELETE FROM bookings WHERE event_id = ?")->execute([$id]);
            
            // Delete event
            $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$id]);
            
            $pdo->commit();
            sendResponse(['success' => true, 'message' => 'Event deleted successfully']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            sendResponse(['error' => 'Failed to delete event'], 500);
        }
    }
}

// ============ BOOKING API ============
elseif ($type === 'book') {
    // POST - Create booking
    if ($method === 'POST') {
        if (!isUserLoggedIn()) {
            sendResponse(['error' => 'Please login to book'], 401);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(['error' => 'Invalid JSON'], 400);
        }
        
        $eventId = $input['event_id'] ?? '';
        $guestCount = (int)($input['guest_count'] ?? 1);
        
        if (!$eventId || $guestCount < 1) {
            sendResponse(['error' => 'Valid event ID and guest count are required'], 400);
        }
        
        try {
            // Get event details
            $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status != 'cancelled'");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$event) {
                sendResponse(['error' => 'Event not found or cancelled'], 404);
            }
            
            // Check if already booked
            $stmt = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ? AND event_id = ? AND status != 'cancelled'");
            $stmt->execute([$_SESSION['user_id'], $eventId]);
            if ($stmt->fetch()) {
                sendResponse(['error' => 'Already booked this event'], 400);
            }
            
            // Create booking
            $stmt = $pdo->prepare("INSERT INTO bookings (user_id, event_id, event_title, event_date, event_location, guest_count, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([
                $_SESSION['user_id'],
                $eventId,
                $event['title'],
                $event['date'],
                $event['location'] ?? '',
                $guestCount
            ]);
            
            sendResponse(['success' => true, 'message' => 'Booking created successfully']);
        } catch (PDOException $e) {
            sendResponse(['error' => 'Failed to create booking'], 500);
        }
    }
}

// ============ BOOKINGS API ============
elseif ($type === 'bookings') {
    // GET - Fetch user bookings
    if ($method === 'GET') {
        if (!isUserLoggedIn()) {
            sendResponse(['error' => 'Please login'], 401);
        }
        
        try {
            $stmt = $pdo->prepare("SELECT b.*, e.title as event_title FROM bookings b LEFT JOIN events e ON b.event_id = e.id WHERE b.user_id = ? ORDER BY b.booking_date DESC");
            $stmt->execute([$_SESSION['user_id']]);
            $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($bookings as &$booking) {
                $booking['event_date'] = date('F j, Y', strtotime($booking['event_date']));
                $booking['booking_date'] = date('F j, Y g:i A', strtotime($booking['booking_date']));
            }
            
            sendResponse(['success' => true, 'bookings' => $bookings, 'count' => count($bookings)]);
        } catch (PDOException $e) {
            sendResponse(['error' => 'Failed to fetch bookings'], 500);
        }
    }
    
    // DELETE - Cancel booking
    elseif ($method === 'DELETE') {
        if (!isUserLoggedIn()) {
            sendResponse(['error' => 'Please login'], 401);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(['error' => 'Invalid JSON'], 400);
        }
        
        $bookingId = $input['booking_id'] ?? '';
        if (!$bookingId) {
            sendResponse(['error' => 'Booking ID is required'], 400);
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
            $result = $stmt->execute([$bookingId, $_SESSION['user_id']]);
            
            if ($stmt->rowCount() === 0) {
                sendResponse(['error' => 'Booking not found'], 404);
            } else {
                sendResponse(['success' => true, 'message' => 'Booking cancelled successfully']);
            }
        } catch (PDOException $e) {
            sendResponse(['error' => 'Failed to cancel booking'], 500);
        }
    }
}

// ============ EXPENSES API ============
elseif ($type === 'expenses') {
    if (!isUserLoggedIn()) {
        // Admin can still view expenses even if not logged in as normal user (but guard should already apply)
        if (!isAdmin()) {
            sendResponse(['error' => 'Please login'], 401);
        }
    }

    if ($method === 'GET') {
        $eventId = $_GET['event_id'] ?? '';
        $bookingId = $_GET['booking_id'] ?? '';

        // Allow viewing expenses by booking_id (admin UI only has event_id today, but this prevents “Event ID required” errors)
        if (!$eventId && $bookingId) {
            $stmtB = $pdo->prepare("SELECT event_id FROM bookings WHERE id = ? LIMIT 1");
            $stmtB->execute([(int)$bookingId]);
            $rowB = $stmtB->fetch(PDO::FETCH_ASSOC);
            if ($rowB) {
                $eventId = $rowB['event_id'];
            }
        }

        if (!$eventId) {
            sendResponse(['error' => 'Event ID is required'], 400);
        }


        try {
            $userId = $_SESSION['user_id'];
            $stmt = $pdo->prepare(
                "SELECT e.id FROM events e
                 LEFT JOIN bookings b ON b.event_id = e.id AND b.user_id = ? AND b.status != 'cancelled'
                 WHERE e.id = ? AND (e.user_id = ? OR b.id IS NOT NULL)"
            );
            $stmt->execute([$userId, $eventId, $userId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                sendResponse(['error' => 'Event not found or not accessible'], 404);
            }

            $stmt = $pdo->prepare("SELECT id, category, amount, expense_date, description, created_at, created_by FROM expenses WHERE event_id = ? ORDER BY expense_date DESC, created_at DESC");
            $stmt->execute([$eventId]);
            $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($expenses as &$expense) {
                $expense['amount'] = (float)$expense['amount'];
                $expense['expense_date'] = date('F j, Y', strtotime($expense['expense_date']));
                $expense['created_by'] = isset($expense['created_by']) ? (int)$expense['created_by'] : null;
            }

            sendResponse(['success' => true, 'expenses' => $expenses]);
        } catch (PDOException $e) {
            sendResponse(['error' => 'Failed to fetch expenses'], 500);
        }
    }
}

// ============ CHAT API ============
elseif ($type === 'chat') {
    // POST - Send message
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(['error' => 'Invalid JSON'], 400);
        }
        
        $message = sanitizeInput($input['message'] ?? '');
        if (!$message || strlen($message) > 1000) {
            sendResponse(['error' => 'Valid message (max 1000 chars) is required'], 400);
        }
        
        $userId = isUserLoggedIn() ? $_SESSION['user_id'] : null;
        $senderType = isAdmin() ? 'admin' : (isUserLoggedIn() ? 'user' : 'guest');
        $senderId = $userId ?? 'guest_' . uniqid();
        
        try {
            $stmt = $pdo->prepare("INSERT INTO messages (user_id, message, sender_type, sender_id, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$userId, $message, $senderType, $senderId]);
            sendResponse(['success' => true, 'message' => 'Message sent successfully']);
        } catch (PDOException $e) {
            sendResponse(['error' => 'Failed to send message'], 500);
        }
    }
    
    // GET - Fetch messages
    elseif ($method === 'GET') {
        try {
            if (isAdmin()) {
                // Admin sees all messages
                $stmt = $pdo->query("SELECT m.*, u.name as user_name, u.email as user_email FROM messages m LEFT JOIN users u ON m.user_id = u.id ORDER BY m.created_at DESC LIMIT 50");
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } elseif (isUserLoggedIn()) {
                // User sees their own messages + admin replies
                $stmt = $pdo->prepare("SELECT m.*, u.name as user_name FROM messages m LEFT JOIN users u ON m.user_id = u.id WHERE m.user_id = ? OR m.sender_type = 'admin' ORDER BY m.created_at DESC LIMIT 50");
                $stmt->execute([$_SESSION['user_id']]);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                sendResponse(['error' => 'Please login'], 401);
            }
            
            foreach ($messages as &$msg) {
                $msg['created_at'] = date('F j, Y g:i A', strtotime($msg['created_at']));
            }
            
            sendResponse(['success' => true, 'messages' => $messages, 'count' => count($messages)]);
        } catch (PDOException $e) {
            sendResponse(['error' => 'Failed to fetch messages'], 500);
        }
    }
}

// Default: Invalid endpoint
// include method/type/action for debugging
sendResponse([
    'error' => 'Invalid API endpoint or method',
    'debug' => [
        'type' => $type,
        'method' => $method,
        'action' => $input['action'] ?? null
    ]
], 404);
?>
