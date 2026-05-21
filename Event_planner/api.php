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
 * 
 * - POST /api.php?type=chat - Send message
 * - GET /api.php?type=chat - Get messages
 */

require_once '../config/config.php';

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

// Helper function to send JSON response
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Helper to check if user is admin
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
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
    
    // POST - Create event (admin only)
    elseif ($method === 'POST') {
        if (!isAdmin()) {
            sendResponse(['error' => 'Admin access required'], 403);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            sendResponse(['error' => 'Invalid JSON'], 400);
        }
        
        $title = sanitizeInput($input['title'] ?? '');
        $date = $input['date'] ?? '';
        $location = sanitizeInput($input['location'] ?? '');
        $description = sanitizeInput($input['description'] ?? '');
        $budget = (float)($input['budget'] ?? 0);
        $maxAttendees = (int)($input['max_attendees'] ?? 200);
        $image = $input['image'] ?? null;
        
        if (!$title || !$date || !isValidDate($date)) {
            sendResponse(['error' => 'Title and valid date (YYYY-MM-DD) are required'], 400);
        }
        
        $id = 'evt_' . uniqid();
        $stmt = $pdo->prepare("INSERT INTO events (id, user_id, title, date, location, description, budget, max_attendees, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'planned')");
        
        try {
            $stmt->execute([$id, $_SESSION['user_id'], $title, $date, $location, $description, $budget, $maxAttendees, $image]);
            sendResponse(['success' => true, 'event_id' => $id, 'message' => 'Event created successfully']);
        } catch (PDOException $e) {
            sendResponse(['error' => 'Failed to create event'], 500);
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
    if ($method === 'GET') {
        if (!isUserLoggedIn()) {
            sendResponse(['error' => 'Please login'], 401);
        }

        $eventId = $_GET['event_id'] ?? '';
        if (!$eventId) {
            sendResponse(['error' => 'Event ID is required'], 400);
        }

        $userId = $_SESSION['user_id'];
        $isAdminUser = isAdmin();

        try {
            if ($isAdminUser) {
                $stmt = $pdo->prepare("SELECT id FROM events WHERE id = ?");
                $stmt->execute([$eventId]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT e.id FROM events e
                     LEFT JOIN bookings b ON b.event_id = e.id AND b.user_id = ? AND b.status != 'cancelled'
                     WHERE e.id = ? AND (e.user_id = ? OR b.id IS NOT NULL)"
                );
                $stmt->execute([$userId, $eventId, $userId]);
            }
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                sendResponse(['error' => 'Event not found or not accessible'], 404);
            }

            $stmt = $pdo->prepare("SELECT id, category, amount, expense_date, description, created_at FROM expenses WHERE event_id = ? ORDER BY expense_date DESC, created_at DESC");
            $stmt->execute([$eventId]);
            $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($expenses as &$expense) {
                $expense['amount'] = (float)$expense['amount'];
                $expense['expense_date'] = date('F j, Y', strtotime($expense['expense_date']));
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
sendResponse(['error' => 'Invalid API endpoint or method'], 404);
?>