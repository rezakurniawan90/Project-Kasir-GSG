<?php
require_once 'php/db_connect.php';

// Fungsi format
if (!function_exists('rupiah')) {
    function rupiah($angka)
    {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}
if (!function_exists('formatDate')) {
    function formatDate($dateString)
    {
        return date('d/m/Y H:i', strtotime($dateString));
    }
}

// Ambil data penjualan
try {
    $stmt = $pdo->query("SELECT * FROM penjualan ORDER BY tanggal DESC");
    $penjualan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $penjualan_list = [];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Penjualan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            font-size: 0.75rem;
            /* Ukuran font kecil (12px) */
        }

        .backdrop-blur {
            backdrop-filter: blur(8px);
        }

        [x-cloak] {
            display: none;
        }

        .receipt {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            line-height: 1.4;
            max-width: 250px;
            white-space: pre-line;
        }

        @media print {
            body * {
                visibility: hidden;
            }

            .receipt,
            .receipt * {
                visibility: visible;
            }

            .receipt {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            .no-print {
                display: none;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div x-data="penjualanCRUD()" x-init="init()" class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4" x-cloak>
        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
            <h2 class="text-base font-semibold text-gray-800 flex items-center gap-1.5">
                <i class="ri-shopping-cart-line text-blue-600 text-base"></i> Data Penjualan
            </h2>
        </div>

        <!-- Tabel -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-700">
                        <tr>
                            <th class="px-3 py-2">No</th>
                            <th class="px-3 py-2">Faktur</th>
                            <th class="px-3 py-2">Kasir</th>
                            <th class="px-3 py-2">Tanggal</th>
                            <th class="px-3 py-2">Total</th>
                            <th class="px-3 py-2">Bayar</th>
                            <th class="px-3 py-2">Kembali</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-xs">
                        <?php if (empty($penjualan_list)): ?>
                            <tr>
                                <td colspan="9" class="text-center py-8 text-gray-500">
                                    <div class="flex flex-col items-center">
                                        <i class="ri-shopping-cart-line text-2xl text-gray-300 mb-2"></i>
                                        <p class="font-medium text-xs">Belum ada data penjualan</p>
                                        <p class="text-[10px] text-gray-400 mt-1">Mulai transaksi di halaman Kasir</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($penjualan_list as $index => $p): ?>
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-3 py-2 font-medium text-gray-900"><?= $index + 1 ?></td>
                                    <td class="px-3 py-2 font-mono text-gray-700">
                                        <?= htmlspecialchars($p['no_faktur']) ?>
                                    </td>
                                    <td class="px-3 py-2 text-gray-800">
                                        <?= htmlspecialchars($p['nama_kasir']) ?>
                                    </td>
                                    <td class="px-3 py-2 text-gray-600">
                                        <?= formatDate($p['tanggal']) ?>
                                    </td>
                                    <td class="px-3 py-2 font-medium text-gray-800">
                                        <?= rupiah($p['total_harga']) ?>
                                    </td>
                                    <td class="px-3 py-2 font-medium text-green-700">
                                        <?= rupiah($p['total_bayar']) ?>
                                    </td>
                                    <td class="px-3 py-2 font-medium text-blue-700">
                                        <?= rupiah($p['kembalian']) ?>
                                    </td>
                                    <td class="px-3 py-2">
                                        <?php
                                        $status = strtolower($p['status']);
                                        $badge = match ($status) {
                                            'lunas'     => 'bg-green-100 text-green-700',
                                            'pending'   => 'bg-yellow-100 text-yellow-700',
                                            'batal'     => 'bg-red-100 text-red-700',
                                            default     => 'bg-gray-100 text-gray-700'
                                        };
                                        ?>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium <?= $badge ?>">
                                            <?= ucfirst(htmlspecialchars($p['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <div class="flex items-center justify-center gap-1">
                                            <button @click="showDetail(<?= htmlspecialchars(json_encode($p, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)"
                                                class="p-1 text-blue-600 hover:bg-blue-50 rounded transition"
                                                title="Detail">
                                                <i class="ri-eye-line text-sm"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal Detail dan Cetak Struk -->
        <div x-show="detailModal"
            class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @click.self="detailModal = false">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-xs p-5">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="text-sm font-semibold text-gray-800">Detail Struk</h3>
                    <button @click="detailModal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-base"></i>
                    </button>
                </div>
                <div class="receipt" x-text="receiptContent"></div>
                <div class="no-print flex gap-2 mt-3">
                    <button @click="printReceipt"
                        class="flex-1 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-md transition flex items-center justify-center gap-1">
                        <i class="ri-printer-line text-sm"></i> Cetak
                    </button>
                    <button @click="detailModal = false"
                        class="flex-1 py-1.5 bg-gray-600 hover:bg-gray-700 text-white text-xs font-medium rounded-md transition">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function penjualanCRUD() {
            return {
                detailModal: false,
                receiptContent: '',
                init() {
                    // Inisialisasi jika diperlukan
                },
                showDetail(penjualan) {
                    const barang_data = penjualan.barang_dibeli ? JSON.parse(penjualan.barang_dibeli) : [];
                    let content = "---------------------------\n";
                    content += "KANTIN GSG\n";
                    content += "---------------------------\n";
                    content += `Nomor Faktur : ${penjualan.no_faktur}\n`;
                    content += `Nama Kasir   : ${penjualan.nama_kasir}\n`;
                    content += `Tanggal      : ${this.formatDate(penjualan.tanggal)}\n`;
                    content += "---------------------------\n";
                    content += "Barang:\n";
                    if (barang_data && barang_data.length > 0) {
                        barang_data.forEach(item => {
                            content += `- ${item.nama_barang} x${item.qty} = ${this.rupiah(item.total)}\n`;
                        });
                    } else {
                        content += "Tidak ada data barang\n";
                    }
                    content += "---------------------------\n";
                    content += `Total Harga  : ${this.rupiah(penjualan.total_harga)}\n`;
                    content += `Total Bayar  : ${this.rupiah(penjualan.total_bayar)}\n`;
                    content += `Kembalian    : ${this.rupiah(penjualan.kembalian)}\n`;
                    content += "---------------------------\n";
                    content += `Status       : ${penjualan.status}\n`;
                    content += "Terima kasih telah berbelanja!\n";
                    content += "---------------------------";

                    this.receiptContent = content;
                    this.detailModal = true;
                },
                printReceipt() {
                    window.print();
                },
                rupiah(angka) {
                    return 'Rp ' + Number(angka).toLocaleString('id-ID');
                },
                formatDate(d) {
                    const date = new Date(d);
                    const pad = n => String(n).padStart(2, '0');
                    return `${pad(date.getDate())}/${pad(date.getMonth()+1)}/${date.getFullYear()} ${pad(date.getHours())}:${pad(date.getMinutes())}`;
                }
            }
        }
    </script>
</body>

</html>