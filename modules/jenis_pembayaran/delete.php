<?php
// modules/jenis_pembayaran/delete.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$row = fetchOne("SELECT * FROM jenis_pembayaran WHERE id = ?", [$id]);

if (!$row) {
    setFlash('error', 'Data tidak ditemukan.');
    redirect('jenis_pembayaran');
}

// Cek tagihan terkait
$jml = (int) fetchOne("
    SELECT COUNT(*) c FROM tagihan WHERE jenis_pembayaran_id = ?
", [$id])['c'];

if ($jml > 0) {
    setFlash('error', 
        "Tidak bisa hapus. Jenis '{$row['nama']}' sudah dipakai di $jml tagihan. " .
        "Nonaktifkan saja kalau tidak dipakai lagi."
    );
    redirect('jenis_pembayaran');
}

try {
    execute("DELETE FROM jenis_pembayaran WHERE id = ?", [$id]);
    setFlash('success', 'Jenis "' . $row['nama'] . '" berhasil dihapus.');
} catch (Exception $e) {
    setFlash('error', 'Gagal: ' . $e->getMessage());
}

redirect('jenis_pembayaran');