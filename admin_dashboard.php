<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to super admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Dashboard metrics
$total_branches = 0;
$total_users = 0;
$total_bookings = 0;
$total_rooms = 0;
$total_reservations = 0;
$total_managers = 0;
$total_customers = 0;
$total_travel_companies = 0;
$total_room_types = 0;

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM branches");
    $total_branches = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role != 'super_admin'");
    $total_users = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'manager'");
    $total_managers = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'customer'");
    $total_customers = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'travel_company'");
    $total_travel_companies = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM rooms");
    $total_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM room_types");
    $total_room_types = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM bookings");
    $total_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM reservations");
    $total_reservations = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

} catch (PDOException $e) {
    $db_error = "Database connection error: " . $e->getMessage();
    $total_branches = $total_users = $total_bookings = $total_rooms = $total_reservations = 0;
    $total_managers = $total_customers = $total_travel_companies = $total_room_types = 0;
}

include 'templates/header.php';
?>

<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Super Admin</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li>
                    <a href="admin_dashboard.php" class="sidebar__link active">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="create_branch.php" class="sidebar__link">
                        <i class="ri-building-line"></i>
                        <span>Create Branch</span>
                    </a>
                </li>
                <li>
                    <a href="create_user.php" class="sidebar__link">
                        <i class="ri-user-add-line"></i>
                        <span>Create User</span>
                    </a>
                </li>
                <li>
                    <a href="create_room.php" class="sidebar__link">
                        <i class="ri-home-line"></i>
                        <span>Add Room</span>
                    </a>
                </li>
                <li>
                    <a href="manage_rooms.php" class="sidebar__link">
                        <i class="ri-home-gear-line"></i>
                        <span>Manage Rooms</span>
                    </a>
                </li>
                <li>
                    <a href="create_room_type.php" class="sidebar__link">
                        <i class="ri-home-2-line"></i>
                        <span>Manage Room Types</span>
                    </a>
                </li>
                <li>
                    <a href="manage_hotels.php" class="sidebar__link">
                        <i class="ri-building-line"></i>
                        <span>Manage Hotels</span>
                    </a>
                </li>
                <li>
                    <a href="manage_users.php" class="sidebar__link">
                        <i class="ri-user-line"></i>
                        <span>Manage Users</span>
                    </a>
                </li>
                <li>
                    <a href="manage_bookings.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Manage Bookings</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="sidebar__link">
                        <i class="ri-bar-chart-line"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="sidebar__link">
                        <i class="ri-settings-3-line"></i>
                        <span>Settings</span>
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
            <h1 class="section__header">Super Admin Dashboard</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <!-- Overview Section -->
        <section id="overview" class="dashboard__section active">
            <h2 class="section__subheader">Overview</h2>
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-building-2-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Branches</h3>
                        <p><?php echo $total_branches; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-home-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Rooms</h3>
                        <p><?php echo $total_rooms; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-home-2-line card__icon"></i>
                    <div class="card__content">
                        <h3>Room Types</h3>
                        <p><?php echo $total_room_types; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-user-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Users</h3>
                        <p><?php echo $total_users; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-user-settings-line card__icon"></i>
                    <div class="card__content">
                        <h3>Managers</h3>
                        <p><?php echo $total_managers; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-user-heart-line card__icon"></i>
                    <div class="card__content">
                        <h3>Customers</h3>
                        <p><?php echo $total_customers; ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-building-3-line card__icon"></i>
                    <div class="card__content">
                        <h3>Travel Companies</h3>
                        <p><?php echo $total_travel_companies; ?></p>
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