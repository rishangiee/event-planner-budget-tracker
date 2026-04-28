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
            height: 200px; position: relative; overflow: hidden;
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
        .event-img .img-overlay {
            position: absolute; inset: 0; display: flex;
            align-items: center; justify-content: center;
        }
        .event-img .img-overlay i { font-size: 3rem; opacity: 0.25; }
        .event-img .img-overlay i.garden-icon { color: var(--forest); }
        .event-img .img-overlay i.summit-icon { color: var(--forest); }
        .event-img .img-overlay i.gala-icon { color: var(--forest); }
        .event-img .img-overlay i.charity-icon { color: var(--forest); }
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
            <!-- Card 1 -->
            <article class="event-card">
                <div class="event-img">
                    <div class="img-bg garden"></div>
                    <div class="img-overlay"><i class="fas fa-leaf garden-icon"></i></div>
                    <span class="event-badge">Wedding</span>
                </div>
                <div class="event-content">
                    <h3>Spring Garden Wedding</h3>
                    <p>An enchanting outdoor celebration surrounded by blooming florals and soft natural light.</p>
                    <div class="event-meta">
                        <div class="meta-item"><i class="far fa-calendar-alt"></i><span>May 16, 2026</span></div>
                        <div class="meta-item"><i class="far fa-clock"></i><span>3:00 PM — 10:00 PM</span></div>
                        <div class="meta-item"><i class="fas fa-map-marker-alt"></i><span>Rosewood Estate, Napa Valley</span></div>
                    </div>
                </div>
            </article>

            <!-- Card 2 -->
            <article class="event-card">
                <div class="event-img">
                    <div class="img-bg summit"></div>
                    <div class="img-overlay"><i class="fas fa-briefcase summit-icon"></i></div>
                    <span class="event-badge">Corporate</span>
                </div>
                <div class="event-content">
                    <h3>Corporate Summit 2026</h3>
                    <p>A refined gathering of industry leaders featuring keynote sessions and curated networking experiences.</p>
                    <div class="event-meta">
                        <div class="meta-item"><i class="far fa-calendar-alt"></i><span>September 8, 2026</span></div>
                        <div class="meta-item"><i class="far fa-clock"></i><span>9:00 AM — 6:00 PM</span></div>
                        <div class="meta-item"><i class="fas fa-map-marker-alt"></i><span>Grand Pavilion, San Francisco</span></div>
                    </div>
                </div>
            </article>

            <!-- Card 3 -->
            <article class="event-card">
                <div class="event-img">
                    <div class="img-bg gala"></div>
                    <div class="img-overlay"><i class="fas fa-glass-cheers gala-icon"></i></div>
                    <span class="event-badge">Gala</span>
                </div>
                <div class="event-content">
                    <h3>Evening Gala Night</h3>
                    <p>An elegant black-tie evening filled with fine dining, live music, and unforgettable entertainment.</p>
                    <div class="event-meta">
                        <div class="meta-item"><i class="far fa-calendar-alt"></i><span>December 12, 2026</span></div>
                        <div class="meta-item"><i class="far fa-clock"></i><span>6:00 PM — 12:00 AM</span></div>
                        <div class="meta-item"><i class="fas fa-map-marker-alt"></i><span>The Plaza Hotel, New York</span></div>
                    </div>
                </div>
            </article>

            <!-- Card 4 -->
            <article class="event-card">
                <div class="event-img">
                    <div class="img-bg charity"></div>
                    <div class="img-overlay"><i class="fas fa-hand-holding-heart charity-icon"></i></div>
                    <span class="event-badge">Charity</span>
                </div>
                <div class="event-content">
                    <h3>Charity Fundraiser</h3>
                    <p>A heartwarming community event bringing people together for a meaningful cause and positive impact.</p>
                    <div class="event-meta">
                        <div class="meta-item"><i class="far fa-calendar-alt"></i><span>October 18, 2026</span></div>
                        <div class="meta-item"><i class="far fa-clock"></i><span>11:00 AM — 4:00 PM</span></div>
                        <div class="meta-item"><i class="fas fa-map-marker-alt"></i><span>Community Center, Austin</span></div>
                    </div>
                </div>
            </article>
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

    <script>
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
</body>
</html>

