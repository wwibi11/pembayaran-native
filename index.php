<?php
// index.php

ob_start();
require_once __DIR__ . '/config/functions.php';

// ============================================
// MAINTENANCE MODE
// ============================================
if (isMaintenanceMode()) {
    $isAdmin = isLoggedIn() && currentRole() === 'admin';
    if (!$isAdmin) {
        include 'maintenance.php';
        exit;
    }
}

// ============================================
// AUTH CHECK
// ============================================
if (!isLoggedIn()) {
    setFlash('error', 'Silakan login terlebih dahulu!');
    redirect('auth/login.php');
}

// Cek status aktif user
$dbUser = fetchOne("SELECT is_active FROM users WHERE id = ?", [currentUser()['id']]);
if (!$dbUser || !$dbUser['is_active']) {
    session_destroy();
    setFlash('error', 'Akun Anda tidak aktif.');
    redirect('auth/login.php');
}

// ============================================
// ROUTING
// ============================================
$url    = trim($_GET['url'] ?? 'dashboard', '/');
$parts  = explode('/', $url);
$module = $parts[0] ?: 'dashboard';
$action = $parts[1] ?? 'index';
$id     = $parts[2] ?? null;

// ============================================
// AKSI TANPA LAYOUT (AJAX, DELETE, PROSES)
// ============================================
// Catatan: file di $no_layout ini WAJIB cek role sendiri di dalamnya.
$no_layout = [
    'delete', 'hapus', 'proses', 'ajax',
    'download', 'export', 'store', 'update'
];

// ============================================
// ROLE ACCESS DEFINITION
// ============================================
$role_access = [
    'admin' => ['*'],
    'kepala' => [
        'dashboard', 'santri', 'kelas', 'orang_tua', 'jenis_pembayaran',
        'tagihan', 'pembayaran', 'kenaikan', 'laporan', 'riwayat'
    ],
    'wali' => [
        'dashboard', 'anak', 'tagihan', 'pembayaran', 'riwayat'
    ],
];

// Modul yang HANYA bisa diakses admin (semua action)
$admin_only_modules = ['users', 'settings', 'wali'];

// Modul yang READ-ONLY untuk kepala, tapi CRUD khusus admin
$admin_write_modules = ['santri', 'orang_tua', 'jenis_pembayaran', 'kelas'];

// Aksi yang dianggap "write" (hanya admin)
$write_actions = [
    'create', 'edit', 'store', 'update', 'add', 'save',
    'delete', 'hapus', 'generate', 'anggota', 'import',
    'verifikasi'   // ⭐ TAMBAHAN: khusus admin
];

// ============================================
// TENTUKAN FILE
// ============================================
$file = ($module === 'dashboard')
    ? 'modules/dashboard/index.php'
    : "modules/{$module}/{$action}.php";

if (!file_exists($file)) {
    http_response_code(404);
    exit('404 - Halaman tidak ditemukan: ' . e($file));
}

// ============================================
// VALIDASI HAK AKSES
// ============================================
if (!in_array($action, $no_layout, true)) {

    $role = currentRole();

    if (!isset($role_access[$role])) {
        http_response_code(403);
        exit('403 - Role tidak valid');
    }

    $allowed = $role_access[$role];

    // 1. Cek akses modul (kecuali super admin)
    if (!in_array('*', $allowed, true) && !in_array($module, $allowed, true)) {
        http_response_code(403);
        exit('403 - Module "' . e($module) . '" tidak diizinkan untuk role Anda.');
    }

    // 2. Modul khusus admin (semua action)
    if (in_array($module, $admin_only_modules, true) && $role !== 'admin') {
        http_response_code(403);
        exit('403 - Module "' . e($module) . '" hanya untuk Admin.');
    }

    // 3. Modul yang CRUD-nya hanya admin
    if (in_array($module, $admin_write_modules, true)
        && in_array($action, $write_actions, true)
        && $role !== 'admin') {
        http_response_code(403);
        exit('403 - Hanya Admin yang bisa mengubah data di module "' . e($module) . '".');
    }

    // 4. Kepala TIDAK BOLEH write action apapun
    if ($role === 'kepala' && in_array($action, $write_actions, true)) {
        http_response_code(403);
        exit('403 - Kepala hanya bisa melihat data.');
    }

    // ============================================
    // 5. WALI — WHITELIST MODUL & AKSI
    // ============================================
    if ($role === 'wali') {

        // 5a. Whitelist modul yang BOLEH diakses wali
        $wali_modules = [
            'dashboard', 'anak', 'tagihan', 'pembayaran',
            'riwayat', 'profil'
        ];

        if (!in_array($module, $wali_modules, true)) {
            http_response_code(403);
            exit('403 - Akses ditolak.');
        }

        // 5b. Whitelist action write yang boleh wali
        // (upload bukti bayar, submit form, dll)
        $wali_write_whitelist = ['upload', 'bayar', 'kirim', 'submit'];

        if (in_array($action, $write_actions, true)
            && !in_array($action, $wali_write_whitelist, true)) {
            http_response_code(403);
            exit('403 - Wali tidak bisa mengubah data ini.');
        }

        // 5c. Action khusus admin di modul pembayaran
        // (create, verifikasi, proses, delete) → tolak untuk wali
        $pembayaran_admin_only = ['create', 'verifikasi', 'proses', 'delete'];
        if ($module === 'pembayaran' 
            && in_array($action, $pembayaran_admin_only, true)) {
            http_response_code(403);
            exit('403 - Hanya admin yang bisa mengakses halaman ini.');
        }
    }

    // ============================================
    // 6. KEPALA — batasi action write di pembayaran
    // ============================================
    if ($role === 'kepala' && $module === 'pembayaran'
        && in_array($action, ['create','verifikasi','proses','delete'], true)) {
        http_response_code(403);
        exit('403 - Kepala hanya bisa melihat data.');
    }
}

// ============================================
// RENDER
// ============================================
if (in_array($action, $no_layout, true)) {
    include $file;
    ob_end_flush();
    exit;
}

include 'views/header.php';
include 'views/sidebar.php';
include 'views/topbar.php';
include $file;
include 'views/footer.php';

ob_end_flush();