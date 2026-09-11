<?php
// includes/header.php - White & Green Enterprise Theme with Google Material Symbols
if (!isset($pageTitle)) {
    $pageTitle = 'PackStock WMS - Stock Kemas & Task Assignment';
}
$baseUrl = Auth::getBaseUrl();
$favIconUrl = (!empty($baseUrl) ? rtrim($baseUrl, '/') : '') . '/assets/img/favicon.svg';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="csrf-token" content="<?= htmlspecialchars(Auth::csrfToken()) ?>">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <!-- App Favicon & Touch Icon -->
  <link rel="icon" type="image/svg+xml" href="<?= $favIconUrl ?>?v=2">
  <link rel="alternate icon" type="image/svg+xml" href="<?= $favIconUrl ?>">
  <link rel="apple-touch-icon" href="<?= $favIconUrl ?>">
  <meta name="theme-color" content="#262363">
  
  <!--
    Tailwind CSS terkompilasi.

    Sebelumnya halaman memuat https://cdn.tailwindcss.com (Play CDN), yang
    mengompilasi seluruh CSS di dalam browser setiap kali halaman dibuka —
    dokumentasi Tailwind sendiri melarangnya untuk produksi. Berkas di bawah
    dibangun dari konfigurasi warna yang sama persis (brand / blue / navy).

    Cara membangun ulang setelah menambah kelas Tailwind baru — WAJIB dijalankan
    dari akar proyek, karena glob konten di tailwind.config.js bersifat relatif:

      cd C:\xampp\htdocs\packstock
      npx tailwindcss@3.4.17 -c ./tailwind.config.js \
        -i ./assets/css/tailwind.input.css -o ./assets/css/tailwind.css --minify

    Hasil yang benar berukuran sekitar 200 KB. Bila jauh lebih kecil, berarti
    glob konten tidak menemukan berkas proyek dan kelas-kelasnya ikut hilang.
  -->

  <!-- Google Fonts: Inter & JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Google Material Symbols Outlined -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

  <!--
    Pustaka pihak ketiga.
    Setiap URL dipatok ke versi tertentu dan diverifikasi dengan Subresource Integrity:
    bila berkas di CDN berubah isinya, browser menolak menjalankannya. Sebelumnya
    flatpickr dimuat tanpa nomor versi sama sekali, sehingga isinya bisa berganti
    sewaktu-waktu tanpa perubahan apa pun di sisi kita.
  -->

  <!-- SheetJS (xlsx) for fast client-side Excel reading & export -->
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"
          integrity="sha384-vtjasyidUo0kW94K5MXDXntzOJpQgBKXmE7e2Ga4LG0skTTLeBi97eFAXsqewJjw"
          crossorigin="anonymous"></script>

  <!-- Chart.js for High Performance Interactive Dashboard Charts -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"
          integrity="sha384-9nhczxUqK87bcKHh20fSQcTGD4qq5GhayNYSYWqwBkINBhOfQLg/P5HG5lF1urn4"
          crossorigin="anonymous"></script>

  <!-- Flatpickr (Modern Date & Time Picker UI) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css"
        integrity="sha384-RkASv+6KfBMW9eknReJIJ6b3UnjKOKC5bOUaNgIY778NFbQ8MtWq9Lr/khUgqtTt"
        crossorigin="anonymous">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"
          integrity="sha384-5JqMv4L/Xa0hfvtF06qboNdhvuYXUku9ZrhZh3bSk8VXF0A/RuSLHpLsSV9Zqhl6"
          crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/id.js"
          integrity="sha384-KMhDAY3QD073iimt/X068iLaMgNPBD+J57j7adrjgvlWbfAv2ONSvLFyOtU9TzDN"
          crossorigin="anonymous"></script>

  <!-- html2canvas for Generating High Resolution PNG Document Receipts -->
  <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"
          integrity="sha384-ZZ1pncU3bQe8y31yfZdMFdSpttDoPmOZg2wguVK9almUodir1PghgT0eY7Mrty8H"
          crossorigin="anonymous"></script>

  <!-- Custom CSS with Cache Buster -->
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/style.css?v=<?= time() ?>">

  <!--
    tailwind.css sengaja dimuat SETELAH style.css.
    Play CDN dulu menyuntikkan gayanya di akhir <head> saat runtime, sehingga
    utility seperti text-[13px] menang atas .material-symbols-outlined{font-size:20px}
    di style.css. Memuatnya lebih awal membuat seluruh ikon terkunci di 20px.
  -->
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/tailwind.css?v=<?= @filemtime(__DIR__ . '/../assets/css/tailwind.css') ?: '1' ?>">
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-blue-600 selection:text-white font-sans text-sm">
  <div id="toast-container" class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 max-w-sm pointer-events-none"></div>
