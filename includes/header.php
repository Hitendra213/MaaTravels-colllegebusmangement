<?php
session_start();
?>
<header class="navbar fixed top-0 w-full bg-white shadow-md z-50">
    <div class="container mx-auto flex justify-between items-center py-4 px-6">
        <a href="index.php" class="flex items-center">
            <img src="assets/images/logo.png" alt="Maa Travels Logo" class="h-10">
            <span class="text-2xl font-bold text-blue-600 ml-2">Maa Travels</span>
        </a>
        <nav class="space-x-6">
            <a href="index.php" class="text-blue-600 hover:text-blue-800">Home</a>
            <a href="about.php" class="text-blue-600 hover:text-blue-800">About</a>
            <a href="contact.php" class="text-blue-600 hover:text-blue-800">Contact</a>
            <a href="routes.php" class="text-blue-600 hover:text-blue-800">Routes</a>
            <a href="payment.php" class="text-blue-600 hover:text-blue-800">Payment</a>
            <a href="receipt.php" class="text-blue-600 hover:text-blue-800">Receipts</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="profile.php" class="text-blue-600 hover:text-blue-800">Profile</a>
                <a href="logout.php" class="text-blue-600 hover:text-blue-800">Logout</a>
            <?php else: ?>
                <a href="login.php" class="text-blue-600 hover:text-blue-800">Login</a>
                <a href="register.php" class="text-blue-600 hover:text-blue-800">Register</a>
            <?php endif; ?>
        </nav>
    </div>
</header>