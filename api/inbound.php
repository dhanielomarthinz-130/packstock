<?php
// api/inbound.php - Inbound Goods Receipt API (Single & Multi-Product Draft Batch Commit)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

// 1. LIST INBOUND TRANSACTIONS
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $limit  = min(200, max(10, (int)($_GET['limit'] ?? 100)));

    $query = "
        SELECT i.*, 
               COALESCE(i.started_at, i.created_at) as started_at,
               COALESCE(i.completed_at, i.created_at) as completed_at,
               COALESCE(i.duration_seconds, TIMESTAMPDIFF(SECOND, COALESCE(i.started_at, i.created_at), COALESCE(i.completed_at, i.created_at))) as duration_seconds,
               m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location,
               u.name as receiver_name, u.username as receiver_username, u.role as receiver_role, u.shift as receiver_shift
        FROM inbound_transactions i
        JOIN materials m ON i.material_id = m.id
        LEFT JOIN users u ON i.received_by = u.id
        WHERE 1=1
    ";
    $params = [];

    $date = trim($_GET['date'] ?? '');
    $time = trim($_GET['time'] ?? '');

    if (!empty($search)) {
        $query .= " AND (i.inbound_no LIKE ? OR i.po_number LIKE ? OR i.supplier LIKE ? OR m.name LIKE ? OR m.code LIKE ? OR u.name LIKE ?)";
        $term = "%{$search}%";
        $params = [$term, $term, $term, $term, $term, $term];
    }

    if (!empty($date)) {
        $query .= " AND DATE(i.created_at) = ?";
        $params[] = $date;
    }

    if (!empty($time)) {
        $query .= " AND TIME_FORMAT(i.created_at, '%H:%i') LIKE ?";
        $params[] = "%{$time}%";
    }

    $query .= " ORDER BY i.created_at DESC LIMIT " . $limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Compute Takt Time and KPI aggregates
    $totalQty = 0;
    $totalDuration = 0;
    $validDurationCount = 0;

    foreach ($rows as &$r) {
        $qty = max(1, (int)$r['qty']);
        $dur = max(0, (int)$r['duration_seconds']);
        // If duration was 0 (instant legacy entry), assign baseline estimate 60s for meaningful takt time display
        if ($dur <= 0) {
            $dur = 60;
            $r['duration_seconds'] = 60;
        }
        $r['takt_time_seconds'] = round($dur / $qty, 2);
        $totalQty += (int)$r['qty'];
        $totalDuration += $dur;
        $validDurationCount++;
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

// 2. CREATE SINGLE INBOUND (Admin or Operator)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $poNumber   = trim($input['po_number'] ?? '-');
    if (empty($poNumber)) $poNumber = '-';
    $supplier   = trim($input['supplier'] ?? '-');
    if (empty($supplier)) $supplier = '-';
    $materialId = (int)($input['material_id'] ?? 0);
    $qty        = (int)($input['qty'] ?? 0);
    $notes      = trim($input['notes'] ?? '');
    $startedAt  = trim($input['started_at'] ?? '');

    if ($materialId <= 0 || $qty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Material packaging dan Jumlah Masuk (Qty) wajib diisi!']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtMat = $pdo->prepare("SELECT id, name, current_stock, unit FROM materials WHERE id = ?");
        $stmtMat->execute([$materialId]);
        $mat = $stmtMat->fetch();

        if (!$mat) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Material tidak ditemukan']);
            exit;
        }

        $stockBefore = (int)$mat['current_stock'];
        $stockAfter  = $stockBefore + $qty;

        $prefix = 'INB-' . date('Ym') . '-';
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM inbound_transactions WHERE inbound_no LIKE ?");
        $stmtCount->execute([$prefix . '%']);
        $nextNum = (int)$stmtCount->fetchColumn() + 1;
        $inboundNo = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

        $now = date('Y-m-d H:i:s');
        $startTime = !empty($startedAt) ? date('Y-m-d H:i:s', strtotime($startedAt)) : date('Y-m-d H:i:s', time() - 120);
        $durationSeconds = max(1, strtotime($now) - strtotime($startTime));

        // Insert inbound record
        $stmtIn = $pdo->prepare("
            INSERT INTO inbound_transactions (inbound_no, po_number, supplier, material_id, qty, notes, received_by, started_at, completed_at, duration_seconds)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIn->execute([$inboundNo, $poNumber, $supplier, $materialId, $qty, $notes, Auth::id(), $startTime, $now, $durationSeconds]);

        // Update Material Stock in Master Product
        $stmtUp = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
        $stmtUp->execute([$stockAfter, $materialId]);

        // Insert Stock Mutation
        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id)
            VALUES (?, 'INBOUND', ?, ?, ?, ?, ?, ?)
        ");
        $mutNotes = "Barang Masuk (Diterima oleh " . (Auth::name() ?? 'Admin') . ")";
        if (!empty($notes)) $mutNotes .= " - " . $notes;
        $stmtMut->execute([$materialId, $qty, $stockBefore, $stockAfter, $inboundNo, $mutNotes, Auth::id()]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Penerimaan {$mat['name']} sebanyak {$qty} berhasil disimpan! Stok master bertambah menjadi {$stockAfter}.",
            'inbound_no' => $inboundNo,
            'new_stock' => $stockAfter,
            'duration_seconds' => $durationSeconds,
            'takt_time_seconds' => round($durationSeconds / max(1, $qty), 2)
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal input barang masuk: ' . $e->getMessage()]);
    }
    exit;
}

// 3. BATCH CREATE INBOUND (MULTI-PRODUCT DRAFT SUBMISSION FROM OPERATOR / ADMIN)
if ($action === 'batch_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $poNumber    = trim($input['po_number'] ?? '-');
    if (empty($poNumber)) $poNumber = '-';
    $supplier    = trim($input['supplier'] ?? '-');
    if (empty($supplier)) $supplier = '-';
    $globalNotes = trim($input['notes'] ?? '');
    $startedAt   = trim($input['started_at'] ?? '');
    $items       = $input['items'] ?? [];

    if (empty($items) || !is_array($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Draft penerimaan barang masih kosong. Silakan tambahkan minimal 1 packaging material.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $prefix = 'INB-' . date('Ym') . '-';
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM inbound_transactions WHERE inbound_no LIKE ?");
        $stmtCount->execute([$prefix . '%']);
        $nextNum = (int)$stmtCount->fetchColumn() + 1;

        $now = date('Y-m-d H:i:s');
        $startTime = !empty($startedAt) ? date('Y-m-d H:i:s', strtotime($startedAt)) : date('Y-m-d H:i:s', time() - 300);
        $totalDuration = max(1, strtotime($now) - strtotime($startTime));
        $itemCount = max(1, count($items));
        $itemDuration = max(1, round($totalDuration / $itemCount));

        $stmtIn = $pdo->prepare("
            INSERT INTO inbound_transactions (inbound_no, po_number, supplier, material_id, qty, notes, received_by, started_at, completed_at, duration_seconds)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtUpMat = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");

        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id)
            VALUES (?, 'INBOUND', ?, ?, ?, ?, ?, ?)
        ");

        $processedItems = 0;
        $totalQtyProcessed = 0;
        $createdInboundNos = [];
        $authId = Auth::id();
        $authName = Auth::name() ?? 'Operator';

        foreach ($items as $item) {
            $materialId = (int)($item['material_id'] ?? 0);
            $qty        = (int)($item['qty'] ?? 0);
            $itemNotes  = trim($item['notes'] ?? '');

            if ($materialId <= 0 || $qty <= 0) continue;

            $stmtMat = $pdo->prepare("SELECT id, name, code, current_stock, unit FROM materials WHERE id = ?");
            $stmtMat->execute([$materialId]);
            $mat = $stmtMat->fetch();
            if (!$mat) continue;

            $stockBefore = (int)$mat['current_stock'];
            $stockAfter  = $stockBefore + $qty;

            $inboundNo = $prefix . str_pad($nextNum++, 4, '0', STR_PAD_LEFT);
            $combinedNotes = !empty($globalNotes) ? ($itemNotes ? "{$globalNotes} | {$itemNotes}" : $globalNotes) : $itemNotes;

            $stmtIn->execute([$inboundNo, $poNumber, $supplier, $materialId, $qty, $combinedNotes, $authId, $startTime, $now, $itemDuration]);
            $stmtUpMat->execute([$stockAfter, $materialId]);

            $mutNotes = "Barang Masuk (Diterima oleh {$authName})";
            if (!empty($combinedNotes)) $mutNotes .= " - {$combinedNotes}";
            $stmtMut->execute([$materialId, $qty, $stockBefore, $stockAfter, $inboundNo, $mutNotes, $authId]);

            $processedItems++;
            $totalQtyProcessed += $qty;
            $createdInboundNos[] = $inboundNo;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Berhasil memproses {$processedItems} packaging material (Total: {$totalQtyProcessed} pcs). Stok master berhasil ditambahkan!",
            'total_items' => $processedItems,
            'total_qty' => $totalQtyProcessed,
            'inbound_nos' => $createdInboundNos,
            'duration_seconds' => $totalDuration,
            'takt_time_seconds' => round($totalDuration / max(1, $totalQtyProcessed), 2)
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal input batch barang masuk: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi inbound tidak valid']);
