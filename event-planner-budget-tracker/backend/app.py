from flask import Flask, request, jsonify, send_from_directory, render_template
from flask_cors import CORS
import os
from database_helper import db_connection
from email_notification import send_reminder_email

app = Flask(__name__, static_folder='../css', template_folder='../frontend')
CORS(app)

# Configuration
app.config['SECRET_KEY'] = 'your-secret-key-change-in-production'
app.config['UPLOAD_FOLDER'] = 'uploads'

# Ensure upload folder exists
os.makedirs(app.config['UPLOAD_FOLDER'], exist_ok=True)

# --- Static File Routes ---
@app.route('/css/<path:filename>')
def serve_css(filename):
    return send_from_directory('../css', filename)

@app.route('/js/<path:filename>')
def serve_js(filename):
    return send_from_directory('../js', filename)

# --- HTML Routes ---
@app.route('/')
def index():
    return send_from_directory('../frontend', 'index.html')

@app.route('/login')
def login():
    return send_from_directory('../frontend', 'login.html')

@app.route('/register')
def register():
    return send_from_directory('../frontend', 'register.html')

@app.route('/calendar')
def calendar():
    return send_from_directory('../frontend', 'calendar.html')

@app.route('/budget')
def budget():
    return send_from_directory('../frontend', 'budget.html')

# --- API Routes ---

# Auth
@app.route('/api/auth/login', methods=['POST'])
def login_user():
    data = request.get_json()
    conn = db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT * FROM users WHERE email = ? AND password = ?", 
                       (data['email'], data['password'])) # Note: Use hashed passwords in production
        user = cursor.fetchone()
        if user:
            return jsonify({'message': 'Login successful', 'user_id': user[0]}), 200
        return jsonify({'error': 'Invalid credentials'}), 401
    except Exception as e:
        return jsonify({'error': str(e)}), 500
    finally:
        conn.close()

@app.route('/api/auth/register', methods=['POST'])
def register_user():
    data = request.get_json()
    conn = db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("INSERT INTO users (name, email, password) VALUES (?, ?, ?)",
                       (data['name'], data['email'], data['password']))
        conn.commit()
        return jsonify({'message': 'User registered successfully'}), 201
    except Exception as e:
        return jsonify({'error': str(e)}), 500
    finally:
        conn.close()

# Events
@app.route('/api/events', methods=['GET'])
def get_events():
    conn = db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT * FROM events")
        events = cursor.fetchall()
        return jsonify({'events': events}), 200
    except Exception as e:
        return jsonify({'error': str(e)}), 500
    finally:
        conn.close()

@app.route('/api/events', methods=['POST'])
def create_event():
    data = request.get_json()
    conn = db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("INSERT INTO events (name, date, location) VALUES (?, ?, ?)",
                       (data['name'], data['date'], data['location']))
        conn.commit()
        # Send email notification
        send_reminder_email(data['email'], data['name'])
        return jsonify({'message': 'Event created'}), 201
    except Exception as e:
        return jsonify({'error': str(e)}), 500
    finally:
        conn.close()

# Budget
@app.route('/api/budget', methods=['GET'])
def get_budget():
    conn = db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("SELECT * FROM budgets")
        budgets = cursor.fetchall()
        return jsonify({'budgets': budgets}), 200
    except Exception as e:
        return jsonify({'error': str(e)}), 500
    finally:
        conn.close()

@app.route('/api/budget', methods=['POST'])
def create_budget():
    data = request.get_json()
    conn = db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute("INSERT INTO budgets (event_id, total, spent) VALUES (?, ?, ?)",
                       (data['event_id'], data['total'], data['spent']))
        conn.commit()
        return jsonify({'message': 'Budget created'}), 201
    except Exception as e:
        return jsonify({'error': str(e)}), 500
    finally:
        conn.close()

if __name__ == '__main__':
    app.run(debug=True, port=5000)