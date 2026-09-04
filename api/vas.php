<?php
// api/vas.php - Value Added Service (Zone VAS) Management & Stock Transfer API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

// 1. LIST MATERIALS IN ZONE VAS & KPI SUMMARY METRICS
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $showAll = (int)($_GET['show_all'] ?? 0); // 0 = only vas_stock > 0, 1 = all materials

    $query = "
        SELECT m.id, m.code, m.name, m.category, m.unit, m.rack_location, 
               m.current_stock, COALESCE(m.vas_stock, 0) as vas_stock, m.min_stock, m.description, m.updated_at
        FROM materials m
        WHERE 1=1
    ";
    $params = [];

    if ($showAll !== 1) {
        $query .= " AND COALESCE(m.vas_stock, 0) > 0";
    }

    if (!empty($search)) {
        $query .= " AND (m.code LIKE ? OR m.name LIKE ? OR m.description LIKE ? OR m.rack_location LIKE ? OR m.category LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term, $term]);
    }

    if (!empty($category) && $category !== 'all') {
        $query .= " AND m.category = ?";
        $params[] = $category;
    }

    $query .= " ORDER BY COALESCE(m.vas_stock, 0) DESC, m.name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();

    foreach ($materials as &$mat) {
        $mat['id'] = (int)$mat['id'];
        $mat['current_stock'] = (float)$mat['current_stock'];
        $mat['vas_stock'] = (float)$mat['vas_stock'];
        $mat['min_stock'] = (float)$mat['min_stock'];
    }
    unset($mat);

    // Compute KPI Metrics for Zone VAS
    $stmtKpi = $pdo->query("
        SELECT 
            COUNT(CASE WHEN COALESCE(vas_stock, 0) > 0 THEN 1 END) as total_vas_skus,
            COALESCE(SUM(CASE WHEN COALESCE(vas_stock, 0) > 0 THEN vas_stock ELSE 0 END), 0) as total_vas_qty
        FROM materials
    ");
    $kpi = $stmtKpi->fetch() ?: ['total_vas_skus' => 0, 'total_vas_qty' => 0];

    $stmtTxCount = $pdo->query("SELECT COUNT(*) FROM vas_transactions");
    $totalTx = (int)($stmtTxCount->fetchColumn() ?: 0);

    echo json_encode([
        'success' => true,
        'data' => $materials,
        'metrics' => [
            'total_vas_skus' => (int)$kpi['total_vas_skus'],
            'total_vas_qty' => (float)$kpi['total_vas_qty'],
            'total_transactions' => $totalTx
        ]
    ]);
    exit;
}

// 2. AUDIT LOG HISTORY OF VAS TRANSACTIONS
if ($action === 'history') {
    $search = trim($_GET['search'] ?? '');
    $type      = trim($_GET['type'] ?? 'ALL');
    $date      = trim($_GET['date'] ?? '');
    $startDate = trim($_GET['start_date'] ?? $_GET['from_date'] ?? '');
    $endDate   = trim($_GET['end_date'] ?? $_GET['to_date'] ?? '');
    $limit     = min(300, max(10, (int)($_GET['limit'] ?? 150)));

    $query = "
        SELECT v.*, 
               m.code as material_code, m.name as material_name, m.unit as material_unit, m.category as material_category,
               u.name as user_name, u.username as user_username
        FROM vas_transactions v
        JOIN materials m ON v.material_id = m.id
        LEFT JOIN users u ON v.created_by = u.id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (v.vas_no LIKE ? OR v.reference_no LIKE ? OR v.notes LIKE ? OR m.code LIKE ? OR m.name LIKE ?)";
        $term = "%{$search}%";
        $params = [$term, $term, $term, $term, $term];
    }

    if (!empty($type) && $type !== 'ALL') {
        $query .= " AND v.type = ?";
        $params[] = $type;
    }

    if (!empty($startDate)) {
        $query .= " AND DATE(v.created_at) >= ?";
        $params[] = $startDate;
    }

    if (!empty($endDate)) {
        $query .= " AND DATE(v.created_at) <= ?";
        $params[] = $endDate;
    }

    if (!empty($date) && empty($startDate) && empty($endDate)) {
        $query .= " AND v.created_at LIKE ?";
        $params[] = "{$date}%";
    }

    $materialId = (int)($_GET['material_id'] ?? 0);
    if ($materialId > 0) {
        $query .= " AND v.material_id = ?";
        $params[] = $materialId;
    }

    $query .= " ORDER BY v.created_at DESC LIMIT " . $limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['material_id'] = (int)$r['material_id'];
        $r['qty'] = (float)$r['qty'];
    }
    unset($r);

    echo json_encode([
        'success' => true,
        'data' => $rows
    ]);
    exit;
}

// 3. TRANSFER STOCK FROM ZONE VAS BACK TO MAIN INVENTORY
if ($action === 'transfer_to_inventory' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $materialId = (int)($input['material_id'] ?? 0);
    $qty = max(0, parseNumberDecimal($input['qty'] ?? 0));
    $notes = trim($input['notes'] ?? '');

    if ($materialId <= 0 || $qty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Material dan Qty transfer balik wajib diisi lebih dari 0!']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtMat = $pdo->prepare("SELECT id, name, code, current_stock, COALESCE(vas_stock, 0) as vas_stock, unit FROM materials WHERE id = ?");
        $stmtMat->execute([$materialId]);
        $mat = $stmtMat->fetch();

        if (!$mat) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Material tidak ditemukan']);
            exit;
        }

        $currentVasStock = (float)$mat['vas_stock'];
        $currentMainStock = (float)$mat['current_stock'];

        if ($qty > $currentVasStock) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Stok di Zone VAS tidak mencukupi! Sisa stok VAS untuk SKU '{$mat['name']}' saat ini hanya " . number_format($currentVasStock, 2, ',', '.') . " {$mat['unit']}."
            ]);
            exit;
        }

        $newVasStock = $currentVasStock - $qty;
        $newMainStock = $currentMainStock + $qty;

        // Generate VAS Document No: VAS-RET-YYYYMM-XXXX
        $prefix = 'VAS-RET-' . date('Ym') . '-';
        $stmtLastNo = $pdo->prepare("SELECT vas_no FROM vas_transactions WHERE vas_no LIKE ? ORDER BY LENGTH(vas_no) DESC, vas_no DESC LIMIT 1");
        $stmtLastNo->execute([$prefix . '%']);
        $lastNo = $stmtLastNo->fetchColumn();

        $nextNum = 1;
        if ($lastNo) {
            $parts = explode('-', $lastNo);
            $lastSuffix = end($parts);
            if (is_numeric($lastSuffix)) $nextNum = (int)$lastSuffix + 1;
        }

        $stmtCheck = $pdo->prepare("SELECT 1 FROM vas_transactions WHERE vas_no = ? LIMIT 1");
        do {
            $vasNo = $prefix . str_pad($nextNum++, 4, '0', STR_PAD_LEFT);
            $stmtCheck->execute([$vasNo]);
        } while ($stmtCheck->fetchColumn());

        $now = date('Y-m-d H:i:s');
        $userId = Auth::id();
        $userName = Auth::name() ?? 'Admin Gudang';

        // 1. Update Material Stocks
        $stmtUpdate = $pdo->prepare("UPDATE materials SET current_stock = ?, vas_stock = ? WHERE id = ?");
        $stmtUpdate->execute([$newMainStock, $newVasStock, $materialId]);

        // 2. Insert into VAS Transactions (Type: TRANSFER_OUT)
        $vasNotes = "Transfer Masuk Ke Inventory Gudang Utama";
        if (!empty($notes)) $vasNotes .= " - " . $notes;

        $stmtVasTx = $pdo->prepare("
            INSERT INTO vas_transactions (vas_no, material_id, type, qty, reference_no, notes, created_by, created_at)
            VALUES (?, ?, 'TRANSFER_OUT', ?, ?, ?, ?, ?)
        ");
        $stmtVasTx->execute([$vasNo, $materialId, $qty, $vasNo, $vasNotes, $userId, $now]);

        // 3. Insert into Stock Mutations (Type: INBOUND)
        $mutNotes = "Transfer dari Zone VAS Ke Gudang Utama (#{$vasNo})";
        if (!empty($notes)) $mutNotes .= " - " . $notes;

        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
            VALUES (?, 'INBOUND', ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtMut->execute([$materialId, $qty, $currentMainStock, $newMainStock, $vasNo, $mutNotes, $userId, $now]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Berhasil mentransfer {$qty} {$mat['unit']} {$mat['name']} dari Zone VAS kembali ke Inventory Gudang Utama!",
            'vas_no' => $vasNo,
            'new_main_stock' => $newMainStock,
            'new_vas_stock' => $newVasStock
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memproses transfer stok dari VAS: ' . $e->getMessage()]);
    }
    exit;
}

// 4. BATCH STOCK TRANSFER (MULTI-ITEM GUDANG UTAMA <-> ZONE VAS)
if ($action === 'batch_transfer' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = !empty($rawInput) ? json_decode($rawInput, true) : [];
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    $items           = $input['items'] ?? [];
    if (is_string($items)) {
        $items = json_decode($items, true) ?? [];
    }

    $globalNotes     = trim($input['notes'] ?? '');
    $globalDirection = trim($input['direction'] ?? 'IN_VAS'); // 'IN_VAS' (Gudang -> VAS) or 'OUT_VAS' (VAS -> Gudang)

    if (empty($items) || !is_array($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Daftar item transfer stok tidak boleh kosong!']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $now = date('Y-m-d H:i:s');
        $userId = Auth::id();

        $processedItems = 0;
        $totalQtyProcessed = 0;
        $createdVasNos = [];

        $stmtMat = $pdo->prepare("SELECT id, name, code, current_stock, COALESCE(vas_stock, 0) as vas_stock, unit FROM materials WHERE id = ?");
        $stmtUpdateMat = $pdo->prepare("UPDATE materials SET current_stock = ?, vas_stock = ? WHERE id = ?");
        $stmtVasTx = $pdo->prepare("
            INSERT INTO vas_transactions (vas_no, material_id, type, qty, reference_no, notes, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $index => $item) {
            $materialId = (int)($item['material_id'] ?? 0);
            $qty        = max(0, parseNumberDecimal($item['qty'] ?? 0));
            $itemNotes  = trim($item['notes'] ?? '');
            $direction  = trim($item['direction'] ?? $globalDirection); // 'IN_VAS' or 'OUT_VAS'

            if ($materialId <= 0 || $qty <= 0) continue;

            $stmtMat->execute([$materialId]);
            $mat = $stmtMat->fetch();
            if (!$mat) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => "Material #{$materialId} tidak ditemukan!"]);
                exit;
            }

            $currentMainStock = (float)$mat['current_stock'];
            $currentVasStock  = (float)$mat['vas_stock'];
            $combinedNotes    = !empty($globalNotes) ? ($itemNotes ? "{$globalNotes} | {$itemNotes}" : $globalNotes) : $itemNotes;

            if ($direction === 'IN_VAS') {
                // Gudang Utama -> Zone VAS
                if ($qty > $currentMainStock) {
                    $pdo->rollBack();
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => "Stok Gudang Utama tidak mencukupi untuk SKU '{$mat['name']}'! Sisa stok Gudang Utama: " . number_format($currentMainStock, 2, ',', '.') . " {$mat['unit']}."
                    ]);
                    exit;
                }

                $newMainStock = $currentMainStock - $qty;
                $newVasStock  = $currentVasStock + $qty;

                $prefix = 'VAS-IN-' . date('Ym') . '-';
                $vasNo  = $prefix . substr(md5(uniqid() . $index . $materialId), 0, 6);

                $stmtUpdateMat->execute([$newMainStock, $newVasStock, $materialId]);

                $vasNotes = "Transfer Masuk Ke Zone VAS";
                if (!empty($combinedNotes)) $vasNotes .= " - " . $combinedNotes;
                $stmtVasTx->execute([$vasNo, $materialId, 'TRANSFER_IN', $qty, $vasNo, $vasNotes, $userId, $now]);

                $mutNotes = "Pengeluaran Transfer Ke Zone VAS (#{$vasNo})";
                if (!empty($combinedNotes)) $mutNotes .= " - " . $combinedNotes;
                $stmtMut->execute([$materialId, 'TRANSFER_OUT', -$qty, $currentMainStock, $newMainStock, $vasNo, $mutNotes, $userId, $now]);

            } elseif ($direction === 'VAS_OUTBOUND') {
                // Zone VAS -> Outbound / Disposal (Direct use or discard from VAS)
                if ($qty > $currentVasStock) {
                    $pdo->rollBack();
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => "Stok Zone VAS tidak mencukupi untuk SKU '{$mat['name']}'! Sisa stok VAS: " . number_format($currentVasStock, 2, ',', '.') . " {$mat['unit']}."
                    ]);
                    exit;
                }

                $newVasStock  = $currentVasStock - $qty;
                $newMainStock = $currentMainStock; // Inventory stock remains untouched

                $prefix = 'VAS-OUT-' . date('Ym') . '-';
                $vasNo  = $prefix . substr(md5(uniqid() . $index . $materialId), 0, 6);

                $stmtUpdateMat->execute([$newMainStock, $newVasStock, $materialId]);

                $vasNotes = "Pengeluaran / Disposal Stok Langsung Dari Zone VAS";
                if (!empty($combinedNotes)) $vasNotes .= " - " . $combinedNotes;
                $stmtVasTx->execute([$vasNo, $materialId, 'VAS_OUTBOUND', $qty, $vasNo, $vasNotes, $userId, $now]);

                $mutNotes = "Pengeluaran Stok Langsung Dari Zone VAS (#{$vasNo})";
                if (!empty($combinedNotes)) $mutNotes .= " - " . $combinedNotes;
                $stmtMut->execute([$materialId, 'VAS_OUTBOUND', -$qty, $currentMainStock, $currentMainStock, $vasNo, $mutNotes, $userId, $now]);

            } else {
                // Zone VAS -> Stock Inventory
                if ($qty > $currentVasStock) {
                    $pdo->rollBack();
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'message' => "Stok Zone VAS tidak mencukupi untuk SKU '{$mat['name']}'! Sisa stok VAS: " . number_format($currentVasStock, 2, ',', '.') . " {$mat['unit']}."
                    ]);
                    exit;
                }

                $newVasStock  = $currentVasStock - $qty;
                $newMainStock = $currentMainStock + $qty;

                $prefix = 'VAS-RET-' . date('Ym') . '-';
                $vasNo  = $prefix . substr(md5(uniqid() . $index . $materialId), 0, 6);

                $stmtUpdateMat->execute([$newMainStock, $newVasStock, $materialId]);

                $vasNotes = "Transfer Keluar Dari Zone VAS Ke Gudang Utama";
                if (!empty($combinedNotes)) $vasNotes .= " - " . $combinedNotes;
                $stmtVasTx->execute([$vasNo, $materialId, 'TRANSFER_OUT', $qty, $vasNo, $vasNotes, $userId, $now]);

                $mutNotes = "Transfer Masuk Kembali Dari Zone VAS (#{$vasNo})";
                if (!empty($combinedNotes)) $mutNotes .= " - " . $combinedNotes;
                $stmtMut->execute([$materialId, 'TRANSFER_IN', $qty, $currentMainStock, $newMainStock, $vasNo, $mutNotes, $userId, $now]);
            }

            $processedItems++;
            $totalQtyProcessed += $qty;
            $createdVasNos[] = $vasNo;
        }

        if ($processedItems === 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada item transfer yang valid untuk diproses!']);
            exit;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Berhasil memproses transfer stok {$processedItems} item (Total Qty: {$totalQtyProcessed})!",
            'processed_items' => $processedItems,
            'total_qty' => $totalQtyProcessed,
            'vas_nos' => $createdVasNos
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memproses batch transfer stok: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi VAS tidak valid']);
