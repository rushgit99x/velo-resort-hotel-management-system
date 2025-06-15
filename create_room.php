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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_room'])) {
    $branch_id = $_POST['branch_id'];
    $room_type_id = $_POST['room_type_id'];
    $room_number = sanitize($_POST['room_number']);
    $status = $_POST['status'] ?: 'available';

    try {
        $stmt = $pdo->prepare("INSERT INTO rooms (branch_id, room_type_id, room_number, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$branch_id, $room_type_id, $room_number, $status]);
        $success = "Room created successfully!";
    } catch (PDOException $e) {
        $error = "Error creating room: " . $e->getMessage();
    }
}

include 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Room</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
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
                    <a href="create_room.php" class="sidebar__link active">
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
            <button class="mobile-sidebar-toggle" id="mobile-sidebar-toggle">
                <i class="ri-menu-line"></i>
            </button>
            <h1 class="section__header">Add New Room</h1>
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

        <!-- Create Room Section -->
        <section class="dashboard__section active">
            <h2 class="section__subheader">Add New Room</h2>
            <div class="form__container">
                <form method="POST" class="admin__form" id="roomForm">
                    <div class="form__group">
                        <label for="room_branch_id" class="form__label">Branch</label>
                        <select id="room_branch_id" name="branch_id" class="form__select" required>
                            <option value="">Select Branch</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT * FROM branches ORDER BY name");
                                while ($branch = $stmt->fetch()) {
                                    echo "<option value='{$branch['id']}'>{$branch['name']} ({$branch['location']})</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error loading branches</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form__group">
                        <label for="room_type_id" class="form__label">Room Type</label>
                        <select id="room_type_id" name="room_type_id" class="form__select" required>
                            <option value="">Select Room Type</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT * FROM room_types ORDER BY name");
                                while ($room_type = $stmt->fetch()) {
                                    echo "<option value='{$room_type['id']}'>{$room_type['name']} (\${$room_type['base_price']}/night)</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Error loading room types</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form__group">
                        <label for="room_number" class="form__label">Room Number</label>
                        <input type="text" id="room_number" name="room_number" class="form__input" required>
                        <span id="roomNumberError" class="alert alert--error" style="display: none;">
                            <i class="ri-error-warning-line"></i>
                            <span>Room number already exists for this branch</span>
                        </span>
                    </div>
                    <div class="form__group">
                        <label for="status" class="form__label">Status</label>
                        <select id="status" name="status" class="form__select" required>
                            <option value="available">Available</option>
                            <option value="occupied">Occupied</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                    <button type="submit" name="create_room" id="submitButton" class="btn btn--primary">
                        <i class="ri-home-add-line"></i>
                        Add Room
                    </button>
                </form>
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

.btn--primary:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.btn--primary:hover:not(:disabled) {
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

.section__subheader {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
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

    .section__header {
        font-size: 1.5rem;
    }

    .section__subheader {
        font-size: 1.2rem;
    }

    .form__input,
    .form__select {
        font-size: 0.95rem;
        padding: 0.65rem;
    }

    .btn {
        padding: 0.65rem 1.25rem;
        font-size: 0.95rem;
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

    .form__input,
    .form__select {
        font-size: 0.9rem;
        padding: 0.5rem;
    }

    .btn {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');
    const roomForm = document.getElementById('roomForm');
    const roomNumberInput = document.getElementById('room_number');
    const branchSelect = document.getElementById('room_branch_id');
    const submitButton = document.getElementById('submitButton');
    const roomNumberError = document.getElementById('roomNumberError');
    let isRoomNumberValid = false;

    // Toggle sidebar function
    function toggleSidebar() {
        sidebar.classList.toggle('collapsed');
        const isCollapsed = sidebar.classList.contains('collapsed');
        // Update icons
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line', !isCollapsed);
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line', isCollapsed);
        mobileSidebarToggle.querySelector('i').classList.toggle('ri-menu-line', isCollapsed);
        mobileSidebarToggle.querySelector('i').classList.toggle('ri-close-line', !isCollapsed);
        // Adjust margin-left for content when sidebar is visible on mobile
        const dashboardContent = document.querySelector('.dashboard__content');
        if (window.innerWidth <= 768) {
            dashboardContent.style.marginLeft = isCollapsed ? '0' : '250px';
        }
    }

    // Event listeners for both toggles
    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);
    if (mobileSidebarToggle) mobileSidebarToggle.addEventListener('click', toggleSidebar);

    // Auto-collapse sidebar on mobile and set initial state
    if (window.innerWidth <= 768) {
        sidebar.classList.add('collapsed');
        mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
        mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
        document.querySelector('.dashboard__content').style.marginLeft = '0';
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth <= 768) {
            sidebar.classList.add('collapsed');
            mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
            mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
            document.querySelector('.dashboard__content').style.marginLeft = '0';
        } else {
            sidebar.classList.remove('collapsed');
            mobileSidebarToggle.querySelector('i').classList.add('ri-menu-line');
            mobileSidebarToggle.querySelector('i').classList.remove('ri-close-line');
            document.querySelector('.dashboard__content').style.marginLeft = '250px';
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

    // Room number validation
    function validateRoomNumber() {
        const roomNumber = roomNumberInput.value.trim();
        const branchId = branchSelect.value;

        if (!roomNumber || !branchId) {
            roomNumberError.style.display = 'none';
            submitButton.disabled = true;
            isRoomNumberValid = false;
            return;
        }

        fetch('check_room_number.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `room_number=${encodeURIComponent(roomNumber)}&branch_id=${encodeURIComponent(branchId)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                roomNumberError.textContent = data.error;
                roomNumberError.style.display = 'flex';
                submitButton.disabled = true;
                isRoomNumberValid = false;
            } else if (data.exists) {
                roomNumberError.textContent = 'Room number already exists for this branch';
                roomNumberError.style.display = 'flex';
                submitButton.disabled = true;
                isRoomNumberValid = false;
            } else {
                roomNumberError.style.display = 'none';
                submitButton.disabled = false;
                isRoomNumberValid = true;
            }
        })
        .catch(() => {
            roomNumberError.textContent = 'Error checking room number';
            roomNumberError.style.display = 'flex';
            submitButton.disabled = true;
            isRoomNumberValid = false;
        });
    }

    roomNumberInput.addEventListener('input', validateRoomNumber);
    branchSelect.addEventListener('change', validateRoomNumber);

    roomForm.addEventListener('submit', function(e) {
        if (!isRoomNumberValid) {
            e.preventDefault();
            roomNumberError.style.display = 'flex';
        }
    });
});
</script>

<?php include 'templates/footer.php'; ?>
</body>
</html>