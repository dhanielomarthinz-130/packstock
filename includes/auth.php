<?php
// includes/auth.php - Session Management & Role-Based Access Control

if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    @ini_set('display_errors', '1');
    @ini_set('display_startup_errors', '1');
    @error_reporting(E_ALL);
}

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/html; charset=utf-8');
        }
        $msg = htmlspecialchars($error['message'] ?? 'Fatal Error', ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($error['file'] ?? '', ENT_QUOTES, 'UTF-8');
        $line = (int)($error['line'] ?? 0);
        echo "<!DOCTYPE html>
<html lang='id'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width, initial-scale=1'>
  <title>System Error - PackStock WMS</title>
  <style>
    body { font-family: system-ui, -apple-system, sans-serif; background: #0f172a; color: #f8fafc; padding: 2rem; display: flex; justify-content: center; align-items: center; min-height: 80vh; margin: 0; }
    .card { background: #1e293b; border: 1px solid #ef4444; border-radius: 1rem; padding: 2rem; max-width: 650px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); }
    h2 { color: #f87171; margin-top: 0; font-size: 1.25rem; }
    p { color: #94a3b8; font-size: 0.9rem; line-height: 1.5; }
    pre { background: #0f172a; padding: 1rem; border-radius: 0.5rem; color: #fca5a5; font-size: 0.8rem; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
    .btn { display: inline-block; background: #3b82f6; color: #fff; padding: 0.6rem 1.2rem; border-radius: 0.5rem; text-decoration: none; font-weight: 600; font-size: 0.875rem; margin-top: 1rem; }
  </style>
</head>
<body>
  <div class='card'>
    <h2>⚠️ Terjadi Kendala Eksekusi Sistem</h2>
    <p>Sistem mendeteksi galat fatal pada runtime PHP:</p>
    <pre>{$msg}\n\nFile: {$file}\nBaris: {$line}</pre>
    <a href='index.php' class='btn'>Kembali ke Dashboard</a>
  </div>
</body>
</html>";
    }
});

if (session_status() === PHP_SESSION_NONE) {
    // Pengerasan cookie sesi. HttpOnly menutup pencurian sesi lewat XSS,
    // SameSite=Lax menutup CSRF lintas situs, dan Secure hanya dipasang saat HTTPS
    // aktif agar pengembangan lokal di http://localhost tetap bisa login.
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['HTTP_CF_VISITOR'] ?? '') !== '' && strpos($_SERVER['HTTP_CF_VISITOR'], '"https"') !== false)
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;

    if (!headers_sent()) {
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_start();
}

// Prevent aggressive browser/proxy caching for dynamic pages and API endpoints
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Header keamanan dasar (F-23)
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content-Security-Policy.
    //
    // 'unsafe-inline' terpaksa diizinkan karena halaman memakai banyak atribut
    // onclick; menghapusnya memerlukan penulisan ulang front-end. 'unsafe-eval'
    // sudah tidak diperlukan lagi setelah Tailwind Play CDN diganti CSS terkompilasi.
    // Meski begitu daftar putih ini tetap berguna: skrip dari domain di luar daftar
    // tidak akan dimuat, dan data tidak bisa dikirim ke endpoint sembarangan.
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; " .
        "font-src 'self' data: https://fonts.gstatic.com; " .
        "img-src 'self' data: blob:; " .
        "connect-src 'self' https://script.google.com https://script.googleusercontent.com; " .
        "form-action 'self'; " .
        "base-uri 'self'; " .
        "object-src 'none'; " .
        "frame-ancestors 'self'"
    );
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
        if (!self::check()) {
            return false;
        }

        $user = self::user();
        $rawRole = (string)($user['role'] ?? '');
        $roleNorm = str_replace([' ', '_', '-'], '', strtolower(trim($rawRole)));

        // 1. Cek variasi penulisan role superadmin / teknisi / master
        if (in_array($roleNorm, ['superadmin', 'teknisi', 'superadministrator', 'master'], true)) {
            return true;
        }

        // 2. Cek username khusus: daniel, superadmin, admin
        $username = strtolower(trim((string)($user['username'] ?? '')));
        if (in_array($username, ['daniel', 'superadmin', 'admin'], true)) {
            return true;
        }

        // 3. Cek langsung ke basis data (jika role baru saja diubah di DB namun sesi masih menyimpan role lama)
        $userId = self::id();
        if ($userId) {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare("SELECT role, username FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $dbUser = $stmt->fetch();
                if ($dbUser) {
                    $dbRoleNorm = str_replace([' ', '_', '-'], '', strtolower(trim((string)($dbUser['role'] ?? ''))));
                    $dbUname = strtolower(trim((string)($dbUser['username'] ?? '')));

                    if (in_array($dbRoleNorm, ['superadmin', 'teknisi', 'superadministrator', 'master'], true) ||
                        in_array($dbUname, ['daniel', 'superadmin', 'admin'], true)) {
                        // Sinkronkan kembali sesi aktif
                        $_SESSION['user']['role'] = $dbUser['role'];
                        $_SESSION['user']['username'] = $dbUser['username'];
                        return true;
                    }
                }
            } catch (Throwable $e) {}
        }

        return false;
    }

    public static function isViewer(): bool {
        return self::role() === 'viewer';
    }

    public static function isAdmin(): bool {
        return self::role() === 'teknisi' || self::role() === 'admin' || self::role() === 'superadmin' || self::role() === 'viewer';
    }

    public static function isOperator(): bool {
        return self::role() === 'operator_inventory' || self::role() === 'operator';
    }

    public static function isOperatorInventory(): bool {
        return self::role() === 'operator_inventory' || self::role() === 'operator';
    }

    public static function isOperatorFulfillment(): bool {
        return self::role() === 'operator_fulfillment';
    }

    public static function isOperatorAny(): bool {
        return self::isOperator() || self::isOperatorFulfillment();
    }

    public static function isMaintenanceMode(): bool {
        $flagFile = __DIR__ . '/../config/maintenance.flag';
        return file_exists($flagFile);
    }

    // =====================================================================
    // CSRF PROTECTION
    // =====================================================================

    /** Token per sesi. Dibuat sekali, dipakai ulang selama sesi hidup. */
    public static function csrfToken(): string {
        if (empty($_SESSION['csrf_token'])) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                return $_SESSION['csrf_token'] ?? '';
            }
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /** Ambil token yang dikirim klien, dari header maupun body. */
    private static function submittedCsrfToken(): string {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($token === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if (strcasecmp($k, 'X-CSRF-Token') === 0) {
                    $token = $v;
                    break;
                }
            }
        }
        if ($token !== '') return trim($token);

        if (!empty($_POST['csrf_token'])) return trim((string)$_POST['csrf_token']);
        if (!empty($_GET['csrf_token'])) return trim((string)$_GET['csrf_token']);

        // Body JSON: baca tanpa merusak pembacaan ulang oleh endpoint (php://input dapat dibaca berkali-kali).
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json) && !empty($json['csrf_token'])) {
                return trim((string)$json['csrf_token']);
            }
        }
        return '';
    }

    /**
     * Tolak permintaan pengubah data yang tidak membawa token sesi yang sah.
     * Permintaan GET/HEAD/OPTIONS dilewati karena tidak mengubah keadaan.
     */
    public static function verifyCsrfOrFail(): void {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $expected = $_SESSION['csrf_token'] ?? '';
        $given    = self::submittedCsrfToken();

        if ($expected !== '' && $given !== '' && hash_equals($expected, $given)) {
            return;
        }

        http_response_code(403);
        if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success'      => false,
            'csrf_expired' => true,
            'message'      => 'Permintaan ditolak karena token keamanan halaman sudah kedaluwarsa. Muat ulang halaman, lalu ulangi tindakan Anda.'
        ]);
        exit;
    }

    /**
     * Kebijakan Global Read-Only:
     * Web ini hanya bisa dilihat saja (read-only), tidak bisa melakukan transaksi,
     * penambahan, pengubahan, atau penghapusan data untuk semua user kecuali role superadmin.
     */
    public static function verifyReadOnlyPolicyOrFail(): void {
        // Super Admin memiliki akses penuh untuk seluruh transaksi & perubahan data
        if (self::isSuperAdmin()) {
            return;
        }

        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $safeMethods = ['GET', 'HEAD', 'OPTIONS'];

        $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $action = strtolower((string)($_GET['action'] ?? ($_POST['action'] ?? '')));

        // Izinkan proses logout (baik via GET atau POST)
        if ($script === 'logout.php' || $action === 'logout') {
            return;
        }

        // Daftar aksi pengubah data yang mungkin dikirim via parameter query GET
        $writeActions = [
            'delete', 'delete_batch', 'clean_table', 'clean_type_bulk',
            'clean_all_transactions', 'factory_reset', 'toggle_maintenance', 'cancel'
        ];

        $isWriteRequest = !in_array($method, $safeMethods, true) || in_array($action, $writeActions, true);

        if ($isWriteRequest) {
            http_response_code(403);
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success'   => false,
                'read_only' => true,
                'message'   => 'Sistem dalam mode Lihat Saja (Read-Only). Transaksi, penambahan, pengubahan, atau penghapusan data hanya dapat dilakukan oleh Super Admin.'
            ]);
            exit;
        }
    }

    // =====================================================================
    // AUDIT TRAIL & LOGIN THROTTLING
    // =====================================================================

    public static function clientIp(): string {
        return substr((string)($_SERVER['REMOTE_ADDR'] ?? '-'), 0, 45);
    }

    /**
     * Catat tindakan sistem (login, ubah role, hak menu, maintenance, reset).
     * Sengaja tidak pernah melempar exception: kegagalan mencatat audit
     * tidak boleh menggagalkan tindakan yang sedang dikerjakan pengguna.
     */
    public static function audit(string $action, ?string $target = null, ?string $detail = null, ?array $actor = null): void {
        try {
            $pdo = Database::getConnection();
            $actorId   = $actor['id'] ?? self::id();
            $actorName = $actor['username'] ?? self::username();

            $stmt = $pdo->prepare("
                INSERT INTO system_audit_logs (actor_id, actor_username, action, target, detail, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $actorId ?: null,
                $actorName,
                $action,
                $target,
                $detail,
                self::clientIp(),
                date('Y-m-d H:i:s')
            ]);
        } catch (Throwable $e) {
            error_log('[PackStock] Gagal menulis audit log (' . $action . '): ' . $e->getMessage());
        }
    }

    private const LOGIN_MAX_ATTEMPTS    = 5;   // per nama pengguna
    private const LOGIN_MAX_ATTEMPTS_IP = 30;  // per IP, jauh lebih longgar
    private const LOGIN_LOCK_SECONDS    = 900; // 15 menit

    /**
     * Jumlah detik penguncian tersisa, atau 0 bila tidak terkunci.
     *
     * Ambang per nama pengguna dibuat ketat, sedangkan ambang per IP sengaja
     * jauh lebih longgar: seluruh operator gudang umumnya keluar lewat satu IP
     * NAT yang sama, sehingga penguncian per IP yang ketat akan mengunci satu
     * gudang penuh hanya karena satu orang salah ketik berulang kali.
     */
    private static function loginLockRemaining(PDO $pdo, string $username): int {
        try {
            $since = date('Y-m-d H:i:s', time() - self::LOGIN_LOCK_SECONDS);

            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS gagal, MAX(attempted_at) AS terakhir
                FROM login_attempts
                WHERE success = 0 AND attempted_at > ? AND LOWER(username) = LOWER(?)
            ");
            $stmt->execute([$since, $username]);
            $perUser = $stmt->fetch();

            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS gagal, MAX(attempted_at) AS terakhir
                FROM login_attempts
                WHERE success = 0 AND attempted_at > ? AND ip_address = ?
            ");
            $stmt->execute([$since, self::clientIp()]);
            $perIp = $stmt->fetch();

            $terkunci = null;
            if ($perUser && (int)$perUser['gagal'] >= self::LOGIN_MAX_ATTEMPTS) {
                $terkunci = $perUser['terakhir'];
            } elseif ($perIp && (int)$perIp['gagal'] >= self::LOGIN_MAX_ATTEMPTS_IP) {
                $terkunci = $perIp['terakhir'];
            }

            if ($terkunci === null) return 0;

            $sisa = self::LOGIN_LOCK_SECONDS - (time() - strtotime((string)$terkunci));
            return $sisa > 0 ? $sisa : 0;
        } catch (Throwable $e) {
            return 0; // tabel belum ada / DB bermasalah: jangan kunci pengguna sah
        }
    }

    private static function recordLoginAttempt(PDO $pdo, string $username, bool $success): void {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO login_attempts (username, ip_address, success, attempted_at)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([substr($username, 0, 100), self::clientIp(), $success ? 1 : 0, date('Y-m-d H:i:s')]);

            if ($success) {
                // Hanya bersihkan kegagalan milik nama pengguna ini.
                // Membersihkan berdasarkan IP juga akan menghapus hitungan kegagalan
                // seluruh akun lain di gudang yang sama, sehingga pembatasan bisa
                // dilewati hanya dengan login memakai satu akun yang sah.
                $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE success = 0 AND LOWER(username) = LOWER(?)");
                $stmt->execute([$username]);
            }

            // Buang catatan lama agar tabel tidak tumbuh tanpa batas.
            $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < ?")
                ->execute([date('Y-m-d H:i:s', time() - 7 * 86400)]);
        } catch (Throwable $e) {
            error_log('[PackStock] Gagal mencatat percobaan login: ' . $e->getMessage());
        }
    }

    public static function redirect(string $url): void {
        if (!headers_sent()) {
            header("Location: {$url}");
        }
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        $jsonUrl = json_encode($url);
        echo "<!DOCTYPE html><html><head><meta charset='utf-8'><meta http-equiv='refresh' content='0;url={$safeUrl}'><script>window.location.replace({$jsonUrl});</script></head><body>Mengalihkan ke <a href='{$safeUrl}'>{$safeUrl}</a>...</body></html>";
        exit;
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
            self::redirect("{$base}/maintenance");
        }

        if (!self::check()) {
            if (self::isAjax()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Sesi login telah berakhir. Silakan login kembali.']);
                exit;
            }
            $base = self::getBaseUrl();
            self::redirect("{$base}/login");
        }

        // Inactivity timeout: 1 hour (3600 seconds)
        $maxIdleSeconds = 3600;
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $maxIdleSeconds)) {
            self::logout();
            if (self::isAjax()) {
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Sesi Anda telah berakhir karena tidak ada aktivitas selama 1 jam.', 'timeout' => true]);
                exit;
            }
            $base = self::getBaseUrl();
            self::redirect("{$base}/login?timeout=1");
        }

        // Verifikasi CSRF untuk seluruh permintaan yang mengubah data.
        // Ditempatkan di sini agar satu titik ini melindungi setiap endpoint yang
        // memanggil requireLogin/requireAdmin/requireSuperAdmin — tanpa perlu
        // menyentuh 154 pemanggilan fetch di sisi front-end satu per satu.
        self::verifyCsrfOrFail();

        // Kebijakan Global: Sistem Read-Only (Hanya Lihat) untuk seluruh user kecuali Super Admin.
        // Transaksi, penambahan, pengubahan, atau penghapusan data diblokir untuk non-superadmin.
        self::verifyReadOnlyPolicyOrFail();

        // Update last activity timestamp on active requests (both regular navigation & AJAX)
        $_SESSION['last_activity'] = time();

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
            self::redirect("{$base}/operator/");
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
            self::redirect("{$base}/admin/");
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

        // Kunci sementara setelah beberapa kegagalan beruntun (per username maupun per IP).
        $lockRemaining = self::loginLockRemaining($pdo, $trimmedUser);
        if ($lockRemaining > 0) {
            $menit = max(1, (int)ceil($lockRemaining / 60));
            self::audit('LOGIN_BLOCKED', $trimmedUser, "Terkunci, sisa {$menit} menit");
            return [
                'success' => false,
                'locked'  => true,
                'message' => "Terlalu banyak percobaan login yang gagal. Coba lagi dalam {$menit} menit, atau hubungi Administrator untuk mengatur ulang password Anda."
            ];
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1");
        $stmt->execute([$trimmedUser]);
        $user = $stmt->fetch();

        // Pesan yang sama untuk username tidak dikenal maupun password salah,
        // supaya daftar nama pengguna yang sah tidak bisa dipetakan dari luar.
        $pesanGagal = 'Username atau password yang Anda masukkan salah.';

        if (!$user) {
            self::recordLoginAttempt($pdo, $trimmedUser, false);
            // Percobaan dengan nama pengguna yang tidak ada adalah sinyal pemindaian,
            // jadi tetap dicatat meskipun akunnya tidak pernah ada.
            self::audit('LOGIN_FAILED', $trimmedUser, 'Username tidak dikenal', ['id' => null, 'username' => $trimmedUser]);
            return ['success' => false, 'message' => $pesanGagal];
        }

        // Blokir akun yang dinonaktifkan oleh Administrator
        if (isset($user['is_active']) && (int)$user['is_active'] === 0) {
            self::recordLoginAttempt($pdo, $trimmedUser, false);
            self::audit('LOGIN_BLOCKED', $trimmedUser, 'Percobaan login ke akun nonaktif', ['id' => $user['id'], 'username' => $trimmedUser]);
            return ['success' => false, 'message' => 'Akun Anda sedang dinonaktifkan oleh Administrator. Silakan hubungi admin gudang.'];
        }

        // Password harus cocok persis. Varian huruf besar/kecil TIDAK diterima:
        // fallback strtolower()/ucfirst() memperbesar ruang tebakan penyerang tanpa alasan sah.
        $isMatch = password_verify($trimmedPass, $user['password']);

        if ($isMatch) {
            self::recordLoginAttempt($pdo, $trimmedUser, true);

            // Cegah session fixation: ganti ID sesi begitu kredensial terbukti benar.
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            // Token CSRF baru mengikuti sesi yang baru.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            // Hitung shift otomatis berdasarkan jam kerja sistem (WIB)
            // Shift 1: 06:00 - 16:00 WIB
            // Shift 2: 16:00 - 06:00 WIB (16:00 - 00:00)
            $currentHour = (int)date('G');
            $autoShift = ($currentHour >= 6 && $currentHour < 16) 
                ? 'Shift 1 (Pagi 06:00 - 16:00)' 
                : 'Shift 2 (Siang 16:00 - 00:00)';

            // Untuk role operator, shift WAJIB otomatis mengikuti jam kerja dan tidak bisa diubah
            $isOperatorRole = str_starts_with($user['role'] ?? '', 'operator');
            $userShift = $isOperatorRole ? $autoShift : (!empty($shift) ? $shift : ($user['shift'] ?? $autoShift));
            
            // Perbarui DB jika shift berbeda
            if ($userShift !== ($user['shift'] ?? '')) {
                $stmtUpdateShift = $pdo->prepare("UPDATE users SET shift = ? WHERE id = ?");
                $stmtUpdateShift->execute([$userShift, $user['id']]);
            }

            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'role' => $user['role'],
                'shift' => $userShift
            ];
            $_SESSION['last_activity'] = time();

            $isAdminRole = ($user['role'] === 'teknisi' || $user['role'] === 'admin' || $user['role'] === 'superadmin');

            self::audit('LOGIN_SUCCESS', $user['username'], "Role {$user['role']}, shift {$userShift}");

            return [
                'success' => true,
                'user' => $_SESSION['user'],
                'csrf_token' => $_SESSION['csrf_token'],
                'redirect' => $isAdminRole ? 'admin/' : 'operator/'
            ];
        }

        self::recordLoginAttempt($pdo, $trimmedUser, false);
        self::audit('LOGIN_FAILED', $trimmedUser, 'Password tidak cocok', ['id' => null, 'username' => $trimmedUser]);

        return ['success' => false, 'message' => $pesanGagal];
    }

    public static function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (self::check()) {
            self::audit('LOGOUT', self::username());
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
        $isHttps = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['HTTP_CF_VISITOR'] ?? '') !== '' && strpos($_SERVER['HTTP_CF_VISITOR'], '"https"') !== false)
            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $protocol = $isHttps ? "https" : "http";
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

/**
 * Syarat minimum password baru.
 *
 * Sengaja hanya diberlakukan saat password DIBUAT atau DIGANTI, bukan saat login,
 * supaya pengguna lama tidak tiba-tiba terkunci di tengah shift. Ambangnya dijaga
 * tetap wajar untuk lingkungan gudang: cukup panjang untuk menahan tebakan, tanpa
 * memaksa simbol yang sulit diketik di keypad perangkat genggam.
 *
 * @return string|null Pesan galat, atau null bila password memenuhi syarat.
 */
if (!function_exists('validatePasswordStrength')) {
    function validatePasswordStrength(string $password, string $username = ''): ?string {
        if (strlen($password) < 8) {
            return 'Password baru minimal 8 karakter.';
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return 'Password baru harus memuat huruf dan angka.';
        }

        $lemah = ['password', 'admin123', 'operator', '12345678', 'qwerty123', 'packstock', 'password1'];
        if (in_array(strtolower($password), $lemah, true)) {
            return 'Password tersebut terlalu umum dan mudah ditebak. Gunakan kombinasi lain.';
        }
        if ($username !== '' && stripos($password, $username) !== false) {
            return 'Password tidak boleh memuat nama pengguna Anda.';
        }
        return null;
    }
}

/**
 * Batas dan pemeriksaan berkas foto bukti yang diunggah operator.
 */
if (!defined('PACKSTOCK_MAX_PHOTO_BYTES')) {
    define('PACKSTOCK_MAX_PHOTO_BYTES', 8 * 1024 * 1024); // 8 MB per foto
}

/**
 * Pastikan berkas yang diunggah benar-benar gambar, bukan sekadar berekstensi gambar.
 *
 * Ekstensi saja tidak membuktikan apa pun tentang isi berkas, dan tanpa batas ukuran
 * satu unggahan besar dapat menghabiskan kuota penyimpanan hosting.
 *
 * @return string|null Nama ekstensi yang sah, atau null bila berkas ditolak.
 */
if (!function_exists('validateUploadedPhoto')) {
    function validateUploadedPhoto(string $tmpPath, string $originalName, int $size): ?string {
        if ($size <= 0 || $size > PACKSTOCK_MAX_PHOTO_BYTES) {
            return null;
        }

        $allowed = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_WEBP => 'webp',
        ];

        $info = @getimagesize($tmpPath);
        if ($info !== false && isset($allowed[$info[2]])) {
            return $allowed[$info[2]];
        }

        // Deteksi langsung melalui byte signature (magic bytes) jika getimagesize gagal pada format WebP
        $head = @file_get_contents($tmpPath, false, null, 0, 16);
        if ($head !== false && strlen($head) >= 12) {
            if (substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP') {
                return 'webp';
            }
            if (substr($head, 0, 3) === "\xFF\xD8\xFF") {
                return 'jpg';
            }
            if (substr($head, 0, 4) === "\x89PNG") {
                return 'png';
            }
        }

        return null; // bukan gambar sungguhan
    }
}

/**
 * Klausa penguncian baris untuk pembaruan stok.
 *
 * Seluruh alur stok memakai pola baca-ubah-tulis. Tanpa penguncian baris, dua
 * transaksi bersamaan pada SKU yang sama membaca nilai awal yang sama lalu saling
 * menimpa (lost update) — satu pergerakan hilang tanpa jejak. SQLite tidak
 * memerlukannya karena menyerialkan penulisan, tetapi MySQL/InnoDB di produksi
 * memerlukannya secara eksplisit.
 *
 * Gunakan HANYA di dalam transaksi: SELECT ... WHERE id = ? <clause>
 */
if (!function_exists('rowLockClause')) {
    function rowLockClause(PDO $pdo): string {
        return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
    }
}

/**
 * Balas kegagalan tak terduga tanpa membocorkan isi perut sistem.
 *
 * Pesan Exception mentah membocorkan nama tabel, potongan kueri, dan jalur berkas
 * ke siapa pun yang memicunya. Detail lengkap ditulis ke error log server beserta
 * kode rujukan singkat, sehingga tim teknis tetap dapat menelusuri satu kejadian
 * spesifik dari laporan pengguna tanpa detail itu pernah keluar ke browser.
 */
if (!function_exists('apiFail')) {
    function apiFail(Throwable $e, string $userMessage = 'Permintaan gagal diproses.'): void {
        $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        error_log(sprintf(
            '[PackStock][%s] %s | %s:%d | user=%s | uri=%s | %s',
            $ref,
            get_class($e),
            $e->getFile(),
            $e->getLine(),
            Auth::username() ?? '-',
            $_SERVER['REQUEST_URI'] ?? '-',
            $e->getMessage()
        ));

        if (!headers_sent()) {
            if (http_response_code() < 400) {
                http_response_code(500);
            }
            header('Content-Type: application/json; charset=utf-8');
        }

        echo json_encode([
            'success'   => false,
            'reference' => $ref,
            'message'   => rtrim($userMessage, ': ') . " Silakan coba lagi. Bila tetap gagal, sampaikan kode {$ref} ke tim teknis."
        ]);
    }
}

/**
 * Batas atas wajar untuk satu transaksi pergerakan stok.
 *
 * Tanpa batas ini, satu kesalahan ketik (mis. menahan tombol angka, atau nilai
 * bernotasi ilmiah seperti 1e20) langsung diterima dan merusak stok secara permanen:
 * begitu nilainya sangat besar, penambahan kecil berikutnya hilang karena presisi float.
 */
if (!defined('PACKSTOCK_MAX_QTY')) {
    define('PACKSTOCK_MAX_QTY', 1000000);
}

/**
 * Validasi qty transaksi. Mengembalikan pesan galat, atau null bila nilainya wajar.
 */
if (!function_exists('validateQtyRange')) {
    function validateQtyRange(float $qty, string $label = 'Jumlah'): ?string {
        if (!is_finite($qty)) {
            return "{$label} bukan angka yang sah. Periksa kembali isian Anda.";
        }
        if ($qty > PACKSTOCK_MAX_QTY) {
            return "{$label} yang dimasukkan (" . number_format($qty, 0, ',', '.') . ") melebihi batas wajar "
                 . number_format(PACKSTOCK_MAX_QTY, 0, ',', '.') . " per transaksi. "
                 . "Periksa kembali angkanya, atau pecah menjadi beberapa transaksi.";
        }
        return null;
    }
}

/**
 * Helper to check if a packaging material SKU is locked/frozen in an active Dynamic Count session.
 * Returns associative array with session metadata if frozen, or null if free to mutate.
 */
if (!function_exists('getMaterialDynamicCountFreeze')) {
    function getMaterialDynamicCountFreeze(PDO $pdo, int $materialId): ?array {
        if ($materialId <= 0) return null;
        try {
            $stmt = $pdo->prepare("
                SELECT so.id as opname_id, 
                       so.opname_no, 
                       so.title as session_title, 
                       so.status as session_status,
                       m.id as material_id,
                       m.name as material_name, 
                       m.code as material_code
                FROM stock_opname_items soi
                JOIN stock_opnames so ON soi.opname_id = so.id
                JOIN materials m ON soi.material_id = m.id
                WHERE soi.material_id = ? 
                  AND so.counting_type = 'DYNAMIC_COUNT' 
                  AND so.status IN ('OPEN', 'COUNTING', 'RECOUNTING')
                LIMIT 1
            ");
            $stmt->execute([$materialId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }
}

