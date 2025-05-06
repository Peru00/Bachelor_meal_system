<?php
session_start();
include 'db_connect.php'; // This initializes $conn

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Fetch emergency notifications
$notifications_query = "SELECT title, message, created_at FROM notifications WHERE user_id = $user_id AND type = 'emergency' ORDER BY created_at DESC LIMIT 2";
$notifications_result = $conn->query($notifications_query);

// Fetch monthly stats
$stats_query = "
    SELECT 
        COUNT(*) AS meals_taken, 
        SUM(price) AS total_due, 
        (SELECT MIN(date) FROM bazar_schedule WHERE user_id = $user_id AND date >= CURDATE()) AS next_bazar 
    FROM meals 
    WHERE user_id = $user_id AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Fetch recent meals
$recent_meals_query = "
    SELECT date, meal_type, meal_name, quantity, price 
    FROM meals 
    WHERE user_id = $user_id 
    ORDER BY date DESC 
    LIMIT 5
";
$recent_meals_result = $conn->query($recent_meals_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard | Bachelor Meal System</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .meals-table th {
            background-color: var(--primary);
            color: white;
            padding: 1rem;
            text-align: left;
        }
        .meals-table tr:nth-child(even) {
            background-color: rgba(255, 255, 255, 0.05);
        }

        .meals-table tr:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .view-all-button {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: var(--accent);
            color: var(--dark-primary);
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .view-all-button:hover {
            background-color: #c49344;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="user-dashboard.php" class="active">Dashboard</a>
                <a href="meals.html">My Meals</a>
                <a href="inventory.html">Inventory</a>
                <a href="payments.php">Payments</a>
                <a href="profile.html">Profile</a>
                <a href="login.html">Logout</a>
            </div>
        </div>
    </nav>

    <main class="dashboard-container">
        <!-- Dashboard Header -->
        <header class="dashboard-header">
            <h1>
                <span class="welcome-text" style="color: var(--primary);">Welcome,</span>
                <span class="user-name" style="color: var(--accent);"><?php echo htmlspecialchars($user_name); ?></span>
            </h1>
            <div class="current-date"><?php echo date('F j, Y'); ?></div>
        </header>

        <!-- Notifications Section -->
        <section class="glass-card notifications-section">
            <h2 class="section-heading">Notifications</h2>
            <?php if ($notifications_result->num_rows > 0): ?>
                <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                    <div class="notification emergency">
                        <div class="notification-icon">🚨</div>
                        <div class="notification-content">
                            <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="notification-time"><?php echo date('h:i A', strtotime($notification['created_at'])); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p>No emergency notifications available.</p>
            <?php endif; ?>
        </section>

        <!-- Monthly Overview Section -->
        <section class="glass-card stats-section">
            <h2 class="section-heading">Monthly Overview</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['meals_taken'] ?? 0; ?></div>
                    <div class="stat-label">Meals Taken</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$<?php echo number_format($stats['total_due'] ?? 0, 2); ?></div>
                    <div class="stat-label">Total Due</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['next_bazar'] ? date('F j', strtotime($stats['next_bazar'])) : 'N/A'; ?></div>
                    <div class="stat-label">Next Bazar</div>
                </div>
            </div>
        </section>

        <!-- Recent Meals Section -->
        <section class="glass-card recent-meals-section">
            <h2 class="section-heading">Recent Meals</h2>
            <table class="meals-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Meal Type</th>
                        <th>Meal</th>
                        <th>Quantity</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($recent_meals_result->num_rows > 0): ?>
                        <?php while ($meal = $recent_meals_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('F j, Y', strtotime($meal['date'])); ?></td>
                                <td><?php echo ucfirst($meal['meal_type']); ?></td>
                                <td><?php echo htmlspecialchars($meal['meal_name']); ?></td>
                                <td><?php echo $meal['quantity']; ?></td>
                                <td>$<?php echo number_format($meal['price'], 2); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No recent meals found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="view-all-container">
                <a href="meals.html" class="view-all-button">View All Meals</a>
            </div>
        </section>
    </main>
</body>
</html>
<?php $conn->close(); ?>