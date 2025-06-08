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

// Set timezone for time-based restriction
date_default_timezone_set('Asia/Kolkata');
$current_hour = (int)date('H');
$is_without_credit_card_disabled = ($current_hour >= 19 || $current_hour < 0); // 7 PM to 11:59 PM

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $branch_id = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
    $room_type = filter_input(INPUT_POST, 'room_type', FILTER_SANITIZE_SPECIAL_CHARS);
    $occupants = filter_input(INPUT_POST, 'occupants', FILTER_VALIDATE_INT);
    $number_of_rooms = filter_input(INPUT_POST, 'number_of_rooms', FILTER_VALIDATE_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS);
    $card_number = filter_input(INPUT_POST, 'card_number', FILTER_SANITIZE_SPECIAL_CHARS);
    $card_expiry = filter_input(INPUT_POST, 'card_expiry', FILTER_SANITIZE_SPECIAL_CHARS);
    $card_cvc = filter_input(INPUT_POST, 'card_cvc', FILTER_SANITIZE_SPECIAL_CHARS);
    $cardholder_name = filter_input(INPUT_POST, 'cardholder_name', FILTER_SANITIZE_SPECIAL_CHARS);

    // Handle date ranges
    $date_ranges = [];
    if (isset($_POST['check_in_date']) && is_array($_POST['check_in_date']) && isset($_POST['check_out_date']) && is_array($_POST['check_out_date'])) {
        foreach ($_POST['check_in_date'] as $index => $check_in_date) {
            $check_out_date = $_POST['check_out_date'][$index] ?? '';
            $check_in_date = filter_var($check_in_date, FILTER_SANITIZE_SPECIAL_CHARS);
            $check_out_date = filter_var($check_out_date, FILTER_SANITIZE_SPECIAL_CHARS);
            if ($check_in_date && $check_out_date) {
                $date_ranges[] = [
                    'check_in_date' => $check_in_date,
                    'check_out_date' => $check_out_date,
                    'index' => $index
                ];
            }
        }
    }

    // Validate inputs
    if (!$branch_id) {
        $errors[] = "Please select a valid branch.";
    }
    if (!in_array($room_type, ['single', 'double', 'suite'])) {
        $errors[] = "Please select a valid room type.";
    }
    if (empty($date_ranges)) {
        $errors[] = "Please provide at least one valid date range. Ensure check-in and check-out dates are filled.";
    } else {
        foreach ($date_ranges as $range) {
            $index = $range['index'] + 1;
            $check_in = DateTime::createFromFormat('Y-m-d', $range['check_in_date']);
            $check_out = DateTime::createFromFormat('Y-m-d', $range['check_out_date']);
            if (!$check_in || $check_in->format('Y-m-d') !== $range['check_in_date']) {
                $errors[] = "Invalid check-in date format for range $index. Use YYYY-MM-DD (e.g., 2025-06-01).";
            }
            if (!$check_out || $check_out->format('Y-m-d') !== $range['check_out_date']) {
                $errors[] = "Invalid check-out date format for range $index. Use YYYY-MM-DD (e.g., 2025-06-05).";
            }
            if ($check_in && $check_out) {
                $today = new DateTime('2025-06-01');
                $today->setTime(0, 0, 0);
                if ($check_in < $today) {
                    $errors[] = "Check-in date for range $index cannot be before today (2025-06-01).";
                }
                if ($check_out <= $check_in) {
                    $errors[] = "Check-out date for range $index must be after the check-in date.";
                }
            }
        }
    }
    if (!$occupants || $occupants < 1) {
        $errors[] = "Please provide a valid number of occupants (minimum 1).";
    }
    if (!$number_of_rooms || $number_of_rooms < 1) {
        $errors[] = "Please provide a valid number of rooms (minimum 1).";
    } elseif ($number_of_rooms > 10) {
        $errors[] = "Cannot reserve more than 10 rooms at once.";
    }
    if (!$payment_method || !in_array($payment_method, ['credit_card', 'without_credit_card'])) {
        $errors[] = "Please select a valid payment method.";
    }
    if ($payment_method === 'without_credit_card' && $is_without_credit_card_disabled) {
        $errors[] = "Cannot select 'Pay at Checkout' between 7 PM and 11:59 PM.";
    }

    // Credit card validation
    if ($payment_method === 'credit_card') {
        if (!$cardholder_name || strlen(trim($cardholder_name)) < 2) {
            $errors[] = "Cardholder name must be at least 2 characters.";
        } elseif (!preg_match('/^[a-zA-Z\s\-\.\']+$/', $cardholder_name)) {
            $errors[] = "Cardholder name contains invalid characters.";
        }
        if (!$card_number) {
            $errors[] = "Card number is required.";
        } else {
            $clean_card_number = preg_replace('/[\s\-]/', '', $card_number);
            if (!preg_match('/^\d{13,19}$/', $clean_card_number)) {
                $errors[] = "Card number must be 13–19 digits.";
            } elseif (!validateCardNumberLuhn($clean_card_number)) {
                $errors[] = "Invalid card number (fails Luhn check).";
            } else {
                $card_type = getCardType($clean_card_number);
                if (!$card_type) {
                    $errors[] = "Unsupported card type.";
                }
            }
        }
        if (!$card_expiry) {
            $errors[] = "Expiry date is required.";
        } elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_expiry)) {
            $errors[] = "Expiry date must be in MM/YY format.";
        } else {
            list($month, $year) = explode('/', $card_expiry);
            $current_year = date('y');
            $current_month = date('m');
            if ($year < $current_year || ($year == $current_year && $month < $current_month)) {
                $errors[] = "Card has expired.";
            }
            if ($year > ($current_year + 10)) {
                $errors[] = "Expiry date too far in the future.";
            }
        }
        if (!$card_cvc) {
            $errors[] = "CVC is required.";
        } elseif (!preg_match('/^\d{3,4}$/', $card_cvc)) {
            $errors[] = "CVC must be 3–4 digits.";
        } else {
            $card_type = isset($clean_card_number) ? getCardType($clean_card_number) : '';
            if ($card_type === 'amex' && strlen($card_cvc) !== 4) {
                $errors[] = "Amex requires a 4-digit CVC.";
            } elseif ($card_type !== 'amex' && strlen($card_cvc) !== 3) {
                $errors[] = "CVC must be 3 digits for non-Amex cards.";
            }
        }
    }

    // Check room availability
    if (empty($errors)) {
        foreach ($date_ranges as $range) {
            try {
                $stmt = $pdo->prepare("
                    SELECT rt.id
                    FROM room_types rt
                    JOIN rooms r ON r.room_type_id = rt.id
                    WHERE r.branch_id = :branch_id AND rt.name = :room_type AND r.status = 'available'
                    AND NOT EXISTS (
                        SELECT 1 FROM bookings b
                        WHERE b.room_id = r.id
                        AND b.status != 'cancelled'
                        AND (:check_in_date <= b.check_out AND :check_out_date >= b.check_in)
                    )
                    LIMIT :limit
                ");
                $stmt->bindValue(':branch_id', $branch_id, PDO::PARAM_INT);
                $stmt->bindValue(':room_type', ucfirst($room_type), PDO::PARAM_STR);
                $stmt->bindValue(':check_in_date', $range['check_in_date'], PDO::PARAM_STR);
                $stmt->bindValue(':check_out_date', $range['check_out_date'], PDO::PARAM_STR);
                $stmt->bindValue(':limit', (int)$number_of_rooms, PDO::PARAM_INT);
                $stmt->execute();
                $available_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($available_rooms) < $number_of_rooms) {
                    $errors[] = "Not enough rooms available for range " . ($range['index'] + 1) . ".";
                }
            } catch (PDOException $e) {
                $errors[] = "Database error for range " . ($range['index'] + 1) . ": " . $e->getMessage();
            }
        }
    }

    // Process reservations
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $reservation_ids = [];
            $total_reservation_fee = 0;
            $total_remaining_balance = 0;
            $total_discount_amount = 0;
            $discount_details = [];

            // Get base price
            $stmt = $pdo->prepare("SELECT base_price FROM room_types WHERE name = ?");
            $stmt->execute([ucfirst($room_type)]);
            $base_price = $stmt->fetch(PDO::FETCH_ASSOC)['base_price'] ?? 100.00;

            // Set reservation fee
            $reservation_fee_per_room = match ($room_type) {
                'single' => 30.00,
                'double' => 50.00,
                'suite' => 70.00,
                default => 0.00,
            };
            $reservation_fee_amount = $reservation_fee_per_room * $number_of_rooms;

            foreach ($date_ranges as $range) {
                $days = (strtotime($range['check_out_date']) - strtotime($range['check_in_date'])) / (60 * 60 * 24);

                // Calculate discount for 3+ rooms
                $discount_percentage = 0;
                if ($number_of_rooms >= 3) {
                    if ($days >= 60) {
                        $discount_percentage = 25;
                    } elseif ($days >= 28) {
                        $discount_percentage = 20;
                    } elseif ($days >= 14) {
                        $discount_percentage = 15;
                    } elseif ($days >= 7) {
                        $discount_percentage = 10;
                    } elseif ($days >= 3) {
                        $discount_percentage = 5;
                    }
                }

                $base_amount = $base_price * $days * $number_of_rooms;
                $discount_amount = $base_amount * ($discount_percentage / 100);
                $remaining_balance = $base_amount - $discount_amount;

                // Store discount details for success message
                if ($discount_percentage > 0) {
                    $discount_details[] = "Range " . ($range['index'] + 1) . " ($days days): {$discount_percentage}% discount, saved $" . number_format($discount_amount, 2);
                }

                // Insert reservation
                $payment_status = $payment_method === 'credit_card' ? 'paid' : 'unpaid';
                $stmt = $pdo->prepare("
                    INSERT INTO reservations (user_id, hotel_id, room_type, check_in_date, check_out_date, occupants, number_of_rooms, status, payment_status, discount_percentage, remaining_balance, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())
                ");
                $stmt->execute([$user_id, $branch_id, htmlspecialchars($room_type), htmlspecialchars($range['check_in_date']), htmlspecialchars($range['check_out_date']), $occupants, $number_of_rooms, $payment_status, $discount_percentage, $remaining_balance]);
                $reservation_ids[] = $pdo->lastInsertId();

                $total_reservation_fee += $reservation_fee_amount;
                $total_remaining_balance += $remaining_balance;
                $total_discount_amount += $discount_amount;
            }

            // Insert payment (reservation fee only for credit card)
            if ($payment_method === 'credit_card') {
                $card_last_four = substr($clean_card_number ?? '', -4);
                foreach ($reservation_ids as $reservation_id) {
                    $stmt = $pdo->prepare("
                        INSERT INTO payments (user_id, reservation_id, amount, payment_method, card_last_four, cardholder_name, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())
                    ");
                    $stmt->execute([$user_id, $reservation_id, $reservation_fee_amount, htmlspecialchars($payment_method), $card_last_four, htmlspecialchars($cardholder_name ?? '')]);
                }
            } else {
                // For 'without_credit_card', insert payment with 'pending_payment'
                foreach ($reservation_ids as $reservation_id) {
                    $stmt = $pdo->prepare("
                        INSERT INTO payments (user_id, reservation_id, amount, payment_method, status, created_at)
                        VALUES (?, ?, ?, ?, 'pending', NOW())
                    ");
                    $stmt->execute([$user_id, $reservation_id, $reservation_fee_amount, 'pending_payment']);
                }
            }

            $pdo->commit();
            $success = "Reservations created successfully! Reservation IDs: " . implode(', ', $reservation_ids) . ".";
            if ($payment_method === 'credit_card') {
                $success .= " Total Reservation Fee Charged: $" . number_format($total_reservation_fee, 2) . " (via credit card).";
            } else {
                $success .= " Total Reservation Fee: $" . number_format($total_reservation_fee, 2) . " (due at checkout).";
            }
            $success .= " Total Remaining Balance (due at checkout): $" . number_format($total_remaining_balance, 2) . ".";
            if ($total_discount_amount > 0) {
                $success .= " Discounts Applied: " . implode('; ', $discount_details) . ". Total saved: $" . number_format($total_discount_amount, 2) . ".";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Failed to create reservations: " . $e->getMessage();
        }
    }
}

// Helper functions for card validation
function validateCardNumberLuhn($number) {
    $sum = 0;
    $alternate = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $digit = intval($number[$i]);
        if ($alternate) {
            $digit *= 2;
            if ($digit > 9) {
                $digit = ($digit % 10) + 1;
            }
        }
        $sum += $digit;
        $alternate = !$alternate;
    }
    return ($sum % 10 == 0);
}

function getCardType($number) {
    $patterns = [
        'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
        'mastercard' => '/^5[1-5][0-9]{14}$/',
        'amex' => '/^3[47][0-9]{13}$/',
        'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/'
    ];
    foreach ($patterns as $type => $pattern) {
        if (preg_match($pattern, $number)) {
            return $type;
        }
    }
    return false;
}

// Fetch branches, room types, and company profile
try {
    $stmt = $pdo->query("SELECT id, name, location FROM branches");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT name, description, base_price FROM room_types");
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT company_name, contact_phone FROM company_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    $company_name = $company['company_name'] ?? 'Travel Company';
    $contact_phone = $company['contact_phone'] ?? 'Unknown';
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $branches = [];
    $room_types = [];
    $company_name = 'Travel Company';
    $contact_phone = 'Unknown';
}

include 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Travel Reservations - Travel Company Dashboard</title>
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
                <li><a href="travel_company_dashboard.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'travel_dashboard.php' ? 'active' : ''; ?>"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li><a href="make_travel_reservations.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'make_travel_reservations.php' ? 'active' : ''; ?>"><i class="ri-calendar-check-line"></i><span>Make Reservations</span></a></li>
                <li><a href="travel_manage_reservations.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'travel_manage_reservations.php' ? 'active' : ''; ?>"><i class="ri-calendar-line"></i><span>Manage Reservations</span></a></li>
                <li><a href="travel_additional_services.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'travel_additional_services.php' ? 'active' : ''; ?>"><i class="ri-service-line"></i><span>Additional Services</span></a></li>
                <li><a href="travel_billing_payments.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'travel_billing_payments.php' ? 'active' : ''; ?>"><i class="ri-wallet-line"></i><span>Billing & Payments</span></a></li>
                <li><a href="company_profile.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'company_profile.php' ? 'active' : ''; ?>"><i class="ri-settings-3-line"></i><span>Profile</span></a></li>
                <li><a href="logout.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'logout.php' ? 'active' : ''; ?>"><i class="ri-logout-box-line"></i><span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Make New Travel Reservations</h1>
            <div class="user__info">
                <span><?php echo htmlspecialchars($company_name); ?> (<?php echo htmlspecialchars($contact_phone); ?>)</span>
                <img src="/hotel_chain_management/assets/images/avatar.png?v=<?php echo time(); ?>" alt="avatar" class="user__avatar" />
            </div>
        </header>

        <section class="reservation__section">
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

            <form method="POST" class="reservation__form">
                <div class="form__group">
                    <label for="branch_id">Select Branch</label>
                    <select id="branch_id" name="branch_id" required>
                        <option value="">Select a branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?php echo $branch['id']; ?>" <?php echo isset($_POST['branch_id']) && $_POST['branch_id'] == $branch['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch['name'] . ' - ' . $branch['location']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form__group">
                    <label for="room_type">Room Type</label>
                    <select id="room_type" name="room_type" required>
                        <option value="">Select room type</option>
                        <?php foreach ($room_types as $type): ?>
                            <option value="<?php echo strtolower($type['name']); ?>" <?php echo isset($_POST['room_type']) && $_POST['room_type'] == strtolower($type['name']) ? 'selected' : ''; ?>
                                data-price="<?php echo $type['base_price']; ?>" data-reservation-fee="<?php echo match(strtolower($type['name'])) { 'single' => 30.00, 'double' => 50.00, 'suite' => 70.00, default => 0.00 }; ?>">
                                <?php echo htmlspecialchars($type['name'] . ' ($' . number_format($type['base_price'], 2) . '/night, $' . number_format(match(strtolower($type['name'])) { 'single' => 30.00, 'double' => 50.00, 'suite' => 70.00, default => 0.00 }, 2) . ' reservation fee)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form__help" id="discount_message" style="display: none; color: #059669;">
                        Bookings of 3+ rooms are eligible for discounts: 5% for 3–6 days, 10% for 7–13 days, 15% for 14–27 days, 20% for 28–59 days, 25% for 60+ days.
                    </small>
                </div>

                <div class="form__group" id="date_ranges">
                    <label>Date Ranges</label>
                    <div class="date-range-group" id="date_range_0">
                        <div class="form__row">
                            <div class="form__group">
                                <label for="check_in_date_0">Check-in Date</label>
                                <input type="date" id="check_in_date_0" name="check_in_date[0]" required min="2025-06-01" value="<?php echo isset($_POST['check_in_date'][0]) ? htmlspecialchars($_POST['check_in_date'][0]) : ''; ?>">
                            </div>
                            <div class="form__group">
                                <label for="check_out_date_0">Check-out Date</label>
                                <input type="date" id="check_out_date_0" name="check_out_date[0]" required min="2025-06-02" value="<?php echo isset($_POST['check_out_date'][0]) ? htmlspecialchars($_POST['check_out_date'][0]) : ''; ?>">
                            </div>
                        </div>
                        <small class="form__error" id="date_error_0" style="display: none;"></small>
                        <small class="form__help" id="discount_applied_message_0" style="display: none; color: #059669;"></small>
                    </div>
                    <button type="button" id="add_date_range" class="add-date-range">Add Another Date Range</button>
                    <small class="form__help">Enter at least one date range. Check-in must be today (2025-06-01) or later, and check-out must be after check-in. Example: 2025-06-01 to 2025-06-05.</small>
                </div>

                <div class="form__group">
                    <label for="occupants">Number of Occupants</label>
                    <input type="number" id="occupants" name="occupants" min="1" value="<?php echo isset($_POST['occupants']) ? htmlspecialchars($_POST['occupants']) : 1; ?>" required>
                    <small class="form__help">Total number of occupants for all rooms</small>
                </div>

                <div class="form__group">
                    <label for="number_of_rooms">Number of Rooms</label>
                    <input type="number" id="number_of_rooms" name="number_of_rooms" min="1" max="10" value="<?php echo isset($_POST['number_of_rooms']) ? htmlspecialchars($_POST['number_of_rooms']) : 1; ?>" required>
                    <small class="form__help">How many rooms would you like to reserve? (Max 10)</small>
                </div>

                <div class="form__group total__cost">
                    <label>Cost Breakdown</label>
                    <div class="cost-breakdown" id="cost_breakdown">
                        <div class="cost-item cost-header">
                            <span>Range</span>
                            <span>Base Cost</span>
                            <span>Discount</span>
                            <span>Reservation Fee</span>
                            <span>Remaining Balance</span>
                        </div>
                        <div id="cost_rows"></div>
                        <div class="cost-item cost-total">
                            <span>Total Reservation Fee:</span>
                            <span id="reservation_fee">$0.00</span>
                        </div>
                        <div class="cost-item cost-total">
                            <span>Total Discount:</span>
                            <span id="total_discount">$0.00</span>
                        </div>
                        <div class="cost-item cost-total">
                            <span>Total Remaining Balance (due at checkout):</span>
                            <span id="remaining_balance">$0.00</span>
                        </div>
                        <div class="cost-item cost-total">
                            <span>Grand Total:</span>
                            <span id="grand_total">$0.00</span>
                        </div>
                    </div>
                    <small class="form__help">Reservation fee is charged now for credit card payments; otherwise, due at checkout. Discounts apply for 3+ rooms based on stay length.</small>
                </div>

                <div class="form__group">
                    <label for="payment_method">Payment Method</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="credit_card" <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] === 'credit_card' ? 'selected' : ''; ?>>Credit Card</option>
                        <option value="without_credit_card" <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] === 'without_credit_card' ? 'selected' : ''; ?> <?php echo $is_without_credit_card_disabled ? 'disabled' : ''; ?>>Without Credit Card</option>
                    </select>
                    <?php if ($is_without_credit_card_disabled): ?>
                        <small class="form__help" style="color: #dc2626;">Pay at Checkout is disabled between 7 PM and 11:59 PM.</small>
                    <?php endif; ?>
                </div>

                <div id="credit_card_fields" style="display: <?php echo isset($_POST['payment_method']) && $_POST['payment_method'] === 'credit_card' ? 'block' : 'none'; ?>;">
                    <div class="form__group">
                        <label for="cardholder_name">Cardholder Name</label>
                        <input type="text" id="cardholder_name" name="cardholder_name" placeholder="John Doe" value="<?php echo isset($_POST['cardholder_name']) ? htmlspecialchars($_POST['cardholder_name']) : ''; ?>">
                        <small class="form__error" id="cardholder_name_error"></small>
                    </div>
                    <div class="form__group">
                        <label for="card_number">Card Number</label>
                        <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" value="<?php echo isset($_POST['card_number']) ? htmlspecialchars($_POST['card_number']) : ''; ?>">
                        <small class="form__error" id="card_number_error"></small>
                    </div>
                    <div class="form__row">
                        <div class="form__group">
                            <label for="card_expiry">Expiry Date</label>
                            <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY" maxlength="5" value="<?php echo isset($_POST['card_expiry']) ? htmlspecialchars($_POST['card_expiry']) : ''; ?>">
                            <small class="form__error" id="card_expiry_error"></small>
                        </div>
                        <div class="form__group">
                            <label for="card_cvc">CVC</label>
                            <input type="text" id="card_cvc" name="card_cvc" placeholder="123" maxlength="4" value="<?php echo isset($_POST['card_cvc']) ? htmlspecialchars($_POST['card_cvc']) : ''; ?>">
                            <small class="form__error" id="card_cvc_error"></small>
                        </div>
                    </div>
                </div>

                <button type="submit" class="submit__button">Make Reservations</button>
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

.reservation__section {
    max-width: 800px;
    margin: 0 auto;
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.reservation__form {
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

.form__error {
    font-size: 0.875rem;
    color: #dc2626;
    margin-top: 0.25rem;
    display: none;
}

.form__error.show {
    display: block;
}

.form__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.date-range-group {
    border: 1px solid #d1d5db;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 0.5rem;
}

.add-date-range, .remove-date-range {
    background: #3b82f6;
    color: white;
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background 0.2s ease;
}

.remove-date-range {
    background: #dc2626;
    margin-top: 0.5rem;
}

.add-date-range:hover {
    background: #2563eb;
}

.remove-date-range:hover {
    background: #b91c1c;
}

.total__cost {
    background: #f9fafb;
    padding: 1rem;
    border-radius: 8px;
    border: 2px solid #3b82f6;
}

.cost-breakdown {
    display: grid;
    gap: 0.5rem;
}

.cost-item {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr 1fr;
    font-size: 1rem;
    padding: 0.25rem 0;
}

.cost-header {
    font-weight: 600;
    background: #e5e7eb;
    padding: 0.5rem;
    border-radius: 4px;
}

.cost-total {
    display: flex;
    justify-content: space-between;
    font-weight: 600;
    font-size: 1.25rem;
    color: #1f2937;
    border-top: 1px solid #d1d5db;
    padding-top: 0.5rem;
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
    
    .form__row {
        grid-template-columns: 1fr;
    }
    
    .cost-item {
        grid-template-columns: 1fr;
        gap: 0.25rem;
    }
    
    .cost-header {
        display: none;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');
    const isWithoutCreditCardDisabled = <?php echo json_encode($is_without_credit_card_disabled); ?>;

    // Sidebar toggle
    sidebarToggle?.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-fold-line');
        sidebarToggle.querySelector('i').classList.toggle('ri-menu-unfold-line');
    });

    // Payment fields handling
    const togglePaymentFields = () => {
        const paymentSelect = document.getElementById('payment_method');
        const creditCardFields = document.getElementById('credit_card_fields');
        creditCardFields.style.display = paymentSelect.value === 'credit_card' ? 'block' : 'none';
        
        const creditCardInputs = creditCardFields.querySelectorAll('input');
        creditCardInputs.forEach(input => {
            input.required = paymentSelect.value === 'credit_card';
        });
    };

    // Show error messages
    const showError = (fieldId, message) => {
        const errorElement = document.getElementById(`${fieldId}_error`);
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.style.display = message ? 'block' : 'none';
        }
    };

    // Update total cost
    const updateTotalCost = () => {
        const roomTypeSelect = document.getElementById('room_type');
        const discountMessage = document.getElementById('discount_message');
        const reservationFee = document.getElementById('reservation_fee');
        const totalDiscount = document.getElementById('total_discount');
        const remainingBalance = document.getElementById('remaining_balance');
        const grandTotal = document.getElementById('grand_total');
        const costRows = document.getElementById('cost_rows');
        const numberOfRooms = parseInt(document.getElementById('number_of_rooms').value) || 1;
        const paymentMethod = document.getElementById('payment_method').value;
        const dateRanges = document.querySelectorAll('.date-range-group');

        discountMessage.style.display = numberOfRooms >= 3 ? 'block' : 'none';
        costRows.innerHTML = '';

        if (!roomTypeSelect.value) {
            reservationFee.textContent = '$0.00';
            totalDiscount.textContent = '$0.00';
            remainingBalance.textContent = '$0.00';
            grandTotal.textContent = '$0.00';
            return;
        }

        const selectedOption = roomTypeSelect.options[roomTypeSelect.selectedIndex];
        const reservationFeePerRoom = parseFloat(selectedOption.dataset.reservationFee) || 0;
        const basePrice = parseFloat(selectedOption.dataset.price) || 100;
        let totalReservationFee = 0;
        let totalRemainingBalance = 0;
        let totalDiscountAmount = 0;

        dateRanges.forEach((range, index) => {
            const checkInDate = document.getElementById(`check_in_date_${index}`).value;
            const checkOutDate = document.getElementById(`check_out_date_${index}`).value;
            if (checkInDate && checkOutDate) {
                const checkIn = new Date(checkInDate);
                const checkOut = new Date(checkOutDate);
                const days = (checkOut - checkIn) / (1000 * 60 * 60 * 24);
                if (days > 0) {
                    const discountPercentage = numberOfRooms >= 3 ? (days >= 60 ? 25 : days >= 28 ? 20 : days >= 14 ? 15 : days >= 7 ? 10 : days >= 3 ? 5 : 0) : 0;
                    const reservationCost = reservationFeePerRoom * numberOfRooms;
                    const baseCost = basePrice * days * numberOfRooms;
                    const discountAmount = baseCost * (discountPercentage / 100);
                    const remainingCost = baseCost - discountAmount;
                    totalReservationFee += reservationCost;
                    totalRemainingBalance += remainingCost;
                    totalDiscountAmount += discountAmount;

                    // Add cost row
                    const row = document.createElement('div');
                    row.className = 'cost-item';
                    row.innerHTML = `
                        <span>Range ${index + 1} (${days} days)</span>
                        <span>$${baseCost.toFixed(2)}</span>
                        <span>${discountPercentage}% (-$${discountAmount.toFixed(2)})</span>
                        <span>$${reservationCost.toFixed(2)}</span>
                        <span>$${remainingCost.toFixed(2)}</span>
                    `;
                    costRows.appendChild(row);

                    const discountMessage = document.getElementById(`discount_applied_message_${index}`);
                    if (discountPercentage > 0) {
                        discountMessage.textContent = `${discountPercentage}% discount applied for ${days} days (Range ${index + 1}, $${discountAmount.toFixed(2)} saved)`;
                        discountMessage.style.display = 'block';
                    } else {
                        discountMessage.style.display = 'none';
                    }
                }
            }
        });

        reservationFee.textContent = paymentMethod === 'credit_card' ? `$${totalReservationFee.toFixed(2)}` : `$${totalReservationFee.toFixed(2)} (Pay at Checkout)`;
        totalDiscount.textContent = `-$${totalDiscountAmount.toFixed(2)}`;
        remainingBalance.textContent = `$${totalRemainingBalance.toFixed(2)}`;
        grandTotal.textContent = paymentMethod === 'credit_card' ? `$${(totalReservationFee + totalRemainingBalance).toFixed(2)}` : `$${(totalReservationFee + totalRemainingBalance).toFixed(2)}`;
    };

    // Validate date range
    const validateDateRange = (index) => {
        const checkInDate = document.getElementById(`check_in_date_${index}`).value;
        const checkOutDate = document.getElementById(`check_out_date_${index}`).value;
        const today = new Date('2025-06-01');
        today.setHours(0, 0, 0, 0);
        let error = '';

        if (!checkInDate) {
            error = 'Please select a check-in date.';
        } else if (!checkOutDate) {
            error = 'Please select a check-out date.';
        } else {
            const checkIn = new Date(checkInDate);
            const checkOut = new Date(checkOutDate);
            if (checkIn < today) {
                error = 'Check-in date cannot be before today (2025-06-01).';
            } else if (checkOut <= checkIn) {
                error = 'Check-out date must be after check-in date.';
            }
        }

        showError(`date_${index}`, error);
        return !error;
    };

    // Credit card validation functions
    const validateCardholderName = (name) => {
        if (!name || name.trim().length < 2) {
            return 'Name must be at least 2 characters.';
        }
        if (!/^[a-zA-Z\s\-\.\']+$/.test(name)) {
            return 'Name contains invalid characters.';
        }
        return '';
    };

    const validateCardNumber = (number) => {
        const cleanNumber = number.replace(/\D/g, '');
        if (!/^\d{13,19}$/.test(cleanNumber)) {
            return 'Card number must be 13–19 digits.';
        }
        if (!luhnCheck(cleanNumber)) {
            return 'Invalid card number.';
        }
        if (!getCardType(cleanNumber)) {
            return 'Unsupported card type.';
        }
        return '';
    };

    const validateExpiryDate = (expiry) => {
        if (!/^(0[1-9]|1[0-2])\/\d{2}$/.test(expiry)) {
            return 'Invalid format (MM/YY).';
        }
        const [month, year] = expiry.split('/').map(Number);
        const currentYear = new Date().getFullYear() % 100;
        const currentMonth = new Date().getMonth() + 1;
        if (year < currentYear || (year === currentYear && month < currentMonth)) {
            return 'Card has expired.';
        }
        if (year > currentYear + 10) {
            return 'Expiry date too far in future.';
        }
        return '';
    };

    const validateCVC = (cvc, cardNumber) => {
        if (!/^\d{3,4}$/.test(cvc)) {
            return 'CVC must be 3–4 digits.';
        }
        const cleanNumber = cardNumber.replace(/\D/g, '');
        const cardType = getCardType(cleanNumber);
        if (cardType === 'amex' && cvc.length !== 4) {
            return 'Amex requires 4-digit CVC.';
        }
        if (cardType !== 'amex' && cvc.length !== 3) {
            return 'CVC must be 3 digits.';
        }
        return '';
    };

    const luhnCheck = (number) => {
        let sum = 0;
        let alternate = false;
        for (let i = number.length - 1; i >= 0; i--) {
            let digit = parseInt(number[i]);
            if (alternate) {
                digit *= 2;
                if (digit > 9) {
                    digit = (digit % 10) + 1;
                }
            }
            sum += digit;
            alternate = !alternate;
        }
        return sum % 10 === 0;
    };

    const getCardType = (number) => {
        const patterns = {
            visa: /^4[0-9]{12}(?:[0-9]{3})?$/,
            mastercard: /^5[1-5][0-9]{14}$/,
            amex: /^3[47][0-9]{13}$/,
            discover: /^6(?:011|5[0-9]{2})[0-9]{12}$/
        };
        for (const [type, pattern] of Object.entries(patterns)) {
            if (pattern.test(number)) {
                return type;
            }
        }
        return null;
    };

    // Dynamic date range handling
    let dateRangeCount = 1;
    const addDateRangeButton = document.getElementById('add_date_range');
    const dateRangesContainer = document.getElementById('date_ranges');

    addDateRangeButton.addEventListener('click', () => {
        const newDateRange = document.createElement('div');
        newDateRange.className = 'date-range-group';
        newDateRange.id = `date_range_${dateRangeCount}`;
        newDateRange.innerHTML = `
            <div class="form__row">
                <div class="form__group">
                    <label for="check_in_date_${dateRangeCount}">Check-in Date</label>
                    <input type="date" id="check_in_date_${dateRangeCount}" name="check_in_date[${dateRangeCount}]" required min="2025-06-01">
                </div>
                <div class="form__group">
                    <label for="check_out_date_${dateRangeCount}">Check-out Date</label>
                    <input type="date" id="check_out_date_${dateRangeCount}" name="check_out_date[${dateRangeCount}]" required min="2025-06-02">
                </div>
            </div>
            <button type="button" class="remove-date-range">Remove</button>
            <small class="form__error" id="date_error_${dateRangeCount}" style="display: none;"></small>
            <small class="form__help" id="discount_applied_message_${dateRangeCount}" style="display: none; color: #059669;"></small>
        `;
        dateRangesContainer.insertBefore(newDateRange, addDateRangeButton);

        const newCheckIn = document.getElementById(`check_in_date_${dateRangeCount}`);
        const newCheckOut = document.getElementById(`check_out_date_${dateRangeCount}`);
        newCheckIn.addEventListener('change', () => {
            const minCheckOutDate = new Date(newCheckIn.value);
            minCheckOutDate.setDate(minCheckOutDate.getDate() + 1);
            newCheckOut.min = minCheckOutDate.toISOString().split('T')[0];
            newCheckOut.value = '';
            validateDateRange(dateRangeCount);
            updateTotalCost();
        });
        newCheckOut.addEventListener('change', () => {
            validateDateRange(dateRangeCount);
            updateTotalCost();
        });

        newDateRange.querySelector('.remove-date-range').addEventListener('click', () => {
            if (document.querySelectorAll('.date-range-group').length > 1) {
                newDateRange.remove();
                updateTotalCost();
            } else {
                alert('At least one date range is required.');
            }
        });

        dateRangeCount++;
        updateTotalCost();
    });

    // Initialize first date range
    const checkInDate0 = document.getElementById('check_in_date_0');
    const checkOutDate0 = document.getElementById('check_out_date_0');
    checkInDate0.addEventListener('change', () => {
        const minCheckOutDate = new Date(checkInDate0.value);
        minCheckOutDate.setDate(minCheckOutDate.getDate() + 1);
        checkOutDate0.min = minCheckOutDate.toISOString().split('T')[0];
        if (checkOutDate0.value && new Date(checkOutDate0.value) <= new Date(checkInDate0.value)) {
            checkOutDate0.value = '';
        }
        validateDateRange(0);
        updateTotalCost();
    });
    checkOutDate0.addEventListener('change', () => {
        validateDateRange(0);
        updateTotalCost();
    });

    // Form validation on submit
    const form = document.querySelector('.reservation__form');
    form.addEventListener('submit', (e) => {
        const errors = [];
        const paymentMethod = document.getElementById('payment_method').value;
        const roomTypeSelect = document.getElementById('room_type');
        const numberOfRooms = document.getElementById('number_of_rooms');

        if (!roomTypeSelect.value) {
            errors.push('Please select a room type.');
        }
        const dateRanges = document.querySelectorAll('.date-range-group');
        let hasValidDateRange = false;
        dateRanges.forEach((range, index) => {
            if (validateDateRange(index)) {
                hasValidDateRange = true;
            } else {
                errors.push(`Invalid dates for range ${index + 1}.`);
            }
        });
        if (!hasValidDateRange) {
            errors.push('Please provide at least one valid date range.');
        }
        const occupants = parseInt(document.getElementById('occupants').value);
        if (!occupants || occupants < 1) {
            errors.push('Please provide a valid number of occupants.');
        }
        const numberOfRoomsValue = parseInt(numberOfRooms.value);
        if (!numberOfRoomsValue || numberOfRoomsValue < 1) {
            errors.push('Please provide a valid number of rooms.');
        } else if (numberOfRoomsValue > 10) {
            errors.push('Cannot reserve more than 10 rooms.');
        }
        if (paymentMethod === 'without_credit_card' && isWithoutCreditCardDisabled) {
            errors.push("Cannot select 'Pay at Checkout' between 7 PM and 11:59 PM.");
        }
        if (paymentMethod === 'credit_card') {
            const cardholderName = document.getElementById('cardholder_name').value;
            const cardNumber = document.getElementById('card_number').value;
            const cardExpiry = document.getElementById('card_expiry').value;
            const cardCvc = document.getElementById('card_cvc').value;
            const cardholderNameError = validateCardholderName(cardholderName);
            if (cardholderNameError) {
                errors.push(cardholderNameError);
                showError('cardholder_name', cardholderNameError);
            }
            const cardNumberError = validateCardNumber(cardNumber);
            if (cardNumberError) {
                errors.push(cardNumberError);
                showError('card_number', cardNumberError);
            }
            const cardExpiryError = validateExpiryDate(cardExpiry);
            if (cardExpiryError) {
                errors.push(cardExpiryError);
                showError('card_expiry', cardExpiryError);
            }
            const cardCvcError = validateCVC(cardCvc, cardNumber);
            if (cardCvcError) {
                errors.push(cardCvcError);
                showError('card_cvc', cardCvcError);
            }
        }

        if (errors.length > 0) {
            e.preventDefault();
            alert('Please fix the following errors:\n\n' + errors.join('\n'));
        }
    });

    // Credit card input handling
    const cardholderNameInput = document.getElementById('cardholder_name');
    const cardNumberInput = document.getElementById('card_number');
    const cardExpiryInput = document.getElementById('card_expiry');
    const cardCvcInput = document.getElementById('card_cvc');

    cardholderNameInput.addEventListener('input', () => {
        showError('cardholder_name', validateCardholderName(cardholderNameInput.value));
    });
    cardholderNameInput.addEventListener('blur', () => {
        showError('cardholder_name', validateCardholderName(cardholderNameInput.value));
    });

    cardNumberInput.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, '');
        value = value.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
        e.target.value = value.slice(0, 19);
        showError('card_number', validateCardNumber(value));
    });
    cardNumberInput.addEventListener('blur', () => {
        showError('card_number', validateCardNumber(cardNumberInput.value));
    });

    cardExpiryInput.addEventListener('input', (e) => {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.slice(0, 2) + '/' + value.slice(2, 4);
        }
        e.target.value = value.slice(0, 5);
        showError('card_expiry', validateExpiryDate(value));
    });
    cardExpiryInput.addEventListener('blur', () => {
        showError('card_expiry', validateExpiryDate(cardExpiryInput.value));
    });

    cardCvcInput.addEventListener('input', () => {
        cardCvcInput.value = cardCvcInput.value.replace(/\D/g, '').slice(0, 4);
        showError('card_cvc', validateCVC(cardCvcInput.value, cardNumberInput.value));
    });
    cardCvcInput.addEventListener('blur', () => {
        showError('card_cvc', validateCVC(cardCvcInput.value, cardNumberInput.value));
    });

    // Event listeners
    const roomTypeSelect = document.getElementById('room_type');
    const numberOfRooms = document.getElementById('number_of_rooms');
    const paymentMethodSelect = document.getElementById('payment_method');
    roomTypeSelect.addEventListener('change', updateTotalCost);
    numberOfRooms.addEventListener('input', updateTotalCost);
    paymentMethodSelect.addEventListener('change', () => {
        togglePaymentFields();
        updateTotalCost();
    });

    // Reset form after success
    const resetFormToDefault = () => {
        form.reset();
        document.getElementById('branch_id').value = '';
        document.getElementById('room_type').value = '';
        document.getElementById('occupants').value = '1';
        document.getElementById('number_of_rooms').value = '1';
        document.getElementById('payment_method').value = isWithoutCreditCardDisabled ? 'credit_card' : 'credit_card';
        document.getElementById('cardholder_name').value = '';
        document.getElementById('card_number').value = '';
        document.getElementById('card_expiry').value = '';
        document.getElementById('card_cvc').value = '';
        const extraDateRanges = document.querySelectorAll('.date-range-group:not(#date_range_0)');
        extraDateRanges.forEach(range => range.remove());
        checkInDate0.value = '';
        checkOutDate0.value = '';
        dateRangeCount = 1;
        ['cardholder_name', 'card_number', 'card_expiry', 'card_cvc', 'date_0'].forEach(id => showError(id, ''));
        togglePaymentFields();
        updateTotalCost();
    };

    const successMessage = document.querySelector('.success');
    if (successMessage) {
        setTimeout(() => {
            resetFormToDefault();
            const resetNotification = document.createElement('div');
            resetNotification.innerHTML = '<small style="color: #059669; font-style: italic;">Form has been reset for your next reservation</small>';
            resetNotification.style.cssText = 'margin-top: 10px; padding: 5px; background: #ecfdf5; border-radius: 4px; border-left: 3px solid #10b981;';
            successMessage.appendChild(resetNotification);
            setTimeout(() => resetNotification.remove(), 3000);
        }, 2000);
        setTimeout(() => {
            successMessage.style.transition = 'opacity 0.5s ease-out';
            successMessage.style.opacity = '0';
            setTimeout(() => successMessage.remove(), 500);
        }, 5000);
    }

    // Initialize
    validateDateRange(0);
    togglePaymentFields();
    updateTotalCost();
});
</script>
</body>
</html>