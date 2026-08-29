<?php
// api/adjust_stock.php - Upload Excel Stock Adjustment (+ / -) to Master Inventory API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAdmin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'preview';

/**
 * Helper: Parse Excel (.xlsx) file natively using ZipArchive & SimpleXML
 */
function parseAdjustXlsx($filePath) {
    if (!file_exists($filePath)) return false;
    
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return false;

    $sharedStrings = [];
    $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($sharedXml) {
        $xml = simplexml_load_string($sharedXml);
        if ($xml) {
            foreach ($xml->si as $si) {
                if (isset($si->t)) {
                    $sharedStrings[] = (string)$si->t;
                } elseif (isset($si->r)) {
                    $text = '';
                    foreach ($si->r as $r) {
                        $text .= (string)$r->t;
                    }
                    $sharedStrings[] = $text;
                } else {
                    $sharedStrings[] = '';
                }
            }
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$sheetXml) {
        for ($i = 1; $i <= 10; $i++) {
            $sheetXml = $zip->getFromName("xl/worksheets/sheet{$i}.xml");
            if ($sheetXml) break;
        }
    }
    if (!$sheetXml) {
        $zip->close();
        return false;
    }

    $xml = simplexml_load_string($sheetXml);
    if (!$xml || !isset($xml->sheetData)) {
        $zip->close();
        return false;
    }

    $rows = [];
    foreach ($xml->sheetData->row as $row) {
        $rowCells = [];
        $maxColIdx = 0;
        foreach ($row->c as $cell) {
            $cellRef = (string)$cell['r'];
            $cellType = (string)$cell['t'];
            $val = isset($cell->v) ? (string)$cell->v : '';

            if ($cellType === 's' && isset($sharedStrings[(int)$val])) {
                $cellValue = $sharedStrings[(int)$val];
            } elseif ($cellType === 'inlineStr' && isset($cell->is->t)) {
                $cellValue = (string)$cell->is->t;
            } elseif ($cellType === 'inlineStr' && isset($cell->is)) {
                // Rich text inline string
                $text = '';
                foreach ($cell->is->children() as $child) {
                    if ($child->getName() === 't') {
                        $text .= (string)$child;
                    } elseif ($child->getName() === 'r' && isset($child->t)) {
                        $text .= (string)$child->t;
                    }
                }
                $cellValue = $text;
            } else {
                $cellValue = $val;
            }

            preg_match('/([A-Z]+)/', $cellRef, $matches);
            $colLetters = $matches[1] ?? 'A';
            $colIdx = 0;
            for ($c = 0; $c < strlen($colLetters); $c++) {
                $colIdx = $colIdx * 26 + (ord($colLetters[$c]) - ord('A') + 1);
            }
            $colIdx -= 1;
            $rowCells[$colIdx] = trim($cellValue);
            if ($colIdx > $maxColIdx) $maxColIdx = $colIdx;
        }

        $denseRow = [];
        for ($c = 0; $c <= $maxColIdx; $c++) {
            $denseRow[$c] = $rowCells[$c] ?? '';
        }
        if (!empty(array_filter($denseRow, 'strlen'))) {
            $rows[] = $denseRow;
        }
    }
    $zip->close();
    return $rows;
}

// =========================================================================
// 1. DOWNLOAD TEMPLATE CSV UNTUK PENYESUAIAN STOK (+ / -)
// =========================================================================
if ($action === 'template') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Template_Penyesuaian_Stok_Adjust.csv"');
    
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Item No', 'Item Description', 'Qty Adjust (+/-)', 'Notes / Alasan']);
    fputcsv($output, ['4000010001', 'Dus E-commerce Hanasui Uk. Kecil', '+150', 'Penyesuaian Hasil Stock Opname (Surplus Fisik)']);
    fputcsv($output, ['4000010002', 'Dus E-commerce Hanasui Uk. Besar', '-20', 'Penyesuaian Hasil Stock Opname (Barang Rusak/Reject)']);
    fputcsv($output, ['4000020001', 'Plastik Hanasui Ukuran Besar', '+500', 'Koreksi Selisih Opname Lapangan']);
    fclose($output);
    exit;
}

// =========================================================================
// 2. PARSE / PREVIEW EXCEL ADJUSTMENT FILE
// =========================================================================
if ($action === 'preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rows = [];

    if (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $filePath = $_FILES['file']['tmp_name'];
        $origName = strtolower($_FILES['file']['name']);

        if (str_ends_with($origName, '.xlsx') || str_ends_with($origName, '.xlsm')) {
            $rows = parseAdjustXlsx($filePath);
        }

        if (empty($rows)) {
            $handle = fopen($filePath, 'r');
            if ($handle) {
                $firstLine = fgets($handle);
                rewind($handle);
                $delimiter = ',';
                if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                    $delimiter = ';';
                } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
                    $delimiter = "\t";
                }

                while (($data = fgetcsv($handle, 4096, $delimiter)) !== false) {
                    if (empty(array_filter($data, 'strlen'))) continue;
                    $rows[] = $data;
                }
                fclose($handle);
            }
        }
    } else {
        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $rawText = trim($input['raw_text'] ?? '');
        if (empty($rawText)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Silakan upload file Excel/CSV atau paste baris teks penyesuaian stok.']);
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
        echo json_encode(['success' => false, 'message' => 'Format file tidak dapat dibaca atau file kosong.']);
        exit;
    }

    // Header mapping
    $headerRowIdx = 0;
    // Look for header row in top 5 rows (in case file has titles)
    for ($r = 0; $r < min(5, count($rows)); $r++) {
        $lineLower = strtolower(implode(' ', $rows[$r]));
        if (strpos($lineLower, 'item no') !== false || strpos($lineLower, 'item_no') !== false || strpos($lineLower, 'kode item') !== false || strpos($lineLower, 'sku') !== false) {
            $headerRowIdx = $r;
            break;
        }
    }

    $rawHeaders = $rows[$headerRowIdx];
    $cleanHeaders = array_map(function($h) {
        $str = strtolower(trim((string)$h));
        return preg_replace('/[^a-z0-9]/', '', $str);
    }, $rawHeaders);

    $itemNoIdx = -1;
    $descIdx   = -1;
    $adjustIdx = -1;
    $notesIdx  = -1;

    foreach ($cleanHeaders as $idx => $clean) {
        if (in_array($clean, ['itemno', 'itemnumber', 'kodeitem', 'kode', 'code', 'sku', 'kodematerial', 'materialcode'])) {
            if ($itemNoIdx === -1) $itemNoIdx = $idx;
        } elseif (in_array($clean, ['itemdescription', 'description', 'deskripsi', 'namabarang', 'namaitem', 'namapackaging', 'namamaterial', 'nama', 'materialname'])) {
            if ($descIdx === -1) $descIdx = $idx;
        } elseif (in_array($clean, ['qtyadjust', 'adjustqty', 'adjust', 'penyesuaian', 'qty', 'selisihadjust', 'selisih', 'diff', 'difference', 'perubahanstok', 'selisihstok', 'selisihfisik']) 
                  || strpos($clean, 'adjust') !== false || strpos($clean, 'selisih') !== false || strpos($clean, 'diff') !== false) {
            if ($adjustIdx === -1) $adjustIdx = $idx;
        } elseif (in_array($clean, ['notesalasan', 'notes', 'alasan', 'keterangan', 'catatan', 'reason', 'note', 'keteranganselisih'])
                  || strpos($clean, 'alasan') !== false || strpos($clean, 'catatan') !== false || strpos($clean, 'note') !== false) {
            if ($notesIdx === -1) $notesIdx = $idx;
        }
    }

    if ($itemNoIdx === -1) $itemNoIdx = 0;
    if ($adjustIdx === -1) {
        // Find first column containing numeric numbers if not detected
        $adjustIdx = 2;
        if (count($cleanHeaders) <= 2) $adjustIdx = 1;
    }

    // Load existing materials
    $stmtMat = $pdo->query("SELECT id, code, name, unit, category, rack_location, current_stock FROM materials");
    $existing = [];
    while ($m = $stmtMat->fetch()) {
        $existing[strtoupper($m['code'])] = $m;
    }

    $parsedItems = [];
    $validCount = 0;
    $warningCount = 0;
    $errorCount = 0;
    $totalPlus = 0;
    $totalMinus = 0;

    for ($i = $headerRowIdx + 1; $i < count($rows); $i++) {
        $r = $rows[$i];
        if (empty(array_filter($r, 'strlen'))) continue;

        $itemNo = strtoupper(trim($r[$itemNoIdx] ?? ''));
        if (empty($itemNo)) continue;

        $rawAdjust = trim($r[$adjustIdx] ?? '0');
        // Clean adjust value: keep sign (+ / - / parenthesis) and digits
        $sign = 1;
        if (strpos($rawAdjust, '-') !== false || (str_starts_with($rawAdjust, '(') && str_ends_with($rawAdjust, ')'))) {
            $sign = -1;
        }
        $digits = preg_replace('/[^0-9\.]/', '', str_replace(',', '.', $rawAdjust));
        $qtyAdjust = ($digits !== '') ? $sign * (float)$digits : 0;

        $notes = ($notesIdx !== -1 && isset($r[$notesIdx])) ? trim($r[$notesIdx]) : 'Penyesuaian Stok Excel';

        $isFound = isset($existing[$itemNo]);
        if (!$isFound) {
            $errorCount++;
            $parsedItems[] = [
                'row_num' => $i + 1,
                'item_no' => $itemNo,
                'item_name' => ($descIdx !== -1 && isset($r[$descIdx])) ? trim($r[$descIdx]) : $itemNo,
                'unit' => '-',
                'rack_location' => '-',
                'stock_before' => 0,
                'qty_adjust' => $qtyAdjust,
                'stock_after' => 0,
                'notes' => $notes,
                'status' => 'NOT_FOUND',
                'status_label' => 'Item Tidak Ditemukan'
            ];
            continue;
        }

        $mat = $existing[$itemNo];
        $stockBefore = (int)$mat['current_stock'];
        $stockAfter = $stockBefore + $qtyAdjust;

        if ($qtyAdjust > 0) $totalPlus += $qtyAdjust;
        else $totalMinus += abs($qtyAdjust);

        $status = 'VALID';
        $statusLabel = 'Valid (Siap Disesuaikan)';

        if ($stockAfter < 0) {
            $status = 'WARNING_NEGATIVE';
            $statusLabel = 'Peringatan: Stok Akhir Menjadi Negatif (< 0)';
            $warningCount++;
        } else {
            $validCount++;
        }

        $parsedItems[] = [
            'row_num' => $i + 1,
            'material_id' => (int)$mat['id'],
            'item_no' => $mat['code'],
            'item_name' => $mat['name'],
            'unit' => $mat['unit'],
            'rack_location' => $mat['rack_location'],
            'stock_before' => $stockBefore,
            'qty_adjust' => $qtyAdjust,
            'stock_after' => $stockAfter,
            'notes' => $notes,
            'status' => $status,
            'status_label' => $statusLabel
        ];
    }

    echo json_encode([
        'success' => true,
        'summary' => [
            'total_rows' => count($parsedItems),
            'valid_count' => $validCount,
            'warning_count' => $warningCount,
            'error_count' => $errorCount,
            'total_plus' => $totalPlus,
            'total_minus' => $totalMinus
        ],
        'items' => $parsedItems
    ]);
    exit;
}

// =========================================================================
// 3. COMMIT STOCK ADJUSTMENT (Execute changes & record in mutations)
// =========================================================================
if ($action === 'commit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $items = $input['items'] ?? [];
    $batchNotes = trim($input['batch_notes'] ?? 'Upload Excel Penyesuaian Stok');

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tidak ada item data penyesuaian untuk diproses.']);
        exit;
    }

    $refNo = 'ADJ-' . date('Ymd-His');
    $userId = Auth::id();

    try {
        $pdo->beginTransaction();

        $stmtGetMat = $pdo->prepare("SELECT id, current_stock FROM materials WHERE code = ?");
        $stmtUpdateMat = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id)
            VALUES (?, 'ADJUSTMENT', ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsertInbound = $pdo->prepare("
            INSERT INTO inbound_transactions (inbound_no, po_number, supplier, material_id, qty, notes, received_by, started_at, completed_at, duration_seconds)
            VALUES (?, 'ADJUSTMENT', 'SYSTEM', ?, ?, ?, ?, NOW(), NOW(), 0)
        ");
        $stmtInsertOutbound = $pdo->prepare("
            INSERT INTO outbound_transactions (outbound_no, material_id, qty, destination, issued_by, reason, notes, started_at, completed_at, duration_seconds)
            VALUES (?, ?, ?, 'SYSTEM', ?, 'ADJUSTMENT', ?, NOW(), NOW(), 0)
        ");

        $appliedCount = 0;

        foreach ($items as $item) {
            $code = strtoupper(trim($item['item_no']));
            $qtyAdjust = (int)$item['qty_adjust'];
            $notes = trim($item['notes'] ?? $batchNotes) ?: $batchNotes;

            if (empty($code) || $qtyAdjust === 0) continue;

            $stmtGetMat->execute([$code]);
            $mat = $stmtGetMat->fetch();

            if (!$mat) continue;

            $matId = (int)$mat['id'];
            $stockBefore = (int)$mat['current_stock'];
            $stockAfter = $stockBefore + $qtyAdjust;

            $stmtUpdateMat->execute([$stockAfter, $matId]);
            $stmtMut->execute([
                $matId,
                $qtyAdjust,
                $stockBefore,
                $stockAfter,
                $refNo,
                $notes,
                $userId
            ]);

            $itemRefNo = $refNo . '-' . ($appliedCount + 1);
            $userName = Auth::name() ?? 'SYSTEM';
            if ($qtyAdjust > 0) {
                $stmtInsertInbound->execute([
                    $itemRefNo,
                    $matId,
                    $qtyAdjust,
                    $notes,
                    $userName
                ]);
            } else {
                $stmtInsertOutbound->execute([
                    $itemRefNo,
                    $matId,
                    abs($qtyAdjust),
                    $userName,
                    $notes
                ]);
            }

            $appliedCount++;
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Penyesuaian stok berhasil disimpan! {$appliedCount} packaging material telah disesuaikan.",
            'applied_count' => $appliedCount,
            'reference_no' => $refNo
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memproses penyesuaian stok: ' . $e->getMessage()]);
    }
    exit;
}

// =========================================================================
// 4. MANUAL SINGLE ADJUSTMENT (PLUS OR MINUS)
// =========================================================================
if ($action === 'manual_adjust' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $materialId = (int)($input['material_id'] ?? 0);
    $adjustType = strtoupper(trim($input['adjust_type'] ?? 'PLUS')); // PLUS or MINUS
    $adjustQty  = abs((int)($input['adjust_qty'] ?? 0));
    $notes      = trim($input['notes'] ?? '');

    if ($materialId <= 0 || $adjustQty <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Material dan jumlah penyesuaian wajib diisi (minimal 1)']);
        exit;
    }

    $delta = ($adjustType === 'MINUS') ? -$adjustQty : $adjustQty;
    $refNo = 'ADJ-' . ($adjustType === 'MINUS' ? 'MIN-' : 'PLS-') . date('Ymd-His');
    $userId = Auth::id();

    try {
        $pdo->beginTransaction();

        $stmtMat = $pdo->prepare("SELECT id, code, name, unit, current_stock FROM materials WHERE id = ?");
        $stmtMat->execute([$materialId]);
        $mat = $stmtMat->fetch();

        if (!$mat) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Material packaging tidak ditemukan']);
            exit;
        }

        $stockBefore = (int)$mat['current_stock'];
        $stockAfter = $stockBefore + $delta;

        $stmtUp = $pdo->prepare("UPDATE materials SET current_stock = ? WHERE id = ?");
        $stmtUp->execute([$stockAfter, $materialId]);

        $fullNotes = "Penyesuaian " . ($adjustType === 'MINUS' ? 'Minus (-)' : 'Plus (+)') . ($notes ? ": {$notes}" : '');

        $stmtMut = $pdo->prepare("
            INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id)
            VALUES (?, 'ADJUSTMENT', ?, ?, ?, ?, ?, ?)
        ");
        $stmtMut->execute([$materialId, $delta, $stockBefore, $stockAfter, $refNo, $fullNotes, $userId]);

        $userName = Auth::name() ?? 'SYSTEM';
        if ($adjustType === 'PLUS') {
            $stmtInsertInbound = $pdo->prepare("
                INSERT INTO inbound_transactions (inbound_no, po_number, supplier, material_id, qty, notes, received_by, started_at, completed_at, duration_seconds)
                VALUES (?, 'ADJUSTMENT', 'SYSTEM', ?, ?, ?, ?, NOW(), NOW(), 0)
            ");
            $stmtInsertInbound->execute([
                $refNo,
                $materialId,
                $adjustQty,
                $fullNotes,
                $userName
            ]);
        } else {
            $stmtInsertOutbound = $pdo->prepare("
                INSERT INTO outbound_transactions (outbound_no, material_id, qty, destination, issued_by, reason, notes, started_at, completed_at, duration_seconds)
                VALUES (?, ?, ?, 'SYSTEM', ?, 'ADJUSTMENT', ?, NOW(), NOW(), 0)
            ");
            $stmtInsertOutbound->execute([
                $refNo,
                $materialId,
                $adjustQty,
                $userName,
                $fullNotes
            ]);
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Penyesuaian " . ($adjustType === 'MINUS' ? 'Minus (-)' : 'Plus (+)') . " berhasil untuk '{$mat['name']}'. Stok baru: {$stockAfter} {$mat['unit']}.",
            'material_id' => $materialId,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'qty_change' => $delta,
            'reference_no' => $refNo
        ]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memproses penyesuaian: ' . $e->getMessage()]);
        exit;
    }
}

// =========================================================================
// 5. GET ADJUSTMENT HISTORY LOGS
// =========================================================================
if ($action === 'history') {
    $search = trim($_GET['search'] ?? '');
    $date   = trim($_GET['date'] ?? '');
    $time   = trim($_GET['time'] ?? '');
    $limit  = min(200, max(10, (int)($_GET['limit'] ?? 100)));

    $query = "
        SELECT sm.*,
               m.code as material_code,
               m.name as material_name,
               m.category as material_category,
               m.unit as material_unit,
               m.rack_location as rack_location,
               u.name as user_name,
               u.username as user_username
        FROM stock_mutations sm
        JOIN materials m ON sm.material_id = m.id
        LEFT JOIN users u ON sm.user_id = u.id
        WHERE sm.type = 'ADJUSTMENT'
    ";
    $params = [];

    if (!empty($search)) {
        $query .= " AND (m.code LIKE ? OR m.name LIKE ? OR sm.reference_no LIKE ? OR sm.notes LIKE ?)";
        $term = "%{$search}%";
        $params = [$term, $term, $term, $term];
    }

    if (!empty($date)) {
        $query .= " AND DATE(sm.created_at) = ?";
        $params[] = $date;
    }

    if (!empty($time)) {
        $query .= " AND TIME_FORMAT(sm.created_at, '%H:%i') LIKE ?";
        $params[] = "%{$time}%";
    }

    $query .= " ORDER BY sm.created_at DESC, sm.id DESC LIMIT {$limit}";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'data' => $rows
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi tidak valid']);
