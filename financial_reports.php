<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to managers only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// Include database connection and TCPDF
require_once 'db_connect.php';
require_once 'vendor/tcpdf/tcpdf.php';
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

// Initialize financial metrics
$total_revenue = 0;
$revenue_by_room_type = [];
$payment_status_summary = [];
$date_filter = isset($_GET['date_filter']) ? $_GET['date_filter'] : 'month';
$start_date = '';
$end_date = date('Y-m-d');

try {
    // Set date range based on filter
    switch ($date_filter) {
        case 'week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            break;
        case 'month':
            $start_date = date('Y-m-01');
            break;
        case 'year':
            $start_date = date('Y-01-01');
            break;
        default:
            $start_date = date('Y-m-01');
    }

    // Total revenue from bookings
    $stmt = $pdo->prepare("
        SELECT SUM(rt.base_price) as revenue
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.branch_id = ? AND b.check_in BETWEEN ? AND ? AND b.status = 'confirmed'
    ");
    $stmt->execute([$branch_id, $start_date, $end_date]);
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['revenue'] ?? 0;

    // Revenue by room type
    $stmt = $pdo->prepare("
        SELECT rt.name, SUM(rt.base_price) as revenue
        FROM bookings b
        JOIN rooms r ON b.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.branch_id = ? AND b.check_in BETWEEN ? AND ? AND b.status = 'confirmed'
        GROUP BY rt.id, rt.name
    ");
    $stmt->execute([$branch_id, $start_date, $end_date]);
    $revenue_by_room_type = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Payment status summary
    $stmt = $pdo->prepare("
        SELECT p.status, COUNT(*) as count, SUM(p.amount) as total_amount
        FROM payments p
        JOIN bookings b ON p.reservation_id = b.id
        WHERE b.branch_id = ? AND p.created_at BETWEEN ? AND ?
        GROUP BY p.status
    ");
    $stmt->execute([$branch_id, $start_date, $end_date]);
    $payment_status_summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $db_error = "Database connection error: " . $e->getMessage();
    $total_revenue = 0;
    $revenue_by_room_type = [];
    $payment_status_summary = [];
}

// Handle download requests
if (isset($_GET['download']) && in_array($_GET['download'], ['pdf', 'excel'])) {
    $format = $_GET['download'];
    
    if ($format === 'pdf') {
        generatePDFReport($branch_name, $total_revenue, $revenue_by_room_type, $payment_status_summary, $date_filter, $start_date, $end_date);
    } elseif ($format === 'excel') {
        generateExcelReport($branch_name, $total_revenue, $revenue_by_room_type, $payment_status_summary, $date_filter, $start_date, $end_date);
    }
    exit();
}

// Function to generate PDF report using TCPDF
function generatePDFReport($branch_name, $total_revenue, $revenue_by_room_type, $payment_status_summary, $date_filter, $start_date, $end_date) {
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Hotel Management System');
    $pdf->SetAuthor('Hotel Management System');
    $pdf->SetTitle('Financial Report - ' . $branch_name);
    $pdf->SetSubject('Financial Report');
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 12);
    
    // Build HTML content for PDF
    $html = '<h1 style="text-align: center;">' . htmlspecialchars($branch_name) . ' - Financial Report</h1>';
    $html .= '<p style="text-align: center;">Period: ' . ucfirst($date_filter) . ' (' . $start_date . ' to ' . $end_date . ')</p>';
    $html .= '<h2>Total Revenue: $' . number_format($total_revenue, 2) . '</h2>';
    
    $html .= '<h2>Revenue by Room Type</h2>';
    $html .= '<table border="1" cellpadding="4"><tr><th style="background-color: #f2f2f2;">Room Type</th><th style="background-color: #f2f2f2;">Revenue</th></tr>';
    if (!empty($revenue_by_room_type)) {
        foreach ($revenue_by_room_type as $row) {
            $html .= '<tr><td>' . htmlspecialchars($row['name']) . '</td><td>$' . number_format($row['revenue'], 2) . '</td></tr>';
        }
    } else {
        $html .= '<tr><td colspan="2">No data available</td></tr>';
    }
    $html .= '</table>';
    
    $html .= '<h2>Payment Status Summary</h2>';
    $html .= '<table border="1" cellpadding="4"><tr><th style="background-color: #f2f2f2;">Status</th><th style="background-color: #f2f2f2;">Count</th><th style="background-color: #f2f2f2;">Total Amount</th></tr>';
    if (!empty($payment_status_summary)) {
        foreach ($payment_status_summary as $row) {
            $html .= '<tr><td>' . htmlspecialchars(ucfirst($row['status'])) . '</td><td>' . $row['count'] . '</td><td>$' . number_format($row['total_amount'], 2) . '</td></tr>';
        }
    } else {
        $html .= '<tr><td colspan="3">No data available</td></tr>';
    }
    $html .= '</table>';
    
    // Output the HTML content to PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF
    $pdf->Output('financial_report_' . $date_filter . '_' . date('Ymd') . '.pdf', 'D');
}

// Function to generate Excel report (CSV)
function generateExcelReport($branch_name, $total_revenue, $revenue_by_room_type, $payment_status_summary, $date_filter, $start_date, $end_date) {
    // Set headers for Excel download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="financial_report_' . $date_filter . '_' . date('Ymd') . '.csv"');
    
    // Create CSV output
    $output = fopen('php://output', 'w');
    
    // Write header
    fputcsv($output, [$branch_name . ' - Financial Report']);
    fputcsv($output, ['Period', ucfirst($date_filter) . ' (' . $start_date . ' to ' . $end_date . ')']);
    fputcsv($output, ['Total Revenue', '$' . number_format($total_revenue, 2)]);
    fputcsv($output, []);
    
    // Revenue by Room Type
    fputcsv($output, ['Revenue by Room Type']);
    fputcsv($output, ['Room Type', 'Revenue']);
    if (!empty($revenue_by_room_type)) {
        foreach ($revenue_by_room_type as $row) {
            fputcsv($output, [$row['name'], '$' . number_format($row['revenue'], 2)]);
        }
    } else {
        fputcsv($output, ['No data available']);
    }
    
    fputcsv($output, []);
    
    // Payment Status Summary
    fputcsv($output, ['Payment Status Summary']);
    fputcsv($output, ['Status', 'Count', 'Total Amount']);
    if (!empty($payment_status_summary)) {
        foreach ($payment_status_summary as $row) {
            fputcsv($output, [ucfirst($row['status']), $row['count'], '$' . number_format($row['total_amount'], 2)]);
        }
    } else {
        fputcsv($output, ['No data available']);
    }
    
    fclose($output);
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
                    <a href="financial_reports.php" class="sidebar__link active">
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
            <h1 class="section__header"><?php echo htmlspecialchars($branch_name); ?> - Financial Reports</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Manager'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <!-- Financial Reports Section -->
        <section id="financial-reports" class="dashboard__section active">
            <h2 class="section__subheader">Financial Overview</h2>
            <?php if (isset($db_error)): ?>
                <p class="error"><?php echo htmlspecialchars($db_error); ?></p>
            <?php endif; ?>

            <!-- Download Buttons -->
            <div class="download__container">
                <a href="financial_reports.php?download=pdf&date_filter=<?php echo $date_filter; ?>" class="download__button">
                    <i class="ri-file-pdf-line"></i> Download PDF
                </a>
                <a href="financial_reports.php?download=excel&date_filter=<?php echo $date_filter; ?>" class="download__button">
                    <i class="ri-file-excel-line"></i> Download Excel
                </a>
            </div>

            <!-- Date Filter -->
            <div class="filter__container">
                <label for="date_filter">Select Time Period:</label>
                <select id="date_filter" name="date_filter" onchange="window.location.href='financial_reports.php?date_filter='+this.value">
                    <option value="week" <?php echo $date_filter == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="month" <?php echo $date_filter == 'month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="year" <?php echo $date_filter == 'year' ? 'selected' : ''; ?>>This Year</option>
                </select>
            </div>

            <!-- Total Revenue -->
            <div class="overview__cards">
                <div class="overview__card">
                    <i class="ri-money-dollar-circle-line card__icon"></i>
                    <div class="card__content">
                        <h3>Total Revenue</h3>
                        <p>$<?php echo number_format($total_revenue, 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Revenue by Room Type -->
            <h3 class="section__subheader">Revenue by Room Type</h3>
            <table class="report__table">
                <thead>
                    <tr>
                        <th>Room Type</th>
                        <th>Revenue</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($revenue_by_room_type as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td>$<?php echo number_format($row['revenue'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($revenue_by_room_type)): ?>
                        <tr>
                            <td colspan="2">No data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Payment Status Summary -->
            <h3 class="section__subheader">Payment Status Summary</h3>
            <table class="report__table">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payment_status_summary as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(ucfirst($row['status'])); ?></td>
                            <td><?php echo $row['count']; ?></td>
                            <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($payment_status_summary)): ?>
                        <tr>
                            <td colspan="3">No data available</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </main>
</div>

<style>
/* Styles for the financial reports */
.filter__container {
    margin-bottom: 1.5rem;
}

.filter__container label {
    margin-right: 1rem;
    font-weight: 600;
}

.filter__container select {
    padding: 0.5rem;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    background: white;
}

.report__table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.report__table th,
.report__table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.report__table th {
    background: #f9fafb;
    font-weight: 600;
    color: #1f2937;
}

.report__table td {
    color: #374151;
}

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

.sidebar__link.active {
    background: #3b82f6;
    color: white;
}

.download__container {
    margin-bottom: 1.5rem;
    display: flex;
    gap: 1rem;
}

.download__button {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: #3b82f6;
    color: white;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: background 0.2s ease;
}

.download__button:hover {
    background: #2563eb;
}

.download__button i {
    font-size: 1.2rem;
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