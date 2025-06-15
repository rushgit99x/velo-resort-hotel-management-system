<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
checkAuth('super_admin');

require_once 'db_connect.php';

// Initialize variables
$success = $error = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_booking'])) {
        $booking_id = (int)$_POST['booking_id'];
        $status = sanitize($_POST['status']);

        try {
            // Validate status
            if (!in_array($status, ['pending', 'confirmed', 'cancelled'])) {
                throw new Exception("Invalid status value.");
            }

            // Fetch current booking details
            $stmt = $pdo->prepare("SELECT b.room_id, b.status AS current_status, r.status AS room_status 
                                   FROM bookings b 
                                   JOIN rooms r ON b.room_id = r.id 
                                   WHERE b.id = ?");
            $stmt->execute([$booking_id]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$booking) {
                throw new Exception("Booking not found.");
            }

            // Prevent redundant updates
            if ($booking['current_status'] === $status) {
                throw new Exception("Booking is already in the selected status.");
            }

            // If confirming, check room availability
            if ($status === 'confirmed' && $booking['room_status'] !== 'available') {
                throw new Exception("Cannot confirm booking: Room is not available.");
            }

            // Update booking status
            $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$status, $booking_id]);

            // Update room status
            if ($status === 'confirmed') {
                $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                $stmt->execute([$booking['room_id']]);
            } elseif ($status === 'cancelled' && $booking['current_status'] === 'confirmed') {
                $stmt = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
                $stmt->execute([$booking['room_id']]);
            }

            $success = "Booking updated successfully!";
        } catch (Exception $e) {
            $error = "Error updating booking: " . $e->getMessage();
        }
    }
}

// Fetch all bookings
try {
    $stmt = $pdo->query("SELECT b.*, u.name AS user_name, r.room_number, rt.name AS room_type_name, br.name AS branch_name 
                         FROM bookings b 
                         JOIN users u ON b.user_id = u.id 
                         JOIN rooms r ON b.room_id = r.id 
                         JOIN branches br ON b.branch_id = br.id 
                         LEFT JOIN room_types rt ON r.room_type_id = rt.id 
                         ORDER BY b.created_at DESC");
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
    $bookings = [];
}

include 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings</title>
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
                <li><a href="create_room_type.php" class="sidebar__link"><i class="ri-home-2-line"></i><span>Manage Room Types</span></a></li>
                <li><a href="manage_hotels.php" class="sidebar__link"><i class="ri-building-line"></i><span>Manage Hotels</span></a></li>
                <li><a href="manage_users.php" class="sidebar__link"><i class="ri-user-line"></i><span>Manage Users</span></a></li>
                <li><a href="manage_bookings.php" class="sidebar__link active"><i class="ri-calendar-check-line"></i><span>Manage Bookings</span></a></li>
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
            <h1 class="section__header">Manage Bookings</h1>
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

        <!-- Manage Bookings Section -->
        <section id="manage-bookings" class="dashboard__section active">
            <h2 class="section__subheader">Individual Bookings</h2>
            <div class="table__container">
                <?php if (empty($bookings)): ?>
                    <p class="text-muted text-center">No bookings available.</p>
                <?php else: ?>
                    <div class="table__wrapper">
                        <table class="data__table">
                            <thead>
                                <tr>
                                    <th><i class="ri-hashtag me-1"></i>ID</th>
                                    <th><i class="ri-user-line me-1"></i>User</th>
                                    <th><i class="ri-building-line me-1"></i>Branch</th>
                                    <th><i class="ri-home-line me-1"></i>Room</th>
                                    <th><i class="ri-calendar-line me-1"></i>Check-In</th>
                                    <th><i class="ri-calendar-line me-1"></i>Check-Out</th>
                                    <th><i class="ri-checkbox-circle-line me-1"></i>Status</th>
                                    <th><i class="ri-settings-3-line me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td><span class="table__badge table__badge--light"><?php echo $booking['id']; ?></span></td>
                                        <td><strong><?php echo htmlspecialchars($booking['user_name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($booking['branch_name']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['room_number'] . ' (' . ($booking['room_type_name'] ?? 'Not Assigned') . ')'); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_in']); ?></td>
                                        <td><?php echo htmlspecialchars($booking['check_out']); ?></td>
                                        <td><span class="table__badge table__badge--info"><?php echo htmlspecialchars($booking['status']); ?></span></td>
                                        <td>
                                            <div class="btn__group">
                                                <button type="button" class="btn btn--small btn--primary" onclick="openEditBookingModal(<?php echo $booking['id']; ?>, '<?php echo addslashes(htmlspecialchars($booking['status'])); ?>')" title="Edit Booking Status"><i class="ri-edit-line"></i></button>
                                            </div>
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

<!-- Edit Booking Modal -->
<div class="modal" id="editBookingModal">
    <div class="modal__dialog">
        <div class="modal__content">
            <div class="modal__header">
                <h5 class="modal__title"><i class="ri-edit-line me-2"></i>Edit Booking Status</h5>
                <button type="button" class="modal__close" onclick="closeModal('editBookingModal')"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal__body">
                <form method="POST" id="editBookingForm">
                    <input type="hidden" name="booking_id" id="editBookingId">
                    <div class="form__group">
                        <label for="editBookingStatus" class="form__label">Status</label>
                        <select id="editBookingStatus" name="status" class="form__select" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('editBookingModal')">Cancel</button>
                <button type="submit" form="editBookingForm" name="update_booking" class="btn btn--primary">
                    <i class="ri-save-line me-1"></i>Save Changes
                </button>
            </div>
        </div>
    </div>
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

.btn--primary:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.btn--primary:hover:not(:disabled) {
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
    background: #ef4444;
    color: white;
}

.btn--danger:hover {
    background: #dc2626;
    transform: translateY(-1px);
}

.btn--small {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.btn__group {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
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

.table__badge--info {
    background: #3b82f6;
    color: white;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
    z-index: 1001;
}

.modal.active {
    display: flex;
}

.modal__dialog {
    max-width: 500px;
    width: 90%;
}

.modal__content {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.modal__header {
    background: #3b82f6;
    color: white;
    padding: 1rem 1.5rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal__title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0;
}

.modal__close {
    background: none;
    border: none;
    color: white;
    font-size: 1.25rem;
    cursor: pointer;
}

.modal__body {
    padding: 1.5rem;
    color: #1f2937;
}

.modal__footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
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

.me-2 {
    margin-right: 0.5rem;
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

    .form__input,
    .form__select {
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

    .btn--small {
        padding: 0.4rem 0.8rem;
        font-size: 0.85rem;
    }

    .modal__dialog {
        width: 95%;
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

    .data__table th,
    .data__table td {
        font-size: 0.85rem;
        padding: 0.4rem;
    }

    .btn__group {
        flex-direction: column;
        gap: 0.3rem;
    }

    .modal__title {
        font-size: 1.1rem;
    }

    .modal__body {
        padding: 1rem;
    }

    .modal__footer {
        flex-direction: column;
        gap: 0.5rem;
    }
}
</style>

<script>
function openEditBookingModal(id, status) {
    document.getElementById('editBookingId').value = id;
    document.getElementById('editBookingStatus').value = status;
    document.getElementById('editBookingModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const mobileSidebarToggle = document.getElementById('mobile-sidebar-toggle');

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

    // Form validation for edit booking
    const editBookingForm = document.getElementById('editBookingForm');
    if (editBookingForm) {
        editBookingForm.addEventListener('submit', function(e) {
            const status = document.getElementById('editBookingStatus').value;
            if (!['pending', 'confirmed', 'cancelled'].includes(status)) {
                e.preventDefault();
                const alert = document.createElement('div');
                alert.className = 'alert alert--error';
                alert.innerHTML = '<i class="ri-error-warning-line"></i><span>Please select a valid status.</span>';
                editBookingForm.parentNode.prepend(alert);
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