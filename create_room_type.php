<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
checkAuth('super_admin');

require_once 'db_connect.php';

// Initialize variables
$success = $error = $edit_room_type = null;
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

// Fetch all room types
try {
    $stmt = $pdo->query("SELECT * FROM room_types ORDER BY created_at DESC");
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching room types: " . $e->getMessage();
    $room_types = [];
}

// Handle create room type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_room_type'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $name = sanitize($_POST['room_type_name']);
        $description = sanitize($_POST['description']);
        $base_price = floatval($_POST['base_price']);
        $image_path = sanitize($_POST['image_path']) ?: null;

        try {
            // Validate inputs
            if (empty($name) || strlen($name) < 2) {
                throw new Exception("Room type name must be at least 2 characters long.");
            }
            if ($base_price < 0) {
                throw new Exception("Base price cannot be negative.");
            }
            if ($image_path && !filter_var($image_path, FILTER_VALIDATE_URL) && !file_exists($image_path)) {
                throw new Exception("Invalid image path or URL.");
            }

            // Check for duplicate name
            $stmt = $pdo->prepare("SELECT id FROM room_types WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetch()) {
                throw new Exception("Room type name already exists.");
            }

            $stmt = $pdo->prepare("INSERT INTO room_types (name, description, base_price, image_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $base_price, $image_path]);
            $success = "Room type created successfully!";
            header("Location: create_room_type.php");
            exit();
        } catch (Exception $e) {
            $error = "Error creating room type: " . $e->getMessage();
        }
    }
}

// Handle update room type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_room_type'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $id = (int)$_POST['room_type_id'];
        $name = sanitize($_POST['room_type_name']);
        $description = sanitize($_POST['description']);
        $base_price = floatval($_POST['base_price']);
        $image_path = sanitize($_POST['image_path']) ?: null;

        try {
            // Validate inputs
            if (empty($name) || strlen($name) < 2) {
                throw new Exception("Room type name must be at least 2 characters long.");
            }
            if ($base_price < 0) {
                throw new Exception("Base price cannot be negative.");
            }
            if ($image_path && !filter_var($image_path, FILTER_VALIDATE_URL) && !file_exists($image_path)) {
                throw new Exception("Invalid image path or URL.");
            }

            // Check for duplicate name (excluding current room type)
            $stmt = $pdo->prepare("SELECT id FROM room_types WHERE name = ? AND id != ?");
            $stmt->execute([$name, $id]);
            if ($stmt->fetch()) {
                throw new Exception("Room type name already exists.");
            }

            $stmt = $pdo->prepare("UPDATE room_types SET name = ?, description = ?, base_price = ?, image_path = ? WHERE id = ?");
            $stmt->execute([$name, $description, $base_price, $image_path, $id]);
            $success = "Room type updated successfully!";
            header("Location: create_room_type.php");
            exit();
        } catch (Exception $e) {
            $error = "Error updating room type: " . $e->getMessage();
        }
    }
}

// Handle delete room type
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_room_type'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $id = (int)$_POST['room_type_id'];

        try {
            // Check for dependencies
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE room_type_id = ?");
            $stmt->execute([$id]);
            $room_count = $stmt->fetchColumn();

            if ($room_count > 0) {
                throw new Exception("Cannot delete room type because it is associated with $room_count room(s).");
            }

            $stmt = $pdo->prepare("DELETE FROM room_types WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Room type deleted successfully!";
            header("Location: create_room_type.php");
            exit();
        } catch (Exception $e) {
            $error = "Error deleting room type: " . $e->getMessage();
        }
    }
}

// Fetch room type for editing
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Room Types</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>
<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" loading="lazy" />
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
                <li><a href="create_room_type.php" class="sidebar__link active"><i class="ri-home-2-line"></i><span>Manage Room Types</span></a></li>
                <li><a href="manage_hotels.php" class="sidebar__link"><i class="ri-building-line"></i><span>Manage Hotels</span></a></li>
                <li><a href="manage_users.php" class="sidebar__link"><i class="ri-user-line"></i><span>Manage Users</span></a></li>
                <li><a href="manage_bookings.php" class="sidebar__link"><i class="ri-calendar-check-line"></i><span>Manage Bookings</span></a></li>
                <li><a href="reports.php" class="sidebar__link"><i class="ri-bar-chart-line"></i><span>Reports</span></a></li>
                <li><a href="settings.php" class="sidebar__link"><i class="ri-settings-3-line"></i><span>Settings</span></a></li>
                <li><a href="logout.php" class="sidebar__link"><i class="ri-logout-box-line"></i><span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <button class="mobile-sidebar-toggle" id="mobile-sidebar-toggle">
                <i class="ri-menu-line"></i>
            </button>
            <h1 class="section__header">Manage Room Types</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" loading="lazy" />
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
                <form method="POST" class="admin__form" id="roomTypeForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                    <?php if (isset($edit_room_type)): ?>
                        <input type="hidden" name="room_type_id" value="<?php echo htmlspecialchars($edit_room_type['id']); ?>">
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
                        <label for="image_path" class="form__label">Image Path/URL (Optional)</label>
                        <input type="text" id="image_path" name="image_path" class="form__input" value="<?php echo isset($edit_room_type) ? htmlspecialchars($edit_room_type['image_path']) : ''; ?>" placeholder="e.g., /assets/images/room.jpg or https://example.com/image.jpg">
                    </div>
                    <div class="form__actions">
                        <button type="submit" name="<?php echo isset($edit_room_type) ? 'update_room_type' : 'create_room_type'; ?>" class="btn btn--primary">
                            <i class="ri-home-2-line me-1"></i><?php echo isset($edit_room_type) ? 'Update Room Type' : 'Create Room Type'; ?>
                        </button>
                        <?php if (isset($edit_room_type)): ?>
                            <a href="create_room_type.php" class="btn btn--secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </section>

        <!-- Room Types List Section -->
        <section class="dashboard__section">
            <h2 class="section__subheader">Existing Room Types</h2>
            <div class="table__container">
                <?php if (empty($room_types)): ?>
                    <p class="text-muted text-center">No room types available.</p>
                <?php else: ?>
                    <div class="table__wrapper">
                        <table class="data__table" id="roomTypesTable">
                            <thead>
                                <tr>
                                    <th><i class="ri-hashtag me-1"></i>ID</th>
                                    <th><i class="ri-home-2-line me-1"></i>Name</th>
                                    <th><i class="ri-file-text-line me-1"></i>Description</th>
                                    <th><i class="ri-money-dollar-circle-line me-1"></i>Base Price ($)</th>
                                    <th><i class="ri-image-line me-1"></i>Image Path</th>
                                    <th><i class="ri-calendar-line me-1"></i>Created At</th>
                                    <th><i class="ri-settings-3-line me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($room_types as $room_type): ?>
                                    <tr>
                                        <td><span class="table__badge table__badge--light"><?php echo htmlspecialchars($room_type['id']); ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($room_type['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($room_type['description'] ?? 'No description'); ?></td>
                                        <td>$<?php echo number_format($room_type['base_price'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($room_type['image_path'] ?? 'No image'); ?></td>
                                        <td><?php echo htmlspecialchars($room_type['created_at']); ?></td>
                                        <td>
                                            <a href="create_room_type.php?edit_id=<?php echo $room_type['id']; ?>" class="btn btn--small btn--primary">
                                                <i class="ri-edit-line me-1"></i>Edit
                                            </a>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this room type?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                                <input type="hidden" name="room_type_id" value="<?php echo $room_type['id']; ?>">
                                                <button type="submit" name="delete_room_type" class="btn btn--small btn--danger">
                                                    <i class="ri-delete-bin-line me-1"></i>Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</div>

<style>
/* General Reset */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Dashboard Container */
.dashboard__container {
    display: flex;
    min-height: 100vh;
    background: #f3f4f6;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background: #000000;
    box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease;
    position: fixed;
    height: 100vh;
    z-index: 1000;
    transform: translateX(0);
}

.sidebar.collapsed {
    transform: translateX(-250px);
}

.sidebar__header {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #333333;
}

.sidebar__logo {
    width: 40px;
    height: 40px;
    margin-right: 0.5rem;
}

.sidebar__title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #ffffff;
}

.sidebar__toggle {
    background: none;
    border: none;
    cursor: pointer;
    margin-left: auto;
    font-size: 1.5rem;
    color: #3b82f6;
}

.sidebar__nav {
    padding: 1rem 0;
}

.sidebar__links {
    list-style: none;
}

.sidebar__link {
    display: flex;
    align-items: center;
    padding: 0.75rem 1rem;
    color: #ffffff;
    text-decoration: none;
    font-size: 0.95rem;
    transition: background 0.2s ease;
}

.sidebar__link:hover {
    background: #333333;
}

.sidebar__link.active {
    background: #3b82f6;
    color: #ffffff;
}

.sidebar__link i {
    font-size: 1.25rem;
    margin-right: 0.75rem;
}

/* Main Content */
.dashboard__content {
    margin-left: 250px;
    padding: 1.5rem;
    flex-grow: 1;
    transition: margin-left 0.3s ease;
}

/* Header */
.dashboard__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.mobile-sidebar-toggle {
    display: none;
    background: none;
    border: none;
    font-size: 1.5rem;
    color: #3b82f6;
    cursor: pointer;
    padding: 0.5rem;
}

.section__header {
    font-size: 1.75rem;
    font-weight: 700;
    color: #1f2937;
}

.user__info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.user__avatar {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
}

/* Form Styles */
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

.form__actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

/* Button Styles */
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

.btn--primary:hover:not(:disabled) {
    background: #2563eb;
    transform: translateY(-1px);
}

.btn--secondary {
    background: #6b7280;
    color: white;
}

.btn--secondary:hover:not(:disabled) {
    background: #4b5563;
    transform: translateY(-1px);
}

.btn--danger {
    background: #dc2626;
    color: white;
}

.btn--danger:hover:not(:disabled) {
    background: #b91c1c;
    transform: translateY(-1px);
}

.btn--small {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

/* Alert Styles */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
    transition: opacity 0.3s ease, transform 0.3s ease;
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

/* Table Styles */
.table__container {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-top: 1.5rem;
}

.table__wrapper {
    overflow-x: auto;
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
    font-size: 0.875rem;
    text-transform: uppercase;
}

.data__table td {
    color: #1f2937;
    vertical-align: middle;
}

.data__table tr:hover {
    background: #f3f4f6;
}

.table__badge {
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    background: #e5e7eb;
    color: #374151;
}

.table__badge--light {
    background: #f3f4f6;
}

/* General Styles */
.section__subheader {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.text-muted {
    color: #6b7280;
}

.text-center {
    text-align: center;
}

.me-1 {
    margin-right: 0.25rem;
}

/* Mobile Responsive Styles */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-250px);
    }

    .sidebar.collapsed {
        transform: translateX(-250px);
    }

    .sidebar:not(.collapsed) {
        transform: translateX(0);
    }

    .dashboard__content {
        margin-left: 0;
    }

    .mobile-sidebar-toggle {
        display: block;
    }

    .sidebar__toggle {
        display: none;
    }

    .sidebar__logo, .sidebar__title {
        display: none;
    }

    .form__container {
        padding: 1.5rem;
        max-width: 100%;
    }

    .table__container {
        padding: 1rem;
    }

    .section__header {
        font-size: 1.5rem;
    }

    .section__subheader {
        font-size: 1.2rem;
    }

    .form__input {
        font-size: 0.95rem;
        padding: 0.65rem;
    }

    .btn {
        padding: 0.65rem 1.25rem;
        font-size: 0.95rem;
    }

    .data__table th,
    .data__table td {
        padding: 0.5rem;
        font-size: 0.9rem;
    }

    .table__badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.4rem;
    }
}

@media (max-width: 480px) {
    .dashboard__content {
        padding: 1rem;
    }

    .dashboard__header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .user__info {
        font-size: 0.9rem;
    }

    .user__avatar {
        width: 1.5rem;
        height: 1.5rem;
    }

    .form__container {
        padding: 1rem;
    }

    .form__label {
        font-size: 0.85rem;
    }

    .form__input {
        font-size: 0.9rem;
        padding: 0.5rem;
    }

    .form__actions {
        flex-direction: column;
        gap: 0.5rem;
    }

    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
        width: 100%;
        justify-content: center;
    }

    .data__table th,
    .data__table td {
        font-size: 0.85rem;
        padding: 0.4rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const roomTypeForm = document.getElementById('roomTypeForm');

    // Toggle sidebar function
    function toggleSidebar() {
        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        // Update icons
        if (sidebarToggle) {
            sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line', !isCollapsed);
            sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line', isCollapsed);
        }
        mobileSidebarToggle.querySelector('i').classList.toggle('ri-menu-line', isCollapsed);
        mobileSidebarToggle.querySelector('i').classList.toggle('ri-close-line', !isCollapsed);
        // Adjust content margin
        const dashboardContent = document.querySelector('.dashboard__content');
        dashboardContent.style.marginLeft = window.innerWidth <= 768 && !isCollapsed ? '250px' : '0';
    }

    // Event listeners for toggles
    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
    if (mobileSidebarToggle) mobileSidebarToggle.addEventListener('click', toggleSidebar);

    // Initialize sidebar state
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
        mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
        document.querySelector('.dashboard__content').style.marginLeft = '0';
    } else {
        document.querySelector('.dashboard__content').style.marginLeft = '250px';
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        const dashboardContent = document.querySelector('.dashboard__content');
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
            mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
            dashboardContent.style.marginLeft = '0';
        } else {
            sidebar.classList.remove('collapsed');
            dashboardContent.style.marginLeft = '250px';
        }
    });

    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Form validation
    if (roomTypeForm) {
        roomTypeForm.addEventListener('submit', function(e) {
            const name = document.getElementById('room_type_name').value.trim();
            const basePrice = parseFloat(document.getElementById('base_price').value);
            const imagePath = document.getElementById('image_path').value.trim();
            let errorMessage = '';

            if (name.length < 2) {
                errorMessage = 'Room type name must be at least 2 characters long.';
            } else if (isNaN(basePrice) || basePrice < 0) {
                errorMessage = 'Base price must be a non-negative number.';
            } else if (imagePath && !/^(https?:\/\/[^\s/$.?#].[^\s]*$|\/[^\s/$.?#].[^\s]*$)/.test(imagePath)) {
                errorMessage = 'Image path must be a valid URL or file path.';
            }

            if (errorMessage) {
                e.preventDefault();
                const alert = document.createElement('div');
                alert.className = 'alert alert--error';
                alert.innerHTML = `<i class="ri-error-warning-line"></i><span>${errorMessage}</span>`;
                roomTypeForm.parentNode.prepend(alert);
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => alert.remove(), 300);
                }, 5000);
            }
        });
    }
});
</script>

<?php include 'templates/footer.php'; ?>
</body>
</html>