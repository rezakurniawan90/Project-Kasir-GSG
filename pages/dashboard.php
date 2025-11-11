<?php
// pages/dashboard.php
// Pastikan variabel ini ada dari index.php:
// $total_barang, $total_stok, $penjualan_hari, rupiah()
// $chart_labels, $chart_data
?>
<!-- 
    PERUBAHAN: x-data dan x-init DIHAPUS.
    Kita akan menggunakan listener JavaScript standar.
-->
<div class="space-y-5">

    <!-- Statistik Kartu -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Total Barang -->
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500">Total Barang</p>
                    <p class="text-xl font-bold text-gray-800 mt-0.5"><?= $total_barang ?></p>
                </div>
                <div class="p-2.5 bg-blue-100 rounded-full">
                    <i class="ri-boxing-line text-lg text-blue-600"></i>
                </div>
            </div>
        </div>

        <!-- Total Stok -->
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500">Total Stok</p>
                    <p class="text-xl font-bold text-gray-800 mt-0.5"><?= $total_stok ?></p>
                </div>
                <div class="p-2.5 bg-green-100 rounded-full">
                    <i class="ri-archive-line text-lg text-green-600"></i>
                </div>
            </div>
        </div>

        <!-- Penjualan Hari Ini -->
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm hover:shadow transition">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500">Penjualan Hari Ini</p>
                    <p class="text-xl font-bold text-gray-800 mt-0.5"><?= rupiah($penjualan_hari) ?></p>
                </div>
                <div class="p-2.5 bg-purple-100 rounded-full">
                    <i class="ri-money-dollar-circle-line text-lg text-purple-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-800 mb-3.5 flex items-center gap-1.5">
            <i class="ri-flashlight-line text-blue-600"></i> Quick Actions
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <a href="index.php?page=barang"
                class="flex items-center justify-center gap-2 p-3 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition text-xs font-medium text-blue-700">
                <i class="ri-add-box-line text-base"></i>
                Tambah Barang
            </a>
            <a href="index.php?page=kasir"
                class="flex items-center justify-center gap-2 p-3 bg-green-50 border border-green-200 rounded-lg hover:bg-green-100 transition text-xs font-medium text-green-700">
                <i class="ri-cash-line text-base"></i>
                Buka Kasir
            </a>
            <a href="index.php?page=barang"
                class="flex items-center justify-center gap-2 p-3 bg-indigo-50 border border-indigo-200 rounded-lg hover:bg-indigo-100 transition text-xs font-medium text-indigo-700">
                <i class="ri-boxing-line text-base"></i>
                Kelola Barang
            </a>
        </div>
    </div>

    <!-- Wadah Grafik Penjualan (Tidak Berubah) -->
    <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-800 mb-3.5 flex items-center gap-1.5">
            <i class="ri-bar-chart-line text-blue-600"></i>
            Grafik Penjualan (7 Hari Terakhir)
        </h3>
        <div style="height: 250px;">
            <canvas id="salesChart"></canvas>
        </div>
    </div>

</div>

<!-- ========================================================== -->
<!-- --- BAGIAN JAVASCRIPT YANG DIPERBARUI --- -->
<!-- ========================================================== -->
<script>
    // 1. Tunggu hingga seluruh HTML halaman (DOM) selesai dimuat
    document.addEventListener('DOMContentLoaded', () => {
        
        // 2. Tentukan fungsi untuk menggambar chart
        function drawSalesChart() {
            // Ambil data dari PHP
            const chartLabels = <?= json_encode($chart_labels) ?>;
            const chartData = <?= json_encode($chart_data) ?>;
            const ctx = document.getElementById('salesChart');

            // Cek jika elemen canvas ada
            if (!ctx) {
                console.error("Elemen canvas 'salesChart' tidak ditemukan.");
                return;
            }

            // 3. GAMBAR CHARTNYA
            new Chart(ctx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [{
                        label: 'Total Penjualan (Rp)',
                        data: chartData,
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return 'Rp ' + value.toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) label += ': ';
                                    if (context.parsed.y !== null) {
                                        label += 'Rp ' + context.parsed.y.toLocaleString('id-ID');
                                    }
                                    return label;
                                }
                            }
                        },
                        legend: { display: false }
                    }
                }
            });
        }

        // 4. FUNGSI "PENGECEK" (POLLER)
        // Fungsi ini akan memeriksa apakah 'Chart' (dari Chart.js) sudah ada
        function waitForChartJs() {
            if (typeof Chart !== 'undefined') {
                // --- SUDAH SIAP! ---
                // Chart.js sudah selesai di-load, panggil fungsi gambar
                console.log("Chart.js sudah siap. Menggambar grafik...");
                drawSalesChart();
            } else {
                // --- BELUM SIAP ---
                // Coba lagi dalam 100ms
                console.log("Menunggu Chart.js... Coba lagi dalam 100ms.");
                setTimeout(waitForChartJs, 100);
            }
        }

        // 5. Mulai proses pengecekan
        waitForChartJs();

    });
</script>
<!-- ========================================================== -->
<!-- --- BATAS PERUBAHAN --- -->
<!-- ========================================================== -->

