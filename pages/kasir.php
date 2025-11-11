<?php
// require_once 'php/db_connect.php'; <-- Sudah tidak perlu

// Get all products (Logika ini tetap di sini untuk menampilkan daftar barang)
// Variabel $products akan didapat dari index.php
if (!isset($products)) { // Fallback jika $products tidak di-pass dari index.php
    try {
        // Gunakan $pdo dari index.php
        $stmt_fallback = $pdo->query("SELECT * FROM barang WHERE jumlah_stok > 0 ORDER BY nama_barang ASC");
        $products = $stmt_fallback->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $products = []; }
}


// Handle form submission SUDAH DIPINDAH KE INDEX.PHP

// Format currency (fungsi ini mungkin masih diperlukan di bagian HTML)
if (!function_exists('formatCurrency')) { // Cegah redeclare jika sudah ada di index.php
     function formatCurrency($amount) { return 'Rp ' . number_format($amount, 0, ',', '.'); }
}
?>

<?php if (isset($_SESSION['message'])): ?>
        <div class="p-3 rounded-lg text-xs flex items-center gap-2 <?= ($_SESSION['message_type'] ?? 'info') === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?> shadow-md mb-6">
            <i class="ri-<?= ($_SESSION['message_type'] ?? 'info') === 'success' ? 'check' : 'close' ?>-line"></i>
            <span><?= htmlspecialchars($_SESSION['message']) ?></span>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); // Hapus pesan setelah ditampilkan ?>
    <?php endif; ?>

    <form method="POST" action="index.php?page=kasir" class="bg-white p-6 rounded-lg shadow-md" 
          x-data="kasirApp(<?= htmlspecialchars(json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)" 
          x-init="init()" x-cloak>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            
            <div class="border border-gray-200 rounded-lg p-4 flex flex-col">
                <h3 class="text-sm font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="ri-boxing-line"></i> Daftar Barang Tersedia
                </h3>
                <div class="mb-4 relative">
                    <input type="text" 
                           x-model="searchQuery" 
                           @input.debounce.300ms="filterProducts()" 
                           placeholder="Cari nama atau kode barang..."
                           class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none pl-8">
                    <i class="ri-search-line absolute left-2 top-1/2 -translate-y-1/2 text-gray-400"></i>
                </div>
                <div class="product-list flex-1"> 
                    <template x-if="filteredProducts.length === 0">
                        <div class="text-center py-10 text-gray-500">
                             <i class="ri-inbox-line text-3xl text-gray-300 mb-3"></i>
                             <p class="text-xs font-medium" x-show="searchQuery === ''">Tidak ada barang tersedia</p>
                             <p class="text-xs font-medium" x-show="searchQuery !== ''">Barang tidak ditemukan</p>
                        </div>
                    </template>
                    <div class="space-y-3">
                        <template x-for="product in filteredProducts" :key="product.id">
                            <div class="product-item flex items-center justify-between p-3 border border-gray-200 rounded-lg"> 
                                <div class="flex-1">
                                    <h4 class="text-xs font-medium text-gray-800" x-text="product.nama_barang"></h4>
                                    <p class="text-[10px] text-gray-600">
                                        Kode: <span x-text="product.kode_barang"></span> | 
                                        Stok: <span x-text="product.jumlah_stok"></span>
                                    </p>
                                    <p class="text-[10px] font-semibold text-green-700" 
                                       :data-price="product.harga_barang" 
                                       x-text="formatCurrency(product.harga_barang)"></p>
                                </div> 
                                <input type="number" 
                                       :name="'qty_' + product.id" 
                                       class="w-20 h-10 text-center text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent" 
                                       value="0" min="0" 
                                       :max="product.jumlah_stok" 
                                       @input="calculateTotal()" required> </div> 
                        </template>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200 shadow-inner">
                    <h3 class="text-sm font-semibold text-gray-800 mb-4 flex items-center gap-2"><i class="ri-shopping-cart-line"></i> Ringkasan Pembayaran</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-medium text-gray-700">Total Harga:</span>
                            <span class="px-2 py-1 text-xs text-right font-semibold text-gray-900 border-b border-gray-300" x-text="formatCurrency(totalHarga)"></span>
                            <input type="hidden" name="total_harga_hidden" :value="totalHarga"> 
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] font-medium text-gray-700 mb-1">Total Bayar:</label>
                            <input type="number" id="total_bayar" name="total_bayar" min="0" step="100" required class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent" x-model.number="totalBayar" placeholder="0">
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[10px] font-medium text-gray-700">Kembalian:</span>
                            <span id="kembalian" class="text-[10px] font-semibold" :class="kembalian >= 0 ? 'text-green-600' : 'text-red-600'" x-text="formatCurrency(kembalian)"></span>
                        </div>
                        <div class="flex flex-col">
                            <label class="text-[10px] font-medium text-gray-700 mb-1">Nama Kasir:</label>
                            <input type="text" name="nama_kasir" placeholder="Masukkan nama kasir" value="<?= htmlspecialchars($_SESSION['admin_username'] ?? 'Kasir') ?>" required class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-green-500 focus:border-transparent">
                        </div>
                        <button type="submit" class="w-full py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-md transition-all duration-200 flex items-center justify-center gap-2 shadow-md disabled:opacity-50 disabled:cursor-not-allowed" :disabled="totalHarga <= 0 || totalBayar === null || totalBayar < totalHarga"> <i class="ri-check-double-line"></i> Proses Pembayaran
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script> 
        function kasirApp(initialProducts) {
            return {
                products: initialProducts || [], 
                filteredProducts: [], 
                searchQuery: '',      
                totalHarga: 0, 
                totalBayar: null, 
                get kembalian() { 
                    const th = parseFloat(this.totalHarga) || 0;
                    const tb = parseFloat(this.totalBayar) || 0;
                    const k = tb - th; 
                    return k >= 0 ? k : 0; 
                },
                init() { 
                    this.filteredProducts = [...this.products]; 
                    // Tunda kalkulasi awal sedikit agar nilai qty sempat terbaca
                    setTimeout(() => { this.calculateTotal(); }, 50); 
                },
                filterProducts() {
                    // ... (logika filter tidak berubah) ...
                    const query = this.searchQuery.toLowerCase().trim();
                    if (!query) { this.filteredProducts = [...this.products]; return; }
                    this.filteredProducts = this.products.filter(product => {
                        return product.nama_barang.toLowerCase().includes(query) || 
                               product.kode_barang.toLowerCase().includes(query);
                    });
                     // Setelah filter, hitung ulang total HANYA jika diperlukan
                     // this.calculateTotal(); // Mungkin tidak perlu di sini
                },
                calculateTotal() { 
                    let total = 0;
                    // Lebih baik ambil dari filteredProducts yang terlihat + qty nya
                    const visibleInputs = document.querySelectorAll('.product-list input[name^="qty_"]');

                    visibleInputs.forEach(input => {
                         const qty = parseInt(input.value) || 0;
                         if (qty > 0) {
                             const productId = input.name.replace('qty_', '');
                             const product = this.products.find(p => p.id == productId); 
                             if (product) {
                                 const price = parseFloat(product.harga_barang) || 0;
                                 total += qty * price;
                             }
                         }
                    });

                    // Update totalHarga hanya jika berbeda untuk mencegah loop tak terbatas
                    if (this.totalHarga !== total) {
                        this.totalHarga = total;
                    }
                },
                formatCurrency(amount) { 
                    const numAmount = Number(amount);
                    if (isNaN(numAmount)) { return 'Rp 0'; }
                    return 'Rp ' + numAmount.toLocaleString('id-ID'); 
                }
            }
        }
        // Pastikan script ini tidak duplikat dan Alpine.js dimuat di index.php
    </script>