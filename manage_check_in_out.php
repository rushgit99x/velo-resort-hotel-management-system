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

// Handle check-in/check-out actions and check-out date updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['booking_id'])) {
    $booking_id = (int)$_POST['booking_id'];
    $action = $_POST['action'];

    try {
        // Fetch booking details with room type price and reservation info
        $stmt = $pdo->prepare("SELECT b.*, r.id as reservation_id, r.remaining_balance as reservation_balance, 
                              rt.base_price, rt.name as room_type_name, u.name as user_name, 
                              r.number_of_rooms, r.discount_percentage
                              FROM bookings b
                              JOIN rooms rm ON b.room_id = rm.id
                              JOIN room_types rt ON rm.room_type_id = rt.id
                              JOIN users u ON b.user_id = u.id
                              JOIN reservations r ON r.user_id = b.user_id AND r.check_in_date = b.check_in AND r.check_out_date = b.check_out
                              WHERE b.id = ? AND b.branch_id = ? AND b.status = 'confirmed'");
        $stmt->execute([$booking_id, $branch_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($booking) {
            if ($action === 'check_in') {
                // Check if check-in/out record exists
                $stmt = $pdo->prepare("SELECT id, check_in_time, check_out_time FROM check_in_out WHERE booking_id = ?");
                $stmt->execute([$booking_id]);
                $check_in_out = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$check_in_out || !$check_in_out['check_in_time']) {
                    // Record check-in time
                    if (!$check_in_out) {
                        $stmt = $pdo->prepare("INSERT INTO check_in_out (booking_id, check_in_time, created_at) 
                                              VALUES (?, NOW(), NOW())");
                        $stmt->execute([$booking_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE check_in_out SET check_in_time = NOW() WHERE booking_id = ?");
                        $stmt->execute([$booking_id]);
                    }
                    $success_message = "Check-in recorded successfully for {$booking['user_name']}.";
                } else {
                    $error_message = "Check-in already recorded.";
                }
            } elseif ($action === 'check_out') {
                // Check if check-in/out record exists
                $stmt = $pdo->prepare("SELECT id, check_in_time, check_out_time FROM check_in_out WHERE booking_id = ?");
                $stmt->execute([$booking_id]);
                $check_in_out = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($check_in_out && $check_in_out['check_in_time'] && !$check_in_out['check_out_time']) {
                    // Start transaction
                    $pdo->beginTransaction();

                    // Record check-out time
                    $stmt = $pdo->prepare("UPDATE check_in_out SET check_out_time = NOW() WHERE booking_id = ?");
                    $stmt->execute([$booking_id]);

                    // Check for late checkout
                    $check_out_date = new DateTime($booking['check_out']);
                    $current_date = new DateTime();
                    $is_late = $current_date->format('Y-m-d') > $check_out_date->format('Y-m-d');

                    if ($is_late) {
                        // Calculate additional night's charge
                        $additional_night_cost = $booking['base_price'] * $booking['number_of_rooms'] * (1 - $booking['discount_percentage'] / 100);

                        // Fetch existing billing record
                        $stmt = $pdo->prepare("SELECT id, amount, remaining_balance FROM billings WHERE reservation_id = ?");
                        $stmt->execute([$booking['reservation_id']]);
                        $billing = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($billing) {
                            // Update billing record
                            $new_amount = $billing['amount'] + $additional_night_cost;
                            $new_remaining_balance = $billing['remaining_balance'] + $additional_night_cost;
                            $stmt = $pdo->prepare("UPDATE billings SET amount = ?, remaining_balance = ? WHERE id = ?");
                            $stmt->execute([$new_amount, $new_remaining_balance, $billing['id']]);

                            // Update reservation remaining_balance
                            $stmt = $pdo->prepare("UPDATE reservations SET remaining_balance = ? WHERE id = ?");
                            $stmt->execute([$new_remaining_balance, $booking['reservation_id']]);
                        } else {
                            // Create new billing record
                            $stmt = $pdo->prepare("INSERT INTO billings (user_id, reservation_id, amount, reservation_fee, additional_fee, remaining_balance, status, due_date) 
                                                  VALUES (?, ?, ?, 10.00, 0.00, ?, 'pending', ?)");
                            $stmt->execute([
                                $booking['user_id'],
                                $booking['reservation_id'],
                                $additional_night_cost,
                                $additional_night_cost,
                                $booking['check_out']
                            ]);

                            // Update reservation remaining_balance
                            $stmt = $pdo->prepare("UPDATE reservations SET remaining_balance = ? WHERE id = ?");
                            $stmt->execute([$additional_night_cost, $booking['reservation_id']]);
                        }
                    }

                    // Update room status to available
                    $stmt = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
                    $stmt->execute([$booking['room_id']]);

                    // Commit transaction
                    $pdo->commit();
                    $success_message = "Check-out recorded successfully for {$booking['user_name']}." . ($is_late ? " Additional night's charge applied." : "");
                } else {
                    $error_message = "Invalid check-out action or already checked out.";
                }
            } elseif ($action === 'update_checkout') {
                $new_check_out_date = $_POST['new_check_out_date'] ?? '';
                $check_in_date = new DateTime($booking['check_in']);
                $new_check_out = new DateTime($new_check_out_date);

                // Validate new check-out date
                if (!$new_check_out_date || $new_check_out <= $check_in_date) {
                    $error_message = "New check-out date must be after the check-in date.";
                } else {
                    // Start transaction
                    $pdo->beginTransaction();

                    // Update bookings table
                    $stmt = $pdo->prepare("UPDATE bookings SET check_out = ? WHERE id = ?");
                    $stmt->execute([$new_check_out_date, $booking_id]);

                    // Update reservations table
                    $stmt = $pdo->prepare("UPDATE reservations SET check_out_date = ? WHERE id = ?");
                    $stmt->execute([$new_check_out_date, $booking['reservation_id']]);

                    // Calculate additional cost for extended stay
                    $original_check_out = new DateTime($booking['check_out']);
                    $interval = $original_check_out->diff($new_check_out);
                    $additional_days = $interval->days;
                    if ($additional_days > 0 && $new_check_out > $original_check_out) {
                        $additional_cost = $booking['base_price'] * $booking['number_of_rooms'] * $additional_days * (1 - $booking['discount_percentage'] / 100);

                        // Fetch existing billing record
                        $stmt = $pdo->prepare("SELECT id, amount, remaining_balance FROM billings WHERE reservation_id = ?");
                        $stmt->execute([$booking['reservation_id']]);
                        $billing = $stmt->fetch(PDO::FETCH_ASSOC);

                        if ($billing) {
                            // Update billing record
                            $new_amount = $billing['amount'] + $additional_cost;
                            $new_remaining_balance = $billing['remaining_balance'] + $additional_cost;
                            $stmt = $pdo->prepare("UPDATE billings SET amount = ?, remaining_balance = ? WHERE id = ?");
                            $stmt->execute([$new_amount, $new_remaining_balance, $billing['id']]);

                            // Update reservation remaining_balance
                            $stmt = $pdo->prepare("UPDATE reservations SET remaining_balance = ? WHERE id = ?");
                            $stmt->execute([$new_remaining_balance, $booking['reservation_id']]);
                        } else {
                            // Create new billing record
                            $stmt = $pdo->prepare("INSERT INTO billings (user_id, reservation_id, amount, reservation_fee, additional_fee, remaining_balance, status, due_date) 
                                                  VALUES (?, ?, ?, 10.00, 0.00, ?, 'pending', ?)");
                            $stmt->execute([
                                $booking['user_id'],
                                $booking['reservation_id'],
                                $additional_cost,
                                $additional_cost,
                                $new_check_out_date
                            ]);

                            // Update reservation remaining_balance
                            $stmt = $pdo->prepare("UPDATE reservations SET remaining_balance = ? WHERE id = ?");
                            $stmt->execute([$additional_cost, $booking['reservation_id']]);
                        }
                    }

                    // Commit transaction
                    $pdo->commit();
                    $success_message = "Check-out date updated successfully for {$booking['user_name']} to {$new_check_out_date}." . ($additional_days > 0 ? " Additional charges applied." : "");
                }
            } else {
                $error_message = "Invalid action.";
            }
        } else {
            $error_message = "Invalid or unauthorized booking.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Fetch bookings eligible for check-in (today, confirmed, no check-in time)
try {
    $stmt = $pdo->prepare("SELECT b.id, b.user_id, b.room_id, b.check_in, b.check_out, u.name as user_name, rt.name as room_type_name
                          FROM bookings b
                          JOIN users u ON b.user_id = u.id
                          JOIN rooms rm ON b.room_id = rm.id
                          JOIN room_types rt ON rm.room_type_id = rt.id
                          LEFT JOIN check_in_out cio ON b.id = cio.booking_id
                          WHERE b.branch_id = ? AND b.status = 'confirmed' AND b.check_in = CURDATE() 
                          AND (cio.check_in_time IS NULL OR cio.id IS NULL)");
    $stmt->execute([$branch_id]);
    $check_ins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
    $check_ins = [];
}

// Fetch bookings eligible for check-out (today or overdue, confirmed, checked-in, no check-out time)
try {
    $stmt = $pdo->prepare("SELECT b.id, b.user_id, b.room_id, b.check_in, b.check_out, u.name as user_name, rt.name as room_type_name
                          FROM bookings b
                          JOIN users u ON b.user_id = u.id
                          JOIN rooms rm ON b.room_id = rm.id
                          JOIN room_types rt ON rm.room_type_id = rt.id
                          JOIN check_in_out cio ON b.id = cio.booking_id
                          WHERE b.branch_id = ? AND b.status = 'confirmed' AND b.check_out <= CURDATE() 
                          AND cio.check_in_time IS NOT NULL AND cio.check_out_time IS NULL");
    $stmt->execute([$branch_id]);
    $check_outs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
    $check_outs = [];
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
                    <a href="manage_reservations.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Manage Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="manage_check_in_out.php" class="sidebar__link active">
                        <i class="ri-login-box-line"></i>
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
            <h1 class="section__header">Manage Check-In/Out</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Clerk'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section id="check-in-out" class="dashboard__section active">
            <h2 class="section__subheader">Check-In Management</h2>
            <?php if (isset($db_error)): ?>
                <p class="error"><?php echo htmlspecialchars($db_error); ?></p>
            <?php endif; ?>
            <?php if (isset($success_message)): ?>
                <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <?php if (empty($check_ins)): ?>
                <p>No bookings available for check-in today.</p>
            <?php else: ?>
                <div class="check_in_out__table">
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Customer</th>
                                <th>Room Type</th>
                                <th>Check-In Date</th>
                                <th>Check-Out Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($check_ins as $check_in): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($check_in['id']); ?></td>
                                    <td><?php echo htmlspecialchars($check_in['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($check_in['room_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($check_in['check_in']); ?></td>
                                    <td><?php echo htmlspecialchars($check_in['check_out']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $check_in['id']; ?>">
                                            <button type="submit" name="action" value="check_in" class="action-btn approve">Check-In</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h2 class="section__subheader">Check-Out Management</h2>
            <?php if (empty($check_outs)): ?>
                <p>No bookings available for check-out today or overdue.</p>
            <?php else: ?>
                <div class="check_in_out__table">
                    <table>
                        <thead>
                            <tr>
                                <th>Booking ID</th>
                                <th>Customer</th>
                                <th>Room Type</th>
                                <th>Check-In Date</th>
                                <th>Check-Out Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($check_outs as $check_out): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($check_out['id']); ?></td>
                                    <td><?php echo htmlspecialchars($check_out['user_name']); ?></td>
                                    <td><?php echo htmlspecialchars($check_out['room_type_name']); ?></td>
                                    <td><?php echo htmlspecialchars($check_out['check_in']); ?></td>
                                    <td><?php echo htmlspecialchars($check_out['check_out']); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $check_out['id']; ?>">
                                            <button type="submit" name="action" value="check_out" class="action-btn cancel">Check-Out</button>
                                        </form>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="booking_id" value="<?php echo $check_out['id']; ?>">
                                            <input type="date" name="new_check_out_date" required min="<?php echo date('Y-m-d', strtotime($check_out['check_in'] . ' +1 day')); ?>">
                                            <button type="submit" name="action" value="update_checkout" class="action-btn update">Update Check-Out</button>
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
.dashboard__container, .sidebar, .dashboard__content, .dashboard__header, .section__header, .user__info, .user__avatar, .sidebar__header, .sidebar__logo, .sidebar__title, .sidebar__toggle, .sidebar__nav, .sidebar__links, .sidebar__link, .section__subheader, .dashboard__section.active, .error {
    /* Inherit existing styles */
}

/* Table styles */
.check_in_out__table {
    margin-top: 1.5rem;
    overflow-x: auto;
    margin-bottom: 2rem;
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

.action-btn.update {
    background: #f59e0b;
    color: white;
}

.action-btn.update:hover {
    background: #d97706;
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

/* Input field styling */
input[type="date"] {
    padding: 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 0.9rem;
    margin-right: 0.5rem;
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