<?php
// modules/santri/edit.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$row = fetchOne("SELECT * FROM santri WHERE id = ?", [$id]);
if (!$row) { setFlash('error','Santri tidak ditemukan.'); redirect('santri'); }

$errors = [];
$kelasList = fetchAll("SELECT id, nama_kelas, urutan FROM kelas WHERE is_active=1 ORDER BY urutan");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $fields = ['nis','nisn','nik','no_kk','no_akta','nama','nama_panggilan',
               'jenis_kelamin','tempat_lahir','tanggal_lahir','agama','kewarganegaraan',
               'anak_ke','jumlah_saudara','golongan_darah','no_hp','alamat','rt_rw',
               'kelurahan','kecamatan','kabupaten','provinsi','kode_pos',
               'sekolah_formal','kelas_formal','tanggal_masuk','kelas_id','catatan'];

    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

    if ($data['nis'] === '')  $errors[] = 'NIS wajib diisi.';
    if ($data['nama'] === '') $errors[] = 'Nama wajib diisi.';
    if ($data['nik'] && strlen($data['nik']) !== 16) $errors[] = 'NIK harus 16 digit.';

    // Cek duplikat NIS & NIK
    if (!$errors) {
        if (fetchOne("SELECT id FROM santri WHERE nis = ? AND id != ?", [$data['nis'], $id]))
            $errors[] = 'NIS sudah dipakai santri lain.';
        if ($data['nik'] && fetchOne("SELECT id FROM santri WHERE nik = ? AND id != ?", [$data['nik'], $id]))
            $errors[] = 'NIK sudah dipakai santri lain.';
    }

    if (!$errors) {
        try {
            $update = [];
            foreach ($fields as $f) {
                $update[$f] = $data[$f] !== '' ? $data[$f] : null;
            }
            // Status tidak berubah dari edit (ubah lewat kenaikan / menu khusus)
            $update['status'] = $row['status'];

            update('santri', $update, 'id = ?', [$id]);

            // Upload foto baru
            if (!empty($_FILES['foto']['name'])) {
                $path = uploadFile($_FILES['foto'], 'santri', ['jpg','jpeg','png']);
                if ($path) {
                    if ($row['foto'] && file_exists(__DIR__ . '/../../' . $row['foto'])) {
                        @unlink(__DIR__ . '/../../' . $row['foto']);
                    }
                    update('santri', ['foto'=>$path], 'id = ?', [$id]);
                }
            }

            setFlash('success', 'Data santri berhasil diperbarui.');
            redirect('santri/detail/' . $id);

        } catch (Exception $e) {
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }

    $row = array_merge($row, $data);
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/santri/detail/<?= $id ?>" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h4 mb-0 text-gray-800">
            <i class="fas fa-user-edit text-warning"></i> Edit Santri
        </h1>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

        <div class="card shadow mb-3">
            <div class="card-header py-2 bg-light">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-id-card"></i> Identitas</h6>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group"><label>NIS *</label>
                        <input type="text" name="nis" class="form-control" value="<?= e($row['nis']) ?>" required></div>
                    <div class="form-group"><label>NISN</label>
                        <input type="text" name="nisn" class="form-control" value="<?= e($row['nisn']) ?>"></div>
                    <div class="form-group"><label>NIK</label>
                        <input type="text" name="nik" class="form-control" value="<?= e($row['nik']) ?>" maxlength="16"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>No. KK</label>
                        <input type="text" name="no_kk" class="form-control" value="<?= e($row['no_kk']) ?>" maxlength="16"></div>
                    <div class="form-group"><label>No. Akta</label>
                        <input type="text" name="no_akta" class="form-control" value="<?= e($row['no_akta']) ?>"></div>
                    <div class="form-group"><label>Gol. Darah</label>
                        <select name="golongan_darah" class="form-control">
                            <?php foreach (['-','A','B','AB','O'] as $g): ?>
                                <option value="<?= $g ?>" <?= $row['golongan_darah']===$g?'selected':'' ?>><?= $g ?></option>
                            <?php endforeach; ?>
                        </select></div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex:2;"><label>Nama Lengkap *</label>
                        <input type="text" name="nama" class="form-control" value="<?= e($row['nama']) ?>" required></div>
                    <div class="form-group"><label>Nama Panggilan</label>
                        <input type="text" name="nama_panggilan" class="form-control" value="<?= e($row['nama_panggilan']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>JK</label>
                        <select name="jenis_kelamin" class="form-control">
                            <option value="L" <?= $row['jenis_kelamin']==='L'?'selected':'' ?>>Laki-laki</option>
                            <option value="P" <?= $row['jenis_kelamin']==='P'?'selected':'' ?>>Perempuan</option>
                        </select></div>
                    <div class="form-group"><label>Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" class="form-control" value="<?= e($row['tempat_lahir']) ?>"></div>
                    <div class="form-group"><label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control" value="<?= e($row['tanggal_lahir']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Anak ke-</label>
                        <input type="number" name="anak_ke" class="form-control" value="<?= e($row['anak_ke']) ?>"></div>
                    <div class="form-group"><label>Jumlah Saudara</label>
                        <input type="number" name="jumlah_saudara" class="form-control" value="<?= e($row['jumlah_saudara']) ?>"></div>
                    <div class="form-group"><label>No. HP</label>
                        <input type="text" name="no_hp" class="form-control" value="<?= e($row['no_hp']) ?>"></div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-3">
            <div class="card-header py-2 bg-light">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-map-marker-alt"></i> Alamat</h6>
            </div>
            <div class="card-body">
                <div class="form-group"><label>Alamat</label>
                    <textarea name="alamat" class="form-control" rows="2"><?= e($row['alamat']) ?></textarea></div>
                <div class="form-row">
                    <div class="form-group"><label>RT/RW</label>
                        <input type="text" name="rt_rw" class="form-control" value="<?= e($row['rt_rw']) ?>"></div>
                    <div class="form-group"><label>Kelurahan</label>
                        <input type="text" name="kelurahan" class="form-control" value="<?= e($row['kelurahan']) ?>"></div>
                    <div class="form-group"><label>Kecamatan</label>
                        <input type="text" name="kecamatan" class="form-control" value="<?= e($row['kecamatan']) ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Kabupaten</label>
                        <input type="text" name="kabupaten" class="form-control" value="<?= e($row['kabupaten']) ?>"></div>
                    <div class="form-group"><label>Provinsi</label>
                        <input type="text" name="provinsi" class="form-control" value="<?= e($row['provinsi']) ?>"></div>
                    <div class="form-group"><label>Kode Pos</label>
                        <input type="text" name="kode_pos" class="form-control" value="<?= e($row['kode_pos']) ?>"></div>
                </div>
            </div>
        </div>

        <div class="card shadow mb-3">
            <div class="card-header py-2 bg-light">
                <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-graduation-cap"></i> Pendidikan</h6>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group"><label>Sekolah Formal</label>
                        <input type="text" name="sekolah_formal" class="form-control" value="<?= e($row['sekolah_formal']) ?>"></div>
                    <div class="form-group"><label>Kelas Formal</label>
                        <input type="text" name="kelas_formal" class="form-control" value="<?= e($row['kelas_formal']) ?>"></div>
                    <div class="form-group"><label>Tanggal Masuk</label>
                        <input type="date" name="tanggal_masuk" class="form-control" value="<?= e($row['tanggal_masuk']) ?>"></div>
                </div>

                <div class="alert alert-info py-2 mb-2">
                    <small>
                        <i class="fas fa-info-circle"></i>
                        <strong>Ubah kelas di sini tidak mencatat riwayat.</strong>
                        Untuk kenaikan yang tercatat di riwayat belajar, gunakan menu
                        <a href="<?= BASE_URL ?>/kenaikan/create/<?= $id ?>">Naik Kelas</a>.
                    </small>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex:2;"><label>Kelas TPQ</label>
                        <select name="kelas_id" class="form-control">
                            <option value="">- Belum ditentukan -</option>
                            <?php foreach ($kelasList as $k): ?>
                                <option value="<?= $k['id'] ?>" <?= $row['kelas_id']==$k['id']?'selected':'' ?>>
                                    <?= e($k['nama_kelas']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Foto</label>
                    <?php if ($row['foto'] && file_exists(__DIR__ . '/../../' . $row['foto'])): ?>
                        <div class="mb-2">
                            <img src="<?= BASE_URL . '/' . e($row['foto']) ?>"
                                 style="width:80px;height:80px;object-fit:cover;border-radius:8px;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="foto" class="form-control-file"
                           accept="image/jpeg,image/png" data-preview="fotoPreview">
                    <img id="fotoPreview" style="display:none; max-width:120px; margin-top:8px; border-radius:8px;">
                </div>

                <div class="form-group"><label>Catatan</label>
                    <textarea name="catatan" class="form-control" rows="2"><?= e($row['catatan']) ?></textarea></div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-body">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Perbarui
                </button>
                <a href="<?= BASE_URL ?>/santri/detail/<?= $id ?>" class="btn btn-light">Batal</a>
            </div>
        </div>
    </form>
</div>