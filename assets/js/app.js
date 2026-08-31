// assets/js/app.js - Global App Helpers & Toast Notifications with Google Material Symbols

const App = {
  // Toast Notification System
  toast(message, type = 'success', title = '') {
    let toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
      toastContainer = document.createElement('div');
      toastContainer.id = 'toast-container';
      toastContainer.className = 'fixed top-4 right-4 z-[9999] flex flex-col gap-2 max-w-sm pointer-events-none';
      document.body.appendChild(toastContainer);
    }

    const toastEl = document.createElement('div');
    toastEl.className = `pointer-events-auto transform transition-all duration-200 translate-y-[-10px] opacity-0 flex items-start gap-2.5 p-3.5 rounded-xl shadow-lg border text-xs font-medium ${
      type === 'success' ? 'bg-slate-900 text-white border-emerald-600' :
      type === 'error' ? 'bg-slate-900 text-white border-rose-600' :
      type === 'warning' ? 'bg-slate-900 text-white border-amber-600' :
      'bg-slate-900 text-white border-blue-600'
    }`;

    const iconMap = {
      success: '<span class="material-symbols-outlined text-emerald-400 text-[20px]">check_circle</span>',
      error: '<span class="material-symbols-outlined text-rose-400 text-[20px]">error</span>',
      warning: '<span class="material-symbols-outlined text-amber-400 text-[20px]">warning</span>',
      info: '<span class="material-symbols-outlined text-blue-400 text-[20px]">info</span>'
    };

    const defaultTitle = {
      success: 'Sukses',
      error: 'Kesalahan',
      warning: 'Peringatan',
      info: 'Informasi'
    };

    toastEl.innerHTML = `
      <div class="flex-shrink-0 mt-0.5">
        ${iconMap[type] || iconMap.info}
      </div>
      <div class="flex-1">
        <h5 class="font-bold uppercase tracking-wider text-slate-300 text-[10px] mb-0.5">${title || defaultTitle[type]}</h5>
        <p class="text-slate-100 leading-snug">${message}</p>
      </div>
      <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-white p-0.5">
        <span class="material-symbols-outlined text-[16px]">close</span>
      </button>
    `;

    toastContainer.appendChild(toastEl);

    // Enter animation
    requestAnimationFrame(() => {
      toastEl.classList.remove('translate-y-[-10px]', 'opacity-0');
      toastEl.classList.add('translate-y-0', 'opacity-100');
    });

    // Auto remove
    setTimeout(() => {
      toastEl.classList.add('opacity-0', 'translate-y-[-5px]');
      setTimeout(() => toastEl.remove(), 200);
    }, 4000);
  },

  // Universal In-App Confirmation Modal (Promise-based replacement for window.confirm)
  confirm({
    title = 'Konfirmasi Tindakan',
    message = 'Apakah Anda yakin ingin melanjutkan?',
    confirmText = 'Ya, Lanjutkan',
    cancelText = 'Batal',
    type = 'emerald',
    icon = 'help'
  } = {}) {
    return new Promise((resolve) => {
      let modal = document.getElementById('app-confirm-dialog');
      if (!modal) {
        modal = document.createElement('div');
        modal.id = 'app-confirm-dialog';
        modal.className = 'fixed inset-0 z-[100000] bg-slate-950/80 backdrop-blur-xs hidden items-end sm:items-center justify-center p-4';
        document.body.appendChild(modal);
      }

      const colorMap = {
        emerald: {
          bg: 'bg-emerald-100',
          text: 'text-emerald-700',
          btn: 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-700/25',
          icon: icon === 'help' ? 'check_circle' : icon
        },
        rose: {
          bg: 'bg-rose-100',
          text: 'text-rose-700',
          btn: 'bg-rose-600 hover:bg-rose-700 shadow-rose-700/25',
          icon: icon === 'help' ? 'warning' : icon
        },
        amber: {
          bg: 'bg-amber-100',
          text: 'text-amber-700',
          btn: 'bg-amber-600 hover:bg-amber-700 shadow-amber-700/25',
          icon: icon === 'help' ? 'help' : icon
        },
        blue: {
          bg: 'bg-blue-100',
          text: 'text-blue-700',
          btn: 'bg-blue-600 hover:bg-blue-700 shadow-blue-700/25',
          icon: icon === 'help' ? 'info' : icon
        }
      };

      const c = colorMap[type] || colorMap.emerald;

      modal.innerHTML = `
        <div class="bg-white rounded-3xl max-w-sm w-full p-5 sm:p-6 shadow-2xl border border-slate-200 space-y-4 animate-scale-up text-center">
          <div class="w-14 h-14 rounded-2xl ${c.bg} ${c.text} flex items-center justify-center mx-auto shadow-inner">
            <span class="material-symbols-outlined text-[30px]">${c.icon}</span>
          </div>
          <div>
            <h3 class="font-black text-slate-900 text-sm tracking-tight leading-snug">${title}</h3>
            <p class="text-xs text-slate-600 mt-1 leading-relaxed">${message}</p>
          </div>
          <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100">
            <button type="button" id="appConfirmCancelBtn" class="py-2.5 px-4 bg-slate-100 hover:bg-slate-200 active:scale-95 text-slate-700 font-bold rounded-xl text-xs transition-colors cursor-pointer">
              ${cancelText}
            </button>
            <button type="button" id="appConfirmOkBtn" class="py-2.5 px-4 ${c.btn} active:scale-95 text-white font-extrabold rounded-xl text-xs shadow-md transition-all flex items-center justify-center gap-1 cursor-pointer">
              <span>${confirmText}</span>
            </button>
          </div>
        </div>
      `;

      modal.classList.remove('hidden');
      modal.classList.add('flex');

      const cleanup = (val) => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        resolve(val);
      };

      document.getElementById('appConfirmOkBtn').onclick = () => cleanup(true);
      document.getElementById('appConfirmCancelBtn').onclick = () => cleanup(false);
    });
  },

  // Generic JSON Fetch Helper
  async fetchJson(url, options = {}) {
    try {
      const defaultHeaders = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      };

      if (!(options.body instanceof FormData)) {
        defaultHeaders['Content-Type'] = 'application/json';
      }

      const response = await fetch(url, {
        ...options,
        headers: {
          ...defaultHeaders,
          ...(options.headers || {})
        }
      });

      if (response.status === 401) {
        const isLoginPage = window.location.pathname.endsWith('/login') || window.location.pathname.endsWith('/login.php') || window.location.pathname.endsWith('/packstock/') || window.location.pathname.endsWith('/packstock');
        if (!isLoginPage) {
          App.toast('Sesi login telah berakhir. Mengalihkan ke login...', 'warning');
          const isSubdir = window.location.pathname.includes('/admin') || window.location.pathname.includes('/operator');
          const loginTarget = isSubdir ? '../login' : 'login';
          setTimeout(() => { window.location.href = loginTarget; }, 1000);
        }
        try {
          const json = await response.json();
          return json;
        } catch (e) {
          return { success: false, message: 'Unauthorized / Sesi Berakhir' };
        }
      }

      const data = await response.json();
      return data;
    } catch (err) {
      console.error('Fetch error:', err);
      return { success: false, message: 'Gagal terhubung ke server database.' };
    }
  },

  // Modal helpers
  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove('hidden');
      modal.classList.add('flex');
    }
  },

  closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
    }
  },

  // Number & Date formatters
  formatNumber(num) {
    if (num === null || num === undefined || num === '') return '0';
    const n = Number(num);
    if (isNaN(n)) return '0';
    return new Intl.NumberFormat('id-ID', {
      maximumFractionDigits: 3
    }).format(n);
  },

  formatDate(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleString('id-ID', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  },

  formatTime(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  },

  formatDuration(sec) {
    sec = Math.round(Number(sec) || 0);
    if (sec <= 0) return '0 dtk';
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    if (h > 0) return `${h} jam ${m} mnt`;
    if (m > 0) return `${m} mnt ${s} dtk`;
    return `${s} dtk`;
  },

  formatTaktTime(secPerUnit) {
    const val = Number(secPerUnit) || 0;
    if (val <= 0) return '-';
    if (val < 60) return `${val.toFixed(1)} dtk/item`;
    return `${(val / 60).toFixed(1)} mnt/item`;
  },

  escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  },

  // ================= UNIVERSAL SEARCHABLE DROPDOWN =================
  initSearchableSelect(select) {
    if (!select) return;
    
    // If already initialized, just sync
    if (select.dataset.ssInit === 'true') {
      if (select._ssUpdate) select._ssUpdate();
      return;
    }
    
    select.dataset.ssInit = 'true';
    select.style.display = 'none';

    // Determine wrapper layout from original select
    const isFullWidth = select.classList.contains('w-full') || select.closest('form') !== null && !select.closest('.flex-wrap');
    const wrapper = document.createElement('div');
    wrapper.className = isFullWidth 
      ? 'ss-wrapper relative w-full block text-left' 
      : 'ss-wrapper relative inline-block min-w-[160px] max-w-[260px] align-middle text-left';
    
    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const isMaterialSelect = select.id.toLowerCase().includes('material') || select.className.includes('material');
    const isOperatorSelect = select.id.toLowerCase().includes('operator') || select.className.includes('operator') || select.id.toLowerCase().includes('user');
    
    let defaultIcon = 'tune';
    if (isMaterialSelect) defaultIcon = 'inventory_2';
    else if (isOperatorSelect) defaultIcon = 'person';

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'ss-trigger w-full h-[38px] px-3 bg-slate-50 hover:bg-white border border-slate-300 focus:border-emerald-600 rounded-lg text-xs font-medium text-slate-800 flex items-center justify-between gap-2 shadow-2xs transition-all cursor-pointer outline-none text-left';
    
    const triggerLeft = document.createElement('div');
    triggerLeft.className = 'flex items-center gap-2 flex-1 text-left min-w-0 overflow-hidden';

    const triggerPrefixIcon = document.createElement('span');
    triggerPrefixIcon.className = 'material-symbols-outlined text-[17px] text-emerald-700 flex-shrink-0';
    triggerPrefixIcon.innerText = defaultIcon;

    const triggerText = document.createElement('span');
    triggerText.className = 'ss-trigger-text text-xs font-semibold text-slate-800 text-left truncate flex-1';

    triggerLeft.appendChild(triggerPrefixIcon);
    triggerLeft.appendChild(triggerText);

    const triggerIcon = document.createElement('span');
    triggerIcon.className = 'material-symbols-outlined ss-trigger-icon text-[18px] text-slate-400 flex-shrink-0 transition-transform';
    triggerIcon.innerText = 'expand_more';

    trigger.appendChild(triggerLeft);
    trigger.appendChild(triggerIcon);
    wrapper.appendChild(trigger);

    // Create dropdown menu directly appended to document.body to prevent ANY table/modal overflow clipping
    const dropdown = document.createElement('div');
    dropdown.className = 'ss-dropdown';
    dropdown.style.display = 'none';

    const searchBox = document.createElement('div');
    searchBox.className = 'ss-search-box relative mb-2';

    const searchIcon = document.createElement('span');
    searchIcon.className = 'material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-[16px] pointer-events-none';
    searchIcon.innerText = 'search';

    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'ss-search-input w-full pl-8 pr-2.5 py-2 bg-slate-50 border border-slate-300 rounded-lg text-xs font-medium text-slate-900 outline-none focus:border-emerald-600 focus:bg-white shadow-2xs';
    searchInput.placeholder = isMaterialSelect ? 'Ketik nama packaging / kode item...' : (isOperatorSelect ? 'Ketik nama operator...' : 'Cari pilihan...');
    searchInput.autocomplete = 'off';

    searchBox.appendChild(searchIcon);
    searchBox.appendChild(searchInput);
    dropdown.appendChild(searchBox);

    const optionsList = document.createElement('div');
    optionsList.className = 'ss-options-list max-h-80 overflow-y-auto space-y-1';
    dropdown.appendChild(optionsList);
    
    // Append to document.body so it floats freely above all elements
    document.body.appendChild(dropdown);

    function updateTriggerText() {
      const selectedOption = select.options[select.selectedIndex];
      if (selectedOption && selectedOption.value) {
        triggerText.innerText = selectedOption.text;
        triggerPrefixIcon.classList.remove('text-slate-400');
        triggerPrefixIcon.classList.add('text-emerald-700');
      } else {
        triggerText.innerText = selectedOption ? selectedOption.text : '-- Pilih --';
        triggerPrefixIcon.classList.add('text-slate-400');
        triggerPrefixIcon.classList.remove('text-emerald-700');
      }
    }

    function renderOptions(filter = '') {
      optionsList.innerHTML = '';
      const term = filter.toLowerCase().trim();
      let matchCount = 0;

      for (let i = 0; i < select.options.length; i++) {
        const opt = select.options[i];
        const text = opt.text;
        const value = opt.value;
        const code = (opt.getAttribute('data-code') || '').toLowerCase();
        const name = (opt.getAttribute('data-name') || '').toLowerCase();
        const isSelected = (i === select.selectedIndex);

        if (term === '' || text.toLowerCase().includes(term) || value.toLowerCase().includes(term) || code.includes(term) || name.includes(term)) {
          matchCount++;
          const optEl = document.createElement('div');
          optEl.className = 'ss-option px-3 py-2 rounded-lg text-xs cursor-pointer flex items-start justify-between gap-2 transition-colors ' + 
            (isSelected ? 'bg-emerald-50 text-emerald-900 font-bold border border-emerald-300' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900 font-medium');
          optEl.dataset.value = value;
          optEl.dataset.index = i;

          const leftContent = document.createElement('div');
          leftContent.className = 'flex items-start gap-2 flex-1 text-left py-0.5';

          const itemIcon = document.createElement('span');
          itemIcon.className = 'material-symbols-outlined text-[16px] text-slate-400 flex-shrink-0 mt-0.5';
          itemIcon.innerText = value ? defaultIcon : 'remove';

          const textSpan = document.createElement('span');
          textSpan.className = 'text-xs font-medium text-slate-800 leading-snug whitespace-normal break-words flex-1 text-left';
          textSpan.innerText = text;

          leftContent.appendChild(itemIcon);
          leftContent.appendChild(textSpan);
          optEl.appendChild(leftContent);

          if (isSelected) {
            const checkIcon = document.createElement('span');
            checkIcon.className = 'material-symbols-outlined text-[16px] text-emerald-600 flex-shrink-0 mt-0.5';
            checkIcon.innerText = 'check_circle';
            optEl.appendChild(checkIcon);
          }

          optEl.addEventListener('click', (e) => {
            e.stopPropagation();
            select.selectedIndex = i;
            select.dispatchEvent(new Event('change', { bubbles: true }));
            updateTriggerText();
            closeDropdown();
          });

          optionsList.appendChild(optEl);
        }
      }

      if (matchCount === 0) {
        optionsList.innerHTML = '<div class="p-4 text-center text-xs text-slate-400 flex flex-col items-center gap-1"><span class="material-symbols-outlined text-[20px] text-slate-300">search_off</span><span>Tidak ada hasil yang cocok</span></div>';
      }
    }

    function positionDropdown() {
      if (!wrapper.classList.contains('open') || dropdown.style.display === 'none') return;
      const rect = trigger.getBoundingClientRect();
      
      const viewportWidth = window.innerWidth;
      const desiredWidth = Math.max(rect.width, 380);
      let left = rect.left;
      if (left + desiredWidth > viewportWidth - 12) {
        left = Math.max(12, viewportWidth - desiredWidth - 12);
      }
      
      dropdown.style.position = 'fixed';
      dropdown.style.left = `${left}px`;
      dropdown.style.width = `${Math.min(desiredWidth, viewportWidth - 24)}px`;
      dropdown.style.zIndex = '9999999';

      const dropdownHeight = 350;
      const spaceBelow = window.innerHeight - rect.bottom;
      if (spaceBelow < dropdownHeight && rect.top > dropdownHeight) {
        dropdown.style.top = 'auto';
        dropdown.style.bottom = `${window.innerHeight - rect.top + 6}px`;
      } else {
        dropdown.style.top = `${rect.bottom + 6}px`;
        dropdown.style.bottom = 'auto';
      }
    }

    function openDropdown() {
      document.querySelectorAll('.ss-dropdown').forEach(d => {
        d.style.display = 'none';
      });
      document.querySelectorAll('.ss-wrapper').forEach(w => {
        if (w !== wrapper) w.classList.remove('open');
      });

      wrapper.classList.add('open');
      dropdown.style.display = 'block';
      positionDropdown();
      searchInput.value = '';
      renderOptions();
      setTimeout(() => {
        searchInput.focus();
        positionDropdown();
      }, 50);
    }

    function closeDropdown() {
      wrapper.classList.remove('open');
      dropdown.style.display = 'none';
    }

    const scrollResizeHandler = () => {
      if (wrapper.classList.contains('open')) {
        positionDropdown();
      }
    };
    window.addEventListener('scroll', scrollResizeHandler, true);
    window.addEventListener('resize', scrollResizeHandler, true);

    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      if (dropdown.style.display === 'block' || wrapper.classList.contains('open')) {
        closeDropdown();
      } else {
        openDropdown();
      }
    });

    searchInput.addEventListener('input', () => {
      renderOptions(searchInput.value);
      positionDropdown();
    });

    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeDropdown();
      }
    });

    document.addEventListener('click', (e) => {
      if (!wrapper.contains(e.target) && !dropdown.contains(e.target)) {
        closeDropdown();
      }
    });

    // Clean up dropdown when parent wrapper is removed from DOM
    const observer = new MutationObserver(() => {
      if (!document.body.contains(wrapper)) {
        dropdown.remove();
        observer.disconnect();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });

    select.addEventListener('change', () => {
      updateTriggerText();
      renderOptions(searchInput.value);
    });

    // Attach update function directly on select element for manual sync
    select._ssUpdate = () => {
      updateTriggerText();
      renderOptions(searchInput.value);
    };

    updateTriggerText();
  },

  escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  },

  syncSearchableSelect(select) {
    if (!select) return;
    if (select.dataset.ssInit !== 'true') {
      App.initSearchableSelect(select);
    } else if (select._ssUpdate) {
      select._ssUpdate();
    }
  },

  initAllSearchableSelects(root = document) {
    const selects = root.querySelectorAll('select.searchable-select, select[data-searchable="true"], select[id*="Material"], select[id*="material"], select[id*="Operator"], select[id*="operator"], select[class*="material-select"], select[class*="operator-select"]');
    selects.forEach(s => {
      if (!s.hasAttribute('data-no-search')) {
        App.initSearchableSelect(s);
      }
    });
  }
};

function escapeHtml(str) {
  return App.escapeHtml(str);
}
window.escapeHtml = escapeHtml;

document.addEventListener('DOMContentLoaded', () => {
  App.initAllSearchableSelects();
});
