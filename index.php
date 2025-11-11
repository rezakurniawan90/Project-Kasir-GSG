<?php
// --- Mulai Kode Debug & Session ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

// --- Koneksi DB ---
require_once 'php/db_connect.php';

// --- Ambil Page Sekarang ---
$current_page = $_GET['page'] ?? 'dashboard';

// ==========================================================
// === Logika POST Kasir Dipindah ke SINI ===
// ==========================================================
if ($current_page === 'kasir' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ambil data produk (diperlukan untuk validasi dan JSON)
    // Kita butuh data TERBARU saat POST, jadi query lagi di sini
    try {
        $stmt_kasir_products_post = $pdo->query("SELECT * FROM barang"); // Ambil semua, termasuk yg stok 0 untuk cek nama
        $kasir_products_post = $stmt_kasir_products_post->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $kasir_products_post = []; }

    $no_faktur = 'INV' . date('YmdHis'); 
    $nama_kasir = trim($_POST['nama_kasir']);
    $total_harga = floatval($_POST['total_harga_hidden']); // Ambil dari hidden input
    $total_bayar = floatval($_POST['total_bayar']);
    $kembalian = $total_bayar - $total_harga;
    $selected_items = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'qty_') === 0 && intval($value) > 0) { // Pastikan value > 0
            $product_id = str_replace('qty_', '', $key);
            $selected_items[] = ['id' => $product_id, 'qty' => intval($value)];
        }
    }

    // Validasi
    if (empty($selected_items)) {
        $_SESSION['message'] = 'Pilih minimal satu barang!';
        $_SESSION['message_type'] = 'error';
    } elseif ($total_bayar < $total_harga) {
        $_SESSION['message'] = 'Total bayar tidak mencukupi!';
        $_SESSION['message_type'] = 'error';
    } elseif (empty($nama_kasir)) {
        $_SESSION['message'] = 'Nama kasir harus diisi!';
        $_SESSION['message_type'] = 'error';
    } else {
        // Proses jika valid
        try {
            $pdo->beginTransaction(); // Mulai transaksi DB

            $barang_data = [];
            foreach ($selected_items as $item) {
                // Cari produk berdasarkan ID dari array $kasir_products_post
                $product = null;
                foreach ($kasir_products_post as $p) { 
                    if ($p['id'] == $item['id']) { $product = $p; break; }
                }

                if ($product) {
                    // Cek stok terbaru LAGI sebelum update (critical section)
                    $stmt_stok_check = $pdo->prepare("SELECT jumlah_stok FROM barang WHERE id = ? FOR UPDATE");
                    $stmt_stok_check->execute([$item['id']]);
                    $current_stok = $stmt_stok_check->fetchColumn();

                    if ($current_stok === false || $current_stok < $item['qty']) { 
                        throw new Exception('Stok barang ' . htmlspecialchars($product['nama_barang']) . ' tidak mencukupi (sisa: '.$current_stok.')!'); 
                    }
                    
                    $barang_data[] = [
                        'kode_barang' => $product['kode_barang'],
                        'nama_barang' => $product['nama_barang'],
                        'qty' => $item['qty'],
                        'harga_jual' => $product['harga_barang'], 
                        'harga_modal' => $product['harga_modal'], 
                        'total' => $product['harga_barang'] * $item['qty']
                    ];
                    // Update stok 
                    $stmt_stok = $pdo->prepare("UPDATE barang SET jumlah_stok = jumlah_stok - ? WHERE id = ?");
                    $stmt_stok->execute([$item['qty'], $item['id']]);
                } else {
                     throw new Exception('Produk dengan ID ' . $item['id'] . ' tidak ditemukan.'); 
                }
            }
            $barang_dibeli = json_encode($barang_data);
            
            // Insert penjualan
            $stmt = $pdo->prepare("INSERT INTO penjualan (no_faktur, nama_kasir, barang_dibeli, total_harga, total_bayar, kembalian, status, tanggal) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$no_faktur, $nama_kasir, $barang_dibeli, $total_harga, $total_bayar, $kembalian, 'Selesai']);
            
            $pdo->commit(); // Konfirmasi transaksi DB

            $_SESSION['message'] = 'Transaksi berhasil disimpan! Faktur: ' . $no_faktur;
            $_SESSION['message_type'] = 'success';
            
            // --- INI DIA REDIRECTNYA ---
            header('Location: index.php?page=penjualan'); 
            exit(); // Penting: Hentikan script setelah redirect

        } catch (Exception $e) {
            if ($pdo->inTransaction()) { // Hanya rollback jika transaksi aktif
               $pdo->rollBack(); 
            }
            $_SESSION['message'] = 'Error: ' . $e->getMessage();
            $_SESSION['message_type'] = 'error';
            // Redirect kembali ke kasir jika error
            header('Location: index.php?page=kasir');
            exit();
        }
    }
    // Jika ada error validasi awal, redirect kembali ke kasir dengan pesan
    if(isset($_SESSION['message']) && $_SESSION['message_type'] === 'error'){
        header('Location: index.php?page=kasir');
        exit();
    }
}
// ==========================================================
// === BATAS PEMINDAHAN LOGIKA POST KASIR ===
// ==========================================================


// --- Lanjutkan dengan logika index.php lainnya ---
// Redirect jika belum login (setelah cek session_start)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Pastikan tidak ada output sebelum header ini
    if (ob_get_level()) ob_end_clean(); // Hapus buffer jika ada
    header('Location: login.php');
    exit();
}

$current_user = $_SESSION['admin_username'] ?? 'Admin';

// Ambil data produk SEKALI saja di sini, untuk di-pass ke kasir.php
$products = []; // Default array kosong
if ($current_page === 'kasir') { // Hanya ambil jika halaman kasir
    try {
        $stmt_products_page = $pdo->query("SELECT * FROM barang WHERE jumlah_stok > 0 ORDER BY nama_barang ASC");
        $products = $stmt_products_page->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $products = []; }
}


// ... (Logika ambil data statistik dashboard tidak berubah) ...
try { $stmt = $pdo->query("SELECT COUNT(*) FROM barang"); $total_barang = $stmt->fetchColumn(); $stmt = $pdo->query("SELECT SUM(jumlah_stok) FROM barang"); $total_stok = $stmt->fetchColumn() ?? 0; $stmt = $pdo->query("SELECT SUM(total_harga) FROM penjualan WHERE DATE(tanggal) = CURDATE()"); $penjualan_hari = $stmt->fetchColumn() ?? 0;} catch (PDOException $e) { $total_barang=0; $total_stok=0; $penjualan_hari=0;}
// ... (Logika ambil data chart tidak berubah) ...
$chart_labels = []; $chart_data = []; try { $stmt_chart = $pdo->query("SELECT DATE_FORMAT(tanggal, '%Y-%m-%d') as tgl, SUM(total_harga) as total FROM penjualan WHERE tanggal >= CURDATE() - INTERVAL 6 DAY GROUP BY tgl ORDER BY tgl ASC"); $sales_data_raw = $stmt_chart->fetchAll(PDO::FETCH_KEY_PAIR); for ($i = 6; $i >= 0; $i--) { $date_key = date('Y-m-d', strtotime("-$i days")); $day_label = date('d/m', strtotime("-$i days")); $chart_labels[] = $day_label; $chart_data[] = $sales_data_raw[$date_key] ?? 0; }} catch (PDOException $e) { $chart_labels = ['Error']; $chart_data = [0]; }
function rupiah($angka) { return 'Rp ' . number_format($angka, 0, ',', '.'); }

?>
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kantin GSG</title> <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <style> body { font-family: 'Poppins', sans-serif; } .ri { font-size: 1.1rem; } [x-cloak] { display: none !important; } </style>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50 text-xs text-gray-700 min-h-screen" x-data="{ isSidebarOpen: window.innerWidth > 768 ? true : false }">
    
    <aside class="w-56 bg-white border-r border-gray-200 flex flex-col fixed inset-y-0 left-0 z-30 transition-transform duration-300 ease-in-out" :class="isSidebarOpen ? 'translate-x-0' : '-translate-x-full'">
        <div class="p-5 border-b border-gray-200"><h2 class="text-sm font-bold text-gray-800 flex items-center gap-2"><i class="ri-store-2-line text-blue-600"></i> Kantin GSG</h2></div>
        <nav class="flex-1 p-3 space-y-1">
             <a href="?page=dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition <?= $current_page == 'dashboard' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'hover:bg-gray-100 text-gray-600' ?>"><i class="ri-dashboard-line"></i> Dashboard</a>
             <a href="?page=barang" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition <?= $current_page == 'barang' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'hover:bg-gray-100 text-gray-600' ?>"><i class="ri-boxing-line"></i> Data Barang</a>
             <a href="?page=penjualan" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition <?= $current_page == 'penjualan' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'hover:bg-gray-100 text-gray-600' ?>"><i class="ri-shopping-cart-line"></i> Penjualan</a>
             <a href="?page=kasir" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition <?= $current_page == 'kasir' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'hover:bg-gray-100 text-gray-600' ?>"><i class="ri-cash-line"></i> Kasir</a>
             <a href="?page=laporan" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium transition <?= $current_page == 'laporan' ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'hover:bg-gray-100 text-gray-600' ?>"><i class="ri-pie-chart-line"></i> Laporan</a>
             <a href="logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-xs font-medium text-red-600 hover:bg-red-50 transition mt-6"><i class="ri-logout-box-line"></i> Logout</a>
        </nav>
    </aside>

    <div x-show="isSidebarOpen" @click="isSidebarOpen = false" x-transition:enter="transition-opacity ease-linear duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-linear duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="fixed inset-0 bg-black/50 z-20 md:hidden"></div>

    <main class="flex-1 flex flex-col transition-all duration-300 ease-in-out" :class="isSidebarOpen ? 'md:ml-56' : 'ml-0'">
        <header class="bg-white border-b border-gray-200 px-6 py-4 flex justify-between items-center">
             <div class="flex items-center gap-3"> <button @click="isSidebarOpen = !isSidebarOpen" class="p-1 rounded-full hover:bg-gray-100 text-gray-700"><i class="ri-menu-line text-base"></i></button> <h1 class="text-sm font-semibold text-gray-800"><?= ucwords(str_replace(['-', '_'], ' ', $current_page)) ?></h1></div>
             <div class="flex items-center gap-2 text-xs"><i class="ri-user-line text-gray-500"></i><span class="text-gray-600">Hi, <strong><?= htmlspecialchars($current_user) ?></strong></span></div>
        </header>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="mx-6 mt-4 p-3 rounded-lg text-xs flex items-center gap-2 <?= ($_SESSION['message_type'] ?? 'info') === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                <i class="ri-<?= ($_SESSION['message_type'] ?? 'info') === 'success' ? 'check' : 'error-warning' ?>-line"></i>
                <span><?= htmlspecialchars($_SESSION['message']) ?></span>
            </div>
            <?php // Hapus pesan flash SETELAH ditampilkan di kasir.php
                  // Jika pesan ini ada sebelum include kasir.php, dia akan terhapus di sana.
                  // Jika pesan ini untuk halaman lain (setelah redirect), dia akan tampil di load berikutnya.
                  // unset($_SESSION['message'], $_SESSION['message_type']); // Sebaiknya unset di halaman target (kasir.php)
            ?>
        <?php endif; ?>

        <div class="flex-1 p-6">
            <?php
            $pages = [
                'dashboard'    => 'pages/dashboard.php',
                'barang'       => 'pages/barang.php',
                'penjualan'    => 'pages/penjualan.php',
                'kasir'        => 'pages/kasir.php',
                'laporan'      => 'pages/laporan.php', 
            ];
            $page_file = $pages[$current_page] ?? $pages['dashboard'];
            if (file_exists($page_file)) {
                // Variabel $products sekarang diteruskan ke kasir.php
                include $page_file; 
            } else {
                echo '<div class="text-center text-gray-500">Halaman tidak ditemukan. (<code>' . htmlspecialchars($page_file) . '</code>)</div>';
            }
            ?>
        </div>
    </main>
</body>
</html>