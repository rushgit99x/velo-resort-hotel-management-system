<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict access to customers only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

// Include database connection
require_once 'db_connect.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

/**
 * Validates a credit card number using the Luhn Algorithm.
 *
 * @param string $cardNumber The credit card number (digits only).
 * @return bool True if the card number is valid, false otherwise.
 */
function validateCardNumberLuhn($cardNumber) {
    $cardNumber = preg_replace('/\D/', '', $cardNumber);
    if (!is_numeric($cardNumber)) {
        return false;
    }
    $sum = 0;
    $isEven = false;
    for ($i = strlen($cardNumber) - 1; $i >= 0; $i--) {
        $digit = (int)$cardNumber[$i];
        if ($isEven) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
        $isEven = !$isEven;
    }
    return $sum % 10 === 0;
}

/**
 * Identifies the card type based on the card number.
 *
 * @param string $cardNumber The credit card number (digits only).
 * @return string|null The card type (visa, mastercard, amex, discover) or null if unsupported.
 */
function getCardType($cardNumber) {
    $cardNumber = preg_replace('/\D/', '', $cardNumber);
    $patterns = [
        'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
        'mastercard' => '/^5[1-5][0-9]{14}$/',
        'amex' => '/^3[47][0-9]{13}$/',
        'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/'
    ];
    foreach ($patterns as $type => $pattern) {
        if (preg_match($pattern, $cardNumber)) {
            return $type;
        }
    }
    return null;
}

// Time-based payment restriction
date_default_timezone_set('Asia/Kolkata');
$current_hour = (int) date('H');
$restrict_without_card = ($current_hour >= 19 && $current_hour <= 23);

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pay') {
    $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS);
    $card_number = filter_input(INPUT_POST, 'card_number', FILTER_SANITIZE_SPECIAL_CHARS);
    $card_expiry = filter_input(INPUT_POST, 'card_expiry', FILTER_SANITIZE_SPECIAL_CHARS);
    $card_cvc = filter_input(INPUT_POST, 'card_cvc', FILTER_SANITIZE_SPECIAL_CHARS);
    $cardholder_name = filter_input(INPUT_POST, 'cardholder_name', FILTER_SANITIZE_SPECIAL_CHARS);

    // Validate payment inputs
    if (!$reservation_id) {
        $errors[] = "Invalid reservation selected.";
    }

    if (!$payment_method || !in_array($payment_method, ['credit_card', 'without_credit_card'])) {
        $errors[] = "Invalid payment method selected.";
    } elseif ($payment_method === 'without_credit_card' && $restrict_without_card) {
        $errors[] = "Without credit card payment is not available between 7 PM and 11:59 PM.";
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

    // Process payment if no errors
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Calculate amount with discount
            $stmt = $pdo->prepare("
                SELECT r.id, r.room_type, r.check_in_date, r.check_out_date, r.number_of_rooms, rt.base_price
                FROM reservations r
                JOIN room_types rt ON r.room_type = rt.name
                WHERE r.id = ? AND r.user_id = ?
            ");
            $stmt->execute([$reservation_id, $user_id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                $errors[] = "Reservation not found or you don't have permission to pay for it.";
            } else {
                $days = (strtotime($reservation['check_out_date']) - strtotime($reservation['check_in_date'])) / (60 * 60 * 24);
                $base_amount = $reservation['base_price'] * $days * $reservation['number_of_rooms'];

                // Calculate discount for suite
                $discount_percentage = 0;
                if ($reservation['room_type'] === 'suite') {
                    if ($days >= 28) {
                        $discount_percentage = 10;
                    } elseif ($days >= 21) {
                        $discount_percentage = 8;
                    } elseif ($days >= 14) {
                        $discount_percentage = 5;
                    } elseif ($days >= 7) {
                        $discount_percentage = 3;
                    }
                }
                $discount_amount = $base_amount * ($discount_percentage / 100);
                $amount = $base_amount - $discount_amount;

                // Insert payment
                $reservation_fee_per_room = match (strtolower($reservation['room_type'])) {
                    'single' => 30.00,
                    'double' => 50.00,
                    'suite' => 70.00,
                    default => 0.00,
                };
                $reservation_fee_amount = $reservation_fee_per_room * $reservation['number_of_rooms'];
                $total_amount = $payment_method === 'credit_card' ? ($amount + $reservation_fee_amount) : $amount;

                $card_last_four = $payment_method === 'credit_card' ? substr($clean_card_number ?? '', -4) : null;
                $stmt = $pdo->prepare("
                    INSERT INTO payments (user_id, reservation_id, amount, payment_method, card_last_four, cardholder_name, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $reservation_id,
                    $total_amount,
                    htmlspecialchars($payment_method),
                    $card_last_four,
                    htmlspecialchars($cardholder_name ?? ''),
                    $payment_method === 'credit_card' ? 'completed' : 'pending'
                ]);

                // Update reservation payment status and discount
                $stmt = $pdo->prepare("
                    UPDATE reservations 
                    SET payment_status = ?, discount_percentage = ?, remaining_balance = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([
                    $payment_method === 'credit_card' ? 'paid' : 'unpaid',
                    $discount_percentage,
                    $amount,
                    $reservation_id,
                    $user_id
                ]);

                $pdo->commit();
                $success = "Payment processed successfully for Reservation ID: $reservation_id. ";
                if ($payment_method === 'credit_card') {
                    $success .= "Total charged: $$total_amount.";
                } else {
                    $success .= "Total due at checkout: $$total_amount.";
                }
                if ($discount_percentage > 0) {
                    $success .= " A $discount_percentage% discount ($$discount_amount saved) was applied.";
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Failed to process payment: " . $e->getMessage();
        }
    }
}

// Handle reservation edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
    $branch_id = filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
    $room_type = filter_input(INPUT_POST, 'room_type', FILTER_SANITIZE_SPECIAL_CHARS);
    $check_in_date = filter_input(INPUT_POST, 'check_in_date', FILTER_DEFAULT);
    $check_out_date = filter_input(INPUT_POST, 'check_out_date', FILTER_DEFAULT);
    $occupants = filter_input(INPUT_POST, 'occupants', FILTER_VALIDATE_INT);
    $number_of_rooms = filter_input(INPUT_POST, 'number_of_rooms', FILTER_VALIDATE_INT);

    // Basic validation
    if (!$reservation_id) {
        $errors[] = "Invalid reservation selected.";
    }
    if (!$branch_id) {
        $errors[] = "Please select a valid branch.";
    }
    if (!in_array($room_type, ['single', 'double', 'suite'])) {
        $errors[] = "Please select a valid room type.";
    }
    if (!$check_in_date || !DateTime::createFromFormat('Y-m-d', $check_in_date)) {
        $errors[] = "Please provide a valid check-in date.";
    }
    if (!$check_out_date || !DateTime::createFromFormat('Y-m-d', $check_out_date)) {
        $errors[] = "Please provide a valid check-out date.";
    }
    if ($check_in_date && $check_out_date && $check_in_date >= $check_out_date) {
        $errors[] = "Check-out date must be after check-in date.";
    }
    if ($check_in_date && $check_in_date < date('Y-m-d')) {
        $errors[] = "Check-in date cannot be in the past.";
    }
    if (!$occupants || $occupants < 1) {
        $errors[] = "Please provide a valid number of occupants (minimum 1).";
    }
    if (!$number_of_rooms || $number_of_rooms < 1) {
        $errors[] = "Please provide a valid number of rooms (minimum 1).";
    } elseif ($number_of_rooms > 10) {
        $errors[] = "Cannot reserve more than 10 rooms at once.";
    }

    // Check room availability
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                SELECT rt.id
                FROM room_types rt
                JOIN rooms r ON r.room_type_id = rt.id
                WHERE r.branch_id = :branch_id AND rt.name = :room_type AND r.status = 'available'
                LIMIT :limit
            ");
            $stmt->bindValue(':branch_id', $branch_id, PDO::PARAM_INT);
            $stmt->bindValue(':room_type', ucfirst($room_type), PDO::PARAM_STR);
            $stmt->bindValue(':limit', (int)$number_of_rooms, PDO::PARAM_INT);
            $stmt->execute();
            $available_rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($available_rooms) < $number_of_rooms) {
                $errors[] = "Not enough rooms available for the selected type and quantity.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // Update reservation
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Calculate discount and remaining balance
            $stmt = $pdo->prepare("SELECT base_price FROM room_types WHERE name = ?");
            $stmt->execute([ucfirst($room_type)]);
            $base_price = $stmt->fetch(PDO::FETCH_ASSOC)['base_price'] ?? 100.00;
            $days = (strtotime($check_out_date) - strtotime($check_in_date)) / (60 * 60 * 24);

            $discount_percentage = 0;
            if ($room_type === 'suite') {
                if ($days >= 28) {
                    $discount_percentage = 10;
                } elseif ($days >= 21) {
                    $discount_percentage = 8;
                } elseif ($days >= 14) {
                    $discount_percentage = 5;
                } elseif ($days >= 7) {
                    $discount_percentage = 3;
                }
            }
            $base_amount = $base_price * $days * $number_of_rooms;
            $discount_amount = $base_amount * ($discount_percentage / 100);
            $remaining_balance = $base_amount - $discount_amount;

            $stmt = $pdo->prepare("
                UPDATE reservations 
                SET hotel_id = ?, room_type = ?, check_in_date = ?, check_out_date = ?, occupants = ?, number_of_rooms = ?, discount_percentage = ?, remaining_balance = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $branch_id,
                htmlspecialchars($room_type),
                htmlspecialchars($check_in_date),
                htmlspecialchars($check_out_date),
                $occupants,
                $number_of_rooms,
                $discount_percentage,
                $remaining_balance,
                $reservation_id,
                $user_id
            ]);
            $pdo->commit();
            $success = "Reservation ID: $reservation_id updated successfully! ";
            if ($discount_percentage > 0) {
                $success .= "A $discount_percentage% discount ($$discount_amount saved) was applied to the remaining balance.";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Failed to update reservation: " . $e->getMessage();
        }
    }
}

// Handle reservation cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);

    if (!$reservation_id) {
        $errors[] = "Invalid reservation selected.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT id, status, check_in_date
                FROM reservations
                WHERE id = ? AND user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$reservation_id, $user_id]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                $errors[] = "Reservation not found or not in pending status.";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE reservations SET status = 'cancelled' WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$reservation_id, $user_id]);

                $pdo->commit();
                $success = "Reservation ID: $reservation_id cancelled successfully!";
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Failed to cancel reservation: " . $e->getMessage();
        }
    }
}

// Fetch customer reservations
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.hotel_id, r.room_type, r.check_in_date, r.check_out_date, r.occupants, r.number_of_rooms, 
               r.status, r.payment_status, r.discount_percentage, r.remaining_balance, 
               b.name as branch_name, b.location, rt.base_price
        FROM reservations r
        JOIN branches b ON r.hotel_id = b.id
        JOIN room_types rt ON r.room_type = rt.name
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $reservations = [];
}

// Fetch branches and room types for edit form
try {
    $stmt = $pdo->query("SELECT id, name, location FROM branches");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT name, description, base_price FROM room_types");
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $branches = [];
    $room_types = [];
}

// Get customer details for header
try {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $customer_name = $user['name'] ?? 'Customer';
    $customer_email = $user['email'] ?? 'Unknown';
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $customer_name = 'Customer';
    $customer_email = 'Unknown';
}

include 'templates/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reservations - Customer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet">
</head>
<body>
<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__header">
            <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="sidebar__logo" />
            <h2 class="sidebar__title">Customer Dashboard</h2>
            <button class="sidebar__toggle" id="sidebar-toggle">
                <i class="ri-menu-fold-line"></i>
            </button>
        </div>
        <nav class="sidebar__nav">
            <ul class="sidebar__links">
                <li><a href="customer_dashboard.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'customer_dashboard.php' ? 'active' : ''; ?>"><i class="ri-dashboard-line"></i><span>Dashboard</span></a></li>
                <li><a href="make_reservation.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'make_reservation.php' ? 'active' : ''; ?>"><i class="ri-calendar-check-line"></i><span>Make Reservation</span></a></li>
                <li><a href="customer_manage_reservations.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'customer_manage_reservations.php' ? 'active' : ''; ?>"><i class="ri-calendar-line"></i><span>Manage Reservations</span></a></li>
                
                <li><a href="customer_additional_services.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'additional_services.php' ? 'active' : ''; ?>"><i class="ri-service-line"></i><span>Additional Services</span></a></li>
                <li><a href="billing_payments.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'billing_payments.php' ? 'active' : ''; ?>"><i class="ri-wallet-line"></i><span>Billing & Payments</span></a></li>
                
                <li><a href="customer_profile.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'customer_profile.php' ? 'active' : ''; ?>"><i class="ri-settings-3-line"></i><span>Profile</span></a></li>
                <li><a href="logout.php" class="sidebar__link <?php echo basename($_SERVER['PHP_SELF']) === 'logout.php' ? 'active' : ''; ?>"><i class="ri-logout-box-line"></i><span>Logout</span></a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Manage Your Reservations</h1>
            <div class="user__info">
                <span><?php echo htmlspecialchars($customer_email); ?></span>
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

            <?php if (empty($reservations)): ?>
                <p>No reservations found.</p>
            <?php else: ?>
                <div class="reservations__table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Branch</th>
                                <th>Room Type</th>
                                <th>Check-in</th>
                                <th>Check-out</th>
                                <th>Occupants</th>
                                <th>Rooms</th>
                                <th>Status</th>
                                <th>Payment</th>
                                <th>Amount</th>
                                <th>Discount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reservations as $reservation): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reservation['id']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['branch_name'] . ' - ' . $reservation['location']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($reservation['room_type'])); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['check_in_date']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['check_out_date']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['occupants']); ?></td>
                                    <td><?php echo htmlspecialchars($reservation['number_of_rooms']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($reservation['status'])); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($reservation['payment_status'])); ?></td>
                                    <td>
                                        <?php
                                        $reservation_fee_per_room = match (strtolower($reservation['room_type'])) {
                                            'single' => 30.00,
                                            'double' => 50.00,
                                            'suite' => 70.00,
                                            default => 0.00,
                                        };
                                        $reservation_fee = $reservation_fee_per_room * $reservation['number_of_rooms'];
                                        $total_amount = $reservation['remaining_balance'] + ($reservation['payment_status'] === 'unpaid' ? $reservation_fee : 0);
                                        echo '$' . number_format($total_amount, 2);
                                        ?>
                                    </td>
                                    <td><?php echo $reservation['discount_percentage'] > 0 ? htmlspecialchars($reservation['discount_percentage'] . '%') : '-'; ?></td>
                                    <td>
                                        <?php if ($reservation['payment_status'] === 'unpaid' && $reservation['status'] !== 'cancelled'): ?>
                                            <button class="action__button pay__button" onclick="showPaymentForm(<?php echo $reservation['id']; ?>, '<?php echo $reservation['room_type']; ?>', '<?php echo $reservation['check_in_date']; ?>', '<?php echo $reservation['check_out_date']; ?>', <?php echo $reservation['number_of_rooms']; ?>, <?php echo $reservation['base_price']; ?>)">Pay</button>
                                        <?php endif; ?>
                                        <?php if ($reservation['status'] === 'pending'): ?>
                                            <button class="action__button edit__button" onclick="showEditForm(<?php echo $reservation['id']; ?>, '<?php echo htmlspecialchars($reservation['hotel_id']); ?>', '<?php echo htmlspecialchars($reservation['room_type']); ?>', '<?php echo htmlspecialchars($reservation['check_in_date']); ?>', '<?php echo htmlspecialchars($reservation['check_out_date']); ?>', '<?php echo htmlspecialchars($reservation['occupants']); ?>', '<?php echo htmlspecialchars($reservation['number_of_rooms']); ?>')">Edit</button>
                                            <button class="action__button cancel_reservation__button" onclick="showCancelForm(<?php echo $reservation['id']; ?>)">Cancel</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Payment Form (Hidden by default) -->
                <div id="payment_form" style="display: none;">
                    <h2>Pay for Reservation</h2>
                    <form method="POST" class="reservation__form">
                        <input type="hidden" name="action" value="pay">
                        <input type="hidden" id="payment_reservation_id" name="reservation_id">
                        <div class="form__group total__cost">
                            <label>Cost Breakdown</label>
                            <div class="cost-breakdown">
                                <div class="cost-item">
                                    <span>Reservation Fee:</span>
                                    <span id="payment_reservation_fee">$0.00</span>
                                </div>
                                <div class="cost-item">
                                    <span>Remaining Balance:</span>
                                    <span id="payment_remaining_balance">$0.00</span>
                                </div>
                                <div class="cost-item cost-total">
                                    <span>Grand Total:</span>
                                    <span id="payment_grand_total">$0.00</span>
                                </div>
                            </div>
                            <small class="form__help">Reservation fee is charged now for credit card payments; otherwise, due at checkout.</small>
                        </div>
                        <div class="form__group">
                            <label for="payment_method">Payment Method</label>
                            <select id="payment_method" name="payment_method" required>
                                <option value="credit_card">Credit Card</option>
                                <option value="without_credit_card" <?php echo $restrict_without_card ? 'disabled' : ''; ?>>Without Credit Card (Pay at Checkout)</option>
                            </select>
                            <small class="form__help" id="credit-card-required" style="display: <?php echo $restrict_without_card ? 'block' : 'none'; ?>; color: #dc2626;">
                                Without credit card payment is not available between 7 PM and 11:59 PM.
                            </small>
                        </div>
                        <div id="credit_card_fields">
                            <div class="form__group">
                                <label for="cardholder_name">Cardholder Name</label>
                                <input type="text" id="cardholder_name" name="cardholder_name" placeholder="John Doe">
                                <small class="form__error" id="cardholder_name_error"></small>
                            </div>
                            <div class="form__group">
                                <label for="card_number">Card Number</label>
                                <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                <small class="form__error" id="card_number_error"></small>
                            </div>
                            <div class="form__row">
                                <div class="form__group">
                                    <label for="card_expiry">Expiry Date</label>
                                    <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY" maxlength="5">
                                    <small class="form__error" id="card_expiry_error"></small>
                                </div>
                                <div class="form__group">
                                    <label for="card_cvc">CVC</label>
                                    <input type="text" id="card_cvc" name="card_cvc" placeholder="123" maxlength="4">
                                    <small class="form__error" id="card_cvc_error"></small>
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="submit__button">Submit Payment</button>
                        <button type="button" class="cancel__button" onclick="hidePaymentForm()">Cancel</button>
                    </form>
                </div>

                <!-- Edit Form (Hidden by default) -->
                <div id="edit_form" style="display: none;">
                    <h2>Edit Reservation</h2>
                    <form method="POST" class="reservation__form">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" id="edit_reservation_id" name="reservation_id">
                        <div class="form__group">
                            <label for="edit_branch_id">Select Branch</label>
                            <select id="edit_branch_id" name="branch_id" required>
                                <option value="">Select a branch</option>
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo $branch['id']; ?>">
                                        <?php echo htmlspecialchars($branch['name'] . ' - ' . $branch['location']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form__group">
                            <label for="edit_room_type">Room Type</label>
                            <select id="edit_room_type" name="room_type" required>
                                <option value="">Select room type</option>
                                <?php foreach ($room_types as $type): ?>
                                    <option value="<?php echo strtolower($type['name']); ?>" data-price="<?php echo $type['base_price']; ?>" data-reservation-fee="<?php echo match(strtolower($type['name'])) { 'single' => 30.00, 'double' => 50.00, 'suite' => 70.00, default => 0.00 }; ?>">
                                        <?php echo htmlspecialchars($type['name'] . ' ($' . number_format($type['base_price'], 2) . '/night, $' . number_format(match(strtolower($type['name'])) { 'single' => 30.00, 'double' => 50.00, 'suite' => 70.00, default => 0.00 }, 2) . ' reservation fee)'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form__help" id="edit_suite_discount_message" style="display: none; color: #059669;">
                                Suite bookings are eligible for discounts on the remaining balance: 3% for 1 week, 5% for 2 weeks, 8% for 3 weeks, 10% for 4 weeks or more.
                            </small>
                        </div>
                        <div class="form__group">
                            <label for="edit_check_in_date">Check-in Date</label>
                            <input type="date" id="edit_check_in_date" name="check_in_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="form__group">
                            <label for="edit_check_out_date">Check-out Date</label>
                            <input type="date" id="edit_check_out_date" name="check_out_date" required min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                            <small class="form__help" id="edit_discount_applied_message" style="display: none; color: #059669;"></small>
                        </div>
                        <div class="form__group">
                            <label for="edit_occupants">Number of Occupants</label>
                            <input type="number" id="edit_occupants" name="occupants" min="1" required>
                            <small class="form__help">Total number of occupants for all rooms</small>
                        </div>
                        <div class="form__group">
                            <label for="edit_number_of_rooms">Number of Rooms</label>
                            <input type="number" id="edit_number_of_rooms" name="number_of_rooms" min="1" max="10" required>
                            <small class="form__help">How many rooms would you like to reserve?</small>
                        </div>
                        <div class="form__group total__cost">
                            <label>Cost Breakdown</label>
                            <div class="cost-breakdown">
                                <div class="cost-item">
                                    <span>Reservation Fee:</span>
                                    <span id="edit_reservation_fee">$0.00</span>
                                </div>
                                <div class="cost-item">
                                    <span>Remaining Balance (due at checkout):</span>
                                    <span id="edit_remaining_balance">$0.00</span>
                                </div>
                                <div class="cost-item cost-total">
                                    <span>Grand Total:</span>
                                    <span id="edit_grand_total">$0.00</span>
                                </div>
                            </div>
                            <small class="form__help">Reservation fee is charged now for credit card payments; otherwise, due at checkout.</small>
                        </div>
                        <button type="submit" class="submit__button">Update Reservation</button>
                        <button type="button" class="cancel__button" onclick="hideEditForm()">Cancel</button>
                    </form>
                </div>

                <!-- Cancel Form (Hidden by default) -->
                <div id="cancel_form" style="display: none;">
                    <h2>Cancel Reservation</h2>
                    <form method="POST" class="reservation__form">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" id="cancel_reservation_id" name="reservation_id">
                        <p>Are you sure you want to cancel this reservation? This action cannot be undone.</p>
                        <button type="submit" class="submit__button cancel_confirm__button">Confirm Cancellation</button>
                        <button type="button" class="cancel__button" onclick="hideCancelForm()">Back</button>
                    </form>
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

.reservation__section {
    max-width: 1250px;
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
    display: flex;
    justify-content: space-between;
    font-size: 1rem;
}

.cost-total {
    font-weight: 600;
    font-size: 1.25rem;
    color: #1f2937;
    border-top: 1px solid #d1d5db;
    padding-top: 0.5rem;
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

.pay__button {
    background: #10b981;
    color: white;
}

.pay__button:hover {
    background: #059669;
}

.edit__button {
    background: #f59e0b;
    color: white;
}

.edit__button:hover {
    background: #d97706;
}

.cancel_reservation__button, .cancel_confirm__button {
    background: #ef4444;
    color: white;
}

.cancel_reservation__button:hover, .cancel_confirm__button:hover {
    background: #dc2626;
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

.reservations__table {
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
    
    .form__row {
        grid-template-columns: 1fr;
    }
    
    .reservations__table {
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

    // Payment fields handling
    const togglePaymentFields = () => {
        const paymentSelect = document.getElementById('payment_method');
        const creditCardFields = document.getElementById('credit_card_fields');
        const creditCardRequired = document.getElementById('credit-card-required');
        
        const now = new Date();
        const currentHour = now.getHours();
        const restrictWithoutCard = currentHour >= 19 && currentHour <= 23;
        
        const withoutCardOption = paymentSelect.querySelector('option[value="without_credit_card"]');
        withoutCardOption.disabled = restrictWithoutCard;
        creditCardRequired.style.display = restrictWithoutCard ? 'block' : 'none';
        
        creditCardFields.style.display = paymentSelect.value === 'credit_card' ? 'block' : 'none';
        
        const creditCardInputs = creditCardFields.querySelectorAll('input');
        creditCardInputs.forEach(input => {
            input.required = paymentSelect.value === 'credit_card';
        });
    };

    // Update payment cost breakdown
    const updatePaymentCost = (roomType, checkInDate, checkOutDate, numberOfRooms, basePrice) => {
        const paymentReservationFee = document.getElementById('payment_reservation_fee');
        const paymentRemainingBalance = document.getElementById('payment_remaining_balance');
        const paymentGrandTotal = document.getElementById('payment_grand_total');
        const paymentMethod = document.getElementById('payment_method').value;

        const reservationFeePerRoom = {
            'single': 30.00,
            'double': 50.00,
            'suite': 70.00
        }[roomType.toLowerCase()] || 0;
        const reservationCost = reservationFeePerRoom * numberOfRooms;

        paymentReservationFee.textContent = paymentMethod === 'credit_card' ? `$${reservationCost.toFixed(2)}` : '$0.00 (Pay at Checkout)';

        if (checkInDate && checkOutDate) {
            const checkIn = new Date(checkInDate);
            const checkOut = new Date(checkOutDate);
            const days = (checkOut - checkIn) / (1000 * 60 * 60 * 24);

            let discountPercentage = 0;
            let discountMessage = '';
            if (roomType.toLowerCase() === 'suite') {
                if (days >= 28) {
                    discountPercentage = 10;
                    discountMessage = '10% discount applied for 4+ weeks stay';
                } else if (days >= 21) {
                    discountPercentage = 8;
                    discountMessage = '8% discount applied for 3 weeks stay';
                } else if (days >= 14) {
                    discountPercentage = 5;
                    discountMessage = '5% discount applied for 2 weeks stay';
                } else if (days >= 7) {
                    discountPercentage = 3;
                    discountMessage = '3% discount applied for 1 week stay';
                }
            }

            const baseCost = basePrice * days * numberOfRooms;
            const discountAmount = baseCost * (discountPercentage / 100);
            const remainingCost = baseCost - discountAmount;
            const totalCost = paymentMethod === 'credit_card' ? (reservationCost + remainingCost) : remainingCost;

            paymentRemainingBalance.textContent = `$${remainingCost.toFixed(2)}`;
            paymentGrandTotal.textContent = `$${totalCost.toFixed(2)}`;
        } else {
            paymentRemainingBalance.textContent = '$0.00';
            paymentGrandTotal.textContent = paymentMethod === 'credit_card' ? `$${reservationCost.toFixed(2)}` : '$0.00';
        }
    };

    // Update edit cost breakdown
    const updateEditCost = () => {
        const roomTypeSelect = document.getElementById('edit_room_type');
        const suiteDiscountMessage = document.getElementById('edit_suite_discount_message');
        const discountAppliedMessage = document.getElementById('edit_discount_applied_message');
        const reservationFee = document.getElementById('edit_reservation_fee');
        const remainingBalance = document.getElementById('edit_remaining_balance');
        const grandTotal = document.getElementById('edit_grand_total');
        const checkInDate = document.getElementById('edit_check_in_date').value;
        const checkOutDate = document.getElementById('edit_check_out_date').value;
        const numberOfRooms = parseInt(document.getElementById('edit_number_of_rooms').value) || 1;

        suiteDiscountMessage.style.display = roomTypeSelect.value === 'suite' ? 'block' : 'none';

        if (!roomTypeSelect.value) {
            reservationFee.textContent = '$0.00';
            remainingBalance.textContent = '$0.00';
            grandTotal.textContent = '$0.00';
            discountAppliedMessage.style.display = 'none';
            return;
        }

        const selectedOption = roomTypeSelect.options[roomTypeSelect.selectedIndex];
        const reservationFeePerRoom = parseFloat(selectedOption.dataset.reservationFee) || 0;
        const reservationCost = reservationFeePerRoom * numberOfRooms;
        reservationFee.textContent = `$${reservationCost.toFixed(2)}`;

        if (checkInDate && checkOutDate) {
            const checkIn = new Date(checkInDate);
            const checkOut = new Date(checkOutDate);
            const days = (checkOut - checkIn) / (1000 * 60 * 60 * 24);

            let discountPercentage = 0;
            let discountMessage = '';
            if (roomTypeSelect.value === 'suite') {
                if (days >= 28) {
                    discountPercentage = 10;
                    discountMessage = '10% discount applied for 4+ weeks stay';
                } else if (days >= 21) {
                    discountPercentage = 8;
                    discountMessage = '8% discount applied for 3 weeks stay';
                } else if (days >= 14) {
                    discountPercentage = 5;
                    discountMessage = '5% discount applied for 2 weeks stay';
                } else if (days >= 7) {
                    discountPercentage = 3;
                    discountMessage = '3% discount applied for 1 week stay';
                }
            }

            const basePrice = parseFloat(selectedOption.dataset.price) || 100;
            const baseCost = basePrice * days * numberOfRooms;
            const discountAmount = baseCost * (discountPercentage / 100);
            const remainingCost = baseCost - discountAmount;
            const totalCost = reservationCost + remainingCost;

            remainingBalance.textContent = `$${remainingCost.toFixed(2)}`;
            grandTotal.textContent = `$${totalCost.toFixed(2)}`;

            if (discountPercentage > 0) {
                discountAppliedMessage.textContent = `${discountMessage} ($${discountAmount.toFixed(2)} saved)`;
                discountAppliedMessage.style.display = 'block';
            } else {
                discountAppliedMessage.style.display = 'none';
            }
        } else {
            remainingBalance.textContent = '$0.00';
            grandTotal.textContent = `$${reservationCost.toFixed(2)}`;
            discountAppliedMessage.style.display = 'none';
        }
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

    // Show/hide error messages
    const showError = (fieldId, message) => {
        const errorElement = document.getElementById(`${fieldId}_error`);
        errorElement.textContent = message;
        errorElement.classList.toggle('show', !!message);
    };

    // Show payment form
    window.showPaymentForm = function(reservationId, roomType, checkInDate, checkOutDate, numberOfRooms, basePrice) {
        document.getElementById('payment_form').style.display = 'block';
        document.getElementById('edit_form').style.display = 'none';
        document.getElementById('cancel_form').style.display = 'none';
        document.getElementById('payment_reservation_id').value = reservationId;
        updatePaymentCost(roomType, checkInDate, checkOutDate, numberOfRooms, basePrice);
        togglePaymentFields();
        window.scrollTo({ top: document.getElementById('payment_form').offsetTop, behavior: 'smooth' });
    };

    // Hide payment form
    window.hidePaymentForm = function() {
        document.getElementById('payment_form').style.display = 'none';
        document.getElementById('payment_reservation_id').value = '';
        document.getElementById('cardholder_name').value = '';
        document.getElementById('card_number').value = '';
        document.getElementById('card_expiry').value = '';
        document.getElementById('card_cvc').value = '';
        ['cardholder_name', 'card_number', 'card_expiry', 'card_cvc'].forEach(id => {
            showError(id, '');
        });
    };

    // Show edit form
    window.showEditForm = function(reservationId, branchId, roomType, checkInDate, checkOutDate, occupants, numberOfRooms) {
        document.getElementById('edit_form').style.display = 'block';
        document.getElementById('payment_form').style.display = 'none';
        document.getElementById('cancel_form').style.display = 'none';
        document.getElementById('edit_reservation_id').value = reservationId;
        document.getElementById('edit_branch_id').value = branchId;
        document.getElementById('edit_room_type').value = roomType.toLowerCase();
        document.getElementById('edit_check_in_date').value = checkInDate;
        document.getElementById('edit_check_out_date').value = checkOutDate;
        document.getElementById('edit_occupants').value = occupants;
        document.getElementById('edit_number_of_rooms').value = numberOfRooms;
        updateEditCost();
        window.scrollTo({ top: document.getElementById('edit_form').offsetTop, behavior: 'smooth' });
    };

    // Hide edit form
    window.hideEditForm = function() {
        document.getElementById('edit_form').style.display = 'none';
        document.getElementById('edit_reservation_id').value = '';
        document.getElementById('edit_branch_id').value = '';
        document.getElementById('edit_room_type').value = '';
        document.getElementById('edit_check_in_date').value = '';
        document.getElementById('edit_check_out_date').value = '';
        document.getElementById('edit_occupants').value = '1';
        document.getElementById('edit_number_of_rooms').value = '1';
        document.getElementById('edit_discount_applied_message').style.display = 'none';
        ['edit_reservation_fee', 'edit_remaining_balance', 'edit_grand_total'].forEach(id => {
            document.getElementById(id).textContent = '$0.00';
        });
    };

    // Show cancel form
    window.showCancelForm = function(reservationId) {
        document.getElementById('cancel_form').style.display = 'block';
        document.getElementById('payment_form').style.display = 'none';
        document.getElementById('edit_form').style.display = 'none';
        document.getElementById('cancel_reservation_id').value = reservationId;
        window.scrollTo({ top: document.getElementById('cancel_form').offsetTop, behavior: 'smooth' });
    };

    // Hide cancel form
    window.hideCancelForm = function() {
        document.getElementById('cancel_form').style.display = 'none';
        document.getElementById('cancel_reservation_id').value = '';
    };

    // Attach event listeners for cost updates
    const editRoomTypeSelect = document.getElementById('edit_room_type');
    const editCheckInDate = document.getElementById('edit_check_in_date');
    const editCheckOutDate = document.getElementById('edit_check_out_date');
    const editNumberOfRooms = document.getElementById('edit_number_of_rooms');
    const paymentMethodSelect = document.getElementById('payment_method');

    if (editRoomTypeSelect) {
        editRoomTypeSelect.addEventListener('change', updateEditCost);
    }
    if (editCheckInDate) {
        editCheckInDate.addEventListener('change', () => {
            const checkInDate = new Date(editCheckInDate.value);
            const minCheckOut = new Date(checkInDate);
            minCheckOut.setDate(minCheckOut.getDate() + 1);
            editCheckOutDate.min = minCheckOut.toISOString().split('T')[0];
            if (editCheckOutDate.value && new Date(editCheckOutDate.value) <= checkInDate) {
                editCheckOutDate.value = '';
            }
            updateEditCost();
        });
    }
    if (editCheckOutDate) {
        editCheckOutDate.addEventListener('change', updateEditCost);
    }
    if (editNumberOfRooms) {
        editNumberOfRooms.addEventListener('input', updateEditCost);
    }
    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', togglePaymentFields);
    }

    // Credit card input handling
    const cardholderNameInput = document.getElementById('cardholder_name');
    const cardNumberInput = document.getElementById('card_number');
    const cardExpiryInput = document.getElementById('card_expiry');
    const cardCvcInput = document.getElementById('card_cvc');

    if (cardholderNameInput) {
        cardholderNameInput.addEventListener('input', () => {
            showError('cardholder_name', validateCardholderName(cardholderNameInput.value));
        });
        cardholderNameInput.addEventListener('blur', () => {
            showError('cardholder_name', validateCardholderName(cardholderNameInput.value));
        });
    }

    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', (e) => {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ').trim();
            e.target.value = value.slice(0, 19);
            showError('card_number', validateCardNumber(value));
        });
        cardNumberInput.addEventListener('blur', () => {
            showError('card_number', validateCardNumber(cardNumberInput.value));
        });
    }

    if (cardExpiryInput) {
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
    }

    if (cardCvcInput) {
        cardCvcInput.addEventListener('input', () => {
            cardCvcInput.value = cardCvcInput.value.replace(/\D/g, '').slice(0, 4);
            showError('card_cvc', validateCVC(cardCvcInput.value, cardNumberInput.value));
        });
        cardCvcInput.addEventListener('blur', () => {
            showError('card_cvc', validateCVC(cardCvcInput.value, cardNumberInput.value));
        });
    }

    // Form validation on submit
    const forms = document.querySelectorAll('.reservation__form');
    forms.forEach(form => {
        form.addEventListener('submit', (e) => {
            const errors = [];
            const action = form.querySelector('input[name="action"]').value;

            if (action === 'pay') {
                const paymentMethod = paymentMethodSelect.value;
                const now = new Date();
                const currentHour = now.getHours();
                if (paymentMethod === 'without_credit_card' && currentHour >= 19 && currentHour <= 23) {
                    errors.push('Without credit card payment is not available between 7 PM and 11:59 PM.');
                }

                if (paymentMethod === 'credit_card') {
                    const cardholderNameError = validateCardholderName(cardholderNameInput.value);
                    if (cardholderNameError) {
                        errors.push(cardholderNameError);
                        showError('cardholder_name', cardholderNameError);
                    }
                    const cardNumberError = validateCardNumber(cardNumberInput.value);
                    if (cardNumberError) {
                        errors.push(cardNumberError);
                        showError('card_number', cardNumberError);
                    }
                    const cardExpiryError = validateExpiryDate(cardExpiryInput.value);
                    if (cardExpiryError) {
                        errors.push(cardExpiryError);
                        showError('card_expiry', cardExpiryError);
                    }
                    const cardCvcError = validateCVC(cardCvcInput.value, cardNumberInput.value);
                    if (cardCvcError) {
                        errors.push(cardCvcError);
                        showError('card_cvc', cardCvcError);
                    }
                }
            } else if (action === 'edit') {
                const checkIn = new Date(editCheckInDate.value);
                const checkOut = new Date(editCheckOutDate.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (!editRoomTypeSelect.value) {
                    errors.push('Please select a room type.');
                }
                if (!editCheckInDate.value) {
                    errors.push('Please select a check-in date.');
                }
                if (!editCheckOutDate.value) {
                    errors.push('Please select a check-out date.');
                }
                if (checkIn < today) {
                    errors.push('Check-in date cannot be in the past.');
                }
                if (checkOut <= checkIn) {
                    errors.push('Check-out date must be after check-in date.');
                }
                const occupants = parseInt(editOccupants.value);
                if (!occupants || occupants < 1) {
                    errors.push('Please provide a valid number of occupants.');
                }
                const numberOfRooms = parseInt(editNumberOfRooms.value);
                if (!numberOfRooms || numberOfRooms < 1) {
                    errors.push('Please provide a valid number of rooms.');
                } else if (numberOfRooms > 10) {
                    errors.push('Cannot reserve more than 10 rooms.');
                }
            }

            if (errors.length > 0) {
                e.preventDefault();
                alert('Please fix the following errors:\n\n' + errors.join('\n'));
            }
        });
    });

    // Auto-hide success message and reset forms
    const successMessage = document.querySelector('.success');
    if (successMessage) {
        setTimeout(() => {
            hidePaymentForm();
            hideEditForm();
            hideCancelForm();
            const resetNotification = document.createElement('div');
            resetNotification.innerHTML = '<small style="color: #059669; font-style: italic;">Forms have been reset</small>';
            resetNotification.style.cssText = 'margin-top: 10px; padding: 5px; background: #ecfdf5; border-radius: 4px; border-left: 3px solid #10b981;';
            successMessage.appendChild(resetNotification);
            setTimeout(() => resetNotification.remove(), 3000);
            successMessage.style.transition = 'opacity 0.5s ease-out';
            successMessage.style.opacity = '0';
            setTimeout(() => successMessage.remove(), 500);
        }, 5000);
    }

    // Initialize
    togglePaymentFields();
});
</script>
</body>
</html>