<?php
// anggaran.php - Enhanced with user isolation
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
        throw new Exception('Database connection failed');
    }

    $method = $_SERVER['REQUEST_METHOD'];

    switch ($method) {
        case 'GET':
            // Ambil data anggaran dengan perhitungan penggunaan
            $tahun = isset($_GET['tahun']) ? intval($_GET['tahun']) : date('Y');
            $bulan = isset($_GET['bulan']) ? intval($_GET['bulan']) : null;

            $where_conditions = ["a.user_id = ?", "a.tahun = ?"];
            $params = [$user_id, $tahun];

            if ($bulan !== null) {
                $where_conditions[] = "(a.bulan = ? OR a.bulan IS NULL)";
                $params[] = $bulan;
            }

            $where_clause = "WHERE " . implode(" AND ", $where_conditions);

            $stmt = $db->prepare("
                SELECT a.*,
                       COALESCE(t.total_terpakai, 0) as total_terpakai,
                       (a.jumlah_anggaran - COALESCE(t.total_terpakai, 0)) as sisa_anggaran,
                       CASE
                           WHEN COALESCE(t.total_terpakai, 0) > a.jumlah_anggaran THEN 'Exceeds Budget'
                           WHEN (COALESCE(t.total_terpakai, 0) / a.jumlah_anggaran * 100) > 80 THEN 'Almost Depleted'
                           ELSE 'Normal'
                       END as status
                FROM anggaran a
                LEFT JOIN (
                    SELECT kategori, SUM(jumlah) as total_terpakai
                    FROM transaksi
                    WHERE tipe = 'pengeluaran'
                    AND user_id = ?
                    AND YEAR(tanggal) = ?
                    " . ($bulan ? "AND MONTH(tanggal) = ?" : "") . "
                    GROUP BY kategori
                ) t ON a.kategori = t.kategori
                $where_clause
                ORDER BY a.kategori
            ");

            $exec_params = [$user_id, $tahun];
            if ($bulan) {
                $exec_params[] = $bulan;
            }
            $exec_params = array_merge($exec_params, $params);

            $stmt->execute($exec_params);
            $anggaran = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $anggaran,
                'total_records' => count($anggaran),
                'user_id' => $user_id
            ]);
            break;

        case 'POST':
            // Get JSON input or form data
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                // Fallback to $_POST for form data
                $input = $_POST;
            }

            // Tambah anggaran baru
            $kategori = trim($input['kategori'] ?? '');
            $jumlah = floatval($input['jumlah'] ?? 0);
            $periode = trim($input['periode'] ?? '');
            $tahun = intval($input['tahun'] ?? date('Y'));
            $bulan = $periode === 'bulanan' ? intval($input['bulan'] ?? date('n')) : null;

            // Validasi input
            if (empty($kategori)) {
                throw new Exception('Category cannot be empty');
            }

            if ($jumlah <= 0) {
                throw new Exception('Budget amount must be greater than 0');
            }

            if (!in_array($periode, ['bulanan', 'tahunan'])) {
                throw new Exception('Period must be monthly or yearly');
            }

            // Cek apakah anggaran sudah ada untuk user ini
            $check_stmt = $db->prepare("
                SELECT id_anggaran FROM anggaran
                WHERE user_id = ? AND kategori = ? AND periode = ? AND tahun = ?
                AND (bulan = ? OR (bulan IS NULL AND ? IS NULL))
            ");
            $check_stmt->execute([$user_id, $kategori, $periode, $tahun, $bulan, $bulan]);

            if ($check_stmt->rowCount() > 0) {
                throw new Exception('Budget for this category already exists in the same period');
            }

            $stmt = $db->prepare("
                INSERT INTO anggaran (user_id, kategori, jumlah_anggaran, periode, tahun, bulan)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user_id, $kategori, $jumlah, $periode, $tahun, $bulan]);

            echo json_encode([
                'success' => true,
                'message' => 'Budget successfully added',
                'id' => $db->lastInsertId()
            ]);
            break;

        case 'PUT':
            // Update anggaran
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                // Fallback untuk form data
                parse_str(file_get_contents('php://input'), $input);
            }

            $id = intval($input['id'] ?? 0);
            $kategori = trim($input['kategori'] ?? '');
            $jumlah = floatval($input['jumlah'] ?? 0);
            $periode = trim($input['periode'] ?? '');

            if ($id <= 0) {
                throw new Exception('Invalid budget ID');
            }

            if (empty($kategori)) {
                throw new Exception('Category cannot be empty');
            }

            if ($jumlah <= 0) {
                throw new Exception('Budget amount must be greater than 0');
            }

            if (!in_array($periode, ['bulanan', 'tahunan'])) {
                throw new Exception('Period must be monthly or yearly');
            }

            // Get current budget data dan pastikan milik user yang benar
            $current_stmt = $db->prepare("SELECT * FROM anggaran WHERE id_anggaran = ? AND user_id = ?");
            $current_stmt->execute([$id, $user_id]);
            $current_budget = $current_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$current_budget) {
                throw new Exception('Budget not found or does not belong to you');
            }

            // Check if kategori+periode combination already exists (exclude current record)
            $tahun = intval($input['tahun'] ?? $current_budget['tahun']);
            $bulan = $periode === 'bulanan' ? intval($input['bulan'] ?? date('n')) : null;

            $check_stmt = $db->prepare("
                SELECT id_anggaran FROM anggaran
                WHERE user_id = ? AND kategori = ? AND periode = ? AND tahun = ?
                AND (bulan = ? OR (bulan IS NULL AND ? IS NULL))
                AND id_anggaran != ?
            ");
            $check_stmt->execute([$user_id, $kategori, $periode, $tahun, $bulan, $bulan, $id]);

            if ($check_stmt->rowCount() > 0) {
                throw new Exception('Budget for this category already exists in the same period');
            }

            $stmt = $db->prepare("
                UPDATE anggaran
                SET kategori = ?, jumlah_anggaran = ?, periode = ?, tahun = ?, bulan = ?
                WHERE id_anggaran = ? AND user_id = ?
            ");
            $stmt->execute([$kategori, $jumlah, $periode, $tahun, $bulan, $id, $user_id]);

            if ($stmt->rowCount() >= 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Budget successfully updated'
                ]);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => 'No data changes'
                ]);
            }
            break;

        case 'DELETE':
            // Hapus anggaran
            $id = intval($_GET['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Invalid budget ID');
            }

            // Pastikan anggaran milik user yang benar
            $stmt = $db->prepare("DELETE FROM anggaran WHERE id_anggaran = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);

            if ($stmt->rowCount() > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Budget successfully deleted'
                ]);
            } else {
                throw new Exception('Budget not found or does not belong to you');
            }
            break;

        default:
            throw new Exception('HTTP method not supported');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => get_class($e)
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>