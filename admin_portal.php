<?php
include_once 'includes/functions.php';
include 'templates/header.php';
checkAuth('super_admin');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_branch'])) {
        $branch_name = sanitize($_POST['branch_name']);
        $location = sanitize($_POST['location']);
        $stmt = $pdo->prepare("INSERT INTO branches (name, location) VALUES (?, ?)");
        $stmt->execute([$branch_name, $location]);
        $success = "Branch created successfully!";
    } elseif (isset($_POST['create_user'])) {
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $branch_id = $_POST['branch_id'];

        if ($role !== 'manager' && $role !== 'clerk') {
            $error = "Invalid role selected.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, branch_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $email, $password, $role, $branch_id]);
            $success = ucfirst($role) . " created successfully!";
        }
    } elseif (isset($_POST['create_room'])) {
        $branch_id = $_POST['branch_id'];
        $room_number = sanitize($_POST['room_number']);
        $type = $_POST['type'];
        $price = floatval($_POST['price']);
        $stmt = $pdo->prepare("INSERT INTO rooms (branch_id, room_number, type, price) VALUES (?, ?, ?, ?)");
        $stmt->execute([$branch_id, $room_number, $type, $price]);
        $success = "Room created successfully!";
    }
}
?>
    <h1 class="text-3xl font-bold mb-6">Super Admin Portal</h1>
    <?php if (isset($error)): ?>
        <p class="text-red-500"><?php echo $error; ?></p>
    <?php elseif (isset($success)): ?>
        <p class="text-green-500"><?php echo $success; ?></p>
    <?php endif; ?>

    <!-- Create Branch -->
    <div class="mt-8 bg-white p-6 rounded shadow-md">
        <h2 class="text-xl font-bold mb-4">Create Branch</h2>
        <form method="POST">
            <div class="mb-4">
                <label for="branch_name" class="block text-gray-700">Branch Name</label>
                <input type="text" id="branch_name" name="branch_name" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label for="location" class="block text-gray-700">Location</label>
                <input type="text" id="location" name="location" class="w-full p-2 border rounded" required>
            </div>
            <button type="submit" name="create_branch" class="bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Create Branch</button>
        </form>
    </div>

    <!-- Create Manager/Clerk -->
    <div class="mt-8 bg-white p-6 rounded shadow-md">
        <h2 class="text-xl font-bold mb-4">Create Manager/Clerk</h2>
        <form method="POST">
            <div class="mb-4">
                <label for="name" class="block text-gray-700">Name</label>
                <input type="text" id="name" name="name" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label for="email" class="block text-gray-700">Email</label>
                <input type="email" id="email" name="email" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-gray-700">Password</label>
                <input type="password" id="password" name="password" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label for="role" class="block text-gray-700">Role</label>
                <select id="role" name="role" class="w-full p-2 border rounded" required>
                    <option value="manager">Manager</option>
                    <option value="clerk">Clerk</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="branch_id" class="block text-gray-700">Branch</label>
                <select id="branch_id" name="branch_id" class="w-full p-2 border rounded" required>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM branches");
                    while ($branch = $stmt->fetch()) {
                        echo "<option value='{$branch['id']}'>{$branch['name']} ({$branch['location']})</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit" name="create_user" class="bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Create User</button>
        </form>
    </div>

    <!-- Create Room -->
    <div class="mt-8 bg-white p-6 rounded shadow-md">
        <h2 class="text-xl font-bold mb-4">Add Room</h2>
        <form method="POST">
            <div class="mb-4">
                <label for="branch_id" class="block text-gray-700">Branch</label>
                <select id="branch_id" name="branch_id" class="w-full p-2 border rounded" required>
                    <?php
                    $stmt = $pdo->query("SELECT * FROM branches");
                    while ($branch = $stmt->fetch()) {
                        echo "<option value='{$branch['id']}'>{$branch['name']} ({$branch['location']})</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-4">
                <label for="room_number" class="block text-gray-700">Room Number</label>
                <input type="text" id="room_number" name="room_number" class="w-full p-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label for="type" class="block text-gray-700">Room Type</label>
                <select id="type" name="type" class="w-full p-2 border rounded" required>
                    <option value="single">Single</option>
                    <option value="double">Double</option>
                    <option value="suite">Suite</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="price" class="block text-gray-700">Price per Night</label>
                <input type="number" id="price" name="price" step="0.01" class="w-full p-2 border rounded" required>
            </div>
            <button type="submit" name="create_room" class="bg-blue-500 text-white p-2 rounded hover:bg-blue-600">Add Room</button>
        </form>
    </div>
<?php include 'templates/footer.php'; ?>