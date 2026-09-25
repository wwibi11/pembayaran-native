<?php
// modules/jenis_pembayaran/create.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$errors = [];
$old = [
    'nama'                      => '',
    'nominal_default'           => '',
    'periode'                   => 'bulanan',
    'deskripsi'                 => '',
    'is_active'                 => 1,
    'auto_generate_saat_daftar' => 0,
    'bisa_prorata'              => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $old = [
        'nama'                      => trim($_POST['nama'] ?? ''),
        'nominal_default'           => (float) str_replace(['.', ','], ['', '.'], $_POST['nominal_default'] ?? '0'),
        'periode'                   => $_POST['periode'] ?? 'bulanan',
        'deskripsi'                 => trim($_POST['deskripsi'] ?? ''),
        'is_active'                 => isset($_POST['is_active']) ? 1 : 0,
        'auto_generate_saat_daftar' => isset($_POST['auto_generate_saat_daftar']) ? 1 : 0,
        'bisa_prorata'              => isset($_POST['bisa_prorata']) ? 1 : 0,
    ];

    // Validasi
    if ($old['nama'] === '') $errors[] = 'Nama jenis pembayaran wajib diisi.';
    if ($old['nominal_default'] <= 0) $errors[] = 'Nominal harus lebih dari 0.';
    if (!in_array($old['periode'], ['bulanan','tahunan','sekali'])) {
        $errors[] = 'Periode tidak valid.';
    }
    if ($old['bisa_prorata'] && $old['periode'] !== 'bulanan') {
        $errors[] = 'Prorata hanya berlaku untuk periode bulanan.';
    }

    // Cek duplikat nama
    if (!$errors) {
        $dup = fetchOne("SELECT id FROM jenis_pembayaran WHERE nama = ?", [$old['nama']]);
        if ($dup) $errors[] = 'Nama jenis pembayaran sudah ada.';
    }

    if (!$errors) {
        try {
            insert('jenis_pembayaran', [
                'nama'                      => $old['nama'],
                'nominal_default'           => $old['nominal_default'],
                'periode'                   => $old['periode'],
                'deskripsi'                 => $old['deskripsi'] ?: null,
                'is_active'                 => $old['is_active'],
                'auto_generate_saat_daftar' => $old['auto_generate_saat_daftar'],
                'bisa_prorata'              => $old['bisa_prorata'],
            ]);

            setFlash('success', 'Jenis pembayaran "' . $old['nama'] . '" berhasil ditambahkan.');
            redirect('jenis_pembayaran');
        } catch (Exception $e) {
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/jenis_pembayaran" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h4 mb-0 text-gray-800">
            <i class="fas fa-plus-circle text-primary"></i> Tambah Jenis Pembayaran
        </h1>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                <?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
            </ul>
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
                               value="<?= e($old['nama']) ?>"
                               placeholder="Contoh: SPP Bulanan, Seragam, Uang Pendaftaran"
                               required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Nominal Default <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">Rp</span>
                            </div>
                            <input type="text" name="nominal_default"
                                   class="form-control" data-rupiah
                                   value="<?= e($old['nominal_default']) ?>"
                                   placeholder="50.000" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Periode <span class="text-danger">*</span></label>
                        <select name="periode" class="form-control" required
                                onchange="toggleProrata()" id="selectPeriode">
                            <option value="bulanan" <?= $old['periode']==='bulanan'?'selected':'' ?>>
                                Bulanan
                            </option>
                            <option value="tahunan" <?= $old['periode']==='tahunan'?'selected':'' ?>>
                                Tahunan
                            </option>
                            <option value="sekali"  <?= $old['periode']==='sekali' ?'selected':'' ?>>
                                Sekali Bayar
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="2"
                              placeholder="Keterangan tambahan (opsional)"><?= e($old['deskripsi']) ?></textarea>
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
                               <?= $old['auto_generate_saat_daftar'] ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="auto_daftar">
                            <strong>Auto-generate saat santri baru didaftarkan</strong>
                        </label>
                    </div>
                    <small class="text-muted d-block ml-4">
                        Kalau dicentang, tagihan jenis ini otomatis dibuat ketika admin menambah santri baru.
                        <br>Cocok untuk: <em>Uang Pendaftaran, Seragam, SPP bulan pertama</em>.
                    </small>
                </div>

                <div class="form-group" id="wrapProrata">
                    <div class="custom-control custom-checkbox mb-1">
                        <input type="checkbox" class="custom-control-input"
                               id="prorata" name="bisa_prorata" value="1"
                               <?= $old['bisa_prorata'] ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="prorata">
                            <strong>Bisa dihitung prorata</strong> (khusus SPP)
                        </label>
                    </div>
                    <small class="text-muted d-block ml-4">
                        Santri yang daftar di tengah bulan akan ditagih sesuai jumlah hari tersisa.
                        <br><em>Contoh: Daftar 15 Okt, SPP Rp 50.000 → ditagih ± Rp 27.000 (17/31 hari)</em>
                    </small>
                </div>

                <hr>

                <!-- STATUS -->
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input"
                               id="is_active" name="is_active" value="1"
                               <?= (!isset($_POST['nama']) || $old['is_active']) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="is_active">
                            <strong>Aktifkan jenis pembayaran ini</strong>
                        </label>
                    </div>
                    <small class="text-muted d-block ml-4">
                        Jenis nonaktif tidak muncul di form generate tagihan
                    </small>
                </div>

                <hr>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
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