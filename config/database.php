<?php
// config/database.php - Dual MySQL / SQLite Database Manager with Auto-Migration

class Database {
    private static ?PDO $pdo = null;

    private static function isLiveEnvironment(): bool {
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
        return !empty($host) && !in_array($host, ['localhost', '127.0.0.1', '::1']) && !str_contains($host, 'localhost');
    }

    public static function getConnection(): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        // Load custom environment config if present (overrides defaults)
        $envFile = __DIR__ . '/env.php';
        $env = file_exists($envFile) ? (include $envFile) : [];

        $isLive = self::isLiveEnvironment();

        $mysqlHost = $env['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? '127.0.0.1');
        $mysqlPort = $env['DB_PORT'] ?? ($_SERVER['DB_PORT'] ?? '3306');
        $mysqlUser = $env['DB_USER'] ?? ($_SERVER['DB_USER'] ?? 'root');
        $mysqlPass = $env['DB_PASS'] ?? ($_SERVER['DB_PASS'] ?? '');
        $dbName    = $env['DB_NAME'] ?? ($_SERVER['DB_NAME'] ?? 'packstock_db');

        // Try connecting to MySQL
        try {
            // If local XAMPP (root with empty password or localhost), try creating DB if not exists
            if (!$isLive && $mysqlUser === 'root') {
                try {
                    $initPdo = new PDO("mysql:host={$mysqlHost};port={$mysqlPort};charset=utf8mb4", $mysqlUser, $mysqlPass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_TIMEOUT => 2
                    ]);
                    $initPdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                } catch (Exception $ignored) {}
            }

            // Connect directly to the database
            self::$pdo = new PDO("mysql:host={$mysqlHost};port={$mysqlPort};dbname={$dbName};charset=utf8mb4", $mysqlUser, $mysqlPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            self::initMySQLTables(self::$pdo);
            return self::$pdo;
        } catch (Exception $e) {
            // Fallback to SQLite if MySQL is unavailable
            $sqlitePath = __DIR__ . '/packstock.sqlite';
            self::$pdo = new PDO("sqlite:" . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            self::initSQLiteTables(self::$pdo);
            return self::$pdo;
        }
    }

    private static function initMySQLTables(PDO $pdo): void {
        // Users (Super Admin, Admin, Operator)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `username` VARCHAR(50) NOT NULL UNIQUE,
                `password` VARCHAR(255) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `role` ENUM('superadmin', 'admin', 'operator', 'teknisi') NOT NULL DEFAULT 'operator',
                `shift` VARCHAR(50) DEFAULT 'Shift A (Pagi)',
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Ensure role column enum includes 'superadmin' if table existed prior
        try {
            $pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` ENUM('superadmin', 'admin', 'operator', 'teknisi') NOT NULL DEFAULT 'operator'");
        } catch (Throwable $e) {
            // Ignored if SQLite or already modified
        }

        // Materials (Packaging Material Catalog) - matching Item No, Item Description, Ending Stock
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `materials` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `code` VARCHAR(100) NOT NULL UNIQUE,
                `name` VARCHAR(255) NOT NULL,
                `category` VARCHAR(100) DEFAULT 'Karton Box',
                `unit` VARCHAR(30) DEFAULT 'Pcs',
                `rack_location` VARCHAR(100) DEFAULT 'Gudang Utama',
                `min_stock` INT DEFAULT 20,
                `current_stock` INT DEFAULT 0,
                `description` TEXT,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Inbound Transactions (Barang Masuk)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `inbound_transactions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `inbound_no` VARCHAR(60) NOT NULL UNIQUE,
                `po_number` VARCHAR(100) NOT NULL,
                `supplier` VARCHAR(150) NOT NULL,
                `material_id` INT NOT NULL,
                `qty` INT NOT NULL,
                `received_by` VARCHAR(100) NOT NULL,
                `notes` TEXT,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (`material_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Outbound Transactions (Barang Keluar Manual)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `outbound_transactions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `outbound_no` VARCHAR(60) NOT NULL UNIQUE,
                `material_id` INT NOT NULL,
                `qty` INT NOT NULL,
                `destination` VARCHAR(150) NOT NULL,
                `issued_by` VARCHAR(100) NOT NULL,
                `reason` VARCHAR(255) NOT NULL,
                `notes` TEXT,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (`material_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Tasks (Assign Task ke Operator untuk Pengambilan Material)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `tasks` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `task_no` VARCHAR(60) NOT NULL UNIQUE,
                `material_id` INT NOT NULL,
                `target_qty` INT NOT NULL,
                `actual_qty` INT DEFAULT 0,
                `priority` ENUM('NORMAL', 'URGENT', 'CRITICAL') DEFAULT 'NORMAL',
                `destination` VARCHAR(150) NOT NULL,
                `assigned_to` INT NOT NULL,
                `assigned_by` INT NOT NULL,
                `status` ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') DEFAULT 'PENDING',
                `notes` TEXT,
                `completion_notes` TEXT,
                `completed_at` DATETIME NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (`assigned_to`),
                INDEX (`material_id`),
                INDEX (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Stock Mutations (Audit Trail & Kartu Stok)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `stock_mutations` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `material_id` INT NOT NULL,
                `type` ENUM('INITIAL_IMPORT', 'INBOUND', 'OUTBOUND', 'TASK_PICKING', 'ADJUSTMENT') NOT NULL,
                `qty_change` INT NOT NULL,
                `stock_before` INT NOT NULL,
                `stock_after` INT NOT NULL,
                `reference_no` VARCHAR(100) NOT NULL,
                `notes` TEXT,
                `user_id` INT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (`material_id`),
                INDEX (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Menu Permissions (Hak Akses Menu per Role / User)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `menu_permissions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `role` VARCHAR(50) NOT NULL,
                `user_id` INT NULL,
                `menu_key` VARCHAR(50) NOT NULL,
                `is_allowed` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uniq_perm` (`role`, `user_id`, `menu_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Stock Opname Sessions (Header)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `stock_opnames` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `opname_no` VARCHAR(60) NOT NULL UNIQUE,
                `title` VARCHAR(255) NOT NULL,
                `counting_type` ENUM('STOCK_OPNAME', 'DYNAMIC_COUNT') NOT NULL DEFAULT 'STOCK_OPNAME',
                `max_stage` INT NOT NULL DEFAULT 1,
                `notes` TEXT,
                `status` ENUM('OPEN', 'COUNTING', 'RECOUNTING', 'COMPLETED', 'CANCELLED') DEFAULT 'OPEN',
                `created_by` INT NOT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (`counting_type`),
                INDEX (`status`),
                INDEX (`created_by`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Stock Opname Items (Master list of materials in an opname session)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `stock_opname_items` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `opname_id` INT NOT NULL,
                `material_id` INT NOT NULL,
                `system_stock` INT NOT NULL DEFAULT 0,
                `final_qty` INT NULL,
                `difference` INT DEFAULT 0,
                `status` ENUM('PENDING', 'COUNTED', 'RECOUNT_REQUESTED', 'MATCH', 'DISCREPANCY') DEFAULT 'PENDING',
                `admin_notes` TEXT,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (`opname_id`),
                INDEX (`material_id`),
                INDEX (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Stock Opname Item Stages (Dynamic Multi-Stage Recounts: 1st, 2nd, 3rd... N-th Count)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `stock_opname_item_stages` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `opname_id` INT NOT NULL,
                `item_id` INT NOT NULL,
                `stage_number` INT NOT NULL DEFAULT 1,
                `assigned_to` INT NOT NULL,
                `count_qty` INT NULL,
                `counted_at` DATETIME NULL,
                `status` ENUM('PENDING', 'COUNTED', 'SKIPPED') DEFAULT 'PENDING',
                `notes` TEXT,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX (`opname_id`),
                INDEX (`item_id`),
                INDEX (`stage_number`),
                INDEX (`assigned_to`),
                INDEX (`status`),
                UNIQUE KEY `uniq_item_stage` (`item_id`, `stage_number`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");

        // Schema migrations for start/submit duration & takt time tracking & multi-stage count
        $migrations = [
            "ALTER TABLE `tasks` ADD COLUMN `started_at` DATETIME NULL",
            "ALTER TABLE `tasks` ADD COLUMN `duration_seconds` INT DEFAULT 0",
            "ALTER TABLE `inbound_transactions` ADD COLUMN `started_at` DATETIME NULL",
            "ALTER TABLE `inbound_transactions` ADD COLUMN `completed_at` DATETIME DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE `inbound_transactions` ADD COLUMN `duration_seconds` INT DEFAULT 0",
            "ALTER TABLE `outbound_transactions` ADD COLUMN `started_at` DATETIME NULL",
            "ALTER TABLE `outbound_transactions` ADD COLUMN `completed_at` DATETIME DEFAULT CURRENT_TIMESTAMP",
            "ALTER TABLE `outbound_transactions` ADD COLUMN `duration_seconds` INT DEFAULT 0",
            "ALTER TABLE `stock_opnames` ADD COLUMN `counting_type` ENUM('STOCK_OPNAME', 'DYNAMIC_COUNT') NOT NULL DEFAULT 'STOCK_OPNAME'",
            "ALTER TABLE `stock_opnames` ADD COLUMN `max_stage` INT NOT NULL DEFAULT 1",
            "ALTER TABLE `stock_opname_item_stages` ADD COLUMN `scanned_rack` VARCHAR(100) NULL"
        ];

        foreach ($migrations as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // Column already exists
            }
        }

        self::seedDefaultData($pdo);
    }

    private static function initSQLiteTables(PDO $pdo): void {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'operator',
                shift TEXT DEFAULT 'Shift A (Pagi)',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS materials (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                code TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                category TEXT DEFAULT 'Karton Box',
                unit TEXT DEFAULT 'Pcs',
                rack_location TEXT DEFAULT 'Gudang Utama',
                min_stock INTEGER DEFAULT 20,
                current_stock INTEGER DEFAULT 0,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS inbound_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                inbound_no TEXT NOT NULL UNIQUE,
                po_number TEXT NOT NULL,
                supplier TEXT NOT NULL,
                material_id INTEGER NOT NULL,
                qty INTEGER NOT NULL,
                received_by TEXT NOT NULL,
                notes TEXT,
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                duration_seconds INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS outbound_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                outbound_no TEXT NOT NULL UNIQUE,
                material_id INTEGER NOT NULL,
                qty INTEGER NOT NULL,
                destination TEXT NOT NULL,
                issued_by TEXT NOT NULL,
                reason TEXT NOT NULL,
                notes TEXT,
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                duration_seconds INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_no TEXT NOT NULL UNIQUE,
                material_id INTEGER NOT NULL,
                target_qty INTEGER NOT NULL,
                actual_qty INTEGER DEFAULT 0,
                priority TEXT DEFAULT 'NORMAL',
                destination TEXT NOT NULL,
                assigned_to INTEGER NOT NULL,
                assigned_by INTEGER NOT NULL,
                status TEXT DEFAULT 'PENDING',
                notes TEXT,
                completion_notes TEXT,
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                duration_seconds INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS stock_mutations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                material_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                qty_change INTEGER NOT NULL,
                stock_before INTEGER NOT NULL,
                stock_after INTEGER NOT NULL,
                reference_no TEXT NOT NULL,
                notes TEXT,
                user_id INTEGER NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS menu_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role TEXT NOT NULL,
                user_id INTEGER NULL,
                menu_key TEXT NOT NULL,
                is_allowed INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE (role, user_id, menu_key)
            );
            CREATE TABLE IF NOT EXISTS stock_opnames (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                opname_no TEXT NOT NULL UNIQUE,
                title TEXT NOT NULL,
                counting_type TEXT DEFAULT 'STOCK_OPNAME',
                max_stage INTEGER DEFAULT 1,
                notes TEXT,
                status TEXT DEFAULT 'OPEN',
                created_by INTEGER NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS stock_opname_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                opname_id INTEGER NOT NULL,
                material_id INTEGER NOT NULL,
                system_stock INTEGER DEFAULT 0,
                final_qty INTEGER NULL,
                difference INTEGER DEFAULT 0,
                status TEXT DEFAULT 'PENDING',
                admin_notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS stock_opname_item_stages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                opname_id INTEGER NOT NULL,
                item_id INTEGER NOT NULL,
                stage_number INTEGER NOT NULL DEFAULT 1,
                assigned_to INTEGER NOT NULL,
                count_qty INTEGER NULL,
                counted_at DATETIME NULL,
                status TEXT DEFAULT 'PENDING',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");

        // Schema migrations for SQLite
        $sqliteMigrations = [
            "ALTER TABLE tasks ADD COLUMN started_at DATETIME NULL",
            "ALTER TABLE tasks ADD COLUMN duration_seconds INTEGER DEFAULT 0",
            "ALTER TABLE inbound_transactions ADD COLUMN started_at DATETIME NULL",
            "ALTER TABLE inbound_transactions ADD COLUMN completed_at DATETIME NULL",
            "ALTER TABLE inbound_transactions ADD COLUMN duration_seconds INTEGER DEFAULT 0",
            "ALTER TABLE outbound_transactions ADD COLUMN started_at DATETIME NULL",
            "ALTER TABLE outbound_transactions ADD COLUMN completed_at DATETIME NULL",
            "ALTER TABLE outbound_transactions ADD COLUMN duration_seconds INTEGER DEFAULT 0",
            "ALTER TABLE stock_opnames ADD COLUMN counting_type TEXT DEFAULT 'STOCK_OPNAME'",
            "ALTER TABLE stock_opnames ADD COLUMN max_stage INTEGER DEFAULT 1",
            "ALTER TABLE stock_opname_item_stages ADD COLUMN scanned_rack TEXT NULL"
        ];

        foreach ($sqliteMigrations as $sql) {
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // Column already exists
            }
        }

        self::seedDefaultData($pdo);
    }

    private static function seedDefaultData(PDO $pdo): void {
        // Ensure Daniel exists with role 'teknisi'
        $stmtDaniel = $pdo->prepare("SELECT id, role FROM users WHERE username = 'Daniel'");
        $stmtDaniel->execute();
        $danielUser = $stmtDaniel->fetch();
        if (!$danielUser) {
            $passDaniel = password_hash('Password01', PASSWORD_BCRYPT);
            $stmtInsert = $pdo->prepare("INSERT INTO users (username, password, name, role, shift) VALUES (?, ?, ?, ?, ?)");
            $stmtInsert->execute(['Daniel', $passDaniel, 'Daniel', 'teknisi', 'Teknisi Utama']);
            $danielId = (int)$pdo->lastInsertId();
        } else {
            $danielId = (int)$danielUser['id'];
            if ($danielUser['role'] !== 'teknisi') {
                $pdo->exec("UPDATE users SET role = 'teknisi' WHERE username = 'Daniel'");
            }
        }

        // Ensure default users exist
        $stmtUserCount = $pdo->query("SELECT COUNT(*) as cnt FROM users");
        if ((int)$stmtUserCount->fetchColumn() <= 1) {
            $passAdmin = password_hash('admin123', PASSWORD_BCRYPT);
            $passOp1   = password_hash('op123', PASSWORD_BCRYPT);
            $passOp2   = password_hash('op123', PASSWORD_BCRYPT);

            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $insertIgnore = ($driver === 'sqlite') ? 'INSERT OR IGNORE' : 'INSERT IGNORE';

            $stmtUser = $pdo->prepare("{$insertIgnore} INTO users (username, password, name, role, shift) VALUES (?, ?, ?, ?, ?)");
            $stmtUser->execute(['admin', $passAdmin, 'Administrator Gudang', 'teknisi', 'Teknisi Gudang']);
            $stmtUser->execute(['operator1', $passOp1, 'Budi Santoso', 'operator', 'Shift 1 (Pagi 07:00 - 15:00)']);
            $stmtUser->execute(['operator2', $passOp2, 'Agus Pratama', 'operator', 'Shift 2 (Siang 15:00 - 23:00)']);
        }

        // Seed default menu permissions
        $defaultMenus = [
            'dashboard'   => ['superadmin' => 1, 'admin' => 1, 'teknisi' => 1, 'operator' => 0],
            'inventory'   => ['superadmin' => 1, 'admin' => 1, 'teknisi' => 1, 'operator' => 0],
            'opname'      => ['superadmin' => 1, 'admin' => 1, 'teknisi' => 1, 'operator' => 0],
            'inbound'     => ['superadmin' => 1, 'admin' => 1, 'teknisi' => 1, 'operator' => 0],
            'outbound'    => ['superadmin' => 1, 'admin' => 1, 'teknisi' => 1, 'operator' => 0],
            'tasks'       => ['superadmin' => 1, 'admin' => 1, 'teknisi' => 1, 'operator' => 0],
            'mutations'   => ['superadmin' => 1, 'admin' => 0, 'teknisi' => 1, 'operator' => 0],
            'users'       => ['superadmin' => 1, 'admin' => 1, 'teknisi' => 1, 'operator' => 0],
            'permissions' => ['superadmin' => 1, 'admin' => 1, 'teknisi' => 1, 'operator' => 0],
            'field_access'=> ['superadmin' => 1, 'admin' => 0, 'teknisi' => 1, 'operator' => 1],
        ];

        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmtInsertPerm = $pdo->prepare("
                INSERT INTO menu_permissions (role, user_id, menu_key, is_allowed)
                VALUES (?, NULL, ?, ?)
                ON CONFLICT(role, user_id, menu_key) DO UPDATE SET is_allowed = excluded.is_allowed
            ");
        } else {
            $stmtInsertPerm = $pdo->prepare("
                INSERT INTO menu_permissions (role, user_id, menu_key, is_allowed)
                VALUES (?, NULL, ?, ?)
                ON DUPLICATE KEY UPDATE is_allowed = is_allowed
            ");
        }
        foreach ($defaultMenus as $menuKey => $roles) {
            foreach ($roles as $role => $allowed) {
                try {
                    $stmtInsertPerm->execute([$role, $menuKey, $allowed]);
                } catch (Throwable $e) {
                    // Ignored
                }
            }
        }
    }
}

