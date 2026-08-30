<?php
// api/opnames.php - Separated Dynamic Counting & Stock Opname Multi-Stage Management API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

// Helper to format stage label
function getStageLabel(int $stageNumber): string {
    if ($stageNumber === 1) return '1st Count';
    if ($stageNumber === 2) return '2nd Count';
    if ($stageNumber === 3) return '3rd Count';
    if ($stageNumber === 4) return '4th Count';
    return "{$stageNumber}th Count";
}

// Helper to generate structured Unique Session ID based on Date & Week
function generateOpnameNumber(PDO $pdo, string $prefix): string {
    $todayStr = date('Ymd');
    $weekNum = date('W');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stock_opnames WHERE opname_no LIKE ?");
    $stmt->execute(["{$prefix}-{$todayStr}-%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    $seq = str_pad((string)$count, 2, '0', STR_PAD_LEFT);
    return "{$prefix}-{$todayStr}-W{$weekNum}-{$seq}";
}

// Helper to get or create today's active Stock Opname session
function getOrCreateActiveStockOpname(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT * FROM stock_opnames 
        WHERE counting_type = 'STOCK_OPNAME' AND status IN ('OPEN', 'COUNTING', 'RECOUNTING')
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute();
    $active = $stmt->fetch();

    if ($active) {
        return $active;
    }

    // Auto-create today's Stock Opname session if none is open
    $opnameNo = generateOpnameNumber($pdo, 'OPN');
    $title = 'Stock Opname Fisik ' . date('d M Y') . ' (Week ' . date('W') . ')';

    $stmtInsert = $pdo->prepare("
        INSERT INTO stock_opnames (opname_no, title, counting_type, max_stage, notes, status, created_by, created_at)
        VALUES (?, ?, 'STOCK_OPNAME', 1, 'Sesi Stock Opname Blank Count Otomatis', 'COUNTING', ?, CURRENT_TIMESTAMP)
    ");
    $stmtInsert->execute([$opnameNo, $title, $userId]);
    $newId = (int)$pdo->lastInsertId();

    $stmtNew = $pdo->prepare("SELECT * FROM stock_opnames WHERE id = ?");
    $stmtNew->execute([$newId]);
    return $stmtNew->fetch();
}

// =========================================================================
// 1. LIST STOCK OPNAME / DYNAMIC COUNT SESSIONS
// =========================================================================
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $type   = trim($_GET['type'] ?? '');

    $query = "
        SELECT so.*,
               u.name as creator_name,
               COUNT(soi.id) as total_items,
               SUM(CASE WHEN soi.final_qty IS NOT NULL THEN 1 ELSE 0 END) as counted_items,
               SUM(CASE WHEN soi.final_qty IS NOT NULL AND soi.difference = 0 THEN 1 ELSE 0 END) as match_items,
               SUM(CASE WHEN soi.final_qty IS NOT NULL AND soi.difference != 0 THEN 1 ELSE 0 END) as discrepancy_items
        FROM stock_opnames so
        LEFT JOIN users u ON so.created_by = u.id
        LEFT JOIN stock_opname_items soi ON so.id = soi.opname_id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($status) && $status !== 'ALL') {
        $query .= " AND so.status = ?";
        $params[] = $status;
    }

    if (!empty($type) && $type !== 'ALL') {
        $query .= " AND so.counting_type = ?";
        $params[] = $type;
    }

    if (!empty($search)) {
        $query .= " AND (so.opname_no LIKE ? OR so.title LIKE ? OR so.notes LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term]);
    }

    $query .= " GROUP BY so.id ORDER BY so.created_at DESC, so.id DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// =========================================================================
// 1.B. GET COMPREHENSIVE PRODUCT MATRIX (DYNAMIC COUNT OR STOCK OPNAME)
// =========================================================================
if ($action === 'matrix') {
    $type = trim($_GET['type'] ?? 'STOCK_OPNAME');
    $opnameId = (int)($_GET['opname_id'] ?? 0);
    $dateFilter = trim($_GET['date'] ?? '');
    $search = trim($_GET['search'] ?? '');
    $noteFilter = trim($_GET['note_filter'] ?? 'ALL');

    if (!in_array($type, ['STOCK_OPNAME', 'DYNAMIC_COUNT'])) {
        $type = 'STOCK_OPNAME';
    }

    // Fetch all available sessions for this type for dropdown selector
    $stmtSessions = $pdo->prepare("
        SELECT so.id, so.opname_no, so.title, so.counting_type, so.status, so.max_stage, so.created_at,
               COUNT(soi.id) as total_items
        FROM stock_opnames so
        LEFT JOIN stock_opname_items soi ON so.id = soi.opname_id
        WHERE so.counting_type = ?
        GROUP BY so.id
        HAVING COUNT(soi.id) > 0
        ORDER BY so.id DESC
    ");
    $stmtSessions->execute([$type]);
    $sessions = $stmtSessions->fetchAll();

    $currentOpname = null;
    if ($opnameId > 0) {
        foreach ($sessions as $s) {
            if ((int)$s['id'] === $opnameId) {
                $currentOpname = $s;
                break;
            }
        }
    }

    // If no opname selected or found, default to active / latest session of that type
    if (!$currentOpname && !empty($sessions)) {
        foreach ($sessions as $s) {
            if (in_array($s['status'], ['OPEN', 'COUNTING', 'RECOUNTING'])) {
                $currentOpname = $s;
                break;
            }
        }
        if (!$currentOpname) {
            $currentOpname = $sessions[0];
        }
    }

    $selectedOpnameId = $currentOpname ? (int)$currentOpname['id'] : 0;

    // If still no session exists for STOCK_OPNAME, auto-create one
    if ($selectedOpnameId === 0 && $type === 'STOCK_OPNAME') {
        $user = Auth::user();
        $currentOpname = getOrCreateActiveStockOpname($pdo, (int)($user['id'] ?? 1));
        $selectedOpnameId = (int)$currentOpname['id'];
        $stmtSessions->execute([$type]);
        $sessions = $stmtSessions->fetchAll();
    }

    $items = [];
    $maxStage = $currentOpname ? (int)$currentOpname['max_stage'] : 1;
    if ($maxStage < 1) $maxStage = 1;

    if ($selectedOpnameId > 0) {
        // Fetch items for this session
        $queryItems = "
            SELECT soi.*,
                   m.code as material_code,
                   m.name as material_name,
                   m.category as material_category,
                   m.unit as material_unit,
                   m.rack_location as material_rack,
                   m.current_stock as current_live_stock
            FROM stock_opname_items soi
            JOIN materials m ON soi.material_id = m.id
            WHERE soi.opname_id = ?
        ";
        $params = [$selectedOpnameId];

        if (!empty($search)) {
            $queryItems .= " AND (m.code LIKE ? OR m.name LIKE ? OR m.rack_location LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        $queryItems .= " ORDER BY (soi.difference != 0) DESC, soi.id DESC";
        $stmtItems = $pdo->prepare($queryItems);
        $stmtItems->execute($params);
        $rawItems = $stmtItems->fetchAll();

        // Fetch stages
        $stmtStages = $pdo->prepare("
            SELECT st.*, u.name as operator_name, u.username as operator_username
            FROM stock_opname_item_stages st
            LEFT JOIN users u ON st.assigned_to = u.id
            WHERE st.opname_id = ?
            ORDER BY st.stage_number ASC, st.id ASC
        ");
        $stmtStages->execute([$selectedOpnameId]);
        $rawStages = $stmtStages->fetchAll();

        $stagesByItem = [];
        foreach ($rawStages as $st) {
            $itemId = (int)$st['item_id'];
            $stageNum = (int)$st['stage_number'];
            if ($stageNum > $maxStage) $maxStage = $stageNum;

            if (!isset($stagesByItem[$itemId])) $stagesByItem[$itemId] = [];
            $stagesByItem[$itemId][$stageNum] = [
                'id' => (int)$st['id'],
                'stage_number' => $stageNum,
                'operator_name' => $st['operator_name'] ?: ($st['operator_username'] ?: 'Operator'),
                'count_qty' => $st['count_qty'] !== null ? (float)$st['count_qty'] : null,
                'scanned_rack' => $st['scanned_rack'] ?? null,
                'counted_at' => $st['counted_at'],
                'status' => $st['status'],
                'notes' => $st['notes']
            ];
        }

        foreach ($rawItems as $item) {
            $itemId = (int)$item['id'];
            $itemStages = $stagesByItem[$itemId] ?? [];
            $item['stages'] = $itemStages;

            // Cascade rule: resolve latest stage with non-null count_qty
            $finalQty = null;
            $activeSource = null;
            $lastCountedAt = null;
            $scannedRack = null;

            for ($s = $maxStage; $s >= 1; $s--) {
                if (isset($itemStages[$s]) && $itemStages[$s]['count_qty'] !== null) {
                    $finalQty = $itemStages[$s]['count_qty'];
                    $activeSource = $s;
                    $lastCountedAt = $itemStages[$s]['counted_at'];
                    $scannedRack = $itemStages[$s]['scanned_rack'];
                    break;
                }
            }

            $item['final_physical_qty'] = $finalQty !== null ? $finalQty : ($item['final_qty'] !== null ? (float)$item['final_qty'] : null);
            $sysStock = (float)$item['system_stock'];
            $diff = $item['final_physical_qty'] !== null ? ($item['final_physical_qty'] - $sysStock) : null;
            $item['final_difference'] = $diff;
            $item['active_source_stage'] = $activeSource;
            $item['counted_at'] = $lastCountedAt ?: ($item['updated_at'] ?: $item['created_at']);
            $item['scanned_rack'] = $scannedRack ?: $item['material_rack'];

            // Note: PLUS, MINUS, BALANCE, PENDING
            if ($diff === null) {
                $item['diff_note'] = 'PENDING';
                $item['diff_note_label'] = 'Pending';
            } elseif ($diff > 0) {
                $item['diff_note'] = 'PLUS';
                $item['diff_note_label'] = 'Plus (+' . $diff . ')';
            } elseif ($diff < 0) {
                $item['diff_note'] = 'MINUS';
                $item['diff_note_label'] = 'Minus (' . $diff . ')';
            } else {
                $item['diff_note'] = 'BALANCE';
                $item['diff_note_label'] = 'Balance (0)';
            }

            // Date filter if requested
            if (!empty($dateFilter)) {
                $itemDate = date('Y-m-d', strtotime($item['counted_at']));
                if ($itemDate !== $dateFilter) {
                    continue;
                }
            }

            // Note filter if requested
            if ($noteFilter !== 'ALL' && $noteFilter !== '') {
                if ($noteFilter === 'DIFF_ONLY') {
                    if ($item['diff_note'] !== 'PLUS' && $item['diff_note'] !== 'MINUS') {
                        continue;
                    }
                } elseif ($item['diff_note'] !== $noteFilter) {
                    continue;
                }
            }

            $items[] = $item;
        }
    }

    // Compute stats
    $totalItems = count($items);
    $plusItems = 0;
    $minusItems = 0;
    $balanceItems = 0;
    $pendingItems = 0;

    foreach ($items as $it) {
        if ($it['diff_note'] === 'PLUS') $plusItems++;
        elseif ($it['diff_note'] === 'MINUS') $minusItems++;
        elseif ($it['diff_note'] === 'BALANCE') $balanceItems++;
        else $pendingItems++;
    }

    echo json_encode([
        'success' => true,
        'type' => $type,
        'sessions' => $sessions,
        'selected_opname_id' => $selectedOpnameId,
        'opname' => $currentOpname,
        'max_stage' => $maxStage,
        'items' => $items,
        'stats' => [
            'total_items' => $totalItems,
            'plus_items' => $plusItems,
            'minus_items' => $minusItems,
            'balance_items' => $balanceItems,
            'pending_items' => $pendingItems
        ]
    ]);
    exit;
}

// =========================================================================
// 1.C. GET DETAIL COUNT FOR A SPECIFIC ITEM IN A SESSION
// =========================================================================
if ($action === 'item_detail') {
    $opnameId = (int)($_GET['opname_id'] ?? 0);
    $itemId   = (int)($_GET['item_id'] ?? 0);

    if ($opnameId <= 0 || $itemId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Parameter opname_id dan item_id wajib diisi']);
        exit;
    }

    // Fetch session info
    $stmtSo = $pdo->prepare("
        SELECT so.*, u.name as creator_name
        FROM stock_opnames so
        LEFT JOIN users u ON so.created_by = u.id
        WHERE so.id = ?
    ");
    $stmtSo->execute([$opnameId]);
    $opname = $stmtSo->fetch();

    if (!$opname) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan']);
        exit;
    }

    // Fetch item info
    $stmtItem = $pdo->prepare("
        SELECT soi.*,
               m.code as material_code,
               m.name as material_name,
               m.category as material_category,
               m.unit as material_unit,
               m.rack_location as material_rack,
               m.current_stock as current_live_stock
        FROM stock_opname_items soi
        JOIN materials m ON soi.material_id = m.id
        WHERE soi.id = ? AND soi.opname_id = ?
    ");
    $stmtItem->execute([$itemId, $opnameId]);
    $item = $stmtItem->fetch();

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan dalam sesi ini']);
        exit;
    }

    // Fetch all stages for this item
    $stmtStages = $pdo->prepare("
        SELECT st.*, 
               u.name as operator_name, 
               u.username as operator_username,
               u.shift as operator_shift
        FROM stock_opname_item_stages st
        LEFT JOIN users u ON st.assigned_to = u.id
        WHERE st.opname_id = ? AND st.item_id = ?
        ORDER BY st.stage_number ASC, st.id ASC
    ");
    $stmtStages->execute([$opnameId, $itemId]);
    $stages = $stmtStages->fetchAll();

    // Determine active source stage (cascade: latest non-null count_qty)
    $maxStage = (int)$opname['max_stage'];
    if ($maxStage < 1) $maxStage = 1;
    foreach ($stages as $st) {
        if ((int)$st['stage_number'] > $maxStage) $maxStage = (int)$st['stage_number'];
    }

    $activeSource = null;
    $finalQty = null;
    for ($s = $maxStage; $s >= 1; $s--) {
        foreach ($stages as $st) {
            if ((int)$st['stage_number'] === $s && $st['count_qty'] !== null) {
                $finalQty = (float)$st['count_qty'];
                $activeSource = $s;
                break 2;
            }
        }
    }

    $sysStock = (float)$item['system_stock'];
    $diff = $finalQty !== null ? ($finalQty - $sysStock) : null;

    echo json_encode([
        'success' => true,
        'opname' => [
            'id' => (int)$opname['id'],
            'opname_no' => $opname['opname_no'],
            'title' => $opname['title'],
            'status' => $opname['status'],
            'counting_type' => $opname['counting_type']
        ],
        'item' => [
            'id' => (int)$item['id'],
            'material_code' => $item['material_code'],
            'material_name' => $item['material_name'],
            'material_unit' => $item['material_unit'],
            'material_rack' => $item['material_rack'],
            'system_stock' => $sysStock,
            'final_qty' => $finalQty,
            'difference' => $diff,
            'active_source_stage' => $activeSource
        ],
        'stages' => array_map(function($st) use ($activeSource) {
            $stageNum = (int)$st['stage_number'];
            return [
                'id' => (int)$st['id'],
                'stage_number' => $stageNum,
                'count_qty' => $st['count_qty'] !== null ? (float)$st['count_qty'] : null,
                'scanned_rack' => $st['scanned_rack'] ?? null,
                'operator_name' => $st['operator_name'] ?: ($st['operator_username'] ?: 'Operator'),
                'operator_shift' => $st['operator_shift'] ?? null,
                'counted_at' => $st['counted_at'],
                'status' => $st['status'],
                'notes' => $st['notes'],
                'is_final_source' => ($stageNum === $activeSource)
            ];
        }, $stages)
    ]);
    exit;
}

// =========================================================================
// 1.D. LIST ALL COUNTING DETAILS (LOG HITUNG FISIK SEMUA PUTARAN DENGAN FILTER SESI/DOKUMEN)
// =========================================================================
if ($action === 'list_counting_details') {
    $countingType = trim($_GET['type'] ?? 'STOCK_OPNAME');
    if (!in_array($countingType, ['STOCK_OPNAME', 'DYNAMIC_COUNT', 'ALL'])) {
        $countingType = 'STOCK_OPNAME';
    }

    $opnameId    = (int)($_GET['opname_id'] ?? 0);
    $stageNumber = (int)($_GET['stage_number'] ?? 0);
    $date        = trim($_GET['date'] ?? '');
    $search      = trim($_GET['search'] ?? '');
    $status      = trim($_GET['status'] ?? '');

    // Build list of sessions for dropdown
    if ($countingType === 'ALL') {
        $stmtSessions = $pdo->query("
            SELECT id, opname_no, title, counting_type, status, max_stage, created_at
            FROM stock_opnames
            ORDER BY created_at DESC, id DESC
        ");
    } else {
        $stmtSessions = $pdo->prepare("
            SELECT id, opname_no, title, counting_type, status, max_stage, created_at
            FROM stock_opnames
            WHERE counting_type = ?
            ORDER BY created_at DESC, id DESC
        ");
        $stmtSessions->execute([$countingType]);
    }
    $sessions = $stmtSessions->fetchAll();

    $where = [];
    $params = [];

    if ($countingType !== 'ALL') {
        $where[] = "so.counting_type = ?";
        $params[] = $countingType;
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
               so.status as opname_status,
               soi.system_stock,
               soi.final_qty as item_final_qty,
               soi.difference as item_difference,
               m.id as material_id,
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
    $rows = $stmt->fetchAll();

    // Calculate Summary Stats
    $totalRecords = count($rows);
    $totalQty = 0;
    $uniqueMaterials = [];
    $uniqueSessions = [];

    foreach ($rows as &$r) {
        $r['id'] = (int)$r['id'];
        $r['stage_number'] = (int)$r['stage_number'];
        $r['stage_label'] = getStageLabel($r['stage_number']);
        $r['count_qty'] = $r['count_qty'] !== null ? (float)$r['count_qty'] : null;
        if ($r['count_qty'] !== null) {
            $totalQty += $r['count_qty'];
        }
        $uniqueMaterials[$r['material_id']] = true;
        $uniqueSessions[$r['opname_id']] = true;
    }
    unset($r);

    echo json_encode([
        'success' => true,
        'sessions' => $sessions,
        'data' => $rows,
        'stats' => [
            'total_records' => $totalRecords,
            'total_qty' => $totalQty,
            'total_unique_sku' => count($uniqueMaterials),
            'total_sessions' => count($uniqueSessions)
        ]
    ]);
    exit;
}

// =========================================================================
// 1.C COUNTING PROGRESS DASHBOARD SUMMARY (DYNAMIC COUNT & STOCK OPNAME)
// =========================================================================
if ($action === 'counting_progress_summary') {
    $type = trim($_GET['type'] ?? 'ALL'); // ALL, DYNAMIC_COUNT, STOCK_OPNAME
    $status = trim($_GET['status'] ?? 'ALL'); // ALL, ACTIVE, COMPLETED
    $date = trim($_GET['date'] ?? '');

    $where = ["1=1"];
    $params = [];

    if ($type !== 'ALL') {
        $where[] = "so.counting_type = ?";
        $params[] = $type;
    }

    if ($status === 'ACTIVE') {
        $where[] = "so.status IN ('OPEN', 'COUNTING', 'RECOUNTING')";
    } elseif ($status === 'COMPLETED') {
        $where[] = "so.status = 'COMPLETED'";
    }

    if (!empty($date)) {
        $where[] = "DATE(so.created_at) = ?";
        $params[] = $date;
    }

    $whereSql = implode(" AND ", $where);

    // Fetch sessions with stage breakdown
    $stmt = $pdo->prepare("
        SELECT so.*,
               u.name as creator_name,
               (SELECT COUNT(*) FROM stock_opname_items soi WHERE soi.opname_id = so.id) as total_items,
               (SELECT COUNT(DISTINCT st.item_id) FROM stock_opname_item_stages st WHERE st.opname_id = so.id AND st.status = 'COUNTED' AND st.stage_number = 1) as stage_1_counted,
               (SELECT COUNT(DISTINCT st.item_id) FROM stock_opname_item_stages st WHERE st.opname_id = so.id AND st.status = 'COUNTED' AND st.stage_number = 2) as stage_2_counted,
               (SELECT COUNT(DISTINCT st.item_id) FROM stock_opname_item_stages st WHERE st.opname_id = so.id AND st.status = 'COUNTED' AND st.stage_number >= 3) as stage_3_counted,
               (SELECT COUNT(*) FROM stock_opname_items soi WHERE soi.opname_id = so.id AND (soi.difference IS NOT NULL AND soi.difference != 0)) as variance_items_count,
               (SELECT SUM(st.count_qty) FROM stock_opname_item_stages st WHERE st.opname_id = so.id AND st.count_qty IS NOT NULL) as total_counted_qty
        FROM stock_opnames so
        LEFT JOIN users u ON so.created_by = u.id
        WHERE {$whereSql}
        ORDER BY CASE WHEN so.status IN ('COUNTING', 'RECOUNTING', 'OPEN') THEN 0 ELSE 1 END, so.id DESC
    ");
    $stmt->execute($params);
    $sessions = $stmt->fetchAll();

    $overallTotalItems = 0;
    $overallCountedItems = 0;
    $overallQty = 0;
    $totalActiveSessions = 0;
    $totalCompletedSessions = 0;
    $activeDynamicCount = 0;
    $activeStockOpname = 0;
    $totalVarianceCount = 0;

    foreach ($sessions as &$s) {
        $s['id'] = (int)$s['id'];
        $s['total_items'] = (int)$s['total_items'];
        $s['max_stage'] = (int)$s['max_stage'];
        $s['stage_1_counted'] = (int)$s['stage_1_counted'];
        $s['stage_2_counted'] = (int)$s['stage_2_counted'];
        $s['stage_3_counted'] = (int)$s['stage_3_counted'];
        $s['variance_items_count'] = (int)$s['variance_items_count'];
        $s['total_counted_qty'] = (float)($s['total_counted_qty'] ?? 0);

        // Calculate progress percentage
        $progressPct = 0;
        if ($s['total_items'] > 0) {
            $progressPct = round(($s['stage_1_counted'] / $s['total_items']) * 100, 1);
            if ($progressPct > 100) $progressPct = 100;
        }
        $s['progress_pct'] = $progressPct;

        $overallTotalItems += $s['total_items'];
        $overallCountedItems += $s['stage_1_counted'];
        $overallQty += $s['total_counted_qty'];
        $totalVarianceCount += $s['variance_items_count'];

        $isActive = in_array($s['status'], ['OPEN', 'COUNTING', 'RECOUNTING']);
        if ($isActive) {
            $totalActiveSessions++;
            if ($s['counting_type'] === 'DYNAMIC_COUNT') $activeDynamicCount++;
            else $activeStockOpname++;
        } elseif ($s['status'] === 'COMPLETED') {
            $totalCompletedSessions++;
        }
    }
    unset($s);

    $overallPct = $overallTotalItems > 0 ? round(($overallCountedItems / $overallTotalItems) * 100, 1) : 0;

    // Leaderboard of operators
    $stmtLeaderboard = $pdo->prepare("
        SELECT u.id as operator_id,
               u.name as operator_name,
               u.username as operator_username,
               u.shift as operator_shift,
               COUNT(DISTINCT st.item_id) as total_items_counted,
               SUM(st.count_qty) as total_qty_counted,
               COUNT(st.id) as total_scan_actions,
               MAX(COALESCE(st.counted_at, st.created_at)) as last_active
        FROM stock_opname_item_stages st
        JOIN users u ON st.assigned_to = u.id
        WHERE st.status = 'COUNTED'
        GROUP BY u.id, u.name, u.username, u.shift
        ORDER BY total_items_counted DESC, total_qty_counted DESC
        LIMIT 10
    ");
    $stmtLeaderboard->execute();
    $leaderboard = $stmtLeaderboard->fetchAll();

    echo json_encode([
        'success' => true,
        'sessions' => $sessions,
        'kpi' => [
            'total_sessions' => count($sessions),
            'active_sessions' => $totalActiveSessions,
            'completed_sessions' => $totalCompletedSessions,
            'active_dynamic_count' => $activeDynamicCount,
            'active_stock_opname' => $activeStockOpname,
            'overall_total_items' => $overallTotalItems,
            'overall_counted_items' => $overallCountedItems,
            'overall_progress_pct' => $overallPct,
            'overall_total_qty' => $overallQty,
            'total_variance_count' => $totalVarianceCount
        ],
        'leaderboard' => $leaderboard
    ]);
    exit;
}

// =========================================================================
// 2. GET SINGLE OPNAME DETAIL WITH DYNAMIC STAGE MATRIX
// =========================================================================
if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);

    $stmtSo = $pdo->prepare("
        SELECT so.*, u.name as creator_name
        FROM stock_opnames so
        LEFT JOIN users u ON so.created_by = u.id
        WHERE so.id = ?
    ");
    $stmtSo->execute([$id]);
    $opname = $stmtSo->fetch();

    if (!$opname) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan']);
        exit;
    }

    // Fetch items
    $stmtItems = $pdo->prepare("
        SELECT soi.*,
               m.code as material_code,
               m.name as material_name,
               m.category as material_category,
               m.unit as material_unit,
               m.rack_location as material_rack,
               m.current_stock as current_live_stock
        FROM stock_opname_items soi
        JOIN materials m ON soi.material_id = m.id
        WHERE soi.opname_id = ?
        ORDER BY (soi.difference != 0) DESC, m.name ASC
    ");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll();

    // Fetch all stages for these items
    $stmtStages = $pdo->prepare("
        SELECT st.*, u.name as operator_name, u.username as operator_username
        FROM stock_opname_item_stages st
        LEFT JOIN users u ON st.assigned_to = u.id
        WHERE st.opname_id = ?
        ORDER BY st.stage_number ASC, st.id ASC
    ");
    $stmtStages->execute([$id]);
    $allStages = $stmtStages->fetchAll();

    // Group stages by item_id
    $stagesByItem = [];
    $maxStageInSession = (int)$opname['max_stage'];
    if ($maxStageInSession < 1) $maxStageInSession = 1;

    foreach ($allStages as $st) {
        $itemId = (int)$st['item_id'];
        $stageNum = (int)$st['stage_number'];
        if ($stageNum > $maxStageInSession) {
            $maxStageInSession = $stageNum;
        }
        if (!isset($stagesByItem[$itemId])) {
            $stagesByItem[$itemId] = [];
        }
        $stagesByItem[$itemId][$stageNum] = [
            'id' => (int)$st['id'],
            'stage_number' => $stageNum,
            'assigned_to' => (int)$st['assigned_to'],
            'operator_name' => $st['operator_name'] ?: 'Operator',
            'operator_username' => $st['operator_username'] ?: '',
            'count_qty' => $st['count_qty'] !== null ? (float)$st['count_qty'] : null,
            'scanned_rack' => $st['scanned_rack'] ?? null,
            'counted_at' => $st['counted_at'],
            'status' => $st['status'],
            'notes' => $st['notes']
        ];
    }

    // Attach stages array and compute cascade final qty for each item
    foreach ($items as &$item) {
        $itemId = (int)$item['id'];
        $itemStages = $stagesByItem[$itemId] ?? [];
        $item['stages'] = $itemStages;

        // Cascade rule: latest stage with non-null count_qty
        $finalQty = null;
        $activeSourceStage = null;
        for ($s = $maxStageInSession; $s >= 1; $s--) {
            if (isset($itemStages[$s]) && $itemStages[$s]['count_qty'] !== null) {
                $finalQty = $itemStages[$s]['count_qty'];
                $activeSourceStage = $s;
                break;
            }
        }

        $item['final_physical_qty'] = $finalQty !== null ? $finalQty : ($item['final_qty'] !== null ? (float)$item['final_qty'] : null);
        $sysStock = (float)$item['system_stock'];
        $item['final_difference'] = $item['final_physical_qty'] !== null ? ($item['final_physical_qty'] - $sysStock) : null;
        $item['active_source_stage'] = $activeSourceStage;
    }
    unset($item);

    echo json_encode([
        'success' => true,
        'opname' => $opname,
        'max_stage' => $maxStageInSession,
        'items' => $items
    ]);
    exit;
}

// =========================================================================
// 3. CREATE NEW STOCK OPNAME OR DYNAMIC COUNTING SESSION
// =========================================================================
if ($action === 'create') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $title         = trim($input['title'] ?? '');
    $counting_type = trim($input['counting_type'] ?? 'STOCK_OPNAME');
    $assigned_to_1 = (int)($input['assigned_to_operator_1'] ?? $input['assigned_to_1'] ?? 0);
    $scope         = trim($input['scope'] ?? 'all');
    $category      = trim($input['category'] ?? '');
    $rack          = trim($input['rack'] ?? '');
    $notes         = trim($input['notes'] ?? '');
    $material_ids  = $input['material_ids'] ?? $input['selected_sku_ids'] ?? [];

    if (!in_array($counting_type, ['STOCK_OPNAME', 'DYNAMIC_COUNT'])) {
        $counting_type = 'STOCK_OPNAME';
    }

    if (empty($title)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Judul sesi wajib diisi']);
        exit;
    }

    if ($counting_type === 'DYNAMIC_COUNT' && $assigned_to_1 <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Pilih PIC untuk penugasan Dynamic Count']);
        exit;
    }

    // Verify Operator if provided
    $op1 = null;
    if ($assigned_to_1 > 0) {
        $stmtOp = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
        $stmtOp->execute([$assigned_to_1]);
        $op1 = $stmtOp->fetch();
        if (!$op1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Operator penugasan tidak valid']);
            exit;
        }
    }

    // Resolve Target Materials
    $materialsQuery = "SELECT id, code, name, category, rack_location, current_stock FROM materials WHERE 1=1";
    $mParams = [];

    if ($counting_type === 'DYNAMIC_COUNT') {
        if (empty($material_ids) || !is_array($material_ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Pilih minimal satu SKU untuk penugasan Dynamic Count']);
            exit;
        }
        $placeholders = implode(',', array_fill(0, count($material_ids), '?'));
        $materialsQuery .= " AND id IN ($placeholders)";
        $mParams = array_map('intval', $material_ids);
    } else {
        if ($scope === 'category' && !empty($category) && $category !== 'all') {
            $materialsQuery .= " AND category = ?";
            $mParams[] = $category;
        } elseif ($scope === 'rack' && !empty($rack)) {
            $materialsQuery .= " AND rack_location LIKE ?";
            $mParams[] = "%{$rack}%";
        }
    }

    $materialsQuery .= " ORDER BY rack_location ASC, code ASC";
    $stmtMat = $pdo->prepare($materialsQuery);
    $stmtMat->execute($mParams);
    $targetMaterials = $stmtMat->fetchAll();

    if ($counting_type === 'DYNAMIC_COUNT' && empty($targetMaterials)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tidak ada SKU yang ditemukan untuk penugasan']);
        exit;
    }

    // Generate Structured Unique Opname Number with Date & Week
    $prefix = $counting_type === 'DYNAMIC_COUNT' ? 'DYN' : 'OPN';
    $opnameNo = generateOpnameNumber($pdo, $prefix);

    // Auto-generate title to document number if empty or generic
    if (empty($title) || strpos($title, 'Dynamic Count') !== false || strpos($title, 'Stock Opname') !== false) {
        $title = $opnameNo;
    }

    $pdo->beginTransaction();
    try {
        // 1. Insert Master Session
        $stmtSo = $pdo->prepare("
            INSERT INTO stock_opnames (opname_no, title, counting_type, max_stage, notes, status, created_by, created_at)
            VALUES (?, ?, ?, 1, ?, 'OPEN', ?, CURRENT_TIMESTAMP)
        ");
        $stmtSo->execute([$opnameNo, $title, $counting_type, $notes, Auth::id()]);
        $opnameId = (int)$pdo->lastInsertId();

        // 2. Insert Items & Stage 1 if targetMaterials found
        $assignedUserId = $assigned_to_1 > 0 ? $assigned_to_1 : Auth::id();
        
        $stmtItem = $pdo->prepare("
            INSERT INTO stock_opname_items (opname_id, material_id, system_stock, final_qty, difference, status, created_at)
            VALUES (?, ?, ?, NULL, 0, 'PENDING', CURRENT_TIMESTAMP)
        ");
        $stmtStage1 = $assigned_to_1 > 0 ? $pdo->prepare("
            INSERT INTO stock_opname_item_stages (opname_id, item_id, stage_number, assigned_to, count_qty, status, created_at)
            VALUES (?, ?, 1, ?, NULL, 'PENDING', CURRENT_TIMESTAMP)
        ") : null;

        foreach ($targetMaterials as $m) {
            $stmtItem->execute([$opnameId, $m['id'], $m['current_stock']]);
            $itemId = (int)$pdo->lastInsertId();

            if ($stmtStage1) {
                $stmtStage1->execute([$opnameId, $itemId, $assigned_to_1]);
            }
        }

        $pdo->commit();

        $typeName = $counting_type === 'DYNAMIC_COUNT' ? 'Dynamic Counting' : 'Stock Opname';
        echo json_encode([
            'success' => true,
            'message' => "Sesi {$typeName} '{$title}' ({$opnameNo}) berhasil dibuat (" . count($targetMaterials) . " SKU).",
            'id' => $opnameId,
            'opname_id' => $opnameId,
            'opname_no' => $opnameNo
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal membuat sesi: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// 4. GET ACTIVE STOCK OPNAME SESSION FOR OPERATOR
// =========================================================================
if ($action === 'get_active_stock_opname') {
    $active = getOrCreateActiveStockOpname($pdo, Auth::id());
    echo json_encode([
        'success' => true,
        'opname' => $active
    ]);
    exit;
}

// =========================================================================
// 5. OPERATOR DYNAMIC COUNTING TASKS (TASK-DRIVEN SKU SELECTION BY ADMIN)
// =========================================================================
if ($action === 'operator_dynamic_tasks') {
    $userId = Auth::id();

    $stmt = $pdo->prepare("
        SELECT st.id as stage_id,
               st.opname_id,
               st.item_id,
               st.stage_number,
               st.count_qty,
               st.scanned_rack,
               st.counted_at,
               st.status as stage_status,
               st.notes as operator_notes,
               so.opname_no,
               so.title as opname_title,
               so.counting_type,
               so.notes as opname_notes,
               so.status as opname_status,
               m.id as material_id,
               m.code as material_code,
               m.name as material_name,
               m.category as material_category,
               m.unit as material_unit,
               m.rack_location as rack_location
        FROM stock_opname_item_stages st
        JOIN stock_opname_items soi ON st.item_id = soi.id
        JOIN stock_opnames so ON st.opname_id = so.id
        JOIN materials m ON soi.material_id = m.id
        WHERE st.assigned_to = ? 
          AND so.counting_type = 'DYNAMIC_COUNT'
          AND so.status IN ('OPEN', 'COUNTING', 'RECOUNTING')
          AND st.stage_number = so.max_stage
          AND st.status = 'PENDING'
        ORDER BY m.rack_location ASC, m.code ASC
    ");
    $stmt->execute([$userId]);
    $tasks = $stmt->fetchAll();

    foreach ($tasks as &$t) {
        $t['id'] = (int)$t['item_id'];
        $t['stage_id'] = (int)$t['stage_id'];
        $t['stage_number'] = (int)$t['stage_number'];
        $t['stage_label'] = getStageLabel($t['stage_number']);
    }
    unset($t);

    echo json_encode([
        'success' => true,
        'tasks' => $tasks
    ]);
    exit;
}

// =========================================================================
// 6. OPERATOR SUBMIT DYNAMIC COUNT (WITH RACK LOCATION VERIFICATION/SCAN)
// =========================================================================
if ($action === 'submit_dynamic_count') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $stage_id     = (int)($input['stage_id'] ?? 0);
    $item_id      = (int)($input['item_id'] ?? 0);
    $count_qty    = isset($input['count_qty']) ? (float)$input['count_qty'] : null;
    $scanned_rack = trim($input['scanned_rack'] ?? $input['rack_location'] ?? '');
    $notes        = trim($input['notes'] ?? '');

    if ($count_qty === null || $count_qty < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Jumlah fisik real wajib diisi (minimal 0)']);
        exit;
    }

    $userId = Auth::id();

    // Find target stage record
    if ($stage_id > 0) {
        $stmtStage = $pdo->prepare("SELECT * FROM stock_opname_item_stages WHERE id = ?");
        $stmtStage->execute([$stage_id]);
    } else {
        $stmtStage = $pdo->prepare("
            SELECT * FROM stock_opname_item_stages 
            WHERE item_id = ? AND assigned_to = ? 
            ORDER BY stage_number DESC LIMIT 1
        ");
        $stmtStage->execute([$item_id, $userId]);
    }

    $stage = $stmtStage->fetch();
    if (!$stage) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Penugasan Dynamic Count tidak ditemukan untuk akun ini']);
        exit;
    }

    $itemId = (int)$stage['item_id'];
    $opnameId = (int)$stage['opname_id'];

    $pdo->beginTransaction();
    try {
        // Update stage record
        $stmtUpdateStage = $pdo->prepare("
            UPDATE stock_opname_item_stages 
            SET count_qty = ?, scanned_rack = ?, counted_at = CURRENT_TIMESTAMP, notes = ?, status = 'COUNTED'
            WHERE id = ?
        ");
        $stmtUpdateStage->execute([$count_qty, $scanned_rack, $notes, $stage['id']]);

        // Cascade Calculation: take latest non-null stage
        $stmtAllStages = $pdo->prepare("
            SELECT * FROM stock_opname_item_stages 
            WHERE item_id = ? 
            ORDER BY stage_number DESC
        ");
        $stmtAllStages->execute([$itemId]);
        $allStages = $stmtAllStages->fetchAll();

        $finalQty = null;
        foreach ($allStages as $st) {
            if ($st['count_qty'] !== null && $st['count_qty'] !== '') {
                $finalQty = (float)$st['count_qty'];
                break;
            }
        }

        // Compare against system stock snapshot
        $stmtItem = $pdo->prepare("SELECT system_stock FROM stock_opname_items WHERE id = ?");
        $stmtItem->execute([$itemId]);
        $sysStock = (float)$stmtItem->fetchColumn();

        $diff = ($finalQty !== null) ? ($finalQty - $sysStock) : 0;
        $status = ($finalQty === null) ? 'PENDING' : ($diff == 0 ? 'MATCH' : 'DISCREPANCY');

        $stmtUpdateItem = $pdo->prepare("
            UPDATE stock_opname_items 
            SET final_qty = ?, difference = ?, status = ?
            WHERE id = ?
        ");
        $stmtUpdateItem->execute([$finalQty, $diff, $status, $itemId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Hasil Dynamic Count berhasil disimpan ({$count_qty} Pcs) di rak '{$scanned_rack}'.",
            'final_qty' => $finalQty,
            'difference' => $diff,
            'status' => $status
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan hitungan: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// 7. OPERATOR STOCK OPNAME BLANK COUNT SUBMIT (NO PRE-ASSIGNED TASK LIST)
// =========================================================================
if ($action === 'submit_blank_count') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $material_id   = (int)($input['material_id'] ?? 0);
    $rack_location = trim($input['rack_location'] ?? $input['scanned_rack'] ?? '');
    $count_qty     = isset($input['count_qty']) ? (float)$input['count_qty'] : null;
    $notes         = trim($input['notes'] ?? '');
    $opname_id     = (int)($input['opname_id'] ?? 0);

    if ($material_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Pilih atau scan SKU Packaging Material']);
        exit;
    }

    if ($count_qty === null || $count_qty < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Jumlah fisik real wajib diisi (minimal 0)']);
        exit;
    }

    $userId = Auth::id();

    // Verify material exists
    $stmtMat = $pdo->prepare("SELECT * FROM materials WHERE id = ?");
    $stmtMat->execute([$material_id]);
    $material = $stmtMat->fetch();
    if (!$material) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Material Packaging tidak ditemukan']);
        exit;
    }

    // Use default rack from material if not specified
    if (empty($rack_location)) {
        $rack_location = $material['rack_location'] ?: '-';
    }

    // Resolve active Stock Opname session
    if ($opname_id <= 0) {
        $activeOp = getOrCreateActiveStockOpname($pdo, $userId);
        $opname_id = (int)$activeOp['id'];
    }

    $pdo->beginTransaction();
    try {
        // 1. Check if item already exists in this opname session
        $stmtItem = $pdo->prepare("
            SELECT * FROM stock_opname_items 
            WHERE opname_id = ? AND material_id = ?
        ");
        $stmtItem->execute([$opname_id, $material_id]);
        $item = $stmtItem->fetch();

        $sysStock = (float)$material['current_stock'];

        if (!$item) {
            // Insert item with snapshot of current system stock
            $stmtInsertItem = $pdo->prepare("
                INSERT INTO stock_opname_items (opname_id, material_id, system_stock, final_qty, difference, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'PENDING', CURRENT_TIMESTAMP)
            ");
            $diff = $count_qty - $sysStock;
            $status = $diff == 0 ? 'MATCH' : 'DISCREPANCY';
            $stmtInsertItem->execute([$opname_id, $material_id, $sysStock, $count_qty, $diff]);
            $itemId = (int)$pdo->lastInsertId();
        } else {
            $itemId = (int)$item['id'];
            $sysStock = (float)$item['system_stock'];
        }

        // 2. Insert or update Stage 1 record
        $stmtStage1 = $pdo->prepare("
            SELECT id FROM stock_opname_item_stages 
            WHERE item_id = ? AND stage_number = 1
        ");
        $stmtStage1->execute([$itemId]);
        $stage1Id = $stmtStage1->fetchColumn();

        if ($stage1Id) {
            $stmtUpdateStage = $pdo->prepare("
                UPDATE stock_opname_item_stages 
                SET assigned_to = ?, count_qty = ?, scanned_rack = ?, notes = ?, counted_at = CURRENT_TIMESTAMP, status = 'COUNTED'
                WHERE id = ?
            ");
            $stmtUpdateStage->execute([$userId, $count_qty, $rack_location, $notes, $stage1Id]);
        } else {
            $stmtInsertStage = $pdo->prepare("
                INSERT INTO stock_opname_item_stages (opname_id, item_id, stage_number, assigned_to, count_qty, scanned_rack, notes, counted_at, status, created_at)
                VALUES (?, ?, 1, ?, ?, ?, ?, CURRENT_TIMESTAMP, 'COUNTED', CURRENT_TIMESTAMP)
            ");
            $stmtInsertStage->execute([$opname_id, $itemId, $userId, $count_qty, $rack_location, $notes]);
        }

        // 3. Cascade update final physical qty on stock_opname_items
        $stmtAllStages = $pdo->prepare("
            SELECT * FROM stock_opname_item_stages 
            WHERE item_id = ? 
            ORDER BY stage_number DESC
        ");
        $stmtAllStages->execute([$itemId]);
        $allStages = $stmtAllStages->fetchAll();

        $finalQty = null;
        foreach ($allStages as $st) {
            if ($st['count_qty'] !== null && $st['count_qty'] !== '') {
                $finalQty = (float)$st['count_qty'];
                break;
            }
        }

        $finalDiff = ($finalQty !== null) ? ($finalQty - $sysStock) : 0;
        $finalStatus = ($finalQty === null) ? 'PENDING' : ($finalDiff == 0 ? 'MATCH' : 'DISCREPANCY');

        $stmtUpdateFinal = $pdo->prepare("
            UPDATE stock_opname_items 
            SET final_qty = ?, difference = ?, status = ?
            WHERE id = ?
        ");
        $stmtUpdateFinal->execute([$finalQty, $finalDiff, $finalStatus, $itemId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Hitung fisik '{$material['name']}' berhasil disimpan ({$count_qty} {$material['unit']}) di rak {$rack_location}.",
            'item_id' => $itemId,
            'material_name' => $material['name'],
            'count_qty' => $count_qty,
            'rack_location' => $rack_location
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan Blank Count: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// 8. GET OPERATOR'S SUBMITTED BLANK COUNTS (FOR ACTIVE SESSION)
// =========================================================================
if ($action === 'my_blank_counts') {
    $userId = Auth::id();

    $stmt = $pdo->prepare("
        SELECT st.id as stage_id,
               st.count_qty,
               st.scanned_rack,
               st.counted_at,
               st.notes as operator_notes,
               st.status as stage_status,
               soi.id as item_id,
               soi.opname_id,
               so.opname_no,
               so.title as opname_title,
               m.id as material_id,
               m.code as material_code,
               m.name as material_name,
               m.category as material_category,
               m.unit as material_unit,
               m.rack_location as master_rack
        FROM stock_opname_item_stages st
        JOIN stock_opname_items soi ON st.item_id = soi.id
        JOIN stock_opnames so ON st.opname_id = so.id
        JOIN materials m ON soi.material_id = m.id
        WHERE st.assigned_to = ? 
          AND st.stage_number = 1
          AND so.counting_type = 'STOCK_OPNAME'
          AND so.status IN ('OPEN', 'COUNTING', 'RECOUNTING')
        ORDER BY st.counted_at DESC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $rows
    ]);
    exit;
}

// =========================================================================
// 9. DELETE OPERATOR'S BLANK COUNT ENTRY
// =========================================================================
if ($action === 'delete_blank_count') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $stage_id = (int)($input['stage_id'] ?? 0);
    $userId   = Auth::id();

    $stmtStage = $pdo->prepare("
        SELECT st.*, so.status as opname_status 
        FROM stock_opname_item_stages st
        JOIN stock_opnames so ON st.opname_id = so.id
        WHERE st.id = ? AND st.assigned_to = ? AND st.stage_number = 1
    ");
    $stmtStage->execute([$stage_id, $userId]);
    $stage = $stmtStage->fetch();

    if (!$stage) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Entri hitungan tidak ditemukan']);
        exit;
    }

    if (!in_array($stage['opname_status'], ['OPEN', 'COUNTING', 'RECOUNTING'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Sesi Stock Opname telah selesai dan tidak dapat diubah']);
        exit;
    }

    $itemId = (int)$stage['item_id'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM stock_opname_item_stages WHERE id = ?")->execute([$stage_id]);
        
        // If no stages remain for this item, delete the item as well
        $stmtRemain = $pdo->prepare("SELECT COUNT(*) FROM stock_opname_item_stages WHERE item_id = ?");
        $stmtRemain->execute([$itemId]);
        if ((int)$stmtRemain->fetchColumn() === 0) {
            $pdo->prepare("DELETE FROM stock_opname_items WHERE id = ?")->execute([$itemId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Entri hitungan berhasil dihapus']);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus entri: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// 10. ASSIGN RECOUNT FOR DISCREPANCY ITEMS (AUTO-SPLIT BALANCED TO MULTIPLE OPERATORS)
// =========================================================================
if ($action === 'assign_recount') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $opname_id   = (int)($input['opname_id'] ?? 0);
    $rawOps      = $input['assigned_to_operators'] ?? $input['assigned_to_operator'] ?? $input['assigned_to'] ?? $input['operator_id'] ?? [];
    $item_ids    = $input['item_ids'] ?? []; // Optional: if empty, selects all discrepancy items
    $notes       = trim($input['notes'] ?? '');

    // Normalize operator IDs
    $operatorIds = [];
    if (is_array($rawOps)) {
        foreach ($rawOps as $opId) {
            $val = (int)$opId;
            if ($val > 0 && !in_array($val, $operatorIds)) $operatorIds[] = $val;
        }
    } elseif ((int)$rawOps > 0) {
        $operatorIds[] = (int)$rawOps;
    }

    if ($opname_id <= 0 || empty($operatorIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Sesi Opname dan minimal 1 Operator Recount wajib dipilih']);
        exit;
    }

    // Fetch operator names
    $placeholdersOps = implode(',', array_fill(0, count($operatorIds), '?'));
    $stmtOps = $pdo->prepare("SELECT id, name FROM users WHERE id IN ($placeholdersOps)");
    $stmtOps->execute($operatorIds);
    $validOps = $stmtOps->fetchAll();
    if (empty($validOps)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Operator penugasan tidak valid']);
        exit;
    }
    $validOpIds = array_column($validOps, 'id');
    $validOpNames = array_column($validOps, 'name');

    // Verify Opname
    $stmtSo = $pdo->prepare("SELECT * FROM stock_opnames WHERE id = ?");
    $stmtSo->execute([$opname_id]);
    $opname = $stmtSo->fetch();
    if (!$opname) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sesi Stock Opname tidak ditemukan']);
        exit;
    }

    $currentMaxStage = (int)$opname['max_stage'];
    $nextStageNumber = $currentMaxStage + 1;

    // Resolve target discrepancy items
    if (!empty($item_ids) && is_array($item_ids)) {
        $placeholders = implode(',', array_fill(0, count($item_ids), '?'));
        $stmtTarget = $pdo->prepare("
            SELECT id FROM stock_opname_items 
            WHERE opname_id = ? AND id IN ($placeholders)
        ");
        $stmtTarget->execute(array_merge([$opname_id], array_map('intval', $item_ids)));
    } else {
        // Auto select all items with discrepancy (difference != 0)
        $stmtTarget = $pdo->prepare("
            SELECT id FROM stock_opname_items 
            WHERE opname_id = ? AND final_qty IS NOT NULL AND difference != 0
        ");
        $stmtTarget->execute([$opname_id]);
    }

    $targetItemIds = $stmtTarget->fetchAll(PDO::FETCH_COLUMN);

    if (empty($targetItemIds)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tidak ada item dengan selisih stok (Difference != 0) untuk di-recount']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        // Update session max_stage and status
        $stmtUpSo = $pdo->prepare("
            UPDATE stock_opnames 
            SET max_stage = ?, status = 'RECOUNTING' 
            WHERE id = ?
        ");
        $stmtUpSo->execute([$nextStageNumber, $opname_id]);

        $stageLabel = getStageLabel($nextStageNumber);

        $stmtCheckStage = $pdo->prepare("
            SELECT id FROM stock_opname_item_stages 
            WHERE opname_id = ? AND item_id = ? AND stage_number = ?
        ");
        $stmtStageInsert = $pdo->prepare("
            INSERT INTO stock_opname_item_stages (opname_id, item_id, stage_number, assigned_to, count_qty, status, notes, created_at)
            VALUES (?, ?, ?, ?, NULL, 'PENDING', ?, CURRENT_TIMESTAMP)
        ");
        $stmtStageUpdate = $pdo->prepare("
            UPDATE stock_opname_item_stages 
            SET assigned_to = ?, count_qty = NULL, status = 'PENDING', notes = ?
            WHERE id = ?
        ");
        $stmtItemUpdate = $pdo->prepare("
            UPDATE stock_opname_items 
            SET status = 'RECOUNT_REQUESTED' 
            WHERE id = ?
        ");

        $opCount = count($validOpIds);
        foreach ($targetItemIds as $idx => $itemId) {
            // Round-robin balanced distribution
            $assignedOpId = $validOpIds[$idx % $opCount];

            $stmtCheckStage->execute([$opname_id, $itemId, $nextStageNumber]);
            $existingStageId = $stmtCheckStage->fetchColumn();

            if ($existingStageId) {
                $stmtStageUpdate->execute([$assignedOpId, $notes, $existingStageId]);
            } else {
                $stmtStageInsert->execute([$opname_id, $itemId, $nextStageNumber, $assignedOpId, $notes]);
            }

            $stmtItemUpdate->execute([$itemId]);
        }

        $pdo->commit();

        $opNamesText = implode(', ', $validOpNames);
        $message = count($validOpIds) > 1
            ? "Berhasil membagi & menugaskan {$stageLabel} untuk " . count($targetItemIds) . " SKU selisih secara merata ke " . count($validOpIds) . " operator ({$opNamesText})."
            : "Berhasil menugaskan {$stageLabel} untuk " . count($targetItemIds) . " SKU selisih ke operator '{$validOpNames[0]}'.";

        echo json_encode([
            'success' => true,
            'message' => $message,
            'next_stage' => $nextStageNumber,
            'stage_label' => $stageLabel,
            'assigned_items_count' => count($targetItemIds),
            'operator_count' => count($validOpIds)
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menugaskan recount: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// 11. OPERATOR RECOUNT TASKS (STAGE >= 2 DISCREPANCY ITEMS)
// =========================================================================
if ($action === 'operator_recount_tasks') {
    $userId = Auth::id();

    $stmt = $pdo->prepare("
        SELECT st.id as stage_id,
               st.opname_id,
               st.item_id,
               st.stage_number,
               st.count_qty,
               st.scanned_rack,
               st.counted_at,
               st.status as stage_status,
               st.notes as operator_notes,
               so.opname_no,
               so.title as opname_title,
               so.counting_type,
               so.notes as opname_notes,
               so.status as opname_status,
               m.id as material_id,
               m.code as material_code,
               m.name as material_name,
               m.category as material_category,
               m.unit as material_unit,
               m.rack_location as rack_location
        FROM stock_opname_item_stages st
        JOIN stock_opname_items soi ON st.item_id = soi.id
        JOIN stock_opnames so ON st.opname_id = so.id
        JOIN materials m ON soi.material_id = m.id
        WHERE st.assigned_to = ? 
          AND so.counting_type = 'STOCK_OPNAME'
          AND st.stage_number >= 2
          AND so.status IN ('OPEN', 'COUNTING', 'RECOUNTING')
          AND st.stage_number = so.max_stage
          AND st.status = 'PENDING'
        ORDER BY st.stage_number DESC, m.rack_location ASC, m.code ASC
    ");
    $stmt->execute([$userId]);
    $tasks = $stmt->fetchAll();

    foreach ($tasks as &$t) {
        $t['id'] = (int)$t['item_id'];
        $t['stage_id'] = (int)$t['stage_id'];
        $t['stage_number'] = (int)$t['stage_number'];
        $t['stage_label'] = getStageLabel($t['stage_number']);
    }
    unset($t);

    echo json_encode([
        'success' => true,
        'tasks' => $tasks
    ]);
    exit;
}

// =========================================================================
// 12. OPERATOR SUBMIT RECOUNT (STAGE >= 2)
// =========================================================================
if ($action === 'submit_recount' || $action === 'submit_count') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $stage_id     = (int)($input['stage_id'] ?? 0);
    $item_id      = (int)($input['item_id'] ?? 0);
    $count_qty    = isset($input['count_qty']) ? (float)$input['count_qty'] : null;
    $scanned_rack = trim($input['scanned_rack'] ?? $input['rack_location'] ?? '');
    $notes        = trim($input['notes'] ?? '');

    if ($count_qty === null || $count_qty < 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Jumlah fisik real wajib diisi (minimal 0)']);
        exit;
    }

    $userId = Auth::id();

    if ($stage_id > 0) {
        $stmtStage = $pdo->prepare("SELECT * FROM stock_opname_item_stages WHERE id = ?");
        $stmtStage->execute([$stage_id]);
    } else {
        $stmtStage = $pdo->prepare("
            SELECT * FROM stock_opname_item_stages 
            WHERE item_id = ? AND assigned_to = ? 
            ORDER BY stage_number DESC LIMIT 1
        ");
        $stmtStage->execute([$item_id, $userId]);
    }

    $stage = $stmtStage->fetch();
    if (!$stage) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Penugasan recount tidak ditemukan']);
        exit;
    }

    $itemId = (int)$stage['item_id'];
    $stageNum = (int)$stage['stage_number'];

    $pdo->beginTransaction();
    try {
        $stmtUpdateStage = $pdo->prepare("
            UPDATE stock_opname_item_stages 
            SET count_qty = ?, scanned_rack = ?, counted_at = CURRENT_TIMESTAMP, notes = ?, status = 'COUNTED'
            WHERE id = ?
        ");
        $stmtUpdateStage->execute([$count_qty, $scanned_rack, $notes, $stage['id']]);

        // Cascade Rule: latest non-null stage
        $stmtAllStages = $pdo->prepare("
            SELECT * FROM stock_opname_item_stages 
            WHERE item_id = ? 
            ORDER BY stage_number DESC
        ");
        $stmtAllStages->execute([$itemId]);
        $allStages = $stmtAllStages->fetchAll();

        $finalQty = null;
        foreach ($allStages as $st) {
            if ($st['count_qty'] !== null && $st['count_qty'] !== '') {
                $finalQty = (float)$st['count_qty'];
                break;
            }
        }

        $stmtItem = $pdo->prepare("SELECT system_stock FROM stock_opname_items WHERE id = ?");
        $stmtItem->execute([$itemId]);
        $sysStock = (float)$stmtItem->fetchColumn();

        $diff = ($finalQty !== null) ? ($finalQty - $sysStock) : 0;
        $status = ($finalQty === null) ? 'PENDING' : ($diff == 0 ? 'MATCH' : 'DISCREPANCY');

        $pdo->prepare("
            UPDATE stock_opname_items 
            SET final_qty = ?, difference = ?, status = ?
            WHERE id = ?
        ")->execute([$finalQty, $diff, $status, $itemId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Recount berhasil disimpan.",
            'final_qty' => $finalQty,
            'difference' => $diff,
            'status' => $status
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan recount: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// 13. ADMIN UPDATE ITEM (MANUAL OVERRIDE)
// =========================================================================
if ($action === 'update_item') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    $item_id   = (int)($input['item_id'] ?? 0);
    $final_qty = isset($input['final_physical_qty']) ? (float)$input['final_physical_qty'] : null;
    $notes     = trim($input['admin_notes'] ?? '');

    $stmtItem = $pdo->prepare("SELECT system_stock FROM stock_opname_items WHERE id = ?");
    $stmtItem->execute([$item_id]);
    $item = $stmtItem->fetch();
    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Item tidak ditemukan']);
        exit;
    }

    $sysStock = (float)$item['system_stock'];
    $diff = $final_qty - $sysStock;
    $status = $diff == 0 ? 'MATCH' : 'DISCREPANCY';

    $stmtUpdate = $pdo->prepare("
        UPDATE stock_opname_items 
        SET final_qty = ?, difference = ?, admin_notes = ?, status = ?
        WHERE id = ?
    ");
    $stmtUpdate->execute([$final_qty, $diff, $notes, $status, $item_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Data item opname berhasil diperbarui',
        'final_qty' => $final_qty,
        'difference' => $diff,
        'status' => $status
    ]);
    exit;
}

// =========================================================================
// 14. ADMIN DELETE ITEM
// =========================================================================
if ($action === 'delete_item') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $item_id = (int)($input['item_id'] ?? 0);

    $pdo->beginTransaction();
    try {
        $stmtGet = $pdo->prepare("SELECT opname_id FROM stock_opname_items WHERE id = ?");
        $stmtGet->execute([$item_id]);
        $opname_id = (int)$stmtGet->fetchColumn();

        $pdo->prepare("DELETE FROM stock_opname_item_stages WHERE item_id = ?")->execute([$item_id]);
        $pdo->prepare("DELETE FROM stock_opname_items WHERE id = ?")->execute([$item_id]);

        // Auto-delete session if no items remain
        if ($opname_id > 0) {
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM stock_opname_items WHERE opname_id = ?");
            $stmtCount->execute([$opname_id]);
            $remaining = (int)$stmtCount->fetchColumn();
            if ($remaining === 0) {
                $pdo->prepare("DELETE FROM stock_opnames WHERE id = ?")->execute([$opname_id]);
            }
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'Item opname berhasil dihapus']);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus item: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// 15. ADMIN DELETE OPNAME SESSION
// =========================================================================
if ($action === 'delete') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($input['id'] ?? 0);

    $stmtSo = $pdo->prepare("SELECT * FROM stock_opnames WHERE id = ?");
    $stmtSo->execute([$id]);
    $opname = $stmtSo->fetch();

    if (!$opname) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM stock_opname_item_stages WHERE opname_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM stock_opname_items WHERE opname_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM stock_opnames WHERE id = ?")->execute([$id]);
        $pdo->commit();

        echo json_encode(['success' => true, 'message' => "Sesi '{$opname['opname_no']}' berhasil dihapus"]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus sesi: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// 16. APPLY STOCK ADJUSTMENT DIRECTLY FROM OPNAME DIFFERENCES
// =========================================================================
if ($action === 'apply_adjustment') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = (int)($input['id'] ?? 0);

    $stmtSo = $pdo->prepare("SELECT * FROM stock_opnames WHERE id = ?");
    $stmtSo->execute([$id]);
    $opname = $stmtSo->fetch();

    if (!$opname) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Sesi tidak ditemukan']);
        exit;
    }

    if ($opname['status'] === 'COMPLETED') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Sesi opname ini sudah pernah di-adjust dan statusnya COMPLETED']);
        exit;
    }

    // Fetch all items with final physical qty
    $stmtItems = $pdo->prepare("
        SELECT soi.*, m.name as material_name, m.code as material_code, m.current_stock as live_stock
        FROM stock_opname_items soi
        JOIN materials m ON soi.material_id = m.id
        WHERE soi.opname_id = ? AND soi.final_qty IS NOT NULL
    ");
    $stmtItems->execute([$id]);
    $items = $stmtItems->fetchAll();

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Belum ada hasil hitung fisik yang terdata untuk di-adjust']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        $adjustedCount = 0;

        foreach ($items as $it) {
            $matId = (int)$it['material_id'];
            $finalQty = (float)$it['final_qty'];
            $currentLive = (float)$it['live_stock'];
            $delta = $finalQty - $currentLive;

            if ($delta != 0) {
                // Update material stock
                $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?")->execute([$finalQty, $matId]);

                // Create audit mutation
                $mutationType = $delta > 0 ? 'IN' : 'OUT';
                $absDelta = abs($delta);
                $docNo = 'ADJ-SO-' . $opname['opname_no'];
                $desc = "Penyesuaian Fisik Hasil {$opname['title']} ({$opname['opname_no']}) - Selisih: " . ($delta > 0 ? "+{$delta}" : "{$delta}");

                $stmtMut = $pdo->prepare("
                    INSERT INTO stock_mutations (material_id, type, qty, previous_stock, current_stock, reference_type, reference_id, document_no, description, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, 'ADJUSTMENT', ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmtMut->execute([$matId, $mutationType, $absDelta, $currentLive, $finalQty, $id, $docNo, $desc, Auth::id()]);
                $adjustedCount++;
            }
        }

        // Mark session as COMPLETED
        $pdo->prepare("UPDATE stock_opnames SET status = 'COMPLETED', updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Stok berhasil disesuaikan ({$adjustedCount} SKU disesuaikan). Sesi Opname ditandai COMPLETED.",
            'adjusted_count' => $adjustedCount
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menerapkan penyesuaian: ' . $e->getMessage()]);
        exit;
    }
}

// Fallback
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi API tidak valid']);
