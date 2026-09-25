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

// Aksi tanpa layout (AJAX, delete, dll)
$no_layout = [
    'delete', 'hapus', 'proses', 'ajax', 'download', 'export',
    'verifikasi', 'tolak', 'approve', 'reject', 'store', 'update'
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

// Modul yang HANYA bisa diakses admin (untuk semua action)
$admin_only_modules = ['users', 'settings', 'wali'];

// Modul yang READ-ONLY untuk kepala, tapi CRUD khusus admin
// (kepala boleh lihat, tapi tidak boleh create/edit/delete)
$admin_write_modules = ['santri', 'orang_tua', 'jenis_pembayaran', 'kelas'];

// Aksi yang dianggap "write" (hanya admin)
$write_actions = [
    'create', 'edit', 'store', 'update', 'add', 'save',
    'delete', 'hapus', 'generate', 'anggota', 'import'
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
    //    (kepala boleh lihat, tapi tidak boleh create/edit/delete/anggota)
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

    // 5. Wali: hanya boleh akses module yang diizinkan + tidak boleh write
    if ($role === 'wali') {
        // Wali hanya boleh lihat, upload bukti bayar, dan lihat anaknya
        $wali_allowed_write = ['upload', 'bayar', 'kirim', 'submit'];

        if (in_array($action, $write_actions, true)
            && !in_array($action, $wali_allowed_write, true)) {
            http_response_code(403);
            exit('403 - Wali tidak bisa mengubah data ini.');
        }

        // Wali tidak boleh akses modul kelola
        if (in_array($module, ['santri', 'kelas', 'kenaikan', 'orang_tua'], true)) {
            http_response_code(403);
            exit('403 - Akses ditolak.');
        }
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