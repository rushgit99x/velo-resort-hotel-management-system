<?php
require_once 'db_connect.php';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        UPDATE pending_payments pp
        JOIN reservations r ON r.id = pp.reservation_id
        JOIN bookings b ON b.user_id = r.user_id
        SET pp.status = 'cancelled',
            r.status = 'cancelled',
            b.status = 'cancelled'
        WHERE pp.status = 'pending'
        AND pp.created_at < DATE_SUB(CURDATE(), INTERVAL 7 HOUR)
        AND TIME(NOW()) >= '19:00:00'
    );
    $stmt->execute();

    $pdo->commit();
    echo "Pending payments and associated reservations cancelled successfully.";
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Error: " . $e->getMessage();
}