from flask import Flask, request, render_template, jsonify
from flask_cors import CORS
from budget_service import add_budget_item, get_budget_items
import sqlite3
import hashlib
from datetime import datetime

app = Flask(__name__)
CORS(app)  # Enable CORS for frontend requests

# Database configuration
DATABASE = 'event_planner.db'

def get_db():
    """Get database connection"""
    conn = sqlite3.connect(DATABASE)
    conn.row_factory = sqlite3.Row
    return conn

def init_db():
    """Initialize database with users table"""
    conn = get_db()
    c = conn.cursor()
    c.execute('''
        CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ''')
    conn.commit()
    conn.close()

def hash_password(password):
    """Hash password using SHA256"""
    return hashlib.sha256(password.encode()).hexdigest()

@app.route("/login", methods=["POST"])
def login():
    """Handle user login"""
    try:
        data = request.get_json()
        email = data.get('email')
        password = data.get('password')
        role = data.get('role')

        if not email or not password or not role:
            return jsonify({'message': 'Email, password, and role are required'}), 400

        # Query user from database
        conn = get_db()
        c = conn.cursor()
        c.execute('SELECT * FROM users WHERE email = ? AND role = ?', (email, role))
        user = c.fetchone()
        conn.close()

        if user is None:
            return jsonify({'message': 'Invalid email or password'}), 401

        # Verify password
        if user['password'] != hash_password(password):
            return jsonify({'message': 'Invalid email or password'}), 401

        # Login successful
        return jsonify({
            'message': 'Login successful',
            'token': f'token_{user["user_id"]}_{datetime.now().timestamp()}',
            'user': {
                'user_id': user['user_id'],
                'full_name': user['full_name'],
                'email': user['email'],
                'role': user['role']
            }
        }), 200

    except Exception as e:
        return jsonify({'message': f'Server error: {str(e)}'}), 500

@app.route("/register", methods=["POST"])
def register():
    """Handle user registration"""
    try:
        data = request.get_json()
        full_name = data.get('full_name')
        email = data.get('email')
        password = data.get('password')
        role = data.get('role')

        if not all([full_name, email, password, role]):
            return jsonify({'message': 'All fields are required'}), 400

        # Hash password
        hashed_password = hash_password(password)

        # Insert user into database
        conn = get_db()
        c = conn.cursor()
        try:
            c.execute('''
                INSERT INTO users (full_name, email, password, role)
                VALUES (?, ?, ?, ?)
            ''', (full_name, email, hashed_password, role))
            conn.commit()
            user_id = c.lastrowid
            conn.close()

            return jsonify({
                'message': 'Registration successful',
                'user_id': user_id,
                'email': email
            }), 201

        except sqlite3.IntegrityError:
            conn.close()
            return jsonify({'message': 'Email already exists'}), 409

    except Exception as e:
        return jsonify({'message': f'Server error: {str(e)}'}), 500

@app.route("/budget", methods=["GET", "POST"])
def budget():
    if request.method == "POST":
        category = request.form["category"]
        amount = request.form["amount"]
        notes = request.form["notes"]
        add_budget_item(category, amount, notes)
        return "Budget item added successfully!"
    items = get_budget_items()
    return render_template("budget.html", items=items)

if __name__ == "__main__":
    init_db()
    app.run(debug=True)
