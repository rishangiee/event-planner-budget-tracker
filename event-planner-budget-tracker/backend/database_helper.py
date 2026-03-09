import sqlite3
import os

# Database file path
DB_PATH = os.path.join(os.path.dirname(__file__), '..', 'database', 'event_planner.db')

def db_connection():
    """Establishes a connection to the SQLite database."""
    try:
        conn = sqlite3.connect(DB_PATH)
        conn.row_factory = sqlite3.Row  # Access columns by name
        return conn
    except sqlite3.Error as e:
        print(f"Database connection error: {e}")
        return None

def execute_query(query, params=()):
    """Executes a query and returns results."""
    conn = db_connection()
    if not conn:
        return None
    try:
        cursor = conn.cursor()
        cursor.execute(query, params)
        conn.commit()
        return cursor
    except sqlite3.Error as e:
        print(f"Query execution error: {e}")
        return None
    finally:
        if conn:
            conn.close()

# Example helper functions (Adjust based on your schema)
def get_user_by_email(email):
    conn = db_connection()
    if not conn: return None
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM users WHERE email = ?", (email,))
    user = cursor.fetchone()
    conn.close()
    return user

def get_event_by_id(event_id):
    conn = db_connection()
    if not conn: return None
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM events WHERE id = ?", (event_id,))
    event = cursor.fetchone()
    conn.close()
    return event

def update_budget_spent(event_id, amount):
    conn = db_connection()
    if not conn: return False
    cursor = conn.cursor()
    cursor.execute("UPDATE budgets SET spent = spent + ? WHERE event_id = ?", (amount, event_id))
    conn.commit()
    conn.close()
    return True