<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to clerks only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'clerk') {
    header("Location: login.php");
    exit();
}

// Include database connection and TCPDF
require_once 'db_connect.php';
include_once 'includes/functions.php';
require_once 'vendor/tcpdf/tcpdf.php'; // Adjust path based on your setup

// Get clerk's branch_id
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ? AND role = 'clerk'");
$stmt->execute([$user_id]);
$branch_id = $stmt->fetch(PDO::FETCH_ASSOC)['branch_id'] ?? 0;

if (!$branch_id) {
    $db_error = "No branch assigned to this clerk.";
}

// Handle billing actions (mark as paid or overdue)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['billing_id'])) {
    $billing_id = (int)$_POST['billing_id'];
    $action = $_POST['action'];

    try {
        // Fetch billing details
        $stmt = $pdo->prepare("SELECT b.*, r.user_id, r.remaining_balance AS reservation_balance 
                              FROM billings b 
                              JOIN reservations r ON b.reservation_id = r.id 
                              JOIN branches br ON r.hotel_id = br.id 
                              WHERE b.id = ? AND br.id = ?");
        $stmt->execute([$billing_id, $branch_id]);
        $billing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($billing) {
            if ($action === 'mark_paid') {
                // Start transaction
                $pdo->beginTransaction();

                // Update billing status to paid
                $stmt = $pdo->prepare("UPDATE billings SET status = 'paid' WHERE id = ?");
                $stmt->execute([$billing_id]);

                // Update reservation remaining_balance
                $new_reservation_balance = $billing['reservation_balance'] - $billing['additional_fee'];
                $stmt = $pdo->prepare("UPDATE reservations SET remaining_balance = ?, payment_status = 'paid' WHERE id = ?");
                $stmt->execute([$new_reservation_balance, $billing['reservation_id']]);

                // Commit transaction
                $pdo->commit();
                $success_message = "Billing marked as paid successfully.";
            } elseif ($action === 'mark_overdue') {
                // Update billing status to overdue
                $stmt = $pdo->prepare("UPDATE billings SET status = 'overdue' WHERE id = ?");
                $stmt->execute([$billing_id]);
                $success_message = "Billing marked as overdue successfully.";
            }
        } else {
            $error_message = "Invalid billing record or not associated with your branch.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle adding additional services
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service']) && isset($_POST['reservation_id']) && isset($_POST['service_type']) && isset($_POST['additional_fee'])) {
    $reservation_id = (int)$_POST['reservation_id'];
    $service_type = $_POST['service_type'];
    $additional_fee = (float)$_POST['additional_fee'];
    $user_id = (int)$_POST['user_id'];

    try {
        // Validate service_type
        $valid_service_types = ['restaurant', 'room_service', 'laundry', 'telephone', 'key_issuing', 'club_facility'];
        if (!in_array($service_type, $valid_service_types)) {
            throw new Exception("Invalid service type.");
        }

        // Validate additional_fee
        if ($additional_fee <= 0) {
            throw new Exception("Additional fee must be greater than 0.");
        }

        // Fetch reservation details
        $stmt = $pdo->prepare("SELECT remaining_balance, user_id FROM reservations WHERE id = ? AND hotel_id IN (SELECT id FROM branches WHERE id = ?)");
        $stmt->execute([$reservation_id, $branch_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reservation) {
            throw new Exception("Invalid reservation ID or not associated with your branch.");
        }

        // Start transaction
        $pdo->beginTransaction();

        // Insert into billings table
        $stmt = $pdo->prepare("INSERT INTO billings (reservation_id, user_id, service_type, additional_fee, status, created_at) 
                              VALUES (?, ?, ?, ?, 'pending', NOW())");
        $stmt->execute([
            $reservation_id,
            $reservation['user_id'],
            $service_type,
            $additional_fee
        ]);

        // Update reservation remaining_balance
        $stmt = $pdo->prepare("UPDATE reservations SET remaining_balance = remaining_balance + ? WHERE id = ?");
        $stmt->execute([$additional_fee, $reservation_id]);

        // Commit transaction
        $pdo->commit();
        $success_message = "Additional service added successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Handle generating final bill for a user
$selected_user_id = null;
$user_bill = [];
$total_additional_fee = 0;
$total_remaining = 0;
$full_total = 0;
$processed_reservations = [];
$user_details = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_bill']) && isset($_POST['user_id'])) {
    $selected_user_id = (int)$_POST['user_id'];

    try {
        // Fetch user details, including company name for travel companies
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.role, cp.company_name 
            FROM users u 
            LEFT JOIN company_profiles cp ON u.id = cp.user_id 
            WHERE u.id = ? AND u.role IN ('customer', 'travel_company')
        ");
        $stmt->execute([$selected_user_id]);
        $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_details) {
            // Fetch all billing records and reservation details (exclude group bookings)
            $stmt = $pdo->prepare("
                SELECT b.id AS billing_id, b.reservation_id, b.service_type, b.additional_fee, b.created_at,
                       r.id AS reservation_id, r.room_type, r.check_in_date, r.check_out_date, r.remaining_balance
                FROM billings b
                JOIN reservations r ON b.reservation_id = r.id
                JOIN branches br ON r.hotel_id = br.id
                WHERE b.user_id = ? AND br.id = ?
                ORDER BY b.created_at DESC
            ");
            $stmt->execute([$selected_user_id, $branch_id]);
            $user_bill = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calculate totals
            foreach ($user_bill as $bill) {
                $total_additional_fee += $bill['additional_fee'];
                if (!in_array($bill['reservation_id'], $processed_reservations)) {
                    $total_remaining += $bill['remaining_balance'];
                    $processed_reservations[] = $bill['reservation_id'];
                }
            }
            $full_total = $total_additional_fee + $total_remaining;
        } else {
            $error_message = "Invalid user selected or user is not a customer or travel company.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Handle PDF download with TCPDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_bill']) && isset($_POST['user_id'])) {
    $selected_user_id = (int)$_POST['user_id'];

    try {
        // Fetch user details
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.role, cp.company_name 
            FROM users u 
            LEFT JOIN company_profiles cp ON u.id = cp.user_id 
            WHERE u.id = ? AND u.role IN ('customer', 'travel_company')
        ");
        $stmt->execute([$selected_user_id]);
        $user_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_details) {
            // Fetch billing records
            $stmt = $pdo->prepare("
                SELECT b.id AS billing_id, b.reservation_id, b.service_type, b.additional_fee, b.created_at,
                       r.id AS reservation_id, r.room_type, r.check_in_date, r.check_out_date, r.remaining_balance
                FROM billings b
                JOIN reservations r ON b.reservation_id = r.id
                JOIN branches br ON r.hotel_id = br.id
                WHERE b.user_id = ? AND br.id = ?
                ORDER BY b.created_at DESC
            ");
            $stmt->execute([$selected_user_id, $branch_id]);
            $user_bill = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $total_additional_fee = 0;
            $total_remaining = 0;
            $processed_reservations = [];
            foreach ($user_bill as $bill) {
                $total_additional_fee += $bill['additional_fee'];
                if (!in_array($bill['reservation_id'], $processed_reservations)) {
                    $total_remaining += $bill['remaining_balance'];
                    $processed_reservations[] = $bill['reservation_id'];
                }
            }
            $full_total = $total_additional_fee + $total_remaining;

            // Create new PDF document
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

            // Set document information
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor('Velo Resort Galle Fort');
            $pdf->SetTitle('Invoice');
            $pdf->SetSubject('User Invoice');
            $pdf->SetKeywords('Invoice, Billing, Hotel');

            // Set header data
            $pdf->SetHeaderData('', 0, 'Velo Resort Galle Fort', 'Invoice', array(0,64,255), array(0,64,128));
            $pdf->setFooterData(array(0,64,0), array(0,64,128));

            // Set header and footer fonts
            $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

            // Set margins
            $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

            // Set auto page breaks
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

            // Add a page
            $pdf->AddPage();

            // Set font
            $pdf->SetFont('helvetica', '', 12);

            // Add bill content
            $html = '<h1 style="text-align: center;">Invoice</h1>';
            $html .= '<p><strong>Generated on:</strong> ' . date('Y-m-d H:i:s') . '</p>';
            $html .= '<p><strong>Name:</strong> ' . htmlspecialchars($user_details['name']) . '</p>';
            if ($user_details['role'] === 'travel_company' && $user_details['company_name']) {
                $html .= '<p><strong>Company:</strong> ' . htmlspecialchars($user_details['company_name']) . '</p>';
            }
            $html .= '<p><strong>Email:</strong> ' . htmlspecialchars($user_details['email']) . '</p>';
            $html .= '<p><strong>Branch:</strong> Velo Resort Galle Fort</p>';

            $html .= '<table border="1" cellpadding="5" cellspacing="0">';
            $html .= '<tr style="background-color: #f0f0f0;">';
            $html .= '<th>Reservation ID</th><th>Room Type</th><th>Check-In</th><th>Check-Out</th><th>Service Type</th><th>Additional Fee</th><th>Remaining Balance</th>';
            $html .= '</tr>';

            $displayed_reservations = [];
            foreach ($user_bill as $bill) {
                $is_new_reservation = !in_array($bill['reservation_id'], $displayed_reservations);
                if ($is_new_reservation) {
                    $displayed_reservations[] = $bill['reservation_id'];
                }
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($bill['reservation_id']) . '</td>';
                $html .= '<td>' . htmlspecialchars($bill['room_type']) . '</td>';
                $html .= '<td>' . htmlspecialchars($bill['check_in_date']) . '</td>';
                $html .= '<td>' . htmlspecialchars($bill['check_out_date']) . '</td>';
                $html .= '<td>' . htmlspecialchars($bill['service_type']) . '</td>';
                $html .= '<td>' . number_format($bill['additional_fee'], 2) . '</td>';
                $html .= '<td>' . ($is_new_reservation ? number_format($bill['remaining_balance'], 2) : '-') . '</td>';
                $html .= '</tr>';
            }

            $html .= '<tr>';
            $html .= '<td colspan="5"><strong>Total Additional Fees</strong></td>';
            $html .= '<td><strong>' . number_format($total_additional_fee, 2) . '</strong></td>';
            $html .= '<td></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td colspan="5"><strong>Total Remaining Balance</strong></td>';
            $html .= '<td></td>';
            $html .= '<td><strong>' . number_format($total_remaining, 2) . '</strong></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td colspan="5"><strong>Full Total</strong></td>';
            $html .= '<td><strong>' . number_format($full_total, 2) . '</strong></td>';
            $html .= '<td></td>';
            $html .= '</tr>';
            $html .= '</table>';

            // Write HTML to PDF
            $pdf->writeHTML($html, true, false, true, false, '');

            // Output PDF
            $pdf->Output('bill_' . $user_details['id'] . '_' . date('Ymd_His') . '.pdf', 'D');
            exit;
        } else {
            $error_message = "Invalid user selected or user is not a customer or travel company.";
        }
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// Fetch all billing records for the clerk's branch
try {
    $stmt = $pdo->prepare("
        SELECT b.*, u.name AS user_name, r.id AS reservation_id 
        FROM billings b 
        JOIN reservations r ON b.reservation_id = r.id 
        JOIN users u ON r.user_id = u.id 
        JOIN branches br ON r.hotel_id = br.id 
        WHERE br.id = ?
    ");
    $stmt->execute([$branch_id]);
    $billings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
    $billings = [];
}

// Fetch confirmed reservations for the clerk's branch
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.user_id, u.name AS user_name, rt.name AS room_type_name 
        FROM reservations r 
        JOIN users u ON r.user_id = u.id 
        JOIN room_types rt ON r.room_type = rt.name 
        JOIN branches b ON r.hotel_id = b.id 
        WHERE b.id = ? AND r.status = 'confirmed'
    ");
    $stmt->execute([$branch_id]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
    $reservations = [];
}

// Fetch users (customers and travel companies) with confirmed reservations
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, u.email, u.role, cp.company_name
        FROM users u
        JOIN reservations r ON u.id = r.user_id
        JOIN branches b ON r.hotel_id = b.id
        LEFT JOIN company_profiles cp ON u.id = cp.user_id
        WHERE b.id = ? AND r.status = 'confirmed' AND u.role IN ('customer', 'travel_company')
    ");
    $stmt->execute([$branch_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $db_error = "Database error: " . $e->getMessage();
    $users = [];
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
                <li><a href="clerk_dashboard.php" class="sidebar__link"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li><a href="manage_reservations.php" class="sidebar__link"><i class="ri-calendar-check-line"></i><span>Manage Reservations</span></a></li>
                <li><a href="manage_check_in_out.php" class="sidebar__link"><i class="ri-logout-box-line"></i><span>Check-In/Out Customers</span></a></li>
                <li><a href="room_availability.php" class="sidebar__link"><i class="ri-home-line"></i><span>Room Availability</span></a></li>
                <li><a href="create_customer.php" class="sidebar__link"><i class="ri-user-add-line"></i><span>Create Customer</span></a></li>
                <li><a href="billing_statements.php" class="sidebar__link active"><i class="ri-wallet-line"></i><span>Billing Statements</span></a></li>
                <li><a href="clerk_settings.php" class="sidebar__link"><i class="ri-settings-3-line"></i><span>Profile</span></a></li>
                <li><a href="logout.php" class="sidebar__link"><i class="ri-logout-box-line"></i><span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Billing Statements</h1>
            <div class="user__info">
                <span>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Clerk'); ?></span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <!-- Generate Final Bill Section -->
        <section id="generate-bill" class="dashboard__section active">
            <h2 class="section__subheader">Generate Final Bill for User</h2>
            <?php if (isset($db_error)): ?>
                <p class="error"><?php echo htmlspecialchars($db_error); ?></p>
            <?php endif; ?>
            <?php if (isset($success_message)): ?>
                <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
            <?php endif; ?>
            <?php if (empty($users)): ?>
                <p>No users with confirmed reservations found.</p>
            <?php else: ?>
                <form id="generate-bill-form" method="POST" action="billing_statements.php">
                    <input type="hidden" name="generate_bill" value="1">
                    <div class="form-group">
                        <label for="user_id">Select User</label>
                        <select name="user_id" id="user_id" required>
                            <option value="">Select a user</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php 
                                    $display_name = $user['role'] === 'travel_company' && $user['company_name'] 
                                        ? htmlspecialchars("{$user['company_name']} ({$user['email']})") 
                                        : htmlspecialchars("{$user['name']} ({$user['email']})"); 
                                    echo $display_name;
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="action-btn approve">Generate Bill</button>
                </form>
            <?php endif; ?>

            <?php if ($selected_user_id && !empty($user_bill) && $user_details): ?>
                <div class="bill__container">
                    <h3>
                        Final Bill for 
                        <?php 
                        echo $user_details['role'] === 'travel_company' && $user_details['company_name'] 
                            ? htmlspecialchars($user_details['company_name']) 
                            : htmlspecialchars($user_details['name']); 
                        ?>
                        (<?php echo htmlspecialchars($user_details['email']); ?>)
                    </h3>
                    <p><strong>Generated on:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                    <p><strong>Branch:</strong> Velo Resort Galle Fort</p>
                    <?php if ($user_details['role'] === 'travel_company' && $user_details['company_name']): ?>
                        <p><strong>Company:</strong> <?php echo htmlspecialchars($user_details['company_name']); ?></p>
                    <?php endif; ?>
                    <form id="download-bill-form" method="POST" action="billing_statements.php">
                        <input type="hidden" name="download_bill" value="1">
                        <input type="hidden" name="user_id" value="<?php echo $selected_user_id; ?>"><br>
                        <button type="submit" class="action-btn approve">Download Bill as PDF</button><br><br>
                    </form>
                    <table>
                        <thead>
                            <tr>
                                <th>Reservation ID</th>
                                <th>Room Type</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Service Type</th>
                                <th>Additional Fee</th>
                                <th>Remaining Balance</th>
                                <th>Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $displayed_reservations = [];
                            foreach ($user_bill as $bill): 
                                $is_new_reservation = !in_array($bill['reservation_id'], $displayed_reservations);
                                if ($is_new_reservation) {
                                    $displayed_reservations[] = $bill['reservation_id'];
                                }
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($bill['reservation_id']); ?></td>
                                    <td><?php echo htmlspecialchars($bill['room_type']); ?></td>
                                    <td><?php echo htmlspecialchars($bill['check_in_date']); ?></td>
                                    <td><?php echo htmlspecialchars($bill['check_out_date']); ?></td>
                                    <td><?php echo htmlspecialchars($bill['service_type']); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($bill['additional_fee'], 2)); ?></td>
                                    <td>
                                        <?php 
                                        if ($is_new_reservation) {
                                            echo htmlspecialchars(number_format($bill['remaining_balance'], 2));
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($bill['created_at']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5"><strong>Total Additional Fees</strong></td>
                                <td><strong><?php echo htmlspecialchars(number_format($total_additional_fee, 2)); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                            <tr>
                                <td colspan="5"><strong>Total Remaining Balance</strong></td>
                                <td></td>
                                <td><strong><?php echo htmlspecialchars(number_format($total_remaining, 2)); ?></strong></td>
                            </tr>
                            <tr>
                                <td colspan="5"><strong>Full Total</strong></td>
                                <td><strong><?php echo htmlspecialchars(number_format($full_total, 2)); ?></strong></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php elseif ($selected_user_id): ?>
                <p>No billing records found for this user.</p>
            <?php endif; ?>
        </section></br></br></br>

        <!-- Add Additional Service Section -->
        <section id="add-service" class="dashboard__section active">
            <h2 class="section__subheader">Add Additional Service</h2>
            <?php if (empty($reservations)): ?>
                <p>No confirmed reservations found.</p>
            <?php else: ?>
                <form id="additional-service-form" method="POST" action="billing_statements.php">
                    <input type="hidden" name="add_service" value="1">
                    <div class="form-group">
                        <label for="reservation_id">Select Reservation</label>
                        <select name="reservation_id" id="reservation_id" required>
                            <option value="">Select a reservation</option>
                            <?php foreach ($reservations as $reservation): ?>
                                <option value="<?php echo $reservation['id']; ?>" data-user-id="<?php echo $reservation['user_id']; ?>">
                                    <?php echo htmlspecialchars("ID: {$reservation['id']} - {$reservation['user_name']} ({$reservation['room_type_name']})"); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="user_id" id="user_id">
                    </div>
                    <div class="form-group">
                        <label for="service_type">Service Type</label>
                        <select name="service_type" id="service_type" required>
                            <option value="">Select a service</option>
                            <option value="restaurant">Restaurant</option>
                            <option value="room_service">Room Service</option>
                            <option value="laundry">Laundry</option>
                            <option value="telephone">Telephone</option>
                            <option value="key_issuing">Key Issuing</option>
                            <option value="club_facility">Club Facility</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="additional_fee">Additional Fee ($)</label>
                        <input type="number" name="additional_fee" id="additional_fee" step="0.01" min="0.01" required>
                    </div>
                    <button type="submit" class="action-btn approve">Add Service</button>
                </form>
            <?php endif; ?>
        </section>
    </main>
</div>

<style>
/* Inherit styles from clerk_dashboard.php */
.overview__cards, .dashboard__container, .sidebar, .dashboard__content, .dashboard__header, .section__header, .user__info, .user__avatar, .sidebar__header, .sidebar__logo, .sidebar__title, .sidebar__toggle, .sidebar__nav, .sidebar__links, .sidebar__link, .card__icon, .card__content, .section__subheader, .dashboard__section.active, .error {
    /* Inherit existing styles */
}

/* Table and form styles */
.billings__table, .bill__container {
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

tfoot td {
    font-weight: 600;
}

.form-group {
    margin-bottom: 1rem;
}

.form-group label {
    display block;
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.form-group select, .form-group input {
    width: 100%;
    padding: 0.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 1rem;
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

.error {
    color: #ef4444;
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
    // Sidebar toggle
    document.getElementById('sidebar-toggle')?.addEventListener('click', function() {
        document.getElementById('sidebar').classList.toggle('collapsed');
    });

    // Additional service form handling
    const reservationSelect = document.getElementById('reservation_id');
    const userIdInput = document.getElementById('user_id');
    const additionalFeeInput = document.getElementById('additional_fee');

    if (reservationSelect && userIdInput) {
        reservationSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            userIdInput.value = selectedOption.getAttribute('data-user-id') || '';
        });
    }

    if (additionalFeeInput) {
        document.getElementById('additional-service-form').addEventListener('submit', function(e) {
            if (parse

Float(additionalFeeInput.value) <= 0) {
                e.preventDefault();
                alert('Additional fee must be greater than 0.');
            }
        });
    }
});
</script>

<?php include 'templates/footer.php'; ?>