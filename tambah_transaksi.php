<?php
// tambah_transaksi.php - Updated with user isolation
session_start();
include_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized. Please login first.'
    ]);
    exit;
}

$user_id = $_SESSION['id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $database = new Database();
        $db = $database->getConnection();

        if (!$db) {
            throw new Exception('Database connection failed');
        }

        // Get JSON input or form data
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            // Fallback to $_POST for form data
            $input = $_POST;
        }

        // Validasi input
        $tipe = trim($input['tipe'] ?? '');
        $kategori = trim($input['kategori'] ?? '');
        $jumlah = floatval($input['jumlah'] ?? 0);
        $deskripsi = trim($input['deskripsi'] ?? '');

        // Validasi tipe
        if (!in_array($tipe, ['pemasukan', 'pengeluaran'])) {
            throw new Exception('Invalid transaction type');
        }

        // Validasi jumlah
        if ($jumlah <= 0) {
            throw new Exception('Amount must be greater than 0');
        }

        // Validasi kategori
        if (empty($kategori)) {
            throw new Exception('Category cannot be empty');
        }

        // Insert transaksi dengan user_id
        $stmt = $db->prepare("INSERT INTO transaksi (user_id, tipe, kategori, deskripsi, jumlah, tanggal) VALUES (?, ?, ?, ?, ?, CURDATE())");
        $stmt->execute([$user_id, $tipe, $kategori, $deskripsi, $jumlah]);

        echo json_encode([
            'success' => true,
            'message' => 'Transaction successfully added',
            'id' => $db->lastInsertId(),
            'user_id' => $user_id
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>