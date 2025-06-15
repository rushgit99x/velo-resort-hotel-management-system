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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>
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
            <button class="mobile-sidebar-toggle" id="mobile-sidebar-toggle">
                <i class="ri-menu-line"></i>
            </button>
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
/* General Reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Dashboard Container */
.dashboard__container {
    display: flex;
    min-height: 100vh;
    background: #f3f4f6;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background: #000000;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    position: fixed;
    height: 100vh;
    z-index: 1000;
    transform: translateX(0);
}

.sidebar.collapsed {
    transform: translateX(-250px);
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-250px);
    }
    .sidebar.collapsed {
        transform: translateX(-250px);
    }
    .sidebar:not(.collapsed) {
        transform: translateX(0);
    }
}

.sidebar__header {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #333333;
}

.sidebar__logo {
    width: 40px;
    height: 40px;
    margin-right: 0.5rem;
}

.sidebar__title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #ffffff;
}

.sidebar__toggle {
    background: none;
    border: none;
    cursor: pointer;
    margin-left: auto;
    font-size: 1.5rem;
    color: #3b82f6;
}

.sidebar__nav {
    padding: 1rem 0;
}

.sidebar__links {
    list-style: none;
}

.sidebar__link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: #ffffff;
    text-decoration: none;
    font-size: 0.95rem;
    transition: background 0.2s ease;
}

.sidebar__link:hover {
    background:rgba(232,37,116,255);
}

.sidebar__link.active {
    background: #3b82f6;
    color: #ffffff;
}

.sidebar__link i {
    font-size: 1.25rem;
    margin-right: 0.75rem;
}

@media (max-width: 768px) {
    .sidebar__title,
    .sidebar__link span {
        display: inline;
    }
    .sidebar__logo {
        margin-right: 0.5rem;
    }
}

/* Main Content */
.dashboard__content {
    margin-left: 250px;
    padding: 1.5rem;
    flex-grow: 1;
    transition: margin-left 0.3s ease;
}

@media (max-width: 768px) {
    .dashboard__content {
        margin-left: 0;
    }
}

/* Header */
.dashboard__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.mobile-sidebar-toggle {
    display: none;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #3b82f6;
    cursor: pointer;
    padding: 0.5rem;
}

.section__header {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2937;
}

.user__info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.user__avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
}

/* Overview Section */
.section__subheader {
    font-size: 1.3rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.overview__cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.overview__card {
    background: white;
    padding: 1rem;
    border-radius: 8px;
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
    margin-bottom: 0.25rem;
}

.card__content p {
    font-size: 1.5rem;
    font-weight: bold;
    color: #1f2937;
}

.dashboard__section.active {
    display: block;
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .mobile-sidebar-toggle {
        display: block;
    }

    .sidebar__toggle {
        display: none;
    }

    .overview__cards {
        grid-template-columns: 1fr;
    }

    .section__header {
        font-size: 1.5rem;
    }

    .section__subheader {
        font-size: 1.2rem;
    }

    .overview__card {
        padding: 1rem;
    }

    .card__icon {
        font-size: 1.5rem;
        min-width: 40px;
        height: 40px;
        padding: 0.5rem;
    }

    .card__content h3 {
        font-size: 0.85rem;
    }

    .card__content p {
        font-size: 1.25rem;
    }
}

@media (max-width: 480px) {
    .dashboard__content {
        padding: 1rem;
    }

    .dashboard__header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .user__info {
        font-size: 0.9rem;
    }

    .user__avatar {
        width: 1.5rem;
        height: 1.5rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');

    // Toggle sidebar function
    function toggleSidebar() {
        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        // Update icons
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line', !isCollapsed);
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line', isCollapsed);
        mobileSidebarToggle.querySelector('i').classList.toggle('ri-menu-line', isCollapsed);
        mobileSidebarToggle.querySelector('i').classList.toggle('ri-close-line', !isCollapsed);
        // Adjust margin-left for content when sidebar is visible on mobile
        const dashboardContent = document.querySelector('.dashboard__content');
        if (window.innerWidth <= 768) {
            dashboardContent.style.marginLeft = isCollapsed ? '0' : '250px';
        }
    }

    // Event listeners for both toggles
    sidebarToggle?.addEventListener('click', toggleSidebar);
    mobileSidebarToggle?.addEventListener('click', toggleSidebar);

    // Auto-collapse sidebar on mobile and set initial state
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
        mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
        document.querySelector('.dashboard__content').style.marginLeft = '0';
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
            mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
            document.querySelector('.dashboard__content').style.marginLeft = '0';
        } else {
            sidebar.classList.remove('collapsed');
            mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
            mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
            document.querySelector('.dashboard__content').style.marginLeft = '250px';
        }
    });
});
</script>

<?php include 'templates/footer.php'; ?>
</body>
</html>