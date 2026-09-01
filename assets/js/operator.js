let myTasks = [];
let allStock = [];
let opInboundDraft = [];
let currentOpTab = 'home';
let activeSubmittingTask = null;

document.addEventListener('DOMContentLoaded', () => {
  updateLiveClock();
  setInterval(updateLiveClock, 1000);
  updateGreeting();

  if (typeof IS_FULFILLMENT_ONLY !== 'undefined' && IS_FULFILLMENT_ONLY) {
    loadFulfillmentStats();
    loadOperatorConsumableRequests();
    initMandatoryShiftGate();

    setInterval(() => {
      if (document.hidden) return;
      loadFulfillmentStats();
      if (currentOpTab === 'request_consumable' && currentOpReqSubTab === 'history') {
        loadOperatorConsumableRequests(true);
      }
    }, 45000);
    return;
  }

  loadOperatorStats();
  loadOperatorTasks(true);
  initMandatoryShiftGate();

  setInterval(() => {
    if (document.hidden) return;
    if (currentOpTab === 'tasks') loadOperatorTasks(true);
    else if (currentOpTab === 'dynamic_count') loadOperatorDynamicTasks(true);
    else if (currentOpTab === 'opname') { loadOperatorBlankCounts(true); loadOperatorRecountTasks(true); }
    else if (currentOpTab === 'handover') loadHandovers(true);
    else if (currentOpTab === 'home') loadOperatorStats(true);
  }, 45000);
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

  if (typeof IS_FULFILLMENT_ONLY !== 'undefined' && IS_FULFILLMENT_ONLY) {
    await Promise.all([
      loadFulfillmentStats(),
      loadOperatorConsumableRequests(),
      loadOperatorStock()
    ]);
  } else {
    await Promise.all([
      loadOperatorStats(true),
      loadOperatorTasks(true),
      loadOperatorDynamicTasks(true),
      loadOperatorBlankCounts(true),
      loadOperatorRecountTasks(true),
      loadOperatorStock(true),
      loadHandovers(true)
    ]);
  }

  if (icon) {
    setTimeout(() => icon.classList.remove('animate-spin'), 600);
  }
  App.toast('Data berhasil disinkronkan', 'info');
}

// Mobile Screen / Tab Switcher
function switchOpTab(tabName) {
  // Strict 1-menu access for operator_fulfillment
  if (typeof IS_FULFILLMENT_ONLY !== 'undefined' && IS_FULFILLMENT_ONLY) {
    if (tabName !== 'home' && tabName !== 'request_consumable') {
      tabName = 'request_consumable';
    }
  }

  currentOpTab = tabName;
  const allTabs = ['home', 'tasks', 'dynamic_count', 'opname', 'inbound', 'stock', 'request_consumable', 'history', 'handover'];

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
  const bottomNavs = ['home', 'inbound', 'tasks', 'handover', 'dynamic_count', 'opname', 'req-form', 'req-hist'];
  bottomNavs.forEach(nav => {
    const navBtn = document.getElementById('bottom-nav-' + nav);
    if (navBtn) {
      const isMatch = (nav === tabName) ||
        (nav === 'req-form' && tabName === 'request_consumable' && currentOpReqSubTab === 'form') ||
        (nav === 'req-hist' && tabName === 'request_consumable' && currentOpReqSubTab === 'history');
      if (isMatch) {
        navBtn.classList.remove('text-slate-400', 'font-semibold');
        navBtn.classList.add('text-emerald-700', 'font-bold');
      } else {
        navBtn.classList.remove('text-emerald-700', 'font-bold');
        navBtn.classList.add('text-slate-400', 'font-semibold');
      }
    }
  });

  // Trigger sub-view data loading
  if (tabName === 'home' && typeof IS_FULFILLMENT_ONLY !== 'undefined' && IS_FULFILLMENT_ONLY) {
    loadFulfillmentStats();
  }
  if (tabName === 'tasks') loadOperatorTasks();
  if (tabName === 'dynamic_count') loadOperatorDynamicTasks();
  if (tabName === 'opname') {
    populateBlankMaterials();
    loadOperatorBlankCounts();
    loadOperatorRecountTasks();
    switchOpnameSubTab(currentOpnameSubTab || '1st');
  }
  if (tabName === 'inbound') populateOpInboundMaterials();
  if (tabName === 'request_consumable') initConsumableRequestView();
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
    const current = App.parseNumber(input.value) || 0;
    input.value = Math.max(0, +(current + delta).toFixed(3));
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

// 2. TASKS FOR OPERATOR (ACTIVE & GROUPED OUTBOUND HISTORY)
let currentOpTaskSubTab = 'active';
window._currentGroupedHistory = {};
let currentShareData = null;

function switchOpTaskSubTab(tab) {
  currentOpTaskSubTab = tab;
  const btnActive = document.getElementById('btnOpTaskSubTabActive');
  const btnHistory = document.getElementById('btnOpTaskSubTabHistory');
  const viewActive = document.getElementById('opTaskActiveView');
  const viewHistory = document.getElementById('opTaskHistoryView');

  if (tab === 'active') {
    if (btnActive) {
      btnActive.className = 'py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 bg-emerald-600 text-white shadow-xs font-bold transition-all cursor-pointer';
    }
    if (btnHistory) {
      btnHistory.className = 'py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 bg-transparent text-slate-600 hover:text-slate-900 font-bold transition-all cursor-pointer';
    }
    if (viewActive) viewActive.classList.remove('hidden');
    if (viewHistory) viewHistory.classList.add('hidden');
  } else {
    if (btnHistory) {
      btnHistory.className = 'py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 bg-emerald-600 text-white shadow-xs font-bold transition-all cursor-pointer';
    }
    if (btnActive) {
      btnActive.className = 'py-2.5 px-3 rounded-xl flex items-center justify-center gap-1.5 bg-transparent text-slate-600 hover:text-slate-900 font-bold transition-all cursor-pointer';
    }
    if (viewHistory) viewHistory.classList.remove('hidden');
    if (viewActive) viewActive.classList.add('hidden');
  }
}

async function loadOperatorTasks(silent = false) {
  try {
    const res = await App.fetchJson('../api/tasks.php?action=list&my_tasks=1');
    if (res && res.success) {
      myTasks = res.data || [];
    } else {
      myTasks = [];
    }
    renderOperatorTasksList();
    renderOperatorTasksHistory();
    if (!silent) loadOperatorStats();
  } catch (err) {
    console.error('Failed to load operator tasks:', err);
    myTasks = [];
    renderOperatorTasksList();
    renderOperatorTasksHistory();
  }
}

function renderOperatorTasksList() {
  const container = document.getElementById('opTasksContainer');
  const badgeActive = document.getElementById('badgeOpTaskActiveCount');
  if (!container) return;

  const activeTasks = myTasks.filter(t => t.status === 'PENDING' || t.status === 'IN_PROGRESS');
  if (badgeActive) badgeActive.innerText = activeTasks.length;

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
        <button onclick="loadOperatorTasks()" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-xl text-xs font-bold hover:bg-slate-200 transition-colors inline-flex items-center gap-1 cursor-pointer">
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
            ${t.request_no ? `<span class="font-mono font-black text-[10px] text-amber-900 bg-amber-100 px-2 py-0.5 rounded-lg border border-amber-300">#${escapeHtml(t.request_no)}</span>` : ''}
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
              <span>${escapeHtml(t.rack_location || '-')}</span>
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
            <button onclick="startOperatorTask(${t.id})" class="flex-1 py-2.5 bg-slate-800 hover:bg-slate-900 active:scale-95 text-white font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-1 shadow-xs cursor-pointer">
              <span class="material-symbols-outlined text-[16px]">play_arrow</span>
              <span>Mulai Ambil</span>
            </button>
          ` : ''}

          <button onclick="openSubmitModal(${t.id})" class="flex-1 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-black text-xs rounded-xl shadow-md transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[17px]">task_alt</span>
            <span>Submit Selesai</span>
          </button>
        </div>

      </div>
    `;
  }).join('');
}

// GROUP COMPLETED TASKS BY OUTBOUND DOCUMENT / FULFILLMENT REQUEST ID
function groupCompletedTasks(tasks) {
  const groups = {};

  tasks.forEach(t => {
    let reqNo = t.request_no ? t.request_no.trim() : '';
    if (!reqNo && t.notes) {
      const match = t.notes.match(/#(REQ-\d+-\d+)/i);
      if (match) reqNo = match[1].toUpperCase();
    }

    let groupKey = '';
    if (reqNo) {
      groupKey = 'REQ_' + reqNo;
    } else {
      groupKey = 'DOC_' + (t.task_no || ('TASK_' + t.id));
    }

    // Extract Receiver Name
    let recName = '';
    const rawNotes = t.completion_notes || t.notes || '';
    if (rawNotes) {
      const recMatch = rawNotes.match(/Penerima:\s*([^|\n]+)/i);
      if (recMatch) {
        recName = recMatch[1].trim();
      }
    }

    if (!groups[groupKey]) {
      groups[groupKey] = {
        groupKey: groupKey,
        request_no: reqNo,
        requester_name: t.requester_name || (t.notes ? (t.notes.match(/Pemohon:\s*([^)]+)/i)?.[1]?.trim() || '') : ''),
        receiver_name: recName,
        destination: t.destination || 'Line Packing',
        priority: t.priority || 'NORMAL',
        completed_at: t.completed_at || t.created_at,
        operator_name: t.operator_name || 'Operator',
        operator_shift: t.operator_shift || (typeof CURRENT_USER_SHIFT !== 'undefined' ? CURRENT_USER_SHIFT : ''),
        completion_notes: t.completion_notes || t.notes || '',
        photos: [],
        task_nos: [],
        items: []
      };
    }

    if (recName && !groups[groupKey].receiver_name) {
      groups[groupKey].receiver_name = recName;
    }

    if (t.task_no && !groups[groupKey].task_nos.includes(t.task_no)) {
      groups[groupKey].task_nos.push(t.task_no);
    }

    if (t.photo_path) {
      try {
        const parsed = JSON.parse(t.photo_path);
        if (Array.isArray(parsed)) {
          parsed.forEach(p => { if (!groups[groupKey].photos.includes(p)) groups[groupKey].photos.push(p); });
        } else if (typeof parsed === 'string' && !groups[groupKey].photos.includes(parsed)) {
          groups[groupKey].photos.push(parsed);
        }
      } catch (e) {
        if (!groups[groupKey].photos.includes(t.photo_path)) {
          groups[groupKey].photos.push(t.photo_path);
        }
      }
    }

    if (t.completion_notes && !groups[groupKey].completion_notes.includes(t.completion_notes)) {
      groups[groupKey].completion_notes = t.completion_notes;
    }

    groups[groupKey].items.push({
      task_id: t.id,
      task_no: t.task_no,
      material_id: t.material_id,
      material_name: t.material_name,
      material_code: t.material_code,
      material_unit: t.material_unit || 'Pcs',
      rack_location: t.rack_location || '-',
      target_qty: t.target_qty,
      actual_qty: (t.actual_qty > 0) ? t.actual_qty : t.target_qty,
      notes: t.notes
    });
  });

  return Object.values(groups);
}

function renderOperatorTasksHistory() {
  const container = document.getElementById('opTasksHistoryContainer');
  const badgeHistory = document.getElementById('badgeOpTaskHistoryCount');
  if (!container) return;

  const completedTasks = myTasks.filter(t => t.status === 'COMPLETED');
  const grouped = groupCompletedTasks(completedTasks);

  window._currentGroupedHistory = {};
  grouped.forEach(g => {
    window._currentGroupedHistory[g.groupKey] = g;
  });

  if (badgeHistory) badgeHistory.innerText = grouped.length;

  if (grouped.length === 0) {
    container.innerHTML = `
      <div class="bg-white rounded-3xl p-6 text-center border border-slate-200 shadow-xs space-y-2.5">
        <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 mx-auto flex items-center justify-center">
          <span class="material-symbols-outlined text-[28px]">history</span>
        </div>
        <div>
          <h4 class="font-extrabold text-slate-800 text-sm">Belum Ada Riwayat Selesai</h4>
          <p class="text-xs text-slate-500 mt-0.5">Tugas pengeluaran yang telah diselesaikan akan muncul terkelompok di sini.</p>
        </div>
      </div>
    `;
    return;
  }

  const query = (document.getElementById('opTaskHistorySearchInput')?.value || '').toLowerCase().trim();

  const filtered = grouped.filter(g => {
    if (!query) return true;
    const matchDoc = g.task_nos.join(' ').toLowerCase().includes(query);
    const matchReq = (g.request_no || '').toLowerCase().includes(query);
    const matchDest = (g.destination || '').toLowerCase().includes(query);
    const matchReqUser = (g.requester_name || '').toLowerCase().includes(query);
    const matchReceiver = (g.receiver_name || '').toLowerCase().includes(query);
    const matchItems = g.items.some(it => (it.material_name || '').toLowerCase().includes(query) || (it.material_code || '').toLowerCase().includes(query));
    return matchDoc || matchReq || matchDest || matchReqUser || matchReceiver || matchItems;
  });

  if (filtered.length === 0) {
    container.innerHTML = `
      <div class="bg-white rounded-3xl p-6 text-center border border-slate-200 shadow-xs">
        <p class="text-xs text-slate-400 font-semibold">Tidak ditemukan riwayat yang cocok dengan pencarian.</p>
      </div>
    `;
    return;
  }

  container.innerHTML = filtered.map(g => {
    const docLabel = g.task_nos.join(', ');
    const totalQty = g.items.reduce((acc, it) => acc + parseFloat(it.actual_qty || 0), 0);
    const dateFormatted = App.formatDate(g.completed_at) + ', ' + App.formatTime(g.completed_at) + ' WIB';

    const itemsRows = g.items.map((it, idx) => `
      <div class="flex items-start justify-between py-2 border-b border-slate-100 last:border-0 gap-2">
        <div class="min-w-0 flex-1">
          <div class="font-bold text-slate-900 text-xs leading-tight">${escapeHtml(it.material_name)}</div>
          <div class="flex items-center gap-2 text-[10px] text-slate-400 font-mono mt-0.5">
            <span>${escapeHtml(it.material_code)}</span>
            <span>&bull;</span>
            <span class="text-slate-600 font-sans">Rak: <b>${escapeHtml(it.rack_location)}</b></span>
          </div>
        </div>
        <div class="text-right shrink-0">
          <span class="font-mono font-black text-xs text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded-lg border border-emerald-200">
            ${App.formatNumber(it.actual_qty)} ${escapeHtml(it.material_unit)}
          </span>
        </div>
      </div>
    `).join('');

    const photoThumbnails = (g.photos && g.photos.length > 0) ? `
      <div class="pt-2 border-t border-slate-100">
        <p class="text-[9px] font-bold uppercase tracking-wider text-slate-400 mb-1.5 flex items-center gap-1">
          <span class="material-symbols-outlined text-[14px] text-amber-600">receipt_long</span>
          <span>Foto Surat Jalan & Bukti Serah Terima (${g.photos.length})</span>
        </p>
        <div class="flex items-center gap-2 overflow-x-auto pb-1">
          ${g.photos.map(p => `
            <a href="../${escapeHtml(p)}" target="_blank" class="w-14 h-14 rounded-xl overflow-hidden border border-slate-200 shadow-2xs shrink-0 block hover:opacity-90 relative group">
              <img src="../${escapeHtml(p)}" alt="Surat Jalan / Bukti" class="w-full h-full object-cover">
              <span class="absolute bottom-0 inset-x-0 bg-slate-900/60 text-white text-[8px] font-mono text-center py-0.5 opacity-0 group-hover:opacity-100 transition-opacity">Zoom</span>
            </a>
          `).join('')}
        </div>
      </div>
    ` : '';

    return `
      <div class="bg-white rounded-3xl p-4 border border-slate-200 shadow-xs hover:shadow-md transition-all space-y-3">
        
        <!-- Header: Doc ID, Request ID & Destination -->
        <div class="flex items-start justify-between gap-2">
          <div class="space-y-1">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="font-mono font-black text-xs text-slate-900 bg-slate-100 px-2.5 py-0.5 rounded-lg border border-slate-200">
                ${escapeHtml(docLabel)}
              </span>
              ${g.request_no ? `
                <span class="font-mono font-black text-[11px] text-amber-900 bg-amber-100 px-2.5 py-0.5 rounded-lg border border-amber-300 flex items-center gap-1">
                  <span class="material-symbols-outlined text-[13px]">assignment</span>
                  <span>#${escapeHtml(g.request_no)}</span>
                </span>
              ` : ''}
            </div>
            ${g.requester_name ? `
              <p class="text-[11px] text-slate-500 font-medium">
                Pemohon: <b class="text-slate-800">${escapeHtml(g.requester_name)}</b>
              </p>
            ` : ''}
          </div>

          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-800 border border-emerald-200 shrink-0 flex items-center gap-1">
            <span class="material-symbols-outlined text-[13px]">check_circle</span>
            <span>SELESAI</span>
          </span>
        </div>

        <!-- Info Strip: Line & Receiver & Time -->
        <div class="bg-slate-50/80 p-3 rounded-2xl border border-slate-100 space-y-2 text-xs">
          <div class="grid grid-cols-2 gap-2">
            <div>
              <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Tujuan Antar</span>
              <span class="font-black text-slate-900 flex items-center gap-1 mt-0.5">
                <span class="material-symbols-outlined text-emerald-600 text-[15px]">pin_drop</span>
                <span>${escapeHtml(g.destination)}</span>
              </span>
            </div>
            <div class="text-right">
              <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block">Waktu Selesai</span>
              <span class="font-bold text-slate-600 text-[11px] block mt-0.5">${dateFormatted}</span>
            </div>
          </div>

          ${g.receiver_name ? `
            <div class="pt-2 border-t border-slate-200/60 flex items-center gap-1.5 text-[11px] text-slate-700">
              <span class="material-symbols-outlined text-indigo-600 text-[16px]">how_to_reg</span>
              <span>Diterima Oleh: <b class="text-slate-900 font-bold">${escapeHtml(g.receiver_name)}</b></span>
            </div>
          ` : ''}
        </div>

        <!-- Items Table Container -->
        <div class="bg-slate-50/50 p-3 rounded-2xl border border-slate-200/70">
          <div class="flex items-center justify-between pb-1.5 border-b border-slate-200 text-[10px] font-black uppercase tracking-wider text-slate-400">
            <span>Daftar Material (${g.items.length} Item)</span>
            <span>Total: ${App.formatNumber(totalQty)}</span>
          </div>
          <div class="divide-y divide-slate-100">
            ${itemsRows}
          </div>
        </div>

        ${g.completion_notes ? `
          <div class="p-2.5 rounded-xl bg-amber-50/60 border border-amber-200/80 text-[11px] text-amber-950 flex items-start gap-1.5">
            <span class="material-symbols-outlined text-amber-700 text-[15px] shrink-0 mt-0.5">description</span>
            <div>
              <b class="font-bold">Catatan:</b> ${escapeHtml(g.completion_notes)}
            </div>
          </div>
        ` : ''}

        ${photoThumbnails}

        <!-- SHARE / COPY ACTIONS -->
        <div class="pt-1 flex items-center gap-2">
          <button type="button" onclick="openShareOutboundModal('${g.groupKey}')" class="flex-1 py-2.5 px-3 bg-slate-900 hover:bg-slate-800 active:scale-95 text-white font-bold text-xs rounded-xl shadow-xs transition-all flex items-center justify-center gap-1.5 cursor-pointer">
            <span class="material-symbols-outlined text-[17px] text-emerald-400">share</span>
            <span>Lihat & Share Bukti</span>
          </button>

          <button type="button" onclick="shareOrCopyDirectly('${g.groupKey}')" class="py-2.5 px-3 bg-emerald-50 hover:bg-emerald-100 active:scale-95 text-emerald-800 border border-emerald-200 font-bold text-xs rounded-xl transition-all flex items-center justify-center gap-1 cursor-pointer" title="Bagikan Cepat">
            <span class="material-symbols-outlined text-[17px]">send</span>
          </button>
        </div>

      </div>
    `;
  }).join('');
}

function filterOperatorTaskHistory() {
  renderOperatorTasksHistory();
}

// HELPER PUBLIC PHOTO URL FOR WHATSAPP PREVIEW
function getPublicPhotoUrl(relativePath) {
  if (!relativePath) return '';
  if (relativePath.startsWith('http://') || relativePath.startsWith('https://')) return relativePath;
  const clean = relativePath.replace(/^\.\.\//, '').replace(/^\//, '');
  const base = window.location.origin + window.location.pathname.replace(/\/operator\/?.*$/, '').replace(/\/admin\/?.*$/, '');
  const cleanBase = base.endsWith('/') ? base.slice(0, -1) : base;
  return `${cleanBase}/${clean}`;
}

function formatShortDate(dtStr) {
  if (!dtStr) {
    const now = new Date();
    const d = String(now.getDate()).padStart(2, '0');
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const y = now.getFullYear();
    return `${d}-${m}-${y}`;
  }
  const parts = dtStr.split(' ')[0].split('-');
  if (parts.length === 3) {
    return `${parts[2]}-${parts[1]}-${parts[0]}`;
  }
  return dtStr;
}

function formatShiftShort(shiftStr) {
  if (!shiftStr) return 'Shift 2';
  if (shiftStr.toLowerCase().includes('shift 2')) return 'Shift 2';
  if (shiftStr.toLowerCase().includes('shift 1')) return 'Shift 1';
  return shiftStr;
}

// SHARE TEXT & WHATSAPP BUILDER (FORMAT: UPDATE OUTPUT KEMAS)
function generateShareText(group) {
  const dateFormatted = formatShortDate(group.completed_at || '');
  const shift = formatShiftShort(group.operator_shift);
  const brand = (group.destination || 'hanasui').toLowerCase();

  let itemsList = '';
  group.items.forEach((it, idx) => {
    const unit = it.material_unit ? it.material_unit.toLowerCase() : 'pcs';
    itemsList += `${idx + 1}.${it.material_name.toLowerCase()} ${App.formatNumber(it.actual_qty)} ${unit}\n`;
  });

  const text = `Update output kemas\n` +
               `Tgl ${dateFormatted}\n` +
               `${shift}\n` +
               `Brand ${brand}\n\n` +
               `${itemsList.trim()}\n\n` +
               `Cc:@~Nurul @~rehan @~Muhamad Afif`;

  return text.trim();
}

function openShareOutboundModal(groupKey) {
  const group = window._currentGroupedHistory?.[groupKey];
  if (!group) return;

  currentShareData = group;
  const shareText = generateShareText(group);

  const docLabel = group.request_no ? `Req #${group.request_no} (${group.task_nos.join(', ')})` : group.task_nos.join(', ');
  document.getElementById('shareModalSubtitle').innerText = `Dokumen: ${docLabel}`;
  
  const previewBox = document.getElementById('shareTextPreviewBox');
  if (previewBox) previewBox.value = shareText;

  // Render Photo Previews in Modal
  const photoContainer = document.getElementById('shareModalPhotosContainer');
  const photosList = document.getElementById('shareModalPhotosList');
  if (photoContainer && photosList) {
    if (group.photos && group.photos.length > 0) {
      photosList.innerHTML = group.photos.map((p, idx) => `
        <a href="../${escapeHtml(p)}" target="_blank" class="w-16 h-16 rounded-xl overflow-hidden border border-slate-200 shadow-2xs shrink-0 block relative group hover:opacity-90">
          <img src="../${escapeHtml(p)}" alt="Foto ${idx + 1}" class="w-full h-full object-cover">
          <span class="absolute inset-0 bg-slate-950/40 text-white text-[9px] font-bold flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">Buka</span>
        </a>
      `).join('');
      photoContainer.classList.remove('hidden');
    } else {
      photosList.innerHTML = '';
      photoContainer.classList.add('hidden');
    }
  }

  const btnLabel = document.getElementById('btnCopyShareTextLabel');
  if (btnLabel) btnLabel.innerText = 'Salin Teks';

  App.openModal('modalShareOutboundSummary');
}

async function shareOrCopyDirectly(groupKey) {
  const group = window._currentGroupedHistory?.[groupKey];
  if (!group) return;

  const text = generateShareText(group);
  const title = `Update Output Kemas ${group.destination || ''}`;

  // Try Web Share API Level 2 (Share actual image files directly to WhatsApp with caption)
  if (navigator.share && group.photos && group.photos.length > 0) {
    try {
      const files = [];
      for (let i = 0; i < Math.min(group.photos.length, 3); i++) {
        const photoPath = group.photos[i];
        const fetchUrl = `../${photoPath.replace(/^\.\.\//, '').replace(/^\//, '')}`;
        const resp = await fetch(fetchUrl);
        if (resp.ok) {
          const blob = await resp.blob();
          const ext = photoPath.split('.').pop() || 'jpg';
          const file = new File([blob], `output_kemas_${i + 1}.${ext}`, { type: blob.type || 'image/jpeg' });
          files.push(file);
        }
      }

      if (files.length > 0 && navigator.canShare && navigator.canShare({ files })) {
        await navigator.share({
          title: title,
          text: text,
          files: files
        });
        return;
      }
    } catch (e) {
      console.log('Share with files cancelled / fallback:', e);
      if (e.name === 'AbortError') return;
    }
  }

  // Fallback to text share
  if (navigator.share) {
    try {
      await navigator.share({
        title: title,
        text: text
      });
      return;
    } catch (err) {
      if (err.name !== 'AbortError') {
        openShareOutboundModal(groupKey);
      }
      return;
    }
  }

  openShareOutboundModal(groupKey);
}

async function copyShareTextToClipboard() {
  const textarea = document.getElementById('shareTextPreviewBox');
  const text = textarea ? textarea.value : (currentShareData ? generateShareText(currentShareData) : '');
  if (!text) return;

  try {
    await navigator.clipboard.writeText(text);
    const btnLabel = document.getElementById('btnCopyShareTextLabel');
    if (btnLabel) btnLabel.innerText = 'Tersalin! ✓';
    App.toast('Teks output kemas berhasil disalin!', 'success', 'Tersalin');
    setTimeout(() => {
      if (btnLabel) btnLabel.innerText = 'Salin Teks';
    }, 2500);
  } catch (e) {
    App.toast('Gagal menyalin teks', 'error');
  }
}

async function openWhatsAppShare() {
  if (!currentShareData) return;
  const textarea = document.getElementById('shareTextPreviewBox');
  const text = textarea ? textarea.value : generateShareText(currentShareData);
  
  // If browser can share file directly via Web Share, trigger it first
  if (navigator.share && currentShareData.photos && currentShareData.photos.length > 0) {
    try {
      const files = [];
      for (let i = 0; i < Math.min(currentShareData.photos.length, 3); i++) {
        const photoPath = currentShareData.photos[i];
        const fetchUrl = `../${photoPath.replace(/^\.\.\//, '').replace(/^\//, '')}`;
        const resp = await fetch(fetchUrl);
        if (resp.ok) {
          const blob = await resp.blob();
          const ext = photoPath.split('.').pop() || 'jpg';
          const file = new File([blob], `output_kemas_${i + 1}.${ext}`, { type: blob.type || 'image/jpeg' });
          files.push(file);
        }
      }

      if (files.length > 0 && navigator.canShare && navigator.canShare({ files })) {
        await navigator.share({
          title: `Update Output Kemas`,
          text: text,
          files: files
        });
        return;
      }
    } catch (e) {
      if (e.name === 'AbortError') return;
    }
  }

  // Fallback to WhatsApp URL
  const waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`;
  window.open(waUrl, '_blank');
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

// Task Completion Multi-Photo State
let taskCompleteSelectedFiles = [];

function previewTaskCompletePhoto(e) {
  const files = Array.from(e.target.files || []);
  if (files.length === 0) return;
  taskCompleteSelectedFiles = taskCompleteSelectedFiles.concat(files);
  renderTaskCompletePreviews();
}

function renderTaskCompletePreviews() {
  const container = document.getElementById('taskPhotoPreviewContainer');
  const badge = document.getElementById('taskPhotoCountBadge');
  const clearBtn = document.getElementById('btnTaskClearPhotos');
  if (!container) return;

  if (badge) badge.innerText = `${taskCompleteSelectedFiles.length} Foto`;

  if (taskCompleteSelectedFiles.length === 0) {
    container.innerHTML = '';
    container.classList.add('hidden');
    if (clearBtn) clearBtn.classList.add('hidden');
    return;
  }

  container.classList.remove('hidden');
  if (clearBtn) clearBtn.classList.remove('hidden');

  container.innerHTML = taskCompleteSelectedFiles.map((file, idx) => {
    const url = URL.createObjectURL(file);
    return `
      <div class="relative group w-14 h-14 rounded-xl overflow-hidden border border-slate-200 shadow-2xs bg-slate-900 flex items-center justify-center flex-shrink-0">
        <img src="${url}" alt="Preview" class="w-full h-full object-cover">
        <button type="button" onclick="removeSelectedTaskCompleteFile(${idx})" class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full bg-rose-600 text-white flex items-center justify-center opacity-90 hover:opacity-100 transition-opacity" title="Hapus foto ini">
          <span class="material-symbols-outlined text-[11px]">close</span>
        </button>
      </div>
    `;
  }).join('');
}

function removeSelectedTaskCompleteFile(index) {
  taskCompleteSelectedFiles.splice(index, 1);
  renderTaskCompletePreviews();
}

function clearTaskCompletePhotos() {
  const input = document.getElementById('taskCompletePhoto');
  if (input) input.value = '';
  taskCompleteSelectedFiles = [];
  renderTaskCompletePreviews();
}

// 4. SUBMIT TASK MODAL & EXECUTION
function openSubmitModal(taskId) {
  const task = myTasks.find(t => t.id == taskId);
  if (!task) return;

  activeSubmittingTask = task;
  clearTaskCompletePhotos();

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

  const recInput = document.getElementById('submitReceiverName');
  if (recInput) recInput.value = '';
  document.getElementById('submitNotes').value = '';

  App.openModal('modalSubmitTask');
}

async function handleFinalTaskSubmit(e) {
  e.preventDefault();
  if (!activeSubmittingTask) return;

  const task_id = document.getElementById('submitTaskId').value;
  const actual_qty = App.parseNumber(document.getElementById('submitActualQty').value);
  const receiver_name = document.getElementById('submitReceiverName')?.value?.trim() || '';
  const extra_notes = document.getElementById('submitNotes')?.value?.trim() || '';

  if (actual_qty <= 0) {
    App.toast('Jumlah riil yang diserahkan harus lebih dari 0', 'warning', 'Qty Wajib');
    return;
  }

  if (!receiver_name) {
    App.toast('Nama Penerima di Line / PIC wajib diisi!', 'warning', 'Wajib Diisi');
    const recInput = document.getElementById('submitReceiverName');
    if (recInput) {
      recInput.focus();
      recInput.classList.add('border-rose-500', 'bg-rose-50');
      setTimeout(() => recInput.classList.remove('border-rose-500', 'bg-rose-50'), 3000);
    }
    return;
  }

  const completion_notes = extra_notes ? `Penerima: ${receiver_name} | ${extra_notes}` : `Penerima: ${receiver_name}`;

  if (!taskCompleteSelectedFiles || taskCompleteSelectedFiles.length === 0) {
    App.toast('Foto Surat Jalan / Bukti penyerahan wajib diunggah minimal 1 foto!', 'warning', 'Foto Wajib');
    return;
  }

  const btn = document.getElementById('btnFinalSubmit');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[17px] animate-spin">progress_activity</span> Menyimpan...';

  const formData = new FormData();
  formData.append('task_id', task_id);
  formData.append('actual_qty', actual_qty);
  formData.append('completion_notes', completion_notes);

  for (let i = 0; i < taskCompleteSelectedFiles.length; i++) {
    formData.append('photos[]', taskCompleteSelectedFiles[i]);
  }

  try {
    const response = await fetch('../api/tasks.php?action=submit_complete', {
      method: 'POST',
      body: formData
    });
    const res = await response.json();

    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check_circle</span><span>Konfirmasi & Potong Stok</span>';

    if (res.success) {
      App.toast(res.message, 'success', 'Tugas Selesai');
      App.closeModal('modalSubmitTask');
      clearTaskCompletePhotos();
      loadOperatorTasks();
      loadOperatorStock();
      loadOperatorStats();
    } else {
      App.toast(res.message || 'Gagal menyelesaikan tugas', 'error');
    }
  } catch (err) {
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check_circle</span><span>Konfirmasi & Potong Stok</span>';
    App.toast('Terjadi kesalahan koneksi saat submit tugas', 'error');
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

  container.innerHTML = completed.map(t => {
    let photos = [];
    if (t.photo_path) {
      if (t.photo_path.startsWith('[')) {
        try { photos = JSON.parse(t.photo_path); } catch (e) { photos = [t.photo_path]; }
      } else {
        photos = [t.photo_path];
      }
    }

    const photosHtml = (photos.length > 0)
      ? `
        <div class="flex items-center gap-1.5 pt-1 overflow-x-auto">
          ${photos.map((p, pIdx) => `
            <div class="w-12 h-12 rounded-lg overflow-hidden border border-slate-200 bg-slate-900 flex items-center justify-center flex-shrink-0 cursor-pointer shadow-2xs" onclick="openPhotoViewer('${escapeHtml(p)}', '${escapeHtml(t.task_no)}', '${escapeHtml(t.completed_at || t.created_at)}', 'Operator', 'TASK PICKING')">
              <img src="../${escapeHtml(p)}" alt="Bukti" class="w-full h-full object-cover">
            </div>
          `).join('')}
          <span class="text-[9px] text-slate-400 font-semibold">${photos.length} Foto</span>
        </div>
      `
      : '';

    return `
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
      ${photosHtml}
      <p class="text-[10px] text-slate-400 text-right font-mono">${App.formatDate(t.completed_at)}</p>
    </div>
    `;
  }).join('');
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
  const notesInp = document.getElementById('opInboundNotes');

  if (!select || !qtyInp) return;

  const materialId = parseInt(select.value);
  const qty = App.parseNumber(qtyInp.value);
  const notes = notesInp ? notesInp.value.trim() : '';

  const poInp = document.getElementById('opInboundPoNumber');
  const po_number = poInp ? poInp.value.trim() : '';

  if (!po_number) {
    App.toast('Nomor Referensi / PO / Surat Jalan (Batch) wajib diisi terlebih dahulu!', 'warning');
    poInp?.focus();
    return;
  }

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

  const existingIdx = opInboundDraft.findIndex(i => i.material_id === materialId && i.rack === itemRack);
  if (existingIdx >= 0) {
    opInboundDraft[existingIdx].qty = +(opInboundDraft[existingIdx].qty + qty).toFixed(3);
    if (notes) opInboundDraft[existingIdx].notes = notes;
  } else {
    opInboundDraft.push({
      material_id: materialId,
      code: itemCode,
      name: itemName,
      rack: itemRack,
      qty: qty,
      notes: notes
    });
  }

  qtyInp.value = '';
  if (locInp) locInp.value = '';
  if (notesInp) notesInp.value = '';
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
      <div class="p-2.5 bg-white border border-slate-200 rounded-xl shadow-2xs flex items-center justify-between gap-2 text-xs">
        <div class="space-y-0.5 flex-1 min-w-0">
          <div class="flex items-center gap-2">
            <span class="text-[10px] text-slate-600 font-bold bg-slate-100 px-1.5 py-0.2 rounded border border-slate-200">Rak: ${escapeHtml(item.rack)}</span>
            ${item.notes ? `<span class="text-[10px] text-slate-500 italic truncate max-w-[150px]">"${escapeHtml(item.notes)}"</span>` : ''}
          </div>
          <p class="font-extrabold text-slate-900 leading-snug truncate">${escapeHtml(item.name)}</p>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
          <div class="text-right">
            <span class="font-black text-sm text-emerald-800 font-mono">+${App.formatNumber(item.qty)}</span>
          </div>
          <button type="button" onclick="removeInboundDraftItem(${idx})" title="Hapus dari draft" class="p-1 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-rose-50 transition-colors">
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

// Operator Inbound Multi-Photo State
let opInboundSelectedFiles = [];

function previewOpInboundPhoto(e) {
  const files = Array.from(e.target.files || []);
  if (files.length === 0) return;
  opInboundSelectedFiles = opInboundSelectedFiles.concat(files);
  renderOpInboundPhotoPreviews();
}

function renderOpInboundPhotoPreviews() {
  const container = document.getElementById('opInboundPhotoPreviewContainer');
  const badge = document.getElementById('opInboundPhotoCountBadge');
  const clearBtn = document.getElementById('btnOpClearInboundPhotos');
  if (!container) return;

  if (badge) badge.innerText = `${opInboundSelectedFiles.length} Foto`;

  if (opInboundSelectedFiles.length === 0) {
    container.innerHTML = '';
    container.classList.add('hidden');
    if (clearBtn) clearBtn.classList.add('hidden');
    return;
  }

  container.classList.remove('hidden');
  if (clearBtn) clearBtn.classList.remove('hidden');

  container.innerHTML = opInboundSelectedFiles.map((file, idx) => {
    const url = URL.createObjectURL(file);
    return `
      <div class="relative group w-14 h-14 rounded-xl overflow-hidden border border-slate-200 shadow-2xs bg-slate-900 flex items-center justify-center flex-shrink-0">
        <img src="${url}" alt="Preview" class="w-full h-full object-cover">
        <button type="button" onclick="removeSelectedOpInboundFile(${idx})" class="absolute top-0.5 right-0.5 w-4 h-4 rounded-full bg-rose-600 text-white flex items-center justify-center opacity-90 hover:opacity-100 transition-opacity" title="Hapus foto ini">
          <span class="material-symbols-outlined text-[11px]">close</span>
        </button>
      </div>
    `;
  }).join('');
}

function removeSelectedOpInboundFile(index) {
  opInboundSelectedFiles.splice(index, 1);
  renderOpInboundPhotoPreviews();
}

function clearOpInboundPhotos() {
  const input = document.getElementById('opInboundPhoto');
  if (input) input.value = '';
  opInboundSelectedFiles = [];
  renderOpInboundPhotoPreviews();
}

async function handleInboundDraftSubmit() {
  const po_number = document.getElementById('opInboundPoNumber')?.value.trim() || '';
  if (!po_number) {
    App.toast('Nomor Referensi / PO / Surat Jalan (Batch) wajib diisi!', 'warning');
    document.getElementById('opInboundPoNumber')?.focus();
    return;
  }

  const notes = document.getElementById('opInboundNotes')?.value.trim() || 'Penerimaan Lapangan Operator';

  if (opInboundDraft.length === 0) {
    App.toast('Keranjang draft penerimaan masih kosong. Tambahkan minimal 1 packaging material.', 'warning');
    return;
  }

  const btn = document.getElementById('btnSubmitInboundDraft');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span> Menyimpan Penerimaan & Update Stok...';

  const started_at = opInboundDraftStartTime || new Date().toISOString();

  const formData = new FormData();
  formData.append('po_number', po_number);
  formData.append('supplier', '-');
  formData.append('started_at', started_at);
  formData.append('notes', notes);
  formData.append('items', JSON.stringify(opInboundDraft.map(d => ({
    material_id: d.material_id,
    qty: d.qty,
    rack_location: d.rack,
    notes: d.notes || '-'
  }))));

  for (let i = 0; i < opInboundSelectedFiles.length; i++) {
    formData.append('photos[]', opInboundSelectedFiles[i]);
  }

  try {
    const response = await fetch('../api/inbound.php?action=batch_create', {
      method: 'POST',
      body: formData
    });
    const res = await response.json();

    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check_circle</span><span>Submit & Update Stok Gudang</span>';

    if (res.success) {
      App.toast(res.message, 'success', 'Penerimaan Berhasil');

      // Clear form & draft
      const poEl = document.getElementById('opInboundPoNumber');
      if (poEl) poEl.value = '';
      const notesEl = document.getElementById('opInboundNotes');
      if (notesEl) notesEl.value = '';
      opInboundDraft = [];
      opInboundDraftStartTime = null;
      clearOpInboundPhotos();
      renderInboundDraftList();

      // Reload operator stock and stats
      loadOperatorStock();
      loadOperatorStats();
    } else {
      App.toast(res.message || 'Gagal menyimpan penerimaan draft', 'error');
    }
  } catch (err) {
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">check_circle</span><span>Submit & Update Stok Gudang</span>';
    App.toast('Terjadi kesalahan koneksi saat menyimpan draft penerimaan', 'error');
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
    reader.onload = function (event) {
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
      } catch (e) {
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

function openPhotoViewer(photoPath, docNo, date, creator, docType = 'HANDOVER') {
  const viewerImage = document.getElementById('viewerImage');
  const viewerDesc = document.getElementById('viewerImageDesc');
  const wmTopLeft = document.getElementById('wmTopLeft');
  const wmTopRight = document.getElementById('wmTopRight');
  const wmBottomLeft = document.getElementById('wmBottomLeft');
  const wmBottomRight = document.getElementById('wmBottomRight');

  if (viewerImage) viewerImage.src = `../${photoPath}`;
  if (viewerDesc) viewerDesc.innerText = `Dokumentasi Foto ${docType} #${docNo} (${creator || 'Operator'})`;

  // Apply Watermark content dynamically
  if (wmTopLeft) wmTopLeft.innerText = `IMS - ${docType.toUpperCase()}`;
  if (wmTopRight) wmTopRight.innerText = `BY ${creator ? creator.toUpperCase() : 'OPERATOR'}`;
  if (wmBottomLeft) wmBottomLeft.innerText = `NO: ${docNo}`;
  if (wmBottomRight) wmBottomRight.innerText = `DATE: ${date ? date.substring(0, 10) : ''}`;

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
      } catch (e) {
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

// =========================================================================
// 8. FORM REQUEST CONSUMABLE (OPERATOR & OPERATOR_FULFILLMENT)
// =========================================================================
let opConsumableDraft = [];
let opConsumablePhotos = [];
let currentOpReqSubTab = 'form';

async function initConsumableRequestView() {
  await populateOpReqMaterialSelect();
  if (currentOpReqSubTab === 'history') {
    loadOperatorConsumableRequests();
  }
}

function handleOpReqPhotosSelected(input) {
  if (!input || !input.files || input.files.length === 0) return;

  const files = Array.from(input.files);
  const maxPhotos = 10;
  const remainingSlots = maxPhotos - opConsumablePhotos.length;

  if (remainingSlots <= 0) {
    App.toast(`Maksimal ${maxPhotos} foto per pengajuan.`, 'warning');
    input.value = '';
    return;
  }

  const allowedFiles = files.slice(0, remainingSlots);
  let loadedCount = 0;

  allowedFiles.forEach(file => {
    if (!file.type.startsWith('image/')) {
      loadedCount++;
      return;
    }

    const reader = new FileReader();
    reader.onload = (e) => {
      opConsumablePhotos.push({
        name: file.name,
        data: e.target.result
      });
      loadedCount++;
      if (loadedCount >= allowedFiles.length) {
        renderOpReqPhotoPreviews();
      }
    };
    reader.readAsDataURL(file);
  });

  input.value = '';
}

function renderOpReqPhotoPreviews() {
  const container = document.getElementById('opReqPhotoPreviewGrid');
  const countBadge = document.getElementById('opReqPhotoCountBadge');
  if (!container) return;

  if (countBadge) {
    countBadge.innerText = `${opConsumablePhotos.length} Foto dipilih`;
  }

  if (opConsumablePhotos.length === 0) {
    container.classList.add('hidden');
    container.innerHTML = '';
    return;
  }

  container.classList.remove('hidden');
  container.innerHTML = opConsumablePhotos.map((photo, idx) => `
    <div class="relative group rounded-xl overflow-hidden border border-amber-300 aspect-square bg-slate-100 shadow-2xs">
      <img src="${photo.data}" alt="Foto ${idx + 1}" class="w-full h-full object-cover">
      <button type="button" onclick="removeOpReqPhoto(${idx})" class="absolute top-1 right-1 w-6 h-6 rounded-full bg-rose-600/90 text-white flex items-center justify-center shadow-md hover:bg-rose-700 active:scale-90 transition-all" title="Hapus foto">
        <span class="material-symbols-outlined text-[14px]">close</span>
      </button>
      <span class="absolute bottom-1 left-1 px-1.5 py-0.2 rounded bg-black/60 text-white text-[9px] font-mono">#${idx + 1}</span>
    </div>
  `).join('');
}

function removeOpReqPhoto(index) {
  opConsumablePhotos.splice(index, 1);
  renderOpReqPhotoPreviews();
}

function switchOpReqSubTab(subTab) {
  currentOpReqSubTab = subTab;
  const formView = document.getElementById('opReqSubViewForm');
  const histView = document.getElementById('opReqSubViewHistory');
  const btnForm = document.getElementById('btnOpReqSubTabForm');
  const btnHist = document.getElementById('btnOpReqSubTabHistory');

  if (subTab === 'form') {
    formView?.classList.remove('hidden');
    histView?.classList.add('hidden');
    btnForm?.classList.add('bg-white', 'text-amber-900', 'shadow-xs');
    btnForm?.classList.remove('text-slate-600');
    btnHist?.classList.remove('bg-white', 'text-amber-900', 'shadow-xs');
    btnHist?.classList.add('text-slate-600');
    populateOpReqMaterialSelect();
  } else {
    formView?.classList.add('hidden');
    histView?.classList.remove('hidden');
    btnHist?.classList.add('bg-white', 'text-amber-900', 'shadow-xs');
    btnHist?.classList.remove('text-slate-600');
    btnForm?.classList.remove('bg-white', 'text-amber-900', 'shadow-xs');
    btnForm?.classList.add('text-slate-600');
    loadOperatorConsumableRequests();
  }
}

async function populateOpReqMaterialSelect() {
  const sel = document.getElementById('opReqMaterialSelect');
  if (!sel) return;

  let materials = (typeof allStock !== 'undefined' && allStock.length > 0) ? allStock : [];
  if (materials.length === 0) {
    const res = await App.fetchJson('../api/materials.php?action=list');
    if (res && res.success && res.data) {
      allStock = res.data;
      materials = res.data;
    }
  }

  const currentVal = sel.value;
  sel.innerHTML = '<option value="">-- Pilih Material Packaging --</option>' + (materials || []).map(m => {
    const code = App.escapeHtml(m.code || '');
    const name = App.escapeHtml(m.name || '');
    const unit = App.escapeHtml(m.unit || 'Pcs');
    const rack = App.escapeHtml(m.rack_location || '-');
    const stock = Number(m.current_stock || 0);
    return `<option value="${m.id}" data-code="${code}" data-name="${name}" data-stock="${stock}" data-unit="${unit}" data-rack="${rack}">${name} (Stok: ${App.formatNumber(stock)} ${unit})</option>`;
  }).join('');

  if (currentVal) sel.value = currentVal;
  if (typeof App.syncSearchableSelect === 'function') {
    App.syncSearchableSelect(sel);
  }
}

function handleOpReqMaterialSelectChange(sel) {
  const badge = document.getElementById('opReqStockInfoBadge');
  const stockVal = document.getElementById('opReqStockVal');
  if (!sel || !sel.value) {
    if (badge) badge.classList.add('hidden');
    validateOpReqQtyLive();
    return;
  }

  const opt = sel.options[sel.selectedIndex];
  const stock = parseFloat(opt.getAttribute('data-stock') || 0);
  const unit = opt.getAttribute('data-unit') || 'Pcs';
  const rack = opt.getAttribute('data-rack') || '-';

  if (badge && stockVal) {
    stockVal.innerText = `${App.formatNumber(stock)} ${unit} (Rak: ${rack})`;
    badge.classList.remove('hidden');
  }

  validateOpReqQtyLive();
}

function validateOpReqQtyLive() {
  const sel = document.getElementById('opReqMaterialSelect');
  const qtyInp = document.getElementById('opReqQty');
  const warningBox = document.getElementById('opReqStockWarning');
  const warningText = document.getElementById('opReqStockWarningText');
  const addBtn = document.getElementById('btnOpReqAddDraft');

  if (!sel || !qtyInp) return true;

  const opt = sel.options[sel.selectedIndex];
  if (!opt || !sel.value) {
    if (warningBox) warningBox.classList.add('hidden');
    qtyInp.classList.remove('border-rose-500', 'bg-rose-50/50');
    if (addBtn) {
      addBtn.disabled = false;
      addBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }
    return true;
  }

  const materialId = parseInt(sel.value);
  const stock = parseFloat(opt.getAttribute('data-stock') || 0);
  const unit = opt.getAttribute('data-unit') || 'Pcs';
  const name = opt.getAttribute('data-name') || 'Material';
  const enteredQty = parseFloat(qtyInp.value || 0);

  // Check if item is already in draft
  const existingInDraft = opConsumableDraft.find(i => i.material_id === materialId);
  const draftQty = existingInDraft ? existingInDraft.qty : 0;
  const totalRequested = enteredQty + draftQty;

  if (stock <= 0) {
    if (warningBox && warningText) {
      warningText.innerText = `Stok material "${name}" HABIS (0 ${unit}) di gudang!`;
      warningBox.classList.remove('hidden');
    }
    qtyInp.classList.add('border-rose-500', 'bg-rose-50/50');
    if (addBtn) {
      addBtn.disabled = true;
      addBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
    return false;
  }

  if (enteredQty > 0 && totalRequested > stock) {
    if (warningBox && warningText) {
      warningText.innerText = draftQty > 0
        ? `Total permintaan (${App.formatNumber(totalRequested)} ${unit}) melebihi sisa stok (${App.formatNumber(stock)} ${unit}). Di draft: ${App.formatNumber(draftQty)} ${unit}.`
        : `Jumlah permintaan (${App.formatNumber(enteredQty)} ${unit}) melebihi sisa stok yang tersedia (${App.formatNumber(stock)} ${unit})!`;
      warningBox.classList.remove('hidden');
    }
    qtyInp.classList.add('border-rose-500', 'bg-rose-50/50');
    if (addBtn) {
      addBtn.disabled = true;
      addBtn.classList.add('opacity-50', 'cursor-not-allowed');
    }
    return false;
  }

  // Valid
  if (warningBox) warningBox.classList.add('hidden');
  qtyInp.classList.remove('border-rose-500', 'bg-rose-50/50');
  if (addBtn) {
    addBtn.disabled = false;
    addBtn.classList.remove('opacity-50', 'cursor-not-allowed');
  }
  return true;
}

function addConsumableDraftItem() {
  const sel = document.getElementById('opReqMaterialSelect');
  const qtyInp = document.getElementById('opReqQty');
  const notesInp = document.getElementById('opReqItemNotes');

  if (!sel || !qtyInp) return;

  const materialId = parseInt(sel.value);
  const qty = App.parseNumber(qtyInp.value);
  const notes = notesInp ? notesInp.value.trim() : '';

  if (!materialId || materialId <= 0) {
    App.toast('Silakan pilih material packaging terlebih dahulu.', 'warning');
    sel.focus();
    return;
  }

  if (!qty || qty <= 0) {
    App.toast('Jumlah permintaan harus lebih besar dari 0.', 'warning');
    qtyInp.focus();
    return;
  }

  const opt = sel.options[sel.selectedIndex];
  const itemCode = opt.getAttribute('data-code') || '';
  const itemName = opt.getAttribute('data-name') || 'Material';
  const itemStock = parseFloat(opt.getAttribute('data-stock') || 0);
  const itemUnit = opt.getAttribute('data-unit') || 'Pcs';

  if (itemStock <= 0) {
    App.toast(`Stok material "${itemName}" saat ini habis (0 ${itemUnit}). Tidak dapat mengajukan request.`, 'error');
    return;
  }

  const existingIdx = opConsumableDraft.findIndex(i => i.material_id === materialId);
  const currentDraftQty = existingIdx >= 0 ? opConsumableDraft[existingIdx].qty : 0;
  const totalRequested = currentDraftQty + qty;

  if (totalRequested > itemStock) {
    App.toast(`Jumlah permintaan (${App.formatNumber(totalRequested)} ${itemUnit}) tidak boleh melebihi sisa stok gudang (${App.formatNumber(itemStock)} ${itemUnit})!`, 'error');
    qtyInp.focus();
    validateOpReqQtyLive();
    return;
  }

  if (existingIdx >= 0) {
    opConsumableDraft[existingIdx].qty = +(opConsumableDraft[existingIdx].qty + qty).toFixed(3);
    if (notes) opConsumableDraft[existingIdx].notes = notes;
  } else {
    opConsumableDraft.push({
      material_id: materialId,
      code: itemCode,
      name: itemName,
      stock: itemStock,
      unit: itemUnit,
      qty: qty,
      notes: notes
    });
  }

  // Reset input fields and clear material selection
  qtyInp.value = '';
  if (notesInp) notesInp.value = '';
  sel.value = '';
  sel.selectedIndex = 0;

  const stockBadge = document.getElementById('opReqStockInfoBadge');
  if (stockBadge) stockBadge.classList.add('hidden');

  handleOpReqMaterialSelectChange(sel);
  sel.dispatchEvent(new Event('change'));
  if (typeof App.syncSearchableSelect === 'function') {
    App.syncSearchableSelect(sel);
  }

  renderConsumableDraftList();
  App.toast(`${itemName} (+${App.formatNumber(qty)} ${itemUnit}) ditambahkan ke draft!`, 'success');
}

function renderConsumableDraftList() {
  const container = document.getElementById('opReqDraftListContainer');
  const badge = document.getElementById('opReqDraftCountBadge');
  if (!container) return;

  if (badge) badge.innerText = `${opConsumableDraft.length} Item`;

  if (opConsumableDraft.length === 0) {
    container.innerHTML = `
      <div class="p-6 text-center text-slate-400 text-xs">
        <span class="material-symbols-outlined text-[32px] text-slate-300 mb-1">shopping_cart</span>
        <p>Belum ada material yang ditambahkan ke draft.</p>
      </div>
    `;
    return;
  }

  const totalQty = opConsumableDraft.reduce((acc, i) => acc + i.qty, 0);

  container.innerHTML = `
    <div class="space-y-2">
      ${opConsumableDraft.map((item, idx) => `
        <div class="p-3 bg-amber-50/50 rounded-xl border border-amber-200/80 flex items-center justify-between gap-2 shadow-2xs">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="font-bold text-slate-900 text-xs truncate">${App.escapeHtml(item.name)}</span>
              <span class="text-[10px] text-amber-800 font-mono font-bold bg-amber-100/80 px-1.5 py-0.2 rounded border border-amber-300">${App.escapeHtml(item.code)}</span>
            </div>
            <div class="flex items-center gap-2 mt-1 text-[11px] text-slate-500 flex-wrap">
              <span>Qty Minta: <b class="font-mono font-black text-amber-900 text-xs">${App.formatNumber(item.qty)} ${App.escapeHtml(item.unit)}</b></span>
              <span>&bull;</span>
              <span>Stok Gudang: <b class="font-mono text-slate-700">${App.formatNumber(item.stock)} ${App.escapeHtml(item.unit)}</b></span>
            </div>
            ${item.notes ? `<p class="text-[10px] text-slate-600 italic mt-0.5">&ldquo;${App.escapeHtml(item.notes)}&rdquo;</p>` : ''}
          </div>
          <button type="button" onclick="removeConsumableDraftItem(${idx})" class="w-8 h-8 rounded-lg bg-rose-100 hover:bg-rose-600 text-rose-700 hover:text-white flex items-center justify-center transition-colors shrink-0" title="Hapus item">
            <span class="material-symbols-outlined text-[16px]">delete</span>
          </button>
        </div>
      `).join('')}

      <div class="p-2.5 bg-amber-100/80 rounded-xl border border-amber-300 flex items-center justify-between text-xs font-bold text-amber-950">
        <span>Total Barang yang Diajukan:</span>
        <span class="font-mono font-black text-sm">${App.formatNumber(totalQty)} Pcs (${opConsumableDraft.length} SKU)</span>
      </div>
    </div>
  `;
}

function removeConsumableDraftItem(idx) {
  opConsumableDraft.splice(idx, 1);
  renderConsumableDraftList();
}

async function handleConsumableRequestSubmit() {
  const destSelect = document.getElementById('opReqDestinationSelect');
  const destination = destSelect ? destSelect.value.trim() : '';

  if (!destination) {
    App.toast('Silakan pilih Tujuan Brand / Line Produksi (HANASUI, NCO, FYNE, EOMMA)!', 'warning');
    destSelect?.focus();
    return;
  }

  if (opConsumableDraft.length === 0) {
    App.toast('Draft permintaan masih kosong! Tambahkan minimal 1 material consumable.', 'warning');
    return;
  }

  const priorityEl = document.querySelector('input[name="opReqPriority"]:checked');
  const priority = priorityEl ? priorityEl.value : 'NORMAL';
  const notes = document.getElementById('opReqGlobalNotes')?.value.trim() || '';

  const photoPayload = opConsumablePhotos.map(p => p.data);

  const btn = document.getElementById('btnSubmitConsumableRequest');
  btn.disabled = true;
  btn.innerHTML = '<span class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span><span>Mengirim Permintaan ke Admin...</span>';

  const res = await App.fetchJson('../api/consumable_requests.php?action=create', {
    method: 'POST',
    body: JSON.stringify({
      destination,
      priority,
      notes,
      items: opConsumableDraft,
      photos: photoPayload
    })
  });

  btn.disabled = false;
  btn.innerHTML = '<span class="material-symbols-outlined text-[18px]">send</span><span>Kirim Permintaan ke Admin (Minta ACC)</span>';

  if (res.success) {
    App.toast(res.message, 'success', 'Pengajuan Terkirim');
    opConsumableDraft = [];
    opConsumablePhotos = [];
    renderConsumableDraftList();
    renderOpReqPhotoPreviews();
    if (destSelect) destSelect.value = '';
    const globalNotesInp = document.getElementById('opReqGlobalNotes');
    if (globalNotesInp) globalNotesInp.value = '';

    if (typeof IS_FULFILLMENT_ONLY !== 'undefined' && IS_FULFILLMENT_ONLY) {
      loadFulfillmentStats();
    }

    // Switch to history tab to view real-time status
    switchOpReqSubTab('history');
  } else {
    App.toast(res.message || 'Gagal mengirim pengajuan', 'error');
  }
} let allOperatorConsumableRequests = [];
let openedOpReqCardIds = new Set();

function toggleOpReqCard(reqId) {
  const detailEl = document.getElementById(`opReqDetail_${reqId}`);
  const chevronEl = document.getElementById(`opReqChevron_${reqId}`);
  const labelEl = document.getElementById(`opReqToggleLabel_${reqId}`);
  if (!detailEl) return;

  const isHidden = detailEl.classList.contains('hidden');
  if (isHidden) {
    detailEl.classList.remove('hidden');
    openedOpReqCardIds.add(reqId);
    if (chevronEl) chevronEl.innerText = 'expand_less';
    if (labelEl) labelEl.innerText = 'Tutup Detail';
  } else {
    detailEl.classList.add('hidden');
    openedOpReqCardIds.delete(reqId);
    if (chevronEl) chevronEl.innerText = 'expand_more';
    if (labelEl) labelEl.innerText = 'Klik untuk Buka Detail';
  }
}

async function loadOperatorConsumableRequests(isSilent = false) {
  const container = document.getElementById('opReqHistoryContainer');
  if (!container) return;

  if (!isSilent && (!allOperatorConsumableRequests || allOperatorConsumableRequests.length === 0)) {
    container.innerHTML = `
      <div class="p-8 text-center text-slate-400 text-xs">
        <span class="material-symbols-outlined text-[28px] animate-spin text-amber-600 mb-1">progress_activity</span>
        <p>Memuat riwayat pengajuan consumable...</p>
      </div>
    `;
  }

  const res = await App.fetchJson('../api/consumable_requests.php?action=list');
  if (!res.success || !res.data || res.data.length === 0) {
    allOperatorConsumableRequests = [];
    container.innerHTML = `
      <div class="p-8 text-center text-slate-400 text-xs bg-white rounded-2xl border border-slate-200 shadow-xs">
        <span class="material-symbols-outlined text-[36px] text-slate-300 mb-1">inbox</span>
        <p class="font-bold text-slate-600">Belum ada riwayat pengajuan aktif.</p>
        <p class="text-[10px] text-slate-400 mt-0.5">Pengajuan yang belum selesai atau diserahkan hari ini akan tampil di sini.</p>
      </div>
    `;
    return;
  }

  allOperatorConsumableRequests = res.data;

  const pendingList = res.data.filter(r => r.status === 'PENDING');
  const myPendingBadge = document.getElementById('badgeOpReqMyPending');
  if (myPendingBadge) {
    if (pendingList.length > 0) {
      myPendingBadge.innerText = pendingList.length;
      myPendingBadge.classList.remove('hidden');
    } else {
      myPendingBadge.classList.add('hidden');
    }
  }

  const homeBadge = document.getElementById('homeBadgeConsumableReq');
  if (homeBadge) {
    if (pendingList.length > 0) {
      homeBadge.innerText = pendingList.length;
      homeBadge.classList.remove('hidden');
    } else {
      homeBadge.classList.add('hidden');
    }
  }

  container.innerHTML = res.data.map((req, idx) => {
    const isUrgent = req.priority === 'URGENT';
    const ho = req.handover_info || {};
    const stage = ho.stage || (req.status === 'APPROVED' ? 2 : (req.status === 'PENDING' ? 1 : 0));
    const simpleShift = (req.requester_shift || '').split('(')[0].trim() || (req.requester_shift || 'Shift');

    // Default open state: open if user previously opened it, otherwise keep collapsed
    const isOpen = openedOpReqCardIds.has(req.id);

    // Status Pill Badge
    let statusBadge = '';
    if (req.status === 'PENDING') {
      statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-amber-100 text-amber-900 border border-amber-300 inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>Menunggu ACC</span>';
    } else if (req.status === 'APPROVED') {
      if (ho.is_handed_over) {
        statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-900 border border-emerald-300 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[13px] text-emerald-700">task_alt</span>Selesai Diserahkan</span>';
      } else {
        statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-blue-100 text-blue-900 border border-blue-300 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[13px] text-blue-700 animate-spin">sync</span>Diproses Gudang</span>';
      }
    } else if (req.status === 'REJECTED') {
      statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 border border-rose-300 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[12px] text-rose-600">cancel</span>Ditolak Admin</span>';
    } else if (req.status === 'CANCELLED') {
      statusBadge = '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-300">Dibatalkan</span>';
    } else {
      statusBadge = `<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">${req.status}</span>`;
    }

    // 1. VISUAL PROGRESS TRACKER (STEPPER)
    let stepperHtml = '';
    if (req.status === 'REJECTED') {
      stepperHtml = `
        <div class="p-2.5 bg-rose-50/70 border border-rose-200 rounded-xl space-y-1.5">
          <div class="flex items-center justify-between text-[11px] font-extrabold text-rose-900">
            <span class="flex items-center gap-1">
              <span class="material-symbols-outlined text-[16px] text-rose-600">cancel</span>
              <span>Pengajuan Ditolak oleh Admin</span>
            </span>
            <span class="text-[10px] text-rose-700">${req.approved_at ? App.formatDate(req.approved_at) : ''}</span>
          </div>
          ${req.admin_notes ? `<p class="text-[11px] text-rose-800 bg-white/80 p-2 rounded-lg border border-rose-200"><strong>Alasan:</strong> ${App.escapeHtml(req.admin_notes)}</p>` : ''}
        </div>
      `;
    } else if (req.status === 'CANCELLED') {
      stepperHtml = `
        <div class="p-2.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-600 text-[11px] font-semibold flex items-center gap-1.5">
          <span class="material-symbols-outlined text-[16px] text-slate-500">block</span>
          <span>Pengajuan ini telah dibatalkan oleh pemohon.</span>
        </div>
      `;
    } else {
      const step1Done = true;
      const step2Done = (stage >= 2);
      const step2Active = (stage === 1);
      const step3Done = (stage >= 3);
      const step3Active = (stage === 2);

      stepperHtml = `
        <div class="p-3 bg-gradient-to-r from-slate-50 to-amber-50/30 rounded-xl border border-slate-200/80 space-y-2.5">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px] text-amber-600">timeline</span>
              <span>Progres Fulfillment:</span>
            </span>
            <span class="text-[10px] font-extrabold font-mono px-2 py-0.5 rounded-full ${step3Done ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : (step2Done ? 'bg-blue-100 text-blue-900 border border-blue-300' : 'bg-amber-100 text-amber-900 border border-amber-300')}">
              ${step3Done ? '3/3 Selesai (Diserahkan)' : (step2Done ? '2/3 Disetujui (Picking)' : '1/3 Menunggu ACC')}
            </span>
          </div>

          <div class="relative grid grid-cols-3 gap-2 px-1 pt-1 pb-1">
            <div class="absolute left-8 right-8 top-4.5 h-1 bg-slate-200 z-0">
              <div class="h-full ${step3Done ? 'bg-emerald-500 w-full' : (step2Done ? 'bg-blue-500 w-1/2' : 'bg-amber-500 w-0')} transition-all duration-500"></div>
            </div>

            <div class="relative z-10 flex flex-col items-center text-center group cursor-default">
              <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center shadow-xs border-2 border-white">
                <span class="material-symbols-outlined text-[16px] font-bold">check</span>
              </div>
              <span class="text-[10.5px] font-black text-emerald-950 mt-1">1. Diajukan</span>
              <span class="text-[9.5px] font-bold text-slate-800 truncate max-w-[100px] flex items-center gap-0.5 justify-center" title="Pemohon: ${App.escapeHtml(req.requester_name || 'Operator')}">
                <span class="material-symbols-outlined text-[11px] text-slate-400">person</span>
                <span>${App.escapeHtml(req.requester_name || 'Operator')}</span>
              </span>
              <span class="text-[8.5px] text-slate-500 font-mono font-medium">${App.formatDate(req.created_at)}</span>
            </div>

            <div class="relative z-10 flex flex-col items-center text-center group cursor-default">
              <div class="w-8 h-8 rounded-full ${step2Done ? 'bg-emerald-500 text-white' : (step2Active ? 'bg-amber-500 text-white ring-4 ring-amber-100 animate-pulse' : 'bg-slate-200 text-slate-400')} flex items-center justify-center shadow-xs border-2 border-white">
                <span class="material-symbols-outlined text-[16px]">${step2Done ? 'check' : (step2Active ? 'hourglass_top' : 'inventory_2')}</span>
              </div>
              <span class="text-[10.5px] font-black ${step2Done ? 'text-emerald-950' : (step2Active ? 'text-amber-900' : 'text-slate-400')} mt-1">2. ACC Admin</span>
              ${step2Done ? `
                <span class="text-[9.5px] font-bold text-slate-800 truncate max-w-[100px] flex items-center gap-0.5 justify-center" title="Disetujui: ${App.escapeHtml(req.approver_name || 'Admin')}">
                  <span class="material-symbols-outlined text-[11px] text-emerald-600">verified_user</span>
                  <span>${App.escapeHtml(req.approver_name || 'Admin')}</span>
                </span>
                <span class="text-[8.5px] text-slate-500 font-mono font-medium">${req.approved_at ? App.formatDate(req.approved_at) : '-'}</span>
              ` : `
                <span class="text-[9px] text-amber-700 font-semibold">${step2Active ? 'Menunggu ACC' : '-'}</span>
                <span class="text-[8.5px] text-slate-400 font-mono">${step2Active ? 'Antrean Admin' : ''}</span>
              `}
            </div>

            <div class="relative z-10 flex flex-col items-center text-center group cursor-default">
              <div class="w-8 h-8 rounded-full ${step3Done ? 'bg-emerald-600 text-white ring-2 ring-emerald-200' : (step3Active ? 'bg-blue-600 text-white ring-4 ring-blue-100 animate-pulse' : 'bg-slate-200 text-slate-400')} flex items-center justify-center shadow-xs border-2 border-white">
                <span class="material-symbols-outlined text-[16px]">${step3Done ? 'verified' : (step3Active ? 'local_shipping' : 'handshake')}</span>
              </div>
              <span class="text-[10.5px] font-black ${step3Done ? 'text-emerald-950' : (step3Active ? 'text-blue-900' : 'text-slate-400')} mt-1">3. Diserahkan</span>
              ${step3Done ? `
                <span class="text-[9.5px] font-bold text-slate-800 truncate max-w-[100px] flex items-center gap-0.5 justify-center" title="Diserahkan: ${App.escapeHtml(ho.penyerah_name || 'Gudang')}">
                  <span class="material-symbols-outlined text-[11px] text-emerald-600">handshake</span>
                  <span>${App.escapeHtml(ho.penyerah_name || 'Gudang')}</span>
                </span>
                <span class="text-[8.5px] text-slate-500 font-mono font-medium">${ho.handover_time ? App.formatDate(ho.handover_time) : (req.approved_at ? App.formatDate(req.approved_at) : '-')}</span>
              ` : `
                <span class="text-[9px] ${step3Active ? 'text-blue-700 font-bold' : 'text-slate-400'}">${step3Active ? (ho.penyerah_name || 'Picking') : '-'}</span>
                <span class="text-[8.5px] ${step3Active ? 'text-blue-600 font-mono font-semibold' : 'text-slate-400'}">${step3Active ? 'Proses Ambil' : ''}</span>
              `}
            </div>
          </div>
        </div>
      `;
    }

    let handoverBoxHtml = '';
    if (stage >= 2 && req.status === 'APPROVED') {
      const hasHandoverPhotos = ho.handover_photos && ho.handover_photos.length > 0;
      handoverBoxHtml = `
        <div class="p-3 rounded-xl border ${ho.is_handed_over ? 'bg-emerald-50/60 border-emerald-200' : 'bg-blue-50/60 border-blue-200'} space-y-2 text-xs">
          <div class="flex items-center justify-between border-b ${ho.is_handed_over ? 'border-emerald-200/80' : 'border-blue-200/80'} pb-1.5">
            <span class="font-extrabold text-[11px] ${ho.is_handed_over ? 'text-emerald-950' : 'text-blue-950'} flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[16px] ${ho.is_handed_over ? 'text-emerald-700' : 'text-blue-700'}">${ho.is_handed_over ? 'local_shipping' : 'pending_actions'}</span>
              <span>${ho.is_handed_over ? 'Bukti Serah Terima & Handover Barang' : 'Status Pengeluaran Barang (Gudang)'}</span>
            </span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded ${ho.is_handed_over ? 'bg-emerald-200 text-emerald-900' : 'bg-blue-200 text-blue-900'}">
              ${ho.is_handed_over ? 'Barang Sudah Dikeluarkan' : 'Sedang Disiapkan'}
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[11px]">
            <div class="p-2 bg-white/80 rounded-lg border border-slate-200/70 space-y-0.5">
              <span class="text-[10px] text-slate-500 font-bold block uppercase tracking-wider">${ho.is_handed_over ? 'Diserahkan Oleh (Gudang):' : 'Petugas Picking (Gudang):'}</span>
              <p class="font-extrabold text-slate-900 flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px] text-amber-600">badge</span>
                <span>${App.escapeHtml(ho.penyerah_name || req.approver_name || 'Tim Gudang')}</span>
              </p>
            </div>

            <div class="p-2 bg-white/80 rounded-lg border border-slate-200/70 space-y-0.5">
              <span class="text-[10px] text-slate-500 font-bold block uppercase tracking-wider">Diterima Oleh (Fulfillment / Line):</span>
              <p class="font-extrabold text-slate-900 flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px] ${ho.is_handed_over ? 'text-emerald-600' : 'text-slate-400'}">${ho.is_handed_over ? 'person' : 'hourglass_empty'}</span>
                <span>${ho.is_handed_over ? `${App.escapeHtml(ho.penerima_name || req.requester_name || 'Line Fulfillment')} &bull; ${App.escapeHtml(req.destination)}` : '<span class="text-slate-400 font-normal italic">Belum Diterima (Menunggu Serah Terima)</span>'}</span>
              </p>
            </div>
          </div>

          ${ho.handover_notes ? `
            <div class="text-[11px] bg-white/90 p-2 rounded-lg border border-slate-200 text-slate-700">
              <span class="font-bold text-slate-800">Catatan Serah Terima:</span> &ldquo;${App.escapeHtml(ho.handover_notes)}&rdquo;
            </div>
          ` : ''}

          ${hasHandoverPhotos ? `
            <div class="pt-1.5 border-t ${ho.is_handed_over ? 'border-emerald-200/60' : 'border-blue-200/60'} space-y-1.5">
              <span class="text-[10px] font-extrabold text-slate-700 flex items-center gap-1 uppercase tracking-wider">
                <span class="material-symbols-outlined text-[14px] text-emerald-700">photo_camera</span>
                <span>Foto Handover & Bukti Barang Keluar (${ho.handover_photos.length}):</span>
              </span>
              <div class="flex items-center gap-2 overflow-x-auto pb-1">
                ${ho.handover_photos.map((ph, idx) => `
                  <a href="../${ph}" target="_blank" class="block shrink-0 w-16 h-16 rounded-xl overflow-hidden border-2 border-emerald-300 shadow-xs hover:border-emerald-600 hover:scale-105 transition-all relative group" title="Klik untuk memperbesar Foto Handover ${idx + 1}">
                    <img src="../${ph}" alt="Foto Handover ${idx + 1}" class="w-full h-full object-cover">
                    <span class="absolute inset-0 bg-slate-950/20 opacity-0 group-hover:opacity-100 flex items-center justify-center text-white transition-opacity">
                      <span class="material-symbols-outlined text-[16px]">zoom_in</span>
                    </span>
                  </a>
                `).join('')}
              </div>
            </div>
          ` : (ho.is_handed_over ? `
            <p class="text-[10px] text-slate-500 italic flex items-center gap-1">
              <span class="material-symbols-outlined text-[13px] text-slate-400">info</span>
              <span>Barang telah dikeluarkan & tercatat di mutasi stok barang keluar.</span>
            </p>
          ` : '')}
        </div>
      `;
    }

    return `
      <div class="bg-white rounded-2xl border ${isUrgent ? 'border-rose-300 ring-2 ring-rose-50' : 'border-slate-200'} shadow-xs transition-all overflow-hidden">
        
        <!-- Clickable Header Card -->
        <div onclick="toggleOpReqCard(${req.id})" class="p-3.5 hover:bg-slate-50/80 active:bg-slate-100 cursor-pointer transition-colors space-y-2">
          
          <!-- Top Row: No Request, Badges & Status -->
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-1.5 flex-wrap">
              <span class="font-mono font-black text-amber-900 text-xs">${App.escapeHtml(req.request_no)}</span>
              ${isUrgent ? '<span class="px-1.5 py-0.2 rounded bg-rose-100 text-rose-800 font-extrabold text-[9px] border border-rose-300">URGENT</span>' : ''}
              <span class="px-2 py-0.2 rounded bg-slate-100 text-slate-800 font-black text-[10px] border border-slate-200">${App.escapeHtml(req.destination)}</span>
            </div>
            <div class="text-right flex items-center gap-1.5">
              ${statusBadge}
              <div class="w-6 h-6 rounded-lg bg-slate-100 flex items-center justify-center text-slate-500 hover:text-slate-800 transition-transform">
                <span id="opReqChevron_${req.id}" class="material-symbols-outlined text-[18px] transition-transform">${isOpen ? 'expand_less' : 'expand_more'}</span>
              </div>
            </div>
          </div>

          <!-- Bottom Summary Row -->
          <div class="flex items-center justify-between text-[10.5px] text-slate-500 pt-0.5">
            <div class="truncate">
              <span>Pemohon: <b class="text-slate-800 font-bold">${App.escapeHtml(req.requester_name || 'Operator')}</b> (${App.escapeHtml(simpleShift)}) &bull; ${App.formatDate(req.created_at)}</span>
            </div>
            <div class="shrink-0 text-right font-bold text-amber-950 font-mono">
              <span>${req.items ? req.items.length : 0} Item (${App.formatNumber(req.total_qty || 0)} Qty)</span>
            </div>
          </div>

          <!-- Mini Quick Actions Bar -->
          <div class="pt-1.5 border-t border-slate-100 flex items-center justify-between text-[11px]" onclick="event.stopPropagation()">
            <span class="text-[10px] font-bold text-amber-700 flex items-center gap-0.5 cursor-pointer hover:underline" onclick="toggleOpReqCard(${req.id})">
              <span class="material-symbols-outlined text-[14px]">touch_app</span>
              <span id="opReqToggleLabel_${req.id}">${isOpen ? 'Tutup Detail' : 'Klik untuk Buka Detail'}</span>
            </span>

            <button type="button" onclick="shareConsumableRequest(${req.id})" class="px-2.5 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border border-emerald-200 hover:border-emerald-300 rounded-lg text-[10.5px] font-extrabold transition-all inline-flex items-center gap-1 active:scale-95 shadow-2xs cursor-pointer">
              <span class="material-symbols-outlined text-[14px] text-emerald-600">share</span>
              <span>Bagikan (WA)</span>
            </button>
          </div>

        </div>

        <!-- Expandable Detail Body (Hidden by default, open on click) -->
        <div id="opReqDetail_${req.id}" class="${isOpen ? '' : 'hidden'} p-3.5 pt-0 border-t border-slate-100 space-y-3 bg-slate-50/40">
          
          <!-- 1. Visual Progress Stepper -->
          <div class="pt-3">
            ${stepperHtml}
          </div>

          <!-- 2. Items List -->
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Daftar Material Permintaan (${req.items ? req.items.length : 0} Item)</span>
              <span class="text-[10px] font-bold text-amber-900 font-mono">Total Qty: ${App.formatNumber(req.total_qty || 0)}</span>
            </div>
            <div class="divide-y divide-slate-100 bg-white rounded-xl border border-slate-200 p-2 text-xs shadow-2xs">
              ${(req.items || []).map(it => `
                <div class="py-1.5 flex items-center justify-between gap-2 first:pt-0 last:pb-0">
                  <div class="min-w-0 flex-1">
                    <p class="font-bold text-slate-800 text-[11px] truncate">${App.escapeHtml(it.material_name)}</p>
                    <p class="font-mono text-[9px] text-slate-400">${App.escapeHtml(it.material_code)} ${it.rack_location ? `&bull; Rak: ${App.escapeHtml(it.rack_location)}` : ''}</p>
                  </div>
                  <div class="text-right shrink-0">
                    <span class="font-mono font-black text-amber-900 text-xs">${App.formatNumber(it.qty)}</span>
                    <span class="text-[10px] text-slate-500">${App.escapeHtml(it.material_unit || 'Pcs')}</span>
                  </div>
                </div>
              `).join('')}
            </div>
          </div>

          <!-- 3. Notes & Uploaded Request Photos -->
          ${req.notes ? `
            <div class="p-2 bg-white rounded-xl border border-slate-200 text-[11px] text-slate-700 shadow-2xs">
              <span class="font-semibold text-slate-500">Catatan Pemohon:</span> &ldquo;${App.escapeHtml(req.notes)}&rdquo;
            </div>
          ` : ''}

          <!-- 4. Handover & Goods Issue Details (Penyerah, Penerima, Foto Handover) -->
          ${handoverBoxHtml}

          <!-- 5. Admin Response Box -->
          ${req.admin_notes && req.status !== 'REJECTED' ? `
            <div class="p-2.5 rounded-xl border bg-emerald-50/70 border-emerald-200 text-emerald-900 text-xs">
              <p class="font-extrabold text-[10px] flex items-center gap-1 uppercase tracking-wider mb-0.5">
                <span class="material-symbols-outlined text-[13px]">verified</span>
                <span>Respon Admin (${App.escapeHtml(req.approver_name || 'Admin')}):</span>
              </p>
              <p class="text-[11px] font-semibold">${App.escapeHtml(req.admin_notes)}</p>
            </div>
          ` : ''}

          <!-- 6. Action Footer (Cancel Request if Pending) -->
          ${req.status === 'PENDING' ? `
            <div class="pt-2 border-t border-slate-200/80 flex items-center justify-end">
              <button type="button" onclick="cancelOperatorConsumableRequest(${req.id})" class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl text-[11px] font-bold transition-colors inline-flex items-center gap-1 cursor-pointer active:scale-95">
                <span class="material-symbols-outlined text-[14px]">close</span>
                <span>Batalkan Pengajuan</span>
              </button>
            </div>
          ` : ''}

        </div>

      </div>
    `;
  }).join('');
}

// =========================================================================
// 3. SHARE CONSUMABLE REQUEST TO INVENTORY CONSUMABLE (WHATSAPP CAPTION DIRECT)
// =========================================================================

async function shareConsumableRequest(id) {
  const req = (allOperatorConsumableRequests || []).find(r => r.id === id);
  if (!req) {
    App.toast('Data pengajuan tidak ditemukan', 'error');
    return;
  }

  const isUrgent = req.priority === 'URGENT';
  const ho = req.handover_info || {};

  let statusText = '🟡 MENUNGGU ACC ADMIN';
  if (req.status === 'APPROVED') {
    statusText = ho.is_handed_over ? '🟢 SELESAI DISERAHKAN (HANDOVER DONE)' : '🔵 DISETUJUI ADMIN (SEDANG PICKING GUDANG)';
  } else if (req.status === 'REJECTED') {
    statusText = '🔴 DITOLAK ADMIN';
  } else if (req.status === 'CANCELLED') {
    statusText = '⚪ DIBATALKAN';
  }

  let itemsText = '';
  if (req.items && req.items.length > 0) {
    itemsText = req.items.map((it, idx) => {
      return `${idx + 1}. *${it.material_name}* : *${App.formatNumber(it.qty)} ${it.material_unit || 'Pcs'}*`;
    }).join('\n');
  } else {
    itemsText = '- Tidak ada rincian item';
  }

  let handoverSummaryText = '';
  if (ho && ho.is_handed_over) {
    handoverSummaryText = `\n━━━━━━━━━━━━━━━━━━━━━━━━━━\n📦 *BUKTI SERAH TERIMA GUDANG:*\n• *Diserahkan Oleh:* ${ho.penyerah_name || 'Staff Inventory'}\n• *Diterima Oleh:* ${ho.penerima_name || req.requester_name || 'Line Fulfillment'}\n• *Waktu Selesai:* ${ho.handover_time ? App.formatDate(ho.handover_time) : '-'}\n${ho.handover_notes ? `• *Catatan:* ${ho.handover_notes}\n` : ''}`;
  }

  const simpleShift = (req.requester_shift || '').split('(')[0].trim() || (req.requester_shift || 'Shift');

  const shareCaption = `📦 *KONFIRMASI PERMINTAAN CONSUMABLE MATERIAL*
━━━━━━━━━━━━━━━━━━━━━━━━━━
Halo Tim Gudang / Inventory Consumable,
Pengajuan material telah dikirimkan dari tim Fulfillment:

📋 *No. Request:* #${req.request_no}
🏷️ *Tujuan Brand / Line:* *${req.destination}*
⚡ *Prioritas:* *${req.priority}* ${isUrgent ? '🚨 (MENDESAK)' : ''}
👤 *Pemohon (PIC):* ${req.requester_name || 'Operator'} (${simpleShift})
📅 *Waktu Pengajuan:* ${App.formatDate(req.created_at)}
📊 *Status Saat Ini:* ${statusText}

📦 *Daftar Material yang Diajukan:*
${itemsText}
${req.notes ? `\n📝 *Catatan Pemohon:* "${req.notes}"` : ''}${handoverSummaryText}
━━━━━━━━━━━━━━━━━━━━━━━━━━
_Dibuat otomatis via PackStock WMS (Fulfillment System)_`;

  // 1. Try Native Mobile Web Share API if available
  if (navigator.share) {
    try {
      await navigator.share({
        title: `Permintaan Consumable #${req.request_no}`,
        text: shareCaption
      });
      App.toast('Permintaan consumable berhasil dibagikan!', 'success');
      return;
    } catch (err) {
      if (err.name === 'AbortError') return;
      // if canceled or unsupported, fallback to WA link
    }
  }

  // 2. Direct WhatsApp Web Link
  const waUrl = `https://api.whatsapp.com/send?text=${encodeURIComponent(shareCaption)}`;
  window.open(waUrl, '_blank');

  // Copy to clipboard for easy pasting
  if (navigator.clipboard && navigator.clipboard.writeText) {
    try {
      await navigator.clipboard.writeText(shareCaption);
      App.toast('Teks caption telah disalin ke clipboard & WhatsApp dibuka!', 'success');
    } catch (e) { }
  }
}

async function cancelOperatorConsumableRequest(id) {
  if (!confirm('Apakah Anda yakin ingin membatalkan pengajuan consumable ini?')) return;

  const res = await App.fetchJson('../api/consumable_requests.php?action=cancel', {
    method: 'POST',
    body: JSON.stringify({ request_id: id })
  });

  if (res.success) {
    App.toast(res.message, 'success');
    loadOperatorConsumableRequests();
    if (typeof IS_FULFILLMENT_ONLY !== 'undefined' && IS_FULFILLMENT_ONLY) {
      loadFulfillmentStats();
    }
  } else {
    App.toast(res.message || 'Gagal membatalkan pengajuan', 'error');
  }
}

async function loadFulfillmentStats() {
  const res = await App.fetchJson('../api/consumable_requests.php?action=list');
  if (res.success && res.data) {
    const total = res.data.length;
    const pending = res.data.filter(r => r.status === 'PENDING').length;
    const approved = res.data.filter(r => r.status === 'APPROVED').length;

    const totalEl = document.getElementById('homeStatFulfillmentTotal');
    const pendingEl = document.getElementById('homeStatFulfillmentPending');
    const approvedEl = document.getElementById('homeStatFulfillmentApproved');
    const badgeEl = document.getElementById('homeBadgeConsumableReq');

    if (totalEl) totalEl.innerText = App.formatNumber(total);
    if (pendingEl) pendingEl.innerText = App.formatNumber(pending);
    if (approvedEl) approvedEl.innerText = App.formatNumber(approved);

    if (badgeEl) {
      if (pending > 0) {
        badgeEl.innerText = pending;
        badgeEl.classList.remove('hidden');
      } else {
        badgeEl.classList.add('hidden');
      }
    }
  }
}




