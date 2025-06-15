<?php
include 'includes/functions.php';
include 'templates/header.php';
checkAuth();

$allowed_roles = ['super_admin', 'manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: index.php");
    exit;
}

$branch_id = $_SESSION['role'] == 'manager' ? getUserBranch($pdo, $_SESSION['user_id']) : null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_room'])) {
    $room_id = $_POST['room_id'];
    $room_number = sanitize($_POST['room_number']);
    $type = $_POST['type'];
    $price = floatval($_POST['price']);
    $status = $_POST['status'];
    $stmt = $pdo->prepare("UPDATE rooms SET room_number = ?, type = ?, price = ?, status = ? WHERE id = ?");
    $stmt->execute([$room_number, $type, $price, $status, $room_id]);
    $success = "Room updated successfully!";
}
?>
    <h1 class="text-3xl font-bold mb-6">Room Management</h1>
    <?php if (isset($error)): ?>
        <p class="text-red-500"><?php echo $error; ?></p>
    <?php elseif (isset($success)): ?>
        <p class="text-green-500"><?php echo $success; ?></p>
    <?php endif; ?>

    <div class="mt-8 bg-white p-6 rounded shadow-md">
        <h2 class="text-xl font-bold mb-4">Room List</h2>
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border p-2">Room Number</th>
                    <th class="border p-2">Branch</th>
                    <th class="border p-2">Type</th>
                    <th class="border p-2">Price</th>
                    <th class="border p-2">Status</th>
                    <th class="border p-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = "SELECT r.*, b.name AS branch_name FROM rooms r JOIN branches b ON r.branch_id = b.id";
                if ($branch_id) {
                    $query .= " WHERE r.branch_id = ?";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([$branch_id]);
                } else {
                    $stmt = $pdo->query($query);
                }
                while ($room = $stmt->fetch()) {
                    echo "<tr>
                            <td class='border p-2'>{$room['room_number']}</td>
                            <td class='border p-2'>{$room['branch_name']}</td>
                            <td class='border p-2'>{$room['type']}</td>
                            <td class='border p-2'>\${$room['price']}</td>
                            <td class='border p-2'>{$room['status']}</td>
                            <td class='border p-2'>
                                <form method='POST' class='inline'>
                                    <input type='hidden' name='room_id' value='{$room['id']}'>
                                    <input type='text' name='room_number' value='{$room['room_number']}' class='p-1 border rounded' required>
                                    <select name='type' class='p-1 border rounded' required>
                                        <option value='single' " . ($room['type'] == 'single' ? 'selected' : '') . ">Single</option>
                                        <option value='double' " . ($room['type'] == 'double' ? 'selected' : '') . ">Double</option>
                                        <option value='suite' " . ($room['type'] == 'suite' ? 'selected' : '') . ">Suite</option>
                                    </select>
                                    <input type='number' name='price' value='{$room['price']}' step='0.01' class='p-1 border rounded' required>
                                    <select name='status' class='p-1 border rounded' required>
                                        <option value='available' " . ($room['status'] == 'available' ? 'selected' : '') . ">Available</option>
                                        <option value='occupied' " . ($room['status'] == 'occupied' ? 'selected' : '') . ">Occupied</option>
                                        <option value='maintenance' " . ($room['status'] == 'maintenance' ? 'selected' : '') . ">Maintenance</option>
                                    </select>
                                    <button type='submit' name='update_room' class='bg-green-500 text-white p-1 rounded hover:bg-green-600'>Update</button>
                                </form>
                            </td>
                          </tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
<?php include 'templates/footer.php'; ?>