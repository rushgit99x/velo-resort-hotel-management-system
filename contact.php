<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'lib/PHPMailer/src/Exception.php';
require 'lib/PHPMailer/src/PHPMailer.php';
require 'lib/PHPMailer/src/SMTP.php';

require 'vendor/autoload.php'; // Adjust path if needed

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    // Use trim and htmlspecialchars instead of deprecated FILTER_SANITIZE_STRING
    $name = trim(htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8'));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $subject = trim(htmlspecialchars($_POST['subject'] ?? '', ENT_QUOTES, 'UTF-8'));
    $message = trim(htmlspecialchars($_POST['message'] ?? '', ENT_QUOTES, 'UTF-8'));

    // Validate inputs
    if (!$name || !$email || !$subject || !$message) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'rushbiz99x@gmail.com';
            $mail->Password = 'ngok qakc yqun dvfp';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('rushbiz99x@gmail.com', 'Velo Resort & Spa');
            $mail->addAddress('rvdmsi694@gmail.com'); // Change to your receiving email
            $mail->addReplyTo($email, $name);

            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Contact Form Submission: ' . $subject;
            $mail->Body = "
                <h2>Contact Form Submission</h2>
                <p><strong>Name:</strong> " . htmlspecialchars($name) . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>
                <p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                <p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>
            ";
            $mail->AltBody = "Name: $name\nEmail: $email\nSubject: $subject\nMessage: $message";

            $mail->send();
            $success = 'Your message has been sent successfully!';
            
            // Clear form fields after successful submission
            $_POST = array();
            $name = '';
            $email = '';
            $subject = '';
            $message = '';
            
        } catch (Exception $e) {
            $error = 'Failed to send message: ' . $mail->ErrorInfo;
        }
    }
}

include 'templates/header.php';
?>

<style>
    /* Contact section styles */
    .contact__wrapper {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        padding: 60px 0;
        min-height: 100vh;
    }

    .contact__container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 20px;
    }

    .contact__header {
        text-align: center;
        margin-bottom: 60px;
    }

    .contact__header h2 {
        font-size: 3em;
        color: #2c3e50;
        margin-bottom: 15px;
        font-weight: 700;
        position: relative;
    }

    .contact__header h2::after {
        content: '';
        position: absolute;
        bottom: -10px;
        left: 50%;
        transform: translateX(-50%);
        width: 80px;
        height: 4px;
        background: linear-gradient(135deg, #3498db, #2980b9);
        border-radius: 2px;
    }

    .contact__header p {
        font-size: 1.2em;
        color: #666;
        max-width: 600px;
        margin: 0 auto;
        line-height: 1.6;
    }

    .contact__grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 40px;
        margin-bottom: 60px;
    }

    /* Contact Info Section */
    .contact__info {
        background: white;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        transform: translateY(0);
        transition: all 0.3s ease;
    }

    .contact__info:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .contact__info h3 {
        font-size: 2em;
        color: #2c3e50;
        margin-bottom: 30px;
        font-weight: 600;
    }

    .contact__detail {
        display: flex;
        align-items: center;
        margin-bottom: 25px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 12px;
        transition: all 0.3s ease;
    }

    .contact__detail:hover {
        background: #e3f2fd;
        transform: translateX(5px);
    }

    .contact__detail i {
        font-size: 1.5em;
        color: #3498db;
        margin-right: 15px;
        width: 30px;
        text-align: center;
    }

    .contact__detail__content h4 {
        font-size: 1.1em;
        color: #2c3e50;
        margin-bottom: 5px;
        font-weight: 600;
    }

    .contact__detail__content p {
        color: #666;
        margin: 0;
        font-size: 0.95em;
    }

    /* Contact Form Section */
    .contact__form__wrapper {
        background: white;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        transform: translateY(0);
        transition: all 0.3s ease;
    }

    .contact__form__wrapper:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .contact__form__wrapper h3 {
        font-size: 2em;
        color: #2c3e50;
        margin-bottom: 30px;
        font-weight: 600;
    }

    .contact__form {
        display: flex;
        flex-direction: column;
        gap: 25px;
    }

    .form__group {
        position: relative;
    }

    .contact__form input,
    .contact__form textarea {
        width: 100%;
        padding: 15px;
        border: 2px solid #e9ecef;
        border-radius: 12px;
        font-size: 1em;
        transition: all 0.3s ease;
        background: #fff;
        font-family: inherit;
    }

    .contact__form input:focus,
    .contact__form textarea:focus {
        border-color: #3498db;
        outline: none;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        transform: translateY(-2px);
    }

    .contact__form textarea {
        resize: vertical;
        min-height: 130px;
    }

    .contact__form button {
        padding: 15px 30px;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        border: none;
        border-radius: 50px;
        font-size: 1.1em;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        position: relative;
        overflow: hidden;
    }

    .contact__form button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        transition: left 0.5s;
    }

    .contact__form button:hover::before {
        left: 100%;
    }

    .contact__form button:hover {
        background: linear-gradient(135deg, #2980b9, #1f5582);
        transform: translateY(-3px);
        box-shadow: 0 10px 25px rgba(52, 152, 219, 0.4);
    }

    /* Map Section */
    .map__section {
        margin-top: 60px;
        background: white;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    }

    .map__section h3 {
        font-size: 2em;
        color: #2c3e50;
        margin-bottom: 30px;
        font-weight: 600;
        text-align: center;
    }

    .map__container {
        position: relative;
        height: 400px;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .map__container iframe {
        width: 100%;
        height: 100%;
        border: none;
        filter: grayscale(20%);
        transition: filter 0.3s ease;
    }

    .map__container:hover iframe {
        filter: grayscale(0%);
    }

    /* Success and error messages */
    .success,
    .error {
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        text-align: center;
        font-weight: 600;
        font-size: 1.1em;
        animation: slideIn 0.5s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .success {
        background: linear-gradient(135deg, #d4edda, #c3e6cb);
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    .error {
        background: linear-gradient(135deg, #f8d7da, #f5c6cb);
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    /* Social Media Links */
    .social__links {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-top: 30px;
    }

    .social__links a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        border-radius: 50%;
        text-decoration: none;
        transition: all 0.3s ease;
        font-size: 1.2em;
    }

    .social__links a:hover {
        transform: translateY(-3px) scale(1.1);
        box-shadow: 0 10px 25px rgba(52, 152, 219, 0.4);
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .contact__grid {
            grid-template-columns: 1fr;
            gap: 30px;
        }

        .contact__header h2 {
            font-size: 2.2em;
        }

        .contact__info,
        .contact__form__wrapper,
        .map__section {
            padding: 25px;
        }

        .contact__detail {
            flex-direction: column;
            text-align: center;
        }

        .contact__detail i {
            margin-right: 0;
            margin-bottom: 10px;
        }

        .map__container {
            height: 300px;
        }
    }

    @media (max-width: 480px) {
        .contact__container {
            padding: 0 15px;
        }

        .contact__header h2 {
            font-size: 1.8em;
        }

        .contact__info,
        .contact__form__wrapper,
        .map__section {
            padding: 20px;
        }
    }
</style>

<section class="header" id="home">
    <nav>
        <div class="nav__bar">
            <div class="logo">
                <a href="index.php"><img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" /></a>
            </div>
            <div class="nav__menu__btn" id="menu-btn">
                <i class="ri-menu-line"></i>
            </div>
            <ul class="nav__links" id="nav-links">
                <li><a href="index.php#home">Home</a></li>
                <li><a href="index.php#about">About</a></li>
                <li><a href="index.php#service">Services</a></li>
                <li><a href="index.php#explore">Explore</a></li>
                <li><a href="contact.php">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] == 'super_admin'): ?>
                        <li><a href="admin_dashboard.php">Admin Panel</a></li>
                    <?php elseif ($_SESSION['role'] == 'manager'): ?>
                        <li><a href="manager/manager_dashboard.php">Manager Portal</a></li>
                    <?php else: ?>
                        <li><a href="customer_dashboard.php">Dashboard</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
    <div class="section__container header__container">
        <p>Simple - Unique - Friendly</p>
        <h1>Contact Us<br />We'd Love to <span>Hear</span> From You.</h1>
    </div>
</section>

<section class="contact__wrapper">
    <div class="contact__container">
        <div class="contact__header">
            <h2>Get in Touch</h2>
            <p>Have questions about our resort or need assistance with your booking? We're here to help make your stay unforgettable.</p>
        </div>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="contact__grid">
            <!-- Contact Information -->
            <div class="contact__info">
                <h3>Contact Information</h3>
                
                <div class="contact__detail">
                    <i class="ri-map-pin-line"></i>
                    <div class="contact__detail__content">
                        <h4>Address</h4>
                        <p>123 Paradise Beach Road<br>Colombo 07, Western Province<br>Sri Lanka</p>
                    </div>
                </div>

                <div class="contact__detail">
                    <i class="ri-phone-line"></i>
                    <div class="contact__detail__content">
                        <h4>Phone</h4>
                        <p>+94 11 234 5678<br>+94 77 123 4567</p>
                    </div>
                </div>

                <div class="contact__detail">
                    <i class="ri-mail-line"></i>
                    <div class="contact__detail__content">
                        <h4>Email</h4>
                        <p>info@veloresort.com<br>reservations@veloresort.com</p>
                    </div>
                </div>

                <div class="contact__detail">
                    <i class="ri-time-line"></i>
                    <div class="contact__detail__content">
                        <h4>Reception Hours</h4>
                        <p>24/7 Available<br>Check-in: 3:00 PM<br>Check-out: 11:00 AM</p>
                    </div>
                </div>

                <div class="social__links">
                    <a href="#" title="Facebook"><i class="ri-facebook-fill"></i></a>
                    <a href="#" title="Instagram"><i class="ri-instagram-line"></i></a>
                    <a href="#" title="Twitter"><i class="ri-twitter-fill"></i></a>
                    <a href="#" title="WhatsApp"><i class="ri-whatsapp-line"></i></a>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="contact__form__wrapper">
                <h3>Send us a Message</h3>
                <form class="contact__form" action="contact.php" method="POST">
                    <div class="form__group">
                        <input type="text" name="name" placeholder="Your Full Name" value="<?php echo (isset($_POST['name']) && !$success) ? htmlspecialchars($_POST['name']) : ''; ?>" required />
                    </div>
                    
                    <div class="form__group">
                        <input type="email" name="email" placeholder="Your Email Address" value="<?php echo (isset($_POST['email']) && !$success) ? htmlspecialchars($_POST['email']) : ''; ?>" required />
                    </div>
                    
                    <div class="form__group">
                        <input type="text" name="subject" placeholder="Subject" value="<?php echo (isset($_POST['subject']) && !$success) ? htmlspecialchars($_POST['subject']) : ''; ?>" required />
                    </div>
                    
                    <div class="form__group">
                        <textarea name="message" placeholder="Your Message" required><?php echo (isset($_POST['message']) && !$success) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" name="submit_contact">Send Message</button>
                </form>
            </div>
        </div>

        <!-- Map Section -->
        <div class="map__section">
            <h3>Find Us</h3>
            <div class="map__container">
                <!-- Google Maps Embed - Replace with your actual location -->
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3960.798467128226!2d79.8612440147709!3d6.927078994996481!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae259af8d3e8c1d%3A0x5c2e8e5e8c5c5e5e!2sColombo%2007%2C%20Sri%20Lanka!5e0!3m2!1sen!2slk!4v1609459200000!5m2!1sen!2slk"
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div>
        </div>
    </div>
</section>

<?php include 'templates/footer.php'; ?>