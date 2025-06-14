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

if (!isset($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
$successMessage = '';
$routes = [];
$searchQuery = '';

try {
    // Create routes table if not exists
    $conn->exec("CREATE TABLE IF NOT EXISTS routes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $searchQuery = filter_var(trim($_GET['search']), FILTER_SANITIZE_STRING);
        $stmt = $conn->prepare("SELECT id, name, description, created_at 
                                FROM routes 
                                WHERE name LIKE :search 
                                ORDER BY created_at DESC");
        $stmt->execute(['search' => "%$searchQuery%"]);
    } else {
        $stmt = $conn->prepare("SELECT id, name, description, created_at 
                                FROM routes 
                                ORDER BY created_at DESC");
        $stmt->execute();
    }
    $routes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Routes fetch error: " . $e->getMessage());
    $errorMessage = "Failed to fetch routes. Please try again later.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
        $errorMessage = "Invalid CSRF token.";
    } else {
        $action = $_POST['action'];

        try {
            if ($action === 'create') {
                $name = filter_var(trim($_POST['name'] ?? ''), FILTER_SANITIZE_STRING);
                $description = filter_var(trim($_POST['description'] ?? ''), FILTER_SANITIZE_STRING);
                if (empty($name)) {
                    $errorMessage = "Route name is required.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO routes (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    $successMessage = "Route created successfully.";
                }
            } elseif ($action === 'edit') {
                $route_id = filter_var($_POST['route_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
                $name = filter_var(trim($_POST['name'] ?? ''), FILTER_SANITIZE_STRING);
                $description = filter_var(trim($_POST['description'] ?? ''), FILTER_SANITIZE_STRING);
                if (empty($name)) {
                    $errorMessage = "Route name is required.";
                } else {
                    $stmt = $conn->prepare("UPDATE routes SET name = ?, description = ? WHERE id = ?");
                    $stmt->execute([$name, $description, $route_id]);
                    $successMessage = "Route updated successfully.";
                }
            } elseif ($action === 'delete') {
                $route_id = filter_var($_POST['route_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
                $stmt = $conn->prepare("DELETE FROM routes WHERE id = ?");
                $stmt->execute([$route_id]);
                $successMessage = "Route deleted successfully.";
            }
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            header("Location: admin_routes.php?success=" . urlencode($successMessage) . (isset($_GET['search']) ? "&search=" . urlencode($_GET['search']) : ""));
            exit;
        } catch (PDOException $e) {
            error_log("Route action error: " . $e->getMessage());
            if ($e->getCode() == 23000) {
                $errorMessage = "Route name already exists.";
            } else {
                $errorMessage = "Action failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
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
    <title>Manage Routes - Maa Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>
    <main class="container mx-auto py-16 px-4">
        <h1 class="text-3xl font-semibold text-center mb-6 text-gray-800">Manage Routes</h1>
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
            <div class="mb-6">
                <form action="admin_routes.php" method="GET" class="flex items-center">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="Search by route name..." 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                    <button type="submit" class="ml-2 bg-blue-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-blue-600 transition duration-200">
                        Search
                    </button>
                    <?php if ($searchQuery): ?>
                        <a href="admin_routes.php" class="ml-2 bg-gray-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-gray-600 transition duration-200">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <div class="mb-6">
                <button onclick="openCreateModal()" class="bg-green-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-green-600 transition duration-200">
                    Add New Route
                </button>
            </div>
            <?php if (empty($routes)): ?>
                <div class="bg-white shadow-lg p-8 rounded-lg text-center">
                    <div class="text-gray-400 text-6xl mb-4">🛤️</div>
                    <p class="text-xl text-gray-600 mb-2">No routes found</p>
                    <p class="text-gray-500"><?php echo $searchQuery ? 'No routes match your search.' : 'Routes will appear here once added.'; ?></p>
                </div>
            <?php else: ?>
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b">
                        <h2 class="text-xl font-semibold text-gray-800">Routes List</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" role="table">
                            <caption class="sr-only">Routes Table</caption>
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
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
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('d-m-Y H:i:s', strtotime($route['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($route), ENT_QUOTES, 'UTF-8'); ?>)" 
                                                    class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded text-xs transition duration-200 mr-2">
                                                Edit
                                            </button>
                                            <form action="admin_routes.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this route?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="route_id" value="<?php echo htmlspecialchars($route['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded text-xs transition duration-200">
                                                    Delete
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
        <!-- Create Modal -->
        <div id="createModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeCreateModal()">×</span>
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Add New Route</h2>
                <form action="admin_routes.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-4">
                        <label for="create_name" class="block text-sm font-medium text-gray-700 mb-2">Route Name</label>
                        <input type="text" id="create_name" name="name" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                    </div>
                    <div class="mb-4">
                        <label for="create_description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="create_description" name="description" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 h-24"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-500 text-white py-3 rounded-lg hover:bg-blue-600 transition duration-200 font-semibold">
                        Create Route
                    </button>
                </form>
            </div>
        </div>
        <!-- Edit Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeEditModal()">×</span>
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Edit Route</h2>
                <form action="admin_routes.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="route_id" id="edit_route_id">
                    <div class="mb-4">
                        <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-2">Route Name</label>
                        <input type="text" id="edit_name" name="name" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                    </div>
                    <div class="mb-4">
                        <label for="edit_description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                        <textarea id="edit_description" name="description" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 h-24"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-500 text-white py-3 rounded-lg hover:bg-blue-600 transition duration-200 font-semibold">
                        Save Changes
                    </button>
                </form>
            </div>
        </div>
    </main>
    <?php include 'includes/admin_footer.php'; ?>
    <script src="js/scripts.js"></script>
    <script>
        function openCreateModal() {
            document.getElementById('createModal').style.display = 'block';
        }

        function closeCreateModal() {
            document.getElementById('createModal').style.display = 'none';
        }

        function openEditModal(route) {
            document.getElementById('edit_route_id').value = route.id;
            document.getElementById('edit_name').value = route.name;
            document.getElementById('edit_description').value = route.description || '';
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const createModal = document.getElementById('createModal');
            const editModal = document.getElementById('editModal');
            if (event.target === createModal) {
                closeCreateModal();
            } else if (event.target === editModal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>