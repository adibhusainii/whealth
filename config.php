<?php
define('CONFIG_ONLY', true);
// config.php
class Database {
    private $host = "localhost";
    private $db_name = "projec15_project_keuangan";
    private $username = "projec15_root";
    private $password = "@kaesquare123";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                                 $this->username, 
                                 $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch(PDOException $exception) {
            error_log("Database Connection Error: " . $exception->getMessage());
            return null;
        }
        return $this->conn;
    }
}

// Function to verify tables exist
function verifyExistingTables() {
    $database = new Database();
    $db = $database->getConnection();
    
    $tables = [
        'transaksi' => false,
        'anggaran' => false
    ];
    
    if ($db) {
        try {
            foreach (array_keys($tables) as $table) {
                $stmt = $db->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                $tables[$table] = $stmt->rowCount() > 0;
            }
        } catch (Exception $e) {
            error_log("Error checking tables: " . $e->getMessage());
        }
    }
    
    return $tables;
}

// Initialize database connection (only if this file is included in a page context)
if (!defined('CONFIG_ONLY')) {
    $database = new Database();
    $pdo = $database->getConnection();
    $db_connected = ($pdo !== null);

    $message = "";
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if ($db_connected) {
            try {
                if (isset($_POST['action'])) {
                    switch ($_POST['action']) {
                        case 'add_transaction':
                            $stmt = $pdo->prepare("INSERT INTO transaksi (tipe, kategori, deskripsi, jumlah, tanggal) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $_POST['tipe'],
                                $_POST['kategori'],
                                $_POST['deskripsi'],
                                $_POST['jumlah'],
                                date('Y-m-d')
                            ]);
                            $message = "Transaksi berhasil ditambahkan!";
                            break;
                            
                        case 'add_budget':
                            $stmt = $pdo->prepare("INSERT INTO anggaran (kategori, jumlah_anggaran, periode, tahun, bulan) VALUES (?, ?, ?, ?, ?)");
                            $bulan = $_POST['periode'] == 'bulanan' ? date('n') : null;
                            $stmt->execute([
                                $_POST['kategori'],
                                $_POST['jumlah'],
                                $_POST['periode'],
                                date('Y'),
                                $bulan
                            ]);
                            $message = "Anggaran berhasil ditambahkan!";
                            break;
                            
                        case 'delete_transaction':
                            $stmt = $pdo->prepare("DELETE FROM transaksi WHERE id_transaksi = ?");
                            $stmt->execute([$_POST['id']]);
                            $message = "Transaksi berhasil dihapus!";
                            break;
                            
                        case 'delete_budget':
                            $stmt = $pdo->prepare("DELETE FROM anggaran WHERE id_anggaran = ?");
                            $stmt->execute([$_POST['id']]);
                            $message = "Anggaran berhasil dihapus!";
                            break;
                    }
                }
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
            }
        } else {
            $message = "Database tidak terhubung!";
        }
    }

    // Ambil data dari database
    $transactions = [];
    $budgets = [];
    $summary = ['total_pemasukan' => 0, 'total_pengeluaran' => 0, 'saldo' => 0];

    if ($db_connected) {
        try {
            // Check if tables exist
            $tables = verifyExistingTables();
            
            if ($tables['transaksi']) {
                // Ambil transaksi
                $stmt = $pdo->query("SELECT * FROM transaksi ORDER BY tanggal DESC, id_transaksi DESC");
                $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Hitung summary
                $stmt = $pdo->query("SELECT tipe, SUM(jumlah) as total FROM transaksi GROUP BY tipe");
                $summary_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($summary_data as $row) {
                    if ($row['tipe'] == 'pemasukan') {
                        $summary['total_pemasukan'] = $row['total'];
                    } else {
                        $summary['total_pengeluaran'] = $row['total'];
                    }
                }
                $summary['saldo'] = $summary['total_pemasukan'] - $summary['total_pengeluaran'];
            }
            
            if ($tables['anggaran']) {
                // Ambil anggaran
                $stmt = $pdo->query("SELECT * FROM anggaran ORDER BY created_at DESC");
                $budgets = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
        } catch (Exception $e) {
            $error_message = "Error mengambil data: " . $e->getMessage();
            error_log($error_message);
        }
    }
}

// Format currency
function formatCurrency($amount) {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
?>  