<?php
// modules/jenis_pembayaran/edit.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$row = fetchOne("SELECT * FROM jenis_pembayaran WHERE id = ?", [$id]);
if (!$row) {
    setFlash('error', 'Data tidak ditemukan.');
    redirect('jenis_pembayaran');
}

// Cek berapa tagihan yang sudah pakai
$jmlTagihan = (int) fetchOne("
    SELECT COUNT(*) c FROM tagihan WHERE jenis_pembayaran_id = ?
", [$id])['c'];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $data = [
        'nama'                      => trim($_POST['nama'] ?? ''),
        'nominal_default'           => (float) str_replace(['.', ','], ['', '.'], $_POST['nominal_default'] ?? '0'),
        'periode'                   => $_POST['periode'] ?? 'bulanan',
        'deskripsi'                 => trim($_POST['deskripsi'] ?? ''),
        'is_active'                 => isset($_POST['is_active']) ? 1 : 0,
        'auto_generate_saat_daftar' => isset($_POST['auto_generate_saat_daftar']) ? 1 : 0,
        'bisa_prorata'              => isset($_POST['bisa_prorata']) ? 1 : 0,
    ];

    if ($data['nama'] === '')       $errors[] = 'Nama wajib diisi.';
    if ($data['nominal_default'] <= 0) $errors[] = 'Nominal harus lebih dari 0.';
    if (!in_array($data['periode'], ['bulanan','tahunan','sekali'])) {
        $errors[] = 'Periode tidak valid.';
    }
    if ($data['bisa_prorata'] && $data['periode'] !== 'bulanan') {
        $errors[] = 'Prorata hanya berlaku untuk periode bulanan.';
    }

    // Cek duplikat nama
    if (!$errors) {
        $dup = fetchOne("SELECT id FROM jenis_pembayaran WHERE nama = ? AND id != ?",
                        [$data['nama'], $id]);
        if ($dup) $errors[] = 'Nama sudah dipakai jenis lain.';
    }

    if (!$errors) {
        try {
            update('jenis_pembayaran', [
                'nama'                      => $data['nama'],
                'nominal_default'           => $data['nominal_default'],
                'periode'                   => $data['periode'],
                'deskripsi'                 => $data['deskripsi'] ?: null,
                'is_active'                 => $data['is_active'],
                'auto_generate_saat_daftar' => $data['auto_generate_saat_daftar'],
                'bisa_prorata'              => $data['bisa_prorata'],
            ], 'id = ?', [$id]);

            setFlash('success', 'Data berhasil diperbarui.');
            redirect('jenis_pembayaran');
        } catch (Exception $e) {
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }

    $row = array_merge($row, $data);
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/jenis_pembayaran" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-edit text-warning"></i> Edit Jenis Pembayaran
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?= e($row['nama']) ?>
            </p>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($jmlTagihan > 0): ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Perhatian:</strong>
            Jenis ini sudah dipakai di <strong><?= $jmlTagihan ?></strong> tagihan.
            Perubahan nominal default <strong>tidak akan mengubah</strong> nominal tagihan yang sudah ada.
        </div>
    <?php endif; ?>

    <div class="card shadow">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

                <!-- INFORMASI DASAR -->
                <h6 class="text-primary mb-3">
                    <i class="fas fa-info-circle"></i> Informasi Dasar
                </h6>

                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Nama <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control"
                               value="<?= e($row['nama']) ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Nominal Default <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">Rp</span>
                            </div>
                            <input type="text" name="nominal_default"
                                   class="form-control" data-rupiah
                                   value="<?= number_format((float) $row['nominal_default'], 0, ',', '.') ?>"
                                   required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Periode <span class="text-danger">*</span></label>
                        <select name="periode" class="form-control" required
                                onchange="toggleProrata()" id="selectPeriode">
                            <option value="bulanan" <?= $row['periode']==='bulanan'?'selected':'' ?>>Bulanan</option>
                            <option value="tahunan" <?= $row['periode']==='tahunan'?'selected':'' ?>>Tahunan</option>
                            <option value="sekali"  <?= $row['periode']==='sekali' ?'selected':'' ?>>Sekali Bayar</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="2"><?= e($row['deskripsi']) ?></textarea>
                </div>

                <hr>

                <!-- OTOMATISASI -->
                <h6 class="text-primary mb-3">
                    <i class="fas fa-magic"></i> Otomatisasi Tagihan
                </h6>

                <div class="form-group">
                    <div class="custom-control custom-checkbox mb-1">
                        <input type="checkbox" class="custom-control-input"
                               id="auto_daftar" name="auto_generate_saat_daftar" value="1"
                               <?= $row['auto_generate_saat_daftar'] ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="auto_daftar">
                            <strong>Auto-generate saat santri baru didaftarkan</strong>
                        </label>
                    </div>
                    <small class="text-muted d-block ml-4">
                        Tagihan otomatis dibuat ketika admin menambah santri baru.
                    </small>
                </div>

                <div class="form-group" id="wrapProrata">
                    <div class="custom-control custom-checkbox mb-1">
                        <input type="checkbox" class="custom-control-input"
                               id="prorata" name="bisa_prorata" value="1"
                               <?= $row['bisa_prorata'] ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="prorata">
                            <strong>Bisa dihitung prorata</strong> (khusus SPP)
                        </label>
                    </div>
                    <small class="text-muted d-block ml-4">
                        Nominal dihitung proporsional kalau didaftarkan di tengah bulan.
                    </small>
                </div>

                <hr>

                <!-- STATUS -->
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input"
                               id="is_active" name="is_active" value="1"
                               <?= $row['is_active'] ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="is_active">
                            <strong>Aktif</strong>
                        </label>
                    </div>
                </div>

                <hr>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Perbarui
                </button>
                <a href="<?= BASE_URL ?>/jenis_pembayaran" class="btn btn-light">
                    <i class="fas fa-times"></i> Batal
                </a>
            </form>
        </div>
    </div>
</div>

<script>
function toggleProrata() {
    const periode = document.getElementById('selectPeriode').value;
    const wrap    = document.getElementById('wrapProrata');
    const cb      = document.getElementById('prorata');

    if (periode === 'bulanan') {
        wrap.style.opacity = '1';
        wrap.style.pointerEvents = 'auto';
    } else {
        wrap.style.opacity = '0.4';
        wrap.style.pointerEvents = 'none';
        cb.checked = false;
    }
}
document.addEventListener('DOMContentLoaded', toggleProrata);
</script>