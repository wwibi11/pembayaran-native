<?php
// modules/items/delete.php

// ============================================
// LOAD FUNCTIONS
// ============================================
require_once __DIR__ . '/../../config/functions.php';

// ============================================
// PROSES DELETE
// ============================================

// Redirect jika bukan admin
if (!isAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=items');
}

// Ambil ID dari POST (dari form modal) atau GET (jika langsung)
$id = $_POST['id'] ?? $_GET['id'] ?? 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID barang tidak valid!';
    redirect('index.php?url=items');
}

// Ambil data barang
$item = fetchOne("SELECT * FROM items WHERE id = ?", [$id]);

if (!$item) {
    $_SESSION['error'] = 'Data barang tidak ditemukan!';
    redirect('index.php?url=items');
}

// Cek apakah barang sedang dipinjam
$isBorrowed = fetchColumn(
    "SELECT COUNT(*) FROM loan_details WHERE item_id = ? AND status = 'dipinjam'",
    [$id]
);

if ($isBorrowed > 0) {
    $_SESSION['error'] = 'Barang "' . $item['name'] . '" sedang dipinjam, tidak dapat dihapus!';
    redirect('index.php?url=items');
}

try {
    // Hapus foto jika ada
    if ($item['photo']) {
        deleteFile($item['photo']);
    }
    
    // Hapus data dari database
    $deleted = delete('items', 'id = ?', [$id]);
    
    if ($deleted) {
        $_SESSION['success'] = 'Barang "' . $item['name'] . '" berhasil dihapus!';
    } else {
        $_SESSION['error'] = 'Gagal menghapus data. Silakan coba lagi.';
    }
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

redirect('index.php?url=items');
?>