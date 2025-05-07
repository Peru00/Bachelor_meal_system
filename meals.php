<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Update available meals query to show only active meals
$available_meals_query = "SELECT id, name, price 
                         FROM meals 
                         WHERE status = 'active' 
                         ORDER BY name";
$stmt = $conn->prepare($available_meals_query);
$stmt->execute();
$available_meals = $stmt->get_result();

// Get months for filter (last 12 months)
$months_query = "SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') as month_year,
                DATE_FORMAT(date, '%M %Y') as month_display
                FROM meals 
                WHERE user_id = $user_id 
                ORDER BY date DESC 
                LIMIT 12";
$months_result = $conn->query($months_query);

// Fetch user's scheduled meals with proper JOIN
$history_query = "SELECT 
    sm.id as schedule_id,
    sm.scheduled_date as date,
    sm.meal_time,
    m.name,
    sm.quantity,
    sm.total_price as price,
    m.rating
    FROM scheduled_meals sm
    JOIN meals m ON sm.meal_id = m.id
    WHERE sm.user_id = ? 
    AND sm.status = 'active'
    ORDER BY sm.scheduled_date DESC";

$stmt = $conn->prepare($history_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$meal_history = $stmt->get_result();

// Get user's scheduled meals
$scheduled_meals_query = "SELECT 
    sm.id as schedule_id,
    sm.scheduled_date as date,
    sm.meal_time,
    m.name,
    sm.quantity,
    COALESCE(sm.total_price, m.price * sm.quantity) as price,  /* Calculate price if total_price is null */
    m.rating
    FROM scheduled_meals sm
    JOIN meals m ON sm.meal_id = m.id
    WHERE sm.user_id = ? 
    AND sm.status = 'active'
    ORDER BY sm.scheduled_date DESC";

$stmt = $conn->prepare($scheduled_meals_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$scheduled_meals = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Meals | Bachelor Meal System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #F6F5EA;  /* Lighter beige background */
        }

        .glass-card {
            background: #F6F5EA;
            color: #000000;  /* Changed to black for better contrast */
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border: none;
            padding: 2rem;
        }

        .meal-scheduler {
            margin-bottom: 2rem;
            background: #F6F5EA;
        }

        .meal-history {
            background: #F6F5EA;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            color: #000000;  /* Changed to black for better contrast */
        }

        .meals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .meals-table th {
            background: #FFFFFF;
            color: #27548A;
            font-weight: 600;
            padding: 1rem;
            text-align: left;
            border-bottom: 2px solid #E5E9EF;
        }

        .meals-table td {
            padding: 1rem;
            border-bottom: 1px solid #E5E9EF;
            color: #2C3E50;
        }

        .meals-table tr:hover {
            background: #F8F9FA;
        }

        .section-heading {
            color: #27548A;
            font-size: 2rem;  /* Increased from 1.5rem */
            margin-bottom: 2.5rem;  /* Increased from 1.5rem */
            text-align: center;
            position: relative;
        }

        .section-heading::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: #DDA853;
        }

        .form-control {
            background: #FFFFFF;
            border: 1px solid #E5E9EF;
            border-radius: 5px;
            padding: 0.75rem;
        }

        .schedule-btn {
            background: #27548A;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            width: 100%;
            margin-top: 2rem;  /* Increased spacing */
        }

        .filter-options select {
            background: #FFFFFF;
            border: 1px solid #E5E9EF;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            margin-left: 1rem;
        }

        .total-price-display {
            font-size: 1.4rem;  /* Increased from 1.2rem */
            color: #DDA853;
            font-weight: bold;
            margin-top: 2rem;  /* Increased spacing */
            text-align: right;
            border-top: 1px solid #E5E9EF;
            padding-top: 1.5rem;
        }

        .rating-options label {
            background: #F3F3F3;
        }

        .rating-options input[type="radio"]:checked + label {
            background: #DDA853;
        }

        .update-btn {
            background: #27548A;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            border: none;
        }

        /* Update text colors for better contrast */
        .form-group label {
            color: #27548A;
        }

        .meal-history-header h2 {
            color: #27548A;
        }

        .meal-grid {
            display: flex;
            flex-direction: row;
            gap: 2rem;  /* Increased from 1rem */
            margin-bottom: 2rem;  /* Increased from 1rem */
            margin-top: 2rem;
        }

        .form-group {
            flex: 1;
            min-width: 200px;  /* Increased from 150px */
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #E5E9EF;
            border-radius: 5px;
        }

        @media (max-width: 768px) {
            .meal-grid {
                flex-direction: column;
            }
        }

        .scheduler-form {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .meal-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 2rem;
            margin-bottom: 1.5rem;
            align-items: end;
        }

        .total-price-display {
            display: flex;
            align-items: flex-end;
            font-size: 1.2rem;
            font-weight: bold;
            color: #DDA853;
            border: none;
            height: 100%;
            margin-top: 1.7rem; /* Align with other inputs */
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .total-price-display {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 2px solid #eee;
            font-size: 1.2rem;
            font-weight: bold;
        }

        .schedule-btn {
            margin-top: 1.5rem;
            width: 100%;
            background: #27548A;
            color: white;
            padding: 1rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .meal-grid {
                grid-template-columns: 1fr;
            }
        }

        
    </style>
</head>
<body>
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="user-dashboard.php">Dashboard</a>
                <a href="meals.php" class="active">My Meals</a>
             <a href="inventory.php">Inventory</a>
                <a href="payments.php">Payments</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <main class="meals-container">
        <header class="meals-header">
            <h1>My Meal Planner</h1>
        </header>

        <!-- Meal Scheduler Section -->
        <section class="glass-card meal-scheduler">
            <h2 class="section-heading">Schedule Your Meal</h2>
            <form action="schedule_meal.php" method="POST" class="scheduler-form">
                <div class="meal-grid">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="meal_date" id="meal_date" class="form-control" required min="<?php echo $today; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Meal Time</label>
                        <select name="meal_time" id="meal_time" class="form-control" required>
                            <option value="">Select Time</option>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Meal</label>
                        <select name="meal_id" id="meal_id" class="form-control" required onchange="updateTotalPrice()">
                            <option value="" data-price="0">Choose a meal</option>
                            <?php while($meal = $available_meals->fetch_assoc()): ?>
                                <option value="<?php echo $meal['id']; ?>" data-price="<?php echo $meal['price']; ?>">
                                    <?php echo htmlspecialchars($meal['name']); ?> (৳<?php echo number_format($meal['price'], 2); ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" id="quantity" class="form-control" min="1" value="1" required onchange="updateTotalPrice()">
                    </div>

                    <div class="total-price-display">
                        Total Price: <span id="total_price">৳0.00</span>
                    </div>
                </div>
                
                <button type="submit" class="schedule-btn">Schedule Meal</button>
            </form>
        </section>

        <!-- Meal History Section -->
        <section class="glass-card meal-history">
            <div class="meal-history-header">
                <h2>My Meal History</h2>
                <div class="filter-options">
                    <select id="month-filter" onchange="filterMeals()">
                        <option value="all">All Months</option>
                        <?php while($month = $months_result->fetch_assoc()): ?>
                            <option value="<?php echo $month['month_year']; ?>">
                                <?php echo $month['month_display']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                    <select id="meal-type-filter" onchange="filterMeals()">
                        <option value="all">All Meal Types</option>
                        <option value="breakfast">Breakfast</option>
                        <option value="lunch">Lunch</option>
                        <option value="dinner">Dinner</option>
                    </select>
                </div>
            </div>
            
            <!-- Update table structure to match template -->
            <table class="meals-table">
                <thead>
                    <tr>
                        <th>Meal ID</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Meal Description</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Rating (1-5)</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($meal_history && $meal_history->num_rows > 0): ?>
                        <?php while($meal = $meal_history->fetch_assoc()): ?>
                            <tr>
                                <td>#M-<?php echo $meal['schedule_id']; ?></td>
                                <td><?php echo date('M j, Y', strtotime($meal['date'])); ?></td>
                                <td><?php echo ucfirst($meal['meal_time']); ?></td>
                                <td><?php echo htmlspecialchars($meal['name']); ?></td>
                                <td><?php echo $meal['quantity']; ?></td>
                                <td>৳<?php echo number_format($meal['price'], 2); ?></td>
                                <td>
                                    <div class="rating-options">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <input type="radio" 
                                                id="meal<?php echo $meal['schedule_id']; ?>-rating<?php echo $i; ?>" 
                                                name="meal<?php echo $meal['schedule_id']; ?>-rating" 
                                                value="<?php echo $i; ?>"
                                                <?php echo ($meal['rating'] == $i) ? 'checked' : ''; ?>>
                                            <label for="meal<?php echo $meal['schedule_id']; ?>-rating<?php echo $i; ?>">
                                                <?php echo $i; ?>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                </td>
                                <td>
                                    <button class="update-btn" 
                                            onclick="updateRating(<?php echo $meal['schedule_id']; ?>)">
                                        Update
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center;">No meal history found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <div class="meals-card">
            <h2 class="section-heading">My Scheduled Meals</h2>
            <table class="meals-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Meal</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($meal = $scheduled_meals->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('M j, Y', strtotime($meal['date'])); ?></td>
                            <td><span class="meal-time"><?php echo ucfirst($meal['meal_time']); ?></span></td>
                            <td><?php echo htmlspecialchars($meal['name']); ?></td>
                            <td><?php echo $meal['quantity']; ?></td>
                            <td>৳<?php echo number_format($meal['price'], 2); ?></td>
                            <td>
                                <button class="schedule-btn">Modify</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Remove all previous price calculation functions
        function updateTotalPrice() {
            const mealSelect = document.getElementById('meal_id');
            const quantityInput = document.getElementById('quantity');
            const totalPriceDisplay = document.getElementById('total_price');
            
            if (!mealSelect || !quantityInput || !totalPriceDisplay) {
                console.error('Required elements not found');
                return;
            }

            try {
                const selectedOption = mealSelect.options[mealSelect.selectedIndex];
                const price = parseFloat(selectedOption.dataset.price) || 0;
                const quantity = parseInt(quantityInput.value) || 1;
                
                const total = (price * quantity).toFixed(2);
                totalPriceDisplay.textContent = '৳' + total;
            } catch (error) {
                console.error('Error calculating price:', error);
                totalPriceDisplay.textContent = '৳0.00';
            }
        }

        // Initialize price calculation
        document.addEventListener('DOMContentLoaded', updateTotalPrice);

        // Add event listeners
        document.getElementById('meal_id').addEventListener('change', updateTotalPrice);
        document.getElementById('quantity').addEventListener('input', updateTotalPrice);

        // Rating update
        function updateRating(mealId) {
            const rating = document.querySelector(`input[name="meal${mealId}-rating"]:checked`)?.value;
            if (!rating) return;

            fetch('update_rating.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `meal_id=${mealId}&rating=${rating}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Rating updated successfully!');
                }
            });
        }

        // Meal filtering
        function filterMeals() {
            const month = document.getElementById('month-filter').value;
            const mealType = document.getElementById('meal-type-filter').value;
            window.location.href = `meals.php?month=${month}&type=${mealType}`;
        }

        // Add JavaScript for dynamic meal loading
        function updateAvailableMeals() {
            const selectedDate = document.getElementById('meal-date').value;
            fetch('get_available_meals.php?date=' + selectedDate)
                .then(response => response.json())
                .then(meals => {
                    const mealSelect = document.getElementById('meal-name');
                    mealSelect.innerHTML = '<option value="">Select Meal</option>';
                    meals.forEach(meal => {
                        mealSelect.innerHTML += `<option value="\${meal.id}" data-price="\${meal.price}">
                            \${meal.name} (৳\${meal.price})
                        </option>`;
                    });
                });
        }

        function validateForm() {
            console.log('Form submitted');
            return true;
        }

        function updatePrice() {
            const mealSelect = document.getElementById('meal_id');
            const quantity = document.getElementById('quantity').value;
            const price = mealSelect.options[mealSelect.selectedIndex].dataset.price || 0;
            const total = (price * quantity).toFixed(2);
            document.getElementById('total_price').textContent = '৳' + total;
        }

        function calculateTotalPrice() {
            const mealSelect = document.getElementById('meal_id');
            const quantityInput = document.getElementById('quantity');
            const totalDisplay = document.getElementById('total_price');
            
            const selectedOption = mealSelect.options[mealSelect.selectedIndex];
            const price = parseFloat(selectedOption.dataset.price) || 0;
            const quantity = parseInt(quantityInput.value) || 0;
            
            const total = (price * quantity).toFixed(2);
            totalDisplay.textContent = '৳' + total;
        }

        // Initialize price calculation
        calculateTotalPrice();

        // Replace all existing price calculation functions with this one
        function updateTotalPrice() {
            const mealSelect = document.getElementById('meal_id');
            const quantityInput = document.getElementById('quantity');
            const totalPriceDisplay = document.getElementById('total_price');
            
            if (!mealSelect || !quantityInput || !totalPriceDisplay) return;

            const selectedOption = mealSelect.options[mealSelect.selectedIndex];
            const price = parseFloat(selectedOption.dataset.price) || 0;
            const quantity = parseInt(quantityInput.value) || 1;
            
            const total = (price * quantity).toFixed(2);
            totalPriceDisplay.textContent = '৳' + total;
        }

        // Initialize price calculation on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateTotalPrice();
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>