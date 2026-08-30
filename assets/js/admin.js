// assets/js/admin.js - Admin Dashboard & Stock Control Frontend Logic (Google Material Symbols)

let allMaterials = [];
let allOperators = [];
let allTasks = [];
let parsedImportItems = [];
let currentAdminTab = 'dashboard';

// Initialize Admin App
document.addEventListener('DOMContentLoaded', () => {
  initSidebarState();
  applyMyPermissions();
  initPremiumPickers();
  handleUrlHashNavigation(false);

  // Auto refresh live stats & task monitor periodically
  setInterval(() => {
    if (currentAdminTab === 'dashboard') loadStats(true);
    if (currentAdminTab === 'tasks') loadTasks();
    if (currentAdminTab === 'inbound') loadInboundHistory();
    if (currentAdminTab === 'outbound') loadOutboundHistory();
  }, 25000);
});

// ================= 0.1 PREMIUM DATE & TIME PICKERS INITIALIZER =================
function initPremiumPickers() {
  if (typeof flatpickr === 'undefined') return;
  if (flatpickr.l10ns && flatpickr.l10ns.id) {
    flatpickr.localize(flatpickr.l10ns.id);
  }

  const initDate = (selector, onChangeCallback) => {
    const el = document.querySelector(selector);
    if (!el) return;
    if (el._flatpickr) {
      return el._flatpickr;
    }
    return flatpickr(el, {
      altInput: true,
      altFormat: "d F Y",
      dateFormat: "Y-m-d",
      altInputClass: "premium-datepicker-input w-full p-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold outline-none focus:bg-white focus:border-amber-600 cursor-pointer",
      allowInput: true,
      clickOpens: true,
      disableMobile: true,
      onChange: (selectedDates, dateStr) => {
        if (onChangeCallback) onChangeCallback(dateStr);
      }
    });
  };

  const initTime = (selector, onChangeCallback) => {
    const el = document.querySelector(selector);
    if (!el) return;
    if (el._flatpickr) {
      return el._flatpickr;
    }
    return flatpickr(el, {
      enableTime: true,
      noCalendar: true,
      dateFormat: "H:i",
      time_24hr: true,
      altInput: true,
      altFormat: "H:i",
      altInputClass: "premium-datepicker-input w-full p-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold outline-none focus:bg-white focus:border-amber-600 cursor-pointer",
      allowInput: true,
      clickOpens: true,
      disableMobile: true,
      onChange: (selectedDates, dateStr) => {
        if (onChangeCallback) onChangeCallback(dateStr);
      }
    });
  };

  const initDateTime = (selector, onChangeCallback) => {
    const el = document.querySelector(selector);
    if (!el) return;
    if (el._flatpickr) {
      return el._flatpickr;
    }
    return flatpickr(el, {
      enableTime: true,
      altInput: true,
      altFormat: "d F Y - H:i",
      dateFormat: "Y-m-d H:i",
      time_24hr: true,
      altInputClass: "premium-datepicker-input w-full p-2.5 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold outline-none focus:bg-white focus:border-amber-600 cursor-pointer",
      allowInput: true,
      clickOpens: true,
      disableMobile: true,
      onChange: (selectedDates, dateStr) => {
        if (onChangeCallback) onChangeCallback(dateStr);
      }
    });
  };

  // 1. Dashboard Filter
  initDate('#dashInputDate', () => loadDashboardStockSummary());

  // 2. Dynamic Count Toolbar Filter
  initDate('#dynamicDateFilter', () => loadDynamicMatrix());

  // 3. Stock Opname Toolbar Filter
  initDate('#opnameDateFilter', () => loadOpnameMatrix());

  // 4. Inbound Toolbar Filter
  initDate('#inboundDateFilter', () => loadInboundHistory());

  // 5. Outbound Toolbar Filter
  initDate('#outboundDateFilter', () => loadOutboundHistory());

  // 6. Audit Mutasi Stok Toolbar
  initDate('#mutationDateFilter', () => renderMutationsTable());

  // 7. Adjustment Direct & History
  initDate('#directAdjustDateInput');
  initDate('#adjustHistoryDateFilter', () => renderAdjustHistoryTable());

  // 8. Task Dispatcher Toolbar Filter
  initDate('#taskDateFilter', () => loadTasks());

  // 9. Detail Counting Filter
  initDate('#cdFilterDate', () => loadCountingDetails());
}

// ================= 0.2 SIDEBAR MINIMIZE / MAXIMIZE TOGGLE =================
function toggleAdminSidebar(forceState = null) {
  const sidebar = document.getElementById('adminSidebar');
  const icon = document.getElementById('iconToggleSidebar');
  if (!sidebar) return;

  const willBeCollapsed = forceState !== null 
    ? forceState 
    : !sidebar.classList.contains('collapsed');

  if (willBeCollapsed) {
    sidebar.classList.add('collapsed');
    if (icon) icon.innerText = 'menu';
    try { localStorage.setItem('packstock_sidebar_collapsed', '1'); } catch (e) {}
  } else {
    sidebar.classList.remove('collapsed');
    if (icon) icon.innerText = 'menu_open';
    try { localStorage.setItem('packstock_sidebar_collapsed', '0'); } catch (e) {}
  }
}

function initSidebarState() {
  try {
    const isSavedCollapsed = localStorage.getItem('packstock_sidebar_collapsed') === '1';
    if (isSavedCollapsed) {
      toggleAdminSidebar(true);
    }
  } catch (e) {}
}

// Listen to browser Back / Forward buttons
window.addEventListener('hashchange', () => {
  handleUrlHashNavigation(false);
});

function handleUrlHashNavigation(updateUrl = false) {
  const fullHash = window.location.hash.replace('#', '');
  if (!fullHash) {
    switchAdminTab('dashboard', false);
    return;
  }

  const [tabName, queryString] = fullHash.split('?');
  const validTabs = ['dashboard', 'inventory', 'dynamic_count', 'opname', 'adjust', 'counting_detail', 'inbound', 'outbound', 'tasks', 'mutations', 'users', 'permissions', 'maintenance', 'history'];

  if (tabName === 'history' && queryString) {
    const params = new URLSearchParams(queryString);
    const id = params.get('id');
    if (id) {
      openMaterialHistoryView(id, false);
      return;
    }
  }

  if (validTabs.includes(tabName)) {
    switchAdminTab(tabName, false);
  } else {
    switchAdminTab('dashboard', false);
  }
}

// Tab Navigation with URL Hash support
function switchAdminTab(tabName, updateUrl = true) {
  currentAdminTab = tabName;

  // Update browser URL (e.g. /admin/index.php#inbound)
  if (updateUrl && tabName !== 'history') {
    window.location.hash = tabName;
  }
  
  const tabs = ['dashboard', 'inventory', 'dynamic_count', 'opname', 'adjust', 'counting_detail', 'inbound', 'outbound', 'tasks', 'mutations', 'users', 'permissions', 'maintenance', 'history'];
  
  tabs.forEach(t => {
    const el = document.getElementById('tab-' + t);
    const navBtn = document.getElementById('nav-' + t);
    if (el) el.classList.add('hidden');
    if (navBtn) {
      navBtn.classList.remove('bg-emerald-600', 'text-white', 'shadow-xs', 'font-bold');
      navBtn.classList.add('text-slate-600', 'hover:text-slate-900', 'hover:bg-slate-100/80', 'font-semibold');
    }
  });

  const activeTab = document.getElementById('tab-' + tabName);
  const activeNavId = tabName === 'history' ? 'inventory' : tabName;
  const activeNav = document.getElementById('nav-' + activeNavId);
  if (activeTab) activeTab.classList.remove('hidden');
  if (activeNav) {
    activeNav.classList.remove('text-slate-600', 'hover:text-slate-900', 'hover:bg-slate-100/80', 'font-semibold');
    activeNav.classList.add('bg-emerald-600', 'text-white', 'shadow-xs', 'font-bold');
  }

  // Set page title
  const titles = {
    dashboard: 'Dashboard Monitoring Stok & Lapangan',
    inventory: 'Master Stok Packaging & Stok Akhir',
    dynamic_count: 'Dynamic Counting (Penugasan SKU Terpilih)',
    opname: 'Stock Opname (Blank Count & Recount)',
    adjust: 'Penyesuaian Stok Packaging (Adjust Plus / Minus)',
    counting_detail: 'Detail Stock Opname (Log Breakdown per Putaran)',
    history: 'History Movement Stock',
    inbound: 'Penerimaan Barang Masuk (Inbound)',
    outbound: 'Pengeluaran Barang Keluar (Outbound)',
    tasks: 'Manajemen Penugasan Operator (Task Dispatch)',
    mutations: 'Buku Mutasi & Audit Trail Stok',
    users: 'Manajemen User & Role',
    permissions: 'Otorisasi & Pengaturan Hak Akses Menu',
    maintenance: 'Pembersihan & Maintenance Database (Teknisi)'
  };
  const titleEl = document.getElementById('adminPageTitle');
  if (titleEl) titleEl.innerText = titles[tabName] || 'Dashboard';

  // Refresh tab specific data immediately on click
  if (tabName === 'dashboard') { loadDashboardStockSummary(); loadStats(true); }
  if (tabName === 'inventory') { loadMaterials(); }
  if (tabName === 'dynamic_count') { loadDynamicSessions(); }
  if (tabName === 'opname') { loadOpnames(); }
  if (tabName === 'adjust') { loadDirectAdjustMaterials(); }
  if (tabName === 'counting_detail') { loadCountingDetails(); }
  if (tabName === 'inbound') { populateMaterialSelects(); loadInboundHistory(); }
  if (tabName === 'outbound') { populateMaterialSelects(); loadOutboundHistory(); }
  if (tabName === 'tasks') {
    populateMaterialSelects();
    populateTaskOperators();
    loadTasks();
    initBulkTaskTable();
  }
  if (tabName === 'mutations') { loadMutations(true); }
  if (tabName === 'users') { loadUsers(); }
  if (tabName === 'permissions') { loadPermissionsModule(); }
  if (tabName === 'maintenance') { loadDatabaseStats(); }

  // Re-sync Flatpickr instances for active tab
  setTimeout(initPremiumPickers, 60);
}

// ================= 1.0 DASHBOARD STOCK SUMMARY, TOP 10 CHARTS & TABLES =================
let currentDashFilterType = 'date';
let dashboardStockData = [];
let dashboardPeriodInfo = {};
let dashboardTopInbound = [];
let dashboardTopOutbound = [];
let dashboardCategoryStats = [];
let currentChartMode = 'inbound';
let dashBarChartInstance = null;
let dashCategoryChartInstance = null;

function setDashboardFilterType(type) {
  currentDashFilterType = type;

  const btnDate = document.getElementById('btnDashFilterDate');
  const btnWeek = document.getElementById('btnDashFilterWeek');
  const btnMonth = document.getElementById('btnDashFilterMonth');
  const btnAll = document.getElementById('btnDashFilterAll');

  const containerDate = document.getElementById('dashFilterDateContainer');
  const containerWeek = document.getElementById('dashFilterWeekContainer');
  const containerMonth = document.getElementById('dashFilterMonthContainer');

  const activeClass = 'py-1.5 px-3 rounded-lg bg-emerald-600 text-white shadow-2xs font-bold transition-all';
  const inactiveClass = 'py-1.5 px-3 rounded-lg text-slate-600 hover:text-slate-900 font-bold transition-all';

  if (btnDate) btnDate.className = type === 'date' ? activeClass : inactiveClass;
  if (btnWeek) btnWeek.className = type === 'week' ? activeClass : inactiveClass;
  if (btnMonth) btnMonth.className = type === 'month' ? activeClass : inactiveClass;
  if (btnAll) btnAll.className = type === 'all' ? activeClass : inactiveClass;

  if (containerDate) containerDate.className = type === 'date' ? 'flex items-center gap-2' : 'hidden';
  if (containerWeek) containerWeek.className = type === 'week' ? 'flex items-center gap-2 flex-wrap' : 'hidden';
  if (containerMonth) containerMonth.className = type === 'month' ? 'flex items-center gap-2' : 'hidden';

  loadDashboardStockSummary();
}

function setDashboardDateToday() {
  const inp = document.getElementById('dashInputDate');
  if (inp) {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    inp.value = `${yyyy}-${mm}-${dd}`;
    loadDashboardStockSummary();
  }
}

function updateDashWeekOptions() {
  const year = parseInt(document.getElementById('dashSelectYear')?.value || new Date().getFullYear());
  const month = parseInt(document.getElementById('dashSelectMonth')?.value || (new Date().getMonth() + 1));
  const weekSel = document.getElementById('dashSelectWeek');
  if (!weekSel) return;

  const lastDay = new Date(year, month, 0).getDate();
  const monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
  const mStr = monthNames[month] || '';

  weekSel.innerHTML = `
    <option value="1">Week 1 (01 - 07 ${mStr})</option>
    <option value="2">Week 2 (08 - 14 ${mStr})</option>
    <option value="3">Week 3 (15 - 21 ${mStr})</option>
    <option value="4">Week 4 (22 - 28 ${mStr})</option>
    <option value="5">Week 5 (29 - ${lastDay} ${mStr})</option>
  `;
  const todayDate = new Date().getDate();
  let defaultWeek = 1;
  if (todayDate > 28) defaultWeek = 5;
  else if (todayDate > 21) defaultWeek = 4;
  else if (todayDate > 14) defaultWeek = 3;
  else if (todayDate > 7) defaultWeek = 2;
  weekSel.value = String(defaultWeek);
}

async function loadDashboardStockSummary() {
  const tbody = document.getElementById('dashboardStockSummaryTableBody');
  if (tbody) {
    tbody.innerHTML = `
      <tr>
        <td colspan="11" class="p-8 text-center text-slate-400">
          <span class="material-symbols-outlined text-[28px] animate-spin text-emerald-600">progress_activity</span>
          <p class="text-xs font-semibold text-slate-600 mt-2">Memuat ringkasan stok, top 10 & visualisasi grafik...</p>
        </td>
      </tr>
    `;
  }

  const queryParams = new URLSearchParams({
    action: 'stock_summary',
    filter_type: currentDashFilterType
  });

  if (currentDashFilterType === 'date') {
    const dateVal = document.getElementById('dashInputDate')?.value || '';
    if (dateVal) queryParams.append('date', dateVal);
  } else if (currentDashFilterType === 'week') {
    const yearVal = document.getElementById('dashSelectYear')?.value || '2026';
    const monthVal = document.getElementById('dashSelectMonth')?.value || '8';
    const weekVal = document.getElementById('dashSelectWeek')?.value || '4';
    queryParams.append('year', yearVal);
    queryParams.append('month', monthVal);
    queryParams.append('week', weekVal);
  } else if (currentDashFilterType === 'month') {
    const yearVal = document.getElementById('dashSelectYearOnly')?.value || '2026';
    const monthVal = document.getElementById('dashSelectMonthOnly')?.value || '8';
    queryParams.append('year', yearVal);
    queryParams.append('month', monthVal);
  }

  const res = await App.fetchJson(`../api/stats.php?${queryParams.toString()}`);
  if (res.success) {
    dashboardStockData = res.data || [];
    dashboardPeriodInfo = res.period || {};
    dashboardTopInbound = res.top_inbound || [];
    dashboardTopOutbound = res.top_outbound || [];
    dashboardCategoryStats = res.category_stats || [];
    const sum = res.summary || {};

    const elPeriod = document.getElementById('dashActivePeriodBadge');
    if (elPeriod) elPeriod.innerText = dashboardPeriodInfo.label || 'Periode Aktif';

    // Grand KPI Cards
    const elStockUnits = document.getElementById('dashKpiTotalStockUnits');
    const elIn = document.getElementById('dashKpiTotalInbound');
    const elOut = document.getElementById('dashKpiTotalOutbound');
    const elAdj = document.getElementById('dashKpiTotalAdjustment');
    const elAdjSub = document.getElementById('dashKpiAdjSubtext');
    const elNet = document.getElementById('dashKpiNetFlow');
    const elCrit = document.getElementById('dashKpiCriticalStock');

    const totalStockDisplay = sum.total_warehouse_stock !== undefined ? sum.total_warehouse_stock : sum.total_ending_stock;
    if (elStockUnits) elStockUnits.innerText = `${App.formatNumber(totalStockDisplay || 0)} Pcs`;
    if (elIn) elIn.innerText = `+${App.formatNumber(sum.total_inbound || 0)}`;
    if (elOut) elOut.innerText = `-${App.formatNumber(sum.total_outbound || 0)}`;
    
    if (elAdj) {
      const adjVal = sum.total_adjustment || 0;
      const prefix = adjVal > 0 ? '+' : '';
      elAdj.innerText = `${prefix}${App.formatNumber(adjVal)}`;
      if (adjVal > 0) elAdj.className = 'text-xl lg:text-2xl font-black tracking-tight text-blue-700';
      else if (adjVal < 0) elAdj.className = 'text-xl lg:text-2xl font-black tracking-tight text-rose-700';
      else elAdj.className = 'text-xl lg:text-2xl font-black tracking-tight text-slate-700';
    }

    if (elAdjSub) {
      const plusVal = sum.total_adjustment_plus || 0;
      const minVal = Math.abs(sum.total_adjustment_minus || 0);
      if (plusVal > 0 || minVal > 0) {
        elAdjSub.innerText = `+${App.formatNumber(plusVal)} / -${App.formatNumber(minVal)}`;
      } else {
        elAdjSub.innerText = 'Tidak ada selisih';
      }
    }

    if (elNet) {
      const netVal = sum.net_flow !== undefined ? sum.net_flow : ((sum.total_inbound || 0) - (sum.total_outbound || 0) + (sum.total_adjustment || 0));
      const prefix = netVal > 0 ? '+' : '';
      elNet.innerText = `${prefix}${App.formatNumber(netVal)}`;
      elNet.className = `text-xl lg:text-2xl font-black tracking-tight ${netVal >= 0 ? 'text-indigo-900' : 'text-rose-700'}`;
    }

    if (elCrit) elCrit.innerText = `${App.formatNumber(sum.critical_stock_count || 0)} SKU`;

    // Render Charts
    renderDashboardCharts();

    // Render Top 10 Tables
    renderDashboardTopTables();

    // Render Operator Process KPIs & Leaderboard
    dashboardOperatorKpi = res.operator_kpi || {};
    renderDashboardOperatorKpi();

    // Render Main Stock Table
    populateDashCategoryFilter();
    renderDashboardTable();
  } else {
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="11" class="p-8 text-center text-rose-500 text-xs font-semibold">
            Gagal memuat data: ${res.message || 'Terjadi kesalahan sistem'}
          </td>
        </tr>
      `;
    }
  }
}

// ================= 1.1 DASHBOARD INTERACTIVE NAVIGATION (CONTEXT-AWARE FILTERS) =================
function getDashboardActiveDate() {
  if (currentDashFilterType === 'date') {
    return document.getElementById('dashInputDate')?.value?.trim() || '';
  }
  return '';
}

function setFilterDateInput(selector, dateVal) {
  const el = document.querySelector(selector);
  if (!el) return;
  if (el._flatpickr) {
    if (dateVal) {
      el._flatpickr.setDate(dateVal, false);
    } else {
      el._flatpickr.clear();
    }
  } else {
    el.value = dateVal || '';
  }
}

function navigateFromDashboard(targetTab, filterVal = null) {
  if (!targetTab) return;
  const activeDate = getDashboardActiveDate();

  // 1. Switch to Target Tab View
  switchAdminTab(targetTab);

  // 2. Apply contextual filters matching the Dashboard Card
  if (targetTab === 'inventory') {
    setTimeout(() => {
      const statusFilter = document.getElementById('inventoryStatusFilter');
      const searchInput = document.getElementById('inventorySearch');
      const catFilter = document.getElementById('inventoryCategoryFilter');
      if (statusFilter) statusFilter.value = filterVal || 'all';
      if (searchInput) searchInput.value = '';
      if (catFilter) catFilter.value = 'all';
      loadMaterials();
    }, 60);
  } else if (targetTab === 'inbound') {
    setTimeout(() => {
      setFilterDateInput('#inboundDateFilter', activeDate);
      const searchInput = document.getElementById('inboundSearchInput');
      if (searchInput) searchInput.value = '';
      loadInboundHistory();
    }, 60);
  } else if (targetTab === 'outbound') {
    setTimeout(() => {
      setFilterDateInput('#outboundDateFilter', activeDate);
      const searchInput = document.getElementById('outboundSearchInput');
      const typeFilter = document.getElementById('outboundTypeFilter');
      const statusFilter = document.getElementById('outboundStatusFilter');
      if (searchInput) searchInput.value = '';
      if (typeFilter) typeFilter.value = 'ALL';
      if (statusFilter) statusFilter.value = 'ALL';
      loadOutboundHistory();
    }, 60);
  } else if (targetTab === 'adjust') {
    setTimeout(() => {
      switchAdjustSubTab('history');
      setFilterDateInput('#adjustHistoryDateFilter', activeDate);
      const searchInput = document.getElementById('adjustHistorySearchInput');
      if (searchInput) searchInput.value = '';
      loadAdjustHistory();
    }, 60);
  } else if (targetTab === 'tasks') {
    setTimeout(() => {
      switchTaskSubView('list');
      setFilterDateInput('#taskDateFilter', activeDate);
      const searchInput = document.getElementById('taskSearchInput');
      if (searchInput) searchInput.value = '';
      const statusFilter = document.getElementById('taskStatusFilter');
      if (statusFilter) {
        statusFilter.value = filterVal || 'ALL';
      }
      loadTasks();
    }, 60);
  }
}

// ================= 1.1 DASHBOARD CHARTS (CHART.JS) =================
function switchDashboardChart(mode) {
  currentChartMode = mode;

  const btnIn = document.getElementById('btnChartTabIn');
  const btnOut = document.getElementById('btnChartTabOut');

  const activeInClass = 'py-1 px-3 rounded-md bg-emerald-600 text-white shadow-2xs font-bold transition-all';
  const activeOutClass = 'py-1 px-3 rounded-md bg-rose-600 text-white shadow-2xs font-bold transition-all';
  const inactiveClass = 'py-1 px-3 rounded-md text-slate-600 hover:text-slate-900 font-bold transition-all';

  if (btnIn) btnIn.className = mode === 'inbound' ? activeInClass : inactiveClass;
  if (btnOut) btnOut.className = mode === 'outbound' ? activeOutClass : inactiveClass;

  renderDashboardBarChart();
}

function renderDashboardCharts() {
  renderDashboardBarChart();
  renderDashboardCategoryChart();
}

function renderDashboardBarChart() {
  if (typeof Chart === 'undefined') return;

  const canvas = document.getElementById('dashBarChartCanvas');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  if (dashBarChartInstance) {
    dashBarChartInstance.destroy();
    dashBarChartInstance = null;
  }

  const isInc = currentChartMode === 'inbound';
  const sourceList = isInc ? dashboardTopInbound : dashboardTopOutbound;

  const labels = [];
  const dataValues = [];
  const bgColors = [];
  const borderColors = [];

  const baseColor = isInc ? 'rgba(5, 150, 105, 0.85)' : 'rgba(225, 29, 72, 0.85)';
  const hoverColor = isInc ? 'rgba(4, 120, 87, 1)' : 'rgba(190, 18, 60, 1)';
  const borderColor = isInc ? '#059669' : '#e11d48';

  if (sourceList.length === 0) {
    labels.push('Belum ada transaksi');
    dataValues.push(0);
    bgColors.push('rgba(203, 213, 225, 0.5)');
    borderColors.push('#94a3b8');
  } else {
    sourceList.forEach((item, i) => {
      // Full non-truncated label with smart multi-line wrapping
      const cleanCode = String(item.code || '').trim();
      const cleanName = String(item.name || '').trim();
      const full = `${cleanCode} - ${cleanName}`;

      if (full.length <= 26) {
        labels.push(full);
      } else {
        const words = cleanName.split(' ');
        let line1 = `${cleanCode} -`;
        let line2 = '';
        let switched = false;

        for (const w of words) {
          if (!w) continue;
          if (!switched && (line1 + ' ' + w).length <= 26) {
            line1 += ' ' + w;
          } else {
            switched = true;
            line2 = line2 ? (line2 + ' ' + w) : w;
          }
        }
        labels.push(line2 ? [line1, line2] : line1);
      }

      dataValues.push(parseInt(item.total_qty || 0));
      bgColors.push(baseColor);
      borderColors.push(borderColor);
    });
  }

  dashBarChartInstance = new Chart(ctx, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [{
        label: isInc ? 'Barang Masuk (Qty)' : 'Barang Keluar (Qty)',
        data: dataValues,
        backgroundColor: bgColors,
        hoverBackgroundColor: hoverColor,
        borderColor: borderColors,
        borderWidth: 1,
        borderRadius: 6,
        barPercentage: 0.75,
        categoryPercentage: 0.85
      }]
    },
    options: {
      indexAxis: 'y', // Horizontal bar chart for clean SKU reading
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: '#0f172a',
          titleFont: { size: 12, weight: 'bold' },
          bodyFont: { size: 12 },
          padding: 10,
          cornerRadius: 8,
          callbacks: {
            title: function(context) {
              const item = sourceList[context[0]?.dataIndex];
              return item ? `${item.code} - ${item.name}` : (context[0]?.label || '');
            },
            label: function(context) {
              const val = context.raw || 0;
              const unit = sourceList[context.dataIndex]?.unit || 'Pcs';
              return ` Total ${isInc ? 'Masuk' : 'Keluar'}: ${App.formatNumber(val)} ${unit}`;
            },
            afterLabel: function(context) {
              const item = sourceList[context.dataIndex];
              if (!item) return '';
              return ` Sisa Stok Saat Ini: ${App.formatNumber(item.current_stock || 0)} ${item.unit || 'Pcs'}\n Rak: ${item.rack_location || '-'}`;
            }
          }
        }
      },
      scales: {
        x: {
          beginAtZero: true,
          grid: {
            color: '#f1f5f9',
            drawBorder: false
          },
          ticks: {
            font: { size: 11 },
            color: '#64748b',
            callback: function(value) {
              if (value >= 1000000) return (value / 1000000) + 'M';
              if (value >= 1000) return (value / 1000) + 'K';
              return value;
            }
          }
        },
        y: {
          grid: {
            display: false
          },
          ticks: {
            font: { size: 10, weight: '600' },
            color: '#334155',
            autoSkip: false
          }
        }
      }
    }
  });
}

function renderDashboardCategoryChart() {
  if (typeof Chart === 'undefined') return;

  const canvas = document.getElementById('dashCategoryChartCanvas');
  const legendContainer = document.getElementById('dashCategoryLegendList');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  if (dashCategoryChartInstance) {
    dashCategoryChartInstance.destroy();
    dashCategoryChartInstance = null;
  }

  const palette = [
    '#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', 
    '#ec4899', '#06b6d4', '#64748b', '#14b8a6', '#f97316'
  ];

  const labels = [];
  const dataValues = [];
  const colors = [];

  const totalStock = dashboardCategoryStats.reduce((acc, c) => acc + parseInt(c.total_stock || 0), 0);

  dashboardCategoryStats.forEach((cat, idx) => {
    labels.push(cat.category);
    dataValues.push(parseInt(cat.total_stock || 0));
    colors.push(palette[idx % palette.length]);
  });

  if (labels.length === 0) {
    labels.push('Tanpa Data');
    dataValues.push(1);
    colors.push('#e2e8f0');
  }

  dashCategoryChartInstance = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: labels,
      datasets: [{
        data: dataValues,
        backgroundColor: colors,
        hoverOffset: 6,
        borderWidth: 2,
        borderColor: '#ffffff'
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: '68%',
      plugins: {
        legend: {
          display: false
        },
        tooltip: {
          backgroundColor: '#0f172a',
          titleFont: { size: 12, weight: 'bold' },
          bodyFont: { size: 12 },
          padding: 10,
          cornerRadius: 8,
          callbacks: {
            label: function(context) {
              const val = context.raw || 0;
              const pct = totalStock > 0 ? ((val / totalStock) * 100).toFixed(1) : '0';
              return ` ${context.label}: ${App.formatNumber(val)} Pcs (${pct}%)`;
            }
          }
        }
      }
    }
  });

  // Render Legend list
  if (legendContainer) {
    legendContainer.innerHTML = dashboardCategoryStats.map((cat, idx) => {
      const color = palette[idx % palette.length];
      const stock = parseInt(cat.total_stock || 0);
      const pct = totalStock > 0 ? ((stock / totalStock) * 100).toFixed(1) : '0';
      return `
        <div class="flex items-center justify-between p-1.5 rounded-lg bg-slate-50 border border-slate-100">
          <div class="flex items-center gap-1.5 truncate">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: ${color}"></span>
            <span class="font-bold text-slate-700 truncate">${escapeHtml(cat.category)}</span>
          </div>
          <span class="font-mono text-slate-500 font-bold ml-1 shrink-0">${pct}%</span>
        </div>
      `;
    }).join('');
  }
}

// ================= 1.2 DASHBOARD TOP 10 TABLES =================
function renderDashboardTopTables() {
  const tbodyIn = document.getElementById('dashTopInboundTableBody');
  const tbodyOut = document.getElementById('dashTopOutboundTableBody');
  const badgeIn = document.getElementById('dashTopInboundBadge');
  const badgeOut = document.getElementById('dashTopOutboundBadge');

  const totalInQty = dashboardTopInbound.reduce((acc, it) => acc + parseInt(it.total_qty || 0), 0);
  const totalOutQty = dashboardTopOutbound.reduce((acc, it) => acc + parseInt(it.total_qty || 0), 0);

  if (badgeIn) badgeIn.innerText = `${dashboardTopInbound.length} SKU (+${App.formatNumber(totalInQty)})`;
  if (badgeOut) badgeOut.innerText = `${dashboardTopOutbound.length} SKU (-${App.formatNumber(totalOutQty)})`;

  // Render Top Inbound Table
  if (tbodyIn) {
    if (dashboardTopInbound.length === 0) {
      tbodyIn.innerHTML = `
        <tr>
          <td colspan="6" class="p-6 text-center text-slate-400">
            <span class="material-symbols-outlined text-[28px] text-slate-300">inbox</span>
            <p class="text-xs font-semibold text-slate-500 mt-1">Belum ada transaksi barang masuk pada periode ini</p>
          </td>
        </tr>
      `;
    } else {
      tbodyIn.innerHTML = dashboardTopInbound.map((item, idx) => {
        let rankBadge = `<span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black inline-flex items-center justify-center">${idx + 1}</span>`;
        if (idx === 0) rankBadge = `<span class="w-6 h-6 rounded-full bg-amber-400 text-amber-950 text-[10px] font-black inline-flex items-center justify-center shadow-xs">🥇</span>`;
        else if (idx === 1) rankBadge = `<span class="w-6 h-6 rounded-full bg-slate-300 text-slate-800 text-[10px] font-black inline-flex items-center justify-center shadow-xs">🥈</span>`;
        else if (idx === 2) rankBadge = `<span class="w-6 h-6 rounded-full bg-amber-700 text-amber-50 text-[10px] font-black inline-flex items-center justify-center shadow-xs">🥉</span>`;

        return `
          <tr class="hover:bg-emerald-50/40 border-b border-slate-100 transition-colors">
            <td class="p-2.5 text-center">${rankBadge}</td>
            <td class="p-2.5 font-mono font-bold text-emerald-950 whitespace-nowrap">${escapeHtml(item.code)}</td>
            <td class="p-2.5">
              <div class="font-bold text-slate-900 line-clamp-1" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
              <div class="text-[10px] text-slate-400 font-mono">${escapeHtml(item.rack_location)} &bull; ${escapeHtml(item.category)}</div>
            </td>
            <td class="p-2.5 text-right font-mono font-black text-emerald-700 whitespace-nowrap bg-emerald-50/30">+${App.formatNumber(item.total_qty)} ${escapeHtml(item.unit)}</td>
            <td class="p-2.5 text-center font-mono text-slate-500 font-bold">${item.tx_count || 1}x</td>
            <td class="p-2.5 text-right font-mono font-black text-slate-800 whitespace-nowrap">${App.formatNumber(item.current_stock)}</td>
          </tr>
        `;
      }).join('');
    }
  }

  // Render Top Outbound Table
  if (tbodyOut) {
    if (dashboardTopOutbound.length === 0) {
      tbodyOut.innerHTML = `
        <tr>
          <td colspan="6" class="p-6 text-center text-slate-400">
            <span class="material-symbols-outlined text-[28px] text-slate-300">outbox</span>
            <p class="text-xs font-semibold text-slate-500 mt-1">Belum ada transaksi barang keluar pada periode ini</p>
          </td>
        </tr>
      `;
    } else {
      tbodyOut.innerHTML = dashboardTopOutbound.map((item, idx) => {
        let rankBadge = `<span class="w-6 h-6 rounded-full bg-slate-100 text-slate-600 text-[10px] font-black inline-flex items-center justify-center">${idx + 1}</span>`;
        if (idx === 0) rankBadge = `<span class="w-6 h-6 rounded-full bg-rose-500 text-white text-[10px] font-black inline-flex items-center justify-center shadow-xs">🥇</span>`;
        else if (idx === 1) rankBadge = `<span class="w-6 h-6 rounded-full bg-rose-400 text-white text-[10px] font-black inline-flex items-center justify-center shadow-xs">🥈</span>`;
        else if (idx === 2) rankBadge = `<span class="w-6 h-6 rounded-full bg-rose-300 text-rose-950 text-[10px] font-black inline-flex items-center justify-center shadow-xs">🥉</span>`;

        return `
          <tr class="hover:bg-rose-50/40 border-b border-slate-100 transition-colors">
            <td class="p-2.5 text-center">${rankBadge}</td>
            <td class="p-2.5 font-mono font-bold text-rose-950 whitespace-nowrap">${escapeHtml(item.code)}</td>
            <td class="p-2.5">
              <div class="font-bold text-slate-900 line-clamp-1" title="${escapeHtml(item.name)}">${escapeHtml(item.name)}</div>
              <div class="text-[10px] text-slate-400 font-mono">${escapeHtml(item.rack_location)} &bull; ${escapeHtml(item.category)}</div>
            </td>
            <td class="p-2.5 text-right font-mono font-black text-rose-700 whitespace-nowrap bg-rose-50/30">-${App.formatNumber(item.total_qty)} ${escapeHtml(item.unit)}</td>
            <td class="p-2.5 text-center font-mono text-slate-500 font-bold">${item.tx_count || 1}x</td>
            <td class="p-2.5 text-right font-mono font-black text-slate-800 whitespace-nowrap">${App.formatNumber(item.current_stock)}</td>
          </tr>
        `;
      }).join('');
    }
  }
}

// ================= 1.3 DASHBOARD OPERATOR PROCESS KPIS =================
let dashboardOperatorKpi = {};

function renderDashboardOperatorKpi() {
  const kpi = dashboardOperatorKpi || {};
  const rateEl = document.getElementById('dashKpiOpRate');
  const ratioEl = document.getElementById('dashKpiOpCompletedRatio');
  const barEl = document.getElementById('dashKpiOpProgressBar');
  const inProgEl = document.getElementById('dashKpiOpInProgress');
  const pendEl = document.getElementById('dashKpiOpPending');
  const avgDurEl = document.getElementById('dashKpiOpAvgDuration');

  const totTasks = kpi.total_tasks || 0;
  const compTasks = kpi.completed_tasks || 0;
  const inProgTasks = kpi.in_progress_tasks || 0;
  const pendTasks = kpi.pending_tasks || 0;
  const rate = kpi.completion_rate !== undefined ? kpi.completion_rate : (totTasks > 0 ? Math.round((compTasks / totTasks) * 100) : 0);
  const avgDurSec = kpi.avg_duration_seconds || 0;

  if (rateEl) rateEl.innerText = `${rate}%`;
  if (ratioEl) ratioEl.innerText = `(${App.formatNumber(compTasks)}/${App.formatNumber(totTasks)} Selesai)`;
  if (barEl) barEl.style.width = `${Math.min(100, Math.max(0, rate))}%`;
  if (inProgEl) inProgEl.innerText = `${App.formatNumber(inProgTasks)} Task`;
  if (pendEl) pendEl.innerText = `${App.formatNumber(pendTasks)} Task`;
  if (avgDurEl) avgDurEl.innerText = App.formatDuration(avgDurSec);

  // 1. Leaderboard Table
  const leaderboardBody = document.getElementById('dashOpLeaderboardBody');
  const leaderboard = kpi.leaderboard || [];
  if (leaderboardBody) {
    if (leaderboard.length === 0) {
      leaderboardBody.innerHTML = `
        <tr>
          <td colspan="5" class="p-6 text-center text-slate-400 text-xs font-medium">
            Belum ada data aktivitas operator pada periode ini.
          </td>
        </tr>
      `;
    } else {
      leaderboardBody.innerHTML = leaderboard.map((op, idx) => {
        let rankBadge = `<span class="font-bold text-slate-400 font-mono text-[11px]">#${idx + 1}</span>`;
        if (idx === 0) rankBadge = '<span class="text-sm" title="Peringkat 1">🥇</span>';
        else if (idx === 1) rankBadge = '<span class="text-sm" title="Peringkat 2">🥈</span>';
        else if (idx === 2) rankBadge = '<span class="text-sm" title="Peringkat 3">🥉</span>';

        const completed = parseInt(op.completed_count || 0);
        const assigned = parseInt(op.total_assigned || 0);
        const pickedQty = parseInt(op.total_picked_qty || 0);
        const avgSec = parseInt(op.avg_duration_seconds || 0);

        return `
          <tr class="hover:bg-slate-50 border-b border-slate-100 text-xs transition-colors">
            <td class="p-2.5 text-center font-bold">${rankBadge}</td>
            <td class="p-2.5">
              <p class="font-bold text-slate-900 leading-tight">${escapeHtml(op.operator_name)}</p>
              <span class="text-[10px] text-slate-400 font-medium">${escapeHtml(op.operator_shift || 'Shift')}</span>
            </td>
            <td class="p-2.5 text-center">
              <span class="px-2 py-0.5 rounded font-black text-xs ${completed > 0 ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-slate-100 text-slate-500'}">
                ${App.formatNumber(completed)} <span class="font-normal text-[10px] text-slate-400">/ ${App.formatNumber(assigned)}</span>
              </span>
            </td>
            <td class="p-2.5 text-right font-mono font-extrabold text-slate-800">
              ${App.formatNumber(pickedQty)} <span class="text-[10px] text-slate-400 font-normal">Pcs</span>
            </td>
            <td class="p-2.5 text-center font-mono font-bold text-indigo-700">
              ${avgSec > 0 ? App.formatDuration(avgSec) : '-'}
            </td>
          </tr>
        `;
      }).join('');
    }
  }

  // 2. Recent Tasks Feed
  const recentTasksBody = document.getElementById('dashOpRecentTasksBody');
  const recentTasks = kpi.recent_tasks || [];
  if (recentTasksBody) {
    if (recentTasks.length === 0) {
      recentTasksBody.innerHTML = `
        <tr>
          <td colspan="5" class="p-6 text-center text-slate-400 text-xs font-medium">
            Tidak ada task picking aktif pada periode ini.
          </td>
        </tr>
      `;
    } else {
      recentTasksBody.innerHTML = recentTasks.map(t => {
        let statusBadge = '';
        if (t.status === 'COMPLETED') {
          statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">Selesai</span>';
        } else if (t.status === 'IN_PROGRESS') {
          statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-amber-50 text-amber-900 border border-amber-300 inline-flex items-center gap-1 shadow-2xs"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>Picking</span>';
        } else if (t.status === 'CANCELLED') {
          statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">Batal</span>';
        } else {
          statusBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">Pending</span>';
        }

        const isUrgent = t.priority === 'URGENT' || t.priority === 'CRITICAL';

        return `
          <tr class="hover:bg-slate-50 border-b border-slate-100 text-xs transition-colors">
            <td class="p-2.5 font-mono font-bold text-slate-900 whitespace-nowrap">
              ${escapeHtml(t.task_no)}
              ${isUrgent ? '<span class="ml-1 px-1 py-0.2 rounded bg-rose-100 text-rose-800 font-bold text-[9px] border border-rose-200">URGENT</span>' : ''}
            </td>
            <td class="p-2.5">
              <p class="font-bold text-slate-900 leading-tight">${escapeHtml(t.material_name)}</p>
              <span class="text-[10px] text-slate-400">Rak: ${escapeHtml(t.rack_location || '-')} &bull; Ke: ${escapeHtml(t.destination || 'Line')}</span>
            </td>
            <td class="p-2.5 text-center font-mono font-extrabold text-indigo-900">
              ${App.formatNumber(t.target_qty)} <span class="text-[10px] text-slate-400 font-normal">${escapeHtml(t.material_unit || 'Pcs')}</span>
            </td>
            <td class="p-2.5">
              <span class="font-bold text-slate-800 block leading-tight">${escapeHtml(t.operator_name)}</span>
              <span class="text-[10px] text-slate-400">${escapeHtml(t.operator_shift || 'Shift')}</span>
            </td>
            <td class="p-2.5 text-center whitespace-nowrap">${statusBadge}</td>
          </tr>
        `;
      }).join('');
    }
  }
}

function populateDashCategoryFilter() {
  const catSel = document.getElementById('dashCategoryFilter');
  if (!catSel || catSel.children.length > 1) return;

  const categories = [...new Set(dashboardStockData.map(d => d.category).filter(Boolean))];
  categories.forEach(cat => {
    const opt = document.createElement('option');
    opt.value = cat;
    opt.innerText = cat;
    catSel.appendChild(opt);
  });
}

function renderDashboardTable() {
  const tbody = document.getElementById('dashboardStockSummaryTableBody');
  if (!tbody) return;

  const search = document.getElementById('dashSearchInput')?.value.trim().toLowerCase() || '';
  const catFilter = document.getElementById('dashCategoryFilter')?.value || 'all';
  const statusFilter = document.getElementById('dashStatusFilter')?.value || 'all';

  let filtered = dashboardStockData.filter(item => {
    if (search) {
      const matchCode = (item.code || '').toLowerCase().includes(search);
      const matchName = (item.name || '').toLowerCase().includes(search);
      const matchRack = (item.rack_location || '').toLowerCase().includes(search);
      if (!matchCode && !matchName && !matchRack) return false;
    }

    if (catFilter !== 'all' && item.category !== catFilter) return false;

    if (statusFilter === 'activity_only' && !item.has_activity) return false;
    if (statusFilter === 'adjusted_only' && item.adjustment === 0) return false;
    if (statusFilter === 'low' && item.status !== 'low') return false;
    if (statusFilter === 'empty' && item.status !== 'empty') return false;
    if (statusFilter === 'safe' && item.status !== 'safe') return false;

    return true;
  });

  if (filtered.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="11" class="p-8 text-center text-slate-400">
          <span class="material-symbols-outlined text-[32px] text-slate-300">inventory_2</span>
          <p class="text-xs font-bold text-slate-700 mt-1">Tidak ada data material yang sesuai filter</p>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = filtered.map((item, idx) => {
    let adjBadge = '<span class="text-slate-300 font-mono">0</span>';
    if (item.adjustment > 0) {
      adjBadge = `<span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-bold border border-blue-200">+${App.formatNumber(item.adjustment)}</span>`;
    } else if (item.adjustment < 0) {
      adjBadge = `<span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 font-bold border border-rose-200">${App.formatNumber(item.adjustment)}</span>`;
    }

    let statusBadge = '<span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-800 font-bold text-[10px] border border-emerald-200">Aman</span>';
    if (item.status === 'empty') {
      statusBadge = '<span class="px-2 py-0.5 rounded bg-rose-100 text-rose-800 font-bold text-[10px] border border-rose-300">Habis</span>';
    } else if (item.status === 'low') {
      statusBadge = '<span class="px-2 py-0.5 rounded bg-amber-50 text-amber-900 font-bold text-[10px] border border-amber-300">Menipis</span>';
    }

    return `
      <tr class="hover:bg-slate-50 border-b border-slate-100 text-xs">
        <td class="p-3 text-center text-slate-400 font-mono">${idx + 1}</td>
        <td class="p-3 font-mono font-bold text-indigo-900 whitespace-nowrap">${escapeHtml(item.code)}</td>
        <td class="p-3">
          <div class="font-bold text-slate-900">${escapeHtml(item.name)}</div>
          <div class="text-[11px] text-slate-400 font-mono">Rak: ${escapeHtml(item.rack_location)} &bull; ${escapeHtml(item.category)}</div>
        </td>
        <td class="p-3 text-center font-semibold text-slate-600">${escapeHtml(item.unit)}</td>
        <td class="p-3 text-slate-700">${escapeHtml(item.rack_location)}</td>
        <td class="p-3 text-center font-mono text-slate-600 bg-slate-50/50">${App.formatNumber(item.beginning_stock)}</td>
        <td class="p-3 text-center font-mono font-bold text-emerald-700 bg-emerald-50/20">${item.inbound > 0 ? '+' + App.formatNumber(item.inbound) : '0'}</td>
        <td class="p-3 text-center font-mono font-bold text-amber-700 bg-amber-50/20">${item.outbound > 0 ? '-' + App.formatNumber(item.outbound) : '0'}</td>
        <td class="p-3 text-center whitespace-nowrap bg-blue-50/20">${adjBadge}</td>
        <td class="p-3 text-center font-mono font-black text-slate-900 bg-slate-100/60">${App.formatNumber(item.ending_stock)}</td>
        <td class="p-3 text-center whitespace-nowrap">${statusBadge}</td>
      </tr>
    `;
  }).join('');
}

function exportDashboardSummaryExcel() {
  if (!dashboardStockData || dashboardStockData.length === 0) {
    App.toast('Tidak ada data untuk di-export.', 'warning');
    return;
  }

  const periodLabel = dashboardPeriodInfo.label || 'Laporan Ringkasan Stok';
  const exportRows = dashboardStockData.map((d, idx) => ({
    'No': idx + 1,
    'Item No': d.code,
    'Deskripsi Material Packaging': d.name,
    'Satuan': d.unit,
    'Lokasi Rak': d.rack_location,
    'Kategori': d.category,
    'Stok Awal': d.beginning_stock,
    'Barang Masuk (+)': d.inbound,
    'Barang Keluar (-)': d.outbound,
    'Adjustment (+/-)': d.adjustment,
    'Stok Akhir': d.ending_stock,
    'Min Stock': d.min_stock,
    'Status': d.status === 'safe' ? 'Aman' : (d.status === 'low' ? 'Menipis' : 'Habis')
  }));

  if (window.XLSX) {
    const ws = XLSX.utils.json_to_sheet(exportRows);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Ringkasan Stok');
    const safePeriod = periodLabel.replace(/[^a-zA-Z0-9_\-]/g, '_');
    XLSX.writeFile(wb, `Ringkasan_Stok_${safePeriod}.xlsx`);
    App.toast('File Excel berhasil di-download!', 'success');
  } else {
    App.toast('Fitur export Excel sedang diproses...', 'info');
  }
}

// 1. STATS LOADER
async function loadStats(silent = false) {
  const res = await App.fetchJson('../api/stats.php');
  if (res.success && res.stats) {
    const s = res.stats;
    const elTot = document.getElementById('statTotalMaterials');
    const elStock = document.getElementById('statTotalStock');
    const elLow = document.getElementById('statLowStock');
    const elTasks = document.getElementById('statActiveTasks');
    const elIn = document.getElementById('statTodayInbound');
    const elOut = document.getElementById('statTodayOutbound');

    if (elTot) elTot.innerText = App.formatNumber(s.total_materials);
    if (elStock) elStock.innerText = App.formatNumber(s.total_stock_units);
    if (elLow) elLow.innerText = App.formatNumber(s.total_critical_stock);
    if (elTasks) elTasks.innerText = App.formatNumber(s.active_tasks_count);
    if (elIn) elIn.innerText = App.formatNumber(s.today_inbound_qty);
    if (elOut) elOut.innerText = App.formatNumber(s.today_outbound_qty);

    const alertBadge = document.getElementById('sidebarAlertBadge');
    if (alertBadge) {
      if (s.total_critical_stock > 0) {
        alertBadge.innerText = s.total_critical_stock;
        alertBadge.classList.remove('hidden');
      } else {
        alertBadge.classList.add('hidden');
      }
    }
  }
}

// 2. MATERIALS LOADER & TABLE
async function loadMaterials() {
  const search = document.getElementById('inventorySearch')?.value || '';
  const category = document.getElementById('inventoryCategoryFilter')?.value || 'all';
  const status = document.getElementById('inventoryStatusFilter')?.value || 'all';

  const query = new URLSearchParams({
    action: 'list',
    search,
    category,
    status
  });

  const res = await App.fetchJson(`../api/materials.php?${query.toString()}`);
  if (res.success) {
    allMaterials = res.data;
    renderMaterialsTable(allMaterials);
    populateMaterialSelects();
    populateCategoryFilters();
  }
}

function renderMaterialsTable(materials) {
  const tbody = document.getElementById('inventoryTableBody');
  if (!tbody) return;

  if (materials.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="11" class="p-8 text-center text-slate-400">
          <span class="material-symbols-outlined text-[32px] text-slate-300 mb-1">inventory_2</span>
          <p class="text-xs font-medium">Tidak ada packaging material yang ditemukan.</p>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = materials.map(m => {
    let statusBadge = '';
    if (m.stock_badge === 'empty') {
      statusBadge = '<span class="badge-low-stock px-2 py-0.5 rounded text-[11px]">HABIS (0)</span>';
    } else if (m.stock_badge === 'low') {
      statusBadge = `<span class="badge-low-stock px-2 py-0.5 rounded text-[11px]">MENIPIS (&le; ${m.min_stock})</span>`;
    } else {
      statusBadge = '<span class="badge-safe-stock px-2 py-0.5 rounded text-[11px]">AMAN</span>';
    }

    return `
      <tr class="hover:bg-slate-50 border-b border-slate-100 transition-colors">
        <td class="p-3 whitespace-nowrap">
          <button type="button" onclick="openMaterialHistoryView(${m.id})" title="Klik untuk lihat riwayat mutasi keluar masuk di halaman penuh" 
            class="font-mono font-bold text-xs text-emerald-800 hover:text-emerald-950 hover:bg-emerald-100/70 inline-flex items-center gap-1 bg-emerald-50 px-2 py-1 rounded border border-emerald-200 transition-colors shadow-2xs">
            <span class="material-symbols-outlined text-[14px]">history</span>
            <span>${escapeHtml(m.code)}</span>
          </button>
        </td>
        <td class="p-3">
          <p class="font-bold text-slate-900 text-xs">${escapeHtml(m.name)}</p>
          <span class="text-[10px] text-slate-400">Min Safety: ${App.formatNumber(m.min_stock)}</span>
        </td>
        <td class="p-3 text-slate-600 text-xs font-medium">${escapeHtml(m.category || '-')}</td>
        
        <!-- Stok Awal Upload Excel -->
        <td class="p-3 text-center font-mono font-semibold text-slate-600 text-xs">
          ${App.formatNumber(m.initial_upload_stock)}
        </td>

        <!-- Total Masuk (+) -->
        <td class="p-3 text-center font-mono font-bold text-emerald-700 text-xs">
          ${m.total_inbound > 0 ? `+${App.formatNumber(m.total_inbound)}` : '0'}
        </td>

        <!-- Total Keluar (-) -->
        <td class="p-3 text-center font-mono font-bold text-amber-700 text-xs">
          ${m.total_outbound > 0 ? `-${App.formatNumber(m.total_outbound)}` : '0'}
        </td>

        <!-- Sisa Stok Akhir (Calculated) -->
        <td class="p-3 text-center whitespace-nowrap">
          <span class="font-black text-sm ${m.current_stock <= m.min_stock ? 'text-rose-600' : 'text-emerald-800'}">
            ${App.formatNumber(m.current_stock)}
          </span>
        </td>

        <!-- Satuan (UOM) -->
        <td class="p-3 text-center text-xs font-semibold text-slate-700 whitespace-nowrap">
          <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200">${escapeHtml(m.unit || 'Pcs')}</span>
        </td>

        <td class="p-3 text-xs text-slate-700 font-medium whitespace-nowrap">${escapeHtml(m.rack_location)}</td>
        <td class="p-3 whitespace-nowrap">${statusBadge}</td>
        
        <!-- Action Buttons (Icon Only) -->
        <td class="p-3 text-right whitespace-nowrap">
          <div class="inline-flex items-center justify-end gap-1.5">
            <button type="button" onclick="openEditMaterialModal(${m.id})" title="Edit Data Material Packaging" class="p-1.5 rounded-lg bg-blue-50 hover:bg-blue-600 hover:text-white text-blue-800 border border-blue-200 transition-colors inline-flex items-center justify-center shadow-2xs">
              <span class="material-symbols-outlined text-[16px]">edit</span>
            </button>
            <button type="button" onclick="openMaterialHistoryView(${m.id})" title="Lihat History Movement Stock" class="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-800 border border-emerald-200 transition-colors inline-flex items-center justify-center shadow-2xs">
              <span class="material-symbols-outlined text-[16px]">history</span>
            </button>
            <button onclick="quickAssignFromMaterial(${m.id})" title="Tugaskan Pengambilan ke Operator (Assign Task)" class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-800 hover:text-white text-slate-700 border border-slate-200 transition-colors inline-flex items-center justify-center shadow-2xs">
              <span class="material-symbols-outlined text-[16px]">add_task</span>
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

// 2.1 EMBEDDED MATERIAL STOCK CARD & IN/OUT HISTORY VIEW (INSIDE INDEX WITH SIDEBAR)
async function openMaterialHistoryView(materialId, updateUrl = true) {
  const res = await App.fetchJson(`../api/materials.php?action=history&id=${materialId}`);
  if (!res.success || !res.material) {
    App.toast(res.message || 'Gagal memuat data riwayat material', 'error');
    return;
  }

  const m = res.material;
  const history = res.history || [];

  // Update URL Hash
  if (updateUrl) {
    window.location.hash = `history?id=${m.id}`;
  }

  // Populate Top Control Bar
  const itemCodeEl = document.getElementById('viewHistItemCode');
  if (itemCodeEl) itemCodeEl.innerText = m.code;
  const itemNameEl = document.getElementById('viewHistItemName');
  if (itemNameEl) itemNameEl.innerText = m.name;
  
  const downloadBtn = document.getElementById('viewHistDownloadBtn');
  if (downloadBtn) downloadBtn.href = `export.php?type=material_history&id=${m.id}`;

  // Populate Header Info Card
  document.getElementById('viewHistBadgeCode').innerText = m.code;
  document.getElementById('viewHistBadgeCategory').innerText = m.category || 'Packaging';
  
  let statusBadge = '';
  if (m.current_stock <= 0) {
    statusBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">STOK HABIS (0)</span>';
  } else if (m.current_stock <= m.min_stock) {
    statusBadge = `<span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200">STOK MENIPIS (&le; ${App.formatNumber(m.min_stock)})</span>`;
  } else {
    statusBadge = '<span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">STOK AMAN</span>';
  }
  document.getElementById('viewHistBadgeStatus').innerHTML = statusBadge;

  document.getElementById('viewHistHeaderName').innerText = m.name;
  const rackEl = document.getElementById('viewHistRack');
  if (rackEl) rackEl.querySelector('span:last-child').innerText = m.rack_location || '-';
  const minStockEl = document.getElementById('viewHistMinStock');
  if (minStockEl) minStockEl.innerText = `${App.formatNumber(m.min_stock)}`;

  // Populate 4 KPI Formula Breakdown Cards
  document.getElementById('viewHistInitialStock').innerText = `${App.formatNumber(m.initial_upload_stock)}`;
  document.getElementById('viewHistTotalInbound').innerText = `+${App.formatNumber(m.total_inbound)}`;
  document.getElementById('viewHistTotalOutbound').innerText = `-${App.formatNumber(m.total_outbound)}`;
  document.getElementById('viewHistCurrentStock').innerText = `${App.formatNumber(m.current_stock)}`;

  const stockBox = document.getElementById('viewHistStockBox');
  if (stockBox) {
    if (m.current_stock <= m.min_stock) {
      stockBox.className = 'p-4 rounded-xl shadow-sm text-white bg-rose-600';
    } else {
      stockBox.className = 'p-4 rounded-xl shadow-sm text-white bg-emerald-600';
    }
  }

  // Populate Table
  document.getElementById('viewHistRowCountBadge').innerText = `Total ${history.length} Catatan Transaksi`;
  const tbody = document.getElementById('viewHistTableBody');
  if (history.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="8" class="p-8 text-center text-slate-400 font-medium">
          <span class="material-symbols-outlined text-[32px] text-slate-300 mb-1">history</span>
          <p>Belum ada catatan mutasi untuk packaging material ini.</p>
        </td>
      </tr>
    `;
  } else {
    tbody.innerHTML = history.map(h => {
      const isPositive = h.qty_change > 0;
      let typeLabel = '';
      if (h.type === 'INBOUND') {
        typeLabel = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">BARANG MASUK</span>';
      } else if (h.type === 'OUTBOUND') {
        typeLabel = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">BARANG KELUAR</span>';
      } else if (h.type === 'TASK_PICKING') {
        typeLabel = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200">TASK PICKING</span>';
      } else if (h.type === 'ADJUSTMENT') {
        typeLabel = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-800 border border-purple-200">PENYESUAIAN STOK</span>';
      } else {
        typeLabel = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">STOK AWAL</span>';
      }

      return `
        <tr class="hover:bg-slate-50 transition-colors text-xs border-b border-slate-100">
          <td class="p-3 text-slate-400 font-mono text-[11px] whitespace-nowrap">${App.formatDate(h.created_at)}</td>
          <td class="p-3">${typeLabel}</td>
          <td class="p-3 font-mono font-bold text-emerald-800">${escapeHtml(h.reference_no)}</td>
          <td class="p-3 text-center font-bold text-emerald-700 font-mono">
            ${isPositive ? `+${App.formatNumber(h.qty_change)}` : '0'}
          </td>
          <td class="p-3 text-center font-bold text-rose-600 font-mono">
            ${!isPositive ? `${App.formatNumber(Math.abs(h.qty_change))}` : '0'}
          </td>
          <td class="p-3 text-center font-black text-slate-900 font-mono">
            ${App.formatNumber(h.stock_after)}
          </td>
          <td class="p-3 text-slate-600 text-[11px]">
            ${escapeHtml(h.notes || '-')}
          </td>
          <td class="p-3">
            <div class="flex items-center gap-1">
              <span class="material-symbols-outlined text-[15px] text-slate-400">person</span>
              <span class="font-semibold text-slate-800">${escapeHtml(h.user_name || 'System')}</span>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  }

  // Switch View
  switchAdminTab('history', false);
}

function printStockCard() {
  window.print();
}

// ================= 2.2 EXCEL / CSV MASTER STOCK IMPORT HANDLERS =================
let pendingExcelItems = [];

async function openUploadExcelModal() {
  pendingExcelItems = [];
  document.getElementById('excelFileInput').value = '';
  document.getElementById('excelPasteText').value = '';
  document.getElementById('importPreviewSection').classList.add('hidden');
  document.getElementById('importSubmitBtn').classList.add('hidden');
  document.getElementById('importPreviewLoading').classList.add('hidden');

  // Check if Data Packaaging Material.xlsx exists in server root
  const detectRes = await App.fetchJson('../api/import_excel.php?action=detect_file');
  const alertBox = document.getElementById('localExcelFileAlert');
  if (detectRes && detectRes.file_exists && alertBox) {
    document.getElementById('localExcelFileName').innerText = detectRes.filename;
    document.getElementById('localExcelFileDesc').innerText = `Tersedia ${detectRes.total_items} data material packaging siap diimpor ke database.`;
    alertBox.classList.remove('hidden');
  } else if (alertBox) {
    alertBox.classList.add('hidden');
  }

  App.openModal('modalExcelImport');
}

async function previewDetectedLocalExcel() {
  const loading = document.getElementById('importPreviewLoading');
  const previewSection = document.getElementById('importPreviewSection');
  loading.classList.remove('hidden');
  previewSection.classList.add('hidden');

  const formData = new FormData();
  formData.append('source', 'local_file');

  try {
    const response = await fetch('../api/import_excel.php?action=preview&source=local_file', {
      method: 'POST',
      body: formData
    });
    const res = await response.json();
    loading.classList.add('hidden');
    renderExcelPreview(res);
  } catch (err) {
    loading.classList.add('hidden');
    App.toast('Gagal memproses file lokal: ' + err.message, 'error');
  }
}

async function handleExcelFileSelect(input) {
  const file = input.files[0];
  if (!file) return;

  const loading = document.getElementById('importPreviewLoading');
  const previewSection = document.getElementById('importPreviewSection');
  loading.classList.remove('hidden');
  previewSection.classList.add('hidden');

  // If XLSX and SheetJS is available, we can parse client side or send directly to PHP
  const formData = new FormData();
  formData.append('file', file);

  try {
    const response = await fetch('../api/import_excel.php?action=preview', {
      method: 'POST',
      body: formData
    });
    const res = await response.json();
    loading.classList.add('hidden');
    renderExcelPreview(res);
  } catch (err) {
    loading.classList.add('hidden');
    App.toast('Gagal membaca file Excel: ' + err.message, 'error');
  }
}

async function previewExcelTextPaste() {
  const text = document.getElementById('excelPasteText').value.trim();
  if (!text) {
    App.toast('Silakan paste data tabel Excel terlebih dahulu', 'warning');
    return;
  }

  const loading = document.getElementById('importPreviewLoading');
  const previewSection = document.getElementById('importPreviewSection');
  loading.classList.remove('hidden');
  previewSection.classList.add('hidden');

  const res = await App.fetchJson('../api/import_excel.php?action=preview', {
    method: 'POST',
    body: JSON.stringify({ raw_text: text })
  });

  loading.classList.add('hidden');
  renderExcelPreview(res);
}

function renderExcelPreview(res) {
  if (!res.success || !res.items || res.items.length === 0) {
    App.toast(res.message || 'Tidak ada data valid yang dapat diimpor.', 'error');
    return;
  }

  pendingExcelItems = res.items;
  const tbody = document.getElementById('importPreviewTableBody');
  const summary = document.getElementById('importSummaryStats');
  const submitBtn = document.getElementById('importSubmitBtn');
  const previewSection = document.getElementById('importPreviewSection');

  summary.innerHTML = `
    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
      ${res.summary.total_rows} Material Terdeteksi (${res.summary.new_items} Baru, ${res.summary.update_items} Update)
    </span>
  `;

  tbody.innerHTML = res.items.map(item => `
    <tr class="hover:bg-slate-50 border-b border-slate-100">
      <td class="p-2 font-mono font-bold text-emerald-800">${escapeHtml(item.item_no)}</td>
      <td class="p-2 font-bold text-slate-800">${escapeHtml(item.item_description)}</td>
      <td class="p-2 text-slate-600 font-medium">
        <span class="px-1.5 py-0.2 bg-slate-100 rounded text-[10px] text-slate-700 font-semibold">${escapeHtml(item.category)}</span>
      </td>
      <td class="p-2 text-center font-mono font-black text-emerald-700 text-xs">
        ${App.formatNumber(item.ending_stock)} <span class="text-[10px] text-slate-400 font-normal">${escapeHtml(item.unit)}</span>
      </td>
      <td class="p-2 text-slate-600 text-xs">${escapeHtml(item.rack_location)}</td>
      <td class="p-2 whitespace-nowrap">
        ${item.status === 'NEW' 
          ? '<span class="px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">BARU</span>'
          : '<span class="px-1.5 py-0.2 rounded bg-blue-100 text-blue-800 font-bold text-[10px]">UPDATE</span>'}
      </td>
    </tr>
  `).join('');

  previewSection.classList.remove('hidden');
  submitBtn.classList.remove('hidden');
  submitBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

async function commitExcelImport() {
  if (pendingExcelItems.length === 0) {
    App.toast('Tidak ada item untuk disimpan', 'warning');
    return;
  }

  const submitBtn = document.getElementById('importSubmitBtn');
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Menyimpan...</span>';

  const res = await App.fetchJson('../api/import_excel.php?action=commit', {
    method: 'POST',
    body: JSON.stringify({ items: pendingExcelItems })
  });

  submitBtn.disabled = false;
  submitBtn.innerHTML = '<span class="material-symbols-outlined text-[16px]">upload</span><span>Simpan ke Master Stok Gudang</span>';

  if (res.success) {
    App.toast(res.message, 'success', 'Import Berhasil');
    App.closeModal('modalExcelImport');
    pendingExcelItems = [];
    loadMaterials();
    loadStats();
    populateMaterialSelects();
  } else {
    App.toast(res.message || 'Gagal menyimpan ke database', 'error');
  }
}

function populateCategoryFilters() {
  const catSelect = document.getElementById('inventoryCategoryFilter');
  if (!catSelect) return;
  const cats = [...new Set(allMaterials.map(m => m.category).filter(Boolean))];
  const currentVal = catSelect.value;
  catSelect.innerHTML = '<option value="all">Semua Kategori</option>' + 
    cats.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
  catSelect.value = currentVal;
  App.syncSearchableSelect(catSelect);
}

async function populateMaterialSelects() {
  if (!allMaterials || allMaterials.length === 0) {
    const res = await App.fetchJson('../api/materials.php?action=list');
    if (res.success && res.data) {
      allMaterials = res.data;
    }
  }
  const selectIds = ['inboundMaterialSelect', 'outboundMaterialSelect', 'taskMaterialSelect'];
  selectIds.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    const currentVal = el.value;
    el.innerHTML = '<option value="">-- Pilih Material Packaging --</option>' +
      (allMaterials || []).map(m => `
        <option value="${m.id}" data-code="${escapeHtml(m.code)}" data-name="${escapeHtml(m.name)}" data-stock="${m.current_stock}" data-unit="${escapeHtml(m.unit || 'Pcs')}" data-rack="${escapeHtml(m.rack_location)}">
          ${escapeHtml(m.name)} (Stok: ${App.formatNumber(m.current_stock)} ${escapeHtml(m.unit || 'Pcs')})
        </option>
      `).join('');
    if (currentVal) el.value = currentVal;
    App.syncSearchableSelect(el);
  });
}

// 3. OPERATORS LOADER
async function loadOperators() {
  const res = await App.fetchJson('../api/users.php?action=operators');
  if (res.success) {
    allOperators = res.data;
    const select = document.getElementById('taskOperatorSelect');
    if (select) {
      select.innerHTML = '<option value="">-- Pilih PIC --</option>' +
        allOperators.map(op => `
          <option value="${op.id}">${escapeHtml(op.name)} (${escapeHtml(op.shift || 'Shift Aktif')})</option>
        `).join('');
      App.syncSearchableSelect(select);
    }
  }
}

function populateTaskOperators() {
  return loadOperators();
}

// 4. TASKS LOADER & MONITOR
async function loadTasks() {
  const filterStatus = document.getElementById('taskStatusFilter')?.value || 'ALL';
  const filterPriority = document.getElementById('taskPriorityFilter')?.value || 'ALL';
  const filterDate = (document.getElementById('taskDateFilter')?.value || '').trim();
  const search = (document.getElementById('taskSearchInput')?.value || '').trim();

  const query = new URLSearchParams({
    action: 'list',
    status: filterStatus,
    priority: filterPriority,
    date: filterDate,
    search
  });

  const res = await App.fetchJson(`../api/tasks.php?${query.toString()}`);
  if (res.success) {
    allTasks = res.data;
    renderTasksTable(allTasks);
    renderDashboardTasksTable(allTasks.filter(t => t.status !== 'COMPLETED' && t.status !== 'CANCELLED').slice(0, 6));
  }
}

function renderTasksTable(tasks) {
  const tbody = document.getElementById('tasksTableBody');
  if (!tbody) return;

  if (tasks.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="9" class="p-8 text-center text-slate-400">
          <span class="material-symbols-outlined text-[32px] text-slate-300 mb-1">checklist</span>
          <p class="text-xs font-medium">Tidak ada tugas penugasan yang sesuai filter.</p>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = tasks.map(t => {
    let priorityBadge = t.priority === 'URGENT' 
      ? '<span class="badge-urgent px-2 py-0.5 rounded text-[10px] font-extrabold">URGENT</span>'
      : '<span class="badge-normal px-2 py-0.5 rounded text-[10px] font-bold">NORMAL</span>';

    let statusBadge = '';
    if (t.status === 'COMPLETED') {
      statusBadge = '<span class="badge-completed px-2 py-0.5 rounded text-[11px] font-bold">SELESAI</span>';
    } else if (t.status === 'IN_PROGRESS') {
      statusBadge = '<span class="badge-inprogress px-2 py-0.5 rounded text-[11px] font-bold">PROSES</span>';
    } else if (t.status === 'CANCELLED') {
      statusBadge = '<span class="px-2 py-0.5 rounded text-[11px] bg-slate-100 text-slate-500 font-semibold">BATAL</span>';
    } else {
      statusBadge = '<span class="px-2 py-0.5 rounded text-[11px] bg-blue-50 text-blue-700 border border-blue-200 font-bold">PENDING</span>';
    }

    return `
      <tr class="hover:bg-slate-50 border-b border-slate-100 transition-colors text-xs">
        <td class="p-3 text-slate-600 whitespace-nowrap font-medium">
          ${App.formatDate(t.created_at)}
        </td>
        <td class="p-3 font-mono font-bold text-xs whitespace-nowrap">
          <span class="px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200">${escapeHtml(t.task_no)}</span>
        </td>
        <td class="p-3 min-w-[240px]">
          <p class="font-bold text-xs text-slate-900 leading-tight">${escapeHtml(t.material_name)}</p>
          <div class="flex items-center gap-1.5 mt-0.5 text-[10px] text-slate-500 font-mono">
            <span>${escapeHtml(t.material_code)}</span>
            <span>&bull;</span>
            <span class="text-slate-600 font-semibold">Rak: ${escapeHtml(t.rack_location || '-')}</span>
          </div>
        </td>
        <td class="p-3 text-center">
          <div class="font-extrabold text-xs text-slate-900">${App.formatNumber(t.target_qty)} <span class="font-normal text-slate-500">${escapeHtml(t.material_unit || 'Pcs')}</span></div>
          ${t.status === 'COMPLETED' ? `
            <div class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-100 text-emerald-900 border border-emerald-300 shadow-2xs">
              <span class="material-symbols-outlined text-[12px]">check</span>
              <span>Diambil: ${App.formatNumber(t.actual_qty)}</span>
            </div>
          ` : `
            <div class="text-[10px] text-slate-400 mt-0.5 font-medium">Stok: ${App.formatNumber(t.material_stock || 0)}</div>
          `}
        </td>
        <td class="p-3 font-bold text-slate-800 whitespace-nowrap">${escapeHtml(t.destination)}</td>
        <td class="p-3 text-center whitespace-nowrap">${priorityBadge}</td>
        <td class="p-3 whitespace-nowrap">
          <p class="font-bold text-slate-900">${escapeHtml(t.operator_name)}</p>
          <span class="text-[10px] text-slate-400 font-medium">${escapeHtml(t.operator_shift || '')}</span>
        </td>
        <td class="p-3 text-center whitespace-nowrap">${statusBadge}</td>
        <td class="p-3 text-center whitespace-nowrap">
          ${t.status === 'PENDING' || t.status === 'IN_PROGRESS' ? `
            <button onclick="cancelTask(${t.id})" class="text-xs text-rose-600 hover:text-white font-bold py-1 px-2.5 rounded-lg hover:bg-rose-600 border border-rose-200 transition-colors shadow-2xs">
              Batalkan
            </button>
          ` : t.status === 'COMPLETED' ? `
            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700">
              <span class="material-symbols-outlined text-[15px]">done_all</span> Selesai
            </span>
          ` : `
            <span class="text-xs text-slate-400 font-medium">-</span>
          `}
        </td>
      </tr>
    `;
  }).join('');
}

function renderDashboardTasksTable(tasks) {
  const tbody = document.getElementById('dashboardTasksTable');
  if (!tbody) return;

  if (tasks.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="5" class="p-6 text-center text-slate-400 text-xs">
          <span class="material-symbols-outlined text-emerald-600 text-[24px] mb-1">check_circle</span>
          <p>Tidak ada antrian tugas aktif. Semua penugasan selesai.</p>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = tasks.map(t => `
    <tr class="hover:bg-slate-50 border-b border-slate-100 transition-colors">
      <td class="py-2.5 font-mono font-bold text-xs text-emerald-700">${escapeHtml(t.task_no)}</td>
      <td class="py-2.5">
        <p class="font-semibold text-xs text-slate-900">${escapeHtml(t.material_name)}</p>
        <span class="text-[10px] text-slate-500">Target: ${t.target_qty} &bull; ${escapeHtml(t.rack_location)}</span>
      </td>
      <td class="py-2.5 text-xs text-slate-700">${escapeHtml(t.destination)}</td>
      <td class="py-2.5 text-xs font-semibold text-slate-800">${escapeHtml(t.operator_name)}</td>
      <td class="py-2.5">
        ${t.status === 'IN_PROGRESS' 
          ? '<span class="badge-inprogress px-2 py-0.5 rounded text-[10px]">Proses</span>'
          : '<span class="bg-blue-50 text-emerald-700 px-2 py-0.5 rounded text-[10px] font-semibold border border-blue-200">Pending</span>'}
      </td>
    </tr>
  `).join('');
}

// 7. TASK ASSIGNMENT (FULL PAGE VIEW: SINGLE PRODUCT, MULTIPLE PRODUCTS, & EXCEL IMPORT)
let bulkRowCounter = 0;

function switchTaskSubView(viewName) {
  const views = {
    create: document.getElementById('taskSubViewCreate'),
    list: document.getElementById('taskSubViewList'),
    excel: document.getElementById('taskSubViewExcel')
  };

  const btns = {
    create: document.getElementById('subtab-task-create-btn'),
    list: document.getElementById('subtab-task-list-btn'),
    excel: document.getElementById('subtab-task-excel-btn')
  };

  Object.keys(views).forEach(key => {
    if (views[key]) {
      if (key === viewName) {
        views[key].classList.remove('hidden');
      } else {
        views[key].classList.add('hidden');
      }
    }

    if (btns[key]) {
      if (key === viewName) {
        btns[key].className = 'h-[34px] px-3.5 rounded-lg bg-white text-emerald-800 shadow-2xs font-bold transition-all flex items-center gap-1.5 border border-slate-200/60';
      } else {
        btns[key].className = 'h-[34px] px-3.5 rounded-lg text-slate-600 hover:text-slate-900 transition-all font-semibold flex items-center gap-1.5';
      }
    }
  });

  if (viewName === 'create') {
    initBulkTaskTable();
  } else if (viewName === 'list') {
    loadTasks();
  }
  setTimeout(initPremiumPickers, 60);
}

function openAssignTaskModal() {
  switchAdminTab('tasks', true);
  switchTaskSubView('create');
}

function openBulkTaskModal() {
  switchAdminTab('tasks', true);
  switchTaskSubView('create');
}

function openExcelTaskImportModal() {
  switchAdminTab('tasks', true);
  switchTaskSubView('excel');
}

async function quickAssignFromMaterial(materialId) {
  switchAdminTab('tasks', true);
  switchTaskSubView('create');
  await initBulkTaskTable();

  setTimeout(() => {
    const tbody = document.getElementById('bulkTaskTableBody');
    if (tbody && tbody.children.length > 0) {
      const firstSelect = tbody.children[0].querySelector('.bulk-material-select');
      if (firstSelect) {
        firstSelect.value = materialId;
        App.syncSearchableSelect(firstSelect);
        updateBulkRowStockInfo(firstSelect);
        const qtyInp = tbody.children[0].querySelector('.bulk-qty-input');
        if (qtyInp) qtyInp.focus();
      }
    }
  }, 100);
}

function switchAssignTaskMode(mode) {
  // Always use multi-product table
  initBulkTaskTable();
}

async function initBulkTaskTable() {
  bulkRowCounter = 0;
  const tbody = document.getElementById('bulkTaskTableBody');
  if (!tbody) return;
  tbody.innerHTML = '';

  if (!allMaterials || allMaterials.length === 0) {
    const resMat = await App.fetchJson('../api/materials.php?action=list');
    if (resMat && resMat.success && resMat.data) {
      allMaterials = resMat.data;
    }
  }

  if (!allOperators || allOperators.length === 0) {
    const resOp = await App.fetchJson('../api/users.php?action=operators');
    if (resOp && resOp.success && resOp.data) {
      allOperators = resOp.data;
    }
  }

  const headerOp = document.getElementById('bulkHeaderOperator');
  if (headerOp) {
    headerOp.innerHTML = '<option value="">-- Pilih PIC --</option>' +
      (allOperators || []).map(op => `<option value="${op.id}">${escapeHtml(op.name)} (${escapeHtml(op.shift || 'Shift')})</option>`).join('');
    App.syncSearchableSelect(headerOp);
  }

  const destInp = document.getElementById('bulkHeaderDestination');
  if (destInp && !destInp.value) destInp.value = 'HANASUI';

  // Add initial 1 single row
  addBulkTaskRow();
}

function updateTaskMaterialInfo() {
  const select = document.getElementById('taskMaterialSelect');
  const opt = select?.options[select.selectedIndex];
  const infoBox = document.getElementById('taskMaterialInfoBox');
  if (opt && opt.value && infoBox) {
    const stock = opt.getAttribute('data-stock');
    const rack = opt.getAttribute('data-rack');
    infoBox.innerHTML = `
      <div class="p-3 bg-white border border-slate-200 rounded-lg flex items-center justify-between text-xs shadow-2xs">
        <span>Lokasi Rak Simpan: <b class="text-slate-900 font-semibold">${rack}</b></span>
        <span>Sisa Stok Tersedia: <b class="${stock <= 0 ? 'text-rose-600' : 'text-emerald-700'} font-bold">${App.formatNumber(stock)}</b></span>
      </div>
    `;
    infoBox.classList.remove('hidden');
  } else if (infoBox) {
    infoBox.classList.add('hidden');
  }
}

async function handleCreateTaskSubmit(e) {
  e.preventDefault();
  const select = document.getElementById('taskMaterialSelect');
  const opt = select?.options[select.selectedIndex];
  const material_id = select?.value;
  const target_qty = parseFloat(document.getElementById('taskTargetQty')?.value) || 0;
  const priority = document.getElementById('taskPrioritySelect')?.value;
  const destination = document.getElementById('taskDestination')?.value.trim();
  const assigned_to = document.getElementById('taskOperatorSelect')?.value;
  const notes = document.getElementById('taskNotes')?.value.trim();

  if (!material_id || target_qty <= 0) {
    App.toast('Pilih material dan isi Target Qty lebih dari 0!', 'warning');
    return;
  }

  const stock = opt ? parseFloat(opt.getAttribute('data-stock') || '0') : 0;
  if (target_qty > stock) {
    App.toast(`Target Qty (${App.formatNumber(target_qty)}) tidak bisa melebihi Sisa Stok (${App.formatNumber(stock)})!`, 'error');
    document.getElementById('taskTargetQty')?.focus();
    return;
  }

  const res = await App.fetchJson('../api/tasks.php?action=create', {
    method: 'POST',
    body: JSON.stringify({ material_id, target_qty, priority, destination, assigned_to, notes })
  });

  if (res.success) {
    App.toast(res.message, 'success', 'Task Berhasil Dibuat');
    document.getElementById('formCreateTask')?.reset();
    document.getElementById('taskMaterialInfoBox')?.classList.add('hidden');
    switchAdminTab('outbound', true);
    loadOutboundHistory();
    loadStats();
  } else {
    App.toast(res.message, 'error');
  }
}

// Helper to auto-focus Packaging Material on a task row
function focusBulkRowMaterial(row) {
  if (!row) return;
  setTimeout(() => {
    const ssTrigger = row.querySelector('.ss-trigger');
    if (ssTrigger) {
      ssTrigger.focus();
      ssTrigger.click();
    } else {
      const select = row.querySelector('.bulk-material-select');
      if (select) select.focus();
    }
  }, 60);
}

// 7.1 MULTIPLE BULK TASK FORM LOGIC
function addBulkTaskRow(prefillMatId = null, prefillQty = 0) {
  bulkRowCounter++;
  const tbody = document.getElementById('bulkTaskTableBody');
  if (!tbody) return null;

  const defaultDest = document.getElementById('bulkHeaderDestination')?.value || 'HANASUI';
  const defaultPriority = document.getElementById('bulkHeaderPriority')?.value || 'NORMAL';

  const rowId = `bulk-row-${bulkRowCounter}`;
  const tr = document.createElement('tr');
  tr.id = rowId;
  tr.className = 'hover:bg-slate-50/80 border-b border-slate-100 text-xs transition-colors';

  let initialUnit = 'Pcs';
  if (prefillMatId && allMaterials && allMaterials.length > 0) {
    const found = allMaterials.find(m => m.id == prefillMatId);
    if (found && found.unit) initialUnit = found.unit;
  }

  const displayQty = (prefillQty !== null && prefillQty !== undefined && prefillQty > 0) ? prefillQty : 0;

  tr.innerHTML = `
    <td class="p-3 font-mono font-bold text-slate-500 text-center bg-slate-50/50">${tbody.children.length + 1}</td>
    <td class="p-2.5 min-w-[300px]">
      <select class="bulk-material-select w-full h-[38px] bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-800 outline-none focus:border-emerald-600" onchange="updateBulkRowStockInfo(this)" required>
        <option value="">-- Pilih Material Packaging --</option>
        ${(allMaterials || []).map(m => `
          <option value="${m.id}" data-code="${escapeHtml(m.code)}" data-name="${escapeHtml(m.name)}" data-unit="${escapeHtml(m.unit || 'Pcs')}" data-stock="${m.current_stock}" data-rack="${escapeHtml(m.rack_location)}" ${prefillMatId == m.id ? 'selected' : ''}>
            ${escapeHtml(m.name)} (Stok: ${App.formatNumber(m.current_stock)})
          </option>
        `).join('')}
      </select>
      <div class="bulk-stock-badge text-[11px] text-slate-500 mt-1 pl-1"></div>
    </td>
    <td class="p-2.5 text-center">
      <span class="bulk-unit-label font-extrabold text-xs px-2.5 py-1.5 rounded-lg bg-indigo-50 text-indigo-900 border border-indigo-200 inline-block font-mono min-w-[55px] shadow-2xs">${escapeHtml(initialUnit)}</span>
    </td>
    <td class="p-2.5">
      <input type="number" class="bulk-qty-input w-full h-[38px] px-3 bg-white border border-slate-300 rounded-lg text-xs font-extrabold text-emerald-700 outline-none focus:border-emerald-600 text-center shadow-2xs" min="1" value="${displayQty}" placeholder="0" oninput="validateBulkQtyStock(this)" required>
    </td>
    <td class="p-2.5">
      <select class="bulk-dest-input w-full h-[38px] px-3 bg-white border border-slate-300 rounded-lg text-xs font-bold outline-none focus:border-emerald-600 text-slate-800 shadow-2xs" data-no-search>
        <option value="HANASUI" ${defaultDest === 'HANASUI' ? 'selected' : ''}>HANASUI</option>
        <option value="NCO" ${defaultDest === 'NCO' ? 'selected' : ''}>NCO</option>
        <option value="FYNE" ${defaultDest === 'FYNE' ? 'selected' : ''}>FYNE</option>
        <option value="EOMMA" ${defaultDest === 'EOMMA' ? 'selected' : ''}>EOMMA</option>
      </select>
    </td>
    <td class="p-2.5">
      <select class="bulk-priority-select w-full h-[38px] px-3 bg-white border border-slate-300 rounded-lg text-xs font-bold outline-none focus:border-emerald-600 text-slate-800 shadow-2xs" data-no-search>
        <option value="NORMAL" ${defaultPriority === 'NORMAL' ? 'selected' : ''}>Normal</option>
        <option value="URGENT" ${defaultPriority === 'URGENT' ? 'selected' : ''}>URGENT</option>
      </select>
    </td>
    <td class="p-2.5">
      <input type="text" class="bulk-notes-input w-full h-[38px] px-3 bg-white border border-slate-300 rounded-lg text-xs font-medium text-slate-800 outline-none focus:border-emerald-600 placeholder-slate-400 shadow-2xs" placeholder="Catatan khusus...">
    </td>
    <td class="p-2.5 text-center">
      <button type="button" onclick="removeBulkTaskRow(this)" class="h-[36px] w-[36px] text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg border border-transparent hover:border-rose-200 transition-colors flex items-center justify-center mx-auto cursor-pointer" title="Hapus Baris (atau tekan Delete pada keyboard)">
        <span class="material-symbols-outlined text-[19px]">delete</span>
      </button>
    </td>
  `;

  tbody.appendChild(tr);
  App.initAllSearchableSelects(tr);

  // 1. Keydown on Catatan/Notes input: Press Enter to create new row & auto-focus Packaging Material
  const notesInput = tr.querySelector('.bulk-notes-input');
  if (notesInput) {
    notesInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        const newRow = addBulkTaskRow();
        focusBulkRowMaterial(newRow);
      }
    });
  }

  // 2. Keydown on Row: Press Delete key on keyboard to delete row
  tr.addEventListener('keydown', (e) => {
    if (e.key === 'Delete') {
      const isTextInput = (e.target.tagName === 'INPUT' && e.target.type !== 'checkbox') || e.target.tagName === 'TEXTAREA';
      // If focused on empty text input or non-text element (like select, button, trigger), or with Ctrl/Alt
      if (!isTextInput || e.target.value === '' || e.ctrlKey || e.altKey) {
        e.preventDefault();
        removeBulkTaskRow(tr);
      }
    }
  });

  const select = tr.querySelector('.bulk-material-select');
  if (select && select.value) {
    updateBulkRowStockInfo(select);
  }

  return tr;
}

function removeBulkTaskRow(btnOrTr) {
  const tr = (btnOrTr instanceof HTMLElement && btnOrTr.tagName === 'TR') ? btnOrTr : btnOrTr?.closest('tr');
  const tbody = document.getElementById('bulkTaskTableBody');
  if (!tbody || !tr) return;

  const allRows = Array.from(tbody.children);
  const currentIndex = allRows.indexOf(tr);

  // If this is the only row, reset its values instead of leaving 0 rows
  if (allRows.length <= 1) {
    tr.remove();
    const newRow = addBulkTaskRow();
    focusBulkRowMaterial(newRow);
    App.toast('Baris penugasan direset ke 1 baris kosong', 'info');
    return;
  }

  tr.remove();

  // Renumber remaining rows
  const remainingRows = Array.from(tbody.children);
  remainingRows.forEach((r, index) => {
    if (r.children && r.children[0]) {
      r.children[0].innerText = index + 1;
    }
  });

  // Shift focus to adjacent row's material
  const targetRow = remainingRows[Math.min(currentIndex, remainingRows.length - 1)];
  if (targetRow) {
    focusBulkRowMaterial(targetRow);
  }
}

function validateBulkQtyStock(qtyInput) {
  if (!qtyInput) return true;
  const tr = qtyInput.closest('tr');
  if (!tr) return true;

  const matSelect = tr.querySelector('.bulk-material-select');
  const badge = tr.querySelector('.bulk-stock-badge');
  const opt = matSelect?.options[matSelect.selectedIndex];

  const qty = parseFloat(qtyInput.value) || 0;
  const stock = (opt && opt.value) ? parseFloat(opt.getAttribute('data-stock') || '0') : null;
  const unit = (opt && opt.value) ? (opt.getAttribute('data-unit') || 'Pcs') : 'Pcs';
  const rack = (opt && opt.value) ? (opt.getAttribute('data-rack') || '-') : '-';

  // Reset classes
  qtyInput.classList.remove('border-rose-500', 'bg-rose-50/70', 'text-rose-700', 'border-amber-400', 'bg-amber-50/40', 'text-amber-700');

  if (stock !== null) {
    if (stock <= 0) {
      qtyInput.classList.add('border-rose-500', 'bg-rose-50/70', 'text-rose-700');
      if (badge) {
        badge.innerHTML = `<span class="text-rose-600 font-bold">⚠️ Sisa Stok Kosong (0 ${escapeHtml(unit)}) &bull; Lokasi: ${escapeHtml(rack)}</span>`;
      }
      return false;
    }

    if (qty > stock) {
      qtyInput.classList.add('border-rose-500', 'bg-rose-50/70', 'text-rose-700');
      if (badge) {
        badge.innerHTML = `<span class="text-rose-600 font-bold">⚠️ Target Qty (${App.formatNumber(qty)}) melebihi Sisa Stok (${App.formatNumber(stock)} ${escapeHtml(unit)})!</span>`;
      }
      return false;
    }

    if (qty <= 0) {
      qtyInput.classList.add('border-amber-400', 'bg-amber-50/40', 'text-amber-700');
      if (badge) {
        badge.innerHTML = `Sisa Stok: <b class="text-emerald-700">${App.formatNumber(stock)} ${escapeHtml(unit)}</b> &bull; Lokasi: <span class="font-semibold text-slate-700">${escapeHtml(rack)}</span> <span class="text-amber-600 font-bold ml-1">(* Wajib diisi > 0)</span>`;
      }
      return false;
    }

    // Valid state
    qtyInput.classList.add('text-emerald-700');
    if (badge) {
      badge.innerHTML = `Sisa Stok: <b class="text-emerald-700">${App.formatNumber(stock)} ${escapeHtml(unit)}</b> &bull; Lokasi: <span class="font-semibold text-slate-700">${escapeHtml(rack)}</span>`;
    }
    return true;
  }

  if (qty <= 0) {
    qtyInput.classList.add('border-amber-400');
  }
  return true;
}

function updateBulkRowStockInfo(select) {
  const opt = select.options[select.selectedIndex];
  const tr = select.closest('tr');
  if (!tr) return;

  const unitLabel = tr.querySelector('.bulk-unit-label');
  const qtyInput = tr.querySelector('.bulk-qty-input');

  if (opt && opt.value) {
    const unit = opt.getAttribute('data-unit') || 'Pcs';
    const stock = parseFloat(opt.getAttribute('data-stock') || '0');
    if (unitLabel) unitLabel.innerText = unit;
    if (qtyInput) {
      qtyInput.max = stock;
      validateBulkQtyStock(qtyInput);
    }
  } else {
    if (unitLabel) unitLabel.innerText = 'Pcs';
    const badge = tr.querySelector('.bulk-stock-badge');
    if (badge) badge.innerHTML = '';
    if (qtyInput) validateBulkQtyStock(qtyInput);
  }
}

function syncBulkDestinationToRows() {
  const val = document.getElementById('bulkHeaderDestination').value;
  document.querySelectorAll('.bulk-dest-input').forEach(inp => {
    inp.value = val;
  });
}

function syncBulkPriorityToRows() {
  const val = document.getElementById('bulkHeaderPriority').value;
  document.querySelectorAll('.bulk-priority-select').forEach(sel => {
    sel.value = val;
  });
}

function syncBulkOperatorToRows() {
  // Operator is handled on submission
}

async function handleBulkTaskSubmit() {
  const assigned_to = parseInt(document.getElementById('bulkHeaderOperator').value);
  if (!assigned_to || assigned_to <= 0) {
    App.toast('Silakan pilih Operator PIC yang ditugaskan terlebih dahulu.', 'warning');
    document.getElementById('bulkHeaderOperator')?.focus();
    return;
  }

  const rows = document.querySelectorAll('#bulkTaskTableBody tr');
  const tasksToCreate = [];

  for (let i = 0; i < rows.length; i++) {
    const tr = rows[i];
    const rowNum = i + 1;
    const matSelect = tr.querySelector('.bulk-material-select');
    const qtyInput  = tr.querySelector('.bulk-qty-input');
    const destInput = tr.querySelector('.bulk-dest-input');
    const priSelect = tr.querySelector('.bulk-priority-select');
    const notesInput= tr.querySelector('.bulk-notes-input');

    const opt = matSelect?.options[matSelect.selectedIndex];
    const material_id = parseInt(matSelect?.value || 0);
    const target_qty  = parseFloat(qtyInput?.value) || 0;
    const destination = destInput?.value.trim() || 'Line Packing';
    const priority    = priSelect?.value || 'NORMAL';
    const notes       = notesInput?.value.trim() || '';

    if (!material_id || material_id <= 0) {
      App.toast(`Silakan pilih Material Packaging pada baris ke-${rowNum}.`, 'warning');
      focusBulkRowMaterial(tr);
      return;
    }

    const stock = parseFloat(opt?.getAttribute('data-stock') || '0');
    const matName = opt?.getAttribute('data-name') || 'Material';

    if (target_qty <= 0) {
      App.toast(`Target Qty wajib diisi dan harus lebih besar dari 0 pada baris ke-${rowNum}!`, 'warning');
      qtyInput?.focus();
      validateBulkQtyStock(qtyInput);
      return;
    }

    if (target_qty > stock) {
      App.toast(`Target Qty (${App.formatNumber(target_qty)}) tidak boleh melebihi Sisa Stok (${App.formatNumber(stock)}) untuk "${matName}" pada baris ke-${rowNum}!`, 'error');
      qtyInput?.focus();
      validateBulkQtyStock(qtyInput);
      return;
    }

    tasksToCreate.push({
      material_id,
      target_qty,
      destination,
      priority,
      assigned_to,
      notes
    });
  }

  if (tasksToCreate.length === 0) {
    App.toast('Pilih minimal 1 packaging material dengan jumlah target valid.', 'warning');
    return;
  }

  const res = await App.fetchJson('../api/tasks.php?action=batch_create', {
    method: 'POST',
    body: JSON.stringify({ tasks: tasksToCreate })
  });

  if (res.success) {
    App.toast(res.message, 'success', 'Task Berhasil Dibuat');
    initBulkTaskTable();
    switchTaskSubView('list');
    loadTasks();
    loadOutboundHistory();
    loadStats();
  } else {
    App.toast(res.message, 'error');
  }
}

// 7.2 UPLOAD EXCEL TASK LOGIC
let parsedExcelTasks = [];

function openExcelTaskImportModal() {
  switchAdminTab('tasks', true);
  switchTaskSubView('excel');
  parsedExcelTasks = [];
  const prevSec = document.getElementById('importTaskPreviewSection');
  const subBtn = document.getElementById('importTaskSubmitBtn');
  const fileInp = document.getElementById('excelTaskFileInput');
  const pasteTxt = document.getElementById('excelTaskPasteText');
  if (prevSec) prevSec.classList.add('hidden');
  if (subBtn) subBtn.classList.add('hidden');
  if (fileInp) fileInp.value = '';
  if (pasteTxt) pasteTxt.value = '';
}

function handleExcelTaskFileSelect(input) {
  if (input.files && input.files[0]) {
    previewExcelTaskFile(input.files[0]);
  }
}

async function previewExcelTaskFile(file) {
  const formData = new FormData();
  formData.append('file', file);

  const previewLoading = document.getElementById('importTaskPreviewLoading');
  if (previewLoading) previewLoading.classList.remove('hidden');

  const res = await App.fetchJson('../api/tasks.php?action=preview_excel', {
    method: 'POST',
    body: formData
  });

  if (previewLoading) previewLoading.classList.add('hidden');

  if (res.success) {
    parsedExcelTasks = res.tasks;
    renderExcelTaskPreview(res);
  } else {
    App.toast(res.message || 'Gagal memproses file task', 'error');
  }
}

async function previewExcelTaskTextPaste() {
  const raw_text = document.getElementById('excelTaskPasteText').value.trim();
  if (!raw_text) {
    App.toast('Silakan paste data tabel task dari Excel.', 'warning');
    return;
  }

  const res = await App.fetchJson('../api/tasks.php?action=preview_excel', {
    method: 'POST',
    body: JSON.stringify({ raw_text })
  });

  if (res.success) {
    parsedExcelTasks = res.tasks;
    renderExcelTaskPreview(res);
  } else {
    App.toast(res.message || 'Gagal memproses data paste', 'error');
  }
}

function renderExcelTaskPreview(res) {
  const previewSection = document.getElementById('importTaskPreviewSection');
  const submitBtn = document.getElementById('importTaskSubmitBtn');
  const summaryEl = document.getElementById('importTaskSummaryStats');
  const tbody = document.getElementById('importTaskPreviewTableBody');

  if (summaryEl) {
    summaryEl.innerHTML = `
      <div class="flex items-center gap-3 text-xs font-medium">
        <span class="text-slate-700">Total: <b>${res.summary.total_rows}</b> Task</span>
        <span class="text-emerald-700">Valid: <b>${res.summary.valid_count}</b></span>
        ${res.summary.invalid_count > 0 ? `<span class="text-rose-700">Error: <b>${res.summary.invalid_count}</b></span>` : ''}
      </div>
    `;
  }

  if (tbody) {
    tbody.innerHTML = res.tasks.map(t => {
      let statusBadge = t.validation_status === 'VALID'
        ? '<span class="px-2 py-0.5 bg-emerald-50 text-emerald-800 rounded font-semibold text-[10px] border border-emerald-200">Valid</span>'
        : `<span class="px-2 py-0.5 bg-rose-50 text-rose-800 rounded font-semibold text-[10px] border border-rose-200">${escapeHtml(t.warning)}</span>`;

      return `
        <tr class="text-xs hover:bg-slate-50 border-b border-slate-100">
          <td class="p-2 font-mono font-bold text-emerald-700">${escapeHtml(t.item_no)}</td>
          <td class="p-2">
            <p class="font-medium text-slate-800">${escapeHtml(t.material_name)}</p>
            <span class="text-[10px] text-slate-400">Rak: ${escapeHtml(t.rack_location)} &bull; Stok: ${App.formatNumber(t.material_stock)}</span>
          </td>
          <td class="p-2 font-bold text-indigo-700">${App.formatNumber(t.target_qty)}</td>
          <td class="p-2 text-slate-700">${escapeHtml(t.destination)}</td>
          <td class="p-2 font-medium text-slate-800">${escapeHtml(t.operator_name)}</td>
          <td class="p-2">${t.priority === 'URGENT' ? '<span class="badge-urgent px-1.5 py-0.2 rounded text-[10px]">URGENT</span>' : 'Normal'}</td>
          <td class="p-2">${statusBadge}</td>
        </tr>
      `;
    }).join('');
  }

  if (previewSection) previewSection.classList.remove('hidden');
  if (submitBtn) submitBtn.classList.remove('hidden');
}

async function commitExcelTaskImport() {
  const validTasks = parsedExcelTasks.filter(t => t.validation_status === 'VALID');
  if (validTasks.length === 0) {
    App.toast('Tidak ada task valid untuk dibuat.', 'warning');
    return;
  }

  const btn = document.getElementById('importTaskSubmitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span> Membuat Task...';

  const res = await App.fetchJson('../api/tasks.php?action=batch_create', {
    method: 'POST',
    body: JSON.stringify({ tasks: validTasks })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">send</span> Buat Semua Task Hasil Import';

  if (res.success) {
    App.toast(res.message, 'success', 'Penugasan Berhasil');
    switchTaskSubView('list');
    loadTasks();
    loadOutboundHistory();
    loadStats();
  } else {
    App.toast(res.message || 'Gagal membuat task', 'error');
  }
}

async function cancelTask(taskId) {
  const res = await App.fetchJson('../api/tasks.php?action=cancel', {
    method: 'POST',
    body: JSON.stringify({ task_id: taskId })
  });
  if (res.success) {
    App.toast('Tugas berhasil dibatalkan', 'info');
    loadTasks();
    loadStats();
  }
}

// 8. STOCK MUTATIONS LOG
async function loadMutations() {
  const type = document.getElementById('mutationTypeFilter')?.value || 'ALL';
  const search = document.getElementById('mutationSearchInput')?.value || '';

  const query = new URLSearchParams({
    action: 'list',
    type,
    search
  });

  const res = await App.fetchJson(`../api/mutations.php?${query.toString()}`);
  if (res.success) {
    const tbody = document.getElementById('mutationsTableBody');
    if (!tbody) return;
    if (res.data.length === 0) {
      tbody.innerHTML = '<tr><td colspan="7" class="p-8 text-center text-xs text-slate-400">Tidak ada data mutasi stok.</td></tr>';
      return;
    }
    tbody.innerHTML = res.data.map(m => {
      let typeBadge = '';
      if (m.type === 'INBOUND') typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">BARANG MASUK</span>';
      else if (m.type === 'OUTBOUND') typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">BARANG KELUAR</span>';
      else if (m.type === 'TASK_PICKING') typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-50 text-blue-800 border border-blue-200">PENGAMBILAN TASK</span>';
      else typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-800 border border-slate-200">STOK AWAL</span>';

      const isPositive = m.qty_change > 0;

      return `
        <tr class="hover:bg-slate-50 border-b border-slate-100 text-xs">
          <td class="p-3 text-slate-400">${App.formatDate(m.created_at)}</td>
          <td class="p-3">${typeBadge}</td>
          <td class="p-3 font-mono font-bold text-emerald-700">${escapeHtml(m.reference_no)}</td>
          <td class="p-3">
            <p class="font-bold text-slate-900">${escapeHtml(m.material_name)}</p>
            <span class="text-[10px] text-slate-400 font-mono">${escapeHtml(m.material_code)}</span>
          </td>
          <td class="p-3 font-bold ${isPositive ? 'text-emerald-700' : 'text-rose-700'}">
            ${isPositive ? '+' : ''}${App.formatNumber(m.qty_change)} ${escapeHtml(m.material_unit)}
          </td>
          <td class="p-3 font-medium text-slate-700">
            <span class="text-slate-400 font-normal">${App.formatNumber(m.stock_before)} &rarr;</span>
            <b class="text-slate-900 font-bold">${App.formatNumber(m.stock_after)}</b> ${escapeHtml(m.material_unit)}
          </td>
          <td class="p-3 text-slate-500">${escapeHtml(m.notes || '-')}</td>
        </tr>
      `;
    }).join('');
  }
}

// 9. EXCEL / CSV IMPORTER MODAL & LOGIC
function openExcelImportModal() {
  parsedImportItems = [];
  document.getElementById('importPreviewSection').classList.add('hidden');
  document.getElementById('importSubmitBtn').classList.add('hidden');
  document.getElementById('importDropzone').classList.remove('hidden');
  document.getElementById('excelFileInput').value = '';
  document.getElementById('excelPasteText').value = '';
  App.openModal('modalExcelImport');
}

function handleExcelFileSelect(input) {
  if (input.files && input.files[0]) {
    previewExcelFile(input.files[0]);
  }
}

async function previewExcelFile(file) {
  const formData = new FormData();
  formData.append('file', file);

  const previewStatus = document.getElementById('importPreviewLoading');
  previewStatus.classList.remove('hidden');

  const res = await App.fetchJson('../api/import_excel.php?action=preview', {
    method: 'POST',
    body: formData
  });

  previewStatus.classList.add('hidden');

  if (res.success) {
    parsedImportItems = res.items;
    renderImportPreview(res);
  } else {
    App.toast(res.message || 'Gagal memproses file', 'error');
  }
}

async function previewExcelTextPaste() {
  const raw_text = document.getElementById('excelPasteText').value.trim();
  if (!raw_text) {
    App.toast('Silakan paste data tabel Excel terlebih dahulu.', 'warning');
    return;
  }

  const res = await App.fetchJson('../api/import_excel.php?action=preview', {
    method: 'POST',
    body: JSON.stringify({ raw_text })
  });

  if (res.success) {
    parsedImportItems = res.items;
    renderImportPreview(res);
  } else {
    App.toast(res.message || 'Gagal memproses data paste', 'error');
  }
}

function renderImportPreview(res) {
  const previewSection = document.getElementById('importPreviewSection');
  const submitBtn = document.getElementById('importSubmitBtn');
  const summaryEl = document.getElementById('importSummaryStats');
  const tbody = document.getElementById('importPreviewTableBody');

  summaryEl.innerHTML = `
    <div class="flex items-center gap-3 text-xs font-medium">
      <span class="text-slate-700">Total: <b>${res.summary.total_rows}</b> Baris</span>
      <span class="text-emerald-700">Baru: <b>${res.summary.new_items}</b></span>
      <span class="text-blue-700">Update Stok: <b>${res.summary.update_items}</b></span>
    </div>
  `;

  tbody.innerHTML = res.items.map(item => `
    <tr class="text-xs hover:bg-slate-50 border-b border-slate-100">
      <td class="p-2 font-mono font-bold text-emerald-800">${escapeHtml(item.item_no)}</td>
      <td class="p-2 font-bold text-slate-800">${escapeHtml(item.item_description)}</td>
      <td class="p-2 text-slate-600">${escapeHtml(item.category || '-')}</td>
      <td class="p-2 text-center font-semibold text-slate-700">
        <span class="px-1.5 py-0.2 bg-slate-100 rounded text-[10px]">${escapeHtml(item.unit || 'Pcs')}</span>
      </td>
      <td class="p-2 text-center font-bold text-emerald-700">${App.formatNumber(item.ending_stock)}</td>
      <td class="p-2 text-slate-600">${escapeHtml(item.rack_location || '-')}</td>
      <td class="p-2 whitespace-nowrap">
        ${item.status === 'NEW' 
          ? '<span class="px-2 py-0.5 bg-emerald-50 text-emerald-800 rounded font-bold text-[10px] border border-emerald-200">Baru</span>'
          : `<span class="px-2 py-0.5 bg-blue-50 text-blue-800 rounded font-bold text-[10px] border border-blue-200">Update (Lama: ${item.old_stock})</span>`}
      </td>
    </tr>
  `).join('');

  previewSection.classList.remove('hidden');
  submitBtn.classList.remove('hidden');
}

async function commitExcelImport() {
  if (parsedImportItems.length === 0) {
    App.toast('Tidak ada item untuk diimpor', 'warning');
    return;
  }

  const btn = document.getElementById('importSubmitBtn');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span> Mengimpor...';

  const res = await App.fetchJson('../api/import_excel.php?action=commit', {
    method: 'POST',
    body: JSON.stringify({ items: parsedImportItems })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">upload</span> Simpan ke Master Stok';

  if (res.success) {
    App.toast(res.message, 'success', 'Import Berhasil');
    App.closeModal('modalExcelImport');
    loadMaterials();
    loadStats();
    loadMutations();
  } else {
    App.toast(res.message || 'Gagal import', 'error');
  }
}

// 10. ADD & EDIT MATERIAL MODAL
function openAddMaterialModal() {
  document.getElementById('modalMaterialTitle').innerText = 'Tambah Material Packaging Baru';
  document.getElementById('materialIdInput').value = '';
  document.getElementById('formMaterial').reset();
  document.getElementById('materialInitialStockGroup').classList.remove('hidden');
  App.openModal('modalMaterialForm');
}

async function openEditMaterialModal(id) {
  const res = await App.fetchJson(`../api/materials.php?action=get&id=${id}`);
  if (res.success && res.data) {
    const m = res.data;
    document.getElementById('modalMaterialTitle').innerText = 'Edit Data Packaging';
    document.getElementById('materialIdInput').value = m.id;
    document.getElementById('materialCodeInput').value = m.code;
    document.getElementById('materialNameInput').value = m.name;
    document.getElementById('materialCategoryInput').value = m.category;
    document.getElementById('materialUnitInput').value = m.unit;
    document.getElementById('materialRackInput').value = m.rack_location;
    document.getElementById('materialMinStockInput').value = m.min_stock;
    document.getElementById('materialDescInput').value = m.description || '';
    
    document.getElementById('materialInitialStockGroup').classList.add('hidden');
    App.openModal('modalMaterialForm');
  }
}

async function handleMaterialFormSubmit(e) {
  e.preventDefault();
  const id = document.getElementById('materialIdInput').value;
  const code = document.getElementById('materialCodeInput').value.trim();
  const name = document.getElementById('materialNameInput').value.trim();
  const category = document.getElementById('materialCategoryInput').value.trim();
  const unit = document.getElementById('materialUnitInput').value.trim();
  const rack_location = document.getElementById('materialRackInput').value.trim();
  const min_stock = document.getElementById('materialMinStockInput').value;
  const initial_stock = document.getElementById('materialInitialStockInput')?.value || 0;
  const description = document.getElementById('materialDescInput').value.trim();

  const isEdit = id && parseInt(id) > 0;
  const endpoint = isEdit ? '../api/materials.php?action=update' : '../api/materials.php?action=create';

  const res = await App.fetchJson(endpoint, {
    method: 'POST',
    body: JSON.stringify({ id, code, name, category, unit, rack_location, min_stock, initial_stock, description })
  });

  if (res.success) {
    App.toast(res.message, 'success', isEdit ? 'Data Diperbarui' : 'Material Ditambahkan');
    App.closeModal('modalMaterialForm');
    loadMaterials();
    loadStats();
  } else {
    App.toast(res.message, 'error');
  }
}

async function deleteMaterial(id, name) {
  const res = await App.fetchJson('../api/materials.php?action=delete', {
    method: 'POST',
    body: JSON.stringify({ id })
  });

  if (res.success) {
    App.toast(res.message, 'success');
    loadMaterials();
    loadStats();
  } else {
    App.toast(res.message, 'error');
  }
}

// 11. USER & ROLE MANAGEMENT
async function loadUsers() {
  const tbody = document.getElementById('usersTableBody');
  if (tbody) {
    tbody.innerHTML = `
      <tr>
        <td colspan="6" class="p-8 text-center text-slate-400">
          <span class="material-symbols-outlined text-[28px] animate-spin text-emerald-600">progress_activity</span>
          <p class="text-xs font-semibold text-slate-600 mt-2">Memuat daftar pengguna...</p>
        </td>
      </tr>
    `;
  }

  const search = document.getElementById('userSearchInput')?.value || '';
  const role = document.getElementById('userRoleFilter')?.value || 'all';

  const query = new URLSearchParams({
    action: 'list',
    search,
    role
  });

  const res = await App.fetchJson(`../api/users.php?${query.toString()}`);
  if (res && res.success && res.data) {
    renderUsersTable(res.data);
  } else {
    if (tbody) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" class="p-8 text-center text-rose-500 text-xs font-medium">
            <p>Gagal memuat daftar pengguna.</p>
          </td>
        </tr>
      `;
    }
  }
}

function renderUsersTable(users) {
  const tbody = document.getElementById('usersTableBody');
  if (!tbody) return;

  if (users.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="6" class="p-8 text-center text-slate-400">
          <span class="material-symbols-outlined text-[32px] text-slate-300 mb-1">group</span>
          <p class="text-xs font-medium">Tidak ada pengguna yang sesuai.</p>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = users.map(u => {
    const isSuperAdmin = u.username.toLowerCase() === 'daniel';
    const isTeknisi = u.role === 'teknisi' || u.role === 'superadmin' || u.role === 'admin';

    let roleBadge = '';
    if (isTeknisi) {
      roleBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-purple-50 text-purple-800 border border-purple-200 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">engineering</span>Teknisi</span>';
    } else {
      roleBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[13px]">account_circle</span>Operator</span>';
    }

    return `
      <tr class="hover:bg-slate-50 border-b border-slate-100 transition-colors">
        <td class="p-3 font-mono font-bold text-slate-900 text-xs">
          ${escapeHtml(u.username)}
          ${isSuperAdmin ? '<span class="ml-1 px-1.5 py-0.2 rounded bg-purple-100 text-purple-900 font-bold text-[9px] border border-purple-300">MASTER</span>' : ''}
        </td>
        <td class="p-3 font-semibold text-slate-800 text-xs">${escapeHtml(u.name)}</td>
        <td class="p-3">${roleBadge}</td>
        <td class="p-3 text-xs text-slate-600 font-medium">${escapeHtml(u.shift || '-')}</td>
        <td class="p-3 text-xs text-slate-400">${App.formatDate(u.created_at)}</td>
        <td class="p-3 text-right space-x-1 whitespace-nowrap">
          <button onclick="openEditUserModal(${u.id})" title="Edit User" class="p-1 px-1.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs border border-slate-200 transition-colors">
            <span class="material-symbols-outlined text-[16px]">edit</span>
          </button>
          ${!isSuperAdmin ? `
            <button onclick="deleteUser(${u.id}, '${escapeHtml(u.name)}')" title="Hapus User" class="p-1 px-1.5 rounded bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-600 text-xs border border-rose-200 transition-colors">
              <span class="material-symbols-outlined text-[16px]">delete</span>
            </button>
          ` : ''}
        </td>
      </tr>
    `;
  }).join('');
}

function openAddUserModal() {
  document.getElementById('modalUserTitle').innerText = 'Tambah Pengguna Baru';
  document.getElementById('userIdInput').value = '';
  document.getElementById('formUser').reset();
  
  // Password is required for new user
  document.getElementById('userPasswordInput').required = true;
  document.getElementById('userPasswordRequiredTag').classList.remove('hidden');
  document.getElementById('userPasswordHint').classList.add('hidden');
  
  App.openModal('modalUserForm');
}

async function openEditUserModal(id) {
  const res = await App.fetchJson(`../api/users.php?action=get&id=${id}`);
  if (res.success && res.data) {
    const u = res.data;
    document.getElementById('modalUserTitle').innerText = 'Edit Data Pengguna';
    document.getElementById('userIdInput').value = u.id;
    document.getElementById('userUsernameInput').value = u.username;
    document.getElementById('userNameInput').value = u.name;
    document.getElementById('userRoleSelect').value = u.role;
    document.getElementById('userShiftInput').value = u.shift || '';
    
    // Password optional on edit
    const passInput = document.getElementById('userPasswordInput');
    passInput.value = '';
    passInput.required = false;
    document.getElementById('userPasswordRequiredTag').classList.add('hidden');
    document.getElementById('userPasswordHint').classList.remove('hidden');
    
    App.openModal('modalUserForm');
  }
}

async function handleUserFormSubmit(e) {
  e.preventDefault();
  const id = document.getElementById('userIdInput').value;
  const username = document.getElementById('userUsernameInput').value.trim();
  const name = document.getElementById('userNameInput').value.trim();
  const role = document.getElementById('userRoleSelect').value;
  const shift = document.getElementById('userShiftInput').value.trim();
  const password = document.getElementById('userPasswordInput').value.trim();

  const isEdit = id && parseInt(id) > 0;
  const endpoint = isEdit ? '../api/users.php?action=update' : '../api/users.php?action=create';

  const res = await App.fetchJson(endpoint, {
    method: 'POST',
    body: JSON.stringify({ id, username, name, role, shift, password })
  });

  if (res.success) {
    App.toast(res.message, 'success', isEdit ? 'User Diperbarui' : 'User Ditambahkan');
    App.closeModal('modalUserForm');
    loadUsers();
    loadOperators();
  } else {
    App.toast(res.message, 'error');
  }
}

async function deleteUser(id, name) {
  const res = await App.fetchJson('../api/users.php?action=delete', {
    method: 'POST',
    body: JSON.stringify({ id })
  });

  if (res.success) {
    App.toast(res.message, 'success');
    loadUsers();
    loadOperators();
  } else {
    App.toast(res.message, 'error');
  }
}

// 8. INBOUND GOODS RECEIPT (ADMIN & OPERATOR TRACKER WITH TABLE BATCH INPUT)
let inboundModalStartTime = null;

function renderMaterialOptionsHtml(selectedId = '', showStock = false) {
  let html = '<option value="">-- Pilih Kemas/Consumable --</option>';
  (allMaterials || []).forEach(m => {
    const isSel = (m.id == selectedId) ? 'selected' : '';
    const stockInfo = showStock ? ` (Stok: ${m.current_stock} ${m.unit})` : '';
    html += `<option value="${m.id}" data-rack="${escapeHtml(m.rack_location || '-')}" data-stock="${m.current_stock}" data-unit="${escapeHtml(m.unit)}" ${isSel}>${escapeHtml(m.name)}${stockInfo}</option>`;
  });
  return html;
}

function openAddInboundModal() {
  inboundModalStartTime = new Date().toISOString();
  populateMaterialSelects();
  
  const form = document.getElementById('inboundForm');
  if (form) form.reset();

  const tbody = document.getElementById('inboundItemsTableBody');
  if (tbody) tbody.innerHTML = '';

  const now = new Date();
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const dd = String(now.getDate()).padStart(2, '0');
  const hh = String(now.getHours()).padStart(2, '0');
  const mi = String(now.getMinutes()).padStart(2, '0');
  const dateStr = `${yyyy}-${mm}-${dd}`;
  const timeStr = `${hh}:${mi}`;
  const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  const displayFormattedDate = `${now.getDate()} ${monthNames[now.getMonth()]} ${yyyy}`;

  const dateInput = document.getElementById('inboundFormDate');
  const timeInput = document.getElementById('inboundFormTime');
  const dateDisplay = document.getElementById('inboundFormDateDisplay');
  const timeDisplay = document.getElementById('inboundFormTimeDisplay');

  if (dateInput) dateInput.value = dateStr;
  if (timeInput) timeInput.value = timeStr;
  if (dateDisplay) dateDisplay.value = displayFormattedDate;
  if (timeDisplay) timeDisplay.value = timeStr;

  // Add 1 default row
  addInboundTableRow();
  recalcInboundTotalQty();

  App.openModal('modalAddInbound');
}

function addInboundTableRow(data = null) {
  const tbody = document.getElementById('inboundItemsTableBody');
  if (!tbody) return;

  const rowCount = tbody.querySelectorAll('tr').length + 1;
  const matOptions = renderMaterialOptionsHtml(data?.material_id || '', false);

  const tr = document.createElement('tr');
  tr.className = 'hover:bg-slate-50 text-xs border-b border-slate-100 transition-colors';
  tr.innerHTML = `
    <td class="p-2.5 text-center font-bold text-slate-500 row-index">${rowCount}</td>
    <td class="p-2.5">
      <select required class="inbound-row-mat w-full p-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-800 outline-none focus:bg-white focus:border-emerald-600">
        ${matOptions}
      </select>
    </td>
    <td class="p-2.5">
      <input type="text" class="inbound-row-rack w-full p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-700 outline-none focus:bg-white focus:border-emerald-600" placeholder="Rak..." value="${escapeHtml(data?.rack || '')}">
    </td>
    <td class="p-2.5">
      <input type="number" required min="1" class="inbound-row-qty w-full p-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-black text-center text-emerald-800 outline-none focus:bg-white focus:border-emerald-600" placeholder="0" value="${data?.qty || ''}" oninput="recalcInboundTotalQty()">
    </td>
    <td class="p-2.5">
      <input type="text" class="inbound-row-notes w-full p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs outline-none focus:bg-white focus:border-emerald-600" placeholder="Catatan item..." value="${escapeHtml(data?.notes || '')}">
    </td>
    <td class="p-2.5 text-center">
      <button type="button" onclick="removeInboundTableRow(this)" class="w-8 h-8 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 flex items-center justify-center transition-colors mx-auto" title="Hapus Baris">
        <span class="material-symbols-outlined text-[18px]">delete</span>
      </button>
    </td>
  `;
  tbody.appendChild(tr);

  const selectEl = tr.querySelector('.inbound-row-mat');
  if (typeof TomSelect !== 'undefined' && selectEl) {
    const ts = new TomSelect(selectEl, {
      create: false,
      maxItems: 1,
      allowEmptyOption: true,
      placeholder: '-- Cari Kemas/Consumable --',
      dropdownParent: 'body',
      searchField: ['text'],
      onChange: function(value) {
        onInboundRowMaterialChange(selectEl, value);
      }
    });
    tr._tomSelect = ts;
  }

  recalcInboundTotalQty();
}

function removeInboundTableRow(btn) {
  const tbody = document.getElementById('inboundItemsTableBody');
  const tr = btn.closest('tr');
  if (tr) {
    if (tr._tomSelect) {
      try { tr._tomSelect.destroy(); } catch (e) {}
    }
    tr.remove();
  }

  if (tbody) {
    const rows = tbody.querySelectorAll('tr');
    if (rows.length === 0) {
      addInboundTableRow();
    } else {
      rows.forEach((r, idx) => {
        const idxEl = r.querySelector('.row-index');
        if (idxEl) idxEl.innerText = idx + 1;
      });
    }
  }
  recalcInboundTotalQty();
}

function onInboundRowMaterialChange(selectEl, val = null) {
  const tr = selectEl.closest('tr');
  if (!tr) return;
  const matId = parseInt(val || selectEl.value || '0');
  const foundMat = (allMaterials || []).find(m => m.id === matId);
  const rackInput = tr.querySelector('.inbound-row-rack');
  if (rackInput && foundMat) {
    if (foundMat.rack_location && foundMat.rack_location !== '-') {
      rackInput.value = foundMat.rack_location;
    }
  }
}

function recalcInboundTotalQty() {
  const qtyInputs = document.querySelectorAll('.inbound-row-qty');
  let total = 0;
  qtyInputs.forEach(input => {
    const val = parseInt(input.value || '0');
    if (val > 0) total += val;
  });
  const summaryEl = document.getElementById('inboundTotalQtySummary');
  if (summaryEl) summaryEl.innerText = App.formatNumber(total);
}

async function loadInboundHistory() {
  const tbody = document.getElementById('inboundHistoryTable');
  if (!tbody) return;

  const search = document.getElementById('inboundSearchInput')?.value || '';
  const date = document.getElementById('inboundDateFilter')?.value || '';
  const query = new URLSearchParams({ action: 'list', search, date, limit: 150 });

  const res = await App.fetchJson(`../api/inbound.php?${query.toString()}`);
  if (res.success && res.data) {
    window._currentInboundList = res.data;

    // Update KPI Metric Cards
    const totalEl = document.getElementById('inboundTotalQtyMetric');
    const avgDurEl = document.getElementById('inboundAvgDurationMetric');
    const avgTaktEl = document.getElementById('inboundAvgTaktTimeMetric');
    if (totalEl) totalEl.innerText = App.formatNumber(res.metrics?.total_inbound_qty || 0);
    if (avgDurEl) avgDurEl.innerText = App.formatDuration(res.metrics?.avg_duration_seconds || 0);
    if (avgTaktEl) avgTaktEl.innerText = App.formatTaktTime(res.metrics?.avg_takt_time_seconds || 0);

    if (res.data.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="6" class="p-8 text-center text-slate-400 text-xs font-medium">
            <span class="material-symbols-outlined text-[32px] text-slate-300 mb-1">move_to_inbox</span>
            <p>Tidak ada riwayat penerimaan barang masuk.</p>
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = res.data.map((i, idx) => `
      <tr class="hover:bg-slate-50 border-b border-slate-100 text-xs transition-colors">
        <td class="p-3 whitespace-nowrap">
          <span class="font-extrabold text-slate-800">${App.formatDate(i.completed_at || i.created_at)}</span>
        </td>
        <td class="p-3 font-mono font-bold text-emerald-800 whitespace-nowrap">
          <button type="button" onclick="openInboundDetailModal(${idx})" class="hover:underline inline-flex items-center gap-1 font-mono text-emerald-900 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 shadow-2xs" title="Klik untuk lihat detail transaksi">
            <span class="material-symbols-outlined text-[13px] text-emerald-600">visibility</span>
            <span>${escapeHtml(i.inbound_no)}</span>
          </button>
        </td>
        <td class="p-3">
          <div class="font-bold text-slate-900">${escapeHtml(i.material_name)}</div>
          <div class="text-[10px] text-slate-400 font-mono">${escapeHtml(i.material_code)} &bull; Rak: ${escapeHtml(i.rack_location || '-')}</div>
        </td>
        <td class="p-3 text-center whitespace-nowrap">
          <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-900 border border-emerald-200 font-black text-xs font-mono">
            +${App.formatNumber(i.qty)} <span class="text-[10px] text-emerald-700 font-normal">${escapeHtml(i.material_unit || 'Pcs')}</span>
          </span>
        </td>
        <td class="p-3 whitespace-nowrap text-slate-700">
          <span class="font-semibold text-slate-800">${escapeHtml(i.receiver_name || i.received_by || 'Admin')}</span>
        </td>
        <td class="p-3 text-right whitespace-nowrap">
          <button onclick="openInboundDetailModal(${idx})" title="Lihat Rincian Detail" class="p-1.5 rounded-lg bg-slate-100 hover:bg-emerald-600 hover:text-white text-slate-700 border border-slate-200 transition-colors inline-flex items-center justify-center shadow-2xs">
            <span class="material-symbols-outlined text-[16px]">visibility</span>
          </button>
        </td>
      </tr>
    `).join('');
  }
}

// ================= 8.1 MODAL DETAIL INBOUND =================
function openInboundDetailModal(idx) {
  const i = window._currentInboundList?.[idx];
  if (!i) return;

  const noEl = document.getElementById('detailInboundNo');
  const dateEl = document.getElementById('detailInboundDate');
  if (noEl) noEl.innerText = i.inbound_no;
  if (dateEl) dateEl.innerText = 'Tanggal Penerimaan: ' + App.formatDate(i.completed_at || i.created_at);

  const content = `
    <div class="p-3 bg-emerald-50/70 rounded-xl border border-emerald-200 flex items-center justify-between">
      <div class="flex items-center gap-1.5 font-bold text-emerald-900 text-xs">
        <span class="material-symbols-outlined text-[16px] text-emerald-700">inventory</span>
        <span>Penerimaan Barang Masuk (Inbound)</span>
      </div>
      <span class="px-2.5 py-0.5 rounded-md text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-300 inline-flex items-center gap-1">
        <span class="material-symbols-outlined text-[12px] text-emerald-600">check_circle</span>Selesai Diterima
      </span>
    </div>

    <div class="p-3.5 bg-white rounded-xl border border-slate-200 shadow-2xs">
      <div class="flex items-start justify-between">
        <div>
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Packaging Material</span>
          <p class="font-extrabold text-slate-900 text-sm mt-0.5">${escapeHtml(i.material_name)}</p>
          <p class="font-mono text-slate-500 text-xs mt-0.5">${escapeHtml(i.material_code)}</p>
        </div>
        <div class="text-right">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Jumlah Masuk</span>
          <p class="font-mono font-black text-emerald-800 text-base mt-0.5">+${App.formatNumber(i.qty)} ${escapeHtml(i.material_unit || 'Pcs')}</p>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-2.5">
      <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
          <span class="material-symbols-outlined text-[13px] text-emerald-600">grid_view</span>
          <span>Lokasi Rak Simpan</span>
        </span>
        <p class="font-bold text-slate-900 mt-1">${escapeHtml(i.rack_location || 'Gudang Utama')}</p>
      </div>

      <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
          <span class="material-symbols-outlined text-[13px] text-purple-600">category</span>
          <span>Kategori Material</span>
        </span>
        <p class="font-bold text-slate-700 mt-1">${escapeHtml(i.material_category || '-')}</p>
      </div>

      <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
          <span class="material-symbols-outlined text-[13px] text-blue-600">how_to_reg</span>
          <span>Petugas Penerima</span>
        </span>
        <p class="font-bold text-slate-900 mt-1">${escapeHtml(i.receiver_name || 'Admin')}</p>
        <span class="text-[10px] text-slate-400 font-medium">${escapeHtml(i.receiver_shift || 'Shift')}</span>
      </div>

      <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
          <span class="material-symbols-outlined text-[13px] text-amber-600">tag</span>
          <span>No. Referensi / Batch</span>
        </span>
        <p class="font-bold text-slate-900 font-mono mt-1">${escapeHtml(i.po_number || '-')}</p>
      </div>
    </div>

    ${i.notes ? `
      <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Catatan Penerimaan</span>
        <p class="text-slate-700 italic mt-0.5">"${escapeHtml(i.notes)}"</p>
      </div>
    ` : ''}

    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
      <span class="text-[10px] font-extrabold text-slate-700 uppercase tracking-wider block mb-1.5">Timeline & Metrik Pengerjaan</span>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
        <div class="p-1.5 bg-white rounded-lg border border-slate-200">
          <span class="text-[9px] text-slate-400 block">Mulai</span>
          <span class="font-mono font-bold text-slate-800 text-[11px]">${i.started_at ? i.started_at.split(' ')[1] || '-' : '-'}</span>
        </div>
        <div class="p-1.5 bg-white rounded-lg border border-slate-200">
          <span class="text-[9px] text-slate-400 block">Selesai</span>
          <span class="font-mono font-bold text-slate-800 text-[11px]">${i.completed_at ? i.completed_at.split(' ')[1] || '-' : '-'}</span>
        </div>
        <div class="p-1.5 bg-white rounded-lg border border-slate-200">
          <span class="text-[9px] text-slate-400 block">Durasi Kerja</span>
          <span class="font-mono font-bold text-emerald-800 text-[11px]">${App.formatDuration(i.duration_seconds)}</span>
        </div>
        <div class="p-1.5 bg-white rounded-lg border border-slate-200">
          <span class="text-[9px] text-slate-400 block">Takt Time</span>
          <span class="font-mono font-black text-purple-700 text-[11px]">${App.formatTaktTime(i.takt_time_seconds)}</span>
        </div>
      </div>
    </div>
  `;

  document.getElementById('detailInboundContent').innerHTML = content;
  App.openModal('modalInboundDetail');
}

async function handleInboundTableSubmit(e) {
  e.preventDefault();
  const rows = document.querySelectorAll('#inboundItemsTableBody tr');
  const items = [];

  rows.forEach(r => {
    const matSelect = r.querySelector('.inbound-row-mat');
    const qtyInput = r.querySelector('.inbound-row-qty');
    const rackInput = r.querySelector('.inbound-row-rack');
    const notesInput = r.querySelector('.inbound-row-notes');

    const material_id = parseInt(matSelect?.value || '0');
    const qty = parseInt(qtyInput?.value || '0');
    const rack = rackInput?.value?.trim() || '';
    const notes = notesInput?.value?.trim() || '';

    if (material_id > 0 && qty > 0) {
      items.push({ material_id, qty, rack_location: rack, notes });
    }
  });

  if (items.length === 0) {
    App.toast('Silakan pilih minimal 1 material dengan Qty lebih dari 0', 'warning');
    return;
  }

  const po_number = document.getElementById('inboundPoNumber')?.value?.trim() || '-';
  const notes = document.getElementById('inboundGlobalNotes')?.value?.trim() || '';
  const formDate = document.getElementById('inboundFormDate')?.value;
  const formTime = document.getElementById('inboundFormTime')?.value;
  const started_at = (formDate && formTime) ? `${formDate} ${formTime}:00` : (inboundModalStartTime || new Date().toISOString());

  const btnSubmit = document.getElementById('btnSubmitInboundTable');
  if (btnSubmit) {
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="material-symbols-outlined text-[17px] animate-spin">progress_activity</span><span>Menyimpan...</span>';
  }

  const res = await App.fetchJson('../api/inbound.php?action=batch_create', {
    method: 'POST',
    body: JSON.stringify({ po_number, supplier: '-', items, notes, started_at })
  });

  if (btnSubmit) {
    btnSubmit.disabled = false;
    btnSubmit.innerHTML = '<span class="material-symbols-outlined text-[17px]">save</span><span>Simpan & Tambah Stok</span>';
  }

  if (res.success) {
    App.toast(res.message, 'success', 'Barang Masuk Disimpan');
    App.closeModal('modalAddInbound');
    document.getElementById('inboundForm')?.reset();
    loadInboundHistory();
    loadStats();
    loadMaterials();
  } else {
    App.toast(res.message || 'Gagal menyimpan barang masuk', 'error');
  }
}

// 9. OUTBOUND MANUAL GOODS DISPATCH & OPERATOR PICKING TRACKER (WITH DURATION & TAKT TIME)
// 9. OUTBOUND MANUAL GOODS DISPATCH & OPERATOR PICKING TRACKER (TABLE BATCH INPUT)
let outboundModalStartTime = null;

function openAddOutboundModal() {
  outboundModalStartTime = new Date().toISOString();
  populateMaterialSelects();
  
  const form = document.getElementById('outboundForm');
  if (form) form.reset();

  const tbody = document.getElementById('outboundItemsTableBody');
  if (tbody) tbody.innerHTML = '';

  const now = new Date();
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const dd = String(now.getDate()).padStart(2, '0');
  const hh = String(now.getHours()).padStart(2, '0');
  const mi = String(now.getMinutes()).padStart(2, '0');
  const dateStr = `${yyyy}-${mm}-${dd}`;
  const timeStr = `${hh}:${mi}`;
  const monthNames = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  const displayFormattedDate = `${now.getDate()} ${monthNames[now.getMonth()]} ${yyyy}`;

  const dateInput = document.getElementById('outboundFormDate');
  const timeInput = document.getElementById('outboundFormTime');
  const dateDisplay = document.getElementById('outboundFormDateDisplay');
  const timeDisplay = document.getElementById('outboundFormTimeDisplay');

  if (dateInput) dateInput.value = dateStr;
  if (timeInput) timeInput.value = timeStr;
  if (dateDisplay) dateDisplay.value = displayFormattedDate;
  if (timeDisplay) timeDisplay.value = timeStr;

  // Add 1 default row
  addOutboundTableRow();
  recalcOutboundTotalQty();

  App.openModal('modalAddOutbound');
}

function addOutboundTableRow(data = null) {
  const tbody = document.getElementById('outboundItemsTableBody');
  if (!tbody) return;

  const rowCount = tbody.querySelectorAll('tr').length + 1;
  const matOptions = renderMaterialOptionsHtml(data?.material_id || '', true);

  const tr = document.createElement('tr');
  tr.className = 'hover:bg-slate-50 text-xs border-b border-slate-100 transition-colors';
  tr.innerHTML = `
    <td class="p-2.5 text-center font-bold text-slate-500 row-index">${rowCount}</td>
    <td class="p-2.5">
      <select required class="outbound-row-mat w-full p-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-800 outline-none focus:bg-white focus:border-amber-600">
        ${matOptions}
      </select>
    </td>
    <td class="p-2.5">
      <select required class="outbound-row-brand w-full p-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-bold text-slate-800 outline-none focus:bg-white focus:border-amber-600">
        <option value="HANASUI" ${data?.destination === 'HANASUI' ? 'selected' : ''}>HANASUI</option>
        <option value="NCO" ${data?.destination === 'NCO' ? 'selected' : ''}>NCO</option>
        <option value="FYNE" ${data?.destination === 'FYNE' ? 'selected' : ''}>FYNE</option>
        <option value="EOMMA" ${data?.destination === 'EOMMA' ? 'selected' : ''}>EOMMA</option>
      </select>
    </td>
    <td class="p-2.5">
      <input type="number" required min="1" class="outbound-row-qty w-full p-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-black text-center text-amber-900 outline-none focus:bg-white focus:border-amber-600" placeholder="0" value="${data?.qty || ''}" oninput="validateOutboundRowQty(this); recalcOutboundTotalQty();">
    </td>
    <td class="p-2.5">
      <input type="text" required class="outbound-row-reason w-full p-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-800 outline-none focus:bg-white focus:border-amber-600" placeholder="Contoh: Uji Kualitas / Rusak / Reject" value="${escapeHtml(data?.reason || 'Kebutuhan Produksi')}">
    </td>
    <td class="p-2.5 text-center">
      <button type="button" onclick="removeOutboundTableRow(this)" class="w-8 h-8 rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50 flex items-center justify-center transition-colors mx-auto" title="Hapus Baris">
        <span class="material-symbols-outlined text-[18px]">delete</span>
      </button>
    </td>
  `;
  tbody.appendChild(tr);

  const selectEl = tr.querySelector('.outbound-row-mat');
  if (typeof TomSelect !== 'undefined' && selectEl) {
    const ts = new TomSelect(selectEl, {
      create: false,
      maxItems: 1,
      allowEmptyOption: true,
      placeholder: '-- Cari Kemas/Consumable --',
      dropdownParent: 'body',
      searchField: ['text'],
      onChange: function(value) {
        onOutboundRowMaterialChange(selectEl, value);
      }
    });
    tr._tomSelect = ts;
  }

  recalcOutboundTotalQty();
}

function removeOutboundTableRow(btn) {
  const tbody = document.getElementById('outboundItemsTableBody');
  const tr = btn.closest('tr');
  if (tr) {
    if (tr._tomSelect) {
      try { tr._tomSelect.destroy(); } catch (e) {}
    }
    tr.remove();
  }

  if (tbody) {
    const rows = tbody.querySelectorAll('tr');
    if (rows.length === 0) {
      addOutboundTableRow();
    } else {
      rows.forEach((r, idx) => {
        const idxEl = r.querySelector('.row-index');
        if (idxEl) idxEl.innerText = idx + 1;
      });
    }
  }
  recalcOutboundTotalQty();
}

function onOutboundRowMaterialChange(selectEl, val = null) {
  const tr = selectEl.closest('tr');
  if (!tr) return;
  const qtyInput = tr.querySelector('.outbound-row-qty');
  if (qtyInput) validateOutboundRowQty(qtyInput);
}

function validateOutboundRowQty(qtyInput) {
  const tr = qtyInput.closest('tr');
  if (!tr) return;
  const matSelect = tr.querySelector('.outbound-row-mat');
  const matId = parseInt(matSelect?.value || '0');
  const foundMat = (allMaterials || []).find(m => m.id === matId);
  const stock = foundMat ? parseInt(foundMat.current_stock || '0') : 0;
  const val = parseInt(qtyInput.value || '0');

  if (matId > 0 && val > stock) {
    qtyInput.classList.add('border-rose-500', 'bg-rose-50', 'text-rose-700');
    qtyInput.classList.remove('border-slate-300', 'bg-slate-50', 'text-amber-900');
  } else {
    qtyInput.classList.remove('border-rose-500', 'bg-rose-50', 'text-rose-700');
    qtyInput.classList.add('border-slate-300', 'bg-slate-50', 'text-amber-900');
  }
}

function recalcOutboundTotalQty() {
  const qtyInputs = document.querySelectorAll('.outbound-row-qty');
  let total = 0;
  qtyInputs.forEach(input => {
    const val = parseInt(input.value || '0');
    if (val > 0) total += val;
  });
  const summaryEl = document.getElementById('outboundTotalQtySummary');
  if (summaryEl) summaryEl.innerText = App.formatNumber(total);
}

async function loadOutboundHistory() {
  const tbody = document.getElementById('outboundHistoryTable');
  if (!tbody) return;

  const search = document.getElementById('outboundSearchInput')?.value || '';
  const typeFilter = document.getElementById('outboundTypeFilter')?.value || 'ALL';
  const statusFilter = document.getElementById('outboundStatusFilter')?.value || 'ALL';
  const date = document.getElementById('outboundDateFilter')?.value || '';
  const query = new URLSearchParams({ action: 'list', search, type: typeFilter, status: statusFilter, date, limit: 150 });

  const res = await App.fetchJson(`../api/outbound.php?${query.toString()}`);
  if (res.success && res.data) {
    // Update KPI Metric Cards
    const totalEl = document.getElementById('outboundTotalQtyMetric');
    const avgDurEl = document.getElementById('outboundAvgDurationMetric');
    const avgTaktEl = document.getElementById('outboundAvgTaktTimeMetric');
    if (totalEl) totalEl.innerText = App.formatNumber(res.metrics?.total_outbound_qty || 0);
    if (avgDurEl) avgDurEl.innerText = App.formatDuration(res.metrics?.avg_duration_seconds || 0);
    if (avgTaktEl) avgTaktEl.innerText = App.formatTaktTime(res.metrics?.avg_takt_time_seconds || 0);

    let currentOutboundList = res.data;
    window._currentOutboundList = currentOutboundList;

    if (res.data.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="7" class="p-8 text-center text-slate-400 text-xs font-medium">
            <span class="material-symbols-outlined text-[32px] text-slate-300 mb-1">outbox</span>
            <p>Tidak ada riwayat pengeluaran barang keluar.</p>
          </td>
        </tr>
      `;
      return;
    }

    tbody.innerHTML = res.data.map((o, idx) => {
      const isTask = o.outbound_type === 'TASK_PICKING';

      let statusBadge = '';
      if (o.status === 'COMPLETED') {
        statusBadge = '<span class="px-2.5 py-0.5 rounded-md text-[10px] font-extrabold bg-emerald-50 text-emerald-800 border border-emerald-200 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[12px] text-emerald-600">check_circle</span>Selesai</span>';
      } else if (o.status === 'IN_PROGRESS') {
        statusBadge = '<span class="px-2.5 py-0.5 rounded-md text-[10px] font-extrabold bg-amber-50 text-amber-900 border border-amber-300 inline-flex items-center gap-1 shadow-2xs"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>On Proses</span>';
      } else if (o.status === 'CANCELLED') {
        statusBadge = '<span class="bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded-md text-[10px] font-bold">Dibatalkan</span>';
      } else {
        statusBadge = '<span class="bg-blue-50 text-blue-800 border border-blue-200 px-2.5 py-0.5 rounded-md text-[10px] font-bold inline-flex items-center gap-1"><span class="material-symbols-outlined text-[12px] text-blue-600">schedule</span>Pending</span>';
      }

      const adminUser = o.assigned_by_name || o.assigned_by_username || 'admin';

      return `
        <tr class="hover:bg-slate-50/90 border-b border-slate-100 text-xs transition-colors duration-150">
          <!-- 1. Tanggal -->
          <td class="py-3.5 px-3.5 align-middle whitespace-nowrap">
            <span class="font-extrabold text-slate-800 text-xs">${App.formatDate(o.completed_at || o.created_at)}</span>
          </td>

          <!-- 2. No Referensi / Task (Klik untuk Detail) -->
          <td class="py-3.5 px-3.5 align-middle whitespace-nowrap">
            <button type="button" onclick="openOutboundDetailModal(${idx})" class="inline-flex items-center gap-1 font-mono font-bold text-xs text-amber-900 bg-amber-50 hover:bg-amber-100 border border-amber-200/80 px-2 py-0.5 rounded-lg shadow-2xs transition-colors cursor-pointer group text-left" title="Klik untuk melihat rincian detail dokumen">
              <span class="material-symbols-outlined text-[13px] text-amber-700 group-hover:text-amber-900">${isTask ? 'task_alt' : 'outbox'}</span>
              <span class="group-hover:underline underline-offset-2">${escapeHtml(o.outbound_no)}</span>
            </button>
          </td>

          <!-- 3. Status -->
          <td class="py-3.5 px-3.5 align-middle whitespace-nowrap">
            ${statusBadge}
          </td>

          <!-- 4. Packaging Material -->
          <td class="py-3.5 px-3.5 align-middle min-w-[220px]">
            <div>
              <p class="font-bold text-slate-900 text-xs leading-snug">${escapeHtml(o.material_name)}</p>
              <div class="flex items-center gap-2 text-[10px] text-slate-500 font-mono mt-1">
                <span class="px-1.5 py-0.2 rounded bg-slate-100 text-slate-700 font-semibold border border-slate-200/70">${escapeHtml(o.material_code)}</span>
                <span class="flex items-center gap-0.5 text-slate-500">
                  <span class="material-symbols-outlined text-[12px] text-slate-400">grid_view</span>
                  <span>Rak: ${escapeHtml(o.rack_location || 'Gudang Utama')}</span>
                </span>
              </div>
            </div>
          </td>

          <!-- 5. Qty Out -->
          <td class="py-3.5 px-3.5 align-middle text-center whitespace-nowrap">
            <div class="inline-flex flex-col items-center">
              <span class="px-3 py-1 rounded-lg bg-amber-50 text-amber-900 border border-amber-200/90 font-black text-xs font-mono shadow-2xs inline-flex items-center gap-1">
                <span>-${App.formatNumber(o.qty)}</span>
                <span class="text-[10px] font-bold text-amber-700 uppercase">${escapeHtml(o.material_unit || 'Pcs')}</span>
              </span>
              ${isTask && o.status === 'COMPLETED' && o.actual_qty !== o.target_qty ? `<span class="text-[10px] text-slate-400 font-mono mt-0.5">Target: ${App.formatNumber(o.target_qty)} ${escapeHtml(o.material_unit || 'Pcs')}</span>` : ''}
            </div>
          </td>

          <!-- 6. Tujuan Antar & PIC -->
          <td class="py-3.5 px-3.5 align-middle whitespace-nowrap">
            <div>
              <p class="font-extrabold text-slate-800 text-xs flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px] text-amber-600">fmd_good</span>
                <span>${escapeHtml(o.destination || '-')}</span>
              </p>
              <div class="flex items-center gap-1.5 text-[11px] text-slate-600 mt-1">
                <span class="w-5 h-5 rounded-full bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-500 font-bold text-[10px] flex-shrink-0">
                  <span class="material-symbols-outlined text-[12px]">person</span>
                </span>
                <span class="font-semibold text-slate-800">${escapeHtml(o.issued_by || 'Operator')}</span>
                ${isTask ? `<span class="text-[10px] font-mono text-emerald-800 font-bold bg-emerald-50 border border-emerald-200 px-1.5 py-0.2 rounded">(Admin: @${escapeHtml(adminUser)})</span>` : ''}
              </div>
            </div>
          </td>

          <!-- 7. Aksi (Edit & Batal untuk Task Aktif) -->
          <td class="py-3.5 px-3.5 align-middle text-center whitespace-nowrap">
            ${isTask && (o.status === 'PENDING' || o.status === 'IN_PROGRESS') ? `
              <div class="flex items-center justify-center gap-1.5">
                <button type="button" onclick="openEditTaskModal(${o.task_id})" class="p-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-800 border border-emerald-200 transition-colors inline-flex items-center justify-center shadow-2xs" title="Edit Penugasan Task (Ganti Produk / Operator)">
                  <span class="material-symbols-outlined text-[16px]">edit</span>
                </button>
                <button type="button" onclick="cancelOutboundTask(${o.task_id})" class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-700 border border-rose-200 transition-colors inline-flex items-center justify-center shadow-2xs" title="Batalkan Penugasan Task">
                  <span class="material-symbols-outlined text-[16px]">cancel</span>
                </button>
              </div>
            ` : `<span class="text-slate-400 font-mono text-[11px]">-</span>`}
          </td>
        </tr>
      `;
    }).join('');
  }
}

// ================= 9.0 MODAL DETAIL OUTBOUND (ICON MATA) =================
function openOutboundDetailModal(idx) {
  const o = window._currentOutboundList?.[idx];
  if (!o) return;

  const noEl = document.getElementById('detailOutboundNo');
  const dateEl = document.getElementById('detailOutboundDate');
  if (noEl) noEl.innerText = o.outbound_no;
  if (dateEl) dateEl.innerText = 'Tanggal Transaksi: ' + App.formatDate(o.completed_at || o.created_at);

  const isTask = o.outbound_type === 'TASK_PICKING';
  let statusBadge = '';
  if (o.status === 'COMPLETED') {
    statusBadge = '<span class="px-2.5 py-0.5 rounded-md text-[10px] font-extrabold bg-emerald-50 text-emerald-800 border border-emerald-200 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[12px] text-emerald-600">check_circle</span>Selesai</span>';
  } else if (o.status === 'IN_PROGRESS') {
    statusBadge = '<span class="px-2.5 py-0.5 rounded-md text-[10px] font-extrabold bg-amber-50 text-amber-900 border border-amber-300 inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>On Proses</span>';
  } else if (o.status === 'CANCELLED') {
    statusBadge = '<span class="bg-rose-50 text-rose-700 border border-rose-200 px-2 py-0.5 rounded-md text-[10px] font-bold">Dibatalkan</span>';
  } else {
    statusBadge = '<span class="bg-blue-50 text-blue-800 border border-blue-200 px-2.5 py-0.5 rounded-md text-[10px] font-bold inline-flex items-center gap-1"><span class="material-symbols-outlined text-[12px] text-blue-600">schedule</span>Pending</span>';
  }

  const typeBadge = isTask
    ? '<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200/70 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">engineering</span>Penugasan Task Operator</span>'
    : '<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">edit_note</span>Pengeluaran Manual Admin</span>';

  const adminUser = o.assigned_by_name || o.assigned_by_username || 'admin';
  const operatorUser = o.issued_by || 'Operator';

  const content = `
    <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 flex items-center justify-between">
      <div>${typeBadge}</div>
      <div>${statusBadge}</div>
    </div>

    <div class="p-3.5 bg-white rounded-xl border border-slate-200 shadow-2xs">
      <div class="flex items-start justify-between">
        <div>
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Packaging Material</span>
          <p class="font-extrabold text-slate-900 text-sm mt-0.5">${escapeHtml(o.material_name)}</p>
          <p class="font-mono text-slate-500 text-xs mt-0.5">${escapeHtml(o.material_code)} &bull; Rak: ${escapeHtml(o.rack_location || '-')}</p>
        </div>
        <div class="text-right">
          <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Jumlah Keluar</span>
          <p class="font-mono font-black text-amber-900 text-base mt-0.5">${App.formatNumber(o.qty)} ${escapeHtml(o.material_unit || 'Pcs')}</p>
          ${isTask && o.actual_qty && o.target_qty && o.actual_qty !== o.target_qty ? `<span class="text-[10px] text-slate-400 font-mono block">Target: ${App.formatNumber(o.target_qty)}</span>` : ''}
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-2.5">
      <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
          <span class="material-symbols-outlined text-[13px] text-amber-600">fmd_good</span>
          <span>Tujuan Antar</span>
        </span>
        <p class="font-bold text-slate-900 mt-1">${escapeHtml(o.destination || '-')}</p>
      </div>

      <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
          <span class="material-symbols-outlined text-[13px] text-slate-500">person</span>
          <span>Petugas PIC (Operator)</span>
        </span>
        <p class="font-bold text-slate-900 mt-1">${escapeHtml(operatorUser)}</p>
      </div>

      <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
          <span class="material-symbols-outlined text-[13px] text-emerald-600">admin_panel_settings</span>
          <span>Admin Penugas</span>
        </span>
        <p class="font-bold text-emerald-800 font-mono mt-1">@${escapeHtml(adminUser)}</p>
      </div>

      <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
          <span class="material-symbols-outlined text-[13px] text-purple-600">flag</span>
          <span>Prioritas</span>
        </span>
        <p class="font-bold ${o.priority === 'URGENT' ? 'text-rose-600 font-extrabold' : 'text-slate-700'} mt-1">${escapeHtml(o.priority || 'NORMAL')}</p>
      </div>
    </div>

    <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
      <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Alasan / Keperluan</span>
      <p class="font-semibold text-slate-800 mt-0.5">${escapeHtml(o.reason || '-')}</p>
    </div>

    ${o.notes ? `
      <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200/80">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Catatan / Instruksi</span>
        <p class="text-slate-700 italic mt-0.5">"${escapeHtml(o.notes)}"</p>
      </div>
    ` : ''}

    <div class="p-3 bg-blue-50/60 rounded-xl border border-blue-200/70">
      <span class="text-[10px] font-extrabold text-blue-900 uppercase tracking-wider block mb-1.5">Timeline & Metrik Pengerjaan</span>
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-center">
        <div class="p-1.5 bg-white rounded-lg border border-blue-100">
          <span class="text-[9px] text-slate-400 block">Mulai</span>
          <span class="font-mono font-bold text-slate-800 text-[11px]">${o.started_at ? o.started_at.split(' ')[1] || '-' : '-'}</span>
        </div>
        <div class="p-1.5 bg-white rounded-lg border border-blue-100">
          <span class="text-[9px] text-slate-400 block">Selesai</span>
          <span class="font-mono font-bold text-slate-800 text-[11px]">${o.completed_at ? o.completed_at.split(' ')[1] || '-' : '-'}</span>
        </div>
        <div class="p-1.5 bg-white rounded-lg border border-blue-100">
          <span class="text-[9px] text-slate-400 block">Durasi Kerja</span>
          <span class="font-mono font-bold text-blue-700 text-[11px]">${App.formatDuration(o.duration_seconds)}</span>
        </div>
        <div class="p-1.5 bg-white rounded-lg border border-blue-100">
          <span class="text-[9px] text-slate-400 block">Takt Time</span>
          <span class="font-mono font-black text-purple-700 text-[11px]">${App.formatTaktTime(o.takt_time_seconds)}</span>
        </div>
      </div>
    </div>
  `;

  document.getElementById('detailOutboundContent').innerHTML = content;
  App.openModal('modalOutboundDetail');
}

// ================= 9.1 EDIT & CANCEL TASK MODAL HANDLERS =================
async function openEditTaskModal(taskId) {
  const matSelect = document.getElementById('editTaskMaterialSelect');
  const opSelect = document.getElementById('editTaskOperatorSelect');

  if (matSelect) {
    matSelect.innerHTML = '<option value="">-- Pilih Material Packaging --</option>' +
      allMaterials.map(m => `
        <option value="${m.id}" data-code="${escapeHtml(m.code)}" data-name="${escapeHtml(m.name)}" data-stock="${m.current_stock}" data-rack="${escapeHtml(m.rack_location)}">
          ${escapeHtml(m.name)} (Stok: ${App.formatNumber(m.current_stock)} | Rak: ${escapeHtml(m.rack_location)})
        </option>
      `).join('');
    App.syncSearchableSelect(matSelect);
  }

  if (opSelect) {
    opSelect.innerHTML = '<option value="">-- Pilih PIC --</option>' +
      allOperators.map(op => `
        <option value="${op.id}">${escapeHtml(op.name)} (${escapeHtml(op.shift || 'Shift')})</option>
      `).join('');
    App.syncSearchableSelect(opSelect);
  }

  const res = await App.fetchJson(`../api/tasks.php?action=get&id=${taskId}`);
  if (!res.success || !res.data) {
    App.toast(res.message || 'Gagal memuat detail task', 'error');
    return;
  }

  const t = res.data;
  document.getElementById('editTaskId').value = t.id;
  document.getElementById('editTaskNoSubtitle').innerText = `No. Task: #${t.task_no} (${t.status})`;
  if (matSelect) matSelect.value = t.material_id;
  document.getElementById('editTaskTargetQty').value = t.target_qty;
  if (opSelect) opSelect.value = t.assigned_to;
  document.getElementById('editTaskDestination').value = t.destination;
  document.getElementById('editTaskPriority').value = t.priority || 'NORMAL';
  document.getElementById('editTaskNotes').value = t.notes || '';

  App.openModal('modalEditTask');
}

async function handleEditTaskSubmit(e) {
  e.preventDefault();
  const taskId      = document.getElementById('editTaskId').value;
  const material_id = document.getElementById('editTaskMaterialSelect').value;
  const target_qty  = document.getElementById('editTaskTargetQty').value;
  const assigned_to = document.getElementById('editTaskOperatorSelect').value;
  const destination = document.getElementById('editTaskDestination').value.trim();
  const priority    = document.getElementById('editTaskPriority').value;
  const notes       = document.getElementById('editTaskNotes').value.trim();

  const submitBtn = document.getElementById('btnEditTaskSubmit');
  submitBtn.disabled = true;
  submitBtn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Menyimpan...</span>';

  const res = await App.fetchJson('../api/tasks.php?action=update', {
    method: 'POST',
    body: JSON.stringify({ task_id: taskId, material_id, target_qty, assigned_to, destination, priority, notes })
  });

  submitBtn.disabled = false;
  submitBtn.innerHTML = '<span class="material-symbols-outlined text-[16px]">save</span><span>Simpan Perubahan Penugasan</span>';

  if (res.success) {
    App.toast(res.message, 'success', 'Berhasil Diperbarui');
    App.closeModal('modalEditTask');
    loadOutboundHistory();
    loadTasks();
    loadStats();
  } else {
    App.toast(res.message || 'Gagal menyimpan perubahan task', 'error');
  }
}

async function cancelOutboundTask(taskId) {
  const res = await App.fetchJson('../api/tasks.php?action=cancel', {
    method: 'POST',
    body: JSON.stringify({ task_id: taskId })
  });

  if (res.success) {
    App.toast(res.message, 'success', 'Task Dibatalkan');
    loadOutboundHistory();
    loadTasks();
    loadStats();
  } else {
    App.toast(res.message || 'Gagal membatalkan task', 'error');
  }
}

async function handleOutboundTableSubmit(e) {
  e.preventDefault();
  const rows = document.querySelectorAll('#outboundItemsTableBody tr');
  const items = [];
  let hasStockError = false;

  rows.forEach(r => {
    const matSelect = r.querySelector('.outbound-row-mat');
    const brandSelect = r.querySelector('.outbound-row-brand');
    const qtyInput = r.querySelector('.outbound-row-qty');
    const reasonInput = r.querySelector('.outbound-row-reason');

    const material_id = parseInt(matSelect?.value || '0');
    const destination = brandSelect?.value?.trim() || 'HANASUI';
    const qty = parseInt(qtyInput?.value || '0');
    const reason = reasonInput?.value?.trim() || 'Kebutuhan Produksi';

    const selectedOpt = matSelect?.options[matSelect?.selectedIndex];
    const stock = parseInt(selectedOpt?.getAttribute('data-stock') || '0');

    if (material_id > 0 && qty > 0) {
      if (qty > stock) {
        hasStockError = true;
        qtyInput?.focus();
      }
      items.push({ material_id, qty, destination, reason });
    }
  });

  if (hasStockError) {
    App.toast('Terdapat jumlah keluar yang melebihi sisa stok gudang!', 'error');
    return;
  }

  if (items.length === 0) {
    App.toast('Silakan pilih minimal 1 material dengan Qty lebih dari 0', 'warning');
    return;
  }

  const notes = document.getElementById('outboundGlobalNotes')?.value?.trim() || '';
  const formDate = document.getElementById('outboundFormDate')?.value;
  const formTime = document.getElementById('outboundFormTime')?.value;
  const started_at = (formDate && formTime) ? `${formDate} ${formTime}:00` : (outboundModalStartTime || new Date().toISOString());

  const btnSubmit = document.getElementById('btnSubmitOutboundTable');
  if (btnSubmit) {
    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span class="material-symbols-outlined text-[17px] animate-spin">progress_activity</span><span>Menyimpan...</span>';
  }

  const res = await App.fetchJson('../api/outbound.php?action=batch_create', {
    method: 'POST',
    body: JSON.stringify({ items, notes, started_at })
  });

  if (btnSubmit) {
    btnSubmit.disabled = false;
    btnSubmit.innerHTML = '<span class="material-symbols-outlined text-[17px]">save</span><span>Catat & Potong Stok</span>';
  }

  if (res.success) {
    App.toast(res.message, 'success', 'Pengeluaran Disimpan');
    App.closeModal('modalAddOutbound');
    document.getElementById('outboundForm')?.reset();
    loadOutboundHistory();
    loadStats();
    loadMaterials();
  } else {
    App.toast(res.message || 'Gagal memproses pengeluaran barang', 'error');
  }
}

// 10. STOCK MUTATIONS AUDIT TRAIL
let allMutationsData = [];

async function loadMutations(force = false) {
  const tbody = document.getElementById('mutationsTableBody');
  if (!tbody) return;

  // If already loaded in memory and not force refreshed, render instantly in 0ms!
  if (allMutationsData.length > 0 && !force) {
    renderMutationsTable();
    return;
  }

  tbody.innerHTML = `
    <tr>
      <td colspan="7" class="p-8 text-center text-slate-400 text-xs font-semibold">
        <span class="material-symbols-outlined text-[28px] animate-spin text-emerald-600">progress_activity</span>
        <p class="mt-2 text-slate-600">Memuat data buku mutasi stok...</p>
      </td>
    </tr>
  `;

  const res = await App.fetchJson('../api/mutations.php?action=list&limit=200');
  if (res.success && res.data) {
    allMutationsData = res.data;
    renderMutationsTable();
  } else {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="p-8 text-center text-rose-500 text-xs font-medium">
          <p>Gagal memuat catatan mutasi.</p>
        </td>
      </tr>
    `;
  }
}

function renderMutationsTable() {
  const tbody = document.getElementById('mutationsTableBody');
  if (!tbody) return;

  const search = (document.getElementById('mutationSearchInput')?.value || '').trim().toLowerCase();
  const type   = document.getElementById('mutationTypeFilter')?.value || 'ALL';
  const date   = (document.getElementById('mutationDateFilter')?.value || '').trim();

  let filtered = allMutationsData;

  if (type !== 'ALL') {
    filtered = filtered.filter(m => m.type === type);
  }

  if (date) {
    filtered = filtered.filter(m => (m.created_at || '').startsWith(date));
  }

  if (search) {
    filtered = filtered.filter(m => {
      const matchCode = (m.material_code || '').toLowerCase().includes(search);
      const matchName = (m.material_name || '').toLowerCase().includes(search);
      const matchRef  = (m.reference_no || '').toLowerCase().includes(search);
      const matchNote = (m.notes || '').toLowerCase().includes(search);
      const matchUser = (m.user_name || m.user_role || '').toLowerCase().includes(search);
      return matchCode || matchName || matchRef || matchNote || matchUser;
    });
  }

  if (filtered.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="p-8 text-center text-slate-400 text-xs font-medium">
          <span class="material-symbols-outlined text-[32px] text-slate-300 mb-1">history</span>
          <p>Tidak ada rekaman audit mutasi stok yang sesuai kriteria.</p>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = filtered.map(m => {
    const isPositive = m.qty_change > 0;
    let typeBadge = '';
    if (m.type === 'INBOUND') {
      typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">INBOUND</span>';
    } else if (m.type === 'OUTBOUND') {
      typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">OUTBOUND</span>';
    } else if (m.type === 'TASK_PICKING') {
      typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200">TASK PICKING</span>';
    } else if (m.type === 'ADJUSTMENT') {
      typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-800 border border-purple-200">ADJUSTMENT</span>';
    } else {
      typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">INITIAL</span>';
    }

    const changeClass = isPositive ? 'text-emerald-600 font-bold' : (m.qty_change < 0 ? 'text-rose-600 font-bold' : 'text-slate-600');
    const changePrefix = isPositive ? '+' : '';

    return `
      <tr class="hover:bg-slate-50 border-b border-slate-100">
        <td class="p-3 text-slate-600 whitespace-nowrap">${App.formatDate(m.created_at)}</td>
        <td class="p-3 whitespace-nowrap">${typeBadge}</td>
        <td class="p-3 font-mono font-bold text-slate-800">${escapeHtml(m.reference_no || '-')}</td>
        <td class="p-3">
          <p class="font-bold text-slate-900">${escapeHtml(m.material_name || '-')}</p>
          <p class="text-[10px] text-slate-400 font-mono">${escapeHtml(m.material_code || '-')} • ${escapeHtml(m.rack_location || '-')}</p>
        </td>
        <td class="p-3 text-center ${changeClass}">${changePrefix}${App.formatNumber(m.qty_change)} ${escapeHtml(m.material_unit || 'Pcs')}</td>
        <td class="p-3 text-center font-bold text-slate-900">${App.formatNumber(m.stock_after)} ${escapeHtml(m.material_unit || 'Pcs')}</td>
        <td class="p-3 text-slate-500 max-w-xs truncate" title="${escapeHtml(m.notes || '')}">
          ${escapeHtml(m.notes || '-')}
          ${m.user_name ? `<span class="block text-[10px] text-slate-400">Oleh: ${escapeHtml(m.user_name)}</span>` : ''}
        </td>
      </tr>
    `;
  }).join('');
}

function exportMutationsExcel() {
  const search = (document.getElementById('mutationSearchInput')?.value || '').trim();
  const type   = document.getElementById('mutationTypeFilter')?.value || 'ALL';
  const date   = (document.getElementById('mutationDateFilter')?.value || '').trim();
  let url = `export.php?type=mutations&mutation_type=${encodeURIComponent(type)}`;
  if (search) url += `&search=${encodeURIComponent(search)}`;
  if (date) url += `&date=${encodeURIComponent(date)}`;
  window.location.href = url;
}

// ================= 12. HAK AKSES MENU & ROLE (PERMISSIONS MODULE) =================
let permCatalog = [];
let permRoles = [];
let permRoleData = {};
let permUserData = [];
let permCurrentMode = 'role'; // 'role' or 'user'

async function applyMyPermissions() {
  const res = await App.fetchJson('../api/permissions.php?action=get_my_permissions');
  if (res.success && res.permissions) {
    const p = res.permissions;
    // Map menu keys to sidebar nav IDs
    const menuNavMap = {
      dashboard: 'nav-dashboard',
      inventory: 'nav-inventory',
      dynamic_count: 'nav-dynamic_count',
      opname: 'nav-opname',
      counting_detail: 'nav-counting_detail',
      adjust: 'nav-adjust',
      inbound: 'nav-inbound',
      outbound: 'nav-outbound',
      tasks: 'nav-tasks',
      mutations: 'nav-mutations',
      users: 'nav-users',
      permissions: 'nav-permissions',
      maintenance: 'nav-maintenance',
      field_access: 'sidebarFieldAccessContainer'
    };

    Object.keys(menuNavMap).forEach(key => {
      const el = document.getElementById(menuNavMap[key]);
      if (el) {
        const permKey = key === 'counting_detail' ? 'opname' : key;
        if (p[permKey] === false || ((permKey === 'permissions' || permKey === 'maintenance') && !res.is_super_admin)) {
          el.classList.add('hidden');
        } else {
          el.classList.remove('hidden');
        }
      }
    });

    // Hide empty sections automatically
    document.querySelectorAll('.sidebar-section').forEach(section => {
      const buttons = section.querySelectorAll('.sidebar-nav-btn');
      const visibleButtons = Array.from(buttons).filter(btn => !btn.classList.contains('hidden'));
      if (visibleButtons.length === 0) {
        section.classList.add('hidden');
      } else {
        section.classList.remove('hidden');
      }
    });
  }
}

async function loadPermissionsModule() {
  const res = await App.fetchJson('../api/permissions.php?action=get_all');
  if (res.success) {
    permCatalog = res.catalog || [];
    permRoles = res.roles || [];
    permRoleData = res.role_permissions || {};
    permUserData = res.users || [];

    // Populate user selector dropdown
    const userSel = document.getElementById('permUserSelector');
    if (userSel) {
      userSel.innerHTML = permUserData.map(u => `
        <option value="${u.id}">
          👤 ${escapeHtml(u.name)} (@${escapeHtml(u.username)}) - Role: ${u.role}${u.is_super_admin ? ' [SUPER ADMIN]' : ''}${u.has_custom_override ? ' ⭐' : ''}
        </option>
      `).join('');
    }

    loadPermissionMatrix();
  }
}

function setPermissionMode(mode) {
  permCurrentMode = mode;
  const btnRole = document.getElementById('btnPermModeRole');
  const btnUser = document.getElementById('btnPermModeUser');
  const selRole = document.getElementById('permRoleSelector');
  const selUser = document.getElementById('permUserSelector');

  if (mode === 'role') {
    btnRole.className = 'px-3 py-1 rounded-md bg-white text-emerald-800 shadow-2xs font-bold transition-all';
    btnUser.className = 'px-3 py-1 rounded-md text-slate-600 hover:text-slate-900 transition-all font-semibold';
    selRole.classList.remove('hidden');
    selUser.classList.add('hidden');
  } else {
    btnUser.className = 'px-3 py-1 rounded-md bg-white text-emerald-800 shadow-2xs font-bold transition-all';
    btnRole.className = 'px-3 py-1 rounded-md text-slate-600 hover:text-slate-900 transition-all font-semibold';
    selUser.classList.remove('hidden');
    selRole.classList.add('hidden');
  }

  loadPermissionMatrix();
}

function loadPermissionMatrix() {
  const grid = document.getElementById('permissionsGrid');
  const titleEl = document.getElementById('permTargetTitle');
  const subEl = document.getElementById('permTargetSubtitle');
  const btnReset = document.getElementById('btnResetPerm');

  if (!grid) return;

  let activePerms = {};
  let isSuperAdmin = false;

  if (permCurrentMode === 'role') {
    const selectedRole = document.getElementById('permRoleSelector')?.value || 'admin';
    activePerms = permRoleData[selectedRole] || {};
    titleEl.innerText = `Matriks Hak Akses: Role ${selectedRole.toUpperCase()}`;
    subEl.innerText = `Aturan hak akses ini akan berlaku secara default untuk seluruh akun dengan Role ${selectedRole}.`;
    if (btnReset) btnReset.classList.add('hidden');
  } else {
    const selectedUserId = parseInt(document.getElementById('permUserSelector')?.value || '0');
    const userObj = permUserData.find(u => u.id === selectedUserId) || permUserData[0];
    if (userObj) {
      activePerms = userObj.permissions || {};
      isSuperAdmin = userObj.is_super_admin;
      titleEl.innerText = `Hak Akses Khusus: ${userObj.name} (@${userObj.username})`;
      subEl.innerText = isSuperAdmin
        ? 'Teknisi Utama memiliki hak akses 100% penuh permanen ke seluruh menu sistem.'
        : `Role dasar: ${userObj.role.toUpperCase()} ${userObj.has_custom_override ? '(Memiliki Pengaturan Khusus)' : '(Menggunakan Standar Role)'}`;
      
      if (btnReset) {
        if (!isSuperAdmin && userObj.has_custom_override) {
          btnReset.classList.remove('hidden');
        } else {
          btnReset.classList.add('hidden');
        }
      }
    }
  }

  grid.innerHTML = permCatalog.map(m => {
    const isAllowed = activePerms[m.key] !== false; // Default true if not explicitly false
    const isDisabled = isSuperAdmin ? 'disabled' : '';

    return `
      <div class="p-4 rounded-xl border ${isAllowed ? 'border-emerald-200 bg-emerald-50/20' : 'border-slate-200 bg-slate-50/40'} transition-all flex flex-col justify-between space-y-3">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-lg ${isAllowed ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-400'} flex items-center justify-center flex-shrink-0">
              <span class="material-symbols-outlined text-[20px]">${m.icon}</span>
            </div>
            <div>
              <h5 class="font-bold text-xs text-slate-900">${escapeHtml(m.label)}</h5>
              <span class="font-mono text-[10px] text-slate-400">key: ${m.key}</span>
            </div>
          </div>

          <!-- Switch Toggle -->
          <label class="relative inline-flex items-center cursor-pointer ${isDisabled ? 'opacity-60 cursor-not-allowed' : ''}">
            <input type="checkbox" data-menu-key="${m.key}" class="sr-only peer perm-checkbox" ${isAllowed ? 'checked' : ''} ${isDisabled} onchange="updatePermCardStyle(this)">
            <div class="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
          </label>
        </div>

        <p class="text-[11px] text-slate-500 line-clamp-2">${escapeHtml(m.description)}</p>
      </div>
    `;
  }).join('');
}

function updatePermCardStyle(checkbox) {
  const card = checkbox.closest('.p-4');
  if (!card) return;
  if (checkbox.checked) {
    card.className = 'p-4 rounded-xl border border-emerald-200 bg-emerald-50/20 transition-all flex flex-col justify-between space-y-3';
    card.querySelector('.w-9.h-9').className = 'w-9 h-9 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center flex-shrink-0';
  } else {
    card.className = 'p-4 rounded-xl border border-slate-200 bg-slate-50/40 transition-all flex flex-col justify-between space-y-3';
    card.querySelector('.w-9.h-9').className = 'w-9 h-9 rounded-lg bg-slate-100 text-slate-400 flex items-center justify-center flex-shrink-0';
  }
}

function toggleAllPermissions(status) {
  const checkboxes = document.querySelectorAll('.perm-checkbox');
  checkboxes.forEach(cb => {
    if (!cb.disabled) {
      cb.checked = status;
      updatePermCardStyle(cb);
    }
  });
}

async function savePermissions() {
  const checkboxes = document.querySelectorAll('.perm-checkbox');
  const perms = {};
  checkboxes.forEach(cb => {
    const key = cb.getAttribute('data-menu-key');
    if (key) perms[key] = cb.checked;
  });

  if (permCurrentMode === 'role') {
    const role = document.getElementById('permRoleSelector')?.value || 'admin';
    const res = await App.fetchJson('../api/permissions.php?action=save_role', {
      method: 'POST',
      body: JSON.stringify({ role, permissions: perms })
    });
    if (res.success) {
      App.toast(res.message, 'success', 'Hak Akses Disimpan');
      loadPermissionsModule();
      applyMyPermissions();
    } else {
      App.toast(res.message || 'Gagal menyimpan', 'error');
    }
  } else {
    const user_id = parseInt(document.getElementById('permUserSelector')?.value || '0');
    const res = await App.fetchJson('../api/permissions.php?action=save_user', {
      method: 'POST',
      body: JSON.stringify({ user_id, permissions: perms })
    });
    if (res.success) {
      App.toast(res.message, 'success', 'Hak Akses Disimpan');
      loadPermissionsModule();
      applyMyPermissions();
    } else {
      App.toast(res.message || 'Gagal menyimpan', 'error');
    }
  }
}

async function resetUserPermission() {
  const user_id = parseInt(document.getElementById('permUserSelector')?.value || '0');

  const res = await App.fetchJson('../api/permissions.php?action=reset_user', {
    method: 'POST',
    body: JSON.stringify({ user_id })
  });

  if (res.success) {
    App.toast(res.message, 'info');
    loadPermissionsModule();
    applyMyPermissions();
  } else {
    App.toast(res.message || 'Gagal reset', 'error');
  }
}

// ================= 13. USER PROFILE & PASSWORD UPDATE =================
function openProfileModal() {
  const curPass = document.getElementById('profileCurrentPassword');
  const newPass = document.getElementById('profileNewPassword');
  const confPass = document.getElementById('profileConfirmPassword');
  if (curPass) curPass.value = '';
  if (newPass) newPass.value = '';
  if (confPass) confPass.value = '';
  App.openModal('modalProfile');
}

async function handleProfileSubmit(e) {
  e.preventDefault();
  const name = document.getElementById('profileName').value.trim();
  const current_password = document.getElementById('profileCurrentPassword').value.trim();
  const new_password = document.getElementById('profileNewPassword').value.trim();
  const confirm_password = document.getElementById('profileConfirmPassword').value.trim();

  if (new_password && new_password.length < 5) {
    App.toast('Password baru minimal 5 karakter!', 'warning');
    return;
  }

  if (new_password && new_password !== confirm_password) {
    App.toast('Konfirmasi password baru tidak cocok!', 'warning');
    return;
  }

  const btn = document.getElementById('btnProfileSubmit');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Menyimpan...</span>';

  const res = await App.fetchJson('../api/auth.php?action=update_profile', {
    method: 'POST',
    body: JSON.stringify({ name, current_password, new_password, confirm_password })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">save</span><span>Simpan Perubahan</span>';

  if (res.success) {
    App.toast(res.message, 'success', 'Profil Diperbarui');
    App.closeModal('modalProfile');
  } else {
    App.toast(res.message || 'Gagal memperbarui profil', 'error');
  }
}

// Utility HTML escape
function escapeHtml(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

// =========================================================================
// 14. DYNAMIC COUNTING & STOCK OPNAME PRODUCT MATRIX MODULES
// =========================================================================
function getStageLabel(stageNum) {
  if (stageNum === 1) return '1st Count';
  if (stageNum === 2) return '2nd Count';
  if (stageNum === 3) return '3rd Count';
  if (stageNum === 4) return '4th Count';
  return `${stageNum}th Count`;
}

function formatMatrixDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr;
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' +
         d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function getNoteBadge(diffNote, diffVal) {
  if (diffNote === 'PLUS') {
    return `<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-800 border border-blue-300 inline-flex items-center gap-1">
      <span class="material-symbols-outlined text-[12px]">add</span>Plus (+${App.formatNumber(diffVal)})
    </span>`;
  } else if (diffNote === 'MINUS') {
    return `<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 border border-rose-300 inline-flex items-center gap-1">
      <span class="material-symbols-outlined text-[12px]">remove</span>Minus (${App.formatNumber(diffVal)})
    </span>`;
  } else if (diffNote === 'BALANCE') {
    return `<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-300 inline-flex items-center gap-1">
      <span class="material-symbols-outlined text-[12px]">check_circle</span>Balance (0)
    </span>`;
  } else {
    return `<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500 border border-slate-200">Pending</span>`;
  }
}

// Global state for selected recount items (Difference <> 0)
let selectedDynamicRecountIds = new Set();
let selectedOpnameRecountIds = new Set();

function toggleSelectAllDiffItems(checked, isDynamic = false) {
  const items = isDynamic ? currentDynamicItems : currentOpnameItems;
  const targetSet = isDynamic ? selectedDynamicRecountIds : selectedOpnameRecountIds;

  targetSet.clear();
  if (checked) {
    items.forEach(it => {
      if (it.final_difference !== null && it.final_difference != 0) {
        targetSet.add(it.id);
      }
    });
  }

  // Update table row checkboxes
  const cbClass = isDynamic ? '.dynamic-diff-cb' : '.opname-diff-cb';
  document.querySelectorAll(cbClass).forEach(cb => {
    cb.checked = checked;
  });

  updateRecountSelectionUI(isDynamic);
}

function toggleItemRecountCheck(itemId, checked, isDynamic = false) {
  const targetSet = isDynamic ? selectedDynamicRecountIds : selectedOpnameRecountIds;
  if (checked) {
    targetSet.add(itemId);
  } else {
    targetSet.delete(itemId);
  }

  // Update header checkbox state
  const items = isDynamic ? currentDynamicItems : currentOpnameItems;
  const diffItems = items.filter(it => it.final_difference !== null && it.final_difference != 0);
  const headerCbId = isDynamic ? 'checkAllDynamicDiff' : 'checkAllOpnameDiff';
  const headerCb = document.getElementById(headerCbId);
  if (headerCb) {
    headerCb.checked = diffItems.length > 0 && targetSet.size === diffItems.length;
    headerCb.indeterminate = targetSet.size > 0 && targetSet.size < diffItems.length;
  }

  updateRecountSelectionUI(isDynamic);
}

function updateRecountSelectionUI(isDynamic = false) {
  const targetSet = isDynamic ? selectedDynamicRecountIds : selectedOpnameRecountIds;
  const badgeId = isDynamic ? 'dynamicSelectedBadge' : 'opnameSelectedBadge';
  const btnTextId = isDynamic ? 'btnAssignDynamicRecountText' : 'btnAssignRecountLabel';
  const maxStage = isDynamic ? currentDynamicMaxStage : currentMaxStage;
  const nextStageNum = maxStage + 1;
  const nextStageLabel = nextStageNum === 2 ? '2nd Count' : `${nextStageNum}th Count`;

  const badge = document.getElementById(badgeId);
  const btnText = document.getElementById(btnTextId);

  if (targetSet.size > 0) {
    if (badge) {
      badge.classList.remove('hidden');
      badge.innerText = `${targetSet.size} SKU Selisih Dipilih`;
    }
    if (btnText) {
      btnText.innerText = `Tugaskan Recount (${targetSet.size} SKU Terpilih)`;
    }
  } else {
    if (badge) {
      badge.classList.add('hidden');
    }
    if (btnText) {
      btnText.innerText = `Tugaskan Recount (${nextStageLabel})`;
    }
  }
}

function buildMatrixHeaderHtml(maxStage, isDynamic = false) {
  let thStages = '';
  for (let s = 1; s <= maxStage; s++) {
    const label = s === 1 ? '1st Count' : (s === 2 ? '2nd Count' : (s === 3 ? '3rd Count' : `${s}th Count`));
    const bg = s === 1 ? 'bg-white/10 text-white font-bold' : 'bg-emerald-950/60 text-emerald-200 font-bold';
    thStages += `<th class="p-2.5 text-center ${bg} border-x border-white/20">${label}</th>`;
  }

  const headerCbId = isDynamic ? 'checkAllDynamicDiff' : 'checkAllOpnameDiff';

  return `
    <tr class="text-white text-[11px] font-extrabold uppercase tracking-wider">
      <th class="p-2.5 w-10 text-center">
        <input type="checkbox" id="${headerCbId}" onchange="toggleSelectAllDiffItems(this.checked, ${isDynamic})" title="Pilih Semua yang Selisih (Difference != 0)" class="w-4 h-4 rounded text-purple-500 focus:ring-purple-400 cursor-pointer border-white/40">
      </th>
      <th class="p-2.5 text-center w-12">No</th>
      <th class="p-2.5">Tanggal</th>
      <th class="p-2.5">Item No</th>
      <th class="p-2.5">Deskripsi Product</th>
      <th class="p-2.5 text-center">Satuan</th>
      ${thStages}
      <th class="p-2.5 text-center font-black text-amber-200 bg-white/15">Qty Final Count</th>
      <th class="p-2.5 text-center">Qty System</th>
      <th class="p-2.5 text-center">Difference (+/-)</th>
      <th class="p-2.5 text-center">Note</th>
      <th class="p-2.5 text-right">Aksi</th>
    </tr>
  `;
}

function buildMatrixRowHtml(item, idx, maxStage, isDynamic = false) {
  const sysStock = parseFloat(item.system_stock) || 0;
  const finalQty = item.final_physical_qty !== null ? parseFloat(item.final_physical_qty) : null;
  const diff = item.final_difference !== null ? parseFloat(item.final_difference) : null;
  const activeSource = item.active_source_stage;
  const stages = item.stages || {};
  const formattedDate = formatMatrixDate(item.counted_at || item.created_at);

  const isDiscrepancy = (diff !== null && diff !== 0);
  const targetSet = isDynamic ? selectedDynamicRecountIds : selectedOpnameRecountIds;
  const isChecked = targetSet.has(item.id);

  let checkboxCellHtml = '';
  if (isDiscrepancy) {
    const cbClass = isDynamic ? 'dynamic-diff-cb' : 'opname-diff-cb';
    checkboxCellHtml = `
      <td class="p-3 text-center">
        <input type="checkbox" value="${item.id}" ${isChecked ? 'checked' : ''} 
          onchange="toggleItemRecountCheck(${item.id}, this.checked, ${isDynamic})" 
          class="${cbClass} w-4 h-4 rounded text-purple-600 focus:ring-purple-500 cursor-pointer border-slate-300 shadow-2xs" 
          title="Centang untuk ditugaskan Recount">
      </td>
    `;
  } else if (diff === 0) {
    checkboxCellHtml = `
      <td class="p-3 text-center">
        <span class="material-symbols-outlined text-[18px] text-emerald-500" title="Stok Sesuai (Balance 0)">check_circle</span>
      </td>
    `;
  } else {
    checkboxCellHtml = `
      <td class="p-3 text-center text-slate-300 font-mono">-</td>
    `;
  }

  // Stage cells — SUMMARY ONLY (just Qty number, no PIC/Rak detail)
  let stageCellsHtml = '';
  for (let s = 1; s <= maxStage; s++) {
    const st = stages[s];
    if (st && st.count_qty !== null) {
      const isSourced = activeSource === s;
      stageCellsHtml += `
        <td class="p-3 text-center ${s === 1 ? 'bg-blue-50/30' : 'bg-purple-50/30'} border-x border-slate-100 whitespace-nowrap">
          <div class="inline-flex items-center gap-1">
            <span class="font-mono font-black ${s === 1 ? 'text-blue-900' : 'text-purple-900'} text-xs">${App.formatNumber(st.count_qty)}</span>
            ${isSourced ? '<span class="px-1 py-0.2 rounded bg-emerald-100 text-emerald-900 font-extrabold text-[9px] border border-emerald-300" title="Digunakan sebagai Qty Final">Final</span>' : ''}
          </div>
        </td>
      `;
    } else if (st) {
      stageCellsHtml += `
        <td class="p-3 text-center ${s === 1 ? 'bg-blue-50/20' : 'bg-purple-50/20'} border-x border-slate-100 whitespace-nowrap">
          <span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 text-[10px] font-bold inline-flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
            Menunggu
          </span>
        </td>
      `;
    } else {
      stageCellsHtml += `
        <td class="p-3 text-center text-slate-300 font-mono border-x border-slate-100 whitespace-nowrap">-</td>
      `;
    }
  }

  let diffFormatted = '<span class="text-slate-400 font-mono">-</span>';
  if (diff !== null) {
    if (diff > 0) {
      diffFormatted = `<span class="px-2 py-0.5 rounded font-black font-mono text-blue-800 bg-blue-100 border border-blue-300">+${App.formatNumber(diff)}</span>`;
    } else if (diff < 0) {
      diffFormatted = `<span class="px-2 py-0.5 rounded font-black font-mono text-rose-800 bg-rose-100 border border-rose-300">${App.formatNumber(diff)}</span>`;
    } else {
      diffFormatted = '<span class="px-2 py-0.5 rounded font-bold font-mono text-slate-700 bg-slate-100 border border-slate-200">0</span>';
    }
  }

  const noteBadge = getNoteBadge(item.diff_note, diff);

  // Determine opnameId for detail view
  const opnameId = item.opname_id || (isDynamic ? (currentDynamicSession?.id || 0) : (currentOpnameSession?.id || 0));

  return `
    <tr class="hover:bg-slate-50 border-b border-slate-100 text-xs transition-colors ${isChecked ? 'bg-purple-50/40' : ''}">
      ${checkboxCellHtml}
      <td class="p-3 font-mono text-slate-400 text-center whitespace-nowrap">${idx + 1}</td>
      
      <td class="p-3 whitespace-nowrap">
        <div class="font-mono text-[11px] text-slate-700 font-medium">${formattedDate}</div>
      </td>

      <td class="p-3 whitespace-nowrap">
        <button type="button" onclick="navigateToCountingDetail(${opnameId}, '${escapeHtml(item.material_code || '')}')" 
          class="font-mono font-bold text-xs ${isDynamic ? 'text-indigo-700 hover:text-indigo-900' : 'text-emerald-800 hover:text-emerald-950'} hover:underline cursor-pointer bg-transparent border-none p-0 transition-colors" 
          title="Buka Halaman Detail Stock Opname untuk Item ini">
          ${escapeHtml(item.material_code || '-')}
        </button>
      </td>

      <td class="p-3 whitespace-nowrap">
        <span class="font-bold text-slate-900">${escapeHtml(item.material_name || '-')}</span>
      </td>

      <td class="p-3 text-center whitespace-nowrap">
        <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-700 font-bold text-[11px]">
          ${escapeHtml(item.material_unit || 'Pcs')}
        </span>
      </td>

      ${stageCellsHtml}

      <td class="p-3 text-center font-mono font-black text-slate-900 bg-slate-100/50 text-xs whitespace-nowrap">
        ${finalQty !== null ? `
          <div class="text-sm text-slate-900 font-extrabold">${App.formatNumber(finalQty)}</div>
          ${activeSource ? `<span class="text-[9px] text-slate-400 font-sans font-medium">(dari ${getStageLabel(activeSource).split(' ')[0]})</span>` : ''}
        ` : '<span class="text-slate-400 font-normal">-</span>'}
      </td>

      <td class="p-3 text-center font-mono font-bold text-slate-700 whitespace-nowrap">
        ${App.formatNumber(sysStock)}
      </td>

      <td class="p-3 text-center whitespace-nowrap">
        ${diffFormatted}
      </td>

      <td class="p-3 whitespace-nowrap text-center">
        ${noteBadge}
      </td>

      <td class="p-3 text-right whitespace-nowrap">
        <div class="inline-flex items-center justify-end gap-1">
          <button type="button" onclick="openEditOpnameItemModal(${item.id}, ${isDynamic})" title="Edit Nilai Fisik Final" 
            class="p-1.5 rounded-lg ${isDynamic ? 'bg-indigo-50 hover:bg-indigo-600 text-indigo-700' : 'bg-emerald-50 hover:bg-emerald-600 text-emerald-800'} hover:text-white border border-slate-200 transition-colors inline-flex items-center justify-center shadow-2xs">
            <span class="material-symbols-outlined text-[15px]">edit</span>
          </button>

          <button type="button" onclick="deleteOpnameItem(${item.id}, ${isDynamic})" title="Hapus Item ini dari Sesi" 
            class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-600 hover:text-white text-rose-700 border border-rose-200 transition-colors inline-flex items-center justify-center shadow-2xs">
            <span class="material-symbols-outlined text-[15px]">delete</span>
          </button>
        </div>
      </td>
    </tr>
  `;
}

function formatSessionOptionLabel(s) {
  let fullId = '';
  
  if (s.opname_no) {
    const parts = s.opname_no.split('-');
    if (parts.length >= 4 && parts[2].startsWith('W')) {
      fullId = `${parts[1]}-${parts[2]}-${parts[3]}`;
    } else if (parts.length >= 3 && parts[2].startsWith('W')) {
      fullId = `${parts[1]}-${parts[2]}-${String(s.id).padStart(2, '0')}`;
    } else {
      fullId = s.opname_no.replace(/^(OPN|DYN)-/, '');
    }
  }

  if (!fullId) {
    let dt = s.created_at ? new Date(s.created_at.replace(/-/g, '/')) : new Date();
    if (isNaN(dt.getTime())) dt = new Date();
    const ymd = `${dt.getFullYear()}${String(dt.getMonth() + 1).padStart(2, '0')}${String(dt.getDate()).padStart(2, '0')}`;
    const firstJan = new Date(dt.getFullYear(), 0, 1);
    const days = Math.floor((dt - firstJan) / (24 * 60 * 60 * 1000));
    const weekNum = Math.ceil((days + firstJan.getDay() + 1) / 7);
    fullId = `${ymd}-W${weekNum}-${String(s.id).padStart(2, '0')}`;
  }

  return fullId;
}

// -------------------------------------------------------------------------
// 14.A. DYNAMIC COUNTING MATRIX MODULE
// -------------------------------------------------------------------------
let currentDynamicSession = null;
let currentDynamicItems = [];
let currentDynamicMaxStage = 1;
let selectedDynamicSkuIds = new Set();
let _recountSpecificItemId = null;

async function loadDynamicMatrix() {
  const selectEl = document.getElementById('dynamicOpnameSelect');
  const opname_id = selectEl ? selectEl.value : '0';
  const date = document.getElementById('dynamicDateFilter')?.value || '';
  const note_filter = document.getElementById('dynamicNoteFilter')?.value || 'ALL';
  const search = document.getElementById('dynamicSearchInput')?.value || '';

  const query = new URLSearchParams({
    action: 'matrix',
    type: 'DYNAMIC_COUNT',
    opname_id,
    date,
    note_filter,
    search
  });

  const res = await App.fetchJson(`../api/opnames.php?${query.toString()}`);
  if (!res.success) {
    App.toast(res.message || 'Gagal memuat matriks Dynamic Count', 'error');
    return;
  }

  // Populate session dropdown if not already populated with same list
  if (selectEl && res.sessions) {
    const currentVal = selectEl.value;
    selectEl.innerHTML = '<option value="0">Semua Sesi</option>' +
      res.sessions.map(s => `
        <option value="${s.id}" ${res.selected_opname_id == s.id ? 'selected' : ''}>
          ${escapeHtml(formatSessionOptionLabel(s))}
        </option>
      `).join('');
    if (currentVal && currentVal !== '0') {
      selectEl.value = currentVal;
    }
  }

  currentDynamicSession = res.opname;
  currentDynamicItems = res.items || [];
  currentDynamicMaxStage = res.max_stage || 1;

  // Clear selections on reload
  selectedDynamicRecountIds.clear();
  updateRecountSelectionUI(true);

  // Update KPI Cards
  const stats = res.stats || {};
  const elTotal = document.getElementById('dynamicStatTotal');
  const elBalance = document.getElementById('dynamicStatBalance');
  const elPlus = document.getElementById('dynamicStatPlus');
  const elMinus = document.getElementById('dynamicStatMinus');

  if (elTotal) elTotal.innerText = `${App.formatNumber(stats.total_items || currentDynamicItems.length)} SKU`;
  if (elBalance) elBalance.innerText = `${App.formatNumber(stats.balance_items || 0)} SKU`;
  if (elPlus) elPlus.innerText = `${App.formatNumber(stats.plus_items || 0)} SKU`;
  if (elMinus) elMinus.innerText = `${App.formatNumber(stats.minus_items || 0)} SKU`;

  // Render Table Head & Body
  const thead = document.getElementById('dynamicItemsTableHead');
  const tbody = document.getElementById('dynamicItemsTableBody');
  if (thead) thead.innerHTML = buildMatrixHeaderHtml(currentDynamicMaxStage, true);

  if (tbody) {
    if (currentDynamicItems.length === 0) {
      const colCount = 11 + currentDynamicMaxStage;
      tbody.innerHTML = `
        <tr>
          <td colspan="${colCount}" class="p-8 text-center text-slate-400 text-xs font-medium">
            <span class="material-symbols-outlined text-[36px] text-slate-300 mb-1">checklist</span>
            <p>Belum ada data penghitungan Dynamic Counting yang sesuai kriteria filter.</p>
            <button onclick="openCreateDynamicCountModal()" class="mt-3 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold inline-flex items-center gap-1">
              <span class="material-symbols-outlined text-[16px]">add_circle</span>
              <span>Buat Dynamic Count Baru</span>
            </button>
          </td>
        </tr>
      `;
    } else {
      tbody.innerHTML = currentDynamicItems.map((item, idx) => buildMatrixRowHtml(item, idx, currentDynamicMaxStage, true)).join('');
    }
  }
}

// Alias for compatibility
function loadDynamicSessions() {
  loadDynamicMatrix();
}

function openCreateDynamicCountModal() {
  const form = document.getElementById('formCreateDynamicCount');
  if (form) form.reset();
  selectedDynamicSkuIds.clear();
  updateDynamicSkuSelectedBadge();

  const titleInput = document.getElementById('createDynamicTitle');
  if (titleInput) {
    const now = new Date();
    const ymd = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}`;
    const firstJan = new Date(now.getFullYear(), 0, 1);
    const days = Math.floor((now - firstJan) / (24 * 60 * 60 * 1000));
    const weekNum = Math.ceil((days + firstJan.getDay() + 1) / 7);
    titleInput.value = `${ymd}-W${weekNum}`;
  }

  const opSelect = document.getElementById('createDynamicOperator');
  if (opSelect) {
    opSelect.innerHTML = '<option value="">-- Pilih PIC --</option>' +
      allOperators.map(op => `
        <option value="${op.id}">${escapeHtml(op.name)} (${escapeHtml(op.shift || 'Shift Aktif')})</option>
      `).join('');
  }

  populateDynamicSkuChecklist();
  App.openModal('modalCreateDynamicCount');
}

function populateDynamicSkuChecklist() {
  const catFilter = document.getElementById('dynamicSkuCategoryFilter');
  if (catFilter) {
    const cats = [...new Set(allMaterials.map(m => m.category).filter(Boolean))];
    catFilter.innerHTML = '<option value="ALL">Semua Kategori</option>' +
      cats.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
  }
  filterDynamicSkuChecklist();
}

function filterDynamicSkuChecklist() {
  const search = (document.getElementById('dynamicSkuSearchInput')?.value || '').toLowerCase().trim();
  const cat = document.getElementById('dynamicSkuCategoryFilter')?.value || 'ALL';
  const container = document.getElementById('dynamicSkuChecklistContainer');
  if (!container) return;

  const filtered = allMaterials.filter(m => {
    const matchSearch = !search || 
      (m.code && m.code.toLowerCase().includes(search)) ||
      (m.name && m.name.toLowerCase().includes(search)) ||
      (m.rack_location && m.rack_location.toLowerCase().includes(search));

    const matchCat = cat === 'ALL' || m.category === cat;
    return matchSearch && matchCat;
  });

  if (filtered.length === 0) {
    container.innerHTML = '<p class="p-3 text-center text-slate-400 text-xs">Tidak ada SKU yang cocok</p>';
    return;
  }

  container.innerHTML = filtered.map(m => {
    const isChecked = selectedDynamicSkuIds.has(m.id);
    return `
      <label class="flex items-center justify-between p-2 rounded-lg hover:bg-indigo-50/50 cursor-pointer text-xs">
        <div class="flex items-center gap-2.5">
          <input type="checkbox" value="${m.id}" ${isChecked ? 'checked' : ''} 
            onchange="toggleDynamicSkuCheck(${m.id}, this.checked)"
            class="rounded text-indigo-600 focus:ring-indigo-500 dynamic-sku-cb">
          <div>
            <p class="font-bold text-slate-900">${escapeHtml(m.name)}</p>
            <div class="flex items-center gap-2 text-[10px] text-slate-500 mt-0.5">
              <span class="font-mono font-bold text-indigo-700">${escapeHtml(m.code)}</span>
              <span>&bull; Rak: ${escapeHtml(m.rack_location || '-')}</span>
              <span>&bull; UOM: ${escapeHtml(m.unit || 'Pcs')}</span>
            </div>
          </div>
        </div>
        <div class="text-right">
          <span class="font-mono font-bold text-slate-700">${App.formatNumber(m.current_stock)}</span>
          <span class="text-[10px] text-slate-400 block">Stok Sistem</span>
        </div>
      </label>
    `;
  }).join('');
}

function toggleDynamicSkuCheck(materialId, checked) {
  if (checked) selectedDynamicSkuIds.add(materialId);
  else selectedDynamicSkuIds.delete(materialId);
  updateDynamicSkuSelectedBadge();
}

function toggleSelectAllDynamicSku(selectBool) {
  const cbs = document.querySelectorAll('.dynamic-sku-cb');
  cbs.forEach(cb => {
    cb.checked = selectBool;
    const id = parseInt(cb.value);
    if (selectBool) selectedDynamicSkuIds.add(id);
    else selectedDynamicSkuIds.delete(id);
  });
  updateDynamicSkuSelectedBadge();
}

function updateDynamicSkuSelectedBadge() {
  const badge = document.getElementById('dynamicSkuSelectedCountBadge');
  if (badge) {
    badge.innerText = `${selectedDynamicSkuIds.size} SKU Terpilih`;
  }
}

async function handleCreateDynamicCountSubmit(e) {
  e.preventDefault();
  const title = document.getElementById('createDynamicTitle').value.trim();
  const assigned_to_operator_1 = document.getElementById('createDynamicOperator').value;
  const notes = document.getElementById('createDynamicNotes').value.trim();

  const selected_sku_ids = Array.from(selectedDynamicSkuIds);
  if (selected_sku_ids.length === 0) {
    App.toast('Pilih minimal 1 SKU material untuk sesi Dynamic Counting', 'warning');
    return;
  }

  const btn = document.getElementById('btnSubmitCreateDynamic');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Membuat & Mengirim Penugasan...</span>';

  const res = await App.fetchJson('../api/opnames.php?action=create', {
    method: 'POST',
    body: JSON.stringify({
      title,
      counting_type: 'DYNAMIC_COUNT',
      assigned_to_operator_1,
      notes,
      selected_sku_ids
    })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">send</span><span>Buat & Assign ke Operator</span>';

  if (res.success && (res.id || res.opname_id)) {
    App.toast(res.message, 'success', 'Sesi Berhasil Dibuat');
    App.closeModal('modalCreateDynamicCount');
    loadDynamicMatrix();
  } else {
    App.toast(res.message || 'Gagal membuat sesi Dynamic Count', 'error');
  }
}

function openAssignDynamicRecountModal() {
  if (!currentDynamicSession || !currentDynamicSession.id) {
    App.toast('Pilih sesi Dynamic Count terlebih dahulu', 'warning');
    return;
  }
  currentOpnameDetail = currentDynamicSession;
  currentMaxStage = currentDynamicMaxStage;
  currentOpnameItems = currentDynamicItems;
  openAssignRecountModal(null, true);
}

function exportDynamicExcel() {
  const opnameId = currentDynamicSession?.id;
  if (!opnameId) {
    App.toast('Pilih sesi Dynamic Count terlebih dahulu', 'warning');
    return;
  }
  window.open(`export.php?type=stock_opname&id=${opnameId}`, '_blank');
}

async function applyCurrentDynamicAdjustment() {
  const opnameId = currentDynamicSession?.id;
  if (!opnameId) {
    App.toast('Pilih sesi Dynamic Count terlebih dahulu', 'warning');
    return;
  }

  const res = await App.fetchJson('../api/opnames.php?action=apply_adjustment', {
    method: 'POST',
    body: JSON.stringify({ opname_id: opnameId })
  });

  if (res.success) {
    App.toast(res.message, 'success', 'Penyesuaian Berhasil');
    loadDynamicMatrix();
    loadMaterials();
    loadStats();
    loadMutations();
  } else {
    App.toast(res.message || 'Gagal menerapkan penyesuaian', 'error');
  }
}

// -------------------------------------------------------------------------
// 14.B. STOCK OPNAME MATRIX MODULE (BLANK COUNT & RECOUNT MATRIX)
// -------------------------------------------------------------------------
let currentOpnameSession = null;
let currentOpnameItems = [];
let currentMaxStage = 1;
let currentOpnameDetail = null;

async function loadOpnameMatrix() {
  const selectEl = document.getElementById('opnameSelectSession');
  const opname_id = selectEl ? selectEl.value : '0';
  const date = document.getElementById('opnameDateFilter')?.value || '';
  const note_filter = document.getElementById('opnameNoteFilter')?.value || 'ALL';
  const search = document.getElementById('opnameSearchInput')?.value || '';

  const query = new URLSearchParams({
    action: 'matrix',
    type: 'STOCK_OPNAME',
    opname_id,
    date,
    note_filter,
    search
  });

  const res = await App.fetchJson(`../api/opnames.php?${query.toString()}`);
  if (!res.success) {
    App.toast(res.message || 'Gagal memuat matriks Stock Opname', 'error');
    return;
  }

  // Populate session dropdown
  if (selectEl && res.sessions) {
    const currentVal = selectEl.value;
    selectEl.innerHTML = '<option value="0">Semua Sesi</option>' +
      res.sessions.map(s => `
        <option value="${s.id}" ${res.selected_opname_id == s.id ? 'selected' : ''}>
          ${escapeHtml(formatSessionOptionLabel(s))}
        </option>
      `).join('');
    if (currentVal && currentVal !== '0') {
      selectEl.value = currentVal;
    }
  }

  currentOpnameSession = res.opname;
  currentOpnameDetail = res.opname;
  currentOpnameItems = res.items || [];
  currentMaxStage = res.max_stage || 1;

  // Clear selections on reload
  selectedOpnameRecountIds.clear();
  updateRecountSelectionUI(false);

  // Update KPI Cards
  const stats = res.stats || {};
  const elTotal = document.getElementById('opnameStatTotal');
  const elBalance = document.getElementById('opnameStatBalance');
  const elPlus = document.getElementById('opnameStatPlus');
  const elMinus = document.getElementById('opnameStatMinus');

  if (elTotal) elTotal.innerText = `${App.formatNumber(stats.total_items || currentOpnameItems.length)} Product`;
  if (elBalance) elBalance.innerText = `${App.formatNumber(stats.balance_items || 0)} Product`;
  if (elPlus) elPlus.innerText = `${App.formatNumber(stats.plus_items || 0)} Product`;
  if (elMinus) elMinus.innerText = `${App.formatNumber(stats.minus_items || 0)} Product`;

  // Render Table Head & Body
  const thead = document.getElementById('opnameItemsTableHead');
  const tbody = document.getElementById('opnameItemsTableBody');
  if (thead) thead.innerHTML = buildMatrixHeaderHtml(currentMaxStage, false);

  if (tbody) {
    if (currentOpnameItems.length === 0) {
      const colCount = 11 + currentMaxStage;
      tbody.innerHTML = `
        <tr>
          <td colspan="${colCount}" class="p-8 text-center text-slate-400 text-xs font-medium">
            <span class="material-symbols-outlined text-[36px] text-slate-300 mb-1">inventory_2</span>
            <p>Belum ada data hasil Blank Count Stock Opname yang masuk.</p>
            <p class="text-[11px] text-slate-400 mt-1">Operator dapat langsung menginput hasil hitung fisik di halaman Operator Mobile App.</p>
          </td>
        </tr>
      `;
    } else {
      tbody.innerHTML = currentOpnameItems.map((item, idx) => buildMatrixRowHtml(item, idx, currentMaxStage, false)).join('');
    }
  }
}

// Alias for compatibility
function loadOpnames() {
  loadOpnameMatrix();
}

function openCreateStockOpnameModal() {
  const form = document.getElementById('formCreateStockOpname');
  if (form) form.reset();

  const titleInput = document.getElementById('createOpnameTitle');
  if (titleInput) {
    const now = new Date();
    const ymd = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}`;
    const firstJan = new Date(now.getFullYear(), 0, 1);
    const days = Math.floor((now - firstJan) / (24 * 60 * 60 * 1000));
    const weekNum = Math.ceil((days + firstJan.getDay() + 1) / 7);
    titleInput.value = `${ymd}-W${weekNum}`;
  }

  const catSelect = document.getElementById('createOpnameCategorySelect');
  if (catSelect) {
    const cats = [...new Set(allMaterials.map(m => m.category).filter(Boolean))];
    catSelect.innerHTML = cats.map(c => `<option value="${escapeHtml(c)}">${escapeHtml(c)}</option>`).join('');
  }

  toggleOpnameScopeFilter();
  App.openModal('modalCreateStockOpname');
}

function toggleOpnameScopeFilter() {
  const scope = document.getElementById('createOpnameScope')?.value || 'all';
  const catGroup = document.getElementById('opnameCategoryScopeGroup');
  const rackGroup = document.getElementById('opnameRackScopeGroup');

  if (catGroup) {
    if (scope === 'category') catGroup.classList.remove('hidden');
    else catGroup.classList.add('hidden');
  }

  if (rackGroup) {
    if (scope === 'rack') rackGroup.classList.remove('hidden');
    else rackGroup.classList.add('hidden');
  }
}

async function handleCreateStockOpnameSubmit(e) {
  e.preventDefault();
  const title = document.getElementById('createOpnameTitle').value.trim();
  const scope = document.getElementById('createOpnameScope').value;
  const category = document.getElementById('createOpnameCategorySelect')?.value || '';
  const rack = document.getElementById('createOpnameRackInput')?.value.trim() || '';
  const notes = document.getElementById('createOpnameNotes').value.trim();

  const btn = document.getElementById('btnSubmitCreateOpname');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Membuka Sesi...</span>';

  const res = await App.fetchJson('../api/opnames.php?action=create', {
    method: 'POST',
    body: JSON.stringify({
      title,
      counting_type: 'STOCK_OPNAME',
      scope,
      category,
      rack,
      notes
    })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">play_circle</span><span>Buka Sesi Stock Opname</span>';

  if (res.success && (res.id || res.opname_id)) {
    App.toast(res.message, 'success', 'Sesi Berhasil Dibuka');
    App.closeModal('modalCreateStockOpname');
    loadOpnameMatrix();
  } else {
    App.toast(res.message || 'Gagal membuat sesi Stock Opname', 'error');
  }
}

let _isRecountForDynamic = false;

function openAssignRecountModal(specificItemId = null, isDynamic = false) {
  _recountSpecificItemId = specificItemId;
  _isRecountForDynamic = isDynamic;

  const session = isDynamic ? currentDynamicSession : (currentOpnameDetail || currentOpnameSession);
  if (!session || !session.id) {
    App.toast('Pilih atau buat sesi penghitungan terlebih dahulu', 'warning');
    return;
  }

  const items = isDynamic ? currentDynamicItems : currentOpnameItems;
  const targetSet = isDynamic ? selectedDynamicRecountIds : selectedOpnameRecountIds;
  const maxStage = isDynamic ? currentDynamicMaxStage : currentMaxStage;
  const nextStage = maxStage + 1;
  const nextStageLabel = getStageLabel(nextStage);

  const stageBadge = document.getElementById('recountStageTargetBadge');
  if (stageBadge) stageBadge.innerText = nextStageLabel;

  const opSelect = document.getElementById('recountOperatorSelect');
  if (opSelect) {
    opSelect.innerHTML = '<option value="">-- Pilih Operator Recount --</option>' +
      allOperators.map(op => `
        <option value="${op.id}">${escapeHtml(op.name)} (${escapeHtml(op.shift || 'Shift Aktif')})</option>
      `).join('');
  }

  const subtitle = document.getElementById('recountOpnameSubtitle');
  if (subtitle) {
    subtitle.innerText = `Sesi #${session.opname_no} - Penugasan ${nextStageLabel}`;
  }

  // Resolve target items to recount
  let targetItems = [];
  if (specificItemId) {
    const single = items.find(i => i.id == specificItemId);
    if (single) targetItems = [single];
  } else if (targetSet.size > 0) {
    targetItems = items.filter(i => targetSet.has(i.id));
  } else {
    // If no manual selection, auto-select all discrepancy items
    targetItems = items.filter(i => i.final_difference !== null && i.final_difference != 0);
  }

  const listContainer = document.getElementById('recountItemsPreviewList');
  const countBadge = document.getElementById('recountItemsCountBadge');

  if (countBadge) {
    countBadge.innerText = `${targetItems.length} SKU Selisih`;
  }

  if (listContainer) {
    if (targetItems.length === 0) {
      listContainer.innerHTML = `
        <div class="p-4 text-center text-slate-400 text-xs">
          <span class="material-symbols-outlined text-[24px] text-emerald-500 mb-1">check_circle</span>
          <p class="font-bold text-slate-700">Semua item saat ini berstatus Balance (0)!</p>
          <p class="text-[11px] text-slate-400">Tidak ada item selisih stok (Difference != 0) yang perlu di-recount.</p>
        </div>
      `;
    } else {
      listContainer.innerHTML = targetItems.map(it => {
        const diff = parseFloat(it.final_difference) || 0;
        const diffBadge = diff > 0 
          ? `<span class="px-2 py-0.5 rounded font-black text-blue-800 bg-blue-100 border border-blue-300 text-[10px]">+${App.formatNumber(diff)}</span>`
          : `<span class="px-2 py-0.5 rounded font-black text-rose-800 bg-rose-100 border border-rose-300 text-[10px]">${App.formatNumber(diff)}</span>`;

        return `
          <div class="flex items-center justify-between p-2 hover:bg-white rounded-lg transition-colors text-xs">
            <div>
              <p class="font-bold text-slate-900">${escapeHtml(it.material_name)}</p>
              <div class="flex items-center gap-2 text-[10px] text-slate-500 mt-0.5">
                <span class="font-mono font-bold text-indigo-700">${escapeHtml(it.material_code)}</span>
                <span>&bull; Rak: ${escapeHtml(it.material_rack || it.rack_location || '-')}</span>
                <span>&bull; Fisik: <b>${App.formatNumber(it.final_physical_qty)}</b> vs Sistem: <b>${App.formatNumber(it.system_stock)}</b></span>
              </div>
            </div>
            <div class="text-right">
              ${diffBadge}
            </div>
          </div>
        `;
      }).join('');
    }
  }

  const btnSubmit = document.getElementById('btnSubmitRecount');
  if (btnSubmit) {
    btnSubmit.disabled = (targetItems.length === 0);
  }

  App.openModal('modalAssignRecount');
}

async function handleAssignRecountSubmit(e) {
  e.preventDefault();
  const isDynamic = _isRecountForDynamic;
  const session = isDynamic ? currentDynamicSession : (currentOpnameDetail || currentOpnameSession);
  const opname_id = session?.id;
  const operator_id = document.getElementById('recountOperatorSelect').value;
  const notes = document.getElementById('recountNotesInput')?.value.trim() || '';

  const items = isDynamic ? currentDynamicItems : currentOpnameItems;
  const targetSet = isDynamic ? selectedDynamicRecountIds : selectedOpnameRecountIds;

  let item_ids = [];
  if (_recountSpecificItemId) {
    item_ids = [_recountSpecificItemId];
  } else if (targetSet.size > 0) {
    item_ids = Array.from(targetSet);
  } else {
    // Select all discrepancy items
    item_ids = items.filter(i => i.final_difference !== null && i.final_difference != 0).map(i => i.id);
  }

  if (item_ids.length === 0) {
    App.toast('Tidak ada item dengan selisih stok yang dipilih untuk recount', 'warning');
    return;
  }

  const btn = document.getElementById('btnSubmitRecount');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Menugaskan...</span>';

  const res = await App.fetchJson('../api/opnames.php?action=assign_recount', {
    method: 'POST',
    body: JSON.stringify({
      opname_id,
      assigned_to_operator: operator_id,
      item_ids,
      notes
    })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">send</span><span>Kirim Tugas Recount</span>';

  if (res.success) {
    App.toast(res.message, 'success', 'Penugasan Recount Berhasil');
    App.closeModal('modalAssignRecount');
    if (isDynamic) {
      selectedDynamicRecountIds.clear();
      loadDynamicMatrix();
    } else {
      selectedOpnameRecountIds.clear();
      loadOpnameMatrix();
    }
  } else {
    App.toast(res.message || 'Gagal menugaskan recount', 'error');
  }
}

function openEditOpnameItemModal(itemId, isDynamic = false) {
  const items = isDynamic ? currentDynamicItems : currentOpnameItems;
  const item = items.find(i => i.id == itemId);
  if (!item) return;

  document.getElementById('editOpnameItemId').value = item.id;
  document.getElementById('editOpnameItemCode').innerText = item.material_code;
  document.getElementById('editOpnameItemName').innerText = item.material_name;
  document.getElementById('editOpnameItemRack').innerText = `Rak: ${item.material_rack || item.rack_location || '-'}`;
  document.getElementById('editOpnameUnitLabel').innerText = item.material_unit || 'Pcs';
  document.getElementById('editOpnameSysStock').innerText = App.formatNumber(item.system_stock);
  
  const count1 = item.stages && item.stages[1] ? item.stages[1].count_qty : null;
  const count2 = item.stages && item.stages[2] ? item.stages[2].count_qty : null;
  document.getElementById('editOpnameCount1').innerText = count1 !== null ? App.formatNumber(count1) : '-';
  document.getElementById('editOpnameCount2').innerText = count2 !== null ? App.formatNumber(count2) : '-';

  const finalInput = document.getElementById('editOpnameFinalQty');
  finalInput.value = item.final_physical_qty !== null ? item.final_physical_qty : item.system_stock;

  document.getElementById('editOpnameAdminNotes').value = item.admin_notes || '';
  App.openModal('modalEditOpnameItem');
}

async function handleEditOpnameItemSubmit(e) {
  e.preventDefault();
  const item_id = document.getElementById('editOpnameItemId').value;
  const final_physical_qty = document.getElementById('editOpnameFinalQty').value;
  const admin_notes = document.getElementById('editOpnameAdminNotes').value.trim();

  const res = await App.fetchJson('../api/opnames.php?action=update_item', {
    method: 'POST',
    body: JSON.stringify({ item_id, final_physical_qty, admin_notes })
  });

  if (res.success) {
    App.toast(res.message, 'success');
    App.closeModal('modalEditOpnameItem');
    loadDynamicMatrix();
    loadOpnameMatrix();
  } else {
    App.toast(res.message || 'Gagal menyimpan perubahan item', 'error');
  }
}

async function deleteOpnameItem(itemId, isDynamic = false) {
  const res = await App.fetchJson('../api/opnames.php?action=delete_item', {
    method: 'POST',
    body: JSON.stringify({ item_id: itemId })
  });

  if (res.success) {
    App.toast(res.message, 'success');
    if (isDynamic) loadDynamicMatrix();
    else loadOpnameMatrix();
  } else {
    App.toast(res.message || 'Gagal menghapus item', 'error');
  }
}

async function deleteOpnameSession(opnameId) {
  const res = await App.fetchJson('../api/opnames.php?action=delete_opname', {
    method: 'POST',
    body: JSON.stringify({ opname_id: opnameId })
  });

  if (res.success) {
    App.toast(res.message, 'success', 'Sesi Dihapus');
    loadDynamicMatrix();
    loadOpnameMatrix();
  } else {
    App.toast(res.message || 'Gagal menghapus sesi', 'error');
  }
}

function exportCurrentOpnameExcel() {
  const opnameId = currentOpnameSession?.id;
  if (!opnameId) {
    App.toast('Pilih sesi Stock Opname terlebih dahulu', 'warning');
    return;
  }
  window.open(`export.php?type=stock_opname&id=${opnameId}`, '_blank');
}

// =========================================================================
// DETAIL COUNT VIEW — Modal showing all stage details for a specific item
// =========================================================================
async function openCountDetailView(opnameId, itemId) {
  const modal = document.getElementById('modalCountDetail');
  if (!modal) return;

  // Show modal with loading state
  modal.classList.remove('hidden');
  modal.classList.add('flex');
  document.getElementById('countDetailContent').innerHTML = `
    <div class="flex flex-col items-center justify-center py-12 text-slate-400">
      <span class="material-symbols-outlined text-[40px] animate-spin mb-2">progress_activity</span>
      <p class="text-sm font-medium">Memuat data detail count...</p>
    </div>
  `;

  const res = await App.fetchJson(`../api/opnames.php?action=item_detail&opname_id=${opnameId}&item_id=${itemId}`);
  if (!res.success) {
    document.getElementById('countDetailContent').innerHTML = `
      <div class="flex flex-col items-center justify-center py-12 text-rose-500">
        <span class="material-symbols-outlined text-[40px] mb-2">error</span>
        <p class="text-sm font-medium">${escapeHtml(res.message || 'Gagal memuat data')}</p>
      </div>
    `;
    return;
  }

  const { opname, item, stages } = res;

  // Header info
  document.getElementById('countDetailTitle').innerHTML = `
    <span class="font-mono text-emerald-800">${escapeHtml(item.material_code)}</span>
    <span class="text-slate-400 mx-1">&bull;</span>
    <span class="text-slate-900">${escapeHtml(item.material_name)}</span>
  `;
  document.getElementById('countDetailSession').innerHTML = `
    Sesi: <span class="font-bold text-emerald-800">${escapeHtml(opname.opname_no)}</span>
    <span class="mx-1.5 text-slate-300">|</span>
    Lokasi Rak: <span class="font-bold text-slate-800">${escapeHtml(item.material_rack || '-')}</span>
    <span class="mx-1.5 text-slate-300">|</span>
    Satuan: <span class="font-bold text-slate-800">${escapeHtml(item.material_unit || 'Pcs')}</span>
  `;

  // Summary KPI bar
  const diffVal = item.difference;
  let diffBadge = '<span class="text-slate-400">-</span>';
  if (diffVal !== null) {
    if (diffVal > 0) {
      diffBadge = `<span class="px-2 py-0.5 rounded font-black font-mono text-blue-800 bg-blue-100 border border-blue-300">+${App.formatNumber(diffVal)}</span>`;
    } else if (diffVal < 0) {
      diffBadge = `<span class="px-2 py-0.5 rounded font-black font-mono text-rose-800 bg-rose-100 border border-rose-300">${App.formatNumber(diffVal)}</span>`;
    } else {
      diffBadge = `<span class="px-2.5 py-0.5 rounded-full font-black font-mono text-emerald-800 bg-emerald-100 border border-emerald-300 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">check_circle</span>Balance (0)</span>`;
    }
  }

  const summaryHtml = `
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
      <div class="bg-slate-50 rounded-xl p-3 border border-slate-200 text-center">
        <div class="text-[10px] font-bold uppercase text-slate-400 mb-0.5">Qty System</div>
        <div class="font-mono font-black text-lg text-slate-900">${App.formatNumber(item.system_stock)}</div>
      </div>
      <div class="bg-amber-50 rounded-xl p-3 border border-amber-200 text-center">
        <div class="text-[10px] font-bold uppercase text-amber-600 mb-0.5">Qty Final Count</div>
        <div class="font-mono font-black text-lg text-amber-900">${item.final_qty !== null ? App.formatNumber(item.final_qty) : '-'}</div>
        ${item.active_source_stage ? `<div class="text-[9px] text-amber-600 font-medium">(dari ${getStageLabel(item.active_source_stage).split(' ')[0]})</div>` : ''}
      </div>
      <div class="bg-white rounded-xl p-3 border border-slate-200 text-center">
        <div class="text-[10px] font-bold uppercase text-slate-400 mb-0.5">Difference</div>
        <div class="mt-0.5">${diffBadge}</div>
      </div>
      <div class="bg-white rounded-xl p-3 border border-slate-200 text-center">
        <div class="text-[10px] font-bold uppercase text-slate-400 mb-0.5">Total Rounds</div>
        <div class="font-mono font-black text-lg text-slate-900">${stages.length}</div>
      </div>
    </div>
  `;

  // Stage detail table
  let stageRowsHtml = '';
  if (stages.length === 0) {
    stageRowsHtml = `
      <tr>
        <td colspan="7" class="p-6 text-center text-slate-400 text-xs">
          <span class="material-symbols-outlined text-[28px] text-slate-300 block mb-1">inbox</span>
          Belum ada data hasil count untuk item ini.
        </td>
      </tr>
    `;
  } else {
    stageRowsHtml = stages.map(st => {
      const stageLabel = getStageLabel(st.stage_number);
      const isFinal = st.is_final_source;
      const statusBadge = st.status === 'COUNTED' 
        ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">COUNTED</span>'
        : st.status === 'PENDING'
        ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">PENDING</span>'
        : `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">${escapeHtml(st.status)}</span>`;

      const countedTime = st.counted_at ? App.formatDate(st.counted_at) : '-';

      return `
        <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100 ${isFinal ? 'bg-emerald-50/30' : ''}">
          <td class="p-3 font-bold text-xs whitespace-nowrap">
            <div class="inline-flex items-center gap-1.5">
              <span class="${st.stage_number === 1 ? 'text-blue-800' : 'text-purple-800'}">${escapeHtml(stageLabel)}</span>
              ${isFinal ? '<span class="px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-900 font-extrabold text-[9px] border border-emerald-300">FINAL</span>' : ''}
            </div>
          </td>
          <td class="p-3 text-center font-mono font-black text-slate-900 text-sm whitespace-nowrap">
            ${st.count_qty !== null ? App.formatNumber(st.count_qty) : '<span class="text-slate-400 font-normal">-</span>'}
          </td>
          <td class="p-3 whitespace-nowrap">
            <span class="font-semibold text-slate-800">${escapeHtml(st.scanned_rack || '-')}</span>
          </td>
          <td class="p-3 whitespace-nowrap">
            <div class="inline-flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px] text-slate-400">person</span>
              <span class="font-bold text-slate-800">${escapeHtml(st.operator_name || 'Operator')}</span>
            </div>
            ${st.operator_shift ? `<div class="text-[10px] text-slate-400">Shift: ${escapeHtml(st.operator_shift)}</div>` : ''}
          </td>
          <td class="p-3 font-mono text-[11px] text-slate-500 whitespace-nowrap">${countedTime}</td>
          <td class="p-3 text-center whitespace-nowrap">${statusBadge}</td>
          <td class="p-3 text-slate-600 text-[11px]">${escapeHtml(st.notes || '-')}</td>
        </tr>
      `;
    }).join('');
  }

  const tableHtml = `
    <div class="overflow-x-auto border border-slate-200 rounded-xl">
      <table class="w-full text-left border-collapse">
        <thead class="thead-emerald text-[11px] font-extrabold uppercase tracking-wider text-white">
          <tr>
            <th class="p-3 border-r border-white/20">Round</th>
            <th class="p-3 text-center border-r border-white/20">Qty Count</th>
            <th class="p-3 border-r border-white/20">Lokasi Rak Scan</th>
            <th class="p-3 border-r border-white/20">PIC Operator</th>
            <th class="p-3 border-r border-white/20">Waktu Count</th>
            <th class="p-3 text-center border-r border-white/20">Status</th>
            <th class="p-3">Catatan</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 text-xs">
          ${stageRowsHtml}
        </tbody>
      </table>
    </div>
  `;

  document.getElementById('countDetailContent').innerHTML = summaryHtml + tableHtml;
}

function closeCountDetailModal() {
  const modal = document.getElementById('modalCountDetail');
  if (modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }
}

// =========================================================================
// DETAIL COUNTING TAB CONTROLLER — Log Breakdown Data Table per Putaran
// =========================================================================
let currentCountingDetailRows = [];

function navigateToCountingDetail(opnameId, materialCode) {
  // 1. Switch to counting_detail tab
  switchAdminTab('counting_detail');

  // 2. Set Session & SKU filter values
  const sessionSelect = document.getElementById('cdFilterSession');
  if (sessionSelect) {
    sessionSelect.value = opnameId ? String(opnameId) : '0';
  }

  const searchInput = document.getElementById('cdSearchInput');
  if (searchInput) {
    searchInput.value = materialCode || '';
  }

  const stageSelect = document.getElementById('cdFilterStage');
  if (stageSelect) stageSelect.value = '0';

  const dateInput = document.getElementById('cdFilterDate');
  if (dateInput) dateInput.value = '';

  // 3. Load data with pre-filled filters
  loadCountingDetails();
}

async function loadCountingDetails() {
  const sessionSelect = document.getElementById('cdFilterSession');
  const opname_id = sessionSelect ? sessionSelect.value : '0';
  const stage_number = document.getElementById('cdFilterStage')?.value || '0';
  const date = document.getElementById('cdFilterDate')?.value || '';
  const search = document.getElementById('cdSearchInput')?.value || '';

  const tbody = document.getElementById('countingDetailTableBody');
  if (tbody) {
    tbody.innerHTML = `
      <tr>
        <td colspan="12" class="p-8 text-center text-slate-400">
          <span class="material-symbols-outlined text-[32px] animate-spin mb-1">progress_activity</span>
          <p class="text-xs font-medium">Memuat data log detail stock opname...</p>
        </td>
      </tr>
    `;
  }

  const query = new URLSearchParams({
    action: 'list_counting_details',
    opname_id,
    stage_number,
    date,
    search
  });

  const res = await App.fetchJson(`../api/opnames.php?${query.toString()}`);
  if (!res.success) {
    App.toast(res.message || 'Gagal memuat log detail stock opname', 'error');
    return;
  }

  // Populate Sessions Dropdown if needed
  if (sessionSelect && res.sessions) {
    const currentVal = sessionSelect.value;
    sessionSelect.innerHTML = '<option value="0">Semua Dokumen Sesi (Stock Opname)</option>' +
      res.sessions.map(s => `
        <option value="${s.id}" ${currentVal == s.id ? 'selected' : ''}>
          ${escapeHtml(s.opname_no)} - ${escapeHtml(s.title || '')}
        </option>
      `).join('');
    if (currentVal && currentVal !== '0') {
      sessionSelect.value = currentVal;
    }
  }

  const data = res.data || [];
  currentCountingDetailRows = data;
  const stats = res.stats || {};

  // Update KPI Cards
  const elRecords = document.getElementById('cdStatTotalRecords');
  const elQty = document.getElementById('cdStatTotalQty');
  const elSku = document.getElementById('cdStatTotalSku');
  const elSessions = document.getElementById('cdStatTotalSessions');

  if (elRecords) elRecords.innerText = `${App.formatNumber(stats.total_records || 0)} Data`;
  if (elQty) elQty.innerText = `${App.formatNumber(stats.total_qty || 0)} Pcs`;
  if (elSku) elSku.innerText = `${App.formatNumber(stats.total_unique_sku || 0)} SKU`;
  if (elSessions) elSessions.innerText = `${App.formatNumber(stats.total_sessions || 0)} Dokumen`;

  // Render Table
  if (tbody) {
    if (data.length === 0) {
      tbody.innerHTML = `
        <tr>
          <td colspan="12" class="p-8 text-center text-slate-400 text-xs font-medium">
            <span class="material-symbols-outlined text-[36px] text-slate-300 mb-1">table_rows</span>
            <p>Tidak ditemukan data log detail stock opname yang sesuai filter.</p>
          </td>
        </tr>
      `;
    } else {
      tbody.innerHTML = data.map((r, idx) => {
        const stageLabel = r.stage_label || `Round ${r.stage_number}`;
        const isFirst = r.stage_number === 1;
        const stageBadge = `<span class="px-2 py-0.5 rounded text-[10px] font-bold ${isFirst ? 'bg-blue-50 text-blue-800 border border-blue-200' : 'bg-purple-50 text-purple-800 border border-purple-200'}">${escapeHtml(stageLabel)}</span>`;
        
        const statusBadge = r.status === 'COUNTED'
          ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">COUNTED</span>'
          : r.status === 'PENDING'
          ? '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">PENDING</span>'
          : `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">${escapeHtml(r.status)}</span>`;

        const countedTime = r.counted_at ? App.formatDate(r.counted_at) : App.formatDate(r.created_at);

        return `
          <tr class="hover:bg-slate-50 transition-colors border-b border-slate-100 text-xs">
            <td class="p-3 font-mono text-slate-400 text-center whitespace-nowrap">${idx + 1}</td>
            <td class="p-3 font-mono font-bold text-emerald-800 whitespace-nowrap">
              ${escapeHtml(r.opname_no)}
            </td>
            <td class="p-3 font-mono text-[11px] text-slate-600 whitespace-nowrap">${countedTime}</td>
            <td class="p-3 text-center whitespace-nowrap">${stageBadge}</td>
            <td class="p-3 whitespace-nowrap">
              <button type="button" onclick="openCountDetailView(${r.opname_id}, ${r.item_id})" 
                class="font-mono font-bold text-emerald-800 hover:text-emerald-950 hover:underline cursor-pointer bg-transparent border-none p-0 transition-colors" 
                title="Lihat Rincian Item">
                ${escapeHtml(r.material_code || '-')}
              </button>
            </td>
            <td class="p-3 font-semibold text-slate-900 whitespace-nowrap">${escapeHtml(r.material_name || '-')}</td>
            <td class="p-3 text-center whitespace-nowrap">
              <span class="px-2 py-0.5 rounded bg-slate-100 border border-slate-200 text-slate-700 font-bold text-[11px]">
                ${escapeHtml(r.material_unit || 'Pcs')}
              </span>
            </td>
            <td class="p-3 text-center font-mono font-black text-slate-900 bg-slate-50/50 text-sm whitespace-nowrap">
              ${r.count_qty !== null ? App.formatNumber(r.count_qty) : '<span class="text-slate-400 font-normal">-</span>'}
            </td>
            <td class="p-3 whitespace-nowrap">
              <span class="font-bold text-slate-800">${escapeHtml(r.scanned_rack || r.material_rack || '-')}</span>
            </td>
            <td class="p-3 whitespace-nowrap">
              <div class="inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px] text-slate-400">person</span>
                <span class="font-bold text-slate-800">${escapeHtml(r.operator_name || r.operator_username || 'Operator')}</span>
              </div>
              ${r.operator_shift ? `<div class="text-[10px] text-slate-400">Shift: ${escapeHtml(r.operator_shift)}</div>` : ''}
            </td>
            <td class="p-3 text-center whitespace-nowrap">${statusBadge}</td>
            <td class="p-3 text-slate-600 text-[11px] max-w-[200px] truncate" title="${escapeHtml(r.notes || '')}">
              ${escapeHtml(r.notes || '-')}
            </td>
          </tr>
        `;
      }).join('');
    }
  }
}

function exportCountingDetailExcel() {
  const opname_id = document.getElementById('cdFilterSession')?.value || '0';
  const stage_number = document.getElementById('cdFilterStage')?.value || '0';
  const date = document.getElementById('cdFilterDate')?.value || '';
  const search = document.getElementById('cdSearchInput')?.value || '';

  const params = new URLSearchParams({
    type: 'counting_detail',
    opname_id,
    stage_number,
    date,
    search
  });

  window.open(`export.php?${params.toString()}`, '_blank');
}

async function applyCurrentOpnameAdjustment() {
  const opnameId = currentOpnameSession?.id;
  if (!opnameId) {
    App.toast('Pilih sesi Stock Opname terlebih dahulu', 'warning');
    return;
  }

  const btn = document.getElementById('btnApplySoAdjustment');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Menerapkan...</span>';
  }

  const res = await App.fetchJson('../api/opnames.php?action=apply_adjustment', {
    method: 'POST',
    body: JSON.stringify({ opname_id: opnameId })
  });

  if (btn) {
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">sync_alt</span><span>Terapkan ke Master Stok</span>';
  }

  if (res.success) {
    App.toast(res.message, 'success', 'Penyesuaian Berhasil');
    loadOpnameMatrix();
    loadMaterials();
    loadStats();
    loadMutations();
  } else {
    App.toast(res.message || 'Gagal menerapkan penyesuaian', 'error');
  }
}

// =========================================================================
// 15. DEDICATED DIRECT STOCK ADJUSTMENT (+ / -) MODULE
// =========================================================================
let directAdjustData = [];

async function loadDirectAdjustMaterials() {
  const tbody = document.getElementById('directAdjustTableBody');
  if (tbody) {
    tbody.innerHTML = `
      <tr>
        <td colspan="10" class="p-8 text-center text-slate-400">
          <span class="material-symbols-outlined text-[28px] animate-spin text-amber-600">progress_activity</span>
          <p class="text-xs font-semibold text-slate-600 mt-2">Memuat daftar master stok packaging...</p>
        </td>
      </tr>
    `;
  }

  try {
    const res = await App.fetchJson('../api/materials.php?action=list');
    if (res && res.success && Array.isArray(res.data)) {
      allMaterials = res.data;
    }
  } catch (e) {
    console.error('Error fetching materials for direct adjust:', e);
  }

  // Preserve any existing non-zero adjustments if already typed
  const prevMap = {};
  directAdjustData.forEach(item => {
    if (item.qty_adjust !== 0 || item.notes || item.is_imported) {
      prevMap[item.code] = {
        qty_adjust: item.qty_adjust,
        notes: item.notes,
        is_imported: item.is_imported
      };
    }
  });

  directAdjustData = (allMaterials || []).map(m => {
    const prev = prevMap[m.code] || {};
    return {
      id: m.id,
      code: m.code,
      name: m.name,
      unit: m.unit || 'Pcs',
      rack_location: m.rack_location || '-',
      current_stock: parseInt(m.current_stock || '0', 10),
      qty_adjust: prev.qty_adjust || 0,
      notes: prev.notes || '',
      is_imported: !!prev.is_imported
    };
  });

  renderDirectAdjustTable();
}

function handleDirectAdjustQtyChange(code, val) {
  const item = directAdjustData.find(d => d.code === code);
  if (!item) return;

  const parsed = (val === '' || val === '-' || val === '+') ? 0 : parseFloat(val);
  item.qty_adjust = isNaN(parsed) ? 0 : parsed;

  updateDirectAdjustRowUI(code);
  updateDirectAdjustCounters();
}

function handleDirectAdjustNotesChange(code, val) {
  const item = directAdjustData.find(d => d.code === code);
  if (item) {
    item.notes = val;
  }
}

function updateDirectAdjustRowUI(code) {
  const row = document.getElementById('adjust-row-' + code);
  const item = directAdjustData.find(d => d.code === code);
  if (!row || !item) return;

  const currentStock = item.current_stock;
  const qtyAdjust = item.qty_adjust || 0;
  const newStock = currentStock + qtyAdjust;

  const newStockEl = row.querySelector('.col-new-stock');
  const statusEl = row.querySelector('.col-status');

  if (newStockEl) {
    newStockEl.innerText = App.formatNumber(newStock);
    if (newStock < 0) {
      newStockEl.className = 'col-new-stock p-3 text-center font-mono font-black text-rose-600 bg-rose-50/50';
    } else if (qtyAdjust > 0) {
      newStockEl.className = 'col-new-stock p-3 text-center font-mono font-black text-emerald-700 bg-emerald-50/40';
    } else if (qtyAdjust < 0) {
      newStockEl.className = 'col-new-stock p-3 text-center font-mono font-black text-rose-700 bg-rose-50/40';
    } else {
      newStockEl.className = 'col-new-stock p-3 text-center font-mono font-black text-slate-800 bg-slate-100/50';
    }
  }

  if (statusEl) {
    if (qtyAdjust > 0) {
      statusEl.innerHTML = `<span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-bold text-[10px] border border-blue-200">+${App.formatNumber(qtyAdjust)}</span>`;
    } else if (qtyAdjust < 0) {
      statusEl.innerHTML = `<span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 font-bold text-[10px] border border-rose-200">${App.formatNumber(qtyAdjust)}</span>`;
    } else if (item.is_imported) {
      statusEl.innerHTML = '<span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 font-bold text-[10px] border border-amber-200">Import (0)</span>';
    } else {
      statusEl.innerHTML = '<span class="font-mono text-slate-400 text-[11px]">-</span>';
    }
  }

  if (qtyAdjust !== 0) {
    row.classList.add('bg-amber-50/30');
  } else if (!item.is_imported) {
    row.classList.remove('bg-amber-50/30');
  }
}

function updateDirectAdjustCounters() {
  const total = directAdjustData.length;
  const readyItems = directAdjustData.filter(d => d.qty_adjust !== 0);
  const readyCount = readyItems.length;
  const importedCount = directAdjustData.filter(d => d.is_imported).length;

  const totalEl = document.getElementById('statAdjustTotalSku');
  const readyEl = document.getElementById('statAdjustReadyCount');
  const commitBtn = document.getElementById('btnCommitDirectAdjust');
  const commitText = document.getElementById('btnCommitDirectAdjustText');

  if (totalEl) {
    if (importedCount > 0) {
      totalEl.innerHTML = `<span class="text-amber-800 font-bold">${importedCount} SKU Di-Import</span> <span class="text-slate-400 font-normal">/ ${total} Master</span>`;
    } else {
      totalEl.innerText = `${total} SKU Terdaftar`;
    }
  }
  if (readyEl) readyEl.innerText = `${readyCount} Siap Adjust`;

  if (commitBtn) {
    if (readyCount > 0) {
      commitBtn.disabled = false;
      commitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
      if (commitText) commitText.innerText = `Terapkan Adjust (${readyCount} SKU)`;
    } else {
      commitBtn.disabled = true;
      commitBtn.classList.add('opacity-50', 'cursor-not-allowed');
      if (commitText) commitText.innerText = 'Terapkan Adjust Stok';
    }
  }
}

function renderDirectAdjustTable() {
  const tbody = document.getElementById('directAdjustTableBody');
  if (!tbody) return;

  const search = document.getElementById('directAdjustSearchInput')?.value.trim().toLowerCase() || '';
  const filter = document.getElementById('directAdjustFilterSelect')?.value || 'ALL';

  let filtered = directAdjustData.filter(item => {
    // Search
    if (search) {
      const matchCode = (item.code || '').toLowerCase().includes(search);
      const matchName = (item.name || '').toLowerCase().includes(search);
      const matchRack = (item.rack_location || '').toLowerCase().includes(search);
      if (!matchCode && !matchName && !matchRack) return false;
    }

    // Filter
    if (filter === 'IMPORTED') return item.is_imported === true;
    if (filter === 'ADJUSTED_ONLY') return item.qty_adjust !== 0;
    if (filter === 'PLUS') return item.qty_adjust > 0;
    if (filter === 'MINUS') return item.qty_adjust < 0;

    return true;
  });

  if (filtered.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="10" class="p-8 text-center text-slate-400">
          <span class="material-symbols-outlined text-[32px] text-slate-300">inventory_2</span>
          <p class="text-xs font-bold text-slate-700 mt-1">Tidak ada packaging material yang cocok</p>
          <p class="text-[11px] text-slate-400">Silakan ubah kata kunci pencarian atau filter status.</p>
        </td>
      </tr>
    `;
    updateDirectAdjustCounters();
    return;
  }

  tbody.innerHTML = filtered.map((item, idx) => {
    const currentStock = item.current_stock;
    const qtyAdjust = item.qty_adjust || 0;
    const newStock = currentStock + qtyAdjust;
    const isAdjusted = qtyAdjust !== 0;
    const isImported = !!item.is_imported;

    let statusBadge = '';
    if (qtyAdjust > 0) {
      statusBadge = `<span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-bold text-[10px] border border-blue-200">+${App.formatNumber(qtyAdjust)}</span>`;
    } else if (qtyAdjust < 0) {
      statusBadge = `<span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 font-bold text-[10px] border border-rose-200">${App.formatNumber(qtyAdjust)}</span>`;
    } else if (isImported) {
      statusBadge = '<span class="px-2 py-0.5 rounded bg-amber-50 text-amber-800 font-bold text-[10px] border border-amber-200">Import (0)</span>';
    } else {
      statusBadge = '<span class="font-mono text-slate-400 text-[11px]">-</span>';
    }

    let newStockBg = 'text-slate-800 bg-slate-100/50';
    if (newStock < 0) newStockBg = 'text-rose-600 bg-rose-50/50';
    else if (qtyAdjust > 0) newStockBg = 'text-emerald-700 bg-emerald-50/40';
    else if (qtyAdjust < 0) newStockBg = 'text-rose-700 bg-rose-50/40';

    const rowHighlight = isAdjusted ? 'bg-amber-50/30' : (isImported ? 'bg-amber-50/15' : '');

    return `
      <tr id="adjust-row-${escapeHtml(item.code)}" class="hover:bg-slate-50 border-b border-slate-100 text-xs transition-colors ${rowHighlight}">
        <td class="p-3 text-center text-slate-400 font-mono">${idx + 1}</td>
        <td class="p-3">
          <div class="font-mono font-bold text-amber-800">${escapeHtml(item.code)}</div>
          ${isImported ? '<span class="inline-block mt-0.5 px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 font-bold text-[9px] border border-amber-200">Di-Import</span>' : ''}
        </td>
        <td class="p-3 font-semibold text-slate-800">${escapeHtml(item.name)}</td>
        <td class="p-3 text-center font-medium text-slate-600">${escapeHtml(item.unit)}</td>
        <td class="p-3 text-slate-600">${escapeHtml(item.rack_location)}</td>
        <td class="p-3 text-center font-mono font-bold text-slate-700 bg-slate-50">${App.formatNumber(currentStock)}</td>
        <td class="p-2 text-center bg-amber-50/40 border-x border-amber-100">
          <input type="number" step="any" value="${qtyAdjust !== 0 ? qtyAdjust : ''}" placeholder="0" 
            oninput="handleDirectAdjustQtyChange('${escapeHtml(item.code)}', this.value)"
            class="w-full text-center py-1.5 px-2 bg-white border border-amber-300 rounded-lg text-xs font-mono font-black text-slate-900 outline-none focus:border-amber-600 focus:ring-1 focus:ring-amber-500 shadow-xs">
        </td>
        <td class="col-new-stock p-3 text-center font-mono font-black ${newStockBg}">
          ${App.formatNumber(newStock)}
        </td>
        <td class="p-2">
          <input type="text" value="${escapeHtml(item.notes || '')}" placeholder="Alasan penyesuaian (opsional)..." 
            oninput="handleDirectAdjustNotesChange('${escapeHtml(item.code)}', this.value)"
            class="w-full py-1.5 px-2.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 focus:border-amber-500 rounded-lg text-xs outline-none transition-colors">
        </td>
        <td class="col-status p-3 text-center whitespace-nowrap">${statusBadge}</td>
      </tr>
    `;
  }).join('');

  updateDirectAdjustCounters();
}

// Sub-Tab Switcher for Adjust Module
let currentAdjustSubTab = 'form';

function switchAdjustSubTab(subTab) {
  currentAdjustSubTab = subTab;
  const tabForm = document.getElementById('adjust-subtab-form');
  const tabHistory = document.getElementById('adjust-subtab-history');
  const btnForm = document.getElementById('btnSubTabAdjustForm');
  const btnHistory = document.getElementById('btnSubTabAdjustHistory');

  if (subTab === 'form') {
    if (tabForm) tabForm.classList.remove('hidden');
    if (tabHistory) tabHistory.classList.add('hidden');

    if (btnForm) btnForm.className = 'py-2 px-3.5 rounded-lg flex items-center gap-1.5 bg-amber-600 text-white shadow-xs transition-all';
    if (btnHistory) btnHistory.className = 'py-2 px-3.5 rounded-lg flex items-center gap-1.5 text-slate-600 hover:text-slate-900 transition-all';
  } else {
    if (tabForm) tabForm.classList.add('hidden');
    if (tabHistory) tabHistory.classList.remove('hidden');

    if (btnForm) btnForm.className = 'py-2 px-3.5 rounded-lg flex items-center gap-1.5 text-slate-600 hover:text-slate-900 transition-all';
    if (btnHistory) btnHistory.className = 'py-2 px-3.5 rounded-lg flex items-center gap-1.5 bg-amber-600 text-white shadow-xs transition-all';

    loadAdjustHistory();
  }
}

let adjustHistoryData = [];

async function loadAdjustHistory() {
  const tbody = document.getElementById('adjustHistoryTableBody');
  if (tbody) {
    tbody.innerHTML = `
      <tr>
        <td colspan="10" class="p-8 text-center text-slate-400">
          <span class="material-symbols-outlined text-[28px] animate-spin text-amber-600">progress_activity</span>
          <p class="text-xs font-semibold text-slate-600 mt-2">Memuat riwayat penyesuaian stok...</p>
        </td>
      </tr>
    `;
  }

  const search = document.getElementById('adjustHistorySearchInput')?.value.trim() || '';
  const date = document.getElementById('adjustHistoryDateFilter')?.value.trim() || '';
  const query = new URLSearchParams({ action: 'history', search, date });

  const res = await App.fetchJson(`../api/adjust_stock.php?${query.toString()}`);
  if (res.success) {
    adjustHistoryData = res.data || [];
    renderAdjustHistoryTable();
  }
}

function renderAdjustHistoryTable() {
  const tbody = document.getElementById('adjustHistoryTableBody');
  if (!tbody) return;

  const search = document.getElementById('adjustHistorySearchInput')?.value.trim().toLowerCase() || '';
  const date = (document.getElementById('adjustHistoryDateFilter')?.value || '').trim();

  let filtered = adjustHistoryData.filter(item => {
    if (date && !(item.created_at || '').startsWith(date)) return false;
    if (!search) return true;
    const matchCode = (item.material_code || '').toLowerCase().includes(search);
    const matchName = (item.material_name || '').toLowerCase().includes(search);
    const matchRef = (item.reference_no || '').toLowerCase().includes(search);
    const matchNotes = (item.notes || '').toLowerCase().includes(search);
    return matchCode || matchName || matchRef || matchNotes;
  });

  if (filtered.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="10" class="p-8 text-center text-slate-400">
          <span class="material-symbols-outlined text-[32px] text-slate-300">history</span>
          <p class="text-xs font-bold text-slate-700 mt-1">Belum ada riwayat penyesuaian stok</p>
        </td>
      </tr>
    `;
    return;
  }

  tbody.innerHTML = filtered.map((h, idx) => {
    const qtyChange = parseFloat(h.qty_change);
    const isPlus = qtyChange > 0;
    const badge = isPlus
      ? `<span class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-bold text-[10px] border border-blue-200">+${App.formatNumber(qtyChange)}</span>`
      : `<span class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 font-bold text-[10px] border border-rose-200">${App.formatNumber(qtyChange)}</span>`;

    return `
      <tr class="hover:bg-slate-50 border-b border-slate-100 text-xs">
        <td class="p-3 text-center text-slate-400 font-mono">${idx + 1}</td>
        <td class="p-3 font-mono text-[11px] text-slate-600 whitespace-nowrap">${App.formatDate(h.created_at)}</td>
        <td class="p-3 font-mono font-bold text-amber-800">${escapeHtml(h.reference_no)}</td>
        <td class="p-3">
          <div class="font-bold text-slate-900">${escapeHtml(h.material_name)}</div>
          <div class="font-mono text-[11px] text-indigo-700">${escapeHtml(h.material_code)}</div>
        </td>
        <td class="p-3 text-slate-600">${escapeHtml(h.rack_location || '-')}</td>
        <td class="p-3 text-center font-mono text-slate-600">${App.formatNumber(h.stock_before)}</td>
        <td class="p-3 text-center whitespace-nowrap">${badge}</td>
        <td class="p-3 text-center font-mono font-black text-slate-900 bg-slate-50">${App.formatNumber(h.stock_after)}</td>
        <td class="p-3 text-slate-700">${escapeHtml(h.notes || '-')}</td>
        <td class="p-3 text-center text-slate-600">${escapeHtml(h.user_name || h.user_username || 'Admin')}</td>
      </tr>
    `;
  }).join('');
}

function exportAdjustHistoryExcel() {
  const search = document.getElementById('adjustHistorySearchInput')?.value.trim() || '';
  const date = document.getElementById('adjustHistoryDateFilter')?.value.trim() || '';
  let url = 'export.php?type=adjust_history';
  if (search) url += '&search=' + encodeURIComponent(search);
  if (date) url += '&date=' + encodeURIComponent(date);
  window.location.href = url;
}

async function handleDirectExcelUpload(input) {
  const file = input.files[0];
  if (!file) return;

  const commitBtn = document.getElementById('btnCommitDirectAdjust');
  if (commitBtn) {
    commitBtn.disabled = true;
  }

  App.toast('Membaca dan memvalidasi file Excel...', 'info');

  // Ensure all master materials are loaded into directAdjustData
  if (!directAdjustData || directAdjustData.length === 0) {
    await loadDirectAdjustMaterials();
  }

  try {
    // Reset previous is_imported flags
    directAdjustData.forEach(d => { d.is_imported = false; });

    let appliedCount = 0;
    let matchedTotal = 0;
    let totalParsedRows = 0;
    const debugMissing = [];

    // Helper to find / match target in directAdjustData
    const findTarget = (code, desc) => {
      if (!code && !desc) return null;
      let target = null;
      const rawCode = String(code || '').trim();
      const rawDesc = String(desc || '').trim();

      if (rawCode) {
        // 1. Exact code
        target = directAdjustData.find(d => String(d.code).trim() === rawCode);
        // 2. Case-insensitive code
        if (!target) target = directAdjustData.find(d => String(d.code).trim().toLowerCase() === rawCode.toLowerCase());
        // 3. Clean alphanumeric code
        const cleanCode = rawCode.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
        if (!target && cleanCode) {
          target = directAdjustData.find(d => String(d.code).replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === cleanCode);
        }
        // 4. Numeric code
        if (!target && !isNaN(rawCode)) {
          const numCode = parseInt(rawCode, 10);
          target = directAdjustData.find(d => parseInt(d.code, 10) === numCode);
        }
      }

      // 5. Match by description/name if code not matched
      if (!target && rawDesc) {
        const cleanDesc = rawDesc.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
        target = directAdjustData.find(d => String(d.name).replace(/[^a-zA-Z0-9]/g, '').toLowerCase() === cleanDesc);
      }
      return target;
    };

    if (window.XLSX) {
      const data = await file.arrayBuffer();
      const workbook = XLSX.read(data, { type: 'array' });
      const firstSheetName = workbook.SheetNames[0];
      const worksheet = workbook.Sheets[firstSheetName];
      const jsonRows = XLSX.utils.sheet_to_json(worksheet, { header: 1, defval: '' });

      if (jsonRows && jsonRows.length > 1) {
        // ====== STEP 1: Find header row ======
        let headerRowIdx = -1;
        for (let r = 0; r < Math.min(10, jsonRows.length); r++) {
          const rowStr = jsonRows[r].map(c => String(c ?? '')).join(' ').toLowerCase();
          const hasItemCol = rowStr.includes('item no') || rowStr.includes('item_no') || rowStr.includes('kode item') || rowStr.includes('kode material') || rowStr.includes('kode barang') || rowStr.includes('item code') || rowStr.includes('material code') || rowStr.includes('sku') || rowStr.includes('deskripsi');
          const hasAdjustCol = rowStr.includes('adjust') || rowStr.includes('selisih') || rowStr.includes('qty') || rowStr.includes('penyesuaian') || rowStr.includes('jumlah') || rowStr.includes('stok');
          if (hasItemCol && hasAdjustCol) { headerRowIdx = r; break; }
        }
        if (headerRowIdx === -1) {
          for (let r = 0; r < Math.min(10, jsonRows.length); r++) {
            const rowStr = jsonRows[r].map(c => String(c ?? '')).join(' ').toLowerCase();
            if (rowStr.includes('item no') || rowStr.includes('item_no') || rowStr.includes('kode item') || rowStr.includes('kode material') || rowStr.includes('sku')) { headerRowIdx = r; break; }
          }
        }
        if (headerRowIdx === -1) headerRowIdx = 0;

        // ====== STEP 2: Map column indices ======
        const cleanHeaders = jsonRows[headerRowIdx].map(h => String(h ?? '').toLowerCase().replace(/[^a-z0-9]/g, ''));
        let itemNoIdx = -1, descIdx = -1, adjustIdx = -1, notesIdx = -1;
        cleanHeaders.forEach((h, idx) => {
          if (itemNoIdx === -1 && ['itemno', 'itemnumber', 'kodeitem', 'kode', 'code', 'sku', 'kodematerial', 'materialcode', 'itemcode', 'kodebarang', 'kodeproduk', 'nomoritem', 'material'].includes(h)) itemNoIdx = idx;
          else if (descIdx === -1 && ['deskripsi', 'description', 'itemdescription', 'namabarang', 'namamaterial', 'namaitem', 'nama'].includes(h)) descIdx = idx;
          else if (adjustIdx === -1 && (['qtyadjust', 'adjustqty', 'adjust', 'selisihadjust', 'selisih', 'diff', 'difference', 'perubahanstok', 'selisihstok', 'selisihfisik', 'jumlah', 'qty', 'penyesuaian', 'koreksi'].some(k => h.includes(k)))) adjustIdx = idx;
          else if (notesIdx === -1 && ['notes', 'alasan', 'keterangan', 'catatan', 'reason', 'note', 'ket'].some(k => h.includes(k))) notesIdx = idx;
        });

        if (itemNoIdx === -1) itemNoIdx = 0;
        if (adjustIdx === -1) {
          for (let c = 1; c < cleanHeaders.length; c++) {
            if (cleanHeaders[c].includes('adjust') || cleanHeaders[c].includes('selisih') || cleanHeaders[c].includes('qty') || cleanHeaders[c].includes('penyesuaian') || cleanHeaders[c].includes('diff')) { adjustIdx = c; break; }
          }
          if (adjustIdx === -1) adjustIdx = cleanHeaders.length >= 3 ? 2 : 1;
        }

        // Process all rows from the Excel
        for (let i = headerRowIdx + 1; i < jsonRows.length; i++) {
          const row = jsonRows[i];
          if (!row || row.length === 0) continue;

          // Extract code & desc
          let rawCodeVal = row[itemNoIdx];
          let rawCode = '';
          if (rawCodeVal !== null && rawCodeVal !== undefined) {
            rawCode = (typeof rawCodeVal === 'number') ? String(Math.round(rawCodeVal)) : String(rawCodeVal).trim();
          }
          let rawDesc = (descIdx !== -1 && row[descIdx]) ? String(row[descIdx]).trim() : '';

          if (!rawCode && !rawDesc) continue;
          totalParsedRows++;

          // Extract adjust value
          let rawAdjustVal = row[adjustIdx];
          let adjustQty = 0;
          if (typeof rawAdjustVal === 'number') {
            adjustQty = rawAdjustVal;
          } else if (rawAdjustVal !== null && rawAdjustVal !== undefined && rawAdjustVal !== '') {
            let rawAdjust = String(rawAdjustVal).trim();
            let sign = 1;
            if (rawAdjust.includes('-') || (rawAdjust.startsWith('(') && rawAdjust.endsWith(')'))) { sign = -1; }
            const normalized = rawAdjust.replace(',', '.');
            const cleanDigits = normalized.replace(/[^0-9\.]/g, '');
            adjustQty = cleanDigits ? sign * parseFloat(cleanDigits) : 0;
          }

          // Extract notes
          const notesVal = (notesIdx !== -1 && row[notesIdx]) ? String(row[notesIdx]).trim() : 'Upload Excel Adjust';

          let target = findTarget(rawCode, rawDesc);
          if (target) {
            target.qty_adjust = adjustQty;
            target.notes = notesVal;
            target.is_imported = true;
            matchedTotal++;
            if (adjustQty !== 0) appliedCount++;
          } else {
            // Add dynamically to directAdjustData so user sees it in the table
            directAdjustData.push({
              id: 0,
              code: rawCode || ('UNKNOWN-' + (matchedTotal + 1)),
              name: rawDesc || rawCode || 'Item dari Excel',
              unit: 'Pcs',
              rack_location: '-',
              current_stock: 0,
              qty_adjust: adjustQty,
              notes: notesVal,
              is_imported: true,
              is_not_found: true
            });
            matchedTotal++;
            if (adjustQty !== 0) appliedCount++;
            debugMissing.push(rawCode || rawDesc);
          }
        }

        input.value = '';
        const filterSelect = document.getElementById('directAdjustFilterSelect');
        if (filterSelect) {
          filterSelect.value = 'ALL';
        }
        renderDirectAdjustTable();

        if (matchedTotal > 0) {
          App.toast(`Semua ${matchedTotal} SKU dari file Excel berhasil dimuat ke sistem (${appliedCount} SKU memiliki nilai selisih +/-).`, 'success', 'Excel Berhasil Dimuat');
        } else {
          App.toast(`Tidak ada baris data SKU yang dapat dibaca dari file Excel.`, 'warning', 'File Kosong');
        }
        return;
      }
    }

    // Fallback to server preview
    const formData = new FormData();
    formData.append('file', file);
    const response = await fetch('../api/adjust_stock.php?action=preview', {
      method: 'POST',
      body: formData
    });
    const res = await response.json();
    input.value = '';

    if (!res.success || !res.items || res.items.length === 0) {
      App.toast(res.message || 'Tidak ada baris data valid yang terdeteksi dari Excel.', 'error');
      updateDirectAdjustCounters();
      return;
    }

    res.items.forEach(excelItem => {
      const code = String(excelItem.item_no || excelItem.code || '').trim();
      const desc = String(excelItem.item_name || excelItem.name || '').trim();
      const adjustQty = parseFloat(excelItem.qty_adjust ?? excelItem.adjust_qty ?? 0);
      const notes = excelItem.notes || excelItem.note || 'Penyesuaian Excel';

      if (!code && !desc) return;

      let target = findTarget(code, desc);
      if (target) {
        target.qty_adjust = adjustQty;
        if (notes) target.notes = notes;
        target.is_imported = true;
        matchedTotal++;
        if (adjustQty !== 0) appliedCount++;
      } else {
        directAdjustData.push({
          id: excelItem.material_id || 0,
          code: code || ('UNKNOWN-' + (matchedTotal + 1)),
          name: desc || code,
          unit: excelItem.unit || 'Pcs',
          rack_location: excelItem.rack_location || '-',
          current_stock: excelItem.stock_before || 0,
          qty_adjust: adjustQty,
          notes: notes,
          is_imported: true,
          is_not_found: true
        });
        matchedTotal++;
        if (adjustQty !== 0) appliedCount++;
      }
    });

    const filterSelect = document.getElementById('directAdjustFilterSelect');
    if (filterSelect) filterSelect.value = 'ALL';
    renderDirectAdjustTable();
    App.toast(`Semua ${matchedTotal} SKU dari file Excel berhasil dimuat ke sistem (${appliedCount} SKU memiliki nilai selisih +/-).`, 'success', 'Excel Berhasil Dimuat');
  } catch (err) {
    input.value = '';
    App.toast('Gagal memproses file Excel: ' + err.message, 'error');
    updateDirectAdjustCounters();
  }
}

function resetDirectAdjustTable() {
  directAdjustData.forEach(item => {
    item.qty_adjust = 0;
    item.notes = '';
    item.is_imported = false;
  });
  renderDirectAdjustTable();
  App.toast('Semua input penyesuaian telah dibersihkan.', 'info');
}

async function commitDirectAdjustTable() {
  const itemsToCommit = directAdjustData.filter(d => d.qty_adjust !== 0);

  if (itemsToCommit.length === 0) {
    App.toast('Tidak ada material packaging yang memiliki jumlah penyesuaian (Qty Adjust masih 0).', 'warning');
    return;
  }

  const payloadItems = itemsToCommit.map(i => ({
    material_id: i.id,
    item_no: i.code,
    item_name: i.name,
    unit: i.unit,
    stock_before: i.current_stock,
    qty_adjust: i.qty_adjust,
    stock_after: i.current_stock + i.qty_adjust,
    notes: i.notes || 'Penyesuaian Stok Master'
  }));

  const btn = document.getElementById('btnCommitDirectAdjust');
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span>';
  }

  const res = await App.fetchJson('../api/adjust_stock.php?action=commit', {
    method: 'POST',
    body: JSON.stringify({
      items: payloadItems,
      batch_notes: 'Penyesuaian Stok Master'
    })
  });

  if (btn) {
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check_circle</span>';
  }

  if (res.success) {
    App.toast(res.message, 'success', 'Penyesuaian Berhasil Diterapkan');
    await loadMaterials();
    loadStats();
    loadMutations();
    await loadDirectAdjustMaterials();
  } else {
    App.toast(res.message || 'Gagal menerapkan penyesuaian stok', 'error');
    updateDirectAdjustCounters();
  }
}

function downloadAdjustExcelTemplate() {
  let exportList = [];

  if (allMaterials && allMaterials.length > 0) {
    exportList = allMaterials.map((m, idx) => ({
      'Item No': m.code,
      'Deskripsi Material Packaging': m.name,
      'Satuan': m.unit || 'Pcs',
      'Lokasi Rak': m.rack_location || '-',
      'Stok Sistem Saat Ini': parseInt(m.current_stock || '0', 10),
      'Qty Adjust (+/-)': '',
      'Alasan / Catatan Penyesuaian': ''
    }));
  } else {
    exportList = [
      {
        'Item No': '4000010001',
        'Deskripsi Material Packaging': 'Dus E-commerce Hanasui Uk. Kecil 225 x 85 x 85 cm',
        'Satuan': 'Pcs',
        'Lokasi Rak': 'Rak A-01',
        'Stok Sistem Saat Ini': 100,
        'Qty Adjust (+/-)': '+150',
        'Alasan / Catatan Penyesuaian': 'Contoh: Surplus Fisik (+150 Menambah Stok)'
      },
      {
        'Item No': '4000010002',
        'Deskripsi Material Packaging': 'Dus E-commerce Hanasui Uk. Besar 250 x 200 x 85 cm',
        'Satuan': 'Pcs',
        'Lokasi Rak': 'Rak A-05',
        'Stok Sistem Saat Ini': 50,
        'Qty Adjust (+/-)': '-25',
        'Alasan / Catatan Penyesuaian': 'Contoh: Rusak / Reject (-25 Memotong Stok)'
      },
      {
        'Item No': '4000020001',
        'Deskripsi Material Packaging': 'Plastik Hanasui Ukuran Besar 21,5 x 35 cm',
        'Satuan': 'Pcs',
        'Lokasi Rak': 'Rak B-01',
        'Stok Sistem Saat Ini': 500,
        'Qty Adjust (+/-)': '+50',
        'Alasan / Catatan Penyesuaian': 'Contoh: Koreksi Selisih Lapangan'
      }
    ];
  }

  if (window.XLSX) {
    const ws = XLSX.utils.json_to_sheet(exportList);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Template Adjust');
    XLSX.writeFile(wb, 'Template_Penyesuaian_Stok_Adjust.xlsx');
    App.toast('Template Excel (.xlsx) berhasil di-download!', 'success');
  } else {
    window.location.href = 'export.php?type=adjust_template';
  }
}

// =========================================================================
// 12. SUPER ADMIN DATABASE MAINTENANCE & TABLE CLEANER
// =========================================================================
async function loadDatabaseStats() {
  try {
    const res = await App.fetchJson('../api/maintenance.php?action=stats');
    if (res && res.success && res.stats) {
      const s = res.stats;
      const setBadge = (id, count, suffix) => {
        const el = document.getElementById(id);
        if (el) {
          el.innerText = `${(count || 0).toLocaleString('id-ID')} ${suffix}`;
        }
      };

      setBadge('statMaint_materials', s.materials ? s.materials.count : 0, 'SKU');
      setBadge('statMaint_inbound_transactions', s.inbound_transactions ? s.inbound_transactions.count : 0, 'Transaksi');
      setBadge('statMaint_outbound_transactions', s.outbound_transactions ? s.outbound_transactions.count : 0, 'Transaksi');
      setBadge('statMaint_tasks', s.tasks ? s.tasks.count : 0, 'Task');
      setBadge('statMaint_stock_opnames', s.stock_opnames ? s.stock_opnames.count : 0, 'Sesi');
      setBadge('statMaint_stock_mutations', s.stock_mutations ? s.stock_mutations.count : 0, 'Entri');
    }
  } catch (err) {
    console.error('Error loading database maintenance stats:', err);
  }
}

function openCleanTableModal(tableKey, tableName, currentCount) {
  const modal = document.getElementById('modalConfirmDbClean');
  if (!modal) return;

  document.getElementById('cleanActionType').value = 'clean_table';
  document.getElementById('cleanTargetTable').value = tableKey;
  document.getElementById('cleanModalTargetTitle').innerText = 'Tabel yang akan dikosongkan:';
  document.getElementById('cleanModalTargetDesc').innerText = `${tableName} (Total saat ini: ${currentCount})`;
  document.getElementById('cleanSuperAdminPassword').value = '';
  document.getElementById('btnSubmitCleanDbText').innerText = 'Ya, Kosongkan Tabel Sekarang';

  App.openModal('modalConfirmDbClean');
  setTimeout(() => {
    const pwdInput = document.getElementById('cleanSuperAdminPassword');
    if (pwdInput) pwdInput.focus();
  }, 100);
}

function openBulkCleanModal(actionType) {
  const modal = document.getElementById('modalConfirmDbClean');
  if (!modal) return;

  document.getElementById('cleanActionType').value = actionType;
  document.getElementById('cleanTargetTable').value = '';
  document.getElementById('cleanSuperAdminPassword').value = '';

  if (actionType === 'clean_all_transactions') {
    const resetStock = document.getElementById('maintResetStockZero')?.checked;
    document.getElementById('cleanModalTargetTitle').innerText = 'Tindakan Pembersihan:';
    document.getElementById('cleanModalTargetDesc').innerText = `KOSONGKAN SEMUA TRANSAKSI (Inbound, Outbound, Tasks, Opname, Mutasi)${resetStock ? ' + Reset Current Stock ke 0' : ''}`;
    document.getElementById('btnSubmitCleanDbText').innerText = 'Ya, Bersihkan Semua Transaksi';
  } else if (actionType === 'factory_reset') {
    document.getElementById('cleanModalTargetTitle').innerText = 'Tindakan Pembersihan:';
    document.getElementById('cleanModalTargetDesc').innerText = 'RESET DATABASE PENUH (FACTORY RESET) - Menghapus Semua Master Stok & Seluruh Transaksi!';
    document.getElementById('btnSubmitCleanDbText').innerText = 'Ya, Reset Database Penuh Sekarang';
  }

  App.openModal('modalConfirmDbClean');
  setTimeout(() => {
    const pwdInput = document.getElementById('cleanSuperAdminPassword');
    if (pwdInput) pwdInput.focus();
  }, 100);
}

async function submitCleanDatabase(e) {
  e.preventDefault();
  const actionType = document.getElementById('cleanActionType').value;
  const tableKey = document.getElementById('cleanTargetTable').value;
  const password = document.getElementById('cleanSuperAdminPassword').value.trim();
  const resetStockZero = document.getElementById('maintResetStockZero')?.checked ? 1 : 0;
  const btn = document.getElementById('btnSubmitCleanDb');
  const btnText = document.getElementById('btnSubmitCleanDbText');

  if (!password) {
    App.toast('Password Teknisi wajib diisi!', 'warning');
    return;
  }

  btn.disabled = true;
  btn.classList.add('opacity-70', 'cursor-not-allowed');
  const origText = btnText.innerText;
  btnText.innerText = 'Memproses...';

  try {
    let payload = {
      password: password
    };

    if (actionType === 'clean_table') {
      payload.table = tableKey;
    } else if (actionType === 'clean_all_transactions') {
      payload.reset_stock_zero = resetStockZero;
    }

    const res = await App.fetchJson(`../api/maintenance.php?action=${actionType}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (res && res.success) {
      App.toast(res.message || 'Pembersihan database berhasil diselesaikan!', 'success');
      App.closeModal('modalConfirmDbClean');
      
      // Refresh DB stats and related modules
      loadDatabaseStats();
      if (typeof loadMaterials === 'function') loadMaterials();
      if (typeof loadMutations === 'function') loadMutations(true);
      if (typeof loadStats === 'function') loadStats(true);
      if (typeof loadInboundHistory === 'function') loadInboundHistory();
      if (typeof loadOutboundHistory === 'function') loadOutboundHistory();
      if (typeof loadTasks === 'function') loadTasks();
      if (typeof loadOpnames === 'function') loadOpnames();
      if (typeof loadDynamicSessions === 'function') loadDynamicSessions();
    } else {
      App.toast(res?.message || 'Gagal memproses pembersihan database', 'error');
    }
  } catch (err) {
    App.toast(err.message || 'Terjadi kesalahan sistem', 'error');
  } finally {
    btn.disabled = false;
    btn.classList.remove('opacity-70', 'cursor-not-allowed');
    btnText.innerText = origText;
  }
}

async function toggleMaintenanceMode(active) {
  const btn = document.getElementById('btnToggleMaintenance');
  if (!btn) return;

  btn.disabled = true;
  const origHtml = btn.innerHTML;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>';

  try {
    const res = await App.fetchJson('../api/maintenance.php?action=toggle_maintenance', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ active: active })
    });

    if (res && res.success) {
      App.toast(res.message, 'success');
      
      // Update UI components dynamically
      const badge = document.getElementById('maintenanceBadge');
      if (badge) {
        badge.innerText = active ? 'AKTIF (SITUS DIKUNCI)' : 'NON-AKTIF';
        badge.className = active 
          ? 'px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase bg-rose-100 text-rose-800 border border-rose-200'
          : 'px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase bg-slate-100 text-slate-600 border border-slate-200';
      }

      btn.className = active 
        ? 'px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow transition-colors flex items-center gap-1.5 cursor-pointer'
        : 'px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow transition-colors flex items-center gap-1.5 cursor-pointer';

      btn.innerHTML = `<span class="material-symbols-outlined text-[16px]">${active ? 'lock_open' : 'lock'}</span><span>${active ? 'Matikan Mode Maintenance' : 'Aktifkan Mode Maintenance'}</span>`;
      btn.setAttribute('onclick', `toggleMaintenanceMode(${active ? 'false' : 'true'})`);
    } else {
      App.toast(res?.message || 'Gagal mengubah status mode maintenance', 'error');
      btn.innerHTML = origHtml;
    }
  } catch (err) {
    App.toast(err.message || 'Terjadi kesalahan jaringan', 'error');
    btn.innerHTML = origHtml;
  } finally {
    btn.disabled = false;
  }
}



