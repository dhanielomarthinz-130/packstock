<?php
/**
 * scripts/unit_test.php — Uji unit fungsi inti PackStock WMS
 *
 * Menguji logika yang paling mudah rusak diam-diam: pembacaan angka desimal
 * Indonesia, batas kuantitas transaksi, syarat password, dan normalisasi
 * tanggal kedaluwarsa. Semua murni perhitungan — tidak menyentuh database.
 *
 * Jalankan sebelum push:
 *   php scripts/unit_test.php
 *
 * Kode keluar: 0 bila semua lulus, 1 bila ada yang gagal.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Skrip ini hanya untuk dijalankan lewat command line.');
}

// Muat fungsi tanpa menyalakan sesi atau koneksi database.
if (session_status() === PHP_SESSION_NONE) {
    $_SERVER['REQUEST_METHOD'] = 'GET';
}
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/batch_helper.php';

$lulus = 0;
$gagal = 0;
$daftarGagal = [];

function w(string $t, string $c): string {
    $k = ['hijau' => "\033[32m", 'merah' => "\033[31m", 'kuning' => "\033[33m", 'abu' => "\033[90m"];
    return ($k[$c] ?? '') . $t . "\033[0m";
}

function cek(string $nama, $didapat, $diharap): void {
    global $lulus, $gagal, $daftarGagal;
    $ok = $didapat === $diharap;
    if ($ok) {
        $lulus++;
        echo '  ' . w('LULUS', 'hijau') . '  ' . $nama . PHP_EOL;
    } else {
        $gagal++;
        $d = var_export($didapat, true);
        $h = var_export($diharap, true);
        $daftarGagal[] = "{$nama} — didapat {$d}, diharap {$h}";
        echo '  ' . w('GAGAL', 'merah') . '  ' . $nama . w("  (didapat {$d}, diharap {$h})", 'abu') . PHP_EOL;
    }
}

function cekNull(string $nama, $didapat, bool $harusNull): void {
    cek($nama, $didapat === null, $harusNull);
}

echo PHP_EOL . w('UJI UNIT PACKSTOCK WMS', 'kuning') . PHP_EOL . PHP_EOL;

// -------------------------------------------------------------------------
echo w('parseNumberDecimal — angka Indonesia & internasional', 'kuning') . PHP_EOL;
cek('Bilangan bulat biasa',                parseNumberDecimal('1500'),      1500.0);
cek('Desimal titik (84.2)',                parseNumberDecimal('84.2'),      84.2);
cek('Desimal koma Indonesia (84,2)',       parseNumberDecimal('84,2'),      84.2);
cek('Ribuan titik + desimal koma',         parseNumberDecimal('1.712,36'),  1712.36);
cek('Ribuan koma + desimal titik',         parseNumberDecimal('1,712.36'),  1712.36);
cek('Ribuan titik tanpa desimal',          parseNumberDecimal('1.500.000'), 1500000.0);
cek('Angka negatif',                       parseNumberDecimal('-25'),       -25.0);
cek('String kosong jadi 0',                parseNumberDecimal(''),          0.0);
cek('Teks bukan angka jadi 0',             parseNumberDecimal('abc'),       0.0);
cek('Sudah bertipe float',                 parseNumberDecimal(12.5),        12.5);
cek('Ada satuan menempel (250 Pcs)',       parseNumberDecimal('250 Pcs'),   250.0);

// -------------------------------------------------------------------------
echo PHP_EOL . w('validateQtyRange — batas kuantitas transaksi', 'kuning') . PHP_EOL;
cekNull('Qty wajar (500) diterima',            validateQtyRange(500.0),        true);
cekNull('Qty tepat di batas (1.000.000)',      validateQtyRange(1000000.0),    true);
cekNull('Qty di atas batas ditolak',           validateQtyRange(1000001.0),    false);
cekNull('Qty notasi ilmiah (1e20) ditolak',    validateQtyRange(1.0e20),       false);
cekNull('Qty INF ditolak',                     validateQtyRange(INF),          false);
cekNull('Qty NAN ditolak',                     validateQtyRange(NAN),          false);
cek('Pesan galat menyebut label kolom',
    str_contains((string)validateQtyRange(1.0e20, 'Jumlah Masuk'), 'Jumlah Masuk'), true);

// -------------------------------------------------------------------------
echo PHP_EOL . w('validatePasswordStrength — syarat password baru', 'kuning') . PHP_EOL;
cekNull('Password kuat diterima',              validatePasswordStrength('Gudang2026'),          true);
cekNull('Kurang dari 8 karakter ditolak',      validatePasswordStrength('Abc123'),              false);
cekNull('Tanpa angka ditolak',                 validatePasswordStrength('passwordku'),          false);
cekNull('Tanpa huruf ditolak',                 validatePasswordStrength('12345678'),            false);
cekNull('Password umum ditolak',               validatePasswordStrength('admin123'),            false);
cekNull('Memuat nama pengguna ditolak',        validatePasswordStrength('operator123', 'operator'), false);
cekNull('Nama pengguna beda tetap diterima',   validatePasswordStrength('Gudang2026', 'budi'),  true);

// -------------------------------------------------------------------------
echo PHP_EOL . w('normalizeExpDateToDb — tanggal kedaluwarsa', 'kuning') . PHP_EOL;
cek('Format DD-MM-YYYY',        normalizeExpDateToDb('25-12-2026'), '2026-12-25');
cek('Format DD/MM/YYYY',        normalizeExpDateToDb('25/12/2026'), '2026-12-25');
cek('Format DD-MM-YY',          normalizeExpDateToDb('25-12-26'),   '2026-12-25');
cek('Format YYYY-MM-DD',        normalizeExpDateToDb('2026-12-25'), '2026-12-25');
cek('Kosong jadi null',         normalizeExpDateToDb(''),           null);
cek('Tanda hubung jadi null',   normalizeExpDateToDb('-'),          null);

// -------------------------------------------------------------------------
echo PHP_EOL . w('validateUploadedPhoto — unggahan foto bukti', 'kuning') . PHP_EOL;
$pngValid = __DIR__ . '/../uploads/.uji_unit.png';
// PNG 1x1 piksel yang sah
file_put_contents($pngValid, base64_decode(
    'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
));
$palsu = __DIR__ . '/../uploads/.uji_unit_palsu.png';
file_put_contents($palsu, "<?php echo 'ini skrip, bukan gambar'; ?>");

cek('PNG asli diterima sebagai png',
    validateUploadedPhoto($pngValid, 'foto.png', filesize($pngValid)), 'png');
cek('Ekstensi gambar tapi isi skrip ditolak',
    validateUploadedPhoto($palsu, 'jahat.png', filesize($palsu)), null);
cek('Ekstensi salah tetap dibaca dari isi berkas',
    validateUploadedPhoto($pngValid, 'foto.jpg', filesize($pngValid)), 'png');
cek('Berkas kosong ditolak',
    validateUploadedPhoto($pngValid, 'foto.png', 0), null);
cek('Melebihi 8 MB ditolak',
    validateUploadedPhoto($pngValid, 'foto.png', 9 * 1024 * 1024), null);

@unlink($pngValid);
@unlink($palsu);

// -------------------------------------------------------------------------
echo PHP_EOL . str_repeat('-', 64) . PHP_EOL;
echo w("LULUS: {$lulus}", 'hijau') . '   ' . ($gagal ? w("GAGAL: {$gagal}", 'merah') : w('GAGAL: 0', 'abu')) . PHP_EOL;
if ($gagal) {
    echo PHP_EOL . w('Yang gagal:', 'merah') . PHP_EOL;
    foreach ($daftarGagal as $g) echo "  - {$g}" . PHP_EOL;
}
echo PHP_EOL;
exit($gagal > 0 ? 1 : 0);
