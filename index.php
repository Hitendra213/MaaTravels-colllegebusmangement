<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maa Travels - Explore the World with Us</title>
    <meta name="description" content="Maa Travels offers reliable and affordable travel solutions including flight bookings, hotel reservations, and curated tour packages. Join us for your next adventure.">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>

<body class="bg-white text-gray-800 leading-relaxed">
    <?php include 'includes/header.php'; ?>

    <?php
    // Show login success message
    if (isset($_GET['success'])) {
        echo '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 text-center">';
        echo htmlspecialchars($_GET['success']);
        echo '</div>';
    }
    ?>

    <main>
        <!-- Hero Section -->
        <section class="bg-gradient-to-r from-blue-700 to-blue-900 text-white py-20 text-center hero-bg">
            <div class="container mx-auto px-4">
                <h1 class="text-4xl md:text-5xl font-bold mb-4">Discover the World with Maa Travels</h1>
                <?php if (isset($_SESSION['user_name'])): ?>
                    <p class="text-xl mb-6">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! Let's plan your next unforgettable journey.</p>
                <?php else: ?>
                    <p class="text-lg mb-6">Your adventure begins here. Book flights, hotels, and tours effortlessly.</p>
                <?php endif; ?>
                <div class="space-x-4">
                    <a href="contact.php"
                        class="bg-yellow-500 text-blue-900 font-semibold py-3 px-6 rounded-lg hover:bg-yellow-400 transition duration-200">Get
                        Started</a>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="register.php"
                            class="bg-transparent border-2 border-white text-white font-semibold py-3 px-6 rounded-lg hover:bg-white hover:text-blue-800 transition duration-200">Join
                            Us</a>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Services Section -->
        <section class="container mx-auto py-16 px-4">
            <h2 class="text-3xl font-bold text-center mb-12 text-gray-800">Our Core Services</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="p-6 bg-white shadow-lg rounded-lg text-center hover:shadow-xl transition duration-200">
                    <div class="text-blue-500 text-4xl mb-4">✈️</div>
                    <h3 class="text-xl font-semibold mb-3">Flight Booking</h3>
                    <p>Access the best airline deals and fly to your dream destinations with ease.</p>
                </div>
                <div class="p-6 bg-white shadow-lg rounded-lg text-center hover:shadow-xl transition duration-200">
                    <div class="text-blue-500 text-4xl mb-4">🏨</div>
                    <h3 class="text-xl font-semibold mb-3">Hotel Reservations</h3>
                    <p>Find comfort anywhere—from budget inns to five-star luxury stays, we’ve got you covered.</p>
                </div>
                <div class="p-6 bg-white shadow-lg rounded-lg text-center hover:shadow-xl transition duration-200">
                    <div class="text-blue-500 text-4xl mb-4">🎒</div>
                    <h3 class="text-xl font-semibold mb-3">Tour Packages</h3>
                    <p>Explore handcrafted tour experiences designed for every type of traveler.</p>
                </div>
            </div>
        </section>

        <!-- New: Why Choose Us Section -->
        <section class="bg-blue-50 py-16 px-4">
            <div class="container mx-auto text-center">
                <h2 class="text-3xl font-bold mb-8 text-blue-800">Why Choose Maa Travels?</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                    <div class="p-6 bg-white rounded-lg shadow hover:shadow-lg transition">
                        <h3 class="text-xl font-semibold mb-2 text-blue-700">Trusted by Thousands</h3>
                        <p>We’ve successfully served over 20,000 happy travelers across the country.</p>
                    </div>
                    <div class="p-6 bg-white rounded-lg shadow hover:shadow-lg transition">
                        <h3 class="text-xl font-semibold mb-2 text-blue-700">24/7 Customer Support</h3>
                        <p>Have a query or emergency? Our team is always ready to assist you.</p>
                    </div>
                    <div class="p-6 bg-white rounded-lg shadow hover:shadow-lg transition">
                        <h3 class="text-xl font-semibold mb-2 text-blue-700">Best Price Guarantee</h3>
                        <p>Enjoy premium travel experiences without breaking your budget.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA for Guests -->
        <?php if (!isset($_SESSION['user_id'])): ?>
            <section class="bg-gray-100 py-16">
                <div class="container mx-auto text-center px-4">
                    <h2 class="text-3xl font-semibold mb-6 text-gray-800">Join Maa Travels Today</h2>
                    <p class="text-lg text-gray-600 mb-8">Sign up now and unlock exclusive travel offers, early bird deals, and more.</p>
                    <div class="space-x-4">
                        <a href="register.php"
                            class="bg-blue-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-blue-600 transition duration-200">Register Now</a>
                        <a href="login.php"
                            class="bg-gray-300 text-gray-800 font-semibold py-3 px-6 rounded-lg hover:bg-gray-400 transition duration-200">Login</a>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <?php include 'includes/footer.php'; ?>
    <script src="js/scripts.js"></script>
</body>

</html>
