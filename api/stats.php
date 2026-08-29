<?php
// api/stats.php - Real-Time Dashboard Statistics & Stock Summary Report API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'stats';

try {
    if ($action === 'stock_summary') {
        $filterType = $_GET['filter_type'] ?? 'date'; // 'date', 'week', 'range', 'month'
        $search     = trim($_GET['search'] ?? '');
        $category   = trim($_GET['category'] ?? 'all');
        $status     = trim($_GET['status'] ?? 'all');

        $now = new DateTime();
        $startDateStr = $now->format('Y-m-d');
        $endDateStr   = $now->format('Y-m-d');
        $periodLabel  = $now->format('d M Y');

        if ($filterType === 'date') {
            $d = trim($_GET['date'] ?? '');
            if ($d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                $startDateStr = $d;
                $endDateStr   = $d;
                $dt = new DateTime($d);
                $periodLabel = 'Tanggal: ' . $dt->format('d M Y');
            } else {
                $periodLabel = 'Tanggal Hari Ini: ' . $now->format('d M Y');
            }
        } elseif ($filterType === 'week') {
            $year  = (int)($_GET['year'] ?? $now->format('Y'));
            $month = (int)($_GET['month'] ?? $now->format('n'));
            $week  = (int)($_GET['week'] ?? 1);

            $lastDayOfMonth = (int)(new DateTime("$year-$month-01"))->format('t');
            $startDay = 1;
            $endDay = 7;

            if ($week === 1) { $startDay = 1; $endDay = min(7, $lastDayOfMonth); }
            elseif ($week === 2) { $startDay = 8; $endDay = min(14, $lastDayOfMonth); }
            elseif ($week === 3) { $startDay = 15; $endDay = min(21, $lastDayOfMonth); }
            elseif ($week === 4) { $startDay = 22; $endDay = min(28, $lastDayOfMonth); }
            elseif ($week >= 5) { $startDay = 29; $endDay = $lastDayOfMonth; }

            $startDateStr = sprintf('%04d-%02d-%02d', $year, $month, $startDay);
            $endDateStr   = sprintf('%04d-%02d-%02d', $year, $month, $endDay);

            $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
            $mName = $monthNames[$month] ?? "Bulan $month";
            $periodLabel = "Week $week - $mName $year (" . date('d M', strtotime($startDateStr)) . " - " . date('d M Y', strtotime($endDateStr)) . ")";
        } elseif ($filterType === 'range') {
            $s = trim($_GET['start_date'] ?? '');
            $e = trim($_GET['end_date'] ?? '');
            if ($s && $e) {
                $startDateStr = $s;
                $endDateStr   = $e;
                $periodLabel  = date('d M Y', strtotime($s)) . ' s/d ' . date('d M Y', strtotime($e));
            }
        } elseif ($filterType === 'month') {
            $year  = (int)($_GET['year'] ?? $now->format('Y'));
            $month = (int)($_GET['month'] ?? $now->format('n'));
            $lastDayOfMonth = (int)(new DateTime("$year-$month-01"))->format('t');
            $startDateStr = sprintf('%04d-%02d-01', $year, $month);
            $endDateStr   = sprintf('%04d-%02d-%02d', $year, $month, $lastDayOfMonth);
            $monthNames = [1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'];
            $mName = $monthNames[$month] ?? "Bulan $month";
            $periodLabel = "Bulan $mName $year";
        }

        $startDateTime = $startDateStr . ' 00:00:00';
        $endDateTime   = $endDateStr . ' 23:59:59';

        $sql = "
            SELECT 
                m.id,
                m.code,
                m.name,
                m.unit,
                m.rack_location,
                m.category,
                m.min_stock,
                m.current_stock,
                COALESCE(SUM(CASE WHEN sm.type = 'INBOUND' AND sm.created_at BETWEEN ? AND ? THEN sm.qty_change ELSE 0 END), 0) AS total_inbound,
                COALESCE(SUM(CASE WHEN sm.type IN ('OUTBOUND', 'TASK_PICKING') AND sm.created_at BETWEEN ? AND ? THEN ABS(sm.qty_change) ELSE 0 END), 0) AS total_outbound,
                COALESCE(SUM(CASE WHEN sm.type = 'ADJUSTMENT' AND sm.created_at BETWEEN ? AND ? THEN sm.qty_change ELSE 0 END), 0) AS total_adjustment,
                COALESCE(SUM(CASE WHEN sm.created_at >= ? THEN sm.qty_change ELSE 0 END), 0) AS change_since_start,
                COALESCE(SUM(CASE WHEN sm.created_at > ? THEN sm.qty_change ELSE 0 END), 0) AS change_after_end
            FROM materials m
            LEFT JOIN stock_mutations sm ON m.id = sm.material_id
            WHERE 1=1
        ";

        $params = [$startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime, $startDateTime, $endDateTime];

        if (!empty($search)) {
            $sql .= " AND (m.code LIKE ? OR m.name LIKE ? OR m.rack_location LIKE ?)";
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (!empty($category) && $category !== 'all') {
            $sql .= " AND m.category = ?";
            $params[] = $category;
        }

        $sql .= " GROUP BY m.id ORDER BY m.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $data = [];
        $sumInbound = 0;
        $sumOutbound = 0;
        $sumAdjustment = 0;
        $sumEndingStock = 0;
        $activeSkuCount = 0;

        foreach ($rows as $r) {
            $current = (int)$r['current_stock'];
            $changeSinceStart = (int)$r['change_since_start'];
            $changeAfterEnd = (int)$r['change_after_end'];
            
            $beginningStock = $current - $changeSinceStart;
            $endingStock = $current - $changeAfterEnd;
            $inbound = (int)$r['total_inbound'];
            $outbound = (int)$r['total_outbound'];
            $adjustment = (int)$r['total_adjustment'];
            $minStock = (int)$r['min_stock'];

            $hasActivity = ($inbound !== 0 || $outbound !== 0 || $adjustment !== 0);

            // Filter Status
            if ($status === 'activity_only' && !$hasActivity) continue;
            if ($status === 'low' && ($endingStock > $minStock || $endingStock <= 0)) continue;
            if ($status === 'empty' && $endingStock > 0) continue;
            if ($status === 'safe' && $endingStock <= $minStock) continue;
            if ($status === 'adjusted_only' && $adjustment === 0) continue;

            $stockStatus = 'safe';
            if ($endingStock <= 0) $stockStatus = 'empty';
            elseif ($endingStock <= $minStock) $stockStatus = 'low';

            $sumInbound += $inbound;
            $sumOutbound += $outbound;
            $sumAdjustment += $adjustment;
            $sumEndingStock += $endingStock;
            $activeSkuCount++;

            $data[] = [
                'id'              => (int)$r['id'],
                'code'            => $r['code'],
                'name'            => $r['name'],
                'unit'            => $r['unit'] ?: 'Pcs',
                'rack_location'   => $r['rack_location'] ?: '-',
                'category'        => $r['category'] ?: 'Umum',
                'min_stock'       => $minStock,
                'beginning_stock' => $beginningStock,
                'inbound'         => $inbound,
                'outbound'        => $outbound,
                'adjustment'      => $adjustment,
                'ending_stock'    => $endingStock,
                'status'          => $stockStatus,
                'has_activity'    => $hasActivity
            ];
        }

        echo json_encode([
            'success' => true,
            'period' => [
                'type'        => $filterType,
                'label'       => $periodLabel,
                'start_date'  => $startDateStr,
                'end_date'    => $endDateStr
            ],
            'summary' => [
                'total_sku'         => $activeSkuCount,
                'total_inbound'     => $sumInbound,
                'total_outbound'    => $sumOutbound,
                'total_adjustment'  => $sumAdjustment,
                'total_ending_stock'=> $sumEndingStock
            ],
            'data' => $data
        ]);
        exit;
    }

    // Default: Overall KPI stats
    $today = date('Y-m-d');

    // 1. Total items count
    $stmt = $pdo->query("SELECT COUNT(*) FROM materials");
    $totalMaterials = (int)$stmt->fetchColumn();

    // 2. Total physical stock sum
    $stmt = $pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM materials");
    $totalStockUnits = (int)$stmt->fetchColumn();

    // 3. Low stock count (<= min_stock and > 0)
    $stmt = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= min_stock AND current_stock > 0");
    $lowStockCount = (int)$stmt->fetchColumn();

    // 4. Out of stock count (<= 0)
    $stmt = $pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock <= 0");
    $outOfStockCount = (int)$stmt->fetchColumn();

    // 5. Active Tasks (PENDING or IN_PROGRESS)
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status IN ('PENDING', 'IN_PROGRESS')");
    $activeTasksCount = (int)$stmt->fetchColumn();

    // 6. Urgent Tasks count
    $stmt = $pdo->query("SELECT COUNT(*) FROM tasks WHERE status IN ('PENDING', 'IN_PROGRESS') AND priority IN ('URGENT', 'CRITICAL')");
    $urgentTasksCount = (int)$stmt->fetchColumn();

    // 7. Today Inbound Qty
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) FROM inbound_transactions WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $todayInboundQty = (int)$stmt->fetchColumn();

    // 8. Today Outbound Qty
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(qty), 0) FROM outbound_transactions WHERE DATE(created_at) = ?");
    $stmt->execute([$today]);
    $todayOutboundQty = (int)$stmt->fetchColumn();

    // Operator specific stats
    $myActiveTasks = 0;
    $myCompletedTasksToday = 0;
    if (Auth::role() === 'operator') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status IN ('PENDING', 'IN_PROGRESS')");
        $stmt->execute([Auth::id()]);
        $myActiveTasks = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status = 'COMPLETED' AND DATE(completed_at) = ?");
        $stmt->execute([Auth::id(), $today]);
        $myCompletedTasksToday = (int)$stmt->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'stats' => [
            'total_materials'        => $totalMaterials,
            'total_stock_units'      => $totalStockUnits,
            'low_stock_count'        => $lowStockCount,
            'out_of_stock_count'     => $outOfStockCount,
            'total_critical_stock'   => $lowStockCount + $outOfStockCount,
            'active_tasks_count'     => $activeTasksCount,
            'urgent_tasks_count'     => $urgentTasksCount,
            'today_inbound_qty'      => $todayInboundQty,
            'today_outbound_qty'     => $todayOutboundQty,
            'my_active_tasks'        => $myActiveTasks,
            'my_completed_today'     => $myCompletedTasksToday
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Gagal memuat statistik: ' . $e->getMessage()]);
}
