<?php
// api/maintenance.php - Super Admin Database Maintenance & Table Cleaner API
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../includes/auth.php';

// STRICT SUPER ADMIN ONLY
Auth::requireSuperAdmin();
$pdo = Database::getConnection();
$action = $_GET['action'] ?? ($_POST['action'] ?? 'stats');
$isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');

/**
 * Helper: Verify Super Admin Password for Security Confirmation
 */
function verifySuperAdminPassword(PDO $pdo, string $password): bool {
    if (empty($password)) return false;
    $userId = Auth::id();
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $u = $stmt->fetch();
    if (!$u) return false;
    return password_verify($password, $u['password']);
}

/**
 * Helper: Cadangkan seluruh isi tabel penting ke berkas JSON sebelum penghapusan.
 *
 * InfinityFree tidak menyediakan cadangan otomatis, sedangkan endpoint di berkas ini
 * menghapus data secara permanen. Cadangan otomatis dibuat lebih dulu supaya sebuah
 * kesalahan klik masih dapat dipulihkan.
 *
 * @return array{file:string,size:int}|null
 */
function createSafetyBackup(PDO $pdo, string $reason): ?array {
    $tables = [
        'users', 'materials', 'material_batches', 'inbound_transactions', 'outbound_transactions',
        'tasks', 'stock_opnames', 'stock_opname_items', 'stock_opname_item_stages',
        'stock_mutations', 'handovers', 'consumable_requests', 'consumable_request_items',
        'vas_transactions', 'menu_permissions', 'system_audit_logs'
    ];

    $dir = __DIR__ . '/../config/backups';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        error_log('[PackStock] Gagal membuat direktori cadangan: ' . $dir);
        return null;
    }

    $dump = [
        'aplikasi'   => 'PackStock WMS',
        'dibuat_pada'=> date('Y-m-d H:i:s'),
        'dibuat_oleh'=> Auth::username(),
        'alasan'     => $reason,
        'tabel'      => []
    ];

    foreach ($tables as $t) {
        try {
            $dump['tabel'][$t] = $pdo->query("SELECT * FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $dump['tabel'][$t] = null; // tabel tidak ada di instalasi ini
        }
    }

    $name = 'backup_' . date('Ymd_His') . '_' . preg_replace('/[^a-z0-9]+/i', '-', $reason) . '.json';
    $path = $dir . '/' . $name;

    $json = json_encode($dump, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || file_put_contents($path, $json) === false) {
        error_log('[PackStock] Gagal menulis berkas cadangan: ' . $path);
        return null;
    }

    // Simpan 20 cadangan terbaru saja.
    $existing = glob($dir . '/backup_*.json') ?: [];
    if (count($existing) > 20) {
        usort($existing, fn($a, $b) => filemtime($a) <=> filemtime($b));
        foreach (array_slice($existing, 0, count($existing) - 20) as $old) {
            @unlink($old);
        }
    }

    return ['file' => $name, 'size' => (int)filesize($path)];
}

/**
 * Helper: Set Foreign Key Checks based on Database Driver
 */
function setForeignKeyChecks(PDO $pdo, bool $enable, bool $isSqlite) {
    if ($isSqlite) {
        $val = $enable ? 'ON' : 'OFF';
        $pdo->exec("PRAGMA foreign_keys = {$val};");
    } else {
        $val = $enable ? 1 : 0;
        $pdo->exec("SET FOREIGN_KEY_CHECKS = {$val};");
    }
}

/**
 * Helper: Truncate or Delete Table data depending on Database Driver
 */
function clearTable(PDO $pdo, string $tableName, bool $isSqlite) {
    try {
        if ($isSqlite) {
            $pdo->exec("DELETE FROM `{$tableName}`");
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name='{$tableName}'");
        } else {
            $pdo->exec("TRUNCATE TABLE `{$tableName}`");
        }
    } catch (Throwable $e) {
        // Quietly ignore if table does not exist
    }
}

// 1. GET DATABASE STATISTICS (ROW COUNTS & TABLE SIZES - GLOBAL & PER TYPE)
if ($action === 'stats') {
    try {
        $stats = [];
        
        // Helper count functions
        $countSafe = function(string $sql) use ($pdo): int {
            try {
                $stmt = $pdo->query($sql);
                return (int)($stmt ? $stmt->fetchColumn() : 0);
            } catch (Throwable $e) {
                return 0;
            }
        };

        // 1. Materials Master
        $matKemas = $countSafe("SELECT COUNT(*) FROM materials WHERE item_type = 'PACKAGING' OR item_type IS NULL OR item_type = ''");
        $matGimmick = $countSafe("SELECT COUNT(*) FROM materials WHERE item_type = 'GIMMICK'");
        $stats['materials'] = ['label' => 'Master Stok Kemas', 'count' => $matKemas, 'count_kemas' => $matKemas, 'count_gimmick' => 0];
        $stats['gimmick'] = ['label' => 'Master Stok Gimmick', 'count' => $matGimmick, 'count_kemas' => 0, 'count_gimmick' => $matGimmick];

        // 2. Inbound
        $inTotal = $countSafe("SELECT COUNT(*) FROM inbound_transactions");
        $inKemas = $countSafe("SELECT COUNT(*) FROM inbound_transactions i JOIN materials m ON i.material_id = m.id WHERE m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = ''");
        $inGimmick = $countSafe("SELECT COUNT(*) FROM inbound_transactions i JOIN materials m ON i.material_id = m.id WHERE m.item_type = 'GIMMICK'");
        $stats['inbound_transactions'] = ['label' => 'Riwayat Barang Masuk', 'count' => $inTotal, 'count_kemas' => $inKemas, 'count_gimmick' => $inGimmick];

        // 3. Outbound
        $outTotal = $countSafe("SELECT COUNT(*) FROM outbound_transactions");
        $outKemas = $countSafe("SELECT COUNT(*) FROM outbound_transactions o JOIN materials m ON o.material_id = m.id WHERE m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = ''");
        $outGimmick = $countSafe("SELECT COUNT(*) FROM outbound_transactions o JOIN materials m ON o.material_id = m.id WHERE m.item_type = 'GIMMICK'");
        $stats['outbound_transactions'] = ['label' => 'Riwayat Barang Keluar', 'count' => $outTotal, 'count_kemas' => $outKemas, 'count_gimmick' => $outGimmick];

        // 4. Tasks
        $taskTotal = $countSafe("SELECT COUNT(*) FROM tasks");
        $taskKemas = $countSafe("SELECT COUNT(*) FROM tasks t JOIN materials m ON t.material_id = m.id WHERE m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = ''");
        $taskGimmick = $countSafe("SELECT COUNT(*) FROM tasks t JOIN materials m ON t.material_id = m.id WHERE m.item_type = 'GIMMICK'");
        $stats['tasks'] = ['label' => 'Penugasan Task Operator', 'count' => $taskTotal, 'count_kemas' => $taskKemas, 'count_gimmick' => $taskGimmick];

        // 5. Stock Mutations
        $mutTotal = $countSafe("SELECT COUNT(*) FROM stock_mutations");
        $mutKemas = $countSafe("SELECT COUNT(*) FROM stock_mutations sm JOIN materials m ON sm.material_id = m.id WHERE m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = ''");
        $mutGimmick = $countSafe("SELECT COUNT(*) FROM stock_mutations sm JOIN materials m ON sm.material_id = m.id WHERE m.item_type = 'GIMMICK'");
        $stats['stock_mutations'] = ['label' => 'Buku Log Mutasi Stok', 'count' => $mutTotal, 'count_kemas' => $mutKemas, 'count_gimmick' => $mutGimmick];

        // 6. Stock Opnames & Items
        $opTotal = $countSafe("SELECT COUNT(*) FROM stock_opnames");
        $opKemas = $countSafe("SELECT COUNT(DISTINCT o.id) FROM stock_opnames o JOIN stock_opname_items oi ON o.id = oi.opname_id JOIN materials m ON oi.material_id = m.id WHERE m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = ''");
        $opGimmick = $countSafe("SELECT COUNT(DISTINCT o.id) FROM stock_opnames o JOIN stock_opname_items oi ON o.id = oi.opname_id JOIN materials m ON oi.material_id = m.id WHERE m.item_type = 'GIMMICK'");
        $stats['stock_opnames'] = ['label' => 'Sesi Stock Opname & Dynamic', 'count' => $opTotal, 'count_kemas' => $opKemas, 'count_gimmick' => $opGimmick];

        $opItemTotal = $countSafe("SELECT COUNT(*) FROM stock_opname_items");
        $opItemKemas = $countSafe("SELECT COUNT(*) FROM stock_opname_items oi JOIN materials m ON oi.material_id = m.id WHERE m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = ''");
        $opItemGimmick = $countSafe("SELECT COUNT(*) FROM stock_opname_items oi JOIN materials m ON oi.material_id = m.id WHERE m.item_type = 'GIMMICK'");
        $stats['stock_opname_items'] = ['label' => 'Item Opname & Dynamic', 'count' => $opItemTotal, 'count_kemas' => $opItemKemas, 'count_gimmick' => $opItemGimmick];

        // 7. Consumable Requests
        $reqTotal = $countSafe("SELECT COUNT(*) FROM consumable_requests");
        $reqKemas = $countSafe("SELECT COUNT(DISTINCT cr.id) FROM consumable_requests cr JOIN consumable_request_items cri ON cr.id = cri.request_id JOIN materials m ON cri.material_id = m.id WHERE m.item_type = 'PACKAGING' OR m.item_type IS NULL OR m.item_type = ''");
        $reqGimmick = $countSafe("SELECT COUNT(DISTINCT cr.id) FROM consumable_requests cr JOIN consumable_request_items cri ON cr.id = cri.request_id JOIN materials m ON cri.material_id = m.id WHERE m.item_type = 'GIMMICK'");
        $stats['consumable_requests'] = ['label' => 'Permintaan Consumable Material', 'count' => $reqTotal, 'count_kemas' => $reqKemas, 'count_gimmick' => $reqGimmick];

        // 8. Global Tables
        $stats['handovers'] = ['label' => 'Serah Terima Shift (Handover)', 'count' => $countSafe("SELECT COUNT(*) FROM handovers"), 'count_kemas' => 0, 'count_gimmick' => 0];
        $stats['users'] = ['label' => 'Manajemen Pengguna', 'count' => $countSafe("SELECT COUNT(*) FROM users"), 'count_kemas' => 0, 'count_gimmick' => 0];

        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'current_user' => Auth::name(),
            'server_time' => date('Y-m-d H:i:s')
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        apiFail($e, 'Gagal membaca statistik database.');
    }
    exit;
}

// 2. CLEAN INDIVIDUAL TABLE OR GROUP (WITH SUPPORT FOR SPECIFIC TYPES)
if ($action === 'clean_table' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $tableKey = trim($input['table'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password konfirmasi Teknisi wajib diisi.']);
        exit;
    }

    if (!verifySuperAdminPassword($pdo, $password)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Verifikasi Gagal: Password Teknisi tidak sesuai! Tindakan dibatalkan.']);
        exit;
    }

    $backup = createSafetyBackup($pdo, 'clean-' . preg_replace('/[^a-z0-9]+/i', '', $tableKey));

    try {
        setForeignKeyChecks($pdo, false, $isSqlite);
        $clearedInfo = '';

        // SQL WHERE clauses for material types
        $sqlKemasIds = "SELECT id FROM materials WHERE item_type = 'PACKAGING' OR item_type IS NULL OR item_type = ''";
        $sqlGimmickIds = "SELECT id FROM materials WHERE item_type = 'GIMMICK'";

        switch ($tableKey) {
            // --- MASTER STOK ---
            case 'materials':
            case 'materials_kemas':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE item_type = 'PACKAGING' OR item_type IS NULL OR item_type = ''")->fetchColumn();
                $pkgIds = $pdo->query($sqlKemasIds)->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($pkgIds)) {
                    $placeholders = implode(',', array_fill(0, count($pkgIds), '?'));
                    $pdo->prepare("DELETE FROM material_batches WHERE material_id IN ($placeholders)")->execute($pkgIds);
                    $pdo->prepare("DELETE FROM materials WHERE id IN ($placeholders)")->execute($pkgIds);
                }
                $clearedInfo = "Master Stok Kemas ({$count} item)";
                break;

            case 'gimmick':
            case 'materials_gimmick':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE item_type = 'GIMMICK'")->fetchColumn();
                $gimmickIds = $pdo->query($sqlGimmickIds)->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($gimmickIds)) {
                    $placeholders = implode(',', array_fill(0, count($gimmickIds), '?'));
                    $pdo->prepare("DELETE FROM material_batches WHERE material_id IN ($placeholders)")->execute($gimmickIds);
                    $pdo->prepare("DELETE FROM materials WHERE id IN ($placeholders)")->execute($gimmickIds);
                }
                $clearedInfo = "Master Stok Gimmick ({$count} item & batch)";
                break;

            // --- INBOUND ---
            case 'inbound_kemas':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM inbound_transactions WHERE material_id IN ($sqlKemasIds)")->fetchColumn();
                $pdo->exec("DELETE FROM inbound_transactions WHERE material_id IN ($sqlKemasIds)");
                $clearedInfo = "Riwayat Barang Masuk Kemas ({$count} transaksi)";
                break;

            case 'inbound_gimmick':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM inbound_transactions WHERE material_id IN ($sqlGimmickIds)")->fetchColumn();
                $pdo->exec("DELETE FROM inbound_transactions WHERE material_id IN ($sqlGimmickIds)");
                $clearedInfo = "Riwayat Barang Masuk Gimmick ({$count} transaksi)";
                break;

            case 'inbound':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM inbound_transactions")->fetchColumn();
                clearTable($pdo, 'inbound_transactions', $isSqlite);
                $clearedInfo = "Riwayat Seluruh Barang Masuk ({$count} transaksi)";
                break;

            // --- OUTBOUND ---
            case 'outbound_kemas':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM outbound_transactions WHERE material_id IN ($sqlKemasIds)")->fetchColumn();
                $pdo->exec("DELETE FROM outbound_transactions WHERE material_id IN ($sqlKemasIds)");
                $clearedInfo = "Riwayat Barang Keluar Kemas ({$count} transaksi)";
                break;

            case 'outbound_gimmick':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM outbound_transactions WHERE material_id IN ($sqlGimmickIds)")->fetchColumn();
                $pdo->exec("DELETE FROM outbound_transactions WHERE material_id IN ($sqlGimmickIds)");
                $clearedInfo = "Riwayat Barang Keluar Gimmick ({$count} transaksi)";
                break;

            case 'outbound':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM outbound_transactions")->fetchColumn();
                clearTable($pdo, 'outbound_transactions', $isSqlite);
                $clearedInfo = "Riwayat Seluruh Barang Keluar Manual ({$count} transaksi)";
                break;

            // --- TASKS ---
            case 'tasks_kemas':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE material_id IN ($sqlKemasIds)")->fetchColumn();
                $pdo->exec("DELETE FROM tasks WHERE material_id IN ($sqlKemasIds)");
                $clearedInfo = "Penugasan Task Kemas ({$count} task)";
                break;

            case 'tasks_gimmick':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE material_id IN ($sqlGimmickIds)")->fetchColumn();
                $pdo->exec("DELETE FROM tasks WHERE material_id IN ($sqlGimmickIds)");
                $clearedInfo = "Penugasan Task Gimmick ({$count} task)";
                break;

            case 'tasks':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
                clearTable($pdo, 'tasks', $isSqlite);
                $clearedInfo = "Penugasan Seluruh Task Operator ({$count} task)";
                break;

            // --- OPNAME ---
            case 'opname_kemas':
                $countItems = (int)$pdo->query("SELECT COUNT(*) FROM stock_opname_items WHERE material_id IN ($sqlKemasIds)")->fetchColumn();
                $pdo->exec("DELETE FROM stock_opname_item_stages WHERE item_id IN (SELECT id FROM stock_opname_items WHERE material_id IN ($sqlKemasIds))");
                $pdo->exec("DELETE FROM stock_opname_items WHERE material_id IN ($sqlKemasIds)");
                $pdo->exec("DELETE FROM stock_opnames WHERE id NOT IN (SELECT DISTINCT opname_id FROM stock_opname_items)");
                $clearedInfo = "Data Stock Opname Kemas ({$countItems} item)";
                break;

            case 'opname_gimmick':
                $countItems = (int)$pdo->query("SELECT COUNT(*) FROM stock_opname_items WHERE material_id IN ($sqlGimmickIds)")->fetchColumn();
                $pdo->exec("DELETE FROM stock_opname_item_stages WHERE item_id IN (SELECT id FROM stock_opname_items WHERE material_id IN ($sqlGimmickIds))");
                $pdo->exec("DELETE FROM stock_opname_items WHERE material_id IN ($sqlGimmickIds)");
                $pdo->exec("DELETE FROM stock_opnames WHERE id NOT IN (SELECT DISTINCT opname_id FROM stock_opname_items)");
                $clearedInfo = "Data Stock Opname Gimmick ({$countItems} item)";
                break;

            case 'opname':
                $countSes = (int)$pdo->query("SELECT COUNT(*) FROM stock_opnames")->fetchColumn();
                clearTable($pdo, 'stock_opname_item_stages', $isSqlite);
                clearTable($pdo, 'stock_opname_audits', $isSqlite);
                clearTable($pdo, 'stock_opname_counts', $isSqlite);
                clearTable($pdo, 'stock_opname_items', $isSqlite);
                clearTable($pdo, 'stock_opnames', $isSqlite);
                $clearedInfo = "Seluruh Sesi Stock Opname & Dynamic Counting ({$countSes} Sesi)";
                break;

            // --- MUTATIONS ---
            case 'mutations_kemas':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM stock_mutations WHERE material_id IN ($sqlKemasIds)")->fetchColumn();
                $pdo->exec("DELETE FROM stock_mutations WHERE material_id IN ($sqlKemasIds)");
                $clearedInfo = "Buku Log Mutasi Kemas ({$count} entri)";
                break;

            case 'mutations_gimmick':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM stock_mutations WHERE material_id IN ($sqlGimmickIds)")->fetchColumn();
                $pdo->exec("DELETE FROM stock_mutations WHERE material_id IN ($sqlGimmickIds)");
                $clearedInfo = "Buku Log Mutasi Gimmick ({$count} entri)";
                break;

            case 'mutations':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM stock_mutations")->fetchColumn();
                clearTable($pdo, 'stock_mutations', $isSqlite);
                $clearedInfo = "Seluruh Buku Log Mutasi Stok ({$count} entri)";
                break;

            // --- HANDOVERS ---
            case 'handovers':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM handovers")->fetchColumn();
                clearTable($pdo, 'handovers', $isSqlite);
                $clearedInfo = "Riwayat Serah Terima Pekerjaan Shift ({$count} data)";
                break;

            // --- CONSUMABLE REQUESTS ---
            case 'consumable_kemas':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM consumable_request_items WHERE material_id IN ($sqlKemasIds)")->fetchColumn();
                $pdo->exec("DELETE FROM consumable_request_items WHERE material_id IN ($sqlKemasIds)");
                $pdo->exec("DELETE FROM consumable_requests WHERE id NOT IN (SELECT DISTINCT request_id FROM consumable_request_items)");
                $clearedInfo = "Permintaan Consumable Kemas ({$count} item)";
                break;

            case 'consumable_gimmick':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM consumable_request_items WHERE material_id IN ($sqlGimmickIds)")->fetchColumn();
                $pdo->exec("DELETE FROM consumable_request_items WHERE material_id IN ($sqlGimmickIds)");
                $pdo->exec("DELETE FROM consumable_requests WHERE id NOT IN (SELECT DISTINCT request_id FROM consumable_request_items)");
                $clearedInfo = "Permintaan Consumable Gimmick ({$count} item)";
                break;

            case 'consumable_requests':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM consumable_requests")->fetchColumn();
                clearTable($pdo, 'consumable_request_items', $isSqlite);
                clearTable($pdo, 'consumable_requests', $isSqlite);
                $clearedInfo = "Seluruh Permintaan Consumable Material ({$count} pengajuan)";
                break;

            default:
                setForeignKeyChecks($pdo, true, $isSqlite);
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Tabel database tidak dikenali.']);
                exit;
        }

        setForeignKeyChecks($pdo, true, $isSqlite);

        Auth::audit('DATA_CLEAN_TABLE', $tableKey, $clearedInfo . ($backup ? " | cadangan: {$backup['file']}" : ' | CADANGAN GAGAL DIBUAT'));

        echo json_encode([
            'success' => true,
            'backup'  => $backup,
            'message' => "Data berhasil dibersihkan: {$clearedInfo} telah dihapus permanen."
                . ($backup ? " Cadangan otomatis tersimpan sebagai {$backup['file']}." : ' Peringatan: cadangan otomatis gagal dibuat.')
        ]);
    } catch (Throwable $e) {
        setForeignKeyChecks($pdo, true, $isSqlite);
        http_response_code(500);
        apiFail($e, 'Gagal mengosongkan tabel.');
    }
    exit;
}

// 2b. CLEAN BULK DATA PER TYPE (KEMAS ATAU GIMMICK)
if ($action === 'clean_type_bulk' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $targetType = strtoupper(trim($input['type'] ?? '')); // 'PACKAGING' or 'GIMMICK'
    $mode = trim($input['mode'] ?? 'transactions_only'); // 'transactions_only' or 'full'
    $resetStockZero = !empty($input['reset_stock_zero']);
    $password = trim($input['password'] ?? '');

    if (!in_array($targetType, ['PACKAGING', 'GIMMICK'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Tipe inventory harus PACKAGING (Kemas) atau GIMMICK.']);
        exit;
    }

    if (empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password konfirmasi Teknisi wajib diisi.']);
        exit;
    }

    if (!verifySuperAdminPassword($pdo, $password)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Verifikasi Gagal: Password Teknisi tidak sesuai! Tindakan dibatalkan.']);
        exit;
    }

    $typeName = ($targetType === 'PACKAGING') ? 'Kemas (Packaging)' : 'Gimmick';
    $backup = createSafetyBackup($pdo, 'bulk-' . strtolower($targetType) . '-' . $mode);

    try {
        setForeignKeyChecks($pdo, false, $isSqlite);

        $sqlTypeIds = ($targetType === 'PACKAGING')
            ? "SELECT id FROM materials WHERE item_type = 'PACKAGING' OR item_type IS NULL OR item_type = ''"
            : "SELECT id FROM materials WHERE item_type = 'GIMMICK'";

        // 1. Delete Inbound for this type
        $pdo->exec("DELETE FROM inbound_transactions WHERE material_id IN ($sqlTypeIds)");

        // 2. Delete Outbound for this type
        $pdo->exec("DELETE FROM outbound_transactions WHERE material_id IN ($sqlTypeIds)");

        // 3. Delete Tasks for this type
        $pdo->exec("DELETE FROM tasks WHERE material_id IN ($sqlTypeIds)");

        // 4. Delete Stock Mutations for this type
        $pdo->exec("DELETE FROM stock_mutations WHERE material_id IN ($sqlTypeIds)");

        // 5. Delete Opnames & Stages for this type
        $pdo->exec("DELETE FROM stock_opname_item_stages WHERE item_id IN (SELECT id FROM stock_opname_items WHERE material_id IN ($sqlTypeIds))");
        $pdo->exec("DELETE FROM stock_opname_items WHERE material_id IN ($sqlTypeIds)");
        $pdo->exec("DELETE FROM stock_opnames WHERE id NOT IN (SELECT DISTINCT opname_id FROM stock_opname_items)");

        // 6. Delete Consumable requests for this type
        $pdo->exec("DELETE FROM consumable_request_items WHERE material_id IN ($sqlTypeIds)");
        $pdo->exec("DELETE FROM consumable_requests WHERE id NOT IN (SELECT DISTINCT request_id FROM consumable_request_items)");

        // 7. Delete VAS transactions if any
        try {
            $pdo->exec("DELETE FROM vas_transactions WHERE material_id IN ($sqlTypeIds)");
        } catch (Throwable $ignored) {}

        if ($mode === 'full') {
            // Full Reset: Also delete material batches and master materials
            $pdo->exec("DELETE FROM material_batches WHERE material_id IN ($sqlTypeIds)");
            $pdo->exec("DELETE FROM materials WHERE id IN ($sqlTypeIds)");
            $infoMsg = "Reset Total Tipe {$typeName} BERHASIL! Seluruh Master SKU, batch, dan riwayat transaksi tipe {$typeName} telah dibersihkan secara permanen.";
        } else {
            // Transactions Only
            if ($resetStockZero) {
                if ($targetType === 'PACKAGING') {
                    $pdo->exec("UPDATE materials SET current_stock = 0 WHERE item_type = 'PACKAGING' OR item_type IS NULL OR item_type = ''");
                } else {
                    $pdo->exec("UPDATE materials SET current_stock = 0 WHERE item_type = 'GIMMICK'");
                }
            }
            $infoMsg = "Pembersihan Transaksi Tipe {$typeName} BERHASIL! Riwayat Inbound, Outbound, Task, Mutasi, dan Opname khusus {$typeName} telah dikosongkan. Master SKU tetap tersimpan aman" . ($resetStockZero ? " (Stok aktual direset ke 0)." : ".");
        }

        setForeignKeyChecks($pdo, true, $isSqlite);

        Auth::audit('DATA_CLEAN_TYPE_BULK', $targetType, "Mode: {$mode}" . ($backup ? " | Cadangan: {$backup['file']}" : ' | CADANGAN GAGAL DIBUAT'));

        echo json_encode([
            'success' => true,
            'backup'  => $backup,
            'message' => $infoMsg . ($backup ? " Cadangan otomatis tersimpan sebagai {$backup['file']}." : ' Peringatan: cadangan otomatis gagal dibuat.')
        ]);
    } catch (Throwable $e) {
        setForeignKeyChecks($pdo, true, $isSqlite);
        http_response_code(500);
        apiFail($e, "Gagal memproses pembersihan massal tipe {$typeName}.");
    }
    exit;
}

// 3. CLEAN ALL TRANSACTION DATA (RETAIN MATERIALS & USERS)
if ($action === 'clean_all_transactions' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $password = trim($input['password'] ?? '');
    $resetStockZero = !empty($input['reset_stock_zero']);

    if (!verifySuperAdminPassword($pdo, $password)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Verifikasi Gagal: Password Teknisi tidak sesuai! Tindakan dibatalkan.']);
        exit;
    }

    $backup = createSafetyBackup($pdo, 'clean-all-transactions');

    try {
        setForeignKeyChecks($pdo, false, $isSqlite);

        clearTable($pdo, 'inbound_transactions', $isSqlite);
        clearTable($pdo, 'outbound_transactions', $isSqlite);
        clearTable($pdo, 'tasks', $isSqlite);
        clearTable($pdo, 'stock_opname_item_stages', $isSqlite);
        clearTable($pdo, 'stock_opname_audits', $isSqlite);
        clearTable($pdo, 'stock_opname_counts', $isSqlite);
        clearTable($pdo, 'stock_opname_items', $isSqlite);
        clearTable($pdo, 'stock_opnames', $isSqlite);
        clearTable($pdo, 'stock_mutations', $isSqlite);
        clearTable($pdo, 'handovers', $isSqlite);
        clearTable($pdo, 'consumable_request_items', $isSqlite);
        clearTable($pdo, 'consumable_requests', $isSqlite);

        if ($resetStockZero) {
            $pdo->exec("UPDATE materials SET current_stock = 0;");
        }

        setForeignKeyChecks($pdo, true, $isSqlite);

        Auth::audit('DATA_CLEAN_ALL_TRANSACTIONS', 'seluruh transaksi', ($resetStockZero ? 'Stok direset ke 0. ' : '') . ($backup ? "Cadangan: {$backup['file']}" : 'CADANGAN GAGAL DIBUAT'));

        echo json_encode([
            'success' => true,
            'backup'  => $backup,
            'message' => 'Seluruh riwayat transaksi (Inbound, Outbound, Task, Opname, Mutasi, Handover, Request Consumable) telah berhasil dikosongkan. Master Material dan User tetap aman.'
                . ($backup ? " Cadangan otomatis tersimpan sebagai {$backup['file']}." : ' Peringatan: cadangan otomatis gagal dibuat.')
        ]);
    } catch (Throwable $e) {
        setForeignKeyChecks($pdo, true, $isSqlite);
        http_response_code(500);
        apiFail($e, 'Gagal membersihkan transaksi.');
    }
    exit;
}

// 4. FACTORY RESET (CLEAN EVERYTHING EXCEPT SUPER ADMIN / USERS)
if ($action === 'factory_reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $password = trim($input['password'] ?? '');

    if (!verifySuperAdminPassword($pdo, $password)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Verifikasi Gagal: Password Teknisi tidak sesuai! Tindakan dibatalkan.']);
        exit;
    }

    $backup = createSafetyBackup($pdo, 'factory-reset');

    try {
        setForeignKeyChecks($pdo, false, $isSqlite);

        clearTable($pdo, 'materials', $isSqlite);
        clearTable($pdo, 'material_batches', $isSqlite);
        clearTable($pdo, 'vas_transactions', $isSqlite);
        clearTable($pdo, 'inbound_transactions', $isSqlite);
        clearTable($pdo, 'outbound_transactions', $isSqlite);
        clearTable($pdo, 'tasks', $isSqlite);
        clearTable($pdo, 'stock_opname_item_stages', $isSqlite);
        clearTable($pdo, 'stock_opname_audits', $isSqlite);
        clearTable($pdo, 'stock_opname_counts', $isSqlite);
        clearTable($pdo, 'stock_opname_items', $isSqlite);
        clearTable($pdo, 'stock_opnames', $isSqlite);
        clearTable($pdo, 'stock_mutations', $isSqlite);
        clearTable($pdo, 'handovers', $isSqlite);
        clearTable($pdo, 'consumable_request_items', $isSqlite);
        clearTable($pdo, 'consumable_requests', $isSqlite);

        setForeignKeyChecks($pdo, true, $isSqlite);

        Auth::audit('DATA_FACTORY_RESET', 'seluruh database', $backup ? "Cadangan: {$backup['file']}" : 'CADANGAN GAGAL DIBUAT');

        echo json_encode([
            'success' => true,
            'backup'  => $backup,
            'message' => 'Reset Database Penuh (Factory Reset) Berhasil! Seluruh data stok dan transaksi telah dikosongkan. Database siap untuk diisi data baru.'
                . ($backup ? " Cadangan otomatis tersimpan sebagai {$backup['file']}." : ' Peringatan: cadangan otomatis gagal dibuat.')
        ]);
    } catch (Throwable $e) {
        setForeignKeyChecks($pdo, true, $isSqlite);
        http_response_code(500);
        apiFail($e, 'Gagal melakukan factory reset.');
    }
    exit;
}

// 4b. CADANGAN MANUAL — BUAT, DAFTAR, UNDUH
if ($action === 'backup_create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $backup = createSafetyBackup($pdo, 'manual');
    if (!$backup) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Cadangan gagal dibuat. Periksa izin tulis pada folder config/backups di server.']);
        exit;
    }
    Auth::audit('BACKUP_CREATE', $backup['file'], 'Ukuran ' . $backup['size'] . ' byte');
    echo json_encode([
        'success' => true,
        'backup'  => $backup,
        'message' => "Cadangan berhasil dibuat: {$backup['file']} (" . number_format($backup['size'] / 1024, 1, ',', '.') . " KB)."
    ]);
    exit;
}

if ($action === 'backup_list') {
    $dir = __DIR__ . '/../config/backups';
    $items = [];
    foreach (glob($dir . '/backup_*.json') ?: [] as $f) {
        $items[] = [
            'file'       => basename($f),
            'size'       => (int)filesize($f),
            'created_at' => date('Y-m-d H:i:s', (int)filemtime($f))
        ];
    }
    usort($items, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    echo json_encode(['success' => true, 'backups' => $items]);
    exit;
}

if ($action === 'backup_download') {
    $file = basename(trim($_GET['file'] ?? ''));   // basename menutup path traversal
    $path = __DIR__ . '/../config/backups/' . $file;

    if ($file === '' || !preg_match('/^backup_[\w.-]+\.json$/', $file) || !is_file($path)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Berkas cadangan tidak ditemukan.']);
        exit;
    }

    Auth::audit('BACKUP_DOWNLOAD', $file);

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $file . '"');
    header('Content-Length: ' . filesize($path));
    readfile($path);
    exit;
}

// 5. TOGGLE MAINTENANCE MODE
if ($action === 'toggle_maintenance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $active = !empty($input['active']);
    $flagFile = __DIR__ . '/../config/maintenance.flag';

    if ($active) {
        $data = [
            'active' => true,
            'activated_at' => date('Y-m-d H:i:s'),
            'activated_by' => Auth::username()
        ];
        file_put_contents($flagFile, json_encode($data));
        Auth::audit('MAINTENANCE_ON', 'situs dikunci');
        echo json_encode([
            'success' => true,
            'maintenance' => true,
            'message' => 'Mode Maintenance BERHASIL diaktifkan! Situs sekarang dikunci untuk non-Teknisi.'
        ]);
    } else {
        if (file_exists($flagFile)) {
            unlink($flagFile);
        }
        Auth::audit('MAINTENANCE_OFF', 'situs dibuka kembali');
        echo json_encode([
            'success' => true,
            'maintenance' => false,
            'message' => 'Mode Maintenance BERHASIL dinonaktifkan! Situs sekarang terbuka untuk semua user.'
        ]);
    }
    exit;
}

// 6. GET MAINTENANCE STATUS
if ($action === 'maintenance_status') {
    echo json_encode([
        'success' => true,
        'maintenance' => Auth::isMaintenanceMode()
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Aksi maintenance tidak valid.']);
