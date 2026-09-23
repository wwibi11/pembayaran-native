<?php
require_once __DIR__ . '/../config/functions.php';

session_destroy();
session_start();
setFlash('success', 'Anda berhasil logout.');
redirect('auth/login.php');