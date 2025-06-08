<?php
include_once 'includes/functions.php';
include 'templates/header.php';

// Check if a super admin already exists
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'super_admin'");
$stmt->execute();
$super_admin_count = $stmt->fetchColumn();

if ($super_admin_count > 0) {
    $error = "A super admin already exists. Only one super admin is allowed.";
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if (!validateEmail($email)) {
        $error = "Invalid email format.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'super_admin')");
            $stmt->execute([$name, $email, $password]);
            $success = "Super admin registered successfully! <a href='login.php'>Login here</a>.";
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
            error_log("Super admin registration error: " . $e->getMessage());
        }
    }
}
?>
    <div class="bg-white p-8 rounded shadow-md w-full max-w-md mx-auto">
        <h2 class="text-2xl font-bold mb-6 text-center">Super Admin Registration</h2>
        <?php if (isset($error)): ?>
            <p class="text-red-500"><?php echo htmlspecialchars($error); ?></p>
        <?php elseif (isset($success)): ?>
            <p class="text-green-500"><?php echo $success; ?></p>
        <?php endif; ?>
        <?php if ($super_admin_count == 0): ?>
            <form id="superAdminRegisterForm" method="POST" onsubmit="return validateRegisterForm()">
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
                <button type="submit" class="w-full bg-green-500 text-white p-2 rounded hover:bg-green-600">Register Super Admin</button>
            </form>
        <?php else: ?>
            <p class="text-center">Super admin registration is disabled as an account already exists.</p>
            <p class="text-center"><a href="login.php" class="text-blue-500">Login here</a>.</p>
        <?php endif; ?>
    </div>
<?php include 'templates/footer.php'; ?>