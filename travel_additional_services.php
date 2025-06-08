<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to travel_company only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'travel_company') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';
include_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

// Define available services with charges and descriptions, aligned with billings table service_type enum
$available_services = [
    'restaurant' => ['name' => 'Restaurant', 'description' => 'Group dining arrangements at on-site restaurants', 'charge' => 25.00],
    'room_service' => ['name' => 'Room Service', 'description' => 'Food and beverage delivery for group reservations', 'charge' => 15.00, 'note' => '+ menu price'],
    'laundry' => ['name' => 'Laundry', 'description' => 'Bulk laundry services for travel groups', 'charge' => 10.00],
    'telephone' => ['name' => 'Telephone Service', 'description' => 'Dedicated communication lines for group coordination', 'charge' => 0.50, 'note' => 'per minute'],
    'key_issuing' => ['name' => 'Group Key Issuing', 'description' => 'Automated key issuance for group check-ins', 'charge' => 5.00],
    'club_facility' => ['name' => 'Club Facility', 'description' => 'Access to exclusive amenities for groups (pool, gym, lounge)', 'charge' => 50.00]
];

// Handle service addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_service') {
    $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
    $service_type = filter_input(INPUT_POST, 'service_type', FILTER_SANITIZE_SPECIAL_CHARS);
    $additional_fee = filter_input(INPUT_POST, 'additional_fee', FILTER_VALIDATE_FLOAT);

    // Validate inputs
    if (!$reservation_id) {
        $errors[] = "Invalid reservation selected.";
    }
    if (!array_key_exists($service_type, $available_services)) {
        $errors[] = "Invalid service type selected.";
    }
    if ($additional_fee === false || $additional_fee < 0) {
        $errors[] = "Invalid additional fee amount.";
    }

    // Verify reservation belongs to user and is active
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, status
                FROM reservations
                WHERE id = ? AND user_id = ? AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$reservation_id, $user_id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                $errors[] = "Reservation not found or not eligible for additional services.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // Insert service into billings table
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                INSERT INTO billings (reservation_id, user_id, service_type, additional_fee, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([
                $reservation_id,
                $user_id,
                htmlspecialchars($service_type),
                $additional_fee
            ]);
            $pdo->commit();
            $success = "Service '{$available_services[$service_type]['name']}' added successfully for Reservation ID: $reservation_id. Base Amount: $$additional_fee.";
            if (isset($available_services[$service_type]['note'])) {
                $success .= " Note: Additional charges may apply ({$available_services[$service_type]['note']}).";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Failed to add service: " . $e->getMessage();
        }
    }
}

// Fetch travel company reservations (only pending or confirmed)
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.hotel_id, r.room_type, r.check_in_date, r.check_out_date, r.status, b.name as branch_name, b.location
        FROM reservations r
        JOIN branches b ON r.hotel_id = b.id
        WHERE r.user_id = ? AND r.status IN ('pending', 'confirmed')
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $reservations = [];
}

// Fetch billing details
try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.reservation_id, b.service_type, b.additional_fee, b.status, b.created_at,
               r.room_type, r.check_in_date, r.check_out_date, br.name as branch_name, br.location
        FROM billings b
        JOIN reservations r ON b.reservation_id = r.id
        JOIN branches br ON r.hotel_id = br.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $billings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $billings = [];
}

// Get travel company details for header
try {
    $stmt = $pdo->prepare("
        SELECT u.name, u.email, cp.company_name 
        FROM users u 
        LEFT JOIN company_profiles cp ON u.id = cp.user_id 
        WHERE u.id = ? AND u.role = 'travel_company'
    ");
    $stmt->execute([$user_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    $company_name = $company['company_name'] ?? $company['name'] ?? 'Travel Company';
    $company_email = $company['email'] ?? 'Unknown';
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $company_name = 'Travel Company';
    $company_email = 'Unknown';
}

include 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Additional Services - Travel Company Dashboard</title>
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
                <li>
                    <a href="travel_company_dashboard.php" class="sidebar__link">
                        <i class="ri-dashboard-line"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="make_travel_reservations.php" class="sidebar__link">
                        <i class="ri-calendar-check-line"></i>
                        <span>Make Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="travel_manage_reservations.php" class="sidebar__link">
                        <i class="ri-calendar-line"></i>
                        <span>Manage Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="travel_additional_services.php" class="sidebar__link active">
                        <i class="ri-service-line"></i>
                        <span>Additional Services</span>
                    </a>
                </li>
                <!-- <li>
                    <a href="billing_payments.php" class="sidebar__link">
                        <i class="ri-wallet-line"></i>
                        <span>Billing & Payments</span>
                    </a>
                </li> -->
                <li><a href="travel_billing_payments.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'travel_billing_payments.php' ? 'active' : ''; ?>"><i class="ri-wallet-line"></i><span>Billing & Payments</span></a></li>
                <li>
                    <a href="company_profile.php" class="sidebar__link">
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
            <h1 class="section__header">Manage Additional Services</h1>
            <div class="user__info">
                <span><?php echo htmlspecialchars($company_email); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section class="services__section">
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

            <!-- Available Services Table -->
            <h2 class="section__subheader">Available Services</h2>
            <div class="services__table">
                <table>
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Description</th>
                            <th>Charge</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($available_services as $key => $service): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($service['name']); ?></td>
                                <td><?php echo htmlspecialchars($service['description']); ?></td>
                                <td>
                                    $<?php echo number_format($service['charge'], 2); ?>
                                    <?php echo isset($service['note']) ? htmlspecialchars($service['note']) : ''; ?>
                                </td>
                                <td>
                                    <button class="action__button add__button" onclick="showAddServiceForm('<?php echo $key; ?>', '<?php echo $service['charge']; ?>')">Add</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add Service Form (Hidden by default) -->
            <div id="add_service_form" style="display: none;">
                <h2 class="section__subheader">Add Additional Service</h2>
                <form method="POST" class="services__form">
                    <input type="hidden" name="action" value="add_service">
                    <input type="hidden" id="service_type" name="service_type">
                    <input type="hidden" id="additional_fee" name="additional_fee">
                    <div class="form__group">
                        <label for="reservation_id">Select Reservation</label>
                        <select id="reservation_id" name="reservation_id" required>
                            <option value="">Select a reservation</option>
                            <?php foreach ($reservations as $reservation): ?>
                                <option value="<?php echo $reservation['id']; ?>">
                                    <?php echo htmlspecialchars("ID: {$reservation['id']} - {$reservation['branch_name']} ({$reservation['location']}) - {$reservation['room_type']} ({$reservation['check_in_date']} to {$reservation['check_out_date']})"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form__help">Only pending or confirmed reservations are eligible.</small>
                    </div>
                    <button type="submit" class="submit__button">Add Service</button>
                    <button type="button" class="cancel__button" onclick="hideAddServiceForm()">Cancel</button>
                </form>
            </div>

            <!-- Billing Details -->
            <?php if (empty($billings)): ?>
                <p>No additional services added yet.</p>
            <?php else: ?>
                <h2 class="section__subheader">Your Billing Details</h2>
                <div class="billings__table">
                    <table>
                        <thead>
                            <tr>
                                <th>Billing ID</th>
                                <th>Reservation ID</th>
                                <th>Branch</th>
                                <th>Room Type</th>
                                <th>Service Type</th>
                                <th>Additional Fee</th>
                                <th>Status</th>
                                <th>Date Added</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($billings as $billing): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($billing['id']); ?></td>
                                    <td><?php echo htmlspecialchars($billing['reservation_id']); ?></td>
                                    <td><?php echo htmlspecialchars($billing['branch_name'] . ' - ' . $billing['location']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($billing['room_type'])); ?></td>
                                    <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $billing['service_type']))); ?></td>
                                    <td>$<?php echo number_format($billing['additional_fee'], 2); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($billing['status'])); ?></td>
                                    <td><?php echo htmlspecialchars($billing['created_at']); ?></td>
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

.section__subheader {
    font-size: 1.5rem;
    font-weight: 600;
    color: #1f2937;
    margin-bottom: 1rem;
}

.services__section {
    max-width: 1250px;
    margin: 0 auto;
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.services__form {
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

.form__group input,
.form__group select {
    padding: 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 1rem;
    width: 100%;
    box-sizing: border-box;
}

.form__group input:focus,
.form__group select:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form__help {
    font-size: 0.875rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.submit__button, .action__button, .cancel__button {
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.2s ease;
}

.submit__button {
    background: #3b82f6;
    color: white;
}

.submit__button:hover {
    background: #2563eb;
}

.action__button {
    margin-right: 0.5rem;
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
}

.add__button {
    background: #10b981;
    color: white;
}

.add__button:hover {
    background: #059669;
}

.cancel__button {
    background: #6b7280;
    color: white;
}

.cancel__button:hover {
    background: #4b5563;
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

.services__table, .billings__table {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    background: #f9fafb;
    border-radius: 8px;
    overflow: hidden;
}

th, td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

th {
    background: #1f2937;
    color: white;
    font-weight: 600;
    font-size: 0.9rem;
}

td {
    font-size: 0.9rem;
    color: #1f2937;
}

tr:hover {
    background: #f1f5f9;
}

td:last-child {
    white-space: nowrap;
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
    
    .services__table, .billings__table {
        font-size: 0.8rem;
    }
    
    th, td {
        padding: 0.5rem;
    }
    
    .action__button {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');

    // Toggle sidebar
    sidebarToggle?.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line');
    });

    // Show add service form
    window.showAddServiceForm = function(serviceType, fee) {
        document.getElementById('add_service_form').style.display = 'block';
        document.getElementById('service_type').value = serviceType;
        document.getElementById('additional_fee').value = parseFloat(fee).toFixed(2);
        window.scrollTo({ top: document.getElementById('add_service_form').offsetTop, behavior: 'smooth' });
    };

    // Hide add service form
    window.hideAddServiceForm = function() {
        document.getElementById('add_service_form').style.display = 'none';
        document.getElementById('service_type').value = '';
        document.getElementById('additional_fee').value = '';
        document.getElementById('reservation_id').value = '';
    };

    // Form validation
    const form = document.querySelector('.services__form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const errors = [];
            const reservationId = document.getElementById('reservation_id').value;
            const serviceType = document.getElementById('service_type').value;
            const additionalFee = parseFloat(document.getElementById('additional_fee').value);

            if (!reservationId) {
                errors.push('Please select a reservation.');
            }
            if (!serviceType) {
                errors.push('Please select a service type.');
            }
            if (!additionalFee || additionalFee < 0) {
                errors.push('Invalid additional fee amount.');
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
            }
        });
    }

    // Auto-hide success message and reset form
    const successMessage = document.querySelector('.success');
    if (successMessage) {
        setTimeout(() => {
            hideAddServiceForm();
            const resetNotification = document.createElement('div');
            resetNotification.innerHTML = '<small style="color: #059669; font-style: italic;">Form has been reset</small>';
            resetNotification.style.cssText = 'margin-top: 10px; padding: 5px; background: #ecfdf5; border-radius: 4px; border-left: 3px solid #10b981;';
            successMessage.appendChild(resetNotification);
            setTimeout(() => resetNotification.remove(), 3000);
            successMessage.style.transition = 'opacity 0.5s ease-out';
            successMessage.style.opacity = '0';
            setTimeout(() => successMessage.remove(), 500);
        }, 5000);
    }
});
</script>

<?php include 'templates/footer.php'; ?>
</body>
</html>