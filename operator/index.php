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
  <div class="mobile-app-wrapper flex flex-col h-screen sm:h-[880px] w-full sm:max-w-md overflow-hidden sm:rounded-[42px] sm:border-[8px] sm:border-slate-800 shadow-2xl shadow-emerald-950/40 relative font-sans" style="background-image: linear-gradient(rgba(148, 163, 184, 0.06) 1px, transparent 1px), linear-gradient(90deg, rgba(148, 163, 184, 0.06) 1px, transparent 1px), linear-gradient(to bottom, #f8fafc, #f1f5f9 95%, #e2e8f0); background-size: 24px 24px, 24px 24px, 100% 100%;">
    
    <!-- Premium Ambient Background Orbs -->
    <div class="absolute top-[250px] -left-16 w-52 h-52 bg-emerald-400/20 rounded-full blur-3xl pointer-events-none z-0"></div>
    <div class="absolute bottom-20 -right-16 w-52 h-52 bg-indigo-400/15 rounded-full blur-3xl pointer-events-none z-0"></div>
    <div class="absolute bottom-[-50px] left-10 w-44 h-44 bg-teal-400/15 rounded-full blur-3xl pointer-events-none z-0"></div>
    
    <!-- TOP APP BAR (TOGGLE MENU, OPERATOR PROFILE & QUICK ACTIONS) -->
    <header class="bg-gradient-to-r from-emerald-800 via-emerald-700 to-teal-800 text-white px-3.5 py-3 flex items-center justify-between shadow-md flex-shrink-0 z-10 border-b border-emerald-900/40">
      
      <!-- Left: Toggle Menu Button & Operator Identity -->
      <div class="flex items-center gap-2 min-w-0 flex-1">
        <!-- TOGGLE MENU BUTTON -->
        <button type="button" onclick="toggleOperatorDrawer()" id="btnOpMenuToggle" title="Menu & Pengaturan" 
          class="w-9 h-9 rounded-2xl bg-emerald-900/70 hover:bg-emerald-900 active:scale-90 flex items-center justify-center text-emerald-100 hover:text-white transition-all border border-emerald-500/40 shadow-xs shrink-0 cursor-pointer">
          <span class="material-symbols-outlined text-[22px]">menu</span>
        </button>

        <div onclick="openShiftSwitcherModal()" title="Klik untuk Ganti Shift Kerja Aktif" class="flex items-center gap-2 min-w-0 truncate cursor-pointer group">
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
              <span id="headerUserShiftDisplay" class="truncate font-bold bg-emerald-900/60 px-1.5 py-0.2 rounded border border-emerald-500/40 text-emerald-200"><?= htmlspecialchars($user['shift'] ?? 'Shift 1 (Pagi)') ?></span>
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
      <?php $isFulfillmentOnly = Auth::isOperatorFulfillment(); ?>
      <div id="op-tab-home" class="space-y-4 animate-fade-in">
        
        <?php if ($isFulfillmentOnly): ?>
        <!-- Welcome Hero Banner Card (Fulfillment Role) -->
        <div class="bg-gradient-to-br from-amber-800 via-amber-700 to-orange-900 text-white rounded-3xl p-4 sm:p-5 shadow-lg border border-amber-600/30 relative overflow-hidden">
          <div class="absolute -right-6 -bottom-6 w-36 h-36 bg-amber-400/20 rounded-full blur-2xl pointer-events-none"></div>
          <div class="absolute right-3 top-3 opacity-15 pointer-events-none">
            <span class="material-symbols-outlined text-[76px]">shopping_cart_checkout</span>
          </div>

          <div class="relative z-10 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-xs uppercase tracking-wider font-black text-amber-200 flex items-center gap-1">
                <span>Fulfillment & Permintaan Consumable</span>
              </span>
              <span class="px-2.5 py-0.5 bg-amber-950/70 rounded-full text-[10px] font-extrabold text-amber-200 border border-amber-600/40 flex items-center gap-1 shadow-2xs">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                <span>Online</span>
              </span>
            </div>
            <h3 class="text-lg font-black tracking-tight leading-snug">
              Halo, <?= htmlspecialchars(explode(' ', $user['name'] ?? 'Operator')[0]) ?>!
            </h3>

            <!-- Quick Shift Indicator & Switcher Strip -->
            <div class="flex items-center justify-between pt-2 border-t border-amber-600/40">
              <div class="flex items-center gap-1.5 text-xs text-amber-100 font-medium truncate">
                <span class="material-symbols-outlined text-[16px] text-amber-300 shrink-0">schedule</span>
                <span class="truncate">Shift: <b id="homeCurrentShiftLabel" class="text-white font-black"><?= htmlspecialchars($user['shift'] ?? 'Shift 1 (Pagi)') ?></b></span>
              </div>
              <button type="button" onclick="openShiftSwitcherModal()" class="px-2.5 py-1 rounded-xl bg-white/20 hover:bg-white/30 active:scale-95 text-white text-[10px] font-black transition-all flex items-center gap-1 border border-white/25 shadow-xs shrink-0 cursor-pointer">
                <span class="material-symbols-outlined text-[14px]">swap_horiz</span>
                <span>Ganti Shift</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Quick KPI Summary Strip (Fulfillment Mode) -->
        <div class="grid grid-cols-3 gap-2.5">
          <div onclick="switchOpTab('request_consumable'); switchOpReqSubTab('history');" class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-xs hover:border-amber-400 cursor-pointer active:scale-95 transition-all text-center space-y-0.5 group">
            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block group-hover:text-amber-700">Total Pengajuan</span>
            <span id="homeStatFulfillmentTotal" class="font-mono font-black text-slate-800 text-lg leading-tight block">0</span>
            <span class="text-[9px] text-slate-500 font-semibold block">Dokumen</span>
          </div>

          <div onclick="switchOpTab('request_consumable'); switchOpReqSubTab('history');" class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-xs hover:border-amber-400 cursor-pointer active:scale-95 transition-all text-center space-y-0.5 group">
            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block group-hover:text-amber-700">Menunggu ACC</span>
            <span id="homeStatFulfillmentPending" class="font-mono font-black text-amber-600 text-lg leading-tight block">0</span>
            <span class="text-[9px] text-amber-700 font-bold block">Pending</span>
          </div>

          <div onclick="switchOpTab('request_consumable'); switchOpReqSubTab('history');" class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-xs hover:border-emerald-400 cursor-pointer active:scale-95 transition-all text-center space-y-0.5 group">
            <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block group-hover:text-emerald-700">Disetujui</span>
            <span id="homeStatFulfillmentApproved" class="font-mono font-black text-emerald-700 text-lg leading-tight block">0</span>
            <span class="text-[9px] text-emerald-700 font-bold block">ACC Selesai</span>
          </div>
        </div>

        <!-- Section Title: Menu Aplikasi (Fulfillment Only: 1 Menu) -->
        <div class="flex items-center justify-between px-1 pt-1">
          <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-amber-700 text-[18px]">shopping_cart_checkout</span>
            <span>Menu Operator Fulfillment</span>
          </h4>
          <span class="text-[11px] text-amber-700 font-extrabold bg-amber-100 px-2 py-0.5 rounded-full border border-amber-200">1 Modul Akses</span>
        </div>

        <!-- APP LAUNCHER: SINGLE CARD FOR REQ CONSUMABLE -->
        <div class="space-y-3">
          <div onclick="switchOpTab('request_consumable')" 
            class="p-5 bg-gradient-to-br from-amber-500 via-amber-600 to-orange-600 rounded-3xl text-white shadow-xl shadow-amber-500/25 active:scale-98 transition-all cursor-pointer relative overflow-hidden group border border-amber-400/40">
            <div class="absolute -right-4 -bottom-4 w-32 h-32 bg-white/10 rounded-full blur-xl pointer-events-none group-hover:scale-125 transition-transform"></div>
            <div class="flex items-center justify-between relative z-10">
              <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-xs flex items-center justify-center text-white shadow-inner shrink-0 group-hover:scale-110 transition-transform">
                  <span class="material-symbols-outlined text-[34px]">shopping_cart_checkout</span>
                </div>
                <div>
                  <h5 class="font-black text-base tracking-tight leading-tight">Request Consumable</h5>
                  <p class="text-xs text-amber-100 mt-0.5">Form Pengajuan Material & Monitoring ACC</p>
                  <div class="mt-2 inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-amber-950/40 text-[10px] font-bold text-amber-200 border border-amber-300/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-300 animate-pulse"></span>
                    <span>Buka Form Pengajuan</span>
                  </div>
                </div>
              </div>
              <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-white group-hover:translate-x-1 transition-transform shrink-0">
                <span class="material-symbols-outlined text-[22px]">arrow_forward</span>
              </div>
            </div>
          </div>
        </div>

        <?php else: ?>
        <!-- Welcome Hero Banner Card (Standard Operator) -->
        <div class="bg-gradient-to-br from-emerald-800 via-emerald-700 to-teal-900 text-white rounded-3xl p-4 sm:p-5 shadow-lg border border-emerald-600/30 relative overflow-hidden">
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

            <!-- Quick Shift Indicator & Switcher Strip -->
            <div class="flex items-center justify-between pt-2 border-t border-emerald-600/40">
              <div class="flex items-center gap-1.5 text-xs text-emerald-100 font-medium truncate">
                <span class="material-symbols-outlined text-[16px] text-emerald-300 shrink-0">schedule</span>
                <span class="truncate">Shift: <b id="homeCurrentShiftLabel" class="text-white font-black"><?= htmlspecialchars($user['shift'] ?? 'Shift 1 (Pagi)') ?></b></span>
              </div>
              <button type="button" onclick="openShiftSwitcherModal()" class="px-2.5 py-1 rounded-xl bg-white/20 hover:bg-white/30 active:scale-95 text-white text-[10px] font-black transition-all flex items-center gap-1 border border-white/25 shadow-xs shrink-0 cursor-pointer">
                <span class="material-symbols-outlined text-[14px]">swap_horiz</span>
                <span>Ganti Shift</span>
              </button>
            </div>
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
            <span>Menu Operator</span>
          </h4>
          <span class="text-[11px] text-slate-400 font-bold">6 Modul</span>
        </div>

        <!-- APP LAUNCHER GRID (NATIVE MOBILE APP TILES) -->
        <div class="grid grid-cols-3 gap-y-4 gap-x-2">

          <!-- APP 1: TUGAS PENGAMBILAN PACKAGING -->
          <div onclick="switchOpTab('tasks')" 
            class="flex flex-col items-center text-center p-2 rounded-2xl active:scale-95 transition-all cursor-pointer relative group">
            <div class="relative">
              <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 text-white flex items-center justify-center shadow-md shadow-amber-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">assignment</span>
              </div>
              <span id="homeBadgeTasks" class="hidden absolute -top-1.5 -right-1.5 px-1.5 py-0.5 rounded-full bg-rose-500 text-white font-black text-[9px] shadow-xs leading-none">
                0
              </span>
            </div>
            <div class="mt-2 w-full">
              <h5 class="font-bold text-slate-800 text-[10px] tracking-tight leading-snug group-hover:text-amber-700 transition-colors">Picking</h5>
            </div>
          </div>

          <!-- APP 2: DYNAMIC COUNTING (TASK SKU) -->
          <div onclick="switchOpTab('dynamic_count')" 
            class="flex flex-col items-center text-center p-2 rounded-2xl active:scale-95 transition-all cursor-pointer relative group">
            <div class="relative">
              <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white flex items-center justify-center shadow-md shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">checklist</span>
              </div>
              <span id="homeBadgeDynamicCount" class="hidden absolute -top-1.5 -right-1.5 px-1.5 py-0.5 rounded-full bg-indigo-600 text-white font-black text-[9px] shadow-xs leading-none">
                0
              </span>
            </div>
            <div class="mt-2 w-full">
              <h5 class="font-bold text-slate-800 text-[10px] tracking-tight leading-snug group-hover:text-indigo-700 transition-colors">Counting</h5>
            </div>
          </div>

          <!-- APP 3: STOCK OPNAME (PURE BLANK COUNT) -->
          <div onclick="switchOpTab('opname')" 
            class="flex flex-col items-center text-center p-2 rounded-2xl active:scale-95 transition-all cursor-pointer relative group">
            <div class="relative">
              <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white flex items-center justify-center shadow-md shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">inventory_2</span>
              </div>
              <span id="homeBadgeOpname" class="hidden absolute -top-1.5 -right-1.5 px-1.5 py-0.5 rounded-full bg-emerald-600 text-white font-black text-[9px] shadow-xs leading-none">
                Aktif
              </span>
            </div>
            <div class="mt-2 w-full">
              <h5 class="font-bold text-slate-800 text-[10px] tracking-tight leading-snug group-hover:text-emerald-700 transition-colors">Stock Opname</h5>
            </div>
          </div>

          <!-- APP 4: PENERIMAAN BARANG MASUK -->
          <div onclick="switchOpTab('inbound')" 
            class="flex flex-col items-center text-center p-2 rounded-2xl active:scale-95 transition-all cursor-pointer relative group">
            <div class="relative">
              <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-teal-500 to-emerald-600 text-white flex items-center justify-center shadow-md shadow-teal-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">move_to_inbox</span>
              </div>
            </div>
            <div class="mt-2 w-full">
              <h5 class="font-bold text-slate-800 text-[10px] tracking-tight leading-snug group-hover:text-teal-700 transition-colors">Putaway</h5>
            </div>
          </div>

          <!-- APP 5: FORM REQUEST CONSUMABLE (BARU) -->
          <div onclick="switchOpTab('request_consumable')" 
            class="flex flex-col items-center text-center p-2 rounded-2xl active:scale-95 transition-all cursor-pointer relative group">
            <div class="relative">
              <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-500 to-orange-600 text-white flex items-center justify-center shadow-md shadow-amber-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">shopping_cart_checkout</span>
              </div>
              <span id="homeBadgeConsumableReq" class="hidden absolute -top-1.5 -right-1.5 px-1.5 py-0.5 rounded-full bg-amber-600 text-white font-black text-[9px] shadow-xs leading-none">
                0
              </span>
            </div>
            <div class="mt-2 w-full">
              <h5 class="font-bold text-slate-800 text-[10px] tracking-tight leading-snug group-hover:text-amber-700 transition-colors">Req Consumable</h5>
            </div>
          </div>

          <!-- APP 6: RIWAYAT SELESAI -->
          <div onclick="switchOpTab('history')" 
            class="flex flex-col items-center text-center p-2 rounded-2xl active:scale-95 transition-all cursor-pointer relative group">
            <div class="relative">
              <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-slate-600 to-slate-800 text-white flex items-center justify-center shadow-md shadow-slate-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">history</span>
              </div>
            </div>
            <div class="mt-2 w-full">
              <h5 class="font-bold text-slate-800 text-[10px] tracking-tight leading-snug group-hover:text-slate-800 transition-colors">History</h5>
            </div>
          </div>

          <!-- APP 7: HANDOVER (SERAH TERIMA SHIFT) -->
          <div onclick="switchOpTab('handover')" 
            class="flex flex-col items-center text-center p-2 rounded-2xl active:scale-95 transition-all cursor-pointer relative group">
            <div class="relative">
              <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-rose-500 to-orange-600 text-white flex items-center justify-center shadow-md shadow-rose-500/20 group-hover:scale-105 transition-transform">
                <span class="material-symbols-outlined text-[24px]">published_with_changes</span>
              </div>
              <span id="homeBadgeHandover" class="hidden absolute -top-1.5 -right-1.5 px-1.5 py-0.5 rounded-full bg-rose-600 text-white font-black text-[9px] shadow-xs leading-none">
                New
              </span>
            </div>
            <div class="mt-2 w-full">
              <h5 class="font-bold text-slate-800 text-[10px] tracking-tight leading-snug group-hover:text-rose-700 transition-colors">Handover</h5>
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
        <?php endif; ?>
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
                  <input type="number" step="any" id="blankCountQty" required min="0" placeholder="0" 
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
          <!-- 1. Nomor Referensi / PO / Surat Jalan Header -->
          <div>
            <label class="block font-bold text-slate-700 mb-1 text-[11px] flex items-center gap-1">
              <span class="material-symbols-outlined text-[16px] text-emerald-600">tag</span>
              <span>No. Referensi / PO / Surat Jalan (Batch) <span class="text-rose-500 font-bold">*</span></span>
            </label>
            <input type="text" id="opInboundPoNumber" required placeholder="Contoh: PO-2026/001 atau No. Surat Jalan..." 
              class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold outline-none focus:bg-white focus:border-emerald-600 transition-colors">
          </div>

          <!-- 2. Box Tambah Packaging ke Keranjang Draft (Sequential Flow) -->
          <div class="p-3.5 bg-emerald-50/70 border border-emerald-200 rounded-2xl space-y-3">
            <p class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-900 flex items-center gap-1">
              <span class="material-symbols-outlined text-[16px]">add_circle</span>
              <span>Tambah Packaging ke Keranjang Draft:</span>
            </p>

            <!-- A. Pilih Material Packaging -->
            <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px]">Pilih Material Packaging <span class="text-rose-500">*</span></label>
              <select id="opInboundMaterialSelect" onchange="updateOpInboundStockBadge()" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs font-bold outline-none focus:border-emerald-600">
                <option value="">-- Pilih Material Packaging --</option>
              </select>
              <div id="opInboundStockBadge" class="text-[10px] text-slate-500 mt-1"></div>
            </div>

            <!-- B. Input Lokasi Rak (Autofill dari Material atau Edit Manual) -->
            <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px] flex items-center justify-between">
                <span class="flex items-center gap-1">
                  <span class="material-symbols-outlined text-[15px] text-emerald-600">grid_view</span>
                  <span>Lokasi Rak Simpan</span>
                </span>
                <span class="text-[10px] text-emerald-700 font-bold bg-emerald-100/80 px-1.5 py-0.2 rounded">Autofill / Edit</span>
              </label>
              <input type="text" id="opInboundLocation" placeholder="Lokasi Rak otomatis terisi dari material, atau ketik lokasi baru..." 
                class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold outline-none focus:border-emerald-600 transition-colors">
            </div>

            <!-- C. Jumlah Masuk (Qty) -->
            <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px]">Jumlah Masuk (Qty) <span class="text-rose-500">*</span></label>
              <input type="number" step="any" id="opInboundQty" min="0.001" placeholder="0" 
                class="w-full p-2.5 bg-white border border-slate-300 rounded-xl font-black text-base text-emerald-800 outline-none focus:border-emerald-600 text-center">
            </div>

            <!-- D. Catatan Item / Penerimaan -->
            <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px] flex items-center gap-1">
                <span class="material-symbols-outlined text-[15px] text-slate-400">notes</span>
                <span>Catatan Item (Opsional)</span>
              </label>
              <input type="text" id="opInboundNotes" placeholder="Keterangan tambahan untuk item ini..." 
                class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold outline-none focus:border-emerald-600">
            </div>

            <!-- E. Tombol Masukkan ke Draft -->
            <button type="button" onclick="addInboundDraftItem()" 
              class="w-full py-2.5 bg-slate-800 hover:bg-slate-900 active:scale-95 text-white rounded-xl text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5 cursor-pointer">
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

          <!-- Inbound Multi-Photo Upload Section -->
          <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-2xl space-y-2">
            <div class="flex items-center justify-between">
              <label class="block font-bold text-slate-700 text-[11px] flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px] text-emerald-600">photo_camera</span>
                <span>Foto Bukti / Surat Jalan (Bisa > 1 Foto)</span>
              </label>
              <span id="opInboundPhotoCountBadge" class="text-[10px] font-extrabold text-slate-500 bg-slate-200/80 px-2 py-0.5 rounded-full">0 Foto</span>
            </div>
            <div class="flex items-center gap-2">
              <input type="file" id="opInboundPhoto" accept="image/*" class="hidden" multiple onchange="previewOpInboundPhoto(event)">
              <button type="button" onclick="document.getElementById('opInboundPhoto').click()" 
                class="px-3 py-2 bg-white hover:bg-emerald-50 text-slate-700 font-bold rounded-xl border border-slate-300 transition-colors flex items-center gap-1 text-xs shadow-2xs">
                <span class="material-symbols-outlined text-[17px] text-emerald-600">add_photo_alternate</span>
                <span>Pilih / Ambil Foto</span>
              </button>
              <button type="button" id="btnOpClearInboundPhotos" onclick="clearOpInboundPhotos()" 
                class="hidden px-2.5 py-2 bg-rose-50 text-rose-600 font-bold rounded-xl border border-rose-200 transition-colors text-xs flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">delete</span>
                <span>Hapus</span>
              </button>
            </div>
            <div id="opInboundPhotoPreviewContainer" class="hidden flex flex-wrap gap-2 pt-1 max-h-28 overflow-y-auto"></div>
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
      <!-- SCREEN: FORM REQUEST CONSUMABLE (OPERATOR / FULFILLMENT) -->
      <!-- ========================================================================= -->
      <div id="op-tab-request_consumable" class="hidden space-y-3.5 animate-fade-in">
        
        <!-- Screen Header & Sub-Tab Switcher -->
        <div class="bg-white p-3 rounded-2xl border border-slate-200 shadow-xs space-y-2.5">
          <div class="flex items-center justify-between">
            <button type="button" onclick="switchOpTab('home')" class="flex items-center gap-1 text-slate-700 hover:text-amber-800 bg-slate-100 hover:bg-amber-50 px-3 py-1.5 rounded-xl text-xs font-bold transition-colors">
              <span class="material-symbols-outlined text-[18px]">arrow_back</span>
              <span>Menu Utama</span>
            </button>

            <div class="text-right">
              <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">Request Consumable</h3>
              <span class="text-[10px] text-amber-700 font-bold">Form Permintaan Barang</span>
            </div>
          </div>

          <!-- Sub-Tab Switch Buttons -->
          <div class="grid grid-cols-2 gap-1.5 p-1 bg-slate-100 rounded-xl">
            <button type="button" id="btnOpReqSubTabForm" onclick="switchOpReqSubTab('form')" class="py-2 rounded-lg font-bold text-xs bg-white text-amber-900 shadow-xs transition-all flex items-center justify-center gap-1.5">
              <span class="material-symbols-outlined text-[16px] text-amber-600">edit_note</span>
              <span>Buat Request Baru</span>
            </button>
            <button type="button" id="btnOpReqSubTabHistory" onclick="switchOpReqSubTab('history')" class="py-2 rounded-lg font-bold text-xs text-slate-600 hover:text-slate-900 transition-all flex items-center justify-center gap-1.5">
              <span class="material-symbols-outlined text-[16px] text-slate-500">history</span>
              <span>Riwayat Pengajuan</span>
              <span id="badgeOpReqMyPending" class="hidden px-1.5 py-0.2 rounded-full bg-amber-500 text-white font-mono text-[10px] leading-none">0</span>
            </button>
          </div>
        </div>

        <!-- 1. SUB-VIEW: FORM REQUEST BARU -->
        <div id="opReqSubViewForm" class="space-y-3">
          
          <!-- Unified Card: Form Permintaan -->
          <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs space-y-3.5">
            
            <!-- 1. Tujuan Brand / Line -->
            <div>
              <label class="block font-bold text-slate-800 mb-1 text-xs flex items-center justify-between">
                <span>Tujuan Brand / Line <span class="text-rose-500">*</span></span>
                <span class="text-[10px] text-amber-700 font-bold">4 Brand Utama</span>
              </label>
              <select id="opReqDestinationSelect" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-900 outline-none focus:bg-white focus:border-amber-600 transition-colors" data-no-search>
                <option value="">-- Pilih Brand (HANASUI / NCO / FYNE / EOMMA) --</option>
                <option value="HANASUI">HANASUI</option>
                <option value="NCO">NCO</option>
                <option value="FYNE">FYNE</option>
                <option value="EOMMA">EOMMA</option>
              </select>
            </div>

            <!-- Divider -->
            <div class="border-t border-slate-100 pt-3 space-y-2.5">
              <!-- 2. Pilih Material -->
              <div>
                <label class="block font-bold text-slate-800 mb-1 text-xs">
                  Pilih Material Packaging <span class="text-rose-500">*</span>
                </label>
                <select id="opReqMaterialSelect" onchange="handleOpReqMaterialSelectChange(this)" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 outline-none focus:bg-white focus:border-amber-600">
                  <option value="">-- Pilih Material Packaging --</option>
                </select>
                <div id="opReqStockInfoBadge" class="hidden mt-1.5 p-2 bg-amber-50/80 rounded-xl border border-amber-200 flex items-center justify-between text-xs">
                  <span class="text-slate-600 font-medium">Sisa Stok di Gudang:</span>
                  <span id="opReqStockVal" class="font-mono font-black text-amber-950">0 Pcs</span>
                </div>
              </div>

              <!-- 3. Qty & Tombol Tambah -->
              <div class="space-y-1.5">
                <div class="flex items-end gap-2">
                  <div class="w-1/3">
                    <label class="block font-bold text-slate-800 mb-1 text-xs">
                      Qty <span class="text-rose-500">*</span>
                    </label>
                    <input type="number" id="opReqQty" min="0.001" step="any" oninput="validateOpReqQtyLive()" placeholder="0" class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-mono font-black text-center text-slate-900 outline-none focus:bg-white focus:border-amber-600 transition-colors">
                  </div>
                  <div class="w-2/3">
                    <button type="button" id="btnOpReqAddDraft" onclick="addConsumableDraftItem()" class="w-full py-2.5 bg-amber-600 hover:bg-amber-700 active:scale-95 text-white font-extrabold text-xs rounded-xl shadow-xs transition-all flex items-center justify-center gap-1.5 h-[38px] cursor-pointer">
                      <span class="material-symbols-outlined text-[18px]">add_circle</span>
                      <span>+ Masukkan Draft</span>
                    </button>
                  </div>
                </div>
                <!-- Real-time Validation Error Banner -->
                <div id="opReqStockWarning" class="hidden p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 font-semibold text-[11px] flex items-center gap-1.5 animate-scale-up">
                  <span class="material-symbols-outlined text-[15px] text-rose-600 shrink-0">error</span>
                  <span id="opReqStockWarningText"></span>
                </div>
              </div>
            </div>

            <!-- 4. Daftar Draft Barang -->
            <div class="border-t border-slate-100 pt-3 space-y-2">
              <div class="flex items-center justify-between">
                <span class="font-extrabold text-xs text-slate-900 flex items-center gap-1">
                  <span class="material-symbols-outlined text-[16px] text-amber-600">shopping_cart</span>
                  <span>Daftar Barang Permintaan</span>
                </span>
                <span id="opReqDraftCountBadge" class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 font-mono font-bold text-[10px]">0 Item</span>
              </div>

              <div id="opReqDraftListContainer" class="space-y-2">
                <div class="p-4 text-center text-slate-400 text-xs bg-slate-50 rounded-xl border border-dashed border-slate-200">
                  <p>Belum ada material yang ditambahkan.</p>
                </div>
              </div>
            </div>

            <!-- 5. Urgensi, Catatan & Foto (Ringkas) -->
            <div class="border-t border-slate-100 pt-3 space-y-3">
              
              <!-- Prioritas Simple Pills -->
              <div>
                <label class="block font-bold text-slate-700 mb-1 text-xs">Urgensi Permintaan</label>
                <div class="grid grid-cols-2 gap-2">
                  <label class="p-2 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center gap-1.5 cursor-pointer has-[:checked]:bg-amber-100 has-[:checked]:border-amber-500 has-[:checked]:text-amber-950 font-bold text-xs text-slate-600 transition-all">
                    <input type="radio" name="opReqPriority" value="NORMAL" checked class="hidden">
                    <span class="material-symbols-outlined text-[16px]">schedule</span>
                    <span>Normal (Reguler)</span>
                  </label>
                  <label class="p-2 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center gap-1.5 cursor-pointer has-[:checked]:bg-rose-100 has-[:checked]:border-rose-500 has-[:checked]:text-rose-950 font-bold text-xs text-slate-600 transition-all">
                    <input type="radio" name="opReqPriority" value="URGENT" class="hidden">
                    <span class="material-symbols-outlined text-[16px] text-rose-600">priority_high</span>
                    <span>Mendesak (Urgent)</span>
                  </label>
                </div>
              </div>

              <!-- Catatan -->
              <div>
                <label class="block font-bold text-slate-700 mb-1 text-xs">Catatan (Opsional)</label>
                <input type="text" id="opReqGlobalNotes" placeholder="Ketik catatan jika ada..." class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs outline-none focus:bg-white focus:border-amber-600">
              </div>

              <!-- Foto Upload -->
              <div>
                <div class="flex items-center justify-between mb-1">
                  <label class="font-bold text-slate-700 text-xs">Foto Dokumen Request</label>
                  <span id="opReqPhotoCountBadge" class="text-[10px] text-slate-400 font-normal">0 Foto dipilih</span>
                </div>

                <input type="file" id="opReqPhotoInput" accept="image/*" multiple class="hidden" onchange="handleOpReqPhotosSelected(this)">
                <div onclick="document.getElementById('opReqPhotoInput').click()" 
                  class="p-2.5 bg-slate-50 hover:bg-amber-50 border border-dashed border-slate-300 hover:border-amber-400 rounded-xl cursor-pointer transition-all flex items-center justify-center gap-1.5 text-slate-700 hover:text-amber-900 active:scale-98">
                  <span class="material-symbols-outlined text-[18px] text-amber-600">add_a_photo</span>
                  <span class="text-xs font-bold">+ Ambil / Pilih Foto Dokumen</span>
                </div>

                <div id="opReqPhotoPreviewGrid" class="grid grid-cols-3 sm:grid-cols-4 gap-2 mt-2 hidden"></div>
              </div>

            </div>

            <!-- 6. Submit Button -->
            <div class="pt-2 border-t border-slate-100">
              <button type="button" id="btnSubmitConsumableRequest" onclick="handleConsumableRequestSubmit()" class="w-full py-3.5 bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-700 hover:from-emerald-700 hover:to-teal-800 active:scale-95 text-white font-black text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">send</span>
                <span>Kirim Permintaan ke Admin (Minta ACC)</span>
              </button>
            </div>

          </div>
        </div>

        <!-- 2. SUB-VIEW: RIWAYAT REQUEST SAYA -->
        <div id="opReqSubViewHistory" class="hidden space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-700">Daftar Pengajuan Saya</span>
            <button type="button" onclick="loadOperatorConsumableRequests()" class="text-xs font-bold text-amber-700 hover:underline flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]">refresh</span>
              <span>Refresh</span>
            </button>
          </div>

          <div id="opReqHistoryContainer" class="space-y-2.5">
            <!-- Injected via JavaScript -->
          </div>
        </div>

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

      <!-- ========================================================================= -->
      <!-- 6. SCREEN: HANDOVER SHIFT (SERAH TERIMA PEKERJAAN) -->
      <!-- ========================================================================= -->
      <div id="op-tab-handover" class="hidden space-y-3.5 animate-fade-in">
        
        <!-- Screen Back Bar -->
        <div class="flex items-center justify-between bg-white p-2.5 rounded-2xl border border-slate-200 shadow-xs">
          <button type="button" onclick="switchOpTab('home')" class="flex items-center gap-1 text-slate-700 hover:text-rose-800 bg-slate-100 hover:bg-rose-50 px-3 py-1.5 rounded-xl text-xs font-bold transition-colors">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            <span>Menu Utama</span>
          </button>

          <div class="text-right">
            <h3 class="font-black text-xs text-slate-900 uppercase tracking-wider">Handover Shift</h3>
            <span class="text-[10px] text-rose-700 font-semibold">Serah Terima Tugas</span>
          </div>
        </div>

        <!-- Button to Toggle Handover Form -->
        <button type="button" onclick="toggleHandoverForm()" 
          class="w-full py-2.5 bg-gradient-to-r from-rose-500 to-orange-600 hover:from-rose-600 hover:to-orange-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5 active:scale-98">
          <span class="material-symbols-outlined text-[18px]">add_circle</span>
          <span id="btnToggleHandoverText">Buat Handover Baru</span>
        </button>

        <!-- Form Submit Handover (Hidden by default) -->
        <div id="handoverFormContainer" class="hidden bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3">
          <h4 class="font-black text-slate-900 text-xs uppercase tracking-wider">Form Serah Terima Shift</h4>
          
          <form id="formSubmitHandover" onsubmit="submitHandover(event)" class="space-y-3 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <label class="block font-bold text-slate-700 mb-1 text-[11px]">Shift Asal Saya (Pengirim) <span class="text-rose-500">*</span></label>
                <select id="handoverFromShift" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl outline-none focus:border-rose-500 focus:bg-white text-xs font-semibold">
                  <option value="Shift 1 (Pagi 08:00 - 16:00)">Shift 1 (Pagi 08:00 - 16:00)</option>
                  <option value="Shift 2 (Siang 16:00 - 00:00)">Shift 2 (Siang 16:00 - 00:00)</option>
                </select>
              </div>

              <div>
                <label class="block font-bold text-slate-700 mb-1 text-[11px]">Shift Tujuan (Penerima) <span class="text-rose-500">*</span></label>
                <select id="handoverToShift" required class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl outline-none focus:border-rose-500 focus:bg-white text-xs font-semibold">
                  <option value="">-- Pilih Shift Tujuan --</option>
                  <option value="Shift 1 (Pagi 08:00 - 16:00)">Shift 1 (Pagi 08:00 - 16:00)</option>
                  <option value="Shift 2 (Siang 16:00 - 00:00)">Shift 2 (Siang 16:00 - 00:00)</option>
                  <option value="Semua Shift / Umum">Semua Shift / Umum</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px]">Catatan / Pekerjaan (Caption) <span class="text-rose-500">*</span></label>
              <textarea id="handoverNotes" required rows="3" placeholder="Tuliskan detail status pekerjaan, kendala, atau hal penting yang wajib dilanjutkan oleh shift berikutnya..." 
                class="w-full p-2.5 bg-slate-50 border border-slate-300 rounded-xl outline-none focus:border-rose-500 focus:bg-white text-xs"></textarea>
            </div>

             <div>
              <label class="block font-bold text-slate-700 mb-1 text-[11px]">Foto Dokumentasi (Opsional - Bisa Lebih dari 1)</label>
              <div class="flex items-center gap-2">
                <input type="file" id="handoverPhoto" accept="image/*" class="hidden" name="photos[]" multiple onchange="previewHandoverPhoto(event)">
                <button type="button" onclick="document.getElementById('handoverPhoto').click()" 
                  class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl border border-slate-300 transition-colors flex items-center gap-1">
                  <span class="material-symbols-outlined text-[18px]">photo_camera</span>
                  <span>Pilih Foto</span>
                </button>
                <span id="handoverPhotoLabel" class="text-[10px] text-slate-400 truncate">Belum ada foto</span>
              </div>
              <!-- Preview thumbnails grid -->
              <div id="handoverPhotoPreviewContainer" class="hidden mt-2"></div>
              <!-- Clear button -->
              <button type="button" id="btnClearHandoverPhotos" onclick="clearHandoverPhoto()" class="hidden mt-2 px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 text-[10px] font-bold rounded-lg border border-rose-200 transition-colors flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">delete</span>
                <span>Hapus Semua Foto</span>
              </button>
            </div>

            <div class="pt-1">
              <button type="submit" id="btnSubmitHandover" 
                class="w-full py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs rounded-xl shadow-md transition-colors flex items-center justify-center gap-1">
                <span class="material-symbols-outlined text-[18px]">send</span>
                <span>Kirim Handover</span>
              </button>
            </div>
          </form>
        </div>

        <!-- Handover Feed/List -->
        <div id="handoverListContainer" class="space-y-3">
          <div class="p-6 bg-white rounded-2xl text-center text-slate-400 text-xs shadow-xs border border-slate-200">
            <span class="material-symbols-outlined text-[20px] animate-spin text-rose-600 mb-1">progress_activity</span>
            <p>Memuat daftar handover...</p>
          </div>
        </div>

      </div>

    </div>

    <!-- PREMIUM BOTTOM NAVIGATION BAR -->
    <nav class="bg-white/95 backdrop-blur-md border-t border-slate-200/80 px-2 py-2.5 flex items-center justify-around flex-shrink-0 z-20 shadow-[0_-4px_12px_rgba(0,0,0,0.03)] rounded-b-[40px] sm:rounded-b-none">
      
      <!-- Home Button -->
      <button onclick="switchOpTab('home')" id="bottom-nav-home" 
        class="flex flex-col items-center gap-0.5 text-emerald-700 font-bold active:scale-95 transition-all cursor-pointer">
        <span class="material-symbols-outlined text-[20px] leading-none">home</span>
        <span class="text-[9px] tracking-tight">Beranda</span>
      </button>

      <?php if ($isFulfillmentOnly): ?>
        <!-- Form Request Button -->
        <button onclick="switchOpTab('request_consumable'); switchOpReqSubTab('form');" id="bottom-nav-req-form" 
          class="flex flex-col items-center gap-0.5 text-slate-400 font-semibold active:scale-95 transition-all cursor-pointer">
          <span class="material-symbols-outlined text-[20px] leading-none">add_shopping_cart</span>
          <span class="text-[9px] tracking-tight">Buat Req</span>
        </button>

        <!-- Riwayat ACC Button -->
        <button onclick="switchOpTab('request_consumable'); switchOpReqSubTab('history');" id="bottom-nav-req-hist" 
          class="flex flex-col items-center gap-0.5 text-slate-400 font-semibold active:scale-95 transition-all cursor-pointer">
          <span class="material-symbols-outlined text-[20px] leading-none">fact_check</span>
          <span class="text-[9px] tracking-tight">Riwayat ACC</span>
        </button>
      <?php else: ?>
        <!-- Picking Button -->
        <button onclick="switchOpTab('tasks')" id="bottom-nav-tasks" 
          class="flex flex-col items-center gap-0.5 text-slate-400 font-semibold active:scale-95 transition-all cursor-pointer">
          <span class="material-symbols-outlined text-[20px] leading-none">assignment</span>
          <span class="text-[9px] tracking-tight">Picking</span>
        </button>

        <!-- Counting Button -->
        <button onclick="switchOpTab('dynamic_count')" id="bottom-nav-dynamic_count" 
          class="flex flex-col items-center gap-0.5 text-slate-400 font-semibold active:scale-95 transition-all cursor-pointer">
          <span class="material-symbols-outlined text-[20px] leading-none">checklist</span>
          <span class="text-[9px] tracking-tight">Counting</span>
        </button>

        <!-- Opname Button -->
        <button onclick="switchOpTab('opname')" id="bottom-nav-opname" 
          class="flex flex-col items-center gap-0.5 text-slate-400 font-semibold active:scale-95 transition-all cursor-pointer">
          <span class="material-symbols-outlined text-[20px] leading-none">inventory_2</span>
          <span class="text-[9px] tracking-tight">Opname</span>
        </button>
      <?php endif; ?>

      <!-- Logout Button -->
      <a href="../logout" 
        class="flex flex-col items-center gap-0.5 text-slate-400 hover:text-rose-600 font-semibold active:scale-95 transition-all cursor-pointer decoration-none">
        <span class="material-symbols-outlined text-[20px] leading-none">logout</span>
        <span class="text-[9px] tracking-tight">Logout</span>
      </a>

    </nav>

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

          <button onclick="closeOperatorDrawer(); switchOpTab('request_consumable');" class="w-full p-2.5 rounded-xl hover:bg-slate-800 flex items-center gap-3 text-xs font-bold text-slate-200 hover:text-white transition-colors">
            <span class="material-symbols-outlined text-amber-400 text-[20px]">shopping_cart_checkout</span>
            <span>Request Consumable</span>
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
        <p class="text-[10px] text-emerald-100 font-bold tracking-wide mt-1">Dibuat oleh: dhanielo-marthinz IMS</p>
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
    <div class="pt-2 border-t border-slate-100 space-y-2">
      <button type="button" onclick="App.closeModal('modalOperatorAbout')" class="w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs rounded-xl transition-colors">
        Tutup Informasi
      </button>
      <div class="text-center text-[9px] text-slate-400 font-extrabold tracking-wider uppercase">
        Powered By Dhanielo Marthinz - IMS
      </div>
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
        <input type="number" step="any" id="submitActualQty" required min="0.001" 
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

      <!-- Task Picking Photo Proof Section -->
      <div class="bg-amber-50/50 p-3 rounded-xl border border-amber-200 space-y-2">
        <div class="flex items-center justify-between">
          <label class="block font-bold text-slate-700 text-[11px] flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px] text-amber-600">photo_camera</span>
            <span>Foto Bukti Penyerahan ke Line (Bisa > 1 Foto)</span>
          </label>
          <span id="taskPhotoCountBadge" class="text-[10px] font-extrabold text-slate-500 bg-amber-100 px-2 py-0.5 rounded-full">0 Foto</span>
        </div>
        <div class="flex items-center gap-2">
          <input type="file" id="taskCompletePhoto" accept="image/*" class="hidden" multiple onchange="previewTaskCompletePhoto(event)">
          <button type="button" onclick="document.getElementById('taskCompletePhoto').click()" 
            class="px-3 py-2 bg-white hover:bg-amber-100 text-slate-700 font-bold rounded-xl border border-slate-300 transition-colors flex items-center gap-1 text-xs shadow-2xs">
            <span class="material-symbols-outlined text-[17px] text-amber-600">photo_camera</span>
            <span>Pilih / Ambil Foto</span>
          </button>
          <button type="button" id="btnTaskClearPhotos" onclick="clearTaskCompletePhotos()" 
            class="hidden px-2.5 py-2 bg-rose-50 text-rose-600 font-bold rounded-xl border border-rose-200 transition-colors text-xs flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px]">delete</span>
            <span>Hapus</span>
          </button>
        </div>
        <div id="taskPhotoPreviewContainer" class="hidden flex flex-wrap gap-2 pt-1 max-h-28 overflow-y-auto"></div>
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
        <input type="number" step="any" id="dynModalCountQty" required min="0" 
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
        <input type="number" step="any" id="recountModalCountQty" required min="0" 
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

<!-- ================= MODAL: HANDOVER DETAIL ================= -->
<div id="modalHandoverDetail" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs hidden items-end sm:items-center justify-center p-0 sm:p-4">
  <div class="bg-white rounded-t-3xl sm:rounded-3xl max-w-md w-full p-5 shadow-2xl border border-slate-200 space-y-4 max-h-[90vh] overflow-y-auto animate-scale-up relative">
    
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
      <div class="flex items-center gap-2">
        <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-800 flex items-center justify-center font-bold shadow-xs">
          <span class="material-symbols-outlined text-[20px]">published_with_changes</span>
        </div>
        <div>
          <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider">Detail Handover</h3>
          <p id="detHandoverNo" class="text-[10px] text-slate-500 font-mono">HND-XXXX</p>
        </div>
      </div>
      <button onclick="App.closeModal('modalHandoverDetail')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center">
        <span class="material-symbols-outlined text-[18px]">close</span>
      </button>
    </div>

    <!-- Handover Info -->
    <div class="text-[11px] space-y-1.5 text-slate-600 bg-slate-50 p-3 rounded-2xl border border-slate-200/60">
      <div class="flex justify-between items-center">
        <span>Tanggal Kirim:</span>
        <b id="detHandoverDate" class="text-slate-950"></b>
      </div>
      <div class="flex justify-between items-center">
        <span>Dari Pengirim:</span>
        <b id="detHandoverFrom" class="text-slate-950"></b>
      </div>
      <div class="flex justify-between items-center">
        <span>Shift Pengirim:</span>
        <b id="detHandoverFromShift" class="text-slate-950"></b>
      </div>
      <div class="flex justify-between items-center">
        <span>Target Shift Tujuan:</span>
        <b id="detHandoverToShift" class="text-indigo-700 font-black"></b>
      </div>
      <div id="detHandoverReceivedByContainer" class="flex justify-between items-center hidden">
        <span>Diterima Oleh:</span>
        <b id="detHandoverReceivedBy" class="text-emerald-700 font-black"></b>
      </div>
      <div class="flex justify-between items-center">
        <span>Status Berkas:</span>
        <span id="detHandoverStatusBadge"></span>
      </div>
      <div class="flex justify-between items-center">
        <span>Status Share:</span>
        <span id="detHandoverShareBadge"></span>
      </div>
    </div>

    <!-- Handover Notes (Caption) -->
    <div class="space-y-1">
      <label class="block font-black text-slate-700 text-[10px] uppercase tracking-wider">Catatan Pekerjaan (Caption):</label>
      <div id="detHandoverNotes" class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 text-slate-800 text-xs font-mono whitespace-pre-wrap leading-relaxed"></div>
    </div>

    <!-- Handover Photos -->
    <div class="space-y-1.5">
      <label class="block font-black text-slate-700 text-[10px] uppercase tracking-wider">Foto Lampiran:</label>
      <div id="detHandoverPhotosGrid" class="grid grid-cols-2 gap-2.5">
        <!-- populated dynamically -->
      </div>
    </div>

    <!-- Actions Footer -->
    <div class="pt-2 border-t border-slate-100 flex gap-2" id="detHandoverActions">
      <!-- populated dynamically -->
    </div>

  </div>
</div>

<!-- ================= MODAL: PHOTO VIEWER WITH WATERMARK ================= -->
<div id="modalHandoverPhotoViewer" class="fixed inset-0 z-[60] bg-slate-950/95 backdrop-blur-md hidden items-center justify-center p-4">
  <div class="max-w-xl w-full flex flex-col items-center space-y-4 relative">
    
    <!-- Close Button -->
    <button onclick="App.closeModal('modalHandoverPhotoViewer')" class="absolute -top-12 right-0 w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
      <span class="material-symbols-outlined text-[22px]">close</span>
    </button>

    <!-- Image Wrapper Container -->
    <div class="relative w-full rounded-3xl overflow-hidden border border-white/10 shadow-2xl bg-black flex items-center justify-center select-none" style="aspect-ratio: 4/3;">
      
      <!-- Viewer Image -->
      <img id="viewerImage" src="#" alt="Watermarked Image" class="w-full h-full object-contain">

      <!-- Dynamic Visual Watermark Overlay -->
      <div class="absolute inset-0 pointer-events-none flex flex-col justify-between p-4 z-10 text-[9px] font-mono tracking-wider font-extrabold uppercase select-none">
        <!-- Top Row Watermark -->
        <div class="flex justify-between items-center text-white/50 drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)] bg-black/35 px-2 py-0.5 rounded-md">
          <span id="wmTopLeft">PACKSTOCK MOBILE WMS</span>
          <span id="wmTopRight">SERAH TERIMA SHIFT</span>
        </div>
        
        <!-- Center Diagonal Watermark -->
        <div class="absolute inset-0 flex items-center justify-center overflow-hidden rotate-[-30deg]">
          <span class="text-white/10 text-5xl sm:text-6xl font-black tracking-[0.3em] whitespace-nowrap select-none drop-shadow-xs">
            IMS
          </span>
        </div>

        <!-- Bottom Row Watermark -->
        <div class="flex justify-between items-center text-white/50 drop-shadow-[0_2px_4px_rgba(0,0,0,0.9)] bg-black/35 px-2 py-0.5 rounded-md mt-auto">
          <span id="wmBottomLeft">NO: HND-XXXX</span>
          <span id="wmBottomRight">DATE: 2026-08-30</span>
        </div>
      </div>
      
    </div>

    <!-- Description Details below image -->
    <div class="w-full text-center text-white/80 text-xs px-2" id="viewerImageDesc"></div>

  </div>
</div>

<!-- ================= MODAL: GANTI SHIFT AKTIF (ROLLING SHIFT MANDIRI) ================= -->
<div id="modalChangeMyShift" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs hidden items-end sm:items-center justify-center p-0 sm:p-4">
  <div class="bg-white rounded-t-3xl sm:rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-slate-200 space-y-4 animate-scale-up relative">
    <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
      <div class="flex items-center gap-2">
        <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
          <span class="material-symbols-outlined text-[20px]">schedule</span>
        </div>
        <div>
          <h3 class="font-black text-slate-900 text-xs uppercase tracking-wider">Pilih Shift Kerja Hari Ini</h3>
          <p class="text-[10px] text-slate-500">Sesuaikan jadwal rolling shift Anda</p>
        </div>
      </div>
      <button onclick="App.closeModal('modalChangeMyShift')" class="w-7 h-7 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 flex items-center justify-center">
        <span class="material-symbols-outlined text-[18px]">close</span>
      </button>
    </div>

    <form id="formChangeMyShift" onsubmit="submitChangeMyShift(event)" class="space-y-2.5 text-xs">
      <div class="space-y-2" id="shiftRadioGroup">
        <label class="flex items-center gap-3 p-3 rounded-2xl border-2 border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/50 cursor-pointer transition-all has-checked:border-emerald-600 has-checked:bg-emerald-50/80 shadow-2xs">
          <input type="radio" name="myActiveShift" value="Shift 1 (Pagi 08:00 - 16:00)" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500" required>
          <div>
            <div class="font-black text-slate-900 text-xs">Shift 1 (Pagi)</div>
            <div class="text-[10px] text-slate-500 font-mono">08:00 - 16:00 WIB</div>
          </div>
        </label>

        <label class="flex items-center gap-3 p-3 rounded-2xl border-2 border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/50 cursor-pointer transition-all has-checked:border-indigo-600 has-checked:bg-indigo-50/80 shadow-2xs">
          <input type="radio" name="myActiveShift" value="Shift 2 (Siang 16:00 - 00:00)" class="w-4 h-4 text-indigo-600 focus:ring-indigo-500" required>
          <div>
            <div class="font-black text-slate-900 text-xs">Shift 2 (Siang / Sore)</div>
            <div class="text-[10px] text-slate-500 font-mono">16:00 - 00:00 WIB</div>
          </div>
        </label>
      </div>

      <div class="pt-2 border-t border-slate-100 flex gap-2">
        <button type="button" onclick="App.closeModal('modalChangeMyShift')" class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold rounded-xl text-xs transition-colors">
          Batal
        </button>
        <button type="submit" id="btnSaveMyShift" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-extrabold rounded-xl text-xs shadow-md transition-all flex items-center justify-center gap-1 cursor-pointer">
          <span class="material-symbols-outlined text-[16px]">save</span>
          <span>Simpan Shift</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL MANDATORY: KONFIRMASI SHIFT SEBELUM AKSES MENU ================= -->
<div id="modalMandatoryShiftGate" class="fixed inset-0 z-[99999] bg-slate-950/90 backdrop-blur-md hidden items-center justify-center p-4">
  <div class="bg-white rounded-3xl max-w-sm w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-scale-up text-center">
    
    <!-- Top Icon Badge -->
    <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-700 text-white flex items-center justify-center mx-auto shadow-lg shadow-emerald-600/30">
      <span class="material-symbols-outlined text-[32px]">schedule</span>
    </div>

    <div>
      <h3 class="font-black text-slate-900 text-base tracking-tight">Pilih Shift Kerja Hari Ini</h3>
      <p class="text-xs text-slate-500 mt-1">Konfirmasi shift Anda untuk membuka seluruh menu & tugas operator</p>
    </div>

    <form id="formMandatoryShiftGate" onsubmit="submitMandatoryShiftGate(event)" class="space-y-3 text-left">
      <div class="space-y-2.5">
        
        <!-- Option Shift 1 -->
        <label id="gateLabelShift1" class="flex items-center gap-3 p-3.5 rounded-2xl border-2 border-slate-200 hover:border-emerald-500 hover:bg-emerald-50/50 cursor-pointer transition-all has-checked:border-emerald-600 has-checked:bg-emerald-50/80 shadow-2xs relative">
          <input type="radio" name="gateActiveShift" value="Shift 1 (Pagi 08:00 - 16:00)" class="w-4 h-4 text-emerald-600 focus:ring-emerald-500" required>
          <div class="flex-1">
            <div class="flex items-center justify-between">
              <span class="font-black text-slate-900 text-xs">Shift 1 (Pagi)</span>
              <span id="gateBadgeShift1" class="text-[9px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-full hidden">Otomatis Terpilih</span>
            </div>
            <div class="text-[10px] text-slate-500 font-mono mt-0.5">08:00 - 16:00 WIB</div>
          </div>
        </label>

        <!-- Option Shift 2 -->
        <label id="gateLabelShift2" class="flex items-center gap-3 p-3.5 rounded-2xl border-2 border-slate-200 hover:border-indigo-500 hover:bg-indigo-50/50 cursor-pointer transition-all has-checked:border-indigo-600 has-checked:bg-indigo-50/80 shadow-2xs relative">
          <input type="radio" name="gateActiveShift" value="Shift 2 (Siang 16:00 - 00:00)" class="w-4 h-4 text-indigo-600 focus:ring-indigo-500" required>
          <div class="flex-1">
            <div class="flex items-center justify-between">
              <span class="font-black text-slate-900 text-xs">Shift 2 (Siang)</span>
              <span id="gateBadgeShift2" class="text-[9px] font-bold text-indigo-800 bg-indigo-100 px-2 py-0.5 rounded-full hidden">Otomatis Terpilih</span>
            </div>
            <div class="text-[10px] text-slate-500 font-mono mt-0.5">16:00 - 00:00 WIB</div>
          </div>
        </label>

      </div>

      <div class="pt-2">
        <button type="submit" id="btnGateConfirmShift" class="w-full py-3.5 bg-gradient-to-r from-emerald-600 via-emerald-700 to-teal-700 hover:from-emerald-700 hover:to-teal-800 active:scale-98 text-white font-black text-xs rounded-xl shadow-lg shadow-emerald-700/30 transition-all flex items-center justify-center gap-1.5 cursor-pointer">
          <span class="material-symbols-outlined text-[18px]">check_circle</span>
          <span>Konfirmasi & Buka Menu</span>
        </button>
      </div>
    </form>

  </div>
</div>

<!-- Scripts with Cache Buster -->
<script>
  let CURRENT_USER_SHIFT = <?= json_encode($user['shift'] ?? 'Shift 1 (Pagi 08:00 - 16:00)') ?>;
  let CURRENT_USER_ROLE = <?= json_encode($user['role'] ?? 'operator') ?>;
  let IS_FULFILLMENT_ONLY = <?= $isFulfillmentOnly ? 'true' : 'false' ?>;
</script>
<script src="<?= $baseUrl ?>/assets/js/app.js?v=<?= time() ?>"></script>
<script src="<?= $baseUrl ?>/assets/js/operator.js?v=<?= time() ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
