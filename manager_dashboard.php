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

// Dashboard metrics
$total_rooms = 0;
$occupied_rooms = 0;
$total_bookings = 0;
$total_reservations = 0;
$daily_revenue = 0;
$occupancy_rate = 0;

try {
    // Total rooms in the branch
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rooms WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Occupied rooms
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rooms WHERE branch_id = ? AND status = 'occupied'");
    $stmt->execute([$branch_id]);
    $occupied_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Occupancy rate
    $occupancy_rate = $total_rooms > 0 ? round(($occupied_rooms / $total_rooms) * 100, 2) : 0;

    // Total bookings
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Total reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM reservations WHERE hotel_id = ?");
    $stmt->execute([$branch_id]);
    $total_reservations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Daily revenue (based on bookings for today)
    $stmt = $pdo->prepare("
        SELECT SUM(rt.base_price) as revenue
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.branch_id = ? AND b.check_in = CURDATE()
    ");
    $stmt->execute([$branch_id]);
    $daily_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

} catch (PDOException $e) {
    $db_error = "Database connection error: " . $e->getMessage();
    $total_rooms = $occupied_rooms = $total_bookings = $total_reservations = $daily_revenue = $occupancy_rate = 0;
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
                    <a href="manager_dashboard.php" class="sidebar__link active">
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
                    <a href="billing_summary.php" class="sidebar__link">
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
            <h1 class="section__header"><?php echo htmlspecialchars($branch_name); ?></h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Manager'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <!-- Overview Section -->
        <section id="overview" class="dashboard__section active">
            <h2 class="section__subheader">Branch Overview</h2>
            <?php if (isset($db_error)): ?>
                <p class="error"><?php echo htmlspecialchars($db_error); ?></p>
            <?php endif; ?>
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-home-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Rooms</h3>
                        <p><?php echo $total_rooms; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-home-fill card__icon"></i>
                    <div class="card__content">
                        <h3>Occupied Rooms</h3>
                        <p><?php echo $occupied_rooms; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-percent-line card__icon"></i>
                    <div class="card__content">
                        <h3>Occupancy Rate</h3>
                        <p><?php echo $occupancy_rate; ?>%</p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-calendar-check-line card__icon"></i>
                    <div class="card__content">
                        <h3>Bookings</h3>
                        <p><?php echo $total_bookings; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-calendar-line card__icon"></i>
                    <div class="card__content">
                        <h3>Reservations</h3>
                        <p><?php echo $total_reservations; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-money-dollar-circle-line card__icon"></i>
                    <div class="card__content">
                        <h3>Daily Revenue</h3>
                        <p>$<?php echo number_format($daily_revenue, 2); ?></p>
                    </div>
                </div>
            </div>
        </section>
    </main>
</div>

<style>
/* Styles for the dashboard */
.overview__cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.overview__card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.overview__card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
}

.card__icon {
    font-size: 2.5rem;
    color: #3b82f6;
    background: #eff6ff;
    padding: 1rem;
    border-radius: 50%;
    min-width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card__content h3 {
    font-size: 1rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.card__content p {
    font-size: 2rem;
    font-weight: bold;
    color: #1f2937;
}

.section__subheader {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.dashboard__section.active {
    display: block;
}

.error {
    color: red;
    margin-bottom: 1rem;
}

/* Sidebar styles */
.sidebar__link.active {
    background: #3b82f6;
    color: white;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });
});
</script>

<?php include 'templates/footer.php'; ?>