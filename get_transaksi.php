<?php
// get_transaksi.php - Updated with user isolation
session_start();
include_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

    // Parameters untuk filtering - validate and sanitize
    $limit = isset($_GET['limit']) ? max(1, min(1000, intval($_GET['limit']))) : 50;
    $offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
    $tipe = isset($_GET['tipe']) ? trim($_GET['tipe']) : '';
    $kategori = isset($_GET['kategori']) ? trim($_GET['kategori']) : '';
    $start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
    $end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

    $where_conditions = ["user_id = ?"];
    $params = [$user_id];

    if (!empty($tipe) && in_array($tipe, ['pemasukan', 'pengeluaran'])) {
        $where_conditions[] = "tipe = ?";
        $params[] = $tipe;
    }

    if (!empty($kategori)) {
        $where_conditions[] = "kategori LIKE ?";
        $params[] = "%$kategori%";
    }

    if (!empty($start_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
        $where_conditions[] = "tanggal >= ?";
        $params[] = $start_date;
    }

    if (!empty($end_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        $where_conditions[] = "tanggal <= ?";
        $params[] = $end_date;
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    // Query untuk data transaksi - use direct values for LIMIT/OFFSET
    $sql = "SELECT * FROM transaksi $where_clause ORDER BY tanggal DESC, id_transaksi DESC LIMIT $limit OFFSET $offset";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $transaksi = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query untuk total count
    $count_sql = "SELECT COUNT(*) as total FROM transaksi $where_clause";
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // Query untuk summary
    $summary_sql = "SELECT 
        tipe,
        SUM(jumlah) as total
        FROM transaksi 
        $where_clause 
        GROUP BY tipe";
    $summary_stmt = $db->prepare($summary_sql);
    $summary_stmt->execute($params);
    $summary_data = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = [
        'total_pemasukan' => 0,
        'total_pengeluaran' => 0,
        'saldo' => 0
    ];

    foreach ($summary_data as $row) {
        if ($row['tipe'] == 'pemasukan') {
            $summary['total_pemasukan'] = floatval($row['total']);
        } else {
            $summary['total_pengeluaran'] = floatval($row['total']);
        }
    }
    $summary['saldo'] = $summary['total_pemasukan'] - $summary['total_pengeluaran'];

    echo json_encode([
        'success' => true,
        'data' => $transaksi,
        'total' => intval($total),
        'summary' => $summary,
        'user_id' => $user_id,
        'pagination' => [
            'limit' => $limit,
            'offset' => $offset,
            'total_pages' => ceil($total / $limit),
            'current_page' => floor($offset / $limit) + 1
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
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