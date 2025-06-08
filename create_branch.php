<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to super admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_branch'])) {
    $branch_name = sanitize($_POST['branch_name']);
    $location = sanitize($_POST['location']);
    try {
        $stmt = $pdo->prepare("INSERT INTO branches (name, location) VALUES (?, ?)");
        $stmt->execute([$branch_name, $location]);
        $success = "Branch created successfully!";
    } catch (PDOException $e) {
        $error = "Error creating branch: " . $e->getMessage();
    }
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
                <li>
                    <a href="admin_dashboard.php" class="sidebar__link">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="create_branch.php" class="sidebar__link active">
                        <i class="ri-building-line"></i>
                        <span>Create Branch</span>
                    </a>
                </li>
                <li>
                    <a href="create_user.php" class="sidebar__link">
                        <i class="ri-user-add-line"></i>
                        <span>Create User</span>
                    </a>
                </li>
                <li>
                    <a href="create_room.php" class="sidebar__link">
                        <i class="ri-home-line"></i>
                        <span>Add Room</span>
                    </a>
                </li>
                <li>
                    <a href="manage_rooms.php" class="sidebar__link">
                        <i class="ri-home-gear-line"></i>
                        <span>Manage Rooms</span>
                    </a>
                </li>
                <li>
                    <a href="create_room_type.php" class="sidebar__link">
                        <i class="ri-home-2-line"></i>
                        <span>Manage Room Types</span>
                    </a>
                </li>
                <li>
                    <a href="manage_hotels.php" class="sidebar__link">
                        <i class="ri-building-line"></i>
                        <span>Manage Hotels</span>
                    </a>
                </li>
                <li>
                    <a href="manage_users.php" class="sidebar__link">
                        <i class="ri-user-line"></i>
                        <span>Manage Users</span>
                    </a>
                </li>
                <li>
                    <a href="manage_bookings.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Manage Bookings</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php" class="sidebar__link">
                        <i class="ri-bar-chart-line"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php" class="sidebar__link">
                        <i class="ri-settings-3-line"></i>
                        <span>Settings</span>
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
            <h1 class="section__header">Create New Branch</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <!-- Success/Error Messages -->
        <?php if (isset($error)): ?>
            <div class="alert alert--error">
                <i class="ri-error-warning-line"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php elseif (isset($success)): ?>
            <div class="alert alert--success">
                <i class="ri-check-line"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <!-- Create Branch Section -->
        <section class="dashboard__section active">
            <h2 class="section__subheader">Create New Branch</h2>
            <div class="form__container">
                <form method="POST" class="admin__form">
                    <div class="form__group">
                        <label for="branch_name" class="form__label">Branch Name</label>
                        <input type="text" id="branch_name" name="branch_name" class="form__input" required>
                    </div>
                    <div class="form__group">
                        <label for="location" class="form__label">Location</label>
                        <input type="text" id="location" name="location" class="form__input" required>
                    </div>
                    <button type="submit" name="create_branch" class="btn btn--primary">
                        <i class="ri-add-line"></i>
                        Create Branch
                    </button>
                </form>
            </div>
        </section>
    </main>
</div>

<style>
.form__container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    max-width: 600px;
    margin-top: 1.5rem;
}

.admin__form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.form__group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form__label {
    font-weight: 600;
    color: #374151;
    font-size: 0.9rem;
}

.form__input {
    padding: 0.75rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form__input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.btn {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    justify-content: center;
    transition: all 0.2s ease;
}

.btn--primary {
    background: #3b82f6;
    color: white;
}

.btn--primary:hover {
    background: #2563eb;
    transform: translateY(-1px);
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.alert--success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert--error {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fca5a5;
}

.section__subheader {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.sidebar__link.active {
    background: #3b82f6;
    color: white;
}

.dashboard__section {
    display: block;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });
});
</script>

<?php include 'templates/footer.php'; ?>