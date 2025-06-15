<?php
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
include_once 'includes/functions.php';

// Get customer details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'customer'");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$customer_name = $user['name'] ?? 'Customer';
$customer_email = $user['email'] ?? 'Unknown';

// Initialize dashboard metrics
$metrics = [
    'upcoming_reservations' => 0,
    'pending_payments' => 0,
    'past_reservations' => 0,
    'residential_suites' => 0,
    'additional_charges' => 0.00
];

try {
    // Upcoming reservations
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM reservations 
        WHERE user_id = ? AND check_in_date >= CURDATE() AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$user_id]);
    $metrics['upcoming_reservations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Pending payments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $metrics['pending_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Past reservations
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM reservations 
        WHERE user_id = ? AND check_out_date < CURDATE()
    ");
    $stmt->execute([$user_id]);
    $metrics['past_reservations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Residential suites
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM residential_suites 
        WHERE user_id = ? AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$user_id]);
    $metrics['residential_suites'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Additional charges (from payments for additional services)
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total 
        FROM payments 
        WHERE user_id = ? AND reservation_id IS NOT NULL AND status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $metrics['additional_charges'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00;

} catch (PDOException $e) {
    $db_error = "Database connection error: " . $e->getMessage();
    $metrics = array_fill_keys(array_keys($metrics), 0);
}

include 'templates/header.php';
?>

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
                <li>
                    <a href="customer_dashboard.php" class="sidebar__link active">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="make_reservation.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Make Reservation</span>
                    </a>
                </li>
                <li>
                    <a href="customer_manage_reservations.php" class="sidebar__link">
                        <i class="ri-calendar-line"></i>
                        <span>Manage Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="customer_additional_services.php" class="sidebar__link">
                        <i class="ri-service-line"></i>
                        <span>Additional Services</span>
                    </a>
                </li>
                <li>
                    <a href="billing_payments.php" class="sidebar__link">
                        <i class="ri-wallet-line"></i>
                        <span>Billing & Payments</span>
                    </a>
                </li>
                <!-- <li>
                    <a href="check_in_out.php" class="sidebar__link">
                        <i class="ri-hotel-line"></i>
                        <span>Check-In/Out</span>
                    </a>
                </li> -->
                <li>
                    <a href="customer_profile.php" class="sidebar__link">
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
            <h1 class="section__header">Welcome, <?php echo htmlspecialchars($customer_name); ?></h1>
            <div class="user__info">
                <span><?php echo htmlspecialchars($customer_email); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <!-- Overview Section -->
        <section id="overview" class="dashboard__section active">
            <h2 class="section__subheader">Your Overview</h2>
            <?php if (isset($db_error)): ?>
                <p class="error"><?php echo htmlspecialchars($db_error); ?></p>
            <?php endif; ?>
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-calendar-check-line card__icon"></i>
                    <div class="card__content">
                        <h3>Upcoming Reservations</h3>
                        <p><?php echo $metrics['upcoming_reservations']; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-money-dollar-circle-line card__icon"></i>
                    <div class="card__content">
                        <h3>Pending Payments</h3>
                        <p><?php echo $metrics['pending_payments']; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-history-line card__icon"></i>
                    <div class="card__content">
                        <h3>Past Reservations</h3>
                        <p><?php echo $metrics['past_reservations']; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-home-heart-line card__icon"></i>
                    <div class="card__content">
                        <h3>Residential Suites</h3>
                        <p><?php echo $metrics['residential_suites']; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-wallet-line card__icon"></i>
                    <div class="card__content">
                        <h3>Additional Charges</h3>
                        <p>$<?php echo number_format($metrics['additional_charges'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <!-- <div class="quick-actions">
                <h3 class="section__subheader">Quick Actions</h3>
                <div class="action__buttons">
                    <a href="make_reservation.php" class="action__button">New Reservation</a>
                    <a href="check_in_out.php" class="action__button">Check-In/Out</a>
                    <a href="customer_additional_services.php" class="action__button">Request Service</a>
                    <a href="billing_payments.php" class="action__button">Pay Now</a>
                </div>
            </div> -->
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

<?php
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
include_once 'includes/functions.php';

// Get customer details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'customer'");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$customer_name = $user['name'] ?? 'Customer';
$customer_email = $user['email'] ?? 'Unknown';

// Initialize dashboard metrics
$metrics = [
    'upcoming_reservations' => 0,
    'pending_payments' => 0,
    'past_reservations' => 0,
    'residential_suites' => 0,
    'additional_charges' => 0.00
];

try {
    // Upcoming reservations
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM reservations 
        WHERE user_id = ? AND check_in_date >= CURDATE() AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$user_id]);
    $metrics['upcoming_reservations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Pending payments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $metrics['pending_payments'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Past reservations
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM reservations 
        WHERE user_id = ? AND check_out_date < CURDATE()
    ");
    $stmt->execute([$user_id]);
    $metrics['past_reservations'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Residential suites
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM residential_suites 
        WHERE user_id = ? AND status IN ('pending', 'confirmed')
    ");
    $stmt->execute([$user_id]);
    $metrics['residential_suites'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Additional charges (from payments for additional services)
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total 
        FROM payments 
        WHERE user_id = ? AND reservation_id IS NOT NULL AND status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $metrics['additional_charges'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0.00;

} catch (PDOException $e) {
    $db_error = "Database connection error: " . $e->getMessage();
    $metrics = array_fill_keys(array_keys($metrics), 0);
}

include 'templates/header.php';
?>

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
                <li>
                    <a href="customer_dashboard.php" class="sidebar__link active">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="make_reservation.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Make Reservation</span>
                    </a>
                </li>
                <li>
                    <a href="customer_manage_reservations.php" class="sidebar__link">
                        <i class="ri-calendar-line"></i>
                        <span>Manage Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="customer_additional_services.php" class="sidebar__link">
                        <i class="ri-service-line"></i>
                        <span>Additional Services</span>
                    </a>
                </li>
                <li>
                    <a href="billing_payments.php" class="sidebar__link">
                        <i class="ri-wallet-line"></i>
                        <span>Billing & Payments</span>
                    </a>
                </li>
                <li>
                    <a href="check_in_out.php" class="sidebar__link">
                        <i class="ri-hotel-line"></i>
                        <span>Check-In/Out</span>
                    </a>
                </li>
                <li>
                    <a href="customer_profile.php" class="sidebar__link">
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
            <h1 class="section__header">Welcome, <?php echo htmlspecialchars($customer_name); ?></h1>
            <div class="user__info">
                <span><?php echo htmlspecialchars($customer_email); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <!-- Overview Section -->
        <section id="overview" class="dashboard__section active">
            <h2 class="section__subheader">Your Overview</h2>
            <?php if (isset($db_error)): ?>
                <p class="error"><?php echo htmlspecialchars($db_error); ?></p>
            <?php endif; ?>
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-calendar-check-line card__icon"></i>
                    <div class="card__content">
                        <h3>Upcoming Reservations</h3>
                        <p><?php echo $metrics['upcoming_reservations']; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-money-dollar-circle-line card__icon"></i>
                    <div class="card__content">
                        <h3>Pending Payments</h3>
                        <p><?php echo $metrics['pending_payments']; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-history-line card__icon"></i>
                    <div class="card__content">
                        <h3>Past Reservations</h3>
                        <p><?php echo $metrics['past_reservations']; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-home-heart-line card__icon"></i>
                    <div class="card__content">
                        <h3>Residential Suites</h3>
                        <p><?php echo $metrics['residential_suites']; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-wallet-line card__icon"></i>
                    <div class="card__content">
                        <h3>Additional Charges</h3>
                        <p>$<?php echo number_format($metrics['additional_charges'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h3 class="section__subheader">Quick Actions</h3>
                <div class="action__buttons">
                    <a href="make_reservation.php" class="action__button">New Reservation</a>
                    <a href="check_in_out.php" class="action__button">Check-In/Out</a>
                    <a href="customer_additional_services.php" class="action__button">Request Service</a>
                    <a href="billing_payments.php" class="action__button">Pay Now</a>
                </div>
            </div>
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

/* Quick Actions */
.quick-actions {
    margin-top: 2rem;
}

.action__buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.action__button {
    background: #3b82f6;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: background 0.2s ease;
}

.action__button:hover {
    background: #2563eb;
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