<?php
// api/stats.php - Real-Time Dashboard Statistics & Stock Summary Report API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'stats';

try {
    if ($action === 'stock_summary') {
        $filterType = $_GET['filter_type'] ?? 'date'; // 'date', 'week', 'range', 'month', 'all'
        $search     = trim($_GET['search'] ?? '');
        $category   = trim($_GET['category'] ?? 'all');
        $status     = trim($_GET['status'] ?? 'all');

        $now = new DateTime();
        $startDateStr = $now->format('Y-m-d');
        $endDateStr   = $now->format('Y-m-d');
        $periodLabel  = $now->format('d M Y');

        if ($filterType === 'all') {
            $startDateStr = '2000-01-01';
            $endDateStr   = '2099-12-31';
            $periodLabel  = 'Semua Waktu (All-Time)';
        } elseif ($filterType === 'date') {
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
        $sumAdjustmentPlus = 0;
        $sumAdjustmentMinus = 0;
        $sumEndingStock = 0;
        $activeSkuCount = 0;
        $lowStockCount = 0;
        $outOfStockCount = 0;

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

            if ($endingStock <= 0) $outOfStockCount++;
            elseif ($endingStock <= $minStock) $lowStockCount++;

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
            if ($adjustment > 0) $sumAdjustmentPlus += $adjustment;
            elseif ($adjustment < 0) $sumAdjustmentMinus += $adjustment;

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

        // ================= TOP 10 BARANG MASUK =================
        $stmtTopIn = $pdo->prepare("
            SELECT 
                m.id,
                m.code,
                m.name,
                m.unit,
                m.category,
                m.rack_location,
                m.current_stock,
                COALESCE(SUM(i.qty), 0) AS total_qty,
                COUNT(i.id) AS tx_count
            FROM inbound_transactions i
            JOIN materials m ON i.material_id = m.id
            WHERE i.created_at BETWEEN ? AND ?
            GROUP BY m.id
            HAVING total_qty > 0
            ORDER BY total_qty DESC, tx_count DESC
            LIMIT 10
        ");
        $stmtTopIn->execute([$startDateTime, $endDateTime]);
        $topInbound = $stmtTopIn->fetchAll();

        if (empty($topInbound)) {
            $stmtTopInAll = $pdo->query("
                SELECT 
                    m.id,
                    m.code,
                    m.name,
                    m.unit,
                    m.category,
                    m.rack_location,
                    m.current_stock,
                    COALESCE(SUM(i.qty), 0) AS total_qty,
                    COUNT(i.id) AS tx_count
                FROM inbound_transactions i
                JOIN materials m ON i.material_id = m.id
                GROUP BY m.id
                HAVING total_qty > 0
                ORDER BY total_qty DESC, tx_count DESC
                LIMIT 10
            ");
            $topInbound = $stmtTopInAll->fetchAll();
        }

        // ================= TOP 10 BARANG KELUAR =================
        $stmtTopOut = $pdo->prepare("
            SELECT 
                m.id,
                m.code,
                m.name,
                m.unit,
                m.category,
                m.rack_location,
                m.current_stock,
                COALESCE(SUM(o.qty), 0) AS total_qty,
                COUNT(o.id) AS tx_count
            FROM outbound_transactions o
            JOIN materials m ON o.material_id = m.id
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY m.id
            HAVING total_qty > 0
            ORDER BY total_qty DESC, tx_count DESC
            LIMIT 10
        ");
        $stmtTopOut->execute([$startDateTime, $endDateTime]);
        $topOutbound = $stmtTopOut->fetchAll();

        if (empty($topOutbound)) {
            $stmtTopOutAll = $pdo->query("
                SELECT 
                    m.id,
                    m.code,
                    m.name,
                    m.unit,
                    m.category,
                    m.rack_location,
                    m.current_stock,
                    COALESCE(SUM(o.qty), 0) AS total_qty,
                    COUNT(o.id) AS tx_count
                FROM outbound_transactions o
                JOIN materials m ON o.material_id = m.id
                GROUP BY m.id
                HAVING total_qty > 0
                ORDER BY total_qty DESC, tx_count DESC
                LIMIT 10
            ");
            $topOutbound = $stmtTopOutAll->fetchAll();
        }

        // ================= CATEGORY DISTRIBUTION =================
        $stmtCat = $pdo->query("
            SELECT 
                COALESCE(category, 'Umum') AS category,
                COUNT(*) AS total_sku,
                COALESCE(SUM(current_stock), 0) AS total_stock
            FROM materials
            GROUP BY category
            ORDER BY total_stock DESC
        ");
        $categoryStats = $stmtCat->fetchAll();

        // Total stock in entire warehouse (Physical Total)
        $totalWarehouseStock = (int)$pdo->query("SELECT COALESCE(SUM(current_stock), 0) FROM materials")->fetchColumn();
        $totalMasterSku = (int)$pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
        $totalAllInbound = (int)$pdo->query("SELECT COALESCE(SUM(qty), 0) FROM inbound_transactions")->fetchColumn();
        $totalAllOutbound = (int)$pdo->query("SELECT COALESCE(SUM(qty), 0) FROM outbound_transactions")->fetchColumn();

        // ================= OPERATOR PROCESS KPIS =================
        $taskDateCondition = "";
        $taskParams = [];
        if (!empty($startDateStr) && !empty($endDateStr)) {
            $taskDateCondition = "WHERE DATE(t.created_at) BETWEEN ? AND ?";
            $taskParams = [$startDateStr, $endDateStr];
        }

        // 1. Overall Task Metrics
        $stmtTaskOverall = $pdo->prepare("
            SELECT 
                COUNT(*) AS total_tasks,
                COALESCE(SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END), 0) AS completed_tasks,
                COALESCE(SUM(CASE WHEN t.status = 'IN_PROGRESS' THEN 1 ELSE 0 END), 0) AS in_progress_tasks,
                COALESCE(SUM(CASE WHEN t.status = 'PENDING' THEN 1 ELSE 0 END), 0) AS pending_tasks,
                COALESCE(SUM(CASE WHEN t.status = 'CANCELLED' THEN 1 ELSE 0 END), 0) AS cancelled_tasks,
                COALESCE(SUM(CASE WHEN t.status = 'COMPLETED' THEN t.actual_qty ELSE 0 END), 0) AS total_picked_qty,
                COALESCE(AVG(CASE WHEN t.status = 'COMPLETED' AND t.duration_seconds > 0 THEN t.duration_seconds ELSE NULL END), 0) AS avg_duration_seconds
            FROM tasks t
            {$taskDateCondition}
        ");
        $stmtTaskOverall->execute($taskParams);
        $taskOverall = $stmtTaskOverall->fetch();

        $totT = (int)($taskOverall['total_tasks'] ?? 0);
        $compT = (int)($taskOverall['completed_tasks'] ?? 0);
        $inProgT = (int)($taskOverall['in_progress_tasks'] ?? 0);
        $pendT = (int)($taskOverall['pending_tasks'] ?? 0);
        $cancT = (int)($taskOverall['cancelled_tasks'] ?? 0);
        $pickedQty = (int)($taskOverall['total_picked_qty'] ?? 0);
        $avgDur = round((float)($taskOverall['avg_duration_seconds'] ?? 0));
        $completionRate = $totT > 0 ? round(($compT / $totT) * 100, 1) : 0;

        // 2. Operator Leaderboard / Individual Breakdown
        $stmtOpPerf = $pdo->prepare("
            SELECT 
                u.id AS operator_id,
                u.name AS operator_name,
                COALESCE(u.shift, 'Shift 1') AS operator_shift,
                COUNT(t.id) AS total_assigned,
                COALESCE(SUM(CASE WHEN t.status = 'COMPLETED' THEN 1 ELSE 0 END), 0) AS completed_count,
                COALESCE(SUM(CASE WHEN t.status = 'IN_PROGRESS' THEN 1 ELSE 0 END), 0) AS in_progress_count,
                COALESCE(SUM(CASE WHEN t.status = 'PENDING' THEN 1 ELSE 0 END), 0) AS pending_count,
                COALESCE(SUM(CASE WHEN t.status = 'COMPLETED' THEN t.actual_qty ELSE 0 END), 0) AS total_picked_qty,
                COALESCE(AVG(CASE WHEN t.status = 'COMPLETED' AND t.duration_seconds > 0 THEN t.duration_seconds ELSE NULL END), 0) AS avg_duration_seconds
            FROM users u
            LEFT JOIN tasks t ON t.assigned_to = u.id " . ($taskDateCondition ? "AND DATE(t.created_at) BETWEEN ? AND ?" : "") . "
            WHERE LOWER(u.role) = 'operator' AND LOWER(u.username) NOT IN ('admin', 'superadmin', 'daniel')
            GROUP BY u.id
            ORDER BY completed_count DESC, total_picked_qty DESC
        ");
        $stmtOpPerf->execute($taskParams);
        $operatorLeaderboard = $stmtOpPerf->fetchAll();

        // 3. Active / Recent Tasks for Dashboard Realtime Feed
        $stmtRecentTasks = $pdo->prepare("
            SELECT 
                t.id, t.task_no, t.priority, t.status, t.destination, t.target_qty, t.actual_qty, t.duration_seconds, t.created_at,
                m.name AS material_name, m.code AS material_code, m.rack_location, m.unit AS material_unit,
                u.name AS operator_name, u.shift AS operator_shift
            FROM tasks t
            JOIN materials m ON t.material_id = m.id
            JOIN users u ON t.assigned_to = u.id
            {$taskDateCondition}
            ORDER BY (t.status = 'IN_PROGRESS') DESC, (t.status = 'PENDING') DESC, (t.priority = 'URGENT') DESC, t.created_at DESC
            LIMIT 6
        ");
        $stmtRecentTasks->execute($taskParams);
        $recentTasks = $stmtRecentTasks->fetchAll();

        echo json_encode([
            'success' => true,
            'period' => [
                'type'        => $filterType,
                'label'       => $periodLabel,
                'start_date'  => $startDateStr,
                'end_date'    => $endDateStr
            ],
            'summary' => [
                'total_sku'             => $activeSkuCount,
                'total_master_sku'      => $totalMasterSku,
                'total_inbound'         => $sumInbound,
                'total_outbound'        => $sumOutbound,
                'total_adjustment'      => $sumAdjustment,
                'total_adjustment_plus' => $sumAdjustmentPlus,
                'total_adjustment_minus'=> $sumAdjustmentMinus,
                'total_ending_stock'    => $sumEndingStock,
                'total_warehouse_stock' => $totalWarehouseStock,
                'total_all_inbound'     => $totalAllInbound,
                'total_all_outbound'    => $totalAllOutbound,
                'net_flow'              => $sumInbound - $sumOutbound + $sumAdjustment,
                'low_stock_count'       => $lowStockCount,
                'out_of_stock_count'    => $outOfStockCount,
                'critical_stock_count'  => $lowStockCount + $outOfStockCount
            ],
            'operator_kpi' => [
                'total_tasks'          => $totT,
                'completed_tasks'      => $compT,
                'in_progress_tasks'    => $inProgT,
                'pending_tasks'        => $pendT,
                'cancelled_tasks'      => $cancT,
                'completion_rate'      => $completionRate,
                'total_picked_qty'     => $pickedQty,
                'avg_duration_seconds' => $avgDur,
                'leaderboard'          => $operatorLeaderboard,
                'recent_tasks'         => $recentTasks
            ],
            'top_inbound'    => $topInbound,
            'top_outbound'   => $topOutbound,
            'category_stats' => $categoryStats,
            'data'           => $data
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
