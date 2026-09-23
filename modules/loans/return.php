<?php
// modules/loans/return.php

ob_start();

require_once __DIR__ . '/../../config/functions.php';

$id = $_GET['id'] ?? 0;
if ($id <= 0) {
    $_SESSION['error'] = 'ID peminjaman tidak valid!';
    redirect('index.php?url=loans');
}

$loan = fetchOne("SELECT * FROM loans WHERE id = ?", [$id]);

if (!$loan) {
    $_SESSION['error'] = 'Data peminjaman tidak ditemukan!';
    redirect('index.php?url=loans');
}

if ($loan['status'] == 'dikembalikan') {
    $_SESSION['error'] = 'Semua barang sudah dikembalikan!';
    redirect('index.php?url=loans');
}

// Ambil detail barang yang masih dipinjam
$details = fetchAll(
    "SELECT ld.*, 
            i.name as item_name, 
            i.code as item_code, 
            i.quantity as current_stock,
            i.photo
     FROM loan_details ld
     LEFT JOIN items i ON ld.item_id = i.id
     WHERE ld.loan_id = ? AND ld.status = 'dipinjam'",
    [$id]
);

if (empty($details)) {
    $_SESSION['error'] = 'Tidak ada barang yang perlu dikembalikan!';
    redirect('index.php?url=loans');
}

$error = $_SESSION['error'] ?? null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);

// Proses pengembalian per barang
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $return_date = $_POST['return_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    $return_items = $_POST['return_items'] ?? [];
    
    // Filter hanya yang dipilih
    $selected_items = [];
    foreach ($return_items as $detail_id => $item) {
        if (isset($item['selected']) && $item['selected'] == '1') {
            $selected_items[$detail_id] = $item;
        }
    }
    
    if (empty($selected_items)) {
        $_SESSION['error'] = 'Pilih minimal satu barang untuk dikembalikan!';
        redirect('index.php?url=loans/return&id=' . $id);
    }
    
    // ============================================
    // PROSES TRANSAKSI
    // ============================================
    try {
        $pdo = getDbConnection();
        $pdo->beginTransaction();
        
        $return_code = generateReturnCode();
        
        // Pastikan kode unik
        $check = fetchOne("SELECT id FROM returns WHERE code = ?", [$return_code]);
        if ($check) {
            $return_code = generateReturnCode();
        }
        
        $total_items = count($selected_items);
        
        // Insert ke returns
        $return_id = insert('returns', [
            'code' => $return_code,
            'loan_id' => $id,
            'return_date' => $return_date,
            'total_items' => $total_items,
            'received_by' => currentUserId(),
            'notes' => $notes
        ]);
        
        if (!$return_id) {
            throw new Exception('Gagal menyimpan data pengembalian!');
        }
        
        // Proses setiap barang
        foreach ($selected_items as $detail_id => $item) {
            $condition = $item['condition'] ?? 'baik';
            $quantity = (int)($item['quantity'] ?? 1);
            
            $detail = fetchOne("SELECT * FROM loan_details WHERE id = ?", [$detail_id]);
            if (!$detail) continue;
            
            // Insert return_details
            $detail_id_return = insert('return_details', [
                'return_id' => $return_id,
                'loan_detail_id' => $detail_id,
                'item_id' => $detail['item_id'],
                'quantity' => $quantity,
                'condition' => $condition
            ]);
            
            if (!$detail_id_return) {
                throw new Exception('Gagal menyimpan detail pengembalian!');
            }
            
            // Update stok
            $current_stock = fetchColumn("SELECT quantity FROM items WHERE id = ?", [$detail['item_id']]);
            $new_stock = $current_stock + $quantity;
            
            updateData('items', [
                'quantity' => $new_stock,
                'status' => 'tersedia'
            ], 'id', $detail['item_id']);
            
            // Update loan detail
            $returned_quantity = $detail['returned_quantity'] + $quantity;
            
            if ($returned_quantity >= $detail['quantity']) {
                updateData('loan_details', [
                    'status' => 'dikembalikan',
                    'returned_quantity' => $returned_quantity,
                    'condition_after' => $condition
                ], 'id', $detail_id);
            } else {
                updateData('loan_details', [
                    'returned_quantity' => $returned_quantity,
                    'condition_after' => $condition,
                    'status' => 'dipinjam'
                ], 'id', $detail_id);
            }
        }
        
        // Cek apakah semua barang sudah dikembalikan
        $remaining = fetchColumn(
            "SELECT COUNT(*) FROM loan_details 
             WHERE loan_id = ? AND status = 'dipinjam'",
            [$id]
        );
        
        if ($remaining == 0) {
            updateData('loans', [
                'status' => 'dikembalikan',
                'actual_return_date' => $return_date
            ], 'id', $id);
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = 'Pengembalian ' . $total_items . ' barang berhasil! Kode: ' . $return_code;
        redirect('index.php?url=loans/detail&id=' . $id);
        
    } catch (Exception $e) {
        try {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Exception $e2) {
            // Ignore
        }
        
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        redirect('index.php?url=loans/return&id=' . $id);
    }
}

ob_end_flush();
?>

<!-- ============================================
STYLE DAN HTML (SAMA SEPERTI SEBELUMNYA)
============================================ -->
<style>
.card-form { border: none; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
.card-form .card-body { padding: 24px 20px; }
.card-form .card-header { background: #fff; border-bottom: 1px solid #eef2f7; padding: 16px 20px; border-radius: 12px 12px 0 0 !important; }

.form-label-custom { font-weight: 600; font-size: 12px; color: #475569; margin-bottom: 4px; display: block; }
.form-control-custom, .form-select-custom {
    border-radius: 8px; border: 1px solid #e2e8f0; padding: 8px 12px; font-size: 13px;
    background: #fafbfc; transition: all 0.2s; width: 100%;
}
.form-control-custom:focus, .form-select-custom:focus {
    border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.1); background: #fff; outline: none;
}

.btn-custom { border-radius: 8px; padding: 8px 20px; font-size: 13px; font-weight: 500; }
.btn-custom-success { background: #22c55e; color: #fff; border: none; }
.btn-custom-success:hover { background: #16a34a; color: #fff; }
.btn-custom-secondary { background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
.btn-custom-secondary:hover { background: #e2e8f0; color: #1e293b; }

.alert-custom { border-radius: 10px; border: none; padding: 12px 18px; font-size: 13px; border-left: 4px solid transparent; }
.alert-custom.alert-success { background: #dcfce7 !important; color: #166534 !important; border-left-color: #22c55e !important; }
.alert-custom.alert-danger { background: #fee2e2 !important; color: #991b1b !important; border-left-color: #dc2626 !important; }

.badge-code {
    background: #1e293b !important;
    color: #ffffff !important;
    font-size: 12px;
    padding: 4px 12px;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-weight: 600;
    letter-spacing: 0.5px;
    display: inline-block;
}

.table-return { font-size: 13px; }
.table-return thead th { background: #f8fafc; color: #475569; font-size: 11px; text-transform: uppercase; padding: 10px 14px; border-bottom: 2px solid #eef2f7; }
.table-return tbody td { padding: 10px 14px; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
</style>

<div class="container-fluid px-4">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 fw-bold text-dark">
                <i class="fas fa-undo-alt text-success me-2"></i>Pengembalian Barang
            </h4>
            <p class="text-muted small mt-1">
                Peminjaman: <span class="badge-code"><?= htmlspecialchars($loan['code']) ?></span>
            </p>
            <p class="text-muted small">
                <i class="fas fa-info-circle"></i> Pilih barang yang akan dikembalikan
            </p>
        </div>
        <a href="index.php?url=loans/detail&id=<?= $id ?>" class="btn btn-custom btn-custom-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    <!-- Alert -->
    <?php if ($success): ?>
    <div class="alert alert-custom alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle me-2"></i> <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-custom alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Form -->
    <div class="card card-form">
        <div class="card-body">
            <form method="POST" id="returnForm">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">Tanggal Kembali</label>
                            <input type="date" name="return_date" class="form-control-custom" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label-custom">Catatan</label>
                            <input type="text" name="notes" class="form-control-custom" placeholder="Catatan pengembalian...">
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                    <h6 class="fw-bold mb-0"><i class="fas fa-list text-primary me-2"></i>Daftar Barang yang Dipinjam</h6>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                            <i class="fas fa-check-double"></i> Pilih Semua
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
                            <i class="fas fa-times"></i> Batal Pilih
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-return">
                        <thead>
                            <tr>
                                <th width="50"><input type="checkbox" id="checkAll" onchange="toggleAll(this)"></th>
                                <th>Foto</th>
                                <th>Kode</th>
                                <th>Nama Barang</th>
                                <th>Jumlah Dipinjam</th>
                                <th>Jumlah Dikembalikan</th>
                                <th>Sisa</th>
                                <th>Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($details as $d): 
                                $sisa = $d['quantity'] - $d['returned_quantity'];
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="item-checkbox" 
                                           name="return_items[<?= $d['id'] ?>][selected]" 
                                           value="1"
                                           <?= $sisa > 0 ? '' : 'disabled' ?>>
                                </td>
                                <td>
                                    <?php if ($d['photo']): ?>
                                    <img src="<?= $d['photo'] ?>" class="thumbnail-sm">
                                    <?php else: ?>
                                    <div class="thumbnail-sm d-flex align-items-center justify-content-center bg-light">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($d['item_code']) ?></td>
                                <td><?= htmlspecialchars($d['item_name']) ?></td>
                                <td class="text-center"><?= $d['quantity'] ?></td>
                                <td class="text-center"><?= $d['returned_quantity'] ?></td>
                                <td class="text-center">
                                    <span class="badge <?= $sisa > 0 ? 'bg-warning text-dark' : 'bg-success' ?>">
                                        <?= $sisa ?>
                                    </span>
                                </td>
                                <td>
                                    <select name="return_items[<?= $d['id'] ?>][condition]" 
                                            class="form-select-custom" style="width:130px;">
                                        <option value="baik">Baik</option>
                                        <option value="rusak">Rusak</option>
                                        <option value="perbaikan">Perbaikan</option>
                                    </select>
                                    <input type="hidden" name="return_items[<?= $d['id'] ?>][quantity]" value="<?= $sisa ?>">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Catatan:</strong> Centang barang yang akan dikembalikan. Barang yang sudah dikembalikan semua (sisa 0) tidak bisa dipilih lagi.
                </div>

                <button type="submit" class="btn btn-custom btn-custom-success" id="submitBtn">
                    <i class="fas fa-check me-1"></i> Kembalikan Barang Terpilih
                </button>
                <a href="index.php?url=loans/detail&id=<?= $id ?>" class="btn btn-custom btn-custom-secondary">Batal</a>
            </form>
        </div>
    </div>
</div>

<script>
function toggleAll(master) {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => {
        if (!cb.disabled) {
            cb.checked = master.checked;
        }
    });
}

function selectAll() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => {
        if (!cb.disabled) {
            cb.checked = true;
        }
    });
    document.getElementById('checkAll').checked = true;
}

function deselectAll() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('checkAll').checked = false;
}

document.getElementById('returnForm').addEventListener('submit', function(e) {
    const checked = document.querySelectorAll('.item-checkbox:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert('Pilih minimal satu barang untuk dikembalikan!');
        return false;
    }
    
    const names = [];
    checked.forEach(cb => {
        const row = cb.closest('tr');
        const name = row.querySelector('td:nth-child(4)').textContent.trim();
        names.push(name);
    });
    
    // Disable tombol
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Memproses...';
    
    return confirm('Yakin ingin mengembalikan barang berikut?\n\n' + names.join('\n'));
});
</script>