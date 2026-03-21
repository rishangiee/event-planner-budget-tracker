// Sample data (replace with your API calls)
let events = [];
let expenses = [];
let categories = [
    {id: 1, name: 'Venue'},
    {id: 2, name: 'Catering'},
    {id: 3, name: 'Decoration'},
    {id: 4, name: 'Entertainment'},
    {id: 5, name: 'Marketing'}
];

let currentEventId = null;

// Navigation
document.querySelectorAll('.nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const target = link.getAttribute('href');
        showSection(target);
    });
});

function showSection(sectionId) {
    document.querySelectorAll('.container-fluid').forEach(section => {
        section.style.display = 'none';
    });
    document.getElementById(sectionId.replace('#', '')).style.display = 'block';
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    document.querySelector(`a[href="${sectionId}"]`).classList.add('active');
    
    if (sectionId === '#events') loadEvents();
    if (sectionId === '#budget') loadBudget();
}

// Dashboard initialization
document.addEventListener('DOMContentLoaded', function() {
    loadSampleData();
    updateDashboard();
    loadEvents();
    initCharts();
});

// Sample data loader (replace with your PHP API)
function loadSampleData() {
    events = [
        {id: 1, name: 'Wedding Ceremony', date: '2024-03-15', budget: 50000, venue: 'Grand Hotel', status: 'ongoing'},
        {id: 2, name: 'Corporate Conference', date: '2024-04-20', budget: 25000, venue: 'Convention Center', status: 'upcoming'},
        {id: 3, name: 'Birthday Party', date: '2024-02-10', budget: 8000, venue: 'Community Hall', status: 'completed'}
    ];
    
    expenses = [
        {id: 1, event_id: 1, category_id: 1, description: 'Venue Rental', amount: 15000, date: '2024-03-01'},
        {id: 2, event_id: 1, category_id: 2, description: 'Catering Service', amount: 12000, date: '2024-03-05'},
        {id: 3, event_id: 2, category_id: 3, description: 'Conference Decor', amount: 5000, date: '2024-04-01'}
    ];
}

function updateDashboard() {
    const totalEvents = events.length;
    const totalBudget = events.reduce((sum, event) => sum + event.budget, 0);
    const totalSpent = expenses.reduce((sum, expense) => sum + expense.amount, 0);
    const remaining = totalBudget - totalSpent;

    document.getElementById('totalEvents').textContent = totalEvents;
    document.getElementById('totalBudget').textContent = formatCurrency(totalBudget);
    document.getElementById('totalSpent').textContent = formatCurrency(totalSpent);
    document.getElementById('remainingBudget').textContent = formatCurrency(remaining);

    const budgetPercent = totalBudget > 0 ? (totalSpent / totalBudget * 100) : 0;
    document.getElementById('budgetProgress').style.width = budgetPercent + '%';
    document.getElementById('budgetProgress').setAttribute('aria-valuenow', budgetPercent);
    document.getElementById('budgetPercent').textContent = Math.round(budgetPercent) + '%';
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

function loadEvents() {
    const eventsList = document.getElementById('eventsList');
    eventsList.innerHTML = '';

    events.forEach(event => {
        const spent = expenses.filter(e => e.event_id == event.id)
                             .reduce((sum, e) => sum + e.amount, 0);
        const remaining = event.budget - spent;
        
        const eventCard = `
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">${event.name}</h5>
                        <p class="card-text">
                            <i class="fas fa-calendar"></i> ${new Date(event.date).toLocaleDateString()}<br>
                            <i class="fas fa-map-marker-alt"></i> ${event.venue}
                        </p>
                        <div class="mb-3">
                            <small class="text-muted">Budget: ${formatCurrency(event.budget)}</small><br>
                            <small class="text-success">Spent: ${formatCurrency(spent)}</small><br>
                            <small class="text-primary">Remaining: ${formatCurrency(remaining)}</small>
                        </div>
                        <div class="progress mb-2" style="height: 8px;">
                            <div class="progress-bar" style="width: ${(spent/event.budget*100)}%"></div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" onclick="editEvent(${event.id})">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteEvent(${event.id})">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        eventsList.innerHTML += eventCard;
    });
}

function loadBudget() {
    // Update expense modal event dropdown
    const eventSelect = document.querySelector('#expenseForm select[name="event_id"]');
    if (eventSelect) {
        eventSelect.innerHTML = '<option value="">Select Event</option>';
        events.forEach(event => {
            eventSelect.innerHTML += `<option value="${event.id}">${event.name}</option>`;
        });
    }

    // Update category dropdown
    const categorySelect = document.querySelector('#expenseForm select[name="category_id"]');
    if (categorySelect) {
        categorySelect.innerHTML = '<option value="">Select Category</option>';
        categories.forEach(category => {
            categorySelect.innerHTML += `<option value="${category.id}">${category.name}</option>`;
        });
    }

    updateBudgetStats();
    loadRecentExpenses();
    updateExpensesChart();
}

function updateBudgetStats() {
    const totalBudget = events.reduce((sum, event) => sum + event.budget, 0);
    const totalSpent = expenses.reduce((sum, expense) => sum + expense.amount, 0);
    const remaining = totalBudget - totalSpent;
    const budgetUsed = totalBudget > 0 ? (totalSpent / totalBudget * 100) : 0;

    if (document.getElementById('totalBudget')) document.getElementById('totalBudget').textContent = formatCurrency(totalBudget);
    if (document.getElementById('totalSpent')) document.getElementById('totalSpent').textContent = formatCurrency(totalSpent);
    if (document.getElementById('remainingBudget')) document.getElementById('remainingBudget').textContent = formatCurrency(remaining);
    if (document.getElementById('budgetUsed')) document.getElementById('budgetUsed').textContent = Math.round(budgetUsed) + '%';
}

function loadRecentExpenses() {
    const recentExpenses = document.getElementById('recentExpenses');
    if (!recentExpenses) return;

    recentExpenses.innerHTML = '';
    const recent = expenses.slice(-5).reverse();
    
    recent.forEach(expense => {
        const event = events.find(e => e.id == expense.event_id);
        const category = categories.find(c => c.id == expense.category_id);
        
        recentExpenses.innerHTML += `
            <div class="d-flex justify-content-between align-items-center py-3 border-bottom">
                <div>
                    <h6 class="mb-1">${expense.description}</h6>
                    <small class="text-muted">
                        ${event ? event.name : 'N/A'} • ${category ? category.name : 'N/A'}
                    </small>
                </div>
                <div class="text-end">
                    <div class="h6 mb-0 text-danger">${formatCurrency(expense.amount)}</div>
                    <small>${new Date(expense.date).toLocaleDateString()}</small>
                </div>
            </div>
        `;
    });
}

// Chart initialization
let expensesChart;
function initCharts() {
    const ctx = document.getElementById('expensesChart');
    if (ctx) {
        const categoryExpenses = {};
        categories.forEach(cat => categoryExpenses[cat.name] = 0);
        
        expenses.forEach(expense => {
            const category = categories.find(c => c.id == expense.category_id);
            if (category) {
                categoryExpenses[category.name] += expense.amount;
            }
        });

        expensesChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: Object.keys(categoryExpenses),
                datasets: [{
                    data: Object.values(categoryExpenses),
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

function updateExpensesChart() {
    if (expensesChart) {
        const categoryExpenses = {};
        categories.forEach(cat => categoryExpenses[cat.name] = 0);
        
        expenses.forEach(expense => {
            const category = categories.find(c => c.id == expense.category_id);
            if (category) {
                categoryExpenses[category.name] += expense.amount;
            }
        });

        expensesChart.data.labels = Object.keys(categoryExpenses);
        expensesChart.data.datasets[0].data = Object.values(categoryExpenses);
        expensesChart.update();
    }
}

// Event Modal Functions
function saveEvent() {
    const form = document.getElementById('eventForm');
    const formData = new FormData(form);
    
    const eventData = {
        id: events.length ? Math.max(...events.map(e => e.id)) + 1 : 1,
        name: formData.get('event_name'),
        date: formData.get('event_date'),
        budget: parseFloat(formData.get('budget')),
        venue: formData.get('venue'),
        status: 'upcoming'
    };
    
    events.push(eventData);
    updateDashboard();
    bootstrap.Modal.getInstance(document.getElementById('eventModal')).hide();
    form.reset();
    loadEvents();
}

function editEvent(id) {
    const event = events.find(e => e.id === id);
    if (event) {
        document.querySelector('#eventForm [name="event_name"]').value = event.name;
        document.querySelector('#eventForm [name="event_date"]').value = event.date;
        document.querySelector('#eventForm [name="budget"]').value = event.budget;
        document.querySelector('#eventForm [name="venue"]').value = event.venue;
        currentEventId = id;
        const modalTitle = document.querySelector('#eventModal .modal-title');
        modalTitle.textContent = 'Edit Event';
    }
}

function deleteEvent(id) {
    if (confirm('Are you sure you want to delete this event?')) {
        events = events.filter(e => e.id !== id);
        updateDashboard();
        loadEvents();
    }
}

// Expense Modal Functions
function saveExpense() {
    const form = document.getElementById('expenseForm');
    const formData = new FormData(form);
    
    const expenseData = {
        id: expenses.length ? Math.max(...expenses.map(e => e.id)) + 1 : 1,
        event_id: parseInt(formData.get('event_id')),
        category_id: parseInt(formData.get('category_id')),
        description: formData.get('description'),
        amount: parseFloat(formData.get('amount')),
        date: new Date().toISOString().split('T')[0]
    };
    
    expenses.push(expenseData);
    updateDashboard();
    bootstrap.Modal.getInstance(document.getElementById('expenseModal')).hide();
    form.reset();
    loadBudget();
}

// Local Storage Sync
function syncData() {
    localStorage.setItem('events', JSON.stringify(events));
    localStorage.setItem('expenses', JSON.stringify(expenses));
}

// Load from localStorage on init
function loadFromStorage() {
    const savedEvents = localStorage.getItem('events');
    const savedExpenses = localStorage.getItem('expenses');
    if (savedEvents) events = JSON.parse(savedEvents);
    if (savedExpenses) expenses = JSON.parse(savedExpenses);
}

// API Integration Functions (Replace with your PHP endpoints)
async function fetchEvents() {
    try {
        const response = await fetch('api/events.php');
        events = await response.json();
    } catch (error) {
        console.error('Error fetching events:', error);
    }
}

async function fetchExpenses() {
    try {
        const response = await fetch('api/expenses.php');
        expenses = await response.json();
    } catch (error) {
        console.error('Error fetching expenses:', error);
    }
}

// Initialize storage sync
loadFromStorage();