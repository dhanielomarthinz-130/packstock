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

// 1. GET DATABASE STATISTICS (ROW COUNTS & TABLE SIZES)
if ($action === 'stats') {
    try {
        $stats = [];
        
        $tables = [
            'materials'            => 'Stock Kemas',
            'inbound_transactions' => 'Riwayat Barang Masuk',
            'outbound_transactions'=> 'Riwayat Barang Keluar',
            'tasks'                => 'Penugasan Task Operator',
            'stock_opnames'        => 'Sesi Stock Opname & Dynamic',
            'stock_opname_items'   => 'Item Opname & Dynamic',
            'stock_mutations'      => 'Buku Log Mutasi Stok',
            'handovers'            => 'Serah Terima Shift (Handover)',
            'consumable_requests'  => 'Permintaan Consumable Material',
            'users'                => 'Manajemen Pengguna'
        ];

        foreach ($tables as $t => $label) {
            try {
                $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM `{$t}`");
                $stats[$t] = [
                    'label' => $label,
                    'count' => (int)$stmt->fetchColumn()
                ];
            } catch (Throwable $e) {
                $stats[$t] = ['label' => $label, 'count' => 0];
            }
        }

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

// 2. CLEAN INDIVIDUAL TABLE OR GROUP
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

        switch ($tableKey) {
            case 'materials':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();
                clearTable($pdo, 'materials', $isSqlite);
                $clearedInfo = "Master Stok Material ({$count} item)";
                break;

            case 'inbound':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM inbound_transactions")->fetchColumn();
                clearTable($pdo, 'inbound_transactions', $isSqlite);
                $clearedInfo = "Riwayat Barang Masuk ({$count} transaksi)";
                break;

            case 'outbound':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM outbound_transactions")->fetchColumn();
                clearTable($pdo, 'outbound_transactions', $isSqlite);
                $clearedInfo = "Riwayat Barang Keluar Manual ({$count} transaksi)";
                break;

            case 'tasks':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
                clearTable($pdo, 'tasks', $isSqlite);
                $clearedInfo = "Penugasan Task Operator ({$count} task)";
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

            case 'mutations':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM stock_mutations")->fetchColumn();
                clearTable($pdo, 'stock_mutations', $isSqlite);
                $clearedInfo = "Buku Log Mutasi Stok ({$count} entri)";
                break;

            case 'handovers':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM handovers")->fetchColumn();
                clearTable($pdo, 'handovers', $isSqlite);
                $clearedInfo = "Riwayat Serah Terima Pekerjaan Shift ({$count} data)";
                break;

            case 'consumable_requests':
                $count = (int)$pdo->query("SELECT COUNT(*) FROM consumable_requests")->fetchColumn();
                clearTable($pdo, 'consumable_request_items', $isSqlite);
                clearTable($pdo, 'consumable_requests', $isSqlite);
                $clearedInfo = "Permintaan Consumable Material ({$count} pengajuan)";
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
            'message' => "Tabel berhasil dikosongkan: {$clearedInfo} telah dibersihkan secara permanen."
                . ($backup ? " Cadangan otomatis tersimpan sebagai {$backup['file']}." : ' Peringatan: cadangan otomatis gagal dibuat.')
        ]);
    } catch (Throwable $e) {
        setForeignKeyChecks($pdo, true, $isSqlite);
        http_response_code(500);
        apiFail($e, 'Gagal mengosongkan tabel.');
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
