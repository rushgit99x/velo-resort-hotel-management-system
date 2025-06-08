<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to managers only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Get manager's branch_id and branch name
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.branch_id, b.name AS branch_name 
                      FROM users u 
                      LEFT JOIN branches b ON u.branch_id = b.id 
                      WHERE u.id = ? AND u.role = 'manager'");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_id = $result['branch_id'] ?? 0;
$branch_name = $result['branch_name'] ?? 'Unknown Branch';

if (!$branch_id) {
    $db_error = "No branch assigned to this manager.";
}

// Initialize metrics
$total_invoiced = 0;
$total_paid = 0;
$outstanding_balance = 0;
$recent_invoices = [];
$recent_payments = [];

try {
    // First, let's create invoices for travel company reservations that don't have invoices yet
    // This ensures we have billing data to display
    $stmt = $pdo->prepare("
        INSERT INTO invoices (company_id, amount, status, issued_at, due_date)
        SELECT DISTINCT 
            r.user_id as company_id,
            (r.number_of_rooms * rt.base_price * DATEDIFF(r.check_out_date, r.check_in_date)) + COALESCE(b.additional_fee, 0) as amount,
            CASE 
                WHEN r.payment_status = 'paid' THEN 'paid'
                WHEN r.check_out_date < CURDATE() THEN 'overdue'
                ELSE 'pending'
            END as status,
            r.created_at as issued_at,
            DATE_ADD(r.check_out_date, INTERVAL 30 DAY) as due_date
        FROM reservations r
        JOIN users u ON r.user_id = u.id AND u.role = 'travel_company'
        LEFT JOIN room_types rt ON rt.name = r.room_type
        LEFT JOIN billings b ON b.reservation_id = r.id
        WHERE r.hotel_id = ? 
        AND NOT EXISTS (SELECT 1 FROM invoices i WHERE i.company_id = r.user_id AND i.amount = (r.number_of_rooms * rt.base_price * DATEDIFF(r.check_out_date, r.check_in_date)) + COALESCE(b.additional_fee, 0))
    ");
    $stmt->execute([$branch_id]);

    // Total invoiced amount (from invoices for travel companies with reservations at this branch)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(DISTINCT i.amount), 0) as total
        FROM invoices i
        JOIN users u ON i.company_id = u.id AND u.role = 'travel_company'
        WHERE i.company_id IN (
            SELECT DISTINCT r.user_id 
            FROM reservations r 
            WHERE r.hotel_id = ?
        )
    ");
    $stmt->execute([$branch_id]);
    $total_invoiced = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Total paid amount (from payments for reservations at this branch)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.amount), 0) as total
        FROM payments p
        JOIN reservations r ON p.reservation_id = r.id
        WHERE r.hotel_id = ? AND p.status = 'completed'
    ");
    $stmt->execute([$branch_id]);
    $total_paid = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Outstanding balance (pending or overdue invoices for travel companies with reservations at this branch)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(DISTINCT i.amount), 0) as total
        FROM invoices i
        JOIN users u ON i.company_id = u.id AND u.role = 'travel_company'
        WHERE i.company_id IN (
            SELECT DISTINCT r.user_id 
            FROM reservations r 
            WHERE r.hotel_id = ?
        )
        AND i.status IN ('pending', 'overdue')
    ");
    $stmt->execute([$branch_id]);
    $outstanding_balance = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // Recent invoices (last 30 days) for travel companies with reservations at this branch
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            i.id, 
            i.amount, 
            i.status, 
            i.due_date,
            COALESCE(cp.company_name, u.name) as company_name,
            i.issued_at
        FROM invoices i
        JOIN users u ON i.company_id = u.id AND u.role = 'travel_company'
        LEFT JOIN company_profiles cp ON i.company_id = cp.user_id
        WHERE i.company_id IN (
            SELECT DISTINCT r.user_id 
            FROM reservations r 
            WHERE r.hotel_id = ?
        )
        AND i.issued_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY i.issued_at DESC
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $recent_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent payments (last 30 days) for reservations at this branch
    $stmt = $pdo->prepare("
        SELECT 
            p.id, 
            p.amount, 
            p.payment_method, 
            p.card_last_four, 
            p.status, 
            p.created_at,
            u.name as customer_name,
            CASE 
                WHEN u.role = 'travel_company' THEN COALESCE(cp.company_name, u.name)
                ELSE u.name
            END as payer_name
        FROM payments p
        JOIN reservations r ON p.reservation_id = r.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN company_profiles cp ON p.user_id = cp.user_id AND u.role = 'travel_company'
        WHERE r.hotel_id = ? 
        AND p.status = 'completed'
        AND p.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$branch_id]);
    $recent_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $db_error = "Database connection error: " . $e->getMessage();
    $total_invoiced = $total_paid = $outstanding_balance = 0;
    $recent_invoices = $recent_payments = [];
}

include 'templates/header.php';
?>

<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Manager Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li>
                    <a href="manager_dashboard.php" class="sidebar__link">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="occupancy_reports.php" class="sidebar__link">
                        <i class="ri-bar-chart-line"></i>
                        <span>Occupancy Reports</span>
                    </a>
                </li>
                <li>
                    <a href="financial_reports.php" class="sidebar__link">
                        <i class="ri-money-dollar-circle-line"></i>
                        <span>Financial Reports</span>
                    </a>
                </li>
                <li>
                    <a href="projected_occupancy.php" class="sidebar__link">
                        <i class="ri-calendar-2-line"></i>
                        <span>Projected Occupancy</span>
                    </a>
                </li>
                <li>
                    <a href="daily_reports.php" class="sidebar__link">
                        <i class="ri-file-chart-line"></i>
                        <span>Daily Reports</span>
                    </a>
                </li>
                <li>
                    <a href="billing_summary.php" class="sidebar__link active">
                        <i class="ri-wallet-line"></i>
                        <span>Billing Summary</span>
                    </a>
                </li>
                <li>
                    <a href="manage_branch_bookings.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Manage Bookings</span>
                    </a>
                </li>
                <li>
                    <a href="clerk_profile.php" class="sidebar__link">
                        <i class="ri-user-settings-line"></i>
                        <span>Manage Clerks</span>
                    </a>
                </li>
                <li>
                    <a href="manager_settings.php" class="sidebar__link">
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
            <h1 class="section__header"><?php echo htmlspecialchars($branch_name); ?> - Billing Summary</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Manager'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <!-- Billing Summary Section -->
        <section id="billing-summary" class="dashboard__section active">
            <h1><b>Billing Overview</b></h1>
            <?php if (isset($db_error)): ?>
                <div class="alert alert-error">
                    <i class="ri-error-warning-line"></i>
                    <span><?php echo htmlspecialchars($db_error); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-money-dollar-circle-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Invoiced</h3>
                        <p class="amount">$<?php echo number_format($total_invoiced, 2); ?></p>
                        <small>All invoices generated</small>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-check-double-line card__icon success"></i>
                    <div class="card__content">
                        <h3>Total Paid</h3>
                        <p class="amount">$<?php echo number_format($total_paid, 2); ?></p>
                        <small>Completed payments</small>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-alert-line card__icon warning"></i>
                    <div class="card__content">
                        <h3>Outstanding Balance</h3>
                        <p class="amount">$<?php echo number_format($outstanding_balance, 2); ?></p>
                        <small>Pending & overdue invoices</small>
                    </div>
                </div>
            </div>

            <!-- Recent Invoices -->
            <div class="billing__section">
                <h1>
                    <b>
                    Recent Invoices (Last 30 Days)
                    </b>
                </h1>
                <?php if (empty($recent_invoices)): ?>
                    <div class="empty__state">
                        <i class="ri-file-list-line"></i>
                        <p>No recent invoices found for this branch.</p>
                        <small>Invoices will appear here when travel companies make bookings.</small>
                    </div>
                <?php else: ?>
                    <div class="table__container">
                        <table class="billing__table">
                            <thead>
                                <tr>
                                    <th>Invoice ID</th>
                                    <th>Company</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Issue Date</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_invoices as $invoice): ?>
                                    <tr>
                                        <td class="invoice-id">#<?php echo htmlspecialchars($invoice['id']); ?></td>
                                        <td><?php echo htmlspecialchars($invoice['company_name']); ?></td>
                                        <td class="amount">$<?php echo number_format($invoice['amount'], 2); ?></td>
                                        <td>
                                            <span class="status status--<?php echo strtolower($invoice['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($invoice['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($invoice['issued_at'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($invoice['due_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Payments -->
            <div class="billing__section">
                <h1><b>
                    <!-- <i class="ri-secure-payment-line"></i> -->
                    Recent Payments (Last 30 Days)
                </b>
                </h1>
                <?php if (empty($recent_payments)): ?>
                    <div class="empty__state">
                        <i class="ri-secure-payment-line"></i>
                        <p>No recent payments found for this branch.</p>
                        <small>Payment records will appear here when customers complete transactions.</small>
                    </div>
                <?php else: ?>
                    <div class="table__container">
                        <table class="billing__table">
                            <thead>
                                <tr>
                                    <th>Payment ID</th>
                                    <th>Customer/Company</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Card Last Four</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_payments as $payment): ?>
                                    <tr>
                                        <td class="payment-id">#<?php echo htmlspecialchars($payment['id']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['payer_name']); ?></td>
                                        <td class="amount">$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td class="payment-method">
                                            <i class="ri-bank-card-line"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($payment['payment_method']))); ?>
                                        </td>
                                        <td><?php echo $payment['card_last_four'] ? '****' . htmlspecialchars($payment['card_last_four']) : 'N/A'; ?></td>
                                        <td>
                                            <span class="status status--<?php echo strtolower($payment['status']); ?>">
                                                <?php echo ucfirst(htmlspecialchars($payment['status'])); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($payment['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<style>
/* Enhanced styles for the billing summary page */
.overview__cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin: 2rem 0;
}

.overview__card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1.5rem;
    transition: all 0.3s ease;
    border: 1px solid #e2e8f0;
}

.overview__card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 25px rgba(0, 0, 0, 0.1), 0 4px 6px rgba(0, 0, 0, 0.05);
}

.card__icon {
    font-size: 2.5rem;
    background: #eff6ff;
    color: #3b82f6;
    padding: 1.2rem;
    border-radius: 50%;
    min-width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card__icon.success {
    background: #f0fdf4;
    color: #22c55e;
}

.card__icon.warning {
    background: #fffbeb;
    color: #f59e0b;
}

.card__content h3 {
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.card__content .amount {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0;
    line-height: 1.2;
}

.card__content small {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 0.25rem;
    display: block;
}

.section__subheader {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin: 2rem 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.section__subheader i {
    color: #3b82f6;
}

.billing__section {
    margin: 3rem 0;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin: 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-error {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.empty__state {
    background: #f9fafb;
    border: 2px dashed #d1d5db;
    border-radius: 12px;
    padding: 3rem 2rem;
    text-align: center;
    color: #6b7280;
}

.empty__state i {
    font-size: 3rem;
    color: #d1d5db;
    margin-bottom: 1rem;
}

.empty__state p {
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
    color: #4b5563;
}

.table__container {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05), 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
}

.billing__table {
    width: 100%;
    border-collapse: collapse;
}

.billing__table th,
.billing__table td {
    padding: 1rem 1.5rem;
    text-align: left;
    border-bottom: 1px solid #f3f4f6;
}

.billing__table th {
    background: #f8fafc;
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.billing__table tbody tr {
    transition: background-color 0.2s ease;
}

.billing__table tbody tr:hover {
    background: #f9fafb;
}

.billing__table tbody tr:last-child td {
    border-bottom: none;
}

.invoice-id, .payment-id {
    font-family: 'Courier New', monospace;
    font-weight: 600;
    color: #3b82f6;
}

.amount {
    font-weight: 600;
    color: #059669;
}

.status {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.status--pending {
    background: #fef3c7;
    color: #d97706;
}

.status--paid, .status--completed {
    background: #d1fae5;
    color: #059669;
}

.status--overdue, .status--failed {
    background: #fee2e2;
    color: #dc2626;
}

.payment-method {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.payment-method i {
    color: #6b7280;
}

/* Responsive design */
@media (max-width: 768px) {
    .overview__cards {
        grid-template-columns: 1fr;
    }
    
    .overview__card {
        padding: 1.5rem;
        gap: 1rem;
    }
    
    .card__icon {
        min-width: 48px;
        height: 48px;
        font-size: 1.5rem;
        padding: 0.75rem;
    }
    
    .card__content .amount {
        font-size: 1.5rem;
    }
    
    .table__container {
        overflow-x: auto;
    }
    
    .billing__table {
        min-width: 600px;
    }
    
    .billing__table th,
    .billing__table td {
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
    }
}

/* Sidebar styles */
.sidebar__link.active {
    background: #3b82f6;
    color: white;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle functionality
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
        });
    }
    
    // Add smooth scrolling to section headers
    const sectionHeaders = document.querySelectorAll('.section__subheader');
    sectionHeaders.forEach(header => {
        header.style.cursor = 'default';
    });
    
    // Auto-refresh functionality (optional)
    const refreshInterval = 300000; // 5 minutes
    setTimeout(function() {
        if (confirm('Refresh billing data to see latest updates?')) {
            location.reload();
        }
    }, refreshInterval);
});
</script>

<?php include 'templates/footer.php'; ?>