CREATE DATABASE IF NOT EXISTS eventify;
USE eventify;

DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) DEFAULT NULL,
    lastname VARCHAR(255) DEFAULT NULL,
    firstname VARCHAR(255) DEFAULT NULL,
    middlename VARCHAR(255) DEFAULT NULL,
    suffix VARCHAR(50) DEFAULT NULL,
    address TEXT,
    contact_number VARCHAR(50),
    role ENUM('user','admin') DEFAULT 'user',
    photo VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE events (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    time TIME DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    description TEXT,
    budget DECIMAL(10,2) DEFAULT 0,
    attendees INT DEFAULT 0,
    max_attendees INT DEFAULT 200,
    image VARCHAR(255) DEFAULT NULL,
    status ENUM('planned', 'ongoing', 'completed', 'cancelled') DEFAULT 'planned',
    customer_name VARCHAR(255),
    customer_contact VARCHAR(50),
    customer_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE expenses (
    id VARCHAR(50) PRIMARY KEY,
    event_id VARCHAR(50),
    category VARCHAR(100),
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id)
);

-- Demo admin (Email: admin@eventplanner.com, Password: Admin123456)
DELETE FROM users WHERE email = 'admin@eventplanner.com';
INSERT INTO users (email, password, role, name) VALUES 
('admin@eventplanner.com', '$2y$10$1KR3H5iMJu0XuNDpUBN6KOWA0XNA1n0VGNJqT4gJ6aO5j7b8s9t0.', 'admin', 'Demo Admin');
