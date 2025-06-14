<?php
header('Content-Type: text/html; charset=UTF-8');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
session_start();
require 'includes/config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_email'])) {
    header("Location: login.php?error=" . urlencode("Please login to view receipts."));
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errorMessage = '';

try {
    $stmt = $conn->prepare("SELECT name, address, mobile, route, enrollment FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        $errorMessage = "User details not found.";
    }
} catch (PDOException $e) {
    error_log("User fetch error: " . $e->getMessage());
    $errorMessage = "Failed to fetch user details. Please try again later.";
}

$receipts = [];
if (!$errorMessage) {
    try {
        if (isset($_GET['export']) && $_GET['export'] === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=receipts_export_' . date('Y-m-d_H-i-s') . '.csv');
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Receipt ID', 'Amount', 'Payment Method', 'Status', 'Date']);
            $stmt = $conn->prepare("SELECT id, amount, payment_method, status, created_at FROM payments WHERE email = ? ORDER BY created_at DESC");
            $stmt->execute([$_SESSION['user_email']]);
            $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($receipts as $receipt) {
                fputcsv($output, [
                    $receipt['id'],
                    'INR ' . number_format($receipt['amount'], 2),
                    ucwords(str_replace('_', ' ', $receipt['payment_method'])),
                    ucfirst($receipt['status']),
                    date('d-m-Y H:i:s', strtotime($receipt['created_at']))
                ]);
            }
            fclose($output);
            exit;
        }

        $stmt = $conn->prepare("SELECT id, amount, payment_method, status, created_at FROM payments WHERE email = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_email']]);
        $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Receipt fetch error: " . $e->getMessage());
        $errorMessage = "Failed to fetch receipts. Please try again later.";
    }
}

// Handle PDF download request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_receipt'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errorMessage = "Invalid CSRF token.";
    } else {
        $payment_id = filter_var($_POST['payment_id'] ?? '', FILTER_SANITIZE_NUMBER_INT);
        try {
            $stmt = $conn->prepare("SELECT p.*, u.name, u.address, u.mobile, u.route, u.enrollment 
                                    FROM payments p 
                                    JOIN users u ON p.user_id = u.id 
                                    WHERE p.id = ? AND p.email = ? LIMIT 1");
            $stmt->execute([$payment_id, $_SESSION['user_email']]);
            $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($receipt) {
                $html_content = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #' . htmlspecialchars($receipt['id'], ENT_QUOTES, 'UTF-8') . '</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
            color: #333;
        }
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            border: 2px solid #ddd;
            border-radius: 10px;
            background: white;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #4CAF50;
        }
        .company-name {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        .receipt-title {
            font-size: 20px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        .receipt-id {
            font-size: 18px;
            font-weight: bold;
            color: #e74c3c;
        }
        .receipt-date {
            font-size: 16px;
            color: #7f8c8d;
        }
        .section {
            margin-bottom: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 5px 0;
        }
        .detail-label {
            font-weight: bold;
            color: #555;
        }
        .detail-value {
            color: #333;
        }
        .amount-section {
            background: #e8f5e8;
            border: 2px solid #4CAF50;
        }
        .amount-value {
            font-size: 24px;
            font-weight: bold;
            color: #2e7d32;
        }
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status-failed {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #ddd;
            color: #7f8c8d;
        }
        .download-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin: 20px 0;
        }
        .download-btn:hover {
            background: #2980b9;
        }
        @media (max-width: 600px) {
            .receipt-info {
                flex-direction: column;
                gap: 10px;
            }
            .detail-row {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="header">
            <div class="company-name">Maa Travels</div>
            <div class="receipt-title">Payment Receipt</div>
        </div>
        <div class="receipt-info">
            <div class="receipt-id">Receipt ID: #' . htmlspecialchars($receipt['id'], ENT_QUOTES, 'UTF-8') . '</div>
            <div class="receipt-date">Date: ' . date('d-m-Y H:i:s', strtotime($receipt['created_at'])) . '</div>
        </div>
        <div class="section">
            <div class="section-title">Customer Details</div>
            <div class="detail-row">
                <span class="detail-label">Name:</span>
                <span class="detail-value">' . htmlspecialchars($receipt['name'], ENT_QUOTES, 'UTF-8') . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Email:</span>
                <span class="detail-value">' . htmlspecialchars($receipt['email'], ENT_QUOTES, 'UTF-8') . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Mobile:</span>
                <span class="detail-value">' . htmlspecialchars($receipt['mobile'], ENT_QUOTES, 'UTF-8') . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Address:</span>
                <span class="detail-value">' . htmlspecialchars($receipt['address'], ENT_QUOTES, 'UTF-8') . '</span>
            </div>';

                if (!empty($receipt['route'])) {
                    $html_content .= '<div class="detail-row">
                        <span class="detail-label">Route:</span>
                        <span class="detail-value">' . htmlspecialchars($receipt['route'], ENT_QUOTES, 'UTF-8') . '</span>
                    </div>';
                }

                if (!empty($receipt['enrollment'])) {
                    $html_content .= '<div class="detail-row">
                        <span class="detail-label">Enrollment No.:</span>
                        <span class="detail-value">' . htmlspecialchars($receipt['enrollment'], ENT_QUOTES, 'UTF-8') . '</span>
                    </div>';
                }

                $status_class = 'status-' . htmlspecialchars($receipt['status'], ENT_QUOTES, 'UTF-8');
                $html_content .= '</div>
        <div class="section amount-section">
            <div class="section-title">Payment Details</div>
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span class="detail-value amount-value">INR ' . number_format($receipt['amount'], 2) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Payment Method:</span>
                <span class="detail-value">' . ucwords(str_replace('_', ' ', htmlspecialchars($receipt['payment_method'], ENT_QUOTES, 'UTF-8'))) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value"><span class="status ' . $status_class . '">' . ucfirst(htmlspecialchars($receipt['status'], ENT_QUOTES, 'UTF-8')) . '</span></span>
            </div>
        </div>
        <div class="footer">
            <p><strong>Thank you for your payment to Maa Travels!</strong></p>
            <p>This is a computer-generated receipt and does not require a signature.</p>
        </div>
        <div class="no-print" style="text-align: center;">
            <button class="download-btn" onclick="window.print()">Print / Save as PDF</button>
            <button class="download-btn" onclick="window.close()" style="background: #95a5a6;">Close</button>
        </div>
    </div>
</body>
</html>';

                header('Content-Type: text/html; charset=UTF-8');
                header('Content-Disposition: inline; filename="receipt_' . $payment_id . '.html"');
                echo $html_content;
                exit;
            } else {
                $errorMessage = "Receipt not found or access denied.";
            }
        } catch (PDOException $e) {
            error_log("PDF generation error: " . $e->getMessage());
            $errorMessage = "Failed to generate receipt. Please try again later.";
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
    <title>Receipts - Maa Travels</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .receipt-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .receipt-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status-failed {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'includes/header.php'; ?>
    <main class="container mx-auto py-16 px-4">
        <h1 class="text-3xl font-semibold text-center mb-6 text-gray-800">Your Payment Receipts</h1>
        <div class="max-w-6xl mx-auto">
            <?php if ($errorMessage): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded mb-6" role="alert">
                    <p class="font-bold">Error:</p>
                    <p><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            <?php endif; ?>
            <div class="mb-6 flex justify-end">
                <a href="receipt.php?export=csv" class="bg-green-500 text-white font-semibold py-3 px-6 rounded-lg hover:bg-green-600 transition duration-200">
                    Export to CSV
                </a>
            </div>
            <?php if (empty($receipts)): ?>
                <div class="bg-white shadow-lg p-8 rounded-lg text-center">
                    <div class="text-gray-400 text-6xl mb-4">📄</div>
                    <p class="text-xl text-gray-600 mb-2">No payment receipts found</p>
                    <p class="text-gray-500">Make your first payment to see receipts here.</p>
                </div>
            <?php else: ?>
                <?php if ($user): ?>
                    <div class="bg-white shadow-lg p-6 rounded-lg mb-6">
                        <h2 class="text-xl font-semibold mb-4 text-gray-800">👤 User Details</h2>
                        <div class="grid md:grid-cols-2 gap-4">
                            <div>
                                <p class="mb-2"><strong class="text-gray-700">Name:</strong> <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="mb-2"><strong class="text-gray-700">Email:</strong> <?php echo htmlspecialchars($_SESSION['user_email'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="mb-2"><strong class="text-gray-700">Mobile:</strong> <?php echo htmlspecialchars($user['mobile'], ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <div>
                                <p class="mb-2"><strong class="text-gray-700">Address:</strong> <?php echo htmlspecialchars($user['address'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php if (!empty($user['route'])): ?>
                                    <p class="mb-2"><strong class="text-gray-700">Route:</strong> <?php echo htmlspecialchars($user['route'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($user['enrollment'])): ?>
                                    <p class="mb-2"><strong class="text-gray-700">Enrollment No.:</strong> <?php echo htmlspecialchars($user['enrollment'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b">
                        <h2 class="text-xl font-semibold text-gray-800">🧾 Payment Receipts</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200" role="table">
                            <caption class="sr-only">Payment Receipts Table</caption>
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Receipt ID</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($receipts as $receipt): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                            #<?php echo htmlspecialchars($receipt['id'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <span class="font-semibold text-green-600">INR <?php echo number_format($receipt['amount'], 2); ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo ucwords(str_replace('_', ' ', htmlspecialchars($receipt['payment_method'], ENT_QUOTES, 'UTF-8'))); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full status-<?php echo htmlspecialchars($receipt['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo ucfirst(htmlspecialchars($receipt['status'], ENT_QUOTES, 'UTF-8')); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <?php echo date('d-m-Y H:i:s', strtotime($receipt['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <form action="receipt.php" method="POST" class="inline" target="_blank">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="payment_id" value="<?php echo htmlspecialchars($receipt['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" name="download_receipt" 
                                                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-xs transition duration-200">
                                                    📥 Download PDF
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
</body>
</html>