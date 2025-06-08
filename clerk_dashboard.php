<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to clerks only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clerk') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Get clerk's branch_id and branch name
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.branch_id, b.name AS branch_name 
                      FROM users u 
                      LEFT JOIN branches b ON u.branch_id = b.id 
                      WHERE u.id = ? AND u.role = 'clerk'");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_id = $result['branch_id'] ?? 0;
$branch_name = $result['branch_name'] ?? 'Unknown Branch';

if (!$branch_id) {
    $db_error = "No branch assigned to this clerk.";
}

// Dashboard metrics
$available_rooms = 0;
$pending_checkins = 0;
$todays_checkouts = 0;

try {
    // Available rooms in the branch
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rooms WHERE branch_id = ? AND status = 'available'");
    $stmt->execute([$branch_id]);
    $available_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Pending check-ins (bookings for today with 'pending' status)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE branch_id = ? AND check_in = CURDATE() AND status = 'pending'");
    $stmt->execute([$branch_id]);
    $pending_checkins = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Today's check-outs (bookings with check-out date as today)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE branch_id = ? AND check_out = CURDATE() AND status = 'confirmed'");
    $stmt->execute([$branch_id]);
    $todays_checkouts = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

} catch (PDOException $e) {
    $db_error = "Database connection error: " . $e->getMessage();
    $available_rooms = $pending_checkins = $todays_checkouts = 0;
}

include 'templates/header.php';
?>

<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Clerk Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li>
                    <a href="clerk_dashboard.php" class="sidebar__link active">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <!-- <li>
                    <a href="check_in.php" class="sidebar__link">
                        <i class="ri-login-box-line"></i>
                        <span>Check-In Customers</span>
                    </a>
                </li> -->
                <li>
                    <a href="manage_reservations.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Manage Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="manage_check_in_out.php" class="sidebar__link">
                        <i class="ri-logout-box-line"></i>
                        <span>Check-In/Out Customers</span>
                    </a>
                </li>
                <li>
                    <a href="room_availability.php" class="sidebar__link">
                        <i class="ri-home-line"></i>
                        <span>Room Availability</span>
                    </a>
                </li>
                <li>
                    <a href="create_customer.php" class="sidebar__link">
                        <i class="ri-user-add-line"></i>
                        <span>Create Customer</span>
                    </a>
                </li>
                <li>
                    <a href="billing_statements.php" class="sidebar__link">
                        <i class="ri-wallet-line"></i>
                        <span>Billing Statements</span>
                    </a>
                </li>
                <li>
                    <a href="clerk_settings.php" class="sidebar__link">
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
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Clerk'); ?></span>
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
                        <h3>Available Rooms</h3>
                        <p><?php echo $available_rooms; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-login-box-line card__icon"></i>
                    <div class="card__content">
                        <h3>Pending Check-Ins</h3>
                        <p><?php echo $pending_checkins; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-logout-box-line card__icon"></i>
                    <div class="card__content">
                        <h3>Today's Check-Outs</h3>
                        <p><?php echo $todays_checkouts; ?></p>
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