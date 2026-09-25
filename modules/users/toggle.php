<?php
// modules/users/toggle.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$user = fetchOne("SELECT * FROM users WHERE id = ?", [$id]);

if (!$user) {
    setFlash('error', 'User tidak ditemukan.');
    redirect('users');
}

// Proteksi: tidak bisa nonaktifkan diri sendiri
if ($user['id'] == currentUser()['id']) {
    setFlash('error', 'Tidak bisa menonaktifkan akun sendiri.');
    redirect('users');
}

$newStatus = $user['is_active'] ? 0 : 1;

try {
    update('users', ['is_active' => $newStatus], 'id = ?', [$id]);
    setFlash('success', 
        'User "' . $user['name'] . '" berhasil ' . 
        ($newStatus ? 'diaktifkan' : 'dinonaktifkan') . '.'
    );
} catch (Exception $e) {
    setFlash('error', 'Gagal: ' . $e->getMessage());
}

redirect('users');