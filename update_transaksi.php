<?php
// update_transaksi.php - Updated with user isolation
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

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Koneksi database gagal');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get JSON input or form data
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            // Fallback to $_POST for form data
            $input = $_POST;
        }

        // Validate required fields
        $required_fields = ['id', 'tipe', 'kategori', 'jumlah'];
        foreach ($required_fields as $field) {
            if (!isset($input[$field]) || (is_string($input[$field]) && empty(trim($input[$field])))) {
                throw new Exception("Field '$field' tidak boleh kosong");
            }
        }

        $id = intval($input['id']);
        $tipe = trim($input['tipe']);
        $kategori = trim($input['kategori']);
        $jumlah = floatval($input['jumlah']);
        $deskripsi = trim($input['deskripsi'] ?? '');

        // Validation
        if ($id <= 0) {
            throw new Exception('ID tidak valid');
        }

        if (!in_array($tipe, ['pemasukan', 'pengeluaran'])) {
            throw new Exception('Tipe transaksi tidak valid');
        }

        if ($jumlah <= 0) {
            throw new Exception('Jumlah harus lebih dari 0');
        }

        if (empty($kategori)) {
            throw new Exception('Kategori tidak boleh kosong');
        }

        // Check if transaction exists and belongs to the current user
        $check_stmt = $db->prepare("SELECT id_transaksi FROM transaksi WHERE id_transaksi = ? AND user_id = ?");
        $check_stmt->execute([$id, $user_id]);
        
        if ($check_stmt->rowCount() === 0) {
            throw new Exception('Transaksi tidak ditemukan atau bukan milik Anda');
        }

        // Update transaction (only for the current user)
        $stmt = $db->prepare("UPDATE transaksi SET tipe = ?, kategori = ?, deskripsi = ?, jumlah = ? WHERE id_transaksi = ? AND user_id = ?");
        $result = $stmt->execute([$tipe, $kategori, $deskripsi, $jumlah, $id, $user_id]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Transaksi berhasil diperbarui',
                'updated_id' => $id,
                'user_id' => $user_id
            ]);
        } else {
            throw new Exception('Gagal memperbarui transaksi');
        }

    } else {
        throw new Exception('Method HTTP tidak didukung. Gunakan POST.');
    }

} catch (Exception $e) {
    error_log("Update Transaction Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e)
    ]);
} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>