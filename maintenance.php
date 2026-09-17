<?php
// maintenance.php - Maintenance Mode Page (Enterprise Fail-Safe Standalone Design)
require_once __DIR__ . '/includes/auth.php';

// If maintenance mode is not active, redirect back to index
if (!Auth::isMaintenanceMode()) {
    header("Location: ./");
    exit;
}

$pageTitle = "Pemeliharaan Sistem - PackStock WMS";
$baseUrl = Auth::getBaseUrl();
$favIconUrl = (!empty($baseUrl) ? rtrim($baseUrl, '/') : '') . '/assets/img/favicon.svg';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <link rel="icon" type="image/svg+xml" href="<?= $favIconUrl ?>?v=2">
  <meta name="theme-color" content="#0f172a">
  
  <!-- Local Stylesheets with Cache-Buster -->
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/tailwind.css?v=<?= time() ?>">

  <!-- Google Fonts: Inter (dengan fallback lokal) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

  <!-- Embedded Standalone Fail-Safe Styles (Bekerja 100% Offline Tanpa CDN) -->
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: #0b0f19;
      color: #cbd5e1;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow-x: hidden;
      line-height: 1.5;
    }

    /* Ambient Glowing Lights */
    .ambient-glow-1 {
      position: absolute;
      top: -10%;
      left: -10%;
      width: 480px;
      height: 480px;
      background: radial-gradient(circle, rgba(239, 68, 68, 0.15) 0%, rgba(239, 68, 68, 0) 70%);
      border-radius: 50%;
      pointer-events: none;
      filter: blur(60px);
    }
    .ambient-glow-2 {
      position: absolute;
      bottom: -10%;
      right: -10%;
      width: 480px;
      height: 480px;
      background: radial-gradient(circle, rgba(245, 158, 11, 0.15) 0%, rgba(245, 158, 11, 0) 70%);
      border-radius: 50%;
      pointer-events: none;
      filter: blur(60px);
    }

    /* Background Grid Pattern */
    .bg-grid-overlay {
      position: absolute;
      inset: 0;
      background-image: 
        linear-gradient(to right, rgba(51, 65, 85, 0.15) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(51, 65, 85, 0.15) 1px, transparent 1px);
      background-size: 32px 32px;
      pointer-events: none;
    }

    /* Main Container Card */
    .maintenance-card {
      position: relative;
      z-index: 10;
      width: 100%;
      max-width: 460px;
      background: rgba(15, 23, 42, 0.88);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(51, 65, 85, 0.6);
      border-radius: 28px;
      padding: 36px 32px;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(255, 255, 255, 0.05);
      text-align: center;
    }

    /* Icon Badge with Pulse Animation */
    .icon-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 80px;
      height: 80px;
      border-radius: 24px;
      background: linear-gradient(135deg, rgba(244, 63, 94, 0.18) 0%, rgba(239, 68, 68, 0.08) 100%);
      border: 1.5px solid rgba(244, 63, 94, 0.35);
      color: #f43f5e;
      box-shadow: 0 10px 25px -5px rgba(244, 63, 94, 0.3);
      margin-bottom: 24px;
      animation: pulse-glow 3s infinite ease-in-out;
    }

    @keyframes pulse-glow {
      0%, 100% {
        box-shadow: 0 0 20px rgba(244, 63, 94, 0.25);
        transform: scale(1);
      }
      50% {
        box-shadow: 0 0 35px rgba(244, 63, 94, 0.45);
        transform: scale(1.04);
      }
    }

    .icon-badge svg {
      width: 40px;
      height: 40px;
      fill: currentColor;
    }

    /* Status Pill */
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 9999px;
      background: rgba(244, 63, 94, 0.12);
      border: 1px solid rgba(244, 63, 94, 0.3);
      color: #fda4af;
      font-size: 11px;
      font-weight: 800;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      margin-bottom: 12px;
    }
    .status-pill-dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background-color: #f43f5e;
      animation: blink 1.5s infinite;
    }

    @keyframes blink {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.3; }
    }

    h1 {
      font-size: 22px;
      font-weight: 900;
      color: #ffffff;
      letter-spacing: -0.02em;
      margin-bottom: 14px;
    }

    .desc-text {
      font-size: 13px;
      color: #94a3b8;
      line-height: 1.6;
      margin-bottom: 24px;
    }
    .desc-text b {
      color: #f8fafc;
      font-weight: 700;
    }

    /* Notice Box */
    .notice-box {
      background: rgba(2, 6, 23, 0.7);
      border: 1px solid rgba(51, 65, 85, 0.5);
      border-radius: 16px;
      padding: 14px 16px;
      text-align: left;
      margin-bottom: 24px;
    }
    .notice-header {
      display: flex;
      align-items: center;
      gap: 6px;
      color: #fbbf24;
      font-size: 11px;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 6px;
    }
    .notice-header svg {
      width: 15px;
      height: 15px;
      fill: currentColor;
    }
    .notice-body {
      font-size: 12px;
      color: #94a3b8;
      line-height: 1.5;
    }
    .notice-body b {
      color: #e2e8f0;
    }

    /* Action Button */
    .btn-action {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 14px 20px;
      border-radius: 14px;
      font-size: 13px;
      font-weight: 700;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.2s ease;
      border: none;
      user-select: none;
    }

    .btn-primary {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: #ffffff;
      box-shadow: 0 4px 15px rgba(16, 185, 129, 0.35);
    }
    .btn-primary:hover {
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
      transform: translateY(-1px);
    }

    .btn-secondary {
      background: rgba(30, 41, 59, 0.9);
      color: #f8fafc;
      border: 1px solid rgba(71, 85, 105, 0.6);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    .btn-secondary:hover {
      background: rgba(51, 65, 85, 0.9);
      border-color: rgba(100, 116, 139, 0.8);
      transform: translateY(-1px);
    }
    .btn-action svg {
      width: 18px;
      height: 18px;
      fill: currentColor;
    }

    /* Footer */
    .footer-text {
      margin-top: 24px;
      font-size: 11px;
      color: #475569;
      font-weight: 500;
    }
  </style>
</head>
<body>
  
  <!-- Ambient Glow Effects -->
  <div class="ambient-glow-1"></div>
  <div class="ambient-glow-2"></div>
  
  <!-- Background Grid Pattern -->
  <div class="bg-grid-overlay"></div>

  <!-- Main Maintenance Card -->
  <div class="maintenance-card">
    
    <!-- Pulse Locked Icon (Inline SVG Bebas Ketergantungan CDN) -->
    <div class="icon-badge">
      <svg viewBox="0 0 24 24">
        <path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/>
      </svg>
    </div>

    <!-- Status Pill -->
    <div>
      <div class="status-pill">
        <span class="status-pill-dot"></span>
        <span>Maintenance Mode Aktif</span>
      </div>
    </div>

    <!-- Message Headers -->
    <h1>Sistem dalam Pemeliharaan</h1>

    <!-- Description -->
    <p class="desc-text">
      Sistem manajemen persediaan <b>PackStock</b> saat ini sedang dalam pemeliharaan berkala untuk pembaruan fitur dan optimasi database. Situs saat ini dikunci untuk perlindungan integritas data.
    </p>

    <!-- Super Admin Action Info -->
    <div class="notice-box">
      <div class="notice-header">
        <svg viewBox="0 0 24 24">
          <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
        </svg>
        <span>Akses Khusus</span>
      </div>
      <p class="notice-body">
        Hanya pengguna dengan peran <b>Teknisi / Super Admin</b> yang dapat melewati halaman pemeliharaan ini untuk mengoperasikan sistem.
      </p>
    </div>

    <!-- Footer Actions -->
    <div>
      <?php if (Auth::isSuperAdmin()): ?>
        <a href="<?= $baseUrl ?>/admin/" class="btn-action btn-primary">
          <svg viewBox="0 0 24 24">
            <path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
          </svg>
          <span>Masuk ke Dashboard Admin</span>
        </a>
      <?php else: ?>
        <a href="<?= $baseUrl ?>/login" class="btn-action btn-secondary">
          <svg viewBox="0 0 24 24">
            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
          </svg>
          <span>Login Sebagai Teknisi</span>
        </a>
      <?php endif; ?>
    </div>

    <!-- Small footer info -->
    <p class="footer-text">PackStock WMS Enterprise &copy; <?= date('Y') ?></p>

  </div>

</body>
</html>
