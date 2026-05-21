<?php
require_once 'config/config.php';

// Auto-run database migrations on first load
function runMigrations($pdo) {
    try {
        // Check if column exists
        $stmt = $pdo->query("DESCRIBE events");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('time', $columns)) {
            $pdo->exec("ALTER TABLE events ADD COLUMN time TIME DEFAULT NULL AFTER date");
        }
        if (!in_array('location', $columns)) {
            $pdo->exec("ALTER TABLE events ADD COLUMN location VARCHAR(255) DEFAULT NULL AFTER time");
        }
        if (!in_array('image', $columns)) {
            $pdo->exec("ALTER TABLE events ADD COLUMN image VARCHAR(255) DEFAULT NULL AFTER max_attendees");
        }
    } catch (PDOException $e) {
        // Ignore migration errors
    }
}

// Auto-create sample events if none exist
function ensureSampleEvents($pdo) {
    $stmt = $pdo->query("SELECT COUNT(*) FROM events");
    $eventCount = $stmt->fetchColumn();
    
    if ($eventCount == 0) {
        $sampleEvents = [
            ['Spring Garden Wedding', '2026-05-16', '15:00:00', 'Tagaytay Highlands, Cavite', 'An enchanting outdoor celebration surrounded by blooming florals and soft natural light. Perfect for couples seeking a romantic garden ceremony.', 150000, 150],
            ['Corporate Summit 2026', '2026-09-08', '09:00:00', 'Shangri-La The Fort, Taguig City', 'A refined gathering of industry leaders featuring keynote sessions, panel discussions, and curated networking.', 200000, 200],
            ['Evening Gala Night', '2026-12-12', '18:00:00', 'Cebu City Marriott Hotel, Cebu', 'An elegant black-tie evening filled with fine dining, live music, and unforgettable entertainment.', 250000, 180],
            ['Charity Fundraiser', '2026-10-18', '11:00:00', 'SMX Convention Center, Davao City', 'A heartwarming community event bringing people together for a meaningful cause and positive impact.', 100000, 250],
            ['Summer Music Festival', '2026-07-22', '14:00:00', 'Burnham Park, Baguio City', 'An electrifying outdoor music experience featuring top artists, food trucks, and unforgettable sunset vibes.', 180000, 300],
            ['Art & Wine Exhibition', '2026-08-14', '17:00:00', 'Intramuros, Manila', 'A refined evening of curated local art, premium wine tastings, and live acoustic performances under the stars.', 120000, 120]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO events (id, user_id, title, date, time, location, description, budget, max_attendees, status) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, 'planned')");
        
foreach ($sampleEvents as $event) {
            $id = 'evt_' . substr(md5($event[0]), 0, 8) . '_' . rand(100, 999);
            $stmt->execute([$id, $event[0], $event[1], $event[2], $event[3], $event[4], $event[5], $event[6]]);
        }
    }
}

// Run migrations and ensure sample data
runMigrations($pdo);
ensureSampleEvents($pdo);

// Fetch events from database
$stmt = $pdo->query("SELECT id, title, date, time, location, description, budget, max_attendees, image FROM events WHERE status != 'cancelled' ORDER BY date ASC");
$events = $stmt->fetchAll();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Handle pending booking after login return
$bookingMessage = '';
if ($isLoggedIn && isset($_SESSION['pending_booking']) && isset($_GET['book']) && $_GET['book'] === '1') {
    $pending = $_SESSION['pending_booking'];
    $eventId = $pending['event_id'];
    $guestCount = $pending['guest_count'];
    
    try {
        // Check if already booked
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ? AND event_id = ? AND status != 'cancelled'");
        $stmt->execute([$_SESSION['user_id'], $eventId]);
        
        if (!$stmt->fetch()) {
            // Get event details
            $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status != 'cancelled'");
            $stmt->execute([$eventId]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($event) {
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
                $bookingMessage = 'Successfully booked: ' . htmlspecialchars($event['title']);
            }
        } else {
            $bookingMessage = 'You have already booked this event';
        }
    } catch (PDOException $e) {
        $bookingMessage = 'Booking failed. Please try again.';
    }
    
    // Clear pending booking
    unset($_SESSION['pending_booking']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CAVENDIA — Architecting Extraordinary Moments</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --sage: #A3B18A;
            --sage-dark: #8A9A6D;
            --sage-light: #DDE5D1;
            --cream: #F1F2EE;
            --forest: #1B4332;
            --forest-light: #2F4F2F;
            --charcoal: #2D3748;
            --white: #FFFFFF;
            --text-muted: #6B7C6D;
            --border: #D8DDD3;
            --pale-sage: #F0F7F4;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--cream);
            color: var(--forest);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* ── Header ── */
        .site-header {
            background: var(--sage);
            padding: 16px 64px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 12px rgba(27,67,50,0.08);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo i {
            font-size: 1.4rem;
            color: var(--white);
        }

        .logo span {
            font-family: 'Inter', sans-serif;
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--white);
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 36px;
            list-style: none;
        }

        .nav-menu a {
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: rgba(255,255,255,0.92);
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
        }

        .nav-menu a:hover {
            color: var(--white);
        }

        .nav-menu a::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--white);
            transition: width 0.3s ease;
        }

        .nav-menu a:hover::after {
            width: 100%;
        }

        .nav-menu .btn-outline {
            border: 1.5px solid rgba(255,255,255,0.7);
            padding: 8px 22px;
            border-radius: 24px;
            transition: all 0.3s ease;
        }

        .nav-menu .btn-outline::after { display: none; }

        .nav-menu .btn-outline:hover {
            background: var(--white);
            color: var(--sage);
        }

        .nav-menu .btn-solid {
            background: var(--white);
            color: var(--sage) !important;
            padding: 8px 22px;
            border-radius: 24px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .nav-menu .btn-solid::after { display: none; }

        .nav-menu .btn-solid:hover {
            background: var(--forest);
            color: var(--white) !important;
        }

        /* ── Hero Section ── */
        .hero {
            position: relative;
            min-height: 92vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .hero-bg {
            position: absolute;
            inset: 0;
            background: url('Gemini_Generated_Image_vnguk6vnguk6vngu.png') center center / cover no-repeat;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        .hero-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 15% 25%, rgba(255,220,150,0.7) 0%, rgba(255,200,100,0.3) 20%, transparent 35%),
                radial-gradient(circle at 35% 15%, rgba(255,225,160,0.6) 0%, rgba(255,210,120,0.25) 18%, transparent 30%),
                radial-gradient(circle at 55% 30%, rgba(255,220,150,0.55) 0%, rgba(255,200,100,0.2) 15%, transparent 28%),
                radial-gradient(circle at 75% 20%, rgba(255,230,170,0.65) 0%, rgba(255,215,130,0.3) 20%, transparent 35%),
                radial-gradient(circle at 90% 35%, rgba(255,225,160,0.5) 0%, rgba(255,205,110,0.2) 15%, transparent 25%),
                radial-gradient(circle at 25% 55%, rgba(255,220,150,0.45) 0%, transparent 20%),
                radial-gradient(circle at 65% 50%, rgba(255,225,165,0.4) 0%, transparent 18%),
                radial-gradient(circle at 85% 60%, rgba(255,215,140,0.5) 0%, transparent 22%);
        }

        .hero-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse at 0% 100%, rgba(47,79,47,0.25) 0%, transparent 50%),
                radial-gradient(ellipse at 100% 100%, rgba(60,90,50,0.2) 0%, transparent 45%),
                radial-gradient(ellipse at 50% 100%, rgba(40,70,40,0.15) 0%, transparent 40%);
        }

        .hero-card {
            position: relative;
            z-index: 2;
            background: rgba(241, 242, 238, 0.72);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 32px;
            padding: 64px 72px;
            max-width: 720px;
            width: 90%;
            text-align: center;
            box-shadow:
                0 4px 24px rgba(27,67,50,0.08),
                0 24px 80px rgba(27,67,50,0.12);
        }

        .hero-card h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.6rem;
            font-weight: 600;
            color: var(--forest);
            margin-bottom: 20px;
            letter-spacing: 1px;
            line-height: 1.15;
        }

        .hero-card .subhead {
            font-size: 1.05rem;
            font-weight: 300;
            color: var(--text-muted);
            max-width: 480px;
            margin: 0 auto 36px;
            line-height: 1.7;
        }

        .btn-getstarted {
            display: inline-block;
            background: var(--sage);
            color: var(--white);
            padding: 16px 40px;
            border-radius: 28px;
            font-size: 0.9rem;
            font-weight: 600;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-getstarted:hover {
            background: var(--forest);
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(27,67,50,0.2);
        }

        /* ── About Section ── */
        .about-section {
            background: var(--white);
            padding: 100px 64px;
            position: relative;
        }

        .about-container {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
        }

        .about-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--charcoal);
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }

        .about-subtitle {
            font-size: 1.1rem;
            color: #718096;
            font-weight: 300;
            max-width: 600px;
            margin: 0 auto 80px;
            line-height: 1.7;
        }

        .about-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 48px;
            margin-top: 40px;
        }

        .about-column {
            padding: 0 20px;
        }

        .about-icon {
            width: 80px;
            height: 80px;
            background: var(--pale-sage);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            border: 2px solid rgba(163,177,138,0.2);
            transition: all 0.3s ease;
        }

        .about-column:hover .about-icon {
            background: var(--sage-light);
            transform: scale(1.05);
            border-color: var(--sage);
        }

        .about-icon i {
            font-size: 1.4rem;
            color: var(--sage);
            width: 32px;
        }

        .about-column h3 {
            font-family: 'Inter', sans-serif;
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--charcoal);
            margin-bottom: 16px;
            letter-spacing: -0.01em;
        }

        .about-column p {
            font-size: 1rem;
            color: var(--text-muted);
            line-height: 1.75;
            font-weight: 300;
        }

        /* ── Featured Events Section ── */
        .featured-events { background: var(--cream); padding: 100px 64px; }
        .featured-events .section-title { text-align: center; margin-bottom: 56px; }
        .featured-events .section-title h2 {
            font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 600;
            color: var(--forest); margin-bottom: 12px; letter-spacing: 1px;
        }
        .featured-events .section-subtitle {
            font-size: 1rem; color: var(--text-muted); font-weight: 300;
            max-width: 560px; margin: 0 auto; line-height: 1.7;
        }
        .featured-events .divider {
            width: 56px; height: 3px; background: var(--sage);
            margin: 16px auto 20px; border-radius: 2px;
        }
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 32px; max-width: 1200px; margin: 0 auto;
        }
        .event-card {
            background: var(--white); border: 1px solid var(--border);
            border-radius: 24px; overflow: hidden; transition: all 0.35s ease;
        }
        .event-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 16px 48px rgba(27,67,50,0.1);
            border-color: var(--sage);
        }
        .event-img {
            height: 220px; position: relative; overflow: hidden;
        }
        .event-img img {
            width: 100%; height: 100%; object-fit: cover;
            display: block; transition: transform 0.5s ease;
        }
        .event-card:hover .event-img img {
            transform: scale(1.08);
        }
        .event-img .img-bg { position: absolute; inset: 0; }
        .event-img .img-bg.garden {
            background:
                radial-gradient(ellipse at 30% 70%, rgba(163,177,138,0.4) 0%, transparent 60%),
                linear-gradient(135deg, #C8D5B9 0%, #B8C9A8 40%, #A8B998 100%);
        }
        .event-img .img-bg.summit {
            background:
                radial-gradient(ellipse at 70% 30%, rgba(120,140,110,0.35) 0%, transparent 55%),
                linear-gradient(160deg, #D4CFC5 0%, #C4BFB5 50%, #B4AFA5 100%);
        }
        .event-img .img-bg.gala {
            background:
                radial-gradient(ellipse at 20% 80%, rgba(180,160,120,0.4) 0%, transparent 60%),
                linear-gradient(135deg, #E8DDD0 0%, #D4C4B0 40%, #C4B09A 100%);
        }
        .event-img .img-bg.charity {
            background:
                radial-gradient(ellipse at 80% 20%, rgba(140,170,140,0.35) 0%, transparent 55%),
                linear-gradient(160deg, #C8D8C8 0%, #B8C8B8 50%, #A8B8A8 100%);
        }
        .event-img .img-bg.music {
            background:
                radial-gradient(ellipse at 30% 70%, rgba(225,112,85,0.35) 0%, transparent 55%),
                linear-gradient(135deg, #F3D1C1 0%, #E8C0A8 50%, #D9A88C 100%);
        }
        .event-img .img-bg.exhibition {
            background:
                radial-gradient(ellipse at 70% 30%, rgba(108,92,231,0.3) 0%, transparent 55%),
                linear-gradient(160deg, #D1D0E0 0%, #C0BED0 50%, #A8A5C0 100%);
        }
        .event-img .img-overlay {
            position: absolute; inset: 0; display: flex;
            align-items: center; justify-content: center;
        }
        .event-img .img-overlay i { font-size: 3rem; opacity: 0.25; }
        .event-img .img-overlay i.garden-icon { color: var(--forest); }
        .event-img .img-overlay i.summit-icon { color: var(--forest); }
        .event-img .img-overlay i.gala-icon { color: var(--forest); }
        .event-img .img-overlay i.charity-icon { color: var(--forest); }
        .event-img .img-overlay i.music-icon { color: var(--forest); }
        .event-img .img-overlay i.exhibition-icon { color: var(--forest); }
        .event-badge {
            position: absolute; top: 16px; right: 16px;
            background: var(--sage); color: var(--white);
            padding: 6px 14px; border-radius: 20px;
            font-size: 0.7rem; font-weight: 600;
            letter-spacing: 1px; text-transform: uppercase;
        }
        .event-content { padding: 28px; }
        .event-content h3 {
            font-family: 'Playfair Display', serif; font-size: 1.25rem;
            font-weight: 600; color: var(--forest); margin-bottom: 10px;
        }
        .event-content p {
            font-size: 0.88rem; font-weight: 300;
            color: var(--text-muted); line-height: 1.65; margin-bottom: 18px;
        }
        .event-meta { display: flex; flex-direction: column; gap: 10px; }
        .event-meta .meta-item {
            display: flex; align-items: center; gap: 10px;
            font-size: 0.82rem; color: var(--text-muted);
        }
        .event-meta .meta-item i {
            color: var(--sage); font-size: 0.9rem; width: 18px; text-align: center;
        }

        /* ── Footer ── */
        .site-footer {
            background: var(--forest); color: rgba(255,255,255,0.75);
            padding: 48px 64px; text-align: center;
        }
        .site-footer .footer-logo {
            display: flex; align-items: center; justify-content: center;
            gap: 10px; margin-bottom: 16px;
        }
        .site-footer .footer-logo i { font-size: 1.2rem; color: var(--sage); }
        .site-footer .footer-logo span {
            font-family: 'Inter', sans-serif; font-size: 1.2rem;
            font-weight: 700; color: var(--white); letter-spacing: 3px; text-transform: uppercase;
        }
        .site-footer p { font-size: 0.85rem; font-weight: 300; letter-spacing: 0.5px; line-height: 1.7; }
        .site-footer .contact-line { margin-top: 8px; opacity: 0.65; font-size: 0.8rem; }

        /* ── Responsive ── */
        @media (max-width: 1024px) {
            .site-header { padding: 16px 32px; }
            .nav-menu { gap: 24px; }
            .hero-card { padding: 48px 40px; }
            .hero-card h1 { font-size: 2rem; }
            .about-section, .featured-events { padding: 80px 32px; }
        }
        @media (max-width: 768px) {
            .site-header { flex-direction: column; gap: 14px; padding: 16px 24px; }
            .nav-menu { flex-wrap: wrap; justify-content: center; gap: 18px; }
            .hero-card { padding: 40px 28px; border-radius: 24px; }
            .hero-card h1 { font-size: 1.6rem; }
            .about-title { font-size: 2rem; }
            .about-grid { grid-template-columns: 1fr; gap: 32px; }
            .about-section, .featured-events { padding: 60px 24px; }
            .events-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="site-header">
        <a href="index.php" class="logo">
            <i class="fas fa-ticket-alt"></i>
            <span>CAVENDIA</span>
        </a>
        <nav>
            <ul class="nav-menu">
                <li><a href="#hero">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#events">Events</a></li>
                <li><a href="#contact">Contact</a></li>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php" class="btn-solid">Sign Up</a></li>
            </ul>
        </nav>
    </header>

<?php if (!empty($bookingMessage)): ?>
            <div id="bookingAlert" style="position: fixed; top: 100px; right: 20px; z-index: 1001; max-width: 350px; animation: slideIn 0.4s ease;">
                <div style="background: <?php echo strpos($bookingMessage, 'Successfully') !== false ? '#D1FAE5' : '#FEF3C7'; ?>; border-radius: 12px; padding: 16px 20px; box-shadow: 0 8px 24px rgba(27,67,50,0.15); border: 1px solid <?php echo strpos($bookingMessage, 'Successfully') !== false ? '#A7F3D0' : '#FDE68A'; ?>;">
                    <div style="display: flex; align-items: flex-start; gap: 12px;">
                        <div style="width: 28px; height: 28px; border-radius: 50%; background: <?php echo strpos($bookingMessage, 'Successfully') !== false ? '#10B981' : '#F59E0B'; ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas <?php echo strpos($bookingMessage, 'Successfully') !== false ? 'fa-check' : 'fa-info'; ?>" style="color: white; font-size: 0.8rem;"></i>
                        </div>
                        <div style="flex: 1;">
                            <p style="font-size: 0.9rem; font-weight: 500; color: #1B4332; margin: 0;"><?php echo htmlspecialchars($bookingMessage); ?></p>
                        </div>
                        <button onclick="document.getElementById('bookingAlert').style.display='none'" style="background: none; border: none; cursor: pointer; color: #6B7C6D; padding: 0;">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
            <script>setTimeout(() => { const el = document.getElementById('bookingAlert'); if (el) el.style.display = 'none'; }, 5000);</script>
            <?php endif; ?>

<!-- Hero Section -->
    <section id="hero" class="hero active-section">
        <div class="hero-bg"></div>
        <div class="hero-card">
            <h1>CAVENDIA: ARCHITECTING EXTRAORDINARY MOMENTS</h1>
            <p class="subhead">We craft bespoke event designs tailored to your unique vision, transforming celebrations into timeless memories with elegance and precision.</p>
            <a href="register.php" class="btn-getstarted">Get Started</a>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="about-section">
        <div class="about-container">
            <h1 class="about-title">About CAVENDIA</h1>
            <p class="about-subtitle">Crafting unforgettable moments through meticulous planning, creative vision, and unwavering commitment to excellence.</p>
            <div class="about-grid">
                <div class="about-column">
                    <div class="about-icon"><i class="fas fa-bullseye"></i></div>
                    <h3>Our Mission</h3>
                    <p>To transform every celebration into a masterpiece of design and emotion, creating experiences that resonate deeply and endure forever.</p>
                </div>
                <div class="about-column">
                    <div class="about-icon"><i class="fas fa-heart"></i></div>
                    <h3>Our Values</h3>
                    <p>Excellence in execution, authenticity in design, and passion in every detail—principles that guide our every creation.</p>
                </div>
                <div class="about-column">
                    <div class="about-icon"><i class="fas fa-bolt"></i></div>
                    <h3>Our Approach</h3>
                    <p>Bespoke planning with precision budgeting, seamless attendee management, and real-time expense tracking for flawless execution.</p>
                </div>
            </div>
        </div>
    </section>

<!-- Featured Events Section -->
    <section id="events" class="featured-events">
        <div class="section-title">
            <h2>Featured Events</h2>
            <div class="divider"></div>
            <p class="section-subtitle">Discover upcoming events and see how EventPlanner helps organize successful occasions</p>
        </div>
        <div class="events-grid">
            <?php if (empty($events)): ?>
            <p style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">No events available at the moment. Check back soon!</p>
            <?php else: ?>
            <?php foreach ($events as $event): 
                // Determine badge style based on event type (default to 'Wedding' if not specified)
                $badgeColors = [
                    'wedding' => '#A3B18A',
                    'corporate' => '#4a6b50',
                    'gala' => '#8B7355',
                    'charity' => '#6B8E6B',
                    'music' => '#e17055',
                    'exhibition' => '#6c5ce7'
                ];
                $eventType = strtolower($event['title'] ?? '');
                $badgeColor = '#A3B18A';
                foreach ($badgeColors as $type => $color) {
                    if (strpos($eventType, $type) !== false) {
                        $badgeColor = $color;
                        break;
                    }
                }
                
// Format date and time
                $eventDate = !empty($event['date']) ? date('F j, Y', strtotime($event['date'])) : 'TBD';
                $eventTime = !empty($event['time']) ? date('g:i A', strtotime($event['time'])) . ' onwards' : 'Time TBA';
                $location = htmlspecialchars($event['location'] ?? 'Location TBA');
                $description = htmlspecialchars($event['description'] ?? 'No description available.');
                $budget = !empty($event['budget']) ? '₱' . number_format($event['budget']) : 'TBD';
                $maxAttendees = $event['max_attendees'] ?? 200;
            ?>
            <article class="event-card">
                <div class="event-img">
                    <?php if (!empty($event['image'])): ?>
                    <img src="<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                    <?php else: ?>
                    <img src="photorealistic-wedding-venue-with-intricate-decor-ornaments_23-2151481464.avif" alt="Featured Venue" style="background: linear-gradient(135deg, <?php echo $badgeColor; ?>40, <?php echo $badgeColor; ?>20);">
                    <?php endif; ?>

                    <span class="event-badge" style="background: <?php echo $badgeColor; ?>;">Event</span>
                </div>
                <div class="event-content">
                    <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                    <p><?php echo $description; ?></p>
                    <div class="event-meta">
                        <div class="meta-item"><i class="far fa-calendar-alt"></i><span><?php echo $eventDate; ?></span></div>
                        <div class="meta-item"><i class="far fa-clock"></i><span><?php echo $eventTime; ?></span></div>
                        <div class="meta-item"><i class="fas fa-map-marker-alt"></i><span><?php echo $location; ?></span></div>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Upcoming Events Section -->
    <section class="upcoming-events" style="background: var(--white); padding: 100px 64px;">
        <div class="section-title" style="text-align: center; margin-bottom: 56px;">
            <h2 style="font-family: 'Playfair Display', serif; font-size: 2rem; font-weight: 600; color: var(--forest); margin-bottom: 12px; letter-spacing: 1px;">Upcoming Events</h2>
            <div class="divider" style="width: 56px; height: 3px; background: var(--sage); margin: 16px auto 20px; border-radius: 2px;"></div>
            <p class="section-subtitle" style="font-size: 1rem; color: var(--text-muted); font-weight: 300; max-width: 560px; margin: 0 auto; line-height: 1.7;">Nearest events coming up soon</p>
        </div>
        <div class="events-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 32px; max-width: 1200px; margin: 0 auto;">
            <?php
            $stmt = $pdo->query("SELECT id, title, date, time, location, description, budget, max_attendees, image FROM events WHERE status != 'cancelled' AND date >= CURDATE() ORDER BY date ASC LIMIT 3");
            $upcomingEvents = $stmt->fetchAll();
            if (empty($upcomingEvents)): ?>
            <p style="grid-column: 1/-1; text-align: center; color: var(--text-muted);">No upcoming events at the moment. Check back soon!</p>
            <?php else: 
            foreach ($upcomingEvents as $event): 
                $badgeColors = [
                    'wedding' => '#A3B18A',
                    'corporate' => '#4a6b50',
                    'gala' => '#8B7355',
                    'charity' => '#6B8E6B',
                    'music' => '#e17055',
                    'exhibition' => '#6c5ce7'
                ];
                $eventType = strtolower($event['title'] ?? '');
                $badgeColor = '#A3B18A';
                foreach ($badgeColors as $type => $color) {
                    if (strpos($eventType, $type) !== false) {
                        $badgeColor = $color;
                        break;
                    }
                }
                $eventDate = !empty($event['date']) ? date('F j, Y', strtotime($event['date'])) : 'TBD';
                $eventTime = !empty($event['time']) ? date('g:i A', strtotime($event['time'])) . ' onwards' : 'Time TBA';
                $location = htmlspecialchars($event['location'] ?? 'Location TBA');
                $description = htmlspecialchars($event['description'] ?? 'No description available.');
            ?>
            <article class="event-card">
                <div class="event-img">
                    <?php if (!empty($event['image'])): ?>
                    <img src="<?php echo htmlspecialchars($event['image']); ?>" alt="<?php echo htmlspecialchars($event['title']); ?>">
                    <?php else: ?>
                    <img src="photorealistic-wedding-venue-with-intricate-decor-ornaments_23-2151481464.avif" alt="Featured Venue" style="background: linear-gradient(135deg, <?php echo $badgeColor; ?>40, <?php echo $badgeColor; ?>20);">
                    <?php endif; ?>
                    <span class="event-badge" style="background: <?php echo $badgeColor; ?>;">Upcoming</span>
                </div>

                <div class="event-content">
                    <h3><?php echo htmlspecialchars($event['title']); ?></h3>
                    <p><?php echo $description; ?></p>
                    <div class="event-meta">
                        <div class="meta-item"><i class="far fa-calendar-alt"></i><span><?php echo $eventDate; ?></span></div>
                        <div class="meta-item"><i class="far fa-clock"></i><span><?php echo $eventTime; ?></span></div>
                        <div class="meta-item"><i class="fas fa-map-marker-alt"></i><span><?php echo $location; ?></span></div>
                    </div>
                </div>
            </article>
            <?php endforeach; 
            endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="site-footer" id="contact">
        <div class="footer-logo">
            <i class="fas fa-ticket-alt"></i>
            <span>CAVENDIA</span>
        </div>
        <p>Bespoke Event Design Collective — Crafting Unforgettable Moments Since 2015</p>
        <p class="contact-line">contact@cavendia.com &nbsp;&bull;&nbsp; +1 (555) 234-5678</p>
</footer>

    <!-- Book Event Modal -->
<div id="bookEventModal" class="book-modal-overlay">
        <div class="book-modal">
            <input type="hidden" id="bookEventId" value="">
            <div class="book-modal-header">
                <h2>Book Event</h2>
                <button class="book-modal-close" onclick="closeBookModal()">&times;</button>
            </div>
            <div class="book-modal-body">
<div class="book-modal-section">
                    <h3>Guest Information</h3>
                    <div class="form-row-2">
                        <div class="book-form-group">
                            <label>Last Name *</label>
                            <input type="text" id="bookLastName" placeholder="Doe" required>
                        </div>
                        <div class="book-form-group">
                            <label>First Name *</label>
                            <input type="text" id="bookFirstName" placeholder="John" required>
                        </div>
                    </div>
                    <div class="book-form-group">
                        <label>Email *</label>
                        <input type="email" id="bookEmail" placeholder="you@gmail.com" required>
                    </div>
                    <div class="book-form-group">
                        <label>Phone Number * (11 digits)</label>
                        <input type="tel" id="bookPhone" placeholder="091234567890" maxlength="11" inputmode="numeric" required>
                    </div>
<div class="book-form-group">
                        <label>Address *</label>
                        <input type="text" id="bookAddress" placeholder="123 Main St, City, Province" required>
                    </div>
                    <div class="book-form-group">
                        <label>Number of Guests *</label>
                        <input type="number" id="bookGuests" min="1" value="1" required>
                    </div>
                </div>
                <div class="book-modal-section">
                    <h3>Event Details</h3>
                    <div class="book-event-details">
                        <div class="book-detail-row">
                            <span>Date:</span>
                            <span id="bookEventDate"></span>
                        </div>
                        <div class="book-detail-row">
                            <span>Budget:</span>
                            <span id="bookEventBudget"></span>
                        </div>
                        <div class="book-detail-row">
                            <span>Max Attendees:</span>
                            <span id="bookEventMax"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="book-modal-footer">
                <button class="btn-cancel-book" onclick="closeBookModal()">Cancel</button>
                <button class="btn-submit-book" onclick="submitBooking()">Submit Booking</button>
            </div>
        </div>
    </div>

<script>
        // Book Event Modal Functions
        function openBookingForm(eventId, eventDate, eventBudget, maxAttendees) {
            document.getElementById('bookEventId').value = eventId;
            document.getElementById('bookEventDate').textContent = eventDate;
            document.getElementById('bookEventBudget').textContent = eventBudget;
            document.getElementById('bookEventMax').textContent = maxAttendees;
            document.getElementById('bookEventModal').style.display = 'flex';
        }

        function closeBookModal() {
            document.getElementById('bookEventModal').style.display = 'none';
        }

function submitBooking() {
            var eventId = document.getElementById('bookEventId').value;
            var lastname = document.getElementById('bookLastName').value.trim();
            var firstname = document.getElementById('bookFirstName').value.trim();
            var email = document.getElementById('bookEmail').value.trim();
            var phone = document.getElementById('bookPhone').value.trim();
            var address = document.getElementById('bookAddress').value.trim();
            var guests = document.getElementById('bookGuests').value;

            // Clear previous error styles
            document.querySelectorAll('.book-form-group').forEach(function(group) {
                group.classList.remove('has-error');
            });

            var errors = [];

            // Validate required fields
            if (!lastname) errors.push({field: 'bookLastName', msg: 'Last name is required'});
            if (!firstname) errors.push({field: 'bookFirstName', msg: 'First name is required'});
            if (!email) {
                errors.push({field: 'bookEmail', msg: 'Email is required'});
            } else if (!email.endsWith('@gmail.com')) {
                errors.push({field: 'bookEmail', msg: 'Email must be @gmail.com'});
            }
            if (!phone) {
                errors.push({field: 'bookPhone', msg: 'Phone is required'});
            } else if (phone.replace(/\D/g, '').length !== 11) {
                errors.push({field: 'bookPhone', msg: 'Enter 11 digits'});
            }
            if (!address) errors.push({field: 'bookAddress', msg: 'Address is required'});
            if (!guests) errors.push({field: 'bookGuests', msg: 'Number of guests is required'});

            // Show errors
            if (errors.length > 0) {
                errors.forEach(function(err) {
                    var input = document.getElementById(err.field);
                    if (input) {
                        input.classList.add('error-border');
                        // Store error message
                        input.setAttribute('data-error', err.msg);
                    }
                });
                alert(errors[0].msg);
                return;
            }

var fullName = firstname + ' ' + lastname;
            var bookingData = JSON.stringify({
                event_id: eventId,
                lastname: lastname,
                firstname: firstname,
                full_name: fullName,
                email: email,
                phone: phone,
                address: address,
                guest_count: parseInt(guests)
            });

sessionStorage.setItem('pending_booking', bookingData);
            window.location.href = 'register.php';
        }

        // Restrict phone to numbers only
        document.getElementById('bookPhone').addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').slice(0, 11);
        });

        // Real-time validation - remove error on input
        document.querySelectorAll('#bookEventModal input').forEach(function(input) {
            input.addEventListener('input', function() {
                this.classList.remove('error-border');
            });
        });

        // Event Details Modal Functions
        function showEventDetails(id, title, date, time, location, description, budget, maxAttendees) {
            document.getElementById('modalEventId').value = id;
            document.getElementById('modalEventTitle').textContent = title;
            document.getElementById('modalEventDate').textContent = date;
            document.getElementById('modalEventTime').textContent = time;
            document.getElementById('modalEventLocation').textContent = location;
            document.getElementById('modalEventDescription').textContent = description;
            document.getElementById('modalEventBudget').textContent = budget;
            document.getElementById('modalEventMaxAttendees').textContent = maxAttendees + ' guests';
            document.getElementById('eventDetailsModal').style.display = 'flex';
        }

        function closeEventDetails() {
            document.getElementById('eventDetailsModal').style.display = 'none';
        }

// Book Event Function
function bookEvent(eventId, eventTitle) {
            if (confirm('Do you want to book "' + eventTitle + '"?')) {
                // Send booking request via fetch with credentials
                fetch('api.php?type=book', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ event_id: eventId, guest_count: 1 })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Booking successful! You can view your booking in your dashboard.');
                        window.location.href = 'booking.php';
                    } else {
                        alert(data.error || 'Failed to book event. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            }
        }

// Store booking intent and redirect to register (use JSON format for sessionStorage)
        function storeBooking(eventId, event) {
            if (event) {
                event.preventDefault();
            }
            // Store booking intent in sessionStorage as JSON
            const bookingData = JSON.stringify({ event_id: eventId, guest_count: 1 });
            sessionStorage.setItem('pending_booking', bookingData);
            window.location.href = 'register.php';
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const modal = document.getElementById('eventDetailsModal');
            if (e.target === modal) {
                closeEventDetails();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventDetails();
            }
        });

        window.addEventListener('load', () => {
            document.getElementById('hero').classList.add('active-section');
        });
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) { target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
            });
        });
    </script>

    <!-- Event Details Modal -->
    <div id="eventDetailsModal" style="display: none; position: fixed; inset: 0; background: rgba(27,67,50,0.6); backdrop-filter: blur(8px); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: #F1F2EE; border-radius: 24px; max-width: 520px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 50px rgba(27,67,50,0.25); animation: modalSlide 0.3s ease;">
            <div style="padding: 28px;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;">
                    <h2 style="font-family: 'Playfair Display', serif; font-size: 1.5rem; color: #1B4332; margin: 0;">Event Details</h2>
                    <button onclick="closeEventDetails()" style="background: transparent; border: none; font-size: 1.5rem; cursor: pointer; color: #6B7C6D; padding: 0; line-height: 1;">&times;</button>
                </div>
                <input type="hidden" id="modalEventId" value="">
                
                <div style="background: #fff; border-radius: 16px; padding: 20px; margin-bottom: 20px; border: 1px solid #D8DDD3;">
                    <h3 id="modalEventTitle" style="font-family: 'Playfair Display', serif; font-size: 1.25rem; color: #1B4332; margin-bottom: 16px;"></h3>
                    
                    <div style="display: grid; gap: 14px;">
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <i class="far fa-calendar-alt" style="color: #A3B18A; font-size: 1rem; margin-top: 2px;"></i>
                            <div>
                                <span style="display: block; font-size: 0.75rem; color: #6B7C6D; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Date</span>
                                <span id="modalEventDate" style="font-size: 0.95rem; color: #2D3748;"></span>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <i class="far fa-clock" style="color: #A3B18A; font-size: 1rem; margin-top: 2px;"></i>
                            <div>
                                <span style="display: block; font-size: 0.75rem; color: #6B7C6D; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Time</span>
                                <span id="modalEventTime" style="font-size: 0.95rem; color: #2D3748;"></span>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <i class="fas fa-map-marker-alt" style="color: #A3B18A; font-size: 1rem; margin-top: 2px;"></i>
                            <div>
                                <span style="display: block; font-size: 0.75rem; color: #6B7C6D; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Location</span>
                                <span id="modalEventLocation" style="font-size: 0.95rem; color: #2D3748;"></span>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <i class="fas fa-users" style="color: #A3B18A; font-size: 1rem; margin-top: 2px;"></i>
                            <div>
                                <span style="display: block; font-size: 0.75rem; color: #6B7C6D; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Capacity</span>
                                <span id="modalEventMaxAttendees" style="font-size: 0.95rem; color: #2D3748;"></span>
                            </div>
                        </div>
                        
                        <div style="display: flex; align-items: flex-start; gap: 12px;">
                            <i class="fas fa-peso-sign" style="color: #A3B18A; font-size: 1rem; margin-top: 2px;"></i>
                            <div>
                                <span style="display: block; font-size: 0.75rem; color: #6B7C6D; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">Budget</span>
                                <span id="modalEventBudget" style="font-size: 0.95rem; color: #1B4332; font-weight: 600;"></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="background: #fff; border-radius: 16px; padding: 20px; border: 1px solid #D8DDD3;">
                    <span style="display: block; font-size: 0.75rem; color: #6B7C6D; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px;">Description</span>
                    <p id="modalEventDescription" style="font-size: 0.95rem; color: #2D3748; line-height: 1.6; margin: 0;"></p>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button onclick="closeEventDetails()" style="flex: 1; padding: 14px 20px; background: transparent; border: 1.5px solid #A3B18A; color: #A3B18A; border-radius: 24px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                        Close
                    </button>
                    <?php if ($isLoggedIn): ?>
                    <button onclick="var id = document.getElementById('modalEventId').value; var title = document.getElementById('modalEventTitle').textContent; closeEventDetails(); bookEvent(id, title);" style="flex: 1; padding: 14px 20px; background: #A3B18A; border: none; color: white; border-radius: 24px; font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease;">
                        Book Now
                    </button>
<?php else: ?>
register.php
                        Sign Up to Book
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<style>
        @keyframes modalSlide {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes bookModal {
            from { opacity: 0; transform: scale(0.9) translateY(-20px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .book-modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(27,67,50,0.65);
            backdrop-filter: blur(10px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .book-modal-overlay.active { display: flex; }
        .book-modal {
            background: #F1F2EE;
            border-radius: 24px;
            max-width: 520px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(27,67,50,0.3);
            animation: bookModal 0.35s ease;
        }
        .book-modal-header {
            padding: 24px 28px 16px;
            border-bottom: 1px solid #D8DDD3;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .book-modal-header h2 {
            font-family: 'Playfair Display', serif;
            font-size: 1.4rem;
            color: #1B4332;
            margin: 0;
        }
        .book-modal-close {
            background: transparent;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6B7C6D;
            padding: 0;
            line-height: 1;
            transition: color 0.2s;
        }
        .book-modal-close:hover { color: #1B4332; }
        .book-modal-body {
            padding: 24px 28px;
        }
        .book-modal-section {
            margin-bottom: 20px;
        }
        .book-modal-section h3 {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6B7C6D;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }
        .book-form-group {
            margin-bottom: 14px;
        }
        .book-form-group label {
            display: block;
            font-size: 0.75rem;
            font-weight: 500;
            color: #6B7C6D;
            margin-bottom: 6px;
        }
        .book-form-group input,
        .book-form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid #D8DDD3;
            border-radius: 12px;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            background: #fff;
            color: #1B4332;
            transition: border-color 0.3s;
        }
        .book-form-group input:focus,
        .book-form-group textarea:focus {
            outline: none;
            border-color: #A3B18A;
            box-shadow: 0 0 0 3px rgba(163,177,138,0.1);
        }
.book-form-group input::placeholder,
        .book-form-group textarea::placeholder { color: #B8C9B0; }
        
        /* Form row for 2 columns */
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .form-row-2 .book-form-group {
            margin-bottom: 0;
        }
        
/* Error styling - only corner border red, not full padding */
        .error-border {
            border-color: #d32f2f !important;
            background: #fff !important;
        }
        
        .book-form-group.error-field {
            padding-left: 0;
        }
        
        .book-form-group.error-field input {
            border-left: 3px solid #d32f2f !important;
        }
        
        .book-event-details {
            background: #fff;
            border-radius: 14px;
            padding: 16px;
            border: 1px solid #D8DDD3;
            margin-bottom: 20px;
        }
        .book-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #F0F7F4;
        }
        .book-detail-row:last-child { border-bottom: none; }
        .book-detail-row span:first-child {
            font-size: 0.8rem;
            color: #6B7C6D;
        }
        .book-detail-row span:last-child {
            font-size: 0.85rem;
            font-weight: 600;
            color: #1B4332;
        }
        .book-modal-footer {
            padding: 20px 28px 24px;
            display: flex;
            gap: 12px;
            border-top: 1px solid #D8DDD3;
        }
        .btn-cancel-book {
            flex: 1;
            padding: 14px 20px;
            background: transparent;
            border: 1.5px solid #A3B18A;
            color: #A3B18A;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-cancel-book:hover {
            background: #f0f7f4;
        }
        .btn-submit-book {
            flex: 1;
            padding: 14px 20px;
            background: #A3B18A;
            border: none;
            color: #fff;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit-book:hover {
            background: #1B4332;
        }
    </style>
</body>
</html>

