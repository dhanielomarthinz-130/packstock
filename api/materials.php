<?php
// api/materials.php - Packaging Material Stock Calculation & Card History API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

// 0. GET DISTINCT CATEGORIES
if ($action === 'categories') {
    $stmt = $pdo->query("SELECT DISTINCT category FROM materials WHERE category IS NOT NULL AND TRIM(category) != '' ORDER BY category ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['success' => true, 'data' => $categories]);
    exit;
}

// 1. LIST MATERIALS WITH DYNAMIC STOCK CALCULATION FORMULA:
// Ending Stock = (Stok Awal Upload Excel) + (Total Masuk) - (Total Keluar)
if ($action === 'list') {
    $search = trim($_GET['search'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $stockStatus = trim($_GET['status'] ?? ''); // all, low, safe, empty

    // Reconcile and calculate real stock before listing
    reconcileMaterialStock($pdo);

    $query = "
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
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (m.code LIKE ? OR m.name LIKE ? OR m.description LIKE ? OR m.rack_location LIKE ? OR m.category LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term, $term]);
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

    $query .= " ORDER BY (m.current_stock <= m.min_stock) DESC, m.name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $materials = $stmt->fetchAll();

    // Attach status label to each
    foreach ($materials as &$mat) {
        $mat['initial_upload_stock'] = (float)$mat['initial_upload_stock'];
        $mat['total_inbound'] = (float)$mat['total_inbound'];
        $mat['total_outbound'] = (float)$mat['total_outbound'];
        $mat['current_stock'] = (float)$mat['current_stock'];
        $mat['min_stock'] = (float)$mat['min_stock'];

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

    echo json_encode(['success' => true, 'data' => $materials]);
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
        echo json_encode(['success' => false, 'message' => 'Material packaging tidak ditemukan']);
        exit;
    }

    $material['initial_upload_stock'] = (float)$material['initial_upload_stock'];
    $material['total_inbound'] = (float)$material['total_inbound'];
    $material['total_outbound'] = (float)$material['total_outbound'];
    $material['current_stock'] = (float)$material['current_stock'];

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

    echo json_encode([
        'success' => true,
        'material' => $material,
        'history' => $history
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
    $category = trim($input['category'] ?? 'Karton Box');
    $unit = trim($input['unit'] ?? 'Pcs');
    $rackLocation = trim($input['rack_location'] ?? 'Gudang Utama');
    $minStock = max(0, (float)($input['min_stock'] ?? 20));
    $initialStock = max(0, (float)($input['initial_stock'] ?? 0));
    $description = trim($input['description'] ?? '');

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
        $stmt = $pdo->prepare("INSERT INTO materials (code, name, category, unit, rack_location, min_stock, current_stock, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$code, $name, $category, $unit, $rackLocation, $minStock, $initialStock, $description]);
        $matId = (int)$pdo->lastInsertId();

        if ($initialStock > 0) {
            $stmtMut = $pdo->prepare("INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id) VALUES (?, 'INITIAL_IMPORT', ?, 0, ?, 'INITIAL-INPUT', 'Stok Awal Pendaftaran Material', ?)");
            $stmtMut->execute([$matId, $initialStock, $initialStock, Auth::id()]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Material packaging berhasil ditambahkan!', 'id' => $matId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()]);
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
    $category = trim($input['category'] ?? 'Karton Box');
    $unit = trim($input['unit'] ?? 'Pcs');
    $rackLocation = trim($input['rack_location'] ?? 'Gudang Utama');
    $minStock = max(0, (float)($input['min_stock'] ?? 20));
    $description = trim($input['description'] ?? '');

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
        $stmt = $pdo->prepare("UPDATE materials SET code = ?, name = ?, category = ?, unit = ?, rack_location = ?, min_stock = ?, description = ? WHERE id = ?");
        $stmt->execute([$code, $name, $category, $unit, $rackLocation, $minStock, $description, $id]);
        echo json_encode(['success' => true, 'message' => 'Material packaging berhasil diperbarui!']);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui material: ' . $e->getMessage()]);
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
        $pdo->prepare("DELETE FROM materials WHERE id = ?")->execute([$id]);
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Material packaging berhasil dihapus!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal menghapus: ' . $e->getMessage()]);
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

