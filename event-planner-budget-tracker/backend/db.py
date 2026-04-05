import mysql.connector

def get_connection():
    return mysql.connector.connect(
        host="localhost",
        user="root",        # default XAMPP user
        password="",        # default XAMPP password is empty
        database="cavendia_db"
    )
