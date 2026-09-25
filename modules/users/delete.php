<?php
// modules/users/delete.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$id]);

if (!$user) {
    setFlash('error', 'User tidak ditemukan.');
    redirect('users');
}

// Proteksi: tidak bisa hapus diri sendiri
if ($user['id'] == currentUser()['id']) {
    setFlash('error', 'Tidak bisa menghapus akun sendiri.');
    redirect('users');
}

// Proteksi: cek apakah sudah ada aktivitas
$jmlUpload = (int) fetchColumn("SELECT COUNT(*) FROM pembayaran WHERE uploaded_by = ?", [$id]);
$jmlVerif  = (int) fetchColumn("SELECT COUNT(*) FROM pembayaran WHERE verified_by = ?", [$id]);

if ($jmlUpload > 0 || $jmlVerif > 0) {
    setFlash('error', 
        "Tidak bisa hapus. User sudah memiliki aktivitas ($jmlUpload upload, $jmlVerif verifikasi). " .
        "Nonaktifkan saja untuk menonaktifkan akses."
    );
    redirect('users');
}

// Kalau user adalah wali, cek apakah terhubung ke orang tua
$ortu = fetchOne("SELECT id FROM orang_tua WHERE user_id = ?", [$id]);
if ($ortu) {
    setFlash('error', 
        "Tidak bisa hapus. User ini terhubung ke data orang tua. " .
        "Lepas link dulu di menu Data Orang Tua."
    );
    redirect('users');
}

try {
    execute("DELETE FROM users WHERE id = ?", [$id]);
    setFlash('success', 'User "' . $user['name'] . '" berhasil dihapus.');
} catch (Exception $e) {
    setFlash('error', 'Gagal: ' . $e->getMessage());
}

redirect('users');