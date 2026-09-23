<?php
// modules/kelas/create.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$errors = [];
$old = ['nama_kelas' => '', 'urutan' => '', 'tingkat' => '', 'deskripsi' => ''];

// Auto saran urutan berikutnya
$nextUrutan = (int) fetchOne("SELECT COALESCE(MAX(urutan),0)+1 AS n FROM kelas")['n'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $old = [
        'nama_kelas' => trim($_POST['nama_kelas'] ?? ''),
        'urutan'     => (int) ($_POST['urutan'] ?? 0),
        'tingkat'    => trim($_POST['tingkat'] ?? ''),
        'deskripsi'  => trim($_POST['deskripsi'] ?? ''),
        'is_active'  => isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($old['nama_kelas'] === '')   $errors[] = 'Nama kelas wajib diisi.';
    if ($old['urutan'] <= 0)         $errors[] = 'Urutan harus berupa angka positif.';

    // Cek duplikat
    if (!$errors) {
        $dup = fetchOne("SELECT id FROM kelas WHERE nama_kelas = ? OR urutan = ?",
                        [$old['nama_kelas'], $old['urutan']]);
        if ($dup) $errors[] = 'Nama kelas atau urutan sudah dipakai.';
    }

    if (!$errors) {
        try {
            insert('kelas', [
                'nama_kelas' => $old['nama_kelas'],
                'urutan'     => $old['urutan'],
                'tingkat'    => $old['tingkat'] ?: null,
                'deskripsi'  => $old['deskripsi'] ?: null,
                'is_active'  => $old['is_active'],
            ]);
            setFlash('success', 'Kelas "' . $old['nama_kelas'] . '" berhasil ditambahkan.');
            redirect('kelas');
        } catch (Exception $e) {
            $errors[] = 'Gagal menyimpan: ' . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/kelas" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h4 mb-0 text-gray-800">
            <i class="fas fa-plus-circle text-primary"></i> Tambah Kelas
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

                <div class="form-row">
                    <div class="form-group">
                        <label>Nama Kelas <span class="text-danger">*</span></label>
                        <input type="text" name="nama_kelas" class="form-control"
                               value="<?= e($old['nama_kelas']) ?>"
                               placeholder="Contoh: Iqro 1" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Urutan <span class="text-danger">*</span></label>
                        <input type="number" name="urutan" class="form-control"
                               value="<?= e($old['urutan'] ?: $nextUrutan) ?>"
                               min="1" required>
                        <small class="text-muted">Angka lebih kecil = level lebih dasar</small>
                    </div>
                    <div class="form-group">
                        <label>Tingkat</label>
                        <select name="tingkat" class="form-control">
                            <option value="">- Pilih -</option>
                            <?php foreach (['Dasar','Menengah','Lanjutan'] as $t): ?>
                                <option value="<?= $t ?>" <?= $old['tingkat'] === $t ? 'selected' : '' ?>>
                                    <?= $t ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Deskripsi / Capaian Minimal</label>
                    <textarea name="deskripsi" class="form-control" rows="3"
                              placeholder="Contoh: Lancar membaca huruf hijaiyah bersambung"><?= e($old['deskripsi']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="d-flex align-items-center" style="gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1"
                               <?= (!isset($_POST['nama_kelas']) || $old['is_active']) ? 'checked' : '' ?>>
                        Aktifkan kelas ini
                    </label>
                </div>

                <hr>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Simpan
                </button>
                <a href="<?= BASE_URL ?>/kelas" class="btn btn-light">Batal</a>
            </form>
        </div>
    </div>
</div>