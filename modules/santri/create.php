<?php
// modules/santri/create.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

// ============================================
// PREFILL dari URL (?ortu_id=X&tipe=ayah)
// ============================================
$prefillOrtuId = (int) ($_GET['ortu_id'] ?? 0);
$prefillOrtu   = null;

if ($prefillOrtuId > 0) {
    $prefillOrtu = fetchOne("SELECT * FROM orang_tua WHERE id = ?", [$prefillOrtuId]);
}

$errors = [];
$old = [
    'nis'=>'', 'nisn'=>'', 'nik'=>'', 'no_kk'=>'', 'no_akta'=>'',
    'nama'=>'', 'nama_panggilan'=>'', 'jenis_kelamin'=>'L',
    'tempat_lahir'=>'', 'tanggal_lahir'=>'', 'agama'=>'Islam',
    'kewarganegaraan'=>'Indonesia', 'anak_ke'=>'', 'jumlah_saudara'=>'',
    'golongan_darah'=>'-', 'no_hp'=>'', 'alamat'=>'', 'rt_rw'=>'',
    'kelurahan'=>'', 'kecamatan'=>'', 'kabupaten'=>'', 'provinsi'=>'',
    'kode_pos'=>'', 'sekolah_formal'=>'', 'kelas_formal'=>'',
    'tanggal_masuk'=>date('Y-m-d'), 'kelas_id'=>'', 'catatan'=>''
];

$kelasList = fetchAll("SELECT id, nama_kelas, urutan FROM kelas WHERE is_active=1 ORDER BY urutan");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    foreach ($old as $k => $v) {
        $old[$k] = trim($_POST[$k] ?? $v);
    }

    // Validasi
    if ($old['nis'] === '')  $errors[] = 'NIS wajib diisi.';
    if ($old['nama'] === '') $errors[] = 'Nama wajib diisi.';
    if ($old['nik'] && strlen($old['nik']) !== 16) $errors[] = 'NIK harus 16 digit.';
    if ($old['no_kk'] && strlen($old['no_kk']) !== 16) $errors[] = 'No. KK harus 16 digit.';

    if (!$errors) {
        if (fetchOne("SELECT id FROM santri WHERE nis = ?", [$old['nis']]))
            $errors[] = 'NIS sudah terdaftar.';
        if ($old['nik'] && fetchOne("SELECT id FROM santri WHERE nik = ?", [$old['nik']]))
            $errors[] = 'NIK sudah terdaftar.';
    }

    if (!$errors) {
        db()->beginTransaction();
        try {
            // 1. Insert santri
            $santriId = insert('santri', [
                'nis'             => $old['nis'],
                'nisn'            => $old['nisn'] ?: null,
                'nik'             => $old['nik'] ?: null,
                'no_kk'           => $old['no_kk'] ?: null,
                'no_akta'         => $old['no_akta'] ?: null,
                'nama'            => $old['nama'],
                'nama_panggilan'  => $old['nama_panggilan'] ?: null,
                'jenis_kelamin'   => $old['jenis_kelamin'],
                'tempat_lahir'    => $old['tempat_lahir'] ?: null,
                'tanggal_lahir'   => $old['tanggal_lahir'] ?: null,
                'agama'           => $old['agama'] ?: 'Islam',
                'kewarganegaraan' => $old['kewarganegaraan'] ?: 'Indonesia',
                'anak_ke'         => $old['anak_ke'] ?: null,
                'jumlah_saudara'  => $old['jumlah_saudara'] ?: null,
                'golongan_darah'  => $old['golongan_darah'] ?: '-',
                'no_hp'           => $old['no_hp'] ?: null,
                'alamat'          => $old['alamat'] ?: null,
                'rt_rw'           => $old['rt_rw'] ?: null,
                'kelurahan'       => $old['kelurahan'] ?: null,
                'kecamatan'       => $old['kecamatan'] ?: null,
                'kabupaten'       => $old['kabupaten'] ?: null,
                'provinsi'        => $old['provinsi'] ?: null,
                'kode_pos'        => $old['kode_pos'] ?: null,
                'sekolah_formal'  => $old['sekolah_formal'] ?: null,
                'kelas_formal'    => $old['kelas_formal'] ?: null,
                'tanggal_masuk'   => $old['tanggal_masuk'] ?: date('Y-m-d'),
                'kelas_id'        => $old['kelas_id'] ?: null,
                'status'          => 'aktif',
                'catatan'         => $old['catatan'] ?: null,
            ]);

            // 2. Upload foto
            if (!empty($_FILES['foto']['name'])) {
                $path = uploadFile($_FILES['foto'], 'santri', ['jpg','jpeg','png']);
                if ($path) update('santri', ['foto'=>$path], 'id = ?', [$santriId]);
            }

            // 3. Riwayat kelas awal
            if (!empty($old['kelas_id'])) {
                insert('riwayat_kelas', [
                    'santri_id'        => $santriId,
                    'kelas_id'         => (int) $old['kelas_id'],
                    'tanggal_mulai'    => $old['tanggal_masuk'] ?: date('Y-m-d'),
                    'status'           => 'aktif',
                    'dipindahkan_oleh' => currentUser()['id'],
                ]);
            }

            // 4. Orang tua
            if (!empty($_POST['ortu']) && is_array($_POST['ortu'])) {
                foreach ($_POST['ortu'] as $o) {
                    if (empty($o['nama_lengkap'])) continue;

                    // Cek duplikat NIK
                    $existing = null;
                    if (!empty($o['nik'])) {
                        $existing = fetchOne("SELECT id FROM orang_tua WHERE nik = ?", [$o['nik']]);
                    }

                    if ($existing) {
                        $ortuId = $existing['id'];
                    } else {
                        $ortuId = insert('orang_tua', [
                            'tipe'          => $o['tipe'] ?? 'wali',
                            'nama_lengkap'  => $o['nama_lengkap'],
                            'nik'           => $o['nik'] ?: null,
                            'no_hp'         => $o['no_hp'] ?: null,
                            'pekerjaan'     => $o['pekerjaan'] ?: null,
                            'alamat'        => $o['alamat'] ?: null,
                        ]);
                    }

                    insert('wali_santri', [
                        'orang_tua_id' => $ortuId,
                        'santri_id'    => $santriId,
                        'is_primary'   => !empty($o['is_primary']) ? 1 : 0,
                    ]);
                }
            }

            db()->commit();
            setFlash('success', 'Santri "' . $old['nama'] . '" berhasil ditambahkan.');

            // Redirect: kalau dari orang tua → balik ke detail ortu
            if ($prefillOrtuId > 0) {
                redirect("orang_tua/detail/$prefillOrtuId");
            }
            redirect('santri');

        } catch (Exception $e) {
            db()->rollBack();
            $errors[] = 'Gagal simpan: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/santri" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-user-plus text-primary"></i> Tambah Santri Baru
            </h1>
            <?php if ($prefillOrtu): ?>
                <p class="text-muted mb-0" style="font-size:13px;">
                    <i class="fas fa-info-circle text-info"></i>
                    Data orang tua: <strong><?= e($prefillOrtu['nama_lengkap']) ?></strong>
                    (<?= ucfirst($prefillOrtu['tipe']) ?>) otomatis terisi
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

        <!-- SECTION 1: IDENTITAS -->
        <div class="card shadow mb-3">
            <div class="card-header py-3 bg-light">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-id-card"></i> 1. Identitas Santri
                </h6>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>NIS <span class="text-danger">*</span></label>
                        <input type="text" name="nis" class="form-control"
                               value="<?= e($old['nis']) ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>NISN</label>
                        <input type="text" name="nisn" class="form-control"
                               value="<?= e($old['nisn']) ?>">
                    </div>
                    <div class="form-group">
                        <label>NIK (16 digit)</label>
                        <input type="text" name="nik" class="form-control"
                               value="<?= e($old['nik']) ?>" maxlength="16">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>No. KK</label>
                        <input type="text" name="no_kk" class="form-control"
                               value="<?= e($old['no_kk']) ?>" maxlength="16">
                    </div>
                    <div class="form-group">
                        <label>No. Akta Kelahiran</label>
                        <input type="text" name="no_akta" class="form-control"
                               value="<?= e($old['no_akta']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Golongan Darah</label>
                        <select name="golongan_darah" class="form-control">
                            <?php foreach (['-','A','B','AB','O'] as $g): ?>
                                <option value="<?= $g ?>" <?= $old['golongan_darah']===$g?'selected':'' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:2;">
                        <label>Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama" class="form-control"
                               value="<?= e($old['nama']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Panggilan</label>
                        <input type="text" name="nama_panggilan" class="form-control"
                               value="<?= e($old['nama_panggilan']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Jenis Kelamin <span class="text-danger">*</span></label>
                        <select name="jenis_kelamin" class="form-control" required>
                            <option value="L" <?= $old['jenis_kelamin']==='L'?'selected':'' ?>>Laki-laki</option>
                            <option value="P" <?= $old['jenis_kelamin']==='P'?'selected':'' ?>>Perempuan</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" class="form-control"
                               value="<?= e($old['tempat_lahir']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control"
                               value="<?= e($old['tanggal_lahir']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Anak ke-</label>
                        <input type="number" name="anak_ke" class="form-control"
                               value="<?= e($old['anak_ke']) ?>" min="1">
                    </div>
                    <div class="form-group">
                        <label>Jumlah Saudara</label>
                        <input type="number" name="jumlah_saudara" class="form-control"
                               value="<?= e($old['jumlah_saudara']) ?>" min="0">
                    </div>
                    <div class="form-group">
                        <label>No. HP Santri</label>
                        <input type="text" name="no_hp" class="form-control"
                               value="<?= e($old['no_hp']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: ALAMAT -->
        <div class="card shadow mb-3">
            <div class="card-header py-3 bg-light">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-map-marker-alt"></i> 2. Alamat
                </h6>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label>Alamat Lengkap</label>
                    <textarea name="alamat" class="form-control" rows="2"><?= e($old['alamat']) ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>RT/RW</label>
                        <input type="text" name="rt_rw" class="form-control" value="<?= e($old['rt_rw']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Kelurahan/Desa</label>
                        <input type="text" name="kelurahan" class="form-control" value="<?= e($old['kelurahan']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Kecamatan</label>
                        <input type="text" name="kecamatan" class="form-control" value="<?= e($old['kecamatan']) ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Kabupaten/Kota</label>
                        <input type="text" name="kabupaten" class="form-control" value="<?= e($old['kabupaten']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Provinsi</label>
                        <input type="text" name="provinsi" class="form-control" value="<?= e($old['provinsi']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Kode Pos</label>
                        <input type="text" name="kode_pos" class="form-control" value="<?= e($old['kode_pos']) ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: PENDIDIKAN & TPQ -->
        <div class="card shadow mb-3">
            <div class="card-header py-3 bg-light">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-graduation-cap"></i> 3. Pendidikan & TPQ
                </h6>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Sekolah Formal</label>
                        <input type="text" name="sekolah_formal" class="form-control"
                               value="<?= e($old['sekolah_formal']) ?>">
                    </div>
                    <div class="form-group">
                        <label>Kelas Formal</label>
                        <input type="text" name="kelas_formal" class="form-control"
                               value="<?= e($old['kelas_formal']) ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Masuk TPQ</label>
                        <input type="date" name="tanggal_masuk" class="form-control"
                               value="<?= e($old['tanggal_masuk']) ?>">
                    </div>
                    <div class="form-group" style="flex:2;">
                        <label><i class="fas fa-layer-group text-primary"></i> Kelas TPQ</label>
                        <select name="kelas_id" class="form-control">
                            <option value="">- Belum ditentukan -</option>
                            <?php foreach ($kelasList as $k): ?>
                                <option value="<?= $k['id'] ?>" <?= $old['kelas_id']==$k['id']?'selected':'' ?>>
                                    <?= e($k['nama_kelas']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Foto Santri</label>
                    <input type="file" name="foto" class="form-control-file"
                           accept="image/jpeg,image/png" data-preview="fotoPreview">
                    <img id="fotoPreview" style="display:none; max-width:120px; margin-top:8px; border-radius:8px;">
                </div>
            </div>
        </div>

        <!-- SECTION 4: ORANG TUA -->
        <div class="card shadow mb-3">
            <div class="card-header py-3 bg-light d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fas fa-users"></i> 4. Data Orang Tua / Wali
                </h6>
                <button type="button" class="btn btn-sm btn-success" onclick="addOrtu()">
                    <i class="fas fa-plus"></i> Tambah
                </button>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">
                    <i class="fas fa-info-circle"></i>
                    Kalau NIK sama dengan yang sudah ada, sistem otomatis link ke data yang sudah ada.
                </p>
                <div id="ortu-container"></div>
            </div>
        </div>

        <!-- CATATAN -->
        <div class="card shadow mb-3">
            <div class="card-body">
                <div class="form-group mb-0">
                    <label>Catatan Tambahan</label>
                    <textarea name="catatan" class="form-control" rows="2"><?= e($old['catatan']) ?></textarea>
                </div>
            </div>
        </div>

        <!-- AKSI -->
        <div class="card shadow mb-4">
            <div class="card-body">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan Santri
                </button>
                <a href="<?= BASE_URL ?>/santri" class="btn btn-light">Batal</a>
            </div>
        </div>
    </form>
</div>

<!-- ============================================
     TEMPLATE ORANG TUA
     ============================================ -->
<template id="ortu-template">
    <div class="card mb-2 ortu-item" style="background:#f8fafc;border:1px solid #e2e8f0;">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="badge bg-primary ortu-label">Orang Tua</span>
                <button type="button" class="btn btn-sm btn-outline-danger"
                        onclick="this.closest('.ortu-item').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Tipe <span class="text-danger">*</span></label>
                    <select name="ortu[__i__][tipe]" class="form-control" required
                            onchange="this.closest('.ortu-item').querySelector('.ortu-label').textContent = this.options[this.selectedIndex].text">
                        <option value="ayah">Ayah</option>
                        <option value="ibu">Ibu</option>
                        <option value="wali">Wali</option>
                    </select>
                </div>
                <div class="form-group" style="flex:2;">
                    <label>Nama Lengkap <span class="text-danger">*</span></label>
                    <input type="text" name="ortu[__i__][nama_lengkap]" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>NIK</label>
                    <input type="text" name="ortu[__i__][nik]" class="form-control" maxlength="16">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>No. HP <span class="text-danger">*</span></label>
                    <input type="text" name="ortu[__i__][no_hp]" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Pekerjaan</label>
                    <input type="text" name="ortu[__i__][pekerjaan]" class="form-control">
                </div>
                <div class="form-group">
                    <label>Alamat</label>
                    <input type="text" name="ortu[__i__][alamat]" class="form-control">
                </div>
            </div>

            <div class="form-check">
                <input type="checkbox" class="form-check-input" name="ortu[__i__][is_primary]" value="1">
                <label class="form-check-label small">
                    Jadikan <strong>wali utama</strong> (kontak darurat)
                </label>
            </div>
        </div>
    </div>
</template>

<script>
// ============================================
// PREFILL dari PHP (kalau ada)
// ============================================
const PREFILL_ORTU = <?= $prefillOrtu ? json_encode([
    'tipe'          => $prefillOrtu['tipe'],
    'nama_lengkap'  => $prefillOrtu['nama_lengkap'],
    'nik'           => $prefillOrtu['nik'],
    'no_hp'         => $prefillOrtu['no_hp'],
    'pekerjaan'     => $prefillOrtu['pekerjaan'],
    'alamat'        => $prefillOrtu['alamat'],
]) : 'null' ?>;

let ortuIndex = 0;

// ============================================
// FUNGSI TAMBAH ORANG TUA
// ============================================
function addOrtu(prefill = null) {
    const tpl = document.getElementById('ortu-template').innerHTML.replace(/__i__/g, ortuIndex++);
    const div = document.createElement('div');
    div.innerHTML = tpl;
    const node = div.firstElementChild;

    // Isi field kalau ada prefill
    if (prefill) {
        const tipe  = node.querySelector('select[name*="[tipe]"]');
        const nama  = node.querySelector('input[name*="[nama_lengkap]"]');
        const nik   = node.querySelector('input[name*="[nik]"]');
        const hp    = node.querySelector('input[name*="[no_hp]"]');
        const kerja = node.querySelector('input[name*="[pekerjaan]"]');
        const alamat= node.querySelector('input[name*="[alamat]"]');
        const prim  = node.querySelector('input[name*="[is_primary]"]');
        const label = node.querySelector('.ortu-label');

        if (tipe   && prefill.tipe)          { tipe.value = prefill.tipe; if (label) label.textContent = tipe.options[tipe.selectedIndex].text; }
        if (nama   && prefill.nama_lengkap)  nama.value  = prefill.nama_lengkap;
        if (nik    && prefill.nik)           nik.value   = prefill.nik;
        if (hp     && prefill.no_hp)         hp.value    = prefill.no_hp;
        if (kerja  && prefill.pekerjaan)     kerja.value = prefill.pekerjaan;
        if (alamat && prefill.alamat)        alamat.value= prefill.alamat;
        if (prim) prim.checked = true;   // Prefill = wali utama
    }

    document.getElementById('ortu-container').appendChild(node);
}

// ============================================
// INIT — otomatis jalan saat load
// ============================================
document.addEventListener('DOMContentLoaded', function() {

    if (PREFILL_ORTU) {
        // Ada prefill → tambah 1 form orang tua terisi otomatis
        addOrtu(PREFILL_ORTU);
    } else if (!document.querySelector('.ortu-item')) {
        // Tidak ada prefill → tetap tampilkan 1 form kosong
        addOrtu();
    }

});
</script>