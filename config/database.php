<?php
// config/database.php

// ============================================
// KONFIGURASI DATABASE
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'nqrwisnu_madin');
define('DB_USER', 'nqrwisnu_madin');
define('DB_PASS', '@Wisnuwb11-');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// AUTO-DETECT BASE URL
// ============================================
// Deteksi otomatis base URL berdasarkan environment:
// - Lokal (localhost/127.0.0.1) → /tpq-madin-app
// - Hosting → otomatis dari $_SERVER
// ============================================

if (php_sapi_name() === 'cli') {
    // CLI (cron job / command line) → gunakan path default
    define('BASE_URL', '/tpq-madin-app');
} else {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Cek apakah environment lokal
    $isLocal = (
        $host === 'localhost' 
        || $host === '127.0.0.1' 
        || strpos($host, '192.168.') === 0
        || strpos($host, '.local') !== false
    );

    if ($isLocal) {
        // Lokal (XAMPP/Laragon) → hardcode path project
        define('BASE_URL', '/tpq-madin-app');
    } else {
        // Hosting → deteksi otomatis dari SCRIPT_NAME
        // Ambil path folder tempat aplikasi dipasang
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // Hapus nama file (index.php, login.php, dll) dan ambil direktorinya
        $basePath = rtrim(dirname($scriptName), '/\\');
        
        // Jika di root domain (mis. domain.com/index.php) → kosongkan
        if ($basePath === '/' || $basePath === '\\' || $basePath === '.') {
            $basePath = '';
        }
        
        define('BASE_URL', $basePath);
    }
}

// ============================================
// KONEKSI DATABASE
// ============================================
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('Koneksi database gagal: ' . $e->getMessage());
        }
    }

    return $pdo;
}