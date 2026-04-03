from db import get_connection

def add_budget_item(category, amount, notes):
    conn = get_connection()
    cursor = conn.cursor()
    sql = "INSERT INTO budget_items (category, amount, notes) VALUES (%s, %s, %s)"
    cursor.execute(sql, (category, amount, notes))
    conn.commit()
    cursor.close()
    conn.close()
    print("Budget item added!")

def get_budget_items():
    conn = get_connection()
    cursor = conn.cursor(dictionary=True)
    cursor.execute("SELECT * FROM budget_items")
    rows = cursor.fetchall()
    cursor.close()
    conn.close()
    return rows
