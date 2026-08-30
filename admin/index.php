<?php
// admin/index.php - White & Green Enterprise Admin Dashboard & Stock Control Panel
require_once __DIR__ . '/../includes/auth.php';
Auth::requireAdmin();

$pageTitle = "Admin Control - PackStock WMS";
$baseUrl = Auth::getBaseUrl();
require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex h-screen overflow-hidden bg-slate-50 font-sans">

  <!-- SIDEBAR NAVIGATION (ENTERPRISE SUITE) -->
  <aside id="adminSidebar" class="w-64 bg-white text-slate-700 flex flex-col flex-shrink-0 border-r border-slate-200/90 select-none shadow-xs z-20 transition-all duration-300">
    <!-- Brand Logo & Mini Toggle -->
    <div class="h-16 flex items-center justify-between px-4 bg-white border-b border-slate-100 flex-shrink-0">
      <div class="flex items-center gap-3 sidebar-brand-container overflow-hidden">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-emerald-500 text-white flex items-center justify-center shadow-sm shadow-emerald-600/30 flex-shrink-0">
          <span class="material-symbols-outlined text-[22px]">inventory_2</span>
        </div>
        <div class="sidebar-brand-text truncate">
          <h2 class="font-extrabold text-slate-900 text-sm tracking-tight">PackStock</h2>
          <p class="text-[10px] text-slate-400 font-medium tracking-wide">Stock Control Panel</p>
        </div>
      </div>
      <button type="button" onclick="toggleAdminSidebar()" class="sidebar-brand-text p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer" title="Minimize Sidebar">
        <span class="material-symbols-outlined text-[18px]">dock_to_left</span>
      </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 px-3 py-3.5 space-y-3.5 overflow-y-auto">
      
      <!-- Section 1: Ringkasan & Dashboard -->
      <div class="sidebar-section">
        <div class="sidebar-section-title px-3 pb-1.5 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Utama</div>
        <div class="space-y-1">
          <button onclick="switchAdminTab('dashboard')" id="nav-dashboard" 
            class="hidden sidebar-nav-btn group w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-bold transition-all bg-emerald-600 text-white shadow-xs" title="Dashboard Overview">
            <span class="material-symbols-outlined text-[20px] flex-shrink-0">space_dashboard</span>
            <span class="sidebar-text truncate">Dashboard Overview</span>
          </button>
        </div>
      </div>

      <!-- Section 2: Group Inventory -->
      <div class="sidebar-section">
        <div class="sidebar-section-title px-3 pb-1.5 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Inventory</div>
        <div class="space-y-1">
          <button onclick="switchAdminTab('inventory')" id="nav-inventory" 
            class="hidden sidebar-nav-btn group w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all" title="Stock Kemas">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-[20px] flex-shrink-0">shelves</span>
              <span class="sidebar-text truncate">Stock Kemas</span>
            </div>
            <span id="sidebarAlertBadge" class="sidebar-badge hidden px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-600 text-white shadow-xs">0</span>
          </button>

          <button onclick="switchAdminTab('inbound')" id="nav-inbound" 
            class="hidden sidebar-nav-btn group w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all" title="Barang Masuk">
            <span class="material-symbols-outlined text-[20px] flex-shrink-0">move_to_inbox</span>
            <span class="sidebar-text truncate">Barang Masuk</span>
          </button>

          <button onclick="switchAdminTab('outbound')" id="nav-outbound" 
            class="hidden sidebar-nav-btn group w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all" title="Barang Keluar">
            <span class="material-symbols-outlined text-[20px] flex-shrink-0">outbox</span>
            <span class="sidebar-text truncate">Barang Keluar</span>
          </button>

          <button onclick="switchAdminTab('tasks')" id="nav-tasks" 
            class="hidden sidebar-nav-btn group w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all" title="Penugasan Operator (Task Dispatch)">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-[20px] flex-shrink-0">assignment</span>
              <span class="sidebar-text truncate">Penugasan Operator</span>
            </div>
            <span class="sidebar-badge px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase bg-slate-100 text-slate-600 border border-slate-200/80">Task</span>
          </button>

          <?php if (Auth::isSuperAdmin()): ?>
          <button onclick="switchAdminTab('mutations')" id="nav-mutations" 
            class="hidden sidebar-nav-btn group w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all" title="Audit Mutasi Stok">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-[20px] flex-shrink-0">history_edu</span>
              <span class="sidebar-text truncate">Audit Mutasi Stok</span>
            </div>
            <span class="sidebar-badge px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase bg-slate-100 text-slate-600 border border-slate-200/80">Audit</span>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Section 3: Group Counting -->
      <div class="sidebar-section">
        <div class="sidebar-section-title px-3 pb-1.5 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Counting</div>
        <div class="space-y-1">
          <button onclick="switchAdminTab('dynamic_count')" id="nav-dynamic_count" 
            class="hidden sidebar-nav-btn group w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all" title="Dynamic Count">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-[20px] flex-shrink-0">checklist</span>
              <span class="sidebar-text truncate">Dynamic Count</span>
            </div>
            <span id="sidebarDynamicBadge" class="sidebar-badge hidden px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-600 text-white shadow-xs">0</span>
          </button>

          <button onclick="switchAdminTab('opname')" id="nav-opname" 
            class="hidden sidebar-nav-btn group w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all" title="Stock Opname">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-[20px] flex-shrink-0">fact_check</span>
              <span class="sidebar-text truncate">Stock Opname</span>
            </div>
            <span id="sidebarOpnameBadge" class="sidebar-badge hidden px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-600 text-white shadow-xs">0</span>
          </button>

          <button onclick="switchAdminTab('counting_detail')" id="nav-counting_detail" 
            class="hidden sidebar-nav-btn group w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all" title="Log Detail Hasil Stock Opname">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-[20px] flex-shrink-0">table_rows</span>
              <span class="sidebar-text truncate">Detail Stock Opname</span>
            </div>
            <span class="sidebar-badge px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase bg-slate-100 text-slate-600 border border-slate-200/80">Log</span>
          </button>
        </div>
      </div>

      <!-- Section 4: Group Adjustment -->
      <div class="sidebar-section">
        <div class="sidebar-section-title px-3 pb-1.5 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Adjustment</div>
        <div class="space-y-1">
          <button onclick="switchAdminTab('adjust')" id="nav-adjust" 
            class="hidden sidebar-nav-btn group w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all" title="Adjustment Stok Packaging (+ / -)">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-[20px] flex-shrink-0">tune</span>
              <span class="sidebar-text truncate">Adjustment Opname</span>
            </div>
            <span class="sidebar-badge px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase bg-slate-100 text-slate-600 border border-slate-200/80">Adjust</span>
          </button>
        </div>
      </div>

      <!-- Section 5: Administrasi & Otorisasi -->
      <div class="sidebar-section">
        <div class="sidebar-section-title px-3 pb-1.5 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Pengaturan Sistem</div>
        <div class="space-y-1">
          <button onclick="switchAdminTab('users')" id="nav-users" 
            class="hidden sidebar-nav-btn group w-full flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all" title="Manajemen User & Role">
            <span class="material-symbols-outlined text-[20px] flex-shrink-0">group</span>
            <span class="sidebar-text truncate">Manajemen User & Role</span>
          </button>

          <button onclick="switchAdminTab('permissions')" id="nav-permissions" 
            class="hidden sidebar-nav-btn group w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all" title="Hak Akses Menu">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-[20px] flex-shrink-0">shield_person</span>
              <span class="sidebar-text truncate">Hak Akses Menu</span>
            </div>
            <span class="sidebar-badge px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase bg-slate-100 text-slate-600 border border-slate-200/80">Akses</span>
          </button>

          <?php if (Auth::isSuperAdmin()): ?>
          <button onclick="switchAdminTab('maintenance')" id="nav-maintenance" 
            class="hidden sidebar-nav-btn group w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-semibold text-rose-700 hover:text-rose-900 hover:bg-rose-50 transition-all" title="Maintenance & Pembersihan Database">
            <div class="flex items-center gap-3">
              <span class="material-symbols-outlined text-[20px] flex-shrink-0">database</span>
              <span class="sidebar-text truncate">Bersihkan Database</span>
            </div>
            <span class="sidebar-badge px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase bg-rose-100 text-rose-800 border border-rose-200">SUPER</span>
          </button>
          <?php endif; ?>
        </div>
      </div>

      <!-- Section 6: Akses Lapangan (Field Access Card) -->
      <div id="sidebarFieldAccessContainer" class="hidden pt-1">
        <div class="sidebar-section-title px-3 pb-1.5 text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Akses Lapangan</div>
        <a href="../operator/" target="_blank" 
          class="sidebar-field-access-btn group w-full p-2.5 rounded-xl bg-slate-50 border border-slate-200/80 hover:border-emerald-500 hover:bg-emerald-50/50 hover:shadow-2xs transition-all flex items-center justify-between" title="Panel PIC">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-lg bg-emerald-600 text-white flex items-center justify-center shadow-2xs group-hover:scale-105 transition-transform flex-shrink-0">
              <span class="material-symbols-outlined text-[16px]">smartphone</span>
            </div>
            <div class="sidebar-field-access-text text-left leading-tight truncate">
              <p class="text-xs font-bold text-slate-900 group-hover:text-emerald-900 transition-colors truncate">Panel PIC</p>
              <p class="text-[10px] text-slate-500 group-hover:text-emerald-700 transition-colors truncate">Mode Mobile Touch</p>
            </div>
          </div>
          <span class="sidebar-chevron material-symbols-outlined text-[16px] text-slate-400 group-hover:text-emerald-700 group-hover:translate-x-0.5 transition-all">open_in_new</span>
        </a>
      </div>

    </nav>

    <!-- User Profile & Logout Box -->
    <div class="p-3 bg-slate-50/90 border-t border-slate-200/80 flex-shrink-0">
      <div class="sidebar-user-card p-2 bg-white rounded-xl border border-slate-200/80 shadow-2xs flex items-center justify-between gap-2">
        <button type="button" onclick="openProfileModal()" title="Klik untuk edit nama & update password" 
          class="flex items-center gap-2.5 overflow-hidden text-left flex-1 hover:opacity-80 transition-opacity group">
          <div class="w-9 h-9 rounded-xl <?= Auth::isSuperAdmin() ? 'bg-gradient-to-br from-purple-500 to-indigo-600 text-white' : 'bg-gradient-to-br from-emerald-500 to-emerald-700 text-white' ?> flex items-center justify-center font-bold text-xs flex-shrink-0 shadow-xs group-hover:scale-105 transition-transform">
            <span class="material-symbols-outlined text-[20px]"><?= Auth::isSuperAdmin() ? 'shield_person' : 'account_circle' ?></span>
          </div>
          <div class="sidebar-user-details truncate leading-tight">
            <p class="text-xs font-black text-slate-900 truncate group-hover:text-emerald-700 transition-colors"><?= htmlspecialchars(Auth::name()) ?></p>
            <p class="text-[11px] font-mono text-emerald-700 font-bold truncate">@<?= htmlspecialchars(Auth::username()) ?></p>
          </div>
        </button>
        <a href="../logout" title="Logout dari Sesi" class="sidebar-user-logout p-2 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors flex-shrink-0">
          <span class="material-symbols-outlined text-[19px]">logout</span>
        </a>
      </div>
    </div>
  </aside>

  <!-- MAIN VIEWPORT -->
  <main class="flex-1 flex flex-col overflow-hidden bg-slate-50">
    
    <!-- TOP HEADER BAR (CLEAN & SIMPLE) -->
    <header class="h-14 bg-white border-b border-slate-200 px-4 sm:px-6 flex items-center justify-between flex-shrink-0 z-10">
      <div class="flex items-center gap-3">
        <!-- Sidebar Minimize / Maximize Toggle Button -->
        <button type="button" id="btnToggleSidebar" onclick="toggleAdminSidebar()" 
          class="p-2 rounded-xl text-slate-600 hover:text-slate-900 hover:bg-slate-100 active:scale-95 transition-all flex items-center justify-center border border-slate-200/80 shadow-2xs cursor-pointer" 
          title="Minimize / Maximize Sidebar">
          <span id="iconToggleSidebar" class="material-symbols-outlined text-[20px]">menu_open</span>
        </button>

        <h1 id="adminPageTitle" class="text-sm font-bold text-slate-900 truncate">Dashboard Monitoring Stok & Lapangan</h1>
      </div>

      <!-- Right Indicator -->
      <div class="flex items-center gap-2 text-xs text-slate-500">
        <span class="material-symbols-outlined text-[16px] text-slate-400">schedule</span>
        <span id="headerLiveClock" class="font-mono text-slate-700 font-medium"><?= date('d M Y - H:i') ?> WIB</span>
      </div>
    </header>

    <!-- CONTENT BODY (SCROLLABLE) -->
    <div class="flex-1 overflow-y-auto p-6 space-y-5">

      <!-- ================= 1. TAB: DASHBOARD (OVERVIEW, TOP 10 CHARTS & TABLES, RINGKASAN STOK) ================= -->
      <div id="tab-dashboard" class="space-y-5">
        
        <!-- Filter & Control Toolbar -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3">
          
          <!-- Top Row: Segmented Mode Selector & Action Buttons -->
          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-slate-100 pb-3">
            
            <!-- Filter Type Selector -->
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5 mr-1">
                <span class="material-symbols-outlined text-[18px] text-emerald-700">calendar_month</span>
                <span>Filter Periode:</span>
              </span>

              <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs font-bold">
                <button type="button" id="btnDashFilterDate" onclick="setDashboardFilterType('date')" 
                  class="py-1.5 px-3 rounded-lg bg-emerald-600 text-white shadow-2xs transition-all">
                  📅 Harian
                </button>
                <button type="button" id="btnDashFilterWeek" onclick="setDashboardFilterType('week')" 
                  class="py-1.5 px-3 rounded-lg text-slate-600 hover:text-slate-900 transition-all">
                  📆 Mingguan
                </button>
                <button type="button" id="btnDashFilterMonth" onclick="setDashboardFilterType('month')" 
                  class="py-1.5 px-3 rounded-lg text-slate-600 hover:text-slate-900 transition-all">
                  📊 Bulanan
                </button>
                <button type="button" id="btnDashFilterAll" onclick="setDashboardFilterType('all')" 
                  class="py-1.5 px-3 rounded-lg text-slate-600 hover:text-slate-900 transition-all">
                  🌐 Semua Waktu
                </button>
              </div>
            </div>

            <!-- Dynamic Input Filters depending on Mode -->
            <div class="flex flex-wrap items-center gap-2">
              
              <!-- Date Picker (if date mode) -->
              <div id="dashFilterDateContainer" class="flex items-center gap-2">
                <div class="premium-datepicker-wrapper">
                  <span class="material-symbols-outlined picker-icon text-emerald-700">calendar_month</span>
                  <input type="text" id="dashInputDate" value="<?= date('Y-m-d') ?>" placeholder="Pilih Tanggal..." 
                    class="premium-datepicker-input px-3 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-900 outline-none focus:border-emerald-600">
                </div>
                <button type="button" onclick="setDashboardDateToday()" class="h-[38px] px-3 bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 rounded-lg text-xs font-semibold transition-colors flex items-center justify-center">Hari Ini</button>
              </div>

              <!-- Week Selectors (if week mode) -->
              <div id="dashFilterWeekContainer" class="hidden flex items-center gap-2 flex-wrap">
                <select id="dashSelectYear" onchange="loadDashboardStockSummary()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none">
                  <option value="2026" selected>2026</option>
                  <option value="2025">2025</option>
                </select>

                <select id="dashSelectMonth" onchange="updateDashWeekOptions(); loadDashboardStockSummary();" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none">
                  <option value="1">Januari</option>
                  <option value="2">Februari</option>
                  <option value="3">Maret</option>
                  <option value="4">April</option>
                  <option value="5">Mei</option>
                  <option value="6">Juni</option>
                  <option value="7">Juli</option>
                  <option value="8" selected>Agustus</option>
                  <option value="9">September</option>
                  <option value="10">Oktober</option>
                  <option value="11">November</option>
                  <option value="12">Desember</option>
                </select>

                <select id="dashSelectWeek" onchange="loadDashboardStockSummary()" class="h-[38px] px-2.5 bg-emerald-50 border border-emerald-300 text-emerald-900 font-bold rounded-lg text-xs outline-none">
                  <option value="1">Week 1 (Tgl 01 - 07)</option>
                  <option value="2">Week 2 (Tgl 08 - 14)</option>
                  <option value="3">Week 3 (Tgl 15 - 21)</option>
                  <option value="4" selected>Week 4 (Tgl 22 - 28)</option>
                  <option value="5">Week 5 (Tgl 29 - 31)</option>
                </select>
              </div>

              <!-- Month Selector (if month mode) -->
              <div id="dashFilterMonthContainer" class="hidden flex items-center gap-2">
                <select id="dashSelectMonthOnly" onchange="loadDashboardStockSummary()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none">
                  <option value="1">Januari</option>
                  <option value="2">Februari</option>
                  <option value="3">Maret</option>
                  <option value="4">April</option>
                  <option value="5">Mei</option>
                  <option value="6">Juni</option>
                  <option value="7">Juli</option>
                  <option value="8" selected>Agustus</option>
                  <option value="9">September</option>
                  <option value="10">Oktober</option>
                  <option value="11">November</option>
                  <option value="12">Desember</option>
                </select>
                <select id="dashSelectYearOnly" onchange="loadDashboardStockSummary()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none">
                  <option value="2026" selected>2026</option>
                  <option value="2025">2025</option>
                </select>
              </div>

              <button type="button" onclick="loadDashboardStockSummary()" class="h-[38px] px-3 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold shrink-0" title="Muat Ulang Data">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
                <span>Refresh</span>
              </button>

              <button type="button" onclick="exportDashboardSummaryExcel()" class="h-[38px] px-3.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold shrink-0" title="Export Rekap Stok Dashboard ke File Excel (.xlsx)">
                <span class="material-symbols-outlined text-[18px]">table_chart</span>
                <span>Export Rekap</span>
              </button>
            </div>
          </div>

          <!-- Bottom Row: Active Period & Sub Info -->
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
              <div class="inline-flex items-center gap-2 px-3 h-[34px] bg-emerald-50 text-emerald-900 border border-emerald-200 rounded-xl text-xs font-bold">
                <span class="w-2 h-2 rounded-full bg-emerald-600 animate-pulse"></span>
                <span id="dashActivePeriodBadge">Memuat Periode...</span>
              </div>
            </div>
          </div>

        </div>

        <!-- ================= GRAND SUMMARY KPI CARDS (5 INTERACTIVE MODULES) ================= -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5">
          
          <!-- 1. SISA STOK TOTAL (Clickable -> Master Stok Inventory) -->
          <div onclick="navigateFromDashboard('inventory')" title="Klik untuk membuka Master Stok Kemas" 
            class="bg-gradient-to-br from-emerald-700 to-emerald-900 text-white p-4 rounded-xl shadow-sm relative overflow-hidden flex flex-col justify-between cursor-pointer hover:shadow-md hover:scale-[1.02] active:scale-[0.98] transition-all group select-none">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-200 group-hover:text-white transition-colors">Sisa Stok Total</span>
              <span class="material-symbols-outlined text-emerald-300 text-[22px] group-hover:rotate-12 transition-transform">inventory_2</span>
            </div>
            <div class="mt-2">
              <p id="dashKpiTotalStockUnits" class="text-xl lg:text-2xl font-black tracking-tight text-white">0</p>
              <div class="flex items-center justify-between mt-0.5">
                <span class="text-[10px] text-emerald-200 font-medium">Seluruh fisik gudang</span>
                <span class="material-symbols-outlined text-[14px] text-emerald-300 opacity-0 group-hover:opacity-100 transition-opacity">arrow_forward</span>
              </div>
            </div>
          </div>

          <!-- 2. TOTAL BARANG MASUK (+) (Clickable -> Inbound Tab with Date Filter) -->
          <div onclick="navigateFromDashboard('inbound')" title="Klik untuk membuka Riwayat Barang Masuk sesuai tanggal" 
            class="bg-white p-4 rounded-xl border border-emerald-200 bg-emerald-50/30 shadow-2xs flex flex-col justify-between cursor-pointer hover:shadow-md hover:scale-[1.02] active:scale-[0.98] transition-all group select-none">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-800">Barang Masuk (+)</span>
              <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[18px]">move_to_inbox</span>
              </div>
            </div>
            <div class="mt-2">
              <p id="dashKpiTotalInbound" class="text-xl lg:text-2xl font-black tracking-tight text-emerald-700">0</p>
              <div class="flex items-center justify-between mt-0.5">
                <span class="text-[10px] text-emerald-600 font-medium">Penerimaan periode ini</span>
                <span class="material-symbols-outlined text-[14px] text-emerald-700 opacity-0 group-hover:opacity-100 transition-opacity">arrow_forward</span>
              </div>
            </div>
          </div>

          <!-- 3. TOTAL BARANG KELUAR (-) (Clickable -> Outbound Tab with Date Filter) -->
          <div onclick="navigateFromDashboard('outbound')" title="Klik untuk membuka Riwayat Barang Keluar sesuai tanggal" 
            class="bg-white p-4 rounded-xl border border-rose-200 bg-rose-50/30 shadow-2xs flex flex-col justify-between cursor-pointer hover:shadow-md hover:scale-[1.02] active:scale-[0.98] transition-all group select-none">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-extrabold uppercase tracking-wider text-rose-800">Barang Keluar (-)</span>
              <div class="w-7 h-7 rounded-lg bg-rose-100 text-rose-700 flex items-center justify-center group-hover:bg-rose-600 group-hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[18px]">outbox</span>
              </div>
            </div>
            <div class="mt-2">
              <p id="dashKpiTotalOutbound" class="text-xl lg:text-2xl font-black tracking-tight text-rose-700">0</p>
              <div class="flex items-center justify-between mt-0.5">
                <span class="text-[10px] text-rose-600 font-medium">Pengeluaran periode ini</span>
                <span class="material-symbols-outlined text-[14px] text-rose-700 opacity-0 group-hover:opacity-100 transition-opacity">arrow_forward</span>
              </div>
            </div>
          </div>

          <!-- 4. TOTAL ADJUSTMENT (+/-) (Clickable -> Adjust -> Riwayat Penyesuaian with Date Filter) -->
          <div onclick="navigateFromDashboard('adjust')" title="Klik untuk membuka Tab Riwayat Penyesuaian Stok (Adjust)" 
            class="bg-white p-4 rounded-xl border border-blue-200 bg-blue-50/30 shadow-2xs flex flex-col justify-between cursor-pointer hover:shadow-md hover:scale-[1.02] active:scale-[0.98] transition-all group select-none">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-extrabold uppercase tracking-wider text-blue-800">Adjustment (+/-)</span>
              <div class="w-7 h-7 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[18px]">tune</span>
              </div>
            </div>
            <div class="mt-2">
              <p id="dashKpiTotalAdjustment" class="text-xl lg:text-2xl font-black tracking-tight text-blue-700">0</p>
              <div class="flex items-center justify-between mt-0.5">
                <span id="dashKpiAdjSubtext" class="text-[10px] text-blue-600 font-medium">Penyesuaian stok</span>
                <span class="material-symbols-outlined text-[14px] text-blue-700 opacity-0 group-hover:opacity-100 transition-opacity">arrow_forward</span>
              </div>
            </div>
          </div>

          <!-- 5. STOK KRITIS / MENIPIS (Clickable -> Inventory with Low Stock Filter) -->
          <div onclick="navigateFromDashboard('inventory', 'low')" title="Klik untuk memfilter SKU Stok Menipis & Habis" 
            class="bg-white p-4 rounded-xl border border-amber-200 bg-amber-50/30 shadow-2xs flex flex-col justify-between cursor-pointer hover:shadow-md hover:scale-[1.02] active:scale-[0.98] transition-all group select-none">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-extrabold uppercase tracking-wider text-amber-800">Stok Kritis</span>
              <div class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center group-hover:bg-amber-600 group-hover:text-white transition-colors">
                <span class="material-symbols-outlined text-[18px]">warning</span>
              </div>
            </div>
            <div class="mt-2">
              <p id="dashKpiCriticalStock" class="text-xl lg:text-2xl font-black tracking-tight text-amber-700">0 SKU</p>
              <div class="flex items-center justify-between mt-0.5">
                <span class="text-[10px] text-amber-600 font-medium">≤ Safety / Habis</span>
                <span class="material-symbols-outlined text-[14px] text-amber-700 opacity-0 group-hover:opacity-100 transition-opacity">arrow_forward</span>
              </div>
            </div>
          </div>

        </div>

        <!-- ================= INTERACTIVE CHARTS ROW ================= -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
          
          <!-- Chart 1: Top 10 Inbound / Outbound Bar Chart (Takes 2 cols on Large) -->
          <div class="lg:col-span-2 bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-3">
              <div>
                <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                  <span class="material-symbols-outlined text-emerald-600 text-[20px]">bar_chart</span>
                  <span>Top 10 Kemas/Consumable Teraktif</span>
                </h3>
                <p class="text-[11px] text-slate-400 mt-0.5">Grafik ranking kuantitas barang masuk (+) vs barang keluar (-)</p>
              </div>

              <!-- Chart Switcher Tabs -->
              <div class="inline-flex p-1 bg-slate-100 rounded-lg text-xs font-bold">
                <button type="button" id="btnChartTabIn" onclick="switchDashboardChart('inbound')" 
                  class="py-1 px-3 rounded-md bg-emerald-600 text-white shadow-2xs transition-all">
                  Top 10 Masuk
                </button>
                <button type="button" id="btnChartTabOut" onclick="switchDashboardChart('outbound')" 
                  class="py-1 px-3 rounded-md text-slate-600 hover:text-slate-900 transition-all">
                  Top 10 Keluar
                </button>
              </div>
            </div>

            <!-- Canvas Chart Container -->
            <div class="relative w-full min-h-[330px] h-[340px] mt-4">
              <canvas id="dashBarChartCanvas"></canvas>
            </div>
          </div>

          <!-- Chart 2: Category Distribution Doughnut Chart -->
          <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
            <div class="border-b border-slate-100 pb-3">
              <h3 class="text-sm font-extrabold text-slate-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-indigo-600 text-[20px]">donut_small</span>
                <span>Komposisi Stok per Kategori</span>
              </h3>
              <p class="text-[11px] text-slate-400 mt-0.5">Persentase distribusi fisik stok Kemas/Consumable</p>
            </div>

            <!-- Doughnut Canvas -->
            <div class="relative w-full h-[175px] mt-1 flex items-center justify-center">
              <canvas id="dashCategoryChartCanvas"></canvas>
            </div>

            <!-- Legend summary list (No scroll, all items displayed directly) -->
            <div id="dashCategoryLegendList" class="mt-3 grid grid-cols-2 gap-1.5 text-[11px]"></div>
          </div>

        </div>

        <!-- ================= TOP 10 DATA TABLES (BARANG MASUK & BARANG KELUAR) ================= -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
          
          <!-- TABLE 1: TOP 10 BARANG MASUK -->
          <div class="bg-white rounded-xl border border-emerald-200/80 shadow-sm overflow-hidden flex flex-col">
            <div class="p-4 bg-emerald-50/60 border-b border-emerald-100 flex items-center justify-between">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center shadow-xs">
                  <span class="material-symbols-outlined text-[18px]">move_to_inbox</span>
                </div>
                <div>
                  <h4 class="text-xs font-extrabold text-emerald-950 uppercase tracking-wide">Top 10 Barang Masuk (Inbound)</h4>
                  <p class="text-[11px] text-emerald-700 font-medium">Kemas/Consumable paling banyak diterima ke gudang</p>
                </div>
              </div>
              <span id="dashTopInboundBadge" class="px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-600 text-white shadow-2xs">0 Masuk</span>
            </div>

            <div class="overflow-x-auto flex-1 max-h-[360px] overflow-y-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead class="sticky top-0 bg-slate-100 text-slate-600 text-[10px] font-extrabold uppercase tracking-wider border-b border-slate-200 z-10">
                  <tr>
                    <th class="p-2.5 text-center w-10">Rank</th>
                    <th class="p-2.5 w-28">Item No</th>
                    <th class="p-2.5">Deskripsi Material</th>
                    <th class="p-2.5 text-right font-mono font-bold text-emerald-800">Total Masuk</th>
                    <th class="p-2.5 text-center w-14">Tx</th>
                    <th class="p-2.5 text-right font-mono font-bold text-slate-700">Sisa Stok</th>
                  </tr>
                </thead>
                <tbody id="dashTopInboundTableBody" class="divide-y divide-slate-100 font-medium"></tbody>
              </table>
            </div>
          </div>

          <!-- TABLE 2: TOP 10 BARANG KELUAR -->
          <div class="bg-white rounded-xl border border-rose-200/80 shadow-sm overflow-hidden flex flex-col">
            <div class="p-4 bg-rose-50/60 border-b border-rose-100 flex items-center justify-between">
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-rose-600 text-white flex items-center justify-center shadow-xs">
                  <span class="material-symbols-outlined text-[18px]">outbox</span>
                </div>
                <div>
                  <h4 class="text-xs font-extrabold text-rose-950 uppercase tracking-wide">Top 10 Barang Keluar (Outbound)</h4>
                  <p class="text-[11px] text-rose-700 font-medium">Kemas/Consumable paling banyak dikeluarkan dari gudang</p>
                </div>
              </div>
              <span id="dashTopOutboundBadge" class="px-2.5 py-1 rounded-full text-[10px] font-black bg-rose-600 text-white shadow-2xs">0 Keluar</span>
            </div>

            <div class="overflow-x-auto flex-1 max-h-[360px] overflow-y-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead class="sticky top-0 bg-slate-100 text-slate-600 text-[10px] font-extrabold uppercase tracking-wider border-b border-slate-200 z-10">
                  <tr>
                    <th class="p-2.5 text-center w-10">Rank</th>
                    <th class="p-2.5 w-28">Item No</th>
                    <th class="p-2.5">Deskripsi Material</th>
                    <th class="p-2.5 text-right font-mono font-bold text-rose-800">Total Keluar</th>
                    <th class="p-2.5 text-center w-14">Tx</th>
                    <th class="p-2.5 text-right font-mono font-bold text-slate-700">Sisa Stok</th>
                  </tr>
                </thead>
                <tbody id="dashTopOutboundTableBody" class="divide-y divide-slate-100 font-medium"></tbody>
              </table>
            </div>
          </div>

        </div>

        <!-- ================= MODUL KPI PROSES & PRODUKTIVITAS OPERATOR ================= -->
        <div class="space-y-4 pt-2">
          
          <!-- Header Modul KPI Operator -->
          <div class="flex items-center justify-between border-b border-slate-200/80 pb-2.5">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center shadow-2xs">
                <span class="material-symbols-outlined text-[19px]">badge</span>
              </div>
              <div>
                <h3 class="text-sm font-extrabold text-slate-900 leading-tight">KPI Proses & Produktivitas Operator</h3>
                <p class="text-[11px] text-slate-400 font-medium">Monitoring performa pengerjaan task picking, durasi kerja, dan produktivitas operator</p>
              </div>
            </div>

            <button type="button" onclick="navigateFromDashboard('tasks')" 
              class="h-[34px] px-3.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-800 border border-indigo-200 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold cursor-pointer" title="Buka Daftar Data Task Operator">
              <span class="material-symbols-outlined text-[17px]">format_list_bulleted</span>
              <span>Data Task</span>
            </button>
          </div>

          <!-- 4 Kartu Metrik KPI Operator -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
            <!-- 1. Tingkat Penyelesaian Task -->
            <div onclick="navigateFromDashboard('tasks', 'COMPLETED')" title="Klik untuk lihat task yang sudah selesai" 
              class="bg-white p-4 rounded-xl border border-slate-200 shadow-2xs flex flex-col justify-between cursor-pointer hover:shadow-md hover:scale-[1.02] active:scale-[0.98] transition-all select-none">
              <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500">Penyelesaian Task</span>
                <span class="material-symbols-outlined text-emerald-600 text-[20px]">task_alt</span>
              </div>
              <div class="mt-2">
                <div class="flex items-baseline gap-1.5">
                  <p id="dashKpiOpRate" class="text-xl lg:text-2xl font-black text-emerald-700">0%</p>
                  <span id="dashKpiOpCompletedRatio" class="text-[11px] text-slate-400 font-semibold">(0/0 Selesai)</span>
                </div>
                <div class="w-full bg-slate-100 h-1.5 rounded-full overflow-hidden mt-1.5">
                  <div id="dashKpiOpProgressBar" class="bg-emerald-500 h-full rounded-full transition-all duration-500" style="width: 0%"></div>
                </div>
              </div>
            </div>

            <!-- 2. Task On-Proses -->
            <div onclick="navigateFromDashboard('tasks', 'IN_PROGRESS')" title="Klik untuk filter task yang sedang dikerjakan" 
              class="bg-white p-4 rounded-xl border border-amber-200 bg-amber-50/20 shadow-2xs flex flex-col justify-between cursor-pointer hover:shadow-md hover:scale-[1.02] active:scale-[0.98] transition-all select-none">
              <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-amber-800">Sedang Dikerjakan</span>
                <span class="material-symbols-outlined text-amber-600 text-[20px] animate-spin">sync</span>
              </div>
              <div class="mt-2">
                <p id="dashKpiOpInProgress" class="text-xl lg:text-2xl font-black text-amber-700">0 Task</p>
                <p class="text-[10px] text-amber-600 font-medium mt-0.5">Dalam proses picking di rak</p>
              </div>
            </div>

            <!-- 3. Antrean / Menunggu -->
            <div onclick="navigateFromDashboard('tasks', 'PENDING')" title="Klik untuk filter task yang menunggu dikerjakan" 
              class="bg-white p-4 rounded-xl border border-blue-200 bg-blue-50/20 shadow-2xs flex flex-col justify-between cursor-pointer hover:shadow-md hover:scale-[1.02] active:scale-[0.98] transition-all select-none">
              <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-blue-800">Antrean / Menunggu</span>
                <span class="material-symbols-outlined text-blue-600 text-[20px]">pending_actions</span>
              </div>
              <div class="mt-2">
                <p id="dashKpiOpPending" class="text-xl lg:text-2xl font-black text-blue-700">0 Task</p>
                <p class="text-[10px] text-blue-600 font-medium mt-0.5">Menunggu diambil operator</p>
              </div>
            </div>

            <!-- 4. Rata-rata Durasi Picking -->
            <div onclick="navigateFromDashboard('tasks')" title="Klik untuk membuka riwayat task" 
              class="bg-white p-4 rounded-xl border border-indigo-200 bg-indigo-50/20 shadow-2xs flex flex-col justify-between cursor-pointer hover:shadow-md hover:scale-[1.02] active:scale-[0.98] transition-all select-none">
              <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-800">Rata-rata Durasi</span>
                <span class="material-symbols-outlined text-indigo-600 text-[20px]">timer</span>
              </div>
              <div class="mt-2">
                <p id="dashKpiOpAvgDuration" class="text-xl lg:text-2xl font-black text-indigo-900 font-mono">00:00</p>
                <p class="text-[10px] text-indigo-600 font-medium mt-0.5">Waktu pengerjaan per task</p>
              </div>
            </div>
          </div>

          <!-- Leaderboard Operator & Realtime Task Stream Grid -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            
            <!-- Leaderboard Kinerja Operator -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
              <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="material-symbols-outlined text-indigo-600 text-[18px]">leaderboard</span>
                  <span class="text-xs font-extrabold text-slate-800 uppercase tracking-wide">Peringkat Produktivitas Operator</span>
                </div>
                <span class="text-[10px] text-slate-400 font-medium">Berdasarkan task selesai</span>
              </div>

              <div class="overflow-x-auto flex-1 max-h-[280px] overflow-y-auto">
                <table class="w-full text-left border-collapse text-xs">
                  <thead class="sticky top-0 bg-slate-100 text-slate-600 text-[10px] font-extrabold uppercase tracking-wider border-b border-slate-200 z-10">
                    <tr>
                      <th class="p-2.5 text-center w-10">Rank</th>
                      <th class="p-2.5">Nama Operator</th>
                      <th class="p-2.5 text-center w-24">Task Selesai</th>
                      <th class="p-2.5 text-right font-mono font-bold text-slate-800">Total Qty Kemas</th>
                      <th class="p-2.5 text-center font-mono w-24">Avg Waktu</th>
                    </tr>
                  </thead>
                  <tbody id="dashOpLeaderboardBody" class="divide-y divide-slate-100 font-medium"></tbody>
                </table>
              </div>
            </div>

            <!-- Feed Status Pengerjaan Task Terkini -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
              <div class="p-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <span class="material-symbols-outlined text-amber-600 text-[18px]">stream</span>
                  <span class="text-xs font-extrabold text-slate-800 uppercase tracking-wide">Status Antrean & Progress Penugasan</span>
                </div>
                <span class="text-[10px] text-slate-400 font-medium">Real-time task feed</span>
              </div>

              <div class="overflow-x-auto flex-1 max-h-[280px] overflow-y-auto">
                <table class="w-full text-left border-collapse text-xs">
                  <thead class="sticky top-0 bg-slate-100 text-slate-600 text-[10px] font-extrabold uppercase tracking-wider border-b border-slate-200 z-10">
                    <tr>
                      <th class="p-2.5 w-24">No. Task</th>
                      <th class="p-2.5">Kemas & Rak</th>
                      <th class="p-2.5 text-center font-mono w-20">Target</th>
                      <th class="p-2.5 w-28">Operator PIC</th>
                      <th class="p-2.5 text-center w-24">Status</th>
                    </tr>
                  </thead>
                  <tbody id="dashOpRecentTasksBody" class="divide-y divide-slate-100 font-medium"></tbody>
                </table>
              </div>
            </div>

          </div>

        </div>

      </div>

      <!-- ================= 2. TAB: MASTER STOK / INVENTORY ================= -->
      <div id="tab-inventory" class="hidden space-y-4">
        <!-- Control Bar -->
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2 flex-1">
            <div class="relative flex-1 min-w-[200px] max-w-md">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">
                <span class="material-symbols-outlined text-[18px]">search</span>
              </span>
              <input type="text" id="inventorySearch" oninput="loadMaterials()" placeholder="Cari Item No, nama material, lokasi rak..." 
                class="w-full h-[38px] pl-9 pr-3 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-emerald-600 focus:bg-white transition-colors">
            </div>

            <select id="inventoryCategoryFilter" onchange="loadMaterials()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-700 outline-none">
              <option value="all">Semua Kategori</option>
            </select>

            <select id="inventoryStatusFilter" onchange="loadMaterials()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-700 outline-none">
              <option value="all">Semua Status Stok</option>
              <option value="low">Menipis (&le; Min Stock)</option>
              <option value="empty">Habis (0 Stock)</option>
              <option value="safe">Aman</option>
            </select>
          </div>

          <!-- Action Buttons (Uniform 38px Height) -->
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <button onclick="loadMaterials()" class="h-[38px] px-3 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Refresh Data Master">
              <span class="material-symbols-outlined text-[18px]">refresh</span>
              <span>Refresh</span>
            </button>

            <a href="export.php?type=inventory_template" target="_blank" class="h-[38px] px-3 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Download Format File Excel (.xlsx) Resmi & Rapi">
              <span class="material-symbols-outlined text-[18px] text-emerald-700">download</span>
              <span>Template Excel</span>
            </a>

            <button onclick="openExcelImportModal()" class="h-[38px] px-3.5 rounded-lg bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Upload File Excel/CSV Stok Awal">
              <span class="material-symbols-outlined text-[18px] text-emerald-700">upload_file</span>
              <span>Import Excel</span>
            </button>

            <a href="export.php?type=all_materials" target="_blank" class="h-[38px] px-3.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Export Master Stok ke File Excel (.xlsx)">
              <span class="material-symbols-outlined text-[18px]">table_chart</span>
              <span>Export Master</span>
            </a>

            <button onclick="openAddMaterialModal()" class="h-[38px] px-3.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Tambah Material Packaging Baru">
              <span class="material-symbols-outlined text-[18px]">add_circle</span>
              <span>Tambah Material</span>
            </button>
          </div>
        </div>

        <!-- Inventory Master Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-700">
                <tr>
                  <th class="p-3 border-r border-white/20">Item No</th>
                  <th class="p-3 border-r border-white/20">Item Description</th>
                  <th class="p-3 border-r border-white/20">Kategori</th>
                  <th class="p-3 text-center border-r border-white/20">Stok Awal</th>
                  <th class="p-3 text-center border-r border-white/20 font-bold">Total Masuk (+)</th>
                  <th class="p-3 text-center border-r border-white/20 font-bold">Total Keluar (-)</th>
                  <th class="p-3 text-center font-black border-r border-white/20">Sisa Stok Akhir</th>
                  <th class="p-3 text-center border-r border-white/20">Satuan (UOM)</th>
                  <th class="p-3 border-r border-white/20">Lokasi Rak</th>
                  <th class="p-3 border-r border-white/20">Status</th>
                  <th class="p-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody id="inventoryTableBody" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ================= 2.1 TAB / VIEW: KARTU STOK & RIWAYAT KELUAR MASUK (EMBEDDED IN INDEX) ================= -->
      <div id="tab-history" class="hidden space-y-5">
        <!-- History Top Control Bar -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <button type="button" onclick="switchAdminTab('inventory')" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors inline-flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[18px]">arrow_back</span>
              <span>Kembali ke Master Stok</span>
            </button>
            <div class="h-5 w-px bg-slate-200 hidden sm:block"></div>
            <div>
              <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Modul Master Stok</span>
              <h2 class="font-black text-sm text-slate-900 flex items-center gap-1.5">
                <span class="text-emerald-800">Kartu Stok Terintegrasi</span>
              </h2>
            </div>
          </div>

          <!-- History Action Buttons (Download & Print) -->
          <div class="flex items-center gap-2 no-print">
            <a id="viewHistDownloadBtn" href="#" target="_blank" class="h-[38px] px-3.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition-colors inline-flex items-center gap-1.5" title="Export Riwayat ke Excel (.xlsx)">
              <span class="material-symbols-outlined text-[18px]">table_chart</span>
              <span>Export History Excel</span>
            </a>

            <button type="button" onclick="printStockCard()" class="h-[38px] px-3.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-2xs transition-colors inline-flex items-center gap-1.5" title="Cetak Kartu Stok">
              <span class="material-symbols-outlined text-[18px]">print</span>
              <span>Cetak Kartu Stok</span>
            </button>
          </div>
        </div>

        <!-- 1. Header Information Card -->
        <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div class="flex items-start gap-3.5">
            <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold flex-shrink-0 border border-emerald-200">
              <span class="material-symbols-outlined text-[28px]">inventory_2</span>
            </div>
            <div>
              <div class="flex flex-wrap items-center gap-2 mb-1">
                <span id="viewHistBadgeCode" class="font-mono font-black text-sm px-2.5 py-0.5 rounded bg-emerald-50 text-emerald-900 border border-emerald-300"></span>
                <span id="viewHistBadgeCategory" class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700"></span>
                <span id="viewHistBadgeStatus"></span>
              </div>
              <h3 id="viewHistHeaderName" class="text-base sm:text-lg font-bold text-slate-900"></h3>
            </div>
          </div>

          <!-- Quick Specs -->
          <div class="flex flex-wrap items-center gap-4 text-xs border-t md:border-t-0 md:border-l border-slate-100 pt-3 md:pt-0 md:pl-6 text-slate-600">
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Lokasi Rak Simpan</span>
              <span id="viewHistRack" class="font-bold text-slate-800 inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px] text-emerald-600">location_on</span>
                <span></span>
              </span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Min Safety Stock</span>
              <span id="viewHistMinStock" class="font-bold text-slate-800"></span>
            </div>
          </div>
        </div>

        <!-- 2. Stock Formula Breakdown (4 KPI Cards) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 text-xs">
          <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm">
            <p class="text-[10px] uppercase font-bold text-slate-400">1. Stok Awal (Upload)</p>
            <h4 id="viewHistInitialStock" class="text-xl sm:text-2xl font-black text-slate-800 mt-1">0</h4>
            <p class="text-[10px] text-slate-400 mt-0.5">Stok awal dari file Excel</p>
          </div>

          <div class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm bg-emerald-50/20">
            <p class="text-[10px] uppercase font-bold text-emerald-700">2. Total Barang Masuk (+)</p>
            <h4 id="viewHistTotalInbound" class="text-xl sm:text-2xl font-black text-emerald-700 mt-1">+0</h4>
            <p class="text-[10px] text-emerald-600 mt-0.5">Akumulasi penerimaan PO</p>
          </div>

          <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm bg-amber-50/20">
            <p class="text-[10px] uppercase font-bold text-amber-700">3. Total Barang Keluar (-)</p>
            <h4 id="viewHistTotalOutbound" class="text-xl sm:text-2xl font-black text-amber-700 mt-1">-0</h4>
            <p class="text-[10px] text-amber-600 mt-0.5">Picking Line & Pengeluaran Manual</p>
          </div>

          <div id="viewHistStockBox" class="p-4 rounded-xl shadow-sm text-white bg-emerald-600">
            <p class="text-[10px] uppercase font-bold text-emerald-100">4. Sisa Stok Akhir</p>
            <h4 id="viewHistCurrentStock" class="text-xl sm:text-2xl font-black text-white mt-1">0</h4>
            <p class="text-[10px] text-emerald-100 mt-0.5">Sisa stok aktual fisik di gudang</p>
          </div>
        </div>

        <!-- 3. Chronological Transactions Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden p-5 space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
              <h4 class="font-bold text-slate-900 text-xs uppercase tracking-wider">History Movement Stock</h4>
            </div>
            <span id="viewHistRowCountBadge" class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-slate-100 text-slate-700"></span>
          </div>

          <div class="overflow-x-auto border border-slate-200 rounded-xl overflow-hidden">
            <table class="w-full text-left border-collapse">
              <thead class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-700">
                <tr>
                  <th class="p-3 border-r border-white/20">Waktu Transaksi</th>
                  <th class="p-3 border-r border-white/20">Tipe Mutasi</th>
                  <th class="p-3 border-r border-white/20">No. Referensi (PO / Task)</th>
                  <th class="p-3 text-center border-r border-white/20 font-bold">Masuk (+)</th>
                  <th class="p-3 text-center border-r border-white/20 font-bold">Keluar (-)</th>
                  <th class="p-3 text-center font-black border-r border-white/20">Sisa Stok</th>
                  <th class="p-3 border-r border-white/20">Keterangan & Catatan</th>
                  <th class="p-3">Petugas PIC</th>
                </tr>
              </thead>
              <tbody id="viewHistTableBody" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
          </div>
        </div>

        <!-- 4. Official Print Signature Section (Visible on Print) -->
        <div class="hidden print:block pt-8 pb-4">
          <div class="grid grid-cols-3 gap-6 text-center text-xs">
            <div>
              <p class="text-slate-500 mb-14">Disiapkan Oleh (PIC Gudang):</p>
              <div class="border-b border-slate-400 w-40 mx-auto"></div>
              <p class="font-bold text-slate-800 mt-1"><?= htmlspecialchars(Auth::name() ?: 'Administrator') ?></p>
              <p class="text-[10px] text-slate-500">Warehouse Staff</p>
            </div>
            <div>
              <p class="text-slate-500 mb-14">Diverifikasi Oleh (Supervisor):</p>
              <div class="border-b border-slate-400 w-40 mx-auto"></div>
              <p class="font-bold text-slate-800 mt-1">( ........................................ )</p>
              <p class="text-[10px] text-slate-500">Warehouse Supervisor</p>
            </div>
            <div>
              <p class="text-slate-500 mb-14">Diketahui Oleh (Kepala Gudang):</p>
              <div class="border-b border-slate-400 w-40 mx-auto"></div>
              <p class="font-bold text-slate-800 mt-1">( ........................................ )</p>
              <p class="text-[10px] text-slate-500">Head of Supply Chain</p>
            </div>
          </div>
          <div class="mt-6 text-center text-[10px] text-slate-400 border-t border-slate-200 pt-2">
            Dokumen resmi dicetak dari Sistem PackStock WMS &bull; Tanggal Cetak: <?= date('d/m/Y H:i:s') ?> WIB
          </div>
        </div>
      </div>
      <!-- ================= 2.2 TAB: DYNAMIC COUNTING (PER SKU PILIHAN) ================= -->
      <div id="tab-dynamic_count" class="hidden space-y-3">

        <!-- Single-Line Clean Action Toolbar -->
        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-2.5">
          <div class="flex flex-wrap items-center gap-2 flex-1">
            <!-- Session Selector -->
            <div class="flex items-center gap-1.5 min-w-[220px] max-w-[280px]">
              <span class="material-symbols-outlined text-indigo-600 text-[18px] shrink-0">folder_open</span>
              <select id="dynamicOpnameSelect" onchange="loadDynamicMatrix()" class="w-full h-[38px] px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-300 rounded-lg text-xs font-bold text-slate-800 outline-none focus:border-indigo-600 focus:bg-white cursor-pointer transition-colors">
                <option value="0">Semua Sesi</option>
              </select>
            </div>

            <!-- Date Filter -->
            <div class="premium-datepicker-wrapper">
              <span class="material-symbols-outlined picker-icon text-indigo-600">calendar_today</span>
              <input type="text" id="dynamicDateFilter" placeholder="Filter Tanggal..." class="premium-datepicker-input px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-indigo-600 focus:bg-white" title="Filter Tanggal">
            </div>

            <!-- Search Input -->
            <div class="relative flex-1 min-w-[160px] max-w-xs">
              <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400 pointer-events-none">
                <span class="material-symbols-outlined text-[16px]">search</span>
              </span>
              <input type="text" id="dynamicSearchInput" oninput="loadDynamicMatrix()" placeholder="Cari SKU, Nama, Rak..." 
                class="w-full h-[38px] pl-8 pr-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-indigo-600 focus:bg-white transition-colors">
            </div>

            <!-- Filter Note -->
            <select id="dynamicNoteFilter" onchange="loadDynamicMatrix()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-indigo-600">
              <option value="ALL">Semua Note</option>
              <option value="DIFF_ONLY">Hanya Selisih (&lt;&gt;0)</option>
              <option value="PLUS">Note: Plus (+)</option>
              <option value="MINUS">Note: Minus (-)</option>
              <option value="BALANCE">Note: Balance (0)</option>
              <option value="PENDING">Belum Dihitung</option>
            </select>

            <button type="button" onclick="loadDynamicMatrix()" class="h-[38px] px-3 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold shrink-0" title="Refresh Data Dynamic Count">
              <span class="material-symbols-outlined text-[18px]">refresh</span>
              <span>Refresh</span>
            </button>
          </div>

          <!-- Action Buttons Right (Uniform 38px Height) -->
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <!-- Selected Recount Badge -->
            <span id="dynamicSelectedBadge" class="hidden px-2.5 h-[38px] rounded-lg text-xs font-bold bg-purple-100 text-purple-900 border border-purple-300 flex items-center">
              0 SKU Dipilih
            </span>

            <button type="button" id="btnAssignDynamicRecount" onclick="openAssignDynamicRecountModal()" class="h-[38px] px-3.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Tugaskan Hitung Ulang (Recount) ke Operator">
              <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
              <span>Tugaskan Recount</span>
            </button>

            <button type="button" onclick="exportDynamicExcel()" class="h-[38px] px-3.5 rounded-lg bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-300 transition-colors flex items-center gap-1.5 text-xs font-bold shadow-2xs" title="Download Excel Hasil Dynamic Count (.xlsx)">
              <span class="material-symbols-outlined text-[18px] text-emerald-700">table_chart</span>
              <span>Export Matrix</span>
            </button>

            <button type="button" onclick="openCreateDynamicCountModal()" class="h-[38px] px-3.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Buat Penugasan Dynamic Counting SKU Baru">
              <span class="material-symbols-outlined text-[18px]">add_circle</span>
              <span>Buat Sesi Baru</span>
            </button>
          </div>
        </div>

        <!-- Dynamic Counting Product Matrix Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead id="dynamicItemsTableHead" class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-950"></thead>
              <tbody id="dynamicItemsTableBody" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- ================= 2.3 TAB: STOCK OPNAME (BLANK COUNT & RECOUNT) ================= -->
      <div id="tab-opname" class="hidden space-y-3">

        <!-- Single-Line Clean Action Toolbar -->
        <div class="bg-white p-3 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-2.5">
          <div class="flex flex-wrap items-center gap-2 flex-1">
            <!-- Session Selector -->
            <div class="flex items-center gap-1.5 min-w-[220px] max-w-[280px]">
              <span class="material-symbols-outlined text-emerald-600 text-[18px] shrink-0">folder_open</span>
              <select id="opnameSelectSession" onchange="loadOpnameMatrix()" class="w-full h-[38px] px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-300 rounded-lg text-xs font-bold text-slate-800 outline-none focus:border-emerald-600 focus:bg-white cursor-pointer transition-colors">
                <option value="0">Semua Sesi</option>
              </select>
            </div>

            <!-- Date Filter -->
            <div class="premium-datepicker-wrapper">
              <span class="material-symbols-outlined picker-icon text-emerald-700">calendar_today</span>
              <input type="text" id="opnameDateFilter" placeholder="Filter Tanggal..." class="premium-datepicker-input px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-emerald-600 focus:bg-white" title="Filter Tanggal">
            </div>

            <!-- Search Input -->
            <div class="relative flex-1 min-w-[160px] max-w-xs">
              <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400 pointer-events-none">
                <span class="material-symbols-outlined text-[16px]">search</span>
              </span>
              <input type="text" id="opnameSearchInput" oninput="loadOpnameMatrix()" placeholder="Cari SKU, Nama, Rak..." 
                class="w-full h-[38px] pl-8 pr-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-emerald-600 focus:bg-white transition-colors">
            </div>

            <!-- Filter Note -->
            <select id="opnameNoteFilter" onchange="loadOpnameMatrix()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-emerald-600">
              <option value="ALL">Semua Note</option>
              <option value="DIFF_ONLY">Hanya Selisih (&lt;&gt;0)</option>
              <option value="PLUS">Note: Plus (+)</option>
              <option value="MINUS">Note: Minus (-)</option>
              <option value="BALANCE">Note: Balance (0)</option>
              <option value="PENDING">Belum Dihitung</option>
            </select>

            <button type="button" onclick="loadOpnameMatrix()" class="h-[38px] px-3 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold shrink-0" title="Refresh Data">
              <span class="material-symbols-outlined text-[18px]">refresh</span>
              <span>Refresh</span>
            </button>
          </div>

          <!-- Action Buttons Right (Uniform 38px Height) -->
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <!-- Selected Recount Badge -->
            <span id="opnameSelectedBadge" class="hidden px-2.5 h-[38px] rounded-lg text-xs font-bold bg-purple-100 text-purple-900 border border-purple-300 flex items-center">
              0 Dipilih
            </span>

            <button type="button" id="btnAssignOpnameRecount" onclick="openAssignRecountModal()" class="h-[38px] px-3.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Tugaskan Hitung Ulang (Recount) ke Operator">
              <span class="material-symbols-outlined text-[18px]">how_to_reg</span>
              <span>Tugaskan Recount</span>
            </button>

            <button type="button" onclick="exportCurrentOpnameExcel()" class="h-[38px] px-3.5 rounded-lg bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-300 transition-colors flex items-center gap-1.5 text-xs font-bold shadow-2xs" title="Download Excel Hasil Stock Opname (.xlsx)">
              <span class="material-symbols-outlined text-[18px] text-emerald-700">table_chart</span>
              <span>Export Hasil</span>
            </button>

            <button type="button" onclick="openCreateStockOpnameModal()" class="h-[38px] px-3.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Mulai Sesi Stock Opname Baru">
              <span class="material-symbols-outlined text-[18px]">add_circle</span>
              <span>Mulai Sesi Baru</span>
            </button>
          </div>
        </div>

        <!-- Stock Opname Product Matrix Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead id="opnameItemsTableHead" class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-950"></thead>
              <tbody id="opnameItemsTableBody" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- ================= 2.3.B TAB: DETAIL HASIL COUNTING (LOG BREAKDOWN PER PUTARAN) ================= -->
      <div id="tab-counting_detail" class="hidden space-y-4">

        <!-- Single-Line Clean Action & Filter Toolbar -->
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2.5 flex-1">
            <!-- Session / Document Selector -->
            <div class="flex items-center gap-1.5 min-w-[240px] max-w-[300px]">
              <span class="material-symbols-outlined text-teal-600 text-[19px] shrink-0">folder_open</span>
              <select id="cdFilterSession" onchange="loadCountingDetails()" class="w-full h-[38px] px-2.5 bg-slate-50 hover:bg-slate-100 border border-slate-300 rounded-lg text-xs font-bold text-slate-800 outline-none focus:border-teal-600 focus:bg-white cursor-pointer transition-colors">
                <option value="0">Semua Dokumen Sesi</option>
              </select>
            </div>

            <!-- Date Filter -->
            <div class="premium-datepicker-wrapper">
              <span class="material-symbols-outlined picker-icon text-teal-700">calendar_today</span>
              <input type="text" id="cdFilterDate" placeholder="Filter Tanggal..." class="premium-datepicker-input px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-teal-600 focus:bg-white" title="Filter Tanggal">
            </div>

            <!-- Round / Putaran Filter -->
            <select id="cdFilterStage" onchange="loadCountingDetails()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-teal-600">
              <option value="0">Semua Putaran (Round)</option>
              <option value="1">1st Count</option>
              <option value="2">2nd Count</option>
              <option value="3">3rd Count</option>
              <option value="4">4th Count</option>
            </select>

            <!-- Search Input -->
            <div class="relative flex-1 min-w-[180px] max-w-xs">
              <span class="absolute inset-y-0 left-0 pl-2.5 flex items-center text-slate-400 pointer-events-none">
                <span class="material-symbols-outlined text-[16px]">search</span>
              </span>
              <input type="text" id="cdSearchInput" oninput="loadCountingDetails()" placeholder="Cari Dokumen, SKU, Nama, Rak, PIC..." 
                class="w-full h-[38px] pl-8 pr-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-teal-600 focus:bg-white transition-colors">
            </div>

            <button type="button" onclick="loadCountingDetails()" class="h-[38px] px-3 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold shrink-0" title="Refresh Data">
              <span class="material-symbols-outlined text-[18px]">refresh</span>
              <span>Refresh</span>
            </button>
          </div>

          <!-- Action Buttons Right (Export Excel) -->
          <div class="flex items-center gap-2 shrink-0">
            <button type="button" onclick="exportCountingDetailExcel()" class="h-[38px] px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Download Excel Log Detail Stock Opname (.xlsx)">
              <span class="material-symbols-outlined text-[18px]">table_chart</span>
              <span>Download Excel</span>
            </button>
          </div>
        </div>

        <!-- 4 KPI Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5">
          <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-700 flex items-center justify-center font-bold flex-shrink-0 border border-teal-100">
              <span class="material-symbols-outlined text-[22px]">format_list_numbered</span>
            </div>
            <div>
              <div class="text-[10px] uppercase font-bold text-slate-400">Total Data Count</div>
              <div id="cdStatTotalRecords" class="text-base sm:text-lg font-black text-slate-900">0 Data</div>
            </div>
          </div>

          <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center font-bold flex-shrink-0 border border-emerald-100">
              <span class="material-symbols-outlined text-[22px]">pin</span>
            </div>
            <div>
              <div class="text-[10px] uppercase font-bold text-slate-400">Total Qty Dihitung</div>
              <div id="cdStatTotalQty" class="text-base sm:text-lg font-black text-emerald-800">0 Pcs</div>
            </div>
          </div>

          <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-700 flex items-center justify-center font-bold flex-shrink-0 border border-indigo-100">
              <span class="material-symbols-outlined text-[22px]">category</span>
            </div>
            <div>
              <div class="text-[10px] uppercase font-bold text-slate-400">Total SKU Terhitung</div>
              <div id="cdStatTotalSku" class="text-base sm:text-lg font-black text-indigo-900">0 SKU</div>
            </div>
          </div>

          <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center font-bold flex-shrink-0 border border-amber-100">
              <span class="material-symbols-outlined text-[22px]">folder</span>
            </div>
            <div>
              <div class="text-[10px] uppercase font-bold text-slate-400">Dokumen Sesi</div>
              <div id="cdStatTotalSessions" class="text-base sm:text-lg font-black text-amber-900">0 Dokumen</div>
            </div>
          </div>
        </div>

        <!-- Detail Stock Opname Data Table -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-950">
                <tr>
                  <th class="p-3 text-center w-12 border-r border-white/20">No</th>
                  <th class="p-3 border-r border-white/20">No. Dokumen Sesi</th>
                  <th class="p-3 border-r border-white/20">Tanggal & Waktu</th>
                  <th class="p-3 text-center border-r border-white/20">Round</th>
                  <th class="p-3 border-r border-white/20">Item No</th>
                  <th class="p-3 border-r border-white/20">Deskripsi Product</th>
                  <th class="p-3 text-center border-r border-white/20">Satuan</th>
                  <th class="p-3 text-center border-r border-white/20 font-black">Qty Count</th>
                  <th class="p-3 border-r border-white/20">Lokasi Rak (Scan)</th>
                  <th class="p-3 border-r border-white/20">PIC Operator</th>
                  <th class="p-3 text-center border-r border-white/20">Status</th>
                  <th class="p-3">Catatan Fisik</th>
                </tr>
              </thead>
              <tbody id="countingDetailTableBody" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- ================= 2.4 TAB: UPLOAD ADJUSTMENT STOK (CLEAN DATA TABLE & UPLOAD) ================= -->
      <div id="tab-adjust" class="hidden space-y-4">

        <!-- Sub-Tab Segmented Switcher for Adjust Module -->
        <div class="flex items-center justify-between flex-wrap gap-3">
          <div class="inline-flex p-1 bg-slate-100/90 rounded-xl border border-slate-200 text-xs font-bold shadow-2xs">
            <button type="button" id="btnSubTabAdjustForm" onclick="switchAdjustSubTab('form')" 
              class="py-2 px-3.5 rounded-lg flex items-center gap-1.5 bg-amber-600 text-white shadow-xs transition-all">
              <span class="material-symbols-outlined text-[17px]">tune</span>
              <span>Form Penyesuaian (+ / -)</span>
            </button>
            <button type="button" id="btnSubTabAdjustHistory" onclick="switchAdjustSubTab('history')" 
              class="py-2 px-3.5 rounded-lg flex items-center gap-1.5 text-slate-600 hover:text-slate-900 transition-all">
              <span class="material-symbols-outlined text-[17px]">history</span>
              <span>Riwayat Penyesuaian (Log)</span>
            </button>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-[11px] text-slate-500 font-medium">Modul Penyesuaian Master Stok (+ / -)</span>
          </div>
        </div>

        <!-- ================= SUBTAB 1: FORM PENYESUAIAN STOK & UPLOAD EXCEL ================= -->
        <div id="adjust-subtab-form" class="space-y-4">
          <!-- Clean Action Toolbar -->
          <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2 flex-1">
              <!-- Search SKU / Nama -->
              <div class="relative flex-1 min-w-[200px] max-w-sm">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px]">search</span>
                </span>
                <input type="text" id="directAdjustSearchInput" oninput="renderDirectAdjustTable()" placeholder="Cari SKU, Nama Material, Rak..." 
                  class="w-full h-[38px] pl-9 pr-3 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-amber-600 focus:bg-white transition-colors">
              </div>

              <!-- Filter Status -->
              <select id="directAdjustFilterSelect" onchange="renderDirectAdjustTable()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-amber-600">
                <option value="ALL">Semua Material</option>
                <option value="IMPORTED">Hanya Dari File Excel (Import)</option>
                <option value="ADJUSTED_ONLY">Hanya Yang Ada Adjust (+/-)</option>
                <option value="PLUS">Hanya Plus (+ Tambah)</option>
                <option value="MINUS">Hanya Minus (- Potong)</option>
              </select>

              <div class="premium-datepicker-wrapper">
                <span class="material-symbols-outlined picker-icon text-amber-600">event_available</span>
                <input type="text" id="directAdjustDateInput" value="<?= date('Y-m-d') ?>" placeholder="Tanggal Adjust..." 
                  class="premium-datepicker-input px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-amber-600" title="Tanggal Pencatatan Penyesuaian">
              </div>

              <button type="button" onclick="loadDirectAdjustMaterials()" class="h-[38px] px-3 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold shrink-0" title="Refresh Data">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
                <span>Refresh</span>
              </button>
            </div>

            <!-- Right: Action Buttons (Uniform 38px Height) -->
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <!-- Hidden File Input for Excel Upload -->
              <input type="file" id="directAdjustFileInput" accept=".xlsx,.xls,.csv" class="hidden" onchange="handleDirectExcelUpload(this)">

              <!-- Download Template Button -->
              <a href="export.php?type=adjust_template" class="h-[38px] px-3 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Download Format File Excel (.xlsx) Resmi & Rapi">
                <span class="material-symbols-outlined text-[18px]">download</span>
                <span>Template</span>
              </a>

              <!-- Upload Excel Button -->
              <button type="button" onclick="document.getElementById('directAdjustFileInput').click()" class="h-[38px] px-3.5 rounded-lg bg-white hover:bg-amber-50 text-amber-900 border border-amber-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Upload File Excel / CSV Hasil Opname untuk Penyesuaian">
                <span class="material-symbols-outlined text-[18px] text-amber-700">upload_file</span>
                <span>Import Excel</span>
              </button>

              <!-- Commit Adjustment Button -->
              <button type="button" id="btnCommitDirectAdjust" onclick="commitDirectAdjustTable()" class="h-[38px] px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold opacity-50 cursor-not-allowed" disabled title="Terapkan Selisih Penyesuaian ke Master Stok">
                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                <span>Terapkan Adjust</span>
              </button>

              <!-- Reset Button -->
              <button type="button" onclick="resetDirectAdjustTable()" class="h-[38px] px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-semibold" title="Bersihkan Angka Penyesuaian">
                <span class="material-symbols-outlined text-[18px]">clear_all</span>
                <span>Reset</span>
              </button>
            </div>
          </div>

          <!-- Info / Summary Strip & Guidance -->
          <div id="directAdjustSummaryStrip" class="bg-amber-50/80 border border-amber-200/90 px-4 py-3 rounded-xl flex flex-col md:flex-row md:items-center justify-between text-xs text-amber-950 gap-3">
            <div class="space-y-1">
              <div class="flex items-center gap-1.5 font-bold text-amber-900">
                <span class="material-symbols-outlined text-amber-700 text-[18px]">info</span>
                <span>Panduan Nilai Qty Adjust (+ / -):</span>
              </div>
              <p class="text-[11px] text-slate-600 leading-relaxed">
                &bull; <b class="text-blue-700 font-bold">Nambah Stok:</b> Masukkan angka positif (contoh: <code class="bg-blue-50 px-1 py-0.5 rounded border border-blue-200 font-bold text-blue-800">+100</code>). Stok baru akan bertambah.<br>
                &bull; <b class="text-rose-700 font-bold">Potong / Kurang Stok:</b> Beri tanda minus di depan (contoh: <code class="bg-rose-50 px-1 py-0.5 rounded border border-rose-200 font-bold text-rose-800">-25</code>). Stok baru akan berkurang.<br>
                &bull; <b class="text-slate-700 font-bold">Tidak Berubah:</b> Biarkan <code class="bg-slate-100 px-1 py-0.5 rounded border font-mono">0</code> atau kosong.
              </p>
            </div>
            <div id="directAdjustStatsCounters" class="flex items-center gap-2 font-bold shrink-0 self-start md:self-auto">
              <span class="px-2.5 h-[32px] rounded-lg bg-white border border-amber-300 text-slate-800 shadow-2xs flex items-center" id="statAdjustTotalSku">0 SKU Terdaftar</span>
              <span class="px-2.5 h-[32px] rounded-lg bg-emerald-100 border border-emerald-300 text-emerald-900 shadow-2xs flex items-center" id="statAdjustReadyCount">0 Siap Adjust</span>
            </div>
          </div>

          <!-- Main Data Table for Adjustment -->
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto max-h-[calc(100vh-280px)] overflow-y-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead class="sticky top-0 thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-700 z-10">
                  <tr>
                    <th class="p-3 text-center w-12 border-r border-white/20">No</th>
                    <th class="p-3 w-36 border-r border-white/20">Item No</th>
                    <th class="p-3 border-r border-white/20">Deskripsi Packaging</th>
                    <th class="p-3 text-center w-20 border-r border-white/20">Satuan</th>
                    <th class="p-3 w-28 border-r border-white/20">Lokasi Rak</th>
                    <th class="p-3 text-center w-28 font-mono border-r border-white/20">Stok Sistem</th>
                    <th class="p-3 text-center w-36 font-black border-r border-white/20">Qty Adjust (+ / -)</th>
                    <th class="p-3 text-center w-32 font-mono font-black border-r border-white/20">Stok Baru</th>
                    <th class="p-3 min-w-[200px] border-r border-white/20">Alasan / Catatan Penyesuaian</th>
                    <th class="p-3 text-center w-28">Status</th>
                  </tr>
                </thead>
                <tbody id="directAdjustTableBody" class="divide-y divide-slate-100 font-medium"></tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ================= SUBTAB 2: RIWAYAT PENYESUAIAN STOK (LOG) ================= -->
        <div id="adjust-subtab-history" class="hidden space-y-4">
          <!-- Toolbar History -->
          <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2 flex-1">
              <div class="relative flex-1 min-w-[180px] max-w-sm">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px]">search</span>
                </span>
                <input type="text" id="adjustHistorySearchInput" oninput="renderAdjustHistoryTable()" placeholder="Cari No Referensi, SKU, Catatan..." 
                  class="w-full h-[38px] pl-9 pr-3 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-amber-600 focus:bg-white transition-colors">
              </div>

              <div class="premium-datepicker-wrapper">
                <span class="material-symbols-outlined picker-icon text-amber-600">calendar_today</span>
                <input type="text" id="adjustHistoryDateFilter" onchange="renderAdjustHistoryTable()" placeholder="Filter Tanggal..." 
                  class="premium-datepicker-input px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-amber-600" title="Filter Tanggal Penyesuaian">
              </div>

              <button type="button" onclick="loadAdjustHistory()" class="h-[38px] px-3 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold shrink-0" title="Refresh Riwayat">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
                <span>Refresh</span>
              </button>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <button type="button" onclick="exportAdjustHistoryExcel()" class="h-[38px] px-3.5 rounded-lg bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-300 transition-colors flex items-center gap-1.5 text-xs font-bold shadow-2xs" title="Download Riwayat Penyesuaian ke Excel (.xlsx)">
                <span class="material-symbols-outlined text-[18px] text-emerald-700">table_chart</span>
                <span>Export Log Adjust</span>
              </button>
            </div>
          </div>

          <!-- History Table -->
          <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto max-h-[calc(100vh-280px)] overflow-y-auto">
              <table class="w-full text-left border-collapse text-xs">
                <thead class="sticky top-0 thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-700 z-10">
                  <tr>
                    <th class="p-3 text-center w-12 border-r border-white/20">No</th>
                    <th class="p-3 w-36 border-r border-white/20">Waktu</th>
                    <th class="p-3 w-40 border-r border-white/20">No Referensi</th>
                    <th class="p-3 border-r border-white/20">Material Packaging</th>
                    <th class="p-3 w-28 border-r border-white/20">Lokasi Rak</th>
                    <th class="p-3 text-center w-24 border-r border-white/20">Stok Sebelum</th>
                    <th class="p-3 text-center w-28 font-bold border-r border-white/20">Penyesuaian</th>
                    <th class="p-3 text-center w-24 font-black border-r border-white/20">Stok Akhir</th>
                    <th class="p-3 min-w-[200px] border-r border-white/20">Alasan / Catatan</th>
                    <th class="p-3 text-center w-32">Petugas</th>
                  </tr>
                </thead>
                <tbody id="adjustHistoryTableBody" class="divide-y divide-slate-100 font-medium"></tbody>
              </table>
            </div>
          </div>
        </div>

      </div>

      <!-- ================= 3. TAB: BARANG MASUK (INBOUND) ================= -->
      <div id="tab-inbound" class="hidden space-y-4">

        <!-- Inbound Control Bar -->
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2 flex-1">
            <div class="relative flex-1 min-w-[180px] max-w-md">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">
                <span class="material-symbols-outlined text-[18px]">search</span>
              </span>
              <input type="text" id="inboundSearchInput" oninput="loadInboundHistory()" placeholder="Cari No. Inbound, Material, Lokasi Rak, atau Penerima..." 
                class="w-full h-[38px] pl-9 pr-3 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-emerald-600 focus:bg-white transition-colors">
            </div>

            <div class="premium-datepicker-wrapper">
              <span class="material-symbols-outlined picker-icon text-emerald-700">calendar_today</span>
              <input type="text" id="inboundDateFilter" onchange="loadInboundHistory()" placeholder="Filter Tanggal..." 
                class="premium-datepicker-input px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-emerald-600" title="Filter Tanggal Inbound">
            </div>
          </div>

          <!-- Inbound Actions (Uniform 38px Height) -->
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <button onclick="loadInboundHistory()" class="h-[38px] px-3 bg-white hover:bg-slate-50 text-slate-700 rounded-lg border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Refresh Data Inbound">
              <span class="material-symbols-outlined text-[18px]">refresh</span>
              <span>Refresh</span>
            </button>

            <a href="export.php?type=inbound" target="_blank" class="h-[38px] px-3.5 bg-white hover:bg-emerald-50 text-emerald-800 rounded-lg border border-emerald-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Export Riwayat Barang Masuk ke File Excel (.xlsx)">
              <span class="material-symbols-outlined text-[18px] text-emerald-700">table_chart</span>
              <span>Export Inbound</span>
            </a>

            <button onclick="openAddInboundModal()" class="h-[38px] px-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Input Penerimaan Barang Masuk">
              <span class="material-symbols-outlined text-[18px]">add_box</span>
              <span>Input Barang Masuk</span>
            </button>
          </div>
        </div>

        <!-- Full-Width Inbound Data Table -->
        <div class="bg-white rounded-xl border border-slate-200/90 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-700">
                <tr>
                  <th class="py-3.5 px-3.5 whitespace-nowrap border-r border-white/20">Tanggal</th>
                  <th class="py-3.5 px-3.5 whitespace-nowrap border-r border-white/20">No. Inbound</th>
                  <th class="py-3.5 px-3.5 border-r border-white/20">Kemas</th>
                  <th class="py-3.5 px-3.5 text-center whitespace-nowrap border-r border-white/20 font-mono font-bold">Qty In</th>
                  <th class="py-3.5 px-3.5 whitespace-nowrap border-r border-white/20">Lokasi Rak</th>
                  <th class="py-3.5 px-3.5 whitespace-nowrap border-r border-white/20">Petugas Penerima</th>
                  <th class="py-3.5 px-3.5">Catatan</th>
                </tr>
              </thead>
              <tbody id="inboundHistoryTable" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ================= 4. TAB: BARANG KELUAR (OUTBOUND) ================= -->
      <div id="tab-outbound" class="hidden space-y-4">

        <!-- Outbound Control Bar -->
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2 flex-1">
            <div class="relative flex-1 min-w-[180px] max-w-md">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">
                <span class="material-symbols-outlined text-[18px]">search</span>
              </span>
              <input type="text" id="outboundSearchInput" oninput="loadOutboundHistory()" placeholder="Cari No. Keluar/Task, Material, Tujuan Line, Operator..." 
                class="w-full h-[38px] pl-9 pr-3 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-amber-600 focus:bg-white transition-colors">
            </div>

            <div class="premium-datepicker-wrapper">
              <span class="material-symbols-outlined picker-icon text-amber-700">calendar_today</span>
              <input type="text" id="outboundDateFilter" onchange="loadOutboundHistory()" placeholder="Filter Tanggal..." 
                class="premium-datepicker-input px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-amber-600" title="Filter Tanggal Outbound">
            </div>

            <select id="outboundTypeFilter" onchange="loadOutboundHistory()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-700 outline-none">
              <option value="ALL">Semua Jenis Pengeluaran</option>
              <option value="TASK_PICKING">Pengambilan Line (Operator Task)</option>
              <option value="MANUAL_OUTBOUND">Pengeluaran Manual (Admin)</option>
            </select>

            <select id="outboundStatusFilter" onchange="loadOutboundHistory()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-700 outline-none">
              <option value="ALL">Semua Status Pengerjaan</option>
              <option value="IN_PROGRESS">On Proses / In Progress</option>
              <option value="PENDING">Pending (Menunggu)</option>
              <option value="COMPLETED">Selesai Dikerjakan</option>
              <option value="CANCELLED">Dibatalkan</option>
            </select>
          </div>

          <!-- Outbound Actions (Uniform 38px Height) -->
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <button onclick="loadOutboundHistory()" class="h-[38px] px-3 bg-white hover:bg-slate-50 text-slate-700 rounded-lg border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Refresh Data Outbound">
              <span class="material-symbols-outlined text-[18px]">refresh</span>
              <span>Refresh</span>
            </button>

            <a href="export.php?type=outbound" target="_blank" class="h-[38px] px-3.5 bg-white hover:bg-emerald-50 text-emerald-800 rounded-lg border border-emerald-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Export Riwayat Barang Keluar ke File Excel (.xlsx)">
              <span class="material-symbols-outlined text-[18px] text-emerald-700">table_chart</span>
              <span>Export Outbound</span>
            </a>

            <button onclick="openAddOutboundModal()" class="h-[38px] px-3.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold shrink-0" title="Input Pengeluaran Manual Admin">
              <span class="material-symbols-outlined text-[18px]">outbox</span>
              <span>Keluar Manual</span>
            </button>

            <button onclick="switchAdminTab('tasks')" class="h-[38px] px-3.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold shrink-0" title="Buka Form Penugasan Operator (Task Dispatch)">
              <span class="material-symbols-outlined text-[18px]">assignment_add</span>
              <span>Tugaskan Operator</span>
            </button>
          </div>
        </div>

        <!-- Full-Width Outbound Data Table -->
        <div class="bg-white rounded-xl border border-slate-200/90 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-700">
                <tr>
                  <th class="py-3.5 px-3.5 whitespace-nowrap border-r border-white/20">Tanggal</th>
                  <th class="py-3.5 px-3.5 whitespace-nowrap border-r border-white/20">No. Dokumen / Task</th>
                  <th class="py-3.5 px-3.5 whitespace-nowrap border-r border-white/20">Status</th>
                  <th class="py-3.5 px-3.5 border-r border-white/20">Kemas</th>
                  <th class="py-3.5 px-3.5 text-center whitespace-nowrap border-r border-white/20 font-mono font-bold">Qty Out</th>
                  <th class="py-3.5 px-3.5 whitespace-nowrap border-r border-white/20">Tujuan Antar & PIC</th>
                  <th class="py-3.5 px-3.5 text-center whitespace-nowrap">Aksi</th>
                </tr>
              </thead>
              <tbody id="outboundHistoryTable" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ================= 5. TAB: MANAJEMEN PENUGASAN OPERATOR (TASK DISPATCH) ================= -->
      <div id="tab-tasks" class="hidden space-y-4">
        
        <!-- Sub-View Switcher Header -->
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <button type="button" onclick="switchAdminTab('outbound')" class="h-[38px] w-[38px] rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors flex items-center justify-center text-xs font-bold border border-slate-200 shadow-2xs shrink-0" title="Kembali ke Barang Keluar">
              <span class="material-symbols-outlined text-[19px]">arrow_back</span>
            </button>
            <div class="w-9 h-9 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center flex-shrink-0 shadow-2xs">
              <span class="material-symbols-outlined text-[20px]">assignment</span>
            </div>
            <div>
              <h2 class="font-extrabold text-slate-900 text-sm sm:text-base leading-tight">Penugasan PIC</h2>
              <p class="text-[11px] text-slate-500 font-medium">Buat & delegasikan tugas pengambilan packaging ke PIC</p>
            </div>
          </div>

          <!-- Sub-Tab Navigation Buttons -->
          <div class="inline-flex p-1 bg-slate-100 rounded-xl border border-slate-200 text-xs font-semibold self-start sm:self-auto gap-1">
            <button type="button" id="subtab-task-create-btn" onclick="switchTaskSubView('create')" 
              class="h-[34px] px-3.5 rounded-lg bg-white text-emerald-800 shadow-2xs font-bold transition-all flex items-center gap-1.5 border border-slate-200/60">
              <span class="material-symbols-outlined text-[17px]">add_task</span>
              <span>Buat Penugasan</span>
            </button>
            <button type="button" id="subtab-task-list-btn" onclick="switchTaskSubView('list')" 
              class="h-[34px] px-3.5 rounded-lg text-slate-600 hover:text-slate-900 transition-all font-semibold flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[17px]">format_list_bulleted</span>
              <span>Daftar Task</span>
            </button>
            <button type="button" id="subtab-task-excel-btn" onclick="switchTaskSubView('excel')" 
              class="h-[34px] px-3.5 rounded-lg text-slate-600 hover:text-slate-900 transition-all font-semibold flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[17px]">upload_file</span>
              <span>Upload Excel</span>
            </button>
          </div>
        </div>

        <!-- ================= SUB-VIEW 1: FORM BUAT PENUGASAN (PAGE VIEW) ================= -->
        <div id="taskSubViewCreate" class="space-y-4">
          <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 class="font-bold text-slate-900 text-sm flex items-center gap-2">
                  <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                  <span>Form Assign Picking Stock Kemas</span>
                </h3>
              </div>
            </div>

            <!-- MULTIPLE PRODUCT FORM (FULL PAGE TABLE) -->
            <div id="assignTaskMultipleSection" class="space-y-4">
              <!-- Batch Defaults Toolbar -->
              <div class="p-4 bg-gradient-to-r from-slate-50 to-emerald-50/20 border border-slate-200 rounded-xl grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs shadow-2xs">
                <div>
                  <label class="block font-bold text-slate-700 mb-1.5">Tugaskan ke PIC <span class="text-rose-500">*</span></label>
                  <select id="bulkHeaderOperator" class="w-full h-[38px] px-3 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 font-semibold text-xs text-slate-800 shadow-2xs">
                    <option value="">-- Pilih PIC --</option>
                  </select>
                </div>

                <div>
                  <label class="block font-bold text-slate-700 mb-1.5">Tujuan Antar Default <span class="text-rose-500">*</span></label>
                  <select id="bulkHeaderDestination" onchange="syncBulkDestinationToRows()" 
                    class="w-full h-[38px] px-3 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 text-xs font-bold text-slate-800 shadow-2xs" data-no-search>
                    <option value="HANASUI" selected>HANASUI</option>
                    <option value="NCO">NCO</option>
                    <option value="FYNE">FYNE</option>
                    <option value="EOMMA">EOMMA</option>
                  </select>
                </div>

                <div>
                  <label class="block font-bold text-slate-700 mb-1.5">Prioritas Default</label>
                  <select id="bulkHeaderPriority" onchange="syncBulkPriorityToRows()" 
                    class="w-full h-[38px] px-3 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 font-bold text-xs text-slate-800 shadow-2xs" data-no-search>
                    <option value="NORMAL">Normal</option>
                    <option value="URGENT">URGENT</option>
                  </select>
                </div>
              </div>

              <!-- Dynamic Multi-Row Table -->
              <div class="overflow-hidden border border-slate-200 rounded-xl bg-white shadow-sm">
                <div class="overflow-x-auto min-h-[340px]">
                  <table class="w-full text-left border-collapse">
                    <thead class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-950 sticky top-0 z-10">
                      <tr>
                        <th class="p-3 w-12 text-center border-r border-white/10">No</th>
                        <th class="p-3 min-w-[300px] border-r border-white/10">Stock Kemas <span class="text-amber-300">*</span></th>
                        <th class="p-3 w-28 text-center border-r border-white/10">Satuan (UOM)</th>
                        <th class="p-3 w-32 border-r border-white/10">Target Qty <span class="text-amber-300">*</span></th>
                        <th class="p-3 w-40 border-r border-white/10">Tujuan Spesifik</th>
                        <th class="p-3 w-36 border-r border-white/10">Prioritas</th>
                        <th class="p-3 min-w-[200px] border-r border-white/10">Catatan / Instruksi</th>
                        <th class="p-3 w-14 text-center">Aksi</th>
                      </tr>
                    </thead>
                    <tbody id="bulkTaskTableBody" class="divide-y divide-slate-100 text-xs"></tbody>
                  </table>
                </div>
              </div>

              <!-- Actions Footer -->
              <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-3 border-t border-slate-100">
                <button type="button" onclick="addBulkTaskRow()" class="h-[40px] px-4 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-200 inline-flex items-center justify-center gap-2 transition-colors shadow-2xs">
                  <span class="material-symbols-outlined text-[19px]">add_circle</span>
                  <span>Tambah Baris Produk</span>
                </button>

                <div class="flex items-center gap-2.5 justify-end">
                  <button type="button" onclick="switchAdminTab('outbound')" class="h-[40px] px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors flex items-center gap-1.5 border border-slate-200 shadow-2xs">
                    <span class="material-symbols-outlined text-[17px]">arrow_back</span>
                    <span>Kembali ke Barang Keluar</span>
                  </button>
                  <button type="button" onclick="handleBulkTaskSubmit()" class="h-[40px] px-6 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white text-xs font-bold shadow-sm inline-flex items-center gap-2 transition-all">
                    <span class="material-symbols-outlined text-[18px]">send</span>
                    <span>Kirim Tugas</span>
                  </button>
                </div>
              </div>
            </div>

          </div>
        </div>

        <!-- ================= SUB-VIEW 2: DAFTAR MONITORING TASK (TABLE VIEW) ================= -->
        <div id="taskSubViewList" class="hidden space-y-4">
          <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2 flex-1">
              <div class="relative flex-1 min-w-[180px] max-w-xs">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">
                  <span class="material-symbols-outlined text-[18px]">search</span>
                </span>
                <input type="text" id="taskSearchInput" oninput="loadTasks()" placeholder="Cari No. Task, material, operator, tujuan..." 
                  class="w-full h-[38px] pl-9 pr-3 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-emerald-600 focus:bg-white transition-colors">
              </div>

              <div class="premium-datepicker-wrapper">
                <span class="material-symbols-outlined picker-icon text-emerald-700">calendar_today</span>
                <input type="text" id="taskDateFilter" onchange="loadTasks()" placeholder="Filter Tanggal..." 
                  class="premium-datepicker-input px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-emerald-600" title="Filter Tanggal Task">
              </div>

              <select id="taskStatusFilter" onchange="loadTasks()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-emerald-600" data-no-search>
                <option value="ALL">Semua Status</option>
                <option value="ACTIVE">Aktif (Pending / In Progress)</option>
                <option value="PENDING">Pending (Belum Diambil)</option>
                <option value="IN_PROGRESS">In Progress (Sedang Diambil)</option>
                <option value="COMPLETED">Selesai (Completed)</option>
                <option value="CANCELLED">Dibatalkan</option>
              </select>

              <select id="taskPriorityFilter" onchange="loadTasks()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-emerald-600" data-no-search>
                <option value="ALL">Semua Prioritas</option>
                <option value="URGENT">URGENT</option>
                <option value="NORMAL">Normal</option>
              </select>
            </div>

            <!-- Actions (Uniform 38px Height) -->
            <div class="flex flex-wrap items-center gap-2 shrink-0">
              <button onclick="loadTasks()" class="h-[38px] px-3 bg-white hover:bg-slate-50 text-slate-700 rounded-lg border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Refresh Daftar Task">
                <span class="material-symbols-outlined text-[18px]">refresh</span>
                <span>Refresh</span>
              </button>

              <button onclick="switchTaskSubView('create')" class="h-[38px] px-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Buat Penugasan Task Baru">
                <span class="material-symbols-outlined text-[18px]">add_task</span>
                <span>Buat Penugasan</span>
              </button>
            </div>
          </div>

          <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse">
                <thead class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-950">
                  <tr>
                    <th class="p-3 border-r border-white/10 whitespace-nowrap">Tanggal & Waktu</th>
                    <th class="p-3 border-r border-white/10 whitespace-nowrap">No. Task</th>
                    <th class="p-3 border-r border-white/10">Kemas</th>
                    <th class="p-3 text-center border-r border-white/10 font-mono whitespace-nowrap">Target & Realisasi</th>
                    <th class="p-3 border-r border-white/10 whitespace-nowrap">Tujuan Antar</th>
                    <th class="p-3 text-center border-r border-white/10 whitespace-nowrap">Prioritas</th>
                    <th class="p-3 border-r border-white/10 whitespace-nowrap">Operator PIC</th>
                    <th class="p-3 text-center border-r border-white/10 whitespace-nowrap">Status</th>
                    <th class="p-3 text-center whitespace-nowrap">Aksi</th>
                  </tr>
                </thead>
                <tbody id="tasksTableBody" class="divide-y divide-slate-100 text-xs"></tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- ================= SUB-VIEW 3: UPLOAD TASK EXCEL / CSV (PAGE VIEW) ================= -->
        <div id="taskSubViewExcel" class="hidden space-y-4">
          <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
              <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-emerald-700 text-[26px]">upload_file</span>
                <div>
                  <h3 class="font-bold text-slate-900 text-sm">Upload Penugasan Task dari Excel / CSV</h3>
                  <p class="text-xs text-slate-500">Format kolom: <b>Item No</b>, <b>Target Qty</b>, <b>Destination</b>, <b>Operator Username</b>, <b>Priority</b>, <b>Notes</b></p>
                </div>
              </div>
              <a href="../api/tasks.php?action=template" target="_blank" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold border border-slate-200 transition-colors inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">download</span>
                <span>Download Template CSV</span>
              </a>
            </div>

            <div class="space-y-3">
              <div id="importTaskDropzone" class="p-6 border-2 border-dashed border-slate-300 hover:border-emerald-600 rounded-xl text-center bg-slate-50 transition-colors">
                <span class="material-symbols-outlined text-[40px] text-emerald-600 mb-1">upload_file</span>
                <p class="text-xs font-bold text-slate-800">Pilih File CSV / Excel Daftar Penugasan Task (.csv, .xlsx, .xls)</p>
                <p class="text-[11px] text-slate-500 mt-0.5">Mendukung format otomatis pemetaan kolom</p>
                
                <input type="file" id="excelTaskFileInput" accept=".csv, .txt, .xlsx, .xls" onchange="handleExcelTaskFileSelect(this)" class="hidden">
                
                <div class="mt-3 flex items-center justify-center gap-2">
                  <button type="button" onclick="document.getElementById('excelTaskFileInput').click()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-semibold transition-colors inline-flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[18px]">folder_open</span>
                    <span>Telusuri File Task</span>
                  </button>
                  <a href="../api/tasks.php?action=template" target="_blank" class="px-3.5 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-semibold transition-colors inline-flex items-center gap-1">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    <span>Download Template</span>
                  </a>
                </div>
              </div>

              <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2">
                <label class="block text-xs font-semibold text-slate-700">Atau Paste Data Tabel Task Excel di sini:</label>
                <textarea id="excelTaskPasteText" rows="3" placeholder="Paste baris dari Excel (Item No [Tab] Target Qty [Tab] Destination [Tab] Operator)..." class="w-full p-2.5 bg-white border border-slate-300 rounded-lg text-xs font-mono"></textarea>
                <button type="button" onclick="previewExcelTaskTextPaste()" class="px-4 py-1.5 bg-slate-800 text-white rounded-lg text-xs font-semibold hover:bg-slate-700">
                  Proses Teks Paste
                </button>
              </div>

              <div id="importTaskPreviewLoading" class="hidden text-center py-3 text-xs font-semibold text-emerald-700">
                <span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                <span>Membaca dan memvalidasi tugas...</span>
              </div>

              <div id="importTaskPreviewSection" class="hidden space-y-2">
                <div class="flex items-center justify-between border-b border-slate-200 pb-1.5">
                  <h4 class="font-bold text-xs text-slate-800 uppercase tracking-wider">Hasil Validasi Task:</h4>
                  <div id="importTaskSummaryStats"></div>
                </div>

                <div class="max-h-72 overflow-y-auto border border-slate-200 rounded-lg">
                  <table class="w-full text-left">
                    <thead class="thead-emerald text-[10px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-950 sticky top-0">
                      <tr>
                        <th class="p-2">Item No</th>
                        <th class="p-2">Material Packaging</th>
                        <th class="p-2">Target Qty</th>
                        <th class="p-2">Tujuan</th>
                        <th class="p-2">Operator PIC</th>
                        <th class="p-2">Prioritas</th>
                        <th class="p-2">Status</th>
                      </tr>
                    </thead>
                    <tbody id="importTaskPreviewTableBody" class="divide-y divide-slate-100 text-xs"></tbody>
                  </table>
                </div>
              </div>

              <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                <button type="button" onclick="switchAdminTab('outbound')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold flex items-center gap-1">
                  <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                  <span>Batal & Kembali ke Barang Keluar</span>
                </button>
                <button type="button" id="importTaskSubmitBtn" onclick="commitExcelTaskImport()" class="hidden px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm inline-flex items-center gap-1.5">
                  <span class="material-symbols-outlined text-[18px]">send</span>
                  <span>Buat Semua Task Hasil Import</span>
                </button>
              </div>
            </div>
          </div>
        </div>

      </div>

      <!-- ================= 6. TAB: LOG MUTASI STOK ================= -->
      <div id="tab-mutations" class="hidden space-y-4">
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2 flex-1">
            <div class="relative flex-1 min-w-[180px] max-w-md">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">
                <span class="material-symbols-outlined text-[18px]">search</span>
              </span>
              <input type="text" id="mutationSearchInput" oninput="renderMutationsTable()" placeholder="Cari nomor ref, material, SKU, PIC, catatan..." 
                class="w-full h-[38px] pl-9 pr-3 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-emerald-600 focus:bg-white transition-colors">
            </div>

            <div class="premium-datepicker-wrapper">
              <span class="material-symbols-outlined picker-icon text-purple-600">calendar_today</span>
              <input type="text" id="mutationDateFilter" onchange="renderMutationsTable()" placeholder="Filter Tanggal..." 
                class="premium-datepicker-input px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-purple-600" title="Filter Tanggal Mutasi">
            </div>

            <select id="mutationTypeFilter" onchange="renderMutationsTable()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 outline-none focus:border-emerald-600">
              <option value="ALL">Semua Jenis Mutasi</option>
              <option value="INITIAL_IMPORT">Stok Awal / Excel Import</option>
              <option value="INBOUND">Barang Masuk (Inbound)</option>
              <option value="OUTBOUND">Barang Keluar Manual</option>
              <option value="TASK_PICKING">Pengambilan Task Operator</option>
              <option value="ADJUSTMENT">Penyesuaian Stok (Adjust)</option>
            </select>
          </div>

          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <button type="button" onclick="loadMutations(true)" class="h-[38px] px-3 bg-white hover:bg-slate-50 text-slate-700 rounded-lg border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Refresh Data Mutasi">
              <span class="material-symbols-outlined text-[18px]">refresh</span>
              <span>Refresh</span>
            </button>

            <button type="button" onclick="exportMutationsExcel()" class="h-[38px] px-3.5 bg-purple-600 hover:bg-purple-700 text-white rounded-lg shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Export Buku Mutasi ke File Excel (.xlsx)">
              <span class="material-symbols-outlined text-[18px]">table_chart</span>
              <span>Export Mutasi</span>
            </button>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-700">
                <tr>
                  <th class="p-3 border-r border-white/20">Waktu</th>
                  <th class="p-3 border-r border-white/20">Tipe Mutasi</th>
                  <th class="p-3 border-r border-white/20">No. Referensi</th>
                  <th class="p-3 border-r border-white/20">Material</th>
                  <th class="p-3 text-center border-r border-white/20 font-mono">Perubahan (+/-)</th>
                  <th class="p-3 text-center border-r border-white/20 font-mono font-black">Sisa Stok</th>
                  <th class="p-3">Keterangan</th>
                </tr>
              </thead>
              <tbody id="mutationsTableBody" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ================= 7. TAB: MANAJEMEN USER & ROLE ================= -->
      <div id="tab-users" class="hidden space-y-4">
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-2 flex-1">
            <div class="relative flex-1 min-w-[200px] max-w-md">
              <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400 pointer-events-none">
                <span class="material-symbols-outlined text-[18px]">search</span>
              </span>
              <input type="text" id="userSearchInput" oninput="loadUsers()" placeholder="Cari username, nama, atau shift..." 
                class="w-full h-[38px] pl-9 pr-3 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-emerald-600 focus:bg-white">
            </div>

            <select id="userRoleFilter" onchange="loadUsers()" class="h-[38px] px-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-700 outline-none">
              <option value="all">Semua Role</option>
              <option value="teknisi">Teknisi</option>
              <option value="operator">PIC</option>
            </select>
          </div>

          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <button type="button" onclick="loadUsers()" class="h-[38px] px-3 bg-white hover:bg-slate-50 text-slate-700 rounded-lg border border-slate-300 shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold" title="Refresh Data User">
              <span class="material-symbols-outlined text-[18px]">refresh</span>
              <span>Refresh</span>
            </button>

            <button onclick="openAddUserModal()" class="h-[38px] px-3.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg shadow-2xs transition-colors flex items-center gap-1.5 text-xs font-bold shrink-0" title="Tambah User Baru">
              <span class="material-symbols-outlined text-[18px]">person_add</span>
              <span>Tambah User</span>
            </button>
          </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-950">
                <tr>
                  <th class="p-3 border-r border-white/10">Username</th>
                  <th class="p-3 border-r border-white/10">Nama Lengkap</th>
                  <th class="p-3 border-r border-white/10">Role Pengguna</th>
                  <th class="p-3 border-r border-white/10">Shift / Divisi</th>
                  <th class="p-3 border-r border-white/10">Tanggal Dibuat</th>
                  <th class="p-3 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody id="usersTableBody" class="divide-y divide-slate-100 text-xs"></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ================= 8. TAB: HAK AKSES MENU & ROLE (PERMISSIONS) ================= -->
      <div id="tab-permissions" class="hidden space-y-4">
        <!-- Permissions Control Bar -->
        <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center border border-emerald-200">
              <span class="material-symbols-outlined text-[22px]">lock_person</span>
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900">Pengaturan Otorisasi & Hak Akses Menu</h3>
              <p class="text-xs text-slate-500">Tentukan menu dan fitur yang dapat diakses oleh Role atau User tertentu</p>
            </div>
          </div>

          <!-- Target Selector: Role or User -->
          <div class="flex items-center gap-2">
            <div class="inline-flex p-1 bg-slate-100 rounded-lg border border-slate-200 text-xs font-semibold">
              <button type="button" id="btnPermModeRole" onclick="setPermissionMode('role')" class="px-3 py-1 rounded-md bg-white text-emerald-800 shadow-2xs font-bold transition-all">Berdasarkan Role</button>
              <button type="button" id="btnPermModeUser" onclick="setPermissionMode('user')" class="px-3 py-1 rounded-md text-slate-600 hover:text-slate-900 transition-all">Khusus Per User</button>
            </div>

            <!-- Role Selector Dropdown -->
            <select id="permRoleSelector" onchange="loadPermissionMatrix()" class="px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-800 outline-none focus:border-emerald-600">
              <option value="superadmin">Role: Super Administrator</option>
              <option value="admin">Role: Administrator</option>
              <option value="operator">Role: PIC</option>
            </select>

            <!-- User Selector Dropdown (Hidden in Role Mode) -->
            <select id="permUserSelector" onchange="loadPermissionMatrix()" class="hidden px-3 py-1.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-800 outline-none focus:border-emerald-600">
            </select>
          </div>
        </div>

        <!-- Permissions Matrix Container -->
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden p-5 space-y-4">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
            <div>
              <h4 id="permTargetTitle" class="font-bold text-xs uppercase tracking-wider text-slate-900">Matriks Hak Akses: Administrator</h4>
              <p id="permTargetSubtitle" class="text-xs text-slate-500">Aktifkan atau nonaktifkan menu untuk role ini</p>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" onclick="toggleAllPermissions(true)" class="px-2.5 py-1 text-xs font-semibold text-emerald-700 bg-emerald-50 hover:bg-emerald-100 rounded-lg border border-emerald-200 transition-colors">
                Pilih Semua (ON)
              </button>
              <button type="button" onclick="toggleAllPermissions(false)" class="px-2.5 py-1 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg border border-slate-200 transition-colors">
                Nonaktifkan Semua (OFF)
              </button>
            </div>
          </div>

          <!-- Grid of Menu Permission Cards -->
          <div id="permissionsGrid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
            <!-- Rendered dynamically -->
          </div>

          <!-- Bottom Action Bar -->
          <div class="flex items-center justify-between pt-4 border-t border-slate-100">
            <button type="button" id="btnResetPerm" onclick="resetUserPermission()" class="hidden px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50 rounded-lg border border-rose-200 transition-colors">
              Kembalikan ke Standar Role
            </button>
            <div class="flex items-center gap-2 ml-auto">
              <button type="button" onclick="loadPermissionMatrix()" class="px-3.5 py-2 text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">Batal</button>
              <button type="button" onclick="savePermissions()" class="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 rounded-lg shadow-sm transition-colors flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[16px]">save</span>
                <span>Simpan Hak Akses</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ================= 9. TAB: MAINTENANCE & PEMBERSIHAN DATABASE (SUPER ADMIN ONLY) ================= -->
      <?php if (Auth::isSuperAdmin()): ?>
      <div id="tab-maintenance" class="hidden space-y-5">
        <!-- Section: Maintenance Mode Switcher -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-600 shadow-xs shrink-0">
                <span class="material-symbols-outlined text-[24px]">construction</span>
              </div>
              <div>
                <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
                  <span>Mode Pemeliharaan (Maintenance Mode)</span>
                </h4>
              </div>
            </div>
            
            <div class="flex items-center gap-3 self-end sm:self-auto">
              <span id="maintenanceBadge" class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase <?= Auth::isMaintenanceMode() ? 'bg-rose-100 text-rose-800 border border-rose-200' : 'bg-slate-100 text-slate-600 border border-slate-200' ?>">
                <?= Auth::isMaintenanceMode() ? 'AKTIF (SITUS DIKUNCI)' : 'NON-AKTIF' ?>
              </span>
              
              <button type="button" id="btnToggleMaintenance" onclick="toggleMaintenanceMode(<?= Auth::isMaintenanceMode() ? 'false' : 'true' ?>)" 
                class="h-[38px] px-4 <?= Auth::isMaintenanceMode() ? 'bg-emerald-600 hover:bg-emerald-700 text-white' : 'bg-rose-600 hover:bg-rose-700 text-white' ?> text-xs font-bold rounded-xl shadow transition-colors flex items-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]"><?= Auth::isMaintenanceMode() ? 'lock_open' : 'lock' ?></span>
                <span><?= Auth::isMaintenanceMode() ? 'Matikan Mode Maintenance' : 'Aktifkan Mode Maintenance' ?></span>
              </button>
            </div>
          </div>
        </div>

        <!-- Maintenance Alert Banner -->
        <div class="bg-gradient-to-r from-rose-950 via-slate-900 to-slate-950 p-4 sm:p-5 rounded-2xl border border-rose-800/60 shadow-lg text-white">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-11 h-11 rounded-2xl bg-rose-600/30 border border-rose-500/50 flex items-center justify-center text-rose-400 shrink-0 shadow-inner">
                <span class="material-symbols-outlined text-[26px]">database</span>
              </div>
              <div>
                <div class="flex items-center gap-2">
                  <h3 class="text-sm sm:text-base font-black tracking-tight text-white">Pembersihan & Maintenance Database</h3>
                  <span class="px-2 py-0.5 rounded bg-rose-500/20 text-rose-300 border border-rose-500/40 text-[10px] font-extrabold uppercase">Teknisi Only</span>
                </div>
              </div>
            </div>

            <button type="button" onclick="loadDatabaseStats()" class="h-[38px] px-3.5 bg-rose-900/60 hover:bg-rose-800 text-rose-200 hover:text-white rounded-lg border border-rose-700/60 transition-colors flex items-center gap-1.5 text-xs font-bold shrink-0 self-start sm:self-auto shadow-2xs" title="Refresh Statistik Database">
              <span class="material-symbols-outlined text-[18px]">refresh</span>
              <span>Refresh Status DB</span>
            </button>
          </div>
        </div>

        <!-- Section 1: Individual Table Cards -->
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
              <span class="material-symbols-outlined text-rose-700 text-[18px]">view_list</span>
              <span>Kosongkan Tabel Spesifik (Per Kategori)</span>
            </h4>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
            
            <!-- 1. Materials Table Card -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-rose-300 transition-all flex flex-col justify-between space-y-3.5">
              <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">materials</span>
                  <span id="statMaint_materials" class="px-2 py-0.5 rounded-full text-xs font-black bg-emerald-50 text-emerald-800 border border-emerald-200">0 SKU</span>
                </div>
                <h5 class="font-bold text-slate-900 text-xs">Master Stok Material Packaging</h5>
              </div>
              <button type="button" onclick="openCleanTableModal('materials', 'Master Stok Material (materials)', document.getElementById('statMaint_materials').innerText)" class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
                <span>Kosongkan Master Material</span>
              </button>
            </div>

            <!-- 2. Inbound Table Card -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-rose-300 transition-all flex flex-col justify-between space-y-3.5">
              <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">inbound_transactions</span>
                  <span id="statMaint_inbound_transactions" class="px-2 py-0.5 rounded-full text-xs font-black bg-emerald-50 text-emerald-800 border border-emerald-200">0 Transaksi</span>
                </div>
                <h5 class="font-bold text-slate-900 text-xs">Riwayat Barang Masuk (Inbound)</h5>
              </div>
              <button type="button" onclick="openCleanTableModal('inbound', 'Riwayat Barang Masuk (inbound_transactions)', document.getElementById('statMaint_inbound_transactions').innerText)" class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
                <span>Kosongkan Riwayat Inbound</span>
              </button>
            </div>

            <!-- 3. Outbound Table Card -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-rose-300 transition-all flex flex-col justify-between space-y-3.5">
              <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">outbound_transactions</span>
                  <span id="statMaint_outbound_transactions" class="px-2 py-0.5 rounded-full text-xs font-black bg-emerald-50 text-emerald-800 border border-emerald-200">0 Transaksi</span>
                </div>
                <h5 class="font-bold text-slate-900 text-xs">Riwayat Barang Keluar Manual</h5>
              </div>
              <button type="button" onclick="openCleanTableModal('outbound', 'Riwayat Barang Keluar (outbound_transactions)', document.getElementById('statMaint_outbound_transactions').innerText)" class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
                <span>Kosongkan Riwayat Outbound</span>
              </button>
            </div>

            <!-- 4. Tasks Table Card -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-rose-300 transition-all flex flex-col justify-between space-y-3.5">
              <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">tasks</span>
                  <span id="statMaint_tasks" class="px-2 py-0.5 rounded-full text-xs font-black bg-amber-50 text-amber-800 border border-amber-200">0 Task</span>
                </div>
                <h5 class="font-bold text-slate-900 text-xs">Penugasan Task PIC</h5>
              </div>
              <button type="button" onclick="openCleanTableModal('tasks', 'Penugasan Task Operator (tasks)', document.getElementById('statMaint_tasks').innerText)" class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
                <span>Kosongkan Semua Task</span>
              </button>
            </div>

            <!-- 5. Opname Sessions Table Card -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-rose-300 transition-all flex flex-col justify-between space-y-3.5">
              <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">stock_opnames</span>
                  <span id="statMaint_stock_opnames" class="px-2 py-0.5 rounded-full text-xs font-black bg-purple-50 text-purple-800 border border-purple-200">0 Sesi</span>
                </div>
                <h5 class="font-bold text-slate-900 text-xs">Sesi Stock Opname & Dynamic Count</h5>
              </div>
              <button type="button" onclick="openCleanTableModal('opname', 'Sesi Stock Opname & Dynamic Count (stock_opnames)', document.getElementById('statMaint_stock_opnames').innerText)" class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
                <span>Kosongkan Sesi Opname</span>
              </button>
            </div>

            <!-- 6. Mutations Table Card -->
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-xs hover:border-rose-300 transition-all flex flex-col justify-between space-y-3.5">
              <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                  <span class="font-mono text-[11px] font-bold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">stock_mutations</span>
                  <span id="statMaint_stock_mutations" class="px-2 py-0.5 rounded-full text-xs font-black bg-blue-50 text-blue-800 border border-blue-200">0 Entri</span>
                </div>
                <h5 class="font-bold text-slate-900 text-xs">Buku Log Mutasi & Kartu Stok</h5>
              </div>
              <button type="button" onclick="openCleanTableModal('mutations', 'Buku Log Mutasi Stok (stock_mutations)', document.getElementById('statMaint_stock_mutations').innerText)" class="w-full h-[36px] bg-rose-50 hover:bg-rose-100 text-rose-800 border border-rose-200 hover:border-rose-300 rounded-lg text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[16px] text-rose-700">delete_sweep</span>
                <span>Kosongkan Log Mutasi</span>
              </button>
            </div>

          </div>
        </div>

        <!-- Section 2: Bulk Actions (Transaction Reset & Factory Reset) -->
        <div class="space-y-3 pt-2">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800 flex items-center gap-2">
              <span class="material-symbols-outlined text-rose-700 text-[18px]">cleaning_services</span>
              <span>Pembersihan Massal & Reset Penuh</span>
            </h4>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <!-- Bulk 1: Clear All Transactions (Keep Materials & Users) -->
            <div class="bg-gradient-to-br from-white to-amber-50/50 p-4 sm:p-5 rounded-2xl border border-amber-200 shadow-sm space-y-3 flex flex-col justify-between">
              <div class="space-y-2">
                <div class="flex items-center gap-2.5">
                  <div class="w-9 h-9 rounded-xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold">
                    <span class="material-symbols-outlined text-[20px]">mop</span>
                  </div>
                  <div>
                    <h5 class="font-black text-slate-900 text-sm">Kosongkan Seluruh Riwayat Transaksi</h5>
                  </div>
                </div>

                <label class="flex items-center gap-2 pt-1 text-xs font-bold text-slate-800 cursor-pointer select-none">
                  <input type="checkbox" id="maintResetStockZero" checked class="w-4 h-4 rounded text-amber-600 focus:ring-amber-500 border-slate-300">
                  <span>Reset juga Stok Aktual (Current Stock) di Master menjadi 0</span>
                </label>
              </div>

              <button type="button" onclick="openBulkCleanModal('clean_all_transactions')" class="h-[40px] px-4 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[18px]">cleaning_services</span>
                <span>Bersihkan Semua Transaksi Sekarang</span>
              </button>
            </div>

            <!-- Bulk 2: Factory Reset (Clean All Data) -->
            <div class="bg-gradient-to-br from-white to-rose-50/50 p-4 sm:p-5 rounded-2xl border border-rose-300 shadow-sm space-y-3 flex flex-col justify-between">
              <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-rose-100 text-rose-800 flex items-center justify-center font-bold">
                  <span class="material-symbols-outlined text-[20px]">restart_alt</span>
                </div>
                <div>
                  <h5 class="font-black text-rose-950 text-sm">Reset Database Penuh (Factory Reset)</h5>
                </div>
              </div>

              <button type="button" onclick="openBulkCleanModal('factory_reset')" class="h-[40px] px-4 bg-rose-700 hover:bg-rose-800 text-white rounded-xl text-xs font-bold transition-all shadow-xs flex items-center justify-center gap-1.5">
                <span class="material-symbols-outlined text-[18px]">delete_forever</span>
                <span>Reset Database Penuh (Factory Reset)</span>
              </button>
            </div>

          </div>
        </div>

      </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<!-- ================= MODAL: INPUT INBOUND TABLE ================= -->
<div id="modalAddInbound" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-3 sm:p-4">
  <div class="bg-white rounded-2xl max-w-6xl w-full xl:max-w-7xl p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 max-h-[92vh] flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-shrink-0">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center border border-emerald-200/80 shadow-2xs">
          <span class="material-symbols-outlined text-[22px]">move_to_inbox</span>
        </div>
        <div>
          <h3 class="font-extrabold text-slate-900 text-sm leading-tight">Input Penerimaan Barang Masuk (Tabel)</h3>
          <p class="text-[11px] text-slate-400 font-medium">Input satu atau beberapa material kemas/consumable dalam satu transaksi</p>
        </div>
      </div>
      <button onclick="App.closeModal('modalAddInbound')" class="w-8 h-8 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center transition-colors">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form id="inboundForm" onsubmit="handleInboundTableSubmit(event)" class="space-y-3.5 text-xs flex-1 flex flex-col min-h-0 overflow-hidden">
      <!-- Meta Information Row -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 flex-shrink-0 bg-slate-50 p-3 rounded-xl border border-slate-200">
        <div>
          <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
            <span>Tanggal Masuk</span>
            <span class="text-[10px] text-slate-400 font-semibold flex items-center gap-0.5">
              <span class="material-symbols-outlined text-[12px]">lock</span>
              <span>Auto</span>
            </span>
          </label>
          <input type="text" id="inboundFormDateDisplay" readonly class="w-full p-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 cursor-not-allowed select-none outline-none">
          <input type="hidden" id="inboundFormDate" value="<?= date('Y-m-d') ?>">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
            <span>Jam / Waktu</span>
            <span class="text-[10px] text-slate-400 font-semibold flex items-center gap-0.5">
              <span class="material-symbols-outlined text-[12px]">lock</span>
              <span>Auto</span>
            </span>
          </label>
          <input type="text" id="inboundFormTimeDisplay" readonly class="w-full p-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 cursor-not-allowed select-none outline-none">
          <input type="hidden" id="inboundFormTime" value="<?= date('H:i') ?>">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Catatan Tambahan</label>
          <input type="text" id="inboundGlobalNotes" placeholder="Keterangan umum (Opsional)..." class="w-full p-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:border-emerald-600">
        </div>
      </div>

      <!-- Items Table Container -->
      <div class="flex-1 min-h-0 overflow-y-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
        <table class="w-full text-left border-collapse text-xs">
          <thead class="bg-slate-100/90 text-slate-700 font-extrabold uppercase text-[10px] tracking-wider border-b border-slate-200 sticky top-0 z-10 whitespace-nowrap">
            <tr>
              <th class="p-2.5 w-12 text-center">#</th>
              <th class="p-2.5 min-w-[340px]">Kemas / Consumable <span class="text-rose-500">*</span></th>
              <th class="p-2.5 w-40 min-w-[140px]">Lokasi Rak</th>
              <th class="p-2.5 w-32 min-w-[110px] text-center">Qty Masuk <span class="text-rose-500">*</span></th>
              <th class="p-2.5 min-w-[200px]">Catatan Item</th>
              <th class="p-2.5 w-14 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody id="inboundItemsTableBody" class="divide-y divide-slate-100">
            <!-- Dynamic rows inserted here -->
          </tbody>
        </table>
      </div>

      <!-- Bottom Bar with Add Row and Total Summary -->
      <div class="flex items-center justify-between pt-1 flex-shrink-0">
        <button type="button" onclick="addInboundTableRow()" class="px-3.5 py-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 font-bold text-xs flex items-center gap-1.5 transition-colors shadow-2xs">
          <span class="material-symbols-outlined text-[16px]">add_circle</span>
          <span>Tambah Baris (Enter)</span>
        </button>

        <div class="text-xs font-bold text-slate-700 flex items-center gap-2">
          <span>Total Qty Masuk:</span>
          <span class="px-3 py-1 rounded-lg bg-emerald-50 text-emerald-900 border border-emerald-200 font-mono font-black text-sm" id="inboundTotalQtySummary">0</span>
        </div>
      </div>

      <!-- Footer Buttons -->
      <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 flex-shrink-0">
        <button type="button" onclick="App.closeModal('modalAddInbound')" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
          Batal
        </button>
        <button type="submit" id="btnSubmitInboundTable" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-extrabold rounded-xl shadow-md text-xs flex items-center gap-1.5 transition-all">
          <span class="material-symbols-outlined text-[17px]">save</span>
          <span>Simpan & Tambah Stok</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: INPUT OUTBOUND TABLE ================= -->
<div id="modalAddOutbound" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-3 sm:p-4">
  <div class="bg-white rounded-2xl max-w-6xl w-full xl:max-w-7xl p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 max-h-[92vh] flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-shrink-0">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center border border-amber-200/80 shadow-2xs">
          <span class="material-symbols-outlined text-[22px]">outbox</span>
        </div>
        <div>
          <h3 class="font-extrabold text-slate-900 text-sm leading-tight">Input Pengeluaran Kemas/Consumable (Tabel)</h3>
          <p class="text-[11px] text-slate-400 font-medium">Catat pengeluaran barang keluar langsung ke lini brand produksi</p>
        </div>
      </div>
      <button onclick="App.closeModal('modalAddOutbound')" class="w-8 h-8 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 flex items-center justify-center transition-colors">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form id="outboundForm" onsubmit="handleOutboundTableSubmit(event)" class="space-y-3.5 text-xs flex-1 flex flex-col min-h-0 overflow-hidden">
      <!-- Meta Information Row -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 flex-shrink-0 bg-slate-50 p-3 rounded-xl border border-slate-200">
        <div>
          <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
            <span>Tanggal Keluar</span>
            <span class="text-[10px] text-slate-400 font-semibold flex items-center gap-0.5">
              <span class="material-symbols-outlined text-[12px]">lock</span>
              <span>Auto</span>
            </span>
          </label>
          <input type="text" id="outboundFormDateDisplay" readonly class="w-full p-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 cursor-not-allowed select-none outline-none">
          <input type="hidden" id="outboundFormDate" value="<?= date('Y-m-d') ?>">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1 flex items-center justify-between">
            <span>Jam / Waktu</span>
            <span class="text-[10px] text-slate-400 font-semibold flex items-center gap-0.5">
              <span class="material-symbols-outlined text-[12px]">lock</span>
              <span>Auto</span>
            </span>
          </label>
          <input type="text" id="outboundFormTimeDisplay" readonly class="w-full p-2 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 cursor-not-allowed select-none outline-none">
          <input type="hidden" id="outboundFormTime" value="<?= date('H:i') ?>">
        </div>

        <div>
          <label class="block font-bold text-slate-700 mb-1">Catatan Tambahan</label>
          <input type="text" id="outboundGlobalNotes" placeholder="Keterangan / No SPK (Opsional)..." class="w-full p-2 bg-white border border-slate-300 rounded-lg text-xs outline-none focus:border-amber-600">
        </div>
      </div>

      <!-- Items Table Container -->
      <div class="flex-1 min-h-0 overflow-y-auto border border-slate-200 rounded-xl bg-white shadow-2xs">
        <table class="w-full text-left border-collapse text-xs">
          <thead class="bg-slate-100/90 text-slate-700 font-extrabold uppercase text-[10px] tracking-wider border-b border-slate-200 sticky top-0 z-10 whitespace-nowrap">
            <tr>
              <th class="p-2.5 w-12 text-center">#</th>
              <th class="p-2.5 min-w-[340px]">Kemas / Consumable <span class="text-rose-500">*</span></th>
              <th class="p-2.5 w-44 min-w-[160px]">Tujuan Brand <span class="text-rose-500">*</span></th>
              <th class="p-2.5 w-32 min-w-[110px] text-center">Qty Keluar <span class="text-rose-500">*</span></th>
              <th class="p-2.5 min-w-[220px]">Alasan Pengeluaran <span class="text-rose-500">*</span></th>
              <th class="p-2.5 w-14 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody id="outboundItemsTableBody" class="divide-y divide-slate-100">
            <!-- Dynamic rows inserted here -->
          </tbody>
        </table>
      </div>

      <!-- Bottom Bar with Add Row and Total Summary -->
      <div class="flex items-center justify-between pt-1 flex-shrink-0">
        <button type="button" onclick="addOutboundTableRow()" class="px-3.5 py-2 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-bold text-xs flex items-center gap-1.5 transition-colors shadow-2xs">
          <span class="material-symbols-outlined text-[16px]">add_circle</span>
          <span>Tambah Baris (Enter)</span>
        </button>

        <div class="text-xs font-bold text-slate-700 flex items-center gap-2">
          <span>Total Qty Keluar:</span>
          <span class="px-3 py-1 rounded-lg bg-amber-50 text-amber-900 border border-amber-200 font-mono font-black text-sm" id="outboundTotalQtySummary">0</span>
        </div>
      </div>

      <!-- Footer Buttons -->
      <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-100 flex-shrink-0">
        <button type="button" onclick="App.closeModal('modalAddOutbound')" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors">
          Batal
        </button>
        <button type="submit" id="btnSubmitOutboundTable" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 active:scale-95 text-white font-extrabold rounded-xl shadow-md text-xs flex items-center gap-1.5 transition-all">
          <span class="material-symbols-outlined text-[17px]">save</span>
          <span>Catat & Potong Stok</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: EXCEL / CSV IMPORT (MASTER STOK) ================= -->
<div id="modalExcelImport" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-3xl w-full p-6 shadow-xl border border-slate-200 space-y-4 max-h-[90vh] flex flex-col">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-shrink-0">
      <div class="flex items-center gap-2.5">
        <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center border border-emerald-100">
          <span class="material-symbols-outlined text-[22px] leading-none">upload_file</span>
        </div>
        <div>
          <h3 class="font-black text-slate-900 text-sm tracking-wide">Upload Database Master Stok</h3>
          <p class="text-[10px] text-slate-500 mt-0.5">Kolom wajib template: <span class="font-mono text-slate-700 bg-slate-100 px-1 py-0.2 rounded">Item No</span>, <span class="font-mono text-slate-700 bg-slate-100 px-1 py-0.2 rounded">Item Description</span>, <span class="font-mono text-slate-700 bg-slate-100 px-1 py-0.2 rounded">Satuan</span>, <span class="font-mono text-slate-700 bg-slate-100 px-1 py-0.2 rounded">Lokasi Rak</span>, <span class="font-mono text-slate-700 bg-slate-100 px-1 py-0.2 rounded">Ending Stock</span></p>
        </div>
      </div>
      <button onclick="App.closeModal('modalExcelImport')" class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <div class="flex-1 overflow-y-auto space-y-4 pr-1 scrollbar-thin">
      <!-- 1. Detected Local File in Folder Alert Banner -->
      <div id="localExcelFileAlert" class="hidden p-3.5 bg-emerald-50 border border-emerald-200 rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5 shadow-2xs">
        <div class="flex items-center gap-2.5">
          <span class="material-symbols-outlined text-emerald-700 text-[24px]">task</span>
          <div>
            <p class="font-bold text-emerald-900 text-xs">File Ditemukan di Folder: <span id="localExcelFileName" class="font-mono bg-white/70 px-1.5 py-0.5 rounded border border-emerald-300">Data Packaaging Material.xlsx</span></p>
            <p class="text-[11px] text-emerald-700 mt-0.5" id="localExcelFileDesc">Tersedia data material packaging siap diimpor ke database.</p>
          </div>
        </div>
        <button type="button" onclick="previewDetectedLocalExcel()" class="px-3.5 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-sm whitespace-nowrap inline-flex items-center gap-1 active:scale-95 transition-all">
          <span class="material-symbols-outlined text-[16px]">bolt</span>
          <span>Preview File Ini</span>
        </button>
      </div>

      <!-- 2. Dropzone for uploading or browsing -->
      <div id="importDropzone" class="relative group p-8 border-2 border-dashed border-slate-200 hover:border-emerald-500 rounded-2xl text-center bg-slate-50/50 hover:bg-emerald-50/20 transition-all duration-300 cursor-pointer flex flex-col items-center justify-center">
        <!-- Pulse overlay ring on hover -->
        <div class="absolute inset-0 rounded-2xl bg-emerald-500/0 group-hover:bg-emerald-500/[0.02] transition-colors duration-300 pointer-events-none"></div>
        
        <div class="w-16 h-16 mb-3 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-100 flex items-center justify-center group-hover:scale-110 group-hover:bg-emerald-600 group-hover:text-white transition-all duration-300 shadow-2xs">
          <span class="material-symbols-outlined text-[32px] leading-none">cloud_upload</span>
        </div>
        
        <p class="text-xs font-bold text-slate-800 tracking-wide">Pilih atau Seret File Excel</p>
        <p class="text-[10px] text-slate-400 mt-1 max-w-sm">Mendukung format spreadsheet (.xlsx, .xls, .csv, .txt) dari sistem SAP atau ERP</p>
        
        <input type="file" id="excelFileInput" accept=".xlsx, .xls, .csv, .txt" onchange="handleExcelFileSelect(this)" class="hidden">
        
        <div class="mt-4 flex items-center gap-2">
          <button type="button" onclick="document.getElementById('excelFileInput').click()" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-all duration-300 shadow-md shadow-emerald-600/10 hover:shadow-emerald-600/20 inline-flex items-center gap-1.5 active:scale-95">
            <span class="material-symbols-outlined text-[16px]">folder_open</span>
            <span>Telusuri File Excel</span>
          </button>
          <a href="export.php?type=inventory_template" target="_blank" class="px-4 py-2 bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 rounded-xl text-xs font-bold transition-all duration-300 inline-flex items-center gap-1.5 shadow-2xs hover:shadow-sm active:scale-95">
            <span class="material-symbols-outlined text-[16px] text-slate-500">download</span>
            <span>Download Template Excel</span>
          </a>
        </div>
      </div>

      <!-- 3. Text Paste Option -->
      <div class="bg-white p-4 rounded-2xl border border-slate-200 space-y-3 shadow-2xs hover:border-slate-300 transition-colors">
        <div class="flex items-center gap-2 text-slate-800">
          <span class="material-symbols-outlined text-[18px] text-slate-500">content_paste</span>
          <label class="text-xs font-bold tracking-wide">Atau Tempel (Paste) Data Tabel Excel di Sini:</label>
        </div>
        <textarea id="excelPasteText" rows="3" placeholder="Contoh format: ItemNo [Tab] Deskripsi [Tab] Stok... (Salin langsung dari baris Excel Anda)" class="w-full p-3 bg-slate-50/50 border border-slate-200 rounded-xl text-xs font-mono focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition-all duration-300 outline-none placeholder-slate-400"></textarea>
        <button type="button" onclick="previewExcelTextPaste()" class="px-4 py-2 bg-slate-950 hover:bg-slate-850 text-white rounded-xl text-xs font-bold transition-all duration-300 shadow-md shadow-slate-950/10 active:scale-95 inline-flex items-center gap-1.5">
          <span class="material-symbols-outlined text-[16px]">done_all</span>
          <span>Proses Teks Tempel</span>
        </button>
      </div>

      <!-- Loading Indicator -->
      <div id="importPreviewLoading" class="hidden flex-col items-center justify-center py-10 bg-slate-50/50 rounded-2xl border border-slate-100">
        <div class="relative w-16 h-16 flex items-center justify-center mb-3">
          <div class="absolute inset-0 rounded-full border-4 border-emerald-500/20"></div>
          <div class="absolute inset-0 rounded-full border-4 border-transparent border-t-emerald-600 animate-spin"></div>
          <span class="material-symbols-outlined text-emerald-600 text-[28px] animate-pulse">analytics</span>
        </div>
        <p class="text-xs font-bold text-slate-800 tracking-wide animate-pulse">Menganalisis Berkas Excel...</p>
        <p class="text-[10px] text-slate-400 mt-1">Sistem sedang memverifikasi kolom data dan struktur baris</p>
      </div>

      <!-- 4. Preview Table Section -->
      <div id="importPreviewSection" class="hidden space-y-3">
        <div class="flex items-center justify-between border-b border-slate-200 pb-2">
          <h4 class="font-bold text-[11px] text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[16px] text-emerald-700">visibility</span>
            <span>Hasil Pratinjau (Preview) Data:</span>
          </h4>
          <div id="importSummaryStats"></div>
        </div>

        <div class="max-h-64 overflow-y-auto border border-slate-200 rounded-xl shadow-2xs">
          <table class="w-full text-left">
            <thead class="thead-emerald text-[10px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-950 sticky top-0">
              <tr>
                <th class="p-2.5">Item No</th>
                <th class="p-2.5">Item Description</th>
                <th class="p-2.5">Kategori</th>
                <th class="p-2.5 text-center">Satuan (UOM)</th>
                <th class="p-2.5 text-center">Ending Stock</th>
                <th class="p-2.5">Lokasi Rak</th>
                <th class="p-2.5">Status</th>
              </tr>
            </thead>
            <tbody id="importPreviewTableBody" class="divide-y divide-slate-100 text-xs"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Footer -->
    <div class="flex items-center justify-between pt-3 border-t border-slate-100 flex-shrink-0">
      <button type="button" onclick="App.closeModal('modalExcelImport')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-all duration-300 active:scale-95">Batal</button>
      <button type="button" id="importSubmitBtn" onclick="commitExcelImport()" class="hidden px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-md shadow-emerald-600/10 hover:shadow-emerald-600/20 transition-all duration-300 inline-flex items-center gap-1.5 active:scale-95">
        <span class="material-symbols-outlined text-[16px]">upload</span>
        <span>Simpan ke Master Stok</span>
      </button>
    </div>
  </div>
</div>

<!-- ================= MODAL: MATERIAL STOCK CARD & IN/OUT HISTORY ================= -->
<div id="modalMaterialHistory" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-4xl w-full p-6 shadow-xl border border-slate-200 space-y-4 max-h-[92vh] flex flex-col">
    <!-- Modal Header -->
    <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-shrink-0">
      <div class="flex items-center gap-2.5">
        <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
          <span class="material-symbols-outlined text-[24px]">history</span>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h3 id="histItemNo" class="font-mono font-black text-sm text-emerald-800"></h3>
            <span id="histCategoryBadge" class="px-2 py-0.2 bg-slate-100 text-slate-600 rounded text-[10px] font-semibold"></span>
          </div>
          <p id="histItemName" class="font-bold text-slate-800 text-xs"></p>
        </div>
      </div>
      <button onclick="App.closeModal('modalMaterialHistory')" class="text-slate-400 hover:text-slate-700">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <!-- Stock Summary Cards (Formula breakdown: Upload + In - Out = Sisa) -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 flex-shrink-0 text-xs">
      <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
        <p class="text-[10px] uppercase font-bold text-slate-400">Stok Awal (Upload)</p>
        <h4 id="histInitialStock" class="text-base font-extrabold text-slate-700 mt-0.5">0</h4>
        <p class="text-[10px] text-slate-400">Excel / Stok Awal</p>
      </div>

      <div class="p-3 bg-emerald-50/70 border border-emerald-200 rounded-xl">
        <p class="text-[10px] uppercase font-bold text-emerald-700">Total Barang Masuk</p>
        <h4 id="histTotalInbound" class="text-base font-extrabold text-emerald-800 mt-0.5">+0</h4>
        <p class="text-[10px] text-emerald-600">Penerimaan PO</p>
      </div>

      <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl">
        <p class="text-[10px] uppercase font-bold text-amber-700">Total Barang Keluar</p>
        <h4 id="histTotalOutbound" class="text-base font-extrabold text-amber-800 mt-0.5">-0</h4>
        <p class="text-[10px] text-amber-600">Picking Line & Manual</p>
      </div>

      <div class="p-3 bg-emerald-600 text-white rounded-xl shadow-sm">
        <p class="text-[10px] uppercase font-bold text-emerald-100">Sisa Stok Akhir</p>
        <h4 id="histCurrentStock" class="text-base font-black text-white mt-0.5">0</h4>
        <p class="text-[10px] text-emerald-200">Stok Aktual Fisik</p>
      </div>
    </div>

    <!-- Transaction History Table -->
    <div class="flex-1 overflow-y-auto border border-slate-200 rounded-xl">
      <table class="w-full text-left border-collapse">
        <thead class="thead-emerald text-[10px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-950 sticky top-0 z-10">
          <tr>
            <th class="p-2.5">Waktu Transaksi</th>
            <th class="p-2.5">Tipe Mutasi</th>
            <th class="p-2.5">No. Referensi</th>
            <th class="p-2.5 text-center">Masuk (+)</th>
            <th class="p-2.5 text-center">Keluar (-)</th>
            <th class="p-2.5 text-center">Sisa Stok</th>
            <th class="p-2.5">Keterangan & Petugas</th>
          </tr>
        </thead>
        <tbody id="histTableBody" class="divide-y divide-slate-100 text-xs">
          <!-- Dynamically populated -->
        </tbody>
      </table>
    </div>

    <!-- Modal Footer -->
    <div class="flex items-center justify-between pt-3 border-t border-slate-100 flex-shrink-0">
      <div class="flex items-center gap-2 text-xs text-slate-500">
        <span>Lokasi Rak: <b id="histRackLocation" class="text-slate-800"></b></span>
        <span>&bull;</span>
        <span>Min Safety: <b id="histMinStock" class="text-slate-800"></b></span>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" id="histAssignBtn" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm inline-flex items-center gap-1">
          <span class="material-symbols-outlined text-[16px]">add</span>
          <span>Assign Task Item Ini</span>
        </button>
        <button type="button" onclick="App.closeModal('modalMaterialHistory')" class="px-3.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- ================= MODAL: ADD / EDIT MATERIAL ================= -->
<div id="modalMaterialForm" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <h3 id="modalMaterialTitle" class="font-bold text-slate-900 text-sm">Tambah Material Packaging</h3>
      <button onclick="App.closeModal('modalMaterialForm')" class="text-slate-400 hover:text-slate-700">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form id="formMaterial" onsubmit="handleMaterialFormSubmit(event)" class="space-y-3 text-xs">
      <input type="hidden" id="materialIdInput">

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Item No / Kode Material <span class="text-rose-500">*</span></label>
        <input type="text" id="materialCodeInput" required placeholder="Contoh: PKG-BOX-005" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg font-mono uppercase font-bold outline-none focus:border-emerald-600 focus:bg-white">
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Item Description / Nama Material <span class="text-rose-500">*</span></label>
        <input type="text" id="materialNameInput" required placeholder="Contoh: Corrugated Master Box 450x350x300" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg outline-none focus:border-emerald-600 focus:bg-white">
      </div>

      <div class="grid grid-cols-3 gap-2.5">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Kategori</label>
          <input type="text" id="materialCategoryInput" placeholder="Karton Box..." class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg outline-none focus:border-emerald-600 focus:bg-white">
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Satuan (UOM) <span class="text-rose-500">*</span></label>
          <input type="text" id="materialUnitInput" placeholder="Pcs / Roll / Box" value="Pcs" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg font-bold text-slate-800 outline-none focus:border-emerald-600 focus:bg-white">
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Lokasi Rak</label>
          <input type="text" id="materialRackInput" placeholder="Rak A-01" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg outline-none focus:border-emerald-600 focus:bg-white">
        </div>
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Min Safety Stock</label>
        <input type="number" id="materialMinStockInput" min="0" placeholder="20" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg outline-none focus:border-emerald-600 focus:bg-white">
      </div>

      <div id="materialInitialStockGroup">
        <label class="block font-semibold text-slate-700 mb-1">Ending Stock / Stok Awal</label>
        <input type="number" id="materialInitialStockInput" min="0" placeholder="0" class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg font-bold text-emerald-700 outline-none focus:border-emerald-600 focus:bg-white">
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Deskripsi Tambahan</label>
        <input type="text" id="materialDescInput" placeholder="Spesifikasi ukuran, bahan..." class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg outline-none focus:border-emerald-600 focus:bg-white">
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
        <button type="button" onclick="App.closeModal('modalMaterialForm')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors">Batal</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm flex items-center gap-1.5 transition-colors">
          <span class="material-symbols-outlined text-[16px]">save</span>
          <span>Simpan Material</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: ADD / EDIT USER & ROLE ================= -->
<div id="modalUserForm" class="fixed inset-0 z-50 modal-backdrop hidden items-center justify-center p-4">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-slate-200 space-y-4">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <h3 id="modalUserTitle" class="font-bold text-slate-900 text-sm">Tambah Pengguna Baru</h3>
      <button onclick="App.closeModal('modalUserForm')" class="text-slate-400 hover:text-slate-700">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form id="formUser" onsubmit="handleUserFormSubmit(event)" class="space-y-3 text-xs">
      <input type="hidden" id="userIdInput">

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Username <span class="text-rose-500">*</span></label>
        <input type="text" id="userUsernameInput" required placeholder="Contoh: operator3 / supervisor1" 
          class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg outline-none focus:border-emerald-600 focus:bg-white font-mono">
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
        <input type="text" id="userNameInput" required placeholder="Contoh: Rian Hidayat" 
          class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg outline-none focus:border-emerald-600 focus:bg-white">
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Role / Hak Akses <span class="text-rose-500">*</span></label>
        <select id="userRoleSelect" required class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg outline-none focus:border-emerald-600 focus:bg-white font-medium">
          <option value="operator">PIC (Panel Mobile)</option>
          <option value="teknisi">Teknisi (Panel Admin)</option>
        </select>
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Shift / Divisi Penugasan</label>
        <input type="text" id="userShiftInput" placeholder="Contoh: Shift 1 (Pagi 07:00-15:00) / Gudang B" 
          class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg outline-none focus:border-emerald-600 focus:bg-white">
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">
          <span id="userPasswordLabel">Password</span> <span id="userPasswordRequiredTag" class="text-rose-500">*</span>
        </label>
        <input type="password" id="userPasswordInput" placeholder="Masukkan password..." 
          class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg outline-none focus:border-emerald-600 focus:bg-white">
        <p id="userPasswordHint" class="text-[10px] text-slate-400 mt-0.5 hidden">Kosongkan jika tidak ingin mengubah password user.</p>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
        <button type="button" onclick="App.closeModal('modalUserForm')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors">Batal</button>
        <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm flex items-center gap-1.5 transition-colors">
          <span class="material-symbols-outlined text-[16px]">save</span>
          <span>Simpan User</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: EDIT PENUGASAN TASK ================= -->
<div id="modalEditTask" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-scale-up border border-slate-200">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold shadow-2xs">
          <span class="material-symbols-outlined text-[20px]">edit_note</span>
        </div>
        <div>
          <h3 class="font-extrabold text-slate-900 text-sm">Edit Penugasan Task Operator</h3>
          <p class="text-xs text-slate-500 font-mono" id="editTaskNoSubtitle">No. Task: -</p>
        </div>
      </div>
      <button type="button" onclick="App.closeModal('modalEditTask')" class="text-slate-400 hover:text-slate-600 p-1">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form id="formEditTask" onsubmit="handleEditTaskSubmit(event)" class="space-y-3 text-xs">
      <input type="hidden" id="editTaskId" value="">

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Stock Kemas (Produk) <span class="text-rose-500">*</span></label>
        <select id="editTaskMaterialSelect" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 font-medium text-xs"></select>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Target Qty <span class="text-rose-500">*</span></label>
          <input type="number" id="editTaskTargetQty" min="1" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 font-mono font-bold text-xs">
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Tugaskan ke Operator PIC <span class="text-rose-500">*</span></label>
          <select id="editTaskOperatorSelect" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 font-medium text-xs"></select>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Tujuan Antar <span class="text-rose-500">*</span></label>
          <input type="text" id="editTaskDestination" required placeholder="Contoh: Line Packing 1" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 font-medium text-xs">
        </div>
        <div>
          <label class="block font-semibold text-slate-700 mb-1">Prioritas</label>
          <select id="editTaskPriority" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 font-bold text-xs">
            <option value="NORMAL">Normal</option>
            <option value="URGENT">URGENT</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Catatan / Instruksi Tambahan</label>
        <textarea id="editTaskNotes" rows="2" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 text-xs" placeholder="Instruksi khusus untuk operator..."></textarea>
      </div>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <button type="button" onclick="App.closeModal('modalEditTask')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors">Batal</button>
        <button type="submit" id="btnEditTaskSubmit" class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-1">
          <span class="material-symbols-outlined text-[16px]">save</span>
          <span>Simpan Perubahan Penugasan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: PROFIL SAYA & UPDATE PASSWORD ================= -->
<div id="modalProfile" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 animate-scale-up border border-slate-200">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-emerald-600 to-emerald-500 text-white flex items-center justify-center shadow-xs">
          <span class="material-symbols-outlined text-[20px]">manage_accounts</span>
        </div>
        <div>
          <h3 class="font-extrabold text-slate-900 text-sm">Profil Saya & Update Password</h3>
          <p class="text-xs text-slate-500">Kelola informasi akun dan kata sandi login</p>
        </div>
      </div>
      <button type="button" onclick="App.closeModal('modalProfile')" class="text-slate-400 hover:text-slate-600 p-1">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form id="formProfile" onsubmit="handleProfileSubmit(event)" class="space-y-3 text-xs">
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Username</label>
        <input type="text" id="profileUsername" value="<?= htmlspecialchars(Auth::username()) ?>" disabled 
          class="w-full p-2 bg-slate-100 border border-slate-200 rounded-lg text-slate-500 font-mono font-bold cursor-not-allowed">
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Nama Lengkap <span class="text-rose-500">*</span></label>
        <input type="text" id="profileName" value="<?= htmlspecialchars(Auth::name()) ?>" required 
          class="w-full p-2 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 font-semibold text-slate-900">
      </div>

      <div class="pt-2 border-t border-slate-100 space-y-3">
        <div class="flex items-center justify-between">
          <span class="font-extrabold text-slate-800 text-[11px] uppercase tracking-wider">Ubah Password Login</span>
          <span class="text-[10px] text-slate-400">(Kosongkan jika tidak diubah)</span>
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1">Password Saat Ini (Lama)</label>
          <input type="password" id="profileCurrentPassword" placeholder="Masukkan password lama..." 
            class="w-full p-2 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600">
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1">Password Baru</label>
          <input type="password" id="profileNewPassword" placeholder="Minimal 5 karakter..." 
            class="w-full p-2 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600">
        </div>

        <div>
          <label class="block font-semibold text-slate-700 mb-1">Konfirmasi Password Baru</label>
          <input type="password" id="profileConfirmPassword" placeholder="Ketik ulang password baru..." 
            class="w-full p-2 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600">
        </div>
      </div>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <button type="button" onclick="App.closeModal('modalProfile')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors">Batal</button>
        <button type="submit" id="btnProfileSubmit" class="px-5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-1">
          <span class="material-symbols-outlined text-[16px]">save</span>
          <span>Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: DETAIL TRANSAKSI INBOUND ================= -->
<div id="modalInboundDetail" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-scale-up border border-slate-200 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 flex items-center justify-center shadow-xs">
          <span class="material-symbols-outlined text-[20px]">receipt_long</span>
        </div>
        <div>
          <h3 class="font-extrabold text-slate-900 text-sm" id="detailInboundNo">Detail Transaksi Inbound</h3>
          <p class="text-xs text-slate-500" id="detailInboundDate">Informasi lengkap penerimaan material</p>
        </div>
      </div>
      <button type="button" onclick="App.closeModal('modalInboundDetail')" class="text-slate-400 hover:text-slate-600 p-1">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <div id="detailInboundContent" class="space-y-3 text-xs">
      <!-- Injected by JavaScript -->
    </div>

    <div class="flex items-center justify-end pt-3 border-t border-slate-100">
      <button type="button" onclick="App.closeModal('modalInboundDetail')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors">Tutup</button>
    </div>
  </div>
</div>

<!-- ================= MODAL: DETAIL TRANSAKSI OUTBOUND ================= -->
<div id="modalOutboundDetail" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-scale-up border border-slate-200 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 flex items-center justify-center shadow-xs">
          <span class="material-symbols-outlined text-[20px]">description</span>
        </div>
        <div>
          <h3 class="font-extrabold text-slate-900 text-sm" id="detailOutboundNo">Detail Transaksi Outbound</h3>
          <p class="text-xs text-slate-500" id="detailOutboundDate">Informasi lengkap pengeluaran material</p>
        </div>
      </div>
      <button type="button" onclick="App.closeModal('modalOutboundDetail')" class="text-slate-400 hover:text-slate-600 p-1">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <div id="detailOutboundContent" class="space-y-3 text-xs">
      <!-- Injected by JavaScript -->
    </div>

    <div class="flex items-center justify-end pt-3 border-t border-slate-100">
      <button type="button" onclick="App.closeModal('modalOutboundDetail')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors">Tutup</button>
    </div>
  </div>
</div>

<!-- ================= MODAL: BUAT SESI DYNAMIC COUNTING (SKU PILIHAN) ================= -->
<div id="modalCreateDynamicCount" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-4 animate-scale-up border border-slate-200 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-indigo-100 text-indigo-800 flex items-center justify-center font-bold">
          <span class="material-symbols-outlined text-[20px]">checklist</span>
        </div>
        <div>
          <h3 class="font-extrabold text-slate-900 text-sm">Buat Sesi Dynamic Counting Baru</h3>
          <p class="text-xs text-slate-500">Pilih SKU produk tertentu untuk dihitung oleh PIC</p>
        </div>
      </div>
      <button type="button" onclick="App.closeModal('modalCreateDynamicCount')" class="text-slate-400 hover:text-slate-600 p-1">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form id="formCreateDynamicCount" onsubmit="handleCreateDynamicCountSubmit(event)" class="space-y-4 text-xs">
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Nomor Dokumen Sesi (Otomatis)</label>
        <input type="text" id="createDynamicTitle" readonly 
          class="w-full p-2.5 bg-slate-100 border border-slate-300 rounded-lg outline-none font-mono font-bold text-indigo-700 text-xs cursor-default">
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Tugaskan ke PIC <span class="text-rose-500">*</span></label>
        <select id="createDynamicOperator" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-indigo-600 font-medium text-xs">
          <option value="">-- Pilih PIC --</option>
        </select>
        <p class="text-[10px] text-slate-400 mt-0.5">Operator ini akan menerima task counting khusus SKU yang dipilih.</p>
      </div>

      <!-- DYNAMIC COUNTING: INTERACTIVE MULTI-SELECT SKU PICKER -->
      <div class="space-y-2.5 p-3.5 bg-indigo-50/50 rounded-xl border border-indigo-200">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-indigo-700 text-[18px]">rule</span>
            <span class="font-bold text-slate-900 text-xs">Pilih SKU Packaging yang Akan Dihitung:</span>
            <span id="dynamicSkuSelectedCountBadge" class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-600 text-white">0 SKU Terpilih</span>
          </div>
          <div class="flex items-center gap-1.5">
            <button type="button" onclick="toggleSelectAllDynamicSku(true)" class="px-2 py-0.5 bg-indigo-100 hover:bg-indigo-200 text-indigo-800 rounded text-[11px] font-semibold">Pilih Semua</button>
            <button type="button" onclick="toggleSelectAllDynamicSku(false)" class="px-2 py-0.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded text-[11px] font-semibold">Reset</button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <input type="text" id="dynamicSkuSearchInput" oninput="filterDynamicSkuChecklist()" placeholder="Cari kode/nama SKU..." 
            class="w-full p-2 bg-white border border-indigo-200 rounded-lg text-xs outline-none focus:border-indigo-600">
          <select id="dynamicSkuCategoryFilter" onchange="filterDynamicSkuChecklist()" class="w-full p-2 bg-white border border-indigo-200 rounded-lg text-xs outline-none">
            <option value="ALL">Semua Kategori</option>
          </select>
        </div>

        <!-- Scrollable SKU Checklist -->
        <div id="dynamicSkuChecklistContainer" class="max-h-52 overflow-y-auto space-y-1 bg-white p-2 rounded-lg border border-indigo-200/80 divide-y divide-slate-100">
          <!-- Rendered dynamically -->
        </div>
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Catatan / Instruksi Khusus (Opsional)</label>
        <textarea id="createDynamicNotes" rows="2" placeholder="Contoh: Tolong hitung dan scan ulang rak simpan..." class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-indigo-600 text-xs"></textarea>
      </div>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <button type="button" onclick="App.closeModal('modalCreateDynamicCount')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors">Batal</button>
        <button type="submit" id="btnSubmitCreateDynamic" class="px-5 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-1.5">
          <span class="material-symbols-outlined text-[16px]">send</span>
          <span>Buat & Assign ke Operator</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: MULAI SESI STOCK OPNAME BARU (BLANK COUNT MODE) ================= -->
<div id="modalCreateStockOpname" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-2xl max-w-xl w-full p-6 shadow-2xl space-y-4 animate-scale-up border border-slate-200 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
          <span class="material-symbols-outlined text-[20px]">inventory_2</span>
        </div>
        <div>
          <h3 class="font-extrabold text-slate-900 text-sm">Mulai Sesi Stock Opname Baru</h3>
          <p class="text-xs text-slate-500">Sesi penghitungan fisik murni (Blank Count) seluruh gudang</p>
        </div>
      </div>
      <button type="button" onclick="App.closeModal('modalCreateStockOpname')" class="text-slate-400 hover:text-slate-600 p-1">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form id="formCreateStockOpname" onsubmit="handleCreateStockOpnameSubmit(event)" class="space-y-4 text-xs">
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Nomor Dokumen Sesi (Otomatis)</label>
        <input type="text" id="createOpnameTitle" readonly 
          class="w-full p-2.5 bg-slate-100 border border-slate-300 rounded-lg outline-none font-mono font-bold text-emerald-700 text-xs cursor-default">
      </div>

      <!-- STOCK OPNAME: SCOPE SELECTION (ALL / CATEGORY / RACK) -->
      <div class="space-y-2">
        <label class="block font-semibold text-slate-700 mb-1">Cakupan Material Packaging</label>
        <select id="createOpnameScope" onchange="toggleOpnameScopeFilter()" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 font-semibold text-xs">
          <option value="all">Semua Material Packaging di Gudang (Rekomendasi)</option>
          <option value="category">Berdasarkan Kategori Tertentu</option>
          <option value="rack">Berdasarkan Lokasi Rak Tertentu</option>
        </select>

        <div id="opnameCategoryScopeGroup" class="hidden mt-2">
          <label class="block font-semibold text-slate-700 mb-1">Pilih Kategori</label>
          <select id="createOpnameCategorySelect" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 text-xs"></select>
        </div>

        <div id="opnameRackScopeGroup" class="hidden mt-2">
          <label class="block font-semibold text-slate-700 mb-1">Ketik Lokasi Rak</label>
          <input type="text" id="createOpnameRackInput" placeholder="Contoh: Rak A-01 / Gudang Utama" class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 text-xs">
        </div>
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Catatan Sesi Opname (Opsional)</label>
        <textarea id="createOpnameNotes" rows="2" placeholder="Contoh: Stock Opname kuartal 3, pastikan semua rak diperiksa..." class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-emerald-600 text-xs"></textarea>
      </div>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <button type="button" onclick="App.closeModal('modalCreateStockOpname')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors">Batal</button>
        <button type="submit" id="btnSubmitCreateOpname" class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-1.5">
          <span class="material-symbols-outlined text-[16px]">play_circle</span>
          <span>Buka Sesi Stock Opname</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: TUGASKAN RECOUNT (DYNAMIC STAGE 2nd, 3rd, dst.) ================= -->
<div id="modalAssignRecount" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4 animate-scale-up border border-slate-200 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-purple-100 text-purple-800 flex items-center justify-center font-bold">
          <span class="material-symbols-outlined text-[20px]">how_to_reg</span>
        </div>
        <div>
          <h3 class="font-extrabold text-slate-900 text-sm flex items-center gap-2">
            <span>Tugaskan Recount (Hitung Ulang)</span>
            <span id="recountStageTargetBadge" class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-purple-100 text-purple-900 border border-purple-300">2nd Count</span>
          </h3>
          <p class="text-xs text-slate-500" id="recountOpnameSubtitle">Verifikasi selisih fisik oleh PIC</p>
        </div>
      </div>
      <button type="button" onclick="App.closeModal('modalAssignRecount')" class="text-slate-400 hover:text-slate-600 p-1">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form id="formAssignRecount" onsubmit="handleAssignRecountSubmit(event)" class="space-y-3.5 text-xs">
      <div>
        <label class="block font-semibold text-slate-700 mb-1">Tugaskan ke Operator Recount <span class="text-rose-500">*</span></label>
        <select id="recountOperatorSelect" required class="w-full p-2.5 bg-white border border-slate-300 rounded-lg outline-none focus:border-purple-600 font-bold text-xs">
          <option value="">-- Pilih Operator Recount --</option>
        </select>
        <p class="text-[10px] text-slate-400 mt-0.5">Operator ini akan menerima task recount untuk menghitung ulang fisik di rak.</p>
      </div>

      <!-- Preview of Selected Discrepancy Items -->
      <div class="space-y-2">
        <div class="flex items-center justify-between">
          <label class="font-bold text-slate-800">Daftar SKU Selisih yang Ditugaskan:</label>
          <span id="recountItemsCountBadge" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-900">0 SKU</span>
        </div>
        <div id="recountItemsPreviewList" class="max-h-48 overflow-y-auto space-y-1.5 bg-slate-50 p-2.5 rounded-xl border border-slate-200 divide-y divide-slate-100">
          <!-- Rendered dynamically -->
        </div>
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Catatan / Instruksi Tambahan (Opsional)</label>
        <input type="text" id="recountNotesInput" placeholder="Contoh: Pastikan hitung box yang ada di atas palet juga..." class="w-full p-2 bg-white border border-slate-300 rounded-lg outline-none focus:border-purple-600 text-xs">
      </div>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <button type="button" onclick="App.closeModal('modalAssignRecount')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors">Batal</button>
        <button type="submit" id="btnSubmitRecount" class="px-5 py-2.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-1.5">
          <span class="material-symbols-outlined text-[16px]">send</span>
          <span id="btnSubmitRecountText">Kirim Tugas Recount</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: EDIT ITEM STOCK OPNAME ================= -->
<div id="modalEditOpnameItem" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 animate-scale-up border border-slate-200">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div class="flex items-center gap-2">
        <span class="material-symbols-outlined text-emerald-700 text-[22px]">edit_note</span>
        <h3 class="font-bold text-slate-900 text-sm">Edit Hasil Fisik Opname</h3>
      </div>
      <button type="button" onclick="App.closeModal('modalEditOpnameItem')" class="text-slate-400 hover:text-slate-600">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <!-- Context Info -->
    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 space-y-1.5 text-xs">
      <div class="flex items-center justify-between">
        <span id="editOpnameItemCode" class="font-mono font-bold text-emerald-800 text-xs"></span>
        <span id="editOpnameItemRack" class="text-slate-500 font-semibold"></span>
      </div>
      <h4 id="editOpnameItemName" class="font-bold text-slate-800 text-xs"></h4>
      <div class="grid grid-cols-3 gap-2 pt-1 border-t border-slate-200 text-[11px]">
        <div>Stok Sistem: <b id="editOpnameSysStock" class="text-slate-900">0</b></div>
        <div>Hitung 1: <b id="editOpnameCount1" class="text-blue-700">-</b></div>
        <div>Recount: <b id="editOpnameCount2" class="text-purple-700">-</b></div>
      </div>
    </div>

    <form id="formEditOpnameItem" onsubmit="handleEditOpnameItemSubmit(event)" class="space-y-3 text-xs">
      <input type="hidden" id="editOpnameItemId">

      <div>
        <label class="block font-bold text-slate-800 mb-1 text-xs">Jumlah Fisik Final Disetujui (<span id="editOpnameUnitLabel">Pcs</span>) <span class="text-rose-500">*</span></label>
        <input type="number" id="editOpnameFinalQty" required min="0" 
          class="w-full p-2.5 bg-emerald-50 border-2 border-emerald-500 rounded-lg font-black text-lg text-emerald-800 text-center outline-none">
      </div>

      <div>
        <label class="block font-semibold text-slate-700 mb-1">Catatan / Alasan Koreksi Admin</label>
        <input type="text" id="editOpnameAdminNotes" placeholder="Contoh: Koreksi fisik ulang setelah verifikasi gudang..." 
          class="w-full p-2 bg-slate-50 border border-slate-300 rounded-lg outline-none focus:border-emerald-600 focus:bg-white">
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
        <button type="button" onclick="App.closeModal('modalEditOpnameItem')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors">Batal</button>
        <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg shadow-sm text-xs flex items-center gap-1.5 transition-colors">
          <span class="material-symbols-outlined text-[16px]">save</span>
          <span>Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ================= MODAL: SUPER ADMIN KONFIRMASI PEMBERSIHAN DATABASE ================= -->
<?php if (Auth::isSuperAdmin()): ?>
<div id="modalConfirmDbClean" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4 hidden">
  <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4 animate-scale-up border-2 border-rose-500">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div class="flex items-center gap-2.5">
        <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-bold shadow-xs">
          <span class="material-symbols-outlined text-[24px]">warning</span>
        </div>
        <div>
          <h3 class="font-extrabold text-slate-900 text-sm">Konfirmasi Pembersihan Database</h3>
          <p class="text-xs text-rose-600 font-semibold">Tindakan Permanen Tidak Dapat Dibatalkan</p>
        </div>
      </div>
      <button type="button" onclick="App.closeModal('modalConfirmDbClean')" class="text-slate-400 hover:text-slate-600 p-1">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <form id="formConfirmDbClean" onsubmit="submitCleanDatabase(event)" class="space-y-3.5 text-xs">
      <input type="hidden" id="cleanActionType" value="clean_table">
      <input type="hidden" id="cleanTargetTable" value="">

      <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-xl space-y-1.5 text-rose-950">
        <p class="font-bold text-xs" id="cleanModalTargetTitle">Tabel yang akan dikosongkan:</p>
        <p class="text-xs font-mono font-extrabold text-rose-800" id="cleanModalTargetDesc">-</p>
        <p class="text-[11px] text-slate-600 leading-relaxed pt-1 border-t border-rose-200/60">
          Seluruh data yang dipilih akan dihapus secara permanen dari server database.
        </p>
      </div>

      <div>
        <label class="block font-bold text-slate-800 mb-1">
          Masukkan Password Teknisi (<span class="font-mono text-rose-600"><?= htmlspecialchars(Auth::username()) ?></span>) <span class="text-rose-500">*</span>:
        </label>
        <input type="password" id="cleanSuperAdminPassword" required placeholder="Ketik password login Anda..." 
          class="w-full p-2.5 bg-white border-2 border-slate-300 rounded-lg outline-none focus:border-rose-600 font-bold text-xs">
        <p class="text-[10px] text-slate-400 mt-1">Verifikasi password login diperlukan demi keamanan sistem.</p>
      </div>

      <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
        <button type="button" onclick="App.closeModal('modalConfirmDbClean')" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition-colors">Batal</button>
        <button type="submit" id="btnSubmitCleanDb" class="px-5 py-2.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white font-bold text-xs shadow-sm transition-colors flex items-center gap-1.5">
          <span class="material-symbols-outlined text-[16px]">delete_forever</span>
          <span id="btnSubmitCleanDbText">Ya, Kosongkan Sekarang</span>
        </button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ================= MODAL: DETAIL COUNT VIEW (Stock Opname Item Stages) ================= -->
<div id="modalCountDetail" class="hidden fixed inset-0 z-[100] items-center justify-center bg-black/50 backdrop-blur-sm p-4" onclick="if(event.target===this)closeCountDetailModal()">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col animate-slide-up">
    <!-- Modal Header -->
    <div class="p-5 border-b border-slate-200 flex items-start justify-between gap-3 shrink-0">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="material-symbols-outlined text-emerald-600 text-[22px]">fact_check</span>
          <h3 class="font-black text-sm text-slate-900">Detail Hasil Count</h3>
        </div>
        <h4 id="countDetailTitle" class="text-xs font-bold text-slate-700 mt-0.5"></h4>
        <p id="countDetailSession" class="text-[11px] text-slate-500 mt-0.5"></p>
      </div>
      <button type="button" onclick="closeCountDetailModal()" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-700 transition-colors shrink-0" title="Tutup">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <!-- Modal Body (Scrollable) -->
    <div id="countDetailContent" class="p-5 overflow-y-auto flex-1">
      <!-- Content populated by JS -->
    </div>
  </div>
</div>

<!-- Scripts with Cache Buster -->
<script src="<?= $baseUrl ?>/assets/js/app.js?v=<?= time() ?>"></script>
<script src="<?= $baseUrl ?>/assets/js/admin.js?v=<?= time() ?>"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

