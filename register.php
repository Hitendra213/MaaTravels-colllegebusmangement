<?php
header('Content-Type: text/html; charset=UTF-8');
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable display errors in production
require 'includes/config.php';

// Initialize CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables for error and success messages
$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid CSRF token.";
    } else {
        // Sanitize and validate input
        $name = filter_var(trim($_POST['name']), FILTER_SANITIZE_STRING);
        $address = filter_var(trim($_POST['address']), FILTER_SANITIZE_STRING);
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $mobile = filter_var(trim($_POST['mobile']), FILTER_SANITIZE_STRING);
        $route = filter_var(trim($_POST['route']), FILTER_SANITIZE_STRING);
        $enrollment = filter_var(trim($_POST['enrollment']), FILTER_SANITIZE_STRING);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];

        // Validation
        $errors = [];

        if (empty($name)) {
            $errors[] = "Name is required.";
        } elseif (strlen($name) < 2 || strlen($name) > 100) {
            $errors[] = "Name must be between 2 and 100 characters.";
        }

        if (empty($address)) {
            $errors[] = "Address is required.";
        }

        if (empty($email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }

        if (empty($mobile)) {
            $errors[] = "Mobile number is required.";
        } elseif (!preg_match("/^[0-9]{10}$/", $mobile)) {
            $errors[] = "Mobile number must be 10 digits.";
        }

        if (empty($route)) {
            $errors[] = "Route is required.";
        }

        if (empty($password)) {
            $errors[] = "Password is required.";
        } elseif (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }

        if (empty($confirmPassword)) {
            $errors[] = "Please confirm your password.";
        } elseif ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match.";
        }

        // If no validation errors, proceed with registration
        if (empty($errors)) {
            try {
                // Check if email already exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $errorMessage = "Email already registered. Please use a different email or login.";
                } else {
                    // Hash password securely
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // Insert new user
                    $stmt = $conn->prepare("INSERT INTO users (name, address, email, mobile, route, enrollment, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $address, $email, $mobile, $route, $enrollment, $hashedPassword]);
                    
                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    $successMessage = "Registration successful! Please <a href='login.php' class='underline font-semibold'>login</a>.";
                }
            } catch (PDOException $e) {
                error_log("Registration error: " . $e->getMessage());
                $errorMessage = "Registration failed. Please try again later.";
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
    <title>Register - Maa Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-gray-100">
    <!-- <?php include 'includes/header.php'; ?> -->

    <main class="container mx-auto py-10 px-4">
        <h1 class="text-3xl font-semibold text-center mb-6 text-gray-800">Register</h1>
        <div class="max-w-md mx-auto bg-white shadow-lg p-6 rounded-lg">
            <?php if ($errorMessage): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-4" role="alert">
                    <p class="font-bold">Error:</p>
                    <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($successMessage): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded mb-4" role="alert">
                    <p class="font-bold">Success:</p>
                    <p><?php echo $successMessage; ?></p>
                </div>
            <?php endif; ?>
            <form id="register-form" action="register.php" method="POST" onsubmit="return validateRegisterForm()">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                    <input type="text" id="name" name="name" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                           required minlength="2" maxlength="100" value="<?php echo isset($name) ? htmlspecialchars($name, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="mb-4">
                    <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Address</label>
                    <input type="text" id="address" name="address" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                           required value="<?php echo isset($address) ? htmlspecialchars($address, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" id="email" name="email" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                           required maxlength="100" value="<?php echo isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="mb-4">
                    <label for="mobile" class="block text-sm font-medium text-gray-700 mb-2">Mobile No.</label>
                    <input type="tel" id="mobile" name="mobile" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                           required pattern="[0-9]{10}" value="<?php echo isset($mobile) ? htmlspecialchars($mobile, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="mb-4">
                    <label for="route" class="block text-sm font-medium text-gray-700 mb-2">Route Name</label>
                    <input type="text" id="route" name="route" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                           required value="<?php echo isset($route) ? htmlspecialchars($route, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="mb-4">
                    <label for="enrollment" class="block text-sm font-medium text-gray-700 mb-2">Enrollment No.</label>
                    <input type="text" id="enrollment" name="enrollment" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                           value="<?php echo isset($enrollment) ? htmlspecialchars($enrollment, ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>
                <div class="mb-4">
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                    <input type="password" id="password" name="password" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                           required minlength="6">
                    <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                </div>
                <div class="mb-6">
                    <label for="confirm-password" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                    <input type="password" id="confirm-password" name="confirm_password" 
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                           required>
                </div>
                <button type="submit" 
                        class="w-full bg-blue-500 text-white py-3 rounded-lg hover:bg-blue-600 transition duration-200 font-semibold">
                    Register
                </button>
            </form>
            <div class="text-center mt-4">
                <p class="text-gray-600">Already have an account? <a href="login.php" class="text-blue-500 hover:underline">Login here</a></p>
            </div>
        </div>
    </main>

    <!-- <?php include 'includes/footer.php'; ?> -->

    <script src="js/scripts.js"></script>
    <script>
        function validateRegisterForm() {
            const name = document.getElementById('name').value;
            const address = document.getElementById('address').value;
            const email = document.getElementById('email').value;
            const mobile = document.getElementById('mobile').value;
            const route = document.getElementById('route').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const mobileRegex = /^[0-9]{10}$/;

            if (name.length < 2 || name.length > 100) {
                alert('Name must be between 2 and 100 characters.');
                return false;
            }
            if (!address) {
                alert('Address is required.');
                return false;
            }
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                return false;
            }
            if (!mobileRegex.test(mobile)) {
                alert('Mobile number must be 10 digits.');
                return false;
            }
            if (!route) {
                alert('Route is required.');
                return false;
            }
            if (password.length < 6) {
                alert('Password must be at least 6 characters long.');
                return false;
            }
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                return false;
            }
            return true;
        }
    </script>
</body>
</html>