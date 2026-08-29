<?php
// includes/header.php - White & Green Enterprise Theme with Google Material Symbols
if (!isset($pageTitle)) {
    $pageTitle = 'PackStock WMS - Kemas / Consumble Stock Control & Task Assignment';
}
$baseUrl = Auth::getBaseUrl();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <!-- App Favicon & Touch Icon -->
  <link rel="icon" type="image/svg+xml" href="<?= $baseUrl ?>/assets/img/favicon.svg?v=<?= time() ?>">
  <link rel="alternate icon" type="image/svg+xml" href="<?= $baseUrl ?>/assets/img/favicon.svg">
  <link rel="apple-touch-icon" href="<?= $baseUrl ?>/assets/img/favicon.svg">
  <meta name="theme-color" content="#047857">
  
  <!-- Tailwind CSS CDN -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#f0fdf4',
              100: '#dcfce7',
              200: '#bbf7d0',
              300: '#86efac',
              400: '#4ade80',
              500: '#22c55e',
              600: '#16a34a',
              700: '#15803d',
              800: '#166534',
              900: '#14532d',
              950: '#052e16'
            }
          }
        }
      }
    }
  </script>

  <!-- Google Fonts: Inter & JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Google Material Symbols Outlined -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

  <!-- SheetJS (xlsx) for fast client-side Excel reading & export -->
  <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

  <!-- Flatpickr (Modern Date & Time Picker UI) -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>

  <!-- Custom CSS with Cache Buster -->
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/style.css?v=<?= time() ?>">
</head>
<body class="bg-slate-50 text-slate-800 antialiased selection:bg-emerald-600 selection:text-white font-sans text-sm">
  <div id="toast-container" class="fixed top-4 right-4 z-[9999] flex flex-col gap-2 max-w-sm pointer-events-none"></div>
