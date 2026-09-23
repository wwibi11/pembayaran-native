<?php
// modules/returns/delete.php

ob_start();

require_once __DIR__ . '/../../config/functions.php';

if (!isAdmin()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID pengembalian tidak valid!';
    redirect('index.php?url=returns');
}

$return = fetchOne("SELECT * FROM returns WHERE id = ?", [$id]);

if (!$return) {
    $_SESSION['error'] = 'Data pengembalian tidak ditemukan!';
    redirect('index.php?url=returns');
}

try {
    beginTransaction();
    
    // Ambil return details
    $details = fetchAll("SELECT * FROM return_details WHERE return_id = ?", [$id]);
    
    foreach ($details as $d) {
        // Kembalikan stok barang
        update(
            'items',
            ['quantity' => 'quantity - ' . $d['quantity']],
            'id = ?',
            [$d['item_id']]
        );
        
        // Update loan detail status kembali ke dipinjam
        updateData('loan_details', [
            'status' => 'dipinjam',
            'returned_quantity' => 0,
            'condition_after' => null
        ], 'id', $d['loan_detail_id']);
        
        // Hapus return detail
        delete('return_details', 'id = ?', [$d['id']]);
    }
    
    // Update loan status kembali ke dipinjam
    updateData('loans', [
        'status' => 'dipinjam',
        'actual_return_date' => null
    ], 'id', $return['loan_id']);
    
    // Hapus return
    delete('returns', 'id = ?', [$id]);
    
    commit();
    
    $_SESSION['success'] = 'Pengembalian berhasil dihapus! Stok barang dikembalikan.';
    
} catch (Exception $e) {
    rollback();
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

redirect('index.php?url=returns');

ob_end_flush();
?>