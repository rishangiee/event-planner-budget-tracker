-- Run this in phpMyAdmin or MySQL to add missing columns to existing events table
-- Go to http://localhost/phpmyadmin, select the eventify database, click SQL tab, and run these commands

USE eventify;

-- Add existing column updates (safe for existing data)
ALTER TABLE events 
ADD COLUMN IF NOT EXISTS max_attendees INT DEFAULT 200;

ALTER TABLE events 
ADD COLUMN IF NOT EXISTS description TEXT;

ALTER TABLE events
ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT NULL;

-- Add photo column to users table for profile picture
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS photo VARCHAR(255) DEFAULT NULL;

-- Add new user profile columns for expanded registration
ALTER TABLE users
ADD COLUMN IF NOT EXISTS lastname VARCHAR(255),
ADD COLUMN IF NOT EXISTS firstname VARCHAR(255),
ADD COLUMN IF NOT EXISTS middlename VARCHAR(255) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS suffix VARCHAR(50) DEFAULT NULL,
ADD COLUMN IF NOT EXISTS address TEXT,
ADD COLUMN IF NOT EXISTS contact_number VARCHAR(50);

-- Add updated_at timestamp to track profile changes
ALTER TABLE users
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ==================== NEW TABLES ====================

-- Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_id VARCHAR(50) NOT NULL,
    event_title VARCHAR(255) NOT NULL,
    event_date DATE NOT NULL,
    event_location VARCHAR(255),
    guest_count INT DEFAULT 1,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (event_id) REFERENCES events(id)
);

-- Messages table for chat system
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    admin_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    sender ENUM('user', 'admin') DEFAULT 'user',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Verify columns
DESCRIBE events;
DESCRIBE users;
DESCRIBE bookings;
DESCRIBE messages;

