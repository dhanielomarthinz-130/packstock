<!-- ================= MODAL: DETAIL TRANSAKSI MUTASI (NO. REFERENSI) ================= -->
<div id="modalMutationReferenceDetail" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 hidden">
  <div class="bg-white rounded-2xl max-w-2xl w-full p-5 sm:p-6 shadow-2xl space-y-4 animate-scale-up border border-slate-200 max-h-[92vh] overflow-y-auto">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <div class="flex items-center gap-3">
        <div id="mutDetailIconBox" class="w-10 h-10 rounded-xl bg-blue-50 border border-blue-200 text-blue-700 flex items-center justify-center shadow-xs shrink-0">
          <span class="material-symbols-outlined text-[22px]" id="mutDetailIcon">receipt_long</span>
        </div>
        <div>
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="font-extrabold text-slate-900 text-base font-mono" id="mutDetailRefNo">REF-NO</h3>
            <span id="mutDetailTypeBadge" class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider">TYPE</span>
          </div>
          <p class="text-xs text-slate-500 mt-0.5" id="mutDetailTimestamp">Waktu Transaksi: -</p>
        </div>
      </div>
      <button type="button" onclick="App.closeModal('modalMutationReferenceDetail')" class="text-slate-400 hover:text-slate-600 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer" title="Tutup (ESC)">
        <span class="material-symbols-outlined text-[20px]">close</span>
      </button>
    </div>

    <!-- Body Content -->
    <div id="mutDetailContent" class="space-y-3.5 text-xs">
      <!-- Injected by JavaScript -->
    </div>

    <!-- Footer -->
    <div class="flex items-center justify-end pt-3 border-t border-slate-100">
      <button type="button" onclick="App.closeModal('modalMutationReferenceDetail')" class="px-5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition-colors cursor-pointer">
        Tutup
      </button>
    </div>
  </div>
</div>

<script>
async function openMutationReferenceDetail(refNo, mutationId = null) {
  if (!refNo && !mutationId) return;

  const modal = document.getElementById('modalMutationReferenceDetail');
  if (!modal) return;

  // Open modal in loading state
  App.openModal('modalMutationReferenceDetail');
  const contentEl = document.getElementById('mutDetailContent');
  const refEl = document.getElementById('mutDetailRefNo');
  const typeBadgeEl = document.getElementById('mutDetailTypeBadge');
  const timeEl = document.getElementById('mutDetailTimestamp');
  const iconEl = document.getElementById('mutDetailIcon');
  const iconBox = document.getElementById('mutDetailIconBox');

  if (refEl) refEl.innerText = refNo || 'Memuat...';
  if (typeBadgeEl) {
    typeBadgeEl.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700';
    typeBadgeEl.innerText = 'MEMUAT';
  }
  if (timeEl) timeEl.innerText = 'Memuat rincian transaksi...';
  if (contentEl) {
    contentEl.innerHTML = `
      <div class="p-10 text-center text-slate-400">
        <span class="material-symbols-outlined text-[32px] animate-spin text-blue-600">progress_activity</span>
        <p class="mt-2 text-xs font-semibold text-slate-600">Mengambil data transaksi lengkap...</p>
      </div>
    `;
  }

  try {
    const query = new URLSearchParams({
      action: 'reference_detail',
      ref: refNo || '',
      id: mutationId || ''
    });

    const res = await App.fetchJson(`../api/mutations.php?${query.toString()}`);
    if (!res.success || !res.data) {
      if (contentEl) {
        contentEl.innerHTML = `
          <div class="p-6 text-center text-rose-500 bg-rose-50 rounded-xl border border-rose-200">
            <span class="material-symbols-outlined text-[28px] mb-1">error</span>
            <p class="font-bold">${App.escapeHtml(res.message || 'Detail transaksi tidak ditemukan.')}</p>
          </div>
        `;
      }
      return;
    }

    const d = res.data;
    const pm = d.primary_mutation || {};
    const inb = d.inbound;
    const tsk = d.task;
    const out = d.outbound;
    const type = d.type || pm.type || 'MUTATION';

    // Update Header
    if (refEl) refEl.innerText = d.reference_no || pm.reference_no || '-';
    if (timeEl) timeEl.innerText = 'Waktu Transaksi: ' + App.formatDate(d.created_at || pm.created_at);

    // Style Badge & Icon
    let typeName = 'MUTASI STOK';
    let badgeClass = 'bg-slate-100 text-slate-800 border border-slate-200';
    let iconName = 'history';
    let boxClass = 'bg-slate-50 border-slate-200 text-slate-700';

    if (type === 'INBOUND' || inb) {
      typeName = 'BARANG MASUK (INBOUND)';
      badgeClass = 'bg-emerald-100 text-emerald-800 border border-emerald-300';
      iconName = 'move_to_inbox';
      boxClass = 'bg-emerald-50 border-emerald-200 text-emerald-700';
    } else if (type === 'OUTBOUND' || out) {
      typeName = 'BARANG KELUAR (OUTBOUND)';
      badgeClass = 'bg-amber-100 text-amber-800 border border-amber-300';
      iconName = 'outbox';
      boxClass = 'bg-amber-50 border-amber-200 text-amber-700';
    } else if (type === 'TASK_PICKING' || tsk) {
      typeName = 'TASK PICKING OPERATOR';
      badgeClass = 'bg-indigo-100 text-indigo-800 border border-indigo-300';
      iconName = 'assignment';
      boxClass = 'bg-indigo-50 border-indigo-200 text-indigo-700';
    } else if (type === 'ADJUSTMENT') {
      typeName = 'PENYESUAIAN STOK (ADJUST)';
      badgeClass = 'bg-purple-100 text-purple-800 border border-purple-300';
      iconName = 'tune';
      boxClass = 'bg-purple-50 border-purple-200 text-purple-700';
    } else if (type === 'INITIAL_IMPORT') {
      typeName = 'STOK AWAL (INITIAL)';
      badgeClass = 'bg-blue-100 text-blue-800 border border-blue-300';
      iconName = 'inventory';
      boxClass = 'bg-blue-50 border-blue-200 text-blue-700';
    } else if (type.includes('TRANSFER')) {
      typeName = 'STOCK TRANSFER';
      badgeClass = 'bg-purple-100 text-purple-800 border border-purple-300';
      iconName = 'sync_alt';
      boxClass = 'bg-purple-50 border-purple-200 text-purple-700';
    }

    if (typeBadgeEl) {
      typeBadgeEl.className = `px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider ${badgeClass}`;
      typeBadgeEl.innerText = typeName;
    }
    if (iconEl) iconEl.innerText = iconName;
    if (iconBox) iconBox.className = `w-10 h-10 rounded-xl border flex items-center justify-center shadow-xs shrink-0 ${boxClass}`;

    // Material Details
    const matName = pm.material_name || inb?.material_name || tsk?.material_name || out?.material_name || '-';
    const matCode = pm.material_code || inb?.material_code || tsk?.material_code || out?.material_code || '-';
    const matRack = pm.rack_location || inb?.rack_location || tsk?.rack_location || out?.rack_location || '-';
    const matUnit = pm.material_unit || inb?.material_unit || tsk?.material_unit || out?.material_unit || 'Pcs';
    const catLabel = (pm.material_item_type === 'GIMMICK') ? 'GIMMICK' : 'KEMAS';

    // Stock changes
    const qtyChange = Number(pm.qty_change || inb?.qty || tsk?.actual_qty || out?.qty || 0);
    const isPositive = qtyChange > 0;
    const picName = pm.user_name || pm.user_username || inb?.receiver_name || tsk?.operator_name || out?.issuer_name || 'System';

    // Inbound photos
    let photos = [];
    const photoRaw = inb?.photo_path || out?.photo_path;
    if (photoRaw) {
      if (Array.isArray(photoRaw)) photos = photoRaw;
      else if (typeof photoRaw === 'string' && photoRaw.startsWith('[')) {
        try { photos = JSON.parse(photoRaw); } catch (e) { photos = [photoRaw]; }
      } else {
        photos = [photoRaw];
      }
    }

    let specificHtml = '';
    if (inb) {
      specificHtml = `
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
          <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Rincian Dokumen Inbound (Penerimaan)</div>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <div>
              <span class="text-slate-400 block text-[10px]">No. PO / Surat Jalan:</span>
              <span class="font-bold text-slate-800 font-mono">${App.escapeHtml(inb.po_number || '-')}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Supplier / Vendor:</span>
              <span class="font-bold text-slate-800">${App.escapeHtml(inb.supplier || '-')}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Petugas Penerima:</span>
              <span class="font-bold text-slate-800">${App.escapeHtml(inb.receiver_name || 'Admin')}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">No. Batch:</span>
              <span class="font-bold text-slate-800 font-mono">${App.escapeHtml(inb.batch_no || '-')}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Expired Date:</span>
              <span class="font-bold text-slate-800">${inb.exp_date ? App.escapeHtml(inb.exp_date) : '-'}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Lokasi Penerimaan:</span>
              <span class="font-bold text-slate-800 font-mono">${App.escapeHtml(inb.location || inb.rack_location || '-')}</span>
            </div>
          </div>
        </div>
      `;
    } else if (tsk) {
      specificHtml = `
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
          <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Rincian Penugasan Operator (Task Dispatch)</div>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <div>
              <span class="text-slate-400 block text-[10px]">Operator Lapangan:</span>
              <span class="font-bold text-slate-800">${App.escapeHtml(tsk.operator_name || tsk.operator_username || 'Operator')}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Tujuan Line / Area:</span>
              <span class="font-bold text-slate-800">${App.escapeHtml(tsk.destination || '-')}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Status Tugas:</span>
              <span class="px-2 py-0.5 rounded text-[10px] font-bold ${tsk.status === 'COMPLETED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'}">${App.escapeHtml(tsk.status)}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Target Qty:</span>
              <span class="font-mono font-bold text-slate-800">${App.formatNumber(tsk.target_qty)}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Realisasi Qty:</span>
              <span class="font-mono font-bold text-emerald-700">${App.formatNumber(tsk.actual_qty)}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Durasi Eksekusi:</span>
              <span class="font-bold text-slate-800">${tsk.duration_seconds > 0 ? (Math.round(tsk.duration_seconds / 6) / 10) + ' Menit' : '-'}</span>
            </div>
          </div>
          ${tsk.completion_notes ? `<div class="mt-2 text-slate-600 bg-white p-2 rounded-lg border border-slate-200 text-[11px]"><b>Catatan Penyelesaian:</b> ${App.escapeHtml(tsk.completion_notes)}</div>` : ''}
        </div>
      `;
    } else if (out) {
      specificHtml = `
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
          <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Rincian Pengeluaran Barang (Outbound)</div>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            <div>
              <span class="text-slate-400 block text-[10px]">Tujuan Pengeluaran:</span>
              <span class="font-bold text-slate-800">${App.escapeHtml(out.destination || '-')}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Alasan / Keperluan:</span>
              <span class="font-bold text-slate-800">${App.escapeHtml(out.reason || '-')}</span>
            </div>
            <div>
              <span class="text-slate-400 block text-[10px]">Dikeluarkan Oleh:</span>
              <span class="font-bold text-slate-800">${App.escapeHtml(out.issuer_name || 'Admin')}</span>
            </div>
          </div>
        </div>
      `;
    }

    // Photo Gallery
    let photoGalleryHtml = '';
    if (photos.length > 0) {
      photoGalleryHtml = `
        <div class="bg-slate-50 p-3.5 rounded-xl border border-slate-200 space-y-2">
          <div class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[15px] text-blue-600">photo_library</span>
            <span>Foto Lampiran & Dokumentasi (${photos.length} Foto)</span>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            ${photos.map((p, idx) => `
              <a href="../${App.escapeHtml(p)}" target="_blank" class="rounded-xl overflow-hidden border border-slate-200 h-24 bg-slate-900 flex items-center justify-center cursor-pointer hover:opacity-90 relative group shadow-2xs">
                <img src="../${App.escapeHtml(p)}" alt="Lampiran" class="h-24 w-full object-cover">
                <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                  <span class="material-symbols-outlined text-white text-[20px]">zoom_in</span>
                </div>
              </a>
            `).join('')}
          </div>
        </div>
      `;
    }

    // Multi-items table if more than 1 mutation in batch
    let itemsTableHtml = '';
    if (d.all_mutations && d.all_mutations.length > 1) {
      itemsTableHtml = `
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden space-y-2">
          <div class="p-3 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
            <span class="text-[11px] font-extrabold text-slate-700 uppercase tracking-wider">Item Lain Dalam Transaksi Ini (${d.all_mutations.length} Item)</span>
          </div>
          <div class="overflow-x-auto max-h-48 overflow-y-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead class="bg-slate-100 text-[10px] font-extrabold uppercase tracking-wider text-slate-600">
                <tr>
                  <th class="p-2">Material / SKU</th>
                  <th class="p-2">Lokasi Rak</th>
                  <th class="p-2 text-center">Perubahan (+/-)</th>
                  <th class="p-2 text-center">Sisa Stok</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                ${d.all_mutations.map(m => `
                  <tr class="hover:bg-slate-50">
                    <td class="p-2">
                      <div class="font-bold text-slate-800">${App.escapeHtml(m.material_name)}</div>
                      <div class="text-[10px] text-slate-400 font-mono">${App.escapeHtml(m.material_code)}</div>
                    </td>
                    <td class="p-2 font-mono text-slate-600">${App.escapeHtml(m.rack_location || '-')}</td>
                    <td class="p-2 text-center font-bold ${Number(m.qty_change) > 0 ? 'text-emerald-700' : 'text-rose-700'}">
                      ${Number(m.qty_change) > 0 ? '+' : ''}${App.formatNumber(m.qty_change)} ${App.escapeHtml(m.material_unit)}
                    </td>
                    <td class="p-2 text-center font-black text-slate-800">${App.formatNumber(m.stock_after)}</td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          </div>
        </div>
      `;
    }

    contentEl.innerHTML = `
      <!-- Card 1: Informasi Material -->
      <div class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Informasi Material / Barang</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-black ${catLabel === 'GIMMICK' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}">${catLabel}</span>
        </div>
        <div class="flex items-start justify-between gap-2">
          <div>
            <h4 class="font-extrabold text-slate-900 text-sm">${App.escapeHtml(matName)}</h4>
            <p class="font-mono text-[11px] text-slate-500">${App.escapeHtml(matCode)} &bull; Rak: <b class="text-slate-700">${App.escapeHtml(matRack)}</b></p>
          </div>
        </div>
      </div>

      <!-- Card 2: Saldo & Pergerakan Stok -->
      <div class="grid grid-cols-3 gap-2.5 text-center">
        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
          <span class="text-[10px] text-slate-500 block font-bold">Stok Sebelum</span>
          <span class="text-base font-black text-slate-700 font-mono">${pm.stock_before !== undefined ? App.formatNumber(pm.stock_before) : '-'}</span>
        </div>
        <div class="p-2.5 ${isPositive ? 'bg-emerald-50 border-emerald-200' : 'bg-rose-50 border-rose-200'} rounded-xl border">
          <span class="text-[10px] ${isPositive ? 'text-emerald-700' : 'text-rose-700'} block font-bold">Perubahan (+/-)</span>
          <span class="text-base font-black font-mono ${isPositive ? 'text-emerald-700' : 'text-rose-700'}">
            ${isPositive ? '+' : ''}${App.formatNumber(qtyChange)} ${App.escapeHtml(matUnit)}
          </span>
        </div>
        <div class="p-2.5 bg-blue-50 rounded-xl border border-blue-200">
          <span class="text-[10px] text-blue-700 block font-bold">Sisa Stok Akhir</span>
          <span class="text-base font-black text-blue-950 font-mono">${pm.stock_after !== undefined ? App.formatNumber(pm.stock_after) : '-'}</span>
        </div>
      </div>

      <!-- Card 3: Spesifik Transaksi -->
      ${specificHtml}

      <!-- Card 4: Catatan & Petugas PIC -->
      <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
        <div class="flex items-center justify-between text-[11px]">
          <span class="text-slate-500 font-bold">Petugas PIC:</span>
          <span class="font-bold text-slate-800 flex items-center gap-1">
            <span class="material-symbols-outlined text-[15px] text-slate-400">person</span>
            <span>${App.escapeHtml(picName)}</span>
          </span>
        </div>
        <div class="text-[11px] pt-1 border-t border-slate-200/60">
          <span class="text-slate-500 font-bold block mb-0.5">Catatan / Keterangan:</span>
          <p class="text-slate-700 bg-white p-2 rounded-lg border border-slate-200 text-xs italic">
            ${App.escapeHtml(pm.notes || inb?.notes || tsk?.notes || out?.notes || 'Tidak ada catatan khusus.')}
          </p>
        </div>
      </div>

      <!-- Card 5: Foto Dokumentasi jika ada -->
      ${photoGalleryHtml}

      <!-- Card 6: Multi-Items Table jika ada -->
      ${itemsTableHtml}
    `;
  } catch (err) {
    if (contentEl) {
      contentEl.innerHTML = `
        <div class="p-6 text-center text-rose-500 bg-rose-50 rounded-xl border border-rose-200">
          <span class="material-symbols-outlined text-[28px] mb-1">wifi_off</span>
          <p class="font-bold">Gagal memuat detail transaksi karena kendala koneksi.</p>
        </div>
      `;
    }
  }
}
</script>
