<?php
// modules/loans/delete.php

ob_start();

require_once __DIR__ . '/../../config/functions.php';

if (!isAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID peminjaman tidak valid!';
    redirect('index.php?url=loans');
}

$loan = fetchOne("SELECT * FROM loans WHERE id = ?", [$id]);

if (!$loan) {
    $_SESSION['error'] = 'Data peminjaman tidak ditemukan!';
    redirect('index.php?url=loans');
}

// Cek apakah masih dipinjam
if ($loan['status'] != 'dikembalikan') {
    $_SESSION['error'] = 'Peminjaman belum dikembalikan. Kembalikan terlebih dahulu!';
    redirect('index.php?url=loans');
}

try {
    // Hapus return details dan returns
    $returns = fetchAll("SELECT id FROM returns WHERE loan_id = ?", [$id]);
    foreach ($returns as $ret) {
        delete('return_details', 'return_id = ?', [$ret['id']]);
        delete('returns', 'id = ?', [$ret['id']]);
    }
    
    delete('loan_details', 'loan_id = ?', [$id]);
    delete('loans', 'id = ?', [$id]);
    
    $_SESSION['success'] = 'Peminjaman berhasil dihapus!';
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

redirect('index.php?url=loans');

ob_end_flush();
?>