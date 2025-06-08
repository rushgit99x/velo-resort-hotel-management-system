<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to super admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: login.php");
    exit();
}

// Include database connection and functions
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Initialize variables
$success = $error = $edit_room_type = null;

// Fetch all room types for display
try {
    $stmt = $pdo->query("SELECT * FROM room_types ORDER BY created_at DESC");
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching room types: " . $e->getMessage();
    $room_types = [];
}

// Handle form submission for creating a new room type
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_room_type'])) {
    $name = sanitize($_POST['room_type_name']);
    $description = sanitize($_POST['description']);
    $base_price = floatval($_POST['base_price']);
    $image_path = sanitize($_POST['image_path']) ?: null;

    try {
        $stmt = $pdo->prepare("INSERT INTO room_types (name, description, base_price, image_path) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $description, $base_price, $image_path]);
        $success = "Room type created successfully!";
        header("Location: create_room_type.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error creating room type: " . $e->getMessage();
    }
}

// Handle form submission for updating a room type
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_room_type'])) {
    $id = (int)$_POST['room_type_id'];
    $name = sanitize($_POST['room_type_name']);
    $description = sanitize($_POST['description']);
    $base_price = floatval($_POST['base_price']);
    $image_path = sanitize($_POST['image_path']) ?: null;

    try {
        $stmt = $pdo->prepare("UPDATE room_types SET name = ?, description = ?, base_price = ?, image_path = ? WHERE id = ?");
        $stmt->execute([$name, $description, $base_price, $image_path, $id]);
        $success = "Room type updated successfully!";
        header("Location: create_room_type.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating room type: " . $e->getMessage();
    }
}

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_room_type'])) {
    $id = (int)$_POST['room_type_id'];

    try {
        // Check for dependencies in the rooms table
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE room_type_id = ?");
        $stmt->execute([$id]);
        $room_count = $stmt->fetchColumn();

        if ($room_count > 0) {
            $error = "Cannot delete room type because it is associated with $room_count room(s). Please remove associated rooms first.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM room_types WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Room type deleted successfully!";
            header("Location: create_room_type.php");
            exit();
        }
    } catch (PDOException $e) {
        $error = "Error deleting room type: " . $e->getMessage();
    }
}

// Fetch room type for editing if edit_id is provided
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM room_types WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_room_type = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$edit_room_type) {
            $error = "Room type not found.";
        }
    } catch (PDOException $e) {
        $error = "Error fetching room type: " . $e->getMessage();
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
                    <a href="create_branch.php" class="sidebar__link">
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
                    <a href="create_room_type.php" class="sidebar__link active">
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
            <h1 class="section__header">Manage Room Types</h1>
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

        <!-- Create/Edit Room Type Section -->
        <section class="dashboard__section active">
            <h2 class="section__subheader"><?php echo isset($edit_room_type) ? 'Edit Room Type' : 'Create New Room Type'; ?></h2>
            <div class="form__container">
                <form method="POST" class="admin__form">
                    <?php if (isset($edit_room_type)): ?>
                        <input type="hidden" name="room_type_id" value="<?php echo $edit_room_type['id']; ?>">
                    <?php endif; ?>
                    <div class="form__group">
                        <label for="room_type_name" class="form__label">Room Type Name</label>
                        <input type="text" id="room_type_name" name="room_type_name" class="form__input" value="<?php echo isset($edit_room_type) ? htmlspecialchars($edit_room_type['name']) : ''; ?>" required>
                    </div>
                    <div class="form__group">
                        <label for="description" class="form__label">Description</label>
                        <textarea id="description" name="description" class="form__input" rows="4"><?php echo isset($edit_room_type) ? htmlspecialchars($edit_room_type['description']) : ''; ?></textarea>
                    </div>
                    <div class="form__group">
                        <label for="base_price" class="form__label">Base Price per Night ($)</label>
                        <input type="number" id="base_price" name="base_price" step="0.01" min="0" class="form__input" value="<?php echo isset($edit_room_type) ? htmlspecialchars($edit_room_type['base_price']) : ''; ?>" required>
                    </div>
                    <div class="form__group">
                        <label for="image_path" class="form__label">Image Path (Optional)</label>
                        <input type="text" id="image_path" name="image_path" class="form__input" value="<?php echo isset($edit_room_type) ? htmlspecialchars($edit_room_type['image_path']) : ''; ?>">
                    </div>
                    <button type="submit" name="<?php echo isset($edit_room_type) ? 'update_room_type' : 'create_room_type'; ?>" class="btn btn--primary">
                        <i class="ri-home-2-line"></i>
                        <?php echo isset($edit_room_type) ? 'Update Room Type' : 'Create Room Type'; ?>
                    </button>
                    <?php if (isset($edit_room_type)): ?>
                        <a href="create_room_type.php" class="btn btn--secondary">Cancel</a>
                    <?php endif; ?>
                </form>
            </div>
        </section>

        <!-- Room Types List Section -->
        <section class="dashboard__section">
            <h2 class="section__subheader">Existing Room Types</h2>
            <div class="table__container">
                <?php if (empty($room_types)): ?>
                    <p>No room types available.</p>
                <?php else: ?>
                    <table class="data__table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Base Price ($)</th>
                                <th>Image Path</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($room_types as $room_type): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($room_type['id']); ?></td>
                                    <td><?php echo htmlspecialchars($room_type['name']); ?></td>
                                    <td><?php echo htmlspecialchars($room_type['description'] ?? 'No description'); ?></td>
                                    <td><?php echo number_format($room_type['base_price'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($room_type['image_path'] ?? 'No image'); ?></td>
                                    <td><?php echo htmlspecialchars($room_type['created_at']); ?></td>
                                    <td>
                                        <a href="create_room_type.php?edit_id=<?php echo $room_type['id']; ?>" class="btn btn--small btn--primary">Edit</a>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this room type?');">
                                            <input type="hidden" name="room_type_id" value="<?php echo $room_type['id']; ?>">
                                            <button type="submit" name="delete_room_type" class="btn btn--small btn--danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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

.form__input,
.form__select {
    padding: 0.75rem;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.form__input:focus,
.form__select:focus {
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
    display: inline-flex;
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

.btn--secondary {
    background: #6b7280;
    color: white;
}

.btn--secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

.btn--danger {
    background: #dc2626;
    color: white;
}

.btn--danger:hover {
    background: #b91c1c;
    transform: translateY(-1px);
}

.btn--small {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
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
    margin-bottom: 2rem;
}

.table__container {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-top: 1.5rem;
}

.data__table {
    width: 100%;
    border-collapse: collapse;
}

.data__table th,
.data__table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.data__table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
}

.data__table td {
    color: #374151;
}

.data__table tr:hover {
    background: #f3f4f6;
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