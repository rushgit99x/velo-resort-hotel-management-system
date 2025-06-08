<?php
// Debug: Confirm file is loaded
error_log("auth.php loaded");

// Include dependencies
if (!file_exists('includes/config.php')) {
    die("Error: includes/config.php not found.");
}
if (!file_exists('includes/functions.php')) {
    die("Error: includes/functions.php not found.");
}
include_once 'includes/config.php';
include_once 'includes/functions.php';

function processLogin($pdo) {
    error_log("processLogin called");
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
        $email = sanitize($_POST['email']);
        $password = $_POST['password'];

        if (!validateEmail($email)) {
            return "Invalid email format.";
        }

        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                error_log("Login successful for user: " . $email . ", role: " . $user['role']);
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'super_admin':
                        header("Location: admin_dashboard.php");
                        break;
                    case 'manager':
                        header("Location: manager_dashboard.php");
                        break;
                    case 'customer':
                        header("Location: customer_dashboard.php");
                        break;
                    case 'travel_company':
                        header("Location: travel_company_dashboard.php");
                        break;
                    case 'clerk':
                        header("Location: clerk_dashboard.php");
                        break;
                    default:
                        error_log("Unknown role: " . $user['role']);
                        return "Invalid user role.";
                }
                exit;
            } else {
                error_log("Login failed for user: " . $email);
                return "Invalid email or password.";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return "Database error: Unable to process login.";
        }
    }
    return null;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>