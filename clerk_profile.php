<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to managers only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

// Get manager's branch_id
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ? AND role = 'manager'");
$stmt->execute([$user_id]);
$branch_id = $stmt->fetch(PDO::FETCH_ASSOC)['branch_id'] ?? 0;

if (!$branch_id) {
    $db_error = "No branch assigned to this manager.";
}

// Fetch clerks for the branch
$clerks = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, email, created_at FROM users WHERE role = 'clerk' AND branch_id = ?");
    $stmt->execute([$branch_id]);
    $clerks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = isset($db_error) ? $db_error . " | Clerk fetch error: " . $e->getMessage() : "Clerk fetch error: " . $e->getMessage();
}

// Handle actions
$action = $_GET['action'] ?? 'list';
$clerk_id = filter_var($_GET['clerk_id'] ?? 0, FILTER_VALIDATE_INT);
$clerk = [];
$success_message = '';
$error_message = '';

if ($action === 'edit' && $clerk_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id = ? AND role = 'clerk' AND branch_id = ?");
        $stmt->execute([$clerk_id, $branch_id]);
        $clerk = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$clerk) {
            $error_message = "Clerk not found or does not belong to your branch.";
            $action = 'list';
        }
    } catch (PDOException $e) {
        $error_message = "Error fetching clerk: " . $e->getMessage();
        $action = 'list';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $action = $_POST['action'] ?? 'add';
    $clerk_id = filter_var($_POST['clerk_id'] ?? 0, FILTER_VALIDATE_INT);

    // Validation
    if (empty($name) || empty($email)) {
        $error_message = "Name and email are required.";
    } elseif ($action === 'add' && empty($password)) {
        $error_message = "Password is required for new clerks.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } elseif ($action === 'edit' && !$clerk_id) {
        $error_message = "Invalid clerk ID for editing.";
        $action = 'list';
    } elseif ($action === 'delete' && !$clerk_id) {
        $error_message = "Invalid clerk ID for deletion.";
        $action = 'list';
    } else {
        try {
            if ($action === 'add') {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error_message = "Email already exists.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, role, name, branch_id, created_at) VALUES (?, ?, 'clerk', ?, ?, NOW())");
                    $stmt->execute([$email, $hashed_password, $name, $branch_id]);
                    $success_message = "Clerk added successfully.";
                    $action = 'list';
                    // Refresh clerks list
                    $stmt = $pdo->prepare("SELECT id, name, email, created_at FROM users WHERE role = 'clerk' AND branch_id = ?");
                    $stmt->execute([$branch_id]);
                    $clerks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } elseif ($action === 'edit' && $clerk_id) {
                // Check if email exists for another user
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $clerk_id]);
                if ($stmt->fetch()) {
                    $error_message = "Email already exists.";
                } else {
                    $sql = "UPDATE users SET name = ?, email = ? WHERE id = ? AND role = 'clerk' AND branch_id = ?";
                    $params = [$name, $email, $clerk_id, $branch_id];
                    if (!empty($password)) {
                        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                        $sql = "UPDATE users SET name = ?, email = ?, password = ? WHERE id = ? AND role = 'clerk' AND branch_id = ?";
                        $params = [$name, $email, $hashed_password, $clerk_id, $branch_id];
                    }
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $success_message = "Clerk updated successfully.";
                    $action = 'list';
                    // Refresh clerks list
                    $stmt = $pdo->prepare("SELECT id, name, email, created_at FROM users WHERE role = 'clerk' AND branch_id = ?");
                    $stmt->execute([$branch_id]);
                    $clerks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } elseif ($action === 'delete' && $clerk_id) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'clerk' AND branch_id = ?");
                    $stmt->execute([$clerk_id, $branch_id]);
                    if ($stmt->rowCount() === 0) {
                        $error_message = "Clerk not found or could not be deleted.";
                    } else {
                        $success_message = "Clerk deleted successfully.";
                    }
                    $action = 'list';
                    // Refresh clerks list
                    $stmt = $pdo->prepare("SELECT id, name, email, created_at FROM users WHERE role = 'clerk' AND branch_id = ?");
                    $stmt->execute([$branch_id]);
                    $clerks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $error_message = "Error deleting clerk: " . $e->getMessage();
                }
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

include 'templates/header.php';
?>

<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Manager Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li>
                    <a href="manager_dashboard.php" class="sidebar__link">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="occupancy_reports.php" class="sidebar__link">
                        <i class="ri-bar-chart-line"></i>
                        <span>Occupancy Reports</span>
                    </a>
                </li>
                <li>
                    <a href="financial_reports.php" class="sidebar__link">
                        <i class="ri-money-dollar-circle-line"></i>
                        <span>Financial Reports</span>
                    </a>
                </li>
                <li>
                    <a href="projected_occupancy.php" class="sidebar__link">
                        <i class="ri-calendar-2-line"></i>
                        <span>Projected Occupancy</span>
                    </a>
                </li>
                <li>
                    <a href="daily_reports.php" class="sidebar__link">
                        <i class="ri-file-chart-line"></i>
                        <span>Daily Reports</span>
                    </a>
                </li>
                <li>
                    <a href="billing_summary.php" class="sidebar__link">
                        <i class="ri-wallet-line"></i>
                        <span>Billing Summary</span>
                    </a>
                </li>
                <li>
                    <a href="manage_branch_bookings.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Manage Bookings</span>
                    </a>
                </li>
                <li>
                    <a href="clerk_profile.php" class="sidebar__link active">
                        <i class="ri-user-settings-line"></i>
                        <span>Manage Clerks</span>
                    </a>
                </li>
                     <li>
                    <a href="manager_settings.php" class="sidebar__link">
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
            <h1 class="section__header">Manage Clerks</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Manager'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section id="clerk-management" class="dashboard__section active">
            <?php if (isset($db_error)): ?>
                <p class="error"><?php echo htmlspecialchars($db_error); ?></p>
            <?php endif; ?>
            <?php if ($success_message): ?>
                <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
            <?php endif; ?>

            <?php if ($action === 'add' || $action === 'edit'): ?>
                <h2 class="section__subheader"><?php echo $action === 'add' ? 'Add New Clerk' : 'Edit Clerk'; ?></h2>
                <?php if ($error_message): ?>
                    <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
                <?php endif; ?>
                <form method="POST" class="clerk__form">
                    <input type="hidden" name="action" value="<?php echo $action; ?>">
                    <input type="hidden" name="clerk_id" value="<?php echo $clerk_id; ?>">
                    <div class="form__group">
                        <label for="name">Name:</label>
                        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($clerk['name'] ?? ''); ?>" required>
                    </div>
                    <div class="form__group">
                        <label for="email">Email:</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($clerk['email'] ?? ''); ?>" required>
                    </div>
                    <div class="form__group">
                        <label for="password">Password: <?php echo $action === 'edit' ? '(Leave blank to keep unchanged)' : ''; ?></label>
                        <input type="password" name="password" id="password" <?php echo $action === 'add' ? 'required' : ''; ?>>
                    </div>
                    <button type="submit" class="btn btn--primary"><?php echo $action === 'add' ? 'Add Clerk' : 'Update Clerk'; ?></button>
                    <a href="clerk_profile.php" class="btn btn--secondary">Cancel</a>
                </form>
            <?php else: ?>
                <h2 class="section__subheader">Clerk List</h2>
                <div class="table__container">
                    <table class="clerks__table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clerks)): ?>
                                <tr>
                                    <td colspan="5">No clerks found for this branch.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clerks as $clerk): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($clerk['id']); ?></td>
                                        <td><?php echo htmlspecialchars($clerk['name']); ?></td>
                                        <td><?php echo htmlspecialchars($clerk['email']); ?></td>
                                        <td><?php echo htmlspecialchars($clerk['created_at']); ?></td>
                                        <td>
                                            <a href="clerk_profile.php?action=edit&clerk_id=<?php echo $clerk['id']; ?>" class="btn btn--primary">Edit</a>
                                            <form method="POST" action="clerk_profile.php" style="display: inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="clerk_id" value="<?php echo $clerk['id']; ?>">
                                                <button type="submit" class="btn btn--danger" onclick="return confirm('Are you sure you want to delete this clerk?');">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <a href="clerk_profile.php?action=add" class="btn btn--primary" style="margin-top: 1rem;">Add New Clerk</a>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<style>
.clerk__form {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    max-width: 500px;
    margin-top: 1rem;
}

.form__group {
    margin-bottom: 1rem;
}

.form__group label {
    display: block;
    font-size: 0.9rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.form__group input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    font-size: 0.9rem;
}

.table__container {
    overflow-x: auto;
}

.clerks__table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.clerks__table th,
.clerks__table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.clerks__table th {
    background: #f9fafb;
    font-weight: 600;
    color: #1f2937;
}

.clerks__table td {
    color: #4b5563;
}

.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.9rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    margin-right: 0.5rem;
}

.btn--primary {
    background: #3b82f6;
    color: white;
    border: none;
}

.btn--primary:hover {
    background: #2563eb;
}

.btn--secondary {
    background: #6b7280;
    color: white;
    border: none;
}

.btn--secondary:hover {
    background: #4b5563;
}

.btn--danger {
    background: #ef4444;
    color: white;
    border: none;
}

.btn--danger:hover {
    background: #dc2626;
}

.error {
    color: red;
    margin-bottom: 1rem;
}

.success {
    color: green;
    margin-bottom: 1rem;
}

.section__subheader {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.dashboard__section.active {
    display: block;
}

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