<?php
// setup_database.php
include_once 'config.php';

header('Content-Type: application/json');

try {
    // Create database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Tidak dapat terhubung ke database');
    }

    // Create tables if they don't exist
    $tables_created = [];
    $tables_errors = [];

    // Create transaksi table
    try {
        $create_transaksi = "
        CREATE TABLE IF NOT EXISTS `transaksi` (
            `id_transaksi` int(11) NOT NULL AUTO_INCREMENT,
            `tipe` enum('pemasukan','pengeluaran') NOT NULL,
            `kategori` varchar(100) NOT NULL,
            `deskripsi` text,
            `jumlah` decimal(15,2) NOT NULL,
            `tanggal` date NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_transaksi`),
            KEY `idx_tipe` (`tipe`),
            KEY `idx_kategori` (`kategori`),
            KEY `idx_tanggal` (`tanggal`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($create_transaksi);
        $tables_created[] = 'transaksi';
    } catch (Exception $e) {
        $tables_errors[] = 'transaksi: ' . $e->getMessage();
    }

    // Create anggaran table
    try {
        $create_anggaran = "
        CREATE TABLE IF NOT EXISTS `anggaran` (
            `id_anggaran` int(11) NOT NULL AUTO_INCREMENT,
            `kategori` varchar(100) NOT NULL,
            `jumlah_anggaran` decimal(15,2) NOT NULL,
            `periode` enum('bulanan','tahunan') NOT NULL,
            `tahun` int(4) NOT NULL,
            `bulan` int(2) NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id_anggaran`),
            KEY `idx_kategori` (`kategori`),
            KEY `idx_periode` (`periode`),
            KEY `idx_tahun_bulan` (`tahun`, `bulan`),
            UNIQUE KEY `unique_budget` (`kategori`, `periode`, `tahun`, `bulan`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($create_anggaran);
        $tables_created[] = 'anggaran';
    } catch (Exception $e) {
        $tables_errors[] = 'anggaran: ' . $e->getMessage();
    }

    // Check if tables exist and get their info
    $table_info = [];
    $tables_to_check = ['transaksi', 'anggaran'];
    
    foreach ($tables_to_check as $table) {
        try {
            $stmt = $db->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $count_stmt = $db->query("SELECT COUNT(*) as count FROM `$table`");
            $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            $table_info[$table] = [
                'exists' => true,
                'columns' => count($columns),
                'records' => intval($count)
            ];
        } catch (Exception $e) {
            $table_info[$table] = [
                'exists' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    // Insert sample data if tables are empty
    $sample_data_inserted = [];
    
    // Sample transactions
    if ($table_info['transaksi']['records'] == 0) {
        try {
            $sample_transactions = [
                ['pemasukan', 'Gaji', 'Gaji bulanan', 5000000, date('Y-m-01')],
                ['pengeluaran', 'Makanan', 'Belanja grocery', 500000, date('Y-m-02')],
                ['pengeluaran', 'Transport', 'Bensin motor', 100000, date('Y-m-03')],
                ['pengeluaran', 'Hiburan', 'Nonton bioskop', 75000, date('Y-m-04')],
                ['pemasukan', 'Freelance', 'Project website', 1500000, date('Y-m-05')]
            ];
            
            $stmt = $db->prepare("INSERT INTO transaksi (tipe, kategori, deskripsi, jumlah, tanggal) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($sample_transactions as $trans) {
                $stmt->execute($trans);
            }
            
            $sample_data_inserted[] = 'transaksi (5 sample records)';
        } catch (Exception $e) {
            $tables_errors[] = 'sample transaksi: ' . $e->getMessage();
        }
    }
    
    // Sample budgets
    if ($table_info['anggaran']['records'] == 0) {
        try {
            $current_year = date('Y');
            $current_month = date('n');
            
            $sample_budgets = [
                ['Makanan', 1000000, 'bulanan', $current_year, $current_month],
                ['Transport', 300000, 'bulanan', $current_year, $current_month],
                ['Hiburan', 200000, 'bulanan', $current_year, $current_month],
                ['Investasi', 10000000, 'tahunan', $current_year, null]
            ];
            
            $stmt = $db->prepare("INSERT INTO anggaran (kategori, jumlah_anggaran, periode, tahun, bulan) VALUES (?, ?, ?, ?, ?)");
            
            foreach ($sample_budgets as $budget) {
                $stmt->execute($budget);
            }
            
            $sample_data_inserted[] = 'anggaran (4 sample records)';
        } catch (Exception $e) {
            $tables_errors[] = 'sample anggaran: ' . $e->getMessage();
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed successfully',
        'tables_created' => $tables_created,
        'tables_info' => $table_info,
        'sample_data_inserted' => $sample_data_inserted,
        'errors' => $tables_errors,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database setup failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>