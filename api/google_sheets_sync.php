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
            'vas'       => null,
            'inbound'   => null,
            'outbound'  => null
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

    $config['web_app_url'] = $webAppUrl;
    if (saveGoogleSheetsConfig($configFile, $config)) {
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
// 4. SYNC DATA (STOCK INVENTORY, STOCK VAS, BARANG MASUK, BARANG KELUAR)
// =========================================================================
if ($action === 'sync') {
    $webAppUrl = $config['web_app_url'];
    if (empty($webAppUrl)) {
        echo json_encode(['success' => false, 'message' => 'URL Google Apps Script Web App belum diisi! Silakan buka tab Pengaturan di modal sync.']);
        exit;
    }

    $target = trim($_GET['target'] ?? ($_POST['target'] ?? 'all'));
    $mode   = trim($_GET['mode'] ?? ($_POST['mode'] ?? 'full')); // 'update' (incremental) or 'full'

    $targetsToProcess = [];
    if ($target === 'all') {
        $targetsToProcess = ['inventory', 'vas', 'inbound', 'outbound'];
    } elseif (in_array($target, ['inventory', 'vas', 'inbound', 'outbound'])) {
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
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POSTREDIR => 7,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 45
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(['success' => false, 'message' => "Gagal mengirim data ke Google Sheets: {$curlErr}"]);
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
        ";
        
        $params = [];
        if ($mode === 'update' && !empty($lastSyncTime)) {
            $query .= " WHERE m.updated_at > ? OR m.created_at > ?";
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
                $r['category'] ?: 'Packaging Material',
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
                $r['category'] ?: 'Packaging Material',
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
        $query = "
            SELECT 
                i.inbound_no,
                i.po_number,
                i.supplier,
                i.qty,
                i.created_at,
                i.notes,
                m.code as material_code,
                m.name as material_name,
                m.unit as material_unit,
                m.rack_location,
                u.name as receiver_name
            FROM inbound_transactions i
            JOIN materials m ON i.material_id = m.id
            LEFT JOIN users u ON i.received_by = u.id
        ";
        $params = [];
        if ($mode === 'update' && !empty($lastSyncTime)) {
            $query .= " WHERE i.created_at > ?";
            $params = [$lastSyncTime];
        }
        $query .= " ORDER BY i.created_at DESC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $rows = [];

        while ($r = $stmt->fetch()) {
            $createdRaw = (string)($r['created_at'] ?? '');
            $time = !empty($createdRaw) ? strtotime($createdRaw) : false;
            $dateStr = $time ? date('d/m/Y H:i:s', $time) : ($r['created_at'] ?: '-');

            $rows[] = [
                $r['inbound_no'], // Primary Key (No. Inbound)
                $dateStr,
                $r['po_number'] ?: '-',
                $r['supplier'] ?: '-',
                $r['material_code'],
                $r['material_name'],
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
                'Tanggal & Waktu',
                'No. PO',
                'Supplier',
                'Item No (SKU)',
                'Nama Packaging Material',
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
                    COALESCE(t.completed_at, t.created_at) as completed_at,
                    t.created_at
                FROM tasks t
                JOIN materials m ON t.material_id = m.id
                JOIN users u_to ON t.assigned_to = u_to.id
                LEFT JOIN users u_by ON t.assigned_by = u_by.id

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
                    COALESCE(o.completed_at, o.created_at) as completed_at,
                    o.created_at
                FROM outbound_transactions o
                JOIN materials m ON o.material_id = m.id
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
            $dateRaw = (string)($r['completed_at'] ?: ($r['created_at'] ?? ''));
            $time = !empty($dateRaw) ? strtotime($dateRaw) : false;
            $dateStr = $time ? date('d/m/Y H:i:s', $time) : ($dateRaw ?: '-');

            $typeLabel = $r['outbound_type'] === 'TASK_PICKING' ? 'Task Operator' : 'Manual Admin';
            $operatorPIC = $r['operator_name'] ?: ($r['operator_username'] ?: 'Operator');

            $rows[] = [
                $r['outbound_no'], // Primary Key (No Transaksi)
                $dateStr,
                $typeLabel,
                $r['status'],
                $r['material_code'],
                $r['material_name'],
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
                'Tanggal & Waktu',
                'Tipe Outbound',
                'Status',
                'Item No (SKU)',
                'Nama Packaging Material',
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

    return ['name' => 'Unknown', 'key_index' => 0, 'headers' => [], 'rows' => []];
}
