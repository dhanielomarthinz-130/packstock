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
        echo json_encode(['success' => false, 'message' => 'Gagal membaca statistik database: ' . $e->getMessage()]);
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

        echo json_encode([
            'success' => true,
            'message' => "Tabel berhasil dikosongkan: {$clearedInfo} telah dibersihkan secara permanen."
        ]);
    } catch (Throwable $e) {
        setForeignKeyChecks($pdo, true, $isSqlite);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal mengosongkan tabel: ' . $e->getMessage()]);
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

        echo json_encode([
            'success' => true,
            'message' => 'Seluruh riwayat transaksi (Inbound, Outbound, Task, Opname, Mutasi, Handover, Request Consumable) telah berhasil dikosongkan. Master Material dan User tetap aman.'
        ]);
    } catch (Throwable $e) {
        setForeignKeyChecks($pdo, true, $isSqlite);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal membersihkan transaksi: ' . $e->getMessage()]);
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

        echo json_encode([
            'success' => true,
            'message' => 'Reset Database Penuh (Factory Reset) Berhasil! Seluruh data stok dan transaksi telah dikosongkan. Database siap untuk diisi data baru.'
        ]);
    } catch (Throwable $e) {
        setForeignKeyChecks($pdo, true, $isSqlite);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Gagal melakukan factory reset: ' . $e->getMessage()]);
    }
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
        echo json_encode([
            'success' => true,
            'maintenance' => true,
            'message' => 'Mode Maintenance BERHASIL diaktifkan! Situs sekarang dikunci untuk non-Teknisi.'
        ]);
    } else {
        if (file_exists($flagFile)) {
            unlink($flagFile);
        }
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
