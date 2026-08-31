<?php
// includes/auth.php - Session Management & Role-Based Access Control

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

class Auth {
    public static function check(): bool {
        return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
    }

    public static function user(): ?array {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function role(): ?string {
        return $_SESSION['user']['role'] ?? null;
    }

    public static function username(): ?string {
        return $_SESSION['user']['username'] ?? null;
    }

    public static function name(): ?string {
        return $_SESSION['user']['name'] ?? null;
    }

    public static function isSuperAdmin(): bool {
        return self::role() === 'teknisi' || self::role() === 'superadmin' || self::username() === 'Daniel';
    }

    public static function isAdmin(): bool {
        return self::role() === 'teknisi' || self::role() === 'admin' || self::role() === 'superadmin' || self::username() === 'Daniel';
    }

    public static function isOperator(): bool {
        return self::role() === 'operator';
    }

    public static function isOperatorFulfillment(): bool {
        return self::role() === 'operator_fulfillment';
    }

    public static function isOperatorAny(): bool {
        return self::role() === 'operator' || self::role() === 'operator_fulfillment';
    }

    public static function isMaintenanceMode(): bool {
        $flagFile = __DIR__ . '/../config/maintenance.flag';
        return file_exists($flagFile);
    }

    public static function requireLogin(): void {
        // Redirect to maintenance page if active and user is not superadmin
        if (self::isMaintenanceMode() && !self::isSuperAdmin()) {
            if (self::isAjax()) {
                http_response_code(503);
                echo json_encode(['success' => false, 'message' => 'Sistem sedang dalam pemeliharaan (Maintenance Mode). Hanya Super Admin yang dapat mengakses.']);
                exit;
            }
            $base = self::getBaseUrl();
            header("Location: {$base}/maintenance");
            exit;
        }

        if (!self::check()) {
            if (self::isAjax()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.']);
                exit;
            }
            $base = self::getBaseUrl();
            header("Location: {$base}/login");
            exit;
        }

        // Release session lock immediately for non-blocking concurrent parallel AJAX requests
        if (self::isAjax() && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdmin()) {
            if (self::isAjax()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses ditolak. Halaman ini khusus Administrator & Super Admin.']);
                exit;
            }
            $base = self::getBaseUrl();
            header("Location: {$base}/operator/");
            exit;
        }
    }

    public static function requireSuperAdmin(): void {
        self::requireLogin();
        if (!self::isSuperAdmin()) {
            if (self::isAjax()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Akses ditolak. Fitur ini khusus Super Admin.']);
                exit;
            }
            $base = self::getBaseUrl();
            header("Location: {$base}/admin/");
            exit;
        }
    }

    public static function requireOperator(): void {
        self::requireLogin();
        // Operators are allowed, and Admin/Super Admin can also view operator mobile interface for testing/dispatching
    }

    public static function login(string $username, string $password, string $shift = ''): array {
        $pdo = Database::getConnection();
        $trimmedUser = trim($username);
        $trimmedPass = trim($password);

        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
        $stmt->execute([$trimmedUser]);
        $user = $stmt->fetch();

        if (!$user) {
            return ['success' => false, 'message' => 'Username tidak terdaftar!'];
        }

        $isMatch = false;
        if (password_verify($trimmedPass, $user['password'])) {
            $isMatch = true;
        } elseif (password_verify(strtolower($trimmedPass), $user['password'])) {
            $isMatch = true;
        } elseif (password_verify(ucfirst($trimmedPass), $user['password'])) {
            $isMatch = true;
        }

        if ($isMatch) {
            $userShift = !empty($shift) ? $shift : ($user['shift'] ?? 'Shift 1 (Pagi 08:00 - 16:00)');
            
            // If shift was chosen and differs from DB, update DB
            if (!empty($shift) && $shift !== ($user['shift'] ?? '')) {
                $stmtUpdateShift = $pdo->prepare("UPDATE users SET shift = ? WHERE id = ?");
                $stmtUpdateShift->execute([$shift, $user['id']]);
            }

            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['role'],
                'shift' => $userShift
            ];

            $isAdminRole = ($user['role'] === 'teknisi' || $user['role'] === 'admin' || $user['role'] === 'superadmin' || strtolower($user['username']) === 'daniel');

            return [
                'success' => true,
                'user' => $_SESSION['user'],
                'redirect' => $isAdminRole ? 'admin/' : 'operator/'
            ];
        }

        return ['success' => false, 'message' => 'Password yang dimasukkan salah!'];
    }

    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function isAjax(): bool {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') ||
               (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) ||
               (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) ||
               (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false);
    }

    public static function getBaseUrl(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Find relative path to project root
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $pos = strpos($scriptPath, '/packstock');
        if ($pos !== false) {
            return $protocol . '://' . $host . '/packstock';
        }
        return '';
    }
}

/**
 * Universal decimal numeric parser supporting both standard (84.2) and Indonesian/European (84,2 or 90,01) formats.
 */
if (!function_exists('parseNumberDecimal')) {
    function parseNumberDecimal($val): float {
        if (is_float($val) || is_int($val)) return (float)$val;
        $val = trim((string)$val);
        if ($val === '') return 0.0;

        $val = preg_replace('/[^\d.,\-+]/u', '', $val);

        if (strpos($val, ',') !== false && strpos($val, '.') !== false) {
            $lastComma = strrpos($val, ',');
            $lastDot   = strrpos($val, '.');
            if ($lastComma > $lastDot) {
                $val = str_replace('.', '', $val);
                $val = str_replace(',', '.', $val);
            } else {
                $val = str_replace(',', '', $val);
            }
        } elseif (strpos($val, ',') !== false) {
            $val = str_replace(',', '.', $val);
        } elseif (strpos($val, '.') !== false) {
            $parts = explode('.', $val);
            if (count($parts) > 2) {
                $val = str_replace('.', '', $val);
            }
        }

        $clean = preg_replace('/[^0-9.\-+]/', '', $val);
        return is_numeric($clean) ? (float)$clean : 0.0;
    }
}
