<?php
/**
 * Database Setup Script for CAVENDIA Event Planner
 * Run this file once to create the required tables
 */

require_once 'config/config.php';

echo "<h2>Setting up CAVENDIA Database...</h2>";

try {
    // Create bookings table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS bookings (
            id VARCHAR(50) PRIMARY KEY,
            user_id INT NOT NULL,
            event_id VARCHAR(50) NOT NULL,
            event_title VARCHAR(255) NOT NULL,
            event_date DATE NOT NULL,
            event_location VARCHAR(255),
            guest_count INT DEFAULT 1,
            status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p>✓ Bookings table created</p>";
    
    // Create messages table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            message TEXT NOT NULL,
            sender_type ENUM('user', 'admin') DEFAULT 'user',
            sender_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p>✓ Messages table created</p>";
    
    // Create admin user if not exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'admin'");
    $stmt->execute(['admin@cavendia.com']);
    
    if (!$stmt->fetch()) {
        // Create admin (same credentials as admin_login.php uses)
        $adminPassword = password_hash('admin_demo2026', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password, name, role) VALUES (?, ?, 'Admin', 'admin')");
        $stmt->execute(['admin@cavendia.com', $adminPassword]);
        echo "<p>✓ Admin user created (admin@cavendia.com / admin_demo2026)</p>";
    } else {
        echo "<p>✓ Admin user already exists</p>";
    }
    
    // Add some sample events if none exist
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
        echo "<p>✓ Added " . count($sampleEvents) . " sample events</p>";
    } else {
        echo "<p>✓ Events already exist ($eventCount total)</p>";
    }
    
    echo "<h3 style='color:green;'>Database setup complete!</h3>";
    echo "<p><a href='index.php'>Go to Homepage</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
