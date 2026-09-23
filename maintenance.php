<?php
// maintenance.php
// Halaman yang tampil saat maintenance mode aktif

$app_name = getAppName();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance - <?= $app_name ?></title>
    <link href="vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .maintenance-container {
            text-align: center;
            padding: 40px;
            max-width: 600px;
        }
        .maintenance-icon {
            width: 120px;
            height: 120px;
            background: #fef3c7;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        .maintenance-icon i {
            font-size: 48px;
            color: #f59e0b;
        }
        .maintenance-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a2634;
            margin-bottom: 12px;
        }
        .maintenance-subtitle {
            font-size: 16px;
            color: #6b7280;
            margin-bottom: 8px;
        }
        .maintenance-message {
            font-size: 14px;
            color: #8a94a6;
            margin-bottom: 24px;
        }
        .maintenance-progress {
            max-width: 300px;
            margin: 0 auto 20px;
        }
        .maintenance-progress .progress {
            height: 8px;
            border-radius: 4px;
        }
        .maintenance-progress .progress-bar {
            width: 70%;
            animation: progress 2s ease-in-out infinite;
        }
        @keyframes progress {
            0% { width: 30%; }
            50% { width: 70%; }
            100% { width: 30%; }
        }
        .maintenance-footer {
            color: #8a94a6;
            font-size: 12px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #edf2f7;
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <div class="maintenance-icon">
            <i class="fas fa-tools"></i>
        </div>
        <h1 class="maintenance-title"><?= $app_name ?></h1>
        <h2 class="maintenance-subtitle">
            <i class="fas fa-cog fa-spin me-2"></i>
            Sedang Dalam Pemeliharaan
        </h2>
        <p class="maintenance-message">
            Kami sedang melakukan perbaikan dan peningkatan sistem.<br>
            Mohon tunggu beberapa saat. Terima kasih atas pengertiannya.
        </p>
        <div class="maintenance-progress">
            <div class="progress">
                <div class="progress-bar bg-warning" role="progressbar"></div>
            </div>
        </div>
        <p class="small text-muted">
            <i class="fas fa-clock me-1"></i>
            Estimasi selesai: beberapa menit lagi
        </p>
        <div class="maintenance-footer">
            <i class="fas fa-boxes me-1"></i>
            <?= $app_name ?> &copy; <?= date('Y') ?>
        </div>
    </div>
</body>
</html>