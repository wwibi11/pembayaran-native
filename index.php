<?php
// index.php

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
        'dashboard', 'santri', 'kelas', 'tagihan',
        'pembayaran', 'kenaikan', 'laporan', 'riwayat'
    ],
    'wali' => [
        'dashboard', 'anak', 'tagihan', 'pembayaran', 'riwayat'
    ],
];

// Modul yang hanya bisa diakses admin
$admin_only = ['users', 'settings', 'jenis_pembayaran', 'wali'];

// Aksi yang hanya untuk admin (write access)
$write_actions = ['create', 'edit', 'store', 'update', 'add', 'save', 'delete', 'hapus', 'generate'];

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

    // Cek akses modul
    if (!in_array('*', $allowed, true) && !in_array($module, $allowed, true)) {
        http_response_code(403);
        exit('403 - Module "' . e($module) . '" tidak diizinkan untuk role Anda.');
    }

    // Modul khusus admin
    if (in_array($module, $admin_only, true) && $role !== 'admin') {
        http_response_code(403);
        exit('403 - Module ini hanya untuk Admin.');
    }

    // Kepala tidak boleh write
    if ($role === 'kepala' && in_array($action, $write_actions, true)) {
        http_response_code(403);
        exit('403 - Kepala hanya bisa melihat data.');
    }

    // Wali tidak boleh akses modul kelola santri/kelas
    if ($role === 'wali' && in_array($module, ['santri', 'kelas', 'kenaikan'], true)) {
        // Wali hanya boleh lihat anaknya via module 'anak'
        if ($module !== 'anak') {
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
    exit;
}

include 'views/header.php';
include 'views/sidebar.php';
include 'views/topbar.php';
include $file;
include 'views/footer.php';