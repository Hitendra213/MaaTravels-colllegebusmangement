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
$contacts = [];
$searchQuery = '';

try {
    if (isset($_GET['search']) && !empty(trim($_GET['search']))) {
        $searchQuery = filter_var(trim($_GET['search']), FILTER_SANITIZE_STRING);
        $stmt = $conn->prepare("SELECT id, name, email, subject, message, created_at 
                                FROM contacts 
                                WHERE name LIKE :search OR email LIKE :search 
                                ORDER BY created_at DESC");
        $stmt->execute(['search' => "%$searchQuery%"]);
    } else {
        $stmt = $conn->prepare("SELECT id, name, email, subject, message, created_at 
                                FROM contacts 
                                ORDER BY created_at DESC");
        $stmt->execute();
    }
    $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Contacts fetch error: " . $e->getMessage());
    $errorMessage = "Failed to fetch contacts. Please try again later.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['admin_csrf_token']) {
        $errorMessage = "Invalid CSRF token.";
    } else {
        $contact_id = filter_var($_POST['contact_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
        $action = $_POST['action'];

        try {
            if ($action === 'delete') {
                $stmt = $conn->prepare("DELETE FROM contacts WHERE id = ?");
                $stmt->execute([$contact_id]);
                $successMessage = "Contact message deleted successfully.";
            }
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
            header("Location: admin_contacts.php?success=" . urlencode($successMessage) . (isset($_GET['search']) ? "&search=" . urlencode($_GET['search']) : ""));
            exit;
        } catch (PDOException $e) {
            error_log("Contact action error: " . $e->getMessage());
            $errorMessage = "Action failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
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
    <title>Manage Contacts - Maa Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fff; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #000; }
        .truncate-message { display: inline-block; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'includes/admin_header.php'; ?>
    <main class="container mx-auto py-16 px-4">
        <h1 class="text-3xl font-semibold text-center mb-6 text-gray-800">Manage Contacts</h1>
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
                <form action="admin_contacts.php" method="GET" class="flex items-center">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8'); ?>" 
                           placeholder="Search by name or email..." 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                    <button type="submit" class="ml-2 bg-blue-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-blue-600 transition duration-200">
                        Search
                    </button>
                    <?php if ($searchQuery): ?>
                        <a href="admin_contacts.php" class="ml-2 bg-gray-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-gray-600 transition duration-200">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>
            <?php if (empty($contacts)): ?>
                <div class="bg-white shadow-lg p-8 rounded-lg text-center">
                    <div class="text-gray-400 text-6xl mb-4">📧</div>
                    <p class="text-xl text-gray-600 mb-2">No contact messages found</p>
                    <p class="text-gray-500"><?php echo $searchQuery ? 'No messages match your search.' : 'Contact messages will appear here once submitted.'; ?></p>
                </div>
            <?php else: ?>
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b">
                        <h2 class="text-xl font-semibold text-gray-800">Contact Messages</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" role="table">
                            <caption class="sr-only">Contacts Table</caption>
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subject</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Submitted At</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($contacts as $contact): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($contact['id'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($contact['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo htmlspecialchars($contact['subject'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900">
                                            <span class="truncate-message" title="<?php echo htmlspecialchars($contact['message'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars(substr($contact['message'], 0, 50), ENT_QUOTES, 'UTF-8'); ?>
                                                <?php if (strlen($contact['message']) > 50): ?>...<?php endif; ?>
                                            </span>
                                            <button onclick="openViewModal(<?php echo htmlspecialchars(json_encode($contact), ENT_QUOTES, 'UTF-8'); ?>)" 
                                                    class="ml-2 text-blue-500 hover:text-blue-700 text-xs font-semibold">
                                                View
                                            </button>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('d-m-Y H:i:s', strtotime($contact['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <form action="admin_contacts.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this contact message?');">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="contact_id" value="<?php echo htmlspecialchars($contact['id'], ENT_QUOTES, 'UTF-8'); ?>">
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
        <!-- View Message Modal -->
        <div id="viewModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeViewModal()">×</span>
                <h2 class="text-xl font-semibold mb-4 text-gray-800">View Message</h2>
                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-700">Name: <span id="view_name" class="font-normal"></span></p>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-700">Email: <span id="view_email" class="font-normal"></span></p>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-700">Subject: <span id="view_subject" class="font-normal"></span></p>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-700">Message:</p>
                    <p id="view_message" class="text-sm text-gray-900 whitespace-pre-wrap"></p>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-700">Submitted At: <span id="view_created_at" class="font-normal"></span></p>
                </div>
                <button onclick="closeViewModal()" class="w-full bg-blue-500 text-white py-3 rounded-lg hover:bg-blue-600 transition duration-200 font-semibold">
                    Close
                </button>
            </div>
        </div>
    </main>
    <?php include 'includes/admin_footer.php'; ?>
    <script src="js/scripts.js"></script>
    <script>
        function openViewModal(contact) {
            document.getElementById('view_name').textContent = contact.name;
            document.getElementById('view_email').textContent = contact.email;
            document.getElementById('view_subject').textContent = contact.subject;
            document.getElementById('view_message').textContent = contact.message;
            document.getElementById('view_created_at').textContent = new Date(contact.created_at).toLocaleString('en-GB', { 
                day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' 
            });
            document.getElementById('viewModal').style.display = 'block';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        window.onclick = function(event) {
            const modal = document.getElementById('viewModal');
            if (event.target === modal) {
                closeViewModal();
            }
        }
    </script>
</body>
</html>