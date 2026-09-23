<?php
require_once __DIR__ . '/../config/functions.php';

checkCSRF($_POST['csrf'] ?? null);

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    setFlash('error', 'Email dan password wajib diisi!');
    redirect('auth/login.php');
}

$user = fetchOne("SELECT * FROM users WHERE email = ?", [$email]);

if (!$user || !password_verify($password, $user['password'])) {
    setFlash('error', 'Email atau password salah!');
    redirect('auth/login.php');
}

if (!$user['is_active']) {
    setFlash('error', 'Akun Anda tidak aktif. Hubungi admin.');
    redirect('auth/login.php');
}

// Simpan session
$_SESSION['user'] = [
    'id'    => $user['id'],
    'name'  => $user['name'],
    'email' => $user['email'],
    'role'  => $user['role'],
];

setFlash('success', 'Selamat datang, ' . $user['name'] . '!');
redirect('dashboard');