from flask import Flask, request, render_template
from budget_service import add_budget_item, get_budget_items

app = Flask(__name__)

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
    app.run(debug=True)
