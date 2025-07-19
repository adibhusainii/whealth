<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

$_loggedInUserId = $_SESSION['id'];
$_loggedInUsername = $_SESSION['username']; 

// config.php
// Assuming config.php defines the Database class and is in the same directory
require_once 'config.php';

// Initialize database connection
$database = new Database();
$pdo = $database->getConnection();
$db_connected = ($pdo !== null);

$message = "";
// This POST handling logic is typically in separate API endpoints,
// but keeping it here as per your original file structure.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($db_connected) {
        try {
            if (isset($_POST['action'])) {
                switch ($_POST['action']) {
                    case 'add_transaction':
                        $stmt = $pdo->prepare("INSERT INTO transaksi (user_id, tipe, kategori, deskripsi, jumlah, tanggal) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $_loggedInUserId,
                            $_POST['tipe'],
                            $_POST['kategori'],
                            $_POST['deskripsi'],
                            $_POST['jumlah'],
                            date('Y-m-d')
                        ]);
                        $message = "Transaction added!";
                        break;
                        
                    case 'add_budget':
                        $stmt = $pdo->prepare("INSERT INTO anggaran (user_id, kategori, jumlah_anggaran, periode, tahun, bulan) VALUES (?, ?, ?, ?, ?, ?)");
                        $bulan = $_POST['periode'] == 'bulanan' ? date('n') : null;
                        $stmt->execute([
                            $_loggedInUserId,
                            $_POST['kategori'],
                            $_POST['jumlah'],
                            $_POST['periode'],
                            date('Y'),
                            $bulan
                        ]);
                        $message = "Budget added!";
                        break;
                        
                    case 'delete_transaction':
                        $stmt = $pdo->prepare("DELETE FROM transaksi WHERE id_transaksi = ? AND user_id = ?");
                        $stmt->execute([$_POST['id'], $_loggedInUserId]);
                        $message = "Transaction deleted!";
                        break;
                        
                    case 'delete_budget':
                        $stmt = $pdo->prepare("DELETE FROM anggaran WHERE id_anggaran = ? AND user_id = ?");
                        $stmt->execute([$_POST['id'], $_loggedInUserId]);
                        $message = "Budget deleted!";
                        break;
                    // Add update cases if they are handled here via POST
                    case 'update_transaction':
                        $stmt = $pdo->prepare("UPDATE transaksi SET tipe = ?, kategori = ?, deskripsi = ?, jumlah = ? WHERE id_transaksi = ? AND user_id = ?");
                        $stmt->execute([
                            $_POST['tipe'],
                            $_POST['kategori'],
                            $_POST['deskripsi'],
                            $_POST['jumlah'],
                            $_POST['id'],
                            $_loggedInUserId
                        ]);
                        $message = "Transaction updated!";
                        break;
                    case 'update_budget':
                        $stmt = $pdo->prepare("UPDATE anggaran SET kategori = ?, jumlah_anggaran = ?, periode = ? WHERE id_anggaran = ? AND user_id = ?");
                        $stmt->execute([
                            $_POST['kategori'],
                            $_POST['jumlah'],
                            $_POST['periode'],
                            $_POST['id'],
                            $_loggedInUserId
                        ]);
                        $message = "Budget updated!";
                        break;
                }
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    } else {
        $message = "Failed to connect to database!";
    }
}

// Ambil data dari database untuk user yang sedang login
$transactions = [];
$budgets = [];
$summary = ['total_pemasukan' => 0, 'total_pengeluaran' => 0, 'saldo' => 0];

if ($db_connected) {
    try {
        // Ambil transaksi untuk user yang sedang login saja
        $stmt = $pdo->prepare("SELECT * FROM transaksi WHERE user_id = ? ORDER BY tanggal DESC");
        $stmt->execute([$_loggedInUserId]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Ambil anggaran untuk user yang sedang login saja
        $stmt = $pdo->prepare("SELECT * FROM anggaran WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_loggedInUserId]);
        $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Hitung summary untuk user yang sedang login saja
        $stmt = $pdo->prepare("SELECT tipe, SUM(jumlah) as total FROM transaksi WHERE user_id = ? GROUP BY tipe");
        $stmt->execute([$_loggedInUserId]);
        $summary_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($summary_data as $row) {
            if ($row['tipe'] == 'pemasukan') {
                $summary['total_pemasukan'] = $row['total'];
            } else {
                $summary['total_pengeluaran'] = $row['total'];
            }
        }
        $summary['saldo'] = $summary['total_pemasukan'] - $summary['total_pengeluaran'];
        
    } catch (Exception $e) {
        $error_message = "Error fetching data: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>wHealth</title>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="frontend.css">
    
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-wallet"></i> wHealth</h1>
            <p>Healthy Wealth for Wellthy Living</p>
            <div style="margin-top: 1rem; text-align: right">
                <div class="welcome-text">
                    <small>Welcome, <?php echo htmlspecialchars($_SESSION['username']);?>!<br>
                </div>
                <div class="logout-btn">
                    <a href="logout.php" style="color: rgba(255,255,255,0.8); margin-left: 2px;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>  
                </small>
            </div>
        </div>

        <div class="nav-tabs">
            <button class="nav-tab active" onclick="showSection('dashboard')">
                <i class="fas fa-chart-line"></i> Home
            </button>
            <button class="nav-tab" onclick="showSection('transaksi')">
                <i class="fas fa-exchange-alt"></i> Transactions
            </button>
            <button class="nav-tab" onclick="showSection('anggaran')">
                <i class="fas fa-piggy-bank"></i> Budget Plan
            </button>
            <button class="nav-tab" onclick="showSection('laporan')">
                <i class="fas fa-file-alt"></i> Report
            </button>
        </div>

        <div id="notificationContainer" style="position: fixed; top: 20px; right: 20px; z-index: 10000;"></div>

        <div class="content">
            <section id="dashboard" class="content-section active">
                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon income">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Total Income</h3>
                                <p class="card-value" id="totalIncome"><?php echo formatCurrency($summary['total_pemasukan']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon expense">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Total Outcome</h3>
                                <p class="card-value" id="totalExpense"><?php echo formatCurrency($summary['total_pengeluaran']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon balance">
                                <i class="fas fa-balance-scale"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Balance</h3>
                                <p class="card-value" id="totalBalance"><?php echo formatCurrency($summary['saldo']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="quick-actions">
                    <div class="card interactive-element" onclick="showQuickTransaction('pemasukan')" style="cursor: pointer;">
                        <div class="card-header">
                            <div class="card-icon income">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Quick Add Income</h3>
                                <p style="margin: 0; font-size: 0.9rem; color: #666;">Click to add new income</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card interactive-element" onclick="showQuickTransaction('pengeluaran')" style="cursor: pointer;">
                        <div class="card-header">
                            <div class="card-icon expense">
                                <i class="fas fa-minus"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Quick Add Outcome</h3>
                                <p style="margin: 0; font-size: 0.9rem; color: #666;">Click to add new outcome</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="pie-chart-container">
                    <h3><i class="fas fa-chart-pie"></i> Outcome Chart</h3>
                    <canvas id="expenseChart"></canvas> </div>

                <div class="recent-transactions">
                    <h3><i class="fas fa-clock"></i> Recent Transactions</h3>
                    <div id="recentTransactionsList">
                        <?php if (empty($transactions)): ?>
                            <div class="transaction-item">
                                <div>No transactions yet for this user</div>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_slice($transactions, 0, 5) as $transaction): ?>
                                <div class="transaction-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($transaction['kategori']); ?></strong>
                                        <br><small><?php echo $transaction['tanggal']; ?></small>
                                        <?php if ($transaction['deskripsi']): ?>
                                            <br><small><?php echo htmlspecialchars($transaction['deskripsi']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <span class="badge badge-<?php echo $transaction['tipe'] === 'pemasukan' ? 'income' : 'outcome'; ?>">
                                        <?php echo ($transaction['tipe'] === 'pemasukan' ? '+' : '-') . formatCurrency($transaction['jumlah']); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section id="transaksi" class="content-section">
                <div class="search-and-filter-container" style="margin-bottom: 2rem;">
                    <div class="filter-container">
                        <div class="search-container">
                            <div style="position: relative;">
                                <i class="fas fa-search search-icon"></i>
                                <input 
                                    type="text" 
                                    id="transactionSearch" 
                                    class="search-input" 
                                    placeholder="Search transactions..."
                                    autocomplete="off"
                                >
                            </div>
                        </div>
                        
                        <div class="filter-group">
                            <label for="typeFilter">Filter Type</label>
                            <select id="typeFilter" class="filter-select">
                                <option value="">All Type</option>
                                <option value="pemasukan">Income</option>
                                <option value="pengeluaran">Outcome</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="form-container" id="transactionFormContainer">
                    <h2><i class="fas fa-plus-circle"></i> Add Transaction</h2>
                    <form id="transactionForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="tipe">Transaction Type</label>
                                <select name="tipe" id="tipeTransaksi" required>
                                    <option value="">Choose Type</option>
                                    <option value="pemasukan">Income</option>
                                    <option value="pengeluaran">Outcome</option>
                                </select>
                            </div>
                            
                            <div class="form-group has-suggestion">
                                <label for="kategori">
                                    Category 
                                </label>
                                <input 
                                    type="text" 
                                    name="kategori" 
                                    id="kategori"
                                    placeholder="Input Category" 
                                    required
                                    autocomplete="off"
                                >
                                <div class="suggestion-indicator">
                                    <i class="fas fa-lightbulb"></i>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="jumlah">Amount</label>
                                <input type="number" name="jumlah" placeholder="Input Amount" required min="1" step="0.01">
                            </div>
                            
                            <div class="form-group">
                                <label for="deskripsi">Description (Optional)</label>
                                <input type="text" name="deskripsi" placeholder="Description">
                            </div>
                        </div>
                        
                        <div class="form-button-container">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Transaction
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-container">
                    <h3><i class="fas fa-list"></i> Transaction List - User: <?php echo htmlspecialchars($_SESSION['username']); ?></h3>
                    
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="transactionTableBody">
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No transactions yet for this user</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo $transaction['tanggal']; ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $transaction['tipe'] === 'pemasukan' ? 'income' : 'outcome'; ?>">
                                                <?php echo $transaction['tipe']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['kategori']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['deskripsi']) ?: '-'; ?></td>
                                        <td class="amount-<?php echo $transaction['tipe'] === 'pemasukan' ? 'income' : 'outcome'; ?>">
                                            <?php echo ($transaction['tipe'] === 'pemasukan' ? '+' : '-') . formatCurrency($transaction['jumlah']); ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-primary btn-sm" onclick="editTransaction(<?php echo $transaction['id_transaksi']; ?>)" title="Edit Transaction">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="deleteTransaction(<?php echo $transaction['id_transaksi']; ?>)" title="Delete Transaction">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="anggaran" class="content-section">
                <div class="form-container" id="budgetFormContainer">
                    <h2><i class="fas fa-piggy-bank"></i> Create Budget Plan</h2>
                    <form id="budgetForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="kategori-anggaran">Category</label>
                                <input type="text" name="kategori" placeholder="Budget Category" required>
                            </div>
                            <div class="form-group">
                                <label for="jumlah-anggaran">Budget Amount</label>
                                <input type="number" name="jumlah" placeholder="Budget Amount" required min="1" step="0.01">
                            </div>
                            <div class="form-group">
                                <label for="periode">Period</label>
                                <select name="periode" required>
                                    <option value="">Choose Period</option>
                                    <option value="bulanan">Monthly</option>
                                    <option value="tahunan">Annual</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-button-container">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-plus"></i> Create Budget
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-container">
                    <h3><i class="fas fa-chart-bar"></i> Budget Status - User: <?php echo htmlspecialchars($_SESSION['username']); ?></h3>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Budget Plan</th>
                                <th>Used</th>
                                <th>Remaining</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="budgetTableBody">
                            <?php if (empty($budgets)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No budget yet for this user</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($budgets as $budget): ?>
                                    <?php
                                    // Calculate used amount from transactions for this user
                                    $used = 0;
                                    foreach ($transactions as $t) {
                                        if ($t['tipe'] === 'pengeluaran' && $t['kategori'] === $budget['kategori']) {
                                            $used += $t['jumlah'];
                                        }
                                    }
                                    $remaining = $budget['jumlah_anggaran'] - $used;
                                    $percentage = $budget['jumlah_anggaran'] > 0 ? ($used / $budget['jumlah_anggaran']) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($budget['kategori']); ?></td>
                                        <td><?php echo formatCurrency($budget['jumlah_anggaran']); ?></td>
                                        <td><?php echo formatCurrency($used); ?></td>
                                        <td><?php echo formatCurrency($remaining); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $percentage > 100 ? 'danger' : ($percentage > 80 ? 'warning' : 'success'); ?>">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-primary btn-sm" onclick="editBudget(<?php echo $budget['id_anggaran']; ?>)" title="Edit Budget">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-danger btn-sm" onclick="deleteBudget(<?php echo $budget['id_anggaran']; ?>)" title="Delete Budget">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="laporan" class="content-section">
                <div class="form-container">
                    <h2><i class="fas fa-file-alt"></i> Financial Report</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="periode-laporan">Report Period</label>
                            <select id="periode-laporan">
                                <option value="bulan-ini">This Month</option>
                                <option value="3-bulan">Last 3 Months</option>
                                <option value="tahun-ini">This Year</option>
                                <option value="all-time">All Time</option> </select>
                        </div>
                    
                    </div>
                </div>

                <div class="dashboard-grid">
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon income">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Income Period</h3>
                                <p class="card-value" id="reportIncome">Rp 0</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon expense">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Outcome Period</h3>
                                <p class="card-value" id="reportExpense">Rp 0</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <div class="card-icon balance">
                                <i class="fas fa-wallet"></i>
                            </div>
                            <div>
                                <h3 class="card-title">Balance</h3>
                                <p class="card-value" id="reportBalance">Rp 0</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="chart-container">
                    <h3><i class="fas fa-chart-area"></i> Financial Trends</h3>
                    <canvas id="trendChart"></canvas> </div>

                <div class="form-container">    
                    <button class="btn btn-success" onclick="downloadReport()">
                        <i class="fas fa-download"></i> Download Report PDF
                    </button>
                </div>
            </section>
        </div>
    </div>

    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); display: flex; align-items: center; justify-content: center; z-index: 9999;">
        <div style="background: rgba(0, 0, 0, 0.8); padding: 2rem; border-radius: 10px; text-align: center; color: white;">
            <i class="fas fa-spinner fa-spin" style="font-size: 2rem; margin-bottom: 1rem;"></i>
            <br><span id="loadingMessage">Loading...</span>
        </div>
    </div>

    <div class="footer">
        Copyright &copy; 2025 Politeknik Statistika STIS<br>
        Created by Ahmad Adib Husaini Al Munawwar (adibhusaini10@gmail.com)
    </div>

    <script src="frontend.js"></script>
</body>
</html>