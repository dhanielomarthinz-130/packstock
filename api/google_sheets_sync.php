<?php
// api/google_sheets_sync.php - Google Sheets Synchronization API (Super Admin Access Only)
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

Auth::requireLogin();

if (!Auth::isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Akses ditolak. Fitur Sync Google Sheets hanya dapat diakses oleh Admin & Super Admin.'
    ]);
    exit;
}

$pdo = Database::getConnection();
$configFile = __DIR__ . '/../config/google_sheets.json';

function getGoogleSheetsConfig(string $path): array {
    $default = [
        'web_app_url' => 'https://script.google.com/macros/s/AKfycby-dXY-qbOS6e9G5L-x_X0hokw0EO8WJo0VzXnVbhRJwMJlsPhP97eCdqqTrIagrEJT2A/exec',
        'auto_sync' => false,
        'last_synced' => [
            'inventory' => null,
            'gimmick'   => null,
            'vas'       => null,
            'reorder'   => null,
            'inbound'   => null,
            'outbound'  => null,
            'mutations' => null
        ]
    ];
    if (file_exists($path)) {
        $content = file_get_contents($path);
        $data = json_decode($content, true);
        if (is_array($data)) {
            $merged = array_merge($default, $data);
            if (empty($merged['web_app_url'])) {
                $merged['web_app_url'] = $default['web_app_url'];
            }
            return $merged;
        }
    }
    return $default;
}

function saveGoogleSheetsConfig(string $path, array $config): bool {
    return file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get_config');
$config = getGoogleSheetsConfig($configFile);

// =========================================================================
// 1. GET CONFIGURATION
// =========================================================================
if ($action === 'get_config') {
    echo json_encode([
        'success' => true,
        'config' => $config
    ]);
    exit;
}

// =========================================================================
// 2. SAVE CONFIGURATION
// =========================================================================
if ($action === 'save_config') {
    $rawBody = file_get_contents('php://input');
    $rawInput = json_decode($rawBody, true);
    $webAppUrl = trim($rawInput['web_app_url'] ?? ($_POST['web_app_url'] ?? ''));

    if (!empty($webAppUrl) && !filter_var($webAppUrl, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'URL Web App Google Apps Script tidak valid!']);
        exit;
    }

    if (empty($webAppUrl)) {
        $webAppUrl = 'https://script.google.com/macros/s/AKfycby-dXY-qbOS6e9G5L-x_X0hokw0EO8WJo0VzXnVbhRJwMJlsPhP97eCdqqTrIagrEJT2A/exec';
    }

    $urlLama = $config['web_app_url'] ?? '(kosong)';
    $config['web_app_url'] = $webAppUrl;
    if (saveGoogleSheetsConfig($configFile, $config)) {
        // Mengubah URL tujuan berarti mengubah ke mana seluruh data stok dikirim.
        // Perubahan sepenting itu harus meninggalkan jejak.
        if ($urlLama !== $webAppUrl) {
            Auth::audit('SHEETS_URL_CHANGED', $webAppUrl, "Sebelumnya: {$urlLama}");
        }
        echo json_encode(['success' => true, 'message' => 'Pengaturan Google Sheets berhasil disimpan!', 'config' => $config]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan konfigurasi ke file server.']);
    }
    exit;
}

// =========================================================================
// 3. PING / TEST CONNECTION
// =========================================================================
if ($action === 'ping') {
    $webAppUrl = $config['web_app_url'];
    if (empty($webAppUrl)) {
        echo json_encode(['success' => false, 'message' => 'URL Google Apps Script belum dikonfigurasi!']);
        exit;
    }

    $payload = [
        'action' => 'ping',
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $_SESSION['user_username'] ?? 'user'
    ];

    $ch = curl_init($webAppUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POSTREDIR => 7,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(['success' => false, 'message' => "Koneksi gagal: {$curlErr}"]);
        exit;
    }

    $resData = json_decode($response, true);
    if ($resData && isset($resData['status']) && $resData['status'] === 'success') {
        echo json_encode(['success' => true, 'message' => 'Koneksi ke Google Sheet Web App BERHASIL!', 'details' => $resData]);
    } else {
        echo json_encode(['success' => true, 'message' => 'Respon diterima dari Google Apps Script Web App.', 'raw' => substr($response, 0, 300)]);
    }
    exit;
}

// =========================================================================
// 4. SYNC DATA (STOCK INVENTORY, STOCK VAS, BARANG MASUK, BARANG KELUAR, MUTASI)
// =========================================================================
if ($action === 'sync') {
    $webAppUrl = $config['web_app_url'];
    if (empty($webAppUrl)) {
        echo json_encode(['success' => false, 'message' => 'URL Google Apps Script Web App belum diisi! Silakan buka tab Pengaturan di modal sync.']);
        exit;
    }

    $target = trim($_GET['target'] ?? ($_POST['target'] ?? 'all'));
    $mode   = trim($_GET['mode'] ?? ($_POST['mode'] ?? 'full')); // 'update' (incremental) or 'full'

    $allValidTargets = ['inventory', 'gimmick', 'vas', 'reorder', 'inbound', 'outbound', 'mutations'];
    $targetsToProcess = [];
    if ($target === 'all') {
        $targetsToProcess = $allValidTargets;
    } elseif (in_array($target, $allValidTargets)) {
        $targetsToProcess = [$target];
    } else {
        echo json_encode(['success' => false, 'message' => "Target sync '{$target}' tidak valid."]);
        exit;
    }

    $sheetsPayload = [];
    $summaryCounts = [];

    foreach ($targetsToProcess as $t) {
        $lastSyncTime = $config['last_synced'][$t] ?? null;
        $sheetData = buildSheetData($pdo, $t, $mode, $lastSyncTime);
        $sheetsPayload[] = $sheetData;
        $summaryCounts[$t] = count($sheetData['rows']);
    }

    $requestData = [
        'action' => 'sync_packstock_data',
        'mode'   => $mode,
        'target' => $target,
        'timestamp' => date('d/m/Y H:i:s'),
        'timestamp_iso' => date('Y-m-d H:i:s'),
        'user'   => $_SESSION['user_username'] ?? 'admin',
        'sheets' => $sheetsPayload
    ];

    // Post to Google Apps Script Web App
    $ch = curl_init($webAppUrl);
    $curlOpts = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POSTREDIR => 7,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 45
    ];
    if (defined('CURL_IPRESOLVE_V4')) {
        $curlOpts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
    }
    curl_setopt_array($ch, $curlOpts);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        $userMsg = "Server PHP cURL gagal terhubung ke Google: {$curlErr}";
        if (stripos($curlErr, 'could not resolve host') !== false) {
            $userMsg = "Server hosting (InfinityFree / cURL) memblokir koneksi luar ke script.google.com. Mengalihkan ke Direct Browser Sync...";
        }
        echo json_encode([
            'success' => false,
            'is_curl_error' => true,
            'message' => $userMsg,
            'web_app_url' => $webAppUrl,
            'request_data' => $requestData,
            'summary_counts' => $summaryCounts
        ]);
        exit;
    }

    $resData = json_decode($response, true);
    $nowStr = date('Y-m-d H:i:s');

    // Update last_synced timestamps
    foreach ($targetsToProcess as $t) {
        $config['last_synced'][$t] = $nowStr;
    }
    saveGoogleSheetsConfig($configFile, $config);

    $msgMode = ($mode === 'update') ? 'Update Terbaru' : 'Full Sync';
    $totalRowsAll = array_sum($summaryCounts);

    // Data stok keluar ke pihak ketiga — catat siapa, ke mana, dan berapa banyak.
    Auth::audit(
        'SHEETS_SYNC',
        $config['web_app_url'] ?? '-',
        "Mode {$msgMode}, target: " . implode(', ', $targetsToProcess) . ", total {$totalRowsAll} baris"
    );

    echo json_encode([
        'success' => true,
        'message' => "Berhasil melakukan {$msgMode} ke Google Sheet! (Total: {$totalRowsAll} baris data diproses).",
        'mode' => $mode,
        'synced_at' => date('d/m/Y H:i:s'),
        'details' => $summaryCounts,
        'remote_response' => $resData
    ]);
    exit;
}

// =========================================================================
// 5. GET PAYLOAD (FOR CLIENT-SIDE DIRECT BROWSER SYNC FALLBACK)
// =========================================================================
if ($action === 'get_payload') {
    $webAppUrl = $config['web_app_url'];
    $target = trim($_GET['target'] ?? ($_POST['target'] ?? 'all'));
    $mode   = trim($_GET['mode'] ?? ($_POST['mode'] ?? 'full'));

    $allValidTargets = ['inventory', 'gimmick', 'vas', 'reorder', 'inbound', 'outbound', 'mutations'];
    $targetsToProcess = [];
    if ($target === 'all') {
        $targetsToProcess = $allValidTargets;
    } elseif (in_array($target, $allValidTargets)) {
        $targetsToProcess = [$target];
    } else {
        $targetsToProcess = $allValidTargets;
    }

    $sheetsPayload = [];
    $summaryCounts = [];

    foreach ($targetsToProcess as $t) {
        $lastSyncTime = $config['last_synced'][$t] ?? null;
        $sheetData = buildSheetData($pdo, $t, $mode, $lastSyncTime);
        $sheetsPayload[] = $sheetData;
        $summaryCounts[$t] = count($sheetData['rows']);
    }

    $requestData = [
        'action' => 'sync_packstock_data',
        'mode'   => $mode,
        'target' => $target,
        'timestamp' => date('d/m/Y H:i:s'),
        'timestamp_iso' => date('Y-m-d H:i:s'),
        'user'   => $_SESSION['user_username'] ?? 'admin',
        'sheets' => $sheetsPayload
    ];

    echo json_encode([
        'success' => true,
        'web_app_url' => $webAppUrl,
        'request_data' => $requestData,
        'summary_counts' => $summaryCounts
    ]);
    exit;
}

// =========================================================================
// 6. MARK SYNCED (AFTER CLIENT-SIDE DIRECT BROWSER SYNC COMPLETES)
// =========================================================================
if ($action === 'mark_synced') {
    $target = trim($_GET['target'] ?? ($_POST['target'] ?? 'all'));
    $allValidTargets = ['inventory', 'gimmick', 'vas', 'reorder', 'inbound', 'outbound', 'mutations'];
    $targetsToProcess = ($target === 'all') ? $allValidTargets : [$target];
    $nowStr = date('Y-m-d H:i:s');

    foreach ($targetsToProcess as $t) {
        if (isset($config['last_synced'][$t]) || array_key_exists($t, $config['last_synced'] ?? [])) {
            $config['last_synced'][$t] = $nowStr;
        }
    }
    saveGoogleSheetsConfig($configFile, $config);

    Auth::audit('SHEETS_SYNC_MANUAL', implode(', ', $targetsToProcess), 'Ditandai tersinkron dari browser (Direct Browser Sync)');

    echo json_encode(['success' => true, 'synced_at' => date('d/m/Y H:i:s')]);
    exit;
}

// =========================================================================
// DATA BUILDER FUNCTION FOR EACH TARGET
// =========================================================================
function buildSheetData(PDO $pdo, string $target, string $mode, ?string $lastSyncTime): array {
    if ($target === 'inventory') {
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
                       ORDER BY id ASC LIMIT 1
                   ), (
                       m.current_stock - 
                       COALESCE((SELECT SUM(qty_change) FROM stock_mutations WHERE material_id = m.id AND type != 'INITIAL_IMPORT'), 0)
                   )) as initial_upload_stock
            FROM materials m
            WHERE (m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = '')
        ";
        
        $params = [];
        if ($mode === 'update' && !empty($lastSyncTime)) {
            $query .= " AND (m.updated_at > ? OR m.created_at > ?)";
            $params = [$lastSyncTime, $lastSyncTime];
        }
        $query .= " ORDER BY m.code ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = [];

        while ($r = $stmt->fetch()) {
            $stock = (float)$r['current_stock'];
            $min = (float)$r['min_stock'];
            $vas = (float)($r['vas_stock'] ?? 0);
            
            $status = 'AMAN';
            if ($stock <= 0) {
                $status = 'HABIS';
            } elseif ($stock <= $min) {
                $status = 'MENIPIS';
            }

            $rows[] = [
                $r['code'], // Key at col 0
                $r['name'],
                $r['category'] ?: 'Kemas',
                (float)$r['initial_upload_stock'],
                (float)$r['total_inbound'],
                (float)$r['total_outbound'],
                $stock,
                $vas,
                $r['unit'] ?: 'Pcs',
                $r['rack_location'] ?: '-',
                $status,
                $r['updated_at'] ?: date('Y-m-d H:i:s')
            ];
        }

        if (empty($rows) && $mode === 'update') {
            return buildSheetData($pdo, 'inventory', 'full', null);
        }

        return [
            'name' => 'Stock Inventory',
            'key_index' => 0, // Column 0 (Item No / SKU) is Primary Key
            'headers' => [
                'Item No (SKU)',
                'Item Description',
                'Kategori',
                'Stok Awal',
                'Total Masuk (+)',
                'Total Keluar (-)',
                'Sisa Stok Akhir',
                'Stok Zone VAS',
                'Satuan',
                'Lokasi Rak',
                'Status Stok',
                'Terakhir Update'
            ],
            'rows' => $rows
        ];
    }

    if ($target === 'gimmick') {
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
                       ORDER BY id ASC LIMIT 1
                   ), (
                       m.current_stock - 
                       COALESCE((SELECT SUM(qty_change) FROM stock_mutations WHERE material_id = m.id AND type != 'INITIAL_IMPORT'), 0)
                   )) as initial_upload_stock
            FROM materials m
            WHERE m.item_type = 'GIMMICK'
        ";
        
        $params = [];
        if ($mode === 'update' && !empty($lastSyncTime)) {
            $query .= " AND (m.updated_at > ? OR m.created_at > ?)";
            $params = [$lastSyncTime, $lastSyncTime];
        }
        $query .= " ORDER BY m.code ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = [];

        while ($r = $stmt->fetch()) {
            $stock = (float)$r['current_stock'];
            $min = (float)$r['min_stock'];
            $vas = (float)($r['vas_stock'] ?? 0);
            $qKecil = (float)($r['qty_gudang_kecil'] ?? 0);
            $qBesar = (float)($r['qty_gudang_besar'] ?? 0);
            
            $status = 'AMAN';
            if ($stock <= 0) {
                $status = 'HABIS';
            } elseif ($stock <= $min) {
                $status = 'MENIPIS';
            }

            $rows[] = [
                $r['code'], // Primary Key
                $r['name'],
                $r['category'] ?: 'Gimmick',
                (float)$r['initial_upload_stock'],
                (float)$r['total_inbound'],
                (float)$r['total_outbound'],
                $stock,
                $qKecil,
                $qBesar,
                $vas,
                $r['unit'] ?: 'Pcs',
                $r['rack_location'] ?: '-',
                $status,
                $r['updated_at'] ?: date('Y-m-d H:i:s')
            ];
        }

        if (empty($rows) && $mode === 'update') {
            return buildSheetData($pdo, 'gimmick', 'full', null);
        }

        return [
            'name' => 'Stock Gimmick',
            'key_index' => 0,
            'headers' => [
                'Item No (SKU)',
                'Nama Gimmick',
                'Kategori',
                'Stok Awal',
                'Total Masuk (+)',
                'Total Keluar (-)',
                'Sisa Stok Akhir',
                'Qty Gudang Kecil',
                'Qty Gudang Besar',
                'Stok Zone VAS',
                'Satuan',
                'Lokasi Rak',
                'Status Stok',
                'Terakhir Update'
            ],
            'rows' => $rows
        ];
    }

    if ($target === 'reorder') {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        
        // Ensure table material_po_trackings exists
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
            WHERE (m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = '')
        ";
        $params = [];
        if ($mode === 'update' && !empty($lastSyncTime)) {
            $query .= " AND (m.updated_at > ? OR m.created_at > ?)";
            $params = [$lastSyncTime, $lastSyncTime];
        }
        $query .= " ORDER BY (m.current_stock <= 0) DESC, (m.current_stock <= m.min_stock) DESC, m.name ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $leadTimeDays = 7;
        $rows = [];

        while ($r = $stmt->fetch()) {
            $stock = (float)$r['current_stock'];
            $minStock = (float)$r['min_stock'];

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

            $leadTimeDemand = $dailyUsage * $leadTimeDays;
            $reorderPoint = $leadTimeDemand + $minStock;

            $urgencyLabel = 'Aman';
            if ($stock <= 0) {
                $urgencyLabel = 'HABIS (0)';
            } elseif ($stock <= $leadTimeDemand || ($minStock > 0 && $stock <= ($minStock * 0.5))) {
                $urgencyLabel = 'HARUS PO (Kritis)';
            } elseif ($stock <= $minStock || ($reorderPoint > 0 && $stock <= $reorderPoint)) {
                $urgencyLabel = 'Menipis';
            }

            $runwayText = 'Statis / Tidak Ada Pemakaian';
            if ($stock <= 0) {
                $runwayText = '0 Hari (Habis)';
            } elseif ($dailyUsage > 0) {
                $days = round($stock / $dailyUsage, 1);
                $runwayText = "{$days} Hari";
            }

            $suggestedQty = 0;
            if ($urgencyLabel !== 'Aman') {
                $targetBuffer = max($minStock * 2, $leadTimeDemand * 3, $minStock + ($dailyUsage * 14));
                $diff = $targetBuffer - $stock;
                $suggestedQty = max(1, ceil($diff));
                if ($suggestedQty > 100) {
                    $suggestedQty = ceil($suggestedQty / 10) * 10;
                }
            }

            $poStatus = ($r['active_po_count'] > 0) ? 'Sudah Di-order PO' : 'Belum Ada PO';

            $rows[] = [
                $r['code'], // Key col 0
                $r['name'],
                $r['category'] ?: 'Kemas',
                $r['rack_location'] ?: '-',
                $stock,
                $minStock,
                round($dailyUsage, 2),
                round($leadTimeDemand, 2),
                round($reorderPoint, 2),
                $runwayText,
                $suggestedQty,
                $r['unit'] ?: 'Pcs',
                $urgencyLabel,
                $poStatus,
                $r['latest_po_number'] ?: '-',
                (float)($r['latest_po_qty'] ?? 0),
                $r['latest_po_eta'] ?: '-',
                $r['latest_supplier'] ?: '-',
                $r['updated_at'] ?: date('Y-m-d H:i:s')
            ];
        }

        if (empty($rows) && $mode === 'update') {
            return buildSheetData($pdo, 'reorder', 'full', null);
        }

        return [
            'name' => 'Reorder Kemas',
            'key_index' => 0,
            'headers' => [
                'Item No (SKU)',
                'Nama Kemas',
                'Kategori',
                'Lokasi Rak',
                'Stok Saat Ini',
                'Safety Stock (Min)',
                'Pemakaian Harian',
                'Kebutuhan Lead Time (7 Hari)',
                'Reorder Point (ROP)',
                'Estimasi Sisa Hari',
                'Saran Qty Order',
                'Satuan',
                'Status Kritis',
                'Status PO',
                'No. PO Terakhir',
                'Qty PO',
                'ETA Kedatangan',
                'Supplier',
                'Terakhir Update'
            ],
            'rows' => $rows
        ];
    }

    if ($target === 'vas') {
        $query = "
            SELECT m.code, m.name, m.category, m.rack_location, m.current_stock, 
                   COALESCE(m.vas_stock, 0) as vas_stock, m.unit, m.updated_at
            FROM materials m
            WHERE COALESCE(m.vas_stock, 0) > 0
        ";
        $params = [];
        if ($mode === 'update' && !empty($lastSyncTime)) {
            $query .= " AND (m.updated_at > ? OR m.created_at > ?)";
            $params = [$lastSyncTime, $lastSyncTime];
        }
        $query .= " ORDER BY vas_stock DESC, m.name ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = [];

        while ($r = $stmt->fetch()) {
            $rows[] = [
                $r['code'], // Primary Key
                $r['name'],
                $r['category'] ?: 'Kemas',
                $r['rack_location'] ?: '-',
                (float)$r['current_stock'],
                (float)$r['vas_stock'],
                $r['unit'] ?: 'Pcs',
                $r['updated_at'] ?: date('Y-m-d H:i:s')
            ];
        }

        if (empty($rows) && $mode === 'update') {
            return buildSheetData($pdo, 'vas', 'full', null);
        }

        return [
            'name' => 'Stock VAS',
            'key_index' => 0,
            'headers' => [
                'Item No (SKU)',
                'Item Description',
                'Kategori',
                'Lokasi Rak',
                'Stok Inventory',
                'Stok Zone VAS',
                'Satuan',
                'Terakhir Update'
            ],
            'rows' => $rows
        ];
    }

    if ($target === 'inbound') {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $concatExpr = ($driver === 'sqlite') 
            ? "('INIT-' || COALESCE(m.code, sm.id))" 
            : "CONCAT('INIT-', COALESCE(m.code, sm.id))";

        $query = "
            SELECT * FROM (
                SELECT 
                    i.inbound_no,
                    i.po_number,
                    i.supplier,
                    i.qty,
                    COALESCE(i.started_at, i.created_at) as started_at,
                    COALESCE(i.completed_at, i.created_at) as completed_at,
                    COALESCE(i.duration_seconds, 0) as duration_seconds,
                    i.created_at,
                    i.notes,
                    m.code as material_code,
                    m.name as material_name,
                    m.unit as material_unit,
                    m.rack_location,
                    COALESCE(u.name, i.received_by, 'Admin') as receiver_name
                FROM inbound_transactions i
                LEFT JOIN materials m ON i.material_id = m.id
                LEFT JOIN users u ON (i.received_by = u.id OR i.received_by = u.username)

                UNION ALL

                SELECT 
                    {$concatExpr} as inbound_no,
                    'STOK AWAL' as po_number,
                    'Upload / Setup Awal' as supplier,
                    sm.qty_change as qty,
                    sm.created_at as started_at,
                    sm.created_at as completed_at,
                    0 as duration_seconds,
                    sm.created_at,
                    COALESCE(sm.notes, 'Stok Awal Pendaftaran Material') as notes,
                    m.code as material_code,
                    m.name as material_name,
                    m.unit as material_unit,
                    m.rack_location,
                    COALESCE(u.name, u.username, 'System') as receiver_name
                FROM stock_mutations sm
                LEFT JOIN materials m ON sm.material_id = m.id
                LEFT JOIN users u ON sm.user_id = u.id
                WHERE sm.type = 'INITIAL_IMPORT'
            ) combined_inbound
        ";
        $params = [];
        if ($mode === 'update' && !empty($lastSyncTime)) {
            $query .= " WHERE created_at > ? OR completed_at > ?";
            $params = [$lastSyncTime, $lastSyncTime];
        }
        $query .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = [];

        while ($r = $stmt->fetch()) {
            $startRaw = (string)($r['started_at'] ?? '');
            $startTime = !empty($startRaw) ? strtotime($startRaw) : false;
            $startStr = $startTime ? date('d/m/Y H:i:s', $startTime) : ($r['started_at'] ?: '-');

            $finishRaw = (string)($r['completed_at'] ?? '');
            $finishTime = !empty($finishRaw) ? strtotime($finishRaw) : false;
            $finishStr = $finishTime ? date('d/m/Y H:i:s', $finishTime) : ($r['completed_at'] ?: '-');

            $durSec = (int)($r['duration_seconds'] ?? 0);
            $durMin = $durSec > 0 ? round($durSec / 60, 1) : 0;

            $rows[] = [
                $r['inbound_no'], // Primary Key (No. Inbound)
                $startStr,       // Waktu Mulai (Start)
                $finishStr,      // Waktu Selesai (Finish)
                $durMin,         // Durasi (Menit)
                $r['po_number'] ?: '-',
                $r['supplier'] ?: '-',
                $r['material_code'] ?: '-',
                $r['material_name'] ?: '-',
                (float)$r['qty'],
                $r['material_unit'] ?: 'Pcs',
                $r['rack_location'] ?: '-',
                $r['receiver_name'] ?: 'Admin',
                $r['notes'] ?: '-'
            ];
        }

        if (empty($rows) && $mode === 'update') {
            return buildSheetData($pdo, 'inbound', 'full', null);
        }

        return [
            'name' => 'Barang Masuk',
            'key_index' => 0, // Column 0 (No. Inbound)
            'headers' => [
                'No. Inbound',
                'Waktu Mulai (Start)',
                'Waktu Selesai (Finish)',
                'Durasi (Menit)',
                'No. PO',
                'Supplier',
                'Item No (SKU)',
                'Nama Kemas',
                'Qty In (+)',
                'Satuan',
                'Lokasi Rak',
                'Petugas Penerima',
                'Catatan'
            ],
            'rows' => $rows
        ];
    }

    if ($target === 'outbound') {
        $query = "
            SELECT * FROM (
                SELECT 
                    'TASK_PICKING' as outbound_type,
                    t.task_no as outbound_no,
                    m.code as material_code,
                    m.name as material_name,
                    m.unit as material_unit,
                    m.rack_location,
                    (CASE WHEN t.status = 'COMPLETED' THEN t.actual_qty ELSE t.target_qty END) as qty,
                    t.destination,
                    u_to.name as operator_name,
                    u_to.username as operator_username,
                    u_by.username as admin_username,
                    u_by.name as admin_name,
                    'Pengambilan Line (Operator Task)' as reason,
                    COALESCE(t.completion_notes, t.notes) as notes,
                    t.status,
                    COALESCE(t.started_at, t.created_at) as started_at,
                    COALESCE(t.completed_at, t.created_at) as completed_at,
                    COALESCE(t.duration_seconds, 0) as duration_seconds,
                    t.created_at
                FROM tasks t
                LEFT JOIN materials m ON t.material_id = m.id
                LEFT JOIN users u_to ON t.assigned_to = u_to.id
                LEFT JOIN users u_by ON t.assigned_by = u_by.id
                WHERE (t.task_type = 'PICKING' OR t.task_type IS NULL OR t.task_type = '')

                UNION ALL

                SELECT 
                    'MANUAL_OUTBOUND' as outbound_type,
                    o.outbound_no,
                    m.code as material_code,
                    m.name as material_name,
                    m.unit as material_unit,
                    m.rack_location,
                    o.qty,
                    o.destination,
                    o.issued_by as operator_name,
                    'admin' as operator_username,
                    o.issued_by as admin_username,
                    o.issued_by as admin_name,
                    o.reason,
                    o.notes,
                    'COMPLETED' as status,
                    COALESCE(o.started_at, o.created_at) as started_at,
                    COALESCE(o.completed_at, o.created_at) as completed_at,
                    COALESCE(o.duration_seconds, 0) as duration_seconds,
                    o.created_at
                FROM outbound_transactions o
                LEFT JOIN materials m ON o.material_id = m.id
            ) combined_outbound
        ";
        $params = [];
        if ($mode === 'update' && !empty($lastSyncTime)) {
            $query .= " WHERE created_at > ? OR completed_at > ?";
            $params = [$lastSyncTime, $lastSyncTime];
        }
        $query .= " ORDER BY created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = [];

        while ($r = $stmt->fetch()) {
            $startRaw = (string)($r['started_at'] ?? '');
            $startTime = !empty($startRaw) ? strtotime($startRaw) : false;
            $startStr = $startTime ? date('d/m/Y H:i:s', $startTime) : ($r['started_at'] ?: '-');

            $finishRaw = (string)($r['completed_at'] ?? '');
            $finishTime = !empty($finishRaw) ? strtotime($finishRaw) : false;
            $finishStr = $finishTime ? date('d/m/Y H:i:s', $finishTime) : ($r['completed_at'] ?: '-');

            $durSec = (int)($r['duration_seconds'] ?? 0);
            $durMin = $durSec > 0 ? round($durSec / 60, 1) : 0;

            $typeLabel = $r['outbound_type'] === 'TASK_PICKING' ? 'Task Operator' : 'Manual Admin';
            $operatorPIC = $r['operator_name'] ?: ($r['operator_username'] ?: 'Operator');

            $rows[] = [
                $r['outbound_no'], // Primary Key (No Transaksi)
                $startStr,        // Waktu Mulai (Start)
                $finishStr,       // Waktu Selesai (Finish)
                $durMin,          // Durasi (Menit)
                $typeLabel,
                $r['status'],
                $r['material_code'] ?: '-',
                $r['material_name'] ?: '-',
                (float)$r['qty'],
                $r['material_unit'] ?: 'Pcs',
                $r['rack_location'] ?: '-',
                $r['destination'] ?: '-',
                $operatorPIC,
                $r['reason'] ?: '-',
                $r['notes'] ?: '-'
            ];
        }

        if (empty($rows) && $mode === 'update') {
            return buildSheetData($pdo, 'outbound', 'full', null);
        }

        return [
            'name' => 'Barang Keluar',
            'key_index' => 0, // Column 0 (No Transaksi)
            'headers' => [
                'No. Transaksi',
                'Waktu Mulai (Start)',
                'Waktu Selesai (Finish)',
                'Durasi (Menit)',
                'Tipe Outbound',
                'Status',
                'Item No (SKU)',
                'Nama Kemas',
                'Qty Out (-)',
                'Satuan',
                'Lokasi Rak',
                'Tujuan Antar',
                'Petugas PIC',
                'Alasan / Keperluan',
                'Catatan'
            ],
            'rows' => $rows
        ];
    }

    if ($target === 'mutations') {
        $query = "
            SELECT sm.*, 
                   m.code as material_code, m.name as material_name, m.unit as material_unit, m.rack_location,
                   u.name as user_name, u.username as user_username
            FROM stock_mutations sm
            LEFT JOIN materials m ON sm.material_id = m.id
            LEFT JOIN users u ON sm.user_id = u.id
        ";
        $params = [];
        if ($mode === 'update' && !empty($lastSyncTime)) {
            $query .= " WHERE sm.created_at > ?";
            $params = [$lastSyncTime];
        }
        $query .= " ORDER BY sm.created_at DESC, sm.id DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = [];

        while ($r = $stmt->fetch()) {
            $createdAt = (string)($r['created_at'] ?? '');
            $createdTime = !empty($createdAt) ? strtotime($createdAt) : false;
            $createdFmt = $createdTime ? date('d/m/Y H:i:s', $createdTime) : ($createdAt ?: '-');

            $qtyChange = (float)$r['qty_change'];
            $inQty  = $qtyChange > 0 ? $qtyChange : 0;
            $outQty = $qtyChange < 0 ? abs($qtyChange) : 0;

            $typeLabel = $r['type'];
            if ($r['type'] === 'INBOUND') $typeLabel = 'BARANG MASUK';
            elseif ($r['type'] === 'OUTBOUND') $typeLabel = 'BARANG KELUAR';
            elseif ($r['type'] === 'TASK_PICKING') $typeLabel = 'TASK PICKING';
            elseif ($r['type'] === 'ADJUSTMENT') $typeLabel = 'PENYESUAIAN STOK';
            elseif ($r['type'] === 'INITIAL_IMPORT') $typeLabel = 'STOK AWAL';
            elseif (in_array($r['type'], ['TRANSFER_OUT', 'TRANSFER_IN', 'STOCK_TRANSFER', 'TRANSFER_LOCATION', 'RACK_MOVEMENT', 'MOVEMENT', 'TRANSFER'])) $typeLabel = 'STOCK TRANSFER';
            elseif ($r['type'] === 'VAS_OUTBOUND') $typeLabel = 'VAS DISPOSAL';

            $pic = $r['user_name'] ?: ($r['user_username'] ?: 'System');
            $refKey = $r['reference_no'] ? ($r['reference_no'] . '-' . $r['id']) : ('MUT-' . $r['id']);

            $rows[] = [
                $refKey, // Column 0: Unique Key
                $createdFmt,
                $typeLabel,
                $r['reference_no'] ?: '-',
                $r['material_code'] ?: '-',
                $r['material_name'] ?: '-',
                $inQty,
                $outQty,
                (float)$r['stock_after'],
                $r['material_unit'] ?: 'Pcs',
                $r['rack_location'] ?: '-',
                $r['notes'] ?: '-',
                $pic
            ];
        }

        if (empty($rows) && $mode === 'update') {
            return buildSheetData($pdo, 'mutations', 'full', null);
        }

        return [
            'name' => 'History Mutasi Stok',
            'key_index' => 0, // Column 0: Key
            'headers' => [
                'ID Key',
                'Waktu Transaksi',
                'Tipe Mutasi',
                'No. Referensi (PO / Task)',
                'Item No (SKU)',
                'Deskripsi Kemas / Gimmick',
                'Masuk (+)',
                'Keluar (-)',
                'Sisa Stok',
                'Satuan',
                'Lokasi Rak',
                'Keterangan / Catatan',
                'Petugas PIC'
            ],
            'rows' => $rows
        ];
    }

    return ['name' => 'Unknown', 'key_index' => 0, 'headers' => [], 'rows' => []];
}
