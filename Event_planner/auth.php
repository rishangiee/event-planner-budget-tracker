<?php
/**
 * Cavendia — Centralized Authentication Guard
 * Include this at the top of any page that requires authentication.
 *
 * Usage:
 *   require_once 'auth.php';        // Basic login required
 *   require_once 'auth.php'; guardUser();   // Only regular users
 *   require_once 'auth.php'; guardAdmin();  // Only admins
 */

// Prevent direct access to this file
if (basename($_SERVER['PHP_SELF']) === 'auth.php') {
    header('HTTP/1.1 403 Forbidden');
    exit('Direct access not allowed.');
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/config/config.php';

/**
 * Check if any user is logged in
 */
if (!function_exists('isLoggedIn')) {
    function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
}

/**
 * Check if current user is an admin
 */
if (!function_exists('isAdmin')) {
    function isAdmin(): bool {
        return isLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }
}

/**
 * Check if current user is a regular user (non-admin)
 */
if (!function_exists('isUser')) {
    function isUser(): bool {
        return isLoggedIn() && (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin');
    }
}

/**
 * Require basic login — redirects to login page if not authenticated
 */
if (!function_exists('guardAuth')) {
    function guardAuth(): void {
        if (!isLoggedIn()) {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            header('Location: login.php');
            exit;
        }
    }
}

/**
 * Require regular user access — redirects admins to admin dashboard
 */
if (!function_exists('guardUser')) {
    function guardUser(): void {
        guardAuth();
        if (isAdmin()) {
            header('Location: admin_dashboard.php');
            exit;
        }
    }
}

/**
 * Require admin access — redirects non-admins to user dashboard
 */
if (!function_exists('guardAdmin')) {
    function guardAdmin(): void {
        guardAuth();
        if (!isAdmin()) {
            header('Location: dashboard.php');
            exit;
        }
    }
}

/**
 * Require guest access only — redirects logged-in users away
 * Useful for login/register pages
 */
if (!function_exists('guardGuest')) {
    function guardGuest(): void {
        if (isLoggedIn()) {
            if (isAdmin()) {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        }
    }
}

/**
 * Get current user data from database
 */
if (!function_exists('getCurrentUser')) {
    function getCurrentUser(): ?array {
        global $pdo;
        if (!isLoggedIn()) return null;

        $stmt = $pdo->prepare("SELECT id, email, name, firstname, lastname, middlename, address, contact_number, role, photo, created_at FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }
}

/**
 * Refresh session data from database
 */
if (!function_exists('refreshSession')) {
    function refreshSession(): void {
        $user = getCurrentUser();
        if ($user) {
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
        }
    }
}

/**
 * Logout helper — clears session and redirects
 */
if (!function_exists('logout')) {
    function logout(): void {
        $_SESSION = [];
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
        session_destroy();
        if (isAdmin()) {
            header('Location: admin_login.php');
        } else {
            header('Location: login.php');
        }
        exit;
    }
}

/**
 * CSRF Token helpers for form security
 */
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCsrfToken')) {
    function validateCsrfToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Auto-generate CSRF token for forms
generateCsrfToken();