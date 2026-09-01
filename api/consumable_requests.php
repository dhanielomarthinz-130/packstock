<?php
// api/consumable_requests.php - Consumable Material Request API (Fulfillment & Approval)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

// Helper function to handle multiple photos upload
function handleConsumablePhotosUpload($files, $rawInputBase64 = null) {
    $uploadDir = __DIR__ . '/../uploads/consumable_requests/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $photoPaths = [];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    // 1. If uploaded via multipart/form-data
    if (!empty($files) && !empty($files['name'])) {
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;
        for ($i = 0; $i < $fileCount; $i++) {
            $err = is_array($files['error']) ? $files['error'][$i] : $files['error'];
            if ($err === UPLOAD_ERR_OK) {
                $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $fileName = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (empty($ext)) $ext = 'jpg';

                if (in_array($ext, $allowedExtensions)) {
                    $newFileName = 'req_' . date('Ymd_His') . '_' . substr(md5(uniqid() . $i), 0, 8) . '.' . $ext;
                    $destPath = $uploadDir . $newFileName;
                    if (move_uploaded_file($tmpName, $destPath)) {
                        $photoPaths[] = 'uploads/consumable_requests/' . $newFileName;
                    }
                }
            }
        }
    }

    // 2. If uploaded via Base64 JSON array
    if (!empty($rawInputBase64) && is_array($rawInputBase64)) {
        foreach ($rawInputBase64 as $idx => $b64) {
            if (is_string($b64) && strpos($b64, 'data:image') === 0) {
                if (preg_match('/^data:image\/(\w+);base64,/', $b64, $type)) {
                    $ext = strtolower($type[1]);
                    if ($ext === 'jpeg') $ext = 'jpg';
                    if (in_array($ext, $allowedExtensions)) {
                        $data = substr($b64, strpos($b64, ',') + 1);
                        $data = base64_decode($data);
                        if ($data !== false) {
                            $newFileName = 'req_' . date('Ymd_His') . '_' . substr(md5(uniqid() . $idx), 0, 8) . '.' . $ext;
                            $destPath = $uploadDir . $newFileName;
                            if (file_put_contents($destPath, $data)) {
                                $photoPaths[] = 'uploads/consumable_requests/' . $newFileName;
                            }
                        }
                    }
                }
            } elseif (is_string($b64) && strpos($b64, 'uploads/') === 0) {
                $photoPaths[] = $b64;
            }
        }
    }

    return !empty($photoPaths) ? json_encode($photoPaths) : null;
}

// 1. LIST CONSUMABLE REQUESTS
if ($action === 'list') {
    $status      = trim($_GET['status'] ?? '');
    $destination = trim($_GET['destination'] ?? '');
    $date        = trim($_GET['date'] ?? '');
    $search      = trim($_GET['search'] ?? '');
    $limit       = max(1, min(200, (int)($_GET['limit'] ?? 100)));

    $query = "
        SELECT r.*,
               u.name as requester_name,
               u.username as requester_username,
               u.role as requester_role,
               u.shift as requester_shift,
               adm.name as approver_name,
               adm.username as approver_username
        FROM consumable_requests r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN users adm ON r.approved_by = adm.id
        WHERE 1=1
    ";
    $params = [];

    // If regular operator / operator fulfillment (non-admin), only see their own requests
    if (!Auth::isAdmin()) {
        $query .= " AND r.user_id = ?";
        $params[] = Auth::id();
    }

    if (!empty($status) && $status !== 'ALL') {
        $query .= " AND r.status = ?";
        $params[] = $status;
    }

    if (!empty($destination) && $destination !== 'ALL') {
        $query .= " AND r.destination LIKE ?";
        $params[] = "%{$destination}%";
    }

    if (!empty($date)) {
        $query .= " AND r.created_at LIKE ?";
        $params[] = "{$date}%";
    }

    if (!empty($search)) {
        $query .= " AND (r.request_no LIKE ? OR r.destination LIKE ? OR r.notes LIKE ? OR u.name LIKE ? OR u.username LIKE ?)";
        $term = "%{$search}%";
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $query .= " ORDER BY (r.status = 'PENDING') DESC, r.created_at DESC LIMIT " . $limit;

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    // Fetch items and fulfillment / handover records for all requests
    if (!empty($requests)) {
        $requestIds = array_column($requests, 'id');
        $inPlaceholders = implode(',', array_fill(0, count($requestIds), '?'));

        $stmtItems = $pdo->prepare("
            SELECT ri.*,
                   m.code as material_code,
                   m.name as material_name,
                   m.unit as material_unit,
                   m.rack_location,
                   m.current_stock,
                   m.category as material_category
            FROM consumable_request_items ri
            JOIN materials m ON ri.material_id = m.id
            WHERE ri.request_id IN ($inPlaceholders)
            ORDER BY ri.id ASC
        ");
        $stmtItems->execute($requestIds);
        $allItems = $stmtItems->fetchAll();

        $itemsByRequest = [];
        foreach ($allItems as $item) {
            $itemsByRequest[$item['request_id']][] = $item;
        }

        // Fetch related tasks for picking & handover
        $tasksByRequest = [];
        $requestNos = array_column($requests, 'request_no');
        $taskIds = array_filter(array_column($requests, 'task_id'));
        $outboundIds = array_filter(array_column($requests, 'outbound_id'));

        if (!empty($requestNos)) {
            $likeClauses = [];
            $taskParams = [];
            foreach ($requestNos as $rNo) {
                $likeClauses[] = "t.notes LIKE ?";
                $taskParams[] = "%#" . $rNo . "%";
            }
            if (!empty($taskIds)) {
                $inTaskPlaceholders = implode(',', array_fill(0, count($taskIds), '?'));
                $taskSql = "
                    SELECT t.*, u.name as operator_name, u.username as operator_username, u.shift as operator_shift
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    WHERE t.id IN ($inTaskPlaceholders) OR (" . implode(' OR ', $likeClauses) . ")
                    ORDER BY t.id ASC
                ";
                $taskParams = array_merge(array_values($taskIds), $taskParams);
            } else {
                $taskSql = "
                    SELECT t.*, u.name as operator_name, u.username as operator_username, u.shift as operator_shift
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    WHERE " . implode(' OR ', $likeClauses) . "
                    ORDER BY t.id ASC
                ";
            }
            $stmtT = $pdo->prepare($taskSql);
            $stmtT->execute($taskParams);
            $allTasks = $stmtT->fetchAll();

            foreach ($allTasks as $tsk) {
                foreach ($requests as $r) {
                    if ((!empty($r['task_id']) && $r['task_id'] == $tsk['id']) || (!empty($tsk['notes']) && strpos($tsk['notes'], '#' . $r['request_no']) !== false)) {
                        $tasksByRequest[$r['id']][] = $tsk;
                    }
                }
            }
        }

        // Fetch related direct outbound transactions
        $outboundsByRequest = [];
        if (!empty($requestNos)) {
            $likeClausesOut = [];
            $outParams = [];
            foreach ($requestNos as $rNo) {
                $likeClausesOut[] = "o.reason LIKE ?";
                $likeClausesOut[] = "o.notes LIKE ?";
                $outParams[] = "%#" . $rNo . "%";
                $outParams[] = "%#" . $rNo . "%";
            }
            if (!empty($outboundIds)) {
                $inOutPlaceholders = implode(',', array_fill(0, count($outboundIds), '?'));
                $outSql = "
                    SELECT o.*
                    FROM outbound_transactions o
                    WHERE o.id IN ($inOutPlaceholders) OR (" . implode(' OR ', $likeClausesOut) . ")
                    ORDER BY o.id ASC
                ";
                $outParams = array_merge(array_values($outboundIds), $outParams);
            } else {
                $outSql = "
                    SELECT o.*
                    FROM outbound_transactions o
                    WHERE " . implode(' OR ', $likeClausesOut) . "
                    ORDER BY o.id ASC
                ";
            }
            $stmtO = $pdo->prepare($outSql);
            $stmtO->execute($outParams);
            $allOuts = $stmtO->fetchAll();

            foreach ($allOuts as $out) {
                foreach ($requests as $r) {
                    if ((!empty($r['outbound_id']) && $r['outbound_id'] == $out['id']) || (!empty($out['reason']) && strpos($out['reason'], '#' . $r['request_no']) !== false) || (!empty($out['notes']) && strpos($out['notes'], '#' . $r['request_no']) !== false)) {
                        $outboundsByRequest[$r['id']][] = $out;
                    }
                }
            }
        }

        foreach ($requests as &$req) {
            $req['items'] = $itemsByRequest[$req['id']] ?? [];
            $req['total_items'] = count($req['items']);
            $req['total_qty'] = array_sum(array_column($req['items'], 'qty'));
            $photosArr = [];
            if (!empty($req['photos'])) {
                $decoded = json_decode($req['photos'], true);
                if (is_array($decoded)) {
                    $photosArr = $decoded;
                } else {
                    $photosArr = [$req['photos']];
                }
            }
            $req['photos_list'] = $photosArr;

            // Fulfillment / Handover Synthesis
            $relatedTasks = $tasksByRequest[$req['id']] ?? [];
            $relatedOutbounds = $outboundsByRequest[$req['id']] ?? [];

            $handoverPhotos = [];
            $penyerahName = null;
            $penerimaName = $req['requester_name'] ?? 'Line Fulfillment';
            $handoverTime = null;
            $handoverNotes = null;
            $isTaskCompleted = false;
            $isOutboundDirect = !empty($relatedOutbounds);

            if (!empty($relatedTasks)) {
                $totalTasks = count($relatedTasks);
                $completedTasks = 0;
                foreach ($relatedTasks as $t) {
                    if ($t['status'] === 'COMPLETED') {
                        $completedTasks++;
                        if (!empty($t['photo_path'])) {
                            $dec = json_decode($t['photo_path'], true);
                            if (is_array($dec)) {
                                $handoverPhotos = array_merge($handoverPhotos, $dec);
                            } else {
                                $handoverPhotos[] = $t['photo_path'];
                            }
                        }
                        if (!$penyerahName && !empty($t['operator_name'])) {
                            $penyerahName = $t['operator_name'] . (!empty($t['operator_shift']) ? ' (' . $t['operator_shift'] . ')' : '');
                        }
                        if (!empty($t['completed_at'])) {
                            $handoverTime = $t['completed_at'];
                        }
                        if (!empty($t['completion_notes'])) {
                            $handoverNotes = $t['completion_notes'];
                        }
                    } elseif (!$penyerahName && !empty($t['operator_name'])) {
                        $penyerahName = $t['operator_name'] . (!empty($t['operator_shift']) ? ' (' . $t['operator_shift'] . ')' : '');
                    }
                }
                if ($totalTasks > 0 && $completedTasks === $totalTasks) {
                    $isTaskCompleted = true;
                }
            }

            if (!empty($relatedOutbounds)) {
                foreach ($relatedOutbounds as $o) {
                    if (!empty($o['photo_path'])) {
                        $dec = json_decode($o['photo_path'], true);
                        if (is_array($dec)) {
                            $handoverPhotos = array_merge($handoverPhotos, $dec);
                        } else {
                            $handoverPhotos[] = $o['photo_path'];
                        }
                    }
                    if (!$penyerahName && !empty($o['issued_by'])) {
                        $penyerahName = $o['issued_by'];
                    }
                    if (!$handoverTime && !empty($o['completed_at'])) {
                        $handoverTime = $o['completed_at'];
                    } elseif (!$handoverTime && !empty($o['created_at'])) {
                        $handoverTime = $o['created_at'];
                    }
                    if (!$handoverNotes && !empty($o['notes'])) {
                        $handoverNotes = $o['notes'];
                    }
                }
            }

            // Fallback penyerah to approver if approved
            if (!$penyerahName && !empty($req['approver_name'])) {
                $penyerahName = $req['approver_name'] . ' (Admin Gudang)';
            }

            // Determine stage:
            // 1 = PENDING (Diajukan)
            // 2 = APPROVED (Disetujui - Dalam Proses Picking Gudang)
            // 3 = COMPLETED (Selesai Diserahkan ke Pemohon / Handover Done)
            // -1 = REJECTED (Ditolak)
            // -2 = CANCELLED (Dibatalkan)
            $stage = 1;
            $stageText = 'Diajukan (Menunggu ACC)';
            if ($req['status'] === 'PENDING') {
                $stage = 1;
                $stageText = 'Menunggu ACC Admin';
            } elseif ($req['status'] === 'REJECTED') {
                $stage = -1;
                $stageText = 'Ditolak Admin';
            } elseif ($req['status'] === 'CANCELLED') {
                $stage = -2;
                $stageText = 'Dibatalkan';
            } elseif ($req['status'] === 'APPROVED' || $req['status'] === 'COMPLETED') {
                if ($isTaskCompleted || $isOutboundDirect || $req['status'] === 'COMPLETED') {
                    $stage = 3;
                    $stageText = 'Selesai Diserahkan (Handover)';
                } else {
                    $stage = 2;
                    $stageText = 'Disetujui (Proses Picking Gudang)';
                }
            }

            $req['handover_info'] = [
                'is_handed_over'    => ($stage === 3),
                'stage'             => $stage,
                'stage_label'       => $stageText,
                'penyerah_name'     => $penyerahName ?: 'Tim Gudang Consumable',
                'penerima_name'     => $penerimaName,
                'handover_time'     => $handoverTime ?: $req['approved_at'],
                'handover_notes'    => $handoverNotes,
                'handover_photos'   => array_values(array_unique(array_filter($handoverPhotos))),
                'related_tasks'     => $relatedTasks,
                'related_outbounds' => $relatedOutbounds
            ];
        }
        unset($req);

        // Filter untuk Operator (Fulfillment):
        // 1. Pengajuan yang belum selesai (Stage 1 / Menunggu ACC & Stage 2 / Proses Picking) SELALU tampil (kapanpun dibuat).
        // 2. Pengajuan yang sudah "Selesai Diserahkan" (Stage 3) atau Dibatalkan/Ditolak HANYA tampil untuk hari ini (CURDATE). Untuk hari besoknya otomatis hilang dari tab riwayat aktif.
        if (!Auth::isAdmin() && empty($date) && empty($_GET['all_history'])) {
            $today = date('Y-m-d');
            $requests = array_values(array_filter($requests, function($r) use ($today) {
                $ho = $r['handover_info'] ?? [];
                $stage = $ho['stage'] ?? 1;
                // Belum selesai serah terima -> Selalu tampil
                if ($stage === 1 || $stage === 2) {
                    return true;
                }
                // Sudah selesai serah terima / ditolak / dibatalkan -> Hanya tampil jika hari ini
                $refTime = $ho['handover_time'] ?? $r['approved_at'] ?? $r['created_at'] ?? '';
                $refDate = !empty($refTime) ? substr($refTime, 0, 10) : '';
                return ($refDate === $today);
            }));
        }
    }

    echo json_encode(['success' => true, 'data' => $requests]);
    exit;
}

// 2. CREATE CONSUMABLE REQUEST (Operator / Operator Fulfillment)
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = !empty($rawInput) ? json_decode($rawInput, true) : [];
    if (empty($input) && !empty($_POST)) {
        $input = $_POST;
    }

    $destination = trim($input['destination'] ?? '');
    $priority    = trim($input['priority'] ?? 'NORMAL');
    $notes       = trim($input['notes'] ?? '');
    $items       = $input['items'] ?? [];

    if (is_string($items)) {
        $items = json_decode($items, true) ?? [];
    }

    if (empty($destination)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tujuan Brand / Line / Departemen wajib diisi!']);
        exit;
    }

    if (empty($items) || !is_array($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Daftar item permintaan consumable masih kosong. Silakan pilih minimal 1 packaging material.']);
        exit;
    }

    if (!in_array($priority, ['NORMAL', 'URGENT', 'CRITICAL'])) {
        $priority = 'NORMAL';
    }

    $photosJson = handleConsumablePhotosUpload($_FILES['photos'] ?? null, $input['photos'] ?? null);

    $userId = Auth::id();
    $userName = Auth::name() ?? 'Operator';

    try {
        $pdo->beginTransaction();

        $prefix = 'REQ-' . date('Ym') . '-';
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM consumable_requests WHERE request_no LIKE ?");
        $stmtCount->execute([$prefix . '%']);
        $nextNum = (int)$stmtCount->fetchColumn() + 1;
        $requestNo = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        $now = date('Y-m-d H:i:s');

        $stmtReq = $pdo->prepare("
            INSERT INTO consumable_requests (request_no, user_id, destination, priority, notes, photos, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'PENDING', ?, ?)
        ");
        $stmtReq->execute([$requestNo, $userId, $destination, $priority, $notes, $photosJson, $now, $now]);
        $requestId = (int)$pdo->lastInsertId();

        $stmtItem = $pdo->prepare("
            INSERT INTO consumable_request_items (request_id, material_id, qty, notes, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");

        $totalQty = 0;
        $validItemCount = 0;

        foreach ($items as $it) {
            $matId = (int)($it['material_id'] ?? 0);
            $qty   = max(0, parseNumberDecimal($it['qty'] ?? 0));
            $itemNotes = trim($it['notes'] ?? '');

            if ($matId <= 0 || $qty <= 0) continue;

            // Validate stock availability
            $stmtMat = $pdo->prepare("SELECT id, name, current_stock, unit FROM materials WHERE id = ?");
            $stmtMat->execute([$matId]);
            $mat = $stmtMat->fetch();

            if (!$mat) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Material dengan ID #{$matId} tidak ditemukan!"]);
                exit;
            }

            $currentStock = (float)$mat['current_stock'];
            if ($qty > $currentStock) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Jumlah permintaan untuk \"{$mat['name']}\" ({$qty} {$mat['unit']}) tidak boleh melebihi sisa stok di gudang ({$currentStock} {$mat['unit']})!"]);
                exit;
            }

            $stmtItem->execute([$requestId, $matId, $qty, $itemNotes, $now]);
            $totalQty += $qty;
            $validItemCount++;
        }

        if ($validItemCount === 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada item material valid yang diajukan (jumlah harus > 0).']);
            exit;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Permintaan Consumable #{$requestNo} berhasil dikirim ke Admin! Menunggu persetujuan (ACC).",
            'request_no' => $requestNo,
            'request_id' => $requestId,
            'total_items' => $validItemCount,
            'total_qty' => $totalQty
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengirim pengajuan consumable: ' . $e->getMessage()]);
    }
    exit;
}

// 3. APPROVE CONSUMABLE REQUEST (Admin only - ACC)
if ($action === 'approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $requestId    = (int)($input['request_id'] ?? 0);
    $approvalType = trim($input['approval_type'] ?? 'DIRECT_OUTBOUND'); // 'DIRECT_OUTBOUND' or 'CREATE_TASK'
    $assignedTo   = (int)($input['assigned_to'] ?? 0);
    $adminNotes   = trim($input['admin_notes'] ?? '');

    if ($requestId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Request tidak valid']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT r.*, u.name as requester_name
        FROM consumable_requests r
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ?
    ");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();

    if (!$req) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data pengajuan consumable tidak ditemukan']);
        exit;
    }

    if ($req['status'] !== 'PENDING') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Pengajuan ini sudah berstatus {$req['status']} dan tidak dapat di-ACC ulang."]);
        exit;
    }

    // Fetch items
    $stmtItems = $pdo->prepare("
        SELECT ri.*, m.name as material_name, m.code as material_code, m.current_stock, m.unit as material_unit
        FROM consumable_request_items ri
        JOIN materials m ON ri.material_id = m.id
        WHERE ri.request_id = ?
    ");
    $stmtItems->execute([$requestId]);
    $items = $stmtItems->fetchAll();

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Daftar item pengajuan kosong']);
        exit;
    }

    $adminId = Auth::id();
    $adminName = Auth::name() ?? 'Admin';
    $now = date('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        if ($approvalType === 'CREATE_TASK') {
            if ($assignedTo <= 0) {
                $pdo->rollBack();
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Silakan pilih operator PIC yang ditugaskan untuk mengambil barang.']);
                exit;
            }

            // Create Picking Tasks for each item
            $createdTaskNos = [];
            $prefixTask = 'TSK-' . date('Ym') . '-';
            $stmtTaskCount = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE task_no LIKE ?");
            $stmtTaskCount->execute([$prefixTask . '%']);
            $nextTaskNum = (int)$stmtTaskCount->fetchColumn() + 1;

            $stmtInsertTask = $pdo->prepare("
                INSERT INTO tasks (task_no, material_id, target_qty, actual_qty, priority, destination, assigned_to, assigned_by, status, notes, created_at)
                VALUES (?, ?, ?, 0, ?, ?, ?, ?, 'PENDING', ?, ?)
            ");

            $firstTaskId = null;

            foreach ($items as $it) {
                $taskNo = $prefixTask . str_pad($nextTaskNum++, 4, '0', STR_PAD_LEFT);
                $taskNotes = "ACC Pengajuan Consumable #{$req['request_no']} oleh {$adminName} (Pemohon: {$req['requester_name']})";
                if (!empty($adminNotes)) $taskNotes .= " - Catatan: {$adminNotes}";
                if (!empty($it['notes'])) $taskNotes .= " - Item note: {$it['notes']}";

                $stmtInsertTask->execute([
                    $taskNo,
                    $it['material_id'],
                    $it['qty'],
                    $req['priority'] ?? 'NORMAL',
                    $req['destination'],
                    $assignedTo,
                    $adminId,
                    $taskNotes,
                    $now
                ]);

                $lastId = (int)$pdo->lastInsertId();
                if (!$firstTaskId) $firstTaskId = $lastId;
                $createdTaskNos[] = $taskNo;
            }

            // Update request
            $stmtUp = $pdo->prepare("
                UPDATE consumable_requests 
                SET status = 'APPROVED', approved_by = ?, admin_notes = ?, approved_at = ?, task_id = ?, updated_at = ?
                WHERE id = ?
            ");
            $combinedAdminNotes = "Disetujui via Penugasan Operator Picking (" . implode(', ', $createdTaskNos) . ")";
            if (!empty($adminNotes)) $combinedAdminNotes .= " | {$adminNotes}";
            $stmtUp->execute([$adminId, $combinedAdminNotes, $now, $firstTaskId, $now, $requestId]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Pengajuan #{$req['request_no']} berhasil di-ACC! " . count($createdTaskNos) . " Tugas Picking telah diteruskan ke operator.",
                'task_nos' => $createdTaskNos
            ]);
            exit;

        } else {
            // DIRECT_OUTBOUND: Potong Stok Langsung & Catat Outbound
            $prefixOut = 'OUT-' . date('Ym') . '-';
            $stmtOutCount = $pdo->prepare("SELECT COUNT(*) FROM outbound_transactions WHERE outbound_no LIKE ?");
            $stmtOutCount->execute([$prefixOut . '%']);
            $nextOutNum = (int)$stmtOutCount->fetchColumn() + 1;

            $stmtInsertOut = $pdo->prepare("
                INSERT INTO outbound_transactions (outbound_no, material_id, qty, destination, issued_by, reason, notes, started_at, completed_at, duration_seconds)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 60)
            ");

            $stmtUpMat = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");

            $stmtMut = $pdo->prepare("
                INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id)
                VALUES (?, 'OUTBOUND', ?, ?, ?, ?, ?, ?)
            ");

            $firstOutId = null;
            $outNos = [];

            foreach ($items as $it) {
                $outboundNo = $prefixOut . str_pad($nextOutNum++, 4, '0', STR_PAD_LEFT);
                $matId = (int)$it['material_id'];
                $qty   = max(0, (float)$it['qty']);

                // Re-fetch current stock
                $stmtMat = $pdo->prepare("SELECT current_stock FROM materials WHERE id = ?");
                $stmtMat->execute([$matId]);
                $stockBefore = (float)$stmtMat->fetchColumn();
                $stockAfter = max(0, $stockBefore - $qty);

                $reasonText = "ACC Permintaan Consumable #{$req['request_no']} ({$req['destination']})";
                $outNotes = "Pengajuan oleh {$req['requester_name']}";
                if (!empty($adminNotes)) $outNotes .= " | Catatan Admin: {$adminNotes}";
                if (!empty($it['notes'])) $outNotes .= " | Item: {$it['notes']}";

                $stmtInsertOut->execute([
                    $outboundNo,
                    $matId,
                    $qty,
                    $req['destination'],
                    $adminName,
                    $reasonText,
                    $outNotes,
                    $now,
                    $now
                ]);

                $lastOutId = (int)$pdo->lastInsertId();
                if (!$firstOutId) $firstOutId = $lastOutId;
                $outNos[] = $outboundNo;

                $stmtUpMat->execute([$stockAfter, $matId]);

                $mutNotes = "Pengeluaran Consumable (ACC Admin {$adminName} untuk {$req['requester_name']}) - {$req['destination']}";
                $stmtMut->execute([$matId, -$qty, $stockBefore, $stockAfter, $outboundNo, $mutNotes, $adminId]);
            }

            // Update request
            $stmtUp = $pdo->prepare("
                UPDATE consumable_requests 
                SET status = 'APPROVED', approved_by = ?, admin_notes = ?, approved_at = ?, outbound_id = ?, updated_at = ?
                WHERE id = ?
            ");
            $combinedAdminNotes = "Disetujui & Stok Langsung Dipotong (" . implode(', ', $outNos) . ")";
            if (!empty($adminNotes)) $combinedAdminNotes .= " | {$adminNotes}";
            $stmtUp->execute([$adminId, $combinedAdminNotes, $now, $firstOutId, $now, $requestId]);

            $pdo->commit();

            echo json_encode([
                'success' => true,
                'message' => "Pengajuan #{$req['request_no']} berhasil di-ACC! Stok material telah otomatis dipotong dari gudang.",
                'outbound_nos' => $outNos
            ]);
            exit;
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memproses ACC: ' . $e->getMessage()]);
    }
    exit;
}

// 4. REJECT CONSUMABLE REQUEST (Admin only)
if ($action === 'reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::requireAdmin();

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $requestId = (int)($input['request_id'] ?? 0);
    $reason    = trim($input['reason'] ?? $input['admin_notes'] ?? '');

    if ($requestId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Request tidak valid']);
        exit;
    }

    if (empty($reason)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Alasan penolakan pengajuan wajib diisi!']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM consumable_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();

    if (!$req) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data pengajuan consumable tidak ditemukan']);
        exit;
    }

    if ($req['status'] !== 'PENDING') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Pengajuan ini sudah berstatus {$req['status']} dan tidak dapat ditolak."]);
        exit;
    }

    $adminId = Auth::id();
    $now = date('Y-m-d H:i:s');

    $stmtUp = $pdo->prepare("
        UPDATE consumable_requests 
        SET status = 'REJECTED', approved_by = ?, admin_notes = ?, approved_at = ?, updated_at = ?
        WHERE id = ?
    ");
    $stmtUp->execute([$adminId, $reason, $now, $now, $requestId]);

    echo json_encode([
        'success' => true,
        'message' => "Pengajuan #{$req['request_no']} telah Ditolak (Rejected). Operator akan melihat alasan penolakan."
    ]);
    exit;
}

// 5. GET DETAIL SINGLE CONSUMABLE REQUEST (For Edit Modal & Detail)
if ($action === 'get') {
    $requestId = (int)($_GET['id'] ?? 0);
    if ($requestId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Request tidak valid']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT r.*,
               u.name as requester_name,
               u.username as requester_username,
               u.shift as requester_shift,
               adm.name as approver_name
        FROM consumable_requests r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN users adm ON r.approved_by = adm.id
        WHERE r.id = ?
    ");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();

    if (!$req) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data pengajuan tidak ditemukan']);
        exit;
    }

    $stmtItems = $pdo->prepare("
        SELECT ri.*, m.name as material_name, m.code as material_code, m.unit as material_unit, m.current_stock, m.rack_location
        FROM consumable_request_items ri
        JOIN materials m ON ri.material_id = m.id
        WHERE ri.request_id = ?
        ORDER BY ri.id ASC
    ");
    $stmtItems->execute([$requestId]);
    $req['items'] = $stmtItems->fetchAll();

    echo json_encode(['success' => true, 'data' => $req]);
    exit;
}

// 6. UPDATE CONSUMABLE REQUEST & ITEMS (Super Admin Only)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Super Admin yang berhak mengedit data pengajuan consumable!']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $requestId   = (int)($input['request_id'] ?? 0);
    $destination = trim($input['destination'] ?? '');
    $priority    = trim($input['priority'] ?? 'NORMAL');
    $notes       = trim($input['notes'] ?? '');
    $items       = $input['items'] ?? [];

    if (is_string($items)) {
        $items = json_decode($items, true) ?? [];
    }

    if ($requestId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID Request tidak valid']);
        exit;
    }

    if (empty($destination)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tujuan Brand / Line wajib diisi!']);
        exit;
    }

    if (empty($items) || !is_array($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Daftar item consumable tidak boleh kosong. Minimal pilih 1 material.']);
        exit;
    }

    if (!in_array($priority, ['NORMAL', 'URGENT', 'CRITICAL'])) {
        $priority = 'NORMAL';
    }

    $stmtCheck = $pdo->prepare("SELECT * FROM consumable_requests WHERE id = ?");
    $stmtCheck->execute([$requestId]);
    $existing = $stmtCheck->fetch();
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data pengajuan tidak ditemukan']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $now = date('Y-m-d H:i:s');

        // Update main request
        $stmtUp = $pdo->prepare("
            UPDATE consumable_requests
            SET destination = ?, priority = ?, notes = ?, updated_at = ?
            WHERE id = ?
        ");
        $stmtUp->execute([$destination, $priority, $notes, $now, $requestId]);

        // Delete old items and insert updated items
        $stmtDel = $pdo->prepare("DELETE FROM consumable_request_items WHERE request_id = ?");
        $stmtDel->execute([$requestId]);

        $stmtInsItem = $pdo->prepare("
            INSERT INTO consumable_request_items (request_id, material_id, qty, notes, created_at)
            VALUES (?, ?, ?, ?, ?)
        ");

        $validCount = 0;
        $totalQty = 0;
        foreach ($items as $it) {
            $matId = (int)($it['material_id'] ?? 0);
            $qty   = max(0, parseNumberDecimal($it['qty'] ?? 0));
            $itNotes = trim($it['notes'] ?? '');

            if ($matId <= 0 || $qty <= 0) continue;

            $stmtInsItem->execute([$requestId, $matId, $qty, $itNotes, $now]);
            $validCount++;
            $totalQty += $qty;
        }

        if ($validCount === 0) {
            $pdo->rollBack();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada item material valid yang disimpan (Qty harus > 0).']);
            exit;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Pengajuan Consumable #{$existing['request_no']} berhasil diperbarui! ({$validCount} item material).",
            'total_items' => $validCount,
            'total_qty' => $totalQty
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui pengajuan: ' . $e->getMessage()]);
    }
    exit;
}

// 7. DELETE CONSUMABLE REQUEST (Super Admin Only)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::isSuperAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Hanya Super Admin yang berhak menghapus data pengajuan consumable!']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $requestId = (int)($input['request_id'] ?? 0);

    $stmtCheck = $pdo->prepare("SELECT * FROM consumable_requests WHERE id = ?");
    $stmtCheck->execute([$requestId]);
    $req = $stmtCheck->fetch();

    if (!$req) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Data pengajuan tidak ditemukan']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtDelItems = $pdo->prepare("DELETE FROM consumable_request_items WHERE request_id = ?");
        $stmtDelItems->execute([$requestId]);

        $stmtDelReq = $pdo->prepare("DELETE FROM consumable_requests WHERE id = ?");
        $stmtDelReq->execute([$requestId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Dokumen Pengajuan Consumable #{$req['request_no']} berhasil dihapus permanen."
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus pengajuan: ' . $e->getMessage()]);
    }
    exit;
}

// 8. CANCEL REQUEST (By Super Admin or By Operator if still PENDING)
if ($action === 'cancel' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $requestId = (int)($input['request_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM consumable_requests WHERE id = ?");
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();

    if (!$req) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Pengajuan tidak ditemukan']);
        exit;
    }

    if (Auth::isAdmin()) {
        if (!Auth::isSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Hanya Super Admin yang berhak membatalkan pengajuan consumable!']);
            exit;
        }
    } else {
        if ((int)$req['user_id'] !== Auth::id()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak untuk membatalkan pengajuan ini']);
            exit;
        }
        if ($req['status'] !== 'PENDING') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Hanya pengajuan berstatus PENDING yang dapat dibatalkan']);
            exit;
        }
    }

    $now = date('Y-m-d H:i:s');
    $stmtCancel = $pdo->prepare("UPDATE consumable_requests SET status = 'CANCELLED', updated_at = ? WHERE id = ?");
    $stmtCancel->execute([$now, $requestId]);

    echo json_encode([
        'success' => true,
        'message' => "Pengajuan #{$req['request_no']} berhasil dibatalkan."
    ]);
    exit;
}

// 6. STATS (Badge Count)
if ($action === 'stats') {
    $todayStart = date('Y-m-d 00:00:00');
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM consumable_requests WHERE status = 'PENDING'")->fetchColumn();
    $stmtApproved = $pdo->prepare("SELECT COUNT(*) FROM consumable_requests WHERE status = 'APPROVED' AND approved_at >= ?");
    $stmtApproved->execute([$todayStart]);
    $approvedToday = (int)$stmtApproved->fetchColumn();
    
    $myPending = 0;
    if (!Auth::isAdmin()) {
        $stmtMy = $pdo->prepare("SELECT COUNT(*) FROM consumable_requests WHERE user_id = ? AND status = 'PENDING'");
        $stmtMy->execute([Auth::id()]);
        $myPending = (int)$stmtMy->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'pending_count' => $pendingCount,
        'approved_today' => $approvedToday,
        'my_pending' => $myPending
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
