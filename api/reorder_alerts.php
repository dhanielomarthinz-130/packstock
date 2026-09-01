<?php
// api/reorder_alerts.php - Reorder Planning & Safety Stock Control API (7-Day Lead Time)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAdmin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'list';

$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

// Auto-create material_po_trackings table if not exists for PO tracking
if ($driver === 'sqlite') {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS material_po_trackings (
          id INTEGER PRIMARY KEY AUTOINCREMENT,
          material_id INTEGER NOT NULL,
          po_number TEXT NOT NULL,
          supplier_name TEXT NULL,
          ordered_qty REAL NOT NULL DEFAULT 0,
          order_date TEXT NOT NULL,
          eta_date TEXT NOT NULL,
          status TEXT DEFAULT 'ORDERED',
          notes TEXT NULL,
          created_by INTEGER NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");
} else {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `material_po_trackings` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `material_id` INT NOT NULL,
          `po_number` VARCHAR(100) NOT NULL,
          `supplier_name` VARCHAR(150) NULL,
          `ordered_qty` DECIMAL(12, 2) NOT NULL DEFAULT 0,
          `order_date` DATE NOT NULL,
          `eta_date` DATE NOT NULL,
          `status` ENUM('ORDERED', 'SHIPPED', 'RECEIVED', 'CANCELLED') DEFAULT 'ORDERED',
          `notes` TEXT NULL,
          `created_by` INT NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX (`material_id`),
          INDEX (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

$date14Expr = ($driver === 'sqlite') ? "datetime('now', '-14 days')" : "DATE_SUB(CURDATE(), INTERVAL 14 DAY)";
$date30Expr = ($driver === 'sqlite') ? "datetime('now', '-30 days')" : "DATE_SUB(CURDATE(), INTERVAL 30 DAY)";

// 1. LIST REORDER ALERTS & LOW STOCK CALCULATIONS
if ($action === 'list') {
    $search     = trim($_GET['search'] ?? '');
    $category   = trim($_GET['category'] ?? '');
    $filterType = trim($_GET['filter_type'] ?? 'ALL_CRITICAL'); // ALL_CRITICAL, EMPTY, MUST_PO, LOW, ALL

    // 1. Fetch materials with 14-day & 30-day outbound consumption
    $query = "
        SELECT m.*,
               COALESCE(out_14.total_outbound_14d, 0) AS total_outbound_14d,
               COALESCE(out_30.total_outbound_30d, 0) AS total_outbound_30d,
               COALESCE(out_all.total_outbound_all, 0) AS total_outbound_all,
               COALESCE(po_active.active_po_count, 0) AS active_po_count,
               po_active.latest_po_number,
               po_active.latest_po_qty,
               po_active.latest_po_eta,
               po_active.latest_supplier
        FROM materials m
        LEFT JOIN (
            SELECT material_id, SUM(ABS(qty_change)) AS total_outbound_14d
            FROM stock_mutations
            WHERE qty_change < 0 AND created_at >= {$date14Expr}
            GROUP BY material_id
        ) out_14 ON m.id = out_14.material_id
        LEFT JOIN (
            SELECT material_id, SUM(ABS(qty_change)) AS total_outbound_30d
            FROM stock_mutations
            WHERE qty_change < 0 AND created_at >= {$date30Expr}
            GROUP BY material_id
        ) out_30 ON m.id = out_30.material_id
        LEFT JOIN (
            SELECT material_id, SUM(ABS(qty_change)) AS total_outbound_all
            FROM stock_mutations
            WHERE qty_change < 0
            GROUP BY material_id
        ) out_all ON m.id = out_all.material_id
        LEFT JOIN (
            SELECT t1.material_id,
                   COUNT(t1.id) AS active_po_count,
                   t1.po_number AS latest_po_number,
                   t1.ordered_qty AS latest_po_qty,
                   t1.eta_date AS latest_po_eta,
                   t1.supplier_name AS latest_supplier
            FROM material_po_trackings t1
            INNER JOIN (
                SELECT material_id, MAX(id) AS max_id
                FROM material_po_trackings
                WHERE status IN ('ORDERED', 'SHIPPED')
                GROUP BY material_id
            ) t2 ON t1.id = t2.max_id
            GROUP BY t1.material_id
        ) po_active ON m.id = po_active.material_id
        WHERE 1=1
    ";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (m.code LIKE ? OR m.name LIKE ? OR m.rack_location LIKE ? OR m.category LIKE ?)";
        $term = "%{$search}%";
        $params = array_merge($params, [$term, $term, $term, $term]);
    }

    if (!empty($category) && $category !== 'all') {
        $query .= " AND m.category = ?";
        $params[] = $category;
    }

    $query .= " ORDER BY (m.current_stock <= 0) DESC, (m.current_stock <= m.min_stock) DESC, m.name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $processed = [];
    $totalEmptyCount    = 0;
    $totalMustPoCount   = 0;
    $totalLowStockCount = 0;
    $totalPoQtyEstimate = 0;

    $leadTimeDays = 7; // Standar Lead Time Pengadaan = 1 Minggu (7 Hari)

    foreach ($rows as $r) {
        $stock = (float)$r['current_stock'];
        $minStock = (float)$r['min_stock'];

        // Laju konsumsi harian (prioritas 14 hari, fallback 30 hari, fallback all-time)
        $out14 = (float)$r['total_outbound_14d'];
        $out30 = (float)$r['total_outbound_30d'];
        $outAll = (float)$r['total_outbound_all'];

        if ($out14 > 0) {
            $dailyUsage = $out14 / 14.0;
        } elseif ($out30 > 0) {
            $dailyUsage = $out30 / 30.0;
        } elseif ($outAll > 0) {
            $dailyUsage = $outAll / 60.0;
        } else {
            $dailyUsage = 0.0;
        }

        // Kebutuhan selama Lead Time 7 Hari
        $leadTimeDemand = $dailyUsage * $leadTimeDays;

        // Reorder Point (Titik Pemesanan Ulang): Lead Time Demand + Safety Stock (min_stock)
        $reorderPoint = $leadTimeDemand + $minStock;

        // Evaluasi Status Kritis
        $urgencyStatus = 'SAFE';
        $urgencyLabel  = 'Aman';
        $urgencyColor  = 'emerald';

        if ($stock <= 0) {
            $urgencyStatus = 'EMPTY';
            $urgencyLabel  = 'HABIS (0)';
            $urgencyColor  = 'rose';
            $totalEmptyCount++;
        } elseif ($stock <= $leadTimeDemand || ($minStock > 0 && $stock <= ($minStock * 0.5))) {
            $urgencyStatus = 'MUST_PO';
            $urgencyLabel  = 'HARUS PO (Kritis)';
            $urgencyColor  = 'orange';
            $totalMustPoCount++;
        } elseif ($stock <= $minStock || ($reorderPoint > 0 && $stock <= $reorderPoint)) {
            $urgencyStatus = 'LOW';
            $urgencyLabel  = 'Menipis';
            $urgencyColor  = 'amber';
            $totalLowStockCount++;
        }

        // Estimasi Sisa Hari (Runway Days)
        $runwayDays = null;
        $runwayText = 'Statis / Tidak Ada Pemakaian';
        if ($stock <= 0) {
            $runwayDays = 0;
            $runwayText = '0 Hari (Habis)';
        } elseif ($dailyUsage > 0) {
            $days = $stock / $dailyUsage;
            $runwayDays = round($days, 1);
            if ($runwayDays <= 1) {
                $runwayText = '< 1 Hari (Sangat Kritis)';
            } elseif ($runwayDays <= 7) {
                $runwayText = "{$runwayDays} Hari (Kurang dari 1 Minggu!)";
            } else {
                $runwayText = "{$runwayDays} Hari";
            }
        }

        // Saran Qty Order (Reorder Qty)
        $suggestedQty = 0;
        if ($urgencyStatus !== 'SAFE') {
            $targetBuffer = max($minStock * 2, $leadTimeDemand * 3, $minStock + ($dailyUsage * 14));
            $diff = $targetBuffer - $stock;
            $suggestedQty = max(1, ceil($diff));
            if ($suggestedQty > 100) {
                $suggestedQty = ceil($suggestedQty / 10) * 10;
            }
            $totalPoQtyEstimate += $suggestedQty;
        }

        $itemData = [
            'id'                  => (int)$r['id'],
            'code'                => $r['code'],
            'name'                => $r['name'],
            'category'            => $r['category'] ?: 'Umum',
            'unit'                => $r['unit'] ?: 'Pcs',
            'rack_location'       => $r['rack_location'] ?: '-',
            'current_stock'       => $stock,
            'min_stock'           => $minStock,
            'lead_time_days'      => $leadTimeDays,
            'daily_usage'         => round($dailyUsage, 2),
            'lead_time_demand'    => round($leadTimeDemand, 2),
            'reorder_point'       => round($reorderPoint, 2),
            'runway_days'         => $runwayDays,
            'runway_text'         => $runwayText,
            'suggested_po_qty'    => $suggestedQty,
            'urgency_status'      => $urgencyStatus,
            'urgency_label'       => $urgencyLabel,
            'urgency_color'       => $urgencyColor,
            'is_ordered'          => ($r['active_po_count'] > 0),
            'latest_po_number'    => $r['latest_po_number'],
            'latest_po_qty'       => (float)$r['latest_po_qty'],
            'latest_po_eta'       => $r['latest_po_eta'],
            'latest_supplier'     => $r['latest_supplier'],
        ];

        // Filter based on filter_type
        if ($filterType === 'ALL_CRITICAL') {
            if ($urgencyStatus !== 'SAFE') {
                $processed[] = $itemData;
            }
        } elseif ($filterType === 'EMPTY') {
            if ($urgencyStatus === 'EMPTY') {
                $processed[] = $itemData;
            }
        } elseif ($filterType === 'MUST_PO') {
            if ($urgencyStatus === 'MUST_PO' || $urgencyStatus === 'EMPTY') {
                $processed[] = $itemData;
            }
        } elseif ($filterType === 'LOW') {
            if ($urgencyStatus === 'LOW') {
                $processed[] = $itemData;
            }
        } else {
            $processed[] = $itemData;
        }
    }

    echo json_encode([
        'success' => true,
        'metrics' => [
            'total_critical'    => $totalEmptyCount + $totalMustPoCount + $totalLowStockCount,
            'total_empty'       => $totalEmptyCount,
            'total_must_po'     => $totalMustPoCount,
            'total_low_stock'   => $totalLowStockCount,
            'total_po_estimate' => $totalPoQtyEstimate,
            'lead_time_days'    => $leadTimeDays
        ],
        'data' => $processed
    ]);
    exit;
}

// 2. MARK AS ORDERED / RECORD PO TRACKING
if ($action === 'mark_ordered' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $materialId   = (int)($input['material_id'] ?? 0);
    $poNumber     = trim($input['po_number'] ?? '');
    $supplierName = trim($input['supplier_name'] ?? '');
    $orderedQty   = max(0.01, (float)($input['ordered_qty'] ?? 0));
    $orderDate    = trim($input['order_date'] ?? date('Y-m-d'));
    $etaDate      = trim($input['eta_date'] ?? date('Y-m-d', strtotime('+7 days')));
    $notes        = trim($input['notes'] ?? '');

    if ($materialId <= 0 || empty($poNumber) || $orderedQty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Material, Nomor PO, dan Qty Pesan wajib diisi!']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO material_po_trackings (material_id, po_number, supplier_name, ordered_qty, order_date, eta_date, status, notes, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'ORDERED', ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$materialId, $poNumber, $supplierName, $orderedQty, $orderDate, $etaDate, $notes, Auth::id()]);

        echo json_encode(['success' => true, 'message' => "Pengajuan PO #{$poNumber} berhasil dicatat! Estimasi kedatangan: " . date('d M Y', strtotime($etaDate))]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mencatat PO: ' . $e->getMessage()]);
    }
    exit;
}

// 3. CANCEL / CLEAR ACTIVE PO FOR MATERIAL
if ($action === 'clear_po' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $materialId = (int)($input['material_id'] ?? 0);

    if ($materialId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Material ID tidak valid']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE material_po_trackings SET status = 'CANCELLED' WHERE material_id = ? AND status IN ('ORDERED', 'SHIPPED')");
    $stmt->execute([$materialId]);

    echo json_encode(['success' => true, 'message' => 'Status PO aktif berhasil dibatalkan']);
    exit;
}

// 4. EXPORT REORDER ALERTS TO CSV
if ($action === 'export') {
    $filename = "Rekap_Peringatan_PO_Stok_Kemas_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");

    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // UTF-8 BOM

    fputcsv($output, [
        'Kode Item / SKU',
        'Nama Material Kemas',
        'Kategori',
        'Lokasi Rak',
        'Stok Fisik Saat Ini',
        'Safety Stock (Min Stock)',
        'Satuan',
        'Rata-rata Keluar / Hari',
        'Kebutuhan Lead Time (7 Hari)',
        'Reorder Point (ROP)',
        'Estimasi Sisa Hari (Runway)',
        'Rekomendasi Qty PO',
        'Status Urgensi',
        'Status PO',
        'No. PO Terakhir',
        'Supplier',
        'Estimasi Tiba (ETA)'
    ]);

    $stmt = $pdo->query("
        SELECT m.*,
               COALESCE(out_14.total_outbound_14d, 0) AS total_outbound_14d,
               COALESCE(po_active.active_po_count, 0) AS active_po_count,
               po_active.latest_po_number,
               po_active.latest_po_qty,
               po_active.latest_po_eta,
               po_active.latest_supplier
        FROM materials m
        LEFT JOIN (
            SELECT material_id, SUM(ABS(qty_change)) AS total_outbound_14d
            FROM stock_mutations
            WHERE qty_change < 0 AND created_at >= {$date14Expr}
            GROUP BY material_id
        ) out_14 ON m.id = out_14.material_id
        LEFT JOIN (
            SELECT t1.material_id,
                   COUNT(t1.id) AS active_po_count,
                   t1.po_number AS latest_po_number,
                   t1.ordered_qty AS latest_po_qty,
                   t1.eta_date AS latest_po_eta,
                   t1.supplier_name AS latest_supplier
            FROM material_po_trackings t1
            INNER JOIN (
                SELECT material_id, MAX(id) AS max_id
                FROM material_po_trackings
                WHERE status IN ('ORDERED', 'SHIPPED')
                GROUP BY material_id
            ) t2 ON t1.id = t2.max_id
            GROUP BY t1.material_id
        ) po_active ON m.id = po_active.material_id
        WHERE m.current_stock <= m.min_stock OR m.current_stock <= 0
        ORDER BY (m.current_stock <= 0) DESC, m.name ASC
    ");

    while ($r = $stmt->fetch()) {
        $stock = (float)$r['current_stock'];
        $minStock = (float)$r['min_stock'];
        $out14 = (float)$r['total_outbound_14d'];
        $dailyUsage = $out14 > 0 ? ($out14 / 14.0) : 0.0;
        $leadTimeDemand = $dailyUsage * 7;
        $reorderPoint = $leadTimeDemand + $minStock;

        $urgency = 'Stok Menipis';
        if ($stock <= 0) $urgency = 'STOK HABIS (0)';
        elseif ($stock <= $leadTimeDemand) $urgency = 'HARUS PO SEGERA (Kritis)';

        $runwayText = $stock <= 0 ? '0 Hari' : ($dailyUsage > 0 ? round($stock / $dailyUsage, 1) . ' Hari' : 'Statis');
        $suggestedQty = max(1, ceil(($minStock * 2) - $stock));

        fputcsv($output, [
            $r['code'],
            $r['name'],
            $r['category'] ?: 'Umum',
            $r['rack_location'] ?: '-',
            $stock,
            $minStock,
            $r['unit'] ?: 'Pcs',
            round($dailyUsage, 2),
            round($leadTimeDemand, 2),
            round($reorderPoint, 2),
            $runwayText,
            $suggestedQty,
            $urgency,
            $r['active_po_count'] > 0 ? 'Sedang Dipesan' : 'Belum Ada PO',
            $r['latest_po_number'] ?: '-',
            $r['latest_supplier'] ?: '-',
            $r['latest_po_eta'] ?: '-'
        ]);
    }

    fclose($output);
    exit;
}
