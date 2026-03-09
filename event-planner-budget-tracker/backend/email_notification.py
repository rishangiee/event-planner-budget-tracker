import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart

# Configuration
SMTP_SERVER = "smtp.gmail.com"
SMTP_PORT = 587
SMTP_USER = "your_email@gmail.com"
SMTP_PASSWORD = "your_app_password"

def send_reminder_email(to_email, event_name):
    """Sends a reminder email for an event."""
    try:
        msg = MIMEMultipart()
        msg['From'] = SMTP_USER
        msg['To'] = to_email
        msg['Subject'] = f"Event Reminder: {event_name}"
        
        body = f"""
        Hello,
        
        This is a reminder about your upcoming event: {event_name}.
        Please ensure all preparations are complete.
        
        Best regards,
        Event Planner Team
        """
        
        msg.attach(MIMEText(body, 'plain'))
        
        server = smtplib.SMTP(SMTP_SERVER, SMTP_PORT)
        server.starttls()
        server.login(SMTP_USER, SMTP_PASSWORD)
        server.send_message(msg)
        server.quit()
        
        return True
    except Exception as e:
        print(f"Failed to send email: {e}")
        return False

def send_budget_alert(to_email, budget_status):
    """Sends an alert if budget is exceeded."""
    try:
        msg = MIMEMultipart()
        msg['From'] = SMTP_USER
        msg['To'] = to_email
        msg['Subject'] = "Budget Alert"
        
        body = f"""
        Hello,
        
        Your budget status is: {budget_status}.
        Please review your expenses.
        
        Best regards,
        Event Planner Team
        """
        
        msg.attach(MIMEText(body, 'plain'))
        
        server = smtplib.SMTP(SMTP_SERVER, SMTP_PORT)
        server.starttls()
        server.login(SMTP_USER, SMTP_PASSWORD)
        server.send_message(msg)
        server.quit()
        
        return True
    except Exception as e:
        print(f"Failed to send budget alert: {e}")
        return False