<?php
require_once 'php/db_connect.php';

// === FUNGSI BANTU ===
if (!function_exists('formatDate')) {
    function formatDate($date)
    {
        return date('d/m/Y H:i', strtotime($date));
    }
}

// === PROSES CRUD (PHP Native) ===
$message = $message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // CREATE
        if ($action === 'create') {
            $kode = trim($_POST['kode_barang'] ?? '');
            $nama = trim($_POST['nama_barang'] ?? '');
            $harga = floatval($_POST['harga_barang'] ?? 0);
            $modal = floatval($_POST['harga_modal'] ?? 0); // <-- PERUBAHAN: Ambil harga modal
            $stok = intval($_POST['jumlah_stok'] ?? 0);

            // <-- PERUBAHAN: Tambahkan validasi harga modal
            if (!$kode || !$nama || $harga <= 0 || $modal < 0 || $stok < 0) {
                throw new Exception('Isi semua field dengan benar!');
            }
            if ($modal > $harga) {
                 throw new Exception('Harga modal tidak boleh lebih besar dari harga jual!');
            }

            $stmt = $pdo->prepare("SELECT id FROM barang WHERE kode_barang = ?");
            $stmt->execute([$kode]);
            if ($stmt->fetch()) throw new Exception('Kode barang sudah ada!');

            // <-- PERUBAHAN: Tambahkan harga_modal ke query INSERT
            $stmt = $pdo->prepare("INSERT INTO barang (kode_barang, nama_barang, harga_barang, harga_modal, jumlah_stok) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$kode, $nama, $harga, $modal, $stok]);

            $_SESSION['message'] = 'Barang berhasil ditambahkan!';
            $_SESSION['message_type'] = 'success';
        }

        // UPDATE
        elseif ($action === 'update') {
            $id = intval($_POST['id'] ?? 0);
            $kode = trim($_POST['kode_barang'] ?? '');
            $nama = trim($_POST['nama_barang'] ?? '');
            $harga = floatval($_POST['harga_barang'] ?? 0);
            $modal = floatval($_POST['harga_modal'] ?? 0); // <-- PERUBAHAN: Ambil harga modal
            $stok = intval($_POST['jumlah_stok'] ?? 0);

            // <-- PERUBAHAN: Tambahkan validasi harga modal
            if ($id <= 0 || !$kode || !$nama || $harga <= 0 || $modal < 0 || $stok < 0) {
                throw new Exception('Data tidak valid!');
            }
            if ($modal > $harga) {
                 throw new Exception('Harga modal tidak boleh lebih besar dari harga jual!');
            }

            $stmt = $pdo->prepare("SELECT id FROM barang WHERE kode_barang = ? AND id != ?");
            $stmt->execute([$kode, $id]);
            if ($stmt->fetch()) throw new Exception('Kode sudah digunakan!');

            // <-- PERUBAHAN: Tambahkan harga_modal ke query UPDATE
            $stmt = $pdo->prepare("UPDATE barang SET kode_barang=?, nama_barang=?, harga_barang=?, harga_modal=?, jumlah_stok=? WHERE id=?");
            $stmt->execute([$kode, $nama, $harga, $modal, $stok, $id]);

            $_SESSION['message'] = 'Barang berhasil diupdate!';
            $_SESSION['message_type'] = 'success';
        }

        // DELETE
        elseif ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID tidak valid!');

            $stmt = $pdo->prepare("DELETE FROM barang WHERE id = ?");
            $stmt->execute([$id]);

            $_SESSION['message'] = 'Barang berhasil dihapus!';
            $_SESSION['message_type'] = 'success';
        }
    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
        $_SESSION['message_type'] = 'error';
    } catch (PDOException $e) {
        $_SESSION['message'] = 'Database error: ' . $e->getMessage();
        $_SESSION['message_type'] = 'error';
    }

    header('Location: index.php?page=barang');
    exit;
}

// Ambil pesan dari session
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $message_type = $_SESSION['message_type'];
    unset($_SESSION['message'], $_SESSION['message_type']);
}

// Ambil data barang
try {
    // <-- PERUBAHAN: Ambil semua kolom, termasuk harga_modal
    $stmt = $pdo->query("SELECT * FROM barang ORDER BY tanggal_ditambahkan DESC");
    $barang_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $barang_list = [];
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Barang</title>
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
        .toast {
            animation: slideIn 0.3s ease-out, slideOut 0.3s ease-in 2.7s;
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
    </style>
</head>

<body class="bg-gray-50">
    <div x-data="barangCRUD()" x-init="init()" class="max-w-6xl mx-auto p-4 sm:p-6 space-y-4" x-cloak>
        <!-- TOAST NOTIFICATION -->
        <div x-show="notif.show"
            class="fixed top-4 right-4 z-[9999] max-w-xs w-full toast">
            <div :class="notif.type==='success'?'bg-green-500':'bg-red-500'"
                class="text-white px-3 py-2 rounded-lg shadow-lg flex items-center gap-2">
                <i :class="notif.type==='success'?'ri-check-line':'ri-close-line'" class="text-base"></i>
                <div>
                    <p class="text-xs font-medium">Info</p>
                    <p class="text-xs" x-text="notif.msg"></p>
                </div>
            </div>
        </div>

        <!-- HEADER -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
            <h2 class="text-base font-semibold text-gray-800 flex items-center gap-1.5">
                <i class="ri-boxing-line text-blue-600 text-base"></i> Data Barang
            </h2>
            <button @click="openModal('create')"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-md transition shadow-sm">
                <i class="ri-add-line text-sm"></i> Tambah
            </button>
        </div>

        <!-- SEARCH & FILTER -->
        <div class="flex flex-col sm:flex-row gap-2">
            <input type="text" x-model="search" @input.debounce.300ms="filter()"
                placeholder="Cari kode atau nama..."
                class="flex-1 px-3 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
            <select x-model="filterStok" @change="filter()"
                class="px-3 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
                <option value="">Semua Stok</option>
                <option value="habis">Habis (0)</option>
                <option value="kurang">Kurang (≤10)</option>
                <option value="aman">Aman (>10)</option>
            </select>
        </div>

        <!-- TABEL -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 border-b border-gray-200 text-xs font-medium text-gray-700">
                        <tr>
                            <th class="px-3 py-2">Kode</th>
                            <th class="px-3 py-2">Nama</th>
                            <!-- <th class="px-3 py-2">Harga Modal</th> <-- PERUBAHAN: Opsional, bisa ditambahkan jika mau -->
                            <th class="px-3 py-2">Harga Jual</th>
                            <th class="px-3 py-2">Stok</th>
                            <th class="px-3 py-2">Ditambahkan</th>
                            <th class="px-3 py-2 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 text-xs">
                        <template x-for="b in filtered" :key="b.id">
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-3 py-2 font-mono text-gray-900" x-text="b.kode_barang"></td>
                                <td class="px-3 py-2 text-gray-800" x-text="b.nama_barang"></td>
                                <!-- <td class="px-3 py-2 font-medium text-gray-500" x-text="'Rp ' + Number(b.harga_modal).toLocaleString('id-ID')"></td> <-- PERUBAHAN: Opsional -->
                                <td class="px-3 py-2 font-medium text-gray-700" x-text="'Rp ' + Number(b.harga_barang).toLocaleString('id-ID')"></td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-medium"
                                        :class="b.jumlah_stok==0 ? 'bg-red-100 text-red-700' : 
                                                (b.jumlah_stok<=10 ? 'bg-yellow-100 text-yellow-700' : 'bg-green-100 text-green-700')">
                                        <span x-text="b.jumlah_stok"></span>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-600" x-text="formatDate(b.tanggal_ditambahkan)"></td>
                                <td class="px-3 py-2 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button @click="openModal('update', b)"
                                            class="p-1 text-blue-600 hover:bg-blue-50 rounded transition" title="Edit">
                                            <i class="ri-edit-line text-sm"></i>
                                        </button>
                                        <button @click="confirmDelete(b.id, b.nama_barang)"
                                            class="p-1 text-red-600 hover:bg-red-50 rounded transition" title="Hapus">
                                            <i class="ri-delete-bin-line text-sm"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="filtered.length===0">
                            <td colspan="6" class="text-center py-8 text-gray-500">
                                <i class="ri-inbox-line text-2xl text-gray-300 mb-2 block"></i>
                                <p class="font-medium text-xs">Tidak ada data</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL CRUD -->
        <div x-show="modal"
            class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @click.self="modal = false">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-md p-5">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-semibold text-gray-800" x-text="mode==='create'?'Tambah Barang':'Edit Barang'"></h3>
                    <button @click="modal = false" class="text-gray-400 hover:text-gray-600">
                        <i class="ri-close-line text-base"></i>
                    </button>
                </div>
                <form method="POST" action="index.php?page=barang" class="space-y-3">
                    <input type="hidden" name="action" :value="mode">
                    <input type="hidden" name="id" :value="form.id">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-700 mb-1">Kode Barang</label>
                        <input type="text" name="kode_barang" x-model="form.kode_barang" required
                            class="w-full px-2 py-1 text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-700 mb-1">Nama Barang</label>
                        <input type="text" name="nama_barang" x-model="form.nama_barang" required
                            class="w-full px-2 py-1 text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    
                    <!-- <-- PERUBAHAN: Form dibagi 2 kolom agar rapi -->
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">Harga Modal (Rp)</label>
                            <input type="number" name="harga_modal" x-model="form.harga_modal" min="0" step="100" required
                                class="w-full px-2 py-1 text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-700 mb-1">Harga Jual (Rp)</label>
                            <input type="number" name="harga_barang" x-model="form.harga_barang" min="0" step="100" required
                                class="w-full px-2 py-1 text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-medium text-gray-700 mb-1">Stok</label>
                        <input type="number" name="jumlah_stok" x-model="form.jumlah_stok" min="0" required
                            class="w-full px-2 py-1 text-xs border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 outline-none">
                    </div>
                    
                    <div class="flex gap-2 pt-2">
                        <button type="submit"
                            class="flex-1 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-md transition flex items-center justify-center gap-1.5">
                            <i class="ri-save-line text-sm"></i> Simpan
                        </button>
                        <button type="button" @click="modal = false"
                            class="flex-1 py-1.5 bg-gray-600 hover:bg-gray-700 text-white text-xs font-medium rounded-md transition">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL HAPUS -->
        <div x-show="delModal"
            class="fixed inset-0 z-[999] flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
            @click.self="delModal = false">
            <div @click.stop class="bg-white rounded-xl shadow-2xl w-full max-w-xs p-5 text-center">
                <i class="ri-delete-bin-line text-3xl text-red-600 mb-3 block"></i>
                <h3 class="text-sm font-semibold text-gray-800 mb-1.5">Hapus Barang?</h3>
                <p class="text-xs text-gray-600 mb-4">Yakin ingin menghapus <strong x-text="delName"></strong>?</p>
                <form method="POST" action="index.php?page=barang" class="inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" x-model="delId">
                    <div class="flex gap-2">
                        <button type="submit"
                            class="flex-1 py-1.5 bg-red-600 hover:bg-red-700 text-white text-xs font-medium rounded-md transition">
                            Hapus
                        </button>
                        <button type="button" @click="delModal = false"
                            class="flex-1 py-1.5 bg-gray-600 hover:bg-gray-700 text-white text-xs font-medium rounded-md transition">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function barangCRUD() {
            return {
                modal: false,
                mode: 'create',
                delModal: false,
                delId: 0,
                delName: '',
                search: '',
                filterStok: '',
                form: {
                    id: 0,
                    kode_barang: '',
                    nama_barang: '',
                    harga_barang: 0,
                    harga_modal: 0, // <-- PERUBAHAN
                    jumlah_stok: 0
                },
                data: <?= json_encode($barang_list, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
                filtered: [],
                notif: {
                    show: false,
                    msg: '',
                    type: 'success'
                },

                init() {
                    this.filtered = [...this.data];
                    <?php if ($message): ?>
                        this.showNotif(<?= json_encode($message) ?>, <?= json_encode($message_type) ?>);
                    <?php endif; ?>
                },

                showNotif(msg, type = 'success') {
                    this.notif = {
                        show: true,
                        msg,
                        type
                    };
                    setTimeout(() => this.notif.show = false, 3000);
                },

                openModal(mode, item = null) {
                    this.mode = mode;
                    if (mode === 'create') {
                        this.form = {
                            id: 0,
                            kode_barang: '',
                            nama_barang: '',
                            harga_barang: 0,
                            harga_modal: 0, // <-- PERUBAHAN
                            jumlah_stok: 0
                        };
                    } else if (mode === 'update') {
                        this.form = {
                            id: item.id,
                            kode_barang: item.kode_barang,
                            nama_barang: item.nama_barang,
                            harga_barang: parseFloat(item.harga_barang),
                            harga_modal: parseFloat(item.harga_modal ?? 0), // <-- PERUBAHAN (tambahkan '?? 0' untuk data lama)
                            jumlah_stok: parseInt(item.jumlah_stok)
                        };
                    }
                    this.modal = true;
                },

                confirmDelete(id, name) {
                    this.delId = id;
                    this.delName = name;
                    this.delModal = true;
                },

                filter() {
                    let list = [...this.data];
                    if (this.search) {
                        const s = this.search.toLowerCase();
                        list = list.filter(b =>
                            b.kode_barang.toLowerCase().includes(s) ||
                            b.nama_barang.toLowerCase().includes(s)
                        );
                    }
                    if (this.filterStok === 'habis') list = list.filter(b => b.jumlah_stok == 0);
                    if (this.filterStok === 'kurang') list = list.filter(b => b.jumlah_stok > 0 && b.jumlah_stok <= 10);
                    if (this.filterStok === 'aman') list = list.filter(b => b.jumlah_stok > 10);
                    this.filtered = list;
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
