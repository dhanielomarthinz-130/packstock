<?php
/**
 * scripts/smoke_test.php — Uji asap pasca-deploy PackStock WMS
 *
 * Menjalankan pemeriksaan cepat terhadap instalasi yang SEDANG BERJALAN:
 * halaman utama hidup, otorisasi masih menutup pintu yang benar, berkas rahasia
 * tidak bocor, dan buku mutasi masih sinkron dengan stok master.
 *
 * Uji ini hanya MEMBACA. Tidak ada satu pun transaksi atau data yang dibuat,
 * diubah, maupun dihapus — aman dijalankan langsung di produksi.
 *
 * Cara pakai:
 *   php scripts/smoke_test.php https://packstock.rf.gd  admin  <password>
 *   php scripts/smoke_test.php http://localhost/packstock admin admin123
 *
 * Kode keluar: 0 bila semua lulus, 1 bila ada yang gagal (cocok untuk CI).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya untuk dijalankan lewat command line.');
}

$baseUrl  = rtrim($argv[1] ?? 'http://localhost/packstock', '/');
$username = $argv[2] ?? '';
$password = $argv[3] ?? '';

$cookieJar = tempnam(sys_get_temp_dir(), 'packstock_smoke_');
$lulus = 0;
$gagal = 0;
$catatan = [];

function warna(string $t, string $c): string {
    $kode = ['hijau' => "\033[32m", 'merah' => "\033[31m", 'kuning' => "\033[33m", 'abu' => "\033[90m"];
    return ($kode[$c] ?? '') . $t . "\033[0m";
}

function hasil(string $nama, bool $ok, string $detail = ''): void {
    global $lulus, $gagal, $catatan;
    if ($ok) { $lulus++; echo '  ' . warna('LULUS', 'hijau'); }
    else     { $gagal++; echo '  ' . warna('GAGAL', 'merah'); $catatan[] = $nama . ($detail ? " — {$detail}" : ''); }
    echo '  ' . str_pad($nama, 52) . ($detail ? warna($detail, 'abu') : '') . PHP_EOL;
}

/** @return array{status:int, body:string, headers:string} */
function minta(string $url, array $opt = []): array {
    global $cookieJar;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => $opt['follow'] ?? false,
        CURLOPT_COOKIEJAR      => $cookieJar,
        CURLOPT_COOKIEFILE     => $cookieJar,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if (!empty($opt['post'])) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['post']);
    }
    $headers = ['Accept: application/json'];
    if (!empty($opt['json']))  $headers[] = 'Content-Type: application/json';
    if (!empty($opt['csrf']))  $headers[] = 'X-CSRF-Token: ' . $opt['csrf'];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $raw  = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $code, 'headers' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
}

echo PHP_EOL . warna("UJI ASAP PACKSTOCK WMS", 'kuning') . PHP_EOL;
echo warna("Target : {$baseUrl}", 'abu') . PHP_EOL;
echo warna("Waktu  : " . date('Y-m-d H:i:s'), 'abu') . PHP_EOL . PHP_EOL;

// ---------------------------------------------------------------- 1. Ketersediaan
echo warna("1. Halaman hidup", 'kuning') . PHP_EOL;
$r = minta("{$baseUrl}/login");
hasil('Halaman login merespons', $r['status'] === 200, "HTTP {$r['status']}");
hasil('Halaman login memuat token CSRF', str_contains($r['body'], 'name="csrf-token"'));

// ---------------------------------------------------------------- 2. Pintu tertutup
echo PHP_EOL . warna("2. Akses tanpa login ditolak", 'kuning') . PHP_EOL;
foreach ([
    'api/stats.php'                   => 401,
    'api/materials.php?action=list'   => 401,
    'api/maintenance.php?action=stats' => 401,
] as $path => $harap) {
    $r = minta("{$baseUrl}/{$path}");
    hasil("Tanpa login: {$path}", $r['status'] === $harap, "HTTP {$r['status']} (harap {$harap})");
}

// ---------------------------------------------------------------- 3. Berkas rahasia
echo PHP_EOL . warna("3. Berkas sensitif tidak bocor", 'kuning') . PHP_EOL;
foreach ([
    'config/packstock.sqlite',
    'config/env.php',
    'config/google_sheets.json',
    '.git/HEAD',
    'config/backups/',
] as $path) {
    $r = minta("{$baseUrl}/{$path}");
    $aman = in_array($r['status'], [403, 404], true) || ($r['status'] === 200 && trim($r['body']) === '');
    hasil("Tertutup: {$path}", $aman, "HTTP {$r['status']}, " . strlen($r['body']) . " byte");
}

// ---------------------------------------------------------------- 4. Header keamanan
echo PHP_EOL . warna("4. Header keamanan terpasang", 'kuning') . PHP_EOL;
$r = minta("{$baseUrl}/login");
foreach ([
    'X-Content-Type-Options'   => 'nosniff',
    'X-Frame-Options'          => 'SAMEORIGIN',
    'Referrer-Policy'          => 'strict-origin',
    'Content-Security-Policy'  => "default-src 'self'",
] as $header => $isi) {
    hasil("Header {$header}", stripos($r['headers'], $header . ':') !== false && stripos($r['headers'], $isi) !== false);
}
// Atribut cookie hanya terlihat pada Set-Cookie, dan server tidak mengirimkannya
// bila permintaan sudah membawa sesi. Jadi periksa dari sesi yang benar-benar baru.
$jarLama = $cookieJar;
$cookieJar = tempnam(sys_get_temp_dir(), 'packstock_smoke_baru_');
$segar = minta("{$baseUrl}/login");
@unlink($cookieJar);
$cookieJar = $jarLama;

$setCookie = '';
foreach (explode("\n", $segar['headers']) as $baris) {
    if (stripos($baris, 'set-cookie:') === 0) { $setCookie = $baris; break; }
}
hasil('Cookie sesi HttpOnly', stripos($setCookie, 'HttpOnly') !== false, $setCookie ? '' : 'Set-Cookie tidak ditemukan');
hasil('Cookie sesi SameSite', stripos($setCookie, 'SameSite') !== false, $setCookie ? '' : 'Set-Cookie tidak ditemukan');

// ---------------------------------------------------------------- 5. Login & CSRF
if ($username === '' || $password === '') {
    echo PHP_EOL . warna("5. Login dilewati (username/password tidak diberikan)", 'abu') . PHP_EOL;
} else {
    echo PHP_EOL . warna("5. Login, CSRF, dan data inti", 'kuning') . PHP_EOL;

    $r = minta("{$baseUrl}/api/auth.php?action=login", [
        'post' => json_encode(['username' => $username, 'password' => $password]),
        'json' => true,
    ]);
    $login = json_decode($r['body'], true);
    hasil('Login kredensial benar', !empty($login['success']), "HTTP {$r['status']}");

    $r = minta("{$baseUrl}/api/auth.php?action=login", [
        'post' => json_encode(['username' => $username, 'password' => $password . '_salah']),
        'json' => true,
    ]);
    hasil('Login password salah ditolak 401', $r['status'] === 401, "HTTP {$r['status']}");

    // Login ulang karena percobaan gagal di atas tidak mengubah sesi.
    minta("{$baseUrl}/api/auth.php?action=login", [
        'post' => json_encode(['username' => $username, 'password' => $password]),
        'json' => true,
    ]);

    // Ambil token CSRF dari halaman.
    $home = minta("{$baseUrl}/admin/", ['follow' => true]);
    preg_match('/name="csrf-token" content="([a-f0-9]+)"/', $home['body'], $m);
    $csrf = $m[1] ?? '';
    hasil('Token CSRF tersedia setelah login', $csrf !== '');

    $r = minta("{$baseUrl}/api/users.php?action=__smoke__", ['post' => '{}', 'json' => true]);
    $j = json_decode($r['body'], true);
    hasil('POST tanpa token CSRF ditolak', $r['status'] === 403 && !empty($j['csrf_expired']), "HTTP {$r['status']}");

    $r = minta("{$baseUrl}/api/users.php?action=__smoke__", ['post' => '{}', 'json' => true, 'csrf' => $csrf]);
    hasil('POST dengan token CSRF diterima', $r['status'] === 400, "HTTP {$r['status']} (400 = aksi tidak dikenal, CSRF lolos)");

    echo PHP_EOL . warna("6. Data inti terbaca", 'kuning') . PHP_EOL;
    foreach ([
        'api/stats.php'                 => 'stats',
        'api/materials.php?action=list' => 'data',
        'api/tasks.php?action=list'     => 'data',
        'api/inbound.php?action=list'   => 'data',
        'api/outbound.php?action=list'  => 'data',
    ] as $path => $kunci) {
        $r = minta("{$baseUrl}/{$path}");
        $j = json_decode($r['body'], true);
        hasil("Baca {$path}", $r['status'] === 200 && !empty($j['success']) && isset($j[$kunci]), "HTTP {$r['status']}");
    }
}

// ---------------------------------------------------------------- 7. Integritas stok
echo PHP_EOL . warna("7. Integritas stok (dibaca langsung dari database)", 'kuning') . PHP_EOL;
try {
    require_once __DIR__ . '/../config/database.php';
    $pdo = Database::getConnection();
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $selisih = 0; $diperiksa = 0;
    foreach ($pdo->query("SELECT id, code, current_stock FROM materials") as $mat) {
        $stmt = $pdo->prepare("SELECT stock_after FROM stock_mutations WHERE material_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$mat['id']]);
        $terakhir = $stmt->fetchColumn();
        if ($terakhir === false) continue;
        $diperiksa++;
        if (abs((float)$terakhir - (float)$mat['current_stock']) > 0.001) $selisih++;
    }
    hasil('Stok master sinkron dengan buku mutasi', $selisih === 0, "{$diperiksa} SKU diperiksa, {$selisih} selisih");

    $negatif = (int)$pdo->query("SELECT COUNT(*) FROM materials WHERE current_stock < 0")->fetchColumn();
    hasil('Tidak ada SKU berstok negatif', $negatif === 0, "{$negatif} SKU negatif");

    foreach (['login_attempts', 'system_audit_logs', 'material_batches'] as $t) {
        try { $pdo->query("SELECT 1 FROM `{$t}` LIMIT 1"); hasil("Tabel {$t} ada", true); }
        catch (Throwable $e) { hasil("Tabel {$t} ada", false, 'tabel tidak ditemukan'); }
    }

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    hasil('Terhubung ke MySQL (bukan fallback SQLite)', $driver === 'mysql', "driver: {$driver}");
} catch (Throwable $e) {
    hasil('Koneksi database', false, $e->getMessage());
}

@unlink($cookieJar);

// ---------------------------------------------------------------- Ringkasan
echo PHP_EOL . str_repeat('-', 72) . PHP_EOL;
echo warna("LULUS: {$lulus}", 'hijau') . '   ' . ($gagal ? warna("GAGAL: {$gagal}", 'merah') : warna("GAGAL: 0", 'abu')) . PHP_EOL;
if ($gagal) {
    echo PHP_EOL . warna("Yang perlu diperiksa:", 'merah') . PHP_EOL;
    foreach ($catatan as $c) echo "  - {$c}" . PHP_EOL;
}
echo PHP_EOL;
exit($gagal > 0 ? 1 : 0);
