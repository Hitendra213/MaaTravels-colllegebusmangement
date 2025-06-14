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

try {
    $stmt = $conn->prepare("SELECT id, name, email, mobile, address, route, enrollment FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("User fetch error: " . $e->getMessage());
    $errorMessage = "Failed to load profile. Please try again later.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid CSRF token.";
    } else {
        $name = filter_var(trim($_POST['name'] ?? ''), FILTER_SANITIZE_STRING);
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $mobile = filter_var(trim($_POST['mobile'] ?? ''), FILTER_SANITIZE_STRING);
        $address = filter_var(trim($_POST['address'] ?? ''), FILTER_SANITIZE_STRING);
        $enrollment = filter_var(trim($_POST['enrollment'] ?? ''), FILTER_SANITIZE_STRING);
        $password = trim($_POST['password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        $errors = [];
        if (empty($name)) $errors[] = "Name is required.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
        if (!preg_match("/^[0-9]{10}$/", $mobile)) $errors[] = "Valid 10-digit mobile number is required.";
        if (empty($address)) $errors[] = "Address is required.";
        if ($password && $password !== $confirm_password) $errors[] = "Passwords do not match.";
        if ($password && strlen($password) < 8) $errors[] = "Password must be at least 8 characters long.";

        if (empty($errors)) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $errors[] = "Email is already in use by another user.";
            }
        }

        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                $query = "UPDATE users SET name = ?, email = ?, mobile = ?, address = ?, enrollment = ?";
                $params = [$name, $email, $mobile, $address, $enrollment];
                if ($password) {
                    $query .= ", password = ?";
                    $params[] = password_hash($password, PASSWORD_DEFAULT);
                }
                $query .= " WHERE id = ?";
                $params[] = $_SESSION['user_id'];
                $stmt = $conn->prepare($query);
                $stmt->execute($params);
                $conn->commit();
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $name;
                $successMessage = "Profile updated successfully.";
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                header("Location: profile.php?success=" . urlencode($successMessage));
                exit;
            } catch (PDOException $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Profile update error: " . $e->getMessage());
                $errorMessage = "Failed to update profile: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            }
        } else {
            $errorMessage = implode(" ", $errors);
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
    <title>Profile - Maa Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>
    <main class="container mx-auto py-16 px-4">
        <h1 class="text-3xl font-semibold text-center mb-6 text-gray-800">Your Profile</h1>
        <div class="max-w-lg mx-auto bg-white shadow-lg p-6 rounded-lg">
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
            <form action="profile.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="update_profile">
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?>" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label for="mobile" class="block text-sm font-medium text-gray-700 mb-2">Mobile</label>
                    <input type="text" id="mobile" name="mobile" value="<?php echo htmlspecialchars($user['mobile'], ENT_QUOTES, 'UTF-8'); ?>" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required>
                </div>
                <div class="mb-4">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <textarea id="address" name="address" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" required><?php echo htmlspecialchars($user['address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>
                <div class="mb-4">
                    <label for="enrollment" class="block text-sm font-medium text-gray-700 mb-2">Enrollment No. (Optional)</label>
                    <input type="text" id="enrollment" name="enrollment" value="<?php echo htmlspecialchars($user['enrollment'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">New Password (leave blank to keep current)</label>
                    <input type="password" id="password" name="password" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <div class="mb-4">
                    <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                </div>
                <button type="submit" class="w-full bg-blue-500 text-white py-3 rounded-lg hover:bg-blue-600 transition duration-200 font-semibold">
                    Update Profile
                </button>
            </form>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script src="js/scripts.js"></script>
</body>
</html>