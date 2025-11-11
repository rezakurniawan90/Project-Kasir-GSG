<?php
// Memulai session untuk keamanan (jika diperlukan) dan koneksi DB
session_start();
require_once 'php/db_connect.php';
// Memuat fungsi rupiah jika belum ada
if (!function_exists('rupiah')) {
    function rupiah($angka) {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

// 1. Ambil filter bulan dan tahun dari URL
$filter_bulan = $_GET['filter_bulan'] ?? date('m');
$filter_tahun = $_GET['filter_tahun'] ?? date('Y');
$nama_bulan = date('F', mktime(0, 0, 0, $filter_bulan, 10));

// 2. Set header untuk download Excel
$filename = "Laporan_Keuntungan_{$nama_bulan}_{$filter_tahun}.xls";
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");

// 3. Ambil data dari database (logika yang sama seperti di laporan.php)
$total_penjualan_kotor = 0;
$total_modal_keluar = 0;
$total_keuntungan_bersih = 0;
$list_transaksi = [];

try {
    $stmt = $pdo->prepare("SELECT no_faktur, tanggal, barang_dibeli, total_harga FROM penjualan WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal ASC");
    $stmt->execute([$filter_bulan, $filter_tahun]);
    $daftar_penjualan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($daftar_penjualan as $penjualan) {
        $barang_list = json_decode($penjualan['barang_dibeli'], true);
        $modal_faktur_ini = 0;
        $keuntungan_faktur_ini = 0;

        if (is_array($barang_list)) {
            foreach ($barang_list as $item) {
                $qty = $item['qty'] ?? 0;
                $harga_jual = $item['harga_jual'] ?? ($item['harga'] ?? 0);
                $harga_modal = $item['harga_modal'] ?? 0;
                
                $modal_item = floatval($harga_modal) * intval($qty);
                $modal_faktur_ini += $modal_item;
            }
        }
        $omzet_faktur_ini = floatval($penjualan['total_harga']);
        $keuntungan_faktur_ini = $omzet_faktur_ini - $modal_faktur_ini;
        
        $total_penjualan_kotor += $omzet_faktur_ini;
        $total_modal_keluar += $modal_faktur_ini;
        
        $list_transaksi[] = [
            'no_faktur' => $penjualan['no_faktur'],
            'tanggal' => date('d/m/Y H:i', strtotime($penjualan['tanggal'])),
            'omzet' => $omzet_faktur_ini,
            'modal' => $modal_faktur_ini,
            'keuntungan' => $keuntungan_faktur_ini
        ];
    }
    $total_keuntungan_bersih = $total_penjualan_kotor - $total_modal_keluar;
} catch (Exception $e) {
    die("Error mengambil data: " . $e->getMessage());
}

// 4. Buat output HTML
?>
<html lang="id">
<head>
    <title>Laporan Keuntungan</title>
    <style>
        body { font-family: sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #dddddd; text-align: left; padding: 8px; }
        th { background-color: #f2f2f2; }
        .total { font-weight: bold; }
    </style>
</head>
<body>
    <h2>Laporan Keuntungan Bulan: <?= $nama_bulan ?> <?= $filter_tahun ?></h2>
    
    <h3>Ringkasan</h3>
    <table>
        <tr>
            <th>Total Omzet</th>
            <td><?= rupiah($total_penjualan_kotor) ?></td>
        </tr>
        <tr>
            <th>Total Modal</th>
            <td><?= rupiah($total_modal_keluar) ?></td>
        </tr>
        <tr class="total">
            <th>Total Keuntungan</th>
            <td><?= rupiah($total_keuntungan_bersih) ?></td>
        </tr>
    </table>
    
    <br>

    <h3>Rincian Transaksi</h3>
    <table>
        <thead>
            <tr>
                <th>No Faktur</th>
                <th>Tanggal</th>
                <th>Omzet</th>
                <th>Modal</th>
                <th>Keuntungan</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($list_transaksi as $tx): ?>
            <tr>
                <td>'<?= htmlspecialchars($tx['no_faktur']) ?></td> <td><?= htmlspecialchars($tx['tanggal']) ?></td>
                <td><?= $tx['omzet'] ?></td>
                <td><?= $tx['modal'] ?></td>
                <td><?= $tx['keuntungan'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>