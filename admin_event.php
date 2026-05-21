<?php
/**
 * Admin Event Management - Upgraded Modals & API Integration
 */
require_once 'config/config.php';
require_once 'auth.php';

guardAdmin();

$userName = $_SESSION['user_name'] ?? 'Admin';
$userId = $_SESSION['user_id'] ?? 0; // Use session user_id for created events

$message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

// Helper function (from API)
if (!function_exists('isValidDate')) {
    function isValidDate($date): bool {
        return strtotime($date) !== false && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
    }
}

// CSRF Token
$csrfToken = generateCsrfToken();


// --------------------
// Admin actions (Traditional POST + AJAX handling)
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_message'] = 'Security token mismatch. Please try again.';
        header('Location: admin_event.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'create_event') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['date'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $budget = (float) ($_POST['budget'] ?? 0);
        $category = $_POST['category'] ?? 'general';
        $status = $_POST['status'] ?? 'planned';
        $maxParticipants = (int) ($_POST['max_participants'] ?? 200);
        // Make event public by default; no is_public checkbox in this admin UI
        $isPublic = 1;

        // Validation
        if (!$title || !$date || $maxParticipants < 1 || $budget < 0) {
            $_SESSION['flash_message'] = 'Title, date, budget, and max participants are required.';
            header('Location: admin_event.php');
            exit;
        }

        if (!isValidDate($date)) {
            $_SESSION['flash_message'] = 'Invalid date format.';
            header('Location: admin_event.php');
            exit;
        }

        try {
            $eventId = uniqid('evt_');

            // Support legacy DB schema (no is_public / category / max_participants columns yet)
            // and map to the available columns in setup.sql.
            $hasCategory = true;
            $hasMaxParticipants = true;
            $hasIsPublic = true;

            // Detect columns from DB (cheap, executed once per request)
            static $columnCache = null;
            if ($columnCache === null) {
                $columnCache = [];
                $cols = $pdo->query("DESCRIBE events")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) {
                    $columnCache[$c['Field']] = true;
                }
            }

            $hasCategory = isset($columnCache['category']);
            $hasMaxParticipants = isset($columnCache['max_participants']);
            $hasIsPublic = isset($columnCache['is_public']);
            $hasMaxAttendees = isset($columnCache['max_attendees']);

            $insertFields = ['id', 'user_id', 'title', 'description', 'date', 'location', 'budget', 'attendees', 'max_attendees', 'status'];
            $insertPlaceholders = ['?', '?', '?', '?', '?', '?', '?', '?', '?', '?'];
            $values = [
                $eventId,
                $userId,
                $title,
                $description,
                $date,
                $location,
                $budget,
                0, // attendees
                $maxParticipants,
                $status
            ];

            if ($hasCategory) {
                $insertFields[] = 'category';
                $insertPlaceholders[] = '?';
                $values[] = $category;
            }

            if ($hasMaxParticipants) {
                $insertFields[] = 'max_participants';
                $insertPlaceholders[] = '?';
                $values[] = $maxParticipants;
            }

            if ($hasIsPublic) {
                $insertFields[] = 'is_public';
                $insertPlaceholders[] = '?';
                $values[] = $isPublic;
            }

            // If max_attendees doesn't exist, fall back to max_participants
            if (!$hasMaxAttendees && $hasMaxParticipants) {
                // remove max_attendees from insertFields
                $idx = array_search('max_attendees', $insertFields, true);
                if ($idx !== false) {
                    array_splice($insertFields, $idx, 1);
                    array_splice($insertPlaceholders, $idx, 1);
                    array_splice($values, $idx, 1);
                }
            }

            $sql = "INSERT INTO events (" . implode(', ', $insertFields) . ") VALUES (" . implode(', ', $insertPlaceholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);


            $_SESSION['flash_message'] = 'Event created successfully!';
            
            // Check for AJAX request
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true, 
                    'event_id' => $eventId,
                    'message' => 'Event created successfully!',
                    'event' => [
                        'id' => $eventId,
                        'title' => $title,
                        'date' => date('M j, Y', strtotime($date)),
                        'location' => $location,
                        'category' => $category,
                        'max_participants' => $maxParticipants,
                        'is_public' => $isPublic ? 'Yes' : 'No'
                    ]
                ]);
                exit;
            }
            
            header('Location: admin_event.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = 'Database error: ' . $e->getMessage();
            header('Location: admin_event.php');
            exit;
        }

    } elseif ($action === 'edit_event') {
        $eventId = $_POST['event_id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['date'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $budget = (float) ($_POST['budget'] ?? 0);
        $status = $_POST['status'] ?? 'planned';
        $category = $_POST['category'] ?? 'general';
        $maxParticipants = (int) ($_POST['max_participants'] ?? 200);
        $isPublic = isset($_POST['is_public']) ? 1 : 0;

        if (!$eventId || !$title || !$date || $budget < 0) {
            $_SESSION['flash_message'] = 'Event ID, title, date, and budget are required.';
            header('Location: admin_event.php');
            exit;
        }

        if (!isValidDate($date)) {
            $_SESSION['flash_message'] = 'Invalid date format.';
            header('Location: admin_event.php');
            exit;
        }

        try {
            // Update only columns that exist in the current DB schema
            static $columnCacheUpdate = null;
            if ($columnCacheUpdate === null) {
                $columnCacheUpdate = [];
                $cols = $pdo->query("DESCRIBE events")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($cols as $c) {
                    $columnCacheUpdate[$c['Field']] = true;
                }
            }

            $setParts = [];
            $params = [];

            $setParts[] = 'title = ?';
            $params[] = $title;

            if (isset($columnCacheUpdate['description'])) {
                $setParts[] = 'description = ?';
                $params[] = $description;
            }

            $setParts[] = 'date = ?';
            $params[] = $date;

            if (isset($columnCacheUpdate['location'])) {
                $setParts[] = 'location = ?';
                $params[] = $location;
            }

            if (isset($columnCacheUpdate['category'])) {
                $setParts[] = 'category = ?';
                $params[] = $category;
            }

            if (isset($columnCacheUpdate['status'])) {
                $setParts[] = 'status = ?';
                $params[] = $status;
            }

            if (isset($columnCacheUpdate['budget'])) {
                $setParts[] = 'budget = ?';
                $params[] = $budget;
            }

            // max_participants / max_attendees mapping
            if (isset($columnCacheUpdate['max_participants'])) {
                $setParts[] = 'max_participants = ?';
                $params[] = $maxParticipants;
            }
            if (isset($columnCacheUpdate['max_attendees'])) {
                $setParts[] = 'max_attendees = ?';
                $params[] = $maxParticipants;
            }

            if (isset($columnCacheUpdate['is_public'])) {
                $setParts[] = 'is_public = ?';
                $params[] = $isPublic;
            }

            if (empty($setParts)) {
                throw new PDOException('No updatable columns found.');
            }

            $sql = "UPDATE events SET " . implode(', ', $setParts) . " WHERE id = ?";
            $params[] = $eventId;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);


            $_SESSION['flash_message'] = 'Event updated successfully!';
            
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Event updated successfully!']);
                exit;
            }
            
            header('Location: admin_event.php');
            exit;
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = 'Database error: ' . $e->getMessage();
            header('Location: admin_event.php');
            exit;
        }

    } elseif ($action === 'delete_event') {
        $eventId = $_POST['event_id'] ?? '';
        if ($eventId) {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("DELETE FROM expenses WHERE event_id = ?")->execute([$eventId]);
                $pdo->prepare("DELETE FROM bookings WHERE event_id = ?")->execute([$eventId]);
                $pdo->prepare("DELETE FROM events WHERE id = ?")->execute([$eventId]);
                $pdo->commit();

                $_SESSION['flash_message'] = 'Event deleted successfully!';
                
                if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'message' => 'Event deleted successfully!']);
                    exit;
                }
                
                header('Location: admin_event.php');
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['flash_message'] = 'Delete failed: ' . $e->getMessage();
                header('Location: admin_event.php');
                exit;
            }
        }
    }
}

// --------------------
// Data load
// --------------------
$search = trim($_GET['search'] ?? '');
$recentEventsQuery = "
    SELECT 
        e.*, 
        u.name as user_name,
        COALESCE(SUM(ex.amount), 0) as total_spent
    FROM events e 
    LEFT JOIN users u ON e.user_id = u.id
    LEFT JOIN expenses ex ON e.id = ex.event_id
";
$params = [];
if ($search) {
    $recentEventsQuery .= " WHERE e.title LIKE ? OR e.date LIKE ? OR e.location LIKE ?";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$recentEventsQuery .= " GROUP BY e.id ORDER BY e.created_at DESC LIMIT 20";
$stmt = $pdo->prepare($recentEventsQuery);
$stmt->execute($params);
$recentEvents = $stmt->fetchAll();

$stmt = $pdo->query("SELECT b.*, u.name AS user_name, e.title AS event_title, e.date AS event_date, e.budget AS event_budget
    FROM bookings b
    LEFT JOIN users u ON b.user_id = u.id
    LEFT JOIN events e ON b.event_id = e.id
    WHERE b.status != 'cancelled'
    ORDER BY b.booking_date DESC");
$bookings = $stmt->fetchAll();

// Category options for dropdown
$categories = ['general', 'wedding', 'corporate', 'birthday', 'conference', 'seminar', 'party', 'other'];

// Admin calendar filter state
$today = date('Y-m-d');
$currentMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$currentYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
if ($currentMonth < 1) { $currentMonth = 12; $currentYear--; }
if ($currentMonth > 12) { $currentMonth = 1; $currentYear++; }
$monthName = date('F Y', mktime(0, 0, 0, $currentMonth, 1, $currentYear));
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
$firstDayOfWeek = (int)date('w', mktime(0, 0, 0, $currentMonth, 1, $currentYear));

function getAdminEventColor($status, $date, $today) {
    if ($status === 'completed' || $date < $today) return '#E8E8E8';
    if ($status === 'ongoing' || $date === $today) return '#DFF2F2';
    return '#CFE1D1';
}

$calendarEvents = [];
$allEventsStmt = $pdo->query("SELECT e.*, u.name AS user_name FROM events e LEFT JOIN users u ON e.user_id = u.id ORDER BY e.date ASC");
$allEvents = $allEventsStmt->fetchAll();
foreach ($allEvents as $evt) {
    $eventDate = $evt['date'] ?? $evt['event_date'] ?? '';
    if (!$eventDate) {
        continue;
    }
    $calendarEvents[] = [
        'id' => $evt['id'],
        'title' => $evt['title'] ?? 'Untitled Event',
        'date' => $eventDate,
        'location' => $evt['location'] ?? '',
        'status' => $evt['status'] ?? 'planned',
        'color' => getAdminEventColor($evt['status'] ?? 'planned', $eventDate, $today),
        'user_name' => $evt['user_name'] ?? 'Admin'
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
$searchParam = $search ? '&search=' . urlencode($search) : '';
$prevLink = "?month=$prevMonth&year=$prevYear$searchParam";
$nextLink = "?month=$nextMonth&year=$nextYear$searchParam";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Events - CAVENDIA</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --sage:#A3B18A;
            --sage-dark:#8A9A6D;
            --cream:#F1F2EE;
            --forest:#1B4332;
            --white:#FFFFFF;
            --text-muted:#6B7C6D;
            --border:#D8DDD3;
            --shadow:0 8px 32px rgba(27,67,50,0.12);
            --shadow-hover:0 20px 40px rgba(27,67,50,0.2);
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:linear-gradient(135deg,var(--cream) 0%, #e8ebe3 100%);color:var(--forest);line-height:1.6;}

        .sidebar{position:fixed;top:0;left:0;width:280px;height:100vh;background:var(--sage);z-index:100;transition:transform .3s ease;padding-top:5rem;overflow-y:auto;}
        .sidebar-header{padding:2rem 2rem 1.5rem;border-bottom:1px solid rgba(255,255,255,0.1);margin-bottom:1.5rem;}
        .sidebar-title{display:flex;align-items:center;gap:.75rem;margin-bottom:.25rem;}
        .sidebar-icon{width:2.5rem;height:2.5rem;background:rgba(255,255,255,0.2);border-radius:.75rem;display:flex;align-items:center;justify-content:center;}
        .sidebar-title h2{font-size:1.5rem;font-weight:700;color:var(--white);} 
        .sidebar-subtitle{font-size:.875rem;color:rgba(255,255,255,0.8);font-weight:400;}
        .sidebar-nav{padding:0 2rem 2rem;}
        .sidebar-item{display:flex;align-items:center;gap:1rem;padding:.875rem 1rem;color:rgba(255,255,255,0.8);text-decoration:none;border-radius:1rem;margin-bottom:.5rem;font-weight:500;transition:all .2s ease;font-size:.95rem;}
        .sidebar-item:hover{background:rgba(255,255,255,0.15);color:var(--white);transform:translateX(.25rem);} 
        .sidebar-item.active{background:rgba(255,255,255,0.25);color:var(--white);border-left:4px solid var(--white);font-weight:600;}
        .sidebar-item i{width:1.25rem;font-size:1.1rem;}

        .mobile-toggle{position:fixed;top:1.5rem;left:1.5rem;z-index:200;background:var(--sage);border:none;padding:1rem;border-radius:1rem;color:var(--white);font-size:1.25rem;cursor:pointer;box-shadow:var(--shadow);transition:all .3s ease;display:none;}
        .mobile-toggle:hover{background:var(--forest);transform:scale(1.05);} 
        .overlay{position:fixed;inset:0;background:rgba(27,67,50,0.45);backdrop-filter:none;z-index:150;opacity:0;visibility:hidden;transition:all .3s ease;}

        .overlay.active{opacity:1;visibility:visible;}

        /* FIXED: Reduced top padding to move content to top */
        .main-content{margin-left:280px;transition:margin-left .3s ease;padding:3rem 2rem 2rem;min-height:100vh;}
        .main-content{padding-bottom:130px;}

        .header{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1rem;flex-wrap:wrap;}
        .header-actions{display:flex;gap:1rem;flex-wrap:wrap;}

        .card{background:var(--white);backdrop-filter:blur(10px);border:1px solid var(--border);box-shadow:var(--shadow);border-radius:2rem;padding:3rem;margin-bottom:2rem;}

        .btn{padding:.875rem 1.5rem;border-radius:1.5rem;font-weight:600;border:2px solid var(--border);cursor:pointer;transition:all .3s ease;background:var(--white);color:var(--forest);display:inline-flex;align-items:center;gap:.5rem;text-decoration:none;font-size:.95rem;}
        .btn:hover{border-color:var(--sage);transform:translateY(-2px);box-shadow:var(--shadow-hover);} 
        .btn-sage{background:var(--sage);color:var(--white);border-color:var(--sage-dark);} 

        .btn-text{padding:.5rem 1rem;border-radius:1rem;font-weight:500;border:1px solid var(--border);cursor:pointer;transition:all .3s ease;background:var(--white);color:var(--forest);text-decoration:none;display:inline-flex;align-items:center;gap:.25rem;font-size:.9rem;}
        .btn-text:hover{border-color:var(--sage);transform:translateY(-1px);box-shadow:var(--shadow);}
        .btn-text-edit{background:var(--cream);border-color:var(--sage);}
        .btn-text-delete{background:#fef2f2;color:#991b1b;border-color:#fca5a5;}

        .badge{padding:.5rem 1rem;border-radius:2rem;font-size:.875rem;font-weight:600;text-transform:uppercase;}
        .badge.upcoming{background:linear-gradient(135deg,#10b981,#059669);color:#fff;}

        .events-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px;}
        .event-img{height:220px;position:relative;overflow:hidden;}
        .event-badge{position:absolute;top:16px;right:16px;background:var(--sage);color:var(--white);padding:6px 12px;border-radius:20px;font-size:.7rem;font-weight:600;letter-spacing:1px;text-transform:uppercase;}

        /* Recent events cards - NOT clickable */
        .event-card{background:var(--white);border:1px solid var(--border);border-radius:24px;overflow:hidden;transition:all .35s ease;}
        .event-card:hover{box-shadow:var(--shadow-hover);transform:translateY(-4px);}

        .calendar-grid{display:grid;grid-template-columns:repeat(7, minmax(0,1fr));gap:12px;margin-top:1rem;}
        .calendar-day-header{font-size:.8rem;font-weight:700;color:var(--sage-dark);text-transform:uppercase;letter-spacing:.08em;text-align:center;}
        .calendar-day{min-height:120px;padding:10px;border-radius:1.25rem;background:var(--cream);border:1px solid var(--border);position:relative;transition:all .25s ease;}
        .calendar-day:hover{background:#f4f7f2;}
        .calendar-day.today{border-color:var(--sage);background:#eff7ea;}
        .calendar-day.empty{background:transparent;border:none;}
        .calendar-day .day-number{font-size:0.95rem;font-weight:700;color:var(--forest);display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:#f1f2ee;}
        .calendar-event-pill{display:block;padding:0.45rem 0.75rem;border-radius:9999px;font-size:.75rem;font-weight:700;color:#1f4333;margin-top:0.65rem;background:rgba(163,177,138,0.15);border:1px solid rgba(163,177,138,0.25);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .calendar-event-pill strong{font-weight:800;}
        .calendar-footer{margin-top:1.5rem;display:grid;gap:1rem;}
        .calendar-nav{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;}
        .calendar-nav a{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1rem;border-radius:1rem;border:1px solid var(--border);background:var(--white);text-decoration:none;color:var(--forest);font-weight:700;}
        .calendar-nav a:hover{background:var(--cream);}
        .event-list-item{display:flex;justify-content:space-between;gap:1rem;padding:1rem 1.25rem;border-radius:1.5rem;border:1px solid var(--border);background:#f9faf7;}

        /* Responsive */
        @media (max-width: 1024px){
            .main-content{margin-left:0;width:100%;}
        }
        @media (max-width: 768px){
            .mobile-toggle{display:block;}
            .sidebar{transform:translateX(-100%);} 
            .sidebar.open{transform:translateX(0);} 
            .main-content{margin-left:0;}
        }

        /* Enhanced Modal Styling */
        .modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px);} 
        .modal.active{display:flex;} 
        .modal-content{
            background:var(--white); 
            border-radius:2rem; 
            padding:2.5rem; 
            max-width:580px; 
            width:90%; 
            max-height:90vh; 
            overflow-y:auto; 
            box-shadow:var(--shadow-hover); 
            border:1px solid var(--border);
            position:relative;
        } 

        /* Modal Form Styling */
        .modal h3{font-size:1.5rem;font-weight:800;color:var(--forest);margin-bottom:2rem;border-bottom:2px solid var(--sage);padding-bottom:1rem;}
        .form-group{margin-bottom:1.5rem;}
        .form-label{display:block;font-weight:700;color:var(--forest);font-size:0.95rem;margin-bottom:0.5rem;}
        .form-input{width:100%;padding:1rem 1.25rem;border:2px solid var(--border);border-radius:1.25rem;font-size:1rem;font-family:inherit;transition:all 0.3s ease;background:var(--cream);color:var(--forest);}
        .form-input:focus{outline:none;border-color:var(--sage);box-shadow:0 0 0 4px rgba(163,177,138,0.15);background:var(--white);}
        .form-input::placeholder{color:var(--text-muted);opacity:0.7;}
        .form-textarea{resize:vertical;min-height:100px;}
.form-select{appearance:none;-webkit-appearance:none;-moz-appearance:none;background-image:url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right 1rem center;background-size:1.2em;}

        
        /* Checkbox Styling */
        .form-checkbox-group{display:flex;align-items:center;gap:0.75rem;margin:1.5rem 0;padding:1.25rem;background:var(--cream);border-radius:1.25rem;border:2px solid var(--border);}
        .form-checkbox{position:relative;}
        .form-checkbox input[type="checkbox"]{opacity:0;position:absolute;}
        .form-checkbox-label{display:flex;align-items:center;gap:0.75rem;font-weight:500;color:var(--forest);cursor:pointer;}
        .checkbox-custom{display:block;width:20px;height:20px;border:2px solid var(--border);border-radius:0.5rem;background:var(--white);transition:all 0.3s ease;position:relative;}
        .checkbox-custom::after{content:'';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) scale(0);width:10px;height:10px;background:var(--sage);border-radius:0.25rem;transition:all 0.3s ease;}
        .form-checkbox input[type="checkbox"]:checked + .checkbox-custom{border-color:var(--sage);background:var(--sage);}
        .form-checkbox input[type="checkbox"]:checked + .checkbox-custom::after{transform:translate(-50%,-50%) scale(1);}

        /* Modal Footer */
        .modal-footer{display:flex;justify-content:flex-end;gap:1rem;margin-top:2rem;padding-top:2rem;border-top:2px solid var(--border);}
        .btn-modal{padding:1rem 2rem;border-radius:1.5rem;font-weight:600;font-size:1rem;border:2px solid;transition:all 0.3s ease;cursor:pointer;display:inline-flex;align-items:center;gap:0.5rem;text-decoration:none;}
        .btn-modal:hover{transform:translateY(-2px);box-shadow:var(--shadow-hover);}
        .btn-cancel{background:var(--white);color:var(--forest);border-color:var(--border);}
        .btn-save{background:var(--sage);color:var(--white);border-color:var(--sage-dark);}

        input,select,textarea{font-family:'Inter',sans-serif;}

        /* Small top flash (non-AJAX page reload) */
        .top-small-flash{
            position:fixed;
            top:0;
            left:50%;
            transform:translateX(-50%) translateY(-10px);
            z-index:60;
            background:rgba(16,185,129,0.12);
            border:2px solid rgba(16,185,129,0.25);
            padding:12px 18px;
            border-radius:16px;
            color:var(--forest);
            font-size:14px;
            font-weight:500;
            display:flex;
            align-items:center;
            gap:10px;
            opacity:0;
            animation:topFlashIn 180ms ease forwards;
        }

        @keyframes topFlashIn{
            from{opacity:0; transform:translateX(-50%) translateY(-10px);}
            to{opacity:1; transform:translateX(-50%) translateY(14px);}
        }

        /* Match the icon size */
        .top-small-flash i{color:#10b981;font-size:16px;}
    </style>
</head>
<body>
<nav class="header-gradient">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:16px 24px;">
        <a href="admin_dashboard.php" style="display:flex;align-items:center;gap:12px;text-decoration:none;color:var(--white);font-weight:700;font-size:20px;">
            <div style="width:40px;height:40px;border-radius:16px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#1a472a,#2d6a4f);">
                <i class="fas fa-crown" style="color:#fff;font-size:18px;"></i>
            </div>
            <span>ADMIN</span>
        </a>
    </div>
</nav>

<button class="mobile-toggle" id="mobileToggle"><i class="fas fa-bars"></i></button>
<div class="overlay" id="overlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-title">
            <div class="sidebar-icon"><i class="fas fa-crown"></i></div>
            <h2>Admin Portal</h2>
        </div>
        <p class="sidebar-subtitle">Welcome, <?= htmlspecialchars($userName) ?></p>
    </div>
    <nav class="sidebar-nav">
        <a href="admin_dashboard.php" class="sidebar-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="admin_event.php" class="sidebar-item active"><i class="fas fa-calendar-alt"></i><span>Manage Events</span></a>
        <a href="admin_users.php" class="sidebar-item"><i class="fas fa-users"></i><span>Manage Users</span></a>
<a href="admin_bookings.php" class="sidebar-item"><i class="fas fa-clipboard-list"></i><span>Bookings</span></a>
<a href="chat.php" class="sidebar-item"><i class="fas fa-comments"></i><span>Messages</span></a>
<a href="admin_logout.php" class="sidebar-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </nav>
</aside>

<main class="main-content" id="mainContent">
    <?php if ($message): ?>
        <div id="topSmallFlash" class="top-small-flash" data-message="<?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>">
        </div>
    <?php endif; ?>

    <!-- MOVED TO TOP - Minimal spacing -->
    <div class="header" style="margin-bottom:1rem;gap:1rem;align-items:center;justify-content:space-between;">
        <div>
            <h1 class="header-title" style="color:#1B4332 !important;background:none !important;-webkit-text-fill-color:#1B4332 !important;font-size:2.25rem;margin:0.25rem 0 0.125rem 0;line-height:1.1;">
                Events Management
            </h1>
            <p style="color:var(--text-muted);font-weight:500;font-size:0.95rem;margin:0;">Manage all your events</p>
        </div>
        <div class="header-actions">
            <button id="openCreateEventBtn" class="btn btn-sage" onclick="openModalWithBlur('createModal')">
                <i class="fas fa-plus"></i> Add Event
            </button>
        </div>
    </div>

    <!-- Admin calendar -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
            <div>
                <h2 style="font-size:24px;font-weight:800;color:var(--forest);margin-bottom:.5rem;">Event Calendar</h2>
                <p style="color:var(--text-muted);font-size:.95rem;max-width:640px;">Browse all scheduled events in a calendar view and see upcoming events for the selected month.</p>
            </div>
            <div class="calendar-nav">
                <a href="<?= htmlspecialchars($prevLink) ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                <span style="font-weight:800;color:var(--forest);font-size:1rem;"><?= htmlspecialchars($monthName) ?></span>
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

        <div class="calendar-footer">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;">
                <h3 style="font-size:18px;font-weight:700;color:var(--forest);margin:0;">Upcoming events this month</h3>
                <span style="color:var(--text-muted);font-size:.95rem;">Showing events from <?= htmlspecialchars($monthName) ?></span>
            </div>
            <?php if (empty($upcomingMonthEvents)): ?>
                <div style="padding:2rem;border-radius:1.5rem;border:1px dashed var(--border);background:#fafaf7;color:var(--text-muted);text-align:center;">No upcoming events in this month.</div>
            <?php else: ?>
                <div style="display:grid;gap:14px;">
                    <?php foreach ($upcomingMonthEvents as $evt): ?>
                        <div class="event-list-item">
                            <div>
                                <div style="font-weight:700;color:var(--forest);"><?= htmlspecialchars($evt['title']) ?></div>
                                <div style="color:var(--text-muted);font-size:.92rem;"><?= date('M j, Y', strtotime($evt['date'])) ?> · <?= htmlspecialchars($evt['location'] ?: 'No location') ?></div>
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
    </div>

    <!-- Booked Events removed per request -->

    <!-- Events List -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <h2 style="font-size:24px;font-weight:800;color:var(--forest);">Recent Events (<?= count($recentEvents) ?>)</h2>
            <?php if ($search): ?><span style="color:#16a34a;font-weight:800;">Showing results for: "<?= htmlspecialchars($search) ?>"</span><?php endif; ?>
        </div>

        <div style="margin-bottom:24px;">
            <form method="GET" style="display:flex;gap:12px;max-width:720px;">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search events by title, date, or location..." style="flex:1;padding:14px 20px;font-size:18px;border:2px solid var(--border);border-radius:1.5rem;">
                <button type="submit" class="btn btn-sage" style="padding:14px 28px;white-space:nowrap;"><i class="fas fa-search"></i> Search</button>
                <?php if ($search): ?><a href="admin_event.php" class="btn" style="padding:14px 24px;">Clear</a><?php endif; ?>
            </form>
        </div>

        <div class="events-grid">
            <?php if (empty($recentEvents)): ?>
                <div style="grid-column:1/-1;text-align:center;padding:64px 32px;color:var(--text-muted);">
                    <i class="fas fa-inbox" style="font-size:64px;margin-bottom:24px;color:#d1d5db;"></i>
                    <p style="font-size:20px;margin-bottom:20px;">No events found<?= $search ? ' for "' . htmlspecialchars($search) . '"' : '' ?>.</p>
                    <button onclick="openModalWithBlur('createModal')" class="btn btn-sage" style="padding:14px 36px;font-size:18px;">Create First Event</button>
                </div>
            <?php else: ?>
                <?php foreach ($recentEvents as $event): ?>
<article class="event-card">
                        <div class="event-img">
                            <img src="photorealistic-wedding-venue-with-intricate-decor-ornaments_23-2151481464.avif" alt="Venue" style="width:100%;height:100%;object-fit:cover;">
                            <span class="event-badge"><?= ucfirst($event['status'] ?? 'planned') ?></span>
                            <?php if (isset($event['is_public']) && $event['is_public']): ?>
                                <span class="event-badge" style="background:#3b82f6;top:16px;left:16px;">Public</span>
                            <?php endif; ?>
                        </div>
                        <div style="padding:20px 28px 16px;">
                            <h3 style="font-size:20px;font-weight:600;color:var(--forest);margin-bottom:12px;line-height:1.3;">
                                <?= htmlspecialchars(substr($event['title'], 0, 60)) ?><?= strlen($event['title']) > 60 ? '...' : '' ?>
                            </h3>
                            <?php if (!empty($event['user_name'])): ?>
                                <div style="font-size:14px;color:var(--text-muted);margin-bottom:16px;opacity:.8;">by <?= htmlspecialchars($event['user_name']) ?></div>
                            <?php endif; ?>
                            <div style="display:flex;flex-direction:column;gap:8px;font-size:15px;color:var(--text-muted);margin-bottom:12px;">
                                <div style="display:flex;align-items:center;gap:8px;"><i class="far fa-calendar-alt" style="color:var(--sage);"></i><span><?= date('M j, Y', strtotime($event['date'])) ?></span></div>
                                <?php if (!empty($event['location'])): ?>
                                    <div style="display:flex;align-items:center;gap:8px;">
                                        <i class="fas fa-map-marker-alt" style="color:var(--sage);"></i>
                                        <span><?= htmlspecialchars(substr($event['location'], 0, 30)) ?><?= strlen($event['location']) > 30 ? '...' : '' ?></span>
                                    </div>
                                <?php endif; ?>
                                <div style="display:flex;align-items:center;gap:8px;"><i class="fas fa-users" style="color:var(--sage);"></i><span><?= number_format($event['attendees'] ?? 0) ?> / <?= number_format($event['max_participants'] ?? $event['max_attendees'] ?? 0) ?></span></div>

                                <!-- Event budget display removed per request -->
                            </div>

                            <div style="margin-top:12px;opacity:.9;">
                                <button type="button" onclick="openEditModal('<?= htmlspecialchars($event['id']) ?>')" class="btn-text btn-text-edit" style="margin-right:8px;">Edit</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this event and all related expenses/bookings?')">
                                    <input type="hidden" name="action" value="delete_event">
                                    <input type="hidden" name="event_id" value="<?= htmlspecialchars($event['id']) ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <button type="submit" class="btn-text btn-text-delete">Delete</button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Create Event Modal - UPGRADED -->
<div id="createModal" class="modal">
    <div class="modal-content" style="padding-bottom:60px;">
        <h3><i class="fas fa-plus-circle" style="color:var(--sage);margin-right:8px;"></i>Create New Event</h3>
        <form id="createEventForm" method="POST">
            <input type="hidden" name="action" value="create_event">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            
            <div class="form-group">
                <label class="form-label">Event Title <span style="color:#ef4444;">*</span></label>
                <input type="text" name="title" required placeholder="e.g. Annual Company Gala 2026" class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" placeholder="Enter event description..." class="form-input form-textarea"></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <div class="form-group">
                    <label class="form-label">Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="date" required class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" placeholder="e.g. Grand Ballroom, Makati" class="form-input">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <div class="form-group">
                    <label class="form-label">Budget <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="budget" min="0" step="0.01" value="0" required class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Max Participants <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="max_participants" min="1" value="200" required class="form-input">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-input form-select">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= ucwords(str_replace('_', ' ', $cat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-input form-select">
                        <option value="planned">Planned</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>



            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" onclick="closeModal('createModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-modal btn-save">
                    <i class="fas fa-save"></i> Create Event
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Event Modal - UPGRADED -->
<div id="editModal" class="modal">
    <div class="modal-content" style="padding-bottom:60px;">
        <h3><i class="fas fa-edit" style="color:var(--sage);margin-right:8px;"></i>Edit Event</h3>
        <form id="editEventForm" method="POST">
            <input type="hidden" name="action" value="edit_event">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <input type="hidden" name="event_id" id="editEventId">
            
            <div class="form-group">
                <label class="form-label">Event Title <span style="color:#ef4444;">*</span></label>
                <input type="text" name="title" id="editTitle" required class="form-input">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="editDescription" class="form-input form-textarea"></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <div class="form-group">
                    <label class="form-label">Date <span style="color:#ef4444;">*</span></label>
                    <input type="date" name="date" id="editDate" required class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" id="editLocation" class="form-input">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <div class="form-group">
                    <label class="form-label">Budget <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="budget" id="editBudget" min="0" step="0.01" value="0" required class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label">Max Participants <span style="color:#ef4444;">*</span></label>
                    <input type="number" name="max_participants" id="editMaxParticipants" min="1" required class="form-input">
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" id="editCategory" class="form-input form-select">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= ucwords(str_replace('_', ' ', $cat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="editStatus" class="form-input form-select">
                        <option value="planned">Planned</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
            </div>



            <div class="modal-footer">
                <button type="button" class="btn-modal btn-cancel" onclick="closeModal('editModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-modal btn-save">
                    <i class="fas fa-save"></i> Update Event
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Mobile sidebar
    const mobileToggle = document.getElementById('mobileToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    mobileToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    });

    overlay?.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    });

    // Modal controls
    function openModal(modalId) {
        document.getElementById(modalId)?.classList.add('active');
    }

    function closeModal(modalId) {
        document.getElementById(modalId)?.classList.remove('active');
        const main = document.getElementById('mainContent');
        main?.classList.remove('modal-open');
        main && (main.style.pointerEvents = '');
        main && (main.style.filter = '');
    }



    function openModalWithBlur(modalId) {
        const main = document.getElementById('mainContent');
        main?.classList.add('modal-open');
        main && (main.style.pointerEvents = 'none');
        openModal(modalId);
    }


    // Open Edit Modal with pre-filled data
    async function openEditModal(eventId) {
        try {
            const response = await fetch(`api.php?type=events`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ action: 'get_event', id: eventId })
            });
            const data = await response.json();
            
            if (data.success && data.event) {
                const event = data.event;
                document.getElementById('editEventId').value = event.id;
                document.getElementById('editTitle').value = event.title || '';
                document.getElementById('editDescription').value = event.description || '';
                document.getElementById('editDate').value = event.date || '';
                document.getElementById('editLocation').value = event.location || '';
                document.getElementById('editCategory').value = event.category || 'general';
                document.getElementById('editMaxParticipants').value = event.max_participants || event.max_attendees || 200;
                document.getElementById('editBudget').value = event.budget ?? 0;
                document.getElementById('editStatus').value = event.status || 'planned';
                const publicEditEl = document.getElementById('publicEdit');
                if (publicEditEl) {
                    publicEditEl.checked = event.is_public == 1;
                }
                
                openModalWithBlur('editModal');
            }
        } catch (error) {
            console.error('Failed to load event:', error);
            alert('Failed to load event data');
        }
    }

    // Form submissions with AJAX
    document.getElementById('createEventForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        // Helpful debug: read raw server response (if JSON parse fails)
        try {
            const response = await fetch('admin_event.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const text = await response.text();
            let data = null;
            try { data = JSON.parse(text); } catch (_) {}

            if (data && data.success) {
                closeModal('createModal');
                // ensure banner is visible immediately and disappears after 3s
                if (data.message) showSmallFlash(data.message, 3000);
                // refresh after banner duration
                setTimeout(() => location.reload(), 3200);
                return;
            }
            

            const msg = (data && (data.error || data.message))
                ? (data.error || data.message)
                : (text ? `Creation failed: ${text.slice(0, 300)}` : 'Creation failed');
            alert(msg);
        } catch (error) {
            alert('Network error. Please try again.');
        }
    });

    document.getElementById('editEventForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        try {
            const response = await fetch('admin_event.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const text = await response.text();
            let data = null;
            try { data = JSON.parse(text); } catch (_) {}

            if (data && data.success) {
                closeModal('editModal');
                if (data.message) showFlash(data.message, 3000);
                setTimeout(() => location.reload(), 1500);
                return;
            }

            const msg = (data && (data.error || data.message))
                ? (data.error || data.message)
                : (text ? `Update failed: ${text.slice(0, 300)}` : 'Update failed');
            alert(msg);
        } catch (error) {
            alert('Network error. Please try again.');
        }
    });

    // Flash message helper
function showSmallFlash(message, duration = 3000) {
        const flash = document.createElement('div');
        flash.className = 'top-small-flash';
        flash.innerHTML = `<i class="fas fa-check-circle" aria-hidden="true"></i><span>${message}</span>`;
        document.body.appendChild(flash);

        setTimeout(() => {
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 200);
        }, duration);
    }

    // Legacy larger toast (AJAX) kept for errors/other pages
    function showFlash(message, duration = 4000) {
        const flash = document.createElement('div');
        flash.className = 'fixed top-24 right-6 z-50';
        flash.style.cssText = `
            background:rgba(16,185,129,0.12);
            border:2px solid rgba(16,185,129,0.25);
            padding:10px 14px;
            border-radius:16px;
            color:var(--forest);
            font-weight:500;
            box-shadow:var(--shadow);
        `;
        flash.innerHTML = `<i class="fas fa-check-circle" style="color:#10b981;margin-right:10px;font-size:16px;"></i><span style="font-size:16px;font-weight:600;line-height:1.2;display:inline-block;vertical-align:middle;">${message}</span>`;
        document.body.appendChild(flash);
        
        setTimeout(() => {
            flash.remove();
        }, duration);
    }

    // Close modals on outside click
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('active');
            const main = document.getElementById('mainContent');
            main?.classList.remove('modal-open');
            main && (main.style.pointerEvents = '');
            main && (main.style.filter = '');
        }
    });

</script>
</body>
</html>