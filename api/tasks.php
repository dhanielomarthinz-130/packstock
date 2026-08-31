<?php
// api/tasks.php - Task Assignment, Bulk Assignment & Mobile Execution API (with Excel Task Import)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

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
    $forMe    = isset($_GET['my_tasks']) && $_GET['my_tasks'] === '1';

    $query = "
        SELECT t.*, 
               m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location, m.current_stock as material_stock,
               u_to.name as operator_name, u_to.username as operator_username, u_to.shift as operator_shift,
               u_by.name as creator_name
        FROM tasks t
        JOIN materials m ON t.material_id = m.id
        JOIN users u_to ON t.assigned_to = u_to.id
        JOIN users u_by ON t.assigned_by = u_by.id
        WHERE 1=1
    ";
    $params = [];

    if (Auth::role() === 'operator' || $forMe) {
        $query .= " AND t.assigned_to = ?";
        $params[] = Auth::id();
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
        $query .= " AND (t.task_no LIKE ? OR m.name LIKE ? OR m.code LIKE ? OR t.destination LIKE ? OR u_to.name LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term, $term]);
    }

    $query .= " ORDER BY (t.priority = 'URGENT' OR t.priority = 'CRITICAL') DESC, (t.status = 'PENDING') DESC, (t.status = 'IN_PROGRESS') DESC, t.created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $tasks]);
    exit;
}

// 3. CREATE SINGLE TASK (Admin only)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $materialId  = (int)($input['material_id'] ?? 0);
    $targetQty   = (int)($input['target_qty'] ?? 0);
    $priority    = strtoupper(trim($input['priority'] ?? 'NORMAL'));
    $destination = trim($input['destination'] ?? 'Line Packing');
    $assignedTo  = (int)($input['assigned_to'] ?? 0);
    $notes       = trim($input['notes'] ?? '');

    if ($materialId <= 0 || $targetQty <= 0 || empty($destination) || $assignedTo <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Material, Target Qty (> 0), Tujuan Line, dan Operator PIC wajib diisi!']);
        exit;
    }

    // Validate available stock
    $stmtMat = $pdo->prepare("SELECT id, name, current_stock FROM materials WHERE id = ?");
    $stmtMat->execute([$materialId]);
    $mat = $stmtMat->fetch();

    if (!$mat) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Material packaging tidak ditemukan!']);
        exit;
    }

    if ($targetQty > (float)$mat['current_stock']) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => "Target Qty ({$targetQty}) tidak bisa lebih besar dari Sisa Stok yang tersedia (" . number_format($mat['current_stock'], 0, ',', '.') . ") untuk material {$mat['name']}!"
        ]);
        exit;
    }

    $prefix = 'TSK-' . date('Ym') . '-';
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE task_no LIKE ?");
    $stmtCount->execute([$prefix . '%']);
    $nextNum = (int)$stmtCount->fetchColumn() + 1;
    $taskNo = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
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
        echo json_encode(['success' => false, 'message' => 'Gagal membuat tugas: ' . $e->getMessage()]);
    }
    exit;
}

// 3.1 GET SINGLE TASK (Admin only for Edit)
if ($action === 'get') {
    Auth::requireAdmin();
    $taskId = (int)($_GET['id'] ?? 0);

    $stmt = $pdo->prepare("
        SELECT t.*, 
               m.code as material_code, m.name as material_name, m.current_stock as material_stock, m.rack_location,
               u.name as operator_name
        FROM tasks t
        JOIN materials m ON t.material_id = m.id
        JOIN users u ON t.assigned_to = u.id
        WHERE t.id = ?
    ");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();

    if (!$task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Task tidak ditemukan']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $task]);
    exit;
}

// 3.2 UPDATE / EDIT TASK (Admin only - Reassign User / Change Product)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $taskId      = (int)($input['task_id'] ?? 0);
    $materialId  = (int)($input['material_id'] ?? 0);
    $targetQty   = (int)($input['target_qty'] ?? 0);
    $priority    = strtoupper(trim($input['priority'] ?? 'NORMAL'));
    $destination = trim($input['destination'] ?? '');
    $assignedTo  = (int)($input['assigned_to'] ?? 0);
    $notes       = trim($input['notes'] ?? '');

    if ($taskId <= 0 || $materialId <= 0 || $targetQty <= 0 || empty($destination) || $assignedTo <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Material, Qty Target, Tujuan Antar, dan Operator PIC wajib diisi!']);
        exit;
    }

    $stmtCheck = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmtCheck->execute([$taskId]);
    $existingTask = $stmtCheck->fetch();

    if (!$existingTask) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Task tidak ditemukan!']);
        exit;
    }

    if ($existingTask['status'] === 'COMPLETED') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Task yang sudah selesai (Completed) tidak dapat diedit lagi!']);
        exit;
    }

    try {
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

        echo json_encode(['success' => true, 'message' => 'Penugasan task berhasil diperbarui!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui task: ' . $e->getMessage()]);
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
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE task_no LIKE ?");
        $stmtCount->execute([$prefix . '%']);
        $nextNum = (int)$stmtCount->fetchColumn() + 1;

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
            $targetQty   = (int)($t['target_qty'] ?? 0);
            $priority    = strtoupper(trim($t['priority'] ?? 'NORMAL'));
            if (!in_array($priority, ['NORMAL', 'URGENT', 'CRITICAL'])) $priority = 'NORMAL';
            $destination = trim($t['destination'] ?? 'Line Packing');
            $assignedTo  = (int)($t['assigned_to'] ?? 0);
            $notes       = trim($t['notes'] ?? '');

            if ($materialId <= 0 || $assignedTo <= 0) continue;

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
                    'message' => "Target Qty ({$targetQty}) tidak bisa lebih besar dari Sisa Stok (" . number_format($mat['current_stock'], 0, ',', '.') . ") untuk material {$mat['name']} pada baris ke-" . ($idx + 1) . "!"
                ]);
                exit;
            }

            $taskNo = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
            $nextNum++;

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
        echo json_encode(['success' => false, 'message' => 'Gagal membuat tugas bulk: ' . $e->getMessage()]);
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
            echo json_encode(['success' => false, 'message' => 'Silakan upload file Excel atau paste data tabel tugas.']);
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
        echo json_encode(['success' => false, 'message' => 'Tidak ada data tugas yang terbaca.']);
        exit;
    }

    // Header index detection
    $headers = array_map(function($h) {
        return strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h)));
    }, $rows[0]);

    $itemNoIdx      = -1;
    $qtyIdx         = -1;
    $destIdx        = -1;
    $opIdx          = -1;
    $priorityIdx    = -1;
    $notesIdx       = -1;

    foreach ($headers as $idx => $h) {
        if (in_array($h, ['item no', 'item no.', 'item_no', 'item number', 'kode', 'kode item', 'code', 'sku'])) $itemNoIdx = $idx;
        elseif (in_array($h, ['target qty', 'target_qty', 'qty target', 'qty', 'target', 'jumlah', 'jumlah ambil'])) $qtyIdx = $idx;
        elseif (in_array($h, ['destination', 'tujuan', 'line', 'tujuan line', 'departemen'])) $destIdx = $idx;
        elseif (in_array($h, ['operator', 'operator username', 'assigned to', 'operator pic', 'username operator', 'pic'])) $opIdx = $idx;
        elseif (in_array($h, ['priority', 'prioritas', 'tingkat'])) $priorityIdx = $idx;
        elseif (in_array($h, ['notes', 'catatan', 'keterangan', 'instruksi'])) $notesIdx = $idx;
    }

    $hasHeader = ($itemNoIdx !== -1 || $qtyIdx !== -1 || $destIdx !== -1);
    if (!$hasHeader) {
        $itemNoIdx = 0;
        $qtyIdx    = 1;
        $destIdx   = 2;
        $opIdx     = 3;
        $priorityIdx = 4;
        $notesIdx  = 5;
        $startRow  = 0;
    } else {
        if ($itemNoIdx === -1) $itemNoIdx = 0;
        if ($qtyIdx === -1) $qtyIdx = 1;
        if ($destIdx === -1) $destIdx = 2;
        if ($opIdx === -1) $opIdx = 3;
        $startRow = 1;
    }

    // Load materials and operators dictionary for fast lookup
    $materialsMap = [];
    $stmtMat = $pdo->query("SELECT id, code, name, unit, current_stock, rack_location FROM materials");
    while ($m = $stmtMat->fetch()) {
        $materialsMap[strtoupper($m['code'])] = $m;
    }

    $operatorsMap = [];
    $defaultOperator = null;
    $stmtOp = $pdo->query("SELECT id, username, name, shift FROM users WHERE role = 'operator'");
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
        $qtyClean = preg_replace('/[^0-9]/', '', $qtyRaw);
        $targetQty = is_numeric($qtyClean) ? max(1, (int)$qtyClean) : 1;
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
            if ($targetQty > (int)$mat['current_stock']) {
                $warningMessage = "Peringatan: Stok tersedia ({$mat['current_stock']} {$mat['unit']}) kurang dari target ambil ({$targetQty} {$mat['unit']}).";
            }
        }

        $parsedTasks[] = [
            'row_num' => $i + 1,
            'item_no' => $itemNo,
            'material_id' => $mat ? (int)$mat['id'] : 0,
            'material_name' => $mat ? $mat['name'] : 'Item Tidak Ditemukan',
            'material_unit' => $mat ? $mat['unit'] : 'Pcs',
            'material_stock' => $mat ? (int)$mat['current_stock'] : 0,
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
    $actualQty       = (int)($input['actual_qty'] ?? 0);
    $completionNotes = trim($input['completion_notes'] ?? '');
    $photoPathValue  = handleUploadedTaskPhotos();

    if ($taskId <= 0 || $actualQty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Jumlah riil barang yang diambil wajib diisi lebih dari 0!']);
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

        if ($task['status'] === 'COMPLETED') {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tugas ini sudah selesai disubmit sebelumnya.']);
            exit;
        }

        $materialId = (int)$task['material_id'];

        $stmtMat = $pdo->prepare("SELECT id, name, code, unit, current_stock FROM materials WHERE id = ?");
        $stmtMat->execute([$materialId]);
        $mat = $stmtMat->fetch();

        if (!$mat) {
            $pdo->rollBack();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Material tidak ditemukan']);
            exit;
        }

        $stockBefore = (float)$mat['current_stock'];
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
        echo json_encode(['success' => false, 'message' => 'Gagal submit tugas: ' . $e->getMessage()]);
    }
    exit;
}

// 8. CANCEL TASK (Admin only)
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $taskId = (int)($input['task_id'] ?? 0);

    $stmt = $pdo->prepare("UPDATE tasks SET status = 'CANCELLED' WHERE id = ? AND status != 'COMPLETED'");
    $stmt->execute([$taskId]);

    echo json_encode(['success' => true, 'message' => 'Tugas berhasil dibatalkan']);
    exit;
}

// 9. DELETE TASK (Admin only)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $taskId = (int)($input['task_id'] ?? 0);

    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->execute([$taskId]);

    echo json_encode(['success' => true, 'message' => 'Tugas berhasil dihapus']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi task tidak valid']);
