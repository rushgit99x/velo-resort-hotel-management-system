<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!file_exists('includes/config.php')) {
    die("Error: includes/config.php not found.");
}
include_once 'includes/config.php';

// Debug: Confirm file is loaded
error_log("functions.php loaded");

// Prevent function redefinition
if (!function_exists('checkAuth')) {
    function checkAuth($role = null) {
        if (!isset($_SESSION['user_id'])) {
            header("Location: login.php");
            exit;
        }
        if ($role && $_SESSION['role'] !== $role) {
            header("Location: index.php");
            exit;
        }
    }
}

if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)));
    }
}

if (!function_exists('validateEmail')) {
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('isRoomAvailable')) {
    function isRoomAvailable($pdo, $room_id, $check_in, $check_out) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings 
                               WHERE room_id = ? 
                               AND status = 'confirmed' 
                               AND (check_in <= ? AND check_out >= ?)");
        $stmt->execute([$room_id, $check_out, $check_in]);
        return $stmt->fetchColumn() == 0;
    }
}

if (!function_exists('getUserBranch')) {
    function getUserBranch($pdo, $user_id) {
        $stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    }
}
function get_manager_branch_id($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ? AND role = 'manager'");
        $stmt->execute([$user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['branch_id'] ?? 0;
    } catch (PDOException $e) {
        return 0;
    }
}

function get_occupancy_rate($pdo, $branch_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rooms WHERE branch_id = ?");
        $stmt->execute([$branch_id]);
        $total_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM rooms WHERE branch_id = ? AND status = 'occupied'");
        $stmt->execute([$branch_id]);
        $occupied_rooms = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

        return $total_rooms > 0 ? round(($occupied_rooms / $total_rooms) * 100, 2) : 0;
    } catch (PDOException $e) {
        return 0;
    }
}
?>