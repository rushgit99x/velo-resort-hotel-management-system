<?php
require_once 'db_connect.php';
include_once 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['room_number']) && isset($_POST['branch_id'])) {
    $room_number = sanitize($_POST['room_number']);
    $branch_id = $_POST['branch_id'];
    $room_id = isset($_POST['room_id']) ? $_POST['room_id'] : null;

    try {
        $query = "SELECT COUNT(*) FROM rooms WHERE room_number = ? AND branch_id = ?";
        $params = [$room_number, $branch_id];
        if ($room_id) {
            $query .= " AND id != ?";
            $params[] = $room_id;
        }
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $count = $stmt->fetchColumn();

        echo json_encode(['exists' => $count > 0]);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error']);
    }
} else {
    echo json_encode(['error' => 'Invalid request']);
}
?>