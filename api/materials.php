<?php
// api/materials.php - Packaging Material Stock Calculation & Card History API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/batch_helper.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

// 0. GET DISTINCT CATEGORIES
if ($action === 'categories') {
    $itemType = trim($_GET['item_type'] ?? 'PACKAGING');
    $query = "SELECT DISTINCT category FROM materials WHERE category IS NOT NULL AND TRIM(category) != ''";
    $params = [];
    if ($itemType === 'GIMMICK') {
        $query .= " AND item_type = 'GIMMICK'";
    } elseif ($itemType === 'PACKAGING') {
        $query .= " AND (item_type = 'PACKAGING' OR item_type IS NULL OR item_type = '')";
    }
    $query .= " ORDER BY category ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['success' => true, 'data' => $categories]);
    exit;
}

// 0.1 FAST BARCODE / SKU LOOKUP (For Scanner Gun & Auto-Fill)
if ($action === 'barcode_lookup') {
    $code = trim($_GET['code'] ?? $_GET['barcode'] ?? '');
    if (empty($code)) {
        echo json_encode(['success' => false, 'message' => 'Kode barcode atau SKU belum diinput']);
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT m.* 
        FROM materials m 
        WHERE UPPER(m.code) = UPPER(?) 
           OR UPPER(COALESCE(m.barcode, '')) = UPPER(?) 
           OR UPPER(COALESCE(m.barcode_bpom, '')) = UPPER(?)
           OR UPPER(COALESCE(m.sap_code, '')) = UPPER(?)
        LIMIT 1
    ");
    $stmt->execute([$code, $code, $code, $code]);
    $mat = $stmt->fetch();
    if ($mat) {
        $mat['current_stock'] = (float)$mat['current_stock'];
        $mat['min_stock'] = (float)$mat['min_stock'];
        $mat['qty_gudang_kecil'] = (float)($mat['qty_gudang_kecil'] ?? 0);
        $mat['qty_gudang_besar'] = (float)($mat['qty_gudang_besar'] ?? 0);
        echo json_encode(['success' => true, 'data' => $mat]);
    } else {
        echo json_encode(['success' => false, 'message' => "Item dengan barcode / SKU '{$code}' tidak ditemukan di database!"]);
    }
    exit;
}

// 0.2 GIMMICK KPI STATS
if ($action === 'gimmick_stats') {
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_sku,
                COALESCE(SUM(current_stock), 0) as total_on_hand,
                COALESCE(SUM(qty_gudang_kecil), 0) as total_gudang_kecil,
                COALESCE(SUM(qty_gudang_besar), 0) as total_gudang_besar,
                COALESCE(SUM(CASE WHEN status_active = 1 OR status_active = '1' OR status_active = 'AKTIF' THEN 1 ELSE 0 END), 0) as active_sku
            FROM materials 
            WHERE item_type = 'GIMMICK'
        ");
        $stats = $stmt ? $stmt->fetch() : null;
        if (!$stats) {
            $stats = [
                'total_sku' => 0,
                'total_on_hand' => 0,
                'total_gudang_kecil' => 0,
                'total_gudang_besar' => 0,
                'active_sku' => 0
            ];
        }
        echo json_encode(['success' => true, 'data' => $stats]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => true,
            'data' => [
                'total_sku' => 0,
                'total_on_hand' => 0,
                'total_gudang_kecil' => 0,
                'total_gudang_besar' => 0,
                'active_sku' => 0
            ]
        ]);
    }
    exit;
}

// 0.3 GET MATERIAL BATCHES (Breakdown Exp Date, Batch No, Lokasi & Qty)
if ($action === 'get_batches' || $action === 'suggest_batches') {
    $materialId = (int)($_GET['material_id'] ?? 0);
    if ($materialId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Material ID tidak valid']);
        exit;
    }

    $stmtMat = $pdo->prepare("SELECT id, code, name, unit, current_stock, rack_location, qty_gudang_kecil, qty_gudang_besar FROM materials WHERE id = ?");
    $stmtMat->execute([$materialId]);
    $material = $stmtMat->fetch();
    if (!$material) {
        echo json_encode(['success' => false, 'message' => 'Material tidak ditemukan']);
        exit;
    }

    $breakdownMap = getBatchMovementBreakdown($pdo, $materialId);
    $batches = $breakdownMap[$materialId] ?? [];

    $today = date('Y-m-d');
    foreach ($batches as &$b) {
        $b['qty'] = (float)$b['qty'];
        $b['ending_stock'] = (float)($b['ending_stock'] ?? $b['qty']);
        $b['initial_stock'] = (float)($b['initial_stock'] ?? $b['qty']);
        $b['total_inbound'] = (float)($b['total_inbound'] ?? 0);
        $b['total_outbound'] = (float)($b['total_outbound'] ?? 0);
        $b['vas_qty'] = (float)($b['vas_qty'] ?? 0);
        $b['exp_date_dmy'] = !empty($b['exp_date']) ? date('d-m-y', strtotime($b['exp_date'])) : '';
        if (empty($b['exp_date'])) {
            $b['exp_status'] = 'none';
            $b['exp_label'] = 'Tanpa Exp Date';
            $b['days_remaining'] = null;
        } else {
            $days = (int)floor((strtotime($b['exp_date']) - strtotime($today)) / 86400);
            $b['days_remaining'] = $days;
            if ($days < 0) {
                $b['exp_status'] = 'expired';
                $b['exp_label'] = 'Expired (' . abs($days) . ' hari lalu)';
            } elseif ($days <= 90) {
                $b['exp_status'] = 'warning';
                $b['exp_label'] = 'Segera Exp (' . $days . ' hari lagi)';
            } else {
                $b['exp_status'] = 'safe';
                $b['exp_label'] = $days . ' hari lagi';
            }
        }
    }

    echo json_encode([
        'success' => true,
        'material' => $material,
        'data' => $batches,
        'batches' => $batches,
        'total_qty' => array_sum(array_column($batches, 'qty'))
    ]);
    exit;
}

// 0.4 SUGGEST LOCATIONS FOR MATERIAL
if ($action === 'suggest_locations') {
    $materialId = (int)($_GET['material_id'] ?? 0);
    $locations = [];
    $locationStocks = [];

    $genericNames = ['Gudang Besar', 'Gudang Utama', 'Gudang Kecil', 'Pusat', 'Gudang Gimmick Pusat', 'Gudang', '-'];

    if ($materialId > 0) {
        $stmtMat = $pdo->prepare("SELECT id, name, code, rack_location, current_stock, item_type FROM materials WHERE id = ?");
        $stmtMat->execute([$materialId]);
        $mat = $stmtMat->fetch();

        $isGimmick = ($mat && strtoupper($mat['item_type'] ?? '') === 'GIMMICK');
        $itemRack = trim($mat['rack_location'] ?? '');

        // 1. Calculate actual stocks per location from material_batches
        $stmtBatchStocks = $pdo->prepare("
            SELECT location, SUM(qty) as total_qty
            FROM material_batches
            WHERE material_id = ? AND qty > 0 AND location IS NOT NULL AND TRIM(location) != ''
            GROUP BY location
        ");
        $stmtBatchStocks->execute([$materialId]);
        while ($bRow = $stmtBatchStocks->fetch(PDO::FETCH_ASSOC)) {
            $locClean = trim($bRow['location']);
            if (!in_array($locClean, $genericNames)) {
                $locations[] = $locClean;
                $locationStocks[$locClean] = (float)$bRow['total_qty'];
            }
        }

        // 2. Add material's own registered rack if it's a valid rack
        if (!empty($itemRack) && !in_array($itemRack, $genericNames)) {
            if (!in_array($itemRack, $locations)) {
                array_unshift($locations, $itemRack);
            }
            if (!isset($locationStocks[$itemRack])) {
                $locationStocks[$itemRack] = (float)($mat['current_stock'] ?? 0);
            }
        }

    }

    // Filter out any generic warehouse building names
    $filteredLocations = array_values(array_unique(array_filter($locations, function($l) use ($genericNames) {
        return !empty($l) && !in_array($l, $genericNames);
    })));

    echo json_encode([
        'success' => true,
        'data' => $filteredLocations,
        'locations' => $filteredLocations,
        'location_stocks' => empty($locationStocks) ? (object)[] : $locationStocks
    ]);
    exit;
}

// 0.5 SUGGEST BATCHES FOR MATERIAL & LOCATION
if ($action === 'suggest_batches') {
    $materialId = (int)($_GET['material_id'] ?? 0);
    $location = trim($_GET['location'] ?? '');

    if ($materialId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Material ID wajib diisi']);
        exit;
    }

    $query = "
        SELECT id, batch_no, exp_date, location, qty, notes
        FROM material_batches
        WHERE material_id = ? AND qty > 0
    ";
    $params = [$materialId];

    if (!empty($location) && $location !== 'ALL' && $location !== 'Gudang Besar') {
        $query .= " AND UPPER(location) = UPPER(?)";
        $params[] = $location;
    }

    // FEFO: First Expired, First Out
    $query .= " ORDER BY (CASE WHEN exp_date IS NULL OR exp_date = '' THEN 1 ELSE 0 END), exp_date ASC, id ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $batches = $stmt->fetchAll();

    // If no batches found for this specific location, fall back to all batches of this material so user can still view and select
    if (empty($batches) && !empty($location) && $location !== 'ALL') {
        $stmtAll = $pdo->prepare("
            SELECT id, batch_no, exp_date, location, qty, notes
            FROM material_batches
            WHERE material_id = ? AND qty > 0
            ORDER BY (CASE WHEN exp_date IS NULL OR exp_date = '' THEN 1 ELSE 0 END), exp_date ASC, id ASC
        ");
        $stmtAll->execute([$materialId]);
        $batches = $stmtAll->fetchAll();
    }

    foreach ($batches as &$b) {
        $b['qty'] = (float)$b['qty'];
        $expDisplay = !empty($b['exp_date']) ? date('d/m/Y', strtotime($b['exp_date'])) : 'No Exp';
        $b['display_label'] = "Batch: {$b['batch_no']} | Exp: {$expDisplay} (Sisa: {$b['qty']} pcs) [{$b['location']}]";
    }

    echo json_encode(['success' => true, 'data' => $batches, 'batches' => $batches]);
    exit;
}

// 0.6 SAVE BATCH MANUAL (Add / Edit Batch)
if ($action === 'save_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $id = (int)($input['id'] ?? 0);
    $materialId = (int)($input['material_id'] ?? 0);
    $batchNo = strtoupper(trim($input['batch_no'] ?? ''));
    $expDate = !empty($input['exp_date']) ? trim($input['exp_date']) : null;
    $location = trim($input['location'] ?? 'Gudang Kecil');
    $qty = max(0, parseNumberDecimal($input['qty'] ?? 0));
    $notes = trim($input['notes'] ?? '');

    if ($materialId <= 0 || empty($batchNo)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Material dan Nomor Batch wajib diisi!']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($id > 0) {
            $stmt = $pdo->prepare("
                UPDATE material_batches 
                SET batch_no = ?, exp_date = ?, location = ?, qty = ?, notes = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND material_id = ?
            ");
            $stmt->execute([$batchNo, $expDate, $location, $qty, $notes, $id, $materialId]);
        } else {
            // Check if batch already exists in this location
            $check = $pdo->prepare("SELECT id, qty FROM material_batches WHERE material_id = ? AND UPPER(batch_no) = UPPER(?) AND UPPER(location) = UPPER(?)");
            $check->execute([$materialId, $batchNo, $location]);
            $existing = $check->fetch();
            if ($existing) {
                $stmt = $pdo->prepare("UPDATE material_batches SET qty = qty + ?, exp_date = COALESCE(?, exp_date), notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$qty, $expDate, $notes, $existing['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO material_batches (material_id, batch_no, exp_date, location, qty, notes) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$materialId, $batchNo, $expDate, $location, $qty, $notes]);
            }
        }

        // Recalculate material overall current_stock & breakdown from batches
        $sumStmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(qty), 0) as total_stock,
                COALESCE(SUM(CASE WHEN UPPER(location) = 'GUDANG KECIL' THEN qty ELSE 0 END), 0) as q_kecil,
                COALESCE(SUM(CASE WHEN UPPER(location) = 'GUDANG BESAR' THEN qty ELSE 0 END), 0) as q_besar
            FROM material_batches 
            WHERE material_id = ?
        ");
        $sumStmt->execute([$materialId]);
        $sums = $sumStmt->fetch();

        $updMat = $pdo->prepare("
            UPDATE materials 
            SET current_stock = ?, qty_gudang_kecil = ?, qty_gudang_besar = ? 
            WHERE id = ?
        ");
        $updMat->execute([$sums['total_stock'], $sums['q_kecil'], $sums['q_besar'], $materialId]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Data Batch berhasil disimpan!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal menyimpan batch.');
    }
    exit;
}

// 0.7 DELETE BATCH (Admin only)
if ($action === 'delete_batch' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int)($input['id'] ?? 0);
    $materialId = (int)($input['material_id'] ?? 0);

    if ($id <= 0 || $materialId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Batch ID tidak valid']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM material_batches WHERE id = ? AND material_id = ?")->execute([$id, $materialId]);

        // Recalculate material current_stock
        $sumStmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(qty), 0) as total_stock,
                COALESCE(SUM(CASE WHEN UPPER(location) = 'GUDANG KECIL' THEN qty ELSE 0 END), 0) as q_kecil,
                COALESCE(SUM(CASE WHEN UPPER(location) = 'GUDANG BESAR' THEN qty ELSE 0 END), 0) as q_besar
            FROM material_batches 
            WHERE material_id = ?
        ");
        $sumStmt->execute([$materialId]);
        $sums = $sumStmt->fetch();

        $updMat = $pdo->prepare("
            UPDATE materials 
            SET current_stock = ?, qty_gudang_kecil = ?, qty_gudang_besar = ? 
            WHERE id = ?
        ");
        $updMat->execute([$sums['total_stock'], $sums['q_kecil'], $sums['q_besar'], $materialId]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Batch berhasil dihapus!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal menghapus batch.');
    }
    exit;
}

// 1. LIST MATERIALS WITH DYNAMIC STOCK CALCULATION FORMULA:
// Ending Stock = (Stok Awal Upload Excel) + (Total Masuk) - (Total Keluar)
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $stockStatus = trim($_GET['status'] ?? ''); // all, low, safe, empty
    $itemType = trim($_GET['item_type'] ?? 'all');

    $query = "
        SELECT m.*,
               COALESCE(sm_agg.total_inbound, 0) as total_inbound,
               COALESCE(sm_agg.total_outbound, 0) as total_outbound,
               COALESCE(sm_init.qty_change, (m.current_stock - COALESCE(sm_agg.total_net_flow, 0))) as initial_upload_stock,
               0 as batch_count,
               NULL as earliest_exp_date,
               '' as batch_numbers,
               '' as batch_locations,
               '[]' as batches_json
        FROM materials m
        LEFT JOIN (
            SELECT material_id,
                   SUM(CASE WHEN qty_change > 0 AND type != 'INITIAL_IMPORT' THEN qty_change ELSE 0 END) as total_inbound,
                   SUM(CASE WHEN qty_change < 0 THEN ABS(qty_change) ELSE 0 END) as total_outbound,
                   SUM(CASE WHEN type != 'INITIAL_IMPORT' THEN qty_change ELSE 0 END) as total_net_flow
            FROM stock_mutations
            GROUP BY material_id
        ) sm_agg ON m.id = sm_agg.material_id
        LEFT JOIN (
            SELECT material_id, MAX(qty_change) as qty_change
            FROM stock_mutations
            WHERE type = 'INITIAL_IMPORT'
            GROUP BY material_id
        ) sm_init ON m.id = sm_init.material_id
        WHERE 1=1
    ";
    $params = [];

    if ($itemType === 'GIMMICK') {
        $query .= " AND m.item_type = 'GIMMICK'";
    } elseif ($itemType === 'PACKAGING') {
        $query .= " AND (m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = '')";
    } // if 'ALL' or 'all', no item_type constraint

    if (!empty($search)) {
        $query .= " AND (m.code LIKE ? OR m.name LIKE ? OR m.description LIKE ? OR m.rack_location LIKE ? OR m.category LIKE ? OR m.sap_code LIKE ? OR m.barcode LIKE ? OR m.barcode_bpom LIKE ? 
                         OR EXISTS (SELECT 1 FROM material_batches mb WHERE mb.material_id = m.id AND (mb.batch_no LIKE ? OR mb.location LIKE ?)))";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term, $term, $term, $term]);
    }

    $shelfLife = trim($_GET['shelf_life'] ?? 'all');
    if (!empty($shelfLife) && $shelfLife !== 'all') {
        $today = date('Y-m-d');
        $plus30 = date('Y-m-d', strtotime('+30 days'));
        $plus90 = date('Y-m-d', strtotime('+90 days'));
        $plus180 = date('Y-m-d', strtotime('+180 days'));

        if ($shelfLife === 'expired') {
            $query .= " AND EXISTS (SELECT 1 FROM material_batches mb WHERE mb.material_id = m.id AND mb.qty > 0 AND mb.exp_date IS NOT NULL AND mb.exp_date != '' AND mb.exp_date < ?)";
            $params[] = $today;
        } elseif ($shelfLife === 'critical') {
            $query .= " AND EXISTS (SELECT 1 FROM material_batches mb WHERE mb.material_id = m.id AND mb.qty > 0 AND mb.exp_date >= ? AND mb.exp_date <= ?)";
            $params[] = $today;
            $params[] = $plus30;
        } elseif ($shelfLife === 'warning') {
            $query .= " AND EXISTS (SELECT 1 FROM material_batches mb WHERE mb.material_id = m.id AND mb.qty > 0 AND mb.exp_date > ? AND mb.exp_date <= ?)";
            $params[] = $plus30;
            $params[] = $plus90;
        } elseif ($shelfLife === 'medium') {
            $query .= " AND EXISTS (SELECT 1 FROM material_batches mb WHERE mb.material_id = m.id AND mb.qty > 0 AND mb.exp_date > ? AND mb.exp_date <= ?)";
            $params[] = $plus90;
            $params[] = $plus180;
        } elseif ($shelfLife === 'safe') {
            $query .= " AND EXISTS (SELECT 1 FROM material_batches mb WHERE mb.material_id = m.id AND mb.qty > 0 AND mb.exp_date > ?)";
            $params[] = $plus180;
        }
    }

    if (!empty($category) && $category !== 'all') {
        $query .= " AND m.category = ?";
        $params[] = $category;
    }

    if ($stockStatus === 'critical' || $stockStatus === 'low') {
        $query .= " AND m.current_stock <= m.min_stock";
    } elseif ($stockStatus === 'empty') {
        $query .= " AND m.current_stock <= 0";
    } elseif ($stockStatus === 'safe') {
        $query .= " AND m.current_stock > m.min_stock";
    }

    if ($itemType === 'GIMMICK') {
        $query .= " ORDER BY m.code ASC";
    } else {
        $query .= " ORDER BY (m.current_stock <= m.min_stock) DESC, m.name ASC";
    }

    // ---------------------------------------------------------------------
    // PAGINASI — bersifat OPT-IN.
    //
    // Tanpa parameter `limit`, endpoint ini tetap mengembalikan seluruh baris
    // seperti sebelumnya, supaya pemanggil lain (cache dropdown, tabel Adjust,
    // export) tidak berubah perilaku. Pemanggil yang mengirim `limit` akan
    // menerima satu halaman beserta blok `pagination`.
    //
    // Batas keras tetap dipasang walau tanpa `limit`: satu katalog yang tumbuh
    // tak terduga tidak boleh sanggup menghabiskan memori server.
    // ---------------------------------------------------------------------
    $limitDiminta = $_GET['limit'] ?? null;
    $adaPaginasi  = ($limitDiminta !== null && $limitDiminta !== '' && $limitDiminta !== 'all');

    $BATAS_KERAS = 5000;
    $perPage = $adaPaginasi ? max(1, min(500, (int)$limitDiminta)) : $BATAS_KERAS;
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $offset  = $adaPaginasi ? ($page - 1) * $perPage : 0;

    // Hitung total baris yang cocok dengan filter, memakai kueri & parameter
    // yang sama persis agar angka halaman tidak pernah menyimpang dari isinya.
    $countQuery = preg_replace('/^\s*SELECT.*?\bFROM materials m\b/s', 'SELECT COUNT(*) FROM materials m', $query, 1);
    $countQuery = preg_replace('/\s+ORDER BY .*$/s', '', $countQuery);

    $totalRows = null;
    try {
        $stmtCount = $pdo->prepare($countQuery);
        $stmtCount->execute($params);
        $totalRows = (int)$stmtCount->fetchColumn();
    } catch (Throwable $e) {
        // Kegagalan menghitung tidak boleh menggagalkan pengambilan data itu sendiri.
        error_log('[PackStock] Gagal menghitung total materials: ' . $e->getMessage());
    }

    $query .= " LIMIT {$perPage} OFFSET {$offset}";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $materials = $stmt->fetchAll();
    } catch (Throwable $e) {
        apiFail($e, 'Gagal mengambil data material dari database.');
        exit;
    }

    // Fetch all active dynamic count materials for fast lookup
    $frozenMap = [];
    try {
        $stmtFrozen = $pdo->query("
            SELECT soi.material_id, so.opname_no
            FROM stock_opname_items soi
            JOIN stock_opnames so ON soi.opname_id = so.id
            WHERE so.counting_type = 'DYNAMIC_COUNT' 
              AND so.status IN ('OPEN', 'COUNTING', 'RECOUNTING')
        ");
        if ($stmtFrozen) {
            while ($fRow = $stmtFrozen->fetch()) {
                $frozenMap[(int)$fRow['material_id']] = $fRow['opname_no'];
            }
        }
    } catch (Throwable $e) {
        error_log('[PackStock] Error reading dynamic count items: ' . $e->getMessage());
    }

    // Pre-calculate granular batch movement breakdown (Stok Awal, Inbound, Outbound, Sisa Stok, Stok Zone VAS)
    $batchBreakdown = getBatchMovementBreakdown($pdo);

    // Attach status label and freeze state to each
    foreach ($materials as &$mat) {
        $mid = (int)$mat['id'];
        $mat['is_frozen'] = isset($frozenMap[$mid]);
        $mat['frozen_session_no'] = $frozenMap[$mid] ?? null;

        $mat['initial_upload_stock'] = (float)$mat['initial_upload_stock'];
        $mat['total_inbound'] = (float)$mat['total_inbound'];
        $mat['total_outbound'] = (float)$mat['total_outbound'];
        $mat['current_stock'] = (float)$mat['current_stock'];
        $mat['vas_stock'] = (float)($mat['vas_stock'] ?? 0);
        $mat['min_stock'] = (float)$mat['min_stock'];

        if (isset($batchBreakdown[$mid]) && !empty($batchBreakdown[$mid])) {
            $mat['batches_json'] = json_encode($batchBreakdown[$mid]);
            $mat['batch_count'] = count($batchBreakdown[$mid]);
            $bNums = [];
            $bLocs = [];
            $minExp = null;
            foreach ($batchBreakdown[$mid] as $bItem) {
                if (!empty($bItem['batch_no'])) $bNums[] = $bItem['batch_no'];
                if (!empty($bItem['location']) && $bItem['location'] !== 'Pusat') $bLocs[] = $bItem['location'];
                if (!empty($bItem['exp_date'])) {
                    if ($minExp === null || $bItem['exp_date'] < $minExp) {
                        $minExp = $bItem['exp_date'];
                    }
                }
            }
            $mat['batch_numbers'] = implode(', ', array_unique($bNums));
            $mat['batch_locations'] = implode(', ', array_unique($bLocs));
            if (!empty($minExp)) {
                $mat['earliest_exp_date'] = $minExp;
            }
        } else {
            $mat['batches_json'] = '[]';
            $mat['batch_count'] = 0;
            $mat['batch_numbers'] = '';
            $mat['batch_locations'] = '';
            $mat['earliest_exp_date'] = null;
        }

        if ($mat['current_stock'] <= 0) {
            $mat['stock_badge'] = 'empty';
            $mat['stock_label'] = 'Habis';
        } elseif ($mat['current_stock'] <= $mat['min_stock']) {
            $mat['stock_badge'] = 'low';
            $mat['stock_label'] = 'Menipis';
        } else {
            $mat['stock_badge'] = 'safe';
            $mat['stock_label'] = 'Aman';
        }
    }

    $respons = ['success' => true, 'data' => $materials];

    if ($adaPaginasi) {
        $totalPages = ($totalRows !== null && $perPage > 0) ? (int)max(1, ceil($totalRows / $perPage)) : 1;
        $respons['pagination'] = [
            'page'        => $page,
            'per_page'    => $perPage,
            'total_rows'  => $totalRows,
            'total_pages' => $totalPages,
            'from'        => $totalRows ? $offset + 1 : 0,
            'to'          => $offset + count($materials),
        ];
    } elseif ($totalRows !== null && $totalRows > $BATAS_KERAS) {
        // Terpotong batas keras. Beri tahu klien secara jujur daripada diam-diam
        // menampilkan sebagian data seolah-olah itu keseluruhannya.
        $respons['truncated']  = true;
        $respons['total_rows'] = $totalRows;
        $respons['message']    = "Menampilkan {$BATAS_KERAS} dari {$totalRows} item. Gunakan pencarian atau filter untuk mempersempit hasil.";
    }

    echo json_encode($respons);
    exit;
}

/**
 * Helper: Automatic Stock Reconciliation (cleans orphaned/duplicate mutations and corrects running balance)
 */
function reconcileMaterialStock(PDO $pdo, int $targetMaterialId = 0) {
    try {
        $matWhere = $targetMaterialId > 0 ? "AND material_id = " . (int)$targetMaterialId : "";

        // 1. Remove duplicate INBOUND mutations for the same (material_id, reference_no)
        $stmtDupIn = $pdo->query("
            SELECT material_id, reference_no, COUNT(*) as cnt, MIN(id) as min_id, MAX(id) as max_id
            FROM stock_mutations
            WHERE type = 'INBOUND' AND reference_no LIKE 'INB-%' {$matWhere}
            GROUP BY material_id, reference_no
            HAVING cnt > 1
        ");
        if ($stmtDupIn) {
            $dupIn = $stmtDupIn->fetchAll();
            foreach ($dupIn as $d) {
                $stmtIn = $pdo->prepare("SELECT id, qty FROM inbound_transactions WHERE inbound_no = ? AND material_id = ?");
                $stmtIn->execute([$d['reference_no'], $d['material_id']]);
                $inbounds = $stmtIn->fetchAll();

                // If only 1 inbound transaction exists, delete redundant duplicate mutation rows
                if (count($inbounds) <= 1) {
                    $stmtDel = $pdo->prepare("DELETE FROM stock_mutations WHERE material_id = ? AND reference_no = ? AND type = 'INBOUND' AND id != ?");
                    $stmtDel->execute([$d['material_id'], $d['reference_no'], $d['max_id']]);
                }
            }
        }

        // 2. Remove duplicate OUTBOUND mutations for the same (material_id, reference_no)
        $stmtDupOut = $pdo->query("
            SELECT material_id, reference_no, COUNT(*) as cnt, MIN(id) as min_id, MAX(id) as max_id
            FROM stock_mutations
            WHERE type = 'OUTBOUND' AND reference_no LIKE 'OUT-%' {$matWhere}
            GROUP BY material_id, reference_no
            HAVING cnt > 1
        ");
        if ($stmtDupOut) {
            $dupOut = $stmtDupOut->fetchAll();
            foreach ($dupOut as $d) {
                $stmtOut = $pdo->prepare("SELECT id, qty FROM outbound_transactions WHERE outbound_no = ? AND material_id = ?");
                $stmtOut->execute([$d['reference_no'], $d['material_id']]);
                $outbounds = $stmtOut->fetchAll();

                if (count($outbounds) <= 1) {
                    $stmtDel = $pdo->prepare("DELETE FROM stock_mutations WHERE material_id = ? AND reference_no = ? AND type = 'OUTBOUND' AND id != ?");
                    $stmtDel->execute([$d['material_id'], $d['reference_no'], $d['max_id']]);
                }
            }
        }

        // 3. Remove older duplicate INITIAL_IMPORT mutations (keep latest uploaded stock)
        $stmtDupInit = $pdo->query("
            SELECT material_id, COUNT(*) as cnt, MAX(id) as max_id
            FROM stock_mutations
            WHERE type = 'INITIAL_IMPORT' {$matWhere}
            GROUP BY material_id
            HAVING cnt > 1
        ");
        if ($stmtDupInit) {
            $dupInit = $stmtDupInit->fetchAll();
            foreach ($dupInit as $d) {
                $stmtDel = $pdo->prepare("DELETE FROM stock_mutations WHERE material_id = ? AND type = 'INITIAL_IMPORT' AND id != ?");
                $stmtDel->execute([$d['material_id'], $d['max_id']]);
            }
        }

        // Set INITIAL_IMPORT created_at to earliest anchor timestamp
        $pdo->exec("UPDATE stock_mutations SET created_at = '2026-08-01 00:00:00' WHERE type = 'INITIAL_IMPORT'");

        // 4. Recalculate running balance and update master current_stock
        $matQuery = $targetMaterialId > 0 ? "SELECT id FROM materials WHERE id = " . (int)$targetMaterialId : "SELECT id FROM materials";
        $materials = $pdo->query($matQuery)->fetchAll(PDO::FETCH_COLUMN);

        foreach ($materials as $matId) {
            $stmtMut = $pdo->prepare("
                SELECT id, type, qty_change 
                FROM stock_mutations 
                WHERE material_id = ? 
                ORDER BY (CASE WHEN type = 'INITIAL_IMPORT' THEN 0 ELSE 1 END), created_at ASC, id ASC
            ");
            $stmtMut->execute([$matId]);
            $mutations = $stmtMut->fetchAll();

            $runningStock = 0;
            foreach ($mutations as $m) {
                $qtyChange = (float)$m['qty_change'];
                if ($m['type'] === 'INITIAL_IMPORT') {
                    $stockBefore = 0;
                    $stockAfter = $qtyChange;
                    $runningStock = $stockAfter;
                } else {
                    $stockBefore = $runningStock;
                    $stockAfter = $runningStock + $qtyChange;
                    $runningStock = $stockAfter;
                }

                $stmtUpMut = $pdo->prepare("UPDATE stock_mutations SET stock_before = ?, stock_after = ? WHERE id = ?");
                $stmtUpMut->execute([$stockBefore, $stockAfter, $m['id']]);
            }

            if (!empty($mutations)) {
                $stmtUpMat = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
                $stmtUpMat->execute([$runningStock, $matId]);
            }
        }
    } catch (Throwable $e) {
        // Quietly catch
    }
}

// 2. RECONCILE MATERIAL STOCK (ADMIN / TEKNISI)
if ($action === 'reconcile') {
    Auth::requireAdmin();
    $id = (int)($_POST['id'] ?? ($_GET['id'] ?? 0));
    reconcileMaterialStock($pdo, $id);
    echo json_encode([
        'success' => true,
        'message' => 'Rekonsiliasi kartu stok dan saldo mutasi berhasil disinkronkan!'
    ]);
    exit;
}

// 3. GET MATERIAL TRANSACTION HISTORY (KARTU STOK / RIWAYAT KELUAR MASUK)
if ($action === 'history') {
    $id = (int)($_GET['id'] ?? 0);
    $code = trim($_GET['code'] ?? '');

    // Auto reconcile before fetching history
    reconcileMaterialStock($pdo, $id);

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
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Kemas tidak ditemukan']);
        exit;
    }

    $material['initial_upload_stock'] = (float)$material['initial_upload_stock'];
    $material['total_inbound'] = (float)$material['total_inbound'];
    $material['total_outbound'] = (float)$material['total_outbound'];
    $material['current_stock'] = (float)$material['current_stock'];

    // Fetch active batches for this material (especially for GIMMICK items)
    $stmtBatches = $pdo->prepare("
        SELECT id, batch_no, exp_date, location, qty, notes, created_at, updated_at
        FROM material_batches
        WHERE material_id = ?
        ORDER BY (CASE WHEN qty > 0 THEN 0 ELSE 1 END), exp_date ASC, batch_no ASC
    ");
    $stmtBatches->execute([$material['id']]);
    $batches = $stmtBatches->fetchAll();

    // Fetch chronological mutations for this item (INITIAL_IMPORT always first, then chronological)
    $stmtMut = $pdo->prepare("
        SELECT sm.*, u.name as user_name, u.role as user_role
        FROM stock_mutations sm
        LEFT JOIN users u ON sm.user_id = u.id
        WHERE sm.material_id = ?
        ORDER BY (CASE WHEN sm.type = 'INITIAL_IMPORT' THEN 0 ELSE 1 END), sm.created_at ASC, sm.id ASC
    ");
    $stmtMut->execute([$material['id']]);
    $history = $stmtMut->fetchAll();

    // If history is empty but material has current stock, synthesize/record initial stock mutation
    if (empty($history) && $material['current_stock'] > 0) {
        $isGimmick = ($material['item_type'] === 'GIMMICK');
        $batchList = !empty($batches) ? implode(', ', array_filter(array_column($batches, 'batch_no'))) : '';
        $refNo = $isGimmick ? 'INIT-GIMMICK' : 'INITIAL-IMPORT';
        $notes = $isGimmick
            ? ('Stok Awal Pendaftaran Gimmick' . (!empty($batchList) ? " [Batch: {$batchList}]" : ''))
            : 'Stok Awal Pendaftaran Kemas';
        $userId = Auth::id() ?? 1;
        $initDate = '2026-08-01 00:00:00';

        $stmtInitMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
            VALUES (?, 'INITIAL_IMPORT', ?, 0, ?, ?, ?, ?, ?)
        ");
        $stmtInitMut->execute([
            $material['id'],
            $material['current_stock'],
            $material['current_stock'],
            $refNo,
            $notes,
            $userId,
            $initDate
        ]);

        // Re-fetch mutations
        $stmtMut->execute([$material['id']]);
        $history = $stmtMut->fetchAll();
    }

    echo json_encode([
        'success' => true,
        'material' => $material,
        'history' => $history,
        'batches' => $batches
    ]);
    exit;
}

// 3. GET SINGLE MATERIAL
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
    $stmt->execute([$id]);
    $mat = $stmt->fetch();

    if ($mat) {
        echo json_encode(['success' => true, 'data' => $mat]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Material tidak ditemukan']);
    }
    exit;
}

// 4. CREATE MATERIAL (Admin only)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $code = strtoupper(trim($input['code'] ?? ''));
    $name = trim($input['name'] ?? '');
    $itemType = strtoupper(trim($input['item_type'] ?? 'PACKAGING'));
    $category = trim($input['category'] ?? '');
    if (empty($category)) {
        if ($itemType === 'GIMMICK') {
            $category = 'Gimmick';
        } else {
            $searchKeywords = strtolower($name . ' ' . $code);
            if (preg_match('/box|dus|karton|corrugated|carton|kardus/i', $searchKeywords)) {
                $category = 'Karton Box';
            } elseif (preg_match('/lakban|tape|seal|isolasi/i', $searchKeywords)) {
                $category = 'Lakban & Seal';
            } elseif (preg_match('/bubble|wrap|stretch/i', $searchKeywords)) {
                $category = 'Plastik/Wrap';
            } elseif (preg_match('/plastik|polybag|pouch|ziplock|klip/i', $searchKeywords)) {
                $category = 'Plastik Kemasan';
            } elseif (preg_match('/label|stiker|sticker|thermal/i', $searchKeywords)) {
                $category = 'Label & Stiker';
            } elseif (preg_match('/card|kartu|insert|ucapan/i', $searchKeywords)) {
                $category = 'Card & Insert';
            } elseif (preg_match('/cushion|honeycomb|kertas/i', $searchKeywords)) {
                $category = 'Packaging Material';
            } else {
                $category = 'Karton Box';
            }
        }
    }
    $unit = trim($input['unit'] ?? 'Pcs');
    $rackLocation = trim($input['rack_location'] ?? 'Gudang Utama');
    $minStock = max(0, parseNumberDecimal($input['min_stock'] ?? 20));
    $initialStock = max(0, parseNumberDecimal($input['initial_stock'] ?? 0));
    $description = trim($input['description'] ?? '');

    // Gimmick specific fields
    $sapCode = trim($input['sap_code'] ?? '');
    $barcode = trim($input['barcode'] ?? '');
    $barcodeBpom = trim($input['barcode_bpom'] ?? '');
    $area = trim($input['area'] ?? 'Pusat');
    $qtyKecil = parseNumberDecimal($input['qty_gudang_kecil'] ?? 0);
    $qtyBesar = parseNumberDecimal($input['qty_gudang_besar'] ?? 0);
    $statusActive = isset($input['status_active']) ? (int)$input['status_active'] : 1;
    $isReserved = !empty($input['is_reserved']) ? 1 : 0;

    if (empty($code) || empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Item No / Kode Material dan Nama Material wajib diisi!']);
        exit;
    }

    // Check duplicate code
    $check = $pdo->prepare("SELECT id FROM materials WHERE code = ?");
    $check->execute([$code]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Item No '{$code}' sudah ada di database!"]);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO materials (
                code, name, item_type, category, unit, rack_location, min_stock, current_stock, 
                description, sap_code, barcode, barcode_bpom, area, qty_gudang_kecil, qty_gudang_besar, status_active, is_reserved
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $code, $name, $itemType, $category, $unit, $rackLocation, $minStock, $initialStock, 
            $description, $sapCode, $barcode, $barcodeBpom, $area, $qtyKecil, $qtyBesar, $statusActive, $isReserved
        ]);
        $matId = (int)$pdo->lastInsertId();

        if ($initialStock > 0) {
            $stmtMut = $pdo->prepare("INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id) VALUES (?, 'INITIAL_IMPORT', ?, 0, ?, 'INITIAL-INPUT', 'Stok Awal Pendaftaran Item', ?)");
            $stmtMut->execute([$matId, $initialStock, $initialStock, Auth::id()]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => ($itemType === 'GIMMICK' ? 'Gimmick' : 'Kemas') . ' berhasil ditambahkan!', 'id' => $matId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal menyimpan.');
    }
    exit;
}

// 5. UPDATE MATERIAL (Admin only)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $id = (int)($input['id'] ?? 0);
    $code = strtoupper(trim($input['code'] ?? ''));
    $name = trim($input['name'] ?? '');
    $itemType = strtoupper(trim($input['item_type'] ?? ''));
    $category = trim($input['category'] ?? 'Karton Box');
    $unit = trim($input['unit'] ?? 'Pcs');
    $rackLocation = trim($input['rack_location'] ?? 'Gudang Utama');
    $minStock = max(0, parseNumberDecimal($input['min_stock'] ?? 20));
    $description = trim($input['description'] ?? '');

    // Gimmick specific fields
    $sapCode = trim($input['sap_code'] ?? '');
    $barcode = trim($input['barcode'] ?? '');
    $barcodeBpom = trim($input['barcode_bpom'] ?? '');
    $area = trim($input['area'] ?? 'Pusat');
    $qtyKecil = parseNumberDecimal($input['qty_gudang_kecil'] ?? 0);
    $qtyBesar = parseNumberDecimal($input['qty_gudang_besar'] ?? 0);
    $statusActive = isset($input['status_active']) ? (int)$input['status_active'] : 1;
    $isReserved = !empty($input['is_reserved']) ? 1 : 0;

    if ($id <= 0 || empty($code) || empty($name)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data material tidak lengkap']);
        exit;
    }

    // Check duplicate code on other records
    $check = $pdo->prepare("SELECT id FROM materials WHERE code = ? AND id != ?");
    $check->execute([$code, $id]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Item No '{$code}' sudah digunakan oleh material lain!"]);
        exit;
    }

    try {
        if (!empty($itemType)) {
            $stmt = $pdo->prepare("
                UPDATE materials SET 
                    code = ?, name = ?, item_type = ?, category = ?, unit = ?, rack_location = ?, min_stock = ?, description = ?,
                    sap_code = ?, barcode = ?, barcode_bpom = ?, area = ?, qty_gudang_kecil = ?, qty_gudang_besar = ?, status_active = ?, is_reserved = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $code, $name, $itemType, $category, $unit, $rackLocation, $minStock, $description,
                $sapCode, $barcode, $barcodeBpom, $area, $qtyKecil, $qtyBesar, $statusActive, $isReserved, $id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE materials SET 
                    code = ?, name = ?, category = ?, unit = ?, rack_location = ?, min_stock = ?, description = ?,
                    sap_code = ?, barcode = ?, barcode_bpom = ?, area = ?, qty_gudang_kecil = ?, qty_gudang_besar = ?, status_active = ?, is_reserved = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $code, $name, $category, $unit, $rackLocation, $minStock, $description,
                $sapCode, $barcode, $barcodeBpom, $area, $qtyKecil, $qtyBesar, $statusActive, $isReserved, $id
            ]);
        }
        echo json_encode(['success' => true, 'message' => 'Data berhasil diperbarui!']);
    } catch (Exception $e) {
        http_response_code(500);
        apiFail($e, 'Gagal memperbarui data.');
    }
    exit;
}

// 6. DELETE MATERIAL (Admin only)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $id = (int)($input['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID material tidak valid']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM stock_mutations WHERE material_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM inbound_transactions WHERE material_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM outbound_transactions WHERE material_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM tasks WHERE material_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM material_batches WHERE material_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM materials WHERE id = ?")->execute([$id]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Item berhasil dihapus!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal menghapus.');
    }
    exit;
}

// 7. GET CATEGORIES LIST
if ($action === 'categories') {
    $stmt = $pdo->query("SELECT DISTINCT category FROM materials WHERE category IS NOT NULL AND category != '' ORDER BY category ASC");
    $cats = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['success' => true, 'data' => $cats]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali']);

