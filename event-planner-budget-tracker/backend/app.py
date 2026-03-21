from flask import Flask, request, jsonify, session
from flask_cors import CORS
from werkzeug.security import generate_password_hash, check_password_hash
import jwt
import datetime
from functools import wraps
import traceback

from config import Config
from database_helper import DatabaseHelper
from email_notification import EmailNotification

app = Flask(__name__)
app.config.from_object(Config)

# CORS for frontend
CORS(app, supports_credentials=True)

# Initialize database and email
db = DatabaseHelper(
    host=app.config['MYSQL_HOST'],
    user=app.config['MYSQL_USER'],
    password=app.config['MYSQL_PASSWORD'],
    database=app.config['MYSQL_DB']
)

email_service = EmailNotification(app.config)

# JWT Helper
def generate_token(user_id):
    payload = {
        'user_id': user_id,
        'exp': datetime.datetime.utcnow() + datetime.timedelta(hours=24)
    }
    return jwt.encode(payload, app.config['JWT_SECRET_KEY'], algorithm='HS256')

def verify_token(token):
    try:
        payload = jwt.decode(token, app.config['JWT_SECRET_KEY'], algorithms=['HS256'])
        return payload['user_id']
    except:
        return None

def token_required(f):
    @wraps(f)
    def decorated(*args, **kwargs):
        token = request.headers.get('Authorization')
        if not token:
            return jsonify({'message': 'Token missing'}), 401
        user_id = verify_token(token.replace('Bearer ', ''))
        if not user_id:
            return jsonify({'message': 'Invalid token'}), 401
        return f(user_id, *args, **kwargs)
    return decorated

@app.route('/api/auth/login', methods=['POST'])
def login():
    try:
        data = request.get_json()
        email = data.get('email')
        password = data.get('password')
        
        user = db.get_user_by_email(email)
        if user and check_password_hash(user['password'], password):
            token = generate_token(user['id'])
            return jsonify({
                'message': 'Login successful',
                'token': token,
                'user': {
                    'id': user['id'],
                    'name': user['name'],
                    'email': user['email'],
                    'role': user['role']
                }
            }), 200
        return jsonify({'message': 'Invalid credentials'}), 401
    except Exception as e:
        return jsonify({'message': str(e)}), 500

@app.route('/api/auth/register', methods=['POST'])
def register():
    try:
        data = request.get_json()
        name = data.get('name')
        email = data.get('email')
        password = data.get('password')
        phone = data.get('phone', '')
        
        # Check if user exists
        if db.get_user_by_email(email):
            return jsonify({'message': 'User already exists'}), 400
        
        user_id = db.create_user(name, email, password, phone)
        token = generate_token(user_id)
        
        email_service.send_welcome_email(email, name)
        
        return jsonify({
            'message': 'Registration successful',
            'token': token,
            'user': {'id': user_id, 'name': name, 'email': email}
        }), 201
    except Exception as e:
        return jsonify({'message': str(e)}), 500

# Events API
@app.route('/api/events', methods=['GET'])
@token_required
def get_events(user_id):
    try:
        events = db.get_events(user_id)
        return jsonify(events), 200
    except Exception as e:
        return jsonify({'message': str(e)}), 500

@app.route('/api/events', methods=['POST'])
@token_required
def create_event(user_id):
    try:
        data = request.get_json()
        event_id = db.create_event(data, user_id)
        event = db.get_event(event_id)
        return jsonify(event), 201
    except Exception as e:
        return jsonify({'message': str(e)}), 500

@app.route('/api/events/<int:event_id>', methods=['GET', 'PUT', 'DELETE'])
@token_required
def event_detail(user_id, event_id):
    if request.method == 'GET':
        event = db.get_event(event_id)
        if not event:
            return jsonify({'message': 'Event not found'}), 404
        return jsonify(event), 200
    
    elif request.method == 'PUT':
        data = request.get_json()
        success = db.update_event(event_id, data, user_id)
        if success:
            return jsonify({'message': 'Event updated'}), 200
        return jsonify({'message': 'Event not found'}), 404
    
    elif request.method == 'DELETE':
        success = db.delete_event(event_id, user_id)
        if success:
            return jsonify({'message': 'Event deleted'}), 200
        return jsonify({'message': 'Event not found'}), 404

# Expenses API
@app.route('/api/expenses', methods=['GET'])
@token_required
def get_expenses(user_id):
    try:
        expenses = db.get_expenses(user_id)
        return jsonify(expenses), 200
    except Exception as e:
        return jsonify({'message': str(e)}), 500

@app.route('/api/expenses', methods=['POST'])
@token_required
def create_expense(user_id):
    try:
        data = request.get_json()
        expense_id = db.create_expense(data, user_id)
        expense = db.get_expense(expense_id)
        return jsonify(expense), 201
    except Exception as e:
        return jsonify({'message': str(e)}), 500

@app.route('/api/expenses/<int:expense_id>', methods=['PUT', 'DELETE'])
@token_required
def expense_detail(user_id, expense_id):
    if request.method == 'PUT':
        data = request.get_json()
        success = db.update_expense(expense_id, data, user_id)
        if success:
            return jsonify({'message': 'Expense updated'}), 200
        return jsonify({'message': 'Expense not found'}), 404
    
    elif request.method == 'DELETE':
        success = db.delete_expense(expense_id, user_id)
        if success:
            return jsonify({'message': 'Expense deleted'}), 200
        return jsonify({'message': 'Expense not found'}), 404

# Budget Summary API
@app.route('/api/dashboard/summary', methods=['GET'])
@token_required
def get_dashboard_summary(user_id):
    try:
        summary = db.get_dashboard_summary(user_id)
        return jsonify(summary), 200
    except Exception as e:
        return jsonify({'message': str(e)}), 500

# Categories API
@app.route('/api/categories', methods=['GET'])
@token_required
def get_categories(user_id):
    try:
        categories = db.get_categories()
        return jsonify(categories), 200
    except Exception as e:
        return jsonify({'message': str(e)}), 500

# Users API (Admin only)
@app.route('/api/users', methods=['GET'])
@token_required
def get_users(user_id):
    try:
        user = db.get_user(user_id)
        if user['role'] != 'admin':
            return jsonify({'message': 'Admin access required'}), 403
        users = db.get_all_users()
        return jsonify(users), 200
    except Exception as e:
        return jsonify({'message': str(e)}), 500

@app.route('/api/profile', methods=['GET', 'PUT'])
@token_required
def profile(user_id):
    if request.method == 'GET':
        user = db.get_user(user_id)
        return jsonify(user), 200
    else:
        data = request.get_json()
        success = db.update_profile(user_id, data)
        if success:
            return jsonify({'message': 'Profile updated'}), 200
        return jsonify({'message': 'Update failed'}), 400

@app.errorhandler(500)
def internal_error(error):
    return jsonify({'message': 'Internal server error'}), 500

if __name__ == '__main__':
    with app.app_context():
        db.init_database()
    app.run(debug=True, host='0.0.0.0', port=5000)