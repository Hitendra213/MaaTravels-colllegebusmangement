<?php
header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
session_start();
require 'includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header("Location: login.php?error=" . urlencode("Please login to make a payment."));
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid CSRF token.";
    } else {
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $amount = filter_var(trim($_POST['amount'] ?? ''), FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $payment_method = filter_var(trim($_POST['payment_method'] ?? ''), FILTER_SANITIZE_STRING);
        $card_number = filter_var(trim($_POST['card_number'] ?? ''), FILTER_SANITIZE_STRING);
        $card_holder = filter_var(trim($_POST['card_holder'] ?? ''), FILTER_SANITIZE_STRING);
        $expiry_date = filter_var(trim($_POST['expiry_date'] ?? ''), FILTER_SANITIZE_STRING);
        $cvv = filter_var(trim($_POST['cvv'] ?? ''), FILTER_SANITIZE_STRING);
        $user_id = $_SESSION['user_id'];

        $errors = [];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $email !== $_SESSION['user_email']) {
            $errors[] = "Invalid or unauthorized email.";
        }

        if (!is_numeric($amount) || $amount <= 0) {
            $errors[] = "Valid amount is required.";
        }

        if (!in_array($payment_method, ['credit_card', 'debit_card', 'upi'])) {
            $errors[] = "Valid payment method is required.";
        }

        if ($payment_method !== 'upi') {
            if (!preg_match("/^[0-9]{16}$/", str_replace(' ', '', $card_number))) {
                $errors[] = "Valid 16-digit card number is required.";
            }
            if (empty($card_holder)) {
                $errors[] = "Card holder name is required.";
            }
            if (!preg_match("/^(0[1-9]|1[0-2])\/[0-9]{2}$/", $expiry_date)) {
                $errors[] = "Valid expiry date (MM/YY) is required.";
            }
            if (!preg_match("/^[0-9]{3}$/", $cvv)) {
                $errors[] = "Valid 3-digit CVV is required.";
            }
        } else {
            if (empty($card_number)) {
                $errors[] = "UPI ID is required.";
            }
        }

        if (empty($errors)) {
            try {
                $conn->beginTransaction();
                $stmt = $conn->prepare("INSERT INTO payments (user_id, email, amount, payment_method, card_number, card_holder, expiry_date, cvv, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $status = 'completed';
                $hashed_card_number = password_hash($card_number, PASSWORD_DEFAULT);
                $stmt->execute([$user_id, $email, $amount, $payment_method, $hashed_card_number, $card_holder, $expiry_date, $cvv, $status]);
                $conn->commit();
                
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $successMessage = "Payment submitted successfully!";
            } catch (PDOException $e) {
                $conn->rollBack();
                error_log("Payment error: " . $e->getMessage());
                $errorMessage = "Payment failed. Please try again later.";
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
    <title>Payment - Maa Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="bg-gray-100">
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
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="payment.php" class="text-blue-600 hover:text-blue-800">Payment</a>
                    <a href="receipt.php" class="text-blue-600 hover:text-blue-800">Receipts</a>
                    <a href="logout.php" class="text-blue-600 hover:text-blue-800">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="text-blue-600 hover:text-blue-800">Login</a>
                    <a href="register.php" class="text-blue-600 hover:text-blue-800">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container mx-auto py-16 px-4">
        <h1 class="text-3xl font-semibold text-center mb-6 text-gray-800">Make a Payment</h1>
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
                    <p><?php echo htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            <form id="payment-form" action="payment.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-4">
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                    <input type="email" id="email" name="email" 
                           class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100 focus:outline-none" 
                           value="<?php echo htmlspecialchars($_SESSION['user_email'], ENT_QUOTES, 'UTF-8'); ?>" 
                           readonly required>
                </div>
                <div class="mb-4">
                    <label for="amount" class="block text-sm font-medium text-gray-700 mb-2">Amount (INR)</label>
                    <input type="number" id="amount" name="amount" step="0.01" min="0.01"
                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                           required>
                </div>
                <div class="mb-4">
                    <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                    <select id="payment_method" name="payment_method" 
                            class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                            required onchange="togglePaymentFields()">
                        <option value="">Select Payment Method</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="upi">UPI</option>
                    </select>
                </div>
                <div id="card_fields" class="hidden">
                    <div class="mb-4">
                        <label for="card_number" class="block text-sm font-medium text-gray-700 mb-2">Card Number / UPI ID</label>
                        <input type="text" id="card_number" name="card_number" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                               placeholder="1234 5678 9012 3456">
                    </div>
                    <div class="mb-4" id="card_holder_field">
                        <label for="card_holder" class="block text-sm font-medium text-gray-700 mb-2">Card Holder Name</label>
                        <input type="text" id="card_holder" name="card_holder" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500">
                    </div>
                    <div class="mb-4" id="expiry_date_field">
                        <label for="expiry_date" class="block text-sm font-medium text-gray-700 mb-2">Expiry Date (MM/YY)</label>
                        <input type="text" id="expiry_date" name="expiry_date" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                               placeholder="MM/YY">
                    </div>
                    <div class="mb-6" id="cvv_field">
                        <label for="cvv" class="block text-sm font-medium text-gray-700 mb-2">CVV</label>
                        <input type="text" id="cvv" name="cvv" 
                               class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500" 
                               placeholder="123">
                    </div>
                </div>
                <button type="submit" 
                        class="w-full bg-blue-500 text-white py-3 rounded-lg hover:bg-blue-600 transition duration-200 font-semibold">
                    Submit Payment
                </button>
            </form>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script>
        function togglePaymentFields() {
            const paymentMethod = document.getElementById('payment_method').value;
            const cardFields = document.getElementById('card_fields');
            const cardHolderField = document.getElementById('card_holder_field');
            const expiryDateField = document.getElementById('expiry_date_field');
            const cvvField = document.getElementById('cvv_field');
            const cardNumberLabel = document.querySelector('label[for="card_number"]');

            if (paymentMethod === 'upi') {
                cardFields.classList.remove('hidden');
                cardHolderField.classList.add('hidden');
                expiryDateField.classList.add('hidden');
                cvvField.classList.add('hidden');
                cardNumberLabel.textContent = 'UPI ID';
            } else if (paymentMethod === 'credit_card' || paymentMethod === 'debit_card') {
                cardFields.classList.remove('hidden');
                cardHolderField.classList.remove('hidden');
                expiryDateField.classList.remove('hidden');
                cvvField.classList.remove('hidden');
                cardNumberLabel.textContent = 'Card Number';
            } else {
                cardFields.classList.add('hidden');
            }
        }

        document.getElementById('payment-form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const amount = document.getElementById('amount').value;
            const paymentMethod = document.getElementById('payment_method').value;
            const cardNumber = document.getElementById('card_number').value;
            const cardHolder = document.getElementById('card_holder').value;
            const expiryDate = document.getElementById('expiry_date').value;
            const cvv = document.getElementById('cvv').value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
            } else if (!amount || amount <= 0) {
                e.preventDefault();
                alert('Please enter a valid amount.');
            } else if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method.');
            } else if (paymentMethod !== 'upi') {
                const cardNumberRegex = /^[0-9]{16}$/;
                const expiryDateRegex = /^(0[1-9]|1[0-2])\/[0-9]{2}$/;
                const cvvRegex = /^[0-9]{3}$/;

                if (!cardNumberRegex.test(cardNumber.replace(/\s/g, ''))) {
                    e.preventDefault();
                    alert('Please enter a valid 16-digit card number.');
                } else if (!cardHolder) {
                    e.preventDefault();
                    alert('Please enter the card holder name.');
                } else if (!expiryDateRegex.test(expiryDate)) {
                    e.preventDefault();
                    alert('Please enter a valid expiry date (MM/YY).');
                } else if (!cvvRegex.test(cvv)) {
                    e.preventDefault();
                    alert('Please enter a valid 3-digit CVV.');
                }
            } else if (!cardNumber) {
                e.preventDefault();
                alert('Please enter a valid UPI ID.');
            }
        });

        // Format card number input
        document.getElementById('card_number').addEventListener('input', function(e) {
            if (document.getElementById('payment_method').value !== 'upi') {
                let value = e.target.value.replace(/\s/g, '');
                if (value.length > 16) value = value.substr(0, 16);
                let formatted = value.match(/.{1,4}/g)?.join(' ') || value;
                e.target.value = formatted;
            }
        });
    </script>
</body>
</html>