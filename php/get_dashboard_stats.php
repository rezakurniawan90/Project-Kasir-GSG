<?php
require_once 'db_connect.php';

header('Content-Type: application/json');

try {
    // Get total barang count
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM barang");
    $totalBarang = $stmt->fetch()['total'];
    
    // Get total stok
    $stmt = $pdo->query("SELECT SUM(jumlah_stok) as total FROM barang");
    $totalStok = $stmt->fetch()['total'] ?? 0;
    
    // Get today's sales
    $stmt = $pdo->query("SELECT SUM(total_harga) as total FROM penjualan WHERE DATE(tanggal) = CURDATE()");
    $penjualanHari = $stmt->fetch()['total'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_barang' => $totalBarang,
            'total_stok' => $totalStok,
            'penjualan_hari' => $penjualanHari
        ]
    ]);
    
} catch(PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
