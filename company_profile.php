<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to travel companies only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'travel_company') {
    header("Location: login.php");
    exit();
}

// Include database connection and functions
require_once 'db_connect.php';
include_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Fetch existing company profile
try {
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, c.company_name, c.contact_phone
        FROM users u
        LEFT JOIN company_profiles c ON u.id = c.user_id
        WHERE u.id = ? AND u.role = 'travel_company'
    ");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$profile) {
        $errors[] = "User profile not found.";
    }
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $company_name = filter_input(INPUT_POST, 'company_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $contact_email = filter_input(INPUT_POST, 'contact_email', FILTER_SANITIZE_EMAIL);
    $contact_phone = filter_input(INPUT_POST, 'contact_phone', FILTER_SANITIZE_SPECIAL_CHARS);

    // Validation
    if (!$company_name || strlen(trim($company_name)) < 2) {
        $errors[] = "Company name must be at least 2 characters.";
    }
    if (!$contact_email || !filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please provide a valid contact email.";
    }
    if (!$contact_phone || !preg_match('/^\+?[\d\s\-]{7,15}$/', $contact_phone)) {
        $errors[] = "Please provide a valid phone number (7-15 digits, optional + or -).";
    }

    // Update profile if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Check if company profile exists
            $stmt = $pdo->prepare("SELECT id FROM company_profiles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $company_exists = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($company_exists) {
                // Update existing profile
                $stmt = $pdo->prepare("
                    UPDATE company_profiles
                    SET company_name = ?, contact_phone = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$company_name, $contact_phone, $user_id]);
            } else {
                // Insert new profile
                $stmt = $pdo->prepare("
                    INSERT INTO company_profiles (user_id, company_name, contact_phone)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$user_id, $company_name, $contact_phone]);
            }

            // Update user's email in users table
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$contact_email, $user_id]);

            $pdo->commit();
            $success = "Company profile updated successfully!";
            // Refresh profile data
            $stmt = $pdo->prepare("
                SELECT u.name, u.email, c.company_name, c.contact_phone
                FROM users u
                LEFT JOIN company_profiles c ON u.id = c.user_id
                WHERE u.id = ?
            ");
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Failed to update profile: " . $e->getMessage();
        }
    }
}

include 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Profile - Travel Company Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>
<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Travel Company Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li><a href="travel_company_dashboard.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'travel_company_dashboard.php' ? 'active' : ''; ?>"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li><a href="make_travel_reservations.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'make_travel_reservations.php' ? 'active' : ''; ?>"><i class="ri-calendar-check-line"></i><span>Make Reservations</span></a></li>
                <li><a href="travel_manage_reservations.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'travel_manage_reservations.php' ? 'active' : ''; ?>"><i class="ri-calendar-line"></i><span>Manage Reservations</span></a></li>
                <li><a href="travel_additional_services.php" class="sidebar__link" <?php echo basename($_SERVER['PHP_SELF']) === 'travel_additional_services.php' ? 'active' : ''; ?>">  <i class="ri-service-line"></i><span>Additional Services</span></a></li>
                <li><a href="travel_billing_payments.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'travel_billing_payments.php' ? 'active' : ''; ?>"><i class="ri-wallet-line"></i><span>Billing & Payments</span></a></li>
                <li><a href="company_profile.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'company_profile.php' ? 'active' : ''; ?>"><i class="ri-settings-3-line"></i><span>Profile</span></a></li>
                <li><a href="logout.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'logout.php' ? 'active' : ''; ?>"><i class="ri-logout-box-line"></i><span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Company Profile</h1>
            <div class="user__info">
                <span><?php echo htmlspecialchars($profile['email'] ?? 'Unknown'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section class="profile__section">
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" class="profile__form">
                <div class="form__group">
                    <label for="company_name">Company Name</label>
                    <input type="text" id="company_name" name="company_name" value="<?php echo htmlspecialchars($profile['company_name'] ?? ''); ?>" required>
                    <small class="form__help">Enter the official name of your company.</small>
                </div>

                <div class="form__group">
                    <label for="contact_email">Contact Email</label>
                    <input type="email" id="contact_email" name="contact_email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" required>
                    <small class="form__help">This email will be used for all communications and updates.</small>
                </div>

                <div class="form__group">
                    <label for="contact_phone">Contact Phone</label>
                    <input type="text" id="contact_phone" name="contact_phone" value="<?php echo htmlspecialchars($profile['contact_phone'] ?? ''); ?>" required>
                    <small class="form__help">Provide a valid phone number (e.g., +1234567890).</small>
                </div>

                <button type="submit" class="submit__button">Update Profile</button>
            </form>
        </section>
    </main>
</div>

<style>
/* General Dashboard Styles */
.dashboard__container {
    display: flex;
    min-height: 100vh;
    background: #f3f4f6;
}

.dashboard__content {
    flex: 1;
    padding: 2rem;
}

.section__header {
    font-size: 2rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
}

.profile__section {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.profile__form {
    display: grid;
    gap: 1.5rem;
}

.form__group {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.form__group label {
    font-size: 1rem;
    font-weight: 500;
    color: #1f2937;
}

.form__group input {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    width: 100%;
    box-sizing: border-box;
}

.form__group input:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form__help {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.submit__button {
    background: #3b82f6;
    color: white;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease;
}

.submit__button:hover {
    background: #2563eb;
}

.error, .success {
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.error {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
}

.success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.error ul {
    margin: 0;
    padding-left: 1.5rem;
}

/* Sidebar Styles */
.sidebar {
    width: 250px;
    background: #1f2937;
    color: white;
    transition: width 0.3s ease;
}

.sidebar.collapsed {
    width: 80px;
}

.sidebar.collapsed .sidebar__title,
.sidebar.collapsed .sidebar__link span {
    display: none;
}

.sidebar__header {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.sidebar__logo {
    width: 40px;
    height: 40px;
}

.sidebar__title {
    font-size: 1.25rem;
    font-weight: 600;
}

.sidebar__toggle {
    background: none;
    border: none;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
}

.sidebar__nav {
    padding: 1rem;
}

.sidebar__links {
    list-style: none;
    padding: 0;
}

.sidebar__link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 0.5rem;
    transition: background 0.2s ease;
}

.sidebar__link:hover,
.sidebar__link.active {
    background: #3b82f6;
}

.sidebar__link i {
    font-size: 1.25rem;
}

/* Header Styles */
.dashboard__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.user__info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.user__avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}

/* Responsive Design */
@media (max-width: 768px) {
    .sidebar {
        width: 80px;
    }
    
    .sidebar__title,
    .sidebar__link span {
        display: none;
    }
    
    .dashboard__content {
        padding: 1rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');

    // Sidebar toggle
    sidebarToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line');
    });

    // Form validation
    const form = document.querySelector('.profile__form');
    form.addEventListener('submit', (e) => {
        const errors = [];
        const companyName = document.getElementById('company_name').value;
        const contactEmail = document.getElementById('contact_email').value;
        const contactPhone = document.getElementById('contact_phone').value;

        if (!companyName || companyName.trim().length < 2) {
            errors.push('Company name must be at least 2 characters.');
        }
        if (!contactEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(contactEmail)) {
            errors.push('Please provide a valid contact email.');
        }
        if (!contactPhone || !/^\+?[\d\s\-]{7,15}$/.test(contactPhone)) {
            errors.push('Please provide a valid phone number (7-15 digits, optional + or -).');
        }

        if (errors.length > 0) {
            e.preventDefault();
            alert('Please fix the following errors:\n\n' + errors.join('\n'));
        }
    });

    // Reset form after success
    const successMessage = document.querySelector('.success');
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.transition = 'opacity 0.5s ease-out';
            successMessage.style.opacity = '0';
            setTimeout(() => successMessage.remove(), 500);
        }, 5000);
    }
});
</script>
</body>
</html>