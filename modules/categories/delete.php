<?php
// modules/categories/delete.php

ob_start();

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

// Ambil ID dari GET (karena kita pakai link langsung)
$id = $_GET['id'] ?? 0;

if ($id <= 0) {
    $_SESSION['error'] = 'ID kategori tidak valid!';
    redirect('index.php?url=categories');
}

// Ambil data kategori
$category = fetchOne("SELECT * FROM categories WHERE id = ?", [$id]);

if (!$category) {
    $_SESSION['error'] = 'Data kategori tidak ditemukan!';
    redirect('index.php?url=categories');
}

// Cek apakah ada barang dengan kategori ini
$totalItems = fetchColumn("SELECT COUNT(*) FROM items WHERE category_id = ?", [$id]);

if ($totalItems > 0) {
    $_SESSION['error'] = 'Kategori "' . $category['name'] . '" memiliki ' . $totalItems . ' barang. Tidak dapat dihapus!';
    redirect('index.php?url=categories');
}

try {
    $deleted = delete('categories', 'id = ?', [$id]);
    
    if ($deleted) {
        $_SESSION['success'] = 'Kategori "' . $category['name'] . '" berhasil dihapus!';
    } else {
        $_SESSION['error'] = 'Gagal menghapus data. Silakan coba lagi.';
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

redirect('index.php?url=categories');

ob_end_flush();
?>