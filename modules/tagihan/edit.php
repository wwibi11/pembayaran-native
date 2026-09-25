<?php
// modules/tagihan/edit.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$row = fetchOne("
    SELECT t.*, s.nama AS nama_santri, s.nis, jp.nama AS jenis_nama
    FROM tagihan t
    JOIN santri s ON s.id = t.santri_id
    JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
    WHERE t.id = ?
", [$id]);

if (!$row) { setFlash('error','Tagihan tidak ditemukan.'); redirect('tagihan'); }

// Cegah edit kalau sudah ada pembayaran diverifikasi
$sudahBayar = (int) fetchOne("
    SELECT COUNT(*) c FROM pembayaran 
    WHERE tagihan_id = ? AND status='diverifikasi'
", [$id])['c'];

if ($sudahBayar > 0) {
    setFlash('error', 'Tidak bisa edit. Tagihan ini sudah memiliki pembayaran terverifikasi.');
    redirect("tagihan/detail/$id");
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $nominal = (float) str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? '0');
    $periode = trim($_POST['periode'] ?? '');
    $jt      = $_POST['jatuh_tempo'] ?? '';

    if ($nominal <= 0) $errors[] = 'Nominal harus > 0.';
    if (!$periode)     $errors[] = 'Periode wajib diisi.';

    // Cek duplikat (kalau periode berubah)
    if (!$errors && $periode !== $row['periode']) {
        $dup = fetchOne("
            SELECT id FROM tagihan
            WHERE santri_id = ? AND jenis_pembayaran_id = ? AND periode = ? AND id != ?
        ", [$row['santri_id'], $row['jenis_pembayaran_id'], $periode, $id]);
        if ($dup) $errors[] = 'Tagihan untuk periode ini sudah ada.';
    }

    if (!$errors) {
        try {
            update('tagihan', [
                'nominal'     => $nominal,
                'periode'     => $periode,
                'jatuh_tempo' => $jt ?: null,
            ], 'id = ?', [$id]);

            setFlash('success', 'Tagihan berhasil diperbarui.');
            redirect("tagihan/detail/$id");
        } catch (Exception $e) {
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }

    $row = array_merge($row, [
        'nominal' => $nominal,
        'periode' => $periode,
        'jatuh_tempo' => $jt,
    ]);
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/tagihan/detail/<?= $id ?>" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-0 text-gray-800">
                <i class="fas fa-edit text-warning"></i> Edit Tagihan
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?= e($row['nama_santri']) ?> · <?= e($row['jenis_nama']) ?>
            </p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

                <div class="alert alert-info py-2">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        Santri: <strong><?= e($row['nama_santri']) ?></strong> 
                        · Jenis: <strong><?= e($row['jenis_nama']) ?></strong>
                    </small>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Nominal <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">Rp</span></div>
                            <input type="text" name="nominal" class="form-control" data-rupiah
                                   value="<?= number_format((float) $row['nominal'], 0, ',', '.') ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Periode <span class="text-danger">*</span></label>
                        <input type="text" name="periode" class="form-control"
                               value="<?= e($row['periode']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Jatuh Tempo</label>
                        <input type="date" name="jatuh_tempo" class="form-control"
                               value="<?= e($row['jatuh_tempo']) ?>">
                    </div>
                </div>

                <hr>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Perbarui
                </button>
                <a href="<?= BASE_URL ?>/tagihan/detail/<?= $id ?>" class="btn btn-light">Batal</a>
            </form>
        </div>
    </div>
</div>