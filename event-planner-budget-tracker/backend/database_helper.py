import mysql.connector
from mysql.connector import Error
from werkzeug.security import generate_password_hash
import json
from datetime import datetime

class DatabaseHelper:
    def __init__(self, host, user, password, database):
        self.config = {
            'host': host,
            'user': user,
            'password': password,
            'database': database,
            'autocommit': True
        }
    
    def get_connection(self):
        try:
            return mysql.connector.connect(**self.config)
        except Error as e:
            print(f"Error connecting to MySQL: {e}")
            return None
    
    def init_database(self):
        """Create tables if they don't exist"""
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor()
            
            # Users table
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(255) NOT NULL,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    phone VARCHAR(20),
                    role ENUM('admin', 'planner', 'client') DEFAULT 'planner',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """)
            
            # Categories table
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS categories (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            """)
            
            # Events table
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    name VARCHAR(255) NOT NULL,
                    event_date DATE NOT NULL,
                    venue VARCHAR(255),
                    budget DECIMAL(10,2) DEFAULT 0,
                    status ENUM('upcoming', 'ongoing', 'completed') DEFAULT 'upcoming',
                    description TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                )
            """)
            
            # Expenses table
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS expenses (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    event_id INT,
                    category_id INT,
                    description VARCHAR(255) NOT NULL,
                    amount DECIMAL(10,2) NOT NULL,
                    expense_date DATE DEFAULT CURRENT_TIMESTAMP,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (event_id) REFERENCES events(id),
                    FOREIGN KEY (category_id) REFERENCES categories(id)
                )
            """)
            
            # Insert default categories
            default_categories = [
                ('Venue', 'Venue rental and booking'),
                ('Catering', 'Food and beverages'),
                ('Decoration', 'Event decoration and setup'),
                ('Entertainment', 'DJ, band, performers'),
                ('Marketing', 'Promotion and advertising')
            ]
            
            cursor.executemany("INSERT IGNORE INTO categories (name, description) VALUES (%s, %s)", default_categories)
            conn.commit()
            cursor.close()
            conn.close()
            print("Database initialized successfully!")
    
    def get_user(self, user_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM users WHERE id = %s", (user_id,))
            user = cursor.fetchone()
            cursor.close()
            conn.close()
            return user
        return None
    
    def get_user_by_email(self, email):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM users WHERE email = %s", (email,))
            user = cursor.fetchone()
            cursor.close()
            conn.close()
            return user
        return None
    
    def create_user(self, name, email, password, phone=''):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor()
            hashed_password = generate_password_hash(password)
            cursor.execute(
                "INSERT INTO users (name, email, password, phone) VALUES (%s, %s, %s, %s)",
                (name, email, hashed_password, phone)
            )
            user_id = cursor.lastrowid
            cursor.close()
            conn.close()
            return user_id
        return None
    
    def get_all_users(self):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC")
            users = cursor.fetchall()
            cursor.close()
            conn.close()
            return users
        return []
    
    def update_profile(self, user_id, data):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor()
            query = "UPDATE users SET "
            params = []
            
            updates = []
            if 'name' in data:
                updates.append("name = %s")
                params.append(data['name'])
            if 'phone' in data:
                updates.append("phone = %s")
                params.append(data['phone'])
            
            if updates:
                query += ", ".join(updates) + " WHERE id = %s"
                params.append(user_id)
                cursor.execute(query, params)
                cursor.close()
                conn.close()
                return cursor.rowcount > 0
        return False
    
    def get_events(self, user_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT e.*, u.name as organizer 
                FROM events e 
                JOIN users u ON e.user_id = u.id 
                WHERE e.user_id = %s 
                ORDER BY e.event_date DESC
            """, (user_id,))
            events = cursor.fetchall()
            cursor.close()
            conn.close()
            return events
        return []
    
    def get_event(self, event_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT e.*, u.name as organizer 
                FROM events e 
                JOIN users u ON e.user_id = u.id 
                WHERE e.id = %s
            """, (event_id,))
            event = cursor.fetchone()
            cursor.close()
            conn.close()
            return event
        return None
    
    def create_event(self, data, user_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor()
            cursor.execute("""
                INSERT INTO events (user_id, name, event_date, venue, budget, status, description)
                VALUES (%s, %s, %s, %s, %s, %s, %s)
            """, (
                user_id, data['name'], data['event_date'], 
                data.get('venue'), data.get('budget', 0),
                data.get('status', 'upcoming'), data.get('description', '')
            ))
            event_id = cursor.lastrowid
            cursor.close()
            conn.close()
            return event_id
        return None
    
    def update_event(self, event_id, data, user_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor()
            query = "UPDATE events SET "
            params = []
            updates = []
            
            if 'name' in data:
                updates.append("name = %s")
                params.append(data['name'])
            if 'event_date' in data:
                updates.append("event_date = %s")
                params.append(data['event_date'])
            if 'venue' in data:
                updates.append("venue = %s")
                params.append(data['venue'])
            if 'budget' in data:
                updates.append("budget = %s")
                params.append(data['budget'])
            if 'status' in data:
                updates.append("status = %s")
                params.append(data['status'])
            if 'description' in data:
                updates.append("description = %s")
                params.append(data['description'])
            
            if updates:
                query += ", ".join(updates) + " WHERE id = %s AND user_id = %s"
                params.extend([event_id, user_id])
                cursor.execute(query, params)
                result = cursor.rowcount > 0
                cursor.close()
                conn.close()
                return result
        return False
    
    def delete_event(self, event_id, user_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor()
            # Delete related expenses first
            cursor.execute("DELETE FROM expenses WHERE event_id = %s", (event_id,))
            cursor.execute("DELETE FROM events WHERE id = %s AND user_id = %s", (event_id, user_id))
            result = cursor.rowcount > 0
            cursor.close()
            conn.close()
            return result
        return False
    
    def get_expenses(self, user_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT ex.*, e.name as event_name, c.name as category_name
                FROM expenses ex
                JOIN events e ON ex.event_id = e.id
                JOIN categories c ON ex.category_id = c.id
                WHERE e.user_id = %s
                ORDER BY ex.expense_date DESC
            """, (user_id,))
            expenses = cursor.fetchall()
            cursor.close()
            conn.close()
            return expenses
        return []
    
    def get_expense(self, expense_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("""
                SELECT ex.*, e.name as event_name, c.name as category_name
                FROM expenses ex
                JOIN events e ON ex.event_id = e.id
                JOIN categories c ON ex.category_id = c.id
                                WHERE ex.id = %s
            """, (expense_id,))
            expense = cursor.fetchone()
            cursor.close()
            conn.close()
            return expense
        return None
    
    def create_expense(self, data, user_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor()
            cursor.execute("""
                INSERT INTO expenses (event_id, category_id, description, amount, expense_date)
                VALUES (%s, %s, %s, %s, %s)
            """, (
                data['event_id'], data['category_id'], 
                data['description'], data['amount'],
                data.get('expense_date', datetime.now().date())
            ))
            expense_id = cursor.lastrowid
            cursor.close()
            conn.close()
            return expense_id
        return None
    
    def update_expense(self, expense_id, data, user_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor()
            query = "UPDATE expenses SET "
            params = []
            updates = []
            
            if 'description' in data:
                updates.append("description = %s")
                params.append(data['description'])
            if 'amount' in data:
                updates.append("amount = %s")
                params.append(data['amount'])
            if 'expense_date' in data:
                updates.append("expense_date = %s")
                params.append(data['expense_date'])
            if 'category_id' in data:
                updates.append("category_id = %s")
                params.append(data['category_id'])
            
            if updates:
                query += ", ".join(updates) + " WHERE id = %s AND event_id IN (SELECT id FROM events WHERE user_id = %s)"
                params.extend([expense_id, user_id])
                cursor.execute(query, params)
                result = cursor.rowcount > 0
                cursor.close()
                conn.close()
                return result
        return False
    
    def delete_expense(self, expense_id, user_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor()
            cursor.execute("""
                DELETE ex FROM expenses ex
                JOIN events e ON ex.event_id = e.id
                WHERE ex.id = %s AND e.user_id = %s
            """, (expense_id, user_id))
            result = cursor.rowcount > 0
            cursor.close()
            conn.close()
            return result
        return False
    
    def get_dashboard_summary(self, user_id):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor(dictionary=True)
            
            # Total events
            cursor.execute("SELECT COUNT(*) as total_events FROM events WHERE user_id = %s", (user_id,))
            total_events = cursor.fetchone()['total_events']
            
            # Total budget and spent
            cursor.execute("""
                SELECT 
                    COALESCE(SUM(e.budget), 0) as total_budget,
                    COALESCE(SUM(ex.amount), 0) as total_spent
                FROM events e
                LEFT JOIN expenses ex ON e.id = ex.event_id
                WHERE e.user_id = %s
            """, (user_id,))
            budget_data = cursor.fetchone()
            
            # Expenses by category
            cursor.execute("""
                SELECT c.name, COALESCE(SUM(ex.amount), 0) as total
                FROM categories c
                LEFT JOIN expenses ex ON c.id = ex.category_id
                JOIN events e ON ex.event_id = e.id
                WHERE e.user_id = %s
                GROUP BY c.id, c.name
                ORDER BY total DESC
            """, (user_id,))
            category_expenses = cursor.fetchall()
            
            cursor.close()
            conn.close()
            
            return {
                'total_events': total_events,
                'total_budget': float(budget_data['total_budget']),
                'total_spent': float(budget_data['total_spent']),
                'remaining_budget': float(budget_data['total_budget'] - budget_data['total_spent']),
                'budget_percentage': 0 if budget_data['total_budget'] == 0 else 
                                   (budget_data['total_spent'] / budget_data['total_budget']) * 100,
                'category_expenses': category_expenses
            }
        return {}
    
    def get_categories(self):
        conn = self.get_connection()
        if conn:
            cursor = conn.cursor(dictionary=True)
            cursor.execute("SELECT * FROM categories ORDER BY name")
            categories = cursor.fetchall()
            cursor.close()
            conn.close()
            return categories
        return []