<?php
// scripts/reset_transactions_keep_stock.php - Cut-Off Riwayat Transaksi & Pertahankan Sisa Stok Berjalan
require_once __DIR__ . '/../config/database.php';

function executeResetTransactionsKeepStock(PDO $pdo, ?string $actor = 'System / CLI'): array {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $isSqlite = ($driver === 'sqlite');

    // 1. Cadangkan seluruh database ke file JSON sebelum tindakan apa pun
    $backupDir = __DIR__ . '/../config/backups';
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }

    $tablesToBackup = [
        'materials', 'material_batches', 'inbound_transactions', 'outbound_transactions',
        'tasks', 'stock_mutations', 'stock_opnames', 'stock_opname_items', 'stock_opname_item_stages',
        'consumable_requests', 'consumable_request_items', 'vas_transactions', 'handovers', 'users'
    ];

    $backupData = [
        'aplikasi'    => 'PackStock WMS',
        'tipe_aksi'   => 'CUTOFF_TRANSAKSI_KEEP_CURRENT_STOCK',
        'dibuat_pada' => date('Y-m-d H:i:s'),
        'dieksekusi_oleh' => $actor,
        'driver'      => $driver,
        'data'        => []
    ];

    foreach ($tablesToBackup as $tbl) {
        try {
            $stmt = $pdo->query("SELECT * FROM `{$tbl}`");
            $backupData['data'][$tbl] = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (Throwable $e) {
            $backupData['data'][$tbl] = [];
        }
    }

    $backupFileName = 'backup_before_cutoff_' . date('Ymd_His') . '.json';
    $backupFilePath = $backupDir . '/' . $backupFileName;
    $saved = @file_put_contents($backupFilePath, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($saved === false) {
        throw new RuntimeException("Gagal membuat berkas cadangan (backup) di {$backupFilePath}. Tindakan dibatalkan demi keamanan.");
    }

    // 2. Catat statistik sebelum reset
    $countSafe = function(string $table) use ($pdo): int {
        try {
            return (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    };

    $clearedStats = [
        'inbound_transactions' => $countSafe('inbound_transactions'),
        'outbound_transactions' => $countSafe('outbound_transactions'),
        'tasks' => $countSafe('tasks'),
        'stock_mutations' => $countSafe('stock_mutations'),
        'stock_opnames' => $countSafe('stock_opnames'),
        'stock_opname_items' => $countSafe('stock_opname_items'),
        'consumable_requests' => $countSafe('consumable_requests'),
        'vas_transactions' => $countSafe('vas_transactions'),
        'handovers' => $countSafe('handovers'),
    ];

    // 3. Matikan foreign keys
    if ($isSqlite) {
        $pdo->exec("PRAGMA foreign_keys = OFF;");
    } else {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    }

    // 4. Kosongkan seluruh tabel transaksi
    $transactionTables = [
        'inbound_transactions',
        'outbound_transactions',
        'tasks',
        'stock_mutations',
        'stock_opname_item_stages',
        'stock_opname_audits',
        'stock_opname_counts',
        'stock_opname_items',
        'stock_opnames',
        'consumable_request_items',
        'consumable_requests',
        'vas_transactions',
        'handovers'
    ];

    foreach ($transactionTables as $tbl) {
        try {
            if ($isSqlite) {
                $pdo->exec("DELETE FROM `{$tbl}`");
                try {
                    $pdo->exec("DELETE FROM sqlite_sequence WHERE name='{$tbl}'");
                } catch (Throwable $e) {}
            } else {
                $pdo->exec("TRUNCATE TABLE `{$tbl}`");
            }
        } catch (Throwable $e) {
            // Jika truncate gagal karena constraint, gunakan DELETE
            try {
                $pdo->exec("DELETE FROM `{$tbl}`");
            } catch (Throwable $ignored) {}
        }
    }

    // 5. Buat saldo awal mutasi (INITIAL_IMPORT) untuk setiap material sesuai current_stock saat ini
    $materials = $pdo->query("SELECT id, name, current_stock FROM materials")->fetchAll(PDO::FETCH_ASSOC);
    $totalMaterials = count($materials);
    $totalStock = 0.0;
    $now = date('Y-m-d H:i:s');

    $stmtInsertMut = $pdo->prepare("
        INSERT INTO stock_mutations (
            material_id, type, qty_change, stock_before, stock_after, 
            reference_no, notes, user_id, created_at
        ) VALUES (
            ?, 'INITIAL_IMPORT', ?, 0, ?, 
            'SALDO-AWAL-CUTOFF', 'Saldo Awal Cut-Off Stok Berjalan', NULL, ?
        )
    ");

    foreach ($materials as $m) {
        $stock = (float)($m['current_stock'] ?? 0);
        $totalStock += $stock;
        
        // Simpan catatan saldo awal pada buku mutasi agar kartu stok tetap sinkron
        $stmtInsertMut->execute([
            $m['id'],
            $stock,
            $stock,
            $now
        ]);
    }

    // 6. Hidupkan kembali foreign keys
    if ($isSqlite) {
        $pdo->exec("PRAGMA foreign_keys = ON;");
    } else {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    }

    // 7. Catat ke audit log
    try {
        $stmtAudit = $pdo->prepare("
            INSERT INTO system_audit_logs (actor_username, action, target, detail, created_at)
            VALUES (?, 'CUTOFF_TRANSACTIONS_KEEP_STOCK', 'GLOBAL', ?, ?)
        ");
        $stmtAudit->execute([
            $actor,
            "Cut-off transaksi berhasil. {$totalMaterials} SKU material dipertahankan dengan total stok berjalan {$totalStock}.",
            $now
        ]);
    } catch (Throwable $e) {}

    return [
        'success'         => true,
        'total_materials' => $totalMaterials,
        'total_stock'     => $totalStock,
        'cleared_stats'   => $clearedStats,
        'backup_file'     => $backupFileName,
        'backup_path'     => $backupFilePath
    ];
}

// Jalankan jika dipanggil via CLI
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "=================================================================\n";
    echo " PackStock WMS - Cut-Off Transaksi & Retensi Stok Berjalan\n";
    echo "=================================================================\n\n";

    try {
        $pdo = Database::getConnection();
        $res = executeResetTransactionsKeepStock($pdo, 'CLI Administrator');
        echo "✅ BERHASIL!\n";
        echo "- File Backup Aman      : {$res['backup_file']}\n";
        echo "- Total Master Material : {$res['total_materials']} SKU\n";
        echo "- Total Stok Sisa       : {$res['total_stock']} unit (Tetap Utuh)\n\n";
        echo "Ringkasan Riwayat Yang Dibersihkan:\n";
        foreach ($res['cleared_stats'] as $tbl => $count) {
            echo "  * {$tbl}: {$count} data dihapus\n";
        }
        echo "\nSeluruh transaksi telah di-reset dan kartu stok kini dimulai dari saldo berjalan saat ini.\n";
    } catch (Throwable $e) {
        echo "❌ GAGAL: " . $e->getMessage() . "\n";
        exit(1);
    }
}
