<?php
// (Asumsikan $pdo sudah ada dari index.php)
// (Asumsikan fungsi rupiah() sudah ada di index.php)

// --- BAGIAN LAPORAN BULANAN (Tidak Berubah) ---
$filter_bulan = $_GET['filter_bulan'] ?? date('m');
$filter_tahun = $_GET['filter_tahun'] ?? date('Y');
$total_penjualan_bulanan = 0;
$total_modal_bulanan = 0;
$total_keuntungan_bulanan = 0;
$list_transaksi_bulanan = [];
try {
    $stmt_bulan = $pdo->prepare("SELECT no_faktur, tanggal, barang_dibeli, total_harga FROM penjualan WHERE MONTH(tanggal) = ? AND YEAR(tanggal) = ? ORDER BY tanggal DESC");
    $stmt_bulan->execute([$filter_bulan, $filter_tahun]);
    $daftar_penjualan_bulanan = $stmt_bulan->fetchAll(PDO::FETCH_ASSOC);
    foreach ($daftar_penjualan_bulanan as $penjualan) {
        $barang_list = json_decode($penjualan['barang_dibeli'], true);
        $modal_faktur_ini = 0; $keuntungan_faktur_ini = 0;
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
        $total_penjualan_bulanan += $omzet_faktur_ini;
        $total_modal_bulanan += $modal_faktur_ini;
        $list_transaksi_bulanan[] = [ 'no_faktur' => $penjualan['no_faktur'], 'tanggal' => $penjualan['tanggal'], 'keuntungan' => $keuntungan_faktur_ini ];
    }
    $total_keuntungan_bulanan = $total_penjualan_bulanan - $total_modal_bulanan;
} catch (Exception $e) { /* Handle error jika perlu */ }

// --- BAGIAN LAPORAN HARIAN (Tidak Berubah) ---
$tanggal_hari_ini = date('Y-m-d'); 
$total_penjualan_harian = 0;
$total_modal_harian = 0;
$total_keuntungan_harian = 0;
$list_transaksi_harian = [];
try {
    $stmt_hari = $pdo->prepare("SELECT no_faktur, tanggal, barang_dibeli, total_harga FROM penjualan WHERE DATE(tanggal) = ? ORDER BY tanggal DESC");
    $stmt_hari->execute([$tanggal_hari_ini]);
    $daftar_penjualan_harian = $stmt_hari->fetchAll(PDO::FETCH_ASSOC);
    foreach ($daftar_penjualan_harian as $penjualan_harian) {
        $barang_list_harian = json_decode($penjualan_harian['barang_dibeli'], true);
        $modal_faktur_harian = 0; $keuntungan_faktur_harian = 0;
        if (is_array($barang_list_harian)) {
            foreach ($barang_list_harian as $item_harian) {
                $qty_harian = $item_harian['qty'] ?? 0;
                $harga_jual_harian = $item_harian['harga_jual'] ?? ($item_harian['harga'] ?? 0); 
                $harga_modal_harian = $item_harian['harga_modal'] ?? 0;
                $modal_item_harian = floatval($harga_modal_harian) * intval($qty_harian);
                $modal_faktur_harian += $modal_item_harian;
            }
        }
        $omzet_faktur_harian = floatval($penjualan_harian['total_harga']);
        $keuntungan_faktur_harian = $omzet_faktur_harian - $modal_faktur_harian;
        $total_penjualan_harian += $omzet_faktur_harian;
        $total_modal_harian += $modal_faktur_harian;
        $list_transaksi_harian[] = [ 'no_faktur' => $penjualan_harian['no_faktur'], 'tanggal' => $penjualan_harian['tanggal'], 'omzet' => $omzet_faktur_harian, 'modal' => $modal_faktur_harian, 'keuntungan' => $keuntungan_faktur_harian ];
    }
    $total_keuntungan_harian = $total_penjualan_harian - $total_modal_harian;
} catch (Exception $e) { /* Handle error jika perlu */ }
?>

<div class="space-y-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
         <h2 class="text-base font-semibold text-gray-800 flex items-center gap-1.5">
            <i class="ri-pie-chart-line text-blue-600 text-base"></i> Laporan
        </h2>
    </div>

    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
        <h3 class="text-sm font-semibold text-gray-800 mb-3 border-b pb-2">Ringkasan Hari Ini (<?= date('d F Y') ?>)</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-gray-50 p-4 rounded-lg border"><p class="text-xs font-medium text-gray-500">Total Penjualan (Omzet)</p><p class="text-lg font-bold text-gray-800 mt-0.5"><?= rupiah($total_penjualan_harian) ?></p></div>
            <div class="bg-gray-50 p-4 rounded-lg border"><p class="text-xs font-medium text-gray-500">Total Modal (HPP)</p><p class="text-lg font-bold text-gray-800 mt-0.5"><?= rupiah($total_modal_harian) ?></p></div>
            <div class="bg-green-50 p-4 rounded-lg border border-green-200"><p class="text-xs font-medium text-green-700">Estimasi Keuntungan (Profit)</p><p class="text-lg font-bold text-green-800 mt-0.5"><?= rupiah($total_keuntungan_harian) ?></p></div>
        </div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b">
             <h3 class="text-sm font-semibold text-gray-800">Rincian Transaksi Hari Ini (<?= date('d F Y') ?>)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-700">
                    <tr>
                        <th class="px-3 py-2">Waktu</th>
                        <th class="px-3 py-2">No Faktur</th>
                        <th class="px-3 py-2">Omzet</th>
                        <th class="px-3 py-2">Modal</th>
                        <th class="px-3 py-2">Keuntungan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-xs">
                    <?php if (empty($list_transaksi_harian)): ?>
                         <tr><td colspan="5" class="text-center p-4 text-gray-500">Belum ada transaksi hari ini.</td></tr>
                    <?php else: ?>
                        <?php foreach($list_transaksi_harian as $tx_hari): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2"><?= date('H:i:s', strtotime($tx_hari['tanggal'])) ?></td>
                            <td class="px-3 py-2 font-mono"><?= htmlspecialchars($tx_hari['no_faktur']) ?></td>
                            <td class="px-3 py-2"><?= rupiah($tx_hari['omzet']) ?></td>
                            <td class="px-3 py-2"><?= rupiah($tx_hari['modal']) ?></td>
                            <td class="px-3 py-2 font-medium text-green-700"><?= rupiah($tx_hari['keuntungan']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
         <h3 class="text-sm font-semibold text-gray-800 mb-3">Laporan Bulanan</h3>
        <form method="GET" action="index.php" class="flex flex-col sm:flex-row gap-3 items-center">
            <input type="hidden" name="page" value="laporan">
            <div class="flex-1 w-full"><label class="text-[11px] font-medium text-gray-700">Bulan</label><select name="filter_bulan" class="w-full text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none px-3 py-1.5"><?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= str_pad($i, 2, '0', STR_PAD_LEFT) ?>" <?= $i == $filter_bulan ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $i, 10)) ?></option><?php endfor; ?></select></div>
            <div class="flex-1 w-full"><label class="text-[11px] font-medium text-gray-700">Tahun</label><select name="filter_tahun" class="w-full text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none px-3 py-1.5"><option value="2025" <?= '2025' == $filter_tahun ? 'selected' : '' ?>>2025</option><option value="2024" <?= '2024' == $filter_tahun ? 'selected' : '' ?>>2024</option></select></div>
            <button type="submit" class="w-full sm:w-auto px-4 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-md transition flex items-center justify-center gap-1.5 mt-4 sm:mt-0"><i class="ri-filter-3-line"></i> Filter</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm"><p class="text-xs font-medium text-gray-500">Total Penjualan (Omzet)</p><p class="text-xl font-bold text-gray-800 mt-0.5"><?= rupiah($total_penjualan_bulanan) ?></p></div>
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm"><p class="text-xs font-medium text-gray-500">Total Modal (HPP)</p><p class="text-xl font-bold text-gray-800 mt-0.5"><?= rupiah($total_modal_bulanan) ?></p></div>
        <div class="bg-green-50 p-5 rounded-lg border border-green-200 shadow-sm"><p class="text-xs font-medium text-green-700">Estimasi Keuntungan (Profit)</p><p class="text-xl font-bold text-green-800 mt-0.5"><?= rupiah($total_keuntungan_bulanan) ?></p></div>
    </div>
    
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b">
             <h3 class="text-sm font-semibold text-gray-800">Rincian Transaksi (<?= date('F Y', mktime(0,0,0,$filter_bulan,1,$filter_tahun)) ?>)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-700">
                    <tr>
                        <th class="px-3 py-2">Tanggal</th>
                        <th class="px-3 py-2">No Faktur</th>
                        <th class="px-3 py-2">Keuntungan</th> 
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 text-xs">
                    <?php if (empty($list_transaksi_bulanan)): ?>
                         <tr><td colspan="3" class="text-center p-4 text-gray-500">Tidak ada data untuk periode ini.</td></tr>
                    <?php else: ?>
                        <?php foreach($list_transaksi_bulanan as $tx_bulan): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2"><?= date('d/m/Y H:i', strtotime($tx_bulan['tanggal'])) ?></td>
                            <td class="px-3 py-2 font-mono"><?= htmlspecialchars($tx_bulan['no_faktur']) ?></td>
                            <td class="px-3 py-2 font-medium text-green-700"><?= rupiah($tx_bulan['keuntungan']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mt-6">
        <h3 class="text-sm font-semibold text-gray-800 mb-3">Download Laporan Bulanan</h3>
        <div class="flex flex-col sm:flex-row gap-3">
            <a href="download_excel.php?filter_bulan=<?= $filter_bulan ?>&filter_tahun=<?= $filter_tahun ?>" class="flex-1 text-center py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-md transition flex items-center justify-center gap-1.5"><i class="ri-file-excel-2-line"></i> Download Excel</a>
            <a href="download_pdf.php?filter_bulan=<?= $filter_bulan ?>&filter_tahun=<?= $filter_tahun ?>" class="flex-1 text-center py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-md transition flex items-center justify-center gap-1.5"><i class="ri-file-pdf-line"></i> Download PDF</a>
        </div>
    </div>

</div>