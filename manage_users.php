<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
checkAuth('super_admin');

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_user'])) {
        $user_id = (int)$_POST['user_id'];
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $role = $_POST['role'];
        $branch_id = $_POST['branch_id'] ? (int)$_POST['branch_id'] : null;
        try {
            $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, branch_id = ? WHERE id = ?");
            $stmt->execute([$name, $email, $role, $branch_id, $user_id]);
            $success = "User updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating user: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'super_admin'");
            $stmt->execute([$user_id]);
            $success = "User deleted successfully!";
        } catch (PDOException $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}

try {
    $stmt = $pdo->query("SELECT u.*, b.name AS branch_name FROM users u LEFT JOIN branches b ON u.branch_id = b.id WHERE u.role != 'super_admin' ORDER BY u.name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->query("SELECT * FROM branches ORDER BY name");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
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
                <li><a href="manage_users.php" class="sidebar__link active"><i class="ri-user-line"></i><span>Manage Users</span></a></li>
                <li><a href="manage_bookings.php" class="sidebar__link"><i class="ri-calendar-check-line"></i><span>Manage Bookings</span></a></li>
                <li><a href="reports.php" class="sidebar__link"><i class="ri-bar-chart-line"></i><span>Reports</span></a></li>
                <li><a href="settings.php" class="sidebar__link"><i class="ri-settings-3-line"></i><span>Settings</span></a></li>
                <li><a href="logout.php" class="sidebar__link"><i class="ri-logout-box-line"></i><span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Manage Users</h1>
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

        <section id="manage-users" class="dashboard__section active">
            <h2 class="section__subheader">Manage Users</h2>
            <div class="table__container">
                <table class="data__table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Branch</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <td><?php echo htmlspecialchars($user['branch_name'] ?? 'N/A'); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        <select name="role" required>
                                            <option value="manager" <?php echo $user['role'] == 'manager' ? 'selected' : ''; ?>>Manager</option>
                                            <option value="customer" <?php echo $user['role'] == 'customer' ? 'selected' : ''; ?>>Customer</option>
                                            <option value="travel_company" <?php echo $user['role'] == 'travel_company' ? 'selected' : ''; ?>>Travel Company</option>
                                        </select>
                                        <select name="branch_id">
                                            <option value="">No Branch</option>
                                            <?php foreach ($branches as $branch): ?>
                                                <option value="<?php echo $branch['id']; ?>" <?php echo $user['branch_id'] == $branch['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($branch['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_user" class="btn btn--primary">Update</button>
                                        <button type="submit" name="delete_user" class="btn btn--danger" onclick="return confirm('Are you sure you want to delete this user?');">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<style>
.table__container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-top: 1.5rem;
}
.data__table {
    width: 100%;
    border-collapse: collapse;
}
.data__table th, .data__table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}
.data__table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
}
.btn--danger {
    background: #ef4444;
    color: white;
}
.btn--danger:hover {
    background: #dc2626;
}
</style>

<script>
document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});
</script>

<?php include 'templates/footer.php'; ?>