<?php
include 'includes/functions.php';
include 'templates/header.php';
checkAuth();

$allowed_roles = ['manager', 'clerk'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: index.php");
    exit;
}

$branch_id = getUserBranch($pdo, $_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_booking'])) {
    $booking_id = $_POST['booking_id'];
    $status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ? AND branch_id = ?");
    $stmt->execute([$status, $booking_id, $branch_id]);
    $success = "Booking updated successfully!";
}
?>
    <h1 class="text-3xl font-bold mb-6">Booking Management</h1>
    <?php if (isset($error)): ?>
        <p class="text-red-500"><?php echo $error; ?></p>
    <?php elseif (isset($success)): ?>
        <p class="text-green-500"><?php echo $success; ?></p>
    <?php endif; ?>

    <div class="mt-8 bg-white p-6 rounded shadow-md">
        <h2 class="text-xl font-bold mb-4">Booking List</h2>
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border p-2">Room</th>
                    <th class="border p-2">Customer</th>
                    <th class="border p-2">Check-In</th>
                    <th class="border p-2">Check-Out</th>
                    <th class="border p-2">Status</th>
                    <th class="border p-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $stmt = $pdo->prepare("SELECT b.*, r.room_number, u.name AS customer_name 
                                      FROM bookings b 
                                      JOIN rooms r ON b.room_id = r.id 
                                      JOIN users u ON b.user_id = u.id 
                                      WHERE b.branch_id = ?");
                $stmt->execute([$branch_id]);
                while ($booking = $stmt->fetch()) {
                    echo "<tr>
                            <td class='border p-2'>{$booking['room_number']}</td>
                            <td class='border p-2'>{$booking['customer_name']}</td>
                            <td class='border p-2'>{$booking['check_in']}</td>
                            <td class='border p-2'>{$booking['check_out']}</td>
                            <td class='border p-2'>{$booking['status']}</td>
                            <td class='border p-2'>
                                <form method='POST' class='inline'>
                                    <input type='hidden' name='booking_id' value='{$booking['id']}'>
                                    <select name='status' class='p-1 border rounded' required>
                                        <option value='pending' " . ($booking['status'] == 'pending' ? 'selected' : '') . ">Pending</option>
                                        <option value='confirmed' " . ($booking['status'] == 'confirmed' ? 'selected' : '') . ">Confirmed</option>
                                        <option value='cancelled' " . ($booking['status'] == 'cancelled' ? 'selected' : '') . ">Cancelled</option>
                                    </select>
                                    <button type='submit' name='update_booking' class='bg-green-500 text-white p-1 rounded hover:bg-green-600'>Update</button>
                                </form>
                            </td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
<?php include 'templates/footer.php'; ?>