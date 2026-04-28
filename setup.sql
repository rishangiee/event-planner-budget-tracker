CREATE DATABASE eventify;
USE eventify;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    lastname VARCHAR(255) NOT NULL,
    firstname VARCHAR(255) NOT NULL,
    middlename VARCHAR(255) DEFAULT NULL,
    suffix VARCHAR(50) DEFAULT NULL,
    address TEXT,
    contact_number VARCHAR(50),
    role ENUM('user','admin') DEFAULT 'user',
    photo VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Additional columns for customer info (run these if your DB already exists)
-- ALTER TABLE events ADD COLUMN description TEXT AFTER attendees;
-- ALTER TABLE events ADD COLUMN customer_name VARCHAR(255) AFTER description;
-- ALTER TABLE events ADD COLUMN customer_contact VARCHAR(50) AFTER customer_name;
-- ALTER TABLE events ADD COLUMN customer_address TEXT AFTER customer_contact;

CREATE TABLE events (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    date DATE NOT NULL,
    budget DECIMAL(10,2) NOT NULL,
    attendees INT NOT NULL,
    max_attendees INT DEFAULT 200,
    status ENUM('planned', 'ongoing', 'completed', 'cancelled') DEFAULT 'planned',
    description TEXT,
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