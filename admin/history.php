<?php
// admin/history.php - Dedicated Stock Card & In/Out History Page (White & Emerald Green Theme)
require_once __DIR__ . '/../includes/auth.php';
Auth::requireAdmin();

$pdo = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
$code = trim($_GET['code'] ?? '');

// Ensure database is reconciled
Database::autoReconcileStockMutations($pdo);

if ($id > 0) {
    $stmtMat = $pdo->prepare("
        SELECT m.*,
               COALESCE((
                   SELECT SUM(qty_change) 
                   FROM stock_mutations 
                   WHERE material_id = m.id 
                     AND qty_change > 0 
                     AND type != 'INITIAL_IMPORT'
               ), 0) as total_inbound,
               COALESCE((
                   SELECT SUM(ABS(qty_change)) 
                   FROM stock_mutations 
                   WHERE material_id = m.id 
                     AND qty_change < 0
               ), 0) as total_outbound,
               COALESCE((
                   SELECT qty_change 
                   FROM stock_mutations 
                   WHERE material_id = m.id 
                     AND type = 'INITIAL_IMPORT' 
                   ORDER BY id DESC LIMIT 1
               ), (
                   m.current_stock - 
                   COALESCE((SELECT SUM(qty_change) FROM stock_mutations WHERE material_id = m.id AND type != 'INITIAL_IMPORT'), 0)
               )) as initial_upload_stock
        FROM materials m
        WHERE m.id = ?
    ");
    $stmtMat->execute([$id]);
} else {
    $stmtMat = $pdo->prepare("
        SELECT m.*,
               COALESCE((
                   SELECT SUM(qty_change) 
                   FROM stock_mutations 
                   WHERE material_id = m.id 
                     AND qty_change > 0 
                     AND type != 'INITIAL_IMPORT'
               ), 0) as total_inbound,
               COALESCE((
                   SELECT SUM(ABS(qty_change)) 
                   FROM stock_mutations 
                   WHERE material_id = m.id 
                     AND qty_change < 0
               ), 0) as total_outbound,
               COALESCE((
                   SELECT qty_change 
                   FROM stock_mutations 
                   WHERE material_id = m.id 
                     AND type = 'INITIAL_IMPORT' 
                   ORDER BY id DESC LIMIT 1
               ), (
                   m.current_stock - 
                   COALESCE((SELECT SUM(qty_change) FROM stock_mutations WHERE material_id = m.id AND type != 'INITIAL_IMPORT'), 0)
               )) as initial_upload_stock
        FROM materials m
        WHERE m.code = ?
    ");
    $stmtMat->execute([$code]);
}

$material = $stmtMat->fetch();

if (!$material) {
    header('Location: index.php#inventory');
    exit;
}

$material['initial_upload_stock'] = (float)$material['initial_upload_stock'];
$material['total_inbound'] = (float)$material['total_inbound'];
$material['total_outbound'] = (float)$material['total_outbound'];
$material['current_stock'] = (float)$material['current_stock'];
$material['min_stock'] = (float)$material['min_stock'];

// Fetch all mutations (INITIAL_IMPORT first, then chronological)
$stmtMut = $pdo->prepare("
    SELECT sm.*, u.name as user_name, u.role as user_role, u.shift as user_shift
    FROM stock_mutations sm
    LEFT JOIN users u ON sm.user_id = u.id
    WHERE sm.material_id = ?
    ORDER BY (CASE WHEN sm.type = 'INITIAL_IMPORT' THEN 0 ELSE 1 END), sm.created_at ASC, sm.id ASC
");
$stmtMut->execute([$material['id']]);
$mutations = $stmtMut->fetchAll();

$pageTitle = "History Movement Stock: {$material['code']} - {$material['name']}";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="min-h-screen bg-slate-50 flex flex-col">
  <!-- Top Navigation Bar (Hidden on Print) -->
  <header class="bg-white border-b border-slate-200 sticky top-0 z-20 shadow-2xs no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <a href="./#inventory" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 transition-colors flex items-center gap-1.5 text-xs font-bold" title="Kembali ke Master Stok">
          <span class="material-symbols-outlined text-[18px]">arrow_back</span>
          <span class="hidden sm:inline">Kembali ke Master Stok</span>
        </a>
        <div class="h-5 w-px bg-slate-200"></div>
        <div>
          <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Modul Master Stok</span>
          <h1 class="font-extrabold text-sm sm:text-base text-slate-900 flex items-center gap-1.5">
            <span class="text-emerald-800">Kartu Stok Terintegrasi</span>
          </h1>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
        <!-- Download Excel -->
        <a href="export.php?type=material_history&id=<?= $material['id'] ?>" class="h-[38px] px-3.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-2xs transition-colors inline-flex items-center gap-1.5" title="Export Riwayat ke Excel (.xlsx)">
          <span class="material-symbols-outlined text-[18px]">table_chart</span>
          <span>Export History Excel</span>
        </a>

        <!-- Print PDF Button -->
        <button onclick="window.print()" class="h-[38px] px-3.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-slate-700 text-xs font-bold shadow-2xs transition-colors inline-flex items-center gap-1.5" title="Cetak Kartu Stok">
          <span class="material-symbols-outlined text-[18px]">print</span>
          <span>Cetak Kartu Stok</span>
        </button>
      </div>
    </div>
  </header>

  <!-- Main Content Container -->
  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-1 space-y-5 w-full">
    
    <!-- 1. Header Information Card -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-start gap-3.5">
        <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold flex-shrink-0 border border-emerald-200">
          <span class="material-symbols-outlined text-[28px]">inventory_2</span>
        </div>
        <div>
          <div class="flex flex-wrap items-center gap-2 mb-1">
            <span class="font-mono font-black text-sm px-2.5 py-0.5 rounded bg-emerald-50 text-emerald-900 border border-emerald-300">
              <?= htmlspecialchars($material['code']) ?>
            </span>
            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700">
              <?= htmlspecialchars($material['category']) ?>
            </span>
            <?php if ($material['current_stock'] <= 0): ?>
              <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-rose-50 text-rose-700 border border-rose-200">STOK HABIS (0)</span>
            <?php elseif ($material['current_stock'] <= $material['min_stock']): ?>
              <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-50 text-amber-800 border border-amber-200">STOK MENIPIS (&le; <?= number_format($material['min_stock']) ?>)</span>
            <?php else: ?>
              <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">STOK AMAN</span>
            <?php endif; ?>
          </div>

          <h2 class="text-base sm:text-lg font-bold text-slate-900"><?= htmlspecialchars($material['name']) ?></h2>
        </div>
      </div>

      <!-- Quick Specs -->
      <div class="flex flex-wrap items-center gap-4 text-xs border-t md:border-t-0 md:border-l border-slate-100 pt-3 md:pt-0 md:pl-6 text-slate-600">
        <div>
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Lokasi Rak Simpan</span>
          <span class="font-bold text-slate-800 inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-[14px] text-emerald-600">location_on</span>
            <?= htmlspecialchars($material['rack_location']) ?>
          </span>
        </div>
        <div>
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Satuan (UOM)</span>
          <span class="font-bold text-slate-800"><?= htmlspecialchars($material['unit']) ?></span>
        </div>
        <div>
          <span class="text-[10px] uppercase font-bold text-slate-400 block">Min Safety Stock</span>
          <span class="font-bold text-slate-800"><?= number_format($material['min_stock']) ?> <?= htmlspecialchars($material['unit']) ?></span>
        </div>
      </div>
    </div>

    <!-- 2. Stock Formula KPI Breakdown Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <!-- Stok Awal Upload -->
      <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm relative overflow-hidden">
        <div class="flex items-center justify-between">
          <p class="text-[11px] uppercase font-bold text-slate-400">1. Stok Awal (Upload)</p>
          <span class="material-symbols-outlined text-slate-300 text-[20px]">upload_file</span>
        </div>
        <h3 class="text-xl sm:text-2xl font-black text-slate-800 mt-2"><?= number_format($material['initial_upload_stock']) ?></h3>
        <p class="text-[11px] text-slate-500 mt-0.5">Stok awal dari file Excel / Setup</p>
      </div>

      <!-- Total Barang Masuk (+) -->
      <div class="bg-white p-4 rounded-xl border border-emerald-200 shadow-sm relative overflow-hidden bg-emerald-50/20">
        <div class="flex items-center justify-between">
          <p class="text-[11px] uppercase font-bold text-emerald-700">2. Total Barang Masuk (+)</p>
          <span class="material-symbols-outlined text-emerald-600 text-[20px]">move_to_inbox</span>
        </div>
        <h3 class="text-xl sm:text-2xl font-black text-emerald-700 mt-2">+<?= number_format($material['total_inbound']) ?></h3>
        <p class="text-[11px] text-emerald-600 mt-0.5">Akumulasi penerimaan PO / Inbound</p>
      </div>

      <!-- Total Barang Keluar (-) -->
      <div class="bg-white p-4 rounded-xl border border-amber-200 shadow-sm relative overflow-hidden bg-amber-50/20">
        <div class="flex items-center justify-between">
          <p class="text-[11px] uppercase font-bold text-amber-700">3. Total Barang Keluar (-)</p>
          <span class="material-symbols-outlined text-amber-600 text-[20px]">outbox</span>
        </div>
        <h3 class="text-xl sm:text-2xl font-black text-amber-700 mt-2">-<?= number_format($material['total_outbound']) ?></h3>
        <p class="text-[11px] text-amber-600 mt-0.5">Picking Line & Pengeluaran Manual</p>
      </div>

      <!-- Sisa Stok Akhir (Aktual) -->
      <div class="p-4 rounded-xl shadow-sm text-white relative overflow-hidden <?= $material['current_stock'] <= $material['min_stock'] ? 'bg-rose-600' : 'bg-emerald-600' ?>">
        <div class="flex items-center justify-between">
          <p class="text-[11px] uppercase font-bold text-emerald-100">4. Sisa Stok Akhir</p>
          <span class="material-symbols-outlined text-emerald-200 text-[20px]">check_circle</span>
        </div>
        <h3 class="text-xl sm:text-2xl font-black text-white mt-2"><?= number_format($material['current_stock']) ?> <span class="text-xs font-normal text-emerald-100"><?= htmlspecialchars($material['unit']) ?></span></h3>
        <p class="text-[11px] text-emerald-100 mt-0.5">Sisa stok aktual fisik di gudang</p>
      </div>
    </div>

    <!-- 3. Transaction History Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden space-y-3 p-5">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
        <div>
          <h3 class="font-bold text-slate-900 text-sm">History Movement Stock</h3>
        </div>

        <div class="flex items-center gap-2 text-xs">
          <span class="px-2.5 py-1 rounded-full font-bold bg-slate-100 text-slate-700">
            Total <?= count($mutations) ?> Catatan Transaksi
          </span>
        </div>
      </div>

      <!-- Table -->
      <div class="overflow-x-auto border border-slate-200 rounded-xl">
        <table class="w-full text-left border-collapse">
          <thead class="thead-emerald text-[10px] font-extrabold uppercase tracking-wider text-white border-b border-emerald-700">
            <tr>
              <th class="p-3">Waktu Transaksi</th>
              <th class="p-3">Tipe Mutasi</th>
              <th class="p-3">No. Referensi (PO / Task)</th>
              <th class="p-3 text-center text-emerald-800">Masuk (+)</th>
              <th class="p-3 text-center text-rose-600">Keluar (-)</th>
              <th class="p-3 text-center font-bold text-slate-900">Sisa Stok</th>
              <th class="p-3">Keterangan & Catatan</th>
              <th class="p-3">Petugas PIC</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 text-xs">
            <?php if (empty($mutations)): ?>
              <tr>
                <td colspan="8" class="p-8 text-center text-slate-400 font-medium">
                  <span class="material-symbols-outlined text-[32px] text-slate-300 mb-1">history</span>
                  <p>Belum ada catatan mutasi untuk packaging material ini.</p>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($mutations as $mut): ?>
                <?php 
                  $isPositive = (int)$mut['qty_change'] > 0;
                  $typeBadge = '';
                  if ($mut['type'] === 'INBOUND') {
                    $typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">BARANG MASUK</span>';
                  } elseif ($mut['type'] === 'OUTBOUND') {
                    $typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">BARANG KELUAR</span>';
                  } elseif ($mut['type'] === 'TASK_PICKING') {
                    $typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-50 text-indigo-800 border border-indigo-200">TASK PICKING</span>';
                  } elseif ($mut['type'] === 'ADJUSTMENT') {
                    $typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-50 text-purple-800 border border-purple-200">PENYESUAIAN STOK</span>';
                  } else {
                    $typeBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700">STOK AWAL</span>';
                  }
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                  <td class="p-3 text-slate-400 font-mono text-[11px] whitespace-nowrap">
                    <?= date('d M Y, H.i', strtotime($mut['created_at'])) ?>
                  </td>
                  <td class="p-3"><?= $typeBadge ?></td>
                  <td class="p-3 font-mono font-bold text-emerald-800"><?= htmlspecialchars($mut['reference_no']) ?></td>
                  <td class="p-3 text-center font-bold text-emerald-700 font-mono">
                    <?= $isPositive ? '+' . number_format($mut['qty_change']) : '0' ?>
                  </td>
                  <td class="p-3 text-center font-bold text-rose-600 font-mono">
                    <?= !$isPositive ? number_format(abs($mut['qty_change'])) : '0' ?>
                  </td>
                  <td class="p-3 text-center font-black text-slate-900 font-mono">
                    <?= number_format($mut['stock_after']) ?> <span class="text-[10px] text-slate-400 font-normal"><?= htmlspecialchars($material['unit']) ?></span>
                  </td>
                  <td class="p-3 text-slate-600 text-[11px]">
                    <?= htmlspecialchars($mut['notes'] ?: '-') ?>
                  </td>
                  <td class="p-3">
                    <div class="flex items-center gap-1">
                      <span class="material-symbols-outlined text-[15px] text-slate-400">person</span>
                      <span class="font-semibold text-slate-800"><?= htmlspecialchars($mut['user_name'] ?: 'System') ?></span>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
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

  </main>
</div>

<!-- Comprehensive Professional Print Stylesheet -->
<style>
@media print {
  @page {
    size: A4 portrait;
    margin: 12mm 12mm 15mm 12mm;
  }
  body {
    background: #ffffff !important;
    color: #0f172a !important;
    font-size: 10.5pt !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  .no-print, header, button, a[href*="export"], a[href*="index"] {
    display: none !important;
  }
  main {
    max-width: 100% !important;
    padding: 0 !important;
    margin: 0 !important;
  }
  .print\:block {
    display: block !important;
  }
  .shadow-sm, .shadow-2xs, .shadow-lg, .shadow-xl {
    box-shadow: none !important;
  }
  .border {
    border-color: #94a3b8 !important;
  }
  table {
    width: 100% !important;
    border-collapse: collapse !important;
    page-break-inside: auto;
  }
  tr {
    page-break-inside: avoid;
    page-break-after: auto;
  }
  thead {
    display: table-header-group;
    background-color: #059669 !important;
    color: #ffffff !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  thead th {
    background-color: #059669 !important;
    color: #ffffff !important;
    border: 1px solid #047857 !important;
    padding: 6px 8px !important;
  }
  tbody td {
    border: 1px solid #cbd5e1 !important;
    padding: 5px 8px !important;
  }
}
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
