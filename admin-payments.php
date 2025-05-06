<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

$message = '';

// Handle payment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['payment_id'])) {
        $payment_id = (int)$_POST['payment_id'];
        $status = $_POST['action'] === 'approve' ? 'approved' : 'rejected';
        
        $sql = "UPDATE payments SET status = '$status' WHERE id = $payment_id";
        if ($conn->query($sql)) {
            $message = "Payment " . ucfirst($status) . " successfully!";
        }
    }
}

// Fetch all payments
$payments_query = "SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as member_name
                  FROM payments p
                  JOIN users u ON p.user_id = u.id
                  ORDER BY p.date DESC";
$payments_result = $conn->query($payments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Management | Admin</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .payments-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        .payments-table th {
            text-align: left;
            padding: 2rem;  /* Increased padding */
            border-bottom: 2px solid #27548A;  /* Thin blue line */
            background: none;  /* Removed background */
            color: var(--primary);  /* Changed text color */
            font-weight: 600;
        }
        
        .payments-table tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .payments-table tr:hover {
            background: rgba(255, 255, 255, 0.1);
        }
        .payments-table td {
            padding: 2rem;  /* Increased padding */
            line-height: 1.8;  /* Increased line height */
        }
        .payments-table tr {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            height: 5rem;  /* Increased height */
        }
        .glass-card h2 {
            color: var(--primary);
            padding: 1rem;
            margin-bottom: 1rem;
            font-size: 1.5rem;
            text-align: center;
            position: relative;
        }
        
        .glass-card h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: var(--accent);  /* Orange accent color */
        }
        .status-pending { 
            background: rgba(255, 165, 0, 0.1);
            color: #ffa500;
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.875rem;
        }
        .status-approved { color: #4caf50; }
        .status-rejected { color: #f44336; }
        .btn-approve,
        .btn-reject {
            padding: 0.5rem 1.5rem;
            border-radius: 5px;
            font-weight: 500;
            margin: 0 0.25rem;
            transition: transform 0.2s;
        }
        
        .btn-approve:hover,
        .btn-reject:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <nav class="glass-card">
        <div class="nav-container">
            <a href="index.html" class="nav-logo">Meal Manager</a>
            <div class="nav-links">
                <a href="admin-dashboard.php">Dashboard</a>
                <a href="admin-inventory.php">Inventory</a>
                <a href="admin-members.php">Members</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <main class="admin-container">
        <?php if ($message): ?>
            <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <section class="glass-card">
            <h2>Payment Requests</h2>
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Amount</th>
                        <th>Method</th>
                        <th>Transaction ID</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($payments_result && $payments_result->num_rows > 0): ?>
                        <?php while ($payment = $payments_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['member_name']); ?></td>
                                <td>৳<?php echo number_format($payment['amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($payment['method']); ?></td>
                                <td><?php echo htmlspecialchars($payment['transaction_id']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($payment['date'])); ?></td>
                                <td>
                                    <span class="status-<?php echo $payment['status']; ?>">
                                        <?php echo ucfirst($payment['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($payment['status'] === 'pending'): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="payment_id" value="<?php echo $payment['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                                            <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7">No payments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</body>
</html>
<?php $conn->close(); ?>
