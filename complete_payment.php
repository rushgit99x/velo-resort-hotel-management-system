<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header("Location: login.php");
    exit();
}

require_once 'db_connect.php';
include_once 'includes/functions.php';

$user_id = $_SESSION['user_id'];
$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pending_payment_id = filter_input(INPUT_POST, 'pending_payment_id', FILTER_VALIDATE_INT);
    $card_number = filter_input(INPUT_POST, 'card_number', FILTER_SANITIZE_SPECIAL_CHARS);
    $card_expiry = filter_input(INPUT_POST, 'card_expiry', FILTER_SANITIZE_SPECIAL_CHARS);
    $card_cvc = filter_input(INPUT_POST, 'card_cvc', FILTER_SANITIZE_SPECIAL_CHARS);
    $cardholder_name = filter_input(INPUT_POST, 'cardholder_name', FILTER_SANITIZE_SPECIAL_CHARS);

    // Validate inputs
    if (!$pending_payment_id) {
        $errors[] = "Invalid pending payment ID.";
    }

    // Cardholder name validation
    if (!$cardholder_name || strlen(trim($cardholder_name)) < 2) {
        $errors[] = "Please provide a valid cardholder name.";
    } elseif (!preg_match('/^[a-zA-Z\s\-\.\']+$/', $cardholder_name)) {
        $errors[] = "Cardholder name contains invalid characters.";
    }

    // Card number validation
    if (!$card_number) {
        $errors[] = "Please provide a card number.";
    } else {
        $clean_card_number = preg_replace('/[\s\-]/', '', $card_number);
        if (!preg_match('/^\d{13,19}$/', $clean_card_number)) {
            $errors[] = "Please provide a valid card number (13-19 digits).";
        } else {
            if (!validateCardNumberLuhn($clean_card_number)) {
                $errors[] = "Please provide a valid card number.";
            }
            $card_type = getCardType($clean_card_number);
            if (!$card_type) {
                $errors[] = "Card type not supported.";
            }
        }
    }

    // Expiry date validation
    if (!$card_expiry) {
        $errors[] = "Please provide an expiry date.";
    } elseif (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $card_expiry)) {
        $errors[] = "Please provide a valid expiry date (MM/YY format).";
    } else {
        list($month, $year) = explode('/', $card_expiry);
        $current_year = date('y');
        $current_month = date('m');
        if ($year < $current_year || ($year == $current_year && $month < $current_month)) {
            $errors[] = "Card has expired.";
        }
        if ($year > ($current_year + 10)) {
            $errors[] = "Invalid expiry date.";
        }
    }

    // CVC validation
    if (!$card_cvc) {
        $errors[] = "Please provide a CVC code.";
    } elseif (!preg_match('/^\d{3,4}$/', $card_cvc)) {
        $errors[] = "Please provide a valid CVC (3-4 digits).";
    } else {
        $card_type = isset($clean_card_number) ? getCardType($clean_card_number) : '';
        if ($card_type === 'amex' && strlen($card_cvc) !== 4) {
            $errors[] = "American Express cards require a 4-digit CVC.";
        } elseif ($card_type !== 'amex' && strlen($card_cvc) !== 3) {
            $errors[] = "Please provide a 3-digit CVC.";
        }
    }

    // Verify pending payment exists and belongs to user
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, amount, reservation_id 
                FROM pending_payments 
                WHERE id = ? AND user_id = ? AND status = 'pending'
            ");
            $stmt->execute([$pending_payment_id, $user_id]);
            $pending_payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$pending_payment) {
                $errors[] = "Invalid or already processed payment.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }

    // Process payment
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert into payments table
            $card_last_four = substr($clean_card_number, -4);
            $stmt = $pdo->prepare("
                INSERT INTO payments (user_id, reservation_id, amount, payment_method, card_last_four, cardholder_name, status, created_at)
                VALUES (?, ?, ?, 'credit_card', ?, ?, 'completed', NOW())
            ");
            $stmt->execute([
                $user_id,
                $pending_payment['reservation_id'],
                $pending_payment['amount'],
                $card_last_four,
                htmlspecialchars($cardholder_name)
            ]);

            // Update pending_payments status
            $stmt = $pdo->prepare("
                UPDATE pending_payments 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$pending_payment_id]);

            // Update reservation status to confirmed
            $stmt = $pdo->prepare("
                UPDATE reservations 
                SET status = 'confirmed' 
                WHERE id = ?
            ");
            $stmt->execute([$pending_payment['reservation_id']]);

            $pdo->commit();
            $success = "Payment completed successfully! Reservation confirmed.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Failed to process payment: " . $e->getMessage();
        }
    }
}

// Fetch pending payments for display
try {
    $stmt = $pdo->prepare("
        SELECT pp.id, pp.amount, pp.created_at, r.reservation_id, rt.name as room_type, r.check_in_date
        FROM pending_payments pp
        JOIN reservations r ON pp.reservation_id = r.id
        JOIN room_types rt ON r.room_type = rt.name
        WHERE pp.user_id = ? AND pp.status = 'pending'
    ");
    $stmt->execute([$user_id]);
    $pending_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
    $pending_payments = [];
}

include 'templates/header.php';
?>

<div class="dashboard__container">
    <aside class="sidebar" id="sidebar">
        <!-- Same sidebar content as make_reservation.php -->
        <!-- ... (copy the sidebar HTML from the updated make_reservation.php) -->
    </aside>

    <main class="dashboard__content">
        <header class="dashboard__header">
            <h1 class="section__header">Complete Pending Payments</h1>
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

            <?php if (empty($pending_payments)): ?>
                <p>No pending payments found.</p>
            <?php else: ?>
                <h2>Pending Payments</h2>
                <ul>
                    <?php foreach ($pending_payments as $payment): ?>
                        <li>
                            Reservation ID: <?php echo htmlspecialchars($payment['reservation_id']); ?><br>
                            Room Type: <?php echo htmlspecialchars($payment['room_type']); ?><br>
                            Check-in Date: <?php echo htmlspecialchars($payment['check_in_date']); ?><br>
                            Amount: $<?php echo number_format($payment['amount'], 2); ?><br>
                            Created: <?php echo htmlspecialchars($payment['created_at']); ?><br>
                            <form method="POST" class="reservation__form">
                                <input type="hidden" name="pending_payment_id" value="<?php echo $payment['id']; ?>">
                                <div class="form__group">
                                    <label for="cardholder_name">Cardholder Name</label>
                                    <input type="text" id="cardholder_name" name="cardholder_name" placeholder="John Doe" required>
                                    <small class="form__help">Name as it appears on the card</small>
                                </div>
                                <div class="form__group">
                                    <label for="card_number">Card Number</label>
                                    <input type="text" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19" required>
                                    <small class="form__help">Enter 13-19 digit card number</small>
                                </div>
                                <div class="form__row">
                                    <div class="form__group">
                                        <label for="card_expiry">Expiry Date</label>
                                        <input type="text" id="card_expiry" name="card_expiry" placeholder="MM/YY" maxlength="5" required>
                                    </div>
                                    <div class="form__group">
                                        <label for="card_cvc">CVC</label>
                                        <input type="text" id="card_cvc" name="card_cvc" placeholder="123" maxlength="4" required>
                                        <small class="form__help">3-4 digits on back of card</small>
                                    </div>
                                </div>
                                <button type="submit" class="submit__button">Complete Payment</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </main>
</div>

<!-- Include the same CSS and JavaScript as make_reservation.php, with additional JavaScript for card input formatting -->
<style>
    /* Copy the CSS from make_reservation.php */
</style>

<script>
    // Copy the JavaScript from make_reservation.php, but only include the card input formatting and form validation parts
    document.addEventListener('DOMContentLoaded', function() {
        // Card number formatting
        const cardNumberInputs = document.querySelectorAll('#card_number');
        cardNumberInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
                if (value.length > 19) {
                    value = value.substring(0, 19);
                }
                e.target.value = value;
            });
        });

        // Expiry date formatting
        const cardExpiryInputs = document.querySelectorAll('#card_expiry');
        cardExpiryInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length >= 2) {
                    value = value.substring(0, 2) + '/' + value.substring(2, 4);
                }
                e.target.value = value;
            });
        });

        // CVC input - numbers only
        const cardCvcInputs = document.querySelectorAll('#card_cvc');
        cardCvcInputs.forEach(input => {
            input.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        });

        // Form validation
        const paymentForms = document.querySelectorAll('.reservation__form');
        paymentForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const errors = [];
                const cardNumber = form.querySelector('#card_number').value.replace(/\s/g, '');
                const cardExpiry = form.querySelector('#card_expiry').value;
                const cardCvc = form.querySelector('#card_cvc').value;
                const cardholderName = form.querySelector('#cardholder_name').value.trim();

                if (!cardholderName || cardholderName.length < 2) {
                    errors.push('Please provide a valid cardholder name');
                }

                if (!cardNumber || !/^\d{13,19}$/.test(cardNumber)) {
                    errors.push('Please provide a valid card number');
                }

                if (!cardExpiry || !/^(0[1-9]|1[0-2])\/\d{2}$/.test(cardExpiry)) {
                    errors.push('Please provide a valid expiry date (MM/YY)');
                } else {
                    const [month, year] = cardExpiry.split('/');
                    const currentYear = new Date().getFullYear() % 100;
                    const currentMonth = new Date().getMonth() + 1;

                    if (parseInt(year) < currentYear || 
                        (parseInt(year) === currentYear && parseInt(month) < currentMonth)) {
                        errors.push('Card has expired');
                    }
                }

                if (!cardCvc || !/^\d{3,4}$/.test(cardCvc)) {
                    errors.push('Please provide a valid CVC (3-4 digits)');
                }

                if (errors.length > 0) {
                    e.preventDefault();
                    alert('Please fix the following errors:\n\n' + errors.join('\n'));
                }
            });
        });
    });
</script>