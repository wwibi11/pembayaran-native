<?php
// modules/settings/proses.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('settings');
}

checkCSRF($_POST['csrf'] ?? null);

$errors = [];

// ============================================
// DAFTAR SETTING YANG BOLEH DIUBAH
// ============================================
$allowedKeys = [
    'nama_madin',
    'tahun_ajaran',
    'alamat',
    'telepon',
    'email_madin',
    'nama_kepala',
    'nip_kepala',
    'info_rekening',
    'info_qris',
    'info_cash',
    'deadline_verifikasi',
    'maintenance_mode',
];

// ============================================
// VALIDASI
// ============================================
if (empty(trim($_POST['nama_madin'] ?? ''))) {
    $errors[] = 'Nama Madin wajib diisi.';
}

if (!empty($_POST['email_madin']) && !filter_var($_POST['email_madin'], FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Format email tidak valid.';
}

// ============================================
// UPLOAD LOGO (kalau ada)
// ============================================
$logoPath = null;
if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {

    $file = $_FILES['logo'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png'];

    // Validasi
    if (!in_array($ext, $allowed)) {
        $errors[] = 'Format logo harus JPG atau PNG.';
    } elseif ($file['size'] > 1024 * 1024) { // max 1 MB
        $errors[] = 'Ukuran logo maksimal 1 MB.';
    } else {
        // Cek apakah file gambar valid
        $imgInfo = @getimagesize($file['tmp_name']);
        if (!$imgInfo) {
            $errors[] = 'File yang diupload bukan gambar valid.';
        }
    }

    // Upload kalau valid
    if (!$errors) {
        $dir = __DIR__ . '/../../uploads/logo';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        // Hapus logo lama
        $oldLogo = setting('logo');
        if ($oldLogo && strpos($oldLogo, 'uploads/logo/') === 0) {
            $oldFile = __DIR__ . '/../../' . $oldLogo;
            if (file_exists($oldFile)) @unlink($oldFile);
        }

        // Nama file unik
        $filename = 'logo_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $dir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $target)) {
            $logoPath = 'uploads/logo/' . $filename;
        } else {
            $errors[] = 'Gagal upload logo. Cek permission folder uploads/logo.';
        }
    }
}

// ============================================
// SIMPAN SETTING
// ============================================
if (!$errors) {
    try {
        db()->beginTransaction();

        // Simpan setting biasa
        foreach ($allowedKeys as $key) {
            // Khusus maintenance_mode (checkbox)
            if ($key === 'maintenance_mode') {
                $val = isset($_POST['maintenance_mode']) ? '1' : '0';
            } else {
                $val = trim($_POST[$key] ?? '');
            }

            // Update atau insert
            $existing = fetchOne("SELECT id FROM settings WHERE setting_key = ?", [$key]);

            if ($existing) {
                execute("UPDATE settings SET setting_val = ? WHERE setting_key = ?", [$val, $key]);
            } else {
                execute("INSERT INTO settings (setting_key, setting_val) VALUES (?, ?)", [$key, $val]);
            }
        }

        // Simpan logo kalau ada upload
        if ($logoPath) {
            $existing = fetchOne("SELECT id FROM settings WHERE setting_key = 'logo'");
            if ($existing) {
                execute("UPDATE settings SET setting_val = ? WHERE setting_key = 'logo'", [$logoPath]);
            } else {
                execute("INSERT INTO settings (setting_key, setting_val) VALUES ('logo', ?)", [$logoPath]);
            }
        }

        db()->commit();

        setFlash('success', 'Pengaturan berhasil disimpan.');
        redirect('settings');

    } catch (Exception $e) {
        db()->rollBack();
        $errors[] = 'Gagal menyimpan: ' . $e->getMessage();
    }
}

// Kalau ada error, kembalikan
if ($errors) {
    setFlash('error', implode(' | ', $errors));
    redirect('settings');
}