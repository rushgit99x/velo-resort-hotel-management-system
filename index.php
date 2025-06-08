<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'db_connect.php';

// Fetch room types from the database (for the general room display section)
try {
    $stmt = $pdo->query("SELECT * FROM room_types");
    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $room_types = [];
    $error = "Error fetching room types: " . $e->getMessage();
}

include 'templates/header.php';
?>

<style>
    /* Enhanced styles for the room type availability section */
    .room-type__grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
        padding: 20px 0;
        margin-top: 30px;
    }

    .room-type__card {
        background: #fff;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: 1px solid #f0f0f0;
    }

    .room-type__card:hover {
        transform: translateY(-8px);
        box-shadow: 0 16px 32px rgba(0, 0, 0, 0.15);
    }

    .room-type__image {
        width: 100%;
        height: 200px;
        overflow: hidden;
        position: relative;
    }

    .room-type__image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform 0.3s ease;
    }

    .room-type__card:hover .room-type__image img {
        transform: scale(1.05);
    }

    .room-type__content {
        padding: 20px;
        text-align: center;
    }

    .room-type__card h4 {
        margin: 0 0 10px 0;
        font-size: 1.4em;
        color: #2c3e50;
        font-weight: 600;
    }

    .room-type__description {
        margin: 10px 0;
        font-size: 0.9em;
        color: #666;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .room-type__price {
        margin: 15px 0 10px 0;
        font-size: 1.1em;
        font-weight: 600;
        color: #3498db;
    }

    .room-type__status {
        margin: 15px 0;
        font-size: 1em;
        font-weight: 600;
        padding: 8px 16px;
        border-radius: 20px;
        display: inline-block;
    }

    .room-type__status.available {
        color: #fff;
        background: linear-gradient(135deg, #27ae60, #2ecc71);
    }

    .room-type__status.not-available {
        color: #fff;
        background: linear-gradient(135deg, #c0392b, #e74c3c);
    }

    .room-type__btn {
        display: inline-block;
        margin-top: 15px;
        padding: 12px 24px;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        text-decoration: none;
        border-radius: 25px;
        font-weight: 500;
        transition: all 0.3s ease;
        text-transform: uppercase;
        font-size: 0.9em;
        letter-spacing: 0.5px;
    }

    .room-type__btn:hover {
        background: linear-gradient(135deg, #2980b9, #1f5582);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .room-type__grid {
            grid-template-columns: 1fr;
            gap: 20px;
            padding: 15px 10px;
        }
        
        .room-type__card {
            margin: 0 10px;
        }
        
        .room-type__image {
            height: 180px;
        }
        
        .room-type__content {
            padding: 15px;
        }
    }

    /* Error message styling */
    .error {
        text-align: center;
        color: #e74c3c;
        font-weight: 600;
        margin: 20px 0;
        padding: 15px;
        background: #fdf2f2;
        border: 1px solid #f5c6cb;
        border-radius: 8px;
    }

    /* Availability section title */
    .availability-title {
        text-align: center;
        font-size: 2em;
        color: #2c3e50;
        margin: 30px 0 20px 0;
        font-weight: 600;
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
                <li><a href="#home">Home</a></li>
                <li><a href="#about">About</a></li>
                <li><a href="#service">Services</a></li>
                <li><a href="#explore">Explore</a></li>
                <li><a href="#contact">Contact</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($_SESSION['role'] == 'super_admin'): ?>
                        <li><a href="admin_portal.php">Admin Portal</a></li>
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
        <h1>Make Yourself At Home<br />In Our <span>Hotel</span>.</h1>
    </div>
</section>

<section class="booking">
    <div class="section__container booking__container">
        <form class="booking__form" action="" method="POST">
            <div class="input__group">
                <label for="check-in">CHECK-IN</label>
                <input type="date" id="check-in" name="check_in" value="<?php echo isset($_POST['check_in']) ? htmlspecialchars($_POST['check_in']) : ''; ?>" required />
            </div>
            <div class="input__group">
                <label for="check-out">CHECK-OUT</label>
                <input type="date" id="check-out" name="check_out" value="<?php echo isset($_POST['check_out']) ? htmlspecialchars($_POST['check_out']) : ''; ?>" required />
            </div>
            <div class="input__group">
                <label for="guest">GUEST</label>
                <input type="number" id="guest" name="guest" min="1" value="<?php echo isset($_POST['guest']) ? htmlspecialchars($_POST['guest']) : '1'; ?>" required />
            </div>
            <div class="input__group">
                <label for="branch_id">Branch</label>
                <select id="branch_id" name="branch_id" required>
                    <option value="">Select Branch</option>
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT id, name, location FROM branches");
                        $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($branches as $branch) {
                            $selected = isset($_POST['branch_id']) && $_POST['branch_id'] == $branch['id'] ? 'selected' : '';
                            echo "<option value='" . htmlspecialchars($branch['id']) . "' $selected>" . htmlspecialchars($branch['name'] . ' - ' . $branch['location']) . "</option>";
                        }
                    } catch (PDOException $e) {
                        echo "<option value=''>Error loading branches</option>";
                    }
                    ?>
                </select>
            </div>
            <button type="submit" name="check_availability" class="btn">Check Available Rooms</button>
        </form>

        <?php
        if (isset($_POST['check_availability'])) {
            require_once 'db_connect.php';

            $check_in = $_POST['check_in'];
            $check_out = $_POST['check_out'];
            $guests = (int)$_POST['guest'];
            $branch_id = $_POST['branch_id'] ?? null;

            // Validate dates
            $check_in_date = new DateTime($check_in);
            $check_out_date = new DateTime($check_out);
            $today = new DateTime('2025-06-02 10:13:00+05:30'); // Current date and time

            if ($check_in_date < $today) {
                echo "<p class='error'>Check-in date cannot be in the past.</p>";
            } elseif ($check_in_date >= $check_out_date) {
                echo "<p class='error'>Check-out date must be after check-in date.</p>";
            } elseif (!$branch_id) {
                echo "<p class='error'>Please select a branch.</p>";
            } else {
                try {
                    // Fetch room types and join with rooms to check branch-wise availability
                    $stmt = $pdo->prepare("
                        SELECT rt.*, r.branch_id
                        FROM room_types rt
                        LEFT JOIN rooms r ON rt.id = r.room_type_id
                        WHERE r.branch_id = ?
                        GROUP BY rt.id
                    ");
                    $stmt->execute([$branch_id]);
                    $room_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (empty($room_types)) {
                        echo "<p class='error'>No room types available at the selected branch.</p>";
                    } else {
                        // Find the selected branch name and location
                        $selected_branch = null;
                        foreach ($branches as $branch) {
                            if ($branch['id'] == $branch_id) {
                                $selected_branch = $branch;
                                break;
                            }
                        }

                        echo "<h3 class='availability-title'>Room Type Availability at " . htmlspecialchars($selected_branch['name']) . " - " . htmlspecialchars($selected_branch['location']) . "</h3>";
                        echo "<div class='room-type__grid'>";

                        foreach ($room_types as $room_type) {
                            // Check availability for this room type at the selected branch
                            $stmt = $pdo->prepare("
                                SELECT r.*
                                FROM rooms r
                                WHERE r.room_type_id = ?
                                AND r.branch_id = ?
                                AND r.status = 'available'
                                AND r.id NOT IN (
                                    SELECT room_id
                                    FROM bookings
                                    WHERE status = 'confirmed'
                                    AND (
                                        (check_in <= ? AND check_out > ?)
                                        OR (check_in < ? AND check_out >= ?)
                                        OR (? < check_in AND ? > check_out)
                                    )
                                )
                                LIMIT 1
                            ");
                            $stmt->execute([$room_type['id'], $branch_id, $check_in, $check_in, $check_out, $check_out, $check_in, $check_out]);
                            $available_room = $stmt->fetch(PDO::FETCH_ASSOC);

                            // Determine status
                            $status = $available_room ? "Available" : "Not Available";
                            $status_class = $available_room ? "available" : "not-available";

                            // Display card with image
                            echo "<div class='room-type__card'>";
                            $image_path = $room_type['image_path'] ?? '/hotel_chain_management/assets/images/default-room.jpg';
                            echo "<div class='room-type__image'>";
                            echo "<img src='" . htmlspecialchars($image_path) . "?v=" . time() . "' alt='" . htmlspecialchars($room_type['name']) . "' />";
                            echo "</div>";
                            echo "<div class='room-type__content'>";
                            echo "<h4>" . htmlspecialchars($room_type['name']) . "</h4>";
                            if (!empty($room_type['description'])) {
                                echo "<p class='room-type__description'>" . htmlspecialchars($room_type['description']) . "</p>";
                            }
                            echo "<p class='room-type__price'>Starting from $" . number_format($room_type['base_price'], 2) . "/night</p>";
                            echo "<p class='room-type__status $status_class'>Status: $status</p>";
                            if ($available_room) {
                                echo "<a href='login.php' class='room-type__btn'>Book Now</a>";
                            }
                            echo "</div>";
                            echo "</div>";
                        }

                        echo "</div>";
                    }
                } catch (PDOException $e) {
                    echo "<p class='error'>Error checking availability: " . htmlspecialchars($e->getMessage()) . "</p>";
                }
            }
        }
        ?>
    </div>
</section>

<section class="section__container about__container" id="about">
    <div class="about__image">
        <img src="/hotel_chain_management/assets/images/about.jpg?v=<?php echo time(); ?>" alt="about" />
    </div>
    <div class="about__content">
        <p class="section__subheader">ABOUT US</p>
        <h2 class="section__header">The Best Holidays Start Here!</h2>
        <p class="section__description">
            With a focus on quality accommodations, personalized experiences, and
            seamless booking, our platform is dedicated to ensuring that every
            traveler embarks on their dream holiday with confidence and excitement.
        </p>
        <a href="#" class="btn about__btn">Read More</a>
    </div>
</section>

<section class="section__container room__container">
    <p class="section__subheader">OUR LIVING ROOM</p>
    <h2 class="section__header">The Most Memorable Rest Time Starts Here.</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert--error">
            <i class="ri-error-warning-line"></i>
            <span><?php echo htmlspecialchars($error); ?></span>
        </div>
    <?php endif; ?>
    <div class="room__grid">
        <?php if (empty($room_types)): ?>
            <p>No room types available at the moment.</p>
        <?php else: ?>
            <?php foreach ($room_types as $room_type): ?>
                <div class="room__card">
                    <div class="room__card__image">
                        <img src="<?php echo htmlspecialchars($room_type['image_path'] ?? '/hotel_chain_management/assets/images/default-room.jpg?v=' . time()); ?>" alt="<?php echo htmlspecialchars($room_type['name']); ?>" />
                    </div>
                    <div class="room__card__details">
                        <h4><?php echo htmlspecialchars($room_type['name']); ?></h4>
                        <p><?php echo htmlspecialchars($room_type['description'] ?? 'No description available.'); ?></p>
                        <h5>Starting from <span>$<?php echo number_format($room_type['base_price'], 2); ?>/night</span></h5>
                        <a href="book_room.php?room_type_id=<?php echo $room_type['id']; ?>" class="btn">Book Now</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<section class="service" id="service">
    <div class="section__container service__container">
        <div class="service__content">
            <p class="section__subheader">SERVICES</p>
            <h2 class="section__header">Strive Only For The Best.</h2>
            <ul class="service__list">
                <li>
                    <span><i class="ri-shield-star-line"></i></span>
                    High Class Security
                </li>
                <li>
                    <span><i class="ri-24-hours-line"></i></span>
                    24 Hours Room Service
                </li>
                <li>
                    <span><i class="ri-headphone-line"></i></span>
                    Conference Room
                </li>
                <li>
                    <span><i class="ri-map-2-line"></i></span>
                    Tourist Guide Support
                </li>
            </ul>
        </div>
    </div>
</section>

<section class="section__container banner__container">
    <div class="banner__content">
        <div class="banner__card">
            <h4>25+</h4>
            <p>Properties Available</p>
        </div>
        <div class="banner__card">
            <h4>350+</h4>
            <p>Bookings Completed</p>
        </div>
        <div class="banner__card">
            <h4>600+</h4>
            <p>Happy Customers</p>
        </div>
    </div>
</section>

<section class="explore" id="explore">
    <p class="section__subheader">EXPLORE</p>
    <h2 class="section__header">What's New Today.</h2>
    <div class="explore__bg">
        <div class="explore__content">
            <p>10th MAR 2025</p>
            <h4>A New Menu Is Available In Our Hotel.</h4>
            <a href="#" class="btn">Continue</a>
        </div>
    </div>
</section>

<section class="section__container footer__container" id="contact">
    <div class="footer__col">
        <img src="/hotel_chain_management/assets/images/logo.png?v=<?php echo time(); ?>" alt="logo" class="logo" />
        <p class="section__description">
            Discover a world of comfort, luxury, and adventure as you explore
            our curated selection of hotels, making every moment of your getaway
            truly extraordinary.
        </p>
        <a href="login.php" class="btn">Book Now</a>
    </div>
    <div class="footer__col">
        <h4>QUICK LINKS</h4>
        <ul class="footer__links">
            <li><a href="#">Browse Destinations</a></li>
            <li><a href="#">Special Offers & Packages</a></li>
            <li><a href="#">Room Types & Amenities</a></li>
            <li><a href="#">Customer Reviews & Ratings</a></li>
            <li><a href="#">Travel Tips & Guides</a></li>
        </ul>
    </div>
    <div class="footer__col">
        <h4>OUR SERVICES</h4>
        <ul class="footer__links">
            <li><a href="#">Concierge Assistance</a></li>
            <li><a href="#">Flexible Booking Options</a></li>
            <li><a href="#">Airport Transfers</a></li>
            <li><a href="#">Wellness & Recreation</a></li>
        </ul>
    </div>
    <div class="footer__col">
        <h4>CONTACT US</h4>
        <ul class="footer__links">
            <li><a href="mailto:info@hotelchain.com">info@hotelchain.com</a></li>
        </ul>
        <div class="footer__socials">
            <a href="https://web.facebook.com/"><img src="/hotel_chain_management/assets/images/facebook.png?v=<?php echo time(); ?>" alt="facebook" /></a>
            <a href="https://www.instagram.com/"><img src="/hotel_chain_management/assets/images/instagram.png?v=<?php echo time(); ?>" alt="instagram" /></a>
            <a href="https://x.com/"><img src="/hotel_chain_management/assets/images/twitter.png?v=<?php echo time(); ?>" alt="X" /></a>
            <a href="https://lk.linkedin.com/"><img src="/hotel_chain_management/assets/images/linkedin.png?v=<?php echo time(); ?>" alt="linkedin" /></a>
        </div>
    </div>
</section>

<div class="footer__bar">
    Copyright © 2025. All rights reserved.
</div>

<?php include 'templates/footer.php'; ?>