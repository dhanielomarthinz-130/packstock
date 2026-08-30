let myTasks = [];
let allStock = [];
let opInboundDraft = [];
let currentOpTab = 'home';
let activeSubmittingTask = null;

document.addEventListener('DOMContentLoaded', () => {
  updateLiveClock();
  setInterval(updateLiveClock, 1000);
  updateGreeting();

  loadOperatorStats();
  loadOperatorTasks(true);
  loadOperatorDynamicTasks(true);
  loadOperatorBlankCounts(true);
  loadOperatorRecountTasks(true);
  loadOperatorStock(true);
  loadHandovers(true);
  initMandatoryShiftGate();

  // Auto refresh data every 15 seconds in background
  setInterval(() => {
    if (currentOpTab === 'tasks') loadOperatorTasks(true);
    if (currentOpTab === 'dynamic_count') loadOperatorDynamicTasks(true);
    if (currentOpTab === 'opname') { loadOperatorBlankCounts(true); loadOperatorRecountTasks(true); }
    if (currentOpTab === 'handover') loadHandovers(true);
    loadOperatorStats(true);
    loadHandovers(true);
  }, 15000);
});

// Digital Clock Updater
function updateLiveClock() {
  const clockEl = document.getElementById('liveClock');
  if (clockEl) {
    const now = new Date();
    clockEl.innerText = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
  }
}

// Dynamic Greeting (Pagi / Siang / Sore / Malam)
function updateGreeting() {
  const greetingEl = document.getElementById('homeGreetingText');
  if (greetingEl) {
    const hour = new Date().getHours();
    let text = 'Selamat Pagi';
    if (hour >= 11 && hour < 15) text = 'Selamat Siang';
    else if (hour >= 15 && hour < 18) text = 'Selamat Sore';
    else if (hour >= 18 || hour < 5) text = 'Selamat Malam';
    greetingEl.innerText = text;
  }
}

// Sync Button with Rotation Effect
async function refreshOperatorData() {
  const icon = document.getElementById('btnSyncIcon');
  if (icon) icon.classList.add('animate-spin');

  await Promise.all([
    loadOperatorStats(true),
    loadOperatorTasks(true),
    loadOperatorDynamicTasks(true),
    loadOperatorBlankCounts(true),
    loadOperatorRecountTasks(true),
    loadOperatorStock(true)
  ]);

  if (icon) {
    setTimeout(() => icon.classList.remove('animate-spin'), 600);
  }
  App.toast('Data & penugasan berhasil diperbarui', 'info');
}

// Mobile Screen / Tab Switcher
function switchOpTab(tabName) {
  currentOpTab = tabName;
  const allTabs = ['home', 'tasks', 'dynamic_count', 'opname', 'inbound', 'stock', 'history', 'handover'];

  allTabs.forEach(t => {
    const el = document.getElementById('op-tab-' + t);
    if (el) el.classList.add('hidden');
  });

  const activeTab = document.getElementById('op-tab-' + tabName);
  if (activeTab) {
    activeTab.classList.remove('hidden');
    const viewport = document.getElementById('operatorViewport');
    if (viewport) viewport.scrollTop = 0;
  }

  // Update bottom navigation bar active states
  const bottomNavs = ['home', 'tasks', 'dynamic_count', 'opname'];
  bottomNavs.forEach(nav => {
    const navBtn = document.getElementById('bottom-nav-' + nav);
    if (navBtn) {
      if (nav === tabName) {
        navBtn.classList.remove('text-slate-400', 'font-semibold');
        navBtn.classList.add('text-emerald-700', 'font-bold');
      } else {
        navBtn.classList.remove('text-emerald-700', 'font-bold');
        navBtn.classList.add('text-slate-400', 'font-semibold');
      }
    }
  });

  // Trigger sub-view data loading
  if (tabName === 'tasks') loadOperatorTasks();
  if (tabName === 'dynamic_count') loadOperatorDynamicTasks();
  if (tabName === 'opname') { 
    populateBlankMaterials(); 
    loadOperatorBlankCounts(); 
    loadOperatorRecountTasks(); 
    switchOpnameSubTab(currentOpnameSubTab || '1st');
  }
  if (tabName === 'inbound') populateOpInboundMaterials();
  if (tabName === 'stock') loadOperatorStock();
  if (tabName === 'history') renderCompletedHistory();
  if (tabName === 'handover') loadHandovers();
}

// Side Drawer Navigation (Toggle in Top-Left)
function toggleOperatorDrawer() {
  const drawer = document.getElementById('operatorDrawer');
  const backdrop = document.getElementById('operatorDrawerBackdrop');
  if (!drawer || !backdrop) return;

  const isClosed = drawer.classList.contains('-translate-x-full');
  if (isClosed) {
    backdrop.classList.remove('hidden');
    setTimeout(() => {
      backdrop.classList.remove('opacity-0');
      drawer.classList.remove('-translate-x-full');
    }, 10);
  } else {
    closeOperatorDrawer();
  }
}

function closeOperatorDrawer() {
  const drawer = document.getElementById('operatorDrawer');
  const backdrop = document.getElementById('operatorDrawerBackdrop');
  if (!drawer || !backdrop) return;

  drawer.classList.add('-translate-x-full');
  backdrop.classList.add('opacity-0');
  setTimeout(() => {
    backdrop.classList.add('hidden');
  }, 300);
}

// Open Setting & Profile Modal
function openSettingProfileModal() {
  App.openModal('modalOperatorProfile');
}

// Open About Modal
function openAboutModal() {
  App.openModal('modalOperatorAbout');
}

// Handle Operator Password Change
async function handleOperatorPasswordSubmit(e) {
  e.preventDefault();
  const old_password = document.getElementById('opOldPassword').value;
  const new_password = document.getElementById('opNewPassword').value;
  const confirm_password = document.getElementById('opConfirmNewPassword').value;

  if (new_password !== confirm_password) {
    App.toast('Konfirmasi password baru tidak cocok!', 'warning');
    return;
  }

  const btn = document.getElementById('btnSubmitOpPassword');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span> Menyimpan...';

  const res = await App.fetchJson('../api/users.php?action=update_my_password', {
    method: 'POST',
    body: JSON.stringify({ old_password, new_password })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">key</span><span>Simpan Perubahan Password</span>';

  if (res.success) {
    App.toast(res.message || 'Password Anda berhasil diperbarui!', 'success', 'Keamanan');
    document.getElementById('opOldPassword').value = '';
    document.getElementById('opNewPassword').value = '';
    document.getElementById('opConfirmNewPassword').value = '';
    App.closeModal('modalOperatorProfile');
  } else {
    App.toast(res.message || 'Gagal mengubah password', 'error');
  }
}

// Rapid Numeric Stepper Helper for Touch Devices
function adjustNumericInput(inputId, delta, isReset = false) {
  const input = document.getElementById(inputId);
  if (!input) return;
  if (isReset) {
    input.value = 0;
  } else {
    const current = parseInt(input.value) || 0;
    input.value = Math.max(0, current + delta);
  }
}

// 1. STATS & HOME DASHBOARD METRICS
async function loadOperatorStats(silent = false) {
  const res = await App.fetchJson('../api/stats.php');
  if (res.success && res.stats) {
    const s = res.stats;
    const activeTasks = parseInt(s.my_active_tasks) || 0;
    const doneToday = parseInt(s.my_completed_today) || 0;

    // Home Summary Chips
    const elHomeTasks = document.getElementById('homeStatTasks');
    const elHomeDone = document.getElementById('homeStatDone');
    if (elHomeTasks) elHomeTasks.innerText = App.formatNumber(activeTasks);
    if (elHomeDone) elHomeDone.innerText = App.formatNumber(doneToday);

    // Home App Grid Badges
    const badgeTasks = document.getElementById('homeBadgeTasks');
    if (badgeTasks) {
      if (activeTasks > 0) {
        badgeTasks.innerText = `${activeTasks} Tugas`;
        badgeTasks.classList.remove('hidden');
      } else {
        badgeTasks.classList.add('hidden');
      }
    }
    if (dockBadgeTasks) {
      if (activeTasks > 0) dockBadgeTasks.classList.remove('hidden');
      else dockBadgeTasks.classList.add('hidden');
    }

    // Urgent Alert Banner on Home
    const urgentBanner = document.getElementById('homeUrgentBanner');
    const urgentText = document.getElementById('homeUrgentText');
    if (urgentBanner && urgentText) {
      if (activeTasks > 0) {
        urgentText.innerText = `Ada ${activeTasks} tugas serah terima packaging siap dikerjakan`;
        urgentBanner.classList.remove('hidden');
      } else {
        urgentBanner.classList.add('hidden');
      }
    }
  }
}

// 2. TASKS FOR OPERATOR
async function loadOperatorTasks(silent = false) {
  const res = await App.fetchJson('../api/tasks.php?action=list&my_tasks=1');
  if (res.success) {
    myTasks = res.data;
    renderOperatorTasksList();
    if (!silent) loadOperatorStats();
  }
}

function renderOperatorTasksList() {
  const container = document.getElementById('opTasksContainer');
  if (!container) return;

  const activeTasks = myTasks.filter(t => t.status === 'PENDING' || t.status === 'IN_PROGRESS');

  if (activeTasks.length === 0) {
    container.innerHTML = `
      <div class="bg-white rounded-3xl p-6 text-center border border-slate-200 shadow-xs space-y-2.5">
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 mx-auto flex items-center justify-center shadow-xs">
          <span class="material-symbols-outlined text-[28px]">task_alt</span>
        </div>
        <div>
          <h4 class="font-extrabold text-slate-800 text-sm">Tidak Ada Tugas Tertunda</h4>
          <p class="text-xs text-slate-500 mt-0.5">Semua tugas serah terima packaging telah diselesaikan.</p>
        </div>
        <button onclick="loadOperatorTasks()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200 transition-colors inline-flex items-center gap-1">
          <span class="material-symbols-outlined text-[16px]">refresh</span>
          <span>Refresh Tugas</span>
        </button>
      </div>
    `;
    return;
  }

  container.innerHTML = activeTasks.map(t => {
    const isUrgent = t.priority === 'URGENT' || t.priority === 'CRITICAL';
    const isInProgress = t.status === 'IN_PROGRESS';

    return `
      <div class="bg-white rounded-3xl p-4 border ${isUrgent ? 'border-amber-300 ring-2 ring-amber-400/30' : 'border-slate-200'} shadow-xs hover:shadow-md transition-all space-y-3">
        
        <!-- Header: Task No & Priority Badge -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="font-mono font-black text-xs text-emerald-800 bg-emerald-50 px-2.5 py-0.5 rounded-lg border border-emerald-200">${escapeHtml(t.task_no)}</span>
            ${isInProgress ? '<span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-100 text-amber-900 border border-amber-300 animate-pulse">Sedang Diambil</span>' : ''}
          </div>
          ${isUrgent 
            ? '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200 animate-pulse">URGENT</span>'
            : '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200">NORMAL</span>'}
        </div>

        <!-- Material Info -->
        <div>
          <h3 class="font-black text-slate-900 text-sm leading-snug">${escapeHtml(t.material_name)}</h3>
          <p class="text-[11px] font-mono font-bold text-slate-400 mt-0.5">${escapeHtml(t.material_code)}</p>
        </div>

        <!-- Warehouse Floor Picking Box -->
        <div class="grid grid-cols-2 gap-2 bg-slate-50/80 p-3 rounded-2xl border border-slate-100">
          <div>
            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Lokasi Rak Simpan</p>
            <p class="text-xs font-black text-slate-900 flex items-center gap-1 mt-0.5">
              <span class="material-symbols-outlined text-rose-500 text-[15px]">location_on</span>
              <span>${escapeHtml(t.rack_location)}</span>
            </p>
          </div>
          <div class="text-right">
            <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400">Target Diminta</p>
            <p class="text-sm font-black text-emerald-700 mt-0.5">
              ${App.formatNumber(t.target_qty)} <span class="text-xs font-semibold text-slate-500">${escapeHtml(t.material_unit || 'Pcs')}</span>
            </p>
          </div>
        </div>

        <!-- Destination & Notes -->
        <div class="text-xs space-y-1.5">
          <div class="flex items-center gap-1 text-slate-700 font-semibold bg-slate-50 p-2 rounded-xl border border-slate-100">
            <span class="material-symbols-outlined text-emerald-600 text-[16px]">arrow_forward</span>
            <span>Antar ke Line: <b class="text-slate-900 font-black">${escapeHtml(t.destination)}</b></span>
          </div>
          ${isInProgress ? `
            <div class="flex items-center gap-1.5 text-[11px] font-mono text-amber-800 bg-amber-50 p-2 rounded-xl border border-amber-200">
              <span class="material-symbols-outlined text-[15px] animate-spin text-amber-600">progress_activity</span>
              <span>Mulai Pengambilan: <b>${App.formatTime(t.started_at || t.created_at)}</b></span>
            </div>
          ` : ''}
          ${t.notes ? `
            <div class="p-2 rounded-xl bg-slate-50 border border-slate-200 text-slate-700 text-[11px] flex items-start gap-1">
              <span class="material-symbols-outlined text-slate-400 text-[14px] flex-shrink-0 mt-0.5">info</span>
              <span>${escapeHtml(t.notes)}</span>
            </div>
          ` : ''}
        </div>

        <!-- Action Buttons -->
        <div class="pt-1 flex items-center gap-2">
          ${!isInProgress ? `
            <button onclick="startOperatorTask(${t.id})" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-900 active:scale-95 text-white font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-1 shadow-xs">
              <span class="material-symbols-outlined text-[16px]">play_arrow</span>
              <span>Mulai Ambil</span>
            </button>
          ` : ''}

          <button onclick="openSubmitModal(${t.id})" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-black text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5">
            <span class="material-symbols-outlined text-[17px]">task_alt</span>
            <span>Submit Selesai</span>
          </button>
        </div>

      </div>
    `;
  }).join('');
}

// 3. START TASK (Status -> IN_PROGRESS)
async function startOperatorTask(taskId) {
  const res = await App.fetchJson('../api/tasks.php?action=start', {
    method: 'POST',
    body: JSON.stringify({ task_id: taskId })
  });

  if (res.success) {
    App.toast('Tugas dimulai! Waktu mulai telah dicatat.', 'info', 'In Progress');
    loadOperatorTasks();
  }
}

// 4. SUBMIT TASK MODAL & EXECUTION
function openSubmitModal(taskId) {
  const task = myTasks.find(t => t.id == taskId);
  if (!task) return;

  activeSubmittingTask = task;
  document.getElementById('submitTaskId').value = task.id;
  document.getElementById('submitMaterialTitle').innerText = task.material_name;
  document.getElementById('submitTargetQtyLabel').innerText = `${App.formatNumber(task.target_qty)} ${escapeHtml(task.material_unit || 'Pcs')}`;
  document.getElementById('submitRackLocationLabel').innerText = task.rack_location;
  document.getElementById('submitDestinationLabel').innerText = task.destination;
  
  const actualInput = document.getElementById('submitActualQty');
  actualInput.value = task.target_qty;
  actualInput.removeAttribute('max');
  const unitLabel = document.getElementById('submitUnitLabel');
  if (unitLabel) unitLabel.innerText = escapeHtml(task.material_unit || 'Qty');

  document.getElementById('submitNotes').value = '';

  App.openModal('modalSubmitTask');
}

async function handleFinalTaskSubmit(e) {
  e.preventDefault();
  if (!activeSubmittingTask) return;

  const task_id = document.getElementById('submitTaskId').value;
  const actual_qty = parseInt(document.getElementById('submitActualQty').value);
  const completion_notes = document.getElementById('submitNotes').value.trim();

  if (actual_qty <= 0) {
    App.toast('Jumlah ambil harus lebih dari 0', 'warning');
    return;
  }

  const btn = document.getElementById('btnFinalSubmit');
  btn.disabled = true;
  btn.innerText = 'Menyimpan...';

  const res = await App.fetchJson('../api/tasks.php?action=submit_complete', {
    method: 'POST',
    body: JSON.stringify({
      task_id,
      actual_qty,
      completion_notes
    })
  });

  btn.disabled = false;
  btn.innerText = 'Konfirmasi Selesai & Kirim';

  if (res.success) {
    App.toast(res.message, 'success', 'Tugas Selesai');
    App.closeModal('modalSubmitTask');
    loadOperatorTasks();
    loadOperatorStock();
  } else {
    App.toast(res.message, 'error');
  }
}

// 5. STOCK CHECKER
async function loadOperatorStock() {
  const search = document.getElementById('opStockSearch')?.value || '';
  const res = await App.fetchJson(`../api/materials.php?action=list&search=${encodeURIComponent(search)}`);
  if (res.success) {
    allStock = res.data;
    renderOperatorStockList();
  }
}

function renderOperatorStockList() {
  const container = document.getElementById('opStockListContainer');
  if (!container) return;

  if (allStock.length === 0) {
    container.innerHTML = '<div class="p-6 bg-white rounded-3xl text-center text-xs text-slate-400 border border-slate-200">Tidak ada material yang cocok.</div>';
    return;
  }

  container.innerHTML = allStock.map(m => {
    let stockColor = 'text-slate-900';
    let badge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-800 border border-emerald-200">Aman</span>';

    if (m.current_stock <= 0) {
      stockColor = 'text-rose-600';
      badge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-50 text-rose-800 border border-rose-200 animate-pulse">Habis</span>';
    } else if (m.current_stock <= m.min_stock) {
      stockColor = 'text-amber-600';
      badge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-50 text-amber-900 border border-amber-300">Menipis</span>';
    }

    return `
      <div class="bg-white p-3.5 rounded-2xl border border-slate-200/90 shadow-xs hover:shadow-md transition-all flex items-center justify-between gap-3">
        <div class="space-y-1 min-w-0 flex-1">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="font-mono font-black text-[10px] text-sky-800 bg-sky-50 px-2 py-0.5 rounded-md border border-sky-200">${escapeHtml(m.code)}</span>
            ${badge}
          </div>
          <h4 class="font-black text-xs text-slate-900 leading-snug truncate">${escapeHtml(m.name)}</h4>
          <p class="text-[11px] text-slate-500 font-semibold flex items-center gap-1">
            <span class="material-symbols-outlined text-rose-500 text-[14px]">location_on</span>
            <span>Rak: <b class="text-slate-800">${escapeHtml(m.rack_location)}</b></span>
          </p>
        </div>

        <div class="text-right flex-shrink-0 bg-slate-50 p-2.5 rounded-2xl border border-slate-200">
          <p class="text-[9px] uppercase font-bold text-slate-400">Sisa Stok</p>
          <p class="text-base font-black ${stockColor} leading-tight">${App.formatNumber(m.current_stock)}</p>
          <span class="text-[9px] font-semibold text-slate-400">${escapeHtml(m.unit || 'Pcs')}</span>
        </div>
      </div>
    `;
  }).join('');
}

// 6. COMPLETED TASKS HISTORY
function renderCompletedHistory() {
  const container = document.getElementById('opHistoryContainer');
  if (!container) return;

  const completed = myTasks.filter(t => t.status === 'COMPLETED');
  if (completed.length === 0) {
    container.innerHTML = '<div class="p-6 bg-white rounded-3xl text-center text-xs text-slate-400 border border-slate-200">Belum ada riwayat tugas yang selesai hari ini.</div>';
    return;
  }

  container.innerHTML = completed.map(t => `
    <div class="bg-white p-4 rounded-3xl border border-slate-200 shadow-xs space-y-2 text-xs">
      <div class="flex items-center justify-between">
        <span class="font-mono font-black text-xs text-blue-700 bg-blue-50 px-2 py-0.5 rounded-lg border border-blue-200">${escapeHtml(t.task_no)}</span>
        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-800 border border-emerald-200 flex items-center gap-1">
          <span class="material-symbols-outlined text-[13px]">check_circle</span>
          <span>SELESAI</span>
        </span>
      </div>
      <h4 class="font-black text-slate-900 text-xs">${escapeHtml(t.material_name)}</h4>
      <div class="flex justify-between items-center text-slate-600 bg-slate-50 p-2.5 rounded-xl text-[11px] border border-slate-100">
        <span>Tujuan: <b class="text-slate-900">${escapeHtml(t.destination)}</b></span>
        <span>Diambil: <b class="text-emerald-700 font-black text-xs">${App.formatNumber(t.actual_qty)}</b></span>
      </div>
      ${t.completion_notes ? `<p class="text-[11px] text-slate-500 italic bg-slate-50/50 p-2 rounded-lg border border-slate-100">Catatan: ${escapeHtml(t.completion_notes)}</p>` : ''}
      <p class="text-[10px] text-slate-400 text-right font-mono">${App.formatDate(t.completed_at)}</p>
    </div>
  `).join('');
}

// 5. INBOUND GOODS RECEIPT DRAFT (MULTI-PRODUCT)
async function populateOpInboundMaterials() {
  if (allStock.length === 0) {
    const res = await App.fetchJson('../api/materials.php?action=list');
    if (res.success && res.data) {
      allStock = res.data;
    }
  }

  const select = document.getElementById('opInboundMaterialSelect');
  if (!select) return;

  const currentVal = select.value;
  select.innerHTML = '<option value="">-- Pilih Material Packaging --</option>' +
    allStock.map(m => `
      <option value="${m.id}" data-code="${escapeHtml(m.code)}" data-name="${escapeHtml(m.name)}" data-stock="${m.current_stock}" data-rack="${escapeHtml(m.rack_location)}">
        ${escapeHtml(m.name)} (Stok: ${App.formatNumber(m.current_stock)})
      </option>
    `).join('');

  if (currentVal) select.value = currentVal;
  App.syncSearchableSelect(select);
}

function updateOpInboundStockBadge() {
  const select = document.getElementById('opInboundMaterialSelect');
  const badge = document.getElementById('opInboundStockBadge');
  const locInp = document.getElementById('opInboundLocation');
  if (!select || !badge) return;

  const opt = select.options[select.selectedIndex];
  if (opt && opt.value) {
    const stock = opt.getAttribute('data-stock');
    const rack = opt.getAttribute('data-rack') || 'Gudang Utama';
    badge.innerHTML = `Sisa Stok Saat Ini: <b class="${stock <= 0 ? 'text-rose-600' : 'text-emerald-700'}">${App.formatNumber(stock)}</b> &bull; Lokasi Rak: <b>${rack}</b>`;
    if (locInp) locInp.value = rack;
  } else {
    badge.innerHTML = '';
  }
}

let opInboundDraftStartTime = null;

function addInboundDraftItem() {
  const select = document.getElementById('opInboundMaterialSelect');
  const qtyInp = document.getElementById('opInboundQty');
  const locInp = document.getElementById('opInboundLocation');

  if (!select || !qtyInp) return;

  const materialId = parseInt(select.value);
  const qty = parseInt(qtyInp.value);

  if (!materialId || materialId <= 0) {
    App.toast('Silakan pilih material packaging terlebih dahulu.', 'warning');
    return;
  }

  if (!qty || qty <= 0) {
    App.toast('Jumlah penerimaan harus lebih dari 0.', 'warning');
    return;
  }

  const opt = select.options[select.selectedIndex];
  const itemCode = opt.getAttribute('data-code') || '';
  const itemName = opt.getAttribute('data-name') || '';
  const itemRack = (locInp && locInp.value.trim()) ? locInp.value.trim() : (opt.getAttribute('data-rack') || 'Gudang Utama');

  if (!opInboundDraftStartTime) {
    opInboundDraftStartTime = new Date().toISOString();
  }

  const existingIdx = opInboundDraft.findIndex(i => i.material_id === materialId);
  if (existingIdx >= 0) {
    opInboundDraft[existingIdx].qty += qty;
    opInboundDraft[existingIdx].rack = itemRack;
  } else {
    opInboundDraft.push({
      material_id: materialId,
      code: itemCode,
      name: itemName,
      rack: itemRack,
      qty: qty
    });
  }

  qtyInp.value = '';
  select.value = '';
  App.syncSearchableSelect(select);
  updateOpInboundStockBadge();
  renderInboundDraftList();
  App.toast(`Item ditambahkan ke draft penerimaan.`, 'info');
}

function removeInboundDraftItem(index) {
  if (index >= 0 && index < opInboundDraft.length) {
    opInboundDraft.splice(index, 1);
    renderInboundDraftList();
  }
}

function clearInboundDraft() {
  if (opInboundDraft.length === 0) return;
  opInboundDraft = [];
  opInboundDraftStartTime = null;
  renderInboundDraftList();
  App.toast('Draft penerimaan telah dikosongkan', 'info');
}

function renderInboundDraftList() {
  const container = document.getElementById('opInboundDraftList');
  const countEl = document.getElementById('opDraftCount');
  const summaryBox = document.getElementById('opDraftSummaryBox');
  const totalQtyEl = document.getElementById('opDraftTotalQty');

  if (countEl) countEl.innerText = opInboundDraft.length;

  if (!container) return;

  if (opInboundDraft.length === 0) {
    container.innerHTML = `
      <div class="p-4 bg-slate-50 border border-dashed border-slate-200 rounded-lg text-center text-xs text-slate-400">
        Draft masih kosong. Pilih material dan klik <b>"+ Masukkan ke Draft"</b> di atas.
      </div>
    `;
    if (summaryBox) summaryBox.classList.add('hidden');
    return;
  }

  let totalQty = 0;

  container.innerHTML = opInboundDraft.map((item, idx) => {
    totalQty += item.qty;

    return `
      <div class="p-2.5 bg-white border border-slate-200 rounded-lg shadow-sm flex items-center justify-between gap-2 text-xs">
        <div class="space-y-0.5 flex-1 min-w-0">
          <div class="flex items-center gap-1.5">
            <span class="text-[10px] text-slate-500 font-medium">Lokasi: <b class="text-slate-800">${escapeHtml(item.rack)}</b></span>
          </div>
          <p class="font-bold text-slate-900 leading-snug">${escapeHtml(item.name)}</p>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
          <div class="text-right">
            <span class="font-extrabold text-sm text-emerald-800">+${App.formatNumber(item.qty)}</span>
          </div>
          <button type="button" onclick="removeInboundDraftItem(${idx})" title="Hapus dari draft" class="p-1 text-slate-400 hover:text-rose-600 rounded">
            <span class="material-symbols-outlined text-[18px]">delete</span>
          </button>
        </div>
      </div>
    `;
  }).join('');

  if (summaryBox && totalQtyEl) {
    summaryBox.classList.remove('hidden');
    totalQtyEl.innerText = `${App.formatNumber(totalQty)} (${opInboundDraft.length} Item)`;
  }
}

async function handleInboundDraftSubmit() {
  const notes = document.getElementById('opInboundNotes')?.value.trim() || 'Penerimaan Lapangan Operator';

  if (opInboundDraft.length === 0) {
    App.toast('Keranjang draft penerimaan masih kosong. Tambahkan minimal 1 packaging material.', 'warning');
    return;
  }

  const btn = document.getElementById('btnSubmitInboundDraft');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span> Menyimpan Penerimaan & Update Stok...';

  const started_at = opInboundDraftStartTime || new Date().toISOString();

  const res = await App.fetchJson('../api/inbound.php?action=batch_create', {
    method: 'POST',
    body: JSON.stringify({
      po_number: '-',
      supplier: '-',
      started_at,
      notes,
      items: opInboundDraft.map(d => ({
        material_id: d.material_id,
        qty: d.qty,
        notes: d.notes
      }))
    })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check_circle</span><span>Submit & Update Stok Gudang</span>';

  if (res.success) {
    App.toast(res.message, 'success', 'Penerimaan Berhasil');
    
    // Clear form & draft
    const notesEl = document.getElementById('opInboundNotes');
    if (notesEl) notesEl.value = '';
    opInboundDraft = [];
    opInboundDraftStartTime = null;
    renderInboundDraftList();

    // Reload operator stock and stats
    loadOperatorStock();
    loadOperatorStats();
  } else {
    App.toast(res.message || 'Gagal menyimpan penerimaan draft', 'error');
  }
}

// HTML escape helper
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
// 5. OPERATOR DYNAMIC COUNTING MODULE (TASK DRIVEN WITH RACK SCAN)
// =========================================================================
let myDynamicTasks = [];

async function loadOperatorDynamicTasks(silent = false) {
  const res = await App.fetchJson('../api/opnames.php?action=operator_dynamic_tasks');
  if (res.success) {
    myDynamicTasks = res.tasks || [];
    renderOperatorDynamicTasksList();

    const pendingCount = myDynamicTasks.filter(t => t.stage_status === 'PENDING').length;
    const badgeDyn = document.getElementById('homeBadgeDynamicCount');
    const statDyn = document.getElementById('homeStatDynamic');
    const navDotDyn = document.getElementById('navDotDynamic');

    if (statDyn) statDyn.innerText = App.formatNumber(pendingCount);
    if (badgeDyn) {
      if (pendingCount > 0) {
        badgeDyn.innerText = `${pendingCount} Task`;
        badgeDyn.classList.remove('hidden');
      } else {
        badgeDyn.classList.add('hidden');
      }
    }
    if (navDotDyn) {
      if (pendingCount > 0) navDotDyn.classList.remove('hidden');
      else navDotDyn.classList.add('hidden');
    }

    if (!silent) App.toast('Daftar task Dynamic Count diperbarui', 'info');
  }
}

function renderOperatorDynamicTasksList() {
  const container = document.getElementById('opDynamicTasksContainer');
  if (!container) return;

  if (myDynamicTasks.length === 0) {
    container.innerHTML = `
      <div class="bg-white rounded-2xl p-6 text-center border border-slate-200 shadow-sm space-y-2.5">
        <div class="w-12 h-12 rounded-full bg-indigo-50 text-indigo-600 mx-auto flex items-center justify-center">
          <span class="material-symbols-outlined text-[28px]">checklist</span>
        </div>
        <div>
          <h4 class="font-bold text-slate-800 text-sm">Tidak Ada Tugas Dynamic Counting</h4>
          <p class="text-xs text-slate-500 mt-0.5">Admin belum menugaskan SKU packaging untuk dihitung.</p>
        </div>
        <button onclick="loadOperatorDynamicTasks()" class="px-3.5 py-1.5 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold hover:bg-slate-200 transition-colors inline-flex items-center gap-1">
          <span class="material-symbols-outlined text-[16px]">refresh</span>
          <span>Refresh Task</span>
        </button>
      </div>
    `;
    return;
  }

  container.innerHTML = myDynamicTasks.map(t => {
    const isCounted = t.stage_status === 'COUNTED' && t.count_qty !== null;
    const isRecount = t.stage_number > 1;

    const statusBadge = isCounted
      ? '<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[12px]">check_circle</span>Selesai</span>'
      : '<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-50 text-amber-900 border border-amber-300 inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>Perlu Dihitung</span>';

    return `
      <div class="bg-white rounded-2xl p-4 border ${isCounted ? 'border-slate-200' : 'border-indigo-300 ring-1 ring-indigo-400'} shadow-sm space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="font-mono font-bold text-xs text-slate-800 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
              #${escapeHtml(t.opname_no)}
            </span>
            <span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-indigo-100 text-indigo-900 border border-indigo-200">Dynamic Count</span>
            ${isRecount ? `<span class="px-2 py-0.5 rounded text-[10px] font-extrabold bg-purple-100 text-purple-900 border border-purple-200">${escapeHtml(t.stage_label)}</span>` : ''}
          </div>
          <div>${statusBadge}</div>
        </div>

        <div>
          <h3 class="font-bold text-slate-900 text-sm leading-snug">${escapeHtml(t.material_name)}</h3>
          <div class="flex items-center gap-2 mt-0.5">
            <span class="text-[11px] font-mono font-bold text-indigo-700">${escapeHtml(t.material_code)}</span>
            <span class="text-[10px] text-slate-400">&bull; ${escapeHtml(t.material_category || '-')}</span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-100 text-xs">
          <div>
            <p class="text-[10px] font-semibold uppercase text-slate-400">Lokasi Rak Master</p>
            <p class="text-xs font-black text-slate-800 flex items-center gap-1 mt-0.5">
              <span class="material-symbols-outlined text-rose-600 text-[14px]">location_on</span>
              <span>${escapeHtml(t.rack_location || '-')}</span>
            </p>
          </div>
          <div class="text-right">
            <p class="text-[10px] font-semibold uppercase text-slate-400">Satuan (UOM)</p>
            <p class="text-xs font-black text-indigo-800 mt-0.5">
              ${escapeHtml(t.material_unit || 'Pcs')}
            </p>
          </div>
        </div>

        ${isCounted ? `
          <div class="p-2.5 rounded-xl bg-indigo-50 border border-indigo-200 flex items-center justify-between text-xs">
            <div>
              <span class="text-[10px] font-bold uppercase text-slate-500">Hasil Hitung Fisik:</span>
              <p class="text-sm font-black font-mono text-indigo-900">${App.formatNumber(t.count_qty)} ${escapeHtml(t.material_unit || 'Pcs')}</p>
              ${t.scanned_rack ? `<p class="text-[10px] text-slate-600 font-semibold mt-0.5">Rak Scanned: <b>${escapeHtml(t.scanned_rack)}</b></p>` : ''}
            </div>
            <div class="text-right text-[10px] text-slate-500 font-mono">
              <span>Waktu: ${t.counted_at ? t.counted_at.split(' ')[1] : '-'}</span>
            </div>
          </div>
        ` : ''}

        <div class="pt-1">
          <button type="button" onclick="openDynamicCountModal(${t.stage_id})" 
            class="w-full py-2.5 ${isCounted ? 'bg-slate-800 hover:bg-slate-900 text-white' : 'bg-indigo-600 hover:bg-indigo-700 text-white'} font-bold text-xs rounded-xl shadow-sm transition-colors flex items-center justify-center gap-1.5 active:scale-98">
            <span class="material-symbols-outlined text-[17px]">${isCounted ? 'edit_note' : 'qr_code_scanner'}</span>
            <span>${isCounted ? 'Koreksi Hitungan / Rak' : 'Scan Rak & Input Hasil Hitung'}</span>
          </button>
        </div>
      </div>
    `;
  }).join('');
}

function openDynamicCountModal(stageId) {
  const task = myDynamicTasks.find(t => t.stage_id == stageId);
  if (!task) return;

  document.getElementById('dynModalItemId').value = task.item_id;
  document.getElementById('dynModalStageId').value = task.stage_id;
  document.getElementById('dynModalItemCode').innerText = task.material_code;
  document.getElementById('dynModalItemName').innerText = task.material_name;
  document.getElementById('dynModalMasterRack').innerText = `Rak Master: ${task.rack_location || '-'}`;
  document.getElementById('dynModalUnitLabel').innerText = task.material_unit || 'Pcs';
  document.getElementById('dynModalSessionSubtitle').innerText = `Sesi #${task.opname_no} - ${task.opname_title}`;

  const rackInput = document.getElementById('dynModalScannedRack');
  if (rackInput) rackInput.value = task.scanned_rack || task.rack_location || '';

  const qtyInput = document.getElementById('dynModalCountQty');
  if (qtyInput) qtyInput.value = task.count_qty !== null ? task.count_qty : '';

  const notesInput = document.getElementById('dynModalNotes');
  if (notesInput) notesInput.value = task.operator_notes || '';

  App.openModal('modalSubmitDynamicCount');
  if (qtyInput) setTimeout(() => qtyInput.focus(), 150);
}

async function handleDynamicCountSubmit(e) {
  e.preventDefault();
  const item_id = document.getElementById('dynModalItemId').value;
  const stage_id = document.getElementById('dynModalStageId').value;
  const scanned_rack = document.getElementById('dynModalScannedRack').value.trim();
  const count_qty = document.getElementById('dynModalCountQty').value;
  const notes = document.getElementById('dynModalNotes').value.trim();

  const btn = document.getElementById('btnSubmitDynamicCount');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Menyimpan...</span>';

  const res = await App.fetchJson('../api/opnames.php?action=submit_dynamic_count', {
    method: 'POST',
    body: JSON.stringify({
      item_id,
      stage_id,
      scanned_rack,
      count_qty,
      notes
    })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">send</span><span>Kirim Hasil Dynamic Count</span>';

  if (res.success) {
    App.toast(res.message, 'success', 'Dynamic Count Berhasil Disimpan');
    App.closeModal('modalSubmitDynamicCount');
    loadOperatorDynamicTasks(true);
    loadOperatorStats(true);
  } else {
    App.toast(res.message || 'Gagal menyimpan Dynamic Count', 'error');
  }
}

// =========================================================================
// 6. OPERATOR STOCK OPNAME MODULE (PURE BLANK COUNT & RECOUNT MATRIX)
// =========================================================================
let myBlankCounts = [];
let myRecountTasks = [];

async function populateBlankMaterials() {
  if (!Array.isArray(allStock) || allStock.length === 0) {
    const res = await App.fetchJson('../api/materials.php?action=list');
    if (res.success && res.data) {
      allStock = res.data;
    }
  }

  const select = document.getElementById('blankMaterialSelect');
  if (!select) return;

  const currentVal = select.value;
  select.innerHTML = '<option value="">-- Ketik / Pilih Material Packaging --</option>' +
    (allStock || []).map(m => `
      <option value="${m.id}" data-code="${escapeHtml(m.code)}" data-name="${escapeHtml(m.name)}" data-unit="${escapeHtml(m.unit || 'Pcs')}" data-rack="${escapeHtml(m.rack_location || '')}">
        ${escapeHtml(m.code)} - ${escapeHtml(m.name)} (${escapeHtml(m.unit || 'Pcs')})
      </option>
    `).join('');

  if (currentVal) select.value = currentVal;
  App.syncSearchableSelect(select);
  handleBlankMaterialChange();
}

function handleBlankMaterialChange() {
  const select = document.getElementById('blankMaterialSelect');
  const rackInput = document.getElementById('blankRackLocation');
  const unitLabel = document.getElementById('blankUnitLabel');
  if (!select) return;

  const val = select.value;
  if (!val) {
    if (unitLabel) unitLabel.innerText = 'Pcs';
    return;
  }

  let unit = 'Pcs';
  let rack = '';

  const mat = (Array.isArray(allStock) && allStock.length > 0) ? allStock.find(m => String(m.id) === String(val)) : null;
  if (mat) {
    unit = mat.unit || 'Pcs';
    rack = mat.rack_location || '';
  } else {
    const opt = select.options[select.selectedIndex];
    if (opt && opt.value) {
      unit = opt.getAttribute('data-unit') || 'Pcs';
      rack = opt.getAttribute('data-rack') || '';
    }
  }

  if (unitLabel) unitLabel.innerText = unit;
  if (rackInput && (!rackInput.value || rackInput.value === '')) {
    rackInput.value = rack;
  }
}

async function handleBlankCountSubmit(e) {
  e.preventDefault();
  const material_id = document.getElementById('blankMaterialSelect').value;
  const scanned_rack = document.getElementById('blankRackLocation').value.trim();
  const count_qty = document.getElementById('blankCountQty').value;
  const notes = document.getElementById('blankNotes').value.trim();

  if (!material_id) {
    App.toast('Pilih material packaging terlebih dahulu', 'warning');
    return;
  }

  const btn = document.getElementById('btnSubmitBlankCount');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Menyimpan...</span>';

  const res = await App.fetchJson('../api/opnames.php?action=submit_blank_count', {
    method: 'POST',
    body: JSON.stringify({
      material_id,
      scanned_rack,
      count_qty,
      notes
    })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">add_circle</span><span>+ Simpan Hasil Hitung Fisik</span>';

  if (res.success) {
    App.toast(res.message, 'success', 'Hasil Hitung Tersimpan ke Stock Opname');
    
    // Clear form inputs
    const select = document.getElementById('blankMaterialSelect');
    if (select) {
      select.value = '';
      App.syncSearchableSelect(select);
    }
    const unitLabel = document.getElementById('blankUnitLabel');
    if (unitLabel) unitLabel.innerText = 'Pcs';
    const rackInput = document.getElementById('blankRackLocation');
    if (rackInput) rackInput.value = '';
    document.getElementById('blankCountQty').value = '';
    document.getElementById('blankNotes').value = '';

    loadOperatorBlankCounts(true);
    loadOperatorStats(true);
  } else {
    App.toast(res.message || 'Gagal menyimpan hasil hitung', 'error');
  }
}

async function loadOperatorBlankCounts(silent = false) {
  const res = await App.fetchJson('../api/opnames.php?action=my_blank_counts');
  if (res.success) {
    myBlankCounts = res.data || [];
    renderOperatorBlankCountsList();

    const badge = document.getElementById('opMyBlankCountBadge');
    if (badge) badge.innerText = myBlankCounts.length;
  }
}

function renderOperatorBlankCountsList() {
  const container = document.getElementById('opBlankCountHistoryContainer');
  if (!container) return;

  if (myBlankCounts.length === 0) {
    container.innerHTML = `
      <div class="p-4 bg-slate-50 border border-dashed border-slate-200 rounded-xl text-center text-xs text-slate-400">
        Belum ada hasil hitung yang disubmit hari ini.
      </div>
    `;
    return;
  }

  container.innerHTML = myBlankCounts.map(item => `
    <div class="bg-slate-50 p-3 rounded-xl border border-slate-200 flex items-center justify-between gap-2 text-xs">
      <div class="space-y-0.5 flex-1 min-w-0">
        <div class="flex items-center gap-1.5">
          <span class="font-mono font-bold text-[10px] text-emerald-800 bg-emerald-100 px-1.5 py-0.2 rounded">${escapeHtml(item.material_code)}</span>
          <span class="text-[10px] text-slate-500">Rak: <b>${escapeHtml(item.scanned_rack || '-')}</b></span>
        </div>
        <p class="font-bold text-slate-900 leading-snug truncate">${escapeHtml(item.material_name)}</p>
        <div class="text-[10px] text-slate-400 font-mono">Pukul: ${item.counted_at ? item.counted_at.split(' ')[1] : '-'}</div>
      </div>

      <div class="flex items-center gap-2 flex-shrink-0">
        <div class="text-right">
          <span class="font-black text-sm text-emerald-900">${App.formatNumber(item.count_qty)}</span>
          <span class="text-[10px] text-slate-500 block">${escapeHtml(item.material_unit || 'Pcs')}</span>
        </div>
        <button type="button" onclick="deleteBlankCountItem(${item.stage_id})" title="Hapus Hitungan Ini" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors">
          <span class="material-symbols-outlined text-[18px]">delete</span>
        </button>
      </div>
    </div>
  `).join('');
}

async function deleteBlankCountItem(stageId) {
  const res = await App.fetchJson('../api/opnames.php?action=delete_blank_count', {
    method: 'POST',
    body: JSON.stringify({ stage_id: stageId })
  });

  if (res.success) {
    App.toast(res.message, 'success');
    loadOperatorBlankCounts(true);
  } else {
    App.toast(res.message || 'Gagal menghapus hasil hitung', 'error');
  }
}

// -------------------------------------------------------------------------
// RECOUNT TASKS FOR DISCREPANCY ITEMS (DIASSIGN ADMIN UNTUK DIFFERENCE != 0)
// -------------------------------------------------------------------------
let currentOpnameSubTab = '1st';

function switchOpnameSubTab(subTab) {
  currentOpnameSubTab = subTab;
  const tab1st = document.getElementById('opname-subtab-1st');
  const tabRecount = document.getElementById('opname-subtab-recount');
  const btn1st = document.getElementById('btnSubTabOpname1st');
  const btnRecount = document.getElementById('btnSubTabOpnameRecount');

  if (subTab === '1st') {
    if (tab1st) tab1st.classList.remove('hidden');
    if (tabRecount) tabRecount.classList.add('hidden');

    if (btn1st) {
      btn1st.className = 'py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 bg-emerald-600 text-white shadow-xs transition-all';
    }
    if (btnRecount) {
      btnRecount.className = 'py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900 transition-all relative';
    }
  } else {
    if (tab1st) tab1st.classList.add('hidden');
    if (tabRecount) tabRecount.classList.remove('hidden');

    if (btn1st) {
      btn1st.className = 'py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 text-slate-600 hover:text-slate-900 transition-all';
    }
    if (btnRecount) {
      btnRecount.className = 'py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 bg-purple-600 text-white shadow-xs transition-all relative';
    }
    loadOperatorRecountTasks();
  }
}

async function loadOperatorRecountTasks(silent = false) {
  const res = await App.fetchJson('../api/opnames.php?action=operator_recount_tasks');
  if (res.success) {
    myRecountTasks = res.tasks || [];
    renderOperatorRecountTasksList();

    const pendingRecounts = myRecountTasks.filter(t => t.stage_status === 'PENDING').length;
    const badgeRecount = document.getElementById('opRecountCountBadge');
    const subTabRecountBadge = document.getElementById('subTabRecountBadge');
    const homeBadgeOpname = document.getElementById('homeBadgeOpname');
    const homeStatOpname = document.getElementById('homeStatOpname');

    if (badgeRecount) badgeRecount.innerText = `${pendingRecounts} Tugas`;
    if (subTabRecountBadge) {
      if (pendingRecounts > 0) {
        subTabRecountBadge.innerText = pendingRecounts;
        subTabRecountBadge.classList.remove('hidden');
      } else {
        subTabRecountBadge.classList.add('hidden');
      }
    }
    if (homeStatOpname && pendingRecounts > 0) homeStatOpname.innerText = App.formatNumber(pendingRecounts);
    if (homeBadgeOpname) {
      if (pendingRecounts > 0) {
        homeBadgeOpname.innerText = `${pendingRecounts} Recount`;
        homeBadgeOpname.className = 'absolute -top-1.5 -right-1.5 px-1.5 py-0.5 rounded-full bg-purple-600 text-white font-black text-[9px] shadow-xs animate-pulse leading-none';
        homeBadgeOpname.classList.remove('hidden');
      } else {
        homeBadgeOpname.innerText = 'Aktif';
        homeBadgeOpname.className = 'absolute -top-1.5 -right-1.5 px-1.5 py-0.5 rounded-full bg-emerald-600 text-white font-black text-[9px] shadow-xs leading-none';
      }
    }
  }
}

function renderOperatorRecountTasksList() {
  const container = document.getElementById('opRecountTasksContainer');
  if (!container) return;

  if (myRecountTasks.length === 0) {
    container.innerHTML = `
      <div class="bg-white rounded-2xl p-6 text-center border border-slate-200 shadow-sm space-y-2">
        <div class="w-10 h-10 rounded-full bg-purple-50 text-purple-600 mx-auto flex items-center justify-center">
          <span class="material-symbols-outlined text-[24px]">task_alt</span>
        </div>
        <h4 class="font-bold text-slate-800 text-xs">Tidak Ada Tugas Recount</h4>
        <p class="text-[11px] text-slate-500">Semua item fisik saat ini balance / belum ada penugasan recount dari Admin.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = myRecountTasks.map(t => {
    const isCounted = t.stage_status === 'COUNTED' && t.count_qty !== null;

    return `
      <div class="bg-purple-50/70 border-2 border-purple-300 rounded-2xl p-3.5 shadow-sm space-y-2.5 text-xs">
        <div class="flex items-center justify-between">
          <span class="px-2 py-0.5 rounded-full bg-purple-200 text-purple-900 font-extrabold text-[10px] flex items-center gap-1">
            <span class="material-symbols-outlined text-[13px]">replay</span>
            <span>${escapeHtml(t.stage_label)} (Selisih)</span>
          </span>
          <span class="font-mono text-purple-800 font-bold text-[10px]">#${escapeHtml(t.opname_no)}</span>
        </div>

        <div>
          <h4 class="font-black text-slate-900 text-xs">${escapeHtml(t.material_name)}</h4>
          <div class="flex items-center gap-2 text-[10px] text-slate-500 mt-0.5">
            <span class="font-mono font-bold text-purple-800">${escapeHtml(t.material_code)}</span>
            <span>&bull; Rak: <b>${escapeHtml(t.rack_location || '-')}</b></span>
          </div>
        </div>

        ${isCounted ? `
          <div class="p-2 rounded-xl bg-purple-100 border border-purple-200 flex items-center justify-between">
            <span class="text-[10px] font-bold text-purple-900">Hasil Recount Anda:</span>
            <span class="font-black font-mono text-purple-900 text-xs">${App.formatNumber(t.count_qty)} ${escapeHtml(t.material_unit || 'Pcs')}</span>
          </div>
        ` : ''}

        <button type="button" onclick="openRecountModal(${t.stage_id})" 
          class="w-full py-2 bg-purple-600 hover:bg-purple-700 active:scale-95 text-white font-bold text-xs rounded-xl shadow-xs transition-all flex items-center justify-center gap-1">
          <span class="material-symbols-outlined text-[16px]">${isCounted ? 'edit_note' : 'draw'}</span>
          <span>${isCounted ? 'Koreksi Hasil Recount' : 'Input Hasil Recount'}</span>
        </button>
      </div>
    `;
  }).join('');
}

function openRecountModal(stageId) {
  const task = myRecountTasks.find(t => t.stage_id == stageId);
  if (!task) return;

  document.getElementById('recountModalItemId').value = task.item_id;
  document.getElementById('recountModalStageId').value = task.stage_id;
  document.getElementById('recountModalStageNumber').value = task.stage_number;

  document.getElementById('recountModalItemCode').innerText = task.material_code;
  document.getElementById('recountModalItemName').innerText = task.material_name;
  document.getElementById('recountModalRack').innerText = `Rak: ${task.rack_location || '-'}`;
  document.getElementById('recountModalUnitLabel').innerText = task.material_unit || 'Pcs';
  document.getElementById('recountModalSubtitle').innerText = `Sesi #${task.opname_no} - Penugasan ${task.stage_label}`;

  const badge = document.getElementById('recountModalStageBadge');
  if (badge) badge.innerHTML = `<span class="px-2 py-0.5 rounded bg-purple-100 text-purple-900 font-extrabold text-[10px]">Tugas: ${escapeHtml(task.stage_label)}</span>`;

  const qtyInput = document.getElementById('recountModalCountQty');
  if (qtyInput) qtyInput.value = task.count_qty !== null ? task.count_qty : '';

  const notesInput = document.getElementById('recountModalNotes');
  if (notesInput) notesInput.value = task.operator_notes || '';

  App.openModal('modalSubmitRecount');
  if (qtyInput) setTimeout(() => qtyInput.focus(), 150);
}

async function handleRecountSubmit(e) {
  e.preventDefault();
  const item_id = document.getElementById('recountModalItemId').value;
  const stage_id = document.getElementById('recountModalStageId').value;
  const stage_number = document.getElementById('recountModalStageNumber').value;
  const count_qty = document.getElementById('recountModalCountQty').value;
  const notes = document.getElementById('recountModalNotes').value.trim();

  const btn = document.getElementById('btnSubmitRecountCount');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Menyimpan...</span>';

  const res = await App.fetchJson('../api/opnames.php?action=submit_recount', {
    method: 'POST',
    body: JSON.stringify({
      item_id,
      stage_id,
      stage_number,
      count_qty,
      notes
    })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">send</span><span>Simpan Hasil Recount</span>';

  if (res.success) {
    App.toast(res.message, 'success', 'Recount Berhasil Disimpan');
    App.closeModal('modalSubmitRecount');
    loadOperatorRecountTasks(true);
    switchOpnameSubTab('recount');
  } else {
    App.toast(res.message || 'Gagal menyimpan recount', 'error');
  }
}

// =========================================================================
// HANDOVER SHIFT MODULE
// =========================================================================
let myHandoversList = [];
let handoverSelectedFiles = [];

function escapeHtml(text) {
  if (text === null || text === undefined) return '';
  return text.toString()
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function toggleHandoverForm() {
  const formContainer = document.getElementById('handoverFormContainer');
  const btnText = document.getElementById('btnToggleHandoverText');
  if (!formContainer || !btnText) return;

  const isHidden = formContainer.classList.contains('hidden');
  if (isHidden) {
    // Pre-select current active shift in handoverFromShift
    const fromShiftSel = document.getElementById('handoverFromShift');
    if (fromShiftSel && typeof CURRENT_USER_SHIFT !== 'undefined' && CURRENT_USER_SHIFT) {
      for (let opt of fromShiftSel.options) {
        if (opt.value === CURRENT_USER_SHIFT || CURRENT_USER_SHIFT.toLowerCase().includes(opt.value.toLowerCase()) || opt.value.toLowerCase().includes(CURRENT_USER_SHIFT.toLowerCase())) {
          fromShiftSel.value = opt.value;
          break;
        }
      }
    }
    formContainer.classList.remove('hidden');
    btnText.innerText = 'Tutup Form Handover';
  } else {
    formContainer.classList.add('hidden');
    btnText.innerText = 'Buat Handover Baru';
  }
}

function previewHandoverPhoto(e) {
  const files = e.target.files;
  if (!files || files.length === 0) return;
  
  for (let i = 0; i < files.length; i++) {
    handoverSelectedFiles.push(files[i]);
  }
  
  // Clear file input so the same file or subsequent selections work properly
  e.target.value = '';
  
  renderHandoverPreviews();
}

function renderHandoverPreviews() {
  const label = document.getElementById('handoverPhotoLabel');
  const previewContainer = document.getElementById('handoverPhotoPreviewContainer');
  const clearBtn = document.getElementById('btnClearHandoverPhotos');
  
  if (label) {
    label.innerText = handoverSelectedFiles.length > 0 
      ? `${handoverSelectedFiles.length} Foto terpilih` 
      : 'Belum ada foto';
  }
  
  if (!previewContainer) return;
  
  if (handoverSelectedFiles.length === 0) {
    previewContainer.innerHTML = '';
    previewContainer.classList.add('hidden');
    if (clearBtn) clearBtn.classList.add('hidden');
    return;
  }
  
  previewContainer.innerHTML = '';
  previewContainer.className = 'grid grid-cols-3 gap-2 mt-2';
  previewContainer.classList.remove('hidden');
  if (clearBtn) clearBtn.classList.remove('hidden');
  
  handoverSelectedFiles.forEach((file, index) => {
    const reader = new FileReader();
    reader.onload = function(event) {
      const itemDiv = document.createElement('div');
      itemDiv.className = 'relative w-20 h-20 rounded-xl overflow-hidden border border-slate-200';
      itemDiv.innerHTML = `
        <img src="${event.target.result}" alt="Preview" class="w-full h-full object-cover">
        <button type="button" onclick="removeSelectedHandoverFile(${index})" class="absolute top-0.5 right-0.5 w-5 h-5 bg-rose-600/90 hover:bg-rose-700 text-white rounded-full flex items-center justify-center shadow-xs">
          <span class="material-symbols-outlined text-[12px] font-black">close</span>
        </button>
      `;
      previewContainer.appendChild(itemDiv);
    };
    reader.readAsDataURL(file);
  });
}

function removeSelectedHandoverFile(index) {
  handoverSelectedFiles.splice(index, 1);
  renderHandoverPreviews();
}

function clearHandoverPhoto() {
  const input = document.getElementById('handoverPhoto');
  if (input) input.value = '';
  handoverSelectedFiles = [];
  renderHandoverPreviews();
}

async function submitHandover(e) {
  e.preventDefault();
  const fromShift = document.getElementById('handoverFromShift')?.value || CURRENT_USER_SHIFT || '';
  const toShift = document.getElementById('handoverToShift').value;
  const notes = document.getElementById('handoverNotes').value.trim();
  
  if (!toShift || !notes) {
    App.toast('Mohon lengkapi shift tujuan dan catatan.', 'error');
    return;
  }
  
  const btn = document.getElementById('btnSubmitHandover');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[18px] animate-spin">progress_activity</span><span>Mengirim...</span>';
  
  const formData = new FormData();
  formData.append('from_shift', fromShift);
  formData.append('to_shift', toShift);
  formData.append('notes', notes);
  
  for (let i = 0; i < handoverSelectedFiles.length; i++) {
    formData.append('photos[]', handoverSelectedFiles[i]);
  }
  
  try {
    const response = await fetch('../api/handovers.php?action=submit', {
      method: 'POST',
      body: formData
    });
    const res = await response.json();
    
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">send</span><span>Kirim Handover</span>';
    
    if (res.success) {
      App.toast(res.message, 'success', 'Handover Berhasil');
      document.getElementById('formSubmitHandover').reset();
      clearHandoverPhoto();
      toggleHandoverForm();
      loadHandovers();
    } else {
      App.toast(res.message || 'Gagal mengirim handover', 'error');
    }
  } catch (error) {
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">send</span><span>Kirim Handover</span>';
    App.toast('Terjadi kesalahan jaringan.', 'error');
  }
}

async function loadHandovers(silent = false) {
  const container = document.getElementById('handoverListContainer');
  if (!container) {
    const res = await App.fetchJson('../api/handovers.php?action=list');
    if (res.success) {
      myHandoversList = res.data || [];
      updateHandoverBadge();
    }
    return;
  }
  
  if (!silent) {
    container.innerHTML = `
      <div class="p-6 bg-white rounded-2xl text-center text-slate-400 text-xs shadow-xs border border-slate-200">
        <span class="material-symbols-outlined text-[20px] animate-spin text-rose-600 mb-1">progress_activity</span>
        <p>Memuat daftar handover...</p>
      </div>
    `;
  }
  
  const res = await App.fetchJson('../api/handovers.php?action=list');
  if (res.success) {
    myHandoversList = res.data || [];
    renderHandoversList();
    updateHandoverBadge();
  } else {
    container.innerHTML = `
      <div class="p-6 bg-white rounded-2xl text-center text-rose-500 text-xs shadow-xs border border-rose-200">
        <p>Gagal memuat data handover.</p>
      </div>
    `;
  }
}

function updateHandoverBadge() {
  const badge = document.getElementById('homeBadgeHandover');
  if (!badge) return;
  
  const canAcceptHandover = (toShift) => {
    if (typeof CURRENT_USER_SHIFT === 'undefined' || !CURRENT_USER_SHIFT || !toShift) return false;
    const getShiftKey = (str) => {
      const match = str.match(/shift\s*([0-9a-zA-Z]+)/i);
      return match ? match[0].toLowerCase().replace(/\s+/g, '') : str.toLowerCase();
    };
    return getShiftKey(CURRENT_USER_SHIFT) === getShiftKey(toShift);
  };
  
  const pendingForMe = myHandoversList.filter(item => item.status === 'PENDING' && canAcceptHandover(item.to_shift));
  
  if (pendingForMe.length > 0) {
    badge.innerText = pendingForMe.length;
    badge.classList.remove('hidden');
  } else {
    badge.classList.add('hidden');
  }
}

function renderHandoversList() {
  const container = document.getElementById('handoverListContainer');
  if (!container) return;
  
  if (myHandoversList.length === 0) {
    container.innerHTML = `
      <div class="p-6 bg-white/70 rounded-2xl text-center text-slate-400 text-xs border border-slate-200/50">
        <span class="material-symbols-outlined text-[26px] text-slate-300 mb-1">published_with_changes</span>
        <p>Belum ada serah terima shift hari ini.</p>
      </div>
    `;
    return;
  }
  
  let html = '';
  myHandoversList.forEach(item => {
    const isPending = item.status === 'PENDING';
    const statusBadge = isPending 
      ? '<span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-800 border border-amber-200 text-[9px] font-black uppercase">PENDING</span>' 
      : `<span class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-800 border border-emerald-200 text-[9px] font-black uppercase">DONE</span>`;
    
    const shareBadge = (item.is_shared == 1)
      ? `<span class="px-1.5 py-0.5 rounded bg-teal-50 text-teal-700 border border-teal-200 text-[9px] font-bold flex items-center gap-0.5 leading-none"><span class="material-symbols-outlined text-[10px] font-bold">check</span>Shared</span>`
      : `<span class="px-1.5 py-0.5 rounded bg-slate-50 text-slate-400 border border-slate-200 text-[9px] font-bold leading-none">Unshared</span>`;

    html += `
      <!-- COMPACT CARD FOR HANDOVER -->
      <div onclick="openHandoverDetail(${item.id})" class="bg-white p-3 rounded-2xl border border-slate-200 shadow-xs hover:border-rose-300 hover:shadow-sm transition-all cursor-pointer space-y-1.5 relative group active:scale-98">
        <div class="flex items-center justify-between border-b border-slate-100 pb-1.5">
          <div class="flex items-center gap-1.5">
            <span class="material-symbols-outlined text-rose-500 text-[18px]">published_with_changes</span>
            <h4 class="font-mono font-black text-slate-800 text-[11px]">${escapeHtml(item.handover_no)}</h4>
          </div>
          <div class="flex items-center gap-1">
            ${statusBadge}
            ${shareBadge}
          </div>
        </div>
        
        <div class="text-[10px] space-y-0.5 text-slate-600">
          <div class="flex justify-between">
            <span>Dari: <b class="text-slate-900">${escapeHtml(item.from_user_name)}</b> (${escapeHtml(item.from_user_shift)})</span>
            <span class="text-slate-400 font-mono text-[9px]">${escapeHtml(item.created_at.substring(0, 16))}</span>
          </div>
          <div class="flex justify-between items-center mt-1">
            <span>Tujuan: <b class="text-indigo-700">${escapeHtml(item.to_shift)}</b></span>
            <span class="text-[9px] text-rose-500 font-bold flex items-center gap-0.5">Lihat Detail &rarr;</span>
          </div>
        </div>
      </div>
    `;
  });
  
  container.innerHTML = html;
}

function openHandoverDetail(id) {
  const item = myHandoversList.find(x => x.id == id);
  if (!item) return;

  // Set text contents
  document.getElementById('detHandoverNo').innerText = item.handover_no;
  document.getElementById('detHandoverDate').innerText = item.created_at;
  document.getElementById('detHandoverFrom').innerText = item.from_user_name;
  document.getElementById('detHandoverFromShift').innerText = item.from_user_shift;
  document.getElementById('detHandoverToShift').innerText = item.to_shift;
  document.getElementById('detHandoverNotes').innerText = item.notes;

  // Status Badge
  const isPending = item.status === 'PENDING';
  const statusBadge = isPending 
    ? '<span class="px-2 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200 text-[9px] font-black uppercase">PENDING</span>' 
    : `<span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-200 text-[9px] font-black uppercase">DONE</span>`;
  document.getElementById('detHandoverStatusBadge').innerHTML = statusBadge;

  // Share Badge
  const shareBadge = (item.is_shared == 1)
    ? `<span class="px-2 py-0.5 rounded bg-teal-100 text-teal-800 border border-teal-200 text-[9px] font-bold flex items-center gap-0.5"><span class="material-symbols-outlined text-[10px] font-bold">check</span>Shared</span>`
    : `<span class="px-2 py-0.5 rounded bg-slate-100 text-slate-500 border border-slate-200 text-[9px] font-bold">Unshared</span>`;
  document.getElementById('detHandoverShareBadge').innerHTML = shareBadge;

  // Receiver info
  const recContainer = document.getElementById('detHandoverReceivedByContainer');
  if (item.received_by_name) {
    document.getElementById('detHandoverReceivedBy').innerText = `${item.received_by_name} (${item.received_at})`;
    recContainer.classList.remove('hidden');
  } else {
    recContainer.classList.add('hidden');
  }

  // Parse photos
  let photos = [];
  if (item.photo_path) {
    if (item.photo_path.startsWith('[')) {
      try {
        photos = JSON.parse(item.photo_path);
      } catch(e) {
        photos = [item.photo_path];
      }
    } else {
      photos = [item.photo_path];
    }
  }

  // Populate Photos Grid
  const grid = document.getElementById('detHandoverPhotosGrid');
  if (photos.length > 0) {
    let html = '';
    photos.forEach((p, idx) => {
      html += `
        <div class="rounded-xl overflow-hidden border border-slate-200 h-24 bg-slate-900 flex items-center justify-center cursor-pointer hover:opacity-90 relative" onclick="openPhotoViewer('${escapeHtml(p)}', '${escapeHtml(item.handover_no)}', '${escapeHtml(item.created_at)}', '${escapeHtml(item.from_user_name)}')">
          <img src="../${escapeHtml(p)}" alt="Attachment" class="h-24 w-full object-cover">
          <div class="absolute bottom-1 right-1 bg-black/40 text-white/50 text-[7px] px-1 rounded font-mono scale-[0.9]">VIEW</div>
        </div>
      `;
    });
    grid.innerHTML = html;
    grid.parentElement.classList.remove('hidden');
  } else {
    grid.innerHTML = '';
    grid.parentElement.classList.add('hidden');
  }

  // Action buttons
  const canAcceptHandover = (toShift) => {
    if (typeof CURRENT_USER_SHIFT === 'undefined' || !CURRENT_USER_SHIFT || !toShift) return false;
    const getShiftKey = (str) => {
      const match = str.match(/shift\s*([0-9a-zA-Z]+)/i);
      return match ? match[0].toLowerCase().replace(/\s+/g, '') : str.toLowerCase();
    };
    return getShiftKey(CURRENT_USER_SHIFT) === getShiftKey(toShift);
  };

  const actions = document.getElementById('detHandoverActions');
  let actionsHtml = '';
  
  if (isPending && canAcceptHandover(item.to_shift)) {
    actionsHtml += `
      <button onclick="receiveHandoverInModal(${item.id})" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-xs font-extrabold rounded-xl transition-all flex items-center justify-center gap-1 shadow-md">
        <span class="material-symbols-outlined text-[16px]">done_all</span>
        <span>Terima & Selesaikan</span>
      </button>
    `;
  }

  actionsHtml += `
    <button onclick="shareHandover(${item.id})" class="py-2.5 px-4 bg-slate-100 hover:bg-slate-200 active:scale-95 text-slate-700 text-xs font-bold rounded-xl transition-all flex items-center justify-center gap-1 border border-slate-300">
      <span class="material-symbols-outlined text-[16px] text-slate-600">share</span>
      <span>Share</span>
    </button>
  `;

  actions.innerHTML = actionsHtml;

  App.openModal('modalHandoverDetail');
}

function openPhotoViewer(photoPath, handoverNo, date, creator) {
  const viewerImage = document.getElementById('viewerImage');
  const viewerDesc = document.getElementById('viewerImageDesc');
  const wmTopLeft = document.getElementById('wmTopLeft');
  const wmBottomLeft = document.getElementById('wmBottomLeft');
  const wmBottomRight = document.getElementById('wmBottomRight');

  if (viewerImage) viewerImage.src = `../${photoPath}`;
  if (viewerDesc) viewerDesc.innerText = `Foto lampiran untuk berkas ${handoverNo} oleh ${creator}`;

  // Apply Watermark content dynamically
  if (wmTopLeft) wmTopLeft.innerText = `IMS - BY ${creator.toUpperCase()}`;
  if (wmBottomLeft) wmBottomLeft.innerText = `NO: ${handoverNo}`;
  if (wmBottomRight) wmBottomRight.innerText = `DATE: ${date.substring(0, 10)}`;

  App.openModal('modalHandoverPhotoViewer');
}

async function receiveHandover(id) {
  const confirmed = await App.confirm({
    title: 'Terima Serah Terima Tugas',
    message: 'Apakah Anda yakin ingin menerima serah terima pekerjaan ini dan menandainya sebagai Selesai (DONE)?',
    confirmText: 'Ya, Terima & Selesaikan',
    cancelText: 'Batal',
    type: 'emerald',
    icon: 'task_alt'
  });

  if (!confirmed) return;
  
  const res = await App.fetchJson('../api/handovers.php?action=receive', {
    method: 'POST',
    body: 'id=' + id,
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
  });
  
  if (res.success) {
    App.toast(res.message, 'success', 'Handover Diterima');
    loadHandovers(true);
  } else {
    App.toast(res.message || 'Gagal menerima handover', 'error');
  }
}

async function receiveHandoverInModal(id) {
  const confirmed = await App.confirm({
    title: 'Terima Serah Terima Tugas',
    message: 'Apakah Anda yakin ingin menerima serah terima pekerjaan ini dan menandainya sebagai Selesai (DONE)?',
    confirmText: 'Ya, Terima & Selesaikan',
    cancelText: 'Batal',
    type: 'emerald',
    icon: 'task_alt'
  });

  if (!confirmed) return;

  App.closeModal('modalHandoverDetail');

  const res = await App.fetchJson('../api/handovers.php?action=receive', {
    method: 'POST',
    body: 'id=' + id,
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
  });

  if (res.success) {
    App.toast(res.message, 'success', 'Handover Diterima');
    loadHandovers(true);
  } else {
    App.toast(res.message || 'Gagal menerima handover', 'error');
  }
}

async function shareHandover(id) {
  const item = myHandoversList.find(x => x.id == id);
  if (!item) return;
  
  // Mark as shared in database asynchronously
  fetch('../api/handovers.php?action=mark_shared', {
    method: 'POST',
    body: 'id=' + id,
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
  }).then(response => response.json()).then(res => {
    if (res.success) {
      item.is_shared = 1;
      // Update share badge in the currently opened details modal if visible
      const badge = document.getElementById('detHandoverShareBadge');
      if (badge) {
        badge.innerHTML = `<span class="px-2 py-0.5 rounded bg-teal-100 text-teal-800 border border-teal-200 text-[9px] font-bold flex items-center gap-0.5"><span class="material-symbols-outlined text-[10px] font-bold">check</span>Shared</span>`;
      }
      loadHandovers(true);
    }
  }).catch(console.error);
  
  // Parse photos
  let photos = [];
  if (item.photo_path) {
    if (item.photo_path.startsWith('[')) {
      try {
        photos = JSON.parse(item.photo_path);
      } catch(e) {
        photos = [item.photo_path];
      }
    } else {
      photos = [item.photo_path];
    }
  }

  const shareText = `*HANDOVER SHIFT REPORT*
No: ${item.handover_no}
Dari: ${item.from_user_name} (${item.from_user_shift})
Tujuan: ${item.to_shift}
Status: ${item.status === 'PENDING' ? '🔴 PENDING (Menunggu)' : '🟢 DONE (Diterima)'}

Catatan / Pekerjaan:
${item.notes}

Dikirim via PackStock Mobile WMS`;

  // Fetch actual photo files to attach directly to the share intent
  let fileObjects = [];
  if (photos.length > 0) {
    try {
      for (let i = 0; i < photos.length; i++) {
        const p = photos[i];
        const res = await fetch(`../${p}`);
        const blob = await res.blob();
        const ext = p.split('.').pop() || 'jpg';
        const file = new File([blob], `handover_${item.handover_no}_${i + 1}.${ext}`, { type: blob.type || 'image/jpeg' });
        fileObjects.push(file);
      }
    } catch (err) {
      console.warn('Could not load photo blobs for file sharing:', err);
    }
  }

  // 1. If file sharing is supported by device, share actual photos with caption
  if (navigator.share && fileObjects.length > 0 && navigator.canShare && navigator.canShare({ files: fileObjects })) {
    try {
      await navigator.share({
        title: `Handover Shift Report - ${item.handover_no}`,
        text: shareText,
        files: fileObjects
      });
      return;
    } catch (err) {
      if (err.name !== 'AbortError') {
        console.warn('Native file share failed, trying text fallback:', err);
      } else {
        return; // User dismissed share sheet
      }
    }
  }

  // 2. Fallback text share with links if file sharing is not supported
  let photoUrlText = '';
  if (photos.length > 0) {
    photoUrlText += '\n\nFoto Lampiran:';
    photos.forEach((p, idx) => {
      photoUrlText += `\n${idx + 1}. ${window.location.origin}/${p}`;
    });
  }

  if (navigator.share) {
    try {
      await navigator.share({
        title: `Handover Shift Report - ${item.handover_no}`,
        text: shareText + photoUrlText
      });
      return;
    } catch (err) {
      if (err.name === 'AbortError') return;
    }
  }

  // 3. Fallback to WhatsApp Web
  const waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(shareText + photoUrlText)}`;
  window.open(waUrl, '_blank');
}

// =========================================================================
// SELF-SERVICE ROLLING SHIFT SWITCHER (OPERATOR)
// =========================================================================
function openShiftSwitcherModal() {
  const radios = document.querySelectorAll('input[name="myActiveShift"]');
  radios.forEach(r => {
    if (typeof CURRENT_USER_SHIFT !== 'undefined' && CURRENT_USER_SHIFT) {
      if (r.value === CURRENT_USER_SHIFT || CURRENT_USER_SHIFT.toLowerCase().includes(r.value.toLowerCase()) || r.value.toLowerCase().includes(CURRENT_USER_SHIFT.toLowerCase())) {
        r.checked = true;
      }
    }
  });
  App.openModal('modalChangeMyShift');
}

async function submitChangeMyShift(e) {
  e.preventDefault();
  const selectedRadio = document.querySelector('input[name="myActiveShift"]:checked');
  if (!selectedRadio) {
    App.toast('Silakan pilih salah satu shift kerja.', 'warning');
    return;
  }

  const chosenShift = selectedRadio.value;
  const btn = document.getElementById('btnSaveMyShift');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Menyimpan...</span>';

  try {
    const res = await App.fetchJson('../api/users.php?action=update_my_shift', {
      method: 'POST',
      body: JSON.stringify({ shift: chosenShift })
    });

    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">save</span><span>Simpan Shift</span>';

    if (res.success) {
      CURRENT_USER_SHIFT = chosenShift;
      
      // Update Header & Home UI
      const headerDisplay = document.getElementById('headerUserShiftDisplay');
      if (headerDisplay) headerDisplay.innerText = chosenShift;

      const homeLabel = document.getElementById('homeCurrentShiftLabel');
      if (homeLabel) homeLabel.innerText = chosenShift;

      const fromShiftSel = document.getElementById('handoverFromShift');
      if (fromShiftSel) fromShiftSel.value = chosenShift;

      App.toast(res.message, 'success', 'Shift Diperbarui');
      App.closeModal('modalChangeMyShift');

      // Refresh handovers to re-evaluate notification badges for this new shift
      loadHandovers(true);
    } else {
      App.toast(res.message || 'Gagal mengubah shift.', 'error');
    }
  } catch (err) {
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">save</span><span>Simpan Shift</span>';
    App.toast('Terjadi kesalahan jaringan.', 'error');
  }
}

// =========================================================================
// MANDATORY SHIFT GATEKEEPER & TIME-BASED AUTO-SELECTION
// =========================================================================
function initMandatoryShiftGate() {
  const now = new Date();
  const hour = now.getHours();
  // 08:00 to 15:59 -> Shift 1
  // 16:00 to 07:59 -> Shift 2
  const isShift1Time = (hour >= 8 && hour < 16);

  // Set default radio selection based on current clock in Gatekeeper
  const gateRadio1 = document.querySelector('input[name="gateActiveShift"][value*="Shift 1"]');
  const gateRadio2 = document.querySelector('input[name="gateActiveShift"][value*="Shift 2"]');
  const badge1 = document.getElementById('gateBadgeShift1');
  const badge2 = document.getElementById('gateBadgeShift2');

  if (isShift1Time) {
    if (gateRadio1) gateRadio1.checked = true;
    if (badge1) badge1.classList.remove('hidden');
    if (badge2) badge2.classList.add('hidden');
  } else {
    if (gateRadio2) gateRadio2.checked = true;
    if (badge2) badge2.classList.remove('hidden');
    if (badge1) badge1.classList.add('hidden');
  }

  // Also pre-check in regular shift modal
  const shiftRadios = document.querySelectorAll('input[name="myActiveShift"]');
  shiftRadios.forEach(r => {
    if (typeof CURRENT_USER_SHIFT !== 'undefined' && CURRENT_USER_SHIFT) {
      if (r.value === CURRENT_USER_SHIFT || CURRENT_USER_SHIFT.toLowerCase().includes(r.value.toLowerCase())) {
        r.checked = true;
      }
    } else {
      if (isShift1Time && r.value.includes('Shift 1')) r.checked = true;
      if (!isShift1Time && r.value.includes('Shift 2')) r.checked = true;
    }
  });

  // Check if operator has confirmed their shift for this session
  const isConfirmed = sessionStorage.getItem('packstock_op_shift_confirmed');
  const gateModal = document.getElementById('modalMandatoryShiftGate');
  if (!isConfirmed && gateModal) {
    gateModal.classList.remove('hidden');
    gateModal.classList.add('flex');
  }
}

async function submitMandatoryShiftGate(e) {
  e.preventDefault();
  const selectedRadio = document.querySelector('input[name="gateActiveShift"]:checked');
  if (!selectedRadio) {
    App.toast('Silakan pilih salah satu shift kerja.', 'warning');
    return;
  }

  const chosenShift = selectedRadio.value;
  const btn = document.getElementById('btnGateConfirmShift');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Mengonfirmasi...</span>';

  try {
    const res = await App.fetchJson('../api/users.php?action=update_my_shift', {
      method: 'POST',
      body: JSON.stringify({ shift: chosenShift })
    });

    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check_circle</span><span>Konfirmasi & Buka Menu</span>';

    if (res.success) {
      sessionStorage.setItem('packstock_op_shift_confirmed', 'true');
      CURRENT_USER_SHIFT = chosenShift;

      // Update UI displays
      const headerDisplay = document.getElementById('headerUserShiftDisplay');
      if (headerDisplay) headerDisplay.innerText = chosenShift;

      const homeLabel = document.getElementById('homeCurrentShiftLabel');
      if (homeLabel) homeLabel.innerText = chosenShift;

      const fromShiftSel = document.getElementById('handoverFromShift');
      if (fromShiftSel) fromShiftSel.value = chosenShift;

      const gateModal = document.getElementById('modalMandatoryShiftGate');
      if (gateModal) {
        gateModal.classList.add('hidden');
        gateModal.classList.remove('flex');
      }

      App.toast(`Shift aktif Anda: ${chosenShift}`, 'success', 'Selamat Bertugas');
      loadHandovers(true);
    } else {
      App.toast(res.message || 'Gagal menyimpan shift.', 'error');
    }
  } catch (err) {
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check_circle</span><span>Konfirmasi & Buka Menu</span>';
    App.toast('Terjadi kesalahan jaringan.', 'error');
  }
}


