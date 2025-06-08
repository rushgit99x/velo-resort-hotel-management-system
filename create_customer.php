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

// Get clerk's branch_id and branch name
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT u.branch_id, b.name AS branch_name 
                      FROM users u 
                      LEFT JOIN branches b ON u.branch_id = b.id 
                      WHERE u.id = ? AND u.role = 'clerk'");
$stmt->execute([$user_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$branch_id = $result['branch_id'] ?? 0;
$branch_name = $result['branch_name'] ?? 'Unknown Branch';

if (!$branch_id) {
    $db_error = "No branch assigned to this clerk.";
}

// Initialize variables
$errors = [];
$success = '';

// Handle customer creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_customer'])) {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Input validation
    if (empty($name) || empty($email)) {
        $errors[] = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif (!empty($phone) && !preg_match('/^\+?\d{10,15}$/', $phone)) {
        $errors[] = "Invalid phone number format (10-15 digits, optional + prefix).";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                $errors[] = "Email is already in use.";
            } else {
                // Generate temporary password
                $temp_password = password_hash('default123', PASSWORD_BCRYPT); // Temporary password
                // Insert new customer
                $stmt = $pdo->prepare("
                    INSERT INTO users (email, password, role, name, created_at)
                    VALUES (?, ?, 'customer', ?, NOW())
                ");
                $stmt->execute([$email, $temp_password, $name]);
                $new_user_id = $pdo->lastInsertId();

                // Optionally store phone number in company_profiles or a custom field
                // For simplicity, we're not using company_profiles here unless required
                $success = "Customer created successfully. Temporary password: default123. Please advise the customer to reset their password.";
            }
        } catch (PDOException $e) {
            $errors[] = "Error creating customer: " . $e->getMessage();
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
                    <a href="create_customer.php" class="sidebar__link active">
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
                    <a href="clerk_settings.php" class="sidebar__link">
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
            <h1 class="section__header"><?php echo htmlspecialchars($branch_name); ?> - Create Customer</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Clerk'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section id="create-customer" class="dashboard__section active">
            <h2 class="section__subheader">Create New Customer</h2>

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

            <div class="form__container">
                <form method="POST" action="create_customer.php">
                    <div class="form__group">
                        <label for="name">Customer Name:</label>
                        <input type="text" name="name" id="name" required>
                    </div>
                    <div class="form__group">
                        <label for="email">Customer Email:</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                    <div class="form__group">
                        <label for="phone">Phone Number (Optional):</label>
                        <input type="text" name="phone" id="phone" placeholder="+1234567890">
                    </div>
                    <button type="submit" name="create_customer" class="btn btn-primary">Create Customer</button>
                </form>
            </div>
        </section>
    </main>
</div>

<style>
/* Styles for the create customer page */
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