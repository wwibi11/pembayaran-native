<?php
// modules/kelas/delete.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$id = (int) ($id ?? 0);
$row = fetchOne("SELECT * FROM kelas WHERE id = ?", [$id]);

if (!$row) {
    setFlash('error', 'Kelas tidak ditemukan.');
    redirect('kelas');
}

// Cek apakah masih ada santri di kelas ini
$jumlah = (int) fetchOne("SELECT COUNT(*) c FROM santri WHERE kelas_id = ? AND status='aktif'", [$id])['c'];
if ($jumlah > 0) {
    setFlash('error', "Tidak bisa hapus. Masih ada $jumlah santri aktif di kelas ini.");
    redirect('kelas');
}

try {
    execute("DELETE FROM kelas WHERE id = ?", [$id]);
    setFlash('success', 'Kelas "' . $row['nama_kelas'] . '" berhasil dihapus.');
} catch (Exception $e) {
    setFlash('error', 'Gagal menghapus: ' . $e->getMessage());
}

redirect('kelas');