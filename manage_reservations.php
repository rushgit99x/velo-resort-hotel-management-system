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

// Get clerk's branch_id
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ? AND role = 'clerk'");
$stmt->execute([$user_id]);
$branch_id = $stmt->fetch(PDO::FETCH_ASSOC)['branch_id'] ?? 0;

if (!$branch_id) {
    $db_error = "No branch assigned to this clerk.";
}

// Define reservation fee (configurable)
define('RESERVATION_FEE', 10.00);

// Handle reservation actions (approve/cancel)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['reservation_id'])) {
    $reservation_id = (int)$_POST['reservation_id'];
    $action = $_POST['action'];

    try {
        // Fetch reservation details with room type price
        $stmt = $pdo->prepare("SELECT r.*, rt.id as room_type_id, rt.base_price, rt.name as room_type_name 
                              FROM reservations r 
                              JOIN room_types rt ON r.room_type = rt.name 
                              WHERE r.id = ? AND r.status = 'pending'");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reservation) {
            if ($action === 'approve') {
                // Find an available room of the requested type in the clerk's branch
                $stmt = $pdo->prepare("SELECT id FROM rooms WHERE branch_id = ? AND room_type_id = ? AND status = 'available' LIMIT 1");
                $stmt->execute([$branch_id, $reservation['room_type_id']]);
                $room = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($room) {
                    // Calculate room cost: base_price * number_of_rooms * stay duration (in days)
                    $check_in = new DateTime($reservation['check_in_date']);
                    $check_out = new DateTime($reservation['check_out_date']);
                    $stay_duration = $check_in->diff($check_out)->days;
                    $room_cost = $reservation['base_price'] * $reservation['number_of_rooms'] * $stay_duration;

                    // Apply discount if any
                    $discount = $reservation['discount_percentage'] / 100;
                    $room_cost = $room_cost * (1 - $discount);

                    // Calculate total amount including reservation fee
                    $total_amount = $room_cost + RESERVATION_FEE;

                    // Start transaction
                    $pdo->beginTransaction();

                    // Insert into bookings table
                    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, room_id, branch_id, check_in, check_out, status, created_at) 
                                          VALUES (?, ?, ?, ?, ?, 'confirmed', NOW())");
                    $stmt->execute([
                        $reservation['user_id'],
                        $room['id'],
                        $branch_id,
                        $reservation['check_in_date'],
                        $reservation['check_out_date']
                    ]);

                    // Update room status to occupied
                    $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
                    $stmt->execute([$room['id']]);

                    // Insert into billings table with remaining_balance and placeholder for no additional services
                    $stmt = $pdo->prepare("INSERT INTO billings (reservation_id, user_id, service_type, additional_fee, remaining_balance, status, created_at) 
                                          VALUES (?, ?, 'none', 0.00, ?, 'pending', NOW())");
                    $stmt->execute([
                        $reservation['id'],
                        $reservation['user_id'],
                        $total_amount
                    ]);

                    // Check if the user is a travel company
                    $stmt = $pdo->prepare("SELECT role, id FROM users WHERE id = ?");
                    $stmt->execute([$reservation['user_id']]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user['role'] === 'travel_company') {
                        // Fetch company profile to get company_id
                        $stmt = $pdo->prepare("SELECT id FROM company_profiles WHERE user_id = ?");
                        $stmt->execute([$reservation['user_id']]);
                        $company = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($company) {
                            // Calculate due date (30 days from now)
                            $due_date = (new DateTime())->modify('+30 days')->format('Y-m-d');

                            // Insert into invoices table
                            $stmt = $pdo->prepare("INSERT INTO invoices (company_id, amount, status, issued_at, due_date) 
                                                  VALUES (?, ?, 'pending', NOW(), ?)");
                            $stmt->execute([
                                $company['id'],
                                $total_amount,
                                $due_date
                            ]);
                        } else {
                            error_log("No company profile found for travel company user ID: " . $reservation['user_id']);
                        }
                    }

                    // Update reservation status and remaining_balance
                    $stmt = $pdo->prepare("UPDATE reservations SET status = 'confirmed', remaining_balance = ? WHERE id = ?");
                    $stmt->execute([$total_amount, $reservation_id]);

                    // Commit transaction
                    $pdo->commit();
                    $success_message = "Reservation approved, moved to bookings, billing record created" . ($user['role'] === 'travel_company' ? ", and invoice generated." : ".");
                } else {
                    $error_message = "No available rooms of the requested type.";
                }
            } elseif ($action === 'cancel') {
                // Update reservation status to cancelled
                $stmt = $pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$reservation_id]);
                $success_message = "Reservation cancelled successfully.";
            }
        } else {
            $error_message = "Invalid or non-pending reservation.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Fetch all pending reservations for the clerk's branch
try {
    $stmt = $pdo->prepare("SELECT r.*, u.name AS user_name, rt.name AS room_type_name 
                          FROM reservations r 
                          JOIN users u ON r.user_id = u.id 
                          JOIN room_types rt ON r.room_type = rt.name 
                          JOIN branches b ON r.hotel_id = b.id 
                          WHERE b.id = ? AND r.status = 'pending'");
    $stmt->execute([$branch_id]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
    $reservations = [];
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
                    <a href="manage_reservations.php" class="sidebar__link active">
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
                    <a href="create_customer.php" class="sidebar__link">
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
            <h1 class="section__header">Manage Reservations</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Clerk'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section id="reservations" class="dashboard__section active">
            <h2 class="section__subheader">Pending Reservations</h2>
            <?php if (isset($db_error)): ?>
                <p class="error"><?php echo htmlspecialchars($db_error); ?></p>
            <?php endif; ?>
            <?php if (isset($success_message)): ?>
                <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <?php if (empty($reservations)): ?>
                <p>No pending reservations found.</p>
            <?php else: ?>
                <div class="reservations__table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Room Type</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Occupants</th>
                                <th>Rooms</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reservation['id']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['room_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['check_in_date']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['check_out_date']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['occupants']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['number_of_rooms']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                            <button type="submit" name="action" value="approve" class="action-btn approve">Approve</button>
                                            <button type="submit" name="action" value="cancel" class="action-btn cancel">Cancel</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<style>
/* Inherit styles from clerk_dashboard.php */
.overview__cards, .dashboard__container, .sidebar, .dashboard__content, .dashboard__header, .section__header, .user__info, .user__avatar, .sidebar__header, .sidebar__logo, .sidebar__title, .sidebar__toggle, .sidebar__nav, .sidebar__links, .sidebar__link, .card__icon, .card__content, .section__subheader, .dashboard__section.active, .error {
    /* Inherit existing styles */
}

/* Table styles */
.reservations__table {
    margin-top: 1.5rem;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

th, td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

th {
    background: #f9fafb;
    font-weight: 600;
    color: #1f2937;
}

td {
    color: #4b5563;
}

.action-btn {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.9rem;
    margin-right: 0.5rem;
    transition: background 0.2s ease;
}

.action-btn.approve {
    background: #3b82f6;
    color: white;
}

.action-btn.approve:hover {
    background: #2563eb;
}

.action-btn.cancel {
    background: #ef4444;
    color: white;
}

.action-btn.cancel:hover {
    background: #dc2626;
}

.success {
    color: #15803d;
    margin-bottom: 1rem;
}

/* Sidebar active link */
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