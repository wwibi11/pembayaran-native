<?php
// modules/orang_tua/delete.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$row = fetchOne("SELECT * FROM orang_tua WHERE id = ?", [$id]);

if (!$row) {
    setFlash('error', 'Data tidak ditemukan.');
    redirect('orang_tua');
}

// Cek apakah masih punya anak terhubung
$jmlAnak = (int) fetchOne("SELECT COUNT(*) c FROM wali_santri WHERE orang_tua_id = ?", [$id])['c'];

if ($jmlAnak > 0) {
    setFlash('error', "Tidak bisa hapus. Masih terhubung dengan $jmlAnak anak. Lepas link dulu di edit santri.");
    redirect('orang_tua');
}

try {
    // Hapus user akun kalau ada
    if ($row['user_id']) {
        execute("DELETE FROM users WHERE id = ?", [$row['user_id']]);
    }

    // Hapus orang tua (wali_santri otomatis terhapus via CASCADE)
    execute("DELETE FROM orang_tua WHERE id = ?", [$id]);

    setFlash('success', 'Data "' . $row['nama_lengkap'] . '" berhasil dihapus.');
} catch (Exception $e) {
    setFlash('error', 'Gagal: ' . $e->getMessage());
}

redirect('orang_tua');