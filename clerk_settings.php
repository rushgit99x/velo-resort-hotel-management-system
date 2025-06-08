<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to clerks only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clerk') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Get clerk's branch_id, branch name, and current profile details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.branch_id, u.name, u.email, b.name AS branch_name 
                      FROM users u 
                      LEFT JOIN branches b ON u.branch_id = b.id 
                      WHERE u.id = ? AND u.role = 'clerk'");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_id = $result['branch_id'] ?? 0;
$branch_name = $result['branch_name'] ?? 'Unknown Branch';
$current_name = $result['name'] ?? '';
$current_email = $result['email'] ?? '';

if (!$branch_id) {
    $db_error = "No branch assigned to this clerk.";
}

// Initialize variables
$errors = [];
$success = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (empty($name) || empty($email)) {
        $errors[] = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        try {
            // Check if email is already in use by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = "Email is already in use by another user.";
            } else {
                // Update profile
                $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'clerk'");
                $stmt->execute([$name, $email, $user_id]);
                $success = "Profile updated successfully.";
                // Update session username
                $_SESSION['username'] = $name;
                $current_name = $name;
                $current_email = $email;
            }
        } catch (PDOException $e) {
            $errors[] = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $errors[] = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $errors[] = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
        $errors[] = "New password must be at least 8 characters long.";
    } else {
        try {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ? AND role = 'clerk'");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($current_password, $user['password'])) {
                $errors[] = "Current password is incorrect.";
            } else {
                // Update password
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                $success = "Password updated successfully.";
            }
        } catch (PDOException $e) {
            $errors[] = "Error updating password: " . $e->getMessage();
        }
    }
}

include 'templates/header.php';
?>

<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Clerk Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li>
                    <a href="clerk_dashboard.php" class="sidebar__link">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="manage_reservations.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Manage Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="manage_check_in_out.php" class="sidebar__link">
                        <i class="ri-logout-box-line"></i>
                        <span>Check-In/Out Customers</span>
                    </a>
                </li>
                <li>
                    <a href="room_availability.php" class="sidebar__link">
                        <i class="ri-home-line"></i>
                        <span>Room Availability</span>
                    </a>
                </li>
                <li>
                    <a href="create_customer.php" class="sidebar__link">
                        <i class="ri-user-add-line"></i>
                        <span>Create Customer</span>
                    </a>
                </li>
                <li>
                    <a href="billing_statements.php" class="sidebar__link">
                        <i class="ri-wallet-line"></i>
                        <span>Billing Statements</span>
                    </a>
                </li>
                <li>
                    <a href="clerk_settings.php" class="sidebar__link active">
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
            <h1 class="section__header"><?php echo htmlspecialchars($branch_name); ?> - Profile Settings</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Clerk'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section id="clerk-settings" class="dashboard__section active">
            <h2 class="section__subheader">Profile Settings</h2>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="success">
                    <p><?php echo htmlspecialchars($success); ?></p>
                </div>
            <?php endif; ?>

            <!-- Update Profile -->
            <div class="form__container">
                <h3>Update Profile</h3>
                <form method="POST" action="clerk_settings.php">
                    <div class="form__group">
                        <label for="name">Name:</label>
                        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($current_name); ?>" required>
                    </div>
                    <div class="form__group">
                        <label for="email">Email:</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($current_email); ?>" required>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>

            <!-- Update Password -->
            <div class="form__container">
                <h3>Update Password</h3>
                <form method="POST" action="clerk_settings.php">
                    <div class="form__group">
                        <label for="current_password">Current Password:</label>
                        <input type="password" name="current_password" id="current_password" required>
                    </div>
                    <div class="form__group">
                        <label for="new_password">New Password:</label>
                        <input type="password" name="new_password" id="new_password" required>
                    </div>
                    <div class="form__group">
                        <label for="confirm_password">Confirm New Password:</label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                    </div>
                    <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </section>
    </main>
</div>

<style>
/* Styles for the clerk settings page */
.form__container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
    max-width: 500px;
}

.form__group {
    margin-bottom: 1rem;
}

.form__group label {
    display: block;
    font-size: 1rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.form__group input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-size: 1rem;
    cursor: pointer;
}

.btn-primary {
    background: #3b82f6;
    color: white;
    border: none;
}

.btn-primary:hover {
    background: #2563eb;
}

.error, .success {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.error {
    background: #fee2e2;
    color: #dc2626;
}

.success {
    background: #dcfce7;
    color: #15803d;
}

.section__subheader {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

h3 {
    font-size: 1.2rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 1rem;
}

.dashboard__section.active {
    display: block;
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