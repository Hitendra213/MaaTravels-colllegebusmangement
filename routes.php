<?php
header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
session_start();
require 'includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header("Location: login.php?error=" . urlencode("Please login to access this page."));
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
$successMessage = '';
$routes = [];
$searchQuery = '';

try {
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $searchQuery = filter_var(trim($_GET['search']), FILTER_SANITIZE_STRING);
        $stmt = $conn->prepare("SELECT id, name, description, created_at FROM routes WHERE name LIKE :search ORDER BY name ASC");
        $stmt->execute(['search' => "%$searchQuery%"]);
    } else {
        $stmt = $conn->prepare("SELECT id, name, description, created_at FROM routes ORDER BY name ASC");
        $stmt->execute();
    }
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch user's current route
    $stmt = $conn->prepare("SELECT route FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentRoute = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Routes fetch error: " . $e->getMessage());
    $errorMessage = "Failed to fetch routes. Please try again later.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'select_route') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid CSRF token.";
    } else {
        $route_id = filter_var($_POST['route_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
        try {
            $stmt = $conn->prepare("SELECT name FROM routes WHERE id = ?");
            $stmt->execute([$route_id]);
            $route_name = $stmt->fetchColumn();
            if ($route_name) {
                $stmt = $conn->prepare("UPDATE users SET route = ? WHERE id = ?");
                $stmt->execute([$route_name, $_SESSION['user_id']]);
                $successMessage = "Route selected successfully.";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header("Location: routes.php?success=" . urlencode($successMessage));
                exit;
            } else {
                $errorMessage = "Invalid route selected.";
            }
        } catch (PDOException $e) {
            error_log("Route selection error: " . $e->getMessage());
            $errorMessage = "Failed to select route. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Select Route - Maa Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>
    <main class="container mx-auto py-16 px-4">
        <h1 class="text-3xl font-semibold text-center mb-6 text-gray-800">Select Your Travel Route</h1>
        <div class="max-w-6xl mx-auto">
            <?php if ($errorMessage): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6" role="alert">
                    <p class="font-bold">Error:</p>
                    <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['success'])): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-6" role="alert">
                    <p class="font-bold">Success:</p>
                    <p><?php echo htmlspecialchars($_GET['success'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            <div class="mb-6 flex justify-between items-center">
                <p class="text-lg font-semibold text-gray-800">Current Route: <span class="text-blue-600"><?php echo htmlspecialchars($currentRoute ?: 'None', ENT_QUOTES, 'UTF-8'); ?></span></p>
                <form action="routes.php" method="GET" class="flex items-center">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="Search by route name..." 
                           class="p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                    <button type="submit" class="ml-2 bg-blue-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-blue-600 transition duration-200">
                        Search
                    </button>
                    <?php if ($searchQuery): ?>
                        <a href="routes.php" class="ml-2 bg-gray-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-gray-600 transition duration-200">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <?php if (empty($routes)): ?>
                <div class="bg-white shadow-lg p-8 rounded-lg text-center">
                    <div class="text-gray-400 text-6xl mb-4">🛤️</div>
                    <p class="text-xl text-gray-600 mb-2">No routes available</p>
                    <p class="text-gray-500">Please check back later or contact support.</p>
                </div>
            <?php else: ?>
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b">
                        <h2 class="text-xl font-semibold text-gray-800">Available Routes</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" role="table">
                            <caption class="sr-only">Routes Table</caption>
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($routes as $route): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($route['id'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($route['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <?php echo htmlspecialchars($route['description'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <form action="routes.php" method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="route_id" value="<?php echo htmlspecialchars($route['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="select_route">
                                                <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded text-xs transition duration-200 <?php echo $currentRoute === $route['name'] ? 'opacity-50 cursor-not-allowed' : ''; ?>" 
                                                        <?php echo $currentRoute === $route['name'] ? 'disabled' : ''; ?>>
                                                    <?php echo $currentRoute === $route['name'] ? 'Selected' : 'Select'; ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="js/scripts.js"></script>
</body>
</html>