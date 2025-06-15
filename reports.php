<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
checkAuth('super_admin');

require_once 'db_connect.php';

// Initialize variables
$error = null;

try {
    // Total revenue from confirmed bookings
    $stmt = $pdo->query("
        SELECT COALESCE(SUM(rt.base_price * DATEDIFF(b.check_out, b.check_in)), 0) as total_revenue 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        JOIN room_types rt ON r.room_type_id = rt.id 
        WHERE b.status = 'confirmed'
    ");
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'];

    // Occupancy rate
    $stmt = $pdo->query("
        SELECT COUNT(*) as confirmed_bookings 
        FROM bookings 
        WHERE status = 'confirmed' 
        AND check_in <= CURDATE() 
        AND check_out >= CURDATE()
    ");
    $confirmed_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['confirmed_bookings'] ?? 0;

    $stmt = $pdo->query("SELECT COUNT(*) as total_rooms FROM rooms");
    $total_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['total_rooms'] ?? 1;
    $occupancy_rate = ($total_rooms > 0) ? ($confirmed_bookings / $total_rooms) * 100 : 0;

    // Bookings by branch
    $stmt = $pdo->query("
        SELECT b.name, COALESCE(COUNT(bo.id), 0) as booking_count 
        FROM branches b 
        LEFT JOIN bookings bo ON b.id = bo.branch_id 
        GROUP BY b.id, b.name
        ORDER BY b.name
    ");
    $bookings_by_branch = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Detailed bookings
    $stmt = $pdo->query("
        SELECT 
            b.id,
            u.name as customer_name,
            u.email,
            br.name as branch_name,
            r.room_number,
            rt.name as room_type,
            b.check_in,
            b.check_out,
            b.status,
            COALESCE(rt.base_price * DATEDIFF(b.check_out, b.check_in), 0) as total_amount,
            b.created_at
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN rooms r ON b.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        JOIN branches br ON b.branch_id = br.id
        ORDER BY b.created_at DESC
        LIMIT 100
    ");
    $detailed_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Monthly revenue data
    $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(b.created_at, '%Y-%m') as month,
            COUNT(b.id) as booking_count,
            COALESCE(SUM(rt.base_price * DATEDIFF(b.check_out, b.check_in)), 0) as monthly_revenue
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.status = 'confirmed'
        GROUP BY DATE_FORMAT(b.created_at, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    $monthly_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $total_revenue = $occupancy_rate = $confirmed_bookings = $total_rooms = 0;
    $bookings_by_branch = $detailed_bookings = $monthly_data = [];
}

include 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>
<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" loading="lazy" />
            <h2 class="sidebar__title">Super Admin</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li><a href="admin_dashboard.php" class="sidebar__link"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li><a href="create_branch.php" class="sidebar__link"><i class="ri-building-line"></i><span>Create Branch</span></a></li>
                <li><a href="create_user.php" class="sidebar__link"><i class="ri-user-add-line"></i><span>Create User</span></a></li>
                <li><a href="create_room.php" class="sidebar__link"><i class="ri-home-line"></i><span>Add Room</span></a></li>
                <li><a href="manage_rooms.php" class="sidebar__link"><i class="ri-home-gear-line"></i><span>Manage Rooms</span></a></li>
                <li><a href="create_room_type.php" class="sidebar__link"><i class="ri-home-2-line"></i><span>Manage Room Types</span></a></li>
                <li><a href="manage_hotels.php" class="sidebar__link"><i class="ri-building-line"></i><span>Manage Hotels</span></a></li>
                <li><a href="manage_users.php" class="sidebar__link"><i class="ri-user-line"></i><span>Manage Users</span></a></li>
                <li><a href="manage_bookings.php" class="sidebar__link"><i class="ri-calendar-check-line"></i><span>Manage Bookings</span></a></li>
                <li><a href="reports.php" class="sidebar__link active"><i class="ri-bar-chart-line"></i><span>Reports</span></a></li>
                <li><a href="settings.php" class="sidebar__link"><i class="ri-settings-3-line"></i><span>Settings</span></a></li>
                <li><a href="logout.php" class="sidebar__link"><i class="ri-logout-box-line"></i><span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <button class="mobile-sidebar-toggle" id="mobile-sidebar-toggle">
                <i class="ri-menu-line"></i>
            </button>
            <h1 class="section__header">Reports</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" loading="lazy" />
            </div>
        </header>

        <!-- Error Messages -->
        <?php if (isset($error)): ?>
            <div class="alert alert--error">
                <i class="ri-error-warning-line"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Reports Section -->
        <section id="reports" class="dashboard__section active">
            <!-- Export Buttons -->
            <div class="export__buttons">
                <button onclick="exportToPDF()" class="btn btn--primary">
                    <i class="ri-file-pdf-line me-1"></i>Download PDF Report
                </button>
                <button onclick="exportToExcel()" class="btn btn--secondary">
                    <i class="ri-file-excel-line me-1"></i>Download Excel Report
                </button>
            </div>

            <h2 class="section__subheader">Key Metrics</h2>
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-money-dollar-circle-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Revenue</h3>
                        <p>$<?php echo number_format($total_revenue, 2); ?></p>
                    </div>
                </div>
                <div class="overview__card">
                    <i class="ri-home-line card__icon"></i>
                    <div class="card__content">
                        <h3>Occupancy Rate</h3>
                        <p><?php echo number_format($occupancy_rate, 2); ?>%</p>
                    </div>
                </div>
            </div>

            <h2 class="section__subheader">Bookings by Branch</h2>
            <div class="table__container">
                <?php if (empty($bookings_by_branch)): ?>
                    <p class="text-muted text-center">No branch data available.</p>
                <?php else: ?>
                    <div class="table__wrapper">
                        <table class="data__table" id="branchTable">
                            <thead>
                                <tr>
                                    <th><i class="ri-building-line me-1"></i>Branch</th>
                                    <th><i class="ri-calendar-check-line me-1"></i>Booking Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings_by_branch as $branch): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($branch['name']); ?></strong></td>
                                        <td><span class="table__badge table__badge--info"><?php echo $branch['booking_count']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <h2 class="section__subheader">Monthly Revenue Summary</h2>
            <div class="table__container">
                <?php if (empty($monthly_data)): ?>
                    <p class="text-muted text-center">No monthly data available.</p>
                <?php else: ?>
                    <div class="table__wrapper">
                        <table class="data__table" id="monthlyTable">
                            <thead>
                                <tr>
                                    <th><i class="ri-calendar-line me-1"></i>Month</th>
                                    <th><i class="ri-calendar-check-line me-1"></i>Bookings</th>
                                    <th><i class="ri-money-dollar-circle-line me-1"></i>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($monthly_data as $month): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($month['month']); ?></td>
                                        <td><span class="table__badge table__badge--info"><?php echo $month['booking_count']; ?></span></td>
                                        <td>$<?php echo number_format($month['monthly_revenue'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <h2 class="section__subheader">Detailed Bookings Report</h2>
            <div class="table__container">
                <?php if (empty($detailed_bookings)): ?>
                    <p class="text-muted text-center">No bookings available.</p>
                <?php else: ?>
                    <div class="table__wrapper">
                        <table class="data__table" id="detailsTable">
                            <thead>
                                <tr>
                                    <th><i class="ri-hashtag me-1"></i>Booking ID</th>
                                    <th><i class="ri-user-line me-1"></i>Customer</th>
                                    <th><i class="ri-mail-line me-1"></i>Email</th>
                                    <th><i class="ri-building-line me-1"></i>Branch</th>
                                    <th><i class="ri-home-line me-1"></i>Room</th>
                                    <th><i class="ri-home-2-line me-1"></i>Room Type</th>
                                    <th><i class="ri-calendar-line me-1"></i>Check In</th>
                                    <th><i class="ri-calendar-line me-1"></i>Check Out</th>
                                    <th><i class="ri-checkbox-circle-line me-1"></i>Status</th>
                                    <th><i class="ri-money-dollar-circle-line me-1"></i>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detailed_bookings as $booking): ?>
                                    <tr>
                                        <td><span class="table__badge table__badge--light"><?php echo $booking['id']; ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($booking['email']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['branch_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['room_type']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_in']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_out']); ?></td>
                                        <td><span class="table__badge table__badge--info"><?php echo ucfirst($booking['status']); ?></span></td>
                                        <td>$<?php echo number_format($booking['total_amount'], 2); ?></td>
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

<!-- Hidden data for export -->
<script type="text/javascript">
const reportData = {
    totalRevenue: <?php echo json_encode($total_revenue); ?>,
    occupancyRate: <?php echo json_encode($occupancy_rate); ?>,
    branchData: <?php echo json_encode($bookings_by_branch); ?>,
    monthlyData: <?php echo json_encode($monthly_data); ?>,
    detailedBookings: <?php echo json_encode($detailed_bookings); ?>,
    generatedDate: new Date().toLocaleDateString('en-CA') // ISO format YYYY-MM-DD
};
</script>

<!-- External Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

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
    background: #333333;
}

.sidebar__link.active {
    background: #3b82f6;
    color: #ffffff;
}

.sidebar__link i {
    font-size: 1.25rem;
    margin-right: 0.75rem;
}

/* Main Content */
.dashboard__content {
    margin-left: 250px;
    padding: 1.5rem;
    flex-grow: 1;
    transition: margin-left 0.3s ease;
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

/* Export Buttons */
.export__buttons {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
    flex-wrap: wrap;
}

/* Button Styles */
.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn--primary {
    background: #3b82f6;
    color: white;
}

.btn--primary:hover:not(:disabled) {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn--secondary {
    background: #6b7280;
    color: white;
}

.btn--secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

/* Alert Styles */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.alert--error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

/* Overview Cards */
.overview__cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.overview__card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s ease;
}

.overview__card:hover {
    transform: translateY(-2px);
}

.card__icon {
    font-size: 2rem;
    color: #3b82f6;
    background: #eff6ff;
    padding: 0.75rem;
    border-radius: 50%;
}

.card__content h3 {
    font-size: 1rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.card__content p {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
}

/* Table Styles */
.table__container {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-top: 1.5rem;
}

.table__wrapper {
    overflow-x: auto;
}

.data__table {
    width: 100%;
    border-collapse: collapse;
}

.data__table th,
.data__table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.data__table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
    text-transform: uppercase;
}

.data__table td {
    color: #1f2937;
    vertical-align: middle;
}

.data__table tr:hover {
    background: #f3f4f6;
}

.table__badge {
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    background: #e5e7eb;
    color: #374151;
}

.table__badge--light {
    background: #f3f4f6;
}

.table__badge--info {
    background: #3b82f6;
    color: white;
}

/* Status Badges */
.status {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    display: inline-block;
}

.status--pending {
    background: #fef3c7;
    color: #d97706;
}

.status--confirmed {
    background: #d1fae5;
    color: #059669;
}

.status--cancelled {
    background: #fee2e2;
    color: #dc2626;
}

/* General Styles */
.section__subheader {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.text-muted {
    color: #6b7280;
}

.text-center {
    text-align: center;
}

.me-1 {
    margin-right: 0.25rem;
}

/* Mobile Responsive Styles */
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

    .dashboard__content {
        margin-left: 0;
    }

    .mobile-sidebar-toggle {
        display: block;
    }

    .sidebar__toggle {
        display: none;
    }

    .sidebar__logo, .sidebar__title {
        display: none;
    }

    .table__container {
        padding: 1rem;
    }

    .section__header {
        font-size: 1.5rem;
    }

    .section__subheader {
        font-size: 1.2rem;
    }

    .btn {
        padding: 0.65rem 1.25rem;
        font-size: 0.95rem;
    }

    .data__table th,
    .data__table td {
        padding: 0.5rem;
        font-size: 0.9rem;
    }

    .table__badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.4rem;
    }

    .overview__cards {
        grid-template-columns: 1fr;
    }

    .overview__card {
        padding: 1rem;
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

    .export__buttons {
        flex-direction: column;
        gap: 0.5rem;
    }

    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
        width: 100%;
        justify-content: center;
    }

    .data__table th,
    .data__table td {
        font-size: 0.85rem;
        padding: 0.4rem;
    }

    .card__icon {
        font-size: 1.5rem;
        padding: 0.5rem;
    }

    .card__content h3 {
        font-size: 0.9rem;
    }

    .card__content p {
        font-size: 1.2rem;
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
        if (sidebarToggle) {
            sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line', !isCollapsed);
            sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line', isCollapsed);
        }
        mobileSidebarToggle.querySelector('i').classList.toggle('ri-menu-line', isCollapsed);
        mobileSidebarToggle.querySelector('i').classList.toggle('ri-close-line', !isCollapsed);
        // Adjust content margin
        const dashboardContent = document.querySelector('.dashboard__content');
        dashboardContent.style.marginLeft = window.innerWidth <= 768 && !isCollapsed ? '250px' : '0';
    }

    // Event listeners for toggles
    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
    if (mobileSidebarToggle) mobileSidebarToggle.addEventListener('click', toggleSidebar);

    // Initialize sidebar state
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
        mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
        document.querySelector('.dashboard__content').style.marginLeft = '0';
    } else {
        document.querySelector('.dashboard__content').style.marginLeft = '250px';
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        const dashboardContent = document.querySelector('.dashboard__content');
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
            mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
            dashboardContent.style.marginLeft = '0';
        } else {
            sidebar.classList.remove('collapsed');
            dashboardContent.style.marginLeft = '250px';
        }
    });

    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

function exportToPDF() {
    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF({ orientation: 'landscape' });

        // Add title
        doc.setFontSize(20);
        doc.text('Hotel Chain Management Report', 20, 20);

        // Add generation date
        doc.setFontSize(10);
        doc.text(`Generated on: ${reportData.generatedDate}`, 20, 30);

        let yPosition = 50;

        // Key Metrics
        doc.setFontSize(16);
        doc.text('Key Metrics', 20, yPosition);
        yPosition += 10;

        doc.setFontSize(12);
        doc.text(`Total Revenue: $${Number(reportData.totalRevenue).toLocaleString()}`, 20, yPosition);
        yPosition += 8;
        doc.text(`Occupancy Rate: ${Number(reportData.occupancyRate).toFixed(2)}%`, 20, yPosition);
        yPosition += 20;

        // Bookings by Branch
        if (reportData.branchData.length > 0) {
            doc.setFontSize(16);
            doc.text('Bookings by Branch', 20, yPosition);
            yPosition += 10;

            const branchTableData = reportData.branchData.map(branch => [
                branch.name || 'N/A',
                branch.booking_count.toString()
            ]);

            doc.autoTable({
                head: [['Branch', 'Booking Count']],
                body: branchTableData,
                startY: yPosition,
                theme: 'striped',
                headStyles: { fillColor: [59, 130, 246] },
                styles: { fontSize: 10 }
            });

            yPosition = doc.lastAutoTable.finalY + 20;
        }

        // Monthly Revenue
        if (reportData.monthlyData.length > 0 && yPosition < 180) {
            doc.setFontSize(16);
            doc.text('Monthly Revenue Summary', 20, yPosition);
            yPosition += 10;

            const monthlyTableData = reportData.monthlyData.map(month => [
                month.month,
                month.booking_count.toString(),
                `$${Number(month.monthly_revenue).toLocaleString()}`
            ]);

            doc.autoTable({
                head: [['Month', 'Bookings', 'Revenue']],
                body: monthlyTableData,
                startY: yPosition,
                theme: 'striped',
                headStyles: { fillColor: [59, 130, 246] },
                styles: { fontSize: 10 }
            });

            yPosition = doc.lastAutoTable.finalY + 20;
        }

        // Detailed Bookings (new page if necessary)
        if (reportData.detailedBookings.length > 0) {
            if (yPosition > 150) {
                doc.addPage();
                yPosition = 20;
            }
            doc.setFontSize(16);
            doc.text('Detailed Bookings Report', 20, yPosition);
            yPosition += 10;

            const detailedTableData = reportData.detailedBookings.slice(0, 50).map(booking => [
                booking.id.toString(),
                booking.customer_name || 'N/A',
                booking.branch_name || 'N/A',
                booking.room_number || 'N/A',
                booking.check_in,
                booking.check_out,
                booking.status,
                `$${Number(booking.total_amount).toFixed(2)}`
            ]);

            doc.autoTable({
                head: [['ID', 'Customer', 'Branch', 'Room', 'Check In', 'Check Out', 'Status', 'Amount']],
                body: detailedTableData,
                startY: yPosition,
                theme: 'striped',
                headStyles: { fillColor: [59, 130, 246] },
                styles: { fontSize: 8 },
                columnStyles: {
                    0: { cellWidth: 15 },
                    1: { cellWidth: 30 },
                    2: { cellWidth: 30 },
                    3: { cellWidth: 20 },
                    4: { cellWidth: 25 },
                    5: { cellWidth: 25 },
                    6: { cellWidth: 20 },
                    7: { cellWidth: 25 }
                }
            });
        }

        doc.save(`hotel_reports_${reportData.generatedDate}.pdf`);
    } catch (e) {
        showAlert('Error generating PDF: ' + e.message);
    }
}

function exportToExcel() {
    try {
        const wb = XLSX.utils.book_new();

        // Summary Sheet
        const summaryData = [
            ['Hotel Chain Management Report'],
            ['Generated on:', reportData.generatedDate],
            [''],
            ['Key Metrics'],
            ['Total Revenue', `$${Number(reportData.totalRevenue).toLocaleString()}`],
            ['Occupancy Rate', `${Number(reportData.occupancyRate).toFixed(2)}%`],
            [''],
            ['Bookings by Branch'],
            ['Branch', 'Booking Count']
        ];

        reportData.branchData.forEach(branch => {
            summaryData.push([branch.name || 'N/A', branch.booking_count]);
        });

        const summarySheet = XLSX.utils.aoa_to_sheet(summaryData);
        XLSX.utils.book_append_sheet(wb, summarySheet, 'Summary');

        // Monthly Data Sheet
        const monthlyData = [
            ['Monthly Revenue Summary'],
            ['Month', 'Bookings', 'Revenue']
        ];

        reportData.monthlyData.forEach(month => {
            monthlyData.push([
                month.month,
                month.booking_count,
                Number(month.monthly_revenue)
            ]);
        });

        const monthlySheet = XLSX.utils.aoa_to_sheet(monthlyData);
        XLSX.utils.book_append_sheet(wb, monthlySheet, 'Monthly Revenue');

        // Detailed Bookings Sheet
        const detailedData = [
            ['Detailed Bookings Report'],
            ['Booking ID', 'Customer', 'Email', 'Branch', 'Room', 'Room Type', 'Check In', 'Check Out', 'Status', 'Amount']
        ];

        reportData.detailedBookings.forEach(booking => {
            detailedData.push([
                booking.id,
                booking.customer_name || 'N/A',
                booking.email || 'N/A',
                booking.branch_name || 'N/A',
                booking.room_number || 'N/A',
                booking.room_type || 'N/A',
                booking.check_in,
                booking.check_out,
                booking.status,
                Number(booking.total_amount)
            ]);
        });

        const detailedSheet = XLSX.utils.aoa_to_sheet(detailedData);
        XLSX.utils.book_append_sheet(wb, detailedSheet, 'Detailed Bookings');

        XLSX.writeFile(wb, `hotel_reports_${reportData.generatedDate}.xlsx`);
    } catch (e) {
        showAlert('Error generating Excel: ' + e.message);
    }
}

function showAlert(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert--error';
    alert.innerHTML = `<i class="ri-error-warning-line"></i><span>${message}</span>`;
    document.querySelector('.dashboard__content').prepend(alert);
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}
</script>

<?php include 'templates/footer.php'; ?>
</body>
</html>