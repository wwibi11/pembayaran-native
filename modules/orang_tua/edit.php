<?php
// modules/orang_tua/edit.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$id = (int) ($id ?? 0);
$row = fetchOne("SELECT * FROM orang_tua WHERE id = ?", [$id]);
if (!$row) {
    setFlash('error', 'Data tidak ditemukan.');
    redirect('orang_tua');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $fields = ['tipe','nama_lengkap','nik','tempat_lahir','tanggal_lahir',
               'jenis_kelamin','pendidikan_terakhir','pekerjaan','penghasilan',
               'no_hp','email','alamat'];

    $data = [];
    foreach ($fields as $f) $data[$f] = trim($_POST[$f] ?? '');

    // Validasi
    if ($data['nama_lengkap'] === '') $errors[] = 'Nama lengkap wajib diisi.';
    if ($data['no_hp'] === '')        $errors[] = 'No. HP wajib diisi.';
    if ($data['nik'] && strlen($data['nik']) !== 16) $errors[] = 'NIK harus 16 digit.';
    if ($data['email'] && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    // Cek duplikat NIK
    if (!$errors && $data['nik']) {
        $dup = fetchOne("SELECT id FROM orang_tua WHERE nik = ? AND id != ?", [$data['nik'], $id]);
        if ($dup) $errors[] = 'NIK sudah dipakai orang tua lain.';
    }

    if (!$errors) {
        try {
            // Null-kan field kosong
            $final = [];
            foreach ($fields as $f) {
                $final[$f] = $data[$f] !== '' ? $data[$f] : null;
            }

            update('orang_tua', $final, 'id = ?', [$id]);

            setFlash('success', 'Data "' . $data['nama_lengkap'] . '" berhasil diperbarui.');
            redirect('orang_tua/detail/' . $id);

        } catch (Exception $e) {
            $errors[] = 'Gagal: ' . $e->getMessage();
        }
    }

    // Merge ke $row untuk ditampilkan kembali
    $row = array_merge($row, $data);
}
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/orang_tua" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-grow-1">
            <h1 class="h4 mb-0 text-gray-800">
                <i class="fas fa-user-edit text-warning"></i> Edit Data Orang Tua
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                <?= e($row['nama_lengkap']) ?> — <?= ucfirst($row['tipe']) ?>
            </p>
        </div>
    </div>

    <!-- Error -->
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0 pl-3">
                <?php foreach ($errors as $er): ?>
                    <li><?= e($er) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="card shadow">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

                <!-- ============================================
                     IDENTITAS
                     ============================================ -->
                <h6 class="text-primary mb-3">
                    <i class="fas fa-user"></i> Identitas
                </h6>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tipe <span class="text-danger">*</span></label>
                        <select name="tipe" class="form-control" required>
                            <option value="ayah" <?= $row['tipe']==='ayah'?'selected':'' ?>>Ayah</option>
                            <option value="ibu"  <?= $row['tipe']==='ibu' ?'selected':'' ?>>Ibu</option>
                            <option value="wali" <?= $row['tipe']==='wali'?'selected':'' ?>>Wali</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:2;">
                        <label>Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="nama_lengkap" class="form-control"
                               value="<?= e($row['nama_lengkap']) ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Jenis Kelamin</label>
                        <select name="jenis_kelamin" class="form-control">
                            <option value="L" <?= $row['jenis_kelamin']==='L'?'selected':'' ?>>Laki-laki</option>
                            <option value="P" <?= $row['jenis_kelamin']==='P'?'selected':'' ?>>Perempuan</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>NIK (16 digit)</label>
                        <input type="text" name="nik" class="form-control"
                               value="<?= e($row['nik']) ?>" maxlength="16"
                               placeholder="3201010101850001">
                    </div>
                    <div class="form-group">
                        <label>Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" class="form-control"
                               value="<?= e($row['tempat_lahir']) ?>"
                               placeholder="Jakarta">
                    </div>
                    <div class="form-group">
                        <label>Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="form-control"
                               value="<?= e($row['tanggal_lahir']) ?>">
                    </div>
                </div>

                <!-- ============================================
                     SOSIAL EKONOMI
                     ============================================ -->
                <h6 class="text-primary mb-3 mt-4">
                    <i class="fas fa-briefcase"></i> Sosial Ekonomi
                </h6>

                <div class="form-row">
                    <div class="form-group">
                        <label>Pendidikan Terakhir</label>
                        <select name="pendidikan_terakhir" class="form-control">
                            <option value="">- Pilih -</option>
                            <?php foreach (['SD','SMP','SMA/SMK','D3','S1','S2','S3'] as $p): ?>
                                <option value="<?= $p ?>" <?= $row['pendidikan_terakhir']===$p?'selected':'' ?>>
                                    <?= $p ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Pekerjaan</label>
                        <input type="text" name="pekerjaan" class="form-control"
                               value="<?= e($row['pekerjaan']) ?>"
                               placeholder="Petani, PNS, Wiraswasta...">
                    </div>
                    <div class="form-group">
                        <label>Penghasilan</label>
                        <select name="penghasilan" class="form-control">
                            <option value="">- Pilih -</option>
                            <?php foreach (['< 1 juta','1-3 juta','3-5 juta','> 5 juta'] as $p): ?>
                                <option value="<?= $p ?>" <?= $row['penghasilan']===$p?'selected':'' ?>>
                                    <?= $p ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- ============================================
                     KONTAK
                     ============================================ -->
                <h6 class="text-primary mb-3 mt-4">
                    <i class="fas fa-address-book"></i> Kontak
                </h6>

                <div class="form-row">
                    <div class="form-group">
                        <label>No. HP <span class="text-danger">*</span></label>
                        <input type="text" name="no_hp" class="form-control"
                               value="<?= e($row['no_hp']) ?>"
                               placeholder="08123456789" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= e($row['email']) ?>"
                               placeholder="nama@email.com">
                    </div>
                </div>

                <div class="form-group">
                    <label>Alamat</label>
                    <textarea name="alamat" class="form-control" rows="2"
                              placeholder="Alamat lengkap"><?= e($row['alamat']) ?></textarea>
                </div>

                <hr>

                <!-- ============================================
                     AKSI
                     ============================================ -->
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Perbarui
                </button>
                <a href="<?= BASE_URL ?>/orang_tua" class="btn btn-light">
                    <i class="fas fa-times"></i> Batal
                </a>
                <a href="<?= BASE_URL ?>/orang_tua/detail/<?= (int) $row['id'] ?>"
                   class="btn btn-outline-info float-right">
                    <i class="fas fa-eye"></i> Lihat Detail
                </a>
            </form>
        </div>
    </div>

</div>