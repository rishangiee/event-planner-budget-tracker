from flask import render_template
from flask_mail import Mail, Message
import os

class EmailNotification:
    def __init__(self, config):
        self.mail = Mail()
        self.mail.init_app(app)  # app needs to be passed from app.py
        
    def send_welcome_email(self, email, name):
        msg = Message(
            subject='Welcome to Event Planner!',
            recipients=[email],
            html=render_template('email_template.html', 
                               name=name, 
                               action='welcome')
        )
        self.mail.send(msg)
    
    def send_budget_alert(self, email, name, event_name, budget, spent):
        remaining = budget - spent
        msg = Message(
            subject=f'Budget Alert: {event_name}',
            recipients=[email],
            html=render_template('email_template.html',
                               name=name,
                               event_name=event_name,
                               budget=budget,
                               spent=spent,
                               remaining=remaining,
                               action='budget_alert')
        )
        self.mail.send(msg)
    
    def send_event_reminder(self, email, name, event_name, event_date):
        msg = Message(
            subject=f'Reminder: {event_name} is approaching!',
            recipients=[email],
            html=render_template('email_template.html',
                               name=name,
                               event_name=event_name,
                               event_date=event_date,
                               action='event_reminder')
        )
        self.mail.send(msg)