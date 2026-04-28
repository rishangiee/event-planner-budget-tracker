-- Run this in phpMyAdmin or MySQL to add missing columns to existing events table
-- Go to http://localhost/phpmyadmin, select the eventify database, click SQL tab, and run these commands

USE eventify;

-- Add max_attendees column (safe for existing data)
ALTER TABLE events 
ADD COLUMN max_attendees INT DEFAULT 200 AFTER attendees;

-- Add description column (safe for existing data)
ALTER TABLE events 
ADD COLUMN description TEXT AFTER status;

-- Add photo column to users table for profile picture
ALTER TABLE users 
ADD COLUMN photo VARCHAR(255) DEFAULT NULL AFTER role;

-- Add new user profile columns for expanded registration
ALTER TABLE users
ADD COLUMN lastname VARCHAR(255) NOT NULL AFTER name,
ADD COLUMN firstname VARCHAR(255) NOT NULL AFTER lastname,
ADD COLUMN middlename VARCHAR(255) DEFAULT NULL AFTER firstname,
ADD COLUMN suffix VARCHAR(50) DEFAULT NULL AFTER middlename,
ADD COLUMN address TEXT AFTER suffix,
ADD COLUMN contact_number VARCHAR(50) AFTER address;

-- Add updated_at timestamp to track profile changes
ALTER TABLE users
ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Verify columns
DESCRIBE events;
DESCRIBE users;

