<?php
// modules/kelas/edit.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$id = (int) ($id ?? 0);
$row = fetchOne("SELECT * FROM kelas WHERE id = ?", [$id]);
if (!$row) {
    setFlash('error', 'Kelas tidak ditemukan.');
    redirect('kelas');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCSRF($_POST['csrf'] ?? null);

    $data = [
        'nama_kelas' => trim($_POST['nama_kelas'] ?? ''),
        'urutan'     => (int) ($_POST['urutan'] ?? 0),
        'tingkat'    => trim($_POST['tingkat'] ?? ''),
        'deskripsi'  => trim($_POST['deskripsi'] ?? ''),
        'is_active'  => isset($_POST['is_active']) ? 1 : 0,
    ];

    if ($data['nama_kelas'] === '') $errors[] = 'Nama kelas wajib diisi.';
    if ($data['urutan'] <= 0)       $errors[] = 'Urutan harus positif.';

    if (!$errors) {
        $dup = fetchOne("SELECT id FROM kelas WHERE (nama_kelas = ? OR urutan = ?) AND id != ?",
                        [$data['nama_kelas'], $data['urutan'], $id]);
        if ($dup) $errors[] = 'Nama atau urutan sudah dipakai kelas lain.';
    }

    if (!$errors) {
        try {
            update('kelas', [
                'nama_kelas' => $data['nama_kelas'],
                'urutan'     => $data['urutan'],
                'tingkat'    => $data['tingkat'] ?: null,
                'deskripsi'  => $data['deskripsi'] ?: null,
                'is_active'  => $data['is_active'],
            ], 'id = ?', [$id]);

            setFlash('success', 'Kelas berhasil diperbarui.');
            redirect('kelas');
        } catch (Exception $e) {
            $errors[] = 'Gagal update: ' . $e->getMessage();
        }
    }

    // Merge ke $row untuk ditampilkan
    $row = array_merge($row, $data);
}
?>

<div class="container-fluid">
    <div class="d-flex align-items-center mb-3">
        <a href="<?= BASE_URL ?>/kelas" class="btn btn-sm btn-light mr-2">
            <i class="fas fa-arrow-left"></i>
        </a>
        <h1 class="h4 mb-0 text-gray-800">
            <i class="fas fa-edit text-warning"></i> Edit Kelas
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
                               value="<?= e($row['nama_kelas']) ?>" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>Urutan <span class="text-danger">*</span></label>
                        <input type="number" name="urutan" class="form-control"
                               value="<?= e($row['urutan']) ?>" min="1" required>
                    </div>
                    <div class="form-group">
                        <label>Tingkat</label>
                        <select name="tingkat" class="form-control">
                            <option value="">- Pilih -</option>
                            <?php foreach (['Dasar','Menengah','Lanjutan'] as $t): ?>
                                <option value="<?= $t ?>" <?= $row['tingkat'] === $t ? 'selected' : '' ?>>
                                    <?= $t ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Deskripsi</label>
                    <textarea name="deskripsi" class="form-control" rows="3"><?= e($row['deskripsi']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="d-flex align-items-center" style="gap:8px; cursor:pointer;">
                        <input type="checkbox" name="is_active" value="1"
                               <?= $row['is_active'] ? 'checked' : '' ?>>
                        Aktif
                    </label>
                </div>

                <hr>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Perbarui
                </button>
                <a href="<?= BASE_URL ?>/kelas" class="btn btn-light">Batal</a>
            </form>
        </div>
    </div>
</div>