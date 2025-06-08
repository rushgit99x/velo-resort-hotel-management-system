<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/functions.php';
checkAuth('super_admin');

require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_booking'])) {
        $booking_id = (int)$_POST['booking_id'];
        $status = sanitize($_POST['status']);
        try {
            // Validate status
            if (!in_array($status, ['pending', 'confirmed', 'cancelled'])) {
                throw new Exception("Invalid status value.");
            }
            // If confirming, check room availability
            if ($status === 'confirmed') {
                $stmt = $pdo->prepare("SELECT r.status FROM bookings b JOIN rooms r ON b.room_id = r.id WHERE b.id = ?");
                $stmt->execute([$booking_id]);
                $room_status = $stmt->fetchColumn();
                if ($room_status !== 'available') {
                    throw new Exception("Cannot confirm booking: Room is not available.");
                }
            }
            $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $stmt->execute([$status, $booking_id]);
            // Update room status if booking is confirmed or cancelled
            if ($status === 'confirmed') {
                $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = (SELECT room_id FROM bookings WHERE id = ?)");
                $stmt->execute([$booking_id]);
            } elseif ($status === 'cancelled') {
                $stmt = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = (SELECT room_id FROM bookings WHERE id = ?)");
                $stmt->execute([$booking_id]);
            }
            $success = "Booking updated successfully!";
        } catch (Exception $e) {
            $error = "Error updating booking: " . $e->getMessage();
        }
    }
}

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
                <li><a href="create_branch.php" class="sidebar__link" onclick="showSection('create-branch')"><i class="ri-building-line"></i><span>Create Branch</span></a></li>
                <li><a href="create_user.php" class="sidebar__link" onclick="showSection('create-user')"><i class="ri-user-add-line"></i><span>Create User</span></a></li>
                <li><a href="create_room.php" class="sidebar__link" onclick="showSection('create-room')"><i class="ri-home-line"></i><span>Add Room</span></a></li>
                <li><a href="manage_rooms.php" class="sidebar__link"><i class="ri-home-gear-line"></i><span>Manage Rooms</span></a></li>
                <li><a href="create_room_type.php" class="sidebar__link" onclick="showSection('create-room-type')"><i class="ri-home-2-line"></i><span>Manage Room Types</span></a></li>
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
            <h1 class="section__header">Manage Bookings</h1>
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

        <section id="manage-bookings" class="dashboard__section active">
            <h2 class="section__subheader">Individual Bookings</h2>
            <div class="table__container">
                <table class="data__table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Branch</th>
                            <th>Room</th>
                            <th>Check-In</th>
                            <th>Check-Out</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?php echo $booking['id']; ?></td>
                                <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['branch_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['room_number'] . ' (' . ($booking['room_type_name'] ?? 'Not Assigned') . ')'); ?></td>
                                <td><?php echo $booking['check_in']; ?></td>
                                <td><?php echo $booking['check_out']; ?></td>
                                <td><?php echo htmlspecialchars($booking['status']); ?></td>
                                <td>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <select name="status" required>
                                            <option value="pending" <?php echo $booking['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo $booking['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="cancelled" <?php echo $booking['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_booking" class="btn btn--primary">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($bookings)): ?>
                            <tr>
                                <td colspan="8">No individual bookings found.</td>
                            </tr>
                        <?php endif; ?>
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
.btn--primary {
    background: #2563eb;
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}
.btn--primary:hover {
    background: #1d4ed8;
}
</style>

<script>
function showSection(sectionId) {
    const sections = document.querySelectorAll('.dashboard__section');
    sections.forEach(section => section.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
}

document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.toggle('collapsed');
});
</script>

<?php include 'templates/footer.php'; ?>