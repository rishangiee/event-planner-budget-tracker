<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
<title>Create Event - EventPlanner</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gradient-to-br from-blue-50 to-emerald-50 p-8">
<div class="max-w-4xl mx-auto bg-white rounded-3xl shadow-2xl p-12">
    
<?php
// HANDLE FORM
if ($_POST) {
    session_start();
    if (!isset($_SESSION['events'])) $_SESSION['events'] = [];
    
    $event = [
        'id' => uniqid(),
        'title' => $_POST['title'],
        'date' => $_POST['date'],
                'budget' => $_POST['budget'],
        'attendees' => $_POST['attendees'],
        'spent' => 0
    ];
    
    $_SESSION['events'][] = $event;
    echo '<div class="mb-8 p-6 bg-green-100 border-4 border-green-400 rounded-2xl text-green-800 font-bold text-xl flex items-center gap-4">
        <i class="fas fa-check-circle text-3xl"></i>
        SUCCESS! Event "'.htmlspecialchars($event['title']).'" created!
        <a href="index.php" class="ml-auto bg-green-600 text-white px-6 py-2 rounded-xl hover:bg-green-700">View Dashboard</a>
    </div>';
}

// FORM
echo '
<form method="POST" class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-xl font-bold mb-3">Event Title *</label>
            <input type="text" name="title" required placeholder="Wedding Gala 2024" 
                   class="w-full p-4 border-2 border-gray-300 rounded-2xl text-lg focus:border-green-500 focus:ring-4 focus:ring-green-200">
        </div>
        <div>
            <label class="block text-xl font-bold mb-3">Date *</label>
                        <input type="date" name="date" required 
                   class="w-full p-4 border-2 border-gray-300 rounded-2xl text-lg focus:border-green-500 focus:ring-4 focus:ring-green-200">
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-xl font-bold mb-3">Expected Attendees *</label>
            <input type="number" name="attendees" min="1" required placeholder="150"
                                      class="w-full p-4 border-2 border-gray-300 rounded-2xl text-lg focus:border-green-500 focus:ring-4 focus:ring-green-200">
        </div>
        <div>
            <label class="block text-xl font-bold mb-3">Total Budget * $</label>
            <input type="number" name="budget" step="0.01" min="0.01" required placeholder="5000"
                   class="w-full p-4 border-2 border-gray-300 rounded-2xl text-lg focus:border-green-500 focus:ring-4 focus:ring-green-200">
        </div>
    </div>
    <button type="submit" class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white py-6 px-12 rounded-2xl text-2xl font-bold shadow-2xl hover:shadow-3xl hover:scale-105 transition-all duration-300 mt-8">
        <i class="fas fa-rocket mr-3"></i>Create Event!
    </button>
</form>';

// Events List
if (!empty($_SESSION['events'])) {
    echo '<div class="mt-16 p-8 bg-gradient-to-r from-green-50 to-emerald-50 rounded-3xl border-4 border-green-200">';
    echo '<h2 class="text-3xl font-bold mb-8 flex items-center gap-4 text-green-800"><i class="fas fa-list"></i>Your Events ('.count($_SESSION['events']).')</h2>';
    echo '<div class="overflow-x-auto">';
    echo '<table class="w-full bg-white rounded-2xl shadow-xl">';
    echo '<thead><tr class="bg-gradient-to-r from-green-100 to-emerald-100">';
    echo '<th class="p-4 text-left font-bold">Event</th>';
    echo '<th class="p-4 text-left font-bold">Date</th>';
    echo '<th class="p-4 text-right font-bold">Budget</th>';
    echo '<th class="p-4 text-right font-bold">Spent</th>';
    echo '<th class="p-4 text-right font-bold">Actions</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($_SESSION['events'] as $event) {
        $progress = $event['budget'] > 0 ? round(($event['spent'] / $event['budget']) * 100) : 0;
        echo '<tr class="hover:bg-white border-b hover:shadow-sm transition-all">';
        echo '<td class="p-4 font-bold text-lg">'.htmlspecialchars($event['title']).'</td>';
        echo '<td class="p-4">'.date('M j, Y', strtotime($event['date'])).'</td>';
        echo '<td class="p-4 text-right font-bold text-xl">$'.number_format($event['budget']).'</td>';
        echo '<td class="p-4 text-right font-bold text-orange-600">-$'.number_format($event['spent'], 2).'</td>';
        echo '<td class="p-4 text-right">';
        echo '<div class="flex items-center gap-3">';
        echo '<div class="w-24 bg-gray-200 rounded-full h-4">';
        echo '<div class="bg-gradient-to-r from-green-500 to-emerald-500 h-4 rounded-full" style="width:'.$progress.'%"></div>';
        echo '</div>';
        echo '<span class="font-bold text-green-700">'.$progress.'%</span>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div></div>';
}
?>

<!-- Footer Buttons -->
<div class="mt-12 flex flex-col sm:flex-row gap-4 justify-center">
    <a href="index.php" class="bg-blue-600 text-white py-4 px-12 rounded-2xl font-bold text-xl hover:bg-blue-700 shadow-xl transition-all flex items-center justify-center gap-3">
        <i class="fas fa-home"></i>Dashboard
    </a>
    <button onclick="location.reload()" class="bg-gray-600 text-white py-4 px-12 rounded-2xl font-bold text-xl hover:bg-gray-700 shadow-xl transition-all flex items-center justify-center gap-3">
        <i class="fas fa-sync-alt"></i>Refresh
    </button>
</div>

<script>
// Auto scroll to top
window.scrollTo(0, 0);
</script>
</div>
</body>
</html>