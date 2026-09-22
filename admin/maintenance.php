<?php
// admin/maintenance.php - Dedicated Enterprise Database Maintenance & Type-Based Cleaner
require_once __DIR__ . '/../includes/auth.php';

// Strict Super Admin Check
Auth::requireSuperAdmin();

$pageTitle = "Pembersihan & Maintenance Database - PackStock WMS";
$baseUrl = Auth::getBaseUrl();
$favIconUrl = (!empty($baseUrl) ? rtrim($baseUrl, '/') : '') . '/assets/img/favicon.svg';
$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <link rel="icon" type="image/svg+xml" href="<?= $favIconUrl ?>?v=2">
  <meta name="theme-color" content="#262363">
  
  <!-- CSS Stylesheets -->
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/tailwind.css?v=<?= time() ?>">

  <!-- Google Fonts & Material Symbols -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      background-color: #f8fafc;
      color: #0f172a;
    }
    .bg-ieg-primary {
      background-color: #262363;
    }
    .text-ieg-primary {
      color: #262363;
    }
    .tab-btn-active {
      background-color: #262363 !important;
      color: #ffffff !important;
      box-shadow: 0 4px 12px rgba(38, 35, 99, 0.25);
    }
    .modal-backdrop {
      background-color: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(4px);
    }
  </style>
</head>
<body class="min-h-screen flex flex-col antialiased bg-slate-50">

  <!-- Top Header Navigation -->
  <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
      
      <!-- Left: Logo & Page Title -->
      <div class="flex items-center gap-3">
        <a href="index.php" class="flex items-center gap-2 p-1 rounded-xl hover:bg-slate-100 transition-colors" title="Kembali ke Dashboard Admin">
          <span class="material-symbols-outlined text-[22px] text-slate-700">arrow_back</span>
        </a>
        <div class="h-6 w-px bg-slate-200"></div>
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-center font-bold">
            <span class="material-symbols-outlined text-[20px]">database</span>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="font-extrabold text-sm sm:text-base text-slate-900 leading-tight">Maintenance & Pembersihan Database</h1>
              <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase bg-rose-100 text-rose-800 border border-rose-200">Super Admin</span>
            </div>
            <p class="text-[11px] text-slate-500 font-medium hidden sm:block">Kelola & bersihkan data tabel per kategori (Kemas / Gimmick) secara terisolasi</p>
          </div>
        </div>
      </div>

      <!-- Right: Maintenance Mode Switcher & Quick Actions -->
      <div class="flex items-center gap-2.5">
        <!-- Live Status Pill -->
        <span id="headerMaintBadge" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold uppercase <?= Auth::isMaintenanceMode() ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-emerald-100 text-emerald-800 border border-emerald-300' ?>">
          <span class="w-2 h-2 rounded-full <?= Auth::isMaintenanceMode() ? 'bg-rose-600 animate-ping' : 'bg-emerald-600' ?>"></span>
          <span id="headerMaintBadgeText"><?= Auth::isMaintenanceMode() ? 'Mode Maintenance: AKTIF' : 'Situs: ONLINE' ?></span>
        </span>

        <!-- Toggle Maintenance Button -->
        <button type="button" id="btnHeaderToggleMaint" onclick="toggleMaintStatus()"
          class="h-[36px] px-3.5 <?= Auth::isMaintenanceMode() ? 'bg-blue-600 hover:bg-blue-700 text-white' : 'bg-rose-600 hover:bg-rose-700 text-white' ?> text-xs font-bold rounded-xl shadow-xs transition-all flex items-center gap-1.5 cursor-pointer active:scale-95">
          <span class="material-symbols-outlined text-[16px]"><?= Auth::isMaintenanceMode() ? 'lock_open' : 'lock' ?></span>
          <span class="hidden sm:inline"><?= Auth::isMaintenanceMode() ? 'Buka Kunci Situs' : 'Kunci Situs' ?></span>
        </button>

        <a href="index.php" class="h-[36px] px-3.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition-colors flex items-center gap-1.5 cursor-pointer">
          <span class="material-symbols-outlined text-[16px]">dashboard</span>
          <span class="hidden md:inline">Dashboard</span>
        </a>
      </div>
    </div>
  </header>

  <!-- Main Content Body -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6 w-full flex-1">

    <!-- Top KPI Summary Bar -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
      <!-- 1. Kemas Total SKU -->
      <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Master Stok Kemas</span>
          <span id="kpiKemasSku" class="text-xl font-black text-slate-900">-- SKU</span>
          <span class="text-[10px] text-emerald-700 font-bold block mt-0.5" id="kpiKemasTrans">-- Transaksi</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center justify-center">
          <span class="material-symbols-outlined text-[22px]">inventory_2</span>
        </div>
      </div>

      <!-- 2. Gimmick Total SKU -->
      <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Master Stok Gimmick</span>
          <span id="kpiGimmickSku" class="text-xl font-black text-slate-900">-- SKU</span>
          <span class="text-[10px] text-purple-700 font-bold block mt-0.5" id="kpiGimmickTrans">-- Transaksi</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 flex items-center justify-center">
          <span class="material-symbols-outlined text-[22px]">featured_seasonal_and_gifts</span>
        </div>
      </div>

      <!-- 3. Total Sesi Opname -->
      <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Sesi Stock Opname</span>
          <span id="kpiOpname" class="text-xl font-black text-slate-900">-- Sesi</span>
          <span class="text-[10px] text-blue-600 font-bold block mt-0.5" id="kpiTasks">-- Penugasan Task</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 flex items-center justify-center">
          <span class="material-symbols-outlined text-[22px]">fact_check</span>
        </div>
      </div>

      <!-- 4. Cadangan Otomatis -->
      <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
        <div>
          <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">Cadangan Database</span>
          <span id="kpiBackups" class="text-xl font-black text-slate-900">-- Berkas</span>
          <span class="text-[10px] text-amber-700 font-bold block mt-0.5">Snapshot Keamanan Aktif</span>
        </div>
        <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 border border-amber-200 flex items-center justify-center">
          <span class="material-symbols-outlined text-[22px]">backup</span>
        </div>
      </div>
    </div>

    <!-- Category Filter Tabs: KEMAS / GIMMICK / SEMUA DATA / BACKUP -->
    <div class="bg-white p-2 rounded-2xl border border-slate-200 shadow-xs flex flex-wrap items-center justify-between gap-2">
      <div class="flex flex-wrap items-center gap-1.5" id="typeFilterNav">
        <button type="button" onclick="switchTypeTab('kemas')" id="tabBtnKemas" 
          class="tab-btn-active px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 cursor-pointer">
          <span class="material-symbols-outlined text-[18px]">inventory_2</span>
          <span>Tipe Kemas (Packaging)</span>
          <span id="badgeTabKemas" class="px-1.5 py-0.2 rounded-full text-[10px] bg-white/20 text-white font-extrabold">0</span>
        </button>

        <button type="button" onclick="switchTypeTab('gimmick')" id="tabBtnGimmick" 
          class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all flex items-center gap-2 cursor-pointer">
          <span class="material-symbols-outlined text-[18px]">featured_seasonal_and_gifts</span>
          <span>Tipe Gimmick</span>
          <span id="badgeTabGimmick" class="px-1.5 py-0.2 rounded-full text-[10px] bg-slate-200 text-slate-700 font-extrabold">0</span>
        </button>

        <button type="button" onclick="switchTypeTab('all')" id="tabBtnAll" 
          class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all flex items-center gap-2 cursor-pointer">
          <span class="material-symbols-outlined text-[18px]">view_agenda</span>
          <span>Semua Data & Global</span>
        </button>

        <button type="button" onclick="switchTypeTab('backups')" id="tabBtnBackups" 
          class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-all flex items-center gap-2 cursor-pointer">
          <span class="material-symbols-outlined text-[18px]">history</span>
          <span>Riwayat Cadangan JSON</span>
        </button>
      </div>

      <!-- Quick Reload DB Stats -->
      <button type="button" onclick="loadAllMaintenanceData()" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-all flex items-center gap-1 cursor-pointer">
        <span class="material-symbols-outlined text-[16px]">refresh</span>
        <span>Refresh Angka</span>
      </button>
    </div>

    <!-- ========================================================================= -->
    <!-- VIEW 1: TIPE KEMAS (PACKAGING MATERIAL) -->
    <!-- ========================================================================= -->
    <div id="viewKemas" class="space-y-5">
      
      <!-- Kemas Quick Bulk Action Banner -->
      <div class="bg-gradient-to-r from-emerald-950 via-slate-900 to-slate-900 p-5 rounded-2xl border border-emerald-800/50 shadow-md text-white">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 text-[10px] font-extrabold uppercase">Area Khusus Kemas</span>
              <h3 class="text-base sm:text-lg font-black tracking-tight text-white">Pembersihan Terisolasi: Tipe Kemas</h3>
            </div>
            <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
              Tindakan di bawah hanya mempengaruhi data yang terkait dengan <b>Material Kemas</b> (Packaging). Seluruh data stok, transaksi, dan riwayat <b>Gimmick 100% aman dan tidak tersentuh</b>.
            </p>
          </div>

          <!-- Bulk Buttons for Kemas -->
          <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <!-- 1. Kemas Transactions Only -->
            <button type="button" onclick="openBulkCleanTypeModal('PACKAGING', 'transactions_only')" 
              class="h-[38px] px-4 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer active:scale-95">
              <span class="material-symbols-outlined text-[16px]">cleaning_services</span>
              <span>Kosongkan Transaksi Kemas Saja</span>
            </button>

            <!-- 2. Kemas Full Reset (Master + Transactions) -->
            <button type="button" onclick="openBulkCleanTypeModal('PACKAGING', 'full')" 
              class="h-[38px] px-4 bg-rose-700 hover:bg-rose-800 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer active:scale-95">
              <span class="material-symbols-outlined text-[16px]">delete_forever</span>
              <span>Reset Total Data Kemas</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Specific Table Cards Grid for Kemas -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">

        <!-- 1. Master Stok Kemas -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-emerald-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">materials (kemas)</span>
              <span id="badge_materials_kemas" class="px-2 py-0.5 rounded-full text-xs font-black bg-emerald-50 text-emerald-800 border border-emerald-200">0 SKU</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Master Katalog Kemas</h5>
            <p class="text-[11px] text-slate-500">Daftar master SKU dan saldo stok material kemas.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('materials_kemas', 'Master Katalog Kemas (materials)', document.getElementById('badge_materials_kemas').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Master Kemas</span>
          </button>
        </div>

        <!-- 2. Inbound Kemas -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-emerald-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">inbound_transactions</span>
              <span id="badge_inbound_kemas" class="px-2 py-0.5 rounded-full text-xs font-black bg-emerald-50 text-emerald-800 border border-emerald-200">0 Transaksi</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Riwayat Barang Masuk (Inbound) Kemas</h5>
            <p class="text-[11px] text-slate-500">Penerimaan surat jalan dan PO khusus kemas.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('inbound_kemas', 'Riwayat Barang Masuk Kemas (inbound_transactions)', document.getElementById('badge_inbound_kemas').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Inbound Kemas</span>
          </button>
        </div>

        <!-- 3. Outbound Kemas -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-emerald-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">outbound_transactions</span>
              <span id="badge_outbound_kemas" class="px-2 py-0.5 rounded-full text-xs font-black bg-emerald-50 text-emerald-800 border border-emerald-200">0 Transaksi</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Riwayat Barang Keluar (Outbound) Kemas</h5>
            <p class="text-[11px] text-slate-500">Pengeluaran manual material kemas dari gudang.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('outbound_kemas', 'Riwayat Barang Keluar Kemas (outbound_transactions)', document.getElementById('badge_outbound_kemas').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Outbound Kemas</span>
          </button>
        </div>

        <!-- 4. Tasks Kemas -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-emerald-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">tasks (kemas)</span>
              <span id="badge_tasks_kemas" class="px-2 py-0.5 rounded-full text-xs font-black bg-amber-50 text-amber-800 border border-amber-200">0 Task</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Penugasan Task PIC Kemas</h5>
            <p class="text-[11px] text-slate-500">Task picking, transfer rak, dan relokasi kemas.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('tasks_kemas', 'Penugasan Task Operator Kemas (tasks)', document.getElementById('badge_tasks_kemas').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Task Kemas</span>
          </button>
        </div>

        <!-- 5. Mutations Kemas -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-emerald-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">stock_mutations</span>
              <span id="badge_mutations_kemas" class="px-2 py-0.5 rounded-full text-xs font-black bg-blue-50 text-blue-800 border border-blue-200">0 Entri</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Buku Log Mutasi Stok Kemas</h5>
            <p class="text-[11px] text-slate-500">Kartu stok dan log mutasi keluar-masuk kemas.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('mutations_kemas', 'Log Mutasi Stok Kemas (stock_mutations)', document.getElementById('badge_mutations_kemas').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Mutasi Kemas</span>
          </button>
        </div>

        <!-- 6. Opname Kemas -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-emerald-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">stock_opname_items</span>
              <span id="badge_opname_kemas" class="px-2 py-0.5 rounded-full text-xs font-black bg-purple-50 text-purple-800 border border-purple-200">0 Item</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Data Stock Opname Kemas</h5>
            <p class="text-[11px] text-slate-500">Pencatatan fisik dan audit perhitungan kemas.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('opname_kemas', 'Data Stock Opname Kemas (stock_opnames)', document.getElementById('badge_opname_kemas').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Opname Kemas</span>
          </button>
        </div>

        <!-- 7. Consumable Requests Kemas -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-emerald-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">consumable_requests</span>
              <span id="badge_consumable_kemas" class="px-2 py-0.5 rounded-full text-xs font-black bg-amber-50 text-amber-800 border border-amber-200">0 Item</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Permintaan Material Kemas</h5>
            <p class="text-[11px] text-slate-500">Pengajuan consumable material untuk operasional.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('consumable_kemas', 'Permintaan Consumable Kemas (consumable_requests)', document.getElementById('badge_consumable_kemas').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Request Kemas</span>
          </button>
        </div>

      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- VIEW 2: TIPE GIMMICK (BARANG PROMOSI & HADIAH) -->
    <!-- ========================================================================= -->
    <div id="viewGimmick" class="hidden space-y-5">
      
      <!-- Gimmick Quick Bulk Action Banner -->
      <div class="bg-gradient-to-r from-purple-950 via-slate-900 to-slate-900 p-5 rounded-2xl border border-purple-800/50 shadow-md text-white">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 border border-purple-500/40 text-[10px] font-extrabold uppercase">Area Khusus Gimmick</span>
              <h3 class="text-base sm:text-lg font-black tracking-tight text-white">Pembersihan Terisolasi: Tipe Gimmick</h3>
            </div>
            <p class="text-xs text-slate-300 max-w-2xl leading-relaxed">
              Tindakan di bawah hanya mempengaruhi data yang terkait dengan <b>Material Gimmick</b>. Seluruh data stok, transaksi, dan riwayat <b>Kemas 100% aman dan tidak tersentuh</b>.
            </p>
          </div>

          <!-- Bulk Buttons for Gimmick -->
          <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <!-- 1. Gimmick Transactions Only -->
            <button type="button" onclick="openBulkCleanTypeModal('GIMMICK', 'transactions_only')" 
              class="h-[38px] px-4 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer active:scale-95">
              <span class="material-symbols-outlined text-[16px]">cleaning_services</span>
              <span>Kosongkan Transaksi Gimmick Saja</span>
            </button>

            <!-- 2. Gimmick Full Reset (Master + Transactions) -->
            <button type="button" onclick="openBulkCleanTypeModal('GIMMICK', 'full')" 
              class="h-[38px] px-4 bg-rose-700 hover:bg-rose-800 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer active:scale-95">
              <span class="material-symbols-outlined text-[16px]">delete_forever</span>
              <span>Reset Total Data Gimmick</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Specific Table Cards Grid for Gimmick -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">

        <!-- 1. Master Stok Gimmick -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-purple-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-purple-100/60 text-purple-700 px-2 py-0.5 rounded">materials (gimmick)</span>
              <span id="badge_materials_gimmick" class="px-2 py-0.5 rounded-full text-xs font-black bg-purple-50 text-purple-800 border border-purple-200">0 SKU</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Master Stok & Batch Gimmick</h5>
            <p class="text-[11px] text-slate-500">Daftar SKU gimmick beserta detail batch nomor dan kadaluarsa.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('materials_gimmick', 'Master Stok & Batch Gimmick (materials & material_batches)', document.getElementById('badge_materials_gimmick').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Master Gimmick</span>
          </button>
        </div>

        <!-- 2. Inbound Gimmick -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-purple-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-purple-100/60 text-purple-700 px-2 py-0.5 rounded">inbound_transactions</span>
              <span id="badge_inbound_gimmick" class="px-2 py-0.5 rounded-full text-xs font-black bg-purple-50 text-purple-800 border border-purple-200">0 Transaksi</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Riwayat Barang Masuk (Inbound) Gimmick</h5>
            <p class="text-[11px] text-slate-500">Penerimaan barang promosi/gimmick ke gudang.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('inbound_gimmick', 'Riwayat Barang Masuk Gimmick (inbound_transactions)', document.getElementById('badge_inbound_gimmick').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Inbound Gimmick</span>
          </button>
        </div>

        <!-- 3. Outbound Gimmick -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-purple-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-purple-100/60 text-purple-700 px-2 py-0.5 rounded">outbound_transactions</span>
              <span id="badge_outbound_gimmick" class="px-2 py-0.5 rounded-full text-xs font-black bg-purple-50 text-purple-800 border border-purple-200">0 Transaksi</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Riwayat Barang Keluar (Outbound) Gimmick</h5>
            <p class="text-[11px] text-slate-500">Pengeluaran manual material gimmick dari gudang.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('outbound_gimmick', 'Riwayat Barang Keluar Gimmick (outbound_transactions)', document.getElementById('badge_outbound_gimmick').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Outbound Gimmick</span>
          </button>
        </div>

        <!-- 4. Tasks Gimmick -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-purple-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-purple-100/60 text-purple-700 px-2 py-0.5 rounded">tasks (gimmick)</span>
              <span id="badge_tasks_gimmick" class="px-2 py-0.5 rounded-full text-xs font-black bg-amber-50 text-amber-800 border border-amber-200">0 Task</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Penugasan Task PIC Gimmick</h5>
            <p class="text-[11px] text-slate-500">Task picking dan relokasi rak khusus gimmick.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('tasks_gimmick', 'Penugasan Task Operator Gimmick (tasks)', document.getElementById('badge_tasks_gimmick').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Task Gimmick</span>
          </button>
        </div>

        <!-- 5. Mutations Gimmick -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-purple-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-purple-100/60 text-purple-700 px-2 py-0.5 rounded">stock_mutations</span>
              <span id="badge_mutations_gimmick" class="px-2 py-0.5 rounded-full text-xs font-black bg-blue-50 text-blue-800 border border-blue-200">0 Entri</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Buku Log Mutasi Stok Gimmick</h5>
            <p class="text-[11px] text-slate-500">Kartu stok dan jejak audit pergerakan gimmick.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('mutations_gimmick', 'Log Mutasi Stok Gimmick (stock_mutations)', document.getElementById('badge_mutations_gimmick').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Mutasi Gimmick</span>
          </button>
        </div>

        <!-- 6. Opname Gimmick -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-purple-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-purple-100/60 text-purple-700 px-2 py-0.5 rounded">stock_opname_items</span>
              <span id="badge_opname_gimmick" class="px-2 py-0.5 rounded-full text-xs font-black bg-purple-50 text-purple-800 border border-purple-200">0 Item</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Data Stock Opname Gimmick</h5>
            <p class="text-[11px] text-slate-500">Perhitungan fisik dan opname berkala khusus gimmick.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('opname_gimmick', 'Data Stock Opname Gimmick (stock_opnames)', document.getElementById('badge_opname_gimmick').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Opname Gimmick</span>
          </button>
        </div>

        <!-- 7. Consumable Requests Gimmick -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-purple-300 transition-all flex flex-col justify-between space-y-3">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-mono text-[11px] font-bold text-slate-500 bg-purple-100/60 text-purple-700 px-2 py-0.5 rounded">consumable_requests</span>
              <span id="badge_consumable_gimmick" class="px-2 py-0.5 rounded-full text-xs font-black bg-amber-50 text-amber-800 border border-amber-200">0 Item</span>
            </div>
            <h5 class="font-bold text-slate-900 text-xs">Permintaan Material Gimmick</h5>
            <p class="text-[11px] text-slate-500">Pengajuan permintaan khusus item gimmick.</p>
          </div>
          <button type="button" onclick="openCleanTableModal('consumable_gimmick', 'Permintaan Consumable Gimmick (consumable_requests)', document.getElementById('badge_consumable_gimmick').innerText)" 
            class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
            <span>Kosongkan Request Gimmick</span>
          </button>
        </div>

      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- VIEW 3: SEMUA DATA & GLOBAL SYSTEM -->
    <!-- ========================================================================= -->
    <div id="viewAll" class="hidden space-y-5">
      
      <!-- Bulk Clean Card -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        
        <!-- Global Action 1: Kosongkan Seluruh Riwayat Transaksi Semua Tipe -->
        <div class="bg-gradient-to-br from-white to-amber-50/50 p-5 rounded-2xl border border-amber-200 shadow-sm space-y-4 flex flex-col justify-between">
          <div class="space-y-2">
            <div class="flex items-center gap-2.5">
              <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                <span class="material-symbols-outlined text-[22px]">mop</span>
              </div>
              <div>
                <h4 class="font-black text-slate-900 text-sm sm:text-base">Kosongkan Seluruh Riwayat Transaksi</h4>
                <p class="text-xs text-slate-500">Hapus transaksi Inbound, Outbound, Task, Opname, Mutasi untuk SEMUA tipe.</p>
              </div>
            </div>

            <label class="flex items-center gap-2 pt-2 text-xs font-bold text-slate-800 cursor-pointer select-none">
              <input type="checkbox" id="globalResetStockZero" checked class="w-4 h-4 rounded text-amber-600 focus:ring-amber-500 border-slate-300">
              <span>Reset juga Stok Aktual (Current Stock) di Master menjadi 0</span>
            </label>
          </div>

          <button type="button" onclick="openBulkCleanGlobalModal('clean_all_transactions')" 
            class="h-[40px] px-4 bg-[#262363] hover:bg-[#1c1a4a] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer active:scale-95">
            <span class="material-symbols-outlined text-[18px]">cleaning_services</span>
            <span>Bersihkan Seluruh Riwayat Transaksi</span>
          </button>
        </div>

        <!-- Global Action 2: Factory Reset Penuh -->
        <div class="bg-gradient-to-br from-white to-rose-50/50 p-5 rounded-2xl border border-rose-300 shadow-sm space-y-4 flex flex-col justify-between">
          <div class="space-y-2">
            <div class="flex items-center gap-2.5">
              <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-800 flex items-center justify-center font-bold">
                <span class="material-symbols-outlined text-[22px]">restart_alt</span>
              </div>
              <div>
                <h4 class="font-black text-rose-950 text-sm sm:text-base">Reset Database Penuh (Factory Reset)</h4>
                <p class="text-xs text-slate-500">Hapus seluruh data master (Kemas & Gimmick) beserta seluruh transaksinya.</p>
              </div>
            </div>
            <p class="text-[11px] text-rose-700 font-medium">Hanya akun Super Admin dan pengguna sistem yang akan disisakan.</p>
          </div>

          <button type="button" onclick="openBulkCleanGlobalModal('factory_reset')" 
            class="h-[40px] px-4 bg-rose-700 hover:bg-rose-800 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5 cursor-pointer active:scale-95">
            <span class="material-symbols-outlined text-[18px]">delete_forever</span>
            <span>Lakukan Factory Reset Sekarang</span>
          </button>
        </div>

      </div>

      <!-- General Tables Grid -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-4">
        <h4 class="font-bold text-xs uppercase tracking-wider text-slate-700 flex items-center gap-2">
          <span class="material-symbols-outlined text-rose-600 text-[18px]">view_list</span>
          <span>Tabel Sistem & Umum</span>
        </h4>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
          <!-- 1. Handover Shift -->
          <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 flex flex-col justify-between space-y-3">
            <div class="space-y-1">
              <div class="flex items-center justify-between">
                <span class="font-mono text-[11px] font-bold text-slate-500">handovers</span>
                <span id="badge_handovers" class="px-2 py-0.5 rounded-full text-xs font-black bg-indigo-50 text-indigo-800 border border-indigo-200">0 Data</span>
              </div>
              <h5 class="font-bold text-slate-900 text-xs">Serah Terima Pekerjaan (Handover Shift)</h5>
            </div>
            <button type="button" onclick="openCleanTableModal('handovers', 'Serah Terima Pekerjaan (handovers)', document.getElementById('badge_handovers').innerText)" 
              class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
              <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
              <span>Kosongkan Data Handover</span>
            </button>
          </div>

          <!-- 2. Seluruh Inbound Semua Tipe -->
          <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 flex flex-col justify-between space-y-3">
            <div class="space-y-1">
              <div class="flex items-center justify-between">
                <span class="font-mono text-[11px] font-bold text-slate-500">inbound (semua)</span>
                <span id="badge_inbound_all" class="px-2 py-0.5 rounded-full text-xs font-black bg-emerald-50 text-emerald-800 border border-emerald-200">0 Transaksi</span>
              </div>
              <h5 class="font-bold text-slate-900 text-xs">Seluruh Inbound (Kemas & Gimmick)</h5>
            </div>
            <button type="button" onclick="openCleanTableModal('inbound', 'Riwayat Seluruh Barang Masuk (inbound_transactions)', document.getElementById('badge_inbound_all').innerText)" 
              class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
              <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
              <span>Kosongkan Semua Inbound</span>
            </button>
          </div>

          <!-- 3. Seluruh Outbound Semua Tipe -->
          <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 flex flex-col justify-between space-y-3">
            <div class="space-y-1">
              <div class="flex items-center justify-between">
                <span class="font-mono text-[11px] font-bold text-slate-500">outbound (semua)</span>
                <span id="badge_outbound_all" class="px-2 py-0.5 rounded-full text-xs font-black bg-emerald-50 text-emerald-800 border border-emerald-200">0 Transaksi</span>
              </div>
              <h5 class="font-bold text-slate-900 text-xs">Seluruh Outbound (Kemas & Gimmick)</h5>
            </div>
            <button type="button" onclick="openCleanTableModal('outbound', 'Riwayat Seluruh Barang Keluar (outbound_transactions)', document.getElementById('badge_outbound_all').innerText)" 
              class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5 cursor-pointer">
              <span class="material-symbols-outlined text-[16px]">delete_sweep</span>
              <span>Kosongkan Semua Outbound</span>
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- ========================================================================= -->
    <!-- VIEW 4: RIWAYAT CADANGAN DATABASE (BACKUP JSON) -->
    <!-- ========================================================================= -->
    <div id="viewBackups" class="hidden space-y-5">
      <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
          <div>
            <h4 class="font-black text-slate-900 text-sm sm:text-base flex items-center gap-2">
              <span class="material-symbols-outlined text-amber-600 text-[20px]">backup</span>
              <span>Daftar Cadangan Database Snapshot (.json)</span>
            </h4>
            <p class="text-xs text-slate-500">Sistem otomatis membuat backup sebelum tindakan pembersihan. Anda juga dapat membuat backup manual kapan saja.</p>
          </div>

          <button type="button" onclick="createManualBackup()" id="btnCreateBackup" 
            class="h-[38px] px-4 bg-[#262363] hover:bg-[#1c1a4a] text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer active:scale-95 shrink-0">
            <span class="material-symbols-outlined text-[18px]">add_circle</span>
            <span>Buat Cadangan Baru</span>
          </button>
        </div>

        <!-- Table of Backups -->
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs text-slate-700">
            <thead class="bg-slate-50 text-slate-500 font-extrabold uppercase border-b border-slate-200">
              <tr>
                <th class="py-3 px-4">Nama Berkas Cadangan</th>
                <th class="py-3 px-4">Waktu Dibuat</th>
                <th class="py-3 px-4">Ukuran</th>
                <th class="py-3 px-4 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody id="backupTableBody" class="divide-y divide-slate-100">
              <tr>
                <td colspan="4" class="py-6 text-center text-slate-400">Memuat berkas cadangan...</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </main>

  <!-- ========================================================================= -->
  <!-- MODAL: KONFIRMASI PEMBERSIHAN DATA (SECURITY CONFIRMATION) -->
  <!-- ========================================================================= -->
  <div id="modalConfirmDbClean" class="fixed inset-0 z-50 hidden items-center justify-center p-4 modal-backdrop">
    <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-rose-200 space-y-4">
      
      <!-- Modal Header -->
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold shrink-0">
          <span class="material-symbols-outlined text-[24px]">warning</span>
        </div>
        <div>
          <h4 class="font-black text-slate-900 text-sm sm:text-base">Konfirmasi Pembersihan Data</h4>
          <span class="text-[11px] text-rose-600 font-bold">Tindakan ini menghapus data secara permanen</span>
        </div>
      </div>

      <!-- Target Info Box -->
      <div class="p-3.5 bg-rose-50/70 rounded-xl border border-rose-200 space-y-1">
        <span id="cleanModalTargetTitle" class="text-[11px] font-extrabold uppercase tracking-wider text-rose-800 block">Tabel / Kategori Target:</span>
        <p id="cleanModalTargetDesc" class="text-xs font-bold text-slate-800"></p>
      </div>

      <p class="text-xs text-slate-600 leading-relaxed">
        Cadangan snapshot JSON otomatis dibuat di server sebelum penghapusan dieksekusi. Masukkan <b>Password Super Admin</b> Anda untuk melanjutkan.
      </p>

      <!-- Form Inputs -->
      <form id="formConfirmClean" onsubmit="submitCleanDatabase(event)" class="space-y-3.5">
        <input type="hidden" id="cleanActionType" value="">
        <input type="hidden" id="cleanTargetTable" value="">
        <input type="hidden" id="cleanTargetType" value="">
        <input type="hidden" id="cleanTargetMode" value="">

        <div>
          <label class="block text-xs font-bold text-slate-700 mb-1">Password Teknisi / Super Admin <span class="text-rose-500">*</span></label>
          <input type="password" id="cleanSuperAdminPassword" required autocomplete="current-password" placeholder="Ketik password login Anda..."
            class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-xs font-semibold text-slate-900 outline-none focus:border-rose-600 focus:bg-white transition-all">
        </div>

        <div id="cleanResetStockOption" class="hidden">
          <label class="flex items-center gap-2 text-xs font-bold text-slate-800 cursor-pointer select-none">
            <input type="checkbox" id="cleanResetStockZero" checked class="w-4 h-4 rounded text-rose-600 focus:ring-rose-500 border-slate-300">
            <span>Reset juga Stok Aktual (Current Stock) master terkait ke 0</span>
          </label>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
          <button type="button" onclick="closeCleanModal()" 
            class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors cursor-pointer">
            Batal
          </button>
          <button type="submit" id="btnSubmitCleanDb" 
            class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[16px]">delete</span>
            <span id="btnSubmitCleanDbText">Ya, Hapus Data Sekarang</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- Toast Notification Container -->
  <div id="toastContainer" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 max-w-sm pointer-events-none"></div>

  <!-- Scripts -->
  <script>
    const baseUrl = '<?= rtrim($baseUrl, '/') ?>';
    let currentDbStats = null;
    let isMaintenanceActive = <?= Auth::isMaintenanceMode() ? 'true' : 'false' ?>;

    // Toast Utility
    function showToast(message, type = 'info') {
      const container = document.getElementById('toastContainer');
      if (!container) return;
      
      const toast = document.createElement('div');
      toast.className = `p-3.5 rounded-xl shadow-lg text-xs font-bold flex items-center gap-2.5 text-white pointer-events-auto transition-all transform duration-300 translate-y-2 opacity-0 ${
        type === 'success' ? 'bg-emerald-600 shadow-emerald-600/30' :
        type === 'error' ? 'bg-rose-600 shadow-rose-600/30' :
        type === 'warning' ? 'bg-amber-600 shadow-amber-600/30' :
        'bg-[#262363] shadow-slate-900/30'
      }`;
      
      const icon = type === 'success' ? 'check_circle' : type === 'error' ? 'error' : type === 'warning' ? 'warning' : 'info';
      toast.innerHTML = `<span class="material-symbols-outlined text-[18px] shrink-0">${icon}</span><span>${message}</span>`;
      
      container.appendChild(toast);
      setTimeout(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
      }, 10);

      setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
      }, 4000);
    }

    // Tab Switcher (Kemas / Gimmick / All / Backups)
    function switchTypeTab(type) {
      const tabs = ['kemas', 'gimmick', 'all', 'backups'];
      tabs.forEach(t => {
        const view = document.getElementById('view' + t.charAt(0).toUpperCase() + t.slice(1));
        const btn = document.getElementById('tabBtn' + t.charAt(0).toUpperCase() + t.slice(1));
        if (view) view.classList.add('hidden');
        if (btn) {
          btn.classList.remove('tab-btn-active');
          btn.classList.add('text-slate-600', 'hover:text-slate-900', 'hover:bg-slate-100');
        }
      });

      const activeView = document.getElementById('view' + type.charAt(0).toUpperCase() + type.slice(1));
      const activeBtn = document.getElementById('tabBtn' + type.charAt(0).toUpperCase() + type.slice(1));
      if (activeView) activeView.classList.remove('hidden');
      if (activeBtn) {
        activeBtn.classList.remove('text-slate-600', 'hover:text-slate-900', 'hover:bg-slate-100');
        activeBtn.classList.add('tab-btn-active');
      }

      if (type === 'backups') {
        loadBackupsList();
      }
    }

    // Load Database Statistics
    async function loadAllMaintenanceData() {
      try {
        const res = await fetch('../api/maintenance.php?action=stats').then(r => r.json());
        if (res && res.success && res.stats) {
          currentDbStats = res.stats;
          renderStatsUI(res.stats);
        }
      } catch (err) {
        showToast('Gagal membaca statistik database: ' + err.message, 'error');
      }
    }

    function renderStatsUI(s) {
      const setBadge = (id, count, suffix = '') => {
        const el = document.getElementById(id);
        if (el) el.innerText = `${(count || 0).toLocaleString('id-ID')} ${suffix}`.trim();
      };

      // KPI Bar
      const kSku = s.materials ? s.materials.count_kemas : 0;
      const gSku = s.gimmick ? s.gimmick.count_gimmick : 0;
      const kIn = s.inbound_transactions ? s.inbound_transactions.count_kemas : 0;
      const kOut = s.outbound_transactions ? s.outbound_transactions.count_kemas : 0;
      const gIn = s.inbound_transactions ? s.inbound_transactions.count_gimmick : 0;
      const gOut = s.outbound_transactions ? s.outbound_transactions.count_gimmick : 0;

      setBadge('kpiKemasSku', kSku, 'SKU');
      setBadge('kpiKemasTrans', kIn + kOut, 'In/Out');
      setBadge('kpiGimmickSku', gSku, 'SKU');
      setBadge('kpiGimmickTrans', gIn + gOut, 'In/Out');
      setBadge('kpiOpname', s.stock_opnames ? s.stock_opnames.count : 0, 'Sesi');
      setBadge('kpiTasks', s.tasks ? s.tasks.count : 0, 'Task');

      // Badges on Tab Header
      setBadge('badgeTabKemas', kSku, 'SKU');
      setBadge('badgeTabGimmick', gSku, 'SKU');

      // Kemas Cards
      setBadge('badge_materials_kemas', kSku, 'SKU');
      setBadge('badge_inbound_kemas', kIn, 'Transaksi');
      setBadge('badge_outbound_kemas', kOut, 'Transaksi');
      setBadge('badge_tasks_kemas', s.tasks ? s.tasks.count_kemas : 0, 'Task');
      setBadge('badge_mutations_kemas', s.stock_mutations ? s.stock_mutations.count_kemas : 0, 'Entri');
      setBadge('badge_opname_kemas', s.stock_opname_items ? s.stock_opname_items.count_kemas : 0, 'Item');
      setBadge('badge_consumable_kemas', s.consumable_requests ? s.consumable_requests.count_kemas : 0, 'Item');

      // Gimmick Cards
      setBadge('badge_materials_gimmick', gSku, 'SKU');
      setBadge('badge_inbound_gimmick', gIn, 'Transaksi');
      setBadge('badge_outbound_gimmick', gOut, 'Transaksi');
      setBadge('badge_tasks_gimmick', s.tasks ? s.tasks.count_gimmick : 0, 'Task');
      setBadge('badge_mutations_gimmick', s.stock_mutations ? s.stock_mutations.count_gimmick : 0, 'Entri');
      setBadge('badge_opname_gimmick', s.stock_opname_items ? s.stock_opname_items.count_gimmick : 0, 'Item');
      setBadge('badge_consumable_gimmick', s.consumable_requests ? s.consumable_requests.count_gimmick : 0, 'Item');

      // General Cards
      setBadge('badge_handovers', s.handovers ? s.handovers.count : 0, 'Data');
      setBadge('badge_inbound_all', s.inbound_transactions ? s.inbound_transactions.count : 0, 'Transaksi');
      setBadge('badge_outbound_all', s.outbound_transactions ? s.outbound_transactions.count : 0, 'Transaksi');
    }

    // Modal Handlers
    function openCleanTableModal(tableKey, tableName, currentCount) {
      document.getElementById('cleanActionType').value = 'clean_table';
      document.getElementById('cleanTargetTable').value = tableKey;
      document.getElementById('cleanTargetType').value = '';
      document.getElementById('cleanTargetMode').value = '';
      document.getElementById('cleanResetStockOption').classList.add('hidden');

      document.getElementById('cleanModalTargetTitle').innerText = 'Tabel yang akan dikosongkan:';
      document.getElementById('cleanModalTargetDesc').innerText = `${tableName} (Total saat ini: ${currentCount})`;
      document.getElementById('cleanSuperAdminPassword').value = '';
      document.getElementById('btnSubmitCleanDbText').innerText = 'Ya, Kosongkan Tabel Sekarang';

      showCleanModal();
    }

    function openBulkCleanTypeModal(type, mode) {
      const typeLabel = (type === 'PACKAGING') ? 'Kemas (Packaging)' : 'Gimmick';
      document.getElementById('cleanActionType').value = 'clean_type_bulk';
      document.getElementById('cleanTargetTable').value = '';
      document.getElementById('cleanTargetType').value = type;
      document.getElementById('cleanTargetMode').value = mode;

      if (mode === 'transactions_only') {
        document.getElementById('cleanModalTargetTitle').innerText = `Tindakan: Kosongkan Seluruh Transaksi Tipe ${typeLabel}`;
        document.getElementById('cleanModalTargetDesc').innerText = `Semua Inbound, Outbound, Task, Opname, Mutasi, dan Request khusus tipe ${typeLabel} akan dihapus permanen. Master SKU tetap tersimpan aman.`;
        document.getElementById('cleanResetStockOption').classList.remove('hidden');
        document.getElementById('btnSubmitCleanDbText').innerText = `Ya, Bersihkan Transaksi ${typeLabel}`;
      } else {
        document.getElementById('cleanModalTargetTitle').innerText = `PERINGATAN: Reset Total Data Tipe ${typeLabel} (Master + Transaksi)`;
        document.getElementById('cleanModalTargetDesc').innerText = `Seluruh master SKU, batch, beserta seluruh riwayat transaksi tipe ${typeLabel} akan DIHAPUS TOTAL secara permanen. Tipe lainnya tetap 100% aman.`;
        document.getElementById('cleanResetStockOption').classList.add('hidden');
        document.getElementById('btnSubmitCleanDbText').innerText = `Ya, Reset Total Tipe ${typeLabel}`;
      }

      document.getElementById('cleanSuperAdminPassword').value = '';
      showCleanModal();
    }

    function openBulkCleanGlobalModal(actionType) {
      document.getElementById('cleanActionType').value = actionType;
      document.getElementById('cleanTargetTable').value = '';
      document.getElementById('cleanTargetType').value = '';
      document.getElementById('cleanTargetMode').value = '';
      document.getElementById('cleanResetStockOption').classList.add('hidden');

      if (actionType === 'clean_all_transactions') {
        document.getElementById('cleanModalTargetTitle').innerText = 'Tindakan: Kosongkan Seluruh Riwayat Transaksi (Semua Tipe)';
        document.getElementById('cleanModalTargetDesc').innerText = 'Semua transaksi Inbound, Outbound, Task, Opname, Mutasi untuk SEMUA tipe akan dihapus permanen. Master Kemas, Gimmick, dan User tetap aman.';
        document.getElementById('btnSubmitCleanDbText').innerText = 'Ya, Bersihkan Seluruh Transaksi';
      } else if (actionType === 'factory_reset') {
        document.getElementById('cleanModalTargetTitle').innerText = 'PERINGATAN TINGGI: Reset Database Penuh (Factory Reset)';
        document.getElementById('cleanModalTargetDesc').innerText = 'Seluruh data master stok (Kemas & Gimmick) beserta seluruh riwayat transaksi akan DIHAPUS TOTAL secara permanen! Hanya akun Super Admin/User yang disisakan.';
        document.getElementById('btnSubmitCleanDbText').innerText = 'Ya, Lakukan Factory Reset';
      }

      document.getElementById('cleanSuperAdminPassword').value = '';
      showCleanModal();
    }

    function showCleanModal() {
      const modal = document.getElementById('modalConfirmDbClean');
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      setTimeout(() => {
        document.getElementById('cleanSuperAdminPassword')?.focus();
      }, 100);
    }

    function closeCleanModal() {
      const modal = document.getElementById('modalConfirmDbClean');
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }

    // Submit Clean Database Action
    async function submitCleanDatabase(e) {
      e.preventDefault();
      const actionType = document.getElementById('cleanActionType').value;
      const tableKey = document.getElementById('cleanTargetTable').value;
      const targetType = document.getElementById('cleanTargetType').value;
      const targetMode = document.getElementById('cleanTargetMode').value;
      const password = document.getElementById('cleanSuperAdminPassword').value.trim();
      const resetStockZero = (document.getElementById('cleanResetStockZero')?.checked) ? 1 : 0;
      const globalResetStock = (document.getElementById('globalResetStockZero')?.checked) ? 1 : 0;

      const btn = document.getElementById('btnSubmitCleanDb');
      const btnText = document.getElementById('btnSubmitCleanDbText');
      const origText = btnText.innerText;

      if (!password) {
        showToast('Password Super Admin wajib diisi!', 'warning');
        return;
      }

      btn.disabled = true;
      btn.classList.add('opacity-70', 'cursor-not-allowed');
      btnText.innerText = 'Memproses & Menyimpan Cadangan...';

      try {
        let payload = { password: password };
        let url = `../api/maintenance.php?action=${actionType}`;

        if (actionType === 'clean_table') {
          payload.table = tableKey;
        } else if (actionType === 'clean_type_bulk') {
          payload.type = targetType;
          payload.mode = targetMode;
          payload.reset_stock_zero = resetStockZero;
        } else if (actionType === 'clean_all_transactions') {
          payload.reset_stock_zero = globalResetStock;
        }

        const res = await fetch(url, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        }).then(r => r.json());

        if (res && res.success) {
          showToast(res.message || 'Pembersihan berhasil diselesaikan!', 'success');
          closeCleanModal();
          loadAllMaintenanceData();
          loadBackupsList();
        } else {
          showToast(res?.message || 'Gagal memproses pembersihan data.', 'error');
        }
      } catch (err) {
        showToast('Terjadi kesalahan sistem: ' + err.message, 'error');
      } finally {
        btn.disabled = false;
        btn.classList.remove('opacity-70', 'cursor-not-allowed');
        btnText.innerText = origText;
      }
    }

    // Toggle Maintenance Mode
    async function toggleMaintStatus() {
      const btn = document.getElementById('btnHeaderToggleMaint');
      btn.disabled = true;
      const newActive = !isMaintenanceActive;

      try {
        const res = await fetch('../api/maintenance.php?action=toggle_maintenance', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ active: newActive })
        }).then(r => r.json());

        if (res && res.success) {
          isMaintenanceActive = newActive;
          showToast(res.message, 'success');
          
          const badge = document.getElementById('headerMaintBadge');
          const badgeText = document.getElementById('headerMaintBadgeText');
          if (newActive) {
            badge.className = 'hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold uppercase bg-rose-100 text-rose-800 border border-rose-300';
            badgeText.innerText = 'Mode Maintenance: AKTIF';
            btn.className = 'h-[36px] px-3.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition-all flex items-center gap-1.5 cursor-pointer active:scale-95';
            btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">lock_open</span><span class="hidden sm:inline">Buka Kunci Situs</span>';
          } else {
            badge.className = 'hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-extrabold uppercase bg-emerald-100 text-emerald-800 border border-emerald-300';
            badgeText.innerText = 'Situs: ONLINE';
            btn.className = 'h-[36px] px-3.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-xs transition-all flex items-center gap-1.5 cursor-pointer active:scale-95';
            btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">lock</span><span class="hidden sm:inline">Kunci Situs</span>';
          }
        } else {
          showToast(res?.message || 'Gagal mengubah status mode pemeliharaan', 'error');
        }
      } catch (err) {
        showToast('Kesalahan jaringan: ' + err.message, 'error');
      } finally {
        btn.disabled = false;
      }
    }

    // Manual Backup Creator & List
    async function createManualBackup() {
      const btn = document.getElementById('btnCreateBackup');
      btn.disabled = true;
      btn.innerText = 'Menyimpan...';

      try {
        const res = await fetch('../api/maintenance.php?action=backup_create', { method: 'POST' }).then(r => r.json());
        if (res && res.success) {
          showToast(res.message, 'success');
          loadBackupsList();
        } else {
          showToast(res?.message || 'Gagal membuat cadangan', 'error');
        }
      } catch (err) {
        showToast('Kesalahan: ' + err.message, 'error');
      } finally {
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">add_circle</span><span>Buat Cadangan Baru</span>';
      }
    }

    async function loadBackupsList() {
      const tbody = document.getElementById('backupTableBody');
      try {
        const res = await fetch('../api/maintenance.php?action=backup_list').then(r => r.json());
        if (res && res.success && res.backups) {
          const countEl = document.getElementById('kpiBackups');
          if (countEl) countEl.innerText = `${res.backups.length} Berkas`;

          if (res.backups.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="py-6 text-center text-slate-400">Belum ada berkas cadangan tersimpan.</td></tr>';
            return;
          }

          tbody.innerHTML = res.backups.map(b => `
            <tr class="hover:bg-slate-50 transition-colors">
              <td class="py-3 px-4 font-mono font-bold text-slate-900">${b.file}</td>
              <td class="py-3 px-4 text-slate-600">${b.created_at} WIB</td>
              <td class="py-3 px-4 text-slate-600">${(b.size / 1024).toFixed(1)} KB</td>
              <td class="py-3 px-4 text-right">
                <a href="../api/maintenance.php?action=backup_download&file=${encodeURIComponent(b.file)}" 
                  class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg text-xs font-bold transition-all inline-flex items-center gap-1">
                  <span class="material-symbols-outlined text-[14px]">download</span>
                  <span>Unduh JSON</span>
                </a>
              </td>
            </tr>
          `).join('');
        }
      } catch (err) {
        tbody.innerHTML = `<tr><td colspan="4" class="py-4 text-center text-rose-500">Gagal memuat cadangan: ${err.message}</td></tr>`;
      }
    }

    // Auto-initialize on load
    document.addEventListener('DOMContentLoaded', () => {
      loadAllMaintenanceData();
      loadBackupsList();
    });
  </script>
</body>
</html>
