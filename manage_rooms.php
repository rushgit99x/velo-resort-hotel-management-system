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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['edit_room'])) {
        $room_id = $_POST['room_id'];
        $branch_id = $_POST['branch_id'];
        $room_type_id = $_POST['room_type_id'];
        $room_number = sanitize($_POST['room_number']);
        $status = $_POST['status'];

        try {
            $stmt = $pdo->prepare("UPDATE rooms SET branch_id = ?, room_type_id = ?, room_number = ?, status = ? WHERE id = ?");
            $stmt->execute([$branch_id, $room_type_id, $room_number, $status, $room_id]);
            $success = "Room updated successfully!";
        } catch (PDOException $e) {
            $error = "Error updating room: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_room'])) {
        $room_id = $_POST['room_id'];
        try {
            // Check for existing bookings
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM bookings WHERE room_id = ?");
            $stmt->execute([$room_id]);
            $booking_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            if ($booking_count > 0) {
                $error = "Cannot delete room with existing bookings.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM rooms WHERE id = ?");
                $stmt->execute([$room_id]);
                $success = "Room deleted successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error deleting room: " . $e->getMessage();
        }
    }
}

// Fetch room data for editing
$edit_room = null;
if (isset($_GET['edit_room_id'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT r.*, b.name AS branch_name, rt.name AS room_type_name
            FROM rooms r
            LEFT JOIN branches b ON r.branch_id = b.id
            LEFT JOIN room_types rt ON r.room_type_id = rt.id
            WHERE r.id = ?
        ");
        $stmt->execute([$_GET['edit_room_id']]);
        $edit_room = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "Error fetching room details: " . $e->getMessage();
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
                <li><a href="admin_dashboard.php" class="sidebar__link"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li><a href="create_branch.php" class="sidebar__link"><i class="ri-building-line"></i><span>Create Branch</span></a></li>
                <li><a href="create_user.php" class="sidebar__link"><i class="ri-user-add-line"></i><span>Create User</span></a></li>
                <li><a href="create_room.php" class="sidebar__link"><i class="ri-home-line"></i><span>Add Room</span></a></li>
                <li><a href="manage_rooms.php" class="sidebar__link active"><i class="ri-home-gear-line"></i><span>Manage Rooms</span></a></li>
                <li><a href="create_room_type.php" class="sidebar__link"><i class="ri-home-2-line"></i><span>Manage Room Types</span></a></li>
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
            <h1 class="section__header">Manage Rooms</h1>
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

        <!-- Manage Rooms Section -->
        <section id="manage-rooms" class="dashboard__section active">
            <?php if ($edit_room): ?>
                <!-- Edit Room Form -->
                <div class="form__container">
                    <h2 class="section__subheader"><i class="ri-edit-line me-2"></i>Edit Room</h2>
                    <form method="POST" class="admin__form" id="editRoomForm">
                        <input type="hidden" name="room_id" value="<?php echo $edit_room['id']; ?>">
                        <div class="form__group">
                            <label for="edit_room_branch_id" class="form__label">Branch</label>
                            <select id="edit_room_branch_id" name="branch_id" class="form__select" required>
                                <option value="">Select Branch</option>
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT * FROM branches ORDER BY name");
                                    while ($branch = $stmt->fetch()) {
                                        $selected = $branch['id'] == $edit_room['branch_id'] ? 'selected' : '';
                                        echo "<option value='{$branch['id']}' $selected>{$branch['name']} ({$branch['location']})</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=''>Error loading branches</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form__group">
                            <label for="edit_room_type_id" class="form__label">Room Type</label>
                            <select id="edit_room_type_id" name="room_type_id" class="form__select" required>
                                <option value="">Select Room Type</option>
                                <?php
                                try {
                                    $stmt = $pdo->query("SELECT * FROM room_types ORDER BY name");
                                    while ($room_type = $stmt->fetch()) {
                                        $selected = $room_type['id'] == $edit_room['room_type_id'] ? 'selected' : '';
                                        echo "<option value='{$room_type['id']}' $selected>{$room_type['name']} (\${$room_type['base_price']}/night)</option>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<option value=''>Error loading room types</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form__group">
                            <label for="edit_room_number" class="form__label">Room Number</label>
                            <input type="text" id="edit_room_number" name="room_number" class="form__input" value="<?php echo htmlspecialchars($edit_room['room_number']); ?>" required>
                            <span id="roomNumberError" class="alert alert--error" style="display: none;">
                                <i class="ri-error-warning-line"></i>
                                <span>Room number already exists for this branch</span>
                            </span>
                        </div>
                        <div class="form__group">
                            <label for="edit_status" class="form__label">Status</label>
                            <select id="edit_status" name="status" class="form__select" required>
                                <option value="available" <?php echo $edit_room['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="occupied" <?php echo $edit_room['status'] == 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                <option value="maintenance" <?php echo $edit_room['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            </select>
                        </div>
                        <div class="form__group">
                            <button type="submit" name="edit_room" id="submitButton" class="btn btn--primary">
                                <i class="ri-save-line"></i> Update Room
                            </button>
                            <a href="manage_rooms.php" class="btn btn--secondary">
                                <i class="ri-arrow-left-line"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Room List Table -->
                <div class="form__container">
                    <div class="table__header">
                        <h2 class="section__subheader"><i class="ri-home-gear-line me-2"></i>Room Management</h2>
                        <span class="table__badge">
                            <?php
                            try {
                                $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM rooms");
                                $total_rooms = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
                                echo $total_rooms . ' Total Rooms';
                            } catch (PDOException $e) {
                                echo '0 Rooms';
                            }
                            ?>
                        </span>
                    </div>
                    <div class="table__wrapper">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th><i class="ri-hashtag me-1"></i>ID</th>
                                    <th><i class="ri-building-line me-1"></i>Branch</th>
                                    <th><i class="ri-home-2-line me-1"></i>Room Type</th>
                                    <th><i class="ri-door-open-line me-1"></i>Room Number</th>
                                    <th><i class="ri-checkbox-circle-line me-1"></i>Status</th>
                                    <th class="text-center"><i class="ri-settings-3-line me-1"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                try {
                                    $stmt = $pdo->query("
                                        SELECT r.*, b.name AS branch_name, rt.name AS room_type_name
                                        FROM rooms r
                                        LEFT JOIN branches b ON r.branch_id = b.id
                                        LEFT JOIN room_types rt ON r.room_type_id = rt.id
                                        ORDER BY r.id
                                    ");
                                    $room_count = 0;
                                    while ($room = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $room_count++;
                                        $status_class = '';
                                        $status_icon = '';
                                        switch ($room['status']) {
                                            case 'available':
                                                $status_class = 'success';
                                                $status_icon = 'ri-checkbox-circle-fill';
                                                break;
                                            case 'occupied':
                                                $status_class = 'warning';
                                                $status_icon = 'ri-user-fill';
                                                break;
                                            case 'maintenance':
                                                $status_class = 'danger';
                                                $status_icon = 'ri-tools-line';
                                                break;
                                            default:
                                                $status_class = 'secondary';
                                                $status_icon = 'ri-question-line';
                                        }
                                        echo "<tr>";
                                        echo "<td><span class='table__badge table__badge--light'>" . htmlspecialchars($room['id']) . "</span></td>";
                                        echo "<td><strong>" . htmlspecialchars($room['branch_name'] ?? 'Unknown Branch') . "</strong></td>";
                                        echo "<td>" . htmlspecialchars($room['room_type_name'] ?? 'Unknown Type') . "</td>";
                                        echo "<td><span class='table__badge table__badge--info'>" . htmlspecialchars($room['room_number']) . "</span></td>";
                                        echo "<td><span class='table__badge table__badge--{$status_class}'><i class='{$status_icon} me-1'></i>" . ucfirst(htmlspecialchars($room['status'])) . "</span></td>";
                                        echo "<td class='text-center'>";
                                        echo "<div class='btn__group'>";
                                        echo "<a href='manage_rooms.php?edit_room_id={$room['id']}' class='btn btn--small btn--primary' title='Edit Room'><i class='ri-edit-line'></i></a>";
                                        echo "<button type='button' class='btn btn--small btn--danger' onclick='confirmDelete({$room['id']})' title='Delete Room'><i class='ri-delete-bin-line'></i></button>";
                                        echo "</div>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                    if ($room_count == 0) {
                                        echo "<tr>";
                                        echo "<td colspan='6' class='text-center py-5'>";
                                        echo "<div class='text-muted'>";
                                        echo "<i class='ri-home-2-line display-1 d-block mb-3'></i>";
                                        echo "<h5>No Rooms Found</h5>";
                                        echo "<p>Start by adding some rooms to your hotel branches.</p>";
                                        echo "</div>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                } catch (PDOException $e) {
                                    echo "<tr><td colspan='6' class='text-center text-danger py-4'>";
                                    echo "<i class='ri-error-warning-line me-2'></i>";
                                    echo "Error loading rooms: " . htmlspecialchars($e->getMessage());
                                    echo "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal" id="deleteModal">
    <div class="modal__dialog">
        <div class="modal__content">
            <div class="modal__header">
                <h5 class="modal__title"><i class="ri-error-warning-line me-2"></i>Confirm Deletion</h5>
                <button type="button" class="modal__close" onclick="closeModal('deleteModal')"><i class="ri-close-line"></i></button>
            </div>
            <div class="modal__body">
                <p>Are you sure you want to delete this room? This action cannot be undone.</p>
            </div>
            <div class="modal__footer">
                <button type="button" class="btn btn--secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <form method="POST" style="display:inline;" id="deleteForm">
                    <input type="hidden" name="room_id" id="deleteRoomId">
                    <button type="submit" name="delete_room" class="btn btn--danger">
                        <i class="ri-delete-bin-line me-1"></i>Delete Room
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Form styles */
.form__container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    max-width: 800px;
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

/* Button styles */
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

/* Alert styles */
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

/* Table styles */
.table__wrapper {
    overflow-x: auto;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1.5rem;
}

.table th,
.table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
    font-size: 0.875rem;
    text-transform: uppercase;
}

.table td {
    color: #1f2937;
    vertical-align: middle;
}

.table__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.table__badge {
    font-size: 0.75rem;
    font-weight: 500;
    padding: 0.5rem 1rem;
    border-radius: 12px;
    background: #e5e7eb;
    color: #374151;
}

.table__badge--light {
    background: #f3f4f6;
    color: #374151;
}

.table__badge--info {
    background: #3b82f6;
    color: white;
}

.table__badge--success {
    background: #10b981;
    color: white;
}

.table__badge--warning {
    background: #f59e0b;
    color: #1f2937;
}

.table__badge--danger {
    background: #ef4444;
    color: white;
}

.table tr:hover {
    background: rgba(0, 0, 0, 0.035);
}

/* Modal styles */
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
    z-index: 1000;
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
    background: #ef4444;
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

/* General styles */
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

.text-muted {
    color: #6b7280;
}

.text-center {
    text-align: center;
}

.py-5 {
    padding-top: 3rem;
    padding-bottom: 3rem;
}

.display-1 {
    font-size: 4rem;
    line-height: 1;
}

.me-1 {
    margin-right: 0.25rem;
}

.me-2 {
    margin-right: 0.5rem;
}

.mb-3 {
    margin-bottom: 1rem;
}
</style>

<script>
function confirmDelete(roomId) {
    document.getElementById('deleteRoomId').value = roomId;
    document.getElementById('deleteModal').classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss alerts
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    // Sidebar toggle
    document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });

    // Room number validation for edit form
    const editRoomForm = document.getElementById('editRoomForm');
    const roomNumberInput = document.getElementById('edit_room_number');
    const branchSelect = document.getElementById('edit_room_branch_id');
    const submitButton = document.getElementById('submitButton');
    const roomNumberError = document.getElementById('roomNumberError');
    const roomId = document.querySelector('input[name="room_id"]').value;
    let isRoomNumberValid = true; // Initially true to allow unchanged room number

    if (editRoomForm) {
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
                body: `room_number=${encodeURIComponent(roomNumber)}&branch_id=${encodeURIComponent(branchId)}&room_id=${encodeURIComponent(roomId)}`
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

        editRoomForm.addEventListener('submit', function(e) {
            if (!isRoomNumberValid) {
                e.preventDefault();
                roomNumberError.style.display = 'flex';
            }
        });

        // Initial validation
        validateRoomNumber();
    }
});
</script>

<?php include 'templates/footer.php'; ?>