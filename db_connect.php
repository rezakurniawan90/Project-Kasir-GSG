<?php
// php/db_connect.php
    
// --- TAMBAHKAN BARIS INI UNTUK MEMAKSA WAKTU INDONESIA ---
date_default_timezone_set('Asia/Jakarta');
// --- BATAS TAMBAHAN ---

// === Konfigurasi Database ===
$host     = 'sql104.infinityfree.com';
$dbname   = 'if0_40256492_kantin_db';     // Nama database kamu
$username = 'if0_40256492';          // Username MySQL
$password = 'YRvOY8tx3odUX';              // Password MySQL (kosong jika default)

// === Koneksi PDO dengan error handling ===
try {
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);

    // --- TAMBAHKAN BARIS INI (FIX 2) ---
    // Memaksa Zona Waktu KONEKSI DATABASE ke WIB (UTC+7)
    $pdo->exec("SET time_zone = '+07:00'");
    // --- BATAS TAMBAHAN ---
    
} catch (PDOException $e) {
    // Hanya tampilkan error jika di development
    // Di production, ganti dengan pesan umum
    die("Koneksi database gagal: " . $e->getMessage());
}
