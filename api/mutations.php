<?php
// api/mutations.php - Stock Mutation Ledger & Audit Trail API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAdmin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $materialId = (int)($_GET['material_id'] ?? 0);
    $type       = trim($_GET['type'] ?? '');
    $search     = trim($_GET['search'] ?? '');
    $itemType   = strtoupper(trim($_GET['item_type'] ?? ''));
    $limit      = min(500, max(10, (int)($_GET['limit'] ?? 150)));

    $query = "
        SELECT sm.*, 
               m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location,
               COALESCE(m.item_type, 'PACKAGING') as material_item_type,
               u.name as user_name, u.role as user_role
        FROM stock_mutations sm
        JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.user_id = u.id
        WHERE 1=1
    ";
    $params = [];

    $date = trim($_GET['date'] ?? '');
    $time = trim($_GET['time'] ?? '');

    if ($materialId > 0) {
        $query .= " AND sm.material_id = ?";
        $params[] = $materialId;
    }

    if (!empty($itemType) && $itemType !== 'ALL') {
        if ($itemType === 'GIMMICK') {
            $query .= " AND m.item_type = 'GIMMICK'";
        } elseif ($itemType === 'PACKAGING' || $itemType === 'KEMAS') {
            $query .= " AND (m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = '')";
        }
    }

    if (!empty($type) && $type !== 'ALL') {
        $query .= " AND sm.type = ?";
        $params[] = $type;
    }

    if (!empty($date)) {
        $query .= " AND sm.created_at LIKE ?";
        $params[] = "{$date}%";
    }

    if (!empty($time)) {
        $query .= " AND sm.created_at LIKE ?";
        $params[] = "% {$time}%";
    }

    if (!empty($search)) {
        $query .= " AND (sm.reference_no LIKE ? OR sm.notes LIKE ? OR m.name LIKE ? OR m.code LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term]);
    }

    $query .= " ORDER BY sm.created_at DESC, sm.id DESC LIMIT {$limit}";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode(['success' => true, 'data' => $rows]);
    exit;
}

// 2. GET DETAIL BY REFERENCE NUMBER OR MUTATION ID
if ($action === 'reference_detail') {
    $ref = trim($_GET['ref'] ?? '');
    $mutationId = (int)($_GET['id'] ?? ($_GET['mutation_id'] ?? 0));

    if (empty($ref) && $mutationId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parameter no referensi atau ID mutasi diperlukan.']);
        exit;
    }

    // 1. Fetch mutation records matching reference or ID
    if (!empty($ref)) {
        $stmtMut = $pdo->prepare("
            SELECT sm.*, 
                   m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location,
                   COALESCE(m.item_type, 'PACKAGING') as material_item_type,
                   u.name as user_name, u.username as user_username, u.role as user_role
            FROM stock_mutations sm
            JOIN materials m ON sm.material_id = m.id
            LEFT JOIN users u ON sm.user_id = u.id
            WHERE sm.reference_no = ?
            ORDER BY sm.id ASC
        ");
        $stmtMut->execute([$ref]);
        $mutations = $stmtMut->fetchAll();
    } else {
        $stmtMut = $pdo->prepare("
            SELECT sm.*, 
                   m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location,
                   COALESCE(m.item_type, 'PACKAGING') as material_item_type,
                   u.name as user_name, u.username as user_username, u.role as user_role
            FROM stock_mutations sm
            JOIN materials m ON sm.material_id = m.id
            LEFT JOIN users u ON sm.user_id = u.id
            WHERE sm.id = ?
        ");
        $stmtMut->execute([$mutationId]);
        $mutations = $stmtMut->fetchAll();
        if (!empty($mutations)) {
            $ref = $mutations[0]['reference_no'];
        }
    }

    $primaryMutation = $mutations[0] ?? null;

    // Check Inbound Transaction if ref matches
    $inboundData = null;
    $inboundItems = [];
    if (!empty($ref)) {
        $stmtInb = $pdo->prepare("
            SELECT i.*, 
                   m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location,
                   COALESCE(u.name, i.received_by) as receiver_name,
                   COALESCE(u.username, i.received_by) as receiver_username,
                   COALESCE(u.role, 'admin') as receiver_role
            FROM inbound_transactions i
            JOIN materials m ON i.material_id = m.id
            LEFT JOIN users u ON (i.received_by = u.id OR i.received_by = u.username OR i.received_by = u.name)
            WHERE i.inbound_no = ?
            ORDER BY i.id ASC
        ");
        $stmtInb->execute([$ref]);
        $inboundRows = $stmtInb->fetchAll();
        if (!empty($inboundRows)) {
            $inboundData = $inboundRows[0];
            $inboundItems = $inboundRows;
        }
    }

    // Check Tasks if ref matches
    $taskData = null;
    if (!empty($ref)) {
        $stmtTask = $pdo->prepare("
            SELECT t.*, 
                   m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location,
                   u_to.name as operator_name, u_to.username as operator_username,
                   u_by.name as admin_name, u_by.username as admin_username
            FROM tasks t
            JOIN materials m ON t.material_id = m.id
            LEFT JOIN users u_to ON t.assigned_to = u_to.id
            LEFT JOIN users u_by ON t.assigned_by = u_by.id
            WHERE t.task_no = ?
            LIMIT 1
        ");
        $stmtTask->execute([$ref]);
        $taskData = $stmtTask->fetch();
    }

    // Check Outbound Transactions if ref matches
    $outboundData = null;
    if (!empty($ref)) {
        $stmtOut = $pdo->prepare("
            SELECT o.*, 
                   m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location,
                   COALESCE(u.name, o.issued_by) as issuer_name,
                   COALESCE(u.username, o.issued_by) as issuer_username
            FROM outbound_transactions o
            JOIN materials m ON o.material_id = m.id
            LEFT JOIN users u ON (o.issued_by = u.id OR o.issued_by = u.username OR o.issued_by = u.name)
            WHERE o.outbound_no = ?
            LIMIT 1
        ");
        $stmtOut->execute([$ref]);
        $outboundData = $stmtOut->fetch();
    }

    if (!$primaryMutation && !$inboundData && !$taskData && !$outboundData) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Detail transaksi untuk referensi '{$ref}' tidak ditemukan."]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'reference_no' => $ref,
            'type' => $primaryMutation['type'] ?? ($inboundData ? 'INBOUND' : ($taskData ? 'TASK_PICKING' : ($outboundData ? 'OUTBOUND' : 'UNKNOWN'))),
            'created_at' => $primaryMutation['created_at'] ?? ($inboundData['created_at'] ?? ($taskData['created_at'] ?? ($outboundData['created_at'] ?? ''))),
            'primary_mutation' => $primaryMutation,
            'all_mutations' => $mutations,
            'inbound' => $inboundData,
            'inbound_items' => $inboundItems,
            'task' => $taskData,
            'outbound' => $outboundData
        ]
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
