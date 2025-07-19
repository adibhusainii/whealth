<?php
// search_suggestions.php - API untuk live search dan suggestions
include_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Koneksi database gagal');
    }

    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'search_transactions':
            searchTransactions($db);
            break;
        case 'kategori_suggestions':
            getKategoriSuggestions($db);
            break;
        case 'quick_stats':
            getQuickStats($db);
            break;
        default:
            throw new Exception('Action tidak valid');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function searchTransactions($db) {
    $query = trim($_GET['q'] ?? '');
    $type = $_GET['type'] ?? '';
    $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
    
    if (strlen($query) < 2) {
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'Query terlalu pendek'
        ]);
        return;
    }

    $where_conditions = [];
    $params = [];

    // Search dalam kategori dan deskripsi
    $where_conditions[] = "(kategori LIKE ? OR deskripsi LIKE ?)";
    $params[] = "%$query%";
    $params[] = "%$query%";

    // Filter berdasarkan tipe jika dipilih
    if (!empty($type) && in_array($type, ['pemasukan', 'pengeluaran'])) {
        $where_conditions[] = "tipe = ?";
        $params[] = $type;
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    $sql = "SELECT *, 
            CASE 
                WHEN kategori LIKE ? THEN 2
                WHEN deskripsi LIKE ? THEN 1
                ELSE 0
            END as relevance_score
            FROM transaksi 
            $where_clause 
            ORDER BY relevance_score DESC, tanggal DESC 
            LIMIT ?";

    // Add relevance parameters
    array_unshift($params, "%$query%", "%$query%");
    $params[] = $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Highlight search terms in results
    foreach ($results as &$result) {
        $result['kategori_highlighted'] = highlightText($result['kategori'], $query);
        $result['deskripsi_highlighted'] = highlightText($result['deskripsi'] ?? '', $query);
    }

    echo json_encode([
        'success' => true,
        'data' => $results,
        'query' => $query,
        'count' => count($results)
    ]);
}

function getKategoriSuggestions($db) {
    $query = trim($_GET['q'] ?? '');
    $type = $_GET['type'] ?? '';
    
    $where_conditions = ["kategori IS NOT NULL"];
    $params = [];

    if (!empty($query)) {
        $where_conditions[] = "kategori LIKE ?";
        $params[] = "%$query%";
    }

    if (!empty($type) && in_array($type, ['pemasukan', 'pengeluaran'])) {
        $where_conditions[] = "tipe = ?";
        $params[] = $type;
    }

    $where_clause = "WHERE " . implode(" AND ", $where_conditions);

    // Get kategori dengan statistik penggunaan
    $sql = "SELECT 
                kategori,
                COUNT(*) as usage_count,
                SUM(jumlah) as total_amount,
                AVG(jumlah) as avg_amount,
                MAX(tanggal) as last_used,
                tipe
            FROM transaksi 
            $where_clause
            GROUP BY kategori, tipe
            ORDER BY usage_count DESC, last_used DESC
            LIMIT 10";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format results
    $suggestions = [];
    foreach ($categories as $cat) {
        $suggestions[] = [
            'kategori' => $cat['kategori'],
            'tipe' => $cat['tipe'],
            'usage_count' => intval($cat['usage_count']),
            'total_amount' => floatval($cat['total_amount']),
            'avg_amount' => floatval($cat['avg_amount']),
            'last_used' => $cat['last_used'],
            'display_text' => $cat['kategori'] . " ({$cat['usage_count']} kali digunakan)"
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $suggestions,
        'query' => $query
    ]);
}

function getQuickStats($db) {
    // Quick stats untuk dashboard
    $today = date('Y-m-d');
    $month_start = date('Y-m-01');
    
    // Stats hari ini
    $today_stmt = $db->prepare("
        SELECT tipe, SUM(jumlah) as total 
        FROM transaksi 
        WHERE tanggal = ?
        GROUP BY tipe
    ");
    $today_stmt->execute([$today]);
    $today_data = $today_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Stats bulan ini
    $month_stmt = $db->prepare("
        SELECT tipe, SUM(jumlah) as total 
        FROM transaksi 
        WHERE tanggal >= ?
        GROUP BY tipe
    ");
    $month_stmt->execute([$month_start]);
    $month_data = $month_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Recent transaction count
    $recent_stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM transaksi 
        WHERE tanggal >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $recent_stmt->execute();
    $recent_count = $recent_stmt->fetch()['count'];

    echo json_encode([
        'success' => true,
        'today' => $today_data,
        'this_month' => $month_data,
        'recent_transactions' => intval($recent_count)
    ]);
}

function highlightText($text, $query) {
    if (empty($query) || empty($text)) {
        return $text;
    }
    
    $highlighted = preg_replace(
        '/(' . preg_quote($query, '/') . ')/i',
        '<mark>$1</mark>',
        $text
    );
    
    return $highlighted;
}
?>