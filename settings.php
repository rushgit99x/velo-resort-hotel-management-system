<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
checkAuth('super_admin');

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;
    try {
        if ($password) {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $email, $password, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $stmt->execute([$name, $email, $_SESSION['user_id']]);
        }
        $_SESSION['username'] = $name;
        $success = "Profile updated successfully!";
    } catch (PDOException $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}

try {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching profile: " . $e->getMessage();
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
                <li><a href="admin_dashboard.php" class="sidebar__link"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li><a href="create_branch.php" class="sidebar__link"><i class="ri-building-line"></i><span>Create Branch</span></a></li>
                <li><a href="create_user.php" class="sidebar__link"><i class="ri-user-add-line"></i><span>Create User</span></a></li>
                <li><a href="create_room.php" class="sidebar__link"><i class="ri-home-line"></i><span>Add Room</span></a></li>
                <li><a href="manage_rooms.php" class="sidebar__link"><i class="ri-home-gear-line"></i><span>Manage Rooms</span></a></li>
                <li><a href="create_room_type.php" class="sidebar__link"><i class="ri-home-2-line"></i><span>Manage Room Types</span></a></li>
                <li><a href="manage_hotels.php" class="sidebar__link"><i class="ri-building-line"></i><span>Manage Hotels</span></a></li>
                <li><a href="manage_users.php" class="sidebar__link"><i class="ri-user-line"></i><span>Manage Users</span></a></li>
                <li><a href="manage_bookings.php" class="sidebar__link"><i class="ri-calendar-check-line"></i><span>Manage Bookings</span></a></li>
                <li><a href="reports.php" class="sidebar__link"><i class="ri-bar-chart-line"></i><span>Reports</span></a></li>
                <li><a href="settings.php" class="sidebar__link active"><i class="ri-settings-3-line"></i><span>Settings</span></a></li>
                <li><a href="logout.php" class="sidebar__link"><i class="ri-logout-box-line"></i><span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Settings</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

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

        <section id="settings" class="dashboard__section active">
            <h2 class="section__subheader">Update Profile</h2>
            <div class="form__container">
                <form method="POST" class="admin__form">
                    <div class="form__group">
                        <label for="name" class="form__label">Full Name</label>
                        <input type="text" id="name" name="name" class="form__input" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form__group">
                        <label for="email" class="form__label">Email Address</label>
                        <input type="email" id="email" name="email" class="form__input" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form__group">
                        <label for="password" class="form__label">New Password (leave blank to keep current)</label>
                        <input type="password" id="password" name="password" class="form__input">
                    </div>
                    <button type="submit" name="update_profile" class="btn btn--primary">
                        <i class="ri-save-line"></i>
                        Update Profile
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
}
.form__input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
.btn--primary {
    background: #3b82f6;
    color: white;
}
.btn--primary:hover {
    background: #2563eb;
}
</style>

<script>
document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});
</script>

<?php include 'templates/footer.php'; ?>