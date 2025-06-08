<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
checkAuth('super_admin');

require_once 'db_connect.php';

try {
    // Total revenue from confirmed bookings (using base_price from room_types)
    $stmt = $pdo->query("
        SELECT SUM(rt.base_price * DATEDIFF(b.check_out, b.check_in)) as total_revenue 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        JOIN room_types rt ON r.room_type_id = rt.id 
        WHERE b.status = 'confirmed'
    ");
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

    // Occupancy rate (confirmed bookings vs total available rooms)
    $stmt = $pdo->query("
        SELECT COUNT(*) as confirmed_bookings 
        FROM bookings 
        WHERE status = 'confirmed' 
        AND check_in <= CURDATE() 
        AND check_out >= CURDATE()
    ");
    $confirmed_bookings = $stmt->fetch(PDO::FETCH_ASSOC)['confirmed_bookings'] ?? 0;

    $stmt = $pdo->query("
        SELECT COUNT(*) as total_rooms 
        FROM rooms 
        WHERE status = 'available'
    ");
    $total_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['total_rooms'] ?? 1;
    $occupancy_rate = ($total_rooms > 0) ? ($confirmed_bookings / $total_rooms) * 100 : 0;

    // Bookings by branch
    $stmt = $pdo->query("
        SELECT b.name, COUNT(bo.id) as booking_count 
        FROM branches b 
        LEFT JOIN bookings bo ON b.id = bo.branch_id 
        GROUP BY b.id, b.name
    ");
    $bookings_by_branch = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Additional detailed reports data
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
            rt.base_price * DATEDIFF(b.check_out, b.check_in) as total_amount,
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
            SUM(rt.base_price * DATEDIFF(b.check_out, b.check_in)) as monthly_revenue
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
            <h1 class="section__header">Reports</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <?php if (isset($error)): ?>
            <div class="alert alert--error">
                <i class="ri-error-warning-line"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <section id="reports" class="dashboard__section active">
            <!-- Export Buttons -->
            <div class="export__buttons">
                <button onclick="exportToPDF()" class="btn btn--primary">
                    <i class="ri-file-pdf-line"></i>
                    Download PDF Report
                </button>
                <button onclick="exportToExcel()" class="btn btn--secondary">
                    <i class="ri-file-excel-line"></i>
                    Download Excel Report
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
                <table class="data__table" id="branchTable">
                    <thead>
                        <tr>
                            <th>Branch</th>
                            <th>Booking Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings_by_branch as $branch): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($branch['name']); ?></td>
                                <td><?php echo $branch['booking_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h2 class="section__subheader">Monthly Revenue Summary</h2>
            <div class="table__container">
                <table class="data__table" id="monthlyTable">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Bookings</th>
                            <th>Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($monthly_data as $month): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($month['month']); ?></td>
                                <td><?php echo $month['booking_count']; ?></td>
                                <td>$<?php echo number_format($month['monthly_revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <h2 class="section__subheader">Detailed Bookings Report</h2>
            <div class="table__container">
                <table class="data__table" id="detailsTable">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Branch</th>
                            <th>Room</th>
                            <th>Room Type</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Status</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detailed_bookings as $booking): ?>
                            <tr>
                                <td><?php echo $booking['id']; ?></td>
                                <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['email']); ?></td>
                                <td><?php echo htmlspecialchars($booking['branch_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['room_number']); ?></td>
                                <td><?php echo htmlspecialchars($booking['room_type']); ?></td>
                                <td><?php echo $booking['check_in']; ?></td>
                                <td><?php echo $booking['check_out']; ?></td>
                                <td><span class="status status--<?php echo $booking['status']; ?>"><?php echo ucfirst($booking['status']); ?></span></td>
                                <td>$<?php echo number_format($booking['total_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
    generatedDate: new Date().toISOString().split('T')[0]
};
</script>

<style>
.export__buttons {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn--primary {
    background: #dc2626;
    color: white;
}

.btn--primary:hover {
    background: #b91c1c;
}

.btn--secondary {
    background: #059669;
    color: white;
}

.btn--secondary:hover {
    background: #047857;
}

.table__container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-top: 1.5rem;
    overflow-x: auto;
}

.data__table {
    width: 100%;
    border-collapse: collapse;
    min-width: 600px;
}

.data__table th, .data__table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.data__table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
    position: sticky;
    top: 0;
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
}

.card__icon {
    font-size: 2.5rem;
    color: #3b82f6;
    background: #eff6ff;
    padding: 1rem;
    border-radius: 50%;
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

.status {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
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

.alert {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.alert--error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
</style>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<script>
document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});

function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
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
    doc.text(`Total Revenue: $${parseFloat(reportData.totalRevenue).toLocaleString()}`, 20, yPosition);
    yPosition += 8;
    doc.text(`Occupancy Rate: ${parseFloat(reportData.occupancyRate).toFixed(2)}%`, 20, yPosition);
    yPosition += 20;
    
    // Bookings by Branch
    doc.setFontSize(16);
    doc.text('Bookings by Branch', 20, yPosition);
    yPosition += 10;
    
    const branchTableData = reportData.branchData.map(branch => [
        branch.name,
        branch.booking_count.toString()
    ]);
    
    doc.autoTable({
        head: [['Branch', 'Booking Count']],
        body: branchTableData,
        startY: yPosition,
        theme: 'striped',
        headStyles: { fillColor: [59, 130, 246] }
    });
    
    yPosition = doc.lastAutoTable.finalY + 20;
    
    // Monthly Revenue (if fits on page)
    if (yPosition < 250) {
        doc.setFontSize(16);
        doc.text('Monthly Revenue Summary', 20, yPosition);
        yPosition += 10;
        
        const monthlyTableData = reportData.monthlyData.map(month => [
            month.month,
            month.booking_count.toString(),
            `$${parseFloat(month.monthly_revenue).toLocaleString()}`
        ]);
        
        doc.autoTable({
            head: [['Month', 'Bookings', 'Revenue']],
            body: monthlyTableData,
            startY: yPosition,
            theme: 'striped',
            headStyles: { fillColor: [59, 130, 246] }
        });
    }
    
    // Add new page for detailed bookings
    doc.addPage();
    doc.setFontSize(16);
    doc.text('Detailed Bookings Report', 20, 20);
    
    const detailedTableData = reportData.detailedBookings.slice(0, 50).map(booking => [
        booking.id.toString(),
        booking.customer_name,
        booking.branch_name,
        booking.room_number,
        booking.check_in,
        booking.check_out,
        booking.status,
        `$${parseFloat(booking.total_amount).toFixed(2)}`
    ]);
    
    doc.autoTable({
        head: [['ID', 'Customer', 'Branch', 'Room', 'Check In', 'Check Out', 'Status', 'Amount']],
        body: detailedTableData,
        startY: 30,
        theme: 'striped',
        headStyles: { fillColor: [59, 130, 246] },
        styles: { fontSize: 8 },
        columnStyles: {
            0: { cellWidth: 15 },
            1: { cellWidth: 25 },
            2: { cellWidth: 25 },
            3: { cellWidth: 15 },
            4: { cellWidth: 20 },
            5: { cellWidth: 20 },
            6: { cellWidth: 15 },
            7: { cellWidth: 20 }
        }
    });
    
    doc.save('hotel_reports_' + reportData.generatedDate + '.pdf');
}

function exportToExcel() {
    const wb = XLSX.utils.book_new();
    
    // Summary Sheet
    const summaryData = [
        ['Hotel Chain Management Report'],
        ['Generated on:', reportData.generatedDate],
        [''],
        ['Key Metrics'],
        ['Total Revenue', `$${parseFloat(reportData.totalRevenue).toLocaleString()}`],
        ['Occupancy Rate', `${parseFloat(reportData.occupancyRate).toFixed(2)}%`],
        [''],
        ['Bookings by Branch'],
        ['Branch', 'Booking Count']
    ];
    
    reportData.branchData.forEach(branch => {
        summaryData.push([branch.name, branch.booking_count]);
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
            parseFloat(month.monthly_revenue)
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
            booking.customer_name,
            booking.email,
            booking.branch_name,
            booking.room_number,
            booking.room_type,
            booking.check_in,
            booking.check_out,
            booking.status,
            parseFloat(booking.total_amount)
        ]);
    });
    
    const detailedSheet = XLSX.utils.aoa_to_sheet(detailedData);
    XLSX.utils.book_append_sheet(wb, detailedSheet, 'Detailed Bookings');
    
    // Save the file
    XLSX.writeFile(wb, 'hotel_reports_' + reportData.generatedDate + '.xlsx');
}
</script>

<?php include 'templates/footer.php'; ?>