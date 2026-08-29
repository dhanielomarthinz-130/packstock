<?php
// maintenance.php - Maintenance Mode Page
require_once __DIR__ . '/includes/auth.php';

// If maintenance mode is not active, redirect back to index
if (!Auth::isMaintenanceMode()) {
    header("Location: ./");
    exit;
}

$pageTitle = "Pemeliharaan Sistem - PackStock WMS";
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  
  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Google Material Symbols Outlined -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
  
  <style>
    body { font-family: 'Inter', sans-serif; }
  </style>
</head>
<body class="bg-slate-950 text-slate-300 min-h-screen flex items-center justify-center p-4 relative overflow-hidden font-sans select-none">
  
  <!-- Ambient Glow Effects -->
  <div class="absolute -top-40 -left-40 w-[500px] h-[500px] bg-rose-500/10 rounded-full blur-[100px] pointer-events-none animate-pulse"></div>
  <div class="absolute -bottom-40 -right-40 w-[500px] h-[500px] bg-amber-500/10 rounded-full blur-[100px] pointer-events-none animate-pulse"></div>
  
  <!-- Background Grid Pattern -->
  <div class="absolute inset-0 bg-[linear-gradient(to_right,#33415510_1px,transparent_1px),linear-gradient(to_bottom,#33415510_1px,transparent_1px)] bg-[size:32px_32px] pointer-events-none"></div>

  <!-- Main Card -->
  <div class="w-full max-w-md bg-slate-900/90 backdrop-blur-2xl border border-slate-800 rounded-[28px] p-6 sm:p-8 shadow-2xl text-center space-y-6 relative z-10">
    
    <!-- Pulse Locked Icon -->
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-[24px] bg-rose-500/10 border border-rose-500/30 text-rose-500 shadow-lg shadow-rose-950/20 relative">
      <span class="material-symbols-outlined text-[42px] animate-pulse">construction</span>
    </div>

    <!-- Message Headers -->
    <div class="space-y-2">
      <h1 class="text-xl font-black text-white tracking-tight">Sistem dalam Pemeliharaan</h1>
      <p class="text-xs text-rose-300 font-semibold tracking-wider uppercase">Maintenance Mode Aktif</p>
    </div>

    <!-- Divider -->
    <div class="border-t border-slate-800/80"></div>

    <!-- Description -->
    <p class="text-xs text-slate-400 leading-relaxed">
      Sistem manajemen persediaan <b class="text-white">PackStock</b> saat ini sedang dalam pemeliharaan berkala untuk pembaruan fitur dan optimasi database. 
      Situs saat ini dikunci untuk keamanan data.
    </p>

    <!-- Super Admin Action Info -->
    <div class="p-3 bg-slate-950/60 border border-slate-800 rounded-xl text-left space-y-1.5">
      <div class="flex items-center gap-2 text-amber-400">
        <span class="material-symbols-outlined text-[16px]">info</span>
        <span class="text-[10px] font-bold uppercase tracking-wider">Akses Khusus</span>
      </div>
      <p class="text-[11px] text-slate-400 leading-relaxed">
        Hanya pengguna dengan peran <b class="text-white">Teknisi</b> yang dapat melewati halaman pemeliharaan ini untuk mengoperasikan sistem.
      </p>
    </div>

    <!-- Footer Actions -->
    <div class="space-y-3 pt-2">
      <?php if (Auth::isSuperAdmin()): ?>
        <a href="admin/" class="w-full py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-lg transition-all flex items-center justify-center gap-2 cursor-pointer">
          <span class="material-symbols-outlined text-[16px]">dashboard</span>
          <span>Masuk ke Dashboard</span>
        </a>
      <?php else: ?>
        <a href="login" class="w-full py-3 px-4 bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs rounded-xl border border-slate-700 transition-all flex items-center justify-center gap-2 cursor-pointer">
          <span class="material-symbols-outlined text-[16px]">lock_open</span>
          <span>Login Sebagai Teknisi</span>
        </a>
      <?php endif; ?>
    </div>

    <!-- Small footer info -->
    <p class="text-[10px] text-slate-600 font-medium">PackStock WMS Enterprise &copy; <?= date('Y') ?></p>

  </div>

</body>
</html>
