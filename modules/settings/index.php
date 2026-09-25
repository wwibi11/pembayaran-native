<?php
// modules/settings/index.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin')) { http_response_code(403); exit('Akses ditolak.'); }

$current_module = 'settings';

// Ambil semua setting
$settings = [];
foreach (fetchAll("SELECT setting_key, setting_val FROM settings") as $s) {
    $settings[$s['setting_key']] = $s['setting_val'];
}

// Statistik database
$stats = [
    'santri'     => (int) fetchColumn("SELECT COUNT(*) FROM santri"),
    'kelas'      => (int) fetchColumn("SELECT COUNT(*) FROM kelas"),
    'tagihan'    => (int) fetchColumn("SELECT COUNT(*) FROM tagihan"),
    'pembayaran' => (int) fetchColumn("SELECT COUNT(*) FROM pembayaran"),
    'users'      => (int) fetchColumn("SELECT COUNT(*) FROM users"),
    'orang_tua'  => (int) fetchColumn("SELECT COUNT(*) FROM orang_tua"),
];

// Informasi server
$serverInfo = [
    'php_version'   => PHP_VERSION,
    'mysql_version' => db()->getAttribute(PDO::ATTR_SERVER_VERSION),
    'max_upload'    => ini_get('upload_max_filesize'),
    'max_post'      => ini_get('post_max_size'),
    'timezone'      => date_default_timezone_get(),
    'server_time'   => date('d/m/Y H:i:s'),
];

// Ukuran database
try {
    $dbSize = fetchColumn("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size
        FROM information_schema.tables
        WHERE table_schema = ?
    ", [DB_NAME]);
} catch (Exception $e) {
    $dbSize = 0;
}
?>

<div class="container-fluid">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
        <div>
            <h1 class="h4 mb-1 text-gray-800">
                <i class="fas fa-cog text-primary"></i> Pengaturan
            </h1>
            <p class="text-muted mb-0" style="font-size:13px;">
                Konfigurasi sistem, informasi madin, dan pengaturan lainnya
            </p>
        </div>
    </div>

    <!-- Flash -->
    <?php if ($ok = getFlash('success')): ?>
        <div class="alert alert-success alert-auto-close">
            <i class="fas fa-check-circle"></i> <?= e($ok) ?>
        </div>
    <?php endif; ?>
    <?php if ($err = getFlash('error')): ?>
        <div class="alert alert-danger alert-auto-close">
            <i class="fas fa-exclamation-circle"></i> <?= e($err) ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">

            <!-- Form Pengaturan -->
            <form method="POST" action="<?= BASE_URL ?>/settings/proses" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= generateCSRF() ?>">

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs mb-3" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#tab-madin" role="tab">
                            <i class="fas fa-mosque"></i> Identitas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-kontak" role="tab">
                            <i class="fas fa-address-book"></i> Kontak
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-pembayaran" role="tab">
                            <i class="fas fa-money-bill"></i> Pembayaran
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-sistem" role="tab">
                            <i class="fas fa-tools"></i> Sistem
                        </a>
                    </li>
                </ul>

                <div class="tab-content">

                    <!-- TAB 1: IDENTITAS -->
                    <div class="tab-pane fade show active" id="tab-madin" role="tabpanel">
                        <div class="card shadow mb-3">
                            <div class="card-header py-2 bg-light">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-mosque"></i> Identitas Madin
                                </h6>
                            </div>
                            <div class="card-body">

                                <div class="form-row">
                                    <div class="form-group" style="flex:2;">
                                        <label>Nama Madin <span class="text-danger">*</span></label>
                                        <input type="text" name="nama_madin" class="form-control"
                                               value="<?= e($settings['nama_madin'] ?? 'TPQ MADIN') ?>"
                                               placeholder="Contoh: TPQ Al-Hidayah" required>
                                        <small class="text-muted">
                                            Nama ini muncul di sidebar, login, footer, dan laporan
                                        </small>
                                    </div>
                                    <div class="form-group">
                                        <label>Tahun Ajaran</label>
                                        <input type="text" name="tahun_ajaran" class="form-control"
                                               value="<?= e($settings['tahun_ajaran'] ?? '') ?>"
                                               placeholder="2024/2025">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Alamat Lengkap</label>
                                    <textarea name="alamat" class="form-control" rows="2"
                                              placeholder="Jl. Pendidikan No. 1, Kelurahan, Kecamatan, Kabupaten"
                                              ><?= e($settings['alamat'] ?? '') ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Logo Madin</label>
                                    <div class="row">
                                        <div class="col-md-8">
                                            <input type="file" name="logo" class="form-control-file"
                                                   accept="image/jpeg,image/png"
                                                   data-preview="previewLogo">
                                            <small class="text-muted d-block mt-1">
                                                Format: JPG atau PNG. Ukuran maks 1 MB. Rasio ideal 1:1.
                                            </small>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <?php
                                            $logoPath = $settings['logo'] ?? 'assets/img/logo.png';
                                            $logoExists = $logoPath && file_exists(__DIR__ . '/../../' . $logoPath);
                                            ?>
                                            <div style="border:2px dashed #cbd5e1; border-radius:8px; padding:12px; background:#f8fafc;">
                                                <?php if ($logoExists): ?>
                                                    <img id="previewLogo" src="<?= BASE_URL . '/' . e($logoPath) ?>?v=<?= time() ?>"
                                                         alt="Logo" style="max-width:80px; max-height:80px;">
                                                <?php else: ?>
                                                    <img id="previewLogo" src="" alt=""
                                                         style="max-width:80px; max-height:80px; display:none;">
                                                    <div id="noLogo" style="padding:16px; color:#94a3b8;">
                                                        <i class="fas fa-image fa-2x"></i>
                                                        <div class="small mt-1">Belum ada logo</div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: KONTAK -->
                    <div class="tab-pane fade" id="tab-kontak" role="tabpanel">
                        <div class="card shadow mb-3">
                            <div class="card-header py-2 bg-light">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-address-book"></i> Kontak & Info
                                </h6>
                            </div>
                            <div class="card-body">

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Telepon / WA</label>
                                        <input type="text" name="telepon" class="form-control"
                                               value="<?= e($settings['telepon'] ?? '') ?>"
                                               placeholder="08123456789">
                                    </div>
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" name="email_madin" class="form-control"
                                               value="<?= e($settings['email_madin'] ?? '') ?>"
                                               placeholder="info@madin.id">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Nama Kepala Madin</label>
                                    <input type="text" name="nama_kepala" class="form-control"
                                           value="<?= e($settings['nama_kepala'] ?? '') ?>"
                                           placeholder="Nama kepala untuk tanda tangan laporan">
                                </div>

                                <div class="form-group">
                                    <label>NIP Kepala (opsional)</label>
                                    <input type="text" name="nip_kepala" class="form-control"
                                           value="<?= e($settings['nip_kepala'] ?? '') ?>"
                                           placeholder="NIP kepala">
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- TAB 3: PEMBAYARAN -->
                    <div class="tab-pane fade" id="tab-pembayaran" role="tabpanel">
                        <div class="card shadow mb-3">
                            <div class="card-header py-2 bg-light">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-money-bill"></i> Info Rekening & Pembayaran
                                </h6>
                            </div>
                            <div class="card-body">

                                <div class="alert alert-info py-2 mb-3">
                                    <small>
                                        <i class="fas fa-info-circle"></i>
                                        Info ini muncul di halaman Upload Bukti Bayar wali, sebagai panduan transfer.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-university"></i> Info Rekening Bank</label>
                                    <input type="text" name="info_rekening" class="form-control"
                                           value="<?= e($settings['info_rekening'] ?? '') ?>"
                                           placeholder="BCA 1234567890 a.n. TPQ Madin">
                                    <small class="text-muted">
                                        Contoh: BCA 1234567890 a.n. TPQ Al-Hidayah
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-qrcode"></i> Info QRIS</label>
                                    <input type="text" name="info_qris" class="form-control"
                                           value="<?= e($settings['info_qris'] ?? '') ?>"
                                           placeholder="QRIS tersedia di kantor TPQ">
                                    <small class="text-muted">
                                        Keterangan tentang QRIS (scan di kantor, dll)
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-money-bill-wave"></i> Info Cash</label>
                                    <input type="text" name="info_cash" class="form-control"
                                           value="<?= e($settings['info_cash'] ?? '') ?>"
                                           placeholder="Bayar langsung ke admin/kantor TPQ">
                                </div>

                                <div class="form-group">
                                    <label><i class="fas fa-clock"></i> Deadline Verifikasi (jam)</label>
                                    <input type="number" name="deadline_verifikasi" class="form-control"
                                           value="<?= e($settings['deadline_verifikasi'] ?? '24') ?>"
                                           min="1" max="168">
                                    <small class="text-muted">
                                        Estimasi waktu admin verifikasi bukti bayar (dalam jam)
                                    </small>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- TAB 4: SISTEM -->
                    <div class="tab-pane fade" id="tab-sistem" role="tabpanel">
                        <div class="card shadow mb-3">
                            <div class="card-header py-2 bg-light">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-tools"></i> Sistem
                                </h6>
                            </div>
                            <div class="card-body">

                                <div class="form-group">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input"
                                               id="maintenance_mode" name="maintenance_mode" value="1"
                                               <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="maintenance_mode">
                                            <strong>Mode Maintenance</strong>
                                        </label>
                                    </div>
                                    <small class="text-muted d-block ml-4">
                                        Kalau aktif, hanya <strong>admin</strong> yang bisa akses sistem.
                                        User lain akan melihat halaman "Under Maintenance".
                                    </small>
                                </div>

                                <div class="alert alert-warning py-2 mt-3 mb-0">
                                    <small>
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Hati-hati saat mengaktifkan Maintenance Mode. Pastikan Anda masih bisa login sebagai admin.
                                    </small>
                                </div>

                            </div>
                        </div>
                    </div>

                </div>

                <!-- Tombol Aksi -->
                <div class="card shadow mb-4">
                    <div class="card-body d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i>
                                Perubahan langsung tersimpan setelah klik Simpan
                            </small>
                        </div>
                        <div class="mt-2 mt-md-0">
                            <button type="submit" class="btn btn-primary"
                                    data-confirm="Simpan semua pengaturan?">
                                <i class="fas fa-save"></i> Simpan Pengaturan
                            </button>
                        </div>
                    </div>
                </div>

            </form>

        </div>

        <!-- Sidebar Kanan: Info Sistem -->
        <div class="col-lg-4">

            <!-- Statistik Database -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-database"></i> Statistik Data
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td><i class="fas fa-user-graduate text-primary"></i> Santri</td>
                            <td class="text-right font-weight-bold"><?= number_format($stats['santri']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-layer-group text-info"></i> Kelas</td>
                            <td class="text-right font-weight-bold"><?= number_format($stats['kelas']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-users text-success"></i> Orang Tua</td>
                            <td class="text-right font-weight-bold"><?= number_format($stats['orang_tua']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-file-invoice text-warning"></i> Tagihan</td>
                            <td class="text-right font-weight-bold"><?= number_format($stats['tagihan']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-cash-register text-success"></i> Pembayaran</td>
                            <td class="text-right font-weight-bold"><?= number_format($stats['pembayaran']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-users-cog text-danger"></i> User</td>
                            <td class="text-right font-weight-bold"><?= number_format($stats['users']) ?></td>
                        </tr>
                        <tr style="border-top:2px solid #e5e7eb;">
                            <td><i class="fas fa-hdd text-secondary"></i> Ukuran DB</td>
                            <td class="text-right font-weight-bold"><?= $dbSize ?> MB</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Info Server -->
            <div class="card shadow mb-3">
                <div class="card-header py-2 bg-light">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-server"></i> Info Server
                    </h6>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <td class="text-muted">PHP</td>
                            <td class="text-right"><small><?= e($serverInfo['php_version']) ?></small></td>
                        </tr>
                        <tr>
                            <td class="text-muted">MySQL</td>
                            <td class="text-right"><small><?= e($serverInfo['mysql_version']) ?></small></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Max Upload</td>
                            <td class="text-right"><small><?= e($serverInfo['max_upload']) ?></small></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Timezone</td>
                            <td class="text-right"><small><?= e($serverInfo['timezone']) ?></small></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Waktu Server</td>
                            <td class="text-right">
                                <small><?= e($serverInfo['server_time']) ?></small>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Info Bantuan -->
            <div class="card shadow border-info">
                <div class="card-header py-2 bg-info text-white">
                    <h6 class="m-0 font-weight-bold">
                        <i class="fas fa-question-circle"></i> Bantuan
                    </h6>
                </div>
                <div class="card-body small">
                    <p class="mb-2"><strong>Pengaturan penting:</strong></p>
                    <ul class="pl-3 mb-3">
                        <li><strong>Nama Madin</strong> — muncul di semua halaman</li>
                        <li><strong>Logo</strong> — muncul di sidebar & login</li>
                        <li><strong>Info Rekening</strong> — panduan untuk wali transfer</li>
                        <li><strong>Maintenance Mode</strong> — kunci sistem sementara</li>
                    </ul>
                    <p class="mb-0">
                        <i class="fas fa-shield-alt text-warning"></i>
                        Backup database rutin lewat phpMyAdmin untuk keamanan data.
                    </p>
                </div>
            </div>

        </div>
    </div>

</div>

<style>
.nav-tabs .nav-link {
    color: #6b7280;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 10px 16px;
    font-size: 13px;
}
.nav-tabs .nav-link.active {
    color: #2c6b9e;
    font-weight: 600;
    border-bottom-color: #2c6b9e;
    background: transparent;
}
.nav-tabs .nav-link:hover {
    border-bottom-color: #cbd5e1;
}
.form-group label {
    font-weight: 500;
    color: #374151;
}
</style>