<?php
// modules/tagihan/delete.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$row = fetchOne("SELECT * FROM tagihan WHERE id = ?", [$id]);

if (!$row) {
    setFlash('error', 'Tagihan tidak ditemukan.');
    redirect('tagihan');
}

// Cegah hapus kalau ada pembayaran diverifikasi
$sudahBayar = (int) fetchOne("
    SELECT COUNT(*) c FROM pembayaran 
    WHERE tagihan_id = ? AND status='diverifikasi'
", [$id])['c'];

if ($sudahBayar > 0) {
    setFlash('error', 'Tidak bisa hapus. Sudah ada pembayaran terverifikasi.');
    redirect("tagihan/detail/$id");
}

try {
    // ON DELETE CASCADE akan hapus pembayaran yang masih 'menunggu'
    execute("DELETE FROM tagihan WHERE id = ?", [$id]);
    setFlash('success', 'Tagihan berhasil dihapus.');
} catch (Exception $e) {
    setFlash('error', 'Gagal: ' . $e->getMessage());
}

redirect('tagihan');