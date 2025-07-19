<?php
session_start();

// Ensure user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$_loggedInUserId = $_SESSION['id'];
$_loggedInUsername = $_SESSION['username'];

require_once 'config.php'; 

$database = new Database();
$pdo = $database->getConnection();

if ($pdo === null) {
    die("Error: Could not connect to the database to generate report.");
}

$transactions = [];
$budgets = [];
$summary = ['total_income' => 0, 'total_expense' => 0, 'balance' => 0];

try {
    // Fetch all transactions for the logged-in user
    $stmt = $pdo->prepare("SELECT * FROM transaksi WHERE user_id = ? ORDER BY tanggal DESC");
    $stmt->execute([$_loggedInUserId]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all budgets for the logged-in user
    $stmt = $pdo->prepare("SELECT * FROM anggaran WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_loggedInUserId]);
    $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate summary
    foreach ($transactions as $row) {
        if ($row['tipe'] == 'pemasukan') {
            $summary['total_income'] += $row['jumlah'];
        } else {
            $summary['total_expense'] += $row['jumlah'];
        }
    }
    $summary['balance'] = $summary['total_income'] - $summary['total_expense'];

} catch (Exception $e) {
    error_log("Error fetching data for report: " . $e->getMessage());
    die("Error fetching data for report: " . $e->getMessage());
}

$reportPeriod = "All Time"; 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Report - wHealth</title>
    <!-- Link to your existing CSS for consistent styling -->
    <link rel="stylesheet" href="frontend.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Specific styles for the report page to ensure good print layout */
        body {
            background: #f4f7f6; /* Lighter background for printing */
            color: #333;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .report-container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .report-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }
        .report-header h1 {
            color: #667eea;
            font-size: 2.2rem;
            margin-bottom: 10px;
        }
        .report-header p {
            color: #555;
            font-size: 1.1rem;
        }
        .report-section-title {
            font-size: 1.5rem;
            color: #333;
            margin-top: 30px;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .summary-card {
            background: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            text-align: center;
        }
        .summary-card h3 {
            color: #666;
            font-size: 1rem;
            margin-bottom: 10px;
        }
        .summary-card .value {
            font-size: 1.8rem;
            font-weight: bold;
        }
        .summary-card.income .value { color: #22c55e; }
        .summary-card.expense .value { color: #ef4444; }
        .summary-card.balance .value { color: #3b82f6; }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .report-table th, .report-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .report-table th {
            background-color: #667eea;
            color: white;
            font-weight: 600;
        }
        .report-table tbody tr:nth-child(even) {
            background-color: #f8f8f8;
        }
        .report-table tbody tr:hover {
            background-color: #f1f1f1;
        }
        .amount-income { color: #22c55e; font-weight: 600; }
        .amount-expense { color: #ef4444; font-weight: 600; }
        .badge {
            padding: 0.4em 0.6em;
            border-radius: 0.25rem;
            font-size: 0.75em;
            font-weight: bold;
            color: white;
            text-transform: capitalize;
        }
        .badge-income { background-color: #28a745; }
        .badge-expense { background-color: #dc3545; }

        .print-info {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px dashed #ccc;
            font-size: 0.9rem;
            color: #777;
        }

        /* Hide elements not relevant for print */
        @media print {
            body {
                background: none;
                padding: 0;
            }
            .report-container {
                box-shadow: none;
                border-radius: 0;
                padding: 0;
            }
            .print-info {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="report-container">
        <div class="report-header">
            <h1>Financial Report - wHealth</h1>
            <p>Generated for: <?php echo htmlspecialchars($_loggedInUsername); ?></p>
            <p>Report Period: <?php echo htmlspecialchars($reportPeriod); ?></p>
        </div>

        <h2 class="report-section-title"><i class="fas fa-chart-pie"></i> Financial Summary</h2>
        <div class="summary-grid">
            <div class="summary-card income">
                <h3>Total Income</h3>
                <p class="value"><?php echo formatCurrency($summary['total_income']); ?></p>
            </div>
            <div class="summary-card expense">
                <h3>Total Expense</h3>
                <p class="value"><?php echo formatCurrency($summary['total_expense']); ?></p>
            </div>
            <div class="summary-card balance">
                <h3>Balance</h3>
                <p class="value"><?php echo formatCurrency($summary['balance']); ?></p>
            </div>
        </div>

        <h2 class="report-section-title"><i class="fas fa-list"></i> Transaction Details</h2>
        <?php if (empty($transactions)): ?>
            <p style="text-align: center; margin-top: 20px;">No transactions found for this period.</p>
        <?php else: ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($transaction['tanggal']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $transaction['tipe'] === 'pemasukan' ? 'income' : 'expense'; ?>">
                                    <?php echo htmlspecialchars(ucfirst($transaction['tipe'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($transaction['kategori']); ?></td>
                            <td><?php echo htmlspecialchars($transaction['deskripsi'] ?: '-'); ?></td>
                            <td class="amount-<?php echo $transaction['tipe'] === 'pemasukan' ? 'income' : 'expense'; ?>">
                                <?php echo ($transaction['tipe'] === 'pemasukan' ? '+' : '-') . formatCurrency($transaction['jumlah']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2 class="report-section-title"><i class="fas fa-piggy-bank"></i> Budget Details</h2>
        <?php if (empty($budgets)): ?>
            <p style="text-align: center; margin-top: 20px;">No budgets found.</p>
        <?php else: ?>
            <table class="report-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Budget Amount</th>
                        <th>Period</th>
                        <th>Used</th>
                        <th>Remaining</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budgets as $budget):
                        $used = 0;
                        foreach ($transactions as $t) {
                            if ($t['tipe'] === 'pengeluaran' && $t['kategori'] === $budget['kategori']) {
                                $used += $t['jumlah'];
                            }
                        }
                        $remaining = $budget['jumlah_anggaran'] - $used;
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($budget['kategori']); ?></td>
                            <td><?php echo formatCurrency($budget['jumlah_anggaran']); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst($budget['periode'])); ?></td>
                            <td><?php echo formatCurrency($used); ?></td>
                            <td><?php echo formatCurrency($remaining); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="print-info">
            <p>To save this report as a PDF, use your browser's print function (Ctrl+P).</p>
        </div>
    </div>
</body>
</html>
