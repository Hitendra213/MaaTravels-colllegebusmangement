<?php
session_start();
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header("Location: admin_login.php?error=" . urlencode("Please login as admin to access this page."));
    exit;
}
?>
<header class="navbar fixed top-0 w-full bg-white shadow-md z-50">
    <div class="container mx-auto flex justify-between items-center py-4 px-6">
        <a href="admin_dashboard.php" class="flex items-center">
            <img src="assets/images/logo.png" alt="Maa Travels Logo" class="h-10">
            <span class="text-2xl font-bold text-blue-600 ml-2">Maa Travels - Admin</span>
        </a>
        <nav class="space-x-6">
            <a href="admin_dashboard.php" class="text-blue-600 hover:text-blue-800">Dashboard</a>
            <a href="admin_users.php" class="text-blue-600 hover:text-blue-800">Users</a>
            <a href="admin_payments.php" class="text-blue-600 hover:text-blue-800">Payments</a>
            <a href="admin_contacts.php" class="text-blue-600 hover:text-blue-800">Contacts</a>
            <a href="admin_routes.php" class="text-blue-600 hover:text-blue-800">Routes</a>
            <a href="admin_profile.php" class="text-blue-600 hover:text-blue-800">Profile</a>
            <a href="logout.php" class="text-blue-600 hover:text-blue-800">Logout</a>
        </nav>
    </div>
</header>