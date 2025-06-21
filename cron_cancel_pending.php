<?php
require_once 'db_connect.php';

try {
    $pdo->beginTransaction();

    // First, get the records that will be affected for logging
    $selectStmt = $pdo->prepare("
        SELECT pp.id as payment_id, pp.reservation_id, r.user_id
        FROM pending_payments pp
        JOIN reservations r ON r.id = pp.reservation_id
        WHERE pp.status = 'pending'
        AND pp.created_at < DATE_SUB(NOW(), INTERVAL 7 HOUR)
    ");
    $selectStmt->execute();
    $affectedRecords = $selectStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($affectedRecords) > 0) {
        // Update pending payments
        $stmt1 = $pdo->prepare("
            UPDATE pending_payments 
            SET status = 'cancelled', updated_at = NOW()
            WHERE status = 'pending'
            AND created_at < DATE_SUB(NOW(), INTERVAL 7 HOUR)
        ");
        $stmt1->execute();
        $paymentsUpdated = $stmt1->rowCount();

        // Update reservations
        $stmt2 = $pdo->prepare("
            UPDATE reservations r
            JOIN pending_payments pp ON r.id = pp.reservation_id
            SET r.status = 'cancelled', r.updated_at = NOW()
            WHERE pp.status = 'cancelled'
            AND pp.created_at < DATE_SUB(NOW(), INTERVAL 7 HOUR)
        ");
        $stmt2->execute();
        $reservationsUpdated = $stmt2->rowCount();

        // Update bookings (if needed - this depends on your business logic)
        $stmt3 = $pdo->prepare("
            UPDATE bookings b
            JOIN reservations r ON b.user_id = r.user_id
            JOIN pending_payments pp ON r.id = pp.reservation_id
            SET b.status = 'cancelled', b.updated_at = NOW()
            WHERE pp.status = 'cancelled'
            AND pp.created_at < DATE_SUB(NOW(), INTERVAL 7 HOUR)
            AND b.status != 'cancelled'
        ");
        $stmt3->execute();
        $bookingsUpdated = $stmt3->rowCount();

        $pdo->commit();
        
        echo "Cancellation completed successfully:\n";
        echo "- Pending payments cancelled: $paymentsUpdated\n";
        echo "- Reservations cancelled: $reservationsUpdated\n";
        echo "- Bookings cancelled: $bookingsUpdated\n";
        
    } else {
        $pdo->rollBack();
        echo "No pending payments found that meet the criteria.";
    }

} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
    // Log the error for debugging
    error_log("Payment cancellation error: " . $e->getMessage());
}