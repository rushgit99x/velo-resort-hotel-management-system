<?php
// download_report.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
checkAuth('super_admin');
require_once 'db_connect.php';

// Check if format is specified
$format = $_GET['format'] ?? 'excel';

if (!in_array($format, ['excel', 'csv'])) {
    die('Invalid format specified');
}

try {
    // Get all the report data
    $stmt = $pdo->query("
        SELECT SUM(rt.base_price * DATEDIFF(b.check_out, b.check_in)) as total_revenue 
        FROM bookings b 
        JOIN rooms r ON b.room_id = r.id 
        JOIN room_types rt ON r.room_type_id = rt.id 
        WHERE b.status = 'confirmed'
    ");
    $total_revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total_revenue'] ?? 0;

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

    $stmt = $pdo->query("
        SELECT b.name, COUNT(bo.id) as booking_count 
        FROM branches b 
        LEFT JOIN bookings bo ON b.id = bo.branch_id 
        GROUP BY b.id, b.name
    ");
    $bookings_by_branch = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("
        SELECT 
            bo.id,
            bo.check_in,
            bo.check_out,
            bo.status,
            bo.created_at,
            b.name as branch_name,
            r.room_number,
            rt.name as room_type,
            rt.base_price,
            (rt.base_price * DATEDIFF(bo.check_out, bo.check_in)) as total_amount,
            CONCAT(u.first_name, ' ', u.last_name) as guest_name,
            u.email as guest_email,
            u.phone as guest_phone
        FROM bookings bo
        JOIN branches b ON bo.branch_id = b.id
        JOIN rooms r ON bo.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        LEFT JOIN users u ON bo.user_id = u.id
        ORDER BY bo.created_at DESC
    ");
    $detailed_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}

if ($format === 'csv') {
    // Generate CSV
    $filename = 'Hotel_Report_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Write report header
    fputcsv($output, ['Hotel Chain Management Report']);
    fputcsv($output, ['Generated on', date('Y-m-d H:i:s')]);
    fputcsv($output, []);
    
    // Write key metrics
    fputcsv($output, ['Key Metrics']);
    fputcsv($output, ['Total Revenue', '$' . number_format($total_revenue, 2)]);
    fputcsv($output, ['Occupancy Rate', number_format($occupancy_rate, 2) . '%']);
    fputcsv($output, ['Confirmed Bookings', $confirmed_bookings]);
    fputcsv($output, ['Total Available Rooms', $total_rooms]);
    fputcsv($output, []);
    
    // Write bookings by branch
    fputcsv($output, ['Bookings by Branch']);
    fputcsv($output, ['Branch Name', 'Booking Count']);
    foreach ($bookings_by_branch as $branch) {
        fputcsv($output, [$branch['name'], $branch['booking_count']]);
    }
    fputcsv($output, []);
    
    // Write detailed bookings
    fputcsv($output, ['Detailed Bookings Report']);
    fputcsv($output, [
        'Booking ID', 'Guest Name', 'Guest Email', 'Guest Phone', 'Branch', 
        'Room Number', 'Room Type', 'Check-in', 'Check-out', 'Status', 
        'Base Price', 'Total Amount', 'Booking Date'
    ]);
    
    foreach ($detailed_bookings as $booking) {
        fputcsv($output, [
            $booking['id'],
            $booking['guest_name'] ?: 'N/A',
            $booking['guest_email'] ?: 'N/A',
            $booking['guest_phone'] ?: 'N/A',
            $booking['branch_name'],
            $booking['room_number'],
            $booking['room_type'],
            $booking['check_in'],
            $booking['check_out'],
            ucfirst($booking['status']),
            '$' . number_format($booking['base_price'], 2),
            '$' . number_format($booking['total_amount'], 2),
            date('Y-m-d', strtotime($booking['created_at']))
        ]);
    }
    
    fclose($output);
    exit;
}

// If we reach here, something went wrong
die('Invalid request');
?>