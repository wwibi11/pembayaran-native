<?php
// modules/users/delete.php

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan super admin
if (!isSuperAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Hanya Super Admin yang dapat menghapus user.';
    redirect('index.php?url=dashboard');
}

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID user tidak valid!';
    redirect('index.php?url=users');
}

// Ambil data user untuk ditampilkan di alert
$user = fetchOne("SELECT name FROM users WHERE id = ?", [$id]);

if (!$user) {
    $_SESSION['error'] = 'Data user tidak ditemukan!';
    redirect('index.php?url=users');
}

// Cegah menghapus diri sendiri
if ($id == $_SESSION['user']['id']) {
    $_SESSION['error'] = 'Anda tidak dapat menghapus akun sendiri!';
    redirect('index.php?url=users');
}

try {
    // Hapus user menggunakan fungsi delete()
    $deleted = delete('users', 'id = ?', [$id]);
    
    if ($deleted) {
        $_SESSION['success'] = 'User "' . $user['name'] . '" berhasil dihapus!';
    } else {
        $_SESSION['error'] = 'Gagal menghapus data. Silakan coba lagi.';
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

redirect('index.php?url=users');