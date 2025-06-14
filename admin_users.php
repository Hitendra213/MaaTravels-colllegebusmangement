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
$users = [];
$searchQuery = '';

try {
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=users_export_' . date('Y-m-d_H-i-s') . '.csv');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Name', 'Email', 'Mobile', 'Address', 'Route', 'Enrollment', 'Admin Status', 'Created At']);
        $stmt = $conn->prepare("SELECT id, name, email, mobile, address, route, enrollment, is_admin, created_at FROM users ORDER BY created_at DESC");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($users as $user) {
            fputcsv($output, [
                $user['id'],
                $user['name'],
                $user['email'],
                $user['mobile'],
                $user['address'],
                $user['route'],
                $user['enrollment'],
                $user['is_admin'] ? 'Admin' : 'User',
                $user['created_at']
            ]);
        }
        fclose($output);
        exit;
    }

    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $searchQuery = filter_var(trim($_GET['search']), FILTER_SANITIZE_STRING);
        $stmt = $conn->prepare("SELECT id, name, email, mobile, address, route, enrollment, is_admin, created_at 
                                FROM users 
                                WHERE name LIKE :search OR email LIKE :search OR mobile LIKE :search 
                                ORDER BY created_at DESC");
        $stmt->execute(['search' => "%$searchQuery%"]);
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, mobile, address, route, enrollment, is_admin, created_at 
                                FROM users 
                                ORDER BY created_at DESC");
        $stmt->execute();
    }
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Users fetch error: " . $e->getMessage());
    $errorMessage = "Failed to fetch users. Please try again later.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
        $errorMessage = "Invalid CSRF token.";
    } else {
        $user_id = filter_var($_POST['user_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
        $action = $_POST['action'];

        try {
            if ($action === 'toggle_admin') {
                $is_admin = filter_var($_POST['is_admin'] ?? 0, FILTER_SANITIZE_NUMBER_INT);
                if ($user_id == $_SESSION['admin_id'] && $is_admin == 1) {
                    $errorMessage = "You cannot remove your own admin status.";
                } else {
                    $stmt = $conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
                    $stmt->execute([$is_admin ? 0 : 1, $user_id]);
                    $successMessage = "User admin status updated successfully.";
                }
            } elseif ($action === 'delete') {
                if ($user_id == $_SESSION['admin_id']) {
                    $errorMessage = "You cannot delete your own account.";
                } else {
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $successMessage = "User deleted successfully.";
                }
            } elseif ($action === 'edit') {
                $name = filter_var(trim($_POST['name'] ?? ''), FILTER_SANITIZE_STRING);
                $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
                $mobile = filter_var(trim($_POST['mobile'] ?? ''), FILTER_SANITIZE_STRING);
                $address = filter_var(trim($_POST['address'] ?? ''), FILTER_SANITIZE_STRING);
                $route = filter_var(trim($_POST['route'] ?? ''), FILTER_SANITIZE_STRING);
                $enrollment = filter_var(trim($_POST['enrollment'] ?? ''), FILTER_SANITIZE_STRING);
                $is_admin = (int) filter_var($_POST['is_admin'] ?? 0, FILTER_SANITIZE_NUMBER_INT);

                $errors = [];
                if (empty($name)) $errors[] = "Name is required.";
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
                if (!preg_match("/^[0-9]{10}$/", $mobile)) $errors[] = "Valid 10-digit mobile number is required.";
                if (empty($address)) $errors[] = "Address is required.";
                if (empty($route)) $errors[] = "Route is required.";
                if (!in_array($is_admin, [0, 1])) $errors[] = "Invalid admin status.";
                if ($user_id == $_SESSION['admin_id'] && $is_admin == 0) {
                    $errors[] = "You cannot remove your own admin status.";
                }

                if (empty($errors)) {
                    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetch()) {
                        $errors[] = "Email is already in use by another user.";
                    }
                }

                if (empty($errors)) {
                    $conn->beginTransaction();
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, mobile = ?, address = ?, route = ?, enrollment = ?, is_admin = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $mobile, $address, $route, $enrollment, $is_admin, $user_id]);
                    $conn->commit();
                    $successMessage = "User details updated successfully.";
                } else {
                    $errorMessage = implode(" ", $errors);
                }
            }
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            if ($successMessage) {
                header("Location: admin_users.php?success=" . urlencode($successMessage) . (isset($_GET['search']) ? "&search=" . urlencode($_GET['search']) : ""));
                exit;
            }
        } catch (PDOException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("User action error: " . $e->getMessage());
            if ($e->getCode() == 23000) {
                $errorMessage = "Email is already in use by another user.";
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
    <title>Manage Users - Maa Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .status-admin { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status-user { background: #e9ecef; color: #495057; border: 1px solid #dee2e6; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>
    <main class="container mx-auto py-16 px-4">
        <h1 class="text-3xl font-semibold text-center mb-6 text-gray-800">Manage Users</h1>
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
                <form action="admin_users.php" method="GET" class="flex items-center flex-grow">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="Search by name, email, or mobile..." 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                    <button type="submit" class="ml-2 bg-blue-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-blue-600 transition duration-200">
                        Search
                    </button>
                    <?php if ($searchQuery): ?>
                        <a href="admin_users.php" class="ml-2 bg-gray-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-gray-600 transition duration-200">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
                <a href="admin_users.php?export=csv" class="bg-green-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-green-600 transition duration-200">
                    Export to CSV
                </a>
            </div>
            <?php if (empty($users)): ?>
                <div class="bg-white shadow-lg p-8 rounded-lg text-center">
                    <div class="text-gray-400 text-6xl mb-4">👤</div>
                    <p class="text-xl text-gray-600 mb-2">No users found</p>
                    <p class="text-gray-500"><?php echo $searchQuery ? 'No users match your search.' : 'Users will appear here once registered.'; ?></p>
                </div>
            <?php else: ?>
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b">
                        <h2 class="text-xl font-semibold text-gray-800">Users List</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" role="table">
                            <caption class="sr-only">Users Table</caption>
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mobile</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($user['mobile'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full status-<?php echo $user['is_admin'] ? 'admin' : 'user'; ?>">
                                                <?php echo $user['is_admin'] ? 'Admin' : 'User'; ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('d-m-Y H:i:s', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES, 'UTF-8'); ?>)" 
                                                    class="bg-green-500 hover:bg-green-600 text-white font-bold py-1 px-3 rounded text-xs transition duration-200 mr-2">
                                                Edit
                                            </button>
                                            <form action="admin_users.php" method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="is_admin" value="<?php echo htmlspecialchars($user['is_admin'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" name="action" value="toggle_admin" 
                                                        class="bg-yellow-500 hover:bg-yellow-600 text-white font-bold py-1 px-3 rounded text-xs transition duration-200 mr-2">
                                                    Toggle Admin
                                                </button>
                                            </form>
                                            <form action="admin_users.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" name="action" value="delete" 
                                                        class="bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-3 rounded text-xs transition duration-200">
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
        <!-- Edit Modal -->
        <div id="editModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeEditModal()">×</span>
                <h2 class="text-xl font-semibold mb-4 text-gray-800">Edit User</h2>
                <form id="edit-user-form" action="admin_users.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="mb-4">
                        <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                        <input type="text" id="edit_name" name="name" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                    </div>
                    <div class="mb-4">
                        <label for="edit_email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" id="edit_email" name="email" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                    </div>
                    <div class="mb-4">
                        <label for="edit_mobile" class="block text-sm font-medium text-gray-700 mb-2">Mobile</label>
                        <input type="text" id="edit_mobile" name="mobile" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                    </div>
                    <div class="mb-4">
                        <label for="edit_address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                        <textarea id="edit_address" name="address" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required></textarea>
                    </div>
                    <div class="mb-4">
                        <label for="edit_route" class="block text-sm font-medium text-gray-700 mb-2">Route</label>
                        <input type="text" id="edit_route" name="route" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                    </div>
                    <div class="mb-4">
                        <label for="edit_enrollment" class="block text-sm font-medium text-gray-700 mb-2">Enrollment No.</label>
                        <input type="text" id="edit_enrollment" name="enrollment" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="mb-4">
                        <label for="edit_is_admin" class="block text-sm font-medium text-gray-700 mb-2">Admin Status</label>
                        <select id="edit_is_admin" name="is_admin" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                            <option value="0">User</option>
                            <option value="1">Admin</option>
                        </select>
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
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_name').value = user.name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_mobile').value = user.mobile;
            document.getElementById('edit_address').value = user.address;
            document.getElementById('edit_route').value = user.route;
            document.getElementById('edit_enrollment').value = user.enrollment || '';
            document.getElementById('edit_is_admin').value = user.is_admin;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target === modal) {
                closeEditModal();
            }
        }

        document.getElementById('edit-user-form').addEventListener('submit', function(e) {
            const name = document.getElementById('edit_name').value.trim();
            const email = document.getElementById('edit_email').value.trim();
            const mobile = document.getElementById('edit_mobile').value.trim();
            const address = document.getElementById('edit_address').value.trim();
            const route = document.getElementById('edit_route').value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const mobileRegex = /^[0-9]{10}$/;

            if (!name) {
                e.preventDefault();
                alert('Name is required.');
            } else if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
            } else if (!mobileRegex.test(mobile)) {
                e.preventDefault();
                alert('Please enter a valid 10-digit mobile number.');
            } else if (!address) {
                e.preventDefault();
                alert('Address is required.');
            } else if (!route) {
                e.preventDefault();
                alert('Route is required.');
            }
        });
    </script>
</body>
</html>