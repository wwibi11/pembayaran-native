<?php
// modules/reports/export_pdf.php

require_once __DIR__ . '/../../config/functions.php';

// Redirect jika bukan admin/staff
if (!isAdmin() && !isStaff()) {
    $_SESSION['error'] = 'Akses ditolak! Anda tidak memiliki izin.';
    redirect('index.php?url=dashboard');
}

// ============================================
// LOAD DOMPDF VIA COMPOSER
// ============================================
require_once __DIR__ . '/../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Ambil data
$items = fetchAll("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id ORDER BY i.created_at DESC");
$loans = fetchAll(
    "SELECT l.*, b.name as borrower_name, b.institution 
     FROM loans l 
     LEFT JOIN borrowers b ON l.borrower_id = b.id 
     ORDER BY l.created_at DESC"
);

$options = new Options();
$options->set('defaultFont', 'Courier');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

// HTML untuk PDF
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Inventaris</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; font-size: 12px; }
        h1 { color: #1a2634; border-bottom: 2px solid #2563eb; padding-bottom: 10px; }
        h2 { color: #475569; margin-top: 25px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f1f5f9; color: #1e293b; padding: 8px 10px; text-align: left; border: 1px solid #e2e8f0; }
        td { padding: 6px 10px; border: 1px solid #e2e8f0; }
        .text-center { text-align: center; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        .footer { margin-top: 30px; text-align: center; color: #94a3b8; font-size: 10px; border-top: 1px solid #e2e8f0; padding-top: 10px; }
    </style>
</head>
<body>
    <h1>📦 Laporan Inventaris Barang</h1>
    <p>Tanggal: ' . date('d/m/Y H:i') . '</p>
    <p>Total Barang: ' . count($items) . ' | Total Peminjaman: ' . count($loans) . '</p>

    <h2>📋 Daftar Barang</h2>
    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Barang</th>
                <th>Kategori</th>
                <th>Stok</th>
                <th>Kondisi</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';

foreach ($items as $item) {
    $statusColor = $item['status'] == 'tersedia' ? 'success' : ($item['status'] == 'dipinjam' ? 'warning' : 'danger');
    $conditionColor = $item['condition'] == 'baik' ? 'success' : ($item['condition'] == 'rusak' ? 'danger' : 'warning');
    
    $html .= '
            <tr>
                <td>' . htmlspecialchars($item['code']) . '</td>
                <td>' . htmlspecialchars($item['name']) . '</td>
                <td>' . htmlspecialchars($item['category_name'] ?? '-') . '</td>
                <td class="text-center">' . $item['quantity'] . '</td>
                <td><span class="badge badge-' . $conditionColor . '">' . ucfirst($item['condition']) . '</span></td>
                <td><span class="badge badge-' . $statusColor . '">' . ucfirst($item['status']) . '</span></td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <h2>📋 Daftar Peminjaman</h2>
    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Peminjam</th>
                <th>Instansi</th>
                <th>Tanggal Pinjam</th>
                <th>Tanggal Kembali</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>';

foreach ($loans as $loan) {
    $statusColor = $loan['status'] == 'dikembalikan' ? 'success' : ($loan['status'] == 'terlambat' ? 'danger' : 'warning');
    
    $html .= '
            <tr>
                <td>' . htmlspecialchars($loan['code']) . '</td>
                <td>' . htmlspecialchars($loan['borrower_name'] ?? '-') . '</td>
                <td>' . htmlspecialchars($loan['institution'] ?? '-') . '</td>
                <td>' . formatDate($loan['loan_date']) . '</td>
                <td>' . formatDate($loan['expected_return_date']) . '</td>
                <td><span class="badge badge-' . $statusColor . '">' . ucfirst($loan['status']) . '</span></td>
            </tr>';
}

$html .= '
        </tbody>
    </table>

    <div class="footer">
        Sistem Inventaris Barang &copy; ' . date('Y') . ' | Laporan dibuat otomatis
    </div>
</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

// Output PDF
$dompdf->stream('laporan_inventaris_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
exit;