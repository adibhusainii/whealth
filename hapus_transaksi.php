<?php
// hapus_transaksi.php - Enhanced with user isolation
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

    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id <= 0) {
        throw new Exception('Invalid transaction ID');
    }

    // Check if the transaction belongs to the logged-in user before deleting
    $check_stmt = $db->prepare("SELECT id_transaksi FROM transaksi WHERE id_transaksi = ? AND user_id = ?");
    $check_stmt->execute([$id, $user_id]);

    if ($check_stmt->rowCount() === 0) {
        throw new Exception('Transaction not found or does not belong to you');
    }

    $stmt = $db->prepare("DELETE FROM transaksi WHERE id_transaksi = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Transaction successfully deleted'
        ]);
    } else {
        throw new Exception('Failed to delete transaction or no changes made');
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