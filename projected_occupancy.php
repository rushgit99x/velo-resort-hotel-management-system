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

// Initialize variables
$total_rooms = 0;
$projected_occupancy = [];
$start_date = new DateTime();
$end_date = (new DateTime())->modify('+7 days');

try {
    // Total rooms in the branch
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rooms WHERE branch_id = ?");
    $stmt->execute([$branch_id]);
    $total_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Projected occupancy for the next 7 days
    $stmt = $pdo->prepare("
        SELECT DATE(check_in) as date, COUNT(*) as booked_rooms
        FROM bookings
        WHERE branch_id = ? AND check_in BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
        GROUP BY DATE(check_in)
        UNION
        SELECT DATE(check_in_date) as date, COUNT(*) as booked_rooms
        FROM reservations
        WHERE hotel_id = ? AND check_in_date BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
        GROUP BY DATE(check_in_date)
    ");
    $stmt->execute([$branch_id, $branch_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize occupancy array for the next 7 days
    for ($date = clone $start_date; $date <= $end_date; $date->modify('+1 day')) {
        $projected_occupancy[$date->format('Y-m-d')] = [
            'date' => $date->format('Y-m-d'),
            'day' => $date->format('l, M d'),
            'booked_rooms' => 0,
            'occupancy_rate' => 0
        ];
    }

    // Aggregate booked rooms
    foreach ($results as $row) {
        $date = $row['date'];
        if (isset($projected_occupancy[$date])) {
            $projected_occupancy[$date]['booked_rooms'] += $row['booked_rooms'];
            $projected_occupancy[$date]['occupancy_rate'] = $total_rooms > 0 ? round(($projected_occupancy[$date]['booked_rooms'] / $total_rooms) * 100, 2) : 0;
        }
    }

} catch (PDOException $e) {
    $db_error = "Database connection error: " . $e->getMessage();
    $total_rooms = 0;
    $projected_occupancy = [];
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
                    <a href="projected_occupancy.php" class="sidebar__link active">
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
                    <a href="manage_bookings.php" class="sidebar__link">
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

        <!-- Projected Occupancy Section -->
        <section id="projected-occupancy" class="dashboard__section active">
            <h2 class="section__subheader">Projected Occupancy (Next 7 Days)</h2>
            <?php if (isset($db_error)): ?>
                <p class="error"><?php echo htmlspecialchars($db_error); ?></p>
            <?php endif; ?>
            <div class="overview__cards">
                <?php foreach ($projected_occupancy as $data): ?>
                    <div class="overview__card">
                        <i class="ri-calendar-2-line card__icon"></i>
                        <div class="card__content">
                            <h3><?php echo htmlspecialchars($data['day']); ?></h3>
                            <p><?php echo $data['booked_rooms']; ?> / <?php echo $total_rooms; ?> rooms</p>
                            <p><?php echo $data['occupancy_rate']; ?>% occupancy</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>
</div>

<style>
/* Styles for the projected occupancy page */
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
    font-size: 1.25rem;
    font-weight: bold;
    color: #1f2937;
    margin: 0.25rem 0;
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