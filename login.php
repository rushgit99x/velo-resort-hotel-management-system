<?php
// Include the auth processing at the top
if (!file_exists('includes/config.php')) {
    die("Error: includes/config.php not found.");
}
if (!file_exists('includes/functions.php')) {
    die("Error: includes/functions.php not found.");
}
include_once 'includes/config.php';
include_once 'includes/functions.php';

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    switch ($_SESSION['role']) {
        case 'super_admin':
            header("Location: admin_dashboard.php");
            break;
        case 'manager':
            header("Location: manager_dashboard.php");
            break;
        case 'customer':
            header("Location: customer_dashboard.php");
            break;
        case 'travel_company':
            header("Location: travel_company_dashboard.php");
            break;
        case 'clerk':
            header("Location: clerk_dashboard.php");
            break;
        default:
            header("Location: index.php");
    }
    exit;
}

// Process login
$error_message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];

    if (!validateEmail($email)) {
        $error_message = "Invalid email format.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                error_log("Login successful for user: " . $email . ", role: " . $user['role']);
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'super_admin':
                        header("Location: admin_dashboard.php");
                        break;
                    case 'manager':
                        header("Location: manager_dashboard.php");
                        break;
                    case 'customer':
                        header("Location: customer_dashboard.php");
                        break;
                    case 'travel_company':
                        header("Location: travel_company_dashboard.php");
                        break;
                    case 'clerk':
                        header("Location: clerk_dashboard.php");
                        break;
                    default:
                        error_log("Unknown role: " . $user['role']);
                        $error_message = "Invalid user role.";
                }
                if (empty($error_message)) {
                    exit;
                }
            } else {
                error_log("Login failed for user: " . $email);
                $error_message = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error_message = "Database error: Unable to process login.";
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VELO Resort & Spa - Login</title>
</head>
<body>

<style>
    body {
        margin: 0;
        font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #e91e63 0%, #f06292 50%, #e91e63 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow-x: hidden;
        padding: 1rem;
        box-sizing: border-box;
    }

    /* Background decoration */
    body::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" patternUnits="userSpaceOnUse" width="100" height="100"><circle cx="20" cy="20" r="1" fill="white" opacity="0.1"/><circle cx="80" cy="40" r="1" fill="white" opacity="0.1"/><circle cx="40" cy="80" r="1" fill="white" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
        pointer-events: none;
    }

    .background-elements {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        overflow: hidden;
        pointer-events: none;
    }

    .floating-icon {
        position: absolute;
        opacity: 0.1;
        animation: floatSlow 20s ease-in-out infinite;
    }

    .floating-icon:nth-child(1) {
        top: 10%;
        left: 10%;
        animation-delay: 0s;
    }

    .floating-icon:nth-child(2) {
        top: 20%;
        right: 15%;
        animation-delay: 5s;
    }

    .floating-icon:nth-child(3) {
        bottom: 30%;
        left: 20%;
        animation-delay: 10s;
    }

    .floating-icon:nth-child(4) {
        bottom: 15%;
        right: 10%;
        animation-delay: 15s;
    }

    @keyframes floatSlow {
        0%, 100% { transform: translateY(0px) rotate(0deg); }
        25% { transform: translateY(-20px) rotate(5deg); }
        50% { transform: translateY(-40px) rotate(0deg); }
        75% { transform: translateY(-20px) rotate(-5deg); }
    }

    .login-container {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 1.5rem;
        width: 100%;
        max-width: 380px;
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15), 
                    0 0 0 1px rgba(255, 255, 255, 0.2);
        position: relative;
        animation: slideUp 0.6s ease-out;
        max-height: 95vh;
        overflow-y: auto;
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .hotel-logo {
        text-align: center;
        margin-bottom: 1.5rem;
        position: relative;
    }

    .logo-icon {
        width: 60px;
        height: 60px;
        margin: 0 auto 0.75rem;
        padding: 15px;
        background: linear-gradient(135deg, rgba(233, 30, 99, 0.1), rgba(240, 98, 146, 0.1));
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(10px);
        border: 2px solid rgba(233, 30, 99, 0.2);
        animation: float 3s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50% { transform: translateY(-8px); }
    }

    .hotel-name {
        font-size: 2rem;
        font-weight: 700;
        background: linear-gradient(135deg, #e91e63, #f06292);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        margin-bottom: 0.25rem;
        letter-spacing: -0.5px;
    }

    .hotel-tagline {
        color: #6b7280;
        font-size: 0.85rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 1.5px;
    }

    .login-title {
        font-size: 1.5rem;
        font-weight: 600;
        text-align: center;
        color: #1f2937;
        margin-bottom: 1.5rem;
    }

    .error-message {
        background: linear-gradient(135deg, #fce4ec, #f8bbd9);
        color: #ad1457;
        padding: 0.75rem;
        border-radius: 10px;
        margin-bottom: 1rem;
        border: 1px solid #f48fb1;
        font-weight: 500;
        font-size: 0.9rem;
        animation: shake 0.5s ease-in-out;
        display: block;
    }

    .error-message.hidden {
        display: none;
    }

    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }

    .form-group {
        margin-bottom: 1.25rem;
        position: relative;
    }

    .form-label {
        display: block;
        color: #374151;
        font-weight: 600;
        margin-bottom: 0.4rem;
        font-size: 0.9rem;
    }

    .form-input {
        width: 100%;
        padding: 0.875rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.8);
        box-sizing: border-box;
    }

    .form-input:focus {
        outline: none;
        border-color: #e91e63;
        box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        background: white;
        transform: translateY(-1px);
    }

    .form-input:hover {
        border-color: #d1d5db;
    }

    .login-button {
        width: 100%;
        padding: 1rem;
        background: linear-gradient(135deg, #e91e63, #f06292);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: relative;
        overflow: hidden;
    }

    .login-button::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .login-button:hover::before {
        left: 100%;
    }

    .login-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(233, 30, 99, 0.4);
    }

    .login-button:active {
        transform: translateY(0);
    }

    .links-section {
        margin-top: 1.5rem;
        text-align: center;
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }

    .auth-link {
        display: block;
        margin: 0.5rem 0;
        color: #6b7280;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        padding: 0.4rem;
        border-radius: 6px;
    }

    .auth-link:hover {
        color: #e91e63;
        background: rgba(233, 30, 99, 0.05);
        transform: translateX(2px);
    }

    .register-link {
        color: #e91e63;
    }

    /* Mobile optimizations */
    @media (max-width: 480px) {
        body {
            padding: 0.5rem;
        }
        
        .login-container {
            padding: 1.25rem;
            border-radius: 16px;
            max-width: 100%;
        }
        
        .hotel-name {
            font-size: 1.75rem;
        }
        
        .login-title {
            font-size: 1.25rem;
        }
        
        .logo-icon {
            width: 50px;
            height: 50px;
            padding: 12px;
        }
        
        .form-input {
            padding: 0.75rem;
        }
        
        .login-button {
            padding: 0.875rem;
        }
    }

    /* Very small screens */
    @media (max-height: 600px) {
        .login-container {
            padding: 1rem;
        }
        
        .hotel-logo {
            margin-bottom: 1rem;
        }
        
        .login-title {
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .logo-icon {
            width: 45px;
            height: 45px;
            margin-bottom: 0.5rem;
        }
        
        .hotel-name {
            font-size: 1.5rem;
        }
        
        .hotel-tagline {
            font-size: 0.75rem;
        }
    }

    /* Loading animation for form submission */
    .login-button.loading {
        background: #9ca3af;
        cursor: not-allowed;
    }

    .login-button.loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 18px;
        height: 18px;
        margin: -9px 0 0 -9px;
        border: 2px solid transparent;
        border-top: 2px solid white;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>

<div class="background-elements">
    <!-- Floating Hotel Icons -->
    <svg class="floating-icon" width="60" height="60" viewBox="0 0 24 24" fill="white">
        <path d="M7 13c1.66 0 3-1.34 3-3S8.66 7 7 7s-3 1.34-3 3 1.34 3 3 3zm12-6h-8v7H3V6H1v15h2v-3h18v3h2v-9c0-2.21-1.79-4-4-4z"/>
    </svg>
    <svg class="floating-icon" width="50" height="50" viewBox="0 0 24 24" fill="white">
        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
    </svg>
    <svg class="floating-icon" width="55" height="55" viewBox="0 0 24 24" fill="white">
        <path d="M19 7h-3V6a4 4 0 0 0-8 0v1H5a1 1 0 0 0-1 1v11a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3V8a1 1 0 0 0-1-1zM10 6a2 2 0 0 1 4 0v1h-4V6zm8 13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V9h2v1a1 1 0 0 0 2 0V9h4v1a1 1 0 0 0 2 0V9h2v10z"/>
    </svg>
    <svg class="floating-icon" width="45" height="45" viewBox="0 0 24 24" fill="white">
        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
    </svg>
</div>

<div class="login-container">
    <div class="hotel-logo">
        <div class="logo-icon">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#e91e63" stroke-width="2">
                <path d="M3 21h18"/>
                <path d="M5 21V7l8-4v18"/>
                <path d="M19 21V11l-6-4"/>
                <path d="M9 9v.01"/>
                <path d="M9 12v.01"/>
                <path d="M9 15v.01"/>
                <path d="M9 18v.01"/>
            </svg>
        </div>
        <h1 class="hotel-name">VELO</h1>
        <p class="hotel-tagline">Resort & Spa</p>
    </div>
    
    <h2 class="login-title">Welcome Back</h2>
    
    <?php if (!empty($error_message)): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="loginForm">
        <div class="form-group">
            <label for="email" class="form-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 6px;">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                Email Address
            </label>
            <input type="email" id="email" name="email" class="form-input" required 
                   placeholder="Enter your email address" 
                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="password" class="form-label">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display: inline-block; margin-right: 6px;">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <circle cx="12" cy="16" r="1"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
                Password
            </label>
            <input type="password" id="password" name="password" class="form-input" required 
                   placeholder="Enter your password">
        </div>
        
        <button type="submit" name="login" class="login-button" id="loginBtn">
            Sign In to Your Account
        </button>
    </form>
    
    <div class="links-section">
        <a href="register.php" class="auth-link register-link">
            Don't have an account? Create one here →
        </a>
        <!-- <a href="forgot_password.php" class="auth-link">
            Forgot your password?
        </a> -->
    </div>
</div>

<script>
// Add loading state on form submission
document.getElementById('loginForm').addEventListener('submit', function() {
    const loginBtn = document.getElementById('loginBtn');
    loginBtn.classList.add('loading');
    loginBtn.textContent = 'Signing In...';
});

// Add subtle animations on input focus
document.querySelectorAll('.form-input').forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.style.transform = 'translateY(-1px)';
        this.parentElement.style.transition = 'transform 0.3s ease';
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.style.transform = 'translateY(0)';
    });
});

// Auto-resize for very small screens
function adjustForScreenHeight() {
    const vh = window.innerHeight;
    const container = document.querySelector('.login-container');
    
    if (vh < 600) {
        container.style.maxHeight = '95vh';
        container.style.overflowY = 'auto';
    }
}

window.addEventListener('resize', adjustForScreenHeight);
adjustForScreenHeight();

// Clear form on successful redirect (prevents back button issues)
if (window.performance && window.performance.navigation.type === window.performance.navigation.TYPE_BACK_FORWARD) {
    document.getElementById('loginForm').reset();
}
</script>

</body>
</html>