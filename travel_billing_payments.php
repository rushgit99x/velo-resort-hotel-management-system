<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to travel_company only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'travel_company') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Get travel company details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT u.name, u.email, cp.company_name 
    FROM users u 
    LEFT JOIN company_profiles cp ON u.id = cp.user_id 
    WHERE u.id = ? AND u.role = 'travel_company'
");
$stmt->execute([$user_id]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$company_name = $company['company_name'] ?? $company['name'] ?? 'Travel Company';
$company_email = $company['email'] ?? 'Unknown';

// Initialize billing data
$billing_data = [
    'billings' => [],
    'invoices' => [],
    'payments' => [],
    'total_bills' => 0.00,
    'total_remaining_balance' => 0.00,
    'total_owed' => 0.00
];

try {
    // Get company profile ID
    $stmt = $pdo->prepare("SELECT id FROM company_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $company_profile = $stmt->fetch(PDO::FETCH_ASSOC);
    $company_id = $company_profile['id'] ?? 0;

    // Fetch billings
    $stmt = $pdo->prepare("
        SELECT b.id, b.reservation_id, b.service_type, b.additional_fee, b.status, b.created_at, r.check_in_date, r.check_out_date
        FROM billings b
        LEFT JOIN reservations r ON b.reservation_id = r.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $billing_data['billings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total bills (additional fees from billings)
    $stmt = $pdo->prepare("
        SELECT SUM(additional_fee) as total 
        FROM billings 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $billing_data['total_bills'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00;

    // Calculate total remaining balance from reservations
    $stmt = $pdo->prepare("
        SELECT SUM(remaining_balance) as total 
        FROM reservations 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $billing_data['total_remaining_balance'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00;

    // Calculate total owed (sum of additional fees and remaining balance)
    $billing_data['total_owed'] = $billing_data['total_bills'] + $billing_data['total_remaining_balance'];

    // Fetch invoices
    $stmt = $pdo->prepare("
        SELECT i.id, i.company_id, i.amount, i.status, i.issued_at, i.due_date
        FROM invoices i
        WHERE i.company_id = ?
        ORDER BY i.issued_at DESC
    ");
    $stmt->execute([$company_id]);
    $billing_data['invoices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch payments
    $stmt = $pdo->prepare("
        SELECT p.id, p.reservation_id, p.group_booking_id, p.amount, p.payment_method, p.card_last_four, p.cardholder_name, p.status, p.created_at
        FROM payments p
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $billing_data['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $db_error = "Database connection error: " . $e->getMessage();
}

include 'templates/header.php';
?>

<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Travel Company Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li>
                    <a href="travel_company_dashboard.php" class="sidebar__link">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="make_travel_reservations.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Make Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="travel_manage_reservations.php" class="sidebar__link">
                        <i class="ri-calendar-line"></i>
                        <span>Manage Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="travel_additional_services.php" class="sidebar__link">  
                        <i class="ri-service-line"></i>
                        <span>Additional Services</span>
                    </a>
                </li>
                <li>
                    <a href="travel_billing_payments.php" class="sidebar__link active">
                        <i class="ri-wallet-line"></i>
                        <span>Billing & Payments</span>
                    </a>
                </li>
                <li>
                    <a href="company_profile.php" class="sidebar__link">
                        <i class="ri-settings-3-line"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li>
                    <a href="logout.php" class="sidebar__link">
                        <i class="ri-logout-box-line"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Billing & Payments</h1>
            <div class="user__info">
                <span><?php echo htmlspecialchars($company_email); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section id="billing" class="dashboard__section active">
            <h2 class="section__subheader">Billing Overview</h2>
            <?php if (isset($db_error)): ?>
                <p class="error"><?php echo htmlspecialchars($db_error); ?></p>
            <?php endif; ?>

            <!-- Total Owed Card -->
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-bill-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Owed</h3>
                        <p>$<?php echo number_format($billing_data['total_owed'], 2); ?></p>
                        <small>(Bills: $<?php echo number_format($billing_data['total_bills'], 2); ?> + Remaining Balance: $<?php echo number_format($billing_data['total_remaining_balance'], 2); ?>)</small>
                    </div>
                </div>
            </div>

            <!-- Billings Table -->
            <div class="table__container">
                <h3 class="section__subheader">Service Billings</h3>
                <table class="billing__table">
                    <thead>
                        <tr>
                            <th>Bill ID</th>
                            <th>Reservation ID</th>
                            <th>Service Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Check-in Date</th>
                            <th>Check-out Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($billing_data['billings'])): ?>
                            <tr>
                                <td colspan="8">No billings found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($billing_data['billings'] as $billing): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($billing['id']); ?></td>
                                    <td><?php echo htmlspecialchars($billing['reservation_id']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $billing['service_type']))); ?></td>
                                    <td>$<?php echo number_format($billing['additional_fee'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($billing['status'])); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($billing['created_at']))); ?></td>
                                    <td><?php echo htmlspecialchars($billing['check_in_date'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($billing['check_out_date'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Invoices Table -->
            <div class="table__container">
                <h3 class="section__subheader">Invoices</h3>
                <table class="billing__table">
                    <thead>
                        <tr>
                            <th>Invoice ID</th>
                            <th>Company ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Issued At</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($billing_data['invoices'])): ?>
                            <tr>
                                <td colspan="6">No invoices found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($billing_data['invoices'] as $invoice): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($invoice['id']); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['company_id']); ?></td>
                                    <td>$<?php echo number_format($invoice['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($invoice['status'])); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($invoice['issued_at']))); ?></td>
                                    <td><?php echo htmlspecialchars($invoice['due_date']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Payments Table -->
            <div class="table__container">
                <h3 class="section__subheader">Payments</h3>
                <table class="billing__table">
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
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($billing_data['payments'])): ?>
                            <tr>
                                <td colspan="9">No payments found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($billing_data['payments'] as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['id']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['reservation_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['group_booking_id'] ?? 'N/A'); ?></td>
                                    <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_method']))); ?></td>
                                    <td><?php echo htmlspecialchars($payment['card_last_four'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['cardholder_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($payment['status'])); ?></td>
                                    <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($payment['created_at']))); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<style>
/* Reuse dashboard container styles */
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

.section__subheader {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 1rem;
}

.error {
    color: #dc2626;
    margin-bottom: 1rem;
    font-weight: 500;
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

/* Overview Cards */
.overview__cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.overview__card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.overview__card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.card__icon {
    font-size: 2rem;
    color: #3b82f6;
    background: #eff6ff;
    padding: 0.75rem;
    border-radius: 50%;
    min-width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card__content h3 {
    font-size: 0.9rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.card__content p {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2937;
}

.card__content small {
    font-size: 0.8rem;
    color: #6b7280;
}

/* Table Styles */
.table__container {
    margin-bottom: 2rem;
}

.billing__table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.billing__table th,
.billing__table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.billing__table th {
    background: #f9fafb;
    font-weight: 600;
    color: #1f2937;
}

.billing__table td {
    color: #374151;
}

.billing__table tr:hover {
    background: #f3f4f6;
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
    
    .overview__cards {
        grid-template-columns: 1fr;
    }
    
    .billing__table {
        display: block;
        overflow-x: auto;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line');
    });

    // Highlight active link
    const links = document.querySelectorAll('.sidebar__link');
    links.forEach(link => {
        link.addEventListener('click', function() {
            links.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        });
    });
});
</script>

<?php include 'templates/footer.php'; ?>