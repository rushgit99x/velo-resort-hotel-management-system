<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to customers only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errors = [];

// Fetch customer details for header
try {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $customer_name = $user['name'] ?? 'Customer';
    $customer_email = $user['email'] ?? 'Unknown';
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $customer_name = 'Customer';
    $customer_email = 'Unknown';
}

// Fetch billing details
try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.reservation_id, b.service_type, b.additional_fee, b.created_at,
               r.room_type, r.check_in_date, r.check_out_date, br.name as branch_name, br.location
        FROM billings b
        JOIN reservations r ON b.reservation_id = r.id
        JOIN branches br ON r.hotel_id = br.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $billings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $billings = [];
}

// Fetch payment details
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.reservation_id, p.group_booking_id, p.amount, p.payment_method, p.card_last_four, 
               p.cardholder_name, p.status, p.created_at,
               r.room_type, r.check_in_date, r.check_out_date, br.name as branch_name, br.location
        FROM payments p
        LEFT JOIN reservations r ON p.reservation_id = r.id
        LEFT JOIN branches br ON r.hotel_id = br.id
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $payments = [];
}

// Calculate total additional fees and remaining balance
try {
    // Sum of additional fees from billings
    $stmt = $pdo->prepare("SELECT SUM(additional_fee) as total_fees FROM billings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_fees = $stmt->fetch(PDO::FETCH_ASSOC)['total_fees'] ?? 0.00;

    // Sum of remaining balance from reservations
    $stmt = $pdo->prepare("SELECT SUM(remaining_balance) as total_balance FROM reservations WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $total_balance = $stmt->fetch(PDO::FETCH_ASSOC)['total_balance'] ?? 0.00;

    // Calculate full total
    $full_total = $total_fees + $total_balance;
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $total_fees = 0.00;
    $total_balance = 0.00;
    $full_total = 0.00;
}

include 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & Payments - Customer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>
<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Customer Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li><a href="customer_dashboard.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'customer_dashboard.php' ? 'active' : ''; ?>"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li><a href="make_reservation.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'make_reservation.php' ? 'active' : ''; ?>"><i class="ri-calendar-check-line"></i><span>Make Reservation</span></a></li>
                <li><a href="customer_manage_reservations.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'customer_manage_reservations.php' ? 'active' : ''; ?>"><i class="ri-calendar-line"></i><span>Manage Reservations</span></a></li>
                <li><a href="customer_additional_services.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'customer_additional_services.php' ? 'active' : ''; ?>"><i class="ri-service-line"></i><span>Additional Services</span></a></li>
                <li><a href="billing_payments.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'billing_payments.php' ? 'active' : ''; ?>"><i class="ri-wallet-line"></i><span>Billing & Payments</span></a></li>
                
                <li><a href="customer_profile.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'customer_profile.php' ? 'active' : ''; ?>"><i class="ri-settings-3-line"></i><span>Profile</span></a></li>
                <li><a href="logout.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'logout.php' ? 'active' : ''; ?>"><i class="ri-logout-box-line"></i><span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Billing & Payments</h1>
            <div class="user__info">
                <span><?php echo htmlspecialchars($customer_email); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section class="billing__section">
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Total Amount Owed -->
            <div class="total__summary">
                <h2>Total Amount Owed</h2>
                <div class="summary__box">
                    <p><strong>Total Additional Fees:</strong> $<?php echo number_format($total_fees, 2); ?></p>
                    <p><strong>Total Remaining Balance (Reservations):</strong> $<?php echo number_format($total_balance, 2); ?></p>
                    <p><strong>Full Total:</strong> $<?php echo number_format($full_total, 2); ?></p>
                </div>
            </div>

            <!-- Billing Details -->
            <h2>Your Billing Details</h2>
            <?php if (empty($billings)): ?>
                <p>No additional services billed yet.</p>
            <?php else: ?>
                <div class="billings__table">
                    <table>
                        <thead>
                            <tr>
                                <th>Billing ID</th>
                                <th>Reservation ID</th>
                                <th>Branch</th>
                                <th>Room Type</th>
                                <th>Service Type</th>
                                <th>Additional Fee</th>
                                <th>Date Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($billings as $billing): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($billing['id']); ?></td>
                                    <td><?php echo htmlspecialchars($billing['reservation_id']); ?></td>
                                    <td><?php echo htmlspecialchars($billing['branch_name'] . ' - ' . $billing['location']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($billing['room_type'])); ?></td>
                                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $billing['service_type']))); ?></td>
                                    <td>$<?php echo number_format($billing['additional_fee'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($billing['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?><br><br><br>

            <!-- Payment Details -->
            <h2>Your Payment Details</h2>
            <?php if (empty($payments)): ?>
                <p>No payments recorded yet.</p>
            <?php else: ?>
                <div class="payments__table">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Reservation ID</th>
                                <th>Group Booking ID</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Card Last Four</th>
                                <th>Cardholder Name</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['id']); ?></td>
                                    <td><?php echo $payment['reservation_id'] ? htmlspecialchars($payment['reservation_id']) : '-'; ?></td>
                                    <td><?php echo $payment['group_booking_id'] ? htmlspecialchars($payment['group_booking_id']) : '-'; ?></td>
                                    <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $payment['payment_method']))); ?></td>
                                    <td><?php echo $payment['card_last_four'] ? htmlspecialchars($payment['card_last_four']) : '-'; ?></td>
                                    <td><?php echo $payment['cardholder_name'] ? htmlspecialchars($payment['cardholder_name']) : '-'; ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($payment['status'])); ?></td>
                                    <td><?php echo htmlspecialchars($payment['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<style>
/* General Dashboard Styles */
.dashboard__container {
    display: flex;
    min-height: 100vh;
    background: #f3f4f6;
}

.dashboard__content {
    flex: 1;
    padding: 2rem;
}

.section__header {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.billing__section {
    max-width: 1250px;
    margin: 0 auto;
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.total__summary {
    margin-bottom: 2rem;
    padding: 1rem;
    background: #f9fafb;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.total__summary h2 {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 1rem;
}

.summary__box {
    padding: 1rem;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #d1d5db;
}

.summary__box p {
    margin: 0.5rem 0;
    font-size: 1rem;
    color: #1f2937;
}

.summary__box p strong {
    font-weight: 600;
}

.error {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.error ul {
    margin: 0;
    padding-left: 1.5rem;
}

.billings__table, .payments__table {
    overflow-x: auto;
    margin-top: 1rem;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #f9fafb;
    border-radius: 8px;
    overflow: hidden;
}

th, td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

th {
    background: #1f2937;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

td {
    font-size: 0.9rem;
    color: #1f2937;
}

tr:hover {
    background: #f1f5f9;
}

td:last-child {
    white-space: nowrap;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background: #1f2937;
    color: white;
    transition: width 0.3s ease;
}

.sidebar.collapsed {
    width: 80px;
}

.sidebar.collapsed .sidebar__title,
.sidebar.collapsed .sidebar__link span {
    display: none;
}

.sidebar__header {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar__logo {
    width: 40px;
    height: 40px;
}

.sidebar__title {
    font-size: 1.25rem;
    font-weight: 600;
}

.sidebar__toggle {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
}

.sidebar__nav {
    padding: 1rem;
}

.sidebar__links {
    list-style: none;
    padding: 0;
}

.sidebar__link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    transition: background 0.2s ease;
}

.sidebar__link:hover,
.sidebar__link.active {
    background: #3b82f6;
}

.sidebar__link i {
    font-size: 1.25rem;
}

/* Header Styles */
.dashboard__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.user__info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user__avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        width: 80px;
    }
    
    .sidebar__title,
    .sidebar__link span {
        display: none;
    }
    
    .dashboard__content {
        padding: 1rem;
    }
    
    .billings__table, .payments__table {
        font-size: 0.8rem;
    }
    
    th, td {
        padding: 0.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');

    // Toggle sidebar
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line');
    });
});
</script>
</body>
</html>