<?php
// operator/index.php - Modern Mobile App Launcher Interface for Warehouse Operators
require_once __DIR__ . '/../includes/auth.php';
Auth::requireOperator();

$pageTitle = "Panel Operator - PackStock Mobile App";
$baseUrl = Auth::getBaseUrl();
$user = Auth::user();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mobile-preview-container min-h-screen bg-slate-950 sm:py-6 flex items-center justify-center relative overflow-hidden">
  <!-- Ambient Background Glowing Orbs for Desktop Showcase -->
  <div class="hidden sm:block absolute top-1/4 left-1/4 w-96 h-96 bg-emerald-600/15 rounded-full blur-3xl pointer-events-none"></div>
  <div class="hidden sm:block absolute bottom-1/4 right-1/4 w-96 h-96 bg-teal-600/15 rounded-full blur-3xl pointer-events-none"></div>

  <!-- MOBILE APP WRAPPER (Smartphone Frame on Desktop, Fullscreen on Mobile) -->
  <div class="mobile-app-wrapper bg-slate-100 flex flex-col h-screen sm:h-[880px] w-full sm:max-w-md overflow-hidden sm:rounded-[42px] sm:border-[8px] sm:border-slate-800 shadow-2xl shadow-emerald-950/40 relative font-sans">
    
    <!-- PHONE STATUS BAR (TIME, SIGNAL, APP NAME) -->
    <div class="bg-emerald-900 text-emerald-100 px-5 pt-3 pb-1 flex items-center justify-between text-[11px] font-semibold tracking-wide flex-shrink-0 z-20 select-none border-b border-emerald-800/40">
      <div class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse shadow-xs shadow-emerald-400"></span>
        <span id="liveClock" class="font-mono font-black text-white text-xs">08:00</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="text-[9px] bg-emerald-800/90 text-emerald-200 px-2 py-0.5 rounded-full font-extrabold uppercase tracking-wider border border-emerald-600/30">PackStock Mobile</span>
        <span class="material-symbols-outlined text-[15px]">wifi</span>
        <span class="material-symbols-outlined text-[15px]">battery_full</span>
      </div>
    </div>

    <!-- TOP APP BAR (TOGGLE MENU, OPERATOR PROFILE & QUICK ACTIONS) -->
    <header class="bg-gradient-to-r from-emerald-800 via-emerald-700 to-teal-800 text-white px-3.5 py-3 flex items-center justify-between shadow-md flex-shrink-0 z-10 border-b border-emerald-900/40">
      
      <!-- Left: Toggle Menu Button & Operator Identity -->
      <div class="flex items-center gap-2 min-w-0 flex-1">
        <!-- TOGGLE MENU BUTTON -->
        <button type="button" onclick="toggleOperatorDrawer()" id="btnOpMenuToggle" title="Menu & Pengaturan" 
          class="w-9 h-9 rounded-2xl bg-emerald-900/70 hover:bg-emerald-900 active:scale-90 flex items-center justify-center text-emerald-100 hover:text-white transition-all border border-emerald-500/40 shadow-xs shrink-0 cursor-pointer">
          <span class="material-symbols-outlined text-[22px]">menu</span>
        </button>

        <div onclick="openSettingProfileModal()" title="Lihat Profil & Pengaturan" class="flex items-center gap-2 min-w-0 truncate cursor-pointer group">
          <div class="w-9 h-9 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-200 p-0.5 shadow-md flex-shrink-0 group-hover:scale-105 transition-transform">
            <div class="w-full h-full rounded-[14px] bg-emerald-900 flex items-center justify-center text-emerald-300 font-black">
              <span class="material-symbols-outlined text-[20px]">engineering</span>
            </div>
          </div>
          <div class="min-w-0 truncate">
            <div class="flex items-center gap-1.5">
              <h2 class="font-black text-sm leading-tight text-white tracking-tight truncate group-hover:text-emerald-200 transition-colors"><?= htmlspecialchars($user['name'] ?? 'Operator') ?></h2>
              <span class="w-2 h-2 rounded-full bg-emerald-300 shrink-0"></span>
            </div>
            <p class="text-[10px] text-emerald-100/90 flex items-center gap-1 font-medium truncate mt-0.5">
              <span class="truncate"><?= htmlspecialchars($user['shift'] ?? 'PIC') ?></span>
              <span class="text-emerald-300/70">&bull;</span>
              <span class="text-emerald-200 font-mono text-[9px]">#<?= htmlspecialchars($user['id'] ?? '0') ?></span>
            </p>
          </div>
        </div>
      </div>

      <!-- Right: Quick Actions -->
      <div class="flex items-center gap-1.5 shrink-0 ml-2">
        <button onclick="refreshOperatorData()" title="Sinkronisasi Data" class="w-8 h-8 rounded-xl bg-emerald-900/60 hover:bg-emerald-900 active:scale-90 flex items-center justify-center text-emerald-100 transition-all border border-emerald-600/40 shadow-xs">
          <span id="btnSyncIcon" class="material-symbols-outlined text-[18px]">sync</span>
        </button>

        <?php if (Auth::isAdmin()): ?>
          <a href="../admin/" title="Admin Dashboard" class="w-8 h-8 rounded-xl bg-emerald-900/60 hover:bg-emerald-900 active:scale-90 flex items-center justify-center text-emerald-100 transition-all border border-emerald-600/40 shadow-xs">
            <span class="material-symbols-outlined text-[18px]">desktop_windows</span>
          </a>
        <?php endif; ?>

        <a href="../logout" title="Logout" class="w-8 h-8 rounded-xl bg-emerald-900/60 hover:bg-rose-700 active:scale-90 text-emerald-100 hover:text-white flex items-center justify-center transition-all border border-emerald-600/40 shadow-xs">
          <span class="material-symbols-outlined text-[18px]">logout</span>
        </a>
      </div>
    </header>

    <!-- MAIN SCROLLABLE VIEWPORT -->
    <div id="operatorViewport" class="flex-1 overflow-y-auto p-4 space-y-4 pb-6">

      <!-- ========================================================================= -->
      <!-- 0. SCREEN: HOME LAUNCHER / APP MENU GRID (DEFAULT VIEW) -->
      <!-- ========================================================================= -->
      <div id="op-tab-home" class="space-y-4 animate-fade-in">
        
        <!-- Welcome Hero Banner Card -->
        <div class="bg-gradient-to-br from-emerald-800 via-emerald-700 to-teal-900 text-white rounded-3xl p-4 sm:p-5 shadow-lg border border-emerald-600/30 relative overflow-hidden">
          <!-- Background Ambient Pattern -->
          <div class="absolute -right-6 -bottom-6 w-36 h-36 bg-emerald-400/20 rounded-full blur-2xl pointer-events-none"></div>
          <div class="absolute right-3 top-3 opacity-15 pointer-events-none">
            <span class="material-symbols-outlined text-[76px]">warehouse</span>
          </div>

          <div class="relative z-10 space-y-2">
            <div class="flex items-center justify-between">
              <span id="homeGreetingText" class="text-xs uppercase tracking-wider font-black text-emerald-200 flex items-center gap-1">
                <span>Selamat Bertugas</span>
              </span>
              <span class="px-2.5 py-0.5 bg-emerald-950/70 rounded-full text-[10px] font-extrabold text-emerald-200 border border-emerald-600/40 flex items-center gap-1 shadow-2xs">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Online</span>
              </span>
            </div>
            <h3 class="text-lg font-black tracking-tight leading-snug">
              Halo, <?= htmlspecialchars(explode(' ', $user['name'] ?? 'Operator')[0]) ?>!
            </h3>
            <p class="text-xs text-emerald-100/90 leading-relaxed font-medium">
              Pilih menu di bawah untuk serah terima packaging ke line produksi, stock opname, atau input barang masuk.
            </p>
          </div>
        </div>

        <!-- Quick KPI Summary Strip -->
        <div class="grid grid-cols-3 gap-2.5">
          <div onclick="switchOpTab('tasks')" class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-xs hover:border-amber-400 cursor-pointer active:scale-95 transition-all text-center space-y-0.5 group">
            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block group-hover:text-amber-700">Tugas Picking</span>
            <span id="homeStatTasks" class="font-mono font-black text-amber-600 text-lg leading-tight block">0</span>
            <span class="text-[9px] text-slate-500 font-semibold block">Serah Terima</span>
          </div>

          <div onclick="switchOpTab('dynamic_count')" class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-xs hover:border-indigo-400 cursor-pointer active:scale-95 transition-all text-center space-y-0.5 group">
            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block group-hover:text-indigo-700">Dynamic</span>
            <span id="homeStatDynamic" class="font-mono font-black text-indigo-600 text-lg leading-tight block">0</span>
            <span class="text-[9px] text-slate-500 font-semibold block">Task SKU</span>
          </div>

          <div onclick="switchOpTab('opname')" class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-xs hover:border-emerald-400 cursor-pointer active:scale-95 transition-all text-center space-y-0.5 group">
            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block group-hover:text-emerald-700">Opname</span>
            <span id="homeStatOpname" class="font-mono font-black text-emerald-700 text-lg leading-tight block">0</span>
            <span class="text-[9px] text-slate-500 font-semibold block">Blank Count</span>
          </div>
        </div>

        <!-- Section Title: Menu Aplikasi -->
        <div class="flex items-center justify-between px-1 pt-1">
          <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-emerald-700 text-[18px]">grid_view</span>
            <span>Menu Operasional Lapangan</span>
          </h4>
          <span class="text-[11px] text-slate-400 font-bold">6 Modul</span>
        </div>

        <!-- APP LAUNCHER GRID (NATIVE MOBILE APP TILES) -->
        <div class="grid grid-cols-2 gap-3">

          <!-- APP 1: TUGAS PENGAMBILAN PACKAGING -->
          <div onclick="switchOpTab('tasks')" 
            class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-amber-400 active:scale-95 transition-all cursor-pointer flex flex-col justify-between h-32 relative overflow-hidden group">
            <div class="flex items-start justify-between">
              <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 text-white flex items-center justify-center shadow-md shadow-amber-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">assignment</span>
              </div>
              <span id="homeBadgeTasks" class="hidden px-2 py-0.5 rounded-full bg-rose-500 text-white font-black text-[10px] shadow-xs animate-bounce">
                0 Tugas
              </span>
            </div>
            <div>
              <h5 class="font-black text-slate-900 text-xs tracking-tight leading-snug group-hover:text-amber-700 transition-colors">Tugas Pengambilan</h5>
              <p class="text-[10px] text-slate-500 mt-0.5 line-clamp-1">Serah terima packaging ke line</p>
            </div>
          </div>

          <!-- APP 2: DYNAMIC COUNTING (TASK SKU) -->
          <div onclick="switchOpTab('dynamic_count')" 
            class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-indigo-400 active:scale-95 transition-all cursor-pointer flex flex-col justify-between h-32 relative overflow-hidden group">
            <div class="flex items-start justify-between">
              <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white flex items-center justify-center shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">checklist</span>
              </div>
              <span id="homeBadgeDynamicCount" class="hidden px-2 py-0.5 rounded-full bg-indigo-600 text-white font-black text-[10px] shadow-xs animate-pulse">
                0 Task
              </span>
            </div>
            <div>
              <h5 class="font-black text-slate-900 text-xs tracking-tight leading-snug group-hover:text-indigo-700 transition-colors">Dynamic Counting</h5>
              <p class="text-[10px] text-slate-500 mt-0.5 line-clamp-1">Task SKU & konfirmasi rak</p>
            </div>
          </div>

          <!-- APP 3: STOCK OPNAME (PURE BLANK COUNT) -->
          <div onclick="switchOpTab('opname')" 
            class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-emerald-400 active:scale-95 transition-all cursor-pointer flex flex-col justify-between h-32 relative overflow-hidden group">
            <div class="flex items-start justify-between">
              <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white flex items-center justify-center shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">inventory_2</span>
              </div>
              <span id="homeBadgeOpname" class="hidden px-2 py-0.5 rounded-full bg-emerald-600 text-white font-black text-[10px] shadow-xs animate-pulse">
                Aktif
              </span>
            </div>
            <div>
              <h5 class="font-black text-slate-900 text-xs tracking-tight leading-snug group-hover:text-emerald-700 transition-colors">Stock Opname</h5>
              <p class="text-[10px] text-slate-500 mt-0.5 line-clamp-1">Hitung fisik mandiri & recount</p>
            </div>
          </div>

          <!-- APP 4: PENERIMAAN BARANG MASUK -->
          <div onclick="switchOpTab('inbound')" 
            class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-teal-400 active:scale-95 transition-all cursor-pointer flex flex-col justify-between h-32 relative overflow-hidden group">
            <div class="flex items-start justify-between">
              <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-teal-500 to-emerald-600 text-white flex items-center justify-center shadow-md shadow-teal-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">move_to_inbox</span>
              </div>
              <span class="px-2 py-0.5 rounded-full bg-teal-50 text-teal-800 font-bold text-[10px] border border-teal-200">
                Draft
              </span>
            </div>
            <div>
              <h5 class="font-black text-slate-900 text-xs tracking-tight leading-snug group-hover:text-teal-700 transition-colors">Penerimaan Barang</h5>
              <p class="text-[10px] text-slate-500 mt-0.5 line-clamp-1">Inbound multi-product draft</p>
            </div>
          </div>

          <!-- APP 5: CEK STOK & RAK -->
          <div onclick="switchOpTab('stock')" 
            class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-sky-400 active:scale-95 transition-all cursor-pointer flex flex-col justify-between h-32 relative overflow-hidden group">
            <div class="flex items-start justify-between">
              <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 text-white flex items-center justify-center shadow-md shadow-sky-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">shelves</span>
              </div>
              <span class="px-2 py-0.5 rounded-full bg-sky-50 text-sky-800 font-bold text-[10px] border border-sky-200">
                Pencarian
              </span>
            </div>
            <div>
              <h5 class="font-black text-slate-900 text-xs tracking-tight leading-snug group-hover:text-sky-700 transition-colors">Cek Stok & Rak</h5>
              <p class="text-[10px] text-slate-500 mt-0.5 line-clamp-1">Cari lokasi rak & sisa stok</p>
            </div>
          </div>

          <!-- APP 6: RIWAYAT SELESAI -->
          <div onclick="switchOpTab('history')" 
            class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md hover:border-slate-400 active:scale-95 transition-all cursor-pointer flex flex-col justify-between h-32 relative overflow-hidden group">
            <div class="flex items-start justify-between">
              <div class="w-11 h-11 rounded-2xl bg-gradient-to-br from-slate-600 to-slate-800 text-white flex items-center justify-center shadow-md shadow-slate-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">history</span>
              </div>
              <span id="homeBadgeDone" class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 font-bold text-[10px] border border-slate-200">
                Log
              </span>
            </div>
            <div>
              <h5 class="font-black text-slate-900 text-xs tracking-tight leading-snug group-hover:text-slate-800 transition-colors">Riwayat Selesai</h5>
              <p class="text-[10px] text-slate-500 mt-0.5 line-clamp-1">Catatan serah terima line</p>
            </div>
          </div>

        </div>

        <!-- Quick Urgent Task Alert Banner (If Any Active Tasks) -->
        <div id="homeUrgentBanner" class="hidden bg-amber-50 border-2 border-amber-300 rounded-2xl p-3.5 flex items-center justify-between shadow-xs">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold shadow-xs shrink-0">
              <span class="material-symbols-outlined text-[22px]">notification_important</span>
            </div>
            <div>
              <p class="text-xs font-bold text-amber-950" id="homeUrgentText">Ada tugas siap dikerjakan</p>
              <p class="text-[10px] text-amber-800">Ketuk untuk mulai memproses serah terima</p>
            </div>
          </div>
          <button onclick="switchOpTab('tasks')" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 active:scale-95 text-white font-bold text-xs rounded-xl shadow-xs transition-all shrink-0">
            Buka &rarr;
          </button>
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- 1. SCREEN: TUGAS PENGAMBILAN PACKAGING (PICKING TASK LIST) -->
      <!-- ========================================================================= -->
      <div id="op-tab-tasks" class="hidden space-y-3.5 animate-fade-in">
        
        <!-- Screen Back Bar -->
        <div class="flex items-center justify-between bg-white p-2.5 rounded-2xl border border-slate-200 shadow-xs">
          <button type="button" onclick="switchOpTab('home')" class="flex items-center gap-1 text-slate-700 hover:text-emerald-800 bg-slate-100 hover:bg-emerald-50 px-3 py-1.5 rounded-xl text-xs font-bold transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            <span>Menu Utama</span>
          </button>

          <div class="text-right">
            <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">Tugas Pengambilan</h3>
            <span class="text-[10px] text-emerald-700 font-semibold">Line Production Handover</span>
          </div>
        </div>

        <div id="opTasksContainer" class="space-y-2.5">
          <div class="p-6 bg-white rounded-2xl text-center text-slate-400 text-xs shadow-xs border border-slate-200">
            <span class="material-symbols-outlined text-[20px] animate-spin text-emerald-600 mb-1">progress_activity</span>
            <p>Memuat daftar tugas...</p>
          </div>
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- 2.A. SCREEN: DYNAMIC COUNTING (TASK SKU DRIVEN) -->
      <!-- ========================================================================= -->
      <div id="op-tab-dynamic_count" class="hidden space-y-3.5 animate-fade-in">
        
        <!-- Screen Back Bar -->
        <div class="flex items-center justify-between bg-white p-2.5 rounded-2xl border border-slate-200 shadow-xs">
          <button type="button" onclick="switchOpTab('home')" class="flex items-center gap-1 text-slate-700 hover:text-indigo-800 bg-slate-100 hover:bg-indigo-50 px-3 py-1.5 rounded-xl text-xs font-bold transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            <span>Menu Utama</span>
          </button>

          <div class="text-right">
            <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">Dynamic Counting</h3>
            <span class="text-[10px] text-indigo-700 font-semibold">Tugas Hitung SKU dari Admin</span>
          </div>
        </div>

        <div class="p-3 bg-indigo-50/70 border border-indigo-200 rounded-2xl flex items-center gap-2 text-xs text-indigo-900">
          <span class="material-symbols-outlined text-[20px] text-indigo-700 shrink-0">fact_check</span>
          <p class="text-[11px] leading-tight">Hitung fisik SKU yang ditugaskan di bawah ini dan konfirmasi / scan lokasi rak simpan.</p>
        </div>

        <div id="opDynamicTasksContainer" class="space-y-2.5">
          <div class="p-6 bg-white rounded-2xl text-center text-slate-400 text-xs shadow-xs border border-slate-200">
            <span class="material-symbols-outlined text-[20px] animate-spin text-indigo-600 mb-1">progress_activity</span>
            <p>Memuat tugas Dynamic Count...</p>
          </div>
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- 2.B. SCREEN: STOCK OPNAME (PURE BLANK COUNT & RECOUNT SECTION) -->
      <!-- ========================================================================= -->
      <div id="op-tab-opname" class="hidden space-y-3.5 animate-fade-in">
        
        <!-- Screen Back Bar -->
        <div class="flex items-center justify-between bg-white p-2.5 rounded-2xl border border-slate-200 shadow-xs">
          <button type="button" onclick="switchOpTab('home')" class="flex items-center gap-1 text-slate-700 hover:text-emerald-800 bg-slate-100 hover:bg-emerald-50 px-3 py-1.5 rounded-xl text-xs font-bold transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            <span>Menu Utama</span>
          </button>

          <div class="text-right">
            <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">Stock Opname</h3>
          </div>
        </div>

        <!-- Sub-Tab Segmented Switcher for Opname -->
        <div class="grid grid-cols-2 gap-1.5 p-1 bg-slate-200/80 rounded-2xl border border-slate-200 text-xs font-bold shadow-2xs">
          <button type="button" id="btnSubTabOpname1st" onclick="switchOpnameSubTab('1st')" 
            class="py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 bg-emerald-600 text-white shadow-xs transition-all">
            <span class="material-symbols-outlined text-[17px]">edit_note</span>
            <span>Hitung (1st Count)</span>
          </button>
          <button type="button" id="btnSubTabOpnameRecount" onclick="switchOpnameSubTab('recount')" 
            class="py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900 transition-all relative">
            <span class="material-symbols-outlined text-[17px]">replay</span>
            <span>Tugas Recount</span>
            <span id="subTabRecountBadge" class="hidden px-1.5 py-0.2 rounded-full text-[9px] font-black bg-purple-600 text-white animate-pulse">0</span>
          </button>
        </div>

        <!-- ================= SUBTAB 1: HITUNG FISIK (1ST COUNT) ================= -->
        <div id="opname-subtab-1st" class="space-y-3.5">
          <!-- FORM BLANK COUNT ENTRY -->
          <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <div class="flex items-center gap-1.5">
                <span class="material-symbols-outlined text-emerald-700 text-[18px]">edit_note</span>
                <h4 class="font-black text-slate-900 text-xs uppercase tracking-wider">Form Hitung Fisik Mandiri</h4>
              </div>
              <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 font-bold text-[10px] border border-emerald-200">1st Count</span>
            </div>

            <form id="formBlankCountEntry" onsubmit="handleBlankCountSubmit(event)" class="space-y-3 text-xs">
              <div>
                <label class="block font-bold text-slate-700 mb-1 text-[11px]">Pilih / Cari Material Packaging <span class="text-rose-500">*</span></label>
                <select id="blankMaterialSelect" required onchange="handleBlankMaterialChange()" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold outline-none focus:border-emerald-600 focus:bg-white transition-colors">
                  <option value="">-- Ketik / Pilih Material Packaging --</option>
                </select>
              </div>

              <div class="grid grid-cols-2 gap-2.5">
                <div>
                  <label class="block font-bold text-slate-700 mb-1 text-[11px]">Scan / Lokasi Rak <span class="text-rose-500">*</span></label>
                  <input type="text" id="blankRackLocation" required placeholder="Contoh: Rak A-01" 
                    class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold outline-none focus:border-emerald-600 focus:bg-white">
                </div>

                <div>
                  <label class="block font-bold text-slate-700 mb-1 text-[11px]">Jumlah Fisik (<span id="blankUnitLabel">Pcs</span>) <span class="text-rose-500">*</span></label>
                  <input type="number" id="blankCountQty" required min="0" placeholder="0" 
                    class="w-full p-2.5 bg-emerald-50/60 border-2 border-emerald-500 rounded-xl font-black text-base text-emerald-900 text-center outline-none">
                </div>
              </div>

              <!-- Fast Numeric Stepper Helper for Quick Warehouse Counting -->
              <div class="flex items-center justify-between gap-1 pt-0.5">
                <span class="text-[10px] font-bold text-slate-400">Quick Add:</span>
                <div class="flex items-center gap-1">
                  <button type="button" onclick="adjustNumericInput('blankCountQty', 1)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+1</button>
                  <button type="button" onclick="adjustNumericInput('blankCountQty', 10)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+10</button>
                  <button type="button" onclick="adjustNumericInput('blankCountQty', 50)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+50</button>
                  <button type="button" onclick="adjustNumericInput('blankCountQty', 100)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+100</button>
                  <button type="button" onclick="adjustNumericInput('blankCountQty', 0, true)" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 rounded-lg text-[10px] font-bold text-rose-700 transition-colors">Reset</button>
                </div>
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1 text-[11px]">Catatan Kondisi Fisik (Opsional)</label>
                <input type="text" id="blankNotes" placeholder="Contoh: Kondisi bagus, tersusun rapi..." 
                  class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs outline-none focus:border-emerald-600 focus:bg-white">
              </div>

              <button type="submit" id="btnSubmitBlankCount" 
                class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-black text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[18px]">add_circle</span>
                <span>+ Simpan Hasil Hitung Fisik</span>
              </button>
            </form>
          </div>

          <!-- SECTION: HASIL HITUNGAN SAYA HARI INI -->
          <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-2.5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-700 flex items-center gap-1">
                <span class="material-symbols-outlined text-slate-500 text-[16px]">history</span>
                <span>Hasil Hitungan Saya Hari Ini (<span id="opMyBlankCountBadge">0</span>)</span>
              </h4>
              <button type="button" onclick="loadOperatorBlankCounts()" class="text-[11px] text-emerald-700 font-bold hover:underline flex items-center gap-0.5">
                <span class="material-symbols-outlined text-[14px]">refresh</span>
                <span>Refresh</span>
              </button>
            </div>

            <div id="opBlankCountHistoryContainer" class="space-y-2 max-h-56 overflow-y-auto">
              <div class="p-4 bg-slate-50 border border-dashed border-slate-200 rounded-xl text-center text-xs text-slate-400">
                Belum ada hasil hitung yang disubmit hari ini.
              </div>
            </div>
          </div>
        </div>

        <!-- ================= SUBTAB 2: TUGAS RECOUNT SELISIH ================= -->
        <div id="opname-subtab-recount" class="hidden space-y-3.5">
          <div class="bg-gradient-to-r from-purple-50 to-indigo-50/80 border border-purple-200/80 p-3.5 rounded-2xl flex items-center justify-between gap-3 shadow-2xs">
            <div class="flex items-center gap-2.5 min-w-0">
              <div class="w-9 h-9 shrink-0 rounded-xl bg-purple-600 text-white flex items-center justify-center shadow-xs">
                <span class="material-symbols-outlined text-[20px]">replay</span>
              </div>
              <div class="min-w-0">
                <h4 class="font-black text-purple-950 text-xs uppercase tracking-wider truncate">Tugas Recount Selisih</h4>
                <p class="text-[11px] text-purple-700/90 leading-tight">SKU selisih untuk dihitung ulang</p>
              </div>
            </div>
            <div class="shrink-0">
              <span id="opRecountCountBadge" class="inline-flex items-center justify-center px-3 py-1.5 rounded-xl bg-purple-600 text-white font-extrabold text-xs shadow-xs whitespace-nowrap">
                0 Tugas
              </span>
            </div>
          </div>

          <div id="opRecountTasksContainer" class="space-y-2.5">
            <!-- Rendered dynamically -->
          </div>
        </div>

      </div>

      <!-- ========================================================================= -->
      <!-- 3. SCREEN: INPUT BARANG MASUK (DRAFT MULTI-PRODUCT) -->
      <!-- ========================================================================= -->
      <div id="op-tab-inbound" class="hidden space-y-3.5 animate-fade-in">
        
        <!-- Screen Back Bar -->
        <div class="flex items-center justify-between bg-white p-2.5 rounded-2xl border border-slate-200 shadow-xs">
          <button type="button" onclick="switchOpTab('home')" class="flex items-center gap-1 text-slate-700 hover:text-emerald-800 bg-slate-100 hover:bg-emerald-50 px-3 py-1.5 rounded-xl text-xs font-bold transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            <span>Menu Utama</span>
          </button>

          <div class="text-right">
            <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">Barang Masuk</h3>
            <span class="text-[10px] text-emerald-700 font-semibold">Mode Multi-Product Draft</span>
          </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3.5">
          <!-- Inbound Header Details: Lokasi Rak / Simpan & Catatan -->
          <div class="grid grid-cols-1 gap-2.5 text-xs">
            <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px] flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-emerald-600">grid_view</span>
                <span>Lokasi Rak Simpan (Location)</span>
              </label>
              <input type="text" id="opInboundLocation" placeholder="Lokasi Rak otomatis terisi dari material, atau ketik lokasi baru..." 
                class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold outline-none focus:bg-white focus:border-emerald-600 transition-colors">
            </div>
            <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px] flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-slate-400">notes</span>
                <span>Catatan / Keterangan Penerimaan (Opsional)</span>
              </label>
              <input type="text" id="opInboundNotes" placeholder="Contoh: Penerimaan dari Vendor / Surat Jalan #..." 
                class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold outline-none focus:bg-white focus:border-emerald-600">
            </div>
          </div>

          <!-- Add Product to Draft Section -->
          <div class="p-3.5 bg-emerald-50/70 border border-emerald-200 rounded-2xl space-y-2.5">
            <p class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-900 flex items-center gap-1">
              <span class="material-symbols-outlined text-[16px]">add_circle</span>
              <span>Tambah Packaging ke Keranjang Draft:</span>
            </p>

            <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px]">Pilih Material Packaging <span class="text-rose-500">*</span></label>
              <select id="opInboundMaterialSelect" onchange="updateOpInboundStockBadge()" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs font-bold outline-none focus:border-emerald-600">
                <option value="">-- Pilih Material Packaging --</option>
              </select>
              <div id="opInboundStockBadge" class="text-[10px] text-slate-500 mt-1"></div>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px]">Jumlah Masuk (Qty) <span class="text-rose-500">*</span></label>
              <input type="number" id="opInboundQty" min="1" placeholder="0" 
                class="w-full p-2.5 bg-white border border-slate-300 rounded-xl font-black text-base text-emerald-800 outline-none focus:border-emerald-600 text-center">
            </div>

            <button type="button" onclick="addInboundDraftItem()" 
              class="w-full py-2.5 bg-slate-800 hover:bg-slate-900 active:scale-95 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
              <span class="material-symbols-outlined text-[17px]">add_shopping_cart</span>
              <span>+ Masukkan ke Draft Penerimaan</span>
            </button>
          </div>

          <!-- Draft Items List / Table -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <h4 class="font-bold text-[11px] uppercase tracking-wider text-slate-700">Daftar Packaging dalam Draft (<span id="opDraftCount">0</span>)</h4>
              <button type="button" onclick="clearInboundDraft()" class="text-[10px] text-rose-600 hover:underline font-bold">Bersihkan Draft</button>
            </div>

            <div id="opInboundDraftList" class="space-y-2 max-h-48 overflow-y-auto">
              <div class="p-4 bg-slate-50 border border-dashed border-slate-200 rounded-xl text-center text-xs text-slate-400">
                Draft masih kosong. Pilih material dan klik <b>"+ Masukkan ke Draft"</b> di atas.
              </div>
            </div>

            <div id="opDraftSummaryBox" class="hidden p-3 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center justify-between text-xs font-semibold text-emerald-900">
              <span>Total Qty Masuk:</span>
              <span id="opDraftTotalQty" class="text-sm font-black text-emerald-800">0 Pcs</span>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="pt-2 border-t border-slate-100">
            <button type="button" id="btnSubmitInboundDraft" onclick="handleInboundDraftSubmit()" 
              class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-extrabold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5">
              <span class="material-symbols-outlined text-[18px]">check_circle</span>
              <span>Submit & Update Master Stok</span>
            </button>
          </div>
        </div>
      </div>

      <!-- ========================================================================= -->
      <!-- 4. SCREEN: CEK SISA STOK & LOKASI RAK (SEARCH MODULE) -->
      <!-- ========================================================================= -->
      <div id="op-tab-stock" class="hidden space-y-3.5 animate-fade-in">
        
        <!-- Screen Back Bar -->
        <div class="flex items-center justify-between bg-white p-2.5 rounded-2xl border border-slate-200 shadow-xs">
          <button type="button" onclick="switchOpTab('home')" class="flex items-center gap-1 text-slate-700 hover:text-sky-800 bg-slate-100 hover:bg-sky-50 px-3 py-1.5 rounded-xl text-xs font-bold transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            <span>Menu Utama</span>
          </button>

          <div class="text-right">
            <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">Cek Stok & Rak</h3>
            <span class="text-[10px] text-sky-700 font-semibold">Pencarian Gudang</span>
          </div>
        </div>

        <div class="relative">
          <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 pointer-events-none">
            <span class="material-symbols-outlined text-[18px]">search</span>
          </span>
          <input type="text" id="opStockSearch" oninput="loadOperatorStock()" placeholder="Cari SKU, nama packaging, rak simpan..." 
            class="w-full pl-10 pr-3.5 py-2.5 bg-white border border-slate-300 rounded-2xl text-xs font-semibold text-slate-900 outline-none focus:border-sky-600 focus:ring-2 focus:ring-sky-500/20 shadow-xs transition-all">
        </div>

        <div id="opStockListContainer" class="space-y-2.5"></div>
      </div>

      <!-- ========================================================================= -->
      <!-- 5. SCREEN: RIWAYAT TUGAS SELESAI -->
      <!-- ========================================================================= -->
      <div id="op-tab-history" class="hidden space-y-3.5 animate-fade-in">
        
        <!-- Screen Back Bar -->
        <div class="flex items-center justify-between bg-white p-2.5 rounded-2xl border border-slate-200 shadow-xs">
          <button type="button" onclick="switchOpTab('home')" class="flex items-center gap-1 text-slate-700 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-xl text-xs font-bold transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            <span>Menu Utama</span>
          </button>

          <div class="text-right">
            <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">Riwayat Selesai</h3>
            <span class="text-[10px] text-slate-500 font-semibold">Log Serah Terima</span>
          </div>
        </div>

        <div id="opHistoryContainer" class="space-y-2.5"></div>
      </div>

    </div>

    <!-- ========================================================================= -->
    <!-- SLIDE-OVER OFF-CANVAS DRAWER (SIDEBAR MENU TOGGLE) -->
    <!-- ========================================================================= -->
    <div id="operatorDrawerBackdrop" onclick="closeOperatorDrawer()" class="absolute inset-0 bg-slate-950/70 backdrop-blur-xs z-40 hidden transition-opacity duration-300 opacity-0"></div>

    <div id="operatorDrawer" class="absolute inset-y-0 left-0 w-72 max-w-[80%] bg-slate-900 text-white z-50 shadow-2xl flex flex-col justify-between transform -translate-x-full transition-transform duration-300 ease-in-out border-r border-slate-800">
      
      <!-- Drawer Top: Header & User Card -->
      <div class="p-4 space-y-4">
        
        <!-- Drawer App Brand & Close -->
        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-xl bg-gradient-to-br from-emerald-500 to-teal-700 flex items-center justify-center text-white shadow-xs">
              <span class="material-symbols-outlined text-[20px]">package_2</span>
            </div>
            <div>
              <h3 class="font-black text-xs tracking-tight text-white uppercase">PackStock Mobile</h3>
              <p class="text-[9px] text-emerald-400 font-semibold">Warehouse Operations</p>
            </div>
          </div>
          <button onclick="closeOperatorDrawer()" class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition-colors">
            <span class="material-symbols-outlined text-[18px]">close</span>
          </button>
        </div>

        <!-- Mini Profile Card in Drawer -->
        <div onclick="closeOperatorDrawer(); openSettingProfileModal();" class="p-3 bg-gradient-to-br from-slate-800 to-slate-800/60 rounded-2xl border border-slate-700/80 shadow-xs cursor-pointer hover:border-emerald-500/50 transition-all group">
          <div class="flex items-center gap-2.5">
            <div class="w-10 h-10 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center text-white font-black text-sm shadow-xs flex-shrink-0 group-hover:scale-105 transition-transform">
              <span class="material-symbols-outlined text-[22px]">engineering</span>
            </div>
            <div class="min-w-0 truncate">
              <h4 class="font-black text-xs text-white leading-tight truncate group-hover:text-emerald-300 transition-colors"><?= htmlspecialchars($user['name'] ?? 'Operator') ?></h4>
              <p class="text-[10px] text-slate-400 font-mono mt-0.5">@<?= htmlspecialchars($user['username'] ?? 'user') ?></p>
              <span class="inline-block px-2 py-0.2 mt-1 rounded-md text-[9px] font-black uppercase bg-emerald-950 text-emerald-300 border border-emerald-600/40">
                <?= htmlspecialchars(strtoupper($user['role'] ?? 'OPERATOR')) ?>
              </span>
            </div>
          </div>
          <div class="mt-2.5 pt-2 border-t border-slate-700/60 flex items-center justify-between text-[10px] text-slate-400">
            <span>Shift: <b class="text-slate-200"><?= htmlspecialchars($user['shift'] ?? 'Reguler') ?></b></span>
            <span class="text-emerald-400 font-bold flex items-center gap-0.5">Lihat Profil &rarr;</span>
          </div>
        </div>

        <!-- Navigation Links in Drawer -->
        <div class="space-y-1 pt-1">
          <p class="text-[10px] uppercase font-black tracking-wider text-slate-400 px-2 pb-1">Navigasi & Menu</p>
          
          <button onclick="closeOperatorDrawer(); switchOpTab('home');" class="w-full p-2.5 rounded-xl hover:bg-slate-800 flex items-center gap-3 text-xs font-bold text-slate-200 hover:text-white transition-colors">
            <span class="material-symbols-outlined text-emerald-400 text-[20px]">home</span>
            <span>Home</span>
          </button>

          <!-- SETTING MENU ITEM (OPENS PROFILE VIEW) -->
          <button onclick="closeOperatorDrawer(); openSettingProfileModal();" class="w-full p-2.5 rounded-xl hover:bg-slate-800 flex items-center justify-between text-xs font-bold text-slate-200 hover:text-white transition-colors group">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-indigo-400 text-[20px]">settings</span>
              <span>Settings</span>
            </div>
            <span class="material-symbols-outlined text-[16px] text-slate-400 group-hover:translate-x-0.5 transition-transform">chevron_right</span>
          </button>

          <!-- ABOUT MENU ITEM (OPENS ABOUT MODAL) -->
          <button onclick="closeOperatorDrawer(); openAboutModal();" class="w-full p-2.5 rounded-xl hover:bg-slate-800 flex items-center justify-between text-xs font-bold text-slate-200 hover:text-white transition-colors group">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-sky-400 text-[20px]">info</span>
              <span>About</span>
            </div>
            <span class="material-symbols-outlined text-[16px] text-slate-400 group-hover:translate-x-0.5 transition-transform">chevron_right</span>
          </button>

          <button onclick="closeOperatorDrawer(); refreshOperatorData();" class="w-full p-2.5 rounded-xl hover:bg-slate-800 flex items-center gap-3 text-xs font-bold text-slate-200 hover:text-white transition-colors">
            <span class="material-symbols-outlined text-teal-400 text-[20px]">sync</span>
            <span>Sync</span>
          </button>

          <?php if (Auth::isAdmin()): ?>
            <a href="../admin/" class="w-full p-2.5 rounded-xl hover:bg-slate-800 flex items-center gap-3 text-xs font-bold text-amber-300 hover:text-amber-200 transition-colors">
              <span class="material-symbols-outlined text-amber-400 text-[20px]">desktop_windows</span>
              <span>Buka Admin Panel</span>
            </a>
          <?php endif; ?>
        </div>

      </div>

      <!-- Drawer Bottom: Logout -->
      <div class="p-4 border-t border-slate-800 bg-slate-950/50">
        <a href="../logout" class="w-full py-2.5 px-3 rounded-xl bg-rose-600/20 hover:bg-rose-600 text-rose-300 hover:text-white border border-rose-500/30 flex items-center justify-center gap-2 text-xs font-bold transition-all">
          <span class="material-symbols-outlined text-[18px]">logout</span>
          <span>Keluar dari Aplikasi</span>
        </a>
      </div>

    </div>

  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: SETTING & PROFIL PENGGUNA (SETTING -> PROFILE) -->
<!-- ========================================================================= -->
<div id="modalOperatorProfile" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs hidden items-end sm:items-center justify-center p-0 sm:p-4">
  <div class="bg-white rounded-t-3xl sm:rounded-3xl max-w-md w-full p-5 shadow-2xl border border-slate-200 space-y-4 max-h-[90vh] overflow-y-auto animate-scale-up">
    
    <!-- Modal Header -->
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
      <div class="flex items-center gap-2">
        <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold shadow-xs">
          <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
        </div>
        <div>
          <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider">Setting & Profil Akun</h3>
          <p class="text-[10px] text-slate-500">Informasi pengguna & keamanan akun operator</p>
        </div>
      </div>
      <button onclick="App.closeModal('modalOperatorProfile')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center">
        <span class="material-symbols-outlined text-[18px]">close</span>
      </button>
    </div>

    <!-- Profile Hero Card -->
    <div class="bg-gradient-to-br from-slate-900 to-slate-800 text-white p-4 rounded-2xl shadow-sm space-y-3 relative overflow-hidden">
      <div class="flex items-center gap-3 relative z-10">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-400 to-teal-300 p-0.5 shadow-md flex-shrink-0">
          <div class="w-full h-full rounded-[14px] bg-slate-900 flex items-center justify-center text-emerald-300 font-black">
            <span class="material-symbols-outlined text-[26px]">engineering</span>
          </div>
        </div>
        <div class="min-w-0 truncate">
          <h4 class="font-black text-sm text-white leading-tight truncate"><?= htmlspecialchars($user['name'] ?? 'Operator') ?></h4>
          <p class="text-[11px] text-emerald-300 font-mono">@<?= htmlspecialchars($user['username'] ?? 'user') ?></p>
          <div class="flex items-center gap-1.5 mt-1">
            <span class="px-2 py-0.5 bg-emerald-900/90 text-emerald-200 text-[9px] font-black uppercase rounded-md border border-emerald-600/40">
              <?= htmlspecialchars($user['role'] ?? 'OPERATOR') ?>
            </span>
            <span class="px-2 py-0.5 bg-slate-700 text-slate-200 text-[9px] font-bold rounded-md">
              ID #<?= htmlspecialchars($user['id'] ?? '0') ?>
            </span>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-700/70 text-[11px]">
        <div>
          <span class="text-[9px] uppercase font-bold text-slate-400 block">Shift Kerja</span>
          <span class="font-bold text-white"><?= htmlspecialchars($user['shift'] ?? 'PIC') ?></span>
        </div>
        <div class="text-right">
          <span class="text-[9px] uppercase font-bold text-slate-400 block">Status Koneksi</span>
          <span class="font-bold text-emerald-400 flex items-center justify-end gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            <span>Online & Aktif</span>
          </span>
        </div>
      </div>
    </div>

    <!-- Security & Password Change Section -->
    <div class="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-3">
      <div class="flex items-center gap-1.5 border-b border-slate-200 pb-2">
        <span class="material-symbols-outlined text-slate-700 text-[18px]">lock_reset</span>
        <h4 class="font-black text-xs text-slate-800 uppercase tracking-wider">Ubah Kata Sandi (Password)</h4>
      </div>

      <form id="formOperatorChangePassword" onsubmit="handleOperatorPasswordSubmit(event)" class="space-y-2.5 text-xs">
        <div>
          <label class="block font-bold text-slate-700 mb-1 text-[11px]">Password Lama <span class="text-rose-500">*</span></label>
          <input type="password" id="opOldPassword" required placeholder="Masukkan password lama..." 
            class="w-full p-2.5 bg-white border border-slate-300 rounded-xl outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-500/20 text-xs">
        </div>

        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block font-bold text-slate-700 mb-1 text-[11px]">Password Baru <span class="text-rose-500">*</span></label>
            <input type="password" id="opNewPassword" required minlength="5" placeholder="Minimal 5 char..." 
              class="w-full p-2.5 bg-white border border-slate-300 rounded-xl outline-none focus:border-indigo-600 text-xs">
          </div>
          <div>
            <label class="block font-bold text-slate-700 mb-1 text-[11px]">Konfirmasi <span class="text-rose-500">*</span></label>
            <input type="password" id="opConfirmNewPassword" required minlength="5" placeholder="Ulangi password..." 
              class="w-full p-2.5 bg-white border border-slate-300 rounded-xl outline-none focus:border-indigo-600 text-xs">
          </div>
        </div>

        <button type="submit" id="btnSubmitOpPassword" 
          class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-extrabold text-xs rounded-xl shadow-xs transition-all flex items-center justify-center gap-1.5">
          <span class="material-symbols-outlined text-[16px]">key</span>
          <span>Simpan Perubahan Password</span>
        </button>
      </form>
    </div>

    <!-- Quick Action Switch to Logout -->
    <div class="flex items-center justify-between pt-1 text-xs">
      <a href="../logout" class="text-rose-600 hover:underline font-bold flex items-center gap-1">
        <span class="material-symbols-outlined text-[16px]">logout</span>
        <span>Keluar dari Akun Ini</span>
      </a>
      <button type="button" onclick="App.closeModal('modalOperatorProfile')" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl transition-colors">
        Tutup
      </button>
    </div>

  </div>
</div>

<!-- ========================================================================= -->
<!-- MODAL: ABOUT / TENTANG APLIKASI -->
<!-- ========================================================================= -->
<div id="modalOperatorAbout" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs hidden items-end sm:items-center justify-center p-0 sm:p-4">
  <div class="bg-white rounded-t-3xl sm:rounded-3xl max-w-md w-full p-5 shadow-2xl border border-slate-200 space-y-4 max-h-[90vh] overflow-y-auto animate-scale-up">
    
    <!-- Modal Header -->
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
      <div class="flex items-center gap-2">
        <div class="w-9 h-9 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center font-bold shadow-xs">
          <span class="material-symbols-outlined text-[20px]">info</span>
        </div>
        <div>
          <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider">About Application</h3>
          <p class="text-[10px] text-slate-500">Tentang Sistem PackStock Mobile</p>
        </div>
      </div>
      <button onclick="App.closeModal('modalOperatorAbout')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center">
        <span class="material-symbols-outlined text-[18px]">close</span>
      </button>
    </div>

    <!-- App Info Banner -->
    <div class="bg-gradient-to-br from-emerald-800 via-emerald-700 to-teal-900 text-white p-5 rounded-2xl shadow-sm text-center space-y-2">
      <div class="w-14 h-14 rounded-2xl bg-white/10 mx-auto flex items-center justify-center border border-white/20 shadow-inner">
        <span class="material-symbols-outlined text-[32px] text-emerald-200">package_2</span>
      </div>
      <div>
        <h4 class="font-black text-base tracking-tight">PackStock Mobile WMS</h4>
        <p class="text-[11px] text-emerald-200 font-mono mt-0.5">Versi 2.4.0 (Enterprise Edition)</p>
      </div>
      <p class="text-xs text-emerald-100/90 leading-relaxed max-w-xs mx-auto">
        Aplikasi manajemen operasional stok material packaging gudang dengan sinkronisasi data real-time, blank counting opname, dan verifikasi serah terima picking.
      </p>
    </div>

    <!-- Key Modules List -->
    <div class="space-y-2 text-xs">
      <h5 class="font-black text-[11px] uppercase tracking-wider text-slate-800">Fitur & Modul Utama:</h5>
      
      <div class="grid grid-cols-1 gap-2 text-slate-700">
        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-start gap-2">
          <span class="material-symbols-outlined text-amber-600 text-[18px] shrink-0 mt-0.5">assignment</span>
          <div>
            <b class="text-slate-900">Tugas Pengambilan:</b>
            <p class="text-[11px] text-slate-500">Serah terima material packaging ke line produksi dengan pencatatan riil pemotongan stok.</p>
          </div>
        </div>

        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-start gap-2">
          <span class="material-symbols-outlined text-indigo-600 text-[18px] shrink-0 mt-0.5">checklist</span>
          <div>
            <b class="text-slate-900">Dynamic Counting & Rak Scan:</b>
            <p class="text-[11px] text-slate-500">Penugasan hitung SKU khusus dan konfirmasi lokasi rak simpan.</p>
          </div>
        </div>

        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-start gap-2">
          <span class="material-symbols-outlined text-emerald-600 text-[18px] shrink-0 mt-0.5">inventory_2</span>
          <div>
            <b class="text-slate-900">Stock Opname Blank Count:</b>
            <p class="text-[11px] text-slate-500">Metode hitung fisik independen tanpa menampilkan stok sistem untuk akurasi mutlak.</p>
          </div>
        </div>

        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-start gap-2">
          <span class="material-symbols-outlined text-teal-600 text-[18px] shrink-0 mt-0.5">move_to_inbox</span>
          <div>
            <b class="text-slate-900">Penerimaan Multi-Product (Inbound):</b>
            <p class="text-[11px] text-slate-500">Input cepat barang masuk bertahap dengan keranjang draft sebelum submit.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Close Button -->
    <div class="pt-2 border-t border-slate-100">
      <button type="button" onclick="App.closeModal('modalOperatorAbout')" class="w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-xl transition-colors">
        Tutup Informasi
      </button>
    </div>

  </div>
</div>

<!-- ================= MODAL: SUBMIT PENGAMBILAN BARANG ================= -->
<div id="modalSubmitTask" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs hidden items-end sm:items-center justify-center p-0 sm:p-4">
  <div class="bg-white rounded-t-3xl sm:rounded-3xl max-w-md w-full p-5 shadow-2xl border border-slate-200 space-y-3.5 max-h-[90vh] overflow-y-auto animate-scale-up">
    
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
      <div class="flex items-center gap-2">
        <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold shadow-xs">
          <span class="material-symbols-outlined text-[20px]">task_alt</span>
        </div>
        <div>
          <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider">Submit Pengambilan</h3>
          <p class="text-[10px] text-slate-500">Konfirmasi serah terima ke line & potong stok</p>
        </div>
      </div>
      <button onclick="App.closeModal('modalSubmitTask')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center">
        <span class="material-symbols-outlined text-[18px]">close</span>
      </button>
    </div>

    <!-- Task Context Summary -->
    <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-1.5 text-xs">
      <h4 id="submitMaterialTitle" class="font-black text-slate-900 text-xs"></h4>
      <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-600">
        <p>Lokasi: <b id="submitRackLocationLabel" class="text-slate-900"></b></p>
        <p>Tujuan: <b id="submitDestinationLabel" class="text-slate-900"></b></p>
      </div>
      <p class="text-[11px] text-amber-800 font-bold">Target Diminta: <b id="submitTargetQtyLabel"></b></p>
    </div>

    <form id="formFinalSubmit" onsubmit="handleFinalTaskSubmit(event)" class="space-y-3 text-xs">
      <input type="hidden" id="submitTaskId">

      <div>
        <label class="block font-bold text-slate-800 mb-1 text-xs">
          Jumlah Riil yang Diserahkan (<span id="submitUnitLabel">Pcs</span>) <span class="text-rose-500">*</span>
        </label>
        <input type="number" id="submitActualQty" required min="1" 
          class="w-full p-2.5 bg-amber-50/70 border-2 border-amber-500 rounded-xl font-black text-xl text-amber-900 text-center outline-none">
        
        <!-- Stepper Helper -->
        <div class="flex items-center justify-center gap-1 pt-1.5">
          <button type="button" onclick="adjustNumericInput('submitActualQty', 1)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+1</button>
          <button type="button" onclick="adjustNumericInput('submitActualQty', 5)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+5</button>
          <button type="button" onclick="adjustNumericInput('submitActualQty', 10)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+10</button>
          <button type="button" onclick="adjustNumericInput('submitActualQty', 50)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+50</button>
        </div>
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1">Catatan Penerima di Line / PIC</label>
        <input type="text" id="submitNotes" placeholder="Contoh: Diterima Pak Joko di Line Packing 1" 
          class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl outline-none focus:border-amber-600 focus:bg-white">
      </div>

      <div class="pt-1">
        <button type="submit" id="btnFinalSubmit" 
          class="w-full py-3 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-extrabold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5">
          <span class="material-symbols-outlined text-[18px]">check_circle</span>
          <span>Konfirmasi & Potong Stok</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: SUBMIT DYNAMIC COUNTING (DENGAN SCAN / VERIFIKASI RAK) ================= -->
<div id="modalSubmitDynamicCount" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs hidden items-end sm:items-center justify-center p-0 sm:p-4">
  <div class="bg-white rounded-t-3xl sm:rounded-3xl max-w-md w-full p-5 shadow-2xl border border-slate-200 space-y-3.5 max-h-[90vh] overflow-y-auto animate-scale-up">
    
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
      <div class="flex items-center gap-2">
        <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-800 flex items-center justify-center font-bold shadow-xs">
          <span class="material-symbols-outlined text-[20px]">checklist</span>
        </div>
        <div>
          <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider">Input Dynamic Count</h3>
          <p id="dynModalSessionSubtitle" class="text-[10px] text-slate-500">Tugas Hitung SKU</p>
        </div>
      </div>
      <button onclick="App.closeModal('modalSubmitDynamicCount')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center">
        <span class="material-symbols-outlined text-[18px]">close</span>
      </button>
    </div>

    <!-- SKU Context Card -->
    <div class="bg-indigo-50/60 p-3.5 rounded-2xl border border-indigo-200 space-y-1.5 text-xs">
      <div class="flex items-center justify-between">
        <span id="dynModalItemCode" class="font-mono font-bold text-indigo-800 text-xs"></span>
        <span id="dynModalMasterRack" class="font-semibold text-slate-500 text-[10px]"></span>
      </div>
      <h4 id="dynModalItemName" class="font-black text-slate-900 text-xs"></h4>
      <p class="text-[10px] text-indigo-700 font-semibold flex items-center gap-1">
        <span class="material-symbols-outlined text-[14px]">barcode_scanner</span>
        <span>Pastikan verifikasi lokasi rak dan hitung fisik real di rak simpan.</span>
      </p>
    </div>

    <form id="formSubmitDynamicCount" onsubmit="handleDynamicCountSubmit(event)" class="space-y-3 text-xs">
      <input type="hidden" id="dynModalItemId">
      <input type="hidden" id="dynModalStageId">

      <div>
        <label class="block font-bold text-slate-700 mb-1 text-[11px] flex items-center gap-1">
          <span class="material-symbols-outlined text-[15px] text-indigo-700">qr_code_scanner</span>
          <span>Konfirmasi / Scan Lokasi Rak Simpan <span class="text-rose-500">*</span></span>
        </label>
        <input type="text" id="dynModalScannedRack" required placeholder="Contoh: Rak A-01" 
          class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl font-bold text-slate-800 outline-none focus:border-indigo-600 focus:bg-white text-xs">
      </div>

      <div>
        <label class="block font-bold text-slate-800 mb-1 text-xs">
          Jumlah Fisik Real di Rak (<span id="dynModalUnitLabel">Pcs</span>) <span class="text-rose-500">*</span>
        </label>
        <input type="number" id="dynModalCountQty" required min="0" 
          placeholder="0"
          class="w-full p-2.5 bg-indigo-50/70 border-2 border-indigo-500 rounded-xl font-black text-xl text-indigo-900 text-center outline-none">
        
        <!-- Stepper Helper -->
        <div class="flex items-center justify-center gap-1 pt-1.5">
          <button type="button" onclick="adjustNumericInput('dynModalCountQty', 1)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+1</button>
          <button type="button" onclick="adjustNumericInput('dynModalCountQty', 10)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+10</button>
          <button type="button" onclick="adjustNumericInput('dynModalCountQty', 50)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+50</button>
          <button type="button" onclick="adjustNumericInput('dynModalCountQty', 100)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+100</button>
          <button type="button" onclick="adjustNumericInput('dynModalCountQty', 0, true)" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 rounded-lg text-[10px] font-bold text-rose-700 transition-colors">Reset</button>
        </div>
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1 text-[11px]">Catatan Kondisi Fisik (Opsional)</label>
        <input type="text" id="dynModalNotes" placeholder="Contoh: Stok rapi di rak, kondisi baik..." 
          class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl outline-none focus:border-indigo-600 focus:bg-white text-xs">
      </div>

      <div class="pt-1">
        <button type="submit" id="btnSubmitDynamicCount" 
          class="w-full py-3 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-extrabold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5">
          <span class="material-symbols-outlined text-[18px]">send</span>
          <span>Kirim Hasil Dynamic Count</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: SUBMIT RECOUNT TUGAS SELISIH ================= -->
<div id="modalSubmitRecount" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs hidden items-end sm:items-center justify-center p-0 sm:p-4">
  <div class="bg-white rounded-t-3xl sm:rounded-3xl max-w-md w-full p-5 shadow-2xl border border-slate-200 space-y-3.5 max-h-[90vh] overflow-y-auto animate-scale-up">
    
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
      <div class="flex items-center gap-2">
        <div class="w-9 h-9 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center font-bold shadow-xs">
          <span class="material-symbols-outlined text-[20px]">replay</span>
        </div>
        <div>
          <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider">Submit Hitung Ulang (Recount)</h3>
          <p id="recountModalSubtitle" class="text-[10px] text-slate-500">Verifikasi Selisih Fisik</p>
        </div>
      </div>
      <button onclick="App.closeModal('modalSubmitRecount')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center">
        <span class="material-symbols-outlined text-[18px]">close</span>
      </button>
    </div>

    <!-- Recount SKU Context -->
    <div class="bg-purple-50/60 p-3.5 rounded-2xl border border-purple-200 space-y-1.5 text-xs">
      <div class="flex items-center justify-between">
        <span id="recountModalItemCode" class="font-mono font-bold text-purple-800 text-xs"></span>
        <span id="recountModalRack" class="font-semibold text-slate-600 text-[11px]"></span>
      </div>
      <h4 id="recountModalItemName" class="font-black text-slate-900 text-xs"></h4>
      <div id="recountModalStageBadge" class="pt-0.5"></div>
      
      <div class="p-2 rounded-xl bg-purple-100 text-purple-900 border border-purple-200 flex items-center gap-1 text-[10px] font-bold">
        <span class="material-symbols-outlined text-[15px] text-purple-700">warning</span>
        <span>Item ini mengalami selisih pada hitungan sebelumnya. Mohon hitung ulang dengan sangat teliti!</span>
      </div>
    </div>

    <form id="formSubmitRecount" onsubmit="handleRecountSubmit(event)" class="space-y-3 text-xs">
      <input type="hidden" id="recountModalItemId">
      <input type="hidden" id="recountModalStageId">
      <input type="hidden" id="recountModalStageNumber">

      <div>
        <label class="block font-bold text-slate-800 mb-1 text-xs">
          Jumlah Fisik Hasil Recount (<span id="recountModalUnitLabel">Pcs</span>) <span class="text-rose-500">*</span>
        </label>
        <input type="number" id="recountModalCountQty" required min="0" 
          placeholder="0"
          class="w-full p-2.5 bg-purple-50/70 border-2 border-purple-500 rounded-xl font-black text-xl text-purple-900 text-center outline-none">
        
        <!-- Stepper Helper -->
        <div class="flex items-center justify-center gap-1 pt-1.5">
          <button type="button" onclick="adjustNumericInput('recountModalCountQty', 1)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+1</button>
          <button type="button" onclick="adjustNumericInput('recountModalCountQty', 10)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+10</button>
          <button type="button" onclick="adjustNumericInput('recountModalCountQty', 50)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+50</button>
          <button type="button" onclick="adjustNumericInput('recountModalCountQty', 100)" class="px-2 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-[10px] font-bold text-slate-700 transition-colors">+100</button>
          <button type="button" onclick="adjustNumericInput('recountModalCountQty', 0, true)" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 rounded-lg text-[10px] font-bold text-rose-700 transition-colors">Reset</button>
        </div>
      </div>

      <div>
        <label class="block font-bold text-slate-700 mb-1 text-[11px]">Catatan Verifikasi (Opsional)</label>
        <input type="text" id="recountModalNotes" placeholder="Contoh: Sudah dihitung ulang per ikat kardus..." 
          class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl outline-none focus:border-purple-600 focus:bg-white text-xs">
      </div>

      <div class="pt-1">
        <button type="submit" id="btnSubmitRecountCount" 
          class="w-full py-3 bg-purple-600 hover:bg-purple-700 active:scale-95 text-white font-extrabold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5">
          <span class="material-symbols-outlined text-[18px]">send</span>
          <span>Simpan Hasil Recount</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Scripts with Cache Buster -->
<script src="<?= $baseUrl ?>/assets/js/app.js?v=<?= time() ?>"></script>
<script src="<?= $baseUrl ?>/assets/js/operator.js?v=<?= time() ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
