<?php
header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
session_start();
require 'includes/config.php';

if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_email'])) {
    header("Location: admin_login.php?error=" . urlencode("Please login as admin to access this page."));
    exit;
}

$errorMessage = '';
$stats = [];
$recentActivity = [];

try {
    // Fetch total users
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetchColumn();

    // Fetch total payments
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM payments");
    $stmt->execute();
    $stats['total_payments'] = $stmt->fetchColumn();

    // Fetch pending payments
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM payments WHERE status = 'pending'");
    $stmt->execute();
    $stats['pending_payments'] = $stmt->fetchColumn();

    // Fetch recent contacts
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM contacts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute();
    $stats['recent_contacts'] = $stmt->fetchColumn();

    // Fetch recent activity (last 5 actions)
    $stmt = $conn->prepare("
        SELECT 'user' as type, id, name as description, created_at FROM users
        UNION
        SELECT 'payment' as type, id, CONCAT('Payment of INR ', amount, ' by ', email) as description, created_at FROM payments
        UNION
        SELECT 'contact' as type, id, CONCAT('Contact from ', name) as description, created_at FROM contacts
        ORDER BY created_at DESC LIMIT 5
    ");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Dashboard fetch error: " . $e->getMessage());
    $errorMessage = "Failed to load dashboard data. Please try again later.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Dashboard - Maa Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>
    <main class="container mx-auto py-16 px-4">
        <h1 class="text-3xl font-semibold text-center mb-6 text-gray-800">Admin Dashboard</h1>
        <div class="max-w-6xl mx-auto">
            <?php if ($errorMessage): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6" role="alert">
                    <p class="font-bold">Error:</p>
                    <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white shadow-lg rounded-lg p-6 text-center">
                    <h3 class="text-lg font-semibold text-gray-800">Total Users</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo htmlspecialchars($stats['total_users'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="bg-white shadow-lg rounded-lg p-6 text-center">
                    <h3 class="text-lg font-semibold text-gray-800">Total Payments</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo htmlspecialchars($stats['total_payments'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="bg-white shadow-lg rounded-lg p-6 text-center">
                    <h3 class="text-lg font-semibold text-gray-800">Pending Payments</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo htmlspecialchars($stats['pending_payments'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
                <div class="bg-white shadow-lg rounded-lg p-6 text-center">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Contacts</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo htmlspecialchars($stats['recent_contacts'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b">
                    <h2 class="text-xl font-semibold text-gray-800">Recent Activity</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" role="table">
                        <caption class="sr-only">Recent Activity Table</caption>
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recentActivity as $activity): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?php echo ucfirst(htmlspecialchars($activity['type'], ENT_QUOTES, 'UTF-8')); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo htmlspecialchars($activity['description'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        <?php echo date('d-m-Y H:i:s', strtotime($activity['created_at'])); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <?php include 'includes/admin_footer.php'; ?>
    <script src="js/scripts.js"></script>
</body>
</html>