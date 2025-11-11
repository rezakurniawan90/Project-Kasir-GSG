<?php
// --- TAMBAHKAN 3 BARIS INI UNTUK MENAMPILKAN ERROR ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// --- BATAS TAMBAHAN ---

// Memulai session dan koneksi DB
session_start();
require_once 'php/db_connect.php';

// --- BAGIAN PENTING: Memuat library Dompdf ---
require_once 'php/dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

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

// 2. Ambil data dari database (logika yang sama persis seperti Excel)
$total_penjualan_kotor = 0;
$total_modal_keluar = 0;
$total_keuntungan_bersih = 0;
$list_transaksi = [];

try {
    // Logika pengambilan data (sama persis dengan download_excel.php)
    $stmt = $pdo->prepare("SELECT no_faktur, tanggal, barang_dibeli, total_harga FROM penjualan WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal ASC");
    $stmt->execute([$filter_bulan, $filter_tahun]);
    $daftar_penjualan = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($daftar_penjualan as $penjualan) {
        $barang_list = json_decode($penjualan['barang_dibeli'], true);
        $modal_faktur_ini = 0;
        if (is_array($barang_list)) {
            foreach ($barang_list as $item) {
                $modal_item = floatval($item['harga_modal'] ?? 0) * intval($item['qty'] ?? 0);
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

// 3. Buat string HTML untuk di-render oleh Dompdf
$html = '
<html lang="id">
<head>
    <title>Laporan Keuntungan</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #dddddd; text-align: left; padding: 8px; }
        th { background-color: #f2f2f2; }
        h2, h3 { text-align: center; }
        .total { font-weight: bold; background-color: #e8f5e9; }
    </style>
</head>
<body>
    <h2>Laporan Keuntungan</h2>
    <h3>Bulan: '. $nama_bulan .' '. $filter_tahun .'</h3>
    
    <h3>Ringkasan</h3>
    <table>
        <tr>
            <th>Total Penjualan (Omzet)</th>
            <td>'. rupiah($total_penjualan_kotor) .'</td>
        </tr>
        <tr>
            <th>Total Modal (HPP)</th>
            <td>'. rupiah($total_modal_keluar) .'</td>
        </tr>
        <tr class="total">
            <th>Total Keuntungan Bersih</th>
            <td>'. rupiah($total_keuntungan_bersih) .'</td>
        </tr>
    </table>

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
        <tbody>';
            if (empty($list_transaksi)) {
                 $html .= '<tr><td colspan="5" style="text-align: center;">Tidak ada data untuk periode ini.</td></tr>';
            } else {
                 foreach($list_transaksi as $tx) {
                     $html .= '
                     <tr>
                         <td>'. htmlspecialchars($tx['no_faktur']) .'</td>
                         <td>'. htmlspecialchars($tx['tanggal']) .'</td>
                         <td>'. rupiah($tx['omzet']) .'</td>
                         <td>'. rupiah($tx['modal']) .'</td>
                         <td>'. rupiah($tx['keuntungan']) .'</td>
                     </tr>';
                 }
            }
$html .= '
        </tbody>
    </table>
</body>
</html>';

// 4. Proses pembuatan PDF
try {
    $options = new Options();
    $options->set('isRemoteEnabled', true); // Izinkan Dompdf memuat gambar dari URL (jika ada)
    // PERHATIAN: Baris di bawah mungkin perlu jika ada error font/rendering di hosting
    // $options->set('tempDir', sys_get_temp_dir()); 
    // $options->set('logOutputFile', sys_get_temp_dir() . '/dompdf.log.html');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait'); // Ukuran kertas: A4, orientasi: potret
    $dompdf->render();

    // 5. Kirim PDF ke browser untuk diunduh
    $filename = "Laporan_Keuntungan_{$nama_bulan}_{$filter_tahun}.pdf";
    // Hapus output buffer sebelum mengirim PDF (penting untuk menghindari corrupt)
    if (ob_get_level()) {
        ob_end_clean();
    }
    $dompdf->stream($filename, array("Attachment" => 1)); // 1 = download, 0 = tampilkan di browser
    exit; // Pastikan script berhenti setelah mengirim PDF

} catch (Exception $e) {
    // Jika Dompdf gagal, tampilkan errornya
    echo '<h1>Error saat membuat PDF:</h1>';
    echo '<pre>' . $e->getMessage() . '</pre>';
    echo '<pre>' . $e->getTraceAsString() . '</pre>';
    // Anda bisa juga log error ini ke file jika perlu
}
?>