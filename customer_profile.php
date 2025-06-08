<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to customers only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Get customer details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'customer'");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$customer_name = $user['name'] ?? 'Customer';
$customer_email = $user['email'] ?? 'Unknown';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($name)) {
        $errors[] = "Name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email is required.";
    }
    if (!empty($new_password) && $new_password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    }
    if (!empty($new_password) && strlen($new_password) < 6) {
        $errors[] = "New password must be at least 6 characters.";
    }

    // Verify current password if updating password
    if (!empty($current_password) && !empty($new_password)) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $stored_password = $stmt->fetch(PDO::FETCH_ASSOC)['password'];
        if (!password_verify($current_password, $stored_password)) {
            $errors[] = "Current password is incorrect.";
        }
    }

    if (empty($errors)) {
        try {
            // Check if email is already in use by another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = "This email is already in use by another account.";
            } else {
                // Update user details
                $update_query = "UPDATE users SET name = ?, email = ?";
                $params = [$name, $email];
                if (!empty($new_password)) {
                    $update_query .= ", password = ?";
                    $params[] = password_hash($new_password, PASSWORD_DEFAULT);
                }
                $update_query .= " WHERE id = ?";
                $params[] = $user_id;

                $stmt = $pdo->prepare($update_query);
                $stmt->execute($params);

                // Update session variables
                $_SESSION['name'] = $name;
                $success = "Profile updated successfully.";
                $customer_name = $name;
                $customer_email = $email;
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}

include 'templates/header.php';
?>

<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Customer Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li>
                    <a href="customer_dashboard.php" class="sidebar__link">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="make_reservation.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Make Reservation</span>
                    </a>
                </li>
                <li>
                    <a href="customer_manage_reservations.php" class="sidebar__link">
                        <i class="ri-calendar-line"></i>
                        <span>Manage Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="customer_additional_services.php" class="sidebar__link">
                        <i class="ri-service-line"></i>
                        <span>Additional Services</span>
                    </a>
                </li>
                <li>
                    <a href="billing_payments.php" class="sidebar__link">
                        <i class="ri-wallet-line"></i>
                        <span>Billing & Payments</span>
                    </a>
                </li>
                <li>
                    <a href="customer_profile.php" class="sidebar__link active">
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
            <h1 class="section__header">Your Profile</h1>
            <div class="user__info">
                <span><?php echo htmlspecialchars($customer_email); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section id="profile" class="dashboard__section active">
            <h2 class="section__subheader">Manage Your Profile</h2>
            <?php if (!empty($errors)): ?>
                <div class="error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo htmlspecialchars($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <p class="success"><?php echo htmlspecialchars($success); ?></p>
            <?php endif; ?>
            <form method="POST" class="profile__form">
                <div class="form__group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($customer_name); ?>" required>
                </div>
                <div class="form__group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer_email); ?>" required>
                </div>
                <div class="form__group">
                    <label for="current_password">Current Password</label>
                    <input type="password" id="current_password" name="current_password" placeholder="Enter current password">
                </div>
                <div class="form__group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password">
                </div>
                <div class="form__group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password">
                </div>
                <button type="submit" class="action__button">Update Profile</button>
            </form>
        </section>
    </main>
</div>

<style>
/* General Dashboard Styles */
.dashboard__container {
    display: flex;
    min-height: 100vh;
    background: #f3f4f6;
}

.dashboard__content {
    flex: 1;
    padding: 2rem;
}

.section__header {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.section__subheader {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 1rem;
}

.error {
    color: #dc2626;
    margin-bottom: 1rem;
    font-weight: 500;
}

.success {
    color: #16a34a;
    margin-bottom: 1rem;
    font-weight: 500;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background: #1f2937;
    color: white;
    transition: width 0.3s ease;
}

.sidebar.collapsed {
    width: 80px;
}

.sidebar.collapsed .sidebar__title,
.sidebar.collapsed .sidebar__link span {
    display: none;
}

.sidebar__header {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar__logo {
    width: 40px;
    height: 40px;
}

.sidebar__title {
    font-size: 1.25rem;
    font-weight: 600;
}

.sidebar__toggle {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
}

.sidebar__nav {
    padding: 1rem;
}

.sidebar__links {
    list-style: none;
    padding: 0;
}

.sidebar__link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    transition: background 0.2s ease;
}

.sidebar__link:hover,
.sidebar__link.active {
    background: #3b82f6;
}

.sidebar__link i {
    font-size: 1.25rem;
}

/* Profile Form */
.profile__form {
    max-width: 600px;
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.form__group {
    margin-bottom: 1.5rem;
}

.form__group label {
    display: block;
    font-size: 1rem;
    font-weight: 500;
    color: #1f2937;
    margin-bottom: 0.5rem;
}

.form__group input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    color: #1f2937;
}

.form__group input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.action__button {
    background: #3b82f6;
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease;
}

.action__button:hover {
    background: #2563eb;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        width: 80px;
    }
    
    .sidebar__title,
    .sidebar__link span {
        display: none;
    }
    
    .dashboard__content {
        padding: 1rem;
    }
    
    .profile__form {
        padding: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line');
    });

    // Highlight active link
    const links = document.querySelectorAll('.sidebar__link');
    links.forEach(link => {
        link.addEventListener('click', function() {
            links.forEach(l => l.classList.remove('active'));
            this.classList.add('active');
        });
    });
});
</script>

<?php include 'templates/footer.php'; ?>