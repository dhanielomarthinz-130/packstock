<?php
// api/tasks.php - Task Assignment, Bulk Assignment & Mobile Execution API (with Excel Task Import)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/batch_helper.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

/**
 * Helper: Parse Excel localized numbers (e.g. 1.712,36 or 0,7 or 1,712.36 or 1712.36)
 */
function parseExcelNumeric($val) {
    if (is_numeric($val)) return (float)$val;
    $val = trim((string)$val);
    if ($val === '') return 0.0;
    $val = preg_replace('/[^\d.,\-+]/u', '', $val);

    if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
        $lastComma = strrpos($val, ',');
        $lastDot   = strrpos($val, '.');
        if ($lastComma > $lastDot) {
            $val = str_replace('.', '', $val);
            $val = str_replace(',', '.', $val);
        } else {
            $val = str_replace(',', '', $val);
        }
    } elseif (strpos($val, ',') !== false) {
        $val = str_replace(',', '.', $val);
    } elseif (strpos($val, '.') !== false) {
        $parts = explode('.', $val);
        if (count($parts) > 2) {
            $val = str_replace('.', '', $val);
        }
    }

    $clean = preg_replace('/[^0-9.\-+]/', '', $val);
    return is_numeric($clean) ? (float)$clean : 0.0;
}

// 1. DOWNLOAD TEMPLATE EXCEL / CSV UNTUK TASK ASSIGNMENT
if ($action === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Template_Assign_Task_Operator.csv"');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM
    
    // Header
    fputcsv($output, ['Item No', 'Target Qty', 'Destination', 'Operator Username', 'Priority', 'Notes']);
    
    // Sample rows
    fputcsv($output, ['PKG-BOX-001', '100', 'Line Packing 1', 'operator1', 'URGENT', 'Ambil sebelum jam 10']);
    fputcsv($output, ['PKG-BTL-001', '500', 'Line Filling Botol Line A', 'operator1', 'NORMAL', 'Kebutuhan shift pagi']);
    fputcsv($output, ['PKG-CAP-001', '500', 'Line Filling Botol Line A', 'operator2', 'NORMAL', '']);
    
    fclose($output);
    exit;
}

// 2. LIST TASKS
if ($action === 'list') {
    $status   = trim($_GET['status'] ?? '');
    $priority = trim($_GET['priority'] ?? '');
    $search   = trim($_GET['search'] ?? '');
    $taskType = trim($_GET['task_type'] ?? '');
    $itemType = trim($_GET['item_type'] ?? '');
    $forMe    = isset($_GET['my_tasks']) && $_GET['my_tasks'] === '1';

    $query = "
        SELECT t.*, 
               m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location, m.current_stock as material_stock, m.item_type,
               u_to.name as operator_name, u_to.username as operator_username, u_to.shift as operator_shift,
               u_by.name as creator_name,
               (SELECT cr.request_no FROM consumable_requests cr WHERE cr.task_id = t.id OR (t.notes LIKE ('%' || cr.request_no || '%')) LIMIT 1) as request_no,
               (SELECT u_req.name FROM consumable_requests cr2 JOIN users u_req ON cr2.user_id = u_req.id WHERE cr2.task_id = t.id OR (t.notes LIKE ('%' || cr2.request_no || '%')) LIMIT 1) as requester_name
        FROM tasks t
        JOIN materials m ON t.material_id = m.id
        JOIN users u_to ON t.assigned_to = u_to.id
        JOIN users u_by ON t.assigned_by = u_by.id
        WHERE 1=1
    ";
    $params = [];

    if (Auth::role() === 'operator' || Auth::role() === 'operator_inventory' || Auth::role() === 'operator_fulfillment' || $forMe) {
        $query .= " AND t.assigned_to = ?";
        $params[] = Auth::id();
    }

    if (!empty($itemType) && $itemType !== 'ALL') {
        if ($itemType === 'GIMMICK') {
            $query .= " AND m.item_type = 'GIMMICK'";
        } elseif ($itemType === 'PACKAGING') {
            $query .= " AND (m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = '')";
        }
    }

    if (!empty($taskType) && $taskType !== 'ALL') {
        if ($taskType === 'PICKING') {
            $query .= " AND (t.task_type = 'PICKING' OR t.task_type IS NULL)";
        } else {
            $query .= " AND t.task_type = ?";
            $params[] = $taskType;
        }
    }

    if (!empty($status) && $status !== 'ALL') {
        if ($status === 'ACTIVE') {
            $query .= " AND t.status IN ('PENDING', 'IN_PROGRESS')";
        } else {
            $query .= " AND t.status = ?";
            $params[] = $status;
        }
    }

    if (!empty($priority) && $priority !== 'ALL') {
        $query .= " AND t.priority = ?";
        $params[] = $priority;
    }

    $date = trim($_GET['date'] ?? '');
    if (!empty($date)) {
        $query .= " AND DATE(t.created_at) = ?";
        $params[] = $date;
    }

    if (!empty($search)) {
        $query .= " AND (t.task_no LIKE ? OR m.name LIKE ? OR m.code LIKE ? OR t.destination LIKE ? OR t.from_location LIKE ? OR t.to_location LIKE ? OR u_to.name LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term, $term, $term, $term]);
    }

    $query .= " ORDER BY (t.priority = 'URGENT' OR t.priority = 'CRITICAL') DESC, (t.status = 'PENDING') DESC, (t.status = 'IN_PROGRESS') DESC, t.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $tasks]);
    exit;
}

// 2.1 CREATE RACK MOVEMENT TASK (TRANSFER ANTAR LOKASI RACK A -> RACK B - Admin Dispatching)
if (($action === 'create_movement' || $action === 'batch_create_movement') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $rawInput = file_get_contents('php://input');
    $input = !empty($rawInput) ? json_decode($rawInput, true) : [];
    if (empty($input) && !empty($_POST)) $input = $_POST;

    $items = $input['items'] ?? [];
    if (empty($items) && isset($input['material_id'])) {
        $items = [$input];
    }
    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Daftar item movement tidak boleh kosong!']);
        exit;
    }

    $globalAssignedTo = (int)($input['assigned_to'] ?? 0);
    $globalPriority   = strtoupper(trim($input['priority'] ?? 'NORMAL'));
    $globalNotes      = trim($input['notes'] ?? '');

    try {
        $pdo->beginTransaction();

        $prefix = 'MVT-' . date('Ym') . '-';
        $stmtLast = $pdo->prepare("SELECT task_no FROM tasks WHERE task_no LIKE ? ORDER BY LENGTH(task_no) DESC, task_no DESC LIMIT 1");
        $stmtLast->execute([$prefix . '%']);
        $lastTaskNo = $stmtLast->fetchColumn();
        $nextNum = 1;
        if ($lastTaskNo) {
            $parts = explode('-', $lastTaskNo);
            $lastSuffix = end($parts);
            if (is_numeric($lastSuffix)) $nextNum = (int)$lastSuffix + 1;
        }
        $stmtCheck = $pdo->prepare("SELECT 1 FROM tasks WHERE task_no = ? LIMIT 1");

        $now = date('Y-m-d H:i:s');
        $authId = Auth::id();
        $createdTasks = [];

        $stmtMat = $pdo->prepare("SELECT id, name, code, current_stock, rack_location, item_type FROM materials WHERE id = ?");
        $stmtInsert = $pdo->prepare("
            INSERT INTO tasks (
                task_no, material_id, target_qty, priority, destination, 
                assigned_to, assigned_by, status, notes, task_type, 
                from_location, to_location, batch_id, batch_no, exp_date, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, 'RACK_MOVEMENT', ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $idx => $it) {
            $materialId   = (int)($it['material_id'] ?? 0);
            $targetQty    = max(0, parseNumberDecimal($it['target_qty'] ?? $it['qty'] ?? 0));
            $fromLocation = trim($it['from_location'] ?? $it['from_rack'] ?? '');
            $toLocation   = trim($it['to_location'] ?? $it['to_rack'] ?? '');
            $assignedTo   = (int)($it['assigned_to'] ?? $globalAssignedTo);
            $priority     = strtoupper(trim($it['priority'] ?? $globalPriority));
            if (!in_array($priority, ['NORMAL', 'URGENT', 'CRITICAL'])) $priority = 'NORMAL';
            $itemNotes    = trim($it['notes'] ?? '');
            $notes        = !empty($globalNotes) ? ($itemNotes ? "{$globalNotes} | {$itemNotes}" : $globalNotes) : $itemNotes;

            $batchId      = (int)($it['batch_id'] ?? 0);
            $batchNo      = trim($it['batch_no'] ?? '');
            $expDate      = !empty($it['exp_date']) ? trim($it['exp_date']) : null;

            if ($materialId <= 0 || $targetQty <= 0) continue;
            if (empty($fromLocation) || empty($toLocation)) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Lokasi Rack Asal dan Lokasi Rack Tujuan wajib diisi!"]);
                exit;
            }
            if ($assignedTo <= 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Operator PIC wajib dipilih untuk penugasan movement!"]);
                exit;
            }

            $stmtMat->execute([$materialId]);
            $mat = $stmtMat->fetch();
            if (!$mat) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => "Produk #{$materialId} tidak ditemukan!"]);
                exit;
            }

            // Generate unique task_no MVT-YYYYMM-XXXX
            do {
                $taskNo = $prefix . str_pad($nextNum++, 4, '0', STR_PAD_LEFT);
                $stmtCheck->execute([$taskNo]);
            } while ($stmtCheck->fetchColumn());

            $destinationDesc = "Pindah ke {$toLocation}";
            $stmtInsert->execute([
                $taskNo, $materialId, $targetQty, $priority, $destinationDesc,
                $assignedTo, $authId, $notes,
                $fromLocation, $toLocation,
                $batchId > 0 ? $batchId : null,
                !empty($batchNo) ? $batchNo : null,
                $expDate,
                $now
            ]);

            $createdTasks[] = $taskNo;
        }

        if (empty($createdTasks)) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada item movement yang valid untuk ditugaskan.']);
            exit;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => count($createdTasks) . " tugas Movement Product berhasil ditugaskan ke Operator!",
            'task_numbers' => $createdTasks
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal membuat tugas movement.');
    }
    exit;
}

// 2.2 CREATE OPERATOR DIRECT MOVEMENT (TRANSFER ANTAR LOKASI DIINISIASI OPERATOR)
if (($action === 'create_operator_movement' || $action === 'batch_create_operator_movement') && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::isOperatorAny() && !Auth::isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Akses ditolak. Fitur ini khusus Operator / Admin.']);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $input = !empty($rawInput) ? json_decode($rawInput, true) : [];
    if (empty($input) && !empty($_POST)) $input = $_POST;

    $items = $input['items'] ?? $_POST['items'] ?? [];
    if (is_string($items)) {
        $items = json_decode($items, true) ?: [];
    }
    if (empty($items) && isset($input['material_id'])) {
        $items = [$input];
    }
    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Daftar item transfer tidak boleh kosong!']);
        exit;
    }

    $globalNotes = trim($input['notes'] ?? $_POST['notes'] ?? '');
    $referenceNo = trim($input['no_sj'] ?? $input['reference_no'] ?? $_POST['no_sj'] ?? $_POST['reference_no'] ?? '');
    $photoPath   = handleUploadedTaskPhotos();
    $authId = Auth::id();
    $authName = Auth::name() ?? 'Operator';

    try {
        $pdo->beginTransaction();

        $prefix = 'MVT-' . date('Ym') . '-';
        $stmtLast = $pdo->prepare("SELECT task_no FROM tasks WHERE task_no LIKE ? ORDER BY LENGTH(task_no) DESC, task_no DESC LIMIT 1");
        $stmtLast->execute([$prefix . '%']);
        $lastTaskNo = $stmtLast->fetchColumn();
        $nextNum = 1;
        if ($lastTaskNo) {
            $parts = explode('-', $lastTaskNo);
            $lastSuffix = end($parts);
            if (is_numeric($lastSuffix)) $nextNum = (int)$lastSuffix + 1;
        }
        $stmtCheck = $pdo->prepare("SELECT 1 FROM tasks WHERE task_no = ? LIMIT 1");

        $now = date('Y-m-d H:i:s');
        $createdTasks = [];

        $stmtMat = $pdo->prepare("SELECT id, name, code, unit, current_stock, rack_location, item_type FROM materials WHERE id = ?");
        $stmtInsert = $pdo->prepare("
            INSERT INTO tasks (
                task_no, material_id, target_qty, actual_qty, priority, destination, 
                assigned_to, assigned_by, status, notes, completion_notes, photo_path, reference_no, task_type, 
                from_location, to_location, batch_id, batch_no, exp_date, 
                started_at, completed_at, duration_seconds, created_at
            ) VALUES (?, ?, ?, ?, 'NORMAL', ?, ?, ?, 'COMPLETED', ?, ?, ?, ?, 'RACK_MOVEMENT', ?, ?, ?, ?, ?, ?, ?, 1, ?)
        ");

        $stmtUpMat = $pdo->prepare("UPDATE materials SET rack_location = ? WHERE id = ?");
        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
            VALUES (?, 'TRANSFER_LOCATION', 0, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $it) {
            $materialId   = (int)($it['material_id'] ?? 0);
            $qty          = max(0, parseNumberDecimal($it['qty'] ?? $it['target_qty'] ?? 0));
            $fromLocation = trim($it['from_location'] ?? $it['from_rack'] ?? '');
            $toLocation   = trim($it['to_location'] ?? $it['to_rack'] ?? '');
            $itemNotes    = trim($it['notes'] ?? '');
            $notes        = !empty($globalNotes) ? ($itemNotes ? "{$globalNotes} | {$itemNotes}" : $globalNotes) : $itemNotes;

            $batchId      = (int)($it['batch_id'] ?? 0);
            $batchNo      = trim($it['batch_no'] ?? '');
            $expDate      = !empty($it['exp_date']) ? trim($it['exp_date']) : null;

            if ($materialId <= 0 || $qty <= 0) continue;
            if (empty($fromLocation) || empty($toLocation)) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Lokasi Rack Asal dan Lokasi Rack Tujuan wajib diisi!"]);
                exit;
            }

            // Validasi Dynamic Count Freeze
            $freeze = getMaterialDynamicCountFreeze($pdo, $materialId);
            if ($freeze) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "Transfer Ditolak! SKU '{$freeze['material_name']}' ({$freeze['material_code']}) sedang dalam sesi Dynamic Count aktif (#{$freeze['opname_no']}) dan dibekukan."
                ]);
                exit;
            }

            $stmtMat->execute([$materialId]);
            $mat = $stmtMat->fetch();
            if (!$mat) {
                $pdo->rollBack();
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => "Produk #{$materialId} tidak ditemukan!"]);
                exit;
            }

            // Generate unique task_no MVT-YYYYMM-XXXX
            do {
                $taskNo = $prefix . str_pad($nextNum++, 4, '0', STR_PAD_LEFT);
                $stmtCheck->execute([$taskNo]);
            } while ($stmtCheck->fetchColumn());

            $destinationDesc = "Pindah ke {$toLocation}";
            $compNote = "Transfer mandiri oleh {$authName} dari {$fromLocation} ke {$toLocation}";
            if (!empty($referenceNo)) $compNote .= " (No. SJ: {$referenceNo})";
            if (!empty($notes)) $compNote .= " ({$notes})";

            $stmtInsert->execute([
                $taskNo, $materialId, $qty, $qty, $destinationDesc,
                $authId, $authId, $notes, $compNote, $photoPath, !empty($referenceNo) ? $referenceNo : null,
                $fromLocation, $toLocation,
                $batchId > 0 ? $batchId : null,
                !empty($batchNo) ? $batchNo : null,
                $expDate,
                $now, $now, $now
            ]);

            // 1. Update Master Lokasi Rak Material
            $stmtUpMat->execute([$toLocation, $materialId]);

            // 2. Jika ada batch, pindahkan batch ke lokasi baru
            if (!empty($toLocation)) {
                recordBatchTransfer($pdo, $materialId, $batchId > 0 ? $batchId : 0, !empty($batchNo) ? $batchNo : null, $fromLocation, $toLocation, $qty);
            }

            // 3. Catat Mutasi Audit Trail TRANSFER_LOCATION
            $stockBefore = (float)$mat['current_stock'];
            $mutNotes = "Transfer Antar Lokasi dari {$fromLocation} ke {$toLocation} (Qty: {$qty} {$mat['unit']}) oleh Operator {$authName}";
            if (!empty($referenceNo)) $mutNotes .= " [No. SJ: {$referenceNo}]";
            if (!empty($notes)) $mutNotes .= " (Catatan: {$notes})";
            $stmtMut->execute([$materialId, $stockBefore, $stockBefore, $taskNo, $mutNotes, $authId, $now]);

            $createdTasks[] = $taskNo;
        }

        if (empty($createdTasks)) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada item transfer yang valid untuk diproses.']);
            exit;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => count($createdTasks) . " item Transfer Antar Lokasi berhasil diproses dan disimpan!",
            'task_numbers' => $createdTasks
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal memproses transfer lokasi.');
    }
    exit;
}

// 3. CREATE SINGLE TASK (Admin only)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $materialId  = (int)($input['material_id'] ?? 0);
    $targetQty   = max(0, parseNumberDecimal($input['target_qty'] ?? 0));
    $priority    = strtoupper(trim($input['priority'] ?? 'NORMAL'));
    $destination = trim($input['destination'] ?? 'Line Packing');
    $assignedTo  = (int)($input['assigned_to'] ?? 0);
    $notes       = trim($input['notes'] ?? '');

    if ($qtyError = validateQtyRange($targetQty, 'Target Qty')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $qtyError]);
        exit;
    }

    if ($materialId <= 0 || $targetQty <= 0 || empty($destination) || $assignedTo <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Material, Target Qty (> 0), Tujuan Line, dan Operator PIC wajib diisi!']);
        exit;
    }

    // Validasi Pembekuan (Freeze) SKU saat pembuatan task
    $freeze = getMaterialDynamicCountFreeze($pdo, $materialId);
    if ($freeze) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => "Pembuatan Tugas Picking DITOLAK! SKU '{$freeze['material_name']}' ({$freeze['material_code']}) sedang dalam sesi Dynamic Count aktif (#{$freeze['opname_no']}) dan dibekukan (Freeze) sampai sesi diselesaikan."
        ]);
        exit;
    }

    // Validate available stock
    $stmtMat = $pdo->prepare("SELECT id, name, current_stock FROM materials WHERE id = ?");
    $stmtMat->execute([$materialId]);
    $mat = $stmtMat->fetch();

    if (!$mat) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Kemas tidak ditemukan!']);
        exit;
    }

    if ($targetQty > (float)$mat['current_stock']) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "Target Qty ({$targetQty}) tidak bisa lebih besar dari Sisa Stok yang tersedia (" . number_format($mat['current_stock'], 2, ',', '.') . ") untuk material {$mat['name']}!"
        ]);
        exit;
    }

    $prefix = 'TSK-' . date('Ym') . '-';
    $stmtLast = $pdo->prepare("SELECT task_no FROM tasks WHERE task_no LIKE ? ORDER BY LENGTH(task_no) DESC, task_no DESC LIMIT 1");
    $stmtLast->execute([$prefix . '%']);
    $lastTaskNo = $stmtLast->fetchColumn();
    $nextNum = 1;
    if ($lastTaskNo) {
        $parts = explode('-', $lastTaskNo);
        $lastSuffix = end($parts);
        if (is_numeric($lastSuffix)) $nextNum = (int)$lastSuffix + 1;
    }
    $stmtCheck = $pdo->prepare("SELECT 1 FROM tasks WHERE task_no = ? LIMIT 1");
    do {
        $taskNo = $prefix . str_pad($nextNum++, 4, '0', STR_PAD_LEFT);
        $stmtCheck->execute([$taskNo]);
    } while ($stmtCheck->fetchColumn());
    $now = date('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tasks (task_no, material_id, target_qty, priority, destination, assigned_to, assigned_by, status, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?)
        ");
        $stmt->execute([$taskNo, $materialId, $targetQty, $priority, $destination, $assignedTo, Auth::id(), $notes, $now]);

        echo json_encode([
            'success' => true,
            'message' => "Task #{$taskNo} berhasil ditugaskan ke operator!",
            'task_no' => $taskNo
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        apiFail($e, 'Gagal membuat tugas.');
    }
    exit;
}

// 3.1 GET SINGLE TASK (Admin only for Edit)
if ($action === 'get') {
    Auth::requireAdmin();
    $taskId = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT t.*, 
               m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location, m.current_stock as material_stock,
               u_to.name as operator_name, u_to.username as operator_username, u_to.shift as operator_shift,
               u_by.name as creator_name
        FROM tasks t
        JOIN materials m ON t.material_id = m.id
        JOIN users u_to ON t.assigned_to = u_to.id
        JOIN users u_by ON t.assigned_by = u_by.id
        WHERE t.id = ?
    ");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if ($task) {
        echo json_encode(['success' => true, 'data' => $task]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Task tidak ditemukan']);
    }
    exit;
}

// 3.2 UPDATE TASK (Admin only - Target Qty & Info)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $taskId      = (int)($input['task_id'] ?? 0);
    $materialId  = (int)($input['material_id'] ?? 0);
    $targetQty   = max(0, parseNumberDecimal($input['target_qty'] ?? 0));
    $assignedTo  = (int)($input['assigned_to'] ?? 0);
    $destination = trim($input['destination'] ?? 'Line Packing 1');
    $priority    = strtoupper(trim($input['priority'] ?? 'NORMAL'));
    if (!in_array($priority, ['NORMAL', 'URGENT', 'CRITICAL'])) $priority = 'NORMAL';
    $notes       = trim($input['notes'] ?? '');

    if ($taskId <= 0 || $materialId <= 0 || $targetQty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data task tidak lengkap (Target Qty harus > 0).']);
        exit;
    }

    if ($qtyError = validateQtyRange($targetQty, 'Target Qty')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $qtyError]);
        exit;
    }

    try {
        $stmtMat = $pdo->prepare("SELECT name, current_stock, unit FROM materials WHERE id = ?");
        $stmtMat->execute([$materialId]);
        $mat = $stmtMat->fetch();

        if (!$mat) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Material tidak ditemukan']);
            exit;
        }

        if ($targetQty > (float)$mat['current_stock']) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => "Target Qty ({$targetQty}) tidak bisa lebih besar dari Sisa Stok (" . number_format($mat['current_stock'], 2, ',', '.') . " {$mat['unit']}) untuk material {$mat['name']}!"
            ]);
            exit;
        }

        $stmtUpdate = $pdo->prepare("
            UPDATE tasks 
            SET material_id = ?, 
                target_qty = ?, 
                priority = ?, 
                destination = ?, 
                assigned_to = ?, 
                notes = ?
            WHERE id = ?
        ");
        $stmtUpdate->execute([$materialId, $targetQty, $priority, $destination, $assignedTo, $notes, $taskId]);

        echo json_encode(['success' => true, 'message' => 'Target Qty penugasan task berhasil diperbarui!']);
    } catch (Exception $e) {
        http_response_code(500);
        apiFail($e, 'Gagal memperbarui task.');
    }
    exit;
}

// 4. BATCH / MULTIPLE CREATE TASKS (Admin only - Multiple Rows & Excel Commit)
if ($action === 'batch_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $tasksList = $input['tasks'] ?? [];

    if (empty($tasksList)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Daftar penugasan tugas kosong.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $prefix = 'TSK-' . date('Ym') . '-';
        $stmtLast = $pdo->prepare("SELECT task_no FROM tasks WHERE task_no LIKE ? ORDER BY LENGTH(task_no) DESC, task_no DESC LIMIT 1");
        $stmtLast->execute([$prefix . '%']);
        $lastTaskNo = $stmtLast->fetchColumn();
        $nextNum = 1;
        if ($lastTaskNo) {
            $parts = explode('-', $lastTaskNo);
            $lastSuffix = end($parts);
            if (is_numeric($lastSuffix)) $nextNum = (int)$lastSuffix + 1;
        }
        $stmtCheck = $pdo->prepare("SELECT 1 FROM tasks WHERE task_no = ? LIMIT 1");

        $now = date('Y-m-d H:i:s');
        $stmtInsert = $pdo->prepare("
            INSERT INTO tasks (task_no, material_id, target_qty, priority, destination, assigned_to, assigned_by, status, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', ?, ?)
        ");

        $createdCount = 0;
        $createdTaskNumbers = [];
        $authId = Auth::id();

        $stmtMatCheck = $pdo->prepare("SELECT name, current_stock FROM materials WHERE id = ?");

        foreach ($tasksList as $idx => $t) {
            $materialId  = (int)($t['material_id'] ?? 0);
            $targetQty   = max(0, parseNumberDecimal($t['target_qty'] ?? 0));
            $priority    = strtoupper(trim($t['priority'] ?? 'NORMAL'));
            if (!in_array($priority, ['NORMAL', 'URGENT', 'CRITICAL'])) $priority = 'NORMAL';
            $destination = trim($t['destination'] ?? 'Line Packing 1');
            $assignedTo  = (int)($t['assigned_to'] ?? 0);
            $notes       = trim($t['notes'] ?? '');

            if ($materialId <= 0 || $assignedTo <= 0) continue;

            // Validasi Pembekuan (Freeze) SKU saat batch create task
            $freeze = getMaterialDynamicCountFreeze($pdo, $materialId);
            if ($freeze) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => "Pembuatan Tugas Picking DITOLAK! SKU '{$freeze['material_name']}' ({$freeze['material_code']}) sedang dalam sesi Dynamic Count aktif (#{$freeze['opname_no']}) dan dibekukan (Freeze) pada baris ke-" . ($idx + 1) . "!"
                ]);
                exit;
            }

            if ($targetQty <= 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Target Qty harus lebih besar dari 0 pada baris ke-" . ($idx + 1) . "!"]);
                exit;
            }

            $stmtMatCheck->execute([$materialId]);
            $mat = $stmtMatCheck->fetch();
            if ($mat && $targetQty > (float)$mat['current_stock']) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'message' => "Target Qty ({$targetQty}) tidak bisa lebih besar dari Sisa Stok (" . number_format($mat['current_stock'], 2, ',', '.') . ") untuk material {$mat['name']} pada baris ke-" . ($idx + 1) . "!"
                ]);
                exit;
            }

            do {
                $taskNo = $prefix . str_pad($nextNum++, 4, '0', STR_PAD_LEFT);
                $stmtCheck->execute([$taskNo]);
            } while ($stmtCheck->fetchColumn());

            $stmtInsert->execute([$taskNo, $materialId, $targetQty, $priority, $destination, $assignedTo, $authId, $notes, $now]);
            $createdCount++;
            $createdTaskNumbers[] = $taskNo;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Berhasil membuat {$createdCount} penugasan task sekaligus ke operator!",
            'created_count' => $createdCount,
            'task_numbers' => $createdTaskNumbers
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal membuat tugas bulk.');
    }
    exit;
}

// 5. PARSE & PREVIEW EXCEL / CSV TASK IMPORT (Admin only)
if ($action === 'preview_excel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $rows = [];

    // Parse file or text
    if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $filePath = $_FILES['file']['tmp_name'];
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Gagal membaca file tugas.']);
            exit;
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        while (($data = fgetcsv($handle, 4096, $delimiter)) !== false) {
            if (empty(array_filter($data))) continue;
            $rows[] = $data;
        }
        fclose($handle);
    } else {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $rawText = trim($input['raw_text'] ?? '');
        if (empty($rawText)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Silakan upload file CSV/Excel atau paste teks tabel tugas.']);
            exit;
        }

        $lines = explode("\n", $rawText);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if (strpos($line, "\t") !== false) {
                $cols = explode("\t", $line);
            } elseif (strpos($line, ";") !== false) {
                $cols = str_getcsv($line, ';');
            } else {
                $cols = str_getcsv($line, ',');
            }
            $rows[] = array_map('trim', $cols);
        }
    }

    if (empty($rows)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Data file tugas kosong atau tidak dapat dibaca']);
        exit;
    }

    // Find header
    $headerRowIdx = 0;
    for ($r = 0; $r < min(10, count($rows)); $r++) {
        $lineLower = strtolower(implode(' ', array_map('strval', $rows[$r])));
        if (strpos($lineLower, 'item') !== false || strpos($lineLower, 'sku') !== false || strpos($lineLower, 'target') !== false || strpos($lineLower, 'qty') !== false) {
            $headerRowIdx = $r;
            break;
        }
    }

    $headers = array_map(function($h) {
        return strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF\.]/', '', (string)$h)));
    }, $rows[$headerRowIdx]);

    $itemNoIdx = -1;
    $qtyIdx = -1;
    $destIdx = -1;
    $opIdx = -1;
    $priorityIdx = -1;
    $notesIdx = -1;

    foreach ($headers as $idx => $header) {
        if (strpos($header, 'item no') !== false || strpos($header, 'itemno') !== false || strpos($header, 'kode') !== false || strpos($header, 'sku') !== false) {
            $itemNoIdx = $idx;
        } elseif (strpos($header, 'target qty') !== false || strpos($header, 'qty') !== false || strpos($header, 'jumlah') !== false || strpos($header, 'target') !== false) {
            $qtyIdx = $idx;
        } elseif (strpos($header, 'destination') !== false || strpos($header, 'tujuan') !== false || strpos($header, 'line') !== false) {
            $destIdx = $idx;
        } elseif (strpos($header, 'operator') !== false || strpos($header, 'pic') !== false || strpos($header, 'petugas') !== false) {
            $opIdx = $idx;
        } elseif (strpos($header, 'priority') !== false || strpos($header, 'prioritas') !== false) {
            $priorityIdx = $idx;
        } elseif (strpos($header, 'note') !== false || strpos($header, 'catatan') !== false || strpos($header, 'keterangan') !== false) {
            $notesIdx = $idx;
        }
    }

    if ($itemNoIdx === -1) $itemNoIdx = 0;
    if ($qtyIdx === -1) $qtyIdx = 1;
    $startRow = $headerRowIdx + 1;

    // Load materials and operators dictionary for fast lookup
    $materialsMap = [];
    $stmtMat = $pdo->query("SELECT id, code, name, unit, current_stock, rack_location FROM materials");
    while ($m = $stmtMat->fetch()) {
        $materialsMap[strtoupper($m['code'])] = $m;
    }

    $operatorsMap = [];
    $defaultOperator = null;
    $stmtOp = $pdo->query("SELECT id, username, name, shift FROM users WHERE role IN ('operator', 'operator_inventory', 'operator_fulfillment') OR role LIKE 'operator%'");
    while ($op = $stmtOp->fetch()) {
        $operatorsMap[strtolower($op['username'])] = $op;
        $operatorsMap[strtolower($op['name'])] = $op;
        if (!$defaultOperator) $defaultOperator = $op;
    }

    $parsedTasks = [];
    $validCount = 0;
    $invalidCount = 0;

    for ($i = $startRow; $i < count($rows); $i++) {
        $r = $rows[$i];
        if (count(array_filter($r)) === 0) continue;

        $itemNo   = strtoupper(trim($r[$itemNoIdx] ?? ''));
        $qtyRaw   = trim($r[$qtyIdx] ?? '1');
        $targetQty = max(0.01, parseExcelNumeric($qtyRaw));
        $destination = ($destIdx !== -1 && !empty($r[$destIdx])) ? trim($r[$destIdx]) : 'Line Packing 1';
        $opRaw    = ($opIdx !== -1 && !empty($r[$opIdx])) ? strtolower(trim($r[$opIdx])) : '';
        $priority = ($priorityIdx !== -1 && !empty($r[$priorityIdx])) ? strtoupper(trim($r[$priorityIdx])) : 'NORMAL';
        if (!in_array($priority, ['NORMAL', 'URGENT', 'CRITICAL'])) $priority = 'NORMAL';
        $notes    = ($notesIdx !== -1 && !empty($r[$notesIdx])) ? trim($r[$notesIdx]) : '';

        if (empty($itemNo)) continue;

        // Check if material exists
        $mat = $materialsMap[$itemNo] ?? null;
        $assignedOp = $operatorsMap[$opRaw] ?? $defaultOperator;

        $statusValidation = 'VALID';
        $warningMessage = '';

        if (!$mat) {
            $statusValidation = 'ERROR_MATERIAL';
            $warningMessage = "Material '{$itemNo}' tidak ditemukan di database!";
            $invalidCount++;
        } elseif (!$assignedOp) {
            $statusValidation = 'ERROR_OPERATOR';
            $warningMessage = "Operator tidak ditemukan!";
            $invalidCount++;
        } else {
            $validCount++;
            if ($targetQty > (float)$mat['current_stock']) {
                $warningMessage = "Peringatan: Stok tersedia (" . number_format($mat['current_stock'], 2, ',', '.') . " {$mat['unit']}) kurang dari target ambil ({$targetQty} {$mat['unit']}).";
            }
        }

        $parsedTasks[] = [
            'row_num' => $i + 1,
            'item_no' => $itemNo,
            'material_id' => $mat ? (int)$mat['id'] : 0,
            'material_name' => $mat ? $mat['name'] : 'Item Tidak Ditemukan',
            'material_unit' => $mat ? $mat['unit'] : 'Pcs',
            'material_stock' => $mat ? (float)$mat['current_stock'] : 0,
            'rack_location' => $mat ? $mat['rack_location'] : '-',
            'target_qty' => $targetQty,
            'destination' => $destination,
            'assigned_to' => $assignedOp ? (int)$assignedOp['id'] : 0,
            'operator_name' => $assignedOp ? $assignedOp['name'] : 'Belum Ada Operator',
            'operator_username' => $assignedOp ? $assignedOp['username'] : '-',
            'priority' => $priority,
            'notes' => $notes,
            'validation_status' => $statusValidation,
            'warning' => $warningMessage
        ];
    }

    echo json_encode([
        'success' => true,
        'summary' => [
            'total_rows' => count($parsedTasks),
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount
        ],
        'tasks' => $parsedTasks
    ]);
    exit;
}

// 6. UPDATE TASK STATUS (Mulai Ambil -> IN_PROGRESS)
if ($action === 'start' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $taskId = (int)($input['task_id'] ?? 0);

    if ($taskId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Task tidak valid']);
        exit;
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("UPDATE tasks SET status = 'IN_PROGRESS', started_at = IFNULL(started_at, ?) WHERE id = ? AND status = 'PENDING'");
    $stmt->execute([$now, $taskId]);

    echo json_encode([
        'success' => true, 
        'message' => 'Tugas sedang dikerjakan (In Progress)',
        'started_at' => $now
    ]);
    exit;
}

// Helper to process uploaded photos for tasks
function handleUploadedTaskPhotos(): ?string {
    if (!isset($_FILES['photos'])) {
        return null;
    }
    $files = $_FILES['photos'];
    $fileCount = is_array($files['name']) ? count($files['name']) : 0;
    if ($fileCount === 0) {
        return null;
    }

    $uploadDir = __DIR__ . '/../uploads/tasks/';
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
                $newFileName = 'task_' . date('Ymd_His') . '_' . substr(md5(uniqid() . $i), 0, 8) . '.' . $ext;
                $destPath = $uploadDir . $newFileName;
                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $photoPaths[] = 'uploads/tasks/' . $newFileName;
                }
            }
        }
    }

    return !empty($photoPaths) ? json_encode($photoPaths) : null;
}

// 7. SUBMIT TASK / COMPLETE PICKING (Operator Finalize & Stock Deduction)
if ($action === 'submit_complete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = !empty($rawInput) ? json_decode($rawInput, true) : [];
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    $taskId          = (int)($input['task_id'] ?? 0);
    $actualQty       = max(0, parseNumberDecimal($input['actual_qty'] ?? 0));
    $completionNotes = trim($input['completion_notes'] ?? '');
    $photoPathValue  = handleUploadedTaskPhotos();

    if ($taskId <= 0 || $actualQty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Jumlah riil barang wajib diisi lebih dari 0!']);
        exit;
    }

    if ($qtyError = validateQtyRange($actualQty, 'Jumlah riil barang')) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $qtyError]);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtTask = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmtTask->execute([$taskId]);
        $task = $stmtTask->fetch();

        if (!$task) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Tugas tidak ditemukan']);
            exit;
        }

        $isMovement = ($task['task_type'] ?? '') === 'RACK_MOVEMENT';

        if (empty($completionNotes)) {
            if ($isMovement) {
                $completionNotes = "Movement barang dari " . ($task['from_location'] ?? 'Rak Asal') . " ke " . ($task['to_location'] ?? 'Rak Tujuan') . " selesai ditata.";
            } else {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Catatan penerima di line / PIC wajib diisi!']);
                exit;
            }
        }

        if (empty($photoPathValue) && !$isMovement) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Foto bukti penyerahan ke line wajib diunggah minimal 1 foto!']);
            exit;
        }

        if ($task['status'] === 'COMPLETED') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tugas ini sudah selesai disubmit sebelumnya.']);
            exit;
        }

        // Validasi Pembekuan (Freeze) SKU saat penyelesaian task
        $freeze = getMaterialDynamicCountFreeze($pdo, (int)$task['material_id']);
        if ($freeze) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Penyelesaian Tugas / Pengurangan Stok DITOLAK! SKU '{$freeze['material_name']}' ({$freeze['material_code']}) sedang dalam sesi Dynamic Count aktif (#{$freeze['opname_no']}) dan dibekukan (Freeze) sampai sesi diselesaikan."
            ]);
            exit;
        }

        $materialId = (int)$task['material_id'];

        $stmtMat = $pdo->prepare("SELECT id, name, code, unit, current_stock FROM materials WHERE id = ?" . rowLockClause($pdo));
        $stmtMat->execute([$materialId]);
        $mat = $stmtMat->fetch();

        if (!$mat) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Material tidak ditemukan']);
            exit;
        }

        $stockBefore = (float)$mat['current_stock'];

        // Penjaga kecukupan stok — sejalan dengan api/outbound.php.
        // Tanpa ini, penyelesaian task dapat mendorong stok menjadi negatif.
        // RACK_MOVEMENT dikecualikan karena hanya memindahkan lokasi, tidak mengurangi stok.
        if (($task['task_type'] ?? '') !== 'RACK_MOVEMENT' && $actualQty > $stockBefore) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Stok {$mat['name']} tidak mencukupi! Sisa stok saat ini "
                    . number_format($stockBefore, 2, ',', '.') . " {$mat['unit']}, sedangkan yang akan diserahkan "
                    . number_format($actualQty, 2, ',', '.') . " {$mat['unit']}. "
                    . "Perbaiki jumlah aktual, atau minta Admin melakukan penyesuaian stok terlebih dahulu."
            ]);
            exit;
        }

        // Cek jika tugas adalah RACK_MOVEMENT (Perpindahan Rak Antar Lokasi)
        if (($task['task_type'] ?? '') === 'RACK_MOVEMENT') {
            $toLocation   = trim($task['to_location'] ?? '');
            $fromLocation = trim($task['from_location'] ?? '');

            // 1. Update Master Lokasi Rak Material
            if (!empty($toLocation)) {
                $stmtUpMat = $pdo->prepare("UPDATE materials SET rack_location = ? WHERE id = ?");
                $stmtUpMat->execute([$toLocation, $materialId]);
            }

            // 2. Jika Gimmick / memiliki record batch, pindahkan batch ke lokasi rak baru
            if (!empty($toLocation)) {
                recordBatchTransfer($pdo, $materialId, (int)($task['batch_id'] ?? 0), $task['batch_no'] ?? null, $fromLocation, $toLocation, $actualQty);
            }

            // 3. Durasi & Takt Time
            $now = date('Y-m-d H:i:s');
            $startedAtStr = $task['started_at'] ?? $task['created_at'];
            $startedAtTime = strtotime($startedAtStr);
            $completedAtTime = strtotime($now);
            $durationSeconds = max(1, $completedAtTime - $startedAtTime);

            // 4. Update status task menjadi COMPLETED
            $stmtUpdateTask = $pdo->prepare("
                UPDATE tasks 
                SET status = 'COMPLETED', 
                    actual_qty = ?, 
                    completion_notes = ?, 
                    photo_path = IFNULL(?, photo_path),
                    started_at = IFNULL(started_at, ?),
                    completed_at = ?,
                    duration_seconds = ?
                WHERE id = ?
            ");
            $stmtUpdateTask->execute([$actualQty, $completionNotes, $photoPathValue, date('Y-m-d H:i:s', $startedAtTime), $now, $durationSeconds, $taskId]);

            // 5. Catat Mutasi Audit Trail TRANSFER_LOCATION
            $stmtMut = $pdo->prepare("
                INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
                VALUES (?, 'TRANSFER_LOCATION', 0, ?, ?, ?, ?, ?, ?)
            ");
            $mutNotes = "Movement Produk dari {$fromLocation} ke {$toLocation} (Qty: {$actualQty} {$mat['unit']}) oleh Operator " . (Auth::name() ?? '');
            if (!empty($completionNotes)) $mutNotes .= " (Catatan: {$completionNotes})";
            $stmtMut->execute([$materialId, $stockBefore, $stockBefore, $task['task_no'], $mutNotes, Auth::id(), $now]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Movement #{$task['task_no']} selesai! Lokasi {$mat['name']} berhasil dipindahkan ke {$toLocation}.",
                'task_no' => $task['task_no'],
                'new_location' => $toLocation,
                'stock' => $stockBefore
            ]);
            exit;
        }

        $stockAfter = $stockBefore - $actualQty;

        // Calculate Duration & Takt Time
        $now = date('Y-m-d H:i:s');
        $startedAtStr = $task['started_at'] ?? $task['created_at'];
        $startedAtTime = strtotime($startedAtStr);
        $completedAtTime = strtotime($now);
        $durationSeconds = max(1, $completedAtTime - $startedAtTime);

        // Update Task with duration and completion timestamp
        $stmtUpdateTask = $pdo->prepare("
            UPDATE tasks 
            SET status = 'COMPLETED', 
                actual_qty = ?, 
                completion_notes = ?, 
                photo_path = IFNULL(?, photo_path),
                started_at = IFNULL(started_at, ?),
                completed_at = ?,
                duration_seconds = ?
            WHERE id = ?
        ");
        $stmtUpdateTask->execute([$actualQty, $completionNotes, $photoPathValue, date('Y-m-d H:i:s', $startedAtTime), $now, $durationSeconds, $taskId]);

        // Update Material Stock
        $stmtUpdateMat = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
        $stmtUpdateMat->execute([$stockAfter, $materialId]);

        // Write Stock Mutation
        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
            VALUES (?, 'TASK_PICKING', ?, ?, ?, ?, ?, ?, ?)
        ");
        $mutNotes = "Pengambilan Tugas #{$task['task_no']} ke {$task['destination']} oleh Operator " . (Auth::name() ?? '');
        if (!empty($completionNotes)) $mutNotes .= " (Catatan: {$completionNotes})";

        $stmtMut->execute([$materialId, -$actualQty, $stockBefore, $stockAfter, $task['task_no'], $mutNotes, Auth::id(), $now]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Tugas #{$task['task_no']} berhasil diselesaikan! Stok {$mat['name']} berkurang {$actualQty} {$mat['unit']}. Sisa stok: {$stockAfter} {$mat['unit']}.",
            'task_no' => $task['task_no'],
            'new_stock' => $stockAfter
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal submit tugas.');
    }
    exit;
}

// 8. CANCEL TASK (Super Admin only)
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Super Admin yang berhak membatalkan penugasan tugas!']);
        exit;
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $taskId = (int)($input['task_id'] ?? 0);

    $stmt = $pdo->prepare("UPDATE tasks SET status = 'CANCELLED' WHERE id = ? AND status != 'COMPLETED'");
    $stmt->execute([$taskId]);

    echo json_encode(['success' => true, 'message' => 'Tugas berhasil dibatalkan']);
    exit;
}

// 8.5 REACTIVATE / SET TASK STATUS (Admin only)
if ($action === 'set_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $taskId = (int)($input['task_id'] ?? 0);
    $taskNo = trim($input['task_no'] ?? '');
    $newStatus = strtoupper(trim($input['status'] ?? 'IN_PROGRESS'));

    if (!in_array($newStatus, ['PENDING', 'IN_PROGRESS', 'CANCELLED'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Status tidak valid']);
        exit;
    }

    if ($taskId > 0) {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, started_at = CASE WHEN ? = 'IN_PROGRESS' AND started_at IS NULL THEN CURRENT_TIMESTAMP ELSE started_at END WHERE id = ?");
        $stmt->execute([$newStatus, $newStatus, $taskId]);
    } elseif (!empty($taskNo)) {
        $stmt = $pdo->prepare("UPDATE tasks SET status = ?, started_at = CASE WHEN ? = 'IN_PROGRESS' AND started_at IS NULL THEN CURRENT_TIMESTAMP ELSE started_at END WHERE task_no = ?");
        $stmt->execute([$newStatus, $newStatus, $taskNo]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Task ID atau Nomor Task tidak valid']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => "Status penugasan berhasil diubah menjadi {$newStatus}!"]);
    exit;
}

// 8.5 CONVERT RACK_MOVEMENT TASK TO INBOUND (Admin & Super Admin)
if ($action === 'convert_to_inbound' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $taskId = (int)($input['task_id'] ?? 0);
    $taskNo = trim($input['task_no'] ?? '');

    if ($taskId <= 0 && empty($taskNo)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Task ID atau Nomor Task tidak valid']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare($taskId > 0 ? "SELECT * FROM tasks WHERE id = ?" : "SELECT * FROM tasks WHERE task_no = ?");
        $stmt->execute([$taskId > 0 ? $taskId : $taskNo]);
        $task = $stmt->fetch();

        if (!$task) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Data task tidak ditemukan']);
            exit;
        }

        $qty = max(0, (float)($task['actual_qty'] > 0 ? $task['actual_qty'] : $task['target_qty']));
        if ($qty <= 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Qty task tidak valid']);
            exit;
        }

        $matId = (int)$task['material_id'];
        $refNo = trim($task['reference_no'] ?? '');
        if (empty($refNo)) {
            if (preg_match('/SJ[:\s]+([^\s\)\|\,]+)/i', $task['completion_notes'] ?? '', $m)) {
                $refNo = $m[1];
            }
        }
        $poNum = !empty($refNo) ? $refNo : '-';
        $supplier = 'SPX / Supplier';
        $location = !empty($task['from_location']) ? $task['from_location'] : 'Gudang Kecil';
        $photoPath = $task['photo_path'] ?? null;
        $receivedBy = $task['assigned_to'] ?? Auth::id();
        $createdAt = $task['created_at'] ?? date('Y-m-d H:i:s');
        $startedAt = $task['started_at'] ?? $createdAt;
        $completedAt = $task['completed_at'] ?? $createdAt;
        $dur = (int)($task['duration_seconds'] ?? 60);

        // Generate inbound_no
        $prefix = 'INB-' . date('Ym', strtotime($createdAt)) . '-';
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

        // 1. Insert into inbound_transactions
        $stmtIn = $pdo->prepare("
            INSERT INTO inbound_transactions (
                inbound_no, po_number, supplier, material_id, qty, notes, 
                photo_path, received_by, started_at, completed_at, duration_seconds, 
                created_at, location, batch_no, exp_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $inNotes = "Penerimaan Barang Masuk (Putaway)";
        if (!empty($task['notes'])) $inNotes .= " - {$task['notes']}";
        $stmtIn->execute([
            $inboundNo, $poNum, $supplier, $matId, $qty, $inNotes,
            $photoPath, $receivedBy, $startedAt, $completedAt, $dur,
            $createdAt, $location, $task['batch_no'] ?? null, $task['exp_date'] ?? null
        ]);

        // 2. Add stock to materials master & restore location
        $stmtMat = $pdo->prepare("SELECT current_stock FROM materials WHERE id = ?");
        $stmtMat->execute([$matId]);
        $stockBefore = (float)$stmtMat->fetchColumn();
        $stockAfter = $stockBefore + $qty;

        $stmtUpMat = $pdo->prepare("UPDATE materials SET current_stock = ?, rack_location = ? WHERE id = ?");
        $stmtUpMat->execute([$stockAfter, $location, $matId]);

        // 3. Delete old TRANSFER_LOCATION mutation if any
        $stmtDelMut = $pdo->prepare("DELETE FROM stock_mutations WHERE reference_no = ? AND type = 'TRANSFER_LOCATION'");
        $stmtDelMut->execute([$task['task_no']]);

        // 4. Insert INBOUND stock mutation
        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id, created_at)
            VALUES (?, 'INBOUND', ?, ?, ?, ?, ?, ?, ?)
        ");
        $mutNotes = "Penerimaan Barang Masuk (PO/SJ: {$poNum}) [Lokasi: {$location}]";
        $stmtMut->execute([$matId, $qty, $stockBefore, $stockAfter, $inboundNo, $mutNotes, $receivedBy, $createdAt]);

        // 5. Delete task
        $stmtDelTask = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
        $stmtDelTask->execute([$task['id']]);

        $pdo->commit();

        echo json_encode([
            'success' => true, 
            'message' => "Tugas {$task['task_no']} berhasil dipindahkan ke Inbound (Barang Masuk) #{$inboundNo}! Stok master bertambah +{$qty}.",
            'inbound_no' => $inboundNo
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal memindahkan task ke inbound.');
    }
    exit;
}

// 9. DELETE TASK (Admin & Super Admin)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $taskId = (int)($input['task_id'] ?? 0);
    $taskNo = trim($input['task_no'] ?? '');

    if ($taskId <= 0 && empty($taskNo)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Task ID atau Nomor Task tidak valid']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare($taskId > 0 ? "SELECT * FROM tasks WHERE id = ?" : "SELECT * FROM tasks WHERE task_no = ?");
        $stmt->execute([$taskId > 0 ? $taskId : $taskNo]);
        $task = $stmt->fetch();

        if ($task) {
            // If the task was completed, restore the stock for PICKING
            if ($task['status'] === 'COMPLETED' && (float)$task['actual_qty'] > 0) {
                if (($task['task_type'] ?? '') !== 'RACK_MOVEMENT') {
                    $matId = (int)$task['material_id'];
                    $qty = (float)$task['actual_qty'];

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
                    $mutNotes = "Hapus Penugasan Selesai #{$task['task_no']} (Stok dikembalikan +{$qty})";
                    $stmtMut->execute([$matId, $qty, $currentStock, $newStock, $task['task_no'], $mutNotes, Auth::id(), date('Y-m-d H:i:s')]);
                } else {
                    // For RACK_MOVEMENT: clean up any TRANSFER_LOCATION mutations and restore rack location if needed
                    $stmtDelMut = $pdo->prepare("DELETE FROM stock_mutations WHERE reference_no = ? AND type = 'TRANSFER_LOCATION'");
                    $stmtDelMut->execute([$task['task_no']]);
                    if (!empty($task['from_location'])) {
                        $targetLoc = !empty($task['to_location']) ? $task['to_location'] : $task['destination'];
                        $stmtUpLoc = $pdo->prepare("UPDATE materials SET rack_location = ? WHERE id = ? AND rack_location = ?");
                        $stmtUpLoc->execute([$task['from_location'], $task['material_id'], $targetLoc]);
                    }
                }
            }

            $stmtDel = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
            $stmtDel->execute([$task['id']]);
        }

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => "Tugas #" . ($task['task_no'] ?? $taskId) . " berhasil dihapus"]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        apiFail($e, 'Gagal menghapus tugas.');
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi task tidak valid']);
