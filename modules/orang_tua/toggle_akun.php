<?php
// modules/orang_tua/toggle_akun.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$ortu = fetchOne("SELECT ot.id, ot.nama_lengkap, ot.user_id, u.is_active
                  FROM orang_tua ot
                  LEFT JOIN users u ON u.id = ot.user_id
                  WHERE ot.id = ?", [$id]);
if (!$ortu || !$ortu['user_id']) {
    setFlash('error','Akun tidak ditemukan.');
    redirect('orang_tua');
}

$newStatus = $ortu['is_active'] ? 0 : 1;
update('users', ['is_active' => $newStatus], 'id = ?', [$ortu['user_id']]);

setFlash('success', 'Akun ' . $ortu['nama_lengkap'] . ' berhasil ' 
    . ($newStatus ? 'diaktifkan' : 'dinonaktifkan') . '.');
redirect("orang_tua/akun/$id");