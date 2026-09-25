<?php
// modules/santri/delete.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$row = fetchOne("SELECT * FROM santri WHERE id = ?", [$id]);
if (!$row) { setFlash('error','Santri tidak ditemukan.'); redirect('santri'); }

// Cek tagihan aktif
$jmlTagihan = (int) fetchOne("
    SELECT COUNT(*) c FROM tagihan 
    WHERE santri_id = ? AND status IN ('belum_lunas','menunggu_verifikasi')
", [$id])['c'];

if ($jmlTagihan > 0) {
    setFlash('error', "Tidak bisa hapus. Santri masih punya $jmlTagihan tagihan aktif. Selesaikan dulu atau ubah status jadi 'keluar'.");
    redirect('santri');
}

try {
    // Hapus foto
    if ($row['foto'] && file_exists(__DIR__ . '/../../' . $row['foto'])) {
        @unlink(__DIR__ . '/../../' . $row['foto']);
    }

    // ON DELETE CASCADE otomatis hapus riwayat_kelas, wali_santri
    execute("DELETE FROM santri WHERE id = ?", [$id]);
    setFlash('success', 'Santri "' . $row['nama'] . '" berhasil dihapus.');
} catch (Exception $e) {
    setFlash('error', 'Gagal: ' . $e->getMessage());
}
redirect('santri');