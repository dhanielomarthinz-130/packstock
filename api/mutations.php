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
    $limit      = min(200, max(10, (int)($_GET['limit'] ?? 100)));

    $query = "
        SELECT sm.*, 
               m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location,
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

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
