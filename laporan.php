<?php
// laporan.php
include_once 'config.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    $periode = $_GET['periode'] ?? 'bulan-ini';
    $tahun = intval($_GET['tahun'] ?? date('Y'));
    $bulan = intval($_GET['bulan'] ?? date('n'));

    // Tentukan range tanggal berdasarkan periode
    switch ($periode) {
        case 'bulan-ini':
            $start_date = "$tahun-" . sprintf('%02d', $bulan) . "-01";
            $end_date = date('Y-m-t', strtotime($start_date));
            break;
        case '3-bulan':
            $start_month = $bulan - 2;
            $start_year = $tahun;
            if ($start_month <= 0) {
                $start_month += 12;
                $start_year -= 1;
            }
            $start_date = "$start_year-" . sprintf('%02d', $start_month) . "-01";
            $end_date = "$tahun-" . sprintf('%02d', $bulan) . "-" . date('t', mktime(0, 0, 0, $bulan, 1, $tahun));
            break;
        case 'tahun-ini':
            $start_date = "$tahun-01-01";
            $end_date = "$tahun-12-31";
            break;
        default:
            throw new Exception('Periode tidak valid');
    }

    // Query summary data
    $summary_stmt = $db->prepare("
        SELECT 
            tipe,
            SUM(jumlah) as total,
            COUNT(*) as jumlah_transaksi
        FROM transaksi 
        WHERE tanggal BETWEEN ? AND ?
        GROUP BY tipe
    ");
    $summary_stmt->execute([$start_date, $end_date]);
    $summary_data = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);

    $summary = [
        'total_pemasukan' => 0,
        'total_pengeluaran' => 0,
        'saldo' => 0,
        'jumlah_transaksi_pemasukan' => 0,
        'jumlah_transaksi_pengeluaran' => 0
    ];

    foreach ($summary_data as $row) {
        if ($row['tipe'] == 'pemasukan') {
            $summary['total_pemasukan'] = floatval($row['total']);
            $summary['jumlah_transaksi_pemasukan'] = intval($row['jumlah_transaksi']);
        } else {
            $summary['total_pengeluaran'] = floatval($row['total']);
            $summary['jumlah_transaksi_pengeluaran'] = intval($row['jumlah_transaksi']);
        }
    }
    $summary['saldo'] = $summary['total_pemasukan'] - $summary['total_pengeluaran'];

    // Query per kategori
    $kategori_stmt = $db->prepare("
        SELECT 
            kategori,
            tipe,
            SUM(jumlah) as total,
            COUNT(*) as jumlah_transaksi,
            AVG(jumlah) as rata_rata
        FROM transaksi 
        WHERE tanggal BETWEEN ? AND ?
        GROUP BY kategori, tipe
        ORDER BY total DESC
    ");
    $kategori_stmt->execute([$start_date, $end_date]);
    $kategori_data = $kategori_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query trend bulanan (untuk chart)
    $trend_stmt = $db->prepare("
        SELECT 
            DATE_FORMAT(tanggal, '%Y-%m') as bulan,
            tipe,
            SUM(jumlah) as total
        FROM transaksi 
        WHERE tanggal BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(tanggal, '%Y-%m'), tipe
        ORDER BY bulan
    ");
    $trend_stmt->execute([$start_date, $end_date]);
    $trend_data = $trend_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query top kategori pengeluaran
    $top_expense_stmt = $db->prepare("
        SELECT 
            kategori,
            SUM(jumlah) as total
        FROM transaksi 
        WHERE tanggal BETWEEN ? AND ? AND tipe = 'pengeluaran'
        GROUP BY kategori
        ORDER BY total DESC
        LIMIT 5
    ");
    $top_expense_stmt->execute([$start_date, $end_date]);
    $top_expenses = $top_expense_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'periode' => $periode,
        'start_date' => $start_date,
        'end_date' => $end_date,
        'summary' => $summary,
        'kategori_data' => $kategori_data,
        'trend_data' => $trend_data,
        'top_expenses' => $top_expenses
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>