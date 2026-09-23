<?php
// config/functions.php

require_once __DIR__ . '/database.php';

// ============================================
// SESSION & AUTH
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function currentRole(): string
{
    return $_SESSION['user']['role'] ?? '';
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function hasRole(string|array $roles): bool
{
    $roles = (array) $roles;
    return in_array(currentRole(), $roles, true);
}

/**
 * Redirect yang aman — kalau header sudah terkirim,
 * fallback ke JavaScript.
 */
function redirect(string $path): void
{
    $url = BASE_URL . '/' . ltrim($path, '/');

    // Buang semua output buffer supaya header bisa dikirim
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header("Location: $url");
        exit;
    }

    // Fallback JS kalau header sudah terlanjur dikirim
    echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES) . '";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES) . '"></noscript>';
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][$type] = $message;
}

function getFlash(string $type): ?string
{
    $msg = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $msg;
}

// ============================================
// QUERY HELPERS
// ============================================
function fetchAll(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function fetchOne(string $sql, array $params = []): ?array
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function fetchColumn(string $sql, array $params = [])
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function execute(string $sql, array $params = []): int
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function insert(string $table, array $data): int
{
    $cols = implode(',', array_keys($data));
    $plch = implode(',', array_fill(0, count($data), '?'));
    execute("INSERT INTO {$table} ({$cols}) VALUES ({$plch})", array_values($data));
    return (int) db()->lastInsertId();
}

function update(string $table, array $data, string $where, array $whereParams = []): int
{
    $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
    $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
    return execute($sql, array_merge(array_values($data), $whereParams));
}

// ============================================
// SETTINGS
// ============================================
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (fetchAll("SELECT setting_key, setting_val FROM settings") as $row) {
            $cache[$row['setting_key']] = $row['setting_val'];
        }
    }
    return $cache[$key] ?? $default;
}

function isMaintenanceMode(): bool
{
    return setting('maintenance_mode', '0') === '1';
}

// ============================================
// UTILITIES
// ============================================
function e($str): string
{
    return htmlspecialchars((string) $str, ENT_QUOTES, 'UTF-8');
}

function rupiah($num): string
{
    return 'Rp ' . number_format((float) $num, 0, ',', '.');
}

function tanggalIndo(string $date): string
{
    if (!$date) return '-';
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Ags','Sep','Okt','Nov','Des'];
    $ts = strtotime($date);
    return date('d', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

function periodeSekarang(): string
{
    return date('Y-m');
}

function generateCSRF(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function checkCSRF(?string $token): void
{
    if (!$token || $token !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(419);
        exit('CSRF token tidak valid.');
    }
}

function uploadFile(array $file, string $folder, array $allowed = ['jpg','jpeg','png','pdf']): ?string
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null; // max 5 MB

    $dir = __DIR__ . '/../uploads/' . trim($folder, '/');
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = uniqid('up_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) return null;

    return 'uploads/' . trim($folder, '/') . '/' . $filename;
}

// ============================================
// AKSES DATA WALI — ambil anak dari user login
// ============================================
/**
 * Ambil daftar anak untuk wali yang login.
 *
 * Karena schema final: users → orang_tua.user_id → wali_santri → santri
 */
function getAnakByWali(int $userId): array
{
    return fetchAll("
        SELECT s.*, k.nama_kelas,
               ot.tipe AS hubungan_tipe
        FROM orang_tua ot
        JOIN wali_santri ws ON ws.orang_tua_id = ot.id
        JOIN santri s ON s.id = ws.santri_id
        LEFT JOIN kelas k ON k.id = s.kelas_id
        WHERE ot.user_id = ?
        ORDER BY s.nama
    ", [$userId]);
}

/**
 * Cek apakah santri tertentu adalah anak dari user wali yang login.
 */
function isAnakDariWali(int $santriId, int $userId): bool
{
    return (bool) fetchOne("
        SELECT 1
        FROM orang_tua ot
        JOIN wali_santri ws ON ws.orang_tua_id = ot.id
        WHERE ws.santri_id = ? AND ot.user_id = ?
        LIMIT 1
    ", [$santriId, $userId]);
}