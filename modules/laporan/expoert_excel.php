<?php
// modules/laporan/export_excel.php
require_once __DIR__ . '/../../config/functions.php';

if (!hasRole('admin') && !hasRole('kepala')) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$type   = $_GET['type'] ?? 'pembayaran';
$dari   = $_GET['dari']   ?? date('Y-m-01');
$sampai = $_GET['sampai'] ?? date('Y-m-t');
$jenisId= (int) ($_GET['jenis'] ?? 0);
$kelasId= (int) ($_GET['kelas'] ?? 0);
$metode = $_GET['metode'] ?? '';

$filename = 'laporan_' . $type . '_' . date('Ymd_His') . '.csv';

// Header CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM untuk Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// ============================================
// LAPORAN PEMBAYARAN
// ============================================
if ($type === 'pembayaran') {

    // Header row
    fputcsv($output, [
        'No', 'Tanggal', 'NIS', 'Nama Santri', 'Kelas',
        'Jenis Pembayaran', 'Periode', 'Metode',
        'Nominal Bayar', 'Diverifikasi Oleh', 'Tanggal Verifikasi'
    ]);

    $where = "WHERE p.status='diverifikasi' AND p.tanggal_bayar BETWEEN ? AND ?";
    $params = [$dari, $sampai];

    if ($jenisId) { $where .= " AND t.jenis_pembayaran_id = ?"; $params[] = $jenisId; }
    if ($kelasId) { $where .= " AND s.kelas_id = ?"; $params[] = $kelasId; }
    if ($metode)  { $where .= " AND p.metode = ?"; $params[] = $metode; }

    $list = fetchAll("
        SELECT p.tanggal_bayar, s.nis, s.nama AS nama_santri, k.nama_kelas,
               jp.nama AS jenis_nama, t.periode, p.metode,
               p.nominal_bayar, v.name AS verifikator, p.verified_at
        FROM pembayaran p
        JOIN tagihan t ON t.id = p.tagihan_id
        JOIN santri s ON s.id = t.santri_id
        LEFT JOIN kelas k ON k.id = s.kelas_id
        JOIN jenis_pembayaran jp ON jp.id = t.jenis_pembayaran_id
        LEFT JOIN users v ON v.id = p.verified_by
        $where
        ORDER BY p.tanggal_bayar ASC
    ", $params);

    $no = 1;
    $total = 0;
    foreach ($list as $row) {
        $total += (float) $row['nominal_bayar'];
        fputcsv($output, [
            $no++,
            $row['tanggal_bayar'],
            $row['nis'],
            $row['nama_santri'],
            $row['nama_kelas'] ?? '-',
            $row['jenis_nama'],
            $row['periode'] ?? '-',
            strtoupper($row['metode']),
            $row['nominal_bayar'],
            $row['verifikator'] ?? '-',
            $row['verified_at'] ?? '-',
        ]);
    }

    // Baris kosong + total
    fputcsv($output, []);
    fputcsv($output, ['', '', '', '', '', '', '', 'TOTAL', $total, '', '']);
}

// ============================================
// LAPORAN TUNGGAKAN
// ============================================
elseif ($type === 'tunggakan') {

    fputcsv($output, [
        'No', 'NIS', 'Nama Santri', 'Kelas',
        'Jumlah Tagihan', 'Total Tunggakan', 'Jatuh Tempo Terlama'
    ]);

    $where = "WHERE t.status='belum_lunas' AND s.status='aktif'";
    $params = [];
    if ($kelasId) { $where .= " AND s.kelas_id = ?"; $params[] = $kelasId; }

    $list = fetchAll("
        SELECT s.nis, s.nama, k.nama_kelas,
               COUNT(t.id) AS jml_tagihan,
               SUM(t.nominal) AS total_tunggakan,
               MIN(t.jatuh_tempo) AS jatuh_tempo
        FROM santri s
        LEFT JOIN kelas k ON k.id = s.kelas_id
        JOIN tagihan t ON t.santri_id = s.id
        $where
        GROUP BY s.id, s.nis, s.nama, k.nama_kelas
        ORDER BY total_tunggakan DESC
    ", $params);

    $no = 1;
    $total = 0;
    foreach ($list as $row) {
        $total += (float) $row['total_tunggakan'];
        fputcsv($output, [
            $no++,
            $row['nis'],
            $row['nama'],
            $row['nama_kelas'] ?? '-',
            $row['jml_tagihan'],
            $row['total_tunggakan'],
            $row['jatuh_tempo'] ?? '-',
        ]);
    }

    fputcsv($output, []);
    fputcsv($output, ['', '', '', '', 'TOTAL', $total, '']);
}

fclose($output);
exit;