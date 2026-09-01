<?php
// api/outbound.php - Comprehensive Outbound Goods API (Operator Task Picking & Manual Outbound)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

// 1. LIST ALL OUTBOUND TRANSACTIONS (Operator Picking & Manual Outbounds)
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $typeFilter = trim($_GET['type'] ?? 'ALL');
    $statusFilter = trim($_GET['status'] ?? 'ALL');
    $limit  = min(300, max(10, (int)($_GET['limit'] ?? 150)));

    $query = "
        SELECT * FROM (
            SELECT 
                'TASK_PICKING' as outbound_type,
                t.id as task_id,
                t.task_no as outbound_no,
                t.material_id,
                t.assigned_to,
                t.target_qty,
                t.actual_qty,
                (CASE WHEN t.status = 'COMPLETED' THEN t.actual_qty ELSE t.target_qty END) as qty,
                t.destination,
                u_to.name as issued_by,
                u_to.username as issued_by_username,
                u_by.username as assigned_by_name,
                u_by.username as assigned_by_username,
                'Pengambilan Line (Operator Task)' as reason,
                COALESCE(t.completion_notes, t.notes) as notes,
                t.photo_path,
                t.status,
                t.priority,
                COALESCE(t.started_at, t.created_at) as started_at,
                COALESCE(t.completed_at, t.created_at) as completed_at,
                COALESCE(t.duration_seconds, 0) as duration_seconds,
                t.created_at,
                m.code as material_code, m.name as material_name, m.unit as material_unit, m.category as material_category, m.rack_location
            FROM tasks t
            JOIN materials m ON t.material_id = m.id
            JOIN users u_to ON t.assigned_to = u_to.id
            LEFT JOIN users u_by ON t.assigned_by = u_by.id

            UNION ALL

            SELECT 
                'MANUAL_OUTBOUND' as outbound_type,
                NULL as task_id,
                o.outbound_no,
                o.material_id,
                NULL as assigned_to,
                o.qty as target_qty,
                o.qty as actual_qty,
                o.qty,
                o.destination,
                o.issued_by,
                'admin' as issued_by_username,
                o.issued_by as assigned_by_name,
                o.issued_by as assigned_by_username,
                o.reason,
                o.notes,
                o.photo_path,
                'COMPLETED' as status,
                'NORMAL' as priority,
                COALESCE(o.started_at, o.created_at) as started_at,
                COALESCE(o.completed_at, o.created_at) as completed_at,
                COALESCE(o.duration_seconds, 0) as duration_seconds,
                o.created_at,
                m.code as material_code, m.name as material_name, m.unit as material_unit, m.category as material_category, m.rack_location
            FROM outbound_transactions o
            JOIN materials m ON o.material_id = m.id
        ) combined_outbound
        WHERE 1=1
    ";
    $params = [];

    $date = trim($_GET['date'] ?? '');
    $time = trim($_GET['time'] ?? '');

    if (!empty($search)) {
        $query .= " AND (outbound_no LIKE ? OR destination LIKE ? OR reason LIKE ? OR material_name LIKE ? OR material_code LIKE ? OR issued_by LIKE ?)";
        $term = "%{$search}%";
        $params = [$term, $term, $term, $term, $term, $term];
    }

    if (!empty($date)) {
        $query .= " AND created_at LIKE ?";
        $params[] = "{$date}%";
    }

    if (!empty($time)) {
        $query .= " AND created_at LIKE ?";
        $params[] = "% {$time}%";
    }

    if (!empty($typeFilter) && $typeFilter !== 'ALL') {
        $query .= " AND outbound_type = ?";
        $params[] = $typeFilter;
    }

    if (!empty($statusFilter) && $statusFilter !== 'ALL') {
        $query .= " AND status = ?";
        $params[] = $statusFilter;
    }

    $query .= " ORDER BY created_at DESC LIMIT " . $limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Compute Takt Time & Aggregates
    $totalQty = 0;
    $totalDuration = 0;
    $validDurationCount = 0;

    foreach ($rows as &$r) {
        $r['qty'] = (float)$r['qty'];
        $qty = max(0.001, (float)$r['qty']);
        $dur = max(0, (int)$r['duration_seconds']);
        if ($dur <= 0) {
            $dur = 90; // Default baseline 90s if 0
            $r['duration_seconds'] = 90;
        }
        $r['takt_time_seconds'] = round($dur / $qty, 2);
        if ($r['status'] === 'COMPLETED') {
            $totalQty += (float)$r['qty'];
            $totalDuration += $dur;
            $validDurationCount++;
        }
    }
    unset($r);

    $avgDuration = $validDurationCount > 0 ? round($totalDuration / $validDurationCount) : 0;
    $avgTaktTime = ($totalQty > 0 && $totalDuration > 0) ? round($totalDuration / $totalQty, 2) : 0;

    echo json_encode([
        'success' => true, 
        'data' => $rows,
        'metrics' => [
            'total_outbound_qty' => $totalQty,
            'avg_duration_seconds' => $avgDuration,
            'avg_takt_time_seconds' => $avgTaktTime
        ]
    ]);
    exit;
}

// Helper to process uploaded photos
function handleUploadedOutboundPhotos(): ?string {
    if (!isset($_FILES['photos'])) {
        return null;
    }
    $files = $_FILES['photos'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 0;
    if ($fileCount === 0) {
        return null;
    }

    $uploadDir = __DIR__ . '/../uploads/outbound/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $photoPaths = [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    for ($i = 0; $i < $fileCount; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $fileTmpPath = $files['tmp_name'][$i];
            $fileName = $files['name'][$i];
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

            if (in_array($ext, $allowedExtensions)) {
                $newFileName = 'outbound_' . date('Ymd_His') . '_' . substr(md5(uniqid() . $i), 0, 8) . '.' . $ext;
                $destPath = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $photoPaths[] = 'uploads/outbound/' . $newFileName;
                }
            }
        }
    }

    return !empty($photoPaths) ? json_encode($photoPaths) : null;
}

// 2. CREATE OUTBOUND (Barang Keluar Manual)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $materialId  = (int)($input['material_id'] ?? 0);
    $qty         = max(0, parseNumberDecimal($input['qty'] ?? 0));
    $destination = trim($input['destination'] ?? 'Line Produksi');
    $reason      = trim($input['reason'] ?? 'Pemakaian Reguler');
    $notes       = trim($input['notes'] ?? '');
    $startedAt   = trim($input['started_at'] ?? '');
    $photoPathValue = handleUploadedOutboundPhotos();
    $issuedBy    = Auth::name() ?? 'Admin Gudang';

    if ($materialId <= 0 || $qty <= 0 || empty($destination) || empty($reason)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Material, Qty Keluar, Tujuan, dan Alasan pengeluaran wajib diisi!']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // Check stock
        $stmtMat = $pdo->prepare("SELECT id, name, code, current_stock, unit FROM materials WHERE id = ?");
        $stmtMat->execute([$materialId]);
        $mat = $stmtMat->fetch();

        if (!$mat) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Material tidak ditemukan']);
            exit;
        }

        $stockBefore = (float)$mat['current_stock'];
        if ($qty > $stockBefore) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Stok di gudang tidak mencukupi! Sisa stok saat ini hanya " . number_format($stockBefore, 2, ',', '.') . "."]);
            exit;
        }

        $stockAfter = $stockBefore - $qty;

        // Generate Outbound No: OUT-YYYYMM-XXXX
        $prefix = 'OUT-' . date('Ym') . '-';
        $stmtLastOut = $pdo->prepare("SELECT outbound_no FROM outbound_transactions WHERE outbound_no LIKE ? ORDER BY LENGTH(outbound_no) DESC, outbound_no DESC LIMIT 1");
        $stmtLastOut->execute([$prefix . '%']);
        $lastOutNo = $stmtLastOut->fetchColumn();
        $nextNum = 1;
        if ($lastOutNo) {
            $parts = explode('-', $lastOutNo);
            $lastSuffix = end($parts);
            if (is_numeric($lastSuffix)) $nextNum = (int)$lastSuffix + 1;
        }

        $stmtCheckOut = $pdo->prepare("SELECT 1 FROM outbound_transactions WHERE outbound_no = ? LIMIT 1");
        do {
            $outboundNo = $prefix . str_pad($nextNum++, 4, '0', STR_PAD_LEFT);
            $stmtCheckOut->execute([$outboundNo]);
        } while ($stmtCheckOut->fetchColumn());

        $now = date('Y-m-d H:i:s');
        $startTime = !empty($startedAt) ? date('Y-m-d H:i:s', strtotime($startedAt)) : date('Y-m-d H:i:s', time() - 150);
        $durationSeconds = max(1, strtotime($now) - strtotime($startTime));

        // Insert Outbound record
        $stmtOut = $pdo->prepare("
            INSERT INTO outbound_transactions (outbound_no, material_id, qty, destination, issued_by, reason, notes, photo_path, started_at, completed_at, duration_seconds, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtOut->execute([$outboundNo, $materialId, $qty, $destination, $issuedBy, $reason, $notes, $photoPathValue, $startTime, $now, $durationSeconds, $now]);

        // Update Material Stock
        $stmtUpdateMat = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
        $stmtUpdateMat->execute([$stockAfter, $materialId]);

        // Insert Stock Mutation
        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
            VALUES (?, 'OUTBOUND', ?, ?, ?, ?, ?, ?, ?)
        ");
        $mutNotes = "Pengeluaran ke: {$destination} ({$reason})";
        if (!empty($notes)) $mutNotes .= " - " . $notes;

        $stmtMut->execute([$materialId, -$qty, $stockBefore, $stockAfter, $outboundNo, $mutNotes, Auth::id(), $now]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Pengeluaran {$qty} {$mat['unit']} {$mat['name']} berhasil dicatat! Sisa stok sekarang: {$stockAfter} {$mat['unit']}.",
            'outbound_no' => $outboundNo,
            'new_stock' => $stockAfter
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memproses barang keluar: ' . $e->getMessage()]);
    }
    exit;
}

// 3. BATCH CREATE OUTBOUND (Multi-item Table Input)
if ($action === 'batch_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $rawInput = file_get_contents('php://input');
    $input = !empty($rawInput) ? json_decode($rawInput, true) : [];
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    $items       = $input['items'] ?? [];
    if (is_string($items)) {
        $items = json_decode($items, true) ?? [];
    }

    $globalNotes = trim($input['notes'] ?? '');
    $startedAt   = trim($input['started_at'] ?? '');
    $issuedBy    = Auth::name() ?? 'Admin Gudang';

    if (empty($items) || !is_array($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Daftar item pengeluaran tidak boleh kosong!']);
        exit;
    }

    $photoPathValue = handleUploadedOutboundPhotos();

    try {
        $pdo->beginTransaction();

        $prefix = 'OUT-' . date('Ym') . '-';
        $stmtLastOut = $pdo->prepare("SELECT outbound_no FROM outbound_transactions WHERE outbound_no LIKE ? ORDER BY LENGTH(outbound_no) DESC, outbound_no DESC LIMIT 1");
        $stmtLastOut->execute([$prefix . '%']);
        $lastOutNo = $stmtLastOut->fetchColumn();
        $nextNum = 1;
        if ($lastOutNo) {
            $parts = explode('-', $lastOutNo);
            $lastSuffix = end($parts);
            if (is_numeric($lastSuffix)) $nextNum = (int)$lastSuffix + 1;
        }

        $stmtCheckOut = $pdo->prepare("SELECT 1 FROM outbound_transactions WHERE outbound_no = ? LIMIT 1");

        $now = date('Y-m-d H:i:s');
        $startTime = !empty($startedAt) ? date('Y-m-d H:i:s', strtotime($startedAt)) : date('Y-m-d H:i:s', time() - 150);
        $totalDuration = max(1, strtotime($now) - strtotime($startTime));
        $itemCount = count($items);
        $itemDuration = max(1, (int)round($totalDuration / max(1, $itemCount)));

        $stmtOut = $pdo->prepare("
            INSERT INTO outbound_transactions (outbound_no, material_id, qty, destination, issued_by, reason, notes, photo_path, started_at, completed_at, duration_seconds, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtUpdateMat = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
            VALUES (?, 'OUTBOUND', ?, ?, ?, ?, ?, ?, ?)
        ");

        $processedItems = 0;
        $totalQtyProcessed = 0;
        $createdOutboundNos = [];

        foreach ($items as $item) {
            $materialId  = (int)($item['material_id'] ?? 0);
            $qty         = max(0, parseNumberDecimal($item['qty'] ?? 0));
            $destination = trim($item['destination'] ?? 'HANASUI');
            $reason      = trim($item['reason'] ?? 'Kebutuhan Produksi');
            $itemNotes   = trim($item['notes'] ?? '');

            if ($materialId <= 0 || $qty <= 0) continue;

            $stmtMat = $pdo->prepare("SELECT id, name, code, current_stock, unit FROM materials WHERE id = ?");
            $stmtMat->execute([$materialId]);
            $mat = $stmtMat->fetch();
            if (!$mat) continue;

            $stockBefore = (float)$mat['current_stock'];
            if ($qty > $stockBefore) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Stok untuk {$mat['name']} tidak mencukupi! Tersisa " . number_format($stockBefore, 2, ',', '.') . "."]);
                exit;
            }

            $stockAfter = $stockBefore - $qty;
            do {
                $outboundNo = $prefix . str_pad($nextNum++, 4, '0', STR_PAD_LEFT);
                $stmtCheckOut->execute([$outboundNo]);
            } while ($stmtCheckOut->fetchColumn());
            
            $combinedNotes = !empty($globalNotes) ? ($itemNotes ? "{$globalNotes} | {$itemNotes}" : $globalNotes) : $itemNotes;

            $stmtOut->execute([$outboundNo, $materialId, $qty, $destination, $issuedBy, $reason, $combinedNotes, $photoPathValue, $startTime, $now, $itemDuration, $now]);
            $stmtUpdateMat->execute([$stockAfter, $materialId]);

            $mutNotes = "Pengeluaran ke: {$destination} ({$reason})";
            if (!empty($combinedNotes)) $mutNotes .= " - {$combinedNotes}";
            $stmtMut->execute([$materialId, -$qty, $stockBefore, $stockAfter, $outboundNo, $mutNotes, Auth::id(), $now]);

            $processedItems++;
            $totalQtyProcessed += $qty;
            $createdOutboundNos[] = $outboundNo;
        }

        if ($processedItems === 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada item yang valid untuk diproses!']);
            exit;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Berhasil mencatat {$processedItems} item pengeluaran barang (Total: {$totalQtyProcessed} Qty).",
            'processed_items' => $processedItems,
            'total_qty' => $totalQtyProcessed,
            'outbound_nos' => $createdOutboundNos
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memproses pengeluaran batch: ' . $e->getMessage()]);
    }
    exit;
}

// 5. DELETE OUTBOUND TRANSACTION (Super Admin only)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Super Admin yang berhak menghapus data pengeluaran!']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $outboundNo = trim($input['outbound_no'] ?? '');

    if (empty($outboundNo)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nomor dokumen pengeluaran tidak valid']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM outbound_transactions WHERE outbound_no = ?");
        $stmt->execute([$outboundNo]);
        $out = $stmt->fetch();

        if ($out) {
            $matId = (int)$out['material_id'];
            $qty = (float)$out['qty'];

            $stmtMat = $pdo->prepare("SELECT current_stock FROM materials WHERE id = ?");
            $stmtMat->execute([$matId]);
            $currentStock = (float)$stmtMat->fetchColumn();
            $newStock = $currentStock + $qty;

            $stmtUpMat = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
            $stmtUpMat->execute([$newStock, $matId]);

            $stmtMut = $pdo->prepare("
                INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
                VALUES (?, 'ADJUSTMENT', ?, ?, ?, ?, ?, ?, ?)
            ");
            $mutNotes = "Hapus Transaksi Outbound #{$outboundNo} (Stok dikembalikan +{$qty})";
            $stmtMut->execute([$matId, $qty, $currentStock, $newStock, $outboundNo, $mutNotes, Auth::id(), date('Y-m-d H:i:s')]);

            $stmtDel = $pdo->prepare("DELETE FROM outbound_transactions WHERE outbound_no = ?");
            $stmtDel->execute([$outboundNo]);
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => "Dokumen pengeluaran #{$outboundNo} berhasil dihapus & stok dikembalikan!"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus pengeluaran: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi outbound tidak valid']);
