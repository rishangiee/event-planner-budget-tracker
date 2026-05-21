<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'eventify';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
}

if (!function_exists('requireLogin')) {
    function requireLogin() {
        if (!isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
}

if (!function_exists('requireAdmin')) {
    function requireAdmin() {
        requireLogin();
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            header('Location: dashboard.php');
            exit;
        }
    }
}

if (!function_exists('requireUser')) {
    function requireUser() {
        requireLogin();
        if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin') {
            header('Location: admin_dashboard.php');
            exit;
        }
    }
}

if (!function_exists('hashPassword')) {
    function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
}

// Session helpers for pending booking flow
if (!function_exists('setPendingBooking')) {
    function setPendingBooking($eventId, $guestCount = 1) {
        $_SESSION['pending_booking'] = [
            'event_id' => $eventId,
            'guest_count' => $guestCount,
            'created_at' => time()
        ];
    }
}

if (!function_exists('getPendingBooking')) {
    function getPendingBooking() {
        if (isset($_SESSION['pending_booking'])) {
            // Clear if older than 30 minutes
            if (time() - $_SESSION['pending_booking']['created_at'] > 1800) {
                unset($_SESSION['pending_booking']);
                return null;
            }
            return $_SESSION['pending_booking'];
        }
        return null;
    }
}

if (!function_exists('clearPendingBooking')) {
    function clearPendingBooking() {
        unset($_SESSION['pending_booking']);
    }
}
?>
