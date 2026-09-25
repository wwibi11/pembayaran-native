<?php
// modules/pembayaran/proses.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id   = (int) ($id ?? 0);
$aksi = $_GET['aksi'] ?? $_POST['aksi'] ?? '';

$pembayaran = fetchOne("
    SELECT p.*, t.nominal AS nominal_tagihan, s.nama AS nama_santri
    FROM pembayaran p
    JOIN tagihan t ON t.id = p.tagihan_id
    JOIN santri s ON s.id = t.santri_id
    WHERE p.id = ?
", [$id]);

if (!$pembayaran) {
    setFlash('error', 'Data pembayaran tidak ditemukan.');
    redirect('pembayaran/verifikasi');
}

if ($pembayaran['status'] !== 'menunggu') {
    setFlash('error', 'Pembayaran sudah diverifikasi sebelumnya.');
    redirect('pembayaran/verifikasi');
}

$userId = currentUser()['id'];

// ============================================
// APPROVE
// ============================================
if ($aksi === 'approve') {
    try {
        update('pembayaran', [
            'status'      => 'diverifikasi',
            'verified_by' => $userId,
            'verified_at' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        // Recalculate status tagihan
        recalculateTagihanStatus((int) $pembayaran['tagihan_id']);

        setFlash('success', 
            "Pembayaran " . rupiah($pembayaran['nominal_bayar']) . 
            " dari " . $pembayaran['nama_santri'] . " berhasil diverifikasi."
        );
    } catch (Exception $e) {
        setFlash('error', 'Gagal: ' . $e->getMessage());
    }
    redirect('pembayaran/verifikasi');
}

// ============================================
// REJECT
// ============================================
if ($aksi === 'reject') {
    checkCSRF($_POST['csrf'] ?? null);
    $catatan = trim($_POST['catatan_admin'] ?? '');

    if ($catatan === '') {
        setFlash('error', 'Alasan penolakan wajib diisi.');
        redirect('pembayaran/verifikasi');
    }

    try {
        update('pembayaran', [
            'status'        => 'ditolak',
            'catatan_admin' => $catatan,
            'verified_by'   => $userId,
            'verified_at'   => date('Y-m-d H:i:s'),
        ], 'id = ?', [$id]);

        // Recalculate status tagihan
        recalculateTagihanStatus((int) $pembayaran['tagihan_id']);

        setFlash('success', 
            "Pembayaran dari " . $pembayaran['nama_santri'] . " ditolak."
        );
    } catch (Exception $e) {
        setFlash('error', 'Gagal: ' . $e->getMessage());
    }
    redirect('pembayaran/verifikasi');
}

redirect('pembayaran/verifikasi');