<?php
// api/import_excel.php - High-Performance Native XLSX & CSV Importer for Packaging Materials
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireAdmin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? 'preview';

/**
 * Helper: Parse Excel (.xlsx) file natively using ZipArchive & SimpleXML
 */
function parseNativeXlsx($filePath) {
    if (!file_exists($filePath)) return false;
    
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return false;

    // 1. Read shared strings table
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

    // 2. Read sheet1.xml (or first available sheet)
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
            } else {
                $cellValue = $val;
            }

            // Convert column letter to 0-based index: A->0, B->1, C->2, etc.
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

/**
 * Helper: Intelligently categorize packaging material from its description & code
 */
/**
 * Helper: Intelligently & automatically categorize packaging material from its description & code
 * (Sehingga di file Excel tidak perlu kolom Kategori lagi)
 */
function inferPackagingMetadata($code, $desc) {
    $descLower = strtolower($desc);
    $category = 'Packaging Umum';
    $unit = 'Pcs';
    $rack = 'Rak A-01';

    // 1. Plastik Kemasan / Polymailer / Standing Pouch / Ziplock / Kantong Plastik
    if (strpos($descLower, 'polymailer') !== false || strpos($descLower, 'plastik') !== false || strpos($descLower, 'ziplock') !== false || strpos($descLower, 'pouch') !== false || strpos($descLower, 'kantong') !== false || strpos($descLower, 'opp bag') !== false) {
        $category = 'Plastik Kemasan';
        $unit = 'Pcs';
        $rack = 'Rak B-01';
    }
    // 2. Karton / Dus / Box / Corrugated / Mailer Box
    elseif (strpos($descLower, 'dus') !== false || strpos($descLower, 'box') !== false || strpos($descLower, 'karton') !== false || strpos($descLower, 'corrugated') !== false || strpos($descLower, 'mailer box') !== false) {
        $category = 'Karton Box';
        $unit = 'Pcs';
        $rack = 'Rak A-' . str_pad(((int)preg_replace('/[^0-9]/', '', $code) % 12) + 1, 2, '0', STR_PAD_LEFT);
    }
    // 3. Bubble Film, Wrap, Stretch Film, Air Column
    elseif (strpos($descLower, 'bubble') !== false || strpos($descLower, 'film') !== false || strpos($descLower, 'wrap') !== false || strpos($descLower, 'cushion') !== false || strpos($descLower, 'air column') !== false) {
        $category = 'Bubble & Wrap Film';
        $unit = 'Roll';
        $rack = 'Zona P-01';
    }
    // 4. Kertas, Honeycomb, Kraft, Shredded
    elseif (strpos($descLower, 'honeycomb') !== false || strpos($descLower, 'paper') !== false || strpos($descLower, 'kertas') !== false || strpos($descLower, 'kraft') !== false || strpos($descLower, 'samson') !== false || strpos($descLower, 'shredded') !== false) {
        $category = 'Kertas & Honeycomb';
        $unit = (strpos($descLower, 'panjang') !== false || strpos($descLower, 'roll') !== false || strpos($descLower, '250m') !== false || strpos($descLower, '200m') !== false) ? 'Pcs' : 'Sheet';
        $rack = 'Rak A-01';
    }
    // 5. Lakban, Tape, Isolasi, Seal
    elseif (strpos($descLower, 'lakban') !== false || strpos($descLower, 'tape') !== false || strpos($descLower, 'isolasi') !== false || strpos($descLower, 'fragile') !== false || strpos($descLower, 'seal') !== false || strpos($descLower, 'gummed') !== false) {
        $category = 'Lakban & Tape';
        $unit = 'Roll';
        $rack = 'Rak C-03';
    }
    // 6. Label, Stiker, Waybill, Resi, Thermal
    elseif (strpos($descLower, 'label') !== false || strpos($descLower, 'stiker') !== false || strpos($descLower, 'sticker') !== false || strpos($descLower, 'waybill') !== false || strpos($descLower, 'thermal') !== false || strpos($descLower, 'barcode') !== false || strpos($descLower, 'resi') !== false) {
        $category = 'Label & Stiker';
        $unit = 'Roll';
        $rack = 'Rak C-02';
    }
    // 7. Botol, Jar, Pot, Vial, Tube
    elseif (strpos($descLower, 'botol') !== false || strpos($descLower, 'bottle') !== false || strpos($descLower, 'jar') !== false || strpos($descLower, 'pot') !== false || strpos($descLower, 'vial') !== false || strpos($descLower, 'tube') !== false) {
        $category = 'Botol & Jar';
        $unit = 'Pcs';
        $rack = 'Rak B-02';
    }
    // 8. Tutup, Cap, Pump, Spray, Dropper
    elseif (strpos($descLower, 'tutup') !== false || strpos($descLower, 'cap') !== false || strpos($descLower, 'pump') !== false || strpos($descLower, 'spray') !== false || strpos($descLower, 'dropper') !== false || strpos($descLower, 'plug') !== false) {
        $category = 'Tutup & Pump';
        $unit = 'Pcs';
        $rack = 'Rak B-03';
    }
    // 9. Card, Kartu, Insert, Divider, Sekat, Hangtag
    elseif (strpos($descLower, 'card') !== false || strpos($descLower, 'kartu') !== false || strpos($descLower, 'insert') !== false || strpos($descLower, 'divider') !== false || strpos($descLower, 'sekat') !== false || strpos($descLower, 'hangtag') !== false || strpos($descLower, 'leaflet') !== false) {
        $category = 'Card & Insert';
        $unit = 'Sheet';
        $rack = 'Rak C-01';
    }
    // 10. Strapping Band, Tali, Palet, Corner Protector
    elseif (strpos($descLower, 'strapping') !== false || strpos($descLower, 'tali') !== false || strpos($descLower, 'band') !== false || strpos($descLower, 'palet') !== false || strpos($descLower, 'pallet') !== false || strpos($descLower, 'corner') !== false || strpos($descLower, 'edge') !== false) {
        $category = 'Strapping & Pallet';
        $unit = (strpos($descLower, 'tali') !== false || strpos($descLower, 'band') !== false) ? 'Roll' : 'Pcs';
        $rack = 'Zona P-02';
    }

    return ['category' => $category, 'unit' => $unit, 'rack' => $rack];
}

// 1. CHECK LOCAL FILE IN WORKSPACE
if ($action === 'detect_file') {
    $localFile = __DIR__ . '/../Data Packaaging Material.xlsx';
    if (file_exists($localFile)) {
        $rows = parseNativeXlsx($localFile);
        $totalItems = $rows ? max(0, count($rows) - 1) : 0;
        echo json_encode([
            'success' => true,
            'file_exists' => true,
            'filename' => 'Data Packaaging Material.xlsx',
            'total_items' => $totalItems,
            'filesize' => filesize($localFile)
        ]);
    } else {
        echo json_encode(['success' => true, 'file_exists' => false]);
    }
    exit;
}

// 2. DOWNLOAD TEMPLATE EXCEL (.xlsx)
if ($action === 'template') {
    header("Location: ../admin/export.php?type=inventory_template");
    exit;
}

// 3. PARSE / PREVIEW UPLOAD (Support XLSX, CSV, Local File, and Paste)
if ($action === 'preview' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rows = [];
    $isLocalFile = ($_POST['source'] ?? '') === 'local_file' || ($_GET['source'] ?? '') === 'local_file';

    if ($isLocalFile) {
        $localFile = __DIR__ . '/../Data Packaaging Material.xlsx';
        $rows = parseNativeXlsx($localFile);
        if (!$rows) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Gagal membaca file Data Packaaging Material.xlsx di server.']);
            exit;
        }
    } elseif (isset($_FILES['file']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
        $filePath = $_FILES['file']['tmp_name'];
        $origName = strtolower($_FILES['file']['name']);

        // Check if XLSX
        if (str_ends_with($origName, '.xlsx') || str_ends_with($origName, '.xlsm')) {
            $rows = parseNativeXlsx($filePath);
        }

        // Fallback to CSV / text parser
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
            echo json_encode(['success' => false, 'message' => 'Silakan upload file Excel (.xlsx/.csv) atau paste data tabel.']);
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
        echo json_encode(['success' => false, 'message' => 'Format file tidak dapat dibaca atau file kosong. Pastikan format .xlsx atau .csv']);
        exit;
    }

    // Header mapping
    $headers = array_map(function($h) {
        return strtolower(trim(preg_replace('/[\x00-\x1F\x80-\xFF\.]/', '', $h)));
    }, $rows[0]);

    $itemNoIdx = -1;
    $descIdx   = -1;
    $stockIdx  = -1;
    $catIdx    = -1;
    $unitIdx   = -1;
    $rackIdx   = -1;

    foreach ($headers as $idx => $header) {
        if (in_array($header, ['item no', 'itemno', 'item_no', 'itemnumber', 'item number', 'kode item', 'kode', 'code', 'sku', 'kode material'])) {
            $itemNoIdx = $idx;
        } elseif (in_array($header, ['item description', 'itemdescription', 'item_description', 'description', 'deskripsi', 'nama barang', 'nama item', 'nama packaging', 'nama material', 'nama'])) {
            $descIdx = $idx;
        } elseif (in_array($header, ['ending stock', 'ending_stock', 'endingstock', 'sisa stock', 'sisa stok', 'stock', 'stok', 'qty', 'stok akhir', 'ending', 'balance'])) {
            $stockIdx = $idx;
        } elseif (in_array($header, ['category', 'kategori', 'jenis', 'kategori barang'])) {
            $catIdx = $idx;
        } elseif (in_array($header, ['unit', 'satuan', 'uom', 'oum', 'satuan barang', 'unit of measure'])) {
            $unitIdx = $idx;
        } elseif (in_array($header, ['rack location', 'rack', 'lokasi rak', 'lokasi', 'rak', 'bin', 'location'])) {
            $rackIdx = $idx;
        }
    }

    if ($itemNoIdx === -1) $itemNoIdx = 0;
    if ($descIdx === -1) $descIdx = 1;
    if ($stockIdx === -1) $stockIdx = 2;
    $startRow = 1;

    // Existing materials check
    $existing = [];
    $stmt = $pdo->query("SELECT id, code, name, current_stock FROM materials");
    while ($m = $stmt->fetch()) {
        $existing[strtoupper($m['code'])] = $m;
    }

    $parsedItems = [];
    $validCount = 0;
    $newCount = 0;
    $updateCount = 0;

    for ($i = $startRow; $i < count($rows); $i++) {
        $r = $rows[$i];
        if (empty(array_filter($r, 'strlen'))) continue;

        $itemNo = strtoupper(trim($r[$itemNoIdx] ?? ''));
        $desc   = trim($r[$descIdx] ?? '');
        $stockRaw = trim($r[$stockIdx] ?? '0');
        
        // Clean stock value (e.g. "18,246" or "18.246" or " 18246 ")
        $stockClean = preg_replace('/[^0-9]/', '', $stockRaw);
        $endingStock = ($stockClean !== '') ? (int)$stockClean : 0;

        if (empty($itemNo) && empty($desc)) continue;
        if (empty($itemNo)) $itemNo = 'PKG-' . str_pad($i, 4, '0', STR_PAD_LEFT);

        $meta = inferPackagingMetadata($itemNo, $desc);
        $category = ($catIdx !== -1 && !empty($r[$catIdx])) ? trim($r[$catIdx]) : $meta['category'];
        $unit     = ($unitIdx !== -1 && !empty($r[$unitIdx])) ? trim($r[$unitIdx]) : $meta['unit'];
        $rack     = ($rackIdx !== -1 && !empty($r[$rackIdx])) ? trim($r[$rackIdx]) : $meta['rack'];

        $isExisting = isset($existing[$itemNo]);
        if ($isExisting) {
            $updateCount++;
            $statusType = 'UPDATE';
            $oldStock = $existing[$itemNo]['current_stock'];
        } else {
            $newCount++;
            $statusType = 'NEW';
            $oldStock = 0;
        }

        $parsedItems[] = [
            'row_num' => $i + 1,
            'item_no' => $itemNo,
            'item_description' => $desc ?: $itemNo,
            'ending_stock' => $endingStock,
            'old_stock' => $oldStock,
            'category' => $category,
            'unit' => $unit,
            'rack_location' => $rack,
            'min_stock' => 50,
            'status' => $statusType
        ];
        $validCount++;
    }

    echo json_encode([
        'success' => true,
        'summary' => [
            'total_rows' => $validCount,
            'new_items' => $newCount,
            'update_items' => $updateCount
        ],
        'items' => $parsedItems
    ]);
    exit;
}

// 4. COMMIT IMPORT (Save all packaging materials to database)
if ($action === 'commit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $items = $input['items'] ?? [];

    if (empty($items)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tidak ada item data untuk diimpor.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmtCheck = $pdo->prepare("SELECT id, current_stock FROM materials WHERE code = ?");
        $stmtInsert = $pdo->prepare("INSERT INTO materials (code, name, category, unit, rack_location, min_stock, current_stock, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtUpdate = $pdo->prepare("UPDATE materials SET name = ?, category = ?, unit = ?, rack_location = ?, min_stock = ?, current_stock = ? WHERE id = ?");
        $stmtMut = $pdo->prepare("INSERT INTO stock_mutations (material_id, type, qty_change, stock_before, stock_after, reference_no, notes, user_id) VALUES (?, 'INITIAL_IMPORT', ?, ?, ?, ?, ?, ?)");

        $inserted = 0;
        $updated = 0;
        $userId = Auth::id();

        foreach ($items as $item) {
            $code = strtoupper(trim($item['item_no']));
            $name = trim($item['item_description']);
            $stock = max(0, (int)$item['ending_stock']);
            $cat = trim($item['category'] ?? 'Packaging Material');
            $unit = trim($item['unit'] ?? 'Pcs');
            $rack = trim($item['rack_location'] ?? 'Gudang Utama');
            $minStock = max(0, (int)($item['min_stock'] ?? 50));

            if (empty($code)) continue;

            $stmtCheck->execute([$code]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                $matId = (int)$existing['id'];
                $oldStock = (int)$existing['current_stock'];
                $diff = $stock - $oldStock;

                $stmtUpdate->execute([$name, $cat, $unit, $rack, $minStock, $stock, $matId]);
                $updated++;

                if ($diff !== 0) {
                    $stmtMut->execute([
                        $matId,
                        $diff,
                        $oldStock,
                        $stock,
                        'EXCEL-IMPORT-UPDATE',
                        "Penyesuaian Stok dari Upload Excel (Item No: {$code})",
                        $userId
                    ]);
                }
            } else {
                $stmtInsert->execute([$code, $name, $cat, $unit, $rack, $minStock, $stock, 'Diimpor dari file Excel']);
                $matId = (int)$pdo->lastInsertId();
                $inserted++;

                if ($stock > 0) {
                    $stmtMut->execute([
                        $matId,
                        $stock,
                        0,
                        $stock,
                        'EXCEL-INITIAL-IMPORT',
                        "Stok Awal Upload Excel (Item No: {$code})",
                        $userId
                    ]);
                }
            }
        }

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => "Import Berhasil! {$inserted} material packaging berhasil ditambahkan ke database.",
            'inserted' => $inserted,
            'updated' => $updated
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal memproses import: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi import tidak valid']);
