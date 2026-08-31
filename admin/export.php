<?php
// admin/export.php - Export Master Stock & Transaction History to Genuine Excel (.xlsx)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xlsx_writer.php';
Auth::requireAdmin();

$pdo = Database::getConnection();
$type = $_GET['type'] ?? 'all_materials';

function formatExportDate($dateStr) {
    if (empty($dateStr) || $dateStr === '-') return '-';
    $time = strtotime($dateStr);
    if (!$time) return $dateStr;
    return date('d/m/Y H:i:s', $time);
}

function formatExportDateOnly($dateStr) {
    if (empty($dateStr) || $dateStr === '-') return '-';
    $time = strtotime($dateStr);
    if (!$time) return $dateStr;
    return date('d/m/Y', $time);
}

// =========================================================================
// 1. EXPORT KARTU RIWAYAT STOK MATERIAL INDIVIDUAL (.xlsx)
// =========================================================================
if ($type === 'material_history') {
    $id = (int)($_GET['id'] ?? 0);
    $code = trim($_GET['code'] ?? '');

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
                       ORDER BY id ASC LIMIT 1
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
                       ORDER BY id ASC LIMIT 1
                   ), (
                       m.current_stock - 
                       COALESCE((SELECT SUM(qty_change) FROM stock_mutations WHERE material_id = m.id AND type != 'INITIAL_IMPORT'), 0)
                   )) as initial_upload_stock
            FROM materials m
            WHERE m.code = ?
        ");
        $stmtMat->execute([$code]);
    }

    $mat = $stmtMat->fetch();
    if (!$mat) {
        http_response_code(404);
        die("Material packaging tidak ditemukan.");
    }

    $cleanCode = preg_replace('/[^A-Za-z0-9_-]/', '_', $mat['code']);
    $filename = "History_Movement_Stock_{$cleanCode}_" . date('Ymd_His') . ".xlsx";
    $title = "HISTORY MOVEMENT STOCK: {$mat['code']} - {$mat['name']} (Sisa: {$mat['current_stock']} {$mat['unit']})";

    $headers = [
        'No',
        'Waktu Transaksi',
        'Tipe Mutasi',
        'No. Referensi',
        'Masuk (+)',
        'Keluar (-)',
        'Sisa Stok',
        'Keterangan / Catatan',
        'Petugas PIC'
    ];

    $colWidths = [6, 20, 16, 18, 12, 12, 14, 30, 18];
    $rows = [];

    $stmtMut = $pdo->prepare("
        SELECT sm.*, u.username as user_username, u.name as user_name
        FROM stock_mutations sm
        LEFT JOIN users u ON sm.user_id = u.id
        WHERE sm.material_id = ?
        ORDER BY sm.created_at ASC, sm.id ASC
    ");
    $stmtMut->execute([$mat['id']]);
    $no = 1;
    while ($m = $stmtMut->fetch()) {
        $isPositive = $m['qty_change'] > 0;
        $inQty  = $isPositive ? (int)$m['qty_change'] : 0;
        $outQty = !$isPositive ? (int)abs($m['qty_change']) : 0;

        $typeLabel = $m['type'];
        if ($m['type'] === 'INBOUND') $typeLabel = 'BARANG MASUK';
        elseif ($m['type'] === 'OUTBOUND') $typeLabel = 'BARANG KELUAR';
        elseif ($m['type'] === 'TASK_PICKING') $typeLabel = 'TASK PICKING';
        elseif ($m['type'] === 'ADJUSTMENT') $typeLabel = 'PENYESUAIAN STOK';
        elseif ($m['type'] === 'INITIAL_IMPORT') $typeLabel = 'STOK AWAL';

        $pic = $m['user_username'] ?: ($m['user_name'] ?: 'System');

        $rows[] = [
            $no++,
            formatExportDate($m['created_at']),
            $typeLabel,
            $m['reference_no'],
            $inQty,
            $outQty,
            (float)$m['stock_after'],
            $m['notes'] ?: '-',
            $pic
        ];
    }

    XlsxWriter::download($filename, $title, $headers, $rows, $colWidths);
}

// =========================================================================
// 2. EXPORT MASTER STOK PACKAGING MATERIAL (.xlsx)
// =========================================================================
if ($type === 'csv' || $type === 'all_materials' || $type === 'materials') {
    $filename = "Laporan_Master_Stok_Packaging_" . date('Ymd_His') . ".xlsx";
    $title = "LAPORAN MASTER STOK PACKAGING MATERIAL";

    $headers = [
        'No',
        'Item No',
        'Item Description',
        'Kategori',
        'Stok Awal (Upload)',
        'Total Masuk (+)',
        'Total Keluar (-)',
        'Sisa Stok Akhir',
        'Satuan',
        'Lokasi Rak',
        'Min Safety Stock',
        'Status Stok'
    ];

    $colWidths = [6, 15, 32, 16, 15, 14, 14, 15, 10, 14, 14, 14];
    $rows = [];

    $stmt = $pdo->query("
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
                   ORDER BY id ASC LIMIT 1
               ), (
                   m.current_stock - 
                   COALESCE((SELECT SUM(qty_change) FROM stock_mutations WHERE material_id = m.id AND type != 'INITIAL_IMPORT'), 0)
               )) as initial_upload_stock
        FROM materials m
        ORDER BY m.code ASC
    ");

    $no = 1;
    while ($row = $stmt->fetch()) {
        $stock = (float)$row['current_stock'];
        $min = (float)$row['min_stock'];
        $initStock = (float)$row['initial_upload_stock'];
        $inbound = (float)$row['total_inbound'];
        $outbound = (float)$row['total_outbound'];
        
        $status = 'AMAN';
        if ($stock <= 0) {
            $status = 'HABIS';
        } elseif ($stock <= $min) {
            $status = 'MENIPIS';
        }

        $rows[] = [
            $no++,
            $row['code'],
            $row['name'],
            $row['category'] ?: 'Packaging Material',
            $initStock,
            $inbound,
            $outbound,
            $stock,
            $row['unit'] ?: 'Pcs',
            $row['rack_location'] ?: '-',
            $min,
            $status
        ];
    }

    XlsxWriter::download($filename, $title, $headers, $rows, $colWidths);
}

// =========================================================================
// 3. EXPORT ALL OUTBOUND / PENGELUARAN BARANG (MANUAL + TASK PICKING) (.xlsx)
// =========================================================================
if ($type === 'outbound' || $type === 'outbound_csv' || $type === 'outbound_excel') {
    $filename = "Laporan_Pengeluaran_Barang_Outbound_" . date('Ymd_His') . ".xlsx";
    $title = "LAPORAN PENGELUARAN BARANG (OUTBOUND & TASK PICKING)";

    $headers = [
        'No',
        'Tanggal',
        'Waktu Mulai',
        'Waktu Submit',
        'No. Transaksi',
        'Tipe Outbound',
        'Status',
        'Item No',
        'Nama Packaging Material',
        'Satuan',
        'Lokasi Rak',
        'Qty Out',
        'Durasi Kerja',
        'Takt Time',
        'Tujuan Antar',
        'Petugas PIC (Operator)',
        'Admin Penugas',
        'Alasan / Keperluan',
        'Catatan'
    ];

    $colWidths = [6, 12, 20, 20, 18, 16, 14, 15, 30, 10, 14, 12, 14, 14, 18, 18, 18, 20, 24];
    $rows = [];

    $query = "
        SELECT 
            'TASK_PICKING' as outbound_type,
            t.task_no as outbound_no,
            m.code as material_code,
            m.name as material_name,
            m.unit as material_unit,
            m.rack_location,
            IF(t.status = 'COMPLETED', t.actual_qty, t.target_qty) as qty,
            t.destination,
            u_to.name as operator_name,
            u_to.username as operator_username,
            u_by.username as admin_username,
            u_by.name as admin_name,
            'Pengambilan Line (Operator Task)' as reason,
            COALESCE(t.completion_notes, t.notes) as notes,
            t.status,
            COALESCE(t.started_at, t.created_at) as started_at,
            COALESCE(t.completed_at, t.created_at) as completed_at,
            COALESCE(t.duration_seconds, TIMESTAMPDIFF(SECOND, COALESCE(t.started_at, t.created_at), COALESCE(t.completed_at, CURRENT_TIMESTAMP))) as duration_seconds,
            t.created_at
        FROM tasks t
        JOIN materials m ON t.material_id = m.id
        JOIN users u_to ON t.assigned_to = u_to.id
        LEFT JOIN users u_by ON t.assigned_by = u_by.id

        UNION ALL

        SELECT 
            'MANUAL_OUTBOUND' as outbound_type,
            o.outbound_no,
            m.code as material_code,
            m.name as material_name,
            m.unit as material_unit,
            m.rack_location,
            o.qty,
            o.destination,
            o.issued_by as operator_name,
            'admin' as operator_username,
            o.issued_by as admin_username,
            o.issued_by as admin_name,
            o.reason,
            o.notes,
            'COMPLETED' as status,
            COALESCE(o.started_at, o.created_at) as started_at,
            COALESCE(o.completed_at, o.created_at) as completed_at,
            COALESCE(o.duration_seconds, TIMESTAMPDIFF(SECOND, COALESCE(o.started_at, o.created_at), COALESCE(o.completed_at, o.created_at))) as duration_seconds,
            o.created_at
        FROM outbound_transactions o
        JOIN materials m ON o.material_id = m.id

        ORDER BY created_at DESC
    ";

    $stmt = $pdo->query($query);
    $no = 1;
    while ($r = $stmt->fetch()) {
        $qty = (float)$r['qty'];
        $durSec = max(0, (int)$r['duration_seconds']);
        $taktSec = $qty > 0 ? round($durSec / $qty, 1) : 0;
        
        $durFmt = '';
        if ($durSec > 0) {
            $m = floor($durSec / 60);
            $s = $durSec % 60;
            $durFmt = $m > 0 ? "{$m} mnt {$s} dtk" : "{$s} dtk";
        } else {
            $durFmt = '0 dtk';
        }

        $taktFmt = $taktSec > 0 ? ($taktSec < 60 ? "{$taktSec} dtk/item" : round($taktSec / 60, 1) . " mnt/item") : '-';
        $typeLabel = $r['outbound_type'] === 'TASK_PICKING' ? 'Task Operator' : 'Manual Admin';

        $adminPenugas = $r['admin_username'] ?: ($r['admin_name'] ?: 'admin');
        $operatorPIC = $r['operator_name'] ?: ($r['operator_username'] ?: 'Operator');

        $rows[] = [
            $no++,
            formatExportDateOnly($r['completed_at'] ?: $r['created_at']),
            formatExportDate($r['started_at'] ?: $r['created_at']),
            formatExportDate($r['completed_at'] ?: $r['created_at']),
            $r['outbound_no'],
            $typeLabel,
            $r['status'],
            $r['material_code'],
            $r['material_name'],
            $r['material_unit'] ?: 'Pcs',
            $r['rack_location'] ?: '-',
            $qty,
            $durFmt,
            $taktFmt,
            $r['destination'] ?: '-',
            $operatorPIC,
            $adminPenugas,
            $r['reason'] ?: '-',
            $r['notes'] ?: '-'
        ];
    }

    XlsxWriter::download($filename, $title, $headers, $rows, $colWidths);
}

// =========================================================================
// 4. EXPORT INBOUND TRANSACTIONS (BARANG MASUK) (.xlsx)
// =========================================================================
if ($type === 'inbound' || $type === 'inbound_csv' || $type === 'inbound_excel') {
    $filename = "Laporan_Barang_Masuk_Inbound_" . date('Ymd_His') . ".xlsx";
    $title = "LAPORAN PENERIMAAN BARANG MASUK (INBOUND)";

    $headers = [
        'No',
        'Tanggal',
        'Waktu Mulai',
        'Waktu Submit',
        'No. Inbound',
        'Item No',
        'Nama Packaging Material',
        'Satuan',
        'Lokasi Rak',
        'Qty In',
        'Durasi Kerja',
        'Takt Time',
        'Petugas Penerima',
        'Catatan'
    ];

    $colWidths = [6, 12, 20, 20, 18, 15, 32, 10, 16, 12, 14, 14, 18, 24];
    $date = trim($_GET['date'] ?? '');
    $time = trim($_GET['time'] ?? '');
    $search = trim($_GET['search'] ?? '');

    $query = "
        SELECT 
            i.*,
            m.code as material_code,
            m.name as material_name,
            m.unit as material_unit,
            m.rack_location,
            u.name as receiver_name
        FROM inbound_transactions i
        JOIN materials m ON i.material_id = m.id
        LEFT JOIN users u ON i.received_by = u.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (i.inbound_no LIKE ? OR m.name LIKE ? OR m.code LIKE ? OR u.name LIKE ?)";
        $term = "%{$search}%";
        $params = [$term, $term, $term, $term];
    }

    if (!empty($date)) {
        $query .= " AND DATE(i.created_at) = ?";
        $params[] = $date;
    }

    if (!empty($time)) {
        $query .= " AND TIME_FORMAT(i.created_at, '%H:%i') LIKE ?";
        $params[] = "%{$time}%";
    }

    $query .= " ORDER BY i.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $no = 1;
    while ($r = $stmt->fetch()) {
        $qty = (float)$r['qty'];
        $durSec = max(0, (int)($r['duration_seconds'] ?? 0));
        $taktSec = $qty > 0 ? round($durSec / $qty, 1) : 0;

        $durFmt = '';
        if ($durSec > 0) {
            $m = floor($durSec / 60);
            $s = $durSec % 60;
            $durFmt = $m > 0 ? "{$m} mnt {$s} dtk" : "{$s} dtk";
        } else {
            $durFmt = '0 dtk';
        }

        $taktFmt = $taktSec > 0 ? ($taktSec < 60 ? "{$taktSec} dtk/item" : round($taktSec / 60, 1) . " mnt/item") : '-';

        $rows[] = [
            $no++,
            formatExportDateOnly($r['completed_at'] ?: $r['created_at']),
            formatExportDate($r['started_at'] ?: $r['created_at']),
            formatExportDate($r['completed_at'] ?: $r['created_at']),
            $r['inbound_no'],
            $r['material_code'],
            $r['material_name'],
            $r['material_unit'] ?: 'Pcs',
            $r['rack_location'] ?: '-',
            $qty,
            $durFmt,
            $taktFmt,
            $r['receiver_name'] ?: 'Admin',
            $r['notes'] ?: '-'
        ];
    }

    XlsxWriter::download($filename, $title, $headers, $rows, $colWidths);
}

// =========================================================================
// 5. EXPORT STOCK OPNAME RESULTS (.xlsx)
// =========================================================================
if ($type === 'stock_opname' || $type === 'opname') {
    $opnameId = (int)($_GET['id'] ?? 0);

    $stmtSo = $pdo->prepare("
        SELECT so.*, u.name as creator_name 
        FROM stock_opnames so 
        LEFT JOIN users u ON so.created_by = u.id 
        WHERE so.id = ?
    ");
    $stmtSo->execute([$opnameId]);
    $so = $stmtSo->fetch();

    if (!$so) {
        http_response_code(404);
        die("Sesi Stock Opname tidak ditemukan.");
    }

    $cleanNo = preg_replace('/[^A-Za-z0-9_-]/', '_', $so['opname_no']);
    $typeName = ($so['counting_type'] ?? 'STOCK_OPNAME') === 'DYNAMIC_COUNT' ? 'Dynamic_Counting' : 'Stock_Opname';
    $filename = "Hasil_{$typeName}_{$cleanNo}_" . date('Ymd_His') . ".xlsx";
    $title = "HASIL " . strtoupper(str_replace('_', ' ', $typeName)) . ": {$so['opname_no']} - {$so['title']} (Status: {$so['status']})";

    // 1. Determine max stage in this opname session
    $stmtMax = $pdo->prepare("SELECT MAX(stage_number) FROM stock_opname_item_stages WHERE opname_id = ?");
    $stmtMax->execute([$opnameId]);
    $maxStage = (int)$stmtMax->fetchColumn();
    if ($maxStage < 1) $maxStage = (int)($so['max_stage'] ?? 1);
    if ($maxStage < 1) $maxStage = 1;

    // 2. Build Dynamic Headers & ColWidths
    $headers = [
        'No',
        'Tanggal',
        'Item No',
        'Item Description',
        'Satuan',
        'Lokasi Rak'
    ];
    $colWidths = [6, 18, 16, 34, 12, 14];

    for ($s = 1; $s <= $maxStage; $s++) {
        $sLabel = $s === 1 ? '1st Count' : ($s === 2 ? '2nd Count' : ($s === 3 ? '3rd Count' : "{$s}th Count"));
        $headers[] = "{$sLabel}";
        $colWidths[] = 14;
    }

    $headers[] = 'Qty Final Count';
    $headers[] = 'Qty System';
    $headers[] = 'Difference (+/-)';
    $headers[] = 'Note (Plus/Minus/Balance)';
    $headers[] = 'Catatan';
    $colWidths[] = 14;
    $colWidths[] = 14;
    $colWidths[] = 16;
    $colWidths[] = 22;
    $colWidths[] = 24;

    // 3. Fetch Items
    $stmtItems = $pdo->prepare("
        SELECT soi.*,
               m.code as material_code,
               m.name as material_name,
               m.unit as material_unit,
               m.rack_location as material_rack
        FROM stock_opname_items soi
        JOIN materials m ON soi.material_id = m.id
        WHERE soi.opname_id = ?
        ORDER BY (soi.difference != 0) DESC, m.name ASC
    ");
    $stmtItems->execute([$opnameId]);
    $items = $stmtItems->fetchAll();

    // 4. Fetch Stages Map
    $stmtStages = $pdo->prepare("
        SELECT st.*, u.name as operator_name, u.username as operator_username
        FROM stock_opname_item_stages st
        LEFT JOIN users u ON st.assigned_to = u.id
        WHERE st.opname_id = ?
        ORDER BY st.stage_number ASC
    ");
    $stmtStages->execute([$opnameId]);
    $allStages = $stmtStages->fetchAll();

    $stagesMap = [];
    foreach ($allStages as $st) {
        $stagesMap[$st['item_id']][$st['stage_number']] = $st;
    }

    $rows = [];
    $no = 1;

    foreach ($items as $item) {
        $systemStock = (int)$item['system_stock'];
        $itemId = $item['id'];
        $itemStages = $stagesMap[$itemId] ?? [];

        // Cascade rule: resolve latest stage with non-null count_qty
        $cascadeFinalQty = null;
        $lastCountedAt = null;
        $stageValues = [];

        for ($s = 1; $s <= $maxStage; $s++) {
            $st = $itemStages[$s] ?? null;
            if ($st && $st['count_qty'] !== null) {
                $stageValues[] = (int)$st['count_qty'];
                $cascadeFinalQty = (int)$st['count_qty'];
                $lastCountedAt = $st['counted_at'];
            } elseif ($st) {
                $stageValues[] = 'Belum Hitung';
            } else {
                $stageValues[] = '-';
            }
        }

        $dateFormatted = $lastCountedAt ?: ($item['updated_at'] ?: $item['created_at']);
        $finalQty = ($cascadeFinalQty !== null) ? $cascadeFinalQty : (($item['final_qty'] !== null) ? (int)$item['final_qty'] : $systemStock);
        $diff = $finalQty - $systemStock;

        $diffFormatted = ($diff > 0 ? "+{$diff}" : "{$diff}");
        $noteStatus = $diff === 0 ? 'Balance (0)' : ($diff > 0 ? "Plus (+{$diff})" : "Minus ({$diff})");
        $notes = trim(($item['admin_notes'] ?? $item['notes'] ?? '') ?: '-');

        $row = [
            $no++,
            $dateFormatted,
            $item['material_code'],
            $item['material_name'],
            $item['material_unit'] ?: 'Pcs',
            $item['material_rack'] ?: 'Gudang Utama'
        ];

        foreach ($stageValues as $sv) {
            $row[] = $sv;
        }

        $row[] = $finalQty;
        $row[] = $systemStock;
        $row[] = $diffFormatted;
        $row[] = $noteStatus;
        $row[] = $notes;

        $rows[] = $row;
    }

    XlsxWriter::download($filename, $title, $headers, $rows, $colWidths);
}

// =========================================================================
// 8. TEMPLATE FORMAT EXCEL PENYESUAIAN STOK (.xlsx)
// =========================================================================
if ($type === 'adjust_template') {
    $filename = "Template_Penyesuaian_Stok_Adjust_" . date('Ymd') . ".xlsx";
    $title = "TEMPLATE FORMAT PENYESUAIAN STOK MATERIAL PACKAGING (ADJUST PLUS / MINUS)";

    $headers = [
        'No',
        'Item No',
        'Deskripsi Material Packaging',
        'Satuan',
        'Lokasi Rak',
        'Stok Sistem Saat Ini',
        'Qty Adjust (+/-)',
        'Alasan / Catatan Penyesuaian'
    ];

    $colWidths = [6, 16, 36, 10, 15, 20, 20, 36];
    $rows = [];

    $stmt = $pdo->query("SELECT * FROM materials ORDER BY code ASC");
    $materials = $stmt->fetchAll();

    $no = 1;
    foreach ($materials as $idx => $m) {
        $rows[] = [
            $no++,
            $m['code'],
            $m['name'],
            $m['unit'] ?: 'Pcs',
            $m['rack_location'] ?: '-',
            (int)$m['current_stock'],
            '',
            ''
        ];
    }

    if (empty($rows)) {
        $rows[] = [1, '4000010001', 'Dus E-commerce Hanasui Uk. Kecil', 'Pcs', 'Rak A-01', 100, '+150', 'Penyesuaian Hasil Opname (Surplus Fisik)'];
        $rows[] = [2, '4000010002', 'Dus E-commerce Hanasui Uk. Besar', 'Pcs', 'Rak A-05', 50, '-25', 'Penyesuaian Hasil Opname (Barang Rusak/Reject)'];
        $rows[] = [3, '4000020001', 'Plastik Hanasui Ukuran Besar', 'Pcs', 'Rak B-01', 500, '+50', 'Koreksi Selisih Lapangan'];
    }

    XlsxWriter::download($filename, $title, $headers, $rows, $colWidths);
}

// =========================================================================
// 9. EXPORT RIWAYAT PENYESUAIAN STOK (.xlsx)
// =========================================================================
if ($type === 'adjust_history') {
    $search = trim($_GET['search'] ?? '');
    $date   = trim($_GET['date'] ?? '');
    $time   = trim($_GET['time'] ?? '');
    $filename = "Laporan_Riwayat_Penyesuaian_Stok_" . date('Ymd_His') . ".xlsx";
    $title = "LAPORAN RIWAYAT PENYESUAIAN STOK (ADJUSTMENT AUDIT LOG)";

    $headers = [
        'No',
        'Waktu Penyesuaian',
        'No. Referensi',
        'Item No',
        'Deskripsi Material Packaging',
        'Lokasi Rak',
        'Stok Sebelum',
        'Qty Penyesuaian (+/-)',
        'Stok Akhir',
        'Alasan / Catatan Penyesuaian',
        'Petugas / PIC'
    ];

    $colWidths = [6, 20, 20, 15, 36, 14, 14, 20, 14, 32, 18];
    $rows = [];

    $sql = "
        SELECT sm.*, 
               m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location,
               u.name as user_name, u.username as user_username
        FROM stock_mutations sm
        JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.user_id = u.id
        WHERE sm.type = 'ADJUSTMENT'
    ";
    $params = [];

    if (!empty($search)) {
        $sql .= " AND (sm.reference_no LIKE ? OR sm.notes LIKE ? OR m.name LIKE ? OR m.code LIKE ? OR u.name LIKE ? OR u.username LIKE ?)";
        $term = "%{$search}%";
        $params = [$term, $term, $term, $term, $term, $term];
    }

    if (!empty($date)) {
        $sql .= " AND DATE(sm.created_at) = ?";
        $params[] = $date;
    }

    if (!empty($time)) {
        $sql .= " AND TIME_FORMAT(sm.created_at, '%H:%i') LIKE ?";
        $params[] = "%{$time}%";
    }

    $sql .= " ORDER BY sm.created_at DESC, sm.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $no = 1;
    while ($r = $stmt->fetch()) {
        $qtyChange = (float)$r['qty_change'];
        $qtyFormatted = ($qtyChange > 0 ? "+{$qtyChange}" : "{$qtyChange}");
        $pic = $r['user_name'] ?: ($r['user_username'] ?: 'Admin');

        $rows[] = [
            $no++,
            formatExportDate($r['created_at']),
            $r['reference_no'],
            $r['material_code'],
            $r['material_name'],
            $r['rack_location'] ?: '-',
            (float)$r['stock_before'],
            $qtyFormatted,
            (float)$r['stock_after'],
            $r['notes'] ?: '-',
            $pic
        ];
    }

    XlsxWriter::download($filename, $title, $headers, $rows, $colWidths);
}

// =========================================================================
// 10. EXPORT BUKU MUTASI STOK KESELURUHAN (.xlsx)
// =========================================================================
if ($type === 'mutations') {
    Auth::requireSuperAdmin();
    $search = trim($_GET['search'] ?? '');
    $mutationType = trim($_GET['mutation_type'] ?? '');
    $date   = trim($_GET['date'] ?? '');
    $time   = trim($_GET['time'] ?? '');
    $filename = "Laporan_Buku_Mutasi_Stok_" . date('Ymd_His') . ".xlsx";
    $title = "LAPORAN BUKU MUTASI & AUDIT TRAIL STOK";

    $headers = [
        'No',
        'Waktu Mutasi',
        'Tipe Mutasi',
        'No. Referensi',
        'Item No',
        'Deskripsi Material Packaging',
        'Lokasi Rak',
        'Stok Sebelum',
        'Perubahan (+/-)',
        'Sisa Stok Akhir',
        'Keterangan / Catatan',
        'Petugas PIC'
    ];

    $colWidths = [6, 20, 16, 20, 15, 36, 14, 14, 16, 16, 32, 18];
    $rows = [];

    $sql = "
        SELECT sm.*, 
               m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location,
               u.name as user_name, u.username as user_username
        FROM stock_mutations sm
        JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.user_id = u.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($mutationType) && $mutationType !== 'ALL') {
        $sql .= " AND sm.type = ?";
        $params[] = $mutationType;
    }

    if (!empty($date)) {
        $sql .= " AND DATE(sm.created_at) = ?";
        $params[] = $date;
    }

    if (!empty($time)) {
        $sql .= " AND TIME_FORMAT(sm.created_at, '%H:%i') LIKE ?";
        $params[] = "%{$time}%";
    }

    if (!empty($search)) {
        $sql .= " AND (sm.reference_no LIKE ? OR sm.notes LIKE ? OR m.name LIKE ? OR m.code LIKE ? OR u.name LIKE ? OR u.username LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term, $term, $term]);
    }

    $sql .= " ORDER BY sm.created_at DESC, sm.id DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $no = 1;
    while ($r = $stmt->fetch()) {
        $qtyChange = (float)$r['qty_change'];
        $qtyFormatted = ($qtyChange > 0 ? "+{$qtyChange}" : "{$qtyChange}");
        $pic = $r['user_name'] ?: ($r['user_username'] ?: 'System');

        $rows[] = [
            $no++,
            formatExportDate($r['created_at']),
            $r['type'],
            $r['reference_no'],
            $r['material_code'],
            $r['material_name'],
            $r['rack_location'] ?: '-',
            (float)$r['stock_before'],
            $qtyFormatted,
            (float)$r['stock_after'],
            $r['notes'] ?: '-',
            $pic
        ];
    }

    XlsxWriter::download($filename, $title, $headers, $rows, $colWidths);
}

// =========================================================================
// 10.B. EXPORT DETAIL COUNTING LOGS (.xlsx)
// =========================================================================
if ($type === 'counting_detail' || $type === 'dynamic_counting_detail') {
    $targetCountingType = ($type === 'dynamic_counting_detail' || ($_GET['counting_type'] ?? '') === 'DYNAMIC_COUNT' || ($_GET['type_filter'] ?? '') === 'DYNAMIC_COUNT') ? 'DYNAMIC_COUNT' : 'STOCK_OPNAME';
    $opnameId    = (int)($_GET['opname_id'] ?? 0);
    $stageNumber = (int)($_GET['stage_number'] ?? 0);
    $date        = trim($_GET['date'] ?? '');
    $search      = trim($_GET['search'] ?? '');
    $status      = trim($_GET['status'] ?? '');

    $where = ["so.counting_type = ?"];
    $params = [$targetCountingType];

    if ($opnameId > 0) {
        $where[] = "st.opname_id = ?";
        $params[] = $opnameId;
    }

    if ($stageNumber > 0) {
        $where[] = "st.stage_number = ?";
        $params[] = $stageNumber;
    }

    if (!empty($status) && $status !== 'ALL') {
        $where[] = "st.status = ?";
        $params[] = $status;
    }

    if (!empty($date)) {
        $where[] = "DATE(COALESCE(st.counted_at, st.created_at)) = ?";
        $params[] = $date;
    }

    if (!empty($search)) {
        $term = "%{$search}%";
        $where[] = "(m.code LIKE ? OR m.name LIKE ? OR so.opname_no LIKE ? OR st.scanned_rack LIKE ? OR u.name LIKE ? OR st.notes LIKE ?)";
        $params = array_merge($params, [$term, $term, $term, $term, $term, $term]);
    }

    $whereSql = implode(" AND ", $where);

    $query = "
        SELECT st.*,
               so.opname_no,
               so.title as opname_title,
               so.counting_type,
               m.code as material_code,
               m.name as material_name,
               m.category as material_category,
               m.unit as material_unit,
               m.rack_location as material_rack,
               u.name as operator_name,
               u.username as operator_username,
               u.shift as operator_shift
        FROM stock_opname_item_stages st
        JOIN stock_opnames so ON st.opname_id = so.id
        JOIN stock_opname_items soi ON st.item_id = soi.id
        JOIN materials m ON soi.material_id = m.id
        LEFT JOIN users u ON st.assigned_to = u.id
        WHERE {$whereSql}
        ORDER BY COALESCE(st.counted_at, st.created_at) DESC, st.id DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $isDynamic = $targetCountingType === 'DYNAMIC_COUNT';
    $filename = ($isDynamic ? "Detail_Dynamic_Count_Logs_" : "Detail_Stock_Opname_Logs_") . date('Ymd_His') . ".xlsx";
    $title = $isDynamic ? "LOG DETAIL HASIL DYNAMIC COUNT (BREAKDOWN PER PUTARAN)" : "LOG DETAIL HASIL STOCK OPNAME (BREAKDOWN PER PUTARAN)";

    $headers = [
        'No',
        'No. Dokumen Sesi',
        'Tanggal & Waktu Count',
        'Tipe Counting',
        'Round (Putaran)',
        'Item No',
        'Deskripsi Packaging Material',
        'Satuan',
        'Qty Hasil Count',
        'Lokasi Rak Master',
        'Lokasi Rak Scan',
        'PIC Operator',
        'Shift',
        'Status',
        'Catatan Fisik'
    ];

    $colWidths = [6, 22, 20, 16, 16, 16, 38, 10, 16, 16, 16, 20, 10, 12, 30];
    $rows = [];
    $no = 1;

    while ($r = $stmt->fetch()) {
        $stageLabel = $r['stage_number'] == 1 ? '1st Count' : ($r['stage_number'] == 2 ? '2nd Count' : ($r['stage_number'] == 3 ? '3rd Count' : "{$r['stage_number']}th Count"));
        $typeLabel = $r['counting_type'] === 'DYNAMIC_COUNT' ? 'Dynamic Count' : 'Stock Opname';
        $operator = $r['operator_name'] ?: ($r['operator_username'] ?: 'Operator');

        $rows[] = [
            $no++,
            $r['opname_no'],
            formatExportDate($r['counted_at'] ?: $r['created_at']),
            $typeLabel,
            $stageLabel,
            $r['material_code'],
            $r['material_name'],
            $r['material_unit'] ?: 'Pcs',
            $r['count_qty'] !== null ? (float)$r['count_qty'] : '-',
            $r['material_rack'] ?: '-',
            $r['scanned_rack'] ?: '-',
            $operator,
            $r['operator_shift'] ?: '-',
            $r['status'] ?: 'COUNTED',
            $r['notes'] ?: '-'
        ];
    }

    XlsxWriter::download($filename, $title, $headers, $rows, $colWidths);
}

// =========================================================================
// 11. TEMPLATE FORMAT EXCEL MASTER STOK PACKAGING (.xlsx)
// =========================================================================
if ($type === 'inventory_template') {
    $filename = "Template_Import_Master_Stok_Packaging.xlsx";
    $title = "TEMPLATE IMPORT DATABASE MASTER STOK PACKAGING (KATEGORI OTOMATIS OLEH SISTEM)";

    $headers = [
        'No',
        'Item No',
        'Deskripsi Material Packaging',
        'Satuan (UOM)',
        'Lokasi Rak',
        'Ending Stock (Stok Awal)',
        'Min Safety Stock'
    ];

    $colWidths = [6, 18, 45, 14, 16, 25, 18];
    $rows = [
        [1, '4000010001', 'Dus E-commerce Hanasui Uk. Kecil 255 x 85 x 85 cm', 'Pcs', 'Rak A-01', 15000, 50],
        [2, '4000010002', 'Dus E-commerce Hanasui Uk. Besar 250 x 200 x 170 cm', 'Pcs', 'Rak A-02', 18246, 50],
        [3, '4000020001', 'Plastik Hanasui Ukuran Besar 21,5 x 35 cm', 'Pcs', 'Rak B-01', 725000, 1000],
        [4, '4000030001', 'Lakban Fragile Merah Putih 2 Inch', 'Roll', 'Rak C-03', 350, 20],
        [5, '4000030004', 'Waybill Label A6 100x150mm Thermal', 'Roll', 'Rak C-02', 1824, 50],
        [6, '4000030007', 'Bubble Film Printing 50cm x 100m', 'Roll', 'Zona P-01', 42998, 10],
        [7, '4000030010', 'Honeycomb Paper Panjang 200m', 'Pcs', 'Rak A-01', 2694, 20],
        [8, '4000030012', 'Plastic Wrapping Ukuran 50cm x 200m', 'Roll', 'Zona P-01', 64, 10],
    ];

    XlsxWriter::download($filename, $title, $headers, $rows, $colWidths);
}

// =========================================================================
// 12. EXPORT REKAP RINGKASAN STOK DASHBOARD DENGAN FORMAT EXCEL RESMI (.xlsx)
// =========================================================================
if ($type === 'dashboard_summary' || $type === 'dashboard_stock_summary') {
    $filterType = $_GET['filter_type'] ?? 'date';
    $search     = trim($_GET['search'] ?? '');
    $category   = trim($_GET['category'] ?? 'all');
    $status     = trim($_GET['status'] ?? 'all');

    $now = new DateTime();
    $startDateStr = $now->format('Y-m-d');
    $endDateStr   = $now->format('Y-m-d');
    $periodLabel  = $now->format('d M Y');

    if ($filterType === 'all') {
        $startDateStr = '2000-01-01';
        $endDateStr   = '2099-12-31';
        $periodLabel  = 'Semua Waktu (All-Time)';
    } elseif ($filterType === 'date') {
        $d = trim($_GET['date'] ?? '');
        if ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $startDateStr = $d;
            $endDateStr   = $d;
            $dt = new DateTime($d);
            $periodLabel = 'Tanggal ' . $dt->format('d M Y');
        } else {
            $periodLabel = 'Tanggal Hari Ini (' . $now->format('d M Y') . ')';
        }
    } elseif ($filterType === 'week') {
        $year  = (int)($_GET['year'] ?? $now->format('Y'));
        $month = (int)($_GET['month'] ?? $now->format('n'));
        $week  = (int)($_GET['week'] ?? 1);
        $lastDayOfMonth = (int)(new DateTime("$year-$month-01"))->format('t');
        $startDay = 1;
        $endDay = 7;
        if ($week === 1) { $startDay = 1; $endDay = min(7, $lastDayOfMonth); }
        elseif ($week === 2) { $startDay = 8; $endDay = min(14, $lastDayOfMonth); }
        elseif ($week === 3) { $startDay = 15; $endDay = min(21, $lastDayOfMonth); }
        elseif ($week === 4) { $startDay = 22; $endDay = min(28, $lastDayOfMonth); }
        elseif ($week >= 5) { $startDay = 29; $endDay = $lastDayOfMonth; }

        $startDateStr = sprintf('%04d-%02d-%02d', $year, $month, $startDay);
        $endDateStr   = sprintf('%04d-%02d-%02d', $year, $month, $endDay);
        $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $mName = $monthNames[$month] ?? "Bulan $month";
        $periodLabel = "Week $week - $mName $year (" . date('d M', strtotime($startDateStr)) . " - " . date('d M Y', strtotime($endDateStr)) . ")";
    } elseif ($filterType === 'month') {
        $year  = (int)($_GET['year'] ?? $now->format('Y'));
        $month = (int)($_GET['month'] ?? $now->format('n'));
        $lastDayOfMonth = (int)(new DateTime("$year-$month-01"))->format('t');
        $startDateStr = sprintf('%04d-%02d-01', $year, $month);
        $endDateStr   = sprintf('%04d-%02d-%02d', $year, $month, $lastDayOfMonth);
        $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
        $mName = $monthNames[$month] ?? "Bulan $month";
        $periodLabel = "Bulan $mName $year";
    }

    $startDateTime = $startDateStr . ' 00:00:00';
    $endDateTime   = $endDateStr . ' 23:59:59';

    $sql = "
        SELECT 
            m.id,
            m.code,
            m.name,
            m.unit,
            m.rack_location,
            m.category,
            m.min_stock,
            m.current_stock,
            COALESCE(SUM(CASE WHEN sm.type = 'INBOUND' AND sm.created_at BETWEEN ? AND ? THEN sm.qty_change ELSE 0 END), 0) AS total_inbound,
            COALESCE(SUM(CASE WHEN sm.type IN ('OUTBOUND', 'TASK_PICKING') AND sm.created_at BETWEEN ? AND ? THEN ABS(sm.qty_change) ELSE 0 END), 0) AS total_outbound,
            COALESCE(SUM(CASE WHEN sm.type = 'ADJUSTMENT' AND sm.created_at BETWEEN ? AND ? THEN sm.qty_change ELSE 0 END), 0) AS total_adjustment,
            COALESCE(SUM(CASE WHEN sm.created_at >= ? THEN sm.qty_change ELSE 0 END), 0) AS change_since_start,
            COALESCE(SUM(CASE WHEN sm.created_at > ? THEN sm.qty_change ELSE 0 END), 0) AS change_after_end
        FROM materials m
        LEFT JOIN stock_mutations sm ON m.id = sm.material_id
        WHERE 1=1
    ";
    $params = [$startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime];

    if (!empty($search)) {
        $sql .= " AND (m.code LIKE ? OR m.name LIKE ? OR m.rack_location LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }
    if (!empty($category) && $category !== 'all') {
        $sql .= " AND m.category = ?";
        $params[] = $category;
    }
    $sql .= " GROUP BY m.id ORDER BY m.code ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rowsRaw = $stmt->fetchAll();

    $cleanPeriod = preg_replace('/[^A-Za-z0-9_-]/', '_', $periodLabel);
    $filename = "Rekap_Stok_Dashboard_{$cleanPeriod}_" . date('Ymd_His') . ".xlsx";
    $title = "REKAP RINGKASAN MUTASI & STOK AKHIR DASHBOARD ({$periodLabel})";

    $headers = [
        'No',
        'Item No',
        'Deskripsi Material Packaging / Consumable',
        'Satuan',
        'Lokasi Rak',
        'Kategori',
        'Stok Awal',
        'Barang Masuk (+)',
        'Barang Keluar (-)',
        'Adjustment (+/-)',
        'Stok Akhir',
        'Min Safety Stock',
        'Status Stok'
    ];

    $colWidths = [6, 16, 42, 10, 15, 20, 14, 16, 16, 16, 15, 16, 14];
    $rows = [];
    $no = 1;

    foreach ($rowsRaw as $r) {
        $current = (int)$r['current_stock'];
        $changeSinceStart = (int)$r['change_since_start'];
        $changeAfterEnd = (int)$r['change_after_end'];
        
        $beginningStock = $current - $changeSinceStart;
        $endingStock = $current - $changeAfterEnd;
        $inbound = (int)$r['total_inbound'];
        $outbound = (int)$r['total_outbound'];
        $adjustment = (int)$r['total_adjustment'];
        $minStock = (int)$r['min_stock'];

        $hasActivity = ($inbound !== 0 || $outbound !== 0 || $adjustment !== 0);

        if ($status === 'activity_only' && !$hasActivity) continue;
        if ($status === 'low' && ($endingStock > $minStock || $endingStock <= 0)) continue;
        if ($status === 'empty' && $endingStock > 0) continue;
        if ($status === 'safe' && $endingStock <= $minStock) continue;
        if ($status === 'adjusted_only' && $adjustment === 0) continue;

        $stockStatus = 'AMAN';
        if ($endingStock <= 0) $stockStatus = 'HABIS';
        elseif ($endingStock <= $minStock) $stockStatus = 'MENIPIS';

        $rows[] = [
            $no++,
            $r['code'],
            $r['name'],
            $r['unit'] ?: 'Pcs',
            $r['rack_location'] ?: '-',
            $r['category'] ?: 'Umum',
            $beginningStock,
            $inbound,
            $outbound,
            $adjustment,
            $endingStock,
            $minStock,
            $stockStatus
        ];
    }

    XlsxWriter::download($filename, $title, $headers, $rows, $colWidths);
}

http_response_code(400);
echo "Format ekspor tidak didukung";

