<?php
// includes/batch_helper.php - Shared helpers for Batch, Exp Date, and Location management
require_once __DIR__ . '/../config/database.php';

if (!function_exists('recalcMaterialBatchStock')) {
    function recalcMaterialBatchStock(PDO $pdo, int $materialId): void {
        if ($materialId <= 0) return;

        // Check if this material has any batches recorded
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM material_batches WHERE material_id = ?");
        $stmtCount->execute([$materialId]);
        if ((int)$stmtCount->fetchColumn() === 0) {
            return;
        }

        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN location = 'Gudang Kecil' THEN qty ELSE 0 END), 0) as kecil_qty,
                COALESCE(SUM(CASE WHEN location = 'Gudang Besar' THEN qty ELSE 0 END), 0) as besar_qty
            FROM material_batches
            WHERE material_id = ?
        ");
        $stmt->execute([$materialId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $kecilQty = max(0, (float)($row['kecil_qty'] ?? 0));
        $besarQty = max(0, (float)($row['besar_qty'] ?? 0));

        // PENTING: JANGAN menulis current_stock di sini.
        // material_batches hanya diisi oleh alur inbound/outbound/vas, sedangkan
        // adjust_stock, tasks (TASK_PICKING), dan opnames mengubah current_stock tanpa
        // menyentuh batch. Menimpa current_stock dengan SUM(material_batches) membuat
        // seluruh pergerakan non-batch terhapus diam-diam pada inbound berikutnya.
        // Sumber kebenaran stok adalah current_stock + buku mutasi (stock_mutations).
        $stmtUp = $pdo->prepare("
            UPDATE materials
            SET qty_gudang_kecil = ?, qty_gudang_besar = ?, updated_at = ?
            WHERE id = ?
        ");
        $stmtUp->execute([$kecilQty, $besarQty, date('Y-m-d H:i:s'), $materialId]);
    }
}

if (!function_exists('normalizeExpDateToDb')) {
    function normalizeExpDateToDb(?string $expDate): ?string {
        if (empty($expDate)) return null;
        $expDate = trim($expDate);
        if ($expDate === '-' || strtolower($expDate) === 'null') return null;

        // If format DD-MM-YY or DD-MM-YYYY or DD/MM/YY or DD/MM/YYYY
        if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})$/', $expDate, $m)) {
            $day = (int)$m[1];
            $month = (int)$m[2];
            $year = (int)$m[3];
            if ($year < 100) {
                $year += ($year <= 69 ? 2000 : 1900);
            }
            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // If format YYYY-MM-DD
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $expDate, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
        }

        $ts = strtotime($expDate);
        if ($ts !== false && $ts > 0) {
            return date('Y-m-d', $ts);
        }

        return $expDate;
    }
}

if (!function_exists('formatExpDateToDisplay')) {
    function formatExpDateToDisplay(?string $expDate): string {
        if (empty($expDate)) return '';
        $norm = normalizeExpDateToDb($expDate);
        if (!$norm) return '';
        $ts = strtotime($norm);
        if ($ts === false) return $expDate;
        return date('d-m-y', $ts); // Format DD-MM-YY
    }
}

if (!function_exists('recordBatchInbound')) {
    function recordBatchInbound(PDO $pdo, int $materialId, ?string $batchNo, ?string $expDate, ?string $location, float $qty, ?string $notes = null): ?int {
        if ($materialId <= 0 || $qty <= 0) return null;

        $batchNo = trim($batchNo ?? '');
        $expDate = normalizeExpDateToDb($expDate);
        $location = trim($location ?? 'Gudang Besar');
        if (empty($location) || strtolower($location) === 'pusat' || $location === 'Gudang Kecil') {
            $location = 'Gudang Besar';
        }

        $now = date('Y-m-d H:i:s');

        // Check if there is an existing batch record for this material, batch_no and location
        if (!empty($batchNo)) {
            $stmtFind = $pdo->prepare("
                SELECT id, qty, exp_date FROM material_batches 
                WHERE material_id = ? AND batch_no = ? AND location = ?
                LIMIT 1
            ");
            $stmtFind->execute([$materialId, $batchNo, $location]);
            $existing = $stmtFind->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $newQty = (float)$existing['qty'] + $qty;
                $finalExp = $expDate ?: $existing['exp_date'];
                $stmtUp = $pdo->prepare("
                    UPDATE material_batches 
                    SET qty = ?, exp_date = ?, notes = COALESCE(?, notes), updated_at = ?
                    WHERE id = ?
                ");
                $stmtUp->execute([$newQty, $finalExp, $notes, $now, $existing['id']]);
                recalcMaterialBatchStock($pdo, $materialId);
                return (int)$existing['id'];
            }
        }

        // Insert new batch row if batch_no provided or if materials is Gimmick
        if (empty($batchNo)) {
            $batchNo = 'BATCH-' . date('ymd') . '-' . substr(md5(uniqid()), 0, 4);
        }

        $stmtIns = $pdo->prepare("
            INSERT INTO material_batches (material_id, batch_no, exp_date, location, qty, notes, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtIns->execute([$materialId, $batchNo, $expDate, $location, $qty, $notes, $now, $now]);
        $newId = (int)$pdo->lastInsertId();

        recalcMaterialBatchStock($pdo, $materialId);
        return $newId;
    }
}

if (!function_exists('recordBatchOutbound')) {
    function recordBatchOutbound(PDO $pdo, int $materialId, ?int $batchId, ?string $batchNo, ?string $location, float $qty): bool {
        if ($materialId <= 0 || $qty <= 0) return false;

        $now = date('Y-m-d H:i:s');

        // Check if material has batches
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM material_batches WHERE material_id = ? AND qty > 0");
        $stmtCount->execute([$materialId]);
        if ((int)$stmtCount->fetchColumn() === 0) {
            return false;
        }

        // 1. By specific batch ID if supplied
        if (!empty($batchId) && $batchId > 0) {
            $stmt = $pdo->prepare("SELECT id, qty FROM material_batches WHERE id = ? AND material_id = ?");
            $stmt->execute([$batchId, $materialId]);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($batch) {
                $newQty = max(0, (float)$batch['qty'] - $qty);
                $up = $pdo->prepare("UPDATE material_batches SET qty = ?, updated_at = ? WHERE id = ?");
                $up->execute([$newQty, $now, $batch['id']]);
                recalcMaterialBatchStock($pdo, $materialId);
                return true;
            }
        }

        // 2. By batch_no and location
        if (!empty($batchNo)) {
            $params = [$materialId, $batchNo];
            $sql = "SELECT id, qty FROM material_batches WHERE material_id = ? AND batch_no = ?";
            if (!empty($location) && strtolower($location) !== 'pusat') {
                $sql .= " AND location = ?";
                $params[] = $location;
            }
            $sql .= " ORDER BY qty DESC LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $batch = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($batch) {
                $newQty = max(0, (float)$batch['qty'] - $qty);
                $up = $pdo->prepare("UPDATE material_batches SET qty = ?, updated_at = ? WHERE id = ?");
                $up->execute([$newQty, $now, $batch['id']]);
                recalcMaterialBatchStock($pdo, $materialId);
                return true;
            }
        }

        // 3. Fallback to FEFO (Earliest Exp Date First)
        $stmtFefo = $pdo->prepare("
            SELECT id, qty FROM material_batches 
            WHERE material_id = ? AND qty > 0 
            ORDER BY (CASE WHEN exp_date IS NULL OR exp_date = '' THEN '9999-12-31' ELSE exp_date END) ASC, id ASC
        ");
        $stmtFefo->execute([$materialId]);
        $remainingToDeduct = $qty;

        while ($b = $stmtFefo->fetch(PDO::FETCH_ASSOC)) {
            if ($remainingToDeduct <= 0) break;
            $currentQty = (float)$b['qty'];
            $deduct = min($currentQty, $remainingToDeduct);
            $newQty = max(0, $currentQty - $deduct);

            $up = $pdo->prepare("UPDATE material_batches SET qty = ?, updated_at = ? WHERE id = ?");
            $up->execute([$newQty, $now, $b['id']]);
            $remainingToDeduct -= $deduct;
        }

        recalcMaterialBatchStock($pdo, $materialId);
        return true;
    }
}

if (!function_exists('recordBatchTransfer')) {
    function recordBatchTransfer(PDO $pdo, int $materialId, ?int $batchId, ?string $batchNo, string $fromLoc, string $toLoc, float $qty): bool {
        if ($materialId <= 0 || $qty <= 0) return false;

        $now = date('Y-m-d H:i:s');
        $fromLoc = trim($fromLoc);
        $toLoc = trim($toLoc);
        if (empty($fromLoc) || $fromLoc === 'Gudang Kecil') $fromLoc = 'Gudang Besar';
        if (empty($toLoc) || $toLoc === 'Gudang Kecil') $toLoc = 'VAS';

        // 1. Locate source batch
        $sourceBatch = null;
        if (!empty($batchId) && $batchId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM material_batches WHERE id = ? AND material_id = ?");
            $stmt->execute([$batchId, $materialId]);
            $sourceBatch = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$sourceBatch && !empty($batchNo)) {
            $stmt = $pdo->prepare("SELECT * FROM material_batches WHERE material_id = ? AND batch_no = ? AND location = ? ORDER BY qty DESC LIMIT 1");
            $stmt->execute([$materialId, $batchNo, $fromLoc]);
            $sourceBatch = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$sourceBatch) {
            // Find any batch at fromLoc with qty > 0
            $stmt = $pdo->prepare("SELECT * FROM material_batches WHERE material_id = ? AND location = ? AND qty > 0 ORDER BY qty DESC LIMIT 1");
            $stmt->execute([$materialId, $fromLoc]);
            $sourceBatch = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($sourceBatch) {
            $transferQty = min($qty, (float)$sourceBatch['qty']);
            $newSourceQty = max(0, (float)$sourceBatch['qty'] - $transferQty);
            $upSrc = $pdo->prepare("UPDATE material_batches SET qty = ?, updated_at = ? WHERE id = ?");
            $upSrc->execute([$newSourceQty, $now, $sourceBatch['id']]);

            // Add or increment target batch at $toLoc
            $stmtTgt = $pdo->prepare("SELECT id, qty FROM material_batches WHERE material_id = ? AND batch_no = ? AND location = ? LIMIT 1");
            $stmtTgt->execute([$materialId, $sourceBatch['batch_no'], $toLoc]);
            $tgt = $stmtTgt->fetch(PDO::FETCH_ASSOC);

            if ($tgt) {
                $newTgtQty = (float)$tgt['qty'] + $transferQty;
                $upTgt = $pdo->prepare("UPDATE material_batches SET qty = ?, updated_at = ? WHERE id = ?");
                $upTgt->execute([$newTgtQty, $now, $tgt['id']]);
            } else {
                $insTgt = $pdo->prepare("
                    INSERT INTO material_batches (material_id, batch_no, exp_date, location, qty, notes, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insTgt->execute([$materialId, $sourceBatch['batch_no'], $sourceBatch['exp_date'], $toLoc, $transferQty, "Transfer dari {$fromLoc}", $now, $now]);
            }

            recalcMaterialBatchStock($pdo, $materialId);
            return true;
        }

        return false;
    }
}

if (!function_exists('getBatchMovementBreakdown')) {
    function getBatchMovementBreakdown(PDO $pdo, ?int $targetMaterialId = null): array {
        try {
            $matWhere = $targetMaterialId && $targetMaterialId > 0 ? "WHERE material_id = " . (int)$targetMaterialId : "";
            $matWhereAnd = $targetMaterialId && $targetMaterialId > 0 ? "AND material_id = " . (int)$targetMaterialId : "";

            // Inbounds map
            $inbMap = [];
            $stmtInb = $pdo->query("
                SELECT material_id, 
                       COALESCE(batch_no, '') as batch_no, 
                       COALESCE(location, '') as location, 
                       SUM(qty) as total_qty
                FROM inbound_transactions
                {$matWhere}
                GROUP BY material_id, COALESCE(batch_no, ''), COALESCE(location, '')
            ");
            if ($stmtInb) {
                while ($r = $stmtInb->fetch(PDO::FETCH_ASSOC)) {
                    $bNo = strtoupper(trim($r['batch_no']));
                    $loc = strtoupper(trim($r['location']));
                    $key = $r['material_id'] . '|' . $bNo . '|' . $loc;
                    $inbMap[$key] = (float)$r['total_qty'];
                }
            }

            // Outbounds map
            $outMap = [];
            $stmtOut = $pdo->query("
                SELECT material_id, 
                       COALESCE(batch_no, '') as batch_no, 
                       COALESCE(location, '') as location, 
                       SUM(qty) as total_qty
                FROM outbound_transactions
                {$matWhere}
                GROUP BY material_id, COALESCE(batch_no, ''), COALESCE(location, '')
            ");
            if ($stmtOut) {
                while ($r = $stmtOut->fetch(PDO::FETCH_ASSOC)) {
                    $bNo = strtoupper(trim($r['batch_no']));
                    $loc = strtoupper(trim($r['location']));
                    $key = $r['material_id'] . '|' . $bNo . '|' . $loc;
                    $outMap[$key] = (float)$r['total_qty'];
                }
            }

            // VAS transfers map
            $vasOutFromWhMap = [];
            $vasInToWhMap = [];
            $stmtVasTx = $pdo->query("
                SELECT material_id, type, 
                       COALESCE(batch_no, '') as batch_no, 
                       COALESCE(from_location, '') as from_location, 
                       COALESCE(to_location, '') as to_location, 
                       SUM(qty) as total_qty
                FROM vas_transactions
                {$matWhere}
                GROUP BY material_id, type, COALESCE(batch_no, ''), COALESCE(from_location, ''), COALESCE(to_location, '')
            ");
            if ($stmtVasTx) {
                while ($r = $stmtVasTx->fetch(PDO::FETCH_ASSOC)) {
                    $type = $r['type'];
                    $bNo = strtoupper(trim($r['batch_no']));
                    if ($type === 'TRANSFER_IN') {
                        $fromLoc = strtoupper(trim($r['from_location']));
                        $key = $r['material_id'] . '|' . $bNo . '|' . $fromLoc;
                        $vasOutFromWhMap[$key] = ($vasOutFromWhMap[$key] ?? 0) + (float)$r['total_qty'];
                    } elseif ($type === 'TRANSFER_OUT') {
                        $toLoc = strtoupper(trim($r['to_location']));
                        $key = $r['material_id'] . '|' . $bNo . '|' . $toLoc;
                        $vasInToWhMap[$key] = ($vasInToWhMap[$key] ?? 0) + (float)$r['total_qty'];
                    }
                }
            }

            // VAS net stock map per material_id and batch_no
            $vasStockMap = [];
            $stmtVasStock = $pdo->query("
                SELECT material_id, COALESCE(batch_no, '') as batch_no, 
                       SUM(CASE WHEN type = 'TRANSFER_IN' THEN qty WHEN type IN ('TRANSFER_OUT', 'VAS_OUTBOUND') THEN -qty ELSE 0 END) as net_qty
                FROM vas_transactions
                {$matWhere}
                GROUP BY material_id, COALESCE(batch_no, '')
            ");
            if ($stmtVasStock) {
                while ($r = $stmtVasStock->fetch(PDO::FETCH_ASSOC)) {
                    $bNo = strtoupper(trim($r['batch_no']));
                    $key = $r['material_id'] . '|' . $bNo;
                    $vasStockMap[$key] = max(0, (float)$r['net_qty']);
                }
            }

            // Also check material_batches where location like VAS
            $stmtMatBatchesVas = $pdo->query("
                SELECT material_id, COALESCE(batch_no, '') as batch_no, SUM(qty) as total_qty
                FROM material_batches
                WHERE UPPER(location) LIKE '%VAS%' {$matWhereAnd}
                GROUP BY material_id, COALESCE(batch_no, '')
            ");
            if ($stmtMatBatchesVas) {
                while ($r = $stmtMatBatchesVas->fetch(PDO::FETCH_ASSOC)) {
                    $bNo = strtoupper(trim($r['batch_no']));
                    $key = $r['material_id'] . '|' . $bNo;
                    $vasStockMap[$key] = ($vasStockMap[$key] ?? 0) + (float)$r['total_qty'];
                }
            }

            // Fetch batches (excluding pure VAS rows, sorted)
            $batchSql = "
                SELECT id, material_id, batch_no, exp_date, location, qty, notes, created_at, updated_at
                FROM material_batches
                WHERE UPPER(location) NOT LIKE '%VAS%' {$matWhereAnd}
                ORDER BY material_id ASC, (CASE WHEN location IS NULL OR location = '' OR location = 'Pusat' OR location = '-' THEN 1 ELSE 0 END), location ASC, (CASE WHEN exp_date IS NULL OR exp_date = '' THEN 1 ELSE 0 END), exp_date ASC, id ASC
            ";
            $stmtBatches = $pdo->query($batchSql);
            $batches = $stmtBatches ? $stmtBatches->fetchAll(PDO::FETCH_ASSOC) : [];

            $resultMap = [];
            foreach ($batches as $b) {
                $mid = (int)$b['material_id'];
                $bNo = strtoupper(trim($b['batch_no'] ?? ''));
                $loc = strtoupper(trim($b['location'] ?? ''));
                $key = "{$mid}|{$bNo}|{$loc}";
                $keyNoLoc = "{$mid}|{$bNo}|";

                $inbound = ($inbMap[$key] ?? 0) + ($inbMap[$keyNoLoc] ?? 0) + ($vasInToWhMap[$key] ?? 0);
                $outbound = ($outMap[$key] ?? 0) + ($outMap[$keyNoLoc] ?? 0) + ($vasOutFromWhMap[$key] ?? 0);

                if ($outbound == 0) {
                    $vasKeyBatchOnly = "{$mid}|{$bNo}";
                    foreach ($vasOutFromWhMap as $vk => $vq) {
                        if (str_starts_with($vk, $vasKeyBatchOnly . '|')) {
                            $outbound += $vq;
                        }
                    }
                }

                $endingStock = max(0, (float)$b['qty']);
                $initialStock = max(0, $endingStock - $inbound + $outbound);
                $vasQty = max(0, (float)($vasStockMap["{$mid}|{$bNo}"] ?? 0));

                $resultMap[$mid][] = [
                    'id' => (int)$b['id'],
                    'material_id' => $mid,
                    'batch_no' => $b['batch_no'],
                    'exp_date' => $b['exp_date'],
                    'location' => $b['location'],
                    'initial_stock' => $initialStock,
                    'total_inbound' => $inbound,
                    'total_outbound' => $outbound,
                    'qty' => $endingStock,
                    'ending_stock' => $endingStock,
                    'vas_qty' => $vasQty,
                    'notes' => $b['notes'] ?? ''
                ];
            }

            return $resultMap;
        } catch (Throwable $e) {
            error_log('[PackStock] Error in getBatchMovementBreakdown: ' . $e->getMessage());
            return [];
        }
    }
}
