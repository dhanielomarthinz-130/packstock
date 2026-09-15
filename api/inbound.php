<?php
// api/inbound.php - Inbound Goods Receipt API (Single & Multi-Product Draft Batch Commit)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/batch_helper.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

// 1. LIST INBOUND TRANSACTIONS
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $limit  = min(200, max(10, (int)($_GET['limit'] ?? 100)));

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $concatExpr = ($driver === 'sqlite') 
        ? "('INIT-' || COALESCE(m.code, sm.id))" 
        : "CONCAT('INIT-', COALESCE(m.code, sm.id))";

    $query = "
        SELECT * FROM (
            SELECT i.id,
                   0 as is_initial,
                   'INBOUND' as entry_type,
                   i.inbound_no,
                   i.material_id,
                   i.po_number,
                   i.supplier,
                   i.qty,
                   i.notes,
                   i.photo_path,
                   i.batch_no,
                   i.exp_date,
                   i.location,
                   i.created_at,
                   COALESCE(i.started_at, i.created_at) as started_at,
                   COALESCE(i.completed_at, i.created_at) as completed_at,
                   COALESCE(i.duration_seconds, 0) as duration_seconds,
                   m.code as material_code, m.name as material_name, m.unit as material_unit, m.category as material_category, m.rack_location,
                   COALESCE(m.item_type, 'PACKAGING') as material_item_type, m.barcode as material_barcode, m.sap_code as material_sap_code,
                   COALESCE(u.name, i.received_by, 'Admin') as receiver_name,
                   COALESCE(u.username, i.received_by, 'admin') as receiver_username,
                   COALESCE(u.role, 'admin') as receiver_role,
                   COALESCE(u.shift, 'Head Office') as receiver_shift
            FROM inbound_transactions i
            JOIN materials m ON i.material_id = m.id
            LEFT JOIN users u ON (i.received_by = u.id OR i.received_by = u.username OR i.received_by = u.name)

            UNION ALL

            SELECT sm.id,
                   1 as is_initial,
                   'INITIAL_IMPORT' as entry_type,
                   {$concatExpr} as inbound_no,
                   sm.material_id,
                   'STOK AWAL' as po_number,
                   'Upload / Setup Awal' as supplier,
                   sm.qty_change as qty,
                   COALESCE(sm.notes, 'Stok Awal Pendaftaran Material') as notes,
                   NULL as photo_path,
                   NULL as batch_no,
                   NULL as exp_date,
                   m.rack_location as location,
                   sm.created_at,
                   sm.created_at as started_at,
                   sm.created_at as completed_at,
                   0 as duration_seconds,
                   m.code as material_code, m.name as material_name, m.unit as material_unit, m.category as material_category, m.rack_location,
                   COALESCE(m.item_type, 'PACKAGING') as material_item_type, m.barcode as material_barcode, m.sap_code as material_sap_code,
                   COALESCE(u.name, u.username, 'System') as receiver_name,
                   COALESCE(u.username, 'system') as receiver_username,
                   COALESCE(u.role, 'admin') as receiver_role,
                   'Head Office' as receiver_shift
            FROM stock_mutations sm
            JOIN materials m ON sm.material_id = m.id
            LEFT JOIN users u ON sm.user_id = u.id
            WHERE sm.type = 'INITIAL_IMPORT'
        ) combined_inbound
        WHERE 1=1
    ";
    $params = [];

    $itemTypeFilter = trim($_GET['item_type'] ?? '');
    if ($itemTypeFilter === 'GIMMICK') {
        $query .= " AND material_item_type = 'GIMMICK'";
    } elseif ($itemTypeFilter === 'PACKAGING') {
        $query .= " AND (material_item_type = 'PACKAGING' OR material_item_type IS NULL OR material_item_type = '')";
    }

    $date      = trim($_GET['date'] ?? '');
    $startDate = trim($_GET['start_date'] ?? $_GET['from_date'] ?? '');
    $endDate   = trim($_GET['end_date'] ?? $_GET['to_date'] ?? '');
    $time      = trim($_GET['time'] ?? '');

    if (!empty($search)) {
        $query .= " AND (inbound_no LIKE ? OR po_number LIKE ? OR supplier LIKE ? OR material_name LIKE ? OR material_code LIKE ? OR material_sap_code LIKE ? OR material_barcode LIKE ? OR receiver_name LIKE ?)";
        $term = "%{$search}%";
        $params = [$term, $term, $term, $term, $term, $term, $term, $term];
    }

    if (!empty($startDate)) {
        $query .= " AND DATE(created_at) >= ?";
        $params[] = $startDate;
    }

    if (!empty($endDate)) {
        $query .= " AND DATE(created_at) <= ?";
        $params[] = $endDate;
    }

    if (!empty($date) && empty($startDate) && empty($endDate)) {
        $query .= " AND created_at LIKE ?";
        $params[] = "{$date}%";
    }

    if (!empty($time)) {
        $query .= " AND created_at LIKE ?";
        $params[] = "% {$time}%";
    }

    $query .= " ORDER BY created_at DESC LIMIT " . $limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Compute Takt Time and KPI aggregates
    $totalQty = 0;
    $totalDuration = 0;
    $validDurationCount = 0;

    foreach ($rows as &$r) {
        $r['qty'] = (float)$r['qty'];
        $qty = max(0.001, (float)$r['qty']);
        $dur = max(0, (int)$r['duration_seconds']);
        // If duration was 0 (instant legacy entry), assign baseline estimate 60s for meaningful takt time display
        if ($dur <= 0 && empty($r['is_initial'])) {
            $dur = 60;
            $r['duration_seconds'] = 60;
        }
        $r['takt_time_seconds'] = $qty > 0 && $dur > 0 ? round($dur / $qty, 2) : 0;
        $totalQty += (float)$r['qty'];
        $totalDuration += $dur;
        if ($dur > 0) $validDurationCount++;
    }
    unset($r);

    $avgDuration = $validDurationCount > 0 ? round($totalDuration / $validDurationCount) : 0;
    $avgTaktTime = ($totalQty > 0 && $totalDuration > 0) ? round($totalDuration / $totalQty, 2) : 0;

    echo json_encode([
        'success' => true, 
        'data' => $rows,
        'metrics' => [
            'total_inbound_qty' => $totalQty,
            'avg_duration_seconds' => $avgDuration,
            'avg_takt_time_seconds' => $avgTaktTime
        ]
    ]);
    exit;
}

// 1.1 GET SINGLE INBOUND DETAIL (By ID, Inbound No, or PO Number)
if ($action === 'detail') {
    $id         = (int)($_GET['id'] ?? 0);
    $inboundNo  = trim($_GET['inbound_no'] ?? '');
    $poNumber   = trim($_GET['po_number'] ?? '');
    $materialId = (int)($_GET['material_id'] ?? 0);

    // Cek jika ID atau nomor inbound merujuk ke Stok Awal
    if (str_starts_with($inboundNo, 'INIT-') || $poNumber === 'STOK AWAL') {
        $matCode = str_starts_with($inboundNo, 'INIT-') ? substr($inboundNo, 5) : '';
        $query = "
            SELECT sm.id,
                   1 as is_initial,
                   'INITIAL_IMPORT' as entry_type,
                   sm.reference_no as inbound_no,
                   sm.material_id,
                   'STOK AWAL' as po_number,
                   'Upload / Setup Awal' as supplier,
                   sm.qty_change as qty,
                   COALESCE(sm.notes, 'Stok Awal Pendaftaran Material') as notes,
                   NULL as photo_path,
                   NULL as batch_no,
                   NULL as exp_date,
                   m.rack_location as location,
                   sm.created_at,
                   sm.created_at as started_at,
                   sm.created_at as completed_at,
                   0 as duration_seconds,
                   m.code as material_code, m.name as material_name, m.unit as material_unit, m.category as material_category, m.rack_location,
                   COALESCE(m.item_type, 'PACKAGING') as material_item_type, m.barcode as material_barcode, m.sap_code as material_sap_code,
                   COALESCE(u.name, u.username, 'System') as receiver_name,
                   COALESCE(u.username, 'system') as receiver_username,
                   COALESCE(u.role, 'admin') as receiver_role,
                   'Head Office' as receiver_shift
            FROM stock_mutations sm
            JOIN materials m ON sm.material_id = m.id
            LEFT JOIN users u ON sm.user_id = u.id
            WHERE sm.type = 'INITIAL_IMPORT'
        ";
        $params = [];
        if (!empty($matCode)) {
            $query .= " AND (m.code = ? OR sm.id = ?)";
            $params = [$matCode, (int)$matCode];
        } elseif ($materialId > 0) {
            $query .= " AND sm.material_id = ?";
            $params = [$materialId];
        }
        $query .= " ORDER BY sm.id DESC LIMIT 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $detail = $stmt->fetch();
        if ($detail) {
            $detail['qty'] = (float)$detail['qty'];
            echo json_encode(['success' => true, 'data' => $detail]);
            exit;
        }
    }

    $query = "
        SELECT i.*, 
               COALESCE(i.started_at, i.created_at) as started_at,
               COALESCE(i.completed_at, i.created_at) as completed_at,
               COALESCE(i.duration_seconds, 0) as duration_seconds,
               m.code as material_code, m.name as material_name, m.unit as material_unit, m.category as material_category, m.rack_location,
               COALESCE(m.item_type, 'PACKAGING') as material_item_type, m.barcode as material_barcode, m.sap_code as material_sap_code,
               COALESCE(u.name, i.received_by, 'Admin') as receiver_name,
               COALESCE(u.username, i.received_by, 'admin') as receiver_username,
               COALESCE(u.role, 'admin') as receiver_role,
               COALESCE(u.shift, 'Head Office') as receiver_shift
        FROM inbound_transactions i
        JOIN materials m ON i.material_id = m.id
        LEFT JOIN users u ON (i.received_by = u.id OR i.received_by = u.username OR i.received_by = u.name)
        WHERE 1=1
    ";
    $params = [];

    if ($id > 0) {
        $query .= " AND i.id = ?";
        $params[] = $id;
    } elseif (!empty($inboundNo)) {
        $query .= " AND i.inbound_no = ?";
        $params[] = $inboundNo;
    } elseif (!empty($poNumber)) {
        $query .= " AND UPPER(TRIM(i.po_number)) = UPPER(TRIM(?))";
        $params[] = $poNumber;
        if ($materialId > 0) {
            $query .= " AND i.material_id = ?";
            $params[] = $materialId;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Parameter ID atau No. Inbound / PO diperlukan']);
        exit;
    }

    $query .= " ORDER BY i.id DESC LIMIT 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $detail = $stmt->fetch();

    if ($detail) {
        $detail['qty'] = (float)$detail['qty'];
        echo json_encode(['success' => true, 'data' => $detail]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data penerimaan barang masuk tidak ditemukan']);
    }
    exit;
}

// Helper to process uploaded photos
function handleUploadedInboundPhotos(): ?string {
    if (!isset($_FILES['photos'])) {
        return null;
    }
    $files = $_FILES['photos'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 0;
    if ($fileCount === 0) {
        return null;
    }

    $uploadDir = __DIR__ . '/../uploads/inbound/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $photoPaths = [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $fileTmpPath = $files['tmp_name'][$i];
            $fileName = $files['name'][$i];
            $ext = validateUploadedPhoto($fileTmpPath, $fileName, (int)($files['size'][$i] ?? 0));
            if ($ext !== null) {
                $newFileName = 'inbound_' . date('Ymd_His') . '_' . substr(md5(uniqid() . $i), 0, 8) . '.' . $ext;
                $destPath = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $photoPaths[] = 'uploads/inbound/' . $newFileName;
                }
            }
        }
    }

    return !empty($photoPaths) ? json_encode($photoPaths) : null;
}

// 2. CREATE SINGLE INBOUND (Admin or Operator)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $poNumber   = trim($input['po_number'] ?? '-');
    if (empty($poNumber)) $poNumber = '-';
    $supplier   = trim($input['supplier'] ?? '-');
    if (empty($supplier)) $supplier = '-';
    $materialId = (int)($input['material_id'] ?? 0);
    $qty        = max(0, parseNumberDecimal($input['qty'] ?? 0));
    $notes      = trim($input['notes'] ?? '');
    $startedAt  = trim($input['started_at'] ?? '');
    $batchNo    = trim($input['batch_no'] ?? '');
    $expDate    = normalizeExpDateToDb(trim($input['exp_date'] ?? ''));
    $location   = trim($input['location'] ?? $input['rack_location'] ?? 'Gudang Kecil');
    if (empty($location) || strtolower($location) === 'pusat') {
        $location = 'Gudang Kecil';
    }
    $photoPathValue = handleUploadedInboundPhotos();

    if ($materialId <= 0 || $qty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Kemas dan Jumlah Masuk (Qty) wajib diisi lebih dari 0!']);
        exit;
    }

    if ($qtyError = validateQtyRange($qty, 'Jumlah Masuk (Qty)')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $qtyError]);
        exit;
    }

    // Validasi Pembekuan (Freeze) SKU saat barang masuk
    $freeze = getMaterialDynamicCountFreeze($pdo, $materialId);
    if ($freeze) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Penerimaan Barang Masuk (Inbound) DITOLAK! SKU '{$freeze['material_name']}' ({$freeze['material_code']}) sedang dalam sesi Dynamic Count aktif (#{$freeze['opname_no']}) dan dibekukan (Freeze) sampai sesi diselesaikan."
        ]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtMat = $pdo->prepare("SELECT id, name, current_stock, unit FROM materials WHERE id = ?" . rowLockClause($pdo));
        $stmtMat->execute([$materialId]);
        $mat = $stmtMat->fetch();

        if (!$mat) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Material tidak ditemukan']);
            exit;
        }

        $stockBefore = (float)$mat['current_stock'];
        $stockAfter  = $stockBefore + $qty;

        $prefix = 'INB-' . date('Ym') . '-';
        $stmtLastIn = $pdo->prepare("SELECT inbound_no FROM inbound_transactions WHERE inbound_no LIKE ? ORDER BY LENGTH(inbound_no) DESC, inbound_no DESC LIMIT 1");
        $stmtLastIn->execute([$prefix . '%']);
        $lastInNo = $stmtLastIn->fetchColumn();
        $nextNum = 1;
        if ($lastInNo) {
            $parts = explode('-', $lastInNo);
            $lastSuffix = end($parts);
            if (is_numeric($lastSuffix)) $nextNum = (int)$lastSuffix + 1;
        }

        $stmtCheckIn = $pdo->prepare("SELECT 1 FROM inbound_transactions WHERE inbound_no = ? LIMIT 1");
        do {
            $inboundNo = $prefix . str_pad($nextNum++, 4, '0', STR_PAD_LEFT);
            $stmtCheckIn->execute([$inboundNo]);
        } while ($stmtCheckIn->fetchColumn());

        $now = date('Y-m-d H:i:s');
        $startTime = !empty($startedAt) ? date('Y-m-d H:i:s', strtotime($startedAt)) : date('Y-m-d H:i:s', time() - 120);
        $durationSeconds = max(1, strtotime($now) - strtotime($startTime));

        // Insert inbound record with batch_no, exp_date, and location
        $stmtIn = $pdo->prepare("
            INSERT INTO inbound_transactions (inbound_no, po_number, supplier, material_id, qty, notes, photo_path, received_by, started_at, completed_at, duration_seconds, created_at, batch_no, exp_date, location)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIn->execute([$inboundNo, $poNumber, $supplier, $materialId, $qty, $notes, $photoPathValue, Auth::id(), $startTime, $now, $durationSeconds, $now, $batchNo ?: null, $expDate ?: null, $location]);

        // Update Material Stock in Master Product (and update rack_location if provided)
        if (!empty($location) && strtolower(trim($location)) !== 'pusat') {
            $stmtUpdateMat = $pdo->prepare("UPDATE materials SET current_stock = ?, rack_location = ? WHERE id = ?");
            $stmtUpdateMat->execute([$stockAfter, trim($location), $materialId]);
        } else {
            $stmtUpdateMat = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
            $stmtUpdateMat->execute([$stockAfter, $materialId]);
        }

        // Record or update batch details in material_batches
        recordBatchInbound($pdo, $materialId, $batchNo, $expDate, $location, $qty, $notes);

        // Record Stock Mutation
        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
            VALUES (?, 'INBOUND', ?, ?, ?, ?, ?, ?, ?)
        ");
        $mutNotes = "Penerimaan Barang Masuk (PO: " . ($poNumber ?: '-') . " dari " . ($supplier ?: '-') . ")";
        if (!empty($batchNo)) $mutNotes .= " [Batch: {$batchNo}]";
        if (!empty($location)) $mutNotes .= " [Lokasi: {$location}]";
        if (!empty($notes)) $mutNotes .= " - {$notes}";
        $stmtMut->execute([$materialId, $qty, $stockBefore, $stockAfter, $inboundNo, $mutNotes, Auth::id(), $now]);

        // Auto-update status di PO tracking jika ada PO aktif dengan nomor yang sama
        if (!empty($poNumber) && $poNumber !== '-') {
            try {
                $stmtPoUpd = $pdo->prepare("
                    UPDATE material_po_trackings 
                    SET status = 'RECEIVED', updated_at = CURRENT_TIMESTAMP 
                    WHERE material_id = ? AND UPPER(TRIM(po_number)) = UPPER(TRIM(?)) AND status IN ('ORDERED', 'SHIPPED')
                ");
                $stmtPoUpd->execute([$materialId, $poNumber]);
            } catch (Throwable $pe) {}
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Barang masuk {$mat['name']} sebanyak {$qty} {$mat['unit']} berhasil disimpan!",
            'inbound_no' => $inboundNo,
            'new_stock' => $stockAfter
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal memproses barang masuk.');
    }
    exit;
}

// 4. BATCH MULTIPLE INBOUND TRANSACTIONS (Multi-Item Submissions)
if ($action === 'batch_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    $items       = $input['items'] ?? [];
    if (is_string($items)) {
        $items = json_decode($items, true) ?? [];
    }

    $globalPoNumber = trim($input['po_number'] ?? '');
    $globalSupplier = trim($input['supplier'] ?? '');
    $globalNotes    = trim($input['notes'] ?? '');
    $startedAt      = trim($input['started_at'] ?? '');

    if (empty($items) || !is_array($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Daftar item barang masuk tidak boleh kosong!']);
        exit;
    }

    $photoPathValue = handleUploadedInboundPhotos();

    try {
        $pdo->beginTransaction();

        $prefix = 'INB-' . date('Ym') . '-';
        $stmtLastIn = $pdo->prepare("SELECT inbound_no FROM inbound_transactions WHERE inbound_no LIKE ? ORDER BY LENGTH(inbound_no) DESC, inbound_no DESC LIMIT 1");
        $stmtLastIn->execute([$prefix . '%']);
        $lastInNo = $stmtLastIn->fetchColumn();
        $nextNum = 1;
        if ($lastInNo) {
            $parts = explode('-', $lastInNo);
            $lastSuffix = end($parts);
            if (is_numeric($lastSuffix)) $nextNum = (int)$lastSuffix + 1;
        }

        $stmtCheckIn = $pdo->prepare("SELECT 1 FROM inbound_transactions WHERE inbound_no = ? LIMIT 1");

        $now = date('Y-m-d H:i:s');
        $startTime = !empty($startedAt) ? date('Y-m-d H:i:s', strtotime($startedAt)) : date('Y-m-d H:i:s', time() - 300);
        $totalDuration = max(1, strtotime($now) - strtotime($startTime));
        $itemCount = max(1, count($items));
        $itemDuration = max(1, round($totalDuration / $itemCount));

        $stmtIn = $pdo->prepare("
            INSERT INTO inbound_transactions (inbound_no, po_number, supplier, material_id, qty, notes, photo_path, received_by, started_at, completed_at, duration_seconds, created_at, batch_no, exp_date, location)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtUpMat = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");

        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
            VALUES (?, 'INBOUND', ?, ?, ?, ?, ?, ?, ?)
        ");

        $processedItems = 0;
        $totalQtyProcessed = 0;
        $createdInboundNos = [];
        $authId = Auth::id();
        $authName = Auth::name() ?? 'Operator';

        foreach ($items as $item) {
            $materialId  = (int)($item['material_id'] ?? 0);
            $qty         = max(0, parseNumberDecimal($item['qty'] ?? 0));
            $poNumber    = trim($item['po_number'] ?? $globalPoNumber);
            $supplier    = trim($item['supplier'] ?? $globalSupplier);
            $itemNotes   = trim($item['notes'] ?? '');
            $batchNo     = trim($item['batch_no'] ?? '');
            $expDate     = normalizeExpDateToDb(trim($item['exp_date'] ?? ''));
            $location    = trim($item['location'] ?? $item['rack_location'] ?? 'Gudang Kecil');
            if (empty($location) || strtolower($location) === 'pusat') {
                $location = 'Gudang Kecil';
            }

            if ($materialId <= 0 || $qty <= 0) continue;

            if ($qtyError = validateQtyRange($qty, 'Jumlah Masuk (Qty)')) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => $qtyError]);
                exit;
            }

            // Validasi Pembekuan (Freeze) SKU dalam batch
            $freeze = getMaterialDynamicCountFreeze($pdo, $materialId);
            if ($freeze) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "Penerimaan Barang Masuk Batch DITOLAK! SKU '{$freeze['material_name']}' ({$freeze['material_code']}) sedang dalam sesi Dynamic Count aktif (#{$freeze['opname_no']}) dan dibekukan (Freeze)."
                ]);
                exit;
            }

            $stmtMat = $pdo->prepare("SELECT id, name, code, current_stock, unit FROM materials WHERE id = ?" . rowLockClause($pdo));
            $stmtMat->execute([$materialId]);
            $mat = $stmtMat->fetch();
            if (!$mat) continue;

            $stockBefore = (float)$mat['current_stock'];
            $stockAfter  = $stockBefore + $qty;

            do {
                $inboundNo = $prefix . str_pad($nextNum++, 4, '0', STR_PAD_LEFT);
                $stmtCheckIn->execute([$inboundNo]);
            } while ($stmtCheckIn->fetchColumn());

            $combinedNotes = !empty($globalNotes) ? ($itemNotes ? "{$globalNotes} | {$itemNotes}" : $globalNotes) : $itemNotes;

            $stmtIn->execute([$inboundNo, $poNumber, $supplier, $materialId, $qty, $combinedNotes, $photoPathValue, $authId, $startTime, $now, $itemDuration, $now, $batchNo ?: null, $expDate ?: null, $location]);
            $stmtUpMat->execute([$stockAfter, $materialId]);

            // Record or update batch details in material_batches
            recordBatchInbound($pdo, $materialId, $batchNo, $expDate, $location, $qty, $combinedNotes);

            $mutNotes = "Penerimaan Barang Masuk (PO: " . ($poNumber ?: '-') . " dari " . ($supplier ?: '-') . ")";
            if (!empty($batchNo)) $mutNotes .= " [Batch: {$batchNo}]";
            if (!empty($location)) $mutNotes .= " [Lokasi: {$location}]";
            if (!empty($combinedNotes)) $mutNotes .= " - {$combinedNotes}";
            $stmtMut->execute([$materialId, $qty, $stockBefore, $stockAfter, $inboundNo, $mutNotes, $authId, $now]);

            // Auto-update status di PO tracking jika ada PO aktif dengan nomor yang sama
            if (!empty($poNumber) && $poNumber !== '-') {
                try {
                    $stmtPoUpd = $pdo->prepare("
                        UPDATE material_po_trackings 
                        SET status = 'RECEIVED', updated_at = CURRENT_TIMESTAMP 
                        WHERE material_id = ? AND UPPER(TRIM(po_number)) = UPPER(TRIM(?)) AND status IN ('ORDERED', 'SHIPPED')
                    ");
                    $stmtPoUpd->execute([$materialId, $poNumber]);
                } catch (Throwable $pe) {}
            }

            $processedItems++;
            $totalQtyProcessed += $qty;
            $createdInboundNos[] = $inboundNo;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Berhasil memproses {$processedItems} kemas (Total: {$totalQtyProcessed} pcs). Stok master berhasil ditambahkan!",
            'total_items' => $processedItems,
            'total_qty' => $totalQtyProcessed,
            'inbound_nos' => $createdInboundNos,
            'duration_seconds' => $totalDuration,
            'takt_time_seconds' => round($totalDuration / max(1, $totalQtyProcessed), 2)
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal input batch barang masuk.');
    }
    exit;
}

// 4. GET SINGLE INBOUND DETAIL (Admin / Operator)
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID inbound tidak valid']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT i.*, 
               m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location, m.current_stock as material_current_stock,
               COALESCE(u.name, i.received_by, 'Admin') as receiver_name
        FROM inbound_transactions i
        JOIN materials m ON i.material_id = m.id
        LEFT JOIN users u ON (i.received_by = u.id OR i.received_by = u.username OR i.received_by = u.name)
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $data = $stmt->fetch();

    if ($data) {
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data transaksi inbound tidak ditemukan']);
    }
    exit;
}

// 5. UPDATE INBOUND TRANSACTION (Admin Only)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $id         = (int)($input['id'] ?? 0);
    $materialId = (int)($input['material_id'] ?? 0);
    $qty        = max(0, parseNumberDecimal($input['qty'] ?? 0));
    $poNumber   = trim($input['po_number'] ?? '-');
    if (empty($poNumber)) $poNumber = '-';
    $supplier   = trim($input['supplier'] ?? '-');
    if (empty($supplier)) $supplier = '-';
    $notes      = trim($input['notes'] ?? '');
    $createdAt  = trim($input['created_at'] ?? '');

    if ($id <= 0 || $materialId <= 0 || $qty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Transaksi, Material, dan Jumlah Masuk (Qty > 0) wajib diisi!']);
        exit;
    }

    if ($qtyError = validateQtyRange($qty, 'Jumlah Masuk (Qty)')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $qtyError]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtOld = $pdo->prepare("SELECT * FROM inbound_transactions WHERE id = ?");
        $stmtOld->execute([$id]);
        $oldInbound = $stmtOld->fetch();

        if (!$oldInbound) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Data transaksi inbound tidak ditemukan.']);
            exit;
        }

        $oldMaterialId = (int)$oldInbound['material_id'];
        $oldQty        = (float)$oldInbound['qty'];
        $inboundNo     = $oldInbound['inbound_no'];
        $now           = date('Y-m-d H:i:s');
        $effectiveDate = !empty($createdAt) ? date('Y-m-d H:i:s', strtotime($createdAt)) : $oldInbound['created_at'];

        // If material didn't change:
        if ($oldMaterialId === $materialId) {
            $stmtMat = $pdo->prepare("SELECT id, name, current_stock, unit FROM materials WHERE id = ?" . rowLockClause($pdo));
            $stmtMat->execute([$materialId]);
            $mat = $stmtMat->fetch();
            if (!$mat) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Material tidak ditemukan.']);
                exit;
            }

            $currentStock = (float)$mat['current_stock'];
            $qtyDiff      = $qty - $oldQty;
            $newStock     = $currentStock + $qtyDiff;

            if ($newStock < 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Stok gudang tidak dapat bernilai negatif setelah koreksi (Stok saat ini: {$currentStock}, Koreksi: {$qtyDiff})."]);
                exit;
            }

            // Update Material Stock
            $stmtUpMat = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
            $stmtUpMat->execute([$newStock, $materialId]);

            // Update Inbound Transaction
            $stmtUpIn = $pdo->prepare("
                UPDATE inbound_transactions 
                SET material_id = ?, qty = ?, po_number = ?, supplier = ?, notes = ?, created_at = ?, completed_at = ?
                WHERE id = ?
            ");
            $stmtUpIn->execute([$materialId, $qty, $poNumber, $supplier, $notes, $effectiveDate, $effectiveDate, $id]);

            // Record Mutation if Qty Changed
            if ($qtyDiff != 0) {
                $stmtMut = $pdo->prepare("
                    INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
                    VALUES (?, 'ADJUSTMENT', ?, ?, ?, ?, ?, ?, ?)
                ");
                $mutNotes = "Koreksi Edit Transaksi Inbound #{$inboundNo} (Qty lama: {$oldQty} -> Qty baru: {$qty})";
                $stmtMut->execute([$materialId, $qtyDiff, $currentStock, $newStock, $inboundNo, $mutNotes, Auth::id(), $now]);
            }
        } else {
            // Material changed!
            // 1. Revert stock from old material
            $stmtOldMat = $pdo->prepare("SELECT id, name, current_stock FROM materials WHERE id = ?" . rowLockClause($pdo));
            $stmtOldMat->execute([$oldMaterialId]);
            $oldMat = $stmtOldMat->fetch();
            if ($oldMat) {
                $oldMatStockBefore = (float)$oldMat['current_stock'];
                $oldMatStockAfter  = $oldMatStockBefore - $oldQty;
                if ($oldMatStockAfter < 0) {
                    $pdo->rollBack();
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => "Stok material lama ({$oldMat['name']}) tidak mencukupi untuk dibatalkan (Sisa: {$oldMatStockBefore})."]);
                    exit;
                }
                $stmtUpOld = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
                $stmtUpOld->execute([$oldMatStockAfter, $oldMaterialId]);

                $stmtMutOld = $pdo->prepare("
                    INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
                    VALUES (?, 'ADJUSTMENT', ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtMutOld->execute([$oldMaterialId, -$oldQty, $oldMatStockBefore, $oldMatStockAfter, $inboundNo, "Pembatalan Material Lama via Edit Inbound #{$inboundNo}", Auth::id(), $now]);
            }

            // 2. Add stock to new material
            $stmtNewMat = $pdo->prepare("SELECT id, name, current_stock FROM materials WHERE id = ?" . rowLockClause($pdo));
            $stmtNewMat->execute([$materialId]);
            $newMat = $stmtNewMat->fetch();
            if (!$newMat) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Material baru tidak ditemukan.']);
                exit;
            }

            $newMatStockBefore = (float)$newMat['current_stock'];
            $newMatStockAfter  = $newMatStockBefore + $qty;

            $stmtUpNew = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
            $stmtUpNew->execute([$newMatStockAfter, $materialId]);

            $stmtMutNew = $pdo->prepare("
                INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
                VALUES (?, 'INBOUND', ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtMutNew->execute([$materialId, $qty, $newMatStockBefore, $newMatStockAfter, $inboundNo, "Pengalihan Material Baru via Edit Inbound #{$inboundNo}", Auth::id(), $now]);

            // Update Inbound Transaction
            $stmtUpIn = $pdo->prepare("
                UPDATE inbound_transactions 
                SET material_id = ?, qty = ?, po_number = ?, supplier = ?, notes = ?, created_at = ?, completed_at = ?
                WHERE id = ?
            ");
            $stmtUpIn->execute([$materialId, $qty, $poNumber, $supplier, $notes, $effectiveDate, $effectiveDate, $id]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Transaksi Inbound #{$inboundNo} berhasil diperbarui!",
            'inbound_no' => $inboundNo
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal memperbarui transaksi inbound.');
    }
    exit;
}

// 6. DELETE INBOUND TRANSACTION (Admin Only)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Transaksi tidak valid.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM inbound_transactions WHERE id = ?");
        $stmt->execute([$id]);
        $inbound = $stmt->fetch();

        if (!$inbound) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Data transaksi inbound tidak ditemukan.']);
            exit;
        }

        $materialId = (int)$inbound['material_id'];
        $qty        = (float)$inbound['qty'];
        $inboundNo  = $inbound['inbound_no'];
        $now        = date('Y-m-d H:i:s');

        // Check and revert stock
        $stmtMat = $pdo->prepare("SELECT id, name, current_stock FROM materials WHERE id = ?" . rowLockClause($pdo));
        $stmtMat->execute([$materialId]);
        $mat = $stmtMat->fetch();

        if ($mat) {
            $stockBefore = (float)$mat['current_stock'];
            $stockAfter  = $stockBefore - $qty;
            if ($stockAfter < 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Stok material {$mat['name']} saat ini ({$stockBefore}) tidak mencukupi untuk dibatalkan sejumlah {$qty}."]);
                exit;
            }

            $stmtUp = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
            $stmtUp->execute([$stockAfter, $materialId]);

            $stmtMut = $pdo->prepare("
                INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
                VALUES (?, 'ADJUSTMENT', ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtMut->execute([$materialId, -$qty, $stockBefore, $stockAfter, $inboundNo, "Penghapusan Transaksi Inbound #{$inboundNo} oleh " . (Auth::name() ?? 'Admin'), Auth::id(), $now]);
        }

        $stmtDel = $pdo->prepare("DELETE FROM inbound_transactions WHERE id = ?");
        $stmtDel->execute([$id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Transaksi Inbound #{$inboundNo} berhasil dihapus dan stok master telah dikembalikan."
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal menghapus transaksi.');
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi inbound tidak valid']);

